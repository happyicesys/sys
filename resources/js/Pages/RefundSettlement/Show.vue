<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { LockClosedIcon, LockOpenIcon, ArrowDownTrayIcon } from '@heroicons/vue/20/solid';

const props = defineProps({
    settlement: { type: Object, required: true },
    paynowTickets: { type: Array, default: () => [] },
    paypalTickets: { type: Array, default: () => [] },
    exports: { type: Array, default: () => [] },
    logs: { type: Array, default: () => [] },
});

const msg = ref('');
const busy = ref(false);

// Only two states: open / closed. Anything not open counts as closed.
const isOpen = computed(() => props.settlement.status === 'open');
const isClosed = computed(() => !isOpen.value);
const hasPaynow = computed(() => props.paynowTickets.length > 0);
const hasPaypal = computed(() => props.paypalTickets.length > 0);
// Every member is Approved, so there's no "Pending" label. The Done column only
// appears once at least one row has actually been marked done, and shows just
// "✓ Done" on those rows (blank for the rest).
// The Done column appears once any row is resolved either way — paid (done) or
// flagged insufficient info (handled manually off-settlement).
const showStatus = computed(() => [...props.paynowTickets, ...props.paypalTickets].some((t) => t.is_done || t.is_insufficient));
const allDone = computed(() => {
    const all = [...props.paynowTickets, ...props.paypalTickets];
    return all.length > 0 && all.every((t) => t.is_done);
});

const statusLabel = { open: 'Open', closed: 'Closed' };
const statusClass = (s) => ({
    open: 'bg-amber-100 text-amber-800',
    closed: 'bg-blue-100 text-blue-800',
}[s] || 'bg-gray-100 text-gray-700');

// ---- mark-done selection (any row not already done — allowed open OR closed) ----
const selected = ref([]);
const allTickets = computed(() => [...props.paynowTickets, ...props.paypalTickets]);
// Checkboxes are available as soon as there are tickets; a refund can be paid and
// ticked done before the settlement is formally closed.
const canMarkDone = computed(() => allTickets.value.length > 0);
// A row is tickable until it's paid (done). Insufficient-info rows stay tickable
// so the admin can mark them done here once the payout is handled by hand.
const doneEligible = (t) => !t.is_done;
const eligibleIds = () => allTickets.value.filter(doneEligible).map((t) => t.id);
const allSelected = computed(() => {
    const ids = eligibleIds();
    return ids.length > 0 && ids.every((id) => selected.value.includes(id));
});
function toggleAll(e) { selected.value = e.target.checked ? eligibleIds() : []; }
function toggleRow(id) {
    const i = selected.value.indexOf(id);
    if (i === -1) selected.value.push(id); else selected.value.splice(i, 1);
}

function post(url, body, okReset) {
    busy.value = true; msg.value = '';
    router.post(url, body || {}, {
        preserveScroll: true,
        onError: (e) => { msg.value = e.settlement || Object.values(e)[0] || 'Action failed.'; },
        onSuccess: () => { if (okReset) selected.value = []; },
        onFinish: () => { busy.value = false; },
    });
}

function closeSettlement() {
    if (!confirm('Close this settlement? No more tickets can be added after closing (later approvals open a new batch).')) return;
    post(`/refund-settlements/${props.settlement.id}/close`);
}
function reopenSettlement() {
    if (!confirm('Undo close and reopen this settlement? You can add or remove tickets again.')) return;
    post(`/refund-settlements/${props.settlement.id}/reopen`);
}
function voidSettlement() {
    if (!confirm('Void this empty settlement?')) return;
    busy.value = true; msg.value = '';
    router.delete(`/refund-settlements/${props.settlement.id}`, {
        onError: (e) => { msg.value = e.settlement || Object.values(e)[0] || 'Action failed.'; busy.value = false; },
    });
}
function returnToPool(ticketId, ref_) {
    if (!confirm('Remove ' + ref_ + ' from this settlement? It goes back to Approved so you can push it into another settlement.')) return;
    post(`/refund-settlements/${props.settlement.id}/return-to-pool/${ticketId}`);
}
function markDone() {
    if (!selected.value.length) { msg.value = 'Tick the rows the bank / PayPal actually paid.'; return; }
    if (!confirm('Mark ' + selected.value.length + ' refund(s) as done? A completion email is sent to each (or logged while emails are off).')) return;
    post(`/refund-settlements/${props.settlement.id}/mark-done`, { ticket_ids: selected.value }, true);
}
function markInsufficientInfo() {
    if (!selected.value.length) { msg.value = 'Tick the rows the bank could not pay (bad / missing PayNow info).'; return; }
    if (!confirm('Flag ' + selected.value.length + ' refund(s) as Insufficient Info? They stay in this settlement but drop out of the payout file — handle them by hand on the Refund Requests page. No email is sent.')) return;
    post(`/refund-settlements/${props.settlement.id}/mark-insufficient-info`, { ticket_ids: selected.value }, true);
}

