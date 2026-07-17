<template>
  <Head title="Sites Summary" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex flex-col">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Site Summary (&amp; Communication on Cust Note)
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
          <SearchInput v-if="showAllFilters" placeholderStr="ID" v-model="filters.ref_id">Site ID</SearchInput>
          <SearchInput placeholderStr="ID" v-model="filters.vend_code">Machine ID</SearchInput>
          <SearchInput placeholderStr="Site" v-model="filters.customer">Site</SearchInput>
          <!-- Billing Company — moved behind "Show All Filters" (no longer a
               default filter); Site Status takes its place in the default row. -->
          <SearchInput v-if="showAllFilters" placeholderStr="Company" v-model="filters.billing_company">Billing Company</SearchInput>
          <!-- Hidden filter: locate the site owning a settlement ledger
               reference (e.g. LF-000351). Enter the FULL reference. -->
          <SearchInput v-if="showAllFilters" placeholderStr="e.g. LF-000351" v-model="filters.settlement_ref">Payment Ref</SearchInput>

          <!-- Site Status — now a DEFAULT filter (always visible). -->
          <div>
            <label class="block text-sm font-medium text-gray-700">Site Status</label>
            <MultiSelect
              v-model="filters.status"
              :options="statusOptions"
              trackBy="id" valueProp="id" label="value" mode="tags"
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

          <div v-if="showAllFilters && permissions.includes('admin-access customers') && cmsEndpoint">
            <label class="block text-sm font-medium text-gray-700">Is From CMS</label>
            <MultiSelect
              v-model="filters.is_cms"
              :options="booleanOptions"
              trackBy="id" valueProp="id" label="value"
              placeholder="Select" open-direction="bottom" class="mt-1"
            />
          </div>

          <div v-if="showAllFilters && permissions.includes('admin-access customers')">
            <label class="block text-sm font-medium text-gray-700">Operator</label>
            <MultiSelect
              v-model="filters.operators"
              :options="operatorOptions"
              trackBy="id" valueProp="id" label="full_name" mode="tags"
              placeholder="Select" open-direction="bottom" class="mt-1"
            />
          </div>

          <div v-if="showAllFilters">
            <label class="block text-sm font-medium text-gray-700">Machine Prefix</label>
            <MultiSelect
              v-model="filters.vendPrefixes"
              :options="vendPrefixOptions"
              trackBy="id" valueProp="id" label="value" mode="tags"
              placeholder="Select" open-direction="bottom" class="mt-1"
            />
          </div>

          <div v-if="showAllFilters && permissions.includes('admin-access customers')">
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

          <!--
            Custom Range month pickers — only shown when Period Report = "Custom
            Range". Month granularity (matches the monthly year_month data).
            Either bound may be left blank (the backend falls back to the
            current month); the range is clamped to the reporting floor and the
            current month server-side.
          -->
          <div v-if="filters.period_report?.id === 'custom'">
            <label class="block text-sm font-medium text-gray-700">
              Custom Range (From)
            </label>
            <input
              type="month"
              v-model="filters.period_from"
              class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-emerald-500 focus:ring-emerald-500"
            />
          </div>
          <div v-if="filters.period_report?.id === 'custom'">
            <label class="block text-sm font-medium text-gray-700">
              Custom Range (To)
            </label>
            <input
              type="month"
              v-model="filters.period_to"
              class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-emerald-500 focus:ring-emerald-500"
            />
          </div>

          <!-- Placement Contract Type filter (multi-select tags) -->
          <div v-if="showAllFilters">
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
          <div v-if="showAllFilters">
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
          <div v-if="showAllFilters">
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
          <div v-if="showAllFilters">
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
          <div v-if="showAllFilters">
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
          <div v-if="showAllFilters">
            <label class="block text-sm font-medium text-gray-700">
              Payment Date (From)
            </label>
            <input
              type="date"
              v-model="filters.paid_date_from"
              class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-sm focus:border-emerald-500 focus:ring-emerald-500"
            />
          </div>
          <div v-if="showAllFilters">
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
            <div class="flex flex-wrap items-center gap-2">
              <Button
                class="inline-flex space-x-1 items-center whitespace-nowrap rounded-md border border-green bg-green-500 px-4 py-2.5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600"
                @click="onSearchFilterUpdated"
              >
                <MagnifyingGlassIcon class="h-4 w-4" aria-hidden="true" />
                <span>Search</span>
              </Button>
              <!-- Show / Hide All Filters — by default only Machine ID, Site,
                   Site Status, Tags and Period Report show; this toggle reveals
                   the rest (incl. Billing Company).
                   Mirrors CustomerIndex.vue's showAllFilters button. -->
              <Button
                class="inline-flex space-x-1 items-center whitespace-nowrap rounded-md border border-green bg-gray-300 px-4 py-2.5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400"
                @click.prevent="onShowAllFiltersClicked()"
              >
                <span v-if="!showAllFilters" class="flex">
                  <ChevronDoubleDownIcon class="h-4 w-4" aria-hidden="true" />
                  Show
                </span>
                <span v-if="showAllFilters" class="flex">
                  <ChevronDoubleUpIcon class="h-4 w-4" aria-hidden="true" />
                  Hide
                </span>
                <span>All Filters</span>
              </Button>
              <Button
                class="inline-flex space-x-1 items-center whitespace-nowrap rounded-md border border-sky bg-sky-300 px-4 py-2.5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-sky-400"
                @click.prevent="onMapAllMarkerClicked"
                v-if="hasAnyAddressWithCoords"
              >
                <MapPinIcon class="h-4 w-4" aria-hidden="true" />
                <span>Show Map Markers</span>
              </Button>
              <Button type="button" class="inline-flex items-center whitespace-nowrap rounded-md bg-white px-4 py-2.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-400 hover:bg-gray-100"
                @click.prevent="onExportExcelClicked()">
                <div class="flex items-center space-x-1">
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
                Export Batch Report Content — stitches the Report Content of
                every ticked row into ONE client-facing email body (single
                greeting + each machine's block + single thank-you), shown in a
                modal with a Copy button. Disabled until at least one ticked row
                carries report content. Only meaningful when the checkbox column
                is present (canLock).
              -->
              <Button
                v-if="canLock"
                type="button"
                class="inline-flex items-center whitespace-nowrap rounded-md bg-white px-4 py-2.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-400 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed"
                :disabled="!selectedReportRows.length || batchReportLoading"
                v-tooltip="selectedReportRows.length ? 'Build a combined report email for the ticked machines' : 'Tick one or more rows that have Report Content first'"
                @click.prevent="onExportBatchReportClicked()"
              >
                <div class="flex items-center space-x-1">
                  <DocumentTextIcon v-if="!batchReportLoading" class="h-4 w-4" aria-hidden="true" />
                  <svg v-else aria-hidden="true" class="w-4 h-4 text-gray-200 animate-spin fill-green-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                    <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                  </svg>
                  <span>Export Batch Report Content{{ selectedReportRows.length ? ` (${selectedReportRows.length})` : '' }}</span>
                </div>
              </Button>
              <!-- Unread Site-Note toggle: shows only sites whose Site Note
                   changed (by someone else) since your last visit, newest
                   first. Badge count comes from the server. -->
              <Button
                type="button"
                :class="['inline-flex items-center gap-1.5 whitespace-nowrap rounded-md px-4 py-2.5 text-sm font-medium leading-4 shadow-sm transition-colors',
                  unreadMode ? 'bg-red-500 text-white hover:bg-red-600' : 'bg-white text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50']"
                @click.prevent="toggleUnread()"
              >
                <BellAlertIcon :class="['h-4 w-4', unreadMode ? 'text-white' : 'text-red-500']" aria-hidden="true" />
                <span>{{ unreadMode ? 'Show All' : 'Unread Cust Note' }}</span>
                <span v-if="props.unreadCount > 0"
                  :class="['inline-flex min-w-[18px] items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-semibold leading-none',
                    unreadMode ? 'bg-white/25 text-white' : 'bg-red-500 text-white']">
                  {{ props.unreadCount }}
                </span>
              </Button>
              <!-- @Me Mentioned toggle: shows only sites whose Site Note
                   @-mentions the current user, newest-updated first. Badge
                   count comes from the server (mentionCount). -->
              <Button
                type="button"
                :class="['inline-flex items-center gap-1.5 whitespace-nowrap rounded-md px-4 py-2.5 text-sm font-medium leading-4 shadow-sm transition-colors',
                  mentionMode ? 'bg-indigo-500 text-white hover:bg-indigo-600' : 'bg-white text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50']"
                @click.prevent="toggleMentioned()"
              >
                <AtSymbolIcon :class="['h-4 w-4', mentionMode ? 'text-white' : 'text-indigo-500']" aria-hidden="true" />
                <span>{{ mentionMode ? 'Show All' : 'me only' }}</span>
                <span v-if="props.mentionCount > 0"
                  :class="['inline-flex min-w-[18px] items-center justify-center rounded-full px-1.5 py-0.5 text-[11px] font-semibold leading-none',
                    mentionMode ? 'bg-white/25 text-white' : 'bg-red-500 text-white']">
                  {{ props.mentionCount }}
                </span>
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
        <!--
          Two-section totals row. LEFT holds the live aggregate cards (Sales /
          Gross / Location Fees / Vend Earnings + the two count cards),
          EXCLUDING Total Outstanding. RIGHT groups the settlement-side figures
          under a "Payment to Loc Fees" panel styled like the Ops dashboard
          KPI blocks: bordered card, header bar, stacked line items.
        -->
        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
          <!--
            LEFT: current aggregate totals (excl Total Outstanding) grouped into
            a SINGLE panel (mirrors the right "Payment to Loc Fees" card). The
            six metrics sit in an internal grid divided by light separators.
          -->
          <div class="lg:col-span-2 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 bg-gray-700 px-4 py-1.5">
              <h3 class="text-sm font-semibold text-white">Aggregate Totals</h3>
            </div>
            <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 divide-y divide-gray-100 sm:divide-y-0">
              <div class="px-4 py-2 sm:border-b sm:border-gray-100 sm:border-r">
                <dt class="truncate text-xs font-medium text-gray-500">Total Sales <span class="text-gray-400">w/ GST, (excl GST)</span></dt>
                <dd class="mt-0.5 text-lg font-semibold tracking-normal text-gray-900">
                  {{ formatMoney(totals.sales_cents) }}
                  <span v-if="totals.sales_excl_gst_cents != null && totals.sales_excl_gst_cents !== totals.sales_cents" class="text-xs font-normal text-indigo-600">( {{ formatMoney(totals.sales_excl_gst_cents) }} )</span>
                </dd>
              </div>
              <div class="px-4 py-2 sm:border-b sm:border-gray-100 lg:border-r">
                <dt class="truncate text-xs font-medium text-gray-500">Total Gross Earning</dt>
                <dd class="mt-0.5 text-lg font-semibold tracking-normal text-gray-900">
                  {{ formatMoney(totals.gross_earning_cents) }}
                  <span v-if="pctOfSales(totals.gross_earning_cents)" class="text-xs font-normal text-indigo-600">( {{ pctOfSales(totals.gross_earning_cents) }} )</span>
                </dd>
              </div>
              <div class="px-4 py-2 sm:border-b sm:border-gray-100 sm:border-r lg:border-r-0">
                <dt class="truncate text-xs font-medium text-gray-500">Total Location Fees</dt>
                <dd
                  class="mt-0.5 text-lg font-semibold tracking-normal"
                  :class="(totals.location_fees_cents || 0) < 0 ? 'text-emerald-700' : 'text-gray-900'"
                >
                  {{ formatMoneySigned(totals.location_fees_cents) }}
                  <span v-if="pctOfSales(totals.location_fees_cents)" class="text-xs font-normal text-indigo-600">( {{ pctOfSales(totals.location_fees_cents) }} )</span>
                </dd>
                <p v-if="totals.has_to_date_proration" class="text-[10px] text-sky-700">
                  Current month accrued to date
                </p>
              </div>
              <div class="px-4 py-2 sm:border-r">
                <dt class="truncate text-xs font-medium text-gray-500">Total Vend Earnings</dt>
                <dd
                  class="mt-0.5 text-lg font-semibold tracking-normal"
                  :class="(totals.location_earning_cents || 0) >= 0 ? 'text-gray-900' : 'text-red-700'"
                >
                  {{ formatMoney(totals.location_earning_cents) }}
                  <span v-if="pctOfSales(totals.location_earning_cents)" class="text-xs font-normal text-indigo-600">( {{ pctOfSales(totals.location_earning_cents) }} )</span>
                </dd>
                <p v-if="totals.has_to_date_proration" class="text-[10px] text-sky-700">
                  Current month accrued to date
                </p>
              </div>
              <!--
                Distinct-customer counts scoped to the same filtered customer
                set as the money totals above.
              -->
              <div class="px-4 py-2 lg:border-r">
                <dt class="truncate text-xs font-medium text-gray-500"># without Contract Attachment</dt>
                <dd class="mt-0.5 text-lg font-semibold tracking-normal text-gray-900">
                  {{ totals.no_contract_attachment_count || 0 }}
                </dd>
              </div>
              <div class="px-4 py-2">
                <dt class="truncate text-xs font-medium text-gray-500"># To Be Expired in 30ds</dt>
                <dd class="mt-0.5 text-lg font-semibold tracking-normal text-gray-900">
                  {{ totals.expiring_in_30d_count || 0 }}
                </dd>
                <dd class="text-[10px] italic text-gray-400">excludes auto-renewal contracts</dd>
              </div>
            </dl>
          </div>

          <!--
            RIGHT: Payment to Loc Fees panel — settlement-side figures, split into
            two halves. LEFT half = All-time totals across the filtered sites
            (cumulative ledger). RIGHT half = the SAME three figures but scoped to
            the Shown Period (the Period Report window). The metric labels live in
            a centre rail so both value columns stay row-aligned for easy compare.
          -->
          <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-200 bg-gray-700 px-4 py-1.5">
              <h3 class="text-sm font-semibold text-white">Payment to Loc Fees</h3>
            </div>
            <table class="w-full table-fixed">
              <thead>
                <tr class="border-b border-gray-200 bg-gray-50">
                  <th class="w-2/5 px-3 py-1 text-left align-bottom">
                    <span class="text-[10px] font-normal italic text-gray-500">owed to site owners</span>
                  </th>
                  <th class="w-[30%] px-3 py-1 text-center align-bottom">
                    <span class="block text-[11px] font-semibold uppercase tracking-wide text-gray-700">All-time</span>
                    <span class="block text-[10px] font-normal italic text-gray-500">excl current period</span>
                  </th>
                  <th class="w-[30%] border-l border-gray-200 px-3 py-1 text-center align-bottom">
                    <span class="block text-[11px] font-semibold uppercase tracking-wide text-indigo-600">Shown Period</span>
                    <span class="block text-[10px] font-normal italic text-gray-500">{{ ymd6(rangeStart) }} → {{ ymd6(rangeEnd) }}</span>
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr class="bg-gray-50/60">
                  <td class="px-3 py-1.5 text-xs font-semibold text-gray-700">Total Loc Fees</td>
                  <td class="px-3 py-1.5 text-center text-base font-semibold tracking-normal text-gray-900">{{ formatMoney(totalLocFeesAllTimeCents) }}</td>
                  <td class="border-l border-gray-100 px-3 py-1.5 text-center text-base font-semibold tracking-normal text-gray-900">{{ formatMoney(totalLocFeesPeriodCents) }}</td>
                </tr>
                <tr>
                  <td class="px-3 py-1.5 text-xs font-medium text-gray-500">Total Outstanding</td>
                  <td
                    class="px-3 py-1.5 text-center text-base font-semibold tracking-normal"
                    :class="(totals.outstanding_cents || 0) > 0 ? 'text-rose-700' : 'text-gray-900'"
                  >{{ formatMoney(totals.outstanding_cents) }}</td>
                  <td
                    class="border-l border-gray-100 px-3 py-1.5 text-center text-base font-semibold tracking-normal"
                    :class="(totals.outstanding_period_cents || 0) > 0 ? 'text-rose-700' : 'text-gray-900'"
                  >{{ formatMoney(totals.outstanding_period_cents) }}</td>
                </tr>
                <tr>
                  <td class="px-3 py-1.5 text-xs font-medium text-gray-500">Paid amount</td>
                  <td class="px-3 py-1.5 text-center text-base font-semibold tracking-normal text-emerald-700">{{ formatMoney(totals.paid_cents) }}</td>
                  <td class="border-l border-gray-100 px-3 py-1.5 text-center text-base font-semibold tracking-normal text-emerald-700">{{ formatMoney(totals.paid_period_cents) }}</td>
                </tr>
                <tr>
                  <td class="px-3 py-1.5 text-xs font-medium text-gray-500">Waived amount</td>
                  <td class="px-3 py-1.5 text-center text-base font-semibold tracking-normal text-amber-600">{{ formatMoney(totals.waived_cents) }}</td>
                  <td class="border-l border-gray-100 px-3 py-1.5 text-center text-base font-semibold tracking-normal text-amber-600">{{ formatMoney(totals.waived_period_cents) }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!--
        Batch action bar — appears only while at least one row on the
        CURRENT page is selected. Sticky so it stays reachable while the
        user scrolls the table. Each button shows how many of the selected
        rows are eligible for THAT action (a selection can mix lock-eligible
        and paid-eligible rows); 0-eligible buttons are disabled.
      -->
      <div
        v-if="canLock && (selectedLockRows.length > 0 || selectedPaidRows.length > 0)"
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
        <!--
          Push to Settlement — pools the ticked locked+unpaid rows into their
          Site Settlement (per payout group). CIMB is exported, and each row is
          marked paid, from the settlement page. Replaces the old on-summary
          Export CIMB. Rows already in a settlement are excluded.
        -->
        <Button
          type="button"
          class="inline-flex items-center space-x-1 rounded-md px-3 py-2 text-xs font-semibold bg-teal-100 hover:bg-teal-200 text-teal-800 ring-1 ring-inset ring-teal-300 disabled:opacity-40 disabled:cursor-not-allowed"
          :disabled="!selectedPaidRows.length || pushingSettlement"
          v-tooltip="selectedPaidRows.length ? `Push ${selectedPaidRows.length} row(s), total ${formatMoney(selectedPaidNetTotal)}, into their Site Settlement` : 'No selected rows are eligible (must be locked + unpaid, period 2605 onward, not already in a settlement)'"
          @click="onPushToSettlement"
        >
          <BanknotesIcon class="h-3.5 w-3.5" aria-hidden="true" />
          <span>{{ pushingSettlement ? 'Pushing…' : `Push to Settlement (${selectedPaidRows.length})` }}</span>
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
                      <!--
                        Avg Mthly Sales — cumulative running average of monthly
                        sales up to & including the row's period. Sortable via a
                        correlated subquery server-side (avg_monthly_sales). Live
                        for the current month, frozen once a month completes.
                      -->
                      <SingleSortItem modelName="avg_monthly_sales" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('avg_monthly_sales')">
                        Avg Mthly Sales
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
                      <span class="text-[11px] text-gray-500 inline-flex items-center justify-center gap-0.5">
                        (w/ GST)
                        <ExclamationCircleIcon
                          class="min-w-4 w-4 h-4 text-sky-500 cursor-help"
                          v-tooltip="{ content: 'The w/GST figure is highlighted by its full-month run-rate vs Avg Mthly Sales.<br>Current month: sales &divide; days elapsed &times; days in the month (projected to a full month).<br>Past months: actual sales used as-is.<br><span style=&quot;color:#16a34a&quot;>Green</span> = above average &nbsp; <span style=&quot;color:#dc2626&quot;>Red</span> = below average', html: true }"
                        />
                      </span>
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
                    <template v-else>
                      <div class="flex flex-col space-y-1">
                        <span>Action</span>
                        <!-- Outstanding ($) — sorts by the per-site settlement
                             balance (the Payment-History pill amount). Sits
                             between Action and the Loc Fee remarks. -->
                        <SingleSortItem modelName="outstanding" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('outstanding')">
                          Outstanding($)
                        </SingleSortItem>
                        <!-- Remarks for Loc Fees lives in this column (bottom
                             of the cell). One box per Site; sortable by the
                             last-updated timestamp. -->
                        <span>Remarks for Loc Fees</span>
                        <SingleSortItem modelName="loc_fee_remarks_updated_at" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('loc_fee_remarks_updated_at')">
                          Remarks Last Updated
                        </SingleSortItem>
                      </div>
                    </template>
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
                      <!-- Site Name always links to Customer/Edit on every row
                           (latest and older alike). Only the Site Name uses this
                           always-on link; all other columns keep their prior
                           lock-aware / latest-row logic untouched. -->
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
                          class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] w-fit h-fit"
                          :class="row.customer.status_name === 'Removed'
                            ? 'bg-red-50 text-red-700 border-2 border-red-500 font-semibold'
                            : 'font-medium border ' + siteStatusBadgeClass(row.customer.status_id)"
                          v-tooltip="row.customer.status_name === 'Removed' && row.removed_date ? ('Removed on ' + formatYYMMDD(row.removed_date)) : null"
                        >
                          {{ row.customer.status_name }}<template v-if="row.customer.status_name === 'Removed' && row.removed_date">&nbsp;{{ formatYYMMDD(row.removed_date) }}</template>
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
                        <!--
                          Upcoming Term badge — shown once per site (first row of
                          the cluster) when the site has a pending future contract
                          set via "Set Upcoming Term" on the Edit page. Tooltip
                          shows the effective date. Driven by row.upcoming_term
                          (CustomerController::attachUpcomingTermFlag).
                        -->
                        <span
                          v-if="isFirstRowForCustomer(rowIndex) && row.upcoming_term"
                          class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-medium border w-fit h-fit bg-amber-100 text-amber-800 border-amber-300"
                          v-tooltip="'Upcoming term effective ' + formatYYMMDD(row.upcoming_term.effective_date)"
                        >
                          Upcoming Term
                        </span>
                        <!--
                          Activation badge — site went active mid-month this
                          period (is_activated_in_period), so rental/utility are
                          prorated from the active date. Shown here in the Site
                          column so users see why a partial month reads less than
                          the full monthly rate (not a bug).
                        -->
                        <span
                          v-if="row.is_activated_in_period && row.active_date"
                          class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-medium border w-fit h-fit bg-orange-100 text-orange-800 border-orange-300"
                          v-tooltip="'Active from ' + formatYYMMDD(row.active_date) + ' — rental/utility prorated for the partial month.'"
                        >
                          Active {{ formatYYMMDD(row.active_date) }}
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
                        Avg Mthly Sales — running average of monthly sales up to
                        and including this row's period. Header label lives in
                        the column header; the cell renders just the figure.
                        Hidden when not computed (null).
                      -->
                      <span
                        v-if="row.avg_monthly_sales_cents != null"
                        class="text-xs text-gray-700 mt-1 inline-flex items-center"
                        v-tooltip="'Average monthly sales to date (frozen once the month completes). Arrow compares vs the previous month average.'"
                      >
                        {{ formatMoney(row.avg_monthly_sales_cents) }}
                        <TrendIcon :dir="trendAvgSales(row)" />
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
                      <!--
                        Contract Attachment badge + binding-history clock icon
                        share one line so the clock doesn't consume an extra row
                        in the Site column. Clock (once per site) opens a popup of
                        every machine bound to THIS site with timestamps.
                      -->
                      <div class="flex items-center gap-1 mt-1">
                        <a
                          v-if="row.customer.latest_contract && row.customer.latest_contract.full_url"
                          :href="row.customer.latest_contract.full_url"
                          target="_blank"
                          rel="noopener noreferrer"
                          class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-medium border w-fit bg-green-100 text-green-800 border-green-300 hover:bg-green-200"
                          :title="row.customer.latest_contract.name || 'Contract Attachment'"
                        >
                          Contract
                        </a>
                        <span
                          v-else
                          class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-medium border w-fit bg-red-100 text-red-800 border-red-300"
                        >
                          No Contract
                        </span>
                        <button
                          v-if="isFirstRowForCustomer(rowIndex) && row.customer?.id"
                          type="button"
                          @click.prevent="openBindingHistory(row.customer)"
                          class="inline-flex items-center justify-center rounded bg-yellow-400 hover:bg-yellow-500 text-gray-800 p-0.5 w-fit h-fit border border-yellow-500"
                          v-tooltip="'Machine binding history'"
                        >
                          <ClockIcon class="h-4 w-4" aria-hidden="true" />
                        </button>
                      </div>
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
                    <!--
                      MACHINE-SPLIT row: this segment's own machine (from the
                      mid-month vend swap) + a "New" badge on the swapped-in row.
                    -->
                    <template v-if="row.machine_vend">
                      <span class="inline-flex items-center gap-1">
                        <!-- Hyperlink only on the Current (latest) row of a NON-
                             removed site; older rows are frozen, and a Removed
                             site drops the link entirely (the code may now belong
                             to another site). -->
                        <a
                          v-if="row.is_latest_row && !isRemovedSite(row)"
                          target="_blank"
                          :href="'/vends/customers?codes=' + encodeURIComponent(row.machine_vend.code)"
                          class="text-blue-700 hover:underline"
                          v-tooltip="'Open this machine in the Ops Dashboard'"
                        >
                          {{ row.machine_vend.code }}
                        </a>
                        <span v-else class="text-gray-900" v-tooltip="'Machine attached during this period (frozen)'">{{ row.machine_vend.code }}</span>
                        <span
                          v-if="row.is_new_machine"
                          class="inline-flex items-center rounded px-1 py-0 text-[9px] font-semibold bg-amber-100 text-amber-800 border border-amber-300"
                          v-tooltip="'Machine changed mid-month'"
                        >New</span>
                      </span>
                    </template>
                    <template v-else-if="row.customer?.vends && row.customer.vends.length > 1">
                      <div class="flex flex-col items-center space-y-0.5">
                        <template v-for="v in row.customer.vends" :key="v.id">
                          <a
                            v-if="!isRemovedSite(row)"
                            target="_blank"
                            :href="'/vends/customers?codes=' + encodeURIComponent(v.code)"
                            class="text-blue-700 hover:underline"
                            v-tooltip="'Open this machine in the Ops Dashboard'"
                          >
                            {{ v.code }}
                          </a>
                          <span v-else class="text-gray-900">{{ v.code }}</span>
                        </template>
                      </div>
                    </template>
                    <template v-else>
                      <a
                        v-if="row.customer?.vend?.id && !isRemovedSite(row)"
                        target="_blank"
                        :href="'/vends/customers?codes=' + encodeURIComponent(row.customer.vend.code)"
                        class="text-blue-700 hover:underline"
                        v-tooltip="'Open this machine in the Ops Dashboard'"
                      >
                        {{ row.customer.vend.code }}
                      </a>
                      <span v-else-if="row.customer?.vend">{{ row.customer.vend.code }}</span>
                    </template>
                  </TableData>

                  <!-- Prefix + Machine Model -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-center">
                    <!--
                      Show the prefix AND machine model of the machine attached
                      during THIS row's period (machine_vend, resolved per row in
                      attachMachineSplitInfo). Falls back to the multi-vend list
                      / single current vend when no per-row machine is resolved.
                    -->
                    <template v-if="row.machine_vend">
                      <div class="flex flex-col items-center leading-tight">
                        <span v-if="row.machine_vend.prefix">{{ row.machine_vend.prefix }}</span>
                        <span v-if="row.machine_vend.model" class="text-[10px] text-gray-500">{{ row.machine_vend.model }}</span>
                      </div>
                    </template>
                    <template v-else-if="row.customer?.vends && row.customer.vends.length > 1">
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
                    <div class="flex flex-col items-center space-y-1">
                      <!--
                        Period START — smart: in the site's ACTIVATION month
                        (is_activated_in_period) show the ACTIVE date with a GREEN
                        border instead of the 1st, since billing starts then.
                        Otherwise the normal period start (month start).
                      -->
                      <span
                        :class="row.is_activated_in_period && row.active_date
                          ? 'inline-block border-2 border-green-500 rounded px-1 text-green-700 font-semibold'
                          : ''"
                        v-tooltip="row.is_activated_in_period && row.active_date
                          ? ('Active from ' + formatYYMMDD(row.active_date) + ' — billed from the active date, not the 1st.')
                          : null"
                      >
                        {{ formatYYMMDD(row.is_activated_in_period && row.active_date ? row.active_date : row.period_start) }}
                      </span>
                      <!--
                        Period END — smart, two RED-border cases:
                          (a) REMOVAL month (is_removed_in_period): show the
                              REMOVED date instead of month-end, since billing
                              stops then (removal day exclusive; no rows after).
                          (b) MACHINE REPLACEMENT (is_replaced_machine): this is
                              the previous (swapped-out) segment, so its
                              period_end is the swap boundary — its last billable
                              day on that machine. The replacement continues on
                              the next row.
                        Otherwise the normal period end (no highlight).
                      -->
                      <span
                        :class="(row.is_removed_in_period && row.removed_date) || row.is_replaced_machine
                          ? 'inline-block border-2 border-red-500 rounded px-1 text-red-700 font-semibold'
                          : ''"
                        v-tooltip="row.is_removed_in_period && row.removed_date
                          ? ('Removed on ' + formatYYMMDD(row.removed_date) + ' — final billable period; rental/utility prorated to the last active day. No rows after this month.')
                          : (row.is_replaced_machine
                            ? ('Machine replaced — last billable day on this machine before the swap; rental/utility prorated to here. The replacement machine continues on the next row.')
                            : null)"
                      >
                        {{ formatYYMMDD(row.is_removed_in_period && row.removed_date ? row.removed_date : row.period_end) }}
                      </span>
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
                          <span
                            :class="salesBadgeClass(row)"
                            v-tooltip="salesTooltip(row)"
                          >{{ formatMoney(row.sales_cents) }}</span>
                        </span>
                        <span class="inline-flex items-center">
                          <span :class="SALES_LINE_CLASS">{{ formatMoney(row.sales_cents / (1 + Number(row.customer.operator.gst_vat_rate) / 100)) }}</span>
                        </span>
                      </template>
                      <span v-else class="inline-flex items-center">
                        <span
                          :class="salesBadgeClass(row)"
                          v-tooltip="salesTooltip(row)"
                        >{{ formatMoney(row.sales_cents) }}</span>
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
                      <span v-if="row.contract_ps_term != null && ['PS','PS+U','PSORU'].includes(row.contract_commission_type)" class="text-gray-400">
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
                      <div class="flex flex-col items-end">
                        <span class="inline-flex items-center" :class="locationFeesColorClass(row.location_fees_cents, row.contract_commission_type)">
                          {{ formatMoneySigned(row.location_fees_cents) }}
                        </span>
                        <!-- "to date" badge: the current in-progress month's
                             flat fee is accrued through period_end (X/Y days
                             elapsed), not billed for the whole month, so the
                             figure looks smaller on purpose. Clears at month
                             end (full fee) and never shows on closed/locked
                             or pure-PS rows. Sits on its own line below the
                             value so it never squeezes the figure. -->
                        <span
                          v-if="row.loc_fee_prorated_to_date"
                          class="mt-0.5 inline-flex items-center whitespace-nowrap px-1 py-0 rounded text-[9px] font-semibold bg-sky-100 text-sky-800 border border-sky-300"
                          v-tooltip="`Prorated to date — ${row.to_date_days}/${row.month_total_days} days of this month elapsed. The flat fee accrues daily and shows the full month once the month closes.`"
                        >
                          {{ row.to_date_days }}/{{ row.month_total_days }} d
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
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-right min-w-[90px] max-w-[90px]">
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
                        <!-- The note is a SITE-level field (customers.notes), so
                             it's editable right here on the top row of the
                             site's cluster — regardless of which month that row
                             happens to be (multi-month "Last N months" views
                             may not include the site's latest/current month). -->
                        <MentionTextarea
                          :model-value="row.customer.notes"
                          @update:model-value="row.customer.notes = $event"
                          @change="onNotesChanged(row.customer)"
                          :users="mentionableUsers"
                          :rows="4"
                          :autogrow="true"
                          placeholder="Notes"
                          textarea-class="text-[13px] text-gray-700 border border-gray-400 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-1 block w-full resize-none overflow-hidden"
                        />
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
                    <div class="flex flex-col items-center space-y-1">
                    <!--
                      "Show all Periods" — a SITE-level link (opens the site's
                      full period history in a new tab), so it's pinned to the
                      TOP row of each site's cluster in every view (single- and
                      multi-month alike), plus:
                        - the removal-month row (a removed site's final row),
                          regardless of lock state, and
                        - any current-month unlocked row (there's no Lock
                          action to show there anyway).
                      It sits above whatever lock control renders below.
                    -->
                    <a
                      v-if="isFirstRowForCustomer(rowIndex) || row.is_removed_in_period || (row.is_current_month && !row.is_locked)"
                      :href="allPeriodsUrl(row)"
                      target="_blank"
                      rel="noopener"
                      class="inline-flex items-center justify-center text-center px-2 py-1 !text-[10px] font-semibold leading-tight bg-yellow-300 hover:bg-yellow-400 text-yellow-900 rounded shadow-sm border border-yellow-500 max-w-[64px] whitespace-normal break-words"
                      v-tooltip="'Open Site Summary for this site only, showing all periods (new tab)'"
                    >
                      Show all Periods
                    </a>
                    <template v-if="row.is_locked">
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
                          <div class="text-amber-900 font-medium">
                            <span class="font-bold">Locked</span> {{ formatYYMMDDHM(row.locked_at) }}
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
                          <!-- Waived badge — set when the row was marked via the
                               Mark as Paid / Waived popup with Waived ticked.
                               Reason surfaces on hover. -->
                          <div v-if="row.is_waived" class="mt-0.5">
                            <span
                              class="inline-block px-1.5 py-0.5 rounded bg-purple-100 text-purple-800 font-semibold border border-purple-300"
                              v-tooltip="row.waived_remarks || 'Waived'"
                            >
                              Waived
                            </span>
                          </div>
                          <div v-if="!row.is_paid && row.last_unpaid_at" class="text-red-700">
                            <span class="font-semibold">Unpaid</span> {{ formatYYMMDDHM(row.last_unpaid_at) }}
                            <span v-if="row.last_unpaid_by_user">by {{ row.last_unpaid_by_user.name }}</span>
                          </div>
                        </div>
                        <!-- Unlock — hidden while Paid; user must Undo Verify
                             Paid first, then Unlock appears. Server re-checks. -->
                        <Button
                          v-if="canUnlock && !row.is_paid"
                          type="button"
                          class="inline-flex items-center justify-center space-x-1 px-2 py-0.5 !text-[10px] font-semibold leading-tight bg-slate-600 hover:bg-slate-700 text-white rounded-md shadow-sm border border-slate-700 disabled:opacity-50 disabled:cursor-not-allowed"
                          :disabled="lockingFor.has(row.id)"
                          @click="onUnlockClicked(row)"
                        >
                          <LockOpenIcon class="h-3 w-3 shrink-0" aria-hidden="true" />
                          <span>Unlock</span>
                        </Button>
                        <!-- Paid — only visible on a locked+unpaid row, and
                             only from the paid-tracking cutoff (2605) onward;
                             periods 2604 and earlier never show it. -->
                        <Button
                          v-if="canPaid && !row.is_paid && isPaidEligiblePeriod(row)"
                          type="button"
                          class="inline-flex items-center justify-center space-x-1 px-2 py-0.5 !text-[10px] font-medium leading-tight whitespace-nowrap bg-indigo-600 hover:bg-indigo-700 text-white rounded-md shadow-sm border border-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                          :disabled="lockingFor.has(row.id)"
                          v-tooltip="'Confirm the location fee for this period has been paid'"
                          @click="onPaidClicked(row)"
                        >
                          <CheckBadgeIcon class="h-3 w-3 shrink-0" aria-hidden="true" />
                          <span>Loc Fees paid?</span>
                        </Button>
                        <!-- Unpaid — only visible on a locked+paid row.
                             Same access tier as Unlock (superadmin/admin). -->
                        <Button
                          v-if="canUnpaid && row.is_paid"
                          type="button"
                          class="inline-flex items-center justify-center space-x-1 px-2 py-0.5 !text-[10px] font-medium leading-tight bg-red-100 hover:bg-red-200 text-red-800 rounded"
                          :disabled="lockingFor.has(row.id)"
                          v-tooltip="'Reverse the paid status for this period'"
                          @click="onUnpaidClicked(row)"
                        >
                          <XCircleIcon class="h-3 w-3 shrink-0" aria-hidden="true" />
                          <span>Undo Verify Paid</span>
                        </Button>
                      </div>
                    </template>
                    <!-- Lock — any completed (non-current) unlocked month, plus
                         the removal exception: a current-month row whose site
                         is removed in-period can be locked early. -->
                    <template v-else-if="canLock && (!row.is_current_month || row.is_removed_in_period)">
                      <div class="flex flex-col items-center space-y-1">
                        <Button
                          type="button"
                          class="inline-flex items-center justify-center space-x-1 px-2 py-0.5 !text-[10px] font-medium leading-tight bg-amber-100 hover:bg-amber-200 text-amber-800 rounded"
                          :disabled="lockingFor.has(row.id)"
                          v-tooltip="row.is_current_month ? 'Site removed ' + (row.removed_date || '') + ' — lock this prorated (current-month) period early' : null"
                          @click="onLockClicked(row)"
                        >
                          <LockOpenIcon class="h-3 w-3 shrink-0" aria-hidden="true" />
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
                    <!-- Dash placeholder — only when the cell would otherwise
                         be empty (no link above, no lock control). -->
                    <template v-else-if="!row.is_removed_in_period && !row.is_current_month && !isFirstRowForCustomer(rowIndex)">
                      <span class="text-gray-300">—</span>
                    </template>
                    </div>
                  </TableData>

                  <!-- Action — now the LAST column (was 2 positions earlier). -->
                  <TableData :currentIndex="rowIndex" :totalLength="summaries.data.length" inputClass="text-center min-w-[120px] max-w-[120px]">
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
                        "Email" status badge — only on rows whose Site has a
                        Performance Report Email filled AND the "Enable Send
                        Performance to Email?" opt-in turned on (same pair the
                        modal's Email button checks). Colour reflects whether
                        the report has actually been emailed for THIS period:
                          green  "Email (sent)"        → row.report_emailed_at set
                          red    "Email (havent send)" → not yet emailed
                        Lets the user see send status at-a-glance without
                        opening the Report Content modal.
                      -->
                      <span
                        v-if="row.customer?.is_report_email_enabled && row.customer?.report_email"
                        class="inline-flex items-center justify-center gap-1 self-center mt-1 px-2 py-0.5 rounded-full text-[10px] font-medium"
                        :class="row.report_emailed_at
                          ? 'bg-green-100 text-green-800'
                          : 'bg-red-100 text-red-800'"
                        v-tooltip="row.report_emailed_at
                          ? `Report emailed for this period to ${row.customer.report_email}`
                          : `Not yet emailed for this period — ${row.customer.report_email}`"
                      >
                        <EnvelopeIcon class="w-3 h-3" />
                        <span>{{ row.report_emailed_at ? 'Email (sent)' : 'Email (not yet)' }}</span>
                      </span>
                      <!--
                        Copy Content — for Sites with NO report-email opt-in
                        (no Performance Report Email / "Enable Send..." = No).
                        Those rows can't be emailed, so give the user a quick
                        way to grab the report body for the clipboard and paste
                        it wherever they like. Purely a clipboard action — no
                        "sent" audit is recorded. Only meaningful when the row
                        actually has renderable report content.
                      -->
                      <Button
                        v-else-if="row.customer?.has_report_content"
                        type="button"
                        class="inline-flex items-center justify-center space-x-1 self-center mt-1 px-2 py-1 text-[10px] bg-gray-100 hover:bg-gray-200 text-gray-700"
                        :disabled="copyingContentFor.has(row.customer.id)"
                        v-tooltip="'Copy this report content to the clipboard'"
                        @click="onRowCopyContentClicked(row)"
                      >
                        <ClipboardDocumentIcon class="w-3 h-3" />
                        <span>Copy Content</span>
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

                      <!--
                        Payment History — opens the per-site settlement ledger
                        (what we owe the site owner + payments made). SITE-level
                        data, so it's shown ONCE per Site on the TOP row of the
                        site's cluster (like the Site Note), in every view —
                        single- and multi-month alike. The pill shows the live
                        outstanding balance at-a-glance.
                      -->
                      <div v-if="isFirstRowForCustomer(rowIndex)" class="mt-1">
                        <Button
                          type="button"
                          class="w-full inline-flex items-center justify-center space-x-1 px-3 py-2 text-xs bg-indigo-100 hover:bg-indigo-200 text-indigo-800"
                          v-tooltip="'View this site\'s settlement / payment history'"
                          @click="openPaymentHistory(row.customer)"
                        >
                          <BanknotesIcon class="w-4 h-4 shrink-0" aria-hidden="true" />
                          <span>Payment History</span>
                        </Button>
                        <div class="mt-1 text-center">
                          <span
                            class="inline-block px-2 py-0.5 rounded-full text-[11px] font-semibold border"
                            :class="balancePillClass(outstandingFor(row.customer))"
                          >
                            {{ balancePillLabel(outstandingFor(row.customer)) }}{{ formatMoney(Math.abs(outstandingFor(row.customer))) }}
                          </span>
                        </div>
                      </div>

                      <!--
                        Remarks for Loc Fees — one free-text box per Site,
                        parked on the customer record (like the Site Note) so
                        it persists across whatever period / filter is applied.
                        Sits at the bottom of the Action cell. Audit line below
                        shows who last edited and when. Saved on blur via
                        onLocFeeRemarksChanged. No unread tracking.
                      -->
                      <!-- Shown only ONCE per Site, on the TOP row of the
                           site's cluster — like the Site Note. The other rows
                           in the cluster do not repeat the remarks. -->
                      <div v-if="isFirstRowForCustomer(rowIndex)" class="mt-1 flex flex-col w-full text-left">
                        <MentionTextarea
                          :model-value="row.customer.loc_fee_remarks"
                          @update:model-value="row.customer.loc_fee_remarks = $event"
                          @change="onLocFeeRemarksChanged(row.customer)"
                          :rows="3"
                          :autogrow="true"
                          placeholder="Remarks for Loc Fees"
                          textarea-class="text-[13px] text-gray-700 border border-gray-400 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-1 block w-full resize-none overflow-hidden"
                        />
                        <span class="text-[10px] text-gray-500 mt-1" v-if="row.customer?.loc_fee_remarks_updated_by_user">
                          {{ row.customer.loc_fee_remarks_updated_by_user.name }} ({{ moment(row.customer.loc_fee_remarks_updated_at).format('YYMMDD hh:mma') }})
                        </span>
                      </div>
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

          <!--
            Greeting + intro — mirrors the emailed body (buildReportEmailParts)
            so this preview matches exactly what the customer receives. Billing
            Company is the Edit form's "Bill From" field, falling back to the
            company remark / site name when unset.
          -->
          <div class="text-gray-800 text-sm leading-relaxed mb-4">
            <p>Dear Valued Partner: "<span class="font-semibold">{{ reportContentBillingCompany }}</span>"</p>
            <p class="mt-2">This is an automatic email. Below is the Vending Machine Location Fees Report</p>
          </div>

          <!-- Title banner -->
          <div class="rounded-md bg-blue-50 border border-blue-200 px-4 py-3 mb-4">
            <div class="text-blue-900 font-semibold text-base leading-tight">
              Vending Machine Location Fees Report
            </div>
            <div class="mt-1 text-blue-700 font-bold uppercase tracking-wide text-sm">
              Term: {{ reportContent.contract_type_label }}
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

          <!-- Closing thank-you — mirrors the emailed body. -->
          <div class="text-gray-800 text-sm leading-relaxed mt-4">
            Thank you for your continued support and partnership with HappyIce, and bringing quality ice cream and frozen treats to your visitors, tenants, residents, and staff.
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
            <div
              v-if="reportContentRow?.customer?.is_report_email_enabled && reportContentRow?.customer?.report_email"
              class="flex items-center gap-2"
            >
              <Button
                type="button"
                class="inline-flex items-center justify-center space-x-1 px-3 py-2 text-xs bg-blue-100 hover:bg-blue-200 text-blue-800"
                :disabled="sendingReportFor.has(reportContentRow.customer.id)"
                v-tooltip="'Open your mail client to send this report, and record the send'"
                @click="onModalEmailClicked"
              >
                <EnvelopeIcon class="w-4 h-4" />
                <span>Email</span>
              </Button>
              <Button
                type="button"
                class="inline-flex items-center justify-center space-x-1 px-3 py-2 text-xs bg-gray-100 hover:bg-gray-200 text-gray-800"
                v-tooltip="'Copy the email address, subject and content so you can paste it into webmail (e.g. Gmail)'"
                @click="onModalCopyEmailClicked"
              >
                <ClipboardDocumentIcon class="w-4 h-4" />
                <span>Copy Email Content</span>
              </Button>
            </div>
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
      Export Batch Report Content modal — built from every ticked row that has
      Report Content. All machines are stitched into ONE client email body
      (single greeting → each machine's block → single thank-you), exactly as
      the per-row email composer formats a single machine. Copy button puts the
      whole thing on the clipboard for pasting into the client's email.
    -->
    <Modal :open="showBatchReportModal" @modalClose="onBatchReportModalClose">
      <template #header>
        <div class="flex items-center space-x-2">
          <DocumentTextIcon class="w-5 h-5 text-blue-600" />
          <span>Batch Report Content<template v-if="batchReportMachineCount"> ({{ batchReportMachineCount }} machine{{ batchReportMachineCount === 1 ? '' : 's' }})</template></span>
        </div>
      </template>
      <template #default>
        <div v-if="batchReportLoading" class="text-center py-10 text-gray-500">
          <div class="animate-pulse text-sm">Building batch report…</div>
        </div>

        <div v-else-if="batchReportText" class="text-sm">
          <div class="flex items-center justify-between gap-2 mb-3">
            <p class="text-xs text-gray-500">
              Combined report for one client — review, then copy and paste into the email to the site owner.
            </p>
            <Button
              type="button"
              class="inline-flex items-center justify-center space-x-1 px-3 py-2 text-xs bg-gray-100 hover:bg-gray-200 text-gray-800 shrink-0"
              v-tooltip="'Copy the combined report content to the clipboard'"
              @click="onCopyBatchReportClicked"
            >
              <ClipboardDocumentIcon class="w-4 h-4" />
              <span>Copy Content</span>
            </Button>
          </div>
          <pre class="whitespace-pre-wrap break-words rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-gray-800 text-[13px] leading-relaxed font-sans max-h-[60vh] overflow-auto">{{ batchReportText }}</pre>
          <p v-if="batchReportSkipped" class="text-[11px] text-amber-600 italic mt-2">
            {{ batchReportSkipped }}
          </p>
        </div>

        <div v-else class="text-center py-10 text-gray-500 text-sm">
          No report content could be built for the selected rows.
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
          <span>Mark as Paid/ Waived</span>
        </div>
      </template>
      <template #default>
        <div class="text-sm text-left">
          <p class="mb-4 text-gray-700">
            Mark <span class="font-semibold">{{ paidModalLabel }}</span> as
            {{ paidModalWaived ? 'Waived' : 'Paid' }}?
          </p>
          <div class="flex items-start gap-6">
            <div>
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

              <!--
                Amount — the actual sum paid (or waived) to the site owner.
                Posts to the site's settlement ledger (Payment History) as a
                CREDIT against what we owe. Pre-filled with this period's Net
                Loc Fee; the admin can override with the real figure.
              -->
              <label class="block text-xs font-medium text-gray-600 mb-1 mt-3" for="paid-amount-input">
                {{ paidModalWaived ? 'Amount waived' : 'Amount paid' }}
              </label>
              <div class="relative w-48">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2 text-gray-400 text-sm">$</span>
                <input
                  id="paid-amount-input"
                  v-model="paidModalAmount"
                  type="number"
                  min="0"
                  :step="currencyExp > 0 ? (1 / minorPerUnit) : 1"
                  inputmode="decimal"
                  class="w-48 pl-5 rounded border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500"
                />
              </div>
              <p class="text-[11px] text-gray-500 mt-1">
                Recorded in Payment History. Leave 0 to skip a ledger entry.
              </p>
            </div>
            <div class="flex-1">
              <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-600 mb-1">
                <input
                  type="checkbox"
                  v-model="paidModalWaived"
                  class="rounded border-gray-300 text-purple-600 focus:ring-purple-500"
                />
                Waived?
              </label>
              <textarea
                v-model="paidModalRemarks"
                rows="3"
                :disabled="!paidModalWaived"
                :placeholder="paidModalWaived ? 'Reason for waiving (required)' : 'Tick Waived to add a reason'"
                class="block w-full rounded border-gray-300 text-sm focus:border-purple-500 focus:ring-purple-500 disabled:bg-gray-100 disabled:text-gray-400"
              ></textarea>
              <p v-if="paidModalWaived" class="text-[11px] text-purple-600 mt-1">
                Remark is required when waiving.
              </p>
            </div>
          </div>

          <!--
            Free-text comment — optional, always available for both Paid and
            Waived. Saved onto the Payment History ledger row (settlement
            remarks) so it shows under the entry. For a waiver it sits
            alongside the mandatory reason above.
          -->
          <div class="mt-4">
            <label class="block text-xs font-medium text-gray-600 mb-1" for="paid-comment-input">
              Comment <span class="font-normal text-gray-400">(optional)</span>
            </label>
            <textarea
              id="paid-comment-input"
              v-model="paidModalComment"
              rows="2"
              placeholder="Add a note for this entry — shows in Payment History"
              class="block w-full rounded border-gray-300 text-sm focus:border-emerald-500 focus:ring-emerald-500"
            ></textarea>
          </div>

          <!--
            Outstanding context — the pay action happens on a past period row,
            so surface the SITE's current outstanding right here (it otherwise
            only lives on the latest row's pill). Shows the running balance and
            what remains after the amount being entered, plus a link into the
            full ledger without losing this in-progress entry.
          -->
          <div
            class="mt-4 rounded-lg border px-4 py-3"
            :class="{
              'border-rose-100 bg-rose-50/60': balanceState(paidModalOutstanding) === 'owing',
              'border-emerald-100 bg-emerald-50/60': balanceState(paidModalOutstanding) === 'settled',
              'border-sky-100 bg-sky-50/60': balanceState(paidModalOutstanding) === 'credit',
            }"
          >
            <div class="flex items-center justify-between">
              <div>
                <div class="text-[11px] font-medium uppercase tracking-wide text-gray-500">
                  {{ balanceState(paidModalOutstanding) === 'credit' ? 'Credit balance with this site' : 'Outstanding owed to this site' }}
                </div>
                <div
                  class="text-xl font-bold"
                  :class="{ 'text-rose-600': balanceState(paidModalOutstanding) === 'owing', 'text-emerald-600': balanceState(paidModalOutstanding) === 'settled', 'text-sky-600': balanceState(paidModalOutstanding) === 'credit' }"
                >
                  {{ formatMoney(Math.abs(paidModalOutstanding)) }}<span v-if="balanceState(paidModalOutstanding) === 'credit'" class="ml-1 text-sm font-semibold">CR</span>
                </div>
              </div>
              <button
                type="button"
                class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:text-indigo-800 hover:underline"
                @click="openPaymentHistoryFromPaid"
              >
                <BanknotesIcon class="h-4 w-4" />
                View Payment History
              </button>
            </div>
            <div v-if="paidModalAmountCents > 0" class="mt-2 border-t border-dashed border-gray-200 pt-2 text-[11px] text-gray-600">
              After this {{ paidModalWaived ? 'waiver' : 'payment' }} of
              <span class="font-semibold text-gray-800">{{ formatMoney(paidModalAmountCents) }}</span>,
              <template v-if="(paidModalOutstanding - paidModalAmountCents) < 0">
                this site goes into credit by
                <span class="font-semibold text-sky-600">{{ formatMoney(Math.abs(paidModalOutstanding - paidModalAmountCents)) }}</span>
                (offsets upcoming fees)
              </template>
              <template v-else>
                remaining ≈
                <span class="font-semibold" :class="(paidModalOutstanding - paidModalAmountCents) > 0 ? 'text-rose-600' : 'text-emerald-600'">
                  {{ formatMoney(paidModalOutstanding - paidModalAmountCents) }}
                </span>
              </template>
            </div>
          </div>

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
              <span>{{ paidModalWaived ? 'Confirm Waived' : 'Confirm Paid' }}</span>
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
          <div class="mt-3 rounded-lg border border-emerald-100 bg-emerald-50/60 px-3 py-2">
            <div class="flex items-center justify-between text-xs">
              <span class="text-gray-600">Payment recorded per period</span>
              <span class="font-semibold text-emerald-700">{{ formatMoney(selectedPaidNetTotal) }} total</span>
            </div>
            <p class="mt-1 text-[11px] text-gray-500">
              Each period's payment is recorded in Payment History as its
              <span class="font-medium">Net Location Fee</span>. Periods with no fee are skipped.
            </p>
          </div>
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

    <!-- Machine binding history popup (per site) -->
    <Modal :open="bindingHistoryOpen" @modalClose="bindingHistoryOpen = false">
      <template #header>
        <span>Machine Binding History — {{ bindingHistorySite }}</span>
      </template>
      <div class="text-left">
        <div v-if="bindingHistoryLoading" class="text-sm text-gray-500 py-4 text-center">Loading…</div>
        <div v-else-if="!bindingHistory.length" class="text-sm text-gray-500 py-4 text-center">
          No machine binding history for this site.
        </div>
        <table v-else class="w-full text-sm">
          <thead>
            <tr class="text-left text-xs text-gray-500 border-b">
              <th class="py-1 pr-3">Date / Time</th>
              <th class="py-1 pr-3">Action</th>
              <th class="py-1 pr-3">Machine ID</th>
              <th class="py-1 pr-3">Prefix</th>
              <th class="py-1">By</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(b, i) in bindingHistory" :key="i" class="border-b last:border-0">
              <td class="py-1 pr-3 whitespace-nowrap">{{ b.created_at || '—' }}</td>
              <td class="py-1 pr-3">
                <span
                  class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-medium border"
                  :class="b.is_binding ? 'bg-green-100 text-green-800 border-green-300' : 'bg-red-100 text-red-700 border-red-300'"
                >{{ b.is_binding ? 'Bound' : 'Unbound' }}</span>
              </td>
              <td class="py-1 pr-3 font-medium">{{ b.vend_code || '—' }}</td>
              <td class="py-1 pr-3">{{ b.vend_prefix || '—' }}</td>
              <td class="py-1 text-gray-600">{{ b.user || '—' }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </Modal>

    <!--
      Payment History popup (per site) — the settlement ledger. SOA-style:
      DR = a charge we owe the site owner (opening balance, monthly loc fee),
      CR = a payment / waiver against it, BALANCE = running outstanding.
      Read-only; fetched lazily by clicking the Payment History button.
    -->
    <Modal :open="settlementOpen" @modalClose="settlementOpen = false">
      <template #header>
        <div class="flex items-center gap-3">
          <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600">
            <BanknotesIcon class="h-5 w-5" />
          </span>
          <div class="leading-tight">
            <div class="text-base font-semibold text-gray-900">Payment History</div>
            <div class="text-xs font-normal text-gray-500">{{ settlementSite }}</div>
          </div>
        </div>
      </template>

      <div class="text-left text-sm">
        <!-- Toolbar — Add entry (Paid / Waived) on the left, exports on the right.
             Add buttons are admin-only and post a STANDALONE credit to this
             site's ledger (they do NOT mark any period Paid). Export buttons stay
             neutral grey (secondary). -->
        <div v-if="!settlementLoading" class="mb-3 flex flex-wrap items-center justify-between gap-2">
          <!-- Add Paid / Waived credit -->
          <div v-if="canAddSettlement" class="flex items-center gap-2">
            <Button
              type="button"
              class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold rounded-md bg-emerald-600 hover:bg-emerald-700 text-white shadow-sm transition-colors"
              v-tooltip="'Add a Paid credit to this site\'s ledger'"
              @click="startAddSettlement('payment')"
            >
              <PlusIcon class="h-4 w-4" />
              <span>Record Paid</span>
            </Button>
            <Button
              type="button"
              class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold rounded-md bg-sky-600 hover:bg-sky-700 text-white shadow-sm transition-colors"
              v-tooltip="'Add a Waived credit to this site\'s ledger'"
              @click="startAddSettlement('waiver')"
            >
              <PlusIcon class="h-4 w-4" />
              <span>Record Waived</span>
            </Button>
            <Button
              type="button"
              class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold rounded-md bg-slate-600 hover:bg-slate-700 text-white shadow-sm transition-colors"
              v-tooltip="'Add a free-form adjustment (charge or credit) to this site\'s ledger'"
              @click="startAddSettlement('adjustment')"
            >
              <PlusIcon class="h-4 w-4" />
              <span>Record Adjustment</span>
            </Button>
          </div>
          <div v-else></div>

          <!-- Export (only meaningful once there are rows) -->
          <div v-if="settlementRows.length" class="flex items-center gap-2">
            <span class="mr-1 text-[11px] font-medium uppercase tracking-wide text-gray-400">Export</span>
            <Button
              type="button"
              class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold rounded-md bg-gray-50 hover:bg-white text-gray-700 hover:text-indigo-700 border border-gray-300 hover:border-indigo-400 shadow-sm transition-colors"
              v-tooltip="'Open a printable Statement of Account (Save as PDF)'"
              @click="exportSettlementsPdf"
            >
              <DocumentTextIcon class="h-4 w-4 text-indigo-500" />
              <span>PDF</span>
            </Button>
            <Button
              type="button"
              class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-xs font-semibold rounded-md bg-gray-50 hover:bg-white text-gray-700 hover:text-indigo-700 border border-gray-300 hover:border-indigo-400 shadow-sm transition-colors"
              v-tooltip="'Download this ledger as an Excel file'"
              @click="exportSettlementsExcel"
            >
              <ArrowDownTrayIcon class="h-4 w-4 text-indigo-500" />
              <span>Excel</span>
            </Button>
          </div>
        </div>

        <!-- Inline add-entry form — appears when Record Paid / Waived / Adjustment
             is clicked. Fields: date, description, amount (+ direction for an
             adjustment). Paid/Waived post a credit; an adjustment can be a
             charge or a credit. Type is preset by which button opened the form. -->
        <div
          v-if="!settlementLoading && addingSettlement"
          class="mb-4 rounded-xl border px-4 py-4"
          :class="addFormPanelClass"
        >
          <div class="mb-3 flex items-center gap-2">
            <span
              class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold"
              :class="settlementTypeBadgeClass(addingSettlement)"
            >{{ settlementTypeLabel(addingSettlement) }}</span>
            <span class="text-xs font-medium text-gray-600">{{ addFormHint }}</span>
          </div>
          <div class="flex flex-wrap items-end gap-3">
            <div>
              <label class="block text-[11px] font-medium text-gray-500 mb-1">Date</label>
              <input
                v-model="addSettlementDate"
                type="date"
                class="w-44 rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
              />
            </div>
            <!-- Direction — adjustment only (charge vs credit). -->
            <div v-if="addingSettlement === 'adjustment'">
              <label class="block text-[11px] font-medium text-gray-500 mb-1">Type</label>
              <select
                v-model="addSettlementDirection"
                class="w-44 rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
              >
                <option value="credit">Credit — reduce what we owe</option>
                <option value="debit">Charge — increase what we owe</option>
              </select>
            </div>
            <div class="flex-1 min-w-[200px]">
              <label class="block text-[11px] font-medium text-gray-500 mb-1">Description</label>
              <input
                v-model="addSettlementDescription"
                type="text"
                :placeholder="addFormPlaceholder"
                class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
              />
            </div>
            <div>
              <label class="block text-[11px] font-medium text-gray-500 mb-1">Amount</label>
              <div class="relative w-40">
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2 text-gray-400 text-sm">$</span>
                <input
                  v-model="addSettlementAmount"
                  type="number" min="0" :step="currencyExp > 0 ? (1 / minorPerUnit) : 1" inputmode="decimal"
                  class="w-40 pl-5 rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                />
              </div>
            </div>
            <div class="flex items-center gap-2">
              <Button type="button" class="px-3 py-2 text-xs bg-gray-100 hover:bg-gray-200 text-gray-700" @click="cancelAddSettlement">Cancel</Button>
              <Button
                type="button"
                class="inline-flex items-center gap-1 px-3 py-2 text-xs text-white"
                :class="addFormSubmitClass"
                :disabled="addSettlementSaving"
                @click="submitAddSettlement"
              >{{ addSettlementSaving ? 'Adding…' : 'Add entry' }}</Button>
            </div>
          </div>
          <p class="mt-2 text-[11px] text-gray-400">Posts to this ledger only — it does not change any period's Paid status.</p>
        </div>

        <!--
          Balance summary card — THREE states:
            owing   (balance > 0) → rose,  "Current Outstanding", we owe them
            settled (balance = 0) → green, "Current Outstanding" S$0.00
            credit  (balance < 0) → sky,   "Credit Balance" (we overpaid; the
                                            surplus offsets upcoming fees)
        -->
        <div
          v-if="!settlementLoading"
          class="mb-5 flex items-center justify-between rounded-xl border px-5 py-4"
          :class="{
            'border-rose-100 bg-rose-50/70': settlementState === 'owing',
            'border-emerald-100 bg-emerald-50/70': settlementState === 'settled',
            'border-sky-100 bg-sky-50/70': settlementState === 'credit',
          }"
        >
          <div class="flex items-center gap-4">
            <span
              class="h-11 w-1.5 rounded-full"
              :class="{ 'bg-rose-400': settlementState === 'owing', 'bg-emerald-400': settlementState === 'settled', 'bg-sky-400': settlementState === 'credit' }"
            ></span>
            <div>
              <div class="text-[11px] font-medium uppercase tracking-wider text-gray-500">
                {{ settlementState === 'credit' ? 'Credit Balance' : 'Current Outstanding' }}
              </div>
              <div
                class="mt-0.5 text-3xl font-bold tracking-tight"
                :class="{ 'text-rose-600': settlementState === 'owing', 'text-emerald-600': settlementState === 'settled', 'text-sky-600': settlementState === 'credit' }"
              >
                {{ formatMoney(Math.abs(settlementOutstanding)) }}<span v-if="settlementState === 'credit'" class="ml-1 text-base font-semibold">CR</span>
              </div>
            </div>
          </div>
          <div class="text-right">
            <span
              class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold"
              :class="{ 'bg-rose-100 text-rose-700': settlementState === 'owing', 'bg-emerald-100 text-emerald-700': settlementState === 'settled', 'bg-sky-100 text-sky-700': settlementState === 'credit' }"
            >
              <span class="h-1.5 w-1.5 rounded-full" :class="{ 'bg-rose-500': settlementState === 'owing', 'bg-emerald-500': settlementState === 'settled', 'bg-sky-500': settlementState === 'credit' }"></span>
              {{ settlementStatusLabel }}
            </span>
            <div v-if="settlementSince" class="mt-1.5 text-[11px] text-gray-400">
              Tracked since {{ moment(settlementSince).format('DD MMM YYYY') }}
            </div>
          </div>
        </div>

        <!-- Credit explainer — only when overpaid. -->
        <div v-if="!settlementLoading && settlementState === 'credit'" class="-mt-3 mb-5 flex items-start gap-2 px-1 text-[11px] text-sky-700">
          <CheckCircleIcon class="mt-px h-3.5 w-3.5 shrink-0" />
          <span>Overpaid by {{ formatMoney(Math.abs(settlementOutstanding)) }} — this credit carries forward and offsets upcoming location fees.</span>
        </div>

        <!-- Loading skeleton -->
        <div v-if="settlementLoading" class="space-y-2.5 py-8">
          <div class="h-12 animate-pulse rounded-lg bg-gray-100"></div>
          <div class="h-9 animate-pulse rounded bg-gray-100"></div>
          <div class="h-9 animate-pulse rounded bg-gray-100/70"></div>
        </div>

        <!-- Empty state -->
        <div v-else-if="!settlementRows.length" class="py-12 text-center">
          <BanknotesIcon class="mx-auto h-9 w-9 text-gray-300" />
          <p class="mt-2 text-sm text-gray-500">No settlement records yet for this site.</p>
          <p class="mt-1 text-xs text-gray-400">Monthly location fees will appear here once accrued.</p>
        </div>

        <!-- Ledger -->
        <div v-else class="overflow-hidden rounded-xl border border-gray-200">
          <div class="max-h-[52vh] overflow-y-auto">
            <table class="w-full border-collapse">
              <thead class="sticky top-0 z-10 bg-gray-50/95 backdrop-blur">
                <tr class="text-[10px] font-semibold uppercase tracking-wider text-gray-500">
                  <th class="px-4 py-2.5 text-left">Ref</th>
                  <th class="px-3 py-2.5 text-left">
                    <button
                      type="button"
                      class="inline-flex items-center gap-1 font-semibold uppercase tracking-wider text-gray-500 hover:text-indigo-600"
                      v-tooltip="'Sort by date'"
                      @click="toggleSettlementSort"
                    >
                      Date
                      <ChevronDownIcon
                        class="h-3 w-3 transition-transform"
                        :class="{ 'rotate-180': settlementSortDir === 'asc' }"
                      />
                    </button>
                  </th>
                  <th class="px-3 py-2.5 text-left">Description</th>
                  <th class="whitespace-nowrap px-3 py-2.5 text-right">Debit</th>
                  <th class="whitespace-nowrap px-3 py-2.5 text-right">Credit</th>
                  <th class="whitespace-nowrap px-4 py-2.5 text-right">Balance</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <template v-for="(s, i) in sortedSettlementRows" :key="s.id ?? i">
                  <tr class="group transition-colors hover:bg-indigo-50/30">
                    <td class="whitespace-nowrap px-4 py-3 align-top font-mono text-[11px] text-gray-400">{{ s.reference_no || '—' }}</td>
                    <td class="whitespace-nowrap px-3 py-3 align-top text-gray-600">{{ s.entry_date ? moment(s.entry_date).format('DD MMM YYYY') : '—' }}</td>
                    <td class="px-3 py-3 align-top">
                      <div class="flex items-center gap-2">
                        <span
                          class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold"
                          :class="settlementTypeBadgeClass(s.entry_type)"
                        >{{ settlementTypeLabel(s.entry_type) }}</span>
                        <span class="font-medium text-gray-800">{{ s.item || settlementTypeLabel(s.entry_type) }}</span>
                        <button
                          v-if="isSettlementEditable(s) && editingSettlementId !== s.id"
                          type="button"
                          class="opacity-0 group-hover:opacity-100 transition-opacity text-gray-400 hover:text-indigo-600"
                          v-tooltip="'Edit this entry'"
                          @click="startEditSettlement(s)"
                        >
                          <PencilSquareIcon class="h-3.5 w-3.5" />
                        </button>
                        <button
                          v-if="isSettlementDeletable(s) && editingSettlementId !== s.id"
                          type="button"
                          class="opacity-0 group-hover:opacity-100 transition-opacity text-gray-400 hover:text-rose-600"
                          :disabled="deletingSettlementId === s.id"
                          v-tooltip="'Delete this entry'"
                          @click="deleteSettlementRow(s)"
                        >
                          <TrashIcon class="h-3.5 w-3.5" />
                        </button>
                      </div>
                      <div v-if="s.remarks" class="mt-0.5 line-clamp-1 text-[11px] text-gray-400" v-tooltip="s.remarks">{{ s.remarks }}</div>
                      <!-- Who triggered this entry + when (creation audit). -->
                      <div v-if="s.created_by || s.created_at" class="mt-0.5 text-[10px] text-gray-400">
                        By {{ s.created_by || 'system' }}<span v-if="s.created_at"> · {{ moment(s.created_at).format('DD MMM YY, h:mma') }}</span>
                      </div>
                      <div v-if="s.edited_by" class="mt-0.5 text-[10px] text-amber-600">
                        Edited by {{ s.edited_by }}<span v-if="s.edited_at"> · {{ moment(s.edited_at).format('DD MMM YY, h:mma') }}</span>
                      </div>
                    </td>
                    <td class="px-3 py-3 text-right align-top tabular-nums" :class="s.debit_cents ? 'font-medium text-gray-800' : 'text-gray-300'">
                      {{ s.debit_cents ? formatMoney(s.debit_cents) : '—' }}
                    </td>
                    <td class="px-3 py-3 text-right align-top tabular-nums" :class="s.credit_cents ? 'font-medium text-emerald-600' : 'text-gray-300'">
                      {{ s.credit_cents ? '−' + formatMoney(s.credit_cents) : '—' }}
                    </td>
                    <td class="px-4 py-3 text-right align-top font-semibold tabular-nums text-gray-900">
                      {{ formatMoney(Math.abs(s.balance_cents)) }}<span v-if="s.balance_cents < 0" class="ml-0.5 align-top text-[10px] font-medium text-sky-600">CR</span>
                    </td>
                  </tr>
                  <!-- Inline editor (opening balance / adjustment) -->
                  <tr v-if="editingSettlementId === s.id" class="bg-indigo-50/40">
                    <td colspan="6" class="px-4 py-3">
                      <div class="flex flex-wrap items-end gap-3">
                        <div>
                          <label class="block text-[11px] font-medium text-gray-500 mb-1">Amount</label>
                          <div class="relative w-40">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2 text-gray-400 text-sm">$</span>
                            <input
                              v-model="editSettlementAmount"
                              type="number" min="0" :step="currencyExp > 0 ? (1 / minorPerUnit) : 1" inputmode="decimal"
                              class="w-40 pl-5 rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                            />
                          </div>
                        </div>
                        <div class="flex-1 min-w-[180px]">
                          <label class="block text-[11px] font-medium text-gray-500 mb-1">Remarks</label>
                          <input
                            v-model="editSettlementRemarks"
                            type="text" placeholder="Optional note"
                            class="w-full rounded border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                          />
                        </div>
                        <div class="flex items-center gap-2">
                          <Button type="button" class="px-3 py-2 text-xs bg-gray-100 hover:bg-gray-200 text-gray-700" @click="cancelEditSettlement">Cancel</Button>
                          <Button
                            type="button"
                            class="inline-flex items-center gap-1 px-3 py-2 text-xs bg-indigo-600 hover:bg-indigo-700 text-white"
                            :disabled="editSettlementSaving"
                            @click="saveEditSettlement(s)"
                          >Save</Button>
                        </div>
                      </div>
                      <p class="mt-1.5 text-[11px] text-gray-400">Editing the amount re-derives every balance below it.</p>
                    </td>
                  </tr>
                </template>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Legend -->
        <div v-if="!settlementLoading && settlementRows.length" class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-gray-400">
          <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-gray-300"></span>Debit — increases what we owe</span>
          <span class="inline-flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-emerald-400"></span>Credit — a verified payment or waiver</span>
          <span class="ml-auto">Location fees post on the 1st of each month.</span>
        </div>

        <!-- Change history — audit trail of payments, waivers, reversals, edits. -->
        <div v-if="!settlementLoading && settlementLogs.length" class="mt-4 border-t border-gray-100 pt-3">
          <button
            type="button"
            class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-600 hover:text-indigo-700"
            @click="showSettlementLogs = !showSettlementLogs"
          >
            <ClockIcon class="h-4 w-4 text-gray-400" />
            Change history ({{ settlementLogs.length }})
            <ChevronDoubleDownIcon v-if="!showSettlementLogs" class="h-3.5 w-3.5" />
            <ChevronDoubleUpIcon v-else class="h-3.5 w-3.5" />
          </button>
          <ul v-if="showSettlementLogs" class="mt-2 space-y-1.5">
            <li
              v-for="(lg, li) in settlementLogs"
              :key="li"
              class="flex items-start gap-2 rounded-md bg-gray-50 px-3 py-2 text-[11px] text-gray-600"
            >
              <span class="mt-0.5 inline-block h-1.5 w-1.5 shrink-0 rounded-full"
                :class="{
                  'bg-emerald-500': lg.action === 'payment',
                  'bg-purple-500': lg.action === 'waiver',
                  'bg-rose-500': lg.action === 'payment_reversed',
                  'bg-amber-500': lg.action === 'edited',
                  'bg-gray-400': !['payment','waiver','payment_reversed','edited'].includes(lg.action),
                }"></span>
              <div class="flex-1">
                <span class="font-semibold text-gray-800">{{ settlementLogLabel(lg.action) }}</span>
                <span v-if="lg.reference_no" class="ml-1 font-mono text-[10px] text-gray-400">{{ lg.reference_no }}</span>
                <template v-if="lg.action === 'edited' && lg.old_amount_cents != null">
                  — {{ formatMoney(Math.abs(lg.old_amount_cents)) }} → <span class="font-medium text-gray-800">{{ formatMoney(Math.abs(lg.new_amount_cents)) }}</span>
                </template>
                <template v-else-if="lg.new_amount_cents != null">
                  — {{ formatMoney(Math.abs(lg.new_amount_cents)) }}
                </template>
                <template v-else-if="lg.old_amount_cents != null">
                  — {{ formatMoney(Math.abs(lg.old_amount_cents)) }}
                </template>
                <span v-if="lg.note" class="text-gray-400"> · {{ lg.note }}</span>
                <div class="text-[10px] text-gray-400">
                  by {{ lg.by || (lg.source === 'cron' ? 'system' : (lg.source === 'seed' ? 'seed' : 'someone')) }}<span v-if="lg.at"> · {{ moment(lg.at).format('DD MMM YY, h:mma') }}</span>
                </div>
              </div>
            </li>
          </ul>
        </div>
      </div>
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
import { ArrowDownTrayIcon, AtSymbolIcon, BackspaceIcon, BanknotesIcon, BellAlertIcon, CheckBadgeIcon, CheckCircleIcon, ChevronDoubleDownIcon, ChevronDoubleUpIcon, ChevronDownIcon, ClipboardDocumentCheckIcon, ClipboardDocumentIcon, ClockIcon, DocumentTextIcon, EnvelopeIcon, ExclamationCircleIcon, LockClosedIcon, LockOpenIcon, MagnifyingGlassIcon, MapPinIcon, PencilSquareIcon, PlusIcon, ReceiptPercentIcon, TrashIcon, XCircleIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import MentionTextarea from '@/Components/MentionTextarea.vue';
import { computed, ref, onMounted, nextTick, h } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';
import { vTooltip } from 'floating-vue';
import moment from 'moment';

const props = defineProps({
  summaries: Object,
  // customer_id => outstanding cents (signed; +ve = we owe the site owner).
  // Drives the live balance pill on the Payment-History button. Sites with no
  // ledger rows are absent → outstandingFor() defaults them to 0.
  settlementBalances: { type: Object, default: () => ({}) },
  periodReport: String,
  periodReportOptions: Array,
  // Custom Range bounds echoed back by the server (YYYY-MM) so the month
  // pickers repopulate on a hard reload / deep link.
  periodFrom: String,
  periodTo: String,
  rangeStart: String,
  rangeEnd: String,
  cmsEndpoint: String,
  // Count of sites whose Site Note changed (by someone else) since this user's
  // previous visit — drives the "Unread" toggle button's badge.
  unreadCount: { type: Number, default: 0 },
  // Server-resolved Unread view state (true when this load is filtered to
  // unread notes, e.g. the fresh-load default) — seeds the toggle's state.
  unreadView: { type: Boolean, default: false },
  // Count of sites whose Site Note @-mentions this user — drives the
  // "@Me Mentioned" toggle button's badge.
  mentionCount: { type: Number, default: 0 },
  // Same-operator users for the @-mention dropdown in the Site Note cell.
  mentionableUsers: { type: Array, default: () => [] },
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
      // Total outstanding we owe site owners across the filtered set.
      outstanding_cents: 0,
      // True when current-month flat fees in the totals are accrued to-date.
      has_to_date_proration: false,
      // Distinct-customer aggregate counts (filtered set).
      no_contract_attachment_count: 0,
      expiring_in_30d_count: 0,
    }),
  },
});

