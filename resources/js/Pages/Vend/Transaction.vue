
<template>

  <Head title="Vending Machine" />

  <BreezeAuthenticatedLayout>
      <template #header>
          <h2 class="font-semibold text-xl text-gray-800 leading-tight">
              VM Transactions
          </h2>
      </template>

      <div class="m-2 sm:mx-5 sm:my-3 px-2 sm:px-4 lg:px-6">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-5 px-3 md:px-6 py-6 ">
          <!-- <div class="flex flex-col md:flex-row md:space-x-3 space-y-1 md:space-y-0"> -->
          <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
            <div class="col-span-5 md:col-span-1">
              <label for="text" class="block text-sm font-medium text-gray-700">
                  Code
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
          </div>

          <div class="flex justify-between mt-5">
                <div class="mt-3">
                    <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    @click="onSearchFilterUpdated()"
                    >
                        <SearchIcon class="h-4 w-4" aria-hidden="true"/>
                        <span>
                            Search
                        </span>
                    </Button>
                </div>
                <div class="flex flex-col space-y-2">
                    <p class="text-sm text-gray-700 leading-5 flex space-x-1">
                        <span>Showing</span>
                        <span class="font-medium">{{ vendTransactions.meta.from }}</span>
                        <span>to</span>
                        <span class="font-medium">{{ vendTransactions.meta.to }}</span>
                        <span>of</span>
                        <span class="font-medium">{{ vendTransactions.meta.total }}</span>
                        <span>results</span>
                    </p>
                    <MultiSelect
                        v-model="filters.numberPerPage"
                        :options="[
                                { id: 100, value: 100 },
                                { id: 200, value: 200 },
                                { id: 500, value: 500 },
                                { id: 'All', value: 'All' },
                            ]"
                        trackBy="id"
                        valueProp="id"
                        label="value"
                        placeholder="Select"
                        open-direction="bottom"
                        class="mt-1"
                        @change="onSearchFilterUpdated()"
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
                          <th scope="col"
                              class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                              #
                          </th>
                          <th scope="col"
                              class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                              <div class="flex justify-center">
                                  <a href="#" class="text-blue-600 hover:text-blue-800" @click="sortTable('transaction_datetime')">
                                      DateTime
                                  </a>
                                  <div class="pt-0.5 pl-0.5 text-blue-600 hover:text-blue-800">
                                      <span v-if="filters.sortKey === 'transaction_datetime' && filters.sortBy">
                                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                          <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                          </svg>
                                      </span>
                                      <span v-if="filters.sortKey === 'transaction_datetime' && !filters.sortBy">
                                          <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                              <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                                          </svg>
                                      </span>
                                  </div>
                              </div>
                          </th>
                          <th scope="col"
                              class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                              Code
                          </th>
                          <th scope="col"
                              class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                              Channel
                          </th>
                          <th scope="col"
                              class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                              Amount
                          </th>
                          <th scope="col"
                              class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                              Pay Method
                          </th>
                          <th scope="col"
                              class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                              Channels Error
                          </th>
                          <th scope="col"
                              class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                              Order ID
                          </th>
                      </tr>
                  </thead>
                  <tbody class="bg-white">
                      <tr v-for="(vendTransaction, vendTransactionIndex) in vendTransactions.data" :key="vendTransaction.id"
                          class="divide-x divide-gray-200">
                          <td :class="[vendTransactionIndex !== vendTransactions.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-600 sm:pl-6 lg:pl-8']"
                              class="text-center">
                              {{ vendTransactions.meta.from + vendTransactionIndex }}
                          </td>
                          <td :class="[vendTransactionIndex !== vendTransactions.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-800 sm:pl-6 lg:pl-8']"
                              class="text-left">
                              {{ vendTransaction.transaction_datetime }}
                          </td>
                          <td :class="[vendTransactionIndex !== vendTransactions.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-800 sm:pl-6 lg:pl-8']"
                              class="text-right">
                              {{ vendTransaction.vend_code }}
                          </td>
                          <td :class="[vendTransactionIndex !== vendTransactions.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-800 sm:pl-6 lg:pl-8']"
                              class="text-right">
                              {{ vendTransaction.vend_channel_code }}
                          </td>
                          <td :class="[vendTransactionIndex !== vendTransactions.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-800 sm:pl-6 lg:pl-8']"
                              class="text-right">
                              {{ vendTransaction.amount }}
                          </td>
                          <td :class="[vendTransactionIndex !== vendTransactions.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-800 sm:pl-6 lg:pl-8']"
                              class="text-left">
                              {{ vendTransaction.payment_method_name }}
                          </td>
                          <td :class="[vendTransactionIndex !== vendTransactions.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-800 sm:pl-6 lg:pl-8']"
                              class="text-right">
                              {{ vendTransaction.vend_channel_error_desc }}
                          </td>
                          <td :class="[vendTransactionIndex !== vendTransactions.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-800 sm:pl-6 lg:pl-8']"
                              class="text-left">
                              {{ vendTransaction.order_id }}
                          </td>
                      </tr>
                  </tbody>
              </table>
              <Paginator :links="vendTransactions.links" :meta="vendTransactions.meta"></Paginator>
          </div>
      </div>
      </div>
  </div>
  </BreezeAuthenticatedLayout>
</template>

<script>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import DatePicker from '@/Components/DatePicker.vue';
import OptionDropdown from '@/Components/OptionDropdown.vue';
import Paginator from '@/Components/Paginator.vue';
import { SearchIcon } from '@heroicons/vue/solid';
import SearchInput from '@/Components/SearchInput.vue';
import { debounce } from 'lodash';
  import MultiSelect from '@/Components/MultiSelect.vue';
import moment from 'moment';

export default {
  components: {
  BreezeAuthenticatedLayout,
  Button,
  DatePicker,
  MultiSelect,
  OptionDropdown,
  Paginator,
  SearchIcon,
  SearchInput,
},
  props: {
      paymentMethods: Object,
      vends: Object,
      vendTransactions: Object,
      vendChannelErrors: Object,
  },
  created() {
      this.vendOptions = this.vends.data.map((vend) => {return {id: vend.id, code: vend.code}})
      this.vendChannelErrorOptions = this.vendChannelErrors.data.map((error) => {return {id: error.id, desc: error.desc}})
      this.paymentMethodOptions = [
        {id: '', name: 'All'},
        ...this.paymentMethods.data.map((paymethod) => {return {id: paymethod.id, name: paymethod.name}})
      ]
  },
  data() {
      return {
          filters: this.getFiltersDefault(),
          vendOptions: [],
          vendChannelErrorOptions: [],
          paymentMethodOptions: [],
      }
  },
  watch: {
        'filters.numberPerPage' () {
            this.onSearchFilterUpdated()
        }
    },
  methods: {
      getFiltersDefault() {
          return {
              codes: [],
              errors: [],
              paymentMethod: '',
              date_from: moment().startOf('month').toDate(),
              date_to: moment().toDate(),
              sortKey: '',
              sortBy: true,
              numberPerPage: 100,
          }
      },
      onSearchFilterUpdated: debounce(function() {
          this.$inertia.get('/vends/transactions', this.filters, {
              preserveState: true,
              replace: true,
          })
      }, 500),
      sortTable(sortKey) {
          this.filters.sortKey = sortKey
          this.filters.sortBy = !this.filters.sortBy
          this.onSearchFilterUpdated()
      },
  },
}
</script>