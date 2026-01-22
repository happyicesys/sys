<template>

    <Head title="Dashboard" />

    <BreezeAuthenticatedLayout>
        <template #header>
            <div class="flex flex-col space-y-1">
                <div class="flex space-x-2 items-center">
                    Performance Dashboard
                </div>
            </div>
        </template>

        <div class="p-3">
            <div class="max-w-7xl mx-auto sm:px-3 lg:px-2">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-4">
                        <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-gray-200 px-4 py-3 md:px-4 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        @click="showFilters = true"
                        v-if="!showFilters && permissions.includes('admin-access dashboard')"
                        >
                            <ChevronDoubleDownIcon class="h-4 w-4" aria-hidden="true"/>
                            <span>
                                Show Filters
                            </span>
                        </Button>
                    </div>
                    <div class="p-4 mx-2" v-if="showFilters">
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
                        <SearchInput placeholderStr="Machine ID" v-model="filters.codes" @keyup.enter="onSearchFilterUpdated()">
                            Machine ID
                            <span class="text-[9px]">
                                ("," for multiple)
                            </span>
                        </SearchInput>
                        <SearchInput placeholderStr="Customer" v-model="filters.customer" @keyup.enter="onSearchFilterUpdated()">
                            Customer
                        </SearchInput>
                        <div>
                            <label for="text" class="block text-sm font-medium text-gray-700">
                                Machine Model
                            </label>
                            <MultiSelect
                                v-model="filters.vendModels"
                                :options="vendModelOptions"
                                trackBy="id"
                                valueProp="id"
                                label="value"
                                placeholder="Select"
                                open-direction="bottom"
                                mode="tags"
                                class="mt-1"
                            >
                            </MultiSelect>
                        </div>
                        <div>
                            <label for="text" class="block text-sm font-medium text-gray-700">
                                Machine Prefix
                            </label>
                            <MultiSelect
                                v-model="filters.vendPrefixes"
                                :options="vendPrefixOptions"
                                trackBy="id"
                                valueProp="id"
                                label="value"
                                placeholder="Select"
                                open-direction="bottom"
                                mode="tags"
                                class="mt-1"
                            >
                            </MultiSelect>
                        </div>
                        <div v-if="permissions.includes('admin-access dashboard')">
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

                        <div class="border rounded-md p-4 bg-gray-50">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-medium text-gray-900">Historical Analysis</h3>
                                <div class="flex space-x-2 items-center">
                                    <span class="text-gray-700 font-medium">Chosen Month</span>
                                    <select v-model="filters.monthYear" class="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <option v-for="option in monthYearOptions" :key="option.value" :value="option.value">
                                            {{ option.label }}
                                        </option>
                                    </select>
                                    <Button class="inline-flex items-center rounded-md border border-transparent bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                        @click="onSearchFilterUpdated()"
                                    >
                                        Apply
                                    </Button>
                                </div>
                            </div>

                            <h4 class="text-gray-900 font-medium mb-2">Sales Comparison (Current vs Last Year)</h4>
                            <Graph
                                :key="componentKey5"
                                type="bar"
                                :labels="salesComparisonGraphLabels"
                                :datasets="salesComparisonGraphDatasets"
                                :options="salesComparisonGraphOptions"
                            >
                            </Graph>

                            <h4 class="text-gray-900 font-medium mb-2 mt-5">Chosen month vs last month</h4>
                            <Graph
                                :key="componentKey1"
                                type="scatter"
                                :labels="dayGraphLabels"
                                :datasets="dayGraphDatasets"
                                :options="dayGraphOptions"
                            >
                            </Graph>

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

                            <div class="pt-5 my-1 mx-4 px-4">
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
                                                            {{ month.short_name }} {{ moment(filters.monthYear).format('YY') }}
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
                                                                {{ data.average.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
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
                                                                        data.current ? 'font-bold' : 'font-medium'
                                                                ]"
                                                            >
                                                                {{ data.vend_count.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
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
                                                                        data.current ? 'font-bold' : 'font-medium'
                                                                ]"
                                                            >
                                                                {{ data.amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                                                            </span>
                                                        </span>
                                                    </td>
                                                </tr>
                                                <tr v-if="!bestPerformerGraphData.data.length">
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

                        <div class="border rounded-md p-4 bg-blue-50 mt-6">
                            <div class="mb-4">
                                <h3 class="text-lg font-medium text-gray-900">Current Stat</h3>
                            </div>
                        <div class="pt-5 flex justify-center">
                            <div class="w-full md:w-2/3 lg:w-1/2">
                                <Graph
                                    :key="componentKey2"
                                    type="pie"
                                    :labels="productGraphLabels"
                                    :datasets="productGraphDatasets"
                                    :options="productGraphOptions"
                                ></Graph>
                            </div>
                        </div>
                        <div class="flex flex-col lg:flex-row pt-5 gap-4">
                            <div class="w-full lg:w-1/2 my-1 px-3 lg:px-2">
                                <p class="text-sm flex justify-between">
                                    <div>
                                        Past 30 Days - Top {{ performerLimit }} Best Performance
                                    </div>
                                    <div>
                                        Based on {{ vendCount }} active machine(s)
                                    </div>
                                </p>
                                <div class="mt-2 flow-root">
                                    <div class="-mx-2 -my-2 overflow-x-auto sm:-mx-4 lg:-mx-6">
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
                                                        Amount({{ operatorCountry.currency_symbol }})
                                                    </th>
                                                    <th scope="col" class="px-3 py-2 text-left text-sm font-semibold text-gray-900">
                                                        Sales(#)
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200 bg-white">
                                                <tr v-for="(vend, vendIndex) in bestPerformerGraphData.data" :key="vend.id">
                                                    <td class="whitespace-nowrap py-1 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">
                                                        {{ vendIndex + 1 }}
                                                    </td>
                                                    <td class="px-3 py-1 text-sm text-gray-600 align-top">
                                                        <div class="max-w-[220px] break-words">
                                                            <span class="block font-medium text-gray-700">
                                                                {{ [vend.vend?.code, vend.vend?.name].filter(Boolean).join(' - ') }}
                                                            </span>
                                                            <span class="block text-xs text-gray-500" v-if="vend.vend?.vendPrefix?.name">
                                                                {{ vend.vend.vendPrefix.name }}
                                                            </span>
                                                            <span v-if="(vend.customer || vend.vend?.customer) && (vend.customer || vend.vend?.customer).person_id" class="block text-gray-500">
                                                                {{ (vend.customer || vend.vend?.customer).virtual_customer_code }}
                                                                <br>
                                                                {{ (vend.customer || vend.vend?.customer).name }}
                                                            </span>
                                                            <span v-else class="block text-gray-500">
                                                                {{ (vend.customer || vend.vend?.customer) ? (vend.customer || vend.vend?.customer).name : '' }}
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td class="whitespace-nowrap px-3 py-1 text-sm text-gray-500 text-right mx-3">
                                                        {{ vend.amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                                                    </td>
                                                    <td class="whitespace-nowrap px-3 py-1 text-sm text-gray-500 text-right mx-3">
                                                        {{ vend.count }}
                                                    </td>
                                                </tr>
                                                <tr v-if="!bestPerformerGraphData.data.length">
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
                                <div class="mt-3 flex justify-center" v-if="performerLimit < 50 && bestPerformerGraphData.data && bestPerformerGraphData.data.length">
                                    <Button
                                        class="inline-flex items-center rounded-md border border-green bg-gray-200 px-4 py-2 text-sm font-medium text-gray-800 shadow-sm hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                        @click="loadMoreBestPerformers"
                                        :disabled="performerLoading"
                                    >
                                        <span v-if="performerLoading">
                                            Loading...
                                        </span>
                                        <span v-else>
                                            Load Top 50
                                        </span>
                                    </Button>
                                </div>
                            </div>
                            <div class="w-full lg:w-1/2 my-1 px-3 lg:px-2">
                                <p class="text-sm flex justify-between">
                                    <div>
                                        Past 30 Days - Top {{ worstPerformerLimit }} Worst Performance
                                    </div>
                                    <div>
                                        Based on {{ vendCount }} active machine(s)
                                    </div>
                                </p>
                                <div class="mt-2 flow-root">
                                    <div class="-mx-2 -my-2 overflow-x-auto sm:-mx-4 lg:-mx-6">
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
                                                        Amount({{ operatorCountry.currency_symbol }})
                                                    </th>
                                                    <th scope="col" class="px-3 py-2 text-left text-sm font-semibold text-gray-900">
                                                        Sales(#)
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200 bg-white">
                                                <tr v-for="(vend, vendIndex) in worstPerformerGraphData.data" :key="vend.id">
                                                    <td class="whitespace-nowrap py-1 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">
                                                        {{ vendIndex + 1 }}
                                                    </td>
                                                    <td class="px-3 py-1 text-sm text-gray-600 align-top">
                                                        <div class="max-w-[220px] break-words">
                                                            <span class="block font-medium text-gray-700">
                                                                {{ [vend.vend?.code, vend.vend?.name].filter(Boolean).join(' - ') }}
                                                            </span>
                                                            <span class="block text-xs text-gray-500" v-if="vend.vend?.vendPrefix?.name">
                                                                {{ vend.vend.vendPrefix.name }}
                                                            </span>
                                                            <span v-if="(vend.customer || vend.vend?.customer) && (vend.customer || vend.vend?.customer).person_id" class="block text-gray-500">
                                                                {{ (vend.customer || vend.vend?.customer).virtual_customer_code }}
                                                                <br>
                                                                {{ (vend.customer || vend.vend?.customer).name }}
                                                            </span>
                                                            <span v-else class="block text-gray-500">
                                                                {{ (vend.customer || vend.vend?.customer) ? (vend.customer || vend.vend?.customer).name : '' }}
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td class="whitespace-nowrap px-3 py-1 text-sm text-gray-500 text-right mx-3">
                                                        {{ vend.amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                                                    </td>
                                                    <td class="whitespace-nowrap px-3 py-1 text-sm text-gray-500 text-right mx-3">
                                                        {{ vend.count }}
                                                    </td>
                                                </tr>
                                                <tr v-if="!worstPerformerGraphData.data.length">
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
                                <div class="mt-3 flex justify-center" v-if="worstPerformerLimit < 50 && worstPerformerGraphData.data && worstPerformerGraphData.data.length">
                                    <Button
                                        class="inline-flex items-center rounded-md border border-green bg-gray-200 px-4 py-2 text-sm font-medium text-gray-800 shadow-sm hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                                        @click="loadMoreWorstPerformers"
                                        :disabled="worstPerformerLoading"
                                    >
                                        <span v-if="worstPerformerLoading">
                                            Loading...
                                        </span>
                                        <span v-else>
                                            Load Bottom 50
                                        </span>
                                    </Button>
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
    import { ref, onBeforeMount, watch, onMounted } from 'vue';
    import { Head, Link, router, usePage } from '@inertiajs/vue3';
    import moment from 'moment';

    const props = defineProps({
        activeMachineGraphData: Object,
        dayGraphData: Object,
        locationTypeOptions: Object,
        monthGraphData: Object,
        months: Object,
        monthsByModel: Object,
        operatorOptions: Object,
        productGraphData: Object,
        performerGraphData: Object,
        performerLimit: Number,
        worstPerformerGraphData: Object,
        worstPerformerLimit: Number,
        vendCount: Number,
        vendModelOptions: Object,
        vendPrefixOptions: Object,
        salesComparisonGraphData: Object,
    });
    const filters = ref({
        codes: '',
        customer: '',
        day_date_from: '',
        day_date_to: '',
        locationType: '',
        operators: [],
        monthlyTypeName: 'location-type',
        vendModels: [],
        vendPrefixes: [],
        monthYear: moment().format('YYYY-MM'),
    })
    const monthYearOptions = ref([]);
    const performerLimit = ref(props.performerLimit ?? 20);
    const performerLoading = ref(false);
    const worstPerformerLimit = ref(props.worstPerformerLimit ?? 20);
    const worstPerformerLoading = ref(false);
    const authOperator = usePage().props.auth.operator
    const componentKey1 = ref(0);
    const componentKey2 = ref(0);
    const componentKey3 = ref(0);
    const componentKey4 = ref(0);
    const componentKey5 = ref(0);
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
    const forceRerender5 = () => {
        componentKey5.value += 1;
    };
    const locationTypeOptions = ref([])
    const operator = usePage().props.auth.operator
    const operatorCountry = usePage().props.auth.operatorCountry
    const operatorOptions = ref([])
    const permissions = usePage().props.auth.permissions
    const showFilters = ref(false)
    const tabs = ref([
        { name: 'Location Type', slug: 'location-type', current: true, href: '#' },
        { name: 'Operator', slug: 'operator', current: false, href: '#' },
    ])

    const dayGraphData = ref([]);
    const dayGraphDatasets = ref([])
    const dayGraphLabels = ref([])
    const getOrCreateTooltip = (chart) => {
        let tooltipEl = chart.canvas.parentNode.querySelector('div.chartjs-tooltip');

        if (!tooltipEl) {
            tooltipEl = document.createElement('div');
            tooltipEl.classList.add('chartjs-tooltip');
            tooltipEl.style.background = 'rgba(0, 0, 0, 0.7)';
            tooltipEl.style.borderRadius = '3px';
            tooltipEl.style.color = 'white';
            tooltipEl.style.opacity = 1;
            tooltipEl.style.pointerEvents = 'none';
            tooltipEl.style.position = 'absolute';
            tooltipEl.style.transform = 'translate(-50%, 0)';
            tooltipEl.style.transition = 'all .1s ease';
            tooltipEl.style.zIndex = 100;

            const table = document.createElement('table');
            table.style.margin = '0px';

            tooltipEl.appendChild(table);
            chart.canvas.parentNode.appendChild(tooltipEl);
        }

        return tooltipEl;
    };

    const externalTooltipHandler = (context) => {
        // Tooltip Element
        const { chart, tooltip } = context;
        // Check if tooltip element needs to be created
        // We use a safe check to avoid multiple tooltips if graph re-renders
        const tooltipEl = getOrCreateTooltip(chart);

        // Hide if no tooltip
        if (tooltip.opacity === 0) {
            tooltipEl.style.opacity = 0;
            return;
        }

        // Set Text
        if (tooltip.body) {
            const titleLines = tooltip.title || [];
            const bodyLines = tooltip.body.map(b => b.lines);

            const tableHead = document.createElement('thead');

            titleLines.forEach(title => {
                const tr = document.createElement('tr');
                tr.style.borderWidth = 0;

                const th = document.createElement('th');
                th.style.borderWidth = 0;
                const text = document.createTextNode(title);

                th.appendChild(text);
                tr.appendChild(th);
                tableHead.appendChild(tr);
            });

            const tableBody = document.createElement('tbody');
            bodyLines.forEach((body, i) => {
                const colors = tooltip.labelColors[i];

                const span = document.createElement('span');
                span.style.background = colors.backgroundColor;
                span.style.borderColor = colors.borderColor;
                span.style.borderWidth = '2px';
                span.style.marginRight = '10px';
                span.style.height = '10px';
                span.style.width = '10px';
                span.style.display = 'inline-block';

                const tr = document.createElement('tr');
                tr.style.backgroundColor = 'inherit';
                tr.style.borderWidth = 0;

                const td = document.createElement('td');
                td.style.borderWidth = 0;

                const text = document.createTextNode(body);

                td.appendChild(span);
                td.appendChild(text);
                tr.appendChild(td);
                tableBody.appendChild(tr);
            });

            // Add Weather Icon
            if (tooltip.dataPoints.length > 0) {
                const dataPoint = tooltip.dataPoints[0];
                const dataset = chart.data.datasets[dataPoint.datasetIndex];

                if (dataset.weather_icons && dataset.weather_icons[dataPoint.dataIndex]) {
                    const iconCode = dataset.weather_icons[dataPoint.dataIndex];
                    const tr = document.createElement('tr');
                    const td = document.createElement('td');
                    td.style.textAlign = 'center';
                    td.style.paddingTop = '5px';
                    td.colSpan = 2; // Span full width

                    const img = document.createElement('img');
                    img.src = `https://openweathermap.org/img/wn/${iconCode}@2x.png`;
                    img.style.width = '50px';
                    img.style.height = '50px';
                    img.style.display = 'inline-block';

                    // Add blue halo for Rain to distinguish from Cloudy
                    if (iconCode === '09d') {
                        img.style.backgroundColor = 'rgba(60, 150, 255, 0.3)';
                        img.style.borderRadius = '50%';
                        img.style.boxShadow = '0 0 8px rgba(60, 150, 255, 0.6)';
                    }

                    td.appendChild(img);
                    tr.appendChild(td);
                    tableBody.appendChild(tr);
                }
            }

            const tableRoot = tooltipEl.querySelector('table');

            // Remove old children
            while (tableRoot.firstChild) {
                tableRoot.firstChild.remove();
            }

            // Add new children
            tableRoot.appendChild(tableHead);
            tableRoot.appendChild(tableBody);
        }

        const { offsetLeft: positionX, offsetTop: positionY } = chart.canvas;

        // Display, position, and set styles for font
        tooltipEl.style.opacity = 1;
        tooltipEl.style.left = positionX + tooltip.caretX + 'px';
        tooltipEl.style.top = positionY + tooltip.caretY + 'px';
        tooltipEl.style.font = tooltip.options.bodyFont.string;
        tooltipEl.style.padding = tooltip.options.padding + 'px ' + tooltip.options.padding + 'px';
    };

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
                    text: 'Sales(' + operatorCountry.currency_symbol +')'
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
                reverse: false,
                labels: {
                    padding: 20,
                    sort: (a, b) => a.datasetIndex - b.datasetIndex
                }
            },
            tooltip: {
                enabled: false,
                external: externalTooltipHandler
            }
        }
    })

    const salesComparisonGraphData = ref([]);
    const salesComparisonGraphDatasets = ref([])
    const salesComparisonGraphLabels = ref([])
    const salesComparisonGraphOptions = ref({
        scales: {
            x: {
                ticks: {
                    autoSkip: false,
                    maxRotation: 0,
                    callback: function(val, index) {
                        const label = this.getLabelForValue(val);
                        // Always show labels that are arrays (Month Year labels)
                        if (Array.isArray(label)) {
                            return label;
                        }
                        // For day numbers, show sparsely to avoid crowding
                        // Show 1st, and every 5th day (1, 5, 10, 15, 20, 25, 30)
                        if (label == 1 || label % 5 === 0) {
                            return label;
                        }
                        return null;
                    }
                }
            },
            y: {
                position: 'left',
                title: {
                    display: true,
                    text: 'Sales(' + operatorCountry.currency_symbol +')'
                },
                beginAtZero: true
            },
        },
        plugins: {
            title: {
                display: true,
                text: 'Sales Comparison'
            },
            legend: {
                reverse: false,
                labels: {
                    padding: 20
                }
            },
            tooltip: {
                enabled: false,
                external: externalTooltipHandler
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
                    text: 'Sales(' + operatorCountry.currency_symbol +')'
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
                reverse: false,
                labels: {
                    padding: 20,
                    sort: (a, b) => a.datasetIndex - b.datasetIndex
                }
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
            tooltip: {
                callbacks: {
                    label: function(tooltipItem, chartData) {
                        // console.log(tooltipItem, JSON.parse(JSON.stringify(productGraphDatasets.value)))
                        const label = tooltipItem.label;
                        const value = tooltipItem.parsed;
                        const total = productGraphDatasets.value[0].data.reduce((acc, val) => acc + val, 0);
                        const percentage = Math.round((value / total) * 100);
                        return `${label}: ${value} (${percentage}%)`;
                    }
                }
            }
        },
    })

    const bestPerformerGraphData = ref([])
    const worstPerformerGraphData = ref([])

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
            legend: {
                labels: {
                    padding: 20
                }
            }
        }
    })
    const vendModelOptions = ref([])
    const vendPrefixOptions = ref([])


    onBeforeMount(() => {
        locationTypeOptions.value = [
            {id: 'all', name: 'All'},
            ...props.locationTypeOptions.data.map((data) => {return {id: data.id, name: data.name}})
        ]
        operatorOptions.value = [
            {id: 'all', full_name: 'All'},
            ...props.operatorOptions.data.map((data) => {return {id: data.id, code:data.code, full_name: data.full_name}})
        ]
        vendModelOptions.value = [
            {id: 'all', value: 'All'},
            ...props.vendModelOptions.data.map((data) => {return {id: data.id, value: data.name}})
        ]
        vendPrefixOptions.value = [
            {id: 'single-ud', value: 'Single UD'},
			...props.vendPrefixOptions.data.map((data) => {return {id: data.id, value: data.name}})
	    ]
        generateMonthYearOptions();
        syncDashboardData()
    })

    onMounted(() => {
        filters.value.locationType = locationTypeOptions.value[0]
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

    function generateMonthYearOptions() {
        const options = [];
        const currentDate = moment();
        const endDate = moment().subtract(3, 'years');

        while (currentDate.isAfter(endDate)) {
            options.push({
                value: currentDate.format('YYYY-MM'),
                label: currentDate.format('MMMM YYYY')
            });
            currentDate.subtract(1, 'month');
        }
        monthYearOptions.value = options;
    }

    function hexToRGBA(hex, alpha) {
        var r = parseInt(hex.slice(1, 3), 16);
        var g = parseInt(hex.slice(3, 5), 16);
        var b = parseInt(hex.slice(5, 7), 16);

        return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
    }

    function buildDashboardQueryParams(overrides = {}) {
        const locationType = filters.value.locationType;
        return {
            ...filters.value,
            ...overrides,
            locationType: locationType && locationType.id ? locationType.id : '',
            location_type_id: locationType && locationType.id ? locationType.id : '',
            operators: filters.value.operators.map((operator) => { return operator.id }),
            vendModels: filters.value.vendModels.map((vendModel) => { return vendModel.id }),
            vendPrefixes: filters.value.vendPrefixes.map((vendPrefix) => { return vendPrefix.id }),
            performer_limit: performerLimit.value,
            best_performer_limit: performerLimit.value,
            worst_performer_limit: worstPerformerLimit.value,
            month_year: filters.value.monthYear,
        };
    }

    function onSearchFilterUpdated() {
        router.visit(
            route('dashboard', buildDashboardQueryParams()),{
                only: ['activeMachineGraphData', 'dayGraphData', 'monthGraphData', 'monthsByModel', 'productGraphData', 'performerGraphData', 'performerLimit', 'worstPerformerGraphData', 'worstPerformerLimit', 'vendCount', 'salesComparisonGraphData'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onSuccess: (page) => {
                    // router.reload({
                    //     only: ['activeMachineGraphData', 'dayGraphData', 'monthGraphData', 'monthsByModel', 'productGraphData', 'performerGraphData', 'vendCount'],
                    //     preserveState: true,
                    //     preserveScroll: true,
                    // })
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

    function loadMoreBestPerformers() {
        if (performerLimit.value >= 50 || performerLoading.value) {
            return;
        }

        const previousLimit = performerLimit.value;
        performerLoading.value = true;
        performerLimit.value = 50;

        router.visit(
            route('dashboard', buildDashboardQueryParams()),
            {
                only: ['performerGraphData', 'performerLimit'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onSuccess: () => {
                    syncDashboardData();
                },
                onError: () => {
                    performerLimit.value = previousLimit;
                },
                onFinish: () => {
                    performerLoading.value = false;
                },
            }
        );
    }

    function loadMoreWorstPerformers() {
        if (worstPerformerLimit.value >= 50 || worstPerformerLoading.value) {
            return;
        }

        const previousLimit = worstPerformerLimit.value;
        worstPerformerLoading.value = true;
        worstPerformerLimit.value = 50;

        router.visit(
            route('dashboard', buildDashboardQueryParams()),
            {
                only: ['worstPerformerGraphData', 'worstPerformerLimit'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onSuccess: () => {
                    syncDashboardData();
                },
                onError: () => {
                    worstPerformerLimit.value = previousLimit;
                },
                onFinish: () => {
                    worstPerformerLoading.value = false;
                },
            }
        );
    }

    function syncDashboardData () {
        const formatCurrency = (val) => {
            return val.toLocaleString(undefined, {
                minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent),
                maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)
            })
        }
        const formatCount = (val) => {
             return val.toLocaleString()
        }
        const sumData = (arr) => {
             return arr.reduce((a, b) => a + (Number(b) || 0), 0)
        }

        const sortByMonthYear = (a, b) => {
            const timeA = moment(a, 'MMMM YYYY').isValid() ? moment(a, 'MMMM YYYY').valueOf() : 0;
            const timeB = moment(b, 'MMMM YYYY').isValid() ? moment(b, 'MMMM YYYY').valueOf() : 0;
            return timeA - timeB;
        }

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
        salesComparisonGraphData.value = []
        salesComparisonGraphDatasets.value = []
        salesComparisonGraphLabels.value = []
        performerLimit.value = props.performerLimit ?? performerLimit.value
        worstPerformerLimit.value = props.worstPerformerLimit ?? worstPerformerLimit.value

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
        const monthKeys = Object.keys(months).sort(sortByMonthYear);
        monthKeys.forEach((month, monthIndex) => {
            const isCurrent = monthIndex === monthKeys.length - 1;
            const barColor = isCurrent ? '#ef4444' : '#3b82f6';
            const lineColor = isCurrent ? '#ff7f7f' : '#9ca3af'; // Red for current, Lighter Grey for others
            const countData = months[month].map((data) => {return data.count});
            const amountData = months[month].map((data) => {return data.amount});
            const iconData = months[month].map((data) => {return data.weather_icon});

            // Push Bar First ($)
            dayGraphDatasets.value.push({
                label: month + ' ('+ operatorCountry.currency_symbol + ') ' + formatCurrency(sumData(amountData)),
                data: amountData,
                weather_icons: iconData,
                backgroundColor: hexToRGBA(barColor, isCurrent ? 1 : 0.2),
                borderColor: hexToRGBA(barColor, isCurrent ? 1 : 0.2),
                fill: false,
                yAxisID: 'y',
                type: 'bar',
                order: (monthKeys.length - 1 - monthIndex) * 2 + 1,
            })

            // Push Line Second (#)
            dayGraphDatasets.value.push({
                label: month + ' (#) ' + formatCount(sumData(countData)),
                data: countData,
                backgroundColor: hexToRGBA(lineColor, 1),
                borderColor: hexToRGBA(lineColor, 1),
                yAxisID: 'y1',
                type: 'line',
                order: (monthKeys.length - 1 - monthIndex) * 2,
            })
        })
        for(let i = 1; i <= 31; i++) {
            dayGraphLabels.value.push(i)
        }

        monthGraphData.value = JSON.parse(JSON.stringify(props.monthGraphData))
        let years = []
        years = JSON.parse(JSON.stringify(props.monthGraphData))
        const yearKeys = Object.keys(years).sort(sortByMonthYear);
        yearKeys.forEach((month, monthIndex) => {
            const isCurrent = monthIndex === yearKeys.length - 1;
            const barColor = isCurrent ? '#ef4444' : '#3b82f6';
            const lineColor = isCurrent ? '#ff7f7f' : '#9ca3af'; // Red for current, Lighter Grey for others
            const countData = Object.values(years[month]).map((data) => {return data.count});
            const amountData = Object.values(years[month]).map((data) => {return data.amount});

            // Push Bar First ($)
            monthGraphDatasets.value.push({
                label: month + ' ('+ operatorCountry.currency_symbol + ') ' + formatCurrency(sumData(amountData)),
                data: amountData,
                backgroundColor: hexToRGBA(barColor, isCurrent ? 1 : 0.2),
                borderColor: hexToRGBA(barColor, isCurrent ? 1 : 0.2),
                fill: false,
                yAxisID: 'y',
                type: 'bar',
                order: (yearKeys.length - 1 - monthIndex) * 2 + 1,
            })

            // Push Line Second (#)
            monthGraphDatasets.value.push({
                label: month + ' (#) ' + formatCount(sumData(countData)),
                data: countData,
                backgroundColor: hexToRGBA(lineColor, 1),
                borderColor: hexToRGBA(lineColor, 1),
                yAxisID: 'y1',
                type: 'line',
                order: (yearKeys.length - 1 - monthIndex) * 2,
            })
        })
        for(let i = 1; i <= 12; i++) {
            monthGraphLabels.value.push(i)
        }

        productGraphData.value = JSON.parse(JSON.stringify(props.productGraphData))
        // If data is just array, use it. If it was resource, it had data key.
        const productDataArray = Array.isArray(productGraphData.value) ? productGraphData.value : (productGraphData.value.data || [])

        productGraphDatasets.value.push({
            label: 'Sales',
            data: productDataArray.map((data) => {return data.count}),
            backgroundColor: generalColors,
        })
        productGraphLabels.value = productDataArray.map((data) => {return data.product ? data.product.code + ' - ' + data.product.name : 'Unknown Product'})

        bestPerformerGraphData.value = JSON.parse(JSON.stringify(props.performerGraphData))
        worstPerformerGraphData.value = JSON.parse(JSON.stringify(props.worstPerformerGraphData))


        activeMachineGraphData.value = JSON.parse(JSON.stringify(props.activeMachineGraphData))
        let activeYears = []
        activeYears = JSON.parse(JSON.stringify(props.activeMachineGraphData))
        const activeYearKeys = Object.keys(activeYears).sort(sortByMonthYear);
        activeYearKeys.forEach((activeMonth, activeMonthIndex) => {
            const isCurrent = activeMonthIndex === activeYearKeys.length - 1;
            const color = isCurrent ? '#ef4444' : '#3b82f6';
            const countData = Object.values(activeYears[activeMonth]).map((data) => {return data.count});

            activeMachineGraphDatasets.value.push({
                label: activeMonth + ' (#) ' + formatCount(sumData(countData)),
                data: countData,
                backgroundColor: hexToRGBA(color, isCurrent ? 0.9 : 0.2),
                borderColor: hexToRGBA(color, isCurrent ? 0.9 : 0.2),
                type: 'line',
            })
        })
        for(let i = 1; i <= 12; i++) {
            activeMachineGraphLabels.value.push(i)
        }

        salesComparisonGraphData.value = JSON.parse(JSON.stringify(props.salesComparisonGraphData))
        if (salesComparisonGraphData.value) {
            let labels = [];

            // Data arrays for the 4 datasets
            let prevMonthData = [];
            let currMonthData = [];
            let nextMonthData = [];
            let lastYearData = [];

            let prevMonthIcons = [];
            let currMonthIcons = [];
            let nextMonthIcons = [];

            // Helper to process a period
            const processPeriod = (periodKey, lastYearKey, targetDataArray) => {
                if (!salesComparisonGraphData.value[periodKey]) return;

                const currentData = salesComparisonGraphData.value[periodKey].data;
                const currentIcons = salesComparisonGraphData.value[periodKey].weather_icons || [];
                const lastData = salesComparisonGraphData.value[lastYearKey]?.data || [];
                const monthLabel = salesComparisonGraphData.value[periodKey].label; // e.g., "Dec 2023"

                const length = Math.max(currentData.length, lastData.length);
                const middleDay = Math.ceil(length / 2);

                for (let i = 0; i < length; i++) {
                    // 1. Add to the specific target array (Prev, Curr, or Next)
                    targetDataArray.push(currentData[i] !== undefined ? currentData[i] : null);

                    // 2. Pad the OTHER current-year arrays with null
                    if (targetDataArray !== prevMonthData) prevMonthData.push(null);
                    if (targetDataArray !== currMonthData) currMonthData.push(null);
                    if (targetDataArray !== nextMonthData) nextMonthData.push(null);

                    // Add icons
                    if (targetDataArray === prevMonthData) prevMonthIcons.push(currentIcons[i] ?? null);
                    else prevMonthIcons.push(null);

                    if (targetDataArray === currMonthData) currMonthIcons.push(currentIcons[i] ?? null);
                    else currMonthIcons.push(null);

                    if (targetDataArray === nextMonthData) nextMonthIcons.push(currentIcons[i] ?? null);
                    else nextMonthIcons.push(null);

                    // 3. Add to Last Year Data (always continuous)
                    lastYearData.push(lastData[i] !== undefined ? lastData[i] : null);

                    // 4. Add Label
                    // If it's the middle day, add the Month Year
                    if (i + 1 === middleDay) {
                        labels.push([i + 1, monthLabel]);
                    } else {
                        labels.push(i + 1);
                    }
                }
            };

            // Helper to add spacer
            const addSpacer = () => {
                prevMonthData.push(null);
                currMonthData.push(null);
                nextMonthData.push(null);
                lastYearData.push(null);
                prevMonthIcons.push(null);
                currMonthIcons.push(null);
                nextMonthIcons.push(null);
                labels.push('');
            };

            // 1. Prev Month
            if (salesComparisonGraphData.value.prev_month) {
                processPeriod('prev_month', 'last_year_prev_month', prevMonthData);
            }

            // Spacer
            addSpacer();

            // 2. Current Month
            if (salesComparisonGraphData.value.current_month) {
                processPeriod('current_month', 'last_year_same_month', currMonthData);
            }

            // Spacer
            addSpacer();

            // 3. Next Month
            if (salesComparisonGraphData.value.next_month) {
                processPeriod('next_month', 'last_year_next_month', nextMonthData);
            }

            salesComparisonGraphLabels.value = labels;

            // Dataset 1: Prev Month (Blue)
            if (salesComparisonGraphData.value.prev_month) {
                salesComparisonGraphDatasets.value.push({
                    label: salesComparisonGraphData.value.prev_month.label + ' ' + formatCurrency(sumData(prevMonthData)),
                    data: prevMonthData,
                    backgroundColor: 'rgba(59, 130, 246, 0.6)', // Blue
                    borderColor: 'rgba(37, 99, 235, 1)',
                    borderWidth: 2,
                    type: 'bar',
                    order: 2,
                    barPercentage: 1.0,
                    categoryPercentage: 1.0,

                });
            }

            // Dataset 2: Current Month (Red)
            if (salesComparisonGraphData.value.current_month) {
                salesComparisonGraphDatasets.value.push({
                    label: salesComparisonGraphData.value.current_month.label + ' ' + formatCurrency(sumData(currMonthData)),
                    data: currMonthData,
                    backgroundColor: 'rgba(239, 68, 68, 0.7)', // Red
                    borderColor: 'rgba(220, 38, 38, 1)',
                    borderWidth: 2,
                    type: 'bar',
                    order: 2,
                    barPercentage: 1.0,
                    categoryPercentage: 1.0,

                });
            }

            // Dataset 3: Next Month (Green) - Previously Purple, but let's stick to user request "past use other colors". Next is distinct.
            if (salesComparisonGraphData.value.next_month) {
                salesComparisonGraphDatasets.value.push({
                    label: salesComparisonGraphData.value.next_month.label + ' ' + formatCurrency(sumData(nextMonthData)),
                    data: nextMonthData,
                    backgroundColor: 'rgba(16, 185, 129, 0.7)', // Green (swapped with prev current color to be "other")
                    borderColor: 'rgba(5, 150, 105, 1)',
                    borderWidth: 2,
                    type: 'bar',
                    order: 2,
                    barPercentage: 1.0,
                    categoryPercentage: 1.0,

                });
            }

            // Dataset 4: Last Year (Gray)
            salesComparisonGraphDatasets.value.push({
                label: 'Last Year ' + formatCurrency(sumData(lastYearData)),
                data: lastYearData,
                borderColor: 'rgba(107, 114, 128, 1)', // Gray
                backgroundColor: 'rgba(107, 114, 128, 0.1)',
                borderWidth: 2,
                type: 'line',
                fill: false,
                tension: 0.4,
                pointRadius: 3,
                pointHoverRadius: 6,
                pointHitRadius: 20,
                spanGaps: true,
                order: 1,
            });
        }

        forceRerender1()
        forceRerender2()
        forceRerender3()
        forceRerender4()
        forceRerender5()
        forceRerender5()
    }


</script>
