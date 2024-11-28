<template>
  <ul role="list" class="z-50 divide-y divide-gray-100 overflow-visible bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
    <li v-for="(item, itemIndex) in items" :key="item.id">
      <div class="relative z-50 flex flex-col md:flex-row md:justify-between gap-x-6 px-3 py-3 hover:bg-gray-50 sm:px-6">
        <div class="text-xs pt-4 md:self-center">
          {{ itemIndex + 1 }}
        </div>
        <div class="flex min-w-0 gap-x-4">
          <a :href="item.full_url" target="_blank">
            <img class="h-48 w-52 flex-none rounded-md bg-gray-50" :src="item.full_url" alt="" />
          </a>
        </div>

        <div class="min-w-0 flex-auto md:self-center">
          <p class="text-sm leading-4 text-gray-900 pt-2">
            <div class="flex flex-col space-y-1" v-if="item.show">
              <FormInput v-model="item.name" v-if="item.show">
                Name
              </FormInput>
              <div>
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Ref Price
                </label>
                <MultiSelect
                  v-model="item.type"
                  :options="priceTypeOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
              </div>
            </div>
            <a :href="item.imageUrl" target="_blank">
              <span v-if="!item.show">
                <div class="flex space-x-2 items-center">
                  <div>
                    {{ item.name }}
                  </div>
                  <div
                      class="inline-flex justify-center items-center rounded px-0.5 py-0.5 text-xs border w-fit hover:cursor-pointer bg-indigo-100 text-indigo-800 border-indigo-300"
                      v-if="item.type"
                  >
                      <div class="flex space-x-1">
                          <span class="font-semibold grow-0">
                            RP{{ item.type }}
                          </span>
                      </div>
                  </div>
                </div>
              </span>
            </a>
          </p>
        </div>
        <span class="text-xs pt-3 pb-2 text-gray-600 md:self-center">
          {{ item.created_at_formatted ? item.created_at_formatted : formatDatetime(item.created_at) }}
        </span>
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
            <button type="button" class="rounded-full bg-gray-500 p-1.5 text-white shadow-sm hover:bg-gray-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gray-500"
            @click.prevent="hideInput(itemIndex)"
            v-if="item.show"
            >
              <ArrowLeftCircleIcon class="h-5 w-5" aria-hidden="true" />
            </button>
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
      </div>
    </li>
  </ul>
</template>

<script setup>
import { ArrowLeftCircleIcon, CheckCircleIcon, PencilSquareIcon, XCircleIcon } from '@heroicons/vue/20/solid'
import { router, Link, Head, usePage } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import FormInput from '@/Components/FormInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';

const props = defineProps({
  items: [Array, Object],
  priceTypeOptions: [Array, Object],
})

const items = ref(props.items)
// const priceTypeOptions = ref([])

// onMounted(() => {
//   priceTypeOptions.value = [
//     {id: '', name: '--- Clear ---' },
//     ...Object.entries(props.priceTypeOptions).map(([id, name]) => ({id: id, name: name}))
//   ]

// })

function saveAttachment(itemIndex) {
  router.post('/attachments/' + items.value[itemIndex].id + '/update', {
    name: items.value[itemIndex].name,
    type: items.value[itemIndex].type ? items.value[itemIndex].type.id : null
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
    onSuccess: (page) => {
      location.reload()
    },
  })
}

function formatDatetime(datetime) {
  return datetime ? moment(datetime).format('YYYY-MM-DD hh:mm a') : ''
}

</script>
