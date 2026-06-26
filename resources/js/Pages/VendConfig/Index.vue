<template>

  <Head title="Menu Config" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Setting Charts
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
        <div class="flex justify-end">
          <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
          @click="onCreateClicked()"
          >
            <PlusIcon class="h-4 w-4" aria-hidden="true"/>
            <span>
              Create
            </span>
          </Button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
          <SearchInput placeholderStr="Name" v-model="filters.name">
            Name
          </SearchInput>
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
              Version
            </label>
            <MultiSelect
              v-model="filters.version"
              :options="versionOptions"
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
          <div>
            <label for="text" class="block text-sm font-medium text-gray-700">
              Setting Chart Status
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
                  <span class="font-medium">{{ vendConfigs.meta.from ?? 0 }}</span>
                  <span>to</span>
                  <span class="font-medium">{{ vendConfigs.meta.to ?? 0 }}</span>
                  <span>of</span>
                  <span class="font-medium">{{ vendConfigs.meta.total }}</span>
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
                    <TableHeadSort modelName="name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('name')" class="bg-sky-200">
                      Name
                    </TableHeadSort>
                    <TableHeadSort modelName="version" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('version')">
                      Latest Version
                    </TableHeadSort>
                    <TableHead>
                      Desc
                    </TableHead>
                    <TableHead>
                      Compatible
                    </TableHead>
                    <TableHead>
                      Machine Count
                    </TableHead>
                    <TableHead>
                      Latest Attachment
                    </TableHead>
                    <TableHead>
                      Machine Prefixes
                    </TableHead>
                    <TableHead>
                    </TableHead>
                  </tr>
                </thead>
                  <tbody class="bg-white">
                    <tr v-for="(vendConfig, vendConfigIndex) in vendConfigs.data" :key="vendConfig.id" class="divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100">
                      <TableData :currentIndex="vendConfigIndex" :totalLength="vendConfigs.length" inputClass="text-center">
                        {{ vendConfigs.meta.from + vendConfigIndex }}
                      </TableData>
                      <TableData :currentIndex="vendConfigIndex" :totalLength="vendConfigs.length" inputClass="text-left bg-sky-50">
                        <div class="flex flex-col space-y-1">
                          {{ vendConfig.name }}
                          <div
                            class="inline-flex justify-center items-center rounded px-0.5 py-0.5 text-xs border w-fit hover:cursor-pointer"
                            :class="vendConfig.is_active ? 'bg-green-100 text-green-800 border-green-300' : 'bg-red-100 text-red-800 border-red-300'"
                          >
                            <div class="flex flex-col">
                                <span class="font-semibold grow-0">
                                  {{ vendConfig.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                          </div>
                        </div>
                      </TableData>
                      <TableData :currentIndex="vendConfigIndex" :totalLength="vendConfigs.length" inputClass="text-center">
                        {{ vendConfig.version }}
                      </TableData>
                      <TableData :currentIndex="vendConfigIndex" :totalLength="vendConfigs.length" inputClass="text-left whitespace-pre-line">
                        {{ vendConfig.desc }}
                      </TableData>
                      <TableData :currentIndex="vendConfigIndex" :totalLength="vendConfigs.length" inputClass="text-left whitespace-pre-line">
                        <div v-if="vendConfig.vendConfigCompatibles">
                          <ul class="list-none">
                            <li v-for="vendConfigCompatible in vendConfig.vendConfigCompatibles" :key="vendConfigCompatible.id">
                              <a :href="'/vend-configs/' + vendConfigCompatible.id + '/edit'" target="_blank" class="text-blue-600">
                                {{ vendConfigCompatible.name }}
                              </a>
                            </li>
                          </ul>
                        </div>
                      </TableData>
                      <TableData :currentIndex="vendConfigIndex" :totalLength="vendConfigs.length" inputClass="text-center">
                        {{ vendConfig.vends_count }}
                      </TableData>
                      <TableData :currentIndex="vendConfigIndex" :totalLength="vendConfigs.length" inputClass="text-left">
                        <span v-if="vendConfig.attachments && vendConfig.attachments.length > 0">
                          <a :href="vendConfig.attachments[0].full_url" target="_blank">
                            <img class="aspect-[3/2] rounded-2xl object-scale-down h-48 w-96" :src="vendConfig.attachments[0].full_url" alt="" />
                          </a>
                        </span>
                      </TableData>
                      <TableData :currentIndex="vendConfigIndex" :totalLength="vendConfigs.length" inputClass="text-center">
                        <div v-if="vendConfig.vendPrefixes">
                          <ul class="list-none">
                            <li v-for="vendPrefix in vendConfig.vendPrefixes" :key="vendPrefix.id">
                                {{ vendPrefix.name }}
                            </li>
                          </ul>
                        </div>
                      </TableData>
                      <TableData :currentIndex="vendConfigIndex" :totalLength="vendConfigs.length" inputClass="">
                        <div class="flex flex-col space-y-1 justify-items-center">
                          <Button
                          type="button" class="bg-sky-300 hover:bg-sky-400 px-3 py-2 text-xs text-sky-800 flex space-x-1 w-fit"
                          @click="onAttachmentOverviewClicked(vendConfig)"
                          v-if="vendConfig.attachments && vendConfig.attachments.length > 0"
                          >
                          <!-- {{ vendConfig.attachments }} -->
                            <PhotoIcon class="h-4 w-4" aria-hidden="true"/>
                          </Button>
                          <Link :href="'/vend-configs/' + vendConfig.id + '/edit'">
                            <Button
                              type="button" class="bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1 w-fit"
                            >
                              <PencilSquareIcon class="w-4 h-4"></PencilSquareIcon>
                              <span>
                                  Edit
                              </span>
                            </Button>
                          </Link>

                          <Button
                            type="button"
                            class="bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex-col space-y-1 w-fit"
                            :class="[vendConfig.vendPrefixes && vendConfig.vendPrefixes.length > 0 ? 'opacity-50 cursor-not-allowed' : '']"
                            @click="onDeleteClicked(vendConfig)"
                            :disabled="vendConfig.vendPrefixes && vendConfig.vendPrefixes.length > 0"
                            v-if="vendConfig.operator_id"
                          >
                            <span class="flex space-x-1 items-center">
                              <TrashIcon class="w-4 h-4"></TrashIcon>
                              <span>
                                  Delete
                              </span>
                            </span>
                            <span v-if="vendConfig.vendPrefixes && vendConfig.vendPrefixes.length > 0">
                              (Binded)
                            </span>
                          </Button>
                        </div>
                      </TableData>
                      </tr>
                <tr v-if="!vendConfigs.data.length">
                  <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                      No Results Found
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator v-if="vendConfigs.data.length" :links="vendConfigs.links" :meta="vendConfigs.meta"></Paginator>
          </div>
      </div>
    </div>
  </div>
  <AttachmentOverview
    v-if="showAttachmentOverviewModal"
    :showModal="showAttachmentOverviewModal"
    @modalClose="onAttachmentOverviewModalClose"
    :model="vendConfig"
    :items="attachments"
  >
  </AttachmentOverview>

  <Form
      v-if="showModal"
      :operatorOptions="operatorOptions"
      :vendConfig="vendConfig"
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
import Form from '@/Pages/VendConfig/Form.vue';
import AttachmentOverview from '@/Components/AttachmentOverview.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { BackspaceIcon, MagnifyingGlassIcon, PhotoIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ref, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";

const props = defineProps({
  operatorOptions: [Array, Object],
  vendConfigs: Object,
  vendPrefixOptions: [Array, Object],
  versionOptions: [Array, Object],
})

const filters = ref({
  name: '',
  is_active: '',
  sortKey: '',
  sortBy: false,
  numberPerPage: 100,
  vendPrefixes: [],
  vendStatus: '',
  version: '',
})
const attachments = ref([])
const booleanOptions = ref([
    {id: 'true', value: 'Active'},
    {id: 'false', value: 'Inactive'},
])
const model = ref()
const showAttachmentOverviewModal = ref(false)
const showModal = ref(false)
const vendConfig = ref()
const type = ref('')
const toast = useToast()
const numberPerPageOptions = ref([])
const vendPrefixOptions = ref([])
const versionOptions = ref([])
const vendStatusOptions = ref([])

onMounted(() => {
  numberPerPageOptions.value = [
    { id: 100, value: 100 },
    { id: 200, value: 200 },
    { id: 500, value: 500 },
    { id: 'All', value: 'All' },
  ]
  vendPrefixOptions.value = [
    {id: 'single-ud', value: 'Single UD'},
    ...props.vendPrefixOptions.data.map((data) => {return {id: data.id, value: data.name}})
  ]
  versionOptions.value = [
    { id: 'all', value: 'All' },
    { id: '-', value: '-' },
    ...Object.entries(props.versionOptions).map(([id, version]) => ({id: version, value: version}))
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
  filters.value.version = versionOptions.value[0]
  filters.value.vendStatus = vendStatusOptions.value[2]
})

function onCreateClicked() {
  type.value = 'create'
  vendConfig.value = null
  showModal.value = true
}

function onAttachmentOverviewClicked(vendConfig) {
  attachments.value = vendConfig.attachments
  showAttachmentOverviewModal.value = true
}

function onDeleteClicked(vendConfig) {
  const approval = confirm('Are you sure to delete ' + vendConfig.name + '?');
  if (!approval) {
      return;
  }
  router.delete('/vend-configs/' + vendConfig.id, {
    onSuccess: () => {
      toast.success("Setting chart deleted successfully", { timeout: 3000 })
    },
    onError: () => {
      toast.error("Failed to delete setting chart", { timeout: 3000 })
    }
  })
}

function onEditClicked(telcoValue) {
  type.value = 'update'
  vendConfig.value = telcoValue
  showModal.value = true
}

function onSearchFilterUpdated() {
// console.log({...filters.value, is_active: filters.value.is_active ? filters.value.is_active.id : null, numberPerPage: filters.value.numberPerPage.id})
  router.get('/vend-configs', {
      ...filters.value,
      is_active: filters.value.is_active ? filters.value.is_active.id : null,
      vendStatus: filters.value.vendStatus.id,
      numberPerPage: filters.value.numberPerPage.id,
      vendPrefixes: filters.value.vendPrefixes.map((vendPrefix) => vendPrefix.id),
      version: filters.value.version.id,
  }, {
      preserveState: true,
      replace: true,
  })
}

function resetFilters() {
  router.get('/vend-configs')
}

function sortTable(sortKey) {
  filters.value.sortKey = sortKey
  filters.value.sortBy = !filters.value.sortBy
  onSearchFilterUpdated()
}

function onModalClose() {
  showModal.value = false
}

function onAttachmentOverviewModalClose() {
  showAttachmentOverviewModal.value = false
}
</script>