<template>
  <Head title="Stock Count Dashboard" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex flex-col space-y-1">
        <div class="flex space-x-2 items-center">
          Stock Count Dashboard
        </div>
      </div>
    </template>

    <div class="p-3">
      <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
        <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
          <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
            <SearchInput placeholderStr="Machine ID" v-model="filters.codes" @keyup.enter="onSearchFilterUpdated()">
              Machine ID
              <span class="text-[9px]">( "," for multiple )</span>
            </SearchInput>

            <SearchInput placeholderStr="Site" v-model="filters.customer" @keyup.enter="onSearchFilterUpdated()">
              Site
            </SearchInput>

            <div>
              <label class="block text-sm font-medium text-gray-700">Machine Prefix</label>
              <MultiSelect
                v-model="filters.vendPrefixes"
                :options="vendPrefixOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
                mode="tags"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Operator</label>
              <MultiSelect
                v-model="filters.operators"
                :options="operatorOptions"
                trackBy="id"
                valueProp="id"
                label="full_name"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
                mode="tags"
                @select="onOperatorSelect"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Product</label>
              <MultiSelect
                v-model="filters.products"
                :options="productOptions"
                trackBy="id"
                valueProp="id"
                label="full_name"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
                mode="tags"
              />
            </div>

            <div>
              <label class="block text-sm font-medium text-gray-700">Location Type</label>
              <MultiSelect
                v-model="filters.locationType"
                :options="locationTypeOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              />
            </div>
          </div>

          <div class="flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5">
            <div class="mt-3">
              <div class="flex space-x-1">
                <Button
                  class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                  @click="onSearchFilterUpdated()"
                >
                  <MagnifyingGlassIcon class="h-4 w-4" aria-hidden="true"/>
                  <span>Search</span>
                </Button>

                <Button
                  class="inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                  @click="resetFilters()"
                >
                  <BackspaceIcon class="h-4 w-4" aria-hidden="true"/>
                  <span>Reset</span>
                </Button>
              </div>
            </div>
          </div>
        </div>
        <!-- /FILTERS -->

        <!-- CHART 1 -->
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
          <div class="p-1 py-4 bg-white border-b border-gray-200">
            <Graph
              :key="componentKey"
              type="scatter"
              :labels="dayGraphLabels"
              :datasets="dayGraphDatasets"
              :options="dayGraphOptions"
            />
          </div>

          <!-- CHART 2 -->
          <div class="p-1 py-4 bg-white border-b border-gray-200">
            <Graph
              :key="qtyKey"
              type="scatter"
              :labels="qtyLabels"
              :datasets="qtyDatasets"
              :options="qtyOptions"
            />
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import { ref, onBeforeMount, onMounted } from 'vue'
import { Head, usePage, router } from '@inertiajs/vue3'
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import Graph from '@/Components/Graph.vue'
import Button from '@/Components/Button.vue'
import DatePicker from '@/Components/DatePicker.vue'
import SearchInput from '@/Components/SearchInput.vue'
import MultiSelect from '@/Components/MultiSelect.vue'
import { MagnifyingGlassIcon, BackspaceIcon } from '@heroicons/vue/20/solid'

/**
 * Controller should pass the same option props used by the Daily Stock Count page
 * (locationTypeOptions, operatorOptions, productOptions, reportDateOptions, vendPrefixOptions)
 * in addition to the two graph datasets below.
 */
const props = defineProps({
  dayGraphData: { type: Object, required: true },
  qtyGraphData: { type: Object, required: true },

  // filter option props (same shapes as your other page)
  locationTypeOptions: Object,
  operatorOptions: Object,
  productOptions: Object,
  vendPrefixOptions: Object,
})

const operatorCountry = usePage().props.auth.operatorCountry
const authOperator = usePage().props.auth.operator

/* ----------------- filters (same as Daily Stock Count) ----------------- */
const filters = ref({
  codes: '',
  customer: '',
  date: '',
  currentFilterDate: '',
  locationType: '',
  operators: [],
  products: [],
  vendPrefixes: [],
})

const locationTypeOptions = ref([])
const operatorOptions = ref([])
const productOptions = ref([])
const vendPrefixOptions = ref([])

onMounted(() => {
  locationTypeOptions.value = [
    {id: 'all', value: 'All'},
    ...props.locationTypeOptions?.data?.map(d => ({ id: d.id, value: d.name })) ?? [],
  ]
  operatorOptions.value = [
    {id: 'all', full_name: 'All'},
    ...props.operatorOptions?.data?.map(d => ({ id: d.id, code: d.code, full_name: d.full_name })) ?? [],
  ]
  productOptions.value = [
    {id: 'all', full_name: 'All'},
    ...props.productOptions?.data?.map(d => ({ id: d.id, full_name: `(${d.code}) ${d.name}` })) ?? [],
  ]
  vendPrefixOptions.value = [
    {id: 'all', value: 'All'},
    {id: 'single-ud', value: 'Single UD'},
    ...props.vendPrefixOptions?.data?.map(d => ({ id: d.id, value: d.name })) ?? [],
  ]

  // defaults (same HIPL cluster behaviour)
  filters.value.locationType = locationTypeOptions.value[0]
  filters.value.operators = authOperator ? [
    operatorOptions.value.find(o => o?.id === authOperator.id),
    ...(authOperator.code === 'HIPL'
      ? ['HIMD','LEA','HIESG','UL-ST'].map(c => operatorOptions.value.find(o => o?.code === c))
      : []),
  ].filter(Boolean) : [operatorOptions.value[0]]
  filters.value.vendPrefixes = [vendPrefixOptions.value[0]]
})

