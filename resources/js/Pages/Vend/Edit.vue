<template>
  <Head title="Delivery Product Mapping" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Editing
        {{ vend.code }}
      </h2>
    </template>

     <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
        <div class="shadow-sm ring-1 ring-black ring-opacity-5 p-5 mb-3">
          <form @submit.prevent="submit" id="submit">
            <div class="border-b border-gray-300 pb-5">
                <!-- <h3 class="text-base font-semibold leading-6 text-gray-900 pb-3">Customer</h3> -->
                <div class="relative pb-5">
                  <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-gray-300"></div>
                  </div>
                  <div class="relative flex justify-start">
                    <span class="px-2 bg-white text-lg font-medium text-gray-900 rounded"> Customer </span>
                  </div>
                </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-6 pb-5 mb-3">
              <!-- <div class="sm:col-span-6 pb-1 md:pt-5 md:pb-3"> -->

                <!-- <div class="relative">
                  <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-gray-300"></div>
                  </div>
                  <div class="relative flex justify-center">
                    <span class="px-2 bg-white text-lg font-medium text-gray-900 rounded"> Customer </span>
                  </div>
                </div> -->
              <!-- </div> -->
              <div class="sm:col-span-2">
                <FormInput v-model="form.customer_code" :error="form.errors.customer_code" :disabled="form.person_id">
                  Cust Code
                </FormInput>
              </div>
              <div class="sm:col-span-3">
                <FormInput v-model="form.customer_name" :error="form.errors.customer_name" required="true" :disabled="form.person_id">
                  Cust Name
                </FormInput>
              </div>
              <div class="sm:col-span-6 text-blue-600 text-xs" v-if="form.person_id">
                ** Customer Data only editable from CMS
                <span>
                  <a :class="[form.person_id ? 'text-blue-700' : 'text-gray-500']" target="_blank" :href="'//admin.happyice.com.sg/person/' + form.person_id + '/edit'">
                    (Click Here)
                  </a>
                </span>
              </div>
              <div class="sm:col-span-2">
                <DatePicker v-model="form.binding.begin_date" :error="form.errors['binding.begin_date']" @input="onDateFromChanged()"
                v-if="permissions.includes('update vends')">
                  Begin Date
                </DatePicker>
              </div>
              <div class="sm:col-span-2">
                <DatePicker v-model="form.binding.termination_date" :error="form.errors['binding.termination_date']" :minDate="form.binding.begin_date"
                v-if="permissions.includes('update vends')" disabled="true">
                  Termination Date
                </DatePicker>
              </div>

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
              <FormInput v-model="form.contact.name" :error="form.errors['contact.name']" :disabled="form.person_id">
                Name
              </FormInput>
            </div>
            <div class="sm:col-span-3">
              <FormInput v-model="form.contact.email" :error="form.errors['contact.email']" :disabled="form.person_id">
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
              <FormInput v-model="form.contact.phone_num" required="true" :error="form.errors['contact.phone_num']" :disabled="form.person_id">
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
              <SearchAddressInput v-model="form.address.postcode" @selected="onAddressSelected" required="true" :error="form.errors['address.postcode']">
                Postcode
              </SearchAddressInput>
            </div>
            <div class="sm:col-span-3">
              <FormInput v-model="form.address.unit_num" required="true" :error="form.errors['address.unit_num']">
                Unit Num
              </FormInput>
            </div>
            <div class="sm:col-span-3">
              <FormInput v-model="form.address.block_num" :error="form.errors['address.block_num']">
                Block Num
              </FormInput>
            </div>
            <div class="sm:col-span-3">
              <FormInput v-model="form.address.building" :error="form.errors['address.building']">
                Building Name
              </FormInput>
            </div>
            <div class="sm:col-span-3">
              <FormInput v-model="form.address.street_name" required="true" :error="form.errors['address.street_name']">
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

            <div class="sm:col-span-6">
              <div class="flex space-x-1 mt-5 justify-between">
                <span class="flex space-x-1">
                  <Link :href="'/vends'">
                    <Button
                      class="bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1"
                    >
                      <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
                      <span>
                        Back
                      </span>
                    </Button>
                  </Link>
                  <Button
                    type="button"
                    class="bg-yellow-500 hover:bg-yellow-600 text-white flex space-x-1"
                    v-if="vend && vend.customer && permissions.includes('update vends')"
                    @click="unbindCustomer(form.id)"
                  >
                    <ArrowUturnDownIcon class="w-4 h-4"></ArrowUturnDownIcon>
                    <span>
                      Unbind
                    </span>
                  </Button>
                  <Button
                    type="submit"
                    class="bg-green-500 hover:bg-green-600 text-white flex space-x-1"
                    v-if="permissions.includes('update vends')"
                  >
                    <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                    <span>
                      Save
                    </span>
                  </Button>
                </span>
              </div>
            </div>
          </div>
          </div>

          <div class="grid grid-cols-1 gap-3 sm:grid-cols-6 pb-5 mb-3">
            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center">
                  <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> Vending Machine </span>
                </div>
              </div>
            </div>

            <div class="sm:col-span-2">
              <DatePicker v-model="form.begin_date" :error="form.errors.begin_date" @input="onDateFromChanged()"
              v-if="permissions.includes('update vends')">
                Begin Date
              </DatePicker>
            </div>
            <div class="sm:col-span-2">
              <DatePicker v-model="form.termination_date" :error="form.errors.termination_date" :minDate="form.begin_date"
              v-if="permissions.includes('update vends')">
                Retired Date
              </DatePicker>
            </div>

            <div class="sm:col-span-6">
              <Button
                  class="bg-red-500 hover:bg-red-600 text-white flex space-x-1"
                  @click.prevent="restartVend(vend.data.id)"
                >
                  <ArrowPathIcon class="w-4 h-4"></ArrowPathIcon>
                  <span>
                    Restart
                  </span>
                </Button>
            </div>

            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center">
                  <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> APK Logs </span>
                </div>
              </div>
            </div>
            <div class="sm:col-span-6 flex flex-col mt-3">
            <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5">
              <div class="inline-block min-w-full py-2 align-middle md:px-4 lg:px-6">
                <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                  <table class="min-w-full divide-y divide-gray-300">
                    <thead class="bg-gray-50">
                      <tr>
                        <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                          #
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                          Created At
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                          File
                        </th>
                      </tr>
                    </thead>
                    <tbody class="bg-white">
                      <tr v-for="(log, logIndex) in vend.logs" :key="log.id" :class="logIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                          {{ logIndex + 1 }}
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                          {{ log.created_at }}
                        </td>
                        <td class="whitespace-nowrap py-4 text-sm text-center">
                          <div class="flex w-0 space-x-2 items-center">
                            <PaperClipIcon class="h-5 w-5 flex-shrink-0 text-gray-400" aria-hidden="true" />
                            <a :href="log.full_url" target="_blank" class="font-medium text-indigo-600 hover:text-indigo-500">Download</a>
                          </div>
                        </td>
                      </tr>
                      <tr v-if="!vend.logs || !vend.logs.length">
                        <td colspan="3" class="whitespace-nowrap py-4 text-sm font-medium text-black text-center">
                          No records found
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
            </div>
            <!-- <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3" v-if="form.id">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center">
                  <span class="px-3 bg-white text-lg font-medium text-gray-900"> End of Month Inventory Snapshots </span>
                </div>
              </div>
            </div>
            <div class="sm:col-span-6 flex flex-col mt-3" v-if="form.id">
            <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5">
              <div class="inline-block min-w-full py-2 align-middle md:px-4 lg:px-6">
                <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                  <table class="min-w-full divide-y divide-gray-300">
                    <thead class="bg-gray-50">
                      <tr>
                        <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                          #
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                          End of Month
                          <span class="text-xs">
                            (every last day of the month 11.59:59pm)
                          </span>
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                          Action
                        </th>
                      </tr>
                    </thead>
                    <tbody class="bg-white">
                      <tr v-for="(vendSnapshot, vendSnapshotIndex) in vend.vendSnapshots" :key="vendSnapshot.id" :class="vendSnapshotIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                          {{ vendSnapshotIndex + 1 }}
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                          {{ vendSnapshot.endOfMonthNameYear }}
                        </td>
                        <td class="whitespace-nowrap py-4 text-sm text-center">
                          <Button
                            class="bg-gray-200 hover:bg-gray-300"
                            @click="downloadVendSnapshot(vendSnapshot.id)"
                          >
                            <ArrowDownTrayIcon class="w-4 h-4"></ArrowDownTrayIcon>
                          </Button>
                        </td>
                      </tr>
                      <tr v-if="!vend.vendSnapshots || !vend.vendSnapshots.length">
                        <td colspan="3" class="whitespace-nowrap py-4 text-sm font-medium text-black text-center">
                          No records found
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
            </div> -->

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
import { ArrowPathIcon, ArrowUturnDownIcon, ArrowUturnLeftIcon, CheckCircleIcon, PaperClipIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    countries: Object,
    vend: Object,
    type: String,
  })

