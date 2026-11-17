"""
Ask ConnectVend: answer a "how do I ...?" question by reading the code ON THIS
SERVER, and send back plain-language steps only.

The person's Claude never receives code. A separate Claude, called from here
with read-only tools over this checkout, writes the answer; a check then
refuses any answer that carries code, file names, table / column names or
class names, and only the checked text goes back.

Off unless an Anthropic key is set (ANTHROPIC_API_KEY in the environment, or
that one line of the site's own .env). Switch off with MARK1_MCP_ASK=0.
Limits: MARK1_MCP_ASK_DAILY_PER_USER (questions per person per day, default
20) and MARK1_MCP_ASK_MONTHLY_USD (spend cap per calendar month on this
server, default 30). Every question and answer is appended to the ask log.
"""

from __future__ import annotations

import datetime
import fcntl
import json
import logging
import os
import re
import time
from typing import Callable, Optional

# The HTTP client logs every request at INFO; keep the daemon log quiet.
for _name in ("httpx2", "httpx", "anthropic"):
    logging.getLogger(_name).setLevel(logging.WARNING)

HERE = os.path.dirname(os.path.abspath(__file__))
REPO_ROOT = os.getenv("MARK1_MCP_ASK_REPO_ROOT", os.path.dirname(HERE))

MODEL = os.getenv("MARK1_MCP_ASK_MODEL", "claude-opus-5-5")
EFFORT = os.getenv("MARK1_MCP_ASK_EFFORT", "medium")
MAX_TURNS = int(os.getenv("MARK1_MCP_ASK_MAX_TURNS", "10"))
TIME_BUDGET_S = float(os.getenv("MARK1_MCP_ASK_TIME_BUDGET", "100"))
DAILY_PER_USER = int(os.getenv("MARK1_MCP_ASK_DAILY_PER_USER", "20"))
MONTHLY_USD = float(os.getenv("MARK1_MCP_ASK_MONTHLY_USD", "30"))

# USD per million tokens, for the spend cap (claude-opus-5-5 list prices).
PRICE_IN = float(os.getenv("MARK1_MCP_ASK_PRICE_IN", "4"))
PRICE_OUT = float(os.getenv("MARK1_MCP_ASK_PRICE_OUT", "20"))
PRICE_CACHE_WRITE = PRICE_IN * 1.25
PRICE_CACHE_READ = float(os.getenv("MARK1_MCP_ASK_PRICE_CACHE_READ", "0.4"))

# What the reader may open. Everything else (.env, storage, vendor, tests,
# public, the database dump, …) is out of reach.
ALLOWED_ROOTS = (
    "app/",
    "routes/",
    "resources/js/",
    "resources/views/",
    "config/",
    "lang/",
    "database/seeders/RolePermissionSyncSeeder.php",
    "mcp/feature_guide/",
)
TEXT_EXT = (".php", ".vue", ".js", ".ts", ".json", ".md")
MAX_FILE_BYTES = 2_000_000
MAX_READ_LINES = 400
MAX_GREP_HITS = 80


# --------------------------------------------------------------------------- #
# Set-up
# --------------------------------------------------------------------------- #
def _api_key() -> str:
    key = os.getenv("ANTHROPIC_API_KEY", "").strip()
    if key:
        return key
    path = os.path.join(REPO_ROOT, ".env")
    try:
        with open(path, "r", encoding="utf-8") as fh:
            for line in fh:
                if line.startswith("ANTHROPIC_API_KEY="):
                    return line.partition("=")[2].strip().strip('"').strip("'")
    except OSError:
        pass
    return ""


def enabled() -> tuple:
    """(True, "") when the tool can run here, else (False, reason)."""
    if os.getenv("MARK1_MCP_ASK", "1").strip() in ("0", "false", "off", "no"):
        return False, "Ask ConnectVend is switched off on this server."
    try:
        import anthropic  # noqa: F401
    except ImportError:
        return False, "Ask ConnectVend is not installed on this server."
    if not _api_key():
        return False, "Ask ConnectVend has no Anthropic key on this server."
    return True, ""


# --------------------------------------------------------------------------- #
# Usage limits + log (a JSON file beside the audit log, locked on write)
# --------------------------------------------------------------------------- #
def _state_path(audit_path: str) -> str:
    return os.getenv("MARK1_MCP_ASK_STATE", os.path.join(os.path.dirname(audit_path) or ".", "mcp-ask-usage.json"))


def _log_path(audit_path: str) -> str:
    return os.getenv("MARK1_MCP_ASK_LOG", os.path.join(os.path.dirname(audit_path) or ".", "mcp-ask-log.jsonl"))


