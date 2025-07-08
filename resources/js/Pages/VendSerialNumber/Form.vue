<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600" v-if="props.vendSerialNumber">
            Editing
          </span>
          <span v-if="props.vendSerialNumber">
            {{ props.vendSerialNumber.code }}
          </span>
          <span class="text-gray-600" v-else>
            Create New Serial Number
          </span>
        </div>
      </template>
      <template #default>
        <form @submit.prevent="submit" id="submit">
          <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">
            <div class="sm:col-span-6">
              <FormInput v-model="form.code" :error="form.errors.code" required="true">
                Serial Number
              </FormInput>
            </div>
            <div class="sm:col-span-6">
              <FormTextarea v-model="form.desc" :error="form.errors.desc">
                Remarks
              </FormTextarea>
            </div>
          </div>
          <div class="sm:col-span-6 pt-2">
            <div class="flex justify-between">
              <Button
                type="button"
                class="bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex-col space-y-1 w-fit h-fit"
                :class="[props.vendSerialNumber.vend ? 'opacity-50 cursor-not-allowed' : '']"
                @click="onDeleteClicked(form)"
                :disabled="props.vendSerialNumber.vend"
                v-if="props.vendSerialNumber"
              >
                <span class="flex space-x-1 items-center">
                  <TrashIcon class="w-4 h-4"></TrashIcon>
                  <span>
                      Delete
                  </span>
                </span>
                <span v-if="props.vendSerialNumber.vend">
                  (Binded)
                </span>
              </Button>
              <div class="flex space-x-1 justify-end">
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
import FormTextarea from '@/Components/FormTextarea.vue';
import Modal from '@/Components/Modal.vue';
import { ArrowUturnLeftIcon, CheckCircleIcon, TrashIcon } from '@heroicons/vue/20/solid';
import { useForm, router } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue'

const props = defineProps({
  vendSerialNumber: Object,
  type: String,
  showModal: Boolean,
})

const emit = defineEmits(['modalClose'])

const form = ref(
  useForm(getDefaultForm())
)

onMounted(() => {
  form.value = props.vendSerialNumber ? useForm(props.vendSerialNumber) : useForm(getDefaultForm())
})

function getDefaultForm() {
  return {
    code: '',
    desc: '',
  }
}

function onDeleteClicked(vendSerialNumber) {
  const approval = confirm('Are you sure to delete ' + vendSerialNumber.code + '?');
  if (!approval) {
      return;
  }
  router.delete('/vend-serial-numbers/' + vendSerialNumber.id)
  emit('modalClose')
}

function submit() {
  form.value.clearErrors()

  if(props.type === 'create') {
    form.value
    .transform((data) => {
      return {
        ...data,
      }
    })
    .post('/vend-serial-numbers/store', {
      onSuccess: () => {
        emit('modalClose')
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
      }
    })
      .post('/vend-serial-numbers/' + form.value.id + '/update', {
      onSuccess: () => {
        emit('modalClose')
      },
      preserveState: true,
      replace: true,
    })
  }
}

</script>