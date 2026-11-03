<template>
  <div class="text-sm">
    <div v-if="loading" class="py-3 text-gray-500">{{ $t('Loading…') }}</div>
    <template v-else-if="editor">
      <p class="text-xs text-gray-600">
        {{ $t('Order ID') }}: <span class="font-mono">{{ editor.order_id }}</span>.
        {{ $t('Check the videos, then set how many of each product really left the cabinet.') }}
      </p>
      <p v-if="editor.blocked_reason" class="mt-3 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900">{{ editor.blocked_reason }}</p>
      <p v-if="message" class="mt-3 rounded-md border border-red-300 bg-red-50 px-3 py-2 text-xs text-red-800">{{ message }}</p>

      <table class="mt-3 min-w-full">
        <thead class="text-xs text-gray-600 font-semibold">
          <tr>
            <th class="text-left py-1">{{ $t('Product') }}</th>
            <th class="text-center px-2">{{ $t('Paid') }}</th>
            <th class="text-center px-2">{{ $t('AI saw') }}</th>
            <th class="text-center px-2">{{ $t('Taken') }}</th>
            <th class="text-right px-2">{{ $t('Price') }}</th>
            <th class="text-right">{{ $t('Amount') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="line in shown" :key="line.product_id" class="border-t" :class="line.taken !== line.paid ? 'bg-amber-50' : ''">
            <td class="py-1.5 pr-2">{{ line.name }}<span v-if="line.code" class="block text-xs text-gray-500">{{ line.code }}</span></td>
            <td class="text-center px-2">{{ line.paid }}</td>
            <td class="text-center px-2">{{ line.ai }}</td>
            <td class="px-2">
              <div class="flex items-center justify-center gap-1">
                <button type="button" class="h-7 w-7 rounded border border-gray-300 bg-white font-semibold hover:bg-gray-50 disabled:opacity-40" :disabled="frozen || line.taken <= 0" :aria-label="$t('Less')" @click="line.taken--">−</button>
                <span class="w-6 text-center font-semibold">{{ line.taken }}</span>
                <button type="button" class="h-7 w-7 rounded border border-gray-300 bg-white font-semibold hover:bg-gray-50 disabled:opacity-40" :disabled="frozen || line.unit_cents === null || line.taken >= editor.max_units" :aria-label="$t('More')" @click="line.taken++">+</button>
              </div>
            </td>
            <td class="text-right px-2 whitespace-nowrap">
              <span v-if="line.unit_cents !== null">{{ money(line.unit_cents) }}</span>
              <span v-else class="text-xs text-red-700">{{ $t('No price') }}</span>
            </td>
            <td class="text-right whitespace-nowrap">{{ money((line.unit_cents || 0) * line.taken) }}</td>
          </tr>
          <tr class="border-t font-semibold">
            <td colspan="5" class="py-2 text-right pr-2">{{ $t('This count comes to') }}</td>
            <td class="text-right whitespace-nowrap">{{ money(total) }}</td>
          </tr>
        </tbody>
      </table>

      <div v-if="others.length && !frozen" class="mt-2 flex items-center gap-2">
        <select v-model="adding" class="rounded-md border-gray-300 py-1 pl-2 pr-7 text-xs" :aria-label="$t('Add another product')">
          <option value="">{{ $t('Add another product') }}</option>
          <option v-for="line in others" :key="line.product_id" :value="line.product_id">{{ line.name }}</option>
        </select>
        <button type="button" class="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs hover:bg-gray-50 disabled:opacity-40" :disabled="!adding" @click="add">{{ $t('Add') }}</button>
      </div>

      <template v-if="!editor.blocked_reason">
        <p class="mt-3 rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 text-xs text-indigo-900">{{ outcome }}</p>

        <div v-if="!confirming" class="mt-3 flex items-center justify-end gap-2">
          <button v-if="editor.mode === 'hold'" type="button" class="mr-auto text-xs text-blue-600 hover:underline" @click="$emit('key-in')">{{ $t('Key in an amount instead') }}</button>
          <button type="button" class="rounded-md border border-indigo-600 bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700" @click="confirming = true">{{ $t('Next') }}</button>
        </div>
        <div v-else class="mt-3 rounded-md border border-amber-300 bg-amber-50 px-3 py-3">
          <p class="font-semibold text-amber-900">{{ confirmText }}</p>
          <div class="mt-2 flex justify-end gap-2">
            <button type="button" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs hover:bg-gray-50" :disabled="sending" @click="confirming = false">{{ $t('Back') }}</button>
            <button type="button" class="rounded-md bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-700 disabled:opacity-50" :disabled="sending" @click="save">{{ sending ? $t('Sending…') : $t('Confirm') }}</button>
          </div>
        </div>
      </template>
    </template>
    <p v-else-if="message" class="py-2 text-xs text-red-700">{{ message }}</p>
  </div>
</template>

<script setup>
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';
import { t, tk } from '@/i18n';

const props = defineProps({
  recognitionId: { type: Number, required: true },
});
const emit = defineEmits(['saved', 'key-in']);

const editor = ref(null);
const lines = ref([]);
const added = ref([]);
const adding = ref('');
const loading = ref(true);
const sending = ref(false);
const confirming = ref(false);
const message = ref('');

const url = () => `/ai-recognition/${props.recognitionId}/correction`;
const money = (cents) => '$' + (Number(cents || 0) / 100).toFixed(2);
const frozen = computed(() => !!editor.value?.blocked_reason);

// The machine's other products stay out of the way until a person adds one.
const inPlay = (line) => line.paid > 0 || line.ai > 0 || line.taken > 0 || added.value.includes(line.product_id);
const shown = computed(() => lines.value.filter(inPlay));
const others = computed(() => lines.value.filter((line) => !inPlay(line) && line.unit_cents !== null));
const total = computed(() => lines.value.reduce((sum, line) => sum + (line.unit_cents || 0) * line.taken, 0));
const collected = computed(() => editor.value?.collected_cents ?? 0);

const outcome = computed(() => {
  if (editor.value.mode === 'hold') {
    return total.value === 0
      ? t(tk('Nothing is charged: the hold is released. Sale items and stock follow this count.'))
      : t(tk('Charges :amount to the card on the T05 now. Sale items and stock follow this count.'), { amount: money(total.value) });
  }
  if (editor.value.mode === 'paid') {
    return t(tk('Sale items and stock follow this count. The money is not touched: a refund goes through Refund Request.'));
  }
  if (total.value < collected.value) {
    return t(tk('Sale items and stock follow this count now. :collected was collected, so :difference should go back: Refund opens next, and nothing is sent until you press it.'), { collected: money(collected.value), difference: money(collected.value - total.value) });
  }
  if (total.value > collected.value) {
    return t(tk('Sale items and stock follow this count now. Only :collected was collected, so :difference is recorded as owed: a T05 charge cannot be topped up.'), { collected: money(collected.value), difference: money(total.value - collected.value) });
  }
  return t(tk('Sale items and stock follow this count. No money moves.'));
});

const confirmText = computed(() => {
  if (editor.value.mode !== 'hold') return t(tk('Save this count?'));
  return total.value === 0
    ? t(tk('Release the hold of :amount?'), { amount: money(editor.value.hold_cents) })
    : t(tk('Charge :amount to the customer\'s card?'), { amount: money(total.value) });
});

function load(data) {
  editor.value = data;
  lines.value = data.lines.map((line) => ({ ...line }));
  added.value = [];
}

onMounted(async () => {
  try {
    load((await axios.get(url())).data.editor);
  } catch (e) {
    message.value = e.response?.data?.message || t(tk('Could not load this sale.'));
  } finally {
    loading.value = false;
  }
});

function add() {
  const id = Number(adding.value);
  added.value.push(id);
  const line = lines.value.find((l) => l.product_id === id);
  if (line) line.taken = 1;
  adding.value = '';
}

async function save() {
  sending.value = true;
  message.value = '';
  const taken = {};
  lines.value.forEach((line) => { taken[line.product_id] = line.taken; });
  try {
    const { data } = await axios.post(url(), { taken, amount_cents: total.value });
    emit('saved', data);
  } catch (e) {
    if (e.response?.data?.editor) load(e.response.data.editor);
    message.value = e.response?.data?.message || t(tk('The request did not complete. Reload the page and check this sale before trying again.'));
  } finally {
    sending.value = false;
    confirming.value = false;
  }
}
</script>
