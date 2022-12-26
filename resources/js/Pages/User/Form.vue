<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600" v-if="props.user">
            Editing
          </span>
          <span v-if="props.user">
            {{ props.user.name }}
          </span>
          <span class="text-gray-600" v-else>
            Create New User
          </span>
        </div>
      </template>
      <template #default>
        <form @submit.prevent="submit" id="submit">
          <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">
            <div class="col-span-12 sm:col-span-6">
              <FormInput v-model="form.name" :error="form.errors.name" required="true">
                Name
              </FormInput>
            </div>
            <div class="col-span-12 sm:col-span-6">
              <FormInput v-model="form.email" :error="form.errors.email">
                Email
              </FormInput>
            </div>
            <div class="col-span-12 sm:col-span-6">
              <FormInput v-model="form.username" :error="form.errors.username">
                Username
              </FormInput>
            </div>
            <div class="col-span-12 sm:col-span-6">
              <FormInput v-model="form.password" :error="form.errors.password" :placeholderStr="[type == 'update' ? 'Leave blank for same password' : '']" inputType="password" autocomplete="new-password">
                Password {{type == 'update' ? '(Override)' : ''}}
              </FormInput>
            </div>
            <div class="col-span-12 sm:col-span-6">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Role
              </label>
              <MultiSelect
                v-model="form.role_id"
                :options="roleOptions"
                trackBy="id"
                valueProp="id"
                label="name"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
            </div>
            <div class="col-span-12 sm:col-span-6">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Operator
              </label>
              <MultiSelect
                v-model="form.operator_id"
                :options="operatorOptions"
                trackBy="id"
                valueProp="id"
                label="name"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
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
                <!-- @click.prevent="submit" -->
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
import MultiSelect from '@/Components/MultiSelect.vue'
import { ArrowUturnLeftIcon, CheckCircleIcon } from '@heroicons/vue/20/solid';
import { useForm } from '@inertiajs/inertia-vue3';
import { ref, onMounted } from 'vue'

const props = defineProps({
  user: Object,
  countries: Object,
  operators: Object,
  roles: Object,
  type: String,
  showModal: Boolean,
})

const emit = defineEmits(['modalClose'])

const form = ref(
  useForm(getDefaultForm())
)
const operatorOptions = ref([])
const roleOptions = ref([])

onMounted(() => {
  form.value = props.user ? useForm({...getDefaultForm(), ...props.user}) : useForm(getDefaultForm())
  operatorOptions.value = props.operators.data
  roleOptions.value = props.roles
})

function getDefaultForm() {
  return {
    name: '',
    email: '',
    username: '',
    password: '',
    operator_id: '',
  }
}

function submit() {
  form.value.clearErrors()

  if(props.type === 'create') {
    form.value
    .transform((data) => ({
        ...data,
        operator_id: data.operator_id.id,
    }))
    .post('/users/create', {
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
        operator_id: data.operator_id.id,
      }))
      .post('/users/' + form.value.id + '/update', {
      onSuccess: () => {
        emit('modalClose')
      },
      preserveState: true,
      replace: true,
    })
  }
}

</script>