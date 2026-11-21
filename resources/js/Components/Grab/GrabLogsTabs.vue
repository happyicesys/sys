<template>
  <!-- Grab Logs (Brian, 2026-11-21): one menu entry, a tab per log. A tab shows only to who may open it. -->
  <nav class="-mx-3 sm:-mx-6 lg:-mx-8 flex gap-1 border-b border-gray-200" :aria-label="$t('Grab Logs')">
    <Link
      v-for="tab in tabs"
      :key="tab.key"
      :href="tab.href"
      class="-mb-px rounded-t-md border px-4 py-1.5 text-sm"
      :class="tab.key === current ? 'border-gray-200 border-b-white bg-white font-semibold text-indigo-700' : 'border-transparent text-gray-600 hover:text-gray-900'"
      :aria-current="tab.key === current ? 'page' : undefined"
    >{{ $t(tab.name) }}</Link>
  </nav>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import { tk } from '@/i18n'

defineProps({
  // 'menu-sync' | 'errors'
  current: { type: String, required: true },
})

const ALL = [
  { key: 'menu-sync', name: tk('Menu Sync'), href: '/grab-logs/menu-sync', permission: 'read delivery-platform-product-mappings' },
  { key: 'errors', name: tk('Errors'), href: '/grab-logs/errors', permission: 'read delivery-platform-vends' },
]

const page = usePage()
const tabs = computed(() => ALL.filter(tab => (page.props.auth?.permissions ?? []).includes(tab.permission)))
</script>