const authOperator = usePage().props.auth.operator;
const operatorCountry = usePage().props.auth.operatorCountry;
// Minor-units per currency unit (e.g. 100 for 2-dp currencies, 1 for 0-dp like
// IDR). Mirrors formatMoney()'s exponent so the Paid-amount field round-trips
// correctly on every per-country deployment.
const currencyExp = operatorCountry?.currency_exponent ?? 2;
const minorPerUnit = Math.pow(10, currencyExp);
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

// "Total Loc Fees" summary row on the Payment to Loc Fees card:
// Loc Fees = Outstanding + Paid + Waived, per column (All-time / Shown Period).
const totalLocFeesAllTimeCents = computed(() =>
  (Number(props.totals.outstanding_cents) || 0)
  + (Number(props.totals.paid_cents) || 0)
  + (Number(props.totals.waived_cents) || 0)
);
const totalLocFeesPeriodCents = computed(() =>
  (Number(props.totals.outstanding_period_cents) || 0)
  + (Number(props.totals.paid_period_cents) || 0)
  + (Number(props.totals.waived_period_cents) || 0)
);

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

// A row is lockable when it's not already locked AND either it's a completed
// past month OR it's the current month for a site that has terminated (date
// elapsed) — the latter mirrors the backend exception in CustomerController.
const isLockEligibleRow = (row) => !row.is_locked && (!row.is_current_month || row.is_removed_in_period);
const isPaidEligibleRow = (row) =>
  !row.is_current_month && row.is_locked && !row.is_paid && isPaidEligiblePeriod(row)
  // Not already in a Site Settlement (paid via the settlement flow instead).
  && !row.commission_settlement_id;
