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
        <h3 class="text-base font-semibold text-gray-900">{{ $t('Smart Freezer AI Training') }}</h3>
        <p class="text-xs text-gray-500">
          {{ $t('Submit this product to Zijia so the freezer\'s camera AI can recognise it. Once Zijia approves, its barcode is set here automatically and freezer sales of it are checked by the AI.') }}
        </p>
      </div>
      <div v-if="app" class="flex flex-wrap items-center gap-2 text-xs">
        <span class="rounded px-2 py-0.5 font-semibold border" :class="statusClass(app.status)">{{ statusLabel(app.status) }}</span>
        <span v-if="app.source === 'vms4'" class="rounded px-2 py-0.5 font-semibold border bg-indigo-50 text-indigo-700 border-indigo-300"
              :title="$t('Approved in Zijia\'s vms4 portal; details and photos mirrored from their library')">{{ $t('Imported from vms4') }}</span>
        <span class="text-gray-500">{{ $t('Application') }} <span class="font-mono">{{ app.application_no }}</span></span>
      </div>
    </div>

    <div v-if="!data.configured" class="mt-3 rounded border border-amber-300 bg-amber-50 p-2 text-xs text-amber-800">
      {{ $t('Zijia\'s algorithm service is not configured on this server, so nothing can be submitted.') }}
    </div>

    <!-- Where the application stands -->
    <div v-if="app && !app.editable" class="mt-3 rounded border p-3 text-sm space-y-1" :class="statusPanelClass(app.status)">
      <div v-if="app.status === 'submitted'">
        {{ $t('Waiting for Zijia\'s review — submitted :time', { time: app.submitted_at }) }}<span v-if="app.submitted_by"> {{ $t('by :name', { name: app.submitted_by }) }}</span>.
        {{ $t('The barcode') }} <span class="font-mono">{{ app.product_code }}</span> {{ $t('is set on the product when they approve.') }}
      </div>
      <div v-else-if="app.status === 'approved' && app.source === 'vms4'">
        {{ $t('Approved in Zijia\'s vms4 portal. Details and photos below are mirrored from their library (last changed there :time) and kept in step every 3 minutes.', { time: app.library_updated_at || $t('unknown') }) }}
        {{ $t('Barcode') }} <span class="font-mono">{{ app.product_code }}</span> {{ $t('is on the AI\'s list.') }}
        <span v-if="productBarcode !== app.product_code" class="block text-red-700">
          {{ $t('The product\'s barcode is :barcode — see the log below.', { barcode: productBarcode || $t('empty') }) }}
        </span>
      </div>
      <div v-else-if="app.status === 'approved'">
        {{ $t('Approved by Zijia :time.', { time: app.decided_at }) }} {{ $t('Barcode') }} <span class="font-mono">{{ app.product_code }}</span> {{ $t('is on the AI\'s list.') }}
        <span v-if="productBarcode !== app.product_code" class="block text-red-700">
          {{ $t('The product\'s barcode is :barcode — see the log below.', { barcode: productBarcode || $t('empty') }) }}
        </span>
      </div>
      <div v-else-if="app.status === 'rejected'">
        {{ $t('Rejected by Zijia :time:', { time: app.decided_at }) }} <span class="font-medium">{{ app.decision_msg || $t('no reason given') }}</span>
      </div>
      <div v-else-if="app.status === 'failed'">
        {{ $t('Zijia did not accept the submission:') }} <span class="font-medium">{{ app.last_error }}</span>
      </div>
      <div v-if="canEdit && app.status !== 'submitted'" class="pt-1">
        <Button type="button" class="bg-sky-700 hover:bg-sky-800 text-white disabled:opacity-50 disabled:cursor-not-allowed" :disabled="busy" @click="startNew">
          {{ $t('Start a new application') }}
        </Button>
        <span class="ml-2 text-xs text-gray-500">{{ $t('Copies this one into a new draft (new pack, photos, or a fix after rejection).') }}</span>
      </div>
    </div>

    <!-- What this application was sent / approved with (read-only) -->
    <div v-if="app && !app.editable" class="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-6 text-sm">
      <div class="sm:col-span-3"><span class="text-gray-500">{{ $t('Name for the AI:') }}</span> {{ app.sku_name }}</div>
      <div class="sm:col-span-3"><span class="text-gray-500">{{ $t('Barcode:') }}</span> <span class="font-mono">{{ app.product_code }}</span></div>
      <div class="sm:col-span-2"><span class="text-gray-500">{{ $t('Brand:') }}</span> {{ app.brand_name }}</div>
      <div class="sm:col-span-1"><span class="text-gray-500">{{ $t('Spec:') }}</span> {{ app.spec || '—' }}</div>
      <div class="sm:col-span-3"><span class="text-gray-500">{{ $t('Category / package:') }}</span> {{ optionName(data.categoryOptions, app.category) }} · {{ optionName(data.packageTypeOptions, app.package_type) }}</div>
      <div class="sm:col-span-6 flex flex-wrap gap-2 items-end">
        <div v-if="app.package_image_url" class="text-xs text-gray-500">
          <a :href="app.package_image_url" target="_blank"><img :src="app.package_image_url" class="h-24 w-24 rounded border border-gray-200 object-contain bg-white" alt="" /></a>
          {{ $t('package') }}
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
        <label class="block text-sm font-medium text-gray-700">{{ $t('Product name for the AI') }} <span class="text-red-600">*</span></label>
        <input v-model="form.sku_name" type="text" :disabled="!canEdit" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        <p class="mt-1 text-xs text-red-600" v-if="err('sku_name')">{{ err('sku_name') }}</p>
      </div>
      <div class="sm:col-span-3">
        <label class="block text-sm font-medium text-gray-700">{{ $t('Barcode') }} <span class="text-red-600">*</span></label>
        <input v-model="form.product_code" type="text" :disabled="!canEdit" :placeholder="$t('e.g. :example', { example: '8851932434300' })" class="mt-1 block w-full rounded-md border-gray-300 text-sm font-mono" />
        <p class="mt-1 text-xs text-gray-500">{{ $t('The barcode printed on the pack (EAN-13). If it has none, a unique code of ours. Set on the product only when Zijia approves.') }}</p>
        <p class="mt-1 text-xs text-red-600" v-if="err('product_code')">{{ err('product_code') }}</p>
      </div>
      <div class="sm:col-span-2">
        <label class="block text-sm font-medium text-gray-700">{{ $t('Brand') }} <span class="text-red-600">*</span></label>
        <input v-model="form.brand_name" type="text" :disabled="!canEdit" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
        <p class="mt-1 text-xs text-gray-500">{{ $t('其他/其他 when there is none.') }}</p>
        <p class="mt-1 text-xs text-red-600" v-if="err('brand_name')">{{ err('brand_name') }}</p>
      </div>
      <div class="sm:col-span-1">
        <label class="block text-sm font-medium text-gray-700">{{ $t('Spec') }}</label>
        <input v-model="form.spec" type="text" :disabled="!canEdit" placeholder="120克" class="mt-1 block w-full rounded-md border-gray-300 text-sm" />
      </div>
      <div class="sm:col-span-3 grid grid-cols-2 gap-3">
        <div>
          <label class="block text-sm font-medium text-gray-700">{{ $t('Category') }} <span class="text-red-600">*</span></label>
          <select v-model="form.category" :disabled="!canEdit" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            <option :value="null">{{ $t('Select') }}</option>
            <option v-for="o in data.categoryOptions" :key="o.id" :value="o.id">{{ o.name }}</option>
          </select>
          <p class="mt-1 text-xs text-red-600" v-if="err('category')">{{ err('category') }}</p>
        </div>
        <div>
          <label class="block text-sm font-medium text-gray-700">{{ $t('Package type') }} <span class="text-red-600">*</span></label>
          <select v-model="form.package_type" :disabled="!canEdit" class="mt-1 block w-full rounded-md border-gray-300 text-sm">
            <option :value="null">{{ $t('Select') }}</option>
            <option v-for="o in data.packageTypeOptions" :key="o.id" :value="o.id">{{ o.name }}</option>
          </select>
          <p class="mt-1 text-xs text-red-600" v-if="err('package_type')">{{ err('package_type') }}</p>
        </div>
      </div>

      <!-- Package photo -->
      <div class="sm:col-span-6">
        <label class="block text-sm font-medium text-gray-700">{{ $t('Package photo') }} <span class="text-red-600">*</span></label>
        <div class="mt-1 flex flex-wrap items-center gap-3">
          <a v-if="packageImageUrl" :href="packageImageUrl" target="_blank">
            <img :src="packageImageUrl" class="h-24 w-24 rounded border border-gray-200 object-contain bg-white" alt="" />
          </a>
          <span v-else class="text-xs text-gray-500">{{ $t('None yet — the product photo above is used by default.') }}</span>
          <div v-if="form.package_image" class="relative">
            <img :src="preview(form.package_image)" class="h-24 w-24 rounded border border-emerald-400 object-contain bg-white" alt="" />
            <span class="absolute bottom-0 left-0 right-0 bg-emerald-600 text-[10px] text-white text-center">{{ $t('new') }}</span>
          </div>
          <input v-if="canEdit" type="file" accept="image/jpeg,image/png,image/webp" @input="setPackageImage($event)" class="text-sm" />
        </div>
        <p class="mt-1 text-xs text-gray-500">{{ $t('The pack\'s main photo (front). Max 5 MB.') }}</p>
        <p class="mt-1 text-xs text-red-600" v-if="err('package_image_url') || err('package_image')">{{ err('package_image_url') || err('package_image') }}</p>
      </div>

      <p class="sm:col-span-6 rounded border border-sky-200 bg-sky-50 p-2 text-xs text-sky-900">
        {{ $t('Zijia needs every photo square (1:1). mark1 makes each one square when you save: the whole photo is kept and white space is added to the short side (nothing is cropped or stretched), up to :edge px. The previews show exactly what is sent — if a pack looks small in its square, take the photo closer.', { edge: data.square_edge || 2048 }) }}
      </p>

      <!-- Model photos per angle -->
      <div v-for="angle in angles" :key="angle.key" class="sm:col-span-6">
        <label class="block text-sm font-medium text-gray-700">
          {{ angle.label }} <span v-if="angle.required" class="text-red-600">*</span>
          <span class="ml-1 text-xs font-normal text-gray-500">{{ angle.hint }}</span>
        </label>
        <div class="mt-1 flex flex-wrap gap-2">
          <div v-for="url in existing(angle.key)" :key="url" class="relative">
            <a :href="url" target="_blank">
              <img :src="url" class="h-20 w-20 rounded border object-contain bg-white" :class="form.remove.includes(url) ? 'opacity-30 border-red-400' : 'border-gray-200'" alt="" />
            </a>
            <button v-if="canEdit" type="button" @click="toggleRemove(url)"
                    class="absolute -right-1 -top-1 rounded-full bg-white px-1 text-xs border"
                    :class="form.remove.includes(url) ? 'text-gray-600 border-gray-300' : 'text-red-600 border-red-300'"
                    :title="form.remove.includes(url) ? $t('Keep') : $t('Remove')">{{ form.remove.includes(url) ? '↺' : '✕' }}</button>
          </div>
          <div v-for="(file, i) in form.photos[angle.key]" :key="'new-' + i" class="relative">
            <img :src="preview(file)" class="h-20 w-20 rounded border border-emerald-400 object-contain bg-white" alt="" />
            <span class="absolute bottom-0 left-0 right-0 bg-emerald-600 text-[10px] text-white text-center">{{ $t('new') }}</span>
          </div>
        </div>
        <input v-if="canEdit" type="file" accept="image/jpeg,image/png,image/webp" multiple class="mt-1 text-sm" @input="addPhotos(angle.key, $event)" />
        <p class="mt-1 text-xs text-red-600" v-if="angle.required && err('model_pics.high')">{{ err('model_pics.high') }}</p>
        <p class="mt-1 text-xs text-red-600" v-if="photoError(angle.key)">{{ photoError(angle.key) }}</p>
      </div>

      <!-- What still blocks submission -->
      <div v-if="missingList.length" class="sm:col-span-6 rounded border border-amber-300 bg-amber-50 p-2 text-xs text-amber-900">
        <div class="font-semibold">{{ $t('Still needed before it can be submitted:') }}</div>
        <ul class="list-disc pl-5">
          <li v-for="m in missingList" :key="m">{{ m }}</li>
        </ul>
      </div>
      <div v-if="err('application')" class="sm:col-span-6 text-sm text-red-600">{{ err('application') }}</div>
      <div v-if="notice" class="sm:col-span-6 text-sm text-amber-700">{{ notice }}</div>

      <div v-if="canEdit" class="sm:col-span-6 flex flex-wrap justify-end gap-2">
        <Button type="button" class="bg-gray-600 hover:bg-gray-700 text-white disabled:opacity-50 disabled:cursor-not-allowed" :disabled="busy" @click="save">
          {{ busy ? $t('Saving…') : $t('Save draft') }}
        </Button>
        <Button type="button" class="bg-emerald-600 hover:bg-emerald-700 text-white disabled:opacity-40 disabled:cursor-not-allowed" :disabled="busy || !app || !data.configured || dirty || missingList.length > 0" @click="submit"
                :title="dirty ? $t('Save the draft first') : (missingList.length ? $t('Fill in the required fields first') : '')">
          {{ $t('Submit to Zijia') }}
        </Button>
      </div>
      <p v-if="canEdit && dirty" class="sm:col-span-6 text-right text-xs text-gray-500">{{ $t('Unsaved changes — save the draft, then submit.') }}</p>
    </div>

    <!-- Activity log: every exchange between mark1 and Zijia -->
    <div v-if="app && app.events.length" class="mt-4">
      <h4 class="text-sm font-semibold text-gray-800">{{ $t('Activity log — application :no', { no: app.application_no }) }}</h4>
      <ul class="mt-1 divide-y divide-gray-100 rounded border border-gray-200 text-xs">
        <li v-for="e in app.events" :key="e.id" class="px-2 py-1">
          <div class="flex flex-wrap items-center gap-2">
            <span class="font-mono text-gray-500">{{ e.at }}</span>
            <span class="font-semibold" :class="e.level === 'error' ? 'text-red-700' : (e.level === 'warning' ? 'text-amber-700' : 'text-gray-800')">{{ eventLabel(e.event) }}</span>
            <span v-if="e.user_name" class="text-gray-500">· {{ e.user_name }}</span>
          </div>
          <details v-if="e.detail" class="mt-0.5">
            <summary class="cursor-pointer text-gray-500">{{ $t('details') }}</summary>
            <pre class="mt-1 max-h-64 overflow-auto whitespace-pre-wrap break-all rounded bg-gray-50 p-2 text-[11px]">{{ JSON.stringify(e.detail, null, 2) }}</pre>
          </details>
        </li>
      </ul>
    </div>

    <!-- Earlier applications -->
    <div v-if="data.history.length > 1" class="mt-3 text-xs text-gray-600">
      <span class="font-semibold">{{ $t('Earlier applications:') }}</span>
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
import { t } from '@/i18n';

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
const notice = ref('');
// The server's max_file_uploads: PHP silently drops every file past it from one request.
const maxFiles = computed(() => props.data.max_files_per_save || 20);
const pendingFiles = computed(() => Object.values(form.value.photos).reduce((n, list) => n + list.length, 0) + (form.value.package_image ? 1 : 0));

