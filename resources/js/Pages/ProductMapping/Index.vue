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
          <SearchInput placeholderStr="Site name / ID" v-model="filters.site">
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
                        Binded Vending Machines header — this column also carries
                        the per-machine Ref Price tier chip (RP1..RP5), sourced
                        from customers.selling_price_type, the same source as
                        Vend/CustomerIndex.vue's "Ref Price" column.

                        L30d Sales USED TO LIVE HERE (2026-08-03: MOVED to the
                        Avg Mthly Sales column). It moved because the trend arrow
                        now compares L30d against the lifetime average, and a
                        comparison is unreadable when its two numbers sit in
                        different columns — ops was reading the arrow as if it
                        described the average beside it. The two figures being
                        compared must share a cell. Do NOT re-add an L30d chip
                        here; there would then be two of them.

                        Binded date stays as the per-row chip.

                        SORTING (2026-08-13): "Binded Vending Machines" and
                        "(binded date)" are click-toggles for the machine list
                        INSIDE each row's cell — machine code and binded date
                        respectively, default binded date newest-first. This is
                        NOT the page-level row sort (filters.sortKey); it's
                        client-side (vendListSort / sortedVends) because every
                        mapping's machines ship fully loaded with the row, and
                        one shared state sorts every row's list the same way.
                      -->
                      <div class="flex flex-col space-y-1">
                        <a
                          href="#"
                          class="inline-flex items-center justify-center gap-0.5 hover:text-blue-800"
                          @click.prevent="sortVendList('code')"
                        >
                          <span>Binded Vending Machines</span>
                          <svg v-if="vendListSort.key === 'code' && !vendListSort.desc" xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                          </svg>
                          <svg v-if="vendListSort.key === 'code' && vendListSort.desc" xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                          </svg>
                        </a>
                        <span class="text-black font-normal text-xs">RP Tier</span>
                        <a
                          href="#"
                          class="inline-flex items-center justify-center gap-0.5 font-normal text-xs text-blue-600 hover:text-blue-800"
                          @click.prevent="sortVendList('binded_at')"
                        >
                          <span>(binded date)</span>
                          <svg v-if="vendListSort.key === 'binded_at' && !vendListSort.desc" xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                          </svg>
                          <svg v-if="vendListSort.key === 'binded_at' && vendListSort.desc" xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                          </svg>
                        </a>
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

                      2026-08-03: L30d Sales MOVED IN from the Binded Vending
                      Machines column, and the trend arrow now compares L30d
                      against the lifetime average instead of last month against
                      the month before. Ops read the arrow as describing the
                      number printed beside it, so it now does exactly that:
                      "is this site's last 30 days running above or below its own
                      average month?". Both numbers being compared live in this
                      one cell — that adjacency is the whole point of the move,
                      so do not split them apart again.

                      NOT SORTABLE any more. A per-machine list has no single value
                      to sort rows by; the SQL sum that made the header clickable
                      went away with the group total (see ProductMappingController).
                    -->
                    <TableHead>
                      <!--
                        The full explanation of this column lives HERE, on the header,
                        not on every figure. It used to hang off each number, where a
                        paragraph-length tooltip covered most of the screen on hover.
                        Figures carry only their own short line.
                      -->
                      <div class="flex flex-col space-y-1 cursor-help" v-tooltip="avgMthlySalesTooltip">
                        <span>Avg Mthly Sales</span>
                        <span class="text-black font-normal text-xs">{{ operatorCountry.currency_symbol }} / month</span>
                        <span class="text-black font-normal text-xs">vs L30d Sales ▲▼</span>
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
                            <li class="flex flex-wrap gap-x-2 gap-y-1 py-1 px-3" v-for="(vend, vendIndex) in sortedVends(productMapping)">
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
                                Per-machine RP chip + binded-date sub-row.
                                basis-full forces this onto its own line under
                                the inline customer info above. Indented to
                                line up roughly under the customer text.
                                RP chip styling matches Vend/CustomerIndex.vue
                                (indigo pill).

                                L30d Sales was removed from here on 2026-08-03 and
                                now renders in the Avg Mthly Sales column, directly
                                under the average it is compared against. See that
                                column's header comment for why.
                              -->
                              <div class="basis-full flex items-center space-x-2 pl-6 text-xs">
                                <span
                                  v-if="vend.customer && vend.customer.selling_price_type"
                                  class="inline-flex rounded px-0.5 py-0.5 border bg-indigo-100 text-indigo-800 border-indigo-300"
                                >
                                  RP{{ vend.customer.selling_price_type }}
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
                          <!--
                            Clickable when the count is non-zero: opens a popup
                            listing exactly WHICH machines are queued onto this
                            mapping (fetched on click — see onUpcomingVendsClicked).
                            Zero stays plain grey text, nothing to open.

                            Kept as ONE <span> with the count and the label
                            inside, rather than a <button> wrapping a separate
                            text node: this element's height is measured and
                            copied onto its invisible twin in the Avg Mthly Sales
                            column (alignAvgMthlySalesRows), so its box must stay
                            a plain inline-level run of text that wraps the same
                            way. The underline is the only added ink.
                          -->
                          <span
                            class="text-center px-2 pt-2 text-xs"
                            :class="[
                              (productMapping.upcoming_vends_count || 0) > 0 ? 'text-gray-700 font-semibold' : 'text-gray-400',
                              (productMapping.upcoming_vends_count || 0) > 0 ? 'cursor-pointer underline decoration-dotted underline-offset-2 hover:text-blue-700' : '',
                            ]"
                            :role="(productMapping.upcoming_vends_count || 0) > 0 ? 'button' : null"
                            :tabindex="(productMapping.upcoming_vends_count || 0) > 0 ? 0 : null"
                            v-tooltip="upcomingStageTooltip(productMapping.upcoming_vends_count)"
                            @click="onUpcomingVendsClicked(productMapping)"
                            @keydown.enter="onUpcomingVendsClicked(productMapping)"
                            @keydown.space.prevent="onUpcomingVendsClicked(productMapping)"
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

                        TWO LABELLED LINES PER MACHINE since 2026-08-03:

                            Avg:  S$1,398.64
                            L30d: S$1,960.60 ▲

                        laid out as a 3-COLUMN GRID (label / figure / arrow gutter),
                        not as two independently-centred flex rows. That is the whole
                        reason for the grid: two centred rows each centre on their OWN
                        width, so "Avg:" and "L30d:" — and worse, the two amounts —
                        come out ragged and the eye cannot compare them. The grid gives
                        the labels one right-aligned column (colons line up) and the
                        amounts another right-aligned column (decimal points line up),
                        so the pair reads as one comparison.

                        THE ARROW GUTTER IS ALWAYS EMITTED, even on the Avg row and
                        even when there is no arrow. Grid auto-placement is sequential:
                        drop the empty third cell from row 1 and the L30d LABEL flows
                        up into row 1's arrow column, shredding the layout. Any future
                        row added here must emit exactly three cells.

                        Its height is still pinned by alignAvgMthlySalesRows() to the
                        machines row opposite, which is always 3+ text lines (code /
                        site name / RP + binded date, often an upcoming badge too), so
                        two lines always fit.
                      -->
                      <TableData :currentIndex="productMappingIndex" :totalLength="productMappings.length" inputClass="text-center">
                        <div class="flex flex-col space-y-1" :ref="(el) => setAvgMthlySalesCellRef(productMapping.id, el)">
                          <span class="text-center p-2 text-xs invisible overflow-hidden" aria-hidden="true">
                            {{ productMapping.vends.length }} Machine(s)
                          </span>
                          <ul class="divide-y divide-gray-200">
                            <!-- MUST iterate the SAME sortedVends() as the Binded Vending Machines cell — the two lists pair by index (alignAvgMthlySalesRows), so a differing order silently puts figures on the wrong machine. -->
                            <li class="flex flex-col items-center justify-center py-1 px-3" v-for="vend in sortedVends(productMapping)">
                              <!--
                                The whole two-line block is ONE element, so the grey "—"
                                below is an ordinary v-if/v-else pair on siblings and is
                                immune to the re-parenting trap that bit this cell on
                                2026-08-01 (Vue pairs v-else with the nearest preceding
                                ELEMENT, skipping only whitespace and comments — so a
                                second element added between the block and the dash would
                                silently steal the dash, and a machine with a figure would
                                render the figure AND the dash side by side; that hit 9 of
                                400 live rows). KEEP IT AS ONE ELEMENT: anything new goes
                                INSIDE this grid, never between it and the v-else.
                              -->
                              <div
                                v-if="hasAvgMthlySales(vend)"
                                class="inline-grid grid-cols-[auto_auto_auto] items-center gap-x-1"
                              >
                                <!-- Row 1 — Avg Mthly Sales. Third cell reserves the arrow column. -->
                                <span class="text-xs text-gray-500 text-right">Avg:</span>
                                <span
                                  class="whitespace-nowrap text-right"
                                  :class="[vend.is_active || vend.is_testing ? 'text-gray-800 font-semibold' : 'text-gray-400']"
                                  v-tooltip="avgMthlySalesFigureTooltip(vend)"
                                >
                                  {{ operatorCountry.currency_symbol }}{{ avgMthlySales(vend).toLocaleString(undefined, { minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent) }) }}
                                </span>
                                <span class="w-4" aria-hidden="true"></span>
                                <!--
                                  Row 2 — L30d Sales + trend arrow. MOVED HERE 2026-08-03
                                  from the Binded Vending Machines cell so the arrow sits
                                  on the number it is measured against, one line under the
                                  average it is compared to. Same source as before
                                  (vend.customer.vendTransactionTotalsJson.thirty_days_amount,
                                  i.e. customers.totals_json → SITE-based, NOT the vend's own
                                  vend_transaction_totals_json, which is keyed on vend_id and
                                  follows the machine, so a relocated machine would keep
                                  showing sales earned at its previous site), same
                                  currency/exponent convention.

                                  COLOUR NOW COMES FROM THE ARROW (2026-08-03, second pass).
                                  It originally kept the >1,000 green/red site-size threshold
                                  it carried in the other column, which produced a red
                                  S$784.30 next to a green ↑ — both "correct" (small site,
                                  but running above its own average) and unreadable as a
                                  pair. One cell, one meaning: green/red here now says
                                  above/below this site's own average, exactly like the arrow
                                  beside it. The >1,000 threshold still lives on
                                  CustomerIndex's Last30d, which is where ops reads site size.
                                  Do NOT reintroduce a second colour rule in this cell.

                                  <template> so all three cells appear or vanish together
                                  and the grid never ends up with a half row.

                                  No arrow at all when there is nothing to compare (no
                                  thirty_days_amount key, or no positive average to measure
                                  against). Deliberately different from the flat dot, which
                                  means "compared, and it barely moved". The arrow's own
                                  v-if lives INSIDE the third cell, so the cell — and the
                                  column width — is there either way.

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
                                  to stay visually distinct from "no arrow at all" = nothing
                                  to compare. Kept as a small bullet rather than an icon so it
                                  cannot be mistaken for a direction.
                                -->
                                <template v-if="hasL30dSales(vend)">
                                  <span class="text-xs text-gray-500 text-right">L30d:</span>
                                  <span
                                    class="whitespace-nowrap text-right"
                                    :class="l30dSalesColourClass(vend)"
                                    v-tooltip="l30dSalesTooltip(vend)"
                                  >
                                    {{ operatorCountry.currency_symbol }}{{ l30dSales(vend).toLocaleString(undefined, { minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent) }) }}
                                  </span>
                                  <span class="inline-flex w-4 items-center justify-center">
                                    <span
                                      v-if="avgMthlySalesTrend(vend)"
                                      class="inline-flex items-center justify-center shrink-0"
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
                                  </span>
                                </template>
                              </div>
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

  <!--
    "N Machine(s) at upcoming stage" popup — the machines behind that figure.

    Teleported to body: this lives inside a wide, horizontally-scrolling table,
    and a fixed-position dialog rendered in that subtree inherits its scroll
    offset. Same reason DeliveryPlatform/ChannelOverview teleports.

    Rows are whatever GET /product-mappings/{id}/upcoming-vends returns, which
    resolves the EFFECTIVE upcoming mapping with the identical WHERE the count
    sub-select uses — so the list length always equals the number in the link
    that opened it. The vendStatus filter currently applied to the page is sent
    along for the same reason.
  -->
  <Teleport to="body">
    <Modal :open="showUpcomingVendsModal" @modalClose="onUpcomingVendsModalClose">
      <template #header>
        <div class="flex flex-col">
          <span class="text-gray-600 text-base">
            Machines at upcoming stage
          </span>
          <span class="text-sm text-gray-500" v-if="upcomingVendsMapping">
            Queued to switch onto: {{ upcomingVendsMapping.name }}
          </span>
        </div>
      </template>
      <template #default>
        <div class="flex flex-col text-sm">
          <p class="text-xs text-gray-500 pb-3">
            These machines are binded to another mapping today but are queued to
            switch onto this one — either because the machine itself was set to it
            (<span class="font-medium">Own</span>), or because it inherits it as the
            preset upcoming of the mapping it is on now
            (<span class="font-medium">Inherited</span>). None of them are on this
            mapping yet.
          </p>

          <div v-if="upcomingVendsLoading" class="py-6 text-center text-gray-500">
            Loading…
          </div>
          <div v-else-if="upcomingVendsError" class="py-6 text-center text-red-600">
            {{ upcomingVendsError }}
          </div>
          <div v-else-if="!upcomingVends.length" class="py-6 text-center text-gray-500">
            No machines at upcoming stage.
          </div>
          <div v-else class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="px-2 py-2 text-left text-xs font-semibold text-gray-700">#</th>
                  <th class="px-2 py-2 text-left text-xs font-semibold text-gray-700">Machine ID</th>
                  <th class="px-2 py-2 text-left text-xs font-semibold text-gray-700">Site</th>
                  <th class="px-2 py-2 text-left text-xs font-semibold text-gray-700">Current Mapping</th>
                  <th class="px-2 py-2 text-left text-xs font-semibold text-gray-700">Changeover</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-200">
                <tr v-for="(vend, index) in upcomingVends" :key="vend.id">
                  <td class="px-2 py-2 align-top text-xs text-gray-500">{{ index + 1 }}.</td>
                  <td class="px-2 py-2 align-top text-xs whitespace-nowrap">
                    <a :href="'/vends/customers?codes=' + vend.code" target="_blank" class="text-blue-700">
                      {{ vend.code }}
                    </a>
                    <span class="text-gray-500" v-if="vend.vend_prefix_name">
                      ({{ vend.vend_prefix_name }})
                    </span>
                  </td>
                  <td class="px-2 py-2 align-top text-xs">
                    <a
                      v-if="vend.customer_id && permissions.includes('admin-access vends')"
                      :href="'/customers/' + vend.customer_id + '/edit'"
                      target="_blank"
                      :class="vend.customer_is_active ? 'text-blue-700' : 'text-gray-400'"
                    >
                      {{ vend.site_id }}<br>{{ vend.customer_name }}
                    </a>
                    <span v-else-if="vend.customer_id" :class="vend.customer_is_active ? 'text-gray-800' : 'text-gray-400'">
                      {{ vend.site_id }}<br>{{ vend.customer_name }}
                    </span>
                    <span v-else class="text-gray-400">—</span>
                  </td>
                  <td class="px-2 py-2 align-top text-xs">
                    <a
                      v-if="vend.current_product_mapping_id"
                      :href="'/product-mappings/' + vend.current_product_mapping_id + '/edit'"
                      target="_blank"
                      class="text-blue-700"
                    >
                      {{ vend.current_product_mapping_name }}
                    </a>
                    <span v-else class="text-gray-400">Not binded</span>
                  </td>
                  <td class="px-2 py-2 align-top text-xs">
                    <span
                      class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit"
                      :class="vend.source === 'own'
                        ? 'bg-orange-100 text-orange-800 border-orange-300'
                        : 'bg-gray-100 text-gray-700 border-gray-300'"
                      v-tooltip="vend.source === 'own'
                        ? 'Set on this machine itself (Machine Settings ▸ Upcoming Product Mapping)'
                        : 'Inherited from the preset upcoming of the mapping this machine is binded to today'"
                    >
                      {{ vend.source === 'own' ? 'Own' : 'Inherited' }}
                    </span>
                    <!--
                      Start date comes from the CURRENT mapping's rollout schedule
                      (upcoming_product_mapping_start_date) — there is no per-vend
                      one, and that date gates the changeover for Own and Inherited
                      alike (ProductMapping::isUpcomingMappingEffective).
                    -->
                    <div
                      class="text-indigo-600 pt-0.5"
                      v-if="vend.start_date"
                      v-tooltip="'Scheduled changeover date, taken from the mapping this machine is binded to today'"
                    >
                      from {{ vend.start_date }}
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
            <p class="pt-3 text-xs text-gray-500">
              {{ upcomingVends.length }} machine(s).
            </p>
          </div>
        </div>
      </template>
    </Modal>
  </Teleport>

  </BreezeAuthenticatedLayout>