// Report-eligible = the row has renderable Report Content (R/U/PS families;
// F/S excluded — same server flag the per-row "Report Content" button uses).
// Any such row can be ticked for the "Export Batch Report Content" button,
// independent of lock/paid state, so current-month or already-paid rows can
// still be batched into a client email.
const isReportEligibleRow = (row) => !!row.customer?.has_report_content;
const isBatchSelectable = (row) =>
  isLockEligibleRow(row) || isPaidEligibleRow(row) || isReportEligibleRow(row);

// Current-page derivations. selectedRows intersects the selection with the
// page's rows, so ids left over from a previous page/filter can never leak
// into counts or into the ids POSTed to the server.
const selectableRows = computed(() => (props.summaries?.data ?? []).filter(isBatchSelectable));
const selectedRows = computed(() => selectableRows.value.filter((r) => batchSelected.value.has(r.id)));
const selectedLockRows = computed(() => selectedRows.value.filter(isLockEligibleRow));
const selectedPaidRows = computed(() => selectedRows.value.filter(isPaidEligibleRow));
// Ticked rows that carry Report Content — drives the "Export Batch Report
// Content" button. Not page-scoped via selectedRows? It IS: selectedRows is
// already the page ∩ selection, which keeps counts honest across pagination.
const selectedReportRows = computed(() => selectedRows.value.filter(isReportEligibleRow));
// Total Net Loc Fee that batch-paid will record across the selected rows
// (location_fees − external_subsidize, floored at 0 per row).
const selectedPaidNetTotal = computed(() => selectedPaidRows.value.reduce((sum, r) => {
  const net = Number(r.location_fees_cents || 0) - Number(r.external_subsidize_cents || 0);
  return sum + Math.max(0, net);
}, 0));
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

