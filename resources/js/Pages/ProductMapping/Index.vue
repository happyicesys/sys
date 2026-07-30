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
                      Avg Mthly Sales — GROUP figure for the whole product mapping,
                      i.e. the sum of the average monthly sales of every site this
                      mapping is binded to. Same "true average monthly sales" idea as
                      Vend/CustomerIndex.vue's "Avg Mthly Sales $" column (lifetime
                      sales ÷ months operating, NOT a 30-day projection), but grouped
                      per product mapping instead of per machine.

                      The per-site figure is SITE-based (customers.totals_json +
                      customers.begin_date), deliberately matching the L30d Sales chip
                      in the Binded Vending Machines column rather than
                      Vend/CustomerIndex.vue's machine-based figure. Consequence worth
                      knowing: the figures here will NOT tally with the Machine page's
                      Avg Mthly Sales $, and a site hosting machines on OTHER mappings
                      too contributes its whole site average here.

                      SORTABLE, and therefore summed in SQL
                      (ProductMappingController::index $avgMthlySalesSub → the
                      avg_mthly_sales_amount alias) rather than in this component the
                      way CustomerIndex does it: sorting a client-side figure would
                      only ever reorder the rows on the current page.

                      First click sorts DESC — highest-earning menu at the top, which
                      is what this column is for. That is what passing inverse=false
                      does given sortTable()'s toggle (sortBy starts true = asc, the
                      unconditional flip makes the first click desc); pass true
                      instead to make the first click ascending.
                    -->
                    <TableHeadSort
                      modelName="avg_mthly_sales_amount"
                      :sortKey="filters.sortKey"
                      :sortBy="filters.sortBy"
                      @sort-table="sortTable('avg_mthly_sales_amount', false)"
                    >
                      <div class="flex flex-col space-y-1">
                        <span>Avg Mthly Sales</span>
                        <span class="text-black font-normal text-xs">{{ operatorCountry.currency_symbol }} / month</span>
                        <span class="text-black font-normal text-xs">(all binded sites)</span>
                      </div>
                    </TableHeadSort>
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
                        <div class="flex flex-col space-y-1">
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
                        Avg Mthly Sales (group total) — see the matching TableHead
                        comment above. One figure per mapping, summed IN SQL so the
                        header sort covers every row and not just this page; this cell
                        only formats it. Greyed when zero. The grey footnote is counted
                        here in the component (it needs the per-machine list, which the
                        SQL figure doesn't carry) and only appears when some binded
                        machines could NOT contribute — no site attached, or the site's
                        totals_json has no lifetime figure yet — so the headline number
                        is never quietly short without saying so.
                      -->
                      <TableData :currentIndex="productMappingIndex" :totalLength="productMappings.length" inputClass="text-center">
                        <div class="flex flex-col space-y-1">
                          <span
                            :class="avgMthlySales(productMapping) > 0 ? 'text-gray-900 font-semibold' : 'text-gray-400'"
                            v-tooltip="avgMthlySalesTooltip"
                          >
                            {{ operatorCountry.currency_symbol }}{{ avgMthlySales(productMapping).toLocaleString(undefined, { minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent) }) }}
                          </span>
                          <span
                            class="text-gray-400 text-xs"
                            v-if="avgMthlySalesUncounted(productMapping) > 0"
                            v-tooltip="'These binded machines have no site attached, or their site has no lifetime sales figure yet, so they contribute nothing to the total above.'"
                          >
                            {{ avgMthlySalesUncounted(productMapping) }} of {{ productMapping.vends.length }} machine(s) not counted
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
import { BackspaceIcon, LinkIcon, MagnifyingGlassIcon, PhotoIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import TableData from '@/Components/TableData.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
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
// Avg Mthly Sales (group column)
// ---------------------------------------------------------------------------
// The figure itself is computed IN SQL, not here — see
// ProductMappingController::index() ($avgMthlySalesSub → the
// avg_mthly_sales_amount alias, raw minor units). It lives server-side because
// the header is SORTABLE: a client-side figure could only ever reorder the rows
// on the current page, never the whole paginated set.
//
// What it means: TRUE average monthly sales over the operating lifetime —
// lifetime sales divided by the COUNT of calendar months operated, inclusive of
// both the begin month and the current month. NOT a 30-day projection, so it is
// not expected to match the L30d chips in the neighbouring column.
//
// Two deliberate differences from Vend/CustomerIndex.vue's "Avg Mthly Sales $":
//   1. GROUPING — one figure per product mapping (the sum over its binded
//      sites), not one per machine.
//   2. SOURCE — SITE-based (customers.totals_json + customers.begin_date),
//      matching the L30d chip on this page rather than CustomerIndex's
//      machine-based vends.vend_transaction_totals_json. The vend total follows
//      the machine's vend_id and would keep counting sales earned under a
//      previous site after the machine is moved.
//      Consequence: figures here will NOT tally with the Machine page, and a
//      site that also hosts machines on OTHER mappings still contributes its
//      whole site average to this mapping's total.
//
// Sites are summed ONCE even if several of a mapping's binded machines sit at
// the same site — the site figure already covers all of them. That is inherent
// to the subquery being driven from customers rather than from vends.
const avgMthlySalesTooltip = 'Sum of the average monthly sales of every site this mapping is binded to. Per site: lifetime sales / the number of calendar months the site has been operating (counted from its begin date, floored at the app reporting floor, inclusive of the begin month and this month). A lifetime average, not a 30-day projection, so it will not match the L30d figures — and being site-based it is not expected to tally with the Avg Mthly Sales $ on the Machine page. Each site counts once even if it hosts several of this mapping\'s machines. Click the header to sort.'

// Display value: the SQL sum arrives in raw minor units, same convention as
// totals_json->vend_records_amount_latest.
function avgMthlySales(productMapping) {
  const amount = Number(productMapping.avg_mthly_sales_amount || 0)
  if (!isFinite(amount)) {
    return 0
  }
  return amount / Math.pow(10, operatorCountry.currency_exponent ?? 2)
}

// Binded machines that contribute NOTHING to the figure above — no site
// attached, or a site whose totals_json has no lifetime figure yet. Counted here
// rather than in SQL because it needs the per-machine list, which the summed
// figure doesn't carry. Machines sharing an already-counted site are NOT flagged:
// the site's figure covers them. Drives the grey footnote so the headline number
// is never quietly short without saying so.
function avgMthlySalesUncounted(productMapping) {
  const vends = (productMapping && productMapping.vends) ? productMapping.vends : []
  const countedCustomerIds = new Set()
  let uncounted = 0

  vends.forEach((vend) => {
    const customer = vend ? vend.customer : null
    if (!customer || !customer.id) {
      uncounted++
      return
    }
    if (countedCustomerIds.has(customer.id)) {
      return
    }

    const totals = customer.vendTransactionTotalsJson
    if (!totals || !('vend_records_amount_latest' in totals)) {
      uncounted++
      return
    }

    countedCustomerIds.add(customer.id)
  })

  return uncounted
}

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