
<template>
  <Head title="Daily Stock Count" />

  <BreezeAuthenticatedLayout>
      <template #header>
          <h2 class="font-semibold text-xl text-gray-800 leading-tight">
              Daily Stock Count
          </h2>
      </template>

      <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
          <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
              <SearchInput placeholderStr="Machine ID" v-model="filters.codes" @keyup.enter="onSearchFilterUpdated()">
                Machine ID
                  <span class="text-[9px]">
                      ("," for multiple)
                  </span>
              </SearchInput>
              <SearchInput placeholderStr="Customer" v-model="filters.customer" @keyup.enter="onSearchFilterUpdated()">
                  Customer
              </SearchInput>
              <div>
                <label for="text" class="block text-sm font-medium text-gray-700">
                    Machine Prefix
                </label>
                <MultiSelect
                    v-model="filters.vendPrefixes"
                    :options="vendPrefixOptions"
                    trackBy="id"
                    valueProp="id"
                    label="value"
                    placeholder="Select"
                    open-direction="bottom"
                    class="mt-1"
                    mode="tags"
                >
                </MultiSelect>
            </div>
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
                <label for="text" class="block text-sm font-medium text-gray-700">
                    Product
                </label>
                <MultiSelect
                    v-model="filters.products"
                    :options="productOptions"
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
              <div v-if="filters.currentFilterDate.id != '-1'">
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
              <!-- <div v-if="filters.currentFilterDate.id == '-1'">
                <DatePicker
                    v-model="filters.date_from"
                >
                    Date From
                </DatePicker>
              </div>
              <div v-if="filters.currentFilterDate.id == '-1'">
                <DatePicker
                    v-model="filters.date_to"
                    :minDate="filters.date_from"
                >
                    Date To
                </DatePicker>
              </div> -->
              <div v-if="filters.currentFilterDate.id == '-1'">
                <DatePicker
                    v-model="filters.date"
                >
                    Date
                </DatePicker>
              </div>
          </div>

          <div class="flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5">
              <div class="mt-3">
                  <div class="flex space-x-1">
                      <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                      @click="onSearchFilterUpdated()"
                      v-if="permissions.includes('export reports')"
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
                      @click="onExportExcelClicked()">
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
              <div class="flex flex-col space-y-1">
                <p class="text-sm text-gray-700 leading-5 flex space-x-1">
                    <span>Showing</span>
                    <span class="font-medium">{{ stockCounts.meta?.from ?? 0 }}</span>
                    <span>to</span>
                    <span class="font-medium">{{ stockCounts.meta?.to ?? 0 }}</span>
                    <span>of</span>
                    <span class="font-medium">{{ stockCounts.meta?.total ?? 0 }}</span>
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
          <dl class="grid grid-cols-1 md:grid-cols-4 gap-2">
            <div class="col-span-1 overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow">
                <dt class="truncate text-sm font-medium text-gray-500">Cash Sales ({{ operatorCountry.currency_symbol }})</dt>
                <dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
                    {{((totals['cash_sales_amount'] ? totals['cash_sales_amount'] : 0)/ (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
                </dd>
            </div>
            <div class="col-span-1 overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow">
                <dt class="truncate text-sm font-medium text-gray-500">Cashless Sales ({{ operatorCountry.currency_symbol }})</dt>
                <dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
                    {{((totals['cashless_sales_amount'] ? totals['cashless_sales_amount'] : 0)/ (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
                </dd>
            </div>
            <div class="col-span-1 overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow">
                <dt class="truncate text-sm font-medium text-gray-500">Coin Float ({{ operatorCountry.currency_symbol }})</dt>
                <dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
                    {{((totals['coin_float_amount'] ? totals['coin_float_amount'] : 0)/ (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
                </dd>
            </div>
        </dl>
      </div>

       <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll">
              <table class="min-w-full border-separate" style="border-spacing: 0">
                <thead class="bg-gray-100">
                    <!-- Row 1: static cols + day groups -->
                    <tr class="divide-x divide-gray-200">
                        <TableHead>#</TableHead>

                        <TableHeadSort
                        modelName="product_code"
                        :sortKey="filters.sortKey"
                        :sortBy="filters.sortBy"
                        @sort-table="sortTable('product_code')"
                        >
                        Product ID
                        </TableHeadSort>

                        <TableHead>Product Name</TableHead>

                        <TableHead class="text-center" :colspan="4" :title="dayTitle(pivotDates?.d0)">
                        {{ dayLabel('d0') }} <br>
                        {{ dayTitle(pivotDates?.d0) }}
                        </TableHead>
                        <th class="w-2 bg-gray-300 p-1" aria-hidden="true"></th> <!-- spacer -->
                        <TableHead class="text-center" :colspan="4" :title="dayTitle(pivotDates?.d1)">
                        {{ dayLabel('d1') }} <br>
                        {{ dayTitle(pivotDates?.d1) }}
                        </TableHead>
                        <th class="w-2 bg-gray-300 p-1" aria-hidden="true"></th> <!-- spacer -->
                        <TableHead class="text-center" :colspan="4" :title="dayTitle(pivotDates?.d2)">
                        {{ dayLabel('d2') }}<br>
                        {{ dayTitle(pivotDates?.d2) }}
                        </TableHead>
                        <th class="w-2 bg-gray-300 p-1" aria-hidden="true"></th> <!-- spacer -->

                    </tr>

                    <!-- Row 2: sub-headers per group -->
                    <tr class="divide-x divide-gray-200">
                        <th></th><th></th><th></th>

                        <!-- D0 -->
                        <TableHeadSort modelName="stock_value_d0" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('stock_value_d0')">
                        Stock Value $
                        </TableHeadSort>
                        <TableHeadSort modelName="qty_vend_d0" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('qty_vend_d0')">
                        Qty in Machine
                        </TableHeadSort>
                        <TableHeadSort modelName="qty_warehouse_d0" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('qty_warehouse_d0')">
                        Qty in Warehouse
                        </TableHeadSort>
                        <TableHeadSort class="border-block" modelName="stock_cost_d0" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('stock_cost_d0')">
                        Stock cost $
                        </TableHeadSort>
                        <th class="w-2 bg-gray-300 p-0" aria-hidden="true"></th> <!-- spacer -->

                        <!-- D1 -->
                        <TableHeadSort modelName="stock_value_d1" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('stock_value_d1')">
                        Stock Value $
                        </TableHeadSort>
                        <TableHeadSort modelName="qty_vend_d1" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('qty_vend_d1')">
                        Qty in Machine
                        </TableHeadSort>
                        <TableHeadSort modelName="qty_warehouse_d1" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('qty_warehouse_d1')">
                        Qty in Warehouse
                        </TableHeadSort>
                        <TableHeadSort class="border-block" modelName="stock_cost_d1" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('stock_cost_d1')">
                        Stock cost $
                        </TableHeadSort>
                        <th class="w-2 bg-gray-300 p-0" aria-hidden="true"></th> <!-- spacer -->

                        <!-- D2 -->
                        <TableHeadSort modelName="stock_value_d2" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('stock_value_d2')">
                        Stock Value $
                        </TableHeadSort>
                        <TableHeadSort modelName="qty_vend_d2" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('qty_vend_d2')">
                        Qty in Machine
                        </TableHeadSort>
                        <TableHeadSort modelName="qty_warehouse_d2" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('qty_warehouse_d2')">
                        Qty in Warehouse
                        </TableHeadSort>
                        <TableHeadSort class="border-block" modelName="stock_cost_d2" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('stock_cost_d2')">
                        Stock cost $
                        </TableHeadSort>
                        <th class="w-2 bg-gray-300 p-0" aria-hidden="true"></th> <!-- spacer -->
                    </tr>
                </thead>

                <tbody class="bg-white">
                    <tr
                        v-for="(row, idx) in stockCounts.data"
                        :key="row.product_id"
                        class="divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100"
                    >
                        <TableData inputClass="text-center">
                            {{ (stockCounts.meta?.from ?? stockCounts.from ?? 1) - 1 + idx + 1 }}
                        </TableData>

                        <TableData inputClass="text-center">{{ row.product_code }}</TableData>
                        <TableData inputClass="text-left">{{ row.product_name }}</TableData>

                        <!-- D0 -->
                        <TableData inputClass="text-right">
                            {{(row.stock_value_d0 ?? 0).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
                        </TableData>
                        <TableData inputClass="text-right">
                            {{(row.qty_vend_d0 ?? 0).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0})}}
                        </TableData>
                        <TableData inputClass="text-right">
                            {{(row.qty_warehouse_d0 ?? 0).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0})}}
                        </TableData>
                        <TableData inputClass="text-right border-block">
                            {{(row.stock_cost_d0 ?? 0).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
                        </TableData>
                        <td class="w-2 bg-gray-300 p-0" aria-hidden="true"></td> <!-- spacer -->

                        <!-- D1 -->
                        <TableData inputClass="text-right">
                            {{(row.stock_value_d1 ?? 0).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
                        </TableData>
                        <TableData inputClass="text-right">
                            {{(row.qty_vend_d1 ?? 0).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0})}}
                        </TableData>
                        <TableData inputClass="text-right">
                            {{(row.qty_warehouse_d1 ?? 0).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0})}}
                        </TableData>
                        <TableData inputClass="text-right border-block">
                            {{(row.stock_cost_d1 ?? 0).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
                        </TableData>
                        <td class="w-2 bg-gray-300 p-0" aria-hidden="true"></td> <!-- spacer -->

                        <!-- D2 -->
                        <TableData inputClass="text-right">
                            {{(row.stock_value_d2 ?? 0).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
                        </TableData>
                        <TableData inputClass="text-right">
                            {{(row.qty_vend_d2 ?? 0).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0})}}
                        </TableData>
                        <TableData inputClass="text-right">
                            {{(row.qty_warehouse_d2 ?? 0).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0})}}
                        </TableData>
                        <TableData inputClass="text-right border-block">
                            {{(row.stock_cost_d2 ?? 0).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
                        </TableData>
                        <td class="w-2 bg-gray-300 p-0" aria-hidden="true"></td> <!-- spacer -->
                    </tr>

                    <tr v-if="!(stockCounts.data && stockCounts.data.length)">
                        <td colspan="30" class="py-4 text-sm font-medium text-center">
                        No Results Found
                        </td>
                    </tr>
                    </tbody>
                    <tfoot class="bg-gray-50 font-normal">
                        <tr>
                            <td colspan="30" class="w-1 bg-gray-300 p-0" aria-hidden="true">.</td>
                        </tr>
                        <tr class="divide-x divide-gray-300">
                            <td colspan="3" class="text-center px-1 py-2">Subtotal</td>

                            <!-- D0 -->
                            <td class="text-right px-1 py-2">
                            {{ Number(props.totals?.stock_value_d0 ?? 0)
                                .toLocaleString(undefined, { minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent) }) }}
                            </td>
                            <td class="text-right px-1 py-2">
                            {{ Number(props.totals?.qty_vend_d0 ?? 0).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0}) }}
                            </td>
                            <td class="text-right px-1 py-2">
                            {{ Number(props.totals?.qty_warehouse_d0 ?? 0).toLocaleString() }}
                            </td>
                            <td class="text-right px-1 py-2 border-block">
                            {{ Number(props.totals?.stock_cost_d0 ?? 0)
                                .toLocaleString(undefined, { minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent) }) }}
                            </td>
                            <td class="w-2 bg-gray-300 p-0" aria-hidden="true"></td>

                            <!-- D1 -->
                            <td class="text-right px-1 py-2">
                            {{ Number(props.totals?.stock_value_d1 ?? 0)
                                .toLocaleString(undefined, { minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent) }) }}
                            </td>
                            <td class="text-right px-1 py-2">
                            {{ Number(props.totals?.qty_vend_d1 ?? 0).toLocaleString() }}
                            </td>
                            <td class="text-right px-1 py-2">
                            {{ Number(props.totals?.qty_warehouse_d1 ?? 0).toLocaleString() }}
                            </td>
                            <td class="text-right px-1 py-2 border-block">
                            {{ Number(props.totals?.stock_cost_d1 ?? 0)
                                .toLocaleString(undefined, { minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent) }) }}
                            </td>
                            <td class="w-2 bg-gray-300 p-0" aria-hidden="true"></td>

                            <!-- D2 -->
                            <td class="text-right px-1 py-2">
                            {{ Number(props.totals?.stock_value_d2 ?? 0)
                                .toLocaleString(undefined, { minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent) }) }}
                            </td>
                            <td class="text-right px-1 py-2">
                            {{ Number(props.totals?.qty_vend_d2 ?? 0).toLocaleString() }}
                            </td>
                            <td class="text-right px-1 py-2">
                            {{ Number(props.totals?.qty_warehouse_d2 ?? 0).toLocaleString() }}
                            </td>
                            <td class="text-right px-1 py-2 border-block">
                            {{ Number(props.totals?.stock_cost_d2 ?? 0)
                                .toLocaleString(undefined, { minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent) }) }}
                            </td>
                            <td class="w-2 bg-gray-300 p-0" aria-hidden="true"></td>
                        </tr>
                        </tfoot>

              </table>
          </div>
          <Paginator v-if="stockCounts.data.length" :links="stockCounts.links" :meta="stockCounts.meta"></Paginator>
      </div>
      </div>
  </div>
  </BreezeAuthenticatedLayout>
</template>

<style>
.border-block {
    border-right: 3px solid #4b5563; /* Dark gray (Tailwind gray-600) */
}
</style>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import DatePicker from '@/Components/DatePicker.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { ArrowDownTrayIcon, MagnifyingGlassIcon, BackspaceIcon} from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ref, onMounted, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Head, usePage } from '@inertiajs/vue3';
import moment from 'moment';
import axios from 'axios';

const props = defineProps({
  locationTypeOptions: Object,
  operatorOptions: Object,
  pivotDates: Object,
  productOptions: Object,
  reportDateOptions: Object,
  stockCounts: Object,
  totals: Object,
  vendPrefixOptions: Object,
})

const filters = ref({
  codes: '',
  currentFilterDate: '',
    customer: '',
    date: '',
  locationType: '',
  operators: [],
  products: [],
  sortKey: '',
  sortBy: false,
  numberPerPage: '',
  vendPrefixes: [],
  visited: true,
})

const authOperator = usePage().props.auth.operator
const booleanOptions = ref([])
const enableOptions = ref([])
const loading = ref(false)
const locationTypeOptions = ref([])
const numberPerPageOptions = ref([])
const operatorCountry = usePage().props.auth.operatorCountry;
const operatorOptions = ref([])
const productOptions = ref([])
const reportDateOptions = ref([])
const vendChannelErrorsOptions = ref([])
const vendPrefixOptions = ref([])
const operatorRole = usePage().props.auth.operatorRole
const permissions = usePage().props.auth.permissions

onMounted(() => {
  filters.value.visited = true
  numberPerPageOptions.value = [
      { id: 100, value: 100 },
      { id: 200, value: 200 },
      { id: 500, value: 500 },
      { id: 'All', value: 'All' },
  ]
  filters.value.vend_channel_error_id = vendChannelErrorsOptions.value[0]
  filters.value.numberPerPage = numberPerPageOptions.value[0]

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
      ...props.operatorOptions.data.map((data) => {return {id: data.id, code: data.code, full_name: data.full_name}})
  ]
  productOptions.value = [
      {id: 'all', full_name: 'All'},
      ...props.productOptions.data.map((data) => {return {id: data.id, full_name: '(' + data.code + ') ' + data.name}})
  ]
  reportDateOptions.value = props.reportDateOptions.map((data) => {return {id: data.id, name: data.name}})
  reportDateOptions.value = [
    ...reportDateOptions.value,
    {'id': '-1', 'name': 'Custom Date'}
  ]
  vendPrefixOptions.value = [
        {id: 'all', value: 'All'},
        {id: 'single-ud', value: 'Single UD'},
        ...props.vendPrefixOptions.data.map((data) => {return {id: data.id, value: data.name}})
    ]

  filters.value.currentFilterDate = reportDateOptions.value[0]
  filters.value.locationType = locationTypeOptions.value[0]
  filters.value.operators = authOperator ? [
		operatorOptions.value.find(operator => operator.id === authOperator.id),
		...authOperator.code == 'HIPL' ? [
			operatorOptions.value.find(operator => operator.code == 'HIMD'),
			operatorOptions.value.find(operator => operator.code == 'LEA'),
			operatorOptions.value.find(operator => operator.code == 'DCVIC'),
            operatorOptions.value.find(operator => operator.code == 'HIESG'),
		] : [],
    ] : operatorOptions.value[0]
  filters.value.vendPrefixes = [
    vendPrefixOptions.value[0]
  ]

  // vendOptions.value = props.vendOptions.data.map((stockCount) => {return {id: stockCount.id, code: stockCount.code}})
})

// replace your current dayLabel with this:
const dayLabel = (key) => {
  if (key === 'd0') return 'Today';
  if (key === 'd1') return 'Yesterday';
  return '2 days ago'; // d2
};

// (optional) tiny helper to show the actual date as a tooltip
const dayTitle = (iso) => (iso ? new Date(iso).toISOString().slice(0, 10) : '');


  function onSearchFilterUpdated() {

      router.get('/reports/stock-count', {
          ...filters.value,
          currentFilterDate: filters.value.currentFilterDate?.id ?? null,
          locationType: filters.value.locationType.id,
          location_type_id: filters.value.locationType.id,
          operators: filters.value.operators.map((operator) => { return operator.id }),
          numberPerPage: filters.value.numberPerPage.id,
          vendPrefixes: filters.value.vendPrefixes.map((vendPrefix) => { return vendPrefix.id }),
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

function onExportExcelClicked() {
  loading.value = true
  axios({
      method: 'get',
      url: '/reports/stock-count/excel',
      params: {
          ...filters.value,
          currentFilterDate: filters.value.currentFilterDate?.id ?? null,
          location_type_id: filters.value.locationType.id,
          operators: filters.value.operators.map((operator) => { return operator.id }),
          vendPrefixes: filters.value.vendPrefixes.map((vendPrefix) => { return vendPrefix.id }),
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