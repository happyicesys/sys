
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
            <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
                <SearchInput placeholderStr="4 to 5 Digits Number" v-model="filters.codes" @keyup.enter="onSearchFilterUpdated()">
                    Vend ID
                    <span class="text-[9px]">
                        ("," for multiple)
                    </span>
                </SearchInput>
                <SearchInput class="md:block" :class="[showAllFilters ? 'block' : 'hidden']" placeholderStr="Channel ID" v-model="filters.channel_codes" @keyup.enter="onSearchFilterUpdated()">
                    Channel ID
                    <span class="text-[9px]">
                        ("," for multiple)
                    </span>
                </SearchInput>
                <SearchInput class="md:block" :class="[showAllFilters ? 'block' : 'hidden']"  placeholderStr="Serial Num" v-model="filters.serialNum" @keyup.enter="onSearchFilterUpdated()">
                    Serial Num
                </SearchInput>
                <SearchInput placeholderStr="Number" v-model="filters.tempHigherThan" @keyup.enter="onSearchFilterUpdated()">
                    Temp &gt;&gt;
                </SearchInput>
                <SearchInput class="md:block" :class="[showAllFilters ? 'block' : 'hidden']"  placeholderStr="Number" v-model="filters.tempDeltaHigherThan" @keyup.enter="onSearchFilterUpdated()">
                    T1-T2 Delta &gt;&gt;
                </SearchInput>
                <div class="md:block" :class="[showAllFilters ? 'block' : 'hidden']">
                    <label for="text" class="block text-sm font-medium text-gray-700">
                        Channel Errors
                    </label>
                    <MultiSelect
                        v-model="filters.errors"
                        :options="vendChannelErrorsOptions"
                        valueProp="id"
                        label="desc"
                        mode="tags"
                        placeholder="Select"
                        open-direction="bottom"
                        class="mt-1"
                    >
                    </MultiSelect>
                </div>
                <!-- <SearchInput class="md:block" :class="[showAllFilters ? 'block' : 'hidden']" placeholderStr="Cust ID" v-model="filters.customer_code" v-if="permissions.includes('admin-access vends')" @keyup.enter="onSearchFilterUpdated()">
                    Cust ID
                </SearchInput> -->
                <SearchInput placeholderStr="Customer" v-model="filters.customer" v-if="permissions.includes('admin-access vends')" @keyup.enter="onSearchFilterUpdated()">
                    Customer
                </SearchInput>
                <div class="md:block" :class="[showAllFilters ? 'block' : 'hidden']"  v-if="permissions.includes('admin-access vends')">
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
                <div class="md:block" :class="[showAllFilters ? 'block' : 'hidden']"  v-if="permissions.includes('admin-access vends')">
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
                <!-- <div v-if="permissions.includes('admin-access vends')">
                    <label for="text" class="block text-sm font-medium text-gray-700">
                        Customer Binded?
                    </label>
                    <MultiSelect
                        v-model="filters.is_binded_customer"
                        :options="booleanOptions"
                        trackBy="id"
                        valueProp="id"
                        label="value"
                        placeholder="Select"
                        open-direction="bottom"
                        class="mt-1"
                    >
                    </MultiSelect>
                </div> -->
                <div v-if="permissions.includes('admin-access vends')">
                    <label for="text" class="block text-sm font-medium text-gray-700">
                        Is Active?
                    </label>
                    <MultiSelect
                        v-model="filters.is_active"
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
                <div class="md:block" :class="[showAllFilters ? 'block' : 'hidden']" >
                    <label for="text" class="block text-sm font-medium text-gray-700">
                        Sensor Status
                    </label>
                    <MultiSelect
                        v-model="filters.is_sensor"
                        :options="enableOptions"
                        trackBy="id"
                        valueProp="id"
                        label="value"
                        placeholder="Select"
                        open-direction="bottom"
                        class="mt-1"
                    >
                    </MultiSelect>
                </div>
                <div class="md:block" :class="[showAllFilters ? 'block' : 'hidden']" >
                    <label for="text" class="block text-sm font-medium text-gray-700">
                        Is Door Open
                    </label>
                    <MultiSelect
                        v-model="filters.is_door_open"
                        :options="doorOptions"
                        trackBy="id"
                        valueProp="id"
                        label="value"
                        placeholder="Select"
                        open-direction="bottom"
                        class="mt-1"
                    >
                    </MultiSelect>
                </div>
                <SearchInput class="md:block" :class="[showAllFilters ? 'block' : 'hidden']" placeholderStr="Fan Speed" v-model="filters.fanSpeedLowerThan" @keyup.enter="onSearchFilterUpdated()">
                    Fan Speed &lt;&lt;
                </SearchInput>
                <div v-if="permissions.includes('admin-access vends')">
                    <label for="text" class="block text-sm font-medium text-gray-700">
                        Operator
                    </label>
                    <MultiSelect
                        v-model="filters.operator"
                        :options="operatorOptions"
                        trackBy="id"
                        valueProp="id"
                        label="full_name"
                        placeholder="Select"
                        open-direction="bottom"
                        class="mt-1"
                    >
                    </MultiSelect>
                </div>
                <div class="md:block" :class="[showAllFilters ? 'block' : 'hidden']">
                    <label for="text" class="block text-sm font-medium text-gray-700">
                        Location Type
                    </label>
                    <MultiSelect
                        v-model="filters.locationType"
                        :options="locationTypeOptions"
                        trackBy="id"
                        valueProp="id"
                        label="value"
                        placeholder="Select"
                        open-direction="bottom"
                        class="mt-1"
                    >
                    </MultiSelect>
                </div>
                <SearchInput class="md:block" :class="[showAllFilters ? 'block' : 'hidden']" placeholderStr="How many Day(s)" v-model="filters.lastVisitedGreaterThan" @keyup.enter="onSearchFilterUpdated()">
                    Last Visited Day &gt;&gt;
                </SearchInput>
                <SearchInput class="md:block" :class="[showAllFilters ? 'block' : 'hidden']" placeholderStr="Balance Stock Less Than" v-model="filters.balanceStockLessThan" @keyup.enter="onSearchFilterUpdated()">
                    Balance Stock(%) &lt;&lt;
                </SearchInput>
                <SearchInput class="md:block" :class="[showAllFilters ? 'block' : 'hidden']" placeholderStr="Remaining SKU Less Than" v-model="filters.remainingSkuLessThan" @keyup.enter="onSearchFilterUpdated()">
                    Remaining SKU(%) &lt;&lt;
                </SearchInput>
                <SearchInput class="md:block" :class="[showAllFilters ? 'block' : 'hidden']" placeholderStr="Firmware Ver" v-model="filters.virtual_firmware_ver" v-if="permissions.includes('admin-access vends')" @keyup.enter="onSearchFilterUpdated()">
                    Firmware Ver
                </SearchInput>
                <SearchInput class="md:block" :class="[showAllFilters ? 'block' : 'hidden']" placeholderStr="APK Ver" v-model="filters.virtual_apk_ver" v-if="permissions.includes('admin-access vends')" @keyup.enter="onSearchFilterUpdated()">
                    APK Ver
                </SearchInput>
                <SearchInput class="md:block" :class="[showAllFilters ? 'block' : 'hidden']" placeholderStr="Avg Day Sales Less Than" v-model="filters.vendRecordsThirtyDaysAmountAverageLessThan" v-if="permissions.includes('admin-access vends')" @keyup.enter="onSearchFilterUpdated()">
                    Avg/Day Sales (30 Days) &lt;&lt;
                </SearchInput>
                <div>
                    <label for="text" class="block text-sm font-medium text-gray-700">
                        Is MQTT?
                    </label>
                    <MultiSelect
                        v-model="filters.is_mqtt"
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
                        Is MQTT Active?
                    </label>
                    <MultiSelect
                        v-model="filters.is_mqtt_active"
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

            <div class="flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5">
                <div class="mt-3">
                    <div class="flex flex-col space-y-1 md:flex-row md:space-y-0 md:space-x-1">
                        <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        @click="onSearchFilterUpdated()"
                        >
                            <MagnifyingGlassIcon class="h-4 w-4" aria-hidden="true"/>
                            <span>
                                Search
                            </span>
                        </Button>
                        <Button class="md:hidden inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        @click="onShowAllFiltersClicked()"
                        >
                            <span v-if="!showAllFilters" class="flex">
                                <ChevronDoubleDownIcon class="h-4 w-4" aria-hidden="true"/>
                                Show
                            </span>
                            <span v-if="showAllFilters" class="flex">
                                <ChevronDoubleUpIcon class="h-4 w-4" aria-hidden="true"/>
                                Hide
                            </span>
                            <span>
                                All Filters
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
                        <Button type="button" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-400 hover:bg-gray-100"
                        @click="onExportChannelExcelClicked()"
                        v-if="permissions.includes('export excel')">
                            <div class="flex space-x-1">
                                <div>
                                    <ArrowDownTrayIcon v-if="!loading" class="h-4 w-4" aria-hidden="true"/>
                                    <svg v-if="loading" aria-hidden="true" class="mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                                        <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                                    </svg>
                                </div>
                                <span>
                                    Export Channels Excel
                                </span>
                            </div>
                        </Button>
                        <!-- <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-yellow-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        @click="onCreateClicked()"
                        v-if="permissions.includes('admin-access vends')"
                        >
                            <PlusCircleIcon class="h-4 w-4" aria-hidden="true"/>
                            <span>
                                New Machine
                            </span>
                        </Button> -->
                        <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-blue-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        @click="onSyncNextDeliveryDate()"
                        v-if="permissions.includes('admin-access vends')"
                        >
                            <div class="flex space-x-1">
                                <div>
                                    <ArrowPathIcon  v-if="!loadingSyncNextDeliveryDate" class="h-4 w-4" aria-hidden="true"/>
                                    <svg v-if="loadingSyncNextDeliveryDate" aria-hidden="true" class="mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                                        <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                                    </svg>
                                </div>
                                <span>
                                    Sync Next Delivery Date
                                </span>
                            </div>
                        </Button>
                    </div>
                </div>
                <div class="flex flex-col space-y-1">
                    <span class="text-sm text-gray-700 leading-5">
                        <p>Last loaded: {{ now }}</p>
                    </span>
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
            <dl class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div class="overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow md:block" :class="[showAllFilters ? 'block' : 'hidden']">
                    <dt class="truncate text-sm font-medium text-gray-500">Total Sales (Last 30 days)</dt>
                    <dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
                        {{totals['thirtyDays'].toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
                    </dd>
                </div>
                <div class="overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow md:block" :class="[showAllFilters ? 'block' : 'hidden']">
                    <dt class="truncate text-sm font-medium text-gray-500">Avg per VM (Last 30 days)</dt>
                    <dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
                        {{(totals['thirtyDays']/vends.meta.to ? totals['thirtyDays']/vends.meta.to : 0).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
                    </dd>
                </div>
                <div class="overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow md:block" :class="[showAllFilters ? 'block' : 'hidden']">
                    <dt class="truncate text-sm font-medium text-gray-500">Avg per Day per VM (Last 30 days)</dt>
                    <dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
                        {{(totals['thirthyDaysAvg']/vends.meta.to ? totals['thirthyDaysAvg']/vends.meta.to : 0).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
                    </dd>
                </div>
            </dl>
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
                            <TableHeadSort modelName="vends.code" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vends.code')">
                                ID
                            </TableHeadSort>
                            <TableHead>
                                Name
                            </TableHead>
                            <TableHeadSort modelName="temp" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('temp')">
                                T1&#8451;(freezer)
                                <br>
                                T2&#8451;(evap)
                                <br>
                                &Delta;T1-T2
                            </TableHeadSort>
                            <TableHead>
                                Inventory Status <br>
                                (#Channel, Sold, Balance/Capacity)
                            </TableHead>
                            <TableHead>
                                Errors
                            </TableHead>
                            <TableHeadSort modelName="balance_percent" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('balance_percent')">
                                Balance Stock
                                <!-- <span class="flex justify-center" data-tooltip-target="balance-stock" data-tooltip-style="light">
                                    <QuestionMarkCircleIcon class="w-4 h-4"></QuestionMarkCircleIcon>
                                    <div id="balance-stock" role="tooltip" class="absolute z-10 invisible inline-block px-3 py-2 text-sm font-medium text-gray-900 bg-white border border-gray-200 rounded-lg shadow-sm opacity-0 tooltip">
                                        Tooltip content
                                        <div class="tooltip-arrow" data-popper-arrow></div>
                                    </div>
                                </span> -->
                            </TableHeadSort>
                            <TableHeadSort modelName="out_of_stock_sku_percent" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('out_of_stock_sku_percent')">
                                Remaining SKU#
                            </TableHeadSort>
                            <TableHead>
                                Sales(qty)
                                <SingleSortItem modelName="vend_transaction_totals_json->today_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_transaction_totals_json->today_amount', true)">
                                    Today
                                </SingleSortItem>
                                <SingleSortItem modelName="vend_transaction_totals_json->yesterday_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_transaction_totals_json->yesterday_amount', true)">
                                    Y'day
                                </SingleSortItem>
                                <SingleSortItem modelName="vend_transaction_totals_json->seven_days_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_transaction_totals_json->seven_days_amount', true)">
                                    Last7d
                                </SingleSortItem>
                                <SingleSortItem modelName="vend_transaction_totals_json->thirty_days_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_transaction_totals_json->thirty_days_amount', true)">
                                    Last30d
                                </SingleSortItem>
                            </TableHead>
                            <TableHead>
                                Status
                            </TableHead>
                            <TableHeadSort modelName="last_invoice_date" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('last_invoice_date')">
                                Last Visited
                            </TableHeadSort>
                            <TableHeadSort modelName="next_invoice_date" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('next_invoice_date')">
                                Next Planned Visit
                            </TableHeadSort>
                            <TableHeadSort modelName="virtual_vend_records_thirty_days_amount_average" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('virtual_vend_records_thirty_days_amount_average', true)">
                                Avg Per Day <br>
                                (Last 30 days)
                            </TableHeadSort>
                            <TableHeadSort modelName="amount_average_day" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('amount_average_day', true)">
                                Lifetime Sales,<br>
                                Begin Date, <br>
                                Avg Sales/ Day
                            </TableHeadSort>
                            <!-- <TableHeadSort modelName="parameter_json->t2" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('parameter_json->t2')">
                                Temp2 <br>
                                (Evap)<br>
                                &#8451;
                            </TableHeadSort> -->
                            <TableHeadSort modelName="postcode" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('postcode')">
                                Postcode
                            </TableHeadSort>
                            <TableHead>
                                Firmware Ver
                            </TableHead>
                            <TableHeadSort modelName="location_type_name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('location_type_name')">
                                Location
                            </TableHeadSort>
                            <TableHead>
                            </TableHead>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
                        <tr v-for="(vend, vendIndex) in vends.data" :key="vendIndex"
                            class="divide-x divide-gray-200">
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                {{ vends.meta.from + vendIndex }}
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                <Link :href="'/settings/vend/' + vend.id + '/update'" class="text-blue-600">
                                {{ vend.code }}
                                </Link>
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-left">
                                <span v-if="vend.customer_json">
                                    <span v-if="permissions.includes('admin-access vends')">
                                        <a class="text-blue-700" target="_blank" :href="'//admin.happyice.com.sg/person/' + vend.customer_json.id + '/edit'">
                                            {{ vend.customer_json.prefix }}-{{ vend.customer_json.code }}
                                            <br>
                                            ({{ vend.customer_json.cust_id }})
                                            <br>
                                            {{ vend.customer_name }}
                                        </a>
                                    </span>
                                    <span v-else>
                                        {{ vend.customer_json.prefix }}-{{ vend.customer_json.code }}
                                        <br>
                                        ({{ vend.customer_json.cust_id }})
                                        <br>
                                        {{ vend.customer_name }}
                                    </span>
                                </span>
                                <span v-else-if="!vend.customer_json">
                                    <span v-if="permissions.includes('admin-access vends')">
                                        <a class="text-blue-700" target="_blank" :href="'//admin.happyice.com.sg/person/' + vend.customer_person_id + '/edit'">
                                            {{ vend.customer_code }} <br>
                                            {{ vend.customer_name }}
                                        </a>
                                    </span>
                                    <span v-else>
                                        {{ vend.customer_code }} <br>
                                        {{ vend.customer_name }}
                                    </span>
                                </span>
                                <span v-else>
                                    {{ vend.name }}
                                </span>
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                <div class="flex flex-col items-center space-y-1">
                                    <button
                                        type="button"
                                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center"
                                        :class="[vend.is_online ? (vend.temp > -15 ? 'bg-red-400 active:bg-red-500 hover:bg-red-600' : 'bg-green-400 active:bg-green-500 hover:bg-green-600') : 'bg-gray-300 active:bg-gray-500 hover:bg-gray-600']"
                                        @click="onVendTempClicked(vend.id, 1)"
                                        v-if="vend.temp_updated_at"
                                    >
                                        {{ vend.is_temp_error ? 'Error' : vend.temp }}
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center"
                                        :class="[vend.is_online ? (vend.temp > -15 || vend.parameterJson['t2'] == constTempError ? 'bg-red-400 active:bg-red-500 hover:bg-red-600' : 'bg-green-400 active:bg-green-500 hover:bg-green-600') : 'bg-gray-300 active:bg-gray-500 hover:bg-gray-600']"
                                        @click="onVendTempClicked(vend.id, 2)"
                                        v-if="vend.parameterJson && 't2' in vend.parameterJson"
                                    >
                                        {{ vend.parameterJson['t2'] == constTempError ? 'Error' : vend.parameterJson['t2']/10 }}(t2)
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center"
                                        :class="[vend.is_online ? (vend.temp > -15 || vend.parameterJson['t3'] == constTempError ? 'bg-red-400 active:bg-red-500 hover:bg-red-600' : 'bg-green-400 active:bg-green-500 hover:bg-green-600') : 'bg-gray-300 active:bg-gray-500 hover:bg-gray-600']"
                                        @click="onVendTempClicked(vend.id, 3)"
                                        v-if="vend.parameterJson && vend.parameterJson['t3'] && vend.parameterJson['t3'] != constTempError"
                                    >
                                        {{ vend.parameterJson['t3'] == constTempError ? 'Error' : vend.parameterJson['t3']/10 }}(t3)
                                    </button>
                                    <button
                                        type="button"
                                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center"
                                        :class="[vend.is_online ? (vend.temp > -15 || vend.parameterJson['t4'] == constTempError ? 'bg-red-400 active:bg-red-500 hover:bg-red-600' : 'bg-green-400 active:bg-green-500 hover:bg-green-600') : 'bg-gray-300 active:bg-gray-500 hover:bg-gray-600']"
                                        @click="onVendTempClicked(vend.id, 4)"
                                        v-if="vend.parameterJson && vend.parameterJson['t4'] && vend.parameterJson['t4'] != constTempError"
                                    >
                                        {{ vend.parameterJson['t4'] == constTempError ? 'Error' : vend.parameterJson['t4']/10 }}(t4)
                                    </button>
                                    <span class="mt-1">
                                        {{ vend.temp_updated_at }}
                                    </span>
                                    <span
                                        class="mt-1"
                                        :class="(vend.temp - vend.parameterJson['t2']/10).toFixed(1) >= 4 ? 'text-red-700' : 'text-green-700'"
                                        v-if="vend.parameterJson && vend.parameterJson['t2'] && vend.parameterJson['t2'] != constTempError && !vend.is_temp_error"
                                    >
                                        {{ (vend.temp - vend.parameterJson['t2']/10).toFixed(1) }}
                                    </span>
                                </div>
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-left">
                                <ul
                                class="sm:grid sm:grid-cols-[105px_minmax(110px,_1fr)_100px] hover:cursor-pointer"
                                v-if="vend.vendChannelsJson"
                                @click="onChannelOverviewClicked(vend)"
                                >
                                    <li v-for="(channel, channelIndex) in vend.vendChannelsJson"
                                        class="quick-look"
                                        :class="[channelIndex > 0 && (String(channel['code'])[0] !== String(vend.vendChannelsJson[channelIndex - 1]['code'])[0]) ? 'col-start-1' : '']"
                                    >
                                    <span :class="[channelIndex > 0 && (String(channel['code'])[0] !== String(vend.vendChannelsJson[channelIndex - 1]['code'])[0]) ? 'border-t-4 pt-1' : '']">
                                        <span>
                                            #{{channel['code']}},
                                        </span>
                                        <span class="text-blue-600">
                                            {{channel['capacity'] - channel['qty']}},
                                        </span>
                                        <span :class="[channel['qty'] <= 2 ? 'text-red-700' : 'text-green-700']">
                                            {{channel['qty']}}/{{channel['capacity']}}
                                        </span>
                                    </span>
                                    </li>
                                </ul>
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                <span v-for="vendChannelErrorLog in vend.vendChannelErrorLogsJson" class="inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border"
                                :class="[
                                    vendChannelErrorLog['vendChannelError'] ?
                                    (
                                        vendChannelErrorLog['vendChannelError']['code'] == 4 ||
                                        vendChannelErrorLog['vendChannelError']['code'] == 5 ||
                                        vendChannelErrorLog['vendChannelError']['code'] == 7 ?
                                        'bg-blue-100 text-blue-800' :
                                        'bg-red-100 text-red-800'
                                    ) :
                                    (
                                        vendChannelErrorLog['vend_channel']['code'] == 4 ||
                                        vendChannelErrorLog['vend_channel']['code'] == 5 ||
                                        vendChannelErrorLog['vend_channel']['code'] == 7 ?
                                        'bg-blue-100 text-blue-800' :
                                        'bg-red-100 text-red-800'
                                    )]">
                                    <div class="flex flex-col">
                                        <div>
                                            #{{vendChannelErrorLog['vendChannel'] ? vendChannelErrorLog['vendChannel']['code'] : vendChannelErrorLog['vend_channel']['code']}},
                                            <span class="font-bold">
                                            ({{ vendChannelErrorLog['vendChannelError'] ? vendChannelErrorLog['vendChannelError']['code'] : vendChannelErrorLog['vend_channel_error']['code'] }})
                                            </span>
                                        </div>
                                        <div>
                                            {{vendChannelErrorLog['created_at']}}
                                        </div>
                                    </div>
                                </span>
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                <span
                                    v-if="vend.vendChannelTotalsJson"
                                    :class="[vend.balance_percent <= 20 ? 'text-red-700' : (vend.balance_percent > 50 ? 'text-green-700' : 'text-blue-700')]"
                                >
                                    {{ vend.vendChannelTotalsJson['qty'] }}/ {{ vend.vendChannelTotalsJson['capacity'] }} <br>
                                    ({{ vend.balance_percent }}%)
                                </span>
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                <span
                                    v-if="vend.vendChannelTotalsJson"
                                    :class="[100 - vend.out_of_stock_sku_percent <= 40 ? 'text-red-700' : (100 - vend.out_of_stock_sku_percent > 70 ? 'text-green-700' : 'text-blue-700')]"
                                >
                                    {{ vend.vendChannelTotalsJson['count'] - vend.vendChannelTotalsJson['outOfStockSku'] }}/ {{ vend.vendChannelTotalsJson['count'] }} <br>
                                    ({{ 100 - vend.out_of_stock_sku_percent }}%)
                                </span>
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                <span
                                v-if="vend.vendTransactionTotalsJson && 'today_amount' in vend.vendTransactionTotalsJson"
                                :class="[
                                    (vend.vendTransactionTotalsJson['today_amount']/ (Math.pow(10, operatorCountry.currency_exponent))) >= 30 ? 'text-green-700' : 'text-red-700'
                                ]">
                                    {{ operatorCountry.currency_symbol }}{{(vend.vendTransactionTotalsJson['today_amount'] / (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
                                    ({{vend.vendTransactionTotalsJson['today_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}})
                                </span>
                                <span
                                v-if="vend.vendTransactionTotalsJson && 'yesterday_amount' in vend.vendTransactionTotalsJson"
                                :class="[
                                    (vend.vendTransactionTotalsJson['yesterday_amount']/ (Math.pow(10, operatorCountry.currency_exponent))) >= 30 ? 'text-green-700' : 'text-red-700'
                                ]">
                                    <br>
                                    {{ operatorCountry.currency_symbol }}{{(vend.vendTransactionTotalsJson['yesterday_amount']/ (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
                                    ({{vend.vendTransactionTotalsJson['yesterday_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}})
                                </span>
                                <span
                                v-if="vend.vendTransactionTotalsJson && 'seven_days_amount' in vend.vendTransactionTotalsJson"
                                :class="[
                                    (vend.vendTransactionTotalsJson['seven_days_amount']/ (Math.pow(10, operatorCountry.currency_exponent))) > 200 ? 'text-green-700' : 'text-red-700'
                                ]">
                                    <br>
                                    {{ operatorCountry.currency_symbol }}{{(vend.vendTransactionTotalsJson['seven_days_amount']/ (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}({{vend.vendTransactionTotalsJson['seven_days_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}})
                                </span>
                                <span
                                v-if="vend.vendTransactionTotalsJson && 'thirty_days_amount' in vend.vendTransactionTotalsJson"
                                :class="[
                                    (vend.vendTransactionTotalsJson['thirty_days_amount']/ (Math.pow(10, operatorCountry.currency_exponent))) > 1000 ? 'text-green-700' : 'text-red-700'
                                ]">
                                    <br>
                                    {{ operatorCountry.currency_symbol }}{{(vend.vendTransactionTotalsJson['thirty_days_amount']/ (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}({{vend.vendTransactionTotalsJson['thirty_days_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}})
                                </span>
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                <div class="flex flex-col space-y-1">
                                    <div
                                        class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                                        :class="[vend.is_active ? 'bg-green-200' : 'bg-red-200']"
                                    >
                                        <div class="flex flex-col">
                                            <span class="font-bold">
                                                {{vend.is_active ? 'Active' : 'Inactive'}}
                                            </span>
                                        </div>

                                    </div>
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
                                        :class="[vend.parameterJson['Sensor'] % 2 == 0 ? 'bg-red-200' : 'bg-green-200']"
                                        v-if="vend.parameterJson"
                                    >
                                        <div class="flex flex-col">
                                            <span class="font-bold">
                                                Drop Sensor
                                            </span>
                                            <span>
                                                {{vend.parameterJson['Sensor'] % 2 == 0 ? 'Disabled' : 'Enabled'}}
                                            </span>
                                        </div>
                                    </div>
                                    <div
                                        class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full bg-green-200"
                                        v-if="vend.parameterJson && 'fan' in vend.parameterJson"
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
                                                {{(vend.parameterJson['CoinCnt']/ (Math.pow(10, operatorCountry.currency_exponent))).toFixed(2)}}
                                            </span>
                                        </div>
                                    </div>
                                    <div
                                        class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                                        :class="[vend.is_mqtt_active ? 'bg-green-200' : 'bg-gray-200']"
                                        v-if="vend.is_mqtt"
                                    >
                                        <div class="flex flex-col">
                                            <span class="font-bold">
                                                MQTT
                                            </span>
                                            <span v-if="vend.mqtt_last_updated_at">
                                                {{ vend.mqtt_last_updated_at }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                <span v-if="vend.cms_invoice_history && 'last_delivery_driver' in vend.cms_invoice_history">
                                    {{ vend.cms_invoice_history['last_delivery_driver'] }} <br>
                                </span>
                                <span>
                                    {{ vend.last_invoice_date }} <br>
                                    {{ vend.last_invoice_diff }}
                                </span>
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                <span>
                                    {{ vend.next_invoice_date }} <br>
                                    {{ vend.next_invoice_diff }}
                                </span>
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                <span :class="[vend.virtual_vend_records_thirty_days_amount_average >= vend.vendTransactionTotalsJson['vend_records_amount_average_day']/100 ? 'text-green-700' : 'text-red-700']">
                                    {{ operatorCountry.currency_symbol }}{{ vend.virtual_vend_records_thirty_days_amount_average.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                                </span>
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                <span
                                v-if="vend.vendTransactionTotalsJson && 'vend_records_amount_latest' in vend.vendTransactionTotalsJson"
                                >
                                {{ operatorCountry.currency_symbol }}{{(vend.vendTransactionTotalsJson['vend_records_amount_latest'] / (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0})}}
                                </span>
                                <span
                                v-if="vend.begin_date"
                                >
                                    <br>
                                    {{ vend.begin_date_short }}
                                </span>
                                <br>
                                <span
                                v-if="vend.vendTransactionTotalsJson && 'vend_records_amount_average_day' in vend.vendTransactionTotalsJson"
                                :class="getVendRecordsAmountAverageDayClass(vend.vendTransactionTotalsJson['vend_records_amount_average_day'])"
                                >
                                    {{ operatorCountry.currency_symbol }}{{(vend.vendTransactionTotalsJson['vend_records_amount_average_day'] / (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
                                </span>
                            </TableData>

                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                {{ vend.postcode }}
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                {{ vend.parameterJson && vend.parameterJson['Ver'] ? vend.parameterJson['Ver'].toString(16) : null }}
                                <span class="text-blue-600" v-if="vend.apkVerJson && 'apkver' in vend.apkVerJson">
                                    <br>Apk: {{ vend.apkVerJson['apkver'] }}
                                    <span v-if="vend.apkVerJson && 'buildtime' in vend.apkVerJson">
                                        {{ moment(new Date(vend.apkVerJson['buildtime'])).format('YYMMDD HH:mm:ss')  }}
                                    </span>
                                </span>
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                {{ vend.location_type_name }}
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                <div class="flex justify-center space-x-1">
                                    <!-- <Button
                                        type="button" class="bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
                                        @click="onEditClicked(vend)"
                                    >
                                        <PencilSquareIcon class="w-4 h-4"></PencilSquareIcon>
                                        <span>
                                            Edit
                                        </span>
                                    </Button> -->
                                    <Link :href="'/vends/' + vend.id + '/edit'">
                                        <Button
                                        type="button" class="bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
                                        >
                                        <PencilSquareIcon class="w-4 h-4"></PencilSquareIcon>
                                        <span>
                                            Edit
                                        </span>
                                        </Button>
                                    </Link>
                                </div>
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
    <ChannelOverview
        v-if="showChannelOverviewModal"
        :productOptions="productOptions"
        :vend="vend"
        :showModal="showChannelOverviewModal"
        @modalClose="onChannelOverviewClosed"
    >
    </ChannelOverview>
    <Form
        v-if="showEditModal"
        :vend="vend"
        :type="type"
        :showModal="showEditModal"
        :permissions="permissions"
        @modalClose="onModalClose"
    >
    </Form>
    <Create
        v-if="showCreateModal"
        :showModal="showCreateModal"
        :permissions="permissions"
        :type="type"
        @modalClose="onCreateModalClose"

    >
    </Create>
    </BreezeAuthenticatedLayout>
  </template>

<style setup>
	.quick-look
	{
		-webkit-border-horizontal-spacing: 0px;
		-webkit-border-image: none;
		-webkit-border-vertical-spacing: 0px;
		border-bottom-color: white;
		border-bottom-left-radius: 3px;
		border-bottom-right-radius: 3px;
		border-bottom-style: none;
		border-width: 0px;
		border-collapse: separate;
		border-left-color: white;
		border-left-style: none;
		border-right-color: white;
		border-right-style: none;
		border-top-color: white;
		border-top-left-radius: 3px;
		border-top-right-radius: 3px;
		border-top-style: none;
		line-height: 14px;
		max-width: none;
		text-align: left;
		vertical-align: baseline;
		white-space: nowrap;
		padding:5px;
		margin:3px;
		display:block;
		float:left;
		/* width:170px; */
		font-size:13px;
	}
</style>

  <script setup>
  import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
  import Button from '@/Components/Button.vue';
  import ChannelOverview from '@/Pages/Vend/ChannelOverview.vue';
  import Create from '@/Pages/Vend/Create.vue';
  import Form from '@/Pages/Vend/Form.vue';
  import Paginator from '@/Components/Paginator.vue';
  import SearchInput from '@/Components/SearchInput.vue';
  import MultiSelect from '@/Components/MultiSelect.vue';
  import { ArrowDownTrayIcon, ArrowPathIcon, ChevronDoubleDownIcon, ChevronDoubleUpIcon, MagnifyingGlassIcon, BackspaceIcon, PlusCircleIcon, PencilSquareIcon} from '@heroicons/vue/20/solid';
  import TableHead from '@/Components/TableHead.vue';
  import TableData from '@/Components/TableData.vue';
  import TableHeadSort from '@/Components/TableHeadSort.vue';
  import SingleSortItem from '@/Components/SingleSortItem.vue';
  import { ref, onMounted } from 'vue';
  import { router, Link } from '@inertiajs/vue3';
  import { Head, usePage } from '@inertiajs/vue3';
  import moment from 'moment';
  import axios from 'axios';

  const props = defineProps({
    categories: Object,
    categoryGroups: Object,
    constTempError: Number,
    locationTypeOptions: Object,
    operatorOptions: Object,
    productOptions: Object,
    totals: [Array, Object],
    vends: Object,
    vendChannelErrors: Object,
  })

  const filters = ref({
    codes: '',
    channel_codes: '',
    serialNum: '',
    customer: '',
    categories: [],
    categoryGroups: [],
    errors: [],
    locationType: '',
    operator: '',
    is_active: true,
    is_binded_customer: '',
    tempHigherThan: '',
    tempDeltaHigherThan: '',
    vend_channel_error_id: '',
    lastVisitedGreaterThan: '',
    is_mqtt: '',
    is_mqtt_active: '',
    is_online: '',
    is_sensor: '',
    is_door_open: '',
    fanSpeedLowerThan: '',
    balanceStockLessThan: '',
    remainingSkuLessThan: '',
    virtual_apk_ver: '',
    virtual_firmware_ver: '',
    vendRecordsThirtyDaysAmountAverageLessThan: '',
    sortKey: '',
    sortBy: true,
    numberPerPage: '',
    visited: true,
  })

  const booleanOptions = ref([])
  const booleanStrictOptions = ref([])
  const categoryOptions = ref([])
  const categoryGroupOptions = ref([])
  const doorOptions = ref([])
  const enableOptions = ref([])
  const loading = ref(false)
  const loadingSyncNextDeliveryDate = ref(false)
  const locationTypeOptions = ref([])
  const numberPerPageOptions = ref([])
  const operatorOptions = ref([])
  const showAllFilters = ref(false)
  const showChannelOverviewModal = ref(false)
  const showCreateModal = ref(false)
  const showEditModal = ref(false)
  const type = ref('')
  const vend = ref()
  const vendChannelErrorsOptions = ref([])
//   const vendOptions = ref([])
  const operatorCountry = usePage().props.auth.operatorCountry
  const operatorRole = usePage().props.auth.operatorRole
  const permissions = usePage().props.auth.permissions
  const roles = usePage().props.auth.roles
  const initBinded = usePage().props.initBinded
  const now = ref(moment().format('HH:mm:ss'))

  onMounted(() => {
    filters.value.visited = true
    vendChannelErrorsOptions.value = [
        // {'id': '', 'desc': 'All'},
        {'id': 'errors_only', 'desc': 'Errors Only'},
        ...props.vendChannelErrors.data
    ]
    numberPerPageOptions.value = [
        { id: 50, value: 50 },
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
    booleanStrictOptions.value = [
        {id: 'true', value: 'Yes'},
        {id: 'false', value: 'No'},
    ]
    enableOptions.value = [
        {id: 'all', value: 'All'},
        {id: 'true', value: 'Enabled'},
        {id: 'false', value: 'Disabled'},
    ]
    doorOptions.value = [
        {id: 'all', value: 'All'},
        {id: 'open', value: 'Open'},
        {id: 'close', value: 'Close'},
    ]
    locationTypeOptions.value = [
        {id: 'all', value: 'All'},
        ...props.locationTypeOptions.data.map((data) => {return {id: data.id, value: data.name}})
    ]
    operatorOptions.value = [
        {id: 'all', full_name: 'All'},
        ...props.operatorOptions.data.map((data) => {return {id: data.id, full_name: data.full_name}})
    ]

    filters.value.is_active = booleanOptions.value[1]
    filters.value.is_door_open = doorOptions.value[0]
    filters.value.is_mqtt = booleanOptions.value[0]
    filters.value.is_mqtt_active = booleanOptions.value[0]
    filters.value.is_online = booleanOptions.value[0]
    filters.value.is_sensor = enableOptions.value[0]
    // console.log(initBinded, roles[0])
    filters.value.is_binded_customer = initBinded && (roles[0] == 'superadmin' || roles[0] == 'admin' ||  roles[0] == 'supervisor' || roles[0] == 'driver') ? booleanOptions.value[1] : booleanOptions.value[0]
    filters.value.locationType = locationTypeOptions.value[0]
    filters.value.operator = operatorOptions.value[0]
    // vendOptions.value = props.vendOptions.data.map((vend) => {return {id: vend.id, code: vend.code}})
  })

    function getVendRecordsAmountAverageDayClass(amount) {
        if(amount >= 3000) {
            return 'text-green-700'
        } else if(amount >= 2000 && amount < 3000) {
            return 'text-blue-700'
        } else if(amount >= 1500 && amount < 2000) {
            return 'text-gray-700'
        } else if(amount >= 1000 && amount < 1500) {
            return 'text-red-700'
        }else {
            return 'text-gray-700 bg-red-300 px-1 rounded-sm'
        }
    }

    function onChannelOverviewClicked(vendData) {
        vend.value = vendData
        showChannelOverviewModal.value = true
    }

    function onChannelOverviewClosed() {
        showChannelOverviewModal.value = false
    }

    function onCreateClicked() {
        type.value = 'create'
        vend.value = null
        showCreateModal.value = true
    }

    function onCreateModalClose() {
        showCreateModal.value = false
    }

    function onEditClicked(vendData) {
        type.value = 'edit'
        vend.value = vendData
        showEditModal.value = true
    }

    function onModalClose() {
        showEditModal.value = false
    }

    function onShowAllFiltersClicked() {
        showAllFilters.value = !showAllFilters.value
    }

    function onSearchFilterUpdated() {

        router.get('/vends', {
            ...filters.value,
            categories: filters.value.categories.map((category) => { return category.id }),
            categoryGroups: filters.value.categoryGroups.map((categoryGroup) => { return categoryGroup.id }),
            errors: filters.value.errors.map((error) => { return error.id }),
            location_type_id: filters.value.locationType.id,
            operator_id: filters.value.operator.id,
            is_active: filters.value.is_active.id,
            is_binded_customer: filters.value.is_binded_customer.id,
            is_door_open: filters.value.is_door_open.id,
            is_mqtt: filters.value.is_mqtt.id,
            is_mqtt_active: filters.value.is_mqtt_active.id,
            is_online: filters.value.is_online.id,
            is_sensor: filters.value.is_sensor.id,
            numberPerPage: filters.value.numberPerPage.id,
        }, {
            preserveState: true,
            replace: true,
            onFinish: visit => {
                now.value = moment().format('HH:mm:ss')
            },
        })
    }

    function onSyncNextDeliveryDate() {
        loadingSyncNextDeliveryDate.value = true
        axios({
            method: 'get',
            url: '/customers/sync-next-delivery-date',
        }).then(response => {
        }).catch(error => {
        }).finally(() => {
            loadingSyncNextDeliveryDate.value = false
        })
    }

    function onVendTempClicked(vendId, type) {
        router.get('/vends/' + vendId + '/temp/' + type)
    }

    function resetFilters() {
        router.get('/vends')
    }

  function sortTable(sortKey, inverse = false) {
    filters.value.sortBy = !filters.value.sortBy
    if(inverse && filters.value.sortKey != sortKey) {
        filters.value.sortBy = !filters.value.sortBy
    }
    filters.value.sortKey = sortKey
    onSearchFilterUpdated()
  }

function onExportChannelExcelClicked() {
    loading.value = true
    axios({
        method: 'get',
        url: '/vends/channels/excel',
        params: {
            ...filters.value,
            categories: filters.value.categories.map((category) => { return category.id }),
            categoryGroups: filters.value.categoryGroups.map((categoryGroup) => { return categoryGroup.id }),
            errors: filters.value.errors.map((error) => { return error.id }),
            location_type_id: filters.value.locationType.id,
            operator_id: filters.value.operator.id,
            is_active: filters.value.is_active.id,
            is_binded_customer: filters.value.is_binded_customer.id,
            is_door_open: filters.value.is_door_open.id,
            is_mqtt: filters.value.is_mqtt.id,
            is_mqtt_active: filters.value.is_mqtt_active.id,
            is_online: filters.value.is_online.id,
            is_sensor: filters.value.is_sensor.id,
        },
        responseType: 'blob',
    }).then(response => {
        fileDownload(response.data, 'Vending_Channels_' + moment().format('YYMMDDhhmmss') +'.xlsx')
    }).catch(error => {
        console.log(error)
    }).finally(() => {
        loading.value = false
    })
}


  </script>