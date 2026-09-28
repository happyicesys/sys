<script setup>
// Transactions > "Txn, Revenue & Settlement" (Brian, 2026-09-28).
//
// One sale followed down one line, left to right:
//   Vending Transaction — what the machine reported (our record)
//   Revenue             — what the payment rail CONFIRMS it received, before MDR
//   Settlement Info     — what reaches the bank, after MDR
// The figure only ever shrinks along the line. Every verdict and figure is
// decided server-side (App\Services\Sales\TxnRevenueSettlement); this page only
// draws it.
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

const props = defineProps({
    rows: { type: Array, default: () => [] },
    pagination: { type: Object, required: true },
    totals: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
    operatorOptions: { type: Array, default: () => [] },
});

const page = usePage();
const can = (p) => (page.props.auth?.roles || []).includes('superadmin') || (page.props.auth?.permissions || []).includes(p);

const railOptions = [
    { id: 'card', name: 'Card terminal (NETS)' },
    { id: 'qr', name: 'QR gateway (Omise)' },
    { id: 'cash', name: 'Cash' },
    { id: 'other', name: 'Other (HID, Grab…)' },
];

// Keys are the server's REV_* constants.
const revenueStates = {
    matched: { text: 'Matched', class: 'bg-teal-100 text-teal-800', tip: 'The payment rail confirms this money was received: a line in the NETS report, or an approved Omise charge.' },
    pending: { text: 'Pending', class: 'bg-gray-100 text-gray-600', tip: 'The rail has not ruled yet — the NETS files for this day and the next are not both synced, or the Omise charge is not approved.' },
    not_found: { text: 'No line in NETS', class: 'bg-amber-100 text-amber-800', tip: 'NETS is final for this day and has no line for this sale, so no money was received for it (a failed vend voided before batch upload, or a shortfall to investigate).' },
    unverifiable: { text: 'Not verifiable', class: 'bg-gray-100 text-gray-600', tip: 'No report covers this sale: the terminal was not bound that day, or its supplier (Auresys) settles only part of its sales through the NETS file.' },
    refunded: { text: 'Refunded', class: 'bg-rose-100 text-rose-700', tip: 'The rail returned the money (NETS reversal / void, Omise refund), so there is no revenue.' },
    retained: { text: 'Retained credit', class: 'bg-violet-100 text-violet-800', tip: 'Paid from credit the reader kept after an earlier failed vend — no new money. The money is counted on that earlier sale.' },
    none: { text: '', class: '', tip: '' },
};
const revenueStateOptions = Object.entries(revenueStates)
    .map(([id, s]) => ({ id, name: id === 'none' ? 'No rail (cash, HID, Grab)' : s.text }));

const f = reactive({
    date_from: props.filters.date_from,
    date_to: props.filters.date_to,
    operators: props.filters.operators || [],
    codes: props.filters.codes || '',
    order_id: props.filters.order_id || '',
    rails: props.filters.rails || [],
    revenue_states: props.filters.revenue_states || [],
    numberPerPage: props.filters.numberPerPage || 50,
    sortKey: props.filters.sortKey || 'transaction_datetime',
    sortDir: props.filters.sortDir || 'desc',
});

function query(extra = {}) {
    return { ...f, ...extra };
}
function apply(extra = {}) {
    router.get('/vends/txn-revenue-settlement', query({ page: 1, ...extra }), { preserveState: true, preserveScroll: true });
}
function reset() {
    router.get('/vends/txn-revenue-settlement', {}, { preserveState: false });
}
function goPage(n) {
    router.get('/vends/txn-revenue-settlement', query({ page: n }), { preserveState: true, preserveScroll: true });
}
function sortBy(key) {
    f.sortDir = f.sortKey === key && f.sortDir === 'desc' ? 'asc' : 'desc';
    f.sortKey = key;
    apply();
}
const arrow = (key) => f.sortKey === key ? (f.sortDir === 'asc' ? ' ▲' : ' ▼') : '';

const exportUrl = computed(() => {
    const params = new URLSearchParams();
    Object.entries(f).forEach(([k, v]) => {
        if (Array.isArray(v)) v.forEach((x) => params.append(k + '[]', x));
        else if (v !== '' && v !== null && v !== undefined) params.append(k, v);
    });
    return '/vends/txn-revenue-settlement/export-csv?' + params.toString();
});

