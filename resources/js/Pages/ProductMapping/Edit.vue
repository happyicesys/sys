<template>
  <Head title="VM Edit" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex flex-col md:flex-row space-x-2">
        <span class="text-gray-600" v-if="productMapping.data && productMapping.data.id">
          Editing
        </span>
        <span v-if="productMapping.data && productMapping.data.id">
          {{ productMapping.data.name }}
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
                  <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                    Upcoming Product Mapping(s)
                  </label>
                  <MultiSelect
                    v-model="form.upcomingProductMappings"
                    :options="upcomingProductMappingOptions"
                    trackBy="id"
                    valueProp="id"
                    label="value"
                    placeholder="Select"
                    open-direction="bottom"
                    mode="tags"
                    class="mt-1"
                  >
                  </MultiSelect>
                  <div class="text-sm text-red-600" v-if="form.errors.upcomingProductMappings">
                    {{ form.errors.upcomingProductMappings }}
                  </div>
                </div>

                <div class="sm:col-span-6">
                  <FormTextarea v-model="form.remarks" :error="form.errors.remarks">
                    Remarks
                  </FormTextarea>
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
                      <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded-md"> Vend Channels Product Mapping </span>
                    </div>
                  </div>
                </div>

                <div class="sm:col-span-1" v-if="form.id">
                  <label class="flex justify-start text-sm font-medium text-gray-700">Sequence</label>
                  <select v-model="form.sequence" class="mt-1 block w-full rounded-md border-gray-300">
                    <option v-for="n in productMappingItems.length + 1" :key="n" :value="n">{{ n }}</option>
                  </select>
                </div>
                <div class="sm:col-span-1" v-if="form.id">
                  <FormInput v-model="form.channel_code" :error="form.errors.channel_code" placeholderStr="Channel ID">
                    Channel ID
                  </FormInput>
                </div>
                <div class="sm:col-span-3" v-if="form.id">
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

                <div class="sm:col-span-1" v-if="form.id">
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

                <div class="sm:col-span-6 flex flex-col mt-3" v-if="form.id">
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
                                :sortKey="sortKey"
                                :sortBy="sortBy"
                                @sortTable="onSortTable"
                              >
                                #
                              </TableHeadSort>
                              <TableHeadSort
                                modelName="channel_code"
                                :sortKey="sortKey"
                                :sortBy="sortBy"
                                @sortTable="onSortTable"
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
                            <tr
                                v-for="(productMappingItem, idx) in sortedItems"
                                :key="productMappingItem.id ?? productMappingItem.channel_code ?? idx"
                                :class="idx % 2 === 0 ? undefined : 'bg-gray-50'"
                              >
                              <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
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
                              <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left">
                                <span v-if="productMappingItem.product.category && productMappingItem.product.categoryGroup">
                                  {{ productMappingItem.product.categoryGroup.name }}
                                </span>
                              </td>
                              <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left">
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
const sortKey = ref(null)
const sortBy  = ref(false)
const toast = useToast()
const upcomingProductMappingOptions = ref([])

onMounted(() => {

  priceTypeOptions.value = [
    {id: '', name: '--- Clear ---' },
    ...Object.entries(props.priceTypeOptions).map(([id, name]) => ({id: id, name: name}))
  ]
  productOptions.value = props.products.data;

  productMappingItems.value = props.productMapping
  ? JSON.parse(JSON.stringify(props.productMapping.data.productMappingItems))
  : []
  upcomingProductMappingOptions.value = props.upcomingProductMappingOptions.data.map((data) => ({ id: data.id, value: data.name }));

  form.value = props.productMapping ? useForm({
    ...props.productMapping.data,
    // selling_price_type: priceTypeOptions.value.find((data) => data.id == props.productMapping.data.selling_price_type),
  }) : useForm(getDefaultForm());
})