// Tooltip on each row checkbox — lists every batch action the row qualifies
// for (Lock / Mark Paid / Export Report Content can each apply, and a single
// row can qualify for more than one), or explains why it isn't selectable.
function batchCheckboxTooltip(row) {
  const actions = [];
  if (isLockEligibleRow(row)) actions.push('Lock');
  if (isPaidEligibleRow(row)) actions.push('Mark Paid');
  if (isReportEligibleRow(row)) actions.push('Export Report Content');
  if (actions.length) return 'Select for batch: ' + actions.join(' / ');
  if (row.is_current_month) return 'Current month — cannot lock yet';
  if (row.is_locked && row.is_paid) return 'Already Paid';
  if (row.is_locked && !isPaidEligiblePeriod(row)) return 'Locked — period predates paid tracking (2605)';
  return 'No report content (Free / Subsidized) and no pending Lock/Paid action';
}

function onBatchPaidClicked() {
  if (!selectedPaidRows.value.length) return;
  batchPaidDate.value = moment().format('YYYY-MM-DD'); // pre-fill today
  showBatchPaidModal.value = true;
}

// ---------------------------------------------------------------------------
// Export CIMB — commission payout file (CIMB BizChannel bulk txt) for the
// ticked locked+unpaid rows. Mirrors Refund/Index.vue's exportBatch: axios
// blob POST → browser download via the X-Filename header. The server is the
// authority on eligibility, amounts (frozen Net Loc Fee), bank details and
// the single-operator rule; 422 bodies carry a readable message we surface.
// ---------------------------------------------------------------------------
const cimbExporting = ref(false);

