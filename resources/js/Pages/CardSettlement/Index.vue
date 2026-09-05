<template>

  <Head title="Card Settlement" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Card Settlement
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-3 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
        <!-- Upload -->
        <div class="grid grid-cols-1 md:grid-cols-6 gap-2 items-end">
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
              Provider
            </label>
            <MultiSelect
              v-model="uploadProvider"
              :options="providerOptions"
              trackBy="id"
              valueProp="id"
              label="name"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
            >
            </MultiSelect>
          </div>
          <div class="md:col-span-2">
            <label for="text" class="block text-sm font-medium text-gray-700">
              Settlement Report File
            </label>
            <input
              type="file"
              accept=".csv,text/csv"
              class="mt-1 block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-gray-200 file:px-3 file:py-2 file:text-sm file:font-medium file:text-gray-700 hover:file:bg-gray-300"
              @change="uploadForm.file = $event.target.files[0]"
            />
            <div class="text-sm text-red-600" v-if="uploadForm.errors.file">
              {{ uploadForm.errors.file }}
            </div>
          </div>
          <div>
            <Button
              class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50"
              :disabled="!uploadForm.file || uploadForm.processing"
              @click="submitUpload()"
            >
              <ArrowUpTrayIcon class="h-4 w-4" aria-hidden="true"/>
              <span>
                {{ uploadForm.processing ? ('Uploading… ' + uploadProgress + '%') : 'Upload & Match' }}
              </span>
            </Button>
          </div>
        </div>
        <p class="mt-2 text-xs text-gray-400">
          NETS: upload the raw MerchantConnect daily CSV (MCONNECT_…_STDRPT01_….csv). Avoid opening it
          in Excel first — a re-saved file loses the hour of each transaction time and rows can only be
          matched approximately.
        </p>

        <!-- Filters -->
        <div class="grid grid-cols-1 md:grid-cols-6 gap-2 mt-4">
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
              Status
            </label>
            <MultiSelect
              v-model="filters.status"
              :options="statusOptions"
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
            <DatePicker v-model="filters.date_from">
              Cutover From
            </DatePicker>
          </div>
          <div>
            <DatePicker v-model="filters.date_to" :minDate="filters.date_from">
              Cutover To
            </DatePicker>
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
                  <span class="font-medium">{{ reports.meta.from ?? 0 }}</span>
                  <span>to</span>
                  <span class="font-medium">{{ reports.meta.to ?? 0 }}</span>
                  <span>of</span>
                  <span class="font-medium">{{ reports.meta.total }}</span>
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
                    <TableHeadSort modelName="cutover_date" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('cutover_date')">
                      Cutover
                    </TableHeadSort>
                    <TableHeadSort modelName="original_filename" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('original_filename')">
                      File
                    </TableHeadSort>
                    <TableHeadSort modelName="status" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('status')">
                      Status
                    </TableHeadSort>
                    <TableHead>
                      Purchases
                    </TableHead>
                    <TableHead>
                      Matched
                    </TableHead>
                    <TableHead>
                      Queries
                    </TableHead>
                    <TableHead>
                      Duplicates
                    </TableHead>
                    <TableHead>
                      Synced
                    </TableHead>
                    <TableHeadSort modelName="created_at" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('created_at')">
                      Uploaded
                    </TableHeadSort>
                    <TableHead>
                    </TableHead>
                  </tr>
                </thead>
                  <tbody class="bg-white">
                    <tr v-for="(report, reportIndex) in reports.data" :key="report.id" class="divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100">
                      <TableData :currentIndex="reportIndex" :totalLength="reports.length" inputClass="text-center">
                        {{ reports.meta.from + reportIndex }}
                      </TableData>
                      <TableData :currentIndex="reportIndex" :totalLength="reports.length" inputClass="text-center">
                        <span v-if="report.cutover_date">{{ report.cutover_date }}</span>
                        <span v-else class="text-gray-400">—</span>
                      </TableData>
                      <TableData :currentIndex="reportIndex" :totalLength="reports.length" inputClass="text-left">
                        {{ report.original_filename }}
                        <span class="ml-1 text-xs uppercase text-gray-400">{{ report.provider }}</span>
                      </TableData>
                      <TableData :currentIndex="reportIndex" :totalLength="reports.length" inputClass="text-center">
                        <span
                          class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-bold border"
                          :class="statusBadgeClass(report.status)"
                          :title="report.error_message || ''"
                        >
                          {{ statusLabels[report.status] || report.status }}
                        </span>
                      </TableData>
                      <TableData :currentIndex="reportIndex" :totalLength="reports.length" inputClass="text-center">
                        {{ report.purchase_rows }} / {{ report.total_rows }}
                        <span
                          v-if="report.partial_time_rows"
                          class="block text-xs text-amber-700"
                          :title="report.partial_time_rows + ' lines lost their hour (file re-saved in Excel) — matched on minute:second only'"
                        >
                          ⚠ no hour on {{ report.partial_time_rows }}
                        </span>
                      </TableData>
                      <TableData :currentIndex="reportIndex" :totalLength="reports.length" inputClass="text-center">
                        <span class="text-green-700 font-medium">{{ report.matched_count }}</span>
                      </TableData>
                      <TableData :currentIndex="reportIndex" :totalLength="reports.length" inputClass="text-center">
                        <span v-if="report.unmatched_count + report.ambiguous_count" class="text-amber-700 font-bold">
                          {{ report.unmatched_count + report.ambiguous_count }}
                        </span>
                        <span v-else class="text-gray-400">0</span>
                      </TableData>
                      <TableData :currentIndex="reportIndex" :totalLength="reports.length" inputClass="text-center">
                        <span :class="report.duplicate_count ? '' : 'text-gray-400'">{{ report.duplicate_count }}</span>
                      </TableData>
                      <TableData :currentIndex="reportIndex" :totalLength="reports.length" inputClass="text-center">
                        <span v-if="report.synced_at">{{ report.synced_count }}</span>
                        <span v-else class="text-gray-400">—</span>
                      </TableData>
                      <TableData :currentIndex="reportIndex" :totalLength="reports.length" inputClass="text-center">
                        <div class="flex flex-col space-y-1">
                          <span>{{ report.created_at }}</span>
                          <span v-if="report.uploadedBy && report.uploadedBy.name" class="text-xs text-gray-500">{{ report.uploadedBy.name }}</span>
                        </div>
                      </TableData>
                      <TableData :currentIndex="reportIndex" :totalLength="reports.length" inputClass="text-center">
                        <div class="flex justify-center space-x-1">
                          <Button
                            type="button" class="bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
                            @click="router.visit('/card-settlements/' + report.id)"
                          >
                            <EyeIcon class="w-4 h-4"></EyeIcon>
                            <span>
                                View
                            </span>
                          </Button>
                          <Button
                            v-if="report.status !== 'synced'"
                            type="button" class="bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1"
                            @click="onDeleteClicked(report)"
                          >
                            <TrashIcon class="w-4 h-4"></TrashIcon>
                            <span>
                                Delete
                            </span>
                          </Button>
                        </div>
                      </TableData>
                      </tr>
                <tr v-if="!reports.data.length">
                  <td colspan="11" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                      No Results Found
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator v-if="reports.data.length" :links="reports.links" :meta="reports.meta"></Paginator>
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
import Paginator from '@/Components/Paginator.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { ArrowUpTrayIcon, EyeIcon, MagnifyingGlassIcon, TrashIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ref, onMounted } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";

