<template>
  <!--
    The machine level of a product-limited user (Brian, 2026-10-27): the machines
    carrying their products RIGHT NOW, each Show (default) / No Show. Built by
    App\Services\Access\UserMachineVisibility; a switch saves at once.
    Same full-width stack as AccessBindingSection (the host grid is sm:grid-cols-6).
  -->
  <div class="col-span-12 sm:col-span-6">
    <div class="relative pt-2 pb-1 md:pt-5 md:pb-3">
      <div class="absolute inset-0 flex items-center" aria-hidden="true">
        <div class="w-full border-t border-gray-300"></div>
      </div>
      <div class="relative flex justify-center">
        <span class="px-3 bg-white text-lg font-medium text-gray-900"> {{ $t('Access Vending Machine(s)') }} </span>
      </div>
    </div>

    <p v-if="pendingSave" class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
      {{ $t('Save to see the machines that carry these products.') }}
    </p>

    <template v-else>
      <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-700">
        <span>{{ $t('The machines carrying this user’s products right now. Switch one to No Show to hide it from this user.') }}</span>
        <span class="font-medium" v-if="rows.length">
          {{ $t(':shown shown · :hidden hidden', { shown: shownCount, hidden: rows.length - shownCount }) }}
        </span>
      </div>
      <div class="text-sm text-red-600" v-if="error">{{ error }}</div>

      <div class="mt-3 overflow-x-auto">
        <div class="inline-block min-w-full align-middle">
          <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
            <table class="min-w-full divide-y divide-gray-300">
              <thead class="bg-gray-50">
                <tr>
                  <th scope="col" class="w-12 px-3 py-3 text-center text-sm font-semibold text-gray-900">#</th>
                  <th
                    v-for="col in columns"
                    :key="col.key"
                    scope="col"
                    class="px-3 py-3 text-sm font-semibold text-gray-900"
                    :class="[col.align === 'right' ? 'text-right' : (col.align === 'center' ? 'text-center' : 'text-left'), col.width || '']"
                    :aria-sort="sortKey === col.key ? (sortAsc ? 'ascending' : 'descending') : 'none'"
                  >
                    <button
                      type="button"
                      class="inline-flex items-center gap-0.5 text-blue-600 hover:text-blue-800"
                      @click="sortBy(col.key)"
                    >
                      {{ $t(col.label) }}
                      <svg v-if="sortKey === col.key" xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path v-if="sortAsc" stroke-linecap="round" stroke-linejoin="round" d="M5 15l7-7 7 7" />
                        <path v-else stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                      </svg>
                    </button>
                  </th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-if="!rows.length">
                  <td :colspan="columns.length + 1" class="px-3 py-4 text-center text-sm text-gray-500">
                    {{ $t('No machine carries these products right now.') }}
                  </td>
                </tr>
                <tr v-for="(row, rowIndex) in sortedRows" :key="row.id" :class="[rowIndex % 2 === 0 ? undefined : 'bg-gray-50', row.is_shown ? '' : 'text-gray-400']">
                  <td class="whitespace-nowrap px-3 py-3 text-sm text-center">{{ rowIndex + 1 }}</td>
                  <td class="whitespace-nowrap px-3 py-3 text-sm font-semibold" :class="row.is_shown ? 'text-gray-900' : ''">
                    <MachineIdLink :code="row.code" />
                    <span v-if="!row.is_active" class="ml-1 rounded bg-gray-200 px-1.5 py-0.5 text-xs font-medium text-gray-600">{{ $t('Inactive') }}</span>
                  </td>
                  <td class="whitespace-nowrap px-3 py-3 text-sm">{{ row.prefix || '—' }}</td>
                  <td class="px-3 py-3 text-sm">
                    <a v-if="row.site && row.site_id" :href="'/customers/' + row.site_id + '/edit'" target="_blank" rel="noopener" class="text-blue-700 hover:underline">{{ row.site }}</a>
                    <template v-else>{{ row.site || '—' }}</template>
                  </td>
                  <td class="whitespace-nowrap px-3 py-3 text-sm text-right tabular-nums">
                    <a v-if="row.machine_l30d" :href="l30dTransactionsUrl(row)" target="_blank" rel="noopener" class="text-blue-700 hover:underline">{{ salesText(row.machine_l30d) }}</a>
                    <template v-else>{{ salesText(row.machine_l30d) }}</template>
                  </td>
                  <td class="px-3 py-3 text-sm">
                    <template v-if="row.carries">{{ row.products.join(', ') }}</template>
                    <span v-else class="italic">{{ $t('No longer carries their products') }}</span>
                  </td>
                  <td class="whitespace-nowrap px-3 py-3 text-sm tabular-nums">
                    <template v-if="skuStartLines(row).length > 1">
                      <div v-for="line in skuStartLines(row)" :key="line.code">
                        <span class="text-gray-500">{{ line.code }}</span> {{ line.date }}
                      </div>
                    </template>
                    <template v-else>{{ row.sku_start_date || '—' }}</template>
                  </td>
                  <td class="whitespace-nowrap px-3 py-3 text-sm text-right tabular-nums">{{ salesText(row.sku_l30d) }}</td>
                  <td class="whitespace-nowrap px-3 py-2 text-sm text-center">
                    <div class="inline-flex rounded-md shadow-sm" role="group">
                      <button
                        type="button"
                        class="px-3 py-1.5 text-xs font-medium border rounded-l-md"
                        :class="row.is_shown ? 'bg-green-600 border-green-600 text-white' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'"
                        :disabled="!canEdit || busyId === row.id || row.is_shown"
                        @click="setShown(row, true)"
                      >
                        {{ $t('Show') }}
                      </button>
                      <button
                        type="button"
                        class="px-3 py-1.5 text-xs font-medium border-t border-b border-r rounded-r-md"
                        :class="!row.is_shown ? 'bg-red-600 border-red-600 text-white' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50'"
                        :disabled="!canEdit || busyId === row.id || !row.is_shown"
                        @click="setShown(row, false)"
                      >
                        {{ $t('No Show') }}
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import axios from 'axios'
import { usePage } from '@inertiajs/vue3'
import moment from 'moment'
import { t, tk } from '@/i18n'
import MachineIdLink from '@/Components/MachineIdLink.vue'

