<template>
  <!--
    Setting/Edit "Stock Qty" for a Smart Chiller: the on-hand qty per SKU,
    overwritable by hand when the count inside does not match (VendStockQtyController →
    ChannelQtyAdjuster). Each row shows who last overwrote it: "brian · 260930 11:03 pm (3 → 7)".
    Reads the SAVED machine, not the mapping picked in the dropdown above. A Smart Freezer shows
    the same rows inside its planogram cells instead (Brian, 2026-10-03), so it has no table.
  -->
  <div class="sm:col-span-6">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
      <span class="text-sm font-medium text-gray-700">
        Stock Qty
        <span class="text-xs font-normal text-gray-500">
          · on hand now<template v-if="isChiller"> (CityBox's count, refreshed every minute)</template>
        </span>
      </span>
      <button type="button" class="text-xs text-blue-600 hover:underline" @click="load" :disabled="loading">
        {{ loading ? 'Loading…' : 'Refresh' }}
      </button>
    </div>
    <p v-if="refusal" class="mb-2 text-xs text-amber-700">{{ refusal }}</p>
    <p v-else-if="isChiller && canEdit" class="mb-2 text-xs text-gray-500">
      A change is sent to CityBox straight away (on the chiller's last door-open session) and read back.
    </p>

    <div class="overflow-x-auto shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
      <table class="min-w-full divide-y divide-gray-300">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-3 py-2 text-center text-sm font-semibold text-gray-900">Channel</th>
            <th class="px-3 py-2 text-left text-sm font-semibold text-gray-900">Product</th>
            <th class="px-3 py-2 text-center text-sm font-semibold text-gray-900">Qty</th>
            <th class="px-3 py-2 text-left text-sm font-semibold text-gray-900">Last changed by hand</th>
          </tr>
        </thead>
        <tbody class="bg-white">
          <tr v-for="(channel, index) in channels" :key="channel.id" :class="index % 2 === 0 ? undefined : 'bg-gray-50'">
            <td class="whitespace-nowrap px-3 py-2 text-sm font-semibold text-gray-900 text-center">{{ channel.label }}</td>
            <td class="px-3 py-2 text-sm text-gray-900">
              <span v-if="channel.product">{{ channel.product.code }} - {{ channel.product.name }}</span>
            </td>
            <td class="whitespace-nowrap px-3 py-2 text-sm text-gray-900 text-center">
              <StockQtyInline
                :vend-id="vendId"
                :channel="channel"
                :can-edit="canEdit && !refusal"
                :is-chiller="isChiller"
                @saved="load"
              />
            </td>
            <td class="px-3 py-2 text-xs text-blue-600 italic">
              <template v-if="channel.history.length">
                <div>{{ line(channel.history[0]) }}</div>
                <template v-if="channel.history.length > 1">
                  <button type="button" class="not-italic text-gray-500 hover:underline" @click="toggle(channel.id)">
                    {{ expanded[channel.id] ? 'hide' : 'earlier (' + (channel.history.length - 1) + ')' }}
                  </button>
                  <div v-if="expanded[channel.id]" class="text-gray-500">
                    <div v-for="(entry, i) in channel.history.slice(1)" :key="i">{{ line(entry) }}</div>
                  </div>
                </template>
              </template>
            </td>
          </tr>
          <tr v-if="!loading && !channels.length">
            <td colspan="4" class="py-4 text-sm text-gray-600 text-center">No products on this machine yet.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import StockQtyInline from '@/Components/StockQtyInline.vue';
import { stockQtyLine as line, useStockQty } from '@/composables/useStockQty';

const props = defineProps({
  vendId: { type: [Number, String], required: true },
  canEdit: { type: Boolean, default: false },
});

const { channels, refusal, isChiller, loading, load } = useStockQty(() => props.vendId);
const expanded = ref({});

function toggle(id) {
  expanded.value = { ...expanded.value, [id]: !expanded.value[id] };
}

onMounted(load);
</script>
