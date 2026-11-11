<template>
  <div class="space-y-3 text-sm">
    <p v-if="loading && !preview" class="text-gray-500">{{ $t('Reading machine :code…', { code: vendCode }) }}</p>
    <p v-else-if="error" class="rounded-md border border-red-200 bg-red-50 px-3 py-2 text-red-700">{{ error }}</p>
    <template v-else-if="preview">
      <p class="text-gray-700">
        {{ $t('Grab will list what is in machine :code (:site), at the price rule below. No old Grab menu is used.', { code: preview.machine.code, site: preview.machine.site ?? $t('no Site') }) }}
      </p>

      <!-- Price -->
      <div class="rounded-md border px-3 py-2 space-y-2">
        <div class="font-semibold text-gray-800">{{ $t('Price on Grab') }}</div>
        <label class="flex items-start gap-2">
          <input v-model="choice" type="radio" value="company" class="mt-0.5" />
          <span>{{ $t('Company rule') }} <span class="text-gray-600">({{ ruleText(preview.company_rule, rpTypes) }})</span></span>
        </label>
        <label class="flex items-start gap-2">
          <input v-model="choice" type="radio" value="store" class="mt-0.5" />
          <span>{{ $t('This store\'s own rule') }}</span>
        </label>
        <div v-if="choice === 'store'" class="ml-6 space-y-2">
          <PriceRuleForm v-model="rule" :rp-types="rpTypes" :round-up-steps="roundUpSteps" :custom-help="$t('Type each product\'s price for this store in the table below.')" />
          <input v-model="note" type="text" maxlength="255" :placeholder="$t('Why is this store priced differently? (optional)')" class="block w-full rounded-md border-gray-300 text-sm" />
        </div>
      </div>

      <!-- Products -->
      <p :class="missingLeft ? 'rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-amber-800' : 'rounded-md border border-green-200 bg-green-50 px-3 py-2 text-green-800'">
        <template v-if="missingLeft">{{ $tc(':count product needs your choice below, or it stays off Grab.|:count products need your choice below, or they stay off Grab.', missingLeft, { count: missingLeft }) }}</template>
        <template v-else>{{ $tc(':count product will be on Grab. Nothing is missing.|All :count products will be on Grab. Nothing is missing.', rows.length, { count: rows.length }) }}</template>
      </p>
      <div class="max-h-80 overflow-y-auto rounded-md border">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50 sticky top-0">
            <tr class="text-left text-gray-700">
              <th class="px-2 py-1">{{ $t('Product') }}</th>
              <th class="px-2 py-1">{{ $t('Grab sub-category') }}</th>
              <th class="px-2 py-1 text-right">{{ $t('Grab price') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="row in rows" :key="row.product_id" :class="needs(row) ? 'bg-amber-50' : ''">
              <td class="px-2 py-1">
                <div class="flex items-center gap-2">
                  <img v-if="row.photo" :src="row.photo" alt="" class="h-8 w-8 rounded object-contain border bg-white" />
                  <div><div>{{ row.name }}</div><div class="text-xs text-gray-500">{{ row.code }}</div></div>
                </div>
              </td>
              <td class="px-2 py-1">
                <select v-if="row.problem === 'no_sub_category'" v-model="picks[row.product_id]" class="w-44 rounded-md border-gray-300 text-sm">
                  <option :value="undefined">{{ $t('Choose…') }}</option>
                  <option v-for="sub in preview.sub_categories" :key="sub.id" :value="sub.id">{{ sub.name }}</option>
                </select>
                <span v-else>{{ row.sub_category?.name ?? '—' }}</span>
              </td>
              <td class="px-2 py-1 text-right whitespace-nowrap">
                <input
                  v-if="row.problem === 'no_custom_price'"
                  v-model="prices[row.product_id]"
                  type="number" min="0.01" step="0.01"
                  :placeholder="$t('Price')"
                  class="w-24 rounded-md border-gray-300 text-sm text-right"
                />
                <span v-else-if="row.problem === 'no_rp'" class="text-amber-700">{{ $t('No :rp price: set it on Product → Edit', { rp: rpName(rpTypes, previewRuleTier) }) }}</span>
                <span v-else>{{ money(row.price) }}</span>
              </td>
            </tr>
            <tr v-if="!rows.length">
              <td colspan="3" class="px-2 py-3 text-center text-gray-500">{{ $t('No product on this machine\'s slots.') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
  </div>
</template>

<script setup>
import PriceRuleForm from '@/Components/GrabMenu/PriceRuleForm.vue'
import { money, rpName, ruleFields, ruleText } from '@/Components/GrabMenu/priceRule'
import axios from 'axios'
import { computed, ref, watch } from 'vue'
import { t } from '@/i18n'

// Grab Stores' put-on-machine window: what Grab will list from the machine, the price rule, and what is missing —
// filled in here, saved with the store (GrabStoreSetup). v-model = the `setup` the server applies before going live.
const props = defineProps({
  operatorId: { type: [Number, String], default: null },
  vendCode: { type: String, default: '' },
  refId: { type: Number, default: null },
  rpTypes: { type: Array, required: true },
  roundUpSteps: { type: Array, required: true },
})
const emit = defineEmits(['update:modelValue'])

const preview = ref(null)
const loading = ref(false)
const error = ref(null)
const choice = ref('company')
const rule = ref(ruleFields(null))
const note = ref('')
const picks = ref({})
const prices = ref({})

const rows = computed(() => preview.value?.rows ?? [])
const typed = computed(() => (choice.value === 'company' ? preview.value?.company_rule?.pricing_mode : rule.value.pricing_mode) === 'custom')
const previewRuleTier = computed(() => preview.value?.rule?.rp_type ?? 2)
function needs(row) {
  if (row.problem === 'no_sub_category') return !picks.value[row.product_id]
  if (row.problem === 'no_custom_price') return !(Number(prices.value[row.product_id]) > 0)
  return row.problem !== null
}
const missingLeft = computed(() => rows.value.filter(needs).length)

let timer = null
let seq = 0
function load() {
  clearTimeout(timer)
  const code = String(props.vendCode ?? '').trim()
  if (!code || !props.operatorId) {
    preview.value = null
    return
  }
  timer = setTimeout(async () => {
    const mine = ++seq
    loading.value = true
    error.value = null
    const params = { operator_id: props.operatorId, vend_code: code, ref_id: props.refId ?? undefined, use_company_rule: choice.value === 'company' ? 1 : 0 }
    if (choice.value === 'store') Object.assign(params, rule.value)
    try {
      const { data } = await axios.get('/grab-stores/machine-preview', { params })
      if (mine !== seq) return
      // A store with its own rule already starts from it.
      if (!preview.value && data.own_rule) {
        choice.value = 'store'
        rule.value = ruleFields(data.own_rule)
        note.value = data.own_rule.note ?? ''
      }
      preview.value = data
    } catch (e) {
      if (mine === seq) {
        preview.value = null
        error.value = e.response?.status === 422 ? (Object.values(e.response.data.errors ?? {})[0]?.[0] ?? t('Check the machine ID.')) : t('The machine could not be read.')
      }
    } finally {
      if (mine === seq) loading.value = false
    }
  }, 400)
}
watch(() => [props.vendCode, props.operatorId, props.refId], () => { preview.value = null; picks.value = {}; prices.value = {}; load() }, { immediate: true })
watch([choice, rule], load, { deep: true })

// What the server applies: the rule, the picked sub-categories and the typed prices.
watch([choice, rule, note, picks, prices, preview], () => {
  const subs = preview.value?.sub_categories ?? []
  emit('update:modelValue', {
    use_company_rule: choice.value === 'company',
    ...(choice.value === 'store' ? { ...rule.value, note: note.value } : {}),
    sub_categories: Object.entries(picks.value).filter(([, id]) => id)
      .map(([productId, id]) => ({ product_id: Number(productId), id, name: subs.find(sub => sub.id === id)?.name ?? id })),
    custom_prices: Object.entries(prices.value).filter(([, price]) => Number(price) > 0)
      .map(([productId, price]) => ({ product_id: Number(productId), price })),
  })
}, { deep: true, immediate: true })
</script>
