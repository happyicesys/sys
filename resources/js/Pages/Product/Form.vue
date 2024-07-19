<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600" v-if="product">
            Editing
          </span>
          <span v-if="product">
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
                  <a :href="product.thumbnail.full_url" target="_blank" v-if="product && product.thumbnail">
                    <img class="h-28 w-28 rounded-full border" :src="product.thumbnail.full_url" alt="" v-if="product && product.thumbnail"/>
                    <RectangleStackIcon class="h-28 w-28 text-gray-300"></RectangleStackIcon>
                  </a>
                </span>
                <input v-if="permissions.includes('update products')" type="file" @input="form.thumbnail = $event.target.files[0]" class="ml-5 rounded-md border border-gray-300 bg-white py-2 px-3 text-sm font-medium leading-4 text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"/>
                <progress v-if="form.progress" :value="form.progress.percentage" max="100">
                  {{ form.progress.percentage }}%
                </progress>
                <span class="text-sm p-2 text-red-600">
                  * Image file, max 500kb
                </span>
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
            <div class="sm:col-span-2">
              <FormInput v-model="form.measurement_count" :error="form.errors.measurement_count" :disabled="!permissions.includes('update products')">
                Lowest Unit
              </FormInput>
            </div>
            <div class="sm:col-span-2">
              <FormInput v-model="form.measurement_value" :error="form.errors.measurement_value" :disabled="!permissions.includes('update products')">
                Volume/ Weight
              </FormInput>
            </div>
            <div class="sm:col-span-2">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                UOM
              </label>
              <MultiSelect
                v-model="form.measurement_unit"
                :options="measurementUnitOptions"
                trackBy="id"
                valueProp="id"
                label="name"
                placeholder="Select"
                open-direction="top"
                class="mt-1"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.measurement_unit">
                {{ form.errors.measurement_unit }}
              </div>
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Operator
                <span class="text-red-500">
                   *
                </span>
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
                <span class="text-xs text-gray-500">
                  (before GST)
                </span>
              </FormInput>
            </div>
            <div class="sm:col-span-3" v-if="form.id">
              <DatePicker v-model="form.date_from">
                Start Date
                <span class="text-[9px]">
                  (Leave blank to start NOW)
                </span>
              </DatePicker>
            </div>
            <div class="sm:col-span-6 flex justify-start" v-if="form.id">
              <Button
                type="button"
                @click="addUnitCost"
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
                            Unit Cost <br>
                            <span class="text-xs text-gray-500">
                              (before GST)
                            </span>
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Start Date
                          </th>
                          <th></th>
                        </tr>
                      </thead>
                      <tbody class="bg-white">
                        <tr v-for="(unitCost, unitCostIndex) in unitCosts" :key="unitCostIndex" :class="unitCostIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                            {{ unitCostIndex + 1 }}
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                            {{ unitCost.cost ? unitCost.cost.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : 0 }}
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                            {{ unitCost.date_from }}
                          </td>
                          <td class="whitespace-nowrap py-4 text-sm text-center">
                            <Button
                              class="bg-red-400 hover:bg-red-500 text-white"
                              @click.prevent="removeUnitCost(unitCost)"
                            >
                              <BackspaceIcon class="w-4 h-4"></BackspaceIcon>
                            </Button>
                          </td>
                        </tr>
                        <tr v-if="!unitCosts.length">
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
            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3" v-if="form.id">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center">
                  <span class="px-3 bg-white text-lg font-medium text-gray-900"> Selling Price(s)</span>
                </div>
              </div>
            </div>
            <div class="sm:col-span-3" v-if="form.id">
              <FormInput v-model="form.selling_price_amount" :error="form.errors.unit_cost" placeholder="Number" required="true">
                Amount
              </FormInput>
            </div>
            <div class="sm:col-span-3" v-if="form.id">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Ref Price Type
              </label>
              <MultiSelect
                v-model="form.selling_price_type"
                :options="priceTypeOptions"
                trackBy="id"
                valueProp="id"
                label="name"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
            </div>
            <div class="sm:col-span-6 flex justify-start" v-if="form.id">
              <Button
                type="button"
                @click="addSellingPrice"
                class="bg-green-500 hover:bg-green-600 text-white"
                :class="[
                  !form.selling_price_amount || isNaN(form.selling_price_amount) || !form.selling_price_type ?
                  'opacity-50 cursor-not-allowed' : ''
                  ]"
                :disabled="!form.selling_price_amount || isNaN(form.selling_price_amount) || !form.selling_price_type"
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
                            Amount
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Ref Price Type
                          </th>
                          <th></th>
                        </tr>
                      </thead>
                      <tbody class="bg-white">
                        <tr v-for="(sellingPrice, sellingPriceIndex) in sellingPrices" :key="sellingPriceIndex" :class="sellingPriceIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                            {{ sellingPriceIndex + 1 }}
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                            {{ sellingPrice.id ? (sellingPrice.amount/ (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) : sellingPrice.amount }}
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                            {{ sellingPrice.type }}
                          </td>
                          <td class="whitespace-nowrap py-4 text-sm text-center">
                            <Button
                              class="bg-red-400 hover:bg-red-500 text-white"
                              @click.prevent="removeSellingPrice(sellingPrice)"
                            >
                              <BackspaceIcon class="w-4 h-4"></BackspaceIcon>
                            </Button>
                          </td>
                        </tr>
                        <tr v-if="!sellingPrices.length">
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
            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3" v-if="form.id">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center">
                  <span class="px-3 bg-white text-lg font-medium text-gray-900"> Translated Name</span>
                </div>
              </div>
            </div>
            <div class="sm:col-span-3" v-if="form.id">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Language
              </label>
              <MultiSelect
                v-model="form.language"
                :options="languageOptions"
                trackBy="id"
                valueProp="id"
                label="name"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
            </div>
            <div class="sm:col-span-3" v-if="form.id">
              <FormInput v-model="form.translated_name" :error="form.errors.translated_name">
                Translated Name
              </FormInput>
            </div>
            <div class="sm:col-span-6 flex justify-start" v-if="form.id">
              <Button
                type="button"
                @click="addLanguage"
                class="bg-green-500 hover:bg-green-600 text-white"
                :class="[
                  !form.translated_name ?
                  'opacity-50 cursor-not-allowed' : ''
                  ]"
                :disabled="!form.translated_name"
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
                            Language
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Translated Name
                          </th>
                          <th></th>
                        </tr>
                      </thead>
                      <tbody class="bg-white">
                        <tr v-for="(language, languageIndex) in languages" :key="languageIndex" :class="languageIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                            {{ languageIndex + 1 }}
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                            {{ language.language }}
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                            {{ language.name }}
                          </td>
                          <td class="whitespace-nowrap py-4 text-sm text-center">
                            <Button
                              class="bg-red-400 hover:bg-red-500 text-white"
                              @click.prevent="removeLanguage(languageIndex)"
                            >
                              <BackspaceIcon class="w-4 h-4"></BackspaceIcon>
                            </Button>
                          </td>
                        </tr>
                        <tr v-if="!languages.length">
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
      :uoms="uoms"
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
import MultiSelect from '@/Components/MultiSelect.vue';
import Modal from '@/Components/Modal.vue';
import { ArrowUturnLeftIcon, BackspaceIcon, CheckCircleIcon, FolderMinusIcon, FolderPlusIcon, PlusCircleIcon, RectangleStackIcon } from '@heroicons/vue/20/solid';
import { useForm, usePage } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import moment from 'moment';

