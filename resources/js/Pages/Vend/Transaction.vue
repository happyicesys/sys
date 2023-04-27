
<template>

  <Head title="VM Transactions" />

  <BreezeAuthenticatedLayout>
      <template #header>
          <h2 class="font-semibold text-xl text-gray-800 leading-tight">
              Vending Machines (Transactions)
          </h2>
      </template>

      <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
        <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
          <!-- <div class="flex flex-col md:flex-row md:space-x-3 space-y-1 md:space-y-0"> -->
          <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
            <div class="col-span-5 md:col-span-1">
                <SearchInput placeholderStr="Vend ID" v-model="filters.codes" @keyup.enter="onSearchFilterUpdated()">
                    Vend ID
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
            <div class="col-span-5 md:col-span-1">
                <label for="text" class="block text-sm font-medium text-gray-700">
                    Channel Errors
                </label>
                <MultiSelect
                    v-model="filters.errors"
                    :options="vendChannelErrorOptions"
                    valueProp="id"
                    label="desc"
                    mode="tags"
                    placeholder="Select"
                    open-direction="bottom"
                    class="mt-1"
                >
                </MultiSelect>
            </div>
            <div class="col-span-5 md:col-span-1">
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
            </div>
            <div class="col-span-5 md:col-span-1" v-if="permissions.includes('admin-access transactions')">
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
            <div class="col-span-5 md:col-span-1" v-if="permissions.includes('admin-access transactions')">
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
            <div class="col-span-5 md:col-span-1" v-if="permissions.includes('admin-access transactions')">
                <SearchInput placeholderStr="Cust ID" v-model="filters.customer_code" @keyup.enter="onSearchFilterUpdated()">
                    Cust ID
                </SearchInput>
            </div>
            <div class="col-span-5 md:col-span-1" v-if="permissions.includes('admin-access transactions')">
                <SearchInput placeholderStr="Cust Name" v-model="filters.customer_name" @keyup.enter="onSearchFilterUpdated()">
                    Cust Name
                </SearchInput>
            </div>
            <div v-if="permissions.includes('admin-access transactions')">
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
            <div v-if="permissions.includes('admin-access transactions')">
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
            <div>
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
                        <Button class="inline-flex space-x-1 items-center rounded-md border border-gray-500 bg-white px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
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
                        <span class="font-medium">{{ vendTransactions.meta.from ?? 0 }}</span>
                        <span>to</span>
                        <span class="font-medium">{{ vendTransactions.meta.to ?? 0 }}</span>
                        <span>of</span>
                        <span class="font-medium">{{ vendTransactions.meta.total }}</span>
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

        <dl class="mt-2 grid grid-cols-1 gap-5 sm:grid-cols-3">
            <div class="overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow">
                <dt class="truncate text-sm font-medium text-gray-500">Total Amount (Success)</dt>
                <dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
                    {{totals.amount.toLocaleString(undefined, {minimumFractionDigits: 2})}}
                </dd>
            </div>
            <div class="overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow">
                <dt class="truncate text-sm font-medium text-gray-500">Total Count (Success)</dt>
                <dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
                    {{totals.count.toLocaleString(undefined, {minimumFractionDigits: 0})}}
                </dd>
            </div>
        </dl>
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
                        <TableHead>
                            Order ID
                        </TableHead>
                        <TableHeadSort modelName="transaction_datetime" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('transaction_datetime')">
                            Transaction DateTime
                        </TableHeadSort>
                        <TableHead>
                            Vend ID
                        </TableHead>
                        <TableHead>
                            Customer Name
                        </TableHead>
                        <TableHead>
                            Channel
                        </TableHead>
                        <TableHead>
                            Product Code
                        </TableHead>
                        <TableHead>
                            Product Name
                        </TableHead>
                        <TableHead>
                            Amount
                        </TableHead>
                        <TableHead>
                            Payment Method
                        </TableHead>
                        <TableHead>
                            Channels Error
                        </TableHead>
                        <TableHead>
                            Payment Received
                        </TableHead>
                      </tr>
                  </thead>
                  <tbody class="bg-white">
                      <tr v-for="(vendTransaction, vendTransactionIndex) in vendTransactions.data" :key="vendTransaction.id"
                          class="divide-x divide-gray-200">
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-center">
                            {{ vendTransactions.meta.from + vendTransactionIndex }}
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-center">
                            {{ vendTransaction.order_id }}
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-center">
                            {{ vendTransaction.transaction_datetime }}
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-center">
                            {{ vendTransaction.vend.code }}
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-left">
                            <span v-if="vendTransaction.vendJson && 'latest_vend_binding' in vendTransaction.vendJson && 'customer' in vendTransaction.vendJson['latest_vend_binding']">
                                {{ vendTransaction.vendJson['latest_vend_binding']['customer']['code'] }} <br>
                                {{ vendTransaction.vendJson['latest_vend_binding']['customer']['name'] }}
                            </span>
                            <span v-else-if="!vendTransaction.vendJson && vendTransaction.vend.latestVendBinding && vendTransaction.vend.latestVendBinding.customer">
                                {{ vendTransaction.vend.latestVendBinding.customer.code }} <br>
                                {{ vendTransaction.vend.latestVendBinding.customer.name }}
                            </span>
                            <span v-else>
                                {{ vendTransaction.vend.name }}
                            </span>
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-center">
                            {{ vendTransaction.vendChannel.code }}
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-center">
                            <span v-if="vendTransaction.productJson && 'code' in vendTransaction.productJson">
                                {{ vendTransaction.productJson['code'] }}
                            </span>
                            <span v-else-if="!vendTransaction.productJson && vendTransaction.product && vendTransaction.product.code">
                                {{ vendTransaction.product.code }}
                            </span>
                            <span v-else>
                            </span>
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-left">
                            <span v-if="vendTransaction.productJson && 'name' in vendTransaction.productJson">
                                {{ vendTransaction.productJson['name'] }}
                            </span>
                            <span v-else-if="!vendTransaction.productJson && vendTransaction.product && vendTransaction.product.name">
                                {{ vendTransaction.product.name }}
                            </span>
                            <span v-else>
                            </span>
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-right">
                            {{ vendTransaction.amount }}
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-center">
                            {{ vendTransaction.paymentMethod ? vendTransaction.paymentMethod.name : null }}
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-center">
                            {{ vendTransaction.vendChannelError ? vendTransaction.vendChannelError.desc : 0 }}
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-center">
                            <span v-if="vendTransaction.is_payment_received">
                                {{ vendTransaction.is_payment_received ? 'Successful' : 'Unsuccessful' }}
                            </span>
                            <span v-else>
                                {{ vendTransaction.vendTransactionJson ? (vendTransaction.vendTransactionJson['SErr'] == 0 || vendTransaction.vendTransactionJson['SErr'] == 6 ? 'Successful' : "Unsuccessful") : '' }}
                            </span>
                        </TableData>
                      </tr>
                      <tr v-if="!vendTransactions.data.length">
                            <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                                No Results Found
                            </td>
                        </tr>
                  </tbody>
              </table>
              <Paginator v-if="vendTransactions.data.length" :links="vendTransactions.links" :meta="vendTransactions.meta"></Paginator>
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
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
    categories: Object,
    categoryGroups: Object,
    operatorOptions: Object,
    locationTypeOptions: Object,
    paymentMethods: Object,
    vends: Object,
    vendTransactions: Object,
    totals: [Object, Array],
    vendChannelErrors: Object,
})
const booleanOptions = ref([])
const successfulOptions = ref([])
const categoryOptions = ref([])
const categoryGroupOptions = ref([])
const locationTypeOptions = ref([])
const roles = usePage().props.auth.roles
const permissions = usePage().props.auth.permissions
const operatorRole = usePage().props.auth.operatorRole

