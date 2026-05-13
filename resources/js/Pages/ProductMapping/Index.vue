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
          <SearchInput placeholderStr="Machine ID" v-model="filters.vend_code">
            Machine ID#
          </SearchInput>
          <SearchInput placeholderStr="Product" v-model="filters.product">
            Product
          </SearchInput>
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
              Active Product Mapping
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
							Machine Status
						</label>
						<MultiSelect
							v-model="filters.vendStatus"
							:options="vendStatusOptions"
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

      <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mt-4">
        <div class="bg-white overflow-hidden shadow rounded-lg">
          <div class="px-4 py-5 sm:p-6">
            <dt class="text-sm font-medium text-gray-500 truncate">
              Total Binded Machines
            </dt>
            <dd class="mt-1 text-3xl font-semibold text-gray-900">
              {{ totalBindedVends }}
            </dd>
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
                    <TableHeadSort modelName="name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('name', false)"  class="bg-sky-200">
                      <div class="flex flex-col space-y-1">
                        <span>Name</span>
                        <span class="text-black font-normal text-xs">Remarks</span>
                      </div>
                    </TableHeadSort>
                    <TableHead>
                      <div class="flex flex-col space-y-1">
                        <span>Upcoming Product Mapping</span>
                        <span class="text-black font-normal text-xs">Remarks</span>
                      </div>
                    </TableHead>
                    <TableHead>
                      Operator
                    </TableHead>
                    <TableHeadSort modelName="vend_prefix_name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_prefix_name', false)">
                      Binded Prefix
                    </TableHeadSort>
                    <TableHead>
                      Channel - Product
                    </TableHead>
                    <TableHead>
                      <div class="flex flex-col space-y-1">
                        <span>Binded Vending Machines</span>
                        <span class="text-black font-normal text-xs">(binded date)</span>
                      </div>
                    </TableHead>
                    <TableHead>
                      Menu
                    </TableHead>
                    <TableHead>
                    </TableHead>
                  </tr>
                </thead>
                  <tbody class="bg-white">
                    <tr v-for="(productMapping, productMappingIndex) in productMappings.data" :key="productMapping.id" class="divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100">
                      <TableData :currentIndex="productMappingIndex" :totalLength="productMappings.length" inputClass="text-center">
                        {{ productMappings.meta.from + productMappingIndex }}
                      </TableData>
                      <TableData :currentIndex="productMappingIndex" :totalLength="productMappings.length" inputClass="text-left bg-sky-50">
                        <div class="flex flex-col space-y-1">
                          <span>
                            <!-- {{ productMapping }} -->
                            {{ productMapping.name }}
                          </span>
                          <div
                              class="inline-flex justify-center items-center rounded px-0.5 py-0.5 text-xs border w-fit hover:cursor-pointer"
                              :class="productMapping.is_active ? 'bg-green-100 text-green-800 border-green-300' : 'bg-red-100 text-red-800 border-red-300'"
                          >
                              <div class="flex flex-col">
                                  <span class="font-semibold grow-0">
                                    {{ productMapping.is_active ? 'Active' : 'Inactive' }}
                                  </span>
                              </div>
                          </div>
                          <span class="text-gray-500 text-xs whitespace-pre-wrap" v-if="productMapping.remarks">
                            {{ productMapping.remarks }}
                          </span>
                        </div>
                      </TableData>
                      <TableData :currentIndex="productMappingIndex" :totalLength="productMappings.length" inputClass="text-left">
                        <div class="flex flex-col space-y-1" v-if="productMapping.upcomingProductMapping">
                          <span>
                            {{ productMapping.upcomingProductMapping.name }}
                          </span>
                          <span class="text-gray-500 text-xs whitespace-pre-wrap" v-if="productMapping.upcomingProductMapping.remarks">
                            {{ productMapping.upcomingProductMapping.remarks }}
                          </span>
                        </div>
                      </TableData>
                      <TableData :currentIndex="productMappingIndex" :totalLength="productMappings.length" inputClass="text-center">
                        <span v-if="productMapping.operator">
                          {{ productMapping.operator.code }}
                        </span>
                      </TableData>
                      <TableData :currentIndex="productMappingIndex" :totalLength="productMappings.length" inputClass="text-left">
                        <ul class="divide-y divide-gray-200">
                          <li class="flex py-1 px-3 space-x-2" v-for="(vendPrefix, vendPrefixIndex) in productMapping.vendPrefixes">
                            <span>
                              {{ vendPrefixIndex + 1 }}.
                            </span>
                            <span class="text-md pr-2">
                              {{ vendPrefix.name }}
                            </span>
                          </li>
                        </ul>
                      </TableData>
                      <TableData :currentIndex="productMappingIndex" :totalLength="productMappings.length" inputClass="text-left">
                        <ul class="divide-y divide-gray-200">
                          <li class="flex py-1 px-3 space-x-2" v-for="(productMappingItem, productMappingItemIndex) in productMapping.productMappingItems">
                            <span>
                              {{ productMappingItemIndex + 1 }}.
                            </span>
                            <span class="text-blue-700 text-md pr-2">
                              {{ productMappingItem.channel_code }}
                            </span>
                            <span v-if="productMappingItem.product && productMappingItem.product.code">
                              {{ productMappingItem.product.code }}
                            </span>
                            <span>
                              - {{ productMappingItem.product.name }}
                            </span>

                          </li>
                        </ul>
                      </TableData>
                      <TableData :currentIndex="productMappingIndex" :totalLength="productMappings.length" inputClass="text-left">
                        <div class="flex flex-col space-y-1">
                          <span class="text-center text-indigo-600 p-2 text-xs">
                            {{ productMapping.vends.length }} Machine(s)
                          </span>
                          <ul class="divide-y divide-gray-200">
                            <li class="flex py-1 px-3 space-x-2" v-for="(vend, vendIndex) in productMapping.vends">
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
                                          {{ vend.customer.virtual_customer_code }} ({{ vend.vendPrefix ? vend.vendPrefix.name : '' }})
                                          <br>
                                          {{ vend.customer.name }} <span class="text-black text-xs" v-if="vend.binded_at">({{ moment(vend.binded_at).format('YYMMDD') }})</span>
                                      </a>
                                  </span>
                                  <span v-else>
                                      {{ vend.customer.virtual_customer_code }} ({{ vend.vendPrefix ? vend.vendPrefix.name : '' }})
                                      <br>
                                      {{ vend.customer.name }} <span class="text-black text-xs" v-if="vend.binded_at">({{ moment(vend.binded_at).format('YYMMDD') }})</span>
                                  </span>
                              </span>
                              <span v-else-if="vend.customer && !vend.customer.person_id">
                                  <span v-if="permissions.includes('admin-access vends')" :class="[vend.customer.is_active ? 'text-gray-800' : 'text-gray-400']">
                                      <!-- <a class="text-blue-700" target="_blank" :href="'//admin.happyice.com.sg/person/' + vend.person_id + '/edit'"> -->
                                          {{ vend.customer.name }} <span class="text-black text-xs" v-if="vend.binded_at">({{ moment(vend.binded_at).format('YYMMDD') }})</span>
                                      <!-- </a> -->
                                  </span>
                                  <span v-else :class="[vend.customer.is_active ? 'text-gray-800' : 'text-gray-400']">
                                      {{ vend.customer.name }} <span class="text-black text-xs" v-if="vend.binded_at">({{ moment(vend.binded_at).format('YYMMDD') }})</span>
                                  </span>
                              </span>
                            </li>
                          </ul>
                        </div>
                      </TableData>
                      <TableData :currentIndex="productMappingIndex" :totalLength="productMappings.length" inputClass="text-left">
                        <span v-if="productMapping.attachments && productMapping.attachments.length > 0">
                          <a :href="productMapping.attachments[0].full_url" target="_blank">
                            <img class="aspect-[3/2] rounded-2xl object-scale-down h-48 w-96" :src="productMapping.attachments[0].full_url" alt="" />
                          </a>
                        </span>
                      </TableData>
                      <TableData :currentIndex="productMappingIndex" :totalLength="productMappings.length" inputClass="text-center">
                        <div class="flex justify-center flex-col space-y-1" v-if="permissions.includes('update product-mappings')">
                          <!-- <Button
                            type="button" class="bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
                            @click="onEditClicked(productMapping)"
                          >
                            <PencilSquareIcon class="w-4 h-4"></PencilSquareIcon>
                            <span>
                                Edit
                            </span>
                          </Button> -->
                          <span>
                            <Button
                            type="button" class="bg-sky-300 hover:bg-sky-400 px-3 py-2 text-xs text-sky-800 flex space-x-1 w-fit"
                            @click="onAttachmentOverviewClicked(productMapping)"
                            v-if="productMapping.attachments && productMapping.attachments.length > 0"
                            >
                              <PhotoIcon class="h-4 w-4" aria-hidden="true"/>
                            </Button>
                          </span>
                          <Link :href="'/product-mappings/' + productMapping.id + '/edit'">
                            <Button
                              type="button" class="bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
                            >
                              <PencilSquareIcon class="w-4 h-4"></PencilSquareIcon>
                              <span>
                                  Edit
                              </span>
                            </Button>
                          </Link>
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
                            :class="[productMapping.vends && productMapping.vends.length > 0 ? 'opacity-50 cursor-not-allowed' : '']"
                            @click="onDeleteClicked(productMapping)"
                            :disabled="productMapping.vends && productMapping.vends.length > 0"
                            v-if="productMapping.operator_id"
                          >
                            <span class="flex space-x-1 items-center">
                              <TrashIcon class="w-4 h-4"></TrashIcon>
                              <span>
                                  Delete
                              </span>
                            </span>
                            <span v-if="productMapping.vends && productMapping.vends.length > 0">
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
  <AttachmentOverview
    v-if="showAttachmentOverviewModal"
    :showModal="showAttachmentOverviewModal"
    @modalClose="onAttachmentOverviewModalClose"
    :model="productMapping"
    :items="attachments"
  >
  </AttachmentOverview>
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
      :productMappingOptions="productMappingOptions"
      :showModal="showVendFormModal"
      :unbindedVends="unbindedVends"
      @modalClose="onVendFormModalClose"
  >

  </VendForm>

  </BreezeAuthenticatedLayout>
