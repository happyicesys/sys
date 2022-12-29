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

            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3" v-if="form.id">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center">
                  <span class="px-3 bg-white text-lg font-medium text-gray-900"> Access Vending Machine(s) </span>
                </div>
              </div>
            </div>

            <div class="sm:col-span-5" v-if="form.id">
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

            <div class="sm:col-span-1" v-if="form.id">
              <Button
              type="button"
              @click="bindOperatorVend()"
              class="bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6"
              :class="[!form.vend_id ? 'opacity-50 cursor-not-allowed' : '']"
              :disabled="!form.vend_id"
              >
                <PlusCircleIcon class="w-4 h-4"></PlusCircleIcon>
                <span>
                  Add
                </span>
              </Button>
            </div>

            <div class="sm:col-span-6 flex flex-col mt-3" v-if="form.id">
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
                          Vend ID
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
                      <tr v-for="(vend, vendIndex) in operator.vends" :key="vend.id" :class="vendIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                          {{ vendIndex + 1 }}
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                          {{ vend.code }}
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                          <span v-if="vend.latestVendBinding && vend.latestVendBinding.customer">
                            {{ vend.latestVendBinding.customer.code }} <br>
                            {{ vend.latestVendBinding.customer.name }}
                          </span>
                          <span v-else>
                            {{ vend.name }}
                          </span>
                        </td>
                        <td class="whitespace-nowrap py-4 text-sm text-center">
                          <Button
                            class="bg-red-400 hover:bg-red-500 text-white"
                            @click="unbindOperatorVend(vend)"
                          >
                            <BackspaceIcon class="w-4 h-4"></BackspaceIcon>
                          </Button>
                        </td>
                      </tr>
                      <tr v-if="!operator.vends.length">
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
import { ArrowUturnLeftIcon, BackspaceIcon, CheckCircleIcon, PlusCircleIcon } from '@heroicons/vue/20/solid';
import { useForm } from '@inertiajs/inertia-vue3';
import { ref, onMounted } from 'vue'
// import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  countries: [Array, Object],
  operator: Object,
  timezones: [Array, Object],
  type: String,
  showModal: Boolean,
  unbindedVends: [Array, Object],
})

const emit = defineEmits(['modalClose', 'refreshData'])

const countryOptions = ref([])
const form = ref(
  useForm(getDefaultForm())
)
const timezoneOptions = ref([])
const unbindedVendOptions = ref([])

onMounted(() => {
  countryOptions.value = props.countries.data
  timezoneOptions.value = props.timezones.map((timezone, index) => {return {id: index, name: timezone}})
  // unbindedVendOptions.value = props.unbindedVends.data.map((vend) => {return {id: vend.id, code: vend.code, fullname: vend.full_name}})
  unbindedVendOptions.value = props.unbindedVends.data
  form.value = props.operator ? useForm(props.operator) : useForm(getDefaultForm())
})

function getDefaultForm() {
  return {
    id: '',
    code: '',
    name: '',
    country_id: '',
    timezone: '',
    remarks: '',
    vend_id: '',
  }
}

function bindOperatorVend() {
  if(props.operator.vends.indexOf(form.value.vend_id) < 0) {
    props.operator.vends.push(form.value.vend_id)
    props.operator.vends.sort((a, b) => a.code - b.code)
    unbindedVendOptions.value.splice(unbindedVendOptions.value.indexOf(form.value.vend_id), 1)
    unbindedVendOptions.value.sort((a, b) => a.code - b.code)
  }

  // Inertia.post('/operators/bind-vend',
  // {
  //   operator_id: form.value.id,
  //   vend_id: form.value.vend_id.id,
  // },
  // {
  //   preserveState: true,
  //   replace: true,
  //   onSuccess: (page) => {
  //     emit('refreshData', form.value.id)
  //   },
  // })
}

function unbindOperatorVend(vend) {
  props.operator.vends.splice(props.operator.vends.indexOf(vend), 1)
  unbindedVendOptions.value.push(vend)
  unbindedVendOptions.value.sort((a, b) => a.code - b.code)
  // Inertia.post('/operators/unbind-vend',
  // {
  //   operator_id: form.value.id,
  //   vend_id: vendId,
  // },
  // {
  //   preserveState: true,
  //   replace: true,
  //   onSuccess: (page) => {
  //     Inertia.reload({ only: ['operator'] })
  //     emit('refreshData', form.value.id)
  //   },
  // })
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
        operator: props.operator,
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