<template>
  <Head title="Delivery Product Mapping" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ typeName }} Delivery Platform Campaign
        <span v-if="type == 'update'">
          {{ deliveryPlatformCampaign.name }}
        </span>
      </h2>
    </template>

     <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
        <div class="shadow-sm ring-1 ring-black ring-opacity-5 p-5 mb-3">
          <form @submit.prevent="submit" id="submit">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-6 pb-5 mb-3">
              <div class="sm:col-span-6">
                <FormInput v-model="form.name" :error="form.errors.name" required="true">
                  Name
                </FormInput>
              </div>
              <div class="sm:col-span-6">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Delivery Product Mapping
                </label>
                <div class="mt-1">
                  <input
                    type="text"
                    class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                    :value="props.deliveryPlatformCampaign.data.deliveryProductMapping.name"
                    disabled
                  />
                </div>
              </div>
              <div class="sm:col-span-6">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Delivery Platform
                </label>
                <div class="mt-1">
                  <input
                    type="text"
                    class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                    :value="props.deliveryPlatformCampaign.data.deliveryPlatformOperator.deliveryPlatform.name  + ' (' + props.deliveryPlatformCampaign.data.deliveryPlatformOperator.type + ')'"
                    disabled
                  />
                </div>
              </div>
              <div class="sm:col-span-3">
                <DatetimePicker v-model="form.datetime_from" :error="form.errors.datetime_from" :minDate="datetimeFrom" @input="onDateFromChanged()">
                  Begin Date
                  <span class="text-red-500">
                    *
                  </span>
                </DatetimePicker>
              </div>
              <div class="sm:col-span-3">
                <DatetimePicker v-model="form.datetime_to" :error="form.errors.datetime_to" :minDate="minDatetimeTo">
                  End Date
                  <span class="text-red-500">
                    *
                  </span>
                </DatetimePicker>
              </div>

            <div class="sm:col-span-6">
              <div class="flex space-x-1 mt-5 justify-end">
                <Link href="/delivery-platform-campaigns">
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
                >
                  <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                  <span>
                    Save
                  </span>
                </Button>
                <Button
                  class="bg-yellow-500 hover:bg-yellow-600 text-black flex space-x-1"
                  @click.prevent="submitCampaign(deliveryPlatformCampaign.id)"
                >
                  <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                  <span>
                    Sync to VM & Submit to Platform
                  </span>
                </Button>
              </div>
            </div>

              <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
                <div class="relative">
                  <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-gray-300"></div>
                  </div>
                  <div class="relative flex justify-center">
                    <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded">Campaign Item(s)</span>
                  </div>
                </div>
              </div>
              <div class="sm:col-span-6" v-if="deliveryPlatformCampaignItemDesc">
                <div
                    class="inline-flex justify-center items-center rounded px-3 py-1 my-1 text-lg font-medium border min-w-fit bg-red-300 text-gray-800"
                >
                    <div class="flex flex-col">
                      {{ deliveryPlatformCampaignItemDesc }}
                    </div>
                </div>
              </div>
              <!-- <div class="sm:col-span-6 text-semibold">
                {{ deliveryPlatformCampaignItemDesc }}
              </div> -->
              <div v-if="form.id" class="sm:col-span-6">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Type
                  <span class="text-red-500">
                    *
                  </span>
                </label>
                <MultiSelect
                  v-model="form.delivery_platform_campaign_item"
                  :options="deliveryPlatformCampaignItemOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="top"
                  class="mt-1"
                  @selected="onDeliveryPlatformCampaignItemChanged()"
                >
                </MultiSelect>
              </div>
              <div v-if="form.delivery_platform_campaign_item && form.delivery_platform_campaign_item.cap" class="sm:col-span-2">
                <FormInput v-model="form.cap" :error="form.errors.cap" placeholderStr="Number">
                  Cap (Max Discount Dollar)
                </FormInput>
              </div>
              <div v-if="form.delivery_platform_campaign_item && form.delivery_platform_campaign_item.qty" class="sm:col-span-2">
                <FormInput v-model="form.qty" :error="form.errors.qty" placeholderStr="Number" required="true">
                  Bundle Total Qty
                </FormInput>
              </div>
              <div v-if="form.delivery_platform_campaign_item && form.delivery_platform_campaign_item.id" class="sm:col-span-2">
                <FormInput v-model="form.promo_value" :error="form.errors.promo_value" placeholderStr="Number" required="true">
                  Value ({{ form.delivery_platform_campaign_item.id }})
                </FormInput>
              </div>
              <div v-if="form.delivery_platform_campaign_item" class="sm:col-span-2">
                <FormInput v-model="form.total_count" :error="form.errors.total_count" placeholderStr="Default Unlimited">
                  Redemption Limit
                </FormInput>
              </div>
              <div v-if="form.delivery_platform_campaign_item" class="sm:col-span-2">
                <FormInput v-model="form.total_count_per_user" :error="form.errors.total_count_per_user" placeholderStr="Default Unlimited">
                  Redemtion Limit/ User
                </FormInput>
              </div>
              <div v-if="form.delivery_platform_campaign_item" class="sm:col-span-5">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Scope
                  <span class="text-red-500">
                    *
                  </span>
                </label>
                <MultiSelect
                  v-model="form.delivery_platform_campaign_item_scope"
                  :options="form.delivery_platform_campaign_item.scope"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="top"
                  class="mt-1"
                  @selected="onDeliveryPlatformCampaignItemScopeChanged()"
                >
                </MultiSelect>
              </div>
              <div v-if="form.delivery_platform_campaign_item_scope" class="sm:col-span-3">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  User
                  <span class="text-red-500">
                    *
                  </span>
                </label>
                <MultiSelect
                  v-model="form.delivery_platform_campaign_item_scope_eater_type"
                  :options="form.delivery_platform_campaign_item_scope.eaterType"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="top"
                  class="mt-1"
                >
                </MultiSelect>
              </div>
              <div v-if="form.delivery_platform_campaign_item_scope && form.delivery_platform_campaign_item_scope.minBasketAmount" class="sm:col-span-2">
                <FormInput v-model="form.min_basket_amount" :error="form.errors.min_basket_amount" placeholderStr="Number">
                  Minimum Basket Amount
                </FormInput>
              </div>
              <span v-if="form.delivery_platform_campaign_item_scope && form.delivery_platform_campaign_item_scope.isProduct" class="sm:col-span-6">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Product
                  <span class="text-red-500">
                    *
                  </span>
                </label>
                <MultiSelect
                  v-model="form.delivery_product_mapping_items"
                  :options="deliveryProductMappingItemOptions"
                  trackBy="id"
                  valueProp="id"
                  label="full_name"
                  mode="tags"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                  :clear="isClearForm"
                  :max="form.delivery_platform_campaign_item_scope.singleObjectOnly ? 1 : -1"
                >
                <!-- :mode="form.delivery_platform_campaign_item_scope.singleObjectOnly ? 'single' : 'tags'" -->
                </MultiSelect>
                <span>
                  <span class="text-red-500 text-sm" v-if="form.delivery_platform_campaign_item_scope.singleObjectOnly">
                    * Single Object Only
                  </span>
                </span>
              </span>
              <span v-if="form.delivery_platform_campaign_item_scope && form.delivery_platform_campaign_item_scope.isCategory" class="sm:col-span-6">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Category
                  <span class="text-red-500">
                    *
                  </span>
                </label>
                <MultiSelect
                  v-model="form.category"
                  :options="categoryOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                  :clear="isClearForm"
                  :max="form.delivery_platform_campaign_item_scope.singleObjectOnly ? 1 : -1"
                >
                </MultiSelect>
              </span>
              <div v-if="form.delivery_platform_campaign_item && form.delivery_platform_campaign_item_scope" class="sm:col-span-6">
                <FormInput v-model="form.settings_name" :error="form.errors.settings_name" placeholderStr="Campaign Item Name">
                  Campaign Item Name (Optional)
                </FormInput>
              </div>
              <div class="sm:col-span-6" v-if="form.id">
                <Button
                type="button"
                @click.prevent="saveCampaignItem()"
                class="bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-1"
                :class="[!isFormCompleted ? 'opacity-50 cursor-not-allowed' : '']"
                :disabled="!isFormCompleted"
                >
                  <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                  <span>
                    Create Campaign Item
                  </span>
                </Button>
              </div>

              <div class="sm:col-span-6 flex flex-col mt-2 mx-2 mb-3" v-if="form.id">
                <div class="mt-6 flex flex-col">
                  <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8 px-3">
                      <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll">
                        <table class="min-w-full border-separate" style="border-spacing: 0">
                            <thead class="bg-gray-100">
                              <tr class="divide-x divide-gray-200">
                                <TableHead>
                                  #
                                </TableHead>
                                <TableHead>
                                  Name <br>
                                  Label
                                </TableHead>
                                <TableHead>
                                  Settings
                                </TableHead>
                                <TableHead>
                                  Items
                                </TableHead>
                                <TableHead>
                                  Action
                                </TableHead>
                              </tr>
                            </thead>
                            <tbody class="bg-white">
                              <tr v-for="(deliveryPlatformCampaignItem, deliveryPlatformCampaignItemIndex) in deliveryPlatformCampaign.deliveryPlatformCampaignItems" :key="deliveryPlatformCampaignItem.id" class="divide-x divide-gray-300">
                                <TableData :currentIndex="deliveryPlatformCampaignItemIndex" :totalLength="deliveryPlatformCampaign.deliveryPlatformCampaignItems.length" inputClass="text-center">
                                  {{ deliveryPlatformCampaignItemIndex + 1 }}
                                </TableData>
                                <TableData :currentIndex="deliveryPlatformCampaignItemIndex" :totalLength="deliveryPlatformCampaign.deliveryPlatformCampaignItems.length" inputClass="text-center">
                                  {{ deliveryPlatformCampaignItem.settings_name }}
                                  <br>
                                  <span class="text-blue-700">
                                    {{ deliveryPlatformCampaignItem.settings_label }}
                                  </span>
                                </TableData>
                                <TableData :currentIndex="deliveryPlatformCampaignItemIndex" :totalLength="deliveryPlatformCampaign.deliveryPlatformCampaignItems.length" inputClass="text-center">
                                  <ul role="list" class="divide-y divide-gray-100 overflow-scroll bg-white" v-if="deliveryPlatformCampaignItem.settings_json">
                                    <li v-for="(setting, settingIndex) in deliveryPlatformCampaignItem.settings_json" class=" flex justify-between gap-x-1 px-1 py-0.5">
                                      <div class="flex min-w-0 gap-x-4">
                                        <span class="text-sm">
                                          {{ settingIndex }}:
                                        </span>
                                        <div class="min-w-0 flex-auto">
                                          <p class="text-sm font-semibold leading-2 text-gray-900">
                                            {{ setting }}
                                          </p>
                                        </div>
                                      </div>
                                    </li>
                                  </ul>
                                </TableData>
                                <TableData :currentIndex="deliveryPlatformCampaignItemIndex" :totalLength="deliveryPlatformCampaign.deliveryPlatformCampaignItems.length" inputClass="text-left">
                                  <ul role="list" class="divide-y divide-gray-100 overflow-scroll bg-white" v-if="deliveryPlatformCampaignItem.items_json">
                                    <li v-for="item in deliveryPlatformCampaignItem.items_json" class=" flex justify-between gap-x-2 px-1 py-0.5">
                                      <div class="flex min-w-0 gap-x-3">
                                        <div class="min-w-0 flex-auto pt-1">
                                          <p class="text-sm font-semibold leading-2 text-gray-900" v-if="item && 'full_name' in item">
                                            {{ item.full_name ? item.full_name : item.name }}
                                          </p>
                                        </div>
                                      </div>
                                    </li>
                                  </ul>
                                  <div v-if="!deliveryPlatformCampaignItem.items_json">
                                    NA
                                  </div>
                                </TableData>
                                <TableData :currentIndex="deliveryPlatformCampaignItemIndex" :totalLength="deliveryPlatformCampaign.deliveryPlatformCampaignItems.length" inputClass="text-center">
                                    <Button
                                      @click.prevent="removeDeliveryPlatformCampaignItem(deliveryPlatformCampaignItem.id)"
                                      class="flex flex-col bg-red-500 hover:bg-red-600 text-white"
                                      :disabled="deliveryPlatformCampaignItem.deliveryPlatformCampaignItemVends && deliveryPlatformCampaignItem.deliveryPlatformCampaignItemVends.length > 0"
                                      :class="[deliveryPlatformCampaignItem.deliveryPlatformCampaignItemVends && deliveryPlatformCampaignItem.deliveryPlatformCampaignItemVends.length > 0 ? 'opacity-50 cursor-not-allowed' : '']"
                                    >

                                      <XCircleIcon class="w-4 h-4" ></XCircleIcon>
                                      <span class="text-xs" v-if="deliveryPlatformCampaignItem.deliveryPlatformCampaignItemVends && deliveryPlatformCampaignItem.deliveryPlatformCampaignItemVends.length > 0">
                                        Campaigns running, cannot delete
                                      </span>
                                    </Button>
                                </TableData>
                              </tr>
                               <tr v-if="!deliveryPlatformCampaign.deliveryPlatformCampaignItems.length">
                                <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                                    No Results Found
                                </td>
                              </tr>
                            </tbody>
                        </table>
                      </div>
                  </div>
              </div>
              </div>

              <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
                <div class="relative">
                  <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-gray-300"></div>
                  </div>
                  <div class="relative flex justify-center">
                    <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded">Binded Campaign(s)</span>
                  </div>
                </div>
              </div>

              <div class="sm:col-span-6 flex flex-col mt-2 mx-2 mb-3" v-if="form.id">
                <div class="mt-6 flex flex-col">
                  <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8 px-3">
                      <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll">
                        <table class="min-w-full border-separate" style="border-spacing: 0">
                            <thead class="bg-gray-100">
                              <tr class="divide-x divide-gray-200">
                                <TableHead>
                                  #
                                </TableHead>
                                <TableHead>
                                  Vend
                                </TableHead>
                                <TableHead>
                                  Campaign(s)
                                </TableHead>
                              </tr>
                            </thead>
                            <tbody class="bg-white">
                              <tr v-for="(deliveryProductMappingVend, deliveryProductMappingVendIndex) in deliveryProductMappingVends.data" :key="deliveryProductMappingVend.id" class="divide-x divide-gray-300">
                                <TableData :currentIndex="deliveryProductMappingVendIndex" :totalLength="deliveryProductMappingVends.data.length" inputClass="text-center">
                                  {{ deliveryProductMappingVendIndex + 1 }}
                                </TableData>
                                <TableData :currentIndex="deliveryProductMappingVendIndex" :totalLength="deliveryProductMappingVends.data.length" inputClass="text-left">
                                  {{ deliveryProductMappingVend.vend.code }}
                                  <br>
                                  {{ deliveryProductMappingVend.vend.cust_full_name }}
                                </TableData>
                                <TableData :currentIndex="deliveryProductMappingVendIndex" :totalLength="deliveryProductMappingVends.data.length" inputClass="text-center">
                                  <div class="flex flex-col space-y-1 max-w-fit">
                                    <span class="flex justify-between items-center gap-x-0.5 rounded-md px-2 py-1 text-sm font-medium"
                                    :class="[deliveryPlatformCampaignItemVend.platform_ref_id ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700']"
                                    v-for="deliveryPlatformCampaignItemVend in deliveryProductMappingVend.deliveryPlatformCampaignItemVends">
                                      {{ deliveryPlatformCampaignItemVend.settings_name }}
                                      <button type="button" class="group -mr-1 h-4 w-4 rounded-sm hover:bg-blue-700/20 ">
                                        <span class="sr-only">Remove</span>
                                        <svg viewBox="0 0 14 14" class="h-4 w-4 stroke-blue-900/50 group-hover:stroke-blue-900/75" @click.prevent="onDeleteCampaign(deliveryPlatformCampaignItemVend.id)">
                                          <path d="M4 4l6 6m0-6l-6 6" />
                                        </svg>
                                      </button>
                                    </span>
                                  </div>
                                </TableData>
                              </tr>
                               <tr v-if="!deliveryProductMappingVends.data.length">
                                <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                                    No Results Found
                                </td>
                              </tr>
                            </tbody>
                        </table>
                      </div>
                  </div>
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
import DatetimePicker from '@/Components/DatetimePicker.vue';
import FormInput from '@/Components/FormInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import TableData from '@/Components/TableData.vue';
import TableHead from '@/Components/TableHead.vue';
import { ArrowUturnLeftIcon, CheckCircleIcon, PauseCircleIcon, PencilSquareIcon, PlayCircleIcon, PlusCircleIcon, XCircleIcon } from '@heroicons/vue/20/solid';
import { computed, ref, onMounted } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    deliveryPlatformCampaign: Object,
    deliveryPlatformCampaignItemOptions: Object,
    deliveryProductMappingVends: Object,
    type: String,
  })
  const categoryOptions = ref([])
  const deliveryPlatformCampaign = ref([])
  const deliveryProductMappingItemOptions = ref([])
  const form = ref(
    useForm(getDefaultForm())
  )
  const isClearForm = ref(false)
  const showItemEditModal = ref(false)
  const typeName = ref('')
  // const permissions = usePage().props.auth.permissions


