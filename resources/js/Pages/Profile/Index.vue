
<template>

    <Head title="Profile" />

    <BreezeAuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Profiles
            </h2>
        </template>

        <div class="m-2 sm:mx-5 sm:my-3 px-2 sm:px-4 lg:px-6">
        <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-5 px-3 md:px-6 py-6 ">
            <!-- <div class="flex flex-col md:flex-row md:space-x-3 space-y-1 md:space-y-0"> -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
                <SearchInput placeholderStr="Name" v-model="filters.name" @input="onSearchFilterUpdated()">
                    Name
                </SearchInput>
                <SearchInput placeholderStr="UEN" v-model="filters.uen" @input="onSearchFilterUpdated()">
                    UEN
                </SearchInput>
            </div>

            <div class="flex justify-end mt-5">
                <div class="flex flex-col space-y-2">
                    <p class="text-sm text-gray-700 leading-5 flex space-x-1">
                        <span>Showing</span>
                        <span class="font-medium">{{ profiles.meta.from }}</span>
                        <span>to</span>
                        <span class="font-medium">{{ profiles.meta.to }}</span>
                        <span>of</span>
                        <span class="font-medium">{{ profiles.meta.total }}</span>
                        <span>results</span>
                    </p>
                    <div class="flex justify-end">
                        <MultiSelect
                            v-model="filters.numberPerPage"
                            :options="[
                                { id: 100, value: 100 },
                                { id: 200, value: 200 },
                                { id: 500, value: 500 },
                                { id: 'All', value: 'All' },
                            ]"
                            :custom-label="getNumberPerPageLabel"
                            :close-on-select="true"
                            :clear-on-select="false"
                            placeholder="Select"
                            track-by="id"
                            open-direction="bottom"
                            @on-selected="onNumberPerPageSelected"
                        >
                        </MultiSelect>
                    </div>
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
                                #</th>
                            <th
                                scope="col"
                                class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                                <div class="flex justify-center">
                                    <a href="#" class="text-blue-600 hover:text-blue-800" @click="sortTable('code')">
                                        Code
                                    </a>
                                    <div class="pt-0.5 pl-0.5 text-blue-600 hover:text-blue-800">
                                        <span v-if="filters.sortKey === 'code' && filters.sortBy">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </span>
                                        <span v-if="filters.sortKey === 'code' && !filters.sortBy">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                                            </svg>
                                        </span>
                                    </div>
                                </div>
                            </th>
                            <th scope="col"
                                class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                                <div class="flex justify-center">
                                    <a href="#" class="text-blue-600 hover:text-blue-800" @click="sortTable('temp')">
                                        Temp(C)
                                    </a>
                                    <div class="pt-0.5 pl-0.5 text-blue-600 hover:text-blue-800">
                                        <span v-if="filters.sortKey === 'temp' && filters.sortBy">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </span>
                                        <span v-if="filters.sortKey === 'temp' && !filters.sortBy">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                                            </svg>
                                        </span>
                                    </div>
                                </div>
                            </th>

                            <th scope="col"
                                class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                                Name</th>
                            <th scope="col"
                                class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                                Channels Status</th>
                            <th scope="col"
                                class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                                Inventory Status</th>
                            <th scope="col"
                                class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                                <div class="flex justify-center">
                                    <a href="#" class="text-blue-600 hover:text-blue-800" @click="sortTable('temp_updated_at')">
                                        Last Temp
                                    </a>
                                    <div class="pt-0.5 pl-0.5 text-blue-600 hover:text-blue-800">
                                        <span v-if="filters.sortKey === 'temp_updated_at' && filters.sortBy">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </span>
                                        <span v-if="filters.sortKey === 'temp_updated_at' && !filters.sortBy">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                                            </svg>
                                        </span>
                                    </div>
                                </div>
                            </th>
                            <th scope="col"
                                class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                                <div class="flex justify-center">
                                    <a href="#" class="text-blue-600 hover:text-blue-800" @click="sortTable('coin_amount')">
                                        Coin Amount
                                    </a>
                                    <div class="pt-0.5 pl-0.5 text-blue-600 hover:text-blue-800">
                                        <span v-if="filters.sortKey === 'coin_amount' && filters.sortBy">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                                            </svg>
                                        </span>
                                        <span v-if="filters.sortKey === 'coin_amount' && !filters.sortBy">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                                            </svg>
                                        </span>
                                    </div>
                                </div>
                            </th>
                            <th scope="col"
                                class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                                Serial Num</th>
                            <th scope="col"
                                class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                                Firmware Ver</th>
                            <th scope="col"
                                class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 px-3 py-3.5 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter">
                                Door Opening?</th>
                            <th scope="col"
                                class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 px-3 py-3.5 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter">
                                Sensor Normal?</th>
                            <th scope="col"
                                class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pr-4 pl-3 backdrop-blur backdrop-filter sm:pr-6 lg:pr-8">
                                <span class="sr-only">Edit</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
                        <tr v-for="(vend, vendIndex) in vends.data" :key="vend.id"
                            class="divide-x divide-gray-200">
                            <td :class="[vendIndex !== vends.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-600 sm:pl-6 lg:pl-8']"
                                class="text-right">
                                {{ vends.meta.from + vendIndex }}
                            </td>
                            <td :class="[vendIndex !== vends.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-800 sm:pl-6 lg:pl-8']"
                                class="text-right">
                                {{ vend.code }}
                            </td>
                            <td :class="[vendIndex !== vends.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-blue-600 sm:pl-6 lg:pl-8']"
                                class="text-right">
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
                            </td>
                            <td :class="[vendIndex !== vends.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-800 sm:pl-6 lg:pl-8']"
                                class="text-center">
                                {{ vend.name }}</td>
                            <td :class="[vendIndex !== vends.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-normal py-2 pl-2 pr-1 text-sm font-medium text-gray-800 sm:pl-1 lg:pl-2']"
                                class="text-center">
                                <span v-for="
                                            channel in vend.vend_channels
                                            .map(function(channel){
                                                return channel
                                            })
                                            .filter(function(channel) {
                                                return (channel.vend_channel_error_logs.length) ?? channel.vend_channel_error_logs
                                            })"
                                    class="flex flex-col space-y-1"
                                >
                                    <span v-for="error in channel.vend_channel_error_logs.filter(function(error) {
                                        return !error.is_error_cleared
                                    })" class="inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border" :class="[error.is_error_cleared ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']">
                                        #{{channel.code}}, {{ error.vend_channel_error.desc }}
                                    </span>
                                </span>
                            </td>
                            <td :class="[vendIndex !== vends.length - 1 ? 'border-b border-gray-200' : '', 'py-1 pl-1 pr-1 text-sm font-medium text-gray-800']"
                                class="text-center">
                                <div class="grid grid-cols-[100px_minmax(100px,_1fr)_100px] gap-1">
                                    <div v-for="
                                                channel in vend.vend_channels
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
                            </td>
                            <td :class="[vendIndex !== vends.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-600 sm:pl-6 lg:pl-8']"
                                class="text-center">
                                {{ vend.temp_updated_at }}</td>
                            <td :class="[vendIndex !== vends.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-600 sm:pl-6 lg:pl-8']"
                                class="text-right">
                                {{ vend.coin_amount }}</td>
                            <td :class="[vendIndex !== vends.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-800 sm:pl-6 lg:pl-8']"
                                class="text-center">
                                {{ vend.serial_num }}</td>
                            <td :class="[vendIndex !== vends.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-600 sm:pl-6 lg:pl-8']"
                                class="text-center">
                                {{ vend.firmware_ver }}</td>
                            <td :class="[vendIndex !== vends.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-600 sm:pl-6 lg:pl-8']"
                                class="text-center">
                                {{ vend.is_door_open }}</td>
                            <td :class="[vendIndex !== vends.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-600 sm:pl-6 lg:pl-8']"
                                class="text-center">
                                {{ vend.is_sensor_normal }}</td>
                            <td
                                :class="[vendIndex !== vends.length - 1 ? 'border-b border-gray-200' : '', 'relative whitespace-nowrap py-4 pr-4 pl-3 text-right text-sm font-medium sm:pr-6 lg:pr-8']">
                                <a href="#" class="text-indigo-600 hover:text-indigo-900">Edit<span
                                        class="sr-only">, {{ vend.name }}</span></a>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <Paginator :links="vends.links" :meta="vends.meta"></Paginator>
            </div>
        </div>
        </div>
    </div>
    </BreezeAuthenticatedLayout>
  </template>

  <script>
  import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
  import Button from '@/Components/Button.vue';
  import OptionDropdown from '@/Components/OptionDropdown.vue';
  import Paginator from '@/Components/Paginator.vue';
  import SearchInput from '@/Components/SearchInput.vue';
  import { debounce } from 'lodash';
    import MultiSelect from '@/Components/MultiSelect.vue';

  export default {
    components: {
    BreezeAuthenticatedLayout,
    Button,
    MultiSelect,
    OptionDropdown,
    Paginator,
    SearchInput,
},
    props: {
        vends: Object,
        vendChannelErrors: Object,
        // filters: Object,
    },
    created() {
        this.vendChannelErrorsOptions = [
            {'id': '', 'desc': 'All'},
            {'id': 'errors_only', 'desc': 'Errors Only'},
            ...this.vendChannelErrors.data
        ]
        // this.filters.hasError = Object.values(this.vendChannelErrorsOptions)[0]
    },
    data() {
        return {
            filters: this.getFiltersDefault(),
            vendChannelErrorsOptions: [],
        }
    },
    methods: {
        getFiltersDefault() {
            return {
                code: '',
                serialNum: '',
                name: '',
                tempHigherThan: '',
                hasError: '',
                sortKey: '',
                sortBy: true,
                numberPerPage: 100,
            }
        },
        getHasErrorFiltersLabel(option) {
            return `${option.desc}`
        },
        getNumberPerPageLabel({ id, value }) {
            if(value !== 'All') {
                return `${value}` + ' results (page)'
            }else {
                return `${value}`
            }
        },
        getTotalQty(vend) {
            return vend.vend_channels
                    .filter(function(channel) {
                        return channel.capacity > 0 && channel.code < 1000
                    })
                    .reduce(function(total, value) {
                        return total + value.qty
                    }, 0)
        },
        getTotalCapacity(vend) {
            return vend.vend_channels
                    .filter(function(channel) {
                        return channel.capacity > 0 && channel.code < 1000
                    })
                    .reduce(function(total, value) {
                        return total + value.capacity
                    }, 0)
        },
        onNumberPerPageSelected(option) {
            this.filters.numberPerPage = option.value
            this.onSearchFilterUpdated()
        },
        onHasErrorSelected(option) {
            this.filters.hasError = option
            this.onSearchFilterUpdated()
        },
        onSearchFilterUpdated: debounce(function() {
            // console.log(JSON.parse(JSON.stringify(this.filters)))
            this.$inertia.get('/vends', this.filters, {
                preserveState: true,
                replace: true,
            })
        }, 500),

        onVendTempClicked(vendId) {
            this.$inertia.get('/vend/' + vendId + '/temp')
        },
        onVendChannelErrorLogEmailClicked() {
            this.$inertia.get('/vends/channel-error-logs-email')
        },
        resetFilters() {
            this.filters = this.getFiltersDefault()
            console.log(Object.values(this.vendChannelErrorsOptions)[0])
            this.filters.hasError = Object.values(this.vendChannelErrorsOptions)[0]
            this.onSearchFilterUpdated()
        },
        sortTable(sortKey) {
            this.filters.sortKey = sortKey
            this.filters.sortBy = !this.filters.sortBy
            this.onSearchFilterUpdated()
        },
    },
  }
  </script>