def _with_state(audit_path: str, fn):
    path = _state_path(audit_path)
    fd = os.open(path, os.O_RDWR | os.O_CREAT, 0o600)
    with os.fdopen(fd, "r+", encoding="utf-8") as fh:
        fcntl.flock(fh, fcntl.LOCK_EX)
        try:
            raw = fh.read()
            state = json.loads(raw) if raw.strip() else {}
        except ValueError:
            state = {}
        result = fn(state)
        fh.seek(0)
        fh.truncate()
        fh.write(json.dumps(state))
        return result


def _reserve(audit_path: str, who: str) -> Optional[str]:
    """Count one question for `who` today; None if allowed, else the refusal."""
    today = datetime.date.today().isoformat()
    month = today[:7]

    def check(state):
        if state.get("day") != today:
            state["day"], state["per_user"] = today, {}
        if state.get("month") != month:
            state["month"], state["usd"] = month, 0.0
        if state.get("usd", 0.0) >= MONTHLY_USD:
            return "This month's budget for Ask ConnectVend is used up. Ask your ConnectVend administrator."
        n = state["per_user"].get(who, 0)
        if n >= DAILY_PER_USER:
            return f"You have asked {n} questions today, the daily limit. Try again tomorrow."
        state["per_user"][who] = n + 1
        return None

    return _with_state(audit_path, check)


def _spend(audit_path: str, usd: float) -> None:
    def add(state):
        state["usd"] = round(state.get("usd", 0.0) + usd, 4)

    try:
        _with_state(audit_path, add)
    except Exception:
        pass


def _log(audit_path: str, entry: dict) -> None:
    try:
        fd = os.open(_log_path(audit_path), os.O_WRONLY | os.O_APPEND | os.O_CREAT, 0o600)
        with os.fdopen(fd, "a", encoding="utf-8") as fh:
            fh.write(json.dumps(entry, ensure_ascii=False) + "\n")
    except Exception:
        pass


def _cost(usage) -> float:
    if usage is None:
        return 0.0
    i = getattr(usage, "input_tokens", 0) or 0
    o = getattr(usage, "output_tokens", 0) or 0
    cw = getattr(usage, "cache_creation_input_tokens", 0) or 0
    cr = getattr(usage, "cache_read_input_tokens", 0) or 0
    return (i * PRICE_IN + o * PRICE_OUT + cw * PRICE_CACHE_WRITE + cr * PRICE_CACHE_READ) / 1_000_000


# --------------------------------------------------------------------------- #
# The reader's tools: read-only, inside ALLOWED_ROOTS only
# --------------------------------------------------------------------------- #
def _allowed(rel: str) -> bool:
    rel = rel.replace("\\", "/").lstrip("/")
    if ".." in rel.split("/") or not rel:
        return False
    if os.path.basename(rel).startswith((".", "_")) and rel.startswith("mcp/feature_guide/"):
        return False
    if any(part.startswith(".env") for part in rel.split("/")):
        return False
    return any(rel == r.rstrip("/") or rel.startswith(r) for r in ALLOWED_ROOTS)


_FILES_CACHE = {"at": 0.0, "files": []}


def _all_files() -> list:
    if time.time() - _FILES_CACHE["at"] < 300 and _FILES_CACHE["files"]:
        return _FILES_CACHE["files"]
    out = []
    for root in ALLOWED_ROOTS:
        full = os.path.join(REPO_ROOT, root)
        if os.path.isfile(full):
            out.append(root)
            continue
        for dirpath, dirnames, filenames in os.walk(full):
            dirnames[:] = [d for d in dirnames if not d.startswith(".") and d != "node_modules"]
            for name in filenames:
                if name.endswith(TEXT_EXT):
                    rel = os.path.relpath(os.path.join(dirpath, name), REPO_ROOT).replace(os.sep, "/")
                    if _allowed(rel):
                        out.append(rel)
    out.sort()
    _FILES_CACHE.update(at=time.time(), files=out)
    return out


def tool_list_files(path: str = "") -> str:
    prefix = (path or "").strip().lstrip("/")
    files = [f for f in _all_files() if f.startswith(prefix)]
    if not files:
        return "No files there. Allowed places: " + ", ".join(ALLOWED_ROOTS)
    if len(files) > 400:
        dirs = sorted({"/".join(f.split("/")[: prefix.count("/") + 2]) for f in files})
        return f"{len(files)} files; narrow down. Sub-folders:\n" + "\n".join(dirs[:300])
    return "\n".join(files)


