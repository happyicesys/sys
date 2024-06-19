<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600">
            Attachments
          </span>
        </div>
      </template>

      <template #default>
        <div class="bg-white py-5 sm:py-5 shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
          <div class="mx-auto max-w-7xl px-6 lg:px-8">
            <ul role="list" class="mx-auto mt-3 grid max-w-2xl max-h-3xl gap-x-8 gap-y-3 lg:mx-0 lg:max-w-none">
              <li v-for="(item, itemIndex) in items" :key="item.id" class="flex flex-col sm:justify-between space-y-2 sm:flex-col sm:space-x-2 sm:items-center">
                <a :href="item.full_url" target="_blank">
                  <img class="aspect-[3/2] w-full h-full rounded-2xl" :src="item.full_url" alt="" />
                </a>
                <span class="flex space-x-2">
                  <h4 class="text-lg font-normal leading-4 tracking-tight text-gray-800 flex justify-between">
                    <div class="w-full">
                      <p class="text-sm leading-4 text-gray-900 pt-2">
                        <input type="text"
                              class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block min-w-full text-sm border-gray-300 rounded-md"
                              v-model="item.name"
                              v-if="item.show"
                        />
                        <span v-if="!item.show">
                          {{ item.name }}
                        </span>
                      </p>
                    </div>
                  </h4>
                  <div class="flex space-x-1">
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
                      <div class="flex shrink-0 items-center gap-x-4" v-if="!item.show">
                        <button type="button" class="rounded-full bg-red-600 p-1.5 text-white shadow-sm hover:bg-red-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600"
                        @click.prevent="deleteAttachment(item.id)"
                      >
                          <XCircleIcon class="h-5 w-5" aria-hidden="true" />
                        </button>
                      </div>
                    </div>
                </span>
              </li>
            </ul>
          </div>
        </div>
      </template>
    </Modal>
  </Teleport>
</template>

<script setup>
import { CheckCircleIcon, PencilSquareIcon, XCircleIcon } from '@heroicons/vue/20/solid'
import Modal from '@/Components/Modal.vue';
import { router, Link, Head, usePage } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

const props = defineProps({
  items: [Array, Object],
  model: Object,
  showModal: Boolean,
})

const emit = defineEmits(['modalClose'])
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

</script>