<template>
  <!-- The list page both stop types share: filters, search, export, paging and
       the common columns. A page passes its own extra filter + columns in slots. -->
  <Head :title="title" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">Daily Jobs ({{ title }})</h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-3 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 py-3">
        <div class="grid grid-cols-1 md:grid-cols-6 gap-2">
          <div>
            <label class="block text-sm font-medium text-gray-700">Job Date From</label>
            <input type="date" v-model="filters.date_from" class="mt-1 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Job Date To</label>
            <input type="date" v-model="filters.date_to" class="mt-1 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md" />
          </div>
          <SearchInput placeholderStr="Machine ID" v-model="filters.vend_code" @keyup.enter="search">Machine ID</SearchInput>
          <SearchInput placeholderStr="Site" v-model="filters.customer" @keyup.enter="search">Site</SearchInput>
          <div>
            <label class="block text-sm font-medium text-gray-700">Status</label>
            <MultiSelect v-model="filters.status" :options="statusFilterOptions" trackBy="id" valueProp="id" label="value" placeholder="Select" open-direction="bottom" class="mt-1" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Assigned To</label>
            <MultiSelect v-model="filters.delivered_by" :options="driverFilterOptions" trackBy="id" valueProp="id" label="value" placeholder="Select" open-direction="bottom" class="mt-1" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">{{ extraFilter.label }}</label>
            <MultiSelect v-model="filters.extra" :options="extraFilter.options" trackBy="id" valueProp="id" label="value" placeholder="Select" open-direction="bottom" class="mt-1" />
          </div>
        </div>

        <div class="flex flex-col md:flex-row md:justify-between gap-3 mt-5">
          <div class="flex flex-wrap gap-1">
            <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600" @click="search">
              <MagnifyingGlassIcon class="h-4 w-4" aria-hidden="true" /><span>Search</span>
            </Button>
            <Button class="inline-flex space-x-1 items-center rounded-md bg-white px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-400 hover:bg-gray-100" @click="reset">
              <BackspaceIcon class="h-4 w-4" aria-hidden="true" /><span>Reset</span>
            </Button>
            <Button
              v-if="canExport" type="button" :disabled="exporting"
              class="inline-flex space-x-1 items-center rounded-md bg-white px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-400 hover:bg-gray-100"
              @click.prevent="exportExcel"
            >
              <ArrowDownTrayIcon class="h-4 w-4" :class="{ 'animate-bounce': exporting }" aria-hidden="true" /><span>Export Excel</span>
            </Button>
          </div>
          <div class="flex space-x-3 items-center">
            <div class="text-sm text-gray-700">
              Showing <span class="font-medium">{{ rows.meta.from ?? 0 }}</span> to <span class="font-medium">{{ rows.meta.to ?? 0 }}</span>
              of <span class="font-medium">{{ rows.meta.total }}</span> results
            </div>
            <MultiSelect v-model="filters.numberPerPage" :options="NUMBER_PER_PAGE" trackBy="id" label="value" :showLabels="false" :allowEmpty="false" :searchable="false" class="w-32" @select="search" />
          </div>
        </div>
      </div>

      <div class="mt-6 flex flex-col">
        <div class="-my-2 -mx-3 sm:-mx-6 lg:-mx-8">
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll">
            <table class="min-w-full border-separate" style="border-spacing: 0">
              <thead class="bg-gray-100">
                <tr class="divide-x divide-gray-200">
                  <TableHead>#</TableHead>
                  <TableHeadSort modelName="code" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('code')">Ref</TableHeadSort>
                  <TableHeadSort modelName="job_date" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('job_date')">Job Date</TableHeadSort>
                  <TableHead>Machine ID</TableHead>
                  <TableHead>Site</TableHead>
                  <TableHead>Assigned To</TableHead>
                  <TableHeadSort modelName="status" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('status')">Status</TableHeadSort>
                  <slot name="head" :filters="filters" :sortTable="sortTable" />
                  <TableHead>Remarks</TableHead>
                </tr>
              </thead>
              <tbody class="bg-white">
                <tr v-for="(row, index) in rows.data" :key="row.id" class="divide-x divide-gray-200">
                  <TableData :currentIndex="index" :totalLength="rows.data.length" inputClass="text-center">{{ (rows.meta.from ?? 1) + index }}</TableData>
                  <TableData :currentIndex="index" :totalLength="rows.data.length" inputClass="text-center">
                    <Link :href="basePath + '/' + row.id + '/edit'" class="text-blue-600 underline font-medium">{{ row.display_code }}</Link>
                  </TableData>
                  <TableData :currentIndex="index" :totalLength="rows.data.length" inputClass="text-center">
                    <Link :href="'/ops-jobs/' + row.ops_job_id + '/edit'" class="text-blue-600 underline">{{ row.ops_job?.date_label }}</Link>
                  </TableData>
                  <TableData :currentIndex="index" :totalLength="rows.data.length" inputClass="text-center">{{ row.vend_code }}</TableData>
                  <TableData :currentIndex="index" :totalLength="rows.data.length" inputClass="text-left">{{ row.customer_name }}</TableData>
                  <TableData :currentIndex="index" :totalLength="rows.data.length" inputClass="text-center">{{ row.ops_job?.delivered_by_name }}</TableData>
                  <TableData :currentIndex="index" :totalLength="rows.data.length" inputClass="text-center">
                    <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium border" :class="stopStatusClass(row.status)">{{ row.status_name }}</span>
                  </TableData>
                  <slot name="cells" :row="row" :index="index" :total="rows.data.length" />
                  <TableData :currentIndex="index" :totalLength="rows.data.length" inputClass="text-left">{{ row.remarks }}</TableData>
                </tr>
                <tr v-if="!rows.data.length">
                  <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium text-black sm:pr-6 lg:pr-8 text-center">No Results Found</td>
                </tr>
              </tbody>
            </table>
            <Paginator v-if="rows.data.length" :links="rows.links" :meta="rows.meta"></Paginator>
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import Button from '@/Components/Button.vue'
import MultiSelect from '@/Components/MultiSelect.vue'
import Paginator from '@/Components/Paginator.vue'
import SearchInput from '@/Components/SearchInput.vue'
import TableData from '@/Components/TableData.vue'
import TableHead from '@/Components/TableHead.vue'
import TableHeadSort from '@/Components/TableHeadSort.vue'
import { ArrowDownTrayIcon, BackspaceIcon, MagnifyingGlassIcon } from '@heroicons/vue/20/solid'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import moment from 'moment'
import { computed, ref } from 'vue'
import { useToast } from 'vue-toastification'
import { stopStatusClass } from './stopTypes'

