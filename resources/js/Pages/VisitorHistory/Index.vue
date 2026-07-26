<template>

  <Head title="Visitor History" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Visitor History
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3">

        <!-- View switch -->
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div class="inline-flex rounded-md shadow-sm" role="group">
            <button type="button"
              @click="switchView('sessions')"
              :class="[view === 'sessions' ? 'bg-green-500 text-white border-green-500' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50', 'px-4 py-2 text-sm font-medium border rounded-l-md focus:outline-none']">
              Login Sessions
            </button>
            <button type="button"
              @click="switchView('pages')"
              :class="[view === 'pages' ? 'bg-green-500 text-white border-green-500' : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50', 'px-4 py-2 text-sm font-medium border-t border-b border-r rounded-r-md focus:outline-none']">
              Page Views
            </button>
          </div>
          <p class="text-xs text-gray-500">
            History is kept for {{ retentionDays }} days.
          </p>
        </div>

        <!-- Filters -->
        <div class="grid grid-cols-1 md:grid-cols-6 gap-2 mt-3">
          <div class="col-span-6 md:col-span-1">
            <DatePicker v-model="filters.dateFrom">From</DatePicker>
          </div>
          <div class="col-span-6 md:col-span-1">
            <DatePicker v-model="filters.dateTo" :minDate="filters.dateFrom">To</DatePicker>
          </div>
          <div class="col-span-6 md:col-span-2">
            <label for="text" class="block text-sm font-medium text-gray-700">
              User
            </label>
            <MultiSelect
              v-model="filters.users"
              :options="userOptions"
              trackBy="id"
              valueProp="id"
              label="name"
              placeholder="All users"
              open-direction="bottom"
              class="mt-1"
              mode="tags"
            >
            </MultiSelect>
          </div>
          <div class="col-span-6 md:col-span-1">
            <SearchInput placeholderStr="e.g. 175.140" v-model="filters.ip" @keyup.enter="onSearchFilterUpdated()">
              IP Address
            </SearchInput>
          </div>
          <div class="col-span-6 md:col-span-1">
            <SearchInput placeholderStr="e.g. /transactions" v-model="filters.path" @keyup.enter="onSearchFilterUpdated()">
              Page
            </SearchInput>
          </div>
          <div class="col-span-6 md:col-span-1" v-if="view === 'sessions'">
            <label for="text" class="block text-sm font-medium text-gray-700">
              Device
            </label>
            <MultiSelect
              v-model="filters.deviceType"
              :options="deviceTypeOptions"
              trackBy="id"
              valueProp="id"
              label="name"
              placeholder="Any"
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
                <span>Search</span>
              </Button>
              <Button class="inline-flex space-x-1 items-center rounded-md border border-gray-300 bg-white px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                @click="resetFilters()"
              >
                <BackspaceIcon class="h-4 w-4" aria-hidden="true"/>
                <span>Reset</span>
              </Button>
            </div>
          </div>
          <div class="flex flex-col space-y-2">
            <p class="text-sm text-gray-700 leading-5 flex space-x-1">
              <span>Showing</span>
              <span class="font-medium">{{ rows.meta ? (rows.meta.from ?? 0) : 0 }}</span>
              <span>to</span>
              <span class="font-medium">{{ rows.meta ? (rows.meta.to ?? 0) : 0 }}</span>
              <span>of</span>
              <span class="font-medium">{{ rows.meta ? rows.meta.total : 0 }}</span>
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

        <!-- Summary -->
        <div class="mt-4 grid grid-cols-3 gap-2 text-center">
          <div class="rounded-md bg-gray-50 border px-3 py-2">
            <p class="text-xs text-gray-500">Login Sessions</p>
            <p class="text-lg font-semibold text-gray-800">{{ summary.sessions }}</p>
          </div>
          <div class="rounded-md bg-gray-50 border px-3 py-2">
            <p class="text-xs text-gray-500">Distinct Users</p>
            <p class="text-lg font-semibold text-gray-800">{{ summary.users }}</p>
          </div>
          <div class="rounded-md bg-gray-50 border px-3 py-2">
            <p class="text-xs text-gray-500">Page Views</p>
            <p class="text-lg font-semibold text-gray-800">{{ summary.page_views }}</p>
          </div>
        </div>

        <p class="mt-3 text-xs text-gray-500 leading-relaxed">
          <span class="font-medium text-gray-700">How to read the timings:</span>
          a <span class="inline-flex items-center rounded-full bg-green-100 text-green-800 px-2 py-0.5 text-[10px] font-medium">measured</span>
          duration was reported by the browser itself (accurate, and it excludes time the tab spent in the background).
          An <span class="inline-flex items-center rounded-full bg-amber-100 text-amber-800 px-2 py-0.5 text-[10px] font-medium">est.</span>
          duration is only the gap until the next page was opened, so it includes idle time.
          A session shows <em>Logged out</em> only when the user actually clicked Log Out; closing the tab shows
          <em>Left / closed tab</em>, and a session that simply went quiet past the {{ sessionLifetime }}-minute
          session window is marked <em>Expired (inferred)</em>.
        </p>
      </div>

      <!-- SESSIONS TABLE -->
      <div class="mt-6 flex flex-col" v-if="view === 'sessions'">
        <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll">
            <table class="min-w-full border-separate" style="border-spacing: 0">
              <thead class="bg-gray-100">
                <tr class="divide-x divide-gray-200">
                  <TableHead>#</TableHead>
                  <TableHead>User</TableHead>
                  <TableHead>Login</TableHead>
                  <TableHead>IP Address</TableHead>
                  <TableHead>Device</TableHead>
                  <TableHead>Browser</TableHead>
                  <TableHead>Pages</TableHead>
                  <TableHead>Session Length</TableHead>
                  <TableHead>Ended</TableHead>
                  <TableHead></TableHead>
                </tr>
              </thead>
              <tbody class="bg-white">
                <template v-for="(session, sessionIndex) in sessions.data" :key="session.id">
                  <tr class="divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100">
                    <TableData :currentIndex="sessionIndex" :totalLength="sessions.data.length" inputClass="text-center">
                      {{ (sessions.meta.from ?? 0) + sessionIndex }}
                    </TableData>
                    <TableData :currentIndex="sessionIndex" :totalLength="sessions.data.length" inputClass="text-left">
                      <div class="font-medium text-gray-800">{{ session.user_name ?? '—' }}</div>
                      <div class="text-xs text-gray-500">{{ session.user_email }}</div>
                      <div class="text-xs text-gray-400" v-if="session.operator_code">{{ session.operator_code }}</div>
                    </TableData>
                    <TableData :currentIndex="sessionIndex" :totalLength="sessions.data.length" inputClass="text-center">
                      {{ session.login_at ?? '—' }}
                    </TableData>
                    <TableData :currentIndex="sessionIndex" :totalLength="sessions.data.length" inputClass="text-center">
                      {{ session.ip ?? '—' }}
                    </TableData>
                    <TableData :currentIndex="sessionIndex" :totalLength="sessions.data.length" inputClass="text-center">
                      <span :class="deviceBadgeClass(session.device_type)" class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium">
                        {{ deviceLabel(session.device_type) }}
                      </span>
                      <div class="text-xs text-gray-500 mt-1">{{ session.platform ?? '—' }}</div>
                    </TableData>
                    <TableData :currentIndex="sessionIndex" :totalLength="sessions.data.length" inputClass="text-center">
                      <span v-tooltip="session.user_agent">{{ session.browser_label }}</span>
                    </TableData>
                    <TableData :currentIndex="sessionIndex" :totalLength="sessions.data.length" inputClass="text-center">
                      {{ session.page_view_count }}
                    </TableData>
                    <TableData :currentIndex="sessionIndex" :totalLength="sessions.data.length" inputClass="text-center">
                      {{ session.duration_label }}
                    </TableData>
                    <TableData :currentIndex="sessionIndex" :totalLength="sessions.data.length" inputClass="text-center">
                      <span :class="statusBadgeClass(session)" class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium">
                        {{ session.status }}
                      </span>
                      <div class="text-xs text-gray-500 mt-1" v-if="session.ended_at">{{ session.ended_at }}</div>
                    </TableData>
                    <TableData :currentIndex="sessionIndex" :totalLength="sessions.data.length" inputClass="text-center">
                      <Button
                        type="button" class="bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
                        @click="toggleSession(session)"
                      >
                        <component :is="expanded[session.id] ? ChevronUpIcon : ChevronDownIcon" class="w-4 h-4" />
                        <span>Pages</span>
                      </Button>
                    </TableData>
                  </tr>
                  <tr v-if="expanded[session.id]" class="bg-gray-50">
                    <td colspan="10" class="px-4 py-3">
                      <div v-if="loadingSession === session.id" class="text-sm text-gray-500">
                        Loading pages…
                      </div>
                      <div v-else-if="!(sessionPages[session.id] || []).length" class="text-sm text-gray-500">
                        No page views recorded for this session.
                      </div>
                      <table v-else class="min-w-full text-sm">
                        <thead>
                          <tr class="text-left text-xs uppercase text-gray-500">
                            <th class="py-1 pr-4">Opened</th>
                            <th class="py-1 pr-4">Page</th>
                            <th class="py-1 pr-4">Time on Page</th>
                            <th class="py-1 pr-4">Active</th>
                          </tr>
                        </thead>
                        <tbody>
                          <tr v-for="pageView in sessionPages[session.id]" :key="pageView.id" class="border-t border-gray-200">
                            <td class="py-1 pr-4 whitespace-nowrap text-gray-600">{{ pageView.viewed_at }}</td>
                            <td class="py-1 pr-4 text-gray-800">
                              <span class="font-medium">{{ pageView.path }}</span>
                              <span v-if="pageView.query_string" class="text-xs text-gray-400">?{{ pageView.query_string }}</span>
                            </td>
                            <td class="py-1 pr-4 whitespace-nowrap">
                              <span class="text-gray-800">{{ pageView.duration_label }}</span>
                              <span v-if="pageView.duration_source" :class="pageView.is_estimated ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800'"
                                class="ml-1 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium">
                                {{ pageView.is_estimated ? 'est.' : 'measured' }}
                              </span>
                            </td>
                            <td class="py-1 pr-4 whitespace-nowrap text-gray-600">
                              {{ pageView.active_seconds !== null ? pageView.active_label : '—' }}
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </td>
                  </tr>
                </template>
                <tr v-if="!sessions.data.length">
                  <td colspan="10" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                    No Results Found
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator v-if="sessions.data.length" :links="sessions.links" :meta="sessions.meta"></Paginator>
          </div>
        </div>
      </div>

      <!-- PAGE VIEWS TABLE -->
      <div class="mt-6 flex flex-col" v-else>
        <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll">
            <table class="min-w-full border-separate" style="border-spacing: 0">
              <thead class="bg-gray-100">
                <tr class="divide-x divide-gray-200">
                  <TableHead>#</TableHead>
                  <TableHead>Opened</TableHead>
                  <TableHead>User</TableHead>
                  <TableHead>Page</TableHead>
                  <TableHead>IP Address</TableHead>
                  <TableHead>Time on Page</TableHead>
                  <TableHead>Active</TableHead>
                </tr>
              </thead>
              <tbody class="bg-white">
                <tr v-for="(pageView, pageViewIndex) in pageViews.data" :key="pageView.id" class="divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100">
                  <TableData :currentIndex="pageViewIndex" :totalLength="pageViews.data.length" inputClass="text-center">
                    {{ (pageViews.meta.from ?? 0) + pageViewIndex }}
                  </TableData>
                  <TableData :currentIndex="pageViewIndex" :totalLength="pageViews.data.length" inputClass="text-center">
                    {{ pageView.viewed_at }}
                  </TableData>
                  <TableData :currentIndex="pageViewIndex" :totalLength="pageViews.data.length" inputClass="text-left">
                    {{ pageView.user_name ?? '—' }}
                  </TableData>
                  <TableData :currentIndex="pageViewIndex" :totalLength="pageViews.data.length" inputClass="text-left">
                    <span class="font-medium text-gray-800">{{ pageView.path }}</span>
                    <span v-if="pageView.query_string" class="block text-xs text-gray-400 truncate max-w-md">?{{ pageView.query_string }}</span>
                  </TableData>
                  <TableData :currentIndex="pageViewIndex" :totalLength="pageViews.data.length" inputClass="text-center">
                    {{ pageView.ip ?? '—' }}
                  </TableData>
                  <TableData :currentIndex="pageViewIndex" :totalLength="pageViews.data.length" inputClass="text-center">
                    <span>{{ pageView.duration_label }}</span>
                    <span v-if="pageView.duration_source" :class="pageView.is_estimated ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800'"
                      class="ml-1 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-medium">
                      {{ pageView.is_estimated ? 'est.' : 'measured' }}
                    </span>
                  </TableData>
                  <TableData :currentIndex="pageViewIndex" :totalLength="pageViews.data.length" inputClass="text-center">
                    {{ pageView.active_seconds !== null ? pageView.active_label : '—' }}
                  </TableData>
                </tr>
                <tr v-if="!pageViews.data.length">
                  <td colspan="7" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                    No Results Found
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator v-if="pageViews.data.length" :links="pageViews.links" :meta="pageViews.meta"></Paginator>
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import DatePicker from '@/Components/DatePicker.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import TableData from '@/Components/TableData.vue';
import TableHead from '@/Components/TableHead.vue';
import { BackspaceIcon, ChevronDownIcon, ChevronUpIcon, MagnifyingGlassIcon } from '@heroicons/vue/20/solid';
import { Head, router } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
  view: String,
  sessions: Object,
  pageViews: Object,
  summary: Object,
  userOptions: Array,
  deviceTypeOptions: Array,
  appliedFilters: Object,
  retentionDays: Number,
  sessionLifetime: Number,
})

