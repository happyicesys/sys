<script setup>
import { ref, onMounted, watch } from 'vue'
import { Head, router, usePage } from '@inertiajs/vue3'
import { MagnifyingGlassIcon, BackspaceIcon, ArrowDownTrayIcon } from '@heroicons/vue/24/solid'
import axios from 'axios'
import MultiSelect from '@/Components/MultiSelect.vue'
import DatePicker from '@/Components/DatePicker.vue'
import SearchInput from '@/Components/SearchInput.vue'
import moment from 'moment'
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import Button from '@/Components/Button.vue'
import TableHead from '@/Components/TableHead.vue'
import TableData from '@/Components/TableData.vue'
import Paginator from '@/Components/Paginator.vue'

const props = defineProps({
    movements: Object,
    filters: Object,
    products: Object,
    operatorOptions: Object,
    startBalance: Number,
})

const permissions = usePage().props.auth.permissions
const authOperator = usePage().props.auth.operator

const operatorOptions = ref([])
const productOptions = ref([])

const filters = ref({
    product_id: '',
    operators: [],
    date_from: moment().format('YYYY-MM-DD'),
    date_to: moment().format('YYYY-MM-DD'),
})

onMounted(() => {
    // Initialize Operators
    operatorOptions.value = [
        ...props.operatorOptions.data.map((data) => {return {id: data.id, code: data.code, full_name: data.full_name}})
    ]

    // Set default operators based on auth
    if (props.filters.operators) {
        filters.value.operators = props.filters.operators.map(id =>
            operatorOptions.value.find(op => op.id == id)
        ).filter(Boolean)
    } else {
        filters.value.operators = authOperator ? [
            operatorOptions.value.find(operator => operator.id === authOperator.id),
            ...authOperator.code == 'HIPL' ? [
                operatorOptions.value.find(operator => operator.code == 'HIMD'),
                operatorOptions.value.find(operator => operator.code == 'LEA'),
                operatorOptions.value.find(operator => operator.code == 'DCVIC'),
                operatorOptions.value.find(operator => operator.code == 'HIESG'),
                operatorOptions.value.find(operator => operator.code == 'IP'),
            ] : [],
        ].filter(Boolean) : []
    }

    // Initialize Products
    productOptions.value = props.products.data.map(p => ({
        id: p.id,
        name: `${p.code} - ${p.name}`
    }))

    if (props.filters.product_id) {
        filters.value.product_id = props.filters.product_id
    }

    if (props.filters.date_from) {
        filters.value.date_from = props.filters.date_from
    }
    if (props.filters.date_to) {
        filters.value.date_to = props.filters.date_to
    }
})

const onSearchFilterUpdated = () => {
    let operatorIds = filters.value.operators.map(op => op.id)
    router.get(route('product-movements.tracking-details'), {
        product_id: filters.value.product_id,
        operators: operatorIds,
        date_from: filters.value.date_from,
        date_to: filters.value.date_to,
    }, {
        preserveState: true,
        replace: true,
    })
}

const onResetFilterClicked = () => {
    filters.value.product_id = ''
    filters.value.operators = []
    filters.value.date_from = moment().format('YYYY-MM-DD')
    filters.value.date_to = moment().format('YYYY-MM-DD')

    // Reset operators default
    filters.value.operators = authOperator ? [
        operatorOptions.value.find(operator => operator.id === authOperator.id),
        ...authOperator.code == 'HIPL' ? [
            operatorOptions.value.find(operator => operator.code == 'HIMD'),
            operatorOptions.value.find(operator => operator.code == 'LEA'),
            operatorOptions.value.find(operator => operator.code == 'DCVIC'),
            operatorOptions.value.find(operator => operator.code == 'HIESG'),
            operatorOptions.value.find(operator => operator.code == 'IP'),
        ] : [],
    ].filter(Boolean) : []

    onSearchFilterUpdated()
}

const onExcelExportClicked = () => {
    let operatorIds = filters.value.operators.map(op => op.id)
    axios({
        method: 'get',
        url: '/products/movements/tracking-export-excel',
        params: {
            product_id: filters.value.product_id,
            operators: operatorIds,
            date_from: filters.value.date_from,
            date_to: filters.value.date_to,
        },
        responseType: 'blob',
    }).then(response => {
        const url = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', 'Product_Movement_Tracking_' + moment().format('YYMMDDHHmmss') + '.xlsx');
        document.body.appendChild(link);
        link.click();
    }).catch(error => {
        console.log(error)
    })
}

</script>

