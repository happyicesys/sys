<template>

  <Head title="GP by VM" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Sales Report
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
      <div class="pb-3 mb-2">
        <div class="sm:hidden">
          <label for="tabs" class="sr-only">Select a tab</label>
          <select id="tabs" name="tabs" class="block w-full rounded-md border-gray-300 py-2 pl-3 pr-10 text-base focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 sm:text-sm">
            <option v-for="tab in tabs" :key="tab.name" :selected="tab.current">
              {{ tab.name }}
            </option>
          </select>
        </div>
        <div class="hidden sm:block">
          <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
              <Link v-for="tab in tabs" :key="tab.name"
              :class="[tab.current ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-200 hover:text-gray-700', 'flex whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium']"
              :href="tab.href"
              >
                {{ tab.name }}
            </Link>
            </nav>
          </div>
<!--
            <div v-if="!item.children" class="py-1 space-y-1">
              <BreezeResponsiveNavLink
              v-if="permissions.includes(item.permission)"
              :href="route(item.href)" :active="route().current(item.href)">
                  {{ item.name }}
              </BreezeResponsiveNavLink>
            </div>

            <Disclosure as="div" v-else class="space-y-1" v-slot="{ open }">
                <DisclosureButton class="pt-2 pb-2 mb-1 pl-4 space-y-1 flex" v-if="permissions.includes(item.permission)">
                    <span class="">
                        {{ item.name }}
                    </span>
                    <svg :class="[open ? 'text-gray-400 rotate-90' : 'text-gray-300', 'ml-3 flex-shrink-0 h-5 w-5 transform group-hover:text-gray-400 transition-colors ease-in-out duration-150']"
                        viewBox="0 0 20 20" aria-hidden="true">
                        <path d="M6 6L14 10L6 14V6Z" fill="currentColor" />
                    </svg>
                </DisclosureButton>
                <DisclosurePanel class="py-1 space-y-1">
                    <Link v-for="subItem in item.children" :key="subItem.name" as="a"
                            :href="subItem.href">
                        <DisclosureButton
                            class="group w-full flex items-center pl-11 pr-2 py-3 text-sm font-medium text-gray-600 rounded-md hover:text-gray-900 hover:bg-gray-50">
                            {{ subItem.name }}
                        </DisclosureButton>
                    </Link>
                </DisclosurePanel>
            </Disclosure> -->


        </div>
        </div>
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
          <SearchInput placeholderStr="Product ID" v-model="filters.product_code" @keyup.enter="onSearchFilterUpdated()">
              Product ID
          </SearchInput>
          <SearchInput placeholderStr="Product Name" v-model="filters.product_name" @keyup.enter="onSearchFilterUpdated()">
              Product Name
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
              Filter Date
            </label>
            <MultiSelect
              v-model="filters.currentFilterDate"
              :options="reportDateOptions"
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
              v-if="permissions.includes('export excel')"
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
                  <span class="font-medium">{{ items && items.meta && items.meta.from ? items.meta.from : 0 }}</span>
                  <span>to</span>
                  <span class="font-medium">{{ items && items.meta && items.meta.to ? items.meta.to : 0 }}</span>
                  <span>of</span>
                  <span class="font-medium">{{ items && items.meta && items.meta.total ? items.meta.total : 0 }}</span>
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
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll m-2">
            <table class="min-w-full border-separate" style="border-spacing: 0">
                <thead class="bg-gray-100">
                  <tr class="divide-x divide-gray-200">
                    <TableHead>
                      #
                    </TableHead>
                    <TableHeadSort modelName="code" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('code')">
                      ID
                    </TableHeadSort>
                    <TableHead>
                      Name
                    </TableHead>
                    <TableHeadSort modelName="count" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('count')">
                      Count
                    </TableHeadSort>
                    <TableHeadSort modelName="amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('amount')">
                      Amount
                    </TableHeadSort>
                  </tr>
                  <tr class="divide-x divide-gray-200">
                    <TableHead colspan="3">
                    </TableHead>
                    <TableData inputClass="text-right font-semibold">
                      {{totals['total_count'].toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0})}}
                    </TableData>
                    <TableData inputClass="text-right font-semibold">
                      {{totals['total_amount'].toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}}
                    </TableData>
                  </tr>
                </thead>
                  <tbody class="bg-white">
                    <tr v-for="(item, itemIndex) in items.data" :key="item.id" class="divide-x divide-gray-200">
                      <TableData :currentIndex="itemIndex" :totalLength="items.length" inputClass="text-center">
                        {{ items.meta.from + itemIndex }}
                      </TableData>
                      <TableData :currentIndex="itemIndex" :totalLength="items.length" inputClass="text-center">
                        {{ item.code }}
                      </TableData>
                      <TableData :currentIndex="itemIndex" :totalLength="items.length" inputClass="text-left">
                        {{ item.name }}
                      </TableData>
                      <TableData :currentIndex="itemIndex" :totalLength="items.length" inputClass="text-right">
                        {{ item.count.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0}) }}
                      </TableData>
                      <TableData :currentIndex="itemIndex" :totalLength="items.length" inputClass="text-right">
                        {{ item.amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}
                      </TableData>
                    </tr>
                <tr v-if="!items.data.length">
                  <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                      No Results Found
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator v-if="items.data.length" :links="items.links" :meta="items.meta"></Paginator>
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
import { Head, Link, usePage } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  categories: Object,
  categoryGroups: Object,
  items: Object,
  locationTypeOptions: Object,
  reportDateOptions: Object,
  operators: Object,
  totals: [Array, Object],
})

