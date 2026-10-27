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
                  <th scope="col" class="px-3 py-3 text-left text-sm font-semibold text-gray-900">{{ $t('Machine ID') }}</th>
                  <th scope="col" class="px-3 py-3 text-left text-sm font-semibold text-gray-900">{{ $t('Site') }}</th>
                  <th scope="col" class="px-3 py-3 text-left text-sm font-semibold text-gray-900">{{ $t('Their products here') }}</th>
                  <th scope="col" class="w-48 px-3 py-3 text-center text-sm font-semibold text-gray-900">{{ $t('Visibility') }}</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <tr v-if="!rows.length">
                  <td colspan="5" class="px-3 py-4 text-center text-sm text-gray-500">
                    {{ $t('No machine carries these products right now.') }}
                  </td>
                </tr>
                <tr v-for="(row, rowIndex) in rows" :key="row.id" :class="[rowIndex % 2 === 0 ? undefined : 'bg-gray-50', row.is_shown ? '' : 'text-gray-400']">
                  <td class="whitespace-nowrap px-3 py-3 text-sm text-center">{{ rowIndex + 1 }}</td>
                  <td class="whitespace-nowrap px-3 py-3 text-sm font-semibold" :class="row.is_shown ? 'text-gray-900' : ''">
                    {{ row.code }}
                    <span v-if="!row.is_active" class="ml-1 rounded bg-gray-200 px-1.5 py-0.5 text-xs font-medium text-gray-600">{{ $t('Inactive') }}</span>
                  </td>
                  <td class="px-3 py-3 text-sm">{{ row.site || '—' }}</td>
                  <td class="px-3 py-3 text-sm">
                    <template v-if="row.carries">{{ row.products.join(', ') }}</template>
                    <span v-else class="italic">{{ $t('No longer carries their products') }}</span>
                  </td>
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
import { t } from '@/i18n'

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
