<template>

  <Head title="Machine Model" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Data Management (Serial Number)
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
        <div class="flex justify-end">
          <Button v-if="permissions.create" class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
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
          <SearchInput placeholderStr="Serial Number" v-model="filters.code">
            Serial Number
          </SearchInput>
          <SearchInput placeholderStr="4 to 5 Digits Number" v-model="filters.vend_codes" @keyup.enter="onSearchFilterUpdated()">
              Machine ID
              <span class="text-[9px]">
                  ("," for multiple)
              </span>
          </SearchInput>
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
                Machine Model
            </label>
            <MultiSelect
                v-model="filters.vendModels"
                :options="vendModelOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
                mode="tags"
            >
            </MultiSelect>
          </div>
          <div>
						<label for="text" class="block text-sm font-medium text-gray-700">
							Machine Status
						</label>
						<MultiSelect
							v-model="filters.status"
							:options="statusOptions"
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
                Setting Chart
            </label>
            <MultiSelect
                v-model="filters.vendConfigs"
                :options="vendConfigOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
                mode="tags"
            >
            </MultiSelect>
          </div>
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
          <div>
						<label for="text" class="block text-sm font-medium text-gray-700">
							Machine Contract
						</label>
						<MultiSelect
							v-model="filters.vendContracts"
							:options="vendContractOptions"
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
          <SearchInput placeholderStr="Site" v-model="filters.customer" @keyup.enter="onSearchFilterUpdated()">
            Site
          </SearchInput>
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
                Location Type
            </label>
            <MultiSelect
                v-model="filters.locationTypes"
                :options="locationTypeOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
                mode="tags"
            >
            </MultiSelect>
          </div>
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
                LCD Monitor
            </label>
            <MultiSelect
                v-model="filters.lcd_monitor_id"
                :options="lcdMonitorOptions"
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
              <Button v-if="permissions.export" type="button" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-400 hover:bg-gray-100"
                  @click.prevent="onExportExcelClicked()">
                  <div class="flex space-x-1">
                      <div>
                          <ArrowDownTrayIcon v-if="!loading" class="h-4 w-4" aria-hidden="true"/>
                          <svg v-if="loading" aria-hidden="true" class="mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                              <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                              <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                          </svg>
                      </div>
                      <span>
                          Export Excel
                      </span>
                  </div>
              </Button>
            </div>
          </div>
          <div class="flex flex-col space-y-2">
              <p class="text-sm text-gray-700 leading-5 flex space-x-1">
                  <span>Showing</span>
                  <span class="font-medium">{{ vendSerialNumbers.meta.from ?? 0 }}</span>
                  <span>to</span>
                  <span class="font-medium">{{ vendSerialNumbers.meta.to ?? 0 }}</span>
                  <span>of</span>
                  <span class="font-medium">{{ vendSerialNumbers.meta.total }}</span>
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
                      Serial Number
                    </TableHeadSort>
                    <TableHeadSort modelName="desc" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('desc')" class="max-w-600">
                      Remarks
                    </TableHeadSort>
                    <TableHead colspan="8">
                      Machine Info
                    </TableHead>
                    <TableHead colspan="4">
                      Site Info
                    </TableHead>
                  </tr>
                  <tr>
                    <TableHead colspan="3">
                    </TableHead>
                    <TableHeadSort modelName="vend_code" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_code')">
                      ID
                    </TableHeadSort>
                    <TableHeadSort modelName="vend_model_name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_model_name')">
                      Model
                    </TableHeadSort>
                    <TableHeadSort modelName="vend_lcd_monitor" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_lcd_monitor')">
                      LCD Monitor
                    </TableHeadSort>
                    <TableHeadSort modelName="vend_status" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_status')">
                      Status
                    </TableHeadSort>
                    <TableHeadSort modelName="vend_begin_date" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_begin_date')">
                      Begin Date
                    </TableHeadSort>
                    <TableHeadSort modelName="vend_config_name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_config_name')">
                      Setting Chart
                    </TableHeadSort>
                    <!-- <TableHeadSort modelName="vend_config_name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_config_name')">
                      Setting Chart
                    </TableHeadSort> -->
                    <TableHeadSort modelName="vend_prefix_name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_prefix_name')">
                      Prefix
                    </TableHeadSort>
                    <TableHeadSort modelName="vend_contract_name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_contract_name')">
                      Contract
                    </TableHeadSort>
                    <TableHeadSort modelName="customer_name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('customer_name')">
                      Site Name
                    </TableHeadSort>
                    <TableHeadSort modelName="postcode" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('postcode')">
                      Postcode
                    </TableHeadSort>
                    <TableHeadSort modelName="operator_name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('operator_name')">
                      Operator
                    </TableHeadSort>
                    <TableHeadSort modelName="location_type_name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('location_type_name')">
                      Location Type
                    </TableHeadSort>
                  </tr>
                </thead>
                  <tbody class="bg-white">
                    <tr v-for="(vendSerialNumber, vendSerialNumberIndex) in vendSerialNumbers.data" :vendSerialNumber="vendSerialNumber.id" class="divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100">
                      <TableData :currentIndex="vendSerialNumberIndex" :totalLength="vendSerialNumbers.length" inputClass="text-center">
                        {{ vendSerialNumbers.meta.from + vendSerialNumberIndex }}
                      </TableData>
                      <TableData :currentIndex="vendSerialNumberIndex" :totalLength="vendSerialNumbers.length" inputClass="text-left">
                        {{ vendSerialNumber.code }}
                      </TableData>
                      <TableData :currentIndex="vendSerialNumberIndex" :totalLength="vendSerialNumbers.length" inputClass="text-left whitespace-pre-line max-w-40">
                        <div class="flex flex-col space-y-1">
                          <span>
                            {{ vendSerialNumber.desc }}
                          </span>
                          <Button v-if="permissions.update"
                            type="button" class="bg-gray-300 hover:bg-gray-400 text-xs text-gray-800 flex space-x-1 w-fit"
                            @click="onEditClicked(vendSerialNumber)"
                          >
                            <PencilSquareIcon class="w-3 h-3"></PencilSquareIcon>
                            <span>
                                Edit
                            </span>
                          </Button>
                        </div>
                      </TableData>
                      <TableData :currentIndex="vendSerialNumberIndex" :totalLength="vendSerialNumbers.length" inputClass="text-center">
                        <Link :href="'/settings/vend/' + vendSerialNumber.vend_id + '/update'" class="text-blue-600">
                          {{ vendSerialNumber.vend_code }}
                        </Link>
                      </TableData>
                      <TableData :currentIndex="vendSerialNumberIndex" :totalLength="vendSerialNumbers.length" inputClass="text-center">
                        {{ vendSerialNumber.vend_model_name }}
                      </TableData>
                      <TableData :currentIndex="vendSerialNumberIndex" :totalLength="vendSerialNumbers.length" inputClass="text-center">
                        {{ vendSerialNumber.vend_lcd_monitor }}
                      </TableData>
                      <TableData :currentIndex="vendSerialNumberIndex" :totalLength="vendSerialNumbers.length" :inputClass="getStatusClass(vendSerialNumber.vend_status)">
                        {{ vendSerialNumber.vend_status }}
                      </TableData>
                      <TableData :currentIndex="vendSerialNumberIndex" :totalLength="vendSerialNumbers.length" inputClass="text-center">
                        {{ vendSerialNumber.vend_begin_date }}
                      </TableData>
                      <TableData :currentIndex="vendSerialNumberIndex" :totalLength="vendSerialNumbers.length" inputClass="text-center">
                        {{ vendSerialNumber.vend_config_name }}
                      </TableData>
                      <TableData :currentIndex="vendSerialNumberIndex" :totalLength="vendSerialNumbers.length" inputClass="text-center">
                        {{ vendSerialNumber.vend_prefix_name }}
                      </TableData>
                      <TableData :currentIndex="vendSerialNumberIndex" :totalLength="vendSerialNumbers.length" inputClass="text-center">
                        {{ vendSerialNumber.vend_contract_name }}
                      </TableData>
                      <TableData :currentIndex="vendSerialNumberIndex" :totalLength="vendSerialNumbers.length" inputClass="text-left">
                        {{ vendSerialNumber.customer_virtual_code }} ({{ vendSerialNumber.vend_prefix_name }}) <br>
                        {{ vendSerialNumber.customer_name }}
                      </TableData>
                      <TableData :currentIndex="vendSerialNumberIndex" :totalLength="vendSerialNumbers.length" inputClass="text-center">
                        {{ vendSerialNumber.postcode }}
                      </TableData>
                      <TableData :currentIndex="vendSerialNumberIndex" :totalLength="vendSerialNumbers.length" inputClass="text-center">
                        {{ vendSerialNumber.operator_name }}
                      </TableData>
                      <TableData :currentIndex="vendSerialNumberIndex" :totalLength="vendSerialNumbers.length" inputClass="text-center">
                        {{ vendSerialNumber.location_type_name }}
                      </TableData>
                      </tr>
                <tr v-if="!vendSerialNumbers.data.length">
                  <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                      No Results Found
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator v-if="vendSerialNumbers.data.length" :links="vendSerialNumbers.links" :meta="vendSerialNumbers.meta"></Paginator>
          </div>
      </div>
    </div>
  </div>
  <Form
      v-if="showModal"
      :vendSerialNumber="vendSerialNumber"
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
import Form from '@/Pages/VendSerialNumber/Form.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { BackspaceIcon, MagnifyingGlassIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ref, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
  lcdMonitorOptions: [Array, Object],
  locationTypeOptions: Object,
  operatorOptions: Object,
  vendConfigOptions: Object,
  vendContractOptions: Object,
  vendModelOptions: Object,
  vendPrefixOptions: Object,
  vendSerialNumbers: Object,
  permissions: Object,
})

