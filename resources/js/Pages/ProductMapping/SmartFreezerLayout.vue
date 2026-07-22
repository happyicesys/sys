<template>
  <!--
    Smart-freezer planogram editor.

    Renders the mapping as a basket grid (instead of the classic channel-row
    table used for vending machines). Each basket has a configurable number of
    divisions (1-4); channel codes are all-numeric `${basket}${division}`
    ("11", "12", "23", "41") — division is 1-indexed within the basket, matching
    the physical slot addressing on the freezer VMC.

    Binding lives on the SAME backend endpoints the vending UI uses:
      POST  /product-mappings/{id}/items/create
      DELETE /product-mappings/items/{id}
    so storage stays identical (product_mapping_items rows with alphanumeric
    channel_code) and any future ProductMappingItem consumer (sync to
    vend_channels by channel_label, AI reconciliation, ops jobs) reads one shape.

    Layout state (per-basket division count) is persisted as
    product_mappings.basket_layout_json via the parent Save button — emitting
    `layout-changed` to bubble the latest shape up, and `items-changed` after
    a successful bind/unbind so the parent reloads productMappingItems.
  -->
  <div class="space-y-5">
    <!-- Summary header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 rounded-lg bg-indigo-50 ring-1 ring-indigo-100 px-4 py-3">
      <div class="flex items-center gap-3">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-indigo-600 text-white text-xs font-semibold px-2.5 py-1">
          Smart Freezer Planogram
        </span>
        <span class="text-sm text-gray-700">
          <span class="font-semibold text-indigo-700">{{ boundCount }}</span>
          of
          <span class="font-semibold text-gray-900">{{ totalSlots }}</span>
          slots bound
        </span>
      </div>
      <div class="text-xs text-gray-500">
        Channel codes are
        <code class="bg-white px-1.5 py-0.5 rounded text-indigo-700 font-medium">basket + division</code>
        — e.g. <code class="bg-white px-1 rounded">11</code>, <code class="bg-white px-1 rounded">23</code>, <code class="bg-white px-1 rounded">41</code>.
      </div>
    </div>

    <!--
      Baskets — laid out to mirror the freezer's physical face on desktop:
      LEFT column = baskets 1 / 2 / 3 (top → bottom), RIGHT column = 4 / 5 / 6.
      Implemented as a 2-column grid with `grid-rows-3` + `grid-flow-col` so the
      browser does column-major fill — that lets us keep the source array in
      natural 1..6 order (no reshuffling of localLayout) and still get the
      pairing the user sees on the unit: 1↔4, 2↔5, 3↔6 sharing each row.

      Mobile stays single-column stacked (`grid-cols-1`, default row-flow):
      thumb-scrolling a stack reads more naturally on a narrow viewport than
      a side-by-side pair that would force horizontal scroll or cramp inputs.
    -->
    <!-- The freezer schematic: one bordered outer box holding six baskets in two
         columns (1-3 left, 4-6 right), each a bordered basket box with its
         divisions as sub-boxes in a row — mirrors the APK's on-door FreezerGrid. -->
    <div class="rounded-xl border-[3px] border-gray-800 bg-slate-50 p-3 md:p-4">
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:grid-rows-3 md:grid-flow-col md:gap-4 md:min-h-[560px]">
      <article
        v-for="basket in localLayout"
        :key="basket.basket"
        class="flex flex-col rounded-lg border-2 border-gray-400 bg-white h-full overflow-hidden"
      >
      <header class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-3">
        <div class="flex items-center gap-2">
          <span class="inline-flex items-center justify-center h-7 min-w-7 px-2 rounded-md bg-gray-900 text-white text-sm font-semibold">
            {{ basket.basket }}
          </span>
          <span class="text-sm font-medium text-gray-900">Basket {{ basket.basket }}</span>
          <span class="text-xs text-gray-500">
            ({{ cellsFor(basket).length }} slot{{ cellsFor(basket).length === 1 ? '' : 's' }})
          </span>
        </div>
        <div class="flex items-center gap-2">
          <span class="text-xs text-gray-500">Divisions</span>
          <div class="inline-flex items-center rounded-md border border-gray-200 bg-white">
            <button
              type="button"
              class="px-2.5 py-1 text-gray-600 hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed"
              @click="decDivisions(basket)"
              :disabled="basket.divisions <= MIN_DIVISIONS"
            >
              −
            </button>
            <span class="px-2 py-1 text-sm font-semibold text-gray-900 min-w-8 text-center border-x border-gray-200">
              {{ basket.divisions }}
            </span>
            <button
              type="button"
              class="px-2.5 py-1 text-gray-600 hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed"
              @click="incDivisions(basket)"
              :disabled="basket.divisions >= MAX_DIVISIONS"
            >
              +
            </button>
          </div>
        </div>
      </header>

      <div class="flex-1 p-2.5">
        <div :class="cellGridClasses(basket)">
          <div
            v-for="(cell, cellIndex) in cellsFor(basket)"
            :key="cell.code"
            class="rounded-md p-3 transition"
            :class="[cellWrapperClasses(cell), cell.item ? 'cursor-move' : '', isDragOver(basket, cellIndex) ? 'ring-2 ring-indigo-400' : '']"
            :draggable="!!cell.item"
            @dragstart="onCellDragStart(basket, cellIndex, cell, $event)"
            @dragend="onCellDragEnd"
            @dragover="onCellDragOver(basket, cellIndex, $event)"
            @drop.prevent="onCellDrop(basket, cellIndex)"
          >
            <div class="flex items-center justify-between mb-2">
              <span
                class="inline-flex items-center justify-center px-2 py-0.5 rounded text-xs font-semibold"
                :class="cell.item ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'"
              >
                {{ cell.code }}
              </span>
              <!--
                type="button" + @click.prevent is load-bearing here: the shared
                Components/Button.vue defaults `type` to "submit", and this
                cell renders inside Edit.vue's outer <form>. Without these,
                clicking submits the whole mapping (posting /update with the
                stale productMappingItems ref, no preserveScroll → "nothing
                happens, scrolls to top"). Mirrors the vending Bind button.
              -->
              <Button
                v-if="cell.item"
                type="button"
                class="bg-red-100 hover:bg-red-200 text-red-700"
                @click.prevent="onUnbind(cell)"
                :title="`Unbind ${cell.code}`"
              >
                <BackspaceIcon class="w-3.5 h-3.5" />
              </Button>
            </div>

            <!-- Bound state -->
            <div v-if="cell.item" class="flex items-center gap-3">
              <img
                v-if="cell.item.product && cell.item.product.thumbnail"
                :src="cell.item.product.thumbnail.full_url"
                class="h-14 w-14 rounded-md object-cover ring-1 ring-gray-200 flex-none pointer-events-none"
                draggable="false"
                alt=""
              />
              <div
                v-else
                class="h-14 w-14 rounded-md bg-gray-100 grid place-items-center text-gray-400 text-[10px] flex-none"
              >
                no image
              </div>
              <div class="flex flex-col min-w-0">
                <span
                  v-if="cell.item.product && cell.item.product.code"
                  class="text-[11px] font-semibold text-gray-500 truncate"
                >
                  {{ cell.item.product.code }}
                </span>
                <span class="text-sm text-gray-900 truncate" :title="cell.item.product && cell.item.product.name">
                  {{ cell.item.product && cell.item.product.name }}
                </span>
              </div>
            </div>

            <!-- Empty / picker state -->
            <div v-else>
              <MultiSelect
                v-model="selections[cell.code]"
                :options="productOptions"
                trackBy="id"
                valueProp="id"
                label="full_name"
                placeholder="Select product"
                open-direction="bottom"
                class="text-sm"
              />

              <!--
                Live preview of the picked product (before Bind). The shared
                MultiSelect doesn't expose a per-option slot, so we can't show
                thumbnails inside the dropdown without forking it; surfacing
                the thumbnail here gives the user the same visual confirmation
                without touching shared infra. Mirrors the bound-state layout
                below it so the cell visually settles into place after Bind.
              -->
              <div
                v-if="selectedProduct(cell.code)"
                class="mt-2 flex items-center gap-2 rounded-md bg-white ring-1 ring-gray-200 p-2"
              >
                <img
                  v-if="selectedProduct(cell.code).thumbnail && selectedProduct(cell.code).thumbnail.full_url"
                  :src="selectedProduct(cell.code).thumbnail.full_url"
                  class="h-12 w-12 rounded-md object-cover ring-1 ring-gray-200 flex-none"
                  alt=""
                />
                <div
                  v-else
                  class="h-12 w-12 rounded-md bg-gray-100 grid place-items-center text-gray-400 text-[10px] flex-none"
                >
                  no image
                </div>
                <div class="flex flex-col min-w-0">
                  <span
                    v-if="selectedProduct(cell.code).code"
                    class="text-[11px] font-semibold text-gray-500 truncate"
                  >
                    {{ selectedProduct(cell.code).code }}
                  </span>
                  <span class="text-sm text-gray-900 truncate" :title="selectedProduct(cell.code).name">
                    {{ selectedProduct(cell.code).name }}
                  </span>
                </div>
              </div>

              <!--
                See the unbind button above for why type="button" + .prevent is
                required: defaults inside Edit.vue's <form> would submit the
                entire mapping instead of firing /items/create.
              -->
              <Button
                type="button"
                class="mt-2 w-full bg-green-500 hover:bg-green-600 text-white justify-center"
                :class="!selections[cell.code] ? 'opacity-50 cursor-not-allowed' : ''"
                :disabled="!selections[cell.code]"
                @click.prevent="onBind(cell)"
              >
                <PlusCircleIcon class="w-3.5 h-3.5 mr-1" />
                Bind
              </Button>
            </div>
          </div>
        </div>
      </div>
      </article>
    </div>
    </div>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import { useToast } from 'vue-toastification'
import Button from '@/Components/Button.vue'
import MultiSelect from '@/Components/MultiSelect.vue'
import { BackspaceIcon, PlusCircleIcon } from '@heroicons/vue/20/solid'

const props = defineProps({
  productMappingId: { type: [Number, String], required: true },
  // Flat list of product options (same shape vending uses on Edit.vue).
  products: { type: Array, default: () => [] },
  // Currently-bound items (product_mapping_items rows, with product+thumbnail eager-loaded).
  productMappingItems: { type: Array, default: () => [] },
  // Persisted per-basket division shape; see migration 2026_06_14.
  basketLayout: { type: Array, default: () => [] },
})

const emit = defineEmits(['layout-changed', 'items-changed'])

const MAX_DIVISIONS = 4
const MIN_DIVISIONS = 1
const BASKET_COUNT = 6

const toast = useToast()

const productOptions = computed(() => props.products)

// Local mutable copy of the layout — we never mutate the prop directly.
// Falls back to a sensible default if the mapping was created before the
// basket_layout_json column was populated (defensive against null/empty).
const localLayout = ref(deriveInitialLayout(props.basketLayout))

// channel_code -> MultiSelect-bound value for the currently empty cell.
const selections = reactive({})

const itemsByCode = computed(() => {
  const map = {}
  for (const item of props.productMappingItems) {
    if (item && item.channel_code) map[item.channel_code] = item
  }
  return map
})

const boundCount = computed(() => Object.keys(itemsByCode.value).length)

const totalSlots = computed(() =>
  localLayout.value.reduce((sum, b) => sum + b.divisions, 0)
)

function deriveInitialLayout(layout) {
  if (Array.isArray(layout) && layout.length) {
    return layout.map(b => ({
      basket: Number(b.basket),
      // Clamp to 1..4. Legacy mappings stored divisions=0 (the old "single
      // slot" shape) migrate up to 1 so every basket renders at least one
      // numeric slot.
      divisions: Math.min(MAX_DIVISIONS, Math.max(MIN_DIVISIONS, Number(b.divisions ?? MIN_DIVISIONS))),
    }))
  }
  // Match the server-side seed (ProductMappingController::create) so a fresh
  // smart mapping renders the same default whether the JSON came from the DB
  // or is being derived client-side: 6 baskets × 2 divisions (slots 1 & 2).
  return Array.from({ length: BASKET_COUNT }, (_, i) => ({ basket: i + 1, divisions: 2 }))
}

// Resolve the currently-selected product for a cell into its full record so
// we can show a live thumbnail + code/name preview between the MultiSelect
// and the Bind button. The shared MultiSelect wrapper (Components/MultiSelect.vue)
// uses `:object="true"` so a selection is normally the full product object,
// but we tolerate the bare-id case too in case the wrapper is ever changed.
function selectedProduct(code) {
  const sel = selections[code]
  if (!sel) return null
  if (typeof sel === 'object') return sel
  return productOptions.value.find(p => p && p.id === sel) || null
}

// Channel code rule: all-numeric "${basket}${division}", division 1-indexed.
// e.g. basket 1 → "11","12"; basket 4 (single) → "41"; basket 6 → "61".."64".
// Matches the freezer VMC's physical slot addressing.
function channelCodeFor(basket, divisionIndex) {
  return `${basket.basket}${divisionIndex + 1}`
}

function cellsFor(basket) {
  const count = basket.divisions
  const cells = []
  for (let i = 0; i < count; i++) {
    const code = channelCodeFor(basket, i)
    cells.push({ code, item: itemsByCode.value[code] || null })
  }
  return cells
}

function cellGridClasses(basket) {
  const count = basket.divisions
  // Always keep a basket's divisions on ONE row, whatever the count — mirrors
  // the physical basket (a single horizontal strip of slots). No responsive
  // wrapping, so "11 12", "21 22 23", "61 62 63 64" each stay on one line.
  const cols = {
    1: 'grid-cols-1',
    2: 'grid-cols-2',
    3: 'grid-cols-3',
    4: 'grid-cols-4',
  }
  return ['grid gap-2.5 h-full', cols[count] || 'grid-cols-4']
}

function cellWrapperClasses(cell) {
  // Bordered division sub-boxes (schematic look), bound = solid indigo edge,
  // empty = dashed. h-full so a basket's divisions read as equal-width columns.
  return cell.item
    ? 'h-full bg-indigo-50 border-2 border-indigo-300'
    : 'h-full bg-gray-50 border-2 border-dashed border-gray-300'
}

// --- Drag-and-drop reorder (within a single basket only) --------------------
// Channel codes are positional and never move — left = smallest ("11"), right =
// largest. Dragging reorders which PRODUCT sits in each slot; the server then
// reassigns each channel code to the product now in that position. Constrained
// to the same basket: dragover only allows a drop when the target basket matches
// the drag source, so a product can never jump baskets (51 swaps with 52 only).
const dragSrc = ref(null) // { basket, index }
const dragOver = ref(null) // { basket, index } — drop-target highlight

function onCellDragStart(basket, index, cell, e) {
  if (!cell.item) {
    if (e) e.preventDefault()
    dragSrc.value = null
    return
  }
  dragSrc.value = { basket: basket.basket, index }
  // Required or the browser never starts the drag (Chrome & Firefox both need
  // dataTransfer populated in dragstart). The payload itself is unused; we read
  // dragSrc for the reorder.
  if (e && e.dataTransfer) {
    e.dataTransfer.effectAllowed = 'move'
    try { e.dataTransfer.setData('text/plain', String(cell.code)) } catch (_) { /* IE guard */ }
  }
}

function onCellDragEnd() {
  dragSrc.value = null
  dragOver.value = null
}

function onCellDragOver(basket, index, e) {
  if (dragSrc.value && dragSrc.value.basket === basket.basket) {
    e.preventDefault() // permit the drop (same basket only)
    dragOver.value = { basket: basket.basket, index }
  }
}

function isDragOver(basket, index) {
  return dragOver.value && dragOver.value.basket === basket.basket && dragOver.value.index === index
}

function onCellDrop(basket, index) {
  const src = dragSrc.value
  dragSrc.value = null
  dragOver.value = null
  if (!src || src.basket !== basket.basket || src.index === index) return

  // Products in this basket, left→right; move src → target position, rest shift.
  const ids = cellsFor(basket).map(c => (c.item ? (c.item.product?.id ?? c.item.product_id ?? null) : null))
  const [moved] = ids.splice(src.index, 1)
  ids.splice(index, 0, moved)

  router.post(`/product-mappings/${props.productMappingId}/baskets/reorder`, {
    basket: basket.basket,
    product_ids: ids,
  }, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    onSuccess: () => {
      toast.success(`Rearranged basket ${basket.basket}`, { timeout: 2000 })
      emit('items-changed')
    },
    onError: () => {
      toast.error(`Failed to rearrange basket ${basket.basket}`, { timeout: 3000 })
    },
  })
}

