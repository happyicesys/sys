<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600" v-if="props.cashlessTerminal">
            Editing
          </span>
          <span v-if="props.cashlessTerminal">
            {{ props.cashlessTerminal.name }}
          </span>
          <span class="text-gray-600" v-else>
            Create New Cashless Terminal
          </span>
        </div>
      </template>
      <template #default>
        <form @submit.prevent="submit" id="submit">
          <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">
            <div class="sm:col-span-6">
              <FormInput v-model="form.code" :error="form.errors.code" required="true">
                Terminal ID
              </FormInput>
            </div>
            <div class="sm:col-span-6">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Cashless Terminal Modal
                <span class="text-red-500">
                    *
                </span>
              </label>
              <MultiSelect
                v-model="form.cashless_provider_id"
                :options="cashlessProviderOptions"
                trackBy="id"
                valueProp="id"
                label="name"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.cashless_provider_id">
                {{ form.errors.cashless_provider_id }}
              </div>
            </div>
            <div class="sm:col-span-6">
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
          </div>

        </form>
      </template>
    </Modal>
  </Teleport>
</template>

<script setup>
import Button from '@/Components/Button.vue';
import FormInput from '@/Components/FormInput.vue';
import Modal from '@/Components/Modal.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { ArrowUturnLeftIcon, CheckCircleIcon } from '@heroicons/vue/20/solid';
import { useForm, usePage } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue'
import { useToast } from "vue-toastification";

const authOperator = usePage().props.auth.operator

const props = defineProps({
  cashlessTerminal: Object,
  cashlessProviderOptions: [Array, Object],
  operatorOptions: [Array, Object],
  showModal: Boolean,
  type: String,
})

const emit = defineEmits(['modalClose'])

const form = ref(
  useForm(getDefaultForm())
)
const operatorOptions = ref([])
const toast = useToast()

onMounted(() => {
  operatorOptions.value = props.operatorOptions
  form.value = props.cashlessTerminal ?
  useForm({
    ...props.cashlessTerminal,
    cashless_provider_id: props.cashlessProviderOptions.find(cashlessProvider => cashlessProvider.id === props.cashlessTerminal.cashless_provider_id),
  })
   : useForm(getDefaultForm())
  form.value.operator_id = authOperator ? operatorOptions.value.find(operator => operator.id === authOperator.id) : operatorOptions.value[0]
})

function getDefaultForm() {
  return {
    code: '',
    cashless_provider_id: '',
    operator_id: '',
  }
}

function submit() {
  form.value.clearErrors()

  if(props.type === 'create') {
    form.value
    .transform((data) => {
      return {
        ...data,
        operator_id: data.operator_id.id,
        cashless_provider_id: data.cashless_provider_id.id,
      }
    })
    .post('/cashless-terminals/store', {
      onSuccess: () => {
        toast.success("Cashless terminal created successfully", { timeout: 3000 })
        emit('modalClose')
      },
      onError: () => {
        toast.error("Failed to create cashless terminal", { timeout: 3000 })
      },
      preserveState: true,
      replace: true,
    })
  }

  if(props.type === 'update') {
    form.value
    .transform((data) => {
      return {
        ...data,
        operator_id: data.operator_id.id,
        cashless_provider_id: data.cashless_provider_id.id,
      }
    })
      .post('/cashless-terminals/' + form.value.id + '/update', {
      onSuccess: () => {
        toast.success("Cashless terminal updated successfully", { timeout: 3000 })
        emit('modalClose')
      },
      onError: () => {
        toast.error("Failed to update cashless terminal", { timeout: 3000 })
      },
      preserveState: true,
      replace: true,
    })
  }
}

</script>