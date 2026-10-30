# Happy Hour audit — 2026-10-30

Scope: the whole Happy Hour feature on `main` at `c6353bd98f`. That covers the planner and slot lifecycle, machine membership, pricing and sale tagging, the stats, the admin page and permissions, and how the big-board APK (320+) and the freezer APK (40+) use the feeds. Each area was read separately. Every finding below marked **prod** was confirmed with read-only queries against live mark1.

Prod is healthy right now:
- No stale or unclosed open slots, and no open slots on non-members, inactive or paused machines.
- No slots were created after a campaign was deleted, and no tags point at deleted slots.
- Every one of the 2,052 tagged single sales was charged exactly its slot's promo price.
- 915 of 918 tagged baskets add up to their header. The 3 that don't are from 10-16 to 10-18 and were logged, not guessed.
- Rounding is integer-only and can never reach 0 or below. Retried TRADEs are not tagged twice.
- No IDOR: every by-id route goes through the owner scope and returns 404 for another operator's campaign.

## Fix first (mark1) — all nine FIXED 2026-10-30

All nine are fixed with regression tests in `HappyHourCampaignTest` / `HappyHourVendingTest`. The rules are recorded in mark1 `CLAUDE.md` under Happy Hour.

| # | Sev | Where | Defect | Evidence |
|---|---|---|---|---|
| 1 | High | `HappyHourPlanner::run` / `planCampaign`, controller save/delete | A save's re-plan and the minute run write slots at the same time with no lock between them. The run also plans from campaigns it loaded at its start, so a campaign deleted or paused mid-run can still get new slots, and nothing later cancels open slots of an inactive or deleted campaign. The unique key `(vend_id, starts_at, product_id)` does not stop duplicates, because each writer stamps its own minute. | **prod** 2026-10-15 21:57: machines 1358, 1371 and 1372 each ran 4 slots where `skus_per_slot` is 2 (ids 2888–2899): the same SKUs starting at 21:56 and again at 21:57. |
| 2 | Med | `HappyHourCampaignController::update` (`isDirty(SCHEDULE_FIELDS)`) | Re-ordering the Prioritize or Never-discount list counts as a schedule change and cancels every live slot of the campaign. | **prod** 2026-10-30 12:38: a priority list re-ordered `[840,954,842]` → `[840,842,954]` cancelled 445 live slots on 223 machines, and 10 of them changed SKU mid-slot. The save request also took about 26 s. |
| 3 | Med | `HappyHourCampaignController::index` | Today's deferred `campaignStats` does not save time. The browser's second request re-runs every eager query (campaigns, slots, stock, L24h, machine and product options), because only the stats are wrapped in a closure. | code: Inertia evaluates the non-closure props on every partial reload. |
| 4 | Med | `HappyHourSales::results` / `dailyTotals` | On board-price machines the discount is "today's `vend_channels.amount` − amount charged". Changing a board price, or re-mapping the channel later, rewrites past Happy Hour discounts. | **prod** since 10-13: 212 tagged sales are on channels whose price or product changed, so their discount reads $267.00 when it should be $196.00. |
| 5 | Med | `HappyHourSales::tagVendingLines` | A vending line is tagged only when the board's own `TIME` falls within the slot ± 300 s. The app prices on mark1's clock and keeps a promo cart for 180 s past the slot end, so a board whose clock runs ahead loses the tag even though the TRADE's `HH` list names the slot. An untagged basket is also never repriced, which overstates product revenue and GP. | **prod** about 29 big boards run 1–4.5 min ahead (2869 +267 s, 2651 +255 s, 2688 +254 s). |
| 6 | Med | `HappyHourPlanner::checkStartingSlot` | A swap at slot start rewrites product and prices on the SAME slot id. A board that missed the nudge keeps selling the old SKU under that id, and those sales are rejected (vending: product mismatch; freezer: no slot at that price). | code; prod shows 2 untagged `HH` TRADEs in 17 days, neither of them a swap yet. |
| 7 | Med-Low | `HappyHourPlanner::nudge` | The debounce key lives 20 s but the job fires after 5 s, so a slot change 5–20 s after a nudge sends no nudge. The freezer then advertises the old SKU for the whole slot. | code. |
| 8 | Med-Low | `HappyHourPlanner::closeEnded` / `endSoldOut` / `endPaused` | These run before planning with no per-slot try/catch. One throwing slot (for example a timeout on the unindexed tag query) aborts every minute run, so the fleet gets no picks, swaps or sold-out ends. | code. |
| 9 | Low | Kernel `happy-hour:run`, `windowOn` | At midnight the run starts late (it runs in the foreground behind the `daily()` jobs) and only looks at today, so the 00:00 slot never exists. The first slot of the day starts at 00:01:00 and is created at 00:01:08. | **prod** every day from 10-25 to 10-30. |