const filters = ref({
  code: '',
  lcd_monitor_id: '',
  locationTypes: [],
  operators: [],
  status: '',
  vend_codes: '',
  vendConfigs: [],
  vendContracts: [],
  vendModels: [],
  vendPrefixes: [],
  sortKey: '',
  sortBy: true,
  numberPerPage: 100,
})
const lcdMonitorOptions = ref([])
const loading = ref(false)
const showModal = ref(false)
const vendSerialNumber = ref()
const statusOptions = ref([])
const type = ref('')
const locationTypeOptions = ref([])
const numberPerPageOptions = ref([])
const operatorOptions = ref([])
const vendConfigOptions = ref([])
const vendContractOptions = ref([])
const vendPrefixOptions = ref([])
const vendModelOptions = ref([])

onMounted(() => {
  numberPerPageOptions.value = [
    { id: 100, value: 100 },
    { id: 200, value: 200 },
    { id: 500, value: 500 },
    { id: 'All', value: 'All' },
  ]
  filters.value.numberPerPage = numberPerPageOptions.value[0]

  lcdMonitorOptions.value = [
      { id: 'undefined', value: 'Undefined'},
      ...Object.entries(props.lcdMonitorOptions).map(([id, name]) => ({id: id, value: name}))
  ]
  locationTypeOptions.value = [
      {id: 'all', value: 'All'},
      ...props.locationTypeOptions.data.map((data) => {return {id: data.id, value: data.name}})
  ]
  operatorOptions.value = [
      {id: 'all', full_name: 'All'},
      ...props.operatorOptions.data.map((data) => {return {id: data.id, full_name: data.full_name}})
  ]
  statusOptions.value = [
			{id: 'all', value: 'All'},
			{id: 'factory', value: 'Factory (JB)'},
			{id: 'active', value: 'Active'},
			{id: 'inactive', value: 'Not Active'},
			{id: 'disposed', value: 'Disposed'},
			{id: 'sold', value: 'Sold'},
	]
  vendConfigOptions.value = [
      {id: 'all', value: 'All'},
      ...props.vendConfigOptions.data.map((data) => {return {id: data.id, value: data.name}})
  ]
  vendContractOptions.value = [
      {id: 'all', value: 'All'},
      ...props.vendContractOptions.data.map((data) => {return {id: data.id, value: data.name}})
  ]
  vendModelOptions.value = [
      {id: 'all', value: 'All'},
      ...props.vendModelOptions.data.map((data) => {return {id: data.id, value: data.name}})
  ]
  vendPrefixOptions.value = [
      {id: 'all', value: 'All'},
      {id: 'single-ud', value: 'Single UD'},
      ...props.vendPrefixOptions.data.map((data) => {return {id: data.id, value: data.name}})
  ]

  // filters.value.locationType = locationTypeOptions.value[0]
  // filters.value.operators = operatorOptions.value[0]
  // filters.value.status = statusOptions.value[0]
  // filters.value.vendConfigs = vendConfigOptions.value[0]
  // filters.value.vendModels = vendModelOptions.value[0]
  // filters.value.vendPrefixes = vendPrefixOptions.value[0]
})

