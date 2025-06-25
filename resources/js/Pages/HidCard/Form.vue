<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600" v-if="props.hidCard">
            Editing
          </span>
          <span v-if="props.hidCard">
            {{ props.hidCard.name }}
          </span>
          <span class="text-gray-600" v-else>
            Create New HID Card
          </span>
        </div>
      </template>
      <template #default>
        <form @submit.prevent="submit" id="submit">
          <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">
            <div class="sm:col-span-6">
              <FormInput v-model="form.value" :error="form.errors.value" required="true">
                Value
              </FormInput>
            </div>
            <div class="sm:col-span-6">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Operator <span class="text-red-500">*</span>
              </label>
              <MultiSelect
                v-model="form.operator_id"
                :options="operatorOptions"
                trackBy="id"
                valueProp="id"
                label="full_name"
                placeholder="Select"
                open-direction="top"
                class="mt-1"
              ></MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.operator_id">
                {{ form.errors.operator_id }}
              </div>
            </div>
          </div>
          <div class="sm:col-span-6 mt-2">
            <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
              <div class="flex space-x-1 items-center">
                <span>
                  Machine(s)
                </span>
                <span class="text-red-500 text-xs">
                  Default to all if not selected
                </span>
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
            >
            </MultiSelect>
            <div class="text-sm text-red-600" v-if="form.errors.vends">
              {{ form.errors.vends }}
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
import Modal from '@/Components/Modal.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { ArrowUturnLeftIcon, CheckCircleIcon } from '@heroicons/vue/20/solid';
import { useForm } from '@inertiajs/vue3';
import { ref, onMounted, watch } from 'vue'
import axios from 'axios';

const props = defineProps({
  hidCard: Object,
  operatorOptions: [Array, Object],
  type: String,
  showModal: Boolean,
  vendOptions: [Array, Object]
})

const emit = defineEmits(['modalClose'])

const form = ref(
  useForm(getDefaultForm())
)
const operatorOptions = ref(props.operatorOptions || [])
const vendOptions = ref([])

onMounted(() => {
  vendOptions.value = props.vendOptions.data?.map((vend) => ({
    id: vend.id,
    full_name: `${vend.code} - ${vend.customer?.name || ''}`,
  }))

  form.value = props.hidCard ? useForm({
    ...props.hidCard,
    operator_id: props.hidCard.operator ? props.hidCard.operator : null,
  }) : useForm(getDefaultForm())

  // remove first option, which is all operatorOptions
  if (Array.isArray(operatorOptions.value) && operatorOptions.value.length > 0) {
    operatorOptions.value = operatorOptions.value.filter(option => option.id !== 'all')
  }
})

watch(
  () => form.value.operator_id,
  (newOperator) => {
    if (newOperator && newOperator.id) {
      emit('fetchVends', newOperator.id)
    }
  }
)

function fetchVends(operatorId) {
  axios.get(`/api/vends?operator_id=${operatorId}`).then(response => {
    vendOptions.value = response.data
  })
}

function getDefaultForm() {
  return {
    value: '',
    operator_id: '',
    vends: [],
  }
}

function submit() {
  form.value.clearErrors()

  if(props.type === 'create') {
    form.value
    .transform((data) => ({
      ...data,
      operator_id: data.operator_id ? data.operator_id?.id : null,
      vends: data.vends?.map((vend) => vend.id),
    }))
    .post('/hid-cards/create', {
      onSuccess: () => {
        emit('modalClose')
      },
      preserveState: true,
      replace: true,
    })
  }

  if(props.type === 'update') {
    form.value
      .transform((data) => ({
        ...data,
        operator_id: data.operator_id ? data.operator_id?.id : null,
        vends: data.vends?.map((vend) => vend.id),
      }))
      .post('/hid-cards/' + form.value.id + '/update', {
      onSuccess: () => {
        emit('modalClose')
      },
      preserveState: true,
      replace: true,
    })
  }
}

</script>