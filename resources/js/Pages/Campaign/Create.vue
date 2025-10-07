<template>

  <Head title="Campaign" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Create New Campaign
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
        <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll p-5">
          <form @submit.prevent="submit" id="submit">
            <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">
              <div class="sm:col-span-3">
                <FormInput v-model="form.name" :error="form.errors.name" required="true">
                  Name
                </FormInput>
              </div>
              <div class="sm:col-span-3">
                <label for="operator" class="flex justify-start text-sm font-medium text-gray-700">
                  Operator
                </label>
                <MultiSelect
                  v-model="form.operator"
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
                <label class="flex justify-start text-sm font-medium text-gray-700">Promotion Type</label>
                <MultiSelect
                  v-model="form.promo_type"
                  :options="promoTypeOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.promo_type">
                  {{ form.errors.promo_type }}
                </div>
              </div>
              <div class="sm:col-span-3">
                <FormInput v-model="form.bundle_qty" :error="form.errors.bundle_qty" inputType="number" placeholderStr="Bundle quantity">
                  Bundle Quantity
                </FormInput>
              </div>
              <div class="sm:col-span-6">
                <FormInput v-model="form.slug" :error="form.errors.slug">
                  Slug
                </FormInput>
              </div>
              <div class="sm:col-span-6">
                <FormTextarea v-model="form.description" :error="form.errors.description" rows="3">
                  Description
                </FormTextarea>
              </div>
              <div class="sm:col-span-3">
                <FormInput v-model="form.value" :error="form.errors.value" inputType="number" placeholderStr="Discount value">
                  Value
                </FormInput>
              </div>
              <div class="sm:col-span-3">
                <label class="flex justify-start text-sm font-medium text-gray-700">Labels X</label>
                <MultiSelect
                  v-model="form.labels_x"
                  :options="tagOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  mode="tags"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="labelsXError">
                  {{ labelsXError }}
                </div>
              </div>
              <div class="sm:col-span-3">
                <label class="flex justify-start text-sm font-medium text-gray-700">Labels Y</label>
                <MultiSelect
                  v-model="form.labels_y"
                  :options="tagOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  mode="tags"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="labelsYError">
                  {{ labelsYError }}
                </div>
              </div>
              <div class="sm:col-span-3">
                <DatePicker v-model="form.start_at" :error="form.errors.start_at">
                  Start At
                </DatePicker>
              </div>
              <div class="sm:col-span-3">
                <DatePicker v-model="form.end_at" :error="form.errors.end_at" :minDate="form.start_at">
                  End At
                </DatePicker>
              </div>
              <div class="sm:col-span-6">
                <FormInput v-model="form.remarks" :error="form.errors.remarks">
                  Remarks
                </FormInput>
              </div>

              <div class="sm:col-span-6">
                <div class="flex space-x-1 mt-5 justify-end">
                  <Link :href="'/campaigns'">
                    <Button
                      type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1"
                    >
                      <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
                      <span>
                        Back
                      </span>
                    </Button>
                  </Link>
                  <Button type="submit" class="bg-green-500 hover:bg-green-600 text-white flex space-x-1">
                    <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                    <span>
                      Save
                    </span>
                  </Button>
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
import FormInput from '@/Components/FormInput.vue';
import FormTextarea from '@/Components/FormTextarea.vue';
import DatePicker from '@/Components/DatePicker.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { ArrowUturnLeftIcon, CheckCircleIcon } from '@heroicons/vue/20/solid';
import { computed, ref, onMounted } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
  operatorOptions: Object,
  tagOptions: Object,
  promoTypeOptions: Array,
})

const authUser = computed(() => usePage().props.auth.user)
const operatorOptions = ref([])
const tagOptions = ref([])
const promoTypeOptions = ref([])
const form = ref(useForm(getDefaultForm()))

const labelsXError = computed(() => form.value.errors.labels_x ?? form.value.errors['labels_x.0'] ?? null)
const labelsYError = computed(() => form.value.errors.labels_y ?? form.value.errors['labels_y.0'] ?? null)

onMounted(() => {
  operatorOptions.value = mapOperatorOptions(props.operatorOptions)
  promoTypeOptions.value = mapPromoTypeOptions(props.promoTypeOptions)
  tagOptions.value = mapTagOptions(props.tagOptions)
  form.value = useForm(getDefaultForm())
})

function mapOperatorOptions(optionsResource) {
  const options = optionsResource?.data ?? []

  return options.map((data) => ({
    id: data.id,
    full_name: data.full_name,
  }))
}

function mapPromoTypeOptions(options) {
  if (!Array.isArray(options)) {
    return []
  }

  return options.map((option) => ({
    id: option.id,
    name: option.name,
  }))
}

function mapTagOptions(optionsResource) {
  const options = optionsResource?.data ?? []

  return options.map((data) => ({
    id: data.id,
    name: data.name ?? data.slug ?? data.desc ?? `Tag ${data.id}`,
  }))
}

function getDefaultOperator() {
  if (!operatorOptions.value.length) {
    return null
  }

  const defaultOperatorId = authUser.value?.operator_id
  return operatorOptions.value.find(option => option.id === defaultOperatorId) ?? operatorOptions.value[0] ?? null
}

function getDefaultPromoType() {
  if (!promoTypeOptions.value.length) {
    return null
  }

  return promoTypeOptions.value[0]
}

function getTodayDateString() {
  const today = new Date()
  return today.toISOString().slice(0, 10)
}

function getDefaultForm() {
  return {
    name: '',
    operator: getDefaultOperator(),
    promo_type: getDefaultPromoType(),
    bundle_qty: '',
    slug: '',
    description: '',
    value: '',
    labels_x: [],
    labels_y: [],
    start_at: getTodayDateString(),
    end_at: '',
    remarks: '',
  }
}

function submit() {
  form.value.clearErrors()

  form.value
    .transform((data) => ({
      name: data.name,
      operator_id: data.operator ? data.operator.id : null,
      promo_type: data.promo_type ? data.promo_type.id : null,
      bundle_qty: data.bundle_qty !== '' && data.bundle_qty !== null ? Number(data.bundle_qty) : null,
      slug: data.slug,
      description: data.description,
      value: data.value !== '' && data.value !== null ? Number(data.value) : null,
      labels_x: Array.isArray(data.labels_x) ? data.labels_x.map(tag => tag.id) : [],
      labels_y: Array.isArray(data.labels_y) ? data.labels_y.map(tag => tag.id) : [],
      start_at: data.start_at || null,
      end_at: data.end_at || null,
      remarks: data.remarks && data.remarks.trim() !== '' ? data.remarks : null,
    }))
    .post('/campaigns/create', {
      preserveState: true,
      replace: true,
    })
}
</script>
