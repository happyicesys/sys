<template>
  <!--
    Smart-chiller (CityBox) channel overview — read-only 5-layer planogram.
    Third branch beside ChannelOverview (vending) and SmartFreezerChannelOverview
    (freezer), chosen in CustomerIndex by machine_type === 'smart_chiller'.

    Data = vend_channels (qty / capacity / amount / product) laid out by the
    layer digit of the channel code, joined to the CityBox catalog for
    name/thumbnail. Layer 5 is drawn at the TOP like the physical rack.
    Capacity here IS CityBox's par (their portal is the planogram source of
    truth); qty is the 3-min poll. Nothing is editable from this popup.
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
          <div v-if="loading" class="py-16 text-center text-sm text-gray-500">Loading planogram…</div>
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
                <span v-if="data.unmapped_count" class="text-amber-700">· {{ data.unmapped_count }} unmapped SKU{{ data.unmapped_count === 1 ? '' : 's' }}</span>
              </div>
              <div class="text-xs text-gray-500">
                CityBox: <span class="font-medium text-gray-700">{{ data.citybox_name || '—' }}</span>
                <span v-if="data.device_type"> · {{ data.device_type }}</span>
                · {{ data.vend.equipment_id }}
              </div>
            </div>

            <!-- The rack: layer 5 at top. Built for a driver's phone first: layers stack
                 vertically, SKUs wrap in a responsive grid (a layer can hold many), each
                 tile leads with a big thumbnail and a big count so the whole cabinet can be
                 read at a glance. Empty layers collapse to one slim row so all five fit. -->
            <div class="rounded-xl border-2 border-gray-300 bg-white p-2 sm:p-3 space-y-2">
              <div v-for="layer in data.layers" :key="layer.layer"
                   class="rounded-lg border bg-gray-50"
                   :class="layer.channels.length ? 'border-gray-200 px-2 py-2 sm:px-3' : 'border-dashed border-gray-200 px-3 py-1.5'">
                <div class="flex items-center gap-3">
                  <span class="text-sm font-bold text-gray-700 w-16 shrink-0">Layer {{ layer.layer }}</span>
                  <template v-if="layer.channels.length">
                    <div class="flex-1 h-2 rounded-full bg-gray-200 overflow-hidden" :title="layer.qty + ' of ' + layer.capacity">
                      <div class="h-full rounded-full" :class="barClass(layer.qty, layer.capacity)" :style="{ width: pct(layer.qty, layer.capacity) + '%' }"></div>
                    </div>
                    <span class="text-sm font-semibold tabular-nums shrink-0" :class="layer.qty === 0 ? 'text-red-600' : 'text-gray-800'">{{ layer.qty }} / {{ layer.capacity }}</span>
                    <span class="text-xs text-gray-500 shrink-0 hidden sm:inline">{{ layer.channels.length }} SKU{{ layer.channels.length === 1 ? '' : 's' }}</span>
                  </template>
                  <span v-else class="text-xs text-gray-400 italic">empty</span>
                </div>

                <div v-if="layer.channels.length" class="mt-2 grid gap-2 grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                  <div v-for="ch in layer.channels" :key="ch.code"
                    class="relative rounded-lg border-2 bg-white p-2 flex flex-col min-w-0"
                    :class="ch.qty === 0 ? 'border-red-400' : (ch.qty <= 2 ? 'border-amber-300' : 'border-gray-200')"
                    :title="`Channel ${ch.code}`">
                    <span class="absolute top-1 left-1 rounded bg-gray-800/80 text-white text-[10px] font-semibold px-1 leading-4">#{{ ch.code }}</span>
                    <span v-if="ch.qty === 0" class="absolute top-1 right-1 rounded bg-red-600 text-white text-[10px] font-bold px-1 leading-4">OUT</span>
                    <span v-else-if="ch.qty <= 2" class="absolute top-1 right-1 rounded bg-amber-500 text-white text-[10px] font-bold px-1 leading-4">LOW</span>
                    <div class="w-full h-24 sm:h-28 rounded-md bg-gray-50 flex items-center justify-center overflow-hidden">
                      <img v-if="ch.thumbnail" :src="ch.thumbnail" loading="lazy" class="w-full h-full object-contain p-1" />
                      <span v-else class="text-3xl text-gray-300">🧃</span>
                    </div>
                    <div v-if="ch.product && ch.product.code" class="mt-1.5 text-[11px] font-mono font-semibold text-gray-800 truncate" :title="`Product code ${ch.product.code}`">{{ ch.product.code }}</div>
                    <div class="text-xs sm:text-[13px] font-medium text-gray-900 leading-snug line-clamp-2 min-h-[2.5em]" :class="ch.product && ch.product.code ? 'mt-0.5' : 'mt-1.5'" :title="ch.product ? ch.product.name : (ch.citybox_name || '')">
                      {{ ch.product ? ch.product.name : (ch.citybox_name || 'Unmapped SKU') }}
                    </div>
                    <div class="mt-auto pt-1 flex items-end justify-between gap-1">
                      <span class="text-xl sm:text-2xl font-bold tabular-nums leading-none" :class="ch.qty === 0 ? 'text-red-600' : (ch.qty <= 2 ? 'text-amber-600' : 'text-green-700')">
                        {{ ch.qty }}<span class="text-sm font-medium text-gray-400"> / {{ ch.capacity }}</span>
                      </span>
                      <span class="text-xs text-gray-600 tabular-nums">S${{ (ch.amount_cents / 100).toFixed(2) }}</span>
                    </div>
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

function pct(qty, cap) { return cap ? Math.max(0, Math.min(100, Math.round((qty / cap) * 100))) : 0 }
function barClass(qty, cap) { const p = pct(qty, cap); return qty === 0 ? 'bg-red-500' : (p <= 40 ? 'bg-amber-400' : 'bg-green-500') }

function onModalClose() { emit('modalClose') }
onMounted(load)
</script>
