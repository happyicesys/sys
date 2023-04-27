<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600" v-if="props.profile">
            Channel Overview
          </span>
          <span v-if="vend.code">
            ID# {{ vend.code }}
          </span>
          <span v-if="vend.latestVendBinding">
            ({{ vend.latestVendBinding.customer ? vend.latestVendBinding.customer.code : null }})
            {{ vend.latestVendBinding.customer ? vend.latestVendBinding.customer.name : null }}
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
                      <!-- <th scope="col" class="px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        #
                      </th> -->
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        #
                      </th>
                      <th scope="col" class="w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900" v-if="vend.productMapping">
                        Image
                      </th>
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        Sold
                      </th>
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        Bal
                      </th>
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        Cap
                      </th>
                      <th scope="col" class="w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        Price
                        <span v-if="profile && profile.base_currency">
                          ({{ profile.base_currency.currency_symbol }})
                        </span>
                      </th>
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        Error
                      </th>
                      <th scope="col" class="w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900" v-if="vend.productMapping">
                        Product
                      </th>
                    </tr>
                  </thead>
                  <tbody class="bg-white">
                    <tr v-for="(channel, channelIndex) in vend.vendChannelsJson" :key="channel.id" :class="channelIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                      <!-- <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                        {{ channelIndex + 1 }}
                      </td> -->
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                        {{ channel.code }}
                      </td>
                      <td class="whitespace-nowrap text-sm  font-semibold text-gray-900 text-center" v-if="vend.productMapping">
                        <div class="flex justify-center items-center" >
                          <img class="h-16 w-16 rounded-full" :src="channel.product.thumbnail.full_url" alt="" v-if="channel.product && channel.product.thumbnail"/>
                        </div>
                      </td>

                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-blue-600 sm:pl-6 text-center">
                        {{ channel.capacity - channel.qty }}
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                        {{ channel.qty }}
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                        {{ channel.capacity }}
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                        {{ (channel.amount/100).toLocaleString(undefined, {minimumFractionDigits: 2}) }}
                      </td>
                      <td class="py-1 pl-1 pr-1 text-xs font-medium text-gray-900 sm:pl-1 text-center">
                        <span
                          v-if="channel.vend_channel_error_logs
                                && channel.vend_channel_error_logs[0]
                                && channel.vend_channel_error_logs[0].is_error_cleared == 0"
                        >
                            <div
                              :class="[
                                  channel.vend_channel_error_logs[0].vend_channel_error.code == 4 ||
                                  channel.vend_channel_error_logs[0].vend_channel_error.code == 5 ||
                                  channel.vend_channel_error_logs[0].vend_channel_error.code == 7 ?
                                  ' text-blue-800' :
                                  ' text-red-800'
                                  ]">
                              <span class="font-bold">
                              ({{ channel.vend_channel_error_logs[0].vend_channel_error.code }})
                              </span>
                              <div>
                                {{formatDatetime(channel.vend_channel_error_logs[0].created_at)}}
                              </div>
                            </div>
                        </span>
                      </td>
                      <td class="py-4 text-sm font-semibold text-gray-900 text-center" v-if="vend.productMapping">
                        <span v-if="channel.product && channel.product.code">
                          {{ channel.product.code }}
                        </span>
                        <span class="break-normal text-xs" v-if="channel.product && channel.product.name">
                          <br> {{ channel.product.name }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        <p class="flex flex-col items-end text-blue-800 text-sm p-3" v-if="vend.productMapping">
          <span class="" v-if="vend.productMapping.name">
            {{ vend.productMapping.name }}
          </span>
          <span v-if="vend.productMapping.remarks">
            {{ vend.productMapping.remarks }}
          </span>
        </p>
      </template>
    </Modal>
  </Teleport>
</template>

<script setup>
import Modal from '@/Components/Modal.vue';
// import { onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
  vend: Object,
  showModal: Boolean,
})
const profile = usePage().props.auth.profile

const emit = defineEmits(['modalClose'])

function formatDatetime(value) {
  return moment(value).format('YYMMDD hh:mm A');
}


</script>