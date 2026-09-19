<template>
  <StopIndex
    title="Service Notices"
    basePath="/service-notices"
    exportName="ServiceNotices"
    exportPermission="export service-notices"
    :rows="serviceNotices"
    :statusOptions="statusOptions"
    :driverOptions="driverOptions"
    :serverFilters="filters"
    :extraFilter="INCOMPLETE_FILTER"
  >
    <template #head="{ filters: state, sortTable }">
      <TableHead>Item(s) Done</TableHead>
      <TableHead>Incomplete</TableHead>
      <TableHeadSort modelName="completed_at" :sortKey="state.sortKey" :sortBy="state.sortBy" @sort-table="sortTable('completed_at')">Completed</TableHeadSort>
      <TableHeadSort modelName="created_at" :sortKey="state.sortKey" :sortBy="state.sortBy" @sort-table="sortTable('created_at')">Opened</TableHeadSort>
    </template>
    <template #cells="{ row, index, total }">
      <TableData :currentIndex="index" :totalLength="total" inputClass="text-center">{{ row.items_resolved_count }} / {{ row.items_count }}</TableData>
      <TableData :currentIndex="index" :totalLength="total" inputClass="text-center">
        <span v-if="row.items_incomplete_count" class="text-red-600 font-semibold">{{ row.items_incomplete_count }}</span>
      </TableData>
      <TableData :currentIndex="index" :totalLength="total" inputClass="text-center">
        {{ row.completed_at }}<span v-if="row.completed_by_name"><br>{{ row.completed_by_name }}</span>
      </TableData>
      <TableData :currentIndex="index" :totalLength="total" inputClass="text-center">
        {{ row.created_at }}<span v-if="row.created_by_name"><br>{{ row.created_by_name }}</span>
      </TableData>
    </template>
  </StopIndex>
</template>

<script setup>
import StopIndex from '@/Pages/OpsJob/Stops/StopIndex.vue'
import TableData from '@/Components/TableData.vue'
import TableHead from '@/Components/TableHead.vue'
import TableHeadSort from '@/Components/TableHeadSort.vue'

defineProps({
  serviceNotices: Object,
  statusOptions: Array,
  driverOptions: Array,
  filters: Object,
})

// Where the office chases repairs that could not be finished on the day.
const INCOMPLETE_FILTER = {
  key: 'has_incomplete',
  label: 'Has Incomplete Item?',
  options: [{ id: 'all', value: 'All' }, { id: 'true', value: 'Yes' }],
}
</script>
