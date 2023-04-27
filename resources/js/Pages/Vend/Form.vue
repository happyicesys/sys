<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600">
            Editing
          </span>
          <span v-if="vend">
            {{ vend.code }}
          </span>
        </div>
      </template>
      <template #default>
        <form @submit.prevent="submit" id="submit">
          <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6 pb-2">
            <div class="sm:col-span-6">
              <FormInput v-model="form.name" :error="form.errors.name" required="true" :disabled="vend.latestVendBinding && vend.latestVendBinding.customer">
                Name
              </FormInput>
            </div>
            <div class="sm:col-span-6">
              <FormInput v-model="form.serial_num" :error="form.errors.serial_num" :disabled="!permissions.includes('update vends')">
                Serial Number
              </FormInput>
            </div>
            <div class="sm:col-span-6">
              <FormInput v-model="form.private_key" :error="form.errors.private_key" :disabled="!permissions.includes('update vends')">
                Private Key
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
              <Button
                type="button"
                class="bg-yellow-500 hover:bg-yellow-600 text-white flex space-x-1"
                v-if="vend.latestVendBinding && vend.latestVendBinding.customer && permissions.includes('update vends')"
                @click="unbindCustomer(form.id)"
              >
                <ArrowUturnDownIcon class="w-4 h-4"></ArrowUturnDownIcon>
                <span>
                  Unbind
                </span>
              </Button>
              <Button
                type="submit"
                class="bg-green-500 hover:bg-green-600 text-white flex space-x-1"
                v-if="permissions.includes('update vends')"
              >
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
import { ArrowUturnDownIcon, ArrowUturnLeftIcon, CheckCircleIcon } from '@heroicons/vue/20/solid';
import { useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue'

const props = defineProps({
  vend: Object,
  countries: Object,
  permissions: [Array, Object],
  type: String,
  showModal: Boolean,
})

const emit = defineEmits(['modalClose'])

const form = ref(
  useForm(getDefaultForm())
)

onMounted(() => {
  form.value = props.vend ? useForm(props.vend) : useForm(getDefaultForm())
  form.value.name = props.vend.latestVendBinding ?
                    props.vend.latestVendBinding.customer.code + '    ' + props.vend.latestVendBinding.customer.name :
                    props.vend.name
})

function getDefaultForm() {
  return {
    name: '',
    serial_num: '',
    private_key: '',
  }
}

function submit() {
  form.value.clearErrors()

  form.value
    .post('/vends/' + form.value.id + '/update', {
    onSuccess: () => {
      emit('modalClose')
    },
    preserveState: true,
    replace: true,
  })
}

function unbindCustomer(vendId) {
  form.value
      .post('/vends/' + vendId + '/unbind', {
        onSuccess: () => {
          emit('modalClose')
        },
        preserveState: true,
        replace: true,
      })
}

</script>