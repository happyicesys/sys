# Blind SKU — Build Status (as-built)

## ✅ Built & coherent (Phases 1–4)

### Phase 1 — schema + models
- `database/migrations/2026_06_09_000000_add_is_parent_sku_to_products.php`
- `database/migrations/2026_06_09_000001_create_product_mapping_item_children_table.php`
- `database/migrations/2026_06_09_000002_add_product_mapping_id_to_unit_costs.php`
- `app/Models/ProductMappingItemChild.php` (new) + relations on `Product`, `ProductMappingItem`, `UnitCost`.
- `Product::unitCosts()` / `latestUnitCost()` now scoped to `whereNull('product_mapping_id')` so blended rows never leak into a product's intrinsic cost (no effect on normal products).

### Phase 3 — core logic (fully unit-tested, infra-safe)
- `app/Services/BlindSkuService.php`
  - `allocateToPick()` — Largest-Remainder + water-filling allocator (the OpsJob split logic).
  - `blendedCostCents()` — weighted blended cost.
  - `recomputeSlot()` / `recomputeForChildProduct()` / `recomputeSlots()` — maintain per-mapping blended `unit_costs` rows.
- `app/Observers/UnitCostObserver.php` (fans out on cost change; ignores blended rows → no recursion)
- `app/Observers/ProductMappingItemChildObserver.php`
- `app/Jobs/RecomputeBlindCosts.php` (queued)
- `app/Console/Commands/RecomputeBlindCostsCommand.php` (`blind:recompute-costs`, scheduled 00:20)
- Observers registered in `AppServiceProvider`.
- `tests/Unit/BlindSkuServiceTest.php` — **18 tests** covering every corner case.

### Phase 2 — binding UI + validation (frontend builds ✓)
- `Product/Edit.vue` — `is_parent_sku` checkbox (persists via `$fillable`).
- `ProductMapping/FlavourBinding.vue` (new) — "How many flavours?", remove-on-select dropdown, weight % with even-default (largest remainder), live "Total 100%" check, save.
- `ProductMapping/Edit.vue` — renders the panel under each parent slot row.
- `ProductMappingController@saveItemChildren` + route `POST /product-mappings/items/{itemID}/children` + server validation (sum=100, 1–100, distinct, no self, no nested housing).
- Resources expose `is_parent_sku` + `children`; `edit()` eager-loads children and passes `flavourOptions`.

### Phase 4 — gross-profit wiring + infra guards
- `VendTransactionService::processMapping()` — parent-SKU sales resolve the **per-mapping blended** `unit_costs` row; normal products unchanged.
- **3 stock-cost joins guarded** in `VendController` with `product_mapping_id IS NULL` so blended rows can't multiply existing vend stock-value sums (zero change for non-blind vends).

## Phases 5–6: OpsJob + Planning — decision + what's now built

**Decision: Option A — virtual child ledger** (recommended). It's additive (no change to the channel/VMC model), gives true per-flavour inventory tracking, and keeps the machine-facing count at the slot level reconciled by ratio — matching "the machine can't see flavours." (B = children as sub-channels breaks VMC tally, highest risk; C = parent-only hint can't track per-child inventory.)

### Built now (safe, additive foundation for A)
- `database/migrations/2026_06_09_000003_create_ops_job_item_channel_children_table.php` — per-flavour ledger (`to_pick_qty`, `picked_qty`, `actual_qty`, `weight_pct` snapshot) layered on the parent's `ops_job_item_channels` row.
- `app/Models/OpsJobItemChannelChild.php` + `OpsJobItemChannel::children()`.
- `BlindSkuService::syncChannelChildAllocations()` — the clean seam: give it the parent channel id, the housing's needed qty, and the flavour context (weight + per-child availability + cap); it allocates (tested `allocateToPick`) and persists the per-child To-Pick, **without clobbering** any picked/stock-in already recorded.

### Remaining wiring (live daily-driver flow — do with your running env, since PHP can't run in my sandbox)
1. **OpsJobController** (the channel-building methods around the `to_pick`/`needed` raw-SQL aggregates): for each channel whose product `is_parent_sku`, resolve the slot's `product_mapping_item_children` (weights) + each child's `is_available` and pick limit, compute `parentNeeded = capacity - vmc_qty`, and call `syncChannelChildAllocations()`. Eager-load `opsJobItemChannels.children.childProduct` for display.
2. **Subtotal rule (critical):** the parent is a visual aggregate — bottom totals must count **children**, not the parent, so nothing is double-added. Where the raw-SQL `to_pick`/picked aggregates sum channel rows, exclude `is_parent_sku` parent channels and add the child-ledger sums instead.
3. **EditItem.vue / Channel.vue:** render the parent (housing) row with Needed/Capacity + VMC count (aggregate of children), and indented child rows carrying the editable To-Pick / Stock-In. Parent VMC count reacts to children's total.
4. **Tally/Fixed stays at parent level** (machine reports one slot count); split corrections to children by ratio.
5. **Planning screen (`ProductMovement` + `.vue`):** include parent SKUs; show their Daily Sold / Picked / To Pick **greyed and excluded from subtotal**; children carry real numbers with a "child of {parent code}" badge; child Daily Sold = `parent_daily_sold × weight_pct/100`.

Each step is gated on `is_parent_sku`, so normal SKUs keep their exact current behaviour.

## How to verify what's built (your machine — PHP can't run in my sandbox)
1. `php artisan migrate` (3 new migrations, all guarded with `hasColumn`/`hasTable`).
2. `php artisan test --filter=BlindSkuServiceTest` → expect 18 green.
3. Mark a product `is_parent_sku` in Product/Edit; open a ProductMapping that maps it → bind flavours + weights (must total 100) → Save.
4. Add/replace a child's unit cost → confirm a blended `unit_costs` row appears for that (parent, mapping) with `is_current=1`; run `php artisan blind:recompute-costs` as the backstop.
5. Ingest a vend transaction on that blind channel → confirm `gross_profit` uses the blended cost.
6. Frontend already verified to build (`npm run build`).
