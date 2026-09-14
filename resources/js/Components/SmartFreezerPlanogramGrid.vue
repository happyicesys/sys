<template>
  <!--
    The smart-freezer door schematic, presentational only. Shared by the Ops
    Dashboard popup (Vend/SmartFreezerChannelOverview, live stock) and Machine
    Settings (Setting/Edit, the selected mapping previewed before save), so both
    draw the same picture as the APK's on-door FreezerGrid and the ProductMapping
    SmartFreezerLayout editor: one bordered outer box, six baskets in two columns
    — LEFT 1/2/3 top→bottom, RIGHT 4/5/6 — each basket a horizontal strip of its
    divisions.

    Column-major fill (grid-rows-3 + grid-flow-col) keeps the source array in
    natural 1..6 order while rendering the physical pairing 1↔4, 2↔5, 3↔6.
    Mobile stacks to a single column — a side-by-side pair would cramp.
  -->
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
            v-if="showQty && basketQty(basket) !== null"
            class="ml-auto text-xs font-semibold"
            :class="basketQty(basket) === 0 ? 'text-red-600' : 'text-gray-700'"
          >
            {{ basketQty(basket) }} pcs
          </span>
        </header>

        <!--
          Divisions laid out left→right across the full basket width, so a
          one-division basket (e.g. channel 41) fills its basket with no inner
          dividers — exactly as the door is built. Inline grid-template rather
          than a Tailwind class map: the division count comes from data and must
          never silently clamp a slot out of view.
        -->
        <div class="flex-1 p-2">
          <div class="grid gap-2" :style="{ gridTemplateColumns: `repeat(${Math.max(1, basket.divisions)}, minmax(0, 1fr))` }">
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
                  v-if="showQty && cell.item"
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
                  class="h-12 w-12 rounded-md object-contain bg-white p-0.5 ring-1 ring-gray-200 flex-none"
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
                  <span v-if="cell.item.price_cents != null" class="text-[11px] font-semibold text-gray-600">
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
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  // [{ basket: 1..6, divisions: n }], sorted by basket.
  basketLayout: { type: Array, default: () => [] },
  // [{ channel_code, product_code, product_name, thumbnail, price_cents, qty, capacity }]
  items: { type: Array, default: () => [] },
  // Per-slot / per-basket qty badges. Off where no stock is known (a mapping preview).
  showQty: { type: Boolean, default: false },
  // Integer cents → display string; the one place /100 happens for this grid.
  formatPrice: {
    type: Function,
    default: (cents) => {
      const value = Number(cents)
      return Number.isFinite(value) ? `S$${(value / 100).toFixed(2)}` : ''
    },
  },
})

// A slot at or below this is worth flagging amber — one more sale and it is out.
const LOW_STOCK_QTY = 2

const itemsByCode = computed(() => {
  const map = {}
  for (const item of props.items) {
    if (item && item.channel_code) map[String(item.channel_code)] = item
  }
  return map
})

/**
 * Channel code rule, shared with the editor and the APK: `${basket}${division}`,
 * division 1-indexed within its basket.
 */
function cellsFor(basket) {
  const count = Math.max(1, basket.divisions)
  const cells = []
  for (let i = 0; i < count; i++) {
    const code = `${basket.basket}${i + 1}`
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
</script>