</template>

<script setup>
import AttachmentOverview from '@/Components/AttachmentOverview.vue';
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import Form from '@/Pages/ProductMapping/Form.vue';
import VendForm from '@/Pages/ProductMapping/VendForm.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { BackspaceIcon, LinkIcon, MagnifyingGlassIcon, PhotoIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import TableData from '@/Components/TableData.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";
import moment from 'moment';

const props = defineProps({
  cmsEndpoint: String,
  products: Object,
  productMappings: Object,
  productMappingOptions: Object,
  unbindedVends: Object,
  vendPrefixOptions: Object,
  totalBindedVends: Number,
})

const filters = ref({
  is_active: true,
  name: '',
  product: '',
  vend_code: '',
  vendStatus: '',
  sortKey: '',
  sortBy: true,
  numberPerPage: 100,
  vendPrefixes: [],
})
const attachments = ref([])
const booleanOptions = ref([])
const showAttachmentOverviewModal = ref(false)
const showModal = ref(false)
const showVendFormModal = ref(false)
const productMapping = ref()
const type = ref('')
const toast = useToast()
const numberPerPageOptions = ref([])
const roles = usePage().props.auth.roles
const permissions = usePage().props.auth.permissions
const vendPrefixOptions = ref([])
const vendStatusOptions = ref([])

