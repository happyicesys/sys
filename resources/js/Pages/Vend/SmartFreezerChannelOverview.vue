<template>
  <!--
    Smart-freezer channel overview — read-only 2D planogram viewer.

    The vending ChannelOverview reads vend_channels telemetry; a smart freezer
    never reports that, so this view is driven by the product mapping instead
    ("reverse" management — the planogram is the source of truth).

    The geometry deliberately mirrors what everyone else already sees: the APK's
    on-door `FreezerGrid` (KioskScreen.kt — browse screen and pick guide) and the
    ProductMapping `SmartFreezerLayout` editor. One bordered outer box holding
    six baskets in two columns — LEFT 1/2/3 top→bottom, RIGHT 4/5/6 — each basket
    a single horizontal strip of its divisions. So an ops user reading this popup,
    a driver at the machine and the customer at the touchscreen are all looking at
    the same picture of the same door.

    Column-major fill (grid-rows-3 + grid-flow-col) keeps the source array in
    natural 1..6 order while rendering the physical pairing 1↔4, 2↔5, 3↔6.
    Mobile stacks to a single column — a side-by-side pair would cramp.
  -->
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="onModalClose">
      <template #header>
        <div class="flex flex-col md:flex-row md:items-center md:space-x-2 text-black">
          <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-600 text-white text-xs font-semibold px-2.5 py-1">
            Smart Freezer
          </span>
          <span v-if="vend.code" class="font-semibold">ID# {{ vend.code }}</span>
          <span v-if="vend.customer_code" class="text-gray-600 text-sm">
            ({{ vend.customer_code }}) {{ vend.customer_name }}
          </span>
        </div>
      </template>

      <template #default>
        <div class="min-h-40">
          <div v-if="loading" class="py-16 text-center text-sm text-gray-500">
            Loading planogram…
          </div>

          <div v-else-if="loadError" class="py-16 text-center text-sm text-red-600">
            Couldn't load the planogram. Close and try again.
          </div>

          <div v-else-if="!items.length" class="py-16 text-center text-sm text-gray-500">
            No products mapped to this freezer yet.
          </div>

          <div v-else class="space-y-3">
            <!-- Summary strip: how full the door is, and whether qty means anything. -->
            <div class="flex flex-col gap-2 rounded-lg bg-indigo-50 ring-1 ring-indigo-100 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
              <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-gray-700">
                <span>
                  <span class="font-semibold text-indigo-700">{{ boundCount }}</span>
                  of
                  <span class="font-semibold text-gray-900">{{ totalSlots }}</span>
                  slots bound
                </span>
                <span v-if="hasStockFeed" class="text-gray-500">
                  ·
                  <span class="font-semibold text-gray-900">{{ totalQty }}</span>
                  pcs on the door
                </span>
              </div>

              <!--
                An unlabelled "—" on every cell reads as a bug. Say plainly that
                no stock feed exists yet: the APK sends no CHANNEL frame, so
                nothing writes vend_channels until mark1 owns that write.
              -->
              <span
                v-if="!hasStockFeed"
                class="inline-flex items-center gap-1.5 self-start rounded-full bg-amber-100 text-amber-800 ring-1 ring-amber-200 text-xs font-semibold px-2.5 py-1"
                v-tooltip="'This freezer reports no channel stock. Qty will populate once ops-job topup writes the baseline.'"
              >
                No stock feed yet
              </span>
              <span v-else-if="stockUpdatedAt" class="text-xs text-gray-500 self-start">
                Stock updated {{ stockUpdatedAt }}
              </span>
            </div>

            <!-- The freezer schematic. -->
            <div class="rounded-xl border-[3px] border-gray-800 bg-slate-50 p-3 md:p-4">
              <div class="grid grid-cols-1 gap-3 md:grid-cols-2 md:grid-rows-3 md:grid-flow-col md:gap-4">
                <article
                  v-for="basket in basketLayout"
                  :key="basket.basket"
                  class="flex flex-col rounded-lg border-2 border-gray-400 bg-white overflow-hidden"
                >
                  <header class="flex items-center gap-2 border-b border-gray-100 px-3 py-2">
                    <span class="inline-flex items-center justify-center h-6 min-w-6 px-2 rounded-md bg-gray-900 text-white text-xs font-semibold">
                      {{ basket.basket }}
                    </span>
                    <span class="text-sm font-medium text-gray-900">Basket {{ basket.basket }}</span>
                    <span class="text-xs text-gray-500">
                      ({{ basket.divisions }} slot{{ basket.divisions === 1 ? '' : 's' }})
                    </span>
                    <span
                      v-if="hasStockFeed && basketQty(basket) !== null"
                      class="ml-auto text-xs font-semibold"
                      :class="basketQty(basket) === 0 ? 'text-red-600' : 'text-gray-700'"
                    >
                      {{ basketQty(basket) }} pcs
                    </span>
                  </header>

                  <!--
                    Divisions laid out left→right across the full basket width, so
                    a one-division basket (e.g. channel 41) fills its basket with
                    no inner dividers — exactly as the door is built. Inline
                    grid-template rather than a Tailwind class map: the division
                    count comes from data and must never silently clamp a slot
                    out of view.
                  -->
                  <div class="flex-1 p-2">
                    <div class="grid gap-2" :style="{ gridTemplateColumns: `repeat(${basket.divisions}, minmax(0, 1fr))` }">
                      <div
                        v-for="cell in cellsFor(basket)"
                        :key="cell.code"
                        class="flex flex-col rounded-md p-2 transition"
                        :class="cell.item
                          ? 'bg-indigo-50/60 ring-1 ring-indigo-100'
                          : 'bg-gray-50 ring-1 ring-gray-200 border border-dashed border-gray-300'"
                      >
                        <div class="flex items-center justify-between gap-1 mb-1.5">
                          <span
                            class="inline-flex items-center justify-center px-1.5 py-0.5 rounded text-xs font-semibold"
                            :class="cell.item ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'"
                          >
                            {{ cell.code }}
                          </span>
                          <span
                            v-if="cell.item"
                            class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-bold"
                            :class="qtyClass(cell.item.qty)"
                            v-tooltip="qtyTooltip(cell.item)"
                          >
                            {{ qtyLabel(cell.item.qty) }}
                          </span>
                        </div>

                        <div v-if="cell.item" class="flex items-center gap-2 min-w-0">
                          <img
                            v-if="cell.item.thumbnail"
                            :src="cell.item.thumbnail"
                            class="h-12 w-12 rounded-md object-cover ring-1 ring-gray-200 flex-none"
                            loading="lazy"
                            alt=""
                          />
                          <div
                            v-else
                            class="h-12 w-12 rounded-md bg-gray-100 grid place-items-center text-gray-400 text-[10px] flex-none"
                          >
                            no image
                          </div>
                          <div class="flex flex-col min-w-0">
                            <span v-if="cell.item.product_code" class="text-[11px] font-semibold text-gray-500 truncate">
                              {{ cell.item.product_code }}
                            </span>
                            <span class="text-xs text-gray-900 truncate" :title="cell.item.product_name">
                              {{ cell.item.product_name }}
                            </span>
                            <span v-if="cell.item.price_cents" class="text-[11px] font-semibold text-gray-600">
                              {{ formatPrice(cell.item.price_cents) }}
                            </span>
                          </div>
                        </div>

                        <div v-else class="h-12 grid place-items-center text-[11px] text-gray-400">
                          empty
                        </div>
                      </div>
                    </div>
                  </div>
                </article>
              </div>
            </div>
          </div>
        </div>

        <div class="flex justify-end mt-3" v-if="productMappingId">
          <a
            :href="'/product-mappings/' + productMappingId + '/edit'"
            target="_blank"
            class="text-blue-800 text-sm hover:underline"
          >
            {{ productMappingName }}
          </a>
        </div>
      </template>
    </Modal>
  </Teleport>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import axios from 'axios'
