<template>
  <th scope="col"
      class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-1 pr-1 text-center text-[12px] font-semibold text-gray-900 backdrop-blur-3xl backdrop-filter sm:pl-2 lg:pl-2">
      <div class="flex justify-center">
          <a href="#" class="text-blue-600 hover:text-blue-800" @click="$emit('sortTable', modelName)">
              <slot/>
          </a>
          <div class="pt-0.5 text-blue-600 hover:text-blue-800">
              <span v-if="sortKey === modelName && sortBy">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                </svg>
              </span>
              <span v-if="sortKey === modelName && !sortBy">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                </svg>
              </span>
          </div>
      </div>
  </th>
</template>

<script setup>

defineProps({
  modelName: String,
  sortKey: String,
  sortBy: {
      type: Boolean,
      default: true,
  },
})
</script>