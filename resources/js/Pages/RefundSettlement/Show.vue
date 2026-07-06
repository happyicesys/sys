<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    settlement: { type: Object, required: true },
    paynowTickets: { type: Array, default: () => [] },
    paypalTickets: { type: Array, default: () => [] },
    exports: { type: Array, default: () => [] },
    logs: { type: Array, default: () => [] },
});

const msg = ref('');
const busy = ref(false);

const isOpen = computed(() => props.settlement.status === 'open');
const isClosed = computed(() => props.settlement.status === 'closed');
const isExported = computed(() => props.settlement.status === 'exported');
const isDone = computed(() => props.settlement.status === 'done');
const hasPaynow = computed(() => props.paynowTickets.length > 0);
const hasPaypal = computed(() => props.paypalTickets.length > 0);

const statusLabel = { open: 'Open', closed: 'Closed', exported: 'Exported', done: 'Done' };
const statusClass = (s) => ({
    open: 'bg-amber-100 text-amber-800',
    closed: 'bg-blue-100 text-blue-800',
    exported: 'bg-violet-100 text-violet-800',
    done: 'bg-green-100 text-green-800',
}[s] || 'bg-gray-100 text-gray-700');

// ---- mark-done selection (only when exported, and row not already done) ----
const selected = ref([]);
const canMarkDone = computed(() => isExported.value);
const doneEligible = (t) => canMarkDone.value && !t.is_done;
const allTickets = computed(() => [...props.paynowTickets, ...props.paypalTickets]);
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
function voidSettlement() {
    if (!confirm('Void this empty settlement?')) return;
    busy.value = true; msg.value = '';
    router.delete(`/refund-settlements/${props.settlement.id}`, {
        onError: (e) => { msg.value = e.settlement || Object.values(e)[0] || 'Action failed.'; busy.value = false; },
    });
}
function returnToPool(ticketId, ref_) {
    if (!confirm('Return ' + ref_ + ' to the pool? It goes back to Approved so you can push it into a later settlement.')) return;
    post(`/refund-settlements/${props.settlement.id}/return-to-pool/${ticketId}`);
}
function markDone() {
    if (!selected.value.length) { msg.value = 'Tick the rows the bank / PayPal actually paid.'; return; }
    if (!confirm('Mark ' + selected.value.length + ' refund(s) as done? A completion email is sent to each (or logged while emails are off).')) return;
    post(`/refund-settlements/${props.settlement.id}/mark-done`, { ticket_ids: selected.value }, true);
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
        <!-- summary + actions -->
        <div class="bg-white rounded-md border p-4 mb-3">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm mb-3">
                <div><div class="text-xs text-gray-500">Payout Group / Operator</div><div class="font-medium">{{ settlement.head }}</div></div>
                <div><div class="text-xs text-gray-500">Date</div><div class="font-medium">{{ settlement.settlement_date }}</div></div>
                <div><div class="text-xs text-gray-500">Tickets</div><div class="font-medium">{{ settlement.count }}</div></div>
                <div><div class="text-xs text-gray-500">Total</div><div class="font-medium">${{ settlement.total }}</div></div>
            </div>

            <div class="flex flex-wrap items-center gap-2 border-t pt-3">
                <button v-if="isOpen && settlement.count > 0" @click="closeSettlement" :disabled="busy"
                    class="bg-blue-600 text-white rounded-md px-4 py-2 text-sm font-semibold hover:bg-blue-700 disabled:opacity-50">Close settlement</button>
                <button v-if="isOpen && settlement.count === 0" @click="voidSettlement" :disabled="busy"
                    class="bg-gray-100 text-gray-700 border rounded-md px-4 py-2 text-sm hover:bg-gray-200 disabled:opacity-50">Void (empty)</button>

                <button v-if="(isClosed || isExported) && hasPaynow" @click="blobExport('export-cimb', settlement.reference + '-cimb.txt')" :disabled="busy"
                    class="bg-teal-600 text-white rounded-md px-4 py-2 text-sm font-semibold hover:bg-teal-700 disabled:opacity-50">⬇ Export CIMB (PayNow)</button>
                <button v-if="(isClosed || isExported) && hasPaypal" @click="blobExport('export-xlsx', settlement.reference + '-paypal.xlsx')" :disabled="busy"
                    class="bg-emerald-600 text-white rounded-md px-4 py-2 text-sm font-semibold hover:bg-emerald-700 disabled:opacity-50">⬇ Export Excel (PayPal)</button>

                <button v-if="isExported" @click="markDone" :disabled="busy || !selected.length"
                    class="bg-green-600 text-white rounded-md px-4 py-2 text-sm font-semibold hover:bg-green-700 disabled:opacity-50">✓ Mark selected refund done ({{ selected.length }})</button>

                <span v-if="isOpen" class="text-xs text-gray-500">Add more approved refunds from the Refund Requests page, then close.</span>
                <span v-if="isExported" class="text-xs text-gray-500">Tick the rows that were actually paid, then mark them done. Leave bounced rows unchecked.</span>
                <span v-if="isDone" class="text-xs text-green-700 font-medium">All member refunds completed.</span>
            </div>
            <div v-if="msg" class="text-xs text-red-600 mt-2">{{ msg }}</div>
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
                        <th>Refund ID</th><th>Machine / Site</th><th class="text-right">Amount</th><th>PayNow</th><th class="text-center">Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="t in paynowTickets" :key="t.id" class="border-t">
                        <td v-if="canMarkDone" class="px-4 py-2">
                            <input type="checkbox" :disabled="!doneEligible(t)" :checked="selected.includes(t.id)" @change="toggleRow(t.id)" />
                        </td>
                        <td class="px-4 py-2"><a :href="'/refunds/' + t.id" target="_blank" class="text-teal-700 font-semibold hover:underline">{{ t.reference }}</a></td>
                        <td class="px-4 py-2">{{ t.vend_code }}<div class="text-xs text-gray-500">{{ t.site_name || '—' }}</div></td>
                        <td class="px-4 py-2 text-right">${{ t.amount }}</td>
                        <td class="px-4 py-2">
                            <span>{{ t.payout_destination }}</span>
                            <span v-if="!t.proxy_valid" class="ml-1 text-[10px] font-semibold uppercase bg-red-100 text-red-700 px-1 py-0.5 rounded" title="This PayNow number does not look valid — the bank will likely reject it. Fix or return to pool.">check no.</span>
                        </td>
                        <td class="px-4 py-2 text-center">
                            <span v-if="t.is_done" class="text-xs font-bold px-2 py-1 rounded-full bg-green-100 text-green-800">✓ Done<template v-if="t.completed_at"> · {{ t.completed_at }}</template></span>
                            <span v-else class="text-xs font-bold px-2 py-1 rounded-full bg-amber-100 text-amber-800">Pending</span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <button v-if="!t.is_done && (isExported || isClosed)" @click="returnToPool(t.id, t.reference)" :disabled="busy"
                                class="text-xs text-gray-500 hover:text-red-600 underline">Return to pool</button>
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
                        <th>Refund ID</th><th>Machine / Site</th><th class="text-right">Amount</th><th>PayPal email</th><th class="text-center">Status</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="t in paypalTickets" :key="t.id" class="border-t">
                        <td v-if="canMarkDone" class="px-4 py-2">
                            <input type="checkbox" :disabled="!doneEligible(t)" :checked="selected.includes(t.id)" @change="toggleRow(t.id)" />
                        </td>
                        <td class="px-4 py-2"><a :href="'/refunds/' + t.id" target="_blank" class="text-teal-700 font-semibold hover:underline">{{ t.reference }}</a></td>
                        <td class="px-4 py-2">{{ t.vend_code }}<div class="text-xs text-gray-500">{{ t.site_name || '—' }}</div></td>
                        <td class="px-4 py-2 text-right">${{ t.amount }}</td>
                        <td class="px-4 py-2">{{ t.payout_destination }}</td>
                        <td class="px-4 py-2 text-center">
                            <span v-if="t.is_done" class="text-xs font-bold px-2 py-1 rounded-full bg-green-100 text-green-800">✓ Done<template v-if="t.completed_at"> · {{ t.completed_at }}</template></span>
                            <span v-else class="text-xs font-bold px-2 py-1 rounded-full bg-amber-100 text-amber-800">Pending</span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <button v-if="!t.is_done && (isExported || isClosed)" @click="returnToPool(t.id, t.reference)" :disabled="busy"
                                class="text-xs text-gray-500 hover:text-red-600 underline">Return to pool</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-if="!hasPaynow && !hasPaypal" class="bg-white rounded-md border px-4 py-8 text-center text-gray-400 mb-3">No tickets in this settlement.</div>

        <!-- audit log -->
        <div v-if="logs.length" class="bg-white rounded-md border p-3">
            <div class="text-xs font-semibold text-gray-500 uppercase mb-2">Audit trail</div>
            <div class="flex flex-col gap-1 text-xs text-gray-600">
                <div v-for="(l, i) in logs" :key="i" class="flex gap-2">
                    <span class="text-gray-400 whitespace-nowrap">{{ l.created_at }}</span>
                    <span class="font-semibold text-gray-700 whitespace-nowrap">{{ l.action }}</span>
                    <span class="text-gray-500">{{ l.note }}</span>
                    <span class="text-gray-400 ml-auto whitespace-nowrap">{{ l.actor_label }}</span>
                </div>
            </div>
        </div>
    </div>
</BreezeAuthenticatedLayout>
</template>
