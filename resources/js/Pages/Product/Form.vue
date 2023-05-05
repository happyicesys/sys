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
                <input v-if="permissions.includes('update products')" type="file" @input="form.thumbnail = $event.target.files[0]" class="ml-5 rounded-md border border-gray-300 bg-white py-2 px-3 text-sm font-medium leading-4 text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"/>
                <progress v-if="form.progress" :value="form.progress.percentage" max="100">
                  {{ form.progress.percentage }}%
                </progress>
              </div>
              <div class="text-sm text-red-600" v-if="form.errors.thumbnail">
                {{ form.errors.thumbnail }}
              </div>
            </div>
            <div class="sm:col-span-2">
              <FormInput v-model="form.code" :error="form.errors.code" :disabled="!permissions.includes('update products')" required="true">
                Code
              </FormInput>
            </div>
            <div class="sm:col-span-4">
              <FormInput v-model="form.name" :error="form.errors.name" :disabled="!permissions.includes('update products')" required="true">
                Name
              </FormInput>
            </div>
            <div class="sm:col-span-6">
              <FormTextarea v-model="form.desc" :disabled="!permissions.includes('update products')" :error="form.errors.desc">
                Desc
              </FormTextarea>
            </div>
            <div class="col-span-12 sm:col-span-6">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Operator
              </label>
              <MultiSelect
                v-model="form.operator_id"
                :options="operatorOptions"
                trackBy="id"
                valueProp="id"
                label="full_name"
                placeholder="Select"
                open-direction="top"
                class="mt-1"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.operator_id">
                {{ form.errors.operator_id }}
              </div>
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

              <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3" v-if="form.id">
                <div class="relative">
                  <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-gray-300"></div>
                  </div>
                  <div class="relative flex justify-center">
                    <span class="px-3 bg-white text-lg font-medium text-gray-900"> Unit Cost</span>
                  </div>
                </div>
              </div>


              <div class="sm:col-span-3" v-if="form.id">
                <FormInput v-model="form.unit_cost" :error="form.errors.unit_cost" required="true">
                  Unit Cost
                </FormInput>
              </div>

              <div class="sm:col-span-3" v-if="form.id">
                <DatePicker
                      v-model="form.date_from"
                  >
                  Start Date
                  <span class="text-[9px]">
                    (Leave blank to start NOW)
                  </span>
                </DatePicker>
              </div>

              <div class="sm:col-span-6 flex justify-start" v-if="form.id">
                <Button
                type="button"
                @click="addUnitCost()"
                class="bg-green-500 hover:bg-green-600 text-white"
                :class="[
                  !form.unit_cost || isNaN(form.unit_cost) ?
                  'opacity-50 cursor-not-allowed' : ''
                  ]"
                :disabled="!form.unit_cost || isNaN(form.unit_cost)"
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
                            Unit Cost
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Start Date
                          </th>
                          <th></th>
                        </tr>
                      </thead>
                      <tbody class="bg-white">
                        <tr v-for="(unitCost, unitCostIndex) in product.unitCosts" :key="unitCost.id" :class="unitCostIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                            {{ unitCostIndex + 1 }}
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                            {{ unitCost.cost }}
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                            {{ unitCost.date_from }}
                          </td>
                          <td class="whitespace-nowrap py-4 text-sm text-center">
                          <Button
                            class="bg-red-400 hover:bg-red-500 text-white"
                            @click="removeUnitCost(unitCost)"
                            v-if="!unitCost.id"
                          >
                            <BackspaceIcon class="w-4 h-4"></BackspaceIcon>
                          </Button>
                          <span class="inline-flex items-center rounded-md bg-indigo-50 px-1.5 py-0.5 text-xs font-medium text-indigo-700 ring-1 ring-inset ring-indigo-700/10" v-if="unitCost.is_current">
                            Current
                          </span>
                        </td>
                        </tr>
                        <tr v-if="!product.unitCosts.length">
                          <td colspan="4" class="whitespace-nowrap py-4 text-sm font-medium text-black-600 text-center">
                            No Results Found
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
              <Button type="button" v-if="form.id && permissions.includes('update products')" @click="toggleActivateDeactivate" class="text-white" :class="[form.is_active ? 'bg-red-500 hover:bg-red-600' : 'bg-green-500 hover:bg-green-600']">
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
              <Button type="submit" v-if="permissions.includes('update products')" class="bg-green-500 hover:bg-green-600 text-white flex space-x-1">
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
import DatePicker from '@/Components/DatePicker.vue';
import Uom from '@/Pages/Product/Uom.vue';
import FormInput from '@/Components/FormInput.vue';
import FormTextarea from '@/Components/FormTextarea.vue';
import MultiSelect from '@/Components/MultiSelect.vue'
import Modal from '@/Components/Modal.vue';
import { ArrowUturnLeftIcon, BackspaceIcon, CheckCircleIcon, FolderMinusIcon, FolderPlusIcon, PlusCircleIcon, RectangleStackIcon } from '@heroicons/vue/20/solid';
import { useForm, usePage } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue'
import moment from 'moment';

const props = defineProps({
  categories: Object,
  categoryGroups: Object,
  product: Object,
  uoms: Object,
  type: String,
  showModal: Boolean,
  operatorOptions: Object,
  permissions: [Array, Object],
})

const emit = defineEmits(['modalClose'])

const categoryOptions = ref([])
const categoryGroupOptions = ref([])
const showUomModal = ref(false)
const uomOptions = ref([])
const unitCosts = ref([])
// const productUoms = ref(props.product.productUoms)
const form = ref(
  useForm(getDefaultForm())
)
const operatorOptions = ref([])
const operatorRole = usePage().props.auth.operatorRole

onMounted(() => {
  form.value = props.product ? useForm(props.product) : useForm(getDefaultForm())
  categoryOptions.value = props.categories.data.map((category) => {return {id: category.id, name: category.name}})
  categoryGroupOptions.value = props.categoryGroups.data.map((categoryGroup) => {return {id: categoryGroup.id, name: categoryGroup.name}})
  uomOptions.value = props.uoms.data.map((uom) => {return {id: uom.id, name: uom.name}})
  operatorOptions.value = props.operatorOptions.slice(1)
  unitCosts.value = props.product ? props.product.unitCosts : null
})

function getDefaultForm() {
  return {
    code: '',
    date_from: '',
    desc: '',
    name: '',
    thumbnail: '',
    is_inventory: 1,
    is_commission: '',
    is_supermarket_fee: '',
    category_id: '',
    category_group_id: '',
    operator_id: '',
    unit_cost: '',
    date_from: '',
  }
}

function submit() {
  form.value.clearErrors()

  if(props.type === 'create') {
    form.value
    .transform((data) => ({
      ...data,
      operator_id: data.operator_id.id,
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
        operator_id: data.operator_id.id,
        unitCosts: unitCosts.value,
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

function addUnitCost() {
  unitCosts.value.unshift({
    cost: form.value.unit_cost,
    date_from: form.value.date_from ? form.value.date_from : moment().format('YYYY-MM-DD'),
  })
}

function removeUnitCost(unitCost) {
  unitCosts.value.splice(unitCosts.value.indexOf(unitCost), 1)
}


</script>