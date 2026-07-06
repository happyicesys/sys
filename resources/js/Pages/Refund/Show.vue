<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    ticket: { type: Object, required: true },
    statuses: { type: Object, default: () => ({}) },
    prevTicket: { type: Object, default: null },
    nextTicket: { type: Object, default: null },
    emailTemplates: { type: Object, default: () => ({}) },
    emailTemplateContents: { type: Object, default: () => ({}) },
});

const page = usePage();
const can = (p) => (page.props.auth?.roles || []).includes('superadmin') || (page.props.auth?.permissions || []).includes(p);

const t = computed(() => props.ticket);
// All badges read from the FROZEN validation snapshot taken at submission
// (system_validation_json / stored ticket fields), so they never change later.
const sv = computed(() => props.ticket.system_validation || {});
const hasChannelError = computed(() => (typeof sv.value.had_channel_error !== 'undefined'
    ? !!sv.value.had_channel_error
    : (props.ticket.items || []).some((i) => i.had_channel_error)));
const isManualClaim = computed(() => (typeof sv.value.is_manual !== 'undefined' ? !!sv.value.is_manual : !!props.ticket.is_manual));
// The double-refund badge must reflect the CURRENT refunded state, not just the
// frozen snapshot: a charge can be refunded after submission (e.g. the system
// auto-refunds an Omise non-dispense), and the badge has to stay in sync with the
// live "Refunded" flag shown in Related Transactions. live_txn_refunded is read
// fresh from the linked transaction/gateway log by the controller.
const alreadyRefunded = computed(() => !!(props.ticket.live_txn_refunded || sv.value.txn_already_refunded || props.ticket.auto_refund_detected));
const isVideo = (a) => a && a.mime && a.mime.startsWith('video/');

// ---- Photo / video carousel (one large item at a time; shared index for
// the inline viewer and the enlarge lightbox) ----
const mediaIndex = ref(0);
const lightboxOpen = ref(false);
function openLightbox() { lightboxOpen.value = true; }
function closeLightbox() { lightboxOpen.value = false; }
function prevMedia() {
    const n = attachments.value.length;
    if (n) mediaIndex.value = (mediaIndex.value - 1 + n) % n;
}
function nextMedia() {
    const n = attachments.value.length;
    if (n) mediaIndex.value = (mediaIndex.value + 1) % n;
}
const busy = ref(false);

// Machine ID → Operations Dashboard, deep-linked+auto-searched to that machine
// via the ?codes= param (opens in a new tab so the ticket stays open).
const opsDashboardUrl = (code) => code ? ('/vends/customers?codes=' + encodeURIComponent(code)) : null;

// ---- Sent-email preview (opened from an audit-trail email line) ----
const emailPreview = ref(null); // the log entry's meta, or null when closed
function openEmail(meta) { emailPreview.value = meta; }
function closeEmail() { emailPreview.value = null; }

// ---- Email-template preview (opened from an action button; shows the template
// that WOULD be sent, tokens like {name}/{reference} shown as placeholders) ----
const templatePreview = ref(null);
function openTemplate(key) {
    const tpl = props.emailTemplateContents?.[key];
    if (tpl) templatePreview.value = { label: props.emailTemplates?.[key] || key, subject: tpl.subject, body: tpl.body };
}
function closeTemplate() { templatePreview.value = null; }

function post(url, data = {}) {
    busy.value = true;
    router.post(url, data, { preserveScroll: true, onFinish: () => (busy.value = false) });
}
const base = computed(() => '/refunds/' + t.value.id);

// "No charge / auto-refund" — email the customer it was already handled and close
// the ticket. Never triggers a new refund.
function resolveNoCharge() {
    if (!confirm('Email the customer that the charge was auto-refunded / no charge was captured, and close this ticket?\n\nNo payout will be made.')) return;
    post(base.value + '/resolve-no-charge');
}
// "Drop / double submission" — close the ticket (kept, struck through in the
// list). No email is sent.
const showDrop = ref(false);
const dropRemarks = ref('');
function doDrop() {
    post(base.value + '/drop', { remarks: dropRemarks.value });
    showDrop.value = false;
}
function toggleItem(item) {
    post(base.value + '/items/' + item.id, { approved: !item.approved });
}
function deleteTicket() {
    router.delete(base.value, {
        onBefore: () => confirm('Permanently delete this refund ticket? This cannot be undone.'),
    });
}

const recClass = (r) => ({ proceed: 'text-green-700 bg-green-50 border-green-200', review: 'text-amber-700 bg-amber-50 border-amber-200', reject: 'text-red-700 bg-red-50 border-red-200' }[r] || 'text-gray-600 bg-gray-50 border-gray-200');
// System-validation badges are a green (good/safe) vs red (danger/check) binary.
const badgeGood = 'bg-green-50 border-green-200 text-green-700';
const badgeBad = 'bg-red-50 border-red-200 text-red-700';
const statusClass = (s) => ({ submitted: 'bg-yellow-100 text-yellow-800', auto_resolved: 'bg-purple-100 text-purple-800', rejected: 'bg-red-100 text-red-800', approved: 'bg-green-100 text-green-800', completed: 'text-gray-500' }[s] || 'bg-gray-100 text-gray-700');

const isPaynow = computed(() => t.value.refund_method === 'paynow');
const s = computed(() => t.value.status);

