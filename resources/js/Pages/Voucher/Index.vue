<template>

  <Head title="Voucher Management" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Voucher Management
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-1 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
        <div class="flex justify-end space-x-2 pb-3">
          <Link href="/vouchers/create/same">
            <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            >
              <PlusIcon class="h-4 w-4" aria-hidden="true"/>
              <span>
                Batch Create (Same Code)
              </span>
            </Button>
          </Link>
          <Link href="/vouchers/create/unique">
            <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            >
              <PlusIcon class="h-4 w-4" aria-hidden="true"/>
              <span>
                Batch Create (Unique Code)
              </span>
            </Button>
          </Link>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
          <SearchInput placeholderStr="Name" v-model="filters.name">
            Name
          </SearchInput>
          <SearchInput placeholderStr="Code" v-model="filters.code">
            Code
          </SearchInput>
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
              Status
            </label>
            <MultiSelect
              v-model="filters.is_active"
              :options="activeOptions"
              trackBy="id"
              valueProp="id"
              label="value"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
            ></MultiSelect>
          </div>
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
              Is Same Voucher Code?
            </label>
            <MultiSelect
              v-model="filters.is_batch_code"
              :options="booleanOptions"
              trackBy="id"
              valueProp="id"
              label="value"
              placeholder="Select"
              open-direction="bottom"
              class="mt-1"
            ></MultiSelect>
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
                  <span class="font-medium">{{ vouchers.meta.from ?? 0 }}</span>
                  <span>to</span>
                  <span class="font-medium">{{ vouchers.meta.to ?? 0 }}</span>
                  <span>of</span>
                  <span class="font-medium">{{ vouchers.meta.total }}</span>
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
                      Desc
                    </TableHead>
                    <TableHeadSort modelName="is_active" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('is_active')">
                      Is Active?
                    </TableHeadSort>
                    <TableHeadSort modelName="is_batch_code" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('is_batch_code')">
                      Is Same <br>
                      Voucher Code?
                    </TableHeadSort>
                    <TableHead>
                      Code
                    </TableHead>
                    <TableHead>
                      Qty
                    </TableHead>
                    <TableHeadSort modelName="date_from" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('date_from')">
                      Date From
                    </TableHeadSort>
                    <TableHeadSort modelName="date_to" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('date_to')">
                      Date To
                    </TableHeadSort>
                    <TableHeadSort modelName="type" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('type')">
                      Type
                    </TableHeadSort>
                    <TableHead>
                      Settings
                    </TableHead>
                    <TableHead>
                    </TableHead>
                  </tr>
                </thead>
                  <tbody class="bg-white">
                    <tr v-for="(voucher, voucherIndex) in vouchers.data" :key="voucher.id" class="divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100">
                      <TableData :currentIndex="voucherIndex" :totalLength="vouchers.length" inputClass="text-center">
                        {{ vouchers.meta.from + voucherIndex }}
                      </TableData>
                      <TableData :currentIndex="voucherIndex" :totalLength="vouchers.length" inputClass="text-left">
                        {{ voucher.name }}
                      </TableData>
                      <TableData :currentIndex="voucherIndex" :totalLength="vouchers.length" inputClass="text-left whitespace-pre-line">
                        {{ voucher.desc }}
                      </TableData>
                      <TableData :currentIndex="voucherIndex" :totalLength="vouchers.length" inputClass="text-left">
                        <div class="flex justify-center">
                            <CheckCircleIcon class="h-4 w-4 text-green-500" aria-hidden="true" v-if="voucher.is_active"/>
                            <XCircleIcon class="h-4 w-4 text-red-500" aria-hidden="true" v-if="!voucher.is_active"/>
                        </div>
                      </TableData>
                      <TableData :currentIndex="voucherIndex" :totalLength="vouchers.length" inputClass="text-left">
                        <div class="flex justify-center">
                            <CheckCircleIcon class="h-4 w-4 text-green-500" aria-hidden="true" v-if="!voucher.is_batch_code"/>
                            <XCircleIcon class="h-4 w-4 text-red-500" aria-hidden="true" v-if="voucher.is_batch_code"/>
                        </div>
                      </TableData>
                      <TableData :currentIndex="voucherIndex" :totalLength="vouchers.length" inputClass="text-center">
                        {{ voucher.code_formatted }}
                      </TableData>
                      <TableData :currentIndex="voucherIndex" :totalLength="vouchers.length" inputClass="text-center">
                        {{ voucher.qty }}
                      </TableData>
                      <TableData :currentIndex="voucherIndex" :totalLength="vouchers.length" inputClass="text-center">
                        {{ voucher.date_from_formatted }}
                      </TableData>
                      <TableData :currentIndex="voucherIndex" :totalLength="vouchers.length" inputClass="text-center">
                        {{ voucher.date_to_formatted }}
                      </TableData>
                      <TableData :currentIndex="voucherIndex" :totalLength="vouchers.length" inputClass="text-center">
                        {{ voucher.type_name }}
                      </TableData>
                      <TableData :currentIndex="voucherIndex" :totalLength="vouchers.length" inputClass="text-left">
                        <div class="flex flex-col space-y-2">
                          <span v-if="voucher.value">
                            Value: {{ voucher.type == 'percent' ? '' : operatorCountry.currency_symbol }} {{ voucher.value }} {{ voucher.type == 'percent' ? '%' : '' }} <br>
                          </span>
                          <span v-if="voucher.min_value">
                            Min Basket Value: {{ operatorCountry.currency_symbol }} {{ voucher.min_value }} <br>
                          </span>
                          <span v-if="voucher.max_promo_value">
                            Max Promo Value: {{ operatorCountry.currency_symbol }} {{ voucher.max_promo_value }} <br>
                          </span>
                          <span v-if="voucher.product_json_mapped">
                            <span v-for="product in voucher.product_json_mapped">
                              {{ product.code }} - {{ product.name }} <br>
                            </span>
                          </span>
                        </div>
                      </TableData>
                      <TableData :currentIndex="voucherIndex" :totalLength="vouchers.length" inputClass="text-center">
                        <div class="flex justify-center space-x-1">

                          <Link :href="'/vouchers/edit/' + voucher.id ">
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
                            type="button"
                            class="bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex-col space-y-1 w-fit"
                            :class="[voucher.vends && voucher.vends.length > 0 ? 'opacity-50 cursor-not-allowed' : '']"
                            @click="onDeleteClicked(voucher)"
                            :disabled="voucher.vends && voucher.vends.length > 0"
                          >
                            <span class="flex space-x-1 items-center">
                              <TrashIcon class="w-4 h-4"></TrashIcon>
                              <span>
                                  Delete
                              </span>
                            </span>
                            <span v-if="voucher.vends && voucher.vends.length > 0">
                              (Binded)
                            </span>
                          </Button> -->
                        </div>
                      </TableData>
                      </tr>
                <tr v-if="!vouchers.data.length">
                  <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                      No Results Found
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator v-if="vouchers.data.length" :links="vouchers.links" :meta="vouchers.meta"></Paginator>
          </div>
      </div>
    </div>
  </div>
  <Form
      v-if="showModal"
      :isUnique="isUnique"
      :voucher="voucher"
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
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { BackspaceIcon, CheckCircleIcon, MagnifyingGlassIcon, PencilSquareIcon, PlusIcon, TrashIcon, XCircleIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ref, onMounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
  vouchers: Object,
})

