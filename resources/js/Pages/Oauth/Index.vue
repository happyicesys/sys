<template>

  <Head title="Oauth & API" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Oauth & API
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
                    <TableHeadSort modelName="name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('name')">
                      Name
                    </TableHeadSort>
                    <TableHead>
                      Type
                    </TableHead>
                    <TableHead>
                    </TableHead>
                  </tr>
                </thead>
                  <tbody class="bg-white">
                    <tr v-for="(client, clientIndex) in clients" :key="client.id" class="divide-x divide-gray-200">
                      <TableData :currentIndex="clientIndex" :totalLength="clients.length" inputClass="text-center">
                        {{ clientIndex + 1 }}
                      </TableData>
                      <TableData :currentIndex="clientIndex" :totalLength="clients.length" inputClass="text-left">
                        {{ client.name }}
                      </TableData>
                      <TableData :currentIndex="clientIndex" :totalLength="clients.length" inputClass="text-left">
                        <span >

                        </span>
                      </TableData>
                      <TableData :currentIndex="clientIndex" :totalLength="clients.length" inputClass="text-center">
                        <div class="flex justify-center space-x-1">
                          <Button
                            type="button" class="bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
                            @click="onEditClicked(client)"
                          >
                            <PencilSquareIcon class="w-4 h-4"></PencilSquareIcon>
                            <span>
                                Edit
                            </span>
                          </Button>
                          <Button
                            type="button" class="bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1"
                            @click="onDeleteClicked(client)"
                          >
                            <TrashIcon class="w-4 h-4"></TrashIcon>
                            <span>
                                Delete
                            </span>
                          </Button>
                        </div>
                      </TableData>
                      </tr>
                <tr v-if="!clients.length">
                  <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                      No Results Found
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
      </div>
    </div>
  </div>
  <!-- <Form
      v-if="showModal"
      :client="client"
      :type="type"
      :showModal="showModal"
      @modalClose="onModalClose"
  >
  </Form> -->
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import Form from '@/Pages/Telco/Form.vue';
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
  clients: Object,
  // personal_access_tokens: Object,
})

const filters = ref({
  name: '',
  sortKey: '',
  sortBy: true,
  numberPerPage: 100,
})
const showModal = ref(false)
const client = ref()
const type = ref('')

onMounted(() => {
  console.log(JSON.parse(JSON.stringify(props.clients)))
  // console.log(JSON.parse(JSON.stringify(props.personal_access_tokens)))
})

function onCreateClicked() {
  type.value = 'create'
  client.value = null
  showModal.value = true
}

function onDeleteClicked(client) {
  const approval = confirm('Are you sure to delete ' + client.name + '?');
  if (!approval) {
      return;
  }
  router.delete('/clients/' + client.id)
}

function onEditClicked(telcoValue) {
  type.value = 'update'
  client.value = telcoValue
  showModal.value = true
}

function onSearchFilterUpdated() {
  router.get('/clients', {
      ...filters.value,
      numberPerPage: filters.value.numberPerPage.id,
  }, {
      preserveState: true,
      replace: true,
  })
}

function resetFilters() {
  router.get('/clients')
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