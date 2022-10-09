<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600" v-if="props.simcard">
            Editing
          </span>
          <span v-if="props.simcard">
            {{ props.simcard.name }}
          </span>
          <span class="text-gray-600" v-else>
            Create New Simcard
          </span>
        </div>
      </template>
      <template #default>
        <form @submit.prevent="submit" id="submit">
          <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">
            <div class="sm:col-span-6">
              <FormInput v-model="form.code" :error="form.errors.code" required="true">
                Simcard Number
              </FormInput>
            </div>
            <div class="sm:col-span-6">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Telco
              </label>
              <MultiSelect
                v-model="form.telco_id"
                :options="telcoOptions"
                trackBy="id"
                valueProp="id"
                label="name"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.telco_id">
                {{ form.errors.telco_id }}
              </div>
            </div>
            <div class="sm:col-span-6">
              <FormInput v-model="form.code" :error="form.errors.code" required="true">
                Simcard Number
              </FormInput>
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
import { ArrowUturnLeftIcon, CheckCircleIcon } from '@heroicons/vue/20/solid';
import { useForm } from '@inertiajs/inertia-vue3';
import { ref, onMounted } from 'vue'

const props = defineProps({
  simcard: Object,
  telcos: Object,
  type: String,
  showModal: Boolean,
})

const emit = defineEmits(['modalClose'])

const form = ref(
  useForm(getDefaultForm())
)
const telcoOptions = ref([])

onMounted(() => {
  form.value = props.simcard ? useForm(props.simcard) : useForm(getDefaultForm())
  telcoOptions.value = props.telcos.data.map((data) => {return {id: data.id, name: data.name}})
})

function getDefaultForm() {
  return {
    code: '',
    phone_number: '',
    telco_id: '',
  }
}

function submit() {
  form.value.clearErrors()

  if(props.type === 'create') {
    form.value
    .post('/simcards/create', {
      onSuccess: () => {
        emit('modalClose')
      },
      preserveState: true,
      replace: true,
    })
  }

  if(props.type === 'update') {
    form.value
      .post('/simcards/' + form.value.id + '/update', {
      onSuccess: () => {
        emit('modalClose')
      },
      preserveState: true,
      replace: true,
    })
  }
}

</script>