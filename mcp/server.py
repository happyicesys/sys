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


def _build_http_app():
    """Wrap the streamable-HTTP MCP app with bearer-token auth. Each request must
    carry `Authorization: Bearer <token>` (or `X-MCP-Token`); the token is
    validated against mcp_access_tokens and the resolved identity is exposed to
    the tools via the _http_identity context var. Pure-ASGI (not
    BaseHTTPMiddleware) so the context var propagates into the request handler."""
    import anyio
    from starlette.responses import JSONResponse

    inner = mcp.streamable_http_app()

    class _BearerAuthASGI:
        def __init__(self, app):
            self.app = app

        async def __call__(self, scope, receive, send):
            if scope.get("type") != "http":
                return await self.app(scope, receive, send)
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
                await anyio.to_thread.run_sync(_authenticate, token) if token else None
            )
            if not identity:
                resp = JSONResponse({"error": "unauthorized"}, status_code=401)
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
