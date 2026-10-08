<template>
  <!--
    Product → Edit → Freezer welcome sketch (ProductWelcomeSketchService / ProductWelcomeSketchController).
    The drawing a smart freezer's welcome scene drops for this product instead of its photo. Drawn
    automatically from the product photo when the photo is saved; Regenerate redraws it, Upload
    replaces it with your own transparent PNG/WebP.
  -->
  <div class="shadow-sm ring-1 ring-black ring-opacity-5 p-5 mb-3 bg-white">
    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 pb-3">
      <div>
        <h3 class="text-base font-semibold text-gray-900">Freezer Welcome Sketch</h3>
        <p class="text-xs text-gray-500">
          The drawing that falls to the polar bear on the smart freezer's welcome screen. Without one, the freezer drops the
          product photo instead.
        </p>
      </div>
      <div v-if="sketch" class="flex flex-wrap items-center gap-2 text-xs">
        <span class="rounded px-2 py-0.5 font-semibold border" :class="statusClass">{{ statusLabel }}</span>
        <span v-if="sketch.source" class="rounded px-2 py-0.5 border bg-gray-50 text-gray-700 border-gray-300">{{ sourceLabel }}</span>
      </div>
    </div>

    <div v-if="!data.configured" class="mt-3 rounded border border-amber-300 bg-amber-50 p-2 text-xs text-amber-800">
      Automatic drawing is off on this server (no image service key and no background remover). You can still upload a sketch.
    </div>
    <div v-else-if="!data.ai_configured" class="mt-3 rounded border border-sky-300 bg-sky-50 p-2 text-xs text-sky-800">
      No image-model key on this server, so products get a photo cut-out sticker (background removed) instead of a drawn
      sketch. They are redrawn as sketches automatically once a key is added.
    </div>
    <div v-else-if="!data.has_photo" class="mt-3 rounded border border-gray-300 bg-gray-50 p-2 text-xs text-gray-700">
      Add a product photo and save — the sketch is drawn from it.
    </div>

    <div class="mt-3 flex flex-wrap items-start gap-5">
      <div class="h-40 w-40 shrink-0 rounded-md border border-sky-200 bg-sky-100 flex items-center justify-center overflow-hidden">
        <img v-if="sketch && sketch.url" :src="sketch.url" alt="Welcome sketch" class="max-h-36 max-w-36 object-contain" />
        <span v-else class="px-2 text-center text-xs text-sky-700">No sketch yet</span>
      </div>

      <div class="min-w-0 flex-1 space-y-2 text-sm">
        <p v-if="inFlight" class="text-indigo-700">Drawing from the product photo… it appears here in about a minute (refresh the page).</p>
        <p v-if="sketch && sketch.status === 'failed'" class="text-red-700">
          Last drawing failed: {{ sketch.last_error }}
          <span v-if="sketch.url" class="text-gray-600">(the previous sketch is still used)</span>
        </p>
        <p v-if="sketch && sketch.status === 'ready' && sketch.source === 'cutout' && sketch.last_error" class="text-amber-700">
          {{ sketch.last_error }}
        </p>
        <p v-if="sketch && sketch.photo_changed && !inFlight" class="text-amber-700">
          The photo changed since this sketch was drawn.
        </p>
        <p v-if="sketch && sketch.generated_at" class="text-xs text-gray-500">
          {{ { generated: 'Drawn', cutout: 'Cut out', seed: 'Stored', upload: 'Uploaded' }[sketch.source] || 'Saved' }}
          {{ formatTime(sketch.generated_at) }}<span v-if="sketch.model"> · {{ sketch.model }}</span>
        </p>

        <div class="flex flex-wrap items-center gap-2 pt-1">
          <Button
            v-if="canUpdate"
            type="button"
            class="bg-indigo-600 hover:bg-indigo-700 text-white flex space-x-1 disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="busy || inFlight || !data.configured || !data.has_photo"
            @click="regenerate"
          >
            {{ sketch && sketch.url ? 'Regenerate from photo' : 'Draw from photo' }}
          </Button>
          <label v-if="canUpdate" class="cursor-pointer rounded border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50">
            Upload sketch (PNG/WebP)
            <input type="file" accept="image/png,image/webp" class="hidden" @change="upload" />
          </label>
        </div>
        <p v-if="error" class="text-sm text-red-700">{{ error }}</p>
        <p class="text-xs text-gray-500">
          Regenerating replaces the current sketch only when the new drawing succeeds. An uploaded or approved sketch is never
          replaced automatically.
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import Button from '@/Components/Button.vue';
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

const props = defineProps({
  productId: { type: Number, required: true },
  data: { type: Object, required: true },
});

const page = usePage();
const busy = ref(false);
const error = ref(null);

const sketch = computed(() => props.data.sketch);
const inFlight = computed(() => ['pending', 'generating'].includes(sketch.value?.status));
const canUpdate = computed(() => (page.props.auth?.permissions ?? []).includes('update products'));

const statusLabel = computed(() => ({
  pending: 'Queued',
  generating: 'Drawing…',
  ready: 'Ready',
  failed: 'Failed',
}[sketch.value?.status] ?? sketch.value?.status));
const statusClass = computed(() => ({
  pending: 'bg-indigo-50 text-indigo-700 border-indigo-300',
  generating: 'bg-indigo-50 text-indigo-700 border-indigo-300',
  ready: 'bg-green-50 text-green-700 border-green-300',
  failed: 'bg-red-50 text-red-700 border-red-300',
}[sketch.value?.status] ?? 'bg-gray-50 text-gray-700 border-gray-300'));
const sourceLabel = computed(() => ({
  seed: 'Approved set',
  generated: 'Drawn automatically',
  cutout: 'Photo cut-out',
  upload: 'Uploaded',
}[sketch.value?.source] ?? sketch.value?.source));

function formatTime(iso) {
  return iso ? new Date(iso).toLocaleString() : '';
}

function send(url, payload) {
  busy.value = true;
  error.value = null;
  router.post(url, payload, {
    forceFormData: true,
    preserveScroll: true,
    onError: (e) => { error.value = e.welcome_sketch || Object.values(e)[0] || 'Could not save.'; },
    onFinish: () => { busy.value = false; },
  });
}

function regenerate() {
  send(`/products/${props.productId}/welcome-sketch/regenerate`, {});
}

function upload(event) {
  const file = event.target.files?.[0];
  if (!file) return;
  send(`/products/${props.productId}/welcome-sketch`, { welcome_sketch: file });
  event.target.value = '';
}
</script>
