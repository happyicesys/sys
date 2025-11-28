<template>

  <Head title="Daily Jobs" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Operation Daily Jobs
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
        <div class="flex space-x-1 justify-end">
          <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
          @click="onCreateClicked()"
          >
            <PlusIcon class="h-4 w-4" aria-hidden="true"/>
            <span>
              Create
            </span>
          </Button>
          <!-- <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
          @click="onCreateAddressClicked()"
          >
            <PlusIcon class="h-4 w-4" aria-hidden="true"/>
            <span>
              Create Start or Finish Point
            </span>
          </Button> -->
        </div>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-2 mt-2">
            <div class="col-span-5 md:col-span-1">
              <SearchInput placeholderStr="Job ID#" v-model="filters.ops_job_item_ref_id" class="block text-sm font-medium text-gray-700">
                Job ID#
					    </SearchInput>
            </div>
            <div class="col-span-5 md:col-span-1">
              <SearchInput placeholderStr="Machine ID" v-model="filters.vend_code" class="block text-sm font-medium text-gray-700">
                Machine ID#
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
                   Delivery By
                </label>
                <MultiSelect
                    v-model="filters.delivered_by"
                    :options="userOptions"
                    valueProp="id"
                    label="value"
                    placeholder="Select"
                    open-direction="bottom"
                    class="mt-1"
                    :disabled="isDriver"
                >
                </MultiSelect>
            </div>
            <div class="col-span-5 md:col-span-1">
                <label for="text" class="block text-sm font-medium text-gray-700">
                   Created By
                </label>
                <MultiSelect
                    v-model="filters.created_by"
                    :options="userOptions"
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
                  <span class="font-medium">{{ opsJobs.meta.from ?? 0 }}</span>
                  <span>to</span>
                  <span class="font-medium">{{ opsJobs.meta.to ?? 0 }}</span>
                  <span>of</span>
                  <span class="font-medium">{{ opsJobs.meta.total }}</span>
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
          <div class="overflow-scroll max-h-[600px] md:max-h-[800px] shadow-sm ring-1 ring-black ring-opacity-5">
            <table class="min-w-full border-separate" style="border-spacing: 0">
                <thead class="bg-gray-100">
                  <tr class="divide-x divide-gray-200">
                    <TableHead>
                      #
                    </TableHead>
                    <TableHead>
                      <div class="flex flex-col space-y-2">
                        <SingleSortItem modelName="date" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('date')">
                          Date
                        </SingleSortItem>
                        <span>
                          Deliver By
                        </span>
                        <span>
                          # of Job
                        </span>
                      </div>
                    </TableHead>
                    <TableHead>
                      <div class="flex flex-col space-y-2">
                        <span>
                          Jobs
                        </span>
                        <SingleSortItem modelName="ops_job_items_delivered_count_percentage" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('ops_job_items_delivered_count_percentage')">
                          Picked(%)
                        </SingleSortItem>
                        <SingleSortItem modelName="ops_job_items_delivered_count_percentage" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('ops_job_items_delivered_count_percentage')">
                          Stock-in(%)
                        </SingleSortItem>
                        <SingleSortItem modelName="ops_job_items_verified_count_percentage" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('ops_job_items_verified_count_percentage')">
                          Verified(%)
                        </SingleSortItem>
                        <SingleSortItem modelName="cms_transaction_percentage" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('cms_transaction_percentage')">
                          API Invoices(%)
                        </SingleSortItem>
                      </div>
                    </TableHead>
                    <TableHead>
                      <div class="flex flex-col space-y-2">
                        <span>
                          Picked
                        </span>
                        <SingleSortItem modelName="picked_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('picked_amount')">
                          Value
                        </SingleSortItem>
                        <SingleSortItem modelName="picked_count" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('picked_count')">
                          Qty
                        </SingleSortItem>
                        <SingleSortItem modelName="picked_cost" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('picked_cost')">
                          Cost
                        </SingleSortItem>
                        <!-- <SingleSortItem modelName="picked_cost" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('picked_cost')">
                          Gross Margin
                        </SingleSortItem> -->
                      </div>
                    </TableHead>
                    <TableHead>
                      <div class="flex flex-col space-y-2">
                        <span>
                          Stock-in
                        </span>
                        <SingleSortItem modelName="stock_in_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('stock_in_amount')">
                          Value
                        </SingleSortItem>
                        <SingleSortItem modelName="stock_in_count" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('stock_in_count')">
                          Qty
                        </SingleSortItem>
                        <SingleSortItem modelName="stock_in_cost" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('stock_in_cost')">
                          Cost
                        </SingleSortItem>
                        <!-- <SingleSortItem modelName="total_cash_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('total_cash_amount')">
                          Gross Margin
                        </SingleSortItem> -->
                      </div>
                    </TableHead>
                    <TableHead>
                      <div class="flex flex-col space-y-2">
                        <span>
                          Stock-out <br>
                          (Transactions)
                        </span>
                        <SingleSortItem modelName="total_cash_amount_from_vmc" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('total_cash_amount_from_vmc')">
                          Total Amount$
                        </SingleSortItem>
                        <SingleSortItem modelName="total_cash_amount_from_vmc" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('total_cash_amount_from_vmc')">
                          Qty
                        </SingleSortItem>
                        <!-- <SingleSortItem modelName="total_cash_amount_from_vmc" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('total_cash_amount_from_vmc')">
                          Cost
                        </SingleSortItem> -->
                        <!-- <SingleSortItem modelName="total_cash_amount_from_vmc" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('total_cash_amount_from_vmc')">
                          Gross Margin
                        </SingleSortItem> -->
                      </div>
                    </TableHead>
                    <TableHead>
                      <div class="flex flex-col space-y-2">
                        <span>
                          Cash Amount
                        </span>
                        <SingleSortItem modelName="total_cash_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('total_cash_amount')">
                          Cash Collected (fr VM)
                        </SingleSortItem>
                        <SingleSortItem modelName="total_cash_amount_from_vmc" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('total_cash_amount_from_vmc')">
                          CashAmt$ (VMC)
                        </SingleSortItem>
                        <span>
                          % Cash Collected/ CashAmt$
                        </span>
                        <SingleSortItem modelName="delta_cash_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('delta_cash_amount')">
                          Cash Adjustment
                        </SingleSortItem>
                      </div>
                    </TableHead>
                    <TableHead>
                      <div class="flex flex-col space-y-2">
                        <span>
                          Created By
                        </span>
                        <span>
                          Created At
                        </span>
                      </div>
                    </TableHead>
                  </tr>
                </thead>
                  <tbody class="bg-white">
                    <tr v-for="(opsJob, opsJobIndex) in opsJobs.data" :key="opsJob.id" class="divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100">
                      <TableData :currentIndex="opsJobIndex" :totalLength="opsJobs.length" inputClass="text-center">
                        {{ opsJobs.meta.from + opsJobIndex }}
                      </TableData>
                      <TableData :currentIndex="opsJobIndex" :totalLength="opsJobs.length" inputClass="text-center">
                        <div class="flex flex-col space-y-2">
                          <Link :href="'/ops-jobs/' + opsJob.id + '/edit'">
                            <div
                              class="inline-flex justify-center items-center rounded px-2 py-2 text-xs font-medium border min-w-fit text-blue-700 hover:cursor-pointer"
                              :class="[(opsJob.date_diff_count < 1 &&  opsJob.date_diff_count > 0) ? 'bg-green-200' : ((opsJob.date_diff_count > -1 && opsJob.date_diff_count < 0) ? 'bg-yellow-200' : '') ]"
                              v-if="opsJob.date"
                            >
                              <div class="flex flex-col space-y-1">
                                <span>
                                  {{ opsJob.date_formatted }}
                                </span>
                                <span>
                                  ({{ opsJob.date_day }})
                                </span>
                                <span>
                                  {{ opsJob.date_diff_human }}
                                </span>
                              </div>
                            </div>
                          </Link>
                          <span>
                            {{ opsJob.deliveredBy ? opsJob.deliveredBy.name : '' }}
                          </span>
                          <span>
                            {{ opsJob.ops_job_items_count }}
                          </span>
                        </div>
                      </TableData>
                      <TableData :currentIndex="opsJobIndex" :totalLength="opsJobs.length" inputClass="text-center align-top">
                        <div class="flex flex-col space-y-2">
                          <div
                            class="inline-flex justify-center items-center rounded px-1.5 text-xs font-medium border min-w-full text-gray-900"
                            :class="[opsJob.ops_job_items_picked_count_percentage == 100 ? 'text-green-700 bg-green-200' : '']"
                          >
                            <span>
                              {{ opsJob.ops_job_items_picked_count }} ({{ opsJob.ops_job_items_picked_count_percentage }}%)
                            </span>
                          </div>
                          <div
                            class="inline-flex justify-center items-center rounded px-1.5 text-xs font-medium border min-w-full text-gray-900"
                            :class="[opsJob.ops_job_items_delivered_count_percentage == 100 ? 'text-green-700 bg-green-200' : '']"
                          >
                            <span>
                              {{ opsJob.ops_job_items_delivered_count }} ({{ opsJob.ops_job_items_delivered_count_percentage }}%)
                            </span>
                          </div>
                          <div
                            class="inline-flex justify-center items-center rounded px-1.5 text-xs font-medium border min-w-full text-gray-900"
                            :class="[opsJob.ops_job_items_verified_count_percentage == 100 ? 'text-green-700 bg-green-200' : '']"
                          >
                            <span>
                              {{ opsJob.ops_job_items_verified_count }} ({{ opsJob.ops_job_items_verified_count_percentage }}%)
                            </span>
                          </div>
                          <div
                            class="inline-flex justify-center items-center rounded px-1.5 text-xs font-medium border min-w-full text-gray-900"
                            :class="[opsJob.cms_transaction_percentage == 100 ? 'text-green-700 bg-green-200' : '']"
                          >
                            <span>
                              {{ opsJob.cms_transaction_count }} ({{ opsJob.cms_transaction_percentage }}%)
                            </span>
                          </div>
                        </div>
                      </TableData>
                      <TableData :currentIndex="opsJobIndex" :totalLength="opsJobs.length" inputClass="text-center align-top">
                        <div class="flex flex-col space-y-2">
                          <span>
                            {{ operatorCountry.currency_symbol }}{{ opsJob.picked_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                          </span>
                          <span>
                            {{ opsJob.picked_count.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0}) }}
                          </span>
                          <span>
                            {{ operatorCountry.currency_symbol }}{{ opsJob.picked_cost.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                          </span>
                          <!-- <span v-if="opsJob.picked_gross_margin_amount">
                            {{ operatorCountry.currency_symbol }}{{ opsJob.picked_gross_margin_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }} ({{ opsJob.picked_gross_margin_percentage }}%)
                          </span> -->
                        </div>
                      </TableData>
                      <TableData :currentIndex="opsJobIndex" :totalLength="opsJobs.length" inputClass="text-center align-top">
                        <div class="flex flex-col space-y-2 min-w-24">
                          <span :class="[opsJob.stock_in_amount < opsJob.picked_amount ? 'text-red-700' : 'text-green-700']">
                            {{ operatorCountry.currency_symbol }}{{ opsJob.stock_in_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                          </span>
                          <span>
                            {{ opsJob.stock_in_count.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0}) }}
                          </span>
                          <span>
                            {{ operatorCountry.currency_symbol }}{{ opsJob.stock_in_cost.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                          </span>
                        </div>
                      </TableData>
                      <TableData :currentIndex="opsJobIndex" :totalLength="opsJobs.length" inputClass="text-center align-top">
                        <div class="flex flex-col space-y-2 min-w-24">
                          <span>
                            {{ operatorCountry.currency_symbol }}{{ opsJob.acc_vend_transactions_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                          </span>
                          <span>
                            {{ opsJob.acc_vend_transactions_count.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0}) }}
                          </span>
                          <!-- <span>
                            {{ operatorCountry.currency_symbol }}{{ opsJob.stock_in_cost.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                          </span> -->
                        </div>
                      </TableData>
                      <TableData :currentIndex="opsJobIndex" :totalLength="opsJobs.length" inputClass="text-center align-top">
                        <div class="flex flex-col space-y-2">
                          <span>
                            {{ operatorCountry.currency_symbol }}{{ opsJob.total_cash_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                          </span>
                          <span>
                            {{ operatorCountry.currency_symbol }}{{ opsJob.total_cash_amount_from_vmc.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                          </span>
                          <span>
                            {{ (opsJob.total_cash_amount/(opsJob.total_cash_amount_from_vmc > 0 ? opsJob.total_cash_amount_from_vmc : 1) * 100).toFixed(2) }}%
                          </span>
                          <span :class="[opsJob.delta_cash_amount > 0 ? 'text-green-600' : (opsJob.delta_cash_amount < 0 ? 'text-red-600' : '')]">
                            {{ operatorCountry.currency_symbol }}{{ opsJob.delta_cash_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                          </span>
                        </div>
                      </TableData>
                      <TableData :currentIndex="opsJobIndex" :totalLength="opsJobs.length" inputClass="text-center">
                        <div class="flex flex-col space-y-2">
                          <span>
                            {{ opsJob.createdBy ? opsJob.createdBy.name : '' }}
                          </span>
                          <span>
                            {{ opsJob.created_at }}
                          </span>
                        </div>
                      </TableData>
<!--
                      <TableData :currentIndex="opsJobIndex" :totalLength="opsJobs.length" inputClass="">
                        <div class="flex flex-col space-y-1 justify-items-center">
                          <Button
                            type="button"
                            class="bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex-col space-y-1 w-fit"
                            :class="[opsJob.vendPrefixes && opsJob.vendPrefixes.length > 0 ? 'opacity-50 cursor-not-allowed' : '']"
                            @click="onDeleteClicked(opsJob)"
                            :disabled="opsJob.vendPrefixes && opsJob.vendPrefixes.length > 0"
                          >
                            <span class="flex space-x-1 items-center">
                              <TrashIcon class="w-4 h-4"></TrashIcon>
                              <span>
                                  Delete
                              </span>
                            </span>
                            <span v-if="opsJob.vendPrefixes && opsJob.vendPrefixes.length > 0">
                              (Binded)
                            </span>
                          </Button>
                        </div>
                      </TableData> -->
                      </tr>
                <tr v-if="!opsJobs.data.length">
                  <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                      No Results Found
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator v-if="opsJobs.data.length" :links="opsJobs.links" :meta="opsJobs.meta"></Paginator>
          </div>
      </div>
    </div>
  </div>

  <Form
      v-if="showModal"
      :operatorOptions="operatorOptions"
      :opsJob="opsJob"
      :type="type"
      :showModal="showModal"
      :userOptions="userOptions"
      @modalClose="onModalClose"
  >
  </Form>
  <!-- <AddressForm
    v-if="showAddressModal"
    :startAddresses =
  >
  </AddressForm> -->
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import Form from '@/Pages/OpsJob/Form.vue';
import ChangeDriver from '@/Pages/OpsJob/ChangeDriver.vue';
import DatePicker from '@/Components/DatePicker.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { BackspaceIcon, MagnifyingGlassIcon, PencilSquareIcon, PlusIcon, TrashIcon, UserIcon } from '@heroicons/vue/20/solid';
import SingleSortItem from '@/Components/SingleSortItem.vue';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ref, onMounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";
import moment from 'moment';

const props = defineProps({
  operatorOptions: [Array, Object],
  opsJobs: Object,
  userOptions: [Array, Object],
})

const filters = ref({
  code: '',
  date_from: moment().subtract(3, 'days').format('YYYY-MM-DD'),
  date_to: moment().add(1, 'week').format('YYYY-MM-DD'),
  delivered_by: '',
  created_by: '',
  ops_job_item_ref_id: '',
  operators: [],
  vend_code: '',
  sortKey: '',
  sortBy: false,
  numberPerPage: 100,
})
const authOperator = usePage().props.auth.operator
const authUser = usePage().props.auth.user
const authRoles = usePage().props.auth.roles || []
const isDriver = authRoles.includes('driver')
const showAddressModal = ref(false)
const showModal = ref(false)
const showChangeDriverModal = ref(false)
const opsJob = ref()
const type = ref('')
const toast = useToast()
const operatorCountry = usePage().props.auth.operatorCountry
const operatorOptions = ref([])
const numberPerPageOptions = ref([])
const userOptions = ref([])


onMounted(() => {
  numberPerPageOptions.value = [
    { id: 100, value: 100 },
    { id: 200, value: 200 },
    { id: 500, value: 500 },
    { id: 'All', value: 'All' },
  ]
  operatorOptions.value = [
    {id: 'all', full_name: 'All'},
    ...props.operatorOptions.data.map((data) => {return {id: data.id, code:data.code, full_name: data.full_name}})
  ]
  userOptions.value = [
    ...props.userOptions.data.map((data) => {return {id: data.id, value: data.name}})
  ]
  filters.value.numberPerPage = numberPerPageOptions.value[0]

  // If driver: default Delivered By to self and lock the control
  if (isDriver && authUser) {
    const selfOption = userOptions.value.find(u => u.id === authUser.id)
    if (selfOption) {
      filters.value.delivered_by = selfOption
    }
  }

  filters.value.operators = authOperator ? [
		operatorOptions.value.find(operator => operator.id === authOperator.id),
		...authOperator.code == 'HIPL' ? [
			operatorOptions.value.find(operator => operator.code == 'HIMD'),
			operatorOptions.value.find(operator => operator.code == 'LEA'),
			operatorOptions.value.find(operator => operator.code == 'DCVIC'),
      operatorOptions.value.find(operator => operator.code == 'HIESG'),
      operatorOptions.value.find(operator => operator.code == 'IP'),
		] : [],
	] : operatorOptions.value[0]
})

function onCreateClicked() {
  type.value = 'create'
  opsJob.value = null
  showModal.value = true
}

function onCreateAddressClicked() {
  showAddressModal.value = true
}

function onDeleteClicked(opsJob) {
  const approval = confirm('Are you sure to delete ' + opsJob.code + '?');
  if (!approval) {
      return;
  }
  router.delete('/ops-jobs/' + opsJob.id, {
    onSuccess: () => {
      toast.success("Ops job deleted successfully", { timeout: 3000 })
    },
    onError: () => {
      toast.error("Failed to delete ops job", { timeout: 3000 })
    }
  })
}

function onSearchFilterUpdated() {
  router.get('/ops-jobs', {
      ...filters.value,
      created_by: filters.value.created_by?.id,
      delivered_by: filters.value.delivered_by?.id,
      operators: filters.value.operators.map(operator => operator.id),
      numberPerPage: filters.value.numberPerPage.id,
  }, {
      preserveState: true,
      replace: true,
  })
}

function resetFilters() {
  router.get('/ops-jobs')
}

function sortTable(sortKey) {
  filters.value.sortKey = sortKey
  filters.value.sortBy = !filters.value.sortBy
  onSearchFilterUpdated()
}

function onModalClose() {
  showModal.value = false
}

</script>
