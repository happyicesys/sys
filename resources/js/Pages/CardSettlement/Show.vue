<template>

  <Head :title="'Card Settlement ' + (report.cutover_date || report.id)" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between flex-wrap gap-2">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          <Link href="/card-settlements" class="text-blue-700 hover:underline">Card Settlement</Link>
          <span class="text-gray-400"> / </span>{{ report.original_filename }}
          <a v-if="report.file_url" :href="report.file_url" class="ml-2 text-xs font-normal text-blue-700 hover:underline">download file</a>
        </h2>
        <div class="flex space-x-1">
          <Button
            class="bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
            :class="report.status === 'matching' ? 'opacity-50 cursor-not-allowed' : ''"
            @click="rematch()"
          >
            <ArrowPathIcon class="w-4 h-4"></ArrowPathIcon>
            <span>
              Rematch
            </span>
          </Button>
          <Button
            class="bg-green-500 hover:bg-green-600 px-3 py-2 text-xs text-white flex space-x-1"
            :class="report.status === 'matching' || !report.matched_count ? 'opacity-50 cursor-not-allowed' : ''"
            @click="sync()"
          >
            <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
            <span>
              Sync {{ report.matched_count }} Matched
            </span>
          </Button>
          <Button
            v-if="report.status !== 'synced'"
            class="bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1"
            @click="destroyReport()"
          >
            <TrashIcon class="w-4 h-4"></TrashIcon>
            <span>
              Delete
            </span>
          </Button>
        </div>
      </div>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <!-- summary -->
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-3 text-sm">
        <div>
          <div class="text-xs text-gray-400 uppercase">Provider / Account</div>
          <span class="uppercase">{{ report.provider }}</span> · {{ report.merchant_account || '—' }}
        </div>
        <div>
          <div class="text-xs text-gray-400 uppercase">Cutover Date</div>
          {{ report.cutover_date || '—' }}
        </div>
        <div>
          <div class="text-xs text-gray-400 uppercase">Status</div>
          <span
            class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-bold border"
            :class="statusBadgeClass(report.status)"
          >
            {{ report.status }}
          </span>
        </div>
        <div>
          <div class="text-xs text-gray-400 uppercase">Purchases / Reversals</div>
          {{ report.purchase_rows }} / <span :class="report.reversal_rows ? 'text-red-600 font-medium' : ''">{{ report.reversal_rows }}</span>
          <span class="text-xs text-gray-400">of {{ report.total_rows }} rows</span>
        </div>
        <div>
          <div class="text-xs text-gray-400 uppercase">Matched</div>
          <span class="text-green-700 font-medium">{{ report.matched_count }}</span>
        </div>
        <div>
          <div class="text-xs text-gray-400 uppercase">Queries</div>
          <span :class="queriesCount ? 'text-amber-700 font-bold' : ''">{{ queriesCount }}</span>
        </div>
        <div>
          <div class="text-xs text-gray-400 uppercase">Duplicates / Ignored</div>
          {{ report.duplicate_count }} / {{ report.ignored_count }}
        </div>
        <div>
          <div class="text-xs text-gray-400 uppercase">Synced / Refund-marked</div>
          <span v-if="report.synced_at">
            {{ report.synced_count }} / <span :class="report.refunded_count ? 'text-red-600 font-medium' : ''">{{ report.refunded_count }}</span>
            <span class="text-xs text-gray-400">· {{ report.synced_at }}<span v-if="report.synced_by"> by {{ report.synced_by }}</span></span>
          </span>
          <span v-else class="text-gray-400">not yet</span>
        </div>
      </div>

      <div v-if="report.error_message" class="-mx-4 sm:-mx-6 lg:-mx-8 bg-red-50 border border-red-200 text-red-700 rounded-md p-3 my-3 text-sm">
        {{ report.error_message }}
      </div>

      <!-- Excel-damaged file: the hour is gone from the file itself (Excel shows a fake
           "12:xx:xx AM" in its formula bar). Rows are matched on minute:second only. -->
      <div v-if="report.partial_time_rows" class="-mx-4 sm:-mx-6 lg:-mx-8 bg-amber-50 border border-amber-200 rounded-md p-3 my-3 text-sm text-amber-800">
        <span class="font-semibold">{{ report.partial_time_rows }} of {{ report.total_rows }} lines have no hour</span> —
        this file was opened and re-saved in Excel, which turns "23:12:41" into "12:41.0". Those lines were matched on
        minute:second within the hour (same terminal, same amount), so ambiguous ones need a manual pick. For exact
        matching, re-download the raw CSV from MerchantConnect and upload that instead.
      </div>

      <!-- unbound terminals -->
      <div v-if="unboundTerminals.length" class="-mx-4 sm:-mx-6 lg:-mx-8 bg-amber-50 border border-amber-200 rounded-md p-3 my-3 text-sm">
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

      <!-- suspected wrong bindings: the sale exists, on a different machine -->
      <div v-if="suspectBindings.length" class="-mx-4 sm:-mx-6 lg:-mx-8 bg-orange-50 border border-orange-200 rounded-md p-3 my-3 text-sm">
        <div class="font-semibold text-orange-800 mb-1">Terminals that look bound to the wrong machine</div>
        <div class="text-orange-800 space-y-0.5">
          <div v-for="s in suspectBindings" :key="s.terminal_id">
            <span class="font-mono">{{ s.terminal_id }}</span> is bound to <b>{{ s.bound_vend_code }}</b>, but its sales are on
            <b>{{ s.suggested_vend_code }}</b> — {{ s.suggested_hits }} of {{ s.row_count }} unmatched lines fit that machine exactly.
          </div>
        </div>
        <div class="mt-1 text-orange-700">
          Move the terminal on <Link href="/card-terminal-bindings" class="underline font-medium">Card Terminal Bindings</Link>
          (close the old binding, add the new one from the right date), then hit Rematch.
        </div>
      </div>

      <!-- row status chips + batch actions on the checked lines -->
      <div class="flex flex-wrap items-center justify-between gap-2 my-3">
        <div class="flex flex-wrap gap-2">
          <span v-for="c in chips" :key="c.key"
            class="text-xs font-semibold px-3 py-1.5 rounded-full border bg-white cursor-pointer"
            :class="rowStatus === c.key ? 'border-green-500 text-green-700' : 'border-gray-200 text-gray-600'"
            @click="pickStatus(c.key)">
            {{ c.label }}
          </span>
        </div>
        <div v-if="ignorableRows.length" class="flex items-center space-x-2">
          <span class="text-xs text-gray-500">{{ selected.length }} of {{ ignorableRows.length }} selected</span>
          <Button
            type="button" class="bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
            :class="selected.length ? '' : 'opacity-50 cursor-not-allowed'"
            @click="ignoreSelected()"
          >
            <EyeSlashIcon class="w-4 h-4"></EyeSlashIcon>
            <span>Ignore {{ selected.length ? selected.length + ' ' : '' }}Selected</span>
          </Button>
        </div>
      </div>

      <div class="mt-3 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll">
            <table class="min-w-full border-separate" style="border-spacing: 0">
                <thead class="bg-gray-100">
                  <tr class="divide-x divide-gray-200">
                    <TableHead>
                      <input
                        type="checkbox"
                        class="cursor-pointer rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        :checked="allSelected"
                        :disabled="!ignorableRows.length"
                        title="Select every open query on this page"
                        @change="toggleAll"
                      />
                    </TableHead>
                    <TableHead>
                      #
                    </TableHead>
                    <TableHead>
                      Terminal
                    </TableHead>
                    <TableHead>
                      Machine ID
                    </TableHead>
                    <TableHead>
                      Date
                    </TableHead>
                    <TableHead>
                      Time
                    </TableHead>
                    <TableHead>
                      Card
                    </TableHead>
                    <TableHead>
                      Amount
                    </TableHead>
                    <TableHead>
                      Status
                    </TableHead>
                    <TableHead>
                      Matched Sale
                    </TableHead>
                    <TableHead>
                      Note / Resolve
                    </TableHead>
                  </tr>
                </thead>
                  <tbody class="bg-white">
                    <tr v-for="(row, rowIndex) in rows.data" :key="row.id" class="divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100" :class="selected.includes(row.id) ? '!bg-indigo-50' : ''">
                      <TableData :currentIndex="rowIndex" :totalLength="rows.length" inputClass="text-center">
                        <!-- Only open queries (Unmatched / Ambiguous) can be batch-ignored. -->
                        <input
                          v-if="isIgnorable(row)"
                          type="checkbox"
                          class="cursor-pointer rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                          :checked="selected.includes(row.id)"
                          @change="toggleRow(row.id)"
                        />
                      </TableData>
                      <TableData :currentIndex="rowIndex" :totalLength="rows.length" inputClass="text-center">
                        {{ row.row_no }}
                      </TableData>
                      <TableData :currentIndex="rowIndex" :totalLength="rows.length" inputClass="text-center">
                        {{ row.terminal_id }}
                      </TableData>
                      <TableData :currentIndex="rowIndex" :totalLength="rows.length" inputClass="text-center">
                        <a
                          v-if="row.vend_code"
                          class="text-blue-700 hover:underline"
                          target="_blank"
                          :href="'/vends/customers?codes=' + row.vend_code + '&autoload=true'"
                        >
                          {{ row.vend_code }}
                        </a>
                        <span v-else class="text-gray-400">—</span>
                      </TableData>
                      <TableData :currentIndex="rowIndex" :totalLength="rows.length" inputClass="text-center">
                        {{ row.transaction_date }}
                      </TableData>
                      <TableData :currentIndex="rowIndex" :totalLength="rows.length" inputClass="text-center">
                        <span
                          v-if="row.time_is_partial"
                          class="text-amber-700"
                          title="Hour lost (file was re-saved in Excel) — matched by minute:second within the hour"
                        >
                          ??{{ row.transaction_time ? row.transaction_time.slice(2) : '' }}
                        </span>
                        <span v-else>{{ row.transaction_time }}</span>
                      </TableData>
                      <TableData :currentIndex="rowIndex" :totalLength="rows.length" inputClass="text-center">
                        {{ row.card_issuer }}
                      </TableData>
                      <TableData :currentIndex="rowIndex" :totalLength="rows.length" inputClass="text-right">
                        <span :class="row.is_reversal ? 'text-red-600 font-medium' : ''">{{ row.amount.toFixed(2) }}</span>
                        <span
                          v-if="row.is_reversal"
                          class="ml-1 inline-flex items-center rounded px-1 py-0.5 text-[10px] font-bold border bg-red-100 text-red-800 border-red-300"
                          title="Terminal reversal line (Reversal Code = Y)"
                        >
                          REVERSAL
                        </span>
                      </TableData>
                      <TableData :currentIndex="rowIndex" :totalLength="rows.length" inputClass="text-center">
                        <span
                          class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-bold border"
                          :class="rowStatusBadgeClass(row.status)"
                        >
                          {{ row.status_label }}
                        </span>
                      </TableData>
                      <TableData :currentIndex="rowIndex" :totalLength="rows.length" inputClass="text-left">
                        <!-- reversal line: points at the purchase line it undoes -->
                        <template v-if="row.is_reversal">
                          <template v-if="row.reverses_row">
                            <div>Reverses row #{{ row.reverses_row.row_no }}
                              <span v-if="row.reverses_row.report_id !== report.id" class="text-xs text-gray-500">(report {{ row.reverses_row.report_id }})</span>
                            </div>
                            <div class="text-xs text-gray-500">{{ row.match_time_delta }}s after the purchase</div>
                          </template>
                          <span v-else class="text-gray-400">—</span>
                        </template>
                        <template v-else-if="row.matched_txn">
                          <div>#{{ row.matched_txn.id }} · {{ row.matched_txn.transaction_datetime }}</div>
                          <div class="text-xs text-gray-500">
                            Δ {{ row.match_time_delta !== null ? row.match_time_delta + 's' : 'manual' }}
                            <span v-if="row.matched_txn.is_refunded" class="text-red-600 font-medium"> · refunded</span>
                            <span v-if="row.matched_txn.synced" class="text-green-600 font-medium"> · synced</span>
                          </div>
                          <div v-if="row.reversed_by_row" class="text-xs text-red-600 font-medium">
                            Reversed by row #{{ row.reversed_by_row.row_no }}
                            <span v-if="!row.matched_txn.is_refunded" class="text-gray-500 font-normal">→ marked refunded on Sync</span>
                          </div>
                          <div
                            v-else-if="row.matched_txn.auto_refund_source === 'card_terminal_reversal'"
                            class="text-xs text-amber-700"
                            title="mark1 inferred a reversal from the TRADE frame, but this report has no reversal line for it — the reader may have retained the credit instead"
                          >
                            ⚠ refunded by inference, no reversal in report
                          </div>
                        </template>
                        <span v-else class="text-gray-400">—</span>
                      </TableData>
                      <TableData :currentIndex="rowIndex" :totalLength="rows.length" inputClass="text-left">
                        <div v-if="row.resolution_note" class="text-xs text-gray-500 mb-1"
                          :title="row.resolution_note === 'All matching sales already claimed'
                            ? 'Every sale that fits this line is already held by another line — NETS charged more times than mark1 recorded sales here. Likely a double charge (customer tapped twice); check the two lines and refund if so.'
                            : (row.resolution_note === 'No matching sale in window'
                              ? 'mark1 has no card sale on this machine at this time/amount — the machine may have been offline (TRADE never arrived) or the terminal is bound to the wrong machine.'
                              : '')">
                          {{ row.resolution_note }}
                        </div>
                        <!-- candidates to pick from (ambiguous / claimed-elsewhere rows) -->
                        <div v-if="(row.status === 3 || row.status === 2) && row.candidates && row.candidates.length" class="space-y-1">
                          <div v-for="c in row.candidates" :key="c.vend_transaction_id" class="flex items-center space-x-1 text-xs">
                            <!-- A sale another line already holds cannot be picked: it would only bounce.
                                 Show who holds it — if THAT line is the wrong one, ignore it first. -->
                            <span
                              v-if="c.claimed_by_row"
                              class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold bg-gray-200 text-gray-600"
                              :title="'Held by row #' + c.claimed_by_row.row_no + (c.claimed_by_row.same_report ? '' : ' of report ' + c.claimed_by_row.report_id) + ' — NETS charged more times than mark1 recorded sales; check for a double charge'"
                            >
                              held by row #{{ c.claimed_by_row.row_no }}<span v-if="!c.claimed_by_row.same_report"> (report {{ c.claimed_by_row.report_id }})</span>
                            </span>
                            <Button
                              v-else
                              type="button" class="bg-green-500 hover:bg-green-600 px-2 py-1 text-xs text-white"
                              @click="pickCandidate(row, c)"
                            >
                              Pick
                            </Button>
                            <span>#{{ c.vend_transaction_id }} · {{ c.transaction_datetime }}<span v-if="c.other_vend" class="text-orange-700 font-medium"> · on machine {{ c.vend_code }}</span><span v-if="c.is_refunded" class="text-red-600"> · refunded</span></span>
                          </div>
                        </div>
                        <div v-if="(row.status === 2 || row.status === 3) && !row.is_reversal" class="flex items-center space-x-1 mt-1">
                          <input v-model="manualTxnId[row.id]" placeholder="Txn ID"
                            class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-xs border-gray-300 rounded-md w-24 px-2 py-1"
                            @keyup.enter="resolveManual(row)" />
                          <Button
                            type="button" class="bg-gray-300 hover:bg-gray-400 px-2 py-1 text-xs text-gray-800"
                            @click="resolveManual(row)"
                          >
                            Assign
                          </Button>
                          <Button
                            type="button" class="bg-gray-300 hover:bg-gray-400 px-2 py-1 text-xs text-gray-800"
                            @click="ignoreRow(row)"
                          >
                            Ignore
                          </Button>
                        </div>
                      </TableData>
                      </tr>
                <tr v-if="!rows.data.length">
                  <td colspan="11" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                      {{ rowStatus === 'queries' ? 'No open queries — everything matched, duplicated or ignored.' : 'No Results Found' }}
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator v-if="rows.data.length" :links="rows.links" :meta="rows.meta"></Paginator>
          </div>
      </div>
    </div>
  </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import Paginator from '@/Components/Paginator.vue';
