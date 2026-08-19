<template>
  <Head title="CityBox Products" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">CityBox Products</h2>
        <div class="flex items-center space-x-3">
          <span class="text-xs text-gray-500" v-if="lastSync">Last catalog sync: {{ lastSync }}</span>
          <span class="text-xs text-gray-400" v-else>Never synced</span>
          <Button
            v-if="permissions.includes('update products')"
            class="bg-indigo-600 hover:bg-indigo-700 text-white flex items-center space-x-1"
            :disabled="!enabled || syncing"
            :title="enabled ? 'Pull the latest SKU catalog from CityBox now' : 'CityBox integration is disabled'"
            @click.prevent="syncNow"
          >
            <ArrowPathIcon class="w-4 h-4" :class="syncing ? 'animate-spin' : ''" />
            <span>{{ syncing ? 'Syncing…' : 'Sync now' }}</span>
          </Button>
        </div>
      </div>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="rounded-md bg-indigo-50 p-3 mb-3 text-xs text-indigo-800">
        The catalog mirrors CityBox's own product list (their ERP) automatically every hour and whenever a chiller shows a new SKU.
        Nothing here is created by hand — <b>map</b> each CityBox SKU to the mark1 product it represents so cost / GP and pick lists resolve.
        Unmapped SKUs still count stock and pick quantities; only cost is blank until mapped.
      </div>

      <!-- Tabs -->
      <div class="flex space-x-2 mb-3">
        <button v-for="t in tabs" :key="t.key" type="button"
          class="px-3 py-1.5 rounded-md text-sm border"
          :class="tab === t.key ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
          @click="go(t.key)">
          {{ t.label }} <span class="ml-1 opacity-70" v-if="t.count !== null">({{ t.count }})</span>
        </button>
      </div>

      <!-- Sync log tab -->
      <div v-if="tab === 'log'" class="bg-white shadow rounded-md overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-gray-600">
            <tr>
              <th class="px-3 py-2 text-left">When</th><th class="px-3 py-2 text-left">Source</th><th class="px-3 py-2 text-left">By</th>
              <th class="px-3 py-2 text-right">Fetched</th><th class="px-3 py-2 text-right">Added</th><th class="px-3 py-2 text-right">Updated</th>
              <th class="px-3 py-2 text-right">Delisted</th><th class="px-3 py-2 text-left">Changed ids / error</th>
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
            <tr v-if="!logs.length"><td colspan="8" class="px-3 py-6 text-center text-gray-400">No sync runs yet.</td></tr>
          </tbody>
        </table>
      </div>

      <!-- Product tabs -->
      <div v-else class="bg-white shadow rounded-md overflow-x-auto">
        <table class="min-w-full text-sm">
          <thead class="bg-gray-50 text-gray-600">
            <tr>
              <th class="px-3 py-2 text-left w-16">Image</th>
              <th class="px-3 py-2 text-left">CityBox SKU</th>
              <th class="px-3 py-2 text-left">Details</th>
              <th class="px-3 py-2 text-left">mark1 product</th>
              <th class="px-3 py-2 text-left w-64">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="r in rows" :key="r.id" class="border-t align-top">
              <td class="px-3 py-2"><img v-if="r.img_url" :src="r.img_url" class="w-12 h-12 object-contain rounded bg-gray-50" /></td>
              <td class="px-3 py-2">
                <div class="font-medium text-gray-900">{{ r.name }}</div>
                <div class="text-xs text-gray-500">id {{ r.citybox_product_id }} · first seen {{ r.first_seen_at || '—' }}</div>
              </td>
              <td class="px-3 py-2 text-xs text-gray-600">
                <div v-if="r.volume || r.unit">{{ r.volume }} {{ r.unit }}</div>
                <div v-if="r.class_name">{{ r.class_name }}</div>
                <div v-if="r.last_price_cents !== null">S${{ (r.last_price_cents / 100).toFixed(2) }}</div>
              </td>
              <td class="px-3 py-2">
                <div v-if="r.product" class="text-gray-900">{{ r.product.name }} <span class="text-xs text-gray-500">({{ r.product.code }})</span></div>
                <div v-else-if="r.suggestion" class="text-xs text-amber-700">Suggested: {{ r.suggestion.name }} ({{ r.suggestion.code }})</div>
                <div v-else class="text-xs text-gray-400">Not mapped</div>
              </td>
              <td class="px-3 py-2">
                <div v-if="permissions.includes('update products')" class="flex flex-col space-y-1">
                  <div class="flex space-x-1">
                    <input v-model="picker[r.id].q" @input="search(r.id)" type="text" placeholder="Search mark1 product…"
                      class="flex-1 rounded-md border-gray-300 text-xs" />
                  </div>
                  <select v-if="picker[r.id].results.length" v-model="picker[r.id].chosen" class="rounded-md border-gray-300 text-xs">
                    <option :value="null">— pick —</option>
                    <option v-for="p in picker[r.id].results" :key="p.id" :value="p.id">{{ p.name }} ({{ p.code }})</option>
                  </select>
                  <div class="flex space-x-1">
                    <Button v-if="r.suggestion && !r.product" class="bg-amber-500 hover:bg-amber-600 text-white text-xs" @click.prevent="mapTo(r, r.suggestion.id)">Accept suggestion</Button>
                    <Button class="bg-indigo-600 hover:bg-indigo-700 text-white text-xs" :disabled="!picker[r.id].chosen" @click.prevent="mapTo(r, picker[r.id].chosen)">Map</Button>
                    <Button v-if="r.product" class="bg-gray-200 hover:bg-gray-300 text-gray-800 text-xs" @click.prevent="mapTo(r, null)">Unmap</Button>
                  </div>
                </div>
              </td>
            </tr>
            <tr v-if="!rows.length"><td colspan="5" class="px-3 py-6 text-center text-gray-400">
              {{ tab === 'unmapped' ? 'Every CityBox SKU is mapped.' : 'Nothing here.' }}
            </td></tr>
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
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref, watch } from 'vue';
import { useToast } from 'vue-toastification';
import axios from 'axios';

