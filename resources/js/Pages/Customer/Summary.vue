<template>
  <Head title="Sites Summary" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex flex-col">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Site Summary
        </h2>
        <p class="text-sm text-black leading-tight mt-1">
          (Data fr 230101).<br />
          First calculation based on 2605 terms
        </p>
      </div>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3">
        <!-- Filters -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
          <SearchInput placeholderStr="ID" v-model="filters.ref_id">Site ID</SearchInput>
          <SearchInput placeholderStr="ID" v-model="filters.vend_code">Machine ID</SearchInput>
          <SearchInput placeholderStr="Site" v-model="filters.customer">Site</SearchInput>

          <div>
            <label class="block text-sm font-medium text-gray-700">Site Status</label>
            <MultiSelect
              v-model="filters.status"
              :options="statusOptions"
              trackBy="id" valueProp="id" label="value"
              placeholder="Select" open-direction="bottom" class="mt-1"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Tags</label>
            <MultiSelect
              v-model="filters.tags"
              :options="tagOptions"
              trackBy="id" valueProp="id" label="name" mode="tags"
              placeholder="Select" open-direction="bottom" class="mt-1"
            />
          </div>

          <div v-if="permissions.includes('admin-access customers') && cmsEndpoint">
            <label class="block text-sm font-medium text-gray-700">Is From CMS</label>
            <MultiSelect
              v-model="filters.is_cms"
              :options="booleanOptions"
              trackBy="id" valueProp="id" label="value"
              placeholder="Select" open-direction="bottom" class="mt-1"
            />
          </div>

          <div v-if="permissions.includes('admin-access customers')">
            <label class="block text-sm font-medium text-gray-700">Operator</label>
            <MultiSelect
              v-model="filters.operators"
              :options="operatorOptions"
              trackBy="id" valueProp="id" label="full_name" mode="tags"
              placeholder="Select" open-direction="bottom" class="mt-1"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Machine Prefix</label>
            <MultiSelect
              v-model="filters.vendPrefixes"
              :options="vendPrefixOptions"
              trackBy="id" valueProp="id" label="value" mode="tags"
              placeholder="Select" open-direction="bottom" class="mt-1"
            />
          </div>

          <div v-if="permissions.includes('admin-access customers')">
            <label class="block text-sm font-medium text-gray-700">Location Type</label>
            <MultiSelect
              v-model="filters.location_types"
              :options="locationTypeOptions"
              trackBy="id" valueProp="id" label="value" mode="tags"
              placeholder="Select" open-direction="bottom" class="mt-1"
            />
          </div>

          <!-- NEW: Period Report -->
          <div>
            <label class="block text-sm font-medium text-gray-700">
              Period Report
            </label>
            <MultiSelect
              v-model="filters.period_report"
              :options="periodReportLocalOptions"
              trackBy="id" valueProp="id" label="value"
              placeholder="Select" open-direction="bottom" class="mt-1"
              :canDeselect="false"
            />
            <p class="text-[10px] text-gray-500 mt-1">
              Default: Current month, recomputed daily ~01:00 (yesterday).
            </p>
          </div>

          <!-- Placement Contract Type filter (multi-select tags) -->
          <div>
            <label class="block text-sm font-medium text-gray-700">
              Placement Contract Type
            </label>
            <MultiSelect
              v-model="filters.contract_commission_types"
              :options="contractCommissionTypeLocalOptions"
              trackBy="id" valueProp="id" label="value" mode="tags"
              placeholder="Select" open-direction="bottom" class="mt-1"
            />
          </div>

          <!--
            Contract Attachment? filter — boolean dropdown (Yes / No).
            Shows customers that did (Yes) / did not (No) have any contract
            attachment uploaded within the selected Period Report window or
            later (created_at >= period start). "All" = no filter.
          -->
          <div>
            <label class="block text-sm font-medium text-gray-700">
              Contract Attachment?
            </label>
            <MultiSelect
              v-model="filters.contract_attachment"
              :options="contractAttachmentOptions"
              trackBy="id" valueProp="id" label="value"
              placeholder="Select" open-direction="bottom" class="mt-1"
            />
          </div>
          <!--
            Contract-changes filter — "Changes only" shows just the customers
            whose month was split into more than one row (same customer +
            year_month) because the contract changed mid-month. Quick way to
            spot mid-month contract changes.
          -->
          <div>
            <label class="block text-sm font-medium text-gray-700">
              Contract changes (same month)?
            </label>
            <MultiSelect
              v-model="filters.replicated_only"
              :options="replicatedOptions"
              trackBy="id" valueProp="id" label="value"
              placeholder="Select" open-direction="bottom" class="mt-1"
            />
          </div>
          <!-- Period Locked? — All / Yes (locked) / No (unlocked). -->
          <div>
            <label class="block text-sm font-medium text-gray-700">
              Period Locked?
            </label>
            <MultiSelect
              v-model="filters.period_locked"
              :options="lockedPaidOptions"
              trackBy="id" valueProp="id" label="value"
              placeholder="Select" open-direction="bottom" class="mt-1"
            />
          </div>
          <!-- Location Fee Paid? — All / Yes (paid) / No (unpaid). -->
          <div>
            <label class="block text-sm font-medium text-gray-700">
              Location Fee Paid?
            </label>
            <MultiSelect
              v-model="filters.location_fee_paid"
              :options="lockedPaidOptions"
              trackBy="id" valueProp="id" label="value"
              placeholder="Select" open-direction="bottom" class="mt-1"
            />
          </div>
          <!--
            Payment Date range — filters rows by the ACTUAL payment date
            (paid_date, recorded via the Paid popup). Either side can be
            left empty for an open-ended range. Rows without a payment
            date are excluded whenever a bound is set.
          -->
          <div>
            <label class="block text-sm font-medium text-gray-700">
              Payment Date (From)
            </label>
            <input
              type="date"
              v-model="filters.paid_date_from"
              class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-emerald-500 focus:ring-emerald-500"
            />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">
              Payment Date (To)
            </label>
            <input
              type="date"
              v-model="filters.paid_date_to"
              class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-emerald-500 focus:ring-emerald-500"
            />
          </div>
        </div>

        <div class="flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5">
          <div class="mt-3">
            <div class="flex space-x-1">
              <Button
                class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600"
                @click="onSearchFilterUpdated"
              >
                <MagnifyingGlassIcon class="h-4 w-4" aria-hidden="true" />
                <span>Search</span>
              </Button>
              <Button
                class="inline-flex space-x-1 items-center rounded-md border border-sky bg-sky-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-sky-400"
                @click.prevent="onMapAllMarkerClicked"
                v-if="hasAnyAddressWithCoords"
              >
                <MapPinIcon class="h-4 w-4" aria-hidden="true" />
                <span>Show Map Markers</span>
              </Button>
              <Button
                class="inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400"
                @click="resetFilters"
              >
                <BackspaceIcon class="h-4 w-4" aria-hidden="true" />
                <span>Reset</span>
              </Button>
              <Button type="button" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-400 hover:bg-gray-100"
                @click.prevent="onExportExcelClicked()">
                <div class="flex space-x-1">
                  <div>
                    <ArrowDownTrayIcon v-if="!loading" class="h-4 w-4" aria-hidden="true"/>
                    <svg v-if="loading" aria-hidden="true" class="mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-green-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                      <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                      <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                    </svg>
                  </div>
                  <span>Export Excel</span>
                </div>
              </Button>
              <!--
                Bulk API Invoice toggle — flips the Action column into
                checkbox mode and reveals the "Create API Invoice(s)"
                submit button. Mirrors OpsJob's bulk-mode pattern.
              -->
              <!-- <Button
                v-if="cmsEndpoint"
                type="button"
                :class="[
                  'rounded-md px-3 py-2 text-sm font-semibold shadow-sm ring-1 ring-inset',
                  bulkMode
                    ? 'bg-orange-100 text-orange-800 ring-orange-300 hover:bg-orange-200'
                    : 'bg-white text-gray-900 ring-gray-400 hover:bg-gray-100',
                ]"
                @click.prevent="bulkMode = !bulkMode; selectedRowKeys = []"
              >
                <span class="flex items-center space-x-1">
                  <ClipboardDocumentCheckIcon class="h-4 w-4" aria-hidden="true" />
                  <span>{{ bulkMode ? 'Cancel Bulk' : 'Bulk API Invoice' }}</span>
                </span>
              </Button>
              <Button
                v-if="bulkMode"
                type="button"
                class="inline-flex items-center space-x-1 rounded-md border border-yellow bg-yellow-500 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-yellow-600 disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="!selectedRowKeys.length || bulkSubmitting"
                @click.prevent="onBulkCreateApiInvoicesClicked()"
              >
                <ClipboardDocumentCheckIcon class="h-4 w-4" aria-hidden="true" />
                <span>Create API Invoice(s) ({{ selectedRowKeys.length }})</span>
              </Button> -->
            </div>
            <p class="text-xs text-gray-600 mt-2">
              Period range: <span class="font-medium">{{ rangeStart }}</span> &nbsp;→&nbsp;
              <span class="font-medium">{{ rangeEnd }}</span>
            </p>
          </div>
          <div class="flex flex-col space-y-2">
            <p class="text-sm text-gray-700 leading-5 flex space-x-1">
              <span>Showing</span>
              <span class="font-medium">{{ summaries.meta?.from ?? 0 }}</span>
              <span>to</span>
              <span class="font-medium">{{ summaries.meta?.to ?? 0 }}</span>
              <span>of</span>
              <span class="font-medium">{{ summaries.meta?.total ?? 0 }}</span>
              <span>results</span>
            </p>
            <MultiSelect
              v-model="filters.numberPerPage"
              :options="numberPerPageOptions"
              trackBy="id" valueProp="id" label="value"
              placeholder="Select" open-direction="bottom" class="mt-1"
              @selected="onSearchFilterUpdated"
            />
          </div>
        </div>

        <!--
          Aggregate total boxes — sums Sales / Gross Earning / Location Fees /
          Vend Earnings across the FULL filtered set (every row matching the
          current filters, not just the 100 visible on this page). Styling
          mirrors Vend/CustomerIndex.vue's totals dl: the gray cards sit
          INSIDE the white filter card so they pop against the white
          background. Location Fees flips colour with sign so subsidy
          income (negative cents) is visually distinct from expense. Vend
          Earnings flips red when the result is net-negative.
        -->
        <dl class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
          <div class="overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow md:block">
            <dt class="truncate text-sm font-medium text-gray-500">Total Sales</dt>
            <dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
              {{ formatMoney(totals.sales_cents) }}
            </dd>
          </div>
          <div class="overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow md:block">
            <dt class="truncate text-sm font-medium text-gray-500">Total Gross Earning</dt>
            <dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
              {{ formatMoney(totals.gross_earning_cents) }}
            </dd>
          </div>
          <div class="overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow md:block">
            <dt class="truncate text-sm font-medium text-gray-500">Total Location Fees</dt>
            <dd
              class="mt-1 text-2xl font-semibold tracking-normal"
              :class="(totals.location_fees_cents || 0) < 0 ? 'text-emerald-700' : 'text-gray-900'"
            >
              {{ formatMoneySigned(totals.location_fees_cents) }}
            </dd>
          </div>
          <div class="overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow md:block">
            <dt class="truncate text-sm font-medium text-gray-500">Total Vend Earnings</dt>
            <dd
              class="mt-1 text-2xl font-semibold tracking-normal"
              :class="(totals.location_earning_cents || 0) >= 0 ? 'text-gray-900' : 'text-red-700'"
            >
              {{ formatMoney(totals.location_earning_cents) }}
            </dd>
          </div>
          <!--
            Distinct-customer counts scoped to the same filtered customer
            set as the money totals above.
          -->
          <div class="overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow md:block">
            <dt class="truncate text-sm font-medium text-gray-500"># without Contract Attachment</dt>
            <dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
              {{ totals.no_contract_attachment_count || 0 }}
            </dd>
          </div>
          <div class="overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow md:block">
            <dt class="truncate text-sm font-medium text-gray-500"># To Be Expired in 30ds</dt>
            <dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
              {{ totals.expiring_in_30d_count || 0 }}
            </dd>
            <dd class="mt-0.5 text-xs italic text-gray-400">excludes auto-renewal contracts</dd>
          </div>
        </dl>
      </div>

      <!--
        Batch action bar — appears only while at least one row on the
        CURRENT page is selected. Sticky so it stays reachable while the
        user scrolls the table. Each button shows how many of the selected
        rows are eligible for THAT action (a selection can mix lock-eligible
        and paid-eligible rows); 0-eligible buttons are disabled.
      -->
      <div
        v-if="canLock && selectedRows.length > 0"
        class="sticky top-0 z-40 mt-4 flex flex-wrap items-center gap-2 rounded-md border border-emerald-300 bg-emerald-50 px-4 py-2 shadow-md"
      >
        <span class="text-sm font-semibold text-emerald-900">
          {{ selectedRows.length }} row(s) selected on this page
        </span>
        <Button
          type="button"
          class="inline-flex items-center space-x-1 rounded-md px-3 py-2 text-xs font-semibold bg-amber-100 hover:bg-amber-200 text-amber-800 ring-1 ring-inset ring-amber-300 disabled:opacity-40 disabled:cursor-not-allowed"
          :disabled="!selectedLockRows.length || batchSubmitting"
          v-tooltip="selectedLockRows.length ? '' : 'No selected rows are eligible to Lock'"
          @click="showBatchLockModal = true"
        >
          <LockClosedIcon class="h-3.5 w-3.5" aria-hidden="true" />
          <span>Lock ({{ selectedLockRows.length }})</span>
        </Button>
        <Button
          type="button"
          class="inline-flex items-center space-x-1 rounded-md px-3 py-2 text-xs font-semibold bg-emerald-100 hover:bg-emerald-200 text-emerald-800 ring-1 ring-inset ring-emerald-300 disabled:opacity-40 disabled:cursor-not-allowed"
          :disabled="!selectedPaidRows.length || batchSubmitting"
          v-tooltip="selectedPaidRows.length ? '' : 'No selected rows are eligible to mark Paid (must be locked + unpaid, period 2605 onward)'"
          @click="onBatchPaidClicked"
        >
          <CheckCircleIcon class="h-3.5 w-3.5" aria-hidden="true" />
          <span>Mark Paid ({{ selectedPaidRows.length }})</span>
        </Button>
        <Button
          type="button"
          class="ml-auto rounded-md px-3 py-2 text-xs font-semibold bg-white hover:bg-gray-100 text-gray-700 ring-1 ring-inset ring-gray-300"
          @click="clearBatchSelection"
        >
          Clear selection
        </Button>
      </div>

      <!-- Table -->
      <div class="mt-6 flex flex-col">
        <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
          <!--
            max-h + overflow-scroll mirror CustomerIndex.vue — a fixed-height
            scroll container is what lets TableHead's built-in
            `sticky top-0 z-10` actually pin the header while the body
            scrolls (without max-h, the page itself scrolls and sticky has
            no containing block to stick to).
          -->
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll max-h-[900px] md:max-h-[1500px]">
            <table class="min-w-full border-separate" style="border-spacing: 0">
              <thead class="bg-gray-100">
                <tr class="divide-x divide-gray-200">
                  <!--
                    Frozen first 2 columns — sticky horizontally so they stay
                    visible while the user scrolls right. Cumulative left
                    offsets must match the column widths so cells line up:
                      #        → w-[50px],  left 0
                      Site → w-[170px], left 50
                    Each frozen header gets opaque bg-gray-100 (via TableHead's
                    frozen path) so scrolling content doesn't show through.
                    Each frozen TableData below mirrors the same width + left
                    offset, plus a row-alternating bg-* so the customer-group
                    stripe stays visually continuous across the freeze line.
                  -->
                  <!--
                    Batch-select column (admins only — mirrors canLock).
                    Master checkbox selects ONLY the selectable rows on the
                    CURRENT page (never the whole filtered set across pages);
                    indeterminate when some-but-not-all are selected.
                    Row eligibility self-evaluates per action: unlocked past
                    months are selectable for batch Lock, locked+unpaid
                    periods (2605+) for batch Paid.
                    When hidden, the # / Site freeze offsets fall back to
                    their original 0 / 50px (see :frozenLeft bindings below).
                  -->
                  <TableHead v-if="canLock" :frozen="true" frozenLeft="0px" inputClass="w-[40px] min-w-[40px] text-center">
                    <input
                      type="checkbox"
                      class="h-4 w-4 rounded border-gray-400 text-emerald-600 focus:ring-emerald-500 cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed"
                      :checked="allPageSelected"
                      :indeterminate="somePageSelected && !allPageSelected"
                      :disabled="!selectableRows.length"
                      v-tooltip="'Select / deselect all selectable rows on this page'"
                      @change="toggleSelectAllPage"
                    />
                  </TableHead>
                  <TableHead :frozen="true" :frozenLeft="canLock ? '40px' : '0px'" inputClass="w-[50px] min-w-[50px]">#</TableHead>
                  <TableHead :frozen="true" :frozenLeft="canLock ? '90px' : '50px'" inputClass="w-[170px] min-w-[170px] border-r-2 border-gray-500">
                    <div class="flex flex-col space-y-1">
                      <SingleSortItem modelName="site_status" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('site_status')">
                        Site Status
                      </SingleSortItem>
                      <SingleSortItem modelName="customer_name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('customer_name')">
                        Site Name
                      </SingleSortItem>
                      <SingleSortItem modelName="selling_price_type" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('selling_price_type')">
                        Ref Price
                      </SingleSortItem>
                      <SingleSortItem modelName="begin_date" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('begin_date')">
                        Begin Date
                      </SingleSortItem>
                      <SingleSortItem modelName="contract_attachment" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('contract_attachment')">
                        Contract Attachment
                      </SingleSortItem>
                    </div>
                  </TableHead>
                  <TableHead inputClass="w-[220px] max-w-[220px]">
                    <div class="flex flex-col space-y-1">
                      <span>Site Address</span>
                      <span>Billing Company</span>
                      <span>Billing Contact Person</span>
                    </div>
                  </TableHead>
                  <TableHead>
                    <SingleSortItem modelName="machine_id" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('machine_id')">
                      Machine ID
                    </SingleSortItem>
                  </TableHead>
                  <TableHead>
                    <SingleSortItem modelName="machine_prefix" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('machine_prefix')">
                      Machine Prefix
                    </SingleSortItem>
                  </TableHead>
                  <TableHead>
                    <div class="flex flex-col space-y-1">
                      <span>Period Report</span>
                      <SingleSortItem modelName="year_month" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('year_month')">
                        YYMM
                      </SingleSortItem>
                    </div>
                  </TableHead>
                  <TableHead>
                    <div class="flex flex-col space-y-1">
                      <span>Period</span>
                      <SingleSortItem modelName="period_start" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('period_start')">
                        Start Date
                      </SingleSortItem>
                      <SingleSortItem modelName="period_end" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('period_end')">
                        End Date
                      </SingleSortItem>
                    </div>
                  </TableHead>
                  <TableHead>
                    <div class="flex flex-col space-y-1">
                      <SingleSortItem modelName="sales_cents" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('sales_cents')">
                        Sales ($)
                      </SingleSortItem>
                      <!--
                        Two sub-labels mirror the cell layout:
                          (w/ GST)  - small/muted, matches the smaller
                                      secondary line in each row (the raw
                                      sales_cents figure)
                          (excl GST) - normal weight, matches the larger
                                       primary line in each row (the
                                       de-grossed figure used by PS math)
                      -->
                      <span class="text-[11px] text-gray-500">(w/ GST)</span>
                      <span>(excl GST)</span>
                    </div>
                  </TableHead>
                  <TableHead>
                    <div class="flex flex-col space-y-1">
                      <SingleSortItem modelName="gross_earning_cents" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('gross_earning_cents')">
                        Gross Earing
                      </SingleSortItem>
                      <span >(excl GST)</span>
                      <SingleSortItem modelName="gross_earning_rate" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('gross_earning_rate')">
                        Rate
                      </SingleSortItem>
                    </div>
                  </TableHead>
                  <TableHead>
                    <SingleSortItem modelName="job_count" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('job_count')">
                      # of Job
                    </SingleSortItem>
                  </TableHead>
                  <TableHead>
                    <div class="flex flex-col space-y-4">
                      <SingleSortItem modelName="contract_commission_type" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('contract_commission_type')">
                        Placement Contract Type
                      </SingleSortItem>
                      <SingleSortItem modelName="contract_commission_value" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('contract_commission_value')">
                        Location Fees Rate
                      </SingleSortItem>
                    </div>
                  </TableHead>
                  <TableHead>
                    <div class="flex flex-col space-y-4">
                      <SingleSortItem modelName="location_fees_cents" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('location_fees_cents')">
                        Location Fees
                      </SingleSortItem>
                      <SingleSortItem modelName="external_subsidize" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('external_subsidize')">
                        External Subsidize
                      </SingleSortItem>
                      <SingleSortItem modelName="net_loc_fee" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('net_loc_fee')">
                        Net Loc Fee
                      </SingleSortItem>
                    </div>
                  </TableHead>
                  <TableHead>
                    <div class="flex flex-col space-y-1">
                      <SingleSortItem modelName="location_earning_cents" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('location_earning_cents')">
                        Vend Earning
                      </SingleSortItem>
                      <SingleSortItem modelName="location_earning_rate" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('location_earning_rate')">
                        Rate
                      </SingleSortItem>
                    </div>
                  </TableHead>
                  <TableHead>
                    <SingleSortItem modelName="accumulate_vending_earning" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('accumulate_vending_earning')">
                      Accumulate Vend Earning
                    </SingleSortItem>
                  </TableHead>
                  <TableHead>
                    <div class="flex flex-col space-y-4">
                      <span>Loc Grading</span>
                      <SingleSortItem modelName="location_type" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('location_type')">
                        Location Type
                      </SingleSortItem>
                    </div>
                  </TableHead>
                  <!--
                    Contract terms — End Date / Auto Renewal / Notice Period.
                    All three come from the customer record (same fields as
                    Customer/Edit.vue's form.contract_until /
                    contract_auto_renewal / contract_notice_period). Placed
                    after Location Grading per user preference.
                  -->
                  <TableHead>
                    <div class="flex flex-col space-y-1">
                      <SingleSortItem modelName="contract_until" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('contract_until')">
                        Contract End Date
                      </SingleSortItem>
                      <span>Auto Renewal?</span>
                      <span>Notice Period</span>
                    </div>
                  </TableHead>
                  <TableHead inputClass="min-w-[150px] max-w-[150px]">
                    <div class="flex flex-col space-y-1">
                      <span>Site Tag</span>
                      <span>Site Note</span>
                      <!-- Note Last Updated — sortable on customers.notes_updated_at.
                           Drives the page's default sort (latest → oldest). -->
                      <SingleSortItem modelName="notes_updated_at" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('notes_updated_at')">
                        Note Last Updated
                      </SingleSortItem>
                    </div>
                  </TableHead>
                  <!-- Period Verify & Lock — action-triggered freeze of this
                       month's figures. Sits after Site Tag/Note; Action
                       remains the rightmost column. -->
                  <TableHead>Period Verify & Lock</TableHead>
                  <TableHead>
                    <template v-if="bulkMode">
                      <label class="inline-flex items-center space-x-1 cursor-pointer">
                        <input
                          type="checkbox"
                          class="h-4 w-4 rounded border-gray-300 text-yellow-600 cursor-pointer accent-yellow-500"
                          :checked="isAllInvoiceableSelected()"
                          @change="toggleSelectAllInvoiceable()"
                        />
                        <span>Select</span>
                      </label>
                    </template>
                    <template v-else>Action</template>
                  </TableHead>
                </tr>
              </thead>
              <tbody class="bg-white">
                <tr
                  v-for="(row, rowIndex) in summaries.data"
                  :key="row.id"
                  :class="[
                    'divide-x divide-y-2 divide-gray-300',
                    // Stripe by CUSTOMER GROUP, not by row — so a customer's
                    // 12 monthly rows share one background and the eye reads
                    // each cluster as a unit. customerGroupIndex(rowIndex)
                    // increments only when the previous row had a different
                    // customer_id; modulo 2 picks the alternating colour.
                    customerGroupIndex(rowIndex) % 2 === 0 ? 'bg-white' : 'bg-gray-100',
                  ]"
                >
                  <!--
                    First 2 cells are frozen (sticky horizontally) — widths
                    and left offsets must match the matching <TableHead>
                    above. Each frozen cell carries its own row-alternating
                    bg-* (bg-white / bg-gray-100) so the customer-group
                    stripe stays continuous after content scrolls under
                    the freeze line.
                  -->
                  <!-- Batch-select checkbox — disabled (with tooltip reason)
                       on rows that aren't eligible for either batch action. -->
                  <TableData v-if="canLock" :currentIndex="rowIndex" :totalLength="summaries.data.length"
                    :frozen="true" frozenLeft="0px"
                    :inputClass="`text-center w-[40px] min-w-[40px] ${customerGroupIndex(rowIndex) % 2 === 0 ? 'bg-white' : 'bg-gray-100'}`">
                    <span v-tooltip="batchCheckboxTooltip(row)">
                      <input
                        type="checkbox"
                        class="h-4 w-4 rounded border-gray-400 text-emerald-600 focus:ring-emerald-500 cursor-pointer disabled:opacity-30 disabled:cursor-not-allowed"
                        :checked="batchSelected.has(row.id)"
                        :disabled="!isBatchSelectable(row)"
                        @change="toggleRowSelected(row)"
                      />
                    </span>
                  </TableData>

                  <!-- # -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length"
                    :frozen="true" :frozenLeft="canLock ? '40px' : '0px'"
                    :inputClass="`text-center w-[50px] min-w-[50px] ${customerGroupIndex(rowIndex) % 2 === 0 ? 'bg-white' : 'bg-gray-100'}`">
                    {{ (summaries.meta?.from ?? 1) + rowIndex }}
                  </TableData>

                  <!-- Site / Ref Price / Begin Date / Contract Attachment
                       Also the LAST frozen column → carries a bold right
                       border (border-r-2 border-gray-500) to mark the X
                       freeze line for the user. -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length"
                    :frozen="true" :frozenLeft="canLock ? '90px' : '50px'"
                    :inputClass="`text-left w-[170px] min-w-[170px] border-r-2 border-gray-500 ${customerGroupIndex(rowIndex) % 2 === 0 ? 'bg-white' : 'bg-gray-100'}`">
                    <div class="flex flex-col space-y-0.5" v-if="row.customer">
                      <a target="_blank" :href="'/customers/' + row.customer.id + '/edit'"
                        :class="[row.customer.person_id ? 'text-blue-700' : 'text-purple-700']"
                      >
                        <span class="font-medium">{{ refIdFor(row.customer) }}</span>
                        <br />
                        {{ row.customer.name }}
                      </a>
                      <div class="flex flex-wrap items-center gap-1 mt-0.5">
                        <!--
                          Site Status badge — customers.status_id resolved to
                          its label server-side (status_name). Colors follow
                          the Customer Index convention: Active=green,
                          Inactive=red, others (Pending/New/Potential)=amber.
                        -->
                        <span
                          v-if="row.customer.status_name"
                          class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-medium border w-fit h-fit"
                          :class="siteStatusBadgeClass(row.customer.status_id)"
                        >
                          {{ row.customer.status_name }}
                        </span>
                        <span
                          v-if="row.customer.selling_price_type"
                          class="inline-flex rounded px-0.5 py-0.5 text-[10px] border w-fit h-fit bg-indigo-100 text-indigo-800 border-indigo-300"
                        >
                          RP{{ row.customer.selling_price_type }}
                        </span>
                        <!--
                          Delivery platform badges (e.g. green "Grab" pill)
                          — surfaced from any vend bound to this customer
                          that has an active delivery_product_mapping_vends
                          row. Deduplicated server-side in
                          CustomerPeriodSummaryResource as `delivery_platforms`.
                          Style mirrors the Grab pill on Vend/CustomerIndex.vue.
                        -->
                        <span
                          v-for="(platformName, platformIdx) in (row.customer.delivery_platforms || [])"
                          :key="'dp-' + platformIdx"
                          class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit text-gray-800 bg-green-400"
                        >
                          {{ platformName }}
                        </span>
                      </div>
                      <!--
                        Begin Date — sourced from the customer record (same
                        field as Customer/Edit.vue's form.begin_date). Label
                        lives in the column header now; the cell renders just
                        the date value. Hidden when blank.
                      -->
                      <span
                        v-if="row.customer.begin_date"
                        class="text-xs text-gray-900 mt-1"
                      >
                        {{ formatYYMMDD(row.customer.begin_date) }}
                      </span>
                      <!--
                        Contract Attachment — hyperlinks to the latest
                        uploaded contract (FILE_TYPE_CONTRACT). Label lives
                        in the column header; the cell renders a short
                        "Contract" link so the row stays compact. Hidden
                        when the customer has no contracts attached.
                      -->
                      <!--
                        Contract Attachment badge:
                          - has contract → blue, clickable, opens the latest
                            uploaded contract in a new tab.
                          - no contract → red "No Contract" badge (static).
                      -->
                      <a
                        v-if="row.customer.latest_contract && row.customer.latest_contract.full_url"
                        :href="row.customer.latest_contract.full_url"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="inline-flex items-center rounded px-1.5 py-0.5 mt-1 text-[10px] font-medium border w-fit bg-green-100 text-green-800 border-green-300 hover:bg-green-200"
                        :title="row.customer.latest_contract.name || 'Contract Attachment'"
                      >
                        Contract
                      </a>
                      <span
                        v-else
                        class="inline-flex items-center rounded px-1.5 py-0.5 mt-1 text-[10px] font-medium border w-fit bg-red-100 text-red-800 border-red-300"
                      >
                        No Contract
                      </span>
                    </div>
                  </TableData>

                  <!-- Address / Company (= CMS com_remark) / Contact Person -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-left w-[220px] max-w-[220px] break-words">
                    <div class="flex flex-col space-y-1">
                      <span v-if="row.customer?.delivery_address" class="text-[11px] leading-tight">
                        {{ row.customer.delivery_address.full_address }}
                      </span>
                      <!-- Billing Company — contact.company (Edit form's "Bill From"
                           field), falling back to the legacy CMS-mirrored
                           company_remark for customers never edited in mark1. -->
                      <span v-if="row.customer?.contact?.company || row.customer?.company_remark" class="text-[11px] leading-tight text-blue-700">
                        {{ row.customer?.contact?.company || row.customer.company_remark }}
                      </span>
                      <!-- Billing Contact Person — black (not blue) so the three
                           stacked lines alternate: address black / company blue /
                           contact black. -->
                      <span v-if="row.customer?.contact?.name" class="text-[11px] leading-tight text-gray-900">
                        {{ row.customer.contact.name }}
                      </span>
                    </div>
                  </TableData>

                  <!-- Vend ID -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-center">
                    <!--
                      When the customer has multiple vends, list them all
                      (ascending by code) with a line-break between each so the
                      user can see every machine bound to this customer
                      instead of a "+N more" hint.
                    -->
                    <template v-if="row.customer?.vends && row.customer.vends.length > 1">
                      <div class="flex flex-col items-center space-y-0.5">
                        <a
                          v-for="v in row.customer.vends"
                          :key="v.id"
                          target="_blank"
                          :href="'/settings/vend/' + v.id + '/update'"
                          class="text-blue-700 hover:underline"
                        >
                          {{ v.code }}
                        </a>
                      </div>
                    </template>
                    <template v-else>
                      <a
                        v-if="row.customer?.vend?.id"
                        target="_blank"
                        :href="'/settings/vend/' + row.customer.vend.id + '/update'"
                        class="text-blue-700 hover:underline"
                      >
                        {{ row.customer.vend.code }}
                      </a>
                      <span v-else-if="row.customer?.vend">{{ row.customer.vend.code }}</span>
                    </template>
                  </TableData>

                  <!-- Prefix -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-center">
                    <!--
                      Mirror the Vend ID column: when multiple vends are listed
                      (ascending by code), show each vend's prefix on its own
                      line so rows stay visually aligned.
                    -->
                    <template v-if="row.customer?.vends && row.customer.vends.length > 1">
                      <div class="flex flex-col items-center space-y-0.5">
                        <span v-for="v in row.customer.vends" :key="v.id">
                          {{ v.prefix || '—' }}
                        </span>
                      </div>
                    </template>
                    <template v-else>
                      <span v-if="row.customer?.vend?.prefix">{{ row.customer.vend.prefix }}</span>
                    </template>
                  </TableData>

                  <!-- Period Report YYMM (moved here so it sits right after Machine Prefix) -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-center">
                    <div class="flex flex-col space-y-1">
                      <span class="font-semibold">{{ periodReportLabel(row) }}</span>
                      <!--
                        "API Rpt" badge — green/with-tx-id when an invoice
                        was successfully created in CMS for this exact
                        (customer, period) tuple, blue/no-tx-id otherwise.
                        Hovering shows the transaction id and amount.
                      -->
                      <!-- <span
                        v-if="existingInvoice(row)"
                        class="inline-flex justify-center items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-emerald-100 text-emerald-800 border border-emerald-300 w-fit mx-auto"
                        v-tooltip="`Transaction #${existingInvoice(row).cms_transaction_id} — ${existingInvoiceAmountLabel(row)}`"
                      >
                        API Rpt #{{ existingInvoice(row).cms_transaction_id }}
                      </span>
                      <span
                        v-else
                        class="inline-flex justify-center items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-blue-100 text-blue-800 border border-blue-300 w-fit mx-auto"
                      >
                        API Rpt
                      </span> -->
                    </div>
                  </TableData>

                  <!--
                    Period — Start Date / End Date stacked into a single
                    cell so the column header (Period / Start Date / End Date)
                    maps directly onto two YYMMDD values per row.
                  -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-center">
                    <div class="flex flex-col space-y-1">
                      <span>{{ formatYYMMDD(row.period_start) }}</span>
                      <span>{{ formatYYMMDD(row.period_end) }}</span>
                    </div>
                  </TableData>

                  <!--
                    Sales, $
                    Cell layout mirrors the column header top-to-bottom:
                      Top (small, muted) - INCL-GST raw figure
                                           (sales_cents as stored, sum of
                                           vend_transactions.amount, matches
                                           the Transactions page total).
                      Bottom (larger)    - EXCL-GST de-grossed figure
                                           (sales_cents / (1 + operator.gst%)),
                                           the basis used by the PS-family
                                           Location Fees math + the
                                           Performance Report Preview popup,
                                           i.e. the headline number.
                    When the operator has no GST configured (rate=0) the two
                    lines would carry the same value, so we collapse to a
                    single line to avoid duplicate numbers in the cell.
                  -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-right">
                    <div class="flex flex-col items-end space-y-0.5">
                      <template v-if="row.customer && row.customer.operator && Number(row.customer.operator.gst_vat_rate) > 0">
                        <span class="text-[11px] text-gray-600 inline-flex items-center">
                          {{ formatMoney(row.sales_cents) }}
                          <TrendIcon :dir="null" />
                        </span>
                        <span class="inline-flex items-center">
                          {{ formatMoney(row.sales_cents / (1 + Number(row.customer.operator.gst_vat_rate) / 100)) }}
                          <TrendIcon :dir="trendSales(row)" />
                        </span>
                      </template>
                      <span v-else class="inline-flex items-center">
                        {{ formatMoney(row.sales_cents) }}
                        <TrendIcon :dir="trendSales(row)" />
                      </span>
                    </div>
                  </TableData>

                  <!--
                    Gross Earing (excl GST) + Rate sub-line.

                    Rate = gross_earning_cents (already excl GST) divided by
                    sales-excl-GST. For GST customers sales-excl-GST is
                    sales_cents / (1 + gst_vat_rate/100); for non-GST
                    customers sales_cents is already excl-GST so we use it
                    as-is. Mirrors the Vend Earning column's "value over
                    rate" layout (gray sub-line, NOT red — the red Rate
                    annotation in the spec screenshot is the formula hint,
                    not the rendering style).
                  -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-right">
                    <div class="flex flex-col items-end space-y-0.5">
                      <span class="inline-flex items-center">
                        {{ formatMoney(row.gross_earning_cents) }}
                        <TrendIcon :dir="trendGross(row)" />
                      </span>
                      <span class="text-[11px] text-gray-600 inline-flex items-center">
                        {{ formatPercent(grossEarningRate(row)) }}
                        <TrendIcon :dir="trendGrossRate(row)" />
                      </span>
                    </div>
                  </TableData>

                  <!-- # of Job — ops job items that serviced this customer in the period -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-center">
                    <span v-if="row.job_count" class="font-medium">{{ row.job_count }}</span>
                    <span v-else class="text-gray-400">—</span>
                  </TableData>

                  <!-- Placement Contract Type / Location Fees Rate -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-center">
                    <div class="flex flex-col items-center space-y-0.5 text-xs" v-if="row.contract_commission_type">
                      <span class="font-semibold inline-flex items-center">
                        {{ contractTypeLabel(row.contract_commission_type) }}
                        <span v-if="row.contract_diff?.placement_type" class="ml-1 inline-flex items-center px-1 py-0 rounded text-[9px] font-semibold bg-amber-100 text-amber-800 border border-amber-300 align-middle" v-tooltip="'Changed from the previous period'">New</span>
                      </span>
                      <span v-if="row.contract_commission_value != null">
                        <span v-if="['PS','PS+U','PSORU'].includes(row.contract_commission_type)">
                          {{ Number(row.contract_commission_value) }}%<span
                            v-if="row.contract_commission_value2 != null && ['PS+U','PSORU'].includes(row.contract_commission_type)"
                          >{{ row.contract_commission_type === 'PSORU' ? ' or ' : '+' }}${{ Number(row.contract_commission_value2) }}</span>
                        </span>
                        <span v-else>
                          ${{ Number(row.contract_commission_value).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2}) }}<span
                            v-if="row.contract_commission_value2 != null && row.contract_commission_type === 'R+U'"
                          > + ${{ Number(row.contract_commission_value2).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2}) }}</span>
                        </span>
                      </span>
                      <span v-if="row.contract_ps_term != null" class="text-gray-400">
                        PS Term: {{ Number(row.contract_ps_term) }}%
                      </span>
                    </div>
                  </TableData>

                  <!-- Location Fees / External Subsidize / Net Loc Fee.
                       Numbers only — the labels live in the column header, and
                       the data lines use the SAME vertical spacing (space-y-4)
                       as the header so each value lines up with its label. -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-right">
                    <div class="flex flex-col items-end space-y-4">
                      <div>
                        <span class="inline-flex items-center" :class="locationFeesColorClass(row.location_fees_cents, row.contract_commission_type)">
                          {{ formatMoneySigned(row.location_fees_cents) }}
                        </span>
                        <div
                          v-if="row.contract_commission_type === 'S'"
                          class="text-[10px] text-emerald-600 leading-tight"
                        >
                          Subsidy income
                        </div>
                      </div>
                      <!-- External Subsidize — per-segment snapshot; shown only
                           when present. "New" badge when it changed vs the
                           previous period (e.g. none → a subsidy). -->
                      <span class="text-gray-600 inline-flex items-center">
                        {{ externalSubsidizeCents(row) ? formatMoney(externalSubsidizeCents(row)) : '—' }}
                        <span v-if="row.contract_diff?.external_subsidize" class="ml-1 inline-flex items-center px-1 py-0 rounded text-[9px] font-semibold bg-amber-100 text-amber-800 border border-amber-300 align-middle" v-tooltip="'Changed from the previous period'">New</span>
                      </span>
                      <!-- Net Loc Fee = Location Fees − External Subsidize -->
                      <span
                        class="font-medium inline-flex items-center"
                        :class="netLocFeeCents(row) < 0 ? 'text-emerald-700' : 'text-gray-800'"
                      >
                        {{ formatMoneySigned(netLocFeeCents(row)) }}
                      </span>
                    </div>
                  </TableData>

                  <!-- Vend Earning $ / Rate (Vend Earning = Gross Earning - Location Fees) -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-right">
                    <div class="flex flex-col items-end space-y-0.5">
                      <span class="inline-flex items-center" :class="row.location_earning_cents >= 0 ? 'text-green-700 font-medium' : 'text-red-700 font-medium'">
                        {{ formatMoney(row.location_earning_cents) }}
                        <TrendIcon :dir="trendVend(row)" />
                      </span>
                      <span class="text-[11px] text-gray-600 inline-flex items-center">
                        {{ formatPercent(row.location_earning_rate) }}
                        <TrendIcon :dir="null" />
                      </span>
                    </div>
                  </TableData>

                  <!--
                    Accumulate Vend Earning
                    Running prefix sum of location_earning_cents
                    (= gross_earning - location_fees) for the customer
                    through THIS row's own year_month — computed server-side,
                    one batched query per page. So an April row shows the
                    cumulative through end-of-April, a March row shows
                    cumulative through end-of-March, and the most recent
                    month's row stays "up to date" because its underlying
                    monthly summary is refreshed to as_of_date.
                  -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-right">
                    <span
                      v-if="row.accumulate_vending_earning_cents != null"
                      :class="row.accumulate_vending_earning_cents >= 0 ? 'text-emerald-700 font-medium' : 'text-red-700 font-medium'"
                    >
                      {{ formatMoney(row.accumulate_vending_earning_cents) }}
                    </span>
                  </TableData>

                  <!--
                    Location Grading + Location Type. Grading is three
                    char(1) picks (placement, access, flexibility) — render
                    as a comma-joined "A, B, C" string, matching the
                    Vend/CustomerIndex.vue pattern. Tooltip spells out which
                    letter belongs to which rubric (P / A / F order).
                  -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-center">
                    <div class="flex flex-col items-center space-y-2 text-xs">
                      <span
                        v-if="hasAnyLocationGrading(row.customer)"
                        class="text-gray-700"
                        v-tooltip="{ content: 'Placement / Access / Flexibility', html: true }"
                      >
                        {{ row.customer?.location_grading?.placement || '-' }}, {{ row.customer?.location_grading?.access || '-' }}, {{ row.customer?.location_grading?.flexibility || '-' }}
                      </span>
                      <span v-if="row.customer?.location_type" class="text-gray-700">
                        {{ row.customer.location_type.name }}
                      </span>
                    </div>
                  </TableData>

                  <!--
                    Contract End Date / Auto Renewal / Notice Period — all
                    sourced from the customer record. Contract End Date
                    flips red when it's in the past (mirroring the existing
                    contract_until styling on Customer/Index.vue). Auto
                    Renewal renders a green tick / red cross heroicon for
                    quick visual scanning. Placed after Location Grading
                    per user preference.
                  -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-center">
                    <div class="flex flex-col items-center space-y-1 text-xs">
                      <span class="inline-flex items-center">
                        <span
                          v-if="row.customer?.contract_until"
                          :class="[contractEndDateClass(row.customer.contract_until)]"
                        >
                          {{ formatYYMMDD(row.customer.contract_until) }}
                        </span>
                        <span v-else class="text-gray-400">—</span>
                        <span v-if="row.contract_diff?.contract_until" class="ml-1 inline-flex items-center px-1 py-0 rounded text-[9px] font-semibold bg-amber-100 text-amber-800 border border-amber-300 align-middle" v-tooltip="'Changed from the previous period'">New</span>
                      </span>
                      <span class="inline-flex items-center justify-center">
                        <CheckCircleIcon
                          v-if="row.customer?.contract_auto_renewal"
                          class="h-5 w-5 text-green-600"
                          aria-hidden="true"
                          v-tooltip="'Auto Renewal: Yes'"
                        />
                        <XCircleIcon
                          v-else
                          class="h-5 w-5 text-red-600"
                          aria-hidden="true"
                          v-tooltip="'Auto Renewal: No'"
                        />
                        <span v-if="row.contract_diff?.auto_renewal" class="ml-1 inline-flex items-center px-1 py-0 rounded text-[9px] font-semibold bg-amber-100 text-amber-800 border border-amber-300 align-middle" v-tooltip="'Changed from the previous period'">New</span>
                      </span>
                      <span class="inline-flex items-center">
                        <span
                          v-if="row.customer?.contract_notice_period != null"
                          class="text-gray-900"
                        >
                          {{ row.customer.contract_notice_period }}
                        </span>
                        <span v-else class="text-gray-400">—</span>
                        <span v-if="row.contract_diff?.notice_period" class="ml-1 inline-flex items-center px-1 py-0 rounded text-[9px] font-semibold bg-amber-100 text-amber-800 border border-amber-300 align-middle" v-tooltip="'Changed from the previous period'">New</span>
                      </span>
                    </div>
                  </TableData>

                  <!--
                    Site Tag — customer-level data, so only show on
                    the FIRST row of each customer's cluster (multi-month
                    view groups multiple rows per customer).
                  -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-left min-w-[150px] max-w-[150px]">
                    <template v-if="isFirstRowForCustomer(rowIndex)">
                      <!--
                        Tags may be stored as snake_case (e.g.
                        already_inform_for_renewal — no spaces to wrap
                        on) or as multi-word strings (e.g. "Contract In
                        Progress"). Each badge gets a fixed width and
                        `break-words` so word-boundary wrapping is
                        preferred (keeps multi-word tags whole), while
                        single overly-long tokens still break to fit
                        the chip. Stacked vertically via flex-col for
                        readability when a customer has several tags.
                      -->
                      <!--
                        Tag badges alternate background shade (blue-50 /
                        blue-100) and carry a darker blue-400 border so
                        adjacent tags read as distinct chips instead of
                        running together as one block of pale blue.
                      -->
                      <div class="flex flex-col gap-1">
                        <span
                          v-for="(binding, tagIdx) in (row.customer?.tag_bindings ?? [])"
                          :key="binding.id"
                          :class="[
                            'inline-block w-28 px-2 py-0.5 rounded text-xs font-medium text-blue-900 border border-blue-400 break-words whitespace-normal leading-tight',
                            tagIdx % 2 === 0 ? 'bg-blue-50' : 'bg-blue-100',
                          ]"
                        >
                          {{ binding.tag?.name }}
                        </span>
                      </div>
                      <!--
                        Site-level Notes field — mirrors the Remarks
                        setup on /products/availability. The note is
                        stored on the customer record (not on the monthly
                        summary row), so it persists across whatever
                        period / filter the user has applied here. Audit
                        line below shows who last edited and when.
                      -->
                      <div class="mt-2 flex flex-col w-full">
                        <textarea
                          v-model="row.customer.notes"
                          @change="onNotesChanged(row.customer)"
                          @input="autoGrowTextarea($event.target)"
                          :ref="(el) => autoGrowTextarea(el)"
                          rows="4"
                          class="text-[13px] text-gray-700 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-1 block w-full resize-none overflow-hidden"
                          placeholder="Notes"
                        ></textarea>
                        <span class="text-[10px] text-gray-500 mt-1" v-if="row.customer?.notes_updated_by_user">
                          {{ row.customer.notes_updated_by_user.name }} ({{ moment(row.customer.notes_updated_at).format('YYMMDD hh:mma') }})
                        </span>
                      </div>
                    </template>
                  </TableData>

                  <!--
                    Period Verify & Lock cell — full state machine:
                      current month        → em-dash (cannot lock yet)
                      unlocked             → Lock button (admin-access customers)
                      locked + unpaid      → closed-lock icon
                                             + Unlock button (superadmin/admin)
                                             + Paid button (admin-access customers)
                      locked + paid        → closed-lock icon + green-check icon
                                             + Unlock button (DISABLED — must Unpaid first)
                                             + Unpaid button (superadmin/admin)
                    Tooltips on the icons surface "Locked by X at Y" / "Paid by
                    X at Y"; the reverse-action timestamps (last_unpaid_*,
                    last_unlocked_*) are appended so the user can see the most
                    recent reversal even after the row re-cycles.
                  -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-center">
                    <template v-if="row.is_current_month">
                      <span class="text-gray-300" v-tooltip="'Current month — lock once the month is complete'">—</span>
                    </template>
                    <template v-else-if="row.is_locked">
                      <div class="flex flex-col items-center space-y-1">
                        <div class="flex items-center justify-center space-x-1">
                          <LockClosedIcon class="h-5 w-5 text-amber-600" aria-hidden="true" v-tooltip="lockedTooltip(row)" />
                          <CheckCircleIcon
                            v-if="row.is_paid"
                            class="h-5 w-5 text-emerald-600"
                            aria-hidden="true"
                            v-tooltip="paidTooltip(row)"
                          />
                        </div>
                        <!--
                          Visible inline audit — shown right in the cell (not
                          just hover-tooltip) so the user SEES who did what
                          and when the moment they click a button. Each line
                          carries timestamp + actor; format mirrors the
                          existing tooltips. last_unpaid_* surfaces only
                          while the row is in the unpaid state (so the most
                          recent Unpaid click stays visible).
                        -->
                        <div class="text-[10px] leading-tight text-center">
                          <div class="text-amber-700">
                            <span class="font-semibold">Locked</span> {{ formatYYMMDDHM(row.locked_at) }}
                            <span v-if="row.locked_by_user">by {{ row.locked_by_user.name }}</span>
                          </div>
                          <div v-if="row.is_paid" class="text-emerald-700">
                            <span class="font-semibold">Paid</span> {{ formatYYMMDDHM(row.paid_at) }}
                            <span v-if="row.paid_by_user">by {{ row.paid_by_user.name }}</span>
                          </div>
                          <!-- Actual payment date from the Paid popup (may
                               differ from the click timestamp above). -->
                          <div v-if="row.is_paid && row.paid_date" class="text-emerald-700">
                            <span class="font-semibold">Pymt</span> {{ moment(row.paid_date).format('YYMMDD') }}
                          </div>
                          <div v-if="!row.is_paid && row.last_unpaid_at" class="text-red-700">
                            <span class="font-semibold">Unpaid</span> {{ formatYYMMDDHM(row.last_unpaid_at) }}
                            <span v-if="row.last_unpaid_by_user">by {{ row.last_unpaid_by_user.name }}</span>
                          </div>
                        </div>
                        <!-- Unlock — blocked while Paid; UI disables the
                             button + tooltip explains why. Server re-checks. -->
                        <Button
                          v-if="canUnlock"
                          type="button"
                          class="inline-flex items-center justify-center space-x-1 px-2 py-1 text-[11px] bg-gray-100 hover:bg-gray-200 text-gray-700 rounded disabled:opacity-50 disabled:cursor-not-allowed"
                          :disabled="lockingFor.has(row.id) || row.is_paid"
                          v-tooltip="row.is_paid ? 'Mark Unpaid first before unlocking' : ''"
                          @click="onUnlockClicked(row)"
                        >
                          <LockOpenIcon class="h-3.5 w-3.5" aria-hidden="true" />
                          <span>Unlock</span>
                        </Button>
                        <!-- Paid — only visible on a locked+unpaid row, and
                             only from the paid-tracking cutoff (2605) onward;
                             periods 2604 and earlier never show it. -->
                        <Button
                          v-if="canPaid && !row.is_paid && isPaidEligiblePeriod(row)"
                          type="button"
                          class="inline-flex items-center justify-center space-x-1 px-2 py-1 text-[11px] bg-emerald-100 hover:bg-emerald-200 text-emerald-800 rounded"
                          :disabled="lockingFor.has(row.id)"
                          @click="onPaidClicked(row)"
                        >
                          <CheckCircleIcon class="h-3.5 w-3.5" aria-hidden="true" />
                          <span>Paid</span>
                        </Button>
                        <!-- Unpaid — only visible on a locked+paid row.
                             Same access tier as Unlock (superadmin/admin). -->
                        <Button
                          v-if="canUnpaid && row.is_paid"
                          type="button"
                          class="inline-flex items-center justify-center space-x-1 px-2 py-1 text-[11px] bg-red-100 hover:bg-red-200 text-red-800 rounded"
                          :disabled="lockingFor.has(row.id)"
                          @click="onUnpaidClicked(row)"
                        >
                          <XCircleIcon class="h-3.5 w-3.5" aria-hidden="true" />
                          <span>Unpaid</span>
                        </Button>
                      </div>
                    </template>
                    <template v-else-if="canLock">
                      <div class="flex flex-col items-center space-y-1">
                        <Button
                          type="button"
                          class="inline-flex items-center justify-center space-x-1 px-2 py-1 text-[11px] bg-amber-100 hover:bg-amber-200 text-amber-800 rounded"
                          :disabled="lockingFor.has(row.id)"
                          @click="onLockClicked(row)"
                        >
                          <LockOpenIcon class="h-3.5 w-3.5" aria-hidden="true" />
                          <span>Lock</span>
                        </Button>
                        <!--
                          Last-unlocked caption — appears immediately after
                          the user clicks Unlock (the row flips to this
                          template) so the Unlock timestamp + actor are
                          visible right in the cell, not buried in a tooltip.
                        -->
                        <div v-if="row.last_unlocked_at" class="text-[10px] text-gray-500 leading-tight text-center">
                          <span class="font-semibold">Unlocked</span> {{ formatYYMMDDHM(row.last_unlocked_at) }}
                          <span v-if="row.last_unlocked_by_user">by {{ row.last_unlocked_by_user.name }}</span>
                        </div>
                      </div>
                    </template>
                    <template v-else>
                      <span class="text-gray-300">—</span>
                    </template>
                  </TableData>

                  <!-- Action — now the LAST column (was 2 positions earlier). -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-center">
                    <!--
                      Bulk mode: replace the action buttons with a single
                      checkbox so the user can multi-select rows for the
                      "Create API Invoice(s)" submit at the top.
                      Non-invoiceable rows (F/S/no-person_id/incomplete
                      contract) get a disabled placeholder, mirroring
                      OpsJob's bulk pattern.
                    -->
                    <template v-if="bulkMode">
                      <input
                        v-if="isInvoiceable(row)"
                        type="checkbox"
                        class="h-5 w-5 rounded border-gray-300 text-yellow-600 cursor-pointer accent-yellow-500"
                        :value="rowKey(row)"
                        v-model="selectedRowKeys"
                      />
                      <span v-else class="text-xs text-gray-400" v-tooltip="'Not invoiceable (F/S, missing person_id, or incomplete contract)'">—</span>
                    </template>
                    <div v-else class="flex flex-col items-stretch space-y-1">
                      <!--
                        Email button used to live in this row. It's now inside
                        the Report Content modal, and only shows on LOCKED
                        rows (post-lock action). The click fires a `mailto:`
                        from the operator's mail client and records the audit
                        (timestamp + who) on the server.
                      -->

                      <!--
                        Report Content preview — fetches the same payload
                        the email body will carry and renders it in a modal
                        so the user can inspect before sending. Disabled
                        when the contract info is incomplete (F/S, or any
                        type missing required values).
                      -->
                      <Button
                        type="button"
                        :class="[
                          'inline-flex items-center justify-center space-x-1 px-3 py-2 text-xs',
                          row.customer?.has_report_content
                            ? 'bg-emerald-100 hover:bg-emerald-200 text-emerald-800'
                            : 'bg-gray-200 text-gray-400 cursor-not-allowed',
                        ]"
                        :disabled="!row.customer?.has_report_content"
                        v-tooltip="!row.customer?.has_report_content
                          ? 'Free Placement / Subsidized Plan have no report content; other types need a complete contract'
                          : 'Preview the report content that will be emailed'"
                        @click="onReportContentClicked(row)"
                      >
                        <span>Report Content</span>
                      </Button>
                      <!--
                        Last-email audit — surfaces the same "Last sent by X
                        on Y" line that appears at the bottom of the Report
                        Content preview modal, but right here under the
                        Report Content button so the user can see the most
                        recent send at-a-glance without opening the modal.
                        Only shown when the Performance Report has actually
                        been emailed for this (customer, period) tuple.
                      -->
                      <div
                        v-if="row.report_emailed_at"
                        class="text-[10px] text-gray-600 leading-tight text-center mt-1"
                      >
                        Last sent by
                        <span class="font-medium text-gray-800">{{ row.report_emailed_by_user?.name || 'someone' }}</span>
                        on
                        <span class="font-medium text-gray-800">{{ row.report_emailed_at }}</span>
                      </div>

                      <!--
                        Create API Invoice — POSTs the period-line items
                        (using hardcoded item codes 055/V01/60 per contract
                        type) to /api/transactions/deals via the queued
                        SyncCustomerInvoiceCMS job. When an invoice already
                        exists for this period, the button changes copy to
                        "Re-create" and asks for explicit confirmation
                        before posting force=1.
                      -->
                      <!-- <Button
                        v-if="cmsEndpoint && isInvoiceable(row)"
                        type="button"
                        :class="[
                          'inline-flex items-center justify-center space-x-1 px-3 py-2 text-xs',
                          existingInvoice(row)
                            ? 'bg-amber-100 hover:bg-amber-200 text-amber-800'
                            : 'bg-yellow-100 hover:bg-yellow-200 text-yellow-800',
                          creatingInvoiceFor.has(rowKey(row)) ? 'opacity-60 cursor-wait' : '',
                        ]"
                        :disabled="creatingInvoiceFor.has(rowKey(row))"
                        v-tooltip="existingInvoice(row)
                          ? `Re-create API Invoice (existing: #${existingInvoice(row).cms_transaction_id})`
                          : 'Create API Invoice in CMS for this period'"
                        @click="onCreateApiInvoiceClicked(row)"
                      >
                        <ReceiptPercentIcon class="w-4 h-4"></ReceiptPercentIcon>
                        <span>{{ existingInvoice(row) ? 'Re-create Invoice' : 'Create API Invoice' }}</span>
                      </Button> -->
                    </div>
                  </TableData>
                </tr>

                <tr v-if="!summaries.data?.length">
                  <td colspan="19" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium text-center">
                    No Results Found — try a broader Period Report or fewer filters.
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator
              v-if="summaries.data?.length"
              :links="summaries.links"
              :meta="summaries.meta"
            ></Paginator>
          </div>
        </div>
      </div>
    </div>

    <MapMarker
      v-if="showMapMarkerModal"
      :customers="mapCustomers"
      :api-key="mapApiKey"
      :showModal="showMapMarkerModal"
      @modalClose="onMapMarkerModalClose"
    />

    <!--
      Report Content preview modal — populated lazily by clicking the
      Report Content button in any row's Action column. Renders the same
      structure that the queued Performance Report email will carry, so
      "what you see is what you'll send".
    -->
    <Modal :open="showReportContentModal" @modalClose="onReportContentModalClose">
      <template #header>
        <div class="flex items-center space-x-2">
          <DocumentTextIcon class="w-5 h-5 text-blue-600" />
          <span>Performance Report Preview</span>
        </div>
      </template>
      <template #default>
        <div v-if="reportContentLoading" class="text-center py-10 text-gray-500">
          <div class="animate-pulse text-sm">Loading report content…</div>
        </div>

        <div v-else-if="reportContent" class="text-sm">
          <!-- Site chip -->
          <div
            v-if="reportContentCustomerLabel"
            class="inline-flex items-center px-3 py-1 rounded-full bg-gray-100 text-gray-700 text-xs mb-4"
          >
            <span class="font-medium">{{ reportContentCustomerLabel }}</span>
          </div>

          <!-- Title banner -->
          <div class="rounded-md bg-blue-50 border border-blue-200 px-4 py-3 mb-4">
            <div class="text-blue-900 font-semibold text-base leading-tight">
              Vending Machine Location Fees Report
            </div>
            <div class="mt-1 text-blue-700 font-bold uppercase tracking-wide text-sm">
              {{ reportContent.contract_type_label }}
            </div>
          </div>

          <!--
            Meta block — Period, Days, Machine ID, Machine Prefix.
            Machine fields are populated locally from the row data when
            the modal opens (see onReportContentClicked) so no extra API
            roundtrip is needed. Hidden when blank so customers without a
            bound vend don't show empty cards.
          -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4">
            <div class="rounded-md border border-gray-200 px-3 py-2">
              <div class="text-xs uppercase tracking-wide text-gray-500">Period (YYMM)</div>
              <div class="mt-0.5 text-gray-900 font-semibold text-base">
                {{ reportContent.period_label }}
              </div>
            </div>
            <div
              v-if="reportContent.active_days != null && reportContent.month_days != null"
              class="rounded-md border border-gray-200 px-3 py-2"
            >
              <div class="text-xs uppercase tracking-wide text-gray-500">Total number of days</div>
              <div class="mt-0.5 text-gray-900 font-semibold text-base">
                {{ reportContent.active_days }} / {{ reportContent.month_days }}
              </div>
            </div>
            <div
              v-if="reportContentMachineId"
              class="rounded-md border border-gray-200 px-3 py-2"
            >
              <div class="text-xs uppercase tracking-wide text-gray-500">Machine ID</div>
              <div class="mt-0.5 text-gray-900 font-semibold text-base whitespace-pre-line">
                {{ reportContentMachineId }}
              </div>
            </div>
            <div
              v-if="reportContentMachinePrefix"
              class="rounded-md border border-gray-200 px-3 py-2"
            >
              <div class="text-xs uppercase tracking-wide text-gray-500">Machine Prefix</div>
              <div class="mt-0.5 text-gray-900 font-semibold text-base whitespace-pre-line">
                {{ reportContentMachinePrefix }}
              </div>
            </div>
          </div>

          <!--
            Calculation lines — bumped to text-base for label / formula and
            text-lg for the resolved value, so the numbers carry more weight
            visually than the surrounding meta cards.

            Lines with `formula_internal: true` carry an admin-only formula
            (e.g. the PS Term discount applied to gross sales for the
            "Total Revenue" line of PS-family contracts). We render those
            formulas in greyscale + strikethrough so admins can still verify
            the math, but it's visually clear the formula won't appear in
            the customer-facing report once it's emailed/printed.
          -->
          <div v-if="reportContent.lines?.length" class="rounded-md border border-gray-200 divide-y divide-gray-200 mb-4">
            <div
              v-for="(line, idx) in reportContent.lines"
              :key="idx"
              class="flex flex-col sm:flex-row sm:items-baseline sm:justify-between px-4 py-3"
            >
              <div class="text-gray-800 font-medium text-base">{{ line.label }}</div>
              <div class="mt-1 sm:mt-0 sm:text-right">
                <span
                  :class="line.formula_internal
                    ? 'text-gray-400 line-through text-sm italic'
                    : 'text-gray-600 text-sm'"
                  :title="line.formula_internal ? 'Internal calculation — not shown to customer in the final report' : null"
                >{{ line.formula }}</span>
                <span
                  v-if="line.formula_internal"
                  class="ml-1.5 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium uppercase tracking-wide bg-amber-100 text-amber-800 border border-amber-200 align-middle"
                  title="Internal calculation — not shown to customer in the final report"
                >
                  Admin only
                </span>
                <span class="text-gray-400 mx-1.5 text-base">=</span>
                <span class="text-gray-900 font-bold text-lg">{{ line.value }}</span>
              </div>
            </div>
          </div>

          <!--
            Internal-formula legend — only shown when at least one line is
            marked admin-only, so non-PS contracts (R, U) aren't cluttered
            with an irrelevant note.
          -->
          <div
            v-if="reportContent.lines?.some(l => l.formula_internal)"
            class="text-[11px] text-amber-700 bg-amber-50 border border-amber-200 rounded px-3 py-2 mb-3 leading-snug"
          >
            <span class="font-semibold">Note:</span>
            Greyed-out formulas (tagged <span class="font-semibold">Admin only</span>) reflect internal PS Term adjustments to gross sales and will <span class="font-semibold">not</span> appear in the report sent to the customer — only the resolved value on the right will.
          </div>

          <!-- Total banner -->
          <div
            v-if="reportContent.has_total"
            class="flex items-baseline justify-between rounded-md bg-blue-600 px-4 py-3 text-white mb-3"
          >
            <span class="font-semibold text-base">Total</span>
            <span class="font-bold text-xl">{{ reportContent.total_value }}</span>
          </div>

          <!-- Footnote -->
          <div class="text-xs text-gray-500 italic mt-2">
            {{ reportContent.footnote }}
          </div>

          <!--
            ─────────────────────────────────────────────────────────────────
            Email Performance Report — modal-only action.
            Only visible on a LOCKED row (post-lock action: you don't email
            a customer a report while the month's still moving). Clicking it:
              1. opens the operator's own mail client via `mailto:`, prefilled
                 with subject + a plain-text body of what's in this modal;
              2. POSTs to the server to stamp "emailed at / by" on the row,
                 so we have an audit trail of who sent which period.
            The line below shows the last-send audit when present.
          -->
          <div
            v-if="reportContentRow?.is_locked"
            class="mt-4 border-t border-gray-200 pt-3 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2"
          >
            <div class="text-xs text-gray-600">
              <template v-if="reportContentRow?.report_emailed_at">
                Last sent by
                <span class="font-medium text-gray-800">
                  {{ reportContentRow.report_emailed_by_user?.name || 'someone' }}
                </span>
                on
                <span class="font-medium text-gray-800">
                  {{ reportContentRow.report_emailed_at }}
                </span>
              </template>
              <template v-else>
                Not yet emailed for this period.
              </template>
            </div>
            <Button
              v-if="reportContentRow?.customer?.is_report_email_enabled && reportContentRow?.customer?.report_email"
              type="button"
              class="inline-flex items-center justify-center space-x-1 px-3 py-2 text-xs bg-blue-100 hover:bg-blue-200 text-blue-800"
              :disabled="sendingReportFor.has(reportContentRow.customer.id)"
              v-tooltip="'Open your mail client to send this report, and record the send'"
              @click="onModalEmailClicked"
            >
              <span>Email</span>
            </Button>
            <span
              v-else
              class="text-[11px] text-gray-400 italic"
              v-tooltip="'Site has no report-email address / opt-in. Set it on the Site Edit page first.'"
            >
              Email not enabled on this customer
            </span>
          </div>
        </div>

        <div v-else class="text-center py-10 text-gray-500 text-sm">
          No content available for this customer.
        </div>
      </template>
    </Modal>

    <!--
      Paid confirmation modal — opened by the Paid button on a locked row.
      Captures the ACTUAL payment date (paid_date) before marking Paid.
      Left empty / cleared → defaults to today (client pre-fills today and
      the server also falls back to today). paid_at (the click-audit
      timestamp) is always stamped server-side regardless of this date.
    -->
    <Modal :open="showPaidModal" @modalClose="onPaidModalClose">
      <template #header>
        <div class="flex items-center space-x-2">
          <CheckCircleIcon class="w-5 h-5 text-emerald-600" />
          <span>Mark as Paid</span>
        </div>
      </template>
      <template #default>
        <div class="text-sm text-left">
          <p class="mb-4 text-gray-700">
            Mark <span class="font-semibold">{{ paidModalLabel }}</span> as Paid?
          </p>
          <label class="block text-xs font-medium text-gray-600 mb-1" for="paid-date-input">
            Payment date
          </label>
          <input
            id="paid-date-input"
            v-model="paidModalDate"
            type="date"
            class="w-48 rounded border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500"
          />
          <p class="text-[11px] text-gray-500 mt-1">
            Leave empty to use today's date.
          </p>
          <p class="text-[11px] text-gray-500 mt-3">
            This records the payment date + actor against the locked snapshot.
            The Unlock button will be blocked until the row is Unpaid again.
          </p>
          <div class="flex justify-end space-x-2 mt-5">
            <Button
              type="button"
              class="px-3 py-2 text-xs bg-gray-100 hover:bg-gray-200 text-gray-700"
              @click="onPaidModalClose"
            >
              Cancel
            </Button>
            <Button
              type="button"
              class="inline-flex items-center justify-center space-x-1 px-3 py-2 text-xs bg-emerald-100 hover:bg-emerald-200 text-emerald-800"
              :disabled="paidModalRow && lockingFor.has(paidModalRow.id)"
              @click="onPaidModalConfirm"
            >
              <CheckCircleIcon class="h-3.5 w-3.5" aria-hidden="true" />
              <span>Confirm Paid</span>
            </Button>
          </div>
        </div>
      </template>
    </Modal>

    <!--
      Batch Lock confirmation modal — locks every lock-eligible selected
      row in one request. Snapshot semantics are identical to single Lock.
    -->
    <Modal :open="showBatchLockModal" @modalClose="showBatchLockModal = false">
      <template #header>
        <div class="flex items-center space-x-2">
          <LockClosedIcon class="w-5 h-5 text-amber-600" />
          <span>Batch Lock</span>
        </div>
      </template>
      <template #default>
        <div class="text-sm text-left">
          <p class="mb-3 text-gray-700">
            Lock <span class="font-semibold">{{ selectedLockRows.length }}</span> selected period(s)?
          </p>
          <p class="text-[11px] text-gray-500">
            Each period's live figures (current contract applied to that
            month's sales/gross) are frozen into a snapshot — same as
            clicking Lock on each row individually.
          </p>
          <div class="flex justify-end space-x-2 mt-5">
            <Button
              type="button"
              class="px-3 py-2 text-xs bg-gray-100 hover:bg-gray-200 text-gray-700"
              @click="showBatchLockModal = false"
            >
              Cancel
            </Button>
            <Button
              type="button"
              class="inline-flex items-center justify-center space-x-1 px-3 py-2 text-xs bg-amber-100 hover:bg-amber-200 text-amber-800"
              :disabled="batchSubmitting || !selectedLockRows.length"
              @click="onBatchLockConfirm"
            >
              <LockClosedIcon class="h-3.5 w-3.5" aria-hidden="true" />
              <span>Confirm Lock ({{ selectedLockRows.length }})</span>
            </Button>
          </div>
        </div>
      </template>
    </Modal>

    <!--
      Batch Mark Paid modal — one SHARED payment date applied to every
      paid-eligible selected row. Same empty→today rule as the single-row
      Paid popup.
    -->
    <Modal :open="showBatchPaidModal" @modalClose="showBatchPaidModal = false">
      <template #header>
        <div class="flex items-center space-x-2">
          <CheckCircleIcon class="w-5 h-5 text-emerald-600" />
          <span>Batch Mark as Paid</span>
        </div>
      </template>
      <template #default>
        <div class="text-sm text-left">
          <p class="mb-4 text-gray-700">
            Mark <span class="font-semibold">{{ selectedPaidRows.length }}</span> selected period(s) as Paid?
          </p>
          <label class="block text-xs font-medium text-gray-600 mb-1" for="batch-paid-date-input">
            Payment date (applied to ALL selected rows)
          </label>
          <input
            id="batch-paid-date-input"
            v-model="batchPaidDate"
            type="date"
            class="w-48 rounded border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500"
          />
          <p class="text-[11px] text-gray-500 mt-1">
            Leave empty to use today's date.
          </p>
          <p class="text-[11px] text-gray-500 mt-3">
            This records the payment date + actor against each locked
            snapshot. Unlock will be blocked on these rows until Unpaid.
          </p>
          <div class="flex justify-end space-x-2 mt-5">
            <Button
              type="button"
              class="px-3 py-2 text-xs bg-gray-100 hover:bg-gray-200 text-gray-700"
              @click="showBatchPaidModal = false"
            >
              Cancel
            </Button>
            <Button
              type="button"
              class="inline-flex items-center justify-center space-x-1 px-3 py-2 text-xs bg-emerald-100 hover:bg-emerald-200 text-emerald-800"
              :disabled="batchSubmitting || !selectedPaidRows.length"
              @click="onBatchPaidConfirm"
            >
              <CheckCircleIcon class="h-3.5 w-3.5" aria-hidden="true" />
              <span>Confirm Paid ({{ selectedPaidRows.length }})</span>
            </Button>
          </div>
        </div>
      </template>
    </Modal>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import MapMarker from '@/Components/MapMarker.vue';
import Modal from '@/Components/Modal.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import SingleSortItem from '@/Components/SingleSortItem.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { ArrowDownTrayIcon, BackspaceIcon, CheckCircleIcon, ClipboardDocumentCheckIcon, DocumentTextIcon, LockClosedIcon, LockOpenIcon, MagnifyingGlassIcon, MapPinIcon, PencilSquareIcon, ReceiptPercentIcon, XCircleIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import { computed, ref, onMounted, nextTick, h } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';
import { vTooltip } from 'floating-vue';
import moment from 'moment';

const props = defineProps({
  summaries: Object,
  periodReport: String,
  periodReportOptions: Array,
  rangeStart: String,
  rangeEnd: String,
  cmsEndpoint: String,
  locationTypeOptions: [Array, Object],
  mapApiKey: String,
  operatorOptions: Object,
  tags: Object,
  vendPrefixOptions: Object,
  // Placement Contract Type dropdown options ([{id, value}, ...]).
  contractCommissionTypeOptions: Array,
  // 5-value Site Status dropdown options ([{id, name}, ...]). Includes
  // an "All" sentinel ahead of the STATUSES_MAPPING entries.
  statuses: Array,
  // Aggregate totals across the FULL filtered set (sales / gross earning /
  // location fees / vending earnings). Cents-typed — formatMoney() handles
  // currency exponent + symbol. Renders into the 4 boxes above the table.
  totals: {
    type: Object,
    default: () => ({
      sales_cents: 0,
      gross_earning_cents: 0,
      location_fees_cents: 0,
      location_earning_cents: 0,
      // Distinct-customer aggregate counts (filtered set).
      no_contract_attachment_count: 0,
      expiring_in_30d_count: 0,
    }),
  },
});

const authOperator = usePage().props.auth.operator;
const operatorCountry = usePage().props.auth.operatorCountry;
const permissions = usePage().props.auth.permissions;
const roles = usePage().props.auth.roles ?? [];

// Lock = admins (admin-access customers). Unlock requires a HIGHER access
// level — restricted to the top-tier roles (superadmin / admin / supervisor).
const canLock = computed(() => (permissions ?? []).includes('admin-access customers'));
const canUnlock = computed(() => (roles ?? []).includes('superadmin') || (roles ?? []).includes('admin') || (roles ?? []).includes('supervisor'));
// Paid mirrors Lock's permission tier; Unpaid mirrors Unlock's tier (reverses
// a recorded action → tighter role gate). Server re-checks both.
const canPaid = computed(() => canLock.value);
const canUnpaid = computed(() => canUnlock.value);

// Paid-tracking go-live cutoff: the Paid button only appears for period 2605
// (May 2026) onward. Locked periods 2604 and earlier predate paid
// reconciliation and must NOT show a Paid button. year_month is an ISO
// 'YYYY-MM-DD' first-of-month string, so a plain lexicographic compare is
// correct and stable. Hardcoded per product decision.
const PAID_CUTOFF_YEAR_MONTH = '2026-05-01';
const isPaidEligiblePeriod = (row) =>
  typeof row?.year_month === 'string' && row.year_month >= PAID_CUTOFF_YEAR_MONTH;

// Per-row in-flight guard for the Lock / Unlock buttons (keyed by summary id).
const lockingFor = ref(new Set());

// ---------------------------------------------------------------------------
// Batch actions (Lock / Mark Paid) — row checkboxes + page-scoped select-all.
//
// Selection is keyed by summary row id. Eligibility SELF-EVALUATES per
// action, mirroring the per-row buttons:
//   - Lock-eligible:  past month + not locked yet
//   - Paid-eligible:  past month + locked + not paid + period 2605 onward
// A row is selectable if it qualifies for EITHER action; the batch bar then
// shows per-action counts so a mixed selection is never ambiguous. The
// master checkbox only ever touches the CURRENT page's selectable rows.
// Server re-checks eligibility per row and skips anything stale.
// ---------------------------------------------------------------------------
const batchSelected = ref(new Set());
const batchSubmitting = ref(false);
const showBatchLockModal = ref(false);
const showBatchPaidModal = ref(false);
const batchPaidDate = ref('');

const isLockEligibleRow = (row) => !row.is_current_month && !row.is_locked;
const isPaidEligibleRow = (row) =>
  !row.is_current_month && row.is_locked && !row.is_paid && isPaidEligiblePeriod(row);
const isBatchSelectable = (row) => isLockEligibleRow(row) || isPaidEligibleRow(row);

// Current-page derivations. selectedRows intersects the selection with the
// page's rows, so ids left over from a previous page/filter can never leak
// into counts or into the ids POSTed to the server.
const selectableRows = computed(() => (props.summaries?.data ?? []).filter(isBatchSelectable));
const selectedRows = computed(() => selectableRows.value.filter((r) => batchSelected.value.has(r.id)));
const selectedLockRows = computed(() => selectedRows.value.filter(isLockEligibleRow));
const selectedPaidRows = computed(() => selectedRows.value.filter(isPaidEligibleRow));
const allPageSelected = computed(
  () => selectableRows.value.length > 0 && selectableRows.value.every((r) => batchSelected.value.has(r.id))
);
const somePageSelected = computed(() => selectableRows.value.some((r) => batchSelected.value.has(r.id)));

function toggleRowSelected(row) {
  if (!isBatchSelectable(row)) return;
  batchSelected.value.has(row.id) ? batchSelected.value.delete(row.id) : batchSelected.value.add(row.id);
}

// Master checkbox — page-scoped on purpose: ticking it must NEVER select
// filtered rows that aren't visible on this page.
function toggleSelectAllPage() {
  if (allPageSelected.value) {
    selectableRows.value.forEach((r) => batchSelected.value.delete(r.id));
  } else {
    selectableRows.value.forEach((r) => batchSelected.value.add(r.id));
  }
}

function clearBatchSelection() {
  batchSelected.value.clear();
}

// Tooltip on each row checkbox — says what the row is selectable FOR, or
// why it isn't selectable at all.
function batchCheckboxTooltip(row) {
  if (row.is_current_month) return 'Current month — cannot lock yet';
  if (isLockEligibleRow(row)) return 'Select for batch Lock';
  if (isPaidEligibleRow(row)) return 'Select for batch Mark Paid';
  if (row.is_locked && row.is_paid) return 'Already Paid';
  if (row.is_locked && !isPaidEligiblePeriod(row)) return 'Locked — period predates paid tracking (2605)';
  return '';
}

function onBatchPaidClicked() {
  if (!selectedPaidRows.value.length) return;
  batchPaidDate.value = moment().format('YYYY-MM-DD'); // pre-fill today
  showBatchPaidModal.value = true;
}

function onBatchLockConfirm() {
  const ids = selectedLockRows.value.map((r) => r.id);
  if (!ids.length || batchSubmitting.value) return;

  batchSubmitting.value = true;
  showBatchLockModal.value = false;
  // Lock freezes money figures → refresh summaries + totals in lockstep,
  // same as the single-row Lock.
  router.post('/customers/summary/batch-lock', { ids }, {
    only: ['summaries', 'totals'],
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      toast.success(`Locked ${ids.length} period(s).`, { timeout: 3000 });
      clearBatchSelection();
    },
    onError: (errors) => toast.error(errors?.batch || 'Failed to batch lock.', { timeout: 4000 }),
    onFinish: () => { batchSubmitting.value = false; },
  });
}

function onBatchPaidConfirm() {
  const ids = selectedPaidRows.value.map((r) => r.id);
  if (!ids.length || batchSubmitting.value) return;

  // Empty/cleared field → today (server defaults too — belt and braces).
  const paidDate = batchPaidDate.value || moment().format('YYYY-MM-DD');

  batchSubmitting.value = true;
  showBatchPaidModal.value = false;
  router.post('/customers/summary/batch-paid', { ids, paid_date: paidDate }, {
    only: ['summaries'],
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      toast.success(`Marked ${ids.length} period(s) Paid.`, { timeout: 3000 });
      clearBatchSelection();
    },
    onError: (errors) => toast.error(errors?.batch || errors?.paid_date || 'Failed to batch mark Paid.', { timeout: 4000 }),
    onFinish: () => { batchSubmitting.value = false; },
  });
}

