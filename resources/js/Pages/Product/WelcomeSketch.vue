<template>
  <!--
    Product → Edit → Freezer welcome sketch and its album (ProductWelcomeSketchService /
    ProductWelcomeSketchController). Every drawing is kept: Draw adds new versions from the product
    photo, ☆ shortlists, Use puts one on the freezers, Upload adds your own and uses it. JSON calls
    only, so an unsaved product form above is never lost.
  -->
  <div class="shadow-sm ring-1 ring-black ring-opacity-5 p-5 mb-3 bg-white">
    <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-200 pb-3">
      <div class="min-w-0">
        <h3 class="text-base font-semibold text-gray-900">{{ $t('Freezer Welcome Sketch') }}</h3>
        <p class="text-xs text-gray-500">
          {{ $t('The drawing that falls to the polar bear on the smart freezer\'s welcome screen. Draw a few, star the good ones, and press') }}
          <span class="font-semibold">{{ $t('Use') }}</span> {{ $t('on the one the freezers should show.') }}
        </p>
      </div>
      <div v-if="canUpdate" class="flex flex-wrap items-center gap-2">
        <div class="inline-flex rounded-md shadow-sm">
          <button
            type="button"
            class="inline-flex items-center gap-1.5 rounded-l-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-700 focus:z-10 focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500 focus-visible:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="!canDraw"
            :title="drawBlockedReason || ''"
            @click="draw"
          >
            <SparklesIcon class="h-4 w-4" aria-hidden="true" />
            {{ drawCount === 1 ? $t('Draw a new one') : $t('Draw :count new', { count: drawCount }) }}
          </button>
          <label class="sr-only" for="welcome-sketch-count">{{ $t('How many drawings') }}</label>
          <select
            id="welcome-sketch-count"
            v-model.number="drawCount"
            class="rounded-r-md border-0 border-l border-indigo-400 bg-indigo-600 py-2 pl-2 pr-7 text-sm font-semibold text-white focus:ring-2 focus:ring-indigo-500 disabled:opacity-50"
            :disabled="!canDraw"
          >
            <option v-for="n in drawOptions" :key="n" :value="n">×{{ n }}</option>
          </select>
        </div>
        <label
          class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 shadow-sm hover:bg-gray-50 focus-within:ring-2 focus-within:ring-indigo-500"
          :class="{ 'pointer-events-none opacity-50': busy }"
          :title="$t('Your own transparent PNG/WebP — added to the album and used at once')"
        >
          <ArrowUpTrayIcon class="h-4 w-4" aria-hidden="true" />
          {{ $t('Upload & use') }}
          <input type="file" accept="image/png,image/webp" class="sr-only" @change="upload" />
        </label>
      </div>
    </div>

    <div v-if="canUpdate && state.ai_configured && state.has_photo" class="mt-3">
      <label for="welcome-sketch-instructions" class="flex items-baseline justify-between gap-2 text-xs font-semibold text-gray-700">
        <span>{{ $t('Instructions for the next drawing') }} <span class="font-normal text-gray-500">{{ $t('(optional)') }}</span></span>
        <span class="font-normal text-gray-400">{{ instructions.length }} / {{ maxInstructions }}</span>
      </label>
      <textarea
        id="welcome-sketch-instructions"
        v-model="instructions"
        rows="2"
        :maxlength="maxInstructions"
        class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm placeholder:text-gray-400 focus:border-indigo-500 focus:ring-indigo-500"
        :placeholder="$t('e.g. Text on the bar exactly “ICE BALL”, nothing else. No bite taken out. Keep the wooden stick.')"
      ></textarea>
      <p class="mt-1 text-[11px] text-gray-500">
        {{ $t('Sent with the drawings you make next and kept on each one in the album, so you can see what it was asked. Leave blank for the standard drawing.') }}
      </p>
    </div>

    <div v-if="!state.configured" class="mt-3 rounded border border-amber-300 bg-amber-50 p-2 text-xs text-amber-800">
      {{ $t('Automatic drawing is off on this server (no image service key and no background remover). You can still upload a sketch.') }}
    </div>
    <div v-else-if="!state.ai_configured" class="mt-3 rounded border border-sky-300 bg-sky-50 p-2 text-xs text-sky-800">
      {{ $t('No image-model key on this server, so drawings are photo cut-out stickers (background removed) instead of drawn sketches.') }}
    </div>
    <div v-else-if="!state.has_photo" class="mt-3 rounded border border-gray-300 bg-gray-50 p-2 text-xs text-gray-700">
      {{ $t('Add a product photo and save — sketches are drawn from it.') }}
    </div>
    <div v-if="error" class="mt-3 flex items-start justify-between gap-2 rounded border border-red-300 bg-red-50 p-2 text-sm text-red-800" role="alert">
      <span>{{ error }}</span>
      <button type="button" class="text-red-500 hover:text-red-700" :aria-label="$t('Dismiss')" @click="error = null">
        <XMarkIcon class="h-4 w-4" />
      </button>
    </div>

    <div class="mt-4 grid gap-6 md:grid-cols-[14rem_minmax(0,1fr)]">
      <!-- What the freezers drop now -->
      <section aria-labelledby="welcome-sketch-live">
        <h4 id="welcome-sketch-live" class="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-gray-500">
          <span class="h-2 w-2 rounded-full" :class="live ? 'bg-green-500' : 'bg-gray-300'" aria-hidden="true"></span>
          {{ $t('On the freezers now') }}
        </h4>
        <button
          type="button"
          class="mt-2 flex [aspect-ratio:1/1] w-full max-w-[14rem] items-center justify-center overflow-hidden rounded-lg bg-gradient-to-b from-sky-100 to-sky-200 ring-2 focus:outline-none focus-visible:ring-indigo-500"
          :class="live ? 'ring-green-500' : 'ring-sky-200'"
          :disabled="!liveVersion"
          :aria-label="live ? $t('Open the live sketch') : $t('No sketch yet')"
          @click="liveVersion && openViewer(liveVersion)"
        >
          <img v-if="live" :src="live.url" :alt="$t('Live welcome sketch')" class="max-h-[84%] max-w-[84%] object-contain drop-shadow" />
          <span v-else class="px-4 text-center text-xs text-sky-800">{{ $t('No sketch yet — the freezer drops the product photo.') }}</span>
        </button>
        <div v-if="live" class="mt-2 space-y-1 text-xs text-gray-600">
          <p>{{ sourceLabel(live.source) }} · {{ formatTime(live.generated_at) }}<span v-if="live.model"> · {{ live.model }}</span></p>
          <p v-if="live.selected_by" class="text-gray-500">{{ $t('Picked by :name, :time', { name: live.selected_by, time: formatTime(live.selected_at) }) }}</p>
          <p v-else-if="live.curated" class="text-gray-500">{{ $t('Approved art — kept until someone uses another.') }}</p>
          <p v-else class="text-gray-500">{{ $t('Went live automatically; a new product photo redraws it.') }}</p>
          <p v-if="live.photo_changed" class="text-amber-700">{{ $t('The product photo changed since this was drawn.') }}</p>
          <p v-if="live.source === 'cutout' && live.last_error" class="text-amber-700">{{ live.last_error }}</p>
        </div>
        <div v-if="state.photo_url" class="mt-3 flex items-center gap-2 text-xs text-gray-500">
          <img :src="state.photo_url" :alt="$t('Product photo')" class="h-10 w-10 shrink-0 rounded border border-gray-200 bg-white object-contain" />
          {{ $t('New drawings are made from this photo.') }}
        </div>
      </section>

      <!-- Album -->
      <section aria-labelledby="welcome-sketch-album" class="min-w-0">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h4 id="welcome-sketch-album" class="text-xs font-semibold uppercase tracking-wide text-gray-500">
            {{ $t('Album') }}
            <span v-if="state.in_flight" class="ml-1 normal-case tracking-normal font-normal text-indigo-700">· {{ $t('drawing :count…', { count: state.in_flight }) }}</span>
          </h4>
          <div class="inline-flex rounded-md bg-gray-100 p-0.5 text-xs" role="tablist" :aria-label="$t('Album filter')">
            <button
              v-for="tab in tabs"
              :key="tab.key"
              type="button"
              role="tab"
              :aria-selected="filter === tab.key"
              class="inline-flex items-center gap-1 rounded px-2.5 py-1 font-medium"
              :class="filter === tab.key ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-600 hover:text-gray-900'"
              @click="filter = tab.key"
            >
              <StarSolidIcon v-if="tab.key === 'shortlisted'" class="h-3.5 w-3.5 text-amber-400" aria-hidden="true" />
              {{ tab.label }}
              <span class="rounded-full bg-gray-200 px-1.5 text-[10px] text-gray-700">{{ tab.count }}</span>
            </button>
          </div>
        </div>
        <p class="mt-1 text-xs text-gray-500">
          {{ $t('New drawings land here; the freezers keep showing the live sketch until you press Use. A freezer picks up a change in about two minutes.') }}
        </p>

        <div v-if="!visible.length" class="mt-3 rounded-lg border border-dashed border-gray-300 p-6 text-center text-sm text-gray-500">
          <template v-if="filter === 'shortlisted'">{{ $t('Nothing starred yet. Tap ☆ on the drawings you like to compare them here.') }}</template>
          <template v-else>{{ $t('No drawings yet.') }}<span v-if="canDraw"> {{ $t('Press') }} <span class="font-semibold">{{ $t('Draw') }}</span> {{ $t('to make some from the product photo.') }}</span></template>
        </div>

        <ul v-else role="list" class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
          <li v-for="v in visible" :key="v.id" class="group">
            <div
              class="relative [aspect-ratio:1/1] overflow-hidden rounded-lg bg-gradient-to-b from-sky-50 to-sky-100 transition"
              :class="v.is_live ? 'ring-2 ring-green-500' : 'ring-1 ring-gray-200 hover:ring-indigo-400'"
            >
              <button
                v-if="v.status === 'ready'"
                type="button"
                class="absolute inset-0 flex items-center justify-center focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-indigo-500"
                :aria-label="$t('Open drawing from :time', { time: formatTime(v.at) })"
                @click="openViewer(v)"
              >
                <img :src="v.url" alt="" loading="lazy" class="max-h-[80%] max-w-[80%] object-contain" />
              </button>
              <div v-else-if="isPending(v)" class="absolute inset-0 flex flex-col items-center justify-center gap-2 text-xs text-indigo-700">
                <svg class="h-6 w-6 animate-spin text-indigo-500" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z" />
                </svg>
                {{ v.status === 'pending' ? $t('Queued') : $t('Drawing…') }}
              </div>
              <div v-else class="absolute inset-0 flex flex-col items-center justify-center gap-1 bg-red-50 p-2 text-center text-xs text-red-700">
                <ExclamationTriangleIcon class="h-5 w-5" aria-hidden="true" />
                {{ v.status === 'abandoned' ? $t('Stopped') : $t('Failed') }}
                <span v-if="v.last_error" class="line-clamp-3 text-[11px] text-red-600" :title="v.last_error">{{ v.last_error }}</span>
              </div>

              <span v-if="v.is_live" class="absolute left-1.5 top-1.5 rounded-full bg-green-600 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white shadow">{{ $t('Live') }}</span>
              <button
                v-if="canUpdate && v.status === 'ready'"
                type="button"
                class="absolute right-1.5 top-1.5 rounded-full bg-white/90 p-1 shadow-sm hover:bg-white focus:outline-none focus-visible:ring-2 focus-visible:ring-indigo-500"
                :aria-pressed="v.shortlisted"
                :aria-label="v.shortlisted ? $t('Remove star') : $t('Star (shortlist)')"
                :title="v.shortlisted ? $t('Remove star') : $t('Star (shortlist)')"
                @click.stop="toggleShortlist(v)"
              >
                <StarSolidIcon v-if="v.shortlisted" class="h-4 w-4 text-amber-400" />
                <StarOutlineIcon v-else class="h-4 w-4 text-gray-500" />
              </button>
              <span v-else-if="v.shortlisted" class="absolute right-1.5 top-1.5 rounded-full bg-white/90 p-1 shadow-sm">
                <StarSolidIcon class="h-4 w-4 text-amber-400" :aria-label="$t('Starred')" />
              </span>
              <div
                v-if="canUpdate && v.status === 'ready' && !v.is_live"
                class="absolute inset-x-1.5 bottom-1.5 transition sm:opacity-0 sm:group-hover:opacity-100 sm:group-focus-within:opacity-100"
              >
                <button
                  type="button"
                  class="w-full rounded-md bg-indigo-600 px-2 py-1 text-xs font-semibold text-white shadow hover:bg-indigo-700 disabled:opacity-50"
                  :disabled="busy"
                  @click.stop="select(v)"
                >
                  {{ $t('Use') }}
                </button>
              </div>
            </div>
            <div class="mt-1 flex items-center justify-between gap-1 text-[11px] leading-4 text-gray-500">
              <span class="flex min-w-0 items-center gap-1" :title="metaTitle(v)">
                <span class="truncate">{{ shortTime(v.at) }} · {{ sourceShort(v) }}</span>
                <ChatBubbleBottomCenterTextIcon v-if="v.instructions" class="h-3.5 w-3.5 shrink-0 text-indigo-500" :aria-label="$t('Drawn with instructions')" />
              </span>
              <button
                v-if="canUpdate && !v.is_live && !isPending(v)"
                type="button"
                class="shrink-0 rounded p-0.5 text-gray-400 hover:text-red-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-red-500"
                :aria-label="$t('Delete drawing')"
                :title="$t('Delete')"
                @click="remove(v)"
              >
                <TrashIcon class="h-3.5 w-3.5" />
              </button>
            </div>
          </li>
        </ul>
        <p v-if="state.versions.length >= albumLimit" class="mt-2 text-[11px] text-gray-400">{{ $t('Showing the newest :count.', { count: albumLimit }) }}</p>
      </section>
    </div>

    <!-- Viewer: large preview beside the product photo, ← → between drawings -->
    <Teleport to="body">
      <div
        v-if="viewing"
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/70 p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="welcome-sketch-viewer-title"
        @click.self="closeViewer"
      >
        <div class="flex max-h-full w-full max-w-3xl flex-col overflow-hidden rounded-xl bg-white shadow-2xl">
          <div class="flex items-center justify-between gap-2 border-b border-gray-200 px-4 py-3">
            <h3 id="welcome-sketch-viewer-title" class="flex items-center gap-2 text-sm font-semibold text-gray-900">
              {{ sourceLabel(viewing.source) }} · {{ formatTime(viewing.at) }}
              <span v-if="viewing.is_live" class="rounded-full bg-green-600 px-2 py-0.5 text-[10px] font-semibold uppercase text-white">{{ $t('Live') }}</span>
            </h3>
            <div class="flex items-center gap-3 text-xs text-gray-500">
              <span v-if="viewerList.length > 1">{{ viewerIndex + 1 }} / {{ viewerList.length }}</span>
              <button type="button" class="rounded p-1 text-gray-400 hover:text-gray-600" :aria-label="$t('Close')" @click="closeViewer">
                <XMarkIcon class="h-5 w-5" />
              </button>
            </div>
          </div>

          <div class="grid gap-4 overflow-y-auto p-4 sm:grid-cols-[minmax(0,1fr)_11rem]">
            <div class="relative flex [aspect-ratio:1/1] items-center justify-center rounded-lg bg-gradient-to-b from-sky-100 to-sky-200">
              <img :src="viewing.url" :alt="$t('Welcome sketch')" class="max-h-[86%] max-w-[86%] object-contain drop-shadow" />
              <template v-if="viewerList.length > 1">
                <button type="button" class="absolute left-2 top-1/2 -translate-y-1/2 rounded-full bg-white/90 p-1.5 shadow hover:bg-white" :aria-label="$t('Previous drawing')" @click="step(-1)">
                  <ChevronLeftIcon class="h-5 w-5 text-gray-700" />
                </button>
                <button type="button" class="absolute right-2 top-1/2 -translate-y-1/2 rounded-full bg-white/90 p-1.5 shadow hover:bg-white" :aria-label="$t('Next drawing')" @click="step(1)">
                  <ChevronRightIcon class="h-5 w-5 text-gray-700" />
                </button>
              </template>
            </div>
            <div class="space-y-3 text-xs text-gray-600">
              <div v-if="state.photo_url">
                <p class="mb-1 font-semibold text-gray-500">{{ $t('Product photo') }}</p>
                <img :src="state.photo_url" :alt="$t('Product photo')" class="[aspect-ratio:1/1] w-full rounded border border-gray-200 bg-white object-contain" />
              </div>
              <dl class="space-y-1">
                <div v-if="viewing.model"><dt class="inline text-gray-400">{{ $t('Model') }} </dt><dd class="inline">{{ viewing.model }}</dd></div>
                <div v-if="viewing.requested_by"><dt class="inline text-gray-400">{{ $t('By') }} </dt><dd class="inline">{{ viewing.requested_by }}</dd></div>
                <div v-if="viewing.instructions" class="rounded bg-indigo-50 p-2 text-indigo-900">
                  <dt class="font-semibold">{{ $t('Instructions') }}</dt>
                  <dd class="mt-0.5 whitespace-pre-line">{{ viewing.instructions }}</dd>
                  <button
                    v-if="canUpdate && state.ai_configured"
                    type="button"
                    class="mt-1 text-[11px] font-semibold text-indigo-700 underline hover:text-indigo-900"
                    @click="reuseInstructions(viewing)"
                  >
                    {{ $t('Use these for the next drawing') }}
                  </button>
                </div>
                <div v-if="viewing.from_current_photo !== null">
                  <dd v-if="viewing.from_current_photo">{{ $t('From the current photo') }}</dd>
                  <dd v-else class="text-amber-700">{{ $t('From an earlier photo') }}</dd>
                </div>
                <div v-if="viewing.last_error" class="text-amber-700">{{ viewing.last_error }}</div>
              </dl>
            </div>
          </div>

          <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 bg-gray-50 px-4 py-3">
            <button
              v-if="canUpdate && !viewing.is_live"
              type="button"
              class="inline-flex items-center gap-1 rounded-md px-2 py-1.5 text-sm text-red-600 hover:bg-red-50"
              @click="remove(viewing)"
            >
              <TrashIcon class="h-4 w-4" aria-hidden="true" /> {{ $t('Delete') }}
            </button>
            <span v-else></span>
            <div v-if="canUpdate" class="flex items-center gap-2">
              <button
                type="button"
                class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 hover:bg-gray-50"
                :aria-pressed="viewing.shortlisted"
                @click="toggleShortlist(viewing)"
              >
                <StarSolidIcon v-if="viewing.shortlisted" class="h-4 w-4 text-amber-400" aria-hidden="true" />
                <StarOutlineIcon v-else class="h-4 w-4 text-gray-500" aria-hidden="true" />
                {{ viewing.shortlisted ? $t('Starred') : $t('Star') }}
              </button>
              <span v-if="viewing.is_live" class="inline-flex items-center gap-1 rounded-md bg-green-50 px-3 py-1.5 text-sm font-medium text-green-700">
                <CheckCircleIcon class="h-4 w-4" aria-hidden="true" /> {{ $t('On the freezers') }}
              </span>
              <button
                v-else
                type="button"
                class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-700 disabled:opacity-50"
                :disabled="busy"
                @click="select(viewing)"
              >
                {{ $t('Use on the freezers') }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import axios from 'axios';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';
import {
  ArrowUpTrayIcon, ChatBubbleBottomCenterTextIcon, CheckCircleIcon, ChevronLeftIcon, ChevronRightIcon, ExclamationTriangleIcon,
  SparklesIcon, StarIcon as StarSolidIcon, TrashIcon, XMarkIcon,
} from '@heroicons/vue/20/solid';
import { StarIcon as StarOutlineIcon } from '@heroicons/vue/24/outline';
import { t, tc, tk } from '@/i18n';

const props = defineProps({
  productId: { type: Number, required: true },
  data: { type: Object, required: true },
});

const page = usePage();
const toast = useToast();
const state = ref(props.data);
const busy = ref(false);
const error = ref(null);
const filter = ref('all');
const drawCount = ref(1);
// Starts from what the last person's Draw asked, so a drawing can be refined step by step.
const instructions = ref(props.data.last_instructions ?? '');
const viewingId = ref(null);
const albumLimit = 80;

// A product save reloads the page props: take the server's state.
watch(() => props.data, (data) => { state.value = data; });

const maxInstructions = computed(() => state.value.max_instructions || 1000);
const canUpdate = computed(() => (page.props.auth?.permissions ?? []).includes('update products'));
const live = computed(() => state.value.live);
const versions = computed(() => state.value.versions ?? []);
const liveVersion = computed(() => versions.value.find((v) => v.is_live) ?? null);
const drawOptions = computed(() => Array.from({ length: state.value.max_per_draw || 1 }, (_, i) => i + 1));
const drawBlockedReason = computed(() => {
  if (!state.value.configured) return t('Nothing can draw on this server');
  if (!state.value.has_photo) return t('Add a product photo first');
  if (state.value.in_flight + drawCount.value > state.value.max_in_flight) return t('At most :count drawings at once', { count: state.value.max_in_flight });
  return null;
});
const canDraw = computed(() => !busy.value && !drawBlockedReason.value);

const tabs = computed(() => [
  { key: 'all', label: t('All'), count: versions.value.length },
  { key: 'shortlisted', label: t('Starred'), count: versions.value.filter((v) => v.shortlisted).length },
]);
const visible = computed(() => (filter.value === 'shortlisted' ? versions.value.filter((v) => v.shortlisted) : versions.value));

const viewerList = computed(() => visible.value.filter((v) => v.status === 'ready'));
const viewing = computed(() => versions.value.find((v) => v.id === viewingId.value && v.status === 'ready') ?? null);
const viewerIndex = computed(() => viewerList.value.findIndex((v) => v.id === viewingId.value));

function isPending(v) {
  return v.status === 'pending' || v.status === 'generating';
}

const SOURCE_LABELS = { seed: tk('Approved set'), generated: tk('Drawn'), cutout: tk('Photo cut-out'), upload: tk('Uploaded') };
function sourceLabel(source) {
  return t(SOURCE_LABELS[source] ?? tk('Sketch'));
}
function sourceShort(v) {
  if (isPending(v)) return t('drawing');
  if (v.status !== 'ready') return v.status;
  if (v.reason === 'recovered') return t('earlier');
  return { seed: t('approved'), generated: t('drawn'), cutout: t('cut-out'), upload: t('uploaded') }[v.source] ?? '';
}
function metaTitle(v) {
  return [sourceLabel(v.source), formatTime(v.at), v.model, v.requested_by && t('by :name', { name: v.requested_by }), v.instructions && `“${v.instructions}”`]
    .filter(Boolean).join(' · ');
}
function formatTime(iso) {
  return iso ? new Date(iso).toLocaleString() : '';
}
function shortTime(iso) {
  if (!iso) return '';
  const d = new Date(iso);
  return d.toLocaleDateString(undefined, { day: 'numeric', month: 'short' }) + ' '
    + d.toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' });
}

const base = computed(() => `/products/${props.productId}/welcome-sketch`);

async function call(method, url, payload, successMessage) {
  busy.value = true;
  error.value = null;
  try {
    const { data } = await axios({ method, url, data: payload });
    state.value = data;
    if (successMessage) toast.success(successMessage);
    return true;
  } catch (e) {
    const errors = e.response?.data?.errors;
    error.value = (errors && Object.values(errors)[0]?.[0]) || e.response?.data?.message || t('Could not save — try again.');
    return false;
  } finally {
    busy.value = false;
  }
}

function draw() {
  const n = drawCount.value;
  const asked = state.value.ai_configured ? instructions.value.trim() : '';
  call('post', `${base.value}/regenerate`, { count: n, instructions: asked || null },
    n === 1 ? t('Drawing — it appears in the album in about a minute.') : t('Drawing :count — they appear in the album in a minute or two.', { count: n }));
}

function reuseInstructions(v) {
  instructions.value = v.instructions ?? '';
  closeViewer();
  toast.info(t('Instructions copied — press Draw to use them.'));
}

function upload(event) {
  const file = event.target.files?.[0];
  event.target.value = '';
  if (!file) return;
  const form = new FormData();
  form.append('welcome_sketch', file);
  call('post', base.value, form, t('Uploaded — now on the freezers.'));
}

function select(v) {
  call('post', `${base.value}/versions/${v.id}/select`, {}, t('Now live — the freezers switch within about two minutes.'));
}

function toggleShortlist(v) {
  const on = !v.shortlisted;
  v.shortlisted = on; // instant feedback; the answer replaces it
  call('post', `${base.value}/versions/${v.id}/shortlist`, { shortlisted: on }).then((ok) => { if (!ok) v.shortlisted = !on; });
}

async function remove(v) {
  if (!window.confirm(t('Delete this drawing from the album? This cannot be undone.'))) return;
  if (viewingId.value === v.id) {
    const list = viewerList.value;
    const next = list[viewerIndex.value + 1] ?? list[viewerIndex.value - 1] ?? null;
    viewingId.value = next && next.id !== v.id ? next.id : null;
  }
  call('delete', `${base.value}/versions/${v.id}`, null, t('Drawing deleted.'));
}

// Viewer
function openViewer(v) {
  viewingId.value = v.id;
}
function closeViewer() {
  viewingId.value = null;
}
function step(delta) {
  const list = viewerList.value;
  if (!list.length) return;
  const i = (viewerIndex.value + delta + list.length) % list.length;
  viewingId.value = list[i].id;
}
function onKey(e) {
  if (!viewing.value) return;
  if (e.key === 'Escape') closeViewer();
  else if (e.key === 'ArrowLeft') step(-1);
  else if (e.key === 'ArrowRight') step(1);
}
watch(viewing, (v) => {
  if (v) window.addEventListener('keydown', onKey);
  else window.removeEventListener('keydown', onKey);
});

// While drawings are queued or being drawn, re-read the album every few seconds.
let pollTimer = null;
let pollStarted = 0;
function schedulePoll() {
  clearTimeout(pollTimer);
  pollTimer = null;
  if (!state.value.in_flight) {
    pollStarted = 0;
    return;
  }
  pollStarted ||= Date.now();
  if (Date.now() - pollStarted > 20 * 60 * 1000) return; // a stuck worker: stop asking
  pollTimer = setTimeout(async () => {
    const before = new Set(versions.value.filter((v) => v.status === 'ready').map((v) => v.id));
    try {
      const { data } = await axios.get(base.value);
      state.value = data;
      const fresh = (data.versions ?? []).filter((v) => v.status === 'ready' && !before.has(v.id)).length;
      if (fresh) toast.info(tc('A new drawing is in the album.|:count new drawings are in the album.', fresh));
    } catch (e) {
      // try again next tick
    }
    schedulePoll();
  }, 4000);
}
watch(() => state.value.in_flight, () => { if (!pollTimer) schedulePoll(); }, { immediate: true });

onBeforeUnmount(() => {
  clearTimeout(pollTimer);
  window.removeEventListener('keydown', onKey);
});
</script>
