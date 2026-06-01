
<template>
  <Head title="Vending Machines - Machines" />

<BreezeAuthenticatedLayout>
  <template #header>
    <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Vending Machine (Machines View)
    </h2>
  </template>

  <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
  <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
      <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
        <SearchInput placeholderStr="4 to 5 Digits Number" v-model="filters.codes" @keyup.enter="onSearchFilterUpdated()">
          Machine ID
          <span class="text-[9px]">
              ("," for multiple)
          </span>
        </SearchInput>
        <SearchInput :class="[showAllFilters ? 'block' : 'hidden']" placeholderStr="Channel ID" v-model="filters.channel_codes" @keyup.enter="onSearchFilterUpdated()">
          Channel ID
          <span class="text-[9px]">
              ("," for multiple)
          </span>
        </SearchInput>
        <SearchInput :class="[showAllFilters ? 'block' : 'hidden']"  placeholderStr="Serial Num" v-model="filters.serialNum" @keyup.enter="onSearchFilterUpdated()">
          Serial Num
        </SearchInput>
        <SearchInput placeholderStr="Number" v-model="filters.tempHigherThan" @keyup.enter="onSearchFilterUpdated()" :class="[showAllFilters ? 'block' : 'hidden']">
          T1 &gt;&gt;
        </SearchInput>
        <SearchInput placeholderStr="Number" v-model="filters.t2HigherThan" @keyup.enter="onSearchFilterUpdated()" :class="[showAllFilters ? 'block' : 'hidden']">
          T2 &gt;&gt;
        </SearchInput>
        <SearchInput :class="[showAllFilters ? 'block' : 'hidden']"  placeholderStr="Number" v-model="filters.tempDeltaHigherThan" @keyup.enter="onSearchFilterUpdated()">
          T1-T2 Delta &gt;&gt;
        </SearchInput>
        <SearchInput :class="[showAllFilters ? 'block' : 'hidden']" placeholderStr="Number" v-model="filters.tempLimitHigherThan" @keyup.enter="onSearchFilterUpdated()">
          Temperature Limit &gt;&gt;
          <!-- {{vend.acbVmcPaJson['TempLimit']}} -->
        </SearchInput>
        <SearchInput placeholderStr="Number" v-model="filters.allTempHigherThan" @keyup.enter="onSearchFilterUpdated()">
          Temperature &gt;&gt;
        </SearchInput>
        <div :class="[showAllFilters ? 'block' : 'hidden']">
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
        <!-- <SearchInput class="md:block" :class="[showAllFilters ? 'block' : 'hidden']" placeholderStr="Cust ID" v-model="filters.customer_code" v-if="permissions.includes('admin-access vend-machines')" @keyup.enter="onSearchFilterUpdated()">
            Cust ID
        </SearchInput> -->
        <div>
          <label for="text" class="block text-sm font-medium text-gray-700">
              Has Site
          </label>
          <MultiSelect
              v-model="filters.has_customer"
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
        <SearchInput placeholderStr="Site" v-model="filters.customer" v-if="permissions.includes('admin-access vend-machines')" @keyup.enter="onSearchFilterUpdated()">
          Site
        </SearchInput>
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
            Machine Status
          </label>
          <MultiSelect
            v-model="filters.status"
            :options="statusOptions"
            trackBy="id"
            valueProp="id"
            label="value"
            placeholder="Select"
            open-direction="bottom"
            class="mt-1"
          >
          </MultiSelect>
        </div>
        <div :class="[showAllFilters ? 'block' : 'hidden']" >
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
        <div :class="[showAllFilters ? 'block' : 'hidden']" >
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
        <div :class="[showAllFilters ? 'block' : 'hidden']">
            <label for="text" class="block text-sm font-medium text-gray-700">
                Fan RPM
            </label>
            <MultiSelect
                v-model="filters.fan_rpm"
                :options="fanRpmOptions"
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
        <div :class="[showAllFilters ? 'block' : 'hidden']" v-if="indexType === 'customers'">
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
        <SearchInput :class="[showAllFilters ? 'block' : 'hidden']" placeholderStr="How many Day(s)" v-model="filters.lastVisitedGreaterThan" @keyup.enter="onSearchFilterUpdated()" v-if="indexType === 'customers'">
          Last Visited Day &gt;&gt;
        </SearchInput>
        <SearchInput :class="[showAllFilters ? 'block' : 'hidden']" placeholderStr="Balance Stock Less Than" v-model="filters.balanceStockLessThan" @keyup.enter="onSearchFilterUpdated()">
          Balance Stock(%) &lt;&lt;
        </SearchInput>
        <SearchInput :class="[showAllFilters ? 'block' : 'hidden']" placeholderStr="Remaining SKU Less Than" v-model="filters.remainingSkuLessThan" @keyup.enter="onSearchFilterUpdated()">
          Remaining SKU(%) &lt;&lt;
        </SearchInput>
        <SearchInput :class="[showAllFilters ? 'block' : 'hidden']" placeholderStr="Firmware Ver" v-model="filters.firmware_ver" v-if="permissions.includes('admin-access vend-machines')" @keyup.enter="onSearchFilterUpdated()">
          Firmware Ver
        </SearchInput>
        <SearchInput :class="[showAllFilters ? 'block' : 'hidden']" placeholderStr="APK Ver" v-model="filters.apk_ver" v-if="permissions.includes('admin-access vend-machines')" @keyup.enter="onSearchFilterUpdated()">
          APK Ver
        </SearchInput>
        <div :class="[showAllFilters ? 'block' : 'hidden']">
          <label for="text" class="block text-sm font-medium text-gray-700">
            Android Device Type
          </label>
          <MultiSelect
            v-model="filters.deviceType"
            :options="deviceTypeOptions"
            trackBy="id"
            valueProp="id"
            label="value"
            placeholder="Select"
            open-direction="bottom"
            class="mt-1"
          >
          </MultiSelect>
        </div>
        <SearchInput :class="[showAllFilters ? 'block' : 'hidden']" placeholderStr="Avg Day Sales Less Than" v-model="filters.vendRecordsThirtyDaysAmountAverageLessThan" v-if="permissions.includes('admin-access vend-machines')" @keyup.enter="onSearchFilterUpdated()">
          Avg/Day Sales (30d) &lt;&lt;
        </SearchInput>
        <div :class="[showAllFilters ? 'block' : 'hidden']">
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
        <div :class="[showAllFilters ? 'block' : 'hidden']">
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
        <SearchInput :class="[showAllFilters ? 'block' : 'hidden']" placeholderStr="Account Manager" v-model="filters.account_manager_name" v-if="permissions.includes('admin-access vend-machines') && indexType === 'customers'" @keyup.enter="onSearchFilterUpdated()">
          Account Manager
        </SearchInput>
        <SearchInput :class="[showAllFilters ? 'block' : 'hidden']"  placeholderStr="Number" v-model="filters.coinLessThan" @keyup.enter="onSearchFilterUpdated()">
          Coin Amount &lt;&lt;
        </SearchInput>
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
            class="mt-1"
            mode="tags"
          >
          </MultiSelect>
        </div>
        <div :class="[showAllFilters ? 'block' : 'hidden']">
          <label for="text" class="block text-sm font-medium text-gray-700">
            Ref Price Type
          </label>
          <MultiSelect
            v-model="filters.selling_price_type"
            :options="sellingPriceTypeOptions"
            trackBy="id"
            valueProp="id"
            label="value"
            placeholder="Select"
            open-direction="bottom"
            class="mt-1"
          >
          </MultiSelect>
        </div>
        <div :class="[showAllFilters ? 'block' : 'hidden']">
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
                class="mt-1"
                mode="tags"
            >
            </MultiSelect>
          </div>
          <div :class="[showAllFilters ? 'block' : 'hidden']">
            <label for="text" class="block text-sm font-medium text-gray-700">
                Setting Chart
            </label>
            <MultiSelect
                v-model="filters.vendConfigs"
                :options="vendConfigOptions"
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
          <div :class="[showAllFilters ? 'block' : 'hidden']">
            <label for="text" class="block text-sm font-medium text-gray-700">
                Modem Type
            </label>
            <MultiSelect
                v-model="filters.modem_type_id"
                :options="modemTypeOptions"
                trackBy="id"
                valueProp="id"
                label="name"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
            >
            </MultiSelect>
          </div>
          <div :class="[showAllFilters ? 'block' : 'hidden']">
            <label for="text" class="block text-sm font-medium text-gray-700">
                Modem IMEI
            </label>
            <MultiSelect
                v-model="filters.modem_unit_id"
                :options="modemUnitOptions"
                trackBy="id"
                valueProp="id"
                label="name"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
            >
            </MultiSelect>
          </div>
          <div :class="[showAllFilters ? 'block' : 'hidden']">
            <label for="text" class="block text-sm font-medium text-gray-700">
                LCD Monitor
            </label>
            <MultiSelect
                v-model="filters.lcd_monitor_id"
                :options="lcdMonitorOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
            >
            </MultiSelect>
          </div>
          <div :class="[showAllFilters ? 'block' : 'hidden']">
            <label for="text" class="block text-sm font-medium text-gray-700">
              Is QR Code Active?
            </label>
            <MultiSelect
              v-model="filters.is_qr_code_active"
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
                Delivery Platform
            </label>
            <MultiSelect
                v-model="filters.delivery_platform_id"
                :options="deliveryPlatformOptions"
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
            <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
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
            @click="onIsShowOperationDivButtonClicked()"
            v-if="permissions.includes('export vends')">
              <div class="flex space-x-1">
                  <div>
                      <ChevronDoubleDownIcon class="h-4 w-4" aria-hidden="true" v-if="!isShowOperationDiv"/>
                      <ChevronDoubleUpIcon class="h-4 w-4" aria-hidden="true" v-if="isShowOperationDiv"/>
                  </div>
                  <span>
                      Operations
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
      <div v-if="isShowOperationDiv">
        <!-- <hr class="mt-2"> -->
        <div class="flex flex-col space-y-3 md:flex-row md:space-y-0 space-x-1 mt-5">
          <Button type="button" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-400 hover:bg-gray-100"
          @click="onExportChannelExcelClicked()"
          v-if="permissions.includes('export vends')">
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
          <!-- <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-blue-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
          @click="onSyncNextDeliveryDate()"
          v-if="permissions.includes('admin-access vend-machines')"
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
          <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
          @click="onGeneratePickListClicked()"
          >
            <ClipboardDocumentCheckIcon class="h-4 w-4" aria-hidden="true"/>
            <span class="flex flex-col space-y-1">
              <span>
                  Generate Pick List
              </span>
              <span class="text-xs leading-3">
                  Please tick the list below
              </span>
            </span>
          </Button> -->
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
          <TableHead v-if="isShowOperationDiv">
            <input
                id="isSelectedAll"
                type="checkbox"
                v-model="isSelectedAll"
                @change="toggleSelectAll"
                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
            >
          </TableHead>
          <TableHead>
            #
          </TableHead>
          <TableHead>
            <div class="flex flex-col space-y-2">
              <SingleSortItem modelName="vends.code" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vends.code')">
                Machine ID
              </SingleSortItem>
              <SingleSortItem modelName="vend_configs.name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_configs.name', false)">
                Setting Chart
              </SingleSortItem>
              <SingleSortItem modelName="vend_prefixes.name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_prefixes.name', false)">
                Machine Prefix
              </SingleSortItem>
              <SingleSortItem modelName="product_mappings.name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('product_mappings.name', false)">
                Product Mapping
              </SingleSortItem>
              <SingleSortItem modelName="customers.virtual_customer_code" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('customers.virtual_customer_code')">
                Site
              </SingleSortItem>
              <SingleSortItem modelName="customers.selling_price_type" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('customers.selling_price_type', false)">
                Ref Price
              </SingleSortItem>
            </div>
          </TableHead>
          <TableHead>
            <div class="flex flex-col space-y-2">
              <SingleSortItem modelName="temp" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('temp', true)">
                T1: Machine Temp
              </SingleSortItem>
              <SingleSortItem modelName="parameter_json->t2" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('parameter_json->t2', true)">
                T2: Evaporator Temp
              </SingleSortItem>
              <SingleSortItem modelName="temp_updated_at" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('temp_updated_at', true)">
                Updated
              </SingleSortItem>
              <div class="flex justify-center items-center mt-2 mb-2">
                <SingleSortItem modelName="t1_lowest_48h" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('t1_lowest_48h', true)">
                  T1 lowest L48hr
                </SingleSortItem>
                <ExclamationCircleIcon class="min-w-5 w-5 h-5 self-center pl-1 text-sky-500" v-tooltip="{ content: 'Lowest T1 Temp Last 48h <br> Red: > -18c <br> Blue: -21c to -18c <br> Green: < -21c', html: true }"></ExclamationCircleIcon>
              </div>
              <span>
                &Delta;T1-T2
              </span>
              <div class="flex justify-center items-center mt-2">
                <SingleSortItem modelName="parameter_json->fan" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('parameter_json->fan', false)">
                    Fan RPM
                </SingleSortItem>
              </div>
            </div>
          </TableHead>
          <TableHead>
            <div class="flex flex-col space-y-2">
              <span>Setting Chart</span>
              <span class="text-blue-600 text-xs">Current</span>
              <span class="text-blue-600 text-xs">Latest*</span>
              <span class="border-t border-gray-300 pt-1">Product Mapping</span>
              <span class="text-blue-600 text-xs">Current</span>
              <span class="text-blue-600 text-xs">Upcoming*</span>
            </div>
          </TableHead>
          <TableHeadSort modelName="modem_type_name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('modem_type_name')">
            <div class="flex flex-col space-y-1">
              <span>
                Modem
              </span>
              <span>
                IMEI
              </span>
              <span>
                Status
              </span>
            </div>
          </TableHeadSort>
          <TableHead>
            Inventory Status <br>
            #Channel, Needed, Balance/Capacity (LastStockIn)
          </TableHead>
          <TableHead>
            <div class="flex flex-col space-y-2">
              <SingleSortItem modelName="vend_channel_error_logs_json" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_channel_error_logs_json')">
                Errors
              </SingleSortItem>
              <SingleSortItem modelName="totals_json->two_days_error_rate" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('totals_json->two_days_error_rate', false)">
                Error Rate 2d
              </SingleSortItem>
              <SingleSortItem modelName="totals_json->seven_days_error_rate" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('totals_json->seven_days_error_rate', false)">
                Error Rate 7d
              </SingleSortItem>
            </div>
          </TableHead>
          <TableHead>
            <div class="flex flex-col space-y-2">
              <SingleSortItem modelName="balance_percent" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('balance_percent', true)">
                Balance Stock
              </SingleSortItem>
              <SingleSortItem modelName="out_of_stock_sku_percent" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('out_of_stock_sku_percent', false)">
                Remaining SKU#
              </SingleSortItem>
            </div>
          </TableHead>
          <TableHead>
            Sales(qty)
            <SingleSortItem modelName="totals_json->today_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('totals_json->today_amount', false)">
              Today
            </SingleSortItem>
            <SingleSortItem modelName="totals_json->yesterday_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('totals_json->yesterday_amount', false)">
              Y'day
            </SingleSortItem>
            <SingleSortItem modelName="totals_json->seven_days_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('totals_json->seven_days_amount', false)">
              Last7d
            </SingleSortItem>
            <SingleSortItem modelName="totals_json->thirty_days_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('totals_json->thirty_days_amount', false)">
              Last30d
            </SingleSortItem>
          </TableHead>
          <TableHead>
            Machine Status
          </TableHead>
          <TableHead>
            Payment Device
          </TableHead>
          <TableHeadSort modelName="last_invoice_date" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('last_invoice_date')" v-if="indexType === 'customers'">
            Last Visited <br>
            yymmdd
          </TableHeadSort>
          <TableHeadSort modelName="next_invoice_date" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('next_invoice_date')" v-if="indexType === 'customers'">
            Next Planned Visit <br>
            yymmdd
          </TableHeadSort>
          <TableHead>
            <div class="flex flex-col space-y-2">
              <SingleSortItem modelName="virtual_vend_records_thirty_days_amount_average" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('virtual_vend_records_thirty_days_amount_average', true)">
                Avg Per Day (Last 30d)
              </SingleSortItem>
              <SingleSortItem modelName="amount_average_day" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('amount_average_day', true)">
                Lifetime Sales
              </SingleSortItem>
              <span>Begin Date</span>
              <span>Avg Sales/ Day</span>
            </div>
          </TableHead>
          <TableHeadSort modelName="postcode" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('postcode')" v-if="indexType == 'customers'">
            Postcode
          </TableHeadSort>
          <TableHead>
            <div class="flex flex-col space-y-2">
              <span>Firmware Ver</span>
              <SingleSortItem modelName="vends.lcd_monitor_id" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vends.lcd_monitor_id')">
                LCD Monitor
              </SingleSortItem>
              <SingleSortItem modelName="operator_code" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('operator_code')">
                Operator
              </SingleSortItem>
              <SingleSortItem modelName="location_type_name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('location_type_name')" v-if="indexType === 'customers'">
                Location
              </SingleSortItem>
              <SingleSortItem modelName="account_manager_name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('account_manager_name')" v-if="indexType === 'customers'">
                Acc Manager
              </SingleSortItem>
            </div>
          </TableHead>
          <TableHead>
          </TableHead>
        </tr>
      </thead>
      <tbody class="bg-white">
        <tr v-for="(vend, vendIndex) in vends.data" :key="vendIndex"
          class="divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100">
          <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center" v-if="isShowOperationDiv">
            <input type="checkbox" v-model="vend.is_selected" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
          </TableData>
          <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
            {{ vends.meta.from + vendIndex }}
          </TableData>
          <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-left">
            <div class="flex flex-col space-y-1">
              <Link :href="'/settings/vend/' + vend.vend_id + '/update'" :class="[vend.is_active || vend.is_testing ? 'text-blue-600' : 'text-gray-400']" class="hover:underline">
                {{ vend.code }}
              </Link>
              <div
                class="inline-flex rounded px-0.5 py-0.5 text-xs border w-fit bg-yellow-100 text-yellow-800 border-yellow-300 max-w-48"
                v-if="vend.label_name"
              >
                {{ vend.label_name }}
              </div>
              <div class="text-left text-gray-800" v-if="vend.vend_config_name">
                {{ vend.vend_config_name }}
              </div>
              <div class="text-left text-blue-700 cursor-default select-none">
                {{ vend.vend_prefix_name }}
              </div>
              <span class="flex flex-col space-y-0.5">
                <a v-if="vend.productMapping" :href="'/product-mappings/' + vend.productMapping.id + '/edit'" target="_blank" class="text-gray-800 text-xs font-medium underline decoration-gray-400 underline-offset-2">
                  {{ vend.productMapping.name }}
                </a>
                <span v-else-if="vend.product_mapping_name" class="text-xs text-gray-800">
                  {{ vend.product_mapping_name }}
                </span>

              </span>
              <span v-if="vend.person_id" class="flex flex-col space-y-1">
                <span v-if="permissions.includes('admin-access vend-machines')">
                  <a :class="[vend.person_id && vend.customer_is_active || vend.is_testing ? 'text-blue-700' : 'text-gray-400']" class="hover:underline" target="_blank" :href="'/customers/' + vend.customer_id + '/edit'">
                    {{ vend.virtual_customer_code }}<br>
                    {{ vend.customer_name }}
                  </a>
                </span>
                <span v-else :class="[vend.customer_is_active || vend.is_testing ? 'text-gray-800' : 'text-gray-400']">
                  {{ vend.virtual_customer_code }}<br>
                  {{ vend.customer_name }}
                </span>
                <a target="_blank" :href="cmsEndpoint + '/person/' + vend.person_id + '/edit'" class="">
                  <div class="inline-flex justify-center items-center rounded px-2 py-1 text-[10px] font-small border bg-blue-200 text-gray-800">
                    CMS
                  </div>
                </a>
                <span v-if="vend.deliveryProductMappingVends" v-for="(deliveryProductMappingVend, index) in vend.deliveryProductMappingVends">
                  <div
                    class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit text-gray-800 bg-green-400"
                    v-if="deliveryProductMappingVend.deliveryProductMapping && deliveryProductMappingVend.deliveryProductMapping.deliveryPlatformOperator && deliveryProductMappingVend.deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform"
                  >
                    {{ deliveryProductMappingVend.deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform.name }}
                  </div>
                </span>
              </span>
              <span v-else-if="!vend.person_id">
                <span v-if="permissions.includes('admin-access vend-machines')" :class="[vend.customer_is_active || vend.is_testing ? 'text-gray-800' : 'text-gray-400']">
                  <a class="text-blue-700 hover:underline" target="_blank" :href="'/customers/' + vend.customer_id + '/edit'">{{ vend.customer_name }}</a>
                </span>
                <span v-else :class="[vend.customer_is_active || vend.is_testing ? 'text-gray-800' : 'text-gray-400']">
                  <a class="text-blue-700 hover:underline" target="_blank" :href="'/customers/' + vend.customer_id + '/edit'">{{ vend.customer_name }}</a>
                </span>
              </span>
              <div
                class="inline-flex rounded px-0.5 py-0.5 text-xs border w-fit hover:cursor-pointer bg-indigo-100 text-indigo-800 border-indigo-300"
                v-if="vend.selling_price_type"
              >
                RP{{ vend.selling_price_type }}
              </div>
              <Button
                type="button"
                class="bg-orange-400 hover:bg-orange-500 px-2 py-1 text-xs text-white flex space-x-1 items-center w-fit"
                @click="openLogModal(vend)"
              >
                <ClockIcon class="w-4 h-4" />
                <span>Log</span>
              </Button>
            </div>
          </TableData>
          <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
            <div class="flex flex-col items-center space-y-1">
              <a :href="'/vends/' + vend.vend_id + '/temp/' + 1 " target="_blank" class="w-full">
                  <button
                  type="button"
                  class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center"
                  :class="[(vend.is_online || vend.is_testing) && vend.is_temp_active ? (vend.temp > -15 ? 'bg-red-400 active:bg-red-500 hover:bg-red-600' : 'bg-green-400 active:bg-green-500 hover:bg-green-600') : 'bg-gray-300 active:bg-gray-500 hover:bg-gray-600']"
                  v-if="vend.temp_updated_at"
                  >
                      {{ vend.is_temp_error ? 'Error' : vend.temp }}
                  </button>
              </a>
              <!-- <button
                  type="button"
                  class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 w-4/5 text-right justify-center"
                  :class="[vend.is_online && vend.is_active ? (vend.temp > -15 ? 'bg-red-400 active:bg-red-500 hover:bg-red-600 text-black' : 'bg-green-400 active:bg-green-500 hover:bg-green-600 text-black') : 'bg-gray-300 active:bg-gray-500 hover:bg-gray-600 text-gray-700']"
                  @click="onVendTempClicked(vend.id, 1)"
                  v-if="vend.temp_updated_at"
              >
                  {{ vend.is_temp_error ? 'Error' : vend.temp }}
              </button> -->
              <a :href="'/vends/' + vend.vend_id + '/temp/' + 2 " target="_blank" class="w-full">
                  <button
                      type="button"
                      class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center"
                      :class="[(vend.is_online || vend.is_testing) && vend.is_temp_active ? (vend.temp > -15 || vend.parameterJson['t2'] == constTempError ? 'bg-red-400 active:bg-red-500 hover:bg-red-600' : 'bg-green-400 active:bg-green-500 hover:bg-green-600') : 'bg-gray-300 active:bg-gray-500 hover:bg-gray-600']"
                      v-if="vend.parameterJson && 't2' in vend.parameterJson"
                  >
                  <!-- @click="onVendTempClicked(vend.id, 2)" -->
                      {{ vend.parameterJson['t2'] == constTempError ? 'Error' : vend.parameterJson['t2']/10 }}(t2)
                  </button>
              </a>
              <a :href="'/vends/' + vend.vend_id + '/temp/' + 3 " target="_blank" class="w-full">
                  <button
                      type="button"
                      class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center"
                      :class="[(vend.is_online || vend.is_testing) && vend.is_temp_active ? (vend.temp > -15 || vend.parameterJson['t3'] == constTempError ? 'bg-red-400 active:bg-red-500 hover:bg-red-600' : 'bg-green-400 active:bg-green-500 hover:bg-green-600') : 'bg-gray-300 active:bg-gray-500 hover:bg-gray-600']"
                      v-if="vend.parameterJson && vend.parameterJson['t3'] && vend.parameterJson['t3'] != constTempError"
                  >
                      {{ vend.parameterJson['t3'] == constTempError ? 'Error' : vend.parameterJson['t3']/10 }}(t3)
                  </button>
              </a>
              <a :href="'/vends/' + vend.vend_id + '/temp/' + 4 " target="_blank" class="w-full">
                  <button
                      type="button"
                      class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center"
                      :class="[(vend.is_online || vend.is_testing) && vend.is_temp_active ? (vend.temp > -15 || vend.parameterJson['t4'] == constTempError ? 'bg-red-400 active:bg-red-500 hover:bg-red-600' : 'bg-green-400 active:bg-green-500 hover:bg-green-600') : 'bg-gray-300 active:bg-gray-500 hover:bg-gray-600']"
                      v-if="vend.parameterJson && vend.parameterJson['t4'] && vend.parameterJson['t4'] != constTempError"
                  >
                      {{ vend.parameterJson['t4'] == constTempError ? 'Error' : vend.parameterJson['t4']/10 }}(t4)
                  </button>
              </a>
              <span class="mt-1">
                  {{ vend.temp_updated_at }}
              </span>
              <span
                  class="mt-1 text-xs font-semibold"
                  :class="[vend.is_active || vend.is_testing ? (vend.t1_lowest_48h > -18 ? 'text-red-600' : (vend.t1_lowest_48h < -21 ? 'text-green-600' : 'text-blue-600')) : 'text-gray-400' ]"
                  v-if="vend.t1_lowest_48h !== null && vend.t1_lowest_48h !== undefined"
              >
                  {{ vend.t1_lowest_48h }}
              </span>
              <span
                  class="mt-1"
                  :class="[vend.is_active || vend.is_testing ? ((vend.temp - vend.parameterJson['t2']/10).toFixed(1) >= 4 ? 'text-red-700' : 'text-green-700') : 'text-gray-400' ]"
                  v-if="vend.parameterJson && vend.parameterJson['t2'] && vend.parameterJson['t2'] != constTempError && !vend.is_temp_error"
              >
                  {{ (vend.temp - vend.parameterJson['t2']/10).toFixed(1) }}
              </span>
              <!-- Fan RPM Section -->
              <div
                  v-if="!vend.is_fan_enabled"
                  class="flex flex-col items-center justify-center border border-gray-400 rounded-md p-1 min-w-[80px] bg-white text-gray-800"
                  v-tooltip="{ content: 'Fan Speed Signal Disabled' }"
              >
                  <span class="text-[10px] font-bold">Fan RPM</span>
                  <span>N/A</span>
              </div>
              <div
                  v-else-if="vend.parameterJson && 'fan' in vend.parameterJson"
                  class="flex flex-col items-center justify-center border rounded-md p-1 min-w-[80px]"
                  :class="[
                      (vend.is_online || vend.is_testing)
                        ? (vend.parameterJson['fan'] !== null && vend.parameterJson['fan'] !== undefined && vend.parameterJson['fan'] !== 'NaN'
                            ? (vend.parameterJson['fan'] > 0 ? 'bg-green-200 text-gray-800' : 'bg-red-200 text-gray-800')
                            : 'bg-gray-200 text-gray-500')
                        : 'bg-gray-300 text-gray-600'
                  ]"
                  v-tooltip="{ content: 'Fan Speed Signal exists' }"
              >
                  <span class="text-[10px] font-bold">Fan RPM</span>
                  <span>{{vend.parameterJson['fan']}}</span>
              </div>
              <div
                  v-else
                  class="flex flex-col items-center justify-center border rounded-md p-1 min-w-[80px] bg-gray-300 text-gray-600"
                  v-tooltip="{ content: 'Fan Speed Signal Missing' }"
              >
                  <span class="text-[10px] font-bold">Fan RPM</span>
                  <span>--</span>
              </div>

            </div>
          </TableData>
          <!-- Setting Chart version / Product Mapping cell -->
          <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
            <div class="flex flex-col space-y-1" :class="vend.is_active || vend.is_testing ? 'text-gray-900' : 'text-gray-400'">
              <!-- Setting Chart -->
              <span v-if="vend.vendConfig" class="flex flex-col space-y-0.5">
                <a :href="'/vend-configs/' + vend.vendConfig.id + '/edit'" target="_blank" class="text-blue-600 text-xs font-medium">
                  {{ vend.vendConfig.name }}
                </a>
                <span class="text-xs">
                  Current: {{ vend.vend_vend_config_version }}
                </span>
                <span class="text-xs" :class="[vend.vend_vend_config_version == vend.vendConfig.version ? 'text-gray-600' : 'text-red-600 font-bold']">
                  Latest: {{ vend.vendConfig.version }}
                </span>
              </span>
              <span v-else-if="vend.vend_config_name" class="text-xs">
                {{ vend.vend_config_name }}
              </span>
              <!-- Divider -->
              <div class="border-t border-gray-300 my-0.5"></div>
              <!-- Product Mapping -->
              <span class="flex flex-col space-y-0.5">
                <a v-if="vend.productMapping" :href="'/product-mappings/' + vend.productMapping.id + '/edit'" target="_blank" class="text-blue-600 text-xs font-medium">
                  {{ vend.productMapping.name }}
                </a>
                <span v-else-if="vend.product_mapping_name" class="text-xs">
                  {{ vend.product_mapping_name }}
                </span>
                <a v-if="vend.upcomingProductMapping" :href="'/product-mappings/' + vend.upcomingProductMapping.id + '/edit'" target="_blank" class="text-red-600 text-xs font-bold">
                  ↑ {{ vend.upcomingProductMapping.name }}
                </a>
              </span>
            </div>
          </TableData>
          <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
            <div>
              <span v-if="vend.modem_type_name" class="text-blue-800 flex flex-col space-y-1 items-center">
                <span>
                  {{ vend.modem_type_name }}
                </span>
                <span v-if="vend.modem_unit_imei">
                  {{ vend.modem_unit_imei }}
                </span>
                <span v-else>
                  N/A
                </span>
                <div
                    class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit"
                    :class="[vend.modem_unit_is_online ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']"
                >
                    <div class="flex flex-col">
                        <span class="font-bold" v-if="vend.modem_unit_last_updated_at">
                            {{vend.modem_unit_is_online ? 'Online' : 'Offline'}}
                        </span>
                        <span class="font-bold" v-else>
                            {{'N/A'}}
                        </span>
                        <span v-if="vend.modem_unit_last_updated_at">
                            {{vend.modem_unit_last_updated_at}}
                        </span>
                    </div>
                </div>
                <Button
                  type="button" class="bg-yellow-300 hover:bg-yellow-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
                  @click="onResetModemClicked(vend.modem_unit_id)"
                  v-if="vend.modem_type_is_resettable && vend.modem_unit_last_updated_at"
                >
                  <ArrowPathIcon class="w-4 h-4"></ArrowPathIcon>
                  <span>
                      Reset
                  </span>
                </Button>
              </span>
              <span v-else class="text-gray-400">
                N/A
              </span>
            </div>
          </TableData>
          <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-left">
            <div class="flex flex-col space-y-2">
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
                    <span :class="[vend.is_active || vend.is_testing ? 'text-black' : 'text-gray-600']">
                        #{{channel['code']}},
                    </span>
                    <span :class="[vend.is_active || vend.is_testing ? 'text-blue-600' : 'text-gray-500']">
                        {{channel['capacity'] - channel['qty']}},
                    </span>
                    <span :class="[vend.is_active || vend.is_testing ? (channel['qty'] <= 2 ? 'text-red-700' : 'text-green-700') : 'text-gray-400']">
                        {{channel['qty']}}/{{channel['capacity']}}
                    </span>
                </span>
                </li>
              </ul>
              <div class="flex flex-col space-y-1 pl-2 text-center">
                <span>
                  <div class="text-gray-800">
                    Cost: {{ operatorCountry.currency_symbol }}{{ vend.total_stock_cost ? vend.total_stock_cost.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : 0 }}
                  </div>
                </span>
                <span>
                  <div class="text-gray-800">
                    Value: {{ operatorCountry.currency_symbol }}{{ vend.total_stock_amount ? vend.total_stock_amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : 0 }}
                  </div>
                </span>
                <span>
                  <div class="text-gray-800">
                    Full Load Value: {{ operatorCountry.currency_symbol }}{{ vend.total_full_load_amount ? vend.total_full_load_amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : 0 }}
                  </div>
                </span>
                <span>
                  <div class="text-gray-800">
                    Stock Qty: {{ ((vend.vendChannelTotalsJson && vend.vendChannelTotalsJson['qty'] !== undefined) ? vend.vendChannelTotalsJson['qty'] : 0).toLocaleString(undefined, {minimumFractionDigits: 0}) }} pcs
                  </div>
                </span>
                <span>
                  <div class="text-gray-800">
                    Capacity: {{ ((vend.vendChannelTotalsJson && vend.vendChannelTotalsJson['capacity'] !== undefined) ? vend.vendChannelTotalsJson['capacity'] : 0).toLocaleString(undefined, {minimumFractionDigits: 0}) }} pcs
                  </div>
                </span>
              </div>
            </div>
          </TableData>
          <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
            <div class="flex flex-col space-y-1">
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
              <span
              v-if="vend.vendTransactionTotalsJson && 'two_days_error_rate' in vend.vendTransactionTotalsJson"
              :class="[
                  vend.is_active || vend.is_testing ?
                  (vend.vendTransactionTotalsJson['two_days_error_rate'] >= 3 ? 'text-red-700' : 'text-green-700') :
                  'text-gray-400'
              ]">
                  2d: {{vend.vendTransactionTotalsJson['two_days_error_rate'].toLocaleString(undefined, {minimumFractionDigits: 1, maximumFractionDigits: 1})}}%
                  ({{vend.vendTransactionTotalsJson['two_days_error_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}}/{{vend.vendTransactionTotalsJson['two_days_all_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}})
              </span>
              <span
              v-if="vend.vendTransactionTotalsJson && 'seven_days_error_rate' in vend.vendTransactionTotalsJson"
              :class="[
                  vend.is_active || vend.is_testing ?
                  (vend.vendTransactionTotalsJson['seven_days_error_rate'] >= 3 ? 'text-red-700' : 'text-green-700') :
                  'text-gray-400'
              ]">
                  7d: {{vend.vendTransactionTotalsJson['seven_days_error_rate'].toLocaleString(undefined, {minimumFractionDigits: 1, maximumFractionDigits: 1})}}%
                  ({{vend.vendTransactionTotalsJson['seven_days_error_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}}/{{vend.vendTransactionTotalsJson['seven_days_all_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}})
              </span>
            </div>
          </TableData>
          <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
            <div class="flex flex-col space-y-1">
              <span
                  v-if="vend.vendChannelTotalsJson"
                  :class="[vend.is_active || vend.is_testing ? (vend.balance_percent <= 20 ? 'text-red-700' : (vend.balance_percent > 50 ? 'text-green-700' : 'text-blue-700')) : 'text-gray-400']"
              >
                  {{ vend.vendChannelTotalsJson['qty'] }}/ {{ vend.vendChannelTotalsJson['capacity'] }} <br>
                  ({{ vend.balance_percent }}%)
              </span>
              <span
                  v-if="vend.vendChannelTotalsJson"
                  :class="[vend.is_active || vend.is_testing ? (100 - vend.out_of_stock_sku_percent <= 40 ? 'text-red-700' : (100 - vend.out_of_stock_sku_percent > 70 ? 'text-green-700' : 'text-blue-700')) : 'text-gray-400']"
              >
                  {{ vend.vendChannelTotalsJson['count'] - vend.vendChannelTotalsJson['outOfStockSku'] }}/ {{ vend.vendChannelTotalsJson['count'] }} <br>
                  ({{ 100 - vend.out_of_stock_sku_percent }}%)
              </span>
            </div>
          </TableData>
          <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
            <span
            v-if="vend.vendTransactionTotalsJson && 'today_amount' in vend.vendTransactionTotalsJson"
            :class="[
                vend.is_active || vend.is_testing ?
                ((vend.vendTransactionTotalsJson['today_amount']/ (Math.pow(10, operatorCountry.currency_exponent))) >= 30 ? 'text-green-700' : 'text-red-700') :
                'text-gray-400'
            ]">
                {{ operatorCountry.currency_symbol }}{{(vend.vendTransactionTotalsJson['today_amount'] / (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
                ({{vend.vendTransactionTotalsJson['today_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}})
            </span>
            <span
            v-if="vend.vendTransactionTotalsJson && 'yesterday_amount' in vend.vendTransactionTotalsJson"
            :class="[
                vend.is_active || vend.is_testing ?
                ((vend.vendTransactionTotalsJson['yesterday_amount']/ (Math.pow(10, operatorCountry.currency_exponent))) >= 30 ? 'text-green-700' : 'text-red-700') :
                'text-gray-400'
            ]">
                <br>
                {{ operatorCountry.currency_symbol }}{{(vend.vendTransactionTotalsJson['yesterday_amount']/ (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
                ({{vend.vendTransactionTotalsJson['yesterday_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}})
            </span>
            <span
            v-if="vend.vendTransactionTotalsJson && 'seven_days_amount' in vend.vendTransactionTotalsJson"
            :class="[
                vend.is_active || vend.is_testing ?
                ((vend.vendTransactionTotalsJson['seven_days_amount']/ (Math.pow(10, operatorCountry.currency_exponent))) > 200 ? 'text-green-700' : 'text-red-700') :
                'text-gray-400'
            ]">
                <br>
                {{ operatorCountry.currency_symbol }}{{(vend.vendTransactionTotalsJson['seven_days_amount']/ (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}({{vend.vendTransactionTotalsJson['seven_days_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}})
            </span>
            <span
            v-if="vend.vendTransactionTotalsJson && 'thirty_days_amount' in vend.vendTransactionTotalsJson"
            :class="[
                vend.is_active || vend.is_testing ?
                ((vend.vendTransactionTotalsJson['thirty_days_amount']/ (Math.pow(10, operatorCountry.currency_exponent))) > 1000 ? 'text-green-700' : 'text-red-700') :
                'text-gray-400'
            ]">
                <br>
                {{ operatorCountry.currency_symbol }}{{(vend.vendTransactionTotalsJson['thirty_days_amount']/ (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}({{vend.vendTransactionTotalsJson['thirty_days_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}})
            </span>
          </TableData>
          <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
            <div class="flex flex-col space-y-1">
              <div
                  class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                  :class="[vend.is_testing ? 'bg-gray-200' : (vend.is_active ? 'bg-green-200' : (vend.is_sold ? 'bg-yellow-200' : (vend.is_disposed ? 'bg-red-300' : 'bg-red-200')))]"
              >
                  <div class="flex flex-col">
                      <span class="font-bold">
                          {{vend.is_sold ? 'Sold' : (vend.is_disposed ? 'Disposed' : (vend.is_testing ? 'Factory (JB)' : (vend.is_active ? 'Active' : 'Not Active')))}}
                      </span>
                  </div>

              </div>
              <div
                  class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                  :class="[vend.is_active || vend.is_testing ? (vend.is_online ? 'bg-green-200' : 'bg-red-200') : 'bg-gray-200 text-gray-400']"
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
                  :class="[vend.is_active || vend.is_testing ? (vend.parameterJson['Sensor'] % 2 == 0 ? 'bg-red-200' : 'bg-green-200') : 'bg-gray-200 text-gray-400']"
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
                  class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                  :class="[vend.is_active || vend.is_testing ? (vend.parameterJson['door'] == 'close' ? 'bg-green-200' : 'bg-red-200') : 'bg-gray-200 text-gray-400']"
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
                  :class="[vend.is_active || vend.is_testing ? (vend.is_mqtt_active ? 'bg-green-200' : 'bg-gray-200') : 'bg-gray-200 text-gray-400']"
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
              <div
                class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full bg-green-200 text-gray-900"
                v-if="vend.acbVmcPaJson && 'TempLimit' in vend.acbVmcPaJson"
              >
                      <div class="flex flex-col">
                          <span class="font-bold">
                              Temp Limit
                          </span>
                          <span>
                              {{vend.acbVmcPaJson['TempLimit']}}
                          </span>
                      </div>
              </div>
            </div>
          </TableData>
          <!-- Payment Device separated cell -->
          <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
            <div class="flex flex-col space-y-1" :class="vend.is_active || vend.is_testing ? 'text-gray-900' : 'text-gray-400'">
              <div
                  class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                  :class="[vend.is_active || vend.is_testing ? (vend.acbVmcPaJson['QRCode'] == 1 ? 'bg-green-200' : 'bg-gray-200') : 'bg-gray-200 text-gray-400']"
                  v-if="vend.acbVmcPaJson && 'QRCode' in vend.acbVmcPaJson"
              >
                  <div class="flex flex-col"><span class="font-bold">QR Code</span><span>{{vend.acbVmcPaJson['QRCode'] == 1 ? 'Active' : 'NA'}}</span></div>
              </div>
              <div
                  class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                  :class="[vend.is_active || vend.is_testing ? (vend.parameterJson['BILLStat'] == 3 ? 'bg-green-200' : (vend.parameterJson['BILLStat'] == 1 ? 'bg-red-200' : 'bg-gray-200')) : 'bg-gray-200 text-gray-400']"
                  v-if="vend.parameterJson && 'BILLStat' in vend.parameterJson"
              >
                  <div class="flex flex-col"><span class="font-bold">Bill Acceptor</span><span>{{vend.parameterJson['BILLStat'] == 3 ? 'Active' : (vend.parameterJson['BILLStat'] == 1 ? 'Inactive' : 'NA')}}</span></div>
              </div>
              <div
                  class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                  :class="[vend.is_active || vend.is_testing ? (vend.parameterJson['CHGEStat'] == 3 ? 'bg-green-200' : (vend.parameterJson['CHGEStat'] == 1 ? 'bg-red-200' : 'bg-gray-200')) : 'bg-gray-200 text-gray-400']"
                  v-if="vend.parameterJson && 'CHGEStat' in vend.parameterJson"
              >
                  <div class="flex flex-col"><span class="font-bold">Coin Acceptor</span><span>{{vend.parameterJson['CHGEStat'] == 3 ? 'Active' : (vend.parameterJson['CHGEStat'] == 1 ? 'Inactive' : 'NA')}}</span></div>
              </div>
              <div
                  class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full bg-green-200 text-gray-800"
                  v-if="vend.acbVmcPaJson && 'CoinLimit' in vend.acbVmcPaJson"
              >
                  <div class="flex flex-col"><span class="font-bold">Coin Limit</span><span>{{vend.acbVmcPaJson['CoinLimit']}}</span></div>
              </div>
              <!-- Card Terminal (merged from former Cashless Status + Cashless Mfg badges). -->
              <div
                  class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                  :class="[vend.is_active || vend.is_testing ? (vend.card_terminal_name ? 'bg-green-200' : 'bg-gray-200') : 'bg-gray-200 text-gray-400']"
              >
                  <div class="flex flex-col"><span class="font-bold">Card Terminal</span><span>{{ vend.card_terminal_name ? vend.card_terminal_name : 'N/A' }}</span></div>
              </div>
            </div>
          </TableData>
          <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center" v-if="indexType === 'customers'">
            <span v-if="vend.cms_invoice_history && 'last_delivery_driver' in vend.cms_invoice_history" :class="[vend.is_active || vend.is_testing ? 'text-gray-900' : 'text-gray-400']">
                {{ vend.cms_invoice_history['last_delivery_driver'] }} <br>
            </span>
            <span :class="[vend.is_active || vend.is_testing ? 'text-gray-900' : 'text-gray-400']">
                {{ vend.last_invoice_date }}
                <div
                  class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full text-gray-900"
                  :class="[(vend.last_invoice_diff_count >= 5 && vend.last_invoice_diff_count < 7) ? 'bg-yellow-200' : (vend.last_invoice_diff_count >= 7 ? 'bg-red-200' : '') ]"
                  v-if="vend.last_invoice_diff"
                >
                  <span>
                    {{ vend.last_invoice_diff }}
                  </span>
                </div>
            </span>
          </TableData>
          <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center" v-if="indexType === 'customers'">
            <span v-if="vend.cms_invoice_history && 'next_delivery_driver' in vend.cms_invoice_history" :class="[vend.is_active || vend.is_testing ? 'text-gray-900' : 'text-gray-400']">
                  {{ vend.cms_invoice_history['next_delivery_driver'] }} <br>
            </span>
            <span  :class="[vend.is_active || vend.is_testing ? 'text-gray-900' : 'text-gray-400']">
                {{ vend.next_invoice_date }} <br>
                <div
                  class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full text-gray-900"
                  :class="[(vend.next_invoice_diff_count < 1 &&  vend.next_invoice_diff_count > 0) ? 'bg-green-200' : ((vend.next_invoice_diff_count > -1 && vend.next_invoice_diff_count < 0) ? 'bg-yellow-200' : '') ]"
                  v-if="vend.next_invoice_diff"
                >
                  <span>
                    {{ vend.next_invoice_diff }}
                  </span>
                </div>
            </span>
          </TableData>
          <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
            <div class="flex flex-col space-y-1">
              <span :class="[(vend.is_active || vend.is_testing) && vend.vendTransactionTotalsJson && 'vend_records_amount_average_day' in vend.vendTransactionTotalsJson ? (vend.virtual_vend_records_thirty_days_amount_average >= vend.vendTransactionTotalsJson['vend_records_amount_average_day']/100 ? 'text-green-700' : 'text-red-700') : 'text-gray-400']">
                {{ operatorCountry.currency_symbol }}{{ vend.virtual_vend_records_thirty_days_amount_average.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
              </span>
              <span
              v-if="vend.vendTransactionTotalsJson && 'vend_records_amount_latest' in vend.vendTransactionTotalsJson"
              :class="vend.is_active || vend.is_testing ? 'text-gray-900' : 'text-gray-400'"
              >
                {{ operatorCountry.currency_symbol }}{{(vend.vendTransactionTotalsJson['vend_records_amount_latest'] / (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0})}}
              </span>
              <span
                v-if="vend.begin_date"
                :class="vend.is_active || vend.is_testing ? 'text-gray-900' : 'text-gray-400'"
              >
                {{ vend.begin_date_short }}
              </span>
              <span
              v-if="vend.vendTransactionTotalsJson && 'vend_records_amount_average_day' in vend.vendTransactionTotalsJson"
              :class="[ vend.is_active || vend.is_testing ? getVendRecordsAmountAverageDayClass(vend.vendTransactionTotalsJson['vend_records_amount_average_day']) : 'text-gray-400']"
              >
                {{ operatorCountry.currency_symbol }}{{(vend.vendTransactionTotalsJson['vend_records_amount_average_day'] / (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
              </span>
              <span :class="vend.is_active || vend.is_testing ? 'text-gray-900' : 'text-gray-400'" v-if="indexType === 'customers'">
                {{ vend.postcode }}
              </span>
            </div>
          </TableData>
          <!-- Firmware / LCD / Operator merged cell -->
          <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
            <div class="flex flex-col space-y-1" :class="vend.is_active || vend.is_testing ? 'text-gray-900' : 'text-gray-400'">
              <!-- Firmware Ver -->
              <span v-if="vend.apkVerJson && 'deviceType' in vend.apkVerJson">
                {{ vend.apkVerJson['deviceType'] }}
              </span>
              <span>
                {{ vend.parameterJson && vend.parameterJson['Ver'] ? vend.parameterJson['Ver'].toString(16) : null }}
              </span>
              <span class="text-blue-600" v-if="vend.apkVerJson && 'apkver' in vend.apkVerJson">
                Apk: {{ vend.apkVerJson['apkver'] }}
                <span v-if="vend.apkVerJson && 'buildtime' in vend.apkVerJson">
                  {{ moment(new Date(vend.apkVerJson['buildtime'])).format('YYMMDD HH:mm:ss') }}
                </span>
              </span>
              <!-- LCD Monitor -->
              <span>
                {{ vend.modem_type }}
              </span>
              <span>
                {{ vend.lcd_monitor }}
              </span>
              <!-- Operator -->
              <span>{{ vend.operator_code }}</span>
              <!-- Location / Acc Manager (customers only) -->
              <span v-if="indexType === 'customers'">{{ vend.location_type_name }}</span>
              <span v-if="indexType === 'customers'">{{ vend.account_manager_name }}</span>
            </div>
          </TableData>

          <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
            <div class="flex justify-center space-x-1">
              <Link :href="'/vends/' + vend.vend_id + '/edit'">
                <Button
                type="button" class="bg-blue-300 hover:bg-blue-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
                >
                <EllipsisHorizontalCircleIcon class="w-4 h-4"></EllipsisHorizontalCircleIcon>
                <span>
                    more
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
<Create
  v-if="showCreateModal"
  :showModal="showCreateModal"
  :permissions="permissions"
  :type="type"
  @modalClose="onCreateModalClose"
>
</Create>
<Form
  v-if="showEditModal"
  :vend="vend"
  :type="type"
  :showModal="showEditModal"
  :permissions="permissions"
  @modalClose="onModalClose"
>
</Form>
<PickList
  v-if="showPickListModal"
  :pickLists="pickLists"
  :showModal="showPickListModal"
  @modalClose="onPickListModalClose"
>
</PickList>
<VendLogModal
  :open="showLogModal"
  :vend="logVend"
  @close="closeLogModal"
/>

</BreezeAuthenticatedLayout>
</template>

<style setup>
.quick-look
{
  -webkit-border-horizontal-spacing: 0px;
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
import DatePicker from '@/Components/DatePicker.vue';
import Form from '@/Pages/Vend/Form.vue';
import Paginator from '@/Components/Paginator.vue';
import PickList from '@/Pages/Vend/PickList.vue';
  import SearchInput from '@/Components/SearchInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import VendLogModal from '@/Components/VendLogModal.vue';
import { ArrowDownTrayIcon, ArrowPathIcon, ChevronDoubleDownIcon, ChevronDoubleUpIcon, ClockIcon, EllipsisHorizontalCircleIcon, ExclamationCircleIcon, MagnifyingGlassIcon, BackspaceIcon, PencilSquareIcon, ClipboardDocumentCheckIcon} from '@heroicons/vue/20/solid';
  import TableHead from '@/Components/TableHead.vue';
  import TableData from '@/Components/TableData.vue';
  import TableHeadSort from '@/Components/TableHeadSort.vue';
  import SingleSortItem from '@/Components/SingleSortItem.vue';
  import { ref, onMounted } from 'vue';
  import { router, Link, Head, usePage } from '@inertiajs/vue3';
  import moment from 'moment';
  import axios from 'axios';
  import { useToast } from "vue-toastification";

  const props = defineProps({
      cmsEndpoint: String,
      constTempError: Number,
      deliveryPlatformOptions: [Array, Object],
      deviceTypes: [Array, Object],
      indexType: String,
      lcdMonitorOptions: Object,
      locationTypeOptions: Object,
      modemTypeOptions: Object,
      modemUnitOptions: Object,
      nextDeliveryDriverOptions: [Array, Object],
      operatorOptions: Object,
      productOptions: Object,
      sellingPriceTypeOptions: [Array, Object],
      totals: [Array, Object],
      vends: Object,
      vendChannelErrors: Object,
      vendConfigOptions: Object,
      vendModelOptions: Object,
      vendPrefixOptions: Object,
  })

  const filters = ref({
      account_manager_name: '',
      allTempHigherThan: '',
      apk_ver: '',
      codes: '',
      coinLessThan: '',
      channel_codes: '',
      delivery_platform_id: '',
      serialNum: '',
      customer: '',
      deviceType: '',
      errors: [],
      firmware_ver: '',
      has_customer: '',
      locationType: '',
      operators: [],
      is_active: true,
      is_binded_customer: '',
      is_qr_code_active: '',
      tempHigherThan: '',
      tempLimitHigherThan: '',
      t2HigherThan: '',
      tempDeltaHigherThan: '',
      vend_channel_error_id: '',
      lcd_monitor_id: '',
      lastVisitedGreaterThan: '',
      modem_type_id: '',
      next_planned_date: '',
      next_planned_driver: '',
      is_mqtt: '',
      is_mqtt_active: '',
      is_online: '',
      is_sensor: '',
      //   is_testing: '',
      is_door_open: '',
      fan_rpm: '',
      balanceStockLessThan: '',
      remainingSkuLessThan: '',
      vendPrefixes: [],
      selling_price_type: '',
      status: '',
      sortKey: '',
      vendConfigs: [],
      vendModels: [],
      vendRecordsThirtyDaysAmountAverageLessThan: '',
      sortBy: true,
      numberPerPage: '',
      visited: true,
  })

  const authUser = usePage().props.auth.user
  const authOperator = usePage().props.auth.operator
  const baseUrl = ref(props.indexType === 'customers' ? '/vends/customers' : '/vends')
  const booleanOptions = ref([])
  const booleanStrictOptions = ref([])
  const deliveryPlatformOptions = ref([])
  const deviceTypeOptions = ref([])
  const doorOptions = ref([])
  const fanRpmOptions = ref([])
  const enableOptions = ref([])
  const isActiveFactoryOptions = ref([])
  const isShowOperationDiv = ref(false)
  const isSelectedAll = ref(false)
  const lcdMonitorOptions = ref([])
  const loading = ref(false)
  const loadingSyncNextDeliveryDate = ref(false)
  const locationTypeOptions = ref([])
  const modemTypeOptions = ref([])
  const modemUnitOptions = ref([])
  const nextDeliveryDriverOptions = ref([])
  const numberPerPageOptions = ref([])
  const operatorOptions = ref([])
  const pickLists = ref([])
  const sellingPriceTypeOptions = ref([])
  const showAllFilters = ref(false)
  const showChannelOverviewModal = ref(false)
  const showCreateModal = ref(false)
  const showEditModal = ref(false)
  const showPickListModal = ref(false)
  const showLogModal = ref(false)
  const statusOptions = ref([])
  const toast = useToast()
  const type = ref('')
  const vend = ref()
  const logVend = ref(null)

  const vends = ref(getVendsField())
  const vendChannelErrorsOptions = ref([])
  const vendConfigOptions = ref([])
  const vendModelOptions = ref([])
  const vendPrefixOptions = ref([])
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

  deviceTypeOptions.value =
  [
      {id: 'all', value: 'All'},
      ...Object.entries(props.deviceTypes).map(([id, name]) => ({id: id, value: name}))
  ]
booleanOptions.value = [
    {id: 'all', value: 'All'},
    {id: 'true', value: 'Yes'},
    {id: 'false', value: 'No'},
]
booleanStrictOptions.value = [
    {id: 'true', value: 'Yes'},
    {id: 'false', value: 'No'},
]
deliveryPlatformOptions.value = [
    {id: 'all', value: 'All'},
    ...props.deliveryPlatformOptions.data.map((data) => {return {id: data.id, value: data.name}})
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
fanRpmOptions.value = [
    {id: 'all', value: 'All'},
    {id: '0', value: '0'},
    {id: '>0', value: '>0'},
    {id: 'N/A', value: 'N/A'},
    {id: '--', value: '--'},
]
isActiveFactoryOptions.value = [
    {id: 'all', value: 'All'},
    {id: '1', value: 'Factory (JB)'},
    {id: '2', value: 'Active'},
    {id: '3', value: 'Not Active'},
    {id: '4', value: 'Disposed'},
    {id: '5', value: 'Sold'},
]
lcdMonitorOptions.value = [
    { id: 'undefined', value: 'Undefined'},
    ...Object.entries(props.lcdMonitorOptions).map(([id, name]) => ({id: id, value: name}))
]
locationTypeOptions.value = [
    {id: 'all', value: 'All'},
    ...props.locationTypeOptions.data.map((data) => {return {id: data.id, value: data.name}})
]
modemTypeOptions.value = [
  { id: 'undefined', name: 'Undefined'},
  ...props.modemTypeOptions.data.map(modemType => ({
    id: modemType.id,
    name: modemType.name,
  }))
];
modemUnitOptions.value = [
  { id: 'undefined', name: 'Undefined'},
  ...props.modemUnitOptions.data.map(modemUnit => ({
    id: modemUnit.id,
    name: modemUnit.imei,
  }))
];
nextDeliveryDriverOptions.value = [
    {id: 'all', value: 'All'},
    ...props.nextDeliveryDriverOptions.map((data) => {return {id: data.name, value: data.name}})
]
operatorOptions.value = [
    {id: 'all', full_name: 'All'},
    ...props.operatorOptions.data.map((data) => {return {id: data.id, code:data.code, full_name: data.full_name}})
]
sellingPriceTypeOptions.value = Object.entries(props.sellingPriceTypeOptions).map(([id, name]) => ({id: id, value: name}))

if(authUser.is_production_status_only) {
  statusOptions.value = [
    {id: 'factory', value: 'Factory (JB)'},
]
}else {
  statusOptions.value = [
    {id: 'all', value: 'All'},
    {id: 'factory', value: 'Factory (JB)'},
    {id: 'active', value: 'Active'},
    {id: 'inactive', value: 'Not Active'},
    {id: 'disposed', value: 'Disposed'},
    {id: 'sold', value: 'Sold'},
  ]
}

vendConfigOptions.value = [
    {id: 'all', value: 'All'},
    ...props.vendConfigOptions.data.map((data) => {return {id: data.id, value: data.name}})
]
vendModelOptions.value = [
    {id: 'all', value: 'All'},
    ...props.vendModelOptions.data.map((data) => {return {id: data.id, value: data.name}})
]

vendPrefixOptions.value = [
  {id: 'all', value: 'All'},
  {id: 'single-ud', value: 'Single UD'},
  ...props.vendPrefixOptions.data.map((data) => {return {id: data.id, value: data.name}})
]

filters.value.has_customer = booleanOptions.value[0]
filters.value.is_active = booleanOptions.value[1]
filters.value.delivery_platform_id = deliveryPlatformOptions.value[0]
filters.value.deviceType = deviceTypeOptions.value[0]
filters.value.is_door_open = doorOptions.value[0]
filters.value.fan_rpm = fanRpmOptions.value[0]
filters.value.is_mqtt = booleanOptions.value[0]
filters.value.is_mqtt_active = booleanOptions.value[0]
filters.value.is_online = booleanOptions.value[0]
filters.value.is_qr_code_active = booleanOptions.value[0]
filters.value.is_sensor = enableOptions.value[0]
filters.value.is_testing = booleanOptions.value[2]
// console.log(initBinded, roles[0])
filters.value.is_binded_customer = initBinded && (roles[0] == 'superadmin' || roles[0] == 'admin' ||  roles[0] == 'supervisor' || roles[0] == 'driver') ? booleanOptions.value[1] : booleanOptions.value[0]
filters.value.locationType = locationTypeOptions.value[0]
  filters.value.next_planned_driver = nextDeliveryDriverOptions.value[0]
  filters.value.operators = authOperator ? [
	operatorOptions.value.find(operator => operator.id === authOperator.id),
	...authOperator.code == 'HIPL' ? [
		operatorOptions.value.find(operator => operator.code == 'HIMD'),
		operatorOptions.value.find(operator => operator.code == 'LEA'),
		operatorOptions.value.find(operator => operator.code == 'HIESG'),
		operatorOptions.value.find(operator => operator.code == 'UL-ST'),
	] : [],
].filter(operator => operator !== undefined) : [operatorOptions.value[0]]
filters.value.status = statusOptions.value[0]
// vendOptions.value = props.vendOptions.data.map((vend) => {return {id: vend.id, code: vend.code}})
})

function getVendsField() {
  return {
      ...props.vends,
      data: props.vends.data.map((data) => {return {
          ...data,
          vendChannelsJson: props.indexType === 'customers' ? data.vend.vendChannelsJson : data.vendChannelsJson,
      }})
  }
}

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

function openLogModal(vendData) {
    logVend.value = vendData
    showLogModal.value = true
}

function closeLogModal() {
    showLogModal.value = false
    logVend.value = null
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

function onGeneratePickListClicked() {
  if(vends.value.data.some(vend => vend.is_selected == true)) {
      axios({
          method: 'POST',
          url: '/vends/pick-lists',
          data: vends.value.data.filter((vend) => { return vend.is_selected == true }),
      }).then(response => {
          pickLists.value = response.data
      }).catch(error => {
      }).finally(() => {
          showPickListModal.value = true
      })
  }
}

function onModalClose() {
    showEditModal.value = false
}

function onPickListModalClose() {
    showPickListModal.value = false
}

function onShowAllFiltersClicked() {
    showAllFilters.value = !showAllFilters.value
}

function onSearchFilterUpdated() {
  router.get(baseUrl.value, {
      ...filters.value,
      delivery_platform_id: filters.value.delivery_platform_id.id,
      deviceType: filters.value.deviceType.id,
      errors: filters.value.errors.map((error) => { return error.id }),
      fan_rpm: filters.value.fan_rpm.id,
      has_customer: filters.value.has_customer.id,
      lcd_monitor_id: filters.value.lcd_monitor_id.id,
      location_type_id: filters.value.locationType.id,
      modem_type_id: filters.value.modem_type_id.id,
      modem_unit_id: filters.value.modem_unit_id?.id,
      next_planned_date: filters.value.next_planned_date,
      next_planned_driver: filters.value.next_planned_driver.id,
      operators: filters.value.operators.filter(operator => operator).map((operator) => { return operator.id }),
      is_active: filters.value.is_active.id,
      is_binded_customer: filters.value.is_binded_customer.id,
      is_door_open: filters.value.is_door_open.id,
      is_mqtt: filters.value.is_mqtt.id,
      is_mqtt_active: filters.value.is_mqtt_active.id,
      is_online: filters.value.is_online.id,
      is_qr_code_active: filters.value.is_qr_code_active.id,
      is_sensor: filters.value.is_sensor.id,
      // is_testing: filters.value.is_testing.id,
      status: filters.value.status.id,
      vendConfigs: filters.value.vendConfigs.map((vc) => vc.id),
      vendModels: filters.value.vendModels.map((vendModel) => { return vendModel.id }),
      vendPrefixes: filters.value.vendPrefixes.map((vendPrefix) => { return vendPrefix.id }),
      numberPerPage: filters.value.numberPerPage.id,
  }, {
      preserveState: true,
      preserveScroll: true,
      // replace: true,
      onFinish: visit => {
          vends.value = getVendsField()
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

    const url = '/vends/' + vendId + '/temp/' + type

    window.open(url, '_blank')
    // router.get('/vends/' + vendId + '/temp/' + type)
}

function onIsShowOperationDivButtonClicked() {
      isShowOperationDiv.value = !isShowOperationDiv.value
}

function onResetModemClicked(modemUnitID) {
  const approval = confirm('Are you sure to reset this modem?');
  if (!approval) {
      return;
  }

  router.post('/modem-units/' + modemUnitID + '/reset', {}, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    onSuccess: () => {
      toast.success("Reset signal sent", {
        timeout: 3000
      });
    }
  })
}

function resetFilters() {
    router.get(baseUrl.value)
}


function sortTable(sortKey, inverse = false) {
filters.value.sortBy = !filters.value.sortBy
if(inverse && filters.value.sortKey != sortKey) {
    filters.value.sortBy = !filters.value.sortBy
}
filters.value.sortKey = sortKey
onSearchFilterUpdated()
}

function toggleSelectAll() {
  if(isSelectedAll.value) {
      vends.value.data.forEach((vend) => {
          vend.is_selected = true
      })
  } else {
      vends.value.data.forEach((vend) => {
          vend.is_selected = false
      })
  }
}

function onExportChannelExcelClicked() {
loading.value = true
axios({
    method: 'get',
    url: '/vends/channels/excel',
    params: {
        ...filters.value,
        delivery_platform_id: filters.value.delivery_platform_id.id,
        deviceType: filters.value.deviceType.id,
        errors: filters.value.errors.map((error) => { return error.id }),
        has_customer: filters.value.has_customer.id,
        lcd_monitor_id: filters.value.lcd_monitor_id.id,
        location_type_id: filters.value.locationType.id,
        modem_type_id: filters.value.modem_type_id.id,
        modem_unit_id: filters.value.modem_unit_id?.id,
        operators: filters.value.operators.filter(operator => operator).map((operator) => { return operator.id }),
        is_active: filters.value.is_active.id,
        is_binded_customer: filters.value.is_binded_customer.id,
        is_door_open: filters.value.is_door_open.id,
        is_mqtt: filters.value.is_mqtt.id,
        is_mqtt_active: filters.value.is_mqtt_active.id,
        is_online: filters.value.is_online.id,
        is_qr_code_active: filters.value.is_qr_code_active.id,
        is_sensor: filters.value.is_sensor.id,
        is_testing: filters.value.is_testing.id,
        status: filters.value.status.id,
        vendConfigs: filters.value.vendConfigs.map((vc) => vc.id),
        vendModels: filters.value.vendModels.map((vendModel) => { return vendModel.id }),
        vendPrefixes: filters.value.vendPrefixes.map((vendPrefix) => { return vendPrefix.id }),
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
