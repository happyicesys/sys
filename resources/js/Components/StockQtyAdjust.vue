<template>
  <!--
    Setting/Edit "Stock Qty" for a Smart Chiller / Smart Freezer: the on-hand qty per SKU,
    overwritable by hand when the count inside does not match (VendStockQtyController →
    ChannelQtyAdjuster). Each row shows who last overwrote it: "brian · 260930 11:03 pm (3 → 7)".
    Reads the SAVED machine, not the mapping picked in the dropdown above.
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
              <template v-if="editingId === channel.id">
                <div class="flex items-center justify-center gap-1">
                  <input
                    type="number" min="0" :max="maxQty" step="1"
                    v-model="draft"
                    class="w-20 rounded border-gray-300 py-1 text-sm text-center"
                    @keyup.enter="save(channel)"
                    @keyup.esc="cancel"
                  />
                  <button type="button" class="rounded bg-green-500 hover:bg-green-600 px-2 py-1 text-xs text-white disabled:opacity-50" :disabled="saving" @click="save(channel)">
                    {{ saving ? 'Saving…' : 'Save' }}
                  </button>
                  <button type="button" class="rounded bg-gray-200 hover:bg-gray-300 px-2 py-1 text-xs text-gray-700" :disabled="saving" @click="cancel">Cancel</button>
                </div>
              </template>
              <template v-else>
                <span class="font-semibold">{{ channel.qty }}</span>
                <span v-if="channel.capacity" class="text-gray-500"> / {{ channel.capacity }}</span>
                <button
                  v-if="canEdit && !refusal"
                  type="button"
                  class="ml-2 text-xs text-blue-600 hover:underline"
                  @click="edit(channel)"
                >Adjust</button>
              </template>
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
import axios from 'axios';
import moment from 'moment';
import { useToast } from 'vue-toastification';

const props = defineProps({
  vendId: { type: [Number, String], required: true },
  canEdit: { type: Boolean, default: false },
});

const toast = useToast();
const maxQty = 999;

const channels = ref([]);
const refusal = ref(null);
const isChiller = ref(false);
const loading = ref(false);
const saving = ref(false);
const editingId = ref(null);
const draft = ref('');
const expanded = ref({});

function load() {
  loading.value = true;
  return axios.get('/vends/' + props.vendId + '/stock-qty')
    .then((res) => {
      channels.value = res.data.channels || [];
      refusal.value = res.data.refusal;
      isChiller.value = !!res.data.is_chiller;
    })
    .catch(() => toast.error('Could not load stock qty', { timeout: 4000 }))
    .finally(() => { loading.value = false });
}

function edit(channel) {
  editingId.value = channel.id;
  draft.value = String(channel.qty);
}

function cancel() {
  editingId.value = null;
  draft.value = '';
}

function save(channel) {
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
  if (!confirm('Set ' + label + ' from ' + channel.qty + ' to ' + qty + '?' + overCapacity + (isChiller.value ? '\n\nThis is sent to CityBox now.' : ''))) {
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
      load();
    });
}

function toggle(id) {
  expanded.value = { ...expanded.value, [id]: !expanded.value[id] };
}

function line(entry) {
  const at = entry.at ? moment(entry.at).format('YYMMDD hh:mm a') : '';
  return (entry.who || 'unknown') + ' · ' + at + ' (' + entry.from + ' → ' + entry.to + ')';
}

onMounted(load);
</script>
