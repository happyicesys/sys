<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import AuthenticatedLayout from '@/Layouts/Authenticated.vue'
import Button from '@/Components/Button.vue'
import MultiSelect from '@/Components/MultiSelect.vue'
import SearchInput from '@/Components/SearchInput.vue'
import { CheckCircleIcon } from '@heroicons/vue/24/solid'

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

const DEFAULT_VISIBLE = 10
const HIPL_OPERATOR_CODES = ['HIPL', 'HIMD', 'LEA', 'DCVIC', 'HIESG', 'IP']

const normalizeCollection = (collection) => {
  if (!collection) {
    return []
  }

  if (Array.isArray(collection)) {
    return collection
  }

  if (Array.isArray(collection.data)) {
    return collection.data
  }

  return []
}

const normalizeIds = (values) => {
  if (!Array.isArray(values)) {
    return []
  }

  return values
    .map((value) => {
      const numeric = Number(value)
      return Number.isFinite(numeric) ? numeric : value
    })
    .filter((value) => value !== undefined && value !== null && value !== '')
}

const rawFilters = props.machineHealth?.filters ?? {}
const filters = reactive({
  machine_limit: rawFilters.machine_limit ?? 15,
  channel_limit: rawFilters.channel_limit ?? 10,
  error_window_days: rawFilters.error_window_days ?? 7,
  temperature_window_days: rawFilters.temperature_window_days ?? 7,
  temperature_long_window_days: rawFilters.temperature_long_window_days ?? 30,
  temperature_delta_threshold: rawFilters.temperature_delta_threshold ?? 3,
  temperature_min_threshold: rawFilters.temperature_min_threshold ?? -18,
  temperature_sensor_type: rawFilters.temperature_sensor_type ?? 1,
  stockout_target_hours: rawFilters.stockout_target_hours ?? 72,
  stockout_lookback_days: rawFilters.stockout_lookback_days ?? 30,

  operator_ids: normalizeIds(rawFilters.operator_ids ?? []),
  vend_prefix_ids: normalizeIds(rawFilters.vend_prefix_ids ?? []),
  customer_ids: (rawFilters.customer_ids ?? []).map(String),
  machine_codes: rawFilters.machine_codes ?? [],
  machine_codes_input: (rawFilters.machine_codes ?? []).join(','),
  channel_sku: rawFilters.channel_sku ?? '',
  show_all_errors: rawFilters.show_all_errors ?? false,
  no_txn_threshold_hours: {
    any: rawFilters.no_txn_threshold_hours?.any ?? 48,
    cash: rawFilters.no_txn_threshold_hours?.cash ?? 48,
    card: rawFilters.no_txn_threshold_hours?.card ?? 48,
    qr: rawFilters.no_txn_threshold_hours?.qr ?? 72,
    digitalscreen: rawFilters.no_txn_threshold_hours?.digitalscreen ?? 72,
  },
})

const page = usePage()
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
const errorDefinitions = computed(() => props.machineHealth?.error_definitions ?? {})
const operatorOptions = computed(() => normalizeCollection(props.operatorOptions))
const vendPrefixOptions = computed(() => normalizeCollection(props.vendPrefixOptions))
const authOperator = computed(() => page.props.auth?.operator ?? null)

const operatorSelectOptions = computed(() =>
  operatorOptions.value.map((option) => {
    const id = Number(option.id)
    return {
      id: Number.isFinite(id) ? id : option.id,
      code: option.code ?? null,
      value: option.code ? `${option.code} — ${option.name}` : option.name ?? '',
    }
  }),
)

const vendPrefixSelectOptions = computed(() =>
  vendPrefixOptions.value.map((option) => {
    const id = Number(option.id)
    return {
      id: Number.isFinite(id) ? id : option.id,
      value: option.name ?? '',
    }
  }),
)

const operatorSelections = computed({
  get() {
    const optionMap = new Map(
      operatorSelectOptions.value.map((option) => [option.id, option]),
    )

    return (filters.operator_ids ?? [])
      .map((id) => optionMap.get(id))
      .filter(Boolean)
  },
  set(newValue) {
    filters.operator_ids = (Array.isArray(newValue) ? newValue : [])
      .map((option) => option?.id)
      .filter((id) => id !== undefined && id !== null && id !== '')
  },
})

const vendPrefixSelections = computed({
  get() {
    const optionMap = new Map(
      vendPrefixSelectOptions.value.map((option) => [option.id, option]),
    )

    return (filters.vend_prefix_ids ?? [])
      .map((id) => optionMap.get(id))
      .filter(Boolean)
  },
  set(newValue) {
    filters.vend_prefix_ids = (Array.isArray(newValue) ? newValue : [])
      .map((option) => option?.id)
      .filter((id) => id !== undefined && id !== null && id !== '')
  },
})

const ensureDefaultOperatorFilters = () => {
  if ((filters.operator_ids?.length ?? 0) > 0) {
    return
  }

  const operator = authOperator.value
  if (!operator) {
    return
  }

  const options = operatorSelectOptions.value
  if (!options.length) {
    return
  }

  const byCode = new Map(options.map((option) => [option.code, option]))
  const byId = new Map(options.map((option) => [option.id, option]))
  const selectedIds = []
  const [normalizedId] = normalizeIds([operator.id])

  const activeOption =
    byId.get(normalizedId) ??
    (operator.code ? byCode.get(operator.code) : undefined)

  if (!activeOption) {
    return
  }

  if (activeOption.code === 'HIPL') {
    HIPL_OPERATOR_CODES.forEach((code) => {
      const match = byCode.get(code)
      if (match) {
        selectedIds.push(match.id)
      }
    })
  } else {
    selectedIds.push(activeOption.id)
  }

  if (selectedIds.length) {
    filters.operator_ids = selectedIds
  }
}

watch([operatorSelectOptions, authOperator], ensureDefaultOperatorFilters, {
  immediate: true,
})

const createRowLimiter = (rowsSource, limitSource) => {
  const expanded = ref(false)

  const rows = computed(() => {
    const list = rowsSource.value ?? []
    const limit = limitSource ? limitSource() : DEFAULT_VISIBLE
    return expanded.value ? list : list.slice(0, limit)
  })

  const hasMore = computed(() => {
    const list = rowsSource.value ?? []
    const limit = limitSource ? limitSource() : DEFAULT_VISIBLE
    return !expanded.value && list.length > limit
  })

  const showAll = () => {
    expanded.value = true
  }

  watch(
    rowsSource,
    () => {
      expanded.value = false
    },
    { deep: false },
  )

  return { rows, hasMore, showAll }
}

const {
  rows: stockoutRows,
  hasMore: canLoadMoreStockouts,
  showAll: loadRemainingStockouts,
} = createRowLimiter(
  computed(() => stockouts.value.top_channels ?? []),
  () => filters.channel_limit,
)

const bucketExpansion = reactive({})
const expandBucketRows = (key) => {
  bucketExpansion[key] = true
}
const isBucketExpanded = (key) => !!bucketExpansion[key]
const canExpandBucket = (bucket) => {
  const rows = bucket.rows ?? []
  return !isBucketExpanded(bucket.key) && rows.length > DEFAULT_VISIBLE
}
const visibleBucketRows = (bucket) => {
  const rows = bucket.rows ?? []
  return isBucketExpanded(bucket.key)
    ? rows
    : rows.slice(0, filters.machine_limit)
}
watch(
  errorBuckets,
  (buckets = []) => {
    const keys = new Set(buckets.map((bucket) => bucket.key))
    Object.keys(bucketExpansion).forEach((key) => {
      if (!keys.has(key)) {
        delete bucketExpansion[key]
        return
      }

      bucketExpansion[key] = false
    })
  },
  { deep: false },
)

