<template>
  <!--
    Product → Edit → Smart Freezer AI Training (ZijiaSkuApplicationService / ZijiaAiTrainingController).
    A product is submitted to Zijia's algorithm for modelling (算法服务接口文档 §5); their approval
    callback (§7) comes back to mark1 and sets the product's barcode, which puts it on the AI's list.
    Every exchange is in the activity log below.
  -->
  <div class="shadow-sm ring-1 ring-black ring-opacity-5 p-5 mb-3 bg-white">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 pb-3">
      <div>
        <h3 class="text-base font-semibold text-gray-900">Smart Freezer AI Training</h3>
        <p class="text-xs text-gray-500">
          Submit this product to Zijia so the freezer's camera AI can recognise it. Once Zijia approves, its barcode is
          set here automatically and freezer sales of it are checked by the AI.
        </p>
      </div>
      <div v-if="app" class="flex flex-wrap items-center gap-2 text-xs">
        <span class="rounded px-2 py-0.5 font-semibold border" :class="statusClass(app.status)">{{ statusLabel(app.status) }}</span>
        <span v-if="app.source === 'vms4'" class="rounded px-2 py-0.5 font-semibold border bg-indigo-50 text-indigo-700 border-indigo-300"
              title="Approved in Zijia's vms4 portal; details and photos mirrored from their library">Imported from vms4</span>
        <span class="text-gray-500">Application <span class="font-mono">{{ app.application_no }}</span></span>
      </div>
    </div>

    <div v-if="!data.configured" class="mt-3 rounded border border-amber-300 bg-amber-50 p-2 text-xs text-amber-800">
      Zijia's algorithm service is not configured on this server, so nothing can be submitted.
    </div>

    <!-- Where the application stands -->
    <div v-if="app && !app.editable" class="mt-3 rounded border p-3 text-sm space-y-1" :class="statusPanelClass(app.status)">
      <div v-if="app.status === 'submitted'">
        Waiting for Zijia's review — submitted {{ app.submitted_at }}<span v-if="app.submitted_by"> by {{ app.submitted_by }}</span>.
        The barcode <span class="font-mono">{{ app.product_code }}</span> is set on the product when they approve.
      </div>
      <div v-else-if="app.status === 'approved' && app.source === 'vms4'">
        Approved in Zijia's vms4 portal. Details and photos below are mirrored from their library
        (last changed there {{ app.library_updated_at || 'unknown' }}) and kept in step every 3 minutes.
        Barcode <span class="font-mono">{{ app.product_code }}</span> is on the AI's list.
        <span v-if="productBarcode !== app.product_code" class="block text-red-700">
          The product's barcode is {{ productBarcode || 'empty' }} — see the log below.
        </span>
      </div>
      <div v-else-if="app.status === 'approved'">
        Approved by Zijia {{ app.decided_at }}. Barcode <span class="font-mono">{{ app.product_code }}</span> is on the AI's list.
        <span v-if="productBarcode !== app.product_code" class="block text-red-700">
          The product's barcode is {{ productBarcode || 'empty' }} — see the log below.
        </span>
      </div>
      <div v-else-if="app.status === 'rejected'">
        Rejected by Zijia {{ app.decided_at }}: <span class="font-medium">{{ app.decision_msg || 'no reason given' }}</span>
      </div>
      <div v-else-if="app.status === 'failed'">
        Zijia did not accept the submission: <span class="font-medium">{{ app.last_error }}</span>
      </div>
      <div v-if="canEdit && app.status !== 'submitted'" class="pt-1">
        <Button type="button" class="bg-sky-700 hover:bg-sky-800 text-white disabled:opacity-50 disabled:cursor-not-allowed" :disabled="busy" @click="startNew">
          Start a new application
        </Button>
        <span class="ml-2 text-xs text-gray-500">Copies this one into a new draft (new pack, photos, or a fix after rejection).</span>
      </div>
    </div>

    <!-- What this application was sent / approved with (read-only) -->
    <div v-if="app && !app.editable" class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-6 text-sm">
      <div class="sm:col-span-3"><span class="text-gray-500">Name for the AI:</span> {{ app.sku_name }}</div>
      <div class="sm:col-span-3"><span class="text-gray-500">Barcode:</span> <span class="font-mono">{{ app.product_code }}</span></div>
      <div class="sm:col-span-2"><span class="text-gray-500">Brand:</span> {{ app.brand_name }}</div>
      <div class="sm:col-span-1"><span class="text-gray-500">Spec:</span> {{ app.spec || '—' }}</div>
      <div class="sm:col-span-3"><span class="text-gray-500">Category / package:</span> {{ optionName(data.categoryOptions, app.category) }} · {{ optionName(data.packageTypeOptions, app.package_type) }}</div>
      <div class="sm:col-span-6 flex flex-wrap gap-2 items-end">
        <div v-if="app.package_image_url" class="text-xs text-gray-500">
          <a :href="app.package_image_url" target="_blank"><img :src="app.package_image_url" class="h-24 w-24 rounded border border-gray-200 object-contain bg-white" alt="" /></a>
          package
        </div>
        <template v-for="angle in angles" :key="'ro-' + angle.key">
          <div v-for="url in (app.model_pics[angle.key] || [])" :key="url" class="text-xs text-gray-500">
            <a :href="url" target="_blank"><img :src="url" class="h-24 w-24 rounded border border-gray-200 object-contain bg-white" alt="" /></a>
            {{ angle.short }}
          </div>
        </template>
      </div>
    </div>

    <!-- The draft -->
    <div v-if="!app || app.editable" class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-6">
      <div class="sm:col-span-3">
        <label class="block text-sm font-medium text-gray-700">Product name for the AI <span class="text-red-600">*</span></label>
        <input v-model="form.sku_name" type="text" :disabled="!canEdit" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        <p class="mt-1 text-xs text-red-600" v-if="err('sku_name')">{{ err('sku_name') }}</p>
      </div>
      <div class="sm:col-span-3">
        <label class="block text-sm font-medium text-gray-700">Barcode <span class="text-red-600">*</span></label>
        <input v-model="form.product_code" type="text" :disabled="!canEdit" placeholder="e.g. 8851932434300" class="mt-1 block w-full rounded-md border-gray-300 text-sm font-mono" />
        <p class="mt-1 text-xs text-gray-500">The barcode printed on the pack (EAN-13). If it has none, a unique code of ours. Set on the product only when Zijia approves.</p>
        <p class="mt-1 text-xs text-red-600" v-if="err('product_code')">{{ err('product_code') }}</p>
      </div>
      <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-gray-700">Brand <span class="text-red-600">*</span></label>
        <input v-model="form.brand_name" type="text" :disabled="!canEdit" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        <p class="mt-1 text-xs text-gray-500">其他/其他 when there is none.</p>
        <p class="mt-1 text-xs text-red-600" v-if="err('brand_name')">{{ err('brand_name') }}</p>
      </div>
      <div class="sm:col-span-1">
        <label class="block text-sm font-medium text-gray-700">Spec</label>
        <input v-model="form.spec" type="text" :disabled="!canEdit" placeholder="120克" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
      </div>
      <div class="sm:col-span-3 grid grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-medium text-gray-700">Category <span class="text-red-600">*</span></label>
          <select v-model="form.category" :disabled="!canEdit" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            <option :value="null">Select</option>
            <option v-for="o in data.categoryOptions" :key="o.id" :value="o.id">{{ o.name }}</option>
          </select>
          <p class="mt-1 text-xs text-red-600" v-if="err('category')">{{ err('category') }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">Package type <span class="text-red-600">*</span></label>
          <select v-model="form.package_type" :disabled="!canEdit" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            <option :value="null">Select</option>
            <option v-for="o in data.packageTypeOptions" :key="o.id" :value="o.id">{{ o.name }}</option>
          </select>
          <p class="mt-1 text-xs text-red-600" v-if="err('package_type')">{{ err('package_type') }}</p>
        </div>
      </div>

      <!-- Package photo -->
      <div class="sm:col-span-6">
        <label class="block text-sm font-medium text-gray-700">Package photo <span class="text-red-600">*</span></label>
        <div class="mt-1 flex flex-wrap items-center gap-3">
          <a v-if="packageImageUrl" :href="packageImageUrl" target="_blank">
            <img :src="packageImageUrl" class="h-24 w-24 rounded border border-gray-200 object-contain bg-white" alt="" />
          </a>
          <span v-else class="text-xs text-gray-500">None yet — the product photo above is used by default.</span>
          <input v-if="canEdit" type="file" accept="image/*" @input="form.package_image = $event.target.files[0]" class="text-sm" />
        </div>
        <p class="mt-1 text-xs text-gray-500">The pack's main photo (front). Max 5 MB.</p>
        <p class="mt-1 text-xs text-red-600" v-if="err('package_image_url') || err('package_image')">{{ err('package_image_url') || err('package_image') }}</p>
      </div>

      <!-- Model photos per angle -->
      <div v-for="angle in angles" :key="angle.key" class="sm:col-span-6">
        <label class="block text-sm font-medium text-gray-700">
          {{ angle.label }} <span v-if="angle.required" class="text-red-600">*</span>
          <span class="ml-1 text-xs font-normal text-gray-500">{{ angle.hint }}</span>
        </label>
        <div class="mt-1 flex flex-wrap gap-2">
          <div v-for="url in existing(angle.key)" :key="url" class="relative">
            <a :href="url" target="_blank">
              <img :src="url" class="h-20 w-20 rounded border object-cover" :class="form.remove.includes(url) ? 'opacity-30 border-red-400' : 'border-gray-200'" alt="" />
            </a>
            <button v-if="canEdit" type="button" @click="toggleRemove(url)"
                    class="absolute -right-1 -top-1 rounded-full bg-white px-1 text-xs border"
                    :class="form.remove.includes(url) ? 'text-gray-600 border-gray-300' : 'text-red-600 border-red-300'"
                    :title="form.remove.includes(url) ? 'Keep' : 'Remove'">{{ form.remove.includes(url) ? '↺' : '✕' }}</button>
          </div>
          <div v-for="(file, i) in form.photos[angle.key]" :key="'new-' + i" class="relative">
            <img :src="preview(file)" class="h-20 w-20 rounded border border-emerald-400 object-cover" alt="" />
            <span class="absolute bottom-0 left-0 right-0 bg-emerald-600 text-[10px] text-white text-center">new</span>
          </div>
        </div>
        <input v-if="canEdit" type="file" accept="image/*" multiple class="mt-1 text-sm" @input="addPhotos(angle.key, $event)" />
        <p class="mt-1 text-xs text-red-600" v-if="angle.required && err('model_pics.high')">{{ err('model_pics.high') }}</p>
      </div>

      <!-- What still blocks submission -->
      <div v-if="missingList.length" class="sm:col-span-6 rounded border border-amber-300 bg-amber-50 p-2 text-xs text-amber-900">
        <div class="font-semibold">Still needed before it can be submitted:</div>
        <ul class="list-disc pl-5">
          <li v-for="m in missingList" :key="m">{{ m }}</li>
        </ul>
      </div>
      <div v-if="err('application')" class="sm:col-span-6 text-sm text-red-600">{{ err('application') }}</div>

      <div v-if="canEdit" class="sm:col-span-6 flex flex-wrap justify-end gap-2">
        <Button type="button" class="bg-gray-600 hover:bg-gray-700 text-white disabled:opacity-50 disabled:cursor-not-allowed" :disabled="busy" @click="save">
          {{ busy ? 'Saving…' : 'Save draft' }}
        </Button>
        <Button type="button" class="bg-emerald-600 hover:bg-emerald-700 text-white disabled:opacity-40 disabled:cursor-not-allowed" :disabled="busy || !app || !data.configured || dirty || missingList.length > 0" @click="submit"
                :title="dirty ? 'Save the draft first' : (missingList.length ? 'Fill in the required fields first' : '')">
          Submit to Zijia
        </Button>
      </div>
      <p v-if="canEdit && dirty" class="sm:col-span-6 text-right text-xs text-gray-500">Unsaved changes — save the draft, then submit.</p>
    </div>

    <!-- Activity log: every exchange between mark1 and Zijia -->
    <div v-if="app && app.events.length" class="mt-4">
      <h4 class="text-sm font-semibold text-gray-800">Activity log — application {{ app.application_no }}</h4>
      <ul class="mt-1 divide-y divide-gray-100 rounded border border-gray-200 text-xs">
        <li v-for="e in app.events" :key="e.id" class="px-2 py-1">
          <div class="flex flex-wrap items-center gap-2">
            <span class="font-mono text-gray-500">{{ e.at }}</span>
            <span class="font-semibold" :class="e.level === 'error' ? 'text-red-700' : (e.level === 'warning' ? 'text-amber-700' : 'text-gray-800')">{{ eventLabel(e.event) }}</span>
            <span v-if="e.user_name" class="text-gray-500">· {{ e.user_name }}</span>
          </div>
          <details v-if="e.detail" class="mt-0.5">
            <summary class="cursor-pointer text-gray-500">details</summary>
            <pre class="mt-1 max-h-64 overflow-auto whitespace-pre-wrap break-all rounded bg-gray-50 p-2 text-[11px]">{{ JSON.stringify(e.detail, null, 2) }}</pre>
          </details>
        </li>
      </ul>
    </div>

    <!-- Earlier applications -->
    <div v-if="data.history.length > 1" class="mt-3 text-xs text-gray-600">
      <span class="font-semibold">Earlier applications:</span>
      <span v-for="h in data.history.slice(1)" :key="h.id" class="ml-2">
        <span class="font-mono">{{ h.application_no }}</span> {{ statusLabel(h.status) }}<span v-if="h.source === 'vms4'"> (vms4)</span><span v-if="h.decided_at"> {{ h.decided_at }}</span>
      </span>
    </div>
  </div>
</template>

<script setup>
import Button from '@/Components/Button.vue';
import { computed, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
  productId: { type: [Number, String], required: true },
  data: { type: Object, required: true },
});

