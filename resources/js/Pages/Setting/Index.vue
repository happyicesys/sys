<template>

  <Head title="VM Management" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Machine Management
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
        <div class="flex justify-end">
          <Link href="/settings/vend/create">
            <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            >
              <PlusIcon class="h-4 w-4" aria-hidden="true"/>
              <span>
                Create
              </span>
            </Button>
          </Link>
        </div>
          <!-- <div class="flex flex-col md:flex-row md:space-x-3 space-y-1 md:space-y-0"> -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
          <SearchInput placeholderStr="Vend ID" v-model="filters.codes" @keyup.enter="onSearchFilterUpdated()">
            Vend ID
            <span class="text-[9px]">
                ("," for multiple)
            </span>
          </SearchInput>
          <!-- <SearchInput placeholderStr="Cust ID" v-model="filters.customer_code" v-if="permissions.includes('admin-access vends')" @keyup.enter="onSearchFilterUpdated()">
            Cust ID
          </SearchInput>
          <SearchInput placeholderStr="Cust Name" v-model="filters.customer_name" v-if="permissions.includes('admin-access vends')" @keyup.enter="onSearchFilterUpdated()">
            Cust Name
          </SearchInput> -->
          <SearchInput placeholderStr="Customer" v-model="filters.customer" v-if="permissions.includes('admin-access vends')" @keyup.enter="onSearchFilterUpdated()">
            Customer
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
          <!-- <div v-if="permissions.includes('admin-access vends')">
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
          <div v-if="permissions.includes('admin-access vends')">
            <label for="text" class="block text-sm font-medium text-gray-700">
                Is Factory?
            </label>
            <MultiSelect
                v-model="filters.is_testing"
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
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
                Status
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
          <div>
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
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
                Cashless Terminal
            </label>
            <MultiSelect
                v-model="filters.cashless_terminal_id"
                :options="cashlessTerminalOptions"
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
                Simcard
            </label>
            <MultiSelect
                v-model="filters.simcard_id"
                :options="simcardOptions"
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
                Machine Prefix
            </label>
            <MultiSelect
                v-model="filters.vend_prefix_id"
                :options="vendPrefixOptions"
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
                Setting Chart
            </label>
            <MultiSelect
                v-model="filters.vend_config_id"
                :options="vendConfigOptions"
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
                Machine Model
            </label>
            <MultiSelect
                v-model="filters.vend_model_id"
                :options="vendModelOptions"
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
                Machine Key
            </label>
            <MultiSelect
                v-model="filters.key_id"
                :options="keyOptions"
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
                      Vend ID
                    </TableHeadSort>
                    <TableHead>
                      Prefix
                    </TableHead>
                    <TableHead>
                      Customer
                    </TableHead>
                    <TableHead>
                      Ref Price
                    </TableHead>
                    <TableHead>
                      Simcard
                    </TableHead>
                    <TableHead>
                      Cashless Terminal
                    </TableHead>
                    <TableHead>
                      Bill Acceptor
                    </TableHead>
                    <TableHead>
                      Coin Acceptor
                    </TableHead>
                    <TableHead>
                      Status
                    </TableHead>
                    <TableHead>
                      Model
                    </TableHead>
                    <TableHeadSort modelName="operator_code" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('operator_code')">
                      Operator
                    </TableHeadSort>
                    <TableHeadSort modelName="vend_serial_number_code" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_serial_number_code')">
                      Serial Num
                    </TableHeadSort>
                    <TableHead>
                      Setting Chart
                    </TableHead>
                    <TableHead>
                      Product Mapping <br>
                      (Current)
                    </TableHead>
                    <TableHead>
                      Product Mapping <br>
                      (Upcoming)
                    </TableHead>
                    <TableHead>
                      Deploy Date
                    </TableHead>
                    <TableHead>
                      Retired Date
                    </TableHead>
                    <TableHead>
                    </TableHead>
                  </tr>
                </thead>
                  <tbody class="bg-white">
                    <tr v-for="(vend, vendIndex) in vends.data" :key="vend.id" class="divide-x divide-gray-200">
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        {{ vends.meta.from + vendIndex }}
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        {{ vend.code }}
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        {{ vend.vendPrefix ? vend.vendPrefix.name : '' }}
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-left">
                        <a :class="[vend && vend.customer && vend.customer.person_id ? 'text-blue-700' : 'text-purple-700']" target="_blank" :href="vend.customer && vend.customer.person_id ? cmsEndpoint + '/person/' + vend.customer.person_id + '/edit' : (vend.customer ? '/customers/' + vend.customer.id + '/edit' : '#' )">
                          <span v-if="vend.customer && vend.customer.person_id && (vend.customer.virtual_customer_code || vend.customer.virtual_customer_prefix)">
                            <span v-if="vend.customer.virtual_customer_code">
                              {{ vend.customer.virtual_customer_code }}
                            </span>
                            <!-- <span v-if="vend.customer.virtual_customer_prefix">
                              ({{ vend.customer.virtual_customer_prefix }})
                            </span> -->
                             <br>
                          </span>
                          <span v-else>
                            <span v-if="vend.customer && vend.customer.code">
                              {{ vend.customer.code }} <br>
                            </span>
                          </span>
                          <span v-if="vend.customer">
                            {{ vend.customer.name }}
                          </span>
                        </a>
                        <!-- <span v-if="vend && vend.customer && vend.customer.person_id">
                          <a :class="[vend && vend.customer && vend.customer.person_id ? 'text-blue-700' : 'text-purple-700']" target="_blank" :href="'//admin.happyice.com.sg/person/' + vend.customer.person_id + '/edit'">
                            {{ vend.customer.virtual_customer_code }} ({{ vend.customer.virtual_customer_prefix }})
                            <br>
                            {{ vend.customer.name }}
                          </a>
                        </span>
                        <span v-else-if="vend && vend.customer && !vend.customer.person_id">
                            {{ vend.customer.code }}
                            <br>
                            {{ vend.customer.name }}
                        </span>
                        <span v-else>
                          {{ vend.name }}
                        </span> -->
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        {{ vend.customer ? vend.customer.selling_price_type : '' }}
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        {{ vend.simcard ? vend.simcard.code : '' }}
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        <div class="flex flex-col space-y-1">
                          <div
                              class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                              :class="[vend.is_active || vend.is_testing ? (vend.parameterJson['CSHLStat'] == 3 ? 'bg-green-200' : (vend.parameterJson['CSHLStat'] == 1 ? 'bg-red-200' : 'bg-gray-200')) : 'bg-gray-200 text-gray-400']"
                              v-if="vend.parameterJson && 'CSHLStat' in vend.parameterJson"
                          >
                              <div class="flex flex-col">
                                  <span class="font-bold">
                                      Cashless Status
                                  </span>
                                  <span>
                                      {{vend.parameterJson['CSHLStat'] == 3 ? 'Active' : (vend.parameterJson['CSHLStat'] == 1 ? 'Inactive' : 'NA') }}
                                  </span>
                              </div>
                          </div>
                          <div
                              class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                              :class="[vend.is_active || vend.is_testing ? (vend.acbVmcPaJson['CSHL_MFG'] ? 'bg-green-200' : 'bg-gray-200') : 'bg-gray-200 text-gray-400']"
                              v-if="vend.acbVmcPaJson && 'CSHL_MFG' in vend.acbVmcPaJson"
                          >
                              <div class="flex flex-col">
                                  <span class="font-bold">
                                      Cashless Mfg
                                  </span>
                                  <span>
                                      {{vend.acbVmcPaJson['CSHL_MFG'] ? vend.acbVmcPaJson['CSHL_MFG'] : 'NA' }}
                                  </span>
                              </div>
                          </div>
                          <div
                              class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                              :class="[vend.is_active || vend.is_testing ? (vend.acbVmcPaJson['CSHL_MDL'] ? 'bg-green-200' : 'bg-gray-200') : 'bg-gray-200 text-gray-400']"
                              v-if="vend.acbVmcPaJson && 'CSHL_MDL' in vend.acbVmcPaJson"
                          >
                              <div class="flex flex-col">
                                  <span class="font-bold">
                                      Cashless Model
                                  </span>
                                  <span>
                                      {{vend.acbVmcPaJson['CSHL_MDL'] ? vend.acbVmcPaJson['CSHL_MDL'] : 'NA' }}
                                  </span>
                              </div>
                          </div>
                          <div
                              class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                              :class="[vend.is_active || vend.is_testing ? (vend.acbVmcPaJson['CSHL_SN'] ? 'bg-green-200' : 'bg-gray-200') : 'bg-gray-200 text-gray-400']"
                              v-if="vend.acbVmcPaJson && 'CSHL_SN' in vend.acbVmcPaJson"
                          >
                              <div class="flex flex-col">
                                  <span class="font-bold">
                                      Cashless SN
                                  </span>
                                  <span>
                                      {{vend.acbVmcPaJson['CSHL_SN'] ? vend.acbVmcPaJson['CSHL_SN'] : 'NA' }}
                                  </span>
                              </div>
                          </div>
                        </div>
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        <div class="flex flex-col space-y-1">
                          <div
                              class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                              :class="[vend.is_active || vend.is_testing ? (vend.parameterJson['BILLStat'] == 3 ? 'bg-green-200' : (vend.parameterJson['BILLStat'] == 1 ? 'bg-red-200' : 'bg-gray-200')) : 'bg-gray-200 text-gray-400']"
                              v-if="vend.parameterJson && 'BILLStat' in vend.parameterJson"
                          >
                              <div class="flex flex-col">
                                  <span class="font-bold">
                                      Bill Acceptor
                                  </span>
                                  <span>
                                      {{vend.parameterJson['BILLStat'] == 3 ? 'Active' : (vend.parameterJson['BILLStat'] == 1 ? 'Inactive' : 'NA') }}
                                  </span>
                              </div>
                          </div>
                          <div
                              class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                              :class="[vend.is_active || vend.is_testing ? (vend.acbVmcPaJson['BILL_MFG'] ? 'bg-green-200' : 'bg-gray-200') : 'bg-gray-200 text-gray-400']"
                              v-if="vend.acbVmcPaJson && 'BILL_MFG' in vend.acbVmcPaJson"
                          >
                              <div class="flex flex-col">
                                  <span class="font-bold">
                                      Bill Mfg
                                  </span>
                                  <span>
                                      {{vend.acbVmcPaJson['BILL_MFG'] ? vend.acbVmcPaJson['BILL_MFG'] : 'NA' }}
                                  </span>
                              </div>
                          </div>
                          <div
                              class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                              :class="[vend.is_active || vend.is_testing ? (vend.acbVmcPaJson['BILL_MDL'] ? 'bg-green-200' : 'bg-gray-200') : 'bg-gray-200 text-gray-400']"
                              v-if="vend.acbVmcPaJson && 'BILL_MDL' in vend.acbVmcPaJson"
                          >
                              <div class="flex flex-col">
                                  <span class="font-bold">
                                      Bill Model
                                  </span>
                                  <span>
                                      {{ vend.acbVmcPaJson['BILL_MDL'] ? vend.acbVmcPaJson['BILL_MDL'] : 'NA' }}
                                  </span>
                              </div>
                          </div>
                          <div
                              class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                              :class="[vend.is_active || vend.is_testing ? (vend.acbVmcPaJson['BILL_SN'] ? 'bg-green-200' : 'bg-gray-200') : 'bg-gray-200 text-gray-400']"
                              v-if="vend.acbVmcPaJson && 'BILL_SN' in vend.acbVmcPaJson"
                          >
                              <div class="flex flex-col">
                                  <span class="font-bold">
                                      Bill SN
                                  </span>
                                  <span>
                                      {{vend.acbVmcPaJson['BILL_SN'] ? vend.acbVmcPaJson['BILL_SN'] : 'NA' }}
                                  </span>
                              </div>
                          </div>
                        </div>
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        <div class="flex flex-col space-y-1">
                          <div
                              class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                              :class="[vend.is_active || vend.is_testing ? (vend.parameterJson['CHGEStat'] == 3 ? 'bg-green-200' : (vend.parameterJson['CHGEStat'] == 1 ? 'bg-red-200' : 'bg-gray-200')) : 'bg-gray-200 text-gray-400']"
                              v-if="vend.parameterJson && 'CHGEStat' in vend.parameterJson"
                          >
                              <div class="flex flex-col">
                                  <span class="font-bold">
                                      Coin Acceptor
                                  </span>
                                  <span>
                                      {{vend.parameterJson['CHGEStat'] == 3 ? 'Active' : (vend.parameterJson['CHGEStat'] == 1 ? 'Inactive' : 'NA') }}
                                  </span>
                              </div>
                          </div>
                          <div
                            class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                            :class="[vend.is_active || vend.is_testing ? (vend.parameterJson['CoinCnt'] > 1600 ? 'bg-green-200' : 'bg-red-200') : 'bg-gray-200 text-gray-400']"
                            v-if="vend.parameterJson && vend.parameterJson['CoinCnt']"
                          >
                              <div class="flex flex-col">
                                  <span class="font-bold">
                                      Coin Float
                                  </span>
                                  <span>
                                      {{(vend.parameterJson['CoinCnt']/ (Math.pow(10, operatorCountry.currency_exponent))).toFixed(2)}}
                                  </span>
                              </div>
                          </div>
                          <div
                              class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full bg-green-200 text-gray-800"
                              v-if="vend.acbVmcPaJson && 'CoinLimit' in vend.acbVmcPaJson"
                          >
                              <div class="flex flex-col">
                                  <span class="font-bold">
                                      Coin Limit
                                  </span>
                                  <span>
                                      {{vend.acbVmcPaJson['CoinLimit']}}
                                  </span>
                              </div>
                          </div>
                          <div
                              class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                              :class="[vend.is_active || vend.is_testing ? (vend.acbVmcPaJson['COIN_MFG'] ? 'bg-green-200' : 'bg-gray-200') : 'bg-gray-200 text-gray-400']"
                              v-if="vend.acbVmcPaJson && 'COIN_MFG' in vend.acbVmcPaJson"
                          >
                              <div class="flex flex-col">
                                  <span class="font-bold">
                                      Coin Mfg
                                  </span>
                                  <span>
                                      {{vend.acbVmcPaJson['COIN_MFG'] ? vend.acbVmcPaJson['COIN_MFG'] : 'NA' }}
                                  </span>
                              </div>
                          </div>
                          <div
                              class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                              :class="[vend.is_active || vend.is_testing ? (vend.acbVmcPaJson['COIN_MDL'] ? 'bg-green-200' : 'bg-gray-200') : 'bg-gray-200 text-gray-400']"
                              v-if="vend.acbVmcPaJson && 'COIN_MDL' in vend.acbVmcPaJson"
                          >
                              <div class="flex flex-col">
                                  <span class="font-bold">
                                      Coin Model
                                  </span>
                                  <span>
                                      {{vend.acbVmcPaJson['COIN_MDL'] ? vend.acbVmcPaJson['COIN_MDL'] : 'NA' }}
                                  </span>
                              </div>
                          </div>
                          <div
                              class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                              :class="[vend.is_active || vend.is_testing ? (vend.acbVmcPaJson['COIN_SN'] ? 'bg-green-200' : 'bg-gray-200') : 'bg-gray-200 text-gray-400']"
                              v-if="vend.acbVmcPaJson && 'COIN_SN' in vend.acbVmcPaJson"
                          >
                              <div class="flex flex-col">
                                  <span class="font-bold">
                                      Coin SN
                                  </span>
                                  <span>
                                      {{vend.acbVmcPaJson['COIN_SN'] ? vend.acbVmcPaJson['COIN_SN'] : 'NA' }}
                                  </span>
                              </div>
                          </div>
                        </div>
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        <div class="flex flex-col space-y-1">
                          <div
                            class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                            :class="[vend.is_testing ? 'bg-gray-200' : (vend.is_active ? 'bg-green-200' : 'bg-red-200')]"
                          >
                            <div class="flex flex-col">
                              <span class="font-bold">
                                {{vend.is_testing ? 'Factory' : (vend.is_active ? 'Active' : 'Not Active')}}
                              </span>
                            </div>
                          </div>
                        </div>
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        {{ vend.vendModel ? vend.vendModel.name : '' }}
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        {{ vend.operator_code }}
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        {{ vend.vend_serial_number_code }}
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        <span v-if="vend.vendConfig">
                          <a :href="'/vend-configs/' + vend.vendConfig.id + '/edit'" target="_blank" class="text-blue-600">
                            {{ vend.vendConfig.name }}
                          </a>
                        </span>
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        <span v-if="vend.productMapping">
                          <a :href="'/product-mappings/' + vend.productMapping.id + '/edit'" target="_blank" class="text-blue-600">
                            {{ vend.productMapping.name }}
                          </a>
                        </span>
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        <span v-if="vend.upcomingProductMapping">
                          <a :href="'/product-mappings/' + vend.upcomingProductMapping.id + '/edit'" target="_blank" class="text-blue-600">
                            {{ vend.upcomingProductMapping.name }}
                          </a>
                        </span>
                        <span v-else>
                          <span v-if="vend.productMapping && vend.productMapping.upcomingProductMappings">
                            <a
                              v-for="upcomingProductMapping in vend.productMapping.upcomingProductMappings"
                              :key="upcomingProductMapping.id"
                              :href="'/product-mappings/' + upcomingProductMapping.id + '/edit'"
                              class="text-red-600 flex flex-col space-y-1"
                              target="_blank"
                            >
                              {{ upcomingProductMapping.name }}
                            </a>
                          </span>
                        </span>
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        {{ vend.begin_date_short }}
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        {{ vend.termination_date_short }}
                      </TableData>

                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        <div class="flex justify-center space-x-1">
                          <Link :href="'/settings/vend/' + vend.id + '/update'">
                            <Button
                              type="button" class="bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
                            >
                              <PencilSquareIcon class="w-4 h-4"></PencilSquareIcon>
                              <span>
                                  Edit
                              </span>
                            </Button>
                          </Link>
                          <!-- <Button
                            type="button" class="bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
                            @click="onEditClicked(vend)"
                          >
                            <PencilSquareIcon class="w-4 h-4"></PencilSquareIcon>
                            <span>
                                Edit
                            </span>
                          </Button> -->
                          <!-- <Button
                            type="button" class="bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1"
                            @click="onDeleteClicked(vend)"
                          >
                            <TrashIcon class="w-4 h-4"></TrashIcon>
                            <span>
                                Delete
                            </span>
                          </Button> -->
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
import { BackspaceIcon, MagnifyingGlassIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ref, onMounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    cashlessTerminalOptions: Object,
    categories: Object,
    categoryGroups: Object,
    cmsEndpoint: String,
    keyOptions: Object,
    locationTypeOptions: Object,
    operatorOptions: Object,
    sellingPriceTypeOptions: [Array, Object],
    simcardOptions: Object,
    vends: Object,
    vendConfigOptions: Object,
    vendPrefixOptions: Object,
    vendModelOptions: Object,
  })