const filters = ref({
  categories: [],
  categoryGroups: [],
  codes: '',
  currentFilterDate: '',
  customer_code: '',
  customer_name: '',
  is_binded_customer: '',
  location_type_id: '',
  operator_id: '',
  product_code: '',
  product_name: '',
  sortKey: '',
  sortBy: false,
  numberPerPage: 30,
  visited: false,
})
const booleanOptions = ref([])
const categoryOptions = ref([])
const categoryGroupOptions = ref([])
const loading = ref(false)
const locationTypeOptions = ref([])
const reportDateOptions = ref([])
const operatorOptions = ref([])
const operatorRole = usePage().props.auth.operatorRole
const numberPerPageOptions = ref([])
const permissions = usePage().props.auth.permissions
const currentUrl = ref()

const tabs = ref([
  { name: 'Operator', href: '/reports/sales/operator', current: false },
  { name: 'Vending Machines', href: '/reports/sales/vend', current: false },
  { name: 'Product', href: '/reports/sales/product', current: false },
  { name: 'Category', href: '/reports/sales/category', current: false },
  { name: 'Location Type', href: '/reports/sales/location-type', current: false },
])

onMounted(() => {
  currentUrl.value = window.location.pathname
  tabs.value.find(tab => tab.href === window.location.pathname).current = true
  filters.value.visited = true
  numberPerPageOptions.value = [
    { id: 30, value: 30 },
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
  reportDateOptions.value = props.reportDateOptions.map((data) => {return {id: data.id, name: data.name}})
  filters.value.currentFilterDate = reportDateOptions.value[0]
  operatorOptions.value = [
    {id: 'all', full_name: 'All'},
    ...props.operators.data.map((data) => {return {id: data.id, full_name: data.full_name}})
  ]
  filters.value.is_binded_customer = operatorRole.value ? booleanOptions.value[0] : booleanOptions.value[1]
  filters.value.location_type_id = locationTypeOptions.value[0]
  filters.value.operator_id = operatorOptions.value[0]
})

function onSearchFilterUpdated() {
  router.get(currentUrl.value, {
      ...filters.value,
      categories: filters.value.categories.map((category) => { return category.id }),
      categoryGroups: filters.value.categoryGroups.map((categoryGroup) => { return categoryGroup.id }),
      currentFilterDate: filters.value.currentFilterDate.id,
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
  router.get(currentUrl.value)
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
        url: currentUrl.value + 'excel',
        params: {
            ...filters.value,
            categories: filters.value.categories.map((category) => { return category.id }),
            categoryGroups: filters.value.categoryGroups.map((categoryGroup) => { return categoryGroup.id }),
            currentFilterDate: filters.value.currentFilterDate.id,
            is_binded_customer: filters.value.is_binded_customer.id,
            location_type_id: filters.value.location_type_id.id,
            operator_id: filters.value.operator_id.id,
        },
        responseType: 'blob',
    }).then(response => {
        fileDownload(response.data, 'SalesReport' + moment().format('YYMMDDhhmmss') +'.xlsx')
    }).catch(error => {
        console.log(error)
    }).finally(() => {
        loading.value = false
    })
}
</script>