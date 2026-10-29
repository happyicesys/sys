<template>
  <!--
    Every Machine ID on screen opens that machine on the Operation Dashboard in a
    new tab (Brian, 2026-10-29), unless a page says otherwise. Pass `vend` (a
    VendResource or raw vend row) or `code` (the label already, e.g. "C6003").
    Plain text when the viewer cannot open the Dashboard.
  -->
  <a
    v-if="href"
    :href="href"
    target="_blank"
    rel="noopener"
    class="text-blue-700 hover:underline"
    @click.stop
  ><slot>{{ label }}</slot></a>
  <span v-else><slot>{{ label }}</slot></span>
</template>

<script setup>
import { computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { opsDashboardUrl, vendCodeLabel } from '@/utils/vendCode'

const props = defineProps({
  vend: { type: Object, default: null },
  code: { type: [String, Number], default: null },
})

const page = usePage()
const label = computed(() => (props.code !== null && props.code !== '' ? String(props.code) : vendCodeLabel(props.vend)))
const href = computed(() => opsDashboardUrl(label.value, page.props.auth))
</script>
