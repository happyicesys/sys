<template>
  <Teleport to="body">
    <Modal :open="open" @modalClose="handleClose">
      <template #header>
        <div class="flex flex-col">
          <span class="text-sm text-gray-500">
            Machine Log
          </span>
          <span class="text-base font-semibold text-gray-900" v-if="displayTitle">
            {{ displayTitle }}
          </span>
        </div>
      </template>
      <div class="space-y-4">
        <div class="text-sm text-gray-600">
          Showing the latest {{ HISTORY_PAGE_SIZE }} events by default. Use Load More to pull older records.
        </div>
        <div v-if="loading && !logs.length" class="py-6 text-sm text-gray-500 text-center">
          Loading log history...
        </div>
        <div v-else-if="!loading && !logs.length" class="py-6 text-sm text-gray-500 text-center">
          No history recorded for this machine yet.
        </div>
        <ul v-else class="divide-y divide-gray-200">
          <li v-for="log in logs" :key="log.id" class="py-4">
            <div class="flex items-start justify-between">
              <div class="space-y-1">
                <p class="text-sm font-semibold text-gray-800">
                  {{ formatEventLabel(log.event) }}
                </p>
                <p v-if="formatLogDetails(log)" class="text-sm text-gray-600">
                  {{ formatLogDetails(log) }}
                </p>
                <p class="text-xs text-gray-500">
                  {{ formatOccurredAt(log.occurred_at) }}
                </p>
              </div>
              <span class="text-[10px] uppercase tracking-wide bg-orange-50 text-orange-600 font-semibold px-2 py-1 rounded">
                {{ log.event }}
              </span>
            </div>
          </li>
        </ul>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pt-4 border-t border-gray-100">
          <p class="text-xs text-gray-500">
            Showing {{ logs.length }} of {{ meta.total || logs.length }} events
          </p>
          <div class="flex flex-wrap gap-2 justify-end">
            <Button
              class="bg-white border border-orange-300 text-orange-600 hover:bg-orange-50 text-xs flex items-center space-x-1 disabled:opacity-50 disabled:cursor-not-allowed"
              type="button"
              @click="loadMore"
              :disabled="!hasMore || loading"
            >
              <span v-if="loading && logs.length">
                Loading...
              </span>
              <span v-else>
                Load More
              </span>
            </Button>
            <Button
              class="bg-gray-200 hover:bg-gray-300 text-gray-800 text-xs"
              type="button"
              @click="handleClose"
            >
              Close
            </Button>
          </div>
        </div>
      </div>
    </Modal>
  </Teleport>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import moment from 'moment'
import Modal from '@/Components/Modal.vue'
import Button from '@/Components/Button.vue'

const props = defineProps({
  open: {
    type: Boolean,
    default: false,
  },
  vend: {
    type: Object,
    default: null,
  },
})

const emit = defineEmits(['close'])

const HISTORY_PAGE_SIZE = 10
const logs = ref([])
const meta = ref({
  current_page: 0,
  per_page: HISTORY_PAGE_SIZE,
  has_more_pages: false,
  total: 0,
})
const loading = ref(false)
const currentPage = ref(1)

const vendId = computed(() => props.vend?.id ?? props.vend?.vend_id ?? null)
const vendCode = computed(() => props.vend?.code ?? props.vend?.vend_code ?? '')
const vendName = computed(() => {
  if (props.vend?.name) {
    return props.vend.name
  }
  if (props.vend?.customer_name) {
    return props.vend.customer_name
  }
  if (props.vend?.address) {
    return props.vend.address
  }
  if (props.vend?.location) {
    return props.vend.location
  }
  return ''
})
const displayTitle = computed(() => {
  return [vendCode.value, vendName.value].filter(Boolean).join(' ')
})
const hasMore = computed(() => Boolean(meta.value.has_more_pages))

watch(
  () => props.open,
  async (isOpen) => {
    if (isOpen) {
      await initializeLogs()
    } else {
      resetState()
    }
  }
)

watch(
  () => vendId.value,
  async (newId, oldId) => {
    if (props.open && newId && newId !== oldId) {
      await initializeLogs()
    }
  }
)

function resetState() {
  logs.value = []
  meta.value = {
    current_page: 0,
    per_page: HISTORY_PAGE_SIZE,
    has_more_pages: false,
    total: 0,
  }
  currentPage.value = 1
  loading.value = false
}

async function initializeLogs() {
  resetState()
  if (vendId.value) {
    await fetchLogs()
  }
}

async function fetchLogs({ append = false } = {}) {
  if (!vendId.value) {
    return
  }
  loading.value = true
  try {
    const response = await window.axios.get(`/vends/${vendId.value}/logs`, {
      params: {
        per_page: HISTORY_PAGE_SIZE,
        page: currentPage.value,
      },
    })
    const { data, meta: responseMeta } = response.data
    logs.value = append ? logs.value.concat(data) : data
    meta.value = {
      current_page: responseMeta?.current_page ?? currentPage.value,
      per_page: responseMeta?.per_page ?? HISTORY_PAGE_SIZE,
      has_more_pages: Boolean(responseMeta?.has_more_pages),
      total: responseMeta?.total ?? logs.value.length,
    }
  } catch (error) {
    console.error('Unable to fetch vend history', error)
  } finally {
    loading.value = false
  }
}

async function loadMore() {
  if (!hasMore.value || loading.value) {
    return
  }
  currentPage.value = meta.value.current_page + 1
  await fetchLogs({ append: true })
}

function handleClose() {
  emit('close')
}

function formatEventLabel(event) {
  if (!event) {
    return 'Unknown Event'
  }
  const mappings = {
    channel_error: 'Channel Error',
    power_off: 'Power Off',
    power_restored: 'Power Restored',
    no_transaction: 'No Transaction',
  }
  if (mappings[event]) {
    return mappings[event]
  }
  return event
    .split('_')
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(' ')
}

function formatOccurredAt(timestamp) {
  if (!timestamp) {
    return 'Unknown time'
  }
  return moment(timestamp).format('DD MMM YYYY, HH:mm:ss')
}

function formatLogDetails(log) {
  if (!log) {
    return ''
  }
  if (log.subject) {
    return log.subject
  }
  const context = log.context
  if (!context) {
    return ''
  }

  if (log.event === 'channel_error' && context.channel_code && context.error_code) {
    return `Channel ${context.channel_code} error ${context.error_code}`
  }

  if (typeof context === 'string') {
    return context
  }

  if (typeof context === 'object') {
    return Object.entries(context)
      .map(([key, value]) => `${key.replace(/_/g, ' ')}: ${value}`)
      .join(', ')
  }

  return ''
}
</script>
