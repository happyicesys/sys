#!/usr/bin/env python3
"""
mark1 read-only MCP server.

Exposes the mark1 MySQL database to Claude as a small set of *read-only* tools so
you can ask analytical questions ("what can boost sales based on Sales
Transactions data?") against live data without ever touching it.

Safety model (defense in depth):
  1. Connect as a dedicated SELECT-only MySQL user (the hard guarantee).
  2. The server still refuses anything that isn't a single SELECT/WITH query.
  3. Every query is wrapped in a READ ONLY transaction and row/time capped.

Transport: stdio (works with Claude Desktop's mcpServers config). See README.md.
"""

from __future__ import annotations

import os
import re
import sys
import json
import datetime
import decimal
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

# Hard caps so a single question can never pull the whole DB into context.
DEFAULT_ROW_LIMIT = int(os.getenv("MARK1_DEFAULT_ROW_LIMIT", "500"))
MAX_ROW_LIMIT = int(os.getenv("MARK1_MAX_ROW_LIMIT", "5000"))
STATEMENT_TIMEOUT_MS = int(os.getenv("MARK1_STATEMENT_TIMEOUT_MS", "15000"))

DICT_PATH = os.path.join(os.path.dirname(__file__), "data_dictionary.md")

mcp = FastMCP("mark1-readonly")

# --------------------------------------------------------------------------- #
# SQL guardrails
# --------------------------------------------------------------------------- #
# Statements we never allow, even though the read-only DB user would also block
# the writes. This catches mistakes early and keeps the error message clear.
_FORBIDDEN = re.compile(
    r"\b(insert|update|delete|replace|merge|drop|alter|create|truncate|grant|"
    r"revoke|rename|lock|unlock|call|do|handler|load_file|into\s+outfile|"
    r"into\s+dumpfile|set\s+|use\s+|begin|commit|rollback|start\s+transaction)\b",
    re.IGNORECASE,
)


def _strip_sql_comments(sql: str) -> str:
    """Remove -- line comments and /* */ block comments before validation."""
    sql = re.sub(r"/\*.*?\*/", " ", sql, flags=re.DOTALL)
    sql = re.sub(r"--[^\n]*", " ", sql)
    sql = re.sub(r"#[^\n]*", " ", sql)
    return sql.strip()


def _validate_select_only(sql: str) -> str:
    """Return a cleaned single SELECT/WITH statement or raise ValueError."""
    cleaned = _strip_sql_comments(sql)
    if not cleaned:
        raise ValueError("Empty query.")

    # One statement only. Allow a single optional trailing semicolon.
    body = cleaned.rstrip(";").strip()
    if ";" in body:
        raise ValueError("Only a single statement is allowed (no semicolons).")

    lowered = body.lower()
    if not (lowered.startswith("select") or lowered.startswith("with")):
        raise ValueError("Only SELECT / WITH queries are allowed.")

    if _FORBIDDEN.search(body):
        raise ValueError("Query contains a forbidden (write/DDL) keyword.")

    return body


def _ensure_limit(sql: str, max_rows: int) -> str:
    """Append a LIMIT if the outermost query has none."""
    if re.search(r"\blimit\b", sql, re.IGNORECASE):
        return sql
    return f"{sql}\nLIMIT {max_rows}"


# --------------------------------------------------------------------------- #
# DB access
# --------------------------------------------------------------------------- #
def _connect() -> pymysql.connections.Connection:
    return pymysql.connect(
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


def _run(sql: str, max_rows: int) -> dict:
    conn = _connect()
    try:
        with conn.cursor() as cur:
            # Per-session read-only + time guard; ignore if the server variant
            # does not support a given knob.
            for guard in (
                "SET SESSION TRANSACTION READ ONLY",
                f"SET SESSION MAX_EXECUTION_TIME={STATEMENT_TIMEOUT_MS}",
            ):
                try:
                    cur.execute(guard)
                except Exception:
                    pass
            cur.execute(sql)
            rows = cur.fetchmany(max_rows + 1)
            truncated = len(rows) > max_rows
            rows = rows[:max_rows]
            data = [{k: _jsonable(v) for k, v in r.items()} for r in rows]
            return {
                "row_count": len(data),
                "truncated": truncated,
                "rows": data,
            }
    finally:
        conn.close()


# --------------------------------------------------------------------------- #
# Tools
# --------------------------------------------------------------------------- #
@mcp.tool()
def data_dictionary() -> str:
    """Return the curated guide to the most useful mark1 tables and columns
    (Sales Transactions and friends). Read this first when planning a query."""
    try:
        with open(DICT_PATH, "r", encoding="utf-8") as fh:
            return fh.read()
    except FileNotFoundError:
        return "data_dictionary.md not found next to server.py."


@mcp.tool()
def list_tables() -> str:
    """List all tables in the mark1 database with their approximate row counts."""
    sql = (
        "SELECT table_name, table_rows "
        "FROM information_schema.tables "
        "WHERE table_schema = %s ORDER BY table_name"
    )
    conn = _connect()
    try:
        with conn.cursor() as cur:
            cur.execute(sql, (DB_NAME,))
            rows = cur.fetchall()
    finally:
        conn.close()
    return json.dumps([{k: _jsonable(v) for k, v in r.items()} for r in rows], indent=2)


@mcp.tool()
def describe_table(table: str) -> str:
    """Return the column names, types, nullability and keys for one table.
    Use this to confirm exact column names before writing a query."""
    if not re.fullmatch(r"[A-Za-z0-9_]+", table or ""):
        return "Invalid table name."
    sql = (
        "SELECT column_name, column_type, is_nullable, column_key, column_default, column_comment "
        "FROM information_schema.columns "
        "WHERE table_schema = %s AND table_name = %s "
        "ORDER BY ordinal_position"
    )
    conn = _connect()
    try:
        with conn.cursor() as cur:
            cur.execute(sql, (DB_NAME, table))
            rows = cur.fetchall()
    finally:
        conn.close()
    if not rows:
        return f"No such table: {table}"
    return json.dumps([{k: _jsonable(v) for k, v in r.items()} for r in rows], indent=2)


@mcp.tool()
def run_query(sql: str, max_rows: int = DEFAULT_ROW_LIMIT) -> str:
    """Run a single READ-ONLY SQL query (SELECT or WITH ... SELECT) against the
    mark1 database and return the rows as JSON.

    Rules:
      - Only one SELECT/WITH statement; no INSERT/UPDATE/DDL (also blocked at the
        DB-user level).
      - A LIMIT is auto-added if you omit one. max_rows is capped server-side.
      - Prefer aggregates (SUM/COUNT/GROUP BY) over pulling raw rows.

    Call data_dictionary() and describe_table() first if unsure of columns.
    """
    try:
        max_rows = max(1, min(int(max_rows), MAX_ROW_LIMIT))
        safe_sql = _validate_select_only(sql)
        safe_sql = _ensure_limit(safe_sql, max_rows)
        result = _run(safe_sql, max_rows)
        result["executed_sql"] = safe_sql
        return json.dumps(result, indent=2)
    except ValueError as e:
        return json.dumps({"error": str(e)})
    except pymysql.MySQLError as e:
        return json.dumps({"error": f"MySQL error: {e}"})
    except Exception as e:  # noqa: BLE001
        return json.dumps({"error": f"Unexpected error: {e}"})


if __name__ == "__main__":
    # Fail fast with a readable message if the password is missing.
    if not DB_PASS:
        print(
            "MARK1_DB_PASSWORD is not set. See README.md.",
            file=sys.stderr,
        )
    mcp.run()
