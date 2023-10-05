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
                  @selected="onDeliveryPlatformOperatorIdSelected"
                >
                </MultiSelect>
              </div>
              <div class="sm:col-span-6" v-if="form.delivery_platform_operator_id">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Platform Category
                </label>
                <MultiSelect
                  v-model="form.category_json"
                  :options="categoryApiOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                  @selected="onCategoryJsonSelected"
                >
                </MultiSelect>
              </div>
              <div class="sm:col-span-6" v-if="form.delivery_platform_operator_id">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Refer to Product Mapping
                </label>
                <MultiSelect
                  v-model="form.product_mapping_id"
                  :options="productMappingOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                  @selected="onProductMappingIdSelected"
                >
                </MultiSelect>
              </div>

            <!-- <div class="sm:col-span-6" v-if="form.product_mapping_id"> -->
              <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3" v-if="form.product_mapping_id">
                <div class="relative">
                  <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-gray-300"></div>
                  </div>
                  <div class="relative flex justify-center">
                    <span class="px-3 bg-white text-lg font-medium text-gray-900">Delivery Platform Product(s) </span>
                  </div>
                </div>
              </div>

              <div v-if="form.product_mapping_id">
                <FormInput v-model="form.channel_code" :error="form.errors.channel_code" placeholderStr="Channel ID">
                  Channel ID
                </FormInput>
              </div>
              <div v-if="form.product_mapping_id">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Product
                </label>
                <MultiSelect
                  v-model="form.product_id"
                  :options="productOptions"
                  trackBy="id"
                  valueProp="id"
                  label="full_name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.product_id">
                  {{ form.errors.product_id }}
                </div>
              </div>
              <div v-if="form.product_mapping_id">
                <FormInput v-model="form.amount" :error="form.errors.amount" placeholderStr="Platform Price">
                  Price
                </FormInput>
              </div>
              <div v-if="form.product_mapping_id">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Platform SubCategory
                </label>
                <MultiSelect
                  v-model="form.sub_category_json"
                  :options="subCategoryOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
              </div>

              <div class="sm:col-span-1" v-if="form.product_mapping_id">
                <Button
                type="button"
                @click="addDeliveryProductMappingItem()"
                class="bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6"
                :class="[!form.channel_code || !form.product_id ? 'opacity-50 cursor-not-allowed' : '']"
                :disabled="!form.channel_code || !form.product_id"
                >
                  <PlusCircleIcon class="w-4 h-4"></PlusCircleIcon>
                  <span>
                    Add
                  </span>
                </Button>
              </div>

              <div class="sm:col-span-6 flex flex-col mt-3" v-if="form.product_mapping_id">
              <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5">
                <div class="inline-block min-w-full py-2 align-middle md:px-4 lg:px-6">
                  <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                    <table class="table-fixed min-w-full divide-y divide-gray-300">
                      <thead class="bg-gray-50">
                        <tr>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            #
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Channel Code
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Thumbnail
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Product
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Price
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Platform SubCategory
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Action
                          </th>
                        </tr>
                      </thead>
                      <tbody class="bg-white">
                        <tr v-for="(productMappingItem, productMappingItemIndex) in productMappingItems" :key="productMappingItem.id" :class="productMappingItemIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                          <!-- <td>
                            {{ productMappingItem }}
                          </td> -->
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                            {{ productMappingItemIndex + 1 }}
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                            {{ productMappingItem.channel_code }}
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                            <div class="flex justify-center">
                              <img class="h-24 w-24 md:h-20 md:w-20 rounded-full" :src="productMappingItem.product.thumbnail.full_url" alt="" v-if="productMappingItem.product && productMappingItem.product.thumbnail"/>
                            </div>
                          </td>
                          <td class="whitespace-normal py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left flex flex-col">
                            <span v-if="productMappingItem.product.code">
                              {{ productMappingItem.product.code }}
                            </span>
                            <span class="break-words" v-if="productMappingItem.product.name">
                              {{ productMappingItem.product.name }}
                            </span>
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                            <FormInput v-model="productMappingItem.delivery_platform_amount" :error="form.errors.delivery_platform_amount" placeholderStr="Platform Price">
                            </FormInput>
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                            <MultiSelect
                              v-model="productMappingItem.delivery_platform_sub_category_json"
                              :options="subCategoryOptions"
                              trackBy="id"
                              valueProp="id"
                              label="name"
                              placeholder="Select"
                              open-direction="bottom"
                              class="mt-1"
                            >
                            </MultiSelect>
                          </td>
                          <td class="whitespace-nowrap py-4 text-sm text-center">
                            <Button
                              class="bg-red-400 hover:bg-red-500 text-white"
                              @click="removeDeliveryProductMappingItem(productMappingItem)"
                            >
                              <BackspaceIcon class="w-4 h-4"></BackspaceIcon>
                            </Button>
                          </td>
                        </tr>
                        <tr v-if="!productMappingItems.length">
                          <td colspan="5" class="whitespace-nowrap py-4 text-sm font-medium text-gray-600 text-center">
                            No Records Found
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
import { ArrowUturnLeftIcon, BackspaceIcon, CheckCircleIcon, PlusCircleIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import moment from 'moment';

const props = defineProps({
    categoryApiOptions: [Array, Object],
    deliveryProductMappings: Object,
    operatorOptions: Object,
    productMappingItems: Object,
    productMappingOptions: [Array, Object],
    productOptions: Object,
    type: String,
  })

  const categoryApiOptions = ref([])
  const deliveryPlatformOperatorOptions = ref([])
  const form = ref(
    useForm(getDefaultForm())
  )
  const loading = ref(false)
  const operatorOptions = ref([])
  const productMappingOptions = ref([])
  const productMappingItems = ref([])
  const productOptions = ref([])
  const subCategoryOptions = ref([])
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
    productOptions.value = props.productOptions.data
})

