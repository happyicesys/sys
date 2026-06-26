
<template>

  <Head title="Daily Summary" />

  <BreezeAuthenticatedLayout>
      <template #header>
          <h2 class="font-semibold text-xl text-gray-800 leading-tight">
              Transactions Daily Summary
          </h2>
      </template>

      <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
        <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
          <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
            <div class="col-span-5 md:col-span-1">
                <SearchInput placeholderStr="Machine ID" v-model="filters.codes" @keyup.enter="onSearchFilterUpdated()">
                    Machine ID
                    <span class="text-[9px]">
                        ("," for multiple)
                    </span>
                </SearchInput>
            </div>
            <div class="col-span-5 md:col-span-1">
                <SearchInput placeholderStr="Channel ID" v-model="filters.channel_codes" @keyup.enter="onSearchFilterUpdated()">
                    Channel ID
                    <span class="text-[9px]">
                        ("," for multiple)
                    </span>
                </SearchInput>
            </div>
            <div class="col-span-5 md:col-span-1">
                <DatePicker
                    v-model="filters.date_from"
                >
                    From
                </DatePicker>
            </div>
            <div class="col-span-5 md:col-span-1">
                <DatePicker
                    v-model="filters.date_to"
                    :minDate="filters.date_from"
                >
                    To
                </DatePicker>
            </div>
            <div class="col-span-5 md:col-span-1" v-if="permissions.includes('admin-access transactions')">
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
            <div class="col-span-5 md:col-span-1">
                <label for="text" class="block text-sm font-medium text-gray-700">
                   Payment Method
                </label>
                <MultiSelect
                    v-model="filters.paymentMethods"
                    :options="paymentMethodOptions"
                    valueProp="id"
                    label="name"
                    placeholder="Select"
                    open-direction="bottom"
                    class="mt-1"
                    mode="tags"
                >
                </MultiSelect>
            </div>
            <!-- Card Terminal filter retired on 2026-05-16 — its options
                 were folded into Payment Method as "Credit Card (<terminal>)"
                 synthetic entries. See paymentMethodOptions below. -->
            <div class="col-span-5 md:col-span-1">
                <label for="text" class="block text-sm font-medium text-gray-700">
                    Payment Received
                </label>
                <MultiSelect
                    v-model="filters.is_payment_received"
                    :options="successfulOptions"
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
                    <div class="flex flex-col space-y-1 md:flex-row md:space-y-0 md:space-x-1">
                        <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        @click.prevent="onSearchFilterUpdated()"
                        >
                            <MagnifyingGlassIcon class="h-4 w-4" aria-hidden="true"/>
                            <span>
                                Search
                            </span>
                        </Button>
                        <Button
                            type="button"
                            class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-400 hover:bg-gray-100"
                            @click.prevent="onExportCsvClicked()"
                            v-if="permissions.includes('export transactions')"
                        >
                            <div class="flex space-x-1">
                                <ArrowDownTrayIcon v-if="!loadingCsv" class="h-4 w-4" aria-hidden="true"/>
                                <svg v-if="loadingCsv" aria-hidden="true" class="mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908Z" fill="currentColor"/>
                                    <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                                </svg>
                                <span>Export CSV</span>
                            </div>
                        </Button>
                    </div>
                </div>
                <div class="flex flex-col space-y-2">
                    <p class="text-sm text-gray-700 leading-5 flex space-x-1">
                        <span>Showing</span>
                        <span class="font-medium">{{ dailySummary.meta.from ?? 0 }}</span>
                        <span>to</span>
                        <span class="font-medium">{{ dailySummary.meta.to ?? 0 }}</span>
                        <span>of</span>
                        <span class="font-medium">{{ dailySummary.meta.total }}</span>
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

        <dl class="grid grid-cols-1 md:grid-cols-4 gap-2 mt-3">
            <div class="col-span-1 overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow">
                <dt class="text-base font-bold text-gray-800">Total Sales {{ operatorCountry.currency_symbol }}</dt>
                <dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
                    {{ formatAmount(totals && totals.success_amount) }}
                </dd>
                <dd class="text-xs text-gray-500 mt-1">
                    Gross: {{ formatAmount(totals && totals.total_amount) }}
                </dd>
            </div>
            <div class="col-span-1 overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow">
                <dt class="text-base font-bold text-gray-800">Transaction Count</dt>
                <dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
                    {{ formatCount(totals && totals.total_transaction_count) }}
                </dd>
                <dd class="text-xs text-gray-500 mt-1">
                    Successful: {{ formatCount(totals && totals.success_count) }}
                </dd>
            </div>
            <div class="col-span-1 overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow">
                <dt class="text-base font-bold text-gray-800">Refunded</dt>
                <dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
                    {{ formatCount(totals && totals.refunded_count) }}
                </dd>
                <dd class="text-xs text-gray-500 mt-1">
                    Amount: {{ formatAmount(totals && totals.refunded_amount) }}
                </dd>
            </div>
            <div class="col-span-1 overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow">
                <dt class="text-base font-bold text-gray-800">Success Rate</dt>
                <dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
                    {{ successRate }}%
                </dd>
            </div>
        </dl>
      </div>

       <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll">
              <table class="table-auto min-w-full border-separate" style="border-spacing: 0">
                  <thead class="">
                      <tr class="divide-x bg-gray-400">
                        <TableHead>
                            #
                        </TableHead>
                        <TableHeadSort modelName="transaction_date" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('transaction_date')">
                            Date
                        </TableHeadSort>
                        <TableHeadSort modelName="payment_method_name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('payment_method_name')">
                            Payment Method
                        </TableHeadSort>
                        <TableHeadSort modelName="total_transaction_count" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('total_transaction_count', true)">
                            Total Txns
                        </TableHeadSort>
                        <TableHeadSort modelName="success_count" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('success_count', true)">
                            Successful
                        </TableHeadSort>
                        <TableHead>
                            Success Rate
                        </TableHead>
                        <TableHeadSort modelName="total_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('total_amount', true)">
                            Total Amount {{ operatorCountry.currency_symbol }}
                        </TableHeadSort>
                        <TableHeadSort modelName="success_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('success_amount', true)">
                            Successful Amount {{ operatorCountry.currency_symbol }}
                        </TableHeadSort>
                        <TableHead>
                            Refunded
                        </TableHead>
                        <TableHead>
                            Refunded Amount {{ operatorCountry.currency_symbol }}
                        </TableHead>
                      </tr>
                  </thead>
                  <tbody class="bg-white">
                    <template v-for="(row, rowIndex) in dailySummary.data" :key="rowIndex">
                      <tr class="divide-x divide-y-2 divide-gray-300">
                        <TableData :currentIndex="rowIndex" :totalLength="dailySummary.data.length" inputClass="text-center">
                            {{ (dailySummary.meta.from || 0) + rowIndex }}
                        </TableData>
                        <TableData :currentIndex="rowIndex" :totalLength="dailySummary.data.length" inputClass="text-center">
                            {{ row.transaction_date }}
                        </TableData>
                        <TableData :currentIndex="rowIndex" :totalLength="dailySummary.data.length" inputClass="text-center">
                            {{ row.payment_method_name || '—' }}
                        </TableData>
                        <TableData :currentIndex="rowIndex" :totalLength="dailySummary.data.length" inputClass="text-right">
                            {{ formatCount(row.total_transaction_count) }}
                        </TableData>
                        <TableData :currentIndex="rowIndex" :totalLength="dailySummary.data.length" inputClass="text-right">
                            {{ formatCount(row.success_count) }}
                        </TableData>
                        <TableData :currentIndex="rowIndex" :totalLength="dailySummary.data.length" inputClass="text-right">
                            {{ rowSuccessRate(row) }}%
                        </TableData>
                        <TableData :currentIndex="rowIndex" :totalLength="dailySummary.data.length" inputClass="text-right">
                            {{ formatAmount(row.total_amount) }}
                        </TableData>
                        <TableData :currentIndex="rowIndex" :totalLength="dailySummary.data.length" inputClass="text-right">
                            {{ formatAmount(row.success_amount) }}
                        </TableData>
                        <TableData :currentIndex="rowIndex" :totalLength="dailySummary.data.length" inputClass="text-right">
                            {{ formatCount(row.refunded_count) }}
                        </TableData>
                        <TableData :currentIndex="rowIndex" :totalLength="dailySummary.data.length" inputClass="text-right">
                            {{ formatAmount(row.refunded_amount) }}
                        </TableData>
                      </tr>
                    </template>
                    <tr v-if="!dailySummary || !dailySummary.data.length">
                        <td colspan="11" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                            No Results Found
                        </td>
                    </tr>
                  </tbody>
              </table>
              <Paginator v-if="dailySummary.data.length" :links="dailySummary.links" :meta="dailySummary.meta"></Paginator>
          </div>
      </div>
      </div>
  </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import DatePicker from '@/Components/DatePicker.vue';
