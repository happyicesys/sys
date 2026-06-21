<template>
  <Head title="Site" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Editing Site
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
        <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 p-5 mb-3">
            <form @submit.prevent="submit" id="submit">
              <div class="pb-5">
                <!-- Site Header -->
                <div class="relative mb-5">
                  <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-gray-300"></div>
                  </div>
                  <div class="relative flex justify-start">
                    <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> Site </span>
                  </div>
                </div>

                <!-- Site Form Fields -->
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-6 pb-2 mb-2">
                  <div class="sm:col-span-6 flex space-x-1">
                    <div
                      class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit"
                      :class="statusBadgeClass"
                    >
                      <span> {{ statusLabel }} </span>
                    </div>
                    <div
                      class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit bg-green-300"
                      v-if="customer.person_id"
                    >
                      <a
                          target="_blank"
                          :href="cmsEndpoint + '/person/' + customer.person_id + '/edit'"
                          class=""
                          v-if="customer.person_id"
                        >
                        <div class="flex flex-col">
                          From CMS
                        </div>
                      </a>
                    </div>
                  </div>

                  <div class="sm:col-span-3" v-if="customer.id">
                    <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                      Site ID#
                    </label>
                    <div class="mt-1">
                      <input
                        type="text"
                        class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                        :value="customer ? customer.id + 20000 : ''"
                        disabled
                      />
                    </div>
                  </div>

                  <!-- CMS Linking ID — CMS person id used by "Create API Invoice".
                       Editable; paste the "SYS Linking ID" from the CMS person.
                       NOTE: update() validates this nested as customer.person_id,
                       so the error key is 'customer.person_id' (NOT 'person_id').
                       Reading the wrong key made unique/integer rejections
                       invisible — the save silently "did nothing". -->
                  <div class="sm:col-span-3">
                    <FormInput v-model="form.person_id" inputType="number" placeholderStr="CMS person id — links invoicing">
                      <span class="inline-flex items-center gap-2">
                        CMS Linking ID
                        <a v-if="form.person_id && cmsEndpoint" :href="cmsEndpoint + '/person/' + form.person_id + '/edit'" target="_blank" rel="noopener noreferrer" class="text-blue-600 text-xs font-normal underline">
                          Open in CMS ↗
                        </a>
                      </span>
                    </FormInput>
                    <!-- person_id error is rendered with v-html (not FormInput's
                         text slot) so the duplicate-binding message's <a> link to
                         the already-bound site is clickable. Server escapes the
                         name; only safe markup reaches here. Key is the nested
                         customer.person_id (update() validates it nested). -->
                    <div
                      v-if="form.errors['customer.person_id'] || form.errors.person_id"
                      class="text-sm text-red-600 mt-1"
                      v-html="form.errors['customer.person_id'] || form.errors.person_id"
                    ></div>
                  </div>

                  <!--
                    Editable Site Name. Previously locked + hidden for
                    CMS-linked customers (person_id) and surfaced only as a
                    read-only mirror. CMS → mark1 data sync is now disabled, so
                    the name is editable for every customer, including
                    CMS-linked ones. The "From CMS" badge above is retained for
                    traceability of the original CMS link.
                  -->
                  <div class="sm:col-span-4 grid grid-cols-1 gap-3 sm:grid-cols-6" v-if="customer.id || (!customer.id && isExisting != 1)">
                    <div class="sm:col-span-5">
                      <FormInput v-model="form.name" :error="form.errors.name" required="true" placeholderStr="Site Name">
                        Site Name
                      </FormInput>
                    </div>
                  </div>

                  <!-- Created At and Begin Date share a row. Begin Date is now
                       read-only (display only) — status changes manage the date,
                       so it must not be hand-edited here. -->
                  <div class="sm:col-span-3" v-if="customer.id">
                    <label for="text" class="flex justify-start text-sm font-medium text-gray-700"> Created At </label>
                    <div class="mt-1">
                      <input
                        type="text"
                        class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                        :value="formatDatetime(customer.created_at)"
                        disabled
                      />
                    </div>
                  </div>
                  <div class="sm:col-span-3">
                    <label for="text" class="flex justify-start text-sm font-medium text-gray-700"> Begin Date </label>
                    <div class="mt-1">
                      <input
                        type="text"
                        class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                        :value="form.begin_date ? fmtDate(form.begin_date) : ''"
                        disabled
                      />
                    </div>
                  </div>

                  <!-- Divider before the Status field -->
                  <div class="sm:col-span-6">
                    <hr class="border-t border-gray-300" />
                  </div>

                  <div class="sm:col-span-3">
                    <label for="text" class="flex justify-start items-center text-sm font-medium text-gray-700">
                      Status (Site)
                      <!-- Status History — opens a popup listing every status
                           change with its date, who set it, and when. -->
                      <button
                        type="button"
                        class="ml-2 inline-flex items-center gap-1 text-xs text-indigo-600 hover:text-indigo-800"
                        v-tooltip="'View status change history'"
                        @click="openStatusHistory"
                      >
                        <ClockIcon class="h-4 w-4" aria-hidden="true" />
                        <span>Status History</span>
                      </button>
                    </label>
                    <MultiSelect
                      v-model="form.status_id"
                      :options="statusOptions"
                      trackBy="id"
                      valueProp="id"
                      label="value"
                      placeholder="Select"
                      open-direction="bottom"
                      class="mt-1"
                    ></MultiSelect>
                    <div class="text-sm text-red-600" v-if="form.errors['customer.status_id']">
                      {{ form.errors['customer.status_id'] }}
                    </div>
                    <!-- Active / Removed dates captured via the status prompt;
                         shown here for reference. -->
                    <p class="mt-1 text-xs text-gray-500" v-if="form.active_date">
                      Active Date: {{ fmtDate(form.active_date) }}
                      <button type="button" class="ml-1 text-indigo-600 hover:text-indigo-800 underline" @click="editStatusDate(STATUS_ACTIVE)">edit</button>
                    </p>
                    <p class="mt-1 text-xs text-gray-500" v-if="form.removed_date">
                      Removed Date: {{ fmtDate(form.removed_date) }}
                      <button type="button" class="ml-1 text-indigo-600 hover:text-indigo-800 underline" @click="editStatusDate(STATUS_REMOVED)">edit</button>
                    </p>
                  </div>
                  <!-- Inactive Date — auto-captured when the status is set to
                       "Inactive". Read-only (not user-settable). -->
                  <div class="sm:col-span-3" v-if="form.termination_date">
                    <label class="flex justify-start text-sm font-medium text-gray-700">Inactive Date</label>
                    <div class="mt-1 text-sm text-gray-700">
                      {{ fmtDate(form.termination_date) }}
                    </div>
                    <p class="mt-1 text-xs text-gray-400">
                      Auto-set when the site status is changed to Inactive.
                    </p>
                  </div>
                  <div class="sm:col-span-3">
                    <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                      Reference Price Type
                      <ExclamationCircleIcon class="w-5 h-5 self-center pl-1" v-tooltip="'Desired Price to be Set on Vending Machine'"></ExclamationCircleIcon>
                    </label>
                    <MultiSelect
                      v-model="form.selling_price_type"
                      :options="sellingPriceTypeOptions"
                      trackBy="id"
                      valueProp="id"
                      label="value"
                      placeholder="Select"
                      open-direction="bottom"
                      class="mt-1"
                      @selected="onSellingPriceTypeSelected"
                    ></MultiSelect>
                    <div class="text-sm text-red-600" v-if="form.errors['customer.selling_price_type']">
                      {{ form.errors['customer.selling_price_type'] }}
                    </div>
                  </div>

                  <!-- Operator — moved up from its own section; placed right after
                       Reference Price Type. Inline with it on desktop. -->
                  <div class="sm:col-span-3">
                    <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                      Operator <span class="text-red-500">*</span>
                    </label>
                    <MultiSelect
                      v-model="form.operator_id"
                      :options="operatorOptions"
                      trackBy="id"
                      valueProp="id"
                      label="full_name"
                      placeholder="Select"
                      open-direction="bottom"
                      class="mt-1"
                    ></MultiSelect>
                    <div class="text-sm text-red-600" v-if="form.errors.operator_id">
                      {{ form.errors.operator_id }}
                    </div>
                    <div class="text-blue-600 text-xs">
                      ** Changing Operator will change Vending Machine's Operator as well
                    </div>
                  </div>

                  <!-- Address Section -->
                  <div class="sm:col-span-6 grid grid-cols-1 gap-3 sm:grid-cols-6 pb-6">
                    <div class="sm:col-span-6 pt-2 mt-2 md:pt-5 md:pb-3">
                      <div class="relative">
                        <div class="absolute inset-0 flex items-center" aria-hidden="true">
                          <div class="w-full border-t border-gray-300"></div>
                        </div>
                        <div class="relative flex justify-start">
                          <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> Site Address (Machine Placement Exact Location) </span>
                        </div>
                      </div>
                    </div>
                    <div class="sm:col-span-6">
                      <FormInput v-model="form.address.map_url" :error="form.errors['address.map_url']" placeholderStr="Google Map URL">
                        <div class="flex space-x-1">
                          <span> Google Map URL </span>
                          <span v-if="form.address.map_url">
                            <a class="text-blue-700" target="_blank" rel="noopener noreferrer" :href="'//' + form.address.map_url">
                              <ArrowTopRightOnSquareIcon class="w-4 h-4"></ArrowTopRightOnSquareIcon>
                            </a>
                          </span>
                        </div>
                      </FormInput>
                    </div>
                    <div class="sm:col-span-6">
                      <SearchAddressInput v-model="form.address.postcode" @selected="onAddressSelected" :error="form.errors['address.postcode']" :apiEnabled="addressApiEnabled" :provider="mapProvider"> Postcode <span class="text-gray-400 font-normal">(key in to autofill)</span> </SearchAddressInput>
                    </div>
                    <div class="sm:col-span-3">
                      <FormInput v-model="form.address.unit_num" :error="form.errors['address.unit_num']" placeholderStr="Unit Num"> Unit Num </FormInput>
                    </div>
                    <div class="sm:col-span-3">
                      <FormInput v-model="form.address.block_num" :error="form.errors['address.block_num']" placeholderStr="Block Num"> Block Num </FormInput>
                    </div>
                    <div class="sm:col-span-3">
                      <FormInput v-model="form.address.building" :error="form.errors['address.building']" :disabled="lockAddressFields" placeholderStr="Building Name"> Building Name </FormInput>
                    </div>
                    <div class="sm:col-span-3">
                      <FormInput v-model="form.address.street_name" :error="form.errors['address.street_name']" :disabled="lockAddressFields" placeholderStr="Street Name"> Street Name </FormInput>
                    </div>
                    <!-- Country field removed from UI — single-country localized
                         deployment. form.address.country_id is defaulted from the
                         operator's country (operatorCountry) in onMounted. -->
                    <div class="sm:col-span-3 hidden">
                      <FormInput v-model="form.address.latitude" placeholderStr="Latitude"> Latitude </FormInput>
                    </div>
                    <div class="sm:col-span-3 hidden">
                      <FormInput v-model="form.address.longitude" placeholderStr="Longitude"> Longitude </FormInput>
                    </div>

                    <!-- Site-level contact (distinct from the Billing Contact below).
                         Stored directly on the customers table. Phone is plain text —
                         no country code (single-country localized deployment). -->
                    <div class="sm:col-span-6">
                      <FormInput v-model="form.site_contact_person" :error="form.errors['customer.site_contact_person']" placeholderStr="Site Contact Person"> Site Contact Person </FormInput>
                    </div>
                    <div class="sm:col-span-3">
                      <FormInput v-model="form.site_phone_number" :error="form.errors['customer.site_phone_number']" placeholderStr="Site Phone Number"> Site Phone Number </FormInput>
                    </div>
                    <div class="sm:col-span-3">
                      <FormInput v-model="form.site_alt_phone_number" :error="form.errors['customer.site_alt_phone_number']" placeholderStr="Alt Site Phone Number"> Alt Site Phone Number </FormInput>
                    </div>

                    <!-- Remarks for the delivery address — free text, stored on the
                         customers table (address_remarks). -->
                    <div class="sm:col-span-6">
                      <FormTextarea v-model="form.address_remarks" :error="form.errors['customer.address_remarks']" placeholderStr="e.g. Loading bay at rear, ask security for access" rows="2"> Remarks for Address </FormTextarea>
                    </div>
                  </div>

                  <!-- Billing & Bank Section — highlighted container (matches Placement Contract Detail styling) -->
                  <div class="sm:col-span-6 bg-blue-50 border border-blue-200 rounded-lg p-4 mt-3 mb-3 shadow-sm" v-if="customer.id || (!customer.id && isExisting != 1)">
                    <!-- Billing Contact -->
                    <h3 class="text-lg font-semibold text-gray-900 mb-3 pb-2 border-b border-blue-200">
                      Billing Contact
                    </h3>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-6">
                    <div class="sm:col-span-6">
                      <FormInput v-model="form.contact.company" :error="form.errors['contact.company']" placeholderStr="Company"> Bill From (Company Full Name or Personal Name) </FormInput>
                    </div>
                    <div class="sm:col-span-6">
                      <label class="flex items-center gap-2 cursor-pointer select-none">
                        <input
                          type="checkbox"
                          v-model="form.is_gst_registered"
                          class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                        />
                        <span class="text-sm font-medium text-gray-700"> Is GST Registered? </span>
                      </label>
                    </div>
                    <div class="sm:col-span-3">
                      <FormInput v-model="form.contact.name" :error="form.errors['contact.name']" placeholderStr="Contact Person"> Billing Contact Person </FormInput>
                    </div>
                    <div class="sm:col-span-3">
                      <FormTextarea v-model="form.contact.email" :error="form.errors['contact.email']" placeholderStr="One email per line (or comma-separated)" rows="2"> Email </FormTextarea>
                    </div>
                    <!-- Phone Code removed from UI — single-country localized
                         deployment. form.contact.phone_country_id is defaulted
                         from the operator's country (operatorCountry) in onMounted. -->
                    <div class="sm:col-span-3">
                      <FormInput v-model="form.contact.phone_num" :error="form.errors['contact.phone_num']" placeholderStr="Phone Number"> Phone Number </FormInput>
                    </div>
                    <!-- Alt Phone Code removed from UI — defaulted from operator country. -->
                    <div class="sm:col-span-3">
                      <FormInput v-model="form.contact.alt_phone_num" :error="form.errors['contact.alt_phone_num']" placeholderStr="Alt Phone Number"> Alt Phone Number </FormInput>
                    </div>

                    <!-- Billing Address — "same as Delivery Address" toggle. When
                         checked (default) the billing fields are hidden and the
                         server mirrors the delivery address into the billing
                         record. When unchecked the user fills a dedicated
                         billing address. -->
                    <div class="sm:col-span-6 flex items-center gap-2 mt-1">
                      <input
                        type="checkbox"
                        id="billing_same_as_delivery"
                        v-model="form.is_billing_same_as_delivery"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                      />
                      <label for="billing_same_as_delivery" class="text-sm font-medium text-gray-700 cursor-pointer">
                        Billing Address same as Site Address
                      </label>
                    </div>

                    <template v-if="!form.is_billing_same_as_delivery">
                      <div class="sm:col-span-6">
                        <SearchAddressInput v-model="form.billing_address.postcode" @selected="onBillingAddressSelected" :error="form.errors['customer.billing_address.postcode']" :apiEnabled="addressApiEnabled" :provider="mapProvider"> Postcode <span class="text-gray-400 font-normal">(key in to autofill)</span> </SearchAddressInput>
                      </div>
                      <div class="sm:col-span-3">
                        <FormInput v-model="form.billing_address.unit_num" :error="form.errors['customer.billing_address.unit_num']" placeholderStr="Unit Num"> Unit Num </FormInput>
                      </div>
                      <div class="sm:col-span-3">
                        <FormInput v-model="form.billing_address.block_num" :error="form.errors['customer.billing_address.block_num']" placeholderStr="Block Num"> Block Num </FormInput>
                      </div>
                      <div class="sm:col-span-3">
                        <FormInput v-model="form.billing_address.building" :error="form.errors['customer.billing_address.building']" :disabled="lockAddressFields" placeholderStr="Building Name"> Building Name </FormInput>
                      </div>
                      <div class="sm:col-span-3">
                        <FormInput v-model="form.billing_address.street_name" :error="form.errors['customer.billing_address.street_name']" :disabled="lockAddressFields" placeholderStr="Street Name"> Street Name </FormInput>
                      </div>
                      <!-- Country field removed from UI — defaulted from operator country. -->
                    </template>
                    </div>

                    <!-- Bank Details -->
                    <h3 class="text-lg font-semibold text-gray-900 mt-5 mb-3 pb-2 border-b border-blue-200">
                      Bank Details
                    </h3>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-6">
                    <div class="sm:col-span-6">
                      <label for="text" class="flex justify-start text-sm font-medium text-gray-700"> Bank Name </label>
                      <MultiSelect
                        v-model="form.bank_id"
                        :options="bankOptions"
                        trackBy="id"
                        valueProp="id"
                        label="name"
                        placeholder="Select"
                        open-direction="top"
                        class="mt-1"
                      ></MultiSelect>
                      <div class="text-sm text-red-600" v-if="form.errors['customer.bank_id']">
                        {{ form.errors['customer.bank_id'] }}
                      </div>
                    </div>
                    <div class="sm:col-span-3">
                      <FormInput v-model="form.bank_account_name" :error="form.errors['customer.bank_account_name']" placeholderStr="Account Holder Name"> Account Holder Name </FormInput>
                    </div>
                    <div class="sm:col-span-3">
                      <FormInput v-model="form.bank_account_number" :error="form.errors['customer.bank_account_number']" placeholderStr="Account Number"> Account Number </FormInput>
                    </div>
                    </div>
                  </div>

                  <!-- Placement Contract Detail Section — highlighted container -->
                  <div class="sm:col-span-6 bg-blue-50 border border-blue-200 rounded-lg p-4 mt-3 mb-3 shadow-sm">
                    <div class="flex items-center justify-between mb-3 pb-2 border-b border-blue-200">
                      <h3 class="text-lg font-semibold text-gray-900">
                        Placement Contract Detail
                      </h3>
                      <button
                        type="button"
                        v-if="customer.id"
                        @click.prevent="openScheduleForm(!!scheduledRow)"
                        :class="['text-xs px-3 py-1.5 rounded-md text-white hover:opacity-90 flex items-center gap-1 whitespace-nowrap', scheduledRow ? 'bg-amber-500' : 'bg-indigo-600']"
                      >
                        <CheckCircleIcon class="w-4 h-4" />
                        {{ scheduledRow ? 'View Future Contract' : 'Set Future Contract' }}
                      </button>
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-6">
                  <!-- Audit info -->
                  <div class="sm:col-span-6 mb-2" v-if="customer.contract_detail_updated_at">
                    <span class="text-xs text-gray-600 italic">
                      Last Updated by: {{ customer.contract_detail_updated_by?.name ?? '—' }}, {{ formatDatetime(customer.contract_detail_updated_at) }}
                    </span>
                  </div>

                  <!-- Commission Type (single-select radio styled as checkboxes) -->
                  <div class="sm:col-span-6">
                    <label class="flex justify-start text-sm font-medium text-gray-700 mb-2">
                      Location Fees
                      <span class="ml-1 text-red-500 text-xs font-normal">(choose 1 only)</span>
                    </label>
                    <div class="flex flex-wrap gap-x-6 gap-y-2">
                      <label
                        v-for="opt in commissionTypeOptions"
                        :key="opt.id"
                        class="flex items-center gap-2 cursor-pointer select-none"
                      >
                        <input
                          type="radio"
                          name="contract_commission_type"
                          :value="opt.id"
                          v-model="form.contract_commission_type"
                          class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500"
                          @change="onCommissionTypeChange"
                        />
                        <span class="text-sm text-gray-700">{{ opt.label }}</span>
                      </label>
                      <button
                        type="button"
                        v-if="form.contract_commission_type"
                        @click="clearCommissionType"
                        class="text-xs text-red-500 hover:text-red-700 underline self-center"
                      >
                        Clear
                      </button>
                    </div>
                    <div class="text-sm text-red-600 mt-1" v-if="form.errors.contract_commission_type">
                      {{ form.errors.contract_commission_type }}
                    </div>
                  </div>

                  <!-- Commission Value field (hidden for Free Placement & when nothing selected) -->
                  <template v-if="form.contract_commission_type && form.contract_commission_type !== 'F'">
                    <div class="sm:col-span-3">
                      <label class="flex justify-start text-sm font-medium text-gray-700">
                        {{ commissionValueLabel }}
                        <span class="ml-1 text-gray-400 text-xs font-normal">({{ isPsCommission ? '%' : 'Amount' }})</span>
                        <span class="ml-1 text-red-500">*</span>
                      </label>
                      <div class="mt-1 relative rounded-md shadow-sm">
                        <div
                          v-if="!isPsCommission"
                          class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3"
                        >
                          <span class="text-gray-500 sm:text-sm">$</span>
                        </div>
                        <input
                          type="number"
                          step="0.01"
                          min="0"
                          :max="isPsCommission ? 100 : undefined"
                          v-model="form.contract_commission_value"
                          :class="['shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md', !isPsCommission ? 'pl-7' : 'pr-8']"
                          :placeholder="isPsCommission ? 'e.g. 30' : 'e.g. 100.00'"
                        />
                        <div
                          v-if="isPsCommission"
                          class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3"
                        >
                          <span class="text-gray-500 sm:text-sm">%</span>
                        </div>
                      </div>
                      <div class="text-sm text-red-600 mt-1" v-if="form.errors['customer.contract_commission_value']">
                        {{ form.errors['customer.contract_commission_value'] }}
                      </div>
                    </div>

                    <!-- Second value: Utility Amount (only for PS+U and PSORU) -->
                    <div class="sm:col-span-3" v-if="hasTwoValues">
                      <label class="flex justify-start text-sm font-medium text-gray-700">
                        Utility Amount
                        <span class="ml-1 text-gray-400 text-xs font-normal">(Amount)</span>
                        <span class="ml-1 text-red-500">*</span>
                      </label>
                      <div class="mt-1 relative rounded-md shadow-sm">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                          <span class="text-gray-500 sm:text-sm">$</span>
                        </div>
                        <input
                          type="number"
                          step="0.01"
                          min="0"
                          v-model="form.contract_commission_value2"
                          class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md pl-7"
                          placeholder="e.g. 50.00"
                        />
                      </div>
                      <div class="text-sm text-red-600 mt-1" v-if="form.errors['customer.contract_commission_value2']">
                        {{ form.errors['customer.contract_commission_value2'] }}
                      </div>
                    </div>
                  </template>

                  <!-- PS Term % (only for PS / PS+U / PSORU) -->
                  <div class="sm:col-span-3" v-if="showPsTerm">
                    <label class="flex justify-start text-sm font-medium text-gray-700">
                      PS Term
                      <span class="ml-1 text-gray-400 text-xs font-normal">(%)</span>
                      <span class="ml-1 text-red-500">*</span>
                    </label>
                    <div class="mt-1 relative rounded-md shadow-sm">
                      <input
                        type="number"
                        step="0.01"
                        min="0"
                        max="100"
                        v-model="form.contract_ps_term"
                        class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md pr-8"
                        placeholder="e.g. 70"
                      />
                      <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                        <span class="text-gray-500 sm:text-sm">%</span>
                      </div>
                    </div>
                    <div class="text-sm text-red-600 mt-1" v-if="form.errors['customer.contract_ps_term']">
                      {{ form.errors['customer.contract_ps_term'] }}
                    </div>
                  </div>

                  <!-- External Subsidize — toggle gates an optional dollar amount.
                       When the toggle is off the amount input is readonly/disabled
                       and the value is cleared to null on save (server-enforced too). -->
                  <div class="sm:col-span-6 grid grid-cols-6 gap-4">
                    <div class="col-span-6 sm:col-span-3 flex flex-col justify-end pb-1">
                      <label class="flex justify-start text-sm font-medium text-gray-700 mb-2">External Subsidize</label>
                      <div class="flex items-center gap-2">
                        <input
                          type="checkbox"
                          id="is_external_subsidize"
                          v-model="form.is_external_subsidize"
                          @change="onExternalSubsidizeToggle"
                          class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <label for="is_external_subsidize" class="text-sm text-gray-600 cursor-pointer">
                          {{ form.is_external_subsidize ? 'Enabled' : 'Disabled' }}
                        </label>
                      </div>
                    </div>
                    <div class="col-span-6 sm:col-span-3">
                      <label class="flex justify-start text-sm font-medium text-gray-700">
                        External Subsidize Amount
                        <span class="ml-1 text-gray-400 text-xs font-normal">(Amount)</span>
                      </label>
                      <div class="mt-1 relative rounded-md shadow-sm">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                          <span class="text-gray-500 sm:text-sm">$</span>
                        </div>
                        <input
                          type="number"
                          step="0.01"
                          min="0"
                          v-model="form.external_subsidize_amount"
                          :readonly="!form.is_external_subsidize"
                          :disabled="!form.is_external_subsidize"
                          :class="['shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md pl-7', !form.is_external_subsidize ? 'bg-gray-200 text-gray-500 hover:cursor-not-allowed' : '']"
                          placeholder="e.g. 100.00"
                        />
                      </div>
                      <div class="text-sm text-red-600 mt-1" v-if="form.errors['customer.external_subsidize_amount']">
                        {{ form.errors['customer.external_subsidize_amount'] }}
                      </div>
                    </div>
                  </div>

                  <!-- Contract From + Contract Until — same row -->
                  <div class="sm:col-span-6 grid grid-cols-6 gap-4">
                    <div class="col-span-6 sm:col-span-3">
                      <DatePicker v-model="form.contract_from" :error="form.errors['customer.contract_from']">
                        Contract From
                      </DatePicker>
                    </div>
                    <div class="col-span-6 sm:col-span-3">
                      <DatePicker v-model="form.contract_until" :error="form.errors['customer.contract_until']">
                        Contract Until
                      </DatePicker>
                    </div>
                  </div>

                  <!-- Auto Renewal + Notice Period — same row -->
                  <div class="sm:col-span-6 grid grid-cols-6 gap-4">
                    <div class="col-span-6 sm:col-span-3 flex flex-col justify-end pb-1">
                      <label class="flex justify-start text-sm font-medium text-gray-700 mb-2">Auto Renewal</label>
                      <div class="flex items-center gap-2">
                        <input
                          type="checkbox"
                          id="contract_auto_renewal"
                          v-model="form.contract_auto_renewal"
                          class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                        />
                        <label for="contract_auto_renewal" class="text-sm text-gray-600 cursor-pointer">
                          {{ form.contract_auto_renewal ? 'Yes' : 'No' }}
                        </label>
                      </div>
                    </div>
                    <div class="col-span-6 sm:col-span-3">
                      <label class="flex justify-start text-sm font-medium text-gray-700">
                        Notice Period
                      </label>
                      <div class="mt-1">
                        <select
                          v-model="form.contract_notice_period"
                          class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md"
                        >
                          <option :value="null">-- Select --</option>
                          <option
                            v-for="opt in (noticePeriodOptions || [])"
                            :key="opt"
                            :value="opt"
                          >
                            {{ opt }}
                          </option>
                        </select>
                      </div>
                      <div class="text-sm text-red-600 mt-1" v-if="form.errors['customer.contract_notice_period']">
                        {{ form.errors['customer.contract_notice_period'] }}
                      </div>
                    </div>
                  </div>

                  <!-- Attach Contract -->
                  <div class="sm:col-span-6 mt-2">
                    <label class="flex justify-start text-sm font-medium text-gray-700 mb-2">
                      Attach Contract
                    </label>
                    <div class="flex flex-col sm:flex-row sm:items-center gap-2" v-if="customer.id">
                      <input
                        ref="contractFileInput"
                        type="file"
                        multiple
                        class="block w-full text-sm text-gray-700 border border-gray-300 rounded-md file:mr-3 file:py-1.5 file:px-3 file:rounded-l-md file:border-0 file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100"
                        @change="onContractFilesSelected"
                      />
                      <Button
                        type="button"
                        class="text-white flex items-center space-x-1 whitespace-nowrap"
                        :class="(!pendingContractFiles.length || isUploadingContract) ? 'cursor-not-allowed bg-gray-400' : 'cursor-pointer bg-green-500 hover:bg-green-600'"
                        :disabled="!pendingContractFiles.length || isUploadingContract"
                        @click.prevent="uploadContractFiles"
                      >
                        <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                        <span>{{ isUploadingContract ? 'Uploading...' : 'Upload' }}</span>
                      </Button>
                    </div>
                    <div class="text-xs text-gray-500 italic mt-1" v-if="!customer.id">
                      Save the customer first to attach contract file(s).
                    </div>

                    <!-- Slim list of attached contracts -->
                    <ul role="list" class="mt-2 divide-y divide-gray-100 bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-md" v-if="customer.id">
                      <li
                        v-for="(item, idx) in contracts"
                        :key="item.id"
                        class="flex items-center justify-between gap-x-3 px-3 py-2 hover:bg-gray-50"
                      >
                        <div class="flex items-center min-w-0 gap-x-2 flex-1">
                          <span class="text-xs text-gray-500 w-6 text-right shrink-0">{{ idx + 1 }}.</span>
                          <a
                            :href="item.full_url"
                            target="_blank"
                            class="text-sm text-blue-600 hover:text-blue-800 truncate"
                            :title="item.name"
                          >
                            {{ item.name || ('Contract ' + (idx + 1)) }}
                          </a>
                          <span class="text-xs text-gray-400 hidden sm:inline whitespace-nowrap">
                            {{ formatDatetime(item.created_at) }}
                          </span>
                        </div>
                        <div class="flex items-center gap-x-1 shrink-0">
                          <a
                            :href="item.full_url"
                            target="_blank"
                            class="rounded-full bg-gray-100 hover:bg-gray-200 p-1.5 text-gray-700"
                            title="Download / View"
                          >
                            <ArrowDownTrayIcon class="h-4 w-4"></ArrowDownTrayIcon>
                          </a>
                          <button
                            type="button"
                            class="rounded-full bg-red-100 hover:bg-red-200 p-1.5 text-red-700"
                            @click.prevent="deleteContract(item.id)"
                            title="Delete"
                          >
                            <TrashIcon class="h-4 w-4"></TrashIcon>
                          </button>
                        </div>
                      </li>
                      <li v-if="!contracts || !contracts.length" class="px-3 py-2 text-xs text-gray-500 italic">
                        No contracts attached
                      </li>
                    </ul>
                  </div>

                  <!-- Remarks for Contract -->
                  <div class="sm:col-span-6">
                    <label for="contract_remarks" class="flex justify-start text-sm font-medium text-gray-700">
                      Remarks for Contract
                    </label>
                    <div class="mt-1">
                      <textarea
                        id="contract_remarks"
                        v-model="form.contract_remarks"
                        rows="3"
                        maxlength="5000"
                        class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md"
                        placeholder="e.g. Special clauses, agreed adjustments, etc."
                      ></textarea>
                    </div>
                    <div class="text-sm text-red-600 mt-1" v-if="form.errors['customer.contract_remarks']">
                      {{ form.errors['customer.contract_remarks'] }}
                    </div>
                  </div>

                  <!-- ── Schedule Future Contract Change ──────────────────────
                       Lets the user queue the NEXT contract (date + terms). It
                       stays dormant until contract:apply-scheduled runs on the
                       effective date, which then writes the live contract +
                       contract log (start-of-month = single row, mid-month =
                       split, same as an immediate edit). One pending at a time. -->
                  <!-- Inline status banner — a pending schedule exists. The
                       create/edit form itself lives in the modal below. -->
                  <div class="sm:col-span-6 mt-2 border-t border-blue-200 pt-3" v-if="customer.id && scheduledRow">
                    <div class="rounded-md bg-amber-50 border border-amber-200 p-3">
                      <div class="flex items-start justify-between gap-3">
                        <div class="text-sm text-amber-900">
                          <div class="font-medium">
                            A contract change is scheduled for
                            <span class="font-semibold">{{ formatDate(scheduledRow.effective_date) }}</span>.
                          </div>
                          <div class="text-xs text-amber-800 mt-1">
                            {{ scheduleSummary(scheduledRow) }}
                          </div>
                          <div class="text-xs text-amber-700 italic mt-1" v-if="scheduledRow.created_by">
                            Set by: {{ scheduledRow.created_by.name }}
                          </div>
                        </div>
                        <div class="flex items-center gap-1 shrink-0">
                          <button
                            type="button"
                            @click.prevent="openScheduleForm(true)"
                            class="text-xs px-2.5 py-1 rounded-md bg-white border border-amber-300 text-amber-800 hover:bg-amber-100"
                          >
                            Edit
                          </button>
                          <button
                            type="button"
                            @click.prevent="cancelScheduledContract(customer.id)"
                            class="text-xs px-2.5 py-1 rounded-md bg-red-100 text-red-700 hover:bg-red-200"
                          >
                            Cancel
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- ── Set Future Contract (modal) ──────────────────────────
                       Create/overwrite the single pending future contract. It
                       keeps getting overwritten until the effective date arrives;
                       then contract:apply-scheduled writes it onto the live
                       contract + contract log and clears this pending entry. -->
                  <Teleport to="body">
                    <Modal :open="sf.open" @modalClose="closeScheduleForm">
                      <template #header>
                        <span>{{ scheduledRow ? 'Edit Future Contract' : 'Set Future Contract' }}</span>
                      </template>
                      <div class="text-left">
                        <p class="text-xs text-gray-500 mb-3">
                          These details stay pending and can be overwritten any time until the effective date.
                          On that day they automatically replace the current contract, and this scheduled entry is cleared.
                        </p>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-6">
                        <!-- Effective date -->
                        <div class="sm:col-span-3">
                          <DatePicker
                            v-model="sf.effective_date"
                            :error="sfErrors.effective_date"
                            :minDate="minScheduleDate"
                            :isPreviousNextButton="false"
                          >
                            Effective Date <span class="text-red-500">*</span>
                          </DatePicker>
                          <p class="text-xs text-gray-500 mt-1">Must be a future date. The change applies automatically on this day.</p>
                          <p class="text-xs text-red-600 mt-1" v-if="sfErrors.effective_date">{{ sfErrors.effective_date }}</p>
                        </div>

                        <!-- Location Fees / commission type -->
                        <div class="sm:col-span-6">
                          <label class="flex justify-start text-sm font-medium text-gray-700 mb-2">
                            Location Fees <span class="ml-1 text-red-500 text-xs font-normal">(choose 1 only)</span>
                          </label>
                          <div class="flex flex-wrap gap-x-6 gap-y-2">
                            <label
                              v-for="opt in commissionTypeOptions"
                              :key="'sf-' + opt.id"
                              class="flex items-center gap-2 cursor-pointer select-none"
                            >
                              <input
                                type="radio"
                                name="sf_contract_commission_type"
                                :value="opt.id"
                                v-model="sf.contract_commission_type"
                                class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                @change="onSfCommissionTypeChange"
                              />
                              <span class="text-sm text-gray-700">{{ opt.label }}</span>
                            </label>
                            <button
                              type="button"
                              v-if="sf.contract_commission_type"
                              @click="clearSfCommissionType"
                              class="text-xs text-red-500 hover:text-red-700 underline self-center"
                            >
                              Clear
                            </button>
                          </div>
                        </div>

                        <!-- Commission value + utility amount -->
                        <template v-if="sf.contract_commission_type && sf.contract_commission_type !== 'F'">
                          <div class="sm:col-span-3">
                            <label class="flex justify-start text-sm font-medium text-gray-700">
                              {{ sfCommissionValueLabel }}
                              <span class="ml-1 text-gray-400 text-xs font-normal">({{ sfIsPs ? '%' : 'Amount' }})</span>
                            </label>
                            <div class="mt-1 relative rounded-md shadow-sm">
                              <div v-if="!sfIsPs" class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <span class="text-gray-500 sm:text-sm">$</span>
                              </div>
                              <input
                                type="number" step="0.01" min="0" :max="sfIsPs ? 100 : undefined"
                                v-model="sf.contract_commission_value"
                                :class="['shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md', !sfIsPs ? 'pl-7' : 'pr-8']"
                                :placeholder="sfIsPs ? 'e.g. 30' : 'e.g. 100.00'"
                              />
                              <div v-if="sfIsPs" class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                                <span class="text-gray-500 sm:text-sm">%</span>
                              </div>
                            </div>
                          </div>
                          <div class="sm:col-span-3" v-if="sfHasTwoValues">
                            <label class="flex justify-start text-sm font-medium text-gray-700">
                              Utility Amount <span class="ml-1 text-gray-400 text-xs font-normal">(Amount)</span>
                            </label>
                            <div class="mt-1 relative rounded-md shadow-sm">
                              <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <span class="text-gray-500 sm:text-sm">$</span>
                              </div>
                              <input
                                type="number" step="0.01" min="0"
                                v-model="sf.contract_commission_value2"
                                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md pl-7"
                                placeholder="e.g. 50.00"
                              />
                            </div>
                          </div>
                        </template>

                        <!-- PS Term -->
                        <div class="sm:col-span-3" v-if="sfShowPsTerm">
                          <label class="flex justify-start text-sm font-medium text-gray-700">
                            PS Term <span class="ml-1 text-gray-400 text-xs font-normal">(%)</span>
                          </label>
                          <div class="mt-1 relative rounded-md shadow-sm">
                            <input
                              type="number" step="0.01" min="0" max="100"
                              v-model="sf.contract_ps_term"
                              class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md pr-8"
                              placeholder="e.g. 70"
                            />
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                              <span class="text-gray-500 sm:text-sm">%</span>
                            </div>
                          </div>
                        </div>

                        <!-- External Subsidize -->
                        <div class="sm:col-span-6 grid grid-cols-6 gap-4">
                          <div class="col-span-6 sm:col-span-3 flex flex-col justify-end pb-1">
                            <label class="flex justify-start text-sm font-medium text-gray-700 mb-2">External Subsidize</label>
                            <div class="flex items-center gap-2">
                              <input
                                type="checkbox"
                                v-model="sf.is_external_subsidize"
                                @change="onSfExternalSubsidizeToggle"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                              />
                              <span class="text-sm text-gray-600">{{ sf.is_external_subsidize ? 'Enabled' : 'Disabled' }}</span>
                            </div>
                          </div>
                          <div class="col-span-6 sm:col-span-3">
                            <label class="flex justify-start text-sm font-medium text-gray-700">
                              External Subsidize Amount <span class="ml-1 text-gray-400 text-xs font-normal">(Amount)</span>
                            </label>
                            <div class="mt-1 relative rounded-md shadow-sm">
                              <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                                <span class="text-gray-500 sm:text-sm">$</span>
                              </div>
                              <input
                                type="number" step="0.01" min="0"
                                v-model="sf.external_subsidize_amount"
                                :readonly="!sf.is_external_subsidize"
                                :disabled="!sf.is_external_subsidize"
                                :class="['shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md pl-7', !sf.is_external_subsidize ? 'bg-gray-200 text-gray-500 hover:cursor-not-allowed' : '']"
                                placeholder="e.g. 100.00"
                              />
                            </div>
                          </div>
                        </div>

                        <!-- Contract From / Until -->
                        <div class="sm:col-span-6 grid grid-cols-6 gap-4">
                          <div class="col-span-6 sm:col-span-3">
                            <DatePicker v-model="sf.contract_from">Contract From</DatePicker>
                          </div>
                          <div class="col-span-6 sm:col-span-3">
                            <DatePicker v-model="sf.contract_until">Contract Until</DatePicker>
                          </div>
                        </div>

                        <!-- Auto Renewal / Notice Period -->
                        <div class="sm:col-span-6 grid grid-cols-6 gap-4">
                          <div class="col-span-6 sm:col-span-3 flex flex-col justify-end pb-1">
                            <label class="flex justify-start text-sm font-medium text-gray-700 mb-2">Auto Renewal</label>
                            <div class="flex items-center gap-2">
                              <input
                                type="checkbox"
                                v-model="sf.contract_auto_renewal"
                                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                              />
                              <span class="text-sm text-gray-600">{{ sf.contract_auto_renewal ? 'Yes' : 'No' }}</span>
                            </div>
                          </div>
                          <div class="col-span-6 sm:col-span-3">
                            <label class="flex justify-start text-sm font-medium text-gray-700">Notice Period</label>
                            <div class="mt-1">
                              <select
                                v-model="sf.contract_notice_period"
                                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md"
                              >
                                <option :value="null">-- Select --</option>
                                <option v-for="opt in (noticePeriodOptions || [])" :key="'sf-np-' + opt" :value="opt">{{ opt }}</option>
                              </select>
                            </div>
                          </div>
                        </div>

                        <!-- Remarks -->
                        <div class="sm:col-span-6">
                          <label class="flex justify-start text-sm font-medium text-gray-700">Remarks for Contract</label>
                          <div class="mt-1">
                            <textarea
                              v-model="sf.contract_remarks"
                              rows="2" maxlength="5000"
                              class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md"
                              placeholder="e.g. Special clauses, agreed adjustments, etc."
                            ></textarea>
                          </div>
                        </div>
                        </div>

                        <div class="flex items-center justify-between gap-2 mt-5 border-t border-gray-200 pt-3">
                          <button
                            type="button"
                            v-if="scheduledRow"
                            @click.prevent="cancelScheduledContract(customer.id)"
                            class="text-sm px-3 py-1.5 rounded-md bg-red-100 text-red-700 hover:bg-red-200"
                          >
                            Cancel scheduled change
                          </button>
                          <span v-else></span>
                          <div class="flex items-center gap-2">
                            <button
                              type="button"
                              @click.prevent="closeScheduleForm"
                              class="text-sm px-3 py-1.5 rounded-md bg-gray-100 text-gray-700 hover:bg-gray-200"
                            >
                              Close
                            </button>
                            <button
                              type="button"
                              :disabled="isSavingSchedule"
                              @click.prevent="submitScheduledContract(customer.id)"
                              :class="['text-sm px-3 py-1.5 rounded-md text-white flex items-center gap-1', isSavingSchedule ? 'bg-gray-400 cursor-not-allowed' : 'bg-green-600 hover:bg-green-700']"
                            >
                              <CheckCircleIcon class="w-4 h-4" />
                              {{ isSavingSchedule ? 'Saving...' : 'Confirm & Schedule' }}
                            </button>
                          </div>
                        </div>
                      </div>
                    </Modal>
                  </Teleport>
                  <!-- end Set Future Contract (modal) -->

                  <!-- ── Status effective-date prompt (modal) ─────────────────
                       Opens when the user selects Active or Removed. Captures
                       the Active Date / Removed Date that the save persists +
                       logs. Cancel reverts the status selection. -->
                  <Teleport to="body">
                    <Modal :open="statusDateModal.open" @modalClose="cancelStatusDate">
                      <template #header>
                        <span>{{ statusDateModal.status === STATUS_REMOVED ? 'Removed Date' : 'Active Date' }}</span>
                      </template>
                      <div class="text-left">
                        <p class="text-xs text-gray-500 mb-3">
                          <template v-if="statusDateModal.status === STATUS_REMOVED">
                            Enter the date this site is removed. Commission stops after this date (the removal month is prorated).
                          </template>
                          <template v-else>
                            Enter the date this site becomes active. Commission is calculated from this date.
                          </template>
                        </p>
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-6">
                          <div class="sm:col-span-6">
                            <DatePicker
                              v-model="statusDateModal.date"
                              :isPreviousNextButton="false"
                            >
                              {{ statusDateModal.status === STATUS_REMOVED ? 'Removed Date' : 'Active Date' }} <span class="text-red-500">*</span>
                            </DatePicker>
                            <p class="text-xs text-red-600 mt-1" v-if="statusDateModal.error">{{ statusDateModal.error }}</p>
                          </div>
                        </div>
                        <div class="mt-4 flex justify-end gap-2">
                          <button
                            type="button"
                            class="px-3 py-1.5 text-sm rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                            @click="cancelStatusDate"
                          >
                            Cancel
                          </button>
                          <button
                            type="button"
                            class="px-3 py-1.5 text-sm rounded-md bg-indigo-600 text-white hover:bg-indigo-700"
                            @click="confirmStatusDate"
                          >
                            Confirm
                          </button>
                        </div>
                      </div>
                    </Modal>
                  </Teleport>

                  <!-- ── Status History (modal) ───────────────────────────────
                       Read-only table of every status change for this site. -->
                  <Teleport to="body">
                    <Modal :open="showStatusHistory" @modalClose="closeStatusHistory">
                      <template #header>
                        <span>Status History</span>
                      </template>
                      <div class="text-left">
                        <div v-if="!props.statusHistory || props.statusHistory.length === 0" class="text-sm text-gray-500 py-4 text-center">
                          No status changes recorded yet.
                        </div>
                        <table v-else class="min-w-full text-sm">
                          <thead>
                            <tr class="text-left text-xs font-medium text-gray-500 border-b">
                              <th class="py-2 pr-4">Status</th>
                              <th class="py-2 pr-4">Date</th>
                              <th class="py-2 pr-4">Changed By</th>
                              <th class="py-2">When</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr v-for="log in props.statusHistory" :key="log.id" class="border-b last:border-0">
                              <td class="py-2 pr-4 text-gray-800">{{ log.status_name || log.status_id }}</td>
                              <td class="py-2 pr-4 text-gray-700">{{ log.status_date || '—' }}</td>
                              <td class="py-2 pr-4 text-gray-700">{{ log.changed_by || '—' }}</td>
                              <td class="py-2 text-gray-500">{{ log.created_at ? fmtDateTime(log.created_at) : '—' }}</td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </Modal>
                  </Teleport>

                  <!--
                    Performance Report Email opt-in.
                    The toggle is force-disabled (and force-false) when no
                    valid email is entered, so the Site Summary "Email"
                    button can rely on is_report_email_enabled === true to
                    mean "this customer has agreed to receive the report at
                    a known address".
                  -->
                  <div class="sm:col-span-6">
                    <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                      Site Tags
                      <ExclamationCircleIcon
                        class="w-5 h-5 self-center pl-1"
                        v-tooltip="'Manage tag list under Site Management → Tags'"
                      ></ExclamationCircleIcon>
                    </label>
                    <MultiSelect
                      v-model="form.tags"
                      :options="customerTagOptions"
                      trackBy="id"
                      valueProp="id"
                      label="name"
                      placeholder="Select tags"
                      open-direction="bottom"
                      class="mt-1"
                      mode="tags"
                    ></MultiSelect>
                    <div class="text-sm text-red-600" v-if="form.errors.tag_ids">
                      {{ form.errors.tag_ids }}
                    </div>
                  </div>
                  <div class="sm:col-span-3">
                    <FormInput
                      v-model="form.report_email"
                      :error="form.errors['customer.report_email']"
                      type="email"
                      placeholder="customer@example.com" placeholderStr="Performance Report Email">
                      Performance Report Email
                    </FormInput>
                  </div>
                  <div class="sm:col-span-3">
                    <label class="flex justify-start text-sm font-medium text-gray-700">
                      Enable Send Performance to Email?
                      <ExclamationCircleIcon
                        class="w-5 h-5 self-center pl-1"
                        v-tooltip="'Toggle on to surface the Email action button on Site Summary. Disabled until a valid email is entered.'"
                      ></ExclamationCircleIcon>
                    </label>
                    <MultiSelect
                      v-model="form.is_report_email_enabled"
                      :options="booleanStrictOptions"
                      :disabled="!isReportEmailValid"
                      trackBy="id"
                      valueProp="id"
                      label="value"
                      placeholder="Select"
                      open-direction="bottom"
                      class="mt-1"
                    ></MultiSelect>
                    <div
                      v-if="!isReportEmailValid"
                      class="text-xs text-gray-500 mt-1"
                    >
                      Enter a valid email above to enable.
                    </div>
                    <div class="text-sm text-red-600" v-if="form.errors['customer.is_report_email_enabled']">
                      {{ form.errors['customer.is_report_email_enabled'] }}
                    </div>
                  </div>
                    </div>
                  </div>
                  <!-- end Placement Contract Detail Section -->

                  <!-- Location Grading Section — A/B/C radio groups per category.
                       Rubric data comes from props.locationGradingCategories
                       (Customer::LOCATION_GRADING_CATEGORIES). Edit-page only. -->
                  <div class="sm:col-span-6 bg-amber-50 border border-amber-200 rounded-lg p-4 mt-3 mb-3 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3 pb-2 border-b border-amber-200">
                      Location Grading
                    </h3>
                    <div class="grid grid-cols-1 gap-4">
                      <div
                        v-for="(category, key) in locationGradingCategories"
                        :key="key"
                      >
                        <label class="flex justify-start text-sm font-medium text-gray-700 mb-2">
                          {{ category.label }}
                          <span class="ml-1 text-red-500 text-xs font-normal">(choose 1 only)</span>
                        </label>
                        <div class="flex flex-col gap-2">
                          <label
                            v-for="(optLabel, optCode) in category.options"
                            :key="optCode"
                            class="flex items-start gap-2 cursor-pointer select-none"
                          >
                            <input
                              type="radio"
                              :name="`location_grading_${key}`"
                              :value="optCode"
                              v-model="form[`location_grading_${key}`]"
                              class="h-4 w-4 mt-0.5 border-gray-300 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span class="text-sm text-gray-700">
                              <span class="font-semibold">{{ optCode }}</span> = {{ optLabel }}
                            </span>
                          </label>
                          <button
                            type="button"
                            v-if="form[`location_grading_${key}`]"
                            @click="form[`location_grading_${key}`] = null"
                            class="text-xs text-red-500 hover:text-red-700 underline self-start"
                          >
                            Clear
                          </button>
                        </div>
                        <div class="text-sm text-red-600 mt-1" v-if="form.errors[`customer.location_grading_${key}`]">
                          {{ form.errors[`customer.location_grading_${key}`] }}
                        </div>
                      </div>
                    </div>
                  </div>
                  <!-- end Location Grading Section -->

                  <div class="sm:col-span-3">
                    <FormInput v-model="form.power_socket_key_number" :error="form.errors.power_socket_key_number" placeholderStr="Power Socket Key Num"> Power Socket Key Num </FormInput>
                  </div>
                  <div class="sm:col-span-3">
                    <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                      Location Type
                    </label>
                    <MultiSelect
                      v-model="form.location_type_id"
                      :options="locationTypeOptions"
                      trackBy="id"
                      valueProp="id"
                      label="value"
                      placeholder="Select"
                      open-direction="bottom"
                      class="mt-1"
                    ></MultiSelect>
                    <div class="text-sm text-red-600" v-if="form.errors['customer.location_type_id']">
                      {{ form.errors['customer.location_type_id'] }}
                    </div>
                  </div>
                  <div class="sm:col-span-3">
                    <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                      Is Restricted Access Location?
                    </label>
                    <MultiSelect
                      v-model="form.is_restricted_access"
                      :options="booleanStrictOptions"
                      trackBy="id"
                      valueProp="id"
                      label="value"
                      placeholder="Select"
                      open-direction="bottom"
                      class="mt-1"
                    ></MultiSelect>
                    <div class="text-sm text-red-600" v-if="form.errors['customer.is_restricted_access']">
                      {{ form.errors['customer.is_restricted_access'] }}
                    </div>
                  </div>
                </div>

                <!-- Menu Section -->
                <hr class="sm:col-span-6">
                <div class="sm:col-span-6 pb-1 md:pt-5 md:pb-3">
                  <div class="relative">
                    <div class="absolute inset-0 flex items-center" aria-hidden="true">
                      <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-start">
                      <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> Menu </span>
                    </div>
                  </div>
                </div>
                <div class="sm:col-span-6 mb-3">
                  <AttachmentListProductMapping
                    :items="customer.vend.product_mapping.attachments"
                    :priceTypeOptions="sellingPriceTypeOptions"
                    v-if="customer.vend && customer.vend.product_mapping && customer.vend.product_mapping.attachments"
                  >
                  </AttachmentListProductMapping>
                </div>

                <!-- Binded Machine Section -->
                <div class="sm:col-span-6 pb-1 md:pt-5 md:pb-3">
                  <div class="relative">
                    <div class="absolute inset-0 flex items-center" aria-hidden="true">
                      <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-start">
                      <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> Binded Machine </span>
                    </div>
                  </div>
                </div>
                <div class="sm:col-span-6" v-if="customer.vend">
                  <label for="text" class="flex justify-start text-sm font-medium text-gray-700"> Machine ID# </label>
                  <div class="mt-1">
                    <a :href="'/settings/vend/' + customer.vend.id + '/update'" target="_blank">
                      <input
                        type="text"
                        class="shadow-sm focus:ring-indigo-300 focus:border-indigo-300 block w-full text-sm border-gray-200 rounded-md bg-gray-100 hover:cursor-pointer text-blue-600 hover:text-blue-700"
                        :value="customer.vend.code"
                        readonly
                      />
                    </a>
                  </div>
                </div>
                <div class="sm:col-span-6" v-if="!customer.vend">
                  <label for="text" class="flex justify-start text-sm font-medium text-gray-700"> Machine ID# </label>
                  <MultiSelect
                    v-model="form.vend_id"
                    :options="vendOptions"
                    trackBy="id"
                    valueProp="id"
                    label="full_name"
                    placeholder="Select"
                    open-direction="bottom"
                    class="mt-1"
                  ></MultiSelect>
                </div>
                <div class="sm:col-span-6 mt-1">
                  <span class="flex space-x-1">
                    <Button
                      type="button"
                      class="text-white flex space-x-1"
                      :class="!form.vend_id ? 'cursor-not-allowed bg-gray-400' : 'cursor-pointer bg-green-500 hover:bg-green-600'"
                      :disabled="!form.vend_id"
                      v-if="!customer.vend && permissions.includes('update customers')"
                      @click.prevent="saveCustomer(form.id)"
                    >
                      <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                      <span> Bind Machine </span>
                    </Button>
                    <Button
                      type="button"
                      class="bg-red-500 hover:bg-red-600 text-white flex space-x-1 w-full md:w-fit my-1"
                      v-if="customer.vend && permissions.includes('update customers')"
                      @click.prevent="unbindCustomer(customer.vend.id)"
                    >
                      <XCircleIcon class="w-4 h-4"></XCircleIcon>
                      <span> Unbind Machine </span>
                    </Button>
                    <Button
                      type="button"
                      class="bg-red-500 hover:bg-red-600 text-white flex space-x-1 w-full md:w-fit my-1"
                      v-if="customer.vend && permissions.includes('update customers')"
                      @click.prevent="unbindCustomerDeactivate(customer.vend.id)"
                    >
                      <XCircleIcon class="w-4 h-4"></XCircleIcon>
                      <span>
                        Unbind Machine & Deactivate Site
                      </span>
                    </Button>
                  </span>
                </div>

                <!-- Product Mapping Section -->
                <div class="sm:col-span-6" v-if="customer.vend && customer.vend.product_mapping">
                  <label for="text" class="flex justify-start text-sm font-medium text-gray-700"> Product Mapping </label>
                  <div class="mt-1">
                    <a :href="'/product-mappings/' + customer.vend.product_mapping.id + '/edit'" target="_blank">
                      <input
                        type="text"
                        class="shadow-sm focus:ring-indigo-300 focus:border-indigo-300 block w-full text-sm border-gray-200 rounded-md bg-gray-100 hover:cursor-pointer text-blue-600 hover:text-blue-700"
                        :value="customer.vend.product_mapping.name"
                        readonly
                      />
                    </a>
                  </div>
                </div>

                <!-- Vend Channels Section -->
                <div class="flex flex-col sm:col-span-5">
                  <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5">
                    <div class="inline-block min-w-full py-2 align-middle md:px-4 lg:px-6">
                      <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                        <table class="table-fixed min-w-full divide-y divide-gray-300">
                          <thead class="bg-gray-50">
                            <tr>
                              <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"> # </th>
                              <th scope="col" class="w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900" v-if="customer.vend && customer.vend.product_mapping_id"> Image </th>
                              <th scope="col" class="w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900" v-if="customer.vend && customer.vend.product_mapping_id"> Product </th>
                              <th scope="col" class="w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                                <div class="flex justify-center">
                                  <span> P1 </span>
                                  <span v-if="profile && profile.base_currency">
                                    ({{ profile.base_currency.currency_symbol }})
                                  </span>
                                  <ExclamationCircleIcon class="w-5 h-5 self-center pl-1" v-tooltip="'Actual Price on Vending Machine'"></ExclamationCircleIcon>
                                </div>
                              </th>
                              <th scope="col" class="w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                                <div class="flex justify-center">
                                  <span> P2 </span>
                                  <span v-if="profile && profile.base_currency">
                                    ({{ profile.base_currency.currency_symbol }})
                                  </span>
                                  <ExclamationCircleIcon class="w-5 h-5 self-center pl-1" v-tooltip="'Discounted Price on 2nd Purchase'"></ExclamationCircleIcon>
                                </div>
                              </th>
                              <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"> Ref Price {{ form.selling_price_type ? form.selling_price_type.id : '' }} </th>
                            </tr>
                          </thead>
                          <tbody class="bg-white">
                            <tr v-for="(channel, channelIndex) in vendChannels" :key="channel.id" :class="channelIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                              <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold sm:pl-6 text-center text-gray-900"> {{ channel.code }} </td>
                              <td class="whitespace-nowrap text-sm  font-semibold text-gray-900 text-center" v-if="customer.vend && customer.vend.product_mapping_id">
                                <div class="flex justify-center items-center">
                                  <img class="h-16 w-16 rounded-full" :src="channel.product.thumbnail.full_url" alt="" v-if="channel.product && channel.product.thumbnail"/>
                                </div>
                              </td>
                              <td class="py-4 text-sm font-semibold text-center text-gray-900" v-if="customer.vend && customer.vend.product_mapping_id">
                                <span v-if="channel.product && channel.product.code"> {{ channel.product.code }} </span>
                                <span class="break-normal text-xs" v-if="channel.product && channel.product.name"> <br> {{ channel.product.name }} </span>
                              </td>
                              <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center" :class="compareSellingPrice(channel)">
                                {{ (channel.amount/ (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                              </td>
                              <td
                                class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center text-gray-800"
                                v-if="vendChannels.some(channel => 'amount2' in channel)"
                              >
                                {{ (channel.amount2/ (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                              </td>
                              <td
                                class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center text-gray-800"
                              >
                                {{ channel.product && channel.product.selling_prices[0] ? (channel.product.selling_prices[0].amount/ (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) : null }}
                              </td>
                            </tr>
                            <tr v-if="!vendChannels || !vendChannels.length">
                              <td class="whitespace-nowrap py-4 pl-4 pr-3 text-xs font-normal sm:pl-6 text-center text-gray-900" colspan="6"> No Results Found </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Operations Section -->
                <div class="sm:col-span-6 grid grid-cols-1 gap-3 sm:grid-cols-6">
                  <div class="sm:col-span-6 pt-2 mt-2 md:pt-5 md:pb-3">
                    <div class="relative">
                      <div class="absolute inset-0 flex items-center" aria-hidden="true">
                        <div class="w-full border-t border-gray-300"></div>
                      </div>
                      <div class="relative flex justify-start">
                        <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> Operations </span>
                      </div>
                    </div>
                  </div>
                  <div class="sm:col-span-4">
                    <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                      Refilling Routes
                    </label>
                    <MultiSelect
                      v-model="form.zone_id"
                      :options="zoneOptions"
                      trackBy="id"
                      valueProp="id"
                      label="value"
                      placeholder="Select"
                      open-direction="bottom"
                      class="mt-1"
                    ></MultiSelect>
                    <div class="text-sm text-red-600" v-if="form.errors['customer.zone_id']">
                      {{ form.errors['customer.zone_id'] }}
                    </div>
                  </div>
                  <div class="sm:col-span-5">
                    <FormTextarea v-model="form.ops_note" :error="form.errors.ops_note" rows="3">
                      Ops Note
                    </FormTextarea>
                  </div>
                  <div class="sm:col-span-6">
                      <label>Preferred Visit Days:</label>
                      <div class="flex flex-wrap gap-2 mt-2 space-x-3">
                        <label v-for="(day, index) in days" :key="index" class="flex items-center">
                          <input type="checkbox" v-model="form.preferred_visit_days_json[index]" class="mr-2 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                          <span>
                            {{ day }}
                          </span>
                        </label>
                      </div>
                  </div>
                  <div class="sm:col-span-4">
                    <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                      #Refill per Week
                    </label>
                    <MultiSelect
                      v-model="form.frequency_per_week_status"
                      :options="frequencyPerWeekOptions"
                      trackBy="id"
                      valueProp="id"
                      label="value"
                      placeholder="Select"
                      open-direction="bottom"
                      class="mt-1"
                    ></MultiSelect>
                    <div class="text-sm text-red-600" v-if="form.errors['customer.zone_id']">
                      {{ form.errors['customer.zone_id'] }}
                    </div>
                  </div>

                </div>

                  <!-- Save and Delete Buttons -->
                  <div class="sm:col-span-6 mt-3 pt-2">
                    <span class="flex flex-col space-y-1 md:flex-row justify-between">
                      <span class="flex flex-col space-y-1 md:flex-row md:space-x-1">
                        <Button
                          type="button"
                          class="bg-green-500 hover:bg-green-600 text-white flex space-x-1 w-full"
                          v-if="permissions.includes('update customers')"
                          @click.prevent="saveCustomer(form.id)"
                        >
                          <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                          <span> Save Site </span>
                        </Button>
                        <Link :href="'/customers'">
                          <Button class="bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1 w-full">
                            <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
                            <span> Back </span>
                          </Button>
                        </Link>
                      </span>
                      <span class="flex flex-col md:flex-row space-x-1">
                        <Button
                          type="button"
                          class="bg-yellow-500 hover:bg-yellow-600 text-gray-800 flex space-x-1"
                          v-if="permissions.includes('update customers') && customer.person_id"
                          @click.prevent="disconnectCMSCustomer(customer.id)"
                        >
                          <StopCircleIcon class="w-4 h-4"></StopCircleIcon>
                          <span> Disconnect from CMS </span>
                        </Button>
                        <Button
                          type="button"
                          class="bg-red-500 hover:bg-red-600 text-white flex space-x-1"
                          v-if="!customer.vend && permissions.includes('update customers')"
                          @click.prevent="deleteCustomer(customer.id)"
                        >
                          <XCircleIcon class="w-4 h-4"></XCircleIcon>
                          <span> Delete Site </span>
                        </Button>
                      </span>
                    </span>
                  </div>

                  <div class="relative pt-2 m-5">
                    <div class="absolute inset-0 flex items-center" aria-hidden="true">
                      <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-start">
                      <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> Machine Binding History </span>
                    </div>
                  </div>
                  <nav aria-label="Progress">
                    <ol role="list" class="overflow-hidden">
                      <li v-for="(customerVendBinding, customerVendBindingIndex) in customerVendBindings" :key="customerVendBinding.id" :class="[customerVendBindingIndex !== customerVendBindings.length - 1? 'pb-3' : 'relative bg-gray-300 rounded']">
                        <template v-if="true">
                          <span class="group relative flex items-start">
                            <span class="flex h-9 items-center">
                              <span class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full" :class="[customerVendBinding.is_binding ? 'bg-green-600' : 'bg-red-600']">
                                <LockClosedIcon class="h-5 w-5 text-white" aria-hidden="true" v-if="customerVendBinding.is_binding"/>
                                <LockOpenIcon class="h-5 w-5 text-white" aria-hidden="true" v-if="!customerVendBinding.is_binding"/>
                              </span>
                            </span>
                            <span class="ml-4 flex min-w-0 flex-col">
                              <span class="text-sm font-medium">
                                <span v-if="customerVendBinding.vend_prefix">
                                  {{ customerVendBinding.vend_prefix.name }} -
                                </span>
                                 {{ customerVendBinding?.vend.code }}
                              </span>
                              <span class="text-sm text-gray-500">{{ customerVendBinding.created_at ? formatDatetime(customerVendBinding.created_at) : '' }}</span>
                            </span>
                          </span>
                        </template>
                      </li>
                    </ol>
                  </nav>
                  <template v-if="!customerVendBindings || !customerVendBindings.length">
                    <span class="group relative flex items-start">
                      <span class="flex h-9 items-center">
                        <span class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full bg-red-600">
                          <MinusCircleIcon class="h-5 w-5 text-white" aria-hidden="true"/>
                        </span>
                      </span>
                      <span class="ml-4 flex min-w-0 flex-col pt-2">
                        <span class="text-sm font-medium">
                          No Records Found
                        </span>
                      </span>
                    </span>
                  </template>

                  <!-- Photo Section -->
                <div class="sm:col-span-6 mt-5 pb-1 md:pt-5 md:pb-3">
                  <div class="relative">
                    <div class="absolute inset-0 flex items-center" aria-hidden="true">
                      <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center ">
                      <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> Photo(s) </span>
                    </div>
                  </div>
                </div>
                <div class="sm:col-span-6">
                  <AttachmentList :items="customer.photos"></AttachmentList>
                </div>
                <!-- <div class="sm:col-span-6" v-if="customer.id">
                  <UploadFileInput
                    :endpoint="'/customers/' + customer.id + '/upload-photos'"
                    acceptedFileTypes="image/*"
                    maxFileSize="5MB"
                  >
                  </UploadFileInput>
                </div> -->
                <div class="sm:col-span-6" v-if="customer.id">
                  <DropzoneFileInput :endpoint="'/customers/' + customer.id + '/upload-photos'"></DropzoneFileInput>
                </div>

                <!-- Attachment Section -->
                <div class="sm:col-span-6 mt-5 pb-1 md:pt-5 md:pb-3">
                  <div class="relative">
                    <div class="absolute inset-0 flex items-center" aria-hidden="true">
                      <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center ">
                      <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> Attachment(s) </span>
                    </div>
                  </div>
                </div>
                <div class="sm:col-span-6">
                  <AttachmentList :items="customer.attachments"></AttachmentList>
                </div>
                <div class="sm:col-span-6" v-if="customer.id">
                  <UploadFileInput :endpoint="'/customers/' + customer.id + '/upload-attachments'"></UploadFileInput>
                </div>
                <div class="sm:col-span-6" v-if="customer.id">
                  <DropzoneFileInput :endpoint="'/customers/' + customer.id + '/upload-attachments'"></DropzoneFileInput>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import AttachmentList from '@/Components/AttachmentList.vue';
import AttachmentListProductMapping from '@/Components/AttachmentListProductMapping.vue';
import Button from '@/Components/Button.vue';
import DatePicker from '@/Components/DatePicker.vue';
import DropzoneFileInput from '@/Components/DropzoneFileInput.vue';
import FormInput from '@/Components/FormInput.vue';
import FormTextarea from '@/Components/FormTextarea.vue';
import Modal from '@/Components/Modal.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import SearchAddressInput from '@/Components/SearchAddressInput.vue';
import UploadFileInput from '@/Components/UploadFileInput.vue';
import { ArrowDownTrayIcon, ArrowTopRightOnSquareIcon, ArrowUturnLeftIcon, CheckCircleIcon, ClockIcon, LockClosedIcon, LockOpenIcon, ExclamationCircleIcon, MinusCircleIcon, StopCircleIcon, TrashIcon, XCircleIcon } from '@heroicons/vue/20/solid';
import { Dropdown, Tooltip, Menu, vTooltip } from 'floating-vue';
import { ref, computed, onMounted, watch, nextTick } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";

const props = defineProps({
  cmsEndpoint: String,
  vendOptions: Object,
  countries: Object,
  customerTagOptions: Object,
  days: Object,
  frequencyPerWeekOptions: [Array, Object],
  // Location grading rubric — { placement: { label, options: {A,B,C} }, ... }
  // Sourced from Customer::LOCATION_GRADING_CATEGORIES.
  locationGradingCategories: { type: Object, default: () => ({}) },
  // Notice Period dropdown options — sourced from
  // Customer::NOTICE_PERIOD_OPTIONS. Array of label strings.
  noticePeriodOptions: { type: Array, default: () => [] },
  locationTypeOptions: [Array, Object],
  operatorOptions: Object,
  bankOptions: [Array, Object],
  customer: Object,
  // Site status change history (newest first) for the Status History popup.
  statusHistory: { type: Array, default: () => [] },
  sellingPriceTypeOptions: [Array, Object],
  type: String,
  zoneOptions: Object,
});

const form = ref(useForm(getDefaultForm()));
const adminCustomerOptions = ref([]);
const booleanStrictOptions = ref([
  { id: 'true', value: 'Yes' },
  { id: 'false', value: 'No' },
]);

// Site lifecycle status — replaces the old "Is Active?" Yes/No field.
// `id` values are the integer customers.status_id stored server-side
// (App\Models\Customer::STATUS_* constants). Display order matches the
// product spec: Potential, New, Active, Pending, Inactive.
const statusOptions = ref([
  { id: 5, value: 'Potential' },
  { id: 4, value: 'New' },
  { id: 2, value: 'Active' },
  { id: 3, value: 'Removed' },
  { id: 1, value: 'Inactive' },
]);

// Status ids that prompt for an effective date when chosen (mirrors
// Customer::STATUS_DATE_FIELDS). 2 = Active → active_date, 3 = Removed →
// removed_date.
const STATUS_ACTIVE = 2;
const STATUS_REMOVED = 3;

// Resolve the status id from the live form selection, falling back to the
// saved customer value (used by the header badge below).
const currentStatusId = computed(() =>
  form.value?.status_id?.id ?? customer.value?.status_id ?? null
);
const statusLabel = computed(() => {
  const opt = statusOptions.value.find(o => o.id === currentStatusId.value);
  return opt ? opt.value : '—';
});
const statusBadgeClass = computed(() => {
  switch (currentStatusId.value) {
    case 2:  return 'bg-green-300'; // Active
    case 1:  return 'bg-red-300';   // Inactive
    default: return 'bg-amber-300'; // Potential / New / Removed
  }
});

// Date formatters used by the template (moment is used in the script, so these
// stay script-scoped helpers rather than calling moment() directly in markup).
const fmtDate = (d) => (d ? moment(d).format('YYYY-MM-DD') : '');
const fmtDateTime = (d) => (d ? moment(d).format('YYYY-MM-DD HH:mm') : '');

// ── Status effective-date prompt + history popup ──────────────────────────
const statusDateModal = ref({ open: false, status: null, prevStatus: null, date: '', error: '' });
const showStatusHistory = ref(false);
// Guards so the status watcher ignores (a) the initial hydration and (b) our
// own programmatic revert when the user cancels the date prompt.
const statusWatchReady = ref(false);
const suppressStatusWatch = ref(false);

function openStatusHistory() { showStatusHistory.value = true; }
function closeStatusHistory() { showStatusHistory.value = false; }

// Open the date prompt to correct the Active/Removed date WITHOUT changing the
// status (the backend logs a date-only change too). prevStatus = current status
// so Cancel is a no-op on the selection.
function editStatusDate(statusId) {
  const current = form.value.status_id;
  const preset = statusId === STATUS_REMOVED
    ? (form.value.removed_date ? moment(form.value.removed_date).format('YYYY-MM-DD') : moment().format('YYYY-MM-DD'))
    : (form.value.active_date ? moment(form.value.active_date).format('YYYY-MM-DD') : moment().format('YYYY-MM-DD'));
  statusDateModal.value = { open: true, status: statusId, prevStatus: current, date: preset, error: '' };
}

// When the user picks Active or Removed, prompt for the effective date. Other
// statuses save straight away (Inactive auto-stamps its date server-side).
watch(() => form.value.status_id, (newVal, oldVal) => {
  if (!statusWatchReady.value || suppressStatusWatch.value) return;
  const v = typeof newVal === 'object' && newVal !== null ? newVal.id : newVal;
  const prevId = typeof oldVal === 'object' && oldVal !== null ? oldVal.id : oldVal;
  // Ignore re-selecting the same status (object identity changes even when the
  // id doesn't).
  if (v === prevId) return;
  if (v === STATUS_ACTIVE || v === STATUS_REMOVED) {
    const preset = v === STATUS_REMOVED
      ? (form.value.removed_date || moment().format('YYYY-MM-DD'))
      : (form.value.active_date || moment().format('YYYY-MM-DD'));
    statusDateModal.value = { open: true, status: v, prevStatus: oldVal, date: preset, error: '' };
  }
});

function confirmStatusDate() {
  const d = statusDateModal.value.date;
  if (!d || d === 'Invalid date') {
    statusDateModal.value.error = 'Please choose a date.';
    return;
  }
  const formatted = moment(d).format('YYYY-MM-DD');
  const prev = statusDateModal.value.prevStatus;
  const prevId = typeof prev === 'object' && prev !== null ? prev.id : prev;
  if (statusDateModal.value.status === STATUS_REMOVED) {
    form.value.removed_date = formatted;
  } else if (statusDateModal.value.status === STATUS_ACTIVE) {
    form.value.active_date = formatted;
    // Re-activating opens a fresh interval — clear any prior removed date, but
    // ONLY on a genuine transition INTO Active (not when merely editing the
    // Active Date of a site that is already Active/Removed).
    if (prevId !== STATUS_ACTIVE) {
      form.value.removed_date = null;
    }
  }
  statusDateModal.value.open = false;
}

function cancelStatusDate() {
  // Revert the status selection without re-triggering the watcher.
  const prev = statusDateModal.value.prevStatus;
  suppressStatusWatch.value = true;
  form.value.status_id = prev;
  statusDateModal.value.open = false;
  nextTick(() => { suppressStatusWatch.value = false; });
}

const countryOptions = ref([]);
const customer = ref([]);
const customerTagOptions = ref([]);
const customerVendBindings = ref([]);
const frequencyPerWeekOptions = ref([]);
const isExisting = ref(1);
const locationTypeOptions = ref([]);
const operatorCountry = usePage().props.auth.operatorCountry;
// Address autofill provider (set via MAP_PROVIDER in .env): 'onemap' (SG) or
// 'google' (e.g. Indonesia). When a supported provider is set the search API
// is active; with none configured the API is skipped and Building/Street fall
// back to manual entry.
const mapProvider = usePage().props.mapProvider;
const addressApiEnabled = computed(() => ['onemap', 'google'].includes(mapProvider));
// Lock Building/Street only for OneMap — its postcode→address data is
// authoritative. Google's results are less consistent, so keep them editable.
const lockAddressFields = computed(() => mapProvider === 'onemap');
// Resolved operatorCountry as a countryOptions object — used to default the
// (now hidden) Country / Phone Code fields so the saved payload stays complete.
const operatorCountryOption = ref(null);
const operatorOptions = ref([]);
const bankOptions = ref([]);
const permissions = usePage().props.auth.permissions;
const profile = usePage().props.auth.profile;
const toast = useToast();
const vendChannels = ref([]);
const sellingPriceTypeOptions = ref([]);
const vendOptions = ref([]);
const zoneOptions = ref([]);

// ── Contract attachments (slim file field) ────────────────────────────────────
const contracts = ref([]);
const contractFileInput = ref(null);
const pendingContractFiles = ref([]);
const isUploadingContract = ref(false);

// ── Placement Contract Detail ─────────────────────────────────────────────────
const commissionTypeOptions = [
  { id: 'F',     label: 'F: Free Placement' },
  { id: 'S',     label: 'S: Subsidized Plan' },
  { id: 'R',     label: 'R: Fix Rental' },
  { id: 'U',     label: 'U: Utility only' },
  { id: 'R+U',   label: 'R + U' },
  { id: 'PS',    label: 'PS: Profit sharing only' },
  { id: 'PS+U',  label: 'PS + U' },
  { id: 'PSORU', label: 'PS OR U (whichever higher)' },
];

const PS_TYPES   = ['PS', 'PS+U', 'PSORU'];
// Types that render a second value field (Utility Amount). R+U is a flat
// Fix-Rental + Utility combo (no PS Term), so it joins the PS+U / PSORU
// two-value layout but stays out of PS_TYPES so its first field renders as a
// dollar amount and no PS Term input appears.
const TWO_VAL_TYPES = ['PS+U', 'PSORU', 'R+U'];

const isPsCommission = computed(() => PS_TYPES.includes(form.value.contract_commission_type));
const hasTwoValues   = computed(() => TWO_VAL_TYPES.includes(form.value.contract_commission_type));
const showPsTerm     = computed(() => isPsCommission.value);

// Performance Report Email — a tiny RFC-5322-ish guard. Good enough for
// gating the "Enable Send Performance to Email?" toggle; the server-side
// 'email' validator on update() does the authoritative check.
const EMAIL_RX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const isReportEmailValid = computed(() => {
  const v = form.value?.report_email;
  return typeof v === 'string' && EMAIL_RX.test(v.trim());
});

// Whenever the email becomes invalid (cleared, malformed), force the toggle
// back to "false" so a stale "true" can't survive an email deletion and
// silently leave the Email button enabled with no address to send to.
watch(isReportEmailValid, (valid) => {
  if (!valid && form.value && booleanStrictOptions.value?.length) {
    form.value.is_report_email_enabled = booleanStrictOptions.value.find(o => o.id === 'false');
  }
});

const commissionValueLabel = computed(() => {
  switch (form.value.contract_commission_type) {
    case 'S':     return 'Subsidized Amount';
    case 'R':     return 'Fix Rental Amount';
    case 'R+U':   return 'Fix Rental Amount';
    case 'U':     return 'Utility Amount';
    case 'PS':
    case 'PS+U':
    case 'PSORU': return 'Profit Sharing';
    default:      return 'Value';
  }
});

function onCommissionTypeChange() {
  // Clear value fields that no longer apply when type changes
  if (form.value.contract_commission_type === 'F') {
    form.value.contract_commission_value  = null;
    form.value.contract_commission_value2 = null;
    form.value.contract_ps_term           = null;
  }
  if (!TWO_VAL_TYPES.includes(form.value.contract_commission_type)) {
    form.value.contract_commission_value2 = null;
  }
  if (!PS_TYPES.includes(form.value.contract_commission_type)) {
    form.value.contract_ps_term = null;
  } else if (form.value.contract_ps_term === null || form.value.contract_ps_term === '') {
    // default PS Term to 70 when first selecting a PS-type option
    form.value.contract_ps_term = 70;
  }
}

function clearCommissionType() {
  form.value.contract_commission_type   = null;
  form.value.contract_commission_value  = null;
  form.value.contract_commission_value2 = null;
  form.value.contract_ps_term           = null;
}

// External Subsidize — when the toggle is switched off, clear the amount so a
// stale value can't survive a disable (server re-enforces this on save too).
function onExternalSubsidizeToggle() {
  if (!form.value.is_external_subsidize) {
    form.value.external_subsidize_amount = null;
  }
}
// ─────────────────────────────────────────────────────────────────────────────

// ── Schedule Future Contract Change ───────────────────────────────────────────
// A single pending future contract per customer. The form (sf) sits in the
// Placement Contract Detail section; on Confirm it POSTs to
// /customers/{id}/scheduled-contract. It does NOT change the live contract —
// the contract:apply-scheduled command does that on the effective date.
function blankScheduleForm() {
  return {
    open: false,
    effective_date: null,
    contract_commission_type: null,
    contract_commission_value: null,
    contract_commission_value2: null,
    contract_ps_term: null,
    is_external_subsidize: false,
    external_subsidize_amount: null,
    contract_from: null,
    contract_until: null,
    contract_auto_renewal: false,
    contract_notice_period: null,
    contract_remarks: null,
  };
}
const sf = ref(blankScheduleForm());
const sfErrors = ref({});
const isSavingSchedule = ref(false);

// The customer's pending scheduled contract (eager-loaded on the customer
// prop as `scheduled_contract`). Null when none is pending.
const scheduledRow = computed(() => customer.value?.scheduled_contract ?? null);

// Earliest selectable effective date = tomorrow. A future contract can never
// be backdated — the calendar disables today and earlier, the back-stepper is
// hidden, submitScheduledContract re-checks, and the server validates
// `after:today`.
const minScheduleDate = computed(() => moment().add(1, 'day').startOf('day').toDate());

const sfIsPs        = computed(() => PS_TYPES.includes(sf.value.contract_commission_type));
const sfHasTwoValues= computed(() => TWO_VAL_TYPES.includes(sf.value.contract_commission_type));
const sfShowPsTerm  = computed(() => sfIsPs.value);
const sfCommissionValueLabel = computed(() => {
  switch (sf.value.contract_commission_type) {
    case 'S':     return 'Subsidized Amount';
    case 'R':     return 'Fix Rental Amount';
    case 'R+U':   return 'Fix Rental Amount';
    case 'U':     return 'Utility Amount';
    case 'PS':
    case 'PS+U':
    case 'PSORU': return 'Profit Sharing';
    default:      return 'Value';
  }
});

function formatDate(d) {
  return d ? moment(d).format('YYYY-MM-DD') : '';
}

// Human-readable one-liner describing a scheduled change for the banner.
function scheduleSummary(row) {
  if (!row) return '';
  const typeOpt = commissionTypeOptions.find(o => o.id === row.contract_commission_type);
  const parts = [];
  parts.push('Location Fees: ' + (typeOpt ? typeOpt.label : (row.contract_commission_type || '—')));
  if (row.contract_commission_value !== null && row.contract_commission_value !== '') {
    parts.push('Value: ' + row.contract_commission_value);
  }
  if (row.contract_commission_value2 !== null && row.contract_commission_value2 !== '') {
    parts.push('Utility: ' + row.contract_commission_value2);
  }
  if (row.contract_ps_term !== null && row.contract_ps_term !== '') {
    parts.push('PS Term: ' + row.contract_ps_term + '%');
  }
  if (row.is_external_subsidize) {
    parts.push('Ext. Subsidize: ' + (row.external_subsidize_amount ?? '—'));
  }
  return parts.join(' · ');
}

function openScheduleForm(fromExisting) {
  sfErrors.value = {};
  if (fromExisting && scheduledRow.value) {
    const r = scheduledRow.value;
    sf.value = {
      open: true,
      effective_date: r.effective_date ? moment(r.effective_date).format('YYYY-MM-DD') : null,
      contract_commission_type: r.contract_commission_type ?? null,
      contract_commission_value: r.contract_commission_value ?? null,
      contract_commission_value2: r.contract_commission_value2 ?? null,
      contract_ps_term: r.contract_ps_term ?? null,
      is_external_subsidize: r.is_external_subsidize ?? false,
      external_subsidize_amount: r.external_subsidize_amount ?? null,
      contract_from: r.contract_from ? moment(r.contract_from).format('YYYY-MM-DD') : null,
      contract_until: r.contract_until ? moment(r.contract_until).format('YYYY-MM-DD') : null,
      contract_auto_renewal: r.contract_auto_renewal ?? false,
      contract_notice_period: r.contract_notice_period ?? null,
      contract_remarks: r.contract_remarks ?? null,
    };
  } else {
    // New schedule — start from the customer's CURRENT live contract as a
    // template, with a blank effective date for the user to pick.
    sf.value = {
      open: true,
      effective_date: null,
      contract_commission_type: form.value.contract_commission_type ?? null,
      contract_commission_value: form.value.contract_commission_value ?? null,
      contract_commission_value2: form.value.contract_commission_value2 ?? null,
      contract_ps_term: form.value.contract_ps_term ?? null,
      is_external_subsidize: form.value.is_external_subsidize ?? false,
      external_subsidize_amount: form.value.external_subsidize_amount ?? null,
      contract_from: form.value.contract_from ? moment(form.value.contract_from).format('YYYY-MM-DD') : null,
      contract_until: form.value.contract_until ? moment(form.value.contract_until).format('YYYY-MM-DD') : null,
      contract_auto_renewal: form.value.contract_auto_renewal ?? false,
      contract_notice_period: form.value.contract_notice_period ?? null,
      contract_remarks: form.value.contract_remarks ?? null,
    };
  }
}

function closeScheduleForm() {
  sf.value = blankScheduleForm();
  sfErrors.value = {};
}

function onSfCommissionTypeChange() {
  if (sf.value.contract_commission_type === 'F') {
    sf.value.contract_commission_value  = null;
    sf.value.contract_commission_value2 = null;
    sf.value.contract_ps_term           = null;
  }
  if (!TWO_VAL_TYPES.includes(sf.value.contract_commission_type)) {
    sf.value.contract_commission_value2 = null;
  }
  if (!PS_TYPES.includes(sf.value.contract_commission_type)) {
    sf.value.contract_ps_term = null;
  } else if (sf.value.contract_ps_term === null || sf.value.contract_ps_term === '') {
    sf.value.contract_ps_term = 70;
  }
}

function clearSfCommissionType() {
  sf.value.contract_commission_type   = null;
  sf.value.contract_commission_value  = null;
  sf.value.contract_commission_value2 = null;
  sf.value.contract_ps_term           = null;
}

function onSfExternalSubsidizeToggle() {
  if (!sf.value.is_external_subsidize) {
    sf.value.external_subsidize_amount = null;
  }
}

function submitScheduledContract(customerID) {
  sfErrors.value = {};

  // Client-side guard: a real future date is required.
  if (!sf.value.effective_date || sf.value.effective_date === 'Invalid date') {
    sfErrors.value.effective_date = 'Please pick an effective date.';
    return;
  }
  if (!moment(sf.value.effective_date).isAfter(moment(), 'day')) {
    sfErrors.value.effective_date = 'The effective date must be in the future.';
    return;
  }

  const effLabel = moment(sf.value.effective_date).format('YYYY-MM-DD');
  const approval = confirm(
    'Schedule this contract change to take effect on ' + effLabel + '?\n\n' +
    'It will not change the current contract now — it applies automatically on that date.'
  );
  if (!approval) return;

  const payload = {
    effective_date: effLabel,
    contract_commission_type: sf.value.contract_commission_type || null,
    contract_commission_value: (sf.value.contract_commission_value !== null && sf.value.contract_commission_value !== '') ? parseFloat(sf.value.contract_commission_value) : null,
    contract_commission_value2: (sf.value.contract_commission_value2 !== null && sf.value.contract_commission_value2 !== '') ? parseFloat(sf.value.contract_commission_value2) : null,
    contract_ps_term: (sf.value.contract_ps_term !== null && sf.value.contract_ps_term !== '') ? parseFloat(sf.value.contract_ps_term) : null,
    is_external_subsidize: sf.value.is_external_subsidize ?? false,
    external_subsidize_amount: (sf.value.is_external_subsidize && sf.value.external_subsidize_amount !== null && sf.value.external_subsidize_amount !== '') ? parseFloat(sf.value.external_subsidize_amount) : null,
    contract_from: (sf.value.contract_from && sf.value.contract_from !== 'Invalid date') ? moment(sf.value.contract_from).format('YYYY-MM-DD') : null,
    contract_until: (sf.value.contract_until && sf.value.contract_until !== 'Invalid date') ? moment(sf.value.contract_until).format('YYYY-MM-DD') : null,
    contract_auto_renewal: sf.value.contract_auto_renewal ?? false,
    contract_notice_period: (sf.value.contract_notice_period && String(sf.value.contract_notice_period).trim() !== '') ? sf.value.contract_notice_period : null,
    contract_remarks: (sf.value.contract_remarks && String(sf.value.contract_remarks).trim() !== '') ? sf.value.contract_remarks : null,
  };

  isSavingSchedule.value = true;
  router.post('/customers/' + customerID + '/scheduled-contract', payload, {
    preserveScroll: true,
    onSuccess: () => {
      router.reload({
        only: ['customer'],
        data: { id: customerID },
        preserveState: true,
        replace: true,
        onSuccess: () => {
          customer.value = props.customer;
          closeScheduleForm();
          toast.success('Future contract change scheduled', { timeout: 3000 });
        },
      });
    },
    onError: (errors) => {
      sfErrors.value = errors || {};
      toast.error('Failed to schedule. Please check the form.', { timeout: 3000 });
    },
    onFinish: () => { isSavingSchedule.value = false; },
  });
}

function cancelScheduledContract(customerID) {
  const approval = confirm('Cancel the scheduled contract change? This removes the pending change.');
  if (!approval) return;

  router.delete('/customers/' + customerID + '/scheduled-contract', {
    preserveScroll: true,
    onSuccess: () => {
      router.reload({
        only: ['customer'],
        data: { id: customerID },
        preserveState: true,
        replace: true,
        onSuccess: () => {
          customer.value = props.customer;
          closeScheduleForm();
          toast.success('Scheduled contract change cancelled', { timeout: 3000 });
        },
      });
    },
    onError: () => {
      toast.error('Failed to cancel. Please try again.', { timeout: 3000 });
    },
  });
}
// ─────────────────────────────────────────────────────────────────────────────

function getDefaultForm() {
  return {
    id: '',
    customer_id: '',
    person_id: '',
    operator_id: '',
    begin_date: '',
    frequency_per_week_status: '',
    status_id: '',
    is_restricted_access: '',
    location_type_id: '',
    ops_note: '',
    preferred_visit_days_json:  {
      1: false,
      2: false,
      3: false,
      4: false,
      5: false,
      6: false,
      7: false,
      8: false,
    },
    selling_price_type: '',
    termination_date: '',
    active_date: null,
    removed_date: null,
    code: '',
    name: '',
    // Site-level contact (separate from the billing contact relation).
    site_contact_person: '',
    site_phone_number: '',
    site_alt_phone_number: '',
    // Free-text remarks for the delivery address.
    address_remarks: '',
    is_gst_registered: false,
    address: {
      block_num: '',
      building: '',
      country_id: '',
      latitude: '',
      longitude: '',
      postcode: '',
      street_name: '',
      unit_num: '',
    },
    contact: {
      name: '',
      company: '',
      email: '',
      phone_country_id: '',
      phone_num: '',
      alt_phone_country_id: '',
      alt_phone_num: '',
    },
    // Billing Address — defaults to "same as delivery" (checkbox checked).
    is_billing_same_as_delivery: true,
    billing_address: {
      block_num: '',
      building: '',
      country_id: '',
      latitude: '',
      longitude: '',
      postcode: '',
      street_name: '',
      unit_num: '',
    },
    // Bank Details — required together (Bank Name + Account Name + Number).
    bank_id: '',
    bank_account_name: '',
    bank_account_number: '',
    vend_id: '',
    zone_id: '',
    contract_commission_type: null,
    contract_commission_value: null,
    contract_commission_value2: null,
    contract_ps_term: null,
    is_external_subsidize: false,
    external_subsidize_amount: null,
    contract_from: null,
    contract_until: null,
    contract_auto_renewal: false,
    contract_notice_period: null,
    contract_remarks: null,
    // Site-scoped tag bindings — populated from props.customer.tag_bindings
    // on mount and synced server-side via TagBindingService::sync().
    tags: [],
    // Performance Report email opt-in. report_email is a plain string;
    // is_report_email_enabled is the booleanStrictOptions object {id,value}
    // (matches how is_active / is_restricted_access are modelled), then
    // unpacked back to a boolean in saveCustomer's transform.
    report_email: '',
    is_report_email_enabled: null,
    // Location Grading — three independent radio groups, each A/B/C or null.
    location_grading_placement: null,
    location_grading_access: null,
    location_grading_flexibility: null,
  };
}

onMounted(() => {
  countryOptions.value = props.countries.data;
  // Resolve operator's country to its option object (falls back to the raw
  // model or first country) for defaulting the hidden Country/Phone Code fields.
  operatorCountryOption.value =
    countryOptions.value.find(c => c.id === operatorCountry?.id)
    || operatorCountry
    || countryOptions.value[0]
    || null;
  customer.value = props.customer;
  frequencyPerWeekOptions.value = [
    { id: '', value: '--- Clear ---' },
    ...Object.entries(props.frequencyPerWeekOptions).map(([id, value]) => {
      return {
        id: id,
        value: value,
      };
    })
  ]

  locationTypeOptions.value = [
    { id: '', value: '--- Clear ---' },
    ...props.locationTypeOptions.data.map(locationType => ({
      id: locationType.id,
      value: locationType.name,
    }))
  ];

  operatorOptions.value = props.operatorOptions.data;
  bankOptions.value = props.bankOptions && props.bankOptions.data ? props.bankOptions.data : [];
  // Tag options come from OptionsService::tags('App\\Models\\Customer'),
  // wrapped in a Resource collection → unwrap .data and keep id+name only.
  customerTagOptions.value = (props.customerTagOptions?.data ?? [])
    .map(tag => ({ id: tag.id, name: tag.name }));
  zoneOptions.value = [
    { id: '', value: '--- Clear ---' },
    ...props.zoneOptions.data.map(zone => ({
      id: zone.id,
      value: zone.name,
    }))
  ];
  sellingPriceTypeOptions.value = Object.entries(props.sellingPriceTypeOptions).map(([id, value]) => {
    return {
      id: id,
      value: value,
    };
  });
  const initialPreferredVisitDays = {
    1: false,
    2: false,
    3: false,
    4: false,
    5: false,
    6: false,
    7: false,
    8: false,
  };
  form.value = props.customer ? useForm({
    ...JSON.parse(JSON.stringify(props.customer)),
    code: props.customer && props.customer.person_id ? props.customer.virtual_customer_code + ' (' + props.customer.virtual_customer_prefix + ')' : (props.customer ? props.customer.code : null),
    frequency_per_week_status: props.customer && props.customer.frequency_per_week_status ? frequencyPerWeekOptions.value.find(frequencyPerWeek => frequencyPerWeek.id == props.customer.frequency_per_week_status) : null,
    location_type_id: props.customer ? props.customer.location_type_id ? locationTypeOptions.value.find(locationType => locationType.id === props.customer.location_type_id) : null : null,
    operator_id: props.customer ? props.customer.operator_id ? operatorOptions.value.find(operator => operator.id === props.customer.operator_id) : null : null,
    vend_id: '',
    person_id: props.customer ? props.customer.person_id : null,
    // Bank Details — map stored bank_id to its option object for the dropdown;
    // account name/number flow through via the JSON spread of props.customer.
    bank_id: props.customer && props.customer.bank_id ? bankOptions.value.find(b => b.id === props.customer.bank_id) : null,
    contact: props.customer ? {
      ...JSON.parse(JSON.stringify(props.customer.contact)),
      phone_country_id: props.customer && props.customer.contact ? countryOptions.value.find(country => country.id === props.customer.contact.phone_country_id) : null,
      alt_phone_country_id: props.customer && props.customer.contact ? countryOptions.value.find(country => country.id === props.customer.contact.alt_phone_country_id) : null,
    } : {
      name: '',
      email: '',
      phone_country_id: '',
      phone_num: '',
      alt_phone_country_id: '',
      alt_phone_num: '',
    },
    address: props.customer ? {
      ...props.customer.delivery_address,
      country_id: props.customer && props.customer.delivery_address ? countryOptions.value.find(country => country.id === props.customer.delivery_address.country_id) : null,
    } : {
      block_num: '',
      building: '',
      country_id: '',
      latitude: '',
      longitude: '',
      postcode: '',
      street_name: '',
      unit_num: '',
    },
    // Billing Address — defaults to "same as delivery" when the flag is unset
    // (e.g. before the migration backfill); maps the stored country_id to its
    // option object like the delivery address above.
    is_billing_same_as_delivery: (props.customer && props.customer.is_billing_same_as_delivery != null) ? !!props.customer.is_billing_same_as_delivery : true,
    billing_address: (props.customer && props.customer.billing_address) ? {
      ...props.customer.billing_address,
      country_id: countryOptions.value.find(country => country.id === props.customer.billing_address.country_id) || null,
    } : {
      block_num: '',
      building: '',
      country_id: '',
      latitude: '',
      longitude: '',
      postcode: '',
      street_name: '',
      unit_num: '',
    },
    // Status (replaces is_active). Pre-select the saved status_id; default a
    // brand-new customer to Active (id 2), matching the old is_active default.
    status_id: props.customer && props.customer.status_id
      ? (statusOptions.value.find(option => option.id === props.customer.status_id) ?? statusOptions.value.find(option => option.id === 2))
      : statusOptions.value.find(option => option.id === 2),
    is_restricted_access: props.customer ? props.customer.is_restricted_access ? booleanStrictOptions.value.find(option => option.id === 'true') : booleanStrictOptions.value.find(option => option.id === 'false') : booleanStrictOptions.value.find(option => option.id === 'false'),
    preferred_visit_days_json: { ...initialPreferredVisitDays, ...props.customer.preferred_visit_days_json },
    selling_price_type: props.customer && props.customer.selling_price_type ? sellingPriceTypeOptions.value.find(option => option.id == props.customer.selling_price_type) : sellingPriceTypeOptions.value.find(option => option.value === 'RP2'),
    zone_id: props.customer && props.customer.zone_id ? zoneOptions.value.find(zone => zone.id === props.customer.zone_id) : null,
    contract_commission_type: props.customer ? (props.customer.contract_commission_type ?? null) : null,
    contract_commission_value: props.customer ? (props.customer.contract_commission_value ?? null) : null,
    contract_commission_value2: props.customer ? (props.customer.contract_commission_value2 ?? null) : null,
    contract_ps_term: props.customer ? (props.customer.contract_ps_term ?? null) : null,
    is_external_subsidize: props.customer ? (props.customer.is_external_subsidize ?? false) : false,
    external_subsidize_amount: props.customer ? (props.customer.external_subsidize_amount ?? null) : null,
    contract_from: props.customer ? (props.customer.contract_from ?? null) : null,
    contract_until: props.customer ? (props.customer.contract_until ?? null) : null,
    contract_auto_renewal: props.customer ? (props.customer.contract_auto_renewal ?? false) : false,
    contract_notice_period: props.customer ? (props.customer.contract_notice_period ?? null) : null,
    contract_remarks: props.customer ? (props.customer.contract_remarks ?? null) : null,
    // Pre-select the multiselect with already-bound tags. tag_bindings comes
    // from the Site model with `tagBindings.tag` eager-loaded, so each
    // binding has a nested .tag object. Filter to options we know about so the
    // multiselect doesn't render orphan rows.
    tags: (props.customer?.tag_bindings ?? [])
      .map(tb => customerTagOptions.value.find(opt => opt.id === tb.tag?.id))
      .filter(Boolean),
    report_email: props.customer ? (props.customer.report_email ?? '') : '',
    is_report_email_enabled: props.customer
      ? (props.customer.is_report_email_enabled
          ? booleanStrictOptions.value.find(option => option.id === 'true')
          : booleanStrictOptions.value.find(option => option.id === 'false'))
      : booleanStrictOptions.value.find(option => option.id === 'false'),
    // Location Grading — bind directly to the stored char(1) value.
    location_grading_placement: props.customer ? (props.customer.location_grading_placement ?? null) : null,
    location_grading_access: props.customer ? (props.customer.location_grading_access ?? null) : null,
    location_grading_flexibility: props.customer ? (props.customer.location_grading_flexibility ?? null) : null,
  }) : useForm(getDefaultForm());

  // Country / Phone Code selectors were removed from the UI. Default these
  // hidden fields to the operator's country when no saved value exists, so the
  // payload stays complete without overwriting an existing record's values.
  if (!form.value.address.country_id) form.value.address.country_id = operatorCountryOption.value;
  if (!form.value.contact.phone_country_id) form.value.contact.phone_country_id = operatorCountryOption.value;
  if (!form.value.contact.alt_phone_country_id) form.value.contact.alt_phone_country_id = operatorCountryOption.value;
  if (form.value.billing_address && !form.value.billing_address.country_id) form.value.billing_address.country_id = operatorCountryOption.value;

  vendChannels.value = props.customer && props.customer.vend ? props.customer.vend.vend_channels : [];

  vendOptions.value = props.vendOptions.map(vend => ({
    id: vend.id,
    full_name: vend.code,
  }));

  customerVendBindings.value = props.customer ? props.customer.customer_vend_bindings : [];

  contracts.value = props.customer && props.customer.contracts ? props.customer.contracts : [];

  // Arm the status-change watcher only AFTER hydration has flushed, so the
  // initial status assignment from props doesn't pop the date prompt.
  nextTick(() => { statusWatchReady.value = true; });
});

function compareSellingPrice(channel) {
  if (channel.product && channel.product.selling_prices[0] && channel.product.selling_prices[0].amount) {
    if (channel.amount != channel.product.selling_prices[0].amount) {
      return 'text-red-500';
    }
  }
  return 'text-gray-800';
}

function deleteCustomer(customerID) {
  form.value.clearErrors();

  form.value.delete('/customers/' + customerID, {
    onSuccess: () => {
      router.push('/customers');
    },
    preserveState: true,
    replace: true,
  });
}

function disconnectCMSCustomer(customerID) {
  const approval = confirm('Are you sure to disconnect this customer from CMS ?');
  if (!approval) {
    return;
  }

  form.value.clearErrors();

  form.value.post('/customers/' + customerID + '/disconnect-cms', {
    onSuccess: () => {
      location.reload();
    },
    preserveState: true,
    replace: true,
  });
}

function formatDatetime(datetime) {
  return datetime ? moment(datetime).format('YYYY-MM-DD hh:mm a') : '';
}

// Resolve a country_id to its display name using the already-loaded
// countryOptions list. Used by the read-only "CMS Customer Details"
// mirror block (billing / delivery country).
function countryName(id) {
  if (!id) return '';
  const country = countryOptions.value.find(c => c.id === id);
  return country ? country.name : '';
}

function toggleActivationCustomer(customerID) {
  form.value.clearErrors();

  form.value.post('/customers/' + customerID + '/toggle-activation', {
    onSuccess: () => { },
    preserveState: true,
    replace: true,
  });
}

function saveCustomer(customerID) {
  form.value.clearErrors();

  form.value.transform((data) => ({
    id: data.vend_id ? data.vend_id.id : null,
    // tag_ids lives at the top level — server reads it via $request->input('tag_ids')
    // and applies TagBindingService::sync() after the customer is updated.
    tag_ids: Array.isArray(data.tags) ? data.tags.map(t => t?.id).filter(v => v != null) : [],
    customer: {
      ...data,
      begin_date: data.begin_date && data.begin_date != 'Invalid date' ? data.begin_date : null,
      termination_date: data.termination_date && data.termination_date != 'Invalid date' ? data.termination_date : null,
      // Site lifecycle dates (set via the status-change prompt).
      active_date: data.active_date && data.active_date != 'Invalid date' ? moment(data.active_date).format('YYYY-MM-DD') : null,
      removed_date: data.removed_date && data.removed_date != 'Invalid date' ? moment(data.removed_date).format('YYYY-MM-DD') : null,
      frequency_per_week_status: data.frequency_per_week_status ? data.frequency_per_week_status.id : null,
      location_type_id: data.location_type_id ? data.location_type_id.id : null,
      operator_id: data.operator_id ? data.operator_id.id : null,
      contact: {
        ...data.contact,
        phone_country_id: data.contact.phone_country_id ? data.contact.phone_country_id.id : null,
        alt_phone_country_id: data.contact.alt_phone_country_id ? data.contact.alt_phone_country_id.id : null,
      },
      address: {
        ...data.address,
        country_id: data.address.country_id ? data.address.country_id.id : null,
      },
      // Billing address — is_billing_same_as_delivery flows through via ...data.
      // When checked the server ignores this payload and mirrors delivery.
      billing_address: {
        ...data.billing_address,
        country_id: data.billing_address && data.billing_address.country_id ? data.billing_address.country_id.id : null,
      },
      // Status — send the integer status_id. The server mirrors is_active
      // from this (is_active = status_id === STATUS_ACTIVE).
      status_id: data.status_id ? data.status_id.id : null,
      is_restricted_access: data.is_restricted_access.id,
      // GST flag is always a clean boolean.
      is_gst_registered: data.is_gst_registered ?? false,
      selling_price_type: data.selling_price_type ? data.selling_price_type.id : null,
      bank_id: data.bank_id ? data.bank_id.id : null,
      vend_id: data.vend_id ? data.vend_id.id : null,
      zone_id: data.zone_id ? data.zone_id.id : null,
      // Contract details
      contract_commission_type: data.contract_commission_type || null,
      contract_commission_value: data.contract_commission_value !== null && data.contract_commission_value !== '' ? parseFloat(data.contract_commission_value) : null,
      contract_commission_value2: data.contract_commission_value2 !== null && data.contract_commission_value2 !== '' ? parseFloat(data.contract_commission_value2) : null,
      contract_ps_term: data.contract_ps_term !== null && data.contract_ps_term !== '' ? parseFloat(data.contract_ps_term) : null,
      // External Subsidize — only persist the amount while the toggle is on;
      // clear to null otherwise (server re-enforces this defensively).
      is_external_subsidize: data.is_external_subsidize ?? false,
      external_subsidize_amount: (data.is_external_subsidize && data.external_subsidize_amount !== null && data.external_subsidize_amount !== '')
        ? parseFloat(data.external_subsidize_amount)
        : null,
      contract_from: data.contract_from && data.contract_from !== 'Invalid date' ? data.contract_from : null,
      contract_until: data.contract_until && data.contract_until !== 'Invalid date' ? data.contract_until : null,
      contract_auto_renewal: data.contract_auto_renewal ?? false,
      // Notice Period is now a label string from Customer::NOTICE_PERIOD_OPTIONS
      // ('1 wk', '1.5 mth', 'NO need', 'Cant ETerm', etc.) — pass through as-is,
      // server-side `in:` validation owns the allowed-value check.
      contract_notice_period: data.contract_notice_period && String(data.contract_notice_period).trim() !== '' ? data.contract_notice_period : null,
      contract_remarks: data.contract_remarks && String(data.contract_remarks).trim() !== '' ? data.contract_remarks : null,
      // Performance Report Email — normalise empty strings to null so the
      // DB stores NULL rather than '', and force-clear the enabled flag
      // when the email is missing/invalid so the Summary "Email" button
      // can never appear without a deliverable address.
      report_email: data.report_email && String(data.report_email).trim() !== ''
        ? String(data.report_email).trim()
        : null,
      is_report_email_enabled: (data.report_email && String(data.report_email).trim() !== '')
        ? (data.is_report_email_enabled?.id === 'true')
        : false,
      // Location Grading — coerce '' to null so the DB stores NULL, and
      // only accept the rubric's A/B/C codes (server-side validator
      // double-checks via `in:A,B,C`).
      location_grading_placement: ['A','B','C'].includes(data.location_grading_placement) ? data.location_grading_placement : null,
      location_grading_access: ['A','B','C'].includes(data.location_grading_access) ? data.location_grading_access : null,
      location_grading_flexibility: ['A','B','C'].includes(data.location_grading_flexibility) ? data.location_grading_flexibility : null,
    }
  })).post('/customers/' + customerID + '/update', {
    onSuccess: () => {
      router.reload({
        only: ['customer'],
        data: {
          id: form.value.id,
        },
        replace: true,
        preserveState: true,
        onSuccess: () => {
          customer.value = props.customer;
          vendChannels.value = props.customer.vend.vend_channels;
          vendChannels.value = [...vendChannels.value];
          toast.success("Successfully Saved", {
            timeout: 3000
          });
        },
        onError: (errors) => {
          toast.error("Failed, Please Try Again", {
            timeout: 3000
          });
        },
      });
    },
    preserveState: true,
    replace: true,
  });
}

function saveVend(vendID) {
  form.value.clearErrors();

  form.value.transform((data) => ({
    ...data,
    begin_date: data.begin_date && data.begin_date != 'Invalid date' ? data.begin_date : null,
    termination_date: data.termination_date && data.termination_date != 'Invalid date' ? data.termination_date : null,
    active_date: data.active_date && data.active_date != 'Invalid date' ? moment(data.active_date).format('YYYY-MM-DD') : null,
    removed_date: data.removed_date && data.removed_date != 'Invalid date' ? moment(data.removed_date).format('YYYY-MM-DD') : null,
    is_testing: data.is_testing.id,
  })).post('/customers/' + vendID + '/update', {
    onSuccess: () => { },
    preserveState: true,
    replace: true,
  });
}

function submit() {
  form.value.clearErrors();

  form.value.post('/customers/' + form.value.id + '/update', {
    onSuccess: () => {
      emit('modalClose');
    },
    preserveState: true,
    replace: true,
  });
}

function onAddressSelected(address) {
  form.value.address = {
    block_num: address.BLK_NO,
    building: address.BUILDING,
    country_id: operatorCountryOption.value,
    latitude: address.LATITUDE,
    longitude: address.LONGTITUDE,
    postcode: address.POSTAL,
    street_name: address.ROAD_NAME,
    unit_num: '',
  };
}

function onBillingAddressSelected(address) {
  form.value.billing_address = {
    block_num: address.BLK_NO,
    building: address.BUILDING,
    country_id: operatorCountryOption.value,
    latitude: address.LATITUDE,
    longitude: address.LONGTITUDE,
    postcode: address.POSTAL,
    street_name: address.ROAD_NAME,
    unit_num: '',
  };
}

function onSellingPriceTypeSelected() {
  router.reload({
    only: ['customer'],
    data: {
      id: form.value.id,
      selling_price_type: form.value.selling_price_type.id,
    },
    replace: true,
    preserveState: true,
    onSuccess: page => {
      customer.value = props.customer;
      vendChannels.value = props.customer.vend.vend_channels;
      vendChannels.value = [...vendChannels.value];
    }
  });
}

function bindVend(customerID) {
  form.value.clearErrors();

  form.value.post('/customers/' + customerID + '/bind-vend', {
    vendID: form.value.vend_id.id
  }, {
    onSuccess: () => { },
    preserveState: true,
    replace: true,
  });
}

function unbindCustomer(vendID) {
  const approval = confirm('Are you sure to unbind this customer?');
  if (!approval) {
    return;
  }
  form.value.clearErrors();

  form.value.post('/vends/' + vendID + '/unbind-customer/customers', {
    onSuccess: () => {
      router.reload({
        only: ['customer'],
        data: {
          id: form.value.id,
        },
        replace: true,
        preserveState: true,
        onSuccess: (page) => {
          customer.value = props.customer;
          vendChannels.value = props.customer.vend ? props.customer.vend.vend_channels : [];
        },
      });
    },
  });
}

function unbindCustomerDeactivate(vendID) {
  const approval = confirm('Are you sure to unbind this customer and deactivate it ?');
  if (!approval) {
    return;
  }

  form.value.clearErrors();
  form.value
      .post('/vends/' + vendID + '/unbind-customer-deactivate/settings', {
        onSuccess: () => {
          router.reload({
            only: ['customer'],
            data: {
              id: form.value.id,
            },
            replace: true,
            preserveState: true,
            onSuccess: (page) => {
              customer.value = props.customer;
              vendChannels.value = props.customer.vend ? props.customer.vend.vend_channels : [];
            },
          });
        },
        preserveState: true,
        replace: true,
      })
}

function onContractFilesSelected(e) {
  pendingContractFiles.value = Array.from(e.target.files || []);
}

async function uploadContractFiles() {
  if (!pendingContractFiles.value.length) {
    return;
  }
  if (!customer.value || !customer.value.id) {
    toast.error('Save the customer first before uploading contracts', { timeout: 3000 });
    return;
  }

  isUploadingContract.value = true;
  const url = '/customers/' + customer.value.id + '/upload-contracts';
  let failed = 0;

  for (const file of pendingContractFiles.value) {
    const fd = new FormData();
    fd.append('files', file);
    try {
      await axios.post(url, fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
    } catch (err) {
      console.error('Contract upload failed for', file.name, err);
      failed++;
    }
  }

  pendingContractFiles.value = [];
  if (contractFileInput.value) {
    contractFileInput.value.value = '';
  }

  router.reload({
    only: ['customer'],
    data: { id: form.value.id },
    replace: true,
    preserveState: true,
    onSuccess: () => {
      contracts.value = props.customer && props.customer.contracts ? props.customer.contracts : [];
      isUploadingContract.value = false;
      if (failed > 0) {
        toast.error(failed + ' file(s) failed to upload', { timeout: 3000 });
      } else {
        toast.success('Contract uploaded successfully', { timeout: 3000 });
      }
    },
    onError: () => {
      isUploadingContract.value = false;
      toast.error('Failed to refresh contract list, please reload', { timeout: 3000 });
    },
  });
}

function deleteContract(id) {
  const approval = confirm('Delete this contract attachment?');
  if (!approval) {
    return;
  }
  router.delete('/attachments/' + id, {
    preserveState: true,
    preserveScroll: true,
    onSuccess: () => {
      contracts.value = contracts.value.filter(c => c.id !== id);
      toast.success('Contract deleted', { timeout: 3000 });
    },
    onError: () => {
      toast.error('Delete failed, please try again', { timeout: 3000 });
    },
  });
}

function downloadVendSnapshot(vendSnapshotId) {
  axios({
    method: 'get',
    url: '/customers/customer-snapshots/excel/' + vendSnapshotId,
    responseType: 'blob',
  }).then(response => {
    fileDownload(response.data, 'Vending_Channels_' + moment().format('YYMMDDhhmmss') + '.xlsx');
  }).catch(error => {
    console.log(error);
  }).finally(() => { });
}
</script>

