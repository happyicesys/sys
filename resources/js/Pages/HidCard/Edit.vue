<template>
  <Head :title="`Edit HID Card: ${props.hidCard.value}`" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Edit HID Card: {{ props.hidCard.value }}
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
        <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 p-5 mb-3">
            <form @submit.prevent="submit" id="submit">
              <div class="grid grid-cols-1 gap-3 sm:grid-cols-6 pb-5 mb-3">
                <div class="sm:col-span-6">
                  <FormInput
                    v-model="form.value"
                    required
                    placeholderStr="Enter value"
                    :error="form.errors.value"
                  >
                    Card Value
                  </FormInput>
                </div>
                <div class="sm:col-span-3">
                  <FormInput
                    v-model="form.name"
                    placeholderStr="Name"
                    :error="form.errors.name"
                  >
                    Name
                  </FormInput>
                </div>
                <div class="sm:col-span-3">
                  <FormInput
                    v-model="form.email"
                    placeholderStr="Email"
                    :error="form.errors.email"
                  >
                    Email
                  </FormInput>
                </div>
                <div class="sm:col-span-6">
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
                    @selected="onOperatorChanged"
                    :disabled="true"
                  />
                  <div class="text-sm text-red-600" v-if="form.errors.operator_id">
                    {{ form.errors.operator_id }}
                  </div>
                </div>

                <div class="sm:col-span-6">
                  <label class="text-sm font-medium text-gray-700">
                    <div class="flex space-x-1 items-center">
                      <span>Machine(s)</span>
                      <span class="text-red-500 text-xs">Default to all if not selected</span>
                    </div>
                  </label>
                  <MultiSelect
                    v-model="form.vends"
                    :options="vendOptions"
                    trackBy="id"
                    valueProp="id"
                    label="full_name"
                    placeholder="Select"
                    open-direction="bottom"
                    class="mt-1"
                    mode="tags"
                  />
                  <div class="text-sm text-red-600" v-if="form.errors.vends">
                    {{ form.errors.vends }}
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
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref, onMounted } from 'vue'
import FormInput from '@/Components/FormInput.vue'
import Button from '@/Components/Button.vue'
import MultiSelect from '@/Components/MultiSelect.vue'
import { CheckCircleIcon } from '@heroicons/vue/20/solid'
import { useToast } from "vue-toastification";

const props = defineProps({
  hidCard: Object,
  operatorOptions: [Array, Object],
  vendOptions: [Array, Object],
})

const hidCard = ref()
const operatorOptions = ref([])
const toast = useToast()
const vendOptions = ref([])

const form = ref(useForm(getDefaultForm()))

function getDefaultForm() {
  return {
    id: props.hidCard.id,
    name: props.hidCard.name ?? '',
    email: props.hidCard.email ?? '',
    value: props.hidCard.value ?? '',
    operator_id: props.hidCard.operator ?? '',
    vends: props.hidCard.vends?.map(v => ({
      id: v.id,
      full_name: `${v.code} - ${v.customer?.name || ''}`
    })) ?? [],
  }
}

onMounted(() => {
  hidCard.value = props.hidCard.data
  operatorOptions.value = props.operatorOptions?.data ?? []

  vendOptions.value = (props.vendOptions?.data || []).map((vend) => ({
    id: vend.id,
    full_name: `${vend.code} - ${vend.customer?.name || ''}`,
  }))

  form.value = hidCard.value ? useForm(
    {
      ...hidCard.value,
      operator_id: hidCard.value ? operatorOptions.value.find(operator => operator.id == hidCard.value.operator_id) : [],
      vends: hidCard.value.vends ? hidCard.value.vends.map((vend) =>
        vendOptions.value.find((v) => v.id === vend.id)
      ) : []
    }
  ) : useForm(getDefaultForm())
})

function onOperatorChanged() {
  form.value.vends = []
  router.reload({
    only: ['vendOptions'],
    data: {
      operator_id: form.value.operator_id?.id,
    },
    replace: true,
    preserveState: true,
    onSuccess: (page) => {
      vendOptions.value = (page.props.vendOptions?.data || []).map((vend) => ({
        id: vend.id,
        full_name: `${vend.code} - ${vend.customer?.name || ''}`,
      }))
    },
  })
}

function submit() {
  form.value.clearErrors()

  form.value
    .transform((data) => ({
      ...data,
      operator_id: data.operator_id?.id ?? null,
      vends: data.vends?.map((v) => v.id) ?? [],
    }))
    .post(`/hid-cards/${form.value.id}/update`, {
      onSuccess: () => {
        toast.success("Successfully updated", { timeout: 3000 });
      },
      onError: (errors) => {
        console.error("Validation errors:", errors);
        toast.error("Failed to update. Please check your form.", { timeout: 3000 });
      },
      preserveState: true,
      replace: true,
    })
}
</script>
