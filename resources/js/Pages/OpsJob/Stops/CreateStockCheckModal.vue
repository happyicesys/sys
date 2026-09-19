<template>
  <Modal :open="open" @modalClose="close">
    <template #header>
      <span class="text-base font-semibold flex items-center gap-2">
        <ClipboardDocumentListIcon class="h-5 w-5 text-cyan-700" />
        Open Stock Count 盘点
      </span>
    </template>
    <template #default>
      <form @submit.prevent="submit" class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6 text-sm">
        <div class="sm:col-span-6 bg-cyan-50 border border-cyan-200 rounded-lg px-3 py-2">
          <span class="text-xs text-gray-500">Machine</span>
          <div class="font-medium text-gray-900">{{ vend?.full_name }}</div>
          <div class="text-xs text-gray-600 mt-1" v-if="loading">Reading its channels…</div>
          <div class="text-xs text-gray-600 mt-1" v-else-if="options">
            <span class="font-semibold text-cyan-800">{{ poolCount }}</span>
            stocked channel(s) can be counted<span v-if="form.product_ids.length"> with the chosen product(s)</span>.
            Channels the system already shows as empty are left out.
          </div>
        </div>

        <div class="sm:col-span-6" v-if="options && options.products.length">
          <label class="block text-sm font-medium text-gray-700">
            Only these product(s) <span class="font-normal text-gray-500">— leave empty for any</span>
          </label>
          <MultiSelect
            v-model="form.product_ids"
            :options="productOptions"
            mode="tags"
            trackBy="id"
            valueProp="id"
            label="value"
            placeholder="Any product"
            open-direction="bottom"
            class="mt-1"
          />
          <div class="text-sm text-red-600">{{ errors.product_ids }}</div>
        </div>

        <div class="sm:col-span-3 flex items-center space-x-2 pt-1">
          <input id="sc-random" type="checkbox" v-model="form.is_random" class="h-5 w-5 rounded border-gray-300 text-cyan-600 focus:ring-cyan-500 cursor-pointer" />
          <label for="sc-random" class="text-sm font-medium text-gray-700 cursor-pointer">
            Random channels?
            <span class="block font-normal text-xs text-gray-500">Unticked = count every stocked channel</span>
          </label>
        </div>
        <div class="sm:col-span-3" v-if="form.is_random">
          <label class="block text-sm font-medium text-gray-700">How many channels <span class="text-red-500">*</span></label>
          <input
            type="number" min="1" :max="poolCount || 1" step="1"
            v-model="form.sample_size"
            class="mt-1 shadow-sm focus:ring-cyan-500 focus:border-cyan-500 block w-full text-sm border-gray-300 rounded-md"
          />
          <div class="text-xs text-gray-500" v-if="poolCount && Number(form.sample_size) >= poolCount">
            That is all {{ poolCount }} of them — nothing left to draw from.
          </div>
          <div class="text-sm text-red-600">{{ errors.sample_size }}</div>
        </div>

        <div class="sm:col-span-4">
          <label class="block text-sm font-medium text-gray-700">Remarks</label>
          <input type="text" v-model="form.remarks" class="mt-1 shadow-sm focus:ring-cyan-500 focus:border-cyan-500 block w-full text-sm border-gray-300 rounded-md" />
        </div>
        <div class="sm:col-span-2">
          <label class="block text-sm font-medium text-gray-700">Job Sequence</label>
          <input type="number" step="0.1" min="0.1" v-model="form.sequence" class="mt-1 shadow-sm focus:ring-cyan-500 focus:border-cyan-500 block w-full text-sm border-gray-300 rounded-md" placeholder="last" />
          <div class="text-sm text-red-600">{{ errors.sequence }}</div>
        </div>

        <div class="sm:col-span-6 text-sm text-red-600" v-if="errors.vend_id || errors._">{{ errors.vend_id || errors._ }}</div>

        <div class="sm:col-span-6 flex justify-end space-x-2 pt-2">
          <Button type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-800" @click="close">Cancel</Button>
          <Button type="submit" class="bg-cyan-600 hover:bg-cyan-700 text-white" :class="{ 'opacity-50 cursor-not-allowed': !canSubmit }" :disabled="!canSubmit">
            Open Stock Count<span v-if="drawCount">&nbsp;({{ drawCount }} channel{{ drawCount === 1 ? '' : 's' }})</span>
          </Button>
        </div>
      </form>
    </template>
  </Modal>
</template>

<script setup>
import Button from '@/Components/Button.vue'
import Modal from '@/Components/Modal.vue'
import MultiSelect from '@/Components/MultiSelect.vue'
import { ClipboardDocumentListIcon } from '@heroicons/vue/20/solid'
import { computed, ref, watch } from 'vue'
import { firstErrors } from './stopTypes'

const props = defineProps({
  open: Boolean,
  opsJobId: [Number, String],
  vend: Object, // { id, full_name }
})
const emit = defineEmits(['close', 'created'])

const blank = () => ({ is_random: false, sample_size: '', product_ids: [], remarks: '', sequence: '' })
const form = ref(blank())
const errors = ref({})
const processing = ref(false)
const loading = ref(false)
const options = ref(null) // { eligible_count, products: [{ id, value, channels }] }

const productOptions = computed(() => (options.value?.products || []).map(p => ({
  id: p.id,
  value: p.value + ' (' + p.channels + ')',
})))

// MultiSelect runs in object mode, so a chosen product is { id, value }.
const chosenProductIds = computed(() => form.value.product_ids.map(p => (typeof p === 'object' ? p.id : p)))

// How many channels the draw can choose from, given the product filter.
const poolCount = computed(() => {
  if (!options.value) return 0
  if (!chosenProductIds.value.length) return options.value.eligible_count
  return options.value.products
    .filter(p => chosenProductIds.value.includes(p.id))
    .reduce((sum, p) => sum + p.channels, 0)
})

const drawCount = computed(() => {
  if (!poolCount.value) return 0
  if (!form.value.is_random) return poolCount.value
  const size = parseInt(form.value.sample_size)
  return Number.isFinite(size) && size > 0 ? Math.min(size, poolCount.value) : 0
})

const canSubmit = computed(() => !processing.value && !loading.value && drawCount.value > 0)

watch(() => props.open, (isOpen) => {
  if (!isOpen || !props.vend) return
  form.value = blank()
  errors.value = {}
  options.value = null
  loading.value = true

  axios.get('/ops-jobs/' + props.opsJobId + '/stock-checks/draw-options/' + props.vend.id)
    .then(({ data }) => {
      options.value = data
      if (!data.eligible_count) {
        errors.value = { _: 'This machine has no stocked channel to count.' }
      }
    })
    .catch(error => { errors.value = firstErrors(error) })
    .finally(() => { loading.value = false })
})

function close() {
  emit('close')
}

function submit() {
  if (!canSubmit.value) return
  processing.value = true
  errors.value = {}

  axios.post('/ops-jobs/' + props.opsJobId + '/stock-checks', {
    vend_id: props.vend.id,
    is_random: form.value.is_random,
    sample_size: form.value.is_random ? parseInt(form.value.sample_size) : null,
    product_ids: chosenProductIds.value,
    remarks: form.value.remarks || null,
    sequence: form.value.sequence !== '' ? parseFloat(form.value.sequence) : null,
  })
    .then(({ data }) => emit('created', data))
    .catch(error => { errors.value = firstErrors(error) })
    .finally(() => { processing.value = false })
}
</script>