// Whichever collection the current view renders — used only for the
// "showing x to y of z" counter, so it must tolerate the other one being null.
const rows = computed(() => (props.view === 'sessions' ? props.sessions : props.pageViews) ?? {})

const numberPerPageOptions = ref([
  { id: 100, value: 100 },
  { id: 200, value: 200 },
  { id: 500, value: 500 },
])

const filters = ref({
  dateFrom: props.appliedFilters.dateFrom,
  dateTo: props.appliedFilters.dateTo,
  users: [],
  ip: props.appliedFilters.ip ?? '',
  path: props.appliedFilters.path ?? '',
  deviceType: '',
  numberPerPage: numberPerPageOptions.value[0],
})

const expanded = ref({})
const sessionPages = ref({})
const loadingSession = ref(null)

onMounted(() => {
  // Re-hydrate the multiselects from the querystring the server echoed back so a
  // refresh / back-button keeps the filters visible.
  const ids = props.appliedFilters.userIds ?? []
  filters.value.users = props.userOptions.filter((option) => ids.includes(option.id))

  if (props.appliedFilters.deviceType) {
    filters.value.deviceType = props.deviceTypeOptions.find((option) => option.id === props.appliedFilters.deviceType) ?? ''
  }

  const perPage = numberPerPageOptions.value.find((option) => option.id === Number(props.appliedFilters.numberPerPage))
  if (perPage) {
    filters.value.numberPerPage = perPage
  }
})

