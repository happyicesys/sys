<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex space-x-3">
          <span v-if="vend.code">
            ID# {{ vend.code }}
          </span>
          <span v-if="vend.customer" class="text-gray-700">
            ({{ vend.customer.id + 20000 }})
            {{ vend.customer.name }}
          </span>
        </div>
      </template>
      <template #default>
        <div class="px-2 border-b mb-2 border-gray-100">
          <dl class="divide-y divide-gray-100">
            <div class="px-4 py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
              <dt class="text-sm font-medium leading-6 text-gray-900">
                Status
              </dt>
              <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                <div
                    class="inline-flex justify-center items-center rounded px-1 py-0.5 text-xs font-medium border w-xs"
                    :class="statusClass(opsJobItem.status)"
                >
                    <div class="flex flex-col">
                        <span class="font-semibold grow-0">
                          {{ opsJobItem.status_name }}
                        </span>
                    </div>
                </div>
              </dd>
            </div>
            <div class="px-4 py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0" v-if="opsJobItem.status >= 2">
              <dt class="text-sm font-medium leading-6 text-gray-900">
                Picked By
              </dt>
              <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                <div class="flex space-x-2">
                  <span>
                    {{ opsJobItem.pickedBy.name }}
                  </span>
                  <span>
                    ({{ opsJobItem.picked_at }})
                  </span>
                </div>
              </dd>
            </div>
            <div class="px-4 py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0" v-if="opsJobItem.status >= 3">
              <dt class="text-sm font-medium leading-6 text-gray-900">
                Stock In By
              </dt>
              <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                <div class="flex space-x-2">
                  <span>
                    {{ opsJobItem.completedBy.name }}
                  </span>
                  <span>
                    ({{ opsJobItem.completed_at }})
                  </span>
                </div>
              </dd>
            </div>
            <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0" v-if="opsJobItem.status >= 2">
              <dt class="text-sm font-medium leading-6 text-gray-900">
                Cash Collected
                <span class="text-red-500">*</span>
              </dt>
              <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                <div class="flex space-x-2">
                  <FormInput inputType="number" v-model="form.cash_amount" class="text-center" :disabled="opsJobItem.status >= 3">
                  </FormInput>
                </div>
              </dd>
            </div>
            <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0" v-if="opsJobItem.status >= 2">
              <dt class="text-sm font-medium leading-6 text-gray-900">
                Cashless Collected
                <span class="text-red-500">*</span>
              </dt>
              <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                <div class="flex space-x-2">
                  <FormInput inputType="number" v-model="form.cashless_amount" class="text-center" :disabled="opsJobItem.status >= 3">
                  </FormInput>
                </div>
              </dd>
            </div>
            <div class="px-4 py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0" v-if="opsJobItem.status >= 2">
              <dt class="text-sm font-medium leading-6 text-gray-900">
                Remarks
              </dt>
              <dd class="text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                <FormTextarea v-model="form.remarks" class="w-full" v-if="opsJobItem.status >= 2">
                </FormTextarea>
              </dd>
            </div>
          </dl>
        </div>
        <div class="flex justify-end mb-2">
        <Button
            type="button"
            class=" px-1 py-1 mt-1 ml-1 text-xs  flex space-x-1 bg-green-500 hover:bg-green-600 text-white"
            @click.prevent="onSaveFormClicked()"
        >
          <span class="flex space-x-1 items-center">
            <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
            <span>
              Save
            </span>
          </span>
        </Button>
        </div>


        <div class="flex flex-col">
          <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5">
            <div class="inline-block min-w-full py-2 align-middle md:px-4 lg:px-6">
              <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                <table class="min-w-full divide-y divide-gray-300">
                  <thead class="bg-gray-50">
                    <tr>
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        #
                      </th>
                      <th scope="col" class="w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        Image
                      </th>
                      <th scope="col" class="w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        Product
                      </th>
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        Cap
                      </th>
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        Bal
                      </th>
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        Needed
                      </th>
                      <th scope="col" class="w-2/12 px-3 py-3.5 text-center text-xs font-semibold" :class="[opsJobItem.status < 2 ? 'text-blue-700' : 'text-gray-900']">
                        Picked
                      </th>
                      <th scope="col" class="w-2/12 px-3 py-3.5 text-center text-xs font-semibold" :class="[opsJobItem.status == 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status >= 2">
                        Stock In
                      </th>
                    </tr>
                  </thead>
                  <tbody class="bg-white">
                    <tr v-for="(channel, channelIndex) in channels" :key="channel.id" :class="channelIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold sm:pl-6 text-center text-gray-800">
                        {{ channel.code }}
                      </td>
                      <td class="whitespace-nowrap text-sm  font-semibold text-gray-800 text-center" v-if="channel.product">
                        <div class="flex justify-center items-center" >
                          <img class="h-20 w-20 min-w-20 min-h-20 rounded-full" :src="channel.product.thumbnail.full_url" alt="" v-if="channel.product && channel.product.thumbnail" :class="[channel.product.is_available ? '' : 'opacity-50']"/>
                        </div>
                      </td>
                      <td class="py-4 text-sm font-semibold text-center" :class="[(channel.product && channel.product.is_available) ? 'text-gray-800' : 'text-gray-400']" v-if="channel.product">
                        <span v-if="!editable">
                          <span v-if="channel.product && channel.product.code">
                            {{ channel.product.code }}
                          </span>
                          <span class="break-normal text-xs" v-if="channel.product && channel.product.name">
                            <br> {{ channel.product.name }}
                          </span>
                        </span>
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center text-gray-800">
                        {{ channel.capacity }}
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center text-gray-800">
                        {{ channel.qty }}
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-bold sm:pl-6 text-center text-gray-900">
                        {{ channel.capacity - channel.qty }}
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center" :class="[opsJobItem.status < 2 ? 'text-blue-700' : 'text-gray-900']">
                        <FormInput inputType="number" v-model="channel.picked" :maxValue="channel.capacity - channel.qty" class="text-center" :disabled="channel.product && !channel.product.is_available" v-if="opsJobItem.status < 2">
                        </FormInput>
                        <span v-if="opsJobItem.status >= 2">
                          {{ channel.picked }}
                        </span>
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center" :class="[opsJobItem.status == 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status >= 2">
                        <FormInput inputType="number" v-model="channel.refill" :maxValue="channel.picked" class="text-center" :disabled="channel.product && !channel.product.is_available" v-if="opsJobItem.status >= 2 && opsJobItem.status < 3">
                        </FormInput>
                        <span v-if="opsJobItem.status > 2">
                          {{ channel.refill }}
                        </span>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        <div class="flex justify-end">
          <span>
            <Button
                type="button"
                class=" px-2 py-2 mt-2 ml-1 text-md  flex space-x-1 bg-yellow-400 hover:bg-yellow-500 text-gray-800"
                @click="onConfirmClicked()"
                v-if="opsJobItem.status < 2 && opsJobItem.status < 3"
            >
              <span class="flex space-x-1 items-center">
                <ClipboardDocumentCheckIcon class="w-4 h-4"></ClipboardDocumentCheckIcon>
                <span>
                  Picked
                </span>
              </span>
            </Button>
            <Button
                type="button"
                class=" px-2 py-2 mt-2 ml-1 text-md  flex space-x-1 bg-green-400 hover:bg-green-500 text-gray-800"
                @click="onConfirmClicked()"
                v-if="opsJobItem.status == 2 && opsJobItem.status < 3"
            >
            <span class="flex space-x-1 items-center">
                <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                <span>
                  Stock In
                </span>
              </span>
            </Button>

            <Button
                type="button"
                class=" px-2 py-2 mt-2 ml-1 text-md  flex space-x-1 bg-green-500 hover:bg-green-600 text-white"
                @click="onVerifyClicked(1)"
                v-if="opsJobItem.status >= 3 && opsJobItem.status != 4"
            >
              <span class="flex space-x-1 items-center">
                <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                <span>
                  Verified
                </span>
              </span>
            </Button>
            <Button
                type="button"
                class=" px-2 py-2 mt-2 ml-1 text-md  flex space-x-1 bg-red-500 hover:bg-red-700 text-white"
                @click="onVerifyClicked(0)"
                v-if="opsJobItem.status >= 3 && opsJobItem.status != 98"
            >
              <span class="flex space-x-1 items-center">
                <FlagIcon class="w-4 h-4"></FlagIcon>
                <span>
                  Flagged
                </span>
              </span>
            </Button>
          </span>
          <span v-if="vend.product_mapping_name">
            <a :href="'/product-mappings/' + vend.product_mapping_id + '/edit'" target="_blank" class="hover:cursor-pointer flex flex-col text-blue-800 text-sm p-3">
              <span class="" v-if="vend.product_mapping_name">
              {{ vend.product_mapping_name }}
              </span>
              <span v-if="vend.product_mapping_remarks">
                {{ vend.product_mapping_remarks }}
              </span>
            </a>
          </span>
        </div>
      </template>
    </Modal>
  </Teleport>
</template>

<script setup>
import { ChevronDoubleDownIcon, ChevronDoubleUpIcon, CheckCircleIcon, ClipboardDocumentCheckIcon, FlagIcon, PencilSquareIcon } from '@heroicons/vue/20/solid';
import Button from '@/Components/Button.vue';
import FormInput from '@/Components/FormInput.vue';
import FormTextarea from '@/Components/FormTextarea.vue';
import Modal from '@/Components/Modal.vue';
import { onMounted, ref } from 'vue';
import { router, usePage, useForm } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";

const props = defineProps({
  opsJobItem: Object,
  showModal: Boolean,
})

const channels = ref([])
const toast = useToast()
const vend = ref([])


onMounted(() => {
  vend.value = props.opsJobItem.vend

  form.value = props.opsJobItem ? useForm({
    ...props.opsJobItem,
  }) : useForm(getDefaultForm())

  channels.value = props.opsJobItem.opsJobItemChannels.map((opsJobItemChannel) => {
    return {
      ...opsJobItemChannel.vendChannel,
      ops_job_item_channel_id: opsJobItemChannel.id,
      picked: props.opsJobItem.status < 2 ? (opsJobItemChannel.vendChannel.product.is_available ? (opsJobItemChannel.vendChannel.capacity - opsJobItemChannel.vendChannel.qty) : 0) : opsJobItemChannel.picked_qty,
      refill: props.opsJobItem.status == 2 ? opsJobItemChannel.picked_qty : opsJobItemChannel.actual_qty,
      product: opsJobItemChannel.vendChannel.product ? {
        ...opsJobItemChannel.vendChannel.product,
      } : null
    }
  })
})
const form = ref(
  useForm(getDefaultForm())
)
const profile = usePage().props.auth.profile
const editable = ref(false)
const emit = defineEmits(['modalClose', 'statusUpdated'])

function getDefaultForm() {
  return {
    cash_amount: '',
    cashless_amount: '',
    remarks: '',
  }
}

function onConfirmClicked() {
  router.post('/ops-jobs/items/' + props.opsJobItem.id + '/confirm', {
    channels: channels.value.map((channel) => {
      return {
        id: channel.ops_job_item_channel_id,
        picked: channel.picked,
        refill: channel.refill,
      }
    }),
    ...form.value
  }, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success("Successfully Updated", {
        timeout: 3000
      });
      emit('statusUpdated')
    }
  })
}

function onSaveFormClicked() {
  router.post('/ops-jobs/items/' + props.opsJobItem.id + '/update', form.value, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success("Successfully Saved", {
        timeout: 3000
      });
    }
  })
}

function onVerifyClicked(verify) {
  router.post('/ops-jobs/items/' + props.opsJobItem.id + '/verify', {
    verify: verify
  }, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success("Successfully Verified", {
        timeout: 3000
      });
      emit('statusUpdated')
    }
  })
}

function formatDatetime(value) {
  return moment(value).format('YYMMDD hh:mm A');
}

function statusClass(status) {
  let statusClass = ''
  switch(status) {
    case 1:
      statusClass = 'bg-blue-400 text-white'
      break;
    case 2:
      statusClass = 'bg-yellow-400 text-gray-800'
      break;
    case 3:
    case 4:
      statusClass = 'bg-green-400 text-gray-800'
      break;
    case 98:
    case 99:
      statusClass = 'bg-red-500 text-white'
      break;
  }
  return statusClass
}


</script>