async function blobExport(action, fallbackName) {
    busy.value = true; msg.value = '';
    try {
        const res = await window.axios.post(`/refund-settlements/${props.settlement.id}/${action}`, {}, { responseType: 'blob' });
        let fn = res.headers['x-filename'] || fallbackName;
        const cd = res.headers['content-disposition'];
        if (!res.headers['x-filename'] && cd) {
            const m = /filename="?([^"]+)"?/.exec(cd);
            if (m) fn = m[1];
        }
        const url = URL.createObjectURL(new Blob([res.data]));
        const a = document.createElement('a');
        a.href = url; a.download = fn; document.body.appendChild(a); a.click(); a.remove();
        URL.revokeObjectURL(url);
        router.reload();
    } catch (e) {
        let m = 'Export failed.';
        if (e.response && e.response.status === 422) {
            try { const b = JSON.parse(await e.response.data.text()); if (b && b.message) m = b.message; } catch (_) { /* keep */ }
        }
        msg.value = m;
    } finally {
        busy.value = false;
    }
}

// ---- Audit-trail action badge (mirrors the Refund Request page) ----
// Badges the settlement lifecycle events; the plain "Settlement opened" line is
// left unbadged like the ticket page's system lines.
const actionBadges = {
    entry_added: { label: 'Added', cls: 'bg-blue-100 text-blue-700 border-blue-200' },
    entry_removed: { label: 'Removed', cls: 'bg-gray-200 text-gray-700 border-gray-300' },
    closed: { label: 'Closed', cls: 'bg-green-100 text-green-700 border-green-200' },
    reopened: { label: 'Reopened', cls: 'bg-yellow-100 text-yellow-700 border-yellow-200' },
    exported_cimb: { label: 'Exported CIMB', cls: 'bg-violet-100 text-violet-700 border-violet-200' },
    exported_xlsx: { label: 'Exported Excel', cls: 'bg-emerald-100 text-emerald-700 border-emerald-200' },
    marked_done: { label: 'Refund done', cls: 'bg-green-100 text-green-700 border-green-200' },
    insufficient_info: { label: 'Insufficient info', cls: 'bg-red-100 text-red-700 border-red-200' },
    settled: { label: 'Settled', cls: 'bg-green-100 text-green-700 border-green-200' },
    voided: { label: 'Voided', cls: 'bg-gray-100 text-gray-600 border-gray-300' },
};
function actionBadge(l) {
    return actionBadges[l.action] || null;
}
</script>