const props = defineProps({
  categories: Object,
  categoryGroups: Object,
  languageOptions: [Array, Object],
  measurementUnitOptions: Object,
  priceTypeOptions: Object,
  product: Object,
  uoms: Object,
  type: String,
  showModal: Boolean,
  operatorOptions: Object,
  permissions: [Array, Object],
});

const emit = defineEmits(['modalClose']);

const categoryOptions = ref([]);
const categoryGroupOptions = ref([]);
const measurementUnitOptions = ref([]);
const showUomModal = ref(false);
const uomOptions = ref([]);
const unitCosts = ref([]);
const form = ref(useForm(getDefaultForm()));
const languages = ref([]);
const languageOptions = ref([]);
const operatorCountry = usePage().props.auth.operatorCountry;
const operatorOptions = ref([]);
const operatorRole = usePage().props.auth.operatorRole;
const priceTypeOptions = ref([]);
const sellingPrices = ref([]);

onMounted(() => {
  form.value = props.product ? useForm(props.product) : useForm(getDefaultForm());
  categoryOptions.value = props.categories.data.map(category => ({ id: category.id, name: category.name }));
  categoryGroupOptions.value = props.categoryGroups.data.map(categoryGroup => ({ id: categoryGroup.id, name: categoryGroup.name }));
  languageOptions.value = Object.entries(props.languageOptions).map(([id, name]) => ({ id, name }));
  measurementUnitOptions.value = Object.keys(props.measurementUnitOptions).map(measurementUnit => ({ id: measurementUnit, name: measurementUnit }));
  priceTypeOptions.value = Object.entries(props.priceTypeOptions).map(([id, name]) => ({ id, name }));
  uomOptions.value = props.uoms.data.map(uom => ({ id: uom.id, name: uom.name }));
  operatorOptions.value = props.operatorOptions.slice(1);
  sellingPrices.value = props.product ? props.product.sellingPrices : [];
  unitCosts.value = props.product ? (Array.isArray(props.product.unitCosts) ? props.product.unitCosts : []) : [];
  languages.value = props.product ? (props.product.translated_names_json ? props.product.translated_names_json : []) : [];

  priceTypeOptions.value = priceTypeOptions.value.filter(priceTypeOption => {
    return !sellingPrices.value.some(sellingPrice => sellingPrice.type === priceTypeOption.id);
  });
});

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
    measurement_count: '',
    measurement_value: '',
    measurement_unit: '',
    operator_id: '',
    selling_price_amount: '',
    selling_price_type: '',
    unit_cost: '',
    date_from: '',
  };
}