Fixes as made (where they differ from the suggestion, the CLAUDE.md wording governs):
1. One `Cache::lock('happy-hour-plan')` around `run()` and around the controller's cancel + plan. Re-read the campaign inside the lock. Add a sweep in `run()` that cancels open slots whose campaign is inactive or deleted, or whose machine is no longer a member or eligible. Move the save's re-plan to a queued job.
2. Sort and de-duplicate the id lists in `fields()` before `fill()`.
3. Make `campaigns`, `machines` and `products` closures, so the partial reload computes only `campaignStats`.
4. Freeze the list price on the sale at tagging: the item's pre-reprice `unit_price_amount`, and the slot's `original_price` for a single sale. Compute the discount from that, never from live `vend_channels`.
5. For vending, the `HH` list is the proof. Accept the slot when either `transaction_datetime` or `created_at` (arrival) falls within the slot ± grace, up to its planned end. Readers search with 15 min of clock slack.
6. A swap cancels the old row and inserts a new one. Every slot keeps `planned_ends_at`, and sales are tagged up to it, so a board that missed a cancel, swap or pause still gets its promo sales tagged.
7. Set the debounce lifetime to the delay (5 s), or have the job clear the key before it reads.
8. Wrap each slot in try/catch, cap closing at a few hundred per run, and do the closing after planning.
9. `->runInBackground()`, and plan tomorrow's first slot when now + lead crosses midnight.

## Also worth fixing (mark1, lower)

- **Skipped list cost:** `/skipped` is cached per user, and a miss costs about 1,900 queries for the 210-machine Default campaign. Key the cache by the viewer's operator instead.
- **Skipped list goes stale:** it loads only on mount, so after a save it stays stale or keeps saying "Checking…". Refetch it when `campaigns` changes.
- **Edits drop SKUs:** an edit silently drops Never-discount and Prioritize SKUs that are not on any planogram today (for example Default's PP02–PP05 once they leave). Merge the stored ids back into the picker.
- **Validation leaks:** the "both lists" error messages look up codes without the operator scope, and run before the update's 404, so a restricted admin can learn any machine's code by id. Use scoped lookups and resolve the campaign in `authorize()`.
- **Unbounded inputs:** id arrays have no `max`, `/stats` accepts 366 days and misses the cache each time, and `/preview` without `vend_id` ranks every machine.
- **Deleted campaigns:** their pivot rows still count as "bound", so ineligible machines from campaigns 1–3 are offered in the picker forever.
- **Reversed card baskets:** the basket-line stats filter `i.is_refunded` but not `t.is_refunded`, so a fully reversed card basket's Happy Hour lines still count (0 such baskets so far).
- **Full-price single sale tagged:** a stale `HH` entry can tag a full-price single sale, because nothing checks that amount < list. All 2,052 tagged so far are at exactly the promo price.
- **Stale header tag:** `write()` never clears a header tag when a re-tag leaves no promo line.
- **AI extra unit on a card sale:** the charge values it at the promo price, but `FreezerSaleAudit::rewriteBasket` stores the list price, so items exceed the header and the unit goes untagged. This path has not run in prod yet.
- **One-run zero stock:** a single zero-stock reading ends a slot as sold out and the same SKU is re-picked a second later (prod: 2129, 2302, 2363, 2474 on 10-24 to 10-26). Require two consecutive zero readings.
- **Late swap:** the start-of-slot swap check has no lower bound, so a slot that missed its pre-start check can be swapped while live.
- **Overlap check gap:** it covers only the posted `vend_ids`, not hidden hand-picked machines the save keeps.
- **Hand-picked machines:** a hand-picked machine that is deactivated, moved operator or downgraded keeps being planned. This is covered by the sweep in fix 1.
- **Translations:** server-side Happy Hour strings (request messages, overlap messages, rule names, group labels) are not translated, and neither is the literal "L24h".
- **Doc drift:** CLAUDE.md says the permission is superadmin + admin, but the seeder also grants supervisor and technician (Brian's sheet sync on 10-15). Fix the doc.

## APK side (needs a release; not urgent)

- **Freezer (41):**
  - A promo cart has no expiry: the price is held until the kiosk goes back to Idle.
  - Prices are decided on the raw device clock.
  - Fix: port vending 318's `HappyHourCart.expireStale` and use a server-anchored clock.
- **Big board (321):**
  - `HappyHourCart.lines` sends one `HH` entry per channel, so a promo unit and a full-price unit of the same channel both get tagged.
  - `GRACE_MS` of 180 s could drop to about 60 s once fix 5 is in.
  - On a slow SIM, a board keeps no server-time anchor (round trips over 10 s are discarded).
- **Both apps:** while a slot is live, re-read the feed every ~5 min, so a missed nudge cannot leave a cancelled or swapped promo running for a whole slot.
- **Big board price source:** when the app's pricing source and `vends.is_using_server_price` disagree, the promo silently never shows. mark1 should flag it on the campaign chip.

## Test gaps worth closing with the fixes

- A delete or pause during a planner run.
- `/stats` and `/skipped` for another operator's campaign return 404.
- A save that only re-orders a list keeps live slots.
- Validation bounds (window end ≤ start, ends before starts, discount 0 or 91).
- Overlap edge cases (adjacent windows, disjoint dates, re-activating a paused campaign).
- Stats and skipped caches never shared between an HIPL viewer and a restricted viewer.