const filters = ref({
    cashless_terminal_id: '',
    codes: '',
    customer_code: '',
    customer_name: '',
    categories: [],
    categoryGroups: [],
    key_id: '',
    locationType: '',
    operators: [],
    selling_price_type: '',
    simcard_id: '',
    // is_active: '',
    is_binded_customer: '',
    // is_testing: '',
    vend_config_id: '',
    vend_model_id: '',
    vend_prefix_id: '',
    sortKey: '',
    sortBy: true,
    status: '',
    numberPerPage: '',
    visited: true,
  })
  const authOperator = usePage().props.auth.operator
  const booleanOptions = ref([])
  const categoryOptions = ref([])
  const categoryGroupOptions = ref([])
  const cashlessTerminalOptions = ref([])
  const initBinded = usePage().props.initBinded
  const keyOptions = ref([])
  const loading = ref(false)
  const locationTypeOptions = ref([])
  const numberPerPageOptions = ref([])
  const operatorOptions = ref([])
  const type = ref('')
  const vend = ref()
  const operatorCountry = usePage().props.auth.operatorCountry
  const operatorRole = usePage().props.auth.operatorRole
  const sellingPriceTypeOptions = ref([])
  const simcardOptions = ref([])
  const permissions = usePage().props.auth.permissions
  const roles = usePage().props.auth.roles
  const vendConfigOptions = ref([])
  const vendModelOptions = ref([])
  const vendPrefixOptions = ref([])
  const now = ref(moment().format('HH:mm:ss'))
  const statusOptions = ref([
    {id: 'all', value: 'All'},
    {id: 'factory', value: 'Factory'},
    {id: 'active', value: 'Active'},
    {id: 'inactive', value: 'Inactive'},
])

