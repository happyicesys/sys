<template>
  <details class="-mx-3 sm:-mx-6 lg:-mx-8 rounded-md border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-900" :open="open" @toggle="remember">
    <summary class="cursor-pointer font-medium">{{ $t('How Grab menus and prices work') }}</summary>
    <ol class="mt-2 list-decimal space-y-1 pl-5">
      <li>{{ $t('Each Grab store sells what is in its machine. When a machine\'s products change, its Grab menu follows by itself.') }}</li>
      <li>{{ $t('Grab prices come from a price rule. The company rule covers every store. A store can have its own rule instead, for example a lower price at a quiet store.') }}</li>
      <li>{{ $t('A store uses its machine and its price rule only after it is switched to the machine menu. Until then Grab keeps showing its old menu. A new store added on Grab Stores starts on the machine menu once its operator has switched any store.') }}</li>
      <li>{{ $t('A product with no price or no Grab sub-category is left off Grab, never sold at $0. Those are listed under Products to fix.') }}</li>
    </ol>
  </details>
</template>

<script setup>
import { ref } from 'vue'

// Open until the user closes it once (per browser).
const KEY = 'grab-menu-how-it-works-closed'
const open = ref(read() !== '1')

function read() {
  try {
    return window.localStorage.getItem(KEY)
  } catch (e) {
    return null
  }
}
function remember(event) {
  try {
    window.localStorage.setItem(KEY, event.target.open ? '0' : '1')
  } catch (e) {
    // Private mode: it simply opens again next time.
  }
}
</script>
