<template>
  <td :class="[currentIndex !== totalLength - 1 ? 'border-b border-gray-200' : '', 'whitespace-normal py-2 pl-2 pr-1 text-sm font-medium text-gray-800 sm:pl-1 lg:pl-2', inputClass]">
    <slot/>
  </td>
</template>

<script setup>

defineProps({
  inputClass: String,
  currentIndex: Number,
  totalLength: Number,
})
</script>