<template>
  <div class="rounded-lg border border-indigo-200 bg-white p-4 text-left shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
      <h4 class="flex items-center gap-2 text-sm font-semibold text-indigo-700">
        <img
          v-if="parentThumbnail"
          :src="parentThumbnail"
          class="h-7 w-7 rounded-full object-cover border border-indigo-200"
          alt=""
        />
        <span>Blind flavours — {{ parentCode }} {{ parentName }}</span>
      </h4>
      <div class="flex items-center gap-2 text-xs text-gray-600">
        <label class="whitespace-nowrap">How many flavours in total?</label>
        <input
          type="number" min="0" max="20"
          class="w-16 rounded border-gray-300 text-sm py-1"
          v-model.number="flavourCount"
          @change="applyCount"
        />
        <Button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-700 text-xs" @click="distributeEven">
          Even split
        </Button>
      </div>
    </div>

    <table class="w-full text-sm">
      <thead>
        <tr class="text-xs text-gray-500 border-b">
          <th class="text-left py-1 w-1/2">Flavour</th>
          <th class="text-left py-1">Weight %</th>
          <th class="py-1"></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(row, idx) in rows" :key="idx" class="border-b border-gray-100">
          <td class="py-1 pr-2">
            <div class="flex items-center gap-2">
              <img
                v-if="thumbFor(row)"
                :src="thumbFor(row)"
                class="h-9 w-9 rounded-full object-cover border border-gray-200 shrink-0"
                alt=""
              />
              <span
                v-else
                class="h-9 w-9 rounded-full bg-gray-100 border border-gray-200 shrink-0 flex items-center justify-center text-gray-300 text-xs"
              >?</span>
              <div class="flex-1 min-w-0">
                <MultiSelect
                  :options="availableOptionsFor(row)"
                  trackBy="id"
                  valueProp="id"
                  label="full_name"
                  placeholder="— select flavour —"
                  open-direction="bottom"
                  :model-value="optionFor(row)"
                  @selected="(opt) => onFlavourSelected(row, opt)"
                />
              </div>
            </div>
          </td>
          <td class="py-1 pr-2">
            <div class="flex items-center gap-2">
              <input
                type="number" min="1" max="100"
                class="w-20 rounded border-gray-300 text-sm py-1"
                v-model.number="row.weight_pct"
              />
              <span class="text-xs text-gray-400">{{ pctLabel(row) }}</span>
            </div>
          </td>
          <td class="py-1 text-right">
            <button type="button" class="text-red-500 hover:text-red-700 text-xs" @click="removeRow(idx)">
              remove
            </button>
          </td>
        </tr>
        <tr v-if="!rows.length">
          <td colspan="3" class="py-2 text-xs text-gray-400">No flavours bound yet.</td>
        </tr>
      </tbody>
    </table>

    <div class="flex flex-wrap items-center justify-between gap-2 mt-2">
      <button type="button" class="text-xs text-indigo-600 hover:text-indigo-800" @click="addRow">
        + Add flavour
      </button>
      <div class="text-xs font-semibold" :class="totalOk ? 'text-green-600' : 'text-red-500'">
        Total: {{ total }}% {{ rows.length ? (totalOk ? '✓' : '✗') : '' }}
      </div>
    </div>

    <p class="text-xs text-red-500 mt-1" v-if="error">{{ error }}</p>

    <div class="flex justify-end mt-2">
      <Button
        type="button"
        class="bg-green-500 hover:bg-green-600 text-white text-xs disabled:opacity-50 disabled:cursor-not-allowed"
        :disabled="!canSave || saving"
        @click="save"
      >
        {{ saving ? 'Saving…' : 'Save flavours' }}
      </Button>
    </div>
  </div>
</template>

<script setup>
import Button from '@/Components/Button.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';

const props = defineProps({
  productId: { type: [Number, String], required: true },
  parentCode: { type: String, default: '' },
  parentName: { type: String, default: '' },
  parentThumbnail: { type: String, default: '' },
  initialChildren: { type: Array, default: () => [] },
  flavourOptions: { type: Array, default: () => [] },
});

const toast = useToast();
const saving = ref(false);
const error = ref('');

const rows = ref(
  (props.initialChildren || [])
    .slice()
    .sort((a, b) => (a.sort ?? 0) - (b.sort ?? 0))
    .map((c) => ({ child_product_id: c.child_product_id, weight_pct: c.weight_pct }))
);

const flavourCount = ref(rows.value.length);

const flavourList = computed(() =>
  props.flavourOptions.map((o) => ({
    id: o.id,
    full_name: `${o.code} - ${o.name}`,
    thumbnail_url: o.thumbnail?.full_url || null,
  }))
);

const total = computed(() => rows.value.reduce((s, r) => s + (Number(r.weight_pct) || 0), 0));
const totalOk = computed(() => rows.value.length > 0 && total.value === 100);
const allSelected = computed(() => rows.value.every((r) => r.child_product_id != null));
const canSave = computed(() => rows.value.length === 0 || (totalOk.value && allSelected.value));

function availableOptionsFor(row) {
  const takenElsewhere = new Set(
    rows.value.filter((r) => r !== row).map((r) => r.child_product_id).filter((v) => v != null)
  );
  return flavourList.value.filter((o) => !takenElsewhere.has(o.id));
}

function optionFor(row) {
  return flavourList.value.find((o) => o.id === row.child_product_id) || null;
}

function thumbFor(row) {
  return optionFor(row)?.thumbnail_url || null;
}

function onFlavourSelected(row, opt) {
  row.child_product_id = opt ? opt.id : null;
}

function pctLabel(row) {
  const w = Number(row.weight_pct) || 0;
  if (!total.value) return '';
  return `${Math.round((w / total.value) * 100)}%`;
}

function addRow() {
  rows.value.push({ child_product_id: null, weight_pct: 0 });
  flavourCount.value = rows.value.length;
}

function removeRow(idx) {
  rows.value.splice(idx, 1);
  flavourCount.value = rows.value.length;
}

function applyCount() {
  let n = Math.max(0, Math.min(20, Math.floor(flavourCount.value || 0)));
  while (rows.value.length < n) rows.value.push({ child_product_id: null, weight_pct: 0 });
  while (rows.value.length > n) rows.value.pop();
  distributeEven();
}

function distributeEven() {
  const n = rows.value.length;
  if (!n) return;
  const base = Math.floor(100 / n);
  const rem = 100 - base * n;
  rows.value.forEach((r, i) => {
    r.weight_pct = base + (i < rem ? 1 : 0);
  });
}

function save() {
  error.value = '';
  if (!canSave.value) {
    error.value = rows.value.length && !allSelected.value
      ? 'Every flavour row must have a flavour selected.'
      : 'Flavour ratios must add up to exactly 100%.';
    return;
  }
  saving.value = true;
  router.post(
    `/products/${props.productId}/children`,
    {
      children: rows.value.map((r, i) => ({
        child_product_id: r.child_product_id,
        weight_pct: r.weight_pct,
        sort: i,
      })),
    },
    {
      preserveScroll: true,
      preserveState: true,
      onSuccess: () => toast.success('Flavours saved.'),
      onError: (errs) => {
        error.value = errs.children || 'Could not save flavours.';
      },
      onFinish: () => { saving.value = false; },
    }
  );
}
</script>