function getDefaultForm() {
  return {
    id: '',
    cap: '',
    datetime_from: '',
    datetime_to: '',
    delivery_platform_campaign_item: '',
    delivery_platform_campaign_item_scope: '',
    delivery_product_mapping_items: '',
    min_basket_amount: 0,
    qty: '',
    total_count: '',
    total_count_per_user: '',
    promo_value: '',
  }
}

onMounted(() => {
    if(props.type == 'create') {
        typeName.value = 'Create New'
    } else {
        typeName.value = 'Edit'
    }
    deliveryPlatformCampaign.value = props.deliveryPlatformCampaign.data
    deliveryProductMappingItemOptions.value = props.deliveryPlatformCampaign.data.deliveryProductMapping.deliveryProductMappingItems.map((data) => {return {
      id: data.id,
      full_name: '(#' + data.channel_code + ') ' + data.product.code + ' ' + data.product.name,
      img_url: data.product.thumbnail ? data.product.thumbnail.full_url : '',
      product_id: data.product.id,
    }})
    categoryOptions.value = [
      props.deliveryPlatformCampaign.data.deliveryProductMapping.category_json ? {
        id: props.deliveryPlatformCampaign.data.deliveryProductMapping.category_json.id,
        name: props.deliveryPlatformCampaign.data.deliveryProductMapping.category_json.name,
      } : {
        id: '',
        name: '',
      },
    ]

    form.value = deliveryPlatformCampaign.value ?
      useForm(deliveryPlatformCampaign.value) :
      useForm(getDefaultForm())
})

