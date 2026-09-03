<template>
  <div>
    <div class="flex items-center justify-between">
      <label class="block text-sm font-medium text-gray-700">{{ label }}</label>
      <div class="flex items-center space-x-1 text-xs font-medium">
        <button
          type="button"
          :class="scope === 'all' ? activeClass : idleClass"
          @click="setScope('all')"
        >
          All
        </button>
        <button
          type="button"
          :class="scope === 'active' ? activeClass : idleClass"
          @click="setScope('active')"
        >
          Active
        </button>
      </div>
    </div>
    <MultiSelect
      :modelValue="modelValue"
      :options="visibleOptions"
      trackBy="id"
      valueProp="id"
      label="full_name"
      :placeholder="placeholder"
      open-direction="bottom"
      class="mt-1"
      mode="tags"
      @update:modelValue="onUpdate"
      @selected="onSelected"
    />
  </div>
</template>

<script setup>
// The standard Operator filter (tags mode) used on every listing / report
// page. Options come from the shared `operatorFilterOptions` Inertia prop
// (HandleInertiaRequests), which includes deactivated operators, so the
// All / Active toggle is purely client-side. Pressing either button empties
// the selection and switches which operators can be picked. Default: Active.
import MultiSelect from '@/Components/MultiSelect.vue';
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const emit = defineEmits(['update:modelValue', 'selected', 'select']);

const props = defineProps({
  modelValue: {
    type: Array,
    default: () => ([]),
  },
  label: {
    type: String,
    default: 'Operator',
  },
  placeholder: {
    type: String,
    default: 'Select',
  },
  // Entries listed before the operators. Never filtered out by the toggle.
  // Defaults to the {id: 'all'} sentinel every page's old per-page list
  // started with: several backends (ProductMovement, OpsPerformance, Vend
  // index) treat an explicit 'all' differently from an empty selection
  // (empty = the HIPL sibling group), so it must stay selectable.
  // Pass [] to drop it.
  prepend: {
    type: Array,
    default: () => ([{ id: 'all', full_name: 'All' }]),
  },
});

const scope = ref('active');

const activeClass = 'px-2 py-0.5 rounded bg-indigo-100 text-indigo-700';
const idleClass = 'px-2 py-0.5 rounded text-gray-400 hover:text-indigo-700';

const allOptions = computed(() => usePage().props.operatorFilterOptions ?? []);

const visibleOptions = computed(() => [
  ...props.prepend,
  ...allOptions.value.filter(o => scope.value === 'all' || o.is_active !== false),
]);

function setScope(value) {
  scope.value = value;
  emit('update:modelValue', []);
}

function onUpdate(value) {
  emit('update:modelValue', value);
}

function onSelected(data) {
  emit('selected', data);
  emit('select', data);
}
</script>