function onSearchFilterUpdated(view) {
  router.get('/visitor-history', {
    view: view ?? props.view,
    dateFrom: filters.value.dateFrom,
    dateTo: filters.value.dateTo,
    userIds: (filters.value.users ?? []).filter((user) => user).map((user) => user.id),
    ip: filters.value.ip,
    path: filters.value.path,
    deviceType: filters.value.deviceType?.id ?? '',
    numberPerPage: filters.value.numberPerPage?.id ?? 100,
  }, {
    preserveState: true,
    replace: true,
  })
}

function switchView(view) {
  if (view === props.view) {
    return
  }
  expanded.value = {}
  onSearchFilterUpdated(view)
}

function resetFilters() {
  router.get('/visitor-history')
}

function toggleSession(session) {
  if (expanded.value[session.id]) {
    expanded.value = { ...expanded.value, [session.id]: false }
    return
  }

  expanded.value = { ...expanded.value, [session.id]: true }

  // Cached after the first open — the rows are immutable history.
  if (sessionPages.value[session.id]) {
    return
  }

  loadingSession.value = session.id
  window.axios.get('/visitor-history/sessions/' + session.id + '/page-views')
    .then((response) => {
      sessionPages.value = { ...sessionPages.value, [session.id]: response.data.data ?? [] }
    })
    .catch(() => {
      sessionPages.value = { ...sessionPages.value, [session.id]: [] }
    })
    .finally(() => {
      loadingSession.value = null
    })
}

function deviceLabel(deviceType) {
  if (deviceType === 'desktop') return 'Desktop'
  if (deviceType === 'mobile') return 'Mobile'
  if (deviceType === 'tablet') return 'Tablet'
  if (deviceType === 'bot') return 'Bot'
  return 'Unknown'
}

function deviceBadgeClass(deviceType) {
  if (deviceType === 'desktop') return 'bg-blue-100 text-blue-800'
  if (deviceType === 'mobile') return 'bg-purple-100 text-purple-800'
  if (deviceType === 'tablet') return 'bg-teal-100 text-teal-800'
  if (deviceType === 'bot') return 'bg-red-100 text-red-800'
  return 'bg-gray-100 text-gray-700'
}

function statusBadgeClass(session) {
  if (session.status === 'Active') return 'bg-green-100 text-green-800'
  if (session.status === 'Logged out') return 'bg-gray-200 text-gray-700'
  if (session.status === 'Left / closed tab') return 'bg-blue-100 text-blue-800'
  return 'bg-amber-100 text-amber-800'
}
</script>
