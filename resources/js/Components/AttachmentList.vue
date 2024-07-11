<template>
  <ul role="list" class="divide-y divide-gray-100 overflow-hidden bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
    <li v-for="(item, itemIndex) in items" :key="item.id" class="relative flex justify-between gap-x-6 px-3 py-3 hover:bg-gray-50 sm:px-6">
      <div class="text-xs pt-4">
        {{ itemIndex + 1 }}
      </div>
        <div class="flex min-w-0 gap-x-4">
          <a :href="item.full_url" target="_blank">
            <img class="h-48 w-52 flex-none rounded-md bg-gray-50" :src="item.full_url" alt="" />
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
      <span class="text-xs pt-3 text-gray-600 self-center">
        {{ item.created_at_formatted ? item.created_at_formatted : formatDatetime(item.created_at) }}
      </span>
      <div class="flex space-x-2">
        <div class="flex shrink-0 items-center gap-x-4">
          <button type="button" class="rounded-full bg-gray-600 p-1.5 text-white shadow-sm hover:bg-gray-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-600"
          @click.prevent="showInput(itemIndex)"
          v-if="!item.show"
          >
            <PencilSquareIcon class="h-5 w-5" aria-hidden="true" />
          </button>
        </div>
        <div class="flex shrink-0 items-center gap-x-4">
          <button type="button" class="rounded-full bg-green-600 p-1.5 text-white shadow-sm hover:bg-green-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-600"
          @click.prevent="saveAttachment(itemIndex)"
          v-if="item.show"
          >
            <CheckCircleIcon class="h-5 w-5" aria-hidden="true" />
          </button>
        </div>
        <div class="flex shrink-0 items-center gap-x-4">
          <button type="button" class="rounded-full bg-red-600 p-1.5 text-white shadow-sm hover:bg-red-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600"
          @click.prevent="deleteAttachment(item.id)"
        >
            <XCircleIcon class="h-5 w-5" aria-hidden="true" />
          </button>
        </div>
      </div>
    </li>
  </ul>
</template>

<script setup>
import { CheckCircleIcon, PencilSquareIcon, XCircleIcon } from '@heroicons/vue/20/solid'
import { router, Link, Head, usePage } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

const props = defineProps({
  items: [Array, Object],
})

const items = ref(props.items)

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

function showInput(itemIndex) {
  items.value[itemIndex].show = true
}

function deleteAttachment(id) {
  router.delete('/attachments/' + id, {
    onSuccess: (page) => {
      location.reload()
    },
  })
}

function formatDatetime(datetime) {
  return datetime ? moment(datetime).format('YYYY-MM-DD hh:mm a') : ''
}

</script>