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
          <div>
            <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
              Delivery Platform
            </label>
            <MultiSelect
              v-model="filters.delivery_platform_operator_id"
              :options="deliveryPlatformOperatorOptions"
              trackBy="id"
              valueProp="id"
              label="name"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
            >
            </MultiSelect>
          </div>
          <div>
            <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
              Status
            </label>
            <MultiSelect
              v-model="filters.status"
              :options="deliveryPlatformOrderStatusOptions"
              trackBy="id"
              valueProp="id"
              label="name"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
            >
            </MultiSelect>
          </div>
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
              Has Complaint?
            </label>
            <MultiSelect
              v-model="filters.has_complaint"
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

              <Button type="button" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-400 hover:bg-gray-100"
                @click="onExportExcelClicked()"
                v-if="permissions.includes('export excel')">
                <div class="flex space-x-1">
                  <div>
                    <ArrowDownTrayIcon v-if="!loading" class="h-4 w-4" aria-hidden="true"/>
                    <svg v-if="loading" aria-hidden="true" class="mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                        <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                    </svg>
                  </div>
                  <span>
                      Export Excel
                  </span>
                </div>
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
        <dl class="mt-2 grid grid-cols-1 gap-5 sm:grid-cols-3">
            <div class="overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow">
                <dt class="truncate text-sm font-medium text-gray-500">Total Amount (Delivered)</dt>
                <dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
                    {{(totals['total_amount']/ (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
                </dd>
            </div>
            <div class="overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow">
                <dt class="truncate text-sm font-medium text-gray-500">Total Orders (Delivered)</dt>
                <dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
                    {{totals['order_count'].toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0})}}
                </dd>
            </div>
        </dl>
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
                      Transaction <br>
                      Order ID
                    </TableHead>
                    <TableHead>
                      (Channel) Item x Qty
                    </TableHead>
                    <TableHead>
                      Subtotal
                    </TableHead>
                    <TableHead>
                      Campaign
                    </TableHead>
                    <TableHead>
                      Channel Error(s)
                    </TableHead>
                    <TableHead>
                      Driver Phone Number
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
                    <TableData :currentIndex="deliveryPlatformOrderIndex" :totalLength="deliveryPlatformOrders.length" inputClass="text-center w-xs">
                      <div class="w-xs">
                      <div
                          class="inline-flex justify-center items-center rounded px-1 py-0.5 text-xs font-medium border w-xs hover:cursor-pointer"
                          :class="statusClass(deliveryPlatformOrder).statusClass"
                          @click.prevent="onStatusModalClicked(deliveryPlatformOrder)"
                      >
                          <div class="flex flex-col">
                          <!-- <div class="flex flex-col"> -->
                              <span class="font-semibold grow-0">
                                {{ deliveryPlatformOrder.status_name }}
                              </span>
                          </div>
                      </div>
                      <span class="text-xs" v-if="statusClass(deliveryPlatformOrder).statusDesc">
                        <br>
                        {{ statusClass(deliveryPlatformOrder).statusDesc }}
                      </span>
                      </div>
                    </TableData>
                    <TableData :currentIndex="deliveryPlatformOrderIndex" :totalLength="deliveryPlatformOrders.length" inputClass="text-left">
                      {{ deliveryPlatformOrder.vend_code }} <br>
                      {{ deliveryPlatformOrder.deliveryProductMappingVend.vend.cust_full_name }}
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
                          <!-- <div class="flex self-center">
                            <a :href="deliveryPlatformOrderItem.deliveryProductMappingItem.product.thumbnail.full_url" target="_blank" v-if="deliveryPlatformOrderItem.deliveryProductMappingItem.product.thumbnail">
                              <img class="object-scale-down h-24 w-24 md:h-16 md:w-20 rounded-full" :src="deliveryPlatformOrderItem.deliveryProductMappingItem.product.thumbnail.full_url" alt="" />
                            </a>
                          </div> -->
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
                    <!-- <TableData :currentIndex="deliveryPlatformOrderIndex" :totalLength="deliveryPlatformOrders.length" inputClass="text-right">
                      <div class="flex flex-col space-y-1">
                        <span v-if="deliveryPlatformOrder.campaign_json" v-for="campaign in deliveryPlatformOrder.campaign_json" class="inline-flex items-center rounded-md bg-purple-50 px-1 py-1 text-xs font-medium text-purple-700 ring-1 ring-inset ring-purple-700/10 max-w-[130px] truncate hover:text-clip">
                          {{ campaign.name }}
                        </span>
                      </div>
                    </TableData> -->
                    <TableData :currentIndex="deliveryPlatformOrderIndex" :totalLength="deliveryPlatformOrders.length" inputClass="text-center">
                      <!-- {{ deliveryPlatformOrder.deliveryProductMappingVend.deliveryPlatformCampaignItemVends }} -->
                      <div class="flex flex-col space-y-1">
                        <span v-for="campaign in deliveryPlatformOrder.virtual_campaign_id_json" class="inline-flex items-center rounded-md bg-purple-50 px-1 py-1 text-xs font-medium text-purple-700 ring-1 ring-inset ring-purple-700/10 hover:text-clip">
                          {{ getCampaignSettingsName(deliveryPlatformOrder, campaign) }}
                        </span>
                      </div>
                    </TableData>
                    <TableData :currentIndex="deliveryPlatformOrderIndex" :totalLength="deliveryPlatformOrders.length" inputClass="text-center">
                      <span v-if="deliveryPlatformOrder.vendTransaction && deliveryPlatformOrder.vendTransaction.itemsJson" v-for="item in deliveryPlatformOrder.vendTransaction.itemsJson" class="inline-flex items-center rounded px-2 py-0.5 text-xs">
                        <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium border bg-red-100 text-red-800" v-if="item.vendChannelError != null">
                          <div class="flex flex-col space-x-1">
                              <div>
                                  #{{ item.vendChannelCode }}
                                  <span class="font-bold">
                                    {{ item.vendChannelError.desc }}
                                  </span>
                              </div>
                          </div>
                        </span>
                      </span>
                    </TableData>
                    <TableData :currentIndex="deliveryPlatformOrderIndex" :totalLength="deliveryPlatformOrders.length" inputClass="text-center">
                      <div class="flex flex-col space-y-1">
                        <span>
                          {{ deliveryPlatformOrder.driver_phone_number }}
                        </span>
                        <div
                            class="inline-flex justify-center items-center rounded px-1.5 py-1 text-xs font-medium border min-w-full bg-yellow-400 text-gray-800 hover:cursor-pointer"
                            v-if="deliveryPlatformOrder.deliveryPlatformOrderComplaint"
                            @click="onDeliveryPlatformOrderComplaintClicked(deliveryPlatformOrder)"
                        >
                            <div class="flex space-x-1">
                                <ChatBubbleLeftEllipsisIcon class="h-4 w-4" aria-hidden="true"/>
                                <span class="font-semibold">
                                  Complaint
                                </span>
                            </div>

                        </div>
                      </div>
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
  <Complaint
    v-if="showDeliveryPlatformOrderComplaintModal"
    :model="model"
    :showModal="showDeliveryPlatformOrderComplaintModal"
    @modalClose="onDeliveryPlatformOrderComplaintClosed"
  >
  </Complaint>
  <StatusModal
    v-if="showStatusModal"
    :model="model"
    :showModal="showStatusModal"
    @modalClose="onStatusModalClosed"
  >
  </StatusModal>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import DatePicker from '@/Components/DatePicker.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import StatusModal from '@/Pages/DeliveryPlatformOrder/StatusModal.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import moment from 'moment';
import Complaint from '@/Pages/DeliveryPlatformOrder/Complaint.vue';
import { ArrowDownTrayIcon, BackspaceIcon, ChatBubbleLeftEllipsisIcon, MagnifyingGlassIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ref, onMounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
  deliveryPlatformOrders: Object,
  deliveryPlatformOperatorOptions: Object,
  deliveryPlatformOrderStatusOptions: Object,
  totals: Object,
})

const booleanOptions = ref([])
const filters = ref({
  order_id: '',
  short_order_id: '',
  vend_code: '',
  date_from: moment().format('YYYY-MM-DD'),
  date_to: moment().format('YYYY-MM-DD'),
  delivery_platform_operator_id: '',
  has_complaint: 'all',
  sortKey: '',
  sortBy: false,
  status: '',
  numberPerPage: 100,
})
const deliveryPlatformOperatorOptions = ref([])
const deliveryPlatformOrder = ref()
const loading = ref(false)
const model = ref()
const operatorCountry = usePage().props.auth.operatorCountry
const numberPerPageOptions = ref([])
const permissions = usePage().props.auth.permissions
const showDeliveryPlatformOrderComplaintModal = ref(false)
const showStatusModal = ref(false)

onMounted(() => {
  booleanOptions.value = [
      {id: 'all', value: 'All'},
      {id: 'true', value: 'Yes'},
      {id: 'false', value: 'No'},
  ]
  numberPerPageOptions.value = [
    { id: 100, value: 100 },
    { id: 200, value: 200 },
    { id: 500, value: 500 },
    { id: 'All', value: 'All' },
  ]
  filters.value.numberPerPage = numberPerPageOptions.value[0]
  deliveryPlatformOperatorOptions.value = [
    { id: 'all', name: 'All' },
    ...props.deliveryPlatformOperatorOptions.data.map((data) => {
    return {id: data.id, name: data.deliveryPlatform.name + ' (' + data.type + ')'}})
  ]
  filters.value.delivery_platform_operator_id = deliveryPlatformOperatorOptions.value[0]
  filters.value.has_complaint = booleanOptions.value[0]
  filters.value.status = props.deliveryPlatformOrderStatusOptions[0]
})

function getCampaignSettingsName(deliveryPlatformOrder, campaignID) {
  return deliveryPlatformOrder.deliveryProductMappingVend.deliveryPlatformCampaignItemVends.find((vend) => vend.platform_ref_id == campaignID).deliveryPlatformCampaignItem.settings_name
}

function onDeliveryPlatformOrderComplaintClicked(deliveryPlatformOrder) {
  model.value = deliveryPlatformOrder
  showDeliveryPlatformOrderComplaintModal.value = true
}

function onDeliveryPlatformOrderComplaintClosed() {
  showDeliveryPlatformOrderComplaintModal.value = false
}

function onStatusModalClicked(deliveryPlatformOrder) {
  model.value = deliveryPlatformOrder
  showStatusModal.value = true
}

function onStatusModalClosed() {
  showStatusModal.value = false
}

function onExportExcelClicked() {
    loading.value = true
    axios({
        method: 'get',
        url: '/delivery-platform-orders/excel',
        params: {
          ...filters.value,
          delivery_platform_operator_id: filters.value.delivery_platform_operator_id.id,
          has_complaint: filters.value.has_complaint.id,
          status: filters.value.status.id,
        },
        responseType: 'blob',
    }).then(response => {
        fileDownload(response.data, 'Delivery_Platform_Order_' + moment().format('YYMMDDhhmmss') +'.xlsx')
    }).catch(error => {
        console.log(error)
    }).finally(() => {
        loading.value = false
    })
}

function onSearchFilterUpdated() {
  router.get('/delivery-platform-orders', {
      ...filters.value,
      delivery_platform_operator_id: filters.value.delivery_platform_operator_id.id,
      status: filters.value.status.id,
      has_complaint: filters.value.has_complaint.id,
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

function statusClass(deliveryPlatformOrder) {
  let statusClass = ''
  let statusDesc = ''
  switch(deliveryPlatformOrder.status) {
    case 1:
    case 2:
      statusClass = 'bg-blue-400 text-gray-800'
      break;
    case 3:
    case 4:
    case 5:
    case 6:
    case 7:
      statusClass = 'bg-yellow-400 text-gray-800'
      break;
    case 8:
      statusClass = 'bg-green-400 text-white-800'
      break;
    case 98:
    case 99:
      statusClass = 'bg-red-400 text-white-800'
      statusDesc = deliveryPlatformOrder.request_history_json['code'] + ' (' + deliveryPlatformOrder.request_history_json['message'] + ')'
      break;
  }
  return {
    statusClass: statusClass,
    statusDesc: statusDesc,
  }
}

</script>