import Paginator from '@/Components/Paginator.vue';
import { MagnifyingGlassIcon, BackspaceIcon, ArrowDownTrayIcon } from '@heroicons/vue/20/solid';
import MultiSelect from '@/Components/MultiSelect.vue';
import moment from 'moment';
import SearchInput from '@/Components/SearchInput.vue';
import TableData from '@/Components/TableData.vue';
import TableHead from '@/Components/TableHead.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ref, onMounted, watch, computed } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import axios from 'axios';
import fileDownload from 'js-file-download'
import { useToast } from "vue-toastification";

const props = defineProps({
    cardTerminalOptions: Object,
    paymentMethods: Object,
    operatorOptions: Object,
    dailySummary: Object,
    totals: [Object, Array],
})

const authOperator = usePage().props.auth.operator
const operatorCountry = usePage().props.auth.operatorCountry
const permissions = usePage().props.auth.permissions
const toast = useToast()

const paymentMethodOptions = ref([])
const operatorOptions = ref([])
const successfulOptions = ref([])
const numberPerPageOptions = ref([])
const loadingCsv = ref(false)

const filters = ref({
    codes: '',
    channel_codes: '',
    paymentMethods: [],
    operators: [],
    is_payment_received: '',
    date_from: moment().format('YYYY-MM-DD'),
    date_to: moment().format('YYYY-MM-DD'),
    sortKey: '',
    sortBy: false,
    numberPerPage: 50,
})

