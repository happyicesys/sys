
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
<!--
                <div>
                    <label for="text" class="block text-sm font-medium text-gray-700">
                        Vend ID
                    </label>
                    <MultiSelect
                        v-model="filters.codes"
                        :options="vendOptions"
                        valueProp="id"
                        label="code"
                        mode="tags"
                        placeholder="Select"
                        open-direction="bottom"
                        class="mt-1"
                    >
                    </MultiSelect>
                </div> -->
                <SearchInput placeholderStr="Vend ID" v-model="filters.codes">
                    Vend ID
                    <span class="text-[9px]">
                        ("," for multiple)
                    </span>
                </SearchInput>
                <SearchInput placeholderStr="Channel ID" v-model="filters.channel_codes">
                    Channel ID
                    <span class="text-[9px]">
                        ("," for multiple)
                    </span>
                </SearchInput>
                <SearchInput placeholderStr="Serial Num" v-model="filters.serialNum">
                    Serial Num
                </SearchInput>
                <SearchInput placeholderStr="Number" v-model="filters.tempHigherThan">
                    Temp &gt;&gt;
                </SearchInput>
                <SearchInput placeholderStr="Number" v-model="filters.tempDeltaHigherThan">
                    t1-t2 Delta &gt;&gt;
                </SearchInput>
                <div>
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
                    <!-- <label for="text" class="block text-sm font-medium text-gray-700">
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
                    </MultiSelect> -->
                </div>
                <SearchInput placeholderStr="Cust ID" v-model="filters.customer_code" v-if="!operatorRole">
                    Cust ID
                </SearchInput>
                <SearchInput placeholderStr="Cust Name" v-model="filters.customer_name" v-if="!operatorRole">
                    Cust Name
                </SearchInput>
                <div v-if="!operatorRole">
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
                <div v-if="!operatorRole">
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
                <div v-if="!operatorRole">
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
                </div>
                <div>
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
                <div>
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
                <SearchInput placeholderStr="Fan Speed" v-model="filters.fanSpeedLowerThan">
                    Fan Speed &lt;&lt;
                </SearchInput>
                <div v-if="!operatorRole">
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
                        <Button class="inline-flex space-x-1 items-center rounded-md border border-gray-500 bg-white px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        @click="onExportChannelExcelClicked()"
                        >
                            <ArrowDownTrayIcon v-if="!loading" class="h-4 w-4" aria-hidden="true"/>
                            <svg v-if="loading" aria-hidden="true" class="mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                                <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                            </svg>
                            <span>
                                Export Channels Excel
                            </span>
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
                                Temp1(&#8451;)
                                <br>
                                &Delta;t1-t2
                            </TableHeadSort>
                            <TableHead>
                                Inventory Status <br>
                                (#Channel, Sales, Balance/Capacity)
                            </TableHead>
                            <TableHead>
                                Errors
                            </TableHead>
                            <TableHeadSort modelName="vend_channel_totals_json->balancePercent" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_channel_totals_json->balancePercent')">
                                Balance Stock
                            </TableHeadSort>
                            <TableHeadSort modelName="vend_channel_totals_json->outOfStockSkuPercent" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_channel_totals_json->outOfStockSkuPercent')">
                                Out of Stock SKU
                            </TableHeadSort>
                            <TableHeadSort modelName="vend_transaction_totals_json->seven_days_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_transaction_totals_json->seven_days_amount')">
                                $ Sales (qty)<br>
                                Today <br>
                                Yesterday<br>
                                Last 7 Days <br>
                                Last 30 Days
                            </TableHeadSort>
                            <TableHead>
                                Status
                            </TableHead>
                            <TableHeadSort modelName="parameter_json->t2" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('parameter_json->t2')">
                                Temp2 <br>
                                (Evap)<br>
                                &#8451;
                            </TableHeadSort>
                            <TableHeadSort modelName="postcode" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('postcode')">
                                Postcode
                            </TableHeadSort>
                            <TableHead>
                                Firmware Ver
                            </TableHead>
                            <TableHead>
                                Serial Num
                            </TableHead>
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
                                {{ vend.code }}
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-left">
                                <span v-if="vend.latestVendBinding && vend.latestVendBinding.customer">
                                    <span v-if="!operatorRole">
                                        <a class="text-blue-700" target="_blank" :href="'//admin.happyice.com.sg/person/vend-code/' + vend.code">
                                            {{ vend.latestVendBinding.customer.code }} <br>
                                            {{ vend.latestVendBinding.customer.name }}
                                        </a>
                                    </span>
                                    <span v-else>
                                        {{ vend.latestVendBinding.customer.code }} <br>
                                        {{ vend.latestVendBinding.customer.name }}
                                    </span>
                                </span>
                                <span v-else>
                                    {{ vend.name }}
                                </span>
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                <div class="flex flex-col items-center">
                                    <button
                                        type="button"
                                        class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center"
                                        :class="[vend.is_online ? (vend.temp > -15 ? 'bg-red-400 active:bg-red-500 hover:bg-red-600' : 'bg-green-400 active:bg-green-500 hover:bg-green-600') : 'bg-gray-300 active:bg-gray-500 hover:bg-gray-600']"
                                        @click="onVendTempClicked(vend.id, 1)"
                                    >
                                        {{ vend.is_temp_error ? 'Error' : vend.temp }}
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
                                    :class="[vend.vendChannelTotalsJson['balancePercent'] <= 30 ? 'text-red-700' : (vend.vendChannelTotalsJson['balancePercent'] > 60 ? '' : 'text-blue-700')]"
                                >
                                    {{ vend.vendChannelTotalsJson['qty'] }}/ {{ vend.vendChannelTotalsJson['capacity'] }} <br>
                                    ({{ vend.vendChannelTotalsJson['balancePercent'] }}%)
                                </span>
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                <span
                                    v-if="vend.vendChannelTotalsJson"
                                    :class="[vend.vendChannelTotalsJson['outOfStockSkuPercent'] > 40 ? 'text-red-700' : '']"
                                >
                                    {{ vend.vendChannelTotalsJson['outOfStockSku'] }}/ {{ vend.vendChannelTotalsJson['count'] }} <br>
                                    ({{ vend.vendChannelTotalsJson['outOfStockSkuPercent'] }}%)
                                </span>
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                <span
                                v-if="'today_amount' in vend.vendTransactionTotalsJson"
                                :class="[
                                    (vend.vendTransactionTotalsJson['today_amount']/ 100) >= 30 ? 'text-green-700' : 'text-red-700'
                                ]">
                                    {{(vend.vendTransactionTotalsJson['today_amount'] / 100).toLocaleString(undefined, {minimumFractionDigits: 2})}}
                                    ({{vend.vendTransactionTotalsJson['today_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}}) <br>
                                </span>
                                <span
                                v-if="'yesterday_amount' in vend.vendTransactionTotalsJson"
                                :class="[
                                    (vend.vendTransactionTotalsJson['yesterday_amount']/ 100) >= 30 ? 'text-green-700' : 'text-red-700'
                                ]">
                                    {{(vend.vendTransactionTotalsJson['yesterday_amount']/ 100).toLocaleString(undefined, {minimumFractionDigits: 2})}}
                                    ({{vend.vendTransactionTotalsJson['yesterday_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}}) <br>
                                </span>
                                <span
                                v-if="'seven_days_amount' in vend.vendTransactionTotalsJson"
                                :class="[
                                    (vend.vendTransactionTotalsJson['seven_days_amount']/ 100) > 200 ? 'text-green-700' : 'text-red-700'
                                ]">
                                    {{(vend.vendTransactionTotalsJson['seven_days_amount']/ 100).toLocaleString(undefined, {minimumFractionDigits: 2})}}({{vend.vendTransactionTotalsJson['seven_days_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}})
                                </span>
                                <span
                                v-if="'thirty_days_amount' in vend.vendTransactionTotalsJson"
                                :class="[
                                    (vend.vendTransactionTotalsJson['thirty_days_amount']/ 100) > 1000 ? 'text-green-700' : 'text-red-700'
                                ]">
                                    {{(vend.vendTransactionTotalsJson['thirty_days_amount']/ 100).toLocaleString(undefined, {minimumFractionDigits: 2})}}({{vend.vendTransactionTotalsJson['thirty_days_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}})
                                </span>
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                <!-- <div class="grid grid-cols-[90px_minmax(90px,_1fr)_90px] gap-1"> -->
                                <div class="flex flex-col space-y-1">
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
                                <div class="flex flex-col items-center space-y-1">
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
                                </div>
                            </TableData>

                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                {{ vend.latestVendBinding && vend.latestVendBinding.customer && vend.latestVendBinding.customer.deliveryAddress ? vend.latestVendBinding.customer.deliveryAddress.postcode : null }}
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                {{ vend.parameterJson['Ver'] ? vend.parameterJson['Ver'].toString(16) : null }}
                                <span class="text-blue-600" v-if="vend.apkVerJson && 'apkver' in vend.apkVerJson">
                                    <br>Apk: {{ vend.apkVerJson['apkver'] }}
                                    <span v-if="vend.apkVerJson && 'buildtime' in vend.apkVerJson">
                                        {{ moment(new Date(vend.apkVerJson['buildtime'])).format('YYMMDD HH:mm:ss')  }}
                                    </span>
                                </span>
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                {{ vend.serial_num }}
                            </TableData>
                            <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                                <div class="flex justify-center space-x-1">
                                    <Button
                                        type="button" class="bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
                                        @click="onEditClicked(vend)"
                                    >
                                        <PencilSquareIcon class="w-4 h-4"></PencilSquareIcon>
                                        <span>
                                            Edit
                                        </span>
                                    </Button>
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
        @modalClose="onModalClose"
    >
    </Form>
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
  import Form from '@/Pages/Vend/Form.vue';
  import Paginator from '@/Components/Paginator.vue';
  import SearchInput from '@/Components/SearchInput.vue';
  import MultiSelect from '@/Components/MultiSelect.vue';
  import { ArrowDownTrayIcon, MagnifyingGlassIcon, BackspaceIcon, PencilSquareIcon } from '@heroicons/vue/20/solid';
  import TableHead from '@/Components/TableHead.vue';
  import TableData from '@/Components/TableData.vue';
  import TableHeadSort from '@/Components/TableHeadSort.vue';
  import { ref, onMounted } from 'vue';
  import { Inertia } from '@inertiajs/inertia';
  import { Head, usePage } from '@inertiajs/inertia-vue3';
  import moment from 'moment';
  import axios from 'axios';

  const props = defineProps({
    categories: Object,
    categoryGroups: Object,
    constTempError: Number,
    operatorOptions: Object,
    vends: Object,
    // vendOptions: Object,
    vendChannelErrors: Object,
  })

  const filters = ref({
    codes: '',
    channel_codes: '',
    serialNum: '',
    customer_code: '',
    customer_name: '',
    categories: [],
    categoryGroups: [],
    errors: [],
    operator: '',
    is_binded_customer: '',
    tempHigherThan: '',
    tempDeltaHigherThan: '',
    vend_channel_error_id: '',
    is_online: '',
    is_sensor: '',
    is_door_open: '',
    fanSpeedLowerThan: '',
    sortKey: '',
    sortBy: false,
    numberPerPage: '',
  })

  const booleanOptions = ref([])
  const categoryOptions = ref([])
  const categoryGroupOptions = ref([])
  const doorOptions = ref([])
  const enableOptions = ref([])
  const loading = ref(false)
  const numberPerPageOptions = ref([])
  const operatorOptions = ref([])
  const showChannelOverviewModal = ref(false)
  const showEditModal = ref(false)
  const type = ref('')
  const vend = ref()
  const vendChannelErrorsOptions = ref([])
//   const vendOptions = ref([])
  const operatorRole = usePage().props.value.auth.operatorRole
  const roles = usePage().props.value.auth.user.roles
  const permissions = usePage().props.value.auth.user.permissions
  const now = ref(moment().format('HH:mm:ss'))
  const rolesCollection = ref([])
  const permissionsCollection = ref([])

  onMounted(() => {
    rolesCollection.value = roles ? roles.map((data) => {return data.name}) : ''
    permissionsCollection.value = permissions ? permissions.map((data) => {return data.name}) : ''
    vendChannelErrorsOptions.value = [
        // {'id': '', 'desc': 'All'},
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
    operatorOptions.value = [
        {
            id: 'all', full_name: 'All'
        },
        ...props.operatorOptions.data.map((data) => {return {id: data.id, full_name: data.full_name}})
    ]

    filters.value.is_door_open = doorOptions.value[0]
    filters.value.is_online = booleanOptions.value[0]
    filters.value.is_sensor = enableOptions.value[0]
    filters.value.is_binded_customer = operatorRole.value ? booleanOptions.value[0] : booleanOptions.value[1]
    filters.value.operator = operatorOptions.value[0]

    // vendOptions.value = props.vendOptions.data.map((vend) => {return {id: vend.id, code: vend.code}})
  })

    function onChannelOverviewClicked(vendData) {
        vend.value = vendData
        showChannelOverviewModal.value = true
    }

    function onChannelOverviewClosed() {
        showChannelOverviewModal.value = false
    }

    function onEditClicked(vendData) {
        vend.value = vendData
        showEditModal.value = true
    }

    function onModalClose() {
        showEditModal.value = false
    }

    function onSearchFilterUpdated() {
        Inertia.get('/vends', {
            ...filters.value,
            // codes: filters.value.codes.map((code) => { return code.id }),
            // vend_channel_error_id: filters.value.vend_channel_error_id.id,
            categories: filters.value.categories.map((category) => { return category.id }),
            categoryGroups: filters.value.categoryGroups.map((categoryGroup) => { return categoryGroup.id }),
            errors: filters.value.errors.map((error) => { return error.id }),
            operator_id: filters.value.operator.id,
            is_binded_customer: filters.value.is_binded_customer.id,
            is_door_open: filters.value.is_door_open.id,
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
            operator_id: filters.value.operator.id,
            is_binded_customer: filters.value.is_binded_customer.id,
            is_door_open: filters.value.is_door_open.id,
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