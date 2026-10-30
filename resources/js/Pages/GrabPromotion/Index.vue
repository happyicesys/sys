<template>
  <Head :title="$t('Grab Promotions')" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ $t('Grab Promotions') }}
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <GrabPromotionViews current="grab" />
      <div v-if="flashSuccess" class="mb-3 rounded-md bg-green-50 border border-green-200 px-3 py-2 text-sm text-green-800">
        {{ flashSuccess }}
      </div>
      <div v-if="flashError" class="mb-3 rounded-md bg-red-50 border border-red-200 px-3 py-2 text-sm text-red-800">
        {{ flashError }}
      </div>

      <div class="-mx-3 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 py-3">
        <p class="text-sm text-gray-600">
          {{ $t('What Grab has live or upcoming on each store, read from Grab every 10 minutes. Grab is the truth here: a promotion mark1 believes has ended but Grab still runs is a leftover, and blocks the same items in a new promotion.') }}
        </p>
        <p class="text-sm text-gray-600 mt-1">
          <span v-if="reads.last_read_at">{{ $t('Last read :time, :count stores.', { time: formatDateTime(reads.last_read_at), count: reads.stores }) }}</span>
          <span v-else>{{ $t('Not read from Grab yet.') }}</span>
        </p>

        <div v-if="reads.failing.length" class="mt-3 rounded-md border border-amber-300 bg-amber-50 px-3 py-2">
          <div class="text-sm font-semibold text-amber-900">
            {{ $tc(':count store could not be read on its last try; its promotions below may be out of date.|:count stores could not be read on their last try; their promotions below may be out of date.', reads.failing.length, { count: reads.failing.length }) }}
          </div>
          <ul class="mt-1 space-y-1">
            <li v-for="read in reads.failing" :key="read.merchant_id" class="text-sm text-amber-900 flex flex-wrap items-center gap-2">
              <span class="font-mono">{{ read.merchant_id }}</span>
              <MachineIdLink v-if="read.vend_code" :code="read.vend_code" />
              <span>{{ read.status ? 'HTTP ' + read.status : $t('No answer') }}</span>
              <span v-if="read.message" class="text-amber-800">{{ read.message }}</span>
              <span v-if="read.last_ok_at" class="text-amber-800">{{ $t('last good read :time', { time: formatDateTime(read.last_ok_at) }) }}</span>
              <button type="button" class="text-indigo-700 underline" @click="refresh(read.ref_id)">{{ $t('Read again') }}</button>
            </li>
          </ul>
        </div>

        <div class="flex flex-wrap gap-2 mt-3">
          <button
            v-for="chip in viewChips"
            :key="chip.key"
            type="button"
            class="rounded-full border px-3 py-1 text-sm"
            :class="filters.view === chip.key ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
            @click="filters.view = chip.key; filters.source = 'all'; filters.state = 'all'; search()"
          >{{ chip.name }}</button>
          <span class="border-l mx-1"></span>
          <button
            v-for="chip in stateChips"
            :key="chip.key"
            type="button"
            class="rounded-full border px-3 py-1 text-sm"
            :class="filters.state === chip.key ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
            @click="filters.state = chip.key; search()"
          >{{ chip.name }} <span class="text-gray-500">{{ totals[chip.key] ?? 0 }}</span></button>
        </div>
        <div class="flex flex-wrap gap-2 mt-2">
          <button
            v-for="chip in sourceChips"
            :key="chip.key"
            type="button"
            class="rounded-full border px-3 py-1 text-sm"
            :class="[
              filters.source === chip.key ? 'border-indigo-500 bg-indigo-50 text-indigo-700' : 'border-gray-300 text-gray-700 hover:bg-gray-50',
              chip.key === 'leftover' && totals.leftover ? 'font-semibold text-red-700' : '',
            ]"
            @click="filters.source = chip.key; search()"
          >{{ chip.name }} <span class="text-gray-500">{{ chip.key === 'all' ? totals.all : (totals[chip.key] ?? 0) }}</span></button>
        </div>

        <div class="mt-3 flex flex-wrap items-end gap-2">
          <div class="w-full md:w-80">
            <SearchInput :placeholderStr="$t('Store, machine or promotion name')" v-model="filters.search" @keyup.enter="search()">
              {{ $t('Search') }}
            </SearchInput>
          </div>
          <Button
            class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-2 text-sm font-medium text-white hover:bg-green-600"
            @click="search()"
          >
            <MagnifyingGlassIcon class="h-4 w-4" aria-hidden="true" />
            <span>{{ $t('Search') }}</span>
          </Button>
          <p class="ml-auto text-sm text-gray-700">
            {{ $t('Showing :from to :to of :total results', { from: campaigns.meta.from ?? 0, to: campaigns.meta.to ?? 0, total: campaigns.meta.total }) }}
          </p>
        </div>
      </div>

      <div class="mt-4 -mx-3 sm:-mx-6 lg:-mx-8 shadow-sm ring-1 ring-black ring-opacity-5 overflow-x-auto bg-white">
        <table class="min-w-full border-separate" style="border-spacing: 0">
          <thead class="bg-gray-100">
            <tr class="divide-x divide-gray-200">
              <TableHead>{{ $t('Grab store') }}</TableHead>
              <TableHead>{{ $t('Promotion') }}</TableHead>
              <TableHead>{{ $t('On') }}</TableHead>
              <TableHead>{{ $t('When') }}</TableHead>
              <TableHead>{{ $t('Status') }}</TableHead>
              <TableHead>{{ $t('Made by') }}</TableHead>
            </tr>
          </thead>
          <tbody>
            <template v-for="(campaign, index) in rows" :key="campaign.id">
              <tr class="divide-x divide-gray-200 align-top cursor-pointer hover:bg-indigo-50" :class="index % 2 ? 'bg-gray-50' : 'bg-white'" @click="toggle(campaign.id)">
                <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-left">
                  <div class="font-mono whitespace-nowrap">{{ campaign.merchant_id }}</div>
                  <div class="flex items-center gap-1">
                    <MachineIdLink v-if="campaign.vend_code" :code="campaign.vend_code" />
                    <span v-else class="text-xs text-gray-500">{{ $t('No machine') }}</span>
                    <span v-if="campaign.operator_code" class="text-xs text-gray-500">{{ campaign.operator_code }}</span>
                  </div>
                </TableData>
                <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-left">
                  <div class="font-medium">{{ deal(campaign) }}</div>
                  <div v-if="campaign.name" class="text-xs text-gray-500">{{ campaign.name }}</div>
                  <div v-if="limits(campaign)" class="text-xs text-gray-500">{{ limits(campaign) }}</div>
                </TableData>
                <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-left text-sm">
                  <template v-if="campaign.discount?.scope?.type === 'order'">{{ $t('Whole order') }}</template>
                  <template v-else>
                    <div v-for="object in campaign.objects.slice(0, 3)" :key="object.id">{{ object.name || object.id }}</div>
                    <div v-if="campaign.objects.length > 3" class="text-xs text-gray-500">{{ $t('+ :count more', { count: campaign.objects.length - 3 }) }}</div>
                  </template>
                </TableData>
                <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-left whitespace-nowrap text-sm">
                  <div>{{ formatDateTime(campaign.starts_at) }}</div>
                  <div class="text-gray-500">{{ $t('to :time', { time: formatDateTime(campaign.ends_at) }) }}</div>
                </TableData>
                <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-left">
                  <span v-if="campaign.gone_at" class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold bg-gray-100 text-gray-700">
                    {{ $t('Gone from Grab :time', { time: formatDateTime(campaign.gone_at) }) }}
                  </span>
                  <span v-else-if="campaign.state === 'ongoing'" class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold bg-green-100 text-green-800">{{ $t('Live now') }}</span>
                  <span v-else class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold bg-blue-100 text-blue-800">{{ $t('Upcoming') }}</span>
                </TableData>
                <TableData :currentIndex="index" :totalLength="rows.length" inputClass="text-left">
                  <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold" :class="sourceClass[campaign.source]">
                    {{ sourceName[campaign.source] }}
                  </span>
                  <div v-if="campaign.source === 'leftover'" class="text-xs text-red-700 mt-1">{{ leftoverReason(campaign) }}</div>
                  <div v-if="campaign.mark1_campaign_id" class="text-xs mt-1">
                    <a :href="'/delivery-platform-campaigns/' + campaign.mark1_campaign_id + '/edit'" class="text-indigo-700 underline" @click.stop>
                      {{ $t('mark1 campaign #:id', { id: campaign.mark1_campaign_id }) }}
                    </a>
                  </div>
                  <div v-else-if="campaign.created_by" class="text-xs text-gray-500 mt-1">{{ $t('Grab says: :who', { who: campaign.created_by }) }}</div>
                </TableData>
              </tr>
              <tr v-if="open[campaign.id]" class="bg-white">
                <td colspan="6" class="px-3 py-2 border-b border-gray-200">
                  <div class="flex flex-wrap items-center gap-3 text-xs text-gray-600 mb-2">
                    <span>{{ $t('Grab campaign ID') }}: <span class="font-mono">{{ campaign.grab_campaign_id }}</span></span>
                    <span>{{ $t('First seen :time', { time: formatDateTime(campaign.first_seen_at) }) }}</span>
                    <span>{{ $t('Last seen :time', { time: formatDateTime(campaign.last_seen_at) }) }}</span>
                    <button v-if="campaign.ref_id" type="button" class="text-indigo-700 underline" @click.stop="refresh(campaign.ref_id)">{{ $t('Read this store again') }}</button>
                  </div>
                  <div v-if="campaign.objects.length > 3" class="text-xs text-gray-700 mb-2">
                    <span v-for="object in campaign.objects" :key="object.id" class="inline-block mr-3">{{ object.name || object.id }}</span>
                  </div>
                  <pre class="text-xs bg-gray-50 border rounded p-2 overflow-x-auto max-h-64">{{ JSON.stringify(campaign.campaign_json, null, 2) }}</pre>
                </td>
              </tr>
            </template>
            <tr v-if="!rows.length">
              <td colspan="6" class="py-6 text-center text-sm text-gray-500">
                {{ filters.view === 'ended' ? $t('No promotion has left Grab in the last :days days.', { days: keepGoneDays }) : $t('Grab lists no live or upcoming promotion on these stores.') }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <Paginator v-if="rows.length" :links="campaigns.links" :meta="campaigns.meta" class="mt-3" />
    </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import GrabPromotionViews from '@/Components/GrabPromotionViews.vue';
import MachineIdLink from '@/Components/MachineIdLink.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import TableData from '@/Components/TableData.vue';
import TableHead from '@/Components/TableHead.vue';
import { MagnifyingGlassIcon } from '@heroicons/vue/20/solid';
import { Head, router, usePage } from '@inertiajs/vue3';
import moment from 'moment';
import { computed, ref } from 'vue';
import { t } from '@/i18n';

const props = defineProps({
  campaigns: Object,
  totals: Object,
  reads: Object,
  filters: Object,
  keepGoneDays: Number,
})

const page = usePage()
const flashSuccess = computed(() => page.props.flash?.success)
const flashError = computed(() => page.props.flash?.error)
const rows = computed(() => props.campaigns.data ?? [])
const filters = ref({ ...props.filters })
const open = ref({})

const viewChips = [
  { key: 'live', name: t('On Grab now') },
  { key: 'ended', name: t('Gone from Grab') },
]
const stateChips = [
  { key: 'all', name: t('All') },
  { key: 'ongoing', name: t('Live now') },
  { key: 'upcoming', name: t('Upcoming') },
]
const sourceName = {
  mark1: t('Made in mark1'),
  leftover: t('Leftover'),
  merchant_app: t('Grab merchant app'),
  grab_team: t('Grab team'),
  unknown: t('Not found in mark1'),
}
const sourceClass = {
  mark1: 'bg-green-100 text-green-800',
  leftover: 'bg-red-100 text-red-800',
  merchant_app: 'bg-blue-100 text-blue-800',
  grab_team: 'bg-purple-100 text-purple-800',
  unknown: 'bg-amber-100 text-amber-800',
}
const sourceChips = [
  { key: 'all', name: t('Anyone') },
  ...['mark1', 'leftover', 'merchant_app', 'grab_team', 'unknown'].map(key => ({ key, name: sourceName[key] })),
]

function search() {
  router.get('/grab-promotions', filters.value, { preserveState: true, replace: true })
}

function refresh(refId) {
  router.post('/grab-promotions/stores/' + refId + '/refresh', {}, { preserveScroll: true })
}

function toggle(id) {
  open.value = { ...open.value, [id]: !open.value[id] }
}

function formatDateTime(value) {
  return value ? moment(value).format('DD MMM YY HH:mm') : '—'
}

// Grab sends amounts in dollars; shown as sent.
function money(value) {
  return '$' + Number(value ?? 0).toFixed(2)
}

function deal(campaign) {
  const discount = campaign.discount ?? {}
  const value = discount.value
  const qty = campaign.conditions?.bundleQuantity
  const cap = discount.cap ? ' ' + t('(up to :cap)', { cap: money(discount.cap) }) : ''
  switch (discount.type) {
    case 'net': return t(':amount off', { amount: money(value) })
    case 'percentage': return t(':pct% off', { pct: value }) + cap
    case 'fixPrice': return t('Now :price', { price: money(value) })
    case 'bundleSameFixPrice': return t('Buy :qty for :price', { qty, price: money(value) })
    case 'bundleDiffFixPrice': return t('Any :qty for :price', { qty, price: money(value) })
    case 'bundleSameNet': return t('Buy :qty, :amount off', { qty, amount: money(value) })
    case 'bundleDiffNet': return t('Any :qty, :amount off', { qty, amount: money(value) })
    case 'bundleSamePercentage': return t('Buy :qty, :pct% off', { qty, pct: value })
    case 'bundleDiffPercentage': return t('Any :qty, :pct% off', { qty, pct: value })
    case 'delivery': return t(':amount off delivery', { amount: money(value) })
    case 'freeItem': return t('Free item')
    default: return discount.type ?? '—'
  }
}

function limits(campaign) {
  const parts = []
  if (campaign.conditions?.minBasketAmount) {
    parts.push(t('min. spend :amount', { amount: money(campaign.conditions.minBasketAmount) }))
  }
  if (campaign.conditions?.eaterType && campaign.conditions.eaterType !== 'all') {
    parts.push(t('customers: :who', { who: campaign.conditions.eaterType }))
  }
  if (campaign.quotas?.totalCount) {
    parts.push(t(':count uses in all', { count: campaign.quotas.totalCount }))
  }
  if (campaign.quotas?.totalCountPerUser) {
    parts.push(t(':count per customer', { count: campaign.quotas.totalCountPerUser }))
  }
  return parts.join(' · ')
}

function leftoverReason(campaign) {
  if (campaign.mark1_binding_closed) {
    return t('mark1 ended it when the store left machine :code', { code: campaign.mark1_vend_code ?? '?' })
  }
  if (campaign.mark1_active === false) {
    return t('mark1 shows it ended; Grab still runs it')
  }
  return t('mark1 shows it ended :time; Grab still runs it', { time: formatDateTime(campaign.mark1_ended_at) })
}
</script>
