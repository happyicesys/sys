# Service Notice in Ops Jobs — plan (2026-09-19)

> **Status: SHIPPED to main 2026-09-20 (push = deploy).** Commits: `2cec10b9f6`
> migrations (pushed first, on their own — the deploy serves code before it
> migrates), `12f90b3f6f` backend, `5ee93d7002` frontend, plus the asset rebuild.
> F1–F9 are done; F10–F13 (phase 2) are not started. Full suite 1104 → 1148
> passed, no regressions. Prod needs `db:seed --class=RolePermissionSyncSeeder`
> after the deploy or nobody holds the new permissions.
>
> Decisions taken (Brian, 2026-09-19: "both … best practices, OOP, validate
> before and after") — §9 items 1–6 were built on the recommended answers:
> own tables; nothing synced to cms; a machine may carry a job AND a notice the
> same day; a notice always sits inside an ops job; picker and operator_3pl get
> no access; no PDF.
>
> Differences from this plan as built:
> - The "machine must belong to the job's operator" idea was DROPPED after
>   checking prod: ~16% of operator-1 job items sit on a sibling operator's
>   machine. The rule is the dropdown's own — the machine must be visible to the
>   viewer (`ManagesOpsJobStops::vendVisibleToViewer`).
> - `RemoveEmptyOpsJob` deleted any job without machine ITEMS nightly, which
>   would have cascade-deleted a notice-only job (and already did that to
>   task-only jobs). Fixed: "empty" now means no stop of any kind.
> - Batch Assign Driver does not move a notice to another driver's job yet.

## 1. What was asked

On `OpsJob/Edit.vue`, the "Add More Job(s)" machine dropdown should be shared by
two actions: **Open New Job** (today's behaviour) and **Open Service Notice**
(new). The feature reference is the Service Notice in `cms/`.

## 2. What cms actually has (verified in code)

- A service notice is **not its own entity** — it is a `transactions` row with
  `is_service = 1`, plus child rows in `service_items`.
- Each service item = `desc` (what to fix), `desc1` + photos/videos (**before**),
  `desc2` + photos/videos (**after**), and a status:
  `1 New · 2 Completed 完成 · 90 Incomplete 未能完成 · 99 Cancelled 取消`.
- The notice cannot be marked Delivered until **every item has moved past New**.
- Created three ways: convert an empty transaction, click the wrench on a list
  row, or "Batch Generate" from the customer list with a textarea — **one line
  = one service item**.
- It rides the normal delivery flow: has a date, a driver/technician and a
  sequence, shows up on the driver's job-assign board.
- No numbering of its own, no notifications, no dedicated list page (just an
  `is_service` filter on transaction lists), PDF reuses the invoice template
  with the photo cells commented out.
- Rough edges we must **not** port: no validation or role checks on any service
  endpoint; autosave silently deletes a row when its description is cleared;
  5 padded blank rows; two entry points disagree on `pay_status`; remote files
  never actually deleted; dead columns `attachment1/2`, `remarks`, `sequence`.

## 3. Where it lands in mark1 (verified in code)

- `OpsJob` = one driver-day. `OpsJobItem` = one machine stop (stock, cash,
  freeze, cms sync). `OpsJobTask` = a non-machine stop, added 2026-05 as a
  **second row type merged into the same table by `sequence`** — this is the
  structural precedent to follow.
- The dropdown lists every bound `Vend` **not already on this job**; "Add"
  posts `POST /ops-jobs/{id}/item/create`.
- mark1 has: polymorphic `attachments` (with an unused-here `type int`
  column), FilePond uploader, `RunningNumberService`, `UserLogger` audit,
  Excel export, operator-ceiling helpers. mark1 has **no** PDF library, no
  Telegram/WhatsApp, no ops-job API (drivers use the web pages on a phone).
- Nothing called service notice/ticket/work-order exists. `Maintenance` model +
  `maintenances` table is dead legacy (no route, no page) — ignore it.

## 4. Design decision: new tables, not a flag on `ops_job_items`

cms flagged a transaction because a transaction was its only "stop" concept.
Doing the same here (`ops_job_items.is_service`) would drag a repair visit
through stock-in, freeze, cash, cms transaction sync, refill stats and the Ops
Dashboard queries — every one of which would need an exclusion, and
`indexCustomer` is join-sensitive. `OpsJobTask` already proved the
sibling-row-type pattern works.

```
ops_jobs
 ├─ ops_job_items          (machine stock stop)      existing
 ├─ ops_job_tasks          (non-machine stop)        existing
 └─ service_notices        (machine repair stop)     NEW
      └─ service_notice_items                        NEW
           └─ attachments (morph, type = 1 title / 2 before / 3 after)
```

**`service_notices`**: `id, code (int, per-operator running no.), operator_id,
ops_job_id (FK), vend_id, customer_id (snapshot at create), sequence
decimal(8,2), status tinyint default 1, remarks text, completed_at/by,
undo_completed_at/by, cancelled_at/by, created_by, updated_by, timestamps`.
Indexes: `ops_job_id`, `(vend_id, created_at)`, unique `(operator_id, code)`.

**`service_notice_items`**: `id, service_notice_id (FK cascade), sequence,
status tinyint default 1, desc text, desc_before text, desc_after text,
incomplete_reason text, status_changed_at/by, created_by, updated_by,
timestamps`.

Status constants live on the models (no magic numbers). Item codes keep the
cms values so staff vocabulary carries over: `1 / 2 / 90 / 99`.
Notice: `1 Pending · 3 Completed · 99 Cancelled`.

## 5. Features, in detail

### F1 — Shared dropdown, two actions (the ask)
- Label becomes **"Add Job / Service Notice"**; one machine dropdown, two
  buttons: green **Open New Job**, amber **Open Service Notice** (wrench icon).
- The dropdown must now list **all** bound machines, because a machine already
  being topped up today can still need a repair. Machines already on the job
  show a "· in job" suffix and **Open New Job** is disabled for them (server
  already refuses the duplicate).
- Fix in passing: the Add button's `:disabled` uses `&&` so it is never truly
  disabled; make both buttons `disabled` when nothing is selected or the
  permission is missing.
- Option list gets the viewer's operator ceiling (today it walks all `vends`).

### F2 — Create modal
- Opens on **Open Service Notice** with the machine pre-filled (code, site,
  address read-only).
- **Service items textarea — one line = one item** (the cms batch pattern
  staff already know). Blank/whitespace lines are dropped server-side.
- Optional **Remarks** and **Sequence** (defaults to end of the job).
- `POST /ops-jobs/{id}/service-notices` → creates notice + items in one DB
  transaction, assigns the running code, shown as **SN-10001**.
- Validated through a FormRequest; at least one item required.

### F3 — Row in the job table and on the route page
- Third row type in `mergedRows`, ordered by `sequence` like items and tasks.
- Amber wrench badge, `SN-xxxxx`, machine code, site, **progress "2/3"**,
  status badge, inline sequence edit, link to the detail page.
- Counters: "Total of N Job(s) · M Task(s) · K Service Notice(s)".
- `OpsJob/Route.vue`: appears as a stop with a wrench marker, using the
  machine's existing lat/lng (no geocoding needed). Sequence save + renumber
  include it, same as tasks.
- `OpsJob/Index.vue` job counts include notices; `RemoveEmptyOpsJob` must treat
  a job with a notice as non-empty.

### F4 — Service Notice page (`/service-notices/{id}/edit`)
- Mobile-first; this is what the technician uses on site.
- Header: SN code, status, machine + site + address (tap-to-map), date,
  assigned driver/technician (from the ops job), remarks.
- Item cards (table on desktop), bilingual labels as in cms:
  | # / 进展状况 | Description 维修项目 | Before 维修前 | After 维修后 |
  Each of the three columns = text + photo/video/PDF attachments.
- Per item buttons: **Done 完成 · Incomplete 未能完成 · Cancelled 取消**, plus
  **Undo** back to New. **Incomplete requires a reason** (new vs cms — so the
  office knows why).
- **Add item** button; explicit Save per item (debounced autosave is fine, but
  clearing a description never deletes the row). Delete item = admin /
  supervisor only, confirms, and really removes the files from Spaces.

### F5 — Completing a notice
- **Complete** is blocked until every item is past New — the cms rule, with
  the same bilingual error. Stamps `completed_at/by`.
- **Undo complete** (stamps `undo_*`), **Cancel notice** (`update`), **Delete**
  (`delete`, admin/supervisor).
- No picked/stock-in/verify ladder, no freeze, no cash, no payment status, no
  cms transaction — a notice never touches stock or sales.

### F6 — Photos / videos
- Existing `Attachment` morph + `UploadFileInput` (FilePond): image, video,
  PDF, 20 MB. Stored under `sys/service-notices/`.
- Slot encoded in `attachments.type` (1 title, 2 before, 3 after) instead of
  cms's two-boolean trick. Hashed filenames (`storePublicly`) — no same-minute
  collisions, and stays inside the 255-char `full_url` limit.

### F7 — Permissions and tenancy
- New tuple in `RolePermissionSyncSeeder`: `service-notices`
  `read / create / update / delete / export`.
  - read/create/update: superadmin, admin, supervisor, technician, driver,
    sup_driver, operator_admin, operator_supervisor, operator_driver
  - delete/export: superadmin, admin, supervisor, operator_admin,
    operator_supervisor
  - picker, operator_3pl, observer, prod_owner: none (Brian to confirm 3PL).
- Constructor `permission:` middleware per method **and** the operator ceiling
  via the parent job (`scopedOpsJob`). Do **not** copy `OpsJobTaskController`,
  which is currently ungated (flagged as a separate fix).
- Seeder must be run on prod after deploy.

### F8 — Service Notices list (`/service-notices`)
- Sidebar: Daily Jobs → **Service Notices**.
- Filters: date range, operator, driver/technician, machine code, site,
  status, "has incomplete items". Columns: SN code, date, machine, site,
  assigned, items done/total, status, completed at/by, created by.
- Excel export. Built on the `CardTerminalUnit` index convention.
- This is what cms never had — it is where the office chases incompletes.

### F9 — Audit
- `UserLogger` records every change automatically; who/when stamps on each
  status change. `HistoryButton` on the detail page.

### Phase 2 (after F1–F9 are in the field)
- **F10 Carry-over**: "Reopen on another day" copies a notice's Incomplete
  items into a new notice on a chosen date/driver, linked by
  `previous_service_notice_id`.
- **F11 Ops Dashboard**: the "Assign Job(s)" modal gets a Job / Service Notice
  toggle + the line-per-item textarea, so notices can be raised for many
  machines without opening a job first (the cms Batch Generate equivalent).
- **F12 Machine history**: last N service notices on the machine's
  Setting/Edit page, and a wrench indicator on the Ops Dashboard row when a
  notice is open (correlated subquery, never a join — see the
  indexCustomer memory).
- **F13 Raise from an alert**: one-click "Open Service Notice" from a machine
  error / smart alert, pre-filling the item text.

### Deliberately not ported
PDF print (mark1 has no PDF lib; the cms PDF prints empty photo cells anyway),
signature, payment status coupling, email, 5 padded blank rows, autosave-delete,
the wrench "convert" toggle (a notice is created as a notice, never converted).

## 6. Build order

1. Migrations + models + constants + factories.
2. Seeder tuple; controller (`ServiceNoticeController`) with FormRequests,
   ceiling, resources; feature tests (permission matrix, operator ceiling,
   completion gate, blank-line handling, attachment slot, delete cleans files).
3. F1 dropdown + F2 modal + F3 merged row in `Edit.vue`; `edit()` eager-load.
4. F4/F5/F6 detail page.
5. Route.vue + Index counts + `RemoveEmptyOpsJob`.
6. F8 list + export + sidebar.
7. Full suite on a private DB, Pint, asset build from a clean worktree at HEAD,
   diff review, then push (= deploy) + run the seeder on prod.

`Edit.vue` is 2.2k lines; the modal and the row go into
`Pages/OpsJob/ServiceNotice/*` components rather than growing it further.

## 7. Blast radius

Additive. Touches existing code only in: `OpsJobController::edit` (options +
eager-load), sequence save/renumber, index counts, `RemoveEmptyOpsJob`,
`Edit.vue`, `Route.vue`, `Authenticated.vue`, the seeder. No change to
`ops_job_items`, stock, sales, cms sync, or the Ops Dashboard query in phase 1.

## 8. Risks

- Sequence/renumber code currently knows two row types; the third must be added
  everywhere `opsJobTasks` is handled (`OpsJobController` ~1997–2081, 2400) or
  notices will be dropped on renumber.
- Large videos on site over mobile data — FilePond 20 MB cap stays.
- `database/schema/mysql-schema.sql` is stale; confirm live schema via the
  `sys-happyice` MCP before writing migrations.

## 9. Decisions needed from Brian

1. **New tables** (recommended) vs a flag on `ops_job_items`?
2. **cms**: mark1 becomes the only home for new service notices and nothing is
   synced to cms (recommended) — or must cms still see them?
3. Same machine may have **both a job and a service notice on one day**
   (recommended: yes)?
4. Must a notice always sit inside an ops job (recommended for phase 1), or do
   you need an unassigned backlog from day one?
5. `operator_3pl` and `picker`: no access (recommended)?
6. PDF print: drop (recommended) or needed for a customer/landlord copy?
