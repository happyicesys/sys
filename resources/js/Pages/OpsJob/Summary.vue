<template>

  <Head title="Daily Jobs Summary" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Daily Jobs Summary
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
        <div class="grid grid-cols-1 md:grid-cols-5 gap-2 mt-2">
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
            </div>
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
                        <span>
                          Deliver By
                        </span>
                        <span>
                          # of Days
                        </span>
                      </div>
                    </TableHead>
                    <TableHead>
                      <div class="flex flex-col space-y-2">
                        <span>
                          Jobs
                        </span>
                        <span>
                          Picked(%)
                        </span>
                        <span>
                          Stock-in(%)
                        </span>
                        <span>
                          Verified(%)
                        </span>
                        <span v-if="isCmsUrlSet">
                          API Invoices(%)
                        </span>
                      </div>
                    </TableHead>
                    <TableHead>
                      <div class="flex flex-col space-y-2">
                        <span>
                          Picked
                        </span>
                        <span>
                          Value
                        </span>
                        <span>
                          Qty
                        </span>
                        <span>
                          Cost
                        </span>
                      </div>
                    </TableHead>
                    <TableHead>
                      <div class="flex flex-col space-y-2">
                        <span>
                          Stock-in
                        </span>
                        <span>
                          Value
                        </span>
                        <span>
                          Qty
                        </span>
                        <span>
                          Cost
                        </span>
                      </div>
                    </TableHead>
                    <TableHead>
                      <div class="flex flex-col space-y-2">
                        <span>
                          Stock-out <br>
                          (Transactions)
                        </span>
                        <span>
                          Total Amount$
                        </span>
                        <span>
                          Qty
                        </span>
                      </div>
                    </TableHead>
                    <TableHead>
                      <div class="flex flex-col space-y-2">
                        <span>
                          Cash Amount
                        </span>
                        <span>
                          Cash Collected (fr VM)
                        </span>
                        <span>
                          CashAmt$ (VMC)
                        </span>
                        <span>
                          % Cash Collected/ CashAmt$
                        </span>
                        <span>
                          Cash Adjustment
                        </span>
                      </div>
                    </TableHead>
                  </tr>
                </thead>
                  <tbody class="bg-white">
                    <tr v-if="summaries.length" class="bg-gray-100 font-bold border-b-2 border-gray-300">
                      <td colspan="2" class="whitespace-nowrap py-2 pl-1 pr-1 text-[13px] text-gray-800 sm:pl-1 lg:pl-1 bg-gray-200 text-center border-r border-gray-300">
                        Total
                      </td>
                      <TableData :inputClass="'text-center align-top bg-gray-200'">
                        <div class="flex flex-col space-y-2">
                          <div class="inline-flex justify-center items-center rounded px-1.5 text-xs font-bold border min-w-full text-gray-900 border-gray-400"
                            :class="[totalSummary.ops_job_items_count > 0 && totalSummary.ops_job_items_picked_count_percentage == 100 ? 'text-green-700 bg-green-200 border-green-300' : '']"
                          >
                            <span>
                              {{ totalSummary.ops_job_items_picked_count }} ({{ totalSummary.ops_job_items_count > 0 ? totalSummary.ops_job_items_picked_count_percentage.toFixed(0) : 0 }}%)
                            </span>
                          </div>
                          <div class="inline-flex justify-center items-center rounded px-1.5 text-xs font-bold border min-w-full text-gray-900 border-gray-400"
                            :class="[totalSummary.ops_job_items_count > 0 && totalSummary.ops_job_items_delivered_count_percentage == 100 ? 'text-green-700 bg-green-200 border-green-300' : '']"
                          >
                            <span>
                              {{ totalSummary.ops_job_items_delivered_count }} ({{ totalSummary.ops_job_items_count > 0 ? totalSummary.ops_job_items_delivered_count_percentage.toFixed(0) : 0 }}%)
                            </span>
                          </div>
                          <div class="inline-flex justify-center items-center rounded px-1.5 text-xs font-bold border min-w-full text-gray-900 border-gray-400"
                            :class="[totalSummary.ops_job_items_count > 0 && totalSummary.ops_job_items_verified_count_percentage == 100 ? 'text-green-700 bg-green-200 border-green-300' : '']"
                          >
                            <span>
                              {{ totalSummary.ops_job_items_verified_count }} ({{ totalSummary.ops_job_items_count > 0 ? totalSummary.ops_job_items_verified_count_percentage.toFixed(0) : 0 }}%)
                            </span>
                          </div>
                          <div
                            class="inline-flex justify-center items-center rounded px-1.5 text-xs font-bold border min-w-full text-gray-900 border-gray-400"
                            v-if="isCmsUrlSet"
                            :class="[totalSummary.ops_job_items_delivered_count > 0 && totalSummary.cms_transaction_percentage == 100 ? 'text-green-700 bg-green-200 border-green-300' : '']"
                          >
                            <span>
                              {{ totalSummary.cms_transaction_count }} ({{ totalSummary.ops_job_items_delivered_count > 0 ? totalSummary.cms_transaction_percentage.toFixed(0) : 0 }}%)
                            </span>
                          </div>
                        </div>
                      </TableData>
                      <TableData :inputClass="'text-center align-top bg-gray-200'">
                        <div class="flex flex-col space-y-2">
                          <span>
                            {{ operatorCountry.currency_symbol }}{{ totalSummary.picked_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                          </span>
                          <span>
                            {{ totalSummary.picked_count.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0}) }}
                          </span>
                          <span>
                             {{ operatorCountry.currency_symbol }}{{ totalSummary.picked_cost.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                          </span>
                        </div>
                      </TableData>
                      <TableData :inputClass="'text-center align-top bg-gray-200'">
                         <div class="flex flex-col space-y-2 min-w-24">
                          <span :class="[totalSummary.stock_in_amount < totalSummary.picked_amount ? 'text-red-700' : 'text-green-700']">
                            {{ operatorCountry.currency_symbol }}{{ totalSummary.stock_in_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                          </span>
                          <span>
                            {{ totalSummary.stock_in_count.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0}) }}
                          </span>
                          <span>
                            {{ operatorCountry.currency_symbol }}{{ totalSummary.stock_in_cost.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                          </span>
                        </div>
                      </TableData>
                      <TableData :inputClass="'text-center align-top bg-gray-200'">
                         <div class="flex flex-col space-y-2 min-w-24">
                          <span>
                            {{ operatorCountry.currency_symbol }}{{ totalSummary.acc_vend_transactions_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                          </span>
                          <span>
                            {{ totalSummary.acc_vend_transactions_count.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0}) }}
                          </span>
                        </div>
                      </TableData>
                      <TableData :inputClass="'text-center align-top bg-gray-200'">
                        <div class="flex flex-col space-y-2">
                          <span>
                            {{ operatorCountry.currency_symbol }}{{ totalSummary.total_cash_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                          </span>
                          <span>
                            {{ operatorCountry.currency_symbol }}{{ totalSummary.total_cash_amount_from_vmc.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                          </span>
                          <span>
                            {{ (totalSummary.total_cash_amount/(totalSummary.total_cash_amount_from_vmc > 0 ? totalSummary.total_cash_amount_from_vmc : 1) * 100).toFixed(2) }}%
                          </span>
                          <span :class="[totalSummary.delta_cash_amount > 0 ? 'text-green-600' : (totalSummary.delta_cash_amount < 0 ? 'text-red-600' : '')]">
                            {{ operatorCountry.currency_symbol }}{{ totalSummary.delta_cash_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                          </span>
                        </div>
                      </TableData>
                    </tr>
                    <tr v-for="(summary, index) in summaries" :key="summary.id" class="divide-x divide-y-2 divide-gray-300 odd:bg-white bg-white">
                      <TableData :currentIndex="index" :totalLength="summaries.length" inputClass="text-center">
                        {{ index + 1 }}
                      </TableData>
                      <TableData :currentIndex="index" :totalLength="summaries.length" inputClass="text-center">
                        <div class="flex flex-col space-y-2">
                          <span class="font-bold">
                            {{ summary.delivered_by ? summary.delivered_by.name : 'Unassigned' }}
                          </span>
                          <span>
                            {{ summary.job_count }} Days
                          </span>
                        </div>
                      </TableData>
                      <TableData :currentIndex="index" :totalLength="summaries.length" inputClass="text-center align-top">
                        <div class="flex flex-col space-y-2">
                          <div
                            class="inline-flex justify-center items-center rounded px-1.5 text-xs font-medium border min-w-full text-gray-900"
                            :class="[summary.ops_job_items_picked_count_percentage == 100 ? 'text-green-700 bg-green-200' : '']"
                          >
                            <span>
                              {{ summary.ops_job_items_picked_count }} ({{ summary.ops_job_items_picked_count_percentage.toFixed(0) }}%)
                            </span>
                          </div>
                          <div
                            class="inline-flex justify-center items-center rounded px-1.5 text-xs font-medium border min-w-full text-gray-900"
                            :class="[summary.ops_job_items_delivered_count_percentage == 100 ? 'text-green-700 bg-green-200' : '']"
                          >
                            <span>
                              {{ summary.ops_job_items_delivered_count }} ({{ summary.ops_job_items_delivered_count_percentage.toFixed(0) }}%)
                            </span>
                          </div>
                          <div
                            class="inline-flex justify-center items-center rounded px-1.5 text-xs font-medium border min-w-full text-gray-900"
                            :class="[summary.ops_job_items_verified_count_percentage == 100 ? 'text-green-700 bg-green-200' : '']"
                          >
                            <span>
                              {{ summary.ops_job_items_verified_count }} ({{ summary.ops_job_items_verified_count_percentage.toFixed(0) }}%)
                            </span>
                          </div>
                          <div
                            class="inline-flex justify-center items-center rounded px-1.5 text-xs font-medium border min-w-full text-gray-900"
                            :class="[summary.cms_transaction_percentage == 100 ? 'text-green-700 bg-green-200' : '']"
                            v-if="isCmsUrlSet"
                          >
                            <span>
                              {{ summary.cms_transaction_count }} ({{ summary.cms_transaction_percentage.toFixed(0) }}%)
                            </span>
                          </div>
                        </div>
                      </TableData>
                      <TableData :currentIndex="index" :totalLength="summaries.length" inputClass="text-center align-top">
                        <div class="flex flex-col space-y-2">
                          <span>
                            {{ operatorCountry.currency_symbol }}{{ summary.picked_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                          </span>
                          <span>
                            {{ summary.picked_count.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0}) }}
                          </span>
                          <span>
                            {{ operatorCountry.currency_symbol }}{{ summary.picked_cost.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                          </span>
                        </div>
                      </TableData>
                      <TableData :currentIndex="index" :totalLength="summaries.length" inputClass="text-center align-top">
                        <div class="flex flex-col space-y-2 min-w-24">
                          <span :class="[summary.stock_in_amount < summary.picked_amount ? 'text-red-700' : 'text-green-700']">
                            {{ operatorCountry.currency_symbol }}{{ summary.stock_in_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                          </span>
                          <span>
                            {{ summary.stock_in_count.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0}) }}
                          </span>
                          <span>
                            {{ operatorCountry.currency_symbol }}{{ summary.stock_in_cost.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                          </span>
                        </div>
                      </TableData>
                      <TableData :currentIndex="index" :totalLength="summaries.length" inputClass="text-center align-top">
                        <div class="flex flex-col space-y-2 min-w-24">
                          <span>
                            {{ operatorCountry.currency_symbol }}{{ summary.acc_vend_transactions_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                          </span>
                          <span>
                            {{ summary.acc_vend_transactions_count.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0}) }}
                          </span>
                        </div>
                      </TableData>
                      <TableData :currentIndex="index" :totalLength="summaries.length" inputClass="text-center align-top">
                        <div class="flex flex-col space-y-2">
                          <span>
                            {{ operatorCountry.currency_symbol }}{{ summary.total_cash_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                          </span>
                          <span>
                            {{ operatorCountry.currency_symbol }}{{ summary.total_cash_amount_from_vmc.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                          </span>
                          <span>
                            {{ (summary.total_cash_amount/(summary.total_cash_amount_from_vmc > 0 ? summary.total_cash_amount_from_vmc : 1) * 100).toFixed(2) }}%
                          </span>
                          <span :class="[summary.delta_cash_amount > 0 ? 'text-green-600' : (summary.delta_cash_amount < 0 ? 'text-red-600' : '')]">
                            {{ operatorCountry.currency_symbol }}{{ summary.delta_cash_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                          </span>
                        </div>
                      </TableData>
                      </tr>
                <tr v-if="!summaries.length">
                  <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                      No Results Found
                  </td>
                </tr>
              </tbody>
            </table>
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
import MultiSelect from '@/Components/MultiSelect.vue';
import { BackspaceIcon, MagnifyingGlassIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import { ref, onMounted, computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import moment from 'moment';

const props = defineProps({
  operatorOptions: [Array, Object],
  summaries: [Array, Object],
  userOptions: [Array, Object],
})

const filters = ref({
  date_from: moment().subtract(7, 'days').format('YYYY-MM-DD'),
  date_to: moment().subtract(1, 'days').format('YYYY-MM-DD'),
  delivered_by: '',
  operators: [],
})
const authOperator = usePage().props.auth.operator
const authUser = usePage().props.auth.user
const authRoles = usePage().props.auth.roles || []
const isDriver = authRoles.includes('driver')
const isCmsUrlSet = usePage().props.isCmsUrlSet
const operatorCountry = usePage().props.auth.operatorCountry
const operatorOptions = ref([])
const userOptions = ref([])

const totalSummary = computed(() => {
  const total = {
    ops_job_items_count: 0,
    ops_job_items_picked_count: 0,
    ops_job_items_delivered_count: 0,
    ops_job_items_verified_count: 0,
    cms_transaction_count: 0,
    picked_amount: 0,
    picked_count: 0,
    picked_cost: 0,
    stock_in_amount: 0,
    stock_in_count: 0,
    stock_in_cost: 0,
    acc_vend_transactions_amount: 0,
    acc_vend_transactions_count: 0,
    total_cash_amount: 0,
    total_cash_amount_from_vmc: 0,
    delta_cash_amount: 0,
    ops_job_items_picked_count_percentage: 0,
    ops_job_items_delivered_count_percentage: 0,
    ops_job_items_verified_count_percentage: 0,
    cms_transaction_percentage: 0,
  }

  if (props.summaries) {
    props.summaries.forEach(summary => {
      total.ops_job_items_count += summary.ops_job_items_count || 0
      total.ops_job_items_picked_count += summary.ops_job_items_picked_count || 0
      total.ops_job_items_delivered_count += summary.ops_job_items_delivered_count || 0
      total.ops_job_items_verified_count += summary.ops_job_items_verified_count || 0
      total.cms_transaction_count += summary.cms_transaction_count || 0
      total.picked_amount += parseFloat(summary.picked_amount || 0)
      total.picked_count += parseFloat(summary.picked_count || 0)
      total.picked_cost += parseFloat(summary.picked_cost || 0)
      total.stock_in_amount += parseFloat(summary.stock_in_amount || 0)
      total.stock_in_count += parseFloat(summary.stock_in_count || 0)
      total.stock_in_cost += parseFloat(summary.stock_in_cost || 0)
      total.acc_vend_transactions_amount += parseFloat(summary.acc_vend_transactions_amount || 0)
      total.acc_vend_transactions_count += parseFloat(summary.acc_vend_transactions_count || 0)
      total.total_cash_amount += parseFloat(summary.total_cash_amount || 0)
      total.total_cash_amount_from_vmc += parseFloat(summary.total_cash_amount_from_vmc || 0)
      total.delta_cash_amount += parseFloat(summary.delta_cash_amount || 0)
    })

    if (total.ops_job_items_count > 0) {
      total.ops_job_items_picked_count_percentage = (total.ops_job_items_picked_count / total.ops_job_items_count) * 100
      total.ops_job_items_delivered_count_percentage = (total.ops_job_items_delivered_count / total.ops_job_items_count) * 100
      total.ops_job_items_verified_count_percentage = (total.ops_job_items_verified_count / total.ops_job_items_count) * 100
    }
    if (total.ops_job_items_delivered_count > 0) {
      total.cms_transaction_percentage = (total.cms_transaction_count / total.ops_job_items_delivered_count) * 100
    }
  }
  return total
})


onMounted(() => {
  operatorOptions.value = [
    {id: 'all', full_name: 'All'},
    ...props.operatorOptions.data.map((data) => {return {id: data.id, code:data.code, full_name: data.full_name}})
  ]
  userOptions.value = [
    ...props.userOptions.data.map((data) => {return {id: data.id, value: data.name}})
  ]

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
      operatorOptions.value.find(operator => operator.code == 'HIESG'),
      operatorOptions.value.find(operator => operator.code == 'UL-ST'),
		] : [],
	].filter(operator => operator !== undefined) : [operatorOptions.value[0]]
})

function onSearchFilterUpdated() {
  router.get('/ops-jobs/summary', {
      ...filters.value,
      delivered_by: filters.value.delivered_by?.id,
      operators: filters.value.operators.filter(operator => operator).map(operator => operator.id),
  }, {
      preserveState: true,
      replace: true,
  })
}

function resetFilters() {
  router.get('/ops-jobs/summary')
}

</script>
