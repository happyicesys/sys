<template>
  <Head :title="$t('Grab Menu & Prices')" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ $t('Grab Menu & Prices') }}
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div v-if="flashSuccess" class="mb-3 rounded-md bg-green-50 border border-green-200 px-3 py-2 text-sm text-green-800">{{ flashSuccess }}</div>
      <div v-if="flashError" class="mb-3 rounded-md bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-800">{{ flashError }}</div>

      <div class="-mx-3 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 py-3 space-y-3">
        <p class="text-sm text-gray-600">
          {{ $t('A Grab store on the machine menu lists every product on its machine\'s slots, and follows the machine when its product mapping changes. Set here how those products are priced on Grab and which Grab sub-category each one sits in.') }}
        </p>
        <div v-if="operators.length > 1" class="max-w-sm">
          <label class="block text-sm font-medium text-gray-700">{{ $t('Operator') }}</label>
          <select :value="operatorId" :class="inputClass" @change="pickOperator($event.target.value)">
            <option v-for="operator in operators" :key="operator.id" :value="operator.id">{{ operator.code }} · {{ operator.name }}</option>
          </select>
        </div>
        <p v-if="!operatorId" class="text-sm text-gray-500">{{ $t('No operator with Grab stores.') }}</p>
      </div>

      <template v-if="operatorId && setting">
        <!-- Pricing rule -->
        <div class="-mx-3 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 py-3">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-base font-semibold text-gray-800">{{ $t('Grab pricing') }}</h3>
            <span v-if="setting.updated_at" class="text-xs text-gray-500">{{ $t('Saved :time by :name', { time: formatDateTime(setting.updated_at), name: setting.updated_by ?? '—' }) }}</span>
            <span v-else class="text-xs text-amber-700">{{ $t('Not saved yet: shown with the default (RP2).') }}</span>
          </div>
          <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-2">
            <label
              v-for="mode in modes"
              :key="mode.id"
              class="flex items-start gap-2 rounded-md border px-3 py-2 text-sm"
              :class="settingForm.pricing_mode === mode.id ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200'"
            >
              <input v-model="settingForm.pricing_mode" type="radio" :value="mode.id" :disabled="!can.update" class="mt-0.5" />
              <span>
                <span class="block font-medium text-gray-800">{{ mode.name }}</span>
                <span class="block text-xs text-gray-600">{{ mode.help }}</span>
              </span>
            </label>
          </div>
          <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
            <div>
              <label class="block font-medium text-gray-700">{{ settingForm.pricing_mode === 'custom' ? $t('Compare with') : $t('RP tier') }}</label>
              <select v-model="settingForm.rp_type" :disabled="!can.update" :class="inputClass">
                <option v-for="rp in rpTypes" :key="rp.id" :value="rp.id">{{ rp.name }}</option>
              </select>
            </div>
            <template v-if="settingForm.pricing_mode === 'rp_multiplier'">
              <div>
                <label class="block font-medium text-gray-700">{{ $t('Multiplier') }}</label>
                <input v-model="settingForm.multiplier" type="number" min="0.5" max="5" step="0.01" :disabled="!can.update" :class="inputClass" />
                <InputError :message="settingForm.errors.multiplier" />
              </div>
              <div>
                <label class="block font-medium text-gray-700">{{ $t('Round up to') }}</label>
                <select v-model="settingForm.round_up_cents" :disabled="!can.update" :class="inputClass">
                  <option v-for="step in roundUpSteps" :key="step" :value="step">{{ step === 100 ? $t('the dollar') : $t(':cents¢', { cents: step }) }}</option>
                </select>
              </div>
              <p class="col-span-2 sm:col-span-1 self-end text-xs text-gray-600">
                {{ $t('e.g. :rp × :multiplier = :price', { rp: money(250), multiplier: settingForm.multiplier || 1, price: money(example) }) }}
              </p>
            </template>
          </div>
          <div class="mt-3 text-sm">
            <span class="font-medium text-gray-700">{{ $t('Grab category') }}:</span>
            <span class="ml-1">{{ settingForm.category?.name ?? $t('not set') }}</span>
            <select v-if="grabCategories.length && can.update" class="ml-2 rounded-md border-gray-300 text-sm" @change="settingForm.category = grabCategories.find(c => c.id === $event.target.value) ?? settingForm.category">
              <option value="">{{ $t('Change…') }}</option>
              <option v-for="category in grabCategories" :key="category.id" :value="category.id">{{ category.name }}</option>
            </select>
          </div>
          <InputError :message="settingForm.errors.pricing_mode || settingForm.errors.rp_type || settingForm.errors.round_up_cents" />
          <div v-if="can.update" class="mt-3 flex flex-wrap gap-2">
            <button type="button" :class="primaryClass" :disabled="settingForm.processing" @click="saveSetting">{{ $t('Save pricing') }}</button>
            <button type="button" :class="secondaryClass" :disabled="busy" @click="fillFromTemplates">{{ $t('Fill empty fields from the old Grab menus') }}</button>
            <button type="button" :class="secondaryClass" :disabled="grabCategoriesLoading" @click="loadGrabCategories">
              {{ grabCategoriesLoading ? $t('Loading…') : $t('Load Grab\'s sub-category list') }}
            </button>
          </div>
        </div>

        <!-- Products -->
        <div class="-mx-3 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 py-3">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-base font-semibold text-gray-800">{{ $t('Products on this operator\'s Grab machines') }}</h3>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="chip in productChips"
                :key="chip.key"
                type="button"
                class="rounded-full border px-3 py-1 text-sm"
                :class="productFilter === chip.key ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
                @click="productFilter = chip.key"
              >{{ chip.name }} <span class="text-gray-500">{{ chip.count }}</span></button>
            </div>
          </div>
          <div class="mt-2 flex flex-wrap items-center gap-2">
            <input
              v-model="productSearch"
              type="search"
              :placeholder="$t('Search product code or name')"
              class="w-full sm:w-72 rounded-md border-gray-300 text-sm"
            />
            <span v-if="productSearch.trim()" class="text-xs text-gray-500">{{ $t(':shown of :total shown', { shown: shownProducts.length, total: chipProducts.length }) }}</span>
          </div>
          <p v-if="problemCount" class="mt-2 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-800">
            {{ $tc(':count product is on a machine but not on Grab yet: it needs a price or a sub-category.|:count products are on machines but not on Grab yet: they need a price or a sub-category.', problemCount, { count: problemCount }) }}
          </p>

          <div v-if="can.update && picked.length" class="mt-2 flex flex-wrap items-center gap-2 rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm">
            <span>{{ $tc(':count product picked|:count products picked', picked.length, { count: picked.length }) }}</span>
            <select v-model="bulkSubCategory" class="rounded-md border-gray-300 text-sm">
              <option :value="null">{{ $t('Sub-category…') }}</option>
              <option v-for="sub in subCategoryChoices" :key="sub.id" :value="sub.id">{{ sub.name }}</option>
            </select>
            <button type="button" :class="primaryClass" :disabled="!bulkSubCategory || busy" @click="applyBulkSubCategory">{{ $t('Set on picked products') }}</button>
            <button type="button" class="text-indigo-700 underline" @click="picked = []">{{ $t('Clear') }}</button>
          </div>

          <div class="mt-2 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr class="text-left text-gray-700">
                  <th v-if="can.update" class="px-2 py-2"><input type="checkbox" :checked="shownProducts.length && picked.length === shownProducts.length" @change="picked = $event.target.checked ? shownProducts.map(p => p.product_id) : []" /></th>
                  <th class="px-2 py-2">{{ $t('Product') }}</th>
                  <th class="px-2 py-2 text-right">{{ $t('Machines') }}</th>
                  <th class="px-2 py-2">{{ $t('Grab sub-category') }}</th>
                  <th class="px-2 py-2 text-right">{{ rpName }}</th>
                  <th class="px-2 py-2 text-right">{{ $t('Grab price') }}</th>
                  <th class="px-2 py-2 text-right">{{ $t('vs :rp', { rp: rpName }) }}</th>
                  <th class="px-2 py-2">{{ $t('Status') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="product in shownProducts" :key="product.product_id" class="align-middle">
                  <td v-if="can.update" class="px-2 py-2"><input v-model="picked" type="checkbox" :value="product.product_id" /></td>
                  <td class="px-2 py-2">
                    <div class="flex items-center gap-2">
                      <img v-if="product.photo" :src="product.photo" alt="" class="h-10 w-10 rounded object-contain border bg-white" />
                      <div>
                        <div class="font-medium">{{ product.code }}</div>
                        <div class="text-xs text-gray-600">{{ product.name }}</div>
                      </div>
                    </div>
                  </td>
                  <td class="px-2 py-2 text-right">{{ product.machines }}</td>
                  <td class="px-2 py-2">
                    <select
                      :value="product.sub_category?.id ?? ''"
                      :disabled="!can.update"
                      class="rounded-md border-gray-300 text-sm w-48"
                      @change="saveSubCategory(product, $event.target.value)"
                    >
                      <option value="">{{ $t('— none —') }}</option>
                      <option v-for="sub in subCategoryChoices" :key="sub.id" :value="sub.id">{{ sub.name }}</option>
                    </select>
                  </td>
                  <td class="px-2 py-2 text-right whitespace-nowrap">{{ product.rp !== null ? money(product.rp) : '—' }}</td>
                  <td class="px-2 py-2 text-right whitespace-nowrap">
                    <input
                      v-if="setting.pricing_mode === 'custom' && can.update"
                      type="number"
                      min="0.01"
                      step="0.01"
                      :value="product.custom_amount !== null ? (product.custom_amount / 100).toFixed(2) : ''"
                      class="w-24 rounded-md border-gray-300 text-sm text-right"
                      @change="saveCustomPrice(product, $event.target.value)"
                    />
                    <span v-else class="font-medium">{{ product.grab_price !== null ? money(product.grab_price) : '—' }}</span>
                  </td>
                  <td class="px-2 py-2 text-right whitespace-nowrap" :class="product.vs_rp_pct < 0 ? 'text-red-700' : 'text-gray-700'">
                    {{ product.vs_rp_pct !== null ? (product.vs_rp_pct > 0 ? '+' : '') + product.vs_rp_pct + '%' : '—' }}
                  </td>
                  <td class="px-2 py-2">
                    <span v-if="!product.problem" class="text-green-700">{{ product.machines ? $t('On Grab') : $t('Not on a machine') }}</span>
                    <span v-else class="text-amber-700">{{ problemName(product.problem) }}</span>
                  </td>
                </tr>
                <tr v-if="!shownProducts.length">
                  <td colspan="8" class="px-2 py-4 text-center text-gray-500">{{ $t('No product here.') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
          <p v-if="setting.pricing_mode !== 'custom'" class="mt-2 text-xs text-gray-500">
            {{ $t('Grab prices follow the RP on Product → Edit; change an RP there and Grab follows within minutes.') }}
          </p>
        </div>

        <!-- Stores -->
        <div class="-mx-3 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 py-3">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="text-base font-semibold text-gray-800">{{ $t('Grab stores') }}</h3>
            <div v-if="can.update && stores.length" class="flex flex-wrap gap-2">
              <button type="button" :class="primaryClass" :disabled="busy || !oldMenuStores.length" @click="switchStores(oldMenuStores, true)">
                {{ $tc('Switch :count store to the machine menu|Switch all :count stores to the machine menu', oldMenuStores.length, { count: oldMenuStores.length }) }}
              </button>
            </div>
          </div>
          <p class="mt-1 text-sm text-gray-600">
            {{ $t('Switching changes the store\'s menu on Grab within a few minutes: it then lists its machine\'s products at the prices above. Preview first; you can switch a store back.') }}
          </p>
          <div class="mt-2 flex flex-wrap items-center gap-2">
            <input
              v-model="storeSearch"
              type="search"
              :placeholder="$t('Search machine, Site or Grab merchant ID')"
              class="w-full sm:w-80 rounded-md border-gray-300 text-sm"
            />
            <select v-model="storeMenuFilter" class="rounded-md border-gray-300 text-sm">
              <option value="all">{{ $t('All menus') }}</option>
              <option value="machine">{{ $t('Machine menu') }}</option>
              <option value="old">{{ $t('Old Grab menu') }}</option>
            </select>
            <span v-if="storeFiltered" class="text-xs text-gray-500">{{ $t(':shown of :total shown', { shown: shownStores.length, total: stores.length }) }}</span>
          </div>
          <div class="mt-2 overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
              <thead class="bg-gray-50">
                <tr class="text-left text-gray-700">
                  <th class="px-2 py-2">{{ $t('Machine') }}</th>
                  <th class="px-2 py-2">{{ $t('Site') }}</th>
                  <th class="px-2 py-2">{{ $t('Grab merchant ID') }}</th>
                  <th class="px-2 py-2">{{ $t('Menu') }}</th>
                  <th class="px-2 py-2"></th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="store in shownStores" :key="store.ref_id">
                  <td class="px-2 py-2"><MachineIdLink v-if="store.vend_code" :code="store.vend_code" /></td>
                  <td class="px-2 py-2">{{ store.site ?? '—' }}</td>
                  <td class="px-2 py-2 font-mono text-xs">{{ store.merchant_id }}</td>
                  <td class="px-2 py-2">
                    <span v-if="store.machine_menu_since" class="text-green-700">{{ $t('Machine menu since :time', { time: formatDateTime(store.machine_menu_since) }) }}</span>
                    <span v-else class="text-gray-600">{{ $t('Old Grab menu') }}</span>
                    <span v-if="!store.selling" class="ml-1 text-xs text-gray-500">{{ $t('(not selling)') }}</span>
                  </td>
                  <td class="px-2 py-2 whitespace-nowrap text-right">
                    <button type="button" :class="actionClass" @click="openPreview(store)">{{ $t('Preview') }}</button>
                    <template v-if="can.update">
                      <button v-if="!store.machine_menu_since" type="button" :class="actionClass" class="ml-1" :disabled="busy" @click="switchStores([store], true)">{{ $t('Switch to machine menu') }}</button>
                      <button v-else type="button" :class="actionClass" class="ml-1" :disabled="busy" @click="switchStores([store], false)">{{ $t('Back to old menu') }}</button>
                    </template>
                  </td>
                </tr>
                <tr v-if="!stores.length">
                  <td colspan="5" class="px-2 py-4 text-center text-gray-500">{{ $t('This operator has no Grab store on a machine.') }}</td>
                </tr>
                <tr v-else-if="!shownStores.length">
                  <td colspan="5" class="px-2 py-4 text-center text-gray-500">{{ $t('No store matches the search.') }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </div>

    <!-- Preview: the machine menu as Grab would get it. -->
    <Modal :open="!!previewStore" @modalClose="previewStore = null">
      <template #header>{{ $t('Machine menu of :code', { code: previewStore?.vend_code ?? '' }) }}</template>
      <div class="text-sm">
        <p v-if="previewLoading" class="text-gray-500">{{ $t('Loading…') }}</p>
        <template v-else>
          <p class="text-gray-600 mb-2">
            {{ $t(':listed of :total products on this machine would be on Grab.', { listed: previewLines.filter(l => !l.problem).length, total: previewLines.length }) }}
          </p>
          <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr class="text-left text-gray-700">
                <th class="px-2 py-1">{{ $t('Product') }}</th>
                <th class="px-2 py-1">{{ $t('Slots') }}</th>
                <th class="px-2 py-1 text-right">{{ $t('Can sell') }}</th>
                <th class="px-2 py-1 text-right">{{ $t('Grab price') }}</th>
                <th class="px-2 py-1">{{ $t('Status') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr v-for="line in previewLines" :key="line.item_id">
                <td class="px-2 py-1"><span class="font-medium">{{ line.code }}</span> <span class="text-gray-600">{{ line.name }}</span></td>
                <td class="px-2 py-1 text-xs">{{ line.slots.join(', ') }}</td>
                <td class="px-2 py-1 text-right">{{ line.available }}</td>
                <td class="px-2 py-1 text-right">{{ line.price !== null ? money(line.price) : '—' }}</td>
                <td class="px-2 py-1">
                  <span v-if="!line.problem" class="text-green-700">{{ line.sub_category }}</span>
                  <span v-else class="text-amber-700">{{ problemName(line.problem) }}</span>
                </td>
              </tr>
              <tr v-if="!previewLines.length">
                <td colspan="5" class="px-2 py-3 text-center text-gray-500">{{ $t('No product on this machine\'s slots.') }}</td>
              </tr>
            </tbody>
          </table>
        </template>
      </div>
    </Modal>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import InputError from '@/Components/InputError.vue';
import MachineIdLink from '@/Components/MachineIdLink.vue';
import Modal from '@/Components/Modal.vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import moment from 'moment';
import { computed, ref, watch } from 'vue';
import { t } from '@/i18n';

const props = defineProps({
  operators: Array,
  operatorId: Number,
  setting: Object,
  products: Array,
  stores: Array,
  subCategories: Array,
  rpTypes: Array,
  roundUpSteps: Array,
  can: Object,
})

const page = usePage()
const flashSuccess = computed(() => page.props.flash?.success)
const localError = ref(null)
const flashError = computed(() => localError.value ?? page.props.flash?.error)

const inputClass = 'mt-1 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md'
const primaryClass = 'rounded-md bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50'
const secondaryClass = 'rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-50'
const actionClass = 'rounded border border-gray-300 bg-white px-2 py-1 text-xs text-gray-700 hover:bg-gray-50 disabled:opacity-50'

const modes = [
  { id: 'rp', name: t('RP tier'), help: t('The Grab price is the product\'s RP on the tier chosen below.') },
  { id: 'rp_multiplier', name: t('RP × multiplier'), help: t('The RP times a multiplier, always rounded up.') },
  { id: 'custom', name: t('Custom price'), help: t('A Grab price typed for each product below.') },
]
const problemNames = {
  no_rp: () => t('No :rp price', { rp: rpName.value }),
  no_custom_price: () => t('No Grab price'),
  no_sub_category: () => t('No sub-category'),
}
function problemName(problem) {
  return problemNames[problem]?.() ?? problem
}

function money(cents) {
  return '$' + (Number(cents ?? 0) / 100).toFixed(2)
}
function formatDateTime(value) {
  return value ? moment(value).format('YYYY-MM-DD HH:mm') : ''
}

function pickOperator(id) {
  router.get('/grab-menu', { operator_id: id }, { preserveState: false })
}

// Pricing rule
const settingForm = useForm({
  pricing_mode: props.setting?.pricing_mode ?? 'rp',
  rp_type: props.setting?.rp_type ?? 2,
  multiplier: props.setting?.multiplier ?? 1,
  round_up_cents: props.setting?.round_up_cents ?? 10,
  category: props.setting?.category ?? null,
})
// Follow the saved rule after each save or fill (the page keeps its state across visits).
watch(() => props.setting, (setting) => {
  if (!setting || settingForm.isDirty) return
  settingForm.defaults({
    pricing_mode: setting.pricing_mode,
    rp_type: setting.rp_type,
    multiplier: setting.multiplier,
    round_up_cents: setting.round_up_cents,
    category: setting.category,
  })
  settingForm.reset()
})
const rpName = computed(() => props.rpTypes.find(rp => rp.id === Number(settingForm.rp_type))?.name ?? 'RP')

// The same integer maths as GrabPriceList::multiply, on a $2.50 example.
const example = computed(() => {
  const bp = Math.round(Number(settingForm.multiplier || 1) * 10000)
  const step = Number(settingForm.round_up_cents) || 10
  return Math.ceil((250 * bp) / (step * 10000)) * step
})

function saveSetting() {
  settingForm.post(`/grab-menu/${props.operatorId}/setting`, { preserveScroll: true })
}

const busy = ref(false)
function post(url, data) {
  busy.value = true
  router.post(url, data, { preserveScroll: true, onFinish: () => { busy.value = false } })
}

function fillFromTemplates() {
  post(`/grab-menu/${props.operatorId}/fill-from-templates`, {})
}

// Grab's own sub-categories (a read on Grab), added to the ones already in use.
const grabCategories = ref([])
const grabCategoriesLoading = ref(false)
async function loadGrabCategories() {
  grabCategoriesLoading.value = true
  try {
    const { data } = await axios.get(`/grab-menu/${props.operatorId}/grab-categories`)
    grabCategories.value = data
  } catch (e) {
    localError.value = t('Grab\'s list could not be loaded.')
  } finally {
    grabCategoriesLoading.value = false
  }
}
const subCategoryChoices = computed(() => {
  const category = grabCategories.value.find(c => c.id === settingForm.category?.id) ?? grabCategories.value[0]
  const fromGrab = category?.sub_categories ?? []
  const all = [...fromGrab, ...(props.subCategories ?? [])]
  return all.filter((sub, index) => all.findIndex(other => other.id === sub.id) === index)
    .sort((a, b) => a.name.localeCompare(b.name))
})

// Products
// Products on a machine by default; ones only remembered from old Grab menus under their own chip.
const productFilter = ref('machines')
const problemCount = computed(() => props.products.filter(p => p.problem && p.machines).length)
const productChips = computed(() => [
  { key: 'machines', name: t('On machines'), count: props.products.filter(p => p.machines).length },
  { key: 'problem', name: t('Not on Grab yet'), count: problemCount.value },
  { key: 'listed', name: t('On Grab'), count: props.products.filter(p => !p.problem && p.machines).length },
  { key: 'off', name: t('On no machine'), count: props.products.filter(p => !p.machines).length },
])
const chipProducts = computed(() => props.products.filter(product => {
  if (productFilter.value === 'problem') return product.problem && product.machines
  if (productFilter.value === 'listed') return !product.problem && product.machines
  if (productFilter.value === 'off') return !product.machines
  return product.machines > 0
}))
// Search narrows the chip's list: every word must appear in the code or the name.
const productSearch = ref('')
const shownProducts = computed(() => {
  const words = searchWords(productSearch.value)
  if (!words.length) return chipProducts.value
  return chipProducts.value.filter(product => matchesAll(`${product.code} ${product.name}`, words))
})
function searchWords(text) {
  return String(text ?? '').toLowerCase().split(/\s+/).filter(Boolean)
}
function matchesAll(haystack, words) {
  const text = String(haystack ?? '').toLowerCase()
  return words.every(word => text.includes(word))
}
const picked = ref([])
const bulkSubCategory = ref(null)
watch(() => props.products, () => { picked.value = picked.value.filter(id => props.products.some(p => p.product_id === id)) })

function subCategoryById(id) {
  return subCategoryChoices.value.find(sub => sub.id === id) ?? null
}
function saveSubCategory(product, id) {
  post(`/grab-menu/${props.operatorId}/products/${product.product_id}`, { sub_category: id ? subCategoryById(id) : null })
}
function saveCustomPrice(product, value) {
  post(`/grab-menu/${props.operatorId}/products/${product.product_id}`, { custom_price: value === '' ? null : value })
}
function applyBulkSubCategory() {
  post(`/grab-menu/${props.operatorId}/sub-categories`, { product_ids: picked.value, sub_category: subCategoryById(bulkSubCategory.value) })
  picked.value = []
  bulkSubCategory.value = null
}

// Stores
const oldMenuStores = computed(() => props.stores.filter(store => !store.machine_menu_since))
// Search + menu filter only narrow the table; "Switch all" still counts every store on the old menu.
const storeSearch = ref('')
const storeMenuFilter = ref('all')
const storeFiltered = computed(() => !!storeSearch.value.trim() || storeMenuFilter.value !== 'all')
const shownStores = computed(() => {
  const words = searchWords(storeSearch.value)
  return props.stores.filter(store => {
    if (storeMenuFilter.value === 'machine' && !store.machine_menu_since) return false
    if (storeMenuFilter.value === 'old' && store.machine_menu_since) return false
    return !words.length || matchesAll(`${store.vend_code ?? ''} ${store.site ?? ''} ${store.merchant_id ?? ''}`, words)
  })
})
function switchStores(stores, machineMenu) {
  const message = machineMenu
    ? t('Switch :count store(s) to the machine menu? Grab shows the new menu within a few minutes.', { count: stores.length })
    : t('Put :count store(s) back on the old Grab menu?', { count: stores.length })
  if (!window.confirm(message)) return
  post(`/grab-menu/${props.operatorId}/menu-source`, { machine_menu: machineMenu, ref_ids: stores.map(store => store.ref_id) })
}

const previewStore = ref(null)
const previewLines = ref([])
const previewLoading = ref(false)
async function openPreview(store) {
  previewStore.value = store
  previewLines.value = []
  previewLoading.value = true
  try {
    const { data } = await axios.get(`/grab-menu/stores/${store.ref_id}/preview`)
    previewLines.value = data.lines ?? []
  } finally {
    previewLoading.value = false
  }
}
</script>
