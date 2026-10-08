<template>
  <!--
    The smart-freezer door schematic, presentational only. Shared by the Ops
    Dashboard popup (Vend/SmartFreezerChannelOverview, live stock) and Machine
    Settings (Setting/Edit, the selected mapping previewed before save), so both
    draw the same picture as the APK's on-door FreezerGrid and the ProductMapping
    SmartFreezerLayout editor (same tile styling): one outer cabinet, six baskets in two columns
    — LEFT 1/2/3 top→bottom, RIGHT 4/5/6 — each basket a horizontal strip of its
    divisions.

    Column-major fill (grid-rows-3 + grid-flow-col) keeps the source array in
    natural 1..6 order while rendering the physical pairing 1↔4, 2↔5, 3↔6.
    Mobile stacks to a single column — a side-by-side pair would cramp.
  -->
  <div class="rounded-2xl bg-slate-100 p-2.5 ring-1 ring-slate-200 md:p-3">
    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 md:grid-rows-3 md:grid-flow-col">
      <article
        v-for="basket in basketLayout"
        :key="basket.basket"
        class="flex flex-col rounded-xl bg-white shadow-sm ring-1 ring-slate-200"
      >
        <header class="flex items-center gap-2 px-3 pb-2 pt-2.5">
          <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-md bg-slate-800 px-1.5 text-xs font-semibold text-white">
            {{ basket.basket }}
          </span>
          <span class="text-sm font-semibold text-slate-800">Basket {{ basket.basket }}</span>
          <span class="text-xs text-slate-400">
            {{ basket.divisions }} slot{{ basket.divisions === 1 ? '' : 's' }}
          </span>
          <span
            v-if="showQty && basketQty(basket) !== null"
            class="ml-auto text-xs font-semibold tabular-nums"
            :class="basketQty(basket) === 0 ? 'text-red-600' : 'text-slate-600'"
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
        <div class="flex-1 px-2.5 pb-2.5">
          <div class="grid h-full gap-2" :style="{ gridTemplateColumns: `repeat(${Math.max(1, basket.divisions)}, minmax(0, 1fr))` }">
            <div
              v-for="cell in cellsFor(basket)"
              :key="cell.code"
              class="flex flex-col gap-1.5 rounded-xl p-2"
              :class="cell.item
                ? 'bg-white ring-1 ring-slate-200'
                : 'border border-dashed border-slate-300 bg-slate-50/70'"
            >
              <template v-if="cell.item">
                <!-- 3+ slots in a basket leave too little width beside the
                     thumbnail (the Ops Dashboard popup is ~900px), so the name
                     drops under it there. -->
                <div :class="isStacked(basket) ? 'flex flex-col gap-1.5' : 'flex items-start gap-2.5'">
                  <!-- Thumbnail with the slot code pinned to its corner. -->
                  <div class="relative flex-none">
                    <div class="h-12 w-12 overflow-hidden rounded-xl bg-slate-100 ring-1 ring-black/5">
                      <img
                        v-if="cell.item.thumbnail"
                        :src="cell.item.thumbnail"
                        class="h-full w-full object-cover"
                        loading="lazy"
                        alt=""
                      />
                      <div v-else class="grid h-full w-full place-items-center">
                        <PhotoIcon class="h-5 w-5 text-slate-300" />
                      </div>
                    </div>
                    <span class="absolute -left-1.5 -top-1.5 rounded-md bg-indigo-600 px-1.5 py-px font-mono text-[10px] font-semibold leading-4 text-white shadow-sm ring-2 ring-white">
                      {{ cell.code }}
                    </span>
                  </div>
                  <div class="min-w-0 flex-1" :class="isStacked(basket) ? '' : 'pt-0.5'">
                    <div v-if="cell.item.product_code" class="truncate text-[11px] font-medium leading-4 text-slate-400">
                      {{ cell.item.product_code }}
                    </div>
                    <div class="line-clamp-2 text-xs font-medium leading-4 text-slate-800" :title="cell.item.product_name">
                      {{ cell.item.product_name }}
                    </div>
                  </div>
                </div>

                <div
                  v-if="cell.item.price_cents != null || showQty"
                  class="mt-auto flex flex-wrap items-center justify-between gap-1"
                >
                  <span class="text-[11px] font-semibold tabular-nums text-slate-600">
                    {{ cell.item.price_cents != null ? formatPrice(cell.item.price_cents) : '' }}
                  </span>
                  <!-- Setting/Edit fills this slot with the overwrite control (StockQtyInline). -->
                  <slot v-if="showQty" name="qty" :item="cell.item">
                    <span
                      class="inline-flex items-center rounded-md px-1.5 py-0.5 text-[11px] font-bold tabular-nums"
                      :class="qtyClass(cell.item.qty)"
                      v-tooltip="qtyTooltip(cell.item)"
                    >
                      {{ qtyLabel(cell.item.qty) }}
                    </span>
                  </slot>
                </div>

                <slot name="cell-footer" :item="cell.item" />
              </template>

              <div v-else class="flex items-center gap-1.5">
                <span class="rounded-md bg-slate-200 px-1.5 py-px font-mono text-[10px] font-semibold leading-4 text-slate-600">
                  {{ cell.code }}
                </span>
                <span class="text-[11px] text-slate-400">Empty</span>
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
import { PhotoIcon } from '@heroicons/vue/20/solid'

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

function isStacked(basket) {
  return basket.divisions >= 3
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
  if (!Number.isFinite(qty)) return 'bg-slate-100 text-slate-400'
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
