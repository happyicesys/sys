<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    report: { type: Object, required: true },
    rows: { type: Object, required: true },
    rowFilters: { type: Object, default: () => ({}) },
    unboundTerminals: { type: Array, default: () => [] },
    statusLabels: { type: Object, default: () => ({}) },
});

const rowStatus = ref(props.rowFilters.row_status ?? 'queries');

// Row status codes (CardSettlementRow::STATUS_*)
const chips = [
    { key: 'queries', label: 'Queries' },
    { key: '1', label: 'Matched' },
    { key: '2', label: 'Unmatched' },
    { key: '3', label: 'Ambiguous' },
    { key: '5', label: 'Duplicates' },
    { key: '4', label: 'Ignored' },
    { key: 'all', label: 'All rows' },
];

const rowStatusClass = (s) => ({
    1: 'bg-green-100 text-green-800',
    2: 'bg-amber-100 text-amber-800',
    3: 'bg-orange-100 text-orange-800',
    4: 'bg-gray-100 text-gray-500',
    5: 'bg-gray-100 text-gray-500',
}[s] || 'bg-gray-100 text-gray-700');

function pickStatus(key) {
    rowStatus.value = key;
    router.get(`/card-settlements/${props.report.id}`, { row_status: key }, { preserveState: true, preserveScroll: true, replace: true });
}

function rematch() {
    router.post(`/card-settlements/${props.report.id}/rematch`, {}, { preserveScroll: true });
}

function sync() {
    if (!confirm(`Stamp "settlement synced" onto ${props.report.matched_count} matched transaction(s)?`)) return;
    router.post(`/card-settlements/${props.report.id}/sync`, {}, { preserveScroll: true });
}

function destroyReport() {
    if (!confirm('Delete this report and all its rows?')) return;
    router.delete(`/card-settlements/${props.report.id}`);
}

function resolveTo(row, txnId) {
    router.post(`/card-settlements/${props.report.id}/rows/${row.id}/resolve`,
        { vend_transaction_id: txnId }, { preserveScroll: true });
}

const manualTxnId = ref({});
function resolveManual(row) {
    const id = parseInt(manualTxnId.value[row.id], 10);
    if (!id) return;
    resolveTo(row, id);
}

function ignoreRow(row) {
    router.post(`/card-settlements/${props.report.id}/rows/${row.id}/ignore`, {}, { preserveScroll: true });
}

const queriesCount = props.report.unmatched_count + props.report.ambiguous_count;
</script>

