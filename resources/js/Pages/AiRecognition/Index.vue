<template>

  <Head title="AI Recognition" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        AI Recognition
        <span class="ml-2 align-middle inline-flex items-center rounded px-1.5 py-0.5 text-xs font-bold border bg-sky-100 text-sky-800 border-sky-300">
          Smart Freezer
        </span>
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-3 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
        <p class="text-xs text-gray-500">
          Each row is one Smart Freezer door session: the camera videos Zijia pushed, what their AI saw
          taken, and how that compares with what the customer paid for. A sale paid on a T05 card terminal
          is charged by this verdict in one capture (above the checkout hold too); NETS and QR sales were charged
          at the machine. Nothing here changes stock.
        </p>

        <!-- Filters -->
        <div class="grid grid-cols-1 md:grid-cols-6 gap-2 mt-3">
          <div class="md:col-span-2">
            <SearchInput placeholderStr="Order no / device / machine" v-model="filters.search" @keyup.enter="onSearchFilterUpdated()">
              Search
            </SearchInput>
          </div>
          <div class="md:col-span-2">
            <SearchInput placeholderStr="Machine ID (e.g. 50001, or 50001,2009)" v-model="filters.codes" @keyup.enter="onSearchFilterUpdated()">
              Machine ID
            </SearchInput>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">
              Status
            </label>
            <MultiSelect
              v-model="filters.status"
              :options="statusOptions"
              trackBy="id"
              valueProp="id"
              label="name"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
            >
            </MultiSelect>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">
              Verdict
            </label>
            <MultiSelect
              v-model="filters.verdict"
              :options="verdictOptions"
              trackBy="id"
              valueProp="id"
              label="name"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
            >
            </MultiSelect>
          </div>
          <div class="md:col-span-2">
            <DatePicker v-model="filters.date_from">
              Date From
            </DatePicker>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">
              Time From
            </label>
            <input type="time" v-model="filters.time_from" @keyup.enter="onSearchFilterUpdated()"
              class="mt-1 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md"
            />
          </div>
          <div class="md:col-span-2">
            <DatePicker v-model="filters.date_to" :minDate="filters.date_from">
              Date To
            </DatePicker>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">
              Time To
            </label>
            <input type="time" v-model="filters.time_to" @keyup.enter="onSearchFilterUpdated()"
              class="mt-1 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md"
            />
          </div>
        </div>

        <div class="flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5">
          <div class="mt-3">
            <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
              @click="onSearchFilterUpdated()"
            >
              <MagnifyingGlassIcon class="h-4 w-4" aria-hidden="true"/>
              <span>
                Search
              </span>
            </Button>
          </div>
          <div class="flex flex-col space-y-2">
            <p class="text-sm text-gray-700 leading-5 flex space-x-1">
              <span>Showing</span>
              <span class="font-medium">{{ recognitions.meta.from ?? 0 }}</span>
              <span>to</span>
              <span class="font-medium">{{ recognitions.meta.to ?? 0 }}</span>
              <span>of</span>
              <span class="font-medium">{{ recognitions.meta.total }}</span>
              <span>results</span>
            </p>
            <MultiSelect
              v-model="filters.numberPerPage"
              :options="numberPerPageOptions"
              trackBy="id"
              valueProp="id"
              label="value"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
              @selected="onSearchFilterUpdated"
            >
            </MultiSelect>
          </div>
        </div>
      </div>

      <div class="mt-6 flex flex-col">
        <div class="-my-2 -mx-3 sm:-mx-6 lg:-mx-8">
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll">
            <table class="min-w-full border-separate" style="border-spacing: 0">
              <thead class="bg-gray-100">
                <tr class="divide-x divide-gray-200">
                  <TableHead>
                    #
                  </TableHead>
                  <TableHeadSort modelName="created_at" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('created_at')">
                    Door Session
                  </TableHeadSort>
                  <TableHead>
                    Machine
                  </TableHead>
                  <TableHead>
                    Order No
                  </TableHead>
                  <TableHead>
                    Videos
                  </TableHead>
                  <TableHeadSort modelName="status" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('status')">
                    Status
                  </TableHeadSort>
                  <TableHead>
                    AI Saw Taken
                  </TableHead>
                  <TableHeadSort modelName="verdict" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('verdict')">
                    Verdict vs Paid
                  </TableHeadSort>
                  <TableHead>
                    Sale / Card charge
                  </TableHead>
                </tr>
              </thead>
              <tbody class="bg-white">
                <tr v-for="(row, rowIndex) in recognitions.data" :key="row.id" class="divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100 align-top">
                  <TableData :currentIndex="rowIndex" :totalLength="recognitions.data.length" inputClass="text-center">
                    {{ recognitions.meta.from + rowIndex }}
                  </TableData>
                  <TableData :currentIndex="rowIndex" :totalLength="recognitions.data.length" inputClass="text-center">
                    <div class="flex flex-col space-y-1">
                      <span>{{ row.created_at }}</span>
                      <span v-if="row.completed_at" class="text-xs text-gray-500" title="When the AI result came back">
                        result {{ row.completed_at.substring(11) }}
                      </span>
                    </div>
                  </TableData>
                  <TableData :currentIndex="rowIndex" :totalLength="recognitions.data.length" inputClass="text-center">
                    <div class="flex flex-col space-y-1">
                      <span v-if="row.vend_label" class="font-medium">{{ row.vend_label }}</span>
                      <span v-else class="text-amber-700 text-xs" title="The push named no freezer of ours">Not matched</span>
                      <span v-if="row.device_id" class="text-xs text-gray-500" title="Device no / IMEI in the push">{{ row.device_id }}</span>
                    </div>
                  </TableData>
                  <TableData :currentIndex="rowIndex" :totalLength="recognitions.data.length" inputClass="text-left">
                    <div class="flex flex-col space-y-1 text-xs">
                      <span class="whitespace-nowrap" title="Zijia's order no for the door session (tradeId sent to the AI)">
                        <span class="text-gray-500">Zijia:</span> <span class="font-mono">{{ row.trade_id }}</span>
                      </span>
                      <span v-if="row.session_ref" class="whitespace-nowrap" title="Our txnRef (SFREF on the TRADE)">
                        <span class="text-gray-500">Ours:</span> <span class="font-mono">{{ row.session_ref }}</span>
                      </span>
                      <span v-if="row.request_id" class="whitespace-nowrap" title="The AI service's request id">
                        <span class="text-gray-500">AI req:</span> <span class="font-mono">{{ row.request_id }}</span>
                      </span>
                    </div>
                  </TableData>
                  <TableData :currentIndex="rowIndex" :totalLength="recognitions.data.length" inputClass="text-left">
                    <div class="flex flex-col space-y-1 text-xs">
                      <a v-for="(url, urlIndex) in row.videos" :key="url" :href="url" target="_blank" rel="noopener noreferrer"
                        class="text-blue-600 hover:underline whitespace-nowrap" :title="url"
                      >
                        ▶ {{ videoLabel(url, urlIndex) }}
                      </a>
                      <span v-if="!row.videos.length" class="text-gray-400">—</span>
                      <span v-if="row.pushes > 1" class="text-gray-400" title="Zijia pushed this session more than once">{{ row.pushes }} pushes</span>
                    </div>
                  </TableData>
                  <TableData :currentIndex="rowIndex" :totalLength="recognitions.data.length" inputClass="text-left">
                    <div class="flex flex-col space-y-1">
                      <span class="inline-flex w-fit items-center rounded px-1.5 py-0.5 text-xs font-bold border" :class="statusBadgeClass(row.status)">
                        {{ statusLabels[row.status] || row.status }}
                      </span>
                      <span v-if="row.algorithm_status" class="text-xs text-gray-700" title="The AI's own status">AI: {{ row.algorithm_status }}</span>
                      <span v-if="row.status_reason && row.status_reason !== ('algorithm: ' + row.algorithm_status)" class="text-xs text-gray-500">{{ row.status_reason }}</span>
                      <span v-if="row.callback_verified === false" class="text-xs text-red-700">Result signature NOT verified</span>
                    </div>
                  </TableData>
                  <TableData :currentIndex="rowIndex" :totalLength="recognitions.data.length" inputClass="text-left">
                    <div v-if="row.algorithm_status !== null" class="flex flex-col space-y-1 text-xs">
                      <span v-for="item in row.items" :key="item.code">
                        {{ item.number }} × {{ item.name || item.code }}
                        <span v-if="item.name" class="text-gray-400 font-mono">{{ item.code }}</span>
                      </span>
                      <span v-if="!row.items.length" class="text-gray-500">Nothing</span>
                    </div>
                    <span v-else class="text-gray-400">—</span>
                  </TableData>
                  <TableData :currentIndex="rowIndex" :totalLength="recognitions.data.length" inputClass="text-left">
                    <div v-if="row.verdict" class="flex flex-col space-y-1">
                      <span class="inline-flex w-fit items-center rounded px-1.5 py-0.5 text-xs font-bold border" :class="verdictBadgeClass(row.verdict)">
                        {{ verdictLabels[row.verdict] || row.verdict }}
                      </span>
                      <span v-for="(line, lineIndex) in row.verdict_lines" :key="lineIndex" class="text-xs"
                        :class="line.delta ? 'text-red-700 font-medium' : 'text-gray-600'"
                      >
                        {{ line.name || line.code || ('product ' + line.product_id) }}: paid {{ line.paid }}, taken {{ line.taken }}
                      </span>
                    </div>
                    <span v-else class="text-gray-400">—</span>
                  </TableData>
                  <TableData :currentIndex="rowIndex" :totalLength="recognitions.data.length" inputClass="text-center">
                    <div v-if="row.sale" class="flex flex-col space-y-1 text-xs">
                      <a :href="saleUrl(row.sale)" target="_blank" class="text-blue-600 hover:underline font-mono whitespace-nowrap">{{ row.sale.order_id }}</a>
                      <span v-if="row.sale.date" class="text-gray-500 whitespace-nowrap" title="Sale time (the TRADE)">{{ row.sale.date }} {{ row.sale.time }}</span>
                      <span>{{ formatCents(row.sale.amount) }}</span>
                    </div>
                    <span v-else class="text-gray-400">—</span>
                    <div v-if="row.card" class="mt-1 flex flex-col space-y-0.5 text-xs" :title="row.card.reason || ''">
                      <span class="inline-flex w-fit mx-auto items-center rounded px-1.5 py-0.5 font-bold border" :class="cardBadgeClass(row.card)">
                        {{ cardLabel(row.card) }}
                      </span>
                      <span v-if="row.card.increment_refused" class="text-gray-600 whitespace-nowrap">Payrallel refused the increase; hold charged</span>
                      <span v-if="row.card.owed_cents" class="text-red-700 whitespace-nowrap">Not charged yet {{ formatCents(row.card.owed_cents) }}</span>
                      <span v-if="row.card.error" class="text-red-700">{{ row.card.error }}</span>
                    </div>
                  </TableData>
                </tr>
                <tr v-if="!recognitions.data.length">
                  <td colspan="9" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                    No Results Found
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator v-if="recognitions.data.length" :links="recognitions.links" :meta="recognitions.meta"></Paginator>
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import DatePicker from '@/Components/DatePicker.vue';
import Paginator from '@/Components/Paginator.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import SearchInput from '@/Components/SearchInput.vue';
import { MagnifyingGlassIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ref, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
  recognitions: Object,
  statuses: Array,
  verdicts: Array,
  filters: Object,
})

