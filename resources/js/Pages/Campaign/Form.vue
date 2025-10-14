<template>
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
              <label for="operator" class="flex justify-start text-sm font-medium text-gray-700 space-x-1">
                <span>
                  Operator
                </span>
                <span class="text-red-600">*</span>
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
                :required="true"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.operator_id">
                {{ form.errors.operator_id }}
              </div>
            </div>
            <div class="sm:col-span-6">
              <FormInput v-model="form.slug" :error="form.errors.slug" required="true">
                Slug (Appear in Machine Display)
              </FormInput>
            </div>
            <div class="sm:col-span-6">
              <FormTextarea v-model="form.description" :error="form.errors.description" rows="3">
                Description
              </FormTextarea>
            </div>
            <div class="sm:col-span-6 pt-2">
              <hr>
            </div>
            <div class="sm:col-span-4">
              <label class="flex justify-start text-sm font-medium text-gray-700 space-x-1">
                <span>
                  Promotion Type
                </span>
                <span class="text-red-600">*</span>
              </label>
              <MultiSelect
                v-model="form.promo_type"
                :options="promoTypeOptions"
                trackBy="id"
                valueProp="id"
                label="name"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
                :required="true"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.promo_type">
                {{ form.errors.promo_type }}
              </div>
            </div>
            <div class="sm:col-span-3" v-if="!isFreeItemPromo">
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
              <label class="flex justify-start text-sm font-medium text-gray-700">
                {{ isFreeItemPromo ? 'Labels' : 'Labels Y' }}
              </label>
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
              <label class="flex justify-start text-sm font-medium text-gray-700 space-x-1">
                <span>
                  Discount Basis
                </span>
                <span class="text-red-600">*</span>
              </label>
              <MultiSelect
                v-model="form.is_using_qty"
                :options="isUsingQtyOptions"
                trackBy="id"
                valueProp="id"
                label="name"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
                required="true"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.is_using_qty">
                {{ form.errors.is_using_qty }}
              </div>
            </div>
            <div class="sm:col-span-3" v-if="showBundleQtyField">
              <FormInput v-model="form.bundle_qty" :error="form.errors.bundle_qty" inputType="number" placeholderStr="Bundle quantity">
                Bundle Quantity X
              </FormInput>
            </div>
            <div class="sm:col-span-3" v-if="showValueField">
              <FormInput v-model="form.value" :error="form.errors.value" inputType="number" placeholderStr="Discount value">
                Value X
              </FormInput>
            </div>
            <div class="sm:col-span-3" v-if="!isFreeItemPromo">
              <FormInput
                v-model="form.min_basket_value"
                :error="form.errors.min_basket_value"
                inputType="number"
                placeholderStr="Minimum basket amount"
              >
                Min Basket Value
              </FormInput>
            </div>
            <div class="sm:col-span-3" v-if="!isFreeItemPromo">
              <FormInput
                v-model="form.max_discount_value"
                :error="form.errors.max_discount_value"
                inputType="number"
                placeholderStr="Maximum discount amount"
              >
                Max Discount Value
              </FormInput>
            </div>
            <div class="sm:col-span-6 grid grid-cols-1 gap-y-3 sm:grid-cols-2 sm:gap-x-3">
              <div>
                <DatePicker v-model="form.start_at" :error="form.errors.start_at">
                  Start At
                </DatePicker>
              </div>
              <div>
                <DatePicker v-model="form.end_at" :error="form.errors.end_at" :minDate="form.start_at">
                  End At
                </DatePicker>
              </div>
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
</template>

<script setup>
import Button from '@/Components/Button.vue';
import FormInput from '@/Components/FormInput.vue';
import FormTextarea from '@/Components/FormTextarea.vue';
import DatePicker from '@/Components/DatePicker.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { ArrowUturnLeftIcon, CheckCircleIcon } from '@heroicons/vue/20/solid';
import { computed, onMounted, ref, watch } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
  type: {
    type: String,
    default: 'create',
    validator: (value) => ['create', 'update'].includes(value),
  },
  campaign: {
    type: Object,
    default: null,
  },
  operatorOptions: {
    type: Object,
    default: () => ({}),
  },
  tagOptions: {
    type: Object,
    default: () => ({}),
  },
  promoTypeOptions: {
    type: Array,
    default: () => [],
  },
});

