<script setup>
import { computed, reactive, ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/Authenticated.vue'
import Button from '@/Components/Button.vue'

const props = defineProps({
  machineHealth: {
    type: Object,
    required: true,
  },
  operatorOptions: {
    type: Array,
    default: () => [],
  },
  vendPrefixOptions: {
    type: Array,
    default: () => [],
  },
  customerOptions: {
    type: Array,
    default: () => [],
  },
  locationTypeOptions: {
    type: Array,
    default: () => [],
  },
})

const DEFAULT_VISIBLE = 15

const rawFilters = props.machineHealth?.filters ?? {}
const filters = reactive({
  machine_limit: rawFilters.machine_limit ?? 20,
  channel_limit: rawFilters.channel_limit ?? 30,
  error_window_days: rawFilters.error_window_days ?? 7,
  temperature_window_days: rawFilters.temperature_window_days ?? 7,
  temperature_long_window_days: rawFilters.temperature_long_window_days ?? 30,
  temperature_delta_threshold: rawFilters.temperature_delta_threshold ?? 3,
  temperature_min_threshold: rawFilters.temperature_min_threshold ?? -18,
  temperature_sensor_type: rawFilters.temperature_sensor_type ?? 1,
  stockout_target_hours: rawFilters.stockout_target_hours ?? 72,
  stockout_lookback_days: rawFilters.stockout_lookback_days ?? 30,
  offline_threshold_hours: rawFilters.offline_threshold_hours ?? 12,
  offline_secondary_threshold_hours: rawFilters.offline_secondary_threshold_hours ?? 24,
  operator_ids: (rawFilters.operator_ids ?? []).map(String),
  vend_prefix_ids: (rawFilters.vend_prefix_ids ?? []).map(String),
  customer_ids: (rawFilters.customer_ids ?? []).map(String),
  machine_codes: rawFilters.machine_codes ?? [],
  channel_sku: rawFilters.channel_sku ?? '',
  no_txn_threshold_hours: {
    any: rawFilters.no_txn_threshold_hours?.any ?? 66,
    cash: rawFilters.no_txn_threshold_hours?.cash ?? 72,
    card: rawFilters.no_txn_threshold_hours?.card ?? 72,
    cashless: rawFilters.no_txn_threshold_hours?.cashless ?? 72,
  },
})

const summary = computed(() => props.machineHealth?.summary ?? {})
const stockouts = computed(() => props.machineHealth?.stockouts ?? {})
const errorBuckets = computed(() => {
  const buckets = props.machineHealth?.error_codes ?? {}
  return Object.entries(buckets).map(([key, bucket]) => ({
    key,
    ...bucket,
  }))
})
const temperature = computed(() => props.machineHealth?.temperature ?? {})
const connectivity = computed(() => props.machineHealth?.connectivity ?? {})
const noTransactions = computed(() => props.machineHealth?.no_transactions ?? {})
const operatorOptions = computed(() => props.operatorOptions ?? [])
const vendPrefixOptions = computed(() => props.vendPrefixOptions ?? [])

const showAllStockouts = ref(false)
const stockoutRows = computed(() => {
  const rows = stockouts.value.top_channels ?? []
  return showAllStockouts.value ? rows : rows.slice(0, DEFAULT_VISIBLE)
})

const bucketExpansion = reactive({})
const toggleBucketRows = (key) => {
  bucketExpansion[key] = !bucketExpansion[key]
}
const isBucketExpanded = (key) => !!bucketExpansion[key]
const visibleBucketRows = (bucket) => {
  const rows = bucket.rows ?? []
  return isBucketExpanded(bucket.key) ? rows : rows.slice(0, DEFAULT_VISIBLE)
}

const showAllRising = ref(false)
const risingRows = computed(() => {
  const rows = temperature.value.rising_lowest?.rows ?? []
  return showAllRising.value ? rows : rows.slice(0, DEFAULT_VISIBLE)
})

const showAllWorst = ref(false)
const worstMinimaRows = computed(() => {
  const rows = temperature.value.worst_minima?.rows ?? []
  return showAllWorst.value ? rows : rows.slice(0, DEFAULT_VISIBLE)
})

const showAllNotReaching = ref(false)
const notReachingRows = computed(() => {
  const rows = temperature.value.not_reaching_threshold?.rows ?? []
  return showAllNotReaching.value ? rows : rows.slice(0, DEFAULT_VISIBLE)
})

const showAllOfflinePrimary = ref(false)
const offlinePrimaryRows = computed(() => {
  const rows = connectivity.value.primary ?? []
  return showAllOfflinePrimary.value ? rows : rows.slice(0, DEFAULT_VISIBLE)
})

const showAllOfflineSecondary = ref(false)
const offlineSecondaryRows = computed(() => {
  const rows = connectivity.value.secondary ?? []
  return showAllOfflineSecondary.value ? rows : rows.slice(0, DEFAULT_VISIBLE)
})

const showAllNoTxnAny = ref(false)
const noTxnAnyRows = computed(() => {
  const rows = noTransactions.value.any_sales ?? []
  return showAllNoTxnAny.value ? rows : rows.slice(0, DEFAULT_VISIBLE)
})

const showAllNoTxnCash = ref(false)
const noTxnCashRows = computed(() => {
  const rows = noTransactions.value.cash_sales ?? []
  return showAllNoTxnCash.value ? rows : rows.slice(0, DEFAULT_VISIBLE)
})

const showAllNoTxnCard = ref(false)
const noTxnCardRows = computed(() => {
  const rows = noTransactions.value.card_sales ?? []
  return showAllNoTxnCard.value ? rows : rows.slice(0, DEFAULT_VISIBLE)
})

const showAllNoTxnQr = ref(false)
const noTxnQrRows = computed(() => {
  const rows = noTransactions.value.qr_sales ?? []
  return showAllNoTxnQr.value ? rows : rows.slice(0, DEFAULT_VISIBLE)
})

const formatNumber = (value, decimals = 2) => {
  if (value === null || value === undefined || Number.isNaN(value)) {
    return '—'
  }

  return Number(value).toFixed(decimals)
}

const formatHours = (value) => {
  if (value === null || value === undefined) {
    return '—'
  }

  return `${formatNumber(value)} h`
}

const formatDays = (value) => {
  if (value === null || value === undefined) {
    return '—'
  }

  return `${formatNumber(value)} d`
}

const formatPercent = (value) => {
  if (value === null || value === undefined) {
    return '—'
  }

  return `${formatNumber(value, 1)}%`
}

const formatDateTime = (value) => {
  if (!value) {
    return '—'
  }

  return new Date(value).toLocaleString()
}

const applyFilters = () => {
  const payload = {
    machine_limit: Number(filters.machine_limit),
    channel_limit: Number(filters.channel_limit),
    error_window_days: Number(filters.error_window_days),
    temperature_window_days: Number(filters.temperature_window_days),
    temperature_long_window_days: Number(filters.temperature_long_window_days),
    temperature_delta_threshold: Number(filters.temperature_delta_threshold),
    temperature_min_threshold: Number(filters.temperature_min_threshold),
    temperature_sensor_type: Number(filters.temperature_sensor_type),
    stockout_target_hours: Number(filters.stockout_target_hours),
    stockout_lookback_days: Number(filters.stockout_lookback_days),
    offline_threshold_hours: Number(filters.offline_threshold_hours),
    offline_secondary_threshold_hours: Number(
      filters.offline_secondary_threshold_hours,
    ),
    no_txn_threshold_hours: {
      any: Number(filters.no_txn_threshold_hours.any),
      cash: Number(filters.no_txn_threshold_hours.cash),
      card: Number(filters.no_txn_threshold_hours.card),
      cashless: Number(filters.no_txn_threshold_hours.cashless),
    },
    operator_ids: filters.operator_ids
      .map((value) => Number(value))
      .filter((value) => value > 0),
    vend_prefix_ids: filters.vend_prefix_ids
      .map((value) => Number(value))
      .filter((value) => value > 0),
    customer_ids: filters.customer_ids
      .map((value) => Number(value))
      .filter((value) => value > 0),
    channel_sku: filters.channel_sku || undefined,
  }

  router.get('/reports/machine-health', payload, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
  })
}

