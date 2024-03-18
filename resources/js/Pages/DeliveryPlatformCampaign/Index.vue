<template>

  <Head title="Delivery Campaign" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Delivery Platform Campaign
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
        <div class="flex justify-end">
          <Link :href="'/delivery-platform-campaigns/create'">
            <Button
              type="button" class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            >
              <PlusIcon class="h-4 w-4" aria-hidden="true"/>
              <span>
                Create
              </span>
            </Button>
          </Link>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
          <SearchInput placeholderStr="Vend ID" v-model="filters.vend_code">
            Vend ID
          </SearchInput>
          <DatePicker
              v-model="filters.date_from"
          >
              From
          </DatePicker>
          <DatePicker
              v-model="filters.date_to"
              :minDate="filters.date_from"
          >
              To
          </DatePicker>
          <div>
            <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
              Delivery Platform
            </label>
            <MultiSelect
              v-model="filters.delivery_platform_operator_id"
              :options="deliveryPlatformOperatorOptions"
              trackBy="id"
              valueProp="id"
              label="name"
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
                  <span class="font-medium">{{ deliveryPlatformCampaigns.meta.from ?? 0 }}</span>
                  <span>to</span>
                  <span class="font-medium">{{ deliveryPlatformCampaigns.meta.to ?? 0 }}</span>
                  <span>of</span>
                  <span class="font-medium">{{ deliveryPlatformCampaigns.meta.total }}</span>
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
                    <TableHead>
                      Platform
                    </TableHead>
                    <TableHead>
                      Name
                    </TableHead>
                    <TableHead>
                      Status
                    </TableHead>
                    <TableHead>
                      Campaign Item(s)
                    </TableHead>
                    <TableHead>
                      Product Mapping
                    </TableHead>
                    <TableHead>
                    </TableHead>
                  </tr>
                </thead>
                <tbody class="bg-white">
                  <tr v-for="(deliveryPlatformCampaign, deliveryPlatformCampaignIndex) in deliveryPlatformCampaigns.data" :key="deliveryPlatformCampaign.id" class="divide-x divide-gray-200">
                    <TableData :currentIndex="deliveryPlatformCampaignIndex" :totalLength="deliveryPlatformCampaigns.length" inputClass="text-center">
                      {{ deliveryPlatformCampaigns.meta.from + deliveryPlatformCampaignIndex }}
                    </TableData>
                    <TableData :currentIndex="deliveryPlatformCampaignIndex" :totalLength="deliveryPlatformCampaigns.length" inputClass="text-center">
                      {{ deliveryPlatformCampaign.deliveryPlatformOperator.deliveryPlatform.name }} ({{ deliveryPlatformCampaign.deliveryPlatformOperator.type }})
                    </TableData>
                    <TableData :currentIndex="deliveryPlatformCampaignIndex" :totalLength="deliveryPlatformCampaigns.length" inputClass="text-center">
                      {{ deliveryPlatformCampaign.name }}
                    </TableData>
                    <TableData :currentIndex="deliveryPlatformCampaignIndex" :totalLength="deliveryPlatformCampaigns.length" inputClass="text-center">
                      {{ deliveryPlatformCampaign.is_active ? 'Active' : 'Not Active' }}
                    </TableData>
                    <TableData :currentIndex="deliveryPlatformCampaignIndex" :totalLength="deliveryPlatformCampaigns.length" inputClass="text-left">
                      <ul class="divide-y divide-gray-200">
                        <li class="flex flex-col py-1 px-3 space-x-2" v-for="deliveryPlatformCampaignItem in deliveryPlatformCampaign.deliveryPlatformCampaignItems">
                          <span class="text-blue-700">
                            {{ deliveryPlatformCampaignItem.settings_label }}
                          </span>
                          <ul class="divide-y divide-gray-200 pl-3">
                            <li class="flex py-1 px-3 space-x-2" v-for="item in deliveryPlatformCampaignItem.items_json">
                              <span v-if="item && 'full_name' in item">
                                {{ item.full_name ? item.full_name : item.name }}
                              </span>
                            </li>
                          </ul>
                        </li>
                      </ul>
                    </TableData>
                    <TableData :currentIndex="deliveryPlatformCampaignIndex" :totalLength="deliveryPlatformCampaigns.length" inputClass="text-center">
                      {{ deliveryPlatformCampaign.deliveryProductMapping.name }}
                    </TableData>
                    <TableData :currentIndex="deliveryPlatformCampaignIndex" :totalLength="deliveryPlatformCampaigns.length" inputClass="text-center">
                      <div class="flex justify-center space-x-1">
                        <Link :href="'/delivery-platform-campaigns/' + deliveryPlatformCampaign.id + '/edit'">
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
                  <tr v-if="!deliveryPlatformCampaigns.data.length">
                    <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                        No Results Found
                    </td>
                  </tr>
                </tbody>
            </table>
            <Paginator v-if="deliveryPlatformCampaigns.data.length" :links="deliveryPlatformCampaigns.links" :meta="deliveryPlatformCampaigns.meta"></Paginator>
          </div>
      </div>
    </div>
  </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import DatePicker from '@/Components/DatePicker.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import moment from 'moment';
import Complaint from '@/Pages/DeliveryPlatformOrder/Complaint.vue';
import { BackspaceIcon, MagnifyingGlassIcon, PencilSquareIcon, PlusIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ref, onMounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
  deliveryPlatformCampaigns: Object,
  deliveryPlatformOperatorOptions: Object,
  deliveryPlatformOrderStatusOptions: Object,
  totals: Object,
})

const filters = ref({
  order_id: '',
  short_order_id: '',
  vend_code: '',
  date_from: moment().format('YYYY-MM-DD'),
  date_to: moment().format('YYYY-MM-DD'),
  delivery_platform_operator_id: '',
  has_complaint: 'all',
  sortKey: '',
  sortBy: false,
  status: '',
  numberPerPage: 100,
})
const deliveryPlatformOperatorOptions = ref([])
const operatorCountry = usePage().props.auth.operatorCountry
const numberPerPageOptions = ref([])
const permissions = usePage().props.auth.permissions

onMounted(() => {
  numberPerPageOptions.value = [
    { id: 100, value: 100 },
    { id: 200, value: 200 },
    { id: 500, value: 500 },
    { id: 'All', value: 'All' },
  ]
  filters.value.numberPerPage = numberPerPageOptions.value[0]
  deliveryPlatformOperatorOptions.value = [
    { id: 'all', name: 'All' },
    ...props.deliveryPlatformOperatorOptions.data.map((data) => {
    return {id: data.id, name: data.deliveryPlatform.name + ' (' + data.type + ')'}})
  ]
  filters.value.delivery_platform_operator_id = deliveryPlatformOperatorOptions.value[0]
})

function onSearchFilterUpdated() {
  router.get('/delivery-platform-orders', {
      ...filters.value,
      delivery_platform_operator_id: filters.value.delivery_platform_operator_id.id,
      status: filters.value.status.id,
      has_complaint: filters.value.has_complaint.id,
      numberPerPage: filters.value.numberPerPage.id,
  }, {
      preserveState: true,
      replace: true,
  })
}

function resetFilters() {
  router.get('/delivery-platform-orders')
}

function sortTable(sortKey) {
  filters.value.sortKey = sortKey
  filters.value.sortBy = !filters.value.sortBy
  onSearchFilterUpdated()
}

</script>