<template>
  <StopIndex
    :title="$t('Stock Counts')"
    basePath="/stock-checks"
    exportName="StockCounts"
    exportPermission="export stock-checks"
    :rows="stockChecks"
    :statusOptions="statusOptions"
    :driverOptions="driverOptions"
    :serverFilters="filters"
    :extraFilter="MISMATCH_FILTER"
  >
    <template #head="{ filters: state, sortTable }">
      <TableHead>{{ $t('Channel(s)') }}</TableHead>
      <TableHead>{{ $t('Mismatch') }}</TableHead>
      <TableHead>{{ $t('Variance (pcs)') }}</TableHead>
      <TableHead>{{ $t('Variance (:currency)', { currency: operatorCountry?.currency_symbol ?? '$' }) }}</TableHead>
      <TableHead>{{ $t('Synced') }}</TableHead>
      <TableHeadSort modelName="counted_at" :sortKey="state.sortKey" :sortBy="state.sortBy" @sort-table="sortTable('counted_at')">{{ $t('Counted') }}</TableHeadSort>
    </template>
    <template #cells="{ row, index, total }">
      <TableData :currentIndex="index" :totalLength="total" inputClass="text-center">
        {{ row.channels_count }} <span class="text-xs text-gray-500">{{ row.is_random ? $t('random') : $t('all') }}</span>
      </TableData>
      <TableData :currentIndex="index" :totalLength="total" inputClass="text-center">
        <template v-if="row.status === 3">
          <span v-if="row.mismatch_count" class="text-red-600 font-semibold">{{ row.mismatch_count }}</span>
          <span v-else class="text-green-700">✓</span>
        </template>
      </TableData>
      <TableData :currentIndex="index" :totalLength="total" inputClass="text-center">
        <span v-if="row.status === 3" :class="row.variance_qty < 0 ? 'text-red-600' : ''">{{ signed(row.variance_qty) }}</span>
      </TableData>
      <TableData :currentIndex="index" :totalLength="total" inputClass="text-right">
        <!-- Cents on the wire; divided only here, at the point of display. -->
        <span v-if="row.status === 3" :class="row.variance_value < 0 ? 'text-red-600' : ''">{{ (row.variance_value / 100).toFixed(2) }}</span>
      </TableData>
      <TableData :currentIndex="index" :totalLength="total" inputClass="text-center">
        <span v-if="row.synced_at">{{ row.synced_at }}<br>{{ row.synced_by_name }}</span>
        <span v-else-if="row.unsynced_mismatch_count" class="text-orange-600 text-xs">{{ $t('not synced') }}</span>
      </TableData>
      <TableData :currentIndex="index" :totalLength="total" inputClass="text-center">
        {{ row.counted_at }}<span v-if="row.counted_by_name"><br>{{ row.counted_by_name }}</span>
      </TableData>
    </template>
  </StopIndex>
</template>

<script setup>
import StopIndex from '@/Pages/OpsJob/Stops/StopIndex.vue'
import TableData from '@/Components/TableData.vue'
import TableHead from '@/Components/TableHead.vue'
import TableHeadSort from '@/Components/TableHeadSort.vue'
import { usePage } from '@inertiajs/vue3'
import { t } from '@/i18n'

defineProps({
  stockChecks: Object,
  statusOptions: Array,
  driverOptions: Array,
  filters: Object,
})

const operatorCountry = usePage().props.auth.operatorCountry

const MISMATCH_FILTER = {
  key: 'mismatch',
  label: t('Result'),
  options: [{ id: 'all', value: t('All') }, { id: 'true', value: t('Any mismatch') }, { id: 'short', value: t('Short only') }],
}

function signed(value) {
  const n = Number(value || 0)
  return (n > 0 ? '+' : '') + n
}
</script>