const statusLabels = {
  pending: 'Waiting',
  submitting: 'Sending…',
  submitted: 'Sent to AI',
  completed: 'Result in',
  failed: 'Failed',
}

const verdictLabels = {
  match: 'Match',
  took_more: 'Took more than paid',
  took_less: 'Took less than paid',
  mixed: 'Mixed',
  unrecognised: 'Unrecognised',
  incomplete: 'Cannot judge',
}

function statusBadgeClass(status) {
  return {
    pending: 'bg-gray-100 text-gray-700 border-gray-300',
    submitting: 'bg-amber-100 text-amber-800 border-amber-300',
    submitted: 'bg-blue-100 text-blue-800 border-blue-300',
    completed: 'bg-green-100 text-green-800 border-green-300',
    failed: 'bg-red-100 text-red-800 border-red-300',
  }[status] || 'bg-gray-100 text-gray-700 border-gray-300'
}

function verdictBadgeClass(verdict) {
  return {
    match: 'bg-green-100 text-green-800 border-green-300',
    took_more: 'bg-red-100 text-red-800 border-red-300',
    took_less: 'bg-amber-100 text-amber-800 border-amber-300',
    mixed: 'bg-red-100 text-red-800 border-red-300',
    unrecognised: 'bg-gray-100 text-gray-700 border-gray-300',
    incomplete: 'bg-gray-100 text-gray-700 border-gray-300',
  }[verdict] || 'bg-gray-100 text-gray-700 border-gray-300'
}