const props = defineProps({
  reports: Object,
  providers: Array,
  statuses: Array,
  filters: Object,
})

const statusLabels = {
  uploaded: 'Uploaded',
  matching: 'Matching…',
  review: 'Review',
  synced: 'Synced',
  failed: 'Failed',
}

function statusBadgeClass(status) {
  return {
    uploaded: 'bg-gray-100 text-gray-700 border-gray-300',
    matching: 'bg-amber-100 text-amber-800 border-amber-300',
    review: 'bg-blue-100 text-blue-800 border-blue-300',
    synced: 'bg-green-100 text-green-800 border-green-300',
    failed: 'bg-red-100 text-red-800 border-red-300',
  }[status] || 'bg-gray-100 text-gray-700 border-gray-300'
}

const filters = ref({
  status: props.filters.status || 'all',
  date_from: props.filters.date_from || '',
  date_to: props.filters.date_to || '',
  sortKey: props.filters.sortKey || 'cutover_date',
  // Query-string round-trips turn the boolean into "true"/"false" strings.
  sortBy: String(props.filters.sortBy ?? false) === 'true',
  numberPerPage: 100,
})
const toast = useToast()
const numberPerPageOptions = ref([])
const providerOptions = ref([])
const statusOptions = ref([])
const uploadProvider = ref(null)
const uploadForm = useForm({ provider: '', file: null })
const uploadProgress = ref(0)

onMounted(() => {
  numberPerPageOptions.value = [
    { id: 100, value: 100 },
    { id: 200, value: 200 },
    { id: 500, value: 500 },
    { id: 'All', value: 'All' },
  ]
  filters.value.numberPerPage = numberPerPageOptions.value[0]
  providerOptions.value = props.providers.map((p) => ({ id: p, name: p.toUpperCase() }))
  uploadProvider.value = providerOptions.value[0] || null
  statusOptions.value = [
    { id: 'all', name: 'All' },
    ...props.statuses.map((s) => ({ id: s, name: statusLabels[s] || s })),
  ]
  filters.value.status = statusOptions.value.find((o) => o.id === filters.value.status) || statusOptions.value[0]
})

function submitUpload() {
  uploadForm.provider = uploadProvider.value ? uploadProvider.value.id : ''
  uploadForm.post('/card-settlements', {
    forceFormData: true,
    preserveScroll: true,
    onProgress: (e) => { uploadProgress.value = e ? Math.round(e.percentage) : 0; },
    onSuccess: () => {
      toast.success("Report uploaded — matching runs in the background", { timeout: 4000 })
      uploadForm.reset()
      uploadProgress.value = 0
    },
    onError: () => {
      uploadProgress.value = 0
      toast.error("Upload failed — check the file", { timeout: 3000 })
    },
  })
}

function onDeleteClicked(report) {
  const approval = confirm('Are you sure to delete report ' + report.original_filename + ' and all its rows?');
  if (!approval) {
      return;
  }
  router.delete('/card-settlements/' + report.id, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success("Report deleted successfully", { timeout: 3000 })
    },
    onError: () => {
      toast.error("Failed to delete report", { timeout: 3000 })
    }
  })
}

function onSearchFilterUpdated() {
  router.get('/card-settlements', {
      ...filters.value,
      status: filters.value.status ? filters.value.status.id : 'all',
      numberPerPage: filters.value.numberPerPage.id,
  }, {
      preserveState: true,
      replace: true,
  })
}

function sortTable(sortKey) {
  filters.value.sortKey = sortKey
  filters.value.sortBy = !filters.value.sortBy
  onSearchFilterUpdated()
}
</script>