onMounted(() => {
    vendChannelErrorOptions.value = [
        {'id': 'errors_only', 'desc': 'Errors Only'},
        ...props.vendChannelErrors.data.map((error) => {return {id: error.id, desc: error.desc}})
    ]
    paymentMethodOptions.value = [
        {id: '', name: 'All'},
        ...props.paymentMethods.data.map((paymethod) => {return {id: paymethod.id, name: paymethod.name}})
    ]
    numberPerPageOptions.value = [
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
    categoryOptions.value = props.categories.data.map((data) => {return {id: data.id, name: data.name}})
    categoryGroupOptions.value = props.categoryGroups.data.map((data) => {return {id: data.id, name: data.name}})
    locationTypeOptions.value = [
        {id: 'all', value: 'All'},
        ...props.locationTypeOptions.data.map((data) => {return {id: data.id, value: data.name}})
    ]
    operatorOptions.value = [
        {id: 'all', full_name: 'All'},
        ...props.operatorOptions.data.map((data) => {return {id: data.id, full_name: data.full_name}})
    ]
    successfulOptions.value = [
        {id: 'all', value: 'All'},
        {id: 'true', value: 'Successful'},
        {id: 'false', value: 'Unsuccessful'},
    ]
    filters.value.locationType = locationTypeOptions.value[0]
    filters.value.operator = operatorOptions.value[0]
    filters.value.is_binded_customer = booleanOptions.value[0]
    filters.value.is_payment_received = booleanOptions.value[0]
})

const filters = ref({
    codes: '',
    channel_codes: '',
    categories: [],
    categoryGroups: [],
    customer_code: '',
    customer_name: '',
    errors: [],
    locationType: '',
    operator: '',
    is_binded_customer: '',
    is_payment_received: '',
    paymentMethod: '',
    date_from: moment().toDate(),
    date_to: moment().toDate(),
    sortKey: '',
    sortBy: false,
    numberPerPage: 100,
})
const operatorOptions = ref([])
// const vendOptions = ref([])
const vendChannelErrorOptions = ref([])
const loading = ref(false)
const paymentMethodOptions = ref([])
const numberPerPageOptions = ref([])

// function onExportExcelClicked() {
//     // window.open('/vends/transactions/excel', '_blank');
//     loading.value = true
//     axios({
//         method: 'get',
//         url: '/vends/transactions/excel',
//         params: {
//             ...filters.value,
//             categories: filters.value.categories.map((category) => { return category.id }),
//             categoryGroups: filters.value.categoryGroups.map((categoryGroup) => { return categoryGroup.id }),
//             channel_code: filters.value.channel_code,
//             errors: filters.value.errors.map((error) => { return error.id }),
//             operator_id: filters.value.operator.id,
//             paymentMethod: filters.value.paymentMethod.id,
//             numberPerPage: filters.value.numberPerPage.id,
//         },
//         responseType: 'blob',
//     }).then(response => {
//         fileDownload(response.data, 'Vending_Transaction_' + moment().format('YYMMDDhhmmss') +'.xlsx')
//         loading.value = false
//     }).catch(error => {
//         console.log(error.message)
//         loading.value = false
//     }).finally(() => {
//         loading.value = false
//     })
// }

function onExportExcelClicked() {
    // window.open('/vends/transactions/excel', '_blank');
    loading.value = true
    axios({
        method: 'get',
        url: '/vends/transactions/excel',
        params: {
            ...filters.value,
            categories: filters.value.categories.map((category) => { return category.id }),
            categoryGroups: filters.value.categoryGroups.map((categoryGroup) => { return categoryGroup.id }),
            channel_codes: filters.value.channel_codes,
            errors: filters.value.errors.map((error) => { return error.id }),
            location_type_id: filters.value.locationType.id,
            operator_id: filters.value.operator.id,
            is_binded_customer: filters.value.is_binded_customer.id,
            is_payment_received: filters.value.is_payment_received.id,
            paymentMethod: filters.value.paymentMethod.id,
            numberPerPage: filters.value.numberPerPage.id,
        },
        responseType: 'blob',
    }).then(response => {
        fileDownload(response.data, 'Vending_Transaction_' + moment().format('YYMMDDhhmmss') +'.xlsx')
    }).catch(error => {
        console.log(error)
    }).finally(() => {
        loading.value = false
    })
}

function onSearchFilterUpdated() {
    router.get('/vends/transactions', {
        ...filters.value,
        categories: filters.value.categories.map((category) => { return category.id }),
        categoryGroups: filters.value.categoryGroups.map((categoryGroup) => { return categoryGroup.id }),
        channel_codes: filters.value.channel_codes,
        errors: filters.value.errors.map((error) => { return error.id }),
        location_type_id: filters.value.locationType.id,
        operator_id: filters.value.operator.id,
        is_binded_customer: filters.value.is_binded_customer.id,
        is_payment_received: filters.value.is_payment_received.id,
        paymentMethod: filters.value.paymentMethod.id,
        numberPerPage: filters.value.numberPerPage.id,
    }, {
        preserveState: true,
        replace: true,
    })
}

function resetFilters() {
    router.get('/vends/transactions')
}

function sortTable(sortKey) {
    filters.value.sortKey = sortKey
    filters.value.sortBy = !filters.value.sortBy
    onSearchFilterUpdated()
}
</script>