const props = defineProps({
  userId: { type: [Number, String], required: true },
  machines: { type: Array, default: () => [] },
  canEdit: { type: Boolean, default: false },
  // The product mode on screen differs from the saved one: the list below
  // would describe the old setting, so ask for a Save instead.
  pendingSave: { type: Boolean, default: false },
})

const rows = ref([...props.machines])
watch(() => props.machines, (machines) => { rows.value = [...machines] })

const busyId = ref(null)
const error = ref('')
const shownCount = computed(() => rows.value.filter((row) => row.is_shown).length)

// Sortable headers (Brian, 2026-10-27). Client-side: the list is one machine set,
// already complete. The sort survives a Show / No Show save, which replaces rows.
const columns = [
  { key: 'code', label: tk('Machine ID') },
  { key: 'prefix', label: tk('Prefix') },
  { key: 'site', label: tk('Site') },
  { key: 'machine_l30d', label: tk('Machine L30d Sales'), align: 'right' },
  { key: 'products', label: tk('Their products here') },
  // When their products went into the machine (first refill that loaded them),
  // not the Site's begin date. Sorts on the earliest.
  { key: 'sku_start_date', label: tk('SKU Start Date') },
  { key: 'sku_l30d', label: tk('SKU L30d Sales'), align: 'right' },
  { key: 'is_shown', label: tk('Visibility'), align: 'center', width: 'w-48' },
]
// Figures sort biggest first on the first click; text and dates A–Z / oldest first.
const DESC_FIRST = ['machine_l30d', 'sku_l30d']

const sortKey = ref('code')
const sortAsc = ref(true)

function sortBy(key) {
  if (sortKey.value === key) {
    sortAsc.value = !sortAsc.value
  } else {
    sortKey.value = key
    sortAsc.value = !DESC_FIRST.includes(key)
  }
}

function sortValue(row, key) {
  switch (key) {
    case 'code': return row.code
    case 'machine_l30d':
    case 'sku_l30d': return row[key] ? row[key].amount : null
    case 'products': return row.carries ? row.products.join(', ') : null
    case 'is_shown': return row.is_shown ? 1 : 0
    default: return row[key] || null
  }
}

// One date when every product went in on the same day, else a line per product.
function skuStartLines(row) {
  const dates = row.sku_start_dates || {}
  if (new Set(Object.values(dates)).size <= 1) return []
  return Object.entries(dates).map(([code, date]) => ({ code, date }))
}

const collator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' })

const sortedRows = computed(() => {
  const key = sortKey.value
  const dir = sortAsc.value ? 1 : -1
  return [...rows.value].sort((a, b) => {
    const va = sortValue(a, key)
    const vb = sortValue(b, key)
    // Blanks always last, whichever way.
    if (va === null && vb === null) return collator.compare(a.code, b.code)
    if (va === null) return 1
    if (vb === null) return -1
    const cmp = typeof va === 'number' && typeof vb === 'number' ? va - vb : collator.compare(String(va), String(vb))
    return cmp !== 0 ? cmp * dir : collator.compare(a.code, b.code)
  })
})

const operatorCountry = usePage().props.auth?.operatorCountry
function salesText(figure) {
  if (!figure) return '—'
  const exponent = operatorCountry?.currency_exponent ?? 2
  const symbol = operatorCountry?.currency_symbol ?? '$'
  const amount = (figure.amount / Math.pow(10, exponent)).toLocaleString(undefined, {
    minimumFractionDigits: operatorCountry?.is_currency_exponent_hidden ? 0 : exponent,
    maximumFractionDigits: operatorCountry?.is_currency_exponent_hidden ? 0 : exponent,
  })
  return `${symbol}${amount} (${figure.count.toLocaleString()})`
}

// The machine's sales over the same window as the L30d figure (ProductScopedSales:
// today and the 29 days before). The trailing comma makes the code match exactly.
function l30dTransactionsUrl(row) {
  return '/vends/transactions?' + new URLSearchParams({
    codes: row.code + ',',
    date_from: moment().subtract(29, 'days').format('YYYY-MM-DD'),
    date_to: moment().format('YYYY-MM-DD'),
  }).toString()
}

async function setShown(row, show) {
  if (!props.canEdit) return
  busyId.value = row.id
  error.value = ''
  try {
    const { data } = await axios.post(`/users/${props.userId}/machine-visibility`, { vend_id: row.id, show })
    rows.value = data.machines || []
  } catch (e) {
    error.value = e?.response?.data?.message || t('Could not save. Please try again.')
  } finally {
    busyId.value = null
  }
}
</script>
