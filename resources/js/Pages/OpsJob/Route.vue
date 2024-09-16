<template>
  <Head title="VM Edit" />
  <BreezeAuthenticatedLayout>
    <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600" v-if="opsJob && opsJob.id">
            Route Planning
          </span>
        </div>
      </template>
    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
        <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll p-5">
          <form @submit.prevent="submit" id="submit">
          <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">
            <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Date
              </label>
              <div class="mt-1">
                <input
                  type="text"
                  class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                  :value=" opsJob ? opsJob.date_formatted : ''"
                  disabled
                />
              </div>
            </div>
            <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Delivery By
              </label>
              <div class="mt-1">
                <input
                  type="text"
                  class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                  :value=" opsJob && opsJob.deliveredBy ? opsJob.deliveredBy.name : ''"
                  disabled
                />
              </div>
            </div>
            <div class="sm:col-span-5">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Total Job(s)
              </label>
              <div class="mt-1">
                <input
                  type="text"
                  class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                  :value=" opsJob.opsJobItems ? opsJob.opsJobItems.length : 0 "
                  disabled
                />
              </div>
            </div>
<!--
            <div class="sm:col-span-3">
              <label for="text" class="block text-sm font-medium text-gray-700">
                Machine Prefix
              </label>
              <MultiSelect
                v-model="filters.vendPrefixes"
                :options="vendPrefixOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                mode="tags"
                class="mt-1"
              >
              </MultiSelect>
            </div> -->


            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
                <div class="relative">
                  <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-gray-300"></div>
                  </div>
                  <div class="relative flex justify-center">
                    <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> Current Sequence </span>
                  </div>
                </div>
              </div>

              <div class="sm:col-span-6 flex flex-col">
              <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5">
                <div class="inline-block min-w-full py-2 align-middle md:px-4 lg:px-6">
                  <div class="overflow-scroll max-h-[600px] md:max-h-[800px] shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-300">
                      <thead class="bg-gray-50">
                        <tr class="">
                          <TableHead>
                            <span>
                              Job Sequence
                            </span>
                          </TableHead>
                          <TableHead>
                            <div class="flex flex-col space-y-2">
                              <span>
                                Machine ID
                              </span>
                              <span>
                                Job ID#
                              </span>
                              <span>
                                Remarks
                              </span>
                            </div>
                          </TableHead>
                          <TableHead>
                            <div class="flex flex-col space-y-2">
                              <span>
                                Customer(Prefix)
                              </span>
                              <span>
                                Ops Note
                              </span>
                            </div>
                          </TableHead>
                          <TableHead>
                            Address
                          </TableHead>
                          <TableHead>
                            Action
                          </TableHead>
                        </tr>

                      </thead>
                      <tbody class="bg-white">
                        <tr v-for="(opsJobItem, opsJobItemIndex) in opsJob.opsJobItems" :key="opsJobItem.id" :class="opsJobItemIndex % 2 === 0 ? undefined : 'bg-gray-100'">
                          <td class="whitespace-nowrap py-2 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                            {{ opsJobItem.sequence }}
                          </td>
                          <td class="whitespace-pre-line py-2 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                            <div class="flex flex-col space-y-2 max-w-24">
                              <Link :href="'/vends/customers?codes=' + opsJobItem.vend.code" class="text-blue-700">
                                <span>
                                  {{ opsJobItem.vend.code }}
                                </span>
                              </Link>
                              <div>
                                <Link :href="'/ops-jobs/items/' + opsJobItem.id + '/edit'">
                                  <Button
                                    class="bg-indigo-400 hover:bg-indigo-500 text-white text-xs font-medium"
                                    v-if="permissions.includes('update operations')"
                                  >
                                    {{ opsJobItem.ref_id }}
                                  </Button>
                                </Link>
                              </div>
                              <div class="text-left text-red-800">
                                {{ opsJobItem.remarks }}
                              </div>
                            </div>
                          </td>
                          <td class="whitespace py-2 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left">
                            <div class="flex flex-col space-y-1 max-w-40 md:max-w-72">
                              <span>
                                <span v-if="opsJobItem.customer && opsJobItem.customer.person_id">
                                    {{ opsJobItem.customer.virtual_customer_code }} ({{ opsJobItem.vend && opsJobItem.vend.vendPrefix ? opsJobItem.vend.vendPrefix.name : '' }})  - {{ opsJobItem.customer.name }}
                                </span>
                                <span v-else>
                                  <span v-if="opsJobItem.customer && opsJobItem.customer.code">
                                    {{ opsJobItem.customer.code }} <br>
                                  </span>
                                  {{ opsJobItem.customer && opsJobItem.customer.name ? opsJobItem.customer.name : ''}}
                                </span>
                              </span>
                              <span v-if="opsJobItem.customer && opsJobItem.customer.deliveryAddress">
                                <div class="flex space-x-2 items-center font-medium text-xs">
                                  <span class="flex space-x-1 items-center" v-if="opsJobItem.customer && opsJobItem.customer.deliveryAddress">
                                    <span>
                                      <Button
                                      type="button" class="bg-sky-300 hover:bg-sky-400 px-3 py-1 text-xs text-sky-800 flex space-x-1 w-fit"
                                      @click="onMapMarkerClicked(opsJobItem)"
                                      v-if="opsJobItem.customer.deliveryAddress && opsJobItem.customer.deliveryAddress.latitude && opsJobItem.customer.deliveryAddress.longitude"
                                      >
                                        <MapPinIcon class="h-3 w-3" aria-hidden="true"/>
                                      </Button>
                                    </span>
                                    <a
                                      :href="opsJobItem.customer && opsJobItem.customer.deliveryAddress && opsJobItem.customer.deliveryAddress.map_url
                                        ? opsJobItem.customer.deliveryAddress.map_url
                                        : (opsJobItem.customer.deliveryAddress.latitude && opsJobItem.customer.deliveryAddress.longitude
                                          ? 'https://www.google.com/maps/search/?api=1&query=' + opsJobItem.customer.deliveryAddress.latitude + ',' + opsJobItem.customer.deliveryAddress.longitude
                                          : '')"
                                      target="_blank"
                                      rel="noopener noreferrer"
                                      type="button"
                                      class="bg-green-300 hover:bg-green-400 px-3 py-2 text-xs text-green-800 flex space-x-1 w-fit rounded shadow font-bold"
                                    >
                                      GPS
                                    </a>
                                  </span>
                                  <span>
                                    {{ opsJobItem.customer.deliveryAddress.postcode }}
                                  </span>
                                </div>
                              </span>
                              <span class="text-left">
                                {{ opsJobItem.ops_note }}
                              </span>
                            </div>
                          </td>
                          <td class="whitespace-pre-line py-2 px-1 text-sm text-left">
                            <div class="flex flex-col space-y-2 break-words max-w-32 md:max-w-72" v-if="opsJobItem.customer && opsJobItem.customer.deliveryAddress">
                              <span>
                                <a :href="opsJobItem.customer.deliveryAddress.map_url" v-if="opsJobItem.customer.deliveryAddress.map_url" class="text-blue-700" target="_blank">
                                  {{ opsJobItem.customer.deliveryAddress.full_address }}
                                </a>
                                <span v-else>
                                  {{ opsJobItem.customer.deliveryAddress.full_address }}
                                </span>
                              </span>
                              <span v-if="!opsJobItem.customer.deliveryAddress.full_address">
                                <a :href="opsJobItem.customer.deliveryAddress.map_url" v-if="opsJobItem.customer.deliveryAddress.map_url" class="text-blue-700" target="_blank">
                                  {{ opsJobItem.customer.deliveryAddress.postcode }}
                                </a>
                                <span v-else>
                                  {{ opsJobItem.customer.deliveryAddress.postcode }}
                                </span>
                              </span>
                            </div>
                          </td>
                          <td class="whitespace-nowrap py-2 px-1 text-sm text-center">
                          </td>
                        </tr>
                        <tr v-if="!opsJob.opsJobItems || !opsJob.opsJobItems.length">
                          <td colspan="11" class="whitespace-nowrap py-2 text-sm font-medium text-black text-center">
                            No Records Found
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
              </div>


            <div class="sm:col-span-6 mt-4 px-1">
              <div class="flex flex-col space-y-1 md:space-y-0 md:flex-row md:justify-between">
                <div class="flex space-x-1 md:justify-end">
                  <Link :href="'/ops-jobs/' + opsJob.id + '/edit'">
                    <Button
                      type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1 "
                    >
                      <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
                      <span>
                        Back
                      </span>
                    </Button>
                  </Link>
                </div>
              </div>
            </div>
          </div>
        </form>
        </div>
      </div>
    </div>
  </div>

  <Channel
		v-if="showChannelModal"
		:opsJobItem="opsJobItemModel"
		:showModal="showChannelModal"
		@modalClose="onChannelClosed"
    @statusUpdated="statusUpdated"
    @updatedNoRefresh="onChannelUpdatedNoRefresh"
  >
  </Channel>

  <MapMarker
    v-if="showMapMarkerModal"
    :customers="customerModel"
    :api-key="mapApiKey"
    :showModal="showMapMarkerModal"
    isShowDirectionButton=true
    @modalClose="onMapMarkerModalClose"
  >
  </MapMarker>

  <PickList
		v-if="showPickListModal"
		:pickLists="pickLists"
    :type="pickListType"
		:showModal="showPickListModal"
		@modalClose="onPickListModalClose"
  >
  </PickList>

  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import Channel from '@/Pages/OpsJob/Channel.vue';
