
<template>
  <Head title="Vending Machines" />

  <BreezeAuthenticatedLayout>
      <template #header>
          <h2 class="font-semibold text-xl text-gray-800 leading-tight">
              Vending Machines (List)
          </h2>
      </template>

      <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
        <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
          <!-- <div class="flex flex-col md:flex-row md:space-x-3 space-y-1 md:space-y-0"> -->
          <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
              <SearchInput placeholderStr="Code" v-model="filters.code">
                  Vend ID
              </SearchInput>
              <SearchInput placeholderStr="Serial Num" v-model="filters.serialNum">
                  Serial Num
              </SearchInput>
              <SearchInput placeholderStr="Number" v-model="filters.tempHigherThan">
                  Temp >>
              </SearchInput>
              <div>
                  <label for="text" class="block text-sm font-medium text-gray-700">
                      Errors?
                  </label>
                  <MultiSelect
                      v-model="filters.vend_channel_error_id"
                      :options="vendChannelErrorsOptions"
                      trackBy="id"
                      valueProp="id"
                      label="desc"
                      placeholder="Select"
                      open-direction="bottom"
                      class="mt-1"
                  >
                  </MultiSelect>
              </div>
              <SearchInput placeholderStr="Cust ID" v-model="filters.customer_code">
                  Cust ID
              </SearchInput>
              <SearchInput placeholderStr="Cust ID Name" v-model="filters.customer_name">
                  Cust ID Name
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
                          <TableHeadSort modelName="temp" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('temp')">
                              Temp
                          </TableHeadSort>
                          <TableHead>
                              Name
                          </TableHead>
                          <TableHead>
                              Category
                          </TableHead>
                          <TableHead>
                              Channel Status
                          </TableHead>
                          <TableHead>
                              Inventory Status
                          </TableHead>
                          <TableHead>
                              Balance Stock
                          </TableHead>
                          <TableHead>
                              Out of Stock SKU
                          </TableHead>
                          <TableHeadSort modelName="temp_updated_at" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('temp_updated_at')">
                              Last Temp
                          </TableHeadSort>
                          <TableHeadSort modelName="coin_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('coin_amount')">
                              Coin Amount
                          </TableHeadSort>
                          <TableHead>
                              Serial Num
                          </TableHead>
                          <TableHead>
                              Firmware Ver
                          </TableHead>
                          <TableHead>
                              Door Opening?
                          </TableHead>
                          <TableHead>
                              Sensor Normal?
                          </TableHead>
                      </tr>
                  </thead>
                  <tbody class="bg-white">
                      <tr v-for="(vend, vendIndex) in vends.data" :key="vend.id"
                          class="divide-x divide-gray-200">
                          <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                              {{ vends.meta.from + vendIndex }}
                          </TableData>
                          <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                              {{ vend.code }}
                          </TableData>
                          <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                              <div class="flex flex-col">
                                  <button
                                      type="button"
                                      class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center"
                                      :class="[vend.temp > -15 ? 'bg-red-400 active:bg-red-500 hover:bg-red-600' : 'bg-green-400 active:bg-green-500 hover:bg-green-600']"
                                      @click="onVendTempClicked(vend.id)"
                                  >
                                      {{ vend.is_temp_error ? 'Error' : vend.temp }}
                                  </button>
                              </div>
                          </TableData>
                          <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-left">
                              <!-- {{  vend.customer.code }} -->
                              {{ vend && vend.customer ? vend.customer.code : null }} <br>
                              {{ vend && vend.customer ? vend.customer.name : null }}
                          </TableData>
                          <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-left">
                              {{ vend && vend.customer && vend.customer.category ? vend.customer.category.name : null }} <br>
                              {{ vend && vend.customer && vend.customer.category && vend.customer.category.category_group ? vend.customer.category.category_group.name : null }}
                          </TableData>
                          <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">

                              <span v-for="vendChannelErrorLog in vend.vendChannelErrorLogsJson" class="inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border"
                              :class="[vendChannelErrorLog['is_error_cleared'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800']">
                                  <div class="flex flex-col">
                                      <div>
                                          #{{vendChannelErrorLog['vend_channel']['code']}},
                                          <span class="font-bold">
                                          ({{ vendChannelErrorLog['vend_channel_error']['code'] }})
                                          </span>
                                      </div>
                                      <div>
                                          {{vendChannelErrorLog['created_at']}}
                                      </div>
                                  </div>
                              </span>
                          </TableData>
                          <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                              <div class="grid grid-cols-[120px_minmax(120px,_1fr)_120px] gap-1">
                                  <div v-for="(channel, channelIndex) in vend.vendChannelsJson"
                                      class="inline-flex justify-between items-center rounded px-2.5 py-0.5 text-xs font-medium border min-w-full"
                                      :class="[channelIndex > 0 && (String(channel['code'])[0] !== String(vend.vendChannelsJson[channelIndex - 1]['code'])[0]) ? 'col-start-1' : '']"
                                  >
                                      <div class="font-semibold">
                                          #{{channel['code']}},
                                      </div>
                                      <div class="text-blue-600 text-sm pl-1">
                                          {{channel['capacity'] - channel['qty']}},
                                      </div>
                                      <div class="pl-1">
                                          {{channel['qty']}}/{{channel['capacity']}}
                                      </div>
                                  </div>

                              </div>
                          </TableData>
                          <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                              <!-- {{ vend.vendChannelsTotals.qty }}/ {{ vend.vendChannelsTotals.capacity }} <br>
                              ({{ vend.vendChannelsTotals.balancePercent }}%) -->
                          </TableData>
                          <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                              <!-- {{ vend.vendChannelsTotals.outOfStockSku }}/ {{ vend.vendChannelsTotals.count }} <br>
                              ({{ vend.vendChannelsTotals.outOfStockSkuPercent }}%) -->
                          </TableData>
                          <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                              {{ vend.temp_updated_at }}
                          </TableData>
                          <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-right">
                              {{ vend.coin_amount }}
                          </TableData>
                          <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                              {{ vend.serial_num }}
                          </TableData>
                          <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                              {{ vend.firmware_ver }}
                          </TableData>
                          <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                              {{ vend.is_door_open }}
                          </TableData>
                          <TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
                              {{ vend.is_sensor_normal }}
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
import { MagnifyingGlassIcon, BackspaceIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ref, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
  categories: Object,
  categoryGroups: Object,
  vends: Object,
  vendChannelErrors: Object,
})

