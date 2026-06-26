<template>

  <Head title="Delivery Platform Machine" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Delivery Platform Machine
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
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
            <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
              Delivery Platform
            </label>
            <MultiSelect
              v-model="filters.delivery_platform_type_id"
              :options="deliveryPlatformTypeOptions"
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
              Del Product Mapping
            </label>
            <MultiSelect
              v-model="filters.delivery_product_mapping_id"
              :options="deliveryProductMappingOptions"
              trackBy="id"
              valueProp="id"
              label="name"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
            >
            </MultiSelect>
          </div>
          <SearchInput placeholderStr="Machine ID" v-model="filters.vend_code">
            Machine ID
          </SearchInput>
          <div>
            <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
              Platform ID
            </label>
            <MultiSelect
              v-model="selectedPlatformRefNumber"
              :options="platformRefNumberOptions"
              trackBy="id"
              valueProp="id"
              label="ref_number"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
              :canClear="false"
            >
            </MultiSelect>
          </div>
          <div>
              <label for="text" class="block text-sm font-medium text-gray-700">
                  Binding Status
              </label>
              <MultiSelect
                  v-model="filters.is_active"
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
          <div>
              <DatePicker
                  v-model="filters.date_from"
              >
                  From
              </DatePicker>
          </div>
          <div>
              <DatePicker
                  v-model="filters.date_to"
                  :minDate="filters.date_from"
              >
                  To
              </DatePicker>
          </div>
        </div>

        <div class="flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5">
          <div class="mt-3">
            <div class="flex flex-col space-y-1 md:flex-row md:space-y-0 md:space-x-1">
              <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
              @click="onSearchFilterUpdated()"
              >
                <MagnifyingGlassIcon class="h-4 w-4" aria-hidden="true"/>
                <span>
                  Search
                </span>
              </Button>
            </div>
          </div>
          <div class="flex flex-col space-y-2">
              <p class="text-sm text-gray-700 leading-5 flex space-x-1">
                  <span>Showing</span>
                  <span class="font-medium">{{ deliveryProductMappingVends.meta.from ?? 0 }}</span>
                  <span>to</span>
                  <span class="font-medium">{{ deliveryProductMappingVends.meta.to ?? 0 }}</span>
                  <span>of</span>
                  <span class="font-medium">{{ deliveryProductMappingVends.meta.total }}</span>
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
                    {{(totals.subtotal_amount - totals.promo_amount).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
                </dd>
            </div>
            <div class="overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow">
                <dt class="truncate text-sm font-medium text-gray-500">Total Count (Delivered)</dt>
                <dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
                    {{totals.count.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0})}}
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
                    <TableHeadSort modelName="vend_code" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_code')">
                      Machine ID
                    </TableHeadSort>
                    <TableHead>
                      Platform ID
                    </TableHead>
                    <TableHead>
                      Cust ID
                    </TableHead>
                    <TableHead>
                      Cust Name
                    </TableHead>
                    <TableHead>
                      Channel
                    </TableHead>
                    <TableHead>
                      VM Status
                    </TableHead>
                    <TableHeadSort modelName="delivery_platform_orders_sum_subtotal_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('delivery_platform_orders_sum_subtotal_amount', true)">
                      Amount
                    </TableHeadSort>
                    <TableHeadSort modelName="delivery_platform_orders_count" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('delivery_platform_orders_count', true)">
                      Count
                    </TableHeadSort>
                    <!-- <TableHead>
                    </TableHead> -->
                  </tr>
                </thead>
                <tbody class="bg-white">
                  <tr v-for="(deliveryProductMappingVend, deliveryProductMappingVendIndex) in deliveryProductMappingVends.data" :key="deliveryProductMappingVend.id" class="divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100">
                    <TableData :currentIndex="deliveryProductMappingVendIndex" :totalLength="deliveryProductMappingVends.length" inputClass="text-center">
                      {{ deliveryProductMappingVends.meta.from + deliveryProductMappingVendIndex }}
                    </TableData>
                    <TableData :currentIndex="deliveryProductMappingVendIndex" :totalLength="deliveryProductMappingVends.length" inputClass="text-center">
                      {{ deliveryProductMappingVend.vend ? deliveryProductMappingVend.vend.code : '' }}
                    </TableData>
                    <TableData :currentIndex="deliveryProductMappingVendIndex" :totalLength="deliveryProductMappingVends.length" inputClass="text-left">
                      {{ deliveryProductMappingVend.platform_ref_id }}
                      <!-- <span class="inline-flex items-center rounded-md bg-blue-100 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-800/10" v-if="deliveryProductMappingVend.binded_times > 1">
                        {{ deliveryProductMappingVend.binded_times }}
                      </span> -->
                    </TableData>
                    <TableData :currentIndex="deliveryProductMappingVendIndex" :totalLength="deliveryProductMappingVends.length" inputClass="text-center">
                      <span v-if="deliveryProductMappingVend.vend && deliveryProductMappingVend.vend.customer">
                        {{ deliveryProductMappingVend.vend.customer.virtual_customer_code }}
                        <!-- <span v-if="deliveryProductMappingVend.vend.customer.virtual_customer_prefix">
                         ({{ deliveryProductMappingVend.vend.customer.virtual_customer_prefix }})
                        </span> -->
                      </span>
                    </TableData>
                    <TableData :currentIndex="deliveryProductMappingVendIndex" :totalLength="deliveryProductMappingVends.length" inputClass="text-left">
                      <span v-if="deliveryProductMappingVend.vend && deliveryProductMappingVend.vend && deliveryProductMappingVend.vend.customer">
                        {{ deliveryProductMappingVend.vend.customer.name }}
                      </span>
                    </TableData>
                    <TableData :currentIndex="deliveryProductMappingVendIndex" :totalLength="deliveryProductMappingVends.length" inputClass="text-center">
                      <MagnifyingGlassCircleIcon class="h-5 w-5 text-green-500 hover:cursor-pointer" aria-hidden="true" v-if="deliveryProductMappingVend.deliveryProductMappingVendChannels" @click="onChannelOverviewClicked(deliveryProductMappingVend)"/>
                      <!-- <ul
                        class="sm:grid sm:grid-cols-[105px_minmax(110px,_1fr)_100px] hover:cursor-pointer"
                        v-if="deliveryProductMappingVend.deliveryProductMappingVendChannels"
                        @click="onChannelOverviewClicked(deliveryProductMappingVend)"
                        >
                            <li v-for="(channel, channelIndex) in deliveryProductMappingVend.deliveryProductMappingVendChannels"
                                class="quick-look"
                                :class="[channelIndex > 0 && (String(channel['vend_channel_code'])[0] !== String(deliveryProductMappingVend.deliveryProductMappingVendChannels[channelIndex - 1]['vend_channel_code'])[0]) ? 'col-start-1' : '']"
                            >
                            <span :class="[channelIndex > 0 && (String(channel['vend_channel_code'])[0] !== String(deliveryProductMappingVend.deliveryProductMappingVendChannels[channelIndex - 1]['vend_channel_code'])[0]) ? 'border-t-4 pt-1' : '']" class="flex space-x-2">
                                <span>
                                  #{{channel.vend_channel_code}}
                                </span>
                            </span>
                            </li>
                        </ul> -->

                    </TableData>
                    <TableData :currentIndex="deliveryProductMappingVendIndex" :totalLength="deliveryProductMappingVends.length" inputClass="text-center">
                      <div class="flex flex-col items-center space-y-1">
                        <span class="inline-flex items-center rounded-md bg-green-300 px-1.5 py-0.5 text-xs font-medium text-green-800 ring-1 ring-inset ring-indigo-700/10" v-if="deliveryProductMappingVend.is_active == 1">
                          Binded
                        </span>
                        <span class="inline-flex items-center rounded-md bg-red-200 px-1.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-indigo-700/10" v-if="deliveryProductMappingVend.is_active == 0 && !deliveryProductMappingVend.end_date">
                          Paused
                        </span>
                        <span class="inline-flex items-center rounded-md bg-red-200 px-1.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-indigo-700/10" v-if="deliveryProductMappingVend.is_active == 0 && deliveryProductMappingVend.end_date">
                          Unbinded
                        </span>
                        <span class="text-[10px] text-gray-500 whitespace-nowrap" v-if="deliveryProductMappingVend.is_active == 0 && deliveryProductMappingVend.end_date">
                          {{ deliveryProductMappingVend.end_date }}
                        </span>
                      </div>
                    </TableData>
                    <TableData :currentIndex="deliveryProductMappingVendIndex" :totalLength="deliveryProductMappingVends.length" inputClass="text-right">
                      {{ (deliveryProductMappingVend.delivery_platform_orders_sum_subtotal_amount - deliveryProductMappingVend.delivery_platform_orders_sum_promo_amount).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                    </TableData>
                    <TableData :currentIndex="deliveryProductMappingVendIndex" :totalLength="deliveryProductMappingVends.length" inputClass="text-right">
                      {{ deliveryProductMappingVend.delivery_platform_orders_count }}
                    </TableData>
                    <TableData :currentIndex="deliveryProductMappingVendIndex" :totalLength="deliveryProductMappingVends.length" inputClass="text-center">
                      <div class="flex flex-col space-y-1" v-if="!deliveryProductMappingVend.end_date">
                        <Button
                          class="flex space-x-1 w-fit"
                          :class="[deliveryProductMappingVend.is_active ? 'bg-yellow-300 hover:bg-yellow-400 text-black' : 'bg-green-500 hover:bg-green-600 text-white']"
                          @click.prevent="togglePauseVend(deliveryProductMappingVend)"
                        >
                          <PauseCircleIcon class="w-3 h-3" v-if="deliveryProductMappingVend.is_active"></PauseCircleIcon>
                          <PlayCircleIcon class="w-3 h-3" v-else></PlayCircleIcon>
                          <span class="text-xs" v-if="deliveryProductMappingVend.is_active">
                            Pause VM
                          </span>
                          <span class="text-xs" v-else>
                            Resume VM
                          </span>
                        </Button>
                        <Button
                          class="flex space-x-1 bg-red-500 hover:bg-red-600 text-white w-fit"
                          v-if="!deliveryProductMappingVend.is_active"
                          @click.prevent="unbindVend(deliveryProductMappingVend.id)"
                        >
                          <XCircleIcon class="w-3 h-3" ></XCircleIcon>
                          <span class="text-xs">Unbind VM</span>
                        </Button>
                      </div>
                    </TableData>

                    <!-- <TableData :currentIndex="deliveryProductMappingVendIndex" :totalLength="deliveryProductMappingVends.length" inputClass="text-center">
                      <div class="flex justify-center space-x-1">
                        <Link :href="'/delivery-platform-campaigns/' + deliveryProductMappingVend.id + '/edit'">
                          <Button
                            type="button" class="bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
                          >
                            <PencilSquareIcon class="w-4 h-4"></PencilSquareIcon>
                            <span>
                                Edit
                            </span>
                          </Button>
                        </Link>
                      </div>
                    </TableData> -->
                  </tr>
                  <tr v-if="!deliveryProductMappingVends.data.length">
                    <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                        No Results Found
                    </td>
                  </tr>
                </tbody>
            </table>
            <Paginator v-if="deliveryProductMappingVends.data.length" :links="deliveryProductMappingVends.links" :meta="deliveryProductMappingVends.meta"></Paginator>
          </div>
      </div>
    </div>
  </div>
  <ChannelOverview
    v-if="showChannelOverviewModal"
    :deliveryProductMappingVendModel="deliveryProductMappingVendModel"
    :showModal="showChannelOverviewModal"
    @modalClose="onChannelOverviewClosed"
  >
  </ChannelOverview>
  </BreezeAuthenticatedLayout>
</template>

<style setup>
	.quick-look
	{
		-webkit-border-horizontal-spacing: 0px;
		-webkit-border-vertical-spacing: 0px;
		border-bottom-color: white;
		border-bottom-left-radius: 3px;
		border-bottom-right-radius: 3px;
		border-bottom-style: none;
		border-width: 0px;
		border-collapse: separate;
		border-left-color: white;
		border-left-style: none;
		border-right-color: white;
		border-right-style: none;
		border-top-color: white;
		border-top-left-radius: 3px;
		border-top-right-radius: 3px;
		border-top-style: none;
		line-height: 14px;
		max-width: none;
		text-align: left;
		white-space: nowrap;
		padding:5px;
		margin:3px;
		display:block;
		float:left;
		/* width:170px; */
		font-size:13px;
	}
</style>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import ChannelOverview from '@/Pages/DeliveryProductMappingVend/ChannelOverview.vue';
import DatePicker from '@/Components/DatePicker.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import moment from 'moment';
import MultiSelect from '@/Components/MultiSelect.vue';
import { BackspaceIcon, MagnifyingGlassIcon, MagnifyingGlassCircleIcon, PauseCircleIcon, PlayCircleIcon, XCircleIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import TableData from '@/Components/TableData.vue';
import { ref, onMounted, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";

const props = defineProps({
  deliveryProductMappingVends: Object,
  deliveryPlatformTypeOptions: [Array, Object],
  deliveryProductMappingOptions: Object,
  operatorOptions: Object,
  platformRefNumberOptions: [Array, Object],
  totals: Object,
})

const filters = ref({
  vend_code: '',
  date_from: moment().startOf('week').format('YYYY-MM-DD'),
  date_to: moment().format('YYYY-MM-DD'),
  delivery_product_mapping_id: '',
  delivery_platform_type_id: '',
  operators: [],
  platform_ref_id: '',
  sortKey: '',
  sortBy: true,
  status: '',
  numberPerPage: 100,
})
const authOperator = usePage().props.auth.operator
const booleanOptions = ref([])
const deliveryPlatformTypeOptions = ref([])
const deliveryProductMapping = ref()
const deliveryProductMappingOptions = ref([])
const deliveryProductMappingVendModel = ref()
const operatorCountry = usePage().props.auth.operatorCountry
const operatorOptions = ref([])
const platformRefNumberOptions = ref([])
const selectedPlatformRefNumber = ref(null)
const numberPerPageOptions = ref([])
const showChannelOverviewModal = ref(false)
const permissions = usePage().props.auth.permissions
const vend = ref()
const toast = useToast()

onMounted(() => {
  booleanOptions.value = [
      {id: 'all', value: 'All'},
      {id: 'true', value: 'Binded'},
      {id: 'false', value: 'Unbinded'},
  ]
  numberPerPageOptions.value = [
    { id: 100, value: 100 },
    { id: 200, value: 200 },
    { id: 500, value: 500 },
    { id: 'All', value: 'All' },
  ]
  deliveryProductMappingOptions.value = [
    { id: 'all', name: 'All' },
    ...props.deliveryProductMappingOptions.data.map((data) => {
    return {id: data.id, name: data.name }})
  ]
  deliveryPlatformTypeOptions.value = [
    {id: 'all', name: 'All'},
    ...Object.keys(props.deliveryPlatformTypeOptions).map((deliveryPlatformType, index) => {return {id: deliveryPlatformType, name: deliveryPlatformType}})
  ]
  operatorOptions.value = [
		{id: 'all', full_name: 'All'},
    ...props.operatorOptions.data.map((data) => {return {id: data.id, code: data.code, full_name: data.full_name}})
  ]
  platformRefNumberOptions.value = [
    { id: 'all', ref_number: 'All' },
    ...(props.platformRefNumberOptions?.data || []).map((data) => ({ id: data.id, ref_number: data.ref_number })),
  ]

  filters.value.numberPerPage = numberPerPageOptions.value[0]
  filters.value.is_active = booleanOptions.value[1]
  filters.value.delivery_platform_type_id = deliveryPlatformTypeOptions.value.find(deliveryPlatformType => deliveryPlatformType.id === 'all')
  filters.value.delivery_product_mapping_id = deliveryProductMappingOptions.value[0]
  filters.value.operators = authOperator ? [
		operatorOptions.value.find(operator => operator.id === authOperator.id),
		...authOperator.code == 'HIPL' ? [
			operatorOptions.value.find(operator => operator.code == 'HIMD'),
			operatorOptions.value.find(operator => operator.code == 'LEA'),
            operatorOptions.value.find(operator => operator.code == 'HIESG'),
            operatorOptions.value.find(operator => operator.code == 'UL-ST'),
		] : [],
	].filter(operator => operator !== undefined) : [operatorOptions.value[0]]
  const preselectedPlatformRef = platformRefNumberOptions.value.find(option => option.ref_number === filters.value.platform_ref_id)
  selectedPlatformRefNumber.value = preselectedPlatformRef ?? platformRefNumberOptions.value[0]
})

watch(selectedPlatformRefNumber, (newValue) => {
  if (!newValue || newValue.id === 'all') {
    filters.value.platform_ref_id = ''
    return
  }
  filters.value.platform_ref_id = newValue.ref_number
})

function onChannelOverviewClicked(deliveryProductMappingVend) {
  deliveryProductMappingVendModel.value = deliveryProductMappingVend
  showChannelOverviewModal.value = true
}

function onChannelOverviewClosed() {
  showChannelOverviewModal.value = false
}

function onSearchFilterUpdated() {
  router.get('/delivery-product-mapping-vends', {
      ...filters.value,
      delivery_platform_type_id: filters.value.delivery_platform_type_id.id,
      delivery_product_mapping_id: filters.value.delivery_product_mapping_id.id,
      operators: filters.value.operators.filter(operator => operator).map((operator) => { return operator.id }),
      is_active: filters.value.is_active.id,
      numberPerPage: filters.value.numberPerPage.id,
      platform_ref_id: filters.value.platform_ref_id || undefined,
  }, {
      preserveState: true,
      replace: true,
  })
}

function resetFilters() {
  router.get('/delivery-product-mapping-vends')
}

function sortTable(sortKey, inverse = false) {
  filters.value.sortBy = !filters.value.sortBy
  if(inverse && filters.value.sortKey != sortKey) {
      filters.value.sortBy = !filters.value.sortBy
  }
  filters.value.sortKey = sortKey
  onSearchFilterUpdated()
}

function togglePauseVend(deliveryProductMappingVend) {
  let approvalText = deliveryProductMappingVend.is_active ? 'Are you sure to pause this vending machine?' : 'Are you sure to resume this vending machine?'
  const approval = confirm(approvalText);
  if (!approval) {
      return;
  }
  router.post('/delivery-product-mappings/vends/' + deliveryProductMappingVend.id + '/toggle-pause-vend', {}, {
      preserveState: false,
      preserveScroll: true,
      replace: true,
  })
}

function unbindVend(deliveryProductMappingVendId) {
  const approval = confirm('Are you sure to delete this entry?');
  if (!approval) {
      return;
  }
  router.delete('/delivery-product-mappings/unbind/' + deliveryProductMappingVendId, {
    onSuccess: () => {
      toast.success("Delivery product mapping vend unbound successfully", { timeout: 3000 })
    },
    onError: () => {
      toast.error("Failed to unbind delivery product mapping vend", { timeout: 3000 })
    },
    preserveState: true,
    replace: true,
  })
}

</script>
