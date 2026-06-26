<template>
  <Head title="Machine Alert Parameters" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Machine Alert Parameters
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-2">
          <SearchInput placeholderStr="Machine ID" v-model="filters.code" @keyup.enter="onSearch">
            Machine ID
          </SearchInput>
          <SearchInput placeholderStr="Site Name" v-model="filters.name" @keyup.enter="onSearch">
            Site Name
          </SearchInput>
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700">
              Operator
            </label>
            <MultiSelect
              v-model="filters.operators"
              :options="operatorOptions"
              trackBy="id"
              valueProp="id"
              label="full_name"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
              mode="tags"
            />
          </div>
        </div>

        <div class="flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5">
          <div class="mt-3">
            <div class="flex flex-col space-y-1 md:flex-row md:space-y-0 md:space-x-1">
              <Button
                class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
                @click="onSearch"
              >
                <MagnifyingGlassIcon class="h-4 w-4" aria-hidden="true" />
                <span>
                  Search
                </span>
              </Button>
            </div>
          </div>
          <div class="flex flex-col space-y-2 md:items-end">
            <p class="text-sm text-gray-700 leading-5 flex space-x-1">
              <span>Showing</span>
              <span class="font-medium">{{ paginationMeta.from ?? 0 }}</span>
              <span>to</span>
              <span class="font-medium">{{ paginationMeta.to ?? 0 }}</span>
              <span>of</span>
              <span class="font-medium">{{ paginationMeta.total ?? 0 }}</span>
              <span>results</span>
            </p>
            <MultiSelect
              v-model="filters.numberPerPage"
              :options="numberPerPageOptions"
              trackBy="id"
              valueProp="id"
              label="value"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1 w-40"
              @selected="onSearch"
            />
          </div>
        </div>
      </div>

      <div class="mt-6 flex flex-col">
        <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8 px-3">
          <div class="bg-white border rounded-md shadow-sm p-4">
            <div class="flex flex-col space-y-4 lg:space-y-0 lg:flex-row lg:items-end lg:justify-between">
              <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:items-end w-full lg:w-auto">
                <div class="flex flex-col space-y-1">
                  <label for="bulk-parameter" class="block text-sm font-medium text-gray-700">
                    Alert Parameter
                  </label>
                  <select
                    id="bulk-parameter"
                    class="block w-full rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500"
                    v-model="selectedBulkFieldId"
                  >
                    <option value="">Select parameter</option>
                    <option v-for="option in bulkFieldOptions" :key="option.id" :value="option.id">
                      {{ option.label }}
                    </option>
                  </select>
                  <p v-if="bulkParameterError" class="text-sm text-red-600">
                    {{ bulkParameterError }}
                  </p>
                </div>

                <div v-if="selectedBulkField" class="flex flex-col space-y-1">
                  <label :for="`bulk-value-${selectedBulkField.id}`" class="block text-sm font-medium text-gray-700">
                    {{ selectedBulkField.inputLabel }}
                  </label>
                  <div class="flex flex-col sm:flex-row sm:items-center sm:space-x-4 space-y-2 sm:space-y-0">
                    <input
                      :id="`bulk-value-${selectedBulkField.id}`"
                      type="number"
                      min="0"
                      step="1"
                      class="block w-full sm:w-44 rounded-md border-gray-300 shadow-sm focus:border-green-500 focus:ring-green-500 disabled:bg-gray-100 disabled:text-gray-500"
                      :placeholder="selectedBulkField.placeholder"
                      :disabled="clearOverride"
                      v-model="bulkValue"
                    />
                    <label class="inline-flex items-center text-sm text-gray-600">
                      <input
                        type="checkbox"
                        class="mr-2 h-4 w-4 rounded border-gray-300 text-green-600 focus:ring-green-500"
                        v-model="clearOverride"
                      />
                      Clear override (use default)
                    </label>
                  </div>
                  <p v-if="currentFieldError" class="text-sm text-red-600">
                    {{ currentFieldError }}
                  </p>
                </div>
              </div>

              <div class="flex flex-col items-start lg:items-end space-y-2">
                <span class="text-sm text-gray-600">
                  Selected {{ selectedCount }} machine(s)
                </span>
                <Button
                  class="inline-flex items-center rounded-md border border-green bg-green-500 px-6 py-2 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-green-300"
                  :disabled="!canApplyBulk || bulkForm.processing"
                  @click="applyBulkUpdate"
                >
                  <ArrowPathIcon v-if="bulkForm.processing" class="h-4 w-4 mr-2 animate-spin" aria-hidden="true" />
                  <span>Apply</span>
                </Button>
                <p v-if="bulkForm.errors.vend_ids" class="text-sm text-red-600">
                  {{ bulkForm.errors.vend_ids }}
                </p>
              </div>
            </div>
          </div>

          <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-auto mt-4">
            <table class="min-w-full border-separate" style="border-spacing: 0">
              <thead class="bg-gray-100">
                <tr class="divide-x divide-gray-200">
                  <TableHead inputClass="w-14">
                    <input
                      ref="selectAllRef"
                      type="checkbox"
                      class="h-4 w-4 rounded border-gray-300 text-green-600 focus:ring-green-500"
                      :checked="allSelected"
                      @change="toggleSelectAll($event)"
                    />
                  </TableHead>
                  <TableHead>
                    Machine ID
                  </TableHead>
                  <TableHead>
                    Site Name
                  </TableHead>
                  <TableHead>
                    Operator
                  </TableHead>
                  <TableHead>
                    Offline Threshold
                  </TableHead>
                  <TableHead>
                    Power Restored Threshold
                  </TableHead>
                  <TableHead>
                    No Sales Threshold
                  </TableHead>
                  <TableHead>
                    Last Transaction
                  </TableHead>
                  <TableHead>
                    Last Sync
                  </TableHead>
                </tr>
              </thead>
              <tbody class="bg-white">
                <tr
                  v-for="(vend, index) in vendRows"
                  :key="vend.id"
                  class="divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100"
                >
                  <TableData
                    :currentIndex="index"
                    :totalLength="vendRows.length"
                    inputClass="text-center"
                  >
                    <input
                      type="checkbox"
                      class="h-4 w-4 rounded border-gray-300 text-green-600 focus:ring-green-500"
                      :checked="selectedVendIds.has(vend.id)"
                      @change="toggleSelect(vend.id, $event)"
                    />
                  </TableData>
                  <TableData
                    :currentIndex="index"
                    :totalLength="vendRows.length"
                    inputClass="text-center"
                  >
                    <span class="font-semibold text-gray-900">
                      {{ vend.code }}
                    </span>
                  </TableData>
                  <TableData
                    :currentIndex="index"
                    :totalLength="vendRows.length"
                    inputClass="text-left"
                  >
                    {{ vend.display_name ?? vend.customer?.name ?? '-' }}
                  </TableData>
                  <TableData
                    :currentIndex="index"
                    :totalLength="vendRows.length"
                    inputClass="text-center"
                  >
                    {{ vend.operator?.name ?? '-' }}
                  </TableData>
                  <TableData
                    :currentIndex="index"
                    :totalLength="vendRows.length"
                    inputClass="text-center"
                  >
                    <div class="flex flex-col">
                      <span>{{ formatMinutes(vend.offline_alert_minutes) }}</span>
                      <span class="text-xs" :class="vend.overrides.offline_after_minutes !== null ? 'text-green-600' : 'text-gray-500'">
                        {{ vend.overrides.offline_after_minutes !== null ? 'Custom' : `Default ${vend.defaults.offline_after_minutes} mins` }}
                      </span>
                    </div>
                  </TableData>
                  <TableData
                    :currentIndex="index"
                    :totalLength="vendRows.length"
                    inputClass="text-center"
                  >
                    <div class="flex flex-col">
                      <span>{{ formatMinutes(vend.power_restored_alert_minutes) }}</span>
                      <span class="text-xs" :class="vend.overrides.power_restored_after_minutes !== null ? 'text-green-600' : 'text-gray-500'">
                        {{ vend.overrides.power_restored_after_minutes !== null ? 'Custom' : `Default ${vend.defaults.power_restored_after_minutes} mins` }}
                      </span>
                    </div>
                  </TableData>
                  <TableData
                    :currentIndex="index"
                    :totalLength="vendRows.length"
                    inputClass="text-center"
                  >
                    <div class="flex flex-col">
                      <span>{{ formatHours(vend.no_sales_alert_hours) }}</span>
                      <span class="text-xs" :class="vend.overrides.no_sales_after_hours !== null ? 'text-green-600' : 'text-gray-500'">
                        {{ vend.overrides.no_sales_after_hours !== null ? 'Custom' : `Default ${vend.defaults.no_sales_after_hours} hrs` }}
                      </span>
                    </div>
                  </TableData>
                  <TableData
                    :currentIndex="index"
                    :totalLength="vendRows.length"
                    inputClass="text-center"
                  >
                    {{ formatDateTime(vend.last_vend_transaction_at) }}
                  </TableData>
                  <TableData
                    :currentIndex="index"
                    :totalLength="vendRows.length"
                    inputClass="text-center"
                  >
                    {{ formatDateTime(vend.last_updated_at) }}
                  </TableData>
                </tr>
                <tr v-if="vendRows.length === 0">
                  <td colspan="9" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium text-gray-500 text-center">
                    No results found
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator v-if="vendRows.length" :links="props.vends.links" :meta="props.vends.meta" />
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import moment from 'moment'
import { Head, router, useForm } from '@inertiajs/vue3'
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import Button from '@/Components/Button.vue'
import SearchInput from '@/Components/SearchInput.vue'
import MultiSelect from '@/Components/MultiSelect.vue'
import Paginator from '@/Components/Paginator.vue'
import TableHead from '@/Components/TableHead.vue'
import TableData from '@/Components/TableData.vue'
import { BackspaceIcon, MagnifyingGlassIcon, ArrowPathIcon } from '@heroicons/vue/20/solid'

