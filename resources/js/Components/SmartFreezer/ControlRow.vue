<template>
  <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3"
       :class="danger ? 'border-red-200 bg-red-50' : 'border-gray-200 bg-white'">
    <div class="min-w-0">
      <div class="flex flex-wrap items-center gap-2">
        <span class="text-sm font-medium text-gray-800">{{ label }}</span>
        <StateChip v-if="state !== undefined" :value="state" :tone="tone" :note="note" />
      </div>
      <p v-if="hint" class="mt-0.5 text-xs text-gray-500">{{ hint }}</p>
      <slot name="detail" />
    </div>
    <!--
      The tooltip sits on this wrapper, never on the buttons: a disabled <button> swallows mouse
      events, so a tooltip bound to it would never show - and that is exactly when the reason is
      wanted.
    -->
    <span class="isolate inline-flex -space-x-px shadow-sm" v-tooltip="reason">
      <slot />
    </span>
  </div>
</template>

<script setup>
import StateChip from '@/Components/SmartFreezer/StateChip.vue'
import { TONE } from '@/support/freezerStatus'

/** Label + current value on the left, its button group on the right. One shape for every control. */
defineProps({
  label: { type: String, required: true },
  state: { type: [String, Number], default: undefined },
  tone: { type: String, default: TONE.UNKNOWN },
  note: { type: String, default: '' },
  hint: { type: String, default: '' },
  danger: { type: Boolean, default: false },
  /** Why this row's buttons are disabled, shown on hover. Empty when they work. */
  reason: { type: String, default: '' },
})
</script>
