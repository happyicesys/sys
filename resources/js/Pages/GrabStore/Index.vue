<template>
  <Head :title="$t('Grab Stores')" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ $t('Grab Stores') }}
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div v-if="flashSuccess" class="mb-3 rounded-md bg-green-50 border border-green-200 px-3 py-2 text-sm text-green-800">
        {{ flashSuccess }}
      </div>

      <div class="-mx-3 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 py-3">
        <!-- Totals: one click filters the list. -->
        <div class="flex flex-wrap gap-2 mb-4">
          <button
            v-for="chip in statusChips"
            :key="chip.key"
            type="button"
            class="inline-flex items-center gap-1 rounded-full border px-3 py-1 text-sm"
            :class="filters.status === chip.key ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
            @click="setStatus(chip.key)"
          >
            <span>{{ chip.name }}</span>
            <span class="font-semibold">{{ totals[chip.key] ?? 0 }}</span>
          </button>
          <div class="flex-1"></div>
          <Button
            v-if="canUpdate"
            class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-4 py-2 text-sm font-medium text-white hover:bg-green-600"
            @click="openAdd()"
          >
            <PlusIcon class="h-4 w-4" aria-hidden="true" />
            <span>{{ $t('Add store') }}</span>
          </Button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-2">
          <OperatorFilter v-model="filters.operators" />
          <SearchInput :placeholderStr="$t('Grab store or machine ID')" v-model="filters.search" @keyup.enter="search()">
            {{ $t('Search') }}
          </SearchInput>
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
            {{ $t('Showing :from to :to of :total results', { from: stores.meta.from ?? 0, to: stores.meta.to ?? 0, total: stores.meta.total }) }}
          </p>
        </div>
      </div>

      <div class="mt-4 -mx-3 sm:-mx-6 lg:-mx-8 shadow-sm ring-1 ring-black ring-opacity-5 overflow-x-auto bg-white">
        <table class="min-w-full border-separate" style="border-spacing: 0">
          <thead class="bg-gray-100">
            <tr class="divide-x divide-gray-200">
              <TableHead>#</TableHead>
              <TableHead>{{ $t('Grab store') }}</TableHead>
              <TableHead>{{ $t('Machine') }}</TableHead>
              <TableHead>{{ $t('Status') }}</TableHead>
              <TableHead>{{ $t('Grab\'s machine ID') }}</TableHead>
              <TableHead>{{ $t('Orders') }}</TableHead>
              <TableHead></TableHead>
            </tr>
          </thead>
          <tbody class="bg-white">
            <tr v-for="(store, index) in rows" :key="store.id" class="divide-x divide-gray-200 align-top" :class="index % 2 ? 'bg-gray-50' : 'bg-white'">
              <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-center">
                {{ (stores.meta.from ?? 1) + index }}
              </TableData>
              <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-left">
                <div class="font-mono whitespace-nowrap">{{ store.ref_number }}</div>
                <div class="text-xs text-gray-500">{{ store.operator?.code }}</div>
                <div v-if="store.remarks" class="text-xs text-gray-500 whitespace-pre-wrap">{{ store.remarks }}</div>
              </TableData>
              <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-left">
                <template v-if="store.binding">
                  <MachineIdLink :vend="store.binding.vend" :code="store.binding.vend ? null : store.binding.vend_code" />
                  <div class="text-xs text-gray-600">{{ store.binding.site }}</div>
                  <div class="text-xs text-gray-500">{{ $t('Since :time', { time: formatDateTime(store.binding.since) }) }}</div>
                  <div class="text-xs text-gray-400">{{ $t('Menu from :template', { template: store.binding.template }) }}</div>
                </template>
                <span v-else class="text-gray-400">{{ $t('No machine') }}</span>
                <div
                  v-for="other in store.other_open_bindings"
                  :key="other.id"
                  class="mt-1 rounded border border-amber-300 bg-amber-50 px-2 py-1 text-xs text-amber-800"
                >
                  {{ $t('Also still open on machine :code since :since.', { code: other.vend_code, since: formatDateTime(other.since) }) }}
                  <button
                    v-if="canUpdate"
                    type="button"
                    class="ml-1 underline font-medium"
                    @click="closeLeftover(store, other)"
                  >{{ $t('Close that one') }}</button>
                </div>
              </TableData>
              <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-left">
                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" :class="statusClass[store.status]">
                  {{ statusNames[store.status] }}
                </span>
                <div v-if="store.status === 'paused'" class="text-xs text-gray-600 mt-1">
                  <span v-if="store.binding.pause_reason">{{ store.binding.pause_reason }}</span>
                  <span v-if="store.binding.paused_until"> · {{ $t('until :time', { time: formatDateTime(store.binding.paused_until) }) }}</span>
                </div>
                <div v-if="store.status === 'off' && store.binding" class="text-xs text-gray-500 mt-1">
                  {{ $t('Paused on Grab while off') }}
                </div>
              </TableData>
              <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-center">
                <template v-if="store.grab_machine_code">
                  <span :class="store.grab_machine_code_mismatch ? 'text-red-600 font-semibold' : 'text-gray-700'">
                    {{ store.grab_machine_code }}
                  </span>
                  <div v-if="store.grab_machine_code_mismatch" class="text-xs text-red-600">
                    {{ $t('Not this machine: email Grab') }}
                  </div>
                </template>
                <span v-else class="text-gray-400">—</span>
              </TableData>
              <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-center">
                <div>{{ store.orders_30d }} <span class="text-xs text-gray-500">/ 30d</span></div>
                <div class="text-xs text-gray-500">{{ store.last_order_at ? $t('Last :time', { time: formatDateTime(store.last_order_at) }) : $t('No orders') }}</div>
              </TableData>
              <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-left">
                <div class="flex flex-wrap gap-1">
                  <button type="button" :class="actionClass" @click="openHistory(store)">{{ $t('History') }}</button>
                  <template v-if="canUpdate">
                    <button v-if="store.binding" type="button" :class="actionClass" @click="openMove(store)">{{ $t('Move') }}</button>
                    <button v-else type="button" :class="actionClass" @click="openBind(store)">{{ $t('Put on machine') }}</button>
                    <button v-if="store.binding && store.binding.is_active" type="button" :class="actionClass" @click="openPause(store)">{{ $t('Pause') }}</button>
                    <button v-if="store.binding && !store.binding.is_active" type="button" :class="actionClass" @click="resume(store)">{{ $t('Resume') }}</button>
                    <button v-if="store.binding" type="button" :class="actionClass" @click="openUnbind(store)">{{ $t('Remove from machine') }}</button>
                    <button type="button" :class="store.is_off ? actionClass : dangerClass" @click="toggleOff(store)">
                      {{ store.is_off ? $t('Switch on') : $t('Switch off') }}
                    </button>
                  </template>
                </div>
              </TableData>
            </tr>
            <tr v-if="!rows.length">
              <td colspan="7" class="py-6 text-center text-sm text-gray-500">{{ $t('No Grab stores match.') }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      <Paginator v-if="rows.length" :links="stores.links" :meta="stores.meta" class="mt-3" />
    </div>

    <!-- Add store -->
    <Modal :open="modal === 'add'" @modalClose="closeModal">
      <template #header>{{ $t('Add Grab store') }}</template>
      <div class="space-y-3 text-sm">
        <div>
          <label class="block font-medium text-gray-700">{{ $t('Operator') }}</label>
          <select v-model="addForm.operator_id" :class="inputClass">
            <option v-for="option in operatorOptions" :key="option.id" :value="option.id">{{ option.full_name ?? option.name }}</option>
          </select>
        </div>
        <div>
          <label class="block font-medium text-gray-700">{{ $t('Grab merchant ID') }}</label>
          <input v-model="addForm.ref_number" type="text" :class="inputClass" placeholder="4-C7..." />
          <p class="text-xs text-gray-500 mt-1">{{ $t('mark1 asks Grab about it before saving.') }}</p>
          <InputError :message="addForm.errors.ref_number" />
        </div>
        <div>
          <label class="block font-medium text-gray-700">{{ $t('Machine ID (optional)') }}</label>
          <input v-model="addForm.vend_code" type="text" :class="inputClass" />
          <InputError :message="addForm.errors.vend_code" />
        </div>
        <div v-if="addForm.vend_code">
          <label class="block font-medium text-gray-700">{{ $t('Grab template (menu)') }}</label>
          <select v-model="addForm.delivery_product_mapping_id" :class="inputClass">
            <option v-for="template in templatesFor(addForm.operator_id)" :key="template.id" :value="template.id">{{ template.name }}</option>
          </select>
          <InputError :message="addForm.errors.delivery_product_mapping_id" />
        </div>
        <div>
          <label class="block font-medium text-gray-700">{{ $t('Remarks') }}</label>
          <textarea v-model="addForm.remarks" rows="2" :class="inputClass"></textarea>
        </div>
        <label class="flex items-center gap-2 text-gray-700">
          <input v-model="addForm.skip_grab_check" type="checkbox" class="rounded border-gray-300" />
          {{ $t('Save without Grab check') }}
          <span class="text-xs text-gray-500">({{ $t('a store Grab has not integrated yet') }})</span>
        </label>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" :class="actionClass" @click="closeModal">{{ $t('Cancel') }}</button>
          <button type="button" :class="primaryClass" :disabled="addForm.processing" @click="submitAdd">{{ $t('Save') }}</button>
        </div>
      </div>
    </Modal>

    <!-- Put on machine / Move -->
    <Modal :open="modal === 'move' || modal === 'bind'" @modalClose="closeModal">
      <template #header>
        {{ modal === 'move' ? $t('Move :ref to another machine', { ref: current?.ref_number }) : $t('Put :ref on a machine', { ref: current?.ref_number }) }}
      </template>
      <div class="space-y-3 text-sm">
        <p v-if="modal === 'move'" class="text-gray-600">
          {{ $t('Machine :code stops selling this store and the new machine starts at the same moment. The store stays open on Grab.', { code: current?.binding?.vend_code }) }}
        </p>
        <div>
          <label class="block font-medium text-gray-700">{{ $t('Machine ID') }}</label>
          <input v-model="moveForm.vend_code" type="text" :class="inputClass" />
          <InputError :message="moveForm.errors.vend_code" />
        </div>
        <div>
          <label class="block font-medium text-gray-700">{{ $t('Grab template (menu)') }}</label>
          <select v-model="moveForm.delivery_product_mapping_id" :class="inputClass">
            <option v-for="template in templatesFor(current?.operator_id, current?.binding?.template_id)" :key="template.id" :value="template.id">{{ template.name }}</option>
          </select>
          <InputError :message="moveForm.errors.delivery_product_mapping_id" />
        </div>
        <div v-if="modal === 'move'">
          <label class="block font-medium text-gray-700">{{ $t('Why') }}</label>
          <select v-model="moveForm.reason" :class="inputClass">
            <option v-for="key in moveReasons" :key="key" :value="key">{{ $t(unbindReasons[key]) }}</option>
          </select>
        </div>
        <div>
          <label class="block font-medium text-gray-700">{{ $t('Remarks') }}</label>
          <input v-model="moveForm.remarks" type="text" :class="inputClass" />
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" :class="actionClass" @click="closeModal">{{ $t('Cancel') }}</button>
          <button type="button" :class="primaryClass" :disabled="moveForm.processing" @click="submitMove">{{ modal === 'move' ? $t('Move') : $t('Save') }}</button>
        </div>
      </div>
    </Modal>

    <!-- Pause -->
    <Modal :open="modal === 'pause'" @modalClose="closeModal">
      <template #header>{{ $t('Pause :ref', { ref: current?.ref_number }) }}</template>
      <div class="space-y-3 text-sm">
        <p class="text-gray-600">{{ $t('The store is paused on Grab: customers cannot order from it.') }}</p>
        <div>
          <label class="block font-medium text-gray-700">{{ $t('Reason') }}</label>
          <input v-model="pauseForm.reason" type="text" :class="inputClass" :placeholder="$t('e.g. machine under repair')" />
          <InputError :message="pauseForm.errors.reason" />
        </div>
        <div>
          <label class="block font-medium text-gray-700">{{ $t('Until (optional)') }}</label>
          <div class="flex flex-wrap gap-1 mt-1">
            <button v-for="preset in pausePresets" :key="preset.hours" type="button" :class="actionClass" @click="setPauseFor(preset.hours)">{{ preset.name }}</button>
          </div>
          <input v-model="pauseForm.until" type="datetime-local" :class="inputClass" />
          <p class="text-xs text-gray-500 mt-1">{{ $t('Empty = paused until someone presses Resume.') }}</p>
          <InputError :message="pauseForm.errors.until" />
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" :class="actionClass" @click="closeModal">{{ $t('Cancel') }}</button>
          <button type="button" :class="primaryClass" :disabled="pauseForm.processing" @click="submitPause">{{ $t('Pause') }}</button>
        </div>
      </div>
    </Modal>

    <!-- Remove from machine -->
    <Modal :open="modal === 'unbind'" @modalClose="closeModal">
      <template #header>{{ $t('Remove :ref from machine :code', { ref: current?.ref_number, code: current?.binding?.vend_code }) }}</template>
      <div class="space-y-3 text-sm">
        <p class="text-gray-600">{{ $t('No machine will sell this store, so it is paused on Grab. Its history is kept.') }}</p>
        <div>
          <label class="block font-medium text-gray-700">{{ $t('Why') }}</label>
          <select v-model="unbindForm.reason" :class="inputClass">
            <option v-for="(name, key) in unbindReasons" :key="key" :value="key">{{ $t(name) }}</option>
          </select>
        </div>
        <div>
          <label class="block font-medium text-gray-700">{{ $t('Remarks') }}</label>
          <input v-model="unbindForm.remarks" type="text" :class="inputClass" />
        </div>
        <div class="flex justify-end gap-2 pt-2">
          <button type="button" :class="actionClass" @click="closeModal">{{ $t('Cancel') }}</button>
          <button type="button" :class="dangerClass" :disabled="unbindForm.processing" @click="submitUnbind">{{ $t('Remove from machine') }}</button>
        </div>
      </div>
    </Modal>

    <!-- History -->
    <Modal :open="modal === 'history'" @modalClose="closeModal">
      <template #header>{{ $t('Grab store :ref', { ref: current?.ref_number }) }}</template>
      <div class="space-y-4 text-sm">
        <div class="flex items-center gap-2">
          <button type="button" :class="actionClass" :disabled="grabCheck.loading" @click="askGrab">{{ $t('Ask Grab now') }}</button>
          <span v-if="grabCheck.loading" class="text-gray-500">{{ $t('Asking Grab…') }}</span>
          <span v-else-if="grabCheck.result" :class="grabCheck.result.ok ? 'text-green-700' : 'text-red-600'">{{ grabCheck.result.message }}</span>
        </div>

        <div v-if="history.loading" class="text-gray-500">{{ $t('Loading…') }}</div>
        <template v-else-if="history.data">
          <div>
            <h4 class="font-semibold text-gray-800 mb-1">{{ $t('Machines over time') }}</h4>
            <div class="overflow-x-auto">
              <table class="min-w-full text-xs border">
                <thead class="bg-gray-100">
                  <tr>
                    <th class="px-2 py-1 text-left">{{ $t('Machine') }}</th>
                    <th class="px-2 py-1 text-left">{{ $t('Site') }}</th>
                    <th class="px-2 py-1 text-left">{{ $t('From') }}</th>
                    <th class="px-2 py-1 text-left">{{ $t('To') }}</th>
                    <th class="px-2 py-1 text-left">{{ $t('By') }}</th>
                    <th class="px-2 py-1 text-right">{{ $t('Orders') }}</th>
                    <th class="px-2 py-1 text-right">{{ $t('Sales') }}</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="binding in history.data.bindings" :key="binding.id" class="border-t align-top">
                    <td class="px-2 py-1">
                      <MachineIdLink :vend="binding.vend" :code="binding.vend ? null : binding.vend_code" />
                      <div class="text-gray-400">{{ binding.template }}</div>
                    </td>
                    <td class="px-2 py-1">{{ binding.site }}</td>
                    <td class="px-2 py-1 whitespace-nowrap">{{ formatDateTime(binding.from) }}</td>
                    <td class="px-2 py-1 whitespace-nowrap">
                      <span v-if="binding.to">{{ formatDateTime(binding.to) }}</span>
                      <span v-else class="font-semibold text-green-700">{{ $t('now') }}</span>
                      <div v-if="binding.unbind_reason_label" class="text-gray-500">{{ $t(binding.unbind_reason_label) }}</div>
                      <div v-if="binding.unbind_remarks" class="text-gray-500">{{ binding.unbind_remarks }}</div>
                    </td>
                    <td class="px-2 py-1">
                      <div>{{ binding.bound_by ?? $t('not recorded') }}</div>
                      <div v-if="binding.to" class="text-gray-500">{{ $t('Taken off by :name', { name: binding.unbound_by ?? $t('not recorded') }) }}</div>
                    </td>
                    <td class="px-2 py-1 text-right">{{ binding.orders_count }}</td>
                    <td class="px-2 py-1 text-right">{{ money(binding.orders_subtotal) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <div>
            <h4 class="font-semibold text-gray-800 mb-1">{{ $t('What happened') }}</h4>
            <ul v-if="history.data.events.length" class="divide-y border rounded">
              <li v-for="event in history.data.events" :key="event.id" class="px-2 py-1 flex gap-2">
                <span class="whitespace-nowrap text-gray-500">{{ formatDateTime(event.at) }}</span>
                <span class="flex-1">
                  <span class="font-medium">{{ eventText(event) }}</span>
                  <span v-if="event.reason" class="text-gray-600"> — {{ event.reason }}</span>
                  <span v-if="grabNote(event)" class="text-gray-500"> ({{ grabNote(event) }})</span>
                </span>
                <span class="whitespace-nowrap text-gray-500">{{ event.is_system ? $t('system') : event.user }}</span>
              </li>
            </ul>
            <p v-else class="text-gray-500">{{ $t('Nothing recorded yet. Changes are recorded from now on.') }}</p>
          </div>
        </template>
      </div>
    </Modal>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import InputError from '@/Components/InputError.vue';
import MachineIdLink from '@/Components/MachineIdLink.vue';
import Modal from '@/Components/Modal.vue';
import OperatorFilter from '@/Components/OperatorFilter.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import TableData from '@/Components/TableData.vue';
import TableHead from '@/Components/TableHead.vue';
import { MagnifyingGlassIcon, PlusIcon } from '@heroicons/vue/20/solid';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import moment from 'moment';
import { computed, ref } from 'vue';
import { t } from '@/i18n';

const props = defineProps({
  stores: Object,
  totals: Object,
  filters: Object,
  templateOptions: Array,
  unbindReasons: Object,
  canUpdate: Boolean,
})

const page = usePage()
const flashSuccess = computed(() => page.props.flash?.success)
const rows = computed(() => props.stores.data ?? [])

const inputClass = 'mt-1 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md'
const actionClass = 'rounded border border-gray-300 bg-white px-2 py-1 text-xs text-gray-700 hover:bg-gray-50'
const dangerClass = 'rounded border border-red-300 bg-white px-2 py-1 text-xs text-red-700 hover:bg-red-50'
const primaryClass = 'rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50'

const statusNames = {
  selling: t('Selling'),
  paused: t('Paused'),
  off: t('Off (Inactive)'),
  no_machine: t('No machine'),
}
const statusClass = {
  selling: 'bg-green-100 text-green-800',
  paused: 'bg-amber-100 text-amber-800',
  off: 'bg-gray-200 text-gray-700',
  no_machine: 'bg-gray-100 text-gray-500',
}
const statusChips = [
  { key: 'all', name: t('All') },
  { key: 'selling', name: t('Selling') },
  { key: 'paused', name: t('Paused') },
  { key: 'off', name: t('Off (Inactive)') },
  { key: 'no_machine', name: t('No machine') },
]
const moveReasons = ['moved', 'replaced', 'template', 'correction']
const pausePresets = [
  { name: t('1 hour'), hours: 1 },
  { name: t('4 hours'), hours: 4 },
  { name: t('24 hours'), hours: 24 },
]
const eventNames = {
  created: t('Store added'),
  bound: t('Put on machine :code'),
  unbound: t('Taken off machine :code'),
  paused: t('Paused'),
  resumed: t('Resumed'),
  switched_off: t('Switched off'),
  switched_on: t('Switched on'),
  renamed: t('Merchant ID changed'),
}

const filters = ref({
  operators: props.filters.operators ?? [],
  search: props.filters.search ?? '',
  status: props.filters.status ?? 'all',
  numberPerPage: props.filters.numberPerPage ?? 100,
})

function search() {
  router.get('/grab-stores', {
    operators: filters.value.operators,
    search: filters.value.search,
    status: filters.value.status,
    numberPerPage: filters.value.numberPerPage,
  }, { preserveState: true, replace: true })
}

function setStatus(key) {
  filters.value.status = key
  search()
}

const operatorOptions = computed(() => page.props.operatorFilterOptions ?? [])

function templatesFor(operatorId, alsoId = null) {
  return (props.templateOptions ?? []).filter(template => template.operator_id === operatorId || template.id === alsoId)
}

function formatDateTime(value) {
  return value ? moment(value).format('DD MMM YY HH:mm') : '—'
}

function money(cents) {
  return '$' + ((cents ?? 0) / 100).toFixed(2)
}

// Modals: one at a time, on the row they were opened from.
const modal = ref(null)
const current = ref(null)

function closeModal() {
  modal.value = null
}

const addForm = useForm({
  operator_id: null, ref_number: '', remarks: '', vend_code: '', delivery_product_mapping_id: null, skip_grab_check: false,
})
const moveForm = useForm({ vend_code: '', delivery_product_mapping_id: null, reason: 'moved', remarks: '' })
const pauseForm = useForm({ reason: '', until: '' })
const unbindForm = useForm({ reason: 'removed', remarks: '' })

const ok = { preserveScroll: true, onSuccess: () => closeModal() }

function openAdd() {
  addForm.reset()
  addForm.clearErrors()
  addForm.operator_id = page.props.auth?.user?.operator_id ?? operatorOptions.value[0]?.id ?? null
  addForm.delivery_product_mapping_id = templatesFor(addForm.operator_id)[0]?.id ?? null
  modal.value = 'add'
}

function submitAdd() {
  addForm.post('/grab-stores', ok)
}

function openBind(store) {
  current.value = store
  moveForm.reset()
  moveForm.clearErrors()
  moveForm.delivery_product_mapping_id = templatesFor(store.operator_id)[0]?.id ?? null
  modal.value = 'bind'
}

function openMove(store) {
  current.value = store
  moveForm.reset()
  moveForm.clearErrors()
  moveForm.delivery_product_mapping_id = store.binding.template_id
  modal.value = 'move'
}

function submitMove() {
  if (modal.value === 'move') {
    moveForm.post(`/grab-stores/bindings/${current.value.binding.id}/move`, ok)
  } else {
    moveForm.post(`/grab-stores/${current.value.id}/bind`, ok)
  }
}

function openPause(store) {
  current.value = store
  pauseForm.reset()
  pauseForm.clearErrors()
  modal.value = 'pause'
}

function setPauseFor(hours) {
  pauseForm.until = moment().add(hours, 'hours').format('YYYY-MM-DDTHH:mm')
}

function submitPause() {
  pauseForm.post(`/grab-stores/bindings/${current.value.binding.id}/pause`, ok)
}

function resume(store) {
  router.post(`/grab-stores/bindings/${store.binding.id}/resume`, {}, { preserveScroll: true })
}

function openUnbind(store) {
  current.value = store
  unbindForm.reset()
  unbindForm.clearErrors()
  modal.value = 'unbind'
}

function submitUnbind() {
  unbindForm.post(`/grab-stores/bindings/${current.value.binding.id}/unbind`, ok)
}

function closeLeftover(store, other) {
  if (!window.confirm(t('Close the leftover binding on machine :code? The store keeps selling from :live.', { code: other.vend_code, live: store.binding?.vend_code }))) {
    return
  }
  router.post(`/grab-stores/bindings/${other.id}/unbind`, { reason: 'correction', remarks: t('Leftover open binding closed') }, { preserveScroll: true })
}

function toggleOff(store) {
  const question = store.is_off
    ? t('Switch :ref on? It reopens on Grab if it is on a machine.', { ref: store.ref_number })
    : t('Switch :ref off? It is paused on Grab at once and stops selling.', { ref: store.ref_number })
  if (!window.confirm(question)) {
    return
  }
  router.post(`/grab-stores/${store.id}/status`, { active: store.is_off }, { preserveScroll: true })
}

// History
const history = ref({ loading: false, data: null })
const grabCheck = ref({ loading: false, result: null })

async function openHistory(store) {
  current.value = store
  grabCheck.value = { loading: false, result: null }
  history.value = { loading: true, data: null }
  modal.value = 'history'
  try {
    const { data } = await axios.get(`/grab-stores/${store.id}/history`)
    history.value = { loading: false, data }
  } catch (e) {
    history.value = { loading: false, data: null }
  }
}

async function askGrab() {
  grabCheck.value = { loading: true, result: null }
  try {
    const { data } = await axios.post(`/grab-stores/${current.value.id}/check-grab`)
    grabCheck.value = { loading: false, result: data }
  } catch (e) {
    grabCheck.value = { loading: false, result: { ok: false, message: t('Could not ask Grab.') } }
  }
}

function eventText(event) {
  const name = eventNames[event.event] ?? event.event
  const code = event.vend_code ?? event.data?.vend_code ?? ''
  if (event.event === 'renamed') {
    return `${name}: ${event.data?.from ?? ''} → ${event.data?.to ?? ''}`
  }
  if (event.event === 'paused' && event.data?.until) {
    return `${name} ${t('until :time', { time: formatDateTime(event.data.until) })}`
  }
  return name.replace(':code', code)
}

function grabNote(event) {
  const grab = event.data?.grab
  if (!grab) {
    return null
  }
  if (typeof grab === 'string') {
    return t('Grab: :result', { result: grab })
  }
  return Object.entries(grab).map(([code, result]) => `${code}: ${result}`).join(', ')
}
</script>