function getStatusClass(status) {
  switch(status) {
    case 'Factory (JB)':
      return 'text-center'
    case 'Active':
      return 'bg-green-300 text-center'
    case 'Not Active':
      return 'text-center'
    case 'Disposed':
      return 'bg-red-300 text-center'
    case 'Sold':
      return 'bg-yellow-300 text-center'
    default:
      return 'text-center'
  }
}

function onExportExcelClicked() {
    // window.open('/vends/transactions/excel', '_blank');
    loading.value = true
    axios({
        method: 'get',
        url: '/vend-serial-numbers/excel',
        params: {
          ...filters.value,
          lcd_monitor_id: filters.value.lcd_monitor_id.id,
          locationTypes: filters.value.locationTypes.map((locationType) => locationType.id),
          operators: filters.value.operators.filter(operator => operator).map((operator) => operator.id),
          vendConfigs: filters.value.vendConfigs.map((config) => config.id),
          vendContracts: filters.value.vendContracts.map((contract) => contract.id),
          vendModels: filters.value.vendModels.map((model) => model.id),
          vendPrefixes: filters.value.vendPrefixes.map((prefix) => prefix.id),
          status: filters.value.status.id,
        },
        responseType: 'blob',
    }).then(response => {
        fileDownload(response.data, 'VendSerialNumber' + moment().format('YYMMDDhhmmss') +'.xlsx')
    }).catch(error => {
        console.log(error)
    }).finally(() => {
        loading.value = false
    })
}

function onCreateClicked() {
  type.value = 'create'
  vendSerialNumber.value = null
  showModal.value = true
}

function onEditClicked(model) {
  type.value = 'update'
  vendSerialNumber.value = model
  showModal.value = true
}

function onSearchFilterUpdated() {
  router.get('/vend-serial-numbers', {
      ...filters.value,
      lcd_monitor_id: filters.value.lcd_monitor_id.id,
      locationTypes: filters.value.locationTypes.map((location) => location.id),
      operators: filters.value.operators.filter(operator => operator).map((operator) => operator.id),
      vendConfigs: filters.value.vendConfigs.map((config) => config.id),
      vendContracts: filters.value.vendContracts.map((contract) => contract.id),
      vendModels: filters.value.vendModels.map((model) => model.id),
      vendPrefixes: filters.value.vendPrefixes.map((prefix) => prefix.id),
      status: filters.value.status.id,
      numberPerPage: filters.value.numberPerPage.id,
  }, {
      preserveState: true,
      replace: true,
  })
}

function resetFilters() {
  router.get('/vend-serial-numbers')
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