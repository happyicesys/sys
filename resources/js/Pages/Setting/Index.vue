<template>

  <Head title="VM Management" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Machine Settings
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
          <SearchInput placeholderStr="Machine ID" v-model="filters.codes" @keyup.enter="onSearchFilterUpdated()">
            Machine ID
            <span class="text-[9px]">
                ("," for multiple)
            </span>
          </SearchInput>
          <!-- <SearchInput placeholderStr="Cust ID" v-model="filters.customer_code" v-if="permissions.includes('admin-access vend-settings')" @keyup.enter="onSearchFilterUpdated()">
            Cust ID
          </SearchInput>
          <SearchInput placeholderStr="Cust Name" v-model="filters.customer_name" v-if="permissions.includes('admin-access vend-settings')" @keyup.enter="onSearchFilterUpdated()">
            Cust Name
          </SearchInput> -->
          <SearchInput placeholderStr="Customer" v-model="filters.customer" v-if="permissions.includes('admin-access vend-settings')" @keyup.enter="onSearchFilterUpdated()">
            Customer
          </SearchInput>
          <div v-if="permissions.includes('admin-access vend-settings')">
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
          <div v-if="permissions.includes('admin-access vend-settings')">
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
          <!-- <div v-if="permissions.includes('admin-access vend-settings')">
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
          <!-- <div v-if="permissions.includes('admin-access vend-settings')">
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
          <div v-if="permissions.includes('admin-access vend-settings')">
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
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
                Location Type
            </label>
            <MultiSelect
                v-model="filters.locationTypes"
                :options="locationTypeOptions"
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
                v-model="filters.cashless_mfg"
                :options="cashlessMfgOptions"
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
          <div>
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
          <div>
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
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
              LED Matrix Panel
            </label>
            <MultiSelect
                v-model="filters.led_matrix_panel_id"
                :options="ledMatrixPanelOptions"
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
          <div>
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
                class="mt-1"
                mode="tags"
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
          <SearchInput placeholderStr="Serial Num" v-model="filters.vend_serial_number_code" v-if="permissions.includes('admin-access vend-settings')" @keyup.enter="onSearchFilterUpdated()">
            Serial Num
          </SearchInput>
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
                    <TableHeadSort modelName="vend_models.name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_models.name')">
                      Model
                    </TableHeadSort>
                    <TableHeadSort modelName="code" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('code')">
                      Machine ID
                    </TableHeadSort>
                    <TableHeadSort modelName="vend_config_name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_config_name')">
                      Setting Chart
                    </TableHeadSort>
                    <TableHeadSort modelName="vend_prefix_name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_prefix_name')">
                      Prefix
                    </TableHeadSort>
                    <TableHead>
                      <div class="flex flex-col space-y-1">
                        <SingleSortItem modelName="customer_code" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('customer_code')">
                          Customer
                        </SingleSortItem>
                        <SingleSortItem modelName="selling_price_type" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('selling_price_type', false)">
                          Ref Price
                        </SingleSortItem>
                      </div>
                    </TableHead>
                    <TableHeadSort modelName="product_mapping_name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('product_mapping_name')">
                      Product Mapping <br>
                      (Current)
                    </TableHeadSort>
                    <TableHeadSort modelName="upcoming_product_mapping_name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('upcoming_product_mapping_name')">
                      Product Mapping <br>
                      (Upcoming)
                    </TableHeadSort>
                    <TableHead>
                      <div class="flex flex-col space-y-2">
                        <span>
                          Machine Status
                        </span>
                        <SingleSortItem modelName="key_name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('key_name')">
                          Machine Key
                        </SingleSortItem>
                      </div>
                    </TableHead>
                    <TableHead>
                      Bill Acceptor
                    </TableHead>
                    <TableHead>
                      Coin Acceptor
                    </TableHead>
                    <TableHead>
                      Cashless Terminal
                    </TableHead>
                    <TableHead>
                      Modem
                    </TableHead>
                    <TableHead>
                      <div class="flex flex-col space-y-2">
                        <span>
                          LCD Monitor
                        </span>
                        <span>
                          LED Matrix Panel
                        </span>
                      </div>
                    </TableHead>
                    <TableHead>
                      Simcard
                    </TableHead>
                    <TableHead>
                      <div class="flex flex-col space-y-2">
                        <SingleSortItem modelName="vend_serial_number_code" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_serial_number_code')">
                          Serial Num
                        </SingleSortItem>
                        <SingleSortItem modelName="operator_code" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('operator_code')">
                          Operator
                        </SingleSortItem>
                      </div>
                    </TableHead>
                    <TableHeadSort modelName="begin_date" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('begin_date')">
                      Deploy Date
                    </TableHeadSort>
                    <TableHeadSort modelName="termination_date" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('termination_date')">
                      Retired Date
                    </TableHeadSort>
                    <TableHead>
                    </TableHead>
                  </tr>
                </thead>
                  <tbody class="bg-white">
                    <tr v-for="(vend, vendIndex) in vends.data" :key="vend.id" class="divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100">
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        {{ vends.meta.from + vendIndex }}
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        {{ vend.vendModel ? vend.vendModel.name : '' }}
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        <div class="flex flex-col space-y-2 items-center">
                          <Link :href="'/settings/vend/' + vend.id + '/update'">
                            <span class="text-blue-600">
                              {{ vend.code }}
                            </span>
                          </Link>
                          <div
                            class="inline-flex rounded px-0.5 py-0.5 text-xs border w-fit bg-yellow-100 text-yellow-800 border-yellow-300 max-w-48"
                            v-if="vend.label_name"
                          >
                            {{ vend.label_name }}
                          </div>
                        </div>
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        <span v-if="vend.vendConfig" class="flex flex-col space-y-1">
                          <a :href="'/vend-configs/' + vend.vendConfig.id + '/edit'" target="_blank" class="text-blue-600">
                            <span>
                              {{ vend.vendConfig.name }}
                            </span>
                          </a>
                          <span>
                            Current Ver: {{ vend.vend_vend_config_version }}
                          </span>
                          <span v-if="vend.vendConfig" :class="[vend.vend_vend_config_version == vend.vendConfig.version ? 'text-gray-800' : 'text-red-600']">
                            Latest Ver: {{ vend.vendConfig.version }}
                          </span>
                        </span>
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        {{ vend.vendPrefix ? vend.vendPrefix.name : '' }}
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-left">
                        <div class="flex flex-col space-y-2">
                          <a :class="[vend && vend.customer && vend.customer.person_id ? 'text-blue-700' : 'text-purple-700']" target="_blank" :href="'/customers/' + vend.customer.id + '/edit'" v-if="vend.customer">
                            <span v-if="vend.customer.person_id && (vend.customer.virtual_customer_code || vend.customer.virtual_customer_prefix)">
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
                          <div
                            class="inline-flex rounded px-0.5 py-0.5 text-xs border w-fit bg-indigo-100 text-indigo-800 border-indigo-300"
                            v-if="vend.customer && vend.customer.selling_price_type"
                          >
                            RP{{ vend.customer.selling_price_type }}
                          </div>
                          <span v-if="vend.deliveryProductMappingVends" v-for="(deliveryProductMappingVend, index) in vend.deliveryProductMappingVends">
                            <div
                                class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit text-gray-800 bg-green-400"
                                v-if="deliveryProductMappingVend.deliveryProductMapping && deliveryProductMappingVend.deliveryProductMapping.deliveryPlatformOperator && deliveryProductMappingVend.deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform"
                            >
                              {{ deliveryProductMappingVend.deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform.name }}
                            </div>
                          </span>
                        </div>
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
                        <div class="flex flex-col space-y-1">
                          <div
                            class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                            :class="[vend.is_testing ? 'bg-gray-200' : (vend.is_active ? 'bg-green-200' : 'bg-red-200')]"
                          >
                            <div class="flex flex-col">
                              <span class="font-bold">
                                {{vend.is_testing ? 'Factory (JB)' : (vend.is_active ? 'Active' : 'Not Active')}}
                              </span>
                            </div>
                          </div>
                          <span>
                            {{ vend.key_name }}
                          </span>
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
                        <div class="flex flex-col space-y-2">
                          <span>
                            {{ vend.modemType?.name }}
                          </span>
                          <span v-if="vend.modemUnit">
                            {{ vend.modemUnit.imei }}
                          </span>
                        </div>
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        <div class="flex flex-col space-y-2">
                          <span>
                            {{ vend.lcd_monitor }}
                          </span>
                          <span v-if="vend.led_matrix_panel_id">
                            {{ vend.led_matrix_panel_id == 1 ? 'Hard' : 'Soft' }}
                          </span>
                        </div>
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        {{ vend.simcard ? vend.simcard.code : '' }}
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        <div class="flex flex-col space-y-2">
                          <span>
                            {{ vend.vend_serial_number_code }}
                          </span>
                          <span>
                            {{ vend.operator_code }}
                          </span>
                        </div>
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        {{ vend.begin_date_short }}
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        {{ vend.termination_date_short }}
                      </TableData>

                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        <div class="flex flex-col justify-center space-y-1">
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
                          <Button
                            type="button"
                            class="bg-orange-400 hover:bg-orange-500 px-3 py-2 text-xs text-white flex space-x-1 items-center"
                            @click="openLogModal(vend)"
                          >
                            <ClockIcon class="w-4 h-4" />
                            <span>
                                Log
                            </span>
                          </Button>
                          <!-- <Link :href="'/settings/vend/' + vend.id + '/parameter'">
                            <Button
                              type="button" class="bg-blue-300 hover:bg-blue-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
                            >
                              <AdjustmentsHorizontalIcon class="w-4 h-4"></AdjustmentsHorizontalIcon>
                              <span>
                                  Parameters
                              </span>
                            </Button>
                          </Link> -->
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
  <VendLogModal :open="historyModalOpen" :vend="historyVend" @close="closeHistoryModal" />
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import VendLogModal from '@/Components/VendLogModal.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { AdjustmentsHorizontalIcon, BackspaceIcon, ClockIcon, MagnifyingGlassIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/20/solid';
import SingleSortItem from '@/Components/SingleSortItem.vue';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ref, onMounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";