onMounted(() => {
    numberPerPageOptions.value = [
        { id: 100, value: 100 },
        { id: 200, value: 200 },
        { id: 500, value: 500 },
        { id: 'All', value: 'All' },
    ]
    filters.value.numberPerPage = numberPerPageOptions.value[0]

    cashlessTerminalOptions.value = [
        {id: 'all', value: 'All'},
        ...props.cashlessTerminalOptions.data.map((data) => {return {id: data.id, value: data.code}})
    ]
    categoryOptions.value = props.categories.data.map((data) => {return {id: data.id, name: data.name}})
    categoryGroupOptions.value = props.categoryGroups.data.map((data) => {return {id: data.id, name: data.name}})
    booleanOptions.value = [
        {id: 'all', value: 'All'},
        {id: 'true', value: 'Yes'},
        {id: 'false', value: 'No'},
    ]
    keyOptions.value = [
        {id: 'all', value: 'All'},
        ...props.keyOptions.data.map((data) => {return {id: data.id, value: data.name}})
    ]
    locationTypeOptions.value = [
        {id: 'all', value: 'All'},
        ...props.locationTypeOptions.data.map((data) => {return {id: data.id, value: data.name}})
    ]
    operatorOptions.value = [
        {id: 'all', full_name: 'All'},
        ...props.operatorOptions.data.map((data) => {return {id: data.id, code:data.code, full_name: data.full_name}})
    ]
    sellingPriceTypeOptions.value = Object.entries(props.sellingPriceTypeOptions).map(([id, name]) => ({id: id, value: name}))
    simcardOptions.value = [
        {id: 'all', value: 'All'},
        ...props.simcardOptions.data.map((data) => {return {id: data.id, value: data.code}})
    ]
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
        ...props.vendPrefixOptions.data.map((data) => {return {id: data.id, value: data.name}})
    ]

    filters.value.locationType = locationTypeOptions.value[0]
    filters.value.operators = authOperator ? [
		operatorOptions.value.find(operator => operator.id === authOperator.id),
		...authOperator.code == 'HIPL' ? [operatorOptions.value.find(operator => operator.code == 'HIMD')] : [],
	] : operatorOptions.value[0]

    // filters.value.is_active = booleanOptions.value[1]
    filters.value.is_binded_customer = initBinded && (roles[0] == 'superadmin' || roles[0] == 'admin' ||  roles[0] == 'supervisor' || roles[0] == 'driver') ? booleanOptions.value[1] : booleanOptions.value[0]
    // filters.value.is_testing = booleanOptions.value[2]
    filters.value.key_id = keyOptions.value[0]
    filters.value.status = statusOptions.value[2]
    filters.value.cashless_terminal_id = cashlessTerminalOptions.value[0]
    filters.value.simcard_id = simcardOptions.value[0]
    filters.value.vend_config_id = vendConfigOptions.value[0]
    filters.value.vend_model_id = vendModelOptions.value[0]
    filters.value.vend_prefix_id = vendPrefixOptions.value[0]
})

