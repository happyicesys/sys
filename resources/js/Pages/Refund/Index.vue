<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';

const props = defineProps({
    tickets: { type: Object, required: true },
    counts: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Object, default: () => ({}) },
    banks: { type: Object, default: () => ({}) },
    defaultBank: { type: String, default: 'cimb' },
});

// ---- batch selection / bank export ----
const selected = ref([]);
const bank = ref(props.defaultBank || Object.keys(props.banks)[0] || 'cimb');
const exporting = ref(false);
const exportMsg = ref('');

// Same checkbox drives both actions: export (Approved · PayNow) and batch
// mark-completed (Approved / Scheduled). Backend filters each action again.
const eligible = (t) => ['approved', 'scheduled'].includes(t.status);
const completing = ref(false);
const completeMsg = ref('');
function completeBatch() {
    if (!selected.value.length) { completeMsg.value = 'Select at least one Approved ticket.'; return; }
    if (!confirm('Mark ' + selected.value.length + ' refund(s) as completed? The completion email is sent to each customer (or logged while emails are disabled).')) return;
    completing.value = true; completeMsg.value = '';
    router.post('/refunds/batch/complete', { ticket_ids: selected.value }, {
        preserveScroll: true,
        onError: (errors) => { completeMsg.value = errors.batch || Object.values(errors)[0] || 'Failed to complete.'; },
        onSuccess: () => { selected.value = []; },
        onFinish: () => { completing.value = false; },
    });
}
const eligibleIds = () => props.tickets.data.filter(eligible).map((t) => t.id);
const allSelected = computed(() => {
    const ids = eligibleIds();
    return ids.length > 0 && ids.every((id) => selected.value.includes(id));
});
function toggleAll(e) {
    selected.value = e.target.checked ? eligibleIds() : [];
}
function toggleRow(id) {
    const i = selected.value.indexOf(id);
    if (i === -1) selected.value.push(id); else selected.value.splice(i, 1);
}
async function exportBatch() {
    if (!selected.value.length) { exportMsg.value = 'Select at least one approved PayNow ticket.'; return; }
    exporting.value = true; exportMsg.value = '';
    try {
        const res = await window.axios.post('/refunds/batch/export',
            { ticket_ids: selected.value, bank: bank.value },
            { responseType: 'blob' });
        let fn = res.headers['x-filename'] || 'refund_batch.txt';
        const url = URL.createObjectURL(new Blob([res.data]));
        const a = document.createElement('a');
        a.href = url; a.download = fn; document.body.appendChild(a); a.click(); a.remove();
        URL.revokeObjectURL(url);
        selected.value = [];
        router.reload({ only: ['tickets', 'counts'] });
    } catch (e) {
        let msg = 'Export failed. Please try again.';
        if (e.response && e.response.status === 422) {
            msg = 'None of the selected tickets are eligible (must be Approved + PayNow).';
            // responseType is blob, so the JSON error body arrives as a Blob
            try {
                const body = JSON.parse(await e.response.data.text());
                if (body && body.message) msg = body.message;
            } catch (_) { /* keep fallback text */ }
        }
        exportMsg.value = msg;
    } finally {
        exporting.value = false;
    }
}

// status options as {id: key, value: label} for the tags MultiSelect
const statusOptions = ref(Object.entries(props.statuses).map(([id, value]) => ({ id, value })));

const filters = ref({
    search: props.filters.search || '',
    status: statusOptions.value.filter((o) => (props.filters.status || []).includes(o.id)),
    refund_method: props.filters.refund_method || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
});

function payload() {
    const p = {
        search: filters.value.search,
        refund_method: filters.value.refund_method,
        date_from: filters.value.date_from,
        date_to: filters.value.date_to,
    };
    // omit status when empty -> server applies the default (all except completed), shown as "All statuses"
    if (filters.value.status.length) p.status = filters.value.status.map((s) => s.id);
    return p;
}
function applyFilters() {
    router.get('/refunds', payload(), { preserveState: true, replace: true });
}
function clearFilters() {
    filters.value = { search: '', status: [], refund_method: '', date_from: '', date_to: '' };
    applyFilters();
}
function pickStatus(key) {
    // toggle: clicking the active chip clears back to "All"
    if (filters.value.status.length === 1 && filters.value.status[0].id === key) {
        filters.value.status = [];
    } else {
        const opt = statusOptions.value.find((o) => o.id === key);
        filters.value.status = opt ? [opt] : [];
    }
    applyFilters();
}

