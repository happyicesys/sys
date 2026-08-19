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

            <!-- The rack: layer 5 at top -->
            <div class="rounded-xl border-2 border-gray-300 bg-white p-3 space-y-2">
              <div v-for="layer in data.layers" :key="layer.layer" class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-2">
                <div class="flex items-center justify-between mb-1">
                  <span class="text-xs font-semibold text-gray-600">Layer {{ layer.layer }}</span>
                  <span class="text-xs" :class="layer.capacity ? 'text-gray-700' : 'text-gray-400'">
                    {{ layer.capacity ? (layer.qty + ' / ' + layer.capacity) : 'empty' }}
                  </span>
                </div>
                <div v-if="layer.channels.length" class="grid gap-2" :style="{ gridTemplateColumns: `repeat(${Math.max(layer.channels.length, 1)}, minmax(0, 1fr))` }">
                  <div v-for="ch in layer.channels" :key="ch.code"
                    class="rounded-md border bg-white p-2 flex items-center space-x-2 min-w-0"
                    :class="ch.qty === 0 ? 'border-red-300' : 'border-gray-200'"
                    :title="`Channel ${ch.code}`">
                    <img v-if="ch.thumbnail" :src="ch.thumbnail" class="w-10 h-10 object-contain rounded bg-gray-50 flex-shrink-0" />
                    <div class="min-w-0">
                      <div class="text-xs font-medium text-gray-900 truncate">{{ ch.product ? ch.product.name : (ch.citybox_name || 'Unmapped SKU') }}</div>
                      <div class="text-xs text-gray-500 truncate">
                        <span :class="ch.qty === 0 ? 'text-red-600 font-semibold' : ''">{{ ch.qty }}</span> / {{ ch.capacity }}
                        · S${{ (ch.amount_cents / 100).toFixed(2) }}
                        · <span class="text-gray-400">#{{ ch.code }}</span>
                        <span v-if="!ch.mapped" class="text-amber-700"> · unmapped</span>
                      </div>
                    </div>
                  </div>
                </div>
                <div v-else class="text-xs text-gray-400 italic">No products on this layer.</div>
              </div>
            </div>

            <div class="flex justify-end">
              <Button class="bg-sky-700 hover:bg-sky-800 text-white flex items-center space-x-1" :disabled="pulling" @click.prevent="pull">
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
import { onMounted, ref } from 'vue'
import { useToast } from 'vue-toastification'

const props = defineProps({ vend: Object, showModal: Boolean })
const emit = defineEmits(['modalClose'])
const toast = useToast()
const loading = ref(true)
const loadError = ref(null)
const pulling = ref(false)
const data = ref({ layers: [], vend: {} })

async function load() {
  loading.value = true; loadError.value = null
  try {
    const res = await axios.get(`/vends/${props.vend.id}/citybox-planogram`)
    data.value = res.data
  } catch (e) {
    loadError.value = e?.response?.status === 403 ? 'This is not a Smart Chiller vend.' : "Couldn't load the planogram. Close and try again."
  } finally { loading.value = false }
}

function pull() {
  pulling.value = true
  router.post(`/vends/${props.vend.id}/citybox-pull`, {}, {
    preserveScroll: true, preserveState: true,
    onSuccess: () => { toast.success('Pulled from CityBox', { timeout: 2500 }); load() },
    onError: (e) => toast.error(e.citybox || 'Pull failed', { timeout: 5000 }),
    onFinish: () => { pulling.value = false },
  })
}

function onModalClose() { emit('modalClose') }
onMounted(load)
</script>
