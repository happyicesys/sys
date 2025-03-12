
<template>

  <Head title="Payment Gateway Transactions" />

  <BreezeAuthenticatedLayout>
      <template #header>
          <h2 class="font-semibold text-xl text-gray-800 leading-tight">
              Payment Gateway (Transactions)
          </h2>
      </template>

      <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
        <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
          <!-- <div class="flex flex-col md:flex-row md:space-x-3 space-y-1 md:space-y-0"> -->
          <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
            <div class="col-span-5 md:col-span-1">
                <SearchInput placeholderStr="Order ID" v-model="filters.ref_id" @keyup.enter="onSearchFilterUpdated()">
                    Ref ID
                </SearchInput>
            </div>
            <div class="col-span-5 md:col-span-1">
                <SearchInput placeholderStr="Order ID" v-model="filters.order_id" @keyup.enter="onSearchFilterUpdated()">
                    Order ID
                </SearchInput>
            </div>
            <div class="col-span-5 md:col-span-1">
                <SearchInput placeholderStr="Machine ID" v-model="filters.codes" @keyup.enter="onSearchFilterUpdated()">
                    Machine ID
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
            <!-- <div class="col-span-5 md:col-span-1">
                <label for="text" class="block text-sm font-medium text-gray-700">
                   Payment Method
                </label>
                <MultiSelect
                    v-model="filters.paymentMethod"
                    :options="paymentMethodOptions"
                    valueProp="id"
                    label="name"
                    placeholder="Select"
                    open-direction="bottom"
                    class="mt-1"
                >
                </MultiSelect>
            </div> -->
            <div class="col-span-5 md:col-span-1" v-if="permissions.includes('admin-access transactions')">
                <SearchInput placeholderStr="Customer" v-model="filters.customer" @keyup.enter="onSearchFilterUpdated()">
                    Customer
                </SearchInput>
            </div>
            <!-- <div class="col-span-5 md:col-span-1" v-if="permissions.includes('admin-access transactions')">
                <SearchInput placeholderStr="Product ID" v-model="filters.product_code" @keyup.enter="onSearchFilterUpdated()">
                    Product ID
                </SearchInput>
            </div>
            <div class="col-span-5 md:col-span-1" v-if="permissions.includes('admin-access transactions')">
                <SearchInput placeholderStr="Product Name" v-model="filters.product_name" @keyup.enter="onSearchFilterUpdated()">
                    Product Name
                </SearchInput>
            </div> -->
            <div v-if="permissions.includes('admin-access transactions')">
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
                    Is Refunded?
                </label>
                <MultiSelect
                    v-model="filters.is_refunded"
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
            </div>

          <div class="flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5">
                <div class="mt-3">
                    <div class="flex space-x-1">
                        <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        @click.prevent="onSearchFilterUpdated()"
                        >
                            <MagnifyingGlassIcon class="h-4 w-4" aria-hidden="true"/>
                            <span>
                                Search
                            </span>
                        </Button>
                        <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        @click.prevent="resetFilters()"
                        >
                            <BackspaceIcon class="h-4 w-4" aria-hidden="true"/>
                            <span>
                                Reset
                            </span>
                        </Button>
                        <Button type="button" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-400 hover:bg-gray-100"
                            @click.prevent="onExportExcelClicked()">
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
                <div class="flex flex-col space-y-2">
                    <p class="text-sm text-gray-700 leading-5 flex space-x-1">
                        <span>Showing</span>
                        <span class="font-medium">{{ paymentGatewayLogs.meta.from ?? 0 }}</span>
                        <span>to</span>
                        <span class="font-medium">{{ paymentGatewayLogs.meta.to ?? 0 }}</span>
                        <span>of</span>
                        <span class="font-medium">{{ paymentGatewayLogs.meta.total }}</span>
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

        <!-- <dl class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-5"> -->
        <dl class="grid grid-cols-1 md:grid-cols-4 gap-2">
            <div class="col-span-1 overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow">
                <dt class="truncate text-sm font-medium text-gray-500">Paid Count</dt>
                <dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
                    {{(totals['paid_count'] ? totals['paid_count'] : 0)}}
                </dd>
            </div>
            <div class="col-span-1 overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow">
                <dt class="truncate text-sm font-medium text-gray-500">Dispense Count</dt>
                <dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
                    {{(totals['dispense_count'] ? totals['dispense_count'] : 0)}}
                </dd>
            </div>
            <div class="col-span-1 overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow">
                <dt class="truncate text-sm font-medium text-gray-500">Refund Count</dt>
                <dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
                    {{(totals['refund_count'] ? totals['refund_count'] : 0)}}
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
                        <TableHead>
                            Ref ID
                        </TableHead>
                        <TableHead>
                            Order ID
                        </TableHead>
                        <TableHead>
                            Dispensed?
                        </TableHead>
                        <TableHeadSort modelName="approved_at" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('approved_at')">
                            Paid At
                        </TableHeadSort>
                        <TableHead>
                            Machine ID
                        </TableHead>
                        <TableHead>
                            Machine Prefix
                        </TableHead>
                        <TableHead>
                            Customer
                        </TableHead>
                        <TableHead>
                            Operator
                        </TableHead>
                        <TableHeadSort modelName="amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('amount', true)">
                            Amount {{ operatorCountry.currency_symbol }}
                        </TableHeadSort>
                        <TableHead>
                            Payment Method
                        </TableHead>
                        <TableHead>
                            Refunded?
                        </TableHead>
                      </tr>
                  </thead>
                  <tbody class="bg-white">
                    <template v-for="(paymentGatewayLog, paymentGatewayLogIndex) in paymentGatewayLogs.data" :key="paymentGatewayLog.id">
                      <tr class="divide-x" :class="paymentGatewayLog.is_multiple ? 'divide-x bg-gray-100' : ''">
                        <TableData :currentIndex="paymentGatewayLogIndex" :totalLength="paymentGatewayLogs.length" inputClass="text-center">
                            {{ paymentGatewayLogs.meta.from + paymentGatewayLogIndex }}
                        </TableData>
                        <TableData :currentIndex="paymentGatewayLogIndex" :totalLength="paymentGatewayLogs.length" inputClass="text-center">
                            {{ paymentGatewayLog.ref_id }}
                        </TableData>
                        <TableData :currentIndex="paymentGatewayLogIndex" :totalLength="paymentGatewayLogs.length" inputClass="text-center">
                          <div class="flex justify-between">
                            <span>
                              {{ paymentGatewayLog.order_id }}
                            </span>
                          </div>
                        </TableData>
                        <TableData :currentIndex="paymentGatewayLogIndex" :totalLength="paymentGatewayLogs.length" inputClass="text-center">
                          <div class="flex justify-center">
                              <CheckCircleIcon class="h-4 w-4 text-green-500" aria-hidden="true" v-if="paymentGatewayLog.vendTransaction"/>
                              <XCircleIcon class="h-4 w-4 text-red-500" aria-hidden="true" v-if="!paymentGatewayLog.vendTransaction"/>
                          </div>
                        </TableData>
                        <TableData :currentIndex="paymentGatewayLogIndex" :totalLength="paymentGatewayLogs.length" inputClass="text-center">
                            {{ paymentGatewayLog.approved_at_formatted }}
                        </TableData>
                        <TableData :currentIndex="paymentGatewayLogIndex" :totalLength="paymentGatewayLogs.length" inputClass="text-center">
                            {{ paymentGatewayLog.vend_code }}
                        </TableData>
                        <TableData :currentIndex="paymentGatewayLogIndex" :totalLength="paymentGatewayLogs.length" inputClass="text-center">
                            {{ paymentGatewayLog.vend?.vendPrefix?.name }}
                        </TableData>
                        <TableData :currentIndex="paymentGatewayLogIndex" :totalLength="paymentGatewayLogs.length" inputClass="text-left">
                            <span v-if="paymentGatewayLog.vend?.customer">
                                {{ paymentGatewayLog.vend?.customer?.virtual_customer_code }} <br>
                                {{ paymentGatewayLog.vend?.customer?.name }}
                            </span>
                        </TableData>
                        <TableData :currentIndex="paymentGatewayLogIndex" :totalLength="paymentGatewayLogs.length" inputClass="text-center">
                            {{ paymentGatewayLog.operatorPaymentGateway?.operator?.code }}
                        </TableData>
                        <!-- <TableData :currentIndex="paymentGatewayLogIndex" :totalLength="paymentGatewayLogs.length" inputClass="text-center">
                            <span v-if="!paymentGatewayLog.is_multiple">
                                {{ paymentGatewayLog.vend_channel_code }}
                            </span>
                        </TableData>
                        <TableData :currentIndex="paymentGatewayLogIndex" :totalLength="paymentGatewayLogs.length" inputClass="text-center">
                            {{ paymentGatewayLog.product_code }}
                        </TableData>
                        <TableData :currentIndex="paymentGatewayLogIndex" :totalLength="paymentGatewayLogs.length" inputClass="text-left">
                            {{ paymentGatewayLog.product_name }}
                        </TableData> -->
                        <TableData :currentIndex="paymentGatewayLogIndex" :totalLength="paymentGatewayLogs.length" inputClass="text-right">
                            {{ paymentGatewayLog.amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                        </TableData>
                        <TableData :currentIndex="paymentGatewayLogIndex" :totalLength="paymentGatewayLogs.length" inputClass="text-center">
                            {{ paymentGatewayLog.method }}
                        </TableData>
                        <TableData :currentIndex="paymentGatewayLogIndex" :totalLength="paymentGatewayLogs.length" inputClass="text-center">
                            <div class="flex justify-center">
                                <CheckCircleIcon class="h-4 w-4 text-green-500" aria-hidden="true" v-if="paymentGatewayLog.status == 98"/>
                            </div>
                        </TableData>
                      </tr>
                    </template>
                    <tr v-if="!paymentGatewayLogs || !paymentGatewayLogs.data.length">
                        <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                            No Results Found
                        </td>
                    </tr>
                  </tbody>
              </table>
              <Paginator v-if="paymentGatewayLogs.data.length" :links="paymentGatewayLogs.links" :meta="paymentGatewayLogs.meta"></Paginator>
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
import { MagnifyingGlassIcon, BackspaceIcon, CheckCircleIcon, XCircleIcon } from '@heroicons/vue/20/solid';
import MultiSelect from '@/Components/MultiSelect.vue';
import moment from 'moment';
import SearchInput from '@/Components/SearchInput.vue';
import TableData from '@/Components/TableData.vue';
import TableHead from '@/Components/TableHead.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    operatorOptions: Object,
    paymentMethods: Object,
    paymentGatewayLogs: Object,
    totals: [Object, Array],
})
const authOperator = usePage().props.auth.operator
const booleanOptions = ref([])
const successfulOptions = ref([])
const operatorCountry = usePage().props.auth.operatorCountry
const operatorOptions = ref([])
const permissions = usePage().props.auth.permissions
const vmcByteOptions = ref([])

