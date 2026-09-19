<template>
  <Modal :open="open" @modalClose="close">
    <template #header>
      <span class="text-base font-semibold flex items-center gap-2">
        <WrenchScrewdriverIcon class="h-5 w-5 text-amber-600" />
        Open Service Notice 维修单
      </span>
    </template>
    <template #default>
      <form @submit.prevent="submit" class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6 text-sm">
        <div class="sm:col-span-6 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
          <span class="text-xs text-gray-500">Machine</span>
          <div class="font-medium text-gray-900">{{ vend?.full_name }}</div>
        </div>

        <div class="sm:col-span-6">
          <label class="block text-sm font-medium text-gray-700">
            Service Item(s) 维修项目 <span class="text-red-500">*</span>
            <span class="font-normal text-gray-500">— every line is one item</span>
          </label>
          <textarea
            v-model="form.items"
            rows="6"
            ref="itemsInput"
            class="mt-1 shadow-sm focus:ring-amber-500 focus:border-amber-500 block w-full text-sm border-gray-300 rounded-md"
            placeholder="Compressor noisy&#10;Door seal torn"
          ></textarea>
          <div class="flex justify-between">
            <div class="text-sm text-red-600">{{ errors.items }}</div>
            <div class="text-xs text-gray-500">{{ lineCount }} item(s)</div>
          </div>
        </div>

        <div class="sm:col-span-4">
          <label class="block text-sm font-medium text-gray-700">Remarks</label>
          <input type="text" v-model="form.remarks" class="mt-1 shadow-sm focus:ring-amber-500 focus:border-amber-500 block w-full text-sm border-gray-300 rounded-md" />
          <div class="text-sm text-red-600">{{ errors.remarks }}</div>
        </div>
        <div class="sm:col-span-2">
          <label class="block text-sm font-medium text-gray-700">Job Sequence</label>
          <input type="number" step="0.1" min="0.1" v-model="form.sequence" class="mt-1 shadow-sm focus:ring-amber-500 focus:border-amber-500 block w-full text-sm border-gray-300 rounded-md" placeholder="last" />
          <div class="text-sm text-red-600">{{ errors.sequence }}</div>
        </div>

        <div class="sm:col-span-6 text-sm text-red-600" v-if="errors.vend_id || errors._">{{ errors.vend_id || errors._ }}</div>

        <div class="sm:col-span-6 flex justify-end space-x-2 pt-2">
          <Button type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-800" @click="close">Cancel</Button>
          <Button type="submit" class="bg-amber-500 hover:bg-amber-600 text-white" :class="{ 'opacity-50 cursor-not-allowed': processing || !lineCount }" :disabled="processing || !lineCount">
            Open Service Notice
          </Button>
        </div>
      </form>
    </template>
  </Modal>
</template>

<script setup>
import Button from '@/Components/Button.vue'
import Modal from '@/Components/Modal.vue'
import { WrenchScrewdriverIcon } from '@heroicons/vue/20/solid'
import { computed, nextTick, ref, watch } from 'vue'
import { firstErrors } from './stopTypes'

const props = defineProps({
  open: Boolean,
  opsJobId: [Number, String],
  vend: Object, // { id, full_name }
})
const emit = defineEmits(['close', 'created'])

const blank = () => ({ items: '', remarks: '', sequence: '' })
const form = ref(blank())
const errors = ref({})
const processing = ref(false)
const itemsInput = ref(null)

const lineCount = computed(() => form.value.items.split(/\r?\n/).filter(line => line.trim() !== '').length)

watch(() => props.open, (isOpen) => {
  if (!isOpen) return
  form.value = blank()
  errors.value = {}
  nextTick(() => itemsInput.value?.focus())
})

function close() {
  emit('close')
}

function submit() {
  if (processing.value || !props.vend) return
  processing.value = true
  errors.value = {}

  axios.post('/ops-jobs/' + props.opsJobId + '/service-notices', {
    vend_id: props.vend.id,
    items: form.value.items,
    remarks: form.value.remarks || null,
    sequence: form.value.sequence !== '' ? parseFloat(form.value.sequence) : null,
  })
    .then(({ data }) => emit('created', data))
    .catch(error => { errors.value = firstErrors(error) })
    .finally(() => { processing.value = false })
}
</script>
