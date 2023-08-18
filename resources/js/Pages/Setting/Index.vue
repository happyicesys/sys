<template>

  <Head title="VM Management" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Device Management
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
        <div class="flex justify-end">
          <Link href="/settings/vend/0/create">
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
          <SearchInput placeholderStr="Vend ID" v-model="filters.codes" @keyup.enter="onSearchFilterUpdated()">
            Vend ID
            <span class="text-[9px]">
                ("," for multiple)
            </span>
          </SearchInput>
          <SearchInput placeholderStr="Cust ID" v-model="filters.customer_code" v-if="permissions.includes('admin-access vends')" @keyup.enter="onSearchFilterUpdated()">
            Cust ID
          </SearchInput>
          <SearchInput placeholderStr="Cust Name" v-model="filters.customer_name" v-if="permissions.includes('admin-access vends')" @keyup.enter="onSearchFilterUpdated()">
            Cust Name
          </SearchInput>
          <div v-if="permissions.includes('admin-access vends')">
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
          <div v-if="permissions.includes('admin-access vends')">
            <label for="text" class="block text-sm font-medium text-gray-700">
                Group
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
          <!-- <div v-if="permissions.includes('admin-access vends')">
            <label for="text" class="block text-sm font-medium text-gray-700">
                Customer Binded?
            </label>
            <MultiSelect
                v-model="filters.is_binded_customer"
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
          <div v-if="permissions.includes('admin-access vends')">
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
          <div v-if="permissions.includes('admin-access vends')">
            <label for="text" class="block text-sm font-medium text-gray-700">
                Operator
            </label>
            <MultiSelect
                v-model="filters.operator"
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
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
                Location Type
            </label>
            <MultiSelect
                v-model="filters.locationType"
                :options="locationTypeOptions"
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
                  <span class="font-medium">{{ vends.meta.from ?? 0 }}</span>
                  <span>to</span>
                  <span class="font-medium">{{ vends.meta.to ?? 0 }}</span>
                  <span>of</span>
                  <span class="font-medium">{{ vends.meta.total }}</span>
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
                    <TableHead>
                      Name
                    </TableHead>
                    <TableHead>
                      Status
                    </TableHead>
                    <TableHead>
                      Begin Date
                    </TableHead>
                    <TableHead>
                      Deactivation Date
                    </TableHead>
                    <TableHead>
                      Operator
                    </TableHead>
                    <TableHead>
                    </TableHead>
                  </tr>
                </thead>
                  <tbody class="bg-white">
                    <tr v-for="(vend, vendIndex) in vends.data" :key="vend.id" class="divide-x divide-gray-200">
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        {{ vends.meta.from + vendIndex }}
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-left">
                        {{ vend.code }}
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-left">
                        <span v-if="vend.latestVendBinding && vend.latestVendBinding.customer">
                          <a class="text-blue-700" target="_blank" :href="'//admin.happyice.com.sg/person/vend-code/' + vend.code">
                            {{ vend.latestVendBinding.customer.code }}
                            <br>
                            {{ vend.latestVendBinding.customer.name }}
                          </a>
                        </span>
                        <span v-else>
                          {{ vend.name }}
                        </span>
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        <div class="flex flex-col space-y-1">
                          <div
                            class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
                            :class="[vend.is_active ? 'bg-green-200' : 'bg-red-200']"
                          >
                            <div class="flex flex-col">
                              <span class="font-bold">
                                {{vend.is_active ? 'Active' : 'Inactive'}}
                              </span>
                            </div>
                          </div>
                        </div>
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        {{ vend.begin_date_short }}
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        {{ vend.termination_date_short }}
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                          {{ vend.latestOperator ? vend.latestOperator.full_name : '' }}
                      </TableData>
                      <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                        <div class="flex justify-center space-x-1">
                          <Link :href="'/settings/vend/' + vend.id + '/update'">
                            <Button
                              type="button" class="bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
                            >
                              <PencilSquareIcon class="w-4 h-4"></PencilSquareIcon>
                              <span>
                                  Edit
                              </span>
                            </Button>
                          </Link>
                          <!-- <Button
                            type="button" class="bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
                            @click="onEditClicked(vend)"
                          >
                            <PencilSquareIcon class="w-4 h-4"></PencilSquareIcon>
                            <span>
                                Edit
                            </span>
                          </Button> -->
                          <Button
                            type="button" class="bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1"
                            @click="onDeleteClicked(vend)"
                          >
                            <TrashIcon class="w-4 h-4"></TrashIcon>
                            <span>
                                Delete
                            </span>
                          </Button>
                        </div>
                      </TableData>
                      </tr>
                <tr v-if="!vends.data.length">
                  <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                      No Results Found
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator v-if="vends.data.length" :links="vends.links" :meta="vends.meta"></Paginator>
          </div>
      </div>
    </div>
  </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { BackspaceIcon, MagnifyingGlassIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ref, onMounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    categories: Object,
    categoryGroups: Object,
    locationTypeOptions: Object,
    operatorOptions: Object,
    vends: Object,
  })

