<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600" v-if="props.productMapping">
            Editing
          </span>
          <span v-if="props.productMapping">
            {{ props.productMapping.name }}
          </span>
          <span class="text-gray-600" v-else>
            Create New Product Mapping
          </span>
        </div>
      </template>
      <template #default>
        <form @submit.prevent="submit" id="submit">
          <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">
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

            <!--
              Mapping type radio. Only meaningful at create time — once a mapping
              has items, switching the type would mix channel_code formats. The
              Edit page surfaces the type as a read-only badge.

              is_smart = true ⇒ ProductMapping/Edit.vue renders SmartFreezerLayout
              (6 baskets × 1-4 divisions, all-numeric channel codes "11","12","41");
              a default basket layout is seeded server-side at create.
            -->
            <div class="sm:col-span-6" v-if="type === 'create'">
              <label class="flex justify-start text-sm font-medium text-gray-700 mb-1">
                Mapping Type
              </label>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                <label
                  class="flex items-start gap-2 rounded-md border p-3 cursor-pointer transition"
                  :class="!form.is_smart ? 'border-indigo-500 ring-1 ring-indigo-500 bg-indigo-50/40' : 'border-gray-200 hover:bg-gray-50'"
                >
                  <input type="radio" :value="false" v-model="form.is_smart" class="mt-1" />
                  <span class="flex flex-col">
                    <span class="text-sm font-semibold text-gray-900">Vending Machine</span>
                    <span class="text-xs text-gray-500">Numeric channels (11, 12, 13…). Classic channel-row editor.</span>
                  </span>
                </label>
                <label
                  class="flex items-start gap-2 rounded-md border p-3 cursor-pointer transition"
                  :class="form.is_smart ? 'border-indigo-500 ring-1 ring-indigo-500 bg-indigo-50/40' : 'border-gray-200 hover:bg-gray-50'"
                >
                  <input type="radio" :value="true" v-model="form.is_smart" class="mt-1" />
                  <span class="flex flex-col">
                    <span class="text-sm font-semibold text-gray-900">Smart Freezer</span>
                    <span class="text-xs text-gray-500">Basket grid (11, 12, 21…). 6 baskets × up to 4 divisions.</span>
                  </span>
                </label>
              </div>
            </div>

            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3" v-if="form.id">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center">
                  <span class="px-3 bg-white text-lg font-medium text-gray-900"> Vend Channels Product Mapping </span>
                </div>
              </div>
            </div>

            <div class="sm:col-span-2" v-if="form.id">
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
              @click="bindProductMappingItem()"
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
                <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                  <table class="min-w-full divide-y divide-gray-300">
                    <thead class="bg-gray-50">
                      <tr>
                        <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                          #
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                          Channel Code
                        </th>
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
                          Group
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                          Action
                        </th>
                      </tr>
                    </thead>
                    <tbody class="bg-white">
                      <tr v-for="(productMappingItem, productMappingItemIndex) in productMappingItems" :key="productMappingItem.id" :class="productMappingItemIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                          {{ productMappingItemIndex + 1 }}
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                          {{ productMappingItem.channel_code }}
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                          <div class="flex justify-center">
                            <img class="h-24 w-24 md:h-20 md:w-20 rounded-full" :src="productMappingItem.product.thumbnail.full_url" alt="" v-if="productMappingItem.product && productMappingItem.product.thumbnail"/>
                          </div>
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left">
                          <span v-if="productMappingItem.product.code">
                            {{ productMappingItem.product.code }} -
                          </span>
                          <span>
                            {{ productMappingItem.product.name }}
                          </span>
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left">
                          <span v-if="productMappingItem.product.category">
                            {{ productMappingItem.product.category.name }}
                          </span>
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left">
                          <span v-if="productMappingItem.product.category && productMappingItem.product.category.categoryGroup">
                            {{ productMappingItem.product.category.categoryGroup.name }}
                          </span>
                        </td>
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
                        <td colspan="5" class="whitespace-nowrap py-4 text-sm font-medium text-gray-600 text-center">
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


          <div class="sm:col-span-6">
            <div class="flex space-x-1 mt-5 justify-end">
              <Button
                class="bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1"
                @click="$emit('modalClose')"
                form="submit"
              >
                <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
                <span>
                  Back
                </span>
              </Button>
              <Button type="button" class="bg-blue-500 hover:bg-blue-600 text-white flex space-x-1" v-if="form.id" @click="replicateProductMapping()">
                <DocumentDuplicateIcon class="w-4 h-4"></DocumentDuplicateIcon>
                <span>
                  Replicate
                </span>
              </Button>
              <Button type="submit" class="bg-green-500 hover:bg-green-600 text-white flex space-x-1">
                <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                <span>
                  Save
                </span>
              </Button>
            </div>
          </div>
        </form>
      </template>
    </Modal>
  </Teleport>