onMounted(() => {
    filters.value.visited = true
    paymentMethodOptions.value = [
        {id: '', name: 'All'},
        ...props.paymentMethods.data.map((paymethod) => {return {id: paymethod.id, name: paymethod.name}})
    ]
    numberPerPageOptions.value = [
        { id: 50, value: 50 },
        { id: 100, value: 100 },
        { id: 200, value: 200 },
        { id: 500, value: 500 },
        { id: 'All', value: 'All' },
    ]
    filters.value.numberPerPage = numberPerPageOptions.value[0]
    filters.value.paymentMethod = paymentMethodOptions.value[0]

    booleanOptions.value = [
        {id: 'all', value: 'All'},
        {id: 'true', value: 'Yes'},
        {id: 'false', value: 'No'},
    ]
    operatorOptions.value = [
        {id: 'all', full_name: 'All'},
        ...props.operatorOptions.data.map((data) => {return {id: data.id, code:data.code, full_name: data.full_name}})
    ]
    successfulOptions.value = [
        {id: 'all', value: 'All'},
        {id: 'true', value: 'Successful'},
        {id: 'false', value: 'Unsuccessful'},
    ]
    vmcByteOptions.value = [
        {id: 'all', value: 'All'},
        {id: '0', value: '0'},
        {id: '1', value: '1'},
        {id: '50', value: '50'},
    ]
    filters.value.operators = authOperator ? [
		operatorOptions.value.find(operator => operator.id === authOperator.id),
		...authOperator.code == 'HIPL' ? [
			operatorOptions.value.find(operator => operator.code == 'HIMD'),
			operatorOptions.value.find(operator => operator.code == 'LEA'),
			operatorOptions.value.find(operator => operator.code == 'DCVIC'),
		] : [],
	] : operatorOptions.value[0]
    filters.value.interface_type = vmcByteOptions.value[0]
    filters.value.is_refunded = booleanOptions.value[0]
})