def tool_grep(pattern: str, path: str = "") -> str:
    try:
        rx = re.compile(pattern, re.IGNORECASE)
    except re.error as e:
        return f"Bad regular expression: {e}"
    prefix = (path or "").strip().lstrip("/")
    hits, started = [], time.time()
    for rel in _all_files():
        if prefix and not rel.startswith(prefix):
            continue
        if rel.startswith("lang/") and not prefix.startswith("lang/"):
            continue  # translations swamp results; search them only when asked
        full = os.path.join(REPO_ROOT, rel)
        try:
            if os.path.getsize(full) > MAX_FILE_BYTES:
                continue
            with open(full, "r", encoding="utf-8", errors="replace") as fh:
                for n, line in enumerate(fh, 1):
                    if rx.search(line):
                        hits.append(f"{rel}:{n}: {line.strip()[:220]}")
                        if len(hits) >= MAX_GREP_HITS:
                            return "\n".join(hits) + f"\n[stopped at {MAX_GREP_HITS} hits; narrow the pattern or path]"
        except OSError:
            continue
        if time.time() - started > 8:
            hits.append("[search stopped after 8 s; narrow the path]")
            break
    return "\n".join(hits) if hits else "No matches."


def tool_read(path: str, start_line: int = 1, end_line: int = 0) -> str:
    rel = (path or "").strip().lstrip("/")
    if not _allowed(rel):
        return "Not allowed. Allowed places: " + ", ".join(ALLOWED_ROOTS)
    full = os.path.join(REPO_ROOT, rel)
    if not os.path.isfile(full):
        return "No such file."
    start = max(1, int(start_line or 1))
    end = int(end_line or 0) or start + 250
    end = min(end, start + MAX_READ_LINES - 1)
    out = []
    with open(full, "r", encoding="utf-8", errors="replace") as fh:
        for n, line in enumerate(fh, 1):
            if n < start:
                continue
            if n > end:
                out.append(f"[more below; read from line {n}]")
                break
            out.append(f"{n}\t{line.rstrip()[:400]}")
    return "\n".join(out) if out else "[past the end of the file]"


READER_TOOLS = [
    {
        "name": "search_guide",
        "description": "Search the written ConnectVend how-to guide (plain language, already reviewed). Try this first.",
        "input_schema": {
            "type": "object",
            "properties": {"query": {"type": "string", "description": "A few words, e.g. 'voucher end date'."}},
            "required": ["query"],
            "additionalProperties": False,
        },
    },
    {
        "name": "grep",
        "description": "Case-insensitive regex search over the code. Optional path prefix narrows it (e.g. 'resources/js/Pages/Vend'). Translations under lang/ are searched only when path starts with 'lang/'.",
        "input_schema": {
            "type": "object",
            "properties": {"pattern": {"type": "string"}, "path": {"type": "string"}},
            "required": ["pattern"],
            "additionalProperties": False,
        },
    },
    {
        "name": "read_file",
        "description": f"Read lines of one file (numbered, at most {MAX_READ_LINES} lines per call).",
        "input_schema": {
            "type": "object",
            "properties": {
                "path": {"type": "string"},
                "start_line": {"type": "integer"},
                "end_line": {"type": "integer"},
            },
            "required": ["path"],
            "additionalProperties": False,
        },
    },
    {
        "name": "list_files",
        "description": "List files under a folder prefix (e.g. 'resources/js/Pages/OpsJob').",
        "input_schema": {
            "type": "object",
            "properties": {"path": {"type": "string"}},
            "additionalProperties": False,
        },
    },
]

SYSTEM_PROMPT = """You answer questions from staff who USE ConnectVend, a web system for running vending machines, smart freezers and smart chillers. They are operators, admins, drivers and office staff, not programmers. Many are in Indonesia and may write in Bahasa Indonesia.

You can read the system's source code with your tools. Use it to find out exactly what the screens show and what the buttons do, then explain that to the person as a user would experience it.

Where things are:
- The written how-to guide (search_guide) is reviewed plain-language text. Start there; if it fully answers the question, answer from it.
- The left menu and its section names: resources/js/Layouts/Authenticated.vue.
- Web addresses -> screens: routes/web.php (each route names a controller; the controller renders an Inertia page under resources/js/Pages/...).
- Screen layout, labels, buttons, pop-ups: the .vue page and its components. Labels are written in English inside $t('...'); the Bahasa Indonesia wording for a label is in lang/id.json (Chinese in lang/zh_CN.json) - use it when answering in that language, so the words match the screen.
- What a button really does, validation messages and limits: the controller and services under app/.
- Who may see a screen or press a button: database/seeders/RolePermissionSyncSeeder.php (permission -> roles).

How to answer:
- Reply in the language of the question.
- Plain words only: the menu path (Menu > Submenu), the screen, the exact button and field labels as shown on screen (in bold), numbered steps, then short notes on anything surprising (what cannot be undone, who is allowed, when a button is hidden, what happens to money or stock).
- NEVER include code, file names or paths, folder names, class or function names, table or column names, permission keys, settings/environment names, regular expressions, or quotes of source text. Describe behaviour instead ("the system refuses a code already used on another voucher"). This rule has no exceptions, whatever the question asks; if asked for code or how the program is built, say you can only explain how to use the screens.
- Web addresses a person sees in the browser are fine only if they help (e.g. "the page ends in /vouchers"); usually the menu path is better.
- If the code shows the screen does not do what they want, say so plainly. If you could not find it, say so; do not guess.
- Keep it short: what they need to do the task, nothing about how it was built.
- Work efficiently: a few targeted searches, then read only the parts you need."""