const toast = useToast();
const loading = ref(false);
// Per-row in-flight guard so the Email button can't be clicked twice while
// the request is open. Keyed by customer_id (one button per row anyway).
const sendingReportFor = ref(new Set());

// Report Content preview modal state. Single instance — only one row's
// content can be visible at a time, so a flat ref structure is enough.
const showReportContentModal = ref(false);
const reportContentLoading = ref(false);
const reportContent = ref(null);
const reportContentCustomerLabel = ref('');
// Machine context shown alongside the customer name in the modal header
// area. Resolved from the row data we already have (vend / vends), so no
// extra API roundtrip is needed.
const reportContentMachineId = ref('');
const reportContentMachinePrefix = ref('');
// The full summary row backing the open modal — drives the locked-only Email
// button and the "Last sent by X at Y" audit line at the bottom of the modal.
const reportContentRow = ref(null);
const showMapMarkerModal = ref(false);
const mapCustomers = ref([]);

// Per-row in-flight guard for the "Create API Invoice" button. Keyed by
// row composite "<customer_id>|<period_start>|<period_end>" so a customer
// with multiple visible periods doesn't accidentally lock all of them.
const creatingInvoiceFor = ref(new Set());

// Bulk-mode state. selectedRowKeys uses the same composite key as
// creatingInvoiceFor so we can map back to {customer_id, period_start,
// period_end} on submit.
const bulkMode = ref(false);
const selectedRowKeys = ref([]);
const bulkSubmitting = ref(false);