</template>

<script setup>
import AttachmentOverview from '@/Components/AttachmentOverview.vue';
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import Form from '@/Pages/ProductMapping/Form.vue';
import Modal from '@/Components/Modal.vue';
import VendForm from '@/Pages/ProductMapping/VendForm.vue';
import axios from 'axios';
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
// "N Machine(s) at upcoming stage" popup — rows are fetched per mapping on
// click (see onUpcomingVendsClicked), never shipped with the page.
const showUpcomingVendsModal = ref(false)
const upcomingVendsMapping = ref(null)
const upcomingVends = ref([])
const upcomingVendsLoading = ref(false)
const upcomingVendsError = ref('')
// Guards against a slow response for mapping A landing after the user has
// already opened mapping B — only the newest request may write the list.
let upcomingVendsRequestId = 0
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
const avgMthlySalesTooltip = '"Avg:" — average monthly sales of the SITE this machine stands at: lifetime sales / the number of calendar months the site has been operating (from its begin date, floored at the app reporting floor, counting both the begin month and this month). A lifetime average, so it moves slowly. Same number the Machine page shows for this machine. Two machines at the same site show the same figure — do not add the lines up.   "L30d:" — that same site\'s sales over the last 30 days (rolling, includes today). Moved here from the Binded Vending Machines column so it sits under the average it is measured against. It is coloured to match its arrow, so the figure and the arrow always say the same thing.   ▲ ▼ • = the L30d line vs the Avg line above it: ▲ green = the last 30 days are running ABOVE this site\'s average month, ▼ red = below, • grey = within ±1% of it. No arrow at all means there is nothing to compare. Hover the L30d figure or the arrow for the exact gap.'

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
// L30d Sales (site) — the bottom line of each cell
// ---------------------------------------------------------------------------
// Rolling last-30-days sales for the SITE, straight out of
// customers.totals_json->thirty_days_amount. Identical source, formula, currency
// convention and >1,000 green/red threshold as the chip that used to live in the
// Binded Vending Machines column, and as Vend/CustomerIndex.vue's Last30d figure.
//
// CUSTOMER totals, not the vend's own vend_transaction_totals_json: the vend
// total is keyed on vend_id and follows the machine, so a machine moved to a new
// site would keep showing sales earned under its previous customer. The customer
// total is keyed on customer_id, i.e. site-based — matching the average above it.
function hasL30dSales(vend) {
  const totals = (vend && vend.customer) ? vend.customer.vendTransactionTotalsJson : null

  return !!totals && ('thirty_days_amount' in totals)
}

