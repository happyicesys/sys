<template>
  <!--
    Smart-freezer channel overview — read-only 2D planogram viewer.

    The vending ChannelOverview reads vend_channels telemetry; a smart freezer
    never reports that, so this view is driven by the product mapping instead
    ("reverse" management — the planogram is the source of truth).

    The door schematic itself is Components/SmartFreezerPlanogramGrid, shared
    with Machine Settings (Setting/Edit) so both draw the same six-basket picture
    as the APK's on-door FreezerGrid and the ProductMapping SmartFreezerLayout.
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

            <!-- The freezer schematic (shared with Machine Settings). -->
            <SmartFreezerPlanogramGrid
              :basket-layout="basketLayout"
              :items="items"
              :show-qty="hasStockFeed"
            />
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
import SmartFreezerPlanogramGrid from '@/Components/SmartFreezerPlanogramGrid.vue'

const props = defineProps({
  vend: Object,
  showModal: Boolean,
})

const emit = defineEmits(['modalClose'])

/**
 * Operation Dashboard rows are keyed by CUSTOMER id and carry the machine's own id as `vend_id`
 * (the query aliases vends.id AS vend_id). Reading `id` there fetched another row's planogram —
 * on 50001 it answered "No products mapped to this freezer yet" against a full 10-slot mapping
 * (2026-09-16). SmartChillerChannelOverview resolves it the same way.
 */
const vendId = computed(() => props.vend?.vend_id ?? props.vend?.id)

const loading = ref(true)
const loadError = ref(false)
const basketLayout = ref([])
const items = ref([])
const hasStockFeed = ref(false)
const stockUpdatedAt = ref('')
const productMappingId = ref(null)
const productMappingName = ref('')

const boundCount = computed(() => items.value.filter(item => item && item.product_id).length)

const totalSlots = computed(() =>
  basketLayout.value.reduce((sum, basket) => sum + Math.max(1, basket.divisions), 0)
)

const totalQty = computed(() =>
  items.value.reduce((sum, item) => sum + (Number.isFinite(item?.qty) ? item.qty : 0), 0)
)

function onModalClose() {
  emit('modalClose')
}

onMounted(() => {
  axios.get(`/vends/${vendId.value}/smart-planogram`)
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
