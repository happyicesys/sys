<template>

  <Head title="Product Mappings" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Product Mappings
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
        <div class="flex justify-end">
          <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
          @click="onCreateClicked()"
          v-if="permissions.includes('create product-mappings')"
          >
            <PlusIcon class="h-4 w-4" aria-hidden="true"/>
            <span>
              Create
            </span>
          </Button>
        </div>
          <!-- <div class="flex flex-col md:flex-row md:space-x-3 space-y-1 md:space-y-0"> -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
          <SearchInput placeholderStr="Name" v-model="filters.name">
            Name
          </SearchInput>
          <SearchInput placeholderStr="Upcoming Product Mapping" v-model="filters.upcoming_product_mapping">
            Upcoming Product Mapping
          </SearchInput>
          <SearchInput placeholderStr="Machine ID" v-model="filters.vend_code">
            Machine ID#
          </SearchInput>
          <!-- Site search — matches a binded machine's site by Site Name,
               virtual code/prefix, or the displayed Site ID (customers.id +
               20000). Same matching rules as the Customer index "Site" box. -->
          <SearchInput placeholderStr="Site name / Site ID" v-model="filters.site">
            Site
          </SearchInput>
          <SearchInput placeholderStr="Product" v-model="filters.product">
            Product
          </SearchInput>
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
              Active Product Mapping
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
          <!-- DEPRECATED (2026-07): prefix→mapping binding retired; Machine Prefix
               filter hidden (pivot kept read-only for historical data). -->
          <!-- <div>
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
					</div> -->
          <div>
						<label for="text" class="block text-sm font-medium text-gray-700">
							Machine Status
						</label>
						<MultiSelect
							v-model="filters.vendStatus"
							:options="vendStatusOptions"
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
            </div>
          </div>
          <div class="flex flex-col space-y-2">
              <p class="text-sm text-gray-700 leading-5 flex space-x-1">
                  <span>Showing</span>
                  <span class="font-medium">{{ productMappings.meta.from ?? 0 }}</span>
                  <span>to</span>
                  <span class="font-medium">{{ productMappings.meta.to ?? 0 }}</span>
                  <span>of</span>
                  <span class="font-medium">{{ productMappings.meta.total }}</span>
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

      <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mt-4">
        <div class="bg-white overflow-hidden shadow rounded-lg">
          <div class="px-4 py-5 sm:p-6">
            <dt class="text-sm font-medium text-gray-500 truncate">
              Total Binded Machines
            </dt>
            <dd class="mt-1 text-3xl font-semibold text-gray-900">
              {{ totalBindedVends }}
            </dd>
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
                    <TableHeadSort modelName="name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('name', false)"  class="bg-sky-200">
                      <div class="flex flex-col space-y-1">
                        <span>Name</span>
                        <span class="text-black font-normal text-xs">Remarks</span>
                      </div>
                    </TableHeadSort>
                    <TableHead>
                      <div class="flex flex-col space-y-1">
                        <span>Upcoming Product Mapping</span>
                        <span class="text-black font-normal text-xs">Remarks</span>
                        <a
                          href="#"
                          class="inline-flex items-center justify-center gap-0.5 font-normal text-xs text-blue-600 hover:text-blue-800"
                          @click.prevent="sortTable('upcoming_product_mapping_start_date', false)"
                        >
                          Start At
                          <svg v-if="filters.sortKey === 'upcoming_product_mapping_start_date' && filters.sortBy" xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                          </svg>
                          <svg v-if="filters.sortKey === 'upcoming_product_mapping_start_date' && !filters.sortBy" xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                          </svg>
                        </a>
                      </div>
                    </TableHead>
                    <TableHead>
                      Operator
                    </TableHead>
                    <!-- DEPRECATED (2026-07): prefix→mapping binding retired; Binded Prefix column hidden -->
                    <!-- <TableHeadSort modelName="vend_prefix_name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_prefix_name', false)">
                      Binded Prefix
                    </TableHeadSort> -->
                    <TableHead>
                      Channel - Product
                    </TableHead>
                    <TableHead>
                      <!--
                        Binded Vending Machines header — same column now also
                        carries two per-machine bits, inline, per ops request:
                          (1) Ref Price tier chip (RP1..RP5) sourced from
                              customers.selling_price_type — same source used
                              by Vend/CustomerIndex.vue's "Ref Price" column.
                          (2) L30d Sales = customer.vendTransactionTotalsJson
                              .thirty_days_amount (customers.totals_json) —
                              site-based, identical formula/colours to
                              Vend/CustomerIndex.vue's Last30d figure.
                        Binded date stays as the subtitle / per-row chip; the
                        header subtitle is expanded so users know what to look
                        for inside this cell.
                      -->
                      <div class="flex flex-col space-y-1">
                        <span>Binded Vending Machines</span>
                        <span class="text-black font-normal text-xs">RP Tier • L30d Sales</span>
                        <span class="text-black font-normal text-xs">(binded date)</span>
                        <span class="text-black font-normal text-xs"># machines at upcoming stage</span>
                      </div>
                    </TableHead>
                    <!--
                      Avg Mthly Sales — ONE FIGURE PER BINDED MACHINE, listed in the
                      same order (and with the same "N. code" prefix) as the Binded
                      Vending Machines column on its left, so the two lists read
                      side by side.

                      CHANGED 2026-07-31 after ops feedback: this used to be a single
                      GROUP figure (the sum over the mapping's binded sites) and users
                      read it as a per-machine number. It is now literally the same
                      value Vend/CustomerIndex.vue prints in its "Avg Mthly Sales $"
                      column for that machine — same numerator, same denominator,
                      same reporting floor — so the two pages tally machine for
                      machine.

                      Both pages are SITE-based: CustomerIndex selects
                      `customers.totals_json AS vend_transaction_totals_json` and
                      `customers.begin_date` onto the vend row (VendController::
                      customerIndex), i.e. its per-machine figure is really the
                      machine's SITE figure. Here we read the same two columns off
                      the eager-loaded vend.customer. Consequence worth knowing:
                      two machines standing at the SAME site show the SAME figure
                      (that site's average) — on both pages — so the lines must not
                      be added up.

                      NOT SORTABLE any more. A per-machine list has no single value
                      to sort rows by; the SQL sum that made the header clickable
                      went away with the group total (see ProductMappingController).
                    -->
                    <TableHead>
                      <!--
                        The full explanation of this column lives HERE, on the header,
                        not on every figure. It used to hang off each number, where a
                        paragraph-length tooltip covered most of the screen on hover.
                        Figures now carry only their own month-on-month change.
                      -->
                      <div class="flex flex-col space-y-1 cursor-help" v-tooltip="avgMthlySalesTooltip">
                        <span>Avg Mthly Sales</span>
                        <span class="text-black font-normal text-xs">{{ operatorCountry.currency_symbol }} / month</span>
                        <span class="text-black font-normal text-xs">(per machine, by site)</span>
                      </div>
                    </TableHead>
                    <TableHead>
                      Menu
                    </TableHead>
                    <TableHead>
                    </TableHead>
                  </tr>
                </thead>
                  <tbody class="bg-white">
                    <tr v-for="(productMapping, productMappingIndex) in productMappings.data" :key="productMapping.id" class="divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100">
                      <TableData :currentIndex="productMappingIndex" :totalLength="productMappings.length" inputClass="text-center">
                        {{ productMappings.meta.from + productMappingIndex }}
                      </TableData>
                      <TableData :currentIndex="productMappingIndex" :totalLength="productMappings.length" inputClass="text-left bg-sky-50">
                        <div class="flex flex-col space-y-1">
                          <span>
                            <!-- {{ productMapping }} -->
                            {{ productMapping.name }}
                          </span>
                          <div
                              class="inline-flex justify-center items-center rounded px-0.5 py-0.5 text-xs border w-fit hover:cursor-pointer"
                              :class="productMapping.is_active ? 'bg-green-100 text-green-800 border-green-300' : 'bg-red-100 text-red-800 border-red-300'"
                          >
                              <div class="flex flex-col">
                                  <span class="font-semibold grow-0">
                                    {{ productMapping.is_active ? 'Active' : 'Inactive' }}
                                  </span>
                              </div>
                          </div>
                          <span class="text-gray-500 text-xs whitespace-pre-wrap" v-if="productMapping.remarks">
                            {{ productMapping.remarks }}
                          </span>
                        </div>
                      </TableData>
                      <TableData :currentIndex="productMappingIndex" :totalLength="productMappings.length" inputClass="text-left">
                        <div class="flex flex-col space-y-1" v-if="productMapping.upcomingProductMapping">
                          <span>
                            {{ productMapping.upcomingProductMapping.name }}
                          </span>
                          <span class="text-gray-500 text-xs whitespace-pre-wrap" v-if="productMapping.upcomingProductMapping.remarks">
                            {{ productMapping.upcomingProductMapping.remarks }}
                          </span>
                          <span class="text-indigo-600 text-xs" v-if="productMapping.upcoming_product_mapping_start_date">
                            {{ productMapping.upcoming_product_mapping_start_date }}
                          </span>
                        </div>
                      </TableData>
                      <TableData :currentIndex="productMappingIndex" :totalLength="productMappings.length" inputClass="text-center">
                        <span v-if="productMapping.operator">
                          {{ productMapping.operator.code }}
                        </span>
                      </TableData>
                      <!-- DEPRECATED (2026-07): Binded Prefix cell hidden -->
                      <!-- <TableData :currentIndex="productMappingIndex" :totalLength="productMappings.length" inputClass="text-left">
                        <ul class="divide-y divide-gray-200">
                          <li class="flex py-1 px-3 space-x-2" v-for="(vendPrefix, vendPrefixIndex) in productMapping.vendPrefixes">
                            <span>
                              {{ vendPrefixIndex + 1 }}.
                            </span>
                            <span class="text-md pr-2">
                              {{ vendPrefix.name }}
                            </span>
                          </li>
                        </ul>
                      </TableData> -->
                      <TableData :currentIndex="productMappingIndex" :totalLength="productMappings.length" inputClass="text-left">
                        <ul class="divide-y divide-gray-200">
                          <li class="flex py-1 px-3 space-x-2" v-for="(productMappingItem, productMappingItemIndex) in productMapping.productMappingItems">
                            <span>
                              {{ productMappingItemIndex + 1 }}.
                            </span>
                            <span class="text-blue-700 text-md pr-2">
                              {{ productMappingItem.channel_code }}
                            </span>
                            <span v-if="productMappingItem.product && productMappingItem.product.code">
                              {{ productMappingItem.product.code }}
                            </span>
                            <span>
                              - {{ productMappingItem.product.name }}
                            </span>

                          </li>
                        </ul>
                      </TableData>
                      <TableData :currentIndex="productMappingIndex" :totalLength="productMappings.length" inputClass="text-left">
                        <!--
                          :ref registers this wrapper as the HEIGHT SOURCE for the Avg
                          Mthly Sales column next door — alignAvgMthlySalesRows() copies
                          the height of EACH of its three children (heading, machine
                          list, upcoming-stage footnote) onto the twin in that cell, and
                          each <li> inside the list onto the matching figure row. The
                          child ORDER is what the script keys on, so keep it
                          heading → <ul> → footnote. Nothing else reads this ref;
                          removing it just makes that column stop lining up.
                        -->
                        <div class="flex flex-col space-y-1" :ref="(el) => setBindedVendsCellRef(productMapping.id, el)">
                          <span class="text-center text-indigo-600 p-2 text-xs">
                            {{ productMapping.vends.length }} Machine(s)
                          </span>
                          <ul class="divide-y divide-gray-200">
                            <!--
                              Each row is now flex-wrap so we can append a
                              basis-full sub-row beneath the existing inline
                              (#. code, customer block) row carrying:
                                • RP tier chip — from vend.customer.selling_price_type
                                • L30d Sales — from vend.customer.vendTransactionTotalsJson (site-based)
                              Both pieces fall back gracefully (chip omitted if
                              no selling_price_type; L30d omitted if the json
                              hasn't been hydrated yet). Mirrors the styling
                              used on Vend/CustomerIndex.vue so the values feel
                              familiar to ops.
                            -->
                            <li class="flex flex-wrap gap-x-2 gap-y-1 py-1 px-3" v-for="(vend, vendIndex) in productMapping.vends">
                              <span>
                                {{ vendIndex + 1 }}.
                              </span>
                              <a :href="'/vends/customers?codes=' + vend.code" target="_blank" class="text-blue-700">
                                <span>
                                  {{ vend.code }}
                                </span>
                              </a>

                              <span v-if="vend.customer && vend.customer.person_id">
                                  <span v-if="permissions.includes('admin-access vends')">
                                      <a :class="[vend.customer && vend.customer.person_id && vend.customer.is_active ? 'text-blue-700' : 'text-gray-400']" target="_blank" :href="'/customers/' + vend.customer.id + '/edit'">
                                          {{ vend.customer.id + 20000 }} ({{ vend.vendPrefix ? vend.vendPrefix.name : '' }})
                                          <br>
                                          {{ vend.customer.name }}<!-- Grab (delivery platform) pill — moved next to the customer name so it doesn't trail after the binded_at date -->
                                          <span v-if="vend.deliveryProductMappingVends" v-for="(deliveryProductMappingVend, index) in vend.deliveryProductMappingVends" :key="'dpmv-a-' + index">
                                              <span
                                                  class="ml-1 inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit text-gray-800 bg-green-400 align-middle"
                                                  v-if="deliveryProductMappingVend.deliveryProductMapping && deliveryProductMappingVend.deliveryProductMapping.deliveryPlatformOperator && deliveryProductMappingVend.deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform"
                                              >
                                                  {{ deliveryProductMappingVend.deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform.name }}
                                              </span>
                                          </span>
                                      </a>
                                  </span>
                                  <span v-else>
                                      {{ vend.customer.id + 20000 }} ({{ vend.vendPrefix ? vend.vendPrefix.name : '' }})
                                      <br>
                                      {{ vend.customer.name }}<!-- Grab pill — moved next to the customer name -->
                                      <span v-if="vend.deliveryProductMappingVends" v-for="(deliveryProductMappingVend, index) in vend.deliveryProductMappingVends" :key="'dpmv-b-' + index">
                                          <span
                                              class="ml-1 inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit text-gray-800 bg-green-400 align-middle"
                                              v-if="deliveryProductMappingVend.deliveryProductMapping && deliveryProductMappingVend.deliveryProductMapping.deliveryPlatformOperator && deliveryProductMappingVend.deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform"
                                          >
                                              {{ deliveryProductMappingVend.deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform.name }}
                                          </span>
                                      </span>
                                  </span>
                              </span>
                              <span v-else-if="vend.customer && !vend.customer.person_id">
                                  <span v-if="permissions.includes('admin-access vends')" :class="[vend.customer.is_active ? 'text-gray-800' : 'text-gray-400']">
                                      <!-- <a class="text-blue-700" target="_blank" :href="'//admin.happyice.com.sg/person/' + vend.person_id + '/edit'"> -->
                                          {{ vend.customer.name }}<!-- Grab pill — moved next to the customer name -->
                                          <span v-if="vend.deliveryProductMappingVends" v-for="(deliveryProductMappingVend, index) in vend.deliveryProductMappingVends" :key="'dpmv-c-' + index">
                                              <span
                                                  class="ml-1 inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit text-gray-800 bg-green-400 align-middle"
                                                  v-if="deliveryProductMappingVend.deliveryProductMapping && deliveryProductMappingVend.deliveryProductMapping.deliveryPlatformOperator && deliveryProductMappingVend.deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform"
                                              >
                                                  {{ deliveryProductMappingVend.deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform.name }}
                                              </span>
                                          </span>
                                      <!-- </a> -->
                                  </span>
                                  <span v-else :class="[vend.customer.is_active ? 'text-gray-800' : 'text-gray-400']">
                                      {{ vend.customer.name }}<!-- Grab pill — moved next to the customer name -->
                                      <span v-if="vend.deliveryProductMappingVends" v-for="(deliveryProductMappingVend, index) in vend.deliveryProductMappingVends" :key="'dpmv-d-' + index">
                                          <span
                                              class="ml-1 inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit text-gray-800 bg-green-400 align-middle"
                                              v-if="deliveryProductMappingVend.deliveryProductMapping && deliveryProductMappingVend.deliveryProductMapping.deliveryPlatformOperator && deliveryProductMappingVend.deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform"
                                          >
                                              {{ deliveryProductMappingVend.deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform.name }}
                                          </span>
                                      </span>
                                  </span>
                              </span>
                              <!--
                                Per-machine RP chip + L30d Sales sub-row.
                                basis-full forces this onto its own line under
                                the inline customer info above. Indented to
                                line up roughly under the customer text.
                                RP chip styling matches Vend/CustomerIndex.vue
                                (indigo pill). L30d uses the same currency
                                exponent/symbol convention via operatorCountry.
                              -->
                              <div class="basis-full flex items-center space-x-2 pl-6 text-xs">
                                <span
                                  v-if="vend.customer && vend.customer.selling_price_type"
                                  class="inline-flex rounded px-0.5 py-0.5 border bg-indigo-100 text-indigo-800 border-indigo-300"
                                >
                                  RP{{ vend.customer.selling_price_type }}
                                </span>
                                <!--
                                  L30d Sales is read from the CUSTOMER's rolling
                                  totals (vend.customer.vendTransactionTotalsJson,
                                  i.e. customers.totals_json), NOT the vend's own
                                  vend_transaction_totals_json. The vend total is
                                  keyed on vend_id and follows the machine, so a
                                  machine moved to a new site would keep showing
                                  sales earned under the previous customer. The
                                  customer total is keyed on customer_id — site-based.
                                -->
                                <span
                                  v-if="vend.customer && vend.customer.vendTransactionTotalsJson && 'thirty_days_amount' in vend.customer.vendTransactionTotalsJson"
                                  :class="[
                                    vend.is_active || vend.is_testing
                                      ? ((vend.customer.vendTransactionTotalsJson['thirty_days_amount'] / Math.pow(10, operatorCountry.currency_exponent)) > 1000 ? 'text-green-700' : 'text-red-700')
                                      : 'text-gray-400'
                                  ]"
                                  v-tooltip="'L30d Sales (site)'"
                                >
                                  L30d: {{ operatorCountry.currency_symbol }}{{ (vend.customer.vendTransactionTotalsJson['thirty_days_amount'] / Math.pow(10, operatorCountry.currency_exponent)).toLocaleString(undefined, { minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent) }) }}
                                </span>
                                <!-- Binded date — moved here from the customer-name line per the latest screenshot arrangement. -->
                                <span class="text-black" v-if="vend.binded_at">({{ moment(vend.binded_at).format('YYMMDD') }})</span>
                              </div>
                              <!--
                                Per-machine "own Upcoming Product Mapping" override badge.
                                Each vend can now pick its OWN upcoming mapping instead of
                                inheriting this mapping's preset (upcoming_product_mapping_id).
                                When the vend's chosen upcoming differs from the preset, show
                                an orange badge on its OWN sub-row (basis-full, not sharing the
                                RP/L30d/binded-date row) carrying the vend's chosen upcoming
                                name — so ops can see at a glance which machines will switch to
                                a different menu than the group default. Compared on id; both
                                null => same => no badge. If the vend has NO upcoming selected
                                (null), no badge — only an explicitly-chosen, differing upcoming
                                is flagged.
                              -->
                              <div
                                class="basis-full flex items-center pl-6"
                                v-if="vend.upcoming_product_mapping_id && vend.upcoming_product_mapping_id !== productMapping.upcoming_product_mapping_id"
                              >
                                <span
                                  class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium border bg-orange-100 text-orange-800 border-orange-300 w-fit"
                                  v-tooltip="'This machine has its own Upcoming Product Mapping, different from the preset upcoming'"
                                >
                                  Upcoming: {{ vend.upcomingProductMapping ? vend.upcomingProductMapping.name : '' }}
                                </span>
                              </div>
                            </li>
                          </ul>
                          <!--
                            "At upcoming stage" count (upcoming_vends_count):
                            machines that are NOT binded to this mapping yet but
                            are queued to switch onto it — i.e. their EFFECTIVE
                            upcoming mapping is this one: the machine's own
                            Upcoming Product Mapping (vend settings page) if it
                            has one, otherwise the preset upcoming inherited from
                            the mapping it is currently binded to. Same
                            vend-own-then-mapping-preset resolution the Ops Job
                            pages use. (Before 2026-07-30 only the machine's own
                            column was counted, so whole mappings queued to move
                            onto this one read as "0".) Tells ops how many
                            machines still have to be updated onto this mapping.
                            Counted with the same vendStatus filter as
                            the binded list above. Sits BELOW the binded list so
                            it reads as a footnote to it rather than competing
                            with the headline count; kept in grey (not orange/red)
                            because it is informational, not a warning — the
                            per-machine orange "Upcoming: <name>" chips inside the
                            list are the only alerting colour in this cell.
                            Greyed lighter when zero.
                          -->
                          <span
                            class="text-center px-2 pt-2 text-xs"
                            :class="(productMapping.upcoming_vends_count || 0) > 0 ? 'text-gray-700 font-semibold' : 'text-gray-400'"
                            v-tooltip="'Machines binded elsewhere but queued to switch to this mapping — their own Upcoming Product Mapping is this mapping, or they inherit it as the preset upcoming of the mapping they are on today. I.e. not yet updated to this mapping.'"
                          >
                            {{ productMapping.upcoming_vends_count || 0 }} Machine(s) at upcoming stage
                          </span>
                        </div>
                      </TableData>
                      <!--
                        Avg Mthly Sales — one figure per binded machine, on the SAME
                        LINE as that machine in the Binded Vending Machines cell to
                        the left. No machine code is repeated here: the row position
                        IS the identification, which is why the alignment has to hold.

                        This cell is a structural TWIN of the machines cell: same
                        `flex flex-col space-y-1` wrapper with the same THREE children
                        in the same order (heading, `divide-y` <ul> over the same
                        v-for, upcoming-stage footnote), and the same `py-1 px-3` row
                        box. alignAvgMthlySalesRows() then copies the measured height
                        of every one of those parts from the machines cell onto its
                        twin here — the two blocks end up exactly the same height, so
                        the <td> default vertical-align:middle centres them
                        identically, and every figure lands on its machine's line.

                        The heading/footnote text is duplicated (invisible) only as a
                        FALLBACK height for the frame before the script runs. Do NOT
                        rely on it matching: this column is much narrower, so
                        "N Machine(s) at upcoming stage" wraps to more lines here than
                        it does over there — that mismatch is exactly what made the
                        first attempt sit ~1 line high. Hence `overflow-hidden`, and
                        hence the script measuring these two spans rather than
                        trusting the markup to match.

                        Failure mode is cosmetic: no JS = the figures keep the right
                        values in the right order, just drifting off their line.

                        "—" (grey) means the machine has no site attached, or its
                        site's totals_json carries no lifetime figure yet, i.e. there
                        is nothing to average — not that it sold nothing.
                      -->
                      <TableData :currentIndex="productMappingIndex" :totalLength="productMappings.length" inputClass="text-center">
                        <div class="flex flex-col space-y-1" :ref="(el) => setAvgMthlySalesCellRef(productMapping.id, el)">
                          <span class="text-center p-2 text-xs invisible overflow-hidden" aria-hidden="true">
                            {{ productMapping.vends.length }} Machine(s)
                          </span>
                          <ul class="divide-y divide-gray-200">
                            <li class="flex items-center justify-center py-1 px-3" v-for="vend in productMapping.vends">
                              <!--
                                The figure and its arrow are wrapped in ONE <template v-if>
                                so the grey "—" below stays the v-else of *hasAvgMthlySales*.
                                Do NOT flatten this: Vue pairs v-else with the nearest
                                preceding ELEMENT (skipping only whitespace and comments), so
                                putting the arrow span directly between the figure and the
                                dash silently re-parents the dash onto the ARROW's v-if — and
                                a machine with a figure but no trend then renders the figure
                                AND the dash side by side, the dash claiming there is nothing
                                to average. That shipped briefly; it hit 9 of 400 live rows.
                              -->
                              <template v-if="hasAvgMthlySales(vend)">
                              <span
                                class="whitespace-nowrap"
                                :class="[vend.is_active || vend.is_testing ? 'text-gray-800 font-semibold' : 'text-gray-400']"
                                v-tooltip="avgMthlySalesFigureTooltip(vend)"
                              >
                                {{ operatorCountry.currency_symbol }}{{ avgMthlySales(vend).toLocaleString(undefined, { minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent) }) }}
                              </span>
                              <!--
                                Month-over-month arrow: last COMPLETE month vs the month
                                before, for this machine's site. Separate from the figure
                                on its left, which is a lifetime average — see
                                avgMthlySalesTrend() for why the current month is not used
                                and why this isn't a 30-day comparison.

                                No arrow at all when there is nothing to compare (older
                                totals_json shapes have no month buckets, or the site sold
                                nothing in either month). That is deliberately different
                                from the flat dot, which means "compared, and it barely
                                moved".

                                Kept to a single inline icon, and the figure beside it is
                                whitespace-nowrap, so the row can never grow to two lines —
                                alignAvgMthlySalesRows() pins each row to the height of its
                                twin in the machines cell, so a wrap here would overflow
                                rather than push the row taller. The icon is h-4 (16px)
                                inside a row pinned to a machines row that is never fewer
                                than two text lines, so it always has room.

                                STYLING IS SHARED WITH CustomerIndex.vue ON PURPOSE — same
                                ArrowUpIcon / ArrowDownIcon from @heroicons/vue/20/solid,
                                same h-4 w-4, same text-green-600 / text-red-600, same
                                stroke / stroke-width / stroke-linejoin, same
                                inline-flex items-center justify-center wrapper as the
                                Mthly Sales $ month chips there (CustomerIndex.vue ~L1909).
                                Change one, change both.

                                The grey bullet has no CustomerIndex twin: that page renders
                                NOTHING when two months are equal, but here "flat" is a real
                                third state (compared, and it moved less than ±1%) that has
                                to stay visually distinct from "no arrow at all" = nothing to
                                compare. Kept as a small bullet rather than an icon so it
                                cannot be mistaken for a direction.
                              -->
                              <span
                                v-if="avgMthlySalesTrend(vend)"
                                class="ml-1 inline-flex items-center justify-center shrink-0"
                                v-tooltip="avgMthlySalesTrend(vend).tooltip"
                              >
                                <ArrowUpIcon
                                  v-if="avgMthlySalesTrend(vend).dir === 'up'"
                                  class="h-4 w-4 text-green-600"
                                  stroke="currentColor" stroke-width="1.25" stroke-linejoin="round" aria-hidden="true"
                                />
                                <ArrowDownIcon
                                  v-else-if="avgMthlySalesTrend(vend).dir === 'down'"
                                  class="h-4 w-4 text-red-600"
                                  stroke="currentColor" stroke-width="1.25" stroke-linejoin="round" aria-hidden="true"
                                />
                                <span v-else class="text-[10px] leading-none text-gray-400">&bull;</span>
                              </span>
                              </template>
                              <span
                                v-else
                                class="text-gray-400"
                                v-tooltip="'No site attached, or this site has no lifetime sales figure yet, so there is nothing to average.'"
                              >
                                —
                              </span>
                            </li>
                          </ul>
                          <span class="text-center px-2 pt-2 text-xs invisible overflow-hidden" aria-hidden="true">
                            {{ productMapping.upcoming_vends_count || 0 }} Machine(s) at upcoming stage
                          </span>
                        </div>
                      </TableData>
                      <TableData :currentIndex="productMappingIndex" :totalLength="productMappings.length" inputClass="text-left">
                        <span v-if="productMapping.attachments && productMapping.attachments.length > 0">
                          <a :href="productMapping.attachments[0].full_url" target="_blank">
                            <img class="aspect-[3/2] rounded-2xl object-scale-down h-48 w-96" :src="productMapping.attachments[0].full_url" alt="" />
                          </a>
                        </span>
                      </TableData>
                      <TableData :currentIndex="productMappingIndex" :totalLength="productMappings.length" inputClass="text-center">
                        <div class="flex justify-center flex-col space-y-1" v-if="permissions.includes('update product-mappings')">
                          <!-- <Button
                            type="button" class="bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
                            @click="onEditClicked(productMapping)"
                          >
                            <PencilSquareIcon class="w-4 h-4"></PencilSquareIcon>
                            <span>
                                Edit
                            </span>
                          </Button> -->
                          <span>
                            <Button
                            type="button" class="bg-sky-300 hover:bg-sky-400 px-3 py-2 text-xs text-sky-800 flex space-x-1 w-fit"
                            @click="onAttachmentOverviewClicked(productMapping)"
                            v-if="productMapping.attachments && productMapping.attachments.length > 0"
                            >
                              <PhotoIcon class="h-4 w-4" aria-hidden="true"/>
                            </Button>
                          </span>
                          <Link :href="'/product-mappings/' + productMapping.id + '/edit'">
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
                            type="button" class="bg-blue-300 hover:bg-blue-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
                            @click="onVendFormEditClicked(productMapping)"
                          >
                            <LinkIcon class="w-4 h-4"></LinkIcon>
                            <span>
                                VM Binding
                            </span>
                          </Button>
                          <Button
                            type="button"
                            class="bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex-col space-y-1"
                            :class="[productMapping.vends && productMapping.vends.length > 0 ? 'opacity-50 cursor-not-allowed' : '']"
                            @click="onDeleteClicked(productMapping)"
                            :disabled="productMapping.vends && productMapping.vends.length > 0"
                            v-if="productMapping.operator_id"
                          >
                            <span class="flex space-x-1 items-center">
                              <TrashIcon class="w-4 h-4"></TrashIcon>
                              <span>
                                  Delete
                              </span>
                            </span>
                            <span v-if="productMapping.vends && productMapping.vends.length > 0">
                              (Binded)
                            </span>
                          </Button>
                        </div>
                      </TableData>
                      </tr>
                <tr v-if="!productMappings.data.length">
                  <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                      No Results Found
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator v-if="productMappings.data.length" :links="productMappings.links" :meta="productMappings.meta"></Paginator>
          </div>
      </div>
    </div>
  </div>
  <AttachmentOverview
    v-if="showAttachmentOverviewModal"
    :showModal="showAttachmentOverviewModal"
    @modalClose="onAttachmentOverviewModalClose"
    :model="productMapping"
    :items="attachments"
  >
  </AttachmentOverview>
  <Form
      v-if="showModal"
      :products="products"
      :productMapping="productMapping"
      :type="type"
      :showModal="showModal"
      @modalClose="onModalClose"
  >
  </Form>

  <VendForm
      v-if="showVendFormModal"
      :productMapping="productMapping"
      :type="type"
      :productMappingOptions="productMappingOptions"
      :showModal="showVendFormModal"
      :unbindedVends="unbindedVends"
      @modalClose="onVendFormModalClose"
  >

  </VendForm>

  </BreezeAuthenticatedLayout>
</template>

<script setup>
import AttachmentOverview from '@/Components/AttachmentOverview.vue';
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import Form from '@/Pages/ProductMapping/Form.vue';
import VendForm from '@/Pages/ProductMapping/VendForm.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
// ArrowUpIcon / ArrowDownIcon: the Avg Mthly Sales trend arrow uses the SAME
// heroicon + classes + stroke attributes as Vend/CustomerIndex.vue's Mthly Sales $
// month-over-month chips, so the two pages read as one system. Same icon pack
// (@heroicons/vue/20/solid) as CustomerIndex — do not switch to 24/outline.
import { ArrowDownIcon, ArrowUpIcon, BackspaceIcon, LinkIcon, MagnifyingGlassIcon, PhotoIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import TableData from '@/Components/TableData.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, onMounted, onUpdated, onBeforeUnmount, nextTick } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";
import moment from 'moment';

const props = defineProps({
  cmsEndpoint: String,
  products: Object,
  productMappings: Object,
  productMappingOptions: Object,
  unbindedVends: Object,
  vendPrefixOptions: Object,
  totalBindedVends: Number,
})

const filters = ref({
  is_active: true,
  name: '',
  upcoming_product_mapping: '',
  product: '',
  vend_code: '',
  site: '',
  vendStatus: '',
  sortKey: '',
  sortBy: true,
  numberPerPage: 100,
  vendPrefixes: [],
})
const attachments = ref([])
const booleanOptions = ref([])
const showAttachmentOverviewModal = ref(false)
const showModal = ref(false)
const showVendFormModal = ref(false)
const productMapping = ref()
const type = ref('')
const toast = useToast()
const numberPerPageOptions = ref([])
const roles = usePage().props.auth.roles
const permissions = usePage().props.auth.permissions
// operatorCountry — needed to format the per-machine L30d Sales figure with
// the right currency symbol + exponent, identical to Vend/CustomerIndex.vue.
const operatorCountry = usePage().props.auth.operatorCountry
const vendPrefixOptions = ref([])
const vendStatusOptions = ref([])

// ---------------------------------------------------------------------------
// Avg Mthly Sales (per binded machine)
// ---------------------------------------------------------------------------
// A straight port of Vend/CustomerIndex.vue's avgMthlySales() helper, on purpose:
// the two pages must print the SAME number for the same machine. Ops compared the
// old group total against the Machine page and read it as a per-machine figure
// (2026-07-31 feedback), so the column now lists one figure per binded machine
// instead of one sum per mapping.
//
// What it means: TRUE average monthly sales over the operating lifetime —
// lifetime sales divided by the COUNT of calendar months operated, inclusive of
// BOTH the begin month and the current month (a machine that began 2026-03-10 and
// is viewed in May 2026 has operated Mar/Apr/May = 3 months, whatever day it
// started). NOT a 30-day projection, so it is not expected to match the L30d chip
// on the same row.
//
// SOURCE — site-based, exactly like CustomerIndex: that page selects
// `customers.totals_json AS vend_transaction_totals_json` and `customers.begin_date`
// onto each vend row (VendController::customerIndex), so its "per machine" figure
// is really the machine's SITE figure. Here the same two columns arrive on the
// eager-loaded vend.customer. Two machines at the same site therefore show the
// SAME figure on both pages — the lines are not additive.
//
// The begin month is floored at the app-wide reporting floor (shared Inertia prop,
// from config/reporting.php — same floor CustomerIndex uses) so an abnormally old,
// zero or missing begin date can't inflate the month count and crush the average.
// Floor of 1 month guards against a begin date in the future.
// Column-level explanation, shown on the HEADER only. Everything a reader needs
// to interpret the column lives here, so the per-figure tooltips can stay to one
// short line. Keep the arrow legend in sync with computeAvgMthlySalesTrend().
const avgMthlySalesTooltip = 'Average monthly sales of the SITE this machine stands at: lifetime sales / the number of calendar months the site has been operating (from its begin date, floored at the app reporting floor, counting both the begin month and this month). A lifetime average, not a 30-day projection, so it will not match the L30d figure. Same number the Machine page shows for this machine. Two machines at the same site show the same figure — do not add the lines up.   ▲ ▼ • = last COMPLETE month vs the month before it, for that site — two closed months, so it does not drift as the current month fills up. A change within ±1% shows as • (no change). Hover any figure for its exact change.'

// True when this machine has something to average — a site, and a site whose
// totals_json already carries a lifetime figure. Drives the grey "—" so an empty
// cell is never mistaken for a zero-selling machine.
function hasAvgMthlySales(vend) {
  const customer = vend ? vend.customer : null
  if (!customer) {
    return false
  }

  const totals = customer.vendTransactionTotalsJson
  return !!totals && ('vend_records_amount_latest' in totals)
}

// Display value, in major units. Numerator is raw minor units (same convention as
// totals_json->vend_records_amount_latest), so divide by the currency exponent.
//
// begin_date_nullable, NOT begin_date: CustomerResource's `begin_date` runs
// Carbon::parse() unguarded and a NULL column (or one left out of a partial
// eager-load) resolves to TODAY — which would make months = 1 and report a whole
// lifetime of sales as one month's average. The nullable twin keeps null null so
// the reporting floor below can do its job.
function avgMthlySales(vend) {
  if (!hasAvgMthlySales(vend)) {
    return 0
  }

  const totals = vend.customer.vendTransactionTotalsJson
  const exponent = operatorCountry.currency_exponent ?? 2
  const lifetime = (totals['vend_records_amount_latest'] || 0) / Math.pow(10, exponent)

  const floorStr = (usePage().props.reportingFloorDate || '2023-01-01')
  const FLOOR = new Date(floorStr + 'T00:00:00')
  const beginStr = vend.customer.begin_date_nullable
  let begin = beginStr ? new Date(beginStr + 'T00:00:00') : null
  if (!begin || isNaN(begin.getTime()) || begin < FLOOR) {
    begin = FLOOR
  }

  const now = new Date()
  // Inclusive calendar-month count between the begin month and the current month.
  const months = Math.max(
    1,
    (now.getFullYear() - begin.getFullYear()) * 12 + (now.getMonth() - begin.getMonth()) + 1
  )

  return lifetime / months
}

// ---------------------------------------------------------------------------
// Month-over-month trend arrow
// ---------------------------------------------------------------------------
// The figure to the left is a LIFETIME average, which barely moves — so on its
// own it never tells ops whether a site is picking up or dying. The arrow adds
// that: it compares the last COMPLETE calendar month against the month before
// it, for the same SITE the figure is derived from.
//
// WHY LAST-COMPLETE, NOT CURRENT: `current_mth_amount` is the month in progress.
// On the 1st of a month it holds a few dollars, so an arrow using it would show
// the entire fleet collapsing every month-start. `last_mth_amount` is always a
// closed month, so the comparison is like-for-like on any day.
//
// WHY NOT 30 DAYS: totals_json carries `thirty_days_amount` (the L30d chip in
// the neighbouring column) but there is NO previous-30-day twin anywhere in the
// schema — a rolling comparison would need new SQL over vend_transactions. The
// month buckets are already in this page's payload, so this costs no extra
// query.
//
// SOURCE / NAMING: verified on live against customer_period_summaries (the
// authoritative monthly ledger) — `last_mth_amount` equals that site's previous
// calendar month to the cent, and `last_2_mth_amount` the month before it. They
// are DISCRETE months, not rolling or cumulative sums, despite the "last_2"
// name reading like "the last 2 months combined".
//
// Raw minor units, same convention as vend_records_amount_latest.
//
// Returns null when there is nothing honest to compare — the keys are absent
// (older totals_json shapes lack them), or both months are zero. Null renders no
// arrow at all, which is deliberately distinct from a flat arrow: "we don't
// know" must not look like "no change".
//
// MEMOISED per vend object (see avgMthlySalesTrend below) because the template
// needs the result three times per row — v-if, colour class and tooltip — and
// this builds a string with two toLocaleString calls. On a "All" page that is
// thousands of rows; computing it once per machine keeps it off the render path.
function computeAvgMthlySalesTrend(vend) {
  const totals = (vend && vend.customer) ? vend.customer.vendTransactionTotalsJson : null
  if (!totals) {
    return null
  }
  if (!('last_mth_amount' in totals) || !('last_2_mth_amount' in totals)) {
    return null
  }

  const last = Number(totals['last_mth_amount'] || 0)
  const prev = Number(totals['last_2_mth_amount'] || 0)
  if (!isFinite(last) || !isFinite(prev)) {
    return null
  }
  // A site with no sales in either month has no trend, only absence.
  if (last === 0 && prev === 0) {
    return null
  }

  const exponent = operatorCountry.currency_exponent ?? 2
  const toMajor = (cents) => (cents / Math.pow(10, exponent)).toLocaleString(undefined, {
    minimumFractionDigits: operatorCountry.is_currency_exponent_hidden ? 0 : exponent,
    maximumFractionDigits: operatorCountry.is_currency_exponent_hidden ? 0 : exponent,
  })

  // Percentage is undefined against a zero base — a site that sold nothing last
  // month and something this month is "up", but not "up N%". Keep pct null and
  // let the tooltip say so rather than printing Infinity.
  const pct = prev > 0 ? ((last - prev) / prev) * 100 : null

  // ±1% deadband: without it, ordinary noise paints half the column red and ops
  // stops trusting the arrows.
  let dir = 'flat'
  if (pct === null) {
    dir = last > 0 ? 'up' : 'flat'
  } else if (pct >= 1) {
    dir = 'up'
  } else if (pct <= -1) {
    dir = 'down'
  }

  // SHORT on purpose: the column's full explanation is on the header tooltip, so
  // hovering a figure only has to answer "which way, and by how much?".
  // pct is null ONLY when the prior month is zero — both-months-zero already
  // returned null above — so that branch always means a new or restarted site.
  const headline = pct === null
    ? 'New — no sales the month before'
    : (pct >= 0 ? '+' : '') + pct.toFixed(1) + '% vs the month before'

  return {
    dir,
    pct,
    tooltip: headline
      + ' (' + operatorCountry.currency_symbol + toMajor(prev)
      + ' → ' + operatorCountry.currency_symbol + toMajor(last) + ')',
  }
}

// Tooltip for the figure itself: its own month-on-month change, or a one-liner
// saying why there is no arrow. Never the column explanation — that is on the
// header, so hovering a number can no longer blanket the screen.
function avgMthlySalesFigureTooltip(vend) {
  const trend = avgMthlySalesTrend(vend)

  return trend
    ? trend.tooltip
    : 'No month-on-month comparison for this site yet.'
}

// Memo keyed on the vend object itself. Inertia hands us a fresh object graph on
// every navigation, so entries die with the page and a WeakMap needs no manual
// clearing. Safe because this page's row data is render-only — nothing mutates a
// vend's totals in place; if that ever changes, this cache has to go.
const avgMthlySalesTrendCache = new WeakMap()

function avgMthlySalesTrend(vend) {
  if (!vend || typeof vend !== 'object') {
    return null
  }
  if (avgMthlySalesTrendCache.has(vend)) {
    return avgMthlySalesTrendCache.get(vend)
  }

  const trend = computeAvgMthlySalesTrend(vend)
  avgMthlySalesTrendCache.set(vend, trend)

  return trend
}

// ---------------------------------------------------------------------------
// Avg Mthly Sales row alignment
// ---------------------------------------------------------------------------
// The Avg Mthly Sales column prints one bare figure per binded machine and no
// machine code — the reader identifies each figure purely by which line it is
// on, so it MUST sit level with its machine in the Binded Vending Machines cell.
//
// CSS alone can't do it. The two cells have very different widths, so every
// piece of text wraps to a different number of lines on each side: a machine row
// is 2-4 lines next to a one-line figure, and even the heading and the
// "N Machine(s) at upcoming stage" footnote — duplicated invisibly here purely
// to reserve the same space — wrap differently and end up taller in the narrow
// column. (That last one is what left the first attempt sitting a line high: the
// figure block was taller than the machines block, so vertical-align:middle
// pushed the machines block down relative to it.)
//
// So nothing is assumed: every part is MEASURED. Both cells render the same
// three children in the same order — heading, <ul> of rows, footnote — and each
// one's height, plus each row's height, is copied across. Equal parts ⇒ equal
// total height ⇒ the <td> default vertical-align:middle centres both blocks
// identically ⇒ every figure lands on its machine's line.
//
// Purely cosmetic: if this never runs, the figures stay in the right ORDER and
// the numbers stay correct — they just drift off their machine's line.
const bindedVendsCellRefs = new Map()   // productMapping.id -> wrapper div in the machines cell
const avgMthlySalesCellRefs = new Map() // productMapping.id -> wrapper div in this column
let avgMthlySalesAlignFrame = null
let avgMthlySalesResizeObserver = null

// Vue calls these with `null` when the element unmounts (pagination, filter
// change, "All" → 100), which is what keeps the Maps from leaking rows.
function setBindedVendsCellRef(id, el) {
  if (el) {
    bindedVendsCellRefs.set(id, el)
    observeBindedVendsCell(el)
  } else {
    bindedVendsCellRefs.delete(id)
  }
}

function setAvgMthlySalesCellRef(id, el) {
  if (el) {
    avgMthlySalesCellRefs.set(id, el)
  } else {
    avgMthlySalesCellRefs.delete(id)
  }
}

// Re-measure whenever a machines cell changes height on its own — a late web
// font, the menu thumbnail loading, browser zoom, a column resize. Cheaper and
// more reliable than trying to enumerate when that can happen.
function observeBindedVendsCell(el) {
  if (typeof ResizeObserver === 'undefined') {
    return
  }
  if (!avgMthlySalesResizeObserver) {
    avgMthlySalesResizeObserver = new ResizeObserver(() => scheduleAvgMthlySalesAlign())
  }
  avgMthlySalesResizeObserver.observe(el)
}

// Coalesce every trigger into one measure/write pass per animation frame.
function scheduleAvgMthlySalesAlign() {
  if (typeof window === 'undefined' || typeof window.requestAnimationFrame !== 'function') {
    return
  }
  if (avgMthlySalesAlignFrame !== null) {
    return
  }
  avgMthlySalesAlignFrame = window.requestAnimationFrame(() => {
    avgMthlySalesAlignFrame = null
    alignAvgMthlySalesRows()
  })
}

// Read every height first, then write them — one layout pass per row, no thrash.
//
// No feedback loop with the ResizeObserver: the figure block is only ever made
// to match the machines block, never to exceed it, and the machines block's own
// height is content-driven (the <td> doesn't stretch it), so writing here can't
// change what was measured.
function alignAvgMthlySalesRows() {
  avgMthlySalesCellRefs.forEach((avgCell, id) => {
    const bindedCell = bindedVendsCellRefs.get(id)
    if (!avgCell || !bindedCell) {
      return
    }

    // [heading, <ul>, footnote] on both sides — see the template comments. Bail
    // rather than mis-assign if either cell is ever restructured.
    const sourceParts = bindedCell.children
    const targetParts = avgCell.children
    if (sourceParts.length !== targetParts.length) {
      return
    }

    const partHeights = []
    const rowHeights = []

    for (let i = 0; i < targetParts.length; i++) {
      const isList = targetParts[i].tagName === 'UL' && sourceParts[i].tagName === 'UL'
      // The <ul> is left to its own (summed row) height; only the spacers around
      // it are pinned, otherwise the rows inside would be squeezed.
      partHeights.push(isList ? null : sourceParts[i].getBoundingClientRect().height)

      if (isList) {
        const sourceRows = sourceParts[i].children
        const targetRows = targetParts[i].children
        for (let r = 0; r < targetRows.length; r++) {
          rowHeights.push({
            el: targetRows[r],
            // null = no machine row to match (shouldn't happen, both lists render
            // the same v-for) — clear any stale height rather than freeze it.
            height: sourceRows[r] ? sourceRows[r].getBoundingClientRect().height : null,
          })
        }
      }
    }

    for (let i = 0; i < targetParts.length; i++) {
      targetParts[i].style.height = partHeights[i] ? partHeights[i] + 'px' : ''
    }
    rowHeights.forEach((row) => {
      row.el.style.height = row.height ? row.height + 'px' : ''
    })
  })
}

onMounted(() => {
  scheduleAvgMthlySalesAlign()
  if (typeof window !== 'undefined') {
    window.addEventListener('resize', scheduleAvgMthlySalesAlign)
  }
})

// Every Inertia navigation on this page (search, filter, sort, paginate) re-renders
// the table in place, so re-align after the DOM settles.
onUpdated(() => {
  nextTick(() => scheduleAvgMthlySalesAlign())
})

onBeforeUnmount(() => {
  if (typeof window !== 'undefined') {
    window.removeEventListener('resize', scheduleAvgMthlySalesAlign)
    if (avgMthlySalesAlignFrame !== null) {
      window.cancelAnimationFrame(avgMthlySalesAlignFrame)
      avgMthlySalesAlignFrame = null
    }
  }
  if (avgMthlySalesResizeObserver) {
    avgMthlySalesResizeObserver.disconnect()
    avgMthlySalesResizeObserver = null
  }
  bindedVendsCellRefs.clear()
  avgMthlySalesCellRefs.clear()
})

onMounted(() => {
  booleanOptions.value = [
    {id: 'true', value: 'Yes'},
    {id: 'false', value: 'No'},
  ]
  numberPerPageOptions.value = [
    { id: 100, value: 100 },
    { id: 200, value: 200 },
    { id: 500, value: 500 },
    { id: 'All', value: 'All' },
  ]
  vendPrefixOptions.value = [
    { id: '', value: 'All' },
    {id: 'single-ud', value: 'Single UD'},
    ...props.vendPrefixOptions.data.map((data) => {return {id: data.id, value: data.name}})
  ]
  vendStatusOptions.value = [
			{id: 'all', value: 'All'},
			{id: 'factory', value: 'Factory (JB)'},
			{id: 'active', value: 'Active'},
			{id: 'inactive', value: 'Not Active'},
			{id: 'disposed', value: 'Disposed'},
      {id: 'sold', value: 'Sold'},
	]
  filters.value.is_active = booleanOptions.value[0]
  filters.value.numberPerPage = numberPerPageOptions.value[0]
  filters.value.vendStatus = vendStatusOptions.value[2]
})

function onAttachmentOverviewClicked(model) {
  attachments.value = model.attachments
  showAttachmentOverviewModal.value = true
}

function onAttachmentOverviewModalClose() {
  showAttachmentOverviewModal.value = false
}

function onCreateClicked() {
  type.value = 'create'
  productMapping.value = null
  showModal.value = true
}

function onDeleteClicked(productMapping) {
  const approval = confirm('Are you sure to delete ' + productMapping.name + '?');
  if (!approval) {
      return;
  }
  router.delete('/product-mappings/' + productMapping.id, {
    onSuccess: () => {
      toast.success("Product mapping deleted successfully", { timeout: 3000 })
    },
    onError: () => {
      toast.error("Failed to delete product mapping", { timeout: 3000 })
    }
  })
}

function onEditClicked(productMappingValue) {
  type.value = 'update'
  productMapping.value = productMappingValue
  showModal.value = true
}

function onVendFormEditClicked(productMappingValue) {
  type.value = 'update'
  productMapping.value = productMappingValue
  router.visit(
      route('product-mappings', {
          id: productMappingValue.id
      }),{
          only: ['unbindedVends'],
          preserveState: true,
      }
  );
  showVendFormModal.value = true
}

function onSearchFilterUpdated() {
  router.get('/product-mappings', {
      ...filters.value,
      is_active: filters.value.is_active.id,
      vendStatus: filters.value.vendStatus.id,
      numberPerPage: filters.value.numberPerPage.id,
      vendPrefixes: filters.value.vendPrefixes.map((vendPrefix) => { return vendPrefix.id }),
  }, {
      preserveState: true,
      replace: true,
  })
}


function resetFilters() {
  router.get('/product-mappings')
}

function sortTable(sortKey, inverse = false) {
  filters.value.sortBy = !filters.value.sortBy
  if(inverse && filters.value.sortKey != sortKey) {
      filters.value.sortBy = !filters.value.sortBy
  }
  filters.value.sortKey = sortKey
  onSearchFilterUpdated()
}

function onModalClose() {
  showModal.value = false
}

function onVendFormModalClose() {
  showVendFormModal.value = false
}
</script>