<template>
  <Head title="VM Edit" />
  <BreezeAuthenticatedLayout>
    <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600" v-if="opsJob && opsJob.id">
            Editing Job

          </span>
          <span v-if="opsJob && opsJob.id">
            {{ opsJob.code }}
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
                Job ID#
              </label>
              <div class="mt-1">
                <input
                  type="text"
                  class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                  :value=" opsJob ? opsJob.code : ''"
                  disabled
                />
              </div>
            </div>
            <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Date
              </label>
              <div class="mt-1">
                <input
                  type="text"
                  class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                  :value=" opsJob ? opsJob.date : ''"
                  disabled
                />
              </div>
            </div>
            <div class="sm:col-span-5">
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


            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
                <div class="relative">
                  <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-gray-300"></div>
                  </div>
                  <div class="relative flex justify-center">
                    <span class="px-3 bg-white text-lg font-medium text-gray-900"> Machine(s) </span>
                  </div>
                </div>
              </div>

              <div class="sm:col-span-5">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Add Machine
                </label>
                <MultiSelect
                  v-model="form.vend_id"
                  :options="unbindedVendOptions"
                  trackBy="id"
                  valueProp="id"
                  label="full_name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                  ref="multiselect"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.vend_id">
                  {{ form.errors.vend_id }}
                </div>
              </div>

              <div class="sm:col-span-1">
                <Button
                type="button"
                @click="addOpsJobItem()"
                class="bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6"
                :class="[!form.vend_id ? 'opacity-50 cursor-not-allowed' : '']"
                :disabled="!form.vend_id && !permissions.includes('update operations')"
                >
                  <PlusCircleIcon class="w-4 h-4"></PlusCircleIcon>
                  <span>
                    Add
                  </span>
                </Button>
              </div>

              <div class="sm:col-span-6 flex flex-col mt-3">
              <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5">
                <div class="inline-block min-w-full py-2 align-middle md:px-4 lg:px-6">
                  <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-300">
                      <thead class="bg-gray-50">
                        <tr class="bg-gray-200">
                          <th scope="col" colspan="10" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            <span class="flex flex-col md:flex-row space-y-2 md:space-y-0 text-left md:space-x-2">
                              <SearchInput placeholderStr="Vend ID" v-model="filters.vend_code" @input="onSearchFilterUpdated()">
                                  Machine ID
                              </SearchInput>
                              <SearchInput placeholderStr="Customer" v-model="filters.customer" @input="onSearchFilterUpdated()">
                                  Customer
                              </SearchInput>
                            </span>
                          </th>
                        </tr>
                        <tr>
                          <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            #
                          </th>
                          <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            <div class="flex flex-col space-y-2">
                              <span>
                                Machine ID
                              </span>
                              <span>
                                Machine Prefix
                              </span>
                            </div>
                          </th>
                          <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Action
                          </th>
                          <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            <div class="flex flex-col space-y-2">
                              <span>
                                Status
                              </span>
                              <span>
                                Customer
                              </span>
                            </div>
                          </th>

                          <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Remarks
                          </th>
                          <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Ops Note
                          </th>
                          <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-sm font-semibold text-gray-900"
                            v-if="permissions.includes('admin-access operations')">
                            CMS Empty Inv
                          </th>
                        </tr>

                      </thead>
                      <tbody class="bg-white">
                        <tr v-for="(opsJobItem, opsJobItemIndex) in opsJob.opsJobItems" :key="opsJobItem.id" :class="opsJobItemIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                            {{ opsJobItemIndex + 1 }}
                            <!-- <input
                              type="text"
                              class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-fit text-sm border-gray-300 rounded-md"
                              v-model="opsJobItem.sequence"
                              /> -->
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                            <div class="flex flex-col space-y-2">
                              <span>
                                {{ opsJobItem.vend.code }}
                              </span>
                              <span>
                                {{ opsJobItem.vend && opsJobItem.vend.vendPrefix ? opsJobItem.vend.vendPrefix.name : '' }}
                              </span>
                            </div>
                          </td>
                          <td class="whitespace-nowrap py-3 px-3 text-xs font-semibold text-gray-900 text-center">
                            <Button
                              class="bg-green-400 hover:bg-green-500 text-white"
                              @click.prevent="onChannelClicked(opsJobItem)"
                              v-if="permissions.includes('update operations')"
                            >
                              <ArrowRightCircleIcon class="w-4 h-4"></ArrowRightCircleIcon>
                            </Button>
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left">
                            <div class="flex flex-col space-y-1">
                              <span>
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
                              </span>
                              <span>
                                <span v-if="opsJobItem.vend.customer && opsJobItem.vend.customer.person_id">
                                    {{ opsJobItem.vend.customer.virtual_customer_code }} ({{ opsJobItem.vend.customer.virtual_customer_prefix }})
                                    <br>
                                    {{ opsJobItem.vend.customer.name }}
                                </span>
                                <span v-else>
                                  <span v-if="opsJobItem.vend.customer && opsJobItem.vend.customer.code">
                                    {{ opsJobItem.vend.customer.code }} <br>
                                  </span>
                                  {{ opsJobItem.vend.customer && opsJobItem.vend.customer.name ? opsJobItem.vend.customer.name : ''}}
                                </span>
                              </span>
                            </div>
                          </td>
                          <td class="whitespace-pre-line py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left">
                            {{ opsJobItem.remarks }}
                          </td>
                          <td class="whitespace-pre-line py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left">
                            {{ opsJobItem.vend.customer ? opsJobItem.vend.customer.ops_note : '' }}
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center" v-if="permissions.includes('admin-access operations')">
                            <div class="flex items-center justify-center">
                              <span v-if="opsJobItem.cms_transaction_id">
                                <CheckCircleIcon class="w-4 h-4 text-green-500"></CheckCircleIcon>
                              </span>
                              <span v-else>
                                <XCircleIcon class="w-4 h-4 text-red-500"></XCircleIcon>
                              </span>
                            </div>
                          </td>
                          <!-- <td class="whitespace-nowrap py-4 text-sm text-center">
                            <Button
                              class="bg-red-400 hover:bg-red-500 text-white"
                              :class="[opsJobItem.status >= 3 ? 'opacity-50 cursor-not-allowed' : '']"
                              @click.prevent="deleteOpsJobItem(opsJobItem)"
                              v-if="permissions.includes('update operations')"
                              :disabled="opsJobItem.status >= 3"
                            >
                              <BackspaceIcon class="w-4 h-4"></BackspaceIcon>
                            </Button>
                          </td> -->
                        </tr>
                        <tr v-if="!opsJob.opsJobItems || !opsJob.opsJobItems.length">
                          <td colspan="10" class="whitespace-nowrap py-4 text-sm font-medium text-black text-center">
                            No Records Found
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
              </div>


            <div class="sm:col-span-6 mt-5 ">
              <div class="flex flex-col space-y-1 md:space-y-0 md:flex-row md:justify-between">
                <div class="flex flex-col space-y-1 md:space-y-0 md:flex-row md:space-x-1">
                  <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                  @click.prevent="onGeneratePickListClicked()"
                  v-if="opsJob.opsJobItems && opsJob.opsJobItems.length && opsJob.opsJobItems.some(item => item.status < 2)"
                  >
                    <ClipboardDocumentCheckIcon class="h-4 w-4" aria-hidden="true"/>
                    <span class="flex flex-col space-y-1">
                      <span>
                          Generate Live Pick List
                      </span>
                    </span>
                  </Button>
                  <Button class="inline-flex space-x-1 items-center rounded-md border border-yellow bg-yellow-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-black shadow-sm hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                  @click.prevent="createCMSEmptyInvoices()"
                  v-if="!opsJob.opsJobItems || opsJob.opsJobItems.length && opsJob.opsJobItems.some(item => item.cms_transaction_id == null)"
                  >
                    <ClipboardDocumentCheckIcon class="h-4 w-4" aria-hidden="true"/>
                    <span class="flex flex-col space-y-1">
                      <span>
                          Create CMS Empty Invoice(s)
                      </span>
                    </span>
                  </Button>
                </div>
                <div class="flex space-x-1 md:justify-end">
                  <Link :href="'/ops-jobs'">
                    <Button
                      type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1 "
                    >
                      <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
                      <span>
                        Back
                      </span>
                    </Button>
                  </Link>

                  <!-- <Button type="submit" class="bg-green-500 hover:bg-green-600 text-white flex space-x-1">
                    <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                    <span>
                      Save
                    </span>
                  </Button> -->
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
  >
  </Channel>

  <PickList
		v-if="showPickListModal"
		:pickLists="pickLists"
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
import MultiSelect from '@/Components/MultiSelect.vue';
import PickList from '@/Pages/Vend/PickList.vue';
import SearchInput from '@/Components/SearchInput.vue';
import { ArrowUturnLeftIcon, BackspaceIcon, ClipboardDocumentCheckIcon, CheckCircleIcon, PlusCircleIcon, XCircleIcon, ArrowRightCircleIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";

const props = defineProps({
  opsJob: Object,
  unbindedVendOptions: [Array, Object],
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

const opsJob = ref([])
const opsJobItemModel = ref([])
const permissions = usePage().props.auth.permissions
const pickLists = ref([])
const showChannelModal = ref(false)
const showPickListModal = ref(false)
const toast = useToast()
const unbindedVendOptions = ref([])

onMounted(() => {
  opsJob.value = props.opsJob.data
  unbindedVendOptions.value = props.unbindedVendOptions.data.map(vend => {
    return {
      id: vend.id,
      full_name: vend.cust_full_name,
    }
  })
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

function createCMSEmptyInvoices() {
  form.value.post('/ops-jobs/' + opsJob.value.id + '/create-cms-empty-invoices', {
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

function onGeneratePickListClicked() {
    axios({
        method: 'POST',
        url: '/vends/pick-lists',
        // get all the vends from the opsJobItems
        data: opsJob.value.opsJobItems,
    }).then(response => {
        pickLists.value = response.data
    }).catch(error => {
    }).finally(() => {
        showPickListModal.value = true
    })
}

function onPickListModalClose() {
  showPickListModal.value = false
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

function statusUpdated() {
  showChannelModal.value = false

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

function submit() {
  form.value.clearErrors()
    form.value
      .transform((data) => ({
        ...data,
        vendPrefixes: vendPrefixes.value,
      }))
      .post('/vend-configs/' + form.value.id + '/update', {
      onSuccess: () => {
        emit('modalClose')
      },
      preserveState: true,
      replace: true,
    })
}

</script>