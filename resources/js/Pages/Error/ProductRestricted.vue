<template>
  <BreezeAuthenticatedLayout title="Not available">
    <Head title="Not available" />

    <div class="max-w-2xl mx-auto mt-10 px-4">
      <div class="rounded-lg border border-amber-200 bg-amber-50 p-6">
        <h2 class="text-lg font-semibold text-amber-900">
          Not available in a product-restricted view
        </h2>
        <p class="mt-2 text-sm text-amber-800 leading-relaxed">
          {{ message }}
        </p>
        <div class="mt-5">
          <Link
            href="/vends/transactions"
            class="inline-flex items-center rounded-md bg-amber-600 px-3 py-2 text-sm font-medium text-white hover:bg-amber-700"
          >
            Go to Sales Transactions
          </Link>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import { Head, Link } from '@inertiajs/vue3'

defineProps({
  message: { type: String, default: '' },
})
</script>
