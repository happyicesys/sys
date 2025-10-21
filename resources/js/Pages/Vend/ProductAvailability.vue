<template>
  <Head title="Product Availability" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Set Product Availability
      </h2>
    </template>

    <div class="m-2 sm:mx-2 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
        <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5">
          <div class="inline-block min-w-full py-2 align-middle md:px-4 lg:px-6">
            <div class="py-4">
              <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
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
                  </div>
              </div>
              </div>
            <div class="overflow-scroll max-h-[900px] md:max-h-[1500px] shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
              <table class="min-w-full divide-y divide-gray-300">
                <thead class="bg-gray-100 sticky top-0 z-10">
                  <tr>
                    <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                      #
                    </th>
                    <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                      Image
                    </th>
                    <th scope="col" class="w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                      Product
                    </th>
                    <th scope="col" class="w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                      Last7d sold qty <br>
                      (avg last 28d)
                    </th>
                    <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                      Available?
                    </th>
                    <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                      Warehouse Qty <br>
                      (from API)
                    </th>
                    <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                      >= Picked (Jobs) <br>
                      (not yet sync API)
                    </th>
                    <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                      Net Available Qty <br>
                      (based on API)
                    </th>
                    <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                      Needed Qty <br>
                      <DatePicker
                        v-model="filters.productAvailableDate"
                        :isPreviousNextButton="false"
                        :clearable="false"
                        @update:modelValue="onSearchFilterUpdated"
                        :minDate="today"
                      />
                    </th>
                    <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                      Qty Limit <br>
                      (per Job, on selected date)
                    </th>
                  </tr>
                </thead>
                <tbody class="bg-white">
                  <tr v-for="(product, productIndex) in products.data" :key="product.id" :class="productIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold sm:pl-6 text-center text-gray-900">
                      {{ productIndex + 1 }}
                    </td>
                    <td class="whitespace-nowrap text-sm  font-semibold text-gray-900 text-center">
                      <div class="flex justify-center items-center">
                        <img class="h-16 w-16 rounded-full" :class="[product.is_available ? '' : 'opacity-50']" :src="product.thumbnail.full_url" alt="" v-if="product.thumbnail" />
                      </div>
                    </td>
                    <td class="py-4 text-sm font-semibold text-left" :class="[product.is_available ? 'text-gray-800' : 'text-gray-400']">
                      <span v-if="product.code">
                        {{ product.code }}
                      </span>
                      <span class="break-normal text-xs" v-if="product.name">
                        <br> {{ product.name }}
                      </span>
                    </td>
                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center" :class="[product.is_available ? 'text-gray-600' : 'text-gray-400']">
                      {{ Number(product.avg_seven_days_count)?.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0}) }}
                    </td>
                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center text-blue-600">
                      <div class="flex flex-col justify-center items-center">
                        <span v-if="product.is_available">
                          <CheckCircleIcon class="h-6 w-6 text-green-500 hover:cursor-pointer hover:text-green-600" @click.prevent="onIsAvailableClicked(product)" />
                        </span>
                        <span v-else>
                          <XCircleIcon class="h-6 w-6 text-red-500 hover:cursor-pointer hover:text-red-600" @click.prevent="onIsAvailableClicked(product)" />
                        </span>
                        <span class="text-xs text-gray-500">
                          {{ product.isAvailableUpdatedBy ? product.isAvailableUpdatedBy.name : '' }}
                        </span>
                        <span class="text-xs text-gray-500">
                          {{ product.is_available_updated_at }}
                        </span>
                      </div>
                    </td>
                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center" :class="[product.is_available ? 'text-blue-600' : 'text-gray-400']">
                      {{ Number(product.qty_available_pcs_api)?.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0}) }}
                    </td>
                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center" :class="[product.is_available ? 'text-gray-800' : 'text-gray-400']">
                      {{ Number(product.not_yet_sync_api_qty)?.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0}) }}
                    </td>
                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center" :class="[product.is_available ? 'text-gray-800' : 'text-gray-400']">
                      <span :class="product.is_available ? (product.net_available_qty_pcs_api < product.needed_qty ? 'text-red-800 bg-red-200 rounded px-1 py-1' : '') : 'text-gray-400'">
                        {{ Number(product.net_available_qty_pcs_api)?.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0}) }}
                      </span>
                    </td>
                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center" :class="[product.is_available ? 'text-gray-800' : 'text-gray-400']">
                      {{ Number(product.needed_qty)?.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0}) }}
                    </td>
                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center text-gray-800">
                      <div class="flex flex-col space-y-1">
                        <select name="max_ops_job_pick_limit" id="max_ops_job_pick_limit" class="rounded" :class="[product.max_ops_job_pick_limit >= 0 && product.max_ops_job_pick_limit != null ? 'text-red-600' : 'text-gray-800']" v-model="product.max_ops_job_pick_limit" :disabled="!product.is_available || !permissions.includes('admin-access product-availability')" @change="onMaxOpsJobPickLimitSelected(product.id, product.max_ops_job_pick_limit)">
                          <option :value="null">No</option>
                          <option v-for="n in 15 + 1" :key="n-1" :value="n-1">{{ n-1 }}</option>
                        </select>
                        <span class="text-xs text-red-700" v-if="product.max_ops_job_pick_limit != null && product.limit_is_created_by_system">
                          from Yesterday
                        </span>
                        <span class="text-xs text-gray-800" v-if="product.max_ops_job_pick_limit != null && product.productLimits[0] && product.productLimits[0].createdBy">
                          {{ product.productLimits[0].createdBy.name }}
                        </span>
                        <span class="text-xs text-gray-800" v-if="product.max_ops_job_pick_limit != null && product.productLimits[0] && product.productLimits[0].createdBy">
                          {{ product.productLimits[0].setupDate }}
                        </span>
                      </div>
                    </td>
                  </tr>
                  <tr>
                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center text-gray-800" colspan="5">
                      <div class="flex flex-col space-y-1">
                        <span>Total Pcs</span>
                        <span>Total Cost$</span>
                      </div>
                    </td>
                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center text-blue-600">
                      <div class="flex flex-col space-y-1">
                        <span>{{ getProductAvailablePcsApiTotal().toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}</span>
                        <span>{{ operatorCountry.currency_symbol }}{{ getProductAvailablePcsApiTotalCost().toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
                      </div>
                    </td>
                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center text-blue-600">
                      <div class="flex flex-col space-y-1">
                        <span>{{ getProductNotYetSyncApiQtyTotal().toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}</span>
                        <span>{{ operatorCountry.currency_symbol }}{{ getProductNotYetSyncApiQtyTotalCost().toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
                      </div>
                    </td>
                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center text-blue-600">
                      <div class="flex flex-col space-y-1">
                        <span>{{ getProductNetAvailableQtyPcsApiTotal().toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}</span>
                        <span>{{ operatorCountry.currency_symbol }}{{ getProductNetAvailableQtyPcsApiTotalCost().toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
                      </div>
                    </td>
                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center text-gray-800">
                      <div class="flex flex-col space-y-1">
                        <span>{{ getProductNeededQtyTotal().toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}</span>
                        <span>{{ operatorCountry.currency_symbol }}{{ getProductNeededQtyTotalCost().toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
                      </div>
                    </td>
                  </tr>
                </tbody>
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
import { CheckCircleIcon, XCircleIcon, MagnifyingGlassIcon, BackspaceIcon } from '@heroicons/vue/20/solid';
import DatePicker from '@/Components/DatePicker.vue';
import { onBeforeMount, onMounted, ref } from 'vue';
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
  name: '',
  code: '',
  is_available: '',
  operators: [],
  productAvailableDate: moment().add(1, 'days').format('YYYY-MM-DD'),
});
const today = moment().format('YYYY-MM-DD');

onMounted(() => {
  operatorOptions.value = [
			{id: 'all', full_name: 'All'},
      ...props.operatorOptions.data.map((data) => {return {id: data.id, code: data.code, full_name: data.full_name}})
  ]
  filters.value.operators = authOperator ? [
		operatorOptions.value.find(operator => operator.id === authOperator.id),
		...authOperator.code == 'HIPL' ? [
			operatorOptions.value.find(operator => operator.code == 'HIMD'),
			operatorOptions.value.find(operator => operator.code == 'LEA'),
      operatorOptions.value.find(operator => operator.code == 'DCVIC'),
      operatorOptions.value.find(operator => operator.code == 'HIESG'),
      operatorOptions.value.find(operator => operator.code == 'IP'),
		] : [],
	] : operatorOptions.value[0]

  filters.value = {
    ...filters.value,
    ...props.products.filters,
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
    const neededQty = Number(product.needed_qty) || 0;
    return acc + neededQty;
  }, 0);
}

function getProductNeededQtyTotalCost() {
  return props.products.data.reduce((acc, product) => {
    const neededQty = Number(product.needed_qty) || 0;
    const unitCost = Number(product.latestUnitCost?.cost) || 0;
    return acc + (neededQty * unitCost);
  }, 0);
}

// Event handlers for availability toggling and limit selection
function onIsAvailableClicked(product) {
  if(permissions.includes('admin-access product-availability')) {
    router.post('/products/toggle-is-available', {
      product_id: product.id
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
  }
}

function onMaxOpsJobPickLimitSelected(id, max_ops_job_pick_limit) {
  axios.post('/products/' + id + '/max-ops-job-pick-limit', {
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
    operators: filters.value.operators.map(operator => operator.id),
  }, {
    replace: true,
    preserveState: true,
    preserveScroll: true,
  })
}

function resetFilters() {
  router.get(baseUrl.value)
}
</script>


// Update the state locally without reloading the page
const updatedProduct = response.data; // Assuming the server returns the updated product

// Find the product in the list and update its max_ops_job_pick_limit
const productIndex = props.products.data.findIndex(product => product.id === id);
if (productIndex !== -1) {
  props.products.data[productIndex].max_ops_job_pick_limit = max_ops_job_pick_limit;
}