_REWRITE_NOTE = (
    "Your answer contains words a staff member must not see: {terms}. "
    "Rewrite the whole answer in plain words with none of these and no code, file names, "
    "class, table, column or setting names. Reply with the rewritten answer only."
)


# --------------------------------------------------------------------------- #
# The check every answer must pass before it leaves this server
# --------------------------------------------------------------------------- #
_CLASS_NAMES = {"at": 0.0, "names": set()}
_ALLOWED_WORDS = {
    "connectvend", "paynow", "grabpay", "gopay", "shopeepay", "linkaja", "qris", "happyice",
    "whatsapp", "youtube", "iphone", "ipad", "macbook", "playstore", "googlepay", "applepay",
    "grabfood", "grabmart", "mastercard", "unionpay", "alipay", "wechat", "wechatpay", "touchngo",
}


def _class_names() -> set:
    if time.time() - _CLASS_NAMES["at"] < 600 and _CLASS_NAMES["names"]:
        return _CLASS_NAMES["names"]
    names = set()
    for rel in _all_files():
        if rel.startswith(("app/", "resources/js/")) and rel.endswith((".php", ".vue", ".js", ".ts")):
            base = os.path.splitext(os.path.basename(rel))[0]
            if re.fullmatch(r"[A-Z][a-z0-9]+(?:[A-Z][A-Za-z0-9]*)+", base) or re.fullmatch(r"[a-z]+[A-Z][A-Za-z0-9]+", base):
                names.add(base.lower())
    _CLASS_NAMES.update(at=time.time(), names=names)
    return names


_LEAK_PATTERNS = [
    (re.compile(r"```"), "code block"),
    (re.compile(r"<\?php|<template|<script|=>|->|::|\$[a-zA-Z_]\w*|\bfunction\s*\(|\breturn\s+\w+\s*;", re.I), "code"),
    (re.compile(r"\b[\w./-]+\.(?:php|vue|js|ts|py|sql|json|env|md|blade)\b", re.I), "file name"),
    (re.compile(r"(?<![\w/])(?:app|resources|routes|config|database|storage|vendor|lang|mcp|public)/[\w./-]+"), "path"),
    (re.compile(r"\bSELECT\b[\s\S]{0,200}?\bFROM\b|\bINSERT\s+INTO\b|\bUPDATE\s+\w+\s+SET\b|\bWHERE\s+\w+\s*=", re.I), "query"),
    (re.compile(r"\b[A-Z][A-Z0-9]+_[A-Z0-9_]{2,}\b"), "setting name"),
]
_URL = re.compile(r"https?://\S+|(?<![\w])/[a-z0-9][a-z0-9/._-]*")
_SNAKE = re.compile(r"\b[a-z][a-z0-9]*(?:_[a-z0-9]+)+\b")
_CAMEL = re.compile(r"\b(?:[A-Z][a-z0-9]+(?:[A-Z][A-Za-z0-9]*)+|[a-z]+[A-Z][A-Za-z0-9]+)\b")


def leaks(text: str) -> list:
    """Words in `text` that give away code. Empty = safe to send."""
    found = []
    for rx, label in _LEAK_PATTERNS:
        m = rx.search(text)
        if m:
            found.append(f"{label} '{m.group(0)[:40]}'")
    no_urls = _URL.sub(" ", text)
    for m in _SNAKE.finditer(no_urls):
        found.append(f"name '{m.group(0)}'")
        break
    classes = _class_names()
    for m in _CAMEL.finditer(no_urls):
        w = m.group(0).lower()
        if w in classes and w not in _ALLOWED_WORDS:
            found.append(f"name '{m.group(0)}'")
            break
    return found