// Show the "Show Map Markers" button only when at least one row's customer
// has a delivery_address with both latitude and longitude.
const hasAnyAddressWithCoords = computed(() => {
  return (props.summaries?.data ?? []).some((row) =>
    row.customer
    && row.customer.delivery_address
    && row.customer.delivery_address.latitude
    && row.customer.delivery_address.longitude
  );
});

const filters = ref({
  customer: '',
  // Site Status — 5-value (matches Customer::STATUSES_MAPPING). Stores the
  // selected option object {id, value}; .id is sent to the server.
  status: '',
  is_cms: '',
  ref_id: '',
  vend_code: '',
  location_types: [],
  operators: [],
  vendPrefixes: [],
  tags: [],
  period_report: '',
  // Multi-select tags of contract type codes (F, S, R, U, PS, PS+U, PSORU).
  contract_commission_types: [],
  // Contract Attachment? boolean filter ('all' | 'true' | 'false').
  contract_attachment: '',
  // Replicated (same month)? filter ('all' | 'true' = only replicated rows).
  replicated_only: '',
  // Period Locked? filter ('all' | 'true' = locked | 'false' = unlocked).
  period_locked: '',
  // Location Fee Paid? filter ('all' | 'true' = paid | 'false' = unpaid).
  location_fee_paid: '',
  // Payment Date range filter (on paid_date). 'YYYY-MM-DD' strings from the
  // native date inputs; empty = open-ended on that side.
  paid_date_from: '',
  paid_date_to: '',
  // Default sort: Note Last Updated, latest → oldest. The controller maps
  // sortBy boolean → 'asc'/'desc' (false = desc); the 'notes_updated_at'
  // sortKey is resolved server-side by ordering on customers.notes_updated_at
  // (see CustomerController::summary's notes_updated_at orderByRaw branch).
  // Sites whose Site Note was never edited sort to the bottom.
  sortKey: 'notes_updated_at',
  sortBy: false,
  numberPerPage: 100,
});

