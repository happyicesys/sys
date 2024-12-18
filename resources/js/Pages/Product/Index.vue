<template>

  <Head title="Product" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Products
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
        <div class="flex justify-end mb-4">
          <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
          @click="onCreateClicked()"
          v-if="permissions.includes('create products')"
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
          <div v-if="permissions.includes('admin-access products')">
            <label for="text" class="block text-sm font-medium text-gray-700">
              Operator
            </label>
            <MultiSelect
              v-model="filters.operator_id"
              :options="operatorOptions"
              trackBy="id"
              valueProp="id"
              label="full_name"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
            >
            </MultiSelect>
          </div>
          <!-- <div>
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
          </div> -->
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
          <!-- <div>
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
          </div> -->
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
                      Category
                    </TableHead>
                    <TableHead>
                      SubCategory
                    </TableHead>
                    <TableHead>
                      Thumbnail
                    </TableHead>
                    <TableHead v-if="permissions.includes('admin-access products')">
                      Operator
                    </TableHead>
                    <TableHead v-if="permissions.includes('admin-access products')">
                      Unit Cost <br>
                      <span class="text-xs text-gray-500">
                        (before GST)
                      </span>
                    </TableHead>
                    <TableHead v-if="permissions.includes('admin-access products')">
                      <div class="flex flex-col space-y-1">
                        <span>
                          Reference Price
                        </span>
                        <span class="text-xs text-gray-600">
                          (tax inclusive)
                        </span>
                      </div>
                    </TableHead>
                    <TableHead v-if="permissions.includes('admin-access products')">
                      <div class="flex justify-center">
                        <!-- <span>
                          Selling Price <br>
                          (before GST)
                        </span> -->
                        <span>
                          Gross Margin
                        </span>
                      </div>
                    </TableHead>
                    <TableHead>
                      Campaign Label(s)
                    </TableHead>
                    <!-- <TableHead>
                      Category
                    </TableHead>
                    <TableHead>
                      Group
                    </TableHead>
                    <TableHead>
                      Is Inventory
                    </TableHead> -->
                    <!-- <TableHead>
                      Is Active
                    </TableHead> -->
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
                        <div class="flex flex-col space-y-1">
                          {{ product.name }}
                          <div v-for="productName in product.translated_names_json" class="text-indigo-600">
                            {{ productName.name }}
                          </div>
                          <div
                            class="inline-flex justify-center items-center rounded px-0.5 py-0.5 text-xs border w-fit hover:cursor-pointer"
                            :class="product.is_active ? 'bg-green-100 text-green-800 border-green-300' : 'bg-red-100 text-red-800 border-red-300'"
                          >
                            <div class="flex flex-col">
                                <span class="font-semibold grow-0">
                                  {{ product.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                          </div>
                        </div>
                      </TableData>
                      <TableData :currentIndex="productIndex" :totalLength="products.length" inputClass="text-center">
                        {{ product.categoryGroup?.name }}
                      </TableData>
                      <TableData :currentIndex="productIndex" :totalLength="products.length" inputClass="text-center">
                        {{ product.category?.name }}
                      </TableData>
                      <TableData :currentIndex="productIndex" :totalLength="products.length" inputClass="text-center">
                        <div class="flex justify-center">
                          <a :href="product.thumbnail.full_url" target="_blank" v-if="product.thumbnail">
                            <img class="h-28 w-28 md:h-24 md:w-24 rounded-full" :src="product.thumbnail.full_url" alt="" />
                          </a>
                        </div>
                      </TableData>
                      <TableData :currentIndex="productIndex" :totalLength="products.length" inputClass="text-center" v-if="permissions.includes('admin-access products')">
                        <span v-if="product.operator">
                          {{ product.operator.code }}
                        </span>
                      </TableData>
                      <TableData :currentIndex="productIndex" :totalLength="products.length" inputClass="text-right" v-if="permissions.includes('admin-access products')">
                        {{ product.latestUnitCost ? (product.latestUnitCost.cost).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) : null }}
                      </TableData>
                      <TableData :currentIndex="productIndex" :totalLength="products.length" inputClass="text-center" v-if="permissions.includes('admin-access products')">
                        <div class="flex flex-col space-y-1">
                          <span v-for="(sellingPrice, sellingPriceIndex) in product.sellingPrices" class="flex space-x-1 justify-center">
                            <div
                                class="inline-flex rounded px-0.5 py-0.5 text-xs border w-fit bg-indigo-100 text-indigo-800 border-indigo-300"
                            >
                                <div class="flex space-x-1">
                                    <span class="font-semibold grow-0">
                                      {{ sellingPrice.type ? sellingPrice.type_name : null }}:
                                    </span>
                                    <span>
                                      {{ sellingPrice.amount ? (sellingPrice.amount/ (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) : null }}
                                    </span>
                                </div>
                            </div>
                          </span>
                        </div>
                      </TableData>
                      <TableData :currentIndex="productIndex" :totalLength="products.length" inputClass="text-center" v-if="permissions.includes('admin-access products')">
                        <div class="flex flex-col space-y-1">
                          <span v-for="(sellingPrice, sellingPriceIndex) in product.sellingPrices" class="flex justify-center">
                            <!-- <div
                                class="inline-flex rounded px-0.5 py-0.5 text-xs border w-fit bg-purple-100 text-purple-800 border-purple-300"
                            >
                                <div class="flex space-x-1">
                                    <span class="font-semibold grow-0">
                                      {{ sellingPrice.type ? sellingPrice.type_name : null }}:
                                    </span>
                                    <span>
                                      {{ calculateSellingPrice(sellingPrice.amount, operatorCountry.currency_exponent, operatorCountry.is_currency_exponent_hidden, product.operator ? product.operator.gst_vat_rate : 0) }}
                                    </span>
                                </div>
                            </div> -->
                            <div
                                class="inline-flex rounded px-0.5 py-0.5 text-xs border w-fit bg-indigo-100 text-indigo-800 border-indigo-300"
                                v-if="product && product.latestUnitCost"
                            >
                                <div class="flex space-x-1">
                                    <span class="font-semibold grow-0">
                                      {{ sellingPrice.type ? sellingPrice.type_name : null }}:
                                    </span>
                                    <span>
                                      {{ calculateGrossMargin(sellingPrice.amount, product.latestUnitCost.cost, operatorCountry.currency_exponent, operatorCountry.is_currency_exponent_hidden, product.operator ? product.operator.gst_vat_rate : 0) }}
                                    </span>
                                </div>
                            </div>
                          </span>
                        </div>
                      </TableData>
                      <!-- <TableData :currentIndex="productIndex" :totalLength="products.length" inputClass="text-center">
                        {{ product.category_id ? product.category_id.name : null }}
                      </TableData>
                      <TableData :currentIndex="productIndex" :totalLength="products.length" inputClass="text-center">
                        {{ product.category_group_id ? product.category_group_id.name : null }}
                      </TableData>
                      <TableData :currentIndex="productIndex" :totalLength="products.length" inputClass="text-center">
                        {{ product.isInventory }}
                      </TableData> -->
                      <!-- <TableData :currentIndex="productIndex" :totalLength="products.length" inputClass="text-center">
                        {{ product.isActive }}
                      </TableData> -->
                      <TableData :currentIndex="productIndex" :totalLength="products.length" inputClass="text-center">
                        <div class="flex flex-col space-y-1">
                          <span v-for="(tagBinding, tagBindingIndex) in product.tagBindings" class="flex space-x-1 justify-center">
                            <div
                                class="inline-flex rounded px-0.5 py-0.5 text-xs border w-fit bg-blue-100 text-blue-800 border-blue-300"
                            >
                                <div class="flex space-x-1">
                                    <span class="font-semibold grow-0">
                                      {{ tagBinding.tag ? tagBinding.tag.name : null }}
                                    </span>
                                </div>
                            </div>
                          </span>
                        </div>
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
      :languageOptions="props.languageOptions"
      :measurementUnitOptions="measurementUnitOptions"
      :operatorOptions="operatorOptions"
      :permissions="permissions"
      :priceTypeOptions="priceTypeOptions"
      :productTagOptions="props.productTagOptions"
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
import { Head, usePage } from '@inertiajs/vue3';
import { ref, onMounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
  categories: Object,
  categoryGroups: Object,
  languageOptions: [Array, Object],
  measurementUnitOptions: Object,
  operatorOptions: Object,
  priceTypeOptions: Object,
  products: Object,
  productTagOptions: Object,
  uoms: Object,
})

const filters = ref({
  code: '',
  name: '',
  operator_id: '',
  is_active: true,
  // is_comm_or_sf: '',
  // is_inventory: '',
  sortKey: '',
  sortBy: true,
  numberPerPage: 100,
})
const authOperator = usePage().props.auth.operator
const booleanOptions = ref([])
const commSfOptions = ref([])
const languageOptions = ref([])
const showModal = ref(false)
const roles = usePage().props.auth.roles
const permissions = usePage().props.auth.permissions
const operatorCountry = usePage().props.auth.operatorCountry
const operatorOptions = ref([])
const operatorRole = usePage().props.auth.operatorRole
const product = ref()
const type = ref('')
const numberPerPageOptions = ref([])

onMounted(() => {
  // console.log(JSON.parse(JSON.stringify(props.languageOptions)))
  numberPerPageOptions.value = [
    { id: 100, value: 100 },
    { id: 200, value: 200 },
    { id: 500, value: 500 },
    { id: 'All', value: 'All' },
  ]
  booleanOptions.value = [
    {id: 'true', value: 'Yes'},
    {id: 'false', value: 'No'},
  ]
  // filters.value.is_active = booleanOptions.value[0]
  // filters.value.is_inventory = booleanOptions.value[0]
  commSfOptions.value = [
    {id: '', value: 'All'},
    {id: 'comm', value: 'Comm Only'},
    {id: 'sf', value: 'SF Only'},
    {id: 'both', value: 'Both Comm & SF'},
  ]
  operatorOptions.value = [
    {
        id: 'all', full_name: 'All'
    },
    ...props.operatorOptions.data.map((data) => {return {id: data.id, full_name: data.full_name}})
  ]
  filters.value.numberPerPage = numberPerPageOptions.value[0]
  filters.value.operator_id = authOperator ? operatorOptions.value.find(operator => operator.id === authOperator.id) : operatorOptions.value[0]
  filters.value.is_active = booleanOptions.value[0]
  // console.log(JSON.parse(JSON.stringify(props.uoms)))
})

function calculateSellingPrice(amount, currencyExponent, isCurrencyExponentHidden, gstVatRate) {
  if (!amount) {
        return null;
    }

    const convertedAmount = amount / Math.pow(10, currencyExponent);
    const divisor = + gstVatRate + 100; // This will be 109.00 if gstVatRate is 9.00
    if (divisor === 0) {
        return null;
    }

    const priceBeforeGST = (convertedAmount / divisor) * 100;

    return priceBeforeGST.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function calculateGrossMargin(amount, latestUnitCost, currencyExponent, isCurrencyExponentHidden, gstVatRate) {
    if (!amount || !latestUnitCost) {
        return null;
    }

    const convertedAmount = amount / Math.pow(10, currencyExponent);
    const divisor = + gstVatRate + 100;
    if (divisor === 0) {
        return null;
    }

    const priceBeforeGST = (convertedAmount / divisor) * 100;
    const grossMargin = ((priceBeforeGST - latestUnitCost) / priceBeforeGST) * 100;

    return grossMargin.toLocaleString(undefined, {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1,
    }) + '%';
}


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
  router.delete('/products/' + product.id)
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
  router.get('/products', {
      ...filters.value,
      numberPerPage: filters.value.numberPerPage.id,
      operator_id: filters.value.operator_id.id,
      is_active: filters.value.is_active.id,
      // is_inventory: filters.value.is_inventory.id,
      // is_comm_or_sf: filters.value.is_comm_or_sf.id,
  }, {
      preserveState: true,
      replace: true,
  })
}

function resetFilters() {
  router.get('/products')
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