const props = defineProps({
  vends: Object,
  operatorOptions: Object,
  filters: Object,
})

const numberPerPageOptions = ref([])
const operatorOptions = ref([])

const filters = ref({
  code: props.filters?.code ?? '',
  name: props.filters?.name ?? '',
  operators: [],
  numberPerPage: null,
})

const bulkFieldOptions = [
  { id: 'offline_after_minutes', label: 'Offline Threshold (mins)', inputLabel: 'Minutes', placeholder: 'Enter minutes' },
  { id: 'power_restored_after_minutes', label: 'Power Restored Threshold (mins)', inputLabel: 'Minutes', placeholder: 'Enter minutes' },
  { id: 'no_sales_after_hours', label: 'No Sales Threshold (hours)', inputLabel: 'Hours', placeholder: 'Enter hours' },
]

const selectedBulkFieldId = ref('')
const bulkValue = ref('')
const clearOverride = ref(false)

const bulkForm = useForm({
  vend_ids: [],
})

const selectedVendIds = ref(new Set())
const selectAllRef = ref(null)

const vendRows = computed(() => props.vends?.data ?? [])
const paginationMeta = computed(() => props.vends?.meta ?? {})

const selectedCount = computed(() => selectedVendIds.value.size)
const allSelected = computed(() => {
  return vendRows.value.length > 0 && vendRows.value.every(vend => selectedVendIds.value.has(vend.id))
})
const indeterminate = computed(() => selectedCount.value > 0 && !allSelected.value)

