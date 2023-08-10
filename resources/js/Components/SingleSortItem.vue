<template>
  <div class="flex justify-center">
      <a href="#" class="text-blue-600 hover:text-blue-800" @click="$emit('sortTable', modelName)">
          <slot/>
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
      </a>
  </div>
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