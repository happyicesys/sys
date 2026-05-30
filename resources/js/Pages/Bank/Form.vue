<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600" v-if="props.bank">
            Editing
          </span>
          <span v-if="props.bank">
            {{ props.bank.name }}
          </span>
          <span class="text-gray-600" v-else>
            Create New Bank
          </span>
        </div>
      </template>
      <template #default>
        <form @submit.prevent="submit" id="submit">
          <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">
            <div class="sm:col-span-6">
              <FormInput v-model="form.name" :error="form.errors.name" required="true">
                Name
              </FormInput>
            </div>
            <div class="sm:col-span-6">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Country
              </label>
              <MultiSelect
                v-model="form.country_id"
                :options="countryOptions"
                trackBy="id"
                valueProp="id"
                label="name"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              ></MultiSelect>
            </div>
            <div class="sm:col-span-6 flex items-center gap-2">
              <input
                type="checkbox"
                id="bank_is_active"
                v-model="form.is_active"
                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
              />
              <label for="bank_is_active" class="text-sm font-medium text-gray-700 cursor-pointer">
                Active
              </label>
            </div>
          </div>
          <div class="sm:col-span-6">
            <div class="flex space-x-1 mt-5 justify-end">
              <Button
                class="bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1"
                @click="$emit('modalClose')"
                form="submit"
              >
                <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
                <span>
                  Back
                </span>
              </Button>
              <Button type="submit" class="bg-green-500 hover:bg-green-600 text-white flex space-x-1">
                <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                <span>
                  Save
                </span>
              </Button>
            </div>
          </div>
        </form>
      </template>
    </Modal>
  </Teleport>
</template>

<script setup>
import Button from '@/Components/Button.vue';
import FormInput from '@/Components/FormInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import Modal from '@/Components/Modal.vue';
import { ArrowUturnLeftIcon, CheckCircleIcon } from '@heroicons/vue/20/solid';
import { useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue'
import { useToast } from "vue-toastification";

const props = defineProps({
  bank: Object,
  type: String,
  showModal: Boolean,
  countryOptions: Array,
})

const emit = defineEmits(['modalClose'])

const form = ref(
  useForm(getDefaultForm())
)
const toast = useToast()

onMounted(() => {
  if (props.bank) {
    form.value = useForm({
      id: props.bank.id,
      name: props.bank.name,
      country_id: props.countryOptions
        ? props.countryOptions.find(c => c.id === props.bank.country_id) || null
        : null,
      is_active: props.bank.is_active ?? true,
    })
  } else {
    form.value = useForm(getDefaultForm())
  }
})

function getDefaultForm() {
  return {
    name: '',
    country_id: null,
    is_active: true,
  }
}

function submit() {
  form.value.clearErrors()

  const transformed = form.value.transform((data) => ({
    ...data,
    country_id: data.country_id ? data.country_id.id : null,
  }))

  if (props.type === 'create') {
    transformed.post('/banks/store', {
      onSuccess: () => {
        toast.success("Bank created successfully", { timeout: 3000 })
        emit('modalClose')
      },
      onError: () => {
        toast.error("Failed to create bank", { timeout: 3000 })
      },
      preserveState: true,
      replace: true,
    })
  }

  if (props.type === 'update') {
    transformed.post('/banks/' + form.value.id + '/update', {
      onSuccess: () => {
        toast.success("Bank updated successfully", { timeout: 3000 })
        emit('modalClose')
      },
      onError: () => {
        toast.error("Failed to update bank", { timeout: 3000 })
      },
      preserveState: true,
      replace: true,
    })
  }
}
</script>
