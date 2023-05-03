<template>
  <th scope="col"
    class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-3 pr-3 text-center text-[13px] font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-2 lg:pl-2"
    :class="inputClass"
    >

    <slot/>
  </th>
</template>

<script setup>
defineProps({
  inputClass: String,
})
</script>