const angles = [
  { key: 'high', short: t('top'), label: t('Model photos — top view'), required: true, hint: t('At least 1; Zijia suggests 5–10, from above like the freezer cameras, different sides and angles, background removed (see an approved product).') },
  { key: 'horizontal', short: t('side'), label: t('Model photos — side view'), required: false, hint: t('Optional.') },
  { key: 'low', short: t('low'), label: t('Model photos — low angle'), required: false, hint: t('Optional.') },
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
  notice.value = '';
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
  const picked = Array.from(event.target.files || []);
  const room = Math.max(0, maxFiles.value - pendingFiles.value);
  form.value.photos[angle] = [...form.value.photos[angle], ...picked.slice(0, room)];
  notice.value = picked.length > room
    ? t('Up to :max new photos per save — :skipped not added. Save these, then add the rest.', { max: maxFiles.value, skipped: picked.length - room })
    : '';
  event.target.value = '';
}
function setPackageImage(event) {
  const file = (event.target.files || [])[0] || null;
  if (!file) return;
  if (!form.value.package_image && pendingFiles.value >= maxFiles.value) {
    notice.value = t('Up to :max new photos per save — save first, then add the package photo.', { max: maxFiles.value });
  } else {
    form.value.package_image = file;
    notice.value = '';
  }
  event.target.value = '';
}
// Laravel reports a bad file as photos.<angle>.<n>; without this a refused file looked like a save that did nothing.
function photoError(angle) {
  const key = Object.keys(errors.value).find(k => k.startsWith(`photos.${angle}.`));
  return key ? errors.value[key] : null;
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
  if (!confirm(t('Submit ":name" (barcode :barcode) to Zijia for AI modelling?', { name: form.value.sku_name, barcode: form.value.product_code }))) return;
  post(`/products/${props.productId}/ai-training/submit`, {});
}

