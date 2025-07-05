
<template>

  <Head title="VM Transactions" />

  <BreezeAuthenticatedLayout>
      <template #header>
          <h2 class="font-semibold text-xl text-gray-800 leading-tight">
              Vending Machines (Transactions)
          </h2>
      </template>

      <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
        <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
          <!-- <div class="flex flex-col md:flex-row md:space-x-3 space-y-1 md:space-y-0"> -->
          <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
            <div class="col-span-5 md:col-span-1">
                <SearchInput placeholderStr="Order ID" v-model="filters.order_id" @keyup.enter="onSearchFilterUpdated()">
                    Order ID
                </SearchInput>
            </div>
            <div class="col-span-5 md:col-span-1">
                <SearchInput placeholderStr="Machine ID" v-model="filters.codes" @keyup.enter="onSearchFilterUpdated()">
                    Machine ID
                    <span class="text-[9px]">
                        ("," for multiple)
                    </span>
                </SearchInput>
            </div>
            <div class="col-span-5 md:col-span-1">
                <SearchInput placeholderStr="Channel ID" v-model="filters.channel_codes" @keyup.enter="onSearchFilterUpdated()">
                    Channel ID
                    <span class="text-[9px]">
                        ("," for multiple)
                    </span>
                </SearchInput>
            </div>
            <div class="col-span-5 md:col-span-1">
                <DatePicker
                    v-model="filters.date_from"
                >
                    From
                </DatePicker>
            </div>
            <div class="col-span-5 md:col-span-1">
                <DatePicker
                    v-model="filters.date_to"
                    :minDate="filters.date_from"
                >
                    To
                </DatePicker>
            </div>
            <div class="col-span-5 md:col-span-1">
                <label for="text" class="block text-sm font-medium text-gray-700">
                    Channel Errors
                </label>
                <MultiSelect
                    v-model="filters.errors"
                    :options="vendChannelErrorOptions"
                    valueProp="id"
                    label="desc"
                    mode="tags"
                    placeholder="Select"
                    open-direction="bottom"
                    class="mt-1"
                >
                </MultiSelect>
            </div>
            <div class="col-span-5 md:col-span-1">
                <label for="text" class="block text-sm font-medium text-gray-700">
                   Payment Method
                </label>
                <MultiSelect
                    v-model="filters.paymentMethods"
                    :options="paymentMethodOptions"
                    valueProp="id"
                    label="name"
                    placeholder="Select"
                    open-direction="bottom"
                    class="mt-1"
                    mode="tags"
                >
                </MultiSelect>
            </div>
            <!-- <div class="col-span-5 md:col-span-1" v-if="permissions.includes('admin-access transactions')">
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
            <div class="col-span-5 md:col-span-1" v-if="permissions.includes('admin-access transactions')">
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
            </div> -->
            <!-- <div class="col-span-5 md:col-span-1" v-if="permissions.includes('admin-access transactions')">
                <SearchInput placeholderStr="Cust ID" v-model="filters.customer_code" @keyup.enter="onSearchFilterUpdated()">
                    Cust ID
                </SearchInput>
            </div>
            <div class="col-span-5 md:col-span-1" v-if="permissions.includes('admin-access transactions')">
                <SearchInput placeholderStr="Cust Name" v-model="filters.customer_name" @keyup.enter="onSearchFilterUpdated()">
                    Cust Name
                </SearchInput>
            </div> -->
            <div class="col-span-5 md:col-span-1" v-if="permissions.includes('admin-access transactions')">
                <SearchInput placeholderStr="Customer" v-model="filters.customer" @keyup.enter="onSearchFilterUpdated()">
                    Customer
                </SearchInput>
            </div>
            <div class="col-span-5 md:col-span-1" v-if="permissions.includes('admin-access transactions')">
                <SearchInput placeholderStr="Product ID" v-model="filters.product_code" @keyup.enter="onSearchFilterUpdated()">
                    Product ID
                </SearchInput>
            </div>
            <div class="col-span-5 md:col-span-1" v-if="permissions.includes('admin-access transactions')">
                <SearchInput placeholderStr="Product Name" v-model="filters.product_name" @keyup.enter="onSearchFilterUpdated()">
                    Product Name
                </SearchInput>
            </div>
            <div v-if="permissions.includes('admin-access transactions')">
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
            <div class="col-span-5 md:col-span-1" v-if="permissions.includes('admin-access transactions')">
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
            <div class="col-span-5 md:col-span-1">
                <label for="text" class="block text-sm font-medium text-gray-700">
                    Payment Received
                </label>
                <MultiSelect
                    v-model="filters.is_payment_received"
                    :options="successfulOptions"
                    trackBy="id"
                    valueProp="id"
                    label="value"
                    placeholder="Select"
                    open-direction="bottom"
                    class="mt-1"
                >
                </MultiSelect>
            </div>
            <div class="col-span-5 md:col-span-1" v-if="permissions.includes('admin-access transactions')">
                <label for="text" class="block text-sm font-medium text-gray-700">
                    Location Type
                </label>
                <MultiSelect
                    v-model="filters.location_type_id"
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
            <div class="col-span-5 md:col-span-1" v-if="permissions.includes('admin-access transactions')">
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
                    class="mt-1"
                    mode="tags"
                >
                </MultiSelect>
            </div>
            <div class="col-span-5 md:col-span-1">
                <label for="text" class="block text-sm font-medium text-gray-700">
                    Is Refunded?
                </label>
                <MultiSelect
                    v-model="filters.is_refunded"
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
            <div class="col-span-5 md:col-span-1" v-if="permissions.includes('admin-access transactions')">
                <label for="text" class="block text-sm font-medium text-gray-700">
                    TXN_SRC
                </label>
                <MultiSelect
                    v-model="filters.interface_type"
                    :options="vmcByteOptions"
                    trackBy="id"
                    valueProp="id"
                    label="value"
                    placeholder="Select"
                    open-direction="bottom"
                    class="mt-1"
                >
                </MultiSelect>
            </div>
            <div class="col-span-5 md:col-span-1">
                <label for="text" class="block text-sm font-medium text-gray-700">
                    Is Multiple?
                </label>
                <MultiSelect
                    v-model="filters.is_multiple"
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
            <div class="col-span-5 md:col-span-1" v-if="permissions.includes('admin-access transactions')">
                <SearchInput placeholderStr="APK Ver" v-model="filters.apk_ver" @keyup.enter="onSearchFilterUpdated()">
                    APK Ver
                </SearchInput>
            </div>
            <div>
                <label for="text" class="block text-sm font-medium text-gray-700">
                    Machine Contract
                </label>
                <MultiSelect
                    v-model="filters.vendContracts"
                    :options="vendContractOptions"
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
            <div class="col-span-5 md:col-span-1">
                <label for="text" class="block text-sm font-medium text-gray-700">
                    Is Member?
                </label>
                <MultiSelect
                    v-model="filters.is_member"
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
            <div class="col-span-5 md:col-span-1">
                <SearchInput placeholderStr="Member ID" v-model="filters.member_code" @keyup.enter="onSearchFilterUpdated()">
                    Member ID
                </SearchInput>
            </div>
            <div class="col-span-5 md:col-span-1">
                <SearchInput placeholderStr="HID Card ID" v-model="filters.hid_card_id" @keyup.enter="onSearchFilterUpdated()">
                    HID Card ID
                </SearchInput>
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
                        @click.prevent="resetFilters()"
                        >
                            <BackspaceIcon class="h-4 w-4" aria-hidden="true"/>
                            <span>
                                Reset
                            </span>
                        </Button>
                        <!-- <Button type="button" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-400 hover:bg-gray-100"
                            @click.prevent="onExportExcelClicked()"
                            v-if="permissions.includes('export transactions')">
                            <div class="flex space-x-1">
                                <div>
                                    <ArrowDownTrayIcon v-if="!loading" class="h-4 w-4" aria-hidden="true"/>
                                    <svg v-if="loading" aria-hidden="true" class="mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                                        <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                                    </svg>
                                </div>
                                <span>
                                    Export Excel
                                </span>
                            </div>
                        </Button> -->
                        <Button
                            type="button"
                            class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-400 hover:bg-gray-100"
                            @click.prevent="onExportCsvClicked()"
                            v-if="permissions.includes('export transactions')"
                        >
                            <div class="flex space-x-1">
                                <ArrowDownTrayIcon v-if="!loading" class="h-4 w-4" aria-hidden="true"/>
                                <svg v-if="loadingCsv" aria-hidden="true" class="mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908Z" fill="currentColor"/>
                                    <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                                </svg>
                                <span>Export CSV</span>
                            </div>
                        </Button>
                    </div>
                </div>
                <div class="flex flex-col space-y-2">
                    <p class="text-sm text-gray-700 leading-5 flex space-x-1">
                        <span>Showing</span>
                        <span class="font-medium">{{ vendTransactions.meta.from ?? 0 }}</span>
                        <span>to</span>
                        <span class="font-medium">{{ vendTransactions.meta.to ?? 0 }}</span>
                        <span>of</span>
                        <span class="font-medium">{{ vendTransactions.meta.total }}</span>
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

            <div v-if="latestExports.length" class="m-2 max-w-full md:max-w-2xl lg:max-w-xl mx-start">
            <h4 class="text-md font-semibold text-gray-900 mt-2">Recent CSV Exports</h4>
            <ul role="list" class="space-y-1">
                <li
                v-for="latestExport in latestExports"
                :key="latestExport.id"
                class="relative flex items-center gap-x-2"
                >
                <div class="flex-shrink-0 pt-1">
                    <CheckCircleIcon
                    v-if="latestExport.status === 'completed'"
                    class="h-5 w-5 text-green-500"
                    aria-hidden="true"
                    />
                    <div
                    v-else
                    class="h-2 w-2 mt-1 rounded-full bg-gray-300 ring-1 ring-gray-400"
                    ></div>
                </div>

                <div class="flex-1 flex items-center justify-between rounded-md bg-white px-3 py-3 shadow ring-1 ring-gray-200 ring-inset">
                    <div class="flex items-center gap-x-2 overflow-hidden">
                    <p class="text-sm font-medium text-gray-900 truncate max-w-xs">
                        {{ latestExport.filename }}
                    </p>
                    <span v-if="latestExport.status === 'completed' && latestExport.attachment?.full_url" class="text-xs text-indigo-600 hover:underline">
                        <a :href="latestExport.attachment.full_url" target="_blank">Download</a>
                    </span>
                    <span v-else-if="latestExport.status === 'failed'" class="text-xs text-red-500 italic">
                        Failed
                    </span>
                    <span v-else class="text-xs italic text-gray-500">
                        Processing...
                    </span>
                    </div>

                    <button
                    @click="deleteExport(latestExport.id)"
                    class="text-gray-400 hover:text-red-600 ml-2"
                    title="Remove"
                    >
                    <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>
                </li>
            </ul>
            </div>



        <!-- <dl class="mt-2 grid grid-cols-1 gap-2 sm:grid-cols-5"> -->
        <dl class="grid grid-cols-1 md:grid-cols-4 gap-2">
            <div class="col-span-1 overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow">
                <dt class="truncate text-sm font-medium text-gray-500">Total Revenue {{ operatorCountry.currency_symbol }} (Success)</dt>
                <dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
                    {{((totals['success_amount'] ? totals['success_amount'] : 0)/ (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
                </dd>
            </div>
            <div class="col-span-1 overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow">
                <dt class="truncate text-sm font-medium text-gray-500">Transactions Count (Success)</dt>
                <dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
                    <div class="flex flex-col space-y-2">
                        <span>
                            {{(totals['success_count'] ? totals['success_count'] : 0 ).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0})}}
                        </span>
                        <div class="flex space-x-2 items-center">
                            <span class="truncate text-xs font-medium text-gray-600">
                                Success Rate
                            </span>
                            <span class="text-gray-600 text-base">
                                {{(totals['success_count_rate'] ? totals['success_count_rate'] : 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}}%
                            </span>
                        </div>
                    </div>
                </dd>
            </div>
            <div class="col-span-1 overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow">
                <dt class="truncate text-sm font-medium text-gray-500">Multiple Puchases Count</dt>
                <dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
                    <div class="flex justify-between items-center">
                        <span class="truncate text-xs font-medium text-gray-600">
                            Delivery Platform
                        </span>
                        <span>
                            {{(totals['multiple_count_delivery_platform'] ? totals['multiple_count_delivery_platform'] : 0).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0})}}
                        </span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="truncate text-xs font-medium text-gray-600">
                            Machine
                        </span>
                        <span>
                            {{(totals['multiple_count_machine'] ? totals['multiple_count_machine'] : 0).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0})}}
                        </span>
                    </div>
                </dd>
            </div>
            <div class="col-span-1 overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow">
                <dt class="truncate text-sm font-medium text-gray-500">Total Qty (Success)</dt>
                <dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
                    <div class="flex flex-col space-y-2">
                        <span>
                            {{(totals['success_total_qty'] ? totals['success_total_qty'] : 0 ).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0})}}
                        </span>
                        <div class="flex space-x-2 items-center">
                            <span class="truncate text-xs font-medium text-gray-600">
                                Success Rate
                            </span>
                            <span class="text-gray-600 text-base">
                                {{(totals['success_total_qty_rate'] ? totals['success_total_qty_rate'] : 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}}%
                            </span>
                        </div>
                    </div>
                </dd>
            </div>
        </dl>
      </div>

       <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll">
              <table class="table-auto min-w-full border-separate" style="border-spacing: 0">
                  <thead class="">
                      <tr class="divide-x bg-gray-400">
                        <TableHead>
                            #
                        </TableHead>
                        <TableHead>
                            Order ID
                        </TableHead>
                        <TableHeadSort modelName="transaction_datetime" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('transaction_datetime')">
                            Transaction DateTime
                        </TableHeadSort>
                        <TableHead>
                            Machine ID
                        </TableHead>
                        <TableHead>
                            Machine Prefix
                        </TableHead>
                        <TableHead>
                            Customer
                        </TableHead>
                        <TableHead>
                            Operator
                        </TableHead>
                        <TableHead>
                            Channel
                        </TableHead>
                        <TableHead>
                            Product ID
                        </TableHead>
                        <TableHead>
                            Product Name
                        </TableHead>
                        <TableHead>
                            Price Type
                        </TableHead>
                        <TableHeadSort modelName="amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('amount', true)">
                            Amount {{ operatorCountry.currency_symbol }}
                        </TableHeadSort>
                        <TableHead>
                            Payment Method
                        </TableHead>
                        <TableHead>
                            Channels Error
                        </TableHead>
                        <TableHead>
                            Payment Received
                        </TableHead>
                        <TableHead>
                            Refunded?
                        </TableHead>
                        <TableHead>
                            TXN_SRC
                        </TableHead>
                        <TableHead>
                            Member ID
                        </TableHead>
                        <TableHead>
                            HID Card ID
                        </TableHead>
                      </tr>
                  </thead>
                  <tbody class="bg-white">
                    <template v-for="(vendTransaction, vendTransactionIndex) in vendTransactions.data" :key="vendTransaction.id">
                      <tr class="divide-x" :class="vendTransaction.is_multiple ? 'divide-x bg-gray-100' : ''">
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-center">
                            {{ vendTransactions.meta.from + vendTransactionIndex }}
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-center">
                            {{ vendTransaction.order_id }}
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-center">
                            {{ vendTransaction.transaction_datetime }}
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-center">
                            {{ vendTransaction.vend_code }}
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-center">
                            {{ vendTransaction.vend_prefix_name }}
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-left">
                            <span v-if="vendTransaction.person_id">
                                <!-- {{ vendTransaction.virtual_customer_prefix }}- -->
                                {{ vendTransaction.virtual_customer_code }} <br>
                                {{ vendTransaction.customer_name }}
                            </span>
                            <span v-else-if="!vendTransaction.person_id">
                                {{ vendTransaction.customer_name }}
                            </span>
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-center">
                            {{ vendTransaction.operator_code }}
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-center">
                            <span v-if="!vendTransaction.is_multiple">
                                {{ vendTransaction.vend_channel_code }}
                            </span>
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-center">
                            {{ vendTransaction.product_code }}
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-left">
                            {{ vendTransaction.product_name }}
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-center">
                            <div
                                class="inline-flex justify-center items-center rounded px-0.5 py-0.5 text-xs border w-fit hover:cursor-pointer"
                                :class="[vendTransaction.amount === vendTransaction.vend_channel_amount ? 'bg-indigo-100 text-indigo-800 border-indigo-300' : (vendTransaction.amount === vendTransaction.vend_channel_amount2 ? 'bg-purple-100 text-purple-800 border-purple-300' : '')]"
                                v-if="(vendTransaction.amount === vendTransaction.vend_channel_amount) || (vendTransaction.amount === vendTransaction.vend_channel_amount2)"
                            >
                                <div class="flex flex-col">
                                    <span class="font-semibold grow-0" v-if="vendTransaction.amount === vendTransaction.vend_channel_amount && vendTransaction.amount === vendTransaction.vend_channel_amount2">
                                        P1
                                    </span>
                                    <span class="font-semibold grow-0" v-else-if="vendTransaction.amount === vendTransaction.vend_channel_amount">
                                        P1
                                    </span>
                                    <span class="font-semibold grow-0" v-else-if="vendTransaction.amount === vendTransaction.vend_channel_amount2">
                                        P2
                                    </span>
                                </div>
                            </div>
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-right">
                            {{ vendTransaction.amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-center">
                            {{ vendTransaction.payment_method_name }}
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-center">
                            <span v-if="vendTransaction.vend_channel_error_desc">
                                {{ vendTransaction.vend_channel_error_desc }}
                            </span>
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-center">
                            <span v-if="vendTransaction.is_payment_received">
                                {{ vendTransaction.is_payment_received ? 'Successful' : 'Unsuccessful' }}
                            </span>
                            <span v-else>
                                {{ vendTransaction.vend_channel_error_code ? (vendTransaction.vend_channel_error_code == 0 || vendTransaction.vend_channel_error_code == 6 ? 'Successful' : "Unsuccessful") : 'Successful' }}
                            </span>
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-center">
                            <div class="flex justify-center">
                                <CheckCircleIcon class="h-4 w-4 text-green-500" aria-hidden="true" v-if="vendTransaction.is_refunded"/>
                            </div>
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-center">
                            {{ vendTransaction.interface_type }}
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-center">
                            {{ vendTransaction.vendTransactionJson && vendTransaction.vendTransactionJson['dcvend_user_id'] ? vendTransaction.vendTransactionJson['dcvend_user_id'] : null }}
                        </TableData>
                        <TableData :currentIndex="vendTransactionIndex" :totalLength="vendTransactions.length" inputClass="text-center">
                            {{ vendTransaction.metaJson && vendTransaction.metaJson['hid_card_id'] ? vendTransaction.metaJson['hid_card_id'] : null }}
                        </TableData>
                      </tr>
                      <tr v-if="vendTransaction.vendTransactionItems" v-for="(vendTransactionItem, vendTransactionItemIndex) in vendTransaction.vendTransactionItems" class="divide-x">
                        <td v-if="vendTransactionItemIndex == 0" class="border-b border-gray-200" colspan="7" :rowspan="vendTransaction.vendTransactionItems.length"></td>
                        <TableData :currentIndex="vendTransactionItemIndex" :totalLength="vendTransaction.vendTransactionItems.length" inputClass="text-center bg-gray-100">
                            {{ vendTransactionItem.vend_channel_code ? vendTransactionItem.vend_channel_code : null }}
                        </TableData>
                        <TableData :currentIndex="vendTransactionItemIndex" :totalLength="vendTransaction.vendTransactionItems.length" inputClass="text-center bg-gray-100">
                            <span v-if="vendTransactionItem.product">
                                {{ vendTransactionItem.product.code }}
                            </span>
                            <span v-else></span>
                        </TableData>
                        <TableData :currentIndex="vendTransactionItemIndex" :totalLength="vendTransaction.vendTransactionItems.length" inputClass="text-left bg-gray-100">
                            <span v-if="vendTransactionItem.product">
                                {{ vendTransactionItem.product.name }}
                            </span>
                            <span v-else></span>
                        </TableData>
                        <TableData :currentIndex="vendTransactionItemIndex" :totalLength="vendTransaction.vendTransactionItems.length" inputClass="text-center bg-gray-100" colspan="3">
                        </TableData>
                        <TableData :currentIndex="vendTransactionItemIndex" :totalLength="vendTransaction.vendTransactionItems.length" inputClass="text-center bg-gray-100">
                            <span v-if="vendTransactionItem.vendChannelError">
                                {{ vendTransactionItem.vendChannelError ? vendTransactionItem.vendChannelError.desc : null }}
                            </span>
                            <span v-if="!vendTransactionItem.vendChannelError && vendTransactionItem.vend_channel_error_code">
                                ({{ vendTransactionItem.vend_channel_error_code }})
                            </span>
                        </TableData>
                        <TableData :currentIndex="vendTransactionItemIndex" :totalLength="vendTransaction.vendTransactionItems.length" inputClass="text-center bg-gray-100">
                            <span v-if="!vendTransactionItem.vendChannelError || (vendTransactionItem.vendChannelError && vendTransactionItem.vendChannelError.code == 0) || (vendTransactionItem.vendChannelError && vendTransactionItem.vendChannelError.code == 6)">
                                Successful
                            </span>
                            <span v-else>
                                Unsuccessful
                            </span>
                        </TableData>
                        <TableData :currentIndex="vendTransactionItemIndex" :totalLength="vendTransaction.vendTransactionItems.length" inputClass="text-center bg-gray-100">
                            <div class="flex justify-center">
                                <CheckCircleIcon class="h-4 w-4 text-green-500" aria-hidden="true" v-if="vendTransactionItem.is_refunded"/>
                            </div>
                        </TableData>
                        <TableData :currentIndex="vendTransactionItemIndex" :totalLength="vendTransaction.vendTransactionItems.length" inputClass="text-center bg-gray-100">
                            {{ vendTransaction.interface_type }}
                        </TableData>
                      </tr>
                    </template>
                    <tr v-if="!vendTransactions || !vendTransactions.data.length">
                        <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                            No Results Found
                        </td>
                    </tr>
                  </tbody>
              </table>
              <Paginator v-if="vendTransactions.data.length" :links="vendTransactions.links" :meta="vendTransactions.meta"></Paginator>
          </div>
      </div>
      </div>
  </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import DatePicker from '@/Components/DatePicker.vue';
import Paginator from '@/Components/Paginator.vue';
import { MagnifyingGlassIcon, BackspaceIcon, CheckCircleIcon, ArrowDownTrayIcon, XMarkIcon } from '@heroicons/vue/20/solid';
import MultiSelect from '@/Components/MultiSelect.vue';
import moment from 'moment';
import SearchInput from '@/Components/SearchInput.vue';
import TableData from '@/Components/TableData.vue';
import TableHead from '@/Components/TableHead.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ref, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { Head, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import fileDownload from 'js-file-download'
import { useToast } from "vue-toastification";

const props = defineProps({
    categories: Object,
    categoryGroups: Object,
    operatorOptions: Object,
    latestExports: Object,
    locationTypeOptions: Object,
    paymentMethods: Object,
    vends: Object,
    vendTransactions: Object,
    totals: [Object, Array],
    vendChannelErrors: Object,
    vendContractOptions: Object,
    vendPrefixOptions: Object,
})
const authOperator = usePage().props.auth.operator
const booleanOptions = ref([])
const successfulOptions = ref([])
const categoryOptions = ref([])
const categoryGroupOptions = ref([])
const latestExports = ref([])
const loadingCsv = ref(false)
const locationTypeOptions = ref([])
const operatorCountry = usePage().props.auth.operatorCountry
const operatorOptions = ref([])
const permissions = usePage().props.auth.permissions
const toast = useToast()
const vendContractOptions = ref([])
const vendPrefixOptions = ref([])
const vmcByteOptions = ref([])

let intervalId = null;

onMounted(() => {
    intervalId = setInterval(fetchLatestExports, 15000);
    filters.value.visited = true
    vendChannelErrorOptions.value = [
        {'id': 'errors_only', 'desc': 'Errors Only'},
        ...props.vendChannelErrors.data.map((error) => {return {id: error.id, desc: error.desc}})
    ]
    paymentMethodOptions.value = [
        {id: 'all', name: 'All'},
        ...props.paymentMethods.data.map((paymethod) => {return {id: paymethod.id, name: paymethod.name}})
    ]
    numberPerPageOptions.value = [
        { id: 50, value: 50 },
        { id: 100, value: 100 },
        { id: 200, value: 200 },
        { id: 500, value: 500 },
        { id: 'All', value: 'All' },
    ]
    filters.value.numberPerPage = numberPerPageOptions.value[0]
    filters.value.paymentMethods = [paymentMethodOptions.value[0]]

    booleanOptions.value = [
        {id: 'all', value: 'All'},
        {id: 'true', value: 'Yes'},
        {id: 'false', value: 'No'},
    ]
    categoryOptions.value = props.categories.data.map((data) => {return {id: data.id, name: data.name}})
    categoryGroupOptions.value = props.categoryGroups.data.map((data) => {return {id: data.id, name: data.name}})
    latestExports.value = props.latestExports
    locationTypeOptions.value = [
        {id: 'all', value: 'All'},
        ...props.locationTypeOptions.data.map((data) => {return {id: data.id, value: data.name}})
    ]
    operatorOptions.value = [
        {id: 'all', full_name: 'All'},
        ...props.operatorOptions.data.map((data) => {return {id: data.id, code:data.code, full_name: data.full_name}})
    ]
    successfulOptions.value = [
        {id: 'all', value: 'All'},
        {id: 'true', value: 'Successful'},
        {id: 'false', value: 'Unsuccessful'},
    ]
    vendContractOptions.value = [
      {id: 'all', value: 'All'},
      ...props.vendContractOptions.data.map((data) => {return {id: data.id, value: data.name}})
    ]
    vendPrefixOptions.value = [
        {id: 'all', value: 'All'},
        {id: 'single-ud', value: 'Single UD'},
        ...props.vendPrefixOptions.data.map((data) => {return {id: data.id, value: data.name}})
    ]
    vmcByteOptions.value = [
        {id: 'all', value: 'All'},
        {id: '0', value: '0'},
        {id: '1', value: '1'},
        {id: '50', value: '50'},
    ]
    filters.value.location_type_id = locationTypeOptions.value[0]
    filters.value.operators = authOperator ? [
		operatorOptions.value.find(operator => operator.id === authOperator.id),
		...authOperator.code == 'HIPL' ? [
			operatorOptions.value.find(operator => operator.code == 'HIMD'),
			operatorOptions.value.find(operator => operator.code == 'LEA'),
			operatorOptions.value.find(operator => operator.code == 'DCVIC'),
		] : [],
	] : operatorOptions.value[0]
    filters.value.interface_type = vmcByteOptions.value[0]
    filters.value.is_binded_customer = booleanOptions.value[0]
    filters.value.is_member = booleanOptions.value[0]
    filters.value.is_multiple = booleanOptions.value[0]
    filters.value.is_payment_received = booleanOptions.value[0]
    filters.value.is_refunded = booleanOptions.value[0]
})

onUnmounted(() => {
    clearInterval(intervalId);
});

const filters = ref({
    apk_ver: '',
    codes: '',
    channel_codes: '',
    categories: [],
    categoryGroups: [],
    customer_code: '',
    customer_name: '',
    product_code: '',
    product_name: '',
    errors: [],
    location_type_id: '',
    member_code: '',
    operators: [],
    order_id: '',
    interface_type: '',
    is_binded_customer: '',
    is_member: '',
    is_multiple: '',
    is_payment_received: '',
    is_refunded: '',
    paymentMethods: [],
    date_from: moment().format('YYYY-MM-DD'),
    date_to: moment().format('YYYY-MM-DD'),
    sortKey: '',
    sortBy: false,
    numberPerPage: 50,
    vendContracts: [],
    vendPrefixes: [],
    visited: true,
})
// const vendOptions = ref([])
const vendChannelErrorOptions = ref([])
const loading = ref(false)
const paymentMethodOptions = ref([])
const numberPerPageOptions = ref([])

// function onExportExcelClicked() {
//     // window.open('/vends/transactions/excel', '_blank');
//     loading.value = true
//     axios({
//         method: 'get',
//         url: '/vends/transactions/excel',
//         params: {
//             ...filters.value,
//             categories: filters.value.categories.map((category) => { return category.id }),
//             categoryGroups: filters.value.categoryGroups.map((categoryGroup) => { return categoryGroup.id }),
//             channel_code: filters.value.channel_code,
//             errors: filters.value.errors.map((error) => { return error.id }),
//             operator_id: filters.value.operator.id,
//             paymentMethod: filters.value.paymentMethod.id,
//             numberPerPage: filters.value.numberPerPage.id,
//         },
//         responseType: 'blob',
//     }).then(response => {
//         fileDownload(response.data, 'Vending_Transaction_' + moment().format('YYMMDDhhmmss') +'.xlsx')
//         loading.value = false
//     }).catch(error => {
//         console.log(error.message)
//         loading.value = false
//     }).finally(() => {
//         loading.value = false
//     })
// }

const deleteExport = (id) => {
    latestExports.value = latestExports.value.filter(e => e.id !== id)
    axios.delete(`/vends/transactions/latest-exports/${id}`)
}

function fetchLatestExports() {
    axios.get('/vends/transactions/latest-exports').then(response => {
        latestExports.value = response.data;
    });
}

function onExportCsvClicked() {
    loadingCsv.value = true
    axios({
        method: 'get',
        url: '/vends/transactions/export-csv',
        params: {
            ...filters.value,
            categories: filters.value.categories.map(c => c.id),
            categoryGroups: filters.value.categoryGroups.map(cg => cg.id),
            channel_codes: filters.value.channel_codes,
            location_type_id: filters.value.location_type_id.id,
            operators: filters.value.operators.map(o => o.id),
            interface_type: filters.value.interface_type.id,
            is_binded_customer: filters.value.is_binded_customer.id,
            is_member: filters.value.is_member.id,
            is_multiple: filters.value.is_multiple.id,
            is_payment_received: filters.value.is_payment_received.id,
            is_refunded: filters.value.is_refunded.id,
            paymentMethods: filters.value.paymentMethods.map(pm => pm.id),
            numberPerPage: filters.value.numberPerPage.id,
            vendContracts: filters.value.vendContracts.map(vc => vc.id),
            vendPrefixes: filters.value.vendPrefixes.map(vp => vp.id),
            errors: filters.value.errors.map(e => e.id),
        },
        responseType: 'blob',
    })
    .then(response => {
        toast.success("Exporting, please visit back or refresh after a while", {
        timeout: 5000
      });
        // fileDownload(response.data, 'Vending_Transaction_' + moment().format('YYMMDDhhmmss') + '.csv')
    })
    .catch(error => {
        console.error(error)
    })
    .finally(() => {
        loadingCsv.value = false
    })
}

function onExportExcelClicked() {
    // window.open('/vends/transactions/excel', '_blank');
    loading.value = true
    axios({
        method: 'get',
        url: '/vends/transactions/excel',
        params: {
            ...filters.value,
            categories: filters.value.categories.map((category) => { return category.id }),
            categoryGroups: filters.value.categoryGroups.map((categoryGroup) => { return categoryGroup.id }),
            channel_codes: filters.value.channel_codes,
            errors: filters.value.errors.map((error) => { return error.id }),
            location_type_id: filters.value.location_type_id.id,
            operators: filters.value.operators.map((operator) => { return operator.id }),
            interface_type: filters.value.interface_type.id,
            is_binded_customer: filters.value.is_binded_customer.id,
            is_member: filters.value.is_member.id,
            is_multiple: filters.value.is_multiple.id,
            is_payment_received: filters.value.is_payment_received.id,
            is_refunded: filters.value.is_refunded.id,
            paymentMethods: filters.value.paymentMethods.map((paymentMethod) => { return paymentMethod.id }),
            numberPerPage: filters.value.numberPerPage.id,
            vendContracts: filters.value.vendContracts.map((vendContract) => { return vendContract.id }),
            vendPrefixes: filters.value.vendPrefixes.map((vendPrefix) => { return vendPrefix.id }),
        },
        responseType: 'blob',
    }).then(response => {
        fileDownload(response.data, 'Vending_Transaction_' + moment().format('YYMMDDhhmmss') +'.xlsx')
    }).catch(error => {
        console.log(error)
    }).finally(() => {
        loading.value = false
    })
}

function onSearchFilterUpdated() {
    router.get('/vends/transactions', {
        ...filters.value,
        categories: filters.value.categories.map((category) => { return category.id }),
        categoryGroups: filters.value.categoryGroups.map((categoryGroup) => { return categoryGroup.id }),
        channel_codes: filters.value.channel_codes,
        errors: filters.value.errors.map((error) => { return error.id }),
        location_type_id: filters.value.location_type_id.id,
        operators: filters.value.operators.map((operator) => { return operator.id }),
        interface_type: filters.value.interface_type.id,
        is_binded_customer: filters.value.is_binded_customer.id,
        is_member: filters.value.is_member.id,
        is_multiple: filters.value.is_multiple.id,
        is_payment_received: filters.value.is_payment_received.id,
        is_refunded: filters.value.is_refunded.id,
        paymentMethods: filters.value.paymentMethods.map((paymentMethod) => { return paymentMethod.id }),
        numberPerPage: filters.value.numberPerPage.id,
        vendContracts: filters.value.vendContracts.map((vendContract) => { return vendContract.id }),
        vendPrefixes: filters.value.vendPrefixes.map((vendPrefix) => { return vendPrefix.id }),
    }, {
        preserveState: true,
        replace: true,
    })
}

function resetFilters() {
    router.get('/vends/transactions')
}

function sortTable(sortKey, inverse = false) {
  filters.value.sortBy = !filters.value.sortBy
  if(inverse && filters.value.sortKey != sortKey) {
      filters.value.sortBy = !filters.value.sortBy
  }
  filters.value.sortKey = sortKey
  onSearchFilterUpdated()
}
</script>