#!/usr/bin/env python3
"""
mark1 read-only MCP server.

Exposes the mark1 MySQL database to Claude as a small set of *read-only* tools so
you can ask analytical questions ("what can boost sales based on Sales
Transactions data?") against live data without ever touching it.

Safety model (defense in depth):
  1. Connect as a dedicated SELECT-only MySQL user (the hard guarantee).
  2. The server still refuses anything that isn't a single SELECT/WITH query.
  3. Every query runs in a READ ONLY, READ COMMITTED session and is row/time
     capped, so it puts as little load on the DB as possible.

Transport: stdio (works with Claude Desktop's mcpServers config). See README.md.
"""

from __future__ import annotations

import os
import re
import sys
import json
import datetime
import decimal
import hashlib
import contextvars
from typing import Any

import pymysql
from mcp.server.fastmcp import FastMCP


def _load_dotenv() -> None:
    """Minimal .env loader (no external dependency). Values already present in
    the real environment (e.g. passed by Claude Desktop's config) win."""
    path = os.path.join(os.path.dirname(__file__), ".env")
    if not os.path.exists(path):
        return
    with open(path, "r", encoding="utf-8") as fh:
        for line in fh:
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            key, _, val = line.partition("=")
            key, val = key.strip(), val.strip().strip('"').strip("'")
            os.environ.setdefault(key, val)


_load_dotenv()

# --------------------------------------------------------------------------- #
# Configuration (from environment)
# --------------------------------------------------------------------------- #
DB_HOST = os.getenv("MARK1_DB_HOST", "127.0.0.1")
DB_PORT = int(os.getenv("MARK1_DB_PORT", "3306"))
DB_NAME = os.getenv("MARK1_DB_DATABASE", "mark1")
DB_USER = os.getenv("MARK1_DB_USERNAME", "mark1_ro")
DB_PASS = os.getenv("MARK1_DB_PASSWORD", "")

# Optional route-writer DB user for apply_route_sequence. The GRANT for this
# user is column-level — UPDATE (sequence) on ops_job_items / ops_job_tasks and
# nothing else (see README) — so even with these credentials set, the server
# physically cannot write any other column or table. Leave unset to keep the
# MCP fully read-only (the tool then refuses with a clear message).
DB_WRITE_USER = os.getenv("MARK1_DB_WRITE_USERNAME", "")
DB_WRITE_PASS = os.getenv("MARK1_DB_WRITE_PASSWORD", "")

# Hard caps so a single question can never pull the whole DB into context or
# make the server chew through a huge result set.
DEFAULT_ROW_LIMIT = int(os.getenv("MARK1_DEFAULT_ROW_LIMIT", "500"))
MAX_ROW_LIMIT = int(os.getenv("MARK1_MAX_ROW_LIMIT", "5000"))
STATEMENT_TIMEOUT_MS = int(os.getenv("MARK1_STATEMENT_TIMEOUT_MS", "15000"))

DICT_PATH = os.path.join(os.path.dirname(__file__), "data_dictionary.md")

mcp = FastMCP("mark1-readonly")

# --------------------------------------------------------------------------- #
# Access control / audit configuration
# --------------------------------------------------------------------------- #
# Transport: "stdio" (Claude Desktop launches this process locally) or "http"
# (hosted once; many people connect over HTTPS, each with their own bearer token).
TRANSPORT = os.getenv("MARK1_MCP_TRANSPORT", "stdio").strip().lower()
HTTP_HOST = os.getenv("MARK1_MCP_HTTP_HOST", "127.0.0.1")
HTTP_PORT = int(os.getenv("MARK1_MCP_HTTP_PORT", "8765"))
AUDIT_PATH = os.getenv(
    "MARK1_MCP_AUDIT_LOG", os.path.join(os.path.dirname(__file__), "audit.log")
)

# --------------------------------------------------------------------------- #
# OAuth (Laravel Passport) — second auth method alongside mk1_ tokens.
# The mcp/ folder lives inside the Laravel app, so the default public key path
# is the app's own storage/oauth-public.key.
# --------------------------------------------------------------------------- #
MCP_PUBLIC_URL = os.getenv("MARK1_MCP_PUBLIC_URL", "https://mcp.happyice.com.sg/mcp")
OAUTH_ISSUER = os.getenv("MARK1_OAUTH_ISSUER", "https://sys.happyice.com.sg")
OAUTH_PUBLIC_KEY_PATH = os.getenv(
    "MARK1_OAUTH_PUBLIC_KEY_PATH",
    os.path.join(os.path.dirname(__file__), "..", "storage", "oauth-public.key"),
)

from urllib.parse import urlsplit as _urlsplit
_split = _urlsplit(MCP_PUBLIC_URL)
RESOURCE_METADATA_URL = f"{_split.scheme}://{_split.netloc}/.well-known/oauth-protected-resource"

# Per-request identity in HTTP mode (set by the bearer-auth ASGI middleware).
_http_identity: contextvars.ContextVar = contextvars.ContextVar(
    "mcp_http_identity", default=None
)

# --------------------------------------------------------------------------- #
# SQL guardrails
# --------------------------------------------------------------------------- #
# Statements we never allow, even though the read-only DB user would also block
# the writes. This catches mistakes early and keeps the error message clear.
# NOTE: these checks run against a copy of the query with string/identifier
# literals blanked out, so a data value like WHERE action = 'delete' is fine.
_FORBIDDEN = re.compile(
    r"\b(insert|update|delete|replace|merge|drop|alter|create|truncate|grant|"
    r"revoke|rename|lock|unlock|call|do|handler|load_file|into\s+outfile|"
    r"into\s+dumpfile|set\s+|use\s+|begin|commit|rollback|start\s+transaction)\b",
    re.IGNORECASE,
)

_LIMIT_OR_PAREN = re.compile(r"\(|\)|\blimit\b", re.IGNORECASE)


def _strip_sql_comments(sql: str) -> str:
    """Remove -- line comments and /* */ block comments before validation."""
    sql = re.sub(r"/\*.*?\*/", " ", sql, flags=re.DOTALL)
    sql = re.sub(r"--[^\n]*", " ", sql)
    sql = re.sub(r"#[^\n]*", " ", sql)
    return sql.strip()


