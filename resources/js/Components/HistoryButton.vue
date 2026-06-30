<template>
  <!-- Trigger: yellow history button -->
  <button
    type="button"
    @click="open"
    v-tooltip="id ? 'View change history for this record' : 'View recent change history'"
    class="inline-flex items-center gap-1 rounded-md border border-yellow-300 bg-yellow-100 px-2 py-2 text-xs font-medium text-yellow-800 shadow-sm hover:bg-yellow-200 focus:outline-none focus:ring-2 focus:ring-yellow-400"
  >
    <ClockIcon class="h-4 w-4" aria-hidden="true" />
    <span v-if="!iconOnly">{{ label }}</span>
  </button>

  <!-- Slide-over drawer -->
  <TransitionRoot as="template" :show="isOpen">
    <Dialog as="div" class="relative z-50" @close="isOpen = false">
      <TransitionChild as="template" enter="ease-in-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
        leave="ease-in-out duration-200" leave-from="opacity-100" leave-to="opacity-0">
        <div class="fixed inset-0 bg-gray-500/75 transition-opacity" />
      </TransitionChild>

      <div class="fixed inset-0 overflow-hidden">
        <div class="absolute inset-0 overflow-hidden">
          <div class="pointer-events-none fixed inset-y-0 right-0 flex max-w-full pl-10">
            <TransitionChild as="template" enter="transform transition ease-in-out duration-300"
              enter-from="translate-x-full" enter-to="translate-x-0"
              leave="transform transition ease-in-out duration-200"
              leave-from="translate-x-0" leave-to="translate-x-full">
              <DialogPanel class="pointer-events-auto w-screen max-w-md">
                <div class="flex h-full flex-col bg-white shadow-xl">

                  <!-- Header -->
                  <div class="flex items-start justify-between border-b border-gray-200 bg-yellow-50 px-4 py-4">
                    <DialogTitle class="flex items-center gap-2 text-base font-semibold text-gray-900">
                      <ClockIcon class="h-5 w-5 text-yellow-500" />
                      <span>
                        {{ title }}
                        <span v-if="id" class="block text-xs font-normal text-gray-500">{{ type }} #{{ id }}</span>
                        <span v-else class="block text-xs font-normal text-gray-500">All {{ type }} records</span>
                      </span>
                    </DialogTitle>
                    <button type="button" class="rounded-md text-gray-400 hover:text-gray-600" @click="isOpen = false">
                      <span class="sr-only">Close</span>
                      <XMarkIcon class="h-5 w-5" />
                    </button>
                  </div>

                  <!-- Body -->
                  <div class="flex-1 overflow-y-auto px-4 py-4">

                    <!-- Loading skeleton (first page only) -->
                    <div v-if="loading && entries.length === 0" class="space-y-3">
                      <div v-for="n in 5" :key="n" class="animate-pulse rounded-lg border border-gray-100 p-3">
                        <div class="h-3 w-1/3 rounded bg-gray-200"></div>
                        <div class="mt-2 h-3 w-2/3 rounded bg-gray-100"></div>
                      </div>
                    </div>

                    <!-- Empty -->
                    <div v-else-if="entries.length === 0"
                      class="flex flex-col items-center justify-center py-20 text-center text-sm text-gray-400">
                      <ClockIcon class="mb-2 h-8 w-8 text-gray-300" />
                      No history recorded yet.
                    </div>

                    <!-- Timeline -->
                    <ol v-else class="space-y-3">
                      <li v-for="entry in entries" :key="entry.id"
                        class="rounded-lg border border-gray-200 p-3 transition hover:border-yellow-300">
                        <div class="flex items-center justify-between">
                          <span :class="badgeClass(entry.event)"
                            class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium capitalize">
                            {{ entry.event }}
                          </span>
                          <time class="text-xs text-gray-500">{{ fmt(entry.created_at) }}</time>
                        </div>

                        <p class="mt-1 text-sm text-gray-700">
                          <span class="font-medium text-gray-900">{{ entry.who }}</span>
                          <span v-if="!id" class="text-gray-400"> · {{ entry.entity }}</span>
                        </p>

                        <!-- Field-level diff -->
                        <ul v-if="hasChanges(entry)" class="mt-2 space-y-0.5 border-t border-gray-100 pt-2">
                          <li v-for="(val, field) in entry.changes" :key="field" class="text-xs leading-5 text-gray-600">
                            <span class="font-medium text-gray-700">{{ field }}:</span>
                            <template v-if="isDiff(val)">
                              <span class="text-red-500 line-through">{{ display(val[0]) }}</span>
                              <span class="mx-1 text-gray-400">&rarr;</span>
                              <span class="text-green-600">{{ display(val[1]) }}</span>
                            </template>
                            <template v-else>
                              <span>{{ display(val) }}</span>
                            </template>
                          </li>
                        </ul>
                      </li>
                    </ol>

                    <!-- Load more (keyset) -->
                    <div v-if="nextBefore" class="mt-4 flex justify-center">
                      <button type="button" :disabled="loading" @click="loadMore"
                        class="inline-flex items-center gap-1 rounded-md border border-gray-300 bg-white px-4 py-2 text-xs font-medium text-gray-600 shadow-sm hover:bg-gray-50 disabled:opacity-50">
                        <ArrowPathIcon v-if="loading" class="h-4 w-4 animate-spin" />
                        {{ loading ? 'Loading…' : 'Load older history' }}
                      </button>
                    </div>

                  </div>
                </div>
              </DialogPanel>
            </TransitionChild>
          </div>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