const permissions = usePage().props.auth.permissions;
const canEdit = computed(() => permissions.includes('update products'));
const app = computed(() => props.data.application);
const productBarcode = computed(() => props.data.product_barcode || '');
const busy = ref(false);
const errors = ref({});

const angles = [
  { key: 'high', short: 'top', label: 'Model photos — top view', required: true, hint: 'At least 1; Zijia suggests 5–10, from above like the freezer cameras, different sides and angles, background removed (see an approved product).' },
  { key: 'horizontal', short: 'side', label: 'Model photos — side view', required: false, hint: 'Optional.' },
  { key: 'low', short: 'low', label: 'Model photos — low angle', required: false, hint: 'Optional.' },
];

function blankForm() {
  const source = app.value && app.value.editable ? app.value : props.data.defaults;
  return {
    sku_name: source.sku_name ?? '',
    brand_name: source.brand_name ?? '',
    spec: source.spec ?? '',
    category: source.category ?? null,
    package_type: source.package_type ?? null,
    product_code: source.product_code ?? '',
    package_image: null,
    photos: { high: [], horizontal: [], low: [] },
    remove: [],
  };
}
const form = ref(blankForm());
const saved = ref(JSON.stringify(fieldsOf(form.value)));
watch(() => props.data, () => {
  form.value = blankForm();
  saved.value = JSON.stringify(fieldsOf(form.value));
}, { deep: true });

