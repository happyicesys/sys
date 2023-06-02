
<template>
  <Head title="Month End Stock Count" />

  <BreezeAuthenticatedLayout>
      <template #header>
          <h2 class="font-semibold text-xl text-gray-800 leading-tight">
              Month End Stock Count
          </h2>
          <p class="text-xs">
            (Machine inventories will be captured on the last day of every month)
          </p>
      </template>

      <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
          <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
              <SearchInput placeholderStr="Vend ID" v-model="filters.codes" @keyup.enter="onSearchFilterUpdated()">
                  Vend ID
                  <span class="text-[9px]">
                      ("," for multiple)
                  </span>
              </SearchInput>
              <SearchInput placeholderStr="Channel ID" v-model="filters.channel_codes" @keyup.enter="onSearchFilterUpdated()">
                  Channel ID
                  <span class="text-[9px]">
                      ("," for multiple)
                  </span>
              </SearchInput>
              <SearchInput placeholderStr="Cust ID" v-model="filters.customer_code" v-if="permissions.includes('admin-access vends')" @keyup.enter="onSearchFilterUpdated()">
                  Cust ID
              </SearchInput>
              <SearchInput placeholderStr="Cust Name" v-model="filters.customer_name" v-if="permissions.includes('admin-access vends')" @keyup.enter="onSearchFilterUpdated()">
                  Cust Name
              </SearchInput>
              <div v-if="permissions.includes('admin-access vends')">
                  <label for="text" class="block text-sm font-medium text-gray-700">
                      Category
                  </label>
                  <MultiSelect
                      v-model="filters.categories"
                      :options="categoryOptions"
                      trackBy="id"
                      valueProp="id"
                      label="name"
                      mode="tags"
                      placeholder="Select"
                      open-direction="bottom"
                      class="mt-1"
                  >
                  </MultiSelect>
              </div>
              <div v-if="permissions.includes('admin-access vends')">
                  <label for="text" class="block text-sm font-medium text-gray-700">
                      Group
                  </label>
                  <MultiSelect
                      v-model="filters.categoryGroups"
                      :options="categoryGroupOptions"
                      trackBy="id"
                      valueProp="id"
                      label="name"
                      mode="tags"
                      placeholder="Select"
                      open-direction="bottom"
                      class="mt-1"
                  >
                  </MultiSelect>
              </div>
              <div v-if="permissions.includes('admin-access vends')">
                  <label for="text" class="block text-sm font-medium text-gray-700">
                      Customer Binded?
                  </label>
                  <MultiSelect
                      v-model="filters.is_binded_customer"
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
              <div v-if="permissions.includes('admin-access vends')">
                  <label for="text" class="block text-sm font-medium text-gray-700">
                      Operator
                  </label>
                  <MultiSelect
                      v-model="filters.operator"
                      :options="operatorOptions"
                      trackBy="id"
                      valueProp="id"
                      label="full_name"
                      placeholder="Select"
                      open-direction="bottom"
                      class="mt-1"
                  >
                  </MultiSelect>
              </div>
              <div>
                  <label for="text" class="block text-sm font-medium text-gray-700">
                      Location Type
                  </label>
                  <MultiSelect
                      v-model="filters.locationType"
                      :options="locationTypeOptions"
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
                <label for="text" class="block text-sm font-medium text-gray-700">
                  Current Month
                </label>
                <MultiSelect
                  v-model="filters.currentMonth"
                  :options="monthOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
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
                      v-if="permissions.includes('export excel')"
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
                      <Button class="inline-flex space-x-1 items-center rounded-md border border-gray-600 bg-white px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                      @click="onExportChannelExcelClicked()"
                      >
                          <ArrowDownTrayIcon v-if="!loading" class="h-4 w-4" aria-hidden="true"/>
                          <svg v-if="loading" aria-hidden="true" class="mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                              <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                              <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                          </svg>
                          <span>
                              Export Channels Excel
                          </span>
                      </Button>
                  </div>
              </div>
              <div class="flex flex-col space-y-1">
                  <p class="text-sm text-gray-700 leading-5 flex space-x-1">
                      <span>Showing</span>
                      <span class="font-medium">{{ vendSnapshots.meta.from ?? 0 }}</span>
                      <span>to</span>
                      <span class="font-medium">{{ vendSnapshots.meta.to ?? 0 }}</span>
                      <span>of</span>
                      <span class="font-medium">{{ vendSnapshots.meta.total }}</span>
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
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll">
              <table class="min-w-full border-separate" style="border-spacing: 0">
                  <thead class="bg-gray-100">
                      <tr class="divide-x divide-gray-200">
                          <TableHead>
                              #
                          </TableHead>
                          <TableHeadSort modelName="vend_snapshots.month_number" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_snapshots.month_number')">
                            Month
                          </TableHeadSort>
                          <TableHeadSort modelName="vend_snapshots.created_at" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_snapshots.created_at')">
                              Capture Datetime
                          </TableHeadSort>
                          <TableHeadSort modelName="vend_snapshots.vend_code" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_snapshots.vend_code')">
                              ID
                          </TableHeadSort>
                          <TableHead>
                              Name
                          </TableHead>
                          <TableHead>
                              Inventory Status <br>
                              (#Channel, Sold, Balance/Capacity)
                          </TableHead>
                          <TableHead>
                              Status
                          </TableHead>
                      </tr>
                  </thead>
                  <tbody class="bg-white">
                      <tr v-for="(vendSnapshot, vendIndex) in vendSnapshots.data" :key="vendIndex"
                          class="divide-x divide-gray-200">
                          <TableData :currentIndex="vendIndex" :totalLength="vendSnapshots.length" inputClass="text-center">
                              {{ vendSnapshots.meta.from + vendIndex }}
                          </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vendSnapshots.length" inputClass="text-center">
                                {{ vendSnapshot.month_number }}
                            </TableData>
                          <TableData :currentIndex="vendIndex" :totalLength="vendSnapshots.length" inputClass="text-center">
                            {{ vendSnapshot.created_at }}
                          </TableData>
                          <TableData :currentIndex="vendIndex" :totalLength="vendSnapshots.length" inputClass="text-center">
                              {{ vendSnapshot.vend_code }}
                          </TableData>
                          <TableData :currentIndex="vendIndex" :totalLength="vendSnapshots.length" inputClass="text-left">
                              <span v-if="vendSnapshot.customer_code">
                                  <span v-if="permissions.includes('admin-access vendSnapshots')">
                                      <a class="text-blue-700" target="_blank" :href="'//admin.happyice.com.sg/person/vendSnapshot-code/' + vendSnapshot.customer_code">
                                          {{ vendSnapshot.customer_code }} <br>
                                          {{ vendSnapshot.customer_name }}
                                      </a>
                                  </span>
                                  <span v-else>
                                      {{ vendSnapshot.customer_code }} <br>
                                      {{ vendSnapshot.customer_name }}
                                  </span>
                              </span>
                              <span v-else>
                                  {{ vendSnapshot.vend_name }}
                              </span>
                          </TableData>
                          <TableData :currentIndex="vendIndex" :totalLength="vendSnapshots.length" inputClass="text-left">
                              <ul
                              class="sm:grid sm:grid-cols-3 p-1 hover:cursor-pointer"
                              v-if="vendSnapshot.vendChannelsJson"
                              @click="onChannelOverviewClicked(vendSnapshot)"
                              >
                                  <li v-for="(channel, channelIndex) in vendSnapshot.vendChannelsJson"
                                      class="quick-look"
                                      :class="[channelIndex > 0 && (String(channel['code'])[0] !== String(vendSnapshot.vendChannelsJson[channelIndex - 1]['code'])[0]) ? 'col-start-1' : '']"
                                  >
                                  <span :class="[channelIndex > 0 && (String(channel['code'])[0] !== String(vendSnapshot.vendChannelsJson[channelIndex - 1]['code'])[0]) ? 'border-t-4 pt-1' : '']">
                                      <span>
                                          #{{channel['code']}},
                                      </span>
                                      <span class="text-blue-600">
                                          {{channel['capacity'] - channel['qty']}},
                                      </span>
                                      <span :class="[channel['qty'] <= 2 ? 'text-red-700' : 'text-green-700']">
                                          {{channel['qty']}}/{{channel['capacity']}}
                                      </span>
                                  </span>
                                  </li>
                              </ul>
                          </TableData>

                          <TableData :currentIndex="vendIndex" :totalLength="vendSnapshots.length" inputClass="text-center">
                              <div class="flex flex-col space-y-1">
                                  <div
                                      class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                                      :class="[vendSnapshot.parameterJson['Sensor'] % 2 == 0 ? 'bg-red-200' : 'bg-green-200']"
                                      v-if="vendSnapshot.parameterJson"
                                  >
                                      <div class="flex flex-col">
                                          <span class="font-bold">
                                              Drop Sensor
                                          </span>
                                          <span>
                                              {{vendSnapshot.parameterJson['Sensor'] % 2 == 0 ? 'Disabled' : 'Enabled'}}
                                          </span>
                                      </div>
                                  </div>
                                  <div
                                      class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full bg-green-200"
                                      v-if="vendSnapshot.parameterJson && 'fan' in vendSnapshot.parameterJson"
                                  >
                                      <div class="flex flex-col">
                                          <span class="font-bold">
                                              Fan Speed
                                          </span>
                                          <span>
                                              {{vendSnapshot.parameterJson['fan']}}
                                          </span>
                                      </div>
                                  </div>
                                  <div
                                      class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                                      :class="[vendSnapshot.parameterJson['door'] == 'close' ? 'bg-green-200' : 'bg-red-200']"
                                      v-if="vendSnapshot.parameterJson && vendSnapshot.parameterJson['door']"
                                  >
                                      <div class="flex flex-col">
                                          <span class="font-bold">
                                              Door
                                          </span>
                                          <span>
                                              {{vendSnapshot.parameterJson['door'] == 'open' ? 'Open' : 'Close'}}
                                          </span>
                                      </div>
                                  </div>
                                  <div
                                      class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                                      :class="[vendSnapshot.parameterJson['CoinCnt'] > 1600 ? 'bg-green-200' : 'bg-red-200']"
                                      v-if="vendSnapshot.parameterJson && vendSnapshot.parameterJson['CoinCnt']"
                                  >
                                      <div class="flex flex-col">
                                          <span class="font-bold">
                                              Coin
                                          </span>
                                          <span>
                                              {{(vendSnapshot.parameterJson['CoinCnt']/ 100).toFixed(2)}}
                                          </span>
                                      </div>
                                  </div>
                              </div>
                          </TableData>
                      </tr>
                      <tr v-if="!vendSnapshots.data.length">
                          <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                              No Results Found
                          </td>
                      </tr>
                  </tbody>
              </table>
          </div>
          <Paginator v-if="vendSnapshots.data.length" :links="vendSnapshots.links" :meta="vendSnapshots.meta"></Paginator>
      </div>
      </div>
  </div>
  <ChannelOverview
      v-if="showChannelOverviewModal"
      :vend="vendSnapshot"
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
import ChannelOverview from '@/Pages/Vend/ChannelOverview.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { ArrowDownTrayIcon, MagnifyingGlassIcon, BackspaceIcon} from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { Head, usePage } from '@inertiajs/vue3';
import moment from 'moment';
import axios from 'axios';

const props = defineProps({
  categories: Object,
  categoryGroups: Object,
  locationTypeOptions: Object,
  monthOptions: Object,
  operatorOptions: Object,
  vendSnapshots: Object,
})

const filters = ref({
  codes: '',
  channel_codes: '',
  currentMonth: '',
  customer_code: '',
  customer_name: '',
  categories: [],
  categoryGroups: [],
  locationType: '',
  operator: '',
  is_binded_customer: '',
  sortKey: '',
  sortBy: false,
  numberPerPage: '',
  visited: true,
})

const booleanOptions = ref([])
const categoryOptions = ref([])
const categoryGroupOptions = ref([])
const enableOptions = ref([])
const loading = ref(false)
const locationTypeOptions = ref([])
const monthOptions = ref([])
const numberPerPageOptions = ref([])
const operatorOptions = ref([])
const showChannelOverviewModal = ref(false)
const vendSnapshot = ref()
const vendChannelErrorsOptions = ref([])
const operatorRole = usePage().props.auth.operatorRole
const permissions = usePage().props.auth.permissions

onMounted(() => {
  filters.value.visited = true
  numberPerPageOptions.value = [
      { id: 50, value: 50 },
      { id: 100, value: 100 },
      { id: 200, value: 200 },
      { id: 500, value: 500 },
      { id: 'All', value: 'All' },
  ]
  filters.value.vend_channel_error_id = vendChannelErrorsOptions.value[0]
  filters.value.numberPerPage = numberPerPageOptions.value[0]

  categoryOptions.value = props.categories.data.map((data) => {return {id: data.id, name: data.name}})
  categoryGroupOptions.value = props.categoryGroups.data.map((data) => {return {id: data.id, name: data.name}})
  booleanOptions.value = [
      {id: 'all', value: 'All'},
      {id: 'true', value: 'Yes'},
      {id: 'false', value: 'No'},
  ]
  enableOptions.value = [
      {id: 'all', value: 'All'},
      {id: 'true', value: 'Enabled'},
      {id: 'false', value: 'Disabled'},
  ]
  locationTypeOptions.value = [
      {id: 'all', value: 'All'},
      ...props.locationTypeOptions.data.map((data) => {return {id: data.id, value: data.name}})
  ]
  operatorOptions.value = [
      {id: 'all', full_name: 'All'},
      ...props.operatorOptions.data.map((data) => {return {id: data.id, full_name: data.full_name}})
  ]
  monthOptions.value = props.monthOptions.map((data) => {return {id: data.id, name: data.name}})

  filters.value.currentMonth = monthOptions.value[1]
  filters.value.is_binded_customer = operatorRole.value ? booleanOptions.value[0] : booleanOptions.value[1]
  filters.value.locationType = locationTypeOptions.value[0]
  filters.value.operator = operatorOptions.value[0]

  // vendOptions.value = props.vendOptions.data.map((vendSnapshot) => {return {id: vendSnapshot.id, code: vendSnapshot.code}})
})

  function onChannelOverviewClicked(vendData) {
      vendSnapshot.value = vendData
      showChannelOverviewModal.value = true
  }

  function onChannelOverviewClosed() {
      showChannelOverviewModal.value = false
  }

  function onSearchFilterUpdated() {

      router.get('/reports/stock-count', {
          ...filters.value,
          categories: filters.value.categories.map((category) => { return category.id }),
          categoryGroups: filters.value.categoryGroups.map((categoryGroup) => { return categoryGroup.id }),
          currentMonth: filters.value.currentMonth.id,
          location_type_id: filters.value.locationType.id,
          operator_id: filters.value.operator.id,
          is_binded_customer: filters.value.is_binded_customer.id,
          numberPerPage: filters.value.numberPerPage.id,
      }, {
          preserveState: true,
          replace: true,
      })
  }

  function resetFilters() {
      router.get('/reports/stock-count')
  }

function sortTable(sortKey) {
  filters.value.sortKey = sortKey
  filters.value.sortBy = !filters.value.sortBy
  onSearchFilterUpdated()
}

function onExportChannelExcelClicked() {
  loading.value = true
  axios({
      method: 'get',
      url: '/reports/stock-count/excel',
      params: {
          ...filters.value,
          categories: filters.value.categories.map((category) => { return category.id }),
          categoryGroups: filters.value.categoryGroups.map((categoryGroup) => { return categoryGroup.id }),
          currentMonth: filters.value.currentMonth.id,
          location_type_id: filters.value.locationType.id,
          operator_id: filters.value.operator.id,
          is_binded_customer: filters.value.is_binded_customer.id,
      },
      responseType: 'blob',
  }).then(response => {
      fileDownload(response.data, 'Vending_Channels_' + moment().format('YYMMDDhhmmss') +'.xlsx')
  }).catch(error => {
      console.log(error)
  }).finally(() => {
      loading.value = false
  })
}
</script>