onMounted(() => {
  booleanOptions.value = [
    {id: 'true', value: 'Yes'},
    {id: 'false', value: 'No'},
  ]
  numberPerPageOptions.value = [
    { id: 100, value: 100 },
    { id: 200, value: 200 },
    { id: 500, value: 500 },
    { id: 'All', value: 'All' },
  ]
  vendPrefixOptions.value = [
    { id: '', value: 'All' },
    {id: 'single-ud', value: 'Single UD'},
    ...props.vendPrefixOptions.data.map((data) => {return {id: data.id, value: data.name}})
  ]
  vendStatusOptions.value = [
			{id: 'all', value: 'All'},
			{id: 'factory', value: 'Factory (JB)'},
			{id: 'active', value: 'Active'},
			{id: 'inactive', value: 'Not Active'},
			{id: 'disposed', value: 'Disposed'},
      {id: 'sold', value: 'Sold'},
	]
  filters.value.is_active = booleanOptions.value[0]
  filters.value.numberPerPage = numberPerPageOptions.value[0]
  filters.value.vendStatus = vendStatusOptions.value[2]
})

function onAttachmentOverviewClicked(model) {
  attachments.value = model.attachments
  showAttachmentOverviewModal.value = true
}

function onAttachmentOverviewModalClose() {
  showAttachmentOverviewModal.value = false
}

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
  router.delete('/product-mappings/' + productMapping.id, {
    onSuccess: () => {
      toast.success("Product mapping deleted successfully", { timeout: 3000 })
    },
    onError: () => {
      toast.error("Failed to delete product mapping", { timeout: 3000 })
    }
  })
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
      is_active: filters.value.is_active.id,
      vendStatus: filters.value.vendStatus.id,
      numberPerPage: filters.value.numberPerPage.id,
      vendPrefixes: filters.value.vendPrefixes.map((vendPrefix) => { return vendPrefix.id }),
  }, {
      preserveState: true,
      replace: true,
  })
}


function resetFilters() {
  router.get('/product-mappings')
}

function sortTable(sortKey, inverse = false) {
  filters.value.sortBy = !filters.value.sortBy
  if(inverse && filters.value.sortKey != sortKey) {
      filters.value.sortBy = !filters.value.sortBy
  }
  filters.value.sortKey = sortKey
  onSearchFilterUpdated()
}

function onModalClose() {
  showModal.value = false
}

function onVendFormModalClose() {
  showVendFormModal.value = false
}
</script>