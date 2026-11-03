<template>

  <Head :title="$t('SKU Links')" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ $t('SKU Links') }}
        <span class="ml-2 align-middle inline-flex items-center rounded px-1.5 py-0.5 text-xs font-bold border bg-sky-100 text-sky-800 border-sky-300">
          {{ $t('Smart Freezer') }}
        </span>
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3 space-y-4">
      <div v-if="$page.props.flash.success" class="rounded-md border border-green-300 bg-green-50 px-3 py-2 text-sm text-green-800">
        {{ $page.props.flash.success }}
      </div>
      <div v-if="$page.props.errors.link" class="rounded-md border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800">
        {{ $page.props.errors.link }}
      </div>

      <section class="bg-white rounded-md border p-3">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
          <h3 class="text-base font-semibold text-gray-800">{{ $t('What the AI is asked about') }}</h3>
          <Link href="/ai-recognition" class="text-sm font-medium text-indigo-600 hover:underline">{{ $t('Back to AI Recognition') }}</Link>
        </div>
        <p class="mt-1 text-xs text-gray-500">
          {{ $t('The AI can only name a product on this list. A product missing from it is not checked, and the AI may call it something else.') }}
        </p>
        <p v-if="!coverage.length" class="mt-3 text-sm text-gray-500">{{ $t('No freezer has products on its planogram.') }}</p>

        <div v-for="machine in coverage" :key="machine.vend_id" class="mt-3 rounded-md border">
          <div class="flex flex-wrap items-center gap-2 border-b bg-gray-50 px-3 py-2">
            <MachineIdLink :code="machine.code_label" class="font-semibold" />
            <span class="text-sm tabular-nums" :class="machine.missing.length ? 'text-amber-800' : 'text-green-700'">
              {{ $t(':on_list of :total products on the AI\'s list', { on_list: machine.on_list, total: machine.total }) }}
            </span>
          </div>
          <ul v-if="machine.missing.length" class="divide-y">
            <li v-for="product in machine.missing" :key="product.product_id" class="px-3 py-2">
              <div class="flex flex-wrap items-baseline gap-2 text-sm">
                <span class="font-mono text-xs text-gray-500">{{ product.code }}</span>
                <span class="font-medium text-gray-800">{{ product.name }}</span>
                <span class="rounded px-1.5 py-0.5 text-xs font-semibold" :class="reasonClass(product.reason)">{{ reasonLabel(product) }}</span>
              </div>
              <ul v-if="product.suggestions.length" class="mt-1.5 space-y-1">
                <li v-for="entry in product.suggestions" :key="entry.id" class="flex flex-wrap items-center gap-2 text-sm">
                  <button
                    v-if="canLink"
                    type="button"
                    class="rounded border border-indigo-300 bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100 disabled:opacity-50"
                    :disabled="busy"
                    @click="link(product, entry)"
                  >
                    {{ $t('Link this') }}
                  </button>
                  <span class="text-gray-800">{{ entry.name }}</span>
                  <span class="font-mono text-xs text-gray-500">{{ entry.product_code }}</span>
                  <span v-if="entry.spec" class="text-xs text-gray-500">{{ entry.spec }}</span>
                  <span class="text-xs text-gray-400">{{ $t(':score% similar', { score: entry.score }) }}</span>
                </li>
              </ul>
            </li>
          </ul>
        </div>
      </section>

      <section v-if="seesLibrary" class="bg-white rounded-md border p-3">
        <h3 class="text-base font-semibold text-gray-800">{{ $t('Registered with Zijia, not linked to a product') }}</h3>
        <p class="mt-1 text-xs text-gray-500">
          {{ $t('A SKU whose name matches exactly one product links by itself within 3 minutes. These need a person, because a wrong link makes the AI name the wrong product and can charge the wrong amount.') }}
        </p>
        <p v-if="!library.length" class="mt-3 text-sm text-gray-500">{{ $t('Every registered SKU is linked.') }}</p>
        <ul v-else class="mt-2 divide-y">
          <li v-for="entry in library" :key="entry.id" class="py-2">
            <div class="flex flex-wrap items-baseline gap-2 text-sm">
              <span class="font-medium text-gray-800">{{ entry.name }}</span>
              <span class="font-mono text-xs text-gray-500">{{ entry.product_code }}</span>
              <span v-if="entry.spec" class="text-xs text-gray-500">{{ entry.spec }}</span>
              <span v-if="entry.updated_at" class="text-xs text-gray-400">{{ entry.updated_at }}</span>
              <button
                v-if="canLink"
                type="button"
                class="ml-auto text-xs font-medium text-gray-500 hover:underline disabled:opacity-50"
                :disabled="busy"
                @click="post(`/ai-recognition/sku-links/library/${entry.id}/dismiss`)"
              >
                {{ $t('Not ours') }}
              </button>
            </div>
            <ul v-if="entry.matches.length" class="mt-1.5 space-y-1">
              <li v-for="match in entry.matches" :key="match.product_id" class="flex flex-wrap items-center gap-2 text-sm">
                <button
                  v-if="canLink"
                  type="button"
                  class="rounded border border-indigo-300 bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100 disabled:opacity-50"
                  :disabled="busy"
                  @click="link(match, entry)"
                >
                  {{ $t('Link this') }}
                </button>
                <span class="font-mono text-xs text-gray-500">{{ match.code }}</span>
                <span class="text-gray-800">{{ match.name }}</span>
                <span class="text-xs text-gray-400">{{ $t(':score% similar', { score: match.score }) }}</span>
              </li>
            </ul>
            <p v-else class="mt-1 text-xs text-gray-500">{{ $t('No similar product without a barcode.') }}</p>
          </li>
        </ul>

        <details v-if="dismissed.length" class="mt-3 border-t pt-2">
          <summary class="cursor-pointer text-sm text-gray-600">{{ $t('Marked not ours (:count)', { count: dismissed.length }) }}</summary>
          <ul class="mt-2 space-y-1">
            <li v-for="entry in dismissed" :key="entry.id" class="flex flex-wrap items-center gap-2 text-sm">
              <span class="text-gray-700">{{ entry.name }}</span>
              <span class="font-mono text-xs text-gray-500">{{ entry.product_code }}</span>
              <button
                v-if="canLink"
                type="button"
                class="text-xs font-medium text-indigo-600 hover:underline disabled:opacity-50"
                :disabled="busy"
                @click="post(`/ai-recognition/sku-links/library/${entry.id}/restore`)"
              >
                {{ $t('Restore') }}
              </button>
            </li>
          </ul>
        </details>
      </section>
    </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import MachineIdLink from '@/Components/MachineIdLink.vue'