onMounted(() => {
    // The standalone "Credit Card" entry is replaced by one synthetic
    // entry per card terminal — "Credit Card (Nayax)", "Credit Card (Nets)"
    // etc. — using ids of the form "cc:<terminal>". The backend
    // scopeFilterTransactionIndex decodes these into
    // (payment_method = Credit Card AND cashless_mfg = <terminal>).
    // The standalone Card Terminal filter was removed as part of this change.
    const terminalNames = (props.cardTerminalOptions?.data ?? [])
    paymentMethodOptions.value = [
        {id: 'all', name: 'All'},
        ...props.paymentMethods.data
            .filter((pm) => pm.name !== 'Credit Card')
            .map((pm) => ({id: pm.id, name: pm.name})),
        ...terminalNames.map((t) => ({id: `cc:${t}`, name: `Credit Card (${t})`})),
    ]
    operatorOptions.value = [
        {id: 'all', full_name: 'All'},
        ...(props.operatorOptions?.data ?? []).map((data) => {return {id: data.id, code: data.code, full_name: data.full_name}})
    ]
    successfulOptions.value = [
        {id: 'all', value: 'All'},
        {id: 'true', value: 'Successful'},
        {id: 'false', value: 'Unsuccessful'},
    ]
    numberPerPageOptions.value = [
        { id: 50, value: 50 },
        { id: 100, value: 100 },
        { id: 200, value: 200 },
        { id: 500, value: 500 },
        { id: 'All', value: 'All' },
    ]
    filters.value.numberPerPage = numberPerPageOptions.value[0]
    filters.value.paymentMethods = [paymentMethodOptions.value[0]]
    filters.value.is_payment_received = successfulOptions.value[0]
    // Match Transaction.vue default: bind to the user's operator; for HIPL,
    // also fan out to HIMD/LEA/HIESG/UL-ST so HQ users see all sister operators.
    filters.value.operators = authOperator ? [
        operatorOptions.value.find(o => o.id === authOperator.id),
        ...(authOperator.code == 'HIPL' ? [
            operatorOptions.value.find(o => o.code == 'HIMD'),
            operatorOptions.value.find(o => o.code == 'LEA'),
            operatorOptions.value.find(o => o.code == 'HIESG'),
            operatorOptions.value.find(o => o.code == 'UL-ST'),
        ] : []),
    ].filter(o => o !== undefined) : [operatorOptions.value[0]]
})