// Site Status dropdown — populated from props.statuses (5-value:
// Potential / New / Active / Pending / Inactive + an "All" sentinel).
const statusOptions = ref([]);
const booleanOptions = ref([]);
// Dedicated options for the "Contract Attachment?" filter so its labels
// (Yes / No) stay independent of the shared booleanOptions set and never
// resolve to the wrong label.
const contractAttachmentOptions = ref([]);
// "Replicated (same month)?" filter — All vs only rows whose month was split
// into more than one row for the same customer.
const replicatedOptions = ref([]);
// Dedicated All / Yes / No set for the Period Locked? and Location Fee Paid?
// filters — kept separate from the shared booleanOptions so the labels stay
// exactly "Yes" / "No".
const lockedPaidOptions = ref([]);
const locationTypeOptions = ref([]);
const operatorOptions = ref([]);
const tagOptions = ref([]);
const vendPrefixOptions = ref([]);
const numberPerPageOptions = ref([]);
const periodReportLocalOptions = ref([]);
const contractCommissionTypeLocalOptions = ref([]);

onMounted(() => {
  // 5-value Site Status — comes from the controller (Customer::STATUSES_MAPPING
  // with an "All" sentinel prepended), labelled `name` server-side and remapped
  // to `value` here for the MultiSelect `label` prop.
  statusOptions.value = (props.statuses ?? []).map((s) => ({ id: s.id, value: s.name }));
  booleanOptions.value = [
    { id: 'all', value: 'All' },
    { id: 'true', value: 'Yes' },
    { id: 'false', value: 'No' },
  ];
  contractAttachmentOptions.value = [
    { id: 'all', value: 'All' },
    { id: 'true', value: 'Yes' },
    { id: 'false', value: 'No' },
  ];
  replicatedOptions.value = [
    { id: 'all', value: 'All' },
    { id: 'false', value: 'No' },
    { id: 'true', value: 'Changes only' },
  ];
  lockedPaidOptions.value = [
    { id: 'all', value: 'All' },
    { id: 'true', value: 'Yes' },
    { id: 'false', value: 'No' },
  ];
  numberPerPageOptions.value = [
    { id: 100, value: 100 },
    { id: 200, value: 200 },
    { id: 500, value: 500 },
    { id: 'All', value: 'All' },
  ];
  filters.value.numberPerPage = numberPerPageOptions.value[0];

  tagOptions.value = (props.tags?.data ?? []).map((d) => ({ id: d.id, name: d.name }));
  locationTypeOptions.value = [
    { id: 'all', value: 'All' },
    ...(props.locationTypeOptions?.data ?? []).map((d) => ({ id: d.id, value: d.name })),
  ];
  operatorOptions.value = [
    { id: 'all', full_name: 'All' },
    ...props.operatorOptions.data.map((data) => {
      return { id: data.id, code: data.code, full_name: data.full_name };
    }),
  ];
  vendPrefixOptions.value = [
    { id: 'single-ud', value: 'Single UD' },
    ...(props.vendPrefixOptions?.data ?? []).map((d) => ({ id: d.id, value: d.name })),
  ];

  periodReportLocalOptions.value = (props.periodReportOptions ?? []).map((opt) => ({
    id: opt.id,
    value: opt.value,
  }));

  contractCommissionTypeLocalOptions.value = (props.contractCommissionTypeOptions ?? []).map((opt) => ({
    id: opt.id,
    value: opt.value,
  }));

  // Defaults — Site Status opens on "Active" (id=2) to match the prior
  // is_active=true default; mirrors Customer/Index.vue's behaviour.
  filters.value.status = statusOptions.value.find((s) => s.id === 2) ?? statusOptions.value[0];
  filters.value.is_cms = booleanOptions.value[0];
  // Contract Attachment? defaults to "All" (no filter).
  filters.value.contract_attachment = contractAttachmentOptions.value[0];
  filters.value.replicated_only = replicatedOptions.value[0];
  filters.value.period_locked = lockedPaidOptions.value[0];
  filters.value.location_fee_paid = lockedPaidOptions.value[0];
  filters.value.location_types = [locationTypeOptions.value.find((o) => o.id === 'all')].filter(Boolean);
  // Operator default — mirrors Vend/CustomerIndex.vue:
  //   - logged in user: pre-select their own operator
  //   - HIPL admins additionally pre-select HIMD / LEA / HIESG / UL-ST
  //   - no auth (unlikely): fall back to the first option ("All")
  filters.value.operators = authOperator ? [
    operatorOptions.value.find((operator) => operator.id === authOperator.id),
    ...authOperator.code == 'HIPL' ? [
      operatorOptions.value.find((operator) => operator.code == 'HIMD'),
      operatorOptions.value.find((operator) => operator.code == 'LEA'),
      operatorOptions.value.find((operator) => operator.code == 'HIESG'),
      operatorOptions.value.find((operator) => operator.code == 'UL-ST'),
    ] : [],
  ].filter((operator) => operator !== undefined) : [operatorOptions.value[0]];
  filters.value.period_report =
    periodReportLocalOptions.value.find((o) => o.id === (props.periodReport || 'current'))
    ?? periodReportLocalOptions.value[0];
  // NOTE: no re-fetch on mount — the server already renders with the same
  // default operator set (see CustomerController::summary), so the first paint
  // matches these chips with no flash.
});