const filters = ref({
    ref_id: '',
    codes: '',
    customer: '',
    operators: [],
    order_id: '',
    is_refunded: '',
    paymentMethod: '',
    date_from: moment().format('YYYY-MM-DD'),
    date_to: moment().format('YYYY-MM-DD'),
    sortKey: '',
    sortBy: false,
    numberPerPage: 50,
    visited: true,
})
const loading = ref(false)
const paymentMethodOptions = ref([])
const numberPerPageOptions = ref([])

function onExportExcelClicked() {
    loading.value = true
    axios({
        method: 'get',
        url: '/vends/payment-gateway-transactions/excel',
        params: {
            ...filters.value,
            ref_id: filters.value.ref_id,
            codes: filters.value.codes,
            operators: filters.value.operators.map((operator) => { return operator.id }),
            is_refunded: filters.value.is_refunded.id,
            paymentMethod: filters.value.paymentMethod.id,
            numberPerPage: filters.value.numberPerPage.id,
        },
        responseType: 'blob',
    }).then(response => {
        fileDownload(response.data, 'Payment_Gateway_Transaction_' + moment().format('YYMMDDhhmmss') +'.xlsx')
    }).catch(error => {
        console.log(error)
    }).finally(() => {
        loading.value = false
    })
}

function onSearchFilterUpdated() {
    router.get('/vends/payment-gateway-transactions', {
        ...filters.value,
        ref_id: filters.value.ref_id,
        codes: filters.value.codes,
        operators: filters.value.operators.map((operator) => { return operator.id }),
        is_refunded: filters.value.is_refunded.id,
        paymentMethod: filters.value.paymentMethod.id,
        numberPerPage: filters.value.numberPerPage.id,
    }, {
        preserveState: true,
        replace: true,
    })
}

function resetFilters() {
    router.get('/vends/payment-gateway-transactions')
}

function sortTable(sortKey, inverse = false) {
  filters.value.sortBy = !filters.value.sortBy
  if(inverse && filters.value.sortKey != sortKey) {
      filters.value.sortBy = !filters.value.sortBy
  }
  filters.value.sortKey = sortKey
  onSearchFilterUpdated()
}
</script>