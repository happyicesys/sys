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
                <!--
                  These figures come from vend_records, whose grain is date x
                  machine - there is no product dimension to filter on, so a
                  product-restricted viewer is seeing the whole machine's takings,
                  not just their own SKUs'. Say so plainly rather than let the
                  numbers be read as theirs.
                -->
                <div
                    v-if="productRestricted"
                    class="mb-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800"
                >
                    <span class="font-semibold">Whole-machine figures.</span>
                    This page reports every product in a machine, including ones you do not have access to.
                    For sales limited to your products, use
                    <a href="/vends/transactions" class="underline font-medium">Transactions</a>.
                </div>
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
                        <SearchInput placeholderStr="Site name / ID" v-model="filters.customer" @keyup.enter="onSearchFilterUpdated()">
                            Site
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
                            <div class="flex flex-col space-y-1 md:flex-row md:space-y-0 md:space-x-1">
                                <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                @click.prevent="onSearchFilterUpdated()"
                                >
                                    <MagnifyingGlassIcon class="h-4 w-4" aria-hidden="true"/>
                                    <span>
                                        Search
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


                    <div v-if="!hasSearched" class="mt-2 mb-4 mx-4 rounded-lg border border-dashed border-gray-300 bg-white px-6 py-12 text-center text-gray-500">
                        Use the available filters and click <span class="font-semibold">Search</span> to load performance data.
                    </div>

                    <div v-if="hasSearched" class="p-1 py-4 bg-white border-b border-gray-200 flex flex-col space-y-6">
                        <p class="text-center p-2">
                            {{ (filters && filters.operator ? filters.operator.name : operator.name)  }}
                        </p>

                        <div class="border rounded-md p-4 bg-gray-50">
                            <div class="flex justify-between items-center mb-4">
                                <div class="flex items-center space-x-3">
                                    <h3 class="text-lg font-medium text-gray-900">Historical Analysis</h3>
                                    <!-- Years toggle -->
                                    <div class="flex rounded-md overflow-hidden border border-gray-300 shadow-sm text-sm font-medium">
                                        <button
                                            @click="() => { if (yearsBack !== 2) { yearsBack = 2; onSearchFilterUpdated(); } }"
                                            :class="yearsBack === 2 ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                                            class="px-3 py-1.5 transition-colors"
                                        >2 Yrs</button>
                                        <button
                                            @click="() => { if (yearsBack !== 3) { yearsBack = 3; onSearchFilterUpdated(); } }"
                                            :class="yearsBack === 3 ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-50'"
                                            class="px-3 py-1.5 border-l border-gray-300 transition-colors"
                                        >3 Yrs</button>
                                    </div>
                                </div>
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

                            <h4 class="text-gray-900 font-medium mb-2">Sales Comparison (Last Year vs Chosen Month Year)</h4>
                            <Graph
                                :key="componentKey5"
                                type="bar"
                                :labels="salesComparisonGraphLabels"
                                :datasets="salesComparisonGraphDatasets"
                                :options="salesComparisonGraphOptions"
                            >
                            </Graph>

                            <h4 class="text-gray-900 font-medium mb-2 mt-5">Last Month vs Chosen Month</h4>
                            <Graph
                                :key="componentKey1"
                                type="bar"
                                :labels="dayGraphLabels"
                                :datasets="dayGraphDatasets"
                                :options="dayGraphOptions"
                            >
                            </Graph>

                            <div class="pt-5">
                                <Graph
                                    :key="componentKey3"
                                    type="bar"
                                    :labels="monthGraphLabels"
                                    :datasets="monthGraphDatasets"
                                    :options="monthGraphOptions"
                                >
                                </Graph>
                            </div>
                            <!--
                              The same monthly bar as above, cut into the four
                              slices that add back up to it. Hidden rather than
                              shown empty when there is nothing to break down
                              (no site rows in range), so the page never carries
                              a flat, unexplained chart.
                            -->
                            <div class="pt-5" v-if="earningsGraphDatasets.length">
                                <Graph
                                    :key="componentKey6"
                                    type="bar"
                                    :labels="earningsGraphLabels"
                                    :datasets="earningsGraphDatasets"
                                    :options="earningsGraphOptions"
                                >
                                </Graph>
                                <div class="mt-2 px-2 text-xs text-gray-500 space-y-1">
                                    <p>
                                        Each bar is that month's <span class="font-medium">Total Sales (incl GST)</span>, split into
                                        <span class="font-medium text-emerald-700">Vend Earning</span>,
                                        <span class="font-medium text-amber-600">Loc Fees</span>,
                                        <span class="font-medium text-blue-600">Product Cost</span> and
                                        <span class="font-medium text-gray-500">GST</span>.
                                        The line is the <span class="font-medium">VE ratio</span> — Vend Earning ÷ Total Sales.
                                        Figures come from the monthly Site Summary, so they cover binded sites only and can sit
                                        slightly under the Sales by Months totals above.
                                    </p>
                                    <p v-if="earningsProvisionalMonths.length">
                                        <span class="font-medium text-gray-700">Outlined segments are provisional.</span>
                                        Loc Fees and Vend Earning are recalculated from each site's current contract terms until
                                        the period is locked — amend a contract and these months re-price.
                                        Not yet fully locked:
                                        <span v-for="(entry, entryIndex) in earningsProvisionalMonths" :key="entry.year">
                                            <span v-if="entryIndex > 0"> · </span>{{ entry.year }} {{ entry.months }}
                                        </span>
                                        <span class="block">Hover a bar for how much of that month is still open.</span>
                                    </p>
                                    <p v-if="earningsNegativeSlices.length">
                                        <!-- Phrased as a label rather than a sentence, like "Not yet fully
                                             locked:" above — a verb here would have to agree with a list
                                             that is 1-3 items long and whose names are already plural. -->
                                        <span class="font-medium text-gray-700">
                                            Negative in some months: {{ formatList(earningsNegativeSlices) }}.
                                        </span>
                                        <!-- Only explain the slices actually in play — a reason for a slice
                                             that did not go negative reads as if it had. -->
                                        <span v-if="earningsNegativeSlices.includes('Loc Fees')">
                                            A negative Loc Fee is a Subsidized Plan, or an External Subsidize larger than the
                                            rental, where the location pays us.
                                        </span>
                                        <span v-if="earningsNegativeSlices.includes('Vend Earning')">
                                            A negative Vend Earning is a site whose Loc Fee came to more than it earned.
                                        </span>
                                        <span v-if="earningsNegativeSlices.includes('Product Cost')">
                                            A negative Product Cost is Gross Earning booked above ex-GST sales — usually a
                                            refund or a re-costed SKU.
                                        </span>
                                        Negative segments are drawn below the axis, so for those months the stack does not add
                                        up to the bar height. The tooltip figures are still exact.
                                    </p>
                                </div>
                            </div>

                            <div class="pt-5" v-else-if="earningsNoSiteData">
                                <p class="rounded-lg border border-dashed border-gray-300 bg-white px-6 py-8 text-center text-sm text-gray-500">
                                    No Vend Earning / Loc Fees breakdown for this filter — the machines it selects are not bound
                                    to a site, so they have no contract terms to split sales against.
                                </p>
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
                                                                {{ (vend.customer || vend.vend?.customer).id + 20000 }}
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
                                                                {{ (vend.customer || vend.vend?.customer).id + 20000 }}
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
        productRestricted: Boolean,
        activeMachineGraphData: Object,
        autoLoad: Boolean,
        dayGraphData: Object,
        earningsGraphData: Object,
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
    const componentKey6 = ref(0);
    const yearsBack = ref(2);
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
    const forceRerender6 = () => {
        componentKey6.value += 1;
    };
    const locationTypeOptions = ref([])
    const operator = usePage().props.auth.operator
    const operatorCountry = usePage().props.auth.operatorCountry
    const operatorOptions = ref([])
    const permissions = usePage().props.auth.permissions
    const hasSearched = ref(props.autoLoad ?? false)
    const showFilters = ref(!hasSearched.value)
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

    /* ---------------------------------------------------------------------
     * Sales by Months — Breakdown.
     *
     * The SAME monthly bar as "Sales by Months" above, cut into the four slices
     * that add back up to it:
     *
     *     Total Sales (incl GST) = Vend Earning + Loc Fees + Product Cost + GST
     *
     * Stacked bottom-up in that order (Chart.js stacks in dataset order), one
     * stack per year sitting side by side — same year-over-year layout, and the
     * same 2/3-year toggle, as the chart above. The right axis carries the VE
     * ratio (Vend Earning ÷ Total Sales) as a line per year.
     *
     * Hue encodes the SLICE, opacity encodes the YEAR (current year solid,
     * earlier years faded) — the inverse of the charts above, where hue was free
     * to encode the year. Within a month the stacks read left-to-right oldest →
     * current, exactly as the bars above do.
     *
     * Outlined Loc Fees / Vend Earning segments mark a month that is not fully
     * locked: those two slices are recomputed nightly from each site's CURRENT
     * contract terms until the period is locked, so amending a contract
     * re-prices every unlocked month. GST and Product Cost do not move that way,
     * so they are never outlined.
     * ------------------------------------------------------------------- */
    const EARNINGS_SLICE_COLORS = {
        vend_earning: '#059669', // emerald — what we keep
        loc_fees: '#f59e0b',     // amber   — what the site takes
        product_cost: '#3b82f6', // blue    — cost of goods
        gst: '#9ca3af',          // grey    — never ours, passed to IRAS
    }
    // Bottom → top of the stack. Mirrors the order on the sketch this chart
    // came from, and puts the two contract-driven (movable) slices adjacent at
    // the bottom.
    const EARNINGS_SLICES = [
        { key: 'vend_earning', name: 'Vend Earning', movable: true },
        { key: 'loc_fees', name: 'Loc Fees', movable: true },
        { key: 'product_cost', name: 'Product Cost', movable: false },
        { key: 'gst', name: 'GST', movable: false },
    ]

    const formatMoneyValue = (val) => {
        return Number(val ?? 0).toLocaleString(undefined, {
            minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent),
            maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)
        })
    }

    const earningsGraphData = ref([]);
    const earningsGraphDatasets = ref([])
    const earningsGraphLabels = ref([])
    // Months (by year) whose site rows are not ALL locked — rendered under the
    // chart so the caveat is stated in words, not only as an outline.
    const earningsProvisionalMonths = ref([])
    // Names of the slices that went negative somewhere in view — Chart.js draws
    // negatives below the axis, so the stack stops summing to the bar height and
    // that needs saying. See the scan in syncDashboardData() for which slices can
    // go negative and why.
    const earningsNegativeSlices = ref([])
    // Filter matched machines, but none of them sit behind a site contract.
    const earningsNoSiteData = ref(false)
    const earningsGraphOptions = ref({
        // One segment at a time. 'index' would open a 10+ row tooltip covering
        // every slice of every year; the stack is already readable by eye, so
        // the tooltip's job here is to name the one block under the cursor.
        interaction: {
            mode: 'nearest',
            intersect: true,
        },
        scales: {
            x: {
                stacked: true,
            },
            y: {
                position: 'left',
                stacked: true,
                title: {
                    display: true,
                    text: 'Sales(' + operatorCountry.currency_symbol + ')'
                },
                beginAtZero: true
            },
            y1: {
                position: 'right',
                // NOT stacked — this axis carries ratio lines, not bar slices.
                // Leaving it stacked would make the second year's line render at
                // year1 + year2.
                stacked: false,
                title: {
                    display: true,
                    text: 'VE ratio'
                },
                beginAtZero: true,
                grid: {
                    drawOnChartArea: false,
                },
                ticks: {
                    callback: function (value) {
                        return (Number(value) * 100).toFixed(0) + '%'
                    }
                }
            },
        },
        plugins: {
            title: {
                display: true,
                text: 'Sales by Months — Vend Earning / Loc Fees / Product Cost / GST'
            },
            legend: {
                reverse: false,
                labels: {
                    padding: 12,
                    boxWidth: 12,
                    sort: (a, b) => a.datasetIndex - b.datasetIndex
                }
            },
            tooltip: {
                callbacks: {
                    title: function (items) {
                        const item = items[0]
                        if (!item) return ''
                        return (item.dataset.year_label ?? '') + ' — month ' + item.label
                    },
                    label: function (item) {
                        const ds = item.dataset
                        const value = Number(item.parsed.y ?? 0)

                        if (ds.is_ratio) {
                            return 'VE ratio: ' + (value * 100).toFixed(1) + '%'
                        }

                        const total = Number((ds.month_totals || [])[item.dataIndex] ?? 0)
                        // GST alone is expressed against the EX-GST base so it reads
                        // as the statutory rate ("9%"), not the tax fraction of the
                        // inclusive total ("8.3%") — users kept reading the latter as
                        // a wrong rate. The other three slices stay on the incl-GST
                        // denominator so Vend Earning's share keeps matching the VE
                        // ratio line and Site Summary's "Rate" column.
                        const isGst = ds.slice_name === 'GST'
                        const base = isGst ? (total - value) : total
                        const share = base > 0
                            ? ' (' + ((value / base) * 100).toFixed(1) + '%' + (isGst ? ' of ex-GST sales' : ' of sales') + ')'
                            : ''

                        return (ds.slice_name ?? ds.label) + ': '
                            + operatorCountry.currency_symbol + formatMoneyValue(value)
                            + share
                    },
                    footer: function (items) {
                        const item = items[0]
                        if (!item) return ''
                        const ds = item.dataset
                        const lines = []

                        if (!ds.is_ratio) {
                            const total = Number((ds.month_totals || [])[item.dataIndex] ?? 0)
                            lines.push('Total sales: ' + operatorCountry.currency_symbol + formatMoneyValue(total))
                        }

                        const lock = (ds.lock_info || [])[item.dataIndex]
                        if (lock && lock.site_count) {
                            if (lock.is_locked) {
                                lines.push('Locked — final (' + lock.site_count + ' sites)')
                            } else {
                                // Name the exposure, not just the state: a month
                                // with 16 of 416 sites open is nearly settled,
                                // one with 416 of 416 is not settled at all.
                                const share = lock.unlocked_share === null || lock.unlocked_share === undefined
                                    ? ''
                                    : ' — ' + (lock.unlocked_share * 100).toFixed(1) + '% of sales'
                                lines.push('Provisional: ' + lock.unlocked_sites + ' of ' + lock.site_count + ' sites unlocked' + share)
                            }
                        }

                        return lines
                    }
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
                text: 'Average Number of Vending Machines (Site) in operation, by Month'
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
        if (props.autoLoad) {
            syncDashboardData()
        }
    })

    onMounted(() => {
        filters.value.locationType = locationTypeOptions.value[0]
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

    /**
     * [1,2,3,4,8] -> "Jan–Apr, Aug". Used for the provisional-months caption,
     * where a fully-unlocked year would otherwise print twelve comma-separated
     * month names.
     */
    function monthRunsLabel(monthNumbers) {
        const sorted = [...new Set(monthNumbers)].sort((a, b) => a - b);
        const name = (m) => moment().month(m - 1).format('MMM');
        const parts = [];
        let runStart = null;
        let runEnd = null;

        sorted.forEach((m) => {
            if (runStart === null) {
                runStart = runEnd = m;
                return;
            }
            if (m === runEnd + 1) {
                runEnd = m;
                return;
            }
            parts.push(runStart === runEnd ? name(runStart) : name(runStart) + '–' + name(runEnd));
            runStart = runEnd = m;
        });

        if (runStart !== null) {
            parts.push(runStart === runEnd ? name(runStart) : name(runStart) + '–' + name(runEnd));
        }

        return parts.join(', ');
    }

    /**
     * ['A','B','C'] -> "A, B and C". Used for the negative-slice caption, where a
     * plain join(' and ') would read "A and B and C".
     */
    function formatList(items) {
        if (items.length <= 1) {
            return items.join('');
        }

        return items.slice(0, -1).join(', ') + ' and ' + items[items.length - 1];
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
            autoload: true,
            locationType: locationType && locationType.id ? locationType.id : '',
            location_type_id: locationType && locationType.id ? locationType.id : '',
            operators: filters.value.operators.filter(operator => operator).map((operator) => { return operator.id }),
            vendModels: filters.value.vendModels.map((vendModel) => { return vendModel.id }),
            vendPrefixes: filters.value.vendPrefixes.map((vendPrefix) => { return vendPrefix.id }),
            performer_limit: performerLimit.value,
            best_performer_limit: performerLimit.value,
            worst_performer_limit: worstPerformerLimit.value,
            month_year: filters.value.monthYear,
            years_back: yearsBack.value,
        };
    }

    function onSearchFilterUpdated() {
        router.visit(
            route('dashboard', buildDashboardQueryParams()),{
                only: ['activeMachineGraphData', 'dayGraphData', 'earningsGraphData', 'monthGraphData', 'monthsByModel', 'productGraphData', 'performerGraphData', 'performerLimit', 'worstPerformerGraphData', 'worstPerformerLimit', 'vendCount', 'salesComparisonGraphData'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
                onSuccess: (page) => {
                    // router.reload({
                    //     only: ['activeMachineGraphData', 'dayGraphData', 'monthGraphData', 'monthsByModel', 'productGraphData', 'performerGraphData', 'vendCount'],
                    //     preserveState: true,
                    //     preserveScroll: true,
                    // })
                    hasSearched.value = true
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
        earningsGraphData.value = []
        earningsGraphDatasets.value = []
        earningsGraphLabels.value = []
        earningsProvisionalMonths.value = []
        earningsNegativeSlices.value = []
        earningsNoSiteData.value = false
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
            const lineColor = isCurrent ? '#4b5563' : '#15803d'; // Dark Grey for current, Darker Green for others
            const countData = months[month].map((data, index) => {
                if (moment(month, 'MMMM YYYY').date(index + 1).isAfter(moment(), 'day')) {
                    return null
                }
                return data.count
            });
            const amountData = months[month].map((data, index) => {
                if (moment(month, 'MMMM YYYY').date(index + 1).isAfter(moment(), 'day')) {
                    return null
                }
                return data.amount
            });
            const iconData = months[month].map((data) => {return data.weather_icon});

            // Push Bar First ($)
            dayGraphDatasets.value.push({
                label: month + ' ('+ operatorCountry.currency_symbol + ') ' + formatCurrency(sumData(amountData)),
                data: amountData,
                weather_icons: iconData,
                backgroundColor: hexToRGBA(barColor, isCurrent ? 1 : 0.4),
                borderColor: hexToRGBA(barColor, isCurrent ? 1 : 0.4),
                fill: false,
                yAxisID: 'y',
                type: 'bar',
                order: 2,
            })

            // Push Line Second (#)
            dayGraphDatasets.value.push({
                label: month + ' (#) ' + formatCount(sumData(countData)),
                data: countData,
                backgroundColor: hexToRGBA(lineColor, isCurrent ? 1 : 0.4),
                borderColor: hexToRGBA(lineColor, isCurrent ? 1 : 0.4),
                yAxisID: 'y1',
                type: 'line',
                order: 1,
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
            const isOldest = monthIndex === 0 && yearKeys.length >= 3;
            // Current year = red, oldest year (3yr mode) = orange, middle year = blue
            const barColor = isCurrent ? '#ef4444' : (isOldest ? '#f97316' : '#3b82f6');
            const lineColor = isCurrent ? '#4b5563' : (isOldest ? '#9a3412' : '#15803d');
            const countData = Object.values(years[month]).map((data, index) => {
                if (moment(month, 'YYYY').month(index).endOf('month').isAfter(moment(), 'month')) {
                    return null
                }
                return data.count
            });
            const amountData = Object.values(years[month]).map((data, index) => {
                if (moment(month, 'YYYY').month(index).endOf('month').isAfter(moment(), 'month')) {
                    return null
                }
                return data.amount
            });

            // Push Bar First ($)
            monthGraphDatasets.value.push({
                label: month + ' ('+ operatorCountry.currency_symbol + ') ' + formatCurrency(sumData(amountData)),
                data: amountData,
                backgroundColor: hexToRGBA(barColor, isCurrent ? 1 : 0.4),
                borderColor: hexToRGBA(barColor, isCurrent ? 1 : 0.4),
                fill: false,
                yAxisID: 'y',
                type: 'bar',
                order: 2,
            })

            // Push Line Second (#)
            monthGraphDatasets.value.push({
                label: month + ' (#) ' + formatCount(sumData(countData)),
                data: countData,
                backgroundColor: hexToRGBA(lineColor, isCurrent ? 1 : 0.4),
                borderColor: hexToRGBA(lineColor, isCurrent ? 1 : 0.4),
                yAxisID: 'y1',
                type: 'line',
                order: 1,
            })
        })
        for(let i = 1; i <= 12; i++) {
            monthGraphLabels.value.push(i)
        }

        // ---- Sales by Months — Breakdown -------------------------------------
        // Same {year: {month: {...}}} shape as monthGraphData, so the two charts
        // walk their years the same way. Empty ({}) on a page that didn't
        // compute it (the Lite twin), which just yields no datasets.
        earningsGraphData.value = JSON.parse(JSON.stringify(props.earningsGraphData ?? {}))
        const earningsYears = earningsGraphData.value ?? {}
        // Keys here are bare years ("2024"), NOT "January 2024" — passing them to
        // sortByMonthYear made every comparison 0 (its moment('MMMM YYYY') parse
        // fails and falls back to 0), so the order only held by accident, via the
        // JS rule that integer-like object keys enumerate ascending. Sort numerically.
        const earningsYearKeys = Object.keys(earningsYears).sort((a, b) => Number(a) - Number(b));
        const provisional = []
        const negativeSlices = new Set()

        earningsYearKeys.forEach((year, yearIndex) => {
            const isCurrent = yearIndex === earningsYearKeys.length - 1;
            // Opacity carries the year here (hue is spoken for by the slice).
            const alpha = isCurrent ? 0.95 : 0.45;
            const monthRows = Object.values(earningsYears[year]);

            // Future months of the current year are absent from the payload —
            // getEarningsGraphData stops at the current month in APP timezone.
            // There is deliberately no client-side "is this month in the future"
            // guard here: it would re-derive the cut-off from the *browser*
            // clock, so a viewer west of Asia/Singapore on the 1st of a month
            // blanked the current month while the bar chart above still drew it.
            const monthTotals = monthRows.map((data) => Number(data.sales ?? 0));
            const lockInfo = monthRows.map((data) => ({
                is_locked: !!data.is_locked,
                locked_sites: Number(data.locked_sites ?? 0),
                unlocked_sites: Number(data.unlocked_sites ?? 0),
                unlocked_share: data.unlocked_share === null || data.unlocked_share === undefined
                    ? null
                    : Number(data.unlocked_share),
                site_count: Number(data.site_count ?? 0),
            }));

            // A year the filter matched no sites for contributes nothing. Without
            // this, a filter that selects only unbinded machines (which have no
            // site contract, so no summary rows) would still push a full set of
            // datasets and draw twelve zero-height bars as if that were the
            // answer. See the v-else note in the template.
            if (!monthRows.some((data) => Number(data.site_count ?? 0) > 0)) {
                return;
            }

            // Months with real rows that are not fully locked — named in the
            // caption under the chart. Collapsed into runs (Jan–Apr, Aug) so a
            // fully-unlocked year reads as one range instead of twelve tokens.
            const unlockedMonthNumbers = monthRows
                .filter((data) => Number(data.site_count ?? 0) > 0 && !data.is_locked)
                .map((data) => Number(data.month));
            if (unlockedMonthNumbers.length) {
                provisional.push({ year, months: monthRunsLabel(unlockedMonthNumbers) });
            }

            EARNINGS_SLICES.forEach((slice) => {
                const sliceData = monthRows.map((data) => Number(data[slice.key] ?? 0));
                const color = EARNINGS_SLICE_COLORS[slice.key];

                // THREE of the four slices can come out negative, and Chart.js
                // draws a negative segment below the axis — so the positive stack
                // then stands taller than Total Sales and the bar looks wrong
                // unless the caption says why:
                //
                //   Loc Fees      a Subsidized Plan site, or External Subsidize
                //                 above the rental — the location pays us.
                //   Vend Earning  the net Loc Fee exceeded Gross Earning, so the
                //                 site cost more than it made. The COMMON one:
                //                 5-32 sites a month through 2026, against 6 rows
                //                 for a negative fee.
                //   Product Cost  Gross Earning above ex-GST sales (a costing
                //                 artefact — refunds, a re-costed SKU). 1-20 rows
                //                 a month.
                //
                // GST alone cannot go negative. All three are drowned out at fleet
                // aggregate but easy to hit with a site or machine filter, which is
                // the normal way this page is used — so the check is per slice and
                // the caption names the ones that actually went negative.
                // (null future months compare as 0, so they never trip this.)
                if (sliceData.some((value) => value < 0)) {
                    negativeSlices.add(slice.name);
                }

                // Outline only the two contract-driven slices, and only where the
                // month isn't fully locked — see the options docblock.
                const outlined = monthRows.map((data) =>
                    slice.movable && Number(data.site_count ?? 0) > 0 && !data.is_locked
                );

                earningsGraphDatasets.value.push({
                    label: year + ' ' + slice.name + ' ' + operatorCountry.currency_symbol + formatCurrency(sumData(sliceData)),
                    slice_name: slice.name,
                    year_label: year,
                    month_totals: monthTotals,
                    lock_info: lockInfo,
                    data: sliceData,
                    backgroundColor: hexToRGBA(color, alpha),
                    borderColor: outlined.map((flag) => flag ? 'rgba(17, 24, 39, 0.9)' : hexToRGBA(color, alpha)),
                    borderWidth: outlined.map((flag) => flag ? 1.5 : 0),
                    // Without this Chart.js skips the border on the edge each bar
                    // sits on, so a stacked segment would only ever be outlined
                    // on three sides.
                    borderSkipped: false,
                    yAxisID: 'y',
                    type: 'bar',
                    // Datasets sharing a stack id pile up; different ids sit side
                    // by side. One id per year => one stack per year per month.
                    stack: 'earnings-' + year,
                    order: 2,
                })
            })

            // VE ratio for the year, on the right axis. The legend figure is the
            // YEAR's ratio (total VE / total sales), not an average of monthly
            // ratios — averaging ratios would weight a quiet month equally with
            // a busy one.
            const ratioData = monthRows.map((data) => {
                return data.ve_ratio === null || data.ve_ratio === undefined ? null : Number(data.ve_ratio);
            });
            const yearVendEarning = sumData(monthRows.map((data) => Number(data.vend_earning ?? 0)));
            const yearSales = sumData(monthTotals);
            const yearRatio = yearSales ? (yearVendEarning / yearSales) : 0;
            const ratioColor = isCurrent ? '#111827' : (yearIndex === 0 && earningsYearKeys.length >= 3 ? '#9a3412' : '#15803d');

            earningsGraphDatasets.value.push({
                label: year + ' VE ratio ' + (yearRatio * 100).toFixed(1) + '%',
                year_label: year,
                lock_info: lockInfo,
                is_ratio: true,
                data: ratioData,
                backgroundColor: hexToRGBA(ratioColor, isCurrent ? 1 : 0.6),
                borderColor: hexToRGBA(ratioColor, isCurrent ? 1 : 0.6),
                borderWidth: 2,
                fill: false,
                // A month with no sales has a null ratio; join across it rather
                // than leaving the line broken.
                spanGaps: true,
                yAxisID: 'y1',
                type: 'line',
                order: 1,
            })
        })
        earningsProvisionalMonths.value = provisional
        // Ordered bottom-of-stack first, so the caption lists them the way they
        // appear on the chart rather than in whichever order a year hit them.
        earningsNegativeSlices.value = EARNINGS_SLICES
            .map((slice) => slice.name)
            .filter((name) => negativeSlices.has(name))
        // The payload had years but none of them held a site: the filter selected
        // machines with no site contract behind them. Distinct from "no payload at
        // all" (pre-search, or the Lite page), which shows nothing.
        earningsNoSiteData.value = earningsYearKeys.length > 0 && earningsGraphDatasets.value.length === 0
        for(let i = 1; i <= 12; i++) {
            earningsGraphLabels.value.push(i)
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
            const isOldest = activeMonthIndex === 0 && activeYearKeys.length >= 3;
            // Current year = red, oldest year (3yr mode) = orange, middle year = blue
            const color = isCurrent ? '#ef4444' : (isOldest ? '#f97316' : '#3b82f6');
            const countData = Object.values(activeYears[activeMonth]).map((data) => {return data.count});

            activeMachineGraphDatasets.value.push({
                label: activeMonth + ' (#) ' + formatCount(sumData(countData)),
                data: countData,
                backgroundColor: hexToRGBA(color, isCurrent ? 0.9 : 0.4),
                borderColor: hexToRGBA(color, isCurrent ? 0.9 : 0.4),
                type: 'line',
            })
        })
        for(let i = 1; i <= 12; i++) {
            activeMachineGraphLabels.value.push(i)
        }

        salesComparisonGraphData.value = JSON.parse(JSON.stringify(props.salesComparisonGraphData))
        if (salesComparisonGraphData.value) {
            let labels = [];

            // Data arrays for the datasets
            let prevMonthData = [];
            let currMonthData = [];
            let nextMonthData = [];
            let lastYearData = [];
            let twoYearsAgoData = [];

            let prevMonthIcons = [];
            let currMonthIcons = [];
            let nextMonthIcons = [];

            const hasTwoYearsAgo = !!(
                salesComparisonGraphData.value.two_years_ago_same_month ||
                salesComparisonGraphData.value.two_years_ago_prev_month ||
                salesComparisonGraphData.value.two_years_ago_next_month
            );

            // Helper to process a period
            const processPeriod = (periodKey, lastYearKey, twoYearsAgoKey, targetDataArray) => {
                if (!salesComparisonGraphData.value[periodKey]) return;

                const currentData = salesComparisonGraphData.value[periodKey].data;
                const currentIcons = salesComparisonGraphData.value[periodKey].weather_icons || [];
                const lastData = salesComparisonGraphData.value[lastYearKey]?.data || [];
                const twoYearsData = twoYearsAgoKey ? (salesComparisonGraphData.value[twoYearsAgoKey]?.data || []) : [];
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

                    // 4. Add to Two Years Ago Data (always continuous when present)
                    twoYearsAgoData.push(twoYearsData[i] !== undefined ? twoYearsData[i] : null);

                    // 5. Add Label — middle day shows the Month Year label
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
                twoYearsAgoData.push(null);
                prevMonthIcons.push(null);
                currMonthIcons.push(null);
                nextMonthIcons.push(null);
                labels.push('');
            };

            // 1. Prev Month
            if (salesComparisonGraphData.value.prev_month) {
                processPeriod('prev_month', 'last_year_prev_month', hasTwoYearsAgo ? 'two_years_ago_prev_month' : null, prevMonthData);
            }

            // Spacer
            addSpacer();

            // 2. Current Month
            if (salesComparisonGraphData.value.current_month) {
                processPeriod('current_month', 'last_year_same_month', hasTwoYearsAgo ? 'two_years_ago_same_month' : null, currMonthData);
            }

            // Spacer
            addSpacer();

            // 3. Next Month
            if (salesComparisonGraphData.value.next_month) {
                processPeriod('next_month', 'last_year_next_month', hasTwoYearsAgo ? 'two_years_ago_next_month' : null, nextMonthData);
            }

            salesComparisonGraphLabels.value = labels;

            // Dataset 1: Prev Month (Blue)
            if (salesComparisonGraphData.value.prev_month) {
                salesComparisonGraphDatasets.value.push({
                    label: salesComparisonGraphData.value.prev_month.label + ' (' + operatorCountry.currency_symbol + ') ' + formatCurrency(sumData(prevMonthData)),
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
                    label: salesComparisonGraphData.value.current_month.label + ' (' + operatorCountry.currency_symbol + ') ' + formatCurrency(sumData(currMonthData)),
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
                    label: salesComparisonGraphData.value.next_month.label + ' (' + operatorCountry.currency_symbol + ') ' + formatCurrency(sumData(nextMonthData)),
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
                label: 'Last Year (' + operatorCountry.currency_symbol + ') ' + formatCurrency(sumData(lastYearData)),
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

            // Dataset 5: 2 Years Ago (Purple) — only shown in 3yr mode
            if (hasTwoYearsAgo) {
                salesComparisonGraphDatasets.value.push({
                    label: '2 Years Ago (' + operatorCountry.currency_symbol + ') ' + formatCurrency(sumData(twoYearsAgoData)),
                    data: twoYearsAgoData,
                    borderColor: 'rgba(168, 85, 247, 1)', // Purple
                    backgroundColor: 'rgba(168, 85, 247, 0.1)',
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
        }

        forceRerender1()
        forceRerender2()
        forceRerender3()
        forceRerender4()
        forceRerender5()
        forceRerender6()
    }


</script>
