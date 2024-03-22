<template>
  <Head title="VM Edit" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Editing Vending Machine
        {{ vend.code }}
      </h2>
    </template>

     <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
        <div class="shadow-sm ring-1 ring-black ring-opacity-5 p-5 mb-3">
          <form @submit.prevent="submit" id="submit">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-6 pb-5 mb-3">
            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-start">
                  <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> Vending Machine </span>
                </div>
              </div>
            </div>

            <div class="sm:col-span-6">
              <div
                  class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit"
                  :class="[vend.is_active ? 'bg-green-300' : 'bg-red-300']"
              >
                <span v-if="vend.is_active">
                  Active
                </span>
                <span v-if="!vend.is_active">
                  Not Active
                </span>
              </div>
            </div>

            <div class="sm:col-span-5" v-if="vend">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Vend ID#
              </label>
              <div class="mt-1">
                <input
                  type="text"
                  class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                  :value="vend ? vend.code : ''"
                  disabled
                />
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
            <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Is Factory?
              </label>
              <!-- {{ form.customer }} -->
              <MultiSelect
                v-model="form.is_testing"
                :options="booleanStrictOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors['customer.is_testing']">
                {{ form.errors['customer.is_testing'] }}
              </div>
            </div>
            <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Is Active? (Vending Machine)
              </label>
              <!-- {{ form.customer }} -->
              <MultiSelect
                v-model="form.is_active"
                :options="booleanStrictOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors['is_active']">
                {{ form.errors['is_active'] }}
              </div>
            </div>

            <div class="sm:col-span-6">
              <span class="flex space-x-1">
                <Button
                  type="button"
                  class="bg-green-500 hover:bg-green-600 text-white flex space-x-1"
                  v-if="permissions.includes('update vends')"
                  @click.prevent="saveVend(vend.id)"
                >
                  <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                  <span>
                    Save Vending Machine
                  </span>
                </Button>
                <Button
                    class="bg-red-500 hover:bg-red-600 text-white flex space-x-1"
                    @click.prevent="restartVend(vend.id)"
                  >
                    <ArrowPathIcon class="w-4 h-4"></ArrowPathIcon>
                    <span>
                      Restart
                    </span>
                </Button>
              </span>
            </div>
            </div>
            <div>
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
              <div class="sm:col-span-6">
                <div
                    class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit bg-green-300"
                    v-if="vend.customer && vend.customer.person_id"
                >
                    <div class="flex flex-col">
                      From CMS
                    </div>
                </div>
                <div
                    class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit bg-gray-300"
                    v-if="!vend.customer || !vend.customer.id"
                >
                    <div class="flex flex-col">
                      No Customer Binding
                    </div>
                </div>
              </div>
              <!-- {{ Boolean((form.customer.id && !form.customer.person_id) || !form.customer.id) }} <br>
              {{ Boolean(form.customer.id && !form.customer.person_id) }}
              {{ Boolean(!form.customer.id) }} -->
              <fieldset class="sm:col-span-6" v-if="!vend.customer">
                <legend class="sr-only">Plan</legend>
                <div class="space-y-5">
                  <div class="relative flex items-start">
                    <div class="flex h-6 items-center">
                      <input id="isExisting" aria-describedby="is-existing-description" name="isExisting" type="radio" v-model="isExisting" value="1" class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                    </div>
                    <div class="ml-3 text-sm leading-6">
                      <label for="is_existing" class="font-medium text-gray-900">Select Existing Customer</label>
                    </div>
                  </div>
                  <!-- <div class="relative flex items-start">
                    <div class="flex h-6 items-center">
                      <input id="isExisting" aria-describedby="is-new-description" name="isExisting" type="radio" v-model="isExisting" value="0" class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                    </div>
                    <div class="ml-3 text-sm leading-6">
                      <label for="is_new" class="font-medium text-gray-900">Create New Customer</label>
                    </div>
                  </div> -->
                </div>
              </fieldset>

              <div class="sm:col-span-6" v-if="!vend.customer && isExisting == 1">
                <div class="sm:col-span-6">
                  <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                    Customer
                  </label>
                  <MultiSelect
                    v-model="form.customer_id"
                    :options="adminCustomerOptions"
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

              <div class="sm:col-span-6" v-if="vend.customer && vend.customer.person_id">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Customer
                </label>
                <div class="mt-1">
                  <input
                    type="text"
                    class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                    :value="'#'+(vend.customer.id + 10000) + ' - ' + vend.customer_code + ' - ' + vend.customer_name"
                    disabled
                  />
                </div>
              </div>
              <div class="sm:col-span-6" v-if="vend.customer && !vend.customer.person_id">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Customer
                </label>
                <div class="mt-1">
                  <input
                    type="text"
                    class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                    :value="'#'+(vend.customer.id + 10000) + ' - ' + (vend.customer.code ? vend.customer.code : '')  + ' - ' + vend.customer_name"
                    disabled
                  />
                </div>
              </div>

              <!-- <div class="sm:col-span-6 grid grid-cols-1 gap-3 sm:grid-cols-6" v-if="(vend.customer.id && !vend.customer.person_id) || (!vend.customer.id && isExisting != 1)"> -->
                <!-- <div class="sm:col-span-2">
                  <FormInput v-model="form.customer.code" :error="form.errors['customer.code']" :disabled="form.customer.person_id">
                    Cust Code
                  </FormInput>
                </div>
                <div class="sm:col-span-3">
                  <FormInput v-model="form.customer.name" :error="form.errors['customer.name']" required="true" :disabled="form.customer.person_id">
                    Cust Name
                  </FormInput>
                </div> -->
                <div class="sm:col-span-6 text-blue-600 text-xs" v-if="vend.customer && vend.customer.person_id">
                  ** Customer Data only editable from CMS
                  <span>
                    <a class="text-blue-700" target="_blank" :href="'//admin.happyice.com.sg/person/' + vend.customer.person_id + '/edit'">
                      (Click Here)
                    </a>
                  </span>
                </div>
                <div class="sm:col-span-6 text-blue-600 text-xs" v-if="vend.customer && !vend.customer.person_id">
                  ** Edit customer data
                  <span>
                    <a class="text-blue-700" target="_blank" :href="'/customers/' + vend.customer.id + '/edit'">
                      (Click Here)
                    </a>
                  </span>
                </div>
                <!-- <div class="sm:col-span-2">
                  <DatePicker v-model="form.customer.begin_date" :error="form.errors['customer.begin_date']" @input="onDateFromChanged()"
                  v-if="permissions.includes('update vends')">
                    Begin Date
                  </DatePicker>
                </div> -->
              <!-- </div> -->