function fieldsOf(f) {
  return [f.sku_name, f.brand_name, f.spec, f.category, f.package_type, f.product_code];
}
const dirty = computed(() => JSON.stringify(fieldsOf(form.value)) !== saved.value
  || !!form.value.package_image || form.value.remove.length > 0
  || Object.values(form.value.photos).some(list => list.length > 0));

const packageImageUrl = computed(() => (app.value && app.value.editable ? app.value.package_image_url : props.data.defaults.package_image_url) || '');
const missingList = computed(() => (app.value && app.value.editable ? Object.values(app.value.missing || {}) : []));

function existing(angle) {
  const source = app.value && app.value.editable ? app.value.model_pics : props.data.defaults.model_pics;
  return (source && source[angle]) || [];
}
function err(key) {
  return errors.value[key] || null;
}
function addPhotos(angle, event) {
  form.value.photos[angle] = [...form.value.photos[angle], ...Array.from(event.target.files || [])];
  event.target.value = '';
}
function toggleRemove(url) {
  const i = form.value.remove.indexOf(url);
  if (i >= 0) form.value.remove.splice(i, 1); else form.value.remove.push(url);
}
const previews = new WeakMap();
function preview(file) {
  if (!previews.has(file)) previews.set(file, URL.createObjectURL(file));
  return previews.get(file);
}

