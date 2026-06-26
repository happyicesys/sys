
<template>

  <Head title="Users" />

  <BreezeAuthenticatedLayout>
      <template #header>
          <h2 class="font-semibold text-xl text-gray-800 leading-tight">
              Users
          </h2>
      </template>

      <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
        <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
          <div class="flex justify-end">
              <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
              @click="onCreateClicked()"
              v-if="permissions.includes('create users')"
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
              <SearchInput placeholderStr="Email" v-model="filters.email">
                  Email
              </SearchInput>
              <div v-if="permissions.includes('admin-access users')">
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
          </div>

          <div class="flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5">
              <div class="mt-3">
                  <div class="flex flex-col space-y-1 md:flex-row md:space-y-0 md:space-x-1">
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
                      <span class="font-medium">{{ users.meta.from ?? 0 }}</span>
                      <span>to</span>
                      <span class="font-medium">{{ users.meta.to ?? 0 }}</span>
                      <span>of</span>
                      <span class="font-medium">{{ users.meta.total }}</span>
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
                          <TableHeadSort modelName="name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('name')">
                              Name
                          </TableHeadSort>
                          <TableHead>
                              Email
                          </TableHead>
                          <TableHead>
                              Username
                          </TableHead>
                          <TableHead>
                              Phone Number
                          </TableHead>
                          <TableHeadSort modelName="role_name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('role_name')">
                            Role
                          </TableHeadSort>
                          <TableHeadSort modelName="operator_id" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('operator_id')">
                              Belongs to Operator
                        </TableHeadSort>
                          <TableHead>
                          </TableHead>
                      </tr>
                  </thead>
                  <tbody class="bg-white">
                      <tr v-for="(user, userIndex) in users.data" :key="user.id"
                          class="divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100">
                          <TableData :currentIndex="userIndex" :totalLength="users.length" inputClass="text-center">
                              {{ users.meta.from + userIndex }}
                          </TableData>
                          <TableData :currentIndex="userIndex" :totalLength="users.length" inputClass="text-left">
                            <div class="flex flex-col space-y-1">
                                <div>
                                {{ user.name }}
                                </div>
                                <div
                                    class="inline-flex justify-center items-center rounded px-0.5 py-0.5 text-xs border w-fit hover:cursor-pointer"
                                    :class="user.is_active ? 'bg-green-100 text-green-800 border-green-300' : 'bg-red-100 text-red-800 border-red-300'"
                                >
                                    <div class="flex flex-col">
                                        <span class="font-semibold grow-0">
                                        {{ user.is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                          </TableData>
                          <TableData :currentIndex="userIndex" :totalLength="users.length" inputClass="text-left">
                              {{ user.email }}
                          </TableData>
                          <TableData :currentIndex="userIndex" :totalLength="users.length" inputClass="text-center">
                              {{ user.username }}
                          </TableData>
                          <TableData :currentIndex="userIndex" :totalLength="users.length" inputClass="text-center">
                              {{ user.phoneCountry ? user.phoneCountry.code : null }} {{ user.phone_number }}
                          </TableData>
                          <TableData :currentIndex="userIndex" :totalLength="users.length" inputClass="text-center">
                              {{ user.role_name }}
                          </TableData>
                          <TableData :currentIndex="userIndex" :totalLength="users.length" inputClass="text-center">
                              {{ user.operator ? user.operator.name : null }}
                          </TableData>
                          <TableData :currentIndex="userIndex" :totalLength="users.length" inputClass="text-center">
                              <div class="flex justify-center space-x-1">
                                <Link :href="'/users/' + user.id + '/edit'">
                                    <Button
                                        type="button" class="bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
                                        v-if="permissions.includes('update users')"
                                    >
                                    <PencilSquareIcon class="w-4 h-4"></PencilSquareIcon>
                                    <span>
                                        Edit
                                    </span>
                                    </Button>
                                </Link>
                                  <Button
                                      type="button" class="bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1"
                                      @click="onDeleteClicked(user)"
                                      v-if="permissions.includes('delete users')"
                                  >
                                      <TrashIcon class="w-4 h-4"></TrashIcon>
                                      <span>
                                          Delete
                                      </span>
                                  </Button>
                              </div>
                          </TableData>
                      </tr>
                      <tr v-if="!users.data.length">
                          <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                              No Results Found
                          </td>
                      </tr>
                  </tbody>
              </table>
              <Paginator v-if="users.data.length" :links="users.links" :meta="users.meta"></Paginator>
          </div>
      </div>
      </div>
  </div>
  <Form
      v-if="showFormModal"
      :countries="countries"
      :user="user"
      :operators="operators"
      :roles="roles"
      :type="type"
      :showModal="showFormModal"
      :permissions="permissions"
      :unbindedVends="unbindedVends"
      @modalClose="onModalClose"
  >
  </Form>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import Form from '@/Pages/User/Form.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { BackspaceIcon, MagnifyingGlassIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { useToast } from "vue-toastification";

const props = defineProps({
  users: Object,
  countries: Object,
  operators: Object,
  roles: Object,
  unbindedVends: Object,
})

const filters = ref({
    is_active: '',
  name: '',
  operator_id: '',
  uen: '',
  sortKey: '',
  sortBy: true,
  numberPerPage: 100,
})
const authOperator = usePage().props.auth.operator
const booleanOptions = ref([])
const operatorOptions = ref([])
const showFormModal = ref(false)
const user = ref()
const type = ref('')
const toast = useToast()
const numberPerPageOptions = ref([])
const permissions = usePage().props.auth.permissions

onMounted(() => {
    booleanOptions.value = [
        {id: '', value: 'All'},
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
  operatorOptions.value = [
    {id: 'all', full_name: 'All'},
    ...props.operators.data.map((data) => {return {id: data.id, full_name: data.full_name}})
    ]
    filters.value.operator_id = authOperator ? operatorOptions.value.find(operator => operator.id === authOperator.id) : operatorOptions.value[0]

    filters.value.is_active = booleanOptions.value[1]
})

function onCreateClicked() {
  type.value = 'create'
  user.value = null
  showFormModal.value = true
}

function onDeleteClicked(user) {
  const approval = confirm('Are you sure to delete ' + user.name + '?');
  if (!approval) {
      return;
  }
  router.delete('/users/' + user.id, {
    onSuccess: () => {
      toast.success("User deleted successfully", { timeout: 3000 })
    },
    onError: () => {
      toast.error("Failed to delete user", { timeout: 3000 })
    }
  })
}

function onEditClicked(userValue) {
    // router.reload({
    //     only: ['unbindedVends'],
    //     preserveState: true,
    //     preserveScroll: true,
    // })
    router.visit(
      route('users', {
          id: userValue.id
      }),{
          only: ['unbindedVends'],
          preserveState: true,
          replace: true,
            onSuccess: (page) => {

            },
      }
    );
    // console.log(props.unbindedVends)
  type.value = 'update'
  user.value = userValue
  showFormModal.value = true
//   router.only['unbindedVends']({ id: userValue.id })

}

function onSearchFilterUpdated() {
  router.get('/users', {
      ...filters.value,
      is_active: filters.value.is_active.id,
      operator_id: filters.value.operator_id.id,
      numberPerPage: filters.value.numberPerPage.id,
  }, {
      preserveState: true,
      replace: true,
  })
}

function resetFilters() {
  router.get('/users')
}

function sortTable(sortKey) {
  filters.value.sortKey = sortKey
  filters.value.sortBy = !filters.value.sortBy
  onSearchFilterUpdated()
}

function onModalClose() {
    showFormModal.value = false
}

</script>