def _blank_literals(sql: str) -> str:
    """Return a copy of the SQL with the *contents* of quoted string literals
    ('...', "...") and backtick identifiers (`...`) replaced by spaces, keeping
    the quote characters and overall length. Structural checks (single
    statement, leading verb, forbidden keywords, top-level LIMIT) run on this
    copy so they never trip on ordinary data values such as
    `WHERE status = 'update'` or a column literally named `delete`."""
    out = []
    i, n = 0, len(sql)
    quote = None
    while i < n:
        ch = sql[i]
        if quote is None:
            out.append(ch)
            if ch in ("'", '"', "`"):
                quote = ch
            i += 1
            continue
        # inside a quoted region
        if ch == "\\" and quote in ("'", '"') and i + 1 < n:
            out.append("  ")           # backslash escape -> blank both chars
            i += 2
            continue
        if ch == quote:
            if i + 1 < n and sql[i + 1] == quote:
                out.append("  ")       # doubled quote = escaped quote
                i += 2
                continue
            out.append(ch)             # closing quote
            quote = None
            i += 1
            continue
        out.append("\n" if ch == "\n" else " ")
        i += 1
    return "".join(out)


def _validate_select_only(sql: str) -> str:
    """Return a cleaned single SELECT/WITH statement or raise ValueError.

    Returns the ORIGINAL (comment-stripped) body for execution; only the checks
    look at the literal-blanked copy."""
    body = _strip_sql_comments(sql).rstrip(";").strip()
    if not body:
        raise ValueError("Empty query.")

    scan = _blank_literals(body)

    if ";" in scan:
        raise ValueError("Only a single statement is allowed (no semicolons).")

    lowered = scan.lstrip().lower()
    if not (lowered.startswith("select") or lowered.startswith("with")):
        raise ValueError("Only SELECT / WITH queries are allowed.")

    if _FORBIDDEN.search(scan):
        raise ValueError("Query contains a forbidden (write/DDL) keyword.")

    return body


def _has_top_level_limit(sql: str) -> bool:
    """True if the query has a LIMIT at parenthesis depth 0 (i.e. its own outer
    LIMIT), ignoring LIMITs that live inside subqueries or string literals."""
    scan = _blank_literals(sql)
    depth = 0
    for m in _LIMIT_OR_PAREN.finditer(scan):
        tok = m.group(0)
        if tok == "(":
            depth += 1
        elif tok == ")":
            depth = max(0, depth - 1)
        elif depth == 0:
            return True
    return False


def _ensure_outer_limit(sql: str, max_rows: int) -> str:
    """Append an outer LIMIT unless the query already has its own top-level one.
    This guarantees the DB itself stops producing rows early, so a query without
    a LIMIT can't scan-and-return an unbounded result set."""
    if _has_top_level_limit(sql):
        return sql
    return f"{sql}\nLIMIT {max_rows}"