const datetimeFrom = computed(function() {
  return moment().add(30, 'minutes').format('YYYY-MM-DD HH:mm:ss')
})
const deliveryPlatformCampaignItemDesc = computed(function() {
  let descStr = ''

  if(form.value.delivery_platform_campaign_item && form.value.promo_value) {
    if(form.value.delivery_platform_campaign_item.phrase1) {
      descStr += form.value.delivery_platform_campaign_item.phrase1
    }

    if(form.value.delivery_platform_campaign_item.qty) {
      descStr += form.value.qty ? form.value.qty : ''
    }else {
      descStr += form.value.promo_value ? form.value.promo_value : ''
    }

    if(form.value.delivery_platform_campaign_item.phrase2) {
      descStr += form.value.delivery_platform_campaign_item.phrase2
    }

    if(form.value.delivery_platform_campaign_item.cap) {
      descStr += form.value.cap ? form.value.cap : ''
    }

    if(!form.value.delivery_platform_campaign_item.qty) {
      descStr += form.value.qty ? form.value.qty : ''
    }else {
      descStr += form.value.promo_value ? form.value.promo_value : ''
    }

    if(form.value.delivery_platform_campaign_item.phrase3) {
      descStr += ' ' + form.value.delivery_platform_campaign_item.phrase3
    }

    if(form.value.delivery_platform_campaign_item_scope) {
      descStr += ' ' + form.value.delivery_platform_campaign_item_scope.name
    }
  }

  return descStr
})
const minDatetimeTo = computed(function() {
  return moment(form.value.datetime_from).add(2, 'hours').format('YYYY-MM-DD HH:mm:ss')
})

