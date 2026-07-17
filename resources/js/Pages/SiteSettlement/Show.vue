<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { LockOpenIcon, ArrowDownTrayIcon } from '@heroicons/vue/20/solid';

const props = defineProps({
    settlement: { type: Object, required: true },
    rows: { type: Array, default: () => [] },
    exports: { type: Array, default: () => [] },
    logs: { type: Array, default: () => [] },
});

const msg = ref('');
const busy = ref(false);

const isOpen = computed(() => props.settlement.status === 'open');
const isClosed = computed(() => !isOpen.value);
const allDone = computed(() => props.rows.length > 0 && props.rows.every((r) => r.is_paid));
const showStatus = computed(() => props.rows.some((r) => r.is_paid));

const statusLabel = { open: 'Open', closed: 'Closed' };
const statusClass = (s) => ({ open: 'bg-amber-100 text-amber-800', closed: 'bg-blue-100 text-blue-800' }[s] || 'bg-gray-100 text-gray-700');

// ---- selection (by row key; a row = one site+month, holding its summary_ids) ----
const selected = ref([]);
const doneEligible = (r) => !r.is_paid;
const eligibleKeys = () => props.rows.filter(doneEligible).map((r) => r.key);
const allSelected = computed(() => {
    const k = eligibleKeys();
    return k.length > 0 && k.every((key) => selected.value.includes(key));
});
function toggleAll(e) { selected.value = e.target.checked ? eligibleKeys() : []; }
function toggleRow(key) {
    const i = selected.value.indexOf(key);
    if (i === -1) selected.value.push(key); else selected.value.splice(i, 1);
}
const selectedSummaryIds = () => props.rows.filter((r) => selected.value.includes(r.key)).flatMap((r) => r.summary_ids);

function post(url, body, okReset) {
    busy.value = true; msg.value = '';
    router.post(url, body || {}, {
        preserveScroll: true,
        onError: (e) => { msg.value = e.settlement || Object.values(e)[0] || 'Action failed.'; },
        onSuccess: () => { if (okReset) selected.value = []; },
        onFinish: () => { busy.value = false; },
    });
}
function reopenSettlement() {
    if (!confirm('Undo close and reopen this settlement? You can add or remove rows again.')) return;
    post(`/site-settlements/${props.settlement.id}/reopen`);
}
function voidSettlement() {
    if (!confirm('Void this empty settlement?')) return;
    busy.value = true; msg.value = '';
    router.delete(`/site-settlements/${props.settlement.id}`, {
        onError: (e) => { msg.value = e.settlement || Object.values(e)[0] || 'Action failed.'; busy.value = false; },
    });
}
function returnToPool(summaryId, label) {
    if (!confirm('Remove ' + label + ' from this settlement? It goes back to the Site Summary so you can push it again.')) return;
    post(`/site-settlements/${props.settlement.id}/return-to-pool/${summaryId}`);
}
function markDone() {
    const ids = selectedSummaryIds();
    if (!ids.length) { msg.value = 'Tick the rows that were actually paid.'; return; }
    if (!confirm('Mark ' + selected.value.length + ' site payment(s) as paid? This records the payment in the settlement ledger.')) return;
    post(`/site-settlements/${props.settlement.id}/mark-done`, { ids }, true);
}

