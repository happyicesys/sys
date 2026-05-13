<template>
  <Head title="Customer" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Editing Customer
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
        <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 p-5 mb-3">
            <form @submit.prevent="submit" id="submit">
              <div class="pb-5">
                <!-- Customer Header -->
                <div class="relative mb-5">
                  <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-gray-300"></div>
                  </div>
                  <div class="relative flex justify-start">
                    <span class="px-2 bg-white text-lg font-medium text-gray-900 rounded"> Customer </span>
                  </div>
                </div>

                <!-- Customer Form Fields -->
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-6 pb-2 mb-2">
                  <div class="sm:col-span-6 flex space-x-1">
                    <div
                      class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit"
                      :class="[customer.is_active ? 'bg-green-300' : 'bg-red-300']"
                    >
                      <span v-if="customer.is_active"> Active </span>
                      <span v-if="!customer.is_active"> Not Active </span>
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
                      Customer ID#
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
                      open-direction="top"
                      class="mt-1"
                    ></MultiSelect>
                    <div class="text-sm text-red-600" v-if="form.errors.operator_id">
                      {{ form.errors.operator_id }}
                    </div>
                    <div class="text-blue-600 text-xs">
                      ** Changing Operator will change Vending Machine's Operator as well
                    </div>
                  </div>

                  <div class="sm:col-span-4" v-if="customer.id && customer.person_id">
                    <label for="text" class="flex justify-start text-sm font-medium text-gray-700"> Customer </label>
                    <div class="mt-1">
                      <input
                        type="text"
                        class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                        :value="customer.person_id ? ' (' + customer.virtual_customer_code + ')  ' + customer.name : customer.code + ' - ' + customer.name"
                        disabled
                      />
                    </div>
                    <div class="text-blue-600 text-xs" v-if="customer.person_id">
                      ** Customer Data only editable from CMS
                      <span>
                        <a :class="[form.person_id ? 'text-blue-700' : 'text-gray-500']" target="_blank" :href="'//admin.happyice.com.sg/person/' + form.person_id + '/edit'">
                          (Click Here)
                        </a>
                      </span>
                    </div>
                  </div>

                  <div class="sm:col-span-4 grid grid-cols-1 gap-3 sm:grid-cols-6" v-if="(customer.id && !customer.person_id) || (!customer.id && isExisting != 1)">
                    <div class="sm:col-span-5">
                      <FormInput v-model="form.name" :error="form.errors.name" required="true" :disabled="form.person_id">
                        Cust Name
                      </FormInput>
                    </div>
                  </div>

                  <div class="sm:col-span-2" v-if="customer.id">
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
                    <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                      Is Active? (Customer)
                    </label>
                    <MultiSelect
                      v-model="form.is_active"
                      :options="booleanStrictOptions"
                      trackBy="id"
                      valueProp="id"
                      label="value"
                      placeholder="Select"
                      open-direction="bottom"
                      class="mt-1"
                    ></MultiSelect>
                    <div class="text-sm text-red-600" v-if="form.errors['customer.is_active']">
                      {{ form.errors['customer.is_active'] }}
                    </div>
                  </div>
                  <div class="sm:col-span-3">
                    <DatePicker v-model="form.begin_date" :error="form.errors.begin_date" @input="onDateFromChanged()" v-if="permissions.includes('update customers')">
                      Begin Date
                    </DatePicker>
                  </div>

                  <!-- Placement Contract Detail Section — highlighted container -->
                  <div class="sm:col-span-6 bg-blue-50 border border-blue-200 rounded-lg p-4 mt-3 mb-3 shadow-sm">
                    <h3 class="text-lg font-semibold text-gray-900 mb-3 pb-2 border-b border-blue-200">
                      Placement Contract Detail
                    </h3>
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
                        <span class="ml-1 text-gray-400 text-xs font-normal">(months)</span>
                      </label>
                      <div class="mt-1">
                        <input
                          type="number"
                          step="1"
                          min="0"
                          v-model="form.contract_notice_period"
                          class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md"
                          placeholder="e.g. 1"
                        />
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

                  <!--
                    Performance Report Email opt-in.
                    The toggle is force-disabled (and force-false) when no
                    valid email is entered, so the Customer Summary "Email"
                    button can rely on is_report_email_enabled === true to
                    mean "this customer has agreed to receive the report at
                    a known address".
                  -->
                  <div class="sm:col-span-6">
                    <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                      Customer Tags
                      <ExclamationCircleIcon
                        class="w-5 h-5 self-center pl-1"
                        v-tooltip="'Manage tag list under Customer Management → Tags'"
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
                      placeholder="customer@example.com"
                    >
                      Performance Report Email
                    </FormInput>
                  </div>
                  <div class="sm:col-span-3">
                    <label class="flex justify-start text-sm font-medium text-gray-700">
                      Enable Send Performance to Email?
                      <ExclamationCircleIcon
                        class="w-5 h-5 self-center pl-1"
                        v-tooltip="'Toggle on to surface the Email action button on Customer Summary. Disabled until a valid email is entered.'"
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
                    <FormInput v-model="form.power_socket_key_number" :error="form.errors.power_socket_key_number"> Power Socket Key Num </FormInput>
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
                  <div class="sm:col-span-5">
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
                        Unbind Machine & Deactivate Customer
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

                <!-- Contact Section -->
                <div class="sm:col-span-6 grid grid-cols-1 gap-3 sm:grid-cols-6" v-if="(customer.id && !customer.person_id) || (!customer.id && isExisting != 1)">
                  <div class="sm:col-span-6 pt-2 pb-1 md:pt-6 md:pb-3">
                    <div class="relative">
                      <div class="absolute inset-0 flex items-center" aria-hidden="true">
                        <div class="w-full border-t border-gray-300"></div>
                      </div>
                      <div class="relative flex justify-start">
                        <span class="px-4 bg-white text-lg font-medium text-gray-900 rounded"> Contact </span>
                      </div>
                    </div>
                  </div>
                  <div class="sm:col-span-3">
                    <FormInput v-model="form.contact.name" :error="form.errors['contact.name']" :disabled="customer.person_id"> Name </FormInput>
                  </div>
                  <div class="sm:col-span-3">
                    <FormInput v-model="form.contact.email" :error="form.errors['contact.email']" :disabled="customer.person_id"> Email </FormInput>
                  </div>
                  <div class="sm:col-span-2">
                    <label for="text" class="flex justify-start text-sm font-medium text-gray-700"> Phone Code </label>
                    <MultiSelect
                      v-model="form.contact.phone_country_id"
                      :options="countryOptions"
                      :disabled="form.person_id"
                      trackBy="id"
                      valueProp="id"
                      label="phone_code"
                      placeholder="Select"
                      open-direction="bottom"
                      class="mt-1"
                    ></MultiSelect>
                    <div class="text-sm text-red-600" v-if="form.errors['contact.phone_country_id']">
                      {{ form.errors['contact.phone_country_id'] }}
                    </div>
                  </div>
                  <div class="sm:col-span-4">
                    <FormInput v-model="form.contact.phone_num" :error="form.errors['contact.phone_num']" :disabled="customer.person_id"> Phone Number </FormInput>
                  </div>
                </div>

                <!-- Address Section -->
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

                  <div class="sm:col-span-6 pt-2 mt-2 md:pt-5 pb-3">
                    <div class="relative">
                      <div class="absolute inset-0 flex items-center" aria-hidden="true">
                        <div class="w-full border-t border-gray-300"></div>
                      </div>
                      <div class="relative flex justify-start">
                        <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> Address </span>
                      </div>
                    </div>
                  </div>

                  <div class="sm:col-span-6">
                    <FormInput v-model="form.address.map_url" :error="form.errors['address.map_url']">
                      <div class="flex space-x-1">
                        <span>
                          Google Map URL
                        </span>
                        <span v-if="form.address.map_url">
                          <a class="text-blue-700" target="_blank" rel="noopener noreferrer" :href=" '//' + form.address.map_url">
                            <ArrowTopRightOnSquareIcon class="w-4 h-4"></ArrowTopRightOnSquareIcon>
                          </a>
                        </span>
                      </div>
                    </FormInput>
                  </div>
                  <div class="sm:col-span-6">
                    <SearchAddressInput v-model="form.address.postcode" @selected="onAddressSelected" :error="form.errors['address.postcode']" :disabled="customer.person_id"> Postcode </SearchAddressInput>
                  </div>
                  <div class="col-span-3">
                    <FormInput v-model="form.address.unit_num" :error="form.errors['address.unit_num']"> Unit Num </FormInput>
                  </div>
                  <div class="col-span-3">
                    <FormInput v-model="form.address.block_num" :error="form.errors['address.block_num']" :disabled="customer.person_id"> Block Num </FormInput>
                  </div>
                  <div class="col-span-3">
                    <FormInput v-model="form.address.building" :error="form.errors['address.building']" :disabled="customer.person_id"> Building Name </FormInput>
                  </div>
                  <div class="col-span-5">
                    <FormTextarea v-model="form.address.street_name" :error="form.errors['address.street_name']" :disabled="customer.person_id">
                      Street Name
                    </FormTextarea>
                  </div>
                  <div class="col-span-3">
                    <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                      Country
                      <span class="text-red-500">
                        *
                      </span>
                    </label>
                    <MultiSelect
                      v-model="form.address.country_id"
                      :options="countryOptions"
                      trackBy="id"
                      valueProp="id"
                      label="name"
                      placeholder="Select"
                      open-direction="bottom"
                      class="mt-1"
                      :disabled="customer.person_id"
                    ></MultiSelect>
                    <div class="text-sm text-red-600" v-if="form.errors['address.country_id']">
                      {{ form.errors['address.country_id'] }}
                    </div>
                  </div>
                  <div class="sm:col-span-3 hidden">
                    <FormInput v-model="form.address.latitude"> Latitude </FormInput>
                  </div>
                  <div class="sm:col-span-3 hidden">
                    <FormInput v-model="form.address.longitude"> Longitude </FormInput>
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
                          <span> Save Customer </span>
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
                          <span> Delete Customer </span>
                        </Button>
                      </span>
                    </span>
                  </div>

                  <div class="relative pt-2 m-5">
                    <div class="absolute inset-0 flex items-center" aria-hidden="true">
                      <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-start">
                      <span class="px-2 bg-white text-lg font-medium text-gray-900 rounded"> Machine Binding History </span>
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
                      <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded-md"> Photo(s) </span>
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
                      <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded-md"> Attachment(s) </span>
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
import MultiSelect from '@/Components/MultiSelect.vue';
import SearchAddressInput from '@/Components/SearchAddressInput.vue';
import UploadFileInput from '@/Components/UploadFileInput.vue';
import { ArrowDownTrayIcon, ArrowTopRightOnSquareIcon, ArrowUturnLeftIcon, CheckCircleIcon, LockClosedIcon, LockOpenIcon, ExclamationCircleIcon, MinusCircleIcon, StopCircleIcon, TrashIcon, XCircleIcon } from '@heroicons/vue/20/solid';
import { Dropdown, Tooltip, Menu, vTooltip } from 'floating-vue';
import { ref, computed, onMounted, watch } from 'vue';
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
  locationTypeOptions: [Array, Object],
  operatorOptions: Object,
  customer: Object,
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