const isFormCompleted = computed(function() {
  let isCompleted = true

  if(!form.value.delivery_platform_campaign_item || !form.value.promo_value || !form.value.delivery_platform_campaign_item_scope) {
    isCompleted = false
  }

  if(form.value.delivery_platform_campaign_item && form.value.delivery_platform_campaign_item.qty && !form.value.qty) {
    isCompleted = false
  }

  if(!form.value.delivery_platform_campaign_item_scope_eater_type) {
    isCompleted = false
  }

  if(form.value.delivery_platform_campaign_item_scope && form.value.delivery_platform_campaign_item_scope.isProduct) {
    if(!form.value.delivery_product_mapping_items) {
      isCompleted = false
    }else {
      if(form.value.delivery_product_mapping_items.length == 0) {
        isCompleted = false
      }
      if(form.value.delivery_platform_campaign_item_scope.singleObjectOnly && form.value.delivery_product_mapping_items.length > 1) {
        isCompleted = false
      }
    }
  }

  if(form.value.delivery_platform_campaign_item_scope && form.value.delivery_platform_campaign_item_scope.isCategory && (form.value.category && form.value.category.length == 0)) {
    if(!form.value.category) {
      isCompleted = false
    }else {
      if(form.value.category.length == 0) {
        isCompleted = false
      }
      if(form.value.category.singleObjectOnly && form.value.category.length > 1) {
        isCompleted = false
      }
    }
  }

  return isCompleted
})

