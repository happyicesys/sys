<template>
  <!--
    Setting/Edit > Smart Freezer > "Camera" — the cabinet's own cameras, on demand.

    A photo is a normal `photo` command: mark1 asks the machine, the machine's app calls the Zijia
    host locally, uploads the still and the panel polls it back. So the newest picture appears a few
    seconds after the button, not instantly, and every shot stays in the machine's command timeline.

    The dialog is ONE camera's window: the view you opened is the title, Take photo asks that same
    camera, and the strip below is that camera's own shots. There is no picker — a technician
    comparing baskets over time should not be able to leave the title and the history disagreeing.
  -->
  <Modal :open="open" @modalClose="$emit('close')">
    <template #header>
      <span class="font-semibold text-black">{{ viewName(cameraId) }}</span>
    </template>
    <template #default>
      <div class="space-y-3">
        <div class="flex flex-wrap items-center gap-2" v-tooltip="blockedReason">
          <ControlButton tone="primary" class="rounded-md" :disabled="!canTake" @click="$emit('take', cameraId)">
            <ArrowPathIcon v-if="busy" class="mr-1 h-4 w-4 animate-spin" />
            {{ busy ? 'Taking photo…' : 'Take photo' }}
          </ControlButton>
          <span v-if="selected" class="text-xs text-gray-500">
            {{ formatTime(selected.taken_at) }}<template v-if="selected.by"> · {{ selected.by }}</template>
          </span>
        </div>

        <div class="flex min-h-[18rem] items-center justify-center rounded-lg bg-gray-900">
          <img v-if="selected" :src="selected.url" :alt="viewName(cameraId)" class="max-h-[60vh] w-auto rounded-lg" />
          <p v-else class="px-6 py-16 text-center text-sm text-gray-300">
            No photo from this camera yet. Press Take photo — the machine answers in a few seconds.
          </p>
        </div>

        <!-- This view's own shots, newest first: the same camera over time, nothing else. -->
        <div v-if="history.length" class="space-y-1">
          <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
            History · last {{ history.length }}
          </p>
          <div class="flex gap-2 overflow-x-auto pb-1">
            <button
              v-for="photo in history"
              :key="photo.id"
              type="button"
              class="shrink-0 overflow-hidden rounded-md border-2 focus:outline-none"
              :class="photo.id === selectedId ? 'border-sky-600' : 'border-transparent hover:border-gray-300'"
              @click="selectedId = photo.id"
            >
              <img :src="photo.thumb_url || photo.url" :alt="viewName(cameraId)" class="h-16 w-28 object-cover" loading="lazy" />
              <span class="block bg-gray-50 px-1 py-0.5 text-[11px] text-gray-600">{{ formatTime(photo.taken_at) }}</span>
            </button>
          </div>
          <p class="text-xs text-gray-400">
            Stored with the machine's command history; open a photo from the timeline to download it.
          </p>
        </div>
      </div>
    </template>
  </Modal>
</template>

<script setup>
import Modal from '@/Components/Modal.vue'
import ControlButton from '@/Components/SmartFreezer/ControlButton.vue'
import { ArrowPathIcon } from '@heroicons/vue/20/solid'
import { computed, ref, watch } from 'vue'
import moment from 'moment'

const props = defineProps({
  open: { type: Boolean, default: false },
  /** Newest first, as the panel payload delivers them. */
  photos: { type: Array, default: () => [] },
  cameras: { type: Array, default: () => [] },
  canTake: { type: Boolean, default: false },
  busy: { type: Boolean, default: false },
  blockedReason: { type: String, default: '' },
  /** The one camera this window is about — its name is the title and Take photo asks it. */
  cameraId: { type: Number, default: 0 },
  /** Which stored photo to show when the dialog opens; null = that camera's newest. */
  initialPhotoId: { type: Number, default: null },
})

defineEmits(['take', 'close'])

const selectedId = ref(null)

/** This camera's own shots, newest first — the payload already arrives in that order. */
const history = computed(() => props.photos.filter((p) => p.camera_id === props.cameraId))

const selected = computed(() => history.value.find((p) => p.id === selectedId.value) || null)

/**
 * Keep the picture on this view: its newest shot, unless the technician is looking at an older one
 * of the SAME camera (then it stays put), or the window was reopened on another camera (then the
 * old selection is not in the list any more and the newest wins).
 */
watch(
  history,
  (now, before) => {
    const stillShown = now.some((p) => p.id === selectedId.value)
    const wasFollowingNewest = before?.[0]?.id === selectedId.value
    if (!stillShown || wasFollowingNewest) selectedId.value = now[0]?.id ?? null
  },
  { immediate: true },
)

// Opening lands on the thumbnail that was clicked, else on this camera's newest shot.
watch(() => props.open, (open) => {
  if (!open) return
  const wanted = history.value.find((p) => p.id === props.initialPhotoId)
  selectedId.value = wanted ? wanted.id : (history.value[0]?.id ?? null)
})

/** The view name for a camera id, taken from the same list the panel's picker uses. */
function viewName(id) {
  const i = props.cameras.findIndex((c) => c.id === id)
  return i >= 0 ? (props.cameras[i].label || `cam ${id}`) : `cam ${id}`
}

function formatTime(iso) {
  return iso ? moment(iso).format('DD MMM HH:mm') : ''
}
</script>
