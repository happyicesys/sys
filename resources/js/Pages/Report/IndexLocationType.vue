<template>

  <Head title="GP by Location Type" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Gross Profit by Location Type
      </h2>
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
          <SearchInput placeholderStr="Cust ID" v-model="filters.customer_code" v-if="permissions.includes('admin-access vends')" @keyup.enter="onSearchFilterUpdated()">
            Cust ID
          </SearchInput>
          <SearchInput placeholderStr="Cust Name" v-model="filters.customer_name" v-if="permissions.includes('admin-access vends')" @keyup.enter="onSearchFilterUpdated()">
            Cust Name
          </SearchInput>
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
              v-model="filters.operator_id"
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
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
              Location Type
            </label>
            <MultiSelect
              v-model="filters.location_type_id"
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
              @click="onExportExcelClicked()"
              >
                <ArrowDownTrayIcon v-if="!loading" class="h-4 w-4" aria-hidden="true"/>
                <svg v-if="loading" aria-hidden="true" class="mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                    <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                </svg>
                <span>
                    Export Excel
                </span>
              </Button>
            </div>
          </div>
          <div class="flex flex-col space-y-2">
              <p class="text-sm text-gray-700 leading-5 flex space-x-1">
                  <span>Showing</span>
                  <span class="font-medium">{{ locationTypes.meta.from ?? 0 }}</span>
                  <span>to</span>
                  <span class="font-medium">{{ locationTypes.meta.to ?? 0 }}</span>
                  <span>of</span>
                  <span class="font-medium">{{ locationTypes.meta.total }}</span>
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
<!--
        <dl class="mt-2 grid grid-cols-1 gap-5 sm:grid-cols-3">
          <div class="overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow">
            <dt class="truncate text-sm font-medium text-gray-500">Total Sales before GST (This Month)</dt>
            <dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
                {{totals['this_month_revenue_total'].toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}}
            </dd>
          </div>
          <div class="overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow">
            <dt class="truncate text-sm font-medium text-gray-500">Total Gross Profit (This Month)</dt>
            <dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
                {{totals['this_month_gross_profit_total'].toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}}
            </dd>
          </div>
          <div class="overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow">
            <dt class="truncate text-sm font-medium text-gray-500">Total Gross Margin (This Month)</dt>
            <dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
                {{totals['this_month_gross_margin_total'].toLocaleString(undefined, {minimumFractionDigits: 1, maximumFractionDigits: 1})}} %
            </dd>
          </div>
        </dl> -->
      </div>

      <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll m-2">
            <table class="min-w-full border-separate" style="border-spacing: 0">
                <thead class="bg-gray-100">
                  <tr class="divide-x divide-gray-200">
                    <TableHead>
                      #
                    </TableHead>
                    <TableHeadSort modelName="name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('name')">
                      Name
                    </TableHeadSort>
                    <TableHead colspan="3">
                      This Month
                    </TableHead>
                    <TableHead colspan="3">
                      Last Month
                    </TableHead>
                    <TableHead colspan="3">
                      Last 2 Month
                    </TableHead>
                  </tr>
                  <tr class="divide-x divide-gray-200">
                    <TableHead colspan="2">
                    </TableHead>
                    <TableHeadSort modelName="this_month_revenue" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('this_month_revenue')">
                      Sales ($)
                    </TableHeadSort>
                    <TableHeadSort modelName="this_month_gross_profit" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('this_month_gross_profit')">
                      GP ($)
                    </TableHeadSort>
                    <TableHeadSort modelName="this_month_gross_profit_margin" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('this_month_gross_profit_margin')">
                      GM (%)
                    </TableHeadSort>
                    <TableHeadSort modelName="last_month_revenue" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('last_month_revenue')">
                      Sales ($)
                    </TableHeadSort>
                    <TableHeadSort modelName="last_month_gross_profit" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('last_month_gross_profit')">
                      GP ($)
                    </TableHeadSort>
                    <TableHeadSort modelName="last_month_gross_profit_margin" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('last_month_gross_profit_margin')">
                      GM (%)
                    </TableHeadSort>
                    <TableHeadSort modelName="last_two_month_revenue" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('last_two_month_revenue')">
                      Sales ($)
                    </TableHeadSort>
                    <TableHeadSort modelName="last_two_month_gross_profit" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('last_two_month_gross_profit')">
                      GP ($)
                    </TableHeadSort>
                    <TableHeadSort modelName="last_two_month_gross_profit_margin" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('last_two_month_gross_profit_margin')">
                      GM (%)
                    </TableHeadSort>
                  </tr>
                  <tr class="divide-x divide-gray-200">
                    <TableHead colspan="2">
                    </TableHead>
                    <TableData inputClass="text-right font-semibold">
                      {{totals['this_month_revenue_total'].toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}}
                    </TableData>
                    <TableData inputClass="text-right font-semibold">
                      {{totals['this_month_gross_profit_total'].toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}}
                    </TableData>
                    <TableData inputClass="text-right font-semibold">
                      {{totals['this_month_gross_margin_total'].toLocaleString(undefined, {minimumFractionDigits: 1, maximumFractionDigits: 1})}}
                    </TableData>
                    <TableData inputClass="text-right font-semibold">
                      {{totals['last_month_revenue_total'].toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}}
                    </TableData>
                    <TableData inputClass="text-right font-semibold">
                      {{totals['last_month_gross_profit_total'].toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}}
                    </TableData>
                    <TableData inputClass="text-right font-semibold">
                      {{totals['last_month_gross_margin_total'].toLocaleString(undefined, {minimumFractionDigits: 1, maximumFractionDigits: 1})}}
                    </TableData>
                    <TableData inputClass="text-right font-semibold">
                      {{totals['last_two_month_revenue_total'].toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}}
                    </TableData>
                    <TableData inputClass="text-right font-semibold">
                      {{totals['last_two_month_gross_profit_total'].toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}}
                    </TableData>
                    <TableData inputClass="text-right font-semibold">
                      {{totals['last_two_month_gross_margin_total'].toLocaleString(undefined, {minimumFractionDigits: 1, maximumFractionDigits: 1})}}
                    </TableData>
                  </tr>
                </thead>
                  <tbody class="bg-white">
                    <tr v-for="(locationType, locationTypeIndex) in locationTypes.data" :key="locationType.id" class="divide-x divide-gray-200">
                      <TableData :currentIndex="locationTypeIndex" :totalLength="locationTypes.length" inputClass="text-center">
                        {{ locationTypes.meta.from + locationTypeIndex }}
                      </TableData>
                      <TableData :currentIndex="locationTypeIndex" :totalLength="locationTypes.length" inputClass="text-left">
                        {{ locationType.name }}
                      </TableData>
                      <TableData :currentIndex="locationTypeIndex" :totalLength="locationTypes.length" inputClass="text-right">
                        {{ locationType.this_month_revenue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}
                      </TableData>
                      <TableData :currentIndex="locationTypeIndex" :totalLength="locationTypes.length" inputClass="text-right">
                        {{ locationType.this_month_gross_profit.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}
                      </TableData>
                      <TableData :currentIndex="locationTypeIndex" :totalLength="locationTypes.length" inputClass="text-right">
                        {{ locationType.this_month_gross_profit_margin ?? 0 }}
                      </TableData>
                      <TableData :currentIndex="locationTypeIndex" :totalLength="locationTypes.length" inputClass="text-right">
                        {{ locationType.last_month_revenue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}
                      </TableData>
                      <TableData :currentIndex="locationTypeIndex" :totalLength="locationTypes.length" inputClass="text-right">
                        {{ locationType.last_month_gross_profit.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}
                      </TableData>
                      <TableData :currentIndex="locationTypeIndex" :totalLength="locationTypes.length" inputClass="text-right">
                        {{ locationType.last_month_gross_profit_margin ?? 0 }}
                      </TableData>
                      <TableData :currentIndex="locationTypeIndex" :totalLength="locationTypes.length" inputClass="text-right">
                        {{ locationType.last_two_month_revenue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}
                      </TableData>
                      <TableData :currentIndex="locationTypeIndex" :totalLength="locationTypes.length" inputClass="text-right">
                        {{ locationType.last_two_month_gross_profit.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}
                      </TableData>
                      <TableData :currentIndex="locationTypeIndex" :totalLength="locationTypes.length" inputClass="text-right">
                        {{ locationType.last_two_month_gross_profit_margin ?? 0 }}
                      </TableData>
                    </tr>
                <tr v-if="!locationTypes.data.length">
                  <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                      No Results Found
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator v-if="locationTypes.data.length" :links="locationTypes.links" :meta="locationTypes.meta"></Paginator>
          </div>
      </div>
    </div>
  </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { ArrowDownTrayIcon, BackspaceIcon, MagnifyingGlassIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  categories: Object,
  categoryGroups: Object,
  locationTypeOptions: Object,
  monthOptions: Object,
  operators: Object,
  totals: [Array, Object],
  locationTypes: Object,
})

const filters = ref({
  categories: [],
  categoryGroups: [],
  codes: '',
  currentMonth: '',
  customer_code: '',
  customer_name: '',
  is_binded_customer: '',
  location_type_id: '',
  operator_id: '',
  sortKey: '',
  sortBy: false,
  numberPerPage: 100,
  visited: false,
})
const booleanOptions = ref([])
const categoryOptions = ref([])
const categoryGroupOptions = ref([])
const loading = ref(false)
const locationTypeOptions = ref([])
const monthOptions = ref([])
const operatorOptions = ref([])
const operatorRole = usePage().props.auth.operatorRole
const numberPerPageOptions = ref([])
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
  filters.value.numberPerPage = numberPerPageOptions.value[0]
  booleanOptions.value = [
    {id: 'all', value: 'All'},
    {id: 'true', value: 'Yes'},
    {id: 'false', value: 'No'},
  ]
  categoryOptions.value = props.categories.data.map((data) => {return {id: data.id, name: data.name}})
  categoryGroupOptions.value = props.categoryGroups.data.map((data) => {return {id: data.id, name: data.name}})
  locationTypeOptions.value = [
    {id: 'all', value: 'All'},
    ...props.locationTypeOptions.data.map((data) => {return {id: data.id, value: data.name}})
  ]
  monthOptions.value = props.monthOptions.map((data) => {return {id: data.id, name: data.name}})
  filters.value.currentMonth = monthOptions.value[0]

  operatorOptions.value = [
    {id: 'all', full_name: 'All'},
    ...props.operators.data.map((data) => {return {id: data.id, full_name: data.full_name}})
  ]
  filters.value.is_binded_customer = operatorRole.value ? booleanOptions.value[0] : booleanOptions.value[1]
  filters.value.location_type_id = locationTypeOptions.value[0]
  filters.value.operator_id = operatorOptions.value[0]
})

function onSearchFilterUpdated() {
  router.get('/reports/location-type', {
      ...filters.value,
      categories: filters.value.categories.map((category) => { return category.id }),
      categoryGroups: filters.value.categoryGroups.map((categoryGroup) => { return categoryGroup.id }),
      currentMonth: filters.value.currentMonth.id,
      is_binded_customer: filters.value.is_binded_customer.id,
      location_type_id: filters.value.location_type_id.id,
      operator_id: filters.value.operator_id.id,
      numberPerPage: filters.value.numberPerPage.id,
  }, {
      preserveState: true,
      replace: true,
  })
}

function resetFilters() {
  router.get('/reports/location-type')
}

function sortTable(sortKey) {
  filters.value.sortKey = sortKey
  filters.value.sortBy = !filters.value.sortBy
  onSearchFilterUpdated()
}

function onExportExcelClicked() {
    loading.value = true
    axios({
        method: 'get',
        url: '/reports/location-type/excel',
        params: {
            ...filters.value,
            categories: filters.value.categories.map((category) => { return category.id }),
            categoryGroups: filters.value.categoryGroups.map((categoryGroup) => { return categoryGroup.id }),
            currentMonth: filters.value.currentMonth.id,
            is_binded_customer: filters.value.is_binded_customer.id,
            location_type_id: filters.value.location_type_id.id,
            operator_id: filters.value.operator_id.id,
        },
        responseType: 'blob',
    }).then(response => {
        fileDownload(response.data, 'UnitCostByLocationType_' + moment().format('YYMMDDhhmmss') +'.xlsx')
    }).catch(error => {
        console.log(error)
    }).finally(() => {
        loading.value = false
    })
}
</script>