function submit() {
  form.value.clearErrors();

  if (props.type === 'create') {
    form.value
      .transform(data => ({
        ...data,
        measurement_unit: data.measurement_unit.id,
        operator_id: data.operator_id.id,
      }))
      .post('/products/create', {
        onSuccess: () => {
          emit('modalClose');
        },
        preserveState: true,
        replace: true,
      });
  }

  if (props.type === 'update') {
    form.value
      .transform(data => ({
        ...data,
        measurement_unit: data.measurement_unit.id,
        operator_id: data.operator_id.id,
        unitCosts: unitCosts.value,
        languages: languages.value,
        sellingPrices: sellingPrices.value,
      }))
      .post('/products/' + form.value.id + '/update', {
        onSuccess: () => {
          emit('modalClose');
        },
        preserveScroll: true,
        preserveState: true,
        replace: true,
      });
  }
}

function toggleActivateDeactivate() {
  form.value.post('/products/' + form.value.id + '/toggle-activate-deactivate', {
    onSuccess: () => {
      emit('modalClose');
    },
    preserveState: true,
    replace: true,
  });
}

function onProductUomDeleted(productUom) {
  form.value.delete('/products/product-uoms/' + productUom.id, {
    onSuccess: () => {
      emit('modalClose');
    },
    preserveState: true,
    resetOnSuccess: true,
    replace: true,
  });
}

function onUomModalClicked() {
  showUomModal.value = true;
}

function onUomModalClose() {
  showUomModal.value = false;
}

function addUnitCost() {
  if (!Array.isArray(unitCosts.value)) {
    unitCosts.value = [];
  }

  unitCosts.value.unshift({
    cost: form.value.unit_cost,
    date_from: form.value.date_from ? form.value.date_from : moment().format('YYYY-MM-DD'),
  });
}

function removeUnitCost(unitCost) {
  unitCosts.value.splice(unitCosts.value.indexOf(unitCost), 1);
}

function addLanguage() {
  languages.value.push({
    id: form.value.language.id,
    language: form.value.language.name,
    name: form.value.translated_name,
  });
  form.value.language = '';
  form.value.translated_name = '';
}

function removeLanguage(key) {
  languages.value.splice(key, 1);
}

function addSellingPrice() {
  sellingPrices.value.push({
    amount: form.value.selling_price_amount,
    type: form.value.selling_price_type.id,
  });
  form.value.selling_price_amount = '';
  form.value.selling_price_type = '';
  priceTypeOptions.value = priceTypeOptions.value.filter(priceTypeOption => {
    return !sellingPrices.value.some(sellingPrice => sellingPrice.type === priceTypeOption.id);
  });
}

function removeSellingPrice(sellingPrice) {
  if (sellingPrice.id) {
    form.value.delete('/products/selling-prices/' + sellingPrice.id, {
      onSuccess: () => {
        emit('modalClose');
      },
      preserveState: true,
      resetOnSuccess: true,
      replace: true,
    });
  } else {
    sellingPrices.value.splice(sellingPrices.value.indexOf(sellingPrice), 1);
    priceTypeOptions.value = priceTypeOptions.value.filter(priceTypeOption => {
      return !sellingPrices.value.some(sellingPrice => sellingPrice.type === priceTypeOption.id);
    });
  }
}
</script>
