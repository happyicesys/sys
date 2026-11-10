<template>
  <Modal :open="!!store" @modalClose="$emit('close')">
    <template #header>
      <span>{{ $t('Store :code · :site', { code: store?.vend_code ?? '', site: store?.site ?? '—' }) }}</span>
    </template>
    <div v-if="store" class="space-y-4 text-sm">
      <!-- 1. Menu: which menu Grab shows for this store. -->
      <section class="rounded-md border px-3 py-2">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div>
            <h4 class="font-semibold text-gray-800">{{ $t('Grab menu') }}</h4>
            <p v-if="store.machine_menu_since" class="text-gray-700">{{ $t('Shows what is in machine :code, at this store\'s price rule. Since :time.', { code: store.vend_code, time: formatDateTime(store.machine_menu_since) }) }}</p>
            <p v-else class="text-gray-700">{{ $t('Still shows its old Grab menu. The machine and the price rule below apply once you switch it.') }}</p>
          </div>
          <template v-if="can.update">
            <button v-if="!store.machine_menu_since" type="button" :class="primaryClass" @click="$emit('switch', [store], true)">{{ $t('Switch to machine menu…') }}</button>
            <button v-else type="button" :class="secondaryClass" @click="$emit('switch', [store], false)">{{ $t('Put back on old menu…') }}</button>
          </template>
        </div>
      </section>

      <!-- 2. Price rule: the company's, or this store's own. -->
      <section class="rounded-md border px-3 py-2 space-y-2">
        <h4 class="font-semibold text-gray-800">{{ $t('Price rule for this store') }}</h4>
        <label class="flex items-start gap-2">
          <input v-model="choice" type="radio" value="company" :disabled="!can.update" class="mt-0.5" />
          <span>{{ $t('Follow the company rule') }} <span class="text-gray-600">({{ ruleText(companyRule, rpTypes) }})</span></span>
        </label>
        <label class="flex items-start gap-2">
          <input v-model="choice" type="radio" value="store" :disabled="!can.update" class="mt-0.5" />
          <span>{{ $t('Give this store its own rule') }}</span>
        </label>
        <div v-if="choice === 'store'" class="ml-6 space-y-2">
          <PriceRuleForm v-model="rule" :rp-types="rpTypes" :round-up-steps="roundUpSteps" :errors="form.errors" :disabled="!can.update" :custom-help="$t('Type each product\'s price for this store in the table below.')" />
          <div>
            <label class="block font-medium text-gray-700">{{ $t('Why is this store priced differently? (optional)') }}</label>
            <input v-model="note" type="text" maxlength="255" :placeholder="$t('Quiet store, lower prices')" :disabled="!can.update" :class="inputClass" />
          </div>
        </div>
        <div v-if="can.update && dirty" class="flex flex-wrap items-center gap-2 border-t pt-2">
          <button type="button" :class="primaryClass" :disabled="form.processing" @click="save">{{ $t('Save price rule for this store') }}</button>
          <button type="button" :class="secondaryClass" :disabled="form.processing" @click="reset">{{ $t('Cancel') }}</button>
          <span class="text-xs text-gray-600">{{ store.machine_menu_since ? $t('Grab shows the new prices within a few minutes of saving.') : $t('Nothing changes on Grab until the store is switched to the machine menu.') }}</span>
        </div>
      </section>

      <!-- 3. What its Grab customers see: today against after. -->
      <section class="rounded-md border px-3 py-2">
        <h4 class="font-semibold text-gray-800">{{ dirty ? $t('What Grab customers would see with this rule') : $t('What Grab customers see') }}</h4>
        <p class="text-gray-600">
          {{ store.machine_menu_since ? $t('"On Grab now" is the menu Grab last read for this store. "After the change" is its price under the rule above.') : $t('"On Grab now" is its old Grab menu. "After the change" is what Grab will show once it is switched to the machine menu.') }}
        </p>
        <p v-if="loading" class="mt-2 text-gray-500">{{ $t('Loading…') }}</p>
        <p v-else-if="loadError" class="mt-2 text-red-700">{{ loadError }}</p>
        <template v-else-if="preview">
          <p v-if="preview.summary" class="mt-2 flex flex-wrap gap-x-3 gap-y-1">
            <span class="font-medium">{{ $tc(':count product on Grab|:count products on Grab', preview.summary.listed, { count: preview.summary.listed }) }}</span>
            <span v-if="preview.summary.up" class="text-amber-800">{{ $tc(':count price goes up|:count prices go up', preview.summary.up, { count: preview.summary.up }) }}</span>
            <span v-if="preview.summary.down" class="text-sky-800">{{ $tc(':count price goes down|:count prices go down', preview.summary.down, { count: preview.summary.down }) }}</span>
            <span v-if="preview.summary.added" class="text-green-800">{{ $tc(':count new on Grab|:count new on Grab', preview.summary.added, { count: preview.summary.added }) }}</span>
            <span v-if="preview.summary.removed" class="text-red-700">{{ $tc(':count leaves Grab|:count leave Grab', preview.summary.removed, { count: preview.summary.removed }) }}</span>
            <span v-if="preview.summary.not_listed" class="text-amber-800">{{ $tc(':count not on Grab (needs fixing)|:count not on Grab (need fixing)', preview.summary.not_listed, { count: preview.summary.not_listed }) }}</span>
            <span v-if="preview.summary.sold_out" class="text-gray-600">{{ $tc(':count sold out in the machine now|:count sold out in the machine now', preview.summary.sold_out, { count: preview.summary.sold_out }) }}</span>
          </p>
          <p v-if="preview.summary && !preview.summary.has_current" class="mt-1 text-xs text-gray-500">{{ $t('Grab has not read this store\'s menu yet, so there is nothing to compare with.') }}</p>
          <div class="mt-2 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr class="text-left text-gray-700">
                  <th class="px-2 py-1">{{ $t('Product') }}</th>
                  <th class="px-2 py-1 text-right whitespace-nowrap">{{ $t('On Grab now') }}</th>
                  <th class="px-2 py-1 text-right whitespace-nowrap">{{ $t('After the change') }}</th>
                  <th class="px-2 py-1">{{ $t('What changes') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="row in preview.rows" :key="row.product_id" class="align-middle">
                  <td class="px-2 py-1">
                    <div class="flex items-center gap-2">
                      <img v-if="row.photo" :src="row.photo" alt="" class="h-8 w-8 rounded object-contain border bg-white" />
                      <div>
                        <div>{{ row.name }}</div>
                        <div class="text-xs text-gray-500">{{ row.code }}<template v-if="row.rp !== null"> · {{ $t(':rp :price', { rp: rpName(rpTypes, previewRule.rp_type), price: money(row.rp) }) }}</template></div>
                      </div>
                    </div>
                  </td>
                  <td class="px-2 py-1 text-right whitespace-nowrap">{{ money(row.now) }}</td>
                  <td class="px-2 py-1 text-right whitespace-nowrap">
                    <input
                      v-if="typesPrices && row.change !== 'removed' && can.update"
                      type="number"
                      min="0.01"
                      step="0.01"
                      :value="typedPrice(row)"
                      :aria-label="$t('Grab price of :name at this store', { name: row.name })"
                      class="w-24 rounded-md border-gray-300 text-sm text-right"
                      @change="saveTypedPrice(row, $event.target.value)"
                    />
                    <span v-else class="font-medium">{{ money(row.after) }}</span>
                  </td>
                  <td class="px-2 py-1">
                    <span :class="changeClass(row.change)">{{ changeName(row) }}</span>
                    <span v-if="row.after !== null && row.available === 0" class="ml-1 text-xs text-gray-500">{{ $t('(sold out now)') }}</span>
                  </td>
                </tr>
                <tr v-if="!preview.rows.length">
                  <td colspan="4" class="px-2 py-3 text-center text-gray-500">{{ $t('No product on this machine\'s slots.') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </template>
      </section>
    </div>
  </Modal>
</template>

<script setup>
import Modal from '@/Components/Modal.vue'
import { useForm } from '@inertiajs/vue3'
import axios from 'axios'
import moment from 'moment'
import { computed, ref, watch } from 'vue'
import { t } from '@/i18n'
import PriceRuleForm from './PriceRuleForm.vue'
import { inputClass, money, primaryClass, problemName, rpName, ruleFields, ruleText, sameRule, secondaryClass } from './priceRule'

// One store: its menu, its price rule (the company's or its own) and what its Grab customers see, today against after.
const props = defineProps({
  store: { type: Object, default: null },
  operatorId: { type: Number, required: true },
  companyRule: { type: Object, required: true },
  rpTypes: { type: Array, required: true },
  roundUpSteps: { type: Array, required: true },
  can: { type: Object, required: true },
})
defineEmits(['close', 'switch'])

const choice = ref('company')
const rule = ref(ruleFields(null))
const note = ref('')
const form = useForm({})
const preview = ref(null)
const loading = ref(false)
const loadError = ref(null)

const saved = computed(() => props.store?.price_rule ?? null)
const savedScope = computed(() => saved.value?.scope ?? 'company')
const dirty = computed(() => {
  if (!props.store) return false
  if (choice.value !== savedScope.value) return true
  return choice.value === 'store' && (!sameRule(rule.value, saved.value) || (note.value || '') !== (saved.value?.note || ''))
})
// The rule the table is priced by: the draft while it differs from what is saved.
const previewRule = computed(() => (choice.value === 'store' ? rule.value : props.companyRule))
// A store with its own typed-price rule: the After column is where its prices are typed.
const typesPrices = computed(() => choice.value === 'store' && rule.value.pricing_mode === 'custom')

function reset() {
  choice.value = savedScope.value
  // A store moving to its own rule starts from the company rule, so only what differs needs changing.
  rule.value = ruleFields(savedScope.value === 'store' ? saved.value : props.companyRule)
  note.value = saved.value?.note ?? ''
  form.clearErrors()
}

let timer = null
let seq = 0
function loadPreview() {
  if (!props.store) return
  clearTimeout(timer)
  timer = setTimeout(async () => {
    const mine = ++seq
    loading.value = !preview.value
    loadError.value = null
    let params = {}
    if (dirty.value) {
      params = choice.value === 'company' ? { use_company_rule: 1 } : { ...rule.value }
    }
    try {
      const { data } = await axios.get(`/grab-menu/stores/${props.store.ref_id}/preview`, { params })
      if (mine === seq) preview.value = data
    } catch (e) {
      if (mine === seq) loadError.value = e.response?.status === 422 ? t('Check the rule: the multiplier must be between 0.5 and 5.') : t('The preview could not be loaded.')
    } finally {
      if (mine === seq) loading.value = false
    }
  }, 300)
}

watch(() => props.store?.ref_id, (id) => {
  preview.value = null
  if (id) {
    reset()
    loadPreview()
  }
}, { immediate: true })
// The saved rule moved (this store saved, or a reload): start again from it.
watch(() => props.store?.price_rule, () => { if (props.store) { reset(); loadPreview() } }, { deep: true })
watch([choice, rule], loadPreview, { deep: true })

function save() {
  const own = choice.value === 'store'
  form.transform(() => ({
    use_company_rule: !own,
    ref_ids: [props.store.ref_id],
    ...(own ? { ...rule.value, note: note.value } : {}),
  })).post(`/grab-menu/${props.operatorId}/store-price-rule`, { preserveScroll: true })
}

function typedPrice(row) {
  return row.after !== null ? (row.after / 100).toFixed(2) : ''
}
async function saveTypedPrice(row, value) {
  try {
    await axios.post(`/grab-menu/stores/${props.store.ref_id}/custom-prices/${row.product_id}`, { price: value === '' ? null : value })
    loadPreview()
  } catch (e) {
    loadError.value = t('That price could not be saved.')
  }
}

function changeName(row) {
  return {
    up: t('Price up'),
    down: t('Price down'),
    same: t('No change'),
    added: t('New on Grab'),
    removed: t('Leaves Grab (not in the machine)'),
    no_current: t('On Grab'),
    not_listed: problemName(row.problem, rpName(props.rpTypes, previewRule.value.rp_type)),
  }[row.change] ?? row.change
}
function changeClass(change) {
  return {
    up: 'text-amber-800',
    down: 'text-sky-800',
    added: 'text-green-800',
    removed: 'text-red-700',
    not_listed: 'text-amber-800',
  }[change] ?? 'text-gray-600'
}
function formatDateTime(value) {
  return value ? moment(value).format('YYYY-MM-DD HH:mm') : ''
}
</script>
