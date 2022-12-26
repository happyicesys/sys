<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600" v-if="props.operator">
            Editing
          </span>
          <span v-if="props.operator">
            {{ props.operator.name }}
          </span>
          <span class="text-gray-600" v-else>
            Create New Operator
          </span>
        </div>
      </template>
      <template #default>
        <form @submit.prevent="submit" id="submit">
          <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">
            <div class="sm:col-span-2">
              <FormInput v-model="form.code" :error="form.errors.code">
                Code
              </FormInput>
            </div>
            <div class="sm:col-span-4">
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
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.country_id">
                {{ form.errors.country_id }}
              </div>
            </div>
            <div class="sm:col-span-6">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Timezone
              </label>
              <MultiSelect
                v-model="form.timezone"
                :options="timezoneOptions"
                trackBy="id"
                valueProp="id"
                label="name"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.timezone">
                {{ form.errors.timezone }}
              </div>
            </div>
            <div class="sm:col-span-6">
              <FormTextarea v-model="form.remarks" :error="form.errors.remarks">
                Remarks
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
import FormInput from '@/Components/FormInput.vue';
import FormTextarea from '@/Components/FormTextarea.vue';
import Modal from '@/Components/Modal.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { ArrowUturnLeftIcon, CheckCircleIcon } from '@heroicons/vue/20/solid';
import { useForm } from '@inertiajs/inertia-vue3';
import { ref, onMounted } from 'vue'

const props = defineProps({
  countries: [Array, Object],
  operator: Object,
  timezones: [Array, Object],
  type: String,
  showModal: Boolean,
})

const emit = defineEmits(['modalClose'])

const countryOptions = ref([])
const form = ref(
  useForm(getDefaultForm())
)
const timezoneOptions = ref([])

onMounted(() => {
  countryOptions.value = props.countries.data
  timezoneOptions.value = props.timezones.map((timezone, index) => {return {id: index, name: timezone}})
  // console.log(timezoneOptions.value)
  form.value = props.operator ? useForm(props.operator) : useForm(getDefaultForm())
  // console.log(JSON.parse(JSON.stringify(form.value)))
})

function getDefaultForm() {
  return {
    code: '',
    name: '',
    country_id: '',
    timezone: '',
    remarks: '',
  }
}

function submit() {
  form.value.clearErrors()

  if(props.type === 'create') {
    form.value
    .transform((data) => ({
      ...data,
      timezone: data.timezone.name,
      country_id: data.country_id.id,
    }))
    .post('/operators/create', {
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
        timezone: data.timezone.name,
        country_id: data.country_id.id,
      }))
      .post('/operators/' + form.value.id + '/update', {
      onSuccess: () => {
        emit('modalClose')
      },
      preserveState: true,
      replace: true,
    })
  }
}

</script>