<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600">
            Delivery Product Mapping Channel
          </span>
          <span v-if="vend.code">
            ID# {{ vend.code }}
          </span>
          <span v-if="vend.customer_code">
            ({{ vend.customer_code ? vend.customer_code : null }})
            {{ vend.customer_name ? vend.customer_name : null }}
          </span>
        </div>
      </template>
      <template #default>
        <div class="flex flex-col">
          <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5">
            <div class="inline-block min-w-full py-2 align-middle md:px-4 lg:px-6">
              <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                <table class="table-fixed min-w-full divide-y divide-gray-300">
                  <thead class="bg-gray-50">
                    <tr>
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        #
                      </th>
                      <th scope="col" class="w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        Image
                      </th>
                      <th scope="col" class="w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        Product
                      </th>
                      <th scope="col" class="w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        Price
                        <span v-if="profile && profile.base_currency">
                          ({{ profile.base_currency.currency_symbol }})
                        </span>
                      </th>
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        Reserved <br>
                        (%)
                      </th>
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        Reserved <br>
                        Qty
                      </th>
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        Current <br>
                        Qty
                      </th>
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        Current <br>
                        Capacity
                      </th>
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        Status
                      </th>
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                      </th>
                    </tr>
                  </thead>
                  <tbody class="bg-white">
                    <tr v-for="(channel, channelIndex) in props.vend.deliveryProductMappingVendChannels" :key="channel.id" :class="channelIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                        {{ channel.vend_channel_code }}
                      </td>
                      <td class="whitespace-nowrap text-sm  font-semibold text-gray-900 text-center">
                        <div class="flex justify-center items-center" >
                          <img class="h-16 w-16 rounded-full" :src="channel.delivery_product_mapping_item.product.thumbnail.full_url" alt="" v-if="channel.delivery_product_mapping_item.product && channel.delivery_product_mapping_item.product.thumbnail"/>
                        </div>
                      </td>
                      <td class="py-4 text-sm font-semibold text-gray-900 text-center">
                          <span v-if="channel.delivery_product_mapping_item.product && channel.delivery_product_mapping_item.product.code">
                            {{ channel.delivery_product_mapping_item.product.code }}
                          </span>
                          <span class="break-normal text-xs" v-if="channel.delivery_product_mapping_item.product && channel.delivery_product_mapping_item.product.name">
                            <br> {{ channel.delivery_product_mapping_item.product.name }}
                          </span>
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                        {{ (channel.amount).toLocaleString(undefined, {minimumFractionDigits: 2}) }}
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-red-700 sm:pl-6 text-center">
                        {{ channel.reserved_percent }}
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-red-700 sm:pl-6 text-center">
                        {{ channel.reserved_qty }}
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-blue-700 sm:pl-6 text-center">
                        {{ channel.vend_channel.qty }}
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-blue-700 sm:pl-6 text-center">
                        {{ channel.vend_channel.capacity }}
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                          <span class="inline-flex items-center rounded-md bg-green-300 px-1.5 py-0.5 text-xs font-medium text-green-800 ring-1 ring-inset ring-indigo-700/10" v-if="channel.is_active == 1">
                            Active
                          </span>
                          <span class="inline-flex items-center rounded-md bg-red-200 px-1.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-indigo-700/10" v-if="channel.is_active == 0">
                            Paused
                          </span>
                        </td>
                      </td>

                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                        <Button
                          class="flex space-x-1"
                          :class="[channel.is_active ? 'bg-yellow-300 hover:bg-yellow-400 text-black' : 'bg-green-500 hover:bg-green-600 text-white']"
                          @click.prevent="togglePauseDeliveryProductMappingVendChannel(channel)"
                        >
                          <PauseCircleIcon class="w-3 h-3" v-if="channel.is_active"></PauseCircleIcon>
                          <PlayCircleIcon class="w-3 h-3" v-else></PlayCircleIcon>
                          <span class="text-xs" v-if="channel.is_active">
                            Pause
                          </span>
                          <span class="text-xs" v-else>
                            Resume
                          </span>
                        </Button>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </template>
    </Modal>
  </Teleport>
</template>

<script setup>
import {PauseCircleIcon, PlayCircleIcon, PencilSquareIcon } from '@heroicons/vue/20/solid';
import Button from '@/Components/Button.vue';
import Modal from '@/Components/Modal.vue';
import { onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
  vend: Object,
  showModal: Boolean,
})

const channels = ref([])
const permissions = usePage().props.auth.permissions

const profile = usePage().props.auth.profile
const editable = ref(false)
const emit = defineEmits(['modalClose'])

function togglePauseDeliveryProductMappingVendChannel(channel) {
  let approvalText = channel.is_active ? 'Are you sure to pause this channel?' : 'Are you sure to resume this channel?'
  const approval = confirm(approvalText);
  if (!approval) {
      return;
  }
  router.post('/delivery-product-mappings/channels/' + channel.id + '/toggle-pause', {
      preserveState: false,
      preserveScroll: true,
      replace: true,
      onSuccess: () => {
        emit('modalClose')
      }
  })
  emit('modalClose')
}

</script>