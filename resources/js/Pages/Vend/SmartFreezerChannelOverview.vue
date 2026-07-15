<template>
  <!--
    Smart-freezer channel overview (read-only 2D planogram viewer).

    The vending ChannelOverview reads vend_channels telemetry; a smart freezer
    never reports that, so this view is driven by the product mapping instead
    ("reverse" management — the planogram is the source of truth). We fetch
    /vends/{id}/smart-planogram and draw the six-basket face: baskets 1-3 in the
    left column, 4-6 in the right, each basket a single horizontal strip of its
    division slots. Each slot shows the product thumbnail, name and qty.
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

          <div v-else class="space-y-4">
            <div class="flex items-center justify-between text-xs text-gray-500 px-1">
              <span>
                <span class="font-semibold text-indigo-700">{{ boundCount }}</span>
                of
                <span class="font-semibold text-gray-900">{{ totalSlots }}</span>
                slots filled
              </span>
              <span v-if="productMappingName">{{ productMappingName }}</span>
            </div>

            <!--
              Column-major fill (grid-rows-3 + grid-flow-col) so baskets stay in
              natural 1..6 order but render as LEFT 1/2/3, RIGHT 4/5/6 — matching
              the physical freezer face. Single column on mobile.
            -->
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2 md:grid-rows-3 md:grid-flow-col md:gap-5">
              <article
                v-for="basket in basketLayout"
                :key="basket.basket"
                class="rounded-lg border border-gray-200 bg-white shadow-sm"
              >
                <header class="flex items-center gap-2 border-b border-gray-100 px-3 py-2">
                  <span class="inline-flex items-center justify-center h-6 min-w-6 px-2 rounded-md bg-gray-900 text-white text-xs font-semibold">
                    {{ basket.basket }}
                  </span>
                  <span class="text-sm font-medium text-gray-900">Basket {{ basket.basket }}</span>
                  <span class="text-xs text-gray-500">
                    ({{ cellsFor(basket).length }} slot{{ cellsFor(basket).length === 1 ? '' : 's' }})
                  </span>
                </header>

                <div class="p-3">
                  <div :class="cellGridClasses(basket)">
                    <div
                      v-for="cell in cellsFor(basket)"
                      :key="cell.code"
                      class="rounded-md p-2 transition"
                      :class="cell.item
                        ? 'bg-indigo-50/60 ring-1 ring-indigo-100'
                        : 'bg-gray-50 ring-1 ring-gray-200 border border-dashed border-gray-300'"
                    >
                      <div class="flex items-center justify-between mb-1.5">
                        <span
                          class="inline-flex items-center justify-center px-1.5 py-0.5 rounded text-xs font-semibold"
                          :class="cell.item ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700'"
                        >
                          {{ cell.code }}
                        </span>
                        <span
                          v-if="cell.item"
                          class="text-xs font-semibold"
                          :class="cell.item.qty === 0 ? 'text-red-500' : 'text-gray-700'"
                        >
                          {{ cell.item.qty === null || cell.item.qty === undefined ? '—' : ('×' + cell.item.qty) }}
                        </span>
                      </div>

                      <div v-if="cell.item" class="flex items-center gap-2">
                        <img
                          v-if="cell.item.thumbnail"
                          :src="cell.item.thumbnail"
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
                          <span v-if="cell.item.product_code" class="text-[11px] font-semibold text-gray-500 truncate">
                            {{ cell.item.product_code }}
                          </span>
                          <span class="text-xs text-gray-900 truncate" :title="cell.item.product_name">
                            {{ cell.item.product_name }}
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

const MAX_DIVISIONS = 4

const loading = ref(true)
const loadError = ref(false)
const basketLayout = ref([])
const items = ref([])
const productMappingId = ref(null)
const productMappingName = ref('')

const itemsByCode = computed(() => {
  const map = {}
  for (const it of items.value) {
    if (it && it.channel_code) map[String(it.channel_code)] = it
  }
  return map
})

const boundCount = computed(() => items.value.filter(i => i && i.product_id).length)

const totalSlots = computed(() =>
  basketLayout.value.reduce((sum, b) => sum + Math.max(1, b.divisions), 0)
)

// Numeric channel code rule, mirrors the editor: "${basket}${division}", 1-indexed.
function channelCodeFor(basket, divisionIndex) {
  return `${basket.basket}${divisionIndex + 1}`
}

function cellsFor(basket) {
  const count = Math.max(1, Math.min(MAX_DIVISIONS, basket.divisions))
  const cells = []
  for (let i = 0; i < count; i++) {
    const code = channelCodeFor(basket, i)
    cells.push({ code, item: itemsByCode.value[code] || null })
  }
  return cells
}

function cellGridClasses(basket) {
  const count = Math.max(1, Math.min(MAX_DIVISIONS, basket.divisions))
  // Always one row per basket, whatever the slot count — matches the physical strip.
  const cols = { 1: 'grid-cols-1', 2: 'grid-cols-2', 3: 'grid-cols-3', 4: 'grid-cols-4' }
  return ['grid gap-2', cols[count] || 'grid-cols-4']
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
