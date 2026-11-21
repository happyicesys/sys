<template>
  <section class="-mx-3 sm:-mx-6 lg:-mx-8 bg-white rounded-md border px-3 py-3 text-sm">
    <h3 class="text-base font-semibold text-gray-800">{{ $t('Grab stores') }}</h3>
    <p class="mt-1 text-gray-600">{{ $t('Open a store to see its prices on Grab now and after a change, set its own price rule, or switch its menu. Tick several stores to change them together.') }}</p>

    <div class="mt-2 flex flex-wrap items-center gap-2">
      <input v-model="search" type="search" :placeholder="$t('Search machine, Site, Grab store or merchant ID')" class="w-full sm:w-80 rounded-md border-gray-300 text-sm" />
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
            <th v-if="can.update" rowspan="2" class="px-2 py-2 w-8">
              <input type="checkbox" :aria-label="$t('Tick every store shown')" :checked="shown.length > 0 && shown.every(store => picked.includes(store.ref_id))" @change="tickShown($event.target.checked)" />
            </th>
            <th rowspan="2" class="px-2 py-2">{{ $t('Machine') }}</th>
            <th rowspan="2" class="px-2 py-2">{{ $t('Site') }}</th>
            <th rowspan="2" class="px-2 py-2">
              {{ $t('Grab store') }}
              <a href="/grab-stores" class="ml-1 text-xs font-normal text-indigo-700 underline">{{ $t('Edit') }}</a>
            </th>
            <th rowspan="2" class="px-2 py-2">{{ $t('Grab menu') }}</th>
            <th rowspan="2" class="px-2 py-2">{{ $t('Price rule') }}</th>
            <th rowspan="2" class="px-2 py-2">
              {{ $t('On Grab') }}
              <a href="/grab-logs/menu-sync" class="ml-1 text-xs font-normal text-indigo-700 underline">{{ $t('Sync log') }}</a>
            </th>
            <th colspan="4" class="px-2 pt-2 pb-0 text-center whitespace-nowrap">{{ $t('Grab orders') }}</th>
            <th rowspan="2" class="px-2 py-2"></th>
          </tr>
          <tr class="text-right text-xs text-gray-600">
            <th class="px-2 pb-2 font-medium" :title="$t('Last 30 days')">{{ $t('L30d') }}</th>
            <th class="px-2 pb-2 font-medium" :title="$t('Last 7 days')">{{ $t('L7d') }}</th>
            <th class="px-2 pb-2 font-medium">{{ $t('Yest.') }}</th>
            <th class="px-2 pb-2 font-medium">{{ $t('Today') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="store in shown" :key="store.ref_id" class="align-top" :class="picked.includes(store.ref_id) ? 'bg-indigo-50' : ''">
            <td v-if="can.update" class="px-2 py-2"><input v-model="picked" type="checkbox" :value="store.ref_id" :aria-label="$t('Tick :code', { code: store.vend_code })" /></td>
            <td class="px-2 py-2"><MachineIdLink v-if="store.vend_code" :code="store.vend_code" /></td>
            <td class="px-2 py-2">
              <div>{{ store.site ?? '—' }} <span v-if="store.site_tier" class="ml-1 rounded bg-gray-100 px-1.5 text-xs text-gray-700" :title="$t('The Site\'s RP tier (Site → RP tier)')">RP{{ store.site_tier }}</span></div>
              <div class="font-mono text-xs text-gray-500">{{ store.merchant_id }}</div>
            </td>
            <td class="px-2 py-2 min-w-[12rem] max-w-xs text-xs">
              <template v-if="store.grab_profile">
                <div class="text-sm text-gray-900">{{ store.grab_profile.name ?? '—' }}</div>
                <div :class="store.grab_profile.match === false ? 'text-red-700' : 'text-gray-700'" :title="store.grab_profile.match === false ? $t('Grab\'s postal code is not the Site\'s: customers may see the wrong address.') : ''">{{ store.grab_profile.address ?? '—' }}</div>
                <div class="text-gray-400">{{ store.grab_profile.typed_by ? $t('Typed by :name :at', { name: store.grab_profile.typed_by, at: when(store.grab_profile.at) }) : $t('GrabMerchant website :at', { at: when(store.grab_profile.at) }) }}</div>
              </template>
              <a v-else :href="`/grab-stores?search=${encodeURIComponent(store.merchant_id ?? '')}`" class="text-indigo-700 underline">{{ $t('Not known yet: type it on Grab Stores') }}</a>
            </td>
            <td class="px-2 py-2 text-xs">
              <span v-if="store.machine_menu_since" class="whitespace-nowrap rounded bg-green-100 px-2 py-0.5 text-sm text-green-800">{{ $t('Machine menu') }}</span>
              <span v-else class="whitespace-nowrap rounded bg-gray-100 px-2 py-0.5 text-sm text-gray-700">{{ $t('Old Grab menu') }}</span>
              <div v-if="!store.selling" class="mt-1 text-gray-500">{{ $t('Not selling now') }}</div>
              <div class="mt-1 whitespace-nowrap text-gray-600">{{ changedLine('items', store.grab_times) }}</div>
              <div v-if="store.grab_times?.pulled_at" class="whitespace-nowrap text-gray-400">{{ $t('Grab last took the menu :at', { at: when(store.grab_times.pulled_at) }) }}</div>
            </td>
            <td class="px-2 py-2 text-xs">
              <template v-if="store.price_rule?.scope === 'store'">
                <span class="rounded bg-indigo-100 px-2 py-0.5 text-sm text-indigo-800">{{ $t('Own rule') }}</span>
                <div class="mt-1 text-gray-700">{{ ruleText(store.price_rule, rpTypes) }}</div>
                <div v-if="store.price_rule.note" class="italic text-gray-500">“{{ store.price_rule.note }}”</div>
                <div v-if="store.price_rule.updated_at" class="whitespace-nowrap text-gray-400">{{ setLine(store.price_rule.updated_at, store.price_rule.updated_by) }}</div>
              </template>
              <template v-else>
                <span class="text-sm text-gray-600">{{ $t('Company rule') }}</span>
                <div v-if="companyRule?.updated_at" class="whitespace-nowrap text-gray-400">{{ setLine(companyRule.updated_at, companyRule.updated_by) }}</div>
              </template>
              <div class="mt-1 whitespace-nowrap text-gray-600">{{ changedLine('prices', store.grab_times) }}</div>
            </td>
            <td class="px-2 py-2" :title="store.on_grab ? [store.on_grab.sync_summary, store.on_grab.last_message].filter(Boolean).join(' — ') : ''">
              <SyncStatus v-if="store.on_grab" :row="store.on_grab" compact />
              <div v-if="store.on_grab?.sync_at" class="mt-1 whitespace-nowrap text-xs text-gray-400">{{ $t('Last change :at by :name', { at: when(store.on_grab.sync_at), name: byName(store.on_grab.sync_by, store.on_grab.sync_kind) }) }}</div>
              <span v-else class="text-xs text-gray-400">{{ $t('Not checked yet') }}</span>
            </td>
            <td class="px-2 py-2 text-right">{{ store.orders?.d30 ?? store.orders_30d }}</td>
            <td class="px-2 py-2 text-right">{{ store.orders?.d7 ?? '' }}</td>
            <td class="px-2 py-2 text-right">{{ store.orders?.yesterday ?? '' }}</td>
            <td class="px-2 py-2 text-right">{{ store.orders?.today ?? '' }}</td>
            <td class="px-2 py-2 text-right"><button type="button" :class="secondaryClass" @click="$emit('open', store)">{{ $t('Open') }}</button></td>
          </tr>
          <tr v-if="!stores.length">
            <td :colspan="can.update ? 12 : 11" class="px-2 py-4 text-center text-gray-500">{{ $t('This operator has no Grab store on a machine.') }}</td>
          </tr>
          <tr v-else-if="!shown.length">
            <td :colspan="can.update ? 12 : 11" class="px-2 py-4 text-center text-gray-500">{{ $t('No store matches the search.') }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<script setup>
import MachineIdLink from '@/Components/MachineIdLink.vue'
import SyncStatus from './SyncStatus.vue'
import { computed, ref, watch } from 'vue'
import { matchesWords, ruleText, secondaryClass } from './priceRule'
import { when } from './syncStatus'
import { t } from '@/i18n'

const props = defineProps({
  stores: { type: Array, required: true },
  rpTypes: { type: Array, required: true },
  can: { type: Object, required: true },
  // The company rule (updated_at / updated_by): when a store on it had its price rule set.
  companyRule: { type: Object, default: null },
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
  return matchesWords(`${store.vend_code ?? ''} ${store.site ?? ''} ${store.merchant_id ?? ''} ${store.grab_profile?.name ?? ''} ${store.grab_profile?.address ?? ''}`, search.value)
}))

const pickedStores = computed(() => props.stores.filter(store => picked.value.includes(store.ref_id)))
const pickedOld = computed(() => pickedStores.value.filter(store => !store.machine_menu_since))
const pickedMachine = computed(() => pickedStores.value.filter(store => store.machine_menu_since))

// "Items changed on Grab 21 Nov 01:05". Tracking began 2026-11-21: a store Grab took since then with no change says so.
// "… by Brian": the change mark1 sent before Grab took it (none = it came another way, e.g. a mapping edit).
const CHANGED = {
  items: {
    at: 'items_changed_at',
    by: 'items_changed_by',
    changed: at => t('Items changed on Grab :at', { at }),
    changedBy: (at, name) => t('Items changed on Grab :at by :name', { at, name }),
    same: () => t('Items on Grab: no change seen yet'),
    unknown: () => t('Items on Grab: not known yet'),
  },
  prices: {
    at: 'prices_changed_at',
    by: 'prices_changed_by',
    changed: at => t('Prices changed on Grab :at', { at }),
    changedBy: (at, name) => t('Prices changed on Grab :at by :name', { at, name }),
    same: () => t('Prices on Grab: no change seen yet'),
    unknown: () => t('Prices on Grab: not known yet'),
  },
}
function changedLine(kind, times) {
  const line = CHANGED[kind]
  const at = times?.[line.at]
  const cause = times?.[line.by]
  if (at) return cause ? line.changedBy(when(at), byName(cause.by, cause.kind)) : line.changed(when(at))
  return times?.pulled_at ? line.same() : line.unknown()
}

// A person's name, else what the system did it as.
function byName(name, kind) {
  if (name) return name
  if (kind === 'nightly') return t('the nightly sync')
  if (kind === 'self_check' || kind === 'resend') return t('the system check')
  return t('the system')
}

function setLine(at, by) {
  return by ? t('Rule set :at by :name', { at: when(at), name: by }) : t('Rule set :at', { at: when(at) })
}

// Ticking every store shown ticks only those the search and filters show.
function tickShown(on) {
  const ids = shown.value.map(store => store.ref_id)
  picked.value = on ? [...new Set([...picked.value, ...ids])] : picked.value.filter(id => !ids.includes(id))
}
// A store that left the list (another operator, a reload) is unticked.
watch(() => props.stores, () => { picked.value = picked.value.filter(id => props.stores.some(store => store.ref_id === id)) })

defineExpose({ untickAll: () => { picked.value = [] } })
</script>