const {
  rows: risingRows,
  hasMore: canLoadMoreRising,
  showAll: loadRemainingRising,
} = createRowLimiter(
  computed(() => temperature.value.rising_lowest?.rows ?? []),
  () => filters.machine_limit,
)

const {
  rows: risingRowsT1,
  hasMore: canLoadMoreRisingT1,
  showAll: loadRemainingRisingT1,
} = createRowLimiter(
  computed(() => temperature.value.rising_lowest_t1_smart?.rows ?? []),
  () => filters.machine_limit,
)

// Helper to group alerts for Matrix display
function groupAlertsMatrix(rows, definitions) {
  // definitions: { type: 't2_frozen', label: 'Title', severities: {1: '> 24h', 2: '> 48h', 3: '> 72h'} }
  const groups = definitions.map(def => ({
    ...def,
    cells: { 1: [], 2: [], 3: [] }
  }))

  rows.forEach(row => {
    const group = groups.find(g => g.types.includes(row.alert_type))
    if (group && row.severity) {
        if (!group.cells[row.severity]) group.cells[row.severity] = []
        group.cells[row.severity].push(row)
    }
  })
  return groups
}

const matrix21 = computed(() => {
  const meta = [
      {
          id: 't2_below_minus_25',
          label: 'A) T2, below -25°C',
          sub: 'Possible caused by:\ni) Fan not function\nii) Temp probe malfunction',
          types: ['t2_below_minus_25'],
          headers: { 1: '> 10 mins', 2: '> 30 mins' }
      },
      {
          id: 'temps_above_0',
          label: 'B) T1 & or T2, above 0°C',
          sub: 'Possible caused by:\ni) Freezer door not close tight\nii) Open freezer door >15mins\nFan not function',
          note: 'Alert dismissed once temp below 0c',
          types: ['temps_above_0'],
          headers: { 1: '> 30 mins', 2: '> 60 mins' }
      },
      {
          id: 'temps_above_minus_8',
          label: 'C) T1 & or T2, above -8°C',
          sub: 'Possible caused by:\ni) Freezer door not close tight\nii) Open freezer door >15mins\niii) Comp not function',
          note: 'Alert dismissed once temp below -8c',
          types: ['temps_above_minus_8'],
          headers: { 1: '> 60 mins', 2: '> 90 mins' }
      },
      {
          id: 'not_reach_minus_18',
          label: 'D) T1 & or T2, did not reach -18°C',
          sub: 'Possible caused by:\ni) Freezer door not close tight\nii) Open freezer door >15mins\nMany purchases occur',
          note: 'Alert dismissed once temp below -18c',
          types: ['not_reach_minus_18'],
          headers: { 1: 'Within last 8 hours', 2: '> 8 hours' }
      },
  ]
  const rows = [
      ...(temperature.value.operation_errors_smart?.rows ?? []),
  ]
  return groupAlertsMatrix(rows, meta)
})

const matrix22 = computed(() => {
    const meta = [
        {
            id: 'lowest_24h',
            label: 'A) T1 & T2 lowest (last 24hrs)',
            sub: 'Possible caused by:\ni) Door\'s seal air leak\nii) Dispensing Door not close tight',
            note: 'Check and show lowest Temp in last 24hrs',
            types: ['lowest_24h_above'],
            headers: { 1: 'Above -21c', 2: 'Above -20c', 3: 'Above -19c' }
        },
        {
            id: 'lowest_72h',
            label: 'B) T1 & T2 lowest (last 72hrs)',
            sub: 'Possible caused by:\ni) Door\'s seal air leak\nii) Fan/Comp ageing\niii) Temperature probe malfunction',
            note: 'Check and show lowest Temp in last 72hrs',
            types: ['lowest_72h_above'],
            headers: { 1: 'Above -21c', 2: 'Above -20c', 3: 'Above -19c' }
        },
        {
            id: 'rising_lowest',
            label: 'C) Rising lowest T1 and T2 (Last 24hrs vs Last 48hrs)',
            sub: 'Possible caused by:\ni) Defrost not clean/enough\nii) Door\'s seal air leak\niii) Defrost drain hole',
            note: null,
            types: ['rising_t1_trend', 'rising_t2_trend'],
            headers: { 1: 'Δ ≥ 1c', 2: 'Δ ≥ 2c', 3: 'Δ ≥ 3c' }
        },
        {
            id: 't2_frozen',
            label: 'D) T2, never above 2°C',
            sub: 'Possible caused by:\ni) Defrost fail\nii) Defrost not clean/enough\niii) Temperature probe malfunction',
            note: 'only for E/F/EG\nexclude UDD\n(Alert dismissed once temp below -23.5c)',
            types: ['t2_frozen'],
            headers: { 1: '> 24 hr', 2: '> 48 hr', 3: '> 72 hr' }
        },
    ]
    const risingT1 = temperature.value.rising_lowest_t1_smart?.rows ?? []
    const risingT2 = temperature.value.rising_lowest_t2_smart?.rows ?? []
    const mergedRising = [...risingT1, ...risingT2]

    // Deduplicate by vend_id, keeping the one with higher severity
    const uniqueRisingMap = new Map();
    mergedRising.forEach(row => {
        if (!uniqueRisingMap.has(row.vend_id)) {
            uniqueRisingMap.set(row.vend_id, row);
        } else {
            const existing = uniqueRisingMap.get(row.vend_id);
            if (row.severity > existing.severity) {
                uniqueRisingMap.set(row.vend_id, row);
            }
        }
    });
    const uniqueRising = Array.from(uniqueRisingMap.values());

    const rows = [
        ...(temperature.value.preventive_maintenance_smart?.rows ?? []),
        ...uniqueRising,
        ...(temperature.value.t2_frozen_smart?.rows ?? [])
    ]
    return groupAlertsMatrix(rows, meta)
})

const {
  rows: worstMinimaRows,
  hasMore: canLoadMoreWorstMinima,
  showAll: loadRemainingWorstMinima,
} = createRowLimiter(
  computed(() => temperature.value.worst_minima?.rows ?? []),
  () => filters.machine_limit,
)

const {
  rows: notReachingRows,
  hasMore: canLoadMoreNotReaching,
  showAll: loadRemainingNotReaching,
} = createRowLimiter(
  computed(() => temperature.value.not_reaching_threshold?.rows ?? []),
  () => filters.machine_limit,
)



const {
  rows: noTxnAnyRows,
  hasMore: canLoadMoreNoTxnAny,
  showAll: loadRemainingNoTxnAny,
} = createRowLimiter(
  computed(() => noTransactions.value.any_sales ?? []),
  () => filters.machine_limit,
)

const {
  rows: noTxnCashRows,
  hasMore: canLoadMoreNoTxnCash,
  showAll: loadRemainingNoTxnCash,
} = createRowLimiter(
  computed(() => noTransactions.value.cash_sales ?? []),
  () => filters.machine_limit,
)

const {
  rows: noTxnCardRows,
  hasMore: canLoadMoreNoTxnCard,
  showAll: loadRemainingNoTxnCard,
} = createRowLimiter(
  computed(() => noTransactions.value.card_sales ?? []),
  () => filters.machine_limit,
)

