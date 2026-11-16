<template>
  <Head :title="$t('Grab Performance')" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ $t('Grab Performance') }}
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-3 sm:-mx-6 lg:-mx-8 rounded-md border border-sky-200 bg-sky-50 my-3 px-3 py-2 text-sm text-sky-900">
        <p>{{ $t('How each Grab store sells, to decide which machines stay on Grab and which move. A store counts the machine on it now: delivered Grab orders less Grab promo, the same figures as Grab Stores.') }}</p>
      </div>

      <!-- Filters -->
      <div class="-mx-3 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 py-3">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-2">
          <OperatorFilter v-model="filters.operators" />
          <SearchInput :placeholderStr="$t('4 to 5 Digits Number')" v-model="filters.codes" @keyup.enter="search()">
            {{ $t('Machine ID') }}
            <span class="text-[9px]">{{ $t('("," for multiple)') }}</span>
          </SearchInput>
          <div>
            <label class="block text-sm font-medium text-gray-700">{{ $t('Period') }}</label>
            <select v-model="filters.days" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500" @change="search()">
              <option v-for="days in periods" :key="days" :value="days">{{ $t('Last :count days', { count: days }) }}</option>
            </select>
          </div>
        </div>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
          <Button
            class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-2 text-sm font-medium text-white hover:bg-green-600"
            @click="search()"
          >
            <MagnifyingGlassIcon class="h-4 w-4" aria-hidden="true" />
            <span>{{ $t('Search') }}</span>
          </Button>
          <p class="text-sm text-gray-600">
            {{ $t(':from to :to, compared with :previousFrom to :previousTo', { from: day(report.from), to: day(report.to), previousFrom: day(report.previous_from), previousTo: day(report.previous_to) }) }}
          </p>
        </div>
      </div>

      <!-- Summary -->
      <div class="-mx-3 sm:-mx-6 lg:-mx-8 grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-2 my-3">
        <div class="rounded-md border bg-white px-3 py-2">
          <div class="text-xs text-gray-500">{{ $t('Grab sales') }}</div>
          <div class="text-xl font-semibold text-gray-900">{{ money(summary.sales) }}</div>
          <Change :pct="summary.sales_change_pct" :previous="money(summary.previous_sales)" />
        </div>
        <div class="rounded-md border bg-white px-3 py-2">
          <div class="text-xs text-gray-500">{{ $t('Orders') }}</div>
          <div class="text-xl font-semibold text-gray-900">{{ summary.orders }}</div>
          <Change :pct="summary.orders_change_pct" :previous="String(summary.previous_orders)" />
        </div>
        <div class="rounded-md border bg-white px-3 py-2">
          <div class="text-xs text-gray-500">{{ $t('Average order') }}</div>
          <div class="text-xl font-semibold text-gray-900">{{ summary.average_order === null ? '—' : money(summary.average_order) }}</div>
          <div class="text-xs text-gray-500">{{ $t(':amount a day', { amount: money(Math.round(summary.sales / report.days)) }) }}</div>
        </div>
        <div class="rounded-md border bg-white px-3 py-2">
          <div class="text-xs text-gray-500">{{ $t('Stores selling') }}</div>
          <div class="text-xl font-semibold text-gray-900">{{ summary.stores_selling }}</div>
          <div class="text-xs text-gray-500">{{ $t(':count with orders', { count: summary.stores_with_orders }) }}</div>
        </div>
        <button type="button" class="rounded-md border bg-white px-3 py-2 text-left hover:bg-red-50" @click="view = 'no_orders'">
          <div class="text-xs text-gray-500">{{ $t('Selling, no orders') }}</div>
          <div class="text-xl font-semibold" :class="summary.stores_no_orders ? 'text-red-700' : 'text-gray-900'">{{ summary.stores_no_orders }}</div>
          <div class="text-xs text-gray-500">{{ $t('On Grab the whole period') }}</div>
        </button>
        <button type="button" class="rounded-md border bg-white px-3 py-2 text-left hover:bg-amber-50" @click="view = 'dropping'">
          <div class="text-xs text-gray-500">{{ $t('Dropping') }}</div>
          <div class="text-xl font-semibold" :class="summary.stores_dropping ? 'text-amber-700' : 'text-gray-900'">{{ summary.stores_dropping }}</div>
          <div class="text-xs text-gray-500">{{ $t('Under half the period before') }}</div>
        </button>
      </div>

      <!-- Daily sales of all listed stores -->
      <div class="-mx-3 sm:-mx-6 lg:-mx-8 rounded-md border bg-white my-3 px-3 py-3">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
          <h3 class="text-sm font-semibold text-gray-800">{{ $t('Grab sales per day') }}</h3>
          <p class="text-sm text-gray-600">
            <template v-if="hovered">
              {{ day(hovered.date) }} · <b>{{ money(hovered.sales) }}</b> · {{ $tc(':count order|:count orders', hovered.orders) }}
            </template>
            <template v-else>{{ $t('Point at a day for its figures') }}</template>
          </p>
        </div>
        <svg :viewBox="`0 0 ${chartW} ${chartH}`" class="mt-2 block h-40 w-full" preserveAspectRatio="none" @mouseleave="hovered = null">
          <line :x1="0" :x2="chartW" :y1="chartH - 0.5" :y2="chartH - 0.5" class="stroke-gray-300" stroke-width="1" vector-effect="non-scaling-stroke" />
          <line :x1="0" :x2="chartW" :y1="chartY(averageDay)" :y2="chartY(averageDay)" class="stroke-indigo-300" stroke-width="1" stroke-dasharray="4 3" vector-effect="non-scaling-stroke" />
          <g v-for="(point, i) in report.daily" :key="point.date" @mouseenter="hovered = point">
            <rect :x="i * slot" :y="0" :width="slot" :height="chartH" fill="transparent" />
            <rect
              :x="i * slot + slot * 0.15"
              :width="slot * 0.7"
              :y="chartY(point.sales)"
              :height="Math.max(point.sales ? 1 : 0, chartH - chartY(point.sales))"
              :class="hovered && hovered.date === point.date ? 'fill-indigo-700' : 'fill-indigo-500'"
            />
          </g>
        </svg>
        <div class="mt-1 flex justify-between text-xs text-gray-500">
          <span>{{ day(report.from) }}</span>
          <span class="text-indigo-500">{{ $t('Dashed line: :amount a day on average', { amount: money(averageDay) }) }}</span>
          <span>{{ day(report.to) }}</span>
        </div>
      </div>

      <!-- Stores -->
      <div class="-mx-3 sm:-mx-6 lg:-mx-8 my-3 flex flex-wrap gap-2">
        <button
          v-for="chip in viewChips"
          :key="chip.key"
          type="button"
          class="inline-flex items-center gap-1 rounded-full border px-3 py-1 text-sm"
          :class="view === chip.key ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-300 bg-white text-gray-700 hover:bg-gray-50'"
          @click="view = chip.key"
        >
          <span>{{ chip.name }}</span>
          <span class="font-semibold">{{ chip.count }}</span>
        </button>
      </div>

      <div class="-mx-3 sm:-mx-6 lg:-mx-8 shadow-sm ring-1 ring-black ring-opacity-5 overflow-x-auto bg-white">
        <table class="min-w-full border-separate" style="border-spacing: 0">
          <thead class="bg-gray-100">
            <tr class="divide-x divide-gray-200">
              <TableHead>#</TableHead>
              <TableHead>{{ $t('Grab store') }}</TableHead>
              <TableHead>{{ $t('Machine') }}</TableHead>
              <TableHead>{{ $t('Status') }}</TableHead>
              <TableHead v-for="column in sortColumns" :key="column.key">
                <button type="button" class="inline-flex items-center gap-1 hover:text-indigo-700" :title="$t('Sort by this, highest first')" @click="sortBy(column.key)">
                  <span>{{ column.name }}</span>
                  <span :class="sort === column.key ? 'text-indigo-700' : 'text-gray-300'">▼</span>
                </button>
              </TableHead>
              <TableHead>{{ $t('Sales per day') }}</TableHead>
            </tr>
          </thead>
          <tbody class="bg-white">
            <tr v-for="(store, index) in rows" :key="store.id" class="divide-x divide-gray-200 align-top" :class="index % 2 ? 'bg-gray-50' : 'bg-white'">
              <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-center">{{ index + 1 }}</TableData>
              <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-left">
                <a :href="`/grab-stores?search=${encodeURIComponent(store.ref_number)}`" target="_blank" class="font-mono whitespace-nowrap text-blue-700 hover:underline">{{ store.ref_number }}</a>
                <div class="text-xs text-gray-500">{{ store.operator }}</div>
                <div v-if="store.name" class="max-w-xs text-xs text-gray-600">{{ store.name }}</div>
              </TableData>
              <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-left">
                <template v-if="store.binding">
                  <MachineIdLink :vend="store.binding.vend" :code="store.binding.vend ? null : store.binding.vend_code" />
                  <a v-if="store.binding.site_id" :href="`/customers/${store.binding.site_id}/edit`" target="_blank" class="block max-w-xs text-xs text-blue-700 hover:underline">{{ store.binding.site }}</a>
                  <div v-else class="max-w-xs text-xs text-gray-600">{{ store.binding.site }}</div>
                  <div class="text-xs text-gray-500">{{ $t('Since :time', { time: day(store.binding.since) }) }}</div>
                </template>
                <span v-else class="text-gray-400">{{ $t('No machine') }}</span>
              </TableData>
              <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-center">
                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" :class="statusClass[store.status]">{{ statusNames[store.status] }}</span>
                <div v-if="store.flag" class="mt-1">
                  <span class="inline-flex rounded px-1.5 py-0.5 text-xs font-semibold" :class="flagClass[store.flag]" :title="flagHints[store.flag]">{{ flagNames[store.flag] }}</span>
                </div>
                <div v-if="store.flag === 'new' && store.binding" class="mt-1 text-xs text-gray-500">
                  {{ $tc('Only :count day on this machine|Only :count days on this machine', store.binding.days_on_machine) }}
                </div>
              </TableData>
              <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-right whitespace-nowrap">
                <div class="font-semibold">{{ money(store.sales) }}</div>
                <div class="text-xs text-gray-500">{{ $t('before: :amount', { amount: money(store.previous_sales) }) }}</div>
              </TableData>
              <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-right whitespace-nowrap">
                <span v-if="store.change_pct === null" class="text-gray-400" :title="$t('No figure: the period before had no sales, or the machine was not on this store for all of it')">—</span>
                <span v-else class="font-semibold" :class="store.change_pct >= 0 ? 'text-green-700' : 'text-red-700'">
                  {{ store.change_pct >= 0 ? '▲' : '▼' }} {{ Math.abs(store.change_pct) }}%
                </span>
              </TableData>
              <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-right whitespace-nowrap">
                <div class="font-semibold">{{ store.orders }}</div>
                <div class="text-xs text-gray-500">{{ $t('before: :count', { count: store.previous_orders }) }}</div>
              </TableData>
              <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-right whitespace-nowrap">
                {{ store.average_order === null ? '—' : money(store.average_order) }}
              </TableData>
              <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-right whitespace-nowrap">
                {{ $t(':count of :days', { count: store.days_with_orders, days: report.days }) }}
              </TableData>
              <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-center whitespace-nowrap">
                <span v-if="store.last_order_at" :class="ageClass(store.last_order_at)">{{ day(store.last_order_at) }}</span>
                <span v-else class="text-gray-400">{{ $t('Never') }}</span>
              </TableData>
              <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-center">
                <svg :viewBox="`0 0 ${store.daily.length} 20`" class="block h-8 w-40" preserveAspectRatio="none" aria-hidden="true">
                  <line x1="0" :x2="store.daily.length" y1="19.75" y2="19.75" class="stroke-gray-300" stroke-width="0.5" vector-effect="non-scaling-stroke" />
                  <rect
                    v-for="(point, i) in store.daily"
                    :key="point.date"
                    :x="i + 0.15"
                    width="0.7"
                    :y="20 - barHeight(point.sales)"
                    :height="barHeight(point.sales)"
                    class="fill-indigo-500"
                  />
                </svg>
                <div class="text-[10px] text-gray-400" :title="$t('Each bar is one day; all rows share one scale')">{{ $t('max :amount a day', { amount: money(rowMax) }) }}</div>
              </TableData>
            </tr>
            <tr v-if="!rows.length">
              <td colspan="11" class="py-6 text-center text-sm text-gray-500">{{ $t('No Grab stores match.') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import MachineIdLink from '@/Components/MachineIdLink.vue';
import OperatorFilter from '@/Components/OperatorFilter.vue';
import SearchInput from '@/Components/SearchInput.vue';
import TableData from '@/Components/TableData.vue';
import TableHead from '@/Components/TableHead.vue';
import { MagnifyingGlassIcon } from '@heroicons/vue/20/solid';
import { Head, router, usePage } from '@inertiajs/vue3';
import moment from 'moment';
import { computed, h, ref } from 'vue';
import { t } from '@/i18n';

const props = defineProps({
  report: Object,
  periods: { type: Array, default: () => [7, 14, 30, 60, 90] },
  filters: Object,
})

const page = usePage()
const summary = computed(() => props.report.summary)

// The URL carries operator ids; the filter's tags need the option objects.
const operatorChoices = [{ id: 'all', full_name: t('All') }, ...(page.props.operatorFilterOptions ?? [])]
const filters = ref({
  operators: [].concat(props.filters.operators ?? [])
    .map(id => operatorChoices.find(option => option.id == id))
    .filter(Boolean),
  codes: props.filters.codes ?? '',
  days: props.filters.days ?? 30,
})

function search() {
  router.get('/grab-performance', {
    operators: filters.value.operators.map(operator => operator.id),
    codes: filters.value.codes,
    days: filters.value.days,
  }, { preserveState: true, replace: true })
}

function money(cents) {
  return '$' + ((cents ?? 0) / 100).toFixed(2)
}

function day(value) {
  return value ? moment(value).format('D MMM YY') : '—'
}

// The change against the period before, green up / red down.
const Change = (changeProps) => {
  if (changeProps.pct === null || changeProps.pct === undefined) {
    return h('div', { class: 'text-xs text-gray-500' }, t('before: :amount', { amount: changeProps.previous }))
  }
  const up = changeProps.pct >= 0
  return h('div', { class: 'text-xs' }, [
    h('span', { class: up ? 'font-semibold text-green-700' : 'font-semibold text-red-700' }, `${up ? '▲' : '▼'} ${Math.abs(changeProps.pct)}%`),
    h('span', { class: 'text-gray-500' }, ' ' + t('before: :amount', { amount: changeProps.previous })),
  ])
}
Change.props = ['pct', 'previous']

// Daily chart of every listed store.
const chartW = 600
const chartH = 100
const hovered = ref(null)
const slot = computed(() => chartW / Math.max(1, props.report.daily.length))
const chartMax = computed(() => Math.max(1, ...props.report.daily.map(point => point.sales)) * 1.1)
const averageDay = computed(() => Math.round(summary.value.sales / Math.max(1, props.report.days)))
const chartY = (cents) => chartH - (cents / chartMax.value) * chartH

// Row graphs share one scale, so a busy store looks busy next to a quiet one.
const rowMax = computed(() => Math.max(1, ...props.report.stores.flatMap(store => store.daily.map(point => point.sales))))
const barHeight = (cents) => (cents > 0 ? Math.max(1, (cents / rowMax.value) * 19.5) : 0)

const statusNames = {
  selling: t('Selling'),
  paused: t('Paused'),
  off: t('Off'),
  no_machine: t('No machine'),
}
const statusClass = {
  selling: 'bg-green-100 text-green-800',
  paused: 'bg-amber-100 text-amber-800',
  off: 'bg-gray-200 text-gray-700',
  no_machine: 'bg-gray-100 text-gray-500',
}
const flagNames = {
  no_orders: t('No orders'),
  dropping: t('Dropping'),
  new: t('New on this machine'),
}
const flagHints = {
  no_orders: t('Selling on ConnectVend for the whole period, yet no Grab order. Check the store on Grab: it may be closed there.'),
  dropping: t('Sold under half of the period before, on the same machine.'),
  new: t('The machine has been on this store for less than the period, so its figures are not comparable yet.'),
}
const flagClass = {
  no_orders: 'bg-red-50 text-red-700',
  dropping: 'bg-amber-50 text-amber-800',
  new: 'bg-sky-50 text-sky-800',
}

function ageClass(at) {
  const days = moment().diff(moment(at), 'days')
  return days > 30 ? 'text-red-700 font-semibold' : days > 7 ? 'text-amber-700' : 'text-gray-700'
}

// Which stores the table lists.
const view = ref('all')
const viewChips = computed(() => {
  const stores = props.report.stores
  return [
    { key: 'all', name: t('All'), count: stores.length },
    { key: 'selling', name: t('Selling'), count: stores.filter(store => store.status === 'selling').length },
    { key: 'attention', name: t('Needs a look'), count: stores.filter(store => ['no_orders', 'dropping'].includes(store.flag)).length },
    { key: 'no_orders', name: t('No orders'), count: stores.filter(store => store.flag === 'no_orders').length },
    { key: 'dropping', name: t('Dropping'), count: stores.filter(store => store.flag === 'dropping').length },
    { key: 'new', name: t('New on this machine'), count: stores.filter(store => store.flag === 'new').length },
  ]
})

const sortColumns = [
  { key: 'sales', name: t('Grab sales') },
  { key: 'change_pct', name: t('Change') },
  { key: 'orders', name: t('Orders') },
  { key: 'average_order', name: t('Average order') },
  { key: 'days_with_orders', name: t('Days with orders') },
  { key: 'last_order_at', name: t('Last order') },
]
const sort = ref('sales')

function sortBy(key) {
  sort.value = key
}

const rows = computed(() => {
  const listed = props.report.stores.filter(store => {
    switch (view.value) {
      case 'selling': return store.status === 'selling'
      case 'attention': return ['no_orders', 'dropping'].includes(store.flag)
      case 'no_orders':
      case 'dropping':
      case 'new': return store.flag === view.value
      default: return true
    }
  })
  const key = sort.value
  const value = (store) => (key === 'last_order_at' ? (store.last_order_at ? Date.parse(store.last_order_at) : null) : store[key])
  // Highest first; a store with no figure goes last.
  return [...listed].sort((a, b) => {
    const left = value(a)
    const right = value(b)
    if (left === null && right === null) return 0
    if (left === null) return 1
    if (right === null) return -1
    return right - left
  })
})
</script>
