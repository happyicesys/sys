<template>
  <Head title="Site Performance" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex flex-col">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Site Performance
        </h2>
        <p class="text-sm text-black leading-tight mt-1">
          Aggregate across the filtered sites — “Avg L30d” (rolling last 30 days)
          plus the last 8 completed months.
        </p>
      </div>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3">
        <!-- Filters -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
          <SearchInput placeholderStr="ID" v-model="filters.ref_id">Site ID</SearchInput>
          <SearchInput placeholderStr="ID" v-model="filters.vend_code">Machine ID</SearchInput>
          <SearchInput placeholderStr="Site" v-model="filters.customer">Site</SearchInput>

          <div>
            <label class="block text-sm font-medium text-gray-700">Site Status</label>
            <MultiSelect
              v-model="filters.status"
              :options="statusOptions"
              trackBy="id" valueProp="id" label="value" mode="tags"
              placeholder="Select" open-direction="bottom" class="mt-1"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Tags</label>
            <MultiSelect
              v-model="filters.tags"
              :options="tagOptions"
              trackBy="id" valueProp="id" label="name" mode="tags"
              placeholder="Select" open-direction="bottom" class="mt-1"
            />
          </div>

          <div v-if="permissions.includes('admin-access customers')">
            <label class="block text-sm font-medium text-gray-700">Operator</label>
            <MultiSelect
              v-model="filters.operators"
              :options="operatorOptions"
              trackBy="id" valueProp="id" label="full_name" mode="tags"
              placeholder="Select" open-direction="bottom" class="mt-1"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Machine Prefix</label>
            <MultiSelect
              v-model="filters.vendPrefixes"
              :options="vendPrefixOptions"
              trackBy="id" valueProp="id" label="value" mode="tags"
              placeholder="Select" open-direction="bottom" class="mt-1"
            />
          </div>

          <div v-if="permissions.includes('admin-access customers')">
            <label class="block text-sm font-medium text-gray-700">Location Type</label>
            <MultiSelect
              v-model="filters.location_types"
              :options="locationTypeOptions"
              trackBy="id" valueProp="id" label="value" mode="tags"
              placeholder="Select" open-direction="bottom" class="mt-1"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Placement Contract Type</label>
            <MultiSelect
              v-model="filters.contract_commission_types"
              :options="contractCommissionTypeLocalOptions"
              trackBy="id" valueProp="id" label="value" mode="tags"
              placeholder="Select" open-direction="bottom" class="mt-1"
            />
          </div>
        </div>

        <div class="flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5">
          <div class="flex flex-col space-y-1 md:flex-row md:space-y-0 md:space-x-1">
            <Button
              class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600"
              @click="onSearchFilterUpdated"
            >
              <MagnifyingGlassIcon class="h-4 w-4" aria-hidden="true" />
              <span>Search</span>
            </Button>
            <Button
              class="inline-flex space-x-1 items-center rounded-md border border-gray bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400"
              @click.prevent="resetFilters()"
            >
              <BackspaceIcon class="h-4 w-4" aria-hidden="true" />
              <span>Reset</span>
            </Button>
            <Button
              type="button"
              class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-400 hover:bg-gray-100"
              @click.prevent="onExportExcelClicked()"
            >
              <div class="flex space-x-1 items-center">
                <ArrowDownTrayIcon v-if="!loading" class="h-4 w-4" aria-hidden="true" />
                <svg v-if="loading" aria-hidden="true" class="w-4 h-4 text-gray-200 animate-spin fill-green-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                  <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                </svg>
                <span>Export Excel</span>
              </div>
            </Button>
          </div>
        </div>
      </div>

      <!-- Matrix -->
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border border-gray-300 my-3 overflow-auto max-h-[80vh]">
        <table class="min-w-full border-separate text-sm [&_th]:border-gray-300 [&_td]:border-gray-300" style="border-spacing: 0">
          <thead>
            <tr class="bg-gray-50">
              <th class="sticky left-0 top-0 z-30 bg-gray-50 border-b border-r px-3 py-2 text-left font-semibold text-gray-700 min-w-[18rem]">
                Metric
              </th>
              <th
                v-for="col in columns"
                :key="col.key"
                class="sticky top-0 z-20 border-b border-l px-3 py-2 text-right font-semibold text-gray-700 whitespace-nowrap"
                :class="col.key === 'l30d' ? 'bg-sky-50' : 'bg-gray-50'"
              >
                <div>{{ col.label }}</div>
                <div class="text-[10px] font-normal text-gray-400">{{ col.sub }}</div>
              </th>
            </tr>
          </thead>
          <tbody>
            <template v-for="section in sections" :key="section.title">
              <tr class="bg-indigo-50">
                <td
                  class="sticky left-0 z-10 bg-indigo-50 border-b border-r px-3 py-1.5 font-semibold text-indigo-800"
                  :colspan="columns.length + 1"
                >
                  {{ section.title }}
                </td>
              </tr>
              <tr v-for="row in section.rows" :key="section.title + row.label" class="hover:bg-gray-50">
                <td class="sticky left-0 z-10 bg-white border-b border-r px-3 py-1.5 text-gray-700">
                  {{ row.label }}
                </td>
                <td
                  v-for="(col, ci) in columns"
                  :key="col.key"
                  class="border-b border-l px-3 py-1.5 text-right tabular-nums whitespace-nowrap text-gray-800"
                  :class="trendClass(row.key, ci, row.dir) || (col.key === 'l30d' ? 'bg-sky-50/60' : '')"
                >
                  {{ cell(row.key, col.key, row.format, row.denom) }}
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <p class="text-[11px] text-gray-500 px-1 mb-6">
        Money/lock/paid/contract-type figures per month are reconstructed from the
        stored monthly snapshots. Profile status and contract end-dates are not
        historized, so those reflect each site’s current setting evaluated against
        that month.
      </p>
    </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import SearchInput from '@/Components/SearchInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ArrowDownTrayIcon, BackspaceIcon, MagnifyingGlassIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted } from 'vue';
