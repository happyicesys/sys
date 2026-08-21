# mark1 — working notes

## Roles and permissions: one file, always

`database/seeders/RolePermissionSyncSeeder.php` is the **single source of truth**.
Its `$permissionsData` table declares every permission the app checks and every
role that holds it.

- To change access, **amend that table**. Nothing else.
- Do **not** write a one-off seeder for a permission.
- Do **not** seed permissions from a migration.

Both of those get silently undone, because the sync seeder rebuilds the entire
permission set from its own table every time it runs.

```
php artisan db:seed --class=RolePermissionSyncSeeder
```

Amending it:

| Want | Do |
|---|---|
| New permission | add a tuple `['thing', ['read','export'], ['role', …]]` |
| Grant to a role | add the role name to that tuple's third array |
| New role | just name it — roles are created automatically |
| Revoke | remove the role from the tuple |

Removing a *tuple* deletes the permission itself, so anything still checking it
starts 403ing. Grep before deleting.

The rebuild is atomic in the DB *and* in Spatie's permission cache (the cache is
what actually decides access and is not transactional, so the seeder isolates it
on an `array` store for the duration and republishes once, in a `finally`). There
is no window where live users hold zero permissions, and a failed run cannot
leave a half-built map cached.

Changes apply on the **next page load** — no logout needed.
`HandleInertiaRequests` shares permissions from a plain `share()` closure, which
Inertia evaluates every response. The real caveat: that closure reads
`roles->first()->permissions`, so a user with two roles only ever sees the first
role's permissions in the sidebar.

Superseded, kept only as history — do not run:
`ProdOwnerPermissionsSeeder`, `ProdOwnerRoleSeeder`,
`DashboardPerformanceLitePermissionSeeder`.

## Operator isolation: the global scopes are not a safety net

`Vend` and `VendTransaction` carry operator/user global scopes
(`OperatorVendFilterScope`, `OperatorTransactionFilterScope`, …). They only fire
on Eloquent queries **rooted at those models**. Three routes bypass them, and
each has already shipped a cross-operator leak:

1. **Raw joins.** `->join('vends', …)` from a table with no scope of its own
   (`vend_channel_error_logs`, `payment_gateway_logs`) reaches machine data with
   no boundary at all. Apply `OperatorVendFilterScope::viewerOperatorId()` by
   hand — that static exists so the rule has one definition.
2. **`whereDoesntHave()` / `doesntHave()`.** Global scopes are applied INSIDE the
   existence subquery, so "no row exists" silently becomes "no row exists **that
   I may see**" — which is true of every other operator's rows. Never use it to
   test for absence across a tenancy boundary; write a raw `whereNotExists`.
   (`whereHas()` is the opposite and is *useful*: it inherits the scope, so
   `->whereHas('vend')` is a working viewer boundary. Do not delete it as a
   no-op.)
3. **Shared caches.** A cache key built from request filters alone is one entry
   shared by every operator, so whoever warms it decides what everyone else
   sees. Any cached payload cut by the viewer must key on `auth()->id()`.

A request filter — including an "All" chip — is a **preference, not an
entitlement**. Apply the viewer ceiling first and let the filter only narrow it;
see `App\Support\OperatorScope` (sibling-group rule) and
`OperatorVendFilterScope::viewerOperatorId()` (the narrower rule the vend and
transaction grids enforce). The two are deliberately different — do not swap one
for the other, it moves live numbers.

Symptom to watch for: a summary card showing money against an empty grid. That
means the card and the grid are drawn from different populations, and the card
is almost always the one that has escaped the boundary. Regression coverage:
`tests/Feature/OperatorScopeLeakTest.php`.

`users` can never carry a global scope — auth, notifications and the driver
APIs all read it unfiltered — so any user-picker has to apply the boundary
itself, and several still don't. The "Assign Job(s)" driver dropdown
(`VendController::indexCustomer`) is the one that has been fixed: own operator
only, `OperatorVendFilterScope::viewerOperatorId()`, so operator 1 keeps seeing
everyone. Regression coverage:
`tests/Feature/OpsJobDriverOperatorScopeTest.php`.

Where a model is reachable by id from write endpoints (`findOrFail`), prefer a
**global scope** over a `where` in `index()` — the listing is rarely the only
door. `OperatorDeliveryProductMappingScope` is the worked example: it also
covers edit/update/delete/bindVend and two `::all()` option lists.
Regression coverage: `tests/Feature/DeliveryProductMappingOperatorScopeTest.php`.

## Pricing source: the Site owns the tier, the machine only follows it

`customers.selling_price_type` (RP1–RP5, Customer/Site edit) is the **only**
place a reference-price tier is chosen. A machine carries just a switch,
`vends.is_using_server_price` ("Is Using Server Price?"):

