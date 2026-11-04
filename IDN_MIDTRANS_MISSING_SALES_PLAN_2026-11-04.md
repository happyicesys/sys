# IDN Midtrans missing sales: fix plan (2026-11-04)

Status: **steps 1 to 3 built 2026-11-04 (Brian: never revenue, a person refunds from the list); steps 4 and 5 not started.** Raised by the idn-sys operator: two settled QRIS payments of
Rp 20,000 missing from the dashboard. IDN figures are read-only queries of `idn_sys` on 2026-11-04, Jakarta time.

## What the data shows

Base: 28,604 approved Midtrans QR payments since June 2026.

| Failure | Rows | Rupiah | Today's outcome |
|---|---|---|---|
| Paid, sale stuck PENDING (no machine ACK or TRADE) | 41 since May | 1,366,000 | Out of totals forever, no alert, no refund |
| Paid after the 210 s cutoff | 3 real + 3 Rp 1 tests | 120,000 + 3 | No sale, no dispense, no refund |
| No paid or expiry webhook applied, QR long expired | 2 | 20,000 paid; 28,000 unknown | Looks unpaid forever |
| Midtrans refund recorded on the log, sale not marked refunded | 6 since 2024 | 140,200 | Still counted as sold |

- **Stuck PENDING, by machine:** 10026 has 24, 10002 and 10028 have 5 each, 10050 has 4, and 18003, 10019 and 10027 have
  1 each. Since 1 October, **10002 lost 5 of 57 paid sales (8.8%)**, against 3 of 983 on 10026: look at that machine.
- **Stuck PENDING, dispensed:** 3 of the 41 (Rp 84,000) have `is_dispensed = 1`, so the existing repair rule settles them.
- **Possibly stuck rows:** `26092119131610026` (10026, Rp 28,000) is the second row with no final webhook. Only Midtrans
  can say whether it was paid.

### The two reported orders

- **`26101617132410017` (10017):** no webhook of any kind was applied, not even pending or expiry. `updated_at` equals
  `created_at`, and its QR expired on 18 October. Other machines' webhooks landed at 17:06 and 17:25, and this machine's
  at 17:37. No sign of an outage on our side, so Midtrans's delivery is the first suspect, but it's **unproven**.
  Nginx error logs from that date have rotated away. Midtrans's notification history settles it.
- **`26102218153410019` (10019):** paid at 18:16:04, then no ACK or TRADE. 10019 reported another sale at 18:23, and 15 of
  the 16 payments fleet-wide that hour were fine, so this was not a fleet or MQTT subscriber fault. **The subscriber lead
  is dropped.** The board runs APK 167, older than the TRADE outbox (307), so a lost report is never resent.

### New finding: QR codes live about 33 hours

The board sends `expiry_seconds`, and mark1 passes it to Midtrans as seconds. **26 of the 35 active machines send
`120000`** (4,467 QRs in 30 days, against 2,237 sending `120`). Those QRs stay payable for 33.3 hours, while mark1 refuses
to serve a payment after 210 s. Anyone paying late, including from a photo of the QR, loses their money with no product.
851 of the 1,960 expired QRs in the last 60 days took 33+ hours to expire. It's also why open rows from 3 November are
normal for now.

### Other code findings

- **Midtrans webhooks are forgeable.** The route is public, `WebhookVerifier` skips Midtrans (its comment wrongly says
  Midtrans is not in use), and Order IDs are predictable. A forged `settlement` on an open order dispenses free product.
- **The `nofound_txn` counter can't fire on these machines.** `LogNofoundTxnIfStillMissing` tests whether a sale row
  exists, and under unified transactions the pre-created row always does.
- **A refund on a row still at status 1 is dropped,** because the refund webhook only looks for an APPROVE row.
- **Auto-refund covers Omise only** (`supportsApiRefund`), so Midtrans has no safety net.

## The fix

Every step is scoped to Midtrans or IDN. A push deploys SG, LSH and IDN, so tests must pin that Omise and Fiuu are unchanged.

### 1. Close the holes (small, ship first)

1. **Cap QR life in `Midtrans::createCharge`:** send at most 180 s, so a QR always dies before the 210 s cutoff. `120000`
   then becomes 180. The fix is server-side because these boards are hand-install only.
2. **Check Midtrans signatures:** add a branch to `WebhookVerifier`:
   `SHA512(order_id + status_code + gross_amount + key1 of the log's operator gateway)`. IDN stays in `log` mode (its
   current setting) until real traffic from all 12 operator gateway rows verifies cleanly, then goes to `enforce`.
   Enforcing is **required before step 2 is switched on**. Built with its own switch, `PAYMENT_WEBHOOK_VERIFICATION_MIDTRANS`.
   Checked 2026-11-04 on stored notifications of the last 45 days: 9,981 matched, 0 failed, 7 with no key on file, across
   the 9 gateway rows that had traffic.