const filters = ref({
  name: '',
  sortKey: '',
  sortBy: false,
  numberPerPage: 100,
})
const activeOptions = ref([])
const booleanOptions = ref([])
const isUnique = ref(false)
const operatorCountry = usePage().props.auth.operatorCountry
const showModal = ref(false)
const voucher = ref()
const type = ref('')
const numberPerPageOptions = ref([])

onMounted(() => {
  activeOptions.value = [
    { id: 'all', value: 'All' },
    { id: 'true', value: 'Active' },
    { id: 'false', value: 'Expired' },
  ];

  booleanOptions.value = [
    { id: 'all', value: 'All' },
    { id: 'true', value: 'Yes' },
    { id: 'false', value: 'No' },
  ];

  numberPerPageOptions.value = [
    { id: 100, value: 100 },
    { id: 200, value: 200 },
    { id: 500, value: 500 },
    { id: 'All', value: 'All' },
  ]
  filters.value.numberPerPage = numberPerPageOptions.value[0]
})

function onCreateClicked(isUniqueObj) {
  isUnique.value = isUniqueObj == 1 ? true : false
  type.value = 'create'
  voucher.value = null
  showModal.value = true
}

function onDeleteClicked(voucher) {
  const approval = confirm('Are you sure to delete ' + voucher.name + '?');
  if (!approval) {
      return;
  }
  router.delete('/vouchers/' + voucher.id)
}

function onEditClicked(telcoValue) {
  type.value = 'update'
  voucher.value = telcoValue
  showModal.value = true
}

function onSearchFilterUpdated() {
  router.get('/vouchers', {
      ...filters.value,
      is_active: filters.value.is_active ? filters.value.is_active.id : '',
      is_batch_code: filters.value.is_batch_code ? filters.value.is_batch_code.id : '',
      numberPerPage: filters.value.numberPerPage.id,
  }, {
      preserveState: true,
      replace: true,
  })
}

function resetFilters() {
  router.get('/vouchers')
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