const authUser = computed(() => usePage().props.auth.user)
const operatorOptions = ref([])
const tagOptions = ref([])
const promoTypeOptions = ref([])
const isUsingQtyOptions = ref(getIsUsingQtyOptions())
const form = ref(useForm(getDefaultForm()))

const labelsXError = computed(() => form.value.errors.labels_x ?? form.value.errors['labels_x.0'] ?? null)
const labelsYError = computed(() => form.value.errors.labels_y ?? form.value.errors['labels_y.0'] ?? null)
const isFreeItemPromo = computed(() => isPromoTypeFreeItem(form.value.promo_type))
const selectedIsUsingQty = computed(() => {
  const value = form.value.is_using_qty

  if (value && typeof value === 'object') {
    return value.id ?? null
  }

  return value ?? null
})
const showBundleQtyField = computed(() => selectedIsUsingQty.value === 'qty' || selectedIsUsingQty.value === 'both')
const showValueField = computed(() => !isFreeItemPromo.value && (selectedIsUsingQty.value === 'amount' || selectedIsUsingQty.value === 'both'))

watch(
  () => form.value.promo_type,
  (newPromo, oldPromo) => {
    const nowFreeItem = isPromoTypeFreeItem(newPromo)
    const wasFreeItem = isPromoTypeFreeItem(oldPromo)

    if (nowFreeItem) {
      if (!Array.isArray(form.value.labels_y) || form.value.labels_y.length === 0) {
        form.value.labels_y = Array.isArray(form.value.labels_x) ? [...form.value.labels_x] : []
      }
      form.value.labels_x = []
      form.value.value = ''
      form.value.min_basket_value = ''
      form.value.max_discount_value = ''
      form.value.clearErrors('labels_x')
      form.value.clearErrors('labels_y')
      form.value.clearErrors('value')
      form.value.clearErrors('min_basket_value')
      form.value.clearErrors('max_discount_value')
    } else if (wasFreeItem && (!Array.isArray(form.value.labels_x) || form.value.labels_x.length === 0)) {
      form.value.labels_x = Array.isArray(form.value.labels_y) ? [...form.value.labels_y] : []
    }
  }
)

watch(showBundleQtyField, (shouldShow) => {
  if (!shouldShow) {
    form.value.bundle_qty = ''
    form.value.clearErrors('bundle_qty')
  }
})

watch(showValueField, (shouldShow) => {
  if (!shouldShow) {
    form.value.value = ''
    form.value.clearErrors('value')
  }
})

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

