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
        <div class="bg-white shadow sm:rounded-md border-t border-gray-100 pb-10 divide-y divide-gray-900" v-if="indexes.length > 0">
          <dl class="divide-y divide-gray-100 py-3 px-4">
            <div v-for="(parent, parentIndex) in indexes" class="py-2">
              <a :href="'#'+ parent.div" class="flex space-x-2 px-3 sm:grid sm:grid-cols-8 sm:gap-4 sm:px-6" v-if="parent.div">
                <dt class="text-lg font-medium text-blue-700 font-medium sm:col-span-1">
                  {{ parent.index }}
                </dt>
                <dd class="mt-1 text-lg leading-6 text-blue-700 sm:col-span-7 sm:mt-0 font-medium">
                  {{ parent.name }}
                </dd>
              </a>
              <span class="flex space-x-2 px-3 sm:grid sm:grid-cols-8 sm:gap-4 sm:px-6" v-else>
                <dt class="text-lg font-medium text-gray-700 font-medium sm:col-span-1">
                  {{ parent.index }}
                </dt>
                <dd class="mt-1 text-lg leading-6 text-gray-700 sm:col-span-7 sm:mt-0 font-medium">
                  {{ parent.name }}
                </dd>
              </span>
              <div v-for="(child, childIndex) in parent.children" v-if="parent.children">
                <div>
                  <a :href="'#'+ child.div" class="flex space-x-2 px-3 sm:grid sm:grid-cols-8 sm:gap-4 sm:px-6" v-if="child.div">
                    <dt class="mt-1 text-sm font-medium text-blue-800 sm:col-span-1 sm:mt-0">
                      {{ child.index }}
                    </dt>
                    <dd class="mt-1 text-sm leading-6 text-blue-800 sm:col-span-7 sm:mt-0">
                      {{ child.name }}
                    </dd>
                  </a>
                  <span class="flex space-x-2 px-3 sm:grid sm:grid-cols-8 sm:gap-4 sm:px-6" v-else>
                    <dt class="mt-1 text-sm font-medium text-gray-800 sm:col-span-1 sm:mt-0">
                      {{ child.index }}
                    </dt>
                    <dd class="mt-1 text-sm leading-6 text-gray-800 sm:col-span-7 sm:mt-0">
                      {{ child.name }}
                    </dd>
                  </span>
                </div>
              </div>
            </div>
          </dl>
        </div>
      <div class="overflow-hidden bg-white shadow sm:rounded-md mt-3">
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
            <div class="pt-2" v-if="item.children" >
              <div class="border-t border-gray-100" v-if="item.is_child_index_needed">
                <dl class="divide-y divide-gray-100">
                  <div v-for="(child, childIndex) in item.children">
                    <a :href="'#'+ child.div" class="px-4 py-2 sm:grid sm:grid-cols-4 sm:gap-4 sm:px-6">
                      <dt class="text-sm font-medium text-blue-700">
                        {{ child.sequence }}
                      </dt>
                      <dd class="mt-1 text-sm leading-6 text-blue-700 sm:col-span-2 sm:mt-0">
                        {{ child.name }}
                      </dd>
                    </a>
                  </div>
                </dl>
              </div>
              <div class="mt-2 border-2 items-center justify-center py-3 pl-3 pr-4 text-sm">
                <ul class="space-y-3">
                  <li v-for="(child, childIndex) in item.children" :key="childIndex" class="overflow-hidden rounded-md bg-white px-6 py-3 shadow">
                    <div :id="child.div">
                      <span class="font-medium">
                        {{ child.sequence }}. {{ child.name }}
                      </span>
                      <div class="aspect-w-9 aspect-h-16 md:aspect-w-16 md:aspect-h-9 mt-3">
                        <iframe :src="child.url" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture;" allowfullscreen></iframe>
                      </div>
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
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { PaperClipIcon } from '@heroicons/vue/20/solid';