const props = defineProps({
    cashlessTerminalOptions: Object,
    categories: Object,
    categoryGroups: Object,
    cashlessMfgOptions: [Array, Object],
    cmsEndpoint: String,
    deliveryPlatformOptions: Object,
    keyOptions: Object,
    lcdMonitorOptions: Object,
    ledMatrixPanelOptions: Object,
    locationTypeOptions: Object,
    modemTypeOptions: [Array, Object],
    modemUnitOptions: [Array, Object],
    operatorOptions: Object,
    sellingPriceTypeOptions: [Array, Object],
    simcardOptions: Object,
    vends: Object,
    vendConfigOptions: Object,
    vendContractOptions: Object,
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
    cashless_mfg: '',
    delivery_platform_id: '',
    key_id: '',
    lcd_monitor_id: '',
    led_matrix_panel_id: '',
    locationTypes: [],
    modem_type_id: '',
    modem_unit_id: '',
    operators: [],
    selling_price_type: '',
    simcard_id: '',
    // is_active: '',
    is_binded_customer: '',
    // is_testing: '',
    vendConfigs: [],
    vendModels: [],
    vendPrefixes: [],
    vend_serial_number_code: '',
    sortKey: '',
    sortBy: false,
    status: '',
    numberPerPage: '',
    visited: true,
  })
  const authOperator = usePage().props.auth.operator
  const booleanOptions = ref([])
  const categoryOptions = ref([])
  const categoryGroupOptions = ref([])
  const cashlessMfgOptions = ref([])
  const cashlessTerminalOptions = ref([])
  const deliveryPlatformOptions = ref([])
  const initBinded = usePage().props.initBinded
  const keyOptions = ref([])
  const loading = ref(false)
  const locationTypeOptions = ref([])
  const modemTypeOptions = ref([])
  const modemUnitOptions = ref([])
  const numberPerPageOptions = ref([])
  const type = ref('')
  const vend = ref()
  const toast = useToast()
  const lcdMonitorOptions = ref([])
  const ledMatrixPanelOptions = ref([])
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
    {id: 'factory', value: 'Factory (JB)'},
    {id: 'active', value: 'Active'},
    {id: 'inactive', value: 'Inactive'},
    {id: 'disposed', value: 'Disposed'},
])
const historyModalOpen = ref(false)
const historyVend = ref(null)

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
    cashlessMfgOptions.value = [
        {id: 'all', value: 'All'},
        ...Object.entries(props.cashlessMfgOptions).map(([id, name]) => ({id: id, value: name}))
    ]
    categoryOptions.value = props.categories.data.map((data) => {return {id: data.id, name: data.name}})
    categoryGroupOptions.value = props.categoryGroups.data.map((data) => {return {id: data.id, name: data.name}})
    deliveryPlatformOptions.value = [
        {id: 'all', value: 'All'},
        ...props.deliveryPlatformOptions.data.map((data) => {return {id: data.id, value: data.name}})
    ]
    booleanOptions.value = [
        {id: 'all', value: 'All'},
        {id: 'true', value: 'Yes'},
        {id: 'false', value: 'No'},
    ]
    keyOptions.value = [
        {id: 'all', value: 'All'},
        ...props.keyOptions.data.map((data) => {return {id: data.id, value: data.name}})
    ]
    lcdMonitorOptions.value = [
        { id: 'undefined', value: 'Undefined'},
        ...Object.entries(props.lcdMonitorOptions).map(([id, name]) => ({id: id, value: name}))
    ]
    ledMatrixPanelOptions.value = [
        { id: 'undefined', value: 'Undefined'},
        ...Object.entries(props.ledMatrixPanelOptions).map(([id, name]) => ({id: id, value: name}))
    ]
    locationTypeOptions.value = [
        {id: 'all', value: 'All'},
        ...props.locationTypeOptions.data.map((data) => {return {id: data.id, value: data.name}})
    ]
    modemTypeOptions.value = [
      ...props.modemTypeOptions.data.map(modemType => ({
        id: modemType.id,
        name: modemType.name,
      }))
    ];
    modemUnitOptions.value = [
      ...props.modemUnitOptions.data.map(modemUnit => ({
        id: modemUnit.id,
        name: modemUnit.imei,
      }))
    ];
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
        {id: 'single-ud', value: 'Single UD'},
        ...props.vendPrefixOptions.data.map((data) => {return {id: data.id, value: data.name}})
    ]

    // filters.value.locationType = locationTypeOptions.value[0]
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

      filters.value.delivery_platform_id = deliveryPlatformOptions.value[0]
    // filters.value.is_active = booleanOptions.value[1]
    filters.value.is_binded_customer = initBinded && (roles[0] == 'superadmin' || roles[0] == 'admin' ||  roles[0] == 'supervisor' || roles[0] == 'driver') ? booleanOptions.value[1] : booleanOptions.value[0]
    // filters.value.is_testing = booleanOptions.value[2]
    filters.value.key_id = keyOptions.value[0]
    filters.value.status = statusOptions.value[2]
    filters.value.cashless_terminal_id = cashlessTerminalOptions.value[0]
    filters.value.simcard_id = simcardOptions.value[0]
    // filters.value.vend_config_id = vendConfigOptions.value[0]
    // filters.value.vend_model_id = vendModelOptions.value[0]
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
  router.delete('/vends/' + vend.id, {
    onSuccess: () => {
      toast.success("Machine deleted successfully", { timeout: 3000 })
    },
    onError: () => {
      toast.error("Failed to delete machine", { timeout: 3000 })
    }
  })
}

