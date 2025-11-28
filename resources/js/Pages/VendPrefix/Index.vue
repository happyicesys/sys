<template>
  <Head title="Menu Prefix" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Machine Prefix
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
        <div class="flex justify-end">
          <Button
            class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
            @click="onCreateClicked()"
          >
            <PlusIcon class="h-4 w-4" aria-hidden="true" />
            <span>Create</span>
          </Button>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
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
              Setting Chart
            </label>
            <MultiSelect
              v-model="filters.vend_config_id"
              :options="vendConfigOptions"
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
              Current Mapping
            </label>
            <MultiSelect
              v-model="filters.product_mapping_id"
              :options="productMappingOptions"
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
              Upcoming Mapping
            </label>
            <MultiSelect
              v-model="filters.upcoming_product_mapping_id"
              :options="productMappingOptions"
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
        </div>

        <div class="flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5">
          <div class="mt-3">
            <div class="flex space-x-1">
              <Button
                class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                @click="onSearchFilterUpdated()"
              >
                <MagnifyingGlassIcon class="h-4 w-4" aria-hidden="true" />
                <span>Search</span>
              </Button>
              <Button
                class="inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                @click="resetFilters()"
              >
                <BackspaceIcon class="h-4 w-4" aria-hidden="true" />
                <span>Reset</span>
              </Button>
            </div>
          </div>
          <div class="flex flex-col space-y-2">
            <p class="text-sm text-gray-700 leading-5 flex space-x-1">
              <span>Showing</span>
              <span class="font-medium">{{ vendPrefixes.meta.from ?? 0 }}</span>
              <span>to</span>
              <span class="font-medium">{{ vendPrefixes.meta.to ?? 0 }}</span>
              <span>of</span>
              <span class="font-medium">{{ vendPrefixes.meta.total }}</span>
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
                  <TableHead>#</TableHead>
                  <TableHead>Setting Chart</TableHead>
                  <TableHeadSort
                    modelName="name"
                    :sortKey="filters.sortKey"
                    :sortBy="filters.sortBy"
                    @sort-table="sortTable('name')"
                    class="bg-sky-200"
                  >
                    Prefix
                  </TableHeadSort>
                  <TableHead>Desc</TableHead>
                  <TableHead>Product Mapping <br /> Current</TableHead>
                  <TableHead>Product Mapping <br /> Upcoming</TableHead>
                  <TableHead></TableHead>
                </tr>
              </thead>
              <tbody class="bg-white">
                <tr
                  v-for="(vendPrefix, vendPrefixIndex) in vendPrefixes.data"
                  :key="vendPrefix.id"
                  class="divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100"
                >
                  <TableData
                    :currentIndex="vendPrefixIndex"
                    :totalLength="vendPrefixes.length"
                    inputClass="text-center"
                  >
                    {{ vendPrefixes.meta.from + vendPrefixIndex }}
                  </TableData>
                  <TableData
                    :currentIndex="vendPrefixIndex"
                    :totalLength="vendPrefixes.length"
                    inputClass="text-center"
                  >
                    <div v-if="vendPrefix.vendConfigs">
                      <span
                        v-for="vendConfig in vendPrefix.vendConfigs"
                        class="flex space-x-2 space-y-1 items-center"
                      >
                        <Link
                          :href="'/vend-configs/' + vendConfig.id + '/edit'"
                          class="text-blue-600"
                          target="_blank"
                        >
                          {{ vendConfig.name }}
                        </Link>
                        <PhotoIcon
                          class="h-5 w-5 bg-sky-400 mx-1 my-1 py-1 px-1 rounded-full hover:cursor-pointer hover:bg-sky-500 hover:text-white shadow"
                          aria-hidden="true"
                          @click="onAttachmentOverviewClicked(vendConfig)"
                          v-if="vendConfig.attachments && vendConfig.attachments.length > 0"
                        />
                      </span>
                    </div>
                  </TableData>
                  <TableData
                    :currentIndex="vendPrefixIndex"
                    :totalLength="vendPrefixes.length"
                    inputClass="text-center bg-sky-50"
                  >
                    {{ vendPrefix.name }}
                  </TableData>
                  <TableData
                    :currentIndex="vendPrefixIndex"
                    :totalLength="vendPrefixes.length"
                    inputClass="text-left whitespace-pre-line"
                  >
                    {{ vendPrefix.desc }}
                  </TableData>
                  <TableData
                    :currentIndex="vendPrefixIndex"
                    :totalLength="vendPrefixes.length"
                    inputClass="text-center"
                  >
                    <div class="flex flex-col space-y-1">
                      <span
                        v-for="productMapping in filteredProductMappings(vendPrefix)"
                        :key="productMapping.id"
                      >
                        <a
                          :href="'/product-mappings/' + productMapping.id + '/edit'"
                          class="text-blue-600"
                          target="_blank"
                        >
                          {{ productMapping.name }}
                        </a>
                      </span>
                    </div>
                  </TableData>
                  <TableData
                    :currentIndex="vendPrefixIndex"
                    :totalLength="vendPrefixes.length"
                    inputClass="text-center"
                  >
                    <div class="flex flex-col space-y-1">
                      <span
                        v-for="upcomingProductMapping in uniqueUpcomingProductMappings(vendPrefix)"
                        :key="upcomingProductMapping.id"
                      >
                        <a
                          :href="'/product-mappings/' + upcomingProductMapping.id + '/edit'"
                          class="text-blue-600"
                          target="_blank"
                        >
                          {{ upcomingProductMapping.name }}
                        </a>
                      </span>
                    </div>
                  </TableData>
                  <TableData
                    :currentIndex="vendPrefixIndex"
                    :totalLength="vendPrefixes.length"
                    inputClass="text-center"
                  >
                    <div class="flex justify-center space-x-1">
                      <Button
                        type="button"
                        class="bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
                        @click="onEditClicked(vendPrefix)"
                      >
                        <PencilSquareIcon class="w-4 h-4"></PencilSquareIcon>
                        <span>Edit</span>
                      </Button>
                      <Button
                        type="button"
                        class="bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex-col space-y-1 w-fit"
                        :class="[
                          (vendPrefix.vends && vendPrefix.vends.length > 0) ||
                          (vendPrefix.vendConfigs && vendPrefix.vendConfigs.length > 0)
                            ? 'opacity-50 cursor-not-allowed'
                            : ''
                        ]"
                        @click="onDeleteClicked(vendPrefix)"
                        :disabled="(vendPrefix.vends && vendPrefix.vends.length > 0) || (vendPrefix.vendConfigs && vendPrefix.vendConfigs.length > 0)"
                      >
                        <span class="flex space-x-1 items-center">
                          <TrashIcon class="w-4 h-4"></TrashIcon>
                          <span>Delete</span>
                        </span>
                        <span
                          v-if="
                            (vendPrefix.vends && vendPrefix.vends.length > 0) ||
                            (vendPrefix.vendConfigs && vendPrefix.vendConfigs.length > 0)
                          "
                        >
                          (Binded)
                        </span>
                      </Button>
                    </div>
                  </TableData>
                </tr>
                <tr v-if="!vendPrefixes.data.length">
                  <td
                    colspan="24"
                    class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center"
                  >
                    No Results Found
                  </td>
                </tr>
              </tbody>
            </table>
            <Paginator
              v-if="vendPrefixes.data.length"
              :links="vendPrefixes.links"
              :meta="vendPrefixes.meta"
            ></Paginator>
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
      :productMappingOptions="props.productMappingOptions"
      :vendConfigOptions="vendConfigOptions"
      :vendPrefix="vendPrefix"
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
import Form from '@/Pages/VendPrefix/Form.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import {
  BackspaceIcon,
  MagnifyingGlassIcon,
  PhotoIcon,
  PencilSquareIcon,
  PlusIcon,
  TrashIcon,
} from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ref, onMounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";
import { filter } from 'lodash';

