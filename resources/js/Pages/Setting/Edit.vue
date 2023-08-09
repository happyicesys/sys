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
            <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6 pb-2">
              <div class="sm:col-span-6">
                <FormInput v-model="form.code" :error="form.errors.code" required="true" :disabled="vend.code">
                  Code
                </FormInput>
              </div>
              <div class="sm:col-span-6">
                <FormInput v-model="form.name" :error="form.errors.name" :disabled="vend.customer_code && vend.customer_name">
                  Name
                </FormInput>
              </div>
              <div class="sm:col-span-6">
                <DatePicker v-model="form.begin_date" :error="form.errors.begin_date" @input="onDateFromChanged()"
                v-if="permissions.includes('update vends')">
                  Begin Date (Default is the Creation/ First Invoice Date)
                </DatePicker>
              </div>
              <div class="sm:col-span-6">
                <DatePicker v-model="form.termination_date" :error="form.errors.termination_date" :minDate="form.begin_date"
                v-if="permissions.includes('update vends')">
                  Termination Date (Default is the Unbinding Date from CMS, status change)
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
    operatorOptions: Object,
    vend: Object,
    type: String,
  })

  const booleanOptions = ref([])
  const form = ref(
    useForm(getDefaultForm())
  )
  const loading = ref(false)
  const locationTypeOptions = ref([])
  const operatorOptions = ref([])
  const typeName = ref('')
  const operatorCountry = usePage().props.auth.operatorCountry
  const operatorRole = usePage().props.auth.operatorRole
  const permissions = usePage().props.auth.permissions
  const now = ref(moment().format('HH:mm:ss'))

onMounted(() => {
console.log(props.vend)
    if(props.type == 'create') {
        typeName.value = 'Create New'
    } else {
        typeName.value = 'Edit'
    }
    operatorOptions.value = [
        {id: 'all', full_name: 'All'},
        ...props.operatorOptions.data.map((data) => {return {id: data.id, full_name: data.full_name}})
    ]

    form.value = props.vend ? useForm(props.vend) : useForm(getDefaultForm())
    form.value.name = props.vend.customer_code ?
                      props.vend.customer_code + '    ' + props.vend.customer_name :
                      props.vend.name
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
</script>