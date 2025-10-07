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
              <div class="sm:col-span-6">
                <FormInput v-model="form.name" :error="form.errors.name" required="true">
                  Name
                </FormInput>
              </div>
              <div class="sm:col-span-6">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Delivery Product Mapping
                  <span class="text-red-500">
                    *
                  </span>
                </label>
                <MultiSelect
                  v-model="form.delivery_product_mapping_id"
                  :options="props.deliveryProductMappingOptions.data"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                  @selected="onDeliveryProductMappingSelected"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.delivery_product_mapping_id">
                  {{ form.errors.delivery_product_mapping_id }}
                </div>
              </div>
              <div class="sm:col-span-6" v-if="form.delivery_product_mapping_id">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Platform
                  <span class="text-red-500">
                    *
                  </span>
                </label>
                <MultiSelect
                  v-model="form.delivery_platform_operator_id"
                  :options="deliveryPlatformOperatorOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.delivery_platform_operator_id">
                  {{ form.errors.delivery_platform_operator_id }}
                </div>
              </div>
              <div class="sm:col-span-6">
                <FormTextarea v-model="form.remarks" :error="form.errors.remarks">
                  Remarks
                </FormTextarea>
              </div>

              <div class="sm:col-span-6">
                <div class="flex space-x-1 mt-5 justify-end">
                  <Link :href="'/operators'">
                    <Button
                      type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1"
                    >
                      <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
                      <span>
                        Back
                      </span>
                    </Button>
                  </Link>
                  <Button type="submit" v-if="permissions.includes('update operators')" class="bg-green-500 hover:bg-green-600 text-white flex space-x-1">
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
// import DatePicker from '@/Components/DatePicker.vue';
import DatetimePicker from '@/Components/DatetimePicker.vue';
import FormInput from '@/Components/FormInput.vue';
import FormTextarea from '@/Components/FormTextarea.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { ArrowUturnLeftIcon, CheckCircleIcon } from '@heroicons/vue/20/solid';
import { computed, ref, onMounted } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import moment from 'moment';

const props = defineProps({
  deliveryPlatformOperatorOptions: Object,
  deliveryProductMappingOptions: Object,
})

const authUser = computed(() => usePage().props.auth.user)

const deliveryPlatformOperatorOptions = ref(mapDeliveryPlatformOperators(props.deliveryPlatformOperatorOptions))
const form = ref(useForm(getDefaultForm()))
const permissions = usePage().props.auth.permissions

onMounted(() => {
    deliveryPlatformOperatorOptions.value = mapDeliveryPlatformOperators(props.deliveryPlatformOperatorOptions)
    form.value = useForm(getDefaultForm())
    setOperatorFromMapping(form.value.delivery_product_mapping_id)
})

function getDefaultForm() {
  const defaultDeliveryProductMapping = getDefaultDeliveryProductMappingOption()

  return {
    name: '',
    delivery_product_mapping_id: defaultDeliveryProductMapping || '',
    delivery_platform_operator_id: getDefaultDeliveryPlatformOperatorOption(defaultDeliveryProductMapping) || '',
    remarks: '',
  }
}

function onDeliveryProductMappingSelected() {
  const selectedMapping = form.value.delivery_product_mapping_id

  if (!selectedMapping || !selectedMapping.id) {
    form.value.delivery_platform_operator_id = ''
    deliveryPlatformOperatorOptions.value = mapDeliveryPlatformOperators(props.deliveryPlatformOperatorOptions)
    return
  }

  router.reload({
    only: ['deliveryPlatformOperatorOptions'],
    data: {
      delivery_product_mapping_id: selectedMapping.id,
    },
    replace: true,
    preserveState: true,
    onSuccess: page => {
      deliveryPlatformOperatorOptions.value = mapDeliveryPlatformOperators(page.props.deliveryPlatformOperatorOptions)
      setOperatorFromMapping(selectedMapping)
    }
  })
}

function submit() {
  form.value.clearErrors()

  form.value
  .transform((data) => ({
    ...data,
    delivery_product_mapping_id: data.delivery_product_mapping_id.id,
    delivery_platform_operator_id: data.delivery_platform_operator_id.id,
  }))
  .post('/delivery-platform-campaigns/store', {
    preserveState: true,
    replace: true,
  })
}

function mapDeliveryPlatformOperators(resource) {
  if (!resource || !resource.data) {
    return []
  }

  return resource.data.map((data) => {
    const platformName = data.deliveryPlatform?.name ?? ''
    const typeLabel = data.type ? ` (${data.type})` : ''

    return {
      id: data.id,
      name: `${platformName}${typeLabel}`.trim(),
    }
  })
}

function getDefaultDeliveryProductMappingOption() {
  const mappingOptions = props.deliveryProductMappingOptions?.data ?? []

  if (!mappingOptions.length) {
    return null
  }

  const operatorId = authUser.value?.operator_id

  return mappingOptions.find((option) => option.operator_id === operatorId) ?? mappingOptions[0]
}

function getDefaultDeliveryPlatformOperatorOption(mappingOption) {
  if (!mappingOption) {
    return null
  }

  return deliveryPlatformOperatorOptions.value.find((option) => option.id === mappingOption.delivery_platform_operator_id) ?? deliveryPlatformOperatorOptions.value[0] ?? null
}

function setOperatorFromMapping(mappingOption) {
  if (!mappingOption) {
    return
  }

  const operatorOption = getDefaultDeliveryPlatformOperatorOption(mappingOption)

  if (operatorOption) {
    form.value.delivery_platform_operator_id = operatorOption
  }
}
</script>
