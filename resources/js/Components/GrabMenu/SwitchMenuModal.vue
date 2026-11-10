<template>
  <Modal :open="stores.length > 0" @modalClose="$emit('close')">
    <template #header>
      {{ toMachine
        ? $tc('Switch :count store to the machine menu?|Switch :count stores to the machine menu?', stores.length, { count: stores.length })
        : $tc('Put :count store back on its old Grab menu?|Put :count stores back on their old Grab menus?', stores.length, { count: stores.length }) }}
    </template>
    <div class="space-y-3 text-sm">
      <p v-if="toMachine" class="text-gray-700">{{ $t('Within a few minutes Grab shows what is in each machine, at each store\'s price rule. Products not in the machine leave Grab. You can put a store back on its old menu at any time.') }}</p>
      <p v-else class="text-gray-700">{{ $t('Within a few minutes Grab shows each store\'s old menu and old prices again. Its price rule is kept for next time.') }}</p>
      <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr class="text-left text-gray-700">
              <th class="px-2 py-1">{{ $t('Machine') }}</th>
              <th class="px-2 py-1">{{ $t('Site') }}</th>
              <th v-if="toMachine" class="px-2 py-1">{{ $t('Price rule') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <tr v-for="store in stores" :key="store.ref_id">
              <td class="px-2 py-1">{{ store.vend_code }}</td>
              <td class="px-2 py-1">{{ store.site ?? '—' }}</td>
              <td v-if="toMachine" class="px-2 py-1">
                {{ store.price_rule?.scope === 'store' ? $t('Own rule: :rule', { rule: ruleText(store.price_rule, rpTypes) }) : $t('Company rule: :rule', { rule: ruleText(companyRule, rpTypes) }) }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <p v-if="toMachine && stores.length === 1" class="text-gray-600">{{ $t('To see each product\'s price before and after, open the store first.') }}</p>
      <div class="flex flex-wrap gap-2">
        <button type="button" :class="toMachine ? primaryClass : dangerClass" :disabled="busy" @click="confirm">
          {{ toMachine ? $tc('Switch :count store|Switch :count stores', stores.length, { count: stores.length }) : $tc('Put :count store back|Put :count stores back', stores.length, { count: stores.length }) }}
        </button>
        <button type="button" :class="secondaryClass" @click="$emit('close')">{{ $t('Cancel') }}</button>
      </div>
    </div>
  </Modal>
</template>

<script setup>
import Modal from '@/Components/Modal.vue'
import { router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { dangerClass, primaryClass, ruleText, secondaryClass } from './priceRule'

// The one place a store's menu is switched, for one store or several, after the user has seen which ones.
const props = defineProps({
  stores: { type: Array, required: true },
  toMachine: { type: Boolean, default: true },
  operatorId: { type: Number, required: true },
  companyRule: { type: Object, required: true },
  rpTypes: { type: Array, required: true },
})
const emit = defineEmits(['close', 'done'])

const busy = ref(false)
function confirm() {
  busy.value = true
  router.post(`/grab-menu/${props.operatorId}/menu-source`, { machine_menu: props.toMachine, ref_ids: props.stores.map(store => store.ref_id) }, {
    preserveScroll: true,
    onSuccess: () => emit('done'),
    onFinish: () => { busy.value = false },
  })
}
</script>