const resetFilters = () => {
  router.get('/reports/machine-health')
}

const renderPerCodeSummary = (perCode) => {
  if (!perCode || !perCode.length) {
    return '—'
  }

  return perCode
    .map((item) => `E${item.code}: ${item.count}`)
    .join(' · ')
}
</script>

<template>
  <AuthenticatedLayout>
    <Head title="Machine Health Dashboard" />

    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Machine Health Dashboard
      </h2>
    </template>

    <div class="py-6">
      <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
        <section class="bg-white shadow-sm sm:rounded-lg">
          <div class="p-6">
            <div class="flex items-center justify-between">
              <h3 class="text-lg font-semibold text-gray-900">Filters</h3>
              <p class="text-sm text-gray-500">
                Tune limits and detection windows to focus on the right cohorts.
              </p>
            </div>
            <form class="mt-4 space-y-4" @submit.prevent="applyFilters">
              <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                <label class="flex flex-col space-y-1 text-sm">
                  <span class="font-medium text-gray-700">Machine Limit</span>
                  <input
                    v-model.number="filters.machine_limit"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    max="50"
                    min="10"
                    step="5"
                    type="number"
                  />
                </label>
                <label class="flex flex-col space-y-1 text-sm">
                  <span class="font-medium text-gray-700"
                    >Channel Limit (Stockouts)</span
                  >
                  <input
                    v-model.number="filters.channel_limit"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    max="50"
                    min="10"
                    step="5"
                    type="number"
                  />
                </label>
                <label class="flex flex-col space-y-1 text-sm">
                  <span class="font-medium text-gray-700">Error Window (days)</span>
                  <input
                    v-model.number="filters.error_window_days"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    max="90"
                    min="1"
                    type="number"
                  />
                </label>
                <label class="flex flex-col space-y-1 text-sm">
                  <span class="font-medium text-gray-700"
                    >Stockout Target (hours)</span
                  >
                  <input
                    v-model.number="filters.stockout_target_hours"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    min="1"
                    type="number"
                  />
                </label>
              </div>

              <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <label class="flex flex-col space-y-1 text-sm">
                  <span class="font-medium text-gray-700">Temperature Window</span>
                  <div class="grid grid-cols-2 gap-2">
                    <input
                      v-model.number="filters.temperature_window_days"
                      class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                      min="3"
                      type="number"
                    />
                    <input
                      v-model.number="filters.temperature_long_window_days"
                      class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                      min="7"
                      type="number"
                    />
                  </div>
                  <span class="text-xs text-gray-500">
                    Short vs. long lookback (days)
                  </span>
                </label>

                <label class="flex flex-col space-y-1 text-sm">
                  <span class="font-medium text-gray-700">Temp Delta Threshold</span>
                  <input
                    v-model.number="filters.temperature_delta_threshold"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    step="0.5"
                    type="number"
                  />
                </label>

                <label class="flex flex-col space-y-1 text-sm">
                  <span class="font-medium text-gray-700"
                    >Temp Minimum Threshold (°C)</span
                  >
                  <input
                    v-model.number="filters.temperature_min_threshold"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    step="0.5"
                    type="number"
                  />
                </label>
              </div>

              <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <label class="flex flex-col space-y-1 text-sm">
                  <span class="font-medium text-gray-700">Offline Threshold (h)</span>
                  <div class="grid grid-cols-2 gap-2">
                    <input
                      v-model.number="filters.offline_threshold_hours"
                      class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                      min="1"
                      type="number"
                    />
                    <input
                      v-model.number="filters.offline_secondary_threshold_hours"
                      class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                      min="1"
                      type="number"
                    />
                  </div>
                  <span class="text-xs text-gray-500">
                    Primary vs. escalation thresholds
                  </span>
                </label>

                <label class="flex flex-col space-y-1 text-sm">
                  <span class="font-medium text-gray-700">Vend Prefix</span>
                  <select
                    v-model="filters.vend_prefix_ids"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    multiple
                  >
                    <option
                      v-for="option in vendPrefixOptions"
                      :key="option.id"
                      :value="String(option.id)"
                    >
                      {{ option.name }}
                    </option>
                  </select>
                  <span class="text-xs text-gray-500">
                    Hold Ctrl / Cmd to multi-select
                  </span>
                </label>

                <label class="flex flex-col space-y-1 text-sm">
                  <span class="font-medium text-gray-700">Operator</span>
                  <select
                    v-model="filters.operator_ids"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    multiple
                  >
                    <option
                      v-for="option in operatorOptions"
                      :key="option.id"
                      :value="String(option.id)"
                    >
                      {{ option.code ? `${option.code} — ${option.name}` : option.name }}
                    </option>
                  </select>
                </label>
              </div>

              <div class="flex space-x-3">
                <Button class="bg-indigo-600 text-white hover:bg-indigo-700">
                  Apply
                </Button>
                <Button
                  class="bg-white text-gray-700 hover:bg-gray-50 border border-gray-300"
                  type="button"
                  @click="resetFilters"
                >
                  Reset
                </Button>
              </div>
            </form>
          </div>
        </section>

        <section class="bg-white shadow-sm sm:rounded-lg">
          <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900">
              Stockout KPI Overview
            </h3>
            <p class="text-sm text-gray-500">
              Rolling {{ stockouts.metadata?.lookback_days ?? 30 }} day window based on
              refill telemetry.
            </p>

            <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-4">
              <div class="rounded-lg border border-gray-200 p-4">
                <p class="text-sm text-gray-500">Avg Stockout Duration</p>
                <p class="mt-2 text-2xl font-semibold text-indigo-700">
                  {{ formatDays(summary.average_duration_days) }}
                </p>
                <p class="text-xs text-gray-400">
                  Target ≤ {{ formatDays(summary.target_days) }}
                </p>
              </div>

              <div class="rounded-lg border border-gray-200 p-4">
                <p class="text-sm text-gray-500">Longest Stockout</p>
                <p class="mt-2 text-2xl font-semibold text-rose-600">
                  {{ formatDays(summary.longest_duration_days) }}
                </p>
                <p class="text-xs text-gray-400">
                  {{ formatHours(summary.longest_duration_hours) }}
                </p>
              </div>

              <div class="rounded-lg border border-gray-200 p-4">
                <p class="text-sm text-gray-500">Recovery Within Target</p>
                <p class="mt-2 text-2xl font-semibold text-emerald-600">
                  {{ formatPercent(summary.recovery_rate) }}
                </p>
                <p class="text-xs text-gray-400">
                  {{ summary.closed_events_count ?? 0 }} closed events
                </p>
              </div>

              <div class="rounded-lg border border-gray-200 p-4">
                <p class="text-sm text-gray-500">Target (Hours)</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900">
                  {{ formatHours(summary.target_hours) }}
                </p>
                <p class="text-xs text-gray-400">
                  Equivalent {{ formatDays(summary.target_days) }}
                </p>
              </div>
            </div>

            <div class="mt-6">
              <h4 class="text-sm font-semibold text-gray-700">
                Top Channels by Stockout Duration
              </h4>
              <div class="mt-2 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                  <thead class="bg-gray-50">
                    <tr>
                      <th class="px-4 py-2 text-left font-medium text-gray-500">
                        Machine / Channel
                      </th>
                      <th class="px-4 py-2 text-left font-medium text-gray-500">
                        Product
                      </th>
                      <th class="px-4 py-2 text-left font-medium text-gray-500">
                        Duration
                      </th>
                      <th class="px-4 py-2 text-left font-medium text-gray-500">
                        Stockout At
                      </th>
                      <th class="px-4 py-2 text-left font-medium text-gray-500">
                        Restocked At
                      </th>
                    </tr>
                  </thead>
                  <tbody v-if="stockoutRows.length" class="divide-y divide-gray-200 bg-white">
                    <tr
                      v-for="row in stockoutRows"
                      :key="`${row.vend_id}-${row.channel_code}-${row.stockout_at}`"
                    >
                      <td class="px-4 py-2">
                        <div class="font-medium text-gray-900">
                          {{ row.vend_code }} · {{ row.channel_code ?? '—' }}
                        </div>
                        <div class="text-xs text-gray-500">
                          {{ row.customer_name ?? 'Unassigned' }}
                        </div>
                      </td>
                      <td class="px-4 py-2 text-gray-700">
                        {{ row.product_name ?? '—' }}
                      </td>
                      <td class="px-4 py-2 text-gray-700">
                        <div>{{ formatDays(row.duration_days) }}</div>
                        <div class="text-xs text-gray-500">
                          {{ formatHours(row.duration_hours) }}
                          <span
                            v-if="row.is_open"
                            class="ml-2 inline-flex items-center rounded bg-rose-100 px-2 py-0.5 text-xs font-medium text-rose-700"
                          >
                            Ongoing
                          </span>
                        </div>
                      </td>
                      <td class="px-4 py-2 text-gray-700">
                        {{ formatDateTime(row.stockout_at) }}
                      </td>
                      <td class="px-4 py-2 text-gray-700">
                        {{ formatDateTime(row.restocked_at) }}
                      </td>
                    </tr>
                  </tbody>
                  <tbody v-else>
                    <tr>
                      <td
                        class="px-4 py-6 text-center text-sm text-gray-500"
                        colspan="5"
                      >
                        No stockout episodes recorded in the selected window.
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div
                v-if="(stockouts.top_channels?.length ?? 0) > DEFAULT_VISIBLE"
                class="mt-4 flex justify-center"
              >
                <Button
                  class="border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                  type="button"
                  @click="showAllStockouts = !showAllStockouts"
                >
                  {{ showAllStockouts ? 'Show less' : 'Load more' }}
                </Button>
              </div>
            </div>
          </div>
        </section>

        <section class="bg-white shadow-sm sm:rounded-lg">
          <div class="p-6">
            <div class="flex items-center justify-between">
              <h3 class="text-lg font-semibold text-gray-900">Channel Error Hotspots</h3>
              <p class="text-sm text-gray-500">
                Grouped by dispense stability vs. mechanical codes.
              </p>
            </div>
            <div class="mt-4 space-y-6">
              <div
                v-for="bucket in errorBuckets"
                :key="bucket.key"
                class="rounded-lg border border-gray-200"
              >
                <div class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-3">
                  <div>
                    <h4 class="text-sm font-semibold text-gray-800">
                      {{ bucket.label }}
                    </h4>
                    <p class="text-xs text-gray-500">
                      Window: {{ bucket.window_days }} days · Limit:
                      {{ bucket.limit }} machines
                    </p>
                  </div>
                  <div class="text-xs text-gray-500">
                    Errors: {{ bucket.codes?.map((code) => `E${code}`).join(', ') }}
                  </div>
                </div>
                <div class="overflow-x-auto">
                  <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-white">
                      <tr>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">
                          Machine
                        </th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">
                          Customer
                        </th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">
                          Operator
                        </th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">
                          Events
                        </th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">
                          Breakdown
                        </th>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">
                          Last Event
                        </th>
                      </tr>
                    </thead>
                    <tbody v-if="bucket.rows?.length" class="divide-y divide-gray-200 bg-white">
                      <tr v-for="row in visibleBucketRows(bucket)" :key="row.vend_id">
                        <td class="px-4 py-2">
                          <div class="font-medium text-gray-900">{{ row.vend_code }}</div>
                        </td>
                        <td class="px-4 py-2 text-gray-700">
                          {{ row.customer_name ?? '—' }}
                        </td>
                        <td class="px-4 py-2 text-gray-700">
                          {{ row.operator_name ?? '—' }}
                        </td>
                        <td class="px-4 py-2 text-gray-700">
                          {{ row.total_events }}
                        </td>
                        <td class="px-4 py-2 text-gray-700">
                          {{ renderPerCodeSummary(row.per_code) }}
                        </td>
                        <td class="px-4 py-2 text-gray-700">
                          {{ formatDateTime(row.last_event_at) }}
                        </td>
                      </tr>
                    </tbody>
                    <tbody v-else>
                      <tr>
                        <td
                          class="px-4 py-6 text-center text-sm text-gray-500"
                          colspan="6"
                        >
                          No events detected for this grouping.
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div
                  v-if="(bucket.rows?.length ?? 0) > DEFAULT_VISIBLE"
                  class="border-t border-gray-200 px-4 py-3 flex justify-center"
                >
                  <Button
                    class="border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                    type="button"
                    @click="toggleBucketRows(bucket.key)"
                  >
                    {{ isBucketExpanded(bucket.key) ? 'Show less' : 'Load more' }}
                  </Button>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section class="bg-white shadow-sm sm:rounded-lg">
          <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900">Temperature Alerts</h3>
            <p class="text-sm text-gray-500">
              Sensor type {{ filters.temperature_sensor_type }} · Threshold
              {{ formatNumber(filters.temperature_min_threshold, 1) }}°C
            </p>
            <div class="mt-4 grid grid-cols-1 gap-6 md:grid-cols-3">
              <div class="rounded-lg border border-gray-200 p-4">
                <h4 class="text-sm font-semibold text-gray-800">
                  Rising Lowest Temperature
                </h4>
                <p class="text-xs text-gray-500">
                  Δ ≥ {{ formatNumber(filters.temperature_delta_threshold, 1) }}°C over
                  {{ temperature.rising_lowest?.window_days ?? filters.temperature_window_days }} days
                </p>
                <ul class="mt-3 space-y-3 text-sm text-gray-700">
                  <li
                    v-for="row in risingRows"
                    :key="row.vend_id"
                    class="rounded border border-gray-100 p-3"
                  >
                    <div class="font-medium text-gray-900">
                      {{ row.vend_code }} (Δ {{ formatNumber(row.delta, 1) }}°C)
                    </div>
                    <div class="text-xs text-gray-500">
                      {{ row.first_day }}: {{ row.first_min_temp }}°C → {{ row.latest_day }}:
                      {{ row.latest_min_temp }}°C
                    </div>
                  </li>
                  <li v-if="!(temperature.rising_lowest?.rows?.length)">
                    No machines breached the delta threshold.
                  </li>
                </ul>
                <div
                  v-if="(temperature.rising_lowest?.rows?.length ?? 0) > DEFAULT_VISIBLE"
                  class="mt-3 flex justify-center"
                >
                  <Button
                    class="border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                    type="button"
                    @click="showAllRising = !showAllRising"
                  >
                    {{ showAllRising ? 'Show less' : 'Load more' }}
                  </Button>
                </div>
              </div>

              <div class="rounded-lg border border-gray-200 p-4">
                <h4 class="text-sm font-semibold text-gray-800">
                  Highest Minimum ({{ temperature.worst_minima?.window_days ?? filters.temperature_long_window_days }} days)
                </h4>
                <ul class="mt-3 space-y-3 text-sm text-gray-700">
                  <li
                    v-for="row in worstMinimaRows"
                    :key="row.vend_id"
                    class="rounded border border-gray-100 p-3"
                  >
                    <div class="font-medium text-gray-900">
                      {{ row.vend_code }} · {{ row.worst_min_temp }}°C
                    </div>
                    <div class="text-xs text-gray-500">
                      {{ formatDateTime(row.last_recorded_at) }}
                    </div>
                  </li>
                  <li v-if="!(temperature.worst_minima?.rows?.length)">
                    All machines remained below target in the selected window.
                  </li>
                </ul>
                <div
                  v-if="(temperature.worst_minima?.rows?.length ?? 0) > DEFAULT_VISIBLE"
                  class="mt-3 flex justify-center"
                >
                  <Button
                    class="border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                    type="button"
                    @click="showAllWorst = !showAllWorst"
                  >
                    {{ showAllWorst ? 'Show less' : 'Load more' }}
                  </Button>
                </div>
              </div>

              <div class="rounded-lg border border-gray-200 p-4">
                <h4 class="text-sm font-semibold text-gray-800">
                  Did Not Reach {{ formatNumber(filters.temperature_min_threshold, 1) }}°C
                </h4>
                <p class="text-xs text-gray-500">Last 12 hours of telemetry</p>
                <ul class="mt-3 space-y-3 text-sm text-gray-700">
                  <li
                    v-for="row in notReachingRows"
                    :key="row.vend_id"
                    class="rounded border border-gray-100 p-3"
                  >
                    <div class="font-medium text-gray-900">
                      {{ row.vend_code }} · {{ row.min_value }}°C
                    </div>
                    <div class="text-xs text-gray-500">
                      {{ formatDateTime(row.last_recorded_at) }} ({{ row.reading_count }} samples)
                    </div>
                  </li>
                  <li v-if="!(temperature.not_reaching_threshold?.rows?.length)">
                    Every monitored machine hit the minimum threshold within the last 12
                    hours.
                  </li>
                </ul>
                <div
                  v-if="(temperature.not_reaching_threshold?.rows?.length ?? 0) > DEFAULT_VISIBLE"
                  class="mt-3 flex justify-center"
                >
                  <Button
                    class="border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                    type="button"
                    @click="showAllNotReaching = !showAllNotReaching"
                  >
                    {{ showAllNotReaching ? 'Show less' : 'Load more' }}
                  </Button>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section class="bg-white shadow-sm sm:rounded-lg">
          <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900">Connectivity</h3>
            <div class="mt-4 grid grid-cols-1 gap-6 md:grid-cols-2">
              <div class="rounded-lg border border-gray-200 p-4">
                <h4 class="text-sm font-semibold text-gray-800">
                  Offline ≥ {{ connectivity.primary_threshold_hours ?? filters.offline_threshold_hours }}h
                </h4>
                <p class="text-xs text-gray-500">Sorted from nearest to threshold</p>
                <ul class="mt-3 space-y-3 text-sm text-gray-700">
                  <li
                    v-for="row in offlinePrimaryRows"
                    :key="row.vend_id"
                    class="rounded border border-gray-100 p-3"
                  >
                    <div class="font-medium text-gray-900">
                      {{ row.vend_code }} · {{ formatHours(row.hours_offline) }}
                    </div>
                    <div class="text-xs text-gray-500">
                      Last contact {{ formatDateTime(row.last_contact_at) }}
                    </div>
                  </li>
                  <li v-if="!(connectivity.primary?.length)">
                    All machines are within the primary offline threshold.
                  </li>
                </ul>
                <div
                  v-if="(connectivity.primary?.length ?? 0) > DEFAULT_VISIBLE"
                  class="mt-3 flex justify-center"
                >
                  <Button
                    class="border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                    type="button"
                    @click="showAllOfflinePrimary = !showAllOfflinePrimary"
                  >
                    {{ showAllOfflinePrimary ? 'Show less' : 'Load more' }}
                  </Button>
                </div>
              </div>

              <div class="rounded-lg border border-gray-200 p-4">
                <h4 class="text-sm font-semibold text-gray-800">
                  Escalations ≥
                  {{ connectivity.secondary_threshold_hours ?? filters.offline_secondary_threshold_hours }}h
                </h4>
                <ul class="mt-3 space-y-3 text-sm text-gray-700">
                  <li
                    v-for="row in offlineSecondaryRows"
                    :key="`${row.vend_id}-secondary`"
                    class="rounded border border-gray-100 p-3"
                  >
                    <div class="font-medium text-gray-900">
                      {{ row.vend_code }} · {{ formatHours(row.hours_offline) }}
                    </div>
                    <div class="text-xs text-gray-500">
                      Last contact {{ formatDateTime(row.last_contact_at) }}
                    </div>
                  </li>
                  <li v-if="!(connectivity.secondary?.length)">
                    No escalations pending.
                  </li>
                </ul>
                <div
                  v-if="(connectivity.secondary?.length ?? 0) > DEFAULT_VISIBLE"
                  class="mt-3 flex justify-center"
                >
                  <Button
                    class="border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                    type="button"
                    @click="showAllOfflineSecondary = !showAllOfflineSecondary"
                  >
                    {{ showAllOfflineSecondary ? 'Show less' : 'Load more' }}
                  </Button>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section class="bg-white shadow-sm sm:rounded-lg">
          <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900">No Transaction Alerts</h3>
            <p class="text-sm text-gray-500">
              Thresholds apply per payment rail; adjust in the filter panel.
            </p>
            <div class="mt-4 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-4">
              <div class="rounded-lg border border-gray-200 p-4">
                <h4 class="text-sm font-semibold text-gray-800">
                  Any Sales ({{ noTransactions.thresholds?.any ?? filters.no_txn_threshold_hours.any }}h)
                </h4>
                <ul class="mt-3 space-y-3 text-sm text-gray-700">
                  <li
                    v-for="row in noTxnAnyRows"
                    :key="row.vend_id"
                    class="rounded border border-gray-100 p-3"
                  >
                    <div class="font-medium text-gray-900">
                      {{ row.vend_code }} · {{ formatHours(row.hours_since) }}
                    </div>
                    <div class="text-xs text-gray-500">
                      Last sale {{ formatDateTime(row.last_transaction_at) }}
                    </div>
                  </li>
                  <li v-if="!(noTransactions.any_sales?.length)">
                    All machines recorded recent transactions.
                  </li>
                </ul>
                <div
                  v-if="(noTransactions.any_sales?.length ?? 0) > DEFAULT_VISIBLE"
                  class="mt-3 flex justify-center"
                >
                  <Button
                    class="border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                    type="button"
                    @click="showAllNoTxnAny = !showAllNoTxnAny"
                  >
                    {{ showAllNoTxnAny ? 'Show less' : 'Load more' }}
                  </Button>
                </div>
              </div>

              <div class="rounded-lg border border-gray-200 p-4">
                <h4 class="text-sm font-semibold text-gray-800">
                  Cash ({{ noTransactions.thresholds?.cash ?? filters.no_txn_threshold_hours.cash }}h)
                </h4>
                <ul class="mt-3 space-y-3 text-sm text-gray-700">
                  <li
                    v-for="row in noTxnCashRows"
                    :key="`${row.vend_id}-cash`"
                    class="rounded border border-gray-100 p-3"
                  >
                    <div class="font-medium text-gray-900">
                      {{ row.vend_code }} · {{ formatHours(row.hours_since) }}
                    </div>
                    <div class="text-xs text-gray-500">
                      {{ formatDateTime(row.last_transaction_at) }}
                    </div>
                  </li>
                  <li v-if="!(noTransactions.cash_sales?.length)">
                    No cash-specific gaps detected.
                  </li>
                </ul>
                <div
                  v-if="(noTransactions.cash_sales?.length ?? 0) > DEFAULT_VISIBLE"
                  class="mt-3 flex justify-center"
                >
                  <Button
                    class="border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                    type="button"
                    @click="showAllNoTxnCash = !showAllNoTxnCash"
                  >
                    {{ showAllNoTxnCash ? 'Show less' : 'Load more' }}
                  </Button>
                </div>
              </div>

              <div class="rounded-lg border border-gray-200 p-4">
                <h4 class="text-sm font-semibold text-gray-800">
                  Card ({{ noTransactions.thresholds?.card ?? filters.no_txn_threshold_hours.card }}h)
                </h4>
                <ul class="mt-3 space-y-3 text-sm text-gray-700">
                  <li
                    v-for="row in noTxnCardRows"
                    :key="`${row.vend_id}-card`"
                    class="rounded border border-gray-100 p-3"
                  >
                    <div class="font-medium text-gray-900">
                      {{ row.vend_code }} · {{ formatHours(row.hours_since) }}
                    </div>
                    <div class="text-xs text-gray-500">
                      {{ formatDateTime(row.last_transaction_at) }}
                    </div>
                  </li>
                  <li v-if="!(noTransactions.card_sales?.length)">
                    No credit card gaps detected.
                  </li>
                </ul>
                <div
                  v-if="(noTransactions.card_sales?.length ?? 0) > DEFAULT_VISIBLE"
                  class="mt-3 flex justify-center"
                >
                  <Button
                    class="border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                    type="button"
                    @click="showAllNoTxnCard = !showAllNoTxnCard"
                  >
                    {{ showAllNoTxnCard ? 'Show less' : 'Load more' }}
                  </Button>
                </div>
              </div>

              <div class="rounded-lg border border-gray-200 p-4">
                <h4 class="text-sm font-semibold text-gray-800">
                  QR / Cashless ({{ noTransactions.thresholds?.cashless ?? filters.no_txn_threshold_hours.cashless }}h)
                </h4>
                <ul class="mt-3 space-y-3 text-sm text-gray-700">
                  <li
                    v-for="row in noTxnQrRows"
                    :key="`${row.vend_id}-qr`"
                    class="rounded border border-gray-100 p-3"
                  >
                    <div class="font-medium text-gray-900">
                      {{ row.vend_code }} · {{ formatHours(row.hours_since) }}
                    </div>
                    <div class="text-xs text-gray-500">
                      {{ formatDateTime(row.last_transaction_at) }}
                    </div>
                  </li>
                  <li v-if="!(noTransactions.qr_sales?.length)">
                    No QR/cashless gaps detected.
                  </li>
                </ul>
                <div
                  v-if="(noTransactions.qr_sales?.length ?? 0) > DEFAULT_VISIBLE"
                  class="mt-3 flex justify-center"
                >
                  <Button
                    class="border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                    type="button"
                    @click="showAllNoTxnQr = !showAllNoTxnQr"
                  >
                    {{ showAllNoTxnQr ? 'Show less' : 'Load more' }}
                  </Button>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </AuthenticatedLayout>
</template>
