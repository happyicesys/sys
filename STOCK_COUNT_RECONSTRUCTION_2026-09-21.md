# Stock count 2026-09-21: lost, fixed, reconstructed (2026-10-13)

## What happened

At 23:59 on 2026-09-21, `save:today-stock-count` queued `SaveStockCount` for all 407
machines. Every job wrote its `stock_counts` header and then died calling CMS
(`/api/items/available-pcs`: cURL 28, connect timeout after 10 s; `failed_jobs`
23:59–00:01, 164 + 199 + 44 = 407). So 09-21 had 407 headers and 0
`stock_count_items`, and the Stock Count Dashboard dropped to 0 that day. It was the
same night as the origin network loss of 2026-09-22. No other day since July is
affected.

## Fix (deployed 2026-10-13, `2e7c8c9937`)

`App\Services\Stock\StockCountWarehouseQty`: the command fetches the fleet-wide CMS
list once (3 tries) into the cache. Jobs read it, a job with no cached list tries CMS
once, and if CMS is still down the machine qty is saved with `qty_warehouse` NULL.
Regression coverage: `tests/Feature/SaveStockCountCmsOutageTest.php`.

## Reconstruction (applied 2026-10-13)

`php artisan stock-count:reconstruct-day 2026-09-21 --apply` wrote **6,090 estimated
items** under the 407 empty headers: 85,797 machine units (neighbours 84,940 on 09-20
and 86,957 on 09-22), stock value $2,809,534. `qty_warehouse` is NULL on all of them,
because CMS keeps only a running `qty_now` with no history, so the 09-21 warehouse bar
stays empty. Rules: `App\Services\Stock\StockCountReconstructor`.

| rule | rows | units |
|---|---|---|
| no visit on 09-21: qty(09-20) − sold(09-21) | 5,401 | 73,897 |
| refilled on 09-21: qty(09-22) + sold(09-22) | 673 | 11,876 |
| fallback | 16 | 24 |

Backtest on prod against real snapshots (`--backtest`):

| day | rows exact | within 1 unit | fleet total |
|---|---|---|---|
| 2026-09-17 | 98.4% | 99.7% | +0.27% |
| 2026-09-23 | 97.9% | 99.0% | −0.18% |
| 2026-10-06 | 98.8% | 99.8% | −0.23% |

**These rows are estimates.** Nothing in the table marks them; they are the
`stock_count_items` under 09-21's headers (ids 161588–161994), all created 2026-10-13
with `qty_warehouse` NULL. Treat that day as ±0.3% at fleet level, and less reliable per
machine × product than a real snapshot.