function onSearchFilterUpdated() {
  router.get('/settings', {
      ...filters.value,
      categories: filters.value.categories.map((category) => { return category.id }),
      categoryGroups: filters.value.categoryGroups.map((categoryGroup) => { return categoryGroup.id }),
      cashless_terminal_id: filters.value.cashless_terminal_id.id,
      cashless_mfg: filters.value.cashless_mfg.id,
      delivery_platform_id: filters.value.delivery_platform_id.id,
      // location_type_id: filters.value.locationType.id,
      lcd_monitor_id: filters.value.lcd_monitor_id.id,
      led_matrix_panel_id: filters.value.led_matrix_panel_id.id,
      locationTypes: filters.value.locationTypes.map((locationType) => { return locationType.id }),
      modem_type_id: filters.value.modem_type_id.id,
      modem_unit_id: filters.value.modem_unit_id.id,
      operators: filters.value.operators.map((operator) => { return operator.id }),
      is_binded_customer: filters.value.is_binded_customer.id,
      key_id: filters.value.key_id.id,
      selling_price_type: filters.value.selling_price_type.id,
      simcard_id: filters.value.simcard_id.id,
      status: filters.value.status.id,
      vendConfigs: filters.value.vendConfigs.map((vendConfig) => { return vendConfig.id }),
      vendModels: filters.value.vendModels.map((vendModel) => { return vendModel.id }),
      // vend_config_id: filters.value.vend_config_id.id,
      // vend_model_id: filters.value.vend_model_id.id,
      vendPrefixes: filters.value.vendPrefixes.map((vendPrefix) => { return vendPrefix.id }),
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

function openLogModal(vendItem) {
  historyVend.value = vendItem
  historyModalOpen.value = true
}

function closeHistoryModal() {
  historyModalOpen.value = false
  historyVend.value = null
}
</script>
