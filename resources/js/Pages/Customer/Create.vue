<template>
  <Head title="Customer" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Create Customer
      </h2>
    </template>

     <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
        <div class="shadow-sm ring-1 ring-black ring-opacity-5 p-5 mb-3">
          <form @submit.prevent="submit" id="submit">
            <div class=" pb-5">
                <!-- <h3 class="text-base font-semibold leading-6 text-gray-900 pb-3">Customer</h3> -->
                <div class="relative mb-5">
                  <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-gray-300"></div>
                  </div>
                  <div class="relative flex justify-start">
                    <span class="px-2 bg-white text-lg font-medium text-gray-900 rounded"> Customer </span>
                  </div>
                </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-6 pb-5 mb-3">
              <div class="sm:col-span-6 flex space-x-1">
                <div
                    class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit"
                    :class="[customer.is_active ? 'bg-green-300' : 'bg-red-300']"
                    v-if="customer.id"
                >
                  <span v-if="customer.is_active">
                    Active
                  </span>
                  <span v-if="!customer.is_active">
                    Not Active
                  </span>
                </div>
                <div
                    class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit bg-green-300"
                    v-if="customer.id && customer.person_id"
                >
                    <div class="flex flex-col">
                      From CMS
                    </div>
                </div>
                <div
                    class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit bg-gray-300"
                    v-if="customer.id && !customer.vend"
                >
                    <div class="flex flex-col">
                      No Customer Binding
                    </div>
                </div>
              </div>

              <fieldset class="sm:col-span-6" v-if="!customer.id">
                <legend class="sr-only">Plan</legend>
                <div class="space-y-5">
                  <div class="relative flex items-start">
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
                      <label for="is_new" class="font-medium text-gray-900">Create New Customer</label>
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
                <div class="sm:col-span-2">
                  <FormInput v-model="form.code" :error="form.errors.code" :disabled="form.person_id">
                    Cust Code
                  </FormInput>
                </div>
                <div class="sm:col-span-3">
                  <FormInput v-model="form.name" :error="form.errors.name" required="true" :disabled="form.person_id">
                    Cust Name
                  </FormInput>
                </div>

                <div class="sm:col-span-2">
                  <DatePicker v-model="form.begin_date" :error="form.errors.begin_date" @input="onDateFromChanged()"
                  v-if="permissions.includes('update customers')">
                    Begin Date
                  </DatePicker>
                </div>
              </div>
              <!-- <div class="sm:col-span-2">
                <DatePicker v-model="form.customer.termination_date" :error="form.errors['customer.termination_date']" :minDate="form.customer.begin_date"
                v-if="permissions.includes('update customers')" disabled="true">
                  Termination Date
                </DatePicker>
              </div> -->

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
              <FormInput v-model="form.contact.name" :error="form.errors['contact.name']" :disabled="customer.person_id">
                Name
              </FormInput>
            </div>
            <div class="sm:col-span-3">
              <FormInput v-model="form.contact.email" :error="form.errors['contact.email']" :disabled="customer.person_id">
                Email
              </FormInput>
            </div>
            <div class="sm:col-span-2">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Phone Code
              </label>
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
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors['contact.phone_country_id']">
                {{ form.errors['contact.phone_country_id'] }}
              </div>
            </div>
            <div class="sm:col-span-4">
              <FormInput v-model="form.contact.phone_num" :error="form.errors['contact.phone_num']" :disabled="customer.person_id">
                Phone Number
              </FormInput>
            </div>

            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
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
              <SearchAddressInput v-model="form.address.postcode" @selected="onAddressSelected" :error="form.errors['address.postcode']" :disabled="customer.person_id">
                Postcode
              </SearchAddressInput>
            </div>
            <div class="sm:col-span-3">
              <FormInput v-model="form.address.unit_num" :error="form.errors['address.unit_num']">
                Unit Num
              </FormInput>
            </div>
            <div class="sm:col-span-3">
              <FormInput v-model="form.address.block_num" :error="form.errors['address.block_num']" :disabled="customer.person_id">
                Block Num
              </FormInput>
            </div>
            <div class="sm:col-span-3">
              <FormInput v-model="form.address.building" :error="form.errors['address.building']" :disabled="customer.person_id">
                Building Name
              </FormInput>
            </div>
            <div class="sm:col-span-3">
              <FormInput v-model="form.address.street_name" :error="form.errors['address.street_name']" :disabled="customer.person_id">
                Street Name
              </FormInput>
            </div>
            <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Country
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
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors['address.country_id']">
                {{ form.errors['address.country_id'] }}
              </div>
            </div>
            <div class="sm:col-span-3 hidden">
              <FormInput v-model="form.address.latitude">
                Latitude
              </FormInput>
            </div>
            <div class="sm:col-span-3 hidden">
              <FormInput v-model="form.address.longitude">
                Longitude
              </FormInput>
            </div>
            </div>

            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-start">
                  <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> Operator </span>
                </div>
              </div>
            </div>

            <div class="col-span-12 sm:col-span-6">
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
                open-direction="top"
                class="mt-1"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.operator_id">
                {{ form.errors.operator_id }}
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
                        Save Customer
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
import { ArrowPathIcon, ArrowUturnDownIcon, ArrowUturnLeftIcon, CheckCircleIcon, PaperClipIcon, XCircleIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { fromPairs } from 'lodash';

const props = defineProps({
    cmsCustomerOptions: Object,
    vendOptions: Object,
    countries: Object,
    operatorOptions: Object,
    customer: Object,
    type: String,
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
const isExisting = ref(1)
const operatorOptions = ref([])
const permissions = usePage().props.auth.permissions
const vendOptions = ref([])

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
    // },
    vend_id: '',
    cms_customer_id: '',
    is_existing: 1,
  }
}

onMounted(() => {
  cmsCustomerOptions.value = Object.values(props.cmsCustomerOptions).map(data => ({
  id: data.id,
  full_name: data.prefix + '-' + data.code + ' - ' + data.company
}));
  countryOptions.value = props.countries.data
  operatorOptions.value = props.operatorOptions.data
  form.value = useForm(getDefaultForm())

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
      },
      address: {
        ...data.address,
        country_id: data.address.country_id ? data.address.country_id.id : null,
      },
      is_existing: isExisting.value,
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
    country_id: countryOptions.value[0],
    latitude: address.LATITUDE,
    longitude: address.LONGTITUDE,
    postcode: address.POSTAL,
    street_name: address.ROAD_NAME,
    unit_num: '',
  }
  // searchAddressForm.value = null
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