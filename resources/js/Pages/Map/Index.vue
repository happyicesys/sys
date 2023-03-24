<template>

    <Head title="Vending Machine Map" />

    <BreezeAuthenticatedLayout>
      <template #header>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Vending Machine Map
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


  </script>