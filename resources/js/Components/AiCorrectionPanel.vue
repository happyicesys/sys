<template>
  <div class="text-left text-sm">
    <div v-if="loading" class="py-3 text-gray-500">{{ $t('Loading…') }}</div>
    <template v-else-if="editor">
      <p class="text-sm text-gray-600">
        {{ $t('Order ID') }}: <span class="font-mono font-medium text-gray-900">{{ editor.order_id }}</span>.
        {{ $t('Check the videos, then set how many of each product really left the cabinet.') }}
      </p>
      <p v-if="editor.blocked_reason" class="mt-3 rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">{{ editor.blocked_reason }}</p>
      <p v-if="message" class="mt-3 rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800">{{ message }}</p>

      <div class="mt-4 overflow-x-auto rounded-xl border border-gray-200">
        <table class="min-w-full">
          <thead class="bg-gray-50 text-xs font-semibold text-gray-500">
            <tr>
              <th class="px-3 py-2 text-left">{{ $t('Product') }}</th>
              <th class="px-2 py-2 text-center">{{ $t('Paid') }}</th>
              <th class="px-2 py-2 text-center whitespace-nowrap">{{ $t('AI saw') }}</th>
              <th class="px-2 py-2 text-center">{{ $t('Taken') }}</th>
              <th class="px-2 py-2 text-right">{{ $t('Price') }}</th>
              <th class="px-3 py-2 text-right">{{ $t('Amount') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="line in shown" :key="line.product_id" :class="line.taken !== line.paid ? 'bg-amber-50' : 'bg-white'">
              <td class="px-3 py-2.5">
                <div class="flex min-w-[12rem] items-center gap-3">
                  <img v-if="line.photo" :src="line.photo" :alt="line.name" loading="lazy" class="h-14 w-14 flex-none rounded-lg border border-gray-200 bg-white object-contain p-0.5" />
                  <span v-else class="flex h-14 w-14 flex-none items-center justify-center rounded-lg border border-gray-200 bg-gray-50 text-gray-300"><PhotoIcon class="h-6 w-6" aria-hidden="true" /></span>
                  <span class="min-w-0">
                    <span class="block font-semibold text-gray-900">{{ line.name }}</span>
                    <span v-if="line.code" class="block font-mono text-xs text-gray-500">{{ line.code }}</span>
                  </span>
                </div>
              </td>
              <td class="px-2 text-center text-gray-700">{{ line.paid }}</td>
              <td class="px-2 text-center text-gray-700">{{ line.ai }}</td>
              <td class="px-2">
                <div class="mx-auto flex w-max items-center overflow-hidden rounded-lg border border-gray-300 bg-white shadow-sm">
                  <button type="button" class="h-8 w-8 text-base font-semibold text-gray-700 hover:bg-gray-100 disabled:opacity-30" :disabled="frozen || line.taken <= 0" :aria-label="$t('Less')" @click="line.taken--">−</button>
                  <span class="w-9 border-x border-gray-200 text-center font-bold leading-8" :class="line.taken !== line.paid ? 'text-amber-700' : 'text-gray-900'">{{ line.taken }}</span>
                  <button type="button" class="h-8 w-8 text-base font-semibold text-gray-700 hover:bg-gray-100 disabled:opacity-30" :disabled="frozen || line.unit_cents === null || line.taken >= editor.max_units" :aria-label="$t('More')" @click="line.taken++">+</button>
                </div>
              </td>
              <td class="px-2 text-right whitespace-nowrap text-gray-700">
                <span v-if="line.unit_cents !== null">{{ money(line.unit_cents) }}</span>
                <span v-else class="text-xs text-red-700">{{ $t('No price') }}</span>
              </td>
              <td class="px-3 text-right whitespace-nowrap font-medium text-gray-900">{{ money((line.unit_cents || 0) * line.taken) }}</td>
            </tr>
          </tbody>
          <tfoot class="border-t border-gray-200 bg-gray-50">
            <tr>
              <td colspan="5" class="px-2 py-2.5 text-right font-semibold text-gray-700">{{ $t('This count comes to') }}</td>
              <td class="px-3 py-2.5 text-right whitespace-nowrap text-base font-bold text-gray-900">{{ money(total) }}</td>
            </tr>
          </tfoot>
        </table>
      </div>

      <div v-if="others.length && !frozen" class="mt-3">
        <button type="button" class="flex w-full items-center justify-center gap-1 rounded-lg border border-dashed border-gray-300 px-3 py-2 font-medium text-gray-600 hover:border-indigo-400 hover:text-indigo-700" :aria-expanded="picking" @click="picking = !picking">
          <PlusIcon class="h-4 w-4" aria-hidden="true" />{{ $t('Add another product') }}
        </button>
        <div v-if="picking" class="mt-2 grid max-h-64 grid-cols-2 gap-2 overflow-y-auto rounded-lg border border-gray-200 bg-gray-50 p-2 sm:grid-cols-3">
          <button v-for="line in others" :key="line.product_id" type="button" class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white p-1.5 text-left hover:border-indigo-400 hover:shadow-sm" @click="add(line)">
            <img v-if="line.photo" :src="line.photo" :alt="line.name" loading="lazy" class="h-10 w-10 flex-none rounded object-contain" />
            <span v-else class="flex h-10 w-10 flex-none items-center justify-center rounded bg-gray-50 text-gray-300"><PhotoIcon class="h-5 w-5" aria-hidden="true" /></span>
            <span class="min-w-0">
              <span class="block truncate text-xs font-medium text-gray-900" :title="line.name">{{ line.name }}</span>
              <span class="block text-xs text-gray-500">{{ money(line.unit_cents) }}</span>
            </span>
          </button>
        </div>
      </div>

      <template v-if="!editor.blocked_reason">
        <p class="mt-3 flex items-start gap-2 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm text-indigo-900">
          <InformationCircleIcon class="mt-0.5 h-4 w-4 flex-none text-indigo-500" aria-hidden="true" /><span>{{ outcome }}</span>
        </p>

        <div v-if="!confirming" class="mt-4 flex items-center justify-end gap-2">
          <button v-if="editor.mode === 'hold'" type="button" class="mr-auto text-xs text-blue-600 hover:underline" @click="$emit('key-in')">{{ $t('Key in an amount instead') }}</button>
          <button type="button" class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700" @click="confirming = true">{{ $t('Next') }}</button>
        </div>
        <div v-else class="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-3 py-3">
          <p class="font-semibold text-amber-900">{{ confirmText }}</p>
          <div class="mt-2 flex justify-end gap-2">
            <button type="button" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm hover:bg-gray-50" :disabled="sending" @click="confirming = false">{{ $t('Back') }}</button>
            <button type="button" class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 disabled:opacity-50" :disabled="sending" @click="save">{{ sending ? $t('Sending…') : $t('Confirm') }}</button>
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
import { InformationCircleIcon, PhotoIcon, PlusIcon } from '@heroicons/vue/20/solid';
import { t, tk } from '@/i18n';

const props = defineProps({
  recognitionId: { type: Number, required: true },
});
const emit = defineEmits(['saved', 'key-in']);

const editor = ref(null);
const lines = ref([]);
const added = ref([]);
const picking = ref(false);
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

function add(line) {
  added.value.push(line.product_id);
  line.taken = 1;
  picking.value = false;
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
