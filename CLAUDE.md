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