// Push selected locked+unpaid rows into their Site Settlement (replaces the
// on-summary Export CIMB — CIMB is now exported from the settlement). Rows
// already in a settlement are excluded by isPaidEligibleRow.
const pushingSettlement = ref(false);
function onPushToSettlement() {
  const ids = selectedPaidRows.value.map((r) => r.id);
  if (!ids.length || pushingSettlement.value) return;
  if (!confirm(`Push ${ids.length} row(s) into their Site Settlement?`)) return;
  pushingSettlement.value = true;
  router.post('/site-settlements/push', { ids }, {
    preserveScroll: true,
    onSuccess: () => { clearBatchSelection(); toast.success('Rows pushed to Site Settlement.', { timeout: 4000 }); },
    onError: (e) => { toast.error(e.settlement || Object.values(e)[0] || 'Failed to push.', { timeout: 5000 }); },
    onFinish: () => { pushingSettlement.value = false; },
  });
}

async function onExportCimbClicked() {
  const ids = selectedPaidRows.value.map((r) => r.id);
  if (!ids.length || cimbExporting.value) return;

  cimbExporting.value = true;
  try {
    const res = await window.axios.post('/customers/summary/export-cimb', { ids }, { responseType: 'blob' });
    // Single operator → .txt; multi-operator selection → .zip with one CIMB
    // txt per operator (each debits that operator's own account).
    const fn = res.headers['x-filename'] || 'commission-cimb.txt';
    const url = URL.createObjectURL(new Blob([res.data], { type: res.headers['content-type'] || 'application/octet-stream' }));
    const a = document.createElement('a');
    a.href = url; a.download = fn; document.body.appendChild(a); a.click(); a.remove();
    URL.revokeObjectURL(url);
    toast.success(`CIMB file exported (${ids.length} row(s) selected). Verify Paid after the bank run succeeds.`, { timeout: 5000 });
  } catch (e) {
    let msg = 'Export failed. Please try again.';
    if (e.response && e.response.status === 422) {
      // responseType is blob, so the JSON error body arrives as a Blob.
      try {
        const body = JSON.parse(await e.response.data.text());
        if (body && body.message) msg = body.message;
      } catch (_) { /* keep fallback text */ }
    }
    // Sticky (no auto-dismiss) — the 422 body lists every site/operator with
    // missing bank details, which takes a while to read/act on. Close via ✕.
    toast.error(msg, { timeout: false });
  } finally {
    cimbExporting.value = false;
  }
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
    only: ['summaries', 'settlementBalances'],
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
// Per-row in-flight guard for the row-level "Copy Content" button (Sites with
// no report-email opt-in). Keyed by customer_id. Clipboard-only, no audit.
const copyingContentFor = ref(new Set());

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
// Billing Company shown in the modal greeting — mirrors buildReportEmailParts()
// so the on-screen preview matches the emailed body. "Bill From" (primary
// contact's company) first, then company remark, then site/customer name.
const reportContentBillingCompany = computed(() => {
  const cust = reportContentRow.value?.customer;
  if (!cust) return '';
  return cust.contact?.company || cust.company_remark || cust.name || '';
});
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

// Filters are collapsed by default — only Machine ID, Site, Tags and Period
// Report show on init; this toggle reveals the rest (mirrors CustomerIndex.vue).
const showAllFilters = ref(false);
function onShowAllFiltersClicked() {
  showAllFilters.value = !showAllFilters.value;
}

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
  // Site Status — 5-value (matches Customer::STATUSES_MAPPING). Multi-select:
  // stores an array of option objects [{id, value}, ...]; their ids are sent to
  // the server. Empty selection is sent as ['all'] (no status constraint).
  status: [],
  is_cms: '',
  ref_id: '',
  vend_code: '',
  // Billing Company text search — matches contact.company (Edit form's
  // "Bill From") or the legacy company_remark fallback (see scopeFilterIndex).
  billing_company: '',
  // Hidden filter: full settlement ledger reference (e.g. LF-000351) → finds
  // the one site owning that entry. See scopeFilterIndex settlement_ref clause.
  settlement_ref: '',
  location_types: [],
  operators: [],
  vendPrefixes: [],
  tags: [],
  period_report: '',
  // Custom Range month bounds ('YYYY-MM' from the native month inputs; empty =
  // open-ended on that side). Only sent/meaningful when period_report=custom.
  period_from: '',
  period_to: '',
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
  // Site Status — comes from the controller (Customer::STATUSES_MAPPING with an
  // "All" sentinel prepended), labelled `name` server-side and remapped to
  // `value` here for the MultiSelect `label` prop.
  // We offer ALL real statuses (drop only the "all" sentinel). The default
  // selection is still Active + Removed (set below) — the common view — but the
  // other statuses must remain selectable: a site that is NOW Inactive (or
  // Pending / New / Potential) can still own period-summary rows from EARLIER
  // months when it was active, and over a multi-month period range a user needs
  // to filter by its current status to find those historical rows. The status
  // filter matches the customer's CURRENT status_id, so without these options
  // those historical rows are unreachable.
  statusOptions.value = (props.statuses ?? [])
    .filter((s) => s.id !== 'all')
    .map((s) => ({ id: s.id, value: s.name }));
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

  // Defaults — Site Status opens on BOTH "Active" (id=2) and "Removed" (id=3)
  // so Admin/Ops see active + removed sites by default. Clearing the selection
  // sends ['all'] (every status). Mirrors Customer/Index.vue's behaviour.
  filters.value.status = statusOptions.value.filter((s) => s.id === 2 || s.id === 3);
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
  // Repopulate the Custom Range pickers from the server-echoed bounds so a hard
  // reload / deep link keeps the chosen window.
  filters.value.period_from = props.periodFrom || '';
  filters.value.period_to = props.periodTo || '';
  // NOTE: no re-fetch on mount — the server already renders with the same
  // default operator set (see CustomerController::summary), so the first paint
  // matches these chips with no flash.
});

