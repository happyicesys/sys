<template>

  <Head title="Vending Machine Map" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Vending Machine Map
      </h2>
    </template>
      <!-- <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 "> -->
      <div class="overflow-hidden bg-white shadow sm:rounded-md">
          <div class="">
            <div class="mt-2 border-2 items-center justify-center py-3 pl-3 pr-4 text-sm space-y-3">
              <!-- <ul class="space-y-3">
                <li v-for="(child, childIndex) in item.children" :key="childIndex" class="overflow-hidden rounded-md bg-white px-6 py-4 shadow"> -->
                  <span class="font-medium px-2 py-4">
                    Vending Machine Map
                  </span>
                  <div>
                    <GoogleMap></GoogleMap>
                  </div>
                <!-- </li>
              </ul> -->
            </div>
          </div>
      </div>
      <!-- </div> -->
  </BreezeAuthenticatedLayout>
</template>

<script setup>
  import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
  import { Head } from '@inertiajs/vue3';
  import { ref, onMounted } from 'vue';
  import GoogleMap from '@/Components/GoogleMap.vue';

  const props = defineProps({
    vends: [Object, Array],
  });

  onMounted(() => {
    console.log(props.vends);
  });
</script>