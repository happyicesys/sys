<template>
  <button
    type="button"
    :disabled="disabled"
    class="inline-flex items-center gap-1 px-3 py-1.5 text-sm font-medium transition first:rounded-l-md last:rounded-r-md focus:z-10 focus:outline-none focus:ring-2 focus:ring-sky-500"
    :class="classes"
    @click.prevent="$emit('click')"
  >
    <slot />
  </button>
</template>

<script setup>
import { computed } from 'vue'

/**
 * One segment of a control's button group. `active` marks the state the machine is ALREADY in, so a
 * press that would change nothing is visibly the current one rather than an identical twin.
 */
const props = defineProps({
  disabled: { type: Boolean, default: false },
  active: { type: Boolean, default: false },
  tone: { type: String, default: 'neutral' }, // neutral | primary | danger
})

defineEmits(['click'])

const classes = computed(() => {
  if (props.disabled) return 'bg-gray-50 text-gray-300 ring-1 ring-inset ring-gray-200 cursor-not-allowed'
  if (props.active) return 'bg-slate-800 text-white ring-1 ring-inset ring-slate-800'
  if (props.tone === 'primary') return 'bg-sky-700 text-white ring-1 ring-inset ring-sky-700 hover:bg-sky-800'
  if (props.tone === 'danger') return 'bg-red-600 text-white ring-1 ring-inset ring-red-600 hover:bg-red-700'
  return 'bg-white text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50'
})
</script>
