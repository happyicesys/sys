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
                      <th scope="col" class="w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900" v-if="vend.product_mapping_name">
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
                      <th
                        scope="col"
                        class="w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"
                        v-if="channels.some(channel => 'amount2' in channel)"
                      >
                        Price 2
                        <span v-if="profile && profile.base_currency">
                          ({{ profile.base_currency.currency_symbol }})
                        </span>
                      </th>
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        Group
                      </th>
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        Error
                      </th>
                      <th scope="col" class="w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900" v-if="vend.product_mapping_name">
                        Product
                      </th>
                      <th
                        scope="col"
                        class="w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"
                        v-if="channels.some(channel => 'sku_code' in channel)"
                      >
                        Product
                      </th>
                      <th
                        scope="col"
                        class="w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"
                        v-if="channels.some(channel => 'locked_qty' in channel)"
                      >
                        Locked Qty
                      </th>
                      <th scope="col" class="w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"  v-if="permissions.includes('admin-access vends')">
                        <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-gray-200 px-4 py-2 md:px-3 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        @click="showFilters = true"
                        v-if="!showFilters && vend.is_mqtt"
                        >
                          <ChevronDoubleDownIcon class="h-3 w-3" aria-hidden="true"/>
                        </Button>
                        <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-4 py-2 md:px-3 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        @click="showFilters = false"
                        v-if="showFilters && vend.is_mqtt"
                        >
                          <ChevronDoubleUpIcon class="h-3 w-3" aria-hidden="true"/>
                        </Button>
                      </th>
                    </tr>
                  </thead>
                  <tbody class="bg-white">
                    <tr v-for="(channel, channelIndex) in channels" :key="channel.id" :class="channelIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                        {{ channel.code }}
                      </td>
                      <td class="whitespace-nowrap text-sm  font-semibold text-gray-900 text-center" v-if="vend.product_mapping_name">
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
                      <td
                        class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"
                        v-if="channels.some(channel => 'amount2' in channel)"
                      >
                        {{ (channel.amount2/100).toLocaleString(undefined, {minimumFractionDigits: 2}) }}
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                        {{ channel.discount_group }}
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
                      <td class="py-4 text-sm font-semibold text-gray-900 text-center" v-if="vend.product_mapping_name">
                        <span v-if="!editable">
                          <span v-if="channel.product && channel.product.code">
                            {{ channel.product.code }}
                          </span>
                          <span class="break-normal text-xs" v-if="channel.product && channel.product.name">
                            <br> {{ channel.product.name }}
                          </span>
                        </span>
                        <span class="font-normal text-xs text-gray-700" v-else>
                          <!-- {{ channel.option_data }} -->
                          <MultiSelect
                            v-model="channel.product.option_data"
                            :options="productOptions"
                            trackBy="id"
                            valueProp="id"
                            label="full_name"
                            placeholder="Select"
                            open-direction="bottom"
                            class="mt-1"
                          >
                          </MultiSelect>
                        </span>
                      </td>
                      <td
                        class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"
                        v-if="channels.some(channel => 'sku_code' in channel)"
                      >
                        {{ channel.sku_code }}
                      </td>
                      <td
                        class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"
                        v-if="channels.some(channel => 'locked_qty' in channel)"
                      >
                        {{ channel.locked_qty }}
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"
                      v-if="permissions.includes('admin-access vends')">
                        <div class="flex justify-center space-x-1">
                          <Button
                            type="button" class="bg-yellow-300 hover:bg-yellow-400 px-1 py-1 text-xs text-gray-800 flex space-x-1"
                            @click="onDispenseClicked(channel)"
                            v-if="vend.is_mqtt && showFilters"
                          >
                            <span>
                              Dispense
                            </span>
                          </Button>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        <div class="flex justify-between">
          <span>
            <Button
                type="button"
                class=" px-2 py-1 mt-2 ml-1 text-xs  flex space-x-1"
                :class="[editable ? 'bg-green-300 hover:bg-green-400 text-green-800' : 'bg-gray-300 hover:bg-gray-400 text-gray-800']"
                @click="onEditClicked()"
                v-if="vend.product_mapping_name"
            >
              <span class="flex space-x-1" v-if="!editable">
                <PencilSquareIcon class="w-4 h-4"></PencilSquareIcon>
                <span>
                    Edit Product
                </span>
              </span>
              <span class="flex space-x-1" v-else>
                <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                <span>
                  Save
                </span>
              </span>
            </Button>
          </span>
          <span class="flex flex-col text-blue-800 text-sm p-3" v-if="vend.product_mapping_name">
            <span class="" v-if="vend.product_mapping_name">
            {{ vend.product_mapping_name }}
            </span>
            <span v-if="vend.product_mapping_remarks">
              {{ vend.product_mapping_remarks }}
            </span>
          </span>
        </div>
      </template>
    </Modal>
  </Teleport>
</template>

<script setup>
import { ChevronDoubleDownIcon, ChevronDoubleUpIcon, CheckCircleIcon, PencilSquareIcon } from '@heroicons/vue/20/solid';
import Button from '@/Components/Button.vue';
import Modal from '@/Components/Modal.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
  productOptions: Object,
  vend: Object,
  showModal: Boolean,
})

const channels = ref([])
const permissions = usePage().props.auth.permissions
const productOptions = ref([])
const showFilters = ref(false)


onMounted(() => {
  productOptions.value = props.productOptions.data.map((data) => {return {id: data.id, full_name: data.full_name + (data.desc ?  ' ' + data.desc  : '')}})

  channels.value = props.vend.vendChannelsJson.map((channel) => {
    return {
      ...channel,
      product: channel.product ? {
        ...channel.product,
        option_data: {
          'id' : channel.product.id,
          'full_name' : channel.product.code + ' - ' + channel.product.name + (channel.product.desc ?  ' ' + channel.product.desc  : '')
        }
      } : null
    }
  })
})
const profile = usePage().props.auth.profile
const editable = ref(false)
const emit = defineEmits(['modalClose'])

function onDispenseClicked(channel) {
  router.post('/vends/' + props.vend.id + '/dispense-product', {
    channel_id: channel.id
  }, {
    preserveScroll: true,
    onSuccess: () => {
      emit('modalClose')
    }
  })
}

function onEditClicked() {
  if(editable.value) {
    router.post('/vends/' + props.vend.id + '/edit-products', {
      channels: channels.value.map((channel) => {
        return {
          id: channel.id,
          product_id : channel.product.id,
          edited_product_id: channel.product.option_data.id
        }
      })
    }, {
      preserveScroll: true,
      onSuccess: () => {
        editable.value = false
        emit('modalClose')
      }
    })

  }else {
    editable.value = true
  }
}

function formatDatetime(value) {
  return moment(value).format('YYMMDD hh:mm A');
}


</script>