// An auto_resolved ticket is terminal ONCE the customer has been emailed the
// auto-refund notice. The Omise job can flip a ticket to auto_resolved WITHOUT
// emailing (markAutoRefundedByCharge no longer sends), in which case Ops still
// needs the "No charge / auto-refund — email customer" button to send that
// notice. So the reject/drop actions disappear only after that email has gone
// out (either logged on the trail or recorded as the last email template).
const autoRefundEmailSent = computed(() =>
    (t.value.logs || []).some(l => l.meta && l.meta.kind === 'email' && l.meta.template === 'auto_refund_triggered')
    || t.value.last_email_template === 'auto_refund_triggered');
const isResolved = computed(() =>
    ['rejected', 'completed'].includes(s.value)
    || (s.value === 'auto_resolved' && autoRefundEmailSent.value));
// Whether any action control is available — keeps the Actions box from
// rendering as an empty titled panel once the ticket is fully resolved.
const hasActions = computed(() =>
    (can('verify refunds') && ['submitted', 'pending_transfer_info'].includes(s.value))
    || (can('update refunds') && s.value === 'approved')
    || (can('verify refunds') && !isResolved.value));

// ---- Ops manual match / re-match ----
// Prefill with the currently-matched Order ID so admins can edit + re-match.
const matchOrderId = ref(props.ticket.order_id || '');
const isMatched = computed(() => !!(t.value.vend_transaction_id || t.value.payment_gateway_log_id));
// The match box stays available at every workflow stage so a wrong Order ID can
// always be corrected. Re-matching an approved/scheduled ticket re-derives the
// amount and drops it back to Verified for re-approval (handled backend). Only
// terminal / auto-resolved tickets are locked.
const showMatchBox = computed(() => can('update refunds')
    && !['rejected', 'completed', 'auto_resolved'].includes(s.value));
function doMatch() {
    if (!matchOrderId.value.trim()) return;
    post(base.value + '/match', { order_id: matchOrderId.value.trim() });
}
function doClear() {
    if (!confirm('Clear the matched transaction? This returns the ticket to an unmatched manual claim.')) return;
    post(base.value + '/clear-match');
}

// Full basket (matched-transaction items) with the customer-claimed rows flagged;
// falls back to the stored claimed items when nothing is matched yet.
const flaggedRows = computed(() => (t.value.flagged_items && t.value.flagged_items.length)
    ? t.value.flagged_items
    : (t.value.items || []));
// The transaction this ticket is matched to — drives the date/time/payment line.
const matchedTxn = computed(() => (t.value.related_transactions && t.value.related_transactions.length)
    ? t.value.related_transactions[0]
    : null);

// ---- Customer submission flow (follows the public input flow) ----
const claimedItems = computed(() => t.value.items || []);
const claimedChannels = computed(() => {
    const c = claimedItems.value.map((i) => i.vend_channel_code).filter((v) => v !== null && v !== undefined && v !== '');
    return c.length ? [...new Set(c)].join(', ') : null;
});
const claimedSkus = computed(() => {
    const c = claimedItems.value.map((i) => i.product_name).filter(Boolean);
    return c.length ? [...new Set(c)].join(', ') : null;
});
// Multiple purchase = the matched transaction basket carried more than one item.
const multiplePurchase = computed(() => (t.value.matched ? flaggedRows.value.length > 1 : null));
const payoutLabel = computed(() => (t.value.refund_method === 'paypal' ? 'PayPal email address' : 'PayNow mobile number'));
// Only append the resolved (yymmdd) date when the choice is relative (Today/Yesterday).
const isRelativeDay = computed(() => ['today', 'yesterday'].includes((t.value.entered_day || '').toLowerCase()));
// Manual claims have no matched basket — surface the customer's typed items as the SKU name.
const skuDisplay = computed(() => {
    if (t.value.is_manual && t.value.manual_items_summary) return 'Manual: ' + t.value.manual_items_summary;
    return claimedSkus.value || '—';
});
// Manual claims: show the customer's declared pay method; matched: the classified channel.
const paymentChannelDisplay = computed(() => {
    if (t.value.is_manual && t.value.manual_pay_method) return t.value.manual_pay_method;
    return t.value.payment_channel || '—';
});

// ---- Photo / video (right of the submission; up to 3 thumbnails) ----
const attachments = computed(() => t.value.attachments || []);
</script>