const filters = ref({
    codes: '',
    customer_code: '',
    customer_name: '',
    categories: [],
    categoryGroups: [],
    locationType: '',
    operator: '',
    is_active: '',
    is_binded_customer: '',
    sortKey: '',
    sortBy: true,
    numberPerPage: '',
    visited: true,
  })
  const booleanOptions = ref([])
  const categoryOptions = ref([])
  const categoryGroupOptions = ref([])
  const initBinded = usePage().props.initBinded
  const loading = ref(false)
  const locationTypeOptions = ref([])
  const numberPerPageOptions = ref([])
  const operatorOptions = ref([])
  const type = ref('')
  const vend = ref()
  const operatorCountry = usePage().props.auth.operatorCountry
  const operatorRole = usePage().props.auth.operatorRole
  const permissions = usePage().props.auth.permissions
  const roles = usePage().props.auth.roles
  const now = ref(moment().format('HH:mm:ss'))

onMounted(() => {
    numberPerPageOptions.value = [
        { id: 50, value: 50 },
        { id: 100, value: 100 },
        { id: 200, value: 200 },
        { id: 500, value: 500 },
        { id: 'All', value: 'All' },
    ]
    filters.value.numberPerPage = numberPerPageOptions.value[0]

    categoryOptions.value = props.categories.data.map((data) => {return {id: data.id, name: data.name}})
    categoryGroupOptions.value = props.categoryGroups.data.map((data) => {return {id: data.id, name: data.name}})
    booleanOptions.value = [
        {id: 'all', value: 'All'},
        {id: 'true', value: 'Yes'},
        {id: 'false', value: 'No'},
    ]
    locationTypeOptions.value = [
        {id: 'all', value: 'All'},
        ...props.locationTypeOptions.data.map((data) => {return {id: data.id, value: data.name}})
    ]
    operatorOptions.value = [
        {id: 'all', full_name: 'All'},
        ...props.operatorOptions.data.map((data) => {return {id: data.id, full_name: data.full_name}})
    ]
    filters.value.locationType = locationTypeOptions.value[0]
    filters.value.operator = operatorOptions.value[0]

    filters.value.is_active = booleanOptions.value[1]
    filters.value.is_binded_customer = initBinded && (roles[0] == 'superadmin' || roles[0] == 'admin' ||  roles[0] == 'supervisor' || roles[0] == 'driver') ? booleanOptions.value[1] : booleanOptions.value[0]
})

function onCreateClicked() {
  type.value = 'create'
  vend.value = null
  showModal.value = true
}

function onDeleteClicked(vend) {
  const approval = confirm('Are you sure to delete ' + vend.name + '?');
  if (!approval) {
      return;
  }
  router.delete('/vends/' + vend.id)
}

function onSearchFilterUpdated() {
  router.get('/settings', {
      ...filters.value,
      categories: filters.value.categories.map((category) => { return category.id }),
      categoryGroups: filters.value.categoryGroups.map((categoryGroup) => { return categoryGroup.id }),
      location_type_id: filters.value.locationType.id,
      operator_id: filters.value.operator.id,
      is_active: filters.value.is_active.id,
      is_binded_customer: filters.value.is_binded_customer.id,
      numberPerPage: filters.value.numberPerPage.id,
  }, {
      preserveState: true,
      replace: true,
  })
}

function resetFilters() {
  router.get('/settings')
}

function sortTable(sortKey) {
  filters.value.sortKey = sortKey
  filters.value.sortBy = !filters.value.sortBy
  onSearchFilterUpdated()
}
</script>