import { t } from '@/i18n'

defineProps({
  coverage: Array,
  library: Array,
  dismissed: Array,
  seesLibrary: Boolean,
  canLink: Boolean,
})

const busy = ref(false)

function post(url, data = {}) {
  router.post(url, data, {
    preserveScroll: true,
    onStart: () => { busy.value = true },
    onFinish: () => { busy.value = false },
  })
}

function link(product, entry) {
  const productId = product.product_id
  const message = t('Link :product :name to Zijia\'s ":entry" (:barcode)? The AI will look for it from the next door session on.', {
    product: product.code, name: product.name, entry: entry.name, barcode: entry.product_code,
  })
  if (confirm(message)) {
    post('/ai-recognition/sku-links/link', { product_id: productId, library_sku_id: entry.id })
  }
}

function reasonLabel(product) {
  if (product.reason === 'barcode_unknown') {
    return t('Barcode :barcode is not in Zijia\'s library', { barcode: product.barcode })
  }
  return product.reason === 'possible_match' ? t('Possible match below') : t('Not registered with Zijia')
}

function reasonClass(reason) {
  if (reason === 'barcode_unknown') {
    return 'bg-red-50 text-red-800 border border-red-300'
  }
  return reason === 'possible_match' ? 'bg-amber-50 text-amber-800 border border-amber-300' : 'bg-gray-100 text-gray-700 border border-gray-300'
}
</script>