import fileDownload from 'js-file-download';
import moment from 'moment';

const props = defineProps({
  columns: { type: Array, default: () => [] },
  metrics: { type: Object, default: () => ({}) },
  statuses: { type: Array, default: () => [] },
  cmsEndpoint: { type: String, default: null },
  locationTypeOptions: { type: Object, default: () => ({}) },
  operatorOptions: { type: Object, default: () => ({}) },
  tags: { type: Object, default: () => ({}) },
  vendPrefixOptions: { type: Object, default: () => ({}) },
  contractCommissionTypeOptions: { type: Array, default: () => [] },
});

const authOperator = usePage().props.auth.operator;
const operatorCountry = usePage().props.auth.operatorCountry;
const permissions = usePage().props.auth.permissions;

const loading = ref(false);

// ── Row layout (mirrors CustomerController::performanceRowLayout) ───────────
const feeBands = [
  ['neg', 'Negative (Subsidized)'],
  ['b0_50', '$0 to $50'],
  ['b51_100', '$51 to $100'],
  ['b101_150', '$101 to $150'],
  ['b151_200', '$151 to $200'],
  ['b201_300', '$201 to $300'],
  ['b301_500', '$301 to $500'],
  ['b500p', 'Above $500'],
];
const accBands = [
  ['neg', 'Negative'],
  ['a0_500', '$0 to $500'],
  ['a500_1000', '$500 to $1000'],
  ['a1000_5000', '$1000 to $5000'],
  ['a5000_10000', '$5000 to $10000'],
  ['a10k_20k', '$10k to $20k'],
  ['a20k_30k', '$20k to $30k'],
  ['a30kp', '$30k above'],
];