const statusClass = (s) => ({
    submitted: 'bg-yellow-100 text-yellow-800',
    auto_resolved: 'bg-cyan-100 text-cyan-800',
    verified: 'bg-blue-100 text-blue-800',
    rejected: 'bg-red-100 text-red-800',
    pending_approval: 'bg-indigo-100 text-indigo-800',
    approved: 'bg-indigo-100 text-indigo-800',
    pending_transfer_info: 'bg-orange-100 text-orange-800',
    scheduled: 'bg-violet-100 text-violet-800',
    completed: 'bg-green-100 text-green-800',
}[s] || 'bg-gray-100 text-gray-700');

const recClass = (r) => ({
    proceed: 'text-green-700', review: 'text-amber-700', reject: 'text-red-700',
}[r] || 'text-gray-500');
</script>

<template>
<Head title="Refunds" />
<BreezeAuthenticatedLayout>
    <template #header>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Refund Requests</h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
        <!-- status chips (quick single-status filter) -->
        <div class="flex flex-wrap gap-2 mb-3">
            <span v-for="(label, key) in statuses" :key="key"
                class="text-xs font-semibold px-3 py-1.5 rounded-full border bg-white cursor-pointer"
                :class="(filters.status.length === 1 && filters.status[0].id === key) ? 'border-teal-500 text-teal-700' : 'border-gray-200 text-gray-600'"
                @click="pickStatus(key)">
                {{ label }} <b class="text-gray-900">{{ counts[key] || 0 }}</b>
            </span>
        </div>

        <!-- filters -->
        <div class="bg-white rounded-md border p-3 mb-3 grid grid-cols-1 md:grid-cols-6 gap-2 items-start">
            <input v-model="filters.search" placeholder="Ref / machine / email" class="border rounded-md px-3 py-2 text-sm md:col-span-2" @keyup.enter="applyFilters" />
            <div class="md:col-span-2">
                <MultiSelect
                    v-model="filters.status"
                    :options="statusOptions"
                    trackBy="id"
                    valueProp="id"
                    label="value"
                    mode="tags"
                    placeholder="All statuses"
                    open-direction="bottom"
                />
            </div>
            <input type="date" v-model="filters.date_from" class="border rounded-md px-3 py-2 text-sm" />
            <input type="date" v-model="filters.date_to" class="border rounded-md px-3 py-2 text-sm" />
            <select v-model="filters.refund_method" class="border rounded-md px-3 py-2 text-sm">
                <option value="">All methods</option>
                <option value="paynow">PayNow</option>
                <option value="paypal">PayPal</option>
                <option value="nayax_auto">Nayax (auto)</option>
                <option value="none">None</option>
            </select>
            <div class="flex gap-2">
                <button @click="applyFilters" class="bg-teal-600 text-white rounded-md px-4 py-2 text-sm font-medium hover:bg-teal-700">Search</button>
                <button @click="clearFilters" class="bg-gray-100 text-gray-700 rounded-md px-3 py-2 text-sm border">Clear</button>
            </div>
        </div>

        <!-- batch toolbar: export bank file + mark completed (same selection) -->
        <div class="bg-teal-50 border border-teal-200 rounded-md px-4 py-3 mb-3 flex flex-wrap items-center gap-3">
            <span class="text-sm font-semibold text-teal-800">{{ selected.length }} selected</span>
            <span class="text-xs text-gray-500">Select Approved tickets — export a bank bulk file (PayNow) or mark refunds done in batch.</span>
            <div class="flex-1"></div>
            <!-- only show the bank picker once there is more than one template -->
            <select v-if="Object.keys(banks).length > 1" v-model="bank" class="border rounded-md px-3 py-2 text-sm bg-white">
                <option v-for="(label, key) in banks" :key="key" :value="key">{{ label }}</option>
            </select>
            <button @click="exportBatch" :disabled="exporting || !selected.length"
                class="bg-teal-600 text-white rounded-md px-4 py-2 text-sm font-semibold hover:bg-teal-700 disabled:opacity-50"
                :title="banks[bank] || ''">
                {{ exporting ? 'Exporting…' : '⬇ Export ' + bank.toUpperCase() + ' Bulk' }}
            </button>
            <button @click="completeBatch" :disabled="completing || !selected.length"
                class="bg-green-600 text-white rounded-md px-4 py-2 text-sm font-semibold hover:bg-green-700 disabled:opacity-50"
                title="Mark the selected refunds as completed and send the completion email">
                {{ completing ? 'Completing…' : '✓ Mark Completed' }}
            </button>
            <span v-if="exportMsg" class="w-full text-xs text-red-600">{{ exportMsg }}</span>
            <span v-if="completeMsg" class="w-full text-xs text-red-600">{{ completeMsg }}</span>
        </div>

        <!-- table -->
        <div class="bg-white rounded-md border overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-3 py-2 w-8" rowspan="2"><input type="checkbox" :checked="allSelected" @change="toggleAll" /></th>
                        <th colspan="8" class="text-left px-4 py-2 border-b border-gray-200">Refund Request</th>
                        <th colspan="3" class="text-left px-4 py-2 border-b border-l border-gray-200 text-teal-700">Refund Progress</th>
                    </tr>
                    <tr>
                        <th class="text-left px-4 py-2">Refund ID</th>
                        <th class="text-left px-4 py-2">Machine ID</th>
                        <th class="text-left px-4 py-2">Submitted</th>
                        <th class="text-left px-4 py-2">Txn Date</th>
                        <th class="text-left px-4 py-2">Paid Amt</th>
                        <th class="text-left px-4 py-2">Pay Method</th>
                        <th class="text-left px-4 py-2">Refund Amt</th>
                        <th class="text-left px-4 py-2">Refund Method</th>
                        <th class="text-left px-4 py-2 border-l border-gray-200">Validation</th>
                        <th class="text-left px-4 py-2">Export for Bank Txf</th>
                        <th class="text-left px-4 py-2">Refund Done?</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="t in tickets.data" :key="t.id" class="border-t hover:bg-gray-50 cursor-pointer" @click="router.get('/refunds/' + t.id)">
                        <td class="px-3 py-3" @click.stop>
                            <input type="checkbox" :disabled="!eligible(t)" :checked="selected.includes(t.id)" @change="toggleRow(t.id)"
                                :title="!eligible(t) ? 'Only Approved / Scheduled tickets can be batch-processed' : ''" />
                        </td>
                        <td class="px-4 py-3 font-semibold text-teal-700 whitespace-nowrap">{{ t.reference }}</td>
                        <td class="px-4 py-3">{{ t.vend_code }}</td>
                        <td class="px-4 py-3 text-gray-600 whitespace-nowrap">{{ t.submitted_at }}</td>
                        <!-- txn details only once matched; manual claims stay blank until Ops matches the Order ID -->
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span v-if="t.matched" class="text-gray-600">{{ t.txn_datetime || '—' }}</span>
                            <span v-else class="text-amber-600 text-xs italic">pending match</span>
                        </td>
                        <td class="px-4 py-3">{{ t.matched && t.paid_amount ? '$' + t.paid_amount : '—' }}</td>
                        <td class="px-4 py-3">{{ t.matched ? (t.pay_method || t.payment_channel || '—') : '—' }}</td>
                        <td class="px-4 py-3 font-medium">{{ t.matched ? '$' + t.amount : '—' }}</td>
                        <td class="px-4 py-3">{{ t.refund_method }}</td>
                        <td class="px-4 py-3 border-l border-gray-100">
                            <span class="text-xs font-bold px-2 py-1 rounded-full whitespace-nowrap" :class="statusClass(t.status)">{{ statuses[t.status] || t.status }}</span>
                            <span class="block mt-1 text-[11px] font-semibold capitalize" :class="recClass(t.recommendation)">{{ t.recommendation }}</span>
                        </td>
                        <td class="px-4 py-3" @click.stop>
                            <a v-if="t.batch" :href="'/refunds/batch/' + t.batch.id + '/download'"
                                class="text-teal-700 text-xs font-semibold hover:underline whitespace-nowrap"
                                :title="t.batch.filename || ''">⬇ {{ t.batch.reference }}</a>
                            <span v-else class="text-gray-300">—</span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span v-if="t.status === 'completed'" class="text-xs font-bold px-2 py-1 rounded-full bg-green-100 text-green-800">✓ Completed<template v-if="t.completed_at"> · {{ t.completed_at }}</template></span>
                            <span v-else-if="['approved', 'scheduled'].includes(t.status)" class="text-xs font-bold px-2 py-1 rounded-full bg-amber-100 text-amber-800">In progress</span>
                            <span v-else class="text-gray-300">—</span>
                        </td>
                    </tr>
                    <tr v-if="!tickets.data.length"><td colspan="12" class="px-4 py-8 text-center text-gray-400">No refund tickets found.</td></tr>
                </tbody>
            </table>
        </div>

        <!-- pagination -->
        <div class="flex items-center justify-between mt-3 text-sm text-gray-600">
            <span>Showing {{ tickets.from || 0 }}–{{ tickets.to || 0 }} of {{ tickets.total }}</span>
            <div class="flex gap-1">
                <template v-for="(l, i) in tickets.links" :key="i">
                    <Link v-if="l.url" :href="l.url" v-html="l.label" preserve-scroll
                        class="px-3 py-1.5 rounded border text-sm"
                        :class="l.active ? 'bg-teal-600 text-white border-teal-600' : 'bg-white text-gray-600'" />
                    <span v-else v-html="l.label" class="px-3 py-1.5 rounded border text-sm text-gray-300"></span>
                </template>
            </div>
        </div>
    </div>
</BreezeAuthenticatedLayout>
</template>
