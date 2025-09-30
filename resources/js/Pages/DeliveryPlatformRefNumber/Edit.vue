<template>
  <Head title="Edit Platform ID" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Edit Platform ID
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
        <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 p-5 mb-3">
            <form @submit.prevent="submit" id="submit">
              <div class="grid grid-cols-1 gap-3 sm:grid-cols-6 pb-5 mb-3">
                <div class="sm:col-span-3">
                  <FormInput
                    v-model="form.ref_number"
                    required
                    placeholderStr="Platform ID"
                    :error="form.errors.ref_number"
                  >
                    Platform ID
                  </FormInput>
                </div>

                <div class="sm:col-span-3">
                  <label class="text-sm font-medium text-gray-700">
                    Operator <span class="text-red-500">*</span>
                  </label>
                  <MultiSelect
                    v-model="form.operator_id"
                    :options="operatorOptions"
                    trackBy="id"
                    valueProp="id"
                    label="full_name"
                    placeholder="Select"
                    class="mt-1"
                  />
                  <div class="text-sm text-red-600" v-if="form.errors.operator_id">
                    {{ form.errors.operator_id }}
                  </div>
                </div>

                <div class="sm:col-span-6">
                  <FormInput
                    v-model="form.remarks"
                    placeholderStr="Remarks"
                    :error="form.errors.remarks"
                  >
                    Remarks
                  </FormInput>
                </div>

                <div class="sm:col-span-3">
                  <label class="text-sm font-medium text-gray-700">Status</label>
                  <MultiSelect
                    v-model="form.is_active"
                    :options="booleanOptions"
                    trackBy="id"
                    valueProp="id"
                    label="value"
                    placeholder="Select"
                    class="mt-1"
                  />
                  <div class="text-sm text-red-600" v-if="form.errors.is_active">
                    {{ form.errors.is_active }}
                  </div>
                </div>

                <div class="sm:col-span-6 py-4">
                  <Button type="submit" class="bg-green-500 hover:bg-green-600 text-white flex space-x-1">
                    <CheckCircleIcon class="w-4 h-4" />
                    <span>Update</span>
                  </Button>
                </div>
              </div>
            </form>
          </div>
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 p-5">
            <div class="relative mb-5">
              <div class="absolute inset-0 flex items-center" aria-hidden="true">
                <div class="w-full border-t border-gray-300"></div>
              </div>
              <div class="relative flex justify-start">
                <span class="px-2 bg-white text-lg font-medium text-gray-900 rounded">Binding History</span>
              </div>
            </div>

            <nav aria-label="Binding History">
              <ol role="list" class="overflow-hidden">
                <li
                  v-for="(entry, entryIndex) in bindingHistory"
                  :key="entry.id"
                  :class="[entryIndex !== bindingHistory.length - 1 ? 'pb-3' : 'relative bg-gray-300 rounded']"
                >
                  <span class="group relative flex items-start">
                    <span class="flex h-9 items-center">
                      <span
                        class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full"
                        :class="[entry.is_binding ? 'bg-green-600' : 'bg-red-600']"
                      >
                        <LockClosedIcon class="h-5 w-5 text-white" aria-hidden="true" v-if="entry.is_binding" />
                        <LockOpenIcon class="h-5 w-5 text-white" aria-hidden="true" v-else />
                      </span>
                    </span>
                    <span class="ml-4 flex min-w-0 flex-col">
                      <span class="text-sm font-medium">
                        {{ entry.description }}
                      </span>
                      <span class="text-sm text-gray-500">{{ formatDatetime(entry.moment) }}</span>
                    </span>
                  </span>
                </li>
              </ol>
            </nav>

            <template v-if="!bindingHistory.length">
              <span class="group relative flex items-start">
                <span class="flex h-9 items-center">
                  <span class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full bg-red-600">
                    <MinusCircleIcon class="h-5 w-5 text-white" aria-hidden="true" />
                  </span>
                </span>
                <span class="ml-4 flex min-w-0 flex-col pt-2">
                  <span class="text-sm font-medium">
                    No Records Found
                  </span>
                </span>
              </span>
            </template>
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, onMounted, watch } from 'vue'
import FormInput from '@/Components/FormInput.vue'
import Button from '@/Components/Button.vue'
import MultiSelect from '@/Components/MultiSelect.vue'
import { CheckCircleIcon, LockClosedIcon, LockOpenIcon, MinusCircleIcon } from '@heroicons/vue/20/solid'
import moment from 'moment'

