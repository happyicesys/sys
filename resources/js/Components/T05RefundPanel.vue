<template>
  <div class="text-sm">
    <div v-if="loading" class="py-3 text-gray-500">{{ $t('Loading…') }}</div>
    <template v-else-if="panel">
      <dl class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
        <div><dt class="text-gray-500">{{ $t('Charged') }}</dt><dd class="font-semibold text-gray-900">{{ money(panel.captured_cents) }}</dd></div>
        <div><dt class="text-gray-500">{{ $t('Refunded') }}</dt><dd class="font-semibold" :class="panel.refunded_cents ? 'text-rose-700' : 'text-gray-900'">{{ money(panel.refunded_cents) }}</dd></div>
        <div><dt class="text-gray-500">{{ $t('Can still refund') }}</dt><dd class="font-semibold text-gray-900">{{ money(panel.refundable_cents) }}</dd></div>
        <div><dt class="text-gray-500">{{ $t('Card') }}</dt><dd class="font-mono text-gray-900">{{ panel.card_masked || panel.payment_method || '—' }}</dd></div>
      </dl>

      <p v-if="panel.blocked_reason" class="mt-3 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-900">{{ panel.blocked_reason }}</p>
      <p v-if="message" class="mt-3 rounded-md border px-3 py-2 text-xs" :class="messageOk ? 'border-green-300 bg-green-50 text-green-800' : 'border-red-300 bg-red-50 text-red-800'">{{ message }}</p>

      <table class="mt-3 min-w-full">
        <thead class="text-xs text-gray-600 font-semibold">
          <tr><th class="text-left py-1">{{ $t('Product') }}</th><th class="text-left">{{ $t('Qty') }}</th><th class="text-left">{{ $t('Price') }}</th><th></th></tr>
        </thead>
        <tbody>
          <tr v-for="line in panel.lines" :key="line.product_id + ':' + line.unit_cents" class="border-t">
            <td class="py-2 pr-2">{{ line.name }}<span v-if="line.code" class="block text-xs text-gray-500">{{ line.code }}</span></td>
            <td class="pr-2 whitespace-nowrap">{{ line.qty }}<span v-if="line.refundable_qty < line.qty" class="ml-1 text-xs text-rose-700">{{ $t(':count refunded', { count: line.qty - line.refundable_qty }) }}</span></td>
            <td class="pr-2">{{ money(line.unit_cents) }}</td>
            <td class="text-right whitespace-nowrap">
              <template v-if="canAct && !panel.blocked_reason && line.refundable_qty > 0">
                <select v-if="line.refundable_qty > 1" :value="qty[lineKey(line)] || 1" @change="qty[lineKey(line)] = Number($event.target.value)" class="mr-1 rounded-md border-gray-300 py-1 pl-2 pr-7 text-xs" :aria-label="$t('Qty')">
                  <option v-for="n in line.refundable_qty" :key="n" :value="n">{{ n }}</option>
                </select>
                <button type="button" class="rounded-md border border-rose-600 bg-white px-2 py-1 text-xs font-semibold text-rose-700 hover:bg-rose-50" @click="askItem(line)">{{ $t('Refund') }}</button>
              </template>
            </td>
          </tr>
          <tr v-if="!panel.lines.length"><td colspan="4" class="py-2 text-xs text-gray-400">{{ $t('No item lines on this sale. It can still be refunded in full.') }}</td></tr>
        </tbody>
      </table>

      <div v-if="canAct && !panel.blocked_reason" class="mt-3 flex justify-end">
        <button type="button" class="rounded-md border border-rose-600 bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700" @click="askFull">
          {{ panel.refunded_cents ? $t('Refund the rest (:amount)', { amount: money(panel.refundable_cents) }) : $t('Full refund (:amount)', { amount: money(panel.refundable_cents) }) }}
        </button>
      </div>

      <div v-if="confirm" class="mt-3 rounded-md border border-rose-300 bg-rose-50 px-3 py-3">
        <p class="font-semibold text-rose-900">{{ $t('Refund :amount to the card?', { amount: money(confirm.amount_cents) }) }}</p>
        <p class="mt-1 text-xs text-rose-800">{{ confirm.label }}</p>
        <p class="mt-1 text-xs text-rose-800">{{ $t('This returns real money to the customer and cannot be undone. Stock does not change.') }}</p>
        <div class="mt-2 flex justify-end gap-2">
          <button type="button" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs hover:bg-gray-50" :disabled="sending" @click="confirm = null">{{ $t('Back') }}</button>
          <button type="button" class="rounded-md bg-rose-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-rose-700 disabled:opacity-50" :disabled="sending" @click="send">{{ sending ? $t('Sending…') : $t('Yes, refund') }}</button>
        </div>
      </div>

      <div v-if="panel.refunds.length" class="mt-3 border-t pt-2">
        <h4 class="text-xs uppercase tracking-wide text-gray-500">{{ $t('Refund history') }}</h4>
        <div v-for="r in panel.refunds" :key="r.id" class="mt-1 text-xs">
          <span class="inline-flex rounded px-1.5 py-0.5 font-semibold" :class="stateClass(r.state)">{{ stateLabel(r.state) }}</span>
          <span class="ml-1">{{ money(r.amount_cents) }} · {{ r.product_name ? r.qty + ' × ' + r.product_name : $t('Full refund') }}</span>
          <span class="ml-1 text-gray-500">{{ r.by }} · {{ r.at }}</span>
          <span v-if="r.error" class="block text-red-700">{{ r.error }}</span>
          <span v-if="canAct && r.state === 'uncertain'" class="mt-1 flex flex-wrap items-center gap-2">
            <span class="text-gray-600">{{ $t('Check Payrallel\'s portal, then:') }}</span>
            <button type="button" class="rounded border border-gray-300 bg-white px-2 py-0.5 hover:bg-gray-50" :disabled="sending" @click="resolve(r, true)">{{ $t('It went through') }}</button>
            <button type="button" class="rounded border border-gray-300 bg-white px-2 py-0.5 hover:bg-gray-50" :disabled="sending" @click="resolve(r, false)">{{ $t('It did not') }}</button>
          </span>
        </div>
      </div>
    </template>
    <p v-else-if="message" class="py-2 text-xs text-red-700">{{ message }}</p>
  </div>
