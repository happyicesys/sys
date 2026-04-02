<template>
  <Head title="Warehouse Qty (via API) & Planning" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Warehouse Qty (via API) & Planning
      </h2>
    </template>

    <div class="m-2 sm:mx-2 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
        <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5">
          <div class="inline-block min-w-full py-2 align-middle md:px-4 lg:px-6">
            <div class="py-4">
              <div class="grid grid-cols-1 md:grid-cols-4 gap-2">
                  <SearchInput placeholderStr="Product ID/ Code" v-model="filters.product_code">
                    Product ID
                  </SearchInput>
                  <SearchInput placeholderStr="Product Name" v-model="filters.product_name">
                    Product Name
                  </SearchInput>
                  <div>
                    <label for="text" class="block text-sm font-medium text-gray-700">
                      Operator
                    </label>
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
                    >
                    </MultiSelect>
                  </div>
                  <div>
                    <label for="text" class="block text-sm font-medium text-gray-700">
                      Is Available?
                    </label>
                    <MultiSelect
                      v-model="filters.is_available"
                      :options="booleanOptions"
                      trackBy="id"
                      valueProp="id"
                      label="value"
                      placeholder="Select"
                      open-direction="bottom"
                      class="mt-1"
                    >
                    </MultiSelect>
                  </div>
              </div>

              <div class="flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-2">
                  <div class="flex space-x-1">
            <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            @click.prevent="onSearchFilterUpdated()"
            >
                <MagnifyingGlassIcon class="h-4 w-4" aria-hidden="true"/>
                <span>
                    Search
                </span>
            </Button>
                      <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                      @click="resetFilters()"
                      >
                          <BackspaceIcon class="h-4 w-4" aria-hidden="true"/>
                          <span>
                              Reset
                          </span>
                      </Button>
                      <Button class="inline-flex space-x-1 items-center rounded-md border border-gray-800 bg-white px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        @click="onExcelExportClicked()"
                      >
                        <ArrowDownTrayIcon class="h-4 w-4" aria-hidden="true"/>
                        <span>Export Excel</span>
                      </Button>
                  </div>
                  <div class="flex flex-col gap-2 items-end">
                      <span class="text-xs text-gray-500 self-center">
                          {{ products.data.length }} products found
                      </span>
                      <div class="flex flex-row items-center gap-2">
                        <label class="text-xs font-semibold text-gray-700">Planning Date</label>
                        <DatePicker v-model="filters.productAvailableDate" class="py-1 text-xs" :isPreviousNextButton="false" :clearable="false" :format="'yyyy-MM-dd'" auto-apply @update:model-value="onSearchFilterUpdated" :minDate="today">
                            <template #trigger>
                                <span class="p-1 border border-gray-300 rounded-md cursor-pointer hover:bg-gray-100 flex flex-row gap-2 justify-center items-center h-full text-xs">
                                    <CalendarIcon class="w-3 h-3" />
                                    {{ moment(filters.productAvailableDate).format('YYYY-MM-DD') }}
                                </span>
                            </template>
                        </DatePicker>
                      </div>
                  </div>
              </div>
              </div>
            <div class="overflow-scroll max-h-[900px] md:max-h-[1500px] shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
              <table class="min-w-full divide-y divide-gray-300">
                <thead class="bg-gray-100 sticky top-0 z-10">
                  <tr>
                    <th colspan="7" class="bg-gray-100"></th>
                    <th colspan="2" class="p-2 text-center text-sm font-bold text-gray-900 border-b border-gray-300 bg-gray-200">
                      Planning
                    </th>
                  </tr>
                  <tr>
                    <th scope="col" class="th-header w-[2%] p-1 sm:p-3 text-[10px] sm:text-xs font-semibold text-center text-gray-900 border-b">
                      #
                    </th>
                    <th scope="col" class="th-header w-[5%] p-1 sm:p-3 text-[10px] sm:text-xs font-semibold text-center text-gray-900 border-b">
                      Image
                    </th>
                    <th scope="col" class="th-header w-[20%] p-1 sm:p-3 text-[10px] sm:text-xs font-semibold text-center text-blue-600 border-b cursor-pointer hover:bg-gray-200" @click="sortTable('code')">
                      <div class="flex items-center justify-center gap-1">
                        Product
                        <span v-if="filters.sortKey === 'code'">
                          <svg v-if="filters.sortBy" xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                             <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                          </svg>
                          <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                             <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                          </svg>
                        </span>
                      </div>
                    </th>
                    <th scope="col" class="th-header w-[8%] p-1 sm:p-3 text-[10px] sm:text-xs font-semibold text-center text-blue-600 border-b border-r border-gray-300 cursor-pointer hover:bg-gray-200" @click="sortTable('avg_seven_days_count')">
                      <div class="flex items-center justify-center gap-1">
                          <span>Daily Sold Qty</span>
                          <span v-if="filters.sortKey === 'avg_seven_days_count'">
                              <svg v-if="filters.sortBy" xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                              </svg>
                              <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                  <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                              </svg>
                          </span>
                      </div>
                      <span class="font-normal text-gray-600">(average last 7days)</span>
                    </th>

                    <th scope="col" class="th-header w-[10%] p-1 sm:p-3 text-[10px] sm:text-xs font-semibold text-center text-gray-900 border-b">
                      Qty in Warehouse <br>
                      <span class="font-normal text-gray-600">(from API)</span>
                    </th>
                    <th scope="col" class="th-header w-[10%] p-1 sm:p-3 text-[10px] sm:text-xs font-semibold text-center text-gray-900 border-b">
                      Picked Qty <br>
                      <span class="font-normal text-gray-600">(not yet sync API)</span>
                    </th>
                    <th scope="col" class="th-header w-[10%] p-1 sm:p-3 text-[10px] sm:text-xs font-semibold text-center text-gray-900 border-b border-r border-gray-300">
                      <div class="flex items-center justify-center gap-1">
                          <span>Remaining Qty</span>
                          <ExclamationCircleIcon class="min-w-4 w-4 h-4 text-sky-500 cursor-help" v-tooltip="{ content: 'Red = Remaining Qty not enough to cover \'To Pick Qty\' as in Planning Date', html: true }"></ExclamationCircleIcon>
                      </div>
                      <span class="font-normal text-[10px] text-gray-600">(Qty in Warehouse, minus Picked Qty)</span>
                    </th>
                    <th scope="col" class="th-header w-[15%] p-1 sm:p-3 text-[10px] sm:text-xs font-semibold text-center text-gray-900 border-b">
                      To Pick Qty <br>
                      <!-- Live Update note similar to ProductMovement if desired, or just keep header simple -->
                      <span class="font-normal text-xs text-gray-600">(Live Update)</span>
                    </th>
                    <th scope="col" class="th-header w-[10%] p-1 sm:p-3 text-[10px] sm:text-xs font-semibold text-center text-gray-900 border-b">
                      Capped Qty per Channel <br>
                      <span class="font-normal text-xs text-gray-600">(max Qty after Refilling on Planning Date & 4 Days Onwards)</span>
                    </th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 bg-white">
                  <tr v-for="(product, productIndex) in products.data" :key="product.id" class="hover:bg-gray-50">
                    <td class="p-1 sm:p-3 text-xs sm:text-sm text-center text-gray-900">
                      {{ productIndex + 1 }}
                    </td>
                    <td class="whitespace-nowrap text-sm font-semibold text-gray-900 text-center">
                      <div class="flex justify-center items-center">
                        <img class="h-8 w-8 sm:h-16 sm:w-16 rounded-full" :class="[product.is_available ? '' : 'opacity-50']" :src="product.thumbnail.full_url" alt="" v-if="product.thumbnail" />
                      </div>
                    </td>
                    <td class="p-1 sm:p-3 text-xs sm:text-sm text-gray-900" :class="[product.is_available ? 'text-gray-800' : 'text-gray-400']">
                      <div class="flex flex-col text-left">
                        <span class="font-bold" v-if="product.code">{{ product.code }}</span>
                        <span class="text-xs mb-1" v-if="product.name">{{ product.name }}</span>
                        <div class="flex items-center gap-1">
                          <span class="text-green-700 font-bold text-xs">Available?</span>
                          <span v-if="product.is_available">
                            <CheckCircleIcon class="h-5 w-5 text-green-500 hover:cursor-pointer hover:text-green-600" @click.prevent="onIsAvailableClicked(product)" />
                          </span>
                          <span v-else>
                            <XCircleIcon class="h-5 w-5 text-red-500 hover:cursor-pointer hover:text-red-600" @click.prevent="onIsAvailableClicked(product)" />
                          </span>
                        </div>
                        <span class="text-[10px] text-gray-500 mt-1" v-if="product.isAvailableUpdatedBy">
                          {{ product.isAvailableUpdatedBy.name }} ({{ product.is_available_updated_at }})
                        </span>
                      </div>
                    </td>
                    <td class="p-1 sm:p-3 text-center text-xs sm:text-sm font-medium border-r border-gray-300" :class="[product.is_available ? 'text-gray-600' : 'text-gray-400']">
                      {{ Number(product.avg_seven_days_count)?.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0}) }}
                    </td>

                    <!-- Qty in Warehouse (Blue) -->
                    <td class="p-1 sm:p-3 text-center text-sm sm:text-lg font-bold text-blue-600">
                      {{ Number(product.qty_available_pcs_api)?.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0}) }}
                    </td>
                    <!-- Picked Qty (Gray) -->
                    <td class="p-1 sm:p-3 text-center text-sm sm:text-lg font-bold text-gray-800">
                      {{ Number(product.not_yet_sync_api_qty)?.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0}) }}
                    </td>
                    <!-- Remaining/Net (Border-r) -->
                    <td class="p-1 sm:p-3 text-center text-sm sm:text-lg font-bold text-gray-900 border-r border-gray-300">
                      <span :class="product.is_available ? (product.net_available_qty_pcs_api < product.needed_qty ? 'text-red-800 bg-red-200 rounded px-1 py-1' : '') : 'text-gray-400'">
                        {{ Number(product.net_available_qty_pcs_api)?.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0}) }}
                      </span>
                    </td>
                    <!-- Needed Qty (Orange) -->
                    <td class="p-1 sm:p-3 text-center text-sm sm:text-lg font-bold text-orange-600">
                      {{ Number(product.needed_qty)?.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0}) }}
                    </td>
                    <!-- Capped Qty / Limit (Select Input) -->
                    <td class="p-1 sm:p-3 text-center">
                      <div class="flex flex-col items-center gap-1">
                        <select name="max_ops_job_pick_limit" id="max_ops_job_pick_limit" class="rounded text-xs py-1" :class="[product.max_ops_job_pick_limit >= 0 && product.max_ops_job_pick_limit != null ? 'text-red-600' : 'text-gray-800']" v-model="product.max_ops_job_pick_limit" :disabled="!product.is_available || !permissions.includes('admin-access product-availability')" @change="onMaxOpsJobPickLimitSelected(product.id, product.max_ops_job_pick_limit)">
                          <option :value="null">No</option>
                          <option v-for="n in 25 + 1" :key="n-1" :value="n-1">{{ n-1 }}</option>
                        </select>
                        <span class="text-[10px] text-red-700" v-if="product.max_ops_job_pick_limit != null && product.limit_is_created_by_system">
                          from Yesterday
                        </span>
                        <span class="text-[10px] text-gray-800" v-if="product.max_ops_job_pick_limit != null && product.productLimits[0] && product.productLimits[0].createdBy">
                          {{ product.productLimits[0].createdBy.name }}
                        </span>
                        <span class="text-[10px] text-gray-800" v-if="product.max_ops_job_pick_limit != null && product.productLimits[0] && product.productLimits[0].setupDate">
                          {{ product.productLimits[0].setupDate }}
                        </span>
                      </div>
                    </td>
                  </tr>
                </tbody>
                <tfoot>
                  <tr class="bg-gray-50 font-bold">
                    <td colspan="3" class="p-1 sm:p-3 text-left text-gray-900">
                      <div class="flex flex-col space-y-1 items-start">
                        <span>Total Pcs</span>
                        <span>Total Cost$</span>
                        <span class="text-gray-900">Stock Value</span>
                      </div>
                    </td>
                    <td class="p-1 sm:p-3 text-center text-gray-900 border-r border-gray-300">
                        <div class="flex flex-col space-y-1">
                            <span>{{ getDailySoldQtyTotal().toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}</span>
                            <span>{{ operatorCountry.currency_symbol }}{{ getDailySoldQtyTotalCost().toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
                            <span>&nbsp;</span>
                        </div>
                    </td>
                    <td class="p-1 sm:p-3 text-center text-blue-600">
                      <div class="flex flex-col space-y-1">
                        <span>{{ getProductAvailablePcsApiTotal().toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}</span>
                        <span>{{ operatorCountry.currency_symbol }}{{ getProductAvailablePcsApiTotalCost().toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
                        <span>&nbsp;</span>
                      </div>
                    </td>
                    <td class="p-1 sm:p-3 text-center text-gray-800">
                      <div class="flex flex-col space-y-1">
                        <span>{{ getProductNotYetSyncApiQtyTotal().toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}</span>
                        <span>{{ operatorCountry.currency_symbol }}{{ getProductNotYetSyncApiQtyTotalCost().toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
                        <span class="text-gray-900 font-bold">{{ operatorCountry.currency_symbol }}{{ getProductPickedValueTotal().toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
                      </div>
                    </td>
                    <td class="p-1 sm:p-3 text-center text-gray-900 border-r border-gray-300">
                      <div class="flex flex-col space-y-1">
                        <span>{{ getProductNetAvailableQtyPcsApiTotal().toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}</span>
                        <span>{{ operatorCountry.currency_symbol }}{{ getProductNetAvailableQtyPcsApiTotalCost().toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
                        <span>&nbsp;</span>
                      </div>
                    </td>
                    <td class="p-1 sm:p-3 text-center text-orange-600">
                       <div class="flex flex-col space-y-1">
                        <span>{{ getProductNeededQtyTotal().toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}</span>
                        <span>{{ operatorCountry.currency_symbol }}{{ getProductNeededQtyTotalCost().toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
                        <span class="text-orange-600 font-bold capitalize">{{ operatorCountry.currency_symbol }}{{ getProductNeededValueTotal().toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
                      </div>
                    </td>
                    <td></td>
                  </tr>
                </tfoot>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import { CheckCircleIcon, XCircleIcon, MagnifyingGlassIcon, BackspaceIcon, CalendarIcon, ExclamationCircleIcon, ArrowDownTrayIcon } from '@heroicons/vue/20/solid';
import DatePicker from '@/Components/DatePicker.vue';
import SearchInput from '@/Components/SearchInput.vue';
import { onBeforeMount, onMounted, ref, watch } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import moment from 'moment';
import MultiSelect from '@/Components/MultiSelect.vue';
const props = defineProps({
  operatorOptions: Object,
  products: Object,
})

const authOperator = usePage().props.auth.operator
const baseUrl = ref('/products/availability')
const operatorCountry = usePage().props.auth.operatorCountry;
const operatorOptions = ref([])
const permissions = usePage().props.auth.permissions
const filters = ref({
  product_name: '',
  product_code: '',
  is_available: '',
  operators: [],
  productAvailableDate: moment().add(1, 'days').format('YYYY-MM-DD'),
  sortKey: 'code',
  sortBy: false,
});
const today = moment().format('YYYY-MM-DD');

const booleanOptions = ref([])
onMounted(() => {
  booleanOptions.value = [
    {id: 'all', value: 'All'},
    {id: 'true', value: 'Yes'},
    {id: 'false', value: 'No'},
  ]
  operatorOptions.value = [
			{id: 'all', full_name: 'All'},
      ...props.operatorOptions.data.map((data) => {return {id: data.id, code: data.code, full_name: data.full_name}})
  ]
  filters.value.operators = authOperator ? [
		operatorOptions.value.find(operator => operator.id === authOperator.id),
		...authOperator.code == 'HIPL' ? [
			operatorOptions.value.find(operator => operator.code == 'HIMD'),
			operatorOptions.value.find(operator => operator.code == 'LEA'),
      operatorOptions.value.find(operator => operator.code == 'HIESG'),
      operatorOptions.value.find(operator => operator.code == 'UL-ST'),
		] : [],
	].filter(operator => operator !== undefined) : [operatorOptions.value[0]]

  filters.value = {
    ...filters.value,
    ...props.products.filters,
  }

  if(!filters.value.is_available) {
    filters.value.is_available = booleanOptions.value[0]
  } else {
    filters.value.is_available = booleanOptions.value.find(option => option.id === filters.value.is_available)
  }
})

// Functions to calculate total available, net, and needed quantities and costs
function getProductAvailablePcsApiTotal() {
  return props.products.data.reduce((acc, product) => {
    const qtyAvailable = Number(product.qty_available_pcs_api) || 0;
    return acc + qtyAvailable;
  }, 0);
}

function getProductAvailablePcsApiTotalCost() {
  return props.products.data.reduce((acc, product) => {
    const qtyAvailable = Number(product.qty_available_pcs_api) || 0;
    const unitCost = Number(product.latestUnitCost?.cost) || 0;
    return acc + (qtyAvailable * unitCost);
  }, 0);
}

function getProductNotYetSyncApiQtyTotal() {
  return props.products.data.reduce((acc, product) => {
    const notYetSyncApiQty = Number(product.not_yet_sync_api_qty) || 0;
    return acc + notYetSyncApiQty;
  }, 0);
}

function getProductNotYetSyncApiQtyTotalCost() {
  return props.products.data.reduce((acc, product) => {
    const notYetSyncApiQty = Number(product.not_yet_sync_api_qty) || 0;
    const unitCost = Number(product.latestUnitCost?.cost) || 0;
    return acc + (notYetSyncApiQty * unitCost);
  }, 0);
}

function getProductPickedValueTotal() {
  return props.products.data.reduce((acc, product) => {
    const pickedValue = Number(product.picked_value_on_date) || 0;
    return acc + pickedValue;
  }, 0);
}

function getProductNeededValueTotal() {
  return props.products.data.reduce((acc, product) => {
    const neededValue = product.is_available ? (Number(product.needed_value) || 0) : 0;
    return acc + neededValue;
  }, 0);
}

function getProductNetAvailableQtyPcsApiTotal() {
  return props.products.data.reduce((acc, product) => {
    const netAvailableQty = Number(product.net_available_qty_pcs_api) || 0;
    return acc + netAvailableQty;
  }, 0);
}

function getProductNetAvailableQtyPcsApiTotalCost() {
  return props.products.data.reduce((acc, product) => {
    const netAvailableQty = Number(product.net_available_qty_pcs_api) || 0;
    const unitCost = Number(product.latestUnitCost?.cost) || 0;
    return acc + (netAvailableQty * unitCost);
  }, 0);
}

function getProductNeededQtyTotal() {
  return props.products.data.reduce((acc, product) => {
    const neededQty = product.is_available ? (Number(product.needed_qty) || 0) : 0;
    return acc + neededQty;
  }, 0);
}

function getProductNeededQtyTotalCost() {
  return props.products.data.reduce((acc, product) => {
    const neededQty = product.is_available ? (Number(product.needed_qty) || 0) : 0;
    const unitCost = Number(product.latestUnitCost?.cost) || 0;
    return acc + (neededQty * unitCost);
  }, 0);
}

function getDailySoldQtyTotal() {
  return props.products.data.reduce((acc, product) => {
    const qty = Number(product.avg_seven_days_count) || 0;
    return acc + qty;
  }, 0);
}

function getDailySoldQtyTotalCost() {
  return props.products.data.reduce((acc, product) => {
    const qty = Number(product.avg_seven_days_count) || 0;
    const unitCost = Number(product.latestUnitCost?.cost) || 0;
    return acc + (qty * unitCost);
  }, 0);
}

// Event handlers for availability toggling and limit selection
function onIsAvailableClicked(product) {
  if(permissions.includes('admin-access product-availability')) {
    router.post('/products/availability/toggle-is-available', {
      product_id: product.id
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
  }
}

function onMaxOpsJobPickLimitSelected(id, max_ops_job_pick_limit) {
  axios.post('/products/availability/update-max-ops-job-pick-limit/' + id, {
    date: filters.value.productAvailableDate,
    max_ops_job_pick_limit,
  })
  .then(response => {
    // Update the state locally without reloading the page
    const updatedProduct = response.data; // Assuming the server returns the updated product

    // Find the product in the list and update its max_ops_job_pick_limit
    const productIndex = props.products.data.findIndex(product => product.id === id);
    if (productIndex !== -1) {
      props.products.data[productIndex].max_ops_job_pick_limit = max_ops_job_pick_limit;
    }
  })
  .catch(error => {
    console.error('Error updating max_ops_job_pick_limit:', error);
  });
}



function onSearchFilterUpdated() {
  router.get(baseUrl.value, {
    ...filters.value,
    operators: filters.value.operators.filter(operator => operator).map(operator => operator.id),
    is_available: filters.value.is_available ? filters.value.is_available.id : 'all',
  }, {
    replace: true,
    preserveState: true,
    preserveScroll: true,
  })
}

function sortTable(key) {
  filters.value.sortKey = key
  filters.value.sortBy = !filters.value.sortBy
  onSearchFilterUpdated()
}

function resetFilters() {
  router.get(baseUrl.value)
}

function onExcelExportClicked() {
  let operators = filters.value.operators.filter(op => op).map(op => op.id)
  if (operators.includes('all')) {
    operators = ['all']
  }
  axios({
    method: 'get',
    url: route('products-availability.export-excel'),
    params: {
      product_name: filters.value.product_name,
      product_code: filters.value.product_code,
      operators: operators,
      is_available: filters.value.is_available ? filters.value.is_available.id : 'all',
      productAvailableDate: filters.value.productAvailableDate,
    },
    responseType: 'blob',
  }).then(response => {
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', 'Product_Availability_' + filters.value.productAvailableDate.replace(/-/g, '') + '_' + new Date().toISOString().slice(11,19).replace(/:/g,'') + '.xlsx')
    document.body.appendChild(link)
    link.click()
    link.remove()
  }).catch(error => {
    console.error('Export failed:', error)
  })
}
</script>