async function exportCimb() {
    busy.value = true; msg.value = '';
    try {
        const res = await window.axios.post(`/site-settlements/${props.settlement.id}/export-cimb`, {}, { responseType: 'blob' });
        let fn = res.headers['x-filename'] || (props.settlement.reference + '-cimb.txt');
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

const actionBadges = {
    entry_added: { label: 'Added', cls: 'bg-blue-100 text-blue-700 border-blue-200' },
    entry_removed: { label: 'Removed', cls: 'bg-gray-200 text-gray-700 border-gray-300' },
    closed: { label: 'Closed', cls: 'bg-green-100 text-green-700 border-green-200' },
    reopened: { label: 'Reopened', cls: 'bg-yellow-100 text-yellow-700 border-yellow-200' },
    exported_cimb: { label: 'Exported CIMB', cls: 'bg-violet-100 text-violet-700 border-violet-200' },
    marked_done: { label: 'Paid', cls: 'bg-green-100 text-green-700 border-green-200' },
};
function actionBadge(l) { return actionBadges[l.action] || null; }
</script>

<template>
<Head :title="'Settlement ' + settlement.reference" />
<BreezeAuthenticatedLayout>
    <template #header>
        <div class="flex items-center gap-3">
            <Link href="/site-settlements" class="text-gray-400 hover:text-gray-600">←</Link>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ settlement.reference }}</h2>
            <span class="text-xs font-bold px-2 py-1 rounded-full" :class="statusClass(settlement.status)">{{ statusLabel[settlement.status] || settlement.status }}</span>
            <span v-if="settlement.is_stale" class="text-[10px] font-semibold uppercase tracking-wide bg-red-100 text-red-700 px-1.5 py-0.5 rounded">stale</span>
        </div>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3 max-w-5xl">
        <!-- summary -->
        <div class="bg-white rounded-md border p-4 mb-3">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                <div><div class="text-xs text-gray-500">Payout Group / Operator</div><div class="font-medium">{{ settlement.head }}</div></div>
                <div><div class="text-xs text-gray-500">Date</div><div class="font-medium">{{ settlement.settlement_date }}</div></div>
                <div><div class="text-xs text-gray-500">Sites</div><div class="font-medium">{{ settlement.count }}</div></div>
                <div><div class="text-xs text-gray-500">Total</div><div class="font-medium">${{ settlement.total }}</div></div>
            </div>
        </div>

        <!-- exported files -->
        <div v-if="exports.length" class="bg-white rounded-md border p-3 mb-3">
            <div class="text-xs font-semibold text-gray-500 uppercase mb-2">Exported files</div>
            <div class="flex flex-col gap-1 text-sm">
                <a v-for="e in exports" :key="e.id" :href="e.download_url" class="text-teal-700 hover:underline">
                    ⬇ {{ e.filename }} <span class="text-gray-400">· {{ e.count }} site(s) · ${{ e.total }} · {{ e.exported_at }}</span>
                </a>
            </div>
        </div>

        <!-- site rows -->
        <div v-if="rows.length" class="bg-white rounded-md border overflow-x-auto mb-3">
            <div class="px-4 py-2 text-sm font-semibold text-gray-700 bg-gray-50 border-b">Location fee — CIMB bulk transfer</div>
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr class="[&>th]:px-4 [&>th]:py-2 [&>th]:text-left [&>th]:whitespace-nowrap">
                        <th class="w-8"><input type="checkbox" :checked="allSelected" @change="toggleAll" /></th>
                        <th>Site</th><th>Month</th><th class="!text-center">Amount</th><th>Bank / Proxy</th>
                        <th v-if="showStatus" class="!text-center">Paid?</th><th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in rows" :key="r.key" class="border-t">
                        <td class="px-4 py-2"><input type="checkbox" :disabled="!doneEligible(r)" :checked="selected.includes(r.key)" @change="toggleRow(r.key)" /></td>
                        <td class="px-4 py-2">{{ r.site_name }}<div class="text-xs text-gray-500">Site ID {{ r.site_id }}</div></td>
                        <td class="px-4 py-2">{{ r.month }}</td>
                        <td class="px-4 py-2 text-center">${{ r.amount }}</td>
                        <td class="px-4 py-2">
                            <div>{{ r.bank_name }}<span v-if="r.col_e" class="text-gray-400"> · {{ r.col_e }}</span></div>
                            <div class="text-xs text-gray-500">{{ r.destination }}</div>
                            <span v-if="r.missing" class="text-[10px] font-semibold uppercase bg-red-100 text-red-700 px-1 py-0.5 rounded" title="Missing bank account / BIC / PayNow proxy — fix in Site Edit ▸ Bank Details before exporting.">check bank</span>
                        </td>
                        <td v-if="showStatus" class="px-4 py-2 text-center">
                            <span v-if="r.is_paid" class="text-xs font-bold px-2 py-1 rounded-full bg-green-100 text-green-800">✓ Paid<template v-if="r.paid_date"> · {{ r.paid_date }}</template></span>
                            <span v-else class="text-gray-300">—</span>
                        </td>
                        <td class="px-4 py-2 text-right">
                            <button v-if="isOpen && !r.is_paid" @click="returnToPool(r.summary_ids[0], r.site_name)" :disabled="busy"
                                class="text-xs text-gray-500 hover:text-red-600 underline"
                                title="Remove from this settlement — the row goes back to the Site Summary. Only possible while Open.">Remove</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="bg-white rounded-md border px-4 py-8 text-center text-gray-400 mb-3">No rows in this settlement.</div>

        <!-- Actions -->
        <div class="bg-white rounded-md border p-4 mb-3">
            <h3 class="text-xs uppercase tracking-wide text-gray-500 mb-3">Actions</h3>

            <div class="flex flex-wrap items-center gap-2">
                <button @click="exportCimb" :disabled="busy"
                    class="inline-flex items-center gap-1 bg-white border border-gray-300 text-gray-700 rounded-md px-4 py-2 text-sm font-semibold hover:bg-gray-50 disabled:opacity-50"
                    title="Export the CIMB bulk-transfer .txt.">
                    <ArrowDownTrayIcon class="h-4 w-4" /> Export CIMB text
                </button>
                <button v-if="isOpen && settlement.count === 0" @click="voidSettlement" :disabled="busy"
                    class="bg-gray-100 text-gray-700 border rounded-md px-4 py-2 text-sm hover:bg-gray-200 disabled:opacity-50">Void (empty)</button>
            </div>

            <template v-if="!allDone && rows.length">
                <p class="mt-3 text-xs text-gray-500">Pay the sites (export the CIMB file), then tick the rows that were actually paid and mark them done. This records each payment in the settlement ledger.</p>
                <div class="mt-2">
                    <button @click="markDone" :disabled="busy || !selected.length"
                        class="bg-green-600 text-white rounded-md px-4 py-2 text-sm font-semibold hover:bg-green-700 disabled:opacity-50">✓ Mark selected as Paid ({{ selected.length }})</button>
                </div>
            </template>

            <p v-else-if="allDone" class="mt-3 text-xs text-green-700 font-medium">All sites in this settlement are paid.</p>

            <div v-if="isClosed" class="mt-3">
                <button @click="reopenSettlement" :disabled="busy"
                    class="inline-flex items-center gap-1 bg-yellow-500 text-white rounded-md px-4 py-2 text-sm font-semibold hover:bg-yellow-600 disabled:opacity-50">
                    <LockOpenIcon class="h-4 w-4" /> Undo close settlement
                </button>
            </div>

            <div v-if="msg" class="text-xs text-red-600 mt-2">{{ msg }}</div>
        </div>

        <!-- Audit trail -->
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
                    :class="actionBadge(l).cls">{{ actionBadge(l).label }}</span>
            </div>
            <div v-if="!logs.length" class="text-xs text-gray-400">No activity yet.</div>
        </div>
    </div>
</BreezeAuthenticatedLayout>
</template>