<template>
<Head :title="'Card Settlement ' + (report.cutover_date || report.id)" />
<BreezeAuthenticatedLayout>
    <template #header>
        <div class="flex items-center justify-between flex-wrap gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                <Link href="/card-settlements" class="text-teal-700 hover:underline">Card Settlement</Link>
                <span class="text-gray-400"> / </span>{{ report.original_filename }}
            </h2>
            <div class="flex gap-2">
                <button @click="rematch" :disabled="report.status === 'matching'"
                    class="bg-gray-100 border text-gray-700 rounded-md px-3 py-2 text-sm disabled:opacity-50">Rematch</button>
                <button @click="sync" :disabled="report.status === 'matching' || !report.matched_count"
                    class="bg-teal-600 text-white rounded-md px-4 py-2 text-sm font-medium hover:bg-teal-700 disabled:opacity-50">
                    Sync {{ report.matched_count }} matched
                </button>
                <button v-if="report.status !== 'synced'" @click="destroyReport"
                    class="bg-white border border-red-200 text-red-600 rounded-md px-3 py-2 text-sm">Delete</button>
            </div>
        </div>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
        <!-- summary -->
        <div class="bg-white rounded-md border p-3 mb-3 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3 text-sm">
            <div><div class="text-xs text-gray-400">Provider / Account</div>{{ report.provider.toUpperCase() }} · {{ report.merchant_account || '—' }}</div>
            <div><div class="text-xs text-gray-400">Cutover date</div>{{ report.cutover_date || '—' }}</div>
            <div><div class="text-xs text-gray-400">Status</div><span class="font-semibold">{{ report.status }}</span></div>
            <div><div class="text-xs text-gray-400">Purchases</div>{{ report.purchase_rows }} / {{ report.total_rows }} rows</div>
            <div><div class="text-xs text-gray-400">Matched</div><span class="text-green-700 font-semibold">{{ report.matched_count }}</span></div>
            <div><div class="text-xs text-gray-400">Queries</div><span :class="queriesCount ? 'text-amber-700 font-semibold' : ''">{{ queriesCount }}</span></div>
            <div><div class="text-xs text-gray-400">Duplicates / Ignored</div>{{ report.duplicate_count }} / {{ report.ignored_count }}</div>
            <div>
                <div class="text-xs text-gray-400">Synced</div>
                <span v-if="report.synced_at">{{ report.synced_count }} · {{ report.synced_at }}<span v-if="report.synced_by"> by {{ report.synced_by }}</span></span>
                <span v-else class="text-gray-300">not yet</span>
            </div>
        </div>

        <div v-if="report.error_message" class="bg-red-50 border border-red-200 text-red-700 rounded-md p-3 mb-3 text-sm">
            {{ report.error_message }}
        </div>

        <!-- unbound terminals -->
        <div v-if="unboundTerminals.length" class="bg-amber-50 border border-amber-200 rounded-md p-3 mb-3 text-sm">
            <div class="font-semibold text-amber-800 mb-1">Terminals without a machine binding</div>
            <div class="text-amber-800">
                <span v-for="t in unboundTerminals" :key="t.terminal_id" class="inline-block mr-3">
                    {{ t.terminal_id }} <span class="text-amber-600">({{ t.row_count }} rows)</span>
                </span>
            </div>
            <div class="mt-1 text-amber-700">
                Add them on <Link href="/card-terminal-bindings" class="underline font-medium">Card Terminal Bindings</Link>, then hit Rematch.
            </div>
        </div>

        <!-- row status chips -->
        <div class="flex flex-wrap gap-2 mb-3">
            <span v-for="c in chips" :key="c.key"
                class="text-xs font-semibold px-3 py-1.5 rounded-full border bg-white cursor-pointer"
                :class="rowStatus === c.key ? 'border-teal-500 text-teal-700' : 'border-gray-200 text-gray-600'"
                @click="pickStatus(c.key)">
                {{ c.label }}
            </span>
        </div>

        <!-- rows -->
        <div class="bg-white rounded-md border overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr class="[&>th]:px-3 [&>th]:py-2 [&>th]:whitespace-nowrap [&>th]:text-left">
                        <th>#</th>
                        <th>Terminal</th>
                        <th>Machine</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Card</th>
                        <th class="text-right">Amount</th>
                        <th class="text-center">Status</th>
                        <th>Matched sale</th>
                        <th>Note / Resolve</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="row in rows.data" :key="row.id" class="border-t align-top">
                        <td class="px-3 py-2 text-gray-400">{{ row.row_no }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ row.terminal_id }}</td>
                        <td class="px-3 py-2">{{ row.vend_code || '—' }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">{{ row.transaction_date }}</td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            <template v-if="row.time_is_partial">
                                <span class="text-amber-700" title="Hour lost (file was re-saved in Excel) — matched by minute:second within the hour">
                                    ??{{ row.transaction_time ? row.transaction_time.slice(2) : '' }}
                                </span>
                            </template>
                            <template v-else>{{ row.transaction_time }}</template>
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap text-gray-500">{{ row.card_issuer }}</td>
                        <td class="px-3 py-2 text-right whitespace-nowrap">{{ row.amount.toFixed(2) }}</td>
                        <td class="px-3 py-2 text-center">
                            <span class="inline-block text-xs font-bold px-2 py-1 rounded-full" :class="rowStatusClass(row.status)">
                                {{ row.status_label }}
                            </span>
                        </td>
                        <td class="px-3 py-2 whitespace-nowrap">
                            <template v-if="row.matched_txn">
                                <div>#{{ row.matched_txn.id }} · {{ row.matched_txn.transaction_datetime }}</div>
                                <div class="text-xs text-gray-400">
                                    Δ {{ row.match_time_delta !== null ? row.match_time_delta + 's' : 'manual' }}
                                    <span v-if="row.matched_txn.is_refunded" class="text-red-500 font-medium"> · refunded</span>
                                    <span v-if="row.matched_txn.synced" class="text-green-600 font-medium"> · synced</span>
                                </div>
                            </template>
                            <span v-else class="text-gray-300">—</span>
                        </td>
                        <td class="px-3 py-2">
                            <div v-if="row.resolution_note" class="text-xs text-gray-500 mb-1">{{ row.resolution_note }}</div>
                            <!-- candidates to pick from (ambiguous / claimed-elsewhere rows) -->
                            <div v-if="(row.status === 3 || row.status === 2) && row.candidates && row.candidates.length" class="space-y-1">
                                <div v-for="c in row.candidates" :key="c.vend_transaction_id" class="flex items-center gap-2 text-xs">
                                    <button @click="resolveTo(row, c.vend_transaction_id)"
                                        class="bg-teal-50 border border-teal-200 text-teal-700 rounded px-2 py-0.5 hover:bg-teal-100">
                                        Pick
                                    </button>
                                    <span>#{{ c.vend_transaction_id }} · {{ c.transaction_datetime }}<span v-if="c.is_refunded" class="text-red-500"> · refunded</span></span>
                                </div>
                            </div>
                            <div v-if="row.status === 2 || row.status === 3" class="flex items-center gap-1 mt-1">
                                <input v-model="manualTxnId[row.id]" placeholder="Txn ID" class="border rounded px-2 py-0.5 text-xs w-24" @keyup.enter="resolveManual(row)" />
                                <button @click="resolveManual(row)" class="text-xs border rounded px-2 py-0.5 text-gray-600 hover:bg-gray-50">Assign</button>
                                <button @click="ignoreRow(row)" class="text-xs border rounded px-2 py-0.5 text-gray-400 hover:bg-gray-50">Ignore</button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="!rows.data.length">
                        <td colspan="10" class="px-4 py-8 text-center text-gray-400">
                            {{ rowStatus === 'queries' ? 'No open queries — everything matched, duplicated or ignored.' : 'No rows.' }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- pagination -->
        <div class="flex items-center justify-between mt-3 text-sm text-gray-600">
            <span>Showing {{ rows.from || 0 }}–{{ rows.to || 0 }} of {{ rows.total }}</span>
            <div class="flex gap-1">
                <template v-for="(l, i) in rows.links" :key="i">
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
