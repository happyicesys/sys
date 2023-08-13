<template>

    <Head title="Dashboard" />

    <BreezeAuthenticatedLayout>
        <template #header>
            <div class="flex flex-col space-y-1">
                <div class="flex space-x-2 items-center">
                    Dashboard
                </div>
            </div>
        </template>

        <div class="p-3">
            <div class="max-w-7xl mx-auto sm:px-3 lg:px-2">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-4">
                        <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-gray-200 px-4 py-3 md:px-4 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        @click="showFilters = true"
                        v-if="!showFilters && permissions.includes('admin-access vends')"
                        >
                            <ChevronDoubleDownIcon class="h-4 w-4" aria-hidden="true"/>
                            <span>
                                Show Filters
                            </span>
                        </Button>
                    </div>
                    <div class="p-4 mx-2" v-if="showFilters">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
                        <SearchInput placeholderStr="Vend ID" v-model="filters.codes" @keyup.enter="onSearchFilterUpdated()">
                            Vend ID
                            <span class="text-[9px]">
                                ("," for multiple)
                            </span>
                        </SearchInput>
                        <SearchInput placeholderStr="Cust ID" v-model="filters.customer_code" @keyup.enter="onSearchFilterUpdated()">
                            Cust ID
                        </SearchInput>
                        <SearchInput placeholderStr="Cust Name" v-model="filters.customer_name" @keyup.enter="onSearchFilterUpdated()">
                            Cust Name
                        </SearchInput>
                        <div v-if="permissions.includes('admin-access vends')">
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
                        <div v-if="permissions.includes('admin-access vends')">
                            <label for="text" class="block text-sm font-medium text-gray-700">
                                Operator
                            </label>
                            <MultiSelect
                                v-model="filters.operator"
                                :options="operatorOptions"
                                trackBy="id"
                                valueProp="id"
                                label="name"
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
                                label="name"
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
                                @click="resetFilters()"
                                >
                                    <BackspaceIcon class="h-4 w-4" aria-hidden="true"/>
                                    <span>
                                        Reset
                                    </span>
                                </Button>
                                <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                @click="showFilters = false"
                                >
                                    <ChevronDoubleUpIcon class="h-4 w-4" aria-hidden="true"/>
                                    <span>
                                        Hide Filters
                                    </span>
                                </Button>
                            </div>
                        </div>
                    </div>
                    </div>


                    <div class="p-1 py-4 bg-white border-b border-gray-200 flex flex-col space-y-6">
                        <p class="text-center p-2">
                            {{ (filters && filters.operator ? filters.operator.name : operator.name)  }}
                        </p>
                        <Graph
                            :key="componentKey1"
                            type="scatter"
                            :labels="dayGraphLabels"
                            :datasets="dayGraphDatasets"
                            :options="dayGraphOptions"
                        >
                        </Graph>

                        <div class="flex flex-col md:flex-row pt-5">
                            <div class="md:basis-1/3 m-1">
                                <Graph
                                    :key="componentKey2"
                                    type="pie"
                                    :labels="productGraphLabels"
                                    :datasets="productGraphDatasets"
                                    :options="productGraphOptions"
                                ></Graph>
                            </div>
                            <div class="md:basis-2/3 my-1 mx-4 px-4">
                                <p class="text-sm flex justify-between">
                                    <div>
                                        Past 30 Days - Top 10 Best Performance
                                    </div>
                                    <div>
                                        Based on {{ vendCount }} active machine(s)
                                    </div>
                                </p>
                                <div class="mt-2 flow-root">
                                    <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                                        <div class="inline-block min-w-full py-2 align-middle sm:px-3 lg:px-4">
                                        <div class="overflow-auto shadow ring-1 ring-black ring-opacity-5 sm:rounded-lg">
                                            <table class="min-w-full divide-y divide-gray-300">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th scope="col" class="py-2 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">
                                                        #
                                                    </th>
                                                    <th scope="col" class="px-3 py-2 text-left text-sm font-semibold text-gray-900">
                                                        Vending Machine
                                                    </th>
                                                    <th scope="col" class="px-3 py-2 text-left text-sm font-semibold text-gray-900">
                                                        Amount($)
                                                    </th>
                                                    <th scope="col" class="px-3 py-2 text-left text-sm font-semibold text-gray-900">
                                                        Sales(#)
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200 bg-white">
                                                <tr v-for="(vend, vendIndex) in performerGraphData.data" :key="vend.id">
                                                    <td class="whitespace-nowrap py-1 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">
                                                        {{ vendIndex + 1 }}
                                                    </td>
                                                    <td class="whitespace-nowrap px-3 py-1 text-sm text-gray-600">
                                                        <span v-if="vend.customer">
                                                            {{ vend.customer.code }} <br>
                                                            {{ vend.customer.name }}
                                                        </span>
                                                        <span v-else>
                                                            {{ vend.code }} <br>
                                                            {{ vend.name }}
                                                        </span>
                                                    </td>
                                                    <td class="whitespace-nowrap px-3 py-1 text-sm text-gray-500 text-right mx-3">
                                                        {{ vend.amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}
                                                    </td>
                                                    <td class="whitespace-nowrap px-3 py-1 text-sm text-gray-500 text-right mx-3">
                                                        {{ vend.count }}
                                                    </td>
                                                </tr>
                                                <tr v-if="!performerGraphData.data.length">
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
                            </div>
                        </div>

                        <div class="pt-5">
                            <Graph
                                :key="componentKey3"
                                type="scatter"
                                :labels="monthGraphLabels"
                                :datasets="monthGraphDatasets"
                                :options="monthGraphOptions"
                            >
                            </Graph>
                        </div>
                        <div class="pt-5">
                            <Graph
                                :key="componentKey4"
                                type="scatter"
                                :labels="activeMachineGraphLabels"
                                :datasets="activeMachineGraphDatasets"
                                :options="activeMachineGraphOptions"
                            >
                            </Graph>
                        </div>

                        <div class="pt-5 my-1 mx-4 px-4" v-if="permissions.includes('admin-access vends')">
                            <p class="text-sm flex justify-between">
                                <div>
                                    Monthly Analytics By Criteria
                                </div>
                            </p>
                            <div class="pb-3 mb-2">
                                <div class="sm:hidden">
                                    <label for="tabs" class="sr-only">Select a tab</label>
                                    <select id="tabs" name="tabs" class="block w-full rounded-md border-gray-300 py-2 pl-3 pr-10 text-base focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 sm:text-sm" @change="onTabChanged($event)">
                                        <option v-for="tab in tabs" :key="tab" :value="tab.href" :selected="tab.current">
                                            {{ tab.name }}
                                        </option>
                                    </select>
                                </div>
                                <div class="hidden sm:block">
                                    <div class="border-b border-gray-200">
                                        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                                            <span v-for="tab in tabs" :key="tab.name"
                                            class="hover:cursor-pointer"
                                            :class="[tab.current ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:border-gray-200 hover:text-gray-700', 'flex whitespace-nowrap border-b-2 py-4 px-1 text-sm font-medium']"
                                            @click="onTabChanged(tab)"
                                            >
                                                {{ tab.name }}
                                        </span>
                                        </nav>
                                    </div>
                                </div>
                            </div>
                            <div class="mt-2 flow-root">
                                <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                                    <div class="inline-block min-w-full py-2 align-middle sm:px-3 lg:px-4">
                                    <div class="overflow-auto shadow ring-1 ring-black ring-opacity-5 sm:rounded-lg">
                                        <table class="min-w-full divide-y divide-gray-300">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th scope="col" class="px-3 py-2 text-center text-sm font-semibold text-gray-900">
                                                    Name
                                                </th>
                                                <th scope="col" class="px-3 py-2 text-left text-sm font-semibold text-gray-900">
                                                </th>
                                                <th scope="col" class="px-3 py-2 text-center text-sm font-semibold text-gray-900" v-for="month in months.data">
                                                    <span :class="[
                                                        month.number == moment().format('M') ? 'bg-yellow-300 rounded p-2' : ''
                                                    ]">
                                                        {{ month.short_name }}
                                                    </span>
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 bg-white" v-for="(item, itemIndex) in monthsByModel">

                                            <tr >
                                                <td rowspan="3" class="whitespace-nowrap py-1 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 row-span-3">
                                                    {{ itemIndex }}
                                                </td>
                                                <td class="whitespace-nowrap py-1 pl-4 pr-3 text-sm font-medium text-gray-600 sm:pl-6 row-span-3">
                                                    Daily Sales/ VM
                                                </td>
                                                <td class="whitespace-nowrap px-3 py-1 text-sm text-gray-600 text-right" v-for="month in months.data">
                                                    <span v-for="(data, dataIndex) in item">
                                                        <span v-if="month.number == dataIndex"
                                                            :class="[
                                                                data.current ? 'font-bold' : 'font-medium',
                                                                item[dataIndex - 1] && item[dataIndex - 1].average < data.average ? 'text-green-600' : (!item[dataIndex - 1] ? '' : 'text-red-600' )
                                                            ]"
                                                        >
                                                            {{ data.average.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}
                                                        </span>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="whitespace-nowrap py-1 pl-4 pr-3 text-sm font-medium text-gray-600 sm:pl-6 row-span-3">
                                                    Machine Count
                                                </td>
                                                <td class="whitespace-nowrap px-3 py-1 text-sm text-gray-600 text-right" v-for="month in months.data">
                                                    <span v-for="(data, dataIndex) in item">
                                                        <span v-if="month.number == dataIndex"
                                                            :class="[
                                                                    data.current ? 'font-bold' : 'font-medium',
                                                                    item[dataIndex - 1] && item[dataIndex - 1].average < data.average ? 'text-green-600' : (!item[dataIndex - 1] ? '' : 'text-red-600' )
                                                            ]"
                                                        >
                                                            {{ data.vend_count.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0}) }}
                                                        </span>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td class="whitespace-nowrap py-1 pl-4 pr-3 text-sm font-medium text-gray-600 sm:pl-6 row-span-3">
                                                    Total Sales
                                                </td>
                                                <td class="whitespace-nowrap px-3 py-1 text-sm text-gray-600 text-right" v-for="month in months.data">
                                                    <span v-for="(data, dataIndex) in item">
                                                        <span v-if="month.number == dataIndex"
                                                            :class="[
                                                                    data.current ? 'font-bold' : 'font-medium',
                                                                    item[dataIndex - 1] && item[dataIndex - 1].average < data.average ? 'text-green-600' : (!item[dataIndex - 1] ? '' : 'text-red-600' )
                                                            ]"
                                                        >
                                                            {{ data.amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}
                                                        </span>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr v-if="!performerGraphData.data.length">
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
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </BreezeAuthenticatedLayout>
</template>
<script setup>
    import { ChevronDoubleDownIcon, ChevronDoubleUpIcon, MagnifyingGlassIcon, BackspaceIcon} from '@heroicons/vue/20/solid';
    import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
    import Button from '@/Components/Button.vue';
    import Graph from '@/Components/Graph.vue';
    import MultiSelect from '@/Components/MultiSelect.vue';
    import SearchInput from '@/Components/SearchInput.vue';
    import { ref, onBeforeMount, watch } from 'vue';
    import { Head, Link, router, usePage } from '@inertiajs/vue3';
    import moment from 'moment';

    const props = defineProps({
        activeMachineGraphData: Object,
        categories: Object,
        categoryGroups: Object,
        dayGraphData: Object,
        locationTypeOptions: Object,
        monthGraphData: Object,
        months: Object,
        monthsByModel: Object,
        operatorOptions: Object,
        productGraphData: Object,
        performerGraphData: Object,
        vendCount: Number,
    });
    const filters = ref({
        categories: [],
        categoryGroups: [],
        codes: '',
        customer_code: '',
        customer_name: '',
        day_date_from: '',
        day_date_to: '',
        locationType: '',
        operator: '',
        monthlyTypeName: 'location-type',
    })
    const categoryOptions = ref([])
    const categoryGroupOptions = ref([])
    const componentKey1 = ref(0);
    const componentKey2 = ref(0);
    const componentKey3 = ref(0);
    const componentKey4 = ref(0);
    const forceRerender1 = () => {
        componentKey1.value += 1;
    };
    const forceRerender2 = () => {
        componentKey2.value += 1;
    };
    const forceRerender3 = () => {
        componentKey3.value += 1;
    };
    const forceRerender4 = () => {
        componentKey4.value += 1;
    };
    const locationTypeOptions = ref([])
    const operator = usePage().props.auth.operator
    const operatorOptions = ref([])
    const permissions = usePage().props.auth.permissions
    const showFilters = ref(false)
    const tabs = ref([
        { name: 'Location Type', slug: 'location-type', current: true, href: '#' },
        { name: 'Category', slug: 'category', current: false, href: '#' },
        { name: 'Operator', slug: 'operator', current: false, href: '#' },
    ])

    const dayGraphData = ref([]);
    const dayGraphDatasets = ref([])
    const dayGraphLabels = ref([])
    const dayGraphOptions = ref({
        scales: {
            x: {
                ticks: {
                    min: 1,  // Minimum value on the x-axis
                    max: 31, // Maximum value on the x-axis
                    stepSize: 1 // Increment between ticks
                }
            },
            y: {
                position: 'left',
                title: {
                    display: true,
                    text: 'Sales($)'
                },
                beginAtZero: true

            },
            y1: {
                position: 'right',
                title: {
                    display: true,
                    text: 'Sales(#)'
                },
                beginAtZero: true
            },
        },
        plugins: {
            title: {
                display: true,
                text: 'Sales by Days'
            },
            legend: {
                reverse: true,
            }
        }
    })

    const monthGraphData = ref([]);
    const monthGraphDatasets = ref([])
    const monthGraphLabels = ref([])
    const monthGraphOptions = ref({
        scales: {
            x: {
                ticks: {
                    min: 1,  // Minimum value on the x-axis
                    max: 12, // Maximum value on the x-axis
                    stepSize: 1 // Increment between ticks
                }
            },
            y: {
                position: 'left',
                title: {
                    display: true,
                    text: 'Sales($)'
                },
                beginAtZero: true

            },
            y1: {
                position: 'right',
                title: {
                    display: true,
                    text: 'Sales(#)'
                },
                beginAtZero: true
            },
        },
        plugins: {
            title: {
                display: true,
                text: 'Sales by Months'
            },
            legend: {
                reverse: true,
            }
        }
    })

    const productGraphData = ref([])
    const productGraphDatasets = ref([])
    const productGraphLabels = ref([])
    const productGraphOptions = ref({
        plugins: {
            legend: {
                display: false,
            },
            title: {
                display: true,
                text: 'Past 7 Days - 10 Best Sellers'
            },
        }
    })

    const performerGraphData = ref([])

    const activeMachineGraphData = ref([]);
    const activeMachineGraphDatasets = ref([])
    const activeMachineGraphLabels = ref([])
    const activeMachineGraphOptions = ref({
        scales: {
            x: {
                ticks: {
                    min: 1,  // Minimum value on the x-axis
                    max: 12, // Maximum value on the x-axis
                    stepSize: 1 // Increment between ticks
                }
            },
            y: {
                position: 'left',
                title: {
                    display: true,
                    text: 'Count(#)'
                },
                beginAtZero: true

            },
        },
        plugins: {
            title: {
                display: true,
                text: 'Number of Active Vending Machine in Market, by Month'
            },
        }
    })


    onBeforeMount(() => {
        categoryOptions.value = props.categories.data.map((data) => {return {id: data.id, name: data.name}})
        categoryGroupOptions.value = props.categoryGroups.data.map((data) => {return {id: data.id, name: data.name}})
        locationTypeOptions.value = [
            {id: 'all', name: 'All'},
            ...props.locationTypeOptions.data.map((data) => {return {id: data.id, name: data.name}})
        ]
        operatorOptions.value = [
            {id: 'all', name: 'All'},
            ...props.operatorOptions.data.map((data) => {return {id: data.id, name: data.name}})
        ]
        filters.value.locationType = locationTypeOptions.value[0]
        filters.value.operator = operatorOptions.value.find(data =>  data.id == operator.id)
        syncDashboardData()
    })

    function hexToRGBA(hex, alpha) {
        var r = parseInt(hex.slice(1, 3), 16);
        var g = parseInt(hex.slice(3, 5), 16);
        var b = parseInt(hex.slice(5, 7), 16);

        return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
    }

    function onSearchFilterUpdated() {
        router.visit(
            route('dashboard', {
                ...filters.value,
                categories: filters.value.categories.map((category) => { return category.id }),
                categoryGroups: filters.value.categoryGroups.map((categoryGroup) => { return categoryGroup.id }),
                location_type_id: filters.value.locationType.id,
                operator_id: filters.value.operator.id,
            }),{
                only: ['activeMachineGraphData', 'dayGraphData', 'monthGraphData', 'monthsByModel', 'productGraphData', 'performerGraphData', 'vendCount'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onSuccess: (page) => {
                    router.reload({
                        only: ['activeMachineGraphData', 'dayGraphData', 'monthGraphData', 'monthsByModel', 'productGraphData', 'performerGraphData', 'vendCount'],
                        preserveState: true,
                        preserveScroll: true,
                    })
                    syncDashboardData()
                },
            }
        );
    }

    function onTabChanged(tab) {
        tabs.value.forEach((tab) => {
            tab.current = false
        })
        tab.current = true
        filters.value.monthlyTypeName = tab.slug
        onSearchFilterUpdated()
    }

    function resetFilters() {
        router.get('/dashboard', {}, {
            preserveState: false,
            preserveScroll: true,
        })
    }

    function syncDashboardData () {
        activeMachineGraphData.value = []
        activeMachineGraphDatasets.value = []
        activeMachineGraphLabels.value = []
        dayGraphData.value = []
        dayGraphDatasets.value = []
        dayGraphLabels.value = []
        monthGraphData.value = []
        monthGraphDatasets.value = []
        monthGraphLabels.value = []
        productGraphData.value = []
        productGraphDatasets.value = []
        productGraphLabels.value = []

        let colors = ['#3e95cd', '#ff7f7f', '#007500', '#808080', '#c45850']
        let generalColors = [
            '#37a2eb',
            '#ff6384',
            '#4cc1c0',
            '#ff9f40',
            '#9a66ff',
            '#ffcd56',
            '#c9cbcf'
        ]
        dayGraphData.value = JSON.parse(JSON.stringify(props.dayGraphData))
        let months = []
        months = _.groupBy(JSON.parse(JSON.stringify(props.dayGraphData)).data, 'month_name')
        Object.keys(months).forEach((month, monthIndex) => {
            dayGraphDatasets.value.push({
                label: month + ' (#)',
                data: months[month].map((data) => {return data.count}),
                backgroundColor: monthIndex % 2 == 0 ? hexToRGBA(colors[monthIndex + 2], 0.2) : hexToRGBA(colors[monthIndex + 2], 0.9),
                borderColor: monthIndex % 2 == 0 ? hexToRGBA(colors[monthIndex + 2], 0.2) : hexToRGBA(colors[monthIndex + 2], 0.9),
                yAxisID: 'y1',
                type: 'line',
                order: 1,
            })
            dayGraphDatasets.value.push({
                label: month + ' ($)',
                data: months[month].map((data) => {return data.amount}),
                backgroundColor: monthIndex % 2 == 0 ? hexToRGBA(colors[monthIndex], 0.2) : hexToRGBA(colors[monthIndex], 1),
                borderColor: monthIndex % 2 == 0 ? hexToRGBA(colors[monthIndex], 0.2) : hexToRGBA(colors[monthIndex], 1),
                fill: false,
                yAxisID: 'y',
                type: 'bar',
                order: 2,
            })
        })
        for(let i = 1; i <= 31; i++) {
            dayGraphLabels.value.push(i)
        }

        monthGraphData.value = JSON.parse(JSON.stringify(props.monthGraphData))
        let years = []
        years = JSON.parse(JSON.stringify(props.monthGraphData))
        Object.keys(years).forEach((month, monthIndex) => {
            monthGraphDatasets.value.push({
                label: month + ' (#)',
                data: Object.values(years[month]).map((data) => {return data.count}),
                backgroundColor: monthIndex % 2 == 0 ? hexToRGBA(colors[monthIndex + 2], 0.2) : hexToRGBA(colors[monthIndex + 2], 0.9),
                borderColor: monthIndex % 2 == 0 ? hexToRGBA(colors[monthIndex + 2], 0.2) : hexToRGBA(colors[monthIndex + 2], 0.9),
                yAxisID: 'y1',
                type: 'line',
                order: 1,
            })
            monthGraphDatasets.value.push({
                label: month + ' ($)',
                data: Object.values(years[month]).map((data) => {return data.amount}),
                backgroundColor: monthIndex % 2 == 0 ? hexToRGBA(colors[monthIndex], 0.2) : hexToRGBA(colors[monthIndex], 1),
                borderColor: monthIndex % 2 == 0 ? hexToRGBA(colors[monthIndex], 0.2) : hexToRGBA(colors[monthIndex], 1),
                fill: false,
                yAxisID: 'y',
                type: 'bar',
                order: 2,
            })
        })
        for(let i = 1; i <= 12; i++) {
            monthGraphLabels.value.push(i)
        }

        productGraphData.value = JSON.parse(JSON.stringify(props.productGraphData))
        productGraphDatasets.value.push({
            label: 'Sales',
            data: productGraphData.value.data.map((data) => {return data.count}),
            backgroundColor: generalColors,
        })
        productGraphLabels.value = productGraphData.value.data.map((data) => {return data.product ? data.product.code + ' - ' + data.product.name : null})

        performerGraphData.value = JSON.parse(JSON.stringify(props.performerGraphData))


        activeMachineGraphData.value = JSON.parse(JSON.stringify(props.activeMachineGraphData))
        let activeYears = []
        activeYears = JSON.parse(JSON.stringify(props.activeMachineGraphData))
        Object.keys(activeYears).forEach((activeMonth, activeMonthIndex) => {
            activeMachineGraphDatasets.value.push({
                label: activeMonth + ' (#)',
                data: Object.values(activeYears[activeMonth]).map((data) => {return data.count}),
                backgroundColor: activeMonthIndex % 2 == 0 ? hexToRGBA(colors[activeMonthIndex], 0.2) : hexToRGBA(colors[activeMonthIndex], 0.9),
                borderColor: activeMonthIndex % 2 == 0 ? hexToRGBA(colors[activeMonthIndex], 0.2) : hexToRGBA(colors[activeMonthIndex], 0.9),
                type: 'line',
            })
        })
        for(let i = 1; i <= 12; i++) {
            activeMachineGraphLabels.value.push(i)
        }

        forceRerender1()
        forceRerender2()
        forceRerender3()
        forceRerender4()
    }

</script>