<template>
    <Head title="Product Movement Tracking Details" />

    <BreezeAuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Product Movement Tracking Details
            </h2>
        </template>

        <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
            <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3">
                <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
                    <div class="col-span-5 md:col-span-1">
                        <label class="block text-sm font-medium text-gray-700">Product</label>
                        <MultiSelect
                            v-model="filters.product_id"
                            :options="productOptions"
                            trackBy="id"
                            valueProp="id"
                            label="name"
                            placeholder="Select Product"
                            open-direction="bottom"
                            class="mt-1"
                            :searchable="true"
                        >
                        </MultiSelect>
                    </div>
                    <div class="col-span-5 md:col-span-1">
                        <label class="block text-sm font-medium text-gray-700">Operator</label>
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
                        <DatePicker v-model="filters.date_from">From</DatePicker>
                    </div>
                    <div class="col-span-5 md:col-span-1">
                        <DatePicker v-model="filters.date_to" :minDate="filters.date_from">To</DatePicker>
                    </div>
                </div>

                <div class="flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5">
                    <div class="mt-3">
                        <div class="flex space-x-1">
                            <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                @click.prevent="onSearchFilterUpdated()"
                            >
                                <MagnifyingGlassIcon class="h-4 w-4" aria-hidden="true"/>
                                <span>Search</span>
                            </Button>
                            <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                @click.prevent="onResetFilterClicked()"
                            >
                                <BackspaceIcon class="h-4 w-4" aria-hidden="true"/>
                                <span>Reset</span>
                            </Button>
                            <Button class="inline-flex space-x-1 items-center rounded-md border border-gray-800 bg-white px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                @click="onExcelExportClicked()" v-if="permissions.includes('export products')"
                            >
                                <ArrowDownTrayIcon class="h-4 w-4" aria-hidden="true"/>
                                <span>Export Excel</span>
                            </Button>
                        </div>
                    </div>
                    <div class="flex flex-col space-y-2">
                        <p class="text-sm text-gray-700 leading-5 flex space-x-1">
                             <span>Showing</span>
                            <span class="font-medium">{{ movements.from ?? 0 }}</span>
                            <span>to</span>
                            <span class="font-medium">{{ movements.to ?? 0 }}</span>
                            <span>of</span>
                            <span class="font-medium">{{ movements.total }}</span>
                            <span>results</span>
                        </p>
                    </div>
                </div>
            </div>

            <div class="mt-6 flex flex-col">
                <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
                    <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll">
                        <table class="table-auto min-w-full border-separate" style="border-spacing: 0">
                            <thead class="">
                                <tr class="divide-x bg-gray-400">
                                    <TableHead>#</TableHead>
                                    <TableHead>Date</TableHead>
                                    <TableHead>Type</TableHead>
                                    <TableHead>Product Code</TableHead>
                                    <TableHead>Product Name</TableHead>
                                    <TableHead>Qty</TableHead>
                                    <TableHead>Remarks</TableHead>
                                </tr>
                            </thead>
                            <tbody class="bg-white">
                                <tr v-for="(movement, index) in movements.data" :key="movement.id + '-' + movement.source_type" class="divide-x divide-y-2 divide-gray-300">
                                    <TableData :currentIndex="index" :totalLength="movements.data.length" inputClass="text-center">
                                        {{ (movements.from ?? 1) + index }}
                                    </TableData>
                                    <TableData :currentIndex="index" :totalLength="movements.data.length" inputClass="text-center">
                                        {{ movement.date }}
                                    </TableData>
                                    <TableData :currentIndex="index" :totalLength="movements.data.length" inputClass="text-center">
                                         <span :class="{
                                            'text-green-600 font-bold': movement.type_label === 'Incoming',
                                            'text-blue-600 font-bold': movement.type_label === 'Adjustment', // Assuming Adjustment is blue
                                            'text-red-600 font-bold': movement.type_label === 'Outgoing',
                                         }">
                                            {{ movement.type_label }}
                                        </span>
                                    </TableData>
                                    <TableData :currentIndex="index" :totalLength="movements.data.length" inputClass="text-center">
                                        {{ movement.product_code }}
                                    </TableData>
                                    <TableData :currentIndex="index" :totalLength="movements.data.length" inputClass="text-left">
                                        {{ movement.product_name }}
                                    </TableData>
                                    <TableData :currentIndex="index" :totalLength="movements.data.length" inputClass="text-center font-bold">
                                        <span :class="movement.qty > 0 ? 'text-green-600' : 'text-red-600'">
                                            {{ Number(movement.qty).toLocaleString() }}
                                        </span>
                                    </TableData>
                                    <TableData :currentIndex="index" :totalLength="movements.data.length" inputClass="text-left">
                                        {{ movement.remarks }}
                                    </TableData>
                                </tr>
                                <tr v-if="movements.data.length === 0">
                                    <td colspan="8" class="p-3 text-center text-gray-500">
                                        No tracking data found for the selected criteria.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
             <div class="mt-4">
                <Paginator v-if="movements.data.length"
                    :links="{ prev: movements.prev_page_url, next: movements.next_page_url }"
                    :meta="movements"
                />
            </div>
        </div>
    </BreezeAuthenticatedLayout>
</template>
