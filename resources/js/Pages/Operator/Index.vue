<template>

  <Head title="Payment Methods" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Operators
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
        <div class="flex justify-end">
          <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
          @click="onCreateClicked()"
          v-if="!operatorRole"
          >
            <PlusIcon class="h-4 w-4" aria-hidden="true"/>
            <span>
              Create
            </span>
          </Button>
        </div>
          <!-- <div class="flex flex-col md:flex-row md:space-x-3 space-y-1 md:space-y-0"> -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
          <SearchInput placeholderStr="Name" v-model="filters.name">
            Name
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
                  <span class="font-medium">{{ operators.meta.from ?? 0 }}</span>
                  <span>to</span>
                  <span class="font-medium">{{ operators.meta.to ?? 0 }}</span>
                  <span>of</span>
                  <span class="font-medium">{{ operators.meta.total }}</span>
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
                    <TableHead>
                      Code
                    </TableHead>
                    <TableHeadSort modelName="name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('name')">
                      Name
                    </TableHeadSort>
                    <TableHead>
                      Country
                    </TableHead>
                    <TableHead>
                      Timezone
                    </TableHead>
                    <TableHead>
                    </TableHead>
                  </tr>
                </thead>
                  <tbody class="bg-white">
                    <tr v-for="(operator, operatorIndex) in operators.data" :key="operator.id" class="divide-x divide-gray-200">
                      <TableData :currentIndex="operatorIndex" :totalLength="operators.length" inputClass="text-center">
                        {{ operators.meta.from + operatorIndex }}
                      </TableData>
                      <TableData :currentIndex="operatorIndex" :totalLength="operators.length" inputClass="text-center">
                        {{ operator.code }}
                      </TableData>
                      <TableData :currentIndex="operatorIndex" :totalLength="operators.length" inputClass="text-left">
                        {{ operator.name }}
                      </TableData>
                      <TableData :currentIndex="operatorIndex" :totalLength="operators.length" inputClass="text-center">
                        {{ operator.country.name }}
                      </TableData>
                      <TableData :currentIndex="operatorIndex" :totalLength="operators.length" inputClass="text-center">
                        {{ operator.timezone.name }}
                      </TableData>
                      <TableData :currentIndex="operatorIndex" :totalLength="operators.length" inputClass="text-center">
                        <div class="flex justify-center space-x-1" v-if="!operatorRole">
                          <Button
                            type="button" class="bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
                            @click="onEditClicked(operator)"
                          >
                            <PencilSquareIcon class="w-4 h-4"></PencilSquareIcon>
                            <span>
                                Edit
                            </span>
                          </Button>
                          <Button
                            type="button" class="bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1"
                            @click="onDeleteClicked(operator)"
                          >
                            <TrashIcon class="w-4 h-4"></TrashIcon>
                            <span>
                                Delete
                            </span>
                          </Button>
                        </div>
                      </TableData>
                      </tr>
                <tr v-if="!operators.data.length">
                  <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                      No Results Found
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator v-if="operators.data.length" :links="operators.links" :meta="operators.meta"></Paginator>
          </div>
      </div>
    </div>
  </div>
  <Form
      v-if="showModal"
      :countries="countries"
      :operator="operator"
      :timezones="timezones"
      :type="type"
      :showModal="showModal"
      :unbindedVends="unbindedVends"
      @modalClose="onModalClose"
      @refreshData="onRefreshData(operatorId)"
  >
  </Form>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import Form from '@/Pages/Operator/Form.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { BackspaceIcon, MagnifyingGlassIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { Head, usePage } from '@inertiajs/inertia-vue3';
import { ref, onMounted } from 'vue';
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  countries: Object,
  operators: Object,
  timezones: [Array, Object],
  unbindedVends: Object,
})

const filters = ref({
  name: '',
  sortKey: '',
  sortBy: true,
  numberPerPage: 100,
})
const showModal = ref(false)
const operator = ref()
const type = ref('')
const numberPerPageOptions = ref([])
const operatorRole = usePage().props.value.auth.operatorRole

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
  operator.value = null
  showModal.value = true
}

function onDeleteClicked(operator) {
  const approval = confirm('Are you sure to delete ' + operator.name + '?');
  if (!approval) {
      return;
  }
  Inertia.delete('/operators/' + operator.id)
}

function onEditClicked(operatorValue) {
  type.value = 'update'
  operator.value = operatorValue
  Inertia.visit(
      route('operators', {
          operator_id: operatorValue.id
      }),{
          only: ['unbindedVends'],
          preserveState: true,
      }
  );
  showModal.value = true


}

function onSearchFilterUpdated() {
  Inertia.get('/operators', {
      ...filters.value,
      numberPerPage: filters.value.numberPerPage.id,
  }, {
      preserveState: true,
      replace: true,
  })
}

function resetFilters() {
  Inertia.get('/operators')
}

function sortTable(sortKey) {
  filters.value.sortKey = sortKey
  filters.value.sortBy = !filters.value.sortBy
  onSearchFilterUpdated()
}

function onModalClose() {
  showModal.value = false
}

function onRefreshData(operatorId) {
  // Inertia.reload({ only: ['operators', 'unbindedVends'] })
  Inertia.visit(
      route('operators', {
          operator_id: operatorId
      }),{
          only: ['unbindedVends', 'operators'],
          // preserveState: true,
      }
  );
}
</script>