<template>
  <!-- One <tr> for a Service Notice or a Stock Count, in the job table's seven
       columns: sequence · what · status · progress · cash · address · action. -->
  <tr :class="type.rowClasses[rowIndex % 2]">
    <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
      <div class="flex items-center justify-center">
        <input
          type="text"
          class="shadow-sm block w-fit text-sm rounded-md max-w-14 text-center"
          :class="type.inputClass"
          v-model.lazy="row.sequence"
          :disabled="!canUpdate"
          @change="saveSequence"
        />
      </div>
    </td>

    <td class="whitespace-pre-line py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
      <div class="flex flex-col space-y-2 max-w-24 items-center">
        <span class="inline-flex items-center justify-center rounded px-2 py-0.5 text-xs font-bold w-fit" :class="type.badgeClass">{{ type.badge }}</span>
        <Link :href="editUrl" class="underline break-words" :class="type.textClass">{{ row.display_code }}</Link>
        <span class="text-gray-800">{{ row.vend_code }}</span>
      </div>
    </td>

    <td class="whitespace py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left">
      <div class="flex flex-col space-y-1 max-w-40">
        <span class="inline-flex justify-center items-center rounded px-1 py-0.5 text-xs font-medium border w-fit" :class="stopStatusClass(row.status)">
          {{ row.status_name }}
        </span>
        <span v-if="row.created_at" class="text-xs font-medium text-gray-600">
          {{ row.created_at }}
          <span v-if="row.created_by_name">({{ row.created_by_name }})</span>
        </span>
        <span class="text-xs text-gray-700 break-words">{{ row.customer_name }}</span>
        <span class="text-left font-medium bg-gray-200 py-1 px-1 rounded" v-if="row.remarks">{{ row.remarks }}</span>
      </div>
    </td>

    <td class="whitespace-pre-line py-4 pl-4 pr-3 text-sm font-semibold sm:pl-6 text-center align-top" :class="type.textClass">
      <!-- Service notice: verdicts given / items. Stock count: channels + variance. -->
      <div v-if="row.stop_type === 'service_notice'" class="flex flex-col">
        <span>{{ row.items_resolved_count }} / {{ row.items_count }}</span>
        <span class="text-xs font-normal text-gray-500">item(s) done</span>
        <span v-if="row.items_incomplete_count" class="text-xs text-red-600">{{ row.items_incomplete_count }} incomplete</span>
      </div>
      <div v-else class="flex flex-col">
        <span>{{ row.channels_count }}</span>
        <span class="text-xs font-normal text-gray-500">
          {{ row.is_random ? 'random' : 'all' }} channel(s)
        </span>
        <template v-if="row.status === 3">
          <span v-if="row.mismatch_count" class="text-xs text-red-600">
            {{ row.mismatch_count }} mismatch ({{ signed(row.variance_qty) }} pcs)
          </span>
          <span v-else class="text-xs text-green-700">all match</span>
          <span v-if="row.unsynced_mismatch_count" class="text-xs text-orange-600">not synced</span>
        </template>
      </div>
    </td>

    <td class="whitespace-pre-line py-4 pl-4 pr-3 text-sm text-center text-gray-400">—</td>

    <td class="whitespace-pre-line py-4 px-1 text-sm text-left">
      <div class="flex flex-col space-y-1 break-words max-w-32 md:max-w-52">
        <span>{{ row.address?.full_address }}</span>
        <span class="text-xs text-gray-500">{{ row.address?.postcode }}</span>
      </div>
    </td>

    <td class="whitespace-nowrap py-4 px-1 text-sm text-center">
      <Link :href="editUrl">
        <Button :class="type.buttonClass">
          <div class="flex space-x-2 items-center">
            <PencilSquareIcon class="h-3 w-3" />
            <span>{{ row.status === 1 ? 'Open' : 'View' }}</span>
          </div>
        </Button>
      </Link>
    </td>
  </tr>
</template>

<script setup>
import Button from '@/Components/Button.vue'
import { PencilSquareIcon } from '@heroicons/vue/20/solid'
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import { STOP_TYPES, stopStatusClass } from './stopTypes'

const props = defineProps({
  row: Object,
  rowIndex: Number,
  canUpdate: Boolean,
})

const type = computed(() => STOP_TYPES[props.row.stop_type])
const editUrl = computed(() => type.value.basePath + '/' + props.row.id + '/edit')

function signed(value) {
  const n = Number(value || 0)
  return (n > 0 ? '+' : '') + n
}

function saveSequence() {
  axios.post(type.value.basePath + '/' + props.row.id + '/update-sequence', {
    sequence: props.row.sequence !== '' && props.row.sequence != null ? parseFloat(props.row.sequence) : null,
  }).catch(error => console.error('Failed to update sequence', error))
}
</script>
