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
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue'
import FormInput from '@/Components/FormInput.vue'
import Button from '@/Components/Button.vue'
import MultiSelect from '@/Components/MultiSelect.vue'
import { CheckCircleIcon } from '@heroicons/vue/20/solid'

const props = defineProps({
  deliveryPlatformRefNumber: Object,
  operatorOptions: [Array, Object],
})

const operatorOptions = ref([])
const booleanOptions = ref([])

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

function getDefaultForm() {
  return {
    id: null,
    operator_id: '',
    ref_number: '',
    remarks: '',
    is_active: { id: 'true', value: 'Active' },
  }
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