function getDefaultForm() {
  return {
    id: '',
    category_json: '',
    delivery_platform_operator_id: '',
    name: '',
    operator_id: '',
    product_mapping_id: '',
    sub_category_json: '',
  }
}

function addDeliveryProductMappingItem() {
  if(productMappingItems.value.map(function(productMapping) { return productMapping.channel_code; }).indexOf(form.value.channel_code) < 0) {
    productMappingItems.value.push({
      product: form.value.product_id,
      channel_code: form.value.channel_code,
      delivery_platform_sub_category_json: form.value.sub_category_json,
      delivery_platform_amount: form.value.amount,
    })
    productMappingItems.value.sort((a, b) => a.channel_code - b.channel_code)
  }

  productMappingItems.value.splice(productMappingItems.value.indexOf(productMappingItem), 1)
}

function onCategoryJsonSelected() {
  subCategoryOptions.value = []
  subCategoryOptions.value = form.value.category_json.subCategories
}

function onDeliveryPlatformOperatorIdSelected() {
  router.reload({
    only: ['categoryApiOptions', 'productMappingOptions'],
    data: {
      delivery_platform_operator_id: form.value.delivery_platform_operator_id.id,
      operator_id: form.value.operator_id.id
    },
    replace: true,
    preserveState: true,
    onSuccess: page => {
      categoryApiOptions.value = props.categoryApiOptions[0].categories.map((data) => {return {id: data.id, name: data.name, subCategories: data.subCategories}})
      productMappingOptions.value = [
        ...props.productMappingOptions[0].data.map((data) => {return {id: data.id, name: data.name}})
      ]
      subCategoryOptions.value = []
    }
  })
}

function onOperatorIdSelected() {
  deliveryPlatformOperatorOptions.value =  [
    ...props.operatorOptions.data.find(x => x.id === form.value.operator_id.id).deliveryPlatformOperators.map((data) => {return {id: data.id, name: data.deliveryPlatform.name}})
  ]
}

function onProductMappingIdSelected() {
  router.reload({
    only: ['productMappingItems'],
    data: {
      product_mapping_id: form.value.product_mapping_id
    },
    replace: true,
    preserveState: true,
    onSuccess: page => {
      productMappingItems.value = [
        ...props.productMappingItems[0].data,
      ]
    }
  })
}

function removeDeliveryProductMappingItem(productMappingItem) {
  productMappingItems.value.splice(productMappingItems.value.indexOf(productMappingItem), 1)
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