// Badge colour for the Site Status badge in the Site column.
// Mirrors Customer/Index.vue's convention (Active=green, Inactive=red,
// everything else amber) but in the bordered badge style used by the
// Contract / No Contract badges in the same cell.
function siteStatusBadgeClass(statusId) {
  switch (statusId) {
    case 2:  return 'bg-green-100 text-green-800 border-green-300';   // Active
    case 1:  return 'bg-red-100 text-red-800 border-red-300';         // Inactive
    default: return 'bg-amber-100 text-amber-800 border-amber-300';   // Pending / New / Potential
  }
}

function refIdFor(customer) {
  // Site model adds ref_id mutator (id + 20000) but pivots may not include it.
  return customer.ref_id ?? (customer.id ? customer.id + 20000 : '');
}

function formatYearMonth(d) {
  if (!d) return '';
  return moment(d).format('YYYY-MM');
}

// Match the screenshot's compact YYMM ("2604") format for the Period Report column.
function formatYYMM(d) {
  if (!d) return '';
  return moment(d).format('YYMM');
}

// For "Current" the cell shows YYMM (single month). For aggregated periods
// (Last 12 months / All) the cell shows the YYMM range, e.g. "2505 → 2604".
function periodReportLabel(row) {
  if (!row || !row.period_start || !row.period_end) return '';
  const start = moment(row.period_start).format('YYMM');
  const end = moment(row.period_end).format('YYMM');
  return start === end ? start : (start + ' → ' + end);
}

