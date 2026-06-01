<template>
  <Head title="Site" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Create Site
      </h2>
    </template>

     <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
        <div class="shadow-sm ring-1 ring-black ring-opacity-5 p-5 mb-3">
          <form @submit.prevent="submit" id="submit">
            <div class=" pb-5">
                <!-- <h3 class="text-base font-semibold leading-6 text-gray-900 pb-3">Site</h3> -->
                <div class="relative mb-5">
                  <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-gray-300"></div>
                  </div>
                  <div class="relative flex justify-start">
                    <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> Site </span>
                  </div>
                </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-6 pb-5 mb-3">
              <div class="sm:col-span-6 flex space-x-1">
                <div
                    class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit"
                    :class="customer.status_id === 2 ? 'bg-green-300' : (customer.status_id === 1 ? 'bg-red-300' : 'bg-amber-300')"
                    v-if="customer.id"
                >
                  <span>{{ customer.status_name || '—' }}</span>
                </div>
                <div
                    class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit bg-green-300"
                    v-if="customer.id && customer.person_id"
                >
                    <div class="flex flex-col">
                      From CMS
                    </div>
                </div>
                <!-- <div
                    class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit bg-gray-300"
                    v-if="customer.id && !customer.vend"
                >
                    <div class="flex flex-col">
                      No Site Binding
                    </div>
                </div> -->
              </div>

              <!--
                "Pull From CMS" create option disabled — we're moving toward
                detaching the mark1↔CMS link. The Create flow is forced to
                "Create New Site" via isExisting=0 below. Keep the markup
                commented (not deleted) so it can be restored quickly if the
                CMS link is re-enabled.
              -->
              <fieldset class="sm:col-span-6" v-if="false">
                <legend class="sr-only">Plan</legend>
                <div class="flex space-x-5">
                  <div class="relative flex items-start" v-if="cmsEndpoint">
                    <div class="flex h-6 items-center">
                      <input id="isExisting" aria-describedby="is-existing-description" name="isExisting" type="radio" v-model="isExisting" value="1" class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                    </div>
                    <div class="ml-3 text-sm leading-6">
                      <label for="is_existing" class="font-medium text-gray-900">Pull From CMS</label>
                    </div>
                  </div>
                  <div class="relative flex items-start">
                    <div class="flex h-6 items-center">
                      <input id="isExisting" aria-describedby="is-new-description" name="isExisting" type="radio" v-model="isExisting" value="0" class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                    </div>
                    <div class="ml-3 text-sm leading-6">
                      <label for="is_new" class="font-medium text-gray-900">Create New Site</label>
                    </div>
                  </div>
                </div>
              </fieldset>

              <div class="sm:col-span-6" v-if="!customer.id && isExisting == 1">
                <div class="sm:col-span-6">
                  <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                    CMS Customer
                  </label>
                  <MultiSelect
                    v-model="form.cms_customer_id"
                    :options="cmsCustomerOptions"
                    trackBy="id"
                    valueProp="id"
                    label="full_name"
                    placeholder="Select"
                    open-direction="bottom"
                    class="mt-1"
                  >
                  </MultiSelect>
                </div>
              </div>
            </div>

              <div class="sm:col-span-6 grid grid-cols-1 gap-3 sm:grid-cols-6" v-if="(customer.id && !customer.person_id) || (!customer.id && isExisting != 1)">
                <!-- <div class="sm:col-span-2">
                  <FormInput v-model="form.code" :error="form.errors.code" :disabled="form.person_id" placeholderStr="Cust Code">
                    Cust Code
                  </FormInput>
                </div> -->
                <div class="sm:col-span-5">
                  <FormInput v-model="form.name" :error="form.errors.name" required="true" :disabled="form.person_id" placeholderStr="Site Name">
                    Site Name
                  </FormInput>
                </div>

                <div class="sm:col-span-2">
                  <DatePicker v-model="form.begin_date" :error="form.errors.begin_date" @input="onDateFromChanged()"
                  v-if="permissions.includes('update customers')">
                    Begin Date
                  </DatePicker>
                </div>
              </div>

              <div class="sm:col-span-6 grid grid-cols-1 gap-3 sm:grid-cols-6" v-if="(customer.id && !customer.person_id) || (!customer.id && isExisting != 1)">
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
                  ></MultiSelect>
                </div>

                <!-- Operator — moved up from its own section; placed right after
                     Reference Price Type. Inline with it on desktop. -->
                <div class="sm:col-span-3">
                  <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                    Operator
                    <span class="text-red-500">
                       *
                    </span>
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
                  >
                  </MultiSelect>
                  <div class="text-sm text-red-600" v-if="form.errors.operator_id">
                    {{ form.errors.operator_id }}
                  </div>
                </div>
              </div>
              <!-- <div class="sm:col-span-2">
                <DatePicker v-model="form.customer.termination_date" :error="form.errors['customer.termination_date']" :minDate="form.customer.begin_date"
                v-if="permissions.includes('update customers')" disabled="true">
                  Termination Date
                </DatePicker>
              </div> -->

          <div class="sm:col-span-6 grid grid-cols-1 gap-3 sm:grid-cols-6 pb-6" v-if="(customer.id && !customer.person_id) || (!customer.id && isExisting != 1)">
            <div class="sm:col-span-6 pt-2 pb-1 md:pt-6 md:pb-3">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-start">
                  <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> Delivery Address </span>
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
              <SearchAddressInput v-model="form.address.postcode" @selected="onAddressSelected" :error="form.errors['address.postcode']" :disabled="customer.person_id">
                Postcode <span class="text-gray-400 font-normal">(key in to autofill)</span>
              </SearchAddressInput>
            </div>
            <div class="sm:col-span-3">
              <FormInput v-model="form.address.unit_num" :error="form.errors['address.unit_num']" placeholderStr="Unit Num">
                Unit Num
              </FormInput>
            </div>
            <div class="sm:col-span-3">
              <FormInput v-model="form.address.block_num" :error="form.errors['address.block_num']" :disabled="customer.person_id" placeholderStr="Block Num">
                Block Num
              </FormInput>
            </div>
            <div class="sm:col-span-3">
              <FormInput v-model="form.address.building" :error="form.errors['address.building']" :disabled="customer.person_id" placeholderStr="Building Name">
                Building Name
              </FormInput>
            </div>
            <div class="sm:col-span-3">
              <FormInput v-model="form.address.street_name" :error="form.errors['address.street_name']" :disabled="customer.person_id" placeholderStr="Street Name">
                Street Name
              </FormInput>
            </div>
            <!-- Country field removed from UI — single-country localized deployment.
                 form.address.country_id is defaulted from the operator's country
                 (auth.operatorCountry) in onMounted. -->
            <div class="sm:col-span-3 hidden">
              <FormInput v-model="form.address.latitude" placeholderStr="Latitude">
                Latitude
              </FormInput>
            </div>
            <div class="sm:col-span-3 hidden">
              <FormInput v-model="form.address.longitude" placeholderStr="Longitude">
                Longitude
              </FormInput>
            </div>

            <!-- Site-level contact (distinct from the Billing Contact below).
                 Stored directly on the customers table. Phone is plain text —
                 no country code (single-country localized deployment). -->
            <div class="sm:col-span-6">
              <FormInput v-model="form.site_contact_person" :error="form.errors.site_contact_person" :disabled="customer.person_id" placeholderStr="Site Contact Person">
                Site Contact Person
              </FormInput>
            </div>
            <div class="sm:col-span-3">
              <FormInput v-model="form.site_phone_number" :error="form.errors.site_phone_number" :disabled="customer.person_id" placeholderStr="Site Phone Number">
                Site Phone Number
              </FormInput>
            </div>
            <div class="sm:col-span-3">
              <FormInput v-model="form.site_alt_phone_number" :error="form.errors.site_alt_phone_number" :disabled="customer.person_id" placeholderStr="Alt Site Phone Number">
                Alt Site Phone Number
              </FormInput>
            </div>

            <!-- Remarks for the delivery address — free text, stored on the
                 customers table (address_remarks). -->
            <div class="sm:col-span-6">
              <FormTextarea v-model="form.address_remarks" :error="form.errors.address_remarks" :disabled="customer.person_id" placeholderStr="e.g. Loading bay at rear, ask security for access" rows="2">
                Remarks for Address
              </FormTextarea>
            </div>

            </div>

            <!-- Billing & Bank Section — highlighted container (matches Placement Contract Detail styling) -->
            <div class="sm:col-span-6 bg-blue-50 border border-blue-200 rounded-lg p-4 mt-3 mb-3 shadow-sm" v-if="(customer.id && !customer.person_id) || (!customer.id && isExisting != 1)">
              <!-- Billing Contact -->
              <h3 class="text-lg font-semibold text-gray-900 mb-3 pb-2 border-b border-blue-200">
                Billing Contact
              </h3>
              <div class="grid grid-cols-1 gap-3 sm:grid-cols-6">
            <div class="sm:col-span-6">
              <FormInput v-model="form.contact.company" :error="form.errors['contact.company']" :disabled="customer.person_id" placeholderStr="Company">
                Billing To (Company Full Name or Personal Name)
              </FormInput>
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
              <FormInput v-model="form.contact.name" :error="form.errors['contact.name']" :disabled="customer.person_id" placeholderStr="Contact Person">
                Billing Contact Person
              </FormInput>
            </div>
            <div class="sm:col-span-3">
              <FormTextarea v-model="form.contact.email" :error="form.errors['contact.email']" :disabled="customer.person_id" placeholderStr="One email per line (or comma-separated)" rows="2">
                Email
              </FormTextarea>
            </div>
            <!-- Phone Code removed from UI — single-country localized deployment.
                 form.contact.phone_country_id is defaulted from the operator's
                 country (auth.operatorCountry) in onMounted. -->
            <div class="sm:col-span-3">
              <FormInput v-model="form.contact.phone_num" :error="form.errors['contact.phone_num']" :disabled="customer.person_id" placeholderStr="Phone Number">
                Phone Number
              </FormInput>
            </div>
            <!-- Alt Phone Code removed from UI — defaulted from operator country. -->
            <div class="sm:col-span-3">
              <FormInput v-model="form.contact.alt_phone_num" :error="form.errors['contact.alt_phone_num']" :disabled="customer.person_id" placeholderStr="Alt Phone Number">
                Alt Phone Number
              </FormInput>
            </div>

            <!-- Billing Address — "same as Delivery Address" toggle. When
                 checked (default) the billing fields are hidden and the server
                 mirrors the delivery address into the billing record. When
                 unchecked the user fills a dedicated billing address. -->
            <div class="sm:col-span-6 flex items-center gap-2 mt-1">
              <input
                type="checkbox"
                id="billing_same_as_delivery"
                v-model="form.is_billing_same_as_delivery"
                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
              />
              <label for="billing_same_as_delivery" class="text-sm font-medium text-gray-700 cursor-pointer">
                Billing Address same as Delivery Address
              </label>
            </div>

            <template v-if="!form.is_billing_same_as_delivery">
              <div class="sm:col-span-6">
                <SearchAddressInput v-model="form.billing_address.postcode" @selected="onBillingAddressSelected" :error="form.errors['billing_address.postcode']">
                  Postcode <span class="text-gray-400 font-normal">(key in to autofill)</span>
                </SearchAddressInput>
              </div>
              <div class="sm:col-span-3">
                <FormInput v-model="form.billing_address.unit_num" :error="form.errors['billing_address.unit_num']" placeholderStr="Unit Num">
                  Unit Num
                </FormInput>
              </div>
              <div class="sm:col-span-3">
                <FormInput v-model="form.billing_address.block_num" :error="form.errors['billing_address.block_num']" placeholderStr="Block Num">
                  Block Num
                </FormInput>
              </div>
              <div class="sm:col-span-3">
                <FormInput v-model="form.billing_address.building" :error="form.errors['billing_address.building']" placeholderStr="Building Name">
                  Building Name
                </FormInput>
              </div>
              <div class="sm:col-span-3">
                <FormInput v-model="form.billing_address.street_name" :error="form.errors['billing_address.street_name']" placeholderStr="Street Name">
                  Street Name
                </FormInput>
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
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Bank Name
                </label>
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
                <div class="text-sm text-red-600" v-if="form.errors.bank_id">
                  {{ form.errors.bank_id }}
                </div>
              </div>
              <div class="sm:col-span-3">
                <FormInput v-model="form.bank_account_name" :error="form.errors.bank_account_name" placeholderStr="Account Holder Name">
                  Account Holder Name
                </FormInput>
              </div>
              <div class="sm:col-span-3">
                <FormInput v-model="form.bank_account_number" :error="form.errors.bank_account_number" placeholderStr="Account Number">
                  Account Number
                </FormInput>
              </div>
              </div>
            </div>

              <div class="sm:col-span-6 mt-4">
                <span class="flex justify-between">
                  <span class="flex space-x-1">
                    <Button
                      type="button"
                      class="bg-green-500 hover:bg-green-600 text-white flex space-x-1"
                      v-if="permissions.includes('update customers')"
                      @click.prevent="saveCustomer()"
                    >
                      <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                      <span>
                        Save Site
                      </span>
                    </Button>
                    <Link :href="'/customers'">
                      <Button
                        class="bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1"
                      >
                        <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
                        <span>
                          Back
                        </span>
                      </Button>
                    </Link>
                  </span>
                </span>
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
import Button from '@/Components/Button.vue';
import DatePicker from '@/Components/DatePicker.vue';
import FormInput from '@/Components/FormInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import SearchAddressInput from '@/Components/SearchAddressInput.vue';
import FormTextarea from '@/Components/FormTextarea.vue';
import { ArrowPathIcon, ArrowTopRightOnSquareIcon, ArrowUturnDownIcon, ArrowUturnLeftIcon, CheckCircleIcon, PaperClipIcon, XCircleIcon, ExclamationCircleIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { fromPairs } from 'lodash';
import { vTooltip } from 'floating-vue';

const props = defineProps({
    cmsCustomerOptions: Object,
    vendOptions: Object,
    countries: Object,
    operatorOptions: Object,
    bankOptions: [Array, Object],
    customer: Object,
    type: String,
    cmsEndpoint: String,
    sellingPriceTypeOptions: [Array, Object],
  })

const form = ref(
  useForm(getDefaultForm())
)

const adminCustomerOptions = ref([])

const booleanStrictOptions = ref([
    {id: 'true', value: 'Yes'},
    {id: 'false', value: 'No'},
])

const cmsCustomerOptions = ref([])
const countryOptions = ref([])
// Operator's country — single-country localized deployment. Used to default
// the (now hidden) Country and Phone Code fields so the saved payload stays
// complete even though the selectors were removed from the UI.
const operatorCountry = usePage().props.auth.operatorCountry
const operatorCountryOption = ref(null)
// Pull-From-CMS path is disabled — force the Create page into the
// "Create New Customer" branch regardless of whether cmsEndpoint is set.
const isExisting = ref(0)
const operatorOptions = ref([])
const bankOptions = ref([])
const permissions = usePage().props.auth.permissions
const vendOptions = ref([])
const sellingPriceTypeOptions = ref([])

function getDefaultForm() {
  return {
    id: '',
    customer_id: '',
    // customer: {
    operator_id: '',
    begin_date: '',
    termination_date: '',
    id: '',
    code: '',
    name: '',
    // Site-level contact (separate from the billing contact relation).
    site_contact_person: '',
    site_phone_number: '',
    site_alt_phone_number: '',
    // Free-text remarks for the delivery address.
    address_remarks: '',
    address: {
      block_num: '',
      building: '',
      country_id: '',
      latitude: '',
      longitude: '',
      map_url: '',
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
    is_gst_registered: false,
    // Billing Address — defaults to "same as delivery" (checkbox checked),
    // so these fields stay hidden until the user unchecks it.
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
    // },
    // Bank Details — required together (Bank Name + Account Name + Number).
    bank_id: '',
    bank_account_name: '',
    bank_account_number: '',
    // },
    vend_id: '',
    cms_customer_id: '',
    // Pull-From-CMS path is disabled in the UI; always start as "Create New".
    is_existing: 0,
    selling_price_type: '',
  }
}

onMounted(() => {
  cmsCustomerOptions.value = Object.values(props.cmsCustomerOptions).map(data => ({
  id: data.id,
  full_name: data.prefix + '-' + data.code + ' - ' + data.company
}));
  countryOptions.value = props.countries.data
  // Resolve the operator's country to the matching option object (so the
  // transform's `.id` access works), falling back to the raw operatorCountry
  // model or the first country in the list.
  operatorCountryOption.value =
    countryOptions.value.find(c => c.id === operatorCountry?.id)
    || operatorCountry
    || countryOptions.value[0]
    || null
  operatorOptions.value = props.operatorOptions.data
  bankOptions.value = props.bankOptions && props.bankOptions.data ? props.bankOptions.data : []
  sellingPriceTypeOptions.value = Object.entries(props.sellingPriceTypeOptions).map(([id, value]) => {
    return {
      id: id,
      value: value,
    };
  });
  form.value = useForm(getDefaultForm())
  form.value.selling_price_type = sellingPriceTypeOptions.value.find(option => option.value === 'RP2')

  // Default the hidden Country / Phone Code fields to the operator's country.
  form.value.address.country_id = operatorCountryOption.value
  form.value.billing_address.country_id = operatorCountryOption.value
  form.value.contact.phone_country_id = operatorCountryOption.value
  form.value.contact.alt_phone_country_id = operatorCountryOption.value

  vendOptions.value = props.vendOptions.map(vend => ({
    id: vend.id,
    full_name: vend.code,
  }))

  // adminCustomerOptions.value = props.adminCustomerOptions.data.map(customer => ({
  //   id: customer.id,
  //   full_name: customer.person_id ? customer.virtual_customer_code + ' (' + customer.virtual_customer_prefix + ') - ' + customer.name  : customer.code + ' - ' + customer.name,
  // }))
})

function deleteCustomer(customerID) {
  form.value.clearErrors()

  form.value
    .delete('/customers/' + customerID, {
    onSuccess: () => {
      router.push('/customers')
    },
    preserveState: true,
    replace: true,
  })
}

function toggleActivationCustomer(customerID) {
  form.value.clearErrors()

  form.value
    .post('/customers/' + customerID + '/toggle-activation', {
    onSuccess: () => {
      // emit('modalClose')
    },
    preserveState: true,
    replace: true,
  })
}

function saveCustomer() {
  form.value.clearErrors()

  // console.log(JSON.parse(JSON.stringify(form.value.vend_id)))
  form.value
    .transform((data) => ({
      ...data,
      cms_customer_id: data.cms_customer_id ? data.cms_customer_id.id : null,
      begin_date: data.begin_date && data.begin_date != 'Invalid date' ? data.begin_date : null,
      termination_date: data.termination_date && data.termination_date != 'Invalid date' ? data.termination_date : null,
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
      billing_address: {
        ...data.billing_address,
        country_id: data.billing_address.country_id ? data.billing_address.country_id.id : null,
      },
      is_existing: isExisting.value,
      selling_price_type: data.selling_price_type ? data.selling_price_type.id : null,
      bank_id: data.bank_id ? data.bank_id.id : null,
    }))
    .post('/customers/store', {
    onSuccess: () => {
      // emit('modalClose')
    },
    preserveState: true,
    replace: true,
  })
}

function saveVend(vendID) {
  form.value.clearErrors()

  form.value
    .transform((data) => ({
      ...data,
      begin_date: data.begin_date && data.begin_date != 'Invalid date' ? data.begin_date : null,
      termination_date: data.termination_date && data.termination_date != 'Invalid date' ? data.termination_date : null,
      is_testing: data.is_testing.id,
    }))
    .post('/customers/' + vendID + '/update', {
    onSuccess: () => {
      // emit('modalClose')
    },
    preserveState: true,
    replace: true,
  })
}

function submit() {
  form.value.clearErrors()

  form.value
    .post('/customers/' + form.value.id + '/update', {
    onSuccess: () => {
      emit('modalClose')
    },
    preserveState: true,
    replace: true,
  })
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
  }
  // searchAddressForm.value = null
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
  }
}

function unbindCustomer(vendID) {
  form.value
    .post('/vends/' + vendID + '/unbind-customer', {
      onSuccess: () => {
      },
      preserveState: true,
      replace: true,
    })
}

function downloadVendSnapshot(vendSnapshotId) {
    axios({
        method: 'get',
        url: '/customers/customer-snapshots/excel/' + vendSnapshotId,
        responseType: 'blob',
    }).then(response => {
        fileDownload(response.data, 'Vending_Channels_' + moment().format('YYMMDDhhmmss') +'.xlsx')
    }).catch(error => {
        console.log(error)
    }).finally(() => {
    })
}
</script>