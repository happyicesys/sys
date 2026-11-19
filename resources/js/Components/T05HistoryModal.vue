<template>
  <!-- Operation Dashboard: a bound T05's online history, opened from the Card Terminal badge. Read-only. -->
  <Modal :open="open" @modalClose="$emit('close')">
    <template #header>
      <span>{{ $t('T05 online history · :code', { code: vend?.code ?? '' }) }}</span>
    </template>
    <div class="space-y-2 text-sm">
      <div v-if="terminal" class="text-gray-700">{{ $t('SN :sn', { sn: terminal.sn || terminal.label || '—' }) }}</div>
      <div v-if="loading" class="text-gray-500">{{ $t('Loading…') }}</div>
      <div v-else-if="error" class="text-red-600">{{ error }}</div>
      <T05OnlineHistory v-else-if="history" :history="history" />
    </div>
  </Modal>
</template>

<script setup>
import Modal from '@/Components/Modal.vue'
import T05OnlineHistory from '@/Components/T05OnlineHistory.vue'
import { ref, watch } from 'vue'
import { t } from '@/i18n'

const props = defineProps({
  open: Boolean,
  // { vend_id, code }
  vend: { type: Object, default: null },
})
defineEmits(['close'])

const terminal = ref(null)
const history = ref(null)
const loading = ref(false)
const error = ref('')

watch(() => [props.open, props.vend?.vend_id], async ([open, vendId]) => {
  if (!open || !vendId) return
  loading.value = true
  error.value = ''
  history.value = null
  try {
    const { data } = await axios.get(`/vends/${vendId}/remote-card-terminal/history`)
    terminal.value = data.terminal
    history.value = data.history
  } catch (e) {
    error.value = e.response?.data?.message || t('Could not read the terminal status')
  } finally {
    loading.value = false
  }
}, { immediate: true })
</script>
