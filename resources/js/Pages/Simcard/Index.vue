<template>

  <Head title="Simcard" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Simcard
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
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
          <SearchInput placeholderStr="MSISDN" v-model="filters.msisdn">
            MSISDN
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
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
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
                    <TableHead>
                      Site
                    </TableHead>
                    <TableHead>
                      Machine APK
                    </TableHead>
                    <TableHeadSort modelName="telco_id" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('telco_id')">
                      SimCard Package
                    </TableHeadSort>
                    <TableHead>
                      Signal Strength
                    </TableHead>
                    <TableHeadSort modelName="msisdn" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('msisdn')">
                      MSISDN
                    </TableHeadSort>
                    <TableHeadSort modelName="updated_at" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('updated_at')">
                      Updated By
                    </TableHeadSort>
                    <TableHead>
                      Status
                    </TableHead>
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
                      <TableData :currentIndex="telcoIndex" :totalLength="simcards.length" inputClass="text-center">
                        {{ simcard.vend_code }}
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
                        <div v-if="reportedLink(simcard)" class="flex flex-col items-center space-y-1">
                          <span class="text-xs font-bold text-gray-700">{{ internetLinkTitle(reportedLink(simcard)) }}</span>
                          <span
                            v-if="signalBars(reportedLink(simcard)) || reportedLink(simcard).internet_source === 'none'"
                            class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-bold border"
                            :class="signalBadgeClass(reportedLink(simcard))"
                          >
                            {{ signalBars(reportedLink(simcard)) || 'No Link' }}
                          </span>
                        </div>
                        <span v-else class="text-gray-400">—</span>
                      </TableData>
                      <TableData :currentIndex="telcoIndex" :totalLength="simcards.length" inputClass="text-left">
                        {{ simcard.msisdn }}
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
import { BackspaceIcon, MagnifyingGlassIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ref, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";
import { internetLinkTitle, signalBars, signalBadgeClass } from '@/constants/internetLink';

// The bound machine's live-reported link (vends.internet_*) for the Signal
// Strength column — first bound vend that has ever reported one, else null.
function reportedLink(simcard) {
  const vends = simcard.vends || [];
  return vends.find((v) => v.internet_source) || null;
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
  vends: Object,
})

const filters = ref({
  code: '',
  vend_code: '',
  msisdn: '',
  telco_id: '',
  sortKey: '',
  sortBy: true,
  numberPerPage: 100,
})
const showModal = ref(false)
const simcard = ref()
const type = ref('')
const toast = useToast()
const numberPerPageOptions = ref([])
const telcoOptions = ref([])

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
})

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
      ...filters.value,
      telco_id: filters.value.telco_id.id,
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