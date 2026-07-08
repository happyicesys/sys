<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { CheckCircleIcon, XCircleIcon } from '@heroicons/vue/20/solid';

const page = usePage();
const can = (p) => (page.props.auth?.roles || []).includes('superadmin') || (page.props.auth?.permissions || []).includes(p);

// "Mark done" moved here from the ticket page: an approved refund is closed out
// straight from the list once the payout has gone through. Posts to the same
// /complete endpoint (guarded by `update refunds`), then reloads in place.
const completing = ref(null); // id being completed, to disable just that button
function markDone(t) {
    if (!confirm('Mark ' + t.reference + ' as refund done? This emails the customer their completion notice.')) return;
    completing.value = t.id;
    router.post('/refunds/' + t.id + '/complete', {}, {
        preserveScroll: true,
        onFinish: () => { completing.value = null; },
    });
}

const props = defineProps({
    tickets: { type: Object, required: true },
    counts: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Object, default: () => ({}) },
    banks: { type: Object, default: () => ({}) },
    defaultBank: { type: String, default: 'cimb' },
});

// ---- batch selection / push to settlement ----
const selected = ref([]);

// The checkbox drives "Push to Settlement": only APPROVED tickets (PayNow or
// PayPal) can be pushed. The backend re-filters and routes each into its day's
// open settlement per payout group.
const eligible = (t) => t.status === 'approved';
const pushing = ref(false);
const pushMsg = ref('');
function pushToSettlement() {
    if (!selected.value.length) { pushMsg.value = 'Select at least one Approved ticket.'; return; }
    if (!confirm('Push ' + selected.value.length + ' approved refund(s) into their Refund Settlement?')) return;
    pushing.value = true; pushMsg.value = '';
    router.post('/refund-settlements/push', { ticket_ids: selected.value }, {
        preserveScroll: true,
        onError: (errors) => { pushMsg.value = errors.settlement || Object.values(errors)[0] || 'Failed to push.'; },
        onSuccess: () => { selected.value = []; },
        onFinish: () => { pushing.value = false; },
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
    submitted: 'bg-yellow-100 text-yellow-800',   // Received
    auto_resolved: 'bg-purple-100 text-purple-800',
    rejected: 'bg-red-100 text-red-800',
    approved: 'bg-green-100 text-green-800',
    completed: 'text-gray-500',                    // Completed = no colour
    dropped: 'bg-white text-gray-500 border border-gray-300',  // temporarily white
}[s] || 'bg-gray-100 text-gray-700');

// Machine ID → Operations Dashboard, deep-linked+auto-searched to that machine
// via the ?codes= param (opens in a new tab so the refund list is kept).
const opsDashboardUrl = (code) => code ? ('/vends/customers?codes=' + encodeURIComponent(code)) : null;

// Three system-validation checks mirrored from the ticket detail page's
// "System validation" badges (green = the favourable state, red = otherwise).
const validationChecks = (t) => [
    { ok: !!t.had_channel_error, label: t.had_channel_error
        ? 'Channel error: a vend/channel error was detected on the matched transaction — this supports the refund.'
        : 'Channel error: no vend/channel error detected on the matched transaction.' },
    { ok: !t.is_manual, label: t.is_manual
        ? 'Match type: manually claimed — the transaction was matched by hand, not auto-verified.'
        : 'Match type: auto-matched — the system matched this request to a transaction automatically.' },
    { ok: !t.already_refunded, label: t.already_refunded
        ? 'Refund status: already refunded — do NOT pay again.'
        : 'Refund status: not yet refunded — safe to process.' },
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
        case 'repeat_flag': return t.repeat_flag ? 1 : 0;
        case 'product_drop_sensor': return t.product_drop_sensor === true ? 2 : (t.product_drop_sensor === false ? 1 : null);
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

        <!-- batch toolbar: push selected Approved tickets into their Refund Settlement -->
        <!-- <div class="bg-teal-50 border border-teal-200 rounded-md px-4 py-3 mb-3 flex flex-wrap items-center gap-3">
            <span class="text-sm font-semibold text-teal-800">{{ selected.length }} selected</span>
            <span class="text-xs text-gray-500">Select Approved tickets and push them into their Refund Settlement. The CIMB (PayNow) and Excel (PayPal) files are exported from the settlement page.</span>
            <div class="flex-1"></div>
            <button @click="pushToSettlement" :disabled="pushing || !selected.length"
                class="bg-teal-600 text-white rounded-md px-4 py-2 text-sm font-semibold hover:bg-teal-700 disabled:opacity-50">
                {{ pushing ? 'Pushing…' : '➡ Push to Settlement' }}
            </button>
            <a href="/refund-settlements" class="text-xs text-teal-700 underline whitespace-nowrap">Open Refund Settlement →</a>
            <span v-if="pushMsg" class="w-full text-xs text-red-600">{{ pushMsg }}</span>
        </div> -->

        <!-- table -->
        <div class="bg-white rounded-md border overflow-x-auto">
            <table class="compact-table min-w-full text-xs">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-3 py-2 w-8" rowspan="2"><input type="checkbox" :checked="allSelected" @change="toggleAll" title="Select all Approved tickets on this page" /></th>
                        <th colspan="7" class="text-center px-4 py-2 border-b border-gray-200">Refund Request</th>
                        <th colspan="4" class="text-center px-4 py-2 border-b border-l border-gray-200 text-indigo-700">System self-checking</th>
                        <th colspan="3" class="text-center px-4 py-2 border-b border-l border-gray-200 text-teal-700">Refund Progress</th>
                    </tr>
                    <tr class="[&>th]:cursor-pointer [&>th]:select-none [&>th]:text-center [&>th]:px-4 [&>th]:py-2 [&>th]:whitespace-nowrap">
                        <th @click="sortTable('reference')" class="hover:text-gray-700">Refund ID{{ arrow('reference') }}</th>
                        <th @click="sortTable('vend_code')" class="hover:text-gray-700">Machine ID<br>Site Name{{ arrow('vend_code') }}</th>
                        <th @click="sortTable('submitted')" class="hover:text-gray-700 whitespace-nowrap">
                            <div>RF Submitted{{ arrow('submitted') }}</div>
                            <div class="text-[11px] font-normal text-gray-400">Transaction</div>
                            <div class="text-[11px] font-normal text-gray-400">Delta xD, xH, xxm</div>
                        </th>
                        <th class="border-l border-gray-200 max-w-[140px]">
                            <div>Channel</div>
                            <div class="text-[11px] font-normal text-gray-400">Product Name</div>
                        </th>
                        <th @click="sortTable('paid')" class="hover:text-gray-700">Paid Amt<br>Pay Method{{ arrow('paid') }}</th>
                        <th @click="sortTable('amount')" class="border-l border-gray-200 hover:text-gray-700">Refund Amt{{ arrow('amount') }}</th>
                        <th @click="sortTable('refund_method')" class="hover:text-gray-700">Refund Method{{ arrow('refund_method') }}</th>
                        <th @click="sortTable('machine_rf_24h')" class="border-l border-gray-200 hover:text-gray-700">Machine L24h<br># of RF{{ arrow('machine_rf_24h') }}</th>
                        <th @click="sortTable('repeat_flag')" class="hover:text-gray-700">New / Repeat?{{ arrow('repeat_flag') }}</th>
                        <th @click="sortTable('product_drop_sensor')" class="hover:text-gray-700">Prod Exit Sensor{{ arrow('product_drop_sensor') }}</th>
                        <th @click="sortTable('error_code')" class="hover:text-gray-700">Error code{{ arrow('error_code') }}</th>
                        <th @click="sortTable('status')" class="border-l border-gray-200 hover:text-gray-700">Validation{{ arrow('status') }}</th>
                        <th @click="sortTable('batch')" class="hover:text-gray-700">Send to Settlement{{ arrow('batch') }}</th>
                        <th @click="sortTable('done')" class="hover:text-gray-700">Refund Done?{{ arrow('done') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="t in sortedRows" :key="t.id" class="border-t hover:bg-gray-50" :class="t.is_dropped ? 'opacity-60' : ''">
                        <td class="px-3 py-3">
                            <input type="checkbox" :disabled="!eligible(t)" :checked="selected.includes(t.id)" @change="toggleRow(t.id)"
                                :title="!eligible(t) ? 'Only Approved tickets can be pushed to settlement' : ''" />
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <a :href="'/refunds/' + t.id" target="_blank"
                                class="font-semibold text-teal-700 hover:underline" :class="t.is_dropped ? 'line-through text-gray-400' : ''"
                                :title="t.is_dropped ? 'Dropped / closed (e.g. double submission)' : 'Open refund ticket in a new tab'">{{ t.reference }}</a>
                            <span v-if="t.is_dropped" class="block text-[10px] font-semibold uppercase tracking-wide text-gray-400">dropped</span>
                        </td>
                        <td class="px-4 py-3">
                            <a v-if="t.vend_code" :href="opsDashboardUrl(t.vend_code)" target="_blank" @click.stop
                                class="font-medium text-teal-700 hover:underline" title="Open in Operations Dashboard">{{ t.vend_code }}</a>
                            <span v-else class="font-medium text-gray-800">—</span>
                            <br>
                            <span class="text-xs text-gray-600">{{ t.site_name || '—' }}</span>
                        </td>
                        <!-- RF submitted on top; matched txn date + elapsed delta stacked below -->
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="text-gray-700">{{ t.submitted_at }}</div>
                            <div class="text-xs mt-1">
                                <a v-if="t.matched && t.txn_link" :href="t.txn_link" target="_blank" @click.stop
                                    class="text-teal-600 hover:text-teal-800 hover:underline">Txn: {{ t.txn_datetime || '—' }}</a>
                                <span v-else-if="t.matched" class="text-gray-600">Txn: {{ t.txn_datetime || '—' }}</span>
                                <span v-else class="text-amber-600 italic">pending match</span>
                            </div>
                            <div v-if="t.matched && t.txn_delta" class="text-xs text-gray-500 mt-0.5">Δ {{ t.txn_delta }}</div>
                        </td>
                        <!-- Customer-flagged affected items: channel + product name, one row each -->
                        <td class="px-3 py-3 align-middle border-l border-gray-100 text-center">
                            <div v-if="t.affected_items && t.affected_items.length" class="space-y-1 max-w-[140px] mx-auto">
                                <div v-for="(it, i) in t.affected_items" :key="i" class="text-xs leading-tight">
                                    <span class="font-medium text-gray-700">{{ it.channel || '—' }}</span>
                                    <span class="block text-gray-500 break-words mt-1" :title="it.product_name || ''">{{ it.product_name || '—' }}</span>
                                </div>
                            </div>
                            <span v-else class="text-xs text-gray-400">—</span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div class="text-gray-700">{{ t.matched && t.paid_amount ? '$' + t.paid_amount : '—' }}</div>
                            <div class="text-xs text-gray-600 mt-1">
                                {{ t.matched ? (t.pay_method || t.payment_channel || '—') : '—' }}
                                <span v-if="t.matched && t.pay_provider" class="block text-gray-500">({{ t.pay_provider }})</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 font-medium border-l border-gray-100">{{ t.matched ? '$' + t.amount : '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            <div>{{ t.refund_method }}</div>
                            <div v-if="t.refund_method === 'paynow' && t.payout_destination"
                                class="text-xs mt-1 cursor-help"
                                :class="t.paynow_duplicate ? 'text-red-600 font-semibold' : 'text-gray-600'"
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
                                :class="t.repeat_flag ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'"
                                v-tooltip="t.repeat_flag
                                    ? ('Repeat: this order + channel was already claimed under ' + (t.repeat_ref || 'an earlier request') + '. Re-validate before payout to avoid a double refund.')
                                    : 'New: this order + channel has not been claimed before.'">
                                {{ t.repeat_flag ? 'Repeat' : 'New' }}
                            </span>
                            <span v-if="t.repeat_flag && t.repeat_ref"
                                class="block text-[10px] font-semibold text-red-500 mt-0.5"
                                v-tooltip="'Duplicates ' + t.repeat_ref">↺ {{ t.repeat_ref }}</span>
                        </td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            <span v-if="t.product_drop_sensor === true" class="text-xs font-semibold text-green-700"
                                v-tooltip="'Product Drop Sensor was Enabled on the machine at the time of the transaction.'">Enabled</span>
                            <span v-else-if="t.product_drop_sensor === false" class="text-xs font-semibold text-gray-500"
                                v-tooltip="'Product Drop Sensor was Disabled on the machine at the time of the transaction.'">Disabled</span>
                            <span v-else class="text-gray-300" v-tooltip="'No Product Drop Sensor reading recorded for this transaction.'">—</span>
                        </td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            <span v-if="t.error_code" class="text-xs font-semibold text-amber-700"
                                v-tooltip="t.error_desc || ('Error code ' + t.error_code)">{{ t.error_code }}</span>
                            <span v-else class="text-gray-300">—</span>
                        </td>
                        <td class="px-4 py-3 border-l border-gray-100 text-center">
                            <span class="inline-block text-xs font-bold px-2 py-1 rounded-full whitespace-nowrap" :class="statusClass(t.is_dropped ? 'dropped' : t.status)">{{ t.is_dropped ? 'Dropped' : (statuses[t.status] || t.status) }}</span>
                            <span class="flex items-center justify-center gap-0.5 mt-1.5">
                                <component :is="c.ok ? CheckCircleIcon : XCircleIcon" v-for="(c, i) in validationChecks(t)" :key="i"
                                    class="h-5 w-5" :class="c.ok ? 'text-green-600' : 'text-red-500'" v-tooltip="c.label" />
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center" @click.stop>
                            <a v-if="t.batch && t.batch.is_settlement" :href="'/refund-settlements/' + t.batch.id" target="_blank"
                                class="text-teal-700 text-xs font-semibold hover:underline whitespace-nowrap"
                                title="Open the Refund Settlement">{{ t.batch.reference }}</a>
                            <a v-else-if="t.batch" :href="'/refunds/batch/' + t.batch.id + '/download'"
                                class="text-teal-700 text-xs font-semibold hover:underline whitespace-nowrap"
                                :title="t.batch.filename || ''">⬇ {{ t.batch.reference }}</a>
                            <span v-else class="text-gray-300">—</span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-center" @click.stop>
                            <span v-if="t.status === 'completed'" class="text-xs font-bold px-2 py-1 rounded-full bg-green-100 text-green-800">✓ Completed<template v-if="t.completed_at"> · {{ t.completed_at }}</template></span>
                            <template v-else-if="['approved', 'scheduled'].includes(t.status)">
                                <button v-if="can('update refunds')" @click="markDone(t)" :disabled="completing === t.id"
                                    class="text-xs font-semibold px-3 py-1.5 rounded bg-green-600 text-white hover:bg-green-700 disabled:opacity-50 whitespace-nowrap"
                                    title="Mark this approved refund as paid / done">✓ Mark done</button>
                                <span v-else class="text-xs font-bold px-2 py-1 rounded-full bg-amber-100 text-amber-800">In progress</span>
                            </template>
                            <span v-else class="text-gray-300">—</span>
                        </td>
                    </tr>
                    <tr v-if="!tickets.data.length"><td colspan="15" class="px-4 py-8 text-center text-gray-400">No refund tickets found.</td></tr>
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

<style scoped>
/* Compact the Refund Request list table only. Scoped to this page + higher
   specificity than the cells' single-class Tailwind padding, so it wins the
   px-4/py-3 without !important and without touching any other page. */
.compact-table th,
.compact-table td {
    padding-top: 0.3rem;
    padding-bottom: 0.3rem;
    padding-left: 0.4rem;
    padding-right: 0.4rem;
    line-height: 1.2;
}
</style>
