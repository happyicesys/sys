<template>
  <div class="text-sm">
    <span class="inline-block whitespace-nowrap rounded px-2 py-0.5 text-xs font-medium" :class="look.badge">{{ look.label }}</span>
    <div v-if="detail" class="mt-0.5 text-xs" :class="look.text">{{ detail }}</div>
    <div v-if="!compact && row.last_message" class="mt-0.5 text-xs text-gray-500 break-words">{{ row.last_message }}</div>
  </div>
</template>

<script setup>
// One store's Grab Menu Sync line: Synced / Waiting / Sent / Turned away / Not synced / Not sent, with when.
import { computed } from 'vue'
import { syncLook, syncDetail } from './syncStatus'

const props = defineProps({
  row: { type: Object, required: true },
  // The "On Grab" column: badge and one short line only.
  compact: { type: Boolean, default: false },
})

const look = computed(() => syncLook(props.row.status))
const detail = computed(() => syncDetail(props.row))
</script>
