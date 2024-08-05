<template>

  <Head title="Ops Job" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Jobs
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
            <div class="col-span-5 md:col-span-1">
                <SearchInput placeholderStr="Job ID" v-model="filters.code" @keyup.enter="onSearchFilterUpdated()">
                  Job ID
                </SearchInput>
            </div>
            <div class="col-span-5 md:col-span-1">
                <DatePicker
                    v-model="filters.date_from"
                >
                  From
                </DatePicker>
            </div>
            <div class="col-span-5 md:col-span-1">
                <DatePicker
                    v-model="filters.date_to"
                    :minDate="filters.date_from"
                >
                  To
                </DatePicker>
            </div>
            <div class="col-span-5 md:col-span-1">
                <label for="text" class="block text-sm font-medium text-gray-700">
                   Delivery By
                </label>
                <MultiSelect
                    v-model="filters.delivered_by"
                    :options="userOptions"
                    valueProp="id"
                    label="value"
                    placeholder="Select"
                    open-direction="bottom"
                    class="mt-1"
                >
                </MultiSelect>
            </div>
            <div class="col-span-5 md:col-span-1">
                <label for="text" class="block text-sm font-medium text-gray-700">
                   Created By
                </label>
                <MultiSelect
                    v-model="filters.created_by"
                    :options="userOptions"
                    valueProp="id"
                    label="value"
                    placeholder="Select"
                    open-direction="bottom"
                    class="mt-1"
                >
                </MultiSelect>
            </div>
        </div>


        <div class="flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5">
          <div class="mt-3">
            <div class="flex space-x-1">
              <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
              @click="onSearchFilterUpdated()"
              >
                <MagnifyingGlassIcon class="h-4 w-4" aria-hidden="true"/>
                <span>
                  Search
                </span>
              </Button>
              <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
              @click="resetFilters()"
              >
                <BackspaceIcon class="h-4 w-4" aria-hidden="true"/>
                <span>
                  Reset
                </span>
              </Button>
            </div>
          </div>
          <div class="flex flex-col space-y-2">
              <p class="text-sm text-gray-700 leading-5 flex space-x-1">
                  <span>Showing</span>
                  <span class="font-medium">{{ opsJobs.meta.from ?? 0 }}</span>
                  <span>to</span>
                  <span class="font-medium">{{ opsJobs.meta.to ?? 0 }}</span>
                  <span>of</span>
                  <span class="font-medium">{{ opsJobs.meta.total }}</span>
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
                      Code
                    </TableHeadSort>
                    <TableHeadSort modelName="date" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('date')">
                      Date
                    </TableHeadSort>
                    <TableHead>
                      Delivery By
                    </TableHead>
                    <TableHead>
                      Machine Count
                    </TableHead>
                    <TableHead>
                      Created By
                    </TableHead>
                    <TableHead>
                      Created At
                    </TableHead>
                    <!-- <TableHead>
                      Count
                    </TableHead> -->
                    <TableHead>
                    </TableHead>
                  </tr>
                </thead>
                  <tbody class="bg-white">
                    <tr v-for="(opsJob, opsJobIndex) in opsJobs.data" :key="opsJob.id" class="divide-x divide-gray-200">
                      <TableData :currentIndex="opsJobIndex" :totalLength="opsJobs.length" inputClass="text-center">
                        {{ opsJobs.meta.from + opsJobIndex }}
                      </TableData>
                      <TableData :currentIndex="opsJobIndex" :totalLength="opsJobs.length" inputClass="text-center">
                        {{ opsJob.code }}
                      </TableData>
                      <TableData :currentIndex="opsJobIndex" :totalLength="opsJobs.length" inputClass="text-center">
                        <div class="flex flex-col space-y-2">
                          <span>
                            {{ opsJob.date }}
                          </span>
                          <span>
                            <div
                              class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full text-gray-900"
                              :class="[(opsJob.date_diff_count < 1 &&  opsJob.date_diff_count > 0) ? 'bg-green-200' : ((opsJob.date_diff_count > -1 && opsJob.date_diff_count < 0) ? 'bg-yellow-200' : '') ]"
                              v-if="opsJob.date_diff_human"
                            >
                              <span>
                                {{ opsJob.date_diff_human }}
                              </span>
                            </div>
                          </span>
                        </div>
                      </TableData>
                      <TableData :currentIndex="opsJobIndex" :totalLength="opsJobs.length" inputClass="text-center">
                        {{ opsJob.deliveredBy ? opsJob.deliveredBy.name : '' }}
                      </TableData>
                      <TableData :currentIndex="opsJobIndex" :totalLength="opsJobs.length" inputClass="text-center">
                        {{ opsJob.ops_job_items_count }}
                      </TableData>
                      <TableData :currentIndex="opsJobIndex" :totalLength="opsJobs.length" inputClass="text-center">
                        {{ opsJob.createdBy ? opsJob.createdBy.name : '' }}
                      </TableData>
                      <TableData :currentIndex="opsJobIndex" :totalLength="opsJobs.length" inputClass="text-center">
                        {{ opsJob.created_at }}
                      </TableData>
                      <TableData :currentIndex="opsJobIndex" :totalLength="opsJobs.length" inputClass="">
                        <div class="flex flex-col space-y-1 justify-items-center">
                          <Link :href="'/ops-jobs/' + opsJob.id + '/edit'">
                            <Button
                              type="button" class="bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1 w-fit"
                            >
                              <PencilSquareIcon class="w-4 h-4"></PencilSquareIcon>
                              <span>
                                  Edit
                              </span>
                            </Button>
                          </Link>

                          <Button
                            type="button"
                            class="bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex-col space-y-1 w-fit"
                            :class="[opsJob.vendPrefixes && opsJob.vendPrefixes.length > 0 ? 'opacity-50 cursor-not-allowed' : '']"
                            @click="onDeleteClicked(opsJob)"
                            :disabled="opsJob.vendPrefixes && opsJob.vendPrefixes.length > 0"
                          >
                            <span class="flex space-x-1 items-center">
                              <TrashIcon class="w-4 h-4"></TrashIcon>
                              <span>
                                  Delete
                              </span>
                            </span>
                            <span v-if="opsJob.vendPrefixes && opsJob.vendPrefixes.length > 0">
                              (Binded)
                            </span>
                          </Button>
                        </div>
                      </TableData>
                      </tr>
                <tr v-if="!opsJobs.data.length">
                  <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                      No Results Found
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator v-if="opsJobs.data.length" :links="opsJobs.links" :meta="opsJobs.meta"></Paginator>
          </div>
      </div>
    </div>
  </div>

  <Form
      v-if="showModal"
      :operatorOptions="operatorOptions"
      :opsJob="opsJob"
      :type="type"
      :showModal="showModal"
      :userOptions="userOptions"
      @modalClose="onModalClose"
  >
