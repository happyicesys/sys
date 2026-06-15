<template>
  <Head title="VM Edit" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex flex-col md:flex-row md:items-center md:space-x-2 gap-1">
        <span class="text-gray-600" v-if="productMapping.data && productMapping.data.id">
          Editing
        </span>
        <span v-if="productMapping.data && productMapping.data.id">
          {{ productMapping.data.name }}
        </span>
        <!--
          Mapping type badge. The same ProductMapping table powers both planograms;
          this chip surfaces which editor mode the page is in. is_smart is set at
          create-time (Form.vue radio) and read-only here to keep channel_code
          formats consistent with what's already bound.
        -->
        <span
          v-if="productMapping.data && productMapping.data.id"
          class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold w-fit"
          :class="productMapping.data.is_smart ? 'bg-indigo-600 text-white' : 'bg-slate-200 text-slate-800'"
        >
          {{ productMapping.data.is_smart ? 'Smart Freezer' : 'Vending Machine' }}
        </span>
      </div>
    </template>
    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3 overflow-visible">
      <div class="mt-6 flex flex-col overflow-visible">
        <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8 overflow-visible">
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-visible p-5">
            <form @submit.prevent="submit" id="submit">
              <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">

                <div class="sm:col-span-6">
                  <div
                    class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit"
                    :class="[productMapping.data.is_active ? 'bg-green-300' : 'bg-red-300']"
                    v-if="productMapping.data && productMapping.data.id"
                  >
                    <span v-if="productMapping.data.is_active">
                      Active
                    </span>
                    <span v-if="!productMapping.data.is_active">
                      Not Active
                    </span>
                  </div>
                </div>
                <div class="sm:col-span-6">
                  <FormInput v-model="form.name" :error="form.errors.name" required="true">
                    Name
                  </FormInput>
                </div>
                <div class="sm:col-span-6">
                  <FormTextarea v-model="form.remarks" :error="form.errors.remarks">
                    Remarks
                  </FormTextarea>
                </div>

                <div class="sm:col-span-6">
                  <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                    Operator
                  </label>
                  <MultiSelect
                    v-model="form.operator_id"
                    :options="operatorOptions.data"
                    trackBy="id"
                    valueProp="id"
                    label="name"
                    placeholder="Select"
                    open-direction="bottom"
                    class="mt-1"
                  >
                  </MultiSelect>
                </div>
                <div class="sm:col-span-6">
                  <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                    Upcoming Product Mapping
                  </label>
                  <MultiSelect
                    v-model="form.upcoming_product_mapping_id"
                    :options="upcomingOptions"
                    trackBy="id"
                    valueProp="id"
                    label="name"
                    placeholder="Select"
                    open-direction="bottom"
                    class="mt-1"
                  >
                  </MultiSelect>
                </div>

                <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
                  <div class="relative">
                    <div class="absolute inset-0 flex items-center" aria-hidden="true">
                      <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center ">
                      <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded-md"> Attachment(s) </span>
                    </div>
                  </div>
                </div>

                <div class="sm:col-span-6">
                  <AttachmentListProductMapping
                    :items="productMapping.data.attachments"
                    :priceTypeOptions="priceTypeOptions"
                  >
                  </AttachmentListProductMapping>
                </div>

                <div class="sm:col-span-6">
                  <UploadFileInput
                    :endpoint="'/product-mappings/' + productMapping.data.id + '/upload-attachments'"
                  >
                  </UploadFileInput>
                </div>
                <div class="sm:col-span-6">
                  <DropzoneFileInput :endpoint="'/product-mappings/' + productMapping.data.id + '/upload-attachments'"></DropzoneFileInput>
                </div>

                <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3" v-if="form.id">
                  <div class="relative">
                    <div class="absolute inset-0 flex items-center" aria-hidden="true">
                      <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-center">
                      <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded-md">
                        {{ form.is_smart ? 'Smart Freezer Basket Layout' : 'Vend Channels Product Mapping' }}
                      </span>
                    </div>
                  </div>
                </div>

                <!--
                  Smart-freezer planogram editor. Drives the SAME backend endpoints
                  the vending row below uses (POST /items/create, DELETE /items/{id}),
                  so storage in product_mapping_items is identical (channel_code is
                  already varchar). Basket layout shape persists on save via
                  form.basket_layout_json. Vending UI below is unchanged.
                -->
                <div class="sm:col-span-6" v-if="form.id && form.is_smart">
                  <SmartFreezerLayout
                    :productMappingId="form.id"
                    :products="productOptions"
                    :productMappingItems="productMappingItems"
                    :basketLayout="form.basket_layout_json || []"
                    @layout-changed="onSmartLayoutChanged"
                    @items-changed="onSmartItemsChanged"
                  />
                </div>

                <div class="sm:col-span-1" v-if="form.id && !form.is_smart">
                  <label class="flex justify-start text-sm font-medium text-gray-700">Display Sequence</label>
                  <select v-model="form.sequence" class="mt-1 block w-full rounded-md border-gray-300">
                    <option v-for="n in productMappingItems.length + 1" :key="n" :value="n">{{ n }}</option>
                  </select>
                </div>
                <div class="sm:col-span-1" v-if="form.id && !form.is_smart">
                  <FormInput v-model="form.channel_code" :error="form.errors.channel_code" placeholderStr="Channel ID">
                    Channel ID
                  </FormInput>
                </div>
                <div class="sm:col-span-3" v-if="form.id && !form.is_smart">
                  <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                    Product
                  </label>
                  <MultiSelect
                    v-model="form.product_id"
                    :options="productOptions"
                    trackBy="id"
                    valueProp="id"
                    label="full_name"
                    placeholder="Select"
                    open-direction="bottom"
                    class="mt-1"
                  >
                  </MultiSelect>
                  <div class="text-sm text-red-600" v-if="form.errors.product_id">
                    {{ form.errors.product_id }}
                  </div>
                </div>

                <div class="sm:col-span-1" v-if="form.id && !form.is_smart">
                  <Button
                    type="button"
                    @click.prevent="bindProductMappingItem()"
                    class="bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6"
                    :class="[!form.channel_code || !form.product_id ? 'opacity-50 cursor-not-allowed' : '']"
                    :disabled="!form.channel_code || !form.product_id"
                  >
                    <PlusCircleIcon class="w-4 h-4"></PlusCircleIcon>
                    <span>
                      Add
                    </span>
                  </Button>
                </div>

                <div class="sm:col-span-6 flex flex-col mt-3" v-if="form.id && !form.is_smart">
                  <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5">
                    <div class="inline-block min-w-full py-2 align-middle md:px-4 lg:px-6">
                      <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg pb-24">
                        <table class="min-w-full divide-y divide-gray-300">
                          <thead class="bg-gray-50" @click.capture="preventHashNav">
                            <tr>
                              <!-- <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                                #
                              </th> -->
                              <TableHeadSort
                                modelName="sequence"
                                :sortKey="form.sortKey"
                                :sortBy="form.sortBy"
                                @sort-table="sortTable('sequence')"
                              >
                                Display Sequence
                              </TableHeadSort>
                              <TableHeadSort
                                modelName="channel_code"
                                :sortKey="form.sortKey"
                                :sortBy="form.sortBy"
                                @sort-table="sortTable('channel_code')"
                              >
                                Channel Code
                              </TableHeadSort>
                              <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                                Thumbnail
                              </th>
                              <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                                Product
                              </th>
                              <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                                Category
                              </th>
                              <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                                SubCategory
                              </th>
                              <!-- <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                                Server Price ({{ operatorCountry.currency_symbol }}) <br>
                                <MultiSelect
                                    v-model="form.selling_price_type"
                                    :options="priceTypeOptions"
                                    trackBy="id"
                                    valueProp="id"
                                    label="name"
                                    placeholder="Select"
                                    open-direction="bottom"
                                    class="mt-1 w-full min-w-36"
                                    @selected="onSellingPriceChanged"
                                  >
                                  </MultiSelect>

                              </th> -->
                              <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                                Action
                              </th>
                            </tr>
                          </thead>
                          <tbody class="bg-white">
                            <template
                                v-for="(productMappingItem, idx) in productMappingItems"
                                :key="productMappingItem.id ?? productMappingItem.channel_code ?? idx"
                              >
                            <tr
                                :class="(productMappingItem.product && productMappingItem.product.is_parent_sku) ? '!bg-indigo-50 border-t-2 border-indigo-200' : (idx % 2 === 0 ? undefined : 'bg-gray-50')"
                              >
                              <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center" :class="[(productMappingItem.product && productMappingItem.product.is_parent_sku) ? 'border-l-4 border-indigo-400' : '']">
                                <select v-model="productMappingItem.sequence" @change="onSequenceChanged(productMappingItem)">
                                  <option :value="null"></option>
                                  <option v-for="n in productMappingItems.length" :key="n" :value="n">{{ n }}</option>
                                </select>
                              </td>
                              <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                                {{ productMappingItem.channel_code }}
                              </td>
                              <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                                <div class="flex justify-center">
                                  <img class="h-24 w-24 md:h-20 md:w-20 rounded-full" :src="productMappingItem.product.thumbnail.full_url" alt="" v-if="productMappingItem.product && productMappingItem.product.thumbnail"/>
                                </div>
                              </td>
                              <td class="py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left">
                                <span v-if="productMappingItem.product.code">
                                  {{ productMappingItem.product.code }} -
                                </span>
                                <span>
                                  {{ productMappingItem.product.name }}
                                </span>
                              </td>
                              <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                                <span v-if="productMappingItem.product.category && productMappingItem.product.categoryGroup">
                                  {{ productMappingItem.product.categoryGroup.name }}
                                </span>
                              </td>
                              <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                                <span v-if="productMappingItem.product.category">
                                  {{ productMappingItem.product.category.name }}
                                </span>
                              </td>
                              <!-- <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                                <span v-if="form.selling_price_type && productMappingItem.product && productMappingItem.product.sellingPrices">
                                  {{((productMappingItem.product.sellingPrices[0].amount)/ (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
                                </span>
                              </td> -->
                              <td class="whitespace-nowrap py-4 text-sm text-center">
                                <Button
                                  class="bg-red-400 hover:bg-red-500 text-white"
                                  @click="unbindProductMappingItem(productMappingItem)"
                                >
                                  <BackspaceIcon class="w-4 h-4"></BackspaceIcon>
                                </Button>
                              </td>
                            </tr>
                            <tr
                              v-if="productMappingItem.product && productMappingItem.product.is_parent_sku"
                              class="!bg-indigo-50 border-b-2 border-indigo-200"
                            >
                              <td colspan="7" class="border-l-4 border-indigo-400 px-6 pb-3 pt-0">
                                <div class="pl-8 text-left">
                                  <div class="flex items-center justify-between flex-wrap gap-2">
                                    <span class="text-xs font-semibold text-indigo-700">
                                      ↳ Blind flavours of {{ productMappingItem.product.code }} ({{ (productMappingItem.product.blindChildren || []).length }})
                                    </span>
                                    <Link :href="'/products/' + productMappingItem.product.id + '/edit'" class="text-[11px] text-indigo-600 hover:text-indigo-800 underline">
                                      Edit on product →
                                    </Link>
                                  </div>
                                  <div v-if="(productMappingItem.product.blindChildren || []).length" class="mt-1.5 flex flex-wrap gap-1.5">
                                    <span
                                      v-for="child in productMappingItem.product.blindChildren"
                                      :key="child.id"
                                      class="inline-flex items-center gap-1.5 rounded-full bg-white ring-1 ring-indigo-200 py-0.5 pl-0.5 pr-2 text-[11px] font-medium text-indigo-700"
                                    >
                                      <img
                                        v-if="child.child_product && child.child_product.thumbnail_url"
                                        :src="child.child_product.thumbnail_url"
                                        class="h-8 w-8 rounded-full object-cover ring-1 ring-indigo-200 cursor-zoom-in hover:ring-2 hover:ring-indigo-400 transition"
                                        alt=""
                                        title="Click to enlarge"
                                        @click="openImagePreview(child)"
                                      />
                                      <span v-else class="h-8 w-8 rounded-full bg-indigo-100"></span>
                                      {{ child.child_product ? (child.child_product.code + ' - ' + child.child_product.name) : 'Flavour' }}
                                      <span class="text-indigo-500">{{ child.weight_pct }}%</span>
                                    </span>
                                  </div>
                                  <p v-else class="mt-1 text-[11px] text-amber-600">
                                    No flavours bound yet — set them in Product → Edit.
                                  </p>
                                </div>
                              </td>
                            </tr>
                            </template>
                            <tr v-if="!productMappingItems.length">
                              <td colspan="7" class="whitespace-nowrap py-4 text-sm font-medium text-gray-600 text-center">
                                No Records Found
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="sm:col-span-6 mt-5 ">
                <div class="flex justify-between">
                  <div class="flex space-x-1 justify-start">
                    <Button type="button" class="bg-blue-500 hover:bg-blue-600 text-white flex space-x-1" v-if="form.id" @click="replicateProductMapping()">
                      <DocumentDuplicateIcon class="w-4 h-4"></DocumentDuplicateIcon>
                      <span>
                        Replicate
                      </span>
                    </Button>

                    <Button type="button" v-if="form.id" @click="toggleActivateDeactivate" class="text-white" :class="[form.is_active ? 'bg-red-500 hover:bg-red-600' : 'bg-green-500 hover:bg-green-600']">
                      <div>
                        <span class="flex flex-col space-y-1" v-if="form.is_active">
                          <span class="flex space-x-1 items-center">
                            <FolderMinusIcon class="w-4 h-4"></FolderMinusIcon>
                            <span>
                              Deactivate
                            </span>
                          </span>
                          <span class="text-xs">
                            (Product Mapping(s) still Binded)
                          </span>
                        </span>
                        <span class="flex space-x-1 items-center" v-else>
                          <FolderPlusIcon class="w-4 h-4"></FolderPlusIcon>
                          <span>
                            Activate
                          </span>
                        </span>
                      </div>
                    </Button>
                  </div>

                  <div class="flex space-x-1 justify-end">
                    <Link :href="'/product-mappings'">
                      <Button
                        type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1 h-full"
                      >
                        <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
                        <span>
                          Back
                        </span>
                      </Button>
                    </Link>

                    <Button type="submit" class="bg-green-500 hover:bg-green-600 text-white flex space-x-1">
                      <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                      <span>
                        Save
                      </span>
                    </Button>
                  </div>
                </div>

              </div>
            </form>
          </div>
        </div>
      </div>
    </div>

    <!-- Flavour thumbnail enlarge popup -->
    <Modal :open="imagePreview.open" @modal-close="imagePreview.open = false">
      <template #header>
        <span class="text-base">{{ imagePreview.title }}</span>
      </template>
      <div class="flex justify-center">
        <img :src="imagePreview.url" class="max-h-[70vh] w-auto rounded-lg object-contain" alt="" />
      </div>
    </Modal>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import AttachmentListProductMapping from '@/Components/AttachmentListProductMapping.vue';
import Button from '@/Components/Button.vue';
import DropzoneFileInput from '@/Components/DropzoneFileInput.vue';
import FormInput from '@/Components/FormInput.vue';
import FormTextarea from '@/Components/FormTextarea.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import Modal from '@/Components/Modal.vue';
import SmartFreezerLayout from '@/Pages/ProductMapping/SmartFreezerLayout.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import UploadFileInput from '@/Components/UploadFileInput.vue';
import { ArrowUturnLeftIcon, BackspaceIcon, CheckCircleIcon, DocumentDuplicateIcon, FolderMinusIcon, FolderPlusIcon, PlusCircleIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted, computed } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";

const props = defineProps({
  priceTypeOptions: Object,
  products: Object,
  productMapping: Object,
  operatorOptions: Object,
  upcomingProductMappingOptions: Object,
})

const emit = defineEmits(['modalClose'])

const form = ref(
  useForm(getDefaultForm())
)
const operatorCountry = usePage().props.auth.operatorCountry
const priceTypeOptions = ref([])
const productOptions = ref([])
const productMappingItems = ref([])
const upcomingOptions = ref([])
// Flavour thumbnail enlarge popup
const imagePreview = ref({ open: false, url: '', title: '' })
function openImagePreview(child) {
  const cp = child?.child_product
  if (!cp || !cp.thumbnail_url) return
  imagePreview.value = { open: true, url: cp.thumbnail_url, title: `${cp.code} - ${cp.name}` }
}
const toast = useToast()

onMounted(() => {

  priceTypeOptions.value = [
    {id: '', name: '--- Clear ---' },
    ...Object.entries(props.priceTypeOptions).map(([id, name]) => ({id: id, name: name}))
  ]
  productOptions.value = props.products.data;

  upcomingOptions.value = [
    {id: '', name: '--- Clear ---'},
    ...props.upcomingProductMappingOptions.data
  ];

  productMappingItems.value = props.productMapping
  ? JSON.parse(JSON.stringify(props.productMapping.data.productMappingItems))
  : []
  form.value = props.productMapping ? useForm({
    ...props.productMapping.data,
    operator_id: props.operatorOptions.data.find(op => op.id === (props.productMapping.data.operator_id || 1)),
    upcoming_product_mapping_id: props.upcomingProductMappingOptions.data.find(op => op.id === props.productMapping.data.upcoming_product_mapping_id),
    // selling_price_type: priceTypeOptions.value.find((data) => data.id == props.productMapping.data.selling_price_type),
  }) : useForm(getDefaultForm());

  form.value.sortKey = usePage().props.sortKey || ''
  form.value.sortBy  = !!usePage().props.sortBy
})


function getDefaultForm() {
  return {
    id: '',
    name: '',
    is_active: '',
    remarks: '',
    channel_code: '',
    product_id: '',
    server_amount: '',
    sequence: '',
    operator_id: props.operatorOptions?.data?.find(op => op.id === 1),
    upcoming_product_mapping_id: '',
    // Smart-freezer planogram. is_smart is read-only on Edit (set at creation
    // time); basket_layout_json carries the per-basket division shape and is
    // mutated locally by SmartFreezerLayout, persisted on Save via the
    // existing /update endpoint.
    is_smart: false,
    basket_layout_json: [],
    sortKey: '',
    sortBy: false,
  }
}

// SmartFreezerLayout bubbles the latest per-basket division shape up here so
// it ships with the next Save round-trip.
function onSmartLayoutChanged(layout) {
  form.value.basket_layout_json = layout
}

// SmartFreezerLayout binds/unbinds via the same endpoints the vending row uses.
// The child's router.post/router.delete already returned a redirect-back, so by
// the time this handler runs Inertia has already refreshed props.productMapping
// with the new items (with real DB IDs). We just re-derive the local ref from
// those fresh props — mirroring the immediate re-derive in the vending
// bindProductMappingItem onSuccess. An extra router.reload here used to cause
// the "needs refresh to take effect + scroll jumps to top" symptom because two
// chained Inertia visits stopped honouring preserveScroll/preserveState
// reliably; trusting the post/delete's own redirect-back avoids that.
function onSmartItemsChanged() {
  productMappingItems.value = props.productMapping
    ? JSON.parse(JSON.stringify(props.productMapping.data.productMappingItems))
    : []
}


function onSequenceChanged(changedItem) {
  // Validate that the item has a valid ID before attempting to update
  if (!changedItem.id || typeof changedItem.id !== 'number') {
    toast.error("Unable to update sequence. Please refresh the page and try again.", {
      timeout: 5000
    });
    // Reload the page to get fresh data
    router.reload({
      only: ['productMapping'],
      replace: true,
      preserveState: true,
      preserveScroll: true,
    });
    return;
  }

  router.post('/product-mappings/items/' + changedItem.id + '/sequence', {
    sequence: changedItem.sequence,
  }, {
    onSuccess: () => {
      toast.success("Sequence updated", {
        timeout: 3000
      });
      productMappingItems.value = props.productMapping
      ? JSON.parse(JSON.stringify(props.productMapping.data.productMappingItems))
      : []

      router.reload({
        only: ['productMappingItems'],
        replace: true,
        preserveState: true,
      })
    },
    onError: (errors) => {
      toast.error("Failed to update sequence: " + (errors.sequence || "Unknown error"), {
        timeout: 5000
      });
    },
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

function submit() {
  form.value.clearErrors()
  form.value
    .transform((data) => ({
      ...data,
      // selling_price_type: data.selling_price_type?.id,
      productMappingItems: productMappingItems.value.map((item) => ({
        ...item,
      })),
      is_active: data.is_active.id,
      operator_id: data.operator_id?.id,
      upcoming_product_mapping_id: data.upcoming_product_mapping_id ? data.upcoming_product_mapping_id.id : null,
    }))
    .post('/product-mappings/' + form.value.id + '/update', {
      onSuccess: () => {
        emit('modalClose')
      },
      preserveState: true,
      replace: true,
    })
}

function bindProductMappingItem() {

  router.post('/product-mappings/' + form.value.id + '/items/create', {
    channel_code: form.value.channel_code,
    product_id: form.value.product_id.id,
    sequence: form.value.sequence,
    productMappingId: form.value.id,
  }, {
    onSuccess: (page) => {
      // update the product mapping items
      productMappingItems.value = props.productMapping
      ? JSON.parse(JSON.stringify(props.productMapping.data.productMappingItems))
      : []
      toast.success("Product Mapping Item added", {
        timeout: 3000
      });

      // Reload to ensure fresh data with proper IDs
      router.reload({
        only: ['productMapping'],
        replace: true,
        preserveState: true,
        preserveScroll: true,
      })
    },
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
  // reset quick-add fields
  form.value.channel_code = '';
  form.value.product_id = '';
  form.value.sequence = null;
}

function getDefaultSellingPriceId(productMappingItem) {
  return productMappingItem.product?.sellingPrices.filter((data) => data.id === productMappingItem.selling_price_id).map((data) => ({ id: data.id, full_name: 'P' + data.type + ' (' + (data.amount/100).toFixed(2) + ')' }))
}

function onSearchFilterUpdated() {
  router.reload({
    // ask for the prop that actually contains the items
    only: ['productMapping', 'sortKey', 'sortBy'],
    data: {
      sortKey: form.value.sortKey,
      sortBy: form.value.sortBy,
    },
    replace: true,
    preserveState: true,
    preserveScroll: true,
    onSuccess: page => {
      productMappingItems.value = page.props.productMapping.data.productMappingItems.map(item => ({
        ...item,
        selling_price_id: getDefaultSellingPriceId(item)[0],
      }))
      // keep the UI arrows in sync
      form.value.sortKey = page.props.sortKey
      form.value.sortBy  = page.props.sortBy
    }
  })
}


function onSellingPriceChanged() {
  router.reload({
    only: ['productMapping'],
    data: {
      selling_price_type: form.value.selling_price_type?.id,
    },
    replace: true,
    preserveState: true,
    onSuccess: page => {
      productMappingItems.value = page.props.productMapping.data.productMappingItems.map(item => ({
        ...item,
        selling_price_id: getDefaultSellingPriceId(item)[0], // Ensure the list has initialized IDs
      }))
    }
  })
}

function onServerAmountChanged(id, amount) {
  router.post('/product-mappings/items/' + id + '/update', {
    server_amount: amount,
  },{
    onSuccess: () => {
    },
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

function preventHashNav(e) {
  const a = e.target.closest('a');
  if (!a) return;
  const href = a.getAttribute('href');
  // only cancel hash anchors in the header
  if ((href === '#' || href === '' || href?.startsWith('#')) && a.closest('thead')) {
    e.preventDefault(); // stops the scroll-to-top
  }
}

function productMappingItemOptionsMapping(productMappingItem) {
  return productMappingItem.product?.sellingPrices.map((data) => ({ id: data.id, full_name: 'P' + data.type + ' (' + (data.amount/100).toFixed(2) + ')' }))
}

function sortTable(sortKey) {
  form.value.sortKey = sortKey;
  form.value.sortBy = !form.value.sortBy;
  onSearchFilterUpdated();
}

function toggleActivateDeactivate() {
  form.value.post('/product-mappings/' + form.value.id + '/toggle-activate-deactivate', {
    onSuccess: () => {
      emit('modalClose')
    },
    preserveState: true,
    replace: true,
  })
}

function unbindProductMappingItem(productMappingItem) {
  productMappingItems.value.splice(productMappingItems.value.indexOf(productMappingItem), 1)

    router.delete('/product-mappings/items/' + productMappingItem.id, {
      onSuccess: (page) => {
        productMappingItems.value = props.productMapping
        ? JSON.parse(JSON.stringify(props.productMapping.data.productMappingItems))
        : []
        toast.success("Product Mapping Item Deleted", {
          timeout: 3000
        });
      },
      preserveState: true,
      preserveScroll: true,
      replace: true,
    })
}

function replicateProductMapping() {
  router.post('/product-mappings/replicate',
    {
      id: form.value.id,
    },
    {
      onSuccess: () => {
        toast.success("Mappings replicated", {
          timeout: 3000
        });
      },
      preserveState: false,
      replace: true,
    })
}
</script>
