<template>

  <Head title="Delivery Platform IDs" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Delivery Platform IDs
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
        <div class="flex justify-end mb-4">
          <Link href="/delivery-platform-ref-numbers/create" class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
            <PlusIcon class="h-4 w-4" aria-hidden="true"/>
            <span>
              Create
            </span>
          </Link>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
              Operator
            </label>
            <MultiSelect
              v-model="filters.operators"
              :options="operatorOptions"
              trackBy="id"
              valueProp="id"
              label="full_name"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
              mode="tags"
            >
            </MultiSelect>
          </div>
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
              Vend Prefix
            </label>
            <MultiSelect
              v-model="filters.vendPrefixes"
              :options="vendPrefixOptions.data"
              trackBy="id"
              valueProp="id"
              label="name"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
              mode="tags"
            >
            </MultiSelect>
          </div>
          <SearchInput placeholderStr="Platform ID" v-model="filters.ref_number">
            Platform ID
          </SearchInput>
          <SearchInput placeholderStr="Machine ID" v-model="filters.current_vend_code">
            Current Machine ID
          </SearchInput>
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
              Status
            </label>
            <MultiSelect
              v-model="filters.is_active"
              :options="booleanOptions"
              trackBy="id"
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
            </div>
          </div>
          <div class="flex flex-col space-y-2">
              <p class="text-sm text-gray-700 leading-5 flex space-x-1">
                  <span>Showing</span>
                  <span class="font-medium">{{ deliveryPlatformRefNumbers.meta.from ?? 0 }}</span>
                  <span>to</span>
                  <span class="font-medium">{{ deliveryPlatformRefNumbers.meta.to ?? 0 }}</span>
                  <span>of</span>
                  <span class="font-medium">{{ deliveryPlatformRefNumbers.meta.total }}</span>
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
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8 px-3">
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll">
            <table class="min-w-full border-separate" style="border-spacing: 0">
                <thead class="bg-gray-100">
                  <tr class="divide-x divide-gray-200">
                    <TableHead>
                      #
                    </TableHead>
                    <TableHeadSort modelName="ref_number" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('ref_number')">
                      Platform ID
                    </TableHeadSort>
                    <TableHead>
                      Binding Count
                    </TableHead>
                    <TableHeadSort modelName="current_vend_code" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('current_vend_code')">
                      Current Machine ID
                    </TableHeadSort>
                    <TableHead>
                      Vend Prefix
                    </TableHead>
                    <TableHead>
                      Operator
                    </TableHead>
                    <TableHeadSort modelName="status" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('status')">
                      Status
                    </TableHeadSort>
                    <TableHeadSort modelName="created_at" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('created_at')">
                      Created At
                    </TableHeadSort>
                    <TableHead>
                      Remarks
                    </TableHead>
                  </tr>
                </thead>
                <tbody class="bg-white">
                  <tr v-for="(refNumber, idx) in deliveryPlatformRefNumbers.data" :key="refNumber.id" class="divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100">
                    <TableData :currentIndex="idx" :totalLength="deliveryPlatformRefNumbers.length" inputClass="text-center">
                      {{ deliveryPlatformRefNumbers.meta.from + idx }}
                    </TableData>
                    <TableData :currentIndex="idx" :totalLength="deliveryPlatformRefNumbers.length" inputClass="text-left">
                      <Link :href="`/delivery-platform-ref-numbers/${refNumber.id}/edit`" class="text-blue-600 hover:text-blue-800">
                        {{ refNumber.ref_number }}
                      </Link>
                    </TableData>
                    <TableData :currentIndex="idx" :totalLength="deliveryPlatformRefNumbers.length" inputClass="text-center">
                      <div
                        class="inline-flex rounded-full px-1.5 py-0.5 text-xs border w-fit bg-blue-100 text-blue-800 border-blue-300"
                      >
                        <div class="flex space-x-1">
                          <span class="font-semibold grow-0">
                            {{ refNumber.binding_count }}
                          </span>
                        </div>
                      </div>
                    </TableData>
                    <TableData :currentIndex="idx" :totalLength="deliveryPlatformRefNumbers.length" inputClass="text-center">
                      {{ refNumber.current_vend_code ?? '' }}
                    </TableData>
                    <TableData :currentIndex="idx" :totalLength="deliveryPlatformRefNumbers.length" inputClass="text-center">
                      {{ refNumber.vend_prefix ?? '' }}
                    </TableData>
                    <TableData :currentIndex="idx" :totalLength="deliveryPlatformRefNumbers.length" inputClass="text-center">
                      <span v-if="refNumber.operator">{{ refNumber.operator.code }} - {{ refNumber.operator.name }}</span>
                    </TableData>
                    <TableData :currentIndex="idx" :totalLength="deliveryPlatformRefNumbers.length" inputClass="text-center">
                      <span v-if="refNumber.binding_status === 1" class="inline-flex items-center rounded-md bg-green-300 px-1.5 py-0.5 text-xs font-medium text-green-800 ring-1 ring-inset ring-indigo-700/10">
                        {{ refNumber.binding_status_label }}
                      </span>
                      <span v-else class="inline-flex items-center rounded-md bg-red-200 px-1.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-indigo-700/10">
                        {{ refNumber.binding_status_label }}
                      </span>
                    </TableData>
                    <TableData :currentIndex="idx" :totalLength="deliveryPlatformRefNumbers.length" inputClass="text-center">
                      {{ refNumber.created_at }}
                    </TableData>
                    <TableData :currentIndex="idx" :totalLength="deliveryPlatformRefNumbers.length" inputClass="text-left">
                      {{ refNumber.remarks }}
                    </TableData>
                  </tr>
                </tbody>
            </table>
          </div>
          <div class="flex justify-end py-3">
            <nav class="px-1 py-2" aria-label="Pagination" v-if="deliveryPlatformRefNumbers.meta.links.length > 3">
              <ol class="inline-flex items-center space-x-1">
                <li v-for="(link, linkIndex) in deliveryPlatformRefNumbers.meta.links" :key="linkIndex">
                  <Link as="button" :href="link.url ? link.url : ''" class="relative inline-flex items-center rounded px-2.5 py-1 border text-sm font-medium hover:bg-gray-100" :class="[link.active ? 'z-10 border-indigo-500 bg-indigo-50 text-indigo-600' : 'border-gray-300 bg-white text-gray-500']">
                    <span v-html="link.label"></span>
                  </Link>
                </li>
              </ol>
            </nav>
          </div>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import SearchInput from '@/Components/SearchInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { BackspaceIcon, MagnifyingGlassIcon, PlusIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
  deliveryPlatformRefNumbers: Object,
  operatorOptions: Object,
  vendPrefixOptions: Object,
})