</template>

<script setup>
import Button from '@/Components/Button.vue';
import FormInput from '@/Components/FormInput.vue';
import FormTextarea from '@/Components/FormTextarea.vue';
import Modal from '@/Components/Modal.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { ArrowUturnLeftIcon, BackspaceIcon, CheckCircleIcon, DocumentDuplicateIcon, PlusCircleIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted } from 'vue'
import { router, useForm } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";

const props = defineProps({
  products: Object,
  productMapping: Object,
  type: String,
  showModal: Boolean,
})

const emit = defineEmits(['modalClose'])

const form = ref(
  useForm(getDefaultForm())
)
const productOptions = ref([])
const productMappingItems = ref([])
const toast = useToast()

onMounted(() => {
  form.value = props.productMapping ? useForm(props.productMapping) : useForm(getDefaultForm())
  productOptions.value = props.products.data
  productMappingItems.value = props.productMapping && props.productMapping.id ? JSON.parse(JSON.stringify(props.productMapping.productMappingItems)) : form.value
})

function getDefaultForm() {
  return {
    id: '',
    name: '',
    remarks: '',
    channel_code: '',
    product_id: '',
    // Mapping type. Only used at create time; the Edit page reads this from
    // the persisted record and renders the matching layout editor.
    is_smart: false,
  }
}

// Natural-order channel_code comparator. The original `a - b` subtraction
// produces NaN for alphanumeric codes ("1a" - "1b" === NaN), which leaves the
// list in unspecified order. Intl.Collator with numeric:true sorts
// "1a","1b","1c","2a","2b","3","10" the way a human reads them, and degrades
// gracefully for the legacy numeric vending codes too.
const channelCodeCollator = new Intl.Collator(undefined, { numeric: true, sensitivity: 'base' })
function compareChannelCodes(a, b) {
  return channelCodeCollator.compare(String(a ?? ''), String(b ?? ''))
}

function submit() {
  form.value.clearErrors()

  if(props.type === 'create') {
    form.value
    .post('/product-mappings/create', {
      onSuccess: () => {
        toast.success("Product mapping created successfully", { timeout: 3000 })
        emit('modalClose')
      },
      onError: () => {
        toast.error("Failed to create product mapping", { timeout: 3000 })
      },
      preserveState: true,
      replace: true,
    })
  }

  if(props.type === 'update') {
    form.value
      .transform((data) => ({
        ...data,
        productMappingItems: productMappingItems.value,
      }))
      .post('/product-mappings/' + form.value.id + '/update', {
      onSuccess: () => {
        toast.success("Product mapping updated successfully", { timeout: 3000 })
        emit('modalClose')
      },
      onError: () => {
        toast.error("Failed to update product mapping", { timeout: 3000 })
      },
      preserveState: true,
      replace: true,
    })
  }
}

function bindProductMappingItem() {
  if(productMappingItems.value.map(function(productMapping) { return productMapping.channel_code; }).indexOf(form.value.channel_code) < 0) {
    productMappingItems.value.push({product: form.value.product_id, channel_code: form.value.channel_code})
    // Use the natural-order comparator so smart-freezer alphanumeric codes
    // (1a, 1b, 2a…) and legacy numeric codes both sort correctly.
    productMappingItems.value.sort((a, b) => compareChannelCodes(a.channel_code, b.channel_code))
  }
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
    preserveState: true,
    replace: true,
    onSuccess: (page) => {
      toast.success("Product mapping replicated successfully", { timeout: 3000 })
      emit('modalClose')
    },
    onError: () => {
      toast.error("Failed to replicate product mapping", { timeout: 3000 })
    },
  })
}

</script>