function incDivisions(basket) {
  if (basket.divisions >= MAX_DIVISIONS) return
  basket.divisions += 1
  emit('layout-changed', cloneLayout())
}

async function decDivisions(basket) {
  if (basket.divisions <= MIN_DIVISIONS) return

  // Stepwise decrement (4→3, 3→2, 2→1). Only the last slot is at risk on a
  // step-down, so if it's bound we confirm before dropping — that prompt
  // guards against accidental loss of a single bound product.
  const lastIndex = basket.divisions - 1
  const lastCode = channelCodeFor(basket, lastIndex)
  const lastItem = itemsByCode.value[lastCode]
  if (lastItem) {
    const name = (lastItem.product && lastItem.product.name) || 'a product'
    const ok = window.confirm(
      `Slot ${lastCode} is currently bound to "${name}". Remove the binding and shrink this basket?`
    )
    if (!ok) return
    await deleteItem(lastItem)
  }
  basket.divisions -= 1
  emit('layout-changed', cloneLayout())
}

function cloneLayout() {
  return localLayout.value.map(b => ({ basket: b.basket, divisions: b.divisions }))
}

function onBind(cell) {
  const sel = selections[cell.code]
  if (!sel) return
  // Tolerate either an id (when valueProp="id" is honoured) or a full option
  // object (the existing Edit.vue accesses .id on the same MultiSelect).
  const productId = (sel && typeof sel === 'object') ? sel.id : sel
  if (!productId) return

  router.post(`/product-mappings/${props.productMappingId}/items/create`, {
    channel_code: cell.code,
    product_id: productId,
    productMappingId: props.productMappingId,
  }, {
    // `replace: true` is the load-bearing bit here — without it, every Bind
    // pushes a fresh history entry and Inertia stops honouring preserveScroll/
    // preserveState properly when chained with the parent's re-derive, which
    // is what was producing the "needs refresh + scrolls to top" symptom on
    // earlier builds. Matches the vending bindProductMappingItem pattern.
    preserveScroll: true,
    preserveState: true,
    replace: true,
    onSuccess: () => {
      toast.success(`Bound slot ${cell.code}`, { timeout: 2500 })
      selections[cell.code] = null
      emit('items-changed')
    },
    onError: (errors) => {
      const msg = (errors && errors.channel_code) || `Failed to bind slot ${cell.code}`
      toast.error(msg, { timeout: 3500 })
    },
  })
}

function onUnbind(cell) {
  if (!cell.item) return
  deleteItem(cell.item)
}

function deleteItem(item) {
  return new Promise((resolve) => {
    router.delete(`/product-mappings/items/${item.id}`, {
      // Same rationale as onBind: replace:true keeps Inertia's history clean
      // so preserveScroll/preserveState are honoured and the parent's
      // re-derive sees the just-refreshed productMapping prop.
      preserveScroll: true,
      preserveState: true,
      replace: true,
      onSuccess: () => {
        toast.success(`Unbound ${item.channel_code}`, { timeout: 2500 })
        emit('items-changed')
        resolve(true)
      },
      onError: () => {
        toast.error(`Failed to unbind ${item.channel_code}`, { timeout: 3500 })
        resolve(false)
      },
    })
  })
}

// Re-derive the local layout when the parent's basketLayout reference changes
// (e.g. after a successful Save round-trip).
watch(() => props.basketLayout, (next) => {
  if (Array.isArray(next) && next.length) {
    localLayout.value = deriveInitialLayout(next)
  }
}, { deep: true })
</script>
