<template>
  <!-- A strip of photos / videos / PDFs with one "add" tile. Built for a phone:
       a plain file input opens the camera or the gallery, one file per tap. -->
  <div class="flex flex-wrap gap-2">
    <div v-for="file in files" :key="file.id" class="relative">
      <a :href="file.full_url" target="_blank" rel="noopener noreferrer">
        <video v-if="isVideo(file.full_url)" :src="file.full_url" class="h-20 w-20 rounded-md object-cover bg-gray-100" muted playsinline></video>
        <div v-else-if="isPdf(file.full_url)" class="h-20 w-20 rounded-md bg-gray-100 border border-gray-300 flex items-center justify-center text-xs font-semibold text-gray-700">PDF</div>
        <img v-else :src="file.full_url" class="h-20 w-20 rounded-md object-cover bg-gray-100" alt="" loading="lazy" />
      </a>
      <button
        v-if="editable"
        type="button"
        class="absolute -top-2 -right-2 bg-red-500 hover:bg-red-600 text-white rounded-full p-0.5 shadow"
        title="Remove"
        @click="remove(file)"
      >
        <XMarkIcon class="h-4 w-4" />
      </button>
    </div>

    <label
      v-if="editable"
      class="h-20 w-20 rounded-md border-2 border-dashed border-gray-300 hover:border-gray-400 flex flex-col items-center justify-center text-gray-500 cursor-pointer"
      :class="{ 'opacity-50 cursor-wait': uploading }"
    >
      <CameraIcon class="h-6 w-6" />
      <span class="text-[10px] leading-tight">{{ uploading ? 'Uploading…' : 'Add' }}</span>
      <input type="file" class="hidden" accept="image/*,video/*,application/pdf" :disabled="uploading" @change="upload" />
    </label>
  </div>
  <div class="text-xs text-red-600 mt-1" v-if="error">{{ error }}</div>
</template>

<script setup>
import { CameraIcon, XMarkIcon } from '@heroicons/vue/20/solid'
import { ref } from 'vue'
import { firstErrors } from './stopTypes'

const MAX_BYTES = 20 * 1024 * 1024 // the server's limit; say so before a slow mobile upload, not after

const props = defineProps({
  files: { type: Array, default: () => [] },
  uploadUrl: String,
  deleteUrl: Function, // (file) => url
  extraFields: { type: Object, default: () => ({}) },
  editable: Boolean,
})
const emit = defineEmits(['changed'])

const uploading = ref(false)
const error = ref('')

const isVideo = (url) => /\.(mp4|mov|webm|qt)(\?|$)/i.test(url || '')
const isPdf = (url) => /\.pdf(\?|$)/i.test(url || '')

function upload(event) {
  const file = event.target.files?.[0]
  event.target.value = '' // let the same file be chosen again
  if (!file) return

  error.value = ''
  if (file.size > MAX_BYTES) {
    error.value = 'That file is over 20 MB.'
    return
  }

  const body = new FormData()
  body.append('file', file)
  Object.entries(props.extraFields).forEach(([key, value]) => body.append(key, value))

  uploading.value = true
  axios.post(props.uploadUrl, body)
    .then(({ data }) => emit('changed', data))
    .catch(e => { error.value = Object.values(firstErrors(e))[0] })
    .finally(() => { uploading.value = false })
}

function remove(file) {
  if (!confirm('Remove this file?')) return
  error.value = ''
  axios.delete(props.deleteUrl(file))
    .then(({ data }) => emit('changed', data))
    .catch(e => { error.value = Object.values(firstErrors(e))[0] })
}
</script>