const props = defineProps({
  tab: String, rows: Array, counts: Object, logs: Array, lastSync: String, enabled: Boolean,
});
const toast = useToast();
const permissions = usePage().props.auth.permissions;
const syncing = ref(false);

const tabs = computed(() => [
  { key: 'unmapped', label: 'Unmapped', count: props.counts.unmapped },
  { key: 'mapped', label: 'Mapped', count: props.counts.mapped },
  { key: 'delisted', label: 'Delisted', count: props.counts.delisted },
  { key: 'log', label: 'Sync log', count: null },
]);

// Per-row picker state, keyed by row id.
const picker = reactive({});
function seedPickers() { (props.rows || []).forEach(r => { if (!picker[r.id]) picker[r.id] = { q: '', results: [], chosen: null, timer: null }; }); }
seedPickers();
watch(() => props.rows, seedPickers);

function go(tab) { router.get('/citybox/products', { tab }, { preserveState: false }); }

function search(rowId) {
  const p = picker[rowId];
  clearTimeout(p.timer);
  p.timer = setTimeout(async () => {
    if (!p.q || p.q.length < 2) { p.results = []; return; }
    const { data } = await axios.get('/citybox/products/search', { params: { q: p.q } });
    p.results = data;
  }, 250);
}

function mapTo(row, productId) {
  router.post(`/citybox/products/${row.id}/map`, { product_id: productId }, {
    preserveScroll: true, preserveState: false,
    onSuccess: () => toast.success(productId ? 'Mapped' : 'Unmapped', { timeout: 2500 }),
    onError: (e) => toast.error(e.product_id || 'Mapping failed', { timeout: 5000 }),
  });
}

function syncNow() {
  syncing.value = true;
  router.post('/citybox/products/sync', {}, {
    preserveScroll: true, preserveState: false,
    onSuccess: () => toast.success(usePage().props.flash?.success || 'CityBox synced', { timeout: 6000 }),
    onError: (e) => toast.error(e.citybox || 'Sync failed', { timeout: 6000 }),
    onFinish: () => { syncing.value = false; },
  });
}
</script>
