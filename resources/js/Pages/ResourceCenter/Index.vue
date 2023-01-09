<template>

  <Head title="Payment Methods" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Resource Center
      </h2>
    </template>
    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <!-- <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 "> -->
      <div class="overflow-hidden bg-white shadow sm:rounded-md">
        <ul role="list" class="space-y-3">
          <li v-for="(item, index) in items" :key="index" class="overflow-hidden rounded-md bg-white px-6 py-4 shadow">
            <span class="font-lg font-medium">
              {{index + 1}}. {{ item.name }}
            </span>
            <div class="mt-2 border-2 flex items-center justify-between py-3 pl-3 pr-4 text-sm" v-if="item.url">
              <div class="flex w-0 flex-1 items-center">
                <PaperClipIcon class="h-5 w-5 flex-shrink-0 text-gray-400" aria-hidden="true" />
                <span class="ml-2 w-0 flex-1 truncate" v-if="item.filename">{{ item.filename }}</span>
              </div>
              <div class="ml-4 flex-shrink-0">
                <a :href="item.url" target="_blank" class="font-medium text-indigo-600 hover:text-indigo-500">Download</a>
              </div>
            </div>
            <div class="" v-if="item.children" >
              <div class="mt-2 border-2 items-center justify-center py-3 pl-3 pr-4 text-sm">
                <ul class="space-y-3">
                  <li v-for="(child, childIndex) in item.children" :key="childIndex" class="overflow-hidden rounded-md bg-white px-6 py-4 shadow">
                    <span class="font-medium">
                      {{ childIndex + 1 }}. {{ child.name }}
                    </span>
                    <div class="aspect-w-9 aspect-h-16 md:aspect-w-16 md:aspect-h-9">
                      <iframe :src="child.url" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture;" allowfullscreen></iframe>
                    </div>
                  </li>
                </ul>
              </div>
            </div>
          </li>
        </ul>
      </div>
      <!-- </div> -->
      </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import { Head } from '@inertiajs/inertia-vue3';
import { ref } from 'vue';
import { ChevronDoubleDownIcon, PaperClipIcon } from '@heroicons/vue/20/solid';


const showModal = ref(false)
const contentIndex = ref()
const items = ref([
  {
    index: 1,
    name: 'VM Training Agenda',
    filename: 'VM training agenda.pdf',
    url: 'https://happyice-space.sgp1.digitaloceanspaces.com/resource_centers/VM%20training%20agenda.pdf',
  },
  {
    index: 2,
    name: 'Quick User Guide Model-F',
    filename: 'Quick User Guide-Model F_Rev1_0.pdf',
    url: 'https://happyice-space.sgp1.digitaloceanspaces.com/resource_centers/Quick%20User%20Guide-Model%20F_Rev1_0.pdf',
  },
  {
    index: 3,
    name: 'Explainer Videos',
    url: '',
    children: [
      {
        sequence: 1,
        name: 'Buying via Cash or Cashless',
        url: 'https://www.youtube.com/embed/Z5Oy7frSLyo',
      },
      {
        sequence: 2,
        name: 'Componenets Walkthrough',
        url: 'https://www.youtube.com/embed/GJULWifpwDg',
      },
      {
        sequence: 3,
        name: 'Replenishing Stock',
        url: 'https://www.youtube.com/embed/VNdcIWCwVsc',
      },
      {
        sequence: 4,
        name: 'Machine Installation',
        url: 'https://www.youtube.com/embed/tHkiLbKMqlA',
      },
    ]
  },
  {
    index: 4,
    name: 'TroubleShooting Videos',
    url: '',
    children: [
      {
        sequence: 1,
        name: 'Adjust Spring Position',
        url: 'https://www.youtube.com/embed/TfCeurwG-Fc',
      },
      {
        sequence: 2,
        name: 'Checking Evaporator',
        url: 'https://www.youtube.com/embed/sk2tGbfKRQs',
      },
      {
        sequence: 3,
        name: 'Checking Foam Door Tightness',
        url: 'https://www.youtube.com/embed/Wql18r7yffE',
      },
      {
        sequence: 4,
        name: 'Checking Freezer Fan',
        url: 'https://www.youtube.com/embed/2m8g6d_mCkI',
      },
      {
        sequence: 5,
        name: 'Checking Seal',
        url: 'https://www.youtube.com/embed/tYtMIugoMrk',
      },
      {
        sequence: 6,
        name: 'Clear Error Code 3',
        url: 'https://www.youtube.com/embed/KFcQ3Nah2dE',
      },
      {
        sequence: 7,
        name: 'Clear Error Code 9',
        url: 'https://www.youtube.com/embed/T55WaxbapRY',
      },
      {
        sequence: 8,
        name: 'Offline Issues',
        url: 'https://www.youtube.com/embed/zIoV7Hrp7Zk',
      },
      {
        sequence: 9,
        name: 'Sensor Disabled Checking',
        url: 'https://www.youtube.com/embed/FnhSZ2A96sU',
      },
    ]
  }
])

function onModalClose() {
  showModal.value = false
}

function showContent(index) {
  console.log('here')
  contentIndex.value = index
}
</script>