<!--
            <div class="sm:col-span-6 grid grid-cols-1 gap-3 sm:grid-cols-6" v-if="(form.customer.id && !form.customer.person_id) || (!form.customer.id && isExisting != 1)">
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
                <FormInput v-model="form.customer.contact.name" :error="form.errors['customer.contact.name']" :disabled="form.customer.person_id">
                  Name
                </FormInput>
              </div>
              <div class="sm:col-span-3">
                <FormInput v-model="form.customer.contact.email" :error="form.errors['customer.contact.email']" :disabled="form.customer.person_id">
                  Email
                </FormInput>
              </div>
              <div class="sm:col-span-2">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Phone Code
                </label>
                <MultiSelect
                  v-model="form.customer.contact.phone_country_id"
                  :options="countryOptions"
                  :disabled="form.customer.person_id"
                  trackBy="id"
                  valueProp="id"
                  label="phone_code"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors['customer.contact.phone_country_id']">
                  {{ form.errors['customer.contact.phone_country_id'] }}
                </div>
              </div>
              <div class="sm:col-span-4">
                <FormInput v-model="form.customer.contact.phone_num" required="true" :error="form.errors['customer.contact.phone_num']" :disabled="form.customer.person_id">
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
                <SearchAddressInput v-model="form.customer.address.postcode" @selected="onAddressSelected" required="true" :error="form.errors['customer.address.postcode']" :disabled="form.customer.person_id">
                  Postcode
                </SearchAddressInput>
              </div>
              <div class="sm:col-span-3">
                <FormInput v-model="form.customer.address.unit_num" required="true" :error="form.errors['customer.address.unit_num']">
                  Unit Num
                </FormInput>
              </div>
              <div class="sm:col-span-3">
                <FormInput v-model="form.customer.address.block_num" :error="form.errors['customer.address.block_num']" :disabled="form.customer.person_id">
                  Block Num
                </FormInput>
              </div>
              <div class="sm:col-span-3">
                <FormInput v-model="form.customer.address.building" :error="form.errors['customer.address.building']" :disabled="form.customer.person_id">
                  Building Name
                </FormInput>
              </div>
              <div class="sm:col-span-3">
                <FormInput v-model="form.customer.address.street_name" required="true" :error="form.errors['customer.address.street_name']" :disabled="form.customer.person_id">
                  Street Name
                </FormInput>
              </div>
              <div class="sm:col-span-3">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Country
                </label>
                <MultiSelect
                  v-model="form.customer.address.country_id"
                  :options="countryOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                  :disabled="form.customer.person_id"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors['customer.address.country_id']">
                  {{ form.errors['customer.address.country_id'] }}
                </div>
              </div>
              <div class="sm:col-span-3 hidden">
                <FormInput v-model="form.customer.address.latitude">
                  Latitude
                </FormInput>
              </div>
              <div class="sm:col-span-3 hidden">
                <FormInput v-model="form.customer.address.longitude">
                  Longitude
                </FormInput>
              </div>
            </div> -->