const form = ref(
  useForm(getDefaultForm())
)

const countryOptions = ref([])

const permissions = usePage().props.auth.permissions

function getDefaultForm() {
  return {
    code: '',
    customer_code: '',
    customer_name: '',
    termination_date: '',
    binding: {
      begin_date: '',
      termination_date: '',
    },
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
  }
}

onMounted(() => {
  countryOptions.value = props.countries.data
  console.log(JSON.parse(JSON.stringify(props.vend)))
  form.value = props.vend ? useForm({
    ...props.vend,
    binding: {
      begin_date: props.vend.customer.begin_date,
      termination_date: props.vend.customer.termination_date,
    },
    contact: {
      ...JSON.parse(JSON.stringify(props.vend.customer.contact))
    },
    address: {
      ...props.vend.customer.delivery_address,
      country_id: props.vend && props.vend.customer && props.vend.customer.delivery_address ? countryOptions.value.find(country => country.id === props.vend.customer.delivery_address.country_id) : null,
    }
  }) : useForm(getDefaultForm())

})

function restartVend(vendID) {
  router.post('/vends/' + vendID + '/restart', {}, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    onSuccess: () => {
      emit('modalClose')
    }
  })
}

function submit() {
  form.value.clearErrors()

  form.value
    .post('/vends/' + form.value.id + '/update', {
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

function unbindCustomer(vendId) {
  form.value
      .post('/vends/' + vendId + '/unbind', {
        onSuccess: () => {
          emit('modalClose')
        },
        preserveState: true,
        replace: true,
      })
}

function downloadVendSnapshot(vendSnapshotId) {
    axios({
        method: 'get',
        url: '/vends/vend-snapshots/excel/' + vendSnapshotId,
        responseType: 'blob',
    }).then(response => {
        fileDownload(response.data, 'Vending_Channels_' + moment().format('YYMMDDhhmmss') +'.xlsx')
    }).catch(error => {
        console.log(error)
    }).finally(() => {
    })
}
</script>