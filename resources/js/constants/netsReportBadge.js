// What the NETS settlement report says about ONE card sale, as a badge.
//
// The reconciler persists its verdict on vend_transactions.card_settlement_state
// and RefundController::toRow() passes it through as `nets_report_state`
// ('pending' when the sale is a card sale whose day is not final yet, null when
// the sale is not a card-terminal sale at all).
//
// Every state gets a badge on purpose. Before 2026-09-09 only two did, so a
// claim the report could not rule on looked exactly like one nobody had
// checked — Brian, on an `uncovered` row: "single purchase, error 7, and no
// badge?". Shared by Refund/Index.vue and Refund/Show.vue so the list and the
// ticket page can never disagree.
//
// `reversed` deliberately returns nothing: the auto-refund source badge beside
// it already reads "NETS reversal", and two badges for one fact is noise.

const GREY = 'bg-gray-100 text-gray-600';

const BADGES = {
    captured: {
        text: 'Matched in NETS',
        class: 'bg-teal-100 text-teal-800',
        tip: 'The synced NETS report has a line for this sale and no reversal — the customer WAS charged and has not been refunded, so a valid claim still needs paying.',
    },
    not_captured: {
        text: 'No line in NETS',
        class: GREY,
        tip: 'Both NETS files that could carry this sale are synced and neither has a line for it. The vend is not a failed single item, so no refund is claimed from that: the goods went out (or it was a multiple) and this is a shortfall to investigate, not money to return.',
    },
    uncovered: {
        text: 'NETS covers this terminal partly',
        class: GREY,
        tip: 'This terminal\'s supplier settles only part of its sales through the NETS file (measured 40–60% coverage), so a missing line proves nothing either way. Check the charge before paying or refusing this claim.',
    },
    unbound: {
        text: 'No NETS terminal',
        class: GREY,
        tip: 'No card terminal was bound to this machine on the day of the sale, so no NETS line can be matched to it. Bind the terminal on the machine\'s Setting/Edit page to fix future days.',
    },
    pending: {
        text: 'NETS report pending',
        class: GREY,
        tip: 'The NETS files for this sale\'s day and the next are not both synced yet, so the report has not ruled on it. Sync them in Card Settlement and this becomes Matched or NA in NETS.',
    },
};

const NA_IN_NETS = {
    text: 'NA in NETS',
    class: 'bg-amber-100 text-amber-800',
    tip: 'Both NETS files that could carry this failed vend are synced and neither has a line for it — the charge was voided before batch upload, so it is already counted as refunded. Do not pay it again.',
};

// A later sale on the machine was served from this failed sale's retained
// credit. Whether the CLAIMANT got the item depends on the card behind each
// NETS line (RefundController.retained_credit_retry, from RetainedCreditRetry):
// on 5073, 2026-09-21, the failed $1.70 was card …9265 and the $0.70 top-up
// that took the item was card …2599 — someone else got it.
const at = (r) => (r.retry_at ?? '').slice(0, 16);
const dollars = (cents) => `$${(Number(cents ?? 0) / 100).toFixed(2)}`;

const RETRY_BADGES = {
    same_card: (r) => ({
        text: `Item received on retry (card …${r.retry_card})`,
        class: 'bg-red-100 text-red-800',
        tip: `This vend failed but the card was charged, and the reader kept the credit. The same card (…${r.retry_card}) then bought a ${dollars(r.retry_amount)} item on this machine at ${at(r)}, served from that credit. The customer got the item — do not refund it.`,
    }),
    different_card: (r) => ({
        text: `Credit used by another card (…${r.retry_card})`,
        class: 'bg-sky-100 text-sky-800',
        tip: `This vend failed and card …${r.failed_card} was charged. The reader kept the credit, and a DIFFERENT card (…${r.retry_card}) used it at ${at(r)} for a ${dollars(r.retry_amount)} item, paying only the difference. The claimant did not get the item — the claim stands.`,
    }),
    unknown: (r) => ({
        text: 'Credit used by a later sale',
        class: 'bg-amber-100 text-amber-800',
        tip: `This vend failed but the card was charged, and the reader kept the credit. A ${dollars(r.retry_amount)} sale on this machine at ${at(r)} was served from it, but NETS shows no card number for ${r.failed_card ? 'that sale' : 'one of the two sales'} (EFTPOS, or a re-vend with no line of its own), so whether it was this customer is unknown. Check before paying or rejecting.`,
    }),
};

/**
 * @param {object} row a refund row carrying `retained_credit_retry`, `na_in_nets` and `nets_report_state`
 * @returns {{text: string, class: string, tip: string}|null}
 */
export function netsReportBadge(row) {
    const retry = row?.retained_credit_retry;
    if (retry && RETRY_BADGES[retry.verdict]) {
        return RETRY_BADGES[retry.verdict](retry);
    }
    if (row?.na_in_nets) {
        return NA_IN_NETS;
    }

    return BADGES[row?.nets_report_state] ?? null;
}