import { ArrowPathIcon, CheckCircleIcon, EyeSlashIcon, TrashIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";

const props = defineProps({
  report: { type: Object, required: true },
  rows: { type: Object, required: true },
  rowFilters: { type: Object, default: () => ({}) },
  unboundTerminals: { type: Array, default: () => [] },
  suspectBindings: { type: Array, default: () => [] },
  statusLabels: { type: Object, default: () => ({}) },
})

const toast = useToast()
const rowStatus = ref(props.rowFilters.row_status ?? 'queries')

// Row status codes (CardSettlementRow::STATUS_*)
const chips = [
  { key: 'queries', label: 'Queries' },
  { key: '1', label: 'Matched' },
  { key: '2', label: 'Unmatched' },
  { key: '3', label: 'Ambiguous' },
  { key: 'reversals', label: 'Reversals' },
  { key: '5', label: 'Duplicates' },
  { key: '4', label: 'Ignored' },
  { key: 'all', label: 'All rows' },
]

function statusBadgeClass(status) {
  return {
    uploaded: 'bg-gray-100 text-gray-700 border-gray-300',
    matching: 'bg-amber-100 text-amber-800 border-amber-300',
    review: 'bg-blue-100 text-blue-800 border-blue-300',
    synced: 'bg-green-100 text-green-800 border-green-300',
    failed: 'bg-red-100 text-red-800 border-red-300',
  }[status] || 'bg-gray-100 text-gray-700 border-gray-300'
}

function rowStatusBadgeClass(status) {
  return {
    1: 'bg-green-100 text-green-800 border-green-300',
    2: 'bg-amber-100 text-amber-800 border-amber-300',
    3: 'bg-orange-100 text-orange-800 border-orange-300',
    4: 'bg-gray-100 text-gray-500 border-gray-300',
    5: 'bg-gray-100 text-gray-500 border-gray-300',
  }[status] || 'bg-gray-100 text-gray-700 border-gray-300'
}

// computed, not a const: Pick / Assign / Ignore reload props with preserveState,
// and the tile must follow the refreshed counts without a hard reload.
const queriesCount = computed(() => props.report.unmatched_count + props.report.ambiguous_count)

function pickStatus(key) {
  rowStatus.value = key
  router.get('/card-settlements/' + props.report.id, { row_status: key }, { preserveState: true, preserveScroll: true, replace: true })
}

function rematch() {
  if (props.report.status === 'matching') return
  router.post('/card-settlements/' + props.report.id + '/rematch', {}, {
    preserveScroll: true,
    onSuccess: () => toast.success("Rematch queued", { timeout: 3000 }),
  })
}

function sync() {
  if (props.report.status === 'matching' || !props.report.matched_count) return
  const approval = confirm('Stamp "settlement synced" onto ' + props.report.matched_count + ' matched transaction(s)?');
  if (!approval) {
      return;
  }
  router.post('/card-settlements/' + props.report.id + '/sync', {}, {
    preserveScroll: true,
    onSuccess: () => toast.success("Matched transactions synced", { timeout: 3000 }),
    onError: () => toast.error("Failed to sync", { timeout: 3000 }),
  })
}

function destroyReport() {
  const approval = confirm('Are you sure to delete this report and all its rows?');
  if (!approval) {
      return;
  }
  router.delete('/card-settlements/' + props.report.id, {
    onSuccess: () => toast.success("Report deleted successfully", { timeout: 3000 }),
    onError: () => toast.error("Failed to delete report", { timeout: 3000 }),
  })
}

function resolveTo(row, txnId) {
  router.post('/card-settlements/' + props.report.id + '/rows/' + row.id + '/resolve',
    { vend_transaction_id: txnId }, {
      preserveScroll: true,
      onSuccess: () => toast.success("Row resolved", { timeout: 3000 }),
      onError: () => toast.error("Failed to resolve row", { timeout: 3000 }),
    })
}

// A candidate on a DIFFERENT machine than the terminal is bound to means the
// binding sheet is probably wrong — make the user say so before the line moves.
function pickCandidate(row, c) {
  if (c.other_vend && String(c.vend_code) !== String(row.vend_code)) {
    const approval = confirm(
      'Moving this line from machine ' + row.vend_code + ' to machine ' + c.vend_code + '.\n\n' +
      'Terminal ' + row.terminal_id + ' is bound to ' + row.vend_code + ' but this sale is on ' + c.vend_code + '. ' +
      'If the terminal has moved, also update it on Card Terminal Bindings so future reports match on their own.\n\n' +
      'Do you confirm?'
    )
    if (!approval) {
      return
    }
  }
  resolveTo(row, c.vend_transaction_id)
}

// Batch ignore: checkboxes on the open queries (Unmatched / Ambiguous) of the
// current page; the selection is per page and cleared once applied.
const selected = ref([])
const isIgnorable = (row) => (row.status === 2 || row.status === 3) && !row.is_reversal
const ignorableRows = computed(() => props.rows.data.filter(isIgnorable))
const allSelected = computed(() => ignorableRows.value.length > 0 && ignorableRows.value.every((r) => selected.value.includes(r.id)))
function toggleRow(id) {
  selected.value = selected.value.includes(id) ? selected.value.filter((x) => x !== id) : [...selected.value, id]
}
function toggleAll() {
  selected.value = allSelected.value ? [] : ignorableRows.value.map((r) => r.id)
}
function ignoreSelected() {
  if (!selected.value.length) return
  const approval = confirm('Ignore ' + selected.value.length + ' selected line(s)? They will leave the queries list and never match a sale.');
  if (!approval) {
      return;
  }
  router.post('/card-settlements/' + props.report.id + '/rows/ignore-batch', { row_ids: selected.value }, {
    preserveScroll: true,
    onSuccess: () => { selected.value = []; toast.success("Selected lines ignored", { timeout: 3000 }) },
    onError: () => toast.error("Failed to ignore the selected lines", { timeout: 3000 }),
  })
}

const manualTxnId = ref({})
function resolveManual(row) {
  const id = parseInt(manualTxnId.value[row.id], 10)
  if (!id) return
  resolveTo(row, id)
}

function ignoreRow(row) {
  router.post('/card-settlements/' + props.report.id + '/rows/' + row.id + '/ignore', {}, {
    preserveScroll: true,
    onSuccess: () => toast.success("Row ignored", { timeout: 3000 }),
  })
}
</script>