const selectedBulkField = computed(() => {
  return bulkFieldOptions.find(option => option.id === selectedBulkFieldId.value) ?? null
})

const bulkParameterError = computed(() => bulkForm.errors.bulk_parameter ?? null)

const currentFieldError = computed(() => {
  if (!selectedBulkField.value) {
    return null
  }
  return bulkForm.errors[selectedBulkField.value.id] ?? null
})

const canApplyBulk = computed(() => {
  if (selectedCount.value === 0 || !selectedBulkField.value) {
    return false
  }
  return clearOverride.value || bulkValue.value !== ''
})

onMounted(() => {
  numberPerPageOptions.value = [
    { id: 100, value: 100 },
    { id: 200, value: 200 },
    { id: 500, value: 500 },
    { id: 'All', value: 'All' },
  ]

  const defaultPerPage = numberPerPageOptions.value.find(option => option.id === props.filters?.numberPerPage) ?? numberPerPageOptions.value[0]
  filters.value.numberPerPage = defaultPerPage

  operatorOptions.value = [
    { id: 'all', full_name: 'All Operators' },
    ...(props.operatorOptions?.data ?? []).map(operator => ({
      id: operator.id,
      full_name: operator.full_name,
    })),
  ]

  const selectedOperators = props.filters?.operators ?? 'all'
  if (selectedOperators === 'all' || (Array.isArray(selectedOperators) && selectedOperators.length === 0)) {
    filters.value.operators = [operatorOptions.value[0]]
  } else {
    const operatorIds = Array.isArray(selectedOperators) ? selectedOperators : [selectedOperators]
    filters.value.operators = operatorOptions.value.filter(option => operatorIds.includes(option.id))
  }
})

