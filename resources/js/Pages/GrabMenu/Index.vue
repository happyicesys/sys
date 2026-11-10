<template>
  <Head :title="$t('Grab Menu & Prices')" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ $t('Grab Menu & Prices') }}
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3 space-y-3">
      <div v-if="flashSuccess" class="rounded-md bg-green-50 border border-green-200 px-3 py-2 text-sm text-green-800">{{ flashSuccess }}</div>
      <div v-if="flashError" class="rounded-md bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-800">{{ flashError }}</div>

      <HowItWorks />

      <div v-if="operators.length > 1" class="max-w-sm text-sm">
        <label class="block font-medium text-gray-700">{{ $t('Operator') }}</label>
        <select :value="operatorId" :class="inputClass" @change="pickOperator($event.target.value)">
          <option v-for="operator in operators" :key="operator.id" :value="operator.id">{{ operator.code }} · {{ operator.name }}</option>
        </select>
      </div>
      <p v-if="!operatorId" class="text-sm text-gray-500">{{ $t('No operator with Grab stores.') }}</p>

      <template v-if="operatorId && setting">
        <CompanyRuleCard
          :operator-id="operatorId"
          :setting="setting"
          :rp-types="rpTypes"
          :round-up-steps="roundUpSteps"
          :grab-categories="grabCategories"
          :own-rule-count="counts.ownRule"
          :following-live-count="counts.followingLive"
          :can="can"
          @load-grab-categories="loadGrabCategories"
        />

        <div class="-mx-3 sm:-mx-6 lg:-mx-8 grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
          <div class="rounded-md border bg-white px-3 py-2"><div class="text-gray-600">{{ $t('On the machine menu') }}</div><div class="text-xl font-semibold">{{ counts.machineMenu }}</div></div>
          <div class="rounded-md border bg-white px-3 py-2"><div class="text-gray-600">{{ $t('Still on the old Grab menu') }}</div><div class="text-xl font-semibold">{{ counts.oldMenu }}</div></div>
          <div class="rounded-md border bg-white px-3 py-2"><div class="text-gray-600">{{ $t('With their own price rule') }}</div><div class="text-xl font-semibold">{{ counts.ownRule }}</div></div>
          <a href="#grab-products" class="rounded-md border px-3 py-2" :class="counts.toFix ? 'border-amber-200 bg-amber-50 text-amber-800' : 'bg-white'">
            <div :class="counts.toFix ? '' : 'text-gray-600'">{{ $t('Products to fix') }}</div><div class="text-xl font-semibold">{{ counts.toFix }}</div>
          </a>
        </div>

        <StoreList
          ref="storeList"
          :stores="stores"
          :rp-types="rpTypes"
          :can="can"
          @open="store => openRefId = store.ref_id"
          @switch="askSwitch"
          @set-rule="list => ruleStores = list"
        />

        <div id="grab-products">
          <ProductsSection
            :operator-id="operatorId"
            :products="products"
            :setting="setting"
            :rp-types="rpTypes"
            :sub-category-choices="subCategoryChoices"
            :grab-categories-loading="grabCategoriesLoading"
            :can="can"
            @load-grab-categories="loadGrabCategories"
          />
        </div>

        <StorePanel
          :store="openStore"
          :operator-id="operatorId"
          :company-rule="setting"
          :rp-types="rpTypes"
          :round-up-steps="roundUpSteps"
          :can="can"
          @close="openRefId = null"
          @switch="askSwitch"
        />
        <StoreRuleModal
          :stores="ruleStores"
          :operator-id="operatorId"
          :company-rule="setting"
          :rp-types="rpTypes"
          :round-up-steps="roundUpSteps"
          @close="ruleStores = []"
          @saved="ruleStores = []; storeList?.untickAll()"
        />
        <SwitchMenuModal
          :stores="switchList"
          :to-machine="switchToMachine"
          :operator-id="operatorId"
          :company-rule="setting"
          :rp-types="rpTypes"
          @close="switchList = []"
          @done="switchList = []; storeList?.untickAll()"
        />
      </template>
    </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import CompanyRuleCard from '@/Components/GrabMenu/CompanyRuleCard.vue'
import HowItWorks from '@/Components/GrabMenu/HowItWorks.vue'
import ProductsSection from '@/Components/GrabMenu/ProductsSection.vue'
import StoreList from '@/Components/GrabMenu/StoreList.vue'
import StorePanel from '@/Components/GrabMenu/StorePanel.vue'
import StoreRuleModal from '@/Components/GrabMenu/StoreRuleModal.vue'
import SwitchMenuModal from '@/Components/GrabMenu/SwitchMenuModal.vue'
import { inputClass } from '@/Components/GrabMenu/priceRule'
import { Head, router, usePage } from '@inertiajs/vue3'
import axios from 'axios'
import { computed, ref } from 'vue'
import { t } from '@/i18n'

// Grab Menu & Prices: how it works, the company price rule, every store (its menu and price rule), products to fix.
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

function pickOperator(id) {
  router.get('/grab-menu', { operator_id: id }, { preserveState: false })
}

const counts = computed(() => {
  const machineMenu = props.stores.filter(store => store.machine_menu_since)
  return {
    machineMenu: machineMenu.length,
    oldMenu: props.stores.length - machineMenu.length,
    ownRule: props.stores.filter(store => store.price_rule?.scope === 'store').length,
    followingLive: machineMenu.filter(store => store.price_rule?.scope !== 'store').length,
    toFix: props.products.filter(product => product.problem && product.machines).length,
  }
})

// The open store follows the page's data, so it shows a saved rule or a switch at once.
const openRefId = ref(null)
const openStore = computed(() => props.stores.find(store => store.ref_id === openRefId.value) ?? null)

const storeList = ref(null)
const ruleStores = ref([])
const switchList = ref([])
const switchToMachine = ref(true)
function askSwitch(list, toMachine) {
  switchToMachine.value = toMachine
  switchList.value = list
}

// Grab's own categories (a read on Grab), added to the sub-categories already in use.
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
  const category = grabCategories.value.find(c => c.id === props.setting?.category?.id) ?? grabCategories.value[0]
  const all = [...(category?.sub_categories ?? []), ...(props.subCategories ?? [])]
  return all.filter((sub, index) => all.findIndex(other => other.id === sub.id) === index)
    .sort((a, b) => a.name.localeCompare(b.name))
})
</script>
