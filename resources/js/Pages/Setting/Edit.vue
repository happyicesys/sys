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
      <span>
        <div
          class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-fit"
          :class="[vend.is_active ? 'bg-green-200' : 'bg-red-200']"
          v-if="vend.is_active != null"
        >
          <div class="flex flex-col">
            <span class="font-bold">
              {{ vend.is_active ? 'Active' : 'Inactive'}}
            </span>
          </div>
        </div>
      </span>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
        <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll p-5">
          <form @submit.prevent="submit" id="submit">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-6 pb-2">
              <div class="sm:col-span-6">
                <FormInput v-model="form.code" :error="form.errors.code" required="true" :disabled="vend.code" v-if="type == 'update'">
                  Code
                </FormInput>
                <SearchVendCodeInput v-model="form.code" @selected="onVendCodeSelected" required="true" :error="form.errors.code" v-if="type == 'create'">
                  Code
                </SearchVendCodeInput>
              </div>
              <div class="sm:col-span-6">
                <div class="relative flex items-start">
                  <div class="flex h-6 items-center">
                    <input
                      aria-describedby="is_customer"
                      v-model="form.is_customer"
                      :disabled="vend.customer_id && type == 'update'"
                      name="is_customer" type="checkbox"
                      class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
                      :class="[vend.customer_id && type == 'update' ? 'bg-gray-200 hover:cursor-not-allowed' : '']"
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
                <FormInput v-model="form.name" :error="form.errors.name">
                  Name
                </FormInput>
              </div>
              <div class="sm:col-span-6">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Operator
                </label>
                <MultiSelect
                  v-model="form.operator_id"
                  :options="operatorOptions"
                  trackBy="id"
                  valueProp="id"
                  label="full_name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
              </div>
              <div class="sm:col-span-3">
                <DatePicker v-model="form.begin_date" :error="form.errors.begin_date" @input="onDateFromChanged()"
                v-if="permissions.includes('update vends')">
                  Begin Date
                </DatePicker>
              </div>
              <!-- <div class="sm:col-span-3">
                <DatePicker v-model="form.termination_date" :error="form.errors.termination_date" :minDate="form.begin_date"
                v-if="permissions.includes('update vends')">
                  Termination Date
                </DatePicker>
              </div> -->
              <div class="sm:col-span-6">
                <FormInput v-model="form.private_key" :error="form.errors.private_key" :disabled="!permissions.includes('update vends')">
                  Private Key
                </FormInput>
              </div>
            </div>
            <div class="sm:col-span-6">
              <div class="flex flex-col space-y-1 sm:flex-row sm:space-x-1 sm:space-y-0 mt-5 justify-end">
                <Link href="/settings" class="bg-gray-300 hover:bg-gray-400 text-gray-700 rounded">
                  <Button
                   class="space-x-1"
                  >
                    <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
                    <span>
                      Back
                    </span>
                  </Button>
                </Link>
                <Button
                  type="button"
                  class="bg-yellow-500 hover:bg-yellow-600 text-white flex space-x-1"
                  v-if="vend.customer_id && permissions.includes('update vends')"
                  @click="unbindCustomer(form.id)"
                >
                  <ArrowUturnDownIcon class="w-4 h-4"></ArrowUturnDownIcon>
                  <span>
                    Unbind Customer
                  </span>
                </Button>
                <Button
                  type="button"
                  class="text-white flex space-x-1"
                  :class="[!vend.is_active ? 'bg-green-500 hover:bg-green-600' : 'bg-yellow-500 hover:bg-yellow-600']"
                  v-if="permissions.includes('update vends') && vend.is_active != null"
                  @click="toggleActivation()"
                >
                  <span class="flex" v-if="!vend.is_active">
                    <PlayIcon class="w-4 h-4 pt-1"></PlayIcon>Activate
                  </span>
                  <span class="flex" v-if="vend.is_active">
                    <PauseCircleIcon class="w-4 h-4 pt-1"></PauseCircleIcon>Deactivate
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
import SearchVendCodeInput from '@/Components/SearchVendCodeInput.vue';
import { ArrowUturnDownIcon, ArrowUturnLeftIcon, CheckCircleIcon, PauseCircleIcon, PlayIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import moment from 'moment';

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

    if(props.type == 'create') {
        typeName.value = 'Create New'
    } else {
        typeName.value = 'Edit'
    }
    adminCustomerOptions.value = props.adminCustomerOptions.map((data) => {return {id: data.id, full_name: data.cust_id + ' - ' + data.company}})
    operatorOptions.value = [
        ...props.operatorOptions.data.map((data) => {return {id: data.id, full_name: data.full_name}})
    ]

    form.value = props.vend && props.vend.id ? useForm(props.vend) : useForm(getDefaultForm())
    // console.log(JSON.parse(JSON.stringify(form.value)))
    form.value.name = props.vend.name
    if(props.vend.customer_id) {
        form.value.is_customer = true
        form.value.customer_id = {
          id: props.vend.person_id,
          full_name: props.vend.customer_code + ' - ' + props.vend.customer_name
        }
    }
    if(props.vend.operator_id) {
        form.value.operator_id = {
          id: props.vend.operator_id,
          full_name: props.vend.operator_name
        }
    }
})

function getDefaultForm() {
  return {
    code: '',
    name: '',
    begin_date: moment().format('YYYY-MM-DD'),
    customer_id: '',
    operator_id: '',
    serial_num: '',
    termination_date: '',
    private_key: '',
  }
}

function onVendCodeSelected(vend) {
  form.value.code = vend.code
}

function submit() {
  form.value.clearErrors()
  if(props.type === 'create') {
    form.value
    .transform((data) => ({
      ...data,
      customer_id: data.customer_id ? data.customer_id.id : null,
      operator_id: data.operator_id ? data.operator_id.id : null,
    }))
    .post('/vends/create', {
      preserveState: true,
      replace: true,
    })
  }

  if(props.type === 'update') {
    form.value
      .transform((data) => ({
        ...data,
        customer_id: data.customer_id ? data.customer_id.id : null,
        operator_id: data.operator_id ? data.operator_id.id : null,
      }))
      .post('/vends/' + form.value.id + '/update', {
      preserveState: true,
      replace: true,
    })
  }
}

function unbindCustomer(vendId) {
  form.value
      .post('/vends/' + vendId + '/unbind', {
        onSuccess: () => {
          form.value.is_customer = false
          form.value.customer_id = ''
          form.value.begin_date = ''
        },
        preserveState: true,
        replace: true,
      })
}

function toggleActivation()
{
  form.value
    .post('/settings/' + form.value.id + '/toggle-activation', {
    })
}
</script>