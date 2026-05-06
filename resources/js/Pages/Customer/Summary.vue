<template>
  <Head title="Customers Summary" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Customers
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3">
        <!-- Filters -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
          <SearchInput placeholderStr="ID" v-model="filters.ref_id">Customer ID</SearchInput>
          <SearchInput placeholderStr="ID" v-model="filters.vend_code">Machine ID</SearchInput>
          <SearchInput placeholderStr="Customer" v-model="filters.customer">Customer</SearchInput>

          <div>
            <label class="block text-sm font-medium text-gray-700">Customer Status</label>
            <MultiSelect
              v-model="filters.is_active"
              :options="activeOptions"
              trackBy="id" valueProp="id" label="value"
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

          <div v-if="permissions.includes('admin-access customers') && cmsEndpoint">
            <label class="block text-sm font-medium text-gray-700">Is From CMS</label>
            <MultiSelect
              v-model="filters.is_cms"
              :options="booleanOptions"
              trackBy="id" valueProp="id" label="value"
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

          <!-- NEW: Period Report -->
          <div>
            <label class="block text-sm font-medium text-gray-700">
              Period Report
            </label>
            <MultiSelect
              v-model="filters.period_report"
              :options="periodReportLocalOptions"
              trackBy="id" valueProp="id" label="value"
              placeholder="Select" open-direction="bottom" class="mt-1"
              :canDeselect="false"
            />
            <p class="text-[10px] text-gray-500 mt-1">
              Default: Current month, recomputed daily ~01:00 (yesterday).
            </p>
          </div>
        </div>

        <div class="flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5">
          <div class="mt-3">
            <div class="flex space-x-1">
              <Button
                class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600"
                @click="onSearchFilterUpdated"
              >
                <MagnifyingGlassIcon class="h-4 w-4" aria-hidden="true" />
                <span>Search</span>
              </Button>
              <Button
                class="inline-flex space-x-1 items-center rounded-md border border-sky bg-sky-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-sky-400"
                @click.prevent="onMapAllMarkerClicked"
                v-if="hasAnyAddressWithCoords"
              >
                <MapPinIcon class="h-4 w-4" aria-hidden="true" />
                <span>Show Map Markers</span>
              </Button>
              <Button
                class="inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400"
                @click="resetFilters"
              >
                <BackspaceIcon class="h-4 w-4" aria-hidden="true" />
                <span>Reset</span>
              </Button>
              <Button type="button" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-400 hover:bg-gray-100"
                @click.prevent="onExportExcelClicked()">
                <div class="flex space-x-1">
                  <div>
                    <ArrowDownTrayIcon v-if="!loading" class="h-4 w-4" aria-hidden="true"/>
                    <svg v-if="loading" aria-hidden="true" class="mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-green-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                      <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                    </svg>
                  </div>
                  <span>Export Excel</span>
                </div>
              </Button>
            </div>
            <p class="text-xs text-gray-600 mt-2">
              Period range: <span class="font-medium">{{ rangeStart }}</span> &nbsp;→&nbsp;
              <span class="font-medium">{{ rangeEnd }}</span>
            </p>
          </div>
          <div class="flex flex-col space-y-2">
            <p class="text-sm text-gray-700 leading-5 flex space-x-1">
              <span>Showing</span>
              <span class="font-medium">{{ summaries.meta?.from ?? 0 }}</span>
              <span>to</span>
              <span class="font-medium">{{ summaries.meta?.to ?? 0 }}</span>
              <span>of</span>
              <span class="font-medium">{{ summaries.meta?.total ?? 0 }}</span>
              <span>results</span>
            </p>
            <MultiSelect
              v-model="filters.numberPerPage"
              :options="numberPerPageOptions"
              trackBy="id" valueProp="id" label="value"
              placeholder="Select" open-direction="bottom" class="mt-1"
              @selected="onSearchFilterUpdated"
            />
          </div>
        </div>
      </div>

      <!-- Table -->
      <div class="mt-6 flex flex-col">
        <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll">
            <table class="min-w-full border-separate" style="border-spacing: 0">
              <thead class="bg-gray-100">
                <tr class="divide-x divide-gray-200">
                  <TableHead>#</TableHead>
                  <TableHead>
                    <div class="flex flex-col space-y-1">
                      <span>Customer</span>
                      <span >Ref Price</span>
                    </div>
                  </TableHead>
                  <TableHead>Address</TableHead>
                  <TableHead>
                    <div class="flex flex-col space-y-1">
                      <span>Period Report</span>
                      <span >YYMM</span>
                    </div>
                  </TableHead>
                  <TableHead>
                    <SingleSortItem modelName="machine_id" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('machine_id')">
                      Machine ID
                    </SingleSortItem>
                  </TableHead>
                  <TableHead>
                    <SingleSortItem modelName="machine_prefix" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('machine_prefix')">
                      Machine Prefix
                    </SingleSortItem>
                  </TableHead>
                  <TableHead>Period Start Date</TableHead>
                  <TableHead>Period End Date</TableHead>
                  <TableHead>
                    <SingleSortItem modelName="sales_cents" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('sales_cents')">
                      Sales ($)
                    </SingleSortItem>
                  </TableHead>
                  <TableHead>
                    <div class="flex flex-col space-y-1">
                      <SingleSortItem modelName="gross_earning_cents" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('gross_earning_cents')">
                        Gross Earing
                      </SingleSortItem>
                      <span >(excl GST)</span>
                    </div>
                  </TableHead>
                  <TableHead>
                    <div class="flex flex-col space-y-4">
                      <span>Placement Contract Type</span>
                      <span>Location Fees Rate</span>
                    </div>
                  </TableHead>
                  <TableHead>
                    <SingleSortItem modelName="location_fees_cents" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('location_fees_cents')">
                      Location Fees
                    </SingleSortItem>
                  </TableHead>
                  <TableHead>
                    <div class="flex flex-col space-y-1">
                      <SingleSortItem modelName="location_earning_cents" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('location_earning_cents')">
                        Location Earning
                      </SingleSortItem>
                      <span >Rate</span>
                    </div>
                  </TableHead>
                  <TableHead>Accumulate Gross Margin $</TableHead>
                  <TableHead>
                    <div class="flex flex-col space-y-4">
                      <span>Location Grading</span>
                      <span>Location Type</span>
                    </div>
                  </TableHead>
                  <TableHead>Action</TableHead>
                  <TableHead>Customer Tag</TableHead>
                </tr>
              </thead>
              <tbody class="bg-white">
                <tr
                  v-for="(row, rowIndex) in summaries.data"
                  :key="row.id"
                  class="divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100"
                >
                  <!-- # -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-center">
                    {{ (summaries.meta?.from ?? 1) + rowIndex }}
                  </TableData>

                  <!-- Customer / Ref Price -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-left">
                    <div class="flex flex-col space-y-0.5" v-if="row.customer">
                      <a target="_blank" :href="'/customers/' + row.customer.id + '/edit'"
                        :class="[row.customer.person_id ? 'text-blue-700' : 'text-purple-700']"
                      >
                        <span class="font-medium">{{ refIdFor(row.customer) }}</span>
                        <br />
                        {{ row.customer.name }}
                      </a>
                      <div class="flex space-x-1 mt-0.5">
                        <span
                          v-if="row.customer.selling_price_type"
                          class="inline-flex rounded px-0.5 py-0.5 text-[10px] border w-fit h-fit bg-indigo-100 text-indigo-800 border-indigo-300"
                        >
                          RP{{ row.customer.selling_price_type }}
                        </span>
                      </div>
                    </div>
                  </TableData>

                  <!-- Address -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-left">
                    <span v-if="row.customer?.delivery_address">
                      {{ row.customer.delivery_address.full_address }}
                    </span>
                  </TableData>

                  <!-- Period Report YYMM -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-center">
                    <div class="flex flex-col space-y-1">
                      <span class="font-semibold">{{ periodReportLabel(row) }}</span>
                      <span
                        class="inline-flex justify-center items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-100 text-blue-800 border border-blue-300 w-fit mx-auto"
                      >
                        API Rpt
                      </span>
                    </div>
                  </TableData>

                  <!-- Vend ID -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-center">
                    <a
                      v-if="row.customer?.vend?.id"
                      target="_blank"
                      :href="'/settings/vend/' + row.customer.vend.id + '/update'"
                      class="text-blue-700 hover:underline"
                    >
                      {{ row.customer.vend.code }}
                    </a>
                    <span v-else-if="row.customer?.vend">{{ row.customer.vend.code }}</span>
                    <div v-if="row.vend_count > 1" class="text-[10px] text-gray-500 mt-0.5">
                      +{{ row.vend_count - 1 }} more
                    </div>
                  </TableData>

                  <!-- Prefix -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-center">
                    <span v-if="row.customer?.vend?.prefix">{{ row.customer.vend.prefix }}</span>
                  </TableData>

                  <!-- Period Start Date (YYMMDD) -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-center">
                    {{ formatYYMMDD(row.period_start) }}
                  </TableData>

                  <!-- Period End Date (YYMMDD) -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-center">
                    {{ formatYYMMDD(row.period_end) }}
                  </TableData>

                  <!-- Sales, $ -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-right">
                    {{ formatMoney(row.sales_cents) }}
                  </TableData>

                  <!-- Gross Earing (excl GST) -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-right">
                    {{ formatMoney(row.gross_earning_cents) }}
                  </TableData>

                  <!-- Placement Contract Type / Location Fees Rate -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-center">
                    <div class="flex flex-col items-center space-y-0.5 text-xs" v-if="row.contract_commission_type">
                      <span class="font-semibold">{{ contractTypeLabel(row.contract_commission_type) }}</span>
                      <span v-if="row.contract_commission_value != null">
                        <span v-if="['PS','PS+U','PSORU'].includes(row.contract_commission_type)">
                          {{ Number(row.contract_commission_value) }}%<span
                            v-if="row.contract_commission_value2 != null && ['PS+U','PSORU'].includes(row.contract_commission_type)"
                          >+${{ Number(row.contract_commission_value2) }}</span>
                        </span>
                        <span v-else>
                          ${{ Number(row.contract_commission_value).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2}) }}
                        </span>
                      </span>
                      <span v-if="row.contract_ps_term != null">
                        PS Term: {{ Number(row.contract_ps_term) }}%
                      </span>
                    </div>
                  </TableData>

                  <!-- Location Fees -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-right">
                    <span :class="locationFeesColorClass(row.location_fees_cents, row.contract_commission_type)">
                      {{ formatMoneySigned(row.location_fees_cents) }}
                    </span>
                    <div
                      v-if="row.contract_commission_type === 'S'"
                      class="text-[10px] text-emerald-600 mt-0.5"
                    >
                      Subsidy income
                    </div>
                  </TableData>

                  <!-- Location Earning $ / Rate -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-right">
                    <div class="flex flex-col space-y-0.5">
                      <span :class="row.location_earning_cents >= 0 ? 'text-green-700 font-medium' : 'text-red-700 font-medium'">
                        {{ formatMoney(row.location_earning_cents) }}
                      </span>
                      <span class="text-[11px] text-gray-600">
                        {{ formatPercent(row.location_earning_rate) }}
                      </span>
                    </div>
                  </TableData>

                  <!-- Accumulate Gross margin $ — placeholder until lifetime aggregator is added -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-right">
                  </TableData>

                  <!-- Location Grading + Location Type -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-center">
                    <div class="flex flex-col items-center space-y-4 text-xs">
                      <span v-if="row.customer?.location_type" class="text-gray-700">
                        {{ row.customer.location_type.name }}
                      </span>
                    </div>
                  </TableData>

                  <!-- Action -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-center">
                  </TableData>

                  <!-- Customer Tag -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-left">
                    <span
                      v-for="binding in (row.customer?.tag_bindings ?? [])"
                      :key="binding.id"
                      class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 mr-1"
                    >
                      {{ binding.tag?.name }}
                    </span>
                  </TableData>
                </tr>

                <tr v-if="!summaries.data?.length">
                  <td colspan="17" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium text-center">
                    No Results Found — try a broader Period Report or fewer filters.
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator
              v-if="summaries.data?.length"
              :links="summaries.links"
              :meta="summaries.meta"
            ></Paginator>
          </div>
        </div>
      </div>
    </div>

    <MapMarker
      v-if="showMapMarkerModal"
      :customers="mapCustomers"
      :api-key="mapApiKey"
      :showModal="showMapMarkerModal"
      @modalClose="onMapMarkerModalClose"
    />
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import MapMarker from '@/Components/MapMarker.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import SingleSortItem from '@/Components/SingleSortItem.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { ArrowDownTrayIcon, BackspaceIcon, MagnifyingGlassIcon, MapPinIcon, PencilSquareIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import { computed, ref, onMounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { vTooltip } from 'floating-vue';
import moment from 'moment';

const props = defineProps({
  summaries: Object,
  periodReport: String,
  periodReportOptions: Array,
  rangeStart: String,
  rangeEnd: String,
  cmsEndpoint: String,
  locationTypeOptions: [Array, Object],
  mapApiKey: String,
  operatorOptions: Object,
  tags: Object,
  vendPrefixOptions: Object,
});

const operatorCountry = usePage().props.auth.operatorCountry;
const permissions = usePage().props.auth.permissions;

const loading = ref(false);
const showMapMarkerModal = ref(false);
const mapCustomers = ref([]);

// Show the "Show Map Markers" button only when at least one row's customer
// has a delivery_address with both latitude and longitude.
const hasAnyAddressWithCoords = computed(() => {
  return (props.summaries?.data ?? []).some((row) =>
    row.customer
    && row.customer.delivery_address
    && row.customer.delivery_address.latitude
    && row.customer.delivery_address.longitude
  );
});

const filters = ref({
  customer: '',
  is_active: '',
  is_cms: '',
  ref_id: '',
  vend_code: '',
  location_types: [],
  operators: [],
  vendPrefixes: [],
  tags: [],
  period_report: '',
  sortKey: 'year_month',
  sortBy: false,
  numberPerPage: 100,
});

const activeOptions = ref([]);
const booleanOptions = ref([]);
const locationTypeOptions = ref([]);
const operatorOptions = ref([]);
const tagOptions = ref([]);
const vendPrefixOptions = ref([]);
const numberPerPageOptions = ref([]);
const periodReportLocalOptions = ref([]);

onMounted(() => {
  activeOptions.value = [
    { id: 'all', value: 'All' },
    { id: 'true', value: 'Active' },
    { id: 'false', value: 'Not Active' },
  ];
  booleanOptions.value = [
    { id: 'all', value: 'All' },
    { id: 'true', value: 'Yes' },
    { id: 'false', value: 'No' },
  ];
  numberPerPageOptions.value = [
    { id: 100, value: 100 },
    { id: 200, value: 200 },
    { id: 500, value: 500 },
    { id: 'All', value: 'All' },
  ];
  filters.value.numberPerPage = numberPerPageOptions.value[0];

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

  periodReportLocalOptions.value = (props.periodReportOptions ?? []).map((opt) => ({
    id: opt.id,
    value: opt.value,
  }));

  // Defaults
  filters.value.is_active = booleanOptions.value[1];
  filters.value.is_cms = booleanOptions.value[0];
  filters.value.location_types = [locationTypeOptions.value.find((o) => o.id === 'all')].filter(Boolean);
  filters.value.operators = [operatorOptions.value.find((o) => o.id === 'all')].filter(Boolean);
  filters.value.period_report =
    periodReportLocalOptions.value.find((o) => o.id === (props.periodReport || 'current'))
    ?? periodReportLocalOptions.value[0];
});

function refIdFor(customer) {
  // Customer model adds ref_id mutator (id + 20000) but pivots may not include it.
  return customer.ref_id ?? (customer.id ? customer.id + 20000 : '');
}

function formatYearMonth(d) {
  if (!d) return '';
  return moment(d).format('YYYY-MM');
}

// Match the screenshot's compact YYMM ("2604") format for the Period Report column.
function formatYYMM(d) {
  if (!d) return '';
  return moment(d).format('YYMM');
}

// For "Current" the cell shows YYMM (single month). For aggregated periods
// (Last 12 months / All) the cell shows the YYMM range, e.g. "2505 → 2604".
function periodReportLabel(row) {
  if (!row || !row.period_start || !row.period_end) return '';
  const start = moment(row.period_start).format('YYMM');
  const end = moment(row.period_end).format('YYMM');
  return start === end ? start : (start + ' → ' + end);
}

// Match the screenshot's compact YYMMDD ("260401") format for period start/end.
function formatYYMMDD(d) {
  if (!d) return '';
  return moment(d).format('YYMMDD');
}

function formatDate(d) {
  if (!d) return '';
  return moment(d).format('YYYY-MM-DD');
}

// Maps the contract type code to the human-readable label
// (mirrors Customer/Edit.vue's commissionTypeOptions).
function contractTypeLabel(type) {
  switch (type) {
    case 'F':     return 'Free Placement';
    case 'S':     return 'Subsidized Plan';
    case 'R':     return 'Fix Rental';
    case 'U':     return 'Utility only';
    case 'PS':    return 'PS';
    case 'PS+U':  return 'PS + U';
    case 'PSORU': return 'PS OR U';
    default:      return type ?? '';
  }
}

function formatMoney(cents) {
  if (cents == null) return '';
  const exp = operatorCountry?.currency_exponent ?? 2;
  const sym = operatorCountry?.currency_symbol ?? '$';
  const value = Number(cents) / Math.pow(10, exp);
  const showExp = !operatorCountry?.is_currency_exponent_hidden;
  return sym + value.toLocaleString(undefined, {
    minimumFractionDigits: showExp ? exp : 0,
    maximumFractionDigits: showExp ? exp : 0,
  });
}

function formatMoneySigned(cents) {
  if (cents == null) return '';
  const sign = Number(cents) < 0 ? '-' : '';
  return sign + formatMoney(Math.abs(Number(cents)));
}

function formatPercent(rate) {
  if (rate == null) return '';
  return (Number(rate) * 100).toFixed(1) + '%';
}

function locationFeesColorClass(cents, type) {
  // Negative = subsidy income (green); positive = expense (gray neutral).
  if (Number(cents) < 0 || type === 'S') return 'text-emerald-700 font-medium';
  return 'text-gray-800';
}

function onSearchFilterUpdated() {
  router.get(
    '/customers/summary',
    {
      ref_id: filters.value.ref_id,
      vend_code: filters.value.vend_code,
      customer: filters.value.customer,
      tags: (filters.value.tags ?? []).map ? filters.value.tags.map(t => t.id ?? t) : filters.value.tags,
      is_cms: filters.value.is_cms?.id,
      is_active: filters.value.is_active?.id,
      location_types: (filters.value.location_types ?? []).map((lt) => lt.id),
      operators: (filters.value.operators ?? []).filter(Boolean).map((o) => o.id),
      vendPrefixes: (filters.value.vendPrefixes ?? []).map((vp) => vp.id),
      period_report: filters.value.period_report?.id || 'current',
      sortKey: filters.value.sortKey,
      sortBy: filters.value.sortBy,
      numberPerPage: filters.value.numberPerPage?.id ?? filters.value.numberPerPage,
    },
    { preserveState: true, replace: true }
  );
}

function resetFilters() {
  router.get('/customers/summary');
}

function sortTable(sortKey) {
  filters.value.sortKey = sortKey;
  filters.value.sortBy = !filters.value.sortBy;
  onSearchFilterUpdated();
}

// Build the param payload for backend calls (search + export). Centralised
// so the export uses the same filter set the page is currently showing.
function buildBackendParams() {
  return {
    ref_id: filters.value.ref_id,
    vend_code: filters.value.vend_code,
    customer: filters.value.customer,
    tags: (filters.value.tags ?? []).map ? filters.value.tags.map(t => t.id ?? t) : filters.value.tags,
    is_cms: filters.value.is_cms?.id,
    is_active: filters.value.is_active?.id,
    location_types: (filters.value.location_types ?? []).map((lt) => lt.id),
    operators: (filters.value.operators ?? []).filter(Boolean).map((o) => o.id),
    vendPrefixes: (filters.value.vendPrefixes ?? []).map((vp) => vp.id),
    period_report: filters.value.period_report?.id || 'current',
    sortKey: filters.value.sortKey,
    sortBy: filters.value.sortBy,
    numberPerPage: filters.value.numberPerPage?.id ?? filters.value.numberPerPage,
  };
}

function onMapAllMarkerClicked() {
  // MapMarker.vue expects camelCase customer.deliveryAddress with latitude/longitude/full_address.
  // Our resource emits snake_case delivery_address, so adapt before opening.
  mapCustomers.value = (props.summaries?.data ?? [])
    .filter((row) => row.customer && row.customer.delivery_address && row.customer.delivery_address.latitude && row.customer.delivery_address.longitude)
    .map((row, index) => ({
      sequence: index + 1,
      ...row.customer,
      deliveryAddress: row.customer.delivery_address,
    }));
  showMapMarkerModal.value = true;
}

// Open MapMarker for a single customer (per-row pin button next to the address).
function onSingleMapMarkerClicked(customer) {
  if (!customer || !customer.delivery_address) return;
  mapCustomers.value = [{
    sequence: 1,
    ...customer,
    deliveryAddress: customer.delivery_address,
  }];
  showMapMarkerModal.value = true;
}

function onMapMarkerModalClose() {
  showMapMarkerModal.value = false;
}

function onExportExcelClicked() {
  loading.value = true;
  axios({
    method: 'get',
    url: '/customers/summary/excel',
    params: buildBackendParams(),
    responseType: 'blob',
  }).then(response => {
    fileDownload(response.data, 'CustomersSummary' + moment().format('YYMMDDHHmmss') + '.xlsx');
  }).catch(error => {
    console.log(error);
  }).finally(() => {
    loading.value = false;
  });
}
</script>