const filters = ref({
  code: '',
  serialNum: '',
  customer_code: '',
  customer_name: '',
  categories: [],
  categoryGroups: [],
  tempHigherThan: '',
  vend_channel_error_id: '',
  sortKey: '',
  sortBy: true,
  numberPerPage: '',
})

const vendChannelErrorsOptions = ref([])
const numberPerPageOptions = ref([])
const categoryOptions = ref([])
const categoryGroupOptions = ref([])

onMounted(() => {
  vendChannelErrorsOptions.value = [
      {'id': '', 'desc': 'All'},
      {'id': 'errors_only', 'desc': 'Errors Only'},
      ...props.vendChannelErrors.data
  ]
  numberPerPageOptions.value = [
      { id: 100, value: 100 },
      { id: 200, value: 200 },
      { id: 500, value: 500 },
      { id: 'All', value: 'All' },
  ]
  filters.value.vend_channel_error_id = vendChannelErrorsOptions.value[0]
  filters.value.numberPerPage = numberPerPageOptions.value[0]

  categoryOptions.value = props.categories.data.map((data) => {return {id: data.id, name: data.name}})
  categoryGroupOptions.value = props.categoryGroups.data.map((data) => {return {id: data.id, name: data.name}})
})

function onSearchFilterUpdated() {
  router.get('/vends', {
      ...filters.value,
      vend_channel_error_id: filters.value.vend_channel_error_id.id,
      categories: filters.value.categories.map((category) => { return category.id }),
      categoryGroups: filters.value.categoryGroups.map((categoryGroup) => { return categoryGroup.id }),
      numberPerPage: filters.value.numberPerPage.id,
  }, {
      preserveState: true,
      replace: true,
  })
}

function onVendTempClicked(vendId) {
  router.get('/vends/' + vendId + '/temp')
}

function resetFilters() {
  router.get('/vends')
}

function sortTable(sortKey) {
  filters.value.sortKey = sortKey
  filters.value.sortBy = !this.filters.sortBy
  onSearchFilterUpdated()
}

</script>