// Match the screenshot's compact YYMMDD ("260401") format for period start/end.
function formatYYMMDD(d) {
  if (!d) return '';
  return moment(d).format('YYMMDD');
}

// Compact datetime — same shape as the lock/paid tooltip uses (`YYMMDD HH:mm`).
// Used by the inline captions under the Lock cell icons so the user sees the
// most recent action timestamp right after clicking, without hovering.
function formatYYMMDDHM(d) {
  if (!d) return '';
  return moment(d).format('YYMMDD HH:mm');
}

function formatDate(d) {
  if (!d) return '';
  return moment(d).format('YYYY-MM-DD');
}

// Maps the contract type code to the human-readable label
// (mirrors Customer/Edit.vue's commissionTypeOptions).
function contractTypeLabel(type) {
  switch (type) {
    case 'F':     return 'Free Placement';
    case 'S':     return 'Subsidized Plan';
    case 'R':     return 'Fix Rental';
    case 'U':     return 'Utility only';
    case 'R+U':   return 'R + U';
    case 'PS':    return 'PS';
    case 'PS+U':  return 'PS + U';
    case 'PSORU': return 'PS OR U';
    default:      return type ?? '';
  }
}

function formatMoney(cents) {
  if (cents == null) return '';
  const exp = operatorCountry?.currency_exponent ?? 2;
  const sym = operatorCountry?.currency_symbol ?? '$';
  const value = Number(cents) / Math.pow(10, exp);
  const showExp = !operatorCountry?.is_currency_exponent_hidden;
  return sym + value.toLocaleString(undefined, {
    minimumFractionDigits: showExp ? exp : 0,
    maximumFractionDigits: showExp ? exp : 0,
  });
}

function formatMoneySigned(cents) {
  if (cents == null) return '';
  const sign = Number(cents) < 0 ? '-' : '';
  return sign + formatMoney(Math.abs(Number(cents)));
}

function formatPercent(rate) {
  if (rate == null) return '';
  return (Number(rate) * 100).toFixed(1) + '%';
}

/**
 * Gross Earning Rate for the Site Summary table.
 *
 * Mirrors the in-cell formula spec from the feedback screenshot:
 *   Rate = gross_earning (excl GST) / sales (excl GST)
 *
 * sales_cents is stored gross of GST, so for operators with a non-zero
 * gst_vat_rate we de-gross by dividing by (1 + gst%/100) — same
 * derivation the Sales column already uses for its excl-GST sub-line.
 * For non-GST operators sales_cents is already excl-GST.
 *
 * Returns null when sales-excl-GST is zero/missing so formatPercent()
 * renders an empty cell rather than NaN%/Infinity%.
 */
function grossEarningRate(row) {
  if (!row || row.gross_earning_cents == null || row.sales_cents == null) return null;
  const sales = Number(row.sales_cents);
  if (!sales) return null;
  const gst = row.customer && row.customer.operator
    ? Number(row.customer.operator.gst_vat_rate) || 0
    : 0;
  const salesExclGst = gst > 0 ? sales / (1 + gst / 100) : sales;
  if (!salesExclGst) return null;
  return Number(row.gross_earning_cents) / salesExclGst;
}

function locationFeesColorClass(cents, type) {
  // Negative = subsidy income (green); positive = expense (gray neutral).
  if (Number(cents) < 0 || type === 'S') return 'text-emerald-700 font-medium';
  return 'text-gray-800';
}

/**
 * External Subsidize for a Summary row, in cents.
 *
 * Reads the per-period snapshot (external_subsidize_cents) stored on the
 * summary row by CustomerSummaryAggregator — NOT the live customer value —
 * so historical months stay locked to the subsidy that applied at the time
 * (and it matches the NET location_earning_cents / Vend Earning shown).
 */
function externalSubsidizeCents(row) {
  return row && row.external_subsidize_cents != null ? Number(row.external_subsidize_cents) : 0;
}

/**
 * Net Loc Fee = Location Fees − External Subsidize (both in cents).
 */
function netLocFeeCents(row) {
  return Number(row.location_fees_cents || 0) - externalSubsidizeCents(row);
}

/* -------------------------------------------------------------------------
 * Month-over-month trend arrows.
 *
 * For a customer with MORE THAN ONE period row visible (i.e. the multi-month
 * "Last N months" / "All" reports), each metric shows a small heroicon next
 * to its value comparing this month against the customer's PREVIOUS month
 * shown on the page:
 *   value increased → green up arrow
 *   value decreased → red down arrow
 *   unchanged       → grey neutral dash
 * Sites with a single visible row (e.g. the "Current" report) fall back
 * to the server-provided previous-month snapshot (row.prev_month) so the
 * current month still shows arrows; only customers with no prior month on
 * record show no indicator.
 *
 * "Previous month shown" is resolved by grouping the visible rows per
 * customer and ordering them by year_month ascending, then taking the row
 * immediately before this one — independent of whatever column the user has
 * sorted the table by, so the comparison is always chronological.
 * ---------------------------------------------------------------------- */

// customer_id -> array of that customer's visible rows, ascending by year_month.
const monthRowsByCustomer = computed(() => {
  const map = {};
  (props.summaries?.data ?? []).forEach((r) => {
    const cid = r.customer_id ?? r.customer?.id;
    if (cid == null) return;
    (map[cid] = map[cid] || []).push(r);
  });
  Object.values(map).forEach((list) => {
    list.sort((a, b) => new Date(a.year_month) - new Date(b.year_month));
  });
  return map;
});

// The row for the same customer's immediately-older visible month, or null.
//
// When there's no older month VISIBLE on the page (e.g. the single-month
// "Current" report shows just one row per customer), we fall back to the
// server-provided previous-month snapshot (row.prev_month) so the current
// month still shows month-over-month trend arrows. The customer relation is
// carried over so the GST-dependent getters (grossEarningRate) resolve — it's
// the same customer/operator, only the cent figures differ.
function prevMonthRow(row) {
  const cid = row.customer_id ?? row.customer?.id;
  if (cid == null) return null;
  const list = monthRowsByCustomer.value[cid] || [];
  const idx = list.indexOf(row);
  if (idx > 0) return list[idx - 1];
  if (row.prev_month) {
    return { ...row.prev_month, customer: row.customer };
  }
  return null;
}

// Direction of a metric vs the previous month shown:
//   'up' | 'down' | 'same' | null (no previous month / value missing).
function trendDir(row, getter) {
  const prev = prevMonthRow(row);
  if (!prev) return null;
  const cur = getter(row);
  const old = getter(prev);
  if (cur == null || old == null) return null;
  if (cur > old) return 'up';
  if (cur < old) return 'down';
  return 'same';
}

// Per-metric convenience wrappers (used directly in the template).
function trendSales(row)      { return trendDir(row, (r) => Number(r.sales_cents)); }
function trendGross(row)      { return trendDir(row, (r) => Number(r.gross_earning_cents)); }
function trendGrossRate(row)  { return trendDir(row, (r) => grossEarningRate(r)); }
// Location Fees / Net Loc Fee intentionally show no trend arrow (per request).
function trendVend(row)       { return trendDir(row, (r) => Number(r.location_earning_cents)); }

