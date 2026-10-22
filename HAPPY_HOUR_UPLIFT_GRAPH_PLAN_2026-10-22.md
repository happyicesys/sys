# Happy Hour graph: show what HH pulls along — plan (2026-10-22)

**Status: BUILT 2026-10-22** (`HappyHourSales::dailyTotals` + `dailyBasketTotals`, `HappyHourCampaignStats` (cached 5 min), `CampaignStatsCharts.vue`; test `test_related_total_sales_count_each_transaction_holding_a_happy_hour_unit_whole`). The "Build" section below is what shipped. Source: Daniel's annotated screenshot of "Happy Hour results · Default 30%" and
his 84 s screen recording explaining it (Dropbox `ScreenRecorderProject100.mp4`, sent by Brian 2026-10-22;
the video is the same screenshot with his cursor, the content is in the voice-over, transcribed below).

## The goal, in Daniel's words

HH 只是个桥，目的是要客人多买 / 常买. Happy Hour is the bridge, not the goal: a customer comes for the
discounted SKU and should buy more on the same visit (多买), and come back more often (常买). Today's graph
only counts the HH units themselves, so it shows neither.

## What he said (whisper.cpp small, Mandarin; lightly cleaned)

> 0:00 现在我们有 Happy Hour 的 graph，只是 show Happy Hour 的。
> 0:06 其实我们的目标不只是要卖 Happy Hour 的 quantity，我们是希望带动其他的 sales，就是 single transaction。
> 0:17 We call it **Related Total Sales**：只要它是有 related 的，如果说 5 支里面有一支是 Happy Hour 的也算。
> 0:31 那我要看一下它 Happy Hour 到底带动了多少的 transaction。
> 0:40 所以我们加强这个 graph 里面，除了 show 现有的、就是 Happy Hour 的，把 total 的也加进去，
> 0:51 再加一条 line graph，就是 non-Happy Hour 的，意思是说它所带动的。
> 0:59 所以这四个 graph 里面有三个是会有 total。
> 1:04 那这个 sales 其实 total 就是从这一边 [the Sales Transactions cards],
> 1:11 然后 quantity 其实也是相当 follow 了，
> 1:16 过后 total per machine，那个就要 back-end to calculate。

So:
- **Related Total Sales** = the WHOLE transaction whenever it holds at least one HH unit (1 of 5 is enough).
  Per transaction, not per customer or per visit.
- He wants to see how many transactions HH drove.
- Three of the four charts get a total: **sales**, **quantity**, **total per machine**. Discount is unchanged.
- The sales and qty totals are the figures the Sales Transactions page shows when filtered to the campaign
  (Total Sales S$, Total Qty); the per-machine total has to be calculated in the backend.
- The mockup's "10.2" per machine is a placeholder, not a figure to match.

## The charts

| Chart | Today | After |
|---|---|---|
| Happy Hour sales | HH lines' $ per day | **Stacked column**: HH $ (blue) + rest of the related transactions (light blue) = Related Total Sales; **line** on a right 0–100% axis: non-HH ÷ total. Total on top of the column |
| Discount given | $ per day | unchanged |
| Units sold | HH units per day | **Stacked column**: HH units + other units in the related transactions = related total qty; **line**: non-HH ÷ total |
| Avg units per machine per day | HH units ÷ machine-days | **Stacked column**: HH units per machine + other related units per machine = related total qty per machine; **line**: non-HH ÷ total |

Tooltip per day: HH / other / total / non-HH % / related transactions (of which multiple purchases) /
machines that ran HH. The range summary above the charts gains "Related Total Sales $X over N transactions,
non-HH Y%".

## Definitions (one rule each)

- **Related transaction** = a settled sale carrying at least one HH unit: `vend_transactions.happy_hour_slot_id`
  is set on the header (a single sale is its header; a basket's header is tagged "includes a promo unit").
  Exactly the set Sales Transactions lists under Campaign Label = that campaign, so the pages tally.
- **HH $ / HH units** = today's figures (`HappyHourSales::dailyTotals`, tagged lines only).
- **Related Total Sales** = the header `amount` of related transactions (what the customer paid), not refunded.
- **Related total qty** = dispensed units of those transactions (single: 1 if dispensed; basket: items with a
  sale code). The Transactions card shows Dispensed and Purchased; we use Dispensed.
- **Per machine** = related total qty ÷ machine-days (a machine counts on each day it ran a slot — the rule
  the chart already uses).
- **Non-HH %** = (total − HH) ÷ total. High = HH pulled other SKUs into the transaction. A single HH sale
  adds 0% non-HH, so the line also shows how often HH is bought alone.

## Checked against prod (2026-10-21, all operators)

| | related transactions | of which multiple | Related Total Sales | related qty |
|---|---|---|---|---|
| prod query | 192 | 65 | $868.70 | 316 |
| Daniel's Transactions card | 184 | 62 | $830.30 | 300 dispensed / 307 purchased |

Same shape. The gap is the card's operator filter (HIPL group + XO/MSW/CB picked on screen) and dispensed vs
purchased. The graph follows the viewer's operator boundary, as the campaign figures already do.

On his 21 Oct figures: HH $521 of $830 → **37% non-HH**; HH 211 of 300 units → **30%**. Per machine on prod:
316 related units ÷ 221 machines = **1.43**, of which HH 0.95.

## Build (about half a day)

1. (Shipped as `dailyTotals` single rows + `HappyHourSales::dailyBasketTotals`.) Per (campaign, day), related transactions,
   multiple count, Related Total Sales, related dispensed qty. Two grouped queries over
   `(vend_id, transaction_datetime)`, same settled / not-refunded rules as `dailyTotals`.
2. `HappyHourCampaignStats::daily` / `window` add `related_revenue`, `related_units`, `related_transactions`,
   `related_multiple`, `related_units_per_machine`, `non_hh_revenue_pct`, `non_hh_units_pct`. The totals row
   gets the same.
3. `CampaignStatsCharts.vue`: stacked bars on three charts, the % line on a right 0–100% axis, total on top
   of each column (the label added 2026-10-18), tooltip as above.
4. The Campaign column's L24h / L30d block gains "Related sales" and "Non-HH %".
5. Tests in `HappyHourCampaignTest`: a single HH sale = 0% non-HH; a 5-unit basket with one HH unit counts
   whole; a refunded transaction left out; a failed line not counted as a unit; per machine divides by
   machine-days.

## Not asked by Daniel — our suggestions, for later

1. **常买 (coming back more often)** cannot be read from these charts. It needs a "before": each HH machine's
   daily sales vs its own 28 days before it joined HH, or vs machines not in HH (a lift chart, a separate
   piece of work). Repeat customers can only be counted on card sales (NETS card last 4), partially.
2. **Discount chart**: could add "discount ÷ Related Total Sales" (what each $ of discount brought in).
