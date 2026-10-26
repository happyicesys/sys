<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600" v-if="props.modemType">
            {{ $t('Editing') }}
          </span>
          <span v-if="props.modemType">
            {{ props.modemType.name }}
          </span>
          <span class="text-gray-600" v-else>
            {{ $t('Create New Modem Model') }}
          </span>
        </div>
      </template>
      <template #default>
        <form @submit.prevent="submit" id="submit">
          <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">
            <div class="sm:col-span-6">
              <FormInput v-model="form.name" :error="form.errors.name" required="true">
                {{ $t('Name') }}
              </FormInput>
            </div>
            <div class="sm:col-span-6">
              <FormInput v-model="form.alias" :error="form.errors.alias">
                {{ $t('Alias (short name)') }}
              </FormInput>
            </div>
            <div class="sm:col-span-6">
              <FormTextArea v-model="form.desc" :error="form.errors.desc">
                {{ $t('Desc') }}
              </FormTextArea>
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
                  {{ $t('Back') }}
                </span>
              </Button>
              <Button type="submit" class="bg-green-500 hover:bg-green-600 text-white flex space-x-1">
                <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                <span>
                  {{ $t('Save') }}
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
import FormTextArea from '@/Components/FormTextArea.vue';
import Modal from '@/Components/Modal.vue';
import { ArrowUturnLeftIcon, CheckCircleIcon } from '@heroicons/vue/20/solid';
import { useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue'
import { useToast } from "vue-toastification";
import { t } from '@/i18n';

const props = defineProps({
  modemType: Object,
  type: String,
  showModal: Boolean,
})

const emit = defineEmits(['modalClose'])

const form = ref(
  useForm(getDefaultForm())
)
const toast = useToast()

onMounted(() => {
  form.value = props.modemType ? useForm(props.modemType) : useForm(getDefaultForm())
})

function getDefaultForm() {
  return {
    name: '',
    alias: '',
    desc: '',
  }
}

function submit() {
  form.value.clearErrors()

  if(props.type === 'create') {
    form.value
    .post('/modem-types/store', {
      onSuccess: () => {
        toast.success(t('Modem type created successfully'), { timeout: 3000 })
        emit('modalClose')
      },
      onError: () => {
        toast.error(t('Failed to create modem type'), { timeout: 3000 })
      },
      preserveState: true,
      replace: true,
    })
  }

  if(props.type === 'update') {
    form.value
      .post('/modem-types/' + form.value.id + '/update', {
      onSuccess: () => {
        toast.success(t('Modem type updated successfully'), { timeout: 3000 })
        emit('modalClose')
      },
      onError: () => {
        toast.error(t('Failed to update modem type'), { timeout: 3000 })
      },
      preserveState: true,
      replace: true,
    })
  }
}

</script>