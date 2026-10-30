<template>
  <!-- Grab Promotions is one menu entry with two views (Brian, 2026-10-30): what Grab
       really runs (read from Grab) and the campaigns set up in mark1 (the old Grab
       Campaign pages, unchanged). -->
  <div class="-mx-3 sm:-mx-6 lg:-mx-8 mt-3 flex flex-wrap gap-1 border-b border-gray-200">
    <Link
      v-for="view in views"
      :key="view.key"
      :href="view.href"
      class="-mb-px rounded-t-md border px-4 py-2 text-sm font-medium"
      :class="current === view.key
        ? 'border-gray-200 border-b-white bg-white text-indigo-700'
        : 'border-transparent text-gray-600 hover:text-gray-900 hover:bg-gray-50'"
    >
      {{ view.name }}
      <span class="block text-xs font-normal text-gray-500">{{ view.hint }}</span>
    </Link>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { t } from '@/i18n';

defineProps({
  // 'grab' = what Grab runs; 'setup' = campaigns set up in mark1
  current: { type: String, required: true },
})

const views = [
  { key: 'grab', href: '/grab-promotions', name: t('Running on Grab'), hint: t('read from Grab every 10 minutes') },
  { key: 'setup', href: '/delivery-platform-campaigns', name: t('Set up in mark1'), hint: t('create, send and end campaigns') },
]
</script>
