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

/**
 * @param {object} row a refund row carrying `na_in_nets` and `nets_report_state`
 * @returns {{text: string, class: string, tip: string}|null}
 */
export function netsReportBadge(row) {
    if (row?.na_in_nets) {
        return NA_IN_NETS;
    }

    return BADGES[row?.nets_report_state] ?? null;
}
