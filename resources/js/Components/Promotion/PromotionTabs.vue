<template>
  <!-- Happy Hour and the next-visit voucher sit side by side (Brian, 2026-11-20): one tab each, shown to whoever may read it. -->
  <nav class="flex gap-1 border-b border-gray-300 -mx-3 sm:-mx-6 lg:-mx-8 px-3 mt-2">
    <Link v-for="tab in tabs" :key="tab.href" :href="tab.href"
      class="px-4 py-2 text-sm font-semibold rounded-t-md border border-b-0 -mb-px"
      :class="tab.key === active ? 'bg-white text-indigo-700 border-gray-300' : 'bg-gray-100 text-gray-600 border-transparent hover:text-gray-800'">
      {{ $t(tab.name) }}
    </Link>
  </nav>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { tk } from '@/i18n';

defineProps({ active: { type: String, required: true } })

const permissions = computed(() => usePage().props.auth?.permissions ?? [])
const tabs = computed(() => [
  { key: 'happy-hour', name: tk('Happy Hour'), href: '/happy-hour-campaigns', permission: 'read happy-hour-campaigns' },
  { key: 'prepaid-voucher', name: tk('Next-visit Voucher'), href: '/prepaid-vouchers', permission: 'read prepaid-vouchers' },
].filter(tab => permissions.value.includes(tab.permission)))
</script>
