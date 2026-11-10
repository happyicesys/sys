<template>
  <span class="relative inline-flex h-2.5 w-2.5 shrink-0" role="img" :aria-label="text" v-tooltip="text">
    <span class="relative inline-flex h-2.5 w-2.5 rounded-full ring-1 ring-inset" :class="classes"></span>
  </span>
</template>

<script setup>
import { computed } from 'vue'
import { t } from '@/i18n'
import { LINK } from '@/support/freezerStatus'

/** Green online, red offline, grey "cannot tell" — one part of the freezer, as at its last report. */
const props = defineProps({
  link: { type: String, required: true },
})

const classes = computed(() => ({
  [LINK.UP]: 'bg-emerald-500 ring-emerald-600',
  [LINK.DOWN]: 'bg-red-500 ring-red-600',
}[props.link] || 'bg-gray-300 ring-gray-400'))

const text = computed(() => ({
  [LINK.UP]: t('Online'),
  [LINK.DOWN]: t('Offline'),
}[props.link] || t('Not known now')))
</script>