// Major units, same divide-by-exponent convention as everything else here.
function l30dSales(vend) {
  if (!hasL30dSales(vend)) {
    return 0
  }

  const exponent = operatorCountry.currency_exponent ?? 2

  return (vend.customer.vendTransactionTotalsJson['thirty_days_amount'] || 0) / Math.pow(10, exponent)
}

// ---------------------------------------------------------------------------
// Trend arrow — L30d vs this site's own average month
// ---------------------------------------------------------------------------
// REWRITTEN 2026-08-03. It used to compare last complete month vs the month
// before (last_mth_amount vs last_2_mth_amount). That number was correct — it
// reconciled to the cent with customer_period_summaries — but it answered a
// question nobody was asking of this column: ops reads the arrow as describing
// the figures printed beside it, and a site could be well ABOVE its average and
// still carry a red ▼ because one closed month dipped against another. Rather
// than relabel the arrow, the comparison was changed to the one ops actually
// wanted, and L30d was moved into this cell so both sides of it are visible.
//
// NOW: L30d (rolling last 30 days, site) vs Avg Mthly Sales (lifetime average,
// same site). "Is this site currently running above or below its own normal
// month?" Both sides are already in this page's payload — no extra query.
//
// Not a like-for-like calendar comparison and it is not meant to be: 30 rolling
// days is roughly, not exactly, one month, and the average is a lifetime figure
// that a long-running site has dragged down or up over years. The ±1% deadband
// keeps that approximation from painting the column at random.
//
// Returns null when there is nothing honest to compare — no L30d key, no
// lifetime figure, or a non-positive average (a site with no lifetime sales has
// no "normal" to be above). Null renders no arrow at all, deliberately distinct
// from the flat dot: "we don't know" must not look like "no change".
//
// MEMOISED per vend object (see avgMthlySalesTrend below) because the template
// needs the result three times per row — v-if, direction and tooltip — and this
// builds a string with two toLocaleString calls. On an "All" page that is
// thousands of rows; computing it once per machine keeps it off the render path.
function computeAvgMthlySalesTrend(vend) {
  if (!hasL30dSales(vend) || !hasAvgMthlySales(vend)) {
    return null
  }

  const l30d = Number(l30dSales(vend))
  const avg = Number(avgMthlySales(vend))
  if (!isFinite(l30d) || !isFinite(avg)) {
    return null
  }
  // No average means no baseline to be above or below — absence, not a trend.
  if (avg <= 0) {
    return null
  }

  const exponent = operatorCountry.currency_exponent ?? 2
  const fmt = (major) => operatorCountry.currency_symbol + major.toLocaleString(undefined, {
    minimumFractionDigits: operatorCountry.is_currency_exponent_hidden ? 0 : exponent,
    maximumFractionDigits: operatorCountry.is_currency_exponent_hidden ? 0 : exponent,
  })

  const pct = ((l30d - avg) / avg) * 100

  // ±1% deadband: without it, ordinary noise paints half the column red and ops
  // stops trusting the arrows.
  let dir = 'flat'
  if (pct >= 1) {
    dir = 'up'
  } else if (pct <= -1) {
    dir = 'down'
  }

  // SHORT on purpose: the column's full explanation is on the header tooltip, so
  // hovering a figure only has to answer "which way, and by how much?".
  const headline = dir === 'flat'
    ? 'In line with its average month'
    : (pct >= 0 ? '+' : '') + pct.toFixed(1) + '% vs its average month'

  return {
    dir,
    pct,
    tooltip: headline + ' (avg ' + fmt(avg) + ' → L30d ' + fmt(l30d) + ')',
  }
}

