# Unattributed sales audit — 2026-09-23

Triggered by machine **2487** (Safti Military Institute Camp Blk 24 — MINDEF):
ops reported the bound product mapping was one they would never use, while sales
looked normal.

**Conclusion: 2487 is a real defect and is being repaired. Everything else is
working as designed.** An earlier revision of this document claimed a
fleet-wide $124k problem caused by deleted `vend_channels` rows. **That was
wrong** — see §5 for the error and the correction.

Read-only investigation via the `sys-happyice` MCP.

---

## 1. The mechanism

`VendTransactionService::processMapping` resolves a sale's product by looking up
the sale's channel code in the machine's **bound mapping**:

```php
$productMappingItem = $this->productMappingItems->get($vendChannel->code);
```

Miss that lookup and the sale is still written — the board supplied the channel
and the price — but with `product_id = NULL`, `unit_cost = 0`, and, because the
GST rate is read off the resolved product's operator, `gst_vat_rate = 0` too:

```
revenue      = amount / (1 + 0/100)  = amount    (GST never extracted)
gross_profit = revenue - 0           = revenue   (booked at 100% margin)
```

So money collected is always right. COGS, GST and product mix are what suffer.

## 2. What happened to 2487 — a real defect

WenBin bound `UEE-UEI_2609_Rocket_Peach_Yog` (#657) on **2026-09-17 19:27**.
That mapping belongs to a different machine family: its planogram sits on
channels 31–38 and 61–66, while 2487's physical slots are 11–19 and 40–49 —
**zero overlap**. LiangBao restored `USD-09A-10L_2608a_Peche_Yog_Org` (#635) on
**2026-09-23 10:08**, and all 19 channels rewrote one second later.

The machine never changed what it sold. Ops were right.

Damage over those six days, all 135 settled sales:

| | sales | revenue | GP booked |
|---|---:|---:|---:|
| No product resolved (2609 has no channels 11–19) | 118 | $326.20 | $326.20 (100% margin) |
| Resolved against 2609's own items — wrong product, wrong COGS | 17 | $41.40 | $15.79 |

It reached reporting. `gp_metrics` for vend 647 carries a `product_id IS NULL`
row at **100% margin on each of 09-17 → 09-22** (28/7/6/4/38/35 sales per day),
so dashboards and GP were overstated for that machine.

## 3. Are there other cases like 2487?

**Essentially none.** Over 2026-08-01 → 09-23, the "machine bound to a mapping
that does not cover its channels" shape accounts for **16 sales, 3 machines,
$16**. In September only **2003** (11 sales, $4.30) and **2321** (1 sale, $0.30).

## 4. Machines currently bound to a mapping that misses channels

19 machines; 2487 is no longer among them.

| coverage | machines | assessment |
|---|---|---|
| 0% | 2506, 2649, 2619, 2735, 2522, 2098, 2534 | all on a mapping named **"NA"**, all with no site — warehouse/unbound stock, not trading |
| 5–10% | 2003 (MSW_2609), 2005, 2007 | 2005/2007 on "test mapping"; **2003 is the only real trading machine worth a look** |
| 60% | 2031 | Brian's bench rig |
| 89–96% | 2769, 2724, 2303, 2150, 2764, 2767, 2683, 2199 | 1–2 uncovered slots each, almost certainly a legitimately empty slot |

## 5. Correction: the "$124k fleet-wide problem" was my error

An earlier revision reported 14,989 sales / 280 machines / $123,938 as
"`vend_channels` row deleted (dangling FK)". **There are no deleted rows and no
dangling FKs.**

The mistake: I classified rows with `LEFT JOIN vend_channels vc ON vc.id =
vt.vend_channel_id` and treated `vc.id IS NULL` as a missing row. But ingest
writes a **sentinel zero**, not a null, when no channel resolves:

```php
'vendChannelID' => $vendChannel ? $vendChannel->id : 0,
```

`vend_channel_id = 0` joins to nothing and passes `IS NOT NULL`, so it read as a
deleted row. All 14,990 are that sentinel.

I also wrongly suspected the 2026-08-26 dedupe. `VendChannelDuplicateResolver`
is careful: it discovers every table with a `vend_channel_id` column from the
live schema and **repoints them to the survivor inside the transaction** before
deleting the losers. It does not orphan anything.

### What those 14,990 rows actually are — by design

September breakdown (5,928 rows):

| shape | rows | share | by design? |
|---|---:|---:|---|
| `is_multiple = 1` — multi-item basket **parent** rows | 5,422 | 91% | **Yes.** A basket has many products, so the parent carries none. The attribution lives on **14,066 child `vend_transaction_items` rows carrying $16,291 of COGS**. CLAUDE.md states this: "a multiple purchase carries it on each item row and the parent row is blank". |
| Error code **99** — rail took the money, TRADE never arrived | 506 | 9% | **Yes.** Documented and deliberate: "Code 99 = Machine transaction not found (NA)… stays in sales and product data, shows a blank Dispense column". With no TRADE there is no channel and no product to resolve. |

Every one is `interface_type = 1` (Card Terminal 3,064 / Omise PayNow 2,035 /
Grab Mart 143 / WeChat 94 / Alipay+ 40 / ShopeePay 21).

**And the parent rows do not leak their inflated GP into reporting.**
`gp_metrics` for 2026-09-22 reports revenue $7,302.65 against GP $4,110.17 — a
**56% margin**, exactly as expected. Had those basket parents been double
counted the roll-up would trend toward 100%. The pre-aggregate reads the child
item rows.

So the 7.6% "unattributed" rate is not a defect. It is the normal shape of
baskets plus a documented payment-without-TRADE case.

## 6. Not caused by the recent product-mapping work

- 2487's wrong mapping was bound **09-17 19:27**.
- The mapping code changes landed **09-21 / 09-22** (chiller mapping ownership,
  machine-type filter, header badge, CityBox label rename).
- The 7.6% baseline is steady across September, before and after those commits.

## 7. The remaining gap worth fixing

Nothing stopped a mapping with **zero** channel overlap from being bound, and
nothing complained for six days. A check at save time — "this mapping covers 0
of this machine's 19 active slots" — would have caught it immediately. It should
warn rather than block: a hard refusal risks making a machine unsavable
(cf. prod 1363/1364, CHILLER_SETTINGS_AUDIT_2026-09-02.md).

The two mapping names differ where it matters least to the eye:

```
USD-09A-10L_2608a_Peche_Yog_Org   ← correct
UEE-UEI_2609_Rocket_Peach_Yog     ← chosen
```

The machine-family code is the prefix; the familiar flavour words trail it.

## 8. Repair

`sales:repair-attribution` (`App\Services\Sales\ProductAttributionRepair`)
re-runs the ingest arithmetic against the planogram the machine really held,
named explicitly by the caller. Dry-run by default; keeps every prior value in
`meta_json.attribution_repair`; marks each touched day dirty so
`reconcile:sales-rollups --dirty` rebuilds `gp_metrics` and the daily facts.

```
php artisan sales:repair-attribution --vend=2487 --mapping=635 \
    --from="2026-09-17 19:27" --to="2026-09-23 10:09" [--apply]
```

Deliberately out of scope: basket parents (`is_multiple`), rows whose channel
row is absent, and any channel the named mapping does not cover — all skipped,
with regression coverage in `tests/Feature/ProductAttributionRepairTest.php`.