// "…-d1c3-25f-1280x720.mp4" → "Door 1 · Cam 3"; anything else → "Video n".
function videoLabel(url, index) {
  const m = url.match(/-d(\d+)c(\d+)-/)
  return m ? 'Door ' + m[1] + ' · Cam ' + m[2] : 'Video ' + (index + 1)
}

function saleUrl(sale) {
  const q = new URLSearchParams({ order_id: sale.order_id })
  if (sale.date) {
    q.set('date_from', sale.date)
    q.set('date_to', sale.date)
  }
  return '/vends/transactions?' + q.toString()
}

// Money is integer cents; divide only for display.
function formatCents(cents) {
  return (cents / 100).toFixed(2)
}

// The session's T05 hold, charged by the verdict (CardPaymentService::settleAwaitingAi).
function cardLabel(card) {
  if (card.uncertain_cents) return `T05 charge ${formatCents(card.uncertain_cents)} unconfirmed — check Payrallel`
  if (card.state === 'awaiting_ai') return `T05 hold ${formatCents(card.hold_cents)} · awaiting AI`
  if (card.state === 'captured') return `T05 charged ${formatCents(card.captured_cents)} (hold ${formatCents(card.hold_cents)})`
  if (card.state === 'voided') return `T05 hold ${formatCents(card.hold_cents)} released`
  return `T05 ${card.state}`
}

