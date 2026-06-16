<template>
  <Head title="VM Edit" />
  <BreezeAuthenticatedLayout>
    <template #header >
        <div class="flex md:flex-row space-x-1">
          <span class="text-gray-600">
            Editing
          </span>
          <span v-if="user">
            {{ user.name }}
          </span>
        </div>
      </template>
    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
        <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll p-5">
          <form @submit.prevent="submit" id="submit">
            <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">
              <div class="col-span-12 sm:col-span-6 flex space-x-1">
                <div
                    class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit"
                    :class="[user.is_active ? 'bg-green-300' : 'bg-red-300']"
                    v-if="user"
                >
                  <span v-if="user.is_active">
                    Active
                  </span>
                  <span v-if="!user.is_active">
                    Not Active
                  </span>
                </div>
              </div>
              <div class="col-span-12 sm:col-span-4">
                <FormInput v-model="form.name" :error="form.errors.name" required="true">
                  Name
                </FormInput>
              </div>
              <div class="col-span-12 sm:col-span-2">
                <FormInput v-model="form.alias" :error="form.errors.alias">
                  Alias
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
              <div class="col-span-12 sm:col-span-3">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Phone Code
                </label>
                <MultiSelect
                  v-model="form.phone_country_id"
                  :options="countryOptions"
                  trackBy="id"
                  valueProp="id"
                  label="country_name_phone_code"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.phone_country_id">
                  {{ form.errors.phone_country_id }}
                </div>
              </div>
              <div class="col-span-12 sm:col-span-3">
                <FormInput v-model="form.phone_number" :error="form.errors.phone_number">
                  Phone Number
                </FormInput>
              </div>
              <div class="col-span-12 sm:col-span-6" v-if="!operatorRole">
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
                  open-direction="top"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.operator_id">
                  {{ form.errors.operator_id }}
                </div>
              </div>

              <div class="col-span-12 sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3" v-if="form.id">
                <div class="relative">
                  <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-gray-300"></div>
                  </div>
                  <div class="relative flex justify-center">
                    <span class="px-3 bg-white text-lg font-medium text-gray-900"> Access Vending Machine(s) </span>
                  </div>
                </div>
              </div>

              <div class="col-span-12 sm:col-span-5" v-if="form.id">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Vending Machine to Bind
                </label>
                <MultiSelect
                  v-model="form.vend_id"
                  :options="unbindedVendOptions"
                  trackBy="id"
                  valueProp="id"
                  label="full_name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                  ref="multiselect"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.vend_id">
                  {{ form.errors.vend_id }}
                </div>
              </div>

              <div class=" col-span-12 sm:col-span-1" v-if="form.id">
                <Button
                type="button"
                @click="bindOperatorVend()"
                class="bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6"
                :class="[!form.vend_id ? 'opacity-50 cursor-not-allowed' : '']"
                :disabled="!form.vend_id && !permissions.includes('update operators')"
                >
                  <PlusCircleIcon class="w-4 h-4"></PlusCircleIcon>
                  <span>
                    Add
                  </span>
                </Button>
              </div>

              <div class="col-span-12 sm:col-span-6 flex flex-col mt-3" v-if="form.id">
              <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5">
                <div class="inline-block min-w-full py-2 align-middle md:px-4 lg:px-6">
                  <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-300">
                      <thead class="bg-gray-50">
                        <tr>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            #
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Machine ID
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Name
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Action
                          </th>
                        </tr>
                      </thead>
                      <tbody class="bg-white">
                        <tr v-for="(vend, vendIndex) in user.vends" :key="vend.id" :class="vendIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                            {{ vendIndex + 1 }}
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                            {{ vend.code }}
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                            <span v-if="vend.customer && vend.customer.person_id">
                              {{ vend.customer.virtual_customer_prefix }}-{{ vend.customer.virtual_customer_code }} <br>
                              {{ vend.customer.name }}
                            </span>
                            <span v-if="vend.customer">
                              {{ vend.customer.name }}
                            </span>
                          </td>
                          <td class="whitespace-nowrap py-4 text-sm text-center">
                            <Button
                              class="bg-red-400 hover:bg-red-500 text-white"
                              @click="unbindOperatorVend(vend)"
                              v-if="permissions.includes('update operators')"
                            >
                              <BackspaceIcon class="w-4 h-4"></BackspaceIcon>
                            </Button>
                          </td>
                        </tr>
                        <tr v-if="!user.vends.length">
                          <td colspan="4" class="whitespace-nowrap py-4 text-sm font-medium text-red-600 text-center">
                            No Binding = Access to All
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
              </div>
            </div>
            <div class="col-span-12 sm:col-span-6">
              <div class="flex justify-between mt-5">
                <Button type="button" v-if="permissions.includes('admin-access users')" @click="toggleActivateDeactivate" class="text-white" :class="[form.is_active ? 'bg-red-500 hover:bg-red-600' : 'bg-green-500 hover:bg-green-600']">
                  <div>
                    <span class="flex space-x-1 items-center" v-if="form.is_active">
                      <FolderMinusIcon class="w-4 h-4"></FolderMinusIcon>
                      <span>
                        Deactivate
                      </span>
                    </span>
                    <span class="flex space-x-1 items-center" v-else>
                      <FolderPlusIcon class="w-4 h-4"></FolderPlusIcon>
                      <span>
                        Activate
                      </span>
                    </span>
                  </div>
                </Button>
                <div class="flex space-x-1 justify-end">
                  <Link :href="'/users'">
                    <Button
                      type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1"
                    >
                      <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
                      <span>
                        Back
                      </span>
                    </Button>
                  </Link>
                  <Button type="submit" class="bg-green-500 hover:bg-green-600 text-white flex space-x-1">
                    <!-- @click.prevent="submit" -->
                    <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                    <span>
                      Save
                    </span>
                  </Button>
                </div>
              </div>
            </div>
        </form>
        </div>
      </div>
    </div>
  </div>

  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import FormInput from '@/Components/FormInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { ArrowUturnLeftIcon, BackspaceIcon, CheckCircleIcon, FolderPlusIcon, FolderMinusIcon, PlusCircleIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted } from 'vue'
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
  user: Object,
  countries: Object,
  operators: Object,
  permissions: [Array, Object],
  roles: Object,
  type: String,
  showModal: Boolean,
  unbindedVends: [Array, Object],
})

