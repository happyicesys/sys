<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600" v-if="props.vendCriteria">
            Editing
          </span>
          <span>
            {{ props.vendCriteria.name }}
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
              <FormInput v-model="form.weightage" :error="form.errors.weightage" required="true">
                Weightage (%)
              </FormInput>
            </div>
            <div class="sm:col-span-6">
              <hr>
            </div>
            <div class="sm:col-span-6">
              <h2 class="text-base font-semibold leading-7 text-gray-900">Settings</h2>
              <p class="mt-1 text-sm leading-6 text-gray-600">{{ form.options_json[form.value] }}</p>
            </div>
            <div class="sm:col-span-6">
              <FormInput v-if="form.value2" v-model="form.value2" :error="form.errors.value2" required="true">
                Value
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
import MultiSelect from '@/Components/MultiSelect.vue';
import { ArrowUturnLeftIcon, CheckCircleIcon } from '@heroicons/vue/20/solid';
import { useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue'

const props = defineProps({
  vendCriteria: Object,
  type: String,
  showModal: Boolean,
})

const emit = defineEmits(['modalClose'])

const form = ref(
  useForm(getDefaultForm())
)

onMounted(() => {
  form.value = props.vendCriteria ? useForm(props.vendCriteria) : useForm(getDefaultForm())
})

function getDefaultForm() {
  return {
    name: '',
    value: '',
  }
}

function submit() {
  form.value.clearErrors()

  if(props.type === 'update') {
    form.value
      .post('/vend-criterias/' + form.value.id + '/update', {
      onSuccess: () => {
        emit('modalClose')
      },
      preserveState: true,
      replace: true,
    })
  }
}

</script>