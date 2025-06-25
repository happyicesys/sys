<template>

  <Head title="APK Settings" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        APK Settings
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
        <div class="flex justify-end">
          <Link href="/apk-settings/create">
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
          <SearchInput placeholderStr="Name" v-model="filters.name" @keyup.enter="onSearchFilterUpdated()">
            Name
          </SearchInput>
          <SearchInput placeholderStr="Machine ID" v-model="filters.codes" @keyup.enter="onSearchFilterUpdated()">
            Machine ID
            <span class="text-[9px]">
                ("," for multiple)
            </span>
          </SearchInput>
          <!-- <div>
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
          </div> -->
          <div>
						<label for="text" class="block text-sm font-medium text-gray-700">
							Machine Prefix
						</label>
						<MultiSelect
							v-model="filters.vendPrefixes"
							:options="vendPrefixOptions"
							trackBy="id"
							valueProp="id"
							label="value"
							placeholder="Select"
							open-direction="bottom"
							mode="tags"
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
                  <span class="font-medium">{{ apkSettings.meta.from ?? 0 }}</span>
                  <span>to</span>
                  <span class="font-medium">{{ apkSettings.meta.to ?? 0 }}</span>
                  <span>of</span>
                  <span class="font-medium">{{ apkSettings.meta.total }}</span>
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
                      Binded Machine ID
                    </TableHead>
                    <TableHead>
                    </TableHead>
                  </tr>
                </thead>
                  <tbody class="bg-white">
                    <tr v-for="(apkSetting, apkSettingIndex) in apkSettings.data" :key="apkSetting.id" class="divide-x divide-gray-200 odd:bg-white even:bg-gray-100">
                      <TableData :currentIndex="apkSettingIndex" :totalLength="apkSettings.length" inputClass="text-center">
                        {{ apkSettings.meta.from + apkSettingIndex }}
                      </TableData>
                      <TableData :currentIndex="apkSettingIndex" :totalLength="apkSettings.length" inputClass="text-center">
                        {{ apkSetting.name }}
                      </TableData>
                      <TableData :currentIndex="apkSettingIndex" :totalLength="apkSettings.length" inputClass="text-center">
                        <div class="flex flex-col space-y-1">
                          <span class="text-center text-indigo-600 text-xs">
                            {{ apkSetting.vends?.length }} Machine(s)
                          </span>
                          <ul class="divide-y divide-gray-200">
                            <li class="flex py-1 px-3 space-x-2" v-for="(vend, vendIndex) in apkSetting.vends">
                              <span>
                                {{ vendIndex + 1 }}.
                              </span>
                              <a :href="'/vends/customers?codes=' + vend.code" target="_blank" class="text-blue-700">
                                <span>
                                  {{ vend.code }}
                                </span>
                              </a>

                              <span v-if="vend.customer && vend.customer.person_id">
                                  <span v-if="permissions.includes('admin-access vends')">
                                      <a :class="[vend.customer && vend.customer.person_id && vend.customer.is_active ? 'text-blue-700' : 'text-gray-400']" target="_blank" :href="'/customers/' + vend.customer.id + '/edit'">
                                          {{ vend.customer.virtual_customer_code }} ({{ vend.customer.virtual_customer_prefix }})
                                          <br>
                                          {{ vend.customer.name }}
                                      </a>
                                  </span>
                                  <span v-else>
                                      {{ vend.customer.virtual_customer_code }} ({{ vend.customer.virtual_customer_prefix }})
                                      <br>
                                      {{ vend.customer.name }}
                                  </span>
                              </span>
                              <span v-else-if="vend.customer && !vend.customer.person_id">
                                  <span v-if="permissions.includes('admin-access vends')" :class="[vend.customer.is_active ? 'text-gray-800' : 'text-gray-400']">
                                      <!-- <a class="text-blue-700" target="_blank" :href="'//admin.happyice.com.sg/person/' + vend.person_id + '/edit'"> -->
                                          {{ vend.customer.name }}
                                      <!-- </a> -->
                                  </span>
                                  <span v-else :class="[vend.customer.is_active ? 'text-gray-800' : 'text-gray-400']">
                                      {{ vend.customer.name }}
                                  </span>
                              </span>
                            </li>
                          </ul>
                        </div>
                      </TableData>

                      <TableData :currentIndex="apkSettingIndex" :totalLength="apkSettings.length" inputClass="text-center">
                        <div class="flex flex-col justify-center space-y-1">
                          <Link :href="'/apk-settings/' + apkSetting.id + '/edit'">
                            <Button
                              type="button" class="bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
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
                <tr v-if="!apkSettings.data.length">
                  <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                      No Results Found
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator v-if="apkSettings.data.length" :links="apkSettings.links" :meta="apkSettings.meta"></Paginator>
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
import { AdjustmentsHorizontalIcon, BackspaceIcon, MagnifyingGlassIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/20/solid';
import SingleSortItem from '@/Components/SingleSortItem.vue';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ref, onMounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
    apkSettings: Object,
    operatorOptions: Object,
    vendPrefixOptions: Object,
})

