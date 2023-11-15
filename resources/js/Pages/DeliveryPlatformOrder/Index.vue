<template>

  <Head title="Delivery Platform" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Delivery Platform Orders
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
          <!-- <div class="flex flex-col md:flex-row md:space-x-3 space-y-1 md:space-y-0"> -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
          <SearchInput placeholderStr="Name" v-model="filters.order_id">
            Order ID
          </SearchInput>
          <SearchInput placeholderStr="Name" v-model="filters.short_order_id">
            Short Order ID
          </SearchInput>
          <SearchInput placeholderStr="Vend ID" v-model="filters.vend_code">
            Vend ID
          </SearchInput>
          <DatePicker
              v-model="filters.date_from"
          >
              From
          </DatePicker>
          <DatePicker
              v-model="filters.date_to"
              :minDate="filters.date_from"
          >
              To
          </DatePicker>
        </div>

        <div class="flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5">
          <div class="mt-3">
            <div class="flex space-x-1">
              <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
              @click="onSearchFilterUpdated()"
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
          <div class="flex flex-col space-y-2">
              <p class="text-sm text-gray-700 leading-5 flex space-x-1">
                  <span>Showing</span>
                  <span class="font-medium">{{ deliveryPlatformOrders.meta.from ?? 0 }}</span>
                  <span>to</span>
                  <span class="font-medium">{{ deliveryPlatformOrders.meta.to ?? 0 }}</span>
                  <span>of</span>
                  <span class="font-medium">{{ deliveryPlatformOrders.meta.total }}</span>
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
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8 px-3">
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll">
            <table class="min-w-full border-separate" style="border-spacing: 0">
                <thead class="bg-gray-100">
                  <tr class="divide-x divide-gray-200">
                    <TableHead>
                      #
                    </TableHead>
                    <TableHeadSort modelName="order_created_at" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('order_created_at')">
                      Order Time
                    </TableHeadSort>
                    <TableHead>
                      Platform
                    </TableHead>
                    <TableHead>
                      Order ID
                    </TableHead>
                    <TableHead>
                      Short Order ID
                    </TableHead>
                    <TableHead>
                      Status
                    </TableHead>
                    <TableHeadSort modelName="vend_code" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_code')">
                      Vend
                    </TableHeadSort>
                    <TableHead>
                      Transactions <br>
                      Order ID
                    </TableHead>
                    <TableHead>
                      (Channel) Item x Qty
                    </TableHead>
                    <TableHead>
                      Subtotal
                    </TableHead>
                  </tr>
                </thead>
                <tbody class="bg-white">
                  <tr v-for="(deliveryPlatformOrder, deliveryPlatformOrderIndex) in deliveryPlatformOrders.data" :key="deliveryPlatformOrder.id" class="divide-x divide-gray-200">
                    <TableData :currentIndex="deliveryPlatformOrderIndex" :totalLength="deliveryPlatformOrders.length" inputClass="text-center">
                      {{ deliveryPlatformOrders.meta.from + deliveryPlatformOrderIndex }}
                    </TableData>
                    <TableData :currentIndex="deliveryPlatformOrderIndex" :totalLength="deliveryPlatformOrders.length" inputClass="text-center">
                      {{ deliveryPlatformOrder.order_created_at }}
                    </TableData>
                    <TableData :currentIndex="deliveryPlatformOrderIndex" :totalLength="deliveryPlatformOrders.length" inputClass="text-center">
                      {{ deliveryPlatformOrder && deliveryPlatformOrder.deliveryPlatform ? deliveryPlatformOrder.deliveryPlatform.name : null }}
                      <span v-if="deliveryPlatformOrder.deliveryPlatformOperator">
                        <br>({{ deliveryPlatformOrder.deliveryPlatformOperator ? deliveryPlatformOrder.deliveryPlatformOperator.type : null }})
                      </span>
                    </TableData>
                    <TableData :currentIndex="deliveryPlatformOrderIndex" :totalLength="deliveryPlatformOrders.length" inputClass="text-center">
                      <Link :href="'/delivery-platform-orders/' + deliveryPlatformOrder.id + '/edit'" class="text-blue-600">
                        {{ deliveryPlatformOrder.order_id }}
                      </Link>
                    </TableData>
                    <TableData :currentIndex="deliveryPlatformOrderIndex" :totalLength="deliveryPlatformOrders.length" inputClass="text-center">
                      {{ deliveryPlatformOrder.short_order_id }}
                    </TableData>
                    <TableData :currentIndex="deliveryPlatformOrderIndex" :totalLength="deliveryPlatformOrders.length" inputClass="text-center">
                      <div
                          class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                          :class="statusClass(deliveryPlatformOrder.status)"
                      >
                          <div class="flex flex-col">
                              <span class="font-semibold">
                                {{ deliveryPlatformOrder.status_name }}
                              </span>
                          </div>

                      </div>
                    </TableData>
                    <TableData :currentIndex="deliveryPlatformOrderIndex" :totalLength="deliveryPlatformOrders.length" inputClass="text-left">
                      {{ deliveryPlatformOrder.deliveryProductMappingVend.vend.full_name }}
                    </TableData>
                    <TableData :currentIndex="deliveryPlatformOrderIndex" :totalLength="deliveryPlatformOrders.length" inputClass="text-center">
                      {{ deliveryPlatformOrder.vend_transaction_order_id  }}
                    </TableData>
                    <TableData :currentIndex="deliveryPlatformOrderIndex" :totalLength="deliveryPlatformOrders.length" inputClass="text-left">
                      <ul class="divide-y divide-gray-200">
                        <li class="flex py-1 px-3 space-x-2" v-for="deliveryPlatformOrderItem in deliveryPlatformOrder.deliveryPlatformOrderItems">
                          <span class="self-center font-semibold text-blue-700">
                            <span v-if="deliveryPlatformOrderItem.orderItemVendChannels[0]">
                              (#{{ deliveryPlatformOrderItem.orderItemVendChannels[0].vend_channel_code }})
                            </span>
                          </span>
                          <span>
                            {{ deliveryPlatformOrderItem.deliveryProductMappingItem.product.code }} <br>
                            {{ deliveryPlatformOrderItem.deliveryProductMappingItem.product.name }}
                          </span>
                          <div class="flex self-center">
                            <a :href="deliveryPlatformOrderItem.deliveryProductMappingItem.product.thumbnail.full_url" target="_blank" v-if="deliveryPlatformOrderItem.deliveryProductMappingItem.product.thumbnail">
                              <img class="object-scale-down h-24 w-24 md:h-16 md:w-20 rounded-full" :src="deliveryPlatformOrderItem.deliveryProductMappingItem.product.thumbnail.full_url" alt="" />
                            </a>
                          </div>
                          <span class="self-center">
                            x
                          </span>
                          <span class="self-center">
                            {{ deliveryPlatformOrderItem.qty }}
                          </span>
                        </li>
                      </ul>
                    </TableData>
                    <TableData :currentIndex="deliveryPlatformOrderIndex" :totalLength="deliveryPlatformOrders.length" inputClass="text-right">
                      {{ deliveryPlatformOrder.subtotal_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                    </TableData>
                  </tr>
                  <tr v-if="!deliveryPlatformOrders.data.length">
                    <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                        No Results Found
                    </td>
                  </tr>
                </tbody>
            </table>
            <Paginator v-if="deliveryPlatformOrders.data.length" :links="deliveryPlatformOrders.links" :meta="deliveryPlatformOrders.meta"></Paginator>
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
import SearchInput from '@/Components/SearchInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import moment from 'moment';
import { BackspaceIcon, MagnifyingGlassIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ref, onMounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
  deliveryPlatformOrders: Object,
})

const filters = ref({
  order_id: '',
  short_order_id: '',
  vend_code: '',
  date_from: moment().format('YYYY-MM-DD'),
  date_to: moment().format('YYYY-MM-DD'),
  sortKey: '',
  sortBy: true,
  numberPerPage: 100,
})
const showModal = ref(false)
const deliveryPlatformOrder = ref()
const type = ref('')
const operatorCountry = usePage().props.auth.operatorCountry
const numberPerPageOptions = ref([])

onMounted(() => {
  numberPerPageOptions.value = [
    { id: 100, value: 100 },
    { id: 200, value: 200 },
    { id: 500, value: 500 },
    { id: 'All', value: 'All' },
  ]
  filters.value.numberPerPage = numberPerPageOptions.value[0]
})

function onCreateClicked() {
  type.value = 'create'
  deliveryPlatformOrder.value = null
  showModal.value = true
}

function onDeleteClicked(deliveryPlatformOrder) {
  const approval = confirm('Are you sure to delete ' + deliveryPlatformOrder.name + '?');
  if (!approval) {
      return;
  }
  router.delete('/delivery-product-mappings/' + deliveryPlatformOrder.id)
}

function onEditClicked(telcoValue) {
  type.value = 'update'
  deliveryPlatformOrder.value = telcoValue
  showModal.value = true
}

function onSearchFilterUpdated() {
  router.get('/delivery-platform-orders', {
      ...filters.value,
      numberPerPage: filters.value.numberPerPage.id,
  }, {
      preserveState: true,
      replace: true,
  })
}

function resetFilters() {
  router.get('/delivery-platform-orders')
}

function sortTable(sortKey) {
  filters.value.sortKey = sortKey
  filters.value.sortBy = !filters.value.sortBy
  onSearchFilterUpdated()
}

function statusClass(status) {
  let statusClass = ''
  switch(status) {
    case 1:
    case 2:
      statusClass = 'bg-blue-400 text-gray-800'
      break;
    case 3:
    case 4:
    case 5:
    case 6:
      statusClass = 'bg-yellow-400 text-gray-800'
      break;
    case 7:
      statusClass = 'bg-green-400 text-white-800'
      break;
    case 98:
    case 99:
      statusClass = 'bg-red-400 text-white-800'
      break;
  }
  return statusClass
}

function onModalClose() {
  showModal.value = false
}
</script>