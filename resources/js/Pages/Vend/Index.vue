
<template>
    <Head title="Vending Machines" />

    <BreezeAuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Vending Machines (List)
            </h2>
        </template>

        <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
        <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
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
                <div>
                    <label for="text" class="block text-sm font-medium text-gray-700">
                        Is Online?
                    </label>
                    <MultiSelect
                        v-model="filters.is_online"
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
                        Country
                    </label>
                    <MultiSelect
                        v-model="filters.country_id"
                        :options="countryOptions"
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
                                ID
                            </TableHeadSort>
                            <TableHead>
                                Name
                            </TableHead>
                            <TableHead>
                                Category
                            </TableHead>
                            <TableHead>
                                Errors
                            </TableHead>
                            <TableHead>
                                Inventory Status
                            </TableHead>
                            <TableHeadSort modelName="temp" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('temp')">
                                Temp <br>
                                (Chamber)
                            </TableHeadSort>
                            <TableHeadSort modelName="parameter_json->t2" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('parameter_json->t2')">
                                Temp <br>
                                (Evaporator)
                            </TableHeadSort>
                            <TableHeadSort modelName="vend_channel_totals_json->balancePercent" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_channel_totals_json->balancePercent')">
                                Balance Stock
                            </TableHeadSort>
                            <TableHeadSort modelName="vend_channel_totals_json->outOfStockSkuPercent" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_channel_totals_json->outOfStockSkuPercent')">
                                Out of Stock SKU
                            </TableHeadSort>
                            <TableHead>
                                Status
                            </TableHead>
                            <TableHead>
                                Serial Num
                            </TableHead>
                            <TableHeadSort modelName="latestVendBinding.customer.deliveryAddress.postcode" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('latestVendBinding.customer.deliveryAddress.postcode')">
                                Postcode
                            </TableHeadSort>
                            <TableHead>
                                Firmware Ver
                            </TableHead>
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

                                <span v-for="vendChannelErrorLog in vend.vendChannelErrorLogsJson" class="inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border"
                                :class="[vendChannelErrorLog['is_error_cleared'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']">
                                    <div class="flex flex-col">
                                        <div>
                                            #{{vendChannelErrorLog['vend_channel']['code']}},
                                            <span class="font-bold">
                                            ({{ vendChannelErrorLog['vend_channel_error']['code'] }})
                                            </span>
                                        </div>
                                        <div>
                                            {{vendChannelErrorLog['created_at']}}
                                        </div>
                                    </div>
                                </span>
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                <div class="grid grid-cols-[105px_minmax(110px,_1fr)_100px] ">
                                    <span v-for="(channel, channelIndex) in vend.vendChannelsJson"
                                        class="inline-flex justify-evenly items-center rounded px-1 py-0.5 text-xs font-medium border min-w-full"
                                        :class="[channelIndex > 0 && (String(channel['code'])[0] !== String(vend.vendChannelsJson[channelIndex - 1]['code'])[0]) ? 'col-start-1' : '']"
                                    >
                                    <!-- inline-flex justify-between items-center rounded px-2.5 py-0.5 text-xs font-medium border min-w-full -->
                                        <div class="font-semibold">
                                            #{{channel['code']}},
                                        </div>
                                        <div class="text-blue-600 text-sm">
                                            {{channel['capacity'] - channel['qty']}},
                                        </div>
                                        <div class="">
                                            {{channel['qty']}}/{{channel['capacity']}}
                                        </div>
                                    </span>
                                </div>
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                <div class="flex flex-col items-center">
                                    <button
                                        type="button"
                                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center"
                                        :class="[vend.temp > -15 ? 'bg-red-400 active:bg-red-500 hover:bg-red-600' : 'bg-green-400 active:bg-green-500 hover:bg-green-600']"
                                        @click="onVendTempClicked(vend.id, 1)"
                                    >
                                        {{ vend.is_temp_error ? 'Error' : vend.temp }}
                                    </button>
                                    <span class="mt-1">
                                        {{ vend.temp_updated_at }}
                                    </span>
                                </div>
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                <div class="flex flex-col items-center">
                                    <button
                                        type="button"
                                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center"
                                        :class="[vend.temp > -15 || vend.parameterJson['t2'] == constTempError ? 'bg-red-400 active:bg-red-500 hover:bg-red-600' : 'bg-green-400 active:bg-green-500 hover:bg-green-600']"
                                        @click="onVendTempClicked(vend.id, 2)"
                                        v-if="vend.parameterJson && vend.parameterJson['t2']"
                                    >
                                        {{ vend.parameterJson['t2'] == constTempError ? 'Error' : vend.parameterJson['t2']/10 }}
                                    </button>
                                </div>
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                <span v-if="vend.vendChannelTotalsJson">
                                    {{ vend.vendChannelTotalsJson['qty'] }}/ {{ vend.vendChannelTotalsJson['capacity'] }} <br>
                                    ({{ vend.vendChannelTotalsJson['balancePercent'] }}%)
                                </span>
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                <span v-if="vend.vendChannelTotalsJson">
                                    {{ vend.vendChannelTotalsJson['outOfStockSku'] }}/ {{ vend.vendChannelTotalsJson['count'] }} <br>
                                    ({{ vend.vendChannelTotalsJson['outOfStockSkuPercent'] }}%)
                                </span>
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                <div class="grid grid-cols-[90px_minmax(90px,_1fr)_90px] gap-1">
                                    <div
                                        class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                                        :class="[vend.is_online ? 'bg-green-200' : 'bg-red-200']"
                                    >
                                        <div class="flex flex-col">
                                            <span class="font-bold">
                                                {{vend.is_online ? 'Online' : 'Offline'}}
                                            </span>
                                            <span v-if="vend.last_updated_at">
                                                {{vend.last_updated_at}}
                                            </span>
                                        </div>

                                    </div>
                                    <div
                                        class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                                        :class="[vend.parameterJson['Sensor'] == 1 ? 'bg-green-200' : 'bg-red-200']"
                                        v-if="vend.parameterJson && vend.parameterJson['Sensor']"
                                    >
                                        <div class="flex flex-col">
                                            <span class="font-bold">
                                                Drop Sensor
                                            </span>
                                            <span>
                                                {{vend.parameterJson['Sensor'] == 1 ? 'Active' : 'Inactive'}}
                                            </span>
                                        </div>
                                    </div>
                                    <div
                                        class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full bg-green-200"
                                        v-if="vend.parameterJson && vend.parameterJson['fan']"
                                    >
                                        <div class="flex flex-col">
                                            <span class="font-bold">
                                                Fan Speed
                                            </span>
                                            <span>
                                                {{vend.parameterJson['fan']}}
                                            </span>
                                        </div>
                                    </div>
                                    <div
                                        class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                                        :class="[vend.parameterJson['door'] == 'close' ? 'bg-green-200' : 'bg-red-200']"
                                        v-if="vend.parameterJson && vend.parameterJson['door']"
                                    >
                                        <div class="flex flex-col">
                                            <span class="font-bold">
                                                Door
                                            </span>
                                            <span>
                                                {{vend.parameterJson['door'] == 'open' ? 'Open' : 'Close'}}
                                            </span>
                                        </div>
                                    </div>
                                    <div
                                        class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                                        :class="[vend.parameterJson['CoinCnt'] > 1600 ? 'bg-green-200' : 'bg-red-200']"
                                        v-if="vend.parameterJson && vend.parameterJson['CoinCnt']"
                                    >
                                        <div class="flex flex-col">
                                            <span class="font-bold">
                                                Coin
                                            </span>
                                            <span>
                                                {{(vend.parameterJson['CoinCnt']/ 100).toFixed(2)}}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                {{ vend.serial_num }}
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                <span v-if="vend.latestVendBinding && vend.latestVendBinding.customer && vend.latestVendBinding.customer.deliveryAddress">
                                    {{ vend.postcode }}
                                </span>
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                {{ vend.firmware_ver }}
                            </TableData>
                        </tr>
                        <tr v-if="!vends.data.length">
                            <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                                No Results Found
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Paginator v-if="vends.data.length" :links="vends.links" :meta="vends.meta"></Paginator>
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
    constTempError: Number,
    countries: Object,
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
    country_id: '',
    tempHigherThan: '',
    vend_channel_error_id: '',
    is_online: '',
    sortKey: '',
    sortBy: true,
    numberPerPage: '',
  })

  const vendChannelErrorsOptions = ref([])
  const numberPerPageOptions = ref([])
  const categoryOptions = ref([])
  const categoryGroupOptions = ref([])
  const countryOptions = ref([])
  const booleanOptions = ref([])

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
    booleanOptions.value = [
        {id: 'all', value: 'All'},
        {id: 'true', value: 'Yes'},
        {id: 'false', value: 'No'},
    ]
    filters.value.is_online = booleanOptions.value[1]
    countryOptions.value = [
        {'id': '0', 'name': 'All'},
        ...props.countries.data.map((data) => {return {id: data.id, name: data.name}})
    ];
    filters.value.country_id = countryOptions.value[1]
  })

  function onSearchFilterUpdated() {
    Inertia.get('/vends', {
        ...filters.value,
        vend_channel_error_id: filters.value.vend_channel_error_id.id,
        categories: filters.value.categories.map((category) => { return category.id }),
        categoryGroups: filters.value.categoryGroups.map((categoryGroup) => { return categoryGroup.id }),
        country_id: filters.value.country_id.id,
        is_online: filters.value.is_online.id,
        numberPerPage: filters.value.numberPerPage.id,
    }, {
        preserveState: true,
        replace: true,
    })
  }

  function onVendTempClicked(vendId, type) {
    Inertia.get('/vends/' + vendId + '/temp/' + type)
  }

  function resetFilters() {
    Inertia.get('/vends')
  }

  function sortTable(sortKey) {
    filters.value.sortKey = sortKey
    filters.value.sortBy = !filters.value.sortBy
    onSearchFilterUpdated()
  }

  </script>