function getIsUsingQtyOptions() {
  return [
    { id: 'qty', name: 'By Qty' },
    { id: 'amount', name: 'By Amount' },
    { id: 'both', name: 'Both' },
  ]
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

function getDefaultIsUsingQty() {
  if (!isUsingQtyOptions.value.length) {
    isUsingQtyOptions.value = getIsUsingQtyOptions()
  }

  return isUsingQtyOptions.value[0] ?? null
}

function isPromoTypeFreeItem(promo) {
  if (!promo) {
    return false
  }

  if (typeof promo === 'string') {
    return promo === 'Item' || promo === 'Free Item'
  }

  if (typeof promo === 'object') {
    const id = promo.id ?? promo.value ?? null
    const name = promo.name ?? null

    return id === 'Item' || name === 'Free Item'
  }

  return false
}

function findIsUsingQtyOption(value) {
  if (value === undefined || value === null) {
    return getDefaultIsUsingQty()
  }

  const normalizedValue = typeof value === 'boolean'
    ? (value ? 'qty' : 'amount')
    : String(value)

  return isUsingQtyOptions.value.find(option => option.id === normalizedValue) ?? getDefaultIsUsingQty()
}

function getTodayDateString() {
  const today = new Date()
  return today.toISOString().slice(0, 10)
}

function unwrapResource(resource) {
  if (resource === null || resource === undefined) {
    return null
  }

  if (Array.isArray(resource)) {
    return resource
  }

  if (typeof resource === 'object' && Object.prototype.hasOwnProperty.call(resource, 'data')) {
    return resource.data
  }

  return resource
}

function extractCollectionValues(resource) {
  const unwrapped = unwrapResource(resource)

  if (!unwrapped) {
    return []
  }

  return Array.isArray(unwrapped) ? unwrapped : [unwrapped]
}

function mapCampaignToForm(campaignResource) {
  const campaign = unwrapResource(campaignResource) ?? {}
  const operator = unwrapResource(campaign.operator)
  const operatorId = campaign.operator_id ?? operator?.id ?? null
  const operatorOption = operatorId
    ? operatorOptions.value.find(option => option.id === operatorId)
    : null

  const promoTypeOption = campaign.promo_type
    ? promoTypeOptions.value.find(option => option.id === campaign.promo_type)
    : null

  let labelsX = extractCollectionValues(campaign.labels_x).map((tag) => {
    return tagOptions.value.find(option => option.id === tag.id) ?? {
      id: tag.id,
      name: tag.name ?? tag.slug ?? `Tag ${tag.id}`,
    }
  })

  let labelsY = extractCollectionValues(campaign.labels_y).map((tag) => {
    return tagOptions.value.find(option => option.id === tag.id) ?? {
      id: tag.id,
      name: tag.name ?? tag.slug ?? `Tag ${tag.id}`,
    }
  })

  if (isPromoTypeFreeItem(promoTypeOption ?? campaign.promo_type)) {
    if (!labelsY.length && labelsX.length) {
      labelsY = [...labelsX]
    }
    labelsX = []
  }

  return {
    id: campaign.id ?? null,
    name: campaign.name ?? '',
    operator: operatorOption ?? getDefaultOperator(),
    promo_type: promoTypeOption ?? getDefaultPromoType(),
    is_using_qty: findIsUsingQtyOption(campaign.is_using_qty),
    bundle_qty: campaign.bundle_qty !== null && campaign.bundle_qty !== undefined ? String(campaign.bundle_qty) : '',
    slug: campaign.slug ?? '',
    description: campaign.description ?? '',
    value: campaign.value !== null && campaign.value !== undefined ? String(campaign.value) : '',
    min_basket_value: campaign.min_basket_value !== null && campaign.min_basket_value !== undefined ? String(campaign.min_basket_value) : '',
    max_discount_value: campaign.max_discount_value !== null && campaign.max_discount_value !== undefined ? String(campaign.max_discount_value) : '',
    labels_x: labelsX,
    labels_y: labelsY,
    start_at: campaign.start_at ?? '',
    end_at: campaign.end_at ?? '',
    remarks: campaign.remarks ?? '',
  }
}

function getDefaultForm() {
  if (props.type === 'update' && props.campaign) {
    return mapCampaignToForm(props.campaign)
  }

  return {
    id: null,
    name: '',
    operator: getDefaultOperator(),
    promo_type: getDefaultPromoType(),
    is_using_qty: getDefaultIsUsingQty(),
    bundle_qty: '',
    slug: '',
    description: '',
    value: '',
    min_basket_value: '',
    max_discount_value: '',
    labels_x: [],
    labels_y: [],
    start_at: getTodayDateString(),
    end_at: '',
    remarks: '',
  }
}

function submit() {
  form.value.clearErrors()

  const request = form.value.transform((data) => {
    const isFreeItem = isPromoTypeFreeItem(data.promo_type)
    const labelXIds = Array.isArray(data.labels_x) ? data.labels_x.map(tag => tag.id) : []
    const labelYIds = Array.isArray(data.labels_y) ? data.labels_y.map(tag => tag.id) : []
    const labelsForFreeItem = labelYIds.length ? labelYIds : labelXIds

    return {
      name: data.name,
      operator_id: data.operator ? data.operator.id : null,
      promo_type: data.promo_type ? data.promo_type.id : null,
      is_using_qty: data.is_using_qty ? data.is_using_qty.id : null,
      bundle_qty: data.bundle_qty !== '' && data.bundle_qty !== null ? Number(data.bundle_qty) : null,
      slug: data.slug ?? null,
      description: data.description ?? null,
      value: data.value !== '' && data.value !== null ? Number(data.value) : null,
      min_basket_value: data.min_basket_value !== '' && data.min_basket_value !== null ? Number(data.min_basket_value) : null,
      max_discount_value: data.max_discount_value !== '' && data.max_discount_value !== null ? Number(data.max_discount_value) : null,
      labels_x: isFreeItem ? [] : labelXIds,
      labels_y: isFreeItem ? labelsForFreeItem : labelYIds,
      start_at: data.start_at || null,
      end_at: data.end_at || null,
      remarks: data.remarks && data.remarks.trim() !== '' ? data.remarks : null,
    }
  })

  const url = props.type === 'update' && form.value.id
    ? `/campaigns/${form.value.id}/update`
    : '/campaigns/create'

  request.post(url, {
    preserveState: true,
    replace: true,
  })
}
</script>
