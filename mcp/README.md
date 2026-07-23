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

---

## Per-user access (tokens bound to mark1 users)

Access is managed from mark1 itself: **Admin ▸ MCP Access** (visible only to
`superadmin`/`admin`). An admin picks a user, gives the token a label, and the
token is shown **once**. Revoking it — or deactivating that mark1 user — cuts
access immediately. Only the token's SHA-256 hash is stored; the server
validates by hashing the presented token and looking it up in
`mcp_access_tokens` (read-only, like everything else). Every tool call is
appended to an audit log (`mcp/audit.log` by default) as
`time · user_id · email · action · query`.

Run the migrations first so the table and permissions exist:

```bash
php artisan migrate
```

### Local / stdio (one trusted person)
Set the issued token in `.env` (or the Desktop config `env` block) as
`MARK1_MCP_TOKEN=mk1_...`. If left blank, the server runs in trusted-local mode
(the SELECT-only DB user is the guard) — fine for your own machine.

### Hosted for a team (no SSH — recommended for boss/admin)
Run one instance in HTTP mode and put it behind HTTPS (e.g. an nginx subdomain
on Forge with a Let's Encrypt cert, proxying to `127.0.0.1:8765`). MySQL stays
private; nothing is exposed except the authenticated HTTPS endpoint.

```bash
# on the server, in this mcp/ folder
source .venv/bin/activate
MARK1_MCP_TRANSPORT=http .venv/bin/python server.py    # binds 127.0.0.1:8765
```

Each person adds it as a custom connector by URL and supplies their own token as
`Authorization: Bearer <token>` (the admin page issues one per person). A
revoked or deactivated user is rejected on their next request (HTTP 401).

> Note: exactly how a custom remote connector is added in the Claude app (URL +
> token vs OAuth) depends on the app version/plan — confirm the current steps in
> Anthropic's docs. The server accepts a static bearer token / `X-MCP-Token`
> header; if your client only supports OAuth remote MCP, use the stdio-over-SSH
> option in the section above instead, still with per-user `MARK1_MCP_TOKEN`.

---

## OAuth connector (no per-PC setup) — alongside mk1_ tokens

Both auth methods work simultaneously on the same endpoint:

- **mk1_ token** (`?token=` / bearer / `X-MCP-Token`): unchanged.
- **OAuth** (Claude "Add custom connector"): user pastes
  `https://mcp.happyice.com.sg/mcp`, clicks Connect, logs in with their mark1
  account, approves. No Node, no config file; works on web too.

Pieces (all additive to Passport; machine/APK personal tokens untouched):
- `/.well-known/oauth-authorization-server` (+ openid-configuration alias) on
  the app — RFC 8414 metadata (`McpOAuthController`).
- `POST /api/oauth/register` — RFC 7591 Dynamic Client Registration; PUBLIC
  clients only (secret-less → PKCE enforced); redirect hosts allow-listed
  (`MCP_OAUTH_REDIRECT_HOSTS`, default claude.ai/claude.com/anthropic.com +
  loopback); throttled; rejected URIs logged.
- `mcp` scope added to `Passport::tokensCan`; auth-code tokens expire in 30d
  (refresh 180d) — personal access tokens keep their 5-year expiry.
- server.py validates Passport RS256 JWTs (`storage/oauth-public.key`):
  signature+expiry → jti not revoked → user active → **active Admin ▸ MCP
  Access grant required** → scope gate (machine personal/password-client
  tokens always rejected). 401s carry `WWW-Authenticate` pointing at
  `/.well-known/oauth-protected-resource` (served by server.py, RFC 9728).

**Access control stays in one place:** a user can OAuth-connect only while they
hold an active (non-revoked) token row on the Admin ▸ MCP Access page. Revoke
the row (or deactivate the user) and both auth methods die.

Deploy notes: `php artisan migrate` not needed (no new tables); if config is
cached run `php artisan config:cache` after deploy; restart the mcp daemon.