const {
  rows: noTxnQrRealRows,
  hasMore: canLoadMoreNoTxnQrReal,
  showAll: loadRemainingNoTxnQrReal,
} = createRowLimiter(
  computed(() => noTransactions.value.qr_sales ?? []),
  () => filters.machine_limit,
)

const {
  rows: noTxnDigitalScreenRows,
  hasMore: canLoadMoreNoTxnDigitalScreen,
  showAll: loadRemainingNoTxnDigitalScreen,
} = createRowLimiter(
  computed(() => noTransactions.value.digitalscreen_sales ?? []),
  () => filters.machine_limit,
)

const formatNumber = (value, decimals = 2) => {
  const num = Number(value)
  if (value === null || value === undefined || Number.isNaN(num)) {
    return '—'
  }

  return num.toFixed(decimals)
}

const operatorCountry = computed(() => page.props.operatorCountry ?? {})

const getCoinFloat = (row) => {
  if (!row.parameter_json || !row.parameter_json['CoinCnt']) return null
  const exponent = operatorCountry.value?.currency_exponent ?? 2
  const value = row.parameter_json['CoinCnt'] / Math.pow(10, exponent)
  return value.toFixed(2)
}



const formatHours = (value) => {
  if (value === null || value === undefined) {
    return '—'
  }

  return `${formatNumber(value)} hr`
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

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return '—'
  }

  const pad = (num) => String(num).padStart(2, '0')
  const twoDigitYear = String(date.getFullYear()).slice(-2)
  const month = pad(date.getMonth() + 1)
  const day = pad(date.getDate())
  const minutes = pad(date.getMinutes())

  let hours = date.getHours()
  const suffix = hours >= 12 ? 'pm' : 'am'
  hours %= 12
  if (hours === 0) {
    hours = 12
  }

  return `${twoDigitYear}-${month}-${day} ${pad(hours)}:${minutes} ${suffix}`
}

const formatDateTimeComma = (value) => {
  if (!value) {
    return '—'
  }

  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return '—'
  }

  const pad = (num) => String(num).padStart(2, '0')
  const twoDigitYear = String(date.getFullYear()).slice(-2)
  const month = pad(date.getMonth() + 1)
  const day = pad(date.getDate())
  const minutes = pad(date.getMinutes())

  let hours = date.getHours()
  const suffix = hours >= 12 ? 'pm' : 'am'
  hours %= 12
  if (hours === 0) {
    hours = 12
  }

  return `${twoDigitYear}-${month}-${day}, ${pad(hours)}:${minutes} ${suffix}`
}

const getBucketBgClass = (label) => {
  switch (label) {
    case '< 1hr': return 'bg-white'
    case '< 2hr': return 'bg-red-50'
    case '< 4hr': return 'bg-red-100'
    case '< 8hr': return 'bg-red-200'
    case '< 12hr': return 'bg-red-300'
    case '> 12hr': return 'bg-red-400'
    default: return 'bg-gray-50'
  }
}

const getMatrixHeaderClass = (sev) => {
  switch (Number(sev)) {
    case 1: return 'bg-red-300'
    case 2: return 'bg-red-400'
    case 3: return 'bg-red-500'
    default: return 'bg-red-400'
  }
}