const STATUS = {
  draft: [t('Draft'), 'bg-gray-100 text-gray-700 border-gray-300'],
  submitted: [t('Waiting for Zijia'), 'bg-amber-50 text-amber-800 border-amber-300'],
  approved: [t('Approved'), 'bg-green-50 text-green-700 border-green-300'],
  rejected: [t('Rejected'), 'bg-red-50 text-red-700 border-red-300'],
  failed: [t('Not accepted'), 'bg-red-50 text-red-700 border-red-300'],
};
function statusLabel(s) { return (STATUS[s] || [s])[0]; }
function statusClass(s) { return (STATUS[s] || [s, 'bg-gray-100 text-gray-700 border-gray-300'])[1]; }
function statusPanelClass(s) {
  return { submitted: 'border-amber-200 bg-amber-50', approved: 'border-green-200 bg-green-50', rejected: 'border-red-200 bg-red-50', failed: 'border-red-200 bg-red-50' }[s] || 'border-gray-200';
}

const EVENTS = {
  'vms4.imported': t('Imported from vms4 (Zijia library) — details and photos copied to mark1'),
  'vms4.updated': t('Updated from vms4 (changed in Zijia\'s portal)'),
  'draft.created': t('Draft started'),
  'draft.saved': t('Draft saved'),
  'photos.squared': t('Photos made square (1:1) for Zijia'),
  'submit.sent': t('Sent to Zijia (sys.sku.sync.put)'),
  'submit.accepted': t('Zijia accepted the application for review'),
  'submit.refused': t('Zijia did not accept the application'),
  'callback.approved': t('Zijia approved (callback received)'),
  'callback.rejected': t('Zijia rejected (callback received)'),
  'barcode.set': t('Barcode set on the product — on the AI\'s list now'),
  'barcode.already_set': t('Barcode already on the product'),
  'barcode.cleared': t('Barcode removed from the product'),
  'barcode.conflict': t('Barcode NOT set — the product has a different one'),
  'barcode.not_ours': t('Product barcode left unchanged'),
  'barcode.not_in_library': t('Barcode NOT set — not found in Zijia\'s library'),
  'barcode.library_unreachable': t('Barcode NOT set — Zijia\'s library did not answer'),
};
function eventLabel(e) { return EVENTS[e] || e; }
function optionName(options, id) { return (options.find(o => o.id === id) || {}).name || '—'; }
</script>