watch(
  () => filters.value.operators,
  (current) => {
    if (!Array.isArray(current)) {
      return
    }

    const hasAll = current.some(option => option?.id === 'all')
    if (hasAll && current.length > 1) {
      filters.value.operators = current.filter(option => option?.id !== 'all')
    }
  }
)

watch(
  () => props.vends?.data,
  () => {
    // Remove selections for machines that are no longer in the dataset
    const currentIds = new Set(vendRows.value.map(vend => vend.id))
    selectedVendIds.value = new Set([...selectedVendIds.value].filter(id => currentIds.has(id)))
  }
)

watch(
  () => selectedBulkFieldId.value,
  () => {
    bulkValue.value = ''
    clearOverride.value = false
    bulkForm.clearErrors()
  }
)

watch(
  () => clearOverride.value,
  (enabled) => {
    if (enabled) {
      bulkValue.value = ''
    }
    if (selectedBulkField.value) {
      bulkForm.clearErrors(selectedBulkField.value.id)
    }
  }
)

watch(
  () => indeterminate.value,
  () => {
    if (selectAllRef.value) {
      selectAllRef.value.indeterminate = indeterminate.value
    }
  },
  { immediate: true }
)

function onSearch() {
  const selectedOperators = Array.isArray(filters.value.operators) ? filters.value.operators : []
  const hasAll = selectedOperators.find(option => option?.id === 'all')
  const operatorsParam = hasAll || selectedOperators.length === 0
    ? 'all'
    : selectedOperators.map(option => option.id)

  router.get('/machine-alert-parameters', {
    code: filters.value.code || undefined,
    name: filters.value.name || undefined,
    operators: operatorsParam,
    numberPerPage: filters.value.numberPerPage?.id ?? 100,
  }, { preserveState: true, replace: true })
}

function resetFilters() {
  router.get('/machine-alert-parameters')
}

function toggleSelectAll(event) {
  if (event.target.checked) {
    selectedVendIds.value = new Set(vendRows.value.map(vend => vend.id))
  } else {
    selectedVendIds.value = new Set()
  }
  bulkForm.clearErrors('vend_ids')
}

function toggleSelect(vendId, event) {
  const next = new Set(selectedVendIds.value)
  if (event.target.checked) {
    next.add(vendId)
  } else {
    next.delete(vendId)
  }
  selectedVendIds.value = next
  bulkForm.clearErrors('vend_ids')
}

function parseNumber(value) {
  if (value === '' || value === null || value === undefined) {
    return null
  }
  const parsed = Number(value)
  return Number.isNaN(parsed) ? null : Math.trunc(parsed)
}

function applyBulkUpdate() {
  bulkForm.clearErrors()

  const vendIds = Array.from(selectedVendIds.value)
  if (!vendIds.length) {
    bulkForm.setError('vend_ids', 'Select at least one machine.')
    return
  }

  const field = selectedBulkField.value
  if (!field) {
    bulkForm.setError('bulk_parameter', 'Select an alert parameter to update.')
    return
  }

  if (!clearOverride.value && bulkValue.value === '') {
    bulkForm.setError(field.id, 'Enter a value or choose "Clear override".')
    return
  }

  const payload = {
    vend_ids: vendIds,
  }

  if (clearOverride.value) {
    payload[field.id] = null
  } else {
    const parsed = parseNumber(bulkValue.value)
    if (parsed === null) {
      bulkForm.setError(field.id, 'Enter a valid number.')
      return
    }
    payload[field.id] = parsed
  }

  bulkForm.transform(() => payload).post('/machine-alert-parameters/bulk-update', {
    preserveScroll: true,
    onSuccess: () => {
      bulkForm.reset()
      selectedVendIds.value = new Set()
      bulkValue.value = ''
      clearOverride.value = false
    },
  })
}

function formatMinutes(value) {
  if (value === null || value === undefined) {
    return '-'
  }
  return `${value} mins`
}

function formatHours(value) {
  if (value === null || value === undefined) {
    return '-'
  }
  return `${value} hrs`
}

function formatDateTime(value) {
  if (!value) {
    return '-'
  }
  return moment(value).format('YYMMDD hh:mma')
}
</script>