// Match Transaction.vue: clamp the 'All' tag so the user can't combine 'All'
// with concrete operator selections — picking 'All' overrides everything.
watch(() => filters.value.operators, (newVal, oldVal) => {
    if (newVal && newVal.length > 1) {
        const hasAllNew = newVal.some(o => (o.id ?? o) === 'all');
        const hasAllOld = (oldVal || []).some(o => (o.id ?? o) === 'all');
        if (hasAllNew && !hasAllOld) {
            filters.value.operators = operatorOptions.value.filter(o => o.id === 'all');
        } else if (hasAllNew && hasAllOld) {
            filters.value.operators = newVal.filter(o => (o.id ?? o) !== 'all');
        }
    }
}, { deep: true });

// Successful payments / total payments rate, expressed at the rollup level.
const successRate = computed(() => {
    const total = Number(props.totals?.total_transaction_count || 0)
    const success = Number(props.totals?.success_count || 0)
    if (!total) return '0.00'
    return ((success / total) * 100).toFixed(2)
})

function rowSuccessRate(row) {
    const total = Number(row.total_transaction_count || 0)
    const success = Number(row.success_count || 0)
    if (!total) return '0.00'
    return ((success / total) * 100).toFixed(2)
}

function formatAmount(value) {
    const v = Number(value || 0) / Math.pow(10, operatorCountry.currency_exponent)
    return v.toLocaleString(undefined, {
        minimumFractionDigits: operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent,
        maximumFractionDigits: operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent,
    })
}

function formatCount(value) {
    return Number(value || 0).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

// Build the query params payload — mirrors filterTransactionIndex contract so
// the same scope works for both this page and the existing /vends/transactions.
function buildParams() {
    return {
        ...filters.value,
        channel_codes: filters.value.channel_codes,
        is_payment_received: filters.value.is_payment_received?.id ?? '',
        operators: (filters.value.operators ?? []).filter(o => o).map(o => o.id ?? o),
        paymentMethods: (filters.value.paymentMethods ?? []).map(pm => pm.id ?? pm),
        numberPerPage: filters.value.numberPerPage?.id ?? filters.value.numberPerPage,
    }
}

function onSearchFilterUpdated() {
    router.get('/vends/transactions-daily-summary', buildParams(), {
        preserveState: true,
        replace: true,
    })
}

function resetFilters() {
    router.get('/vends/transactions-daily-summary')
}

function sortTable(sortKey, inverse = false) {
    filters.value.sortBy = !filters.value.sortBy
    if (inverse && filters.value.sortKey != sortKey) {
        filters.value.sortBy = !filters.value.sortBy
    }
    filters.value.sortKey = sortKey
    onSearchFilterUpdated()
}

function onExportCsvClicked() {
    loadingCsv.value = true
    axios({
        method: 'get',
        url: '/vends/transactions-daily-summary/export-csv',
        params: buildParams(),
        responseType: 'blob',
    })
    .then(response => {
        fileDownload(response.data, 'Daily_Summary_' + moment().format('YYMMDDhhmmss') + '.csv')
    })
    .catch(error => {
        console.error(error)
        toast.error('Export failed, please try again.', { timeout: 5000 })
    })
    .finally(() => {
        loadingCsv.value = false
    })
}
</script>
