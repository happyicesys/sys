<template>
  <Head title="Create Vouchers" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Batch Create
        {{ isUnique ? 'Different Codes' : 'Same Codes' }}
        Vouchers
      </h2>
    </template>

     <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
        <div class="shadow-sm ring-1 ring-black ring-opacity-5 p-5 mb-3">
          <form @submit.prevent="submit" id="submit">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-6 pb-5 mb-3">
            <div class="sm:col-span-3" v-if="isUnique == false">
              <FormInput v-model="form.code" required="true" placeholderStr="Alphanumeric" :error="form.errors['code']">
                Voucher Code
              </FormInput>
            </div>
            <div class="sm:col-span-2">
              <FormInput v-model="form.qty" required="true" placeholderStr="Numbers only" :error="form.errors['qty']">
                Max Claimable Qty
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
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.type">
                  {{ form.errors.type }}
                </div>
            </div>

            <div class="sm:col-span-2" v-if="form.type && form.type?.id != 'item'">
              <FormInput v-model="form.value" required="true" placeholderStr="Numbers only" :error="form.errors['value']">
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
              <FormInput v-model="form.min_value" placeholderStr="Numbers only" :error="form.errors['min_value']">
                Mininum Basket Value ($)
              </FormInput>
            </div>

            <div class="sm:col-span-2" v-if="form.type && form.type?.id != 'item'">
              <FormInput v-model="form.max_promo_value" placeholderStr="Numbers only" :error="form.errors['max_promo_value']">
                Maximum Promo Value ($)
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
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.dcvend_member_type">
                  {{ form.errors.dcvend_member_type }}
                </div>
            </div>

            <div class="sm:col-span-2" v-if="form.is_dcvend && form.is_dcvend.id == 'true'">
              <FormInput v-model="form.dcvend_qty_per_member" placeholderStr="Numbers only" :error="form.errors['dcvend_qty_per_member']" required="true">
                Qty
              </FormInput>
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
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.valid_unit">
                  {{ form.errors.valid_unit }}
                </div>
            </div>

            <div class="sm:col-span-2" v-if="form.is_dcvend && form.is_dcvend.id == 'true'">
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
                v-model="form.product"
                :options="productOptions"
                trackBy="id"
                valueProp="id"
                label="full_name"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.product_json">
                {{ form.errors.product_json }}
              </div>
            </span>
            <div class="sm:col-span-1 flex justify-start items-center">
              <Button
                type="button"
                @click="addProduct(form.product)"
                class="bg-green-500 hover:bg-green-600 text-white h-fit"
                :class="[
                  !form.products ?
                  'opacity-50 cursor-not-allowed' : ''
                  ]"
                :disabled="!form.products"
              >
                <PlusCircleIcon class="w-4 h-4"></PlusCircleIcon>
                <span>
                  Add
                </span>
              </Button>
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
                            ID
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Name
                          </th>
                          <th></th>
                        </tr>
                      </thead>
                      <tbody class="bg-white">
                        <tr v-for="(product, productIndex) in products" :key="productIndex" :class="productIndex % 2 === 0 ? undefined : 'bg-gray-50'">
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
                                !form.products ?
                                'opacity-50 cursor-not-allowed' : ''
                                ]"
                              :disabled="!form.products"
                            >
                              <BackspaceIcon class="w-4 h-4"></BackspaceIcon>
                            </Button>
                          </td>
                        </tr>
                        <tr v-if="!products.length">
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
                    Create
                  </span>
                </Button>
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
import FormTextarea from '@/Components/FormTextarea.vue';
import moment from 'moment';
import MultiSelect from '@/Components/MultiSelect.vue';
import { BackspaceIcon, CheckCircleIcon, PlusCircleIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";

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
    voucherModeMappings: [Array, Object],
    voucherPlatformMappings: [Array, Object],
  })

const booleanOptions = ref([])
const dcvendMemberTypeMappings = ref(props.dcvendMemberTypeMappings)
const operatorOptions = ref([])
const productOptions = ref([])
const products = ref([])
const toast = useToast()
const typeOptions = ref([])
const form = ref(
  useForm(getDefaultForm())
)
const validDurationMappings = ref(props.validDurationMappings)
const validUnitMappings = ref(props.validUnitMappings)
const voucher = ref([])
const voucherModeMappings = ref(props.voucherModeMappings)
const voucherPlatformMappings = ref(props.voucherPlatformMappings)

onMounted(() => {
  booleanOptions.value = [
      {id: 'true', value: 'Yes'},
      {id: 'false', value: 'No'},
  ]
  operatorOptions.value = props.operatorOptions.data
  productOptions.value = props.productOptions.data
  typeOptions.value = props.typeOptions

  form.value = useForm(getDefaultForm())
  form.value.is_dcvend = booleanOptions.value[1]
})

function addProduct(product) {
  if (!product || !product.id) {
    return
  }

  const existingProduct = products.value.find((p) => p.id === product.id)
  if (existingProduct) {
    toast.error("Product already added", {
      timeout: 3000
    });
    return
  }

  products.value.push({
    id: product.id,
    code: product.code,
    name: product.name,
  })

  form.value.product = null
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
    operator_id: '',
    products: [],
    qty: '',
    type: '',
    valid_duration: '',
    valid_unit: '',
    value: '',
  }
}

function removeProduct(product) {
  const index = products.value.findIndex((p) => p.id === product.id)
  if (index !== -1) {
    products.value.splice(index, 1)
  }
}

function submit() {
  form.value.clearErrors()

  form.value
    .transform((data) => ({
      ...data,
      is_batch_code: props.isUnique ? true : false,
      type: data.type?.id,
      operator_id: data.operator_id?.id,
      products: products.value?.map((item) => item.id),
      is_dcvend: data.is_dcvend?.id,
      is_recurring: data.is_recurring?.id,
      dcvend_member_type: data.dcvend_member_type?.id,
      valid_unit: data.valid_unit?.id,
      valid_duration: data.valid_duration?.id,
    }))
    .post('/vouchers/store', {
    onSuccess: () => {
      toast.success("Successfully created", {
        timeout: 3000
      });
    },
    preserveState: true,
    replace: true,
  })
}

</script>