<template>
  <th scope="col"
    :class="[
      'sticky top-0 border-b border-gray-300 py-3 pl-3 pr-3 text-center text-[11px] font-semibold text-gray-900 sm:pl-2 lg:pl-2',
      frozen
        ? 'z-30 bg-gray-100'
        : 'z-10 bg-gray-50 bg-opacity-75 backdrop-blur-3xl backdrop-filter',
      inputClass,
    ]"
    :style="frozen ? `left: ${frozenLeft}` : null"
    >

    <slot/>
  </th>
</template>

<script setup>
// Adds optional horizontal-freeze support on top of the existing
// vertical (sticky top-0) header. When `frozen=true`, the header
// cell ALSO sticks to the left, gets an opaque gray-100 bg (so
// content scrolling underneath doesn't show through), and is
// bumped to z-30 so it stays above both regular sticky headers
// (z-10) and frozen body cells (z-20). The translucent
// gray-50/opacity-75 + backdrop-blur look is intentionally
// preserved for the non-frozen default path.
defineProps({
  inputClass: String,
  frozen: { type: Boolean, default: false },
  // CSS length string (e.g. '0px', '50px') = cumulative left
  // offset of this frozen column. Ignored when frozen=false.
  frozenLeft: { type: String, default: '0px' },
})
</script>