import Modal from '@/Components/Modal.vue'

const props = defineProps({
  vend: Object,
  showModal: Boolean,
})

const emit = defineEmits(['modalClose'])

// A slot at or below this is worth flagging amber on the ops view — one more
// sale and the channel is out.
const LOW_STOCK_QTY = 2

const loading = ref(true)
const loadError = ref(false)
const basketLayout = ref([])
const items = ref([])
const hasStockFeed = ref(false)
const stockUpdatedAt = ref('')
const productMappingId = ref(null)
const productMappingName = ref('')

const itemsByCode = computed(() => {
  const map = {}
  for (const item of items.value) {
    if (item && item.channel_code) map[String(item.channel_code)] = item
  }
  return map
})

const boundCount = computed(() => items.value.filter(item => item && item.product_id).length)

const totalSlots = computed(() =>
  basketLayout.value.reduce((sum, basket) => sum + Math.max(1, basket.divisions), 0)
)

const totalQty = computed(() =>
  items.value.reduce((sum, item) => sum + (Number.isFinite(item?.qty) ? item.qty : 0), 0)
)

/**
 * Channel code rule, shared with the editor and the APK: `${basket}${division}`,
 * division 1-indexed within its basket.
 */
function channelCodeFor(basket, divisionIndex) {
  return `${basket.basket}${divisionIndex + 1}`
}

function cellsFor(basket) {
  const count = Math.max(1, basket.divisions)
  const cells = []
  for (let i = 0; i < count; i++) {
    const code = channelCodeFor(basket, i)
    cells.push({ code, item: itemsByCode.value[code] || null })
  }
  return cells
}

/** Total pieces sitting in a basket, or null when nothing in it reports stock. */
function basketQty(basket) {
  const known = cellsFor(basket)
    .map(cell => cell.item?.qty)
    .filter(qty => Number.isFinite(qty))

  return known.length ? known.reduce((sum, qty) => sum + qty, 0) : null
}

function qtyLabel(qty) {
  return Number.isFinite(qty) ? `×${qty}` : '—'
}

function qtyClass(qty) {
  if (!Number.isFinite(qty)) return 'bg-gray-100 text-gray-400'
  if (qty === 0) return 'bg-red-100 text-red-700'
  if (qty <= LOW_STOCK_QTY) return 'bg-amber-100 text-amber-800'
  return 'bg-green-100 text-green-700'
}

function qtyTooltip(item) {
  if (!Number.isFinite(item.qty)) return 'No stock recorded for this channel'
  if (item.capacity) return `${item.qty} of ${item.capacity} capacity`
  return `${item.qty} in stock`
}

function formatPrice(priceCents) {
  // The API ships integer cents; this is the display point where /100 happens.
  const value = Number(priceCents)
  return Number.isFinite(value) ? `S$${(value / 100).toFixed(2)}` : ''
}

function onModalClose() {
  emit('modalClose')
}

onMounted(() => {
  axios.get(`/vends/${props.vend.id}/smart-planogram`)
    .then((res) => {
      const data = res.data || {}
      basketLayout.value = (Array.isArray(data.basket_layout) ? data.basket_layout : [])
        .slice()
        .sort((a, b) => a.basket - b.basket)
      items.value = Array.isArray(data.items) ? data.items : []
      hasStockFeed.value = !!data.has_stock_feed
      stockUpdatedAt.value = data.stock_updated_at || ''
      productMappingId.value = data.product_mapping_id || null
      productMappingName.value = data.product_mapping_name || ''
    })
    .catch(() => {
      loadError.value = true
    })
    .finally(() => {
      loading.value = false
    })
})
</script>
