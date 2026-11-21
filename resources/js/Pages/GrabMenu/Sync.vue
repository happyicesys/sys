<template>
  <Head :title="$t('Grab Logs')" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ $t('Grab Logs') }}
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3 space-y-3">
      <GrabLogsTabs current="menu-sync" />
      <div v-if="message" class="rounded-md border px-3 py-2 text-sm" :class="messageError ? 'border-red-200 bg-red-50 text-red-800' : 'border-green-200 bg-green-50 text-green-800'">{{ message }}</div>

      <section class="-mx-3 sm:-mx-6 lg:-mx-8 bg-white rounded-md border px-3 py-3 text-sm">
        <p class="text-gray-600">
          {{ $t('Every change sent to Grab, and whether each store has it. A store is Synced only when Grab confirms it took the new menu. Grab sometimes turns a request away (too many at once); ConnectVend asks again by itself, and every 10 minutes it checks each store\'s prices on Grab against its price rule and sends again where they differ.') }}
        </p>
        <div class="mt-2 flex flex-wrap items-end gap-2">
          <div v-if="operators.length > 1">
            <label class="block text-xs font-medium text-gray-700">{{ $t('Operator') }}</label>
            <select :value="filters.operator_id ?? ''" class="mt-1 rounded-md border-gray-300 text-sm" @change="go({ operator_id: $event.target.value || null })">
              <option value="">{{ $t('All') }}</option>
              <option v-for="operator in operators" :key="operator.id" :value="operator.id">{{ operator.code }}</option>
            </select>
          </div>
          <div class="flex flex-wrap gap-1">
            <button
              v-for="chip in stateChips"
              :key="chip.key"
              type="button"
              class="rounded-full border px-3 py-1"
              :class="filters.state === chip.key ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
              @click="go({ state: chip.key })"
            >{{ chip.name }}</button>
          </div>
          <div class="ml-auto flex items-center gap-2">
            <span v-if="lastCheckAt" class="text-xs text-gray-500">{{ $t('Last check :at', { at: when(lastCheckAt) }) }}</span>
            <button v-if="can.update" type="button" :class="secondaryClass" :disabled="busy" @click="checkNow">{{ $t('Check all stores now') }}</button>
          </div>
        </div>
      </section>

      <div class="-mx-3 sm:-mx-6 lg:-mx-8 overflow-x-auto bg-white rounded-md border">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50">
            <tr class="text-left text-gray-700">
              <th class="px-2 py-2 w-8"></th>
              <th class="px-2 py-2 whitespace-nowrap">{{ $t('When') }}</th>
              <th class="px-2 py-2">{{ $t('Change') }}</th>
              <th class="px-2 py-2 whitespace-nowrap">{{ $t('By') }}</th>
              <th class="px-2 py-2">{{ $t('On Grab') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <template v-for="sync in rows" :key="sync.id">
              <tr class="align-top cursor-pointer hover:bg-gray-50" :class="open[sync.id] ? 'bg-gray-50' : ''" @click="toggle(sync)">
                <td class="px-2 py-2">
                  <ChevronRightIcon class="h-4 w-4 text-gray-500 transition-transform" :class="open[sync.id] ? 'rotate-90' : ''" aria-hidden="true" />
                </td>
                <td class="px-2 py-2 whitespace-nowrap">{{ when(sync.created_at) }}</td>
                <td class="px-2 py-2">
                  <div class="text-gray-900">{{ sync.summary }}</div>
                  <div class="text-xs text-gray-500">{{ kindName(sync.kind) }}<template v-if="sync.operator"> · {{ sync.operator }}</template></div>
                </td>
                <td class="px-2 py-2 whitespace-nowrap">{{ sync.by ?? $t('System') }}</td>
                <td class="px-2 py-2">
                  <SyncProgress :sync="sync" />
                </td>
              </tr>
              <tr v-if="open[sync.id]">
                <td></td>
                <td colspan="4" class="px-2 pb-3">
                  <div v-if="details[sync.id]?.loading && !details[sync.id]?.stores" class="py-2 text-gray-500">{{ $t('Loading…') }}</div>
                  <template v-else-if="details[sync.id]?.stores">
                    <div class="mb-2 flex flex-wrap items-center gap-2">
                      <span v-if="anyMoving(details[sync.id].stores)" class="text-xs text-gray-500">{{ $t('Updates by itself every 15 seconds while stores are still moving.') }}</span>
                      <button
                        v-if="can.update && details[sync.id].stores.some(row => ['failed', 'turned_away'].includes(row.status))"
                        type="button"
                        :class="secondaryClass"
                        :disabled="busy"
                        @click.stop="resendAll(sync)"
                      >{{ $t('Ask Grab again for every store not synced') }}</button>
                    </div>
                    <div class="overflow-x-auto rounded border">
                      <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50 text-left text-gray-700">
                          <tr>
                            <th class="px-2 py-1">{{ $t('Machine') }}</th>
                            <th class="px-2 py-1">{{ $t('Site') }}</th>
                            <th class="px-2 py-1">{{ $t('On Grab') }}</th>
                            <th class="px-2 py-1"></th>
                          </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                          <tr v-for="row in details[sync.id].stores" :key="row.id" class="align-top">
                            <td class="px-2 py-1"><MachineIdLink v-if="row.vend_code" :code="row.vend_code" /></td>
                            <td class="px-2 py-1">
                              <div>{{ row.site ?? '—' }}</div>
                              <div class="font-mono text-xs text-gray-500">{{ row.merchant_id }}</div>
                            </td>
                            <td class="px-2 py-1"><SyncStatus :row="row" /></td>
                            <td class="px-2 py-1 text-right">
                              <button
                                v-if="can.update && ['failed', 'turned_away'].includes(row.status)"
                                type="button"
                                class="text-indigo-700 underline whitespace-nowrap"
                                :disabled="busy"
                                @click.stop="resendOne(sync, row)"
                              >{{ $t('Ask again') }}</button>
                            </td>
                          </tr>
                          <tr v-if="!details[sync.id].stores.length">
                            <td colspan="4" class="px-2 py-2 text-gray-500">{{ $t('No store was asked: none of the stores this change prices is on the machine menu and selling on Grab now.') }}</td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </template>
                </td>
              </tr>
            </template>
            <tr v-if="!rows.length">
              <td colspan="5" class="px-2 py-4 text-center text-gray-500">{{ $t('Nothing here yet.') }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <Paginator v-if="rows.length" :links="syncs.links" :meta="syncs.meta" />
    </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import GrabLogsTabs from '@/Components/Grab/GrabLogsTabs.vue'
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import MachineIdLink from '@/Components/MachineIdLink.vue'
import Paginator from '@/Components/Paginator.vue'
import SyncProgress from '@/Components/GrabMenu/SyncProgress.vue'
import SyncStatus from '@/Components/GrabMenu/SyncStatus.vue'
import { MOVING, when } from '@/Components/GrabMenu/syncStatus'
import { secondaryClass } from '@/Components/GrabMenu/priceRule'
import { ChevronRightIcon } from '@heroicons/vue/20/solid'
import { Head, router } from '@inertiajs/vue3'
import axios from 'axios'
import { computed, onBeforeUnmount, ref } from 'vue'
import { t } from '@/i18n'

const props = defineProps({
  syncs: { type: Object, required: true },
  operators: { type: Array, required: true },
  filters: { type: Object, required: true },
  can: { type: Object, required: true },
  lastCheckAt: { type: String, default: null },
})

const rows = computed(() => props.syncs.data ?? [])
const open = ref({})
const details = ref({})
const busy = ref(false)
const message = ref('')
const messageError = ref(false)

const stateChips = [
  { key: 'all', name: t('All') },
  { key: 'attention', name: t('Needs attention') },
  { key: 'in_progress', name: t('In progress') },
  { key: 'synced', name: t('Fully synced') },
]

const KINDS = {
  company_rule: () => t('Company price rule'),
  store_rule: () => t('Store price rule'),
  custom_price: () => t('Typed price'),
  sub_category: () => t('Sub-category'),
  menu_source: () => t('Menu switched'),
  store_setup: () => t('Store on a machine'),
  product: () => t('Product changed'),
  nightly: () => t('Nightly sync'),
  self_check: () => t('System check'),
  resend: () => t('Sent again'),
}
function kindName(kind) {
  return KINDS[kind]?.() ?? kind
}

function go(change) {
  router.get('/grab-logs/menu-sync', { ...props.filters, ...change, page: undefined }, { preserveState: false, replace: true })
}

function anyMoving(stores) {
  return stores.some(row => MOVING.includes(row.status))
}

async function load(sync) {
  details.value = { ...details.value, [sync.id]: { ...(details.value[sync.id] ?? {}), loading: true } }
  try {
    const { data } = await axios.get(`/grab-menu-sync/${sync.id}/stores`)
    details.value = { ...details.value, [sync.id]: { loading: false, stores: data.stores } }
    // The row's counts follow what was just read.
    const index = rows.value.findIndex(row => row.id === sync.id)
    if (index >= 0) Object.assign(rows.value[index], { counts: data.sync.counts, state: data.sync.state })
  } catch (error) {
    details.value = { ...details.value, [sync.id]: { loading: false, stores: details.value[sync.id]?.stores ?? [] } }
    flash(error.response?.data?.message ?? t('Could not load the stores.'), true)
  }
}

function toggle(sync) {
  open.value = { ...open.value, [sync.id]: !open.value[sync.id] }
  if (open.value[sync.id]) load(sync)
}

// While an opened change still has stores moving, read it again every 15 s.
const timer = setInterval(() => {
  rows.value.filter(sync => open.value[sync.id] && anyMoving(details.value[sync.id]?.stores ?? [])).forEach(load)
}, 15000)
onBeforeUnmount(() => clearInterval(timer))

function flash(text, isError = false) {
  message.value = text
  messageError.value = isError
}

async function post(url, sync) {
  busy.value = true
  try {
    const { data } = await axios.post(url)
    flash(data.message)
    if (sync) await load(sync)
  } catch (error) {
    flash(error.response?.data?.message ?? t('That did not work. Try again.'), true)
  } finally {
    busy.value = false
  }
}

const resendAll = sync => post(`/grab-menu-sync/${sync.id}/resend`, sync)
const resendOne = (sync, row) => post(`/grab-menu-sync/stores/${row.id}/resend`, sync)
const checkNow = () => post('/grab-menu-sync/check')
</script>