<script setup>
/**
 * Reusable change-history button + slide-over drawer.
 *
 * Drop anywhere:
 *   <HistoryButton type="Customer" :id="customer.id" />   // one record (Edit page)
 *   <HistoryButton type="Customer" />                      // type-wide (Index toolbar)
 *
 * Data is fetched lazily on first open (nothing on page render), and older
 * pages load via keyset cursor for steady performance.
 */
import { ref } from 'vue';
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue';
import { ClockIcon, XMarkIcon, ArrowPathIcon } from '@heroicons/vue/24/outline';
import moment from 'moment';

const props = defineProps({
  type:     { type: String, required: true },          // model basename, e.g. "Customer"
  id:       { type: [Number, String], default: null }, // omit for type-wide history
  label:    { type: String, default: 'History' },
  title:    { type: String, default: 'Change history' },
  iconOnly: { type: Boolean, default: false },
});

const isOpen = ref(false);
const loading = ref(false);
const entries = ref([]);
const nextBefore = ref(null);
const loaded = ref(false);

function open() {
  isOpen.value = true;
  if (!loaded.value) fetchPage();
}

function fetchPage(before = null) {
  loading.value = true;
  const params = { type: props.type };
  if (props.id !== null && props.id !== undefined && props.id !== '') params.id = props.id;
  if (before) params.before = before;

  axios.get('/user-logs', { params })
    .then((res) => {
      entries.value = before ? entries.value.concat(res.data.data) : res.data.data;
      nextBefore.value = res.data.next_before;
      loaded.value = true;
    })
    .catch((err) => { console.error('Failed to load history', err); })
    .finally(() => { loading.value = false; });
}

function loadMore() {
  if (nextBefore.value) fetchPage(nextBefore.value);
}

function fmt(iso) {
  return iso ? moment(iso).format('YYMMDD hh:mm a') : '';
}

function hasChanges(entry) {
  return entry.changes && Object.keys(entry.changes).length > 0;
}
function isDiff(val) {
  return Array.isArray(val) && val.length === 2;
}
function display(v) {
  if (v === null || v === undefined || v === '') return '∅';
  if (typeof v === 'object') return JSON.stringify(v);
  return String(v);
}
function badgeClass(event) {
  return {
    created:  'bg-green-100 text-green-700',
    updated:  'bg-blue-100 text-blue-700',
    deleted:  'bg-red-100 text-red-700',
    restored: 'bg-purple-100 text-purple-700',
  }[event] || 'bg-gray-100 text-gray-700';
}
</script>
