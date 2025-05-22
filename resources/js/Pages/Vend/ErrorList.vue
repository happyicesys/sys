<template>
  <Teleport to="body">
    <Modal :open="showErrorListModal" @modalClose="$emit('errorListModalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row md:space-x-4 text-black">
          <span class="text-gray-500">
            Error List
          </span>
          <span>
            Vend# {{ vend.code }}
          </span>
          <span>
            Channel# {{ channel.code }}
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
                        Transaction Datetime
                      </th>
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        Error
                      </th>
                    </tr>
                  </thead>
                  <tbody class="bg-white">
                    <tr v-for="(errorLog, errorLogIndex) in channel.vendChannelErrorLogs" :key="errorLog.id" :class="errorLogIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold sm:pl-6 text-center text-gray-900">
                        {{ errorLogIndex + 1 }}
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center text-gray 700">
                        {{ errorLog.created_at }}
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-left text-gray 700">
                        {{ errorLog.desc }}
                      </td>
                    </tr>
                    <tr v-if="!channel.vendChannelErrorLogs.length">
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
      </template>
    </Modal>
  </Teleport>
</template>

<script setup>
import Modal from '@/Components/Modal.vue';
import { onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
  channel: Object,
  vend: Object,
  showErrorListModal: Boolean,
})

const emit = defineEmits(['errorListModalClose'])

function formatDatetime(value) {
  return moment(value).format('YYMMDD hh:mm A');
}


</script>