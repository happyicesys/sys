<template>

  <Head title="Delivery Platform Order" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Edit Delivery Platform Order
        <span v-if="type == 'update'">
          {{ form.order_id }}  {{ form.short_order_id }}
        </span>
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
        <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll p-5">
          <form @submit.prevent="submit" id="submit">
            <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">
              <div class="sm:col-span-6">
                <div class="py-3">
                  <div
                          class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border "
                          :class="statusClass(deliveryPlatformOrder.status)"
                  >
                      <div class="flex flex-col">
                          <span class="font-semibold">
                            {{ deliveryPlatformOrder.status_name }}
                          </span>
                      </div>

                  </div>
                </div>
              </div>
              <div class="sm:col-span-3">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Order ID
                </label>
                <div class="mt-1">
                  <a :href="'/delivery-platform-orders?order_id=' + form.order_id" target="_blank">
                    <input
                      type="text"
                      class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-pointer text-blue-600 font-medium"
                      :value="form.order_id"
                      readonly
                    />
                  </a>
                </div>
              </div>
              <div class="sm:col-span-3">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Short Order ID
                </label>
                <div class="mt-1">
                  <input
                    type="text"
                    class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                    :value="form.short_order_id"
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
                    :value="form.deliveryPlatform.name"
                    disabled
                  />
                </div>
              </div>
              <div class="sm:col-span-6">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Order Datetime
                </label>
                <div class="mt-1">
                  <input
                    type="text"
                    class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                    :value="form.order_created_at"
                    disabled
                  />
                </div>
              </div>
              <div class="sm:col-span-6">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Vending Machine
                </label>
                <div class="mt-1" v-if="form.deliveryProductMappingVend && form.deliveryProductMappingVend.vend">
                  <a :href="'/vends?codes=' + form.deliveryProductMappingVend.vend.code" target="_blank">
                    <input
                      type="text"
                      class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-pointer text-blue-600 font-medium"
                      :value="form.deliveryProductMappingVend.vend.full_name"
                      readonly
                    />
                  </a>
                </div>
              </div>
              <div class="sm:col-span-6">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Delivery Product Mapping
                </label>
                <div class="mt-1" v-if="form.deliveryProductMappingVend && form.deliveryProductMappingVend.deliveryProductMapping">
                  <a :href="'/delivery-product-mappings/' + form.deliveryProductMappingVend.deliveryProductMapping.id + '/edit'" target="_blank">
                    <input
                      type="text"
                      class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-pointer text-blue-600 font-medium"
                      :value="form.deliveryProductMappingVend.deliveryProductMapping.name"
                      readonly
                    />
                  </a>
                </div>
              </div>
              <div class="sm:col-span-6">
                <FormTextarea v-model="form.remarks" :error="form.errors.remarks">
                  Remarks
                </FormTextarea>
              </div>

              <div class="sm:col-span-6">
                <div class="flex space-x-1 mt-5 justify-end">
                  <Link :href="'/delivery-platform-orders'">
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
                    <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                    <span>
                      Save
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
                    <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded">Order Item(s) </span>
                  </div>
                </div>
              </div>

              <div class="sm:col-span-6" v-if="form.id">
                <div class="flex space-x-1 mt-3 justify-start">
                  <!-- <Button
                    class="flex space-x-1 bg-gray-300 hover:bg-gray-400 text-black-700"
                    @click.prevent="onEditDeliveryOrderItemsClicked()"
                  >
                    <PencilSquareIcon class="w-4 h-4" v-if="!editOrderItems"></PencilSquareIcon>
                    <ArrowLeftOnRectangleIcon class="w-3 h-3" v-else></ArrowLeftOnRectangleIcon>
                    <span class="text-xs" v-if="!editOrderItems">
                      Edit Order
                    </span>
                    <span class="text-xs" v-else>
                      Hide Edit
                    </span>
                  </Button> -->

                  <Button
                    class="flex space-x-1 bg-red-500 hover:bg-red-600 text-white"
                    @click.prevent="onRequestCancelOrderClicked(form.id)"
                    v-if="!form.is_cancelled && form.status < 6"
                  >
                    <XCircleIcon class="w-4 h-4"></XCircleIcon>
                    <span>
                      Request to Cancel Order
                    </span>
                  </Button>

                  <Button
                    class="flex space-x-1 bg-green-500 hover:bg-green-600 text-white"
                    @click.prevent="onSaveDeliveryOrderItemsClicked(form.id)"
                    v-if="editOrderItems"
                  >
                    <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                    <span>
                      Save Order
                    </span>
                  </Button>
                </div>
              </div>

              <div class="sm:col-span-3" v-if="form.id && editOrderItems">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Product
                </label>
                <MultiSelect
                  v-model="form.delivery_platform_product_mapping_item_id"
                  :options="deliveryPlatformProductMappingItemOptions"
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
              <div class="sm:col-span-2" v-if="form.id && editOrderItems">
                <FormInput v-model="form.qty" :error="form.errors.qty" placeholderStr="Qty">
                  Qty
                </FormInput>
              </div>

              <div class="sm:col-span-1" v-if="form.id && editOrderItems">
                <Button
                type="button"
                @click.prevent="addDeliveryProductMappingItem()"
                class="bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6"
                :class="[!form.delivery_platform_product_mapping_item_id || !form.qty ? 'opacity-50 cursor-not-allowed' : '']"
                :disabled="!form.delivery_platform_product_mapping_item_id || !form.qty"
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
                    <table class="table-fixed min-w-full divide-y divide-gray-300">
                      <thead class="bg-gray-50">
                        <tr>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            #
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Thumbnail
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Product
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Channel(s)
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Qty
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Price
                          </th>
                          <!-- <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Action
                          </th> -->
                        </tr>
                      </thead>
                      <tbody class="bg-white">
                        <tr v-for="(deliveryPlatformOrderItem, deliveryPlatformOrderItemIndex) in props.deliveryPlatformOrder.data.deliveryPlatformOrderItems" :key="deliveryPlatformOrderItem.id" :class="deliveryPlatformOrderItemIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                            {{ deliveryPlatformOrderItemIndex + 1 }}
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                            <div class="flex justify-center">
                              <img class="h-24 w-24 md:h-20 md:w-20 rounded-full" :src="deliveryPlatformOrderItem.deliveryProductMappingItem.product.thumbnail.full_url" alt="" v-if="deliveryPlatformOrderItem.deliveryProductMappingItem.product && deliveryPlatformOrderItem.deliveryProductMappingItem.product.thumbnail"/>
                            </div>
                          </td>
                          <td class="whitespace-normal py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left flex flex-col">
                            <span v-if="deliveryPlatformOrderItem.deliveryProductMappingItem.product.code">
                              {{ deliveryPlatformOrderItem.deliveryProductMappingItem.product.code }}
                            </span>
                            <span class="break-words" v-if="deliveryPlatformOrderItem.deliveryProductMappingItem.product.name">
                              {{ deliveryPlatformOrderItem.deliveryProductMappingItem.product.name }}
                            </span>
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                            <span v-if="deliveryPlatformOrderItem.orderItemVendChannels[0]">
                              #{{ deliveryPlatformOrderItem.orderItemVendChannels[0].vend_channel_code }}
                            </span>
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                            <span v-if="editOrderItems">
                              <input type="text" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md" v-model="deliveryPlatformOrderItem.qty">
                            </span>
                            <span v-else>
                              {{ deliveryPlatformOrderItem.qty }}
                            </span>
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-right">
                            <span v-if="editOrderItems">
                              <input type="text" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md" v-model="deliveryPlatformOrderItem.amount">
                            </span>
                            <span v-else>
                              {{ deliveryPlatformOrderItem.amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                            </span>
                          </td>

                          <!-- <td class="whitespace-nowrap py-4 text-sm text-center flex flex-col space-y-1 px-2"> -->
                            <!-- <Button
                                class="flex space-x-1"
                                :class="[deliveryProductMappingItem.is_active ? 'bg-yellow-300 hover:bg-yellow-400 text-black' : 'bg-green-500 hover:bg-green-600 text-white']"
                                @click.prevent="togglePauseDeliveryProductMappingItem(deliveryProductMappingItem)"
                              >
                                <PauseCircleIcon class="w-3 h-3" v-if="deliveryProductMappingItem.is_active"></PauseCircleIcon>
                                <PlayCircleIcon class="w-3 h-3" v-else></PlayCircleIcon>
                                <span class="text-xs" v-if="deliveryProductMappingItem.is_active">
                                  Pause SKU
                                </span>
                                <span class="text-xs" v-else>
                                  Resume SKU
                                </span>
                              </Button>
                              <Button
                                class="bg-red-400 hover:bg-red-500 text-white flex space-x-1"
                                @click.prevent="unbindDeliveryProductMappingItem(deliveryProductMappingItem.id)"
                              >
                                <BackspaceIcon class="w-3 h-3"></BackspaceIcon>
                                <span class="text-xs">
                                  Unbind SKU
                                </span>
                              </Button> -->
                          <!-- </td> -->
                        </tr>
                        <tr v-if="props.deliveryPlatformOrder.data.deliveryPlatformOrderItems && props.deliveryPlatformOrder.data.campaign_json">
                          <td colspan="5" class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-bold text-gray-900 sm:pl-6 text-center col-span-4">
                            Promo
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-bold text-gray-900 sm:pl-6 text-right col-span-1">
                            -{{ props.deliveryPlatformOrder.data.promo_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                          </td>
                        </tr>
                        <tr v-if="props.deliveryPlatformOrder.data.deliveryPlatformOrderItems">
                          <td colspan="5" class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-bold text-gray-900 sm:pl-6 text-center col-span-4">
                            Subtotal
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-bold text-gray-900 sm:pl-6 text-right col-span-1">
                            {{ props.deliveryPlatformOrder.data.subtotal_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                          </td>
                        </tr>
                        <tr v-if="!props.deliveryPlatformOrder.data.deliveryPlatformOrderItems || !props.deliveryPlatformOrder.data.deliveryPlatformOrderItems.length">
                          <td colspan="7" class="whitespace-nowrap py-4 text-sm font-medium text-gray-600 text-center">
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
import FormTextarea from '@/Components/FormTextarea.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { ArrowLeftOnRectangleIcon, ArrowUturnLeftIcon, CheckCircleIcon, PencilSquareIcon, PlusCircleIcon, XCircleIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
  deliveryPlatformOrder: Object,
})

const form = ref(
  useForm(getDefaultForm())
)
const deliveryPlatformOrder = ref([])
const editOrderItems = ref(false)
const operatorCountry = usePage().props.auth.operatorCountry

onMounted(() => {
    form.value = props.deliveryPlatformOrder ? useForm(props.deliveryPlatformOrder.data) : useForm(getDefaultForm())
    deliveryPlatformOrder.value = props.deliveryPlatformOrder.data
})

function getDefaultForm() {
  return {
    id: '',
    order_id: '',
    short_order_id: '',
    deliveryPlatform: {
      name: '',
    },
    operator_id: '',
    order_created_at: '',
  }
}

function onEditDeliveryOrderItemsClicked() {
  editOrderItems.value = !editOrderItems.value
}

function onRequestCancelOrderClicked() {
  let approvalText = 'Are you sure to request to cancel this order?'
  const approval = confirm(approvalText);
  if (!approval) {
      return;
  }

  form.value
    .post('/delivery-platform-orders/' + form.value.id + '/request-cancel-order', {
    preserveState: true,
    replace: true,
  })
}

function onSaveDeliveryOrderItemsClicked() {
  let approvalText = 'Are you sure to edit this order?'
  const approval = confirm(approvalText);
  if (!approval) {
      return;
  }

  form.value
    .transform((data) => ({
      ...data,
      delivery_platform_order_items: deliveryPlatformOrder.value.deliveryPlatformOrderItems,
    }))
    .post('/delivery-platform-orders/' + form.value.id + '/update-order-items', {
    preserveState: true,
    replace: true,
  })

  editOrderItems.value = false
}

function statusClass(status) {
  let statusClass = ''
  switch(status) {
    case 1:
    case 2:
      statusClass = 'bg-blue-400 text-gray-800'
      break;
    case 3:
    case 4:
    case 5:
      statusClass = 'bg-yellow-400 text-gray-800'
      break;
    case 6:
      statusClass = 'bg-green-400 text-white-800'
      break;
    case 98:
    case 99:
      statusClass = 'bg-red-400 text-white-800'
      break;
  }
  return statusClass
}

function submit() {
  form.value.clearErrors()
  if(props.type === 'update') {
    form.value
      .transform((data) => ({
        ...data,
        timezone: data.timezone ? data.timezone.name : null,
        country_id: data.country_id ? data.country_id.id : null,
      }))
      .post('/operators/' + form.value.id + '/update', {
      preserveState: true,
      replace: true,
    })
  }
}

</script>