function onDeleteCampaign(deliveryPlatformCampaignItemVendId) {
  const approval = confirm('Are you sure to delete this entry?');
  if (!approval) {
      return;
  }
  router.delete('/delivery-platform-campaigns/item-vend/' + deliveryPlatformCampaignItemVendId, {
      preserveState: false,
      preserveScroll: true,
      replace: true,
  })
}

function onDeliveryPlatformCampaignItemChanged() {
  isClearForm.value = true
  form.value.delivery_platform_campaign_item_scope = ''
  form.value.delivery_platform_campaign_item_scope_eater_type = ''
  form.value.category = ''
  form.value.qty = ''
  form.value.promo_value = ''
  form.value.cap = ''
  form.value.total_count = ''
  form.value.total_count_per_user = ''
}

function onDeliveryPlatformCampaignItemScopeChanged() {
  isClearForm.value = true
}

function onItemEditClicked(deliveryProductMappingItem) {
  deliveryProductMappingItemObj.value = deliveryProductMappingItem
  showItemEditModal.value = true
}

function removeDeliveryPlatformCampaignItem(deliveryPlatformCampaignItemID) {
  const approval = confirm('Are you sure to delete this entry?');
  if (!approval) {
      return;
  }
  router.delete('/delivery-platform-campaigns/item/' + deliveryPlatformCampaignItemID, {
      preserveState: false,
      preserveScroll: true,
      replace: true,
  })
}