const showModal = ref(false)
const contentIndex = ref()
const items = ref([
  {
    sequence: 1,
    name: 'Quick User Guide Model-F',
    filename: 'Quick User Guide-Model F_Rev1_0.pdf',
    url: 'https://happyice-space.sgp1.digitaloceanspaces.com/resource_centers/Quick%20User%20Guide-Model%20F_Rev1_0.pdf',
    div: '',
    file: 'pdf',
  },
  {
    sequence: 2,
    name: 'Vending Machine QAQC',
    is_child_index_needed: false,
    url: '',
    file: 'video',
    children: [
      {
        sequence: 2.1,
        name: 'QAQC System Walkthrough',
        url: '',
        div: 'qaqc-system-walkthrough',
      },
    ]
  },
  {
    sequence: 3,
    name: 'Content Management System (CMS)',
    is_child_index_needed: false,
    url: '',
    file: 'video',
    children: [
      {
        sequence: 3.1,
        name: 'CMS System Walkthrough',
        url: '',
        div: 'cms-system-walkthrough',
      },
    ]
  },
  {
    sequence: 4,
    name: 'Vending Machine System',
    is_child_index_needed: false,
    url: '',
    file: 'video',
    children: [
    {
        sequence: 4.1,
        name: 'Vending Machine System Walkthrough',
        url: 'https://www.youtube.com/embed/ekknbSI4df4',
        div: 'sys-system-walkthrough',
      },
      {
        sequence: 4.2,
        name: 'How to Create [User]',
        url: 'https://www.youtube.com/embed/BHTFBzhHZEQ',
        div: 'sys-how-to-create-user'
      },
      {
        sequence: 4.3,
        name: 'How to Create [New Machine]',
        url: 'https://www.youtube.com/embed/H7GyfGOlr0Q',
        div: 'sys-how-to-create-new-machine'
      },
      {
        sequence: 4.4,
        name: 'How to Create [Product]',
        url: 'https://www.youtube.com/embed/vMp9C7FVbsk',
        div: 'sys-how-to-create-product'
      },
      {
        sequence: 4.5,
        name: 'How to Create [Menu]',
        url: 'https://www.youtube.com/embed/z1FHeJnGyZo',
        div: 'sys-how-to-create-menu'
      },
      {
        sequence: 4.6,
        name: 'How to Check [Transactions] with Time Range',
        url: 'https://www.youtube.com/embed/8QFzQGQduOU',
        div: 'sys-how-to-check-transactions-with-time-range'
      },
    ]
  },
  {
    sequence: 5,
    name: 'Machine Explainer',
    is_child_index_needed: false,
    url: '',
    file: 'video',
    children: [
      {
        sequence: 5.1,
        name: 'Buying via Cash or Cashless',
        url: 'https://www.youtube.com/embed/Z5Oy7frSLyo',
        div: 'buying-via-cash-or-cashless'
      },
      {
        sequence: 5.2,
        name: 'Componenets Walkthrough',
        url: 'https://www.youtube.com/embed/GJULWifpwDg',
        div: 'componenets-walkthrough'
      },
      {
        sequence: 5.3,
        name: 'Replenishing Stock',
        url: 'https://www.youtube.com/embed/VNdcIWCwVsc',
        div: 'replenishing-stock'
      },
      {
        sequence: 5.4,
        name: 'Machine Installation',
        url: 'https://www.youtube.com/embed/tHkiLbKMqlA',
        div: 'machine-installation'
      },
    ]
  },
  {
    sequence: 6,
    name: 'TroubleShooting Videos',
    url: '',
    is_child_index_needed: false,
    file: 'video',
    children: [
      {
        sequence: 6.1,
        name: 'Adjust Spring Position',
        url: 'https://www.youtube.com/embed/TfCeurwG-Fc',
        div: 'adjust-spring-position'
      },
      {
        sequence: 6.2,
        name: 'Checking Evaporator',
        url: 'https://www.youtube.com/embed/sk2tGbfKRQs',
        div: 'checking-evaporator'
      },
      {
        sequence: 6.3,
        name: 'Checking Foam Door Tightness',
        url: 'https://www.youtube.com/embed/Wql18r7yffE',
        div: 'checking-foam-door-tightness'
      },
      {
        sequence: 6.4,
        name: 'Checking Freezer Fan',
        url: 'https://www.youtube.com/embed/2m8g6d_mCkI',
        div: 'checking-freezer-fan'
      },
      {
        sequence: 6.5,
        name: 'Checking Seal',
        url: 'https://www.youtube.com/embed/tYtMIugoMrk',
        div: 'checking-seal'
      },
      {
        sequence: 6.6,
        name: 'Clear Error Code 3',
        url: 'https://www.youtube.com/embed/KFcQ3Nah2dE',
        div: 'clear-error-code-3'
      },
      {
        sequence: 6.7,
        name: 'Clear Error Code 9',
        url: 'https://www.youtube.com/embed/T55WaxbapRY',
        div: 'clear-error-code-9'
      },
      {
        sequence: 6.8,
        name: 'Offline Issues',
        url: 'https://www.youtube.com/embed/zIoV7Hrp7Zk',
        div: 'offline-issues'
      },
      {
        sequence: 6.9,
        name: 'Sensor Disabled Checking',
        url: 'https://www.youtube.com/embed/FnhSZ2A96sU',
        div: 'sensor-disabled-checking'
      },
    ]
  }
])
const indexes = items.value
    // .filter((item) => ( item.children && item.children.length > 0 ))
    .map((item, index) => {
      return {
        index: item.sequence,
        name: item.name,
        div: item.div,
        children: item.children ? item.children.map((child, childIndex) => {
          return {
            index: child.sequence,
            name: child.name,
            div: child.div
          }
        }) :
        []
      }
    })

function onModalClose() {
  showModal.value = false
}

function showContent(index) {
  console.log('here')
  contentIndex.value = index
}
</script>