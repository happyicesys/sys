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
                <table class="min-w-full divide-y divide-gray-300">
                  <thead class="bg-gray-50">
                    <tr>
                      <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                        #
                      </th>
                      <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                        Channel #
                      </th>
                      <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                        Qty
                      </th>
                      <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                        Capacity
                      </th>
                      <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                        Price
                      </th>
                      <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                        Error
                      </th>
                    </tr>
                  </thead>
                  <tbody class="bg-white">
                    <tr v-for="(channel, channelIndex) in vend.vendChannelsJson" :key="channel.id" :class="channelIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                        {{ channelIndex + 1 }}
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                        {{ channel.code }}
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
import Modal from '@/Components/Modal.vue';

const props = defineProps({
  vend: Object,
  showModal: Boolean,
})

const emit = defineEmits(['modalClose'])

function formatDatetime(value) {
  return moment(value).format('YYMMDD hh:mm A');
}


</script>