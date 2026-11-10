<template>
  <section class="-mx-3 sm:-mx-6 lg:-mx-8 bg-white rounded-md border px-3 py-3 text-sm">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h3 class="text-base font-semibold text-gray-800">{{ $t('Products') }}</h3>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="chip in chips"
          :key="chip.key"
          type="button"
          class="rounded-full border px-3 py-1"
          :class="filter === chip.key ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
          @click="filter = chip.key"
        >{{ chip.name }} <span class="text-gray-500">{{ chip.count }}</span></button>
      </div>
    </div>
    <p class="mt-1 text-gray-600">{{ $t('A product\'s Grab sub-category is the same at every store. Prices shown here are under the company rule; open a store to see its own prices.') }}</p>
    <p v-if="problemCount" class="mt-2 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-amber-800">
      {{ $tc(':count product is in a machine but not on Grab: give it what the Status column asks for.|:count products are in machines but not on Grab: give them what the Status column asks for.', problemCount, { count: problemCount }) }}
    </p>
    <p v-else class="mt-2 rounded-md bg-green-50 border border-green-200 px-3 py-2 text-green-800">{{ $t('Every product in the machines is on Grab. Nothing to fix.') }}</p>

    <div class="mt-2 flex flex-wrap items-center gap-2">
      <input v-model="search" type="search" :placeholder="$t('Search product code or name')" class="w-full sm:w-72 rounded-md border-gray-300 text-sm" />
      <span v-if="search.trim()" class="text-xs text-gray-500">{{ $t(':shown of :total shown', { shown: shown.length, total: chipProducts.length }) }}</span>
    </div>

    <div v-if="can.update && picked.length" class="mt-2 flex flex-wrap items-center gap-2 rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2">
      <span>{{ $tc(':count product ticked|:count products ticked', picked.length, { count: picked.length }) }}</span>
      <select v-model="bulkSub" class="rounded-md border-gray-300 text-sm">
        <option :value="null">{{ $t('Sub-category…') }}</option>
        <option v-for="sub in subCategoryChoices" :key="sub.id" :value="sub.id">{{ sub.name }}</option>
      </select>
      <button type="button" :class="primaryClass" :disabled="!bulkSub || busy" @click="applyBulk">{{ $t('Set on ticked products') }}</button>
      <button type="button" class="text-indigo-700 underline" @click="picked = []">{{ $t('Untick all') }}</button>
    </div>

    <div class="mt-2 overflow-x-auto">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr class="text-left text-gray-700">
            <th v-if="can.update" class="px-2 py-2"><input type="checkbox" :aria-label="$t('Tick every product shown')" :checked="shown.length > 0 && picked.length === shown.length" @change="picked = $event.target.checked ? shown.map(p => p.product_id) : []" /></th>
            <th class="px-2 py-2">{{ $t('Product') }}</th>
            <th class="px-2 py-2 text-right">{{ $t('In machines') }}</th>
            <th class="px-2 py-2">{{ $t('Grab sub-category') }}</th>
            <th class="px-2 py-2 text-right">{{ rp }}</th>
            <th class="px-2 py-2 text-right whitespace-nowrap">{{ $t('Grab price (company rule)') }}</th>
            <th class="px-2 py-2">{{ $t('Status') }}</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
          <tr v-for="product in shown" :key="product.product_id" class="align-middle">
            <td v-if="can.update" class="px-2 py-2"><input v-model="picked" type="checkbox" :value="product.product_id" /></td>
            <td class="px-2 py-2">
              <div class="flex items-center gap-2">
                <img v-if="product.photo" :src="product.photo" alt="" class="h-10 w-10 rounded object-contain border bg-white" />
                <div>
                  <div class="font-medium">{{ product.code }}</div>
                  <div class="text-xs text-gray-600">{{ product.name }}</div>
                </div>
              </div>
            </td>
            <td class="px-2 py-2 text-right">{{ product.machines }}</td>
            <td class="px-2 py-2">
              <select :value="product.sub_category?.id ?? ''" :disabled="!can.update" class="rounded-md border-gray-300 text-sm w-48" @change="saveSub(product, $event.target.value)">
                <option value="">{{ $t('— none —') }}</option>
                <option v-for="sub in subCategoryChoices" :key="sub.id" :value="sub.id">{{ sub.name }}</option>
              </select>
            </td>
            <td class="px-2 py-2 text-right whitespace-nowrap">{{ money(product.rp) }}</td>
            <td class="px-2 py-2 text-right whitespace-nowrap">
              <input
                v-if="setting.pricing_mode === 'custom' && can.update"
                type="number"
                min="0.01"
                step="0.01"
                :value="product.custom_amount !== null ? (product.custom_amount / 100).toFixed(2) : ''"
                class="w-24 rounded-md border-gray-300 text-sm text-right"
                @change="saveCustom(product, $event.target.value)"
              />
              <span v-else class="font-medium">{{ money(product.grab_price) }}</span>
            </td>
            <td class="px-2 py-2">
              <span v-if="!product.problem" class="text-green-700">{{ product.machines ? $t('On Grab') : $t('In no machine') }}</span>
              <span v-else class="text-amber-700">{{ problemName(product.problem, rp) }}</span>
            </td>
          </tr>
          <tr v-if="!shown.length">
            <td colspan="7" class="px-2 py-4 text-center text-gray-500">{{ filter === 'problem' && !search.trim() ? $t('Nothing to fix.') : $t('No product here.') }}</td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-if="can.update" class="mt-3 flex flex-wrap items-center gap-2 border-t pt-3">
      <button type="button" :class="secondaryClass" :disabled="busy" @click="post(`/grab-menu/${operatorId}/fill-from-templates`, {})">{{ $t('Fill empty sub-categories and prices from the old Grab menus') }}</button>
      <button type="button" :class="secondaryClass" :disabled="grabCategoriesLoading" @click="$emit('loadGrabCategories')">
        {{ grabCategoriesLoading ? $t('Loading…') : $t('Load Grab\'s sub-category list') }}
      </button>
    </div>
  </section>
