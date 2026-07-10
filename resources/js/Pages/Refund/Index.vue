<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import Button from '@/Components/Button.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { CheckCircleIcon, XCircleIcon, MagnifyingGlassIcon, ArrowDownTrayIcon } from '@heroicons/vue/20/solid';

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
    const ids = paynowSelectedIds.value;
    if (!ids.length) { pushMsg.value = 'Select at least one Approved PayNow ticket.'; return; }
    if (!confirm('Push ' + ids.length + ' approved PayNow refund(s) into their Refund Settlement?')) return;
    pushing.value = true; pushMsg.value = '';
    router.post('/refund-settlements/push', { ticket_ids: ids }, {
        preserveScroll: true,
        onError: (errors) => { pushMsg.value = errors.settlement || Object.values(errors)[0] || 'Failed to push.'; },
        // Clear only the pushed (PayNow) tickets — any PayPal ones stay selected.
        onSuccess: () => { selected.value = selected.value.filter((id) => !ids.includes(id)); },
        onFinish: () => { pushing.value = false; },
    });
}
// PayPal refunds are paid manually — no settlement. Admin marks them done here,
// which completes the ticket and emails the customer (backend re-checks status).
const marking = ref(false);
function markDonePaypal() {
    const ids = paypalSelectedIds.value;
    if (!ids.length) { pushMsg.value = 'Select at least one Approved PayPal ticket.'; return; }
    if (!confirm('Mark ' + ids.length + ' PayPal refund(s) as done? This emails each customer their completion notice.')) return;
    marking.value = true; pushMsg.value = '';
    router.post('/refunds/batch/complete', { ticket_ids: ids }, {
        preserveScroll: true,
        onError: (errors) => { pushMsg.value = errors.batch || Object.values(errors)[0] || 'Failed to mark done.'; },
        // Clear only the marked (PayPal) tickets — any PayNow ones stay selected.
        onSuccess: () => { selected.value = selected.value.filter((id) => !ids.includes(id)); },
        onFinish: () => { marking.value = false; },
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
// Method-aware toolbar: PayNow → push to settlement; PayPal → mark done here.
const selectedTickets = computed(() => props.tickets.data.filter((t) => selected.value.includes(t.id)));
const selMethods = computed(() => [...new Set(selectedTickets.value.map((t) => t.refund_method))]);
const allPaynow = computed(() => selMethods.value.length === 1 && selMethods.value[0] === 'paynow');
const allPaypal = computed(() => selMethods.value.length === 1 && selMethods.value[0] === 'paypal');
const mixedMethods = computed(() => selMethods.value.length > 1);
// Method-specific subsets of the current selection, so a mixed selection can run
// each action on its own group (PayNow → settlement, PayPal → mark done here).
const paynowSelectedIds = computed(() => selectedTickets.value.filter((t) => t.refund_method === 'paynow').map((t) => t.id));
const paypalSelectedIds = computed(() => selectedTickets.value.filter((t) => t.refund_method === 'paypal').map((t) => t.id));

// status options as {id: key, value: label} for the tags MultiSelect
const statusOptions = ref(Object.entries(props.statuses).map(([id, value]) => ({ id, value })));

// Per-page selector — same options + component (MultiSelect) as Vend/CustomerIndex.
const numberPerPageOptions = ref([
    { id: 25, value: 25 },
    { id: 50, value: 50 },
    { id: 100, value: 100 },
    { id: 200, value: 200 },
    { id: 500, value: 500 },
    { id: 'All', value: 'All' },
]);
const fallbackPerPage = numberPerPageOptions.value.find((o) => o.id === 50) || numberPerPageOptions.value[0];
const defaultPerPage = numberPerPageOptions.value.find((o) => String(o.id) === String(props.filters.numberPerPage)) || fallbackPerPage;

const filters = ref({
    search: props.filters.search || '',
    status: statusOptions.value.filter((o) => (props.filters.status || []).includes(o.id)),
    refund_method: props.filters.refund_method || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
    site_name: props.filters.site_name || '',
    channel: props.filters.channel || '',
    product: props.filters.product || '',
    paid_min: props.filters.paid_min || '',
    paid_max: props.filters.paid_max || '',
    repeat: props.filters.repeat || '',
    product_drop_sensor: props.filters.product_drop_sensor || '',
    error_code: props.filters.error_code || '',
    settlement_ref: props.filters.settlement_ref || '',
    refund_done: props.filters.refund_done || '',
    sent_settlement: props.filters.sent_settlement || '',
    numberPerPage: defaultPerPage,
});

function payload() {
    const p = {
        search: filters.value.search,
        refund_method: filters.value.refund_method,
        date_from: filters.value.date_from,
        date_to: filters.value.date_to,
        site_name: filters.value.site_name,
        channel: filters.value.channel,
        product: filters.value.product,
        paid_min: filters.value.paid_min,
        paid_max: filters.value.paid_max,
        repeat: filters.value.repeat,
        product_drop_sensor: filters.value.product_drop_sensor,
        error_code: filters.value.error_code,
        settlement_ref: filters.value.settlement_ref,
        refund_done: filters.value.refund_done,
        sent_settlement: filters.value.sent_settlement,
        numberPerPage: filters.value.numberPerPage?.id ?? 50,
    };
    // omit status when empty -> server applies the default (all except completed), shown as "All statuses"
    if (filters.value.status.length) p.status = filters.value.status.map((s) => s.id);
    return p;
}
function applyFilters() {
    router.get('/refunds', payload(), { preserveState: true, replace: true });
}
function clearFilters() {
    filters.value = {
        search: '', status: [], refund_method: '', date_from: '', date_to: '',
        site_name: '', channel: '', product: '', paid_min: '', paid_max: '',
        repeat: '', product_drop_sensor: '', error_code: '', settlement_ref: '', refund_done: '', sent_settlement: '',
        numberPerPage: fallbackPerPage,
    };
    applyFilters();
}
// Export the CURRENT filtered list to Excel. Plain browser navigation (not
// Inertia) so the .xlsx file download is handled by the browser. The backend
// exports the full filtered set regardless of the per-page value.
function exportExcel() {
    const params = new URLSearchParams();
    Object.entries(payload()).forEach(([k, v]) => {
        if (Array.isArray(v)) v.forEach((x) => params.append(k + '[]', x));
        else if (v !== '' && v !== null && v !== undefined) params.append(k, v);
    });
    window.location.href = '/refunds/export?' + params.toString();
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
    approved: 'bg-green-100 text-green-800',       // also used for `scheduled` (in a settlement)
    completed: 'text-gray-500',                    // Completed = no colour
    dropped: 'bg-white text-gray-500 border border-gray-300',  // temporarily white
}[s] || 'bg-gray-100 text-gray-700');

// Machine ID → Operations Dashboard, deep-linked+auto-searched to that machine
// via the ?codes= param (opens in a new tab so the refund list is kept).
const opsDashboardUrl = (code) => code ? ('/vends/customers?codes=' + encodeURIComponent(code)) : null;

// Split a settlement ref (RST-260710-HIPL-01) after the date onto two lines so the
// "Send to Settlement" column stays narrow: "RST-260710" / "HIPL-01".
const settlementRefTop = (r) => (r || '').split('-').slice(0, 2).join('-');
const settlementRefBottom = (r) => (r || '').split('-').slice(2).join('-');

// Split a pay-method label like "Omise (Paynow)" into a main part and the
// parenthetical, so the "(Paynow)" bracket can sit on its own line.
const payMethodParts = (label) => {
    const s = String(label ?? '').trim();
    const m = s.match(/^(.*?)\s*(\([^)]*\))\s*$/);
    return m ? { main: m[1], paren: m[2] } : { main: s, paren: '' };
};

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
        case 'amount': return toNum(t.final_refund_overridden ? t.final_refund_amount : t.amount);
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
                class="text-xs font-semibold px-3 py-1.5 rounded-full border border-gray-200 cursor-pointer transition"
                :class="[statusClass(key), (filters.status.length === 1 && filters.status[0].id === key) ? 'ring-2 ring-offset-1 ring-teal-500' : 'opacity-80 hover:opacity-100']"
                @click="pickStatus(key)">
                {{ label }} <b>{{ counts[key] || 0 }}</b>
            </span>
        </div>

        <!-- filters (standardised to match Vend/CustomerIndex: labelled grid,
             shared input styling, Search/Clear buttons, per-page selector) -->
        <div class="bg-white rounded-md border my-3 px-3 py-3">
            <div class="grid grid-cols-1 md:grid-cols-5 gap-2 items-start">
                <div>
                    <label class="block text-xs font-medium text-gray-700">Search</label>
                    <div class="mt-1">
                        <input v-model="filters.search" type="text" placeholder="Ref / machine / email"
                            class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md"
                            @keyup.enter="applyFilters" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Status</label>
                    <MultiSelect
                        v-model="filters.status"
                        :options="statusOptions"
                        trackBy="id"
                        valueProp="id"
                        label="value"
                        mode="tags"
                        placeholder="All statuses"
                        open-direction="bottom"
                        class="mt-1"
                    />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Date From</label>
                    <div class="mt-1">
                        <input type="date" v-model="filters.date_from"
                            class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Date To</label>
                    <div class="mt-1">
                        <input type="date" v-model="filters.date_to"
                            class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Refund Method</label>
                    <div class="mt-1">
                        <select v-model="filters.refund_method"
                            class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md">
                            <option value="">All methods</option>
                            <option value="paynow">PayNow</option>
                            <option value="paypal">PayPal</option>
                            <option value="nayax_auto">Nayax (auto)</option>
                            <option value="none">None</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Site Name</label>
                    <div class="mt-1">
                        <input v-model="filters.site_name" type="text" placeholder="Site / customer name"
                            class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md"
                            @keyup.enter="applyFilters" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Channel</label>
                    <div class="mt-1">
                        <input v-model="filters.channel" type="text" placeholder="Channel code"
                            class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md"
                            @keyup.enter="applyFilters" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Product</label>
                    <div class="mt-1">
                        <input v-model="filters.product" type="text" placeholder="Product name"
                            class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md"
                            @keyup.enter="applyFilters" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Paid Amount ($)</label>
                    <div class="mt-1 flex items-center space-x-1">
                        <input v-model="filters.paid_min" type="number" step="0.01" min="0" placeholder="Min"
                            class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md"
                            @keyup.enter="applyFilters" />
                        <span class="text-gray-400">–</span>
                        <input v-model="filters.paid_max" type="number" step="0.01" min="0" placeholder="Max"
                            class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md"
                            @keyup.enter="applyFilters" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">New / Repeat</label>
                    <div class="mt-1">
                        <select v-model="filters.repeat"
                            class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md">
                            <option value="">All</option>
                            <option value="new">New</option>
                            <option value="repeat">Repeat</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Prod Exit Sensor</label>
                    <div class="mt-1">
                        <select v-model="filters.product_drop_sensor"
                            class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md">
                            <option value="">All</option>
                            <option value="enabled">Enabled</option>
                            <option value="disabled">Disabled</option>
                            <option value="unknown">Unknown / none</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Error Code</label>
                    <div class="mt-1">
                        <input v-model="filters.error_code" type="text" placeholder="Error code"
                            class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md"
                            @keyup.enter="applyFilters" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Settlement Ref</label>
                    <div class="mt-1">
                        <input v-model="filters.settlement_ref" type="text" placeholder="Settlement / batch ref"
                            class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md"
                            @keyup.enter="applyFilters" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Refund Done</label>
                    <div class="mt-1">
                        <select v-model="filters.refund_done"
                            class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md">
                            <option value="">All</option>
                            <option value="completed">Completed</option>
                            <option value="in_progress">In progress</option>
                            <option value="not_started">Not started</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Is Sent to Settlement?</label>
                    <div class="mt-1">
                        <select v-model="filters.sent_settlement"
                            class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md">
                            <option value="">All</option>
                            <option value="yes">Yes — in a settlement</option>
                            <option value="no">No — not yet sent</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5">
                <div class="mt-3">
                    <div class="flex flex-col space-y-1 md:flex-row md:space-y-0 md:space-x-1">
                        <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            @click="applyFilters">
                            <MagnifyingGlassIcon class="h-4 w-4" aria-hidden="true" />
                            <span>Search</span>
                        </Button>
                        <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            @click="clearFilters">
                            <span>Clear</span>
                        </Button>
                        <Button type="button" class="inline-flex space-x-1 items-center rounded-md bg-white px-8 py-3 md:px-5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-400 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            @click="exportExcel" title="Export the current filtered list to Excel">
                            <ArrowDownTrayIcon class="h-4 w-4" aria-hidden="true" />
                            <span>Export Excel</span>
                        </Button>
                    </div>
                </div>
                <div class="flex flex-col space-y-1">
                    <p class="text-sm text-gray-700 leading-5 flex space-x-1">
                        <span>Showing</span>
                        <span class="font-medium">{{ tickets.from ?? 0 }}</span>
                        <span>to</span>
                        <span class="font-medium">{{ tickets.to ?? 0 }}</span>
                        <span>of</span>
                        <span class="font-medium">{{ tickets.total }}</span>
                        <span>results</span>
                    </p>
                    <div class="md:w-48">
                        <label class="block text-xs font-medium text-gray-700">Per page</label>
                        <MultiSelect
                            v-model="filters.numberPerPage"
                            :options="numberPerPageOptions"
                            trackBy="id"
                            valueProp="id"
                            label="value"
                            placeholder="Select"
                            open-direction="bottom"
                            class="mt-1"
                            @selected="applyFilters"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- batch toolbar (method-aware): PayNow → push to settlement; PayPal →
             mark done here (paid manually). Only shows once a ticket is ticked. -->
        <div v-if="selected.length" class="bg-teal-50 border border-teal-200 rounded-md px-4 py-3 mb-3">
            <div class="flex items-center gap-3">
                <span class="text-sm font-semibold text-teal-800">{{ selected.length }} selected</span>
                <span v-if="allPaynow" class="text-xs text-gray-500">PayNow — push into the Refund Settlement (the CIMB file is exported from there).</span>
                <span v-else-if="allPaypal" class="text-xs text-gray-500">PayPal — paid manually, so mark them done here. This emails each customer their completion notice. PayPal is not settled via CIMB.</span>
                <span v-else class="text-xs text-gray-500">Mixed methods — run each action on its own group: PayNow tickets are pushed to a settlement, PayPal tickets are marked done here.</span>
            </div>
            <div class="flex items-center gap-3 mt-3">
                <button v-if="paynowSelectedIds.length" @click="pushToSettlement" :disabled="pushing"
                    class="bg-teal-600 text-white rounded-md px-4 py-2 text-sm font-semibold hover:bg-teal-700 disabled:opacity-50">
                    {{ pushing ? 'Pushing…' : '➡ Send to Settlement (' + paynowSelectedIds.length + ')' }}
                </button>
                <button v-if="paypalSelectedIds.length" @click="markDonePaypal" :disabled="marking"
                    class="bg-green-600 text-white rounded-md px-4 py-2 text-sm font-semibold hover:bg-green-700 disabled:opacity-50">
                    {{ marking ? 'Marking…' : '✓ Mark as Done — PayPal (' + paypalSelectedIds.length + ')' }}
                </button>
                <a href="/refund-settlements" class="text-xs text-teal-700 underline whitespace-nowrap">Open Refund Settlement →</a>
            </div>
            <span v-if="pushMsg" class="block mt-2 text-xs text-red-600">{{ pushMsg }}</span>
        </div>

        <!-- table -->
        <div class="bg-white rounded-md border overflow-x-auto">
            <table class="compact-table min-w-full text-xs">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="px-3 py-2 w-8" rowspan="2"><input type="checkbox" :checked="allSelected" @change="toggleAll" class="cursor-pointer" title="Select all Approved tickets on this page" /></th>
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
                        <th @click="sortTable('refund_method')" class="hover:text-gray-700">Refund<br>Method{{ arrow('refund_method') }}</th>
                        <th @click="sortTable('machine_rf_24h')" class="border-l border-gray-200 hover:text-gray-700">Machine L24h<br># of RF{{ arrow('machine_rf_24h') }}</th>
                        <th @click="sortTable('repeat_flag')" class="hover:text-gray-700">New /<br>Repeat?{{ arrow('repeat_flag') }}</th>
                        <th @click="sortTable('product_drop_sensor')" class="hover:text-gray-700">Prod Exit<br>Sensor{{ arrow('product_drop_sensor') }}</th>
                        <th @click="sortTable('error_code')" class="hover:text-gray-700">Error<br>Code{{ arrow('error_code') }}</th>
                        <th @click="sortTable('status')" class="border-l border-gray-200 hover:text-gray-700">Validation{{ arrow('status') }}</th>
                        <th @click="sortTable('batch')" class="hover:text-gray-700">Send to<br>Settlement{{ arrow('batch') }}</th>
                        <th @click="sortTable('done')" class="hover:text-gray-700">Refund<br>Done?{{ arrow('done') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="t in sortedRows" :key="t.id" class="border-t hover:bg-gray-50" :class="t.is_dropped ? 'opacity-60' : ''">
                        <td class="px-3 py-3">
                            <input type="checkbox" :disabled="!eligible(t)" :checked="selected.includes(t.id)" @change="toggleRow(t.id)"
                                :class="eligible(t) ? 'cursor-pointer' : 'cursor-not-allowed opacity-40 grayscale'"
                                :title="!eligible(t) ? 'Only Approved tickets can be marked done in a batch' : ''" />
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
                            <span v-if="t.vend_prefix_name" class="ml-1 text-xs text-gray-500"
                                v-tooltip="'VendPrefix (mapping) name of the matched transaction.'">({{ t.vend_prefix_name }})</span>
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
                                <template v-if="t.matched">
                                    {{ payMethodParts(t.pay_method || t.payment_channel || '—').main }}
                                    <span v-if="payMethodParts(t.pay_method || t.payment_channel || '—').paren" class="block text-gray-500">{{ payMethodParts(t.pay_method || t.payment_channel || '—').paren }}</span>
                                </template>
                                <template v-else>—</template>
                                <span v-if="t.matched && t.pay_provider" class="block text-gray-500">({{ t.pay_provider }})</span>
                            </div>
                        </td>
                        <!-- Refund Amt = effective final payout. When the admin overrode
                             the amount, the original claim is shown struck-through beside it
                             (same treatment as the ticket page's "Overwritten" section). -->
                        <td class="px-4 py-3 font-medium border-l border-gray-100">
                            <template v-if="t.matched">
                                <div>${{ t.final_refund_overridden ? t.final_refund_amount : t.amount }}</div>
                                <div v-if="t.final_refund_overridden" class="text-[11px] font-normal text-amber-600 mt-0.5"
                                    v-tooltip="'Final refund amount overridden from the original claim of $' + t.amount + '.'">
                                    <span class="line-through text-gray-400">${{ t.amount }}</span> claim
                                </div>
                            </template>
                            <template v-else>—</template>
                        </td>
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
                            <span v-if="t.product_drop_sensor === true" class="text-xs font-semibold px-2 py-0.5 rounded-full bg-green-100 text-green-700"
                                v-tooltip="'Product Drop Sensor was Enabled on the machine at the time of the transaction.'">Enabled</span>
                            <span v-else-if="t.product_drop_sensor === false" class="text-xs font-semibold px-2 py-0.5 rounded-full bg-red-100 text-red-700"
                                v-tooltip="'Product Drop Sensor was Disabled on the machine at the time of the transaction.'">Disabled</span>
                            <span v-else class="text-gray-300" v-tooltip="'No Product Drop Sensor reading recorded for this transaction.'">—</span>
                        </td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">
                            <span v-if="t.error_code" class="text-xs font-semibold text-amber-700"
                                v-tooltip="t.error_desc || ('Error code ' + t.error_code)">{{ t.error_code }}</span>
                            <span v-else class="text-xs font-semibold px-2 py-0.5 rounded-full bg-white text-gray-600 border border-gray-300"
                                v-tooltip="'No error code recorded for this transaction.'">No</span>
                        </td>
                        <td class="px-4 py-3 border-l border-gray-100 text-center">
                            <span class="inline-block text-xs font-bold px-2 py-1 rounded-full whitespace-nowrap" :class="statusClass(t.is_dropped ? 'dropped' : (t.status === 'scheduled' ? 'approved' : t.status))">{{ t.is_dropped ? 'Dropped' : (statuses[t.status === 'scheduled' ? 'approved' : t.status] || t.status) }}</span>
                            <span class="flex items-center justify-center gap-0.5 mt-1.5">
                                <component :is="c.ok ? CheckCircleIcon : XCircleIcon" v-for="(c, i) in validationChecks(t)" :key="i"
                                    class="h-5 w-5" :class="c.ok ? 'text-green-600' : 'text-red-500'" v-tooltip="c.label" />
                            </span>
                        </td>
                        <td class="px-4 py-3 text-center" @click.stop>
                            <a v-if="t.batch && t.batch.is_settlement" :href="'/refund-settlements/' + t.batch.id" target="_blank"
                                class="text-teal-700 text-xs font-semibold hover:underline leading-tight inline-block"
                                title="Open the Refund Settlement">
                                <span class="block whitespace-nowrap">{{ settlementRefTop(t.batch.reference) }}</span>
                                <span class="block whitespace-nowrap">{{ settlementRefBottom(t.batch.reference) }}</span>
                            </a>
                            <a v-else-if="t.batch" :href="'/refunds/batch/' + t.batch.id + '/download'"
                                class="text-teal-700 text-xs font-semibold hover:underline whitespace-nowrap"
                                :title="t.batch.filename || ''">⬇ {{ t.batch.reference }}</a>
                            <span v-else class="text-gray-300">—</span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-center" @click.stop>
                            <template v-if="t.status === 'completed'">
                                <span class="inline-block text-xs font-bold px-2 py-1 rounded-full bg-green-100 text-green-800">✓ Completed</span>
                                <div v-if="t.completed_at" class="text-[11px] text-gray-500 mt-1">{{ t.completed_at }}</div>
                            </template>
                            <!-- Per-row "Mark done" removed: completion is now batch-only via the
                                 toolbar action. Approved/Scheduled just show an "In progress" badge. -->
                            <span v-else-if="['approved', 'scheduled'].includes(t.status)" class="text-xs font-bold px-2 py-1 rounded-full bg-amber-100 text-amber-800">In progress</span>
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