const filters = ref({
  ref_number: '',
  current_vend_code: '',
  operators: [],
  vendPrefixes: [],
  sortKey: 'created_at',
  sortBy: false,
  numberPerPage: 100,
  is_active: null,
})
const operatorOptions = ref([])
const numberPerPageOptions = ref([])
const booleanOptions = ref([])

onMounted(() => {
  numberPerPageOptions.value = [
    { id: 100, value: 100 },
    { id: 200, value: 200 },
    { id: 500, value: 500 },
    { id: 'All', value: 'All' },
  ]
  booleanOptions.value = [
    { id: 'all', value: 'All' },
    { id: 'true', value: 'Active' },
    { id: 'false', value: 'Inactive' },
  ]
  operatorOptions.value = [
    {id: 'all', full_name: 'All'},
    ...props.operatorOptions.data.map((data) => {return {id: data.id, code: data.code, full_name: data.full_name}})
  ]
  filters.value.numberPerPage = numberPerPageOptions.value[0]
  filters.value.operators = [operatorOptions.value[0]].filter(operator => operator !== undefined)
  // default to Active
  filters.value.is_active = booleanOptions.value[1]
})

function onSearchFilterUpdated() {
  router.get('/delivery-platform-ref-numbers', {
      ...filters.value,
      operators: filters.value.operators.filter(operator => operator).map((operator) => { return operator.id }),
      vend_prefixes: filters.value.vendPrefixes.map((prefix) => { return prefix.id }),
      is_active: filters.value.is_active?.id ?? 'all',
      numberPerPage: filters.value.numberPerPage.id,
  }, {
      preserveState: true,
      replace: true,
  })
}

function resetFilters() {
  router.get('/delivery-platform-ref-numbers')
}

function sortTable(sortKey) {
  filters.value.sortBy = !filters.value.sortBy
  filters.value.sortKey = sortKey
  onSearchFilterUpdated()
}
</script>

<style scoped>
</style>
