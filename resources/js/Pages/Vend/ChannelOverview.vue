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
import Button from '@/Components/Button.vue';
import FormInput from '@/Components/FormInput.vue';
import Modal from '@/Components/Modal.vue';
import MultiSelect from '@/Components/MultiSelect.vue'
import SearchAddressInput from '@/Components/SearchAddressInput.vue';
import { ArrowUturnLeftIcon, CheckCircleIcon } from '@heroicons/vue/20/solid';

const props = defineProps({
  vend: Object,
  showModal: Boolean,
})

const emit = defineEmits(['modalClose'])

// onMounted(() => {
//   countryOptions.value = props.countries.data
//   form.value = props.profile ? useForm(props.profile) : useForm(getDefaultForm())
// })


</script>