const sections = [
  {
    title: 'Performance',
    rows: [
      { label: 'Total Customer (Active Status)', key: 'total_customers', format: 'int', dir: 'up' },
      { label: 'Total Sales (Inc GST), $', key: 'sales_cents', format: 'money', dir: 'up' },
      { label: 'Total Sales (excl GST), $', key: 'sales_excl_cents', format: 'money', dir: 'up' },
      { label: 'Total Gross Earning, $', key: 'gross_cents', format: 'money_pct', dir: 'up' },
      { label: 'Total Location Fees, $', key: 'location_fees_cents', format: 'money_pct', dir: 'down' },
      { label: 'Total VendEarning, $', key: 'vend_earning_cents', format: 'money_pct', dir: 'up' },
    ],
  },
  {
    title: 'Admin and Finance — Customer Profile Status',
    rows: [
      { label: 'Total', key: 'profile_total', format: 'int' },
      { label: 'Potential', key: 'profile_potential', format: 'count_pct', denom: 'profile_total' },
      { label: 'New', key: 'profile_new', format: 'count_pct', denom: 'profile_total' },
      { label: 'Active', key: 'profile_active', format: 'count_pct', denom: 'profile_total', dir: 'up' },
      { label: 'Pending', key: 'profile_pending', format: 'count_pct', denom: 'profile_total' },
      { label: 'Inactive', key: 'profile_inactive', format: 'count_pct', denom: 'profile_total', dir: 'down' },
    ],
  },
  {
    title: 'Admin and Finance — Month-End Lock',
    rows: [
      { label: 'Done', key: 'lock_done', format: 'count_pct', denom: 'lock_total', dir: 'up' },
      { label: 'Still open', key: 'lock_open', format: 'count_pct', denom: 'lock_total', dir: 'down' },
    ],
  },
  {
    title: 'Admin and Finance — Location Fees payment',
    rows: [
      { label: 'Paid, qty', key: 'paid_qty', format: 'count_pct', denom: 'paid_total', dir: 'up' },
      { label: 'Paid, $', key: 'paid_amt_cents', format: 'money', dir: 'up' },
      { label: 'Unpaid, qty', key: 'unpaid_qty', format: 'count_pct', denom: 'paid_total', dir: 'down' },
      { label: 'Unpaid, $', key: 'unpaid_amt_cents', format: 'money', dir: 'down' },
    ],
  },
  {
    title: 'Placement Contract Type',
    rows: [
      { label: 'F: Free Placement', key: 'ct_F', format: 'int' },
      { label: 'S: Subsidized Plan', key: 'ct_S', format: 'int' },
      { label: 'R: Fix Rental', key: 'ct_R', format: 'int' },
      { label: 'U: Utility only', key: 'ct_U', format: 'int' },
      { label: 'R + U', key: 'ct_RU', format: 'int' },
      { label: 'PS: Profit Sharing', key: 'ct_PS', format: 'int' },
      { label: 'PS + U', key: 'ct_PSU', format: 'int' },
      { label: 'PS or U', key: 'ct_PSORU', format: 'int' },
      { label: 'External Subsidize?', key: 'ct_ext_sub', format: 'int' },
    ],
  },
  {
    title: 'Contract availability',
    rows: [
      { label: 'Contract available? — Yes', key: 'contract_avail_yes', format: 'count_pct', denom: 'contract_avail_total', dir: 'up' },
      { label: 'Contract available? — No', key: 'contract_avail_no', format: 'count_pct', denom: 'contract_avail_total', dir: 'down' },
      { label: 'Contract End (no Auto Renewal) — Next 15d', key: 'contract_end_15', format: 'int' },
      { label: 'Contract End (no Auto Renewal) — Next 30d', key: 'contract_end_30', format: 'int' },
      { label: 'Contract End (no Auto Renewal) — Next 60d', key: 'contract_end_60', format: 'int' },
    ],
  },
  {
    title: 'Net Location Fees (per machine)',
    rows: [
      { label: 'Avg per machine, $', key: 'nlf_avg_per_machine_cents', format: 'money', dir: 'down' },
      ...feeBands.map(([k, label]) => ({ label, key: 'nlf_' + k, format: 'int' })),
    ],
  },
  {
    title: 'Vend Earning (per machine)',
    rows: [
      { label: 'Avg per machine, $', key: 've_avg_per_machine_cents', format: 'money', dir: 'up' },
      ...feeBands.map(([k, label]) => ({ label, key: 've_' + k, format: 'int' })),
    ],
  },
  {
    title: 'Accumulated Vend Earning (per machine)',
    rows: [
      { label: 'Avg per machine, $', key: 'ave_avg_per_machine_cents', format: 'money', dir: 'up' },
      ...accBands.map(([k, label]) => ({ label, key: 'ave_' + k, format: 'int' })),
    ],
  },
];

// ── Cell formatting ─────────────────────────────────────────────────────────
function metricVal(key, colKey) {
  const v = props.metrics?.[key]?.[colKey];
  return v == null ? null : Number(v);
}

function denomVal(denomKey, colKey) {
  if (denomKey === 'lock_total') return (metricVal('lock_done', colKey) ?? 0) + (metricVal('lock_open', colKey) ?? 0);
  if (denomKey === 'paid_total') return (metricVal('paid_qty', colKey) ?? 0) + (metricVal('unpaid_qty', colKey) ?? 0);
  if (denomKey === 'contract_avail_total') return (metricVal('contract_avail_yes', colKey) ?? 0) + (metricVal('contract_avail_no', colKey) ?? 0);
  return metricVal(denomKey, colKey) ?? 0;
}

// Numbers only — the currency is already stated in each row title (", $"),
// so we deliberately omit the currency symbol here.
function formatMoney(cents) {
  if (cents == null) return '';
  const exp = operatorCountry?.currency_exponent ?? 2;
  const value = Number(cents) / Math.pow(10, exp);
  const sign = value < 0 ? '-' : '';
  // Table shows whole numbers only — no decimals.
  return sign + Math.abs(value).toLocaleString(undefined, {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  });
}

// Month-over-month coloring. Columns run newest → oldest, so the "previous
// month" for column ci is the column to its right (ci + 1). Green when the
// month is BETTER than the previous one, red when worse — direction-aware via
// the row's `dir` ('up' = higher is better, 'down' = lower is better, e.g.
// costs / unpaid). Rows without a `dir` (distribution bands, contract-type
// counts) are left uncolored since better/worse isn't meaningful there.
function trendClass(key, ci, dir) {
  if (!dir) return '';
  const prevCol = props.columns[ci + 1];
  if (!prevCol) return '';
  const cur = metricVal(key, props.columns[ci].key);
  const prev = metricVal(key, prevCol.key);
  if (cur == null || prev == null || cur === prev) return '';
  const better = dir === 'up' ? cur > prev : cur < prev;
  return better ? 'bg-green-200' : 'bg-red-200';
}

