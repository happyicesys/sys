<template>

  <Head title="Product" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Product (List)
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
        <div class="flex justify-end mb-4">
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
          <SearchInput placeholderStr="Code" v-model="filters.code">
            Code
          </SearchInput>
          <SearchInput placeholderStr="Name" v-model="filters.name">
            Name
          </SearchInput>
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
              Is Inventory?
            </label>
            <MultiSelect
              v-model="filters.is_inventory"
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
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
              Is Active?
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
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
              Is Comm or SF?
            </label>
            <MultiSelect
              v-model="filters.is_comm_or_sf"
              :options="commSfOptions"
              trackBy="id"
              valueProp="id"
              label="value"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
              @selected="onIsCommOrSfSelected"
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
                  <span class="font-medium">{{ products.meta.from ?? 0 }}</span>
                  <span>to</span>
                  <span class="font-medium">{{ products.meta.to ?? 0 }}</span>
                  <span>of</span>
                  <span class="font-medium">{{ products.meta.total }}</span>
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
                    <TableHeadSort modelName="name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('name')">
                      Name
                    </TableHeadSort>
                    <TableHead>
                      Thumbnail
                    </TableHead>
                    <TableHead>
                      Category
                    </TableHead>
                    <TableHead>
                      Group
                    </TableHead>
                    <TableHead>
                      Is Inventory
                    </TableHead>
                    <TableHead>
                      Is Active
                    </TableHead>
                    <TableHead>
                    </TableHead>
                  </tr>
                </thead>
                  <tbody class="bg-white">
                    <tr v-for="(product, productIndex) in products.data" :key="product.id" class="divide-x divide-gray-200">
                      <TableData :currentIndex="productIndex" :totalLength="products.length" inputClass="text-center">
                        {{ products.meta.from + productIndex }}
                      </TableData>
                      <TableData :currentIndex="productIndex" :totalLength="products.length" inputClass="text-center">
                        {{ product.code }}
                      </TableData>
                      <TableData :currentIndex="productIndex" :totalLength="products.length" inputClass="text-left">
                        {{ product.name }}
                      </TableData>
                      <TableData :currentIndex="productIndex" :totalLength="products.length" inputClass="text-center">
                        <div class="flex justify-center">
                          <img class="h-24 w-24 md:h-20 md:w-20 rounded-full" :src="product.thumbnail.full_url" alt="" v-if="product.thumbnail"/>
                        </div>
                      </TableData>
                      <TableData :currentIndex="productIndex" :totalLength="products.length" inputClass="text-center">
                        {{ product.category_id ? product.category_id.name : null }}
                      </TableData>
                      <TableData :currentIndex="productIndex" :totalLength="products.length" inputClass="text-center">
                        {{ product.category_group_id ? product.category_group_id.name : null }}
                      </TableData>
                      <TableData :currentIndex="productIndex" :totalLength="products.length" inputClass="text-center">
                        {{ product.isInventory }}
                      </TableData>
                      <TableData :currentIndex="productIndex" :totalLength="products.length" inputClass="text-center">
                        {{ product.isActive }}
                      </TableData>
                      <TableData :currentIndex="productIndex" :totalLength="products.length" inputClass="text-center">
                        <div class="flex justify-center space-x-1">
                          <Button
                            type="button" class="bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
                            @click="onEditClicked(product)"
                          >
                            <PencilSquareIcon class="w-4 h-4"></PencilSquareIcon>
                            <span>
                                Edit
                            </span>
                          </Button>
                        </div>
                      </TableData>
                      </tr>
                <tr v-if="!products.data.length">
                  <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                      No Results Found
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator v-if="products.data.length" :links="products.links" :meta="products.meta"></Paginator>
          </div>
      </div>
    </div>
  </div>
  <Form
      v-if="showModal"
      :product="product"
      :categories = "categories"
      :categoryGroups = "categoryGroups"
      :uoms = "uoms"
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
import Form from '@/Pages/Product/Form.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { BackspaceIcon, MagnifyingGlassIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { Head } from '@inertiajs/inertia-vue3';
import { ref, onMounted, watch } from 'vue';
import { Inertia } from '@inertiajs/inertia'

const props = defineProps({
  categories: Object,
  categoryGroups: Object,
  products: Object,
  uoms: Object,
})

const filters = ref({
  code: '',
  name: '',
  is_active: '',
  is_comm_or_sf: '',
  is_inventory: '',
  sortKey: '',
  sortBy: true,
  numberPerPage: 100,
})
const booleanOptions = ref([])
const commSfOptions = ref([])
const showModal = ref(false)
const product = ref()
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
  booleanOptions.value = [
    {id: 1, value: 'Yes'},
    {id: 0, value: 'No'},
  ]
  filters.value.is_active = booleanOptions.value[0]
  filters.value.is_inventory = booleanOptions.value[0]
  commSfOptions.value = [
    {id: '', value: 'All'},
    {id: 'comm', value: 'Comm Only'},
    {id: 'sf', value: 'SF Only'},
    {id: 'both', value: 'Both Comm & SF'},
  ]
  filters.value.is_comm_or_sf = commSfOptions.value[0]
  // console.log(JSON.parse(JSON.stringify(props.uoms)))
})

function onCreateClicked() {
  type.value = 'create'
  product.value = null
  showModal.value = true
}

function onDeleteClicked(product) {
  const approval = confirm('Are you sure to delete ' + product.name + '?');
  if (!approval) {
      return;
  }
  Inertia.delete('/products/' + product.id)
}

function onEditClicked(productValue) {
  type.value = 'update'
  product.value = productValue
  showModal.value = true
}

function onIsCommOrSfSelected() {
  // filters.value.is_inventory = booleanOptions.value[1]
  // console.log('ddudesss')
}

function onSearchFilterUpdated() {
  Inertia.get('/products', {
      ...filters.value,
      numberPerPage: filters.value.numberPerPage.id,
      is_active: filters.value.is_active.id,
      is_inventory: filters.value.is_inventory.id,
      is_comm_or_sf: filters.value.is_comm_or_sf.id,
  }, {
      preserveState: true,
      replace: true,
  })
}

function resetFilters() {
  Inertia.get('/products')
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