function onOperatorSelect(selectedItem) {
  if (selectedItem.id === 'all') {
    filters.value.operators = [selectedItem];
  } else {
    filters.value.operators = filters.value.operators.filter(o => o.id !== 'all');
  }
}

function onSearchFilterUpdated () {
  router.get('/reports/stock-count-dashboard', {
    ...filters.value,
    locationType: filters.value.locationType?.id,
    location_type_id: filters.value.locationType?.id,
    operators: (filters.value.operators ?? []).filter(o => o).map(o => o.id),
    vendPrefixes: (filters.value.vendPrefixes ?? []).map(v => v.id),
    products: (filters.value.products ?? []).map(p => p.id),
  }, {
    preserveState: true,
    replace: true,
    onSuccess: () => {
      syncData()
    }
  })
}

function resetFilters () {
  router.get('/reports/stock-count-dashboard')
}
/* ---------------------------------------------------------------------- */

/* ----------------- charts (your existing code) ----------------- */
const componentKey = ref(0)
const dayGraphLabels = ref([])
const dayGraphDatasets = ref([])
const qtyKey = ref(0)
const qtyLabels = ref([])
const qtyDatasets = ref([])

const dayGraphOptions = ref({
  scales: {
    x: { ticks: { min: 1, max: 31, stepSize: 1 } },
    y: {
      position: 'left',
      title: { display: true, text: 'Stock Value in Machines (' + operatorCountry.currency_symbol + ')' },
      beginAtZero: true,
    },
    y1: {
      position: 'right',
      title: { display: true, text: 'Total Stock Cost - before GST (' + operatorCountry.currency_symbol + ')' },
      beginAtZero: true,
    },
  },
  plugins: {
    title: { display: true, text: 'Stock Value, Stock Cost & Coin Float by Day (' + operatorCountry.currency_symbol + ')' },
    legend: { reverse: false },
  }
})

const qtyOptions = ref({
  scales: {
    x: { ticks: { min: 1, max: 31, stepSize: 1 } },
    y: { position: 'left', title: { display: true, text: 'Quantity (#)' }, beginAtZero: true },
    y2: {
      position: 'right',
      title: { display: true, text: 'Machine Stock Balance (%)' },
      min: 0,
      max: 100,
      grid: { drawOnChartArea: false },
    },
  },
  plugins: {
    title: { display: true, text: 'Stock Qty in Machine; Stock Qty in Warehouse; Machine Stock Balance %, by Day' },
    legend: { reverse: false },
    tooltip: {
      callbacks: {
        title(items) { const d = items?.[0]?.dataIndex; return d != null ? `Day ${d + 1}` : '' }
      }
    }
  }
})

const monthColors = ['#2563eb','#dc2626','#16a34a','#9333ea','#f59e0b']

function hexToRGBA (hex, a) {
  const r = parseInt(hex.slice(1,3),16), g = parseInt(hex.slice(3,5),16), b = parseInt(hex.slice(5,7),16)
  return `rgba(${r}, ${g}, ${b}, ${a})`
}

onBeforeMount(() => {
  syncData()
})

