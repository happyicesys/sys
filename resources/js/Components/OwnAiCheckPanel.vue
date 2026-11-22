<template>
  <div class="text-left text-sm">
    <div v-if="loading" class="py-3 text-gray-500">{{ $t('Loading…') }}</div>
    <template v-else-if="editor">
      <p class="text-sm text-gray-600">
        <template v-if="editor.order_id">{{ $t('Order ID') }}: <span class="font-mono font-medium text-gray-900">{{ editor.order_id }}</span>. </template>
        {{ $t('Watch the videos, then say whether HappyIce AI counted right. If not, set what really left the cabinet.') }}
      </p>
      <p class="mt-2 flex items-start gap-2 rounded-lg border border-violet-200 bg-violet-50 px-3 py-2 text-xs text-violet-900">
        <InformationCircleIcon class="mt-0.5 h-4 w-4 flex-none text-violet-500" aria-hidden="true" />
        <span>{{ $t('Only HappyIce AI learns from this, at 03:15 each night. It changes no sale, money or stock: use Adjust Qty for that.') }}</span>
      </p>
      <p v-if="editor.check" class="mt-2 rounded-lg border px-3 py-2 text-xs" :class="editor.check.outcome === 'right' ? 'border-green-300 bg-green-50 text-green-900' : editor.check.outcome === 'unclear' ? 'border-gray-300 bg-gray-50 text-gray-800' : 'border-rose-300 bg-rose-50 text-rose-900'">
        {{ editor.check.outcome === 'right'
          ? $t('Checked by :name on :at: HappyIce AI was right.', { name: editor.check.by || '?', at: editor.check.at })
          : editor.check.outcome === 'unclear'
            ? $t('Checked by :name on :at: the video cannot show what was taken.', { name: editor.check.by || '?', at: editor.check.at })
            : $t('Checked by :name on :at: HappyIce AI was wrong.', { name: editor.check.by || '?', at: editor.check.at }) }}
        {{ $t('Saving again replaces that check.') }}
      </p>
      <p v-if="message" class="mt-3 rounded-lg border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-800">{{ message }}</p>
      <p v-if="saved" class="mt-3 flex items-center gap-2 rounded-lg border border-green-300 bg-green-50 px-3 py-2 text-sm font-semibold text-green-800" role="status">
        <CheckCircleIcon class="h-5 w-5 flex-none text-green-600" aria-hidden="true" />{{ saved }}
      </p>
      <div class="mt-3 overflow-x-auto rounded-xl border border-gray-200">
        <table class="min-w-full">
          <thead class="bg-gray-50 text-xs font-semibold text-gray-500">
            <tr>
              <th class="px-3 py-2 text-left">{{ $t('Product') }}</th>
              <th class="px-2 py-2 text-center">{{ $t('Paid') }}</th>
              <th class="px-2 py-2 text-center whitespace-nowrap">{{ $t('Zijia AI') }}</th>
              <th class="px-2 py-2 text-center whitespace-nowrap">{{ $t('HappyIce AI') }}</th>
              <th class="px-2 py-2 text-center whitespace-nowrap">{{ $t('Really taken') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="line in shown" :key="line.product_id" :class="line.taken !== line.happyice ? 'bg-rose-50' : 'bg-white'">
              <td class="px-3 py-2">
                <div class="flex min-w-[11rem] items-center gap-3">
                  <img v-if="line.photo" :src="line.photo" :alt="line.name" loading="lazy" class="h-12 w-12 flex-none rounded-lg border border-gray-200 bg-white object-contain p-0.5" />
                  <span v-else class="flex h-12 w-12 flex-none items-center justify-center rounded-lg border border-gray-200 bg-gray-50 text-gray-300"><PhotoIcon class="h-6 w-6" aria-hidden="true" /></span>
                  <span class="min-w-0">
                    <span class="block font-semibold text-gray-900">{{ line.name }}</span>
                    <span v-if="line.code" class="block font-mono text-xs text-gray-500">{{ line.code }}</span>
                  </span>
                </div>
              </td>
              <td class="px-2 text-center text-gray-700">{{ line.paid }}</td>
              <td class="px-2 text-center text-gray-700">{{ editor.zijia_answered ? line.zijia : '—' }}</td>
              <td class="px-2 text-center font-semibold text-emerald-800">{{ line.happyice }}</td>
              <td class="px-2">
                <div class="mx-auto flex w-max items-center overflow-hidden rounded-lg border border-gray-300 bg-white shadow-sm">
                  <button type="button" class="h-8 w-8 text-base font-semibold text-gray-700 hover:bg-gray-100 disabled:opacity-30" :disabled="line.taken <= 0" :aria-label="$t('Less')" @click="line.taken--">−</button>
                  <span class="w-9 border-x border-gray-200 text-center font-bold leading-8" :class="line.taken !== line.happyice ? 'text-rose-700' : 'text-gray-900'">{{ line.taken }}</span>
                  <button type="button" class="h-8 w-8 text-base font-semibold text-gray-700 hover:bg-gray-100 disabled:opacity-30" :disabled="line.taken >= editor.max_units" :aria-label="$t('More')" @click="line.taken++">+</button>
                </div>
              </td>
            </tr>
            <tr v-if="!shown.length"><td colspan="5" class="px-3 py-3 text-center text-gray-500">{{ $t('Nothing taken') }}</td></tr>
          </tbody>
        </table>
      </div>
      <div v-if="others.length" class="mt-3">
        <button type="button" class="flex w-full items-center justify-center gap-1 rounded-lg border border-dashed border-gray-300 px-3 py-2 font-medium text-gray-600 hover:border-violet-400 hover:text-violet-700" :aria-expanded="picking" @click="picking = !picking">
          <PlusIcon class="h-4 w-4" aria-hidden="true" />{{ $t('Add another product') }}
        </button>
        <div v-if="picking" class="mt-2 grid max-h-64 grid-cols-2 gap-2 overflow-y-auto rounded-lg border border-gray-200 bg-gray-50 p-2 sm:grid-cols-3">
          <button v-for="line in others" :key="line.product_id" type="button" class="flex items-center gap-2 rounded-lg border border-gray-200 bg-white p-1.5 text-left hover:border-violet-400 hover:shadow-sm" @click="add(line)">
            <img v-if="line.photo" :src="line.photo" :alt="line.name" loading="lazy" class="h-10 w-10 flex-none rounded object-contain" />
            <span v-else class="flex h-10 w-10 flex-none items-center justify-center rounded bg-gray-50 text-gray-300"><PhotoIcon class="h-5 w-5" aria-hidden="true" /></span>
            <span class="block min-w-0 truncate text-xs font-medium text-gray-900" :title="line.name">{{ line.name }}</span>
          </button>
        </div>
      </div>
      <label class="mt-3 block">
        <span class="text-xs font-semibold text-gray-600">{{ $t('What should HappyIce AI learn? (optional)') }}</span>
        <textarea v-model="note" rows="2" maxlength="2000" class="mt-1 block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-violet-500 focus:ring-violet-500" :placeholder="$t('e.g. counted a pack the customer put back')"></textarea>
      </label>
      <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
        <button v-if="changed" type="button" class="mr-auto text-xs text-gray-600 hover:underline" @click="reset">{{ $t('Back to HappyIce AI\'s count') }}</button>
        <button type="button" class="inline-flex items-center gap-1 rounded-lg border border-gray-400 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-40" :disabled="sending || saved" @pointerdown="down" @pointerup="up('unclear', $event)" @click="keyboardClick('unclear', $event)">
          <QuestionMarkCircleIcon class="h-4 w-4" aria-hidden="true" />{{ $t('Can\'t tell from the video') }}
        </button>
        <button type="button" class="inline-flex items-center gap-1 rounded-lg bg-green-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-green-700 disabled:opacity-40" :disabled="sending || saved || changed" @pointerdown="down" @pointerup="up('right', $event)" @click="keyboardClick('right', $event)">
          <CheckCircleIcon class="h-4 w-4" aria-hidden="true" />{{ $t('HappyIce AI is right') }}
        </button>
        <button type="button" class="inline-flex items-center gap-1 rounded-lg bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-rose-700 disabled:opacity-40" :disabled="sending || saved || !changed" @pointerdown="down" @pointerup="up('wrong', $event)" @click="keyboardClick('wrong', $event)">
          <XCircleIcon class="h-4 w-4" aria-hidden="true" />{{ $t('Wrong: save the right count') }}
        </button>
      </div>
      <p v-if="!changed" class="mt-2 text-right text-xs text-gray-500">{{ $t('To mark it wrong, first set the right count above.') }}</p>
    </template>
    <p v-else-if="message" class="py-2 text-xs text-red-700">{{ message }}</p>
  </div>
</template>

<script setup>
import axios from 'axios';
import { computed, onMounted, ref } from 'vue';
import { CheckCircleIcon, InformationCircleIcon, PhotoIcon, PlusIcon, QuestionMarkCircleIcon, XCircleIcon } from '@heroicons/vue/20/solid';
import { t, tk } from '@/i18n';

// Brian, 2026-11-22: a person checks HappyIce AI's count; the office Macs train on it at 03:15. Moves nothing.
const props = defineProps({
  recognitionId: { type: Number, required: true },
});
const emit = defineEmits(['saved']);

const editor = ref(null);
const lines = ref([]);
const added = ref([]);
const note = ref('');
const picking = ref(false);
const loading = ref(true);
const sending = ref(false);
const message = ref('');
const saved = ref('');            // the server's confirmation, shown in the form before it closes

const url = () => `/ai-recognition/${props.recognitionId}/happyice-check`;
const inPlay = (line) => line.paid > 0 || line.zijia > 0 || line.happyice > 0 || line.taken > 0 || added.value.includes(line.product_id);
const shown = computed(() => lines.value.filter(inPlay));
const others = computed(() => lines.value.filter((line) => !inPlay(line)));
const changed = computed(() => lines.value.some((line) => line.taken !== line.happyice));

function load(data) {
  editor.value = data;
  lines.value = data.lines.map((line) => ({ ...line }));
  added.value = [];
  note.value = data.check?.note || '';
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

function reset() {
  lines.value.forEach((line) => { line.taken = line.happyice; });
}

// Brian, 2026-11-22: on a phone a save took two taps. With the keyboard up (after typing a note), the first tap only
// closed it and the form jumped, so the click landed nowhere. The buttons now act on pointerup, which comes before the
// keyboard moves anything; a press that slid away (a scroll) is ignored, and click still serves keyboard users.
let pressAt = null;
function down(e) {
  pressAt = { x: e.clientX, y: e.clientY };
}
function up(outcome, e) {
  const start = pressAt;
  pressAt = null;
  if (!start || (e.pointerType === 'mouse' && e.button !== 0)) return;
  if (Math.hypot(e.clientX - start.x, e.clientY - start.y) > 12) return;
  e.preventDefault();
  save(outcome);
}
function keyboardClick(outcome, e) {
  if (e.detail === 0) save(outcome);   // Enter / Space; a pointer press was handled on pointerup
}

async function save(outcome) {
  if (sending.value || saved.value) return;
  sending.value = true;
  message.value = '';
  const taken = {};
  lines.value.forEach((line) => { taken[line.product_id] = line.taken; });
  try {
    const { data } = await axios.post(url(), { outcome, taken, note: note.value });
    saved.value = data.message || t(tk('Saved.'));
    setTimeout(() => emit('saved', data), 1200);   // long enough to read; the row shows the check after
  } catch (e) {
    if (e.response?.data?.editor) load(e.response.data.editor);
    message.value = e.response?.data?.message || t(tk('The request did not complete. Reload the page and check this sale before trying again.'));
  } finally {
    sending.value = false;
  }
}
</script>
