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
            <div class="sm:col-span-2" v-if="voucher.is_batch_code == false" >
              <FormInput v-model="form.qty" required="true" placeholderStr="Numbers only" :error="form.errors['qty']" disabled="true">
                Qty
              </FormInput>
            </div>
            <div class="sm:col-span-2" v-if="voucher.is_batch_code == false" >
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
                  v-model="form.operators"
                  :options="operatorOptions"
                  trackBy="id"
                  valueProp="id"
                  label="full_name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                  mode="tags"
                  disabled="disabled"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.operators">
                  {{ form.errors.operators }}
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
              </FormInput>
            </div>

            <div class="sm:col-span-2" v-if="form.type && form.type?.id != 'item'">
              <FormInput v-model="form.max_promo_value" placeholderStr="Numbers only" :error="form.errors['max_promo_value']" disabled="disabled">
                Maximum Promo Value ($)
              </FormInput>
            </div>

            <span class="sm:col-span-6">
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
                mode="tags"
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
import { CheckCircleIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";

const props = defineProps({
    isUnique: Boolean,
    operatorOptions: Object,
    productOptions: Object,
    type: String,
    typeOptions: [Array, Object],
    voucher: Object,
  })

const operatorOptions = ref([])
const productOptions = ref([])
const toast = useToast()
const typeOptions = ref([])
const form = ref(
  useForm(getDefaultForm())
)
const voucher = ref([])

onMounted(() => {
  operatorOptions.value = props.operatorOptions.data
  productOptions.value = props.productOptions.data
  typeOptions.value = props.typeOptions
  voucher.value = props.voucher.data

  form.value = voucher.value ? useForm(
    {
      ...voucher.value,
      date_from: moment(voucher.value.date_from).format('YYYY-MM-DD'),
      date_to: moment(voucher.value.date_to).format('YYYY-MM-DD'),
      operator_id: voucher.value ? operatorOptions.value.find(operator => operator.id == voucher.value.operator_id) : [],
      products: voucher.value ? productOptions.value.filter((item) => {
        return voucher.value.product_json.includes(item.id)
      }) : [],
      type: voucher.value ? { id: voucher.value.type, label: typeOptions.value[voucher.value.type] } : [],
    }
  ) : useForm(getDefaultForm())
})

function getDefaultForm() {
  return {
    code: '',
    date_from: moment().format('YYYY-MM-DD'),
    date_to: '',
    desc: '',
    max_promo_value: '',
    min_value: '',
    name: '',
    operators: [],
    products: [],
    qty: '',
    type: '',
    value: '',
  }
}

function submit() {
  form.value.clearErrors()

  form.value
    .transform((data) => ({
      ...data,
      is_batch_code: props.isUnique ? true : false,
      type: data.type?.id,
      operators: data.operators?.map((item) => item.id),
      products: data.products?.map((item) => item.id),
    }))
    .post('/vouchers/store', {
    onSuccess: () => {
      toast.success("Successfully created, please continue to edit the settings", {
        timeout: 3000
      });
    },
    preserveState: true,
    replace: true,
  })
}

</script>