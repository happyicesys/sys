<template>
  <div class="space-y-3 text-sm">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
      <label
        v-for="mode in modes"
        :key="mode.id"
        class="flex items-start gap-2 rounded-md border px-3 py-2"
        :class="modelValue.pricing_mode === mode.id ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200'"
      >
        <input type="radio" :checked="modelValue.pricing_mode === mode.id" :disabled="disabled" class="mt-0.5" @change="set('pricing_mode', mode.id)" />
        <span>
          <span class="block font-medium text-gray-800">{{ mode.name }}</span>
          <span class="block text-xs text-gray-600">{{ mode.help }}</span>
        </span>
      </label>
    </div>
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
      <div v-if="modelValue.pricing_mode !== 'custom'">
        <label class="block font-medium text-gray-700">{{ $t('RP tier') }}</label>
        <select :value="modelValue.rp_type" :disabled="disabled" :class="inputClass" @change="set('rp_type', Number($event.target.value))">
          <option v-for="rp in rpTypes" :key="rp.id" :value="rp.id">{{ rp.name }}</option>
        </select>
      </div>
      <template v-if="modelValue.pricing_mode === 'rp_multiplier'">
        <div>
          <label class="block font-medium text-gray-700">{{ $t('Multiplier') }}</label>
          <input :value="modelValue.multiplier" type="number" min="0.5" max="5" step="0.01" :disabled="disabled" :class="inputClass" @input="set('multiplier', $event.target.value)" />
          <InputError :message="errors?.multiplier" />
        </div>
        <div>
          <label class="block font-medium text-gray-700">{{ $t('Round up to') }}</label>
          <select :value="modelValue.round_up_cents" :disabled="disabled" :class="inputClass" @change="set('round_up_cents', Number($event.target.value))">
            <option v-for="step in roundUpSteps" :key="step" :value="step">{{ step === 100 ? $t('the dollar') : $t(':cents¢', { cents: step }) }}</option>
          </select>
        </div>
      </template>
    </div>
    <p class="rounded-md bg-gray-50 px-3 py-2 text-gray-700">
      <span class="font-medium">{{ $t('In words:') }}</span> {{ ruleText(modelValue, rpTypes) }}.
      <template v-if="example !== null">{{ $t('Example: a product with :rp :normal sells on Grab at :price.', { rp: rpName(rpTypes, modelValue.rp_type), normal: money(320), price: money(example) }) }}</template>
      <template v-else>{{ customHelp }}</template>
    </p>
    <InputError :message="errors?.pricing_mode || errors?.rp_type || errors?.round_up_cents" />
  </div>
</template>

<script setup>
import InputError from '@/Components/InputError.vue'
import { computed } from 'vue'
import { t } from '@/i18n'
import { examplePrice, inputClass, money, rpName, ruleText } from './priceRule'

// One price rule's fields (company or one store), v-model'd as { pricing_mode, rp_type, multiplier, round_up_cents }.
const props = defineProps({
  modelValue: { type: Object, required: true },
  rpTypes: { type: Array, required: true },
  roundUpSteps: { type: Array, required: true },
  errors: { type: Object, default: null },
  disabled: { type: Boolean, default: false },
  // Where typed prices are entered, said under a custom rule.
  customHelp: { type: String, default: '' },
})
const emit = defineEmits(['update:modelValue'])

const modes = [
  { id: 'rp', name: t('RP price'), help: t('Grab price = the product\'s RP on the tier you pick.') },
  { id: 'rp_multiplier', name: t('RP × multiplier'), help: t('Grab price = RP times a number (e.g. 1.25), always rounded up.') },
  { id: 'custom', name: t('Typed price'), help: t('You type the Grab price of each product yourself.') },
]

const example = computed(() => examplePrice(props.modelValue, 320))

function set(key, value) {
  emit('update:modelValue', { ...props.modelValue, [key]: value })
}
</script>
