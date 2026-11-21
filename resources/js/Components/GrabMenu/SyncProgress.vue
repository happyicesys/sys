<template>
  <div class="min-w-[12rem]">
    <div class="flex items-center gap-2">
      <span class="whitespace-nowrap rounded px-2 py-0.5 text-xs font-medium" :class="look.badge">{{ look.label }}</span>
      <span v-if="counts.total" class="text-xs text-gray-600 whitespace-nowrap">{{ $t(':done of :total stores', { done: counts.synced, total: counts.total - counts.skipped }) }}</span>
    </div>
    <div v-if="counts.total" class="mt-1 flex h-1.5 w-full max-w-[14rem] overflow-hidden rounded bg-gray-100">
      <div class="bg-green-500" :style="{ width: pct(counts.synced) }"></div>
      <div class="bg-blue-400" :style="{ width: pct(counts.moving) }"></div>
      <div class="bg-amber-400" :style="{ width: pct(counts.turned_away) }"></div>
      <div class="bg-red-500" :style="{ width: pct(counts.failed) }"></div>
    </div>
    <div class="mt-1 flex flex-wrap gap-x-2 text-xs">
      <span v-if="counts.moving" class="text-blue-700">{{ $t(':count in progress', { count: counts.moving }) }}</span>
      <span v-if="counts.turned_away" class="text-amber-700">{{ $t(':count turned away, retrying', { count: counts.turned_away }) }}</span>
      <span v-if="counts.failed" class="text-red-700">{{ $t(':count not synced', { count: counts.failed }) }}</span>
      <span v-if="counts.skipped" class="text-gray-500">{{ $t(':count not selling, not sent', { count: counts.skipped }) }}</span>
    </div>
  </div>
</template>

<script setup>
// A change's progress on Grab Menu Sync: Fully synced / In progress / Not synced, with a bar by store.
import { computed } from 'vue'
import { t } from '@/i18n'

const props = defineProps({ sync: { type: Object, required: true } })

const counts = computed(() => props.sync.counts)
const LOOKS = {
  synced: { badge: 'bg-green-100 text-green-800', label: () => t('Fully synced') },
  in_progress: { badge: 'bg-blue-100 text-blue-800', label: () => t('In progress') },
  failed: { badge: 'bg-red-100 text-red-800', label: () => t('Needs attention') },
  none: { badge: 'bg-gray-100 text-gray-600', label: () => t('No store asked') },
}
const look = computed(() => {
  const found = LOOKS[props.sync.state] ?? LOOKS.none
  return { badge: found.badge, label: found.label() }
})

function pct(n) {
  return counts.value.total ? `${(100 * n) / counts.value.total}%` : '0%'
}
</script>