</template>

<script setup>
import axios from 'axios';
import { onMounted, reactive, ref } from 'vue';
import { t, tk } from '@/i18n';

const props = defineProps({
  // The panel as the page already has it, or just the charge's id to fetch it (AI Recognition).
  initial: { type: Object, default: null },
  intentId: { type: Number, default: null },
  canAct: { type: Boolean, default: false },
});
const emit = defineEmits(['changed']);

const panel = ref(props.initial);
const loading = ref(false);
const sending = ref(false);
const message = ref('');
const messageOk = ref(false);
const confirm = ref(null);
const qty = reactive({});

const base = () => '/card-refunds';
const money = (cents) => '$' + (Number(cents || 0) / 100).toFixed(2);
const lineKey = (line) => line.product_id + ':' + line.unit_cents;

const STATES = {
  refunded: { text: tk('Refunded'), cls: 'bg-rose-100 text-rose-800' },
  refused: { text: tk('Not refunded'), cls: 'bg-gray-100 text-gray-700' },
  uncertain: { text: tk('No answer'), cls: 'bg-amber-100 text-amber-900' },
  sending: { text: tk('Sending…'), cls: 'bg-sky-100 text-sky-800' },
};
const stateLabel = (s) => t(STATES[s]?.text ?? s);
const stateClass = (s) => STATES[s]?.cls ?? 'bg-gray-100 text-gray-700';

onMounted(async () => {
  if (panel.value || !props.intentId) return;
  loading.value = true;
  try {
    panel.value = (await axios.get(`${base()}/intents/${props.intentId}`)).data.panel;
  } catch (e) {
    message.value = e.response?.data?.message || t(tk('Could not load this charge.'));
  } finally {
    loading.value = false;
  }
});

function askItem(line) {
  const n = Math.min(qty[lineKey(line)] || 1, line.refundable_qty);
  confirm.value = {
    url: `${base()}/intents/${panel.value.intent_id}/item`,
    label: `${n} × ${line.name}`,
    amount_cents: Math.min(line.unit_cents * n, panel.value.refundable_cents),
    body: { product_id: line.product_id, unit_cents: line.unit_cents, qty: n },
  };
}

function askFull() {
  confirm.value = {
    url: `${base()}/intents/${panel.value.intent_id}/full`,
    label: panel.value.refunded_cents ? t(tk('The rest of this charge')) : t(tk('The whole of this charge')),
    amount_cents: panel.value.refundable_cents,
    body: {},
  };
}

async function post(url, body) {
  sending.value = true;
  message.value = '';
  try {
    const { data } = await axios.post(url, body);
    panel.value = data.panel;
    message.value = data.message;
    messageOk.value = !!data.ok;
    emit('changed');
  } catch (e) {
    if (e.response?.data?.panel) panel.value = e.response.data.panel;
    message.value = e.response?.data?.message || t(tk('The request did not complete. Reload the page and check the refund history before trying again.'));
    messageOk.value = false;
  } finally {
    sending.value = false;
    confirm.value = null;
  }
}

const send = () => post(confirm.value.url, { ...confirm.value.body, amount_cents: confirm.value.amount_cents });
const resolve = (refund, wentThrough) => post(`${base()}/${refund.id}/resolve`, { went_through: wentThrough });
</script>