// Money arrives as integer cents; divide only here, at display.
const money = (cents) => (cents === null || cents === undefined) ? '' : (cents / 100).toFixed(2);
const pct = (part, whole) => whole ? (part * 100 / whole).toFixed(1) + '%' : '—';

const railLabel = { card: 'Card', qr: 'QR', cash: 'Cash', other: 'Other' };
const opsDashboardUrl = (code) => code ? ('/vends/customers?codes=' + encodeURIComponent(code)) : null;
const txnUrl = (r) => '/vends/transactions?' + new URLSearchParams({
    order_id: r.order_id || '', codes: r.vend_code || '', date_from: r.txn_date || '', date_to: r.txn_date || '',
}).toString();

const t = computed(() => props.totals);
const states = computed(() => t.value.revenue.states);
// Cashless only: the part of the machine's figure a rail can confirm.
const cashlessTxnCents = computed(() => t.value.txn.card.cents + t.value.txn.qr.cents);
</script>

<template>
<Head title="Txn, Revenue & Settlement" />
<BreezeAuthenticatedLayout>
    <template #header>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Txn, Revenue &amp; Settlement</h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
        <!-- filters -->
        <div class="bg-white rounded-md border my-3 px-3 py-3">
            <div class="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-8 gap-2 items-start">
                <div>
                    <label class="block text-xs font-medium text-gray-700">Date From</label>
                    <input type="date" v-model="f.date_from" class="mt-1 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Date To</label>
                    <input type="date" v-model="f.date_to" class="mt-1 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Machine ID</label>
                    <input v-model="f.codes" type="text" placeholder="e.g. 5073, 2031" @keyup.enter="apply()"
                        class="mt-1 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Order ID</label>
                    <input v-model="f.order_id" type="text" placeholder="Order ID" @keyup.enter="apply()"
                        class="mt-1 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md" />
                </div>
                <div class="lg:col-span-2">
                    <label class="block text-xs font-medium text-gray-700">Operator</label>
                    <MultiSelect v-model="f.operators" :options="operatorOptions" trackBy="id" valueProp="id" label="code"
                        mode="tags" placeholder="Default operators" open-direction="bottom" class="mt-1" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Payment Rail</label>
                    <MultiSelect v-model="f.rails" :options="railOptions" trackBy="id" valueProp="id" label="name"
                        mode="tags" placeholder="All" open-direction="bottom" class="mt-1" />
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-700">Revenue Status</label>
                    <MultiSelect v-model="f.revenue_states" :options="revenueStateOptions" trackBy="id" valueProp="id" label="name"
                        mode="tags" placeholder="All" open-direction="bottom" class="mt-1" />
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2 mt-3">
                <button type="button" @click="apply()" class="bg-green-600 text-white rounded-md px-4 py-2 text-sm font-semibold hover:bg-green-700">Search</button>
                <button type="button" @click="reset" class="bg-gray-400 text-white rounded-md px-4 py-2 text-sm font-semibold hover:bg-gray-500">Reset</button>
                <a v-if="can('export transactions-revenue-settlement')" :href="exportUrl"
                    class="border border-gray-300 bg-white text-gray-700 rounded-md px-4 py-2 text-sm font-semibold hover:bg-gray-50">Export CSV</a>
                <div class="ml-auto flex items-center gap-2 text-sm text-gray-600">
                    <span>Showing {{ rows.length }} of {{ pagination.total }}</span>
                    <select v-model="f.numberPerPage" @change="apply()" class="text-sm border-gray-300 rounded-md py-1">
                        <option v-for="n in [50, 100, 200, 500]" :key="n" :value="n">{{ n }}</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- The line, as totals: machine figure → rail-confirmed → after MDR -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 mb-3">
            <div class="bg-white rounded-md border px-4 py-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">1 · Vending Transaction</div>
                <div class="text-2xl font-bold text-gray-800 mt-1">${{ money(t.txn.cents) }}</div>
                <div class="text-xs text-gray-500">{{ t.txn.count }} sales reported by the machines</div>
                <dl class="mt-2 text-xs grid grid-cols-2 gap-y-0.5">
                    <dt class="text-gray-500">Card terminal</dt><dd class="text-right">${{ money(t.txn.card.cents) }} <span class="text-gray-400">({{ t.txn.card.count }})</span></dd>
                    <dt class="text-gray-500">QR gateway</dt><dd class="text-right">${{ money(t.txn.qr.cents) }} <span class="text-gray-400">({{ t.txn.qr.count }})</span></dd>
                    <dt class="text-gray-500">Cash</dt><dd class="text-right">${{ money(t.txn.cash.cents) }} <span class="text-gray-400">({{ t.txn.cash.count }})</span></dd>
                    <dt class="text-gray-500">Other</dt><dd class="text-right">${{ money(t.txn.other.cents) }} <span class="text-gray-400">({{ t.txn.other.count }})</span></dd>
                </dl>
            </div>
            <div class="bg-white rounded-md border border-indigo-200 px-4 py-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-indigo-700">2 · Revenue <span class="normal-case font-normal">(matched from API / report, before MDR)</span></div>
                <div class="text-2xl font-bold text-gray-800 mt-1">${{ money(t.revenue.cents) }}</div>
                <div class="text-xs text-gray-500">{{ pct(t.revenue.cents, cashlessTxnCents) }} of cashless sales (${{ money(cashlessTxnCents) }}) confirmed by a rail</div>
                <dl class="mt-2 text-xs grid grid-cols-2 gap-y-0.5">
                    <dt class="text-gray-500">NETS report</dt><dd class="text-right">${{ money(t.revenue.card_cents) }}</dd>
                    <dt class="text-gray-500">Omise API</dt><dd class="text-right">${{ money(t.revenue.qr_cents) }}</dd>
                    <dt class="text-gray-500" v-tooltip="revenueStates.pending.tip">Pending (txn amt)</dt><dd class="text-right">${{ money(states.pending.cents) }} <span class="text-gray-400">({{ states.pending.count }})</span></dd>
                    <dt class="text-gray-500" v-tooltip="revenueStates.not_found.tip">No line in NETS</dt><dd class="text-right">${{ money(states.not_found.cents) }} <span class="text-gray-400">({{ states.not_found.count }})</span></dd>
                    <dt class="text-gray-500" v-tooltip="revenueStates.unverifiable.tip">Not verifiable</dt><dd class="text-right">${{ money(states.unverifiable.cents) }} <span class="text-gray-400">({{ states.unverifiable.count }})</span></dd>
                    <dt class="text-gray-500" v-tooltip="revenueStates.refunded.tip">Refunded by rail</dt><dd class="text-right">${{ money(states.refunded.cents) }} <span class="text-gray-400">({{ states.refunded.count }})</span></dd>
                </dl>
            </div>
            <div class="bg-white rounded-md border border-teal-200 px-4 py-3">
                <div class="text-xs font-semibold uppercase tracking-wide text-teal-700">3 · Settlement Info <span class="normal-case font-normal">(after MDR)</span></div>
                <div class="text-2xl font-bold text-gray-800 mt-1">${{ money(t.settlement.net_cents) }}</div>
                <div class="text-xs text-gray-500">revenue after MDR</div>
                <dl class="mt-2 text-xs grid grid-cols-2 gap-y-0.5">
                    <dt class="text-gray-500">MDR total</dt><dd class="text-right">${{ money(t.settlement.mdr_cents) }} <span class="text-gray-400">({{ pct(t.settlement.mdr_cents, t.revenue.cents) }})</span></dd>
                    <dt class="text-gray-500" v-tooltip="'Estimated from the NETS schedule rates (config/card_settlement.php), rounded per line.'">— NETS (est.)</dt><dd class="text-right">${{ money(t.settlement.card_mdr_cents) }}</dd>
                    <dt class="text-gray-500" v-tooltip="'Omise\'s own fee + VAT on each charge.'">— Omise (actual)</dt><dd class="text-right">${{ money(t.settlement.qr_mdr_cents) }}</dd>
                    <dt class="text-gray-500" v-tooltip="'What the bank receives. NETS EFTPOS / FlashPay / cross-border bank the full amount and bill the MDR separately; Visa/Mastercard bank the amount after MDR.'">Bank-in amount</dt><dd class="text-right">${{ money(t.settlement.bank_in_cents) }}</dd>
                    <template v-if="t.settlement.unpriced_cents">
                        <dt class="text-amber-700" v-tooltip="'Matched NETS lines whose card type has no rate in the payout schedule — no MDR or bank-in figure for them.'">No rate on file</dt><dd class="text-right text-amber-700">${{ money(t.settlement.unpriced_cents) }}</dd>
                    </template>
                </dl>
            </div>
        </div>

        <div class="bg-white rounded-md border overflow-x-auto">
            <table class="compact-table min-w-full text-xs">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th colspan="7" class="text-center px-4 py-2 border-b border-gray-200">Vending Transaction</th>
                        <th colspan="3" class="text-center px-4 py-2 border-b border-l border-gray-200 text-indigo-700">Revenue (Matching fr API / Report)</th>
                        <th colspan="3" class="text-center px-4 py-2 border-b border-l border-gray-200 text-teal-700">Settlement Info</th>
                    </tr>
                    <tr class="text-left">
                        <th>#</th>
                        <th class="cursor-pointer hover:text-gray-700" @click="sortBy('transaction_datetime')">Order ID<br>Date Time{{ arrow('transaction_datetime') }}</th>
                        <th>Machine ID<br>Site</th>
                        <th>Channel<br>Product</th>
                        <th class="text-right cursor-pointer hover:text-gray-700" @click="sortBy('amount')">Txn<br>Amt ${{ arrow('amount') }}</th>
                        <th>Payment<br>Method</th>
                        <th>Dispense</th>
                        <th class="border-l border-gray-200">Status<br>Source</th>
                        <th class="text-right">Revenue<br>Amt $</th>
                        <th>Rail Line</th>
                        <th class="border-l border-gray-200">Settlement<br>Date</th>
                        <th class="text-right">Settlement Amt<br>(bank-in) $</th>
                        <th class="text-right">MDR $<br>Rate %</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(r, i) in rows" :key="r.id" class="border-t hover:bg-gray-50 align-top">
                        <td class="text-gray-400">{{ (pagination.page - 1) * pagination.per_page + i + 1 }}</td>
                        <td class="whitespace-nowrap">
                            <a :href="txnUrl(r)" target="_blank" class="font-medium text-teal-700 hover:underline" title="Open in All Transactions">{{ r.order_id }}</a>
                            <div class="text-gray-600">{{ r.transaction_datetime }}</div>
                        </td>
                        <td class="max-w-[150px]">
                            <a v-if="r.vend_code" :href="opsDashboardUrl(r.vend_code)" target="_blank" class="font-medium text-teal-700 hover:underline">{{ r.vend_code }}</a>
                            <span class="ml-1 text-gray-400">{{ r.operator_code }}</span>
                            <div class="text-gray-600 break-words">{{ r.customer_code }} {{ r.customer_name }}</div>
                        </td>
                        <td class="max-w-[150px]">
                            <span class="font-medium text-gray-700">{{ r.channel }}</span>
                            <div class="text-gray-600 break-words">{{ r.product }}</div>
                        </td>
                        <td class="text-right font-semibold whitespace-nowrap">{{ money(r.amount_cents) }}</td>
                        <td class="whitespace-nowrap">
                            <span class="font-medium text-gray-700">{{ railLabel[r.rail] }}</span>
                            <div class="text-gray-500">{{ r.payment_method }}</div>
                            <a v-if="r.refund_request_reference" :href="'/refunds/' + r.refund_request_id" target="_blank"
                                class="text-blue-600 hover:underline" v-tooltip="'Refund request ' + (r.refund_request_status || '') + ' — paid out by us, not deducted from rail revenue here.'">{{ r.refund_request_reference }}</a>
                        </td>
                        <td class="whitespace-nowrap">
                            <span v-if="r.dispense === 'Dispensed'" class="text-green-700">Dispensed</span>
                            <span v-else-if="r.dispense === 'Failed'" class="text-red-600">Failed</span>
                            <span v-else-if="r.dispense_reason === 'no_trade'" class="text-gray-400" v-tooltip="'No machine TRADE for this sale (yet)'">No TRADE</span>
                            <span v-else-if="r.dispense_reason === 'on_items'" class="text-gray-400">Per item</span>
                        </td>

                        <!-- Revenue -->
                        <td class="border-l border-gray-100 whitespace-nowrap">
                            <template v-if="r.revenue_state !== 'none'">
                                <span class="text-[11px] font-semibold px-2 py-0.5 rounded-full" :class="revenueStates[r.revenue_state]?.class"
                                    v-tooltip="revenueStates[r.revenue_state]?.tip">{{ revenueStates[r.revenue_state]?.text }}</span>
                                <div class="text-gray-500 mt-0.5">{{ r.revenue_source }}</div>
                                <span v-if="['Retained credit', 'Re-vended'].includes(r.payment_status)" class="text-[10px] font-semibold text-violet-700"
                                    v-tooltip="r.payment_status === 'Re-vended' ? 'The reader kept this payment as credit and a later vend consumed it — the money was received, so it is revenue here.' : 'Paid partly or wholly from credit left by an earlier failed vend; only a top-up line (if any) is new money.'">{{ r.payment_status }}</span>
                            </template>
                        </td>
                        <td class="text-right whitespace-nowrap">
                            <span v-if="r.revenue_cents !== null" class="font-semibold">{{ money(r.revenue_cents) }}</span>
                            <div v-if="r.revenue_diff_cents" class="text-amber-700" v-tooltip="'Revenue differs from the machine\'s amount — e.g. a top-up line on a retained-credit sale.'">
                                {{ r.revenue_diff_cents > 0 ? '+' : '' }}{{ money(r.revenue_diff_cents) }}
                            </div>
                        </td>
                        <td class="whitespace-nowrap text-gray-600">
                            <template v-if="r.line_time">
                                <a :href="'/card-settlements/' + r.line_report_id" target="_blank" class="text-teal-700 hover:underline" title="Open the NETS report">{{ r.line_time }}</a>
                                <span v-if="r.line_card_last4" class="ml-1 text-gray-400">…{{ r.line_card_last4 }}</span>
                                <div v-if="!r.line_synced" class="text-amber-700">report not synced</div>
                            </template>
                        </td>

                        <!-- Settlement -->
                        <td class="border-l border-gray-100 whitespace-nowrap">
                            <template v-if="r.settlement_date">
                                <span class="font-medium" v-tooltip="r.settlement_note">{{ r.settlement_date }}</span>
                                <div class="text-[10px] font-semibold text-blue-700">{{ r.settlement_gateway }}</div>
                            </template>
                            <span v-else-if="r.settlement_note" class="text-gray-400" v-tooltip="r.settlement_note">not tracked</span>
                        </td>
                        <td class="text-right whitespace-nowrap font-semibold">{{ money(r.bank_in_cents) }}</td>
                        <td class="text-right whitespace-nowrap">
                            <template v-if="r.mdr_cents !== null">
                                <span v-tooltip="r.mdr_is_estimate ? 'Estimated from the schedule rate, rounded per line' : 'Omise fee + VAT on this charge'">{{ money(r.mdr_cents) }}<span v-if="r.mdr_is_estimate" class="text-gray-400">*</span></span>
                                <div class="text-gray-500">{{ r.mdr_rate }}</div>
                            </template>
                        </td>
                    </tr>
                    <tr v-if="!rows.length"><td colspan="13" class="px-4 py-8 text-center text-gray-400">No transactions for these filters.</td></tr>
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between mt-3 text-sm text-gray-600">
            <span>Page {{ pagination.page }} of {{ pagination.last_page }} · * = MDR estimated from the NETS rate</span>
            <div class="flex gap-1">
                <button type="button" :disabled="pagination.page <= 1" @click="goPage(pagination.page - 1)"
                    class="px-3 py-1.5 rounded border text-sm bg-white disabled:text-gray-300">Prev</button>
                <button type="button" :disabled="pagination.page >= pagination.last_page" @click="goPage(pagination.page + 1)"
                    class="px-3 py-1.5 rounded border text-sm bg-white disabled:text-gray-300">Next</button>
            </div>
        </div>
    </div>
</BreezeAuthenticatedLayout>
</template>

<style scoped>
.compact-table th,
.compact-table td {
    padding: 0.3rem 0.4rem;
    line-height: 1.2;
}
</style>