function cell(key, colKey, format, denom) {
  const v = metricVal(key, colKey);
  if (v == null) return '';
  if (format === 'money') return formatMoney(v);
  if (format === 'money_pct') {
    const s = metricVal('sales_cents', colKey) ?? 0;
    const pct = s ? ((v / s) * 100).toFixed(1) : null;
    return formatMoney(v) + (pct != null ? ` (${pct}%)` : '');
  }
  if (format === 'count_pct') {
    const d = denomVal(denom, colKey);
    const pct = d ? ((v / d) * 100).toFixed(0) : null;
    return v + (pct != null ? ` (${pct}%)` : '');
  }
  return String(v);
}

// ── Filters ─────────────────────────────────────────────────────────────────
const filters = ref({
  ref_id: '',
  vend_code: '',
  customer: '',
  status: [],
  tags: [],
  operators: [],
  vendPrefixes: [],
  location_types: [],
  contract_commission_types: [],
});

const statusOptions = ref([]);
const locationTypeOptions = ref([]);
const operatorOptions = ref([]);
const tagOptions = ref([]);
const vendPrefixOptions = ref([]);
const contractCommissionTypeLocalOptions = ref([]);

onMounted(() => {
  statusOptions.value = (props.statuses ?? []).map((s) => ({ id: s.id, value: s.name }));
  tagOptions.value = (props.tags?.data ?? []).map((d) => ({ id: d.id, name: d.name }));
  locationTypeOptions.value = [
    { id: 'all', value: 'All' },
    ...(props.locationTypeOptions?.data ?? []).map((d) => ({ id: d.id, value: d.name })),
  ];
  operatorOptions.value = [
    { id: 'all', full_name: 'All' },
    ...(props.operatorOptions?.data ?? []).map((d) => ({ id: d.id, code: d.code, full_name: d.full_name })),
  ];
  vendPrefixOptions.value = [
    { id: 'single-ud', value: 'Single UD' },
    ...(props.vendPrefixOptions?.data ?? []).map((d) => ({ id: d.id, value: d.name })),
  ];
  contractCommissionTypeLocalOptions.value = (props.contractCommissionTypeOptions ?? []).map((o) => ({
    id: o.id, value: o.value,
  }));

  // Defaults — mirror Summary & Comm / Sites: multi-select Site Status opens on
  // Active (2) + Removed (3); clearing the selection sends ['all'].
  filters.value.status = statusOptions.value.filter((s) => s.id === 2 || s.id === 3);
  filters.value.location_types = [locationTypeOptions.value.find((o) => o.id === 'all')].filter(Boolean);
  filters.value.operators = authOperator ? [
    operatorOptions.value.find((o) => o.id === authOperator.id),
    ...(authOperator.code === 'HIPL' ? [
      operatorOptions.value.find((o) => o.code === 'HIMD'),
      operatorOptions.value.find((o) => o.code === 'LEA'),
      operatorOptions.value.find((o) => o.code === 'HIESG'),
      operatorOptions.value.find((o) => o.code === 'UL-ST'),
    ] : []),
  ].filter((o) => o !== undefined) : [operatorOptions.value[0]];
});

function buildBackendParams() {
  return {
    ref_id: filters.value.ref_id,
    vend_code: filters.value.vend_code,
    customer: filters.value.customer,
    status: (filters.value.status?.length ? filters.value.status.map((s) => s.id) : ['all']),
    tags: (filters.value.tags ?? []).map((t) => (t && t.id !== undefined ? t.id : t)),
    operators: (filters.value.operators ?? []).filter(Boolean).map((o) => o.id),
    vendPrefixes: (filters.value.vendPrefixes ?? []).map((vp) => vp.id),
    location_types: (filters.value.location_types ?? []).map((lt) => lt.id),
    contract_commission_types: (filters.value.contract_commission_types ?? [])
      .map((t) => (t && t.id !== undefined ? t.id : t)),
    searched: 1,
  };
}

function onSearchFilterUpdated() {
  router.get('/customers/performance', buildBackendParams(), { preserveState: true, replace: true });
}

function resetFilters() {
  router.get('/customers/performance');
}

function onExportExcelClicked() {
  loading.value = true;
  window.axios({
    method: 'get',
    url: '/customers/performance/excel',
    params: buildBackendParams(),
    responseType: 'blob',
  }).then((response) => {
    fileDownload(response.data, 'SitePerformance' + moment().format('YYMMDDHHmmss') + '.xlsx');
  }).catch((error) => {
    console.log(error);
  }).finally(() => {
    loading.value = false;
  });
}
</script>