const props = defineProps({
  deliveryPlatformRefNumber: Object,
  operatorOptions: [Array, Object],
})

const operatorOptions = ref([])
const booleanOptions = ref([])
const bindingHistory = ref([])

const form = ref(useForm(getDefaultForm()))

onMounted(() => {
  operatorOptions.value = props.operatorOptions.data
  booleanOptions.value = [
    { id: 'true', value: 'Active' },
    { id: 'false', value: 'Inactive' },
  ]

  // hydrate form from props
  form.value.id = props.deliveryPlatformRefNumber.data.id
  form.value.ref_number = props.deliveryPlatformRefNumber.data.ref_number
  form.value.remarks = props.deliveryPlatformRefNumber.data.remarks
  form.value.operator_id = operatorOptions.value.find(o => o.id === props.deliveryPlatformRefNumber.data.operator_id)
  form.value.is_active = props.deliveryPlatformRefNumber.data.status === 1 ? booleanOptions.value[0] : booleanOptions.value[1]
})

watch(
  () => props.deliveryPlatformRefNumber,
  (newValue) => {
    if (!newValue || !newValue.data) {
      bindingHistory.value = []
      return
    }

    bindingHistory.value = buildBindingHistory(newValue.data.delivery_product_mapping_vends || [])
  },
  { immediate: true }
)

function getDefaultForm() {
  return {
    id: null,
    operator_id: '',
    ref_number: '',
    remarks: '',
    is_active: { id: 'true', value: 'Active' },
  }
}

function buildBindingHistory(vendBindings) {
  const entries = []

  vendBindings.forEach((binding) => {
    const machineCode = binding.vend?.code
    const customerName = binding.vend?.customer?.name
    const contextParts = []
    if (machineCode) {
      contextParts.push(`Machine ${machineCode}`)
    }
    if (customerName) {
      contextParts.push(customerName)
    }
    const context = contextParts.length ? ` (${contextParts.join(' · ')})` : ''

    const bindMoment = parseToMoment(binding.created_at_iso || binding.created_at)
    if (bindMoment) {
      entries.push({
        id: `${binding.id}-bind`,
        is_binding: true,
        description: `Bound${context}`,
        moment: bindMoment,
      })
    }

    const unbindMoment = parseToMoment(binding.end_date_iso || binding.end_date)
    if (unbindMoment) {
      entries.push({
        id: `${binding.id}-unbind`,
        is_binding: false,
        description: `Unbound${context}`,
        moment: unbindMoment,
      })
    }
  })

  return entries.sort((a, b) => b.moment.valueOf() - a.moment.valueOf())
}

function parseToMoment(value) {
  if (!value) {
    return null
  }
  const parsed = value.includes('T') ? moment(value) : moment(value, 'YYMMDD hh:mma')
  return parsed.isValid() ? parsed : null
}

function formatDatetime(momentObj) {
  return momentObj ? momentObj.clone().format('YYYY-MM-DD hh:mm a') : ''
}

function submit() {
  form.value.clearErrors()

  form.value
    .transform((data) => ({
      ...data,
      operator_id: data.operator_id?.id ?? null,
      is_active: data.is_active?.id ?? 'true',
    }))
    .post(`/delivery-platform-ref-numbers/${form.value.id}/update`, {
      preserveState: true,
      replace: true,
    })
}
</script>
