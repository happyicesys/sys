<template>

  <Head title="Delivery Platform Vend" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Delivery Platform Vend
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
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
          <SearchInput placeholderStr="Vend ID" v-model="filters.vend_code">
            Vend ID
          </SearchInput>
          <SearchInput placeholderStr="Platform ID" v-model="filters.platform_ref_id">
            Platform ID
          </SearchInput>
          <div>
              <label for="text" class="block text-sm font-medium text-gray-700">
                  Is Active?
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
                    <TableHead>
                      Vend ID
                    </TableHead>
                    <TableHead>
                      Platform ID
                    </TableHead>
                    <TableHead>
                      Cust Prefix
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
                    <TableHead>
                      Amount
                    </TableHead>
                    <TableHead>
                      Count
                    </TableHead>
                    <TableHead>
                    </TableHead>
                  </tr>
                </thead>
                <tbody class="bg-white">
                  <tr v-for="(deliveryProductMappingVend, deliveryProductMappingVendIndex) in deliveryProductMappingVends.data" :key="deliveryProductMappingVend.id" class="divide-x divide-gray-200">
                    <TableData :currentIndex="deliveryProductMappingVendIndex" :totalLength="deliveryProductMappingVends.length" inputClass="text-center">
                      {{ deliveryProductMappingVends.meta.from + deliveryProductMappingVendIndex }}
                    </TableData>
                    <TableData :currentIndex="deliveryProductMappingVendIndex" :totalLength="deliveryProductMappingVends.length" inputClass="text-center">
                      {{ deliveryProductMappingVend.vend.code }}
                    </TableData>
                    <TableData :currentIndex="deliveryProductMappingVendIndex" :totalLength="deliveryProductMappingVends.length" inputClass="text-left">
                      {{ deliveryProductMappingVend.platform_ref_id }}
                      <span class="inline-flex items-center rounded-md bg-blue-100 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-800/10" v-if="deliveryProductMappingVend.binded_times > 1">
                        {{ deliveryProductMappingVend.binded_times }}
                      </span>
                    </TableData>
                    <TableData :currentIndex="deliveryProductMappingVendIndex" :totalLength="deliveryProductMappingVends.length" inputClass="text-center">
                      <span v-if="deliveryProductMappingVend.vend && deliveryProductMappingVend.vend.customer">
                        {{ deliveryProductMappingVend.vend.customer.virtual_customer_code }} ({{ deliveryProductMappingVend.vend.customer.virtual_customer_prefix }})
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
                      <span class="inline-flex items-center rounded-md bg-green-300 px-1.5 py-0.5 text-xs font-medium text-green-800 ring-1 ring-inset ring-indigo-700/10" v-if="deliveryProductMappingVend.is_active == 1">
                        Operating
                      </span>
                      <span class="inline-flex items-center rounded-md bg-red-200 px-1.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-indigo-700/10" v-if="deliveryProductMappingVend.is_active == 0">
                        Paused
                      </span>
                    </TableData>
                    <TableData :currentIndex="deliveryProductMappingVendIndex" :totalLength="deliveryProductMappingVends.length" inputClass="text-right">
                      {{ deliveryProductMappingVend.delivery_platform_orders_sum_subtotal_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                    </TableData>
                    <TableData :currentIndex="deliveryProductMappingVendIndex" :totalLength="deliveryProductMappingVends.length" inputClass="text-right">
                      {{ deliveryProductMappingVend.delivery_platform_orders_count }}
                    </TableData>
                    <TableData :currentIndex="deliveryProductMappingVendIndex" :totalLength="deliveryProductMappingVends.length" inputClass="text-center">
                      <div class="flex flex-col space-y-1">
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
		-webkit-border-image: none;
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
		vertical-align: baseline;
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
import TableData from '@/Components/TableData.vue';
import { ref, onMounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
  deliveryProductMappingVends: Object,
  deliveryPlatformOperatorOptions: Object,
  deliveryProductMappingOptions: Object,
})

const filters = ref({
  vend_code: '',
  date_from: moment().startOf('week').format('YYYY-MM-DD'),
  date_to: moment().format('YYYY-MM-DD'),
  delivery_platform_operator_id: '',
  delivery_product_mapping_id: '',
  platform_ref_id: '',
  sortKey: '',
  sortBy: false,
  status: '',
  numberPerPage: 100,
})
const booleanOptions = ref([])
const deliveryPlatformOperatorOptions = ref([])
const deliveryProductMapping = ref()
const deliveryProductMappingOptions = ref([])
const deliveryProductMappingVendModel = ref()
const operatorCountry = usePage().props.auth.operatorCountry
const numberPerPageOptions = ref([])
const showChannelOverviewModal = ref(false)
const permissions = usePage().props.auth.permissions
const vend = ref()

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

  deliveryProductMappingOptions.value = [
    { id: 'all', name: 'All' },
    ...props.deliveryProductMappingOptions.data.map((data) => {
    return {id: data.id, name: data.name }})
  ]
  filters.value.is_active = booleanOptions.value[1]
  filters.value.delivery_platform_operator_id = deliveryPlatformOperatorOptions.value[2]
  filters.value.delivery_product_mapping_id = deliveryProductMappingOptions.value[0]
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
      delivery_platform_operator_id: filters.value.delivery_platform_operator_id.id,
      delivery_product_mapping_id: filters.value.delivery_product_mapping_id.id,
      is_active: filters.value.is_active.id,
      numberPerPage: filters.value.numberPerPage.id,
  }, {
      preserveState: true,
      replace: true,
  })
}

function resetFilters() {
  router.get('/delivery-product-mapping-vends')
}

function sortTable(sortKey) {
  filters.value.sortKey = sortKey
  filters.value.sortBy = !filters.value.sortBy
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
      preserveState: false,
      preserveScroll: true,
      replace: true,
      onSuccess: () => {
        router.reload({only: ['unbindedVendOptions']})
        unbindedVendOptions.value = props.unbindedVendOptions ? props.unbindedVendOptions.data : []
      },
  })
}

</script>