const getMatrixHeaderClassGray = (sev) => {
  switch (Number(sev)) {
    case 0: return 'bg-gray-600' // Some groups might use 0-index? Check loop.
    case 1: return 'bg-gray-600'
    case 2: return 'bg-gray-700'
    case 3: return 'bg-gray-800'
    default: return 'bg-gray-800'
  }
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

    no_txn_threshold_hours: {
      any: Number(filters.no_txn_threshold_hours.any),
      cash: Number(filters.no_txn_threshold_hours.cash),
      card: Number(filters.no_txn_threshold_hours.card),
      qr: Number(filters.no_txn_threshold_hours.qr),
      digitalscreen: Number(filters.no_txn_threshold_hours.digitalscreen),
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
    machine_codes: filters.machine_codes_input
      ? filters.machine_codes_input.split(',').map((s) => s.trim()).filter(Boolean)
      : [],
    channel_sku: filters.channel_sku || undefined,
    show_all_errors: filters.show_all_errors,
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

const getCodeCount = (perCode, code) => {
  if (!perCode) {
    return 0
  }
  const match = perCode.find((item) => Number(item.code) === Number(code))
  return match?.count ?? 0
}

const formatEventBreakdown = (event) => {
  const date = new Date(event.created_at)
  const yymmdd =
    String(date.getFullYear()).slice(-2) +
    String(date.getMonth() + 1).padStart(2, '0') +
    String(date.getDate()).padStart(2, '0')

  let hours = date.getHours()
  const suffix = hours >= 12 ? 'pm' : 'am'
  hours %= 12
  if (hours === 0) hours = 12
  const time = `${String(hours).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')} ${suffix}`

  return `${event.channel_code} (E${event.error_code}) - ${yymmdd} ${time}`
}

const getEventsForCode = (events, code) => {
  if (!events) return []
  return events.filter((e) => Number(e.error_code) === Number(code))
}

const formatEventBreakdownSimple = (event) => {
  const date = new Date(event.created_at)
  const yymmdd =
    String(date.getFullYear()).slice(-2) +
    String(date.getMonth() + 1).padStart(2, '0') +
    String(date.getDate()).padStart(2, '0')

  let hours = date.getHours()
  const suffix = hours >= 12 ? 'pm' : 'am'
  hours %= 12
  if (hours === 0) hours = 12
  const time = `${String(hours).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')} ${suffix}`

  return `${event.channel_code} - ${yymmdd} ${time}`
}

const formatErrorDesc = (code, desc) => {
  if (!desc) return ''
  // Remove " (code)" from the end if it exists, assuming format "Description (code)"
  const suffix = ` (${code})`
  if (desc.endsWith(suffix)) {
    return desc.slice(0, -suffix.length)
  }
  return desc
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
              <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
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
                  <span class="font-medium text-gray-700">Vend Prefix</span>
                  <MultiSelect
                    v-model="vendPrefixSelections"
                    :options="vendPrefixSelectOptions"
                    trackBy="id"
                    valueProp="id"
                    label="value"
                    placeholder="Select"
                    open-direction="bottom"
                    mode="tags"
                    class="mt-1"
                  >
                  </MultiSelect>
                </label>

                <label class="flex flex-col space-y-1 text-sm">
                  <span class="font-medium text-gray-700">Operator</span>
                  <MultiSelect
                    v-model="operatorSelections"
                    :options="operatorSelectOptions"
                    trackBy="id"
                    valueProp="id"
                    label="value"
                    placeholder="Select"
                    open-direction="bottom"
                    mode="tags"
                    class="mt-1"
                  >
                  </MultiSelect>
                </label>
                <SearchInput
                  v-model="filters.machine_codes_input"
                  placeholderStr="Machine ID"
                >
                  Machine ID
                  <span class="text-[9px]"> ("," for multiple) </span>
                </SearchInput>
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
            <div class="flex items-center justify-between mb-4">
              <div>
                <h3 class="text-lg font-semibold text-gray-900">(1) Alert on Lost of Connectivity or Electricity</h3>
                <p class="text-sm text-gray-500">Offline hours</p>
              </div>
              <span class="text-sm text-gray-500">Max 60hr</span>
            </div>

            <div class="pb-4">
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-2">
                <div
                  v-for="bucket in connectivity.buckets"
                  :key="bucket.label"
                  class="flex flex-col rounded-lg border border-gray-200"
                  :class="getBucketBgClass(bucket.label)"
                >
                  <div class="p-3 border-b border-gray-200 bg-opacity-50 rounded-t-lg">
                    <h4 class="text-sm font-semibold text-gray-800">
                      {{ bucket.label }}
                      <span class="ml-1 text-xs font-normal text-gray-500">
                        ({{ bucket.rows.length }})
                      </span>
                    </h4>
                  </div>
                  <div class="p-3 overflow-y-auto max-h-[800px]">
                    <ul class="space-y-3">
                      <li
                        v-for="row in bucket.rows"
                        :key="row.vend_id"
                        class="rounded border border-gray-200 bg-white p-3 shadow-sm"
                      >
                        <div class="font-medium text-gray-900">
                          <a
                            :href="'/vends/customers?codes=' + row.vend_code + '&autoload=true'"
                            target="_blank"
                            class="text-indigo-600 hover:text-indigo-900 hover:underline"
                          >
                            {{ row.vend_code }}
                          </a>
                           · {{ (row.hours_offline).toFixed(2) }}
                        </div>
                        <div class="mt-1 text-xs text-gray-500 grid gap-0.5">
                          <div>{{ row.vend_prefix_name ?? '—' }}</div>
                          <div>{{ row.customer_name ?? '—' }}</div>
                          <div class="text-gray-400">
                            Last connect: {{ formatDateTimeComma(row.last_contact_at) }}
                          </div>
                          <div
                              class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit mt-1"
                              :class="[row.acb_vmc_pa_json && row.acb_vmc_pa_json['CSHL_MFG'] ? 'bg-green-200' : 'bg-gray-200 text-gray-400']"
                          >
                              <div class="flex flex-col items-center text-center">
                                  <span class="font-bold">
                                      Cashless Mfg
                                  </span>
                                  <span>
                                      {{row.acb_vmc_pa_json && row.acb_vmc_pa_json['CSHL_MFG'] ? row.acb_vmc_pa_json['CSHL_MFG'] : 'NA' }}
                                  </span>
                              </div>
                          </div>
                        </div>
                      </li>
                      <li
                        v-if="!bucket.rows.length"
                        class="text-xs text-gray-400 italic text-center py-4"
                      >
                        No machines
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section class="bg-white shadow-sm sm:rounded-lg">
          <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900">(2) Temperature Alert</h3>
            <div class="text-sm text-gray-500">
              <div>T1 = Machine's freezer temp</div>
              <div>T2 = Evaporator Temp</div>
              <div class="mt-1 text-xs italic">Note: Temperature alert, not able to monitor machines with 'offline' status</div>
            </div>

            <div v-if="false">
            <form class="mt-4 mb-6 space-y-4 border-b border-gray-200 pb-6" @submit.prevent="applyFilters">
              <div class="grid grid-cols-1 gap-4 md:grid-cols-4 items-end">
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
                  <span class="font-medium text-gray-700">Temp Minimum Threshold (°C)</span>
                  <input
                    v-model.number="filters.temperature_min_threshold"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    step="0.5"
                    type="number"
                  />
                </label>

                <div>
                  <Button class="bg-indigo-600 text-white hover:bg-indigo-700">
                    Apply
                  </Button>
                </div>
              </div>
            </form>
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
                      <a :href="'/vends/customers?codes=' + row.vend_code + '&autoload=true'" target="_blank" class="text-indigo-600 hover:text-indigo-900 hover:underline">
                        {{ row.vend_code }}
                      </a>
                       (Δ {{ formatNumber(row.delta, 1) }}°C)
                    </div>
                    <div class="text-xs text-gray-500">
                      {{ row.vend_prefix_name ?? '—' }}
                    </div>
                    <div class="text-xs text-gray-500">
                      {{ row.customer_name ?? '—' }}
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
                <div v-if="canLoadMoreRising" class="mt-3 flex justify-center">
                  <Button
                    class="border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                    type="button"
                    @click="loadRemainingRising"
                  >
                    Load remaining
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
                      <a :href="'/vends/customers?codes=' + row.vend_code + '&autoload=true'" target="_blank" class="text-indigo-600 hover:text-indigo-900 hover:underline">
                        {{ row.vend_code }}
                      </a>
                       · {{ row.worst_min_temp }}°C
                    </div>
                    <div class="text-xs text-gray-500">
                      {{ row.vend_prefix_name ?? '—' }}
                    </div>
                    <div class="text-xs text-gray-500">
                      {{ row.customer_name ?? '—' }}
                    </div>
                    <div class="text-xs text-gray-500">
                      {{ formatDateTime(row.last_recorded_at) }}
                    </div>
                  </li>
                  <li v-if="!(temperature.worst_minima?.rows?.length)">
                    All machines remained below target in the selected window.
                  </li>
                </ul>
                <div v-if="canLoadMoreWorstMinima" class="mt-3 flex justify-center">
                  <Button
                    class="border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                    type="button"
                    @click="loadRemainingWorstMinima"
                  >
                    Load remaining
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
                      <a :href="'/vends/customers?codes=' + row.vend_code + '&autoload=true'" target="_blank" class="text-indigo-600 hover:text-indigo-900 hover:underline">
                        {{ row.vend_code }}
                      </a>
                       · {{ row.min_value }}°C
                    </div>
                    <div class="text-xs text-gray-500">
                      {{ row.vend_prefix_name ?? '—' }}
                    </div>
                    <div class="text-xs text-gray-500">
                      {{ row.customer_name ?? '—' }}
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
                  v-if="canLoadMoreNotReaching"
                  class="mt-3 flex justify-center"
                >
                  <Button
                    class="border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                    type="button"
                    @click="loadRemainingNotReaching"
                  >
                    Load remaining
                  </Button>
                </div>
              </div>
            </div>
            </div>

              <!-- (2.1) Operation Error / Critical Parts Failure -->
              <div class="mt-8">
                <h4 class="text-sm font-bold text-gray-900 mb-4 bg-gray-100 p-2 rounded">
                  (2.1) Operation Error / Critical Parts Failure
                </h4>
                <div class="border border-gray-200 rounded-lg overflow-hidden bg-white">
                  <div v-for="(group, idx) in matrix21" :key="group.id" class="border-b border-gray-200 last:border-b-0">
                    <div class="grid grid-cols-1 md:grid-cols-4 min-h-[100px]">
                      <!-- Label Column -->
                      <div class="p-4 bg-gray-50 border-r border-gray-100 md:col-span-1 flex flex-col">
                        <div class="font-bold text-sm text-gray-900">{{ group.label }}</div>
                        <div class="text-xs text-gray-500 whitespace-pre-line mt-1">{{ group.sub }}</div>
                        <div v-if="group.note" class="mt-2 text-[10px] text-gray-500 bg-gray-200/50 p-1.5 rounded inline-block">
                            {{ group.note }}
                        </div>
                      </div>

                      <!-- Data Columns -->
                      <div class="md:col-span-3 grid" :style="`grid-template-columns: repeat(${Object.keys(group.headers).length}, minmax(0, 1fr))`">
                        <div v-for="(header, sev) in group.headers" :key="sev" class="border-r border-gray-100 last:border-r-0 flex flex-col">
                            <div class="border-b border-red-800 px-3 py-2 text-xs font-semibold text-white uppercase tracking-wide shadow-sm" :class="getMatrixHeaderClass(sev)">
                              {{ header }}
                            </div>
                            <div class="p-3 flex-1 space-y-3">
                                <template v-if="group.cells[sev] && group.cells[sev].length">
                                    <div v-for="row in group.cells[sev]" :key="row.vend_id" class="text-sm border border-gray-100 p-2 rounded bg-gray-50/50 mb-2 last:mb-0">
                                        <div class="font-medium text-gray-900 mb-1">
                                          <a :href="'/vends/customers?codes=' + row.vend_code + '&autoload=true'" target="_blank"
                                             class="text-indigo-600 hover:text-indigo-900 hover:underline">
                                            {{ row.vend_code }}
                                          </a>
                                          <span class="text-xs text-gray-500 ml-1" v-if="row.meta_duration">
                                              ({{ !Number.isNaN(Number(row.meta_duration)) ? (Math.abs(Number(row.meta_duration)) < 1 ? Math.round(Math.abs(Number(row.meta_duration)) * 60) + ' min' : formatNumber(Math.abs(Number(row.meta_duration)), 2) + ' hr') : row.meta_duration }})
                                          </span>
                                        </div>
                                        <div class="text-xs text-gray-500 mb-0.5">{{ row.vend_prefix_name ?? '-' }}</div>
                                        <div class="text-xs text-gray-500 mb-2">{{ row.customer_name ?? '-' }}</div>

                                        <!-- Value Display -->
                                        <div class="text-xs text-gray-700 mb-2">
                                          <span v-if="row.meta_min_t1">
                                              Last lowest T1: <span class="bg-yellow-600/20 px-1 rounded">{{ formatNumber(row.meta_min_t1, 1) }}°C</span>
                                          </span>
                                          <span v-if="row.meta_max_temp">
                                              Last highest T2: <span class="bg-yellow-600/20 px-1 rounded">{{ formatNumber(row.meta_max_temp, 1) }}°C</span>
                                          </span>
                                          <div class="text-[10px] text-gray-400 mt-0.5">at {{ formatDateTime(row.updated_at) }}</div>
                                        </div>

                                        <!-- T1/T2 Buttons (Mimicking CustomerIndex) -->
                                        <div class="flex flex-col space-y-1 w-full max-w-[120px]">
                                          <span class="text-xs font-bold text-gray-900">Current Temp:</span>

                                          <!-- T1 -->
                                          <a :href="'/vends/' + row.vend_id + '/temp/1'" target="_blank" class="w-full">
                                            <div class="inline-flex items-center px-4 py-1.5 border border-transparent rounded-md font-semibold text-xs text-black w-full justify-center shadow-sm"
                                                 :class="[row.temp !== null ? (row.temp/10 > -15 ? 'bg-red-400' : 'bg-green-400') : 'bg-gray-300']">
                                              {{ row.temp !== null ? formatNumber(row.temp / 10, 1) : '-' }}
                                            </div>
                                          </a>
                                          <!-- T2 -->
                                          <a :href="'/vends/' + row.vend_id + '/temp/2'" target="_blank" class="w-full">
                                            <div class="inline-flex items-center px-4 py-1.5 border border-transparent rounded-md font-semibold text-xs text-black w-full justify-center shadow-sm"
                                                 :class="[row.parameter_json?.t2 !== undefined ? (row.parameter_json.t2/10 > -15 ? 'bg-red-400' : 'bg-green-400') : 'bg-gray-300']">
                                              {{ row.parameter_json?.t2 !== undefined ? formatNumber(row.parameter_json.t2/10, 1) : '-' }}(t2)
                                            </div>
                                          </a>
                                        </div>

                                    </div>
                                </template>
                                <div v-else class="text-xs text-gray-300 italic p-2 text-center">
                                    -
                                </div>
                            </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <!-- (2.2) Preventive Maintenance -->
              <div class="mt-8">
                <h4 class="text-sm font-bold text-gray-900 mb-4 bg-gray-100 p-2 rounded">
                  (2.2) Preventive maintenance / Temp raise alert
                </h4>
                <div class="border border-gray-200 rounded-lg overflow-hidden bg-white">
                  <div v-for="(group, idx) in matrix22" :key="group.id" class="border-b border-gray-200 last:border-b-0">
                    <div class="grid grid-cols-1 md:grid-cols-4 min-h-[100px]">
                      <!-- Label -->
                      <div class="p-4 bg-gray-50 border-r border-gray-100 md:col-span-1 flex flex-col">
                        <div class="font-bold text-sm text-gray-900">{{ group.label }}</div>
                        <div class="text-xs text-gray-500 whitespace-pre-line mt-1">{{ group.sub }}</div>
                         <div v-if="group.note" class="mt-2 text-[10px] text-gray-500 bg-gray-200/50 p-1.5 rounded inline-block">
                            {{ group.note }}
                        </div>
                      </div>

                       <!-- Data Columns -->
                      <div class="md:col-span-3 grid" :style="`grid-template-columns: repeat(${Object.keys(group.headers).length}, minmax(0, 1fr))`">
                        <div v-for="(header, sev) in group.headers" :key="sev" class="border-r border-gray-100 last:border-r-0 flex flex-col">
                            <div :class="getMatrixHeaderClassGray(sev)" class="border-b border-gray-600 px-3 py-2 text-xs font-semibold text-white uppercase tracking-wide">
                              {{ header }}
                            </div>
                            <div class="p-3 flex-1 space-y-3">
                                <template v-if="group.cells[sev] && group.cells[sev].length">
                                    <div v-for="row in group.cells[sev]" :key="row.vend_id" class="text-sm border border-gray-100 p-2 rounded bg-gray-50/50 mb-2 last:mb-0">
                                      <div class="font-medium text-gray-900 mb-1">
                                          <a :href="'/vends/customers?codes=' + row.vend_code + '&autoload=true'" target="_blank"
                                             class="text-indigo-600 hover:text-indigo-900 hover:underline">
                                            {{ row.vend_code }}
                                          </a>
                                          <span class="text-xs text-gray-500 ml-1" v-if="row.meta_duration">
                                              ({{ !Number.isNaN(Number(row.meta_duration)) ? (Math.abs(Number(row.meta_duration)) < 1 ? Math.round(Math.abs(Number(row.meta_duration)) * 60) + ' min' : formatNumber(Math.abs(Number(row.meta_duration)), 2) + ' hr') : row.meta_duration }})
                                          </span>
                                        </div>
                                        <div class="text-xs text-gray-500 mb-0.5">{{ row.vend_prefix_name ?? '-' }}</div>
                                        <div class="text-xs text-gray-500 mb-2">{{ row.customer_name ?? '-' }}</div>

                                        <!-- Value Display -->
                                        <div class="text-xs text-gray-700 mb-2">
                                           <div v-if="row.alert_type.includes('rising')">
                                              Last 24hrs lowest {{ row.alert_type.includes('t2') ? 'T2' : 'T1' }}: <span class="bg-yellow-600/20 px-1 rounded">{{ formatNumber(row.latest_min_temp, 1) }}°C</span>
                                              <div class="text-[10px] text-gray-400 mt-0.5">at {{ formatDateTime(row.updated_at) }}</div>

                                              <div class="mt-2" v-if="row.first_min_temp !== null">
                                                  Last 48hrs lowest {{ row.alert_type.includes('t2') ? 'T2' : 'T1' }}: <span class="bg-yellow-600/20 px-1 rounded">{{ formatNumber(row.first_min_temp, 1) }}°C</span>
                                                  <div class="text-[10px] text-gray-400 mt-0.5">at {{ formatDateTime(row.first_min_temp_at) }}</div>
                                              </div>

                                              <div class="mt-1 text-xs">Delta: {{ formatNumber(row.delta, 1) }}°C</div>
                                           </div>
                                           <div v-else>
                                              <span v-if="row.meta_min_t1">
                                                  Last lowest T1: <span class="bg-yellow-600/20 px-1 rounded">{{ formatNumber(row.meta_min_t1, 1) }}°C</span>
                                              </span>
                                              <span v-if="row.meta_max_temp">
                                                  Last highest T2: <span class="bg-yellow-600/20 px-1 rounded">{{ formatNumber(row.meta_max_temp, 1) }}°C</span>
                                              </span>
                                              <div class="text-[10px] text-gray-400 mt-0.5">at {{ formatDateTime(row.updated_at) }}</div>
                                           </div>
                                        </div>

                                        <!-- T1/T2 Buttons (Mimicking CustomerIndex) -->
                                        <div class="flex flex-col space-y-1 w-full max-w-[120px]">
                                          <span class="text-xs font-bold text-gray-900">Current Temp:</span>

                                          <!-- T1 -->
                                          <a :href="'/vends/' + row.vend_id + '/temp/1'" target="_blank" class="w-full">
                                            <div class="inline-flex items-center px-4 py-1.5 border border-transparent rounded-md font-semibold text-xs text-black w-full justify-center shadow-sm"
                                                 :class="[row.temp !== null ? (row.temp/10 > -15 ? 'bg-red-400' : 'bg-green-400') : 'bg-gray-300']">
                                              {{ row.temp !== null ? formatNumber(row.temp / 10, 1) : '-' }}
                                            </div>
                                          </a>
                                          <!-- T2 -->
                                          <a :href="'/vends/' + row.vend_id + '/temp/2'" target="_blank" class="w-full">
                                            <div class="inline-flex items-center px-4 py-1.5 border border-transparent rounded-md font-semibold text-xs text-black w-full justify-center shadow-sm"
                                                 :class="[row.parameter_json?.t2 !== undefined ? (row.parameter_json.t2/10 > -15 ? 'bg-red-400' : 'bg-green-400') : 'bg-gray-300']">
                                              {{ row.parameter_json?.t2 !== undefined ? formatNumber(row.parameter_json.t2/10, 1) : '-' }}(t2)
                                            </div>
                                          </a>
                                        </div>

                                    </div>
                                 </template>
                                 <div v-else class="text-xs text-gray-300 italic p-2 text-center">
                                    -
                                </div>
                            </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

          </div>
        </section>

        <section class="bg-white shadow-sm sm:rounded-lg">
          <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900">(3) Alert on Lost of Transaction/Sales</h3>
            <form class="mt-4 mb-6 space-y-4 border-b border-gray-200 pb-6" @submit.prevent="applyFilters">
              <div class="grid grid-cols-1 gap-4 md:grid-cols-5 items-end">
                <label class="flex flex-col space-y-1 text-sm">
                  <span class="font-medium text-gray-700">No any Sales (>= hr)</span>
                  <input
                    v-model.number="filters.no_txn_threshold_hours.any"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    min="1"
                    type="number"
                  />
                </label>
                <label class="flex flex-col space-y-1 text-sm">
                  <span class="font-medium text-gray-700">No Cash Sales (>= hr)</span>
                  <input
                    v-model.number="filters.no_txn_threshold_hours.cash"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    min="1"
                    type="number"
                  />
                </label>
                <label class="flex flex-col space-y-1 text-sm">
                  <span class="font-medium text-gray-700">No Sales via Card Terminal (>= hr)</span>
                  <input
                    v-model.number="filters.no_txn_threshold_hours.card"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    min="1"
                    type="number"
                  />
                </label>
                <label class="flex flex-col space-y-1 text-sm">
                  <span class="font-medium text-gray-700">No Sales via QR (>= hr)</span>
                  <input
                    v-model.number="filters.no_txn_threshold_hours.qr"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    min="1"
                    type="number"
                  />
                </label>
                <label class="flex flex-col space-y-1 text-sm">
                  <span class="font-medium text-gray-700">No Digital Screen Activity (>= hr)</span>
                  <input
                    v-model.number="filters.no_txn_threshold_hours.digitalscreen"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    min="1"
                    type="number"
                  />
                </label>
                <div>
                  <Button class="bg-indigo-600 text-white hover:bg-indigo-700">
                    Apply
                  </Button>
                </div>
              </div>
            </form>
            <div class="mt-4 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
              <div class="rounded-lg border border-gray-200 p-4">
                <h4 class="text-sm font-semibold text-gray-800">
                  No any Sales ({{ noTransactions.thresholds?.any ?? filters.no_txn_threshold_hours.any }}hr)
                </h4>
                <ul class="mt-3 space-y-3 text-sm text-gray-700">
                  <li
                    v-for="row in noTxnAnyRows"
                    :key="row.vend_id"
                    class="rounded border border-gray-100 p-3"
                  >
                    <div class="font-medium text-gray-900">
                      <a :href="'/vends/customers?codes=' + row.vend_code + '&autoload=true'" target="_blank" class="text-indigo-600 hover:text-indigo-900 hover:underline">
                        {{ row.vend_code }}
                      </a>
                       · {{ formatHours(row.hours_since) }}
                    </div>
                    <div class="text-xs text-gray-500">
                      {{ row.vend_prefix_name ?? '—' }}
                    </div>
                    <div class="text-xs text-gray-500">
                      {{ row.customer_name ?? '—' }}
                    </div>
                    <div class="text-xs text-gray-500">
                      L30d: ${{ formatNumber((row.l30d_sales || 0) / 100) }}
                    </div>
                    <div class="text-xs text-gray-500">
                      Last on: {{ formatDateTimeComma(row.last_transaction_at) }}
                    </div>
                  </li>
                  <li v-if="!(noTransactions.any_sales?.length)">
                    All machines recorded recent transactions.
                  </li>
                </ul>
                <div
                  v-if="canLoadMoreNoTxnAny"
                  class="mt-3 flex justify-center"
                >
                  <Button
                    class="border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                    type="button"
                    @click="loadRemainingNoTxnAny"
                  >
                    Load remaining
                  </Button>
                </div>
              </div>

              <div class="rounded-lg border border-gray-200 p-4">
                <h4 class="text-sm font-semibold text-gray-800">
                  No Cash Sales ({{ noTransactions.thresholds?.cash ?? filters.no_txn_threshold_hours.cash }}hr)
                </h4>
                <ul class="mt-3 space-y-3 text-sm text-gray-700">
                  <li
                    v-for="row in noTxnCashRows"
                    :key="`${row.vend_id}-cash`"
                    class="rounded border border-gray-100 p-3"
                  >
                    <div class="font-medium text-gray-900">
                      <a :href="'/vends/customers?codes=' + row.vend_code + '&autoload=true'" target="_blank" class="text-indigo-600 hover:text-indigo-900 hover:underline">
                        {{ row.vend_code }}
                      </a>
                       · {{ formatHours(row.hours_since) }}
                    </div>
                    <div class="text-xs text-gray-500">
                      {{ row.vend_prefix_name ?? '—' }}
                    </div>
                    <div class="text-xs text-gray-500">
                      {{ row.customer_name ?? '—' }}
                    </div>
                    <div class="text-xs text-gray-500">
                      L30d: ${{ formatNumber((row.l30d_sales || 0) / 100) }}
                    </div>
                    <div class="text-xs text-gray-500">
                      Last on: {{ formatDateTimeComma(row.last_transaction_at) }}
                    </div>
                    <div
                       class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit mt-1"
                       :class="[row.parameter_json && row.parameter_json['CoinCnt'] > 1600 ? 'bg-green-200' : 'bg-red-200']"
                       v-if="getCoinFloat(row) !== null"
                    >
                       <div class="flex flex-col items-center text-center">
                           <span class="font-bold">
                               Coin Float
                           </span>
                           <span>
                               {{ getCoinFloat(row) }}
                           </span>
                       </div>
                    </div>
                  </li>
                  <li v-if="!(noTransactions.cash_sales?.length)">
                    No cash-specific gaps detected.
                  </li>
                </ul>
                <div
                  v-if="canLoadMoreNoTxnCash"
                  class="mt-3 flex justify-center"
                >
                  <Button
                    class="border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                    type="button"
                    @click="loadRemainingNoTxnCash"
                  >
                    Load remaining
                  </Button>
                </div>
              </div>

              <div class="rounded-lg border border-gray-200 p-4">
                <h4 class="text-sm font-semibold text-gray-800">
                  No Sales via Card Terminal ({{ noTransactions.thresholds?.card ?? filters.no_txn_threshold_hours.card }}hr)
                </h4>
                <ul class="mt-3 space-y-3 text-sm text-gray-700">
                  <li
                    v-for="row in noTxnCardRows"
                    :key="`${row.vend_id}-card`"
                    class="rounded border border-gray-100 p-3"
                  >
                    <div class="font-medium text-gray-900">
                      <a :href="'/vends/customers?codes=' + row.vend_code + '&autoload=true'" target="_blank" class="text-indigo-600 hover:text-indigo-900 hover:underline">
                        {{ row.vend_code }}
                      </a>
                       · {{ formatHours(row.hours_since) }}
                    </div>
                    <div class="text-xs text-gray-500">
                      {{ row.vend_prefix_name ?? '—' }}
                    </div>
                    <div class="text-xs text-gray-500">
                      {{ row.customer_name ?? '—' }}
                    </div>
                    <div class="text-xs text-gray-500">
                      L30d: ${{ formatNumber((row.l30d_sales || 0) / 100) }}
                    </div>
                    <div class="text-xs text-gray-500">
                      Last on: {{ formatDateTimeComma(row.last_transaction_at) }}
                    </div>
                    <div
                       class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit mt-1"
                       :class="[row.acb_vmc_pa_json && row.acb_vmc_pa_json['CSHL_MFG'] ? 'bg-green-200' : 'bg-gray-200 text-gray-400']"
                       v-if="row.acb_vmc_pa_json && 'CSHL_MFG' in row.acb_vmc_pa_json"
                    >
                       <div class="flex flex-col items-center text-center">
                           <span class="font-bold">
                               Cashless Mfg
                           </span>
                           <span>
                               {{row.acb_vmc_pa_json['CSHL_MFG'] ? row.acb_vmc_pa_json['CSHL_MFG'] : 'NA' }}
                           </span>
                       </div>
                    </div>
                  </li>
                  <li v-if="!(noTransactions.card_sales?.length)">
                    No credit card gaps detected.
                  </li>
                </ul>
                <div
                  v-if="canLoadMoreNoTxnCard"
                  class="mt-3 flex justify-center"
                >
                  <Button
                    class="border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                    type="button"
                    @click="loadRemainingNoTxnCard"
                  >
                    Load remaining
                  </Button>
                </div>
              </div>

              <div class="rounded-lg border border-gray-200 p-4">
                <h4 class="text-sm font-semibold text-gray-800">
                  No Sales via QR ({{ noTransactions.thresholds?.qr ?? filters.no_txn_threshold_hours.qr }}hr)
                </h4>
                <ul class="mt-3 space-y-3 text-sm text-gray-700">
                  <li
                    v-for="row in noTxnQrRealRows"
                    :key="`${row.vend_id}-qr-real`"
                    class="rounded border border-gray-100 p-3"
                  >
                    <div class="font-medium text-gray-900">
                      <a :href="'/vends/customers?codes=' + row.vend_code + '&autoload=true'" target="_blank" class="text-indigo-600 hover:text-indigo-900 hover:underline">
                        {{ row.vend_code }}
                      </a>
                       · {{ formatHours(row.hours_since) }}
                    </div>
                    <div class="text-xs text-gray-500">
                      {{ row.vend_prefix_name ?? '—' }}
                    </div>
                    <div class="text-xs text-gray-500">
                      {{ row.customer_name ?? '—' }}
                    </div>
                    <div class="text-xs text-gray-500">
                      L30d: ${{ formatNumber((row.l30d_sales || 0) / 100) }}
                    </div>
                    <div class="text-xs text-gray-500">
                      Last on: {{ formatDateTimeComma(row.last_transaction_at) }}
                    </div>

                  </li>
                  <li v-if="!(noTransactions.qr_sales?.length)">
                    No QR sales gaps detected.
                  </li>
                </ul>
                <div
                  v-if="canLoadMoreNoTxnQrReal"
                  class="mt-3 flex justify-center"
                >
                  <Button
                    class="border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                    type="button"
                    @click="loadRemainingNoTxnQrReal"
                  >
                    Load remaining
                  </Button>
                </div>
              </div>

              <div class="rounded-lg border border-gray-200 p-4">
                <h4 class="text-sm font-semibold text-gray-800">
                  No Digital Screen Activity ({{ noTransactions.thresholds?.digitalscreen ?? filters.no_txn_threshold_hours.digitalscreen }}hr)
                </h4>
                <ul class="mt-3 space-y-3 text-sm text-gray-700">
                  <li
                    v-for="row in noTxnDigitalScreenRows"
                    :key="`${row.vend_id}-digitalscreen`"
                    class="rounded border border-gray-100 p-3"
                  >
                    <div class="font-medium text-gray-900">
                      <a :href="'/vends/customers?codes=' + row.vend_code + '&autoload=true'" target="_blank" class="text-indigo-600 hover:text-indigo-900 hover:underline">
                        {{ row.vend_code }}
                      </a>
                       · {{ formatHours(row.hours_since) }}
                    </div>
                    <div class="text-xs text-gray-500">
                      {{ row.vend_prefix_name ?? '—' }}
                    </div>
                    <div class="text-xs text-gray-500">
                      {{ row.customer_name ?? '—' }}
                    </div>
                    <div class="text-xs text-gray-500">
                      L30d: ${{ formatNumber((row.l30d_sales || 0) / 100) }}
                    </div>
                    <div class="text-xs text-gray-500">
                      Last on: {{ formatDateTimeComma(row.last_transaction_at) }}
                    </div>
                    <div class="flex gap-2 flex-wrap">
                      <div
                         class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit mt-1"
                         :class="[row.parameter_json && row.parameter_json['CoinCnt'] > 1600 ? 'bg-green-200' : 'bg-red-200']"
                         v-if="getCoinFloat(row) !== null"
                      >
                         <div class="flex flex-col items-center text-center">
                             <span class="font-bold">
                                 Coin Float
                             </span>
                             <span>
                                 {{ getCoinFloat(row) }}
                             </span>
                         </div>
                      </div>
                      <div
                         class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit mt-1"
                         :class="[row.acb_vmc_pa_json && row.acb_vmc_pa_json['CSHL_MFG'] ? 'bg-green-200' : 'bg-gray-200 text-gray-400']"
                         v-if="row.acb_vmc_pa_json && 'CSHL_MFG' in row.acb_vmc_pa_json"
                      >
                         <div class="flex flex-col items-center text-center">
                             <span class="font-bold">
                                 Cashless Mfg
                             </span>
                             <span>
                                 {{row.acb_vmc_pa_json['CSHL_MFG'] ? row.acb_vmc_pa_json['CSHL_MFG'] : 'NA' }}
                             </span>
                         </div>
                      </div>
                    </div>
                  </li>
                  <li v-if="!(noTransactions.digitalscreen_sales?.length)">
                    No digital screen gaps detected.
                  </li>
                </ul>
                <div
                  v-if="canLoadMoreNoTxnDigitalScreen"
                  class="mt-3 flex justify-center"
                >
                  <Button
                    class="border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                    type="button"
                    @click="loadRemainingNoTxnDigitalScreen"
                  >
                    Load remaining
                  </Button>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section class="bg-white shadow-sm sm:rounded-lg">
          <div class="p-6">
            <div class="flex items-center justify-between">
              <h3 class="text-lg font-semibold text-gray-900">(4) Channel Errors</h3>
              <p class="text-sm text-gray-500">
                Grouped by dispense stability vs. mechanical codes.
              </p>
            </div>

            <form class="mt-4 mb-6 space-y-4 border-b border-gray-200 pb-6" @submit.prevent="applyFilters">
              <div class="grid grid-cols-1 gap-4 md:grid-cols-3 items-end">
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
                  <span class="font-medium text-gray-700">Show All Error(s)?</span>
                  <select
                    v-model="filters.show_all_errors"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                  >
                    <option :value="true">Yes (Include Cleared)</option>
                    <option :value="false">No (Not yet Cleared only)</option>
                  </select>
                </label>
                <div>
                  <Button class="bg-indigo-600 text-white hover:bg-indigo-700">
                    Apply
                  </Button>
                </div>
              </div>
            </form>

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
                  <div class="text-xs text-gray-500 flex flex-col items-end space-y-1">
                    <div>
                      Errors: {{ bucket.codes?.map((code) => `E${code}`).join(', ') }}
                    </div>
                    <div class="flex items-center space-x-1">
                      <CheckCircleIcon class="h-4 w-4 text-green-500" />
                      <span>= Cleared</span>
                    </div>
                  </div>
                </div>
                <div class="overflow-x-auto">
                  <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-white">
                      <tr>
                        <th class="px-4 py-2 text-left font-medium text-gray-500">
                          <div class="flex flex-col space-y-1">
                            <span>Machine</span>
                            <span>Vend Prefix</span>
                            <span>Customer</span>
                          </div>
                        </th>

                        <th class="px-4 py-2 text-left font-medium text-gray-500">
                          Counts of Error
                        </th>
                        <th
                          v-for="code in bucket.codes"
                          :key="code"
                          class="px-4 py-2 text-left font-medium text-gray-500 align-top"
                        >
                          <div class="whitespace-nowrap">Error {{ code }}</div>
                          <div class="text-xs font-normal text-gray-600 break-words w-24">
                            {{ formatErrorDesc(code, errorDefinitions[code]) }}
                          </div>
                        </th>

                      </tr>
                    </thead>
                    <tbody v-if="bucket.rows?.length" class="divide-y divide-gray-200 bg-white">
                      <tr v-for="row in visibleBucketRows(bucket)" :key="row.vend_id">
                        <td class="px-4 py-2">
                          <div class="flex flex-col space-y-1">
                            <div class="font-medium text-gray-900">
                              <a :href="'/vends/customers?codes=' + row.vend_code + '&autoload=true'" target="_blank" class="text-indigo-600 hover:text-indigo-900 hover:underline">
                                {{ row.vend_code }}
                              </a>
                            </div>
                            <div class="text-xs text-gray-700">
                              {{ row.vend_prefix_name ?? '—' }}
                            </div>
                            <div class="text-xs text-gray-500">
                              {{ row.customer_name ?? '—' }}
                            </div>
                          </div>
                        </td>

                        <td class="px-4 py-2 text-gray-700">
                          {{ row.total_events }}
                        </td>
                        <td
                          v-for="code in bucket.codes"
                          :key="code"
                          class="px-4 py-2 text-gray-700 text-xs"
                        >
                          <div class="font-bold mb-1 border-b border-gray-100 pb-1">
                            Count = {{ getEventsForCode(row.events, code).length }}
                          </div>
                          <div
                            v-for="(event, index) in getEventsForCode(row.events, code)"
                            :key="index"
                            class="whitespace-nowrap flex items-center gap-1"
                            :class="{ 'text-red-600': !event.is_error_cleared }"
                          >
                            <CheckCircleIcon
                              v-if="event.is_error_cleared"
                              class="h-4 w-4 text-green-500"
                            />
                            <div v-else class="h-4 w-4" />
                            {{ formatEventBreakdownSimple(event) }}
                          </div>
                        </td>

                      </tr>
                    </tbody>
                    <tbody v-else>
                      <tr>
                        <td
                          class="px-4 py-6 text-center text-sm text-gray-500"
                          :colspan="4 + (bucket.codes?.length ?? 0)"
                        >
                          No events detected for this grouping.
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
                <div
                  v-if="canExpandBucket(bucket)"
                  class="border-t border-gray-200 px-4 py-3 flex justify-center"
                >
                  <Button
                    class="border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                    type="button"
                    @click="expandBucketRows(bucket.key)"
                  >
                    Load remaining
                  </Button>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section class="bg-white shadow-sm sm:rounded-lg">
          <div class="p-6">
            <h3 class="text-lg font-semibold text-gray-900">
              (5) Stockout KPI Overview
            </h3>
            <p class="text-sm text-gray-500">
              Rolling {{ stockouts.metadata?.lookback_days ?? 30 }}-day window based on
              refill telemetry.
            </p>

            <form class="mt-4 mb-6 space-y-4 border-b border-gray-200 pb-6" @submit.prevent="applyFilters">
              <div class="grid grid-cols-1 gap-4 md:grid-cols-3 items-end">
                <label class="flex flex-col space-y-1 text-sm">
                  <span class="font-medium text-gray-700">Channel Limit (Stockouts)</span>
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
                  <span class="font-medium text-gray-700">Stockout Target (hours)</span>
                  <input
                    v-model.number="filters.stockout_target_hours"
                    class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    min="1"
                    type="number"
                  />
                </label>
                <div>
                  <Button class="bg-indigo-600 text-white hover:bg-indigo-700">
                    Apply
                  </Button>
                </div>
              </div>
            </form>

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
                          <a :href="'/vends/customers?codes=' + row.vend_code + '&autoload=true'" target="_blank" class="text-indigo-600 hover:text-indigo-900 hover:underline">
                            {{ row.vend_code }}
                          </a>
                           · {{ row.channel_code ?? '—' }}
                        </div>
                        <div class="text-xs text-gray-500">
                          {{ row.vend_prefix_name ?? '—' }}
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
              <div v-if="canLoadMoreStockouts" class="mt-4 flex justify-center">
                <Button
                  class="border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                  type="button"
                  @click="loadRemainingStockouts"
                >
                  Load remaining
                </Button>
              </div>
            </div>
          </div>
        </section>


      </div>
    </div>
  </AuthenticatedLayout>
</template>