const props = defineProps({
  title: String,
  basePath: String, // '/service-notices' | '/stock-checks'
  exportName: String,
  exportPermission: String,
  rows: Object,
  statusOptions: Array,
  driverOptions: Array,
  serverFilters: Object,
  // The one filter that differs per stop type: { key, label, options: [{ id, value }] }
  extraFilter: Object,
})

const NUMBER_PER_PAGE = [{ id: 100, value: 100 }, { id: 200, value: 200 }, { id: 500, value: 500 }, { id: 'All', value: 'All' }]
const ALL = { id: 'all', value: 'All' }

const toast = useToast()
const canExport = usePage().props.auth.permissions.includes(props.exportPermission)
const exporting = ref(false)

const statusFilterOptions = computed(() => [ALL, ...(props.statusOptions || [])])
const driverFilterOptions = computed(() => [ALL, ...(props.driverOptions || [])])
const pick = (options, id) => options.find(o => String(o.id) === String(id ?? 'all')) ?? options[0]

const filters = ref({
  date_from: props.serverFilters?.date_from ?? '',
  date_to: props.serverFilters?.date_to ?? '',
  vend_code: props.serverFilters?.vend_code ?? '',
  customer: props.serverFilters?.customer ?? '',
  status: pick(statusFilterOptions.value, props.serverFilters?.status),
  delivered_by: pick(driverFilterOptions.value, props.serverFilters?.delivered_by),
  extra: pick(props.extraFilter.options, props.serverFilters?.[props.extraFilter.key]),
  sortKey: props.serverFilters?.sortKey ?? 'job_date',
  sortBy: props.serverFilters?.sortBy ?? false,
  numberPerPage: pick(NUMBER_PER_PAGE, props.serverFilters?.numberPerPage ?? 100),
})

function query() {
  return {
    date_from: filters.value.date_from || undefined,
    date_to: filters.value.date_to || undefined,
    vend_code: filters.value.vend_code || undefined,
    customer: filters.value.customer || undefined,
    status: filters.value.status?.id ?? 'all',
    delivered_by: filters.value.delivered_by?.id ?? 'all',
    [props.extraFilter.key]: filters.value.extra?.id ?? 'all',
    sortKey: filters.value.sortKey,
    sortBy: filters.value.sortBy,
    numberPerPage: filters.value.numberPerPage?.id ?? 100,
  }
}

function search() {
  router.get(props.basePath, query(), { preserveState: true, replace: true })
}

function reset() {
  router.get(props.basePath)
}

function sortTable(sortKey) {
  filters.value.sortKey = sortKey
  filters.value.sortBy = !filters.value.sortBy
  search()
}

function exportExcel() {
  exporting.value = true
  axios({ method: 'get', url: props.basePath + '/excel', params: { ...query(), numberPerPage: undefined }, responseType: 'blob' })
    .then(response => fileDownload(response.data, props.exportName + '_' + moment().format('YYYYMMDD_HHmmss') + '.xlsx'))
    .catch(() => toast.error('Failed to export', { timeout: 3000 }))
    .finally(() => { exporting.value = false })
}
</script>
