<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600" v-if="product">
            Editing
          </span>
          <span v-if="props.product">
            {{ product.name }}
          </span>
          <span class="text-gray-600" v-else>
            Create New Product
          </span>
        </div>
      </template>
      <template #default>
        <form @submit.prevent="submit" id="submit">
          <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">
            <div class="sm:col-span-6 pb-3">
              <div class="mt-1 flex flex-col md:flex-row space-y-2 md:space-y-0 items-center">
                <span class="h-28 w-28 overflow-hidden rounded-full bg-gray-100">
                  <img class="h-28 w-28 rounded-full border" :src="product.thumbnail.full_url" alt="" v-if="product && product.thumbnail"/>
                  <RectangleStackIcon class="h-28 w-28 text-gray-300"></RectangleStackIcon>
                </span>
                <input type="file" @input="form.thumbnail = $event.target.files[0]" class="ml-5 rounded-md border border-gray-300 bg-white py-2 px-3 text-sm font-medium leading-4 text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"/>
                <progress v-if="form.progress" :value="form.progress.percentage" max="100">
                  {{ form.progress.percentage }}%
                </progress>
              </div>
              <div class="text-sm text-red-600" v-if="form.errors.thumbnail">
                {{ form.errors.thumbnail }}
              </div>
            </div>
            <div class="sm:col-span-2">
              <FormInput v-model="form.code" :error="form.errors.code" required="true">
                Code
              </FormInput>
            </div>
            <div class="sm:col-span-4">
              <FormInput v-model="form.name" :error="form.errors.name" required="true">
                Name
              </FormInput>
            </div>
            <div class="sm:col-span-6">
              <FormTextarea v-model="form.desc" :error="form.errors.desc">
                Desc
              </FormTextarea>
            </div>
            <!-- <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Category
              </label>
              <MultiSelect
                v-model="form.category_id"
                :options="categoryOptions"
                trackBy="id"
                valueProp="id"
                label="name"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.category_id">
                {{ form.errors.category_id }}
              </div>
            </div>
            <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Group
              </label>
              <MultiSelect
                v-model="form.category_group_id"
                :options="categoryGroupOptions"
                trackBy="id"
                valueProp="id"
                label="name"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.category_group_id">
                {{ form.errors.category_group_id }}
              </div>
            </div> -->
            <!-- <div class="sm:col-span-6 pt-2">
              <div class="flex md:justify-between flex-col space-y-3 md:flex-row md:space-y-0">
                <div class="relative flex items-start">
                  <div class="flex h-5 items-center">
                    <input id="candidates" v-model="form.is_inventory" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-75" />
                  </div>
                  <div class="ml-3 text-sm">
                    <label for="candidates" class="font-medium text-gray-700">Is Inventory?</label>
                  </div>
                </div>

                <div class="relative flex items-start">
                  <div class="flex h-5 items-center">
                    <input id="candidates" v-model="form.is_commission" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-75" :disabled="form.is_inventory"/>
                  </div>
                  <div class="ml-3 text-sm">
                    <label for="candidates" class="font-medium text-gray-700" :class="[form.is_inventory ? 'text-gray-400' : '']">Is Commission?</label>
                  </div>
                </div>

                <div class="relative flex items-start">
                  <div class="flex h-5 items-center">
                    <input id="candidates" v-model="form.is_supermarket_fee" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-75" :disabled="form.is_inventory"/>
                  </div>
                  <div class="ml-3 text-sm">
                    <label for="candidates" class="font-medium text-gray-700" :class="[form.is_inventory ? 'text-gray-400' : '']">Is Supermarket Fee?</label>
                  </div>
                </div>
              </div>
            </div> -->
            <!-- <div class="sm:col-span-6">
              <div class="mt-8 flex flex-col">
                <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
                  <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
                    <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                      <table class="min-w-full divide-y divide-gray-300">
                        <thead class="bg-gray-50">
                          <tr>
                            <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                              #
                            </th>
                            <th scope="col" class="py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 sm:pl-6">
                              UOM Name
                            </th>
                            <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                              Value
                            </th>
                            <th scope="col" colspan="2" class="px-3 py-3.5 text-right text-sm font-semibold text-gray-900">
                              <Button type="button" @click.prevent="onUomModalClicked" class="bg-green-500 hover:bg-green-600 text-white flex space-x-1 px-3 py-1">
                                <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                                <span>
                                  New UOM
                                </span>
                              </Button>
                            </th>
                          </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                          <tr v-for="(productUom, productUomIndex) in productUoms" :key="productUom.id">
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                              {{ productUomIndex + 1 }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-700 text-center">
                              {{ productUom.uom.name }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-700 text-center">
                              {{ productUom.value }}
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-700 text-center">
                              <div class="flex flex-col space-y-1 justify-center">
                                <div>
                                  <span class="inline-flex items-center rounded bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-800" v-if="productUom.is_base_uom">
                                    base_uom
                                  </span>
                                </div>
                                <div>
                                  <span class="inline-flex items-center rounded-md bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800" v-if="productUom.is_transaction_uom">
                                    transacted_uom
                                  </span>
                                </div>
                              </div>
                            </td>
                            <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-700 text-center">
                              <Button
                                type="button" class="bg-red-300 hover:bg-red-400 px-2 py-2 text-xs text-red-800 flex space-x-1"
                                @click="onProductUomDeleted(productUom)"
                              >
                                <XCircleIcon class="w-4 h-4"></XCircleIcon>
                              </Button>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            </div> -->
          </div>
          <div class="sm:col-span-6">
            <div class="flex space-x-1 mt-5 pt-5 justify-end">
              <Button
                class="bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1"
                @click.prevent="$emit('modalClose')"
                form="submit"
              >
                <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
                <span>
                  Back
                </span>
              </Button>
              <Button type="button" v-if="form.id" @click="toggleActivateDeactivate" class="text-white" :class="[form.is_active ? 'bg-red-500 hover:bg-red-600' : 'bg-green-500 hover:bg-green-600']">
                <div>
                  <span class="flex space-x-1 items-center" v-if="form.is_active">
                    <FolderMinusIcon class="w-4 h-4"></FolderMinusIcon>
                    <span>
                      Deactivate
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

    <Uom
      v-if="showUomModal"
      :product="product"
      :uoms = "uoms"
      :showModal="showUomModal"
      @modalClose="onUomModalClose"
    >
    </Uom>
  </Teleport>
</template>

<script setup>
import Button from '@/Components/Button.vue';
import Uom from '@/Pages/Product/Uom.vue';
import FormInput from '@/Components/FormInput.vue';
import FormTextarea from '@/Components/FormTextarea.vue';
import MultiSelect from '@/Components/MultiSelect.vue'
import Modal from '@/Components/Modal.vue';
import { ArrowUturnLeftIcon, CheckCircleIcon, FolderMinusIcon, FolderPlusIcon, RectangleStackIcon, XCircleIcon } from '@heroicons/vue/20/solid';
import { useForm } from '@inertiajs/inertia-vue3';
import { ref, onMounted } from 'vue'

const props = defineProps({
  categories: Object,
  categoryGroups: Object,
  product: Object,
  uoms: Object,
  type: String,
  showModal: Boolean,
})

const emit = defineEmits(['modalClose'])

const categoryOptions = ref([])
const categoryGroupOptions = ref([])
const showUomModal = ref(false)
const uomOptions = ref([])
// const productUoms = ref(props.product.productUoms)
const form = ref(
  useForm(getDefaultForm())
)

onMounted(() => {
  form.value = props.product ? useForm(props.product) : useForm(getDefaultForm())
  categoryOptions.value = props.categories.data.map((category) => {return {id: category.id, name: category.name}})
  categoryGroupOptions.value = props.categoryGroups.data.map((categoryGroup) => {return {id: categoryGroup.id, name: categoryGroup.name}})
  uomOptions.value = props.uoms.data.map((uom) => {return {id: uom.id, name: uom.name}})
  // console.log(JSON.parse(JSON.stringify(props.uoms)))
})

function getDefaultForm() {
  return {
    code: '',
    desc: '',
    name: '',
    thumbnail: '',
    is_inventory: 1,
    is_commission: '',
    is_supermarket_fee: '',
    category_id: '',
    category_group_id: '',
  }
}

function submit() {
  form.value.clearErrors()

  if(props.type === 'create') {
    form.value
    .transform((data) => ({
      ...data,
      // category_id: data.category_id.id,
      // category_group_id: data.category_group_id.id,
    }))
    .post('/products/create', {
      onSuccess: () => {
        emit('modalClose')
      },
      preserveState: true,
      replace: true,
    })
  }

  if(props.type === 'update') {
    form.value
      .transform((data) => ({
        ...data,
        // category_id: data.category_id.id,
        // category_group_id: data.category_group_id.id,
      }))
      .post('/products/' + form.value.id + '/update', {
      onSuccess: () => {
        emit('modalClose')
      },
      preserveState: true,
      replace: true,
    })
  }
}

function toggleActivateDeactivate() {
  form.value.post('/products/' + form.value.id + '/toggle-activate-deactivate', {
    onSuccess: () => {
      emit('modalClose')
    },
      preserveState: true,
      replace: true,
  })
}

function onProductUomDeleted(productUom) {
  form.value.delete('/products/product-uoms/' + productUom.id, {
    onSuccess: () => {
      emit('modalClose')
    },
    preserveState: true,
    resetOnSuccess: true,
    replace: true,
  })
}

function onUomModalClicked() {
  showUomModal.value = true
}

function onUomModalClose() {
  showUomModal.value = false
}


</script>