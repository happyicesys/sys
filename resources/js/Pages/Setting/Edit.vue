<template>

  <Head title="VM Management" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ typeName }} Vending Machine
        <span v-if="type == 'update'">
          {{ vend.code }}
        </span>
        <span v-if="vend.customer_name">
          <br>
          {{ vend.customer_code }} - {{ vend.customer_name }}
        </span>
        <span v-else-if="!vend.customer_name && vend.name">
          <br>
          {{ vend.name }}
        </span>
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
        <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll p-5">
          <form @submit.prevent="submit" id="submit">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-6 pb-2">
              <div class="sm:col-span-6">
                <FormInput v-model="form.code" :error="form.errors.code" required="true" :disabled="vend.code">
                  Code
                </FormInput>
              </div>
              <div class="sm:col-span-6">
                <div class="relative flex items-start">
                  <div class="flex h-6 items-center">
                    <input
                      aria-describedby="is_customer"
                      v-model="form.is_customer"
                      @change="onIsCustomerChecked()"
                      :disabled="form.is_customer && type == 'update'"
                      name="is_customer" type="checkbox"
                      class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                      :class="[form.is_customer && type == 'update' ? 'bg-gray-200 hover:cursor-not-allowed' : '']"
                    />
                  </div>
                  <div class="ml-3 text-sm leading-6">
                    <label for="is_customer" class="font-medium text-gray-900">Customer Binding?</label>
                    {{ ' ' }}
                    <span id="is_customer" class="text-gray-500"><span class="sr-only">Customer Binding?</span>retrieve customer data from cms</span>
                  </div>
                </div>
              </div>
              <div class="sm:col-span-6" v-if="form.is_customer">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Customer
                </label>
                <MultiSelect
                  v-model="form.customer_id"
                  :options="adminCustomerOptions"
                  trackBy="id"
                  valueProp="id"
                  label="full_name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
              </div>
              <div class="sm:col-span-6" v-if="!form.is_customer">
                <FormInput v-model="form.name" :error="form.errors.name" :disabled="vend.customer_code && vend.customer_name">
                  Name
                </FormInput>
              </div>
              <div class="sm:col-span-3">
                <DatePicker v-model="form.begin_date" :error="form.errors.begin_date" @input="onDateFromChanged()"
                v-if="permissions.includes('update vends')">
                  Begin Date
                </DatePicker>
              </div>
              <div class="sm:col-span-3">
                <DatePicker v-model="form.termination_date" :error="form.errors.termination_date" :minDate="form.begin_date"
                v-if="permissions.includes('update vends')">
                  Termination Date
                </DatePicker>
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
        </div>
      </div>
    </div>
  </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import DatePicker from '@/Components/DatePicker.vue';
import FormInput from '@/Components/FormInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { ArrowUturnDownIcon, ArrowUturnLeftIcon, CheckCircleIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    adminCustomerOptions: Object,
    operatorOptions: Object,
    vend: Object,
    type: String,
  })

  const adminCustomerOptions = ref([])
  const booleanOptions = ref([])
  const form = ref(
    useForm(getDefaultForm())
  )
  const loading = ref(false)
  const operatorOptions = ref([])
  const typeName = ref('')
  const operatorCountry = usePage().props.auth.operatorCountry
  const operatorRole = usePage().props.auth.operatorRole
  const permissions = usePage().props.auth.permissions
  const now = ref(moment().format('HH:mm:ss'))

onMounted(() => {
  console.log(JSON.parse(JSON.stringify(props.vend)))
    if(props.type == 'create') {
        typeName.value = 'Create New'
    } else {
        typeName.value = 'Edit'
    }
    adminCustomerOptions.value = props.adminCustomerOptions.map((data) => {return {id: data.id, full_name: data.cust_id + ' - ' + data.company}})
    operatorOptions.value = [
        {id: 'all', full_name: 'All'},
        ...props.operatorOptions.data.map((data) => {return {id: data.id, full_name: data.full_name}})
    ]

    form.value = props.vend ? useForm(props.vend) : useForm(getDefaultForm())
    form.value.name = props.vend.name
    if(props.vend.customer_id) {
        form.value.is_customer = true
        form.value.customer_id = {
          'id': props.vend.customer_id,
          'full_name': props.vend.customer_code + ' - ' + props.vend.customer_name
        }
    }
})

function getDefaultForm() {
  return {
    name: '',
    begin_date: '',
    serial_num: '',
    termination_date: '',
    private_key: '',
  }
}

function onIsCustomerChecked() {
  if(form.value.is_customer) {

  }
}
</script>