function onCreateClicked() {
  type.value = 'create'
  vend.value = null
  showModal.value = true
}

function onDeleteClicked(vend) {
  const approval = confirm('Are you sure to delete ' + vend.name + '?');
  if (!approval) {
      return;
  }
  router.delete('/vends/' + vend.id)
}

function onSearchFilterUpdated() {
  router.get('/settings', {
      ...filters.value,
      categories: filters.value.categories.map((category) => { return category.id }),
      categoryGroups: filters.value.categoryGroups.map((categoryGroup) => { return categoryGroup.id }),
      cashless_terminal_id: filters.value.cashless_terminal_id.id,
      location_type_id: filters.value.locationType.id,
      operators: filters.value.operators.map((operator) => { return operator.id }),
      is_binded_customer: filters.value.is_binded_customer.id,
      key_id: filters.value.key_id.id,
      selling_price_type: filters.value.selling_price_type.id,
      simcard_id: filters.value.simcard_id.id,
      status: filters.value.status.id,
      vend_config_id: filters.value.vend_config_id.id,
      vend_model_id: filters.value.vend_model_id.id,
      vend_prefix_id: filters.value.vend_prefix_id.id,
      numberPerPage: filters.value.numberPerPage.id,
  }, {
      preserveState: true,
      replace: true,
  })
}

function resetFilters() {
  router.get('/settings')
}

function sortTable(sortKey) {
  filters.value.sortKey = sortKey
  filters.value.sortBy = !filters.value.sortBy
  onSearchFilterUpdated()
}
</script>