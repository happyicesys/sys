<template>
  <section class="-mx-3 sm:-mx-6 lg:-mx-8 bg-white rounded-md border px-3 py-3 text-sm">
    <h3 class="text-base font-semibold text-gray-800">{{ $t('Grab stores') }}</h3>
    <p class="mt-1 text-gray-600">{{ $t('Open a store to see its prices on Grab now and after a change, set its own price rule, or switch its menu. Tick several stores to change them together.') }}</p>

    <div class="mt-2 flex flex-wrap items-center gap-2">
      <input v-model="search" type="search" :placeholder="$t('Search machine, Site or Grab merchant ID')" class="w-full sm:w-80 rounded-md border-gray-300 text-sm" />
      <select v-model="menuFilter" class="rounded-md border-gray-300 text-sm">
        <option value="all">{{ $t('All menus') }}</option>
        <option value="machine">{{ $t('Machine menu') }}</option>
        <option value="old">{{ $t('Old Grab menu') }}</option>
      </select>
      <select v-model="ruleFilter" class="rounded-md border-gray-300 text-sm">
        <option value="all">{{ $t('All price rules') }}</option>
        <option value="company">{{ $t('Company rule') }}</option>
        <option value="store">{{ $t('Own rule') }}</option>
      </select>
      <span v-if="filtered" class="text-xs text-gray-500">{{ $t(':shown of :total shown', { shown: shown.length, total: stores.length }) }}</span>
    </div>

    <div v-if="can.update && picked.length" class="mt-2 flex flex-wrap items-center gap-2 rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2">
      <span class="font-medium">{{ $tc(':count store ticked|:count stores ticked', picked.length, { count: picked.length }) }}</span>
      <button type="button" :class="secondaryClass" @click="$emit('setRule', pickedStores)">{{ $t('Set price rule…') }}</button>
      <button type="button" :class="secondaryClass" :disabled="!pickedOld.length" @click="$emit('switch', pickedOld, true)">{{ $tc('Switch :count to machine menu…', pickedOld.length, { count: pickedOld.length }) }}</button>
      <button type="button" :class="secondaryClass" :disabled="!pickedMachine.length" @click="$emit('switch', pickedMachine, false)">{{ $tc('Put :count back on old menu…', pickedMachine.length, { count: pickedMachine.length }) }}</button>
      <button type="button" class="text-indigo-700 underline" @click="picked = []">{{ $t('Untick all') }}</button>
    </div>

    <div class="mt-2 overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr class="text-left text-gray-700">
            <th v-if="can.update" class="px-2 py-2 w-8">
              <input type="checkbox" :aria-label="$t('Tick every store shown')" :checked="shown.length > 0 && shown.every(store => picked.includes(store.ref_id))" @change="tickShown($event.target.checked)" />
            </th>
            <th class="px-2 py-2">{{ $t('Machine') }}</th>
            <th class="px-2 py-2">{{ $t('Site') }}</th>
            <th class="px-2 py-2">{{ $t('Grab menu') }}</th>
            <th class="px-2 py-2">{{ $t('Price rule') }}</th>
            <th class="px-2 py-2 text-right whitespace-nowrap">{{ $t('Grab orders (30 days)') }}</th>
            <th class="px-2 py-2"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="store in shown" :key="store.ref_id" class="align-top" :class="picked.includes(store.ref_id) ? 'bg-indigo-50' : ''">
            <td v-if="can.update" class="px-2 py-2"><input v-model="picked" type="checkbox" :value="store.ref_id" :aria-label="$t('Tick :code', { code: store.vend_code })" /></td>
            <td class="px-2 py-2"><MachineIdLink v-if="store.vend_code" :code="store.vend_code" /></td>
            <td class="px-2 py-2">
              <div>{{ store.site ?? '—' }}</div>
              <div class="font-mono text-xs text-gray-500">{{ store.merchant_id }}</div>
            </td>
            <td class="px-2 py-2 whitespace-nowrap">
              <span v-if="store.machine_menu_since" class="rounded bg-green-100 px-2 py-0.5 text-green-800">{{ $t('Machine menu') }}</span>
              <span v-else class="rounded bg-gray-100 px-2 py-0.5 text-gray-700">{{ $t('Old Grab menu') }}</span>
              <div v-if="!store.selling" class="mt-1 text-xs text-gray-500">{{ $t('Not selling now') }}</div>
            </td>
            <td class="px-2 py-2">
              <template v-if="store.price_rule?.scope === 'store'">
                <span class="rounded bg-indigo-100 px-2 py-0.5 text-indigo-800">{{ $t('Own rule') }}</span>
                <div class="mt-1 text-xs text-gray-700">{{ ruleText(store.price_rule, rpTypes) }}</div>
                <div v-if="store.price_rule.note" class="text-xs italic text-gray-500">“{{ store.price_rule.note }}”</div>
              </template>
              <span v-else class="text-gray-600">{{ $t('Company rule') }}</span>
            </td>
            <td class="px-2 py-2 text-right">{{ store.orders_30d }}</td>
            <td class="px-2 py-2 text-right"><button type="button" :class="secondaryClass" @click="$emit('open', store)">{{ $t('Open') }}</button></td>
          </tr>
          <tr v-if="!stores.length">
            <td colspan="7" class="px-2 py-4 text-center text-gray-500">{{ $t('This operator has no Grab store on a machine.') }}</td>
          </tr>
          <tr v-else-if="!shown.length">
            <td colspan="7" class="px-2 py-4 text-center text-gray-500">{{ $t('No store matches the search.') }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<script setup>
import MachineIdLink from '@/Components/MachineIdLink.vue'
import { computed, ref, watch } from 'vue'
import { matchesWords, ruleText, secondaryClass } from './priceRule'

const props = defineProps({
  stores: { type: Array, required: true },
  rpTypes: { type: Array, required: true },
  can: { type: Object, required: true },
})
defineEmits(['open', 'switch', 'setRule'])

const search = ref('')
const menuFilter = ref('all')
const ruleFilter = ref('all')
const picked = ref([])

const filtered = computed(() => !!search.value.trim() || menuFilter.value !== 'all' || ruleFilter.value !== 'all')
const shown = computed(() => props.stores.filter(store => {
  if (menuFilter.value === 'machine' && !store.machine_menu_since) return false
  if (menuFilter.value === 'old' && store.machine_menu_since) return false
  if (ruleFilter.value !== 'all' && (store.price_rule?.scope ?? 'company') !== ruleFilter.value) return false
  return matchesWords(`${store.vend_code ?? ''} ${store.site ?? ''} ${store.merchant_id ?? ''}`, search.value)
}))

const pickedStores = computed(() => props.stores.filter(store => picked.value.includes(store.ref_id)))
const pickedOld = computed(() => pickedStores.value.filter(store => !store.machine_menu_since))
const pickedMachine = computed(() => pickedStores.value.filter(store => store.machine_menu_since))

// Ticking every store shown ticks only those the search and filters show.
function tickShown(on) {
  const ids = shown.value.map(store => store.ref_id)
  picked.value = on ? [...new Set([...picked.value, ...ids])] : picked.value.filter(id => !ids.includes(id))
}
// A store that left the list (another operator, a reload) is unticked.
watch(() => props.stores, () => { picked.value = picked.value.filter(id => props.stores.some(store => store.ref_id === id)) })

defineExpose({ untickAll: () => { picked.value = [] } })
</script>
