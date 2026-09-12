<template>
  <!--
    Smart-chiller (CityBox) channel overview — read-only 5-layer planogram.
    Third branch beside ChannelOverview (vending) and SmartFreezerChannelOverview
    (freezer), chosen in CustomerIndex by machine_type === 'smart_chiller'.

    Data = vend_channels (qty / capacity / amount / product) laid out by the
    layer digit of the channel code, joined to the CityBox catalog for
    name/thumbnail. Layers run 1 → 5 top to bottom (Brian, 2026-09-10 — the
    order their OPS Pro lists them in).
    Capacity here IS CityBox's par (their portal is the planogram source of
    truth); qty is refreshed live when this popup opens, and by the per-minute
    poll in between. Nothing is editable from this popup.
  -->
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="onModalClose">
      <template #header>
        <div class="flex flex-col md:flex-row md:items-center md:space-x-2 text-black">
          <span class="inline-flex items-center gap-1.5 rounded-full bg-sky-700 text-white text-xs font-semibold px-2.5 py-1">
            Smart Chiller · CityBox
          </span>
          <span v-if="vend.code" class="font-semibold">ID# {{ vend.code }}</span>
          <span v-if="vend.customer_code" class="text-gray-600 text-sm">({{ vend.customer_code }}) {{ vend.customer_name }}</span>
        </div>
      </template>

      <template #default>
        <div class="min-h-40">
          <div v-if="loading" class="py-16 text-center text-sm text-gray-500">Syncing with CityBox…</div>
          <div v-else-if="loadError" class="py-16 text-center text-sm text-red-600">{{ loadError }}</div>
          <div v-else class="space-y-3">
            <!-- Summary strip -->
            <div class="flex flex-col gap-2 rounded-lg bg-sky-50 ring-1 ring-sky-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
              <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-700">
                <span><span class="font-semibold text-sky-800">{{ data.total_qty }}</span> / <span class="font-semibold text-gray-900">{{ data.total_capacity }}</span> in cabinet</span>
                <span class="text-gray-400">·</span>
                <span :class="data.online ? 'text-green-700' : 'text-red-600'">{{ data.online ? 'online' : ('offline' + (data.offline_since ? ' since ' + data.offline_since : '')) }}</span>
                <span class="text-gray-400">·</span>
                <span class="text-gray-500">stock as of {{ data.synced_at || '—' }}</span>
                <!-- Opening this overview pulls CityBox live; say so when that pull failed
                     (offline chiller / API blip) so nobody reads stale numbers as live. -->
                <span v-if="data.refreshed === false" class="text-amber-700" :title="data.refresh_error">· live refresh failed — showing last sync</span>
                <span v-if="data.unmapped_count" class="text-amber-700">· {{ data.unmapped_count }} unmapped SKU{{ data.unmapped_count === 1 ? '' : 's' }}</span>
                <!-- The cabinet total counts channels only, so stock sitting on a SKU their
                     restock config does not carry would silently go missing from it. Say so. -->
                <span v-if="offPlanogramAll.length" class="text-gray-500"
                  title="In the cabinet but not in CityBox's restock config — no channel, no par, ops cannot refill it.">
                  · +{{ data.off_planogram_qty }} off-planogram
                </span>
              </div>
              <div class="flex flex-col gap-1 sm:items-end">
                <!-- Restocking view: a driver only cares about what is still sellable
                     (and often about one SKU), so let them drop the OUT tiles and
                     search by code or name. Layer bars keep the true cabinet numbers
                     either way — only the tile grid is filtered. -->
                <div class="flex items-center gap-3">
                  <div class="relative">
                    <input type="text" v-model="search" placeholder="Code or name…"
                      title="Filter tiles by product code or product name."
                      class="w-36 sm:w-40 shadow-sm text-xs py-1 pr-6 border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500" />
                    <button v-if="search" type="button" title="Clear"
                      class="absolute inset-y-0 right-0 px-1.5 text-gray-400 hover:text-gray-700 leading-none"
                      @click="search = ''">×</button>
                  </div>
                  <label class="flex items-center gap-2 text-xs text-gray-700 cursor-pointer select-none whitespace-nowrap"
                    title="Hide channels that are at 0 — show only SKUs with stock left. Layer totals still count the whole layer.">
                    <input type="checkbox" v-model="inStockOnly"
                      class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                    <span>In stock only<span v-if="soldOutCount" class="text-gray-500"> (hides {{ soldOutCount }})</span></span>
                  </label>
                </div>
                <div class="text-xs text-gray-500">
                  CityBox: <span class="font-medium text-gray-700">{{ data.citybox_name || '—' }}</span>
                  <span v-if="data.device_type"> · {{ data.device_type }}</span>
                  · {{ data.vend.equipment_id }}
                </div>
              </div>
            </div>

            <!-- The rack: layer 1 first, 5 last (Brian, 2026-09-10 — same order as their
                 OPS Pro layer list). Built for a driver's phone first: layers stack
                 vertically, SKUs wrap in a responsive grid (a layer can hold many), each
                 tile leads with a big thumbnail and a big count so the whole cabinet can be
                 read at a glance. Empty layers collapse to one slim row so all five fit. -->
            <div class="rounded-xl border-2 border-gray-300 bg-white p-2 sm:p-3 space-y-2">
              <div v-for="layer in layers" :key="layer.layer"
                   class="rounded-lg border bg-gray-50"
                   :class="layer.total_channels ? 'border-gray-200 px-2 py-2 sm:px-3' : 'border-dashed border-gray-200 px-3 py-1.5'">
                <div class="flex items-center gap-3">
                  <span class="text-sm font-bold text-gray-700 w-16 shrink-0">Layer {{ layer.layer }}</span>
                  <template v-if="layer.total_channels">
                    <div class="flex-1 h-2 rounded-full bg-gray-200 overflow-hidden" :title="layer.qty + ' of ' + layer.capacity">
                      <div class="h-full rounded-full" :class="barClass(layer.qty, layer.capacity)" :style="{ width: pct(layer.qty, layer.capacity) + '%' }"></div>
                    </div>
                    <span class="text-sm font-semibold tabular-nums shrink-0" :class="layer.qty === 0 ? 'text-red-600' : 'text-gray-800'">{{ layer.qty }} / {{ layer.capacity }}</span>
                    <span class="text-xs text-gray-500 shrink-0 hidden sm:inline">
                      {{ layer.channels.length }}<template v-if="layer.channels.length !== layer.total_channels"> of {{ layer.total_channels }}</template>
                      SKU{{ layer.total_channels === 1 ? '' : 's' }}
                    </span>
                  </template>
                  <span v-else class="text-xs text-gray-400 italic">empty</span>
                </div>

                <div v-if="layer.total_channels && !layer.channels.length" class="mt-2 text-xs text-gray-400 italic">
                  <template v-if="query">nothing matching “{{ search.trim() }}” here</template>
                  <template v-else>all {{ layer.total_channels }} SKU{{ layer.total_channels === 1 ? '' : 's' }} sold out</template>
                </div>

                <div v-if="layer.channels.length" class="mt-2 grid gap-2 grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                  <div v-for="ch in layer.channels" :key="ch.code || 'off-' + ch.citybox_product_id"
                    class="relative rounded-lg border-2 p-2 flex flex-col min-w-0"
                    :class="[
                      disabled(ch) ? 'bg-gray-50 border-gray-200 border-dashed' :
                        (ch.qty === 0 ? 'bg-white border-red-400' : (ch.qty <= 2 ? 'bg-white border-amber-300' : 'bg-white border-gray-200')),
                    ]"
                    :title="ch.off_plan ? `CityBox SKU ${ch.citybox_product_id} — on this shelf, not in CityBox's restock config` : (disabled(ch) ? `Channel ${ch.code} — disabled in CityBox` : `Channel ${ch.code}`)">
                    <span class="absolute top-1 left-1 rounded bg-gray-800/80 text-white text-[10px] font-semibold px-1 leading-4">{{ ch.off_plan ? '#—' : '#' + ch.code }}</span>
                    <span v-if="disabled(ch)" class="absolute top-1 right-1 rounded bg-gray-500 text-white text-[10px] font-bold px-1 leading-4">OFF</span>
                    <span v-else-if="ch.qty === 0" class="absolute top-1 right-1 rounded bg-red-600 text-white text-[10px] font-bold px-1 leading-4">OUT</span>
                    <span v-else-if="ch.qty <= 2" class="absolute top-1 right-1 rounded bg-amber-500 text-white text-[10px] font-bold px-1 leading-4">LOW</span>
                    <div class="w-full h-24 sm:h-28 rounded-md bg-gray-50 flex items-center justify-center overflow-hidden">
                      <img v-if="ch.thumbnail" :src="ch.thumbnail" loading="lazy" class="w-full h-full object-contain p-1" :class="[disabled(ch) ? 'opacity-50' : '']" />
                      <span v-else class="text-3xl text-gray-300">🧃</span>
                    </div>
                    <div v-if="ch.product && ch.product.code" class="mt-1.5 text-[11px] font-mono font-semibold truncate" :class="[disabled(ch) ? 'text-gray-400' : 'text-gray-800']" :title="`Product code ${ch.product.code}`">{{ ch.product.code }}</div>
                    <div class="text-xs sm:text-[13px] font-medium leading-snug line-clamp-2 min-h-[2.5em]"
                      :class="[ch.product && ch.product.code ? 'mt-0.5' : 'mt-1.5', disabled(ch) ? 'text-gray-400' : 'text-gray-900']"
                      :title="ch.product ? ch.product.name : (ch.citybox_name || '')">
                      {{ ch.product ? ch.product.name : (ch.citybox_name || 'Unmapped SKU') }}
                    </div>
                    <div class="mt-auto pt-1 flex items-end justify-between gap-1">
                      <span class="text-xl sm:text-2xl font-bold tabular-nums leading-none"
                        :class="disabled(ch) ? 'text-gray-400' : (ch.qty === 0 ? 'text-red-600' : (ch.qty <= 2 ? 'text-amber-600' : 'text-green-700'))">
                        {{ ch.qty }}<span class="text-sm font-medium text-gray-400"> / {{ ch.off_plan ? '—' : ch.capacity }}</span>
                      </span>
                      <span class="text-xs tabular-nums" :class="disabled(ch) ? 'text-gray-400' : 'text-gray-600'">S${{ (ch.amount_cents / 100).toFixed(2) }}</span>
                    </div>
                    <span v-if="disabled(ch) && !ch.off_plan" class="mt-1 text-[10px] text-gray-500">disabled in CityBox</span>
                    <span v-if="!ch.mapped" class="mt-1 text-[10px] text-amber-700">unmapped in ConnectVend</span>
                  </div>
                </div>
              </div>
            </div>

            <div class="flex justify-end">
              <Button class="bg-sky-700 hover:bg-sky-800 text-white flex items-center justify-center space-x-1 w-full sm:w-auto" :disabled="pulling" @click.prevent="pull">
                <ArrowPathIcon class="w-4 h-4" :class="pulling ? 'animate-spin' : ''" />
                <span>{{ pulling ? 'Pulling…' : 'Pull from CityBox' }}</span>
              </Button>
            </div>
          </div>
        </div>
      </template>
    </Modal>
  </Teleport>
