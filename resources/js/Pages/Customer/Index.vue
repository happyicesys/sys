<template>

  <Head title="Customers" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Customers
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
        <div class="flex justify-end">
          <Link href="/customers/create">
            <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            >
              <PlusIcon class="h-4 w-4" aria-hidden="true"/>
              <span>
                Create
              </span>
            </Button>
          </Link>
        </div>
          <!-- <div class="flex flex-col md:flex-row md:space-x-3 space-y-1 md:space-y-0"> -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
          <SearchInput placeholderStr="ID" v-model="filters.ref_id">
            ID
          </SearchInput>
          <SearchInput placeholderStr="ID" v-model="filters.vend_code">
            Vend ID
          </SearchInput>
          <SearchInput placeholderStr="Customer" v-model="filters.customer">
            Customer
          </SearchInput>
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
              Category
            </label>
            <MultiSelect
              v-model="filters.categories"
              :options="categoryOptions"
              trackBy="id"
              valueProp="id"
              label="name"
              mode="tags"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
            >
            </MultiSelect>
          </div>
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
              Category Group
            </label>
            <MultiSelect
              v-model="filters.categoryGroups"
              :options="categoryGroupOptions"
              trackBy="id"
              valueProp="id"
              label="name"
              mode="tags"
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
          <!-- <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
              Account Manager
            </label>
            <MultiSelect
              v-model="filters.handled_by"
              :options="userOptions"
              trackBy="id"
              valueProp="id"
              label="name"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
            >
            </MultiSelect>
          </div> -->
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
              Zone
            </label>
            <MultiSelect
              v-model="filters.zone_id"
              :options="zoneOptions"
              trackBy="id"
              valueProp="id"
              label="name"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
            >
            </MultiSelect>
          </div>
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
              Price Template
            </label>
            <MultiSelect
              v-model="filters.price_template_id"
              :options="priceTemplateOptions"
              trackBy="id"
              valueProp="id"
              label="name"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
            >
            </MultiSelect>
          </div>
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
              Tags
            </label>
            <MultiSelect
              v-model="filters.tags"
              :options="tagOptions"
              trackBy="id"
              valueProp="id"
              label="name"
              mode="tags"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
            >
            </MultiSelect>
          </div>
          <div>
              <label for="text" class="block text-sm font-medium text-gray-700">
                  Is From CMS
              </label>
              <MultiSelect
                  v-model="filters.is_cms"
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
                  <span class="font-medium">{{ customers.meta.from ?? 0 }}</span>
                  <span>to</span>
                  <span class="font-medium">{{ customers.meta.to ?? 0 }}</span>
                  <span>of</span>
                  <span class="font-medium">{{ customers.meta.total }}</span>
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
                      ID
                    </TableHead>
                    <TableHead>
                      Vend ID
                    </TableHead>
                    <TableHeadSort modelName="name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('name')">
                      Customer
                    </TableHeadSort>
                    <TableHead>
                      Label
                    </TableHead>
                    <!-- <TableHeadSort modelName="category_id" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('category_id')">
                      Category
                    </TableHeadSort>
                    <TableHeadSort modelName="category_group" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('category_group')">
                      Group
                    </TableHeadSort> -->
                    <TableHead>
                      Del Address
                    </TableHead>
                    <TableHead>
                      Del Postcode
                    </TableHead>
                    <TableHead>
                      Tags
                    </TableHead>
                    <TableHead>
                      Zone
                    </TableHead>
                    <TableHead>
                      Status
                    </TableHead>
                    <TableHeadSort modelName="created_at" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('created_at')">
                      Created At
                    </TableHeadSort>
                    <TableHead>
                      Action
                    </TableHead>
                  </tr>
                </thead>
                  <tbody class="bg-white">
                    <tr v-for="(customer, customerIndex) in customers.data" :key="customer.id" class="divide-x divide-gray-200">
                      <TableData :currentIndex="customerIndex" :totalLength="customers.length" inputClass="text-center">
                        {{ customers.meta.from + customerIndex }}
                      </TableData>
                      <TableData :currentIndex="customerIndex" :totalLength="customers.length" inputClass="text-center">
                        {{ customer.ref_id }}
                      </TableData>
                      <TableData :currentIndex="customerIndex" :totalLength="customers.length" inputClass="text-center">
                        {{ customer.vend ? customer.vend.code : null }}
                      </TableData>
                      <TableData :currentIndex="customerIndex" :totalLength="customers.length" inputClass="text-left">
                        <!-- <a :class="[customer.person_id ? 'text-blue-700' : 'text-purple-700']" target="_blank" :href="'//admin.happyice.com.sg/person/' + customer.person_id + '/edit'"> -->
                        <a :class="[customer && customer.person_id ? 'text-blue-700' : 'text-purple-700']" target="_blank" :href="customer && customer.person_id ? '//admin.happyice.com.sg/person/' + customer.person_id + '/edit' : (customer ? '/customers/' + customer.id + '/edit' : '#' )">
                          <span v-if="customer.person_id && (customer.virtual_customer_code || customer.virtual_customer_prefix)">
                            <span v-if="customer.virtual_customer_code">
                              {{ customer.virtual_customer_code }}
                            </span>
                            <span v-if="customer.virtual_customer_prefix">
                              ({{ customer.virtual_customer_prefix }})
                            </span>
                             <br>
                          </span>
                          <span v-else>
                            <span v-if="customer.code">
                              {{ customer.code }} <br>
                            </span>
                          </span>
                          {{ customer.name }}
                        </a>
                      </TableData>
                      <TableData :currentIndex="customerIndex" :totalLength="customers.length" inputClass="text-center">
                        <div
                            class="inline-flex justify-center items-center rounded px-1 py-0.5 text-[10px] font-small border min-w-full bg-green-200"
                            v-if="customer.person_id"
                        >
                          From CMS
                        </div>
                      </TableData>
                      <!-- <TableData :currentIndex="customerIndex" :totalLength="customers.length" inputClass="text-center">
                        {{ customer.category ? customer.category.name : '' }}
                      </TableData>
                      <TableData :currentIndex="customerIndex" :totalLength="customers.length" inputClass="text-center">
                        {{ customer.category && customer.category.categoryGroup ? customer.category.categoryGroup.name : '' }}
                      </TableData> -->
                      <TableData :currentIndex="customerIndex" :totalLength="customers.length" inputClass="text-left">
                        {{ customer.deliveryAddress ? customer.deliveryAddress.full_address : null }}
                      </TableData>
                      <TableData :currentIndex="customerIndex" :totalLength="customers.length" inputClass="text-center">
                        {{ customer.deliveryAddress ? customer.deliveryAddress.postcode : null }}
                      </TableData>
                      <TableData :currentIndex="customerIndex" :totalLength="customers.length" inputClass="text-left">
                        <span v-for="tag in customer.tags" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
                          {{tag.name}}
                        </span>
                      </TableData>
                      <TableData :currentIndex="customerIndex" :totalLength="customers.length" inputClass="text-center">
                        {{ customer.zone ? customer.zone.name : null }}
                      </TableData>
                      <TableData :currentIndex="customerIndex" :totalLength="customers.length" inputClass="text-center">
                        <div
                            class="inline-flex justify-center items-center rounded px-1 py-0.5 text-[12px] font-small border min-w-full bg-green-300"
                            v-if="customer.is_active"
                        >
                          Active
                        </div>
                        <div
                            class="inline-flex justify-center items-center rounded px-1 py-0.5 text-[12px] font-small border min-w-full bg-red-300"
                            v-if="!customer.is_active"
                        >
                          Not Active
                        </div>
                      </TableData>
                      <TableData :currentIndex="customerIndex" :totalLength="customers.length" inputClass="text-center">
                        {{ customer.created_at }}
                      </TableData>
                      <TableData :currentIndex="customerIndex" :totalLength="customers.length" inputClass="text-center">
                        <div class="flex justify-center space-x-1">
                          <Link :href="'/customers/' + customer.id + '/edit'">
                              <Button
                              type="button" class="bg-gray-300 hover:bg-gray-400 px-2 py-1 text-xs text-gray-800 flex space-x-1"
                              >
                              <PencilSquareIcon class="w-4 h-4"></PencilSquareIcon>
                              <span>
                                  Edit
                              </span>
                              </Button>
                          </Link>
                        </div>
                      </TableData>
                      </tr>
                <tr v-if="!customers.data.length">
                  <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                      No Results Found
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator v-if="customers.data.length" :links="customers.links" :meta="customers.meta"></Paginator>
          </div>
      </div>
    </div>
  </div>
  <Form
      v-if="showModal"
      :customer="customer"
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
import Form from '@/Pages/Customer/Form.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { BackspaceIcon, MagnifyingGlassIcon, PencilSquareIcon, PlusIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ref, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
  customers: Object,
  categories: Object,
  categoryGroups: Object,
  priceTemplates: Object,
  profiles: Object,
  statuses: Object,
  tags: Object,
  users: Object,
  zones: Object,
})

