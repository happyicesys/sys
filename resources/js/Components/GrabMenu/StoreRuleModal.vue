<template>
  <Modal :open="stores.length > 0" @modalClose="$emit('close')">
    <template #header>{{ $tc('Price rule for :count store|Price rule for :count stores', stores.length, { count: stores.length }) }}</template>
    <div class="space-y-3 text-sm">
      <ul class="max-h-40 overflow-y-auto rounded-md border px-3 py-2 text-gray-700">
        <li v-for="store in stores" :key="store.ref_id">
          {{ store.vend_code }} · {{ store.site ?? '—' }}
          <span class="text-xs text-gray-500">({{ store.price_rule?.scope === 'store' ? $t('now: own rule, :rule', { rule: ruleText(store.price_rule, rpTypes) }) : $t('now: company rule') }})</span>
        </li>
      </ul>
      <label class="flex items-start gap-2">
        <input v-model="choice" type="radio" value="company" class="mt-0.5" />
        <span>{{ $t('Follow the company rule') }} <span class="text-gray-600">({{ ruleText(companyRule, rpTypes) }})</span></span>
      </label>
      <label class="flex items-start gap-2">
        <input v-model="choice" type="radio" value="store" class="mt-0.5" />
        <span>{{ $t('Give each of these stores the same own rule') }}</span>
      </label>
      <div v-if="choice === 'store'" class="ml-6 space-y-2">
        <PriceRuleForm v-model="rule" :rp-types="rpTypes" :round-up-steps="roundUpSteps" :errors="form.errors" :custom-help="$t('Then open each store to type its prices.')" />
        <div>
          <label class="block font-medium text-gray-700">{{ $t('Why are these stores priced differently? (optional)') }}</label>
          <input v-model="note" type="text" maxlength="255" :placeholder="$t('Quiet store, lower prices')" :class="inputClass" />
        </div>
      </div>
      <p class="text-gray-600">
        {{ $tc('Stores on the machine menu show the new prices on Grab within a few minutes; :count store still on its old menu takes the rule when it is switched.|Stores on the machine menu show the new prices on Grab within a few minutes; :count stores still on their old menu take the rule when they are switched.', oldMenuCount, { count: oldMenuCount }) }}
      </p>
      <InputError :message="form.errors.ref_ids" />
      <div class="flex flex-wrap gap-2">
        <button type="button" :class="primaryClass" :disabled="form.processing" @click="save">{{ $tc('Save for :count store|Save for :count stores', stores.length, { count: stores.length }) }}</button>
        <button type="button" :class="secondaryClass" @click="$emit('close')">{{ $t('Cancel') }}</button>
      </div>
    </div>
  </Modal>
</template>

<script setup>
import InputError from '@/Components/InputError.vue'
import Modal from '@/Components/Modal.vue'
import { useForm } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import PriceRuleForm from './PriceRuleForm.vue'
import { inputClass, primaryClass, ruleFields, ruleText, secondaryClass } from './priceRule'

// Several ticked stores at once: back to the company rule, or one own rule for all of them.
const props = defineProps({
  stores: { type: Array, required: true },
  operatorId: { type: Number, required: true },
  companyRule: { type: Object, required: true },
  rpTypes: { type: Array, required: true },
  roundUpSteps: { type: Array, required: true },
})
const emit = defineEmits(['close', 'saved'])

const choice = ref('store')
const rule = ref(ruleFields(props.companyRule))
const note = ref('')
const form = useForm({})
const oldMenuCount = computed(() => props.stores.filter(store => !store.machine_menu_since).length)

watch(() => props.stores.length, (count) => {
  if (count) {
    choice.value = 'store'
    rule.value = ruleFields(props.companyRule)
    note.value = ''
    form.clearErrors()
  }
})

function save() {
  const own = choice.value === 'store'
  form.transform(() => ({
    use_company_rule: !own,
    ref_ids: props.stores.map(store => store.ref_id),
    ...(own ? { ...rule.value, note: note.value } : {}),
  })).post(`/grab-menu/${props.operatorId}/store-price-rule`, { preserveScroll: true, onSuccess: () => emit('saved') })
}
</script>