function cardBadgeClass(card) {
  if (card.uncertain_cents) return 'bg-red-50 text-red-700 border-red-300'
  if (card.state === 'awaiting_ai') return 'bg-amber-50 text-amber-800 border-amber-300'
  if (card.state === 'voided') return 'bg-gray-100 text-gray-700 border-gray-300'
  if (card.owed_cents) return 'bg-red-50 text-red-700 border-red-300'
  return 'bg-green-50 text-green-700 border-green-300'
}

const filters = ref({
  search: props.filters.search || '',
  codes: props.filters.codes || '',
  status: props.filters.status || 'all',
  verdict: props.filters.verdict || 'all',
  date_from: props.filters.date_from || '',
  date_to: props.filters.date_to || '',
  time_from: props.filters.time_from || '',
  time_to: props.filters.time_to || '',
  sortKey: props.filters.sortKey || 'created_at',
  // Query-string round-trips turn the boolean into "true"/"false" strings.
  sortBy: String(props.filters.sortBy ?? false) === 'true',
  numberPerPage: 100,
})
const numberPerPageOptions = ref([])
const statusOptions = ref([])
const verdictOptions = ref([])

onMounted(() => {
  numberPerPageOptions.value = [
    { id: 100, value: 100 },
    { id: 200, value: 200 },
    { id: 500, value: 500 },
    { id: 'All', value: 'All' },
  ]
  filters.value.numberPerPage = numberPerPageOptions.value[0]
  statusOptions.value = [
    { id: 'all', name: 'All' },
    ...props.statuses.map((s) => ({ id: s, name: statusLabels[s] || s })),
  ]
  filters.value.status = statusOptions.value.find((o) => o.id === filters.value.status) || statusOptions.value[0]
  verdictOptions.value = [
    { id: 'all', name: 'All' },
    { id: 'none', name: 'No verdict yet' },
    ...props.verdicts.map((v) => ({ id: v, name: verdictLabels[v] || v })),
  ]
  filters.value.verdict = verdictOptions.value.find((o) => o.id === filters.value.verdict) || verdictOptions.value[0]
})

function onSearchFilterUpdated() {
  router.get('/ai-recognition', {
    ...filters.value,
    status: filters.value.status ? filters.value.status.id : 'all',
    verdict: filters.value.verdict ? filters.value.verdict.id : 'all',
    numberPerPage: filters.value.numberPerPage.id,
  }, {
    preserveState: true,
    replace: true,
  })
}

function sortTable(sortKey) {
  filters.value.sortKey = sortKey
  filters.value.sortBy = !filters.value.sortBy
  onSearchFilterUpdated()
}
</script>
