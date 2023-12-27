<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600">
            Editing
          </span>
          <span v-if="vend">
            {{ vend.code }}
          </span>
        </div>
      </template>
      <template #default>
        <form @submit.prevent="submit" id="submit">
          <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6 pb-2">
            <div class="sm:col-span-6">
              <FormInput v-model="form.name" :error="form.errors.name" required="true" :disabled="vend.customer_code && vend.customer_name">
                Name
              </FormInput>
            </div>
            <!-- <div class="sm:col-span-6">
              <FormInput v-model="form.serial_num" :error="form.errors.serial_num" :disabled="!permissions.includes('update vends')">
                Serial Number
              </FormInput>
            </div> -->
            <div class="sm:col-span-6">
              <FormInput v-model="form.private_key" :error="form.errors.private_key" :disabled="!permissions.includes('update vends')">
                Private Key
              </FormInput>
            </div>
            <div class="sm:col-span-6">
              <DatePicker v-model="form.begin_date" :error="form.errors.begin_date" @input="onDateFromChanged()"
              v-if="permissions.includes('update vends')">
                Begin Date (Default is the Creation/ First Invoice Date)
              </DatePicker>
            </div>
            <div class="sm:col-span-6">
              <DatePicker v-model="form.termination_date" :error="form.errors.termination_date" :minDate="form.begin_date"
              v-if="permissions.includes('update vends')">
                Termination Date (Default is the Unbinding Date from CMS, status change)
              </DatePicker>
            </div>
          </div>
          <div class="sm:col-span-6 flex justify-between">
            <div class="flex space-x-1 mt-5 justify-start">
              <Button
                class="bg-red-500 hover:bg-red-600 text-white flex space-x-1"
                @click.prevent="restartVend(vend.id)"
              >
                <ArrowPathIcon class="w-4 h-4"></ArrowPathIcon>
                <span>
                  Restart
                </span>
              </Button>
            </div>
            <div class="flex space-x-1 mt-5 justify-end">
              <Button
                class="bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1"
                form="submit"
              >
                <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
                <span>
                  Back
                </span>
              </Button>
              <Button
                type="button"
                class="bg-yellow-500 hover:bg-yellow-600 text-white flex space-x-1"
                v-if="vend.latestVendBinding && vend.latestVendBinding.customer && permissions.includes('update vends')"
                @click="unbindCustomer(form.id)"
              >
                <ArrowUturnDownIcon class="w-4 h-4"></ArrowUturnDownIcon>
                <span>
                  Unbind
                </span>
              </Button>
              <Button
                type="submit"
                class="bg-green-500 hover:bg-green-600 text-white flex space-x-1"
                v-if="permissions.includes('update vends')"
              >
                <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                <span>
                  Save
                </span>
              </Button>
            </div>
          </div>
        </form>
          <!-- <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3" v-if="form.id">
            <div class="relative">
              <div class="absolute inset-0 flex items-center" aria-hidden="true">
                <div class="w-full border-t border-gray-300"></div>
              </div>
              <div class="relative flex justify-center">
                <span class="px-3 bg-white text-lg font-medium text-gray-900"> End of Month Inventory Snapshots </span>
              </div>
            </div>
          </div>
          <div class="sm:col-span-6 flex flex-col mt-3" v-if="form.id">
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
                        End of Month
                        <span class="text-xs">
                          (every last day of the month 11.59:59pm)
                        </span>
                      </th>
                      <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                        Action
                      </th>
                    </tr>
                  </thead>
                  <tbody class="bg-white">
                    <tr v-for="(vendSnapshot, vendSnapshotIndex) in vend.vendSnapshots" :key="vendSnapshot.id" :class="vendSnapshotIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                        {{ vendSnapshotIndex + 1 }}
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                        {{ vendSnapshot.endOfMonthNameYear }}
                      </td>
                      <td class="whitespace-nowrap py-4 text-sm text-center">
                        <Button
                          class="bg-gray-200 hover:bg-gray-300"
                          @click="downloadVendSnapshot(vendSnapshot.id)"
                        >
                          <ArrowDownTrayIcon class="w-4 h-4"></ArrowDownTrayIcon>
                        </Button>
                      </td>
                    </tr>
                    <tr v-if="!vend.vendSnapshots || !vend.vendSnapshots.length">
                      <td colspan="3" class="whitespace-nowrap py-4 text-sm font-medium text-black text-center">
                        No records found
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
          </div> -->
      </template>
    </Modal>
  </Teleport>
</template>

<script setup>
import Button from '@/Components/Button.vue';
import DatePicker from '@/Components/DatePicker.vue';
import FormInput from '@/Components/FormInput.vue';
import Modal from '@/Components/Modal.vue';
import { ArrowPathIcon, ArrowUturnDownIcon, ArrowUturnLeftIcon, CheckCircleIcon } from '@heroicons/vue/20/solid';
import { router, useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue'

const props = defineProps({
  vend: Object,
  countries: Object,
  permissions: [Array, Object],
  type: String,
  showModal: Boolean,
})

const emit = defineEmits(['modalClose'])

const form = ref(
  useForm(getDefaultForm())
)

onMounted(() => {
  form.value = props.vend ? useForm(props.vend) : useForm(getDefaultForm())
  form.value.name = props.vend.customer_code ?
                    props.vend.customer_code + '    ' + props.vend.customer_name :
                    props.vend.name
})

function getDefaultForm() {
  return {
    name: '',
    begin_date: '',
    serial_num: '',
    termination_date: '',
    private_key: '',
  }
}

function restartVend(vendID) {
  router.post('/vends/' + vendID + '/restart', {}, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    onSuccess: () => {
      emit('modalClose')
    }
  })
}

function submit() {
  form.value.clearErrors()

  form.value
    .post('/vends/' + form.value.id + '/update', {
    onSuccess: () => {
      emit('modalClose')
    },
    preserveState: true,
    replace: true,
  })
}

function unbindCustomer(vendId) {
  form.value
      .post('/vends/' + vendId + '/unbind', {
        onSuccess: () => {
          emit('modalClose')
        },
        preserveState: true,
        replace: true,
      })
}

function downloadVendSnapshot(vendSnapshotId) {
    axios({
        method: 'get',
        url: '/vends/vend-snapshots/excel/' + vendSnapshotId,
        responseType: 'blob',
    }).then(response => {
        fileDownload(response.data, 'Vending_Channels_' + moment().format('YYMMDDhhmmss') +'.xlsx')
    }).catch(error => {
        console.log(error)
    }).finally(() => {
    })
}

</script>