<template>
<Head :title="'Refund ' + t.reference" />
<BreezeAuthenticatedLayout>
    <template #header>
        <div class="flex items-center gap-3">
            <Link href="/refunds" class="text-teal-600 text-sm">← Refunds</Link>
            <h2 class="font-semibold text-xl text-gray-800">
                <span :class="t.is_dropped ? 'line-through text-gray-400' : ''"
                    :title="t.is_dropped ? 'Dropped / closed (e.g. double submission)' : ''">{{ t.reference }}</span> · {{ t.vend_code }}
            </h2>
            <span v-if="t.is_dropped" class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 px-2 py-1 rounded-full bg-gray-100">dropped</span>
            <span class="text-xs font-bold px-2 py-1 rounded-full" :class="statusClass(s)">{{ statuses[s] || s }}</span>
        </div>
    </template>

    <div v-if="$page.props.errors && Object.keys($page.props.errors).length" class="mx-2 sm:mx-5 mt-3">
        <div class="bg-red-50 border border-red-200 text-red-700 text-sm rounded-md px-4 py-3">
            {{ Object.values($page.props.errors)[0] }}
        </div>
    </div>

    <!-- Single column: info → transactions → actions last -->
    <div class="m-2 sm:mx-5 sm:my-3 space-y-4">
        <!-- Customer submission (follows the customer input flow) -->
        <div class="bg-white rounded-md border p-4">
            <h3 class="text-xs uppercase tracking-wide text-gray-500 mb-3">Customer submission</h3>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <!-- left: the input flow -->
                <div class="lg:col-span-2">
                    <dl class="grid grid-cols-3 gap-y-2 text-sm">
                        <dt class="text-gray-500">Name</dt><dd class="col-span-2 font-medium">{{ t.contact_name || '—' }}</dd>
                        <dt class="text-gray-500">Email</dt><dd class="col-span-2 font-medium break-all">{{ t.contact_email || '—' }}</dd>
                        <dt class="text-gray-500">Machine ID</dt><dd class="col-span-2 font-medium">
                            <a v-if="t.vend_code" :href="opsDashboardUrl(t.vend_code)" target="_blank" class="text-teal-700 hover:underline" title="Open in Operations Dashboard">{{ t.vend_code }}</a>
                            <span v-else>—</span>
                        </dd>
                        <dt class="text-gray-500">Site Name</dt><dd class="col-span-2 font-medium">{{ t.site_name || '—' }}</dd>
                        <dt class="text-gray-500">Day Chosen</dt>
                        <dd class="col-span-2 font-medium">
                            <span class="capitalize">{{ t.entered_day || '—' }}</span>
                            <span v-if="t.entered_day_date && isRelativeDay" class="text-gray-500"> ({{ t.entered_day_date }})</span>
                        </dd>
                        <dt class="text-gray-500">Amount paid</dt><dd class="col-span-2 font-medium">{{ t.entered_amount ? '$' + t.entered_amount : '—' }}</dd>
                        <dt class="text-gray-500">Matched with Transaction</dt>
                        <dd class="col-span-2">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full border"
                                :class="t.matched ? 'bg-green-50 text-green-700 border-green-200' : 'bg-gray-50 text-gray-500 border-gray-200'">
                                {{ t.matched ? 'Yes' : 'No' }}
                            </span>
                        </dd>
                        <dt class="text-gray-500">Multiple purchase</dt>
                        <dd class="col-span-2">
                            <span v-if="multiplePurchase === null" class="font-medium text-gray-400">—</span>
                            <span v-else class="text-xs font-semibold px-2 py-0.5 rounded-full border"
                                :class="multiplePurchase ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-gray-50 text-gray-500 border-gray-200'">
                                {{ multiplePurchase ? 'Yes' : 'No' }}
                            </span>
                        </dd>
                        <dt class="text-gray-500">Channel #</dt><dd class="col-span-2 font-medium">{{ claimedChannels || '—' }}</dd>
                        <dt class="text-gray-500">SKU name</dt><dd class="col-span-2 font-medium">{{ skuDisplay }}</dd>
                        <dt class="text-gray-500">Refund Amount</dt><dd class="col-span-2 font-semibold">${{ t.amount }}</dd>
                        <dt class="text-gray-500">Refund Reason</dt><dd class="col-span-2 font-medium">{{ t.reason_code || '—' }}</dd>
                        <dt class="text-gray-500">Note</dt><dd class="col-span-2 font-medium">{{ t.reason_text || '—' }}</dd>
                    </dl>

                    <!-- payout + meta -->
                    <dl class="grid grid-cols-3 gap-y-2 text-sm mt-3 pt-3 border-t">
                        <dt class="text-gray-500">{{ payoutLabel }}</dt><dd class="col-span-2 font-medium break-all">{{ t.payout_destination || '—' }}</dd>
                        <dt class="text-gray-500">Submitted via</dt><dd class="col-span-2 font-medium">{{ t.is_manual ? 'Manual entry (no match found)' : 'Scanned QR' }}</dd>
                        <dt class="text-gray-500">Submitted at</dt><dd class="col-span-2 font-medium">{{ t.created_at }}</dd>
                        <dt class="text-gray-500">Payment channel</dt><dd class="col-span-2 font-medium">{{ paymentChannelDisplay }}</dd>
                        <template v-if="t.is_manual"><dt class="text-gray-500">Approx. time</dt><dd class="col-span-2 font-medium">{{ t.approx_time || '—' }}</dd></template>
                    </dl>
                </div>

                <!-- right: photo / video (one large item, arrows to browse) -->
                <div>
                    <h4 class="text-[10px] uppercase tracking-wide text-gray-500 mb-1.5">Photo / video<span v-if="attachments.length"> ({{ attachments.length }})</span></h4>
                    <div v-if="attachments.length">
                        <div class="relative w-full rounded-md overflow-hidden border bg-black/5 group">
                            <button type="button" @click="openLightbox()" class="block w-full">
                                <video v-if="isVideo(attachments[mediaIndex])" :src="attachments[mediaIndex].url" muted preload="metadata" class="w-full max-h-[420px] object-contain bg-black"></video>
                                <img v-else :src="attachments[mediaIndex].url" :alt="attachments[mediaIndex].original_name" class="w-full max-h-[420px] object-contain bg-black/5" />
                                <span v-if="isVideo(attachments[mediaIndex])" class="absolute inset-0 flex items-center justify-center text-white text-4xl drop-shadow pointer-events-none">▶</span>
                            </button>
                            <template v-if="attachments.length > 1">
                                <button type="button" @click.stop="prevMedia"
                                    class="absolute left-2 top-1/2 -translate-y-1/2 w-9 h-9 flex items-center justify-center rounded-full bg-black/50 hover:bg-black/70 text-white text-xl leading-none">‹</button>
                                <button type="button" @click.stop="nextMedia"
                                    class="absolute right-2 top-1/2 -translate-y-1/2 w-9 h-9 flex items-center justify-center rounded-full bg-black/50 hover:bg-black/70 text-white text-xl leading-none">›</button>
                            </template>
                        </div>
                        <div class="flex items-center justify-between mt-1.5">
                            <p class="text-[11px] text-gray-400">Click to enlarge (max 3 files).</p>
                            <span class="text-[11px] font-semibold text-gray-500">{{ mediaIndex + 1 }}/{{ attachments.length }}</span>
                        </div>
                    </div>
                    <div v-else class="w-32 h-32 rounded-md border border-dashed border-gray-200 flex items-center justify-center text-[11px] text-gray-400 text-center px-2">No file</div>
                </div>
            </div>

            <!-- System self-checking — mirrors the index list's self-check columns
                 (machine RF-in-24h, New/Repeat, product exit sensor, error code). -->
            <div class="mt-4 pt-4 border-t">
                <h4 class="text-[10px] uppercase tracking-wide text-indigo-600 mb-2">System self-checking</h4>
                <dl class="grid grid-cols-2 sm:grid-cols-4 gap-x-6 gap-y-3">
                    <div>
                        <dt class="text-[10px] uppercase tracking-wide text-gray-500">Machine L24h # of RF</dt>
                        <dd class="mt-1">
                            <span v-if="t.machine_rf_24h != null"
                                class="inline-flex items-center justify-center min-w-6 px-2 py-0.5 rounded-full text-xs font-semibold cursor-help"
                                :class="t.machine_rf_24h > 3 ? 'bg-red-100 text-red-700' : (t.machine_rf_24h > 1 ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600')"
                                :title="t.machine_rf_24h + ' refund request(s) on this machine in the 24h up to this submission.'">
                                {{ t.machine_rf_24h }}
                            </span>
                            <span v-else class="text-gray-300">—</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[10px] uppercase tracking-wide text-gray-500">New / Repeat?</dt>
                        <dd class="mt-1">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full cursor-help"
                                :class="(t.is_repeat || t.requester_repeat) ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'"
                                :title="t.is_repeat
                                    ? ('Repeat: this transaction was already claimed under ' + (t.replicated_from_reference || 'an earlier request') + '. Re-validate before payout to avoid a double refund.')
                                    : (t.requester_repeat
                                        ? 'Repeat: this PayNow/PayPal account or email was used on an earlier refund request.'
                                        : 'New: first refund request seen from this requester.')">
                                {{ (t.is_repeat || t.requester_repeat) ? 'Repeat' : 'New' }}
                            </span>
                            <a v-if="t.is_repeat && t.replicated_from_reference" :href="'/refunds?search=' + t.replicated_from_reference"
                                target="_blank" class="block text-[10px] font-semibold text-red-500 mt-0.5 hover:underline"
                                title="Open the original request this one duplicates">↺ duplicates {{ t.replicated_from_reference }}</a>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[10px] uppercase tracking-wide text-gray-500">Prod Exit Sensor</dt>
                        <dd class="mt-1">
                            <span v-if="t.product_drop_sensor === true" class="text-xs font-semibold text-green-700"
                                title="Product Drop Sensor was Enabled on the machine at the time of the transaction.">Enabled</span>
                            <span v-else-if="t.product_drop_sensor === false" class="text-xs font-semibold text-gray-500"
                                title="Product Drop Sensor was Disabled on the machine at the time of the transaction.">Disabled</span>
                            <span v-else class="text-gray-300" title="No Product Drop Sensor reading recorded for this transaction.">—</span>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-[10px] uppercase tracking-wide text-gray-500">Error code</dt>
                        <dd class="mt-1">
                            <span v-if="t.error_code" class="text-xs font-semibold text-amber-700 cursor-help"
                                :title="t.error_desc || ('Error code ' + t.error_code)">{{ t.error_code }}</span>
                            <span v-else class="text-gray-300">—</span>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- System validation -->
        <div class="rounded-md border p-4" :class="recClass(t.recommendation)">
            <h3 class="text-xs uppercase tracking-wide mb-2 opacity-80">System validation — RefundTicket</h3>
            <div class="font-bold capitalize mb-3">Recommend: {{ t.recommendation }}</div>
            <div class="flex flex-wrap gap-2">
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full border cursor-help" :class="hasChannelError ? badgeGood : badgeBad"
                    :title="hasChannelError
                        ? 'A vend channel / hardware error was recorded on this transaction (e.g. sensor or dispense fault). This is evidence the item may not have been dispensed — a valid reason to refund. Review the specific error code in Items flagged.'
                        : 'No vend channel or hardware error was recorded. The machine reported a clean dispense, so there is no hardware evidence backing a not-dispensed claim — verify carefully before refunding.'">
                    {{ hasChannelError ? '✓ Channel error detected' : '⚠ No channel error' }}
                </span>
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full border cursor-help" :class="isManualClaim ? badgeBad : badgeGood"
                    :title="isManualClaim
                        ? 'The customer could not be auto-matched to a sale, so they typed the details in manually. Ops must match the real Order ID and verify the transaction before approving any payout.'
                        : 'Automatically linked to the exact sales transaction behind this claim — the transaction details and validation were filled in by the system, so it is more trustworthy.'">
                    {{ isManualClaim ? '✍ Manual claim' : '🔗 Auto-matched' }}
                </span>
                <span class="text-xs font-semibold px-2.5 py-1 rounded-full border cursor-help" :class="alreadyRefunded ? badgeBad : badgeGood"
                    :title="alreadyRefunded
                        ? 'A refund has already been recorded against this transaction. Check carefully before paying again — proceeding may create a DOUBLE refund.'
                        : 'No prior refund was found for this transaction, so there is no double-refund risk. Safe to proceed on this check.'">
                    {{ alreadyRefunded ? '↩ Already refunded' : '✓ Not yet refunded' }}
                </span>
                <span v-if="t.is_auto_refund_channel" class="text-xs font-semibold px-2.5 py-1 rounded-full border cursor-help" :class="badgeGood"
                    title="This machine's payment provider (Nayax) issues refunds automatically at the terminal. No manual PayNow / PayPal payout is needed for this ticket.">⚡ Nayax auto-refund</span>
            </div>

            <p v-if="t.system_validation && t.system_validation.evaluated_at" class="text-xs text-gray-400 mt-3">Evaluated {{ t.system_validation.evaluated_at }}</p>
        </div>

        <!-- Ops match / re-match: enter (or change) the Order ID to (re)sync the transaction + validation -->
        <div v-if="showMatchBox" class="bg-amber-50 rounded-md border border-amber-300 p-4">
            <h3 class="text-xs uppercase tracking-wide text-amber-700 mb-1">{{ isMatched ? 'Re-match transaction' : 'Match transaction' }}</h3>
            <p class="text-xs text-gray-600 mb-2">
                <template v-if="isMatched">Currently matched to Order ID <b>{{ t.order_id || '—' }}</b>. Enter a different Order ID to re-match — the transaction details, the flagged items and the System Validation will re-sync automatically. Entering the same ID re-validates it.</template>
                <template v-else>No transaction is linked to this claim yet. Find the sale in Sales Transactions, then enter its Order ID — the transaction details, the flagged items and the validation will fill in automatically.</template>
            </p>
            <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
                <input v-model="matchOrderId" @keyup.enter="doMatch" placeholder="Order ID"
                    class="w-full sm:max-w-xs border rounded px-2 py-1.5 text-sm" />
                <button @click="doMatch" :disabled="busy || !matchOrderId.trim()"
                    class="sm:w-auto bg-amber-600 text-white text-sm font-semibold rounded px-4 py-1.5 disabled:opacity-50">{{ isMatched ? '🔁 Re-match Order ID' : '🔗 Match Order ID' }}</button>
                <button v-if="isMatched" @click="doClear" :disabled="busy"
                    class="sm:w-auto bg-white border border-gray-300 text-gray-700 text-sm font-semibold rounded px-4 py-1.5 disabled:opacity-50 hover:bg-gray-50">✕ Clear match</button>
            </div>
        </div>

        <!-- Items flagged (full basket for the matched transaction) -->
        <div class="bg-white rounded-md border p-4">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                <h3 class="text-xs uppercase tracking-wide text-gray-500">Items flagged</h3>
                <span v-if="matchedTxn" class="text-xs text-gray-500">
                    {{ matchedTxn.datetime || '—' }} · {{ matchedTxn.payment_method || '—' }}
                </span>
            </div>
            <table class="min-w-full text-sm">
                <thead class="text-xs text-gray-600 font-semibold"><tr><th class="text-left py-1">Product</th><th class="text-left">Price</th><th class="text-left">Channel error</th></tr></thead>
                <tbody>
                    <tr v-for="(it, i) in flaggedRows" :key="it.id || ('x' + i)" class="border-t" :class="it.claimed === false ? 'bg-gray-50/60' : ''">
                        <td class="py-2">
                            <span :class="it.claimed === false ? 'text-gray-500' : ''">{{ it.product_name }}</span>
                            <span v-if="it.claimed" class="ml-1 text-[10px] font-semibold px-1.5 py-0.5 rounded-full bg-teal-50 border border-teal-200 text-teal-700">claimed</span>
                            <span v-if="it.product_sku" class="block text-xs text-gray-500">{{ it.product_sku }}<span v-if="it.vend_channel_code"> · Ch {{ it.vend_channel_code }}</span></span>
                        </td>
                        <td>{{ it.unit_price !== null && it.unit_price !== undefined ? '$' + it.unit_price : '—' }}</td>
                        <td>
                            <span v-if="it.had_channel_error" class="text-red-600 font-medium">
                                {{ it.channel_error_desc || ('code ' + it.vend_channel_error_code) }}
                                <span class="text-red-400">(code {{ it.vend_channel_error_code }}<span v-if="it.channel_error_weightage">, weight {{ it.channel_error_weightage }}</span>)</span>
                            </span>
                            <span v-else class="text-gray-400">none</span>
                        </td>
                    </tr>
                    <tr v-if="!flaggedRows.length"><td colspan="3" class="py-3 text-xs text-gray-400">No items yet — enter the Order ID above to pull the transaction basket.</td></tr>
                </tbody>
            </table>
        </div>

        <!-- Related transactions (enriched with Sales Transactions columns) -->
        <div v-if="t.related_transactions && t.related_transactions.length" class="bg-white rounded-md border p-4">
            <h3 class="text-xs uppercase tracking-wide text-gray-500 mb-2">Related transactions</h3>
            <div v-for="r in t.related_transactions" :key="r.id" class="rounded-lg border border-gray-200 shadow-sm overflow-hidden mb-3 last:mb-0">
                <!-- header: amount + method + status badges -->
                <div class="flex items-center justify-between gap-3 flex-wrap bg-gray-50 border-b border-gray-200 px-4 py-2.5">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-lg font-bold text-gray-900 tracking-tight">${{ r.amount }}</span>
                        <span class="text-xs text-gray-500">{{ r.payment_method || '—' }}</span>
                        <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full border"
                            :class="r.payment_status === 'Successful' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200'">
                            {{ r.payment_status || '—' }}
                        </span>
                        <span v-if="r.price_type" class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-indigo-50 text-indigo-700 border border-indigo-200">{{ r.price_type }}</span>
                        <span v-if="r.is_refunded" class="text-[11px] font-semibold px-2 py-0.5 rounded-full bg-red-50 text-red-700 border border-red-200">↩ Refunded</span>
                    </div>
                    <a :href="r.link" target="_blank" class="shrink-0 text-teal-600 text-xs font-semibold hover:underline"
                        title="Open Sales Transactions filtered to this machine on the same day">View same-day transactions ↗</a>
                </div>
                <!-- body: labeled detail grid -->
                <div class="px-4 py-3">
                    <dl class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-6 gap-y-3">
                        <div class="min-w-0">
                            <dt class="text-[10px] uppercase tracking-wide text-gray-500">Order ID</dt>
                            <dd class="text-sm text-gray-800 font-medium break-all">{{ r.order_id || '—' }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-[10px] uppercase tracking-wide text-gray-500">Txn DateTime</dt>
                            <dd class="text-sm text-gray-800 font-medium">{{ r.datetime || '—' }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-[10px] uppercase tracking-wide text-gray-500">Machine ID</dt>
                            <dd class="text-sm font-medium">
                                <a v-if="r.machine" :href="opsDashboardUrl(r.machine)" target="_blank" class="text-teal-700 hover:underline" title="Open in Operations Dashboard">{{ r.machine }}</a>
                                <span v-else class="text-gray-800">—</span>
                            </dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-[10px] uppercase tracking-wide text-gray-500">Machine Prefix</dt>
                            <dd class="text-sm text-gray-800 font-medium">{{ r.vend_prefix_name || '—' }}</dd>
                        </div>
                        <div class="min-w-0 col-span-2">
                            <dt class="text-[10px] uppercase tracking-wide text-gray-500">Site</dt>
                            <dd class="text-sm text-gray-800 font-medium">{{ r.site || '—' }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-[10px] uppercase tracking-wide text-gray-500">Operator</dt>
                            <dd class="text-sm text-gray-800 font-medium">{{ r.operator_code || '—' }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-[10px] uppercase tracking-wide text-gray-500">Dispensed</dt>
                            <dd class="text-sm font-medium" :class="(r.dispensed_qty < r.qty) ? 'text-amber-700' : 'text-gray-800'">{{ r.dispensed_qty }}/{{ r.qty }}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-[10px] uppercase tracking-wide text-gray-500">TXN SRC</dt>
                            <dd class="text-sm text-gray-800 font-medium">{{ r.txn_src ?? '—' }}</dd>
                        </div>
                        <div class="min-w-0 col-span-2 lg:col-span-3">
                            <dt class="text-[10px] uppercase tracking-wide text-gray-500">Channel Error</dt>
                            <dd class="text-sm font-medium" :class="r.channel_error ? 'text-amber-700' : 'text-gray-800'">{{ r.channel_error || 'None' }}</dd>
                        </div>
                    </dl>
                    <div v-if="r.items && r.items.length" class="mt-3 pt-3 border-t border-gray-100">
                        <div class="text-[10px] uppercase tracking-wide text-gray-500 mb-1.5">Items</div>
                        <div class="flex flex-col divide-y divide-gray-50">
                            <div v-for="(it, i) in r.items" :key="i" class="flex items-center justify-between gap-3 py-1">
                                <span class="text-sm text-gray-800">{{ it.product }}</span>
                                <span class="text-xs text-gray-400 shrink-0">{{ it.product_code || '—' }}<span v-if="it.channel"> · Ch {{ it.channel }}</span> · ${{ it.price }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Audit trail -->
        <div class="bg-white rounded-md border p-4">
            <h3 class="text-xs uppercase tracking-wide text-gray-500 mb-2">Audit trail</h3>
            <div v-for="(l, i) in t.logs" :key="i" class="text-xs text-gray-600 border-l-2 pl-3 py-1" :class="l.meta && l.meta.kind === 'email' ? 'border-teal-300' : 'border-gray-200'">
                <b class="text-gray-800">{{ l.actor_label }}</b> {{ l.note }}
                <span class="text-gray-400">· {{ l.created_at }}</span>
                <template v-if="l.meta && l.meta.kind === 'email'">
                    <button type="button" @click="openEmail(l.meta)" class="ml-1 inline-flex items-center gap-1 rounded border border-teal-300 bg-teal-50 px-1.5 py-0.5 text-[11px] font-semibold text-teal-700 hover:bg-teal-100">✉ View email</button>
                    <span v-if="!l.meta.delivered" class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] text-gray-500">{{ l.meta.error ? 'failed' : 'not delivered' }}</span>
                </template>
            </div>
            <div v-if="!t.logs.length" class="text-xs text-gray-400">No activity yet.</div>
        </div>

        <!-- Actions (moved to the bottom) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div v-if="hasActions" class="bg-gray-50 rounded-md border p-4 space-y-2">
                <h3 class="text-xs uppercase tracking-wide text-gray-500 mb-1">Actions</h3>

                <!-- ✓ Verified (Approved): moves to Approved and emails the customer
                     that their refund is approved (paid within 5 working days). -->
                <button v-if="can('verify refunds') && ['submitted','pending_transfer_info'].includes(s)" @click="post(base + '/verify')" :disabled="busy" class="w-full text-left text-sm font-semibold px-3 py-2 rounded bg-teal-600 text-white">✓ Verify (Approved) — email customer</button>
                <a v-if="can('verify refunds') && ['submitted','pending_transfer_info'].includes(s)" href="#" @click.prevent="openTemplate('approved')" class="block text-[11px] text-teal-700 underline hover:text-teal-900 -mt-1 mb-1">✉ Preview approval email</a>
                <button v-if="can('update refunds') && s === 'approved'" @click="post(base + '/complete')" :disabled="busy" class="w-full text-left text-sm font-semibold px-3 py-2 rounded bg-green-600 text-white">✓ Mark refund done</button>

                <!-- ✕ No charge / auto-refund: charge already auto-refunded (or never
                     captured). Emails the customer it's handled and closes the ticket;
                     never starts a new refund. -->
                <button v-if="can('verify refunds') && !isResolved" @click="resolveNoCharge" :disabled="busy" class="w-full text-left text-sm font-semibold px-3 py-2 rounded bg-red-600 text-white">✕ Reject → No charge / auto-refund — email customer</button>
                <a v-if="can('verify refunds') && !isResolved" href="#" @click.prevent="openTemplate('auto_refund_triggered')" class="block text-[11px] text-red-700 underline hover:text-red-900 -mt-1 mb-1">✉ Preview auto-refund email</a>

                <!-- ✕ Drop / close (e.g. double submission): kept but struck through in
                     the list. No customer email is sent. -->
                <button v-if="can('verify refunds') && !isResolved" @click="showDrop = !showDrop" class="w-full text-left text-sm font-semibold px-3 py-2 rounded bg-white border border-gray-300 text-gray-600">✕ Reject → Ignore / drop (double submission)</button>
                <div v-if="showDrop" class="pt-1">
                    <textarea v-model="dropRemarks" rows="2" class="w-full border rounded px-2 py-1 text-sm" placeholder="Reason (optional) — e.g. duplicate of RF-…"></textarea>
                    <button @click="doDrop" :disabled="busy" class="mt-1 w-full bg-gray-700 text-white text-sm rounded px-3 py-1.5">Confirm drop (no email)</button>
                </div>
            </div>

            <div v-if="can('update refunds')" class="bg-gray-50 rounded-md border border-red-200 p-4">
                <h3 class="text-xs uppercase tracking-wide text-red-500 mb-2">Danger zone</h3>
                <button @click="deleteTicket" :disabled="busy" class="w-full bg-red-600 text-white text-sm font-semibold rounded px-3 py-2 hover:bg-red-700">🗑 Delete ticket (permanent)</button>
                <p class="text-xs text-gray-400 mt-2">Removes the ticket, its items, attachments and logs. For testing/cleanup.</p>
            </div>
        </div>

        <!-- Prev / next navigation: page straight through the queue without going
             back to the list. Previous = the newer request (row above on the
             list); Next = the older request (row below). -->
        <div class="flex items-stretch gap-3 pt-2 mt-1 border-t border-gray-100">
            <!-- Next (older) — on the left -->
            <Link v-if="nextTicket" :href="'/refunds/' + nextTicket.id" preserve-scroll
                class="group flex-1 flex items-center gap-3 bg-white border border-gray-200 rounded-lg px-4 py-3 shadow-sm transition hover:border-teal-300 hover:shadow hover:bg-teal-50/40">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500 text-lg leading-none transition group-hover:bg-teal-600 group-hover:text-white">‹</span>
                <span class="min-w-0">
                    <span class="block text-[10px] font-semibold uppercase tracking-wide text-gray-400">Next · older</span>
                    <span class="block text-sm font-bold text-teal-700 truncate">{{ nextTicket.reference }}</span>
                </span>
            </Link>
            <div v-else class="flex-1 flex items-center gap-3 rounded-lg border border-dashed border-gray-200 px-4 py-3 text-gray-300">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-50 text-lg leading-none">‹</span>
                <span class="text-xs">No older request</span>
            </div>

            <!-- Previous (newer) — on the right -->
            <Link v-if="prevTicket" :href="'/refunds/' + prevTicket.id" preserve-scroll
                class="group flex-1 flex items-center justify-end gap-3 bg-white border border-gray-200 rounded-lg px-4 py-3 shadow-sm transition hover:border-teal-300 hover:shadow hover:bg-teal-50/40 text-right">
                <span class="min-w-0">
                    <span class="block text-[10px] font-semibold uppercase tracking-wide text-gray-400">Previous · newer</span>
                    <span class="block text-sm font-bold text-teal-700 truncate">{{ prevTicket.reference }}</span>
                </span>
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-500 text-lg leading-none transition group-hover:bg-teal-600 group-hover:text-white">›</span>
            </Link>
            <div v-else class="flex-1 flex items-center justify-end gap-3 rounded-lg border border-dashed border-gray-200 px-4 py-3 text-gray-300 text-right">
                <span class="text-xs">No newer request</span>
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-50 text-lg leading-none">›</span>
            </div>
        </div>
    </div>

    <!-- attachment lightbox -->
    <div v-if="lightboxOpen && attachments.length" class="fixed inset-0 z-50 bg-black/80 flex items-center justify-center p-4" @click.self="closeLightbox">
        <button type="button" @click="closeLightbox" class="absolute top-4 right-5 text-white text-3xl leading-none">&times;</button>
        <video v-if="isVideo(attachments[mediaIndex])" :key="attachments[mediaIndex].id" :src="attachments[mediaIndex].url" controls autoplay class="max-h-[90vh] max-w-[92vw] rounded-md bg-black"></video>
        <img v-else :src="attachments[mediaIndex].url" :alt="attachments[mediaIndex].original_name" class="max-h-[90vh] max-w-[92vw] rounded-md object-contain" />
        <template v-if="attachments.length > 1">
            <button type="button" @click.stop="prevMedia"
                class="absolute left-4 top-1/2 -translate-y-1/2 w-11 h-11 flex items-center justify-center rounded-full bg-white/15 hover:bg-white/30 text-white text-2xl leading-none">‹</button>
            <button type="button" @click.stop="nextMedia"
                class="absolute right-4 top-1/2 -translate-y-1/2 w-11 h-11 flex items-center justify-center rounded-full bg-white/15 hover:bg-white/30 text-white text-2xl leading-none">›</button>
            <span class="absolute bottom-5 left-1/2 -translate-x-1/2 text-white text-sm font-semibold bg-black/40 px-3 py-1 rounded-full">{{ mediaIndex + 1 }}/{{ attachments.length }}</span>
        </template>
    </div>

    <!-- sent-email preview -->
    <div v-if="emailPreview" class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4" @click.self="closeEmail">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg max-h-[85vh] flex flex-col">
            <div class="flex items-center justify-between border-b px-4 py-3">
                <h3 class="text-sm font-semibold text-gray-800">✉ Sent email</h3>
                <button type="button" @click="closeEmail" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
            </div>
            <div class="overflow-y-auto px-4 py-3 text-sm space-y-3">
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-400">To</div>
                    <div class="font-medium break-all">{{ emailPreview.to || '—' }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-400">Subject</div>
                    <div class="font-medium">{{ emailPreview.subject }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-400">Message</div>
                    <div class="mt-1 whitespace-pre-wrap rounded border bg-gray-50 px-3 py-2 text-gray-700">{{ emailPreview.body }}</div>
                </div>
                <div class="flex flex-wrap gap-2 pt-1">
                    <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="emailPreview.delivered ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'">
                        {{ emailPreview.delivered ? 'Delivered' : (emailPreview.error ? 'Send failed' : 'Logged (delivery off)') }}
                    </span>
                    <span v-if="emailPreview.error" class="rounded-full bg-red-50 px-2 py-0.5 text-[11px] text-red-600">{{ emailPreview.error }}</span>
                </div>
            </div>
            <div class="border-t px-4 py-3 text-right">
                <button type="button" @click="closeEmail" class="rounded bg-gray-100 px-4 py-1.5 text-sm font-semibold text-gray-700 hover:bg-gray-200">Close</button>
            </div>
        </div>
    </div>

    <!-- email-template preview (opened from an action button; not yet sent) -->
    <div v-if="templatePreview" class="fixed inset-0 z-50 bg-black/60 flex items-center justify-center p-4" @click.self="closeTemplate">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-lg max-h-[85vh] flex flex-col">
            <div class="flex items-center justify-between border-b px-4 py-3">
                <h3 class="text-sm font-semibold text-gray-800">✉ Email template — {{ templatePreview.label }}</h3>
                <button type="button" @click="closeTemplate" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
            </div>
            <div class="overflow-y-auto px-4 py-3 text-sm space-y-3">
                <div class="rounded border border-amber-200 bg-amber-50 px-3 py-2 text-[11px] text-amber-700">Preview only — this is the exact email that will be sent to this customer when you click the action.</div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-400">Subject</div>
                    <div class="font-medium">{{ templatePreview.subject }}</div>
                </div>
                <div>
                    <div class="text-xs uppercase tracking-wide text-gray-400">Message</div>
                    <div class="mt-1 whitespace-pre-wrap rounded border bg-gray-50 px-3 py-2 text-gray-700">{{ templatePreview.body }}</div>
                </div>
            </div>
            <div class="border-t px-4 py-3 text-right">
                <button type="button" @click="closeTemplate" class="rounded bg-gray-100 px-4 py-1.5 text-sm font-semibold text-gray-700 hover:bg-gray-200">Close</button>
            </div>
        </div>
    </div>
</BreezeAuthenticatedLayout>
</template>