<!--
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

            <div class="sm:col-span-6">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Operator
                <span class="text-red-500">
                   *
                </span>
              </label>
              <MultiSelect
                v-model="form.customer.operator_id"
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
            </div> -->

            <div class="sm:col-span-6 pt-3">
                <span class="flex justify-between">
                  <span class="flex space-x-1">
                    <Button
                      type="button"
                      class="bg-green-500 hover:bg-green-600 text-white flex space-x-1"
                      v-if="permissions.includes('update vends') && !vend.customer"
                      @click.prevent="saveCustomer(form.customer_id)"
                    >
                      <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                      <span>
                        Save Customer
                      </span>
                    </Button>
                    <Link :href="'/settings'">
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
                  <Button
                    type="button"
                    class="bg-red-500 hover:bg-red-600 text-white flex space-x-1"
                    v-if="vend && vend.customer && permissions.includes('update vends')"
                    @click.prevent="unbindCustomer(form.id)"
                  >
                    <XCircleIcon class="w-4 h-4"></XCircleIcon>
                    <span>
                      Unbind VM & Customer
                    </span>
                  </Button>
                </span>
              </div>
          </div>
          </div>

          <div class="grid grid-cols-1 gap-3 sm:grid-cols-6 pb-5 mb-3">
            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-start">
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
import { ArrowPathIcon, ArrowUturnDownIcon, ArrowUturnLeftIcon, CheckCircleIcon, PaperClipIcon, XCircleIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { fromPairs } from 'lodash';

const props = defineProps({
    adminCustomerOptions: Object,
    countries: Object,
    operatorOptions: Object,
    vend: Object,
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

const countryOptions = ref([])
const isExisting = ref(1)
const operatorOptions = ref([])
const permissions = usePage().props.auth.permissions

function getDefaultForm() {
  return {
    id: '',
    begin_date: '',
    code: '',
    customer_id: '',
    customer: {
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
    },
    termination_date: '',
    is_testing: '',
    is_active: '',
  }
}

onMounted(() => {
  // console.log(JSON.parse(JSON.stringify(props.vend)))
  countryOptions.value = props.countries.data
  operatorOptions.value = props.operatorOptions.data
  form.value = props.vend ? useForm({
    ...props.vend,
    is_active: props.vend.is_active == 1 ? booleanStrictOptions.value.find(option => option.id === 'true') : booleanStrictOptions.value.find(option => option.id === 'false'),
    is_testing: props.vend.is_testing == 1 ? booleanStrictOptions.value.find(option => option.id === 'true') : booleanStrictOptions.value.find(option => option.id === 'false'),
    customer: {
      ...JSON.parse(JSON.stringify(props.vend.customer)),
      code: props.vend.customer && props.vend.customer.person_id ? props.vend.customer.virtual_customer_code + ' (' + props.vend.customer.virtual_customer_prefix + ')' : (props.vend.customer ? props.vend.customer.code : null),
      operator_id: props.vend.customer ? props.vend.customer.operator_id ? operatorOptions.value.find(operator => operator.id === props.vend.customer.operator_id) : null : null,
      contact: props.vend.customer ? {
        ...JSON.parse(JSON.stringify(props.vend.customer.contact))
      } : {
        name: '',
        email: '',
        phone_country_id: '',
        phone_num: '',
      },
      address: props.vend.customer ? {
        ...props.vend.customer.delivery_address,
        country_id: props.vend && props.vend.customer && props.vend.customer.delivery_address ? countryOptions.value.find(country => country.id === props.vend.customer.delivery_address.country_id) : null,
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
    },
  }) : useForm(getDefaultForm())
  adminCustomerOptions.value = props.adminCustomerOptions.data.map(customer => ({
    id: customer.id,
    full_name: customer.person_id ? customer.virtual_customer_code + ' (' + customer.virtual_customer_prefix + ') - ' + customer.name  : customer.code + ' - ' + customer.name,
  }))

  // console.log(JSON.parse(JSON.stringify(form.value)))
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

function saveCustomer(customerID) {
  form.value.clearErrors()

  form.value
    .transform((data) => ({
      ...data,
      customer: {
        ...data.customer,
        begin_date: data.customer.begin_date && data.customer.begin_date != 'Invalid date' ? data.customer.begin_date : null,
        termination_date: data.customer.termination_date && data.customer.termination_date != 'Invalid date' ? data.customer.termination_date : null,
        operator_id: data.customer.operator_id ? data.customer.operator_id.id : null,
        contact: {
          ...data.customer.contact,
          phone_country_id: data.customer.contact.phone_country_id ? data.customer.contact.phone_country_id.id : null,
        },
        address: {
          ...data.customer.address,
          country_id: data.customer.address.country_id ? data.customer.address.country_id.id : null,
        },
      },
      customer_id: data.customer_id ? data.customer_id.id : null,
      is_existing: isExisting.value,
    }))
    .post('/customers/' + customerID + '/update', {
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
      is_active: data.is_active.id,
    }))
    .post('/vends/' + vendID + '/update', {
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
    .post('/vends/' + form.value.id + '/update', {
    onSuccess: () => {
      emit('modalClose')
    },
    preserveState: true,
    replace: true,
  })
}

function onAddressSelected(address) {
  form.value.customer.address = {
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
      .post('/vends/' + vendID + '/unbind-customer/settings', {
        onSuccess: () => {
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