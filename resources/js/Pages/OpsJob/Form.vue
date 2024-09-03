<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600">
            Create New Job
          </span>
        </div>
      </template>
      <template #default>
        <form @submit.prevent="submit" id="submit">
          <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">
            <div class="sm:col-span-6">
              <DatePicker v-model="form.date" :error="form.errors.date" @input="onDateChanged()">
                Date
                <span class="text-red-500">
                   *
                </span>
              </DatePicker>
            </div>
            <div class="sm:col-span-6">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Delivery By
                <span class="text-red-500">
                   *
                </span>
              </label>
              <MultiSelect
                v-model="form.delivered_by"
                :options="userOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="top"
                class="mt-1"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.delivered_by">
                {{ form.errors.delivered_by }}
              </div>
            </div>
            <!-- <div class="sm:col-span-6">
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
                open-direction="top"
                class="mt-1"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.operator_id">
                {{ form.errors.operator_id }}
              </div>
            </div> -->
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
import DatePicker from '@/Components/DatePicker.vue';
import FormInput from '@/Components/FormInput.vue';
import FormTextarea from '@/Components/FormTextarea.vue';
import Modal from '@/Components/Modal.vue';
import MultiSelect from '@/Components/MultiSelect.vue'
import { ArrowUturnLeftIcon, CheckCircleIcon } from '@heroicons/vue/20/solid';
import { useForm, usePage } from '@inertiajs/vue3';
import moment from 'moment';
import { ref, onMounted } from 'vue'

const authOperator = usePage().props.auth.operator

const props = defineProps({
  operatorOptions: [Array, Object],
  opsJob: Object,
  type: String,
  showModal: Boolean,
  userOptions: [Array, Object],
})

const emit = defineEmits(['modalClose'])

const form = ref(
  useForm(getDefaultForm())
)
const operatorOptions = ref([])

onMounted(() => {
  form.value = props.opsJob ? useForm(props.opsJob) : useForm(getDefaultForm())
  operatorOptions.value = [
    ...props.operatorOptions.data.map((data) => {return {id: data.id, full_name: data.full_name}})
  ]
  form.value.operator_id = authOperator ? operatorOptions.value.find(operator => operator.id === authOperator.id) : operatorOptions.value[0]
})

function getDefaultForm() {
  return {
    name: '',
    date: moment().add(1, 'day').format('YYYY-MM-DD'),
    delivered_by: '',
    operator_id: '',
  }
}

function submit() {
  form.value.clearErrors()

  form.value
  .transform((data) => {
    return {
      ...data,
      delivered_by: data.delivered_by.id,
      operator_id: data.operator_id.id,
    }
  })
  .post('/ops-jobs/store', {
    onSuccess: () => {
      emit('modalClose')
    },
    preserveState: true,
    replace: true,
  })
}

</script>