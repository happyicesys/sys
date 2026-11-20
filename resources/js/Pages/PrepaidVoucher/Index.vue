<template>

  <Head :title="$t('Next-visit Voucher')" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ $t('Happy Hour Campaign') }}
        <span class="ml-1 align-middle inline-flex items-center rounded px-1.5 py-0.5 text-xs font-bold border bg-violet-100 text-violet-800 border-violet-300">
          {{ $t('Vending :version+', { version: minVendingApkVersion }) }}
        </span>
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <PromotionTabs active="prepaid-voucher" />

      <!-- Settings -->
      <div class="-mx-3 sm:-mx-6 lg:-mx-8 bg-white rounded-b-md border border-t-0 px-3 py-3">
        <p class="text-xs text-gray-500">
          {{ $t('After a customer pays for ONE item at its normal price on the screen (no Happy Hour, no promotion, no voucher code), the machine holds the item and offers a voucher for the same item on their next visit, at the discount below. They type their mobile number, confirm it, and pay by QR or card; or they say no and the item drops at once. A voucher is used with the same number on any machine of the group: the same item for free, or what they paid towards another item (a cheaper item loses the difference; a dearer one pays the rest). Using a voucher is a single purchase too, so they are offered the next one. Only touchscreen vending machines (big board) on app :version or later run it.', { version: minVendingApkVersion }) }}
        </p>

        <div class="mt-3 grid grid-cols-1 md:grid-cols-6 gap-3">
          <div class="md:col-span-2">
            <label class="block text-sm font-medium text-gray-700">{{ $t('Name') }}</label>
            <input type="text" v-model="form.name" :class="inputClass" :disabled="!canEdit" />
            <p v-if="form.errors.name" class="text-red-600 text-xs mt-1">{{ form.errors.name }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">{{ $t('Discount %') }}</label>
            <input type="number" min="1" max="90" v-model.number="form.discount_pct" :class="inputClass" :disabled="!canEdit" />
            <p v-if="form.errors.discount_pct" class="text-red-600 text-xs mt-1">{{ form.errors.discount_pct }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">{{ $t('Round voucher price up to') }}</label>
            <select v-model.number="form.price_step_cents" :class="inputClass" :disabled="!canEdit">
              <option v-for="o in priceStep.options" :key="o.value" :value="o.value">{{ o.label }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">{{ $t('Valid for (days)') }}</label>
            <input type="number" min="1" max="365" v-model.number="form.expiry_days" :class="inputClass" :disabled="!canEdit" />
            <p class="text-xs text-gray-500 mt-1">{{ expiryHint }}</p>
            <p v-if="form.errors.expiry_days" class="text-red-600 text-xs mt-1">{{ form.errors.expiry_days }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">{{ $t('Offer shown for (s)') }}</label>
            <input type="number" min="10" max="60" v-model.number="form.offer_seconds" :class="inputClass" :disabled="!canEdit" />
            <p class="text-xs text-gray-500 mt-1">{{ $t('No answer in this time: the item drops.') }}</p>
          </div>

          <div class="md:col-span-3">
            <label class="block text-sm font-medium text-gray-700">{{ $t('Never offer a voucher for') }}</label>
            <MultiSelect v-model="selectedExcludedProducts" :options="products" mode="tags" trackBy="id" valueProp="id" label="name"
              :placeholder="$t('Products to leave out')" open-direction="bottom" class="mt-1" :disabled="!canEdit" />
          </div>
          <div class="md:col-span-3">
            <label class="block text-sm font-medium text-gray-700">{{ $t('Machines that never offer it') }}</label>
            <MultiSelect v-model="selectedExcludedVends" :options="machines" mode="tags" trackBy="id" valueProp="id" label="name"
              :placeholder="$t('Machines to leave out')" open-direction="bottom" class="mt-1" :disabled="!canEdit" />
            <p class="text-xs text-gray-500 mt-1">
              {{ $t(':ready of :total machines can run it now; the rest join when they reach app :version.', { ready: readyCount, total: machines.length, version: minVendingApkVersion }) }}
              {{ $t('A machine left out here still accepts vouchers.') }}
            </p>
          </div>

          <div class="md:col-span-6 flex flex-wrap items-center gap-6">
            <label class="inline-flex items-center space-x-2 text-sm text-gray-700">
              <input type="checkbox" v-model="form.is_redeem_enabled" class="rounded border-gray-300" :disabled="!canEdit" />
              <span>{{ $t('Customers can use their vouchers') }}</span>
            </label>
            <label class="inline-flex items-center space-x-2 text-sm text-gray-700"
              v-tooltip="$t('Off: the voucher can be bought by QR only. Card payment needs a card reader on the machine.')">
              <input type="checkbox" v-model="form.is_card_enabled" class="rounded border-gray-300" :disabled="!canEdit" />
              <span>{{ $t('Card can pay for the voucher') }}</span>
            </label>
            <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-bold border"
              :class="form.is_active ? 'bg-green-100 text-green-800 border-green-300' : 'bg-orange-100 text-orange-800 border-orange-300'">
              {{ form.is_active ? $t('Offering') : $t('Offer paused') }}
            </span>
            <span v-if="campaign?.updated_at" class="text-xs text-gray-500">{{ $t('Last saved :at', { at: campaign.updated_at }) }}</span>
          </div>
        </div>

        <div class="flex justify-end gap-2 mt-3" v-if="canEdit">
          <!-- Pause stops new offers only: vouchers already bought can still be used (Brian, 2026-11-20). -->
          <Button v-if="campaign" class="text-white flex space-x-1"
            :class="form.is_active ? 'bg-orange-500 hover:bg-orange-600' : 'bg-blue-500 hover:bg-blue-600'"
            :disabled="form.processing" @click="togglePause()">
            <PauseIcon v-if="form.is_active" class="w-4 h-4" />
            <PlayIcon v-else class="w-4 h-4" />
            <span>{{ form.is_active ? $t('Pause offer') : $t('Resume offer') }}</span>
          </Button>
          <Button class="bg-green-500 hover:bg-green-600 text-white flex space-x-1" :disabled="form.processing" @click="save()">
            <CheckIcon class="w-4 h-4" />
            <span>{{ campaign ? $t('Save') : $t('Save and start offering') }}</span>
          </Button>
        </div>
      </div>

      <!-- Figures -->
      <div class="-mx-3 sm:-mx-6 lg:-mx-8 mt-4 grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="bg-white rounded-md border px-3 py-2">
          <div class="text-xs text-gray-500">{{ $t('Unused vouchers') }}</div>
          <div class="text-lg font-semibold">{{ summary.active_count }} · {{ money(summary.active_value_cents) }}</div>
          <div class="text-[11px] text-gray-500">{{ $t('Paid for, not used yet') }}</div>
        </div>
        <div class="bg-white rounded-md border px-3 py-2">
          <div class="text-xs text-gray-500">{{ $t('Sold, last 30 days') }}</div>
          <div class="text-lg font-semibold">{{ summary.sold_30d_count }} · {{ money(summary.sold_30d_value_cents) }}</div>
        </div>
        <div class="bg-white rounded-md border px-3 py-2">
          <div class="text-xs text-gray-500">{{ $t('Used, last 30 days') }}</div>
          <div class="text-lg font-semibold">{{ summary.redeemed_30d_count }}</div>
        </div>
        <div class="bg-white rounded-md border px-3 py-2">
          <div class="text-xs text-gray-500">{{ $t('Expired unused, last 30 days') }}</div>
          <div class="text-lg font-semibold">{{ summary.expired_30d_count }} · {{ money(summary.expired_30d_value_cents) }}</div>
        </div>
      </div>

      <!-- Vouchers -->
      <div class="-mx-3 sm:-mx-6 lg:-mx-8 mt-4 bg-white rounded-md border px-3 py-3">
        <div class="flex flex-wrap items-end gap-3">
          <div>
            <label class="block text-sm font-medium text-gray-700">{{ $t('Customer mobile number') }}</label>
            <input type="text" v-model="search.phone" :class="inputClass" :placeholder="$t('Full number, e.g. 91234567')" @keyup.enter="applySearch()" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">{{ $t('Status') }}</label>
            <select v-model="search.status" :class="inputClass">
              <option value="">{{ $t('All') }}</option>
              <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
            </select>
          </div>
          <Button class="bg-blue-500 hover:bg-blue-600 text-white flex space-x-1" @click="applySearch()">
            <MagnifyingGlassIcon class="w-4 h-4" />
            <span>{{ $t('Search') }}</span>
          </Button>
          <p v-if="phoneSearchInvalid" class="text-red-600 text-xs">{{ $t('That is not a valid mobile number.') }}</p>
        </div>
        <p class="text-xs text-gray-500 mt-2">{{ $t('Numbers are shown masked. To find a customer\'s vouchers, type their whole number.') }}</p>

        <div class="mt-3 overflow-scroll">
          <table class="min-w-full border-separate" style="border-spacing: 0">
            <thead class="bg-gray-100">
              <tr class="divide-x divide-gray-200">
                <TableHead>#</TableHead>
                <TableHead>{{ $t('Bought') }}</TableHead>
                <TableHead>{{ $t('Mobile') }}</TableHead>
                <TableHead>{{ $t('Product') }}</TableHead>
                <TableHead>{{ $t('Paid') }}</TableHead>
                <TableHead>{{ $t('Valid until') }}</TableHead>
                <TableHead>{{ $t('Status') }}</TableHead>
                <TableHead>{{ $t('Used') }}</TableHead>
                <TableHead v-if="canEdit">{{ $t('Action') }}</TableHead>
              </tr>
            </thead>
            <tbody class="bg-white">
              <tr v-for="(v, i) in vouchers.data" :key="v.id" class="align-top divide-x odd:bg-white even:bg-gray-50 text-sm">
                <td class="px-2 py-1 text-center">{{ v.id }}</td>
                <td class="px-2 py-1 whitespace-nowrap">
                  <div>{{ v.issued_at }}</div>
                  <div class="text-xs text-gray-500">
                    <MachineIdLink v-if="v.bought_vend" :code="v.bought_vend" /> · {{ v.pay_rail === 'card' ? $t('Card') : $t('QR') }}
                  </div>
                </td>
                <td class="px-2 py-1 whitespace-nowrap font-mono">{{ v.phone_purged ? $t('(erased)') : v.phone }}</td>
                <td class="px-2 py-1">{{ v.product }}</td>
                <td class="px-2 py-1 whitespace-nowrap text-right">
                  <div class="font-semibold">{{ money(v.price_paid_cents) }}</div>
                  <div class="text-xs text-gray-500">{{ $t(':pct% off :price', { pct: v.discount_pct, price: money(v.list_price_cents) }) }}</div>
                </td>
                <td class="px-2 py-1 whitespace-nowrap">{{ v.expires_at }}</td>
                <td class="px-2 py-1 text-center">
                  <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-bold border" :class="statusClass(v.status)">{{ statusLabels[v.status] ?? v.status }}</span>
                  <div v-if="v.void_reason" class="text-xs text-gray-500 mt-1">{{ v.void_reason }}</div>
                </td>
                <td class="px-2 py-1 text-xs">
                  <template v-if="v.redeemed_at">
                    <div>{{ v.redeemed_at }} · <MachineIdLink v-if="v.redeemed_vend" :code="v.redeemed_vend" /></div>
                    <div v-if="v.redeemed_mode === 'sku'">{{ $t('Same item, free') }}</div>
                    <div v-else>
                      {{ $t('Value towards :product', { product: v.redeemed_product ?? '' }) }}
                      <span v-if="v.forfeited_cents"> · {{ $t('lost :amount', { amount: money(v.forfeited_cents) }) }}</span>
                      <span v-if="v.balance_paid_cents"> · {{ $t('paid :amount more', { amount: money(v.balance_paid_cents) }) }}</span>
                    </div>
                  </template>
                </td>
                <td v-if="canEdit" class="px-2 py-1 whitespace-nowrap">
                  <template v-if="['active', 'reserved', 'expired'].includes(v.status)">
                    <button type="button" class="text-blue-700 hover:underline text-xs mr-2" @click="extend(v)">{{ $t('Extend') }}</button>
                    <button v-if="v.status !== 'expired'" type="button" class="text-red-700 hover:underline text-xs" @click="voidVoucher(v)">{{ $t('Void') }}</button>
                  </template>
                </td>
              </tr>
              <tr v-if="!vouchers.data.length">
                <td :colspan="canEdit ? 9 : 8" class="px-2 py-4 text-center text-sm text-gray-500">{{ $t('No vouchers') }}</td>
              </tr>
            </tbody>
          </table>
        </div>
        <Paginator v-if="vouchers.data.length" :links="vouchers.links" :meta="vouchers.meta" />
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import MachineIdLink from '@/Components/MachineIdLink.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import Paginator from '@/Components/Paginator.vue';
import PromotionTabs from '@/Components/Promotion/PromotionTabs.vue';
import TableHead from '@/Components/TableHead.vue';
import { CheckIcon, MagnifyingGlassIcon, PauseIcon, PlayIcon } from '@heroicons/vue/20/solid';
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { t } from '@/i18n';

const props = defineProps({
  campaign: { type: Object, default: null },
  machines: { type: Array, default: () => [] },
  products: { type: Array, default: () => [] },
  minVendingApkVersion: Number,
  priceStep: { type: Object, default: () => ({ default: 10, options: [] }) },
  currency: { type: Object, default: () => ({ symbol: '$', exponent: 2, hidden: false }) },
  summary: { type: Object, default: () => ({}) },
  vouchers: { type: Object, default: () => ({ data: [] }) },
  filters: { type: Object, default: () => ({}) },
  phoneSearchInvalid: Boolean,
})

const permissions = computed(() => usePage().props.auth?.permissions ?? [])
const canEdit = computed(() => permissions.value.includes('update prepaid-vouchers'))
const inputClass = 'mt-1 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md'

const statusLabels = {
  active: t('Unused'),
  reserved: t('Being used'),
  redeemed: t('Used'),
  expired: t('Expired'),
  voided: t('Voided'),
}

const c = props.campaign
const form = useForm({
  name: c?.name ?? t('Next-visit voucher'),
  is_active: c ? c.is_active : true,
  is_redeem_enabled: c ? c.is_redeem_enabled : true,
  is_card_enabled: c ? c.is_card_enabled : true,
  discount_pct: c?.discount_pct ?? 10,
  price_step_cents: c?.price_step_cents ?? props.priceStep.default,
  expiry_days: c?.expiry_days ?? 30,
  offer_seconds: c?.offer_seconds ?? 20,
  excluded_product_ids: c?.excluded_product_ids ?? [],
  excluded_vend_ids: c?.excluded_vend_ids ?? [],
})
const selectedExcludedProducts = ref(props.products.filter(p => (c?.excluded_product_ids ?? []).includes(p.id)))
const selectedExcludedVends = ref(props.machines.filter(m => (c?.excluded_vend_ids ?? []).includes(m.id)))
const readyCount = computed(() => props.machines.filter(m => m.ready).length)

const expiryHint = computed(() => {
  const days = Number(form.expiry_days) || 0
  return days === 1
    ? t('Bought today: usable until the end of tomorrow.')
    : t('Bought today: usable until the end of the day :days days from now.', { days })
})

const search = ref({ phone: props.filters.phone ?? '', status: props.filters.status ?? '' })

function save() {
  form.excluded_product_ids = selectedExcludedProducts.value.map(p => p.id ?? p)
  form.excluded_vend_ids = selectedExcludedVends.value.map(m => m.id ?? m)
  form.post('/prepaid-vouchers/settings', { preserveScroll: true })
}

function togglePause() {
  const pausing = form.is_active
  if (!confirm(pausing
    ? t('Pause the offer? Machines stop offering vouchers; vouchers already bought can still be used.')
    : t('Resume the offer on every machine?'))) return
  form.is_active = !form.is_active
  save()
}

function applySearch() {
  router.get('/prepaid-vouchers', { phone: search.value.phone || undefined, status: search.value.status || undefined }, { preserveState: true, preserveScroll: true })
}

function voidVoucher(v) {
  const reason = prompt(t('Why is voucher #:id being voided? It cannot be used afterwards.', { id: v.id }))
  if (!reason) return
  router.post(`/prepaid-vouchers/${v.id}/void`, { reason }, { preserveScroll: true })
}

function extend(v) {
  const days = prompt(t('Extend voucher #:id by how many days?', { id: v.id }), '7')
  if (!days) return
  router.post(`/prepaid-vouchers/${v.id}/extend`, { days: Number(days) }, { preserveScroll: true })
}

function statusClass(status) {
  return {
    active: 'bg-green-100 text-green-800 border-green-300',
    reserved: 'bg-amber-100 text-amber-800 border-amber-300',
    redeemed: 'bg-gray-100 text-gray-700 border-gray-300',
    expired: 'bg-red-50 text-red-700 border-red-200',
    voided: 'bg-red-100 text-red-800 border-red-300',
  }[status] ?? 'bg-gray-100 text-gray-700 border-gray-300'
}

function money(cents) {
  if (cents === null || cents === undefined) return ''
  if (props.currency.hidden) {
    return `${props.currency.symbol} ${Math.round(cents / 10 ** props.currency.exponent).toLocaleString('en-US')}`
  }
  return '$' + (cents / 100).toFixed(2)
}
</script>
