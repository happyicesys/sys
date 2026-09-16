<template>
  <div class="rounded-lg border border-gray-200 bg-white px-3 py-2">
    <div class="text-[11px] font-medium uppercase tracking-wide text-gray-500">{{ label }}</div>
    <div class="mt-0.5 text-sm font-semibold" :class="textTone">{{ value ?? '—' }}</div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { TONE } from '@/support/freezerStatus'

/** One fact the machine reported. Text-coloured, not filled: a wall of filled chips reads as alarm. */
const props = defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number], default: null },
  tone: { type: String, default: TONE.UNKNOWN },
})

const TONES = {
  [TONE.OK]: 'text-emerald-700',
  [TONE.ON]: 'text-emerald-700',
  [TONE.OFF]: 'text-gray-900',
  [TONE.INFO]: 'text-indigo-700',
  [TONE.WARN]: 'text-amber-700',
  [TONE.BAD]: 'text-red-700',
  [TONE.UNKNOWN]: 'text-gray-400',
}

const textTone = computed(() => TONES[props.tone] || TONES[TONE.UNKNOWN])
</script>
