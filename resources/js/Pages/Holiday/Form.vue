<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600" v-if="props.holiday">
            Editing
          </span>
          <span v-if="props.holiday">
            {{ props.holiday.name }}
          </span>
          <span class="text-gray-600" v-else>
            Create New Holiday
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
              <DatePicker v-model="form.date_from" :error="form.errors.date_from" @input="onDateFromChanged()">
                Date From
              </DatePicker>
            </div>
            <div class="sm:col-span-6">
              <DatePicker v-model="form.date_to" :error="form.errors.date_to" :minDate="form.date_from">
                Date To
              </DatePicker>
            </div>
            <div class="sm:col-span-6">
              <FormTextarea v-model="form.desc" :error="form.errors.desc">
                Desc
              </FormTextarea>
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
import DatePicker from '@/Components/DatePicker.vue';
import FormInput from '@/Components/FormInput.vue';
import FormTextarea from '@/Components/FormTextarea.vue';
import Modal from '@/Components/Modal.vue';
import { ArrowUturnLeftIcon, CheckCircleIcon } from '@heroicons/vue/20/solid';
import { useForm } from '@inertiajs/vue3';
import { ref, onMounted, watch } from 'vue'

const props = defineProps({
  holiday: Object,
  type: String,
  showModal: Boolean,
})

const emit = defineEmits(['modalClose'])

const form = ref(
  useForm(getDefaultForm())
)

onMounted(() => {
  form.value = props.holiday ? useForm(props.holiday) : useForm(getDefaultForm())
  form.value.date_from = moment().format('YYYY-MM-DD')
  form.value.date_to = moment().format('YYYY-MM-DD')
})

function getDefaultForm() {
  return {
    name: '',
    date_from: '',
    date_to: '',
    desc: '',
  }
}

function onDateFromChanged() {
  console.log('herer')
  if(form.value.date_from) {
    form.value.date_to = moment(form.value.date_from).format('YYYY-MM-DD')
  }
}

function submit() {
  form.value.clearErrors()

  if(props.type === 'create') {
    form.value
    .post('/holidays/create', {
      onSuccess: () => {
        emit('modalClose')
      },
      preserveState: true,
      replace: true,
    })
  }

  if(props.type === 'update') {
    form.value
      .post('/holidays/' + form.value.id + '/update', {
      onSuccess: () => {
        emit('modalClose')
      },
      preserveState: true,
      replace: true,
    })
  }
}

</script>