<template>
  <td :class="[
      currentIndex !== totalLength - 1 ? 'border-b border-gray-200' : '',
      'whitespace-normal py-2 pl-1 pr-1 text-[13px] font-medium text-gray-800 sm:pl-1 lg:pl-1',
      frozen ? 'sticky z-[5]' : '',
      inputClass,
    ]"
    :style="frozen ? `left: ${frozenLeft}` : null"
  >
    <slot/>
  </td>
</template>

<script setup>
// Mirrors TableHead's frozen support: when `frozen=true`, the cell
// becomes horizontally sticky (sticky + left:N).
// z-[5] is deliberately BELOW the regular sticky header's z-10 — when
// the user scrolls vertically, the frozen cell should slide behind
// the sticky header, not float over it. It's still ABOVE the
// auto-z static regular cells, so horizontal scroll content slides
// underneath the freeze line correctly. (TableHead's frozen path
// uses z-30 so the top-left intersection sits above both.)
// IMPORTANT: a sticky cell needs an OPAQUE bg or scrolling
// content shows through. Callers must include a bg-* utility
// (e.g. bg-white or row-alternating bg-gray-100) via inputClass
// so the frozen cell can pick up the right row stripe colour.
defineProps({
  inputClass: String,
  currentIndex: Number,
  totalLength: Number,
  frozen: { type: Boolean, default: false },
  // CSS length string (e.g. '0px', '50px') = cumulative left
  // offset of this frozen column. Ignored when frozen=false.
  frozenLeft: { type: String, default: '0px' },
})
</script>
