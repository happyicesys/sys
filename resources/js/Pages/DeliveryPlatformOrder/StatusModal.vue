<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col space-y-1">
          <span class="text-gray-700">
            Delivery Order {{ deliveryPlatformOrder.short_order_id }} Status
          </span>
          <span v-if="deliveryPlatformOrder.vend_code">
            {{ deliveryPlatformOrder.deliveryProductMappingVend.vend.cust_full_name }}
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
                        Datetime
                      </th>
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        Status
                      </th>
                    </tr>
                  </thead>
                  <tbody class="bg-white" v-if="deliveryPlatformOrder.status_json && typeof(deliveryPlatformOrder.status_json.status) === 'string'">
                    <tr>

                       <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                        {{ 1 }}
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-600 sm:pl-6 text-center">
                        {{ deliveryPlatformOrder.status_json.datetime }}
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-blue-900 sm:pl-6 text-center">
                        <div class="w-xs">
                          <div
                              class="inline-flex justify-center items-center rounded px-1 py-0.5 text-xs font-medium border w-xs hover:cursor-pointer"
                              :class="statusClass(deliveryPlatformOrder.status_json.status)"
                          >
                              <div class="flex flex-col">
                                  <span class="font-semibold grow-0">
                                    {{ deliveryPlatformOrder.status_json.status }}
                                  </span>
                              </div>
                          </div>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                  <tbody class="bg-white" v-if="deliveryPlatformOrder.status_json && typeof(deliveryPlatformOrder.status_json.status) !== 'string'">
                    <tr v-for="(status, statusIndex) in deliveryPlatformOrder.status_json.status" :class="statusIndex % 2 === 0 ? undefined : 'bg-gray-50'"
                       >
                       <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                        {{ statusIndex + 1 }}
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-600 sm:pl-6 text-center">
                        {{ deliveryPlatformOrder.status_json.datetime[statusIndex] }}
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center">
                        <div class="w-xs">
                          <div
                              class="inline-flex justify-center items-center rounded px-1 py-0.5 text-xs font-medium border w-xs hover:cursor-pointer"
                              :class="statusClass(status)"
                          >
                              <div class="flex flex-col">
                                  <span class="font-semibold grow-0">
                                    {{ status }}
                                  </span>
                              </div>
                          </div>
                        </div>
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
import { onMounted, ref } from 'vue';

const props = defineProps({
  model: Object,
  showModal: Boolean,
})

const deliveryPlatformOrder = ref()


onMounted(() => {
  deliveryPlatformOrder.value = props.model
})
const emit = defineEmits(['modalClose'])

function formatDatetime(value) {
  return moment(value).format('YYMMDD hh:mm A');
}

function statusClass(status) {
  let statusClass = ''
  switch(status) {
    case 'Pending':
    case 'Accepted':
      statusClass = 'bg-blue-400 text-gray-800'
      break;
    case 'Assigned':
    case 'Arrived':
    case 'Requested':
    case 'Dispensed':
    case 'Collected':
      statusClass = 'bg-yellow-400 text-gray-800'
      break;
    case 'Delivered':
      statusClass = 'bg-green-400 text-white-800'
      break;
    case 'Cancelled':
    case 'Failed':
      statusClass = 'bg-red-400 text-white-800'
      break;
  }
  return statusClass
}


</script>