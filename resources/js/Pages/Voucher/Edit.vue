<template>
  <Head title="Edit Voucher" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
       Edit Voucher
      </h2>
    </template>

     <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
        <div class="shadow-sm ring-1 ring-black ring-opacity-5 p-5 mb-3">
          <form @submit.prevent="submit" id="submit">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-6 pb-5 mb-2">
              <div class="sm:col-span-6 flex space-x-1 mb-2">
                <div
                    class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit"
                    :class="[voucher.is_active ? 'bg-green-300' : 'bg-red-300']"
                >
                  <span v-if="voucher.is_active">
                    Active
                  </span>
                  <span v-if="!voucher.is_active">
                    Expired
                  </span>
                </div>
              </div>
              <div class="sm:col-span-6 flex space-x-1 mb-2">
                <div
                    class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit"
                    :class="[voucher.is_batch_code ? 'bg-yellow-300' : 'bg-green-300']"
                >
                  <span v-if="voucher.is_batch_code">
                    Unique Voucher Code
                  </span>
                  <span v-if="!voucher.is_batch_code">
                    Same Voucher Code
                  </span>
                </div>
              </div>
            <div class="sm:col-span-2" v-if="voucher.is_batch_code == false">
              <FormInput v-model="form.code" required="true" placeholderStr="Alphanumeric" :error="form.errors['code']" disabled="true">
                Voucher Code
              </FormInput>
            </div>
            <div class="sm:col-span-2">
              <FormInput v-model="form.qty" required="true" placeholderStr="Numbers only" :error="form.errors['qty']" disabled="true">
                Qty
              </FormInput>
            </div>
            <div class="sm:col-span-2">
              <FormInput v-model="form.used_qty" placeholderStr="Numbers only" :error="form.errors['qty']" disabled="true">
                Used Qty
              </FormInput>
            </div>
            <div class="sm:col-span-5">
              <FormInput v-model="form.name" required="true" :error="form.errors['name']">
                Name
              </FormInput>
            </div>

            <div class="sm:col-span-5">
              <FormTextarea v-model="form.desc" rows="3" :error="form.errors['desc']">
                Desc
              </FormTextarea>
            </div>

            <div class="sm:col-span-4">
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
                  disabled="disabled"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.operator_id">
                  {{ form.errors.operator_id }}
                </div>
            </div>

            <div class="sm:col-span-3">
              <DatePicker v-model="form.date_from" @input="onDateFromChanged()">
                Begin Date
                <span class="text-red-500">*</span>
              </DatePicker>
              <div class="text-sm text-red-600" v-if="form.errors.date_from">
                {{ form.errors.date_from }}
              </div>
            </div>
            <div class="sm:col-span-3">
              <DatePicker v-model="form.date_to" :error="form.errors.date_to" :minDate="form.date_from">
                End Date
                <span class="text-red-500">*</span>
              </DatePicker>
              <div class="text-sm text-red-600" v-if="form.errors.date_to">
                {{ form.errors.date_to }}
              </div>
            </div>

            <div class="sm:col-span-5">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Type
                  <span class="text-red-500">
                    *
                  </span>
                </label>
                <MultiSelect
                  v-model="form.type"
                  :options="typeOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                  disabled="disabled"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.type">
                  {{ form.errors.type }}
                </div>
            </div>

            <div class="sm:col-span-2" v-if="form.type && form.type?.id != 'item'">
              <FormInput v-model="form.value" required="true" placeholderStr="Numbers only" :error="form.errors['value']" disabled="disabled">
                Value
                  <span v-if="form.type.id == 'percent'">
                    (%)
                  </span>
                  <span v-if="form.type.id == 'amount'">
                    ($)
                  </span>
              </FormInput>
            </div>

            <div class="sm:col-span-2" v-if="form.type">
              <FormInput v-model="form.min_value" placeholderStr="Numbers only" :error="form.errors['min_value']" disabled="disabled">
                Mininum Basket Value ($)
                <ExclamationCircleIcon class="min-w-5 w-5 h-5 self-center pl-1 text-sky-500" v-tooltip="{ content: 'If left empty, means no min is enforced', html: true }"></ExclamationCircleIcon>
              </FormInput>
            </div>

            <div class="sm:col-span-2" v-if="form.type && form.type?.id != 'item'">
              <FormInput v-model="form.max_promo_value" placeholderStr="Numbers only" :error="form.errors['max_promo_value']" disabled="disabled">
                Maximum Promo Value ($)
                <ExclamationCircleIcon class="min-w-5 w-5 h-5 self-center pl-1 text-sky-500" v-tooltip="{ content: 'Applicable for percent discount, when left empty, means no limit on maximum', html: true }"></ExclamationCircleIcon>
              </FormInput>
            </div>

            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-start">
                  <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> Audiences </span>
                </div>
              </div>
            </div>

            <div class="sm:col-span-3">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Is DCVend?
                  <span class="text-red-500">
                    *
                  </span>
                </label>
                <MultiSelect
                  v-model="form.is_dcvend"
                  :options="booleanOptions"
                  trackBy="id"
                  valueProp="id"
                  label="value"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                  disabled="disabled"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.is_dcvend">
                  {{ form.errors.is_dcvend }}
                </div>
            </div>

            <div class="sm:col-span-3" v-if="form.is_dcvend && form.is_dcvend.id == 'true'">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  DCVend Member Type
                  <span class="text-red-500">
                    *
                  </span>
                </label>
                <MultiSelect
                  v-model="form.dcvend_member_type"
                  :options="dcvendMemberTypeMappings"
                  trackBy="id"
                  valueProp="id"
                  label="value"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                  disabled="disabled"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.dcvend_member_type">
                  {{ form.errors.dcvend_member_type }}
                </div>
            </div>

            <div class="sm:col-span-2" v-if="(form.is_dcvend && form.is_dcvend.id == 'true') && (form.dcvend_member_type && form.dcvend_member_type.id == 4)">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Refresh Qty After Renew Plan
                  <span class="text-red-500">
                    *
                  </span>
                </label>
                <MultiSelect
                  v-model="form.is_recurring"
                  :options="booleanOptions"
                  trackBy="id"
                  valueProp="id"
                  label="value"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                  disabled="disabled"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.is_recurring">
                  {{ form.errors.is_recurring }}
                </div>
            </div>

            <div class="sm:col-span-2" v-if="form.is_dcvend && form.is_dcvend.id == 'true'">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Voucher Valid Duration Unit
                  <span class="text-red-500">
                    *
                  </span>
                </label>
                <MultiSelect
                  v-model="form.valid_unit"
                  :options="validUnitMappings"
                  trackBy="id"
                  valueProp="id"
                  label="value"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                  disabled="disabled"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.valid_unit">
                  {{ form.errors.valid_unit }}
                </div>
            </div>

            <div class="sm:col-span-2" v-if="form.is_dcvend && form.is_dcvend.id == 'true' && (form.valid_unit && form.valid_unit.id != 'plan')">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Voucher Valid Duration
                  <span class="text-red-500">
                    *
                  </span>
                </label>
                <MultiSelect
                  v-model="form.valid_duration"
                  :options="validDurationMappings"
                  trackBy="id"
                  valueProp="id"
                  label="value"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                  disabled="disabled"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.valid_duration">
                  {{ form.errors.valid_duration }}
                </div>
            </div>



            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-start">
                  <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> Product(s) </span>
                </div>
              </div>
            </div>

            <span class="sm:col-span-5">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Product
                <span class="text-red-500">
                  *
                </span>
              </label>
              <MultiSelect
                v-model="form.products"
                :options="productOptions"
                trackBy="id"
                valueProp="id"
                label="full_name"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
                disabled="disabled"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.products">
                {{ form.errors.products }}
              </div>
            </span>
            <div class="sm:col-span-1 flex justify-start items-center" v-if="form.id">
              <Button
                type="button"
                @click="addProduct"
                class="bg-green-500 hover:bg-green-600 text-white h-fit"
                :class="[
                  !form.products || true ?
                  'opacity-50 cursor-not-allowed' : ''
                  ]"
                :disabled="!form.products || true"
              >
                <PlusCircleIcon class="w-4 h-4"></PlusCircleIcon>
                <span>
                  Add
                </span>
              </Button>
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
                            ID
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Name
                          </th>
                          <th></th>
                        </tr>
                      </thead>
                      <tbody class="bg-white">
                        <tr v-for="(product, productIndex) in form.products" :key="productIndex" :class="productIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                            {{ productIndex + 1 }}
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                            {{ product.code }}
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                            {{ product.name }}
                          </td>
                          <td class="whitespace-nowrap py-4 text-sm text-center">
                            <Button
                              class="bg-red-400 hover:bg-red-500 text-white"
                              @click.prevent="removeProduct(product)"
                              :class="[
                                !form.products || true ?
                                'opacity-50 cursor-not-allowed' : ''
                                ]"
                              :disabled="!form.products || true"
                            >
                              <BackspaceIcon class="w-4 h-4"></BackspaceIcon>
                            </Button>
                          </td>
                        </tr>
                        <tr v-if="!form.products.length">
                          <td colspan="4" class="whitespace-nowrap py-4 text-sm font-medium text-black-600 text-center">
                            No Results Found
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>

            <div class="sm:col-span-6 py-4">
              <span class="flex space-x-1">
                <Button
                  type="submit"
                  class="bg-green-500 hover:bg-green-600 text-white flex space-x-1"
                >
                  <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                  <span>
                    Save
                  </span>
                </Button>
                <Button
                  class="bg-red-500 hover:bg-red-600 text-white flex space-x-1"
                  @click.prevent="deleteVoucher(voucher.id)"
                >
                  <XCircleIcon class="w-4 h-4"></XCircleIcon>
                  <span>
                    Delete
                  </span>
                </Button>
              </span>
            </div>

            <div class="flex flex-col sm:col-span-5 mt-4" v-if="voucherItems.length">
              <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5">
                <div class="inline-block min-w-full py-2 align-middle md:px-4 lg:px-6">
                  <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                    <table class="table-fixed min-w-full divide-y divide-gray-300">
                      <thead class="bg-gray-50">
                        <tr>
                          <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                            <Button type="button" class="rounded-md bg-white px-2 py-1 text-xs font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-400 hover:bg-gray-100"
                              @click.prevent="onExportExcelClicked()">
                              <div class="flex space-x-1 items-center">
                                <div>
                                  <ArrowDownTrayIcon v-if="!loading" class="h-4 w-4" aria-hidden="true"/>
                                  <svg v-if="loading" aria-hidden="true" class="mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                                      <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                                      <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                                  </svg>
                                </div>
                                <span>
                                    Export Excel
                                </span>
                              </div>
                            </Button>
                          </th>
                          <th scope="col" class="w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                            Code
                          </th>
                          <th scope="col" class="w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                            Status
                          </th>
                          <th scope="col" class="w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                            Redeemed At
                          </th>
                        </tr>
                      </thead>
                      <tbody class="bg-white">
                        <tr v-for="(voucherItem, voucherItemIndex) in voucherItems" :key="voucherItem.id" :class="voucherItemIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold sm:pl-6 text-center text-gray-900">
                            {{ voucherItemIndex + 1 }}
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold sm:pl-6 text-center text-gray-900">
                            {{ voucherItem.code }}
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold sm:pl-6 text-center text-gray-900">
                            <div
                                class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit"
                                :class="[voucherItem.is_active ? (voucherItem.is_redeemed ? 'bg-yellow-300' : 'bg-green-300') : 'bg-red-300']"
                            >
                              <span v-if="voucherItem.is_active && !voucherItem.is_redeemed">
                                Active
                              </span>
                              <span v-else-if="voucherItem.is_active && voucherItem.is_redeemed">
                                Redeemed
                              </span>
                              <span v-else>
                                Expired
                              </span>
                            </div>
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold sm:pl-6 text-center text-gray-900">
                            {{ voucherItem.redeemed_at_formatted }}
                          </td>
                        </tr>
                        <tr v-if="!voucherItems || !voucherItems.length">
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-xs font-normal sm:pl-6 text-center text-gray-900" colspan="6"> No Results Found </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
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
import FormTextarea from '@/Components/FormTextarea.vue';
import moment from 'moment';
import MultiSelect from '@/Components/MultiSelect.vue';
import { ArrowDownTrayIcon, BackspaceIcon, CheckCircleIcon, ExclamationCircleIcon, XCircleIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";
import { Dropdown, Tooltip, Menu, vTooltip } from 'floating-vue';

const props = defineProps({
    dcvendMemberTypeMappings: [Array, Object],
    isUnique: Boolean,
    operatorOptions: Object,
    productOptions: Object,
    type: String,
    typeOptions: [Array, Object],
    validDurationMappings: [Array, Object],
    validUnitMappings: [Array, Object],
    voucher: Object,
  })

const booleanOptions = ref([])
const dcvendMemberTypeMappings = ref(props.dcvendMemberTypeMappings)
const loading = ref(false)
const operatorOptions = ref([])
const productOptions = ref([])
const toast = useToast()
const typeOptions = ref([])
const form = ref(
  useForm(getDefaultForm())
)
const validDurationMappings = ref(props.validDurationMappings)
const validUnitMappings = ref(props.validUnitMappings)
const voucher = ref([])
const voucherItems = ref([])

onMounted(() => {
  booleanOptions.value = [
      {id: 'true', value: 'Yes'},
      {id: 'false', value: 'No'},
  ]
  operatorOptions.value = props.operatorOptions.data
  productOptions.value = props.productOptions.data
  typeOptions.value = props.typeOptions
  voucher.value = props.voucher.data
  voucherItems.value = props.voucher?.data.voucherItems

  form.value = voucher.value ? useForm(
    {
      ...voucher.value,
      date_from: moment(voucher.value.date_from).format('YYYY-MM-DD'),
      date_to: moment(voucher.value.date_to).format('YYYY-MM-DD'),
      dcvend_member_type: voucher.value.dcvend_member_type ? { id: voucher.value.dcvend_member_type, value: dcvendMemberTypeMappings.value[voucher.value.dcvend_member_type] } : [],
      is_dcvend: voucher.value.is_dcvend ? { id: 'true', value: 'Yes' } : { id: 'false', value: 'No' },
      is_recurring: voucher.value.is_recurring ? { id: 'true', value: 'Yes' } : { id: 'false', value: 'No' },
      valid_duration: voucher.value.valid_duration ? { id: voucher.value.valid_duration, value: validDurationMappings.value[voucher.value.valid_duration] } : [],
      valid_unit: voucher.value.valid_unit ? { id: voucher.value.valid_unit, value: validUnitMappings.value[voucher.value.valid_unit] } : [],
      operator_id: voucher.value ? operatorOptions.value.find(operator => operator.id == voucher.value.operator_id) : [],
      products: voucher.value && voucher.value.product_json
        ? voucher.value.product_json.map((id) =>
            productOptions.value.find((item) => item.id === id)
          ).filter(Boolean)
        : [],
      type: voucher.value ? { id: voucher.value.type, label: typeOptions.value[voucher.value.type] } : [],
    }
  ) : useForm(getDefaultForm())
})

function deleteVoucher(id) {
  if (confirm('Are you sure you want to delete this voucher, and its binding?')) {
    form.value
      .delete('/vouchers/' + id, {
        onSuccess: () => {
          toast.success("Successfully deleted", {
            timeout: 3000
          });
          router.visit('/vouchers')
        },
        preserveState: true,
        replace: true,
      })
  }
}

function onExportExcelClicked() {
    loading.value = true
    axios({
        method: 'get',
        url: '/vouchers/excel/codes',
        params: {
          id: voucher.value.id,
        },
        responseType: 'blob',
    }).then(response => {
        fileDownload(response.data, 'Voucher_Codes_' + moment().format('YYMMDDhhmmss') +'.xlsx')
    }).catch(error => {
        console.log(error)
    }).finally(() => {
        loading.value = false
    })
}

function getDefaultForm() {
  return {
    code: '',
    date_from: moment().format('YYYY-MM-DD'),
    date_to: '',
    dcvend_member_type: '',
    dcvend_qty_per_member: '',
    desc: '',
    is_dcvend: '',
    is_recurring: '',
    max_promo_value: '',
    min_value: '',
    name: '',
    operators: [],
    operator_id: '',
    products: [],
    qty: '',
    type: '',
    valid_duration: '',
    valid_unit: '',
    value: '',
  }
}

function submit() {
  form.value.clearErrors()

  form.value
    .transform((data) => ({
      ...data,
      is_batch_code: props.is_batch_code,
      type: data.type?.id,
      operators: data.operators?.map((item) => item.id),
      products: data.products?.map((item) => item.id),
      is_dcvend: data.is_dcvend?.id,
      dcvend_member_type: data.dcvend_member_type?.id,
      valid_duration: data.valid_duration?.id,
      valid_unit: data.valid_unit?.id,
    }))
    .post('/vouchers/' + form.value.id + '/update', {
    onSuccess: () => {
      toast.success("Successfully updated", {
        timeout: 3000
      });
    },
    preserveState: true,
    replace: true,
  })
}

</script>