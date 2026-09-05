<template>

  <Head title="Simcard" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Simcard
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-3 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
        <div class="flex justify-end">
          <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
          @click="onCreateClicked()"
          >
            <PlusIcon class="h-4 w-4" aria-hidden="true"/>
            <span>
              Create
            </span>
          </Button>
        </div>
          <!-- <div class="flex flex-col md:flex-row md:space-x-3 space-y-1 md:space-y-0"> -->
        <div class="grid grid-cols-1 md:grid-cols-6 gap-2">
          <SearchInput placeholderStr="Simcard Number" v-model="filters.code">
            Simcard Number
          </SearchInput>
          <SearchInput placeholderStr="Machine ID" v-model="filters.vend_code">
            Machine ID
          </SearchInput>
          <SearchInput placeholderStr="Site name / ID" v-model="filters.customer" @keyup.enter="onSearchFilterUpdated()">
            Site
          </SearchInput>
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
              SimCard Package
            </label>
            <MultiSelect
              v-model="filters.telco_id"
              :options="telcoOptions"
              trackBy="id"
              valueProp="id"
              label="name"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
            >
            </MultiSelect>
          </div>
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
              Status
            </label>
            <MultiSelect
              v-model="filters.usage_status"
              :options="usageStatusOptionList"
              trackBy="id"
              valueProp="id"
              label="name"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
            >
            </MultiSelect>
          </div>
        </div>


        <div class="flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5">
          <div class="mt-3">
            <div class="flex flex-col space-y-1 md:flex-row md:space-y-0 md:space-x-1">
              <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
              @click="onSearchFilterUpdated()"
              >
                <MagnifyingGlassIcon class="h-4 w-4" aria-hidden="true"/>
                <span>
                  Search
                </span>
              </Button>
              <Button type="button" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-400 hover:bg-gray-100"
                  @click.prevent="onExportExcelClicked()">
                  <div class="flex space-x-1">
                      <div>
                          <ArrowDownTrayIcon v-if="!loading" class="h-4 w-4" aria-hidden="true"/>
                          <svg v-if="loading" aria-hidden="true" class="mr-2 w-4 h-4 text-gray-200 animate-spin fill-red-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                              <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                              <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                          </svg>
                      </div>
                      <span>
                          Excel
                      </span>
                  </div>
              </Button>
            </div>
          </div>
          <div class="flex flex-col space-y-2">
              <p class="text-sm text-gray-700 leading-5 flex space-x-1">
                  <span>Showing</span>
                  <span class="font-medium">{{ simcards.meta.from ?? 0 }}</span>
                  <span>to</span>
                  <span class="font-medium">{{ simcards.meta.to ?? 0 }}</span>
                  <span>of</span>
                  <span class="font-medium">{{ simcards.meta.total }}</span>
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
                  class="mt-1"
                  @selected="onSearchFilterUpdated"
              >
              </MultiSelect>
          </div>
        </div>
      </div>

      <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-3 sm:-mx-6 lg:-mx-8">
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll">
            <table class="min-w-full border-separate" style="border-spacing: 0">
                <thead class="bg-gray-100">
                  <tr class="divide-x divide-gray-200">
                    <TableHead>
                      #
                    </TableHead>
                    <TableHeadSort modelName="code" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('code')">
                      Simcard Number
                    </TableHeadSort>
                    <TableHeadSort modelName="vend_code" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_code')">
                      Machine ID
                    </TableHeadSort>
                    <TableHeadSort modelName="site" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('site')">
                      Site
                    </TableHeadSort>
                    <TableHeadSort modelName="apk_version" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('apk_version')">
                      Machine APK
                    </TableHeadSort>
                    <TableHeadSort modelName="telco_id" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('telco_id')">
                      SimCard Package
                    </TableHeadSort>
                    <TableHeadSort modelName="signal" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('signal')">
                      Signal Strength
                    </TableHeadSort>
                    <TableHeadSort modelName="updated_at" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('updated_at')">
                      Updated By
                    </TableHeadSort>
                    <TableHeadSort modelName="usage_status" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('usage_status')">
                      Status
                    </TableHeadSort>
                    <TableHead>
                    </TableHead>
                  </tr>
                </thead>
                  <tbody class="bg-white">
                    <tr v-for="(simcard, telcoIndex) in simcards.data" :key="simcard.id" class="divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100">
                      <TableData :currentIndex="telcoIndex" :totalLength="simcards.length" inputClass="text-center">
                        {{ simcards.meta.from + telcoIndex }}
                      </TableData>
                      <TableData :currentIndex="telcoIndex" :totalLength="simcards.length" inputClass="text-left">
                        {{ simcard.code }}
                      </TableData>
                      <!-- Machine ID — one link per bound machine, in the same
                           order as the Site / Machine APK columns so the three
                           stacks line up. Opens the Operation Dashboard filtered
                           to that machine (codes= + autoload=true, the same link
                           Machine Health uses), in a new tab so the Simcard list
                           and its filters survive the click. -->
                      <TableData :currentIndex="telcoIndex" :totalLength="simcards.length" inputClass="text-center">
                        <div v-if="machineCodes(simcard).length" class="flex flex-col items-center space-y-1">
                          <a
                            v-for="machine in machineCodes(simcard)"
                            :key="machine.id"
                            class="text-blue-700 hover:underline"
                            target="_blank"
                            :href="'/vends/customers?codes=' + machine.code + '&autoload=true'"
                          >
                            {{ machine.code }}
                          </a>
                        </div>
                        <span v-else class="text-gray-400">—</span>
                      </TableData>
                      <!-- The Site each bound machine sits at — displayed Site ID
                           (customers.id + 20000) over the site name, one block per
                           bound machine, in the same order as the Machine ID column.
                           Unbound simcards, and machines sitting at no Site, show '—'. -->
                      <TableData :currentIndex="telcoIndex" :totalLength="simcards.length" inputClass="text-left">
                        <div v-if="simcard.sites && simcard.sites.length" class="flex flex-col space-y-1">
                          <template v-for="site in simcard.sites" :key="site.vend_id">
                            <a
                              v-if="site.id"
                              class="text-blue-700 hover:underline"
                              target="_blank"
                              :href="'/customers/' + site.id + '/edit'"
                            >
                              {{ site.ref_id }}<br>
                              {{ site.name }}
                            </a>
                            <span v-else class="text-gray-400">—</span>
                          </template>
                        </div>
                        <span v-else class="text-gray-400">—</span>
                      </TableData>
                      <!-- The APK versionCode the bound machine last reported, over
                           either channel (OTA check-in or the PWRON frame) — the JS
                           twin of Vend::reportedApkVersion(). One line per bound
                           machine, in the same order as the Machine ID column. -->
                      <TableData :currentIndex="telcoIndex" :totalLength="simcards.length" inputClass="text-center">
                        <div v-if="apkVersions(simcard).length" class="flex flex-col items-center space-y-1">
                          <span v-for="apk in apkVersions(simcard)" :key="apk.id">{{ apk.version }}</span>
                        </div>
                        <span v-else class="text-gray-400">—</span>
                      </TableData>
                      <TableData :currentIndex="telcoIndex" :totalLength="simcards.length" inputClass="text-center">
                        {{ simcard.telco.name }}
                      </TableData>
                      <!-- What the bound machine actually reports (vends.internet_*,
                           same data as the Machine List's SIM Card block): the live
                           carrier + signal pill (1-2 red, 3 yellow, 4-5 green).
                           Nothing shows when no bound machine has reported a link. -->
                      <TableData :currentIndex="telcoIndex" :totalLength="simcards.length" inputClass="text-center">
                        <div v-if="onlineStates(simcard).length || reportedLink(simcard)" class="flex flex-col items-center space-y-1">
                          <span
                            v-for="state in onlineStates(simcard)"
                            :key="state.id"
                            class="text-xs font-bold"
                            :class="state.class"
                          >
                            {{ state.label }}
                          </span>
                          <template v-if="reportedLink(simcard)">
                            <span class="text-xs font-bold text-gray-700">{{ internetLinkTitle(reportedLink(simcard)) }}</span>
                            <span
                              v-if="signalBars(reportedLink(simcard)) || reportedLink(simcard).internet_source === 'none'"
                              class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-bold border"
                              :class="signalBadgeClass(reportedLink(simcard))"
                            >
                              {{ signalBars(reportedLink(simcard)) || 'No Link' }}
                            </span>
                          </template>
                        </div>
                        <span v-else class="text-gray-400">—</span>
                      </TableData>
                      <!-- Who last edited this simcard + when (same two-line pattern as
                           OpsJob Index's Created By). '—' = never edited since the column
                           was added (updated_by is only stamped on Edit saves). -->
                      <TableData :currentIndex="telcoIndex" :totalLength="simcards.length" inputClass="text-center">
                        <div v-if="simcard.updatedBy && simcard.updatedBy.name" class="flex flex-col space-y-1">
                          <span>{{ simcard.updatedBy.name }}</span>
                          <span class="text-xs text-gray-500">{{ simcard.updated_at }}</span>
                        </div>
                        <span v-else class="text-gray-400">—</span>
                      </TableData>
                      <!-- Live telco-API snapshot (simcards:sync-usage, every 10 min):
                           the current package's status / active / expire / used data,
                           the same four values the telco's own portal shows. '—' =
                           telco has no usage API mapped, or nothing synced yet. -->
                      <TableData :currentIndex="telcoIndex" :totalLength="simcards.length" inputClass="text-center">
                        <div v-if="simcard.usage_status" class="flex flex-col items-center space-y-1">
                          <span
                            class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-bold border"
                            :class="usageStatusBadgeClass(simcard)"
                          >
                            {{ simcard.usage_status }}
                          </span>
                          <span
                            v-if="simcard.usage_active_at"
                            class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-bold border bg-gray-100 text-gray-700 border-gray-300"
                          >
                            Act {{ simcard.usage_active_at }}
                          </span>
                          <span
                            v-if="simcard.usage_expire_at"
                            class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-bold border"
                            :class="usageExpireBadgeClass(simcard)"
                          >
                            Exp {{ simcard.usage_expire_at }}
                          </span>
                          <span
                            v-if="typeof simcard.usage_used_mb === 'number'"
                            class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-bold border bg-blue-100 text-blue-800 border-blue-300"
                          >
                            {{ simcard.usage_used_mb.toFixed(2) }} MB
                          </span>
                          <span v-if="simcard.usage_synced_at" class="text-xs text-gray-400">{{ shortTimeAgo(simcard.usage_synced_at) }}</span>
                        </div>
                        <span v-else class="text-gray-400">—</span>
                      </TableData>
                      <TableData :currentIndex="telcoIndex" :totalLength="simcards.length" inputClass="text-center">
                        <div class="flex justify-center space-x-1">
                          <Button
                            type="button" class="bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
                            @click="onEditClicked(simcard)"
                          >
                            <PencilSquareIcon class="w-4 h-4"></PencilSquareIcon>
                            <span>
                                Edit
                            </span>
                          </Button>
                          <Button
                            type="button" class="bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1"
                            @click="onDeleteClicked(simcard)"
                          >
                            <TrashIcon class="w-4 h-4"></TrashIcon>
                            <span>
                                Delete
                            </span>
                          </Button>
                        </div>
                      </TableData>
                      </tr>
                <tr v-if="!simcards.data.length">
                  <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                      No Results Found
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator v-if="simcards.data.length" :links="simcards.links" :meta="simcards.meta"></Paginator>
          </div>
      </div>
    </div>
  </div>
  <Form
      v-if="showModal"
      :simcard="simcard"
      :telcos="telcos"
      :vends="vends"
      :type="type"
      :showModal="showModal"
      @modalClose="onModalClose"
  >
  </Form>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import Form from '@/Pages/Simcard/Form.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { ArrowDownTrayIcon, BackspaceIcon, MagnifyingGlassIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ref, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";
import moment from 'moment';
import { internetLinkTitle, signalBars, signalBadgeClass } from '@/constants/internetLink';

// The bound machine's live-reported link (vends.internet_*) for the Signal
// Strength column — first bound vend that has ever reported one, else null.
function reportedLink(simcard) {
  const vends = simcard.vends || [];
  return vends.find((v) => v.internet_source) || null;
}

// Bound machines for the Machine ID column — id + code only, in the same order
// as the Site column, so the two stacks line up row for row. A machine with no
// code (never happens in practice) is left out rather than linked to nothing.
function machineCodes(simcard) {
  return (simcard.vends || [])
    .filter((vend) => vend.code)
    .map((vend) => ({ id: vend.id, code: vend.code }));
}

// Online/Offline per bound machine, for the top line of the Signal Strength
// column. Straight off vends.is_online — the machine's own HTTP heartbeat
// (SyncOnlineStatus marks it offline after 15 quiet minutes), the same signal
// the Operation Dashboard shows. This is NOT a telco reading: VoicePing's
// sim-info API carries package/usage only and has no network-attach state, so
// the machine heartbeat is the only online evidence mark1 has. A machine that
// has never checked in reads 'N/A' rather than 'Offline'.
function onlineStates(simcard) {
  return (simcard.vends || []).map((vend) => {
    if (!vend.last_updated_at) {
      return { id: vend.id, label: 'N/A', class: 'text-gray-400' };
    }
    return vend.is_online
      ? { id: vend.id, label: 'Online', class: 'text-green-600' }
      : { id: vend.id, label: 'Offline', class: 'text-red-600' };
  });
}

// Reported APK versionCode per bound machine, for the Machine APK column.
// Mirrors Vend::reportedApkVersion(): the OTA check-in writes
// vends.apk_version_code, the PWRON frame writes apk_ver_json.apkver, and the
// higher of the two is what the machine is actually running. Machines that
// have never reported either are left out (0 would read as a real version).
function apkVersions(simcard) {
  return (simcard.vends || [])
    .map((vend) => ({
      id: vend.id,
      version: Math.max(
        Number(vend.apk_version_code) || 0,
        Number(vend.apk_ver_json && vend.apk_ver_json.apkver) || 0
      ),
    }))
    .filter((apk) => apk.version > 0);
}

// Shorten the backend "x seconds/minutes/... ago" string (SimcardResource
// sends usage_synced_at as diffForHumans()) into a compact unit — e.g.
// "39 seconds ago" -> "39s ago". Same pattern as CustomerIndex.
function shortTimeAgo(str) {
  if (!str) return str;
  return str
    .replace(/\bseconds?\b/, 's')
    .replace(/\bminutes?\b/, 'm')
    .replace(/\bhours?\b/, 'h')
    .replace(/\bdays?\b/, 'd')
    .replace(/\bweeks?\b/, 'w')
    .replace(/\bmonths?\b/, 'mo')
    .replace(/\byears?\b/, 'y')
    .replace(/(\d)\s+([smhdwy])/, '$1$2');
}

// Status column badge colors. Status values come from the telco API
// (VoicePing: package status like "Activated"/"Expired", or card-level
// simStatus like "Normal" when no package) — unknown values fall back to gray
// rather than guessing.
function usageStatusBadgeClass(simcard) {
  const status = (simcard.usage_status || '').toLowerCase()
  if (['activated', 'normal', 'active'].includes(status)) {
    return 'bg-green-100 text-green-800 border-green-300'
  }
  if (['expired', 'deactivated', 'closed', 'suspended', 'terminated'].includes(status)) {
    return 'bg-red-100 text-red-800 border-red-300'
  }
  return 'bg-gray-100 text-gray-700 border-gray-300'
}

// Expire badge urgency — red once past, amber within 3 days (flags computed
// server-side in SimcardResource so no date parsing happens here).
function usageExpireBadgeClass(simcard) {
  if (simcard.usage_expired) {
    return 'bg-red-100 text-red-800 border-red-300'
  }
  if (simcard.usage_expiring_soon) {
    return 'bg-amber-100 text-amber-800 border-amber-300'
  }
  return 'bg-gray-100 text-gray-700 border-gray-300'
}

const props = defineProps({
  simcards: Object,
  telcos: Object,
  // The Status filter's options — the values the telco APIs actually report,
  // handed down by the controller so page and filter can never drift.
  usageStatusOptions: Array,
  vends: Object,
})

const filters = ref({
  code: '',
  vend_code: '',
  customer: '',
  telco_id: '',
  usage_status: '',
  sortKey: '',
  sortBy: true,
  numberPerPage: 100,
})
const loading = ref(false)
const showModal = ref(false)
const simcard = ref()
const type = ref('')
const toast = useToast()
const numberPerPageOptions = ref([])
const telcoOptions = ref([])
const usageStatusOptionList = ref([])

onMounted(() => {
  numberPerPageOptions.value = [
    { id: 100, value: 100 },
    { id: 200, value: 200 },
    { id: 500, value: 500 },
    { id: 'All', value: 'All' },
  ]
  filters.value.numberPerPage = numberPerPageOptions.value[0]
  telcoOptions.value = props.telcos.data.map((data) => {return {
    id: data.id,
    name: data.name,
  }})
  usageStatusOptionList.value = (props.usageStatusOptions || []).map((status) => {return {
    id: status,
    name: status,
  }})
})

// Everything the page and its Excel export send to the server: the filter box
// values, flattened out of the MultiSelect objects.
function queryParams() {
  return {
    ...filters.value,
    telco_id: filters.value.telco_id?.id ?? '',
    usage_status: filters.value.usage_status?.id ?? '',
  }
}

// Excel — the grid exactly as filtered and sorted, without the page limit.
function onExportExcelClicked() {
  loading.value = true
  axios({
    method: 'get',
    url: '/simcards/excel',
    params: queryParams(),
    responseType: 'blob',
  }).then(response => {
    fileDownload(response.data, 'Simcards' + moment().format('YYMMDDHHmmss') + '.xlsx')
  }).catch(() => {
    toast.error("Failed to export simcards", { timeout: 3000 })
  }).finally(() => {
    loading.value = false
  })
}

function onCreateClicked() {
  type.value = 'create'
  simcard.value = null
  showModal.value = true
}

function onDeleteClicked(simcard) {
  const approval = confirm('Are you sure to delete ' + simcard.code + '?');
  if (!approval) {
      return;
  }
  router.delete('/simcards/' + simcard.id, {
    onSuccess: () => {
      toast.success("Simcard deleted successfully", { timeout: 3000 })
    },
    onError: () => {
      toast.error("Failed to delete simcard", { timeout: 3000 })
    }
  })
}

function onEditClicked(telcoValue) {
  type.value = 'update'
  simcard.value = telcoValue
  showModal.value = true
}

function onSearchFilterUpdated() {
  router.get('/simcards', {
      ...queryParams(),
      numberPerPage: filters.value.numberPerPage.id,
  }, {
      preserveState: true,
      replace: true,
  })
}

function resetFilters() {
  router.get('/simcards')
}

function sortTable(sortKey) {
  filters.value.sortKey = sortKey
  filters.value.sortBy = !filters.value.sortBy
  onSearchFilterUpdated()
}

function onModalClose() {
  showModal.value = false
}
</script>