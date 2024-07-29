<template>

  <Head title="Machine Model" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Data Management (Serial Number)
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
        <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
          <SearchInput placeholderStr="Serial Number" v-model="filters.code">
            Serial Number
          </SearchInput>
          <SearchInput placeholderStr="4 to 5 Digits Number" v-model="filters.vend_codes" @keyup.enter="onSearchFilterUpdated()">
              Machine ID
              <span class="text-[9px]">
                  ("," for multiple)
              </span>
          </SearchInput>
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
                  <span class="font-medium">{{ vendSerialNumbers.meta.from ?? 0 }}</span>
                  <span>to</span>
                  <span class="font-medium">{{ vendSerialNumbers.meta.to ?? 0 }}</span>
                  <span>of</span>
                  <span class="font-medium">{{ vendSerialNumbers.meta.total }}</span>
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
                      Serial Number
                    </TableHeadSort>
                    <TableHead>
                      Remarks
                    </TableHead>
                    <TableHead>
                      Machine
                    </TableHead>
                    <TableHead>
                    </TableHead>
                  </tr>
                </thead>
                  <tbody class="bg-white">
                    <tr v-for="(vendSerialNumber, vendSerialNumberIndex) in vendSerialNumbers.data" :vendSerialNumber="vendSerialNumber.id" class="divide-x divide-gray-200">
                      <TableData :currentIndex="vendSerialNumberIndex" :totalLength="vendSerialNumbers.length" inputClass="text-center">
                        {{ vendSerialNumbers.meta.from + vendSerialNumberIndex }}
                      </TableData>
                      <TableData :currentIndex="vendSerialNumberIndex" :totalLength="vendSerialNumbers.length" inputClass="text-left">
                        {{ vendSerialNumber.code }}
                      </TableData>
                      <TableData :currentIndex="vendSerialNumberIndex" :totalLength="vendSerialNumbers.length" inputClass="text-left">
                        {{ vendSerialNumber.desc }}
                      </TableData>
                      <TableData :currentIndex="vendSerialNumberIndex" :totalLength="vendSerialNumbers.length" inputClass="text-center">
                        {{ vendSerialNumber.vend ? vendSerialNumber.vend.code : '' }}
                      </TableData>
                      <TableData :currentIndex="vendSerialNumberIndex" :totalLength="vendSerialNumbers.length" inputClass="text-center">
                        <div class="flex justify-center space-x-1">
                          <Button
                            type="button" class="bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
                            @click="onEditClicked(vendSerialNumber)"
                          >
                            <PencilSquareIcon class="w-4 h-4"></PencilSquareIcon>
                            <span>
                                Edit
                            </span>
                          </Button>
                          <Button
                            type="button"
                            class="bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex-col space-y-1 w-fit"
                            :class="[vendSerialNumber.vends && vendSerialNumber.vends.length > 0 ? 'opacity-50 cursor-not-allowed' : '']"
                            @click="onDeleteClicked(vendSerialNumber)"
                            :disabled="vendSerialNumber.vends && vendSerialNumber.vends.length > 0"
                          >
                            <span class="flex space-x-1 items-center">
                              <TrashIcon class="w-4 h-4"></TrashIcon>
                              <span>
                                  Delete
                              </span>
                            </span>
                            <span v-if="vendSerialNumber.vends && vendSerialNumber.vends.length > 0">
                              (Binded)
                            </span>
                          </Button>
                        </div>
                      </TableData>
                      </tr>
                <tr v-if="!vendSerialNumbers.data.length">
                  <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                      No Results Found
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator v-if="vendSerialNumbers.data.length" :links="vendSerialNumbers.links" :meta="vendSerialNumbers.meta"></Paginator>
          </div>
      </div>
    </div>
  </div>
  <Form
      v-if="showModal"
      :vendSerialNumber="vendSerialNumber"
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
import Form from '@/Pages/VendSerialNumber/Form.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { BackspaceIcon, MagnifyingGlassIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ref, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
  vendSerialNumbers: Object,
})

const filters = ref({
  code: '',
  vend_codes: '',
  sortKey: '',
  sortBy: true,
  numberPerPage: 100,
})
const showModal = ref(false)
const vendSerialNumber = ref()
const type = ref('')
const numberPerPageOptions = ref([])

onMounted(() => {
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
  vendSerialNumber.value = null
  showModal.value = true
}

function onDeleteClicked(vendSerialNumber) {
  const approval = confirm('Are you sure to delete ' + vendSerialNumber.code + '?');
  if (!approval) {
      return;
  }
  router.delete('/vend-serial-numbers/' + vendSerialNumber.id)
}

function onEditClicked(model) {
  type.value = 'update'
  vendSerialNumber.value = model
  showModal.value = true
}

function onSearchFilterUpdated() {
  router.get('/vend-serial-numbers', {
      ...filters.value,
      numberPerPage: filters.value.numberPerPage.id,
  }, {
      preserveState: true,
      replace: true,
  })
}

function resetFilters() {
  router.get('/vend-serial-numbers')
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