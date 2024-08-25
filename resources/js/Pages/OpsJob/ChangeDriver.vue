<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col space-y-2 text-left">
          <span>
            Job ID#
            <span class="text-blue-800">
              {{ opsJobItem.ref_id }}
            </span>
          </span>
          <span>
            Machine ID#
            <span class="text-blue-800">
              {{ vend.code }}
            </span>
          </span>
          <span v-if="vend.customer" class="text-gray-700">
            ({{ opsJobItem.vend.customer.id + 20000 }})
            {{ opsJobItem.vend.customer.name }}
          </span>
        </div>
      </template>
      <template #default>
      <form @submit.prevent="submit" id="submit">
        <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">
          <div class="sm:col-span-6">
            <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
              Current Driver
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
          <div class="sm:col-span-6">
            <DatePicker v-model="form.date" :error="form.date">
              Date
            </DatePicker>
          </div>
          <div class="sm:col-span-6">
            <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
              Delivery By
              <span class="text-red-500">
                  *
              </span>
            </label>
            <MultiSelect
              v-model="form.delivered_by"
              :options="userOptions"
              trackBy="id"
              valueProp="id"
              label="value"
              placeholder="Select"
              open-direction="top"
              class="mt-1"
            >
            </MultiSelect>
            <div class="text-sm text-red-600" v-if="form.errors.delivered_by">
              {{ form.errors.delivered_by }}
            </div>
          </div>
          <div class="sm:col-span-6">
            <div class="flex md:justify-end py-3">
              <Button
                  type="button"
                  class="px-3 py-2 ml-1 text-xs md:text-md flex space-x-1 bg-green-500 hover:bg-green-600 text-white w-full md:w-fit"
                  @click.prevent="submit"
              >
                <span class="flex space-x-1 items-center">
                  <CheckCircleIcon class="w-5 h-5"></CheckCircleIcon>
                  <span>
                    Change Driver
                  </span>
                </span>
              </Button>
            </div>
          </div>
        </div>
      </form>
      </template>
    </Modal>
  </Teleport>
</template>

<script setup>
import {CheckCircleIcon, ClipboardDocumentCheckIcon, FlagIcon, TrashIcon, XCircleIcon } from '@heroicons/vue/20/solid';
import Button from '@/Components/Button.vue';
import DatePicker from '@/Components/DatePicker.vue';
import Modal from '@/Components/Modal.vue';
import MultiSelect from '@/Components/MultiSelect.vue'
import { onMounted, ref } from 'vue';
import { router, usePage, useForm } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";

const props = defineProps({
  opsJob: Object,
  opsJobItem: Object,
  showModal: Boolean,
  userOptions: [Array, Object],
})

const channels = ref([])
const permissions = usePage().props.auth.permissions
const toast = useToast()
const vend = ref([])
const form = ref()

onMounted(() => {
  vend.value = props.opsJobItem.vend
  form.value = {
    ...useForm(getDefaultForm()),
    date: props.opsJob.date,
  }
})

const profile = usePage().props.auth.profile
const emit = defineEmits(['modalClose', 'statusUpdated'])

function getDefaultForm() {
  return {
    date: '',
    delivered_by: '',
  }
}

function submit() {
  form.value.clearErrors()

  form.value
  .transform((data) => {
    return {
      ...data,
      delivered_by: data.delivered_by.id,
    }
  })
  .post('/ops-jobs/items/' + props.opsJobItem.id + '/update', {
    onSuccess: () => {
      toast.success("Successfully Changed", {
        timeout: 3000
      });
      emit('statusUpdated')
      emit('modalClose')
    },
    preserveState: true,
    replace: true,
  })
}

function formatDatetime(value) {
  return moment(value).format('YYMMDD hh:mm A');
}
</script>