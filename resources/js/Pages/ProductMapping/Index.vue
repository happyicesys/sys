<template>

  <Head title="Product Mappings" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Product Mappings
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
        <div class="flex justify-end">
          <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
          @click="onCreateClicked()"
          v-if="permissions.includes('create product-mappings')"
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
          <SearchInput placeholderStr="Vend ID" v-model="filters.vend_code">
            Vend ID#
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
                  <span class="font-medium">{{ productMappings.meta.from ?? 0 }}</span>
                  <span>to</span>
                  <span class="font-medium">{{ productMappings.meta.to ?? 0 }}</span>
                  <span>of</span>
                  <span class="font-medium">{{ productMappings.meta.total }}</span>
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
                      Name
                    </TableHead>
                    <TableHead>
                      Channel - Product
                    </TableHead>
                    <TableHead>
                      Binded Vending Machines
                    </TableHead>
                    <TableHead>
                    </TableHead>
                  </tr>
                </thead>
                  <tbody class="bg-white">
                    <tr v-for="(productMapping, productMappingIndex) in productMappings.data" :key="productMapping.id" class="divide-x divide-gray-200">
                      <TableData :currentIndex="productMappingIndex" :totalLength="productMappings.length" inputClass="text-center">
                        {{ productMappings.meta.from + productMappingIndex }}
                      </TableData>
                      <TableData :currentIndex="productMappingIndex" :totalLength="productMappings.length" inputClass="text-left">
                        {{ productMapping.name }}
                      </TableData>
                      <TableData :currentIndex="productMappingIndex" :totalLength="productMappings.length" inputClass="text-left">
                        <ul class="divide-y divide-gray-200">
                          <li class="flex py-1 px-3 space-x-2" v-for="productMappingItem in productMapping.productMappingItemsJson">
                            <span class="text-blue-700 text-md pr-2">
                              {{ productMappingItem['channel_code'] }}
                            </span>
                            <span v-if="productMappingItem['product']['code']">
                              {{ productMappingItem['product']['code'] }}
                            </span>
                            <span>
                              - {{ productMappingItem['product']['name'] }}
                            </span>

                          </li>
                        </ul>
                      </TableData>
                      <TableData :currentIndex="productMappingIndex" :totalLength="productMappings.length" inputClass="text-left">
                        <ul class="divide-y divide-gray-200">
                          <li class="flex py-1 px-3 space-x-2" v-for="productMappingVend in productMapping.vendsJson">
                            <!-- <span class="text-blue-700 text-md pr-2">
                              {{ productMappingVend['code'] }}
                            </span> -->
                            <span v-if="productMappingVend['full_name']">
                              {{ productMappingVend['full_name'] }}
                            </span>
                          </li>
                        </ul>
                      </TableData>
                      <TableData :currentIndex="productMappingIndex" :totalLength="productMappings.length" inputClass="text-center">
                        <div class="flex justify-center flex-col space-y-1" v-if="permissions.includes('update product-mappings')">
                          <Button
                            type="button" class="bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
                            @click="onEditClicked(productMapping)"
                          >
                            <PencilSquareIcon class="w-4 h-4"></PencilSquareIcon>
                            <span>
                                Edit
                            </span>
                          </Button>
                          <Button
                            type="button" class="bg-blue-300 hover:bg-blue-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
                            @click="onVendFormEditClicked(productMapping)"
                          >
                            <LinkIcon class="w-4 h-4"></LinkIcon>
                            <span>
                                VM Binding
                            </span>
                          </Button>
                          <Button
                            type="button"
                            class="bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex-col space-y-1"
                            :class="[productMapping.vendsJson && productMapping.vendsJson.length > 0 ? 'opacity-50 cursor-not-allowed' : '']"
                            @click="onDeleteClicked(productMapping)"
                            :disabled="productMapping.vendsJson && productMapping.vendsJson.length > 0"
                          >
                            <span class="flex space-x-1 items-center">
                              <TrashIcon class="w-4 h-4"></TrashIcon>
                              <span>
                                  Delete
                              </span>
                            </span>
                            <span v-if="productMapping.vendsJson && productMapping.vendsJson.length > 0">
                              (Binded)
                            </span>
                          </Button>
                        </div>
                      </TableData>
                      </tr>
                <tr v-if="!productMappings.data.length">
                  <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                      No Results Found
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator v-if="productMappings.data.length" :links="productMappings.links" :meta="productMappings.meta"></Paginator>
          </div>
      </div>
    </div>
  </div>
  <Form
      v-if="showModal"
      :products="products"
      :productMapping="productMapping"
      :type="type"
      :showModal="showModal"
      @modalClose="onModalClose"
  >
  </Form>

  <VendForm
      v-if="showVendFormModal"
      :productMapping="productMapping"
      :type="type"
      :showModal="showVendFormModal"
      :unbindedVends="unbindedVends"
      @modalClose="onVendFormModalClose"
  >

  </VendForm>

  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import Form from '@/Pages/ProductMapping/Form.vue';
import VendForm from '@/Pages/ProductMapping/VendForm.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { BackspaceIcon, LinkIcon, MagnifyingGlassIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  products: Object,
  productMappings: Object,
  unbindedVends: Object,
})

const filters = ref({
  name: '',
  vend_code: '',
  sortKey: '',
  sortBy: true,
  numberPerPage: 50,
})
const showModal = ref(false)
const showVendFormModal = ref(false)
const productMapping = ref()
const type = ref('')
const numberPerPageOptions = ref([])
const roles = usePage().props.auth.roles
const permissions = usePage().props.auth.permissions

onMounted(() => {
  numberPerPageOptions.value = [
    { id: 50, value: 50 },
    { id: 100, value: 100 },
    { id: 200, value: 200 },
    { id: 500, value: 500 },
    { id: 'All', value: 'All' },
  ]
  filters.value.numberPerPage = numberPerPageOptions.value[0]
})

function onCreateClicked() {
  type.value = 'create'
  productMapping.value = null
  showModal.value = true
}

function onDeleteClicked(productMapping) {
  const approval = confirm('Are you sure to delete ' + productMapping.name + '?');
  if (!approval) {
      return;
  }
  router.delete('/product-mappings/' + productMapping.id)
}

function onEditClicked(productMappingValue) {
  type.value = 'update'
  productMapping.value = productMappingValue
  showModal.value = true
}

function onVendFormEditClicked(productMappingValue) {
  type.value = 'update'
  productMapping.value = productMappingValue
  router.visit(
      route('product-mappings', {
          id: productMappingValue.id
      }),{
          only: ['unbindedVends'],
          preserveState: true,
      }
  );
  showVendFormModal.value = true
}

function onSearchFilterUpdated() {
  router.get('/product-mappings', {
      ...filters.value,
      numberPerPage: filters.value.numberPerPage.id,
  }, {
      preserveState: true,
      replace: true,
  })
}

function resetFilters() {
  router.get('/product-mappings')
}

function sortTable(sortKey) {
  filters.value.sortKey = sortKey
  filters.value.sortBy = !filters.value.sortBy
  onSearchFilterUpdated()
}

function onModalClose() {
  showModal.value = false
}

function onVendFormModalClose() {
  showVendFormModal.value = false
}
</script>