<template>
  <Head :title="$t('CityBox Products')" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $t('CityBox Products') }}</h2>
        <div class="flex items-center space-x-3">
          <span class="text-xs text-gray-500" v-if="lastSync">{{ $t('Last catalog sync: :time', { time: lastSync }) }}</span>
          <span class="text-xs text-gray-400" v-else>{{ $t('Never synced') }}</span>
          <Button
            v-if="permissions.includes('update products')"
            class="bg-indigo-600 hover:bg-indigo-700 text-white flex items-center space-x-1"
            :disabled="!enabled || syncing"
            :title="enabled ? $t('Pull the latest SKU catalog from CityBox now') : $t('CityBox integration is disabled')"
            @click.prevent="syncNow"
          >
            <ArrowPathIcon class="w-4 h-4" :class="syncing ? 'animate-spin' : ''" />
            <span>{{ syncing ? $t('Syncing…') : $t('Sync now') }}</span>
          </Button>
        </div>
      </div>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="rounded-md bg-indigo-50 p-3 mb-3 text-xs text-indigo-800">
        {{ $t('Mirrors CityBox\'s own product list (their ERP) every hour and whenever a chiller shows a new SKU.') }}
        {{ $t('Each SKU automatically gets a ConnectVend product under the') }} <b>Citybox</b> {{ $t('operator — product code = CityBox id, name and image follow CityBox, warehouse qty from the self-system ledger. Nothing to map by hand; edit costs on the product itself.') }}
        <span v-if="counts.unlinked" class="text-red-700 font-semibold">{{ $t(':count SKU(s) have no product — is the Citybox operator seeded?', { count: counts.unlinked }) }}</span>
      </div>

      <!-- Tabs -->
      <div class="flex space-x-2 mb-3">
        <button v-for="t in tabs" :key="t.key" type="button"
          class="px-3 py-1.5 rounded-md text-sm border"
          :class="tab === t.key ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
          @click="go(t.key)">
          {{ $t(t.label) }} <span class="ml-1 opacity-70" v-if="t.count !== null">({{ t.count }})</span>
        </button>
      </div>

      <!-- Sync log tab -->
      <div v-if="tab === 'log'" class="bg-white shadow rounded-md overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-gray-600">
            <tr>
              <th class="px-3 py-2 text-left">{{ $t('When') }}</th><th class="px-3 py-2 text-left">{{ $t('Source') }}</th><th class="px-3 py-2 text-left">{{ $t('By') }}</th>
              <th class="px-3 py-2 text-right">{{ $t('Fetched') }}</th><th class="px-3 py-2 text-right">{{ $t('Added') }}</th><th class="px-3 py-2 text-right">{{ $t('Updated') }}</th>
              <th class="px-3 py-2 text-right">{{ $t('Delisted') }}</th><th class="px-3 py-2 text-left">{{ $t('Changed ids / error') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="l in logs" :key="l.id" class="border-t" :class="l.error ? 'bg-red-50' : ''">
              <td class="px-3 py-2 whitespace-nowrap">{{ l.started_at }}</td>
              <td class="px-3 py-2">{{ l.source }}</td>
              <td class="px-3 py-2">{{ l.by || '—' }}</td>
              <td class="px-3 py-2 text-right">{{ l.fetched }}</td>
              <td class="px-3 py-2 text-right" :class="l.added ? 'text-green-700 font-semibold' : ''">{{ l.added }}</td>
              <td class="px-3 py-2 text-right" :class="l.updated ? 'text-blue-700' : ''">{{ l.updated }}</td>
              <td class="px-3 py-2 text-right" :class="l.delisted ? 'text-red-700 font-semibold' : ''">{{ l.delisted }}</td>
              <td class="px-3 py-2 text-xs text-gray-600">
                <span v-if="l.error" class="text-red-700">{{ l.error }}</span>
                <span v-else>
                  <span v-if="l.details && l.details.added && l.details.added.length">+{{ l.details.added.join(', ') }} </span>
                  <span v-if="l.details && l.details.updated && l.details.updated.length">~{{ l.details.updated.join(', ') }} </span>
                  <span v-if="l.details && l.details.delisted && l.details.delisted.length">−{{ l.details.delisted.join(', ') }}</span>
                </span>
              </td>
            </tr>
            <tr v-if="!logs.length"><td colspan="8" class="px-3 py-6 text-center text-gray-400">{{ $t('No sync runs yet.') }}</td></tr>
          </tbody>
        </table>
      </div>

      <!-- Catalog / delisted -->
      <div v-else class="bg-white shadow rounded-md overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-gray-600">
            <tr>
              <th class="px-3 py-2 text-left w-16">{{ $t('Image') }}</th>
              <th class="px-3 py-2 text-left">{{ $t('CityBox SKU') }}</th>
              <th class="px-3 py-2 text-left">{{ $t('Details') }}</th>
              <th class="px-3 py-2 text-left">{{ $t('ConnectVend product') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in rows" :key="r.id" class="border-t align-top">
              <td class="px-3 py-2"><img v-if="r.img_url" :src="r.img_url" class="w-12 h-12 object-contain rounded-lg bg-white border border-gray-200 p-0.5" /></td>
              <td class="px-3 py-2">
                <div class="font-medium text-gray-900">{{ r.name }}</div>
                <div class="text-xs text-gray-500">{{ $t('id :id · first seen :first · last seen :last', { id: r.citybox_product_id, first: r.first_seen_at || '—', last: r.last_seen_at || '—' }) }}</div>
              </td>
              <td class="px-3 py-2 text-xs text-gray-600">
                <div v-if="r.volume || r.unit">{{ r.volume }} {{ r.unit }}</div>
                <div v-if="r.class_name">{{ r.class_name }}</div>
                <div v-if="r.last_price_cents !== null">S${{ (r.last_price_cents / 100).toFixed(2) }}</div>
              </td>
              <td class="px-3 py-2">
                <Link v-if="r.product" :href="`/products/${r.product.id}/edit`" class="text-indigo-600 hover:underline">
                  {{ r.product.name }} <span class="text-xs text-gray-500">({{ r.product.code }})</span>
                </Link>
                <span v-else class="text-xs text-red-600">{{ $t('No product yet — next sync creates it') }}</span>
              </td>
            </tr>
            <tr v-if="!rows.length"><td colspan="4" class="px-3 py-6 text-center text-gray-400">{{ $t('Nothing here.') }}</td></tr>
          </tbody>
        </table>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import { ArrowPathIcon } from '@heroicons/vue/20/solid';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useToast } from 'vue-toastification';
import { t, tk } from '@/i18n';

const props = defineProps({
  tab: String, rows: Array, counts: Object, logs: Array, lastSync: String, enabled: Boolean,
});
const toast = useToast();
const permissions = usePage().props.auth.permissions;
const syncing = ref(false);

const tabs = computed(() => [
  { key: 'catalog', label: tk('Catalog'), count: props.counts.catalog },
  { key: 'delisted', label: tk('Delisted'), count: props.counts.delisted },
  { key: 'log', label: tk('Sync log'), count: null },
]);

function go(tab) { router.get('/citybox/products', { tab }, { preserveState: false }); }

function syncNow() {
  syncing.value = true;
  router.post('/citybox/products/sync', {}, {
    preserveScroll: true, preserveState: false,
    onSuccess: () => toast.success(usePage().props.flash?.success || t('CityBox synced'), { timeout: 6000 }),
    onError: (e) => toast.error(e.citybox || t('Sync failed'), { timeout: 6000 }),
    onFinish: () => { syncing.value = false; },
  });
}
</script>