// Badge colour for the Site Status badge in the Site column.
// Mirrors Customer/Index.vue's convention (Active=green, Inactive=red,
// everything else amber) but in the bordered badge style used by the
// Contract / No Contract badges in the same cell.
// A Removed site drops its Machine ID hyperlink — the vend code may have since
// been re-bound to a different site, so the Ops Dashboard link could mislead.
function isRemovedSite(row) {
  return row?.customer?.status_name === 'Removed';
}

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

// "Show all Periods" link target — opens the Site Summary in a new tab,
// filtered to THIS site only (ref_id) with period_report=all so every stored
// month shows as its own row. status=all keeps the site visible regardless of
// its current Site Status; searched=1 stops the server re-applying the
// initial-load operator default.
function allPeriodsUrl(row) {
  const refId = refIdFor(row.customer ?? {});
  const params = new URLSearchParams({
    ref_id: refId,
    period_report: 'all',
    status: 'all',
    searched: '1',
  });
  return '/customers/summary?' + params.toString();
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

// Compact yymmdd from a YYYY-MM-DD string (e.g. "2026-04-01" -> "260401").
// Used in the tight "Payment to Loc Fees" period header to save horizontal space.
function ymd6(dateStr) {
  if (!dateStr) return '';
  return String(dateStr).replace(/-/g, '').slice(2);
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
 * Share of Total Sales for an aggregate-totals figure (e.g. Gross Earning,
 * Location Fees, Vend Earnings). Denominator is the EXCL-GST sales total
 * (totals.sales_excl_gst_cents, backend-derived per row via each operator's
 * gst_vat_rate) because Gross Earning / Location Fees / Vend Earnings are
 * all excl-GST figures — dividing them by GST-inclusive sales understated
 * every percentage. Falls back to sales_cents for non-GST operators /
 * older payloads (identical value there). Returns null when sales is
 * zero/missing so the span is hidden rather than rendering NaN%/Infinity%.
 */
function pctOfSales(cents) {
  const sales = Number(props.totals?.sales_excl_gst_cents ?? props.totals?.sales_cents) || 0;
  if (!sales || cents == null) return null;
  return ((Number(cents) / sales) * 100).toFixed(2) + '%';
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
// Avg Mthly Sales trend — up (green) when this month's running average is
// higher than last month's, down (red) when lower. prev row carries
// avg_monthly_sales_cents both for visible multi-month rows and via the
// server-attached prev_month snapshot in the single-month "Current" view.
function trendAvgSales(row)   { return trendDir(row, (r) => r.avg_monthly_sales_cents != null ? Number(r.avg_monthly_sales_cents) : null); }

/**
 * Projected full-month sales (w/GST cents) used for the Avg Mthly comparison.
 * For the CURRENT, still-running month the stored sales_cents only covers the
 * days elapsed so far, which would always look short of the monthly average
 * early in the month. We scale it to a full-month run-rate:
 *     sales_cents / days-covered * days-in-month
 * where days-covered is the period_start→period_end span (the days the figure
 * actually accrued over). Any fully-elapsed month — including past mid-month
 * contract segments — returns its actual sales_cents unchanged, since
 * projecting a completed partial range would wrongly inflate it.
 */
function projectedSalesCents(row) {
  if (!row || row.sales_cents == null) return null;
  const sales = Number(row.sales_cents);
  if (!Number.isFinite(sales)) return null;
  if (!row.period_start || !row.period_end) return sales;
  const start = moment(row.period_start);
  const end = moment(row.period_end);
  if (!start.isValid() || !end.isValid()) return sales;
  // Only the current calendar month gets projected.
  const now = moment();
  if (!(now.isSame(start, 'year') && now.isSame(start, 'month'))) return sales;
  const daysCovered = end.diff(start, 'days') + 1; // inclusive span
  const daysInMonth = start.daysInMonth();
  if (daysCovered <= 0 || daysCovered >= daysInMonth) return sales;
  return Math.round(sales / daysCovered * daysInMonth);
}

/**
 * Projected sales vs running Avg Mthly Sales — drives the green/red box on the
 * Sales cell. Both projected sales and avg_monthly_sales_cents are w/GST cents
 * so they compare like-for-like. The box is rendered on the w/GST figure to
 * match this basis. Returns 'above' / 'below', or null when the average isn't
 * computed yet (first/only month), sales is missing, or the two are equal.
 */
function salesVsAvg(row) {
  if (!row || row.avg_monthly_sales_cents == null) return null;
  const projected = projectedSalesCents(row);
  const avg = Number(row.avg_monthly_sales_cents);
  if (projected == null || !Number.isFinite(avg) || projected === avg) return null;
  return projected > avg ? 'above' : 'below';
}

/**
 * Tooltip text for the Sales box — spells out the projected run-rate and the
 * average it's being compared against so the colour isn't a mystery.
 */
function salesTooltip(row) {
  const dir = salesVsAvg(row);
  if (!dir) return '';
  const proj = projectedSalesCents(row);
  const avg = Number(row.avg_monthly_sales_cents);
  const word = dir === 'above' ? 'Above' : 'Below';
  const projLabel = proj !== Number(row.sales_cents)
    ? `projected full-month ${formatMoney(proj)}`
    : formatMoney(proj);
  return `${word} Avg Mthly Sales (${projLabel} vs avg ${formatMoney(avg)})`;
}

/**
 * Tailwind classes for the Sales (w/GST) box. A uniform-width, right-aligned
 * base is ALWAYS applied (incl. a transparent border) so every w/GST figure
 * occupies the same footprint and lines up vertically — colour is layered on
 * only when there's an above/below result. The matching excl-GST line below
 * uses SALES_LINE_CLASS so its right edge aligns under the box.
 */
const SALES_BOX_BASE = 'inline-block w-24 text-right tabular-nums rounded border border-transparent px-1.5 py-0.5';
const SALES_LINE_CLASS = 'inline-block w-24 text-right tabular-nums px-1.5';
function salesBadgeClass(row) {
  const dir = salesVsAvg(row);
  if (!dir) return SALES_BOX_BASE;
  return dir === 'above'
    ? SALES_BOX_BASE + ' bg-green-100 text-green-800 border-green-300'
    : SALES_BOX_BASE + ' bg-red-100 text-red-800 border-red-300';
}
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
/**
 * Compose the report email's recipient, subject and plain-text body from the
 * currently-open Report Content modal. Shared by both the "Email" (mailto)
 * and "Copy Email Content" (clipboard) actions so they never drift apart.
 * Body mirrors what the modal shows — title banner → meta → customer-facing
 * calculation lines (admin-only formula lines are skipped so we don't leak
 * internal math) → total → footnote.
 * Returns null when the modal isn't backing a valid (row, customer).
 */
function buildReportEmailParts() {
  const row = reportContentRow.value;
  const cust = row?.customer;
  const content = reportContent.value;
  if (!row || !cust) return null;

  const customerId = cust.id;
  const periodLabel = content?.period_label || (row.period_start + ' → ' + row.period_end);
  const subject = `Vending Machine Location Fees Report — ${cust.name || ('#' + customerId)} (${periodLabel})`;

  // Billing Company = the Edit form's "Bill From" field (primary contact's
  // company). Falls back to the company remark, then the site/customer name,
  // so the greeting is never blank when "Bill From" hasn't been filled in.
  const billingCompany = cust.contact?.company || cust.company_remark || cust.name || '';

  const lines = [];
  // Greeting + intro (static boilerplate; only the billing company is dynamic).
  lines.push(`Dear Valued Partner: "${billingCompany}"`);
  lines.push('');
  lines.push('This is an automatic email. Below is the Vending Machine Location Fees Report');
  lines.push('');
  if (content?.contract_type_label) lines.push(`Term: ${content.contract_type_label}`);
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
  // Closing thank-you (static boilerplate).
  lines.push('');
  lines.push('');
  lines.push('Thank you for your continued support and partnership with HappyIce, and bringing quality ice cream and frozen treats to your visitors, tenants, residents, and staff.');

  return { to: cust.report_email || '', subject, body: lines.join('\r\n') };
}

/**
 * Copy the email recipient, subject and content to the clipboard so operators
 * whose mail client isn't wired to `mailto:` (e.g. webmail like Gmail) can
 * paste the report into a new message manually. This is purely a clipboard
 * action — it does NOT stamp the "Last sent by …" audit, since nothing has
 * actually been sent yet.
 */
async function onModalCopyEmailClicked() {
  const parts = buildReportEmailParts();
  if (!parts) return;

  const clip = [
    'Email Addresses:',
    parts.to,
    '',
    'Subject:',
    parts.subject,
    '',
    'Content:',
    parts.body,
  ].join('\r\n');

  // Prefer the async Clipboard API; fall back to a hidden textarea + execCommand
  // for non-secure contexts / older browsers where navigator.clipboard is absent.
  let ok = false;
  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(clip);
      ok = true;
    }
  } catch (e) {
    ok = false;
  }
  if (!ok) {
    try {
      const ta = document.createElement('textarea');
      ta.value = clip;
      ta.setAttribute('readonly', '');
      ta.style.position = 'fixed';
      ta.style.top = '-9999px';
      document.body.appendChild(ta);
      ta.select();
      ok = document.execCommand('copy');
      document.body.removeChild(ta);
    } catch (e) {
      ok = false;
    }
  }

  if (ok) {
    toast.success('Email address, subject and content copied to clipboard.', { timeout: 3500 });
  } else {
    toast.error('Could not copy to clipboard. Please copy manually.', { timeout: 4500 });
  }
}

function onModalEmailClicked() {
  const row = reportContentRow.value;
  const cust = row?.customer;
  if (!row || !cust) return;
  if (!cust.is_report_email_enabled || !cust.report_email) return;
  if (!row.is_locked) return; // server re-checks; this is just defensive

  const customerId = cust.id;
  if (sendingReportFor.value.has(customerId)) return;

  // Build the mailto from the shared composer. Subject mirrors the modal title
  // + period label; body is the plain-text rendering of the modal content.
  const parts = buildReportEmailParts();
  if (!parts) return;
  const { subject, body } = parts;

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

// When on, the listing is restricted to unread-Site-Note sites (newest first).
// Seeded from the server-resolved view so the fresh-load Unread default (when
// there are unread notes) shows the button already in its "Show All" state.
const unreadMode = ref(props.unreadView);
// When on, the listing is restricted to sites that @-mention this user.
const mentionMode = ref(false);

// Toggle the unread-Site-Note view on/off, then re-run the search. Unread and
// Mentioned are mutually exclusive — turning one on clears the other.
function toggleUnread() {
  unreadMode.value = !unreadMode.value;
  if (unreadMode.value) mentionMode.value = false;
  onSearchFilterUpdated();
}

// Toggle the "@Me Mentioned" view on/off, then re-run the search.
function toggleMentioned() {
  mentionMode.value = !mentionMode.value;
  if (mentionMode.value) unreadMode.value = false;
  onSearchFilterUpdated();
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
      billing_company: filters.value.billing_company,
      settlement_ref: filters.value.settlement_ref,
      tags: (filters.value.tags ?? []).map ? filters.value.tags.map(t => t.id ?? t) : filters.value.tags,
      is_cms: filters.value.is_cms?.id,
      status: (filters.value.status?.length ? filters.value.status.map((s) => s.id) : ['all']),
      location_types: (filters.value.location_types ?? []).map((lt) => lt.id),
      operators: (filters.value.operators ?? []).filter(Boolean).map((o) => o.id),
      vendPrefixes: (filters.value.vendPrefixes ?? []).map((vp) => vp.id),
      period_report: filters.value.period_report?.id || 'current',
      period_from: filters.value.period_from,
      period_to: filters.value.period_to,
      replicated_only: filters.value.replicated_only?.id,
      period_locked: filters.value.period_locked?.id,
      location_fee_paid: filters.value.location_fee_paid?.id,
      paid_date_from: filters.value.paid_date_from,
      paid_date_to: filters.value.paid_date_to,
      // Marks this as an explicit user search so the server does NOT re-apply
      // the initial-load operator default — lets "deselect all operators" mean
      // "show all" instead of snapping back to the default set. It also keeps
      // the server from sliding the unread window on an in-page action.
      searched: 1,
      // Restrict results to sites with an unread Site Note when the toggle is on.
      unread: unreadMode.value ? 1 : 0,
      // Restrict results to sites that @-mention the user when the toggle is on.
      mentioned: mentionMode.value ? 1 : 0,
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
  unreadMode.value = false;
  mentionMode.value = false;
  router.get('/customers/summary');
}

// Persist the inline-edited customer-level Note. Mirrors the
// onRemarksChanged setup on Vend/ProductAvailability.vue — POST to a
// dedicated endpoint, then router.reload only the `summaries` prop so
// the audit line (last updated by / at) refreshes without resetting the
// rest of the page state (filters, scroll, etc.).
// ── Machine binding history popup (per site) ──────────────────────────────
const bindingHistoryOpen = ref(false);
const bindingHistoryLoading = ref(false);
const bindingHistory = ref([]);
const bindingHistorySite = ref('');

function openBindingHistory(customer) {
  if (!customer?.id) return;
  bindingHistorySite.value = (refIdFor(customer) || '') + (customer.name ? ' · ' + customer.name : '');
  bindingHistory.value = [];
  bindingHistoryLoading.value = true;
  bindingHistoryOpen.value = true;
  axios.get('/customers/' + customer.id + '/vend-bindings')
    .then((res) => {
      bindingHistory.value = res.data?.data ?? [];
    })
    .catch((error) => {
      console.error('Error loading binding history:', error);
      bindingHistory.value = [];
    })
    .finally(() => {
      bindingHistoryLoading.value = false;
    });
}

// ── Payment History popup (per site settlement ledger) ────────────────────
const settlementOpen = ref(false);
const settlementLoading = ref(false);
const settlementRows = ref([]);
const settlementSite = ref('');
const settlementOutstanding = ref(0);
const settlementSince = ref(null);
const settlementCustomerId = ref(null);
const settlementLogs = ref([]);          // change-history audit trail (newest first)
const showSettlementLogs = ref(false);   // collapsible toggle
const settlementSortDir = ref('desc');   // ledger date sort: 'desc' = latest on top (default)

// Ledger rows in display order. Backend returns chronological (asc) with a
// running balance per row; we only reorder for display, the per-row balance
// already reflects the balance as of that entry (like a bank statement).
const sortedSettlementRows = computed(() => {
  const rows = settlementRows.value.map((s, i) => ({ s, i }));
  rows.sort((a, b) => {
    const da = a.s.entry_date ? new Date(a.s.entry_date).getTime() : 0;
    const db = b.s.entry_date ? new Date(b.s.entry_date).getTime() : 0;
    if (da !== db) return settlementSortDir.value === 'desc' ? db - da : da - db;
    // tiebreak on original (chronological) index to stay stable
    return settlementSortDir.value === 'desc' ? b.i - a.i : a.i - b.i;
  });
  return rows.map((r) => r.s);
});

function toggleSettlementSort() {
  settlementSortDir.value = settlementSortDir.value === 'desc' ? 'asc' : 'desc';
}

const SETTLEMENT_LOG_LABELS = {
  payment: 'Payment recorded',
  waiver: 'Waived',
  payment_reversed: 'Payment reversed',
  edited: 'Entry edited',
  created: 'Entry created',
  deleted: 'Entry deleted',
};
function settlementLogLabel(action) {
  return SETTLEMENT_LOG_LABELS[action] || action || '—';
}

// ── Add / edit / delete a settlement entry ──
// Edit + delete apply to manually-owned rows: opening_balance, adjustment, and
// payment/waiver that were hand-entered from this popup (source === 'manual').
// Auto-posted rows (monthly loc fee, per-row Paid credit) stay locked.
const canEditSettlement = computed(() => canLock.value); // admin-access customers
const canAddSettlement = computed(() => canLock.value);  // same gate as Paid/Lock
const SETTLEMENT_EDITABLE_TYPES = ['opening_balance', 'adjustment'];
const SETTLEMENT_MANUAL_CREDIT_TYPES = ['payment', 'waiver'];
const editingSettlementId = ref(null);
const editSettlementAmount = ref('');
const editSettlementRemarks = ref('');
const editSettlementSaving = ref(false);

// ── Add-entry form state (Record Paid / Record Waived / Record Adjustment) ──
const addingSettlement = ref(null);        // 'payment' | 'waiver' | 'adjustment' | null
const addSettlementDate = ref('');
const addSettlementDescription = ref('');
const addSettlementAmount = ref('');
const addSettlementDirection = ref('credit'); // adjustment only: 'credit' | 'debit'
const addSettlementSaving = ref(false);
const deletingSettlementId = ref(null);

// Form theming + copy by the type being added. Paid/Waived are always credits;
// an adjustment can be a charge or a credit (direction-driven).
const addFormPanelClass = computed(() => ({
  payment: 'border-emerald-200 bg-emerald-50/50',
  waiver: 'border-sky-200 bg-sky-50/50',
  adjustment: 'border-slate-200 bg-slate-50/60',
}[addingSettlement.value] || 'border-gray-200'));
const addFormSubmitClass = computed(() => ({
  payment: 'bg-emerald-600 hover:bg-emerald-700',
  waiver: 'bg-sky-600 hover:bg-sky-700',
  adjustment: 'bg-slate-600 hover:bg-slate-700',
}[addingSettlement.value] || 'bg-indigo-600 hover:bg-indigo-700'));
const addFormHint = computed(() => {
  if (addingSettlement.value === 'adjustment') {
    return addSettlementDirection.value === 'debit'
      ? 'New charge — increases what we owe this site.'
      : 'New credit — reduces what we owe this site.';
  }
  return 'New credit entry — reduces what we owe this site.';
});
const addFormPlaceholder = computed(() => ({
  payment: 'e.g. Payment — bank transfer',
  waiver: 'e.g. Waived — goodwill May 2026',
  adjustment: 'e.g. Adjustment — correction for Apr 2026',
}[addingSettlement.value] || 'Description'));

function isSettlementEditable(s) {
  if (!canEditSettlement.value) return false;
  if (SETTLEMENT_EDITABLE_TYPES.includes(s.entry_type)) return true;
  // Hand-entered paid/waived credits are editable; auto-posted ones are not.
  return SETTLEMENT_MANUAL_CREDIT_TYPES.includes(s.entry_type) && s.source === 'manual';
}
function isSettlementDeletable(s) {
  // Same set as editable — only manually-owned rows can be removed here.
  return isSettlementEditable(s);
}

function startAddSettlement(type) {
  cancelEditSettlement();           // never have both inline forms open at once
  addingSettlement.value = type;    // 'payment' | 'waiver' | 'adjustment'
  addSettlementDate.value = moment().format('YYYY-MM-DD');
  addSettlementDescription.value = '';
  addSettlementAmount.value = '';
  addSettlementDirection.value = 'credit';
}
function cancelAddSettlement() {
  addingSettlement.value = null;
  addSettlementDate.value = '';
  addSettlementDescription.value = '';
  addSettlementAmount.value = '';
  addSettlementDirection.value = 'credit';
}
function submitAddSettlement() {
  if (addSettlementSaving.value || !settlementCustomerId.value) return;
  const type = addingSettlement.value;
  if (!type) return;
  if (!addSettlementDate.value) {
    toast.error('Please choose a date.', { timeout: 3500 });
    return;
  }
  const desc = (addSettlementDescription.value || '').trim();
  if (!desc) {
    toast.error('Please enter a description.', { timeout: 3500 });
    return;
  }
  const cents = Math.round((Number(addSettlementAmount.value) || 0) * minorPerUnit);
  if (!(cents > 0)) {
    toast.error('Amount must be greater than zero.', { timeout: 3500 });
    return;
  }
  addSettlementSaving.value = true;
  axios.post('/customers/' + settlementCustomerId.value + '/settlements', {
    entry_type: type,
    entry_date: addSettlementDate.value,
    item: desc,
    amount_cents: cents,
    // Direction only matters for an adjustment (charge vs credit).
    direction: type === 'adjustment' ? addSettlementDirection.value : undefined,
  })
    .then((res) => {
      cancelAddSettlement();
      reloadOpenSettlements();
      router.reload({ only: ['summaries', 'settlementBalances'], preserveScroll: true, preserveState: true });
      toast.success(res?.data?.message || 'Entry added.', { timeout: 2500 });
    })
    .catch((error) => {
      toast.error(error?.response?.data?.message || 'Failed to add entry.', { timeout: 4500 });
    })
    .finally(() => { addSettlementSaving.value = false; });
}

function deleteSettlementRow(s) {
  if (deletingSettlementId.value) return;
  if (!window.confirm('Delete this ' + settlementTypeLabel(s.entry_type).toLowerCase() + ' entry? This cannot be undone.')) return;
  deletingSettlementId.value = s.id;
  axios.post('/customers/settlements/' + s.id + '/delete')
    .then((res) => {
      reloadOpenSettlements();
      router.reload({ only: ['summaries', 'settlementBalances'], preserveScroll: true, preserveState: true });
      toast.success(res?.data?.message || 'Entry deleted.', { timeout: 2500 });
    })
    .catch((error) => {
      toast.error(error?.response?.data?.message || 'Failed to delete entry.', { timeout: 4500 });
    })
    .finally(() => { deletingSettlementId.value = null; });
}
function startEditSettlement(s) {
  editingSettlementId.value = s.id;
  editSettlementAmount.value = (Math.abs(Number(s.amount_cents) || 0) / minorPerUnit).toFixed(currencyExp);
  editSettlementRemarks.value = s.remarks || '';
}
function cancelEditSettlement() {
  editingSettlementId.value = null;
  editSettlementAmount.value = '';
  editSettlementRemarks.value = '';
}
function saveEditSettlement(s) {
  if (editSettlementSaving.value) return;
  const cents = Math.max(0, Math.round((Number(editSettlementAmount.value) || 0) * minorPerUnit));
  // Preserve the original sign (opening balance is +debit; an adjustment may be
  // a -credit). We only let the magnitude be edited here.
  const signed = Number(s.amount_cents) < 0 ? -cents : cents;
  editSettlementSaving.value = true;
  axios.post('/customers/settlements/' + s.id + '/update', {
    amount_cents: signed,
    remarks: editSettlementRemarks.value || null,
  })
    .then(() => {
      cancelEditSettlement();
      reloadOpenSettlements(); // re-derive balances in the modal
      router.reload({ only: ['summaries', 'settlementBalances'], preserveScroll: true, preserveState: true });
      toast.success('Settlement updated.', { timeout: 2500 });
    })
    .catch((error) => {
      toast.error(error?.response?.data?.message || 'Failed to update settlement.', { timeout: 4500 });
    })
    .finally(() => { editSettlementSaving.value = false; });
}
// Re-fetch the currently-open ledger so the running balances + outstanding
// reflect an edit without closing the modal.
function reloadOpenSettlements() {
  if (!settlementCustomerId.value) return;
  axios.get('/customers/' + settlementCustomerId.value + '/settlements')
    .then((res) => {
      settlementRows.value = res.data?.data ?? [];
      settlementOutstanding.value = Number(res.data?.outstanding_cents ?? 0);
      settlementSince.value = res.data?.since_date ?? null;
      settlementLogs.value = res.data?.logs ?? [];
    })
    .catch(() => {});
}

// Export the open ledger: Excel downloads (.xlsx); PDF opens a printable
// Statement-of-Account in a new tab (browser Save-as-PDF).
function exportSettlementsExcel() {
  if (!settlementCustomerId.value) return;
  window.location.href = '/customers/' + settlementCustomerId.value + '/settlements/excel';
}
function exportSettlementsPdf() {
  if (!settlementCustomerId.value) return;
  window.open('/customers/' + settlementCustomerId.value + '/settlements/pdf', '_blank');
}

// Three-state balance: we owe (>0), settled (0), or in credit / overpaid (<0).
const settlementState = computed(() => {
  const v = settlementOutstanding.value;
  if (v > 0) return 'owing';
  if (v < 0) return 'credit';
  return 'settled';
});
const settlementStatusLabel = computed(() => ({
  owing: 'We owe the site owner',
  settled: 'Fully settled',
  credit: 'In credit · overpaid',
}[settlementState.value]));

// Shared three-state helper for any signed balance (cents): owe / settled /
// credit (overpaid). Used by the action-cell pill and the Paid modal.
function balanceState(cents) {
  const v = Number(cents) || 0;
  if (v > 0) return 'owing';
  if (v < 0) return 'credit';
  return 'settled';
}
function balancePillClass(cents) {
  return {
    owing: 'bg-rose-50 text-rose-700 border-rose-200',
    settled: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    credit: 'bg-sky-50 text-sky-700 border-sky-200',
  }[balanceState(cents)];
}
function balancePillLabel(cents) {
  return { owing: 'Owe ', settled: 'Settled ', credit: 'Credit ' }[balanceState(cents)];
}

// Live outstanding for the button pill — read from the server-provided map
// (keyed by customer id). Absent sites (no ledger) default to 0.
function outstandingFor(customer) {
  if (!customer?.id) return 0;
  const v = props.settlementBalances?.[customer.id];
  return v == null ? 0 : Number(v);
}

const SETTLEMENT_TYPE_LABELS = {
  opening_balance: 'Opening',
  location_fee: 'Loc Fee',
  payment: 'Payment',
  waiver: 'Waived',
  adjustment: 'Adjust',
};
function settlementTypeLabel(type) {
  return SETTLEMENT_TYPE_LABELS[type] || type || '—';
}
function settlementTypeBadgeClass(type) {
  switch (type) {
    case 'payment': return 'bg-emerald-100 text-emerald-700';
    case 'waiver': return 'bg-purple-100 text-purple-700';
    case 'opening_balance': return 'bg-amber-100 text-amber-700';
    case 'adjustment': return 'bg-slate-100 text-slate-600';
    default: return 'bg-sky-100 text-sky-700'; // location_fee
  }
}

function openPaymentHistory(customer) {
  if (!customer?.id) return;
  cancelEditSettlement();
  cancelAddSettlement();
  settlementCustomerId.value = customer.id;
  settlementSite.value = (refIdFor(customer) || '') + (customer.name ? ' · ' + customer.name : '');
  settlementRows.value = [];
  settlementLogs.value = [];
  showSettlementLogs.value = false;
  settlementOutstanding.value = outstandingFor(customer); // seed from pill while loading
  settlementSince.value = null;
  settlementLoading.value = true;
  settlementOpen.value = true;
  axios.get('/customers/' + customer.id + '/settlements')
    .then((res) => {
      settlementRows.value = res.data?.data ?? [];
      settlementOutstanding.value = Number(res.data?.outstanding_cents ?? 0);
      settlementSince.value = res.data?.since_date ?? null;
      settlementLogs.value = res.data?.logs ?? [];
    })
    .catch((error) => {
      console.error('Error loading payment history:', error);
      settlementRows.value = [];
      settlementLogs.value = [];
    })
    .finally(() => {
      settlementLoading.value = false;
    });
}

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

// Persist the inline-edited "Remarks for Loc Fees". Same shape as
// onNotesChanged — POST to a dedicated endpoint, then reload only the
// `summaries` prop so the audit line (last updated by / at) refreshes
// without resetting filters/scroll. Standalone field; no unread tracking.
function onLocFeeRemarksChanged(customer) {
  if (!customer?.id) return;
  axios.post('/customers/' + customer.id + '/update-loc-fee-remarks', {
    loc_fee_remarks: customer.loc_fee_remarks,
  })
    .then(() => {
      router.reload({ only: ['summaries'], preserveScroll: true });
    })
    .catch((error) => {
      console.error('Error updating loc fee remarks:', error);
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
// Waived state for the popup — when ticked, waived_remarks becomes mandatory.
const paidModalWaived = ref(false);
const paidModalRemarks = ref('');
// Optional free-text comment — applies to both Paid and Waived; saved onto
// the settlement ledger row so it shows in Payment History.
const paidModalComment = ref('');
// Amount paid / waived (in dollars, as typed). Pre-filled with the period's
// Net Loc Fee; posted to the settlement ledger as a credit on confirm.
const paidModalAmount = ref('');

const paidModalLabel = computed(() => {
  const row = paidModalRow.value;
  if (!row) return '';
  const label = row.customer?.name || ('#' + row.customer?.id);
  return `${label} — ${periodReportLabel(row)}`;
});

// Site-level outstanding for the row being paid (the pay action lives on a past
// period row, so we surface the per-site balance here for context).
const paidModalOutstanding = computed(() => outstandingFor(paidModalRow.value?.customer));

// Live cents of the amount currently typed — drives the "remaining after" line.
const paidModalAmountCents = computed(() =>
  Math.max(0, Math.round((Number(paidModalAmount.value) || 0) * minorPerUnit))
);

// Open the full Payment History ledger from inside the Paid modal. Stacks on
// top (the Paid modal stays open underneath) so the in-progress entry is kept.
function openPaymentHistoryFromPaid() {
  if (paidModalRow.value?.customer) {
    openPaymentHistory(paidModalRow.value.customer);
  }
}

// Mark a locked row as Paid — STEP 1: open the payment-date popup. Requires
// the row to be Locked + not already Paid (UI hides the button otherwise).
function onPaidClicked(row) {
  if (!row?.id || !row.is_locked || row.is_paid) return;
  if (lockingFor.value.has(row.id)) return;
  paidModalRow.value = row;
  paidModalDate.value = moment().format('YYYY-MM-DD'); // pre-fill today
  paidModalWaived.value = false;
  paidModalRemarks.value = '';
  paidModalComment.value = '';
  // Pre-fill the amount with this period's Net Loc Fee (gross − subsidize),
  // floored at 0 — the typical case is "paid exactly what we owe".
  {
    const net = Number(row.location_fees_cents || 0) - Number(row.external_subsidize_cents || 0);
    paidModalAmount.value = net > 0 ? (net / minorPerUnit).toFixed(currencyExp) : '0';
  }
  showPaidModal.value = true;
}

function onPaidModalClose() {
  showPaidModal.value = false;
  paidModalRow.value = null;
  paidModalWaived.value = false;
  paidModalRemarks.value = '';
  paidModalComment.value = '';
  paidModalAmount.value = '';
}

// STEP 2: confirm from the popup. Same permission as Lock; server re-checks.
// Reuses lockingFor to share the per-row spinner with Lock/Unlock.
function onPaidModalConfirm() {
  const row = paidModalRow.value;
  if (!row?.id || !row.is_locked || row.is_paid) return;
  if (lockingFor.value.has(row.id)) return;

  // Waived requires a remark — block here (server re-validates too).
  const isWaived = paidModalWaived.value;
  const remarks = (paidModalRemarks.value || '').trim();
  if (isWaived && !remarks) {
    toast.error('Please enter a remark explaining why this period is waived.', { timeout: 4000 });
    return;
  }

  // Optional free-text comment — applies to Paid and Waived alike.
  const comment = (paidModalComment.value || '').trim();

  // Empty/cleared field → today (server defaults too — belt and braces).
  const paidDate = paidModalDate.value || moment().format('YYYY-MM-DD');

  // Amount paid/waived → settlement ledger credit, sent as integer minor units
  // (cents) so the server stores it verbatim. 0 / blank → no ledger entry.
  const paidAmountCents = Math.max(0, Math.round((Number(paidModalAmount.value) || 0) * minorPerUnit));

  showPaidModal.value = false;
  paidModalRow.value = null;
  paidModalWaived.value = false;
  paidModalRemarks.value = '';
  paidModalComment.value = '';
  paidModalAmount.value = '';

  lockingFor.value.add(row.id);
  // Partial reload — Paid flips paid_at / paid_date / is_waived AND posts a
  // settlement credit, so refresh both `summaries` (green-check / Waived badge)
  // and `settlementBalances` (the Payment-History pill) on success.
  router.post('/customers/summary/' + row.id + '/paid', {
    paid_date: paidDate,
    is_waived: isWaived,
    waived_remarks: isWaived ? remarks : null,
    comment: comment || null,
    paid_amount_cents: paidAmountCents,
  }, {
    only: ['summaries', 'settlementBalances'],
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => toast.success(isWaived ? 'Period marked Waived.' : 'Period marked Paid.', { timeout: 3000 }),
    onError: (errors) => toast.error(errors?.paid || errors?.paid_date || errors?.waived_remarks || errors?.paid_amount_cents || 'Failed to mark Paid.', { timeout: 4000 }),
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
    only: ['summaries', 'settlementBalances'],
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
    billing_company: filters.value.billing_company,
    settlement_ref: filters.value.settlement_ref,
    tags: (filters.value.tags ?? []).map ? filters.value.tags.map(t => t.id ?? t) : filters.value.tags,
    is_cms: filters.value.is_cms?.id,
    status: (filters.value.status?.length ? filters.value.status.map((s) => s.id) : ['all']),
    location_types: (filters.value.location_types ?? []).map((lt) => lt.id),
    operators: (filters.value.operators ?? []).filter(Boolean).map((o) => o.id),
    vendPrefixes: (filters.value.vendPrefixes ?? []).map((vp) => vp.id),
    period_report: filters.value.period_report?.id || 'current',
    period_from: filters.value.period_from,
    period_to: filters.value.period_to,
    contract_commission_types: (filters.value.contract_commission_types ?? [])
      .map((t) => (t && t.id !== undefined ? t.id : t)),
    contract_attachment: filters.value.contract_attachment?.id,
    replicated_only: filters.value.replicated_only?.id,
    period_locked: filters.value.period_locked?.id,
    location_fee_paid: filters.value.location_fee_paid?.id,
    paid_date_from: filters.value.paid_date_from,
    paid_date_to: filters.value.paid_date_to,
    // Unread / "@Me Mentioned" view toggles — sent so the Excel export
    // restricts to the same row set the screen shows while either is on.
    unread: unreadMode.value ? 1 : 0,
    mentioned: mentionMode.value ? 1 : 0,
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

/**
 * Format a single row's report content into the same plain-text body the
 * email composer produces (greeting → meta → customer-facing lines → total →
 * footnote → thank-you). Admin-only formula lines are skipped so internal
 * PS-term math never leaks. Shared shape with buildReportEmailParts and the
 * batch builder so all three read identically.
 */
function formatSingleReportBody(content, cust) {
  const machine = machineInfoOf(cust);
  const lines = [];
  lines.push(`Dear Valued Partner: "${billingCompanyOf(cust)}"`);
  lines.push('');
  lines.push('This is an automatic email. Below is the Vending Machine Location Fees Report');
  lines.push('');
  if (content.contract_type_label) lines.push(`Term: ${content.contract_type_label}`);
  lines.push('');
  lines.push(`Period (YYMM): ${content.period_label}`);
  if (content.active_days != null && content.month_days != null) {
    lines.push(`Total number of days: ${content.active_days} / ${content.month_days}`);
  }
  if (machine.id) lines.push(`Machine ID: ${machine.id}`);
  if (machine.prefix) lines.push(`Machine Prefix: ${machine.prefix}`);
  lines.push('');
  if (Array.isArray(content.lines)) {
    for (const l of content.lines) {
      if (l?.formula_internal) continue; // admin-only — never in the body
      lines.push(`${l.label}: ${l.formula ? l.formula + ' = ' : ''}${l.value}`);
    }
  }
  if (content.has_total) {
    lines.push('');
    lines.push(`Total: ${content.total_value}`);
  }
  if (content.footnote) {
    lines.push('');
    lines.push(content.footnote);
  }
  lines.push('');
  lines.push('');
  lines.push('Thank you for your continued support and partnership with HappyIce, and bringing quality ice cream and frozen treats to your visitors, tenants, residents, and staff.');
  return lines.join('\r\n');
}

/**
 * Row-level "Copy Content" — for Sites with no report-email opt-in. Fetches
 * the same report payload the modal/email use, formats it, and drops it on
 * the clipboard. Purely a clipboard action: no "sent" audit is recorded.
 */
async function onRowCopyContentClicked(row) {
  const cust = row?.customer;
  if (!cust?.has_report_content) return;
  if (copyingContentFor.value.has(cust.id)) return;

  copyingContentFor.value.add(cust.id);
  copyingContentFor.value = new Set(copyingContentFor.value);

  try {
    const res = await window.axios.get('/customers/' + cust.id + '/performance-report-content', {
      params: { period_start: row.period_start, period_end: row.period_end },
    });
    const content = res?.data ?? null;
    if (!content || content.is_available === false) {
      toast.error('No report content available to copy for this row.', { timeout: 4000 });
      return;
    }

    const text = formatSingleReportBody(content, cust);

    let ok = false;
    try {
      if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(text);
        ok = true;
      }
    } catch (e) {
      ok = false;
    }
    if (!ok) {
      try {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.top = '-9999px';
        document.body.appendChild(ta);
        ta.select();
        ok = document.execCommand('copy');
        document.body.removeChild(ta);
      } catch (e) {
        ok = false;
      }
    }

    if (ok) {
      toast.success('Report content copied to clipboard.', { timeout: 3500 });
    } else {
      toast.error('Could not copy to clipboard. Please copy manually.', { timeout: 4500 });
    }
  } catch (err) {
    const msg = err?.response?.data?.message
      || 'Failed to load report content. Please try again.';
    toast.error(msg, { timeout: 4000 });
  } finally {
    copyingContentFor.value.delete(cust.id);
    copyingContentFor.value = new Set(copyingContentFor.value);
  }
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
// Export Batch Report Content
//
// Stitches the Report Content of every ticked, report-eligible row into a
// SINGLE client-facing email body (per brian: all selected machines are
// treated as one client → one greeting, one closing thank-you, one block per
// machine in between). The per-machine block mirrors buildReportEmailParts so
// a batch body reads identically to a single-row email — admin-only formula
// lines are skipped so internal PS-term math never leaks to the customer.
// ───────────────────────────────────────────────────────────────────────
const showBatchReportModal = ref(false);
const batchReportLoading = ref(false);
const batchReportText = ref('');
const batchReportSkipped = ref('');
const batchReportMachineCount = ref(0);

// Billing Company resolution — identical fallback chain to buildReportEmailParts
// ("Bill From" → company remark → site name) so the batch greeting matches the
// single-row email greeting.
function billingCompanyOf(cust) {
  return cust?.contact?.company || cust?.company_remark || cust?.name || '';
}

// Machine ID / Prefix for a row — mirrors onReportContentClicked: join all
// bound vends for multi-machine sites, else the single primary vend.
function machineInfoOf(cust) {
  if (Array.isArray(cust?.vends) && cust.vends.length > 1) {
    return {
      id: cust.vends.map(v => v.code).filter(Boolean).join(', '),
      prefix: cust.vends.map(v => v.prefix).filter(Boolean).join(', '),
    };
  }
  return { id: cust?.vend?.code || '', prefix: cust?.vend?.prefix || '' };
}

function onExportBatchReportClicked() {
  const rows = selectedReportRows.value;
  if (!rows.length || batchReportLoading.value) return;

  batchReportLoading.value = true;
  batchReportText.value = '';
  batchReportSkipped.value = '';
  batchReportMachineCount.value = 0;
  showBatchReportModal.value = true;

  const payloadRows = rows.map(r => ({
    customer_id: r.customer_id ?? r.customer?.id,
    period_start: r.period_start,
    period_end: r.period_end,
  }));

  window.axios.post('/customers/summary/batch-report-content', { rows: payloadRows })
    .then(res => {
      const resultRows = res?.data?.rows ?? [];
      // Index backend content by composite key so we can pair it with the
      // frontend row (which carries machine + billing info) regardless of order.
      const byKey = {};
      for (const rr of resultRows) {
        byKey[rowKey(rr)] = rr.content;
      }

      const greetingCompany = billingCompanyOf(rows[0]?.customer);
      const lines = [];
      lines.push(`Dear Valued Partner: "${greetingCompany}"`);
      lines.push('');
      lines.push('This is an automatic email. Below is the Vending Machine Location Fees Report');

      let included = 0;
      let skipped = 0;

      rows.forEach((row) => {
        const content = byKey[rowKey(row)];
        // Defensive: skip rows the server couldn't render (e.g. F/S that slipped
        // through, or a customer deleted mid-flight).
        if (!content || !content.is_available) {
          skipped += 1;
          return;
        }

        const cust = row.customer;
        const machine = machineInfoOf(cust);

        lines.push('');
        lines.push('────────────────────────────');
        lines.push('');
        if (content.contract_type_label) lines.push(`Term: ${content.contract_type_label}`);
        lines.push('');
        lines.push(`Period (YYMM): ${content.period_label}`);
        if (content.active_days != null && content.month_days != null) {
          lines.push(`Total number of days: ${content.active_days} / ${content.month_days}`);
        }
        if (machine.id) lines.push(`Machine ID: ${machine.id}`);
        if (machine.prefix) lines.push(`Machine Prefix: ${machine.prefix}`);
        lines.push('');
        if (Array.isArray(content.lines)) {
          for (const l of content.lines) {
            if (l?.formula_internal) continue; // admin-only — never in the body
            lines.push(`${l.label}: ${l.formula ? l.formula + ' = ' : ''}${l.value}`);
          }
        }
        if (content.has_total) {
          lines.push('');
          lines.push(`Total: ${content.total_value}`);
        }
        if (content.footnote) {
          lines.push('');
          lines.push(content.footnote);
        }
        included += 1;
      });

      if (!included) {
        batchReportText.value = '';
        batchReportMachineCount.value = 0;
        batchReportSkipped.value = '';
        return;
      }

      lines.push('');
      lines.push('────────────────────────────');
      lines.push('');
      lines.push('Thank you for your continued support and partnership with HappyIce, and bringing quality ice cream and frozen treats to your visitors, tenants, residents, and staff.');

      batchReportText.value = lines.join('\r\n');
      batchReportMachineCount.value = included;
      batchReportSkipped.value = skipped
        ? `${skipped} selected row(s) had no renderable report content and were skipped.`
        : '';
    })
    .catch(err => {
      const msg = err?.response?.data?.message
        || 'Failed to build the batch report. Please try again.';
      toast.error(msg, { timeout: 4000 });
      showBatchReportModal.value = false;
    })
    .finally(() => {
      batchReportLoading.value = false;
    });
}

async function onCopyBatchReportClicked() {
  const text = batchReportText.value;
  if (!text) return;

  let ok = false;
  try {
    if (navigator.clipboard?.writeText) {
      await navigator.clipboard.writeText(text);
      ok = true;
    }
  } catch (e) {
    ok = false;
  }
  if (!ok) {
    try {
      const ta = document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', '');
      ta.style.position = 'fixed';
      ta.style.top = '-9999px';
      document.body.appendChild(ta);
      ta.select();
      ok = document.execCommand('copy');
      document.body.removeChild(ta);
    } catch (e) {
      ok = false;
    }
  }

  if (ok) {
    toast.success('Batch report content copied to clipboard.', { timeout: 3500 });
  } else {
    toast.error('Could not copy to clipboard. Please copy manually.', { timeout: 4500 });
  }
}

function onBatchReportModalClose() {
  showBatchReportModal.value = false;
  batchReportText.value = '';
  batchReportSkipped.value = '';
  batchReportMachineCount.value = 0;
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
