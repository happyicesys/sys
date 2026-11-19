<template>
  <!--
    A remote card terminal's (T05) online / offline history: RemoteCardTerminalController's `history`
    (App\Services\CardTerminal\TerminalStatusLog). Shown on Setting/Edit's Remote card terminal panel and in the
    Operation Dashboard's Card Terminal popup. Display only.
  -->
  <div class="space-y-2 text-left">
    <div class="grid gap-2 sm:grid-cols-2">
      <div v-for="w in windows" :key="w.key" class="rounded-md bg-gray-50 px-3 py-2">
        <div class="text-xs text-gray-500">{{ w.label }}</div>
        <div v-if="w.sum.uptime_pct === null" class="text-sm text-gray-500">{{ $t('Not checked yet') }}</div>
        <template v-else>
          <div class="text-sm font-semibold" :class="w.sum.uptime_pct >= 99 ? 'text-emerald-700' : (w.sum.uptime_pct >= 95 ? 'text-amber-700' : 'text-red-700')">
            {{ $t(':pct% online', { pct: w.sum.uptime_pct }) }}
          </div>
          <div class="text-xs text-gray-600">
            {{ $tc(':count drop|:count drops', w.sum.drops) }}
            <span v-if="w.sum.drops"> · {{ $t('longest :d', { d: duration(w.sum.longest_offline_seconds) }) }}</span>
            <span v-if="w.sum.failed_seconds"> · {{ $t('Payrallel did not answer for :d', { d: duration(w.sum.failed_seconds) }) }}</span>
            <span v-if="w.sum.unchecked_seconds >= 600"> · {{ $t('not checked for :d', { d: duration(w.sum.unchecked_seconds) }) }}</span>
          </div>
        </template>
      </div>
    </div>
    <ul v-if="spans.length" class="divide-y divide-gray-100 text-xs">
      <li v-for="(s, i) in shownSpans" :key="i" class="flex flex-wrap items-center gap-x-3 gap-y-0.5 py-1.5">
        <span class="inline-flex w-36 items-center gap-1.5 font-medium" :class="kindCls(s.kind)">
          <span class="h-2 w-2 rounded-full" :class="kindDot(s.kind)"></span>
          {{ kindText(s) }}
        </span>
        <span class="text-gray-700">{{ when(s.from) }} → {{ s.open ? $t('now') : when(s.until) }}</span>
        <span class="text-gray-500">{{ duration(s.seconds) }}</span>
        <span v-if="s.kind === 'check_failed' && s.error" class="w-full truncate text-gray-500" :title="s.error">{{ s.error }}</span>
        <span v-if="s.unchecked_after_seconds >= 60" class="w-full text-amber-700">{{ $t('then not checked for :d', { d: duration(s.unchecked_after_seconds) }) }}</span>
      </li>
    </ul>
    <p v-else class="text-xs text-gray-500">{{ $t('Not checked yet') }}</p>
    <button v-if="spans.length > 8" type="button" class="text-xs font-medium text-sky-700 hover:underline" @click="showAll = !showAll">
      {{ showAll ? $t('Show fewer') : $t('Show all :count', { count: spans.length }) }}
    </button>
    <p class="text-xs text-gray-500">{{ $t("Checked about every 30 seconds through Payrallel's cloud: \"online\" means the T05's own 4G link reached Payrallel. Nothing is shown on the terminal.") }}</p>
  </div>
</template>

<script setup>
import moment from 'moment'
import { computed, ref } from 'vue'
import { t } from '@/i18n'

const props = defineProps({
  // { day: summary, week: summary, spans: [...] }
  history: { type: Object, required: true },
})

const showAll = ref(false)

const windows = computed(() => [
  { key: 'day', label: t('Last 24 hours'), sum: props.history.day },
  { key: 'week', label: t('Last 7 days'), sum: props.history.week },
])
const spans = computed(() => props.history.spans ?? [])
const shownSpans = computed(() => (showAll.value ? spans.value : spans.value.slice(0, 8)))

function kindText(s) {
  if (s.kind === 'online') return s.state ? t('Online · :state', { state: s.state }) : t('Online')
  if (s.kind === 'offline') return t('Offline')
  return t('Check failed')
}
const kindCls = (k) => (k === 'online' ? 'text-emerald-700' : (k === 'offline' ? 'text-red-700' : 'text-gray-600'))
const kindDot = (k) => (k === 'online' ? 'bg-emerald-500' : (k === 'offline' ? 'bg-red-500' : 'bg-gray-400'))

function when(iso) {
  const m = moment(iso)
  return m.isSame(moment(), 'day') ? m.format('HH:mm') : m.format('D MMM HH:mm')
}

function duration(seconds) {
  const s = Math.max(0, Math.round(seconds || 0))
  if (s < 60) return t(':s s', { s })
  const m = Math.round(s / 60)
  if (m < 60) return t(':m min', { m })
  const h = Math.floor(m / 60)
  const r = m % 60
  if (h < 24) return r ? t(':h h :m min', { h, m: r }) : t(':h h', { h })
  const d = Math.floor(h / 24)
  return t(':d d :h h', { d, h: h % 24 })
}
</script>