### 2. Catch missed confirmations

New scheduled command, `payment:reconcile-midtrans`, every 10 minutes. It reads status-1 Midtrans rows whose
`expiry_time` passed over 5 minutes ago, and asks `GET /v2/{order_id}/status` using the log's own key:

| Midtrans says | We do |
|---|---|
| `settlement` / `capture` | Status 2 with `approved_at` left NULL, stamp `meta`. **Never dispense:** the customer is gone. Goes to the step 3 list |
| `expire` / `cancel` / `deny` | Status 99, same as the webhook |
| `refund` / `partial_refund` | Status 98 through `OmiseRefundRecorder` |
| `pending` / API error / 404 | Leave and log, retry next run |

The status change must be an atomic `UPDATE … WHERE status = 1`, so a late webhook can't apply twice. The first run is
without `--apply`, and only rows from the last 90 days are scanned on schedule (the 133 open rows date back to 2024). Built
behind `MIDTRANS_RECONCILE_ENABLED`, default off. The answer is stamped on `response`, so no migration.

### 3. Make "paid, not served" visible

Add a **Paid, not served** filter and count on the Payment Gateway Transactions page, plus a Needs attention entry on the
Ops Dashboard. A row qualifies when it is Midtrans, status 2, `is_dispensed = 0`, over 30 minutes old, and has no sale or a
PENDING one. That covers all three failures in one list. New text needs `zh_CN` and `id` wording. Dispense stays blank, per
your rule: never "Failed" without a machine report. The dead counter was left alone: a fix would start counting on every
server, including SG's Omise machines, so it needs its own decision.

### 4. Clean up the backlog

- `repair:gateway-trade-gap --from=2026-05-26`, dry run first, then `--apply` and `reconcile:range`: settles the dispensed rows.
- A one-off command for the 6 refunded rows, through the same recorder, so they stop counting as sold. Then `reconcile:range`.
- The other rows wait on decision 1 below.

### 5. Midtrans refund API (later, only if you choose auto-refund)

`RefundMidtransJob` via `POST /v2/{order_id}/refund/online/direct`. **Widening `supportsApiRefund()` also switches on the
failed-vend demotion, `HandleFailedVendTransaction` and the 10-minute refund sweep**, so all of them need a Midtrans branch
in that one change. Midtrans's docs disagree on which methods qualify, and say POS QRIS refunds are off by default, so
confirm with Midtrans first. Docs give a 45-day window for GoPay QRIS, and 365 days for ShopeePay QRIS with no refunds from
23:55 to 06:00 WIB.

## Your decisions

1. **Paid but unconfirmed sales.** Recommended: never count them as revenue. Show them on the list, and let a person refund
   them in the Midtrans dashboard, because `is_dispensed = 0` doesn't prove the customer got nothing. Auto-refund can follow
   once the list has shown how accurate that signal is.
2. **Backlog refunds.** The money sits in the operators' Midtrans accounts, so the operator should decide. 9 stuck rows
   (Rp 307,000) fall inside GoPay's 45-day window (paid since 20 September).
3. **Upgrade the boards?** 10002, 10017, 10019 and 10026 run APK 161–194. 323 resends lost reports and would cut new stuck
   sales most, but it's hand-install (see `idn-apk-upgrade-2026-10-31` in memory). Not part of this code change.

## Asks for the operator

- Midtrans's notification history for `26101617132410017`, and whether `26092119131610026` was paid.
- Whether the customers of the two reported orders received their products, and whether "05:37" and 5:13 PM are the same payment.
- Have someone check machine 10002.

## Tests

- **Cap:** `120000` and `120` both send at most 180, and Omise and Fiuu charges are unchanged.
- **Signature:** valid, wrong and missing cases in `log` and `enforce` modes, with two operators' keys.
- **Sweep:** each row of the table above, webhook racing the sweep, no MQTT publish ever, API failure leaves the row alone.
- **List:** each failure appears, and leaves once a TRADE or refund arrives.
- Full suite, Pint and build before the push.

## Sources

- [Midtrans webhooks: signature, retries, ordering](https://docs.midtrans.com/docs/https-notification-webhooks)
- [Direct Refund Transaction](https://docs.midtrans.com/reference/direct-refund-transaction)
- [What payment method that have refund feature?](https://docs.midtrans.com/docs/what-payment-method-that-have-refund-feature)
- [GoPay QRIS POS Integration](https://docs.midtrans.com/docs/gopay-qris-pos-integration)