function saveCampaignItem()
{
  router.post('/delivery-platform-campaigns/' + form.value.id + '/create-item', {
    ...form.value,
    delivery_platform_campaign_item: form.value.delivery_platform_campaign_item ? form.value.delivery_platform_campaign_item.id : '',
    delivery_platform_campaign_item_scope: form.value.delivery_platform_campaign_item_scope ? form.value.delivery_platform_campaign_item_scope.id : '',
    delivery_platform_campaign_item_scope_eater_type: form.value.delivery_platform_campaign_item_scope_eater_type ? form.value.delivery_platform_campaign_item_scope_eater_type.id : '',
    settings_label: deliveryPlatformCampaignItemDesc.value,
  }, {
      preserveState: false,
      preserveScroll: true,
      replace: true,
  })
}

function submit() {
  form.value.clearErrors()
  form.value
  .transform((data) => ({
    name: data.name,
    reserved_percent: data.reserved_percent,
    reserved_qty: data.reserved_qty,
  }))
  .post('/delivery-product-mappings/' + form.value.id + '/update', {
    preserveState: true,
    replace: true,
  })
}

function submitCampaign(id) {
  const approval = confirm('Are you sure to submit this campaign and its items to platform?');
  if (!approval) {
      return;
  }
  router.post('/delivery-platform-campaigns/' + id + '/submit-platform', {}, {
      preserveState: false,
      preserveScroll: true,
      replace: true,
  })
}

function togglePauseAllVends(deliveryProductMappingId) {
  let approvalText = form.value.is_active ? 'Are you sure to pause all vending machines?' : 'Are you sure to resume all vending machines?'
  const approval = confirm(approvalText);
  if (!approval) {
      return;
  }
  router.post('/delivery-product-mappings/' + deliveryProductMappingId + '/toggle-pause-all-vends', {}, {
      preserveState: false,
      preserveScroll: true,
      replace: true,
  })
}

function togglePauseVend(deliveryProductMappingVend) {
  let approvalText = deliveryProductMappingVend.is_active ? 'Are you sure to pause this vending machine?' : 'Are you sure to resume this vending machine?'
  const approval = confirm(approvalText);
  if (!approval) {
      return;
  }
  router.post('/delivery-product-mappings/vends/' + deliveryProductMappingVend.id + '/toggle-pause-vend', {}, {
      preserveState: false,
      preserveScroll: true,
      replace: true,
  })
}

function unbindVend(deliveryProductMappingVendId) {
  const approval = confirm('Are you sure to delete this entry?');
  if (!approval) {
      return;
  }
  router.delete('/delivery-product-mappings/unbind/' + deliveryProductMappingVendId, {
      preserveState: false,
      preserveScroll: true,
      replace: true,
      onSuccess: () => {
        router.reload({only: ['unbindedVendOptions']})
        unbindedVendOptions.value = props.unbindedVendOptions ? props.unbindedVendOptions.data : []
      },
  })
}
</script>