</template>

<script setup>
import Button from '@/Components/Button.vue'
import Modal from '@/Components/Modal.vue'
import { ArrowPathIcon } from '@heroicons/vue/20/solid'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import { computed, onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'

const props = defineProps({ vend: Object, showModal: Boolean })
// Index rows alias the primary key as vend_id (vends.id AS vend_id) and may not carry `id`;
// the settings/ops pages pass a plain model. Accept both.
const vendId = computed(() => props.vend?.vend_id ?? props.vend?.id)
const emit = defineEmits(['modalClose'])
const toast = useToast()
const loading = ref(true)
const loadError = ref(null)
const pulling = ref(false)
const data = ref({ layers: [], vend: {} })
// Off by default: the popup's first job is to show the cabinet as it stands,
// OUT tiles included. The toggle is for the restock view.
const inStockOnly = ref(false)
const search = ref('')
const query = computed(() => search.value.trim().toLowerCase())

// Matches what the tile actually shows: the product code, the product name, and
// the CityBox name a still-unmapped SKU falls back to.
function matches(ch) {
  if (!query.value) return true
  return [ch.product && ch.product.code, ch.product && ch.product.name, ch.citybox_name, ch.citybox_product_id]
    .some((v) => v && String(v).toLowerCase().includes(query.value))
}

const soldOutCount = computed(() => (data.value.layers || [])
  .reduce((n, layer) => n + layer.channels.filter((ch) => ch.qty <= 0 && matches(ch)).length, 0))

// A SKU their live stock reports but their restock config does not carry has no
// channel and no par — yet it sits on a real shelf, so it rides in that layer as
// an ordinary tile, greyed (Brian, 2026-09-12). Everything else about it behaves
// like any other SKU: same search, same in-stock filter, same layout.
function offFor(layer) {
  return (data.value.off_planogram || [])
    .filter((sku) => Number(sku.layer) === layer)
    .map((sku) => ({ ...sku, off_plan: true, code: null, capacity: null }))
}

// Layer qty / capacity stay the server's whole-layer figures — the filter only
// drops tiles, so the bars never lie about how full the cabinet is. Off-planogram
// tiles are NOT added to them: they have no par to count against, and the bar is
// CityBox's par truth.
const layers = computed(() => (data.value.layers || []).map((layer) => {
  const all = [...layer.channels, ...offFor(layer.layer)]
  return {
    ...layer,
    total_channels: all.length,
    channels: all.filter((ch) => (inStockOnly.value ? ch.qty > 0 : true) && matches(ch)),
  }
}))

// Summary strip only: the TRUE off-planogram total, never the filtered view.
const offPlanogramAll = computed(() => data.value.off_planogram || [])

async function load() {
  loading.value = true; loadError.value = null
  try {
    const res = await axios.get(`/vends/${vendId.value}/citybox-planogram`)
    data.value = res.data
  } catch (e) {
    loadError.value = e?.response?.status === 403 ? 'This is not a Smart Chiller vend.' : "Couldn't load the planogram. Close and try again."
  } finally { loading.value = false }
}

function pull() {
  pulling.value = true
  router.post(`/vends/${vendId.value}/citybox-pull`, {}, {
    preserveScroll: true, preserveState: true,
    onSuccess: () => { toast.success('Pulled from CityBox', { timeout: 2500 }); load() },
    onError: (e) => toast.error(e.citybox || 'Pull failed', { timeout: 5000 }),
    onFinish: () => { pulling.value = false },
  })
}

// CityBox disabled the SKU (their product_list `status`), mirrored onto the
// mark1 product by the per-minute status sync. The channel is GREYED OUT, never
// removed — Brian, 2026-09-05 — matching how the ops-job channel list dims a
// product that is not available, so the cabinet still reads as it is stocked.
// …and a SKU with no channel at all: same greyscale, since ops cannot refill it.
function disabled(ch) { return !!(ch.off_plan || (ch.product && ch.product.is_active === false)) }

function pct(qty, cap) { return cap ? Math.max(0, Math.min(100, Math.round((qty / cap) * 100))) : 0 }
function barClass(qty, cap) { const p = pct(qty, cap); return qty === 0 ? 'bg-red-500' : (p <= 40 ? 'bg-amber-400' : 'bg-green-500') }

function onModalClose() { emit('modalClose') }
onMounted(load)
</script>