const filters = ref({
    name: '',
    codes: '',
    sortKey: '',
    sortBy: false,
    numberPerPage: '',
    visited: true,
  })
  const authOperator = usePage().props.auth.operator
  const loading = ref(false)
  const operatorOptions = ref([])
  const type = ref('')
  const numberPerPageOptions = ref([])
  const operatorCountry = usePage().props.auth.operatorCountry
  const operatorRole = usePage().props.auth.operatorRole
  const permissions = usePage().props.auth.permissions
  const roles = usePage().props.auth.roles
  const vendPrefixOptions = ref([])

onMounted(() => {
    numberPerPageOptions.value = [
        { id: 100, value: 100 },
        { id: 200, value: 200 },
        { id: 500, value: 500 },
        { id: 'All', value: 'All' },
    ]
    filters.value.numberPerPage = numberPerPageOptions.value[0]
    operatorOptions.value = [
        {id: 'all', full_name: 'All'},
        ...props.operatorOptions.data.map((data) => {return {id: data.id, code:data.code, full_name: data.full_name}})
    ]
    vendPrefixOptions.value = [
        {id: 'all', value: 'All'},
        {id: 'single-ud', value: 'Single UD'},
        ...props.vendPrefixOptions.data.map((data) => {return {id: data.id, value: data.name}})
    ]

    // filters.value.locationType = locationTypeOptions.value[0]
    filters.value.operators = authOperator ? [
		operatorOptions.value.find(operator => operator.id === authOperator.id),
		...authOperator.code == 'HIPL' ? [operatorOptions.value.find(operator => operator.code == 'HIMD')] : [],
	] : operatorOptions.value[0]
})

function onCreateClicked() {
  type.value = 'create'
  apkSetting.value = null
  showModal.value = true
}

function onDeleteClicked(apkSetting) {
  const approval = confirm('Are you sure to delete ' + apkSetting.name + '?');
  if (!approval) {
      return;
  }
  router.delete('/apk-settings/' + apkSetting.id)
}

function onSearchFilterUpdated() {
  router.get('/apk-settings', {
      ...filters.value,
      // operators: filters.value.operators?.map((operator) => { return operator.id }),
      // make codes as array split by comma
      vendPrefixes: filters.value.vendPrefixes?.map((vendPrefix) => { return vendPrefix.id }),
      numberPerPage: filters.value.numberPerPage.id,
  }, {
      preserveState: true,
      replace: true,
  })
}

function resetFilters() {
  router.get('/apk-settings')
}

function sortTable(sortKey) {
  filters.value.sortKey = sortKey
  filters.value.sortBy = !filters.value.sortBy
  onSearchFilterUpdated()
}
</script>