function post(url, payload, opts = {}) {
  busy.value = true;
  errors.value = {};
  router.post(url, payload, {
    forceFormData: true,
    preserveScroll: true,
    onError: (e) => { errors.value = e; },
    onFinish: () => { busy.value = false; },
    ...opts,
  });
}
function save() {
  const f = form.value;
  post(`/products/${props.productId}/ai-training`, {
    sku_name: f.sku_name, brand_name: f.brand_name, spec: f.spec, category: f.category, package_type: f.package_type,
    product_code: f.product_code, package_image: f.package_image, photos: f.photos, remove: f.remove,
  });
}
function startNew() {
  post(`/products/${props.productId}/ai-training`, { start_new: 1 });
}
function submit() {
  if (!confirm(`Submit "${form.value.sku_name}" (barcode ${form.value.product_code}) to Zijia for AI modelling?`)) return;
  post(`/products/${props.productId}/ai-training/submit`, {});
}

const STATUS = {
  draft: ['Draft', 'bg-gray-100 text-gray-700 border-gray-300'],
  submitted: ['Waiting for Zijia', 'bg-amber-50 text-amber-800 border-amber-300'],
  approved: ['Approved', 'bg-green-50 text-green-700 border-green-300'],
  rejected: ['Rejected', 'bg-red-50 text-red-700 border-red-300'],
  failed: ['Not accepted', 'bg-red-50 text-red-700 border-red-300'],
};
function statusLabel(s) { return (STATUS[s] || [s])[0]; }
function statusClass(s) { return (STATUS[s] || [s, 'bg-gray-100 text-gray-700 border-gray-300'])[1]; }
function statusPanelClass(s) {
  return { submitted: 'border-amber-200 bg-amber-50', approved: 'border-green-200 bg-green-50', rejected: 'border-red-200 bg-red-50', failed: 'border-red-200 bg-red-50' }[s] || 'border-gray-200';
}

const EVENTS = {
  'vms4.imported': 'Imported from vms4 (Zijia library) — details and photos copied to mark1',
  'vms4.updated': 'Updated from vms4 (changed in Zijia\'s portal)',
  'draft.created': 'Draft started',
  'draft.saved': 'Draft saved',
  'submit.sent': 'Sent to Zijia (sys.sku.sync.put)',
  'submit.accepted': 'Zijia accepted the application for review',
  'submit.refused': 'Zijia did not accept the application',
  'callback.approved': 'Zijia approved (callback received)',
  'callback.rejected': 'Zijia rejected (callback received)',
  'barcode.set': 'Barcode set on the product — on the AI\'s list now',
  'barcode.already_set': 'Barcode already on the product',
  'barcode.cleared': 'Barcode removed from the product',
  'barcode.conflict': 'Barcode NOT set — the product has a different one',
  'barcode.not_ours': 'Product barcode left unchanged',
  'barcode.not_in_library': 'Barcode NOT set — not found in Zijia\'s library',
  'barcode.library_unreachable': 'Barcode NOT set — Zijia\'s library did not answer',
};
function eventLabel(e) { return EVENTS[e] || e; }
function optionName(options, id) { return (options.find(o => o.id === id) || {}).name || '—'; }
</script>
