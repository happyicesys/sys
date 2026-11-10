<template>
  <section class="-mx-3 sm:-mx-6 lg:-mx-8 bg-white rounded-md border px-3 py-3 text-sm">
    <div class="flex flex-wrap items-start justify-between gap-2">
      <div class="min-w-0">
        <h3 class="text-base font-semibold text-gray-800">{{ $t('Company price rule') }}</h3>
        <p class="mt-1 text-gray-800">{{ ruleText(setting, rpTypes) }}</p>
        <p class="mt-1 text-gray-600">
          {{ $tc('Every store follows this rule unless it has its own. :count store has its own rule.|Every store follows this rule unless it has its own. :count stores have their own rule.', ownRuleCount, { count: ownRuleCount }) }}
        </p>
        <p class="mt-1 text-xs" :class="setting.updated_at ? 'text-gray-500' : 'text-amber-700'">
          {{ setting.updated_at ? $t('Saved :time by :name', { time: formatDateTime(setting.updated_at), name: setting.updated_by ?? '—' }) : $t('Not saved yet: shown with the default (RP2).') }}
        </p>
      </div>
      <button v-if="can.update && !editing" type="button" :class="secondaryClass" @click="editing = true">{{ $t('Change company rule') }}</button>
    </div>

    <div v-if="editing" class="mt-3 border-t pt-3 space-y-3">
      <p class="rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-amber-800">
        {{ $tc('Saving changes the Grab prices of the :count store on the machine menu that follows this rule.|Saving changes the Grab prices of the :count stores on the machine menu that follow this rule.', followingLiveCount, { count: followingLiveCount }) }}
      </p>
      <PriceRuleForm v-model="rule" :rp-types="rpTypes" :round-up-steps="roundUpSteps" :errors="form.errors" :custom-help="$t('Type each product\'s Grab price under Products below.')" />
      <div>
        <label class="block font-medium text-gray-700">{{ $t('Grab category (every product sits under it)') }}</label>
        <div class="mt-1 flex flex-wrap items-center gap-2">
          <span>{{ category?.name ?? $t('not set') }}</span>
          <select v-if="grabCategories.length" class="rounded-md border-gray-300 text-sm" @change="pickCategory($event.target.value)">
            <option value="">{{ $t('Change…') }}</option>
            <option v-for="option in grabCategories" :key="option.id" :value="option.id">{{ option.name }}</option>
          </select>
          <button v-else type="button" class="text-indigo-700 underline" @click="$emit('loadGrabCategories')">{{ $t('Load Grab\'s list') }}</button>
        </div>
      </div>
      <div class="flex flex-wrap gap-2">
        <button type="button" :class="primaryClass" :disabled="form.processing" @click="save">{{ $t('Save company rule') }}</button>
        <button type="button" :class="secondaryClass" :disabled="form.processing" @click="cancel">{{ $t('Cancel') }}</button>
      </div>
    </div>
  </section>
</template>

<script setup>
import { useForm } from '@inertiajs/vue3'
import moment from 'moment'
import { ref } from 'vue'
import PriceRuleForm from './PriceRuleForm.vue'
import { primaryClass, ruleFields, ruleText, secondaryClass } from './priceRule'

const props = defineProps({
  operatorId: { type: Number, required: true },
  setting: { type: Object, required: true },
  rpTypes: { type: Array, required: true },
  roundUpSteps: { type: Array, required: true },
  grabCategories: { type: Array, default: () => [] },
  ownRuleCount: { type: Number, default: 0 },
  followingLiveCount: { type: Number, default: 0 },
  can: { type: Object, required: true },
})
defineEmits(['loadGrabCategories'])

const editing = ref(false)
const rule = ref(ruleFields(props.setting))
const category = ref(props.setting.category ?? null)
const form = useForm({})

function pickCategory(id) {
  category.value = props.grabCategories.find(option => option.id === id) ?? category.value
}
function cancel() {
  rule.value = ruleFields(props.setting)
  category.value = props.setting.category ?? null
  editing.value = false
}
function save() {
  form.transform(() => ({ ...rule.value, category: category.value }))
    .post(`/grab-menu/${props.operatorId}/setting`, { preserveScroll: true, onSuccess: () => { editing.value = false } })
}
function formatDateTime(value) {
  return value ? moment(value).format('YYYY-MM-DD HH:mm') : ''
}
</script>
