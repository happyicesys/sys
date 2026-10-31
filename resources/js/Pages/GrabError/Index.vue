<template>
  <Head :title="$t('Grab Errors')" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ $t('Grab Errors') }}
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-3 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 py-3">
        <p class="text-sm text-gray-600 mb-3">
          {{ $t('Every failed exchange with Grab: our calls Grab refused or never answered, Grab\'s callbacks we answered with an error, and orders Grab has that never reached ConnectVend. Kept :days days.', { days: keepDays }) }}
        </p>
        <div class="flex flex-wrap gap-2 mb-3">
          <button
            v-for="chip in directionChips"
            :key="chip.key"
            type="button"
            class="rounded-full border px-3 py-1 text-sm"
            :class="filters.direction === chip.key ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
            @click="filters.direction = chip.key; search()"
          >{{ chip.name }}</button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-2">
          <div>
            <label class="block text-sm font-medium text-gray-700">{{ $t('What') }}</label>
            <select v-model="filters.action" :class="inputClass">
              <option value="all">{{ $t('All') }}</option>
              <option v-for="option in actionOptions" :key="option.id" :value="option.id">{{ $t(option.name) }}</option>
            </select>
          </div>
          <SearchInput :placeholderStr="$t('Store, order ID or message')" v-model="filters.search" @keyup.enter="search()">
            {{ $t('Search') }}
          </SearchInput>
          <div>
            <label class="block text-sm font-medium text-gray-700">{{ $t('From') }}</label>
            <input v-model="filters.date_from" type="date" :class="inputClass" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">{{ $t('To') }}</label>
            <input v-model="filters.date_to" type="date" :class="inputClass" />
          </div>
        </div>
        <div class="mt-3 flex items-center justify-between">
          <Button
            class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-2 text-sm font-medium text-white hover:bg-green-600"
            @click="search()"
          >
            <MagnifyingGlassIcon class="h-4 w-4" aria-hidden="true" />
            <span>{{ $t('Search') }}</span>
          </Button>
          <p class="text-sm text-gray-700">
            {{ $t('Showing :from to :to of :total results', { from: errors.meta.from ?? 0, to: errors.meta.to ?? 0, total: errors.meta.total }) }}
          </p>
        </div>
      </div>

      <div class="mt-4 -mx-3 sm:-mx-6 lg:-mx-8 shadow-sm ring-1 ring-black ring-opacity-5 overflow-x-auto bg-white">
        <table class="min-w-full border-separate" style="border-spacing: 0">
          <thead class="bg-gray-100">
            <tr class="divide-x divide-gray-200">
              <TableHead>{{ $t('When') }}</TableHead>
              <TableHead>{{ $t('Direction') }}</TableHead>
              <TableHead>{{ $t('What') }}</TableHead>
              <TableHead>{{ $t('Grab store') }}</TableHead>
              <TableHead>{{ $t('Order') }}</TableHead>
              <TableHead>{{ $t('Status') }}</TableHead>
              <TableHead>{{ $t('Message') }}</TableHead>
            </tr>
          </thead>
          <tbody>
            <template v-for="(error, index) in rows" :key="error.id">
              <tr class="divide-x divide-gray-200 align-top cursor-pointer hover:bg-indigo-50" :class="index % 2 ? 'bg-gray-50' : 'bg-white'" @click="toggle(error.id)">
                <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-left whitespace-nowrap">
                  {{ formatDateTime(error.at) }}
                </TableData>
                <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-left">
                  <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" :class="error.direction === 'outgoing' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'">
                    {{ error.direction === 'outgoing' ? $t('We → Grab') : $t('Grab → us') }}
                  </span>
                </TableData>
                <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-left">
                  {{ $t(error.action_name) }}
                </TableData>
                <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-left">
                  <div class="font-mono whitespace-nowrap">{{ error.merchant_id ?? '—' }}</div>
                  <MachineIdLink v-if="error.vend_code" :code="error.vend_code" />
                </TableData>
                <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-left font-mono">
                  {{ error.order_id ?? '—' }}
                </TableData>
                <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-center">
                  <span class="font-semibold" :class="error.status === null || error.status >= 500 ? 'text-red-600' : 'text-amber-700'">
                    {{ error.status ?? $t('No answer') }}
                  </span>
                </TableData>
                <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-left">
                  {{ error.message }}
                </TableData>
              </tr>
              <tr v-if="open[error.id]" class="bg-white">
                <td colspan="7" class="px-3 py-2 border-b border-gray-200">
                  <div class="text-xs text-gray-500 mb-1">{{ error.http_method }} {{ error.path }}</div>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                    <div>
                      <div class="text-xs font-semibold text-gray-700">{{ error.direction === 'outgoing' ? $t('What we sent') : $t('What Grab sent') }}</div>
                      <pre class="text-xs bg-gray-50 border rounded p-2 overflow-x-auto max-h-64">{{ pretty(error.request_json) }}</pre>
                    </div>
                    <div>
                      <div class="text-xs font-semibold text-gray-700">{{ error.direction === 'outgoing' ? $t('What Grab answered') : $t('What we answered') }}</div>
                      <pre class="text-xs bg-gray-50 border rounded p-2 overflow-x-auto max-h-64">{{ pretty(error.response_json) }}</pre>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
            <tr v-if="!rows.length">
              <td colspan="7" class="py-6 text-center text-sm text-gray-500">{{ $t('No Grab errors in this range.') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <Paginator v-if="rows.length" :links="errors.links" :meta="errors.meta" class="mt-3" />
    </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import MachineIdLink from '@/Components/MachineIdLink.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import TableData from '@/Components/TableData.vue';
import TableHead from '@/Components/TableHead.vue';
import { MagnifyingGlassIcon } from '@heroicons/vue/20/solid';
import { Head, router } from '@inertiajs/vue3';
import moment from 'moment';
import { computed, ref } from 'vue';
import { t } from '@/i18n';

const props = defineProps({
  errors: Object,
  filters: Object,
  actionOptions: Array,
  keepDays: Number,
})

const inputClass = 'mt-1 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md'
const rows = computed(() => props.errors.data ?? [])
const directionChips = [
  { key: 'all', name: t('All') },
  { key: 'outgoing', name: t('We → Grab') },
  { key: 'incoming', name: t('Grab → us') },
]

const filters = ref({ ...props.filters })
const open = ref({})

function search() {
  router.get('/grab-errors', filters.value, { preserveState: true, replace: true })
}

function toggle(id) {
  open.value = { ...open.value, [id]: !open.value[id] }
}

function formatDateTime(value) {
  return value ? moment(value).format('DD MMM YY HH:mm:ss') : '—'
}

function pretty(value) {
  return value ? JSON.stringify(value, null, 2) : '—'
}
</script>
