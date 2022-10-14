
<template>

    <Head title="Vending Machines" />

    <BreezeAuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Vending Machines (List)
            </h2>
        </template>

        <div class="m-2 sm:mx-5 sm:my-3 px-2 sm:px-4 lg:px-6">
        <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-5 px-3 md:px-6 py-6 ">
            <!-- <div class="flex flex-col md:flex-row md:space-x-3 space-y-1 md:space-y-0"> -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
                <SearchInput placeholderStr="Code" v-model="filters.code">
                    Vend ID
                </SearchInput>
                <SearchInput placeholderStr="Serial Num" v-model="filters.serialNum">
                    Serial Num
                </SearchInput>
                <SearchInput placeholderStr="Number" v-model="filters.tempHigherThan">
                    Temp >>
                </SearchInput>
                <div>
                    <label for="text" class="block text-sm font-medium text-gray-700">
                        Errors?
                    </label>
                    <MultiSelect
                        v-model="filters.vend_channel_error_id"
                        :options="vendChannelErrorsOptions"
                        trackBy="id"
                        valueProp="id"
                        label="desc"
                        placeholder="Select"
                        open-direction="bottom"
                        class="mt-1"
                    >
                    </MultiSelect>
                </div>
                <SearchInput placeholderStr="Cust ID" v-model="filters.customer_code">
                    Cust ID
                </SearchInput>
                <SearchInput placeholderStr="Cust ID Name" v-model="filters.customer_name">
                    Cust ID Name
                </SearchInput>
                <div>
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
                <div>
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
                        <span class="font-medium">{{ vends.meta.from ?? 0 }}</span>
                        <span>to</span>
                        <span class="font-medium">{{ vends.meta.to ?? 0 }}</span>
                        <span>of</span>
                        <span class="font-medium">{{ vends.meta.total }}</span>
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
                            <TableHeadSort modelName="code" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('code')">
                                Code
                            </TableHeadSort>
                            <TableHeadSort modelName="temp" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('temp')">
                                Temp
                            </TableHeadSort>
                            <TableHead>
                                Name
                            </TableHead>
                            <TableHead>
                                Category
                            </TableHead>
                            <TableHead>
                                Channel Status
                            </TableHead>
                            <TableHead>
                                Inventory Status
                            </TableHead>
                            <TableHeadSort modelName="temp_updated_at" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('temp_updated_at')">
                                Last Temp
                            </TableHeadSort>
                            <TableHeadSort modelName="coin_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('coin_amount')">
                                Coin Amount
                            </TableHeadSort>
                            <TableHead>
                                Serial Num
                            </TableHead>
                            <TableHead>
                                Firmware Ver
                            </TableHead>
                            <TableHead>
                                Door Opening?
                            </TableHead>
                            <TableHead>
                                Sensor Normal?
                            </TableHead>

                            <th scope="col"
                                class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pr-4 pl-3 backdrop-blur backdrop-filter sm:pr-6 lg:pr-8">
                                <span class="sr-only">Edit</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
                        <tr v-for="(vend, vendIndex) in vends.data" :key="vend.id"
                            class="divide-x divide-gray-200">
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                {{ vends.meta.from + vendIndex }}
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                {{ vend.code }}
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                <div class="flex flex-col">
                                    <button
                                        type="button"
                                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center"
                                        :class="[vend.temp > -15 ? 'bg-red-400 active:bg-red-500 hover:bg-red-600' : 'bg-green-400 active:bg-green-500 hover:bg-green-600']"
                                        @click="onVendTempClicked(vend.id)"
                                    >
                                        {{ vend.is_temp_error ? 'Error' : vend.temp }}
                                    </button>
                                </div>
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-left">
                                <!-- {{  vend.latestVendBinding.customer.code }} -->
                                {{ vend.latestVendBinding && vend.latestVendBinding.customer ? vend.latestVendBinding.customer.code : null }} <br>
                                {{ vend.latestVendBinding && vend.latestVendBinding.customer ? vend.latestVendBinding.customer.name : null }}
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-left">
                                {{ vend.latestVendBinding && vend.latestVendBinding.customer && vend.latestVendBinding.customer.category ? vend.latestVendBinding.customer.category.name : null }} <br>
                                {{ vend.latestVendBinding && vend.latestVendBinding.customer && vend.latestVendBinding.customer.category && vend.latestVendBinding.customer.category.category_group ? vend.latestVendBinding.customer.category.category_group.name : null }}
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                <span v-for="
                                            channel in vend.vendChannels
                                            .map(function(channel){
                                                return channel
                                            })
                                            .filter(function(channel) {
                                                return (channel.vendChannelErrorLogs.length) ?? channel.vendChannelErrorLogs
                                            })"
                                    class="flex flex-col space-y-1"
                                >
                                    <span v-for="error in channel.vendChannelErrorLogs.filter(function(error) {
                                        return !error.is_error_cleared
                                    })" class="inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border" :class="[error.is_error_cleared ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']">
                                        #{{channel.code}}, {{ error.vendChannelError.desc }} <br>
                                        {{error.created_at}}
                                    </span>
                                </span>
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                <div class="grid grid-cols-[100px_minmax(100px,_1fr)_100px] gap-1">
                                    <div v-for="
                                                channel in vend.vendChannels
                                                .map(function(channel){
                                                    return channel
                                                })
                                                .filter(function(channel) {
                                                    return channel.capacity > 0 && channel.code < 1000
                                                })"
                                        class="inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border min-w-full"
                                        :class="[channel.capacity > 0 ? 'bg-gray-50 text-gray-900' : 'bg-red-100 text-red-800']"
                                    >
                                        <div class="font-semibold">
                                            #{{channel.code}},
                                        </div>
                                        <div class="text-blue-600 text-sm pl-1">
                                            {{channel.capacity - channel.qty}},
                                        </div>
                                        <div class="pl-1">
                                            {{channel.qty}}/{{channel.capacity}}
                                        </div>
                                    </div>
                                    <div class="col-span-3 inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border min-w-full space-x-2">
                                        <span>
                                            Total
                                        </span>
                                        <span class="text-blue-600 text-sm">
                                            {{getTotalCapacity(vend) - getTotalQty(vend)}},
                                        </span>
                                        <span>
                                            {{getTotalQty(vend)}}/{{getTotalCapacity(vend)}}
                                        </span>
                                    </div>
                                </div>
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                {{ vend.temp_updated_at }}
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-right">
                                {{ vend.coin_amount }}
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                {{ vend.serial_num }}
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                {{ vend.firmware_ver }}
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                {{ vend.is_door_open }}
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                {{ vend.is_sensor_normal }}
                            </TableData>
                            <td
                                :class="[vendIndex !== vends.length - 1 ? 'border-b border-gray-200' : '', 'relative whitespace-nowrap py-4 pr-4 pl-3 text-right text-sm font-medium sm:pr-6 lg:pr-8']">
                                <a href="#" class="text-indigo-600 hover:text-indigo-900">Edit<span
                                        class="sr-only">, {{ vend.name }}</span></a>
                            </td>
                        </tr>
                        <tr v-if="!vends.data.length">
                            <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                                No Results Found
                            </td>
                        </tr>
                    </tbody>
                </table>
                <Paginator v-if="vends.data.length" :links="vends.links" :meta="vends.meta"></Paginator>
            </div>
        </div>
        </div>
    </div>
    </BreezeAuthenticatedLayout>
  </template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { MagnifyingGlassIcon, BackspaceIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ref, onMounted } from 'vue';
import { Inertia } from '@inertiajs/inertia';
import { Head } from '@inertiajs/inertia-vue3';

const props = defineProps({
    categories: Object,
    categoryGroups: Object,
    vends: Object,
    vendChannelErrors: Object,
})

const filters = ref({
    code: '',
    serialNum: '',
    customer_code: '',
    customer_name: '',
    categories: [],
    categoryGroups: [],
    tempHigherThan: '',
    vend_channel_error_id: '',
    sortKey: '',
    sortBy: true,
    numberPerPage: '',
})

const vendChannelErrorsOptions = ref([])
const numberPerPageOptions = ref([])
const categoryOptions = ref([])
const categoryGroupOptions = ref([])

onMounted(() => {
    vendChannelErrorsOptions.value = [
        {'id': '', 'desc': 'All'},
        {'id': 'errors_only', 'desc': 'Errors Only'},
        ...props.vendChannelErrors.data
    ]
    numberPerPageOptions.value = [
        { id: 100, value: 100 },
        { id: 200, value: 200 },
        { id: 500, value: 500 },
        { id: 'All', value: 'All' },
    ]
    filters.value.vend_channel_error_id = vendChannelErrorsOptions.value[0]
    filters.value.numberPerPage = numberPerPageOptions.value[0]

    categoryOptions.value = props.categories.data.map((data) => {return {id: data.id, name: data.name}})
    categoryGroupOptions.value = props.categoryGroups.data.map((data) => {return {id: data.id, name: data.name}})
})

function getTotalQty(vend) {
    return vend.vendChannels
            .filter(function(channel) {
                return channel.capacity > 0 && channel.code < 1000
            })
            .reduce(function(total, value) {
                return total + value.qty
            }, 0)
}
function getTotalCapacity(vend) {
    return vend.vendChannels
            .filter(function(channel) {
                return channel.capacity > 0 && channel.code < 1000
            })
            .reduce(function(total, value) {
                return total + value.capacity
            }, 0)
}

function onSearchFilterUpdated() {
    Inertia.get('/vends', {
        ...filters.value,
        vend_channel_error_id: filters.value.vend_channel_error_id.id,
        categories: filters.value.categories.map((category) => { return category.id }),
        categoryGroups: filters.value.categoryGroups.map((categoryGroup) => { return categoryGroup.id }),
        numberPerPage: filters.value.numberPerPage.id,
    }, {
        preserveState: true,
        replace: true,
    })
}

function onVendTempClicked(vendId) {
    Inertia.get('/vends/' + vendId + '/temp')
}

function resetFilters() {
    Inertia.get('/vends')
}

function sortTable(sortKey) {
    filters.value.sortKey = sortKey
    filters.value.sortBy = !this.filters.sortBy
    onSearchFilterUpdated()
}

</script>