import ChangeDriver from '@/Pages/OpsJob/ChangeDriver.vue';
import MapMarker from '@/Components/MapMarker.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import PickList from '@/Pages/Vend/PickList.vue';
import SearchInput from '@/Components/SearchInput.vue';
import SingleSortItem from '@/Components/SingleSortItem.vue';
import TableHead from '@/Components/TableHead.vue';
import {ArrowUturnLeftIcon, ArrowsRightLeftIcon, ArrowsUpDownIcon, ClipboardDocumentCheckIcon, CurrencyDollarIcon, MapIcon, MapPinIcon, PaperClipIcon, PlayIcon, PlusCircleIcon, TrashIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";

const props = defineProps({
  mapApiKey: String,
  operatorsWithAddress: [Array, Object],
  opsJob: Object,
})

const booleanStrictOptions = ref([
    {id: 'true', value: 'Yes'},
    {id: 'false', value: 'No'},
])

const emit = defineEmits(['modalClose'])

const filters = ref({
    vend_code: '',
    customer: '',
  })

const form = ref(
  useForm(getDefaultForm())
)

const customerModel = ref([])
const operatorCountry = usePage().props.auth.operatorCountry
const operatorsWithAddress = ref([])
const opsJob = ref([])
const opsJobItemModel = ref([])
const permissions = usePage().props.auth.permissions
const pickLists = ref([])
const pickListType = ref(1)
const showChannelModal = ref(false)
const showChangeDriverModal = ref(false)
const showMapMarkerModal = ref(false)
const showPickListModal = ref(false)
const toast = useToast()

onMounted(() => {
  opsJob.value = props.opsJob.data
  operatorsWithAddress.value = props.operatorsWithAddress.data.map(operator => ({
    id: operator.id,
    name: operator.name,
    address: operator.address.full_address,
    value: '(' + operator.name + ')' + ' - ' + operator.address.full_address,
  }))
})

function getDefaultForm() {
  return {
    id: '',
    vend_id: '',
  }
}

function addOpsJobItem() {
  form.value
    .transform((data) => ({
      ...data,
      vend_id: data.vend_id.id,
    }))
    .post('/ops-jobs/' + opsJob.value.id + '/item/create', {
      onSuccess: () => {
        toast.success("Successfully Saved", {
          timeout: 3000
        });
        form.value.vend_id = ''
        opsJob.value = props.opsJob.data
      },
      preserveState: true,
      replace: true,
    })
}

function syncCMSInvoices() {
  form.value.post('/ops-jobs/' + opsJob.value.id + '/sync-cms-invoices', {
    onSuccess: () => {
      toast.success("Data Sent to CMS", {
        timeout: 3000
      });
    },
    preserveState: true,
    replace: true,
  })
}

function deleteOpsJobItem(opsJobItem) {
  const approval = confirm('Are you sure to delete this entry?');
  if (!approval) {
      return;
  }

  form.value.delete('/ops-jobs/items/' + opsJobItem.id, {
    onSuccess: () => {
      toast.success("Successfully Deleted", {
        timeout: 3000
      });
      opsJob.value = props.opsJob.data
    },
    preserveState: true,
    replace: true,
  })
}

// reload opsJob when modal opened
function onChannelClicked(obj) {
  router.reload({
    only: ['opsJob'],
    data: {
      ...filters.value,
    },
    replace: true,
    preserveState: true,
    onSuccess: page => {
      opsJob.value = props.opsJob ? props.opsJob.data : null
    }
  })
  // get the opsJobItem where obj is the opsJobItem
    opsJobItemModel.value = opsJob.value.opsJobItems.find(item => item.id === obj.id)
    showChannelModal.value = true
}

function onChannelClosed() {
    showChannelModal.value = false
}

function onChannelUpdatedNoRefresh() {
  router.reload({
    only: ['opsJob'],
    data: {
      ...filters.value,
    },
    replace: true,
    preserveState: true,
    onSuccess: page => {
      opsJob.value = props.opsJob ? props.opsJob.data : null
    }
  })
}

function onDeleteClicked() {
  const approval = confirm('Are you sure to delete this entry?');
  if (!approval) {
      return;
  }

  form.value.delete('/ops-jobs/' + opsJob.value.id, {
    onSuccess: () => {
      toast.success("Successfully Deleted", {
        timeout: 3000
      });
    },
    preserveState: true,
    replace: true,
  })
}

function onGeneratePickListClicked() {
    axios({
        method: 'POST',
        url: '/vends/pick-lists',
        // get all the vends from the opsJobItems
        data: opsJob.value.opsJobItems,
    }).then(response => {
        pickLists.value = response.data
        pickListType.value = 1
    }).catch(error => {
    }).finally(() => {
        showPickListModal.value = true
    })
}

function onGenerateDeliveredListClicked() {
  axios({
        method: 'POST',
        url: '/ops-jobs/delivered-lists',
        // get all the vends from the opsJobItems
        data: opsJob.value.opsJobItems,
    }).then(response => {
        pickLists.value = response.data
        pickListType.value = 2
    }).catch(error => {
    }).finally(() => {
        showPickListModal.value = true
    })
}

function onMapMarkerClicked(opsJobItem) {
  axios({
    method: 'POST',
    url: '/customers/map',
    data: [{
      ops_job_item_id: opsJobItem.id,
      sequence: opsJobItem.sequence,
      customer_id: opsJobItem.customer.id,
    }],
  })
  .then((response) => {
    customerModel.value = [{
      ops_job_item_id: opsJobItem.id,
      sequence: opsJobItem.sequence,
      ...response.data.data[0],
    }];
    showMapMarkerModal.value = true; // Open the modal to show the map
  })
  .catch((error) => {
    console.error('API Error:', error);  // Log errors to debug
  });
}

function onMapAllMarkerClicked() {
  // Extract all the opsJobItems' customer information and send the request
  const opsJobItems = opsJob.value.opsJobItems.map((item) => ({
    ops_job_item_id: item.id,
    sequence: item.sequence,
    customer_id: item.customer.id,
  }));

  axios({
    method: 'POST',
    url: '/customers/map',
    data: opsJobItems,  // Send all opsJobItems for mapping
  })
  .then((response) => {
    customerModel.value = response.data.data.map((customerData, index) => ({
      ...customerData,
      sequence: opsJobItems[index].sequence,  // Maintain the correct sequence for each customer
      ops_job_item_id: opsJobItems[index].ops_job_item_id,  // Add ops_job_item_id
    }));
    showMapMarkerModal.value = true;  // Open the map modal with all markers
  })
  .catch((error) => {
    console.error('API Error:', error);  // Handle the API error
  });
}




function onMapMarkerModalClose() {
  showMapMarkerModal.value = false
}

function onPickListModalClose() {
  showPickListModal.value = false
}

function onRenumberClicked(opsJobItem) {
  form.value.clearErrors()
  form.value
    .transform((data) => ({
      ...data,
    }))
    .post('/ops-jobs/' + opsJob.value.id + '/renumber', {
    onSuccess: () => {
      toast.success("Successfully Renumbered", {
        timeout: 3000
      });
      opsJob.value = props.opsJob.data
    },
    preserveState: true,
    replace: true,
  })
}

function onSearchFilterUpdated() {
  router.reload({
    only: ['opsJob'],
    data: {
      ...filters.value,
    },
    replace: true,
    preserveState: true,
    onSuccess: page => {
      opsJob.value = props.opsJob ? props.opsJob.data : null
    }
  })
}

function opsJobItemChannelErrorCheck(opsJobItem) {
  let status = 0;

  opsJobItem.opsJobItemChannels.forEach(channel => {

    if(opsJobItem.status >= 3) {
      if (channel.virtual_is_error == 1 && channel.is_error_settle == 0) {
        status = 2; // Highest status
      } else if (channel.virtual_is_error == 1 && channel.is_error_settle == 1 && status < 2) {
        status = 1; // Middle status
      } else if (channel.virtual_is_error == 0 && status < 1) {
        status = 0; // Lowest status
      }
    }else {
      status = 0;
    }

  });

  return status;
}

function sortTable(sortKey) {
  filters.value.sortKey = sortKey
  filters.value.sortBy = !filters.value.sortBy
  onSearchFilterUpdated()
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
      statusClass = 'bg-green-400 text-gray-800'
      break;
    case 4:
      statusClass = 'bg-indigo-400 text-gray-800'
      break;
    case 98:
    case 99:
      statusClass = 'bg-red-500 text-white'
      break;
  }
  return statusClass
}

function statusUpdated() {
  router.reload({
    only: ['opsJob'],
    data: {
      ...filters.value,
    },
    replace: true,
    preserveState: true,
    onSuccess: page => {
      opsJob.value = props.opsJob ? props.opsJob.data : null
    }
  })
}

function updateSequence(opsJobItem, sequence) {
  form.value.clearErrors()
  form.value
    .transform((data) => ({
      ...data,
      sequence: opsJobItem.sequence,
    }))
    .post('/ops-jobs/items/' + opsJobItem.id + '/update', {
    onSuccess: () => {
      emit('modalClose')
    },
    preserveState: true,
    replace: true,
  })
}

</script>