const countryOptions = ref([]);
const customer = ref([]);
const customerTagOptions = ref([]);
const customerVendBindings = ref([]);
const frequencyPerWeekOptions = ref([]);
const isExisting = ref(1);
const locationTypeOptions = ref([]);
const operatorCountry = usePage().props.auth.operatorCountry;
const operatorOptions = ref([]);
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
  { id: 'PS',    label: 'PS: Profit sharing only' },
  { id: 'PS+U',  label: 'PS + U' },
  { id: 'PSORU', label: 'PS OR U (whichever higher)' },
];

const PS_TYPES   = ['PS', 'PS+U', 'PSORU'];
const TWO_VAL_TYPES = ['PS+U', 'PSORU'];

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
    case 'U':     return 'Utility Amount';
    case 'PS':
    case 'PS+U':
    case 'PSORU': return 'Commission';
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
// ─────────────────────────────────────────────────────────────────────────────

function getDefaultForm() {
  return {
    id: '',
    customer_id: '',
    person_id: '',
    operator_id: '',
    begin_date: '',
    frequency_per_week_status: '',
    is_active: '',
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
    code: '',
    name: '',
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
      email: '',
      phone_country_id: '',
      phone_num: '',
    },
    vend_id: '',
    zone_id: '',
    contract_commission_type: null,
    contract_commission_value: null,
    contract_commission_value2: null,
    contract_ps_term: null,
    contract_from: null,
    contract_until: null,
    contract_auto_renewal: false,
    contract_notice_period: null,
    contract_remarks: null,
    // Customer-scoped tag bindings — populated from props.customer.tag_bindings
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
    contact: props.customer ? {
      ...JSON.parse(JSON.stringify(props.customer.contact)),
      phone_country_id: props.customer && props.customer.contact ? countryOptions.value.find(country => country.id === props.customer.contact.phone_country_id) : null,
    } : {
      name: '',
      email: '',
      phone_country_id: '',
      phone_num: '',
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
    is_active: props.customer ? props.customer.is_active ? booleanStrictOptions.value.find(option => option.id === 'true') : booleanStrictOptions.value.find(option => option.id === 'false') : booleanStrictOptions.value.find(option => option.id === 'true'),
    is_restricted_access: props.customer ? props.customer.is_restricted_access ? booleanStrictOptions.value.find(option => option.id === 'true') : booleanStrictOptions.value.find(option => option.id === 'false') : booleanStrictOptions.value.find(option => option.id === 'false'),
    preferred_visit_days_json: { ...initialPreferredVisitDays, ...props.customer.preferred_visit_days_json },
    selling_price_type: props.customer && props.customer.selling_price_type ? sellingPriceTypeOptions.value.find(option => option.id == props.customer.selling_price_type) : sellingPriceTypeOptions.value.find(option => option.value === 'RP2'),
    zone_id: props.customer && props.customer.zone_id ? zoneOptions.value.find(zone => zone.id === props.customer.zone_id) : null,
    contract_commission_type: props.customer ? (props.customer.contract_commission_type ?? null) : null,
    contract_commission_value: props.customer ? (props.customer.contract_commission_value ?? null) : null,
    contract_commission_value2: props.customer ? (props.customer.contract_commission_value2 ?? null) : null,
    contract_ps_term: props.customer ? (props.customer.contract_ps_term ?? null) : null,
    contract_from: props.customer ? (props.customer.contract_from ?? null) : null,
    contract_until: props.customer ? (props.customer.contract_until ?? null) : null,
    contract_auto_renewal: props.customer ? (props.customer.contract_auto_renewal ?? false) : false,
    contract_notice_period: props.customer ? (props.customer.contract_notice_period ?? null) : null,
    contract_remarks: props.customer ? (props.customer.contract_remarks ?? null) : null,
    // Pre-select the multiselect with already-bound tags. tag_bindings comes
    // from the Customer model with `tagBindings.tag` eager-loaded, so each
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

  vendChannels.value = props.customer && props.customer.vend ? props.customer.vend.vend_channels : [];

  vendOptions.value = props.vendOptions.map(vend => ({
    id: vend.id,
    full_name: vend.code,
  }));

  customerVendBindings.value = props.customer ? props.customer.customer_vend_bindings : [];

  contracts.value = props.customer && props.customer.contracts ? props.customer.contracts : [];
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
      frequency_per_week_status: data.frequency_per_week_status ? data.frequency_per_week_status.id : null,
      location_type_id: data.location_type_id ? data.location_type_id.id : null,
      operator_id: data.operator_id ? data.operator_id.id : null,
      contact: {
        ...data.contact,
        phone_country_id: data.contact.phone_country_id ? data.contact.phone_country_id.id : null,
      },
      address: {
        ...data.address,
        country_id: data.address.country_id ? data.address.country_id.id : null,
      },
      is_active: data.is_active.id,
      is_restricted_access: data.is_restricted_access.id,
      selling_price_type: data.selling_price_type ? data.selling_price_type.id : null,
      vend_id: data.vend_id ? data.vend_id.id : null,
      zone_id: data.zone_id ? data.zone_id.id : null,
      // Contract details
      contract_commission_type: data.contract_commission_type || null,
      contract_commission_value: data.contract_commission_value !== null && data.contract_commission_value !== '' ? parseFloat(data.contract_commission_value) : null,
      contract_commission_value2: data.contract_commission_value2 !== null && data.contract_commission_value2 !== '' ? parseFloat(data.contract_commission_value2) : null,
      contract_ps_term: data.contract_ps_term !== null && data.contract_ps_term !== '' ? parseFloat(data.contract_ps_term) : null,
      contract_from: data.contract_from && data.contract_from !== 'Invalid date' ? data.contract_from : null,
      contract_until: data.contract_until && data.contract_until !== 'Invalid date' ? data.contract_until : null,
      contract_auto_renewal: data.contract_auto_renewal ?? false,
      contract_notice_period: data.contract_notice_period !== null && data.contract_notice_period !== '' ? parseInt(data.contract_notice_period) : null,
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
    country_id: countryOptions.value[0],
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

