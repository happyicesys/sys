# mark1 read-only MCP server

Lets Claude (Desktop / Cowork) query your live mark1 MySQL database to answer
analytical questions — e.g. *"what can boost sales based on the Sales
Transactions data?"* — **without ever being able to write to it.**

It runs on a machine that can reach the mark1 DB (your laptop if the app is
local at `127.0.0.1:3306`, or the mark1 server). Claude's reasoning runs on your
Max plan; this server is just a local tool provider, so there's no API bill and
nothing is exposed to the internet.

Tools exposed: `data_dictionary`, `list_tables`, `describe_table`, `run_query`
(SELECT/WITH only, row- and time-capped).

---

## 1. Create a read-only MySQL user (the real safety guarantee)

Log into MySQL as an admin and run (change the password):

```sql
CREATE USER 'mark1_ro'@'127.0.0.1' IDENTIFIED BY 'CHANGE_ME_strong_password';
GRANT SELECT ON mark1.* TO 'mark1_ro'@'127.0.0.1';
FLUSH PRIVILEGES;
```

This user can only `SELECT`. Even if a query were malformed, it physically
cannot insert, update, or drop anything. (The server also refuses non-SELECT SQL
as a second layer.)

> If your MySQL runs in Docker (mark1 has a `docker-compose.yml`), run the above
> inside the db container, e.g. `docker compose exec db mysql -uroot -p`.

## 2. Install

Requires Python 3.10+. From this `mcp/` folder:

```bash
cd /Users/brian/codes/mark1/mcp
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
```

## 3. Configure credentials

```bash
cp .env.example .env
# edit .env — set MARK1_DB_PASSWORD to the mark1_ro password
```

Quick smoke test (lists tables and exits if creds work):

```bash
source .venv/bin/activate
python -c "import server; print(server.list_tables()[:300])"
```

## 4. Register with Claude Desktop

Edit (create if missing):

`~/Library/Application Support/Claude/claude_desktop_config.json`

```json
{
  "mcpServers": {
    "mark1": {
      "command": "/Users/brian/codes/mark1/mcp/.venv/bin/python",
      "args": ["/Users/brian/codes/mark1/mcp/server.py"]
    }
  }
}
```

The credentials come from the `.env` file you created. (Alternatively, drop the
`.env` and pass them inline here under an `"env": { ... }` block — either works.)

Fully quit and reopen Claude Desktop. The `mark1` tools should appear in the
connectors/tools list.

## 5. Ask things

Once connected, just ask in plain language. Claude will call
`data_dictionary` / `describe_table` to learn the schema, then write and run its
own read-only SQL. Examples:

- "What can you suggest to boost sales based on the current Sales Transactions data?"
- "Which sites had the biggest sales drop this month vs last month?"
- "Top 20 products by gross profit in the last 90 days, with margin."
- "Are there machines with an unusual spike in refunds or vend errors lately?"

## Safety summary

- DB user is `SELECT`-only (hard guarantee).
- Server rejects anything that isn't a single `SELECT`/`WITH` statement, strips
  comments, blocks multiple statements and write/DDL keywords.
- Every query runs in a `READ ONLY` session with `MAX_EXECUTION_TIME` and a row
  cap (defaults: 500 rows, 5000 max, 15s).
- Runs locally — not exposed to the internet.

## Troubleshooting

- *Tools don't appear*: confirm the `command` path points at the venv's python
  and fully restart Claude Desktop.
- *Auth/connection error*: re-check `.env`; from the host, `mysql -u mark1_ro -p
  -h 127.0.0.1 mark1` should connect.
- *Docker DB not on 127.0.0.1*: set `MARK1_DB_HOST`/`MARK1_DB_PORT` to the
  published port (see `docker-compose.yml`).