// Tooltip for the average figure (top line). Short by design — the column
// explanation lives on the header so hovering a number can't blanket the screen.
function avgMthlySalesFigureTooltip(vend) {
  const trend = avgMthlySalesTrend(vend)

  return trend
    ? 'Avg Mthly Sales (site, lifetime average). L30d is ' + trend.tooltip
    : 'Avg Mthly Sales (site, lifetime average). No L30d figure to compare against yet.'
}

// Colour of the L30d figure — TAKEN FROM THE ARROW, not from any threshold.
//
// It used to use the absolute ">1,000 = green" site-size rule it inherited from
// the Binded Vending Machines column. Once the arrow moved in beside it, that
// produced a red S$784.30 next to a green ▲ — a small site running above its own
// average. Both signals were correct and the pair was unreadable. One cell, one
// meaning: green/red here says the same thing the arrow says.
//
// Exactly the arrow's own classes (text-green-600 / text-red-600) so the figure
// and the icon are the same colour, not two shades of nearly-the-same.
//
// Inactive/disposed machines stay grey — that greying applies to the whole row
// and outranks the trend.
// Flat (within ±1%) and "no comparison possible" both fall through to the same
// neutral as the Avg figure above: nothing to shout about either way.
function l30dSalesColourClass(vend) {
  if (!vend || !(vend.is_active || vend.is_testing)) {
    return 'text-gray-400'
  }

  const trend = avgMthlySalesTrend(vend)
  if (trend && trend.dir === 'up') {
    return 'text-green-600'
  }
  if (trend && trend.dir === 'down') {
    return 'text-red-600'
  }

  return 'text-gray-800'
}

