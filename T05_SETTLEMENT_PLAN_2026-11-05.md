# T05 (Payrallel) settlement: what we know and the proposed flow (2026-11-05)

Brian, 2026-11-05: AI Recognition now has room for a Settlement column, as on Sales Transactions. Get settlement
information from Payrallel by API and make sure the money reaches our bank. Study the flow first.

**Status: study only. Nothing here is built, and nothing has been sent to Payrallel.**

Sources: `card_payment_intents`, `card_payment_events` (Payrallel's raw answers since 2026-10-02), one read-only
`transactions/actions/query` of every captured charge from prod on 2026-11-05, and payrallel.io/docs.

## 1. Short answer

**Today mark1 cannot show that any T05 money reached the bank.** Payrallel's API tells us a charge was approved, and
nothing after that. A charge captured a month ago reads exactly like one captured yesterday (`status: approved`,
`tranStatus: APPROVED`). Their public docs have no settlement, payout or fee API. Their answers carry no settlement,
payout, fee or MDR field (0 of 230,507 logged answers).

The API gives us one useful hook: each charge carries a `batchNo` and `batchId`. Whether a settlement or payout can be
tied to that batch is a question for Payrallel (§6).

## 2. The flow, stage by stage

| Stage | What happens | Does mark1 know today? |
|---|---|---|
| Hold | Customer taps, `AUTH` | Yes |
| Charged | mark1 captures, `AUTH_COMP`, "C…" transaction | Yes, `captured_cents` |
| Batch | Charge joins a batch | In the raw answer only, not stored in a column |
| Settled | Acquirer pays out the batch | **No** |
| MDR / net | Fee taken, net amount | **No** |
| Bank in | Money lands in our account | **No.** mark1 has no bank statement feature for any rail |

Sales Transactions has no T05 data to show: Settle Sync stays a grey cross (no settlement report) and Settlement Date
stays blank. That is correct today. Txn, Revenue & Settlement counts T05 as "No rate on file".

The gateway in every answer is `2c2p-minesec`. We do not know who pays us: 2C2P directly, or T05.

## 3. What the check of all 29 charges found

**Whose merchant account took the money:**

| Period | Merchant in Payrallel's answer | Sales channel | Charges | Total |
|---|---|---|---|---|
| 2026-10-05 → 10-08 | T05 Technologies Pte Ltd (MID 000240724000056) | channel `69e5f992…` | 10 | $3.38 |
| 2026-10-16 → 11-02 08:04 | T05 Technologies Pte Ltd (MID 000240724000056) | channel `6acf07f1…` | 2 | $3.11 |
| 2026-11-02 16:59 → now | **Happy Ice Pte Ltd** (MID 000250325000103) | channel `6ae81b98…` | 17 | **$76.60** |

So **$6.49 was charged on T05's own merchant account**, and only money from 2026-11-02 16:59 can be expected in our
bank. mark1 counts all $83.09 as charged.

**What we should see in the bank, before MDR:**

| Batch | Date | Charged | Refunded | Expected before MDR |
|---|---|---|---|---|
| 000912 | 11-02 | $1.50 | | $1.50 |
| 000914 | 11-03 | $38.80 | $0.10 | $38.70 |
| 000916 | 11-04 | $36.30 | $3.90 | $32.40 |
| | | **$76.60** | **$4.00** | **$72.60** |

Batches appear to change daily, but that is only what three days show. Refunds carrying a batch of their own is also
seen, not confirmed: the 11-04 refunds sit in 000916, while the charges they undo sit in 000914.

**Differences between mark1 and Payrallel:**

- **A 10c refund mark1 does not know about.** `R26110305410507F`, 2026-11-03 13:41, on intent 80's $1.80 charge. It was
  made before mark1's refund button went live (21:4x that day), so it came from outside mark1. mark1 shows
  `refunded_cents` 0 on that charge.
- **Two charges we can no longer read** (intents 64, 68, $3.11): the query answers 403. Both are on the T05 channel
  `6acf07f1…`, and the token stored with them no longer has access.
- The other 27 charges match mark1 to the cent.

**Six charges went above their hold** (the AI's or a person's count). Each capture was approved, but no bank has shown
us yet that the higher amount actually settles. The 2026-11-02 test ($3.00 on a $1.50 hold) still owes that proof.

## 4. Card numbers are being stored (separate, urgent)

Payrallel's capture and refund answers include EMV tag `57` inside `extraData`: the **full card number and expiry,
in clear text**. mark1 logs raw answers, so this is now in:

- `card_payment_events.detail`: 31 rows (all 29 captures, 2 refunds);
- `storage/logs/payrallel-*.log` on the server: at least 3 daily files matched.

`card_payment_intents.last_response` has none. Storing this breaks PCI DSS rules for card data. To fix:

1. Strip tag `57` (and tags `5A`, `9F6B`) in `CardTerminalEventLog` before anything is written or logged.
2. Clean the 31 rows and the log files, on every server that has them.
3. Tell Payrallel to stop returning it.

While this study read those rows, card numbers also came back in the query output of this Claude session.

## 5. Proposed flow

**Rule: a green tick means evidence from the stage it names.** "Charged" is never shown as settled.

### Phase 1: without Payrallel

1. Stop storing card numbers (§4).
2. Keep what we are already given, on the charge: batch no / id, RRN, approval code, merchant name + MID, card last 4.
   Fill older rows by one query run.
3. **Daily check against Payrallel** (a new command): query each charge from the last N days, then flag an amount that
   differs, a refund mark1 does not have (the 10c case), a merchant that is not ours, or an unreadable charge. Flags go
   in the 08:30 health email.
4. **Settlement column on AI Recognition**, showing the stage reached: "Charged · batch 000916", grey, or "T05 merchant,
   not ours" in red. Same data on Sales Transactions.

### Phase 2: once Payrallel answers §6

Payrallel's data becomes a payout record per batch: date, gross, fee, net, payout reference.

- By **API**: a scheduled job reads it.
- By **file**: reuse Card Settlement upload (`card_settlement_reports` / `card_settlement_rows`) with a Payrallel parser.
  Rows must stay apart from NETS: `CardSettlementRefundReconciler` and the matcher count only `nets` units today, and
  that must remain so.

Then `settlement_payout_date`, Settle Sync and the MDR / net figures on Txn, Revenue & Settlement fill in through
`payout_terms['payrallel']`, and stop reading "No rate on file".

### Phase 3: bank proof

This is the step that really proves "the money came". A payout record is still only Payrallel's word.

- For each payout: expected amount and date, shown on one page, with a person (or a statement upload) marking **Bank in
  confirmed**, who and when.
- An alert when a payout is not confirmed one banking day past its expected date, and when a batch has no payout at all.
- Recommendation: start with a person ticking it against the bank statement. T05 is a few payouts a week. Add a statement
  upload only when the volume justifies it, and consider using it for NETS too, which has the same gap.

## 6. Questions for Payrallel (draft, not sent)

1. Who pays Happy Ice, 2C2P or T05? Which bank account is on merchant `6ae8175b…` (MID 000250325000103), and what
   reference will the bank statement show?
2. Settlement cycle (T+?), daily cut-off, and when a batch closes. Is `batchNo` the settlement unit?
3. MDR per scheme, GST, any per-transaction minimum or other fee. Is it deducted from the payout or billed separately?
4. Is there an API for settlement, per transaction or per batch? We need: status, date, gross, fee, net, payout id, bank
   reference. If not, is there a daily report from the portal, by email or SFTP? A sample file, please.
5. Can you send a webhook for settlement, and for refunds made outside our API?
6. How is a refund netted against a payout, and what happens when a batch nets below zero? How are chargebacks notified
   and taken?
7. A capture above its hold (e.g. $3.00 on a $1.50 hold, transaction `C261102000404C1D`): did it settle at the full amount?
8. The $6.49 charged on T05's own merchant between 2026-10-05 and 11-02: stays with T05, or paid across? Those two
   sales channels are also why our token now gets 403 on two charges.
9. Who made refund `R26110305410507F` (10c, 2026-11-03 13:41)?
10. Your capture and refund answers contain EMV tag `57` (card number and expiry) in clear text. Please remove it.

## 7. What Brian can check now, without any code

- **The bank statement:** look for about $1.50, $38.70 and $32.40 less MDR since 2026-11-03. This is the first real answer.
- **Payrallel's Merchant Administrator Portal:** does it have a settlement or payout report? If so, that is the sample
  for question 4.
