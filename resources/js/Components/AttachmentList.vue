<template>
  <ul role="list" class="z-50 divide-y divide-gray-100 overflow-hidden bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
    <li v-for="(item, itemIndex) in items" :key="item.id" class="relative z-50 flex flex-col md:flex-row justify-between gap-x-6 px-3 py-3 hover:bg-gray-50 sm:px-6 text-left md:text-center md:items-center">
      <div class="text-xs pt-4">
        {{ itemIndex + 1 }}
      </div>
      <div class="flex min-w-0 gap-x-4">
        <a :href="item.full_url" target="_blank">
          <template v-if="isVideo(item.full_url)">
            <video
              class="h-48 w-52 flex-none rounded-md bg-gray-50"
              :src="item.full_url"
              controls
            ></video>
          </template>
          <template v-else-if="isPdf(item.full_url)">
            <div class="flex items-center">
              <span class="text-sm text-gray-900 font-medium underline">
                {{ item.name || 'View PDF' }}
              </span>
            </div>
          </template>
          <template v-else>
            <img
              class="h-48 w-52 flex-none rounded-md bg-gray-50"
              :src="item.full_url"
              alt=""
            />
          </template>
        </a>
      </div>

      <div class="min-w-0 flex-auto self-center">
        <p class="text-sm leading-4 text-gray-900 pt-2">
          <input type="text"
                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md"
                v-model="item.name"
                v-if="item.show"
          />
          <a :href="item.imageUrl" target="_blank">
            <span v-if="!item.show">
              {{ item.name }}
            </span>
          </a>
        </p>
      </div>
      <span class="text-xs pt-3 text-gray-600 text-left md:text-center">
        {{ item.created_at_formatted ? item.created_at_formatted : formatDatetime(item.created_at) }}
      </span>
      <div class="flex space-x-1 md:space-x-2">
        <div class="flex shrink-0 items-center gap-x-4">
          <button type="button" class="rounded-full bg-gray-600 p-1.5 text-white shadow-sm hover:bg-gray-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-600"
          @click.prevent="showInput(itemIndex)"
          v-if="!item.show && isEditEnabled"
          >
            <PencilSquareIcon class="h-5 w-5" aria-hidden="true" />
          </button>
        </div>
        <div class="flex shrink-0 items-center gap-x-4">
          <button type="button" class="rounded-full bg-gray-500 p-1.5 text-white shadow-sm hover:bg-gray-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-500"
          @click.prevent="hideInput(itemIndex)"
          v-if="item.show && isEditEnabled"
          >
            <ArrowLeftCircleIcon class="h-5 w-5" aria-hidden="true" />
          </button>
          <button type="button" class="rounded-full bg-green-600 p-1.5 text-white shadow-sm hover:bg-green-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-600"
          @click.prevent="saveAttachment(itemIndex)"
          v-if="item.show && isEditEnabled"
          >
            <CheckCircleIcon class="h-5 w-5" aria-hidden="true" />
          </button>
        </div>
        <div class="flex shrink-0 items-center gap-x-4">
          <button type="button" class="rounded-full bg-red-600 p-1.5 text-white shadow-sm hover:bg-red-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600"
          @click.prevent="deleteAttachment(item.id)"
          v-if="isEditEnabled"
        >
            <XCircleIcon class="h-5 w-5" aria-hidden="true" />
          </button>
        </div>
      </div>
    </li>
    <li v-if="!items || !items.length">
      <div class="flex items-center justify-center py-4">
        <p class="text-sm text-gray-500">No attachments found</p>
      </div>
    </li>
  </ul>
</template>

<script setup>
import { ArrowLeftCircleIcon, CheckCircleIcon, PencilSquareIcon, XCircleIcon } from '@heroicons/vue/20/solid'
import { router } from '@inertiajs/vue3';
import { onMounted, ref, watch } from 'vue';
import { useToast } from "vue-toastification";

const props = defineProps({
  items: [Array, Object],
  isEditEnabled: {
    default: true,
    type: Boolean,
  }
})
const toast = useToast()

const items = ref(props.items)

watch(() => props.items, (newItems) => {
  items.value = newItems;
})

// onMounted(() => {
//   console.log(props.isEditEnabled)
// })

function isPdf(url) {
  const extension = url.split('.').pop().toLowerCase();
  return extension === 'pdf';
}


function isVideo(url) {
  const videoExtensions = ['mp4', 'webm', 'ogg', 'mkv'];
  const extension = url.split('.').pop().toLowerCase();
  return videoExtensions.includes(extension);
}


function saveAttachment(itemIndex) {
  router.post('/attachments/' + items.value[itemIndex].id + '/update', {
    name: items.value[itemIndex].name
  }, {
    preserveState: false,
    preserveScroll: true,
    replace: true,
  })
  items.value[itemIndex].show = false
}

function hideInput(itemIndex) {
  items.value[itemIndex].show = false
}

function showInput(itemIndex) {
  items.value[itemIndex].show = true
}

function deleteAttachment(id) {
  router.delete('/attachments/' + id, {
    onSuccess: () => {
      location.reload()
      toast.success("Successfully Saved", {
        timeout: 3000
      });
    },
    onError: (errors) => {
      toast.error("Failed, Please Try Again", {
        timeout: 3000
      });
    },
  })
}

function formatDatetime(datetime) {
  return datetime ? moment(datetime).format('YYYY-MM-DD hh:mm a') : ''
}

</script>
