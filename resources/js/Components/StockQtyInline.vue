<template>
  <!--
    One SKU's on-hand qty with its hand overwrite (VendStockQtyController → ChannelQtyAdjuster):
    "3 / 6  Adjust", then an input in place. Used by the chiller's Stock Qty table and, `compact`,
    as the qty badge inside a freezer planogram cell. Emits `saved` once the server has answered,
    either way, so the parent reloads the rows.
  -->
  <div v-if="editing" class="flex items-center justify-center gap-1">
    <input
      ref="input"
      type="number" min="0" :max="maxQty" step="1"
      v-model="draft"
      class="rounded border-gray-300 py-0.5 text-sm text-center"
      :class="compact ? 'w-14' : 'w-20'"
      @keyup.enter="save"
      @keyup.esc="cancel"
    />
    <button type="button" class="rounded bg-green-500 hover:bg-green-600 px-2 py-1 text-xs text-white disabled:opacity-50" :disabled="saving" @click="save">
      {{ saving ? '…' : 'Save' }}
    </button>
    <button type="button" class="rounded bg-gray-200 hover:bg-gray-300 px-2 py-1 text-xs text-gray-700" :disabled="saving" @click="cancel">✕</button>
  </div>
  <div v-else class="inline-flex items-center gap-1.5">
    <span
      v-if="compact"
      class="inline-flex items-center whitespace-nowrap rounded px-1.5 py-0.5 text-xs font-bold"
      :class="badgeClass"
    >
      {{ channel.qty }}<span v-if="channel.capacity" class="font-normal opacity-70">&nbsp;/ {{ channel.capacity }}</span>
    </span>
    <span v-else>
      <span class="font-semibold">{{ channel.qty }}</span>
      <span v-if="channel.capacity" class="text-gray-500"> / {{ channel.capacity }}</span>
    </span>
    <button
      v-if="canEdit"
      type="button"
      class="text-xs text-blue-600 hover:underline"
      @click="edit"
    >Adjust</button>
  </div>
</template>

<script setup>
import { computed, nextTick, ref } from 'vue';
import axios from 'axios';
import { useToast } from 'vue-toastification';

const props = defineProps({
  vendId: { type: [Number, String], required: true },
  // A VendStockQtyController row: { id, code, label, product, qty, capacity, history }.
  channel: { type: Object, required: true },
  canEdit: { type: Boolean, default: false },
  // A chiller's overwrite is pushed to CityBox, so the confirm says so.
  isChiller: { type: Boolean, default: false },
  compact: { type: Boolean, default: false },
});
const emit = defineEmits(['saved']);

const toast = useToast();
const maxQty = 999;
// A slot at or below this is worth flagging amber — one more sale and it is out
// (same threshold as SmartFreezerPlanogramGrid).
const LOW_STOCK_QTY = 2;

const editing = ref(false);
const saving = ref(false);
const draft = ref('');
const input = ref(null);

const badgeClass = computed(() => {
  const qty = props.channel.qty;
  if (qty === 0) return 'bg-red-100 text-red-700';
  if (qty <= LOW_STOCK_QTY) return 'bg-amber-100 text-amber-800';
  return 'bg-green-100 text-green-700';
});

function edit() {
  editing.value = true;
  draft.value = String(props.channel.qty);
  nextTick(() => input.value?.select());
}

function cancel() {
  editing.value = false;
  draft.value = '';
}

function save() {
  const channel = props.channel;
  const qty = Number(draft.value);
  if (draft.value === '' || !Number.isInteger(qty) || qty < 0 || qty > maxQty) {
    toast.error('Qty must be a whole number from 0 to ' + maxQty, { timeout: 4000 });
    return;
  }
  if (qty === channel.qty) {
    cancel();
    return;
  }
  const label = channel.label + (channel.product ? ' ' + channel.product.name : '');
  const overCapacity = channel.capacity && qty > channel.capacity
    ? '\n\nThat is above its capacity of ' + channel.capacity + '.'
    : '';
  if (!confirm('Set ' + label + ' from ' + channel.qty + ' to ' + qty + '?' + overCapacity + (props.isChiller ? '\n\nThis is sent to CityBox now.' : ''))) {
    return;
  }

  saving.value = true;
  axios.post('/vends/' + props.vendId + '/stock-qty/' + channel.id, { qty, expected_qty: channel.qty })
    .then((res) => {
      const entry = res.data.adjustment;
      if (entry && entry.supplier_to != null && entry.supplier_to !== entry.to) {
        toast.warning('Sent ' + entry.to + ', but CityBox reads back ' + entry.supplier_to, { timeout: 8000 });
      } else {
        toast.success('Qty updated', { timeout: 3000 });
      }
      cancel();
    })
    .catch((err) => {
      toast.error(err?.response?.data?.message || 'Qty update failed', { timeout: 8000 });
    })
    .finally(() => {
      saving.value = false;
      emit('saved');
    });
}
</script>