</template>

<script setup>
import { router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import { t } from '@/i18n'
import { matchesWords, money, primaryClass, problemName, rpName, secondaryClass } from './priceRule'

// Each product's Grab sub-category (shared by every store) and, under a company typed-price rule, its Grab price.
const props = defineProps({
  operatorId: { type: Number, required: true },
  products: { type: Array, required: true },
  setting: { type: Object, required: true },
  rpTypes: { type: Array, required: true },
  subCategoryChoices: { type: Array, required: true },
  grabCategoriesLoading: { type: Boolean, default: false },
  can: { type: Object, required: true },
})
defineEmits(['loadGrabCategories'])

const rp = computed(() => rpName(props.rpTypes, props.setting.rp_type))
const problemCount = computed(() => props.products.filter(p => p.problem && p.machines).length)
// Open on what needs doing; everything else is one click away.
const filter = ref(problemCount.value ? 'problem' : 'machines')
const chips = computed(() => [
  { key: 'problem', name: t('To fix'), count: problemCount.value },
  { key: 'listed', name: t('On Grab'), count: props.products.filter(p => !p.problem && p.machines).length },
  { key: 'machines', name: t('All in machines'), count: props.products.filter(p => p.machines).length },
  { key: 'off', name: t('In no machine'), count: props.products.filter(p => !p.machines).length },
])
const chipProducts = computed(() => props.products.filter(product => {
  if (filter.value === 'problem') return product.problem && product.machines
  if (filter.value === 'listed') return !product.problem && product.machines
  if (filter.value === 'off') return !product.machines
  return product.machines > 0
}))
const search = ref('')
const shown = computed(() => chipProducts.value.filter(product => matchesWords(`${product.code} ${product.name}`, search.value)))

const picked = ref([])
const bulkSub = ref(null)
watch(() => props.products, () => { picked.value = picked.value.filter(id => props.products.some(p => p.product_id === id)) })

const busy = ref(false)
function post(url, data) {
  busy.value = true
  router.post(url, data, { preserveScroll: true, onFinish: () => { busy.value = false } })
}
function subById(id) {
  return props.subCategoryChoices.find(sub => sub.id === id) ?? null
}
function saveSub(product, id) {
  post(`/grab-menu/${props.operatorId}/products/${product.product_id}`, { sub_category: id ? subById(id) : null })
}
function saveCustom(product, value) {
  post(`/grab-menu/${props.operatorId}/products/${product.product_id}`, { custom_price: value === '' ? null : value })
}
function applyBulk() {
  post(`/grab-menu/${props.operatorId}/sub-categories`, { product_ids: picked.value, sub_category: subById(bulkSub.value) })
  picked.value = []
  bulkSub.value = null
}
</script>