// Tooltip for the L30d figure (bottom line): what it is, then how it compares.
function l30dSalesTooltip(vend) {
  const trend = avgMthlySalesTrend(vend)

  return trend
    ? 'L30d Sales (site, rolling 30 days) — ' + trend.tooltip
    : 'L30d Sales (site, rolling 30 days). No average to compare against yet.'
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

// Tooltip for the "N Machine(s) at upcoming stage" line.
//
// Returned as a floating-vue options object with html:true rather than a plain
// string, for one reason: floating-vue ships NO max-width on
// `.v-popper--theme-tooltip .v-popper__inner`, so a sentence this long renders
// as a single line stretched across the whole viewport. The width has to be set
// on the content itself.
//
// Two details that look like they could be simplified but can't:
//  • the width is an INLINE style, not a Tailwind class. floating-vue's own
//    `.v-popper__inner > div { max-width: inherit }` (specificity 0,1,1) beats a
//    utility class (0,1,0) and would reset it to none. An inline style outranks
//    both, and it keeps this independent of Tailwind's class extraction.
//  • <br> rather than "\n": nothing sets white-space on the tooltip, so a
//    newline would just collapse back into a space.
//
// Content is an app-authored constant — nothing user-supplied ever reaches
// html:true here.
const upcomingStageTooltipBody =
  'Machines binded elsewhere but queued to switch to this mapping.<br>'
  + 'Either the machine\'s own Upcoming Product Mapping is this mapping, or it inherits this mapping'
  + ' as the preset upcoming of the mapping it is binded to today.<br>'
  + 'I.e. not yet updated to this mapping.'

function upcomingStageTooltip(count) {
  const body = (count || 0) > 0
    ? upcomingStageTooltipBody + '<br><em>Click to list them.</em>'
    : upcomingStageTooltipBody

  return {
    content: '<div style="max-width:20rem;text-align:left;line-height:1.35;">' + body + '</div>',
    html: true,
  }
}

// Opens the "machines at upcoming stage" popup for one mapping and loads its
// rows. No-op at zero — the text is not clickable there.
//
// vendStatus is read from the URL, NOT from filters.value: the count in the link
// was rendered by the server from the query string, while filters.value can hold
// a dropdown change the user has not pressed Search on yet. Reading the URL is
// what keeps the list length equal to the number that was clicked.
function onUpcomingVendsClicked(mapping) {
  if (!mapping || !(mapping.upcoming_vends_count || 0)) return

  upcomingVendsMapping.value = { id: mapping.id, name: mapping.name }
  upcomingVends.value = []
  upcomingVendsError.value = ''
  upcomingVendsLoading.value = true
  showUpcomingVendsModal.value = true

  const requestId = ++upcomingVendsRequestId
  const vendStatus = new URLSearchParams(window.location.search).get('vendStatus') || ''

  axios
    .get('/product-mappings/' + mapping.id + '/upcoming-vends', { params: { vendStatus } })
    .then((response) => {
      if (requestId !== upcomingVendsRequestId) return
      upcomingVends.value = response.data?.vends ?? []
      if (response.data?.productMapping) {
        upcomingVendsMapping.value = response.data.productMapping
      }
    })
    .catch(() => {
      if (requestId !== upcomingVendsRequestId) return
      upcomingVendsError.value = 'Could not load the machines at upcoming stage. Please try again.'
    })
    .finally(() => {
      if (requestId !== upcomingVendsRequestId) return
      upcomingVendsLoading.value = false
    })
}

function onUpcomingVendsModalClose() {
  showUpcomingVendsModal.value = false
  // Invalidate any in-flight request so a late response can't repopulate a
  // closed dialog.
  upcomingVendsRequestId++
  upcomingVendsLoading.value = false
}

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

// ---------------------------------------------------------------------------
// In-cell machine list sort (2026-08-13)
// ---------------------------------------------------------------------------
// Sorts the binded-machine list INSIDE each mapping row — and, in lockstep, the
// per-machine Avg Mthly Sales column, because BOTH cells render this same
// sorted array and alignAvgMthlySalesRows() pairs their rows by index. This is
// separate from the page-level row sort (filters.sortKey / sortTable below):
// it never round-trips, since a mapping's machines always ship fully loaded
// with the row. One shared state sorts every row's list the same way.
// Default: binded date, newest first (ops request).
const vendListSort = ref({ key: 'binded_at', desc: true })

function sortVendList(key) {
  if (vendListSort.value.key === key) {
    vendListSort.value.desc = !vendListSort.value.desc
  } else {
    // Each key starts at its natural direction: dates newest-first, codes ascending.
    vendListSort.value = { key, desc: key === 'binded_at' }
  }
}

function sortedVends(productMapping) {
  const { key, desc } = vendListSort.value
  // binded_at arrives as a datetime string in one fixed format, so string
  // comparison IS chronological comparison. Machines with no binded date sink
  // to the bottom in either direction; ties fall through to code order so the
  // list is deterministic.
  const byCode = (a, b) => String(a.code).localeCompare(String(b.code), undefined, { numeric: true })
  return [...productMapping.vends].sort((a, b) => {
    if (key === 'binded_at') {
      if (!a.binded_at && !b.binded_at) return byCode(a, b)
      if (!a.binded_at) return 1
      if (!b.binded_at) return -1
      if (a.binded_at !== b.binded_at) {
        const cmp = a.binded_at < b.binded_at ? -1 : 1
        return desc ? -cmp : cmp
      }
      return byCode(a, b)
    }
    const cmp = byCode(a, b)
    return desc ? -cmp : cmp
  })
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