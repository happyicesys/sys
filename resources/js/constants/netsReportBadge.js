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

import { t } from '@/i18n';

const GREY = 'bg-gray-100 text-gray-600';

// text / tip are getters: this module is imported before the user's language is loaded, so the
// wording is looked up when a badge is read, not when the module is evaluated.
const BADGES = {
    captured: {
        get text() { return t('Matched in NETS'); },
        class: 'bg-teal-100 text-teal-800',
        get tip() { return t('The synced NETS report has a line for this sale and no reversal — the customer WAS charged and has not been refunded, so a valid claim still needs paying.'); },
    },
    not_captured: {
        get text() { return t('No line in NETS'); },
        class: GREY,
        get tip() { return t('Both NETS files that could carry this sale are synced and neither has a line for it. The vend is not a failed single item, so no refund is claimed from that: the goods went out (or it was a multiple) and this is a shortfall to investigate, not money to return.'); },
    },
    uncovered: {
        get text() { return t('NETS covers this terminal partly'); },
        class: GREY,
        get tip() { return t('This terminal\'s supplier settles only part of its sales through the NETS file (measured 40–60% coverage), so a missing line proves nothing either way. Check the charge before paying or refusing this claim.'); },
    },
    unbound: {
        get text() { return t('No NETS terminal'); },
        class: GREY,
        get tip() { return t('No card terminal was bound to this machine on the day of the sale, so no NETS line can be matched to it. Bind the terminal on the machine\'s Setting/Edit page to fix future days.'); },
    },
    pending: {
        get text() { return t('NETS report pending'); },
        class: GREY,
        get tip() { return t('The NETS files for this sale\'s day and the next are not both synced yet, so the report has not ruled on it. Sync them in Card Settlement and this becomes Matched or NA in NETS.'); },
    },
};

const NA_IN_NETS = {
    get text() { return t('NA in NETS'); },
    class: 'bg-amber-100 text-amber-800',
    get tip() { return t('Both NETS files that could carry this failed vend are synced and neither has a line for it — the charge was voided before batch upload, so it is already counted as refunded. Do not pay it again.'); },
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
        text: t('Retained credit used by same card (…:card)', { card: r.retry_card }),
        class: 'bg-red-100 text-red-800',
        tip: t('This vend failed but the card was charged, and the reader kept the credit. The same card (…:card) then bought a :amount item on this machine at :at, served from that credit. The customer got the item — do not refund it.', { card: r.retry_card, amount: dollars(r.retry_amount), at: at(r) }),
    }),
    different_card: (r) => ({
        text: t('Retained credit used by another card (…:card)', { card: r.retry_card }),
        class: 'bg-sky-100 text-sky-800',
        tip: t('This vend failed and card …:failed was charged. The reader kept the credit, and a DIFFERENT card (…:card) used it at :at for a :amount item, paying only the difference. The claimant did not get the item — the claim stands.', { failed: r.failed_card, card: r.retry_card, at: at(r), amount: dollars(r.retry_amount) }),
    }),
    unknown: (r) => ({
        text: t('Credit used by a later sale'),
        class: 'bg-amber-100 text-amber-800',
        tip: r.failed_card
            ? t('This vend failed but the card was charged, and the reader kept the credit. A :amount sale on this machine at :at was served from it, but NETS shows no card number for that sale (EFTPOS, or a re-vend with no line of its own), so whether it was this customer is unknown. Check before paying or rejecting.', { amount: dollars(r.retry_amount), at: at(r) })
            : t('This vend failed but the card was charged, and the reader kept the credit. A :amount sale on this machine at :at was served from it, but NETS shows no card number for one of the two sales (EFTPOS, or a re-vend with no line of its own), so whether it was this customer is unknown. Check before paying or rejecting.', { amount: dollars(r.retry_amount), at: at(r) }),
    }),
};

// The claim is on the RETRY: this sale was served from a failed sale's
// retained credit (RefundController.retained_credit_source). Whoever made it
// got the item, paying only their own NETS line (or nothing on a re-vend) —
// the second customer on a different card, or the first one retrying.
const paidWithCredit = (r) => {
    const failed = { amount: dollars(r.failed_amount), at: (r.failed_at ?? '').slice(0, 16), card: r.failed_card };
    const served = r.failed_card
        ? t('This sale was served from credit a failed :amount vend left on the reader at :at (card …:card).', failed)
        : t('This sale was served from credit a failed :amount vend left on the reader at :at.', failed);
    const charge = r.paid_cents == null
        ? t('NETS has no charge of its own for it')
        : (r.retry_card
            ? t('Its own NETS charge was :amount on card …:card', { amount: dollars(r.paid_cents), card: r.retry_card })
            : t('Its own NETS charge was :amount', { amount: dollars(r.paid_cents) }));
    const verdict = r.verdict === 'different_card'
        ? t(':charge — a different card: a new customer who paid less and got the item.', { charge })
        : r.verdict === 'same_card'
            ? t(':charge — the same card: the first customer\'s retry, which got the item.', { charge })
            : t(':charge.', { charge });

    return {
        text: t('Paid with retained credit'),
        class: 'bg-red-100 text-red-800',
        tip: served + ' ' + verdict + ' ' + t('The item was received — do not refund it.'),
    };
};

/**
 * One line for the ticket page's Actions panel, beside Approve: what the
 * retained-credit verdict means for THIS decision. null when there is none.
 *
 * @param {object} row
 * @returns {{text: string, class: string}|null}
 */
export function retainedCreditAdvice(row) {
    const r = row?.retained_credit_retry;
    if (row?.retained_credit_source) {
        return { text: t('This sale was paid with retained credit from an earlier failed vend — the customer got the item. Do not refund.'), class: 'text-red-700' };
    }
    if (r?.verdict === 'same_card') {
        return { text: t('Retained credit used by same card (…:card) at :at — the customer got the item on the retry. Do not refund.', { card: r.retry_card, at: at(r) }), class: 'text-red-700' };
    }
    if (r?.verdict === 'different_card') {
        return { text: t('Retained credit used by another card (…:card) — this customer did not get the item. The claim stands.', { card: r.retry_card }), class: 'text-sky-700' };
    }
    if (r?.verdict === 'unknown') {
        return { text: t('Retained credit used by a later sale at :at, card not in NETS — check who took it before deciding.', { at: at(r) }), class: 'text-amber-700' };
    }

    return null;
}

/**
 * @param {object} row a refund row carrying `retained_credit_retry`, `retained_credit_source`, `na_in_nets` and `nets_report_state`
 * @returns {{text: string, class: string, tip: string}|null}
 */
export function netsReportBadge(row) {
    const retry = row?.retained_credit_retry;
    if (retry && RETRY_BADGES[retry.verdict]) {
        return RETRY_BADGES[retry.verdict](retry);
    }
    if (row?.retained_credit_source) {
        return paidWithCredit(row.retained_credit_source);
    }
    if (row?.na_in_nets) {
        return NA_IN_NETS;
    }

    return BADGES[row?.nets_report_state] ?? null;
}
