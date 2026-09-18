<template>
  <!--
    Setting/Edit > Smart Freezer > "Camera" — the cabinet's own cameras, on demand.

    A photo is a normal `photo` command: mark1 asks the machine, the machine's app calls the Zijia
    host locally, uploads the still and the panel polls it back. So the newest picture appears a few
    seconds after the button, not instantly, and every shot stays in the machine's command timeline.
    The strip keeps the last few so a technician can compare "before I asked someone to restock" with
    "after" without leaving the page.
  -->
  <Modal :open="open" @modalClose="$emit('close')">
    <template #header><span class="font-semibold text-black">Cameras</span></template>
    <template #default>
      <div class="space-y-3">
        <div class="flex flex-wrap items-center gap-2" v-tooltip="blockedReason">
          <select v-model.number="cameraId" class="rounded-md border-gray-300 py-1.5 text-sm" :disabled="!canTake">
            <option v-for="cam in cameras" :key="cam.id" :value="cam.id">{{ cam.label }}</option>
          </select>
          <ControlButton tone="primary" class="rounded-md" :disabled="!canTake" @click="$emit('take', cameraId)">
            <ArrowPathIcon v-if="busy" class="mr-1 h-4 w-4 animate-spin" />
            {{ busy ? 'Taking photo…' : 'Take photo' }}
          </ControlButton>
          <span v-if="selected" class="text-xs text-gray-500">
            {{ viewName(selected.camera_id) }} · {{ formatTime(selected.taken_at) }}<template v-if="selected.by"> · {{ selected.by }}</template>
          </span>
        </div>

        <div class="flex min-h-[18rem] items-center justify-center rounded-lg bg-gray-900">
          <img v-if="selected" :src="selected.url" :alt="viewName(selected.camera_id)" class="max-h-[60vh] w-auto rounded-lg" />
          <p v-else class="px-6 py-16 text-center text-sm text-gray-300">
            No photo yet. Pick a camera and press Take photo — the machine answers in a few seconds.
          </p>
        </div>

        <!--
          One line per camera, newest on the left, so a technician compares a view with ITSELF
          over time instead of scanning a mixed strip for the two shots that belong together.
        -->
        <div v-if="photos.length" class="space-y-2">
          <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">History</p>
          <div v-for="row in rows" :key="row.cameraId" class="flex items-start gap-2">
            <span class="w-28 shrink-0 pt-1 text-xs font-medium text-gray-700">{{ row.label }}</span>
            <div class="flex flex-1 gap-2 overflow-x-auto pb-1">
              <button
                v-for="photo in row.photos"
                :key="photo.id"
                type="button"
                class="shrink-0 overflow-hidden rounded-md border-2 focus:outline-none"
                :class="photo.id === selectedId ? 'border-sky-600' : 'border-transparent hover:border-gray-300'"
                @click="selectedId = photo.id"
              >
                <img :src="photo.url" :alt="row.label" class="h-16 w-28 object-cover" />
                <span class="block bg-gray-50 px-1 py-0.5 text-[11px] text-gray-600">{{ formatTime(photo.taken_at) }}</span>
              </button>
              <span v-if="!row.photos.length" class="pt-5 text-xs text-gray-400">No photo yet.</span>
            </div>
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
  /** Which stored photo to show when the dialog opens; null = the newest. */
  initialPhotoId: { type: Number, default: null },
})

defineEmits(['take', 'close'])

const cameraId = ref(props.cameras[0]?.id ?? 0)
const selectedId = ref(null)

/** Follow the newest photo unless the technician picked an older one that is still in the list. */
watch(
  () => props.photos,
  (photos) => {
    if (!photos.length) {
      selectedId.value = null
    } else if (!photos.some((p) => p.id === selectedId.value)) {
      selectedId.value = photos[0].id
    }
  },
  { immediate: true, deep: true },
)

// Opening the dialog starts on the photo that was clicked, else the newest.
watch(() => props.open, (open) => {
  if (!open) return
  const wanted = props.initialPhotoId
  selectedId.value = (wanted && props.photos.some((p) => p.id === wanted)) ? wanted : (props.photos[0]?.id ?? null)
})

watch(() => props.cameras, (cameras) => {
  if (cameras.length && !cameras.some((c) => c.id === cameraId.value)) cameraId.value = cameras[0].id
}, { immediate: true })

const selected = computed(() => props.photos.find((p) => p.id === selectedId.value) || null)

/**
 * One row per camera — every camera the machine lists, even an unused one, plus any camera the
 * stored photos came from that the machine no longer reports. Photos arrive newest first and that
 * order is kept inside each row.
 */
const rows = computed(() => {
  const listed = props.cameras.map((cam) => ({
    cameraId: cam.id,
    label: cam.label || `cam ${cam.id}`,
    photos: props.photos.filter((p) => p.camera_id === cam.id),
  }))
  const known = new Set(props.cameras.map((c) => c.id))
  for (const photo of props.photos) {
    if (known.has(photo.camera_id)) continue
    known.add(photo.camera_id)
    listed.push({
      cameraId: photo.camera_id,
      label: `cam ${photo.camera_id}`,
      photos: props.photos.filter((p) => p.camera_id === photo.camera_id),
    })
  }
  return listed
})

/** The view name for a camera id, taken from the same list the picker uses. */
function viewName(id) {
  const i = props.cameras.findIndex((c) => c.id === id)
  return i >= 0 ? (props.cameras[i].label || `cam ${id}`) : `cam ${id}`
}

function formatTime(iso) {
  return iso ? moment(iso).format('DD MMM HH:mm') : ''
}
</script>