</Form>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import Form from '@/Pages/OpsJob/Form.vue';
import DatePicker from '@/Components/DatePicker.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { BackspaceIcon, MagnifyingGlassIcon, PhotoIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ref, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import moment from 'moment';

const props = defineProps({
  operatorOptions: [Array, Object],
  opsJobs: Object,
  userOptions: [Array, Object],
})

const filters = ref({
  code: '',
  date_from: moment().format('YYYY-MM-DD'),
  date_to: moment().add(1, 'week').format('YYYY-MM-DD'),
  delivered_by: '',
  created_by: '',
  sortKey: '',
  sortBy: true,
  numberPerPage: 100,
})
const model = ref()
const showModal = ref(false)
const opsJob = ref()
const type = ref('')
const numberPerPageOptions = ref([])
const userOptions = ref([])

onMounted(() => {

  numberPerPageOptions.value = [
    { id: 100, value: 100 },
    { id: 200, value: 200 },
    { id: 500, value: 500 },
    { id: 'All', value: 'All' },
  ]
  userOptions.value = [
    ...props.userOptions.data.map((data) => {return {id: data.id, value: data.name}})
  ]
  filters.value.numberPerPage = numberPerPageOptions.value[0]
})

function onCreateClicked() {
  type.value = 'create'
  opsJob.value = null
  showModal.value = true
}

function onDeleteClicked(opsJob) {
  const approval = confirm('Are you sure to delete ' + opsJob.code + '?');
  if (!approval) {
      return;
  }
  router.delete('/ops-jobs/' + opsJob.id)
}

function onSearchFilterUpdated() {
  router.get('/ops-jobs', {
      ...filters.value,
      created_by: filters.value.created_by.id,
      delivered_by: filters.value.delivered_by.id,
      numberPerPage: filters.value.numberPerPage.id,
  }, {
      preserveState: true,
      replace: true,
  })
}

function resetFilters() {
  router.get('/ops-jobs')
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