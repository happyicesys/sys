<template>
  <!-- Floating footer for long Edit forms: the primary actions stay reachable
       without scrolling to the bottom. Fixed to the viewport bottom.

       The left edge is MEASURED from the layout's <main> column rather than
       hard-coded to a sidebar width: Authenticated.vue's sidebar is md:w-16 when
       collapsed (the default) and a fractional width when expanded, so any fixed
       Tailwind offset is wrong in one of the two states and leaves a gap where
       the bar looks cut off. A ResizeObserver on <main> follows the 200 ms
       collapse transition and window resizes.

       The bar also pads <main> by its own height so the last rows of the form
       and the copyright line are never hidden behind it; pages must not add
       their own pb-* for this. -->
  <div
    ref="bar"
    class="fixed bottom-0 right-0 z-40 border-t border-gray-200 bg-white/95 backdrop-blur shadow-[0_-2px_8px_rgba(0,0,0,0.08)] px-3 py-2 sm:px-6"
    :style="{ left: `${left}px` }"
  >
    <div class="flex flex-wrap items-center justify-between gap-2">
      <!-- Left: secondary / destructive actions -->
      <span class="flex flex-row flex-wrap gap-2">
        <slot name="left" />
      </span>
      <!-- Right: primary actions (Back, then Save) -->
      <span class="flex flex-row flex-wrap gap-2 ml-auto">
        <slot />
      </span>
    </div>
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'

const bar = ref(null)
const left = ref(0)

let main = null
let observer = null
let previousPaddingBottom = ''

function measure() {
  if (!bar.value) return
  left.value = main ? Math.max(0, main.getBoundingClientRect().left) : 0
  if (main) main.style.paddingBottom = `${bar.value.offsetHeight}px`
}

onMounted(() => {
  main = bar.value?.closest('main') ?? null
  if (main) previousPaddingBottom = main.style.paddingBottom
  measure()
  if (typeof ResizeObserver !== 'undefined') {
    observer = new ResizeObserver(measure)
    if (main) observer.observe(main)
    observer.observe(bar.value)
  }
  window.addEventListener('resize', measure)
})

onBeforeUnmount(() => {
  window.removeEventListener('resize', measure)
  observer?.disconnect()
  if (main) main.style.paddingBottom = previousPaddingBottom
})
</script>