const emit = defineEmits(['modalClose'])

const countryOptions = ref([])
const form = ref(
  useForm(getDefaultForm())
)
const operatorOptions = ref([])
const roleOptions = ref([])
const operatorRole = usePage().props.auth.operatorRole
const permissions = usePage().props.auth.permissions
const unbindedVendOptions = ref([])
const user = ref()

onMounted(() => {
  countryOptions.value = props.countries.data
  user.value = props.user.data
  operatorOptions.value = props.operators.data.map(operator => ({
    id: operator.id,
    name: operator.name
  }))
  roleOptions.value = JSON.parse(JSON.stringify(props.roles.data))
  unbindedVendOptions.value = props.unbindedVends.data
  console.log(unbindedVendOptions.value)
  form.value = props.user ? useForm({
    ...getDefaultForm(),
    phone_country_id: props.user ? countryOptions.value.find(country => country.id == user.value.phone_country_id) : '',
    ...props.user.data,
    operator_id: props.user ? operatorOptions.value.find(operator => operator.id == user.value.operator_id) : '',
  }) : useForm(getDefaultForm())
  if(!usePage().props.auth.permissions.includes('admin-access operators')) {
    roleOptions.value = props.roles.data.filter(function(role) {
      return ['operator', 'operator_user', 'operator_admin', 'operator_viewer', 'operator_supervisor', 'operator_driver', 'operator_3pl'].includes(role.name)
    })
  }
})

function bindOperatorVend() {
  if(user.value.vends.indexOf(form.value.vend_id) < 0) {
    user.value.vends.push(form.value.vend_id)
    user.value.vends.sort((a, b) => a.code - b.code)
    unbindedVendOptions.value.splice(unbindedVendOptions.value.indexOf(form.value.vend_id), 1)
    unbindedVendOptions.value.sort((a, b) => a.code - b.code)
  }
}

function toggleActivateDeactivate() {
  form.value.post('/users/' + form.value.id + '/toggle-activate-deactivate', {
    onSuccess: () => {
      emit('modalClose');
    },
    preserveState: true,
    replace: true,
  });
}

function unbindOperatorVend(vend) {
  user.value.vends.splice(user.value.vends.indexOf(vend), 1)
  unbindedVendOptions.value.push(vend)
  unbindedVendOptions.value.sort((a, b) => a.code - b.code)
}

function getDefaultForm() {
  return {
    name: '',
    alias: '',
    email: '',
    is_active: '',
    username: '',
    password: '',
    phone_country_id: '',
    phone_number: '',
    operator_id: '',
    role_id: '',
  }
}

function submit() {
  form.value.clearErrors()

  if(props.type === 'update') {
    form.value
      .transform((data) => ({
        ...data,
        phone_country_id: data.phone_country_id ? data.phone_country_id.id : null,
        operator_id: data.operator_id.id,
        role_id: data.role_id.id,
        user: props.user,
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