function syncData() {
  dayGraphLabels.value = []
  dayGraphDatasets.value = []
  qtyLabels.value = []
  qtyDatasets.value = []

  for (let i = 1; i <= 31; i++) dayGraphLabels.value.push(i)

  const colors = ['#3e95cd', '#ff7f7f', '#007500', '#4b5563', '#c45850']
  const rows = JSON.parse(JSON.stringify(props.dayGraphData?.data ?? []))

  const byMonth = rows.reduce((m, r) => ((m[r.month_name] ??= []).push(r), m), {})
  const sortedMonths = Object.keys(byMonth).sort((a, b) => new Date(a) - new Date(b))

  sortedMonths.forEach((month, idx) => {
    const monthRows = byMonth[month]
    const amount = Array.from({ length: 31 }, (_, d) => {
      const found = monthRows.find(r => Number(r.day) === d + 1)
      return found ? Number(found.amount) : null
    })
    const count = Array.from({ length: 31 }, (_, d) => {
      const found = monthRows.find(r => Number(r.day) === d + 1)
      return found ? Number(found.count) : null
    })
    const coin = Array.from({ length: 31 }, (_, d) => {
      const found = monthRows.find(r => Number(r.day) === d + 1)
      return found ? Number(found.coin_float) : null
    })

    const isCurrent = idx === sortedMonths.length - 1;
    const barColor = isCurrent ? '#ef4444' : '#3b82f6';
    const lineColor = isCurrent ? '#4b5563' : '#15803d';

    dayGraphDatasets.value.push({
      label: `${month} Stock Value in Machines (${operatorCountry.currency_symbol})`,
      data: amount,
      backgroundColor: hexToRGBA(barColor, isCurrent ? 1 : 0.4),
      borderColor: hexToRGBA(barColor, isCurrent ? 1 : 0.4),
      fill: false,
      yAxisID: 'y',
      type: 'bar',
      spanGaps: false
    })

    dayGraphDatasets.value.push({
      label: `${month} Total Stock Cost (${operatorCountry.currency_symbol})`,
      data: count,
      backgroundColor: hexToRGBA(lineColor, isCurrent ? 1 : 0.4),
      borderColor: hexToRGBA(lineColor, isCurrent ? 1 : 0.4),
      yAxisID: 'y1',
      type: 'line',
      spanGaps: false
    })


      // Assign different colors for this month and last month coin float lines
      let coinFloatColor = '#374151';
      let coinFloatBg = hexToRGBA('#374151', 0.15);
      let coinFloatDash = [6, 4];
      let coinFloatBorderWidth = 5;
      if (idx === 0) { // This month
        coinFloatColor = '#2563eb'; // blue
        coinFloatBg = hexToRGBA('#2563eb', 0.15);
        coinFloatDash = [6, 4];
        coinFloatBorderWidth = 5;
      } else if (idx === 1) { // Last month
        coinFloatColor = '#dc2626'; // red
        coinFloatBg = hexToRGBA('#dc2626', 0.15);
        coinFloatDash = [6, 4]; // different dash pattern
        coinFloatBorderWidth = 5; // wider red dotted line
      }
      dayGraphDatasets.value.push({
        label: `${month} Coin Float (${operatorCountry.currency_symbol})`,
        data: coin,
        yAxisID: 'y1',
        type: 'line',
        backgroundColor: coinFloatBg,
        borderColor: coinFloatColor,
        borderDash: coinFloatDash,
        borderWidth: coinFloatBorderWidth,
        pointRadius: 0,
        pointHoverRadius: 6,
        pointHitRadius: 20,
        spanGaps: false
      })
  })

  componentKey.value += 1

  // qty chart
  for (let i = 1; i <= 31; i++) qtyLabels.value.push(i)

  const qtyRows = JSON.parse(JSON.stringify(props.qtyGraphData?.data ?? []))
  const qtyByMonth = qtyRows.reduce((m, r) => ((m[r.month_name] ??= []).push(r), m), {})
  const sortedQtyMonths = Object.keys(qtyByMonth).sort((a, b) => new Date(a) - new Date(b))

  sortedQtyMonths.forEach((month, idx) => {
    const monthRows = qtyByMonth[month]
    const machine = Array.from({ length: 31 }, (_, d) =>
      monthRows.find(r => +r.day === d + 1)?.machine_qty ?? null
    ).map(v => v == null ? null : Number(v))
    const wh = Array.from({ length: 31 }, (_, d) =>
      monthRows.find(r => +r.day === d + 1)?.warehouse_qty ?? null
    ).map(v => v == null ? null : Number(v))

    const isCurrent = idx === sortedQtyMonths.length - 1;
    // Machine Qty (Normal Line): Current (Grey), Previous (Green)
    const machineColor = isCurrent ? '#4b5563' : '#15803d';
    // Warehouse Qty (Dotted Line): Current (Red), Previous (Blue)
    const warehouseColor = isCurrent ? '#ef4444' : '#3b82f6';

    qtyDatasets.value.push({
      label: `${month} Qty in Warehouse`,
      data: wh,
      type: 'bar',
      borderColor: hexToRGBA(warehouseColor, isCurrent ? 1 : 0.4),
      backgroundColor: hexToRGBA(warehouseColor, isCurrent ? 1 : 0.4),
      yAxisID: 'y',
      spanGaps: false,
    })

    qtyDatasets.value.push({
      label: `${month} Qty in Machine`,
      data: machine,
      type: 'line',
      borderColor: hexToRGBA(machineColor, isCurrent ? 1 : 0.4),
      backgroundColor: hexToRGBA(machineColor, isCurrent ? 1 : 0.4),
      borderWidth: 3,
      tension: 0.25,
      yAxisID: 'y',
      spanGaps: false,
    })

    // Machine Stock Balance % on secondary axis
    const balancePct = Array.from({ length: 31 }, (_, d) =>
      monthRows.find(r => +r.day === d + 1)?.balance_percent ?? null
    ).map(v => v == null ? null : Number(v))

    const balanceColor = isCurrent ? '#ef4444' : '#3b82f6'   // red (current) / blue (prev)
    qtyDatasets.value.push({
      label: `${month} Machine Stock Balance %`,
      data: balancePct,
      type: 'line',
      yAxisID: 'y2',
      borderColor: hexToRGBA(balanceColor, isCurrent ? 1 : 0.7),
      backgroundColor: hexToRGBA(balanceColor, isCurrent ? 0.15 : 0.1),
      borderWidth: 2,
      borderDash: [6, 4],
      pointRadius: 3,
      pointHoverRadius: 6,
      tension: 0.25,
      spanGaps: false,
    })
  })
  qtyKey.value += 1
}
</script>
