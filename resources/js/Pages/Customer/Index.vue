<template>
  <Head title="Customers" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Customers
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3">
        <div class="flex justify-end">
          <Link href="/customers/create">
            <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
              <PlusIcon class="h-4 w-4" aria-hidden="true" />
              <span>Create</span>
            </Button>
          </Link>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
          <SearchInput placeholderStr="ID" v-model="filters.ref_id">
            Customer ID
          </SearchInput>
          <SearchInput placeholderStr="ID" v-model="filters.vend_code">
            Machine ID
          </SearchInput>
          <SearchInput placeholderStr="Customer" v-model="filters.customer">
            Customer
          </SearchInput>
          <div v-if="permissions.includes('admin-access customers')">
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
            ></MultiSelect>
          </div>
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
              Attached to Machine?
            </label>
            <MultiSelect
              v-model="filters.is_binded_vend"
              :options="booleanOptions"
              trackBy="id"
              valueProp="id"
              label="value"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
            ></MultiSelect>
          </div>
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
              Customer Status
            </label>
            <MultiSelect
              v-model="filters.is_active"
              :options="activeOptions"
              trackBy="id"
              valueProp="id"
              label="value"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
            ></MultiSelect>
          </div>
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
              Refilling Routes
            </label>
            <MultiSelect
              v-model="filters.zone_id"
              :options="zoneOptions"
              trackBy="id"
              valueProp="id"
              label="name"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
            ></MultiSelect>
          </div>

          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
              Tags
            </label>
            <MultiSelect
              v-model="filters.tags"
              :options="tagOptions"
              trackBy="id"
              valueProp="id"
              label="name"
              mode="tags"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
            ></MultiSelect>
          </div>
          <div v-if="permissions.includes('admin-access customers') && cmsEndpoint">
            <label for="text" class="block text-sm font-medium text-gray-700">
              Is From CMS
            </label>
            <MultiSelect
              v-model="filters.is_cms"
              :options="booleanOptions"
              trackBy="id"
              valueProp="id"
              label="value"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
            ></MultiSelect>
          </div>
          <div v-if="permissions.includes('admin-access customers')">
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
            ></MultiSelect>
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
            ></MultiSelect>
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
            ></MultiSelect>
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
          <div v-if="permissions.includes('admin-access customers')">
            <label for="text" class="block text-sm font-medium text-gray-700">
              Location Type
            </label>
            <MultiSelect
              v-model="filters.location_types"
              :options="locationTypeOptions"
              trackBy="id"
              valueProp="id"
              label="value"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
              mode="tags"
            ></MultiSelect>
          </div>
          <div v-if="permissions.includes('admin-access customers')">
            <label for="text" class="block text-sm font-medium text-gray-700">
              Refilling Routes
            </label>
            <MultiSelect
              v-model="filters.zones"
              :options="zoneOptions"
              trackBy="id"
              valueProp="id"
              label="value"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
              mode="tags"
            ></MultiSelect>
          </div>
          <div v-if="permissions.includes('admin-access customers')">
						<label for="text" class="block text-sm font-medium text-gray-700">
							Preferred Day(s)
						</label>
						<MultiSelect
							v-model="filters.preferredDays"
							:options="dayOptions"
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
          <div v-if="permissions.includes('admin-access customers')">
            <label for="text" class="block text-sm font-medium text-gray-700">
              #Refill per Week
            </label>
            <MultiSelect
              v-model="filters.frequency_per_week_status"
              :options="frequencyPerWeekOptions"
              trackBy="id"
              valueProp="id"
              label="value"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
            ></MultiSelect>
          </div>
        </div>

        <div class="flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5">
          <div class="mt-3">
            <div class="flex space-x-1">
              <Button
                class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                @click="onSearchFilterUpdated"
              >
                <MagnifyingGlassIcon class="h-4 w-4" aria-hidden="true" />
                <span>Search</span>
              </Button>
              <Button
                class="inline-flex space-x-1 items-center rounded-md border border-sky bg-sky-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-sky-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                @click.prevent="onMapAllMarkerClicked"
                v-if="customers.data && customers.data.some(customer => customer.deliveryAddress && customer.deliveryAddress.latitude && customer.deliveryAddress.longitude)"
              >
                <MapPinIcon class="h-4 w-4" aria-hidden="true" />
                <span>Show Map Markers</span>
              </Button>
              <Button
                class="inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                @click="resetFilters"
              >
                <BackspaceIcon class="h-4 w-4" aria-hidden="true" />
                <span>Reset</span>
              </Button>
            </div>
          </div>
          <div class="flex flex-col space-y-2">
            <p class="text-sm text-gray-700 leading-5 flex space-x-1">
              <span>Showing</span>
              <span class="font-medium">{{ customers.meta.from ?? 0 }}</span>
              <span>to</span>
              <span class="font-medium">{{ customers.meta.to ?? 0 }}</span>
              <span>of</span>
              <span class="font-medium">{{ customers.meta.total }}</span>
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
            ></MultiSelect>
          </div>
        </div>
      </div>

      <div class="mt-6 flex flex-col">
        <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll">
            <table class="min-w-full border-separate" style="border-spacing: 0">
              <thead class="bg-gray-100">
                <tr class="divide-x divide-gray-200">
                  <TableHead>#</TableHead>
                  <TableHeadSort
                    modelName="id"
                    :sortKey="filters.sortKey"
                    :sortBy="filters.sortBy"
                    @sort-table="sortTable('id')"
                  >
                    Customer ID
                  </TableHeadSort>
                  <TableHead>
                    <div class="flex flex-col space-y-2">
                      <SingleSortItem modelName="vend_code" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_code')">
                        Machine ID
                      </SingleSortItem>
                      <span>
                        Machine Prefix
                      </span>
                    </div>
                  </TableHead>
                  <TableHead>
                    <div class="flex flex-col space-y-2">
                      <SingleSortItem modelName="id" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('id', true)">
                        Customer
                      </SingleSortItem>
                    </div>
                  </TableHead>
                  <TableHead>Del Address</TableHead>
                  <TableHeadSort
                    modelName="postcode"
                    :sortKey="filters.sortKey"
                    :sortBy="filters.sortBy"
                    @sort-table="sortTable('postcode')"
                  >
                    Del Postcode
                  </TableHeadSort>
                  <TableHead>Tags</TableHead>
                  <TableHeadSort
                    modelName="zone_name"
                    :sortKey="filters.sortKey"
                    :sortBy="filters.sortBy"
                    @sort-table="sortTable('zone_name')"
                  >
                    Refilling Routes
                  </TableHeadSort>
                  <TableHeadSort
                    modelName="frequency_per_week_status"
                    :sortKey="filters.sortKey"
                    :sortBy="filters.sortBy"
                    @sort-table="sortTable('frequency_per_week_status')"
                  >
                    #Refill per Week
                  </TableHeadSort>
                  <TableHead>Preferred Visit Days</TableHead>
                  <TableHead>Ops Note</TableHead>
                  <TableHead>
                    <div class="flex flex-col space-y-2">
                      <SingleSortItem modelName="total_full_load_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('total_full_load_amount', true)">
                        Full Load Value
                      </SingleSortItem>
                      <div class="flex justify-center items-center">
                        <SingleSortItem modelName="thirty_days_over_full_load_ratio" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('thirty_days_over_full_load_ratio', false)">
                          Avg30dSales/ Full Load
                        </SingleSortItem>
                        <ExclamationCircleIcon class="min-w-5 w-5 h-5 self-center pl-1 text-sky-500" v-tooltip="{ content: '30dSales = 30 x Avg Daily Sales (Last30d) <br> Red: < 1 <br> Green: > 2', html: true }"></ExclamationCircleIcon>
                      </div>
                    </div>
                  </TableHead>
                  <TableHead>
                    <div class="flex flex-col space-y-2">
                      <SingleSortItem modelName="vend_transaction_totals_json->vend_records_amount_latest" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_transaction_totals_json->vend_records_amount_latest', true)">
                        Lifetime Sales
                      </SingleSortItem>
                      <SingleSortItem modelName="begin_date" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('begin_date', false)">
                        Begin Date
                      </SingleSortItem>
                      <SingleSortItem modelName="vend_transaction_totals_json->vend_records_amount_average_day" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_transaction_totals_json->vend_records_amount_average_day', true)">
                        Avg Sales/ Day
                      </SingleSortItem>
                      <SingleSortItem modelName="vend_transaction_totals_json->vend_records_thirty_days_amount_average" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_transaction_totals_json->vend_records_thirty_days_amount_average', true)">
                        AvgDailySales (Last30d)
                      </SingleSortItem>
                      <SingleSortItem modelName="vend_transaction_totals_json->vend_records_thirty_days_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_transaction_totals_json->vend_records_thirty_days_amount', true)">
                        Sales (Last30d)
                      </SingleSortItem>
                    </div>
                  </TableHead>
                  <TableHead>
                    <div class="flex flex-col space-y-2">
                      <span>
                        Status
                      </span>
                      <SingleSortItem modelName="operator_code" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('operator_code')">
                        Operator
                      </SingleSortItem>
                      <span>
                        Action
                      </span>
                    </div>
                  </TableHead>
                </tr>
              </thead>
              <tbody class="bg-white">
                <tr
                  v-for="(customer, customerIndex) in customers.data"
                  :key="customer.id"
                  class="divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100"
                >
                  <TableData
                    :currentIndex="customerIndex"
                    :totalLength="customers.length"
                    inputClass="text-center"
                  >
                    {{ customers.meta.from + customerIndex }}
                  </TableData>
                  <TableData
                    :currentIndex="customerIndex"
                    :totalLength="customers.length"
                    inputClass="text-center"
                  >
                    {{ customer.ref_id }}
                  </TableData>
                  <TableData
                    :currentIndex="customerIndex"
                    :totalLength="customers.length"
                    inputClass="text-center"
                  >
                    <div class="flex flex-col space-y-2">
                      <Link v-if="customer.vend && customer.vend.id" :href="'/settings/vend/' + customer.vend.id + '/update'" >
                        <span class="text-blue-600">
                          {{ customer.vend.code }}
                        </span>
                      </Link>
                      <span v-if="customer.vend && customer.vend.vendPrefix">
                        {{ customer.vend.vendPrefix.name }}
                      </span>
                    </div>
                  </TableData>
                  <TableData
                    :currentIndex="customerIndex"
                    :totalLength="customers.length"
                    inputClass="text-left"
                  >
                    <div class="flex flex-col space-y-1">
                      <a
                        :class="[customer && customer.person_id ? 'text-blue-700' : 'text-purple-700']"
                        target="_blank"
                        :href="'/customers/' + customer.id + '/edit'"
                      >
                        <span
                          v-if="customer.person_id && (customer.virtual_customer_code || customer.virtual_customer_prefix)"
                        >
                          <span v-if="customer.virtual_customer_code">
                            {{ customer.virtual_customer_code }}
                          </span>
                          <br />
                        </span>
                        {{ customer.name }}
                      </a>
                      <div class="flex space-x-1">
                        <a
                          target="_blank"
                          :href="cmsEndpoint + '/person/' + customer.person_id + '/edit'"
                          class=""
                          v-if="customer.person_id"
                        >
                          <div
                            class="inline-flex justify-center items-center rounded px-2 py-1 text-[10px] font-small border bg-blue-200 text-gray-800"
                          >
                            CMS
                          </div>
                        </a>
                        <div
                          class="inline-flex rounded px-0.5 py-0.5 text-xs border w-fit h-fit bg-indigo-100 text-indigo-800 border-indigo-300"
                        >
                          RP{{ customer.selling_price_type }}
                        </div>
                      </div>
                    </div>
                  </TableData>
                  <TableData
                    :currentIndex="customerIndex"
                    :totalLength="customers.length"
                    inputClass="text-left"
                  >
                    <div class="flex flex-col space-y-1">
                      <span>
                        <!-- {{ customer.deliveryAddress }} -->
                        {{ customer.deliveryAddress?.full_address }}
                        <!-- {{ customer.deliveryAddress
                          ? customer.deliveryAddress.full_address
                          // : null }} -->
                      </span>
                      <span class="flex space-x-1 items-center" v-if="customer.deliveryAddress">
                        <span>
                          <Button
                          type="button" class="bg-sky-300 hover:bg-sky-400 px-3 py-1 text-xs text-sky-800 flex space-x-1 w-fit"
                          @click="onMapMarkerClicked(customer)"
                          v-if="customer.deliveryAddress && customer.deliveryAddress.latitude && customer.deliveryAddress.longitude"
                          >
                            <MapPinIcon class="h-3 w-3" aria-hidden="true"/>
                          </Button>
                        </span>
                        <a
                          :href="customer.deliveryAddress && customer.deliveryAddress.map_url
                            ? customer.deliveryAddress.map_url
                            : (customer.deliveryAddress.latitude && customer.deliveryAddress.longitude
                              ? 'https://www.google.com/maps/search/?api=1&query=' + customer.deliveryAddress.latitude + ',' + customer.deliveryAddress.longitude
                              : '')"
                          target="_blank"
                          rel="noopener noreferrer"
                          type="button"
                          class="bg-green-300 hover:bg-green-400 px-3 py-2 text-xs text-green-800 flex space-x-1 w-fit rounded shadow font-bold"
                        >
                          GPS
                        </a>
                      </span>
                    </div>

                  </TableData>
                  <TableData
                    :currentIndex="customerIndex"
                    :totalLength="customers.length"
                    inputClass="text-center"
                  >
                    {{ customer.deliveryAddress
                      ? customer.deliveryAddress.postcode
                      : null }}
                  </TableData>
                  <TableData
                    :currentIndex="customerIndex"
                    :totalLength="customers.length"
                    inputClass="text-left"
                  >
                    <span
                      v-for="tag in customer.tags"
                      class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800"
                    >
                      {{ tag.name }}
                    </span>
                  </TableData>
                  <TableData
                    :currentIndex="customerIndex"
                    :totalLength="customers.length"
                    inputClass="text-center min-w-20"
                  >
                    {{ customer.zone_id ? customer.zone_name : null }}
                  </TableData>
                  <TableData
                    :currentIndex="customerIndex"
                    :totalLength="customers.length"
                    inputClass="text-center"
                  >
                    {{ customer.frequency_per_week_status_name }}
                  </TableData>
                  <TableData
                    :currentIndex="customerIndex"
                    :totalLength="customers.length"
                    inputClass="text-center"
                  >
                    <div class="flex flex-col space-y-1">
                      <span
                        v-for="(day, dayIndex) in days"
                        :key="dayIndex"
                        v-if="customer.preferred_visit_days_json"
                      >
                        <span v-if="customer.preferred_visit_days_json[dayIndex - 1] == true" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                          {{ day }}
                        </span>
                      </span>
                    </div>
                  </TableData>
                  <TableData
                    :currentIndex="customerIndex"
                    :totalLength="customers.length"
                    inputClass="text-left whitespace-pre-line"
                  >
                    {{ customer.ops_note }}
                  </TableData>
                  <TableData
                    :currentIndex="customerIndex"
                    :totalLength="customers.length"
                    inputClass="text-center"
                  >
                    <div class="flex flex-col space-y-2">
                      <span>
                        {{ operatorCountry.currency_symbol }}{{(customer.total_full_load_amount).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0})}}
                      </span>
                      <span :class="[customer.thirty_days_over_full_load_ratio < 1 ? 'text-red-600' : (customer.thirty_days_over_full_load_ratio > 2 ? 'text-green-600' : 'text-gray-800')]">
                        {{(customer.thirty_days_over_full_load_ratio).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}}
                      </span>
                    </div>
                  </TableData>
                  <TableData
                    :currentIndex="customerIndex"
                    :totalLength="customers.length"
                    inputClass="text-center"
                  >
                    <div class="flex flex-col space-y-2">
                      <span
                      v-if="customer.vendTransactionTotalsJson && 'vend_records_amount_latest' in customer.vendTransactionTotalsJson"
                      >
                        {{ operatorCountry.currency_symbol }}{{(customer.vendTransactionTotalsJson['vend_records_amount_latest'] / (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0})}}
                      </span>
                      <span
                        v-if="customer.begin_date"
                      >
                        {{ customer.begin_date_short }}
                      </span>
                      <span
                      v-if="customer.vendTransactionTotalsJson && 'vend_records_amount_average_day' in customer.vendTransactionTotalsJson"
                      :class="getVendRecordsAmountAverageDayClass(customer.vendTransactionTotalsJson['vend_records_amount_average_day'])"
                      >
                        {{ operatorCountry.currency_symbol }}{{(customer.vendTransactionTotalsJson['vend_records_amount_average_day'] / (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
                      </span>
                      <span
                      v-if="customer.vendTransactionTotalsJson"
                      :class="[ customer.vendTransactionTotalsJson && 'vend_records_amount_average_day' in customer.vendTransactionTotalsJson ? (customer.vendTransactionTotalsJson['vend_records_thirty_days_amount_average'] >= customer.vendTransactionTotalsJson['vend_records_amount_average_day']/100 ? 'text-green-700' : 'text-red-700') : 'text-gray-400']">
                          {{ operatorCountry.currency_symbol }}{{ (customer.vendTransactionTotalsJson['vend_records_thirty_days_amount_average']/ (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                      </span>
                      <span
                        v-if="customer.vendTransactionTotalsJson && 'vend_records_thirty_days_amount' in customer.vendTransactionTotalsJson"
                        :class="[
                            customer.vendTransactionTotalsJson ? ((customer.vendTransactionTotalsJson['vend_records_thirty_days_amount']/ (Math.pow(10, operatorCountry.currency_exponent))) > 1000 ? 'text-green-700' : 'text-red-700') : 'text-gray-800'
                        ]">
                            {{ operatorCountry.currency_symbol }}{{ (customer.vendTransactionTotalsJson['vend_records_thirty_days_amount']/ (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                      </span>
                    </div>
                  </TableData>
                  <TableData
                    :currentIndex="customerIndex"
                    :totalLength="customers.length"
                    inputClass="text-center"
                  >
                    <div class="flex flex-col space-y-2">
                      <span>
                        <div
                          class="inline-flex justify-center items-center rounded px-1 py-0.5 text-[12px] font-small border min-w-full bg-green-300"
                          v-if="customer.is_active"
                        >
                          Active
                        </div>
                        <div
                          class="inline-flex justify-center items-center rounded px-1 py-0.5 text-[12px] font-small border min-w-full bg-red-300"
                          v-if="!customer.is_active"
                        >
                          Not Active
                        </div>
                      </span>
                      <span>
                        {{ customer.operator_code }}
                      </span>
                      <div class="flex justify-center space-x-1">
                        <Link :href="'/customers/' + customer.id + '/edit'">
                          <Button
                            type="button"
                            class="bg-gray-300 hover:bg-gray-400 px-2 py-1 text-xs text-gray-800 flex space-x-1"
                          >
                            <PencilSquareIcon class="w-4 h-4" />
                            <span>Edit</span>
                          </Button>
                        </Link>
                      </div>
                    </div>
                  </TableData>
                </tr>
                <tr v-if="!customers.data.length">
                  <td
                    colspan="24"
                    class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"
                  >
                    No Results Found
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator
              v-if="customers.data.length"
              :links="customers.links"
              :meta="customers.meta"
            ></Paginator>
          </div>
        </div>
      </div>
    </div>
    <Form
      v-if="showModal"
      :customer="customer"
      :type="type"
      :showModal="showModal"
      @modalClose="onModalClose"
    >
    </Form>
    <MapMarker
      v-if="showMapMarkerModal"
      :customers="customerModel"
      :api-key="mapApiKey"
      :showModal="showMapMarkerModal"
      @modalClose="onMapMarkerModalClose"
    >
    </MapMarker>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import Form from '@/Pages/Customer/Form.vue';
import MapMarker from '@/Components/MapMarker.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import SingleSortItem from '@/Components/SingleSortItem.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { BackspaceIcon, ExclamationCircleIcon, MagnifyingGlassIcon, MapPinIcon, PencilSquareIcon, PlusIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ref, onMounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Dropdown, Tooltip, Menu, vTooltip } from 'floating-vue';

const props = defineProps({
  customers: Object,
  categories: Object,
  cmsEndpoint: String,
  days: [Array, Object],
  frequencyPerWeekOptions: [Array, Object],
  locationTypeOptions: [Array, Object],
  mapApiKey: String,
  operatorOptions: Object,

  profiles: Object,
  sellingPriceTypeOptions: [Array, Object],
  statuses: Object,
  tags: Object,
  users: Object,
  vendModelOptions: Object,
  vendPrefixOptions: Object,
  zoneOptions: Object,
});

const filters = ref({
  customer: '',
  frequency_per_week_status: '',
  is_binded_vend: '',
  location_types: [],
  name: '',
  operators: [],
  preferredDays: [],
  ref_id: '',
  status: '',
  vend_code: '',
  vend_model_id: '',
  vendPrefixes: [],
  sortKey: '',
  sortBy: false,
  numberPerPage: 100,
  zones: [],
});
const activeOptions = ref([]);
const authOperator = usePage().props.auth.operator;
const dayOptions = ref([]);
const showModal = ref(false);
const booleanOptions = ref([]);
const customer = ref();
const customerModel = ref([]);
const categoryOptions = ref([]);
const frequencyPerWeekOptions = ref([]);
const locationTypeOptions = ref([]);
const operatorCountry = usePage().props.auth.operatorCountry
const operatorOptions = ref([]);
const permissions = usePage().props.auth.permissions;

const profileOptions = ref([]);
const showMapMarkerModal = ref(false);
const sellingPriceTypeOptions = ref([]);
const statusOptions = ref([]);
const tagOptions = ref([]);
const userOptions = ref([]);
const zoneOptions = ref([]);
const type = ref('');
const numberPerPageOptions = ref([]);
const vendModelOptions = ref([]);
const vendPrefixOptions = ref([])

onMounted(() => {
  activeOptions.value = [
    { id: 'all', value: 'All' },
    { id: 'true', value: 'Active' },
    { id: 'false', value: 'Not Active' },
  ];
  booleanOptions.value = [
    { id: 'all', value: 'All' },
    { id: 'true', value: 'Yes' },
    { id: 'false', value: 'No' },
  ];
  dayOptions.value = [
			{id: 'all', value: 'All'},
			...Object.entries(props.days).map(([id, name]) => ({id: id, value: name}))
	]
  frequencyPerWeekOptions.value = [
    { id: 'all', value: 'All' },
    ...Object.entries(props.frequencyPerWeekOptions).map(([id, value]) => {
      return {
        id: id,
        value: value,
      };
    })
  ]
  numberPerPageOptions.value = [
    { id: 100, value: 100 },
    { id: 200, value: 200 },
    { id: 500, value: 500 },
    { id: 'All', value: 'All' },
  ];
  filters.value.numberPerPage = numberPerPageOptions.value[0];
  categoryOptions.value = props.categories.data.map((data) => {
    return { id: data.id, name: data.name };
  });
  locationTypeOptions.value = [
    { id: 'all', value: 'All' },
    ...props.locationTypeOptions.data.map((data) => {
      return { id: data.id, value: data.name };
    }),
  ];
  operatorOptions.value = [
    { id: 'all', full_name: 'All' },
    ...props.operatorOptions.data.map((data) => {
      return { id: data.id, code: data.code, full_name: data.full_name };
    }),
  ];

  profileOptions.value = props.profiles.data.map((data) => {
    return { id: data.id, name: data.name };
  });
  sellingPriceTypeOptions.value = Object.entries(props.sellingPriceTypeOptions).map(
    ([id, name]) => ({ id: id, value: name })
  );
  userOptions.value = props.users.data.map((data) => {
    return { id: data.id, name: data.name };
  });
  vendPrefixOptions.value = [
      {id: 'single-ud', value: 'Single UD'},
			...props.vendPrefixOptions.data.map((data) => {return {id: data.id, value: data.name}})
	]
  tagOptions.value = props.tags.data.map((data) => {
    return { id: data.id, name: data.name };
  });
  vendModelOptions.value = [
    { id: 'all', value: 'All' },
    ...props.vendModelOptions.data.map((data) => {
      return { id: data.id, value: data.name };
    }),
  ];
  zoneOptions.value = [
    { id: 'all', value: 'All' },
    ...props.zoneOptions.data.map((data) => {
      return { id: data.id, value: data.name };
    }),
  ];
  filters.value.frequency_per_week_status = frequencyPerWeekOptions.value[0];
  filters.value.is_active = booleanOptions.value[1];
  filters.value.is_binded_vend = booleanOptions.value[0];
  filters.value.is_cms = booleanOptions.value[0];
  filters.value.location_types = [locationTypeOptions.value.find((locationType) => locationType.id == 'all')].filter(Boolean);
  filters.value.operators = [operatorOptions.value.find((operator) => operator.id == 'all')].filter(Boolean);
  filters.value.vend_model_id = vendModelOptions.value[0];
});

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


function onCreateClicked() {
  type.value = 'create';
  customer.value = null;
  showModal.value = true;
}

function onEditClicked(customerValue) {
  type.value = 'update';
  customer.value = customerValue;
  showModal.value = true;
}

function onMapMarkerClicked(customer) {
  customerModel.value = [{
    sequence: props.customers.data.findIndex((data) => data.id === customer.id) + 1,
    ...customer
  }];
  showMapMarkerModal.value = true;
}

function onMapAllMarkerClicked() {
  // Extract all the opsJobItems' customer information and send the request
  customerModel.value = props.customers.data.map((customer, index) => ({
    sequence: index + 1,
    ...customer,
  }));
  showMapMarkerModal.value = true;
}

function onMapMarkerModalClose() {
  showMapMarkerModal.value = false
}

function onSearchFilterUpdated() {
  router.get(
    '/customers',
    {
      ...filters.value,
      frequency_per_week_status: filters.value.frequency_per_week_status.id,
      is_binded_vend: filters.value.is_binded_vend.id,
      is_cms: filters.value.is_cms.id,
      is_active: filters.value.is_active.id,
      location_types: filters.value.location_types.map((locationType) => locationType.id),
      operators: filters.value.operators.filter(operator => operator).map((operator) => operator.id),
      preferredDays: filters.value.preferredDays.map((preferredDay) => { return preferredDay.id }),
      selling_price_type: filters.value.selling_price_type ? filters.value.selling_price_type.id : '',
      vend_model_id: filters.value.vend_model_id.id,
      vendPrefixes: filters.value.vendPrefixes.map((vendPrefix) => { return vendPrefix.id }),
      numberPerPage: filters.value.numberPerPage.id,
      zones: filters.value.zones.map((zone) => zone.id),
    },
    {
      preserveState: true,
      replace: true,
    }
  );
}

function resetFilters() {
  router.get('/customers');
}

function sortTable(sortKey) {
  filters.value.sortKey = sortKey;
  filters.value.sortBy = !filters.value.sortBy;
  onSearchFilterUpdated();
}

function onModalClose() {
  showModal.value = false;
}
</script>
