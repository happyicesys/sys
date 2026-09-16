<template>
  <span class="inline-flex items-center gap-1.5">
    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset" :class="classes">
      {{ value ?? '—' }}
    </span>
    <span v-if="note" class="text-xs text-gray-500">{{ note }}</span>
  </span>
</template>

<script setup>
import { computed } from 'vue'
import { TONE } from '@/support/freezerStatus'

/**
 * What a thing IS, next to the buttons that change it.
 *
 * Solid fills rather than the pale badges used elsewhere on the page: this is the one label that has
 * to register before a button is pressed, and the earlier grey-on-grey pill was missed at a glance
 * (Brian, 2026-09-16). Grey is reserved for "the machine has not said", so a colourless chip always
 * means exactly that and never "off".
 */
const props = defineProps({
  value: { type: [String, Number], default: null },
  tone: { type: String, default: TONE.UNKNOWN },
  note: { type: String, default: '' },
})

const TONES = {
  [TONE.OK]: 'bg-emerald-600 text-white ring-emerald-700/10',
  [TONE.ON]: 'bg-emerald-600 text-white ring-emerald-700/10',
  [TONE.OFF]: 'bg-slate-600 text-white ring-slate-700/10',
  [TONE.INFO]: 'bg-indigo-600 text-white ring-indigo-700/10',
  [TONE.WARN]: 'bg-amber-500 text-white ring-amber-600/20',
  [TONE.BAD]: 'bg-red-600 text-white ring-red-700/10',
  [TONE.UNKNOWN]: 'bg-gray-400 text-white ring-gray-500/10',
}

const classes = computed(() => TONES[props.tone] || TONES[TONE.UNKNOWN])
</script>