const filters = ref({
  customer: '',
  name: '',
  ref_id: '',
  status: '',
  vend_code: '',
  sortKey: '',
  sortBy: true,
  numberPerPage: 100,
})
const showModal = ref(false)
const booleanOptions = ref([])
const customer = ref()
const categoryOptions = ref([])
const categoryGroupOptions = ref([])
const priceTemplateOptions = ref([])
const profileOptions = ref([])
const statusOptions = ref([])
const tagOptions = ref([])
const userOptions = ref([])
const zoneOptions = ref([])
const type = ref('')
const numberPerPageOptions = ref([])

onMounted(() => {
  booleanOptions.value = [
    {id: 'all', value: 'All'},
    {id: 'true', value: 'Yes'},
    {id: 'false', value: 'No'},
  ]
  numberPerPageOptions.value = [
    { id: 100, value: 100 },
    { id: 200, value: 200 },
    { id: 500, value: 500 },
    { id: 'All', value: 'All' },
  ]
  filters.value.numberPerPage = numberPerPageOptions.value[0]
  categoryOptions.value = props.categories.data.map((data) => {return {id: data.id, name: data.name}})
  categoryGroupOptions.value = props.categoryGroups.data.map((data) => {return {id: data.id, name: data.name}})
  priceTemplateOptions.value = props.priceTemplates.data.map((data) => {return {id: data.id, name: data.name}})
  profileOptions.value = props.profiles.data.map((data) => {return {id: data.id, name: data.name}})
  // statusOptions.value = props.statuses.map((data) => {return {id: data.id, name: data.name}})
  userOptions.value = props.users.data.map((data) => {return {id: data.id, name: data.name}})
  zoneOptions.value = props.zones.data.map((data) => {return {id: data.id, name: data.name}})
  priceTemplateOptions.value = props.priceTemplates.data.map((data) => {return {id: data.id, name: data.name}})
  tagOptions.value = props.tags.data.map((data) => {return {id: data.id, name: data.name}})
  // filters.value.status = statusOptions.value[3]
  filters.value.is_active = booleanOptions.value[0]
  filters.value.is_cms = booleanOptions.value[0]
})

function onCreateClicked() {
  type.value = 'create'
  customer.value = null
  showModal.value = true
}

function onEditClicked(customerValue) {
  type.value = 'update'
  customer.value = customerValue
  showModal.value = true
}

function onSearchFilterUpdated() {
  router.get('/customers', {
      ...filters.value,
      is_cms: filters.value.is_cms.id,
      is_active: filters.value.is_active.id,
      // status: filters.value.status.id,
      numberPerPage: filters.value.numberPerPage.id,
  }, {
      preserveState: true,
      replace: true,
  })
}

function resetFilters() {
  router.get('/customers')
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