# --------------------------------------------------------------------------- #
# DB access
# --------------------------------------------------------------------------- #
def _connect() -> pymysql.connections.Connection:
    conn = pymysql.connect(
        host=DB_HOST,
        port=DB_PORT,
        user=DB_USER,
        password=DB_PASS,
        database=DB_NAME,
        cursorclass=pymysql.cursors.DictCursor,
        connect_timeout=10,
        read_timeout=max(20, STATEMENT_TIMEOUT_MS // 1000 + 5),
        charset="utf8mb4",
        autocommit=True,
    )
    # Session-level guards, applied once per connection (best-effort: skip any
    # knob the server variant doesn't support). READ ONLY + READ COMMITTED keep
    # the query lightweight (no locking / undo bookkeeping); MAX_EXECUTION_TIME
    # kills any runaway SELECT so it can't hammer the DB.
    with conn.cursor() as cur:
        for guard in (
            "SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED",
            "SET SESSION TRANSACTION READ ONLY",
            f"SET SESSION MAX_EXECUTION_TIME={STATEMENT_TIMEOUT_MS}",
        ):
            try:
                cur.execute(guard)
            except Exception:
                pass
    return conn


def _jsonable(value: Any) -> Any:
    if isinstance(value, (datetime.datetime, datetime.date)):
        return value.isoformat()
    if isinstance(value, datetime.timedelta):
        return str(value)
    if isinstance(value, decimal.Decimal):
        # keep integers clean, floats as float
        return int(value) if value == value.to_integral_value() else float(value)
    if isinstance(value, (bytes, bytearray)):
        try:
            return value.decode("utf-8")
        except UnicodeDecodeError:
            return value.hex()
    return value


def _rows_json(rows: list) -> list:
    return [{k: _jsonable(v) for k, v in r.items()} for r in rows]


def _run(sql: str, max_rows: int) -> dict:
    conn = _connect()
    try:
        with conn.cursor() as cur:
            cur.execute(sql)
            rows = cur.fetchmany(max_rows + 1)
            truncated = len(rows) > max_rows
            rows = rows[:max_rows]
            return {
                "row_count": len(rows),
                "truncated": truncated,
                "rows": _rows_json(rows),
            }
    finally:
        conn.close()


def _connect_write() -> pymysql.connections.Connection:
    """Connection as the route-writer user (column-level UPDATE grant only).
    No READ ONLY session guard, autocommit off — the caller owns the
    transaction. Raises if the write credentials are not configured."""
    if not DB_WRITE_USER or not DB_WRITE_PASS:
        raise RuntimeError(
            "Route writing is not enabled on this MCP server "
            "(MARK1_DB_WRITE_USERNAME/PASSWORD not set)."
        )
    return pymysql.connect(
        host=DB_HOST,
        port=DB_PORT,
        user=DB_WRITE_USER,
        password=DB_WRITE_PASS,
        database=DB_NAME,
        cursorclass=pymysql.cursors.DictCursor,
        connect_timeout=10,
        read_timeout=30,
        charset="utf8mb4",
        autocommit=False,
    )


# --------------------------------------------------------------------------- #
# Access control (tokens bound to mark1 users) + audit
# --------------------------------------------------------------------------- #
def _authenticate(token: str):
    """Validate a plaintext token against mcp_access_tokens. Returns an identity
    dict (user_id, name, email) for an active user holding a non-revoked token,
    else None. Read-only: never writes."""
    token = (token or "").strip()
    if not token:
        return None
    token_hash = hashlib.sha256(token.encode("utf-8")).hexdigest()
    try:
        conn = _connect()
        try:
            with conn.cursor() as cur:
                cur.execute(
                    "SELECT t.user_id AS user_id, u.name AS name, u.email AS email, "
                    "u.is_active AS is_active "
                    "FROM mcp_access_tokens t JOIN users u ON u.id = t.user_id "
                    "WHERE t.token_hash = %s AND t.revoked_at IS NULL LIMIT 1",
                    (token_hash,),
                )
                row = cur.fetchone()
        finally:
            conn.close()
    except Exception:
        return None
    if not row or not row.get("is_active"):
        return None
    return {"user_id": row.get("user_id"), "name": row.get("name"), "email": row.get("email")}


_OAUTH_PUBKEY_CACHE = {"pem": None, "tried": False}


def _oauth_public_key():
    """Read and cache Passport's RS256 public key. Missing key = OAuth path
    disabled (returns None); mk1_ tokens keep working regardless."""
    if not _OAUTH_PUBKEY_CACHE["tried"]:
        _OAUTH_PUBKEY_CACHE["tried"] = True
        try:
            with open(OAUTH_PUBLIC_KEY_PATH, "r", encoding="utf-8") as fh:
                _OAUTH_PUBKEY_CACHE["pem"] = fh.read()
        except Exception as e:  # noqa: BLE001
            print(
                f"MCP OAuth disabled: cannot read public key at "
                f"{OAUTH_PUBLIC_KEY_PATH}: {e}",
                file=sys.stderr,
            )
    return _OAUTH_PUBKEY_CACHE["pem"]


def _authenticate_oauth(bearer: str):
    """Validate a Laravel Passport access token (RS256 JWT) and map it to an
    authorised mark1 user. Defense in depth, all read-only:
      1. Signature + expiry against Passport's public key.
      2. jti looked up in oauth_access_tokens → not revoked, user active.
      3. Scope gate: explicit 'mcp' scope, or an unscoped token from a pure
         authorization-code client. Personal-access / password clients (the
         machines & APK) are ALWAYS rejected here.
      4. Grant gate: the user must hold an active row in mcp_access_tokens —
         the Admin ▸ MCP Access page stays the per-person on/off switch for
         both auth methods.
    Returns an identity dict or None. Never writes."""
    pem = _oauth_public_key()
    if not pem:
        return None
    try:
        import jwt as pyjwt
        claims = pyjwt.decode(
            bearer, key=pem, algorithms=["RS256"],
            options={"verify_aud": False}, leeway=30,
        )
    except Exception:  # invalid signature, expired, malformed
        return None
    jti = claims.get("jti")
    sub = claims.get("sub")
    scopes = claims.get("scopes") or []
    if not jti or sub is None:
        return None
    try:
        conn = _connect()
        try:
            with conn.cursor() as cur:
                cur.execute(
                    "SELECT oat.revoked AS revoked, oat.user_id AS user_id, "
                    "u.is_active AS is_active, u.name AS name, u.email AS email, "
                    "c.personal_access_client AS pac, c.password_client AS pwc, "
                    "(SELECT COUNT(*) FROM mcp_access_tokens t "
                    " WHERE t.user_id = u.id AND t.revoked_at IS NULL) AS grant_count "
                    "FROM oauth_access_tokens oat "
                    "JOIN users u ON u.id = oat.user_id "
                    "JOIN oauth_clients c ON c.id = oat.client_id "
                    "WHERE oat.id = %s LIMIT 1",
                    (jti,),
                )
                row = cur.fetchone()
        finally:
            conn.close()
    except Exception:
        return None
    if not row or row.get("revoked") or not row.get("is_active"):
        return None
    if str(row.get("user_id")) != str(sub):
        return None
    if not row.get("grant_count"):
        return None  # admin has not granted this user MCP access
    if "mcp" not in scopes and (scopes or row.get("pac") or row.get("pwc")):
        return None
    return {
        "user_id": row.get("user_id"),
        "name": row.get("name"),
        "email": row.get("email"),
        "via": "oauth",
    }


def _resolve_identity(token: str):
    """Dispatch: mk1_ opaque tokens → mcp_access_tokens table; JWT-shaped
    tokens (two dots) → Passport OAuth validation."""
    token = (token or "").strip()
    if not token:
        return None
    if token.count(".") == 2 and not token.startswith("mk1_"):
        return _authenticate_oauth(token)
    return _authenticate(token)


def _authorization_server_metadata() -> dict:
    """RFC 8414 Authorization Server Metadata, mirrored on the MCP domain for
    OLDER MCP clients that probe the resource origin directly (pre-2025-06
    spec). Newer clients follow WWW-Authenticate → protected-resource metadata
    → the same endpoints on the Laravel app. Keep in sync with
    McpOAuthController::authorizationServerMetadata."""
    base = OAUTH_ISSUER.rstrip("/")
    return {
        "issuer": base,
        "authorization_endpoint": base + "/oauth/authorize",
        "token_endpoint": base + "/oauth/token",
        "registration_endpoint": base + "/api/oauth/register",
        "response_types_supported": ["code"],
        "grant_types_supported": ["authorization_code", "refresh_token"],
        "code_challenge_methods_supported": ["S256"],
        "token_endpoint_auth_methods_supported": ["none"],
        "scopes_supported": ["mcp"],
    }


def _protected_resource_metadata() -> dict:
    """RFC 9728 Protected Resource Metadata — tells OAuth clients which
    authorization server protects this resource."""
    return {
        "resource": MCP_PUBLIC_URL,
        "authorization_servers": [OAUTH_ISSUER],
        "scopes_supported": ["mcp"],
        "bearer_methods_supported": ["header"],
        "resource_name": "mark1 read-only MCP",
    }


def _current_identity():
    """Resolve the caller's identity for the active transport.
      - http:  set per-request by the bearer-auth middleware (None if unset).
      - stdio: if MARK1_MCP_TOKEN is set, validate it live (so revocation takes
               effect without a restart); if unset, treat as the trusted local
               owner (the SELECT-only DB grant remains the guard, as before)."""
    if TRANSPORT == "http":
        return _http_identity.get()
    tok = os.getenv("MARK1_MCP_TOKEN", "").strip()
    if tok:
        return _authenticate(tok)
    return {"user_id": None, "name": "local", "email": "local"}


def _audit(identity: dict, action: str, detail: str = "") -> None:
    """Append one line per tool call: who ran what. File-based, because the
    read-only DB user cannot write a last-used timestamp back to the table."""
    try:
        who = (identity or {}).get("email") or (identity or {}).get("name") or "?"
        uid = (identity or {}).get("user_id")
        ts = datetime.datetime.now().isoformat(timespec="seconds")
        snippet = " ".join((detail or "").split())
        if len(snippet) > 500:
            snippet = snippet[:500] + "\u2026"
        with open(AUDIT_PATH, "a", encoding="utf-8") as fh:
            fh.write(f"{ts}\t{uid}\t{who}\t{action}\t{snippet}\n")
    except Exception:
        pass


def _guard(action: str, detail: str = ""):
    """Return (identity, None) when authorised, else (None, error_json_str)."""
    identity = _current_identity()
    if not identity:
        return None, json.dumps(
            {"error": "Unauthorized: missing, invalid, or revoked MCP token."}
        )
    _audit(identity, action, detail)
    return identity, None


# --------------------------------------------------------------------------- #
# Tools
# --------------------------------------------------------------------------- #
@mcp.tool()
def data_dictionary() -> str:
    """Return the curated guide to the most useful mark1 tables and columns
    (Sales Transactions and friends). Read this first when planning a query."""
    _ident, _err = _guard("data_dictionary")
    if _err:
        return _err
    try:
        with open(DICT_PATH, "r", encoding="utf-8") as fh:
            return fh.read()
    except FileNotFoundError:
        return "data_dictionary.md not found next to server.py."


@mcp.tool()
def list_tables() -> str:
    """List all tables in the mark1 database with their approximate row counts."""
    _ident, _err = _guard("list_tables")
    if _err:
        return _err
    sql = (
        "SELECT table_name, table_rows "
        "FROM information_schema.tables "
        "WHERE table_schema = %s ORDER BY table_name"
    )
    try:
        conn = _connect()
        try:
            with conn.cursor() as cur:
                cur.execute(sql, (DB_NAME,))
                rows = cur.fetchall()
        finally:
            conn.close()
        return json.dumps(_rows_json(rows), indent=2)
    except pymysql.MySQLError as e:
        return json.dumps({"error": f"MySQL error: {e}"})
    except Exception as e:  # noqa: BLE001
        return json.dumps({"error": f"Unexpected error: {e}"})


@mcp.tool()
def describe_table(table: str) -> str:
    """Return the column names, types, nullability and keys for one table.
    Use this to confirm exact column names before writing a query."""
    _ident, _err = _guard("describe_table", table or "")
    if _err:
        return _err
    if not re.fullmatch(r"[A-Za-z0-9_]+", table or ""):
        return json.dumps({"error": "Invalid table name."})
    sql = (
        "SELECT column_name, column_type, is_nullable, column_key, column_default, column_comment "
        "FROM information_schema.columns "
        "WHERE table_schema = %s AND table_name = %s "
        "ORDER BY ordinal_position"
    )
    try:
        conn = _connect()
        try:
            with conn.cursor() as cur:
                cur.execute(sql, (DB_NAME, table))
                rows = cur.fetchall()
        finally:
            conn.close()
        if not rows:
            return json.dumps({"error": f"No such table: {table}"})
        return json.dumps(_rows_json(rows), indent=2)
    except pymysql.MySQLError as e:
        return json.dumps({"error": f"MySQL error: {e}"})
    except Exception as e:  # noqa: BLE001
        return json.dumps({"error": f"Unexpected error: {e}"})


@mcp.tool()
def mcp_usage_log(lines: int = 50) -> str:
    """Return the most recent MCP audit-log lines — who ran which tool and when
    (tab-separated: time, user_id, email, action, detail). Governance/oversight
    for admins; capped at 200 lines. Takes only a line count — no paths, no
    shell."""
    _ident, _err = _guard("mcp_usage_log")
    if _err:
        return _err
    try:
        lines = max(1, min(int(lines), 200))
    except (TypeError, ValueError):
        lines = 50
    try:
        from collections import deque
        with open(AUDIT_PATH, "r", encoding="utf-8", errors="replace") as fh:
            tail = deque(fh, maxlen=lines)
        return "".join(tail) or "(audit log is empty)"
    except FileNotFoundError:
        return "(no audit log yet)"
    except Exception as e:  # noqa: BLE001
        return json.dumps({"error": f"{e}"})


@mcp.tool()
def server_stats() -> str:
    """One read-only snapshot of the mark1 server's health: disk, memory, swap,
    load average, uptime, MySQL footprint (total + top tables) and local file
    hotspots. Takes NO arguments and runs NO shell commands — every metric is a
    fixed /proc / statvfs / information_schema read, so there is nothing to
    inject into. Ask things like "how is the server doing?" or "is the disk
    filling up again?"."""
    _ident, _err = _guard("server_stats")
    if _err:
        return _err

    stats = {"collected_at": datetime.datetime.now().isoformat(timespec="seconds")}

    # Disk (the df -h / numbers)
    try:
        st = os.statvfs("/")
        total = st.f_blocks * st.f_frsize
        free = st.f_bavail * st.f_frsize
        used = total - (st.f_bfree * st.f_frsize)
        stats["disk_root"] = {
            "total_gb": round(total / 1024 ** 3, 1),
            "used_gb": round(used / 1024 ** 3, 1),
            "free_gb": round(free / 1024 ** 3, 1),
            "used_pct": round(used * 100.0 / total, 1) if total else None,
        }
    except Exception as e:  # noqa: BLE001
        stats["disk_root"] = {"error": str(e)}

    # Memory + swap
    try:
        mem = {}
        with open("/proc/meminfo", "r", encoding="utf-8") as fh:
            for line in fh:
                key, _, val = line.partition(":")
                try:
                    mem[key.strip()] = int(val.strip().split()[0])  # kB
                except (ValueError, IndexError):
                    pass
        mt, ma = mem.get("MemTotal", 0), mem.get("MemAvailable", 0)
        swt, swf = mem.get("SwapTotal", 0), mem.get("SwapFree", 0)
        stats["memory"] = {
            "total_mb": mt // 1024,
            "available_mb": ma // 1024,
            "used_pct": round((mt - ma) * 100.0 / mt, 1) if mt else None,
            "swap_total_mb": swt // 1024,
            "swap_used_mb": (swt - swf) // 1024,
            "swap_used_pct": round((swt - swf) * 100.0 / swt, 1) if swt else None,
        }
    except Exception as e:  # noqa: BLE001
        stats["memory"] = {"error": str(e)}

    # Load average + uptime
    try:
        with open("/proc/loadavg", "r", encoding="utf-8") as fh:
            parts = fh.read().split()
        stats["load_avg"] = {"1m": float(parts[0]), "5m": float(parts[1]), "15m": float(parts[2])}
    except Exception as e:  # noqa: BLE001
        stats["load_avg"] = {"error": str(e)}
    try:
        with open("/proc/uptime", "r", encoding="utf-8") as fh:
            stats["uptime_days"] = round(float(fh.read().split()[0]) / 86400, 1)
    except Exception:  # noqa: BLE001
        stats["uptime_days"] = None

    # MySQL footprint (same read-only connection as every other tool)
    try:
        conn = _connect()
        try:
            with conn.cursor() as cur:
                cur.execute(
                    "SELECT table_name AS name, "
                    "ROUND((data_length+index_length)/1048576) AS mb "
                    "FROM information_schema.tables WHERE table_schema = %s "
                    "ORDER BY (data_length+index_length) DESC LIMIT 10",
                    (DB_NAME,),
                )
                top = cur.fetchall()
                cur.execute(
                    "SELECT ROUND(SUM(data_length+index_length)/1048576) AS mb "
                    "FROM information_schema.tables WHERE table_schema = %s",
                    (DB_NAME,),
                )
                tot = cur.fetchone()
        finally:
            conn.close()
        stats["mysql"] = {
            "database": DB_NAME,
            "total_mb": _jsonable((tot or {}).get("mb")),
            "top_tables": _rows_json(list(top or [])),
        }
    except Exception as e:  # noqa: BLE001
        stats["mysql"] = {"error": f"{e}"}

    # Local file hotspots we care about (walks are bounded, best-effort)
    try:
        base = os.path.join(os.path.dirname(__file__), "..", "storage", "app", "refund-attachments")
        size = 0
        for root_dir, _dirs, files in os.walk(base):
            for name in files:
                try:
                    size += os.path.getsize(os.path.join(root_dir, name))
                except OSError:
                    pass
        stats["refund_attachments_local_mb"] = round(size / 1048576, 1)
    except Exception:  # noqa: BLE001
        stats["refund_attachments_local_mb"] = None
    try:
        stats["mcp_audit_log_mb"] = round(os.path.getsize(AUDIT_PATH) / 1048576, 2)
    except Exception:  # noqa: BLE001
        stats["mcp_audit_log_mb"] = 0.0

    return json.dumps(stats, indent=2)


@mcp.tool()
def run_query(sql: str, max_rows: int = DEFAULT_ROW_LIMIT) -> str:
    """Run a single READ-ONLY SQL query (SELECT or WITH ... SELECT) against the
    mark1 database and return the rows as JSON.

    Rules:
      - Only one SELECT/WITH statement; no INSERT/UPDATE/DDL (also blocked at the
        DB-user level).
      - An outer LIMIT is auto-added if your top-level query has none. max_rows
        is capped server-side.
      - Prefer aggregates (SUM/COUNT/GROUP BY) over pulling raw rows; it's lighter
        on the DB and more useful in the answer.

    Call data_dictionary() and describe_table() first if unsure of columns.
    """
    _ident, _err = _guard("run_query", sql)
    if _err:
        return _err
    try:
        max_rows = max(1, min(int(max_rows), MAX_ROW_LIMIT))
        safe_sql = _validate_select_only(sql)
        safe_sql = _ensure_outer_limit(safe_sql, max_rows)
        result = _run(safe_sql, max_rows)
        result["executed_sql"] = safe_sql
        return json.dumps(result, indent=2)
    except ValueError as e:
        return json.dumps({"error": str(e)})
    except pymysql.MySQLError as e:
        return json.dumps({"error": f"MySQL error: {e}"})
    except Exception as e:  # noqa: BLE001
        return json.dumps({"error": f"Unexpected error: {e}"})


# --------------------------------------------------------------------------- #
# Route planning (the one write this server can make)
# --------------------------------------------------------------------------- #
ROUTE_PERMISSION = "admin-access operations"  # same gate as the UI's Renumber button


def _user_can_plan_routes(identity: dict) -> bool:
    """True when the caller may rewrite route sequences. The local stdio owner
    (no bound user) is trusted, as everywhere else in this server; a token/OAuth
    identity must hold the same mark1 permission that guards the Renumber
    button, via a role or directly. Read-only check on the Spatie tables."""
    user_id = (identity or {}).get("user_id")
    if user_id is None:
        return True  # local stdio owner
    sql = (
        "SELECT COUNT(*) AS n FROM permissions p "
        "WHERE p.name = %s AND ("
        "  EXISTS (SELECT 1 FROM model_has_permissions mhp "
        "          WHERE mhp.permission_id = p.id "
        "            AND mhp.model_type = 'App\\\\Models\\\\User' AND mhp.model_id = %s) "
        "  OR EXISTS (SELECT 1 FROM role_has_permissions rhp "
        "             JOIN model_has_roles mhr ON mhr.role_id = rhp.role_id "
        "              AND mhr.model_type = 'App\\\\Models\\\\User' AND mhr.model_id = %s "
        "             WHERE rhp.permission_id = p.id)"
        ")"
    )
    try:
        conn = _connect()
        try:
            with conn.cursor() as cur:
                cur.execute(sql, (ROUTE_PERMISSION, user_id, user_id))
                row = cur.fetchone()
        finally:
            conn.close()
    except Exception:
        return False
    return bool(row and row.get("n"))


def _load_route_stops(ops_job_id: int) -> list:
    """The job's real stops (items + tasks) in current sequence order. Each:
    {type, id, sequence, code, name}."""
    conn = _connect()
    try:
        with conn.cursor() as cur:
            cur.execute(
                "SELECT 'item' AS type, oji.id AS id, oji.sequence AS sequence, "
                "v.code AS code, c.name AS name "
                "FROM ops_job_items oji "
                "LEFT JOIN vends v ON v.id = oji.vend_id "
                "LEFT JOIN customers c ON c.id = oji.customer_id "
                "WHERE oji.ops_job_id = %s "
                "UNION ALL "
                "SELECT 'task' AS type, t.id AS id, t.sequence AS sequence, "
                "NULL AS code, t.task_name AS name "
                "FROM ops_job_tasks t WHERE t.ops_job_id = %s "
                "ORDER BY ISNULL(sequence), sequence, id",
                (ops_job_id, ops_job_id),
            )
            return list(cur.fetchall())
    finally:
        conn.close()


def _job_route_editable(ops_job_id: int) -> bool:
    """Mirror of the web endpoint's completed-job gate: editable while any item
    is undelivered (status < 3) or the job has tasks (the route page treats
    every task as pending). Fails closed on DB errors."""
    sql = (
        "SELECT (EXISTS (SELECT 1 FROM ops_job_items "
        "                WHERE ops_job_id = %s AND status < 3) "
        "     OR EXISTS (SELECT 1 FROM ops_job_tasks "
        "                WHERE ops_job_id = %s)) AS ok"
    )
    try:
        conn = _connect()
        try:
            with conn.cursor() as cur:
                cur.execute(sql, (ops_job_id, ops_job_id))
                row = cur.fetchone()
        finally:
            conn.close()
    except Exception:
        return False
    return bool(row and row.get("ok"))


@mcp.tool()
def apply_route_sequence(ops_job_id: int, stops: list, dry_run: bool = False) -> str:
    """Overwrite the driver route (visiting order) of one ops job. This is the
    ONLY write this server can make: it renumbers ops_job_items.sequence /
    ops_job_tasks.sequence to 1..N — the same always-overwrite semantics as the
    route page's Renumber button. Nothing else is writable, at the DB-grant level.

    Args:
      ops_job_id: the ops job whose route to set.
      stops: the stops in visiting order. Each entry is
             {"type": "item"|"task", "id": <ops_job_items.id | ops_job_tasks.id>}
             (a bare integer is treated as an item id). Read the job's stops
             first, e.g. via run_query on ops_job_items/ops_job_tasks.
      dry_run: if true, only report what would change — no write.

    Rules:
      - Every id must belong to this ops job; unknown or duplicate ids are an
        error (fix the list and retry).
      - Stops of the job missing from the list are appended after it in their
        current order, so the overwrite never leaves stale sequence numbers.
      - Requires the 'admin-access operations' mark1 permission (the same
        one that guards the route page's Renumber button).

    Returns JSON: the final order with old -> new sequence per stop.
    """
    identity, _err = _guard("apply_route_sequence", f"job={ops_job_id} stops={len(stops or [])} dry_run={dry_run}")
    if _err:
        return _err
    if not _user_can_plan_routes(identity):
        return json.dumps({"error": f"Your mark1 account lacks the '{ROUTE_PERMISSION}' permission required to rewrite routes."})

    # --- validate input shape --------------------------------------------- #
    try:
        ops_job_id = int(ops_job_id)
    except (TypeError, ValueError):
        return json.dumps({"error": "ops_job_id must be an integer."})
    if not isinstance(stops, list) or not stops:
        return json.dumps({"error": "stops must be a non-empty list."})

    requested = []
    for raw in stops:
        if isinstance(raw, dict):
            stop_type = raw.get("type", "item")
            stop_id = raw.get("id")
        else:
            stop_type, stop_id = "item", raw
        if stop_type not in ("item", "task"):
            return json.dumps({"error": f"Invalid stop type {stop_type!r} (use 'item' or 'task')."})
        try:
            stop_id = int(stop_id)
        except (TypeError, ValueError):
            return json.dumps({"error": f"Invalid stop id in entry {raw!r}."})
        requested.append((stop_type, stop_id))

    dupes = {k for k in requested if requested.count(k) > 1}
    if dupes:
        return json.dumps({"error": "Duplicate stops in the list: " + ", ".join(f"{t}:{i}" for t, i in sorted(dupes))})

    # --- resolve against the job's real stops ----------------------------- #
    try:
        job_stops = _load_route_stops(ops_job_id)
    except pymysql.MySQLError as e:
        return json.dumps({"error": f"MySQL error: {e}"})
    if not job_stops:
        return json.dumps({"error": f"Ops job {ops_job_id} has no items or tasks (or does not exist)."})

    # Completed-job rule, mirroring the web endpoint (OpsJobController::renumberItems):
    # a job whose items are all delivered (status >= 3) and which has no tasks is
    # history — its visiting order must not be rewritten.
    if not _job_route_editable(ops_job_id):
        return json.dumps({
            "error": f"Ops job {ops_job_id} is completed (every item delivered, no tasks) — "
                     "its route can no longer be rewritten."
        })

    by_key = {(s["type"], s["id"]): s for s in job_stops}
    unknown = [k for k in requested if k not in by_key]
    if unknown:
        return json.dumps({
            "error": "These stops do not belong to ops job "
                     f"{ops_job_id}: " + ", ".join(f"{t}:{i}" for t, i in unknown),
            "job_stops": _rows_json(job_stops),
        })

    # Requested order first, then any stop the list missed, in current order.
    ordered = [by_key[k] for k in requested]
    missing = [s for s in job_stops if (s["type"], s["id"]) not in set(requested)]
    ordered += missing

    plan = [
        {
            "sequence": seq,
            "type": s["type"],
            "id": s["id"],
            "code": _jsonable(s.get("code")),
            "name": _jsonable(s.get("name")),
            "old_sequence": _jsonable(s.get("sequence")),
        }
        for seq, s in enumerate(ordered, start=1)
    ]

    result = {
        "ops_job_id": ops_job_id,
        "applied": False,
        "stops_in_json_order": len(requested),
        "stops_appended_from_job": len(missing),
        "route": plan,
    }
    if dry_run:
        result["note"] = "dry_run — nothing was written."
        return json.dumps(result, indent=2)

    # --- write (one transaction, per-row ops_job_id guard) ----------------- #
    try:
        conn = _connect_write()
    except RuntimeError as e:
        return json.dumps({"error": str(e)})
    try:
        with conn.cursor() as cur:
            for entry in plan:
                table = "ops_job_items" if entry["type"] == "item" else "ops_job_tasks"
                cur.execute(
                    f"UPDATE {table} SET sequence = %s WHERE id = %s AND ops_job_id = %s",
                    (entry["sequence"], entry["id"], ops_job_id),
                )
        conn.commit()
    except pymysql.MySQLError as e:
        try:
            conn.rollback()
        except Exception:
            pass
        return json.dumps({"error": f"MySQL error while writing (rolled back): {e}"})
    finally:
        conn.close()

    result["applied"] = True
    _audit(identity, "apply_route_sequence:applied", f"job={ops_job_id} n={len(plan)}")
    return json.dumps(result, indent=2)


# --------------------------------------------------------------------------- #
# Source tools (optional): let Claude read the app's own code to answer
# "how do I ... in ConnectVend?" questions. OFF unless MARK1_MCP_SOURCE_TOOLS=1,
# so a server that does not opt in exposes nothing new.
#
# Safety:
#   - Only an allowlist of folders and code file types is served (no .env, no
#     storage/, vendor/, keys, .git, root-level *.md notes, debug scripts).
#   - Every path is resolved and must stay inside an allowed folder (no ../,
#     no symlink escape).
#   - Served text is redacted: the default machine frame key, anything that
#     looks like a long random key string, and default values of env() secrets.
#   - Read-only, size-capped, audited like every other tool.
# --------------------------------------------------------------------------- #
SOURCE_TOOLS_ENABLED = os.getenv("MARK1_MCP_SOURCE_TOOLS", "").strip().lower() in ("1", "true", "yes", "on")
SOURCE_ROOT = os.path.realpath(
    os.getenv("MARK1_MCP_SOURCE_ROOT", os.path.join(os.path.dirname(__file__), ".."))
)
_SOURCE_DIRS = (
    "app", "routes", "resources/js", "resources/views", "lang", "config",
    "database/migrations",
)
_SOURCE_EXTS = (".php", ".vue", ".js", ".ts", ".json")
_SOURCE_SKIP_PARTS = {"node_modules", "vendor", ".git", "storage", "public"}
_SOURCE_MAX_FILE_BYTES = 1_500_000
_SOURCE_MAX_READ_LINES = 400
_SOURCE_CACHE_SECONDS = 300

_REDACT_PATTERNS = [
    # Default machine frame key (config/vend.php) and its many inline copies.
    re.compile(r"123456789110138A"),
    # env('SOMETHING_KEY|SECRET|PASS|TOKEN', 'default') -> hide the default.
    re.compile(
        r"(env\(\s*['\"][A-Z0-9_]*(?:KEY|SECRET|PASS|PASSWORD|TOKEN)[A-Z0-9_]*['\"]\s*,\s*)(['\"])[^'\"]+\2"
    ),
    # Long random-looking quoted strings (keys, hashes, tokens).
    re.compile(r"(['\"])(?=[A-Za-z0-9+/=]*\d[A-Za-z0-9+/=]*\d)(?=[A-Za-z0-9+/=]*[A-Za-z])[A-Za-z0-9+/=]{16,}\1"),
    re.compile(r"-----BEGIN [A-Z ]+-----"),
]


def _redact(text: str) -> str:
    out = _REDACT_PATTERNS[0].sub("[REDACTED]", text)
    out = _REDACT_PATTERNS[1].sub(lambda m: f"{m.group(1)}{m.group(2)}[REDACTED]{m.group(2)}", out)
    out = _REDACT_PATTERNS[2].sub(lambda m: f"{m.group(1)}[REDACTED]{m.group(1)}", out)
    out = _REDACT_PATTERNS[3].sub("[REDACTED]", out)
    return out


_source_index: dict = {"at": 0.0, "files": []}


def _source_files() -> list:
    """Relative paths (forward slashes) of every servable file, cached briefly."""
    import time

    now = time.time()
    if _source_index["files"] and now - _source_index["at"] < _SOURCE_CACHE_SECONDS:
        return _source_index["files"]
    files = []
    for top in _SOURCE_DIRS:
        base = os.path.join(SOURCE_ROOT, top)
        if not os.path.isdir(base):
            continue
        for dirpath, dirnames, filenames in os.walk(base):
            dirnames[:] = sorted(d for d in dirnames if d not in _SOURCE_SKIP_PARTS and not d.startswith("."))
            for name in sorted(filenames):
                if not name.endswith(_SOURCE_EXTS) or name.startswith("."):
                    continue
                full = os.path.join(dirpath, name)
                if os.path.islink(full):
                    continue
                files.append(os.path.relpath(full, SOURCE_ROOT).replace(os.sep, "/"))
    _source_index["files"] = files
    _source_index["at"] = now
    return files


def _source_resolve(path: str):
    """Return (absolute path, relative path) for an allowed file, else (None, error)."""
    rel = (path or "").strip().lstrip("/").replace("\\", "/")
    if not rel:
        return None, "Give a file path, e.g. resources/js/Layouts/Authenticated.vue"
    full = os.path.realpath(os.path.join(SOURCE_ROOT, rel))
    if not full.startswith(SOURCE_ROOT + os.sep):
        return None, "That path is outside the app."
    rel = os.path.relpath(full, SOURCE_ROOT).replace(os.sep, "/")
    if rel not in set(_source_files()):
        return None, (
            "Not an allowed source file. Allowed: "
            + ", ".join(_SOURCE_DIRS) + " (" + ", ".join(_SOURCE_EXTS) + "). "
            "Use source_list or source_search to find the right path."
        )
    return full, rel


_SOURCE_GUIDE = (
    "How to answer 'how do I ...' questions from the code: the main menu is "
    "resources/js/Layouts/Authenticated.vue (menu labels + which permission shows "
    "each item); URLs map to controllers in routes/web.php; each screen is a Vue "
    "page under resources/js/Pages/; button labels and form fields are in that "
    "page. Answer the person in plain words with the menu path and the buttons "
    "they press, never paste code, and call the system ConnectVend. Some menus "
    "need a permission or exist only for some countries/operators; when unsure, "
    "say so instead of guessing."
)


def source_list(path_prefix: str = "", limit: int = 300) -> str:
    """List the app's source files whose path starts with `path_prefix`
    (e.g. "resources/js/Pages/OpsJob"). Use with source_search / source_read to
    work out how a ConnectVend screen or feature is used."""
    _ident, _err = _guard("source_list", path_prefix)
    if _err:
        return _err
    prefix = (path_prefix or "").strip().lstrip("/")
    limit = max(1, min(int(limit or 300), 1000))
    matches = [f for f in _source_files() if f.startswith(prefix)]
    return json.dumps(
        {"total": len(matches), "shown": min(len(matches), limit), "files": matches[:limit],
         "guide": _SOURCE_GUIDE},
        indent=2,
    )


def source_search(pattern: str, path_prefix: str = "", regex: bool = False, max_results: int = 80) -> str:
    """Search the app's source code for text (case-insensitive) and return
    file:line matches. Set regex=true for a regular expression. Narrow with
    path_prefix, e.g. "resources/js/Pages" for screens or "routes" for URLs.
    Use this to answer how-to questions about ConnectVend features."""
    _ident, _err = _guard("source_search", f"{path_prefix} :: {pattern}")
    if _err:
        return _err
    if not pattern or len(pattern) < 2:
        return json.dumps({"error": "Give at least 2 characters to search for."})
    try:
        rx = re.compile(pattern if regex else re.escape(pattern), re.IGNORECASE)
    except re.error as e:
        return json.dumps({"error": f"Bad regular expression: {e}"})
    prefix = (path_prefix or "").strip().lstrip("/")
    max_results = max(1, min(int(max_results or 80), 300))
    hits, files_hit, truncated = [], 0, False
    for rel in _source_files():
        if not rel.startswith(prefix):
            continue
        full = os.path.join(SOURCE_ROOT, rel)
        try:
            if os.path.getsize(full) > _SOURCE_MAX_FILE_BYTES:
                continue
            with open(full, "r", encoding="utf-8", errors="replace") as fh:
                lines = fh.readlines()
        except OSError:
            continue
        hit_here = False
        for i, line in enumerate(lines, 1):
            # Match the REDACTED text, so a search can never probe a hidden
            # value character by character.
            text = _redact(line.rstrip("\n").strip())
            if rx.search(text):
                if len(text) > 240:
                    text = text[:240] + "…"
                hits.append(f"{rel}:{i}: {text}")
                hit_here = True
                if len(hits) >= max_results:
                    truncated = True
                    break
        files_hit += hit_here
        if truncated:
            break
    return json.dumps(
        {"matches": len(hits), "files": files_hit, "truncated": truncated, "results": hits,
         "guide": _SOURCE_GUIDE},
        indent=2,
    )


def source_read(path: str, start_line: int = 1, end_line: int = 0) -> str:
    """Read lines of one app source file (line-numbered, at most 400 lines per
    call; end_line=0 means start_line+399). Secrets are redacted. Use it to see
    a screen's buttons/fields or what a controller action does."""
    _ident, _err = _guard("source_read", f"{path} {start_line}-{end_line}")
    if _err:
        return _err
    full, rel = _source_resolve(path)
    if not full:
        return json.dumps({"error": rel})
    try:
        with open(full, "r", encoding="utf-8", errors="replace") as fh:
            lines = fh.readlines()
    except OSError as e:
        return json.dumps({"error": f"Could not read {rel}: {e.strerror}"})
    total = len(lines)
    start = max(1, int(start_line or 1))
    end = int(end_line or 0) or start + _SOURCE_MAX_READ_LINES - 1
    end = min(end, start + _SOURCE_MAX_READ_LINES - 1, total)
    if start > total:
        return json.dumps({"error": f"{rel} has only {total} lines."})
    body = "".join(f"{n:>5}  {lines[n - 1]}" for n in range(start, end + 1))
    header = f"{rel}  (lines {start}-{end} of {total})\n"
    more = f"\n... {total - end} more lines; call again with start_line={end + 1}\n" if end < total else ""
    return header + _redact(body) + more


if SOURCE_TOOLS_ENABLED:
    mcp.tool()(source_list)
    mcp.tool()(source_search)
    mcp.tool()(source_read)


def _build_http_app():
    """Wrap the streamable-HTTP MCP app with bearer-token auth. Each request must
    carry `Authorization: Bearer <token>` (or `X-MCP-Token`); the token is
    validated against mcp_access_tokens and the resolved identity is exposed to
    the tools via the _http_identity context var. Pure-ASGI (not
    BaseHTTPMiddleware) so the context var propagates into the request handler."""
    import anyio
    from starlette.responses import JSONResponse, Response

    inner = mcp.streamable_http_app()

    class _BearerAuthASGI:
        def __init__(self, app):
            self.app = app

        async def __call__(self, scope, receive, send):
            if scope.get("type") != "http":
                return await self.app(scope, receive, send)
            path = scope.get("path", "")
            if path.startswith("/.well-known/"):
                # OAuth discovery documents — public by design so clients can
                # find the authorization server before they have credentials.
                # PRM (RFC 9728) is the primary flow; the AS-metadata mirror
                # covers older MCP clients that probe the resource origin.
                cors = {
                    "Access-Control-Allow-Origin": "*",
                    "Access-Control-Allow-Methods": "GET, OPTIONS",
                    "Access-Control-Allow-Headers": "Content-Type, Authorization, mcp-protocol-version",
                }
                doc = None
                if path.startswith("/.well-known/oauth-protected-resource"):
                    doc = _protected_resource_metadata()
                elif path.startswith("/.well-known/oauth-authorization-server") or \
                        path.startswith("/.well-known/openid-configuration"):
                    doc = _authorization_server_metadata()
                if doc is not None:
                    if scope.get("method") == "OPTIONS":
                        resp = Response(status_code=204, headers=cors)
                    else:
                        resp = JSONResponse(doc, headers=cors)
                    return await resp(scope, receive, send)
                # unknown well-known path: fall through to normal auth handling
            headers = {
                k.decode("latin1").lower(): v.decode("latin1")
                for k, v in scope.get("headers", [])
            }
            authz = headers.get("authorization", "")
            if authz.lower().startswith("bearer "):
                token = authz[7:].strip()
            else:
                token = headers.get("x-mcp-token", "").strip()
            if not token:
                # Fallback: token in the URL query string (?token=... or ?key=...),
                # for clients whose connector dialog accepts only a URL and has no
                # static-bearer field (e.g. Claude's custom connector = URL + OAuth).
                try:
                    from urllib.parse import parse_qs
                    _qs = parse_qs(scope.get("query_string", b"").decode("latin1"))
                    token = (_qs.get("token") or _qs.get("key") or [""])[0].strip()
                except Exception:
                    token = ""
            identity = (
                await anyio.to_thread.run_sync(_resolve_identity, token) if token else None
            )
            if not identity:
                resp = JSONResponse(
                    {"error": "unauthorized"},
                    status_code=401,
                    headers={
                        "WWW-Authenticate": (
                            'Bearer realm="mark1-mcp", '
                            f'resource_metadata="{RESOURCE_METADATA_URL}"'
                        )
                    },
                )
                return await resp(scope, receive, send)
            reset = _http_identity.set(identity)
            try:
                await self.app(scope, receive, send)
            finally:
                _http_identity.reset(reset)

    return _BearerAuthASGI(inner)


if __name__ == "__main__":
    # Fail fast with a readable message if the password is missing.
    if not DB_PASS:
        print("MARK1_DB_PASSWORD is not set. See README.md.", file=sys.stderr)

    if TRANSPORT == "http":
        import uvicorn

        uvicorn.run(_build_http_app(), host=HTTP_HOST, port=HTTP_PORT, log_level="warning")
    else:
        mcp.run()
