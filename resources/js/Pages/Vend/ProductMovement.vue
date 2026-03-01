<template>
    <Head title="Warehouse Qty & Planning" />

    <BreezeAuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Warehouse Qty & Planning
            </h2>
        </template>

        <div class="m-2 sm:mx-2 sm:my-3 px-1 sm:px-2 lg:px-3">
            <div class="mt-6 flex flex-col">
                <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5">
                    <div class="inline-block min-w-full py-2 align-middle md:px-4 lg:px-6">
                        <div class="py-4">
                            <div class="flex justify-end mb-3">
                                <div class="flex space-x-2">
                                    <Button class="inline-flex space-x-1 items-center rounded-md border border-indigo-500 bg-indigo-500 px-4 py-2 text-sm font-medium leading-4 text-white shadow-sm hover:bg-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                        @click="onTrackingDetailsClicked()"
                                    >
                                        <ClipboardDocumentListIcon class="h-4 w-4" aria-hidden="true"/>
                                        <span>
                                            Warehouse Log
                                        </span>
                                    </Button>
                                    <Link :href="route('product-movements.incoming-history')" class="inline-flex space-x-1 items-center rounded-md border border-purple-500 bg-purple-500 px-4 py-2 text-sm font-medium leading-4 text-white shadow-sm hover:bg-purple-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                        <ClockIcon class="h-4 w-4" aria-hidden="true"/>
                                        <span>
                                            Incoming History
                                        </span>
                                    </Link>
                                    <Link :href="route('product-movements.batch-incoming')" class="inline-flex space-x-1 items-center rounded-md border border-green-600 bg-green-600 px-4 py-2 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                                        <PlusIcon class="h-4 w-4" aria-hidden="true"/>
                                        <span>
                                            Stock Incoming to Warehouse
                                        </span>
                                    </Link>
                                </div>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-2">
                                <SearchInput placeholderStr="Product ID/ Code" v-model="filters.product_code">
                                    Product ID
                                </SearchInput>
                                <SearchInput placeholderStr="Product Name" v-model="filters.product_name">
                                    Product Name
                                </SearchInput>
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
                                        Is Available?
                                    </label>
                                    <MultiSelect
                                        v-model="filters.is_available"
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
                            <div class="flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-2">
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
                                        @click="onResetFilterClicked()"
                                    >
                                        <BackspaceIcon class="h-4 w-4" aria-hidden="true"/>
                                        <span>
                                            Reset
                                        </span>
                                    </Button>

                                    <!-- <Button class="inline-flex space-x-1 items-center rounded-md border border-gray-800 bg-white px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                        @click="onExcelExportClicked()" v-if="permissions.includes('export products')"
                                    >
                                        <ArrowDownTrayIcon class="h-4 w-4" aria-hidden="true"/>
                                        <span>
                                            Export Excel
                                        </span>
                                    </Button> -->
                                </div>
                                <div class="flex flex-col gap-2 items-end">
                                    <span class="text-xs text-gray-500 self-center">
                                        {{ products.data.length }} products found
                                    </span>
                                    <div class="flex flex-row items-center gap-2">
                                      <label class="text-xs font-semibold text-gray-700">Planning Date</label>
                                      <DatePicker v-model="filters.productAvailableDate" class="py-1 text-xs" :isPreviousNextButton="false" :clearable="false" :format="'yyyy-MM-dd'" auto-apply @update:model-value="onSearchFilterUpdated" :minDate="today">
                                          <template #trigger>
                                              <span class="p-1 border border-gray-300 rounded-md cursor-pointer hover:bg-gray-100 flex flex-row gap-2 justify-center items-center h-full text-xs">
                                                  <CalendarIcon class="w-3 h-3" />
                                                  {{ moment(filters.productAvailableDate).format('YYYY-MM-DD') }}
                                              </span>
                                          </template>
                                      </DatePicker>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="overflow-scroll max-h-[900px] md:max-h-[1500px] shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                            <table class="min-w-full divide-y divide-gray-300">
                                <thead class="bg-gray-100 sticky top-0 z-10">
                                    <tr>
                                        <th colspan="7" class="bg-gray-100"></th>
                                        <th colspan="2" class="p-2 text-center text-sm font-bold text-gray-900 border-b border-gray-300 bg-gray-200">
                                            Planning
                                        </th>
                                    </tr>
                                    <tr>
                                        <th  scope="col" class="th-header w-[2%] p-1 sm:p-3 text-[10px] sm:text-xs font-semibold text-center text-gray-900 border-b">#</th>
                                        <th  scope="col" class="th-header w-[5%] p-1 sm:p-3 text-[10px] sm:text-xs font-semibold text-center text-gray-900 border-b">Image</th>
                                        <th  scope="col" class="th-header w-[20%] p-1 sm:p-3 text-[10px] sm:text-xs font-semibold text-center text-blue-600 border-b cursor-pointer hover:bg-gray-200" @click="sortTable('code')">
                                            <div class="flex items-center justify-center gap-1">
                                                Product
                                                <span v-if="filters.sortKey === 'code'">
                                                  <svg v-if="filters.sortBy" xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                     <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                                                  </svg>
                                                  <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                     <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                                  </svg>
                                                </span>
                                            </div>
                                        </th>
                                        <th  scope="col" class="th-header w-[10%] p-1 sm:p-3 text-[10px] sm:text-xs font-semibold text-center text-blue-600 border-b border-r border-gray-300 cursor-pointer hover:bg-gray-200" @click="sortTable('avg_seven_days_count')">
                                            <div class="flex items-center justify-center gap-1">
                                                <span>Daily Sold Qty</span>
                                                <span v-if="filters.sortKey === 'avg_seven_days_count'">
                                                    <svg v-if="filters.sortBy" xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                                                    </svg>
                                                    <svg v-else xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                                    </svg>
                                                </span>
                                            </div>
                                            <span class="font-normal text-gray-600">(average last 7days)</span>
                                        </th>

                                        <th  scope="col" class="th-header w-[10%] p-1 sm:p-3 text-[10px] sm:text-xs font-semibold text-center text-gray-900 border-b">
                                            Qty in Warehouse<br>
                                        </th>
                                        <th  scope="col" class="th-header w-[10%] p-1 sm:p-3 text-[10px] sm:text-xs font-semibold text-center text-gray-900 border-b">
                                            Picked Qty<br>
                                            <span class="font-normal text-gray-600">(as Job on Date)</span>
                                        </th>

                                        <th  scope="col" class="th-header w-[10%] p-1 sm:p-3 text-[10px] sm:text-xs font-semibold text-center text-gray-900 border-b border-r border-gray-300">
                                            <div class="flex items-center justify-center gap-1">
                                                <span>Remaining Qty</span>
                                                <ExclamationCircleIcon class="min-w-4 w-4 h-4 text-sky-500 cursor-help" v-tooltip="{ content: 'Red = Remaining Qty not enough to cover \'To Pick Qty\' as in Planning Date', html: true }"></ExclamationCircleIcon>
                                            </div>
                                            <span class="font-normal text-gray-600">(after Picked)</span>
                                        </th>
                                        <th  scope="col" class="th-header w-[15%] p-1 sm:p-3 text-[10px] sm:text-xs font-semibold text-center text-gray-900 border-b">
                                            To Pick Qty<br>
                                            <span class="font-normal text-gray-600">(Live Update, for Jobs on Date)</span>
                                        </th>
                                        <th  scope="col" class="th-header w-[10%] p-1 sm:p-3 text-[10px] sm:text-xs font-semibold text-center text-gray-900 border-b">
                                            Capped Qty per Channel<br>
                                            <span class="font-normal text-xs text-gray-600">(max Qty after Refilling on Planning Date & 4 Days Onwards)</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <tr v-for="(product, index) in products.data" :key="product.id" class="hover:bg-gray-50">
                                        <td class="p-1 sm:p-3 text-xs sm:text-sm text-center text-gray-900">{{ index + 1 }}</td>
                    <td class="whitespace-nowrap text-sm  font-semibold text-gray-900 text-center">
                      <div class="flex justify-center items-center">
                        <img class="h-8 w-8 sm:h-16 sm:w-16 rounded-full" :class="[product.is_available ? '' : 'opacity-50']" :src="product.thumbnail.full_url" alt="" v-if="product.thumbnail" />
                      </div>
                    </td>
                                        <td class="p-1 sm:p-3 text-xs sm:text-sm text-gray-900">
                                            <div class="flex flex-col text-left">
                                                <span class="font-bold">{{ product.code }}</span>
                                                <span class="text-gray-600 mb-1">{{ product.name }}</span>
                                                <div class="flex items-center gap-1">
                                                    <span class="text-green-700 font-bold text-xs">Available?</span>
                                                    <span v-if="product.is_available">
                                                        <CheckCircleIcon class="h-5 w-5 text-green-500 hover:cursor-pointer hover:text-green-600" @click.prevent="onIsAvailableClicked(product)" />
                                                    </span>
                                                    <span v-else>
                                                        <XCircleIcon class="h-5 w-5 text-red-500 hover:cursor-pointer hover:text-red-600" @click.prevent="onIsAvailableClicked(product)" />
                                                    </span>
                                                </div>
                                                <span class="text-[10px] text-gray-500 mt-1" v-if="product.isAvailableUpdatedBy">
                                                    {{ product.isAvailableUpdatedBy.name }} ({{ product.is_available_updated_at }})
                                                </span>
                                            </div>
                                        </td>
                                        <td class="whitespace-nowrap py-1 pl-1 pr-1 text-xs font-medium sm:py-4 sm:pl-6 sm:pr-3 sm:text-sm text-center border-r border-gray-300" :class="[product.is_available ? 'text-gray-600' : 'text-gray-400']">
                                          {{ Number(product.avg_seven_days_count)?.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0}) }}
                                        </td>

                                        <td class="p-1 sm:p-3 text-center text-sm sm:text-lg font-bold text-blue-600">
                                            {{ product.total_movements_qty ? Number(product.total_movements_qty).toLocaleString() : 0 }}
                                        </td>
                                        <td class="p-1 sm:p-3 text-center text-sm sm:text-lg font-bold text-gray-800">
                                            {{ product.picked_qty_on_date ? Number(product.picked_qty_on_date).toLocaleString() : 0 }}
                                        </td>
                                        <td class="p-1 sm:p-3 text-center text-sm sm:text-lg font-bold text-gray-900 border-r border-gray-300">
                                            <span :class="product.is_available ? (product.calculated_warehouse_qty < product.needed_qty ? 'text-red-800 bg-red-200 rounded px-1 py-1' : '') : 'text-gray-400'">
                                                {{ !isNaN(Number(product.calculated_warehouse_qty)) ? Number(product.calculated_warehouse_qty).toLocaleString() : 0 }}
                                            </span>
                                        </td>
                                        <td class="p-1 sm:p-3 text-center text-sm sm:text-lg font-bold text-orange-600">
                                            {{ product.needed_qty ? Number(product.needed_qty).toLocaleString() : 0 }}
                                        </td>
                                        <td class="p-1 sm:p-3 text-center">
                                        <!-- Reusing limit logic if needed, or keeping it readonly -->
                                        <div class="flex flex-col items-center gap-1">
                                            <select name="max_ops_job_pick_limit" id="max_ops_job_pick_limit" class="rounded text-xs py-1" :class="[product.max_ops_job_pick_limit >= 0 && product.max_ops_job_pick_limit != null ? 'text-red-600' : 'text-gray-800']" v-model="product.max_ops_job_pick_limit" @change="onMaxOpsJobPickLimitSelected(product.id, product.max_ops_job_pick_limit)">
                                                <option :value="null">No</option>
                                                <option v-for="n in 20 + 1" :key="n-1" :value="n-1">{{ n-1 }}</option>
                                            </select>
                                            <span class="text-[10px] text-red-700" v-if="product.max_ops_job_pick_limit != null && product.limit_is_created_by_system">
                                                from Yesterday
                                            </span>
                                            <span class="text-[10px] text-gray-800" v-if="product.max_ops_job_pick_limit != null && product.productLimits && product.productLimits[0] && product.productLimits[0].createdBy">
                                                {{ product.productLimits[0].createdBy.name }}
                                            </span>
                                            <span class="text-[10px] text-gray-800" v-if="product.max_ops_job_pick_limit != null && product.productLimits && product.productLimits[0]">
                                                {{ product.productLimits[0].setupDate }}
                                            </span>
                                        </div>
                                    </td>
                                </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr class="bg-gray-50 font-bold">
                                            <td colspan="3" class="p-3 text-left text-gray-900">
                                                <div class="flex flex-col space-y-1 items-start">
                                                    <span>Total Pcs</span>
                                                    <span>Total Cost$</span>
                                                    <span class="text-gray-900">Stock Value</span>
                                                </div>
                                            </td>
                                            <td class="p-1 sm:p-3 text-center text-gray-900 border-r border-gray-300">
                                                <div class="flex flex-col space-y-1">
                                                    <span>{{ getDailySoldQtyTotal().toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 0 }) }}</span>
                                                    <span>{{ operatorCountry.currency_symbol }}{{ getDailySoldQtyTotalCost().toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
                                                    <span>&nbsp;</span>
                                                </div>
                                            </td>
                                            <td class="p-1 sm:p-3 text-center text-blue-600">
                                                <div class="flex flex-col space-y-1">
                                                    <span>{{ getTotalMovementsQty().toLocaleString() }}</span>
                                                    <span>{{ operatorCountry.currency_symbol }}{{ getTotalMovementsCost().toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
                                                    <span>&nbsp;</span>
                                                </div>
                                            </td>
                                            <td class="p-1 sm:p-3 text-center text-gray-800">
                                                <div class="flex flex-col space-y-1">
                                                    <span>{{ getPickedQtyTotal().toLocaleString() }}</span>
                                                    <span>{{ operatorCountry.currency_symbol }}{{ getPickedQtyTotalCost().toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
                                                    <span class="text-gray-900 font-bold">{{ operatorCountry.currency_symbol }}{{ getPickedValueTotal().toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
                                                </div>
                                            </td>
                                            <td class="p-1 sm:p-3 text-center text-gray-900 border-r border-gray-300">
                                                <div class="flex flex-col space-y-1">
                                                    <span>{{ getCalculatedWarehouseQtyTotal().toLocaleString() }}</span>
                                                    <span>{{ operatorCountry.currency_symbol }}{{ getCalculatedWarehouseQtyTotalCost().toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
                                                    <span>&nbsp;</span>
                                                </div>
                                            </td>
                                            <td class="p-1 sm:p-3 text-center text-orange-600">
                                                <div class="flex flex-col space-y-1">
                                                    <span>{{ getNeededQtyTotal().toLocaleString() }}</span>
                                                    <span>{{ operatorCountry.currency_symbol }}{{ getNeededQtyTotalCost().toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
                                                    <span class="text-orange-600 font-bold capitalize">{{ operatorCountry.currency_symbol }}{{ getNeededValueTotal().toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</span>
                                                </div>
                                            </td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        </div>

        <!-- Incoming Modal -->
        <Modal :show="showMovementModal" @close="closeMovementModal">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900">
                    Record Product Incoming
                </h3>
                <div class="mt-2" v-if="selectedProduct">
                    <p class="text-sm text-gray-500">
                        Recording incoming for <span class="font-bold">{{ selectedProduct.code }} - {{ selectedProduct.name }}</span>
                    </p>
                    <div class="mt-4 flex flex-col gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Type</label>
                            <select v-model="form.type" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm rounded-md">
                                <option :value="1">Incoming (Stock In)</option>
                                <option :value="2">Adjustment (Correction)</option>
                            </select>
                            <InputError :message="form.errors.type" class="mt-2" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Quantity (pcs)</label>
                             <p class="text-xs text-gray-400">For Adjustment, use negative value to deduct.</p>
                            <input type="number" v-model="form.qty" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm" placeholder="e.g. 100 or -5">
                            <InputError :message="form.errors.qty" class="mt-2" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Date</label>
                            <DatePicker v-model="form.created_at" class="mt-1" :isPreviousNextButton="false" :clearable="false" :format="'yyyy-MM-dd'" auto-apply>
                            </DatePicker>
                            <InputError :message="form.errors.created_at" class="mt-2" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Remarks</label>
                            <textarea v-model="form.remarks" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-green-500 focus:border-green-500 sm:text-sm"></textarea>
                            <InputError :message="form.errors.remarks" class="mt-2" />
                        </div>
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none" @click="closeMovementModal">
                        Cancel
                    </button>
                    <button type="button" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none" :disabled="form.processing" @click="submitMovement">
                        Save
                    </button>
                </div>
            </div>
        </Modal>
    </BreezeAuthenticatedLayout>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { Head, router, usePage, useForm, Link } from '@inertiajs/vue3'
import { MagnifyingGlassIcon, XMarkIcon, PlusIcon, CalendarIcon, CheckCircleIcon, XCircleIcon, BackspaceIcon, ClipboardDocumentListIcon, ArrowDownTrayIcon, ClockIcon, ExclamationCircleIcon } from '@heroicons/vue/24/solid'
import MultiSelect from '@/Components/MultiSelect.vue'
import DatePicker from '@/Components/DatePicker.vue'
import SearchInput from '@/Components/SearchInput.vue'
import moment from 'moment'
import Modal from '@/Components/Modal.vue'
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import Button from '@/Components/Button.vue'
import InputError from '@/Components/InputError.vue'
import axios from 'axios'

const props = defineProps({
    operatorOptions: Object,
    products: Object,
    filters: Object,
})

const permissions = usePage().props.auth.permissions
const authOperator = usePage().props.auth.operator
const operatorCountry = usePage().props.auth.operatorCountry;
const operatorOptions = ref([])

const filters = ref({
    product_name: '',
    product_code: '',
    operators: [],
    is_available: '',
    productAvailableDate: moment().add(1, 'days').format('YYYY-MM-DD'),
    sortKey: 'code',
    sortBy: false,
});
const today = moment().format('YYYY-MM-DD');
const booleanOptions = ref([])

const showMovementModal = ref(false)
const selectedProduct = ref(null)

const form = useForm({
    product_id: null,
    type: 1,
    qty: '',
    remarks: '',
    created_at: moment().format('YYYY-MM-DD'),
})

onMounted(() => {
  operatorOptions.value = [
			{id: 'all', full_name: 'All'},
      ...props.operatorOptions.data.map((data) => {return {id: data.id, code: data.code, full_name: data.full_name}})
  ]
  filters.value.operators = authOperator ? [
		operatorOptions.value.find(operator => operator.id === authOperator.id),
		...authOperator.code == 'HIPL' ? [
			operatorOptions.value.find(operator => operator.code == 'HIMD'),
			operatorOptions.value.find(operator => operator.code == 'LEA'),
      operatorOptions.value.find(operator => operator.code == 'HIESG'),
      operatorOptions.value.find(operator => operator.code == 'UL-ST'),
		] : [],
	].filter(operator => operator !== undefined) : [operatorOptions.value[0]]

    // If props.filters.operators exist, it means the user has searched, so we should use those values.
    // The values from props.filters.operators will be IDs (strings or numbers) if they came from the URL.
    // We need to map these IDs back to the full operator objects for the MultiSelect component.
    if (props.filters && props.filters.operators && props.filters.operators.length > 0) {
        filters.value.operators = props.filters.operators.map(filterId =>
            operatorOptions.value.find(op => op.id == filterId)
        ).filter(Boolean); // Filter out any undefined if an ID doesn't match
    }
    booleanOptions.value = [
        {id: 'all', value: 'All'},
        {id: 'true', value: 'Yes'},
        {id: 'false', value: 'No'},
    ]
    if(props.filters && props.filters.is_available) {
        // Handle string/boolean value from URL query
        const isAvailableVal = props.filters.is_available;
        // If it comes as 'true'/'false' string or boolean, map to our options
        // But props.filters from backend (via Inertia) usually matches what we sent.
        // If we sent ID 'true', we get 'true'.
        filters.value.is_available = booleanOptions.value.find(o => o.id === String(isAvailableVal)) || booleanOptions.value[0];
    } else {
        filters.value.is_available = booleanOptions.value[0];
    }
})

const onSearchFilterUpdated = () => {
    let operators = filters.value.operators.filter(operator => operator).map(operator => operator.id)
    if (operators.includes('all')) {
        operators = ['all']
    }
    router.get(route('product-movements.index'), {
        product_name: filters.value.product_name,
        product_code: filters.value.product_code,
        operators: operators,
        is_available: filters.value.is_available ? filters.value.is_available.id : 'all',
        productAvailableDate: filters.value.productAvailableDate,
        sortKey: filters.value.sortKey,
        sortBy: filters.value.sortBy,
    }, {
        preserveState: true,
        replace: true,
    });
}



const onResetFilterClicked = () => {
    filters.value.operators = [
        operatorOptions.value.find(operator => operator.id === authOperator.id),
        ...authOperator.code == 'HIPL' ? [
            operatorOptions.value.find(operator => operator.code == 'HIMD'),
            operatorOptions.value.find(operator => operator.code == 'LEA'),
            operatorOptions.value.find(operator => operator.code == 'HIESG'),
            operatorOptions.value.find(operator => operator.code == 'UL-ST'),
        ] : []
    ].filter(Boolean);
    filters.value.product_name = '';
    filters.value.product_code = '';
    filters.value.is_available = booleanOptions.value[0];
    router.get(route('product-movements.index'));
}

const onTrackingDetailsClicked = () => {
    let operatorIds = filters.value.operators.map(op => op.id)
    router.get(route('product-movements.tracking-details'), {
        operators: operatorIds
    });
}

const onExcelExportClicked = () => {
    let operators = filters.value.operators.filter(operator => operator).map(operator => operator.id)
    if (operators.includes('all')) {
        operators = ['all']
    }
    axios({
        method: 'get',
        url: '/products/movements/export-excel',
        params: {
            product_name: filters.value.product_name,
            product_code: filters.value.product_code,
            operators: operators,
            is_available: filters.value.is_available ? filters.value.is_available.id : 'all',
            productAvailableDate: filters.value.productAvailableDate,
        },
        responseType: 'blob',
    }).then(response => {
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', 'Product_Movement_' + moment().format('YYMMDDHHmmss') + '.xlsx');
        document.body.appendChild(link);
        link.click();
    }).catch(error => {
        console.log(error)
    })
}

const openMovementModal = (product) => {
    selectedProduct.value = product
    form.reset()
    form.clearErrors()
    form.product_id = product.id
    form.type = 1
    form.qty = ''
    form.remarks = ''
    showMovementModal.value = true
}

const closeMovementModal = () => {
    showMovementModal.value = false
    selectedProduct.value = null
    form.reset()
}

const submitMovement = () => {
    form.post(route('product-movements.store'), {
        onSuccess: () => {
            closeMovementModal()
        }
    })
}

const onIsAvailableClicked = (product) => {
      // Check permissions if needed, e.g. 'admin-access product-availability' or similar
      // For now assuming existing permission check or logic from Availability page
      // Reuse the existing route for toggling availability
    router.post(route('products-availability.toggle-is-available'), {
      product_id: product.id
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    })
}

const onMaxOpsJobPickLimitSelected = (id, max_ops_job_pick_limit) => {
  axios.post(route('products-availability.update-max-ops-job-pick-limit', id), {
    date: moment(filters.value.productAvailableDate).format('YYYY-MM-DD'),
    max_ops_job_pick_limit,
  })
  .then(response => {
    // Update the state locally without reloading the page is complicated because
    // we need to update productLimits relation as well potentially?
    // Actually Availability just updates `product.max_ops_job_pick_limit`.
    // And `product.productLimits` for createdBy logic?
    // Let's reload to be safe and simple, or just update the limit if it was successful.
    // Availability implementation:
    // props.products.data[productIndex].max_ops_job_pick_limit = max_ops_job_pick_limit;
    // It doesn't seem to update createdBy info locally?
    // Let's just do Inertia reload to get fresh data, seeing the API returns redirect()->back()
    // But since it's axios, we won't get the redirect effect fully unless we reload.
    // Availability uses axios to avoid full reload.
    // I will use router.reload/visit to ensure data consistency?
    // Or stick to axios and manual update.
    // If I use axios, I should follow Availability's pattern.
    // But `ProductAvailability.vue` doesn't seem to update `productLimits` in the `.then`.
    // It just updates `max_ops_job_pick_limit`.
    // I'll do the same.

    const productIndex = props.products.data.findIndex(product => product.id === id);
    if (productIndex !== -1) {
        props.products.data[productIndex].max_ops_job_pick_limit = max_ops_job_pick_limit;
        // Optionally trigger a reload in background if needed, but UI update is immediate.
    }
  })
  .catch(error => {
    console.error('Error updating max_ops_job_pick_limit:', error);
  });
}

function getTotalMovementsQty() {
  return props.products.data.reduce((acc, product) => acc + (Number(product.total_movements_qty) || 0), 0);
}

function getTotalMovementsCost() {
  return props.products.data.reduce((acc, product) => {
    const qty = Number(product.total_movements_qty) || 0;
    const cost = Number(product.latestUnitCost?.cost) || 0;
    return acc + (qty * cost);
  }, 0);
}

function getPickedQtyTotal() {
  return props.products.data.reduce((acc, product) => acc + (Number(product.picked_qty_on_date) || 0), 0);
}

function getPickedQtyTotalCost() {
  return props.products.data.reduce((acc, product) => {
    const qty = Number(product.picked_qty_on_date) || 0;
    const cost = Number(product.latestUnitCost?.cost) || 0;
    return acc + (qty * cost);
  }, 0);
}

function getPickedValueTotal() {
  return props.products.data.reduce((acc, product) => acc + (Number(product.picked_value_on_date) || 0), 0);
}

function getNeededValueTotal() {
  return props.products.data.reduce((acc, product) => acc + (product.is_available ? (Number(product.needed_value) || 0) : 0), 0);
}

function getCalculatedWarehouseQtyTotal() {
  return props.products.data.reduce((acc, product) => acc + (Number(product.calculated_warehouse_qty) || 0), 0);
}

function getCalculatedWarehouseQtyTotalCost() {
  return props.products.data.reduce((acc, product) => {
    const qty = Number(product.calculated_warehouse_qty) || 0;
    const cost = Number(product.latestUnitCost?.cost) || 0;
    return acc + (qty * cost);
  }, 0);
}

function getNeededQtyTotal() {
  return props.products.data.reduce((acc, product) => acc + (product.is_available ? (Number(product.needed_qty) || 0) : 0), 0);
}

function getNeededQtyTotalCost() {
  return props.products.data.reduce((acc, product) => {
    const qty = product.is_available ? (Number(product.needed_qty) || 0) : 0;
    const cost = Number(product.latestUnitCost?.cost) || 0;
    return acc + (qty * cost);
  }, 0);
}

function getDailySoldQtyTotal() {
  return props.products.data.reduce((acc, product) => {
    const qty = Number(product.avg_seven_days_count) || 0;
    return acc + qty;
  }, 0);
}

function getDailySoldQtyTotalCost() {
  return props.products.data.reduce((acc, product) => {
    const qty = Number(product.avg_seven_days_count) || 0;
    const cost = Number(product.latestUnitCost?.cost) || 0;
    return acc + (qty * cost);
  }, 0);
}
</script>

<style src="@vueform/multiselect/themes/default.css"></style>