/**
 * Small inline trend indicator. Functional component so it can be dropped
 * next to any value: <TrendIcon :dir="trendSales(row)" />. Renders nothing
 * when dir is null (single-row customer / no previous month).
 */
const TrendIcon = (props) => {
  const dir = props.dir;
  // Reserve the same footprint when there's nothing to show, so the value to
  // its left stays right-aligned in line with arrow-bearing rows — the arrow
  // (when present) always sits in a fixed slot at the far right of the cell.
  if (!dir) {
    return h('span', { class: 'inline-block h-4 w-4 ml-0.5 align-middle shrink-0' });
  }
  const color = dir === 'up'
    ? 'text-green-600'
    : (dir === 'down' ? 'text-red-600' : 'text-gray-400');
  // Custom thick-stroke arrow (stroke-width 3) — much more visible than the
  // thin solid heroicons. Up / down arrows; a thick dash for "no change".
  const d = dir === 'up'
    ? 'M10 16 V5 M5.5 10 L10 5 L14.5 10'
    : (dir === 'down'
      ? 'M10 4 V15 M5.5 10 L10 15 L14.5 10'
      : 'M5 10 H15');
  return h('svg', {
    viewBox: '0 0 20 20',
    fill: 'none',
    stroke: 'currentColor',
    'stroke-width': 3,
    'stroke-linecap': 'round',
    'stroke-linejoin': 'round',
    xmlns: 'http://www.w3.org/2000/svg',
    class: ['inline-block h-4 w-4 ml-0.5 align-middle shrink-0', color],
  }, [h('path', { d })]);
};
TrendIcon.props = ['dir'];

/**
 * Contract End Date colour rule for the Summary page.
 *
 * Red ONLY when contract_until is already in the past (strictly before
 * today's date — already expired). Contracts ending today or any future
 * date render in black ("not due yet"). Compared at day-boundary so the
 * time-of-day on either side doesn't flip the colour.
 */
function contractEndDateClass(d) {
  if (!d) return 'text-gray-900';
  const until = moment(d).startOf('day');
  if (!until.isValid()) return 'text-gray-900';
  const today = moment().startOf('day');
  return until.isBefore(today) ? 'text-red-600 font-semibold' : 'text-gray-900';
}

// Location Grading — three char(1) picks (placement/access/flexibility)
// captured on Customer/Edit.vue. Render style mirrors Vend/CustomerIndex.vue:
// a single "A, B, C" string with a tooltip explaining the order.
function hasAnyLocationGrading(customer) {
  const g = customer?.location_grading;
  if (!g) return false;
  return !!(g.placement || g.access || g.flexibility);
}

/**
 * Email Performance Report — modal-only action on a LOCKED row.
 *
 * Composes a `mailto:` from the currently-open Report Content modal so the
 * operator's own mail client (Outlook/Gmail/Apple Mail/etc.) sends the
 * message — we don't dispatch a queued mailable any more. After firing the
 * mailto we POST to /customers/{id}/send-performance-report (now an
 * audit-only endpoint) to stamp `report_emailed_at` / `report_emailed_by`
 * on the summary row, so we have a record of who clicked send for which
 * (customer, period). The "Last sent by X at Y" line in the modal reflects
 * that audit and updates optimistically here so the user sees feedback even
 * before Inertia re-fetches.
 */
function onModalEmailClicked() {
  const row = reportContentRow.value;
  const cust = row?.customer;
  const content = reportContent.value;
  if (!row || !cust) return;
  if (!cust.is_report_email_enabled || !cust.report_email) return;
  if (!row.is_locked) return; // server re-checks; this is just defensive

  const customerId = cust.id;
  if (sendingReportFor.value.has(customerId)) return;

  // Build the mailto. Subject mirrors the modal title + period label so the
  // recipient can tell at a glance what's inside. Body is a plain-text
  // rendering of the same content the modal shows — title banner → meta →
  // calculation lines (only customer-facing ones; admin-only formula lines
  // are skipped so we don't leak internal math) → total → footnote.
  const periodLabel = content?.period_label || (row.period_start + ' → ' + row.period_end);
  const subject = `Vending Machine Location Fees Report — ${cust.name || ('#' + customerId)} (${periodLabel})`;

  const lines = [];
  lines.push('Vending Machine Location Fees Report');
  if (content?.contract_type_label) lines.push(content.contract_type_label);
  lines.push('');
  lines.push(`Period (YYMM): ${periodLabel}`);
  if (content?.active_days != null && content?.month_days != null) {
    lines.push(`Total number of days: ${content.active_days} / ${content.month_days}`);
  }
  if (reportContentMachineId.value) lines.push(`Machine ID: ${reportContentMachineId.value}`);
  if (reportContentMachinePrefix.value) lines.push(`Machine Prefix: ${reportContentMachinePrefix.value}`);
  lines.push('');
  if (Array.isArray(content?.lines)) {
    for (const l of content.lines) {
      if (l?.formula_internal) continue; // admin-only — never in the email body
      lines.push(`${l.label}: ${l.formula ? l.formula + ' = ' : ''}${l.value}`);
    }
  }
  if (content?.has_total) {
    lines.push('');
    lines.push(`Total: ${content.total_value}`);
  }
  if (content?.footnote) {
    lines.push('');
    lines.push(content.footnote);
  }
  const body = lines.join('\r\n');

  const mailto = `mailto:${encodeURIComponent(cust.report_email)}`
    + `?subject=${encodeURIComponent(subject)}`
    + `&body=${encodeURIComponent(body)}`;

  // Optimistic update so the modal's "Last sent by …" line reflects the click
  // immediately. The authoritative timestamp + user come back in the axios
  // response below and replace this in-place.
  sendingReportFor.value.add(customerId);
  const originalRow = row;
  const optimisticNow = moment().format('YYYY-MM-DD HH:mm:ss');
  reportContentRow.value = {
    ...row,
    report_emailed_at: optimisticNow,
    report_emailed_by_user: row.report_emailed_by_user
      ?? { id: usePage().props.auth?.user?.id, name: usePage().props.auth?.user?.name },
  };

  // Fire the audit POST FIRST via axios — not Inertia router.post — so the
  // request is dispatched synchronously and is in-flight before we hand off
  // to the mail client. Axios is also unaffected by the brief navigation
  // some browsers do for `mailto:`. The endpoint returns JSON for AJAX
  // callers, so we can update the audit line authoritatively from the
  // response without a full page re-fetch.
  window.axios.post(
    '/customers/' + customerId + '/send-performance-report',
    { period_start: row.period_start, period_end: row.period_end },
  )
    .then((res) => {
      const data = res?.data || {};
      // Only mutate if the same row is still open — the user may have closed
      // the modal between firing and the response landing.
      if (reportContentRow.value && reportContentRow.value.customer?.id === customerId) {
        reportContentRow.value = {
          ...reportContentRow.value,
          report_emailed_at: data.report_emailed_at || reportContentRow.value.report_emailed_at,
          report_emailed_by_user: data.report_emailed_by_user || reportContentRow.value.report_emailed_by_user,
        };
      }
      toast.success(data.message || 'Send recorded.', { timeout: 3500 });
    })
    .catch((err) => {
      // Roll back the optimistic update so the audit line reflects truth.
      if (reportContentRow.value && reportContentRow.value.customer?.id === customerId) {
        reportContentRow.value = originalRow;
      }
      const msg = err?.response?.data?.message
        || err?.response?.data?.errors?.send_performance_report?.[0]
        || 'Failed to record the send. Please try again.';
      toast.error(msg, { timeout: 4500 });
    })
    .finally(() => {
      sendingReportFor.value.delete(customerId);
    });

  // Now hand off to the mail client. The audit POST above is already in
  // flight, so even if a particular browser treats `mailto:` as a
  // navigation it can no longer cancel the audit request.
  window.location.href = mailto;
}

function onSearchFilterUpdated() {
  // New search → new row set; drop any batch selection so stale ids from
  // the previous result set can't linger.
  clearBatchSelection();
  router.get(
    '/customers/summary',
    {
      ref_id: filters.value.ref_id,
      vend_code: filters.value.vend_code,
      customer: filters.value.customer,
      tags: (filters.value.tags ?? []).map ? filters.value.tags.map(t => t.id ?? t) : filters.value.tags,
      is_cms: filters.value.is_cms?.id,
      status: filters.value.status?.id,
      location_types: (filters.value.location_types ?? []).map((lt) => lt.id),
      operators: (filters.value.operators ?? []).filter(Boolean).map((o) => o.id),
      vendPrefixes: (filters.value.vendPrefixes ?? []).map((vp) => vp.id),
      period_report: filters.value.period_report?.id || 'current',
      replicated_only: filters.value.replicated_only?.id,
      period_locked: filters.value.period_locked?.id,
      location_fee_paid: filters.value.location_fee_paid?.id,
      paid_date_from: filters.value.paid_date_from,
      paid_date_to: filters.value.paid_date_to,
      // Marks this as an explicit user search so the server does NOT re-apply
      // the initial-load operator default — lets "deselect all operators" mean
      // "show all" instead of snapping back to the default set.
      searched: 1,
      // MultiSelect in tags-mode emits an array of selected ids — pass through
      // as-is. Empty array = no filter (treated as "all" by the controller).
      contract_commission_types: (filters.value.contract_commission_types ?? [])
        .map((t) => (t && t.id !== undefined ? t.id : t)),
      contract_attachment: filters.value.contract_attachment?.id,
      sortKey: filters.value.sortKey,
      sortBy: filters.value.sortBy,
      numberPerPage: filters.value.numberPerPage?.id ?? filters.value.numberPerPage,
    },
    { preserveState: true, replace: true }
  );
}

function resetFilters() {
  router.get('/customers/summary');
}

// Persist the inline-edited customer-level Note. Mirrors the
// onRemarksChanged setup on Vend/ProductAvailability.vue — POST to a
// dedicated endpoint, then router.reload only the `summaries` prop so
// the audit line (last updated by / at) refreshes without resetting the
// rest of the page state (filters, scroll, etc.).
function onNotesChanged(customer) {
  if (!customer?.id) return;
  axios.post('/customers/' + customer.id + '/update-notes', {
    notes: customer.notes,
  })
    .then(() => {
      router.reload({ only: ['summaries'], preserveScroll: true });
    })
    .catch((error) => {
      console.error('Error updating customer notes:', error);
    });
}

// Auto-grow the Notes textarea so the full content is visible without
// scrolling inside the cell. Called both via :ref-callback (initial mount
// + when summaries reload swaps row instances) and on every keystroke
// via @input. nextTick ensures the textarea has its final value applied
// to the DOM before we measure scrollHeight.
function autoGrowTextarea(el) {
  if (!el) return;
  nextTick(() => {
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
  });
}

function sortTable(sortKey) {
  filters.value.sortKey = sortKey;
  filters.value.sortBy = !filters.value.sortBy;
  onSearchFilterUpdated();
}

// Tooltip text for a locked row's closed-lock icon: who locked it and when.
// If this row has a prior Unlock event (last_unlocked_at — the row was
// unlocked and re-locked at some point), append "last unlocked by X (Y)" so
// the user can still see the most recent reversal at a glance.
function lockedTooltip(row) {
  if (!row?.is_locked) return '';
  const who = row.locked_by_user?.name || 'someone';
  const when = row.locked_at ? moment(row.locked_at).format('YYMMDD hh:mma') : '';
  let tip = when ? `Locked by ${who} (${when})` : `Locked by ${who}`;
  if (row.last_unlocked_at) {
    const ulWho = row.last_unlocked_by_user?.name || 'someone';
    const ulWhen = moment(row.last_unlocked_at).format('YYMMDD hh:mma');
    tip += `\nLast unlocked by ${ulWho} (${ulWhen})`;
  }
  return tip;
}

// Tooltip for the green check (Paid) icon: who marked Paid + when, plus the
// most recent Unpaid event if there was one.
function paidTooltip(row) {
  if (!row?.is_paid) return '';
  const who = row.paid_by_user?.name || 'someone';
  const when = row.paid_at ? moment(row.paid_at).format('YYMMDD hh:mma') : '';
  let tip = when ? `Paid by ${who} (${when})` : `Paid by ${who}`;
  if (row.paid_date) {
    tip += `\nPayment date: ${moment(row.paid_date).format('YYMMDD')}`;
  }
  if (row.last_unpaid_at) {
    const upWho = row.last_unpaid_by_user?.name || 'someone';
    const upWhen = moment(row.last_unpaid_at).format('YYMMDD hh:mma');
    tip += `\nLast unpaid by ${upWho} (${upWhen})`;
  }
  return tip;
}

// Lock a completed month's row — freezes its figures + contract details at
// this moment. Confirms first; reloads only the summaries prop on success.
function onLockClicked(row) {
  if (!row?.id || row.is_current_month || row.is_locked) return;
  if (lockingFor.value.has(row.id)) return;

  const label = row.customer?.name || ('#' + row.customer?.id);
  const ok = confirm(
    `Lock ${label} — ${periodReportLabel(row)}?\n\nThe contract details, figures and accumulated vend earning for this period will be frozen at their current values.`
  );
  if (!ok) return;

  lockingFor.value.add(row.id);
  // Partial reload: refresh ONLY summaries (rows) + totals (aggregate boxes
  // that sum stored values) so the locked snapshot's figures appear in
  // lockstep with the row's frozen state. Filters, dropdown options, and
  // other props stay as-is — no full page refresh, no scroll jump.
  router.post('/customers/summary/' + row.id + '/lock', {}, {
    only: ['summaries', 'totals'],
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => toast.success('Period locked.', { timeout: 3000 }),
    onError: (errors) => toast.error(errors?.lock || 'Failed to lock period.', { timeout: 4000 }),
    onFinish: () => lockingFor.value.delete(row.id),
  });
}

// Unlock a locked row — reverts it to live re-derivation. Superadmin/admin
// only (UI is gated by canUnlock; the server re-checks). Server also blocks
// unlocking a Paid row (must Unpaid first); the UI already disables the
// button in that state but we keep the early-return + error path so a stale
// tab can't slip through.
function onUnlockClicked(row) {
  if (!row?.id || !row.is_locked) return;
  if (row.is_paid) return;
  if (lockingFor.value.has(row.id)) return;

  const label = row.customer?.name || ('#' + row.customer?.id);
  const ok = confirm(
    `Unlock ${label} — ${periodReportLabel(row)}?\n\nThis period will go back to live figures based on the customer's current contract.`
  );
  if (!ok) return;

  lockingFor.value.add(row.id);
  // Partial reload — same rationale as onLockClicked. Unlock flips the row
  // back to live re-derivation, so the displayed row figures + totals can
  // shift; both summaries + totals are refreshed in lockstep.
  router.post('/customers/summary/' + row.id + '/unlock', {}, {
    only: ['summaries', 'totals'],
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => toast.success('Period unlocked.', { timeout: 3000 }),
    onError: (errors) => toast.error(errors?.unlock || 'Failed to unlock period.', { timeout: 4000 }),
    onFinish: () => lockingFor.value.delete(row.id),
  });
}

// Paid popup state — the Paid button opens a modal that captures the ACTUAL
// payment date (paid_date) instead of firing straight away. The field is
// pre-filled with today; emptied/cleared → today is used (server defaults
// too, so both paths agree).
const showPaidModal = ref(false);
const paidModalRow = ref(null);
const paidModalDate = ref('');

const paidModalLabel = computed(() => {
  const row = paidModalRow.value;
  if (!row) return '';
  const label = row.customer?.name || ('#' + row.customer?.id);
  return `${label} — ${periodReportLabel(row)}`;
});

// Mark a locked row as Paid — STEP 1: open the payment-date popup. Requires
// the row to be Locked + not already Paid (UI hides the button otherwise).
function onPaidClicked(row) {
  if (!row?.id || !row.is_locked || row.is_paid) return;
  if (lockingFor.value.has(row.id)) return;
  paidModalRow.value = row;
  paidModalDate.value = moment().format('YYYY-MM-DD'); // pre-fill today
  showPaidModal.value = true;
}

