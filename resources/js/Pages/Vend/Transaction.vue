
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
              <label for="text" class="block text-sm font-medium text-gray-700">
                  Vend ID
              </label>
                <MultiSelect
                    v-model="filters.codes"
                    :options="vendOptions"
                    valueProp="id"
                    label="code"
                    mode="tags"
                    placeholder="Select"
                    open-direction="bottom"
                    class="mt-1"
                >
                </MultiSelect>
            </div>
            <div class="col-span-5 md:col-span-1">
                <DatePicker
                    v-model="filters.date_from"
                    class="col-span-5 md:col-span-1"
                >
                    From
                </DatePicker>
            </div>
            <div class="col-span-5 md:col-span-1">
                <DatePicker
                    v-model="filters.date_to"
                    :minDate="filters.date_from"
                    class="col-span-5 md:col-span-1"
                >
                    To
                </DatePicker>
            </div>
            <div class="col-span-5 md:col-span-1">
                <label for="text" class="block text-sm font-medium text-gray-700">
                    Errors?
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
                   Pay Method
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
            <div class="col-span-5 md:col-span-1">
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
            <div class="col-span-5 md:col-span-1">
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
            <SearchInput placeholderStr="Cust ID" v-model="filters.customer_code">
                Cust ID
            </SearchInput>
            <SearchInput placeholderStr="Cust Name" v-model="filters.customer_name">
                Cust Name
            </SearchInput>
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
                        <TableHeadSort modelName="transaction_datetime" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('transaction_datetime')">
                            DateTime
                        </TableHeadSort>
                        <TableHead>
                            Code
                        </TableHead>
                        <TableHead>
                            Customer
                        </TableHead>
                        <TableHead>
                            Channel
                        </TableHead>
                        <TableHead>
                            Amount
                        </TableHead>
                        <TableHead>
                            Pay Method
                        </TableHead>
                        <TableHead>
                            Channels Error
                        </TableHead>
                        <TableHead>
                            Order ID
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
                            {{ vendTransaction.transaction_datetime }}
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-center">
                            {{ vendTransaction.vend.code }}
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-left">
                                <!-- {{  vend.latestVendBinding.customer.code }} -->
                                {{ vendTransaction.vend.latestVendBinding && vendTransaction.vend.latestVendBinding.customer ? vendTransaction.vend.latestVendBinding.customer.code : null }} <br>
                                {{ vendTransaction.vend.latestVendBinding && vendTransaction.vend.latestVendBinding.customer ? vendTransaction.vend.latestVendBinding.customer.name : null }}
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-center">
                            {{ vendTransaction.vendChannel.code }}
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-right">
                            {{ vendTransaction.amount }}
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-left">
                            {{ vendTransaction.paymentMethod ? vendTransaction.paymentMethod.name : null }}
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-left">
                            {{ vendTransaction.vend_channel_error_desc }}
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-center">
                            {{ vendTransaction.order_id }}
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
import { MagnifyingGlassIcon, BackspaceIcon } from '@heroicons/vue/20/solid';
import MultiSelect from '@/Components/MultiSelect.vue';
import moment from 'moment';
import SearchInput from '@/Components/SearchInput.vue';
import TableData from '@/Components/TableData.vue';
import TableHead from '@/Components/TableHead.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ref, onMounted } from 'vue';
import { Inertia } from '@inertiajs/inertia';
import { Head } from '@inertiajs/inertia-vue3';

const props = defineProps({
    categories: Object,
    categoryGroups: Object,
    paymentMethods: Object,
    vends: Object,
    vendTransactions: Object,
    vendChannelErrors: Object,
})
const categoryOptions = ref([])
const categoryGroupOptions = ref([])

onMounted(() => {
    vendOptions.value = props.vends.data.map((vend) => {return {id: vend.id, code: vend.code}})
    vendChannelErrorOptions.value = props.vendChannelErrors.data.map((error) => {return {id: error.id, desc: error.desc}})
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

    categoryOptions.value = props.categories.data.map((data) => {return {id: data.id, name: data.name}})
    categoryGroupOptions.value = props.categoryGroups.data.map((data) => {return {id: data.id, name: data.name}})
})

const filters = ref({
    codes: [],
    categories: [],
    categoryGroups: [],
    customer_code: '',
    customer_name: '',
    errors: [],
    paymentMethod: '',
    date_from: moment().startOf('month').toDate(),
    date_to: moment().toDate(),
    sortKey: '',
    sortBy: false,
    numberPerPage: 100,
})
const vendOptions = ref([])
const vendChannelErrorOptions = ref([])
const paymentMethodOptions = ref([])
const numberPerPageOptions = ref([])

function onSearchFilterUpdated() {
    Inertia.get('/vends/transactions', {
        ...filters.value,
        categories: filters.value.categories.map((category) => { return category.id }),
        categoryGroups: filters.value.categoryGroups.map((categoryGroup) => { return categoryGroup.id }),
        codes: filters.value.codes.map((code) => { return code.id }),
        errors: filters.value.errors.map((error) => { return error.id }),
        paymentMethod: filters.value.paymentMethod.id,
        numberPerPage: filters.value.numberPerPage.id,
    }, {
        preserveState: true,
        replace: true,
    })
}

function resetFilters() {
    Inertia.get('/vends/transactions')
}

function sortTable(sortKey) {
    filters.value.sortKey = sortKey
    filters.value.sortBy = !filters.value.sortBy
    onSearchFilterUpdated()
}
</script>