// compare helper for channel_code
function cmpStrNum(a, b) {
  const an = +a, bn = +b
  const bothNum = Number.isFinite(an) && Number.isFinite(bn)
  return bothNum ? (an - bn) : String(a ?? '').localeCompare(String(b ?? ''))
}

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
    upcomingProductMappings: [],
  }
}

const sortedItems = computed(() => {
  const arr = productMappingItems.value.slice()
  if (!sortKey.value) return arr

  const dir = sortBy.value ? -1 : 1 // true=DESC
  if (sortKey.value === 'sequence') {
    return arr.sort((A, B) => {
      const a = Number.isFinite(+A.sequence) ? +A.sequence : Infinity
      const b = Number.isFinite(+B.sequence) ? +B.sequence : Infinity
      if (a !== b) return dir * (a - b)
      return dir * cmpStrNum(A.channel_code, B.channel_code) // tiebreaker
    })
  }
  if (sortKey.value === 'channel_code') {
    return arr.sort((A, B) => dir * cmpStrNum(A.channel_code, B.channel_code))
  }
  return arr
})

function onSequenceChanged(changedItem) {
  // If cleared to "—"
  if (changedItem.sequence === null || changedItem.sequence === undefined) {
    return;
  }

  // Coerce & clamp
  const total = productMappingItems.value.length;
  let n = Number(changedItem.sequence);
  if (!Number.isFinite(n)) { changedItem.sequence = null; return; }
  n = Math.min(Math.max(Math.trunc(n), 1), total);

  // Assign to the changed row
  changedItem.sequence = n;

  // Remove this number from everyone else
  productMappingItems.value.forEach(item => {
    if (item !== changedItem && item.sequence === n) {
      item.sequence = null; // or '' if you prefer empty
    }
  });
}

function onSortTable(key) {
  if (sortKey.value !== key) {
    sortKey.value = key
    sortBy.value  = false   // start ASC
    return
  }
  if (sortBy.value === false) { // ASC -> DESC
    sortBy.value = true
    return
  }
  // DESC -> clear
  sortKey.value = null
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
      upcomingProductMappings: JSON.parse(JSON.stringify(form.value.upcomingProductMappings)).map((data) => data.id),
      is_active: data.is_active.id,
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
  const code = (form.value.channel_code ?? '').toString().trim();
  const productIdOrObj = form.value.product_id;

  if (!code || !productIdOrObj) return;

  // prevent duplicate channel_code rows
  const existsIdx = productMappingItems.value.findIndex(pm => pm.channel_code == code);
  if (existsIdx >= 0) return;

  // clamp sequence
  const afterAddCount = productMappingItems.value.length + 1;
  let seq = form.value.sequence;
  if (seq === null || seq === undefined || !Number.isFinite(Number(seq))) {
    seq = null;
  } else {
    seq = Math.trunc(Number(seq));
    if (seq < 1) seq = 1;
    if (seq > afterAddCount) seq = afterAddCount;
  }

  // ensure the product is an object so UI (thumbnail/name) works before save
  let productObj = productIdOrObj;
  if (productObj && typeof productObj !== 'object') {
    productObj = productOptions.value.find(p => p.id === productIdOrObj) || { id: productIdOrObj };
  }

  const newItem = {
    product: productObj,
    channel_code: code,
    sequence: seq,
  };

    // enforce uniqueness: clear same sequence from others
  if (seq !== null) {
    const previous = productMappingItems.value.find(item => item.sequence === seq);
    if (previous) previous.sequence = null;
  }

  productMappingItems.value.push(newItem);

  // keep your existing channel_code sort (if you want to keep it)
  // productMappingItems.value.sort((a, b) => a.channel_code - b.channel_code);


  // reset quick-add fields
  form.value.channel_code = '';
  form.value.product_id = '';
  form.value.sequence = null;
}

function getDefaultSellingPriceId(productMappingItem) {
  return productMappingItem.product?.sellingPrices.filter((data) => data.id === productMappingItem.selling_price_id).map((data) => ({ id: data.id, full_name: 'P' + data.type + ' (' + (data.amount/100).toFixed(2) + ')' }))
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