<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { CheckCircleIcon, XCircleIcon } from '@heroicons/vue/20/solid';

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

// Machine ID → Operations Dashboard, deep-linked+auto-searched to that machine
// via the ?codes= param (opens in a new tab so the refund list is kept).
const opsDashboardUrl = (code) => code ? ('/vends/customers?codes=' + encodeURIComponent(code)) : null;

// Three system-validation checks mirrored from the ticket detail page's
// "System validation" badges (green = the favourable state, red = otherwise).
const validationChecks = (t) => [
    { ok: !!t.had_channel_error, label: t.had_channel_error ? 'Channel error detected' : 'No channel error' },
    { ok: !t.is_manual, label: t.is_manual ? 'Manual claim' : 'Auto-matched' },
    { ok: !t.already_refunded, label: t.already_refunded ? 'Already refunded' : 'Not yet refunded' },
];

// ---- column sorting (client-side, current page) ----
// Every header is clickable; clicking toggles asc/desc. Because the list is
// server-paginated, this reorders the rows currently on screen — quick triage
// without a round-trip. Empty/unmatched values always sink to the bottom.
const sortKey = ref('');
const sortAsc = ref(true);
function sortTable(key) {
    if (sortKey.value === key) { sortAsc.value = !sortAsc.value; }
    else { sortKey.value = key; sortAsc.value = true; }
}
const arrow = (key) => sortKey.value === key ? (sortAsc.value ? ' ▲' : ' ▼') : '';
// parse a money/number string ("1,234.50") to a Number, or null if not numeric
const toNum = (v) => {
    if (v === null || v === undefined || v === '') return null;
    const n = parseFloat(String(v).replace(/,/g, ''));
    return isNaN(n) ? null : n;
};
function sortVal(t, key) {
    switch (key) {
        case 'reference': return t.reference || '';
        case 'vend_code': return t.vend_code || '';
        case 'submitted': return t.created_at || '';           // ISO string sorts chronologically
        case 'paid': return toNum(t.paid_amount);
        case 'amount': return toNum(t.amount);
        case 'refund_method': return t.refund_method || '';
        case 'machine_rf_24h': return (t.machine_rf_24h ?? null);
        case 'requester_repeat': return t.requester_repeat ? 1 : 0;
        case 'dispense_attempted': return t.dispense_attempted === true ? 2 : (t.dispense_attempted === false ? 1 : null);
        case 'error_code': return t.error_code || '';
        case 'status': return t.status || '';
        case 'batch': return t.batch?.reference || '';
        // Refund Done? — completed > in progress > not started
        case 'done': return t.status === 'completed' ? 2 : (['approved', 'scheduled'].includes(t.status) ? 1 : 0);
        default: return '';
    }
}
const sortedRows = computed(() => {
    const rows = props.tickets?.data ? [...props.tickets.data] : [];
    if (!sortKey.value) return rows;
    const dir = sortAsc.value ? 1 : -1;
    const isEmpty = (v) => v === null || v === undefined || v === '';
    return rows.sort((a, b) => {
        const av = sortVal(a, sortKey.value);
        const bv = sortVal(b, sortKey.value);
        if (isEmpty(av) && isEmpty(bv)) return 0;
        if (isEmpty(av)) return 1;   // empties last, regardless of direction
        if (isEmpty(bv)) return -1;
        if (typeof av === 'number' && typeof bv === 'number') return (av - bv) * dir;
        return String(av).localeCompare(String(bv)) * dir;
    });
});
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
                        <th colspan="6" class="text-center px-4 py-2 border-b border-gray-200">Refund Request</th>
                        <th colspan="4" class="text-center px-4 py-2 border-b border-l border-gray-200 text-indigo-700">System self-checking</th>
                        <th colspan="3" class="text-center px-4 py-2 border-b border-l border-gray-200 text-teal-700">Refund Progress</th>
                    </tr>
                    <tr class="[&>th]:cursor-pointer [&>th]:select-none [&>th]:text-center [&>th]:px-4 [&>th]:py-2 [&>th]:whitespace-nowrap">
                        <th @click="sortTable('reference')" class="hover:text-gray-700">Refund ID{{ arrow('reference') }}</th>
                        <th @click="sortTable('vend_code')" class="hover:text-gray-700">Machine ID<br>Site Name{{ arrow('vend_code') }}</th>
                        <th @click="sortTable('submitted')" class="hover:text-gray-700">RF Submitted{{ arrow('submitted') }}</th>
                        <th @click="sortTable('paid')" class="hover:text-gray-700">Paid Amt<br>Pay Method{{ arrow('paid') }}</th>
                        <th @click="sortTable('amount')" class="border-l border-gray-200 hover:text-gray-700">Refund Amt{{ arrow('amount') }}</th>
                        <th @click="sortTable('refund_method')" class="hover:text-gray-700">Refund Method{{ arrow('refund_method') }}</th>
                        <th @click="sortTable('machine_rf_24h')" class="border-l border-gray-200 hover:text-gray-700">Machine L24h<br># of RF{{ arrow('machine_rf_24h') }}</th>
                        <th @click="sortTable('requester_repeat')" class="hover:text-gray-700">New / Repeat?{{ arrow('requester_repeat') }}</th>
                        <th @click="sortTable('dispense_attempted')" class="hover:text-gray-700">Prod Exit Sensor{{ arrow('dispense_attempted') }}</th>
                        <th @click="sortTable('error_code')" class="hover:text-gray-700">Error code{{ arrow('error_code') }}</th>
                        <th @click="sortTable('status')" class="border-l border-gray-200 hover:text-gray-700">Validation{{ arrow('status') }}</th>
                        <th @click="sortTable('batch')" class="hover:text-gray-700">Export for Bank Txf{{ arrow('batch') }}</th>
                        <th @click="sortTable('done')" class="hover:text-gray-700">Refund Done?{{ arrow('done') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="t in sortedRows" :key="t.id" class="border-t hover:bg-gray-50">
                        <td class="px-3 py-3">
                            <input type="checkbox" :disabled="!eligible(t)" :checked="selected.includes(t.id)" @change="toggleRow(t.id)"
                                :title="!eligible(t) ? 'Only Approved / Scheduled tickets can be batch-processed' : ''" />
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <a :href="'/refunds/' + t.id" target="_blank"
                                class="font-semibold text-teal-700 hover:underline" title="Open refund ticket in a new tab">{{ t.reference }}</a>
                        </td>
                        <td class="px-4 py-3">
                            <a v-if="t.vend_code" :href="opsDashboardUrl(t.vend_code)" target="_blank" @click.stop
                                class="font-medium text-teal-700 hover:underline" title="Open in Operations Dashboard">{{ t.vend_code }}</a>
                            <span v-else class="font-medium text-gray-800">—</span>
                            <br>
                            <span class="text-xs text-gray-500">{{ t.site_name || '—' }}</span>
                        </td>
                        <!-- RF submitted on top; matched txn date + elapsed delta stacked below -->
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="text-gray-700">{{ t.submitted_at }}</div>
                            <div class="text-xs mt-1">
                                <span v-if="t.matched" class="text-gray-500">Txn: {{ t.txn_datetime || '—' }}</span>
                                <span v-else class="text-amber-600 italic">pending match</span>
                            </div>
                            <div v-if="t.matched && t.txn_delta" class="text-xs text-gray-400 mt-0.5">Δ {{ t.txn_delta }}</div>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="text-gray-700">{{ t.matched && t.paid_amount ? '$' + t.paid_amount : '—' }}</div>
                            <div class="text-xs text-gray-500 mt-1">{{ t.matched ? (t.pay_method || t.payment_channel || '—') : '—' }}</div>
                        </td>
                        <td class="px-4 py-3 font-medium border-l border-gray-100">{{ t.matched ? '$' + t.amount : '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div>{{ t.refund_method }}</div>
                            <div v-if="t.refund_method === 'paynow' && t.payout_destination"
                                class="text-xs mt-1 cursor-help"
                                :class="t.paynow_duplicate ? 'text-red-600 font-semibold' : 'text-gray-500'"
                                v-tooltip="t.paynow_duplicate
                                    ? 'Same PayNow number used on another refund within 60 days — possible duplicate/abuse. Verify before paying.'
                                    : 'PayNow number the refund will be paid to.'">
                                {{ t.payout_destination }}
                            </div>
                        </td>
                        <!-- System self-checking -->
                        <td class="px-4 py-3 text-center border-l border-gray-100">
                            <span v-if="t.machine_rf_24h != null"
                                class="inline-flex items-center justify-center min-w-6 px-2 py-0.5 rounded-full text-xs font-semibold"
                                :class="t.machine_rf_24h > 3 ? 'bg-red-100 text-red-700' : (t.machine_rf_24h > 1 ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-600')"
                                v-tooltip="t.machine_rf_24h + ' refund request(s) on this machine in the 24h up to this submission.'">
                                {{ t.machine_rf_24h }}
                            </span>
                            <span v-else class="text-gray-300">—</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="text-xs font-semibold px-2 py-0.5 rounded-full"
                                :class="t.requester_repeat ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'"
                                v-tooltip="t.requester_repeat
                                    ? 'Repeat: this PayNow/PayPal account or email was used on an earlier refund request.'
                                    : 'New: first refund request seen from this requester.'">
                                {{ t.requester_repeat ? 'Repeat' : 'New' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <CheckCircleIcon v-if="t.dispense_attempted === true" class="h-5 w-5 text-green-600 inline"
                                v-tooltip="'Payment gateway reports the product WAS dispensed (exit sensor triggered).'" />
                            <XCircleIcon v-else-if="t.dispense_attempted === false" class="h-5 w-5 text-red-500 inline"
                                v-tooltip="'Payment gateway reports the product was NOT dispensed — supports the refund.'" />
                            <span v-else class="text-gray-300" v-tooltip="'No payment-gateway dispense reading (e.g. card terminal).'">—</span>
                        </td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            <span v-if="t.error_code" class="text-xs font-semibold text-amber-700"
                                v-tooltip="t.error_desc || ('Error code ' + t.error_code)">{{ t.error_code }}</span>
                            <span v-else class="text-gray-300">—</span>
                        </td>
                        <td class="px-4 py-3 border-l border-gray-100 text-center">
                            <span class="inline-block text-xs font-bold px-2 py-1 rounded-full whitespace-nowrap" :class="statusClass(t.status)">{{ statuses[t.status] || t.status }}</span>
                            <span class="flex items-center justify-center gap-0.5 mt-1.5">
                                <component :is="c.ok ? CheckCircleIcon : XCircleIcon" v-for="(c, i) in validationChecks(t)" :key="i"
                                    class="h-5 w-5" :class="c.ok ? 'text-green-600' : 'text-red-500'" :title="c.label" />
                            </span>
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
                    <tr v-if="!tickets.data.length"><td colspan="14" class="px-4 py-8 text-center text-gray-400">No refund tickets found.</td></tr>
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