- **No** → the terminal sells at the VMC board price.
- **Yes, follow Site's pricing** → mark1 selling prices in the Site's tier.

There is no per-machine RP override (`vends.server_price_type` was dropped
2026-08-21 — Brian). Derive, never store: `Vend::usesServerPrice()` /
`Vend::serverPriceType()`, and the raw-query equivalent in
`VendChannel::getServerAmountAttribute()`.

On the wire, `/api/vends/{code}/parameters` emits `selectedPricingSource`
**per vend** from that flag — the `selectedPricingSource` stored in the shared
`apk_settings` row is ignored (kept in `ApkSettingParameters::SCHEMA` only so
old rows normalize and Gson keeps a schema-complete payload). `/thumbnails` and
`/menu` carry `server_price` only while the flag is on.

Every write path goes through `VendPricingSourceService` (or nudges via it):
Machine Settings save, the per-machine column on APK Settings → Edit, and a Site
RP change — each tells the terminal to re-read settings **and** re-fetch its
menu. Regression coverage: `tests/Feature/VendServerPriceSourceTest.php`.

---

# Laravel Boost guidelines

Curated by Laravel maintainers for this application. Follow closely.

**This app: Laravel 12.7 / PHP 8, Inertia 2 + Vue 3, Tailwind, Horizon,
Passport, Spatie Permission, Telescope, Clockwork, MQTT client.** Check the
installed version before applying an example from other Laravel docs — Boost
examples from another major version do not transfer.

## Code style

- Follow existing conventions in the application.
- Use Laravel conventions and established patterns.
- Use PHP 8.x features where appropriate.
- **Run Laravel Pint before considering PHP code complete.**
- Prefer clear, maintainable code over clever abstractions.

## Laravel

- Use built-in functionality whenever possible.
- Prefer Eloquent models and relationships over hand-written SQL.
- Form Requests for validation.
- Policies and gates for authorization — and see the permissions section above:
  permission *definitions* only ever change in `RolePermissionSyncSeeder`.
- Middleware for cross-cutting request concerns.
- Use the service container and dependency injection.
- Named routes and route model binding.
- Config files over hard-coded environment-specific values.

## Database

- Eloquent relationships over manual joins where practical.
- Migrations for all schema changes.
- Define appropriate indexes and foreign keys.
- Factories and seeders for test data.
- **Avoid N+1** — eager load relationships.

## Controllers

Keep them lightweight. Complex business logic goes to `app/Services`,
`app/Jobs`, or an action class — the app already has `Services/`, `Jobs/`,
`Observers/`, `Contracts/`, `ValueObjects/`; use them rather than inventing a
new layer. Form Requests for validation, route model binding, appropriate
Laravel responses.

## Models

Eloquent relationship methods; casts for appropriate attributes;
accessors/mutators where they improve readability; scopes for reusable query
constraints; factories for test setup.

## Validation

Prefer Laravel's validation facilities. Form Request classes for complex
request validation. Meaningful rules and messages.

## Testing

- Every new feature gets appropriate tests.
- Every bug fix gets a regression test where practical.
- Prefer Pest if Pest is installed.
- Run the smallest relevant test set first; the full suite when appropriate.

## Frontend

Inertia + Vue 3 with Tailwind is the stack — follow it. Reuse existing
components before creating new ones. Do not introduce another frontend
framework without a clear reason.

## Environment

- Never hard-code secrets. Never expose `.env` values to users.
- Read config through `config()`, not `env()`, outside config files.
- Do not modify `.env` unless explicitly required.

## Documentation

Use Laravel Boost's documentation tools when Laravel-specific information is
needed, and check installed package versions before applying examples.

---

# Performance

Optimise for performance as a default, not an afterthought — but with evidence.
Telescope and Clockwork are installed; use them rather than guessing.

The DB is large: `vend_transactions` ~4.8M rows, `gp_metrics` ~2.4M,
`vend_temps` ~12M, `stock_count_items` ~2M. Full-table scans are not free here.

- Read `data_dictionary()` on the `sys-happyice` MCP before writing reporting
  queries — it documents which tables are pre-aggregated (`gp_metrics`,
  `fact_*`, `customer_period_summaries`) versus raw truth, and the canonical
  metric definitions. Improvised SQL will not tally with the dashboards.
- Prefer the pre-aggregates for dashboard/trend work; reserve
  `vend_transactions` for genuinely transaction-level questions.
- Avoid `vend_records` for financial accuracy — legacy, drifts, never
  reconciled.
- Chunk or queue heavy work (Horizon is installed); do not block a request.

# Production database

`sys-happyice` MCP is read-only against live production. Use it to confirm
schema and real values before writing migrations or queries — do not infer
schema from model files alone.

