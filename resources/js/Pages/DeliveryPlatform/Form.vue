<template>
  <Head title="Delivery Product Mapping" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ typeName }} Delivery Product Mapping
        <span v-if="type == 'update'">
          {{ deliveryProductMapping.name }}
        </span>
      </h2>
    </template>

     <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
        <div class="shadow-sm ring-1 ring-black ring-opacity-5 p-5">
          <form @submit.prevent="submit" id="submit">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-6 pb-2">
              <div class="sm:col-span-6">
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
                  @selected="onOperatorIdSelected"
                >
                </MultiSelect>
              </div>
              <div class="sm:col-span-6" v-if="form.operator_id && deliveryPlatformOperatorOptions.length">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Delivery Platform
                </label>
                <MultiSelect
                  v-model="form.delivery_platform_operator_id"
                  :options="deliveryPlatformOperatorOptions"
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
                <Link href="/settings">
                  <Button
                    class="bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1"
                  >
                    <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
                    <span>
                      Back
                    </span>
                  </Button>
                </Link>
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
    deliveryPlatformOperators: Object,
    deliveryProductMappings: Object,
    operatorOptions: Object,
    categoryApiOptions: [Array, Object],
    type: String,
  })

  const deliveryPlatformOperatorOptions = ref([])
  const form = ref(
    useForm(getDefaultForm())
  )
  const loading = ref(false)
  const operatorOptions = ref([])
  const typeName = ref('')
  const permissions = usePage().props.auth.permissions

onMounted(() => {
    if(props.type == 'create') {
        typeName.value = 'Create New'
    } else {
        typeName.value = 'Edit'
    }
    operatorOptions.value = [
        ...props.operatorOptions.data.map((data) => {return {id: data.id, full_name: data.full_name}})
    ]
})

function getDefaultForm() {
  return {
    id: '',
    name: '',
    operator_id: '',
    delivery_platform_operator_id: '',
    category_json: '',
  }
}

function onOperatorIdSelected() {
  deliveryPlatformOperatorOptions.value = []

  router.reload({
      only: ['deliveryPlatformOperators'],
      data: {
        operator_id: form.value.operator_id.id,
      },
      replace: true,
      preserveState: true,
  })

  deliveryPlatformOperatorOptions.value = [
    ...props.deliveryPlatformOperators.data.map((data) => {return {id: data.id, name: data.deliveryPlatform.name}})
  ]
  // router.visit(
  //   route('delivery-product-mappings.create', {

  //   }),{
  //     only: ['deliveryPlatformOperators'],
  //     preserveState: true,
  //     preserveScroll: true,
  //   }
  // )
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
      resetOnSuccess: true,
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
</script>