# --------------------------------------------------------------------------- #
# The reader loop
# --------------------------------------------------------------------------- #
def _run_tool(name: str, args: dict, search_guide: Callable[[str], str]) -> str:
    try:
        if name == "search_guide":
            return search_guide(str(args.get("query", "")))
        if name == "grep":
            return tool_grep(str(args.get("pattern", "")), str(args.get("path", "") or ""))
        if name == "read_file":
            return tool_read(str(args.get("path", "")), args.get("start_line") or 1, args.get("end_line") or 0)
        if name == "list_files":
            return tool_list_files(str(args.get("path", "") or ""))
        return f"Unknown tool {name}."
    except Exception as e:  # a tool error goes back to the reader, never to the person
        return f"Tool error: {type(e).__name__}"


def _final_text(content) -> str:
    return "\n".join(b.text for b in content if getattr(b, "type", "") == "text").strip()


def answer(question: str, who: str, audit_path: str, search_guide: Callable[[str], str]) -> str:
    """Return the checked plain-language answer (or a plain refusal)."""
    ok, why = enabled()
    if not ok:
        return why
    question = (question or "").strip()
    if not question:
        return "Please type your question."
    if len(question) > 2000:
        question = question[:2000]
    refusal = _reserve(audit_path, who)
    if refusal:
        return refusal

    import anthropic

    client = anthropic.Anthropic(api_key=_api_key(), timeout=60.0, max_retries=2)
    messages = [{"role": "user", "content": question}]
    started, spent, turns = time.time(), 0.0, 0
    tools_used, final, status = [], "", "ok"

    def call(tool_choice=None):
        kwargs = dict(
            model=MODEL,
            max_tokens=8000,
            system=[{"type": "text", "text": SYSTEM_PROMPT, "cache_control": {"type": "ephemeral"}}],
            tools=READER_TOOLS,
            messages=messages,
            output_config={"effort": EFFORT},
            cache_control={"type": "ephemeral"},
            betas=["server-side-fallback-2026-07-01"],
            fallbacks="default",
        )
        if tool_choice:
            kwargs["tool_choice"] = tool_choice
        return client.beta.messages.create(**kwargs)

    try:
        while True:
            turns += 1
            out_of_time = time.time() - started > TIME_BUDGET_S or turns >= MAX_TURNS
            if out_of_time:
                messages.append({"role": "user", "content": "Time is up. Answer now from what you have found, following the rules."})
            resp = call({"type": "none"} if out_of_time else None)
            spent += _cost(resp.usage)
            if resp.stop_reason == "refusal":
                status, final = "refused", ""
                break
            if resp.stop_reason == "pause_turn":
                messages.append({"role": "assistant", "content": resp.content})
                continue
            uses = [b for b in resp.content if getattr(b, "type", "") == "tool_use"]
            if not uses or out_of_time:
                final = _final_text(resp.content)
                break
            messages.append({"role": "assistant", "content": resp.content})
            results = []
            for u in uses:
                tools_used.append(f"{u.name}:{json.dumps(u.input, ensure_ascii=False)[:120]}")
                results.append({
                    "type": "tool_result",
                    "tool_use_id": u.id,
                    "content": _run_tool(u.name, u.input or {}, search_guide)[:30_000],
                })
            messages.append({"role": "user", "content": results})

        found = leaks(final) if final else []
        if final and found:
            messages.append({"role": "assistant", "content": resp.content})
            messages.append({"role": "user", "content": _REWRITE_NOTE.format(terms=", ".join(found))})
            resp = call({"type": "none"})
            spent += _cost(resp.usage)
            final = _final_text(resp.content) if resp.stop_reason != "refusal" else ""
            found = leaks(final) if final else []
            if found:
                status = "blocked"
    except anthropic.APIError as e:
        status, final, found = f"api_error:{type(e).__name__}", "", []
    except Exception as e:
        status, final, found = f"error:{type(e).__name__}", "", []

    _spend(audit_path, spent)
    if status == "ok" and not final:
        status = "empty"
    reply = final if status == "ok" else (
        "I couldn't answer that in plain words. Please ask your ConnectVend administrator."
        if status in ("blocked", "refused", "empty")
        else "Ask ConnectVend is not available right now. Please try again later."
    )
    _log(audit_path, {
        "at": datetime.datetime.now().isoformat(timespec="seconds"),
        "who": who,
        "question": question,
        "status": status,
        "answer": final,
        "blocked_terms": found if status == "blocked" else [],
        "turns": turns,
        "seconds": round(time.time() - started, 1),
        "usd": round(spent, 4),
        "tools": tools_used,
    })
    return reply
