<template>
  <!--
    Shared "Access X(s)" allow-list block.

    Used three times: Access Vending Machine(s) and Access Product(s) on
    User/Edit, and Access Product(s) on Operator/Edit.

    LAYOUT NOTE: both host pages use `sm:grid-cols-6`, so `sm:col-span-6` here
    means FULL WIDTH of the form. Everything inside is a plain vertical stack -
    deliberately NOT a nested grid, because a nested 12-col grid inside this
    6-col cell throws the picker and the mode toggle out to the right where they
    read as if they belong to the section above.
  -->
  <div class="col-span-12 sm:col-span-6">

    <!-- divider heading -->
    <div class="relative pt-2 pb-1 md:pt-5 md:pb-3">
      <div class="absolute inset-0 flex items-center" aria-hidden="true">
        <div class="w-full border-t border-gray-300"></div>
      </div>
      <div class="relative flex justify-center">
        <span class="px-3 bg-white text-lg font-medium text-gray-900"> {{ title }} </span>
      </div>
    </div>

    <!-- inherited ceiling notice (User/Edit only) -->
    <div v-if="ceiling" class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
      <span class="font-semibold">Capped by operator.</span>
      {{ ceiling.operatorName || 'This operator' }} is restricted to
      {{ ceiling.products.length }} product(s): <span class="font-medium">{{ ceilingSummary }}</span>.
      This user can only be granted products from that list.
    </div>

    <!-- access mode -->
    <div v-if="showMode" class="mt-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2">
      <div class="flex flex-wrap items-center gap-x-6 gap-y-1">
        <span class="text-sm font-medium text-gray-700">This {{ subjectNoun }} can access:</span>

        <label class="flex items-center space-x-2 text-sm text-gray-700" :class="[canEdit ? 'cursor-pointer' : 'cursor-not-allowed opacity-60']">
          <input
            type="radio"
            class="text-indigo-600 focus:ring-indigo-500"
            :checked="mode !== MODE_LIST"
            :disabled="!canEdit"
            @change="$emit('update:mode', MODE_ALL)"
          />
          <span>{{ allLabel }}</span>
        </label>

        <label class="flex items-center space-x-2 text-sm text-gray-700" :class="[canEdit ? 'cursor-pointer' : 'cursor-not-allowed opacity-60']">
          <input
            type="radio"
            class="text-indigo-600 focus:ring-indigo-500"
            :checked="mode === MODE_LIST"
            :disabled="!canEdit"
            @change="$emit('update:mode', MODE_LIST)"
          />
          <span>Only the {{ itemNoun }}(s) listed below</span>
        </label>
      </div>

      <!--
        The whole point of the mode flag: an empty list in 'list' mode means
        "sees nothing", NOT "sees everything". Without this an admin who removes
        the last row has no idea they just blinded the account.
      -->
      <p class="mt-1 text-sm font-semibold text-red-600" v-if="mode === MODE_LIST && !rows.length">
        Nothing listed &mdash; this {{ subjectNoun }} will see NO {{ itemNoun }}s at all.
      </p>
    </div>

    <!-- picker -->
    <div class="mt-3 flex items-end gap-x-2 max-w-2xl">
      <div class="flex-1 min-w-0">
        <label class="block text-sm font-medium text-gray-700">
          {{ addLabel }}
        </label>
        <MultiSelect
          v-model="selected"
          :options="availableOptions"
          trackBy="id"
          valueProp="id"
          :label="optionLabel"
          placeholder="Select"
          open-direction="bottom"
          class="mt-1"
          :disabled="!canEdit"
        >
        </MultiSelect>
      </div>
      <Button
        type="button"
        @click="add()"
        class="bg-green-500 hover:bg-green-600 text-white flex space-x-1 shrink-0"
        :class="[!selectedOptionId || !canEdit ? 'opacity-50 cursor-not-allowed' : '']"
        :disabled="!selectedOptionId || !canEdit"
      >
        <PlusCircleIcon class="w-4 h-4"></PlusCircleIcon>
        <span>Add</span>
      </Button>
    </div>
    <div class="text-sm text-red-600" v-if="error">
      {{ error }}
    </div>

    <!-- bound list -->
    <div class="mt-3 overflow-x-auto">
      <div class="inline-block min-w-full align-middle">
        <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
          <table class="min-w-full divide-y divide-gray-300">
            <thead class="bg-gray-50">
              <tr>
                <th scope="col" class="w-12 px-3 py-3 text-center text-sm font-semibold text-gray-900">#</th>
                <th
                  scope="col"
                  class="px-3 py-3 text-left text-sm font-semibold text-gray-900"
                  v-for="column in columns"
                  :key="column.key"
                >
                  {{ column.label }}
                </th>
                <th scope="col" class="w-24 px-3 py-3 text-center text-sm font-semibold text-gray-900">Action</th>
              </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
              <tr v-for="(row, rowIndex) in rows" :key="row.id" :class="rowIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                <td class="whitespace-nowrap px-3 py-3 text-sm text-gray-500 text-center">
                  {{ rowIndex + 1 }}
                </td>
                <td
                  class="whitespace-nowrap px-3 py-3 text-sm text-gray-900"
                  v-for="(column, columnIndex) in columns"
                  :key="column.key"
                  :class="columnIndex === 0 ? 'font-semibold' : ''"
                >
                  {{ cellValue(row, column) }}
                </td>
                <td class="whitespace-nowrap px-3 py-3 text-sm text-center">
                  <Button
                    class="bg-red-400 hover:bg-red-500 text-white"
                    @click="remove(row)"
                    v-if="canEdit"
                  >
                    <BackspaceIcon class="w-4 h-4"></BackspaceIcon>
                  </Button>
                </td>
              </tr>
              <tr v-if="!rows.length">
                <td :colspan="columns.length + 2" class="whitespace-nowrap py-4 text-sm font-medium text-red-600 text-center">
                  {{ emptyText }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { BackspaceIcon, PlusCircleIcon } from '@heroicons/vue/20/solid'
import Button from '@/Components/Button.vue'
import MultiSelect from '@/Components/MultiSelect.vue'

const MODE_ALL = 'all'
const MODE_LIST = 'list'

const props = defineProps({
  title: { type: String, required: true },
  addLabel: { type: String, required: true },
  // [{ key: 'code', label: 'Product Code' }, ...]; key may be dotted ('customer.name')
  columns: { type: Array, required: true },
  // currently bound rows (array of objects)
  modelValue: { type: Array, default: () => [] },
  // everything that could be bound (array of objects)
  options: { type: Array, default: () => [] },
  optionLabel: { type: String, default: 'full_name' },
  mode: { type: String, default: MODE_ALL },
  // Machines have no mode column - an empty user_vend genuinely means "all", and
  // there is no cascade-delete path that can empty it behind an admin's back.
  // Pass false there so the radio (which would post nowhere) and the
  // "will see NO x" warning (which would be false) both stay hidden.
  showMode: { type: Boolean, default: true },
  canEdit: { type: Boolean, default: false },
  error: { type: String, default: '' },
  itemNoun: { type: String, default: 'product' },
  subjectNoun: { type: String, default: 'user' },
  // null, or { operatorName, products: [{id, code, name}] }
  ceiling: { type: Object, default: null },
})

const emit = defineEmits(['update:modelValue', 'update:mode'])

// Components/MultiSelect.vue passes :object="true" to @vueform/multiselect and
// emits the whole OPTION OBJECT on update:modelValue - it does NOT honour
// valueProp for the emitted value. So this holds an object, not an id. (It can
// also be a bare id if a caller ever pre-seeds it, hence the normaliser below.)
const selected = ref(null)

const selectedOptionId = computed(() => {
  const raw = selected.value
  if (raw === null || raw === undefined || raw === '') return null
  return typeof raw === 'object' ? raw.id : raw
})

const rows = computed(() => props.modelValue || [])

const allLabel = computed(() =>
  props.ceiling
    ? `All ${props.itemNoun}s the operator can access (${props.ceiling.products.length})`
    : `All ${props.itemNoun}s`
)

const ceilingSummary = computed(() => {
  if (!props.ceiling) return ''
  const names = props.ceiling.products.map(p => p.code || p.name)
  return names.length > 6 ? `${names.slice(0, 6).join(', ')} +${names.length - 6} more` : names.join(', ')
})

const emptyText = computed(() => {
  if (props.showMode && props.mode === MODE_LIST) {
    return `Nothing listed = no ${props.itemNoun} access`
  }
  return props.ceiling
    ? `No Binding = Access to All of ${props.ceiling.operatorName || 'the operator'}'s ${props.ceiling.products.length} ${props.itemNoun}(s)`
    : 'No Binding = Access to All'
})

// Hide already-bound entries instead of splicing the options array, so the list
// stays correct after an add/remove/re-add cycle.
const availableOptions = computed(() => {
  const bound = new Set(rows.value.map(r => r.id))
  return (props.options || []).filter(o => !bound.has(o.id))
})

function cellValue(row, column) {
  return String(column.key)
    .split('.')
    .reduce((acc, part) => (acc == null ? acc : acc[part]), row) ?? ''
}

// Codes are strings like "U-12" / "B-BEC2", so a numeric subtraction sort is a
// no-op (NaN). Natural-order string compare is what actually sorts them.
function byCode(a, b) {
  return String(a.code ?? '').localeCompare(String(b.code ?? ''), undefined, { numeric: true })
}

function add() {
  const id = selectedOptionId.value
  if (id === null) return

  // Prefer the canonical option from the list; fall back to the emitted object
  // itself so a stale/filtered options array cannot make Add a silent no-op.
  const raw = selected.value
  const option = (props.options || []).find(o => o.id === id)
    || (typeof raw === 'object' ? raw : null)

  if (!option) return
  if (rows.value.some(r => r.id === option.id)) return

  emit('update:modelValue', [...rows.value, option].sort(byCode))

  // Adding the first entry only makes sense as a restriction, so flip the mode
  // for the admin rather than making them remember a second control.
  if (props.showMode && props.mode !== MODE_LIST) emit('update:mode', MODE_LIST)

  selected.value = null
}

function remove(row) {
  emit('update:modelValue', rows.value.filter(r => r.id !== row.id))
}
</script>
