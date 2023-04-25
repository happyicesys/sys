<template>

  <Head title="GP by Product" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Gross Profit by Product
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
          <SearchInput placeholderStr="Cust Name" v-model="filters.customer_name" v-if="permissions.includes('admin-access products')" @keyup.enter="onSearchFilterUpdated()">
            Cust Name
          </SearchInput>
          <div v-if="permissions.includes('admin-access products')">
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
          <div v-if="permissions.includes('admin-access products')">
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
            </div>
          </div>
          <div class="flex flex-col space-y-2">
              <p class="text-sm text-gray-700 leading-5 flex space-x-1">
                  <span>Showing</span>
                  <span class="font-medium">{{ products.meta.from ?? 0 }}</span>
                  <span>to</span>
                  <span class="font-medium">{{ products.meta.to ?? 0 }}</span>
                  <span>of</span>
                  <span class="font-medium">{{ products.meta.total }}</span>
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
                      Code
                    </TableHeadSort>
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
                    <TableHead colspan="3">
                    </TableHead>
                    <TableHeadSort modelName="this_month_revenue" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('this_month_revenue')">
                      Sales($)
                    </TableHeadSort>
                    <TableHeadSort modelName="this_month_gross_profit" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('this_month_gross_profit')">
                      GM($)
                    </TableHeadSort>
                    <TableHead>
                      GM(%)
                    </TableHead>
                    <TableHeadSort modelName="last_month_revenue" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('last_month_revenue')">
                      Sales($)
                    </TableHeadSort>
                    <TableHeadSort modelName="last_month_gross_profit" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('last_month_gross_profit')">
                      GM($)
                    </TableHeadSort>
                    <TableHead>
                      GM(%)
                    </TableHead>
                    <TableHeadSort modelName="last_two_month_revenue" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('last_two_month_revenue')">
                      Sales($)
                    </TableHeadSort>
                    <TableHeadSort modelName="last_two_month_gross_profit" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('last_two_month_gross_profit')">
                      GM($)
                    </TableHeadSort>
                    <TableHead>
                      GM(%)
                    </TableHead>
                  </tr>
                </thead>
                  <tbody class="bg-white">
                    <tr v-for="(product, vendIndex) in products.data" :key="product.id" class="divide-x divide-gray-200">
                      <TableData :currentIndex="vendIndex" :totalLength="products.length" inputClass="text-center">
                        {{ products.meta.from + vendIndex }}
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="products.length" inputClass="text-center">
                        {{ product.code }}
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="products.length" inputClass="text-left">
                        {{ product.name }}
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="products.length" inputClass="text-right">
                        {{ product.this_month_revenue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="products.length" inputClass="text-right">
                        {{ product.this_month_gross_profit.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="products.length" inputClass="text-right">
                        {{ (product.this_month_gross_profit/ product.this_month_revenue) ? (product.this_month_gross_profit/ product.this_month_revenue * 100).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0}) : 0 }}
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="products.length" inputClass="text-right">
                        {{ product.last_month_revenue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="products.length" inputClass="text-right">
                        {{ product.last_month_gross_profit.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="products.length" inputClass="text-right">
                        {{ (product.last_month_gross_profit/ product.last_month_revenue) ? (product.last_month_gross_profit/ product.last_month_revenue * 100).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0}) : 0 }}
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="products.length" inputClass="text-right">
                        {{ product.last_two_month_revenue.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="products.length" inputClass="text-right">
                        {{ product.last_two_month_gross_profit.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="products.length" inputClass="text-right">
                        {{ (product.last_two_month_gross_profit/ product.last_two_month_revenue) ? (product.last_two_month_gross_profit/ product.last_two_month_revenue * 100).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0}) : 0 }}
                      </TableData>
                    </tr>
                <tr v-if="!products.data.length">
                  <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                      No Results Found
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator v-if="products.data.length" :links="products.links" :meta="products.meta"></Paginator>
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
import { BackspaceIcon, MagnifyingGlassIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { Head, usePage } from '@inertiajs/inertia-vue3';
import { ref, onMounted } from 'vue';
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  categories: Object,
  monthOptions: Object,
  operators: Object,
  products: Object,
})

const filters = ref({
  categories: [],
  codes: '',
  currentMonth: '',
  customer_name: '',
  operator: '',
  sortKey: '',
  sortBy: true,
  numberPerPage: 100,
})
const categoryOptions = ref([])
const monthOptions = ref([])
const operatorOptions = ref([])
const type = ref('')
const numberPerPageOptions = ref([])
const permissions = usePage().props.value.auth.permissions

onMounted(() => {
  numberPerPageOptions.value = [
    { id: 50, value: 50 },
    { id: 100, value: 100 },
    { id: 200, value: 200 },
    { id: 500, value: 500 },
    { id: 'All', value: 'All' },
  ]
  filters.value.numberPerPage = numberPerPageOptions.value[0]

  categoryOptions.value = props.categories.data.map((data) => {return {id: data.id, name: data.name}})
  monthOptions.value = props.monthOptions.map((data) => {return {id: data.id, name: data.name}})
  filters.value.currentMonth = monthOptions.value[0]

  operatorOptions.value = [
    {id: 'all', full_name: 'All'},
    ...props.operators.data.map((data) => {return {id: data.id, full_name: data.full_name}})
  ]
  filters.value.operator = operatorOptions.value[0]
})

function onSearchFilterUpdated() {
  Inertia.get('/reports/product', {
      ...filters.value,
      numberPerPage: filters.value.numberPerPage.id,
  }, {
      preserveState: true,
      replace: true,
  })
}

function resetFilters() {
  Inertia.get('/reports/product')
}

function sortTable(sortKey) {
  filters.value.sortKey = sortKey
  filters.value.sortBy = !filters.value.sortBy
  onSearchFilterUpdated()
}
</script>