<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600" v-if="props.uom">
            {{ $t('Editing') }}
          </span>
          <span v-if="props.uom">
            {{ props.uom.name }}
          </span>
          <span class="text-gray-600" v-else>
            {{ $t('Create New UOM') }}
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
              <FormInput v-model="form.color" :error="form.errors.color">
                {{ $t('Hex Color') }}
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
import Modal from '@/Components/Modal.vue';
import { ArrowUturnLeftIcon, CheckCircleIcon } from '@heroicons/vue/20/solid';
import { useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue'
import { useToast } from "vue-toastification";
import { t } from '@/i18n';

const props = defineProps({
  uom: Object,
  type: String,
  showModal: Boolean,
})

const emit = defineEmits(['modalClose'])

const form = ref(
  useForm(getDefaultForm())
)
const toast = useToast()

onMounted(() => {
  form.value = props.uom ? useForm(props.uom) : useForm(getDefaultForm())
})

function getDefaultForm() {
  return {
    name: '',
    color: '',
  }
}

function submit() {
  form.value.clearErrors()

  if(props.type === 'create') {
    form.value
    .post('/uoms/create', {
      onSuccess: () => {
        toast.success(t('UOM created successfully'), { timeout: 3000 })
        emit('modalClose')
      },
      onError: () => {
        toast.error(t('Failed to create UOM'), { timeout: 3000 })
      },
      preserveState: true,
      replace: true,
    })
  }

  if(props.type === 'update') {
    form.value
      .post('/uoms/' + form.value.id + '/update', {
      onSuccess: () => {
        toast.success(t('UOM updated successfully'), { timeout: 3000 })
        emit('modalClose')
      },
      onError: () => {
        toast.error(t('Failed to update UOM'), { timeout: 3000 })
      },
      preserveState: true,
      replace: true,
    })
  }
}

</script>