<template>
<Head :title="'Settlement ' + settlement.reference" />
<BreezeAuthenticatedLayout>
    <template #header>
        <div class="flex items-center gap-3">
            <Link href="/refund-settlements" class="text-gray-400 hover:text-gray-600">←</Link>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ settlement.reference }}</h2>
            <span class="text-xs font-bold px-2 py-1 rounded-full" :class="statusClass(settlement.status)">{{ statusLabel[settlement.status] || settlement.status }}</span>
            <span v-if="settlement.is_stale" class="text-[10px] font-semibold uppercase tracking-wide bg-red-100 text-red-700 px-1.5 py-0.5 rounded">stale — please close</span>
        </div>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3 max-w-5xl">
        <!-- summary -->
        <div class="bg-white rounded-md border p-4 mb-3">
            <div class="flex items-start justify-between gap-4">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm flex-1">
                    <div><div class="text-xs text-gray-500">Payout Group / Operator</div><div class="font-medium">{{ settlement.head }}</div></div>
                    <div><div class="text-xs text-gray-500">Date</div><div class="font-medium">{{ settlement.settlement_date }}</div></div>
                    <div><div class="text-xs text-gray-500">Tickets</div><div class="font-medium">{{ settlement.count }}</div></div>
                    <div><div class="text-xs text-gray-500">Total</div><div class="font-medium">${{ settlement.total }}</div></div>
                </div>
                <!-- Close / Undo-close settlement — top-right quick action. Open shows
                     Close; closed shows Undo close. Same handlers/endpoints as before. -->
                <button v-if="isOpen" @click="closeSettlement" :disabled="busy || settlement.count === 0"
                    class="shrink-0 inline-flex items-center gap-1 bg-blue-600 text-white rounded-md px-4 py-2 text-sm font-semibold hover:bg-blue-700 disabled:opacity-50"
                    title="Close this settlement. No more tickets can be added after closing (later approvals open a new batch).">
                    <LockClosedIcon class="h-4 w-4" /> Close settlement
                </button>
                <button v-else @click="reopenSettlement" :disabled="busy"
                    class="shrink-0 inline-flex items-center gap-1 bg-yellow-500 text-white rounded-md px-4 py-2 text-sm font-semibold hover:bg-yellow-600 disabled:opacity-50"
                    title="Reopen this settlement so tickets can be added or removed again.">
                    <LockOpenIcon class="h-4 w-4" /> Undo close settlement
                </button>
            </div>
            <p v-if="isOpen" class="text-xs text-gray-500 mt-3">Add more approved refunds from the Refund Requests page, then use the Actions below.</p>
        </div>

        <!-- exported files -->
        <div v-if="exports.length" class="bg-white rounded-md border p-3 mb-3">
            <div class="text-xs font-semibold text-gray-500 uppercase mb-2">Exported files</div>
            <div class="flex flex-col gap-1 text-sm">
                <a v-for="e in exports" :key="e.id" :href="e.download_url"
                    class="text-teal-700 hover:underline">
                    ⬇ {{ e.filename }} <span class="text-gray-400">· {{ e.method }} · {{ e.count }} row(s) · ${{ e.total }} · {{ e.exported_at }}</span>
                </a>
            </div>
        </div>

        <!-- PayNow stream -->
        <div v-if="hasPaynow" class="bg-white rounded-md border overflow-x-auto mb-3">
            <div class="px-4 py-2 text-sm font-semibold text-gray-700 bg-gray-50 border-b">PayNow — CIMB bulk transfer</div>
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr class="[&>th]:px-4 [&>th]:py-2 [&>th]:text-left [&>th]:whitespace-nowrap">
                        <th v-if="canMarkDone" class="w-8"><input type="checkbox" :checked="allSelected" @change="toggleAll" /></th>
                        <th>Refund ID</th><th>Machine / Site</th><th class="!text-center">Amount</th><th class="!text-center">PayNow</th><th v-if="showStatus" class="!text-center">Done?</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="t in paynowTickets" :key="t.id" class="border-t">
                        <td v-if="canMarkDone" class="px-4 py-2">
                            <input type="checkbox" :disabled="!doneEligible(t)" :checked="selected.includes(t.id)" @change="toggleRow(t.id)" />
                        </td>
                        <td class="px-4 py-2"><a :href="'/refunds/' + t.id" target="_blank" class="text-teal-700 font-semibold hover:underline">{{ t.reference }}</a></td>
                        <td class="px-4 py-2">{{ t.vend_code }}<div class="text-xs text-gray-500">{{ t.site_name || '—' }}</div></td>
                        <td class="px-4 py-2 text-center">${{ t.amount }}</td>
                        <td class="px-4 py-2 text-center">
                            <span>{{ t.payout_destination }}</span>
                            <span v-if="!t.proxy_valid" class="ml-1 text-[10px] font-semibold uppercase bg-red-100 text-red-700 px-1 py-0.5 rounded" title="This PayNow number does not look valid — the bank will likely reject it. Fix or return to pool.">check no.</span>
                        </td>
                        <td v-if="showStatus" class="px-4 py-2 text-center">
                            <span v-if="t.is_done" class="text-xs font-bold px-2 py-1 rounded-full bg-green-100 text-green-800">✓ Done<template v-if="t.completed_at"> · {{ t.completed_at }}</template></span>
                            <span v-else-if="t.is_insufficient" class="text-xs font-bold px-2 py-1 rounded-full bg-red-100 text-red-800" title="The bank could not pay this — handle the payout by hand, then tick it and mark it done here.">Insufficient Info</span>
                            <span v-else class="text-gray-300">—</span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <button v-if="isOpen && !t.is_done" @click="returnToPool(t.id, t.reference)" :disabled="busy"
                                class="text-xs text-gray-500 hover:text-red-600 underline"
                                title="Remove from this settlement — the refund goes back to Approved. Only possible while the settlement is Open.">Remove</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- PayPal stream -->
        <div v-if="hasPaypal" class="bg-white rounded-md border overflow-x-auto mb-3">
            <div class="px-4 py-2 text-sm font-semibold text-gray-700 bg-gray-50 border-b">PayPal — Excel worklist (pay manually)</div>
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr class="[&>th]:px-4 [&>th]:py-2 [&>th]:text-left [&>th]:whitespace-nowrap">
                        <th v-if="canMarkDone" class="w-8"></th>
                        <th>Refund ID</th><th>Machine / Site</th><th class="!text-center">Amount</th><th class="!text-center">PayPal email</th><th v-if="showStatus" class="!text-center">Done?</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="t in paypalTickets" :key="t.id" class="border-t">
                        <td v-if="canMarkDone" class="px-4 py-2">
                            <input type="checkbox" :disabled="!doneEligible(t)" :checked="selected.includes(t.id)" @change="toggleRow(t.id)" />
                        </td>
                        <td class="px-4 py-2"><a :href="'/refunds/' + t.id" target="_blank" class="text-teal-700 font-semibold hover:underline">{{ t.reference }}</a></td>
                        <td class="px-4 py-2">{{ t.vend_code }}<div class="text-xs text-gray-500">{{ t.site_name || '—' }}</div></td>
                        <td class="px-4 py-2 text-center">${{ t.amount }}</td>
                        <td class="px-4 py-2 text-center">{{ t.payout_destination }}</td>
                        <td v-if="showStatus" class="px-4 py-2 text-center">
                            <span v-if="t.is_done" class="text-xs font-bold px-2 py-1 rounded-full bg-green-100 text-green-800">✓ Done<template v-if="t.completed_at"> · {{ t.completed_at }}</template></span>
                            <span v-else-if="t.is_insufficient" class="text-xs font-bold px-2 py-1 rounded-full bg-red-100 text-red-800" title="The bank could not pay this — handle the payout by hand, then tick it and mark it done here.">Insufficient Info</span>
                            <span v-else class="text-gray-300">—</span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <button v-if="isOpen && !t.is_done" @click="returnToPool(t.id, t.reference)" :disabled="busy"
                                class="text-xs text-gray-500 hover:text-red-600 underline"
                                title="Remove from this settlement — the refund goes back to Approved. Only possible while the settlement is Open.">Remove</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="!hasPaynow && !hasPaypal" class="bg-white rounded-md border px-4 py-8 text-center text-gray-400 mb-3">No tickets in this settlement.</div>

        <!-- Actions — one row per action: button on the left, what it does on the
             right, a divider under each. Close / Undo close live at the top of the
             summary card; the settlement also auto-closes once every row is done. -->
        <div class="bg-white rounded-md border p-4 mb-3">
            <h3 class="text-xs uppercase tracking-wide text-gray-500 mb-3">Actions</h3>

            <!-- Export CIMB text (PayNow) -->
            <template v-if="hasPaynow">
                <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 py-3">
                    <div class="sm:w-72 shrink-0">
                        <button @click="blobExport('export-cimb', settlement.reference + '-cimb.txt')" :disabled="busy"
                            class="w-full inline-flex items-center justify-center gap-1 bg-white border border-gray-300 text-gray-700 rounded-md px-4 py-2 text-sm font-semibold hover:bg-gray-50 disabled:opacity-50">
                            <ArrowDownTrayIcon class="h-4 w-4" /> Export CIMB text (PayNow)
                        </button>
                    </div>
                    <p class="text-sm text-gray-600 flex-1">Export the text file, then attach this file at the CIMB platform under Bulk Payment.</p>
                </div>
                <hr class="border-gray-200" />
            </template>

            <!-- Export Excel (PayPal) -->
            <template v-if="hasPaypal">
                <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 py-3">
                    <div class="sm:w-72 shrink-0">
                        <button @click="blobExport('export-xlsx', settlement.reference + '-paypal.xlsx')" :disabled="busy"
                            class="w-full inline-flex items-center justify-center gap-1 bg-white border border-gray-300 text-gray-700 rounded-md px-4 py-2 text-sm font-semibold hover:bg-gray-50 disabled:opacity-50">
                            <ArrowDownTrayIcon class="h-4 w-4" /> Export Excel (PayPal)
                        </button>
                    </div>
                    <p class="text-sm text-gray-600 flex-1">Export the Excel worklist, then pay each PayPal refund manually on the PayPal platform.</p>
                </div>
                <hr class="border-gray-200" />
            </template>

            <template v-if="!allDone && allTickets.length">
                <!-- Mark selected as Completed -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 py-3">
                    <div class="sm:w-72 shrink-0">
                        <button @click="markDone" :disabled="busy || !selected.length"
                            class="w-full bg-green-600 text-white rounded-md px-4 py-2 text-sm font-semibold hover:bg-green-700 disabled:opacity-50">✓ Mark selected as Completed ({{ selected.length }})</button>
                    </div>
                    <p class="text-sm text-gray-600 flex-1">PayNow info correct and refund completed. Tick the refund rows, then click this button — it will trigger an email to customers to inform them the refund is completed.</p>
                </div>
                <hr class="border-gray-200" />

                <!-- Mark selected as Insufficient Info -->
                <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 py-3">
                    <div class="sm:w-72 shrink-0">
                        <button @click="markInsufficientInfo" :disabled="busy || !selected.length"
                            class="w-full bg-red-600 text-white rounded-md px-4 py-2 text-sm font-semibold hover:bg-red-700 disabled:opacity-50">Mark selected as Insufficient Info ({{ selected.length }})</button>
                    </div>
                    <p class="text-sm text-gray-600 flex-1">PayNow info is wrong or insufficient. Tick the refund rows, then click this button to mark them as <b>Insufficient Info</b> — it will <b>not</b> trigger any email to customers. Admin will need to manually email customers to obtain the needed PayNow info; once the refund is complete, tick them again and mark as Completed.</p>
                </div>
                <hr class="border-gray-200" />
            </template>

            <p v-else-if="allDone" class="text-sm text-green-700 font-medium py-3">All refunds in this settlement are done.</p>

            <!-- Void empty settlement -->
            <div v-if="isOpen && settlement.count === 0" class="py-3">
                <button @click="voidSettlement" :disabled="busy"
                    class="bg-gray-100 text-gray-700 border rounded-md px-4 py-2 text-sm hover:bg-gray-200 disabled:opacity-50">Void (empty)</button>
            </div>

            <div v-if="msg" class="text-xs text-red-600 mt-2">{{ msg }}</div>
        </div>

        <!-- Audit trail (mirrors the Refund Request page) -->
        <div class="bg-white rounded-md border p-4">
            <h3 class="text-xs uppercase tracking-wide text-gray-500 mb-2">Audit trail</h3>
            <div v-for="(l, i) in logs" :key="i"
                class="text-xs text-gray-600 border-l-2 pl-3 py-1 flex items-start justify-between gap-3"
                :class="[actionBadge(l) ? 'border-gray-300 bg-gray-50/60' : 'border-gray-200']">
                <div class="min-w-0">
                    <b class="text-gray-800">{{ l.actor_label }}</b> {{ l.note }}
                    <span class="text-gray-400">· {{ l.created_at }}</span>
                </div>
                <span v-if="actionBadge(l)" class="shrink-0 inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide"
                    :class="actionBadge(l).cls" title="Action performed by the admin">{{ actionBadge(l).label }}</span>
            </div>
            <div v-if="!logs.length" class="text-xs text-gray-400">No activity yet.</div>
        </div>
    </div>
</BreezeAuthenticatedLayout>
</template>
