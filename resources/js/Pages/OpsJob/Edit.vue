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
                <SearchVendCodeWithOperatorInput v-model="form.vend_id" @selected="selected" required="true" :error="form.errors.vend_id">
                  Machine to Bind
                </SearchVendCodeWithOperatorInput>
                <div class="text-sm text-red-600" v-if="form.errors.vend_id">
                  {{ form.errors.vend_id }}
                </div>
              </div>

              <div class="sm:col-span-1">
                <Button
                type="button"
                @click="bindOperatorVend()"
                class="bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6"
                :class="[!form.vend_id ? 'opacity-50 cursor-not-allowed' : '']"
                :disabled="!form.vend_id && !permissions.includes('update operators')"
                >
                  <PlusCircleIcon class="w-4 h-4"></PlusCircleIcon>
                  <span>
                    Add
                  </span>
                </Button>
              </div>

              <div class="sm:col-span-6 flex flex-col mt-3" v-if="form.id">
              <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5">
                <div class="inline-block min-w-full py-2 align-middle md:px-4 lg:px-6">
                  <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-300">
                      <thead class="bg-gray-50">
                        <tr>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Vend ID
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Customer
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Action
                          </th>
                        </tr>

                      </thead>
                      <tbody class="bg-white">
                        <tr v-for="(opsJobItem, opsJobItemIndex) in opsJob.opsJobItems" :key="opsJobItem.id" :class="opsJobItemIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                            <!-- input of sequence for ops job item -->
                            <input
                              type="text"
                              class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md"
                              v-model="opsJobItem.sequence"
                              />
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                            {{ opsJobItem.vend.code }}
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left">
                            <span v-if="opsJobItem.customer && opsJobItem.customer.person_id">
                                {{ opsJobItem.customer.virtual_customer_code }} ({{ opsJobItem.customer.virtual_customer_prefix }})
                                <br>
                                {{ opsJobItem.customer.name }}
                            </span>
                            <span v-else>
                              <span v-if="opsJobItem.customer && opsJobItem.customer.code">
                                {{ opsJobItem.customer.code }} <br>
                              </span>
                              {{ opsJobItem.customer && opsJobItem.customer.name ? opsJobItem.customer.name : ''}}
                            </span>
                          </td>
                          <td class="whitespace-nowrap py-4 text-sm text-center">
                            <Button
                              class="bg-red-400 hover:bg-red-500 text-white"
                              @click.prevent="deleteOpsJobItem(opsJobItem)"
                              v-if="permissions.includes('update operations')"
                            >
                              <BackspaceIcon class="w-4 h-4"></BackspaceIcon>
                            </Button>
                          </td>
                        </tr>
                        <tr v-if="!opsJob.opsJobItems.length">
                          <td colspan="4" class="whitespace-nowrap py-4 text-sm font-medium text-black text-center">
                            No Result Found
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
              </div>


            <div class="sm:col-span-6 mt-5 ">
              <div class="flex justify-end">
                <div class="flex space-x-1 justify-end">
                  <Link :href="'/vend-configs'">
                    <Button
                      type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1"
                    >
                      <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
                      <span>
                        Back
                      </span>
                    </Button>
                  </Link>

                  <Button type="submit" class="bg-green-500 hover:bg-green-600 text-white flex space-x-1">
                    <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                    <span>
                      Save
                    </span>
                  </Button>
                </div>
              </div>
            </div>
          </div>
        </form>
        </div>
      </div>
    </div>
  </div>

  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import AttachmentList from '@/Components/AttachmentList.vue';
import AttachmentOverview from '@/Components/AttachmentOverview.vue';
import Button from '@/Components/Button.vue';
import FormInput from '@/Components/FormInput.vue';
import FormTextarea from '@/Components/FormTextarea.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import UploadFileInput from '@/Components/UploadFileInput.vue';
import { ArrowUturnLeftIcon, BackspaceIcon, CheckCircleIcon, DocumentDuplicateIcon, FolderMinusIcon, FolderPlusIcon, PlusCircleIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
  opsJob: Object,
})

const booleanStrictOptions = ref([
    {id: 'true', value: 'Yes'},
    {id: 'false', value: 'No'},
])

const emit = defineEmits(['modalClose'])

const form = ref(
  useForm(getDefaultForm())
)

const opsJob = ref([])
const permissions = usePage().props.auth.permissions

onMounted(() => {
  opsJob.value = props.opsJob.data
})

function getDefaultForm() {
  return {
    id: '',
    vend_id: '',
  }
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


function toggleActivateDeactivate() {
  form.value.post('/vend-configs/' + form.value.id + '/toggle-activate-deactivate', {
    onSuccess: () => {
      emit('modalClose')
    },
      preserveState: true,
      replace: true,
  })
}

function replicateProductMapping() {
  router.post('/vend-configs/replicate',
  {
    id: form.value.id,
  },
  {
    preserveState: true,
    replace: true,
  })
}

</script>