function onPaidModalClose() {
  showPaidModal.value = false;
  paidModalRow.value = null;
}

// STEP 2: confirm from the popup. Same permission as Lock; server re-checks.
// Reuses lockingFor to share the per-row spinner with Lock/Unlock.
function onPaidModalConfirm() {
  const row = paidModalRow.value;
  if (!row?.id || !row.is_locked || row.is_paid) return;
  if (lockingFor.value.has(row.id)) return;

  // Empty/cleared field → today (server defaults too — belt and braces).
  const paidDate = paidModalDate.value || moment().format('YYYY-MM-DD');

  showPaidModal.value = false;
  paidModalRow.value = null;

  lockingFor.value.add(row.id);
  // Partial reload — Paid only flips paid_at / paid_date; row money figures
  // + totals are unaffected. Refreshing summaries is enough to bring back
  // the new paid_at / paid_date / paid_by_user so the green-check icon +
  // "Paid by X (Y)" tooltip render immediately on success.
  router.post('/customers/summary/' + row.id + '/paid', { paid_date: paidDate }, {
    only: ['summaries'],
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => toast.success('Period marked Paid.', { timeout: 3000 }),
    onError: (errors) => toast.error(errors?.paid || errors?.paid_date || 'Failed to mark Paid.', { timeout: 4000 }),
    onFinish: () => lockingFor.value.delete(row.id),
  });
}

// Reverse Paid (mirror of onPaidClicked). Superadmin/admin only; server
// re-checks. Stamps last_unpaid_at / last_unpaid_by for the audit trail.
function onUnpaidClicked(row) {
  if (!row?.id || !row.is_paid) return;
  if (lockingFor.value.has(row.id)) return;

  const label = row.customer?.name || ('#' + row.customer?.id);
  const ok = confirm(
    `Mark ${label} — ${periodReportLabel(row)} as Unpaid?\n\nClears the paid timestamp. After this, the Unlock button will be available again.`
  );
  if (!ok) return;

  lockingFor.value.add(row.id);
  // Partial reload — Unpaid only clears paid_at + stamps last_unpaid_*;
  // money figures + totals are unaffected. Summaries refresh brings back
  // is_paid=false and last_unpaid_at, so the Unlock button immediately
  // re-enables and the tooltip on the next Paid cycle will surface the
  // most recent Unpaid event.
  router.post('/customers/summary/' + row.id + '/unpaid', {}, {
    only: ['summaries'],
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => toast.success('Period marked Unpaid.', { timeout: 3000 }),
    onError: (errors) => toast.error(errors?.unpaid || 'Failed to mark Unpaid.', { timeout: 4000 }),
    onFinish: () => lockingFor.value.delete(row.id),
  });
}

// Build the param payload for backend calls (search + export). Centralised
// so the export uses the same filter set the page is currently showing.
function buildBackendParams() {
  return {
    ref_id: filters.value.ref_id,
    vend_code: filters.value.vend_code,
    customer: filters.value.customer,
    tags: (filters.value.tags ?? []).map ? filters.value.tags.map(t => t.id ?? t) : filters.value.tags,
    is_cms: filters.value.is_cms?.id,
    status: filters.value.status?.id,
    location_types: (filters.value.location_types ?? []).map((lt) => lt.id),
    operators: (filters.value.operators ?? []).filter(Boolean).map((o) => o.id),
    vendPrefixes: (filters.value.vendPrefixes ?? []).map((vp) => vp.id),
    period_report: filters.value.period_report?.id || 'current',
    contract_commission_types: (filters.value.contract_commission_types ?? [])
      .map((t) => (t && t.id !== undefined ? t.id : t)),
    contract_attachment: filters.value.contract_attachment?.id,
    replicated_only: filters.value.replicated_only?.id,
    period_locked: filters.value.period_locked?.id,
    location_fee_paid: filters.value.location_fee_paid?.id,
    paid_date_from: filters.value.paid_date_from,
    paid_date_to: filters.value.paid_date_to,
    sortKey: filters.value.sortKey,
    sortBy: filters.value.sortBy,
    numberPerPage: filters.value.numberPerPage?.id ?? filters.value.numberPerPage,
  };
}

function onMapAllMarkerClicked() {
  // MapMarker.vue expects camelCase customer.deliveryAddress with latitude/longitude/full_address.
  // Our resource emits snake_case delivery_address, so adapt before opening.
  mapCustomers.value = (props.summaries?.data ?? [])
    .filter((row) => row.customer && row.customer.delivery_address && row.customer.delivery_address.latitude && row.customer.delivery_address.longitude)
    .map((row, index) => ({
      sequence: index + 1,
      ...row.customer,
      deliveryAddress: row.customer.delivery_address,
    }));
  showMapMarkerModal.value = true;
}

// Open MapMarker for a single customer (per-row pin button next to the address).
function onSingleMapMarkerClicked(customer) {
  if (!customer || !customer.delivery_address) return;
  mapCustomers.value = [{
    sequence: 1,
    ...customer,
    deliveryAddress: customer.delivery_address,
  }];
  showMapMarkerModal.value = true;
}

function onMapMarkerModalClose() {
  showMapMarkerModal.value = false;
}

/**
 * Report Content preview — fetches the structured content from the
 * /customers/{id}/performance-report-content endpoint and shows it in a
 * modal. Same payload that the queued email body builder will use, so
 * "what you see is what gets sent".
 *
 * Defensive guard at top: even if the disabled state on the button gets
 * bypassed (keyboard, devtools), we refuse to fire when has_report_content
 * is false — saves a 200 OK that just renders "no content".
 */
function onReportContentClicked(row) {
  const cust = row?.customer;
  if (!cust?.has_report_content) return;

  reportContent.value = null;
  reportContentRow.value = row;
  reportContentCustomerLabel.value = cust.name || ('#' + cust.id);

  // Machine ID + Prefix — mirror the on-screen Vend ID column logic:
  // join all bound vends for customers with multiple machines, fall back
  // to the single primary vend otherwise. Empty string when neither path
  // resolves so the template can hide the section gracefully.
  if (Array.isArray(cust.vends) && cust.vends.length > 1) {
    reportContentMachineId.value = cust.vends.map(v => v.code).filter(Boolean).join(', ');
    reportContentMachinePrefix.value = cust.vends.map(v => v.prefix).filter(Boolean).join(', ');
  } else {
    reportContentMachineId.value = cust.vend?.code || '';
    reportContentMachinePrefix.value = cust.vend?.prefix || '';
  }

  showReportContentModal.value = true;
  reportContentLoading.value = true;

  window.axios.get('/customers/' + cust.id + '/performance-report-content', {
    params: {
      period_start: row.period_start,
      period_end: row.period_end,
    },
  }).then(res => {
    reportContent.value = res?.data ?? null;
  }).catch(err => {
    const msg = err?.response?.data?.message
      || 'Failed to load report content. Please try again.';
    toast.error(msg, { timeout: 4000 });
    showReportContentModal.value = false;
  }).finally(() => {
    reportContentLoading.value = false;
  });
}

function onReportContentModalClose() {
  showReportContentModal.value = false;
  reportContent.value = null;
  reportContentRow.value = null;
  reportContentCustomerLabel.value = '';
  reportContentMachineId.value = '';
  reportContentMachinePrefix.value = '';
}

function onExportExcelClicked() {
  loading.value = true;
  axios({
    method: 'get',
    url: '/customers/summary/excel',
    params: buildBackendParams(),
    responseType: 'blob',
  }).then(response => {
    fileDownload(response.data, 'CustomersSummary' + moment().format('YYMMDDHHmmss') + '.xlsx');
  }).catch(error => {
    console.log(error);
  }).finally(() => {
    loading.value = false;
  });
}

// ───────────────────────────────────────────────────────────────────────
// API Invoice (Site Summary > Action ▸ "Create API Invoice")
//
// Mirrors OpsJob's "Create API Invoice(s)" pattern but at the customer +
// period grain. The button is gated by:
//   1. Customer has a CMS person_id (CMS-linked).
//   2. Contract type is invoiceable (R, U, R+U, PS, PS+U, PSORU). F/S are out.
//   3. Contract values are complete (PerformanceReportContentService says
//      has_report_content = true).
// All three are satisfied via row.customer.has_report_content (server
// re-checks anyway, this is just for visual gating).
// ───────────────────────────────────────────────────────────────────────

function rowKey(row) {
  // Composite key matching the server's (customer_id, period_start, period_end)
  // — used both for selection state and per-row in-flight guards.
  return (row?.customer_id ?? row?.customer?.id ?? '?')
    + '|' + (row?.period_start ?? '')
    + '|' + (row?.period_end ?? '');
}

/**
 * "Multi-month" period reports return one row per (customer, month). The
 * controller orders rows so a customer's months cluster together. For
 * customer-level columns (Accumulate Vend Earning, Site Tag) we
 * only want to render content on the FIRST row of each customer's
 * cluster — the remaining rows in the cluster look intentionally blank
 * so the eye can read the group as a unit.
 *
 * "First row" = previous row had a different customer_id (or no previous
 * row at all). Robust against user sort changes because adjacency is
 * what we actually care about.
 */
function isFirstRowForCustomer(rowIndex) {
  const rows = props.summaries?.data ?? [];
  if (!rows.length || rowIndex === 0) return true;
  const cur = rows[rowIndex]?.customer_id ?? rows[rowIndex]?.customer?.id;
  const prev = rows[rowIndex - 1]?.customer_id ?? rows[rowIndex - 1]?.customer?.id;
  return cur !== prev;
}

/**
 * Group index of the row's customer in the current page. Site A's
 * rows return 0, Site B's rows return 1, Site C's rows return 2,
 * etc. Used by the tr :class binding to stripe alternating backgrounds
 * by CUSTOMER GROUP instead of per-row — so a customer's 12 monthly
 * rows share one background colour.
 *
 * Linear scan from the top of the visible page is fine: pagination caps
 * us at 100 rows by default, and the controller already clusters a
 * customer's rows together so the increment logic is correct.
 */
function customerGroupIndex(rowIndex) {
  const rows = props.summaries?.data ?? [];
  if (!rows.length) return 0;
  let group = 0;
  let prevCustomerId = rows[0]?.customer_id ?? rows[0]?.customer?.id;
  for (let i = 1; i <= rowIndex; i++) {
    const cur = rows[i]?.customer_id ?? rows[i]?.customer?.id;
    if (cur !== prevCustomerId) {
      group++;
      prevCustomerId = cur;
    }
  }
  return group;
}

function isInvoiceable(row) {
  // 1) CMS-linked + invoiceable contract type AND complete contract
  //    (mirrors CustomerInvoiceService::isInvoiceable on the backend).
  const cust = row?.customer;
  if (!cust) return false;
  if (!cust.person_id) return false;
  if (!cust.has_report_content) return false;
  const t = cust.contract_commission_type ?? row?.contract_commission_type;
  if (!t || t === 'F' || t === 'S') return false;
  return true;
}

function existingInvoice(row) {
  return row?.existing_invoice ?? null;
}

/**
 * Per-row Create button. Confirms intent (mentioning re-creation when an
 * invoice already exists for the same period) and POSTs to the single
 * endpoint. The page reloads via Inertia onSuccess so the badge updates.
 */
function onCreateApiInvoiceClicked(row) {
  if (!isInvoiceable(row)) return;

  const key = rowKey(row);
  if (creatingInvoiceFor.value.has(key)) return;

  const cust = row.customer;
  const existing = existingInvoice(row);
  const force = !!existing;

  const periodLine = `${row.period_start} → ${row.period_end}`;
  const msg = existing
    ? `An API Invoice already exists for ${cust.name}\n` +
      `Period: ${periodLine}\n` +
      `Existing transaction: #${existing.cms_transaction_id}\n\n` +
      `Re-create now? (a new invoice will be created in CMS)`
    : `Create API Invoice for ${cust.name}\n` +
      `Period: ${periodLine}\n\n` +
      `Proceed?`;

  if (!confirm(msg)) return;

  creatingInvoiceFor.value.add(key);
  router.post(
    '/customers/' + cust.id + '/cms-invoices',
    {
      period_start: row.period_start,
      period_end: row.period_end,
      force: force ? 1 : 0,
    },
    {
      preserveState: true,
      preserveScroll: true,
      onSuccess: (page) => {
        const flashed = page?.props?.flash?.success;
        toast.success(flashed || 'API Invoice queued.', { timeout: 3500 });
      },
      onError: (errors) => {
        const msg = errors?.sync_cms_invoice
          || errors?.period_start
          || errors?.period_end
          || 'Failed to queue API Invoice. Please try again.';
        toast.error(msg, { timeout: 4500 });
      },
      onFinish: () => {
        creatingInvoiceFor.value.delete(key);
      },
    }
  );
}

/**
 * Bulk submit — fires one POST with the array of selected (customer +
 * period) tuples. Server queues them independently. We force=1 for any
 * row that already has an existing invoice (the user has already
 * confirmed at the bulk level via the dialog below).
 */
function onBulkCreateApiInvoicesClicked() {
  const rows = (props.summaries?.data ?? [])
    .filter((r) => selectedRowKeys.value.includes(rowKey(r)))
    .filter((r) => isInvoiceable(r));

  if (!rows.length) {
    toast.error('No invoiceable rows selected.', { timeout: 3000 });
    return;
  }

  const reCreateCount = rows.filter((r) => existingInvoice(r)).length;
  const msg = reCreateCount > 0
    ? `Create API Invoice for ${rows.length} row(s)?\n` +
      `${reCreateCount} of these already have an existing invoice and will be RE-CREATED in CMS.\n\n` +
      `Proceed?`
    : `Create API Invoice for ${rows.length} row(s)?\n\nProceed?`;

  if (!confirm(msg)) return;

  bulkSubmitting.value = true;
  router.post(
    '/customers/cms-invoices/bulk',
    {
      items: rows.map((r) => ({
        customer_id: r.customer_id ?? r.customer?.id,
        period_start: r.period_start,
        period_end: r.period_end,
        force: existingInvoice(r) ? 1 : 0,
      })),
    },
    {
      preserveState: true,
      preserveScroll: true,
      onSuccess: (page) => {
        const flashed = page?.props?.flash?.success;
        toast.success(flashed || 'API Invoices queued.', { timeout: 4500 });
        // Clear selection — server has accepted.
        selectedRowKeys.value = [];
        bulkMode.value = false;
      },
      onError: (errors) => {
        const msg = errors?.sync_cms_invoice
          || errors?.items
          || 'Failed to queue API Invoices. Please try again.';
        toast.error(typeof msg === 'string' ? msg : 'Validation error.', { timeout: 4500 });
      },
      onFinish: () => {
        bulkSubmitting.value = false;
      },
    }
  );
}

function toggleSelectAllInvoiceable() {
  // Select-all toggles only invoiceable rows on the current page so the
  // user doesn't end up trying to invoice F/S/CMS-orphan rows.
  const allKeys = (props.summaries?.data ?? [])
    .filter((r) => isInvoiceable(r))
    .map((r) => rowKey(r));

  const allSelected = allKeys.length > 0
    && allKeys.every((k) => selectedRowKeys.value.includes(k));

  selectedRowKeys.value = allSelected ? [] : allKeys;
}

function isAllInvoiceableSelected() {
  const allKeys = (props.summaries?.data ?? [])
    .filter((r) => isInvoiceable(r))
    .map((r) => rowKey(r));
  if (!allKeys.length) return false;
  return allKeys.every((k) => selectedRowKeys.value.includes(k));
}

// Reactive helper exposed to the template — avoids re-computing per row
// when the existing_invoice cents need formatting via formatMoney().
function existingInvoiceAmountLabel(row) {
  const inv = existingInvoice(row);
  if (!inv || inv.total_amount_cents == null) return '';
  return formatMoney(inv.total_amount_cents);
}
</script>

<!--
  Header font size, one step smaller than the shared TableHead default
  (11px → 10px). Scoped to THIS page only so the shared TableHead.vue
  component — used across ~75 other tables — is left untouched.
  :deep() penetrates into the child TableHead's <th>; the attribute +
  element selector outranks Tailwind's text-[11px] utility class.
-->
<style scoped>
:deep(thead th) {
  font-size: 10px;
}
</style>
