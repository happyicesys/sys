<template>
  <!--
    Operation Dashboard → Payment Device → Card Terminal badge: a bound T05's last answer from Payrallel
    (VendResource.t05_status) — "Online · 12 s ago" / "Offline · 3 min ago". A button: opens the online history.
  -->
  <button
    type="button"
    class="mt-0.5 inline-flex items-center justify-center gap-1 whitespace-nowrap rounded-full px-1.5 text-[10px] font-semibold ring-1 ring-inset hover:opacity-80"
    :class="look.cls"
    :title="$t('Show the T05 online history')"
    @click.stop="$emit('open')"
  >
    <span class="h-1.5 w-1.5 rounded-full" :class="look.dot"></span>
    {{ look.text }}
  </button>
</template>

<script>
import { ref } from 'vue'

// One clock for every pill on the page, ticking while at least one is shown.
const now = ref(Date.now())
let users = 0
let timer = null
function startClock() {
  if (users++ === 0) timer = setInterval(() => { now.value = Date.now() }, 5000)
}
function stopClock() {
  if (--users === 0 && timer) {
    clearInterval(timer)
    timer = null
  }
}
</script>

<script setup>
import { computed, onBeforeUnmount, onMounted } from 'vue'
import { t } from '@/i18n'

const props = defineProps({
  // { online: bool|null, state: string|null, checked_at: ISO|null }
  status: { type: Object, required: true },
})
defineEmits(['open'])

onMounted(startClock)
onBeforeUnmount(stopClock)

// Older than this, the last answer no longer says much: shown grey as "last seen".
const STALE_SECONDS = 5 * 60

function ago(iso) {
  const s = Math.max(0, Math.round((now.value - new Date(iso).getTime()) / 1000))
  if (s < 60) return t(':s s ago', { s })
  const m = Math.floor(s / 60)
  if (m < 60) return t(':m min ago', { m })
  const h = Math.floor(m / 60)
  if (h < 24) return t(':h h ago', { h })
  return t(':d d ago', { d: Math.floor(h / 24) })
}

const look = computed(() => {
  const st = props.status
  if (!st.checked_at) return { text: t('T05 not checked yet'), cls: 'bg-gray-100 text-gray-600 ring-gray-300', dot: 'bg-gray-400' }
  const when = ago(st.checked_at)
  const stale = (now.value - new Date(st.checked_at).getTime()) / 1000 > STALE_SECONDS
  if (stale) return { text: t('Last checked :ago', { ago: when }), cls: 'bg-gray-100 text-gray-600 ring-gray-300', dot: 'bg-gray-400' }
  if (st.online === true) return { text: t('Online · :ago', { ago: when }), cls: 'bg-emerald-50 text-emerald-800 ring-emerald-300', dot: 'bg-emerald-500' }
  return { text: t('Offline · :ago', { ago: when }), cls: 'bg-red-50 text-red-700 ring-red-300', dot: 'bg-red-500' }
})
</script>