const props = defineProps({
  operatorOptions: [Array, Object],
  productMappingOptions: [Array, Object],
  vendConfigOptions: [Array, Object],
  vendPrefixes: Object,
  vendPrefixOptions: Object,
});

const filters = ref({
  product_mapping_id: '',
  upcoming_product_mapping_id: '',
  vend_config_id: '',
  vendPrefixes: [],
  vendStatus: '',
  sortKey: '',
  sortBy: true,
  numberPerPage: 100,
});
const attachments = ref([]);
const showAttachmentOverviewModal = ref(false);
const showModal = ref(false);
const vendPrefix = ref();
const type = ref('');
const toast = useToast();
const numberPerPageOptions = ref([]);
const productMappingOptions = ref([]);
const vendConfigOptions = ref([]);
const vendPrefixOptions = ref([]);
const vendStatusOptions = ref([])

onMounted(() => {
  numberPerPageOptions.value = [
    { id: 100, value: 100 },
    { id: 200, value: 200 },
    { id: 500, value: 500 },
    { id: 'All', value: 'All' },
  ];
  productMappingOptions.value = [
    { id: 'all', value: 'All' },
    ...props.productMappingOptions.data.map((data) => {
      return { id: data.id, value: data.name };
    }),
  ];
  vendConfigOptions.value = [
    { id: 'all', value: 'All' },
    ...props.vendConfigOptions.data.map((data) => {
      return { id: data.id, value: data.name };
    }),
  ];
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
	]
  filters.value.numberPerPage = numberPerPageOptions.value[0];
  filters.value.vend_config_id = vendConfigOptions.value[0];
  filters.value.product_mapping_id = productMappingOptions.value[0];
  filters.value.upcoming_product_mapping_id = productMappingOptions.value[0];
  filters.value.vendStatus = vendStatusOptions.value[2]
});

