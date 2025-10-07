<template>

  <Head title="Campaigns" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Campaigns
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
        <div class="flex justify-end mb-3">
          <Link :href="'/campaigns/create'">
            <Button
              type="button"
              class="inline-flex space-x-1 items-center rounded-md border bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white hover:bg-green-600"
            >
              <span>+ Create</span>
            </Button>
          </Link>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
          <SearchInput placeholderStr="Name" v-model="filters.name">
            Name
          </SearchInput>
					<div v-if="permissions.includes('admin-access vend-customers')">
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
                  <span class="font-medium">{{ campaigns.meta.from ?? 0 }}</span>
                  <span>to</span>
                  <span class="font-medium">{{ campaigns.meta.to ?? 0 }}</span>
                  <span>of</span>
                  <span class="font-medium">{{ campaigns.meta.total }}</span>
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
                      Name
                    </TableHead>
                    <TableHead>
                      Active
                    </TableHead>
                    <TableHead>
                      Operator
                    </TableHead>
                    <TableHead>
                      Start
                    </TableHead>
                    <TableHead>
                      End
                    </TableHead>
                    <TableHead>
                      Remarks
                    </TableHead>
                  </tr>
                </thead>
                <tbody class="bg-white">
                  <tr v-for="(campaign, campaignIndex) in campaigns.data" :key="campaign.id" class="divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100">
                    <TableData :currentIndex="campaignIndex" :totalLength="campaigns.length" inputClass="text-center">
                      {{ campaigns.meta.from + campaignIndex }}
                    </TableData>
                    <TableData :currentIndex="campaignIndex" :totalLength="campaigns.length" inputClass="text-center">
                      {{ campaign.name }}
                    </TableData>
                    <TableData :currentIndex="campaignIndex" :totalLength="campaigns.length" inputClass="text-center">
                      {{ campaign.is_active ? 'Yes' : 'No' }}
                    </TableData>
                    <TableData :currentIndex="campaignIndex" :totalLength="campaigns.length" inputClass="text-center">
                      {{ campaign.operator?.full_name ?? '-' }}
                    </TableData>
                    <TableData :currentIndex="campaignIndex" :totalLength="campaigns.length" inputClass="text-center">
                      {{ campaign.start_at ?? '-' }}
                    </TableData>
                    <TableData :currentIndex="campaignIndex" :totalLength="campaigns.length" inputClass="text-center">
                      {{ campaign.end_at ?? '-' }}
                    </TableData>
                    <TableData :currentIndex="campaignIndex" :totalLength="campaigns.length" inputClass="text-center">
                      {{ campaign.remarks ?? '' }}
                    </TableData>
                  </tr>
                  <tr v-if="!campaigns.data.length">
                    <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                        No Results Found
                    </td>
                  </tr>
                </tbody>
            </table>
            <Paginator v-if="campaigns.data.length" :links="campaigns.links" :meta="campaigns.meta"></Paginator>
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
import { BackspaceIcon, MagnifyingGlassIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import { ref, onMounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';

const props = defineProps({
  campaigns: Object,
  operatorOptions: Object,
})

const filters = ref({
  name: '',
  operators: [],
  sortKey: 'created_at',
  sortBy: false,
  numberPerPage: 100,
})
const operatorOptions = ref([])
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
  operatorOptions.value = [
    {id: 'all', full_name: 'All'},
    ...props.operatorOptions.data.map((data) => {return {id: data.id, full_name: data.full_name}})
  ]
  // Default selection to 'All' for tags mode (expects array)
  filters.value.operators = [operatorOptions.value[0]]
})

function onSearchFilterUpdated() {
  const selected = Array.isArray(filters.value.operators) ? filters.value.operators : []
  const hasAll = selected.find(o => o?.id === 'all')
  const operatorsParam = hasAll || selected.length === 0 ? 'all' : selected.map(o => o.id)

  router.get('/campaigns', {
    name: filters.value.name,
    operators: operatorsParam,
    sortKey: filters.value.sortKey,
    sortBy: filters.value.sortBy,
    numberPerPage: filters.value.numberPerPage.id,
  }, { preserveState: true, replace: true })
}

function resetFilters() {
  router.get('/campaigns')
}

function sortTable(sortKey) {
  filters.value.sortKey = sortKey
  filters.value.sortBy = !filters.value.sortBy
  onSearchFilterUpdated()
}

</script>
