<template>
  <Head title="Sales Performance by Product" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Sales Performance by Product
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3">
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
            />
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
            />
          </div>
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
            />
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
            />
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
            />
          </div>
        </div>

        <div class="flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5">
          <div class="mt-3">
            <div class="flex space-x-1">
              <Button
                class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                @click="onSearchFilterUpdated()"
              >
                <MagnifyingGlassIcon class="h-4 w-4" aria-hidden="true" />
                <span>
                  Search
                </span>
              </Button>
              <Button
                class="inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                @click="resetFilters()"
              >
                <BackspaceIcon class="h-4 w-4" aria-hidden="true" />
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
            />
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
                  <TableHead colspan="5">
                    This Month
                  </TableHead>
                  <TableHead colspan="5">
                    Last Month
                  </TableHead>
                  <TableHead colspan="5">
                    2 Months Ago
                  </TableHead>
                </tr>
                <tr class="divide-x divide-gray-200">
                  <TableHead colspan="3" />
                  <TableHeadSort modelName="this_month_channel_count" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('this_month_channel_count')">
                    # of CH
                  </TableHeadSort>
                  <TableHeadSort modelName="this_month_revenue" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('this_month_revenue')">
                    Sales ($)
                  </TableHeadSort>
                  <TableHeadSort modelName="this_month_count" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('this_month_count')">
                    Sales (Qty)
                  </TableHeadSort>
                  <TableHeadSort modelName="this_month_qty_per_day" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('this_month_qty_per_day')">
                    Qty per Day
                  </TableHeadSort>
                  <TableHeadSort modelName="this_month_availability" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('this_month_availability')">
                    Availability (%)
                  </TableHeadSort>

                  <TableHeadSort modelName="last_month_channel_count" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('last_month_channel_count')">
                    # of CH
                  </TableHeadSort>
                  <TableHeadSort modelName="last_month_revenue" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('last_month_revenue')">
                    Sales ($)
                  </TableHeadSort>
                  <TableHeadSort modelName="last_month_count" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('last_month_count')">
                    Sales (Qty)
                  </TableHeadSort>
                  <TableHeadSort modelName="last_month_qty_per_day" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('last_month_qty_per_day')">
                    Qty per Day
                  </TableHeadSort>
                  <TableHeadSort modelName="last_month_availability" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('last_month_availability')">
                    Availability (%)
                  </TableHeadSort>

                  <TableHeadSort modelName="last_two_month_channel_count" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('last_two_month_channel_count')">
                    # of CH
                  </TableHeadSort>
                  <TableHeadSort modelName="last_two_month_revenue" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('last_two_month_revenue')">
                    Sales ($)
                  </TableHeadSort>
                  <TableHeadSort modelName="last_two_month_count" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('last_two_month_count')">
                    Sales (Qty)
                  </TableHeadSort>
                  <TableHeadSort modelName="last_two_month_qty_per_day" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('last_two_month_qty_per_day')">
                    Qty per Day
                  </TableHeadSort>
                  <TableHeadSort modelName="last_two_month_availability" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('last_two_month_availability')">
                    Availability (%)
                  </TableHeadSort>
                </tr>
                <tr class="divide-x divide-gray-200">
                  <TableHead colspan="3" />
                  <TableData inputClass="text-right font-semibold">
                    {{ totals['this_month_channel_total']?.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 }) ?? 0 }}
                  </TableData>
                  <TableData inputClass="text-right font-semibold">
                    {{ totals['this_month_revenue_total'].toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                  </TableData>
                  <TableData inputClass="text-right font-semibold">
                    {{ totals['this_month_count_total'].toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}
                  </TableData>
                  <TableData inputClass="text-right font-semibold">
                    {{ totals['this_month_qty_per_day_total'].toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                  </TableData>
                  <TableData inputClass="text-right font-semibold">
                    {{ formatAvailability(totals['this_month_availability_avg']) }}
                  </TableData>

                  <TableData inputClass="text-right font-semibold">
                    {{ totals['last_month_channel_total']?.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 }) ?? 0 }}
                  </TableData>
                  <TableData inputClass="text-right font-semibold">
                    {{ totals['last_month_revenue_total'].toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                  </TableData>
                  <TableData inputClass="text-right font-semibold">
                    {{ totals['last_month_count_total'].toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}
                  </TableData>
                  <TableData inputClass="text-right font-semibold">
                    {{ totals['last_month_qty_per_day_total'].toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                  </TableData>
                  <TableData inputClass="text-right font-semibold">
                    {{ formatAvailability(totals['last_month_availability_avg']) }}
                  </TableData>

                  <TableData inputClass="text-right font-semibold">
                    {{ totals['last_two_month_channel_total']?.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 }) ?? 0 }}
                  </TableData>
                  <TableData inputClass="text-right font-semibold">
                    {{ totals['last_two_month_revenue_total'].toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                  </TableData>
                  <TableData inputClass="text-right font-semibold">
                    {{ totals['last_two_month_count_total'].toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}
                  </TableData>
                  <TableData inputClass="text-right font-semibold">
                    {{ totals['last_two_month_qty_per_day_total'].toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                  </TableData>
                  <TableData inputClass="text-right font-semibold">
                    {{ formatAvailability(totals['last_two_month_availability_avg']) }}
                  </TableData>
                </tr>
              </thead>
              <tbody class="bg-white">
                <tr
                  v-for="(product, productIndex) in products.data"
                  :key="product.id"
                  class="divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100"
                >
                  <TableData :currentIndex="productIndex" :totalLength="products.data.length" inputClass="text-center">
                    {{ products.meta.from + productIndex }}
                  </TableData>
                  <TableData :currentIndex="productIndex" :totalLength="products.data.length" inputClass="text-center">
                    {{ product.code }}
                  </TableData>
                  <TableData :currentIndex="productIndex" :totalLength="products.data.length" inputClass="text-left">
                    {{ product.name }}
                  </TableData>

                  <TableData :currentIndex="productIndex" :totalLength="products.data.length" inputClass="text-right">
                    {{ product.this_month_channel_count.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}
                  </TableData>
                  <TableData :currentIndex="productIndex" :totalLength="products.data.length" inputClass="text-right">
                    {{ product.this_month_revenue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                  </TableData>
                  <TableData :currentIndex="productIndex" :totalLength="products.data.length" inputClass="text-right">
                    {{ product.this_month_count.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}
                  </TableData>
                  <TableData :currentIndex="productIndex" :totalLength="products.data.length" inputClass="text-right">
                    {{ product.this_month_qty_per_day.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                  </TableData>
                  <TableData :currentIndex="productIndex" :totalLength="products.data.length" inputClass="text-right">
                    {{ formatAvailability(product.this_month_availability) }}
                  </TableData>

                  <TableData :currentIndex="productIndex" :totalLength="products.data.length" inputClass="text-right">
                    {{ product.last_month_channel_count.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}
                  </TableData>
                  <TableData :currentIndex="productIndex" :totalLength="products.data.length" inputClass="text-right">
                    {{ product.last_month_revenue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                  </TableData>
                  <TableData :currentIndex="productIndex" :totalLength="products.data.length" inputClass="text-right">
                    {{ product.last_month_count.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}
                  </TableData>
                  <TableData :currentIndex="productIndex" :totalLength="products.data.length" inputClass="text-right">
                    {{ product.last_month_qty_per_day.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                  </TableData>
                  <TableData :currentIndex="productIndex" :totalLength="products.data.length" inputClass="text-right">
                    {{ formatAvailability(product.last_month_availability) }}
                  </TableData>

                  <TableData :currentIndex="productIndex" :totalLength="products.data.length" inputClass="text-right">
                    {{ product.last_two_month_channel_count.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}
                  </TableData>
                  <TableData :currentIndex="productIndex" :totalLength="products.data.length" inputClass="text-right">
                    {{ product.last_two_month_revenue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                  </TableData>
                  <TableData :currentIndex="productIndex" :totalLength="products.data.length" inputClass="text-right">
                    {{ product.last_two_month_count.toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}
                  </TableData>
                  <TableData :currentIndex="productIndex" :totalLength="products.data.length" inputClass="text-right">
                    {{ product.last_two_month_qty_per_day.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                  </TableData>
                  <TableData :currentIndex="productIndex" :totalLength="products.data.length" inputClass="text-right">
                    {{ formatAvailability(product.last_two_month_availability) }}
                  </TableData>
                </tr>
                <tr v-if="!products.data.length">
                  <td colspan="18" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                    No Results Found
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator v-if="products.data.length" :links="products.links" :meta="products.meta" />
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
import { Head, usePage, router } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

const props = defineProps({
  categories: Object,
  categoryGroups: Object,
  locationTypeOptions: Object,
  monthOptions: Array,
  operators: Object,
  totals: Object,
  products: Object,
  vendPrefixOptions: Object,
});

const filters = ref({
  codes: '',
  currentMonth: '',
  customer: '',
  product_code: '',
  product_name: '',
  is_binded_customer: '',
  location_type_id: '',
  operators: [],
  sortKey: '',
  sortBy: false,
  numberPerPage: 30,
  vendPrefixes: [],
  visited: false,
});

const booleanOptions = ref([]);
const categoryOptions = ref([]);
const categoryGroupOptions = ref([]);
const locationTypeOptions = ref([]);
const monthOptions = ref([]);
const operatorOptions = ref([]);
const numberPerPageOptions = ref([]);
const permissions = usePage().props.auth.permissions;
const operatorRole = usePage().props.auth.operatorRole;
const vendPrefixOptions = ref([]);

onMounted(() => {
  filters.value.visited = true;
  numberPerPageOptions.value = [
    { id: 30, value: 30 },
    { id: 50, value: 50 },
    { id: 100, value: 100 },
    { id: 200, value: 200 },
    { id: 500, value: 500 },
    { id: 'All', value: 'All' },
  ];
  filters.value.numberPerPage = numberPerPageOptions.value[0];

  booleanOptions.value = [
    { id: 'all', value: 'All' },
    { id: 'true', value: 'Yes' },
    { id: 'false', value: 'No' },
  ];

  categoryOptions.value = props.categories.data.map((data) => ({ id: data.id, name: data.name }));
  categoryGroupOptions.value = props.categoryGroups.data.map((data) => ({ id: data.id, name: data.name }));

  locationTypeOptions.value = [
    { id: 'all', value: 'All' },
    ...props.locationTypeOptions.data.map((data) => ({ id: data.id, value: data.name })),
  ];

  monthOptions.value = props.monthOptions.map((data) => ({ id: data.id, name: data.name }));
  filters.value.currentMonth = monthOptions.value[0];

  operatorOptions.value = [
    { id: 'all', full_name: 'All' },
    ...props.operators.data.map((data) => ({ id: data.id, full_name: data.full_name })),
  ];

  vendPrefixOptions.value = [
    { id: 'all', value: 'All' },
    { id: 'single-ud', value: 'Single UD' },
    ...props.vendPrefixOptions.data.map((data) => ({ id: data.id, value: data.name })),
  ];

  filters.value.is_binded_customer = operatorRole ? booleanOptions.value[0] : booleanOptions.value[1];
  filters.value.location_type_id = locationTypeOptions.value[0];
  filters.value.operators = [operatorOptions.value[0]].filter(operator => operator !== undefined);
  filters.value.vendPrefixes = [vendPrefixOptions.value[0]];
});

function onSearchFilterUpdated() {
  router.get('/reports/sales-performance/product', {
    ...filters.value,
    currentMonth: filters.value.currentMonth.id,
    is_binded_customer: filters.value.is_binded_customer.id,
    location_type_id: filters.value.location_type_id.id,
    operators: filters.value.operators.filter(operator => operator).map((operator) => operator.id),
    vendPrefixes: filters.value.vendPrefixes.map((vendPrefix) => vendPrefix.id),
    numberPerPage: filters.value.numberPerPage.id,
  }, {
    preserveState: true,
    replace: true,
  });
}

function resetFilters() {
  router.get('/reports/sales-performance/product');
}

function sortTable(sortKey) {
  filters.value.sortKey = sortKey;
  filters.value.sortBy = !filters.value.sortBy;
  onSearchFilterUpdated();
}

function formatAvailability(value) {
  if (value === null || value === undefined) {
    return '-';
  }
  return `${Number(value).toLocaleString(undefined, { minimumFractionDigits: 1, maximumFractionDigits: 1 })}%`;
}
</script>