function onCreateClicked() {
  type.value = 'create';
  vendPrefix.value = null;
  showModal.value = true;
}

function onDeleteClicked(vendPrefix) {
  const approval = confirm('Are you sure to delete ' + vendPrefix.name + '?');
  if (!approval) {
    return;
  }
  router.delete('/vend-prefixes/' + vendPrefix.id, {
    onSuccess: () => {
      toast.success("Machine prefix deleted successfully", { timeout: 3000 })
    },
    onError: () => {
      toast.error("Failed to delete machine prefix", { timeout: 3000 })
    }
  })
}

function onEditClicked(telcoValue) {
  type.value = 'update';
  vendPrefix.value = telcoValue;
  showModal.value = true;
}

function onSearchFilterUpdated() {
  router.get(
    '/vend-prefixes',
    {
      ...filters.value,
      product_mapping_id: filters.value.product_mapping_id.id,
      upcoming_product_mapping_id: filters.value.upcoming_product_mapping_id.id,
      vend_config_id: filters.value.vend_config_id.id,
      vendPrefixes: filters.value.vendPrefixes.map((vendPrefix) => vendPrefix.id),
      vendStatus: filters.value.vendStatus.id,
      numberPerPage: filters.value.numberPerPage.id,
    },
    {
      preserveState: true,
      replace: true,
    }
  );
}

function resetFilters() {
  router.get('/vend-prefixes');
}

function sortTable(sortKey) {
  filters.value.sortKey = sortKey;
  filters.value.sortBy = !filters.value.sortBy;
  onSearchFilterUpdated();
}

function onAttachmentOverviewClicked(vendConfig) {
  attachments.value = vendConfig.attachments;
  showAttachmentOverviewModal.value = true;
}

function onAttachmentOverviewModalClose() {
  showAttachmentOverviewModal.value = false;
}

function onModalClose() {
  showModal.value = false;
}

function normalizeCollection(collection) {
  if (Array.isArray(collection)) {
    return collection;
  }

  if (collection && Array.isArray(collection.data)) {
    return collection.data;
  }

  return [];
}

function uniqueUpcomingProductMappings(vendPrefix) {
  const normalizedUpcoming = normalizeCollection(
    vendPrefix?.upcomingProductMappingsUnique
  );
  if (normalizedUpcoming.length) {
    return normalizedUpcoming;
  }

  const uniques = [];
  const seen = new Set();

  normalizeCollection(vendPrefix?.productMappings).forEach((productMapping) => {
    normalizeCollection(productMapping?.upcomingProductMappings).forEach(
      (upcoming) => {
        const id = upcoming?.id;
        if (!id || seen.has(id)) {
          return;
        }
        seen.add(id);
        uniques.push(upcoming);
      }
    );
  });

  return uniques;
}

function upcomingProductMappingIds(vendPrefix) {
  return normalizeCollection(vendPrefix?.productMappings).reduce((ids, productMapping) => {
    const upcomingIds = normalizeCollection(productMapping?.upcomingProductMappings).map(
      (upcoming) => upcoming.id
    );
    return ids.concat(upcomingIds);
  }, []);
}

function filteredProductMappings(vendPrefix) {
  const upcomingIds = upcomingProductMappingIds(vendPrefix);
  return normalizeCollection(vendPrefix?.productMappings).filter(
    (productMapping) => !upcomingIds.includes(productMapping.id)
  );
}
</script>
