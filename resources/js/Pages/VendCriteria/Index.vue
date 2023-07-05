<template>

  <Head title="Holidays" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Criteria
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">

      <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll">
            <table class="min-w-full border-separate" style="border-spacing: 0">
                <thead class="bg-gray-100">
                  <tr class="divide-x divide-gray-200">
                    <TableHead>
                      #
                    </TableHead>
                    <TableHeadSort modelName="name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('name')">
                      Name
                    </TableHeadSort>
                    <TableHead>
                      Value
                    </TableHead>
                    <TableHeadSort modelName="weightage" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('weightage')">
                      Weightage (%)
                    </TableHeadSort>
                    <TableHead>
                    </TableHead>
                  </tr>
                </thead>
                  <tbody class="bg-white">
                    <tr v-for="(vendCriteria, vendCriteriaIndex) in vendCriterias.data" :key="vendCriteria.id" class="divide-x divide-gray-200">
                      <TableData :currentIndex="vendCriteriaIndex" :totalLength="vendCriterias.length" inputClass="text-center">
                        {{ vendCriterias.meta.from + vendCriteriaIndex }}
                      </TableData>
                      <TableData :currentIndex="vendCriteriaIndex" :totalLength="vendCriterias.length" inputClass="text-left">
                        {{ vendCriteria.name }}
                      </TableData>
                      <TableData :currentIndex="vendCriteriaIndex" :totalLength="vendCriterias.length" inputClass="text-left">
                        {{ vendCriteria.options_json[vendCriteria.value] }}
                        <span v-if="vendCriteria.value2">
                          {{ vendCriteria.value2 }}
                        </span>
                      </TableData>
                      <TableData :currentIndex="vendCriteriaIndex" :totalLength="vendCriterias.length" inputClass="text-center">
                        {{ vendCriteria.weightage }}
                      </TableData>
                      <TableData :currentIndex="vendCriteriaIndex" :totalLength="vendCriterias.length" inputClass="text-center">
                        <div class="flex justify-center space-x-1">
                          <Button
                            type="button" class="bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
                            @click="onEditClicked(vendCriteria)"
                          >
                            <PencilSquareIcon class="w-4 h-4"></PencilSquareIcon>
                            <span>
                                Edit
                            </span>
                          </Button>
                        </div>
                      </TableData>
                      </tr>
                <tr v-if="!vendCriterias.data.length">
                  <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                      No Results Found
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator v-if="vendCriterias.data.length" :links="vendCriterias.links" :meta="vendCriterias.meta"></Paginator>
          </div>
      </div>
    </div>
  </div>
  <Form
      v-if="showModal"
      :vendCriteria="vendCriteria"
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
import Form from '@/Pages/VendCriteria/Form.vue';
import Paginator from '@/Components/Paginator.vue';
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
  vendCriterias: Object,
  yearOptions: [Array, Object],
})

const filters = ref({
  name: '',
  value: '',
  sortKey: '',
  sortBy: true,
  numberPerPage: 100,
})
const showModal = ref(false)
const vendCriteria = ref()
const type = ref('')


function onEditClicked(vendCriteriaValue) {
  type.value = 'update'
  vendCriteria.value = vendCriteriaValue
  showModal.value = true
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