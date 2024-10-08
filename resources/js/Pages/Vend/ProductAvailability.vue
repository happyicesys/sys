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
            <div class="max-h-[600px] md:max-h-[800px] overflow-y-auto shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
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
                    <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                      Available?
                    </th>
                    <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                      Warehouse Qty <br>
                      (from API)
                    </th>
                    <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                      Job(s) Qty <br>
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
                      <select name="max_ops_job_pick_limit" id="max_ops_job_pick_limit" class="rounded" :class="[product.max_ops_job_pick_limit > 0 ? 'text-red-600' : 'text-gray-800']" v-model="product.max_ops_job_pick_limit" :disabled="!product.is_available" @change="onMaxOpsJobPickLimitSelected(product.id, product.max_ops_job_pick_limit)">
                        <option :value="null">No</option>
                        <option v-for="n in 15 + 1" :key="n-1" :value="n-1">{{ n-1 }}</option>
                      </select>
                    </td>
                  </tr>
                  <tr>
                    <td colspan="3"></td>
                    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center text-gray-800">
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
import { CheckCircleIcon, XCircleIcon } from '@heroicons/vue/20/solid';
import DatePicker from '@/Components/DatePicker.vue';
import { onMounted, ref } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import moment from 'moment';
const props = defineProps({
  products: Object,
})

const operatorCountry = usePage().props.auth.operatorCountry;
const filters = ref({
  name: '',
  code: '',
  is_available: '',
  productAvailableDate: moment().add(1, 'days').format('YYYY-MM-DD'),
});
const today = moment().format('YYYY-MM-DD');

onMounted(() => {
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
  router.post('/products/toggle-is-available', {
    product_id: product.id
  }, {
      preserveState: true,
      preserveScroll: true,
      replace: true,
  })
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
  router.reload({
    only: ['products'],
    data: {
      ...filters.value,
    },
    replace: true,
    preserveState: true,
    preserveScroll: true,
  })
}
</script>


// Update the state locally without reloading the page
const updatedProduct = response.data; // Assuming the server returns the updated product

// Find the product in the list and update its max_ops_job_pick_limit
const productIndex = props.products.data.findIndex(product => product.id === id);
if (productIndex !== -1) {
  props.products.data[productIndex].max_ops_job_pick_limit = max_ops_job_pick_limit;
}