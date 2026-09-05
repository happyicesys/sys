<template>

  <Head title="Card Terminal" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Data Management (Card Terminal)
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
        <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
          <SearchInput placeholderStr="Terminal ID" v-model="filters.terminal_id">
            Terminal ID
          </SearchInput>
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
              Card Terminal Company
            </label>
            <MultiSelect
              v-model="filters.card_terminal_id"
              :options="companyFilterOptions"
              trackBy="id"
              valueProp="id"
              label="name"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
            >
            </MultiSelect>
          </div>
          <SearchInput placeholderStr="Machine ID" v-model="filters.vend_code">
            Machine ID
          </SearchInput>
          <SearchInput placeholderStr="Remarks" v-model="filters.remarks">
            Remarks
          </SearchInput>
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
              <!-- ring-1 ring-gray-400, matching the export button: the plain
                   `border` this used to carry was near-invisible on white. -->
              <Button class="inline-flex space-x-1 items-center rounded-md bg-white px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-400 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
              @click="resetFilters()"
              >
                <BackspaceIcon class="h-4 w-4" aria-hidden="true"/>
                <span>
                  Reset
                </span>
              </Button>
              <Button type="button"
                class="inline-flex space-x-1 items-center rounded-md bg-white px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-400 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                :disabled="loading"
                @click.prevent="onExportExcelClicked()"
              >
                <ArrowDownTrayIcon v-if="!loading" class="h-4 w-4" aria-hidden="true"/>
                <svg v-else aria-hidden="true" class="w-4 h-4 text-gray-200 animate-spin fill-green-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                  <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                </svg>
                <span>
                  Export Excel
                </span>
              </Button>
            </div>
          </div>
          <div class="flex space-x-3 items-center">
            <div class="text-sm text-gray-700">
                Showing
                <span class="font-medium">{{ cardTerminalUnits.meta.from ?? 0 }}</span>
                to
                <span class="font-medium">{{ cardTerminalUnits.meta.to ?? 0 }}</span>
                of
                <span class="font-medium">{{ cardTerminalUnits.meta.total }}</span>
                results
            </div>
            <MultiSelect
                v-model="filters.numberPerPage"
                :options="numberPerPageOptions"
                trackBy="id"
                label="value"
                @select="onSearchFilterUpdated()"
                :showLabels="false"
                :allowEmpty="false"
                :searchable="false"
                class="w-32"
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
                    <TableHeadSort modelName="terminal_id" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('terminal_id')">
                      Terminal ID
                    </TableHeadSort>
                    <TableHeadSort modelName="card_terminal_id" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('card_terminal_id')">
                      Card Terminal Company
                    </TableHeadSort>
                    <TableHead>
                      Machine ID
                    </TableHead>
                    <TableHead>
                      Remarks
                    </TableHead>
                    <TableHead>
                    </TableHead>
                  </tr>
                </thead>
                  <tbody class="bg-white">
                    <tr v-for="(unit, unitIndex) in cardTerminalUnits.data" :key="unit.id" class="divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100">
                      <TableData :currentIndex="unitIndex" :totalLength="cardTerminalUnits.length" inputClass="text-center">
                        {{ cardTerminalUnits.meta.from + unitIndex }}
                      </TableData>
                      <TableData :currentIndex="unitIndex" :totalLength="cardTerminalUnits.length" inputClass="text-left font-mono">
                        {{ unit.terminal_id }}
                      </TableData>
                      <TableData :currentIndex="unitIndex" :totalLength="cardTerminalUnits.length" inputClass="text-left">
                        {{ unit.card_terminal_name ?? '-' }}
                      </TableData>
                      <!--
                        Read-only: which machine this terminal is on today. The
                        binding itself is only ever changed on that machine's
                        Setting/Edit page.
                      -->
                      <TableData :currentIndex="unitIndex" :totalLength="cardTerminalUnits.length" inputClass="text-left">
                        <span v-if="unit.current_vend_code" class="flex flex-col">
                          <!-- Straight to the machine's Settings page, which is
                               where this terminal's binding is changed. -->
                          <a class="text-blue-700 underline" target="_blank" :href="'/settings/vend/' + unit.current_vend_id + '/update'">
                            {{ unit.current_vend_code }}
                          </a>
                          <span class="text-xs text-gray-500">{{ unit.current_vend_name }}</span>
                        </span>
                        <span v-else class="text-gray-400">Not on a machine</span>
                      </TableData>
                      <TableData :currentIndex="unitIndex" :totalLength="cardTerminalUnits.length" inputClass="text-left">
                        {{ unit.remarks }}
                      </TableData>
                      <TableData :currentIndex="unitIndex" :totalLength="cardTerminalUnits.length" inputClass="text-center">
                        <div class="flex justify-center space-x-1">
                          <Button
                            type="button" class="bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
                            @click="onEditClicked(unit)"
                          >
                            <PencilSquareIcon class="w-4 h-4"></PencilSquareIcon>
                            <span>
                                Edit
                            </span>
                          </Button>
                          <Button
                            type="button" class="bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1"
                            @click="onDeleteClicked(unit)"
                          >
                            <TrashIcon class="w-4 h-4"></TrashIcon>
                            <span>
                                Delete
                            </span>
                          </Button>
                        </div>
                      </TableData>
                      </tr>
                <tr v-if="!cardTerminalUnits.data.length">
                  <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                      No Results Found
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator v-if="cardTerminalUnits.data.length" :links="cardTerminalUnits.links" :meta="cardTerminalUnits.meta"></Paginator>
          </div>
      </div>
    </div>
  </div>
  <Form
      v-if="showModal"
      :cardTerminalUnit="cardTerminalUnit"
      :cardTerminalOptions="cardTerminalOptions"
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
import Form from '@/Pages/CardTerminalUnit/Form.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { ArrowDownTrayIcon, BackspaceIcon, MagnifyingGlassIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { useToast } from "vue-toastification";
import { Head } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3'
import moment from 'moment'

const props = defineProps({
  cardTerminalUnits: Object,
  cardTerminalOptions: Object,
  filters: Object,
})

const filters = ref({
  terminal_id: props.filters?.terminal_id ?? '',
  card_terminal_id: null,
  vend_code: props.filters?.vend_code ?? '',
  remarks: props.filters?.remarks ?? '',
  sortKey: props.filters?.sortKey ?? 'terminal_id',
  sortBy: props.filters?.sortBy ?? true,
  numberPerPage: 100,
})
const showModal = ref(false)
const cardTerminalUnit = ref()
const type = ref('')
const toast = useToast()
const numberPerPageOptions = ref([])
const loading = ref(false)

// 'none' is a real filter, not a placeholder: 5 backfilled terminals came off
// machines that carry no company, and they need to be findable to be fixed.
const companyFilterOptions = computed(() => [
  { id: 'all', name: 'All' },
  { id: 'none', name: '(No company)' },
  ...((props.cardTerminalOptions?.data) ?? []).map(company => ({
    id: company.id,
    name: company.name,
  })),
])

onMounted(() => {
  // MultiSelect works on option OBJECTS (:object="true"), so the company
  // filter is rehydrated from the id the server echoed back.
  filters.value.card_terminal_id = companyFilterOptions.value
    .find(o => String(o.id) === String(props.filters?.card_terminal_id ?? 'all')) ?? companyFilterOptions.value[0]

  numberPerPageOptions.value = [
    { id: 100, value: 100 },
    { id: 200, value: 200 },
    { id: 500, value: 500 },
    { id: 'All', value: 'All' },
  ]
  filters.value.numberPerPage = numberPerPageOptions.value[0]
})

function onCreateClicked() {
  type.value = 'create'
  cardTerminalUnit.value = null
  showModal.value = true
}

function onDeleteClicked(unit) {
  const warning = unit.current_vend_code
    ? '\n\nIt is currently on machine ' + unit.current_vend_code + '. Its binding history is kept for card settlement, but it will disappear from the machine picker.'
    : ''
  const approval = confirm('Are you sure to delete terminal ' + unit.terminal_id + '?' + warning);
  if (!approval) {
      return;
  }
  router.delete('/card-terminal-units/' + unit.id, {
    onSuccess: () => {
      toast.success("Card terminal deleted successfully", { timeout: 3000 })
    },
    onError: () => {
      toast.error("Failed to delete card terminal", { timeout: 3000 })
    }
  })
}

function onEditClicked(unitValue) {
  type.value = 'update'
  cardTerminalUnit.value = unitValue
  showModal.value = true
}

function onSearchFilterUpdated() {
  router.get('/card-terminal-units', {
      ...filters.value,
      card_terminal_id: filters.value.card_terminal_id?.id ?? 'all',
      numberPerPage: filters.value.numberPerPage?.id ?? filters.value.numberPerPage,
  }, {
      preserveState: true,
      replace: true,
  })
}

function resetFilters() {
  router.get('/card-terminal-units')
}

// Same rows the grid is showing — every filter travels with the request, and
// the server re-applies them so the file matches what is on screen.
// axios + fileDownload are globals from resources/js/bootstrap.js.
function onExportExcelClicked() {
  loading.value = true
  axios({
    method: 'get',
    url: '/card-terminal-units/excel',
    params: {
      ...filters.value,
      card_terminal_id: filters.value.card_terminal_id?.id ?? 'all',
      numberPerPage: undefined,
    },
    responseType: 'blob',
  }).then(response => {
    fileDownload(response.data, 'CardTerminals_' + moment().format('YYYYMMDD_HHmmss') + '.xlsx')
  }).catch(() => {
    toast.error('Failed to export card terminals', { timeout: 3000 })
  }).finally(() => {
    loading.value = false
  })
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
