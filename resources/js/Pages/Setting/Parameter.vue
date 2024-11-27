<template>
  <Head title="Edit Machine Parameters" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Editing Machine Parameters
        {{ vend.code }}
      </h2>
    </template>

     <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
        <div class="shadow-sm ring-1 ring-black ring-opacity-5 p-5 mb-3">
          <form @submit.prevent="submit" id="submit">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-6 pb-5 mb-3">
            <div class="sm:col-span-5" v-if="vend">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Machine ID#
              </label>
              <div class="mt-1">
                <input
                  type="text"
                  class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                  :value="vend.code"
                  disabled
                />
              </div>
            </div>

            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-start">
                  <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> Campaigns </span>
                </div>
              </div>
            </div>

            <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Enable Promo Header Text?
              </label>
              <span class="text-xs text-gray-600">
                (Enable Main Banner text show on MainView)
              </span>
              <MultiSelect
                v-model="form.enablePromoHeaderText"
                :options="booleanStrictOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
            </div>

            <div class="sm:col-span-5">
              <FormInput v-model="form.promoHeaderText">
                <div class="flex flex-col space-y-1">
                  <span>
                    Promo Header Text
                  </span>
                  <span class="text-xs text-gray-600">
                    (Set Main Banner text)
                  </span>
                </div>
              </FormInput>
            </div>

            <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Enable Header Text Running?
              </label>
              <span class="text-xs text-gray-600">
                (Main Banner show as running text, default is line by line)
              </span>
              <MultiSelect
                v-model="form.enableHeaderTextRunning"
                :options="booleanStrictOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
            </div>

            <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Promo Banner Kind
              </label>
              <span class="text-xs text-gray-600">
                (MainView Campaign background type picture/video)
              </span>
              <MultiSelect
                v-model="form.promoBannerKind"
                :options="promoBannerKindOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
            </div>

            <div class="sm:col-span-3">
              <DatePicker v-model="form.headerTextStartDate">
                <div class="flex flex-col space-y-1">
                  <span>
                    Header Text Start Date
                  </span>
                  <span class="text-xs text-gray-600">
                    (Main Banner starting time)
                  </span>
                </div>
              </DatePicker>
            </div>

            <div class="sm:col-span-3">
              <DatePicker v-model="form.headerTextEndDate">
                <div class="flex flex-col space-y-1">
                  <span>
                    Header Text End Date
                  </span>
                  <span class="text-xs text-gray-600">
                    (Main Banner ending time)
                  </span>
                </div>
              </DatePicker>
            </div>

            <hr class="sm:col-span-6 my-2">

            <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Enable Promo Running Text?
              </label>
              <span class="text-xs text-gray-600">
                (Enable Promo Banner text show on SoftKeybadView)
              </span>
              <MultiSelect
                v-model="form.enablePromoRunningText"
                :options="booleanStrictOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
            </div>

            <div class="sm:col-span-5">
              <FormInput v-model="form.promoRunningText">
                <div class="flex flex-col space-y-1">
                  <span>
                    Promo Running Text
                  </span>
                  <span class="text-xs text-gray-600">
                    (Set Promo Banner text)
                  </span>
                </div>
              </FormInput>
            </div>

            <div class="sm:col-span-3">
              <DatePicker v-model="form.runningTextStartDate">
                <div class="flex flex-col space-y-1">
                  <span>
                    Running Text Start Date
                  </span>
                  <span class="text-xs text-gray-600">
                    (Promo Banner text starting time)
                  </span>
                </div>
              </DatePicker>
            </div>

            <div class="sm:col-span-3">
              <DatePicker v-model="form.runningTextEndDate">
                <div class="flex flex-col space-y-1">
                  <span>
                    Running Text End Date
                  </span>
                  <span class="text-xs text-gray-600">
                    (Promo Banner text ending time)
                  </span>
                </div>
              </DatePicker>
            </div>

            <hr class="sm:col-span-6 my-2">

            <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Disable P1 P2 Cross Group?
              </label>
              <span class="text-xs text-gray-600">
                (Disable Old Discount Logic cross group, old discount logic can cross group to apply)
              </span>
              <MultiSelect
                v-model="form.disableP1P2CrossGrp"
                :options="booleanStrictOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
            </div>

            <hr class="sm:col-span-6 my-2">

            <div class="sm:col-span-4">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Enable Buy 1 Free 1?
              </label>
              <span class="text-xs text-gray-600">
                (Enable buy1free1 Campaign)
              </span>
              <MultiSelect
                v-model="form.enableBuy1Free1"
                :options="booleanStrictOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
            </div>

            <div class="sm:col-span-3">
              <FormInput v-model="form.buy1free1X">
                <div class="flex flex-col space-y-1">
                  <span>
                    Buy 1 Free 1 (Buy Group)
                  </span>
                  <span class="text-xs text-gray-600">
                    (buy1free1 buy group, *cannot same with buy2free1X, numbers only)
                  </span>
                </div>
              </FormInput>
            </div>

            <div class="sm:col-span-3">
              <FormInput v-model="form.buy1free1Y">
                <div class="flex flex-col space-y-1">
                  <span>
                    Buy 1 Free 1 (Free Group)
                  </span>
                  <span class="text-xs text-gray-600">
                    (buy1free1 free group, numbers only)
                  </span>
                </div>
              </FormInput>
            </div>

            <div class="sm:col-span-3">
              <DatePicker v-model="form.buy1free1StartDate">
                <div class="flex flex-col space-y-1">
                  <span>
                    Buy 1 Free 1 Start Date
                  </span>
                  <span class="text-xs text-gray-600">
                    (buy1free1 campaign starting time)
                  </span>
                </div>
              </DatePicker>
            </div>

            <div class="sm:col-span-3">
              <DatePicker v-model="form.buy1free1EndDate">
                <div class="flex flex-col space-y-1">
                  <span>
                    Buy 1 Free 1 End Date
                  </span>
                  <span class="text-xs text-gray-600">
                    (buy1free1 campaign ending time)
                  </span>
                </div>
              </DatePicker>
            </div>

            <hr class="sm:col-span-6 my-2">

            <div class="sm:col-span-4">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Enable Buy 2 Free 1?
              </label>
              <span class="text-xs text-gray-600">
                (Enable buy2free1 Campaign)
              </span>
              <MultiSelect
                v-model="form.enableBuy2Free1"
                :options="booleanStrictOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
            </div>

            <div class="sm:col-span-3">
              <FormInput v-model="form.buy2free1X">
                <div class="flex flex-col space-y-1">
                  <span>
                    Buy 2 Free 1 (Buy Group)
                  </span>
                  <span class="text-xs text-gray-600">
                    (buy2free1 buy group, *cannot same with buy1free1X, numbers only)
                  </span>
                </div>
              </FormInput>
            </div>

            <div class="sm:col-span-3">
              <FormInput v-model="form.buy2free1Y">
                <div class="flex flex-col space-y-1">
                  <span>
                    Buy 2 Free 1 (Free Group)
                  </span>
                  <span class="text-xs text-gray-600">
                    (buy2free1 buy group, numbers only)
                  </span>
                </div>
              </FormInput>
            </div>

            <div class="sm:col-span-3">
              <DatePicker v-model="form.buy2free1StartDate">
                <div class="flex flex-col space-y-1">
                  <span>
                    Buy 2 Free 1 Start Date
                  </span>
                  <span class="text-xs text-gray-600">
                    (buy2free1 campaign starting time)
                  </span>
                </div>
              </DatePicker>
            </div>

            <div class="sm:col-span-3">
              <DatePicker v-model="form.buy2free1EndDate">
                <div class="flex flex-col space-y-1">
                  <span>
                    Buy 2 Free 1 End Date
                  </span>
                  <span class="text-xs text-gray-600">
                    (buy2free1 campaign ending time)
                  </span>
                </div>
              </DatePicker>
            </div>

            <hr class="sm:col-span-6 my-2">

            <div class="sm:col-span-4">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Enable Bundle Discount?
              </label>
              <span class="text-xs text-gray-600">
                (Enable bundle discount Campaign)
              </span>
              <MultiSelect
                v-model="form.enableBundleDiscount"
                :options="booleanStrictOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
            </div>

            <div class="sm:col-span-3">
              <DatePicker v-model="form.bundleStartDate">
                <div class="flex flex-col space-y-1">
                  <span>
                    Bundle Start Date
                  </span>
                  <span class="text-xs text-gray-600">
                    (bundle discount Campaign starting time)
                  </span>
                </div>
              </DatePicker>
            </div>

            <div class="sm:col-span-3">
              <DatePicker v-model="form.bundleEndDate">
                <div class="flex flex-col space-y-1">
                  <span>
                    Bundle End Date
                  </span>
                  <span class="text-xs text-gray-600">
                    (bundle discount Campaign ending time)
                  </span>
                </div>
              </DatePicker>
            </div>

            <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Enable Buy 2 Get Discount?
              </label>
              <span class="text-xs text-gray-600">
                (Enable buy2 get discount)
              </span>
              <MultiSelect
                v-model="form.enableDiscount01"
                :options="booleanStrictOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
            </div>

            <div class="sm:col-span-3">
              <FormInput v-model="form.discountPercent01">
                <div class="flex flex-col space-y-1">
                  <span>
                    Buy 2 Discount %
                  </span>
                  <span class="text-xs text-gray-600">
                    (set buy2 get how many % discount, *default 10%)
                  </span>
                </div>
              </FormInput>
            </div>

            <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Enable Buy 3 Get Discount?
              </label>
              <span class="text-xs text-gray-600">
                (Enable buy3 get discount)
              </span>
              <MultiSelect
                v-model="form.enableDiscount02"
                :options="booleanStrictOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
            </div>

            <div class="sm:col-span-3">
              <FormInput v-model="form.discountPercent02">
                <div class="flex flex-col space-y-1">
                  <span>
                    Buy 3 Discount %
                  </span>
                  <span class="text-xs text-gray-600">
                    (set buy3 get how many % discount, *default 10%)
                  </span>
                </div>
              </FormInput>
            </div>

            <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Enable Buy 4 Get Discount?
              </label>
              <span class="text-xs text-gray-600">
                (Enable buy4 get discount)
              </span>
              <MultiSelect
                v-model="form.enableDiscount03"
                :options="booleanStrictOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
            </div>

            <div class="sm:col-span-3">
              <FormInput v-model="form.discountPercent03">
                <div class="flex flex-col space-y-1">
                  <span>
                    Buy 4 Discount %
                  </span>
                  <span class="text-xs text-gray-600">
                    (set buy4 get how many % discount, *default 10%)
                  </span>
                </div>
              </FormInput>
            </div>

            <div class="sm:col-span-6 py-4">
              <span class="flex space-x-1">
                <Button
                  type="button"
                  class="bg-green-500 hover:bg-green-600 text-white flex space-x-1"
                  @click.prevent="saveParameterSettings()"
                >
                  <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                  <span>
                    Save Parameter Settings
                  </span>
                </Button>
              </span>
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
import AttachmentList from '@/Components/AttachmentList.vue';
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import DatePicker from '@/Components/DatePicker.vue';
import FormInput from '@/Components/FormInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import SearchAddressInput from '@/Components/SearchAddressInput.vue';
import { CheckCircleIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { fromPairs } from 'lodash';
import { useToast } from "vue-toastification";

const props = defineProps({
    operatorOptions: Object,
    type: String,
    vend: Object,
  })

const booleanStrictOptions = ref([
  {id: 'true', value: 'Yes'},
  {id: 'false', value: 'No'},
])

const promoBannerKindOptions = ref([
  {id: 'video', value: 'Video'},
  {id: 'picture', value: 'Picture'},
])

const operatorOptions = ref([])
const permissions = usePage().props.auth.permissions
const toast = useToast()
const form = ref(
  useForm(getDefaultForm())
)
const vend = ref([])

onMounted(() => {
  vend.value = props.vend.data
  operatorOptions.value = props.operatorOptions.data

  form.value = props.vend?.data.settings_parameter_json ? useForm({
    ...JSON.parse(JSON.stringify(props.vend.data.settings_parameter_json)),
    enablePromoHeaderText: booleanStrictOptions.value.find(
      option => option.id === props.vend.data.settings_parameter_json.enablePromoHeaderText
    ),
    enableHeaderTextRunning: booleanStrictOptions.value.find(
      option => option.id === props.vend.data.settings_parameter_json.enableHeaderTextRunning
    ),
    promoBannerKind: promoBannerKindOptions.value.find(
      option => option.id === props.vend.data.settings_parameter_json.promoBannerKind
    ),
    enablePromoRunningText: booleanStrictOptions.value.find(
      option => option.id === props.vend.data.settings_parameter_json.enablePromoRunningText
    ),
    disableP1P2CrossGrp: booleanStrictOptions.value.find(
      option => option.id === props.vend.data.settings_parameter_json.disableP1P2CrossGrp
    ),
    enableBuy1Free1: booleanStrictOptions.value.find(
      option => option.id === props.vend.data.settings_parameter_json.enableBuy1Free1
    ),
    enableBuy2Free1: booleanStrictOptions.value.find(
      option => option.id === props.vend.data.settings_parameter_json.enableBuy2Free1
    ),
    enableBundleDiscount: booleanStrictOptions.value.find(
      option => option.id === props.vend.data.settings_parameter_json.enableBundleDiscount
    ),
    enableDiscount01: booleanStrictOptions.value.find(
      option => option.id === props.vend.data.settings_parameter_json.enableDiscount01
    ),
    enableDiscount02: booleanStrictOptions.value.find(
      option => option.id === props.vend.data.settings_parameter_json.enableDiscount02
    ),
    enableDiscount03: booleanStrictOptions.value.find(
      option => option.id === props.vend.data.settings_parameter_json.enableDiscount03
    ),

  }) : useForm(getDefaultForm())

  // init for default
  if (!form.value.enablePromoHeaderText) {
    form.value.enablePromoHeaderText = booleanStrictOptions.value.find(
      option => option.id === 'false' // or 'true' if you want "Yes" selected
    );
  }
  if(!form.value.enableHeaderTextRunning) {
    form.value.enableHeaderTextRunning = booleanStrictOptions.value.find(
      option => option.id === 'false' // or 'true' if you want "Yes" selected
    );
  }
  if(!form.value.promoBannerKind) {
    form.value.promoBannerKind = promoBannerKindOptions.value.find(
      option => option.id === 'video' // or 'true' if you want "Yes" selected
    );
  }
  if(!form.value.enablePromoRunningText) {
    form.value.enablePromoRunningText = booleanStrictOptions.value.find(
      option => option.id === 'false' // or 'true' if you want "Yes" selected
    );
  }
  if(!form.value.disableP1P2CrossGrp) {
    form.value.disableP1P2CrossGrp = booleanStrictOptions.value.find(
      option => option.id === 'false' // or 'true' if you want "Yes" selected
    );
  }
  if(!form.value.enableBuy1Free1) {
    form.value.enableBuy1Free1 = booleanStrictOptions.value.find(
      option => option.id === 'false' // or 'true' if you want "Yes" selected
    );
  }
  if(!form.value.buy1free1X) {
    form.value.buy1free1X = 0
  }
  if(!form.value.buy1free1X) {
    form.value.buy1free1Y = 0
  }
  if(!form.value.enableBuy2Free1) {
    form.value.enableBuy2Free1 = booleanStrictOptions.value.find(
      option => option.id === 'false' // or 'true' if you want "Yes" selected
    );
  }
  if(!form.value.buy2free1X) {
    form.value.buy2free1X = 1
  }
  if(!form.value.buy2free1Y) {
    form.value.buy2free1Y = 0
  }
  if(!form.value.enableBundleDiscount) {
    form.value.enableBundleDiscount = booleanStrictOptions.value.find(
      option => option.id === 'false' // or 'true' if you want "Yes" selected
    );
  }
  if(!form.value.enableDiscount01) {
    form.value.enableDiscount01 = booleanStrictOptions.value.find(
      option => option.id === 'true' // or 'true' if you want "Yes" selected
    );
  }
  if(!form.value.discountPercent01) {
    form.value.discountPercent01 = 1
  }
  if(!form.value.discountPercent02) {
    form.value.discountPercent02 = 1
  }
  if(!form.value.discountPercent03) {
    form.value.discountPercent03 = 1
  }
  if(!form.value.enableDiscount02) {
    form.value.enableDiscount02 = booleanStrictOptions.value.find(
      option => option.id === 'false' // or 'true' if you want "Yes" selected
    );
  }
  if(!form.value.enableDiscount03) {
    form.value.enableDiscount03 = booleanStrictOptions.value.find(
      option => option.id === 'false' // or 'true' if you want "Yes" selected
    );
  }

})

function getDefaultForm() {
  return {
    enablePromoHeaderText: '',
    promoHeaderText: '',
    enableHeaderTextRunning: '',
    promoBannerKind: '',
    headerTextStartDate: '',
    headerTextEndDate: '',

    enablePromoRunningText: '',
    promoRunningText: '',
    runningTextStartDate: '',
    runningTextEndDate: '',

    disableP1P2CrossGrp: '',

    enableBuy1Free1: '',
    buy1free1X: '',
    buy1free1Y: '',
    buy1free1StartDate: '',
    buy1free1EndDate: '',

    enableBuy2Free1: '',
    buy2free1X: '',
    buy2free1Y: '',
    buy2free1StartDate: '',
    buy2free1EndDate: '',

    enableBundleDiscount: '',
    bundleStartDate: '',
    bundleEndDate: '',
    enableDiscount01: '',
    discountPercent01: '',
    enableDiscount02: '',
    discountPercent02: '',
    enableDiscount03: '',
    discountPercent03: '',
  }
}

function saveParameterSettings(vendID) {
  form.value.clearErrors()

  form.value
    .transform((data) => ({
      ...data,
      enablePromoHeaderText: form.value.enablePromoHeaderText.id,
      enableHeaderTextRunning: form.value.enableHeaderTextRunning.id,
      promoBannerKind: form.value.promoBannerKind.id,
      enablePromoRunningText: form.value.enablePromoRunningText.id,
      disableP1P2CrossGrp: form.value.disableP1P2CrossGrp.id,
      enableBuy1Free1: form.value.enableBuy1Free1.id,
      enableBuy2Free1: form.value.enableBuy2Free1.id,
      enableBundleDiscount: form.value.enableBundleDiscount.id,
      enableDiscount01: form.value.enableDiscount01.id,
      enableDiscount02: form.value.enableDiscount02.id,
      enableDiscount03: form.value.enableDiscount03.id,
    }))
    .post('/settings/vend/' + vend.value.id + '/parameter', {
    onSuccess: () => {
      toast.success("Successfully Saved", {
        timeout: 3000
      });
    },
    onError: (errors) => {
      toast.error("Failed, Please Try Again", {
        timeout: 3000
      });
    },
    preserveState: true,
    replace: true,
  })
}

function submit() {
  form.value.clearErrors()

  form.value
    .post('/vends/' + form.value.id + '/update', {
    onSuccess: () => {
      emit('modalClose')
    },
    preserveState: true,
    replace: true,
  })
}

</script>