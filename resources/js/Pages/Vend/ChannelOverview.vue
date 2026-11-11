<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="onModalClose">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2" :class="[vend.is_active ? 'text-black' : 'text-gray-500']">
          <span class="text-gray-600" v-if="props.profile">
            {{ $t('Channel Overview') }}
          </span>
          <span v-if="vend.code">
            ID# <MachineIdLink :code="vendCodeLabel(vend)" />
          </span>
          <span v-if="vend.customer_code">
            ({{ vend.customer_code ? vend.customer_code : null }})
            {{ vend.customer_name ? vend.customer_name : null }}
          </span>
        </div>
      </template>
      <template #default>
        <!--
          Brian, 2026-11-11: show at once whether the machine has taken a Site RP / selling price change.
          The machine's own menu fetch is the proof (MachinePriceDelivery); hover for the details.
        -->
        <div v-if="priceDelivery" class="mb-4 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm">
          <template v-if="priceDelivery.follows_site">
            <span class="font-semibold text-gray-900">{{ $t('Prices on machine') }}</span>
            <span v-if="priceDelivery.state === 'current'" class="inline-flex items-center gap-1 rounded bg-green-100 px-2 py-0.5 text-xs font-medium text-green-800 cursor-help" v-tooltip="{ content: summaryTooltip(), html: true }">
              <CheckCircleIcon class="h-4 w-4" aria-hidden="true" />{{ $t('Up to date') }}
            </span>
            <span v-else-if="priceDelivery.state === 'stale'" class="inline-flex items-center gap-1 rounded bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800 cursor-help" v-tooltip="{ content: summaryTooltip(), html: true }">
              <ClockIcon class="h-4 w-4" aria-hidden="true" />{{ $t(':count waiting', { count: priceDelivery.stale_count }) }}
            </span>
            <span v-else class="inline-flex items-center gap-1 rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-600 cursor-help" v-tooltip="{ content: summaryTooltip(), html: true }">
              {{ $t('Not checked yet') }}
            </span>
            <span class="text-xs text-gray-500" v-if="priceDelivery.fetched_at">{{ $t('Machine loaded its prices :time', { time: formatTime(priceDelivery.fetched_at) }) }}</span>
            <span class="text-xs text-red-600" v-if="!priceDelivery.reachable">{{ $t('Machine is offline on MQTT') }}</span>
            <span v-if="pricePolling" class="text-xs text-gray-500">{{ $t('Checking…') }}</span>
            <button
              v-if="permissions.includes('update machine-settings') && priceDelivery.state !== 'current'"
              type="button"
              class="rounded-md border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 disabled:opacity-50"
              :disabled="pricePushing"
              @click="pushPricesAgain"
            >
              {{ $t('Send to machine again') }}
            </button>
          </template>
          <span v-else class="text-xs text-gray-500">
            {{ $t('Machine Price: this machine sells at its own board prices, so Ref Price is for reference only.') }}
          </span>
        </div>
        <div class="flex flex-col">
          <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5">
            <div class="inline-block min-w-full py-2 align-middle md:px-4 lg:px-6">
              <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                <table class="min-w-full divide-y divide-gray-300">
                  <thead class="bg-gray-50">
                    <tr>
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        #
                      </th>
                      <th scope="col" class="w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900" v-if="vend.product_mapping_name">
                        {{ $t('Image') }}
                      </th>
                      <th scope="col" class="w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900" v-if="vend.product_mapping_name">
                        {{ $t('Product') }}
                      </th>
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        {{ $t('Needed') }}
                      </th>
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        {{ $t('Bal') }}
                      </th>
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        {{ $t('Cap') }}
                      </th>
                      <th scope="col" class="w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        {{ $t('Machine Price') }}
                      </th>
                      <!-- <th scope="col" class="w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        Server Price
                      </th> -->
                      <!-- <th
                        scope="col"
                        class="w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"
                        v-if="channels.some(channel => 'amount2' in channel)"
                      >
                        P2
                        <span v-if="profile && profile.base_currency">
                          ({{ profile.base_currency.currency_symbol }})
                        </span>
                      </th> -->
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-blue-600">
                        {{ $t('Ref Price(:type)', { type: priceDelivery && priceDelivery.tier ? priceDelivery.tier : (vend.customer ? vend.customer.selling_price_type : vend.selling_price_type) }) }}
                      </th>
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        {{ $t('Error') }}
                        <br>
                        1d
                        <br>
                        2d
                        <br>
                        7d
                      </th>
                      <th
                        scope="col"
                        class="w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"
                      >
                        {{ $t('Last') }} <br>
                        {{ $t('Out Of') }} <br>
                        {{ $t('Stock') }} <br>
                        {{ $t('Since') }}
                      </th>
                      <th scope="col" class="w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"  v-if="permissions.includes('admin-access vends')">
                        <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-gray-200 px-4 py-2 md:px-3 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        @click="showFilters = true"
                        v-if="!showFilters && vend.is_mqtt"
                        >
                          <ChevronDoubleDownIcon class="h-3 w-3" aria-hidden="true"/>
                        </Button>
                        <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-4 py-2 md:px-3 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        @click="showFilters = false"
                        v-if="showFilters && vend.is_mqtt"
                        >
                          <ChevronDoubleUpIcon class="h-3 w-3" aria-hidden="true"/>
                        </Button>
                      </th>
                    </tr>
                  </thead>
                  <tbody class="bg-white">
                    <tr v-for="(channel, channelIndex) in channels" :key="channel.id" :class="channelIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold sm:pl-6 text-center" :class="[vend.is_active ? 'text-gray-900' : 'text-gray-500']">
                        {{ channel.code }}
                      </td>
                      <td class="whitespace-nowrap text-sm  font-semibold text-gray-900 text-center" v-if="vend.product_mapping_name">
                        <div class="flex justify-center items-center" >
                          <img class="h-20 w-20 min-w-20 min-h-20 rounded-lg object-contain bg-white border border-gray-200 p-1" :src="channel.product.thumbnail.full_url" alt="" v-if="channel.product && channel.product.thumbnail" :class="[channel.product.is_available ? '' : 'opacity-50']"/>
                        </div>
                      </td>
                      <td class="py-4 text-sm font-semibold text-center" :class="[vend.is_active && (channel.product && channel.product.is_available) ? 'text-gray-800' : 'text-gray-400']" v-if="vend.product_mapping_name">
                        <span v-if="!editable">
                          <span v-if="channel.product && channel.product.code">
                            {{ channel.product.code }}
                          </span>
                          <span class="break-normal text-xs" v-if="channel.product && channel.product.name">
                            <br> {{ channel.product.name }}
                          </span>
                        </span>
                        <span class="font-normal text-xs text-gray-700" v-else>
                          <!-- {{ channel.option_data }} -->
                          <MultiSelect
                            v-model="channel.product.option_data"
                            :options="productOptions"
                            trackBy="id"
                            valueProp="id"
                            label="full_name"
                            :placeholder="$t('Select')"
                            open-direction="bottom"
                            class="mt-1"
                          >
                          </MultiSelect>
                        </span>
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center" :class="[vend.is_active ? 'text-blue-600' : 'text-gray-400']">
                        {{ channel.capacity - channel.qty }}
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center" :class="[vend.is_active ? (channel.qty == 0 ? 'text-red-500 font-bold' : 'text-gray-900') : 'text-gray-400']">
                        {{ channel.qty }}
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center" :class="[vend.is_active ? 'text-gray-900' : 'text-gray-400']">
                        {{ channel.capacity }}
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center" :class="compareSellingPrice(channel)">
                        {{ (channel.amount).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                      </td>
                      <!-- <td class=" py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center">
                        {{ (channel.server_amount ?? 0).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                      </td> -->
                      <td
                        class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center text-gray-800"
                      >
                      <!-- {{ getSellingPrice(channel) ? ((getSellingPrice(channel)/ (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})) : null }} -->

                        <!-- {{ channel.product && channel.product.selling_prices[0] ? (channel.product.selling_prices[0].amount/ (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) : null }} -->
                        {{ channel.ref_price ? (channel.ref_price).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) : null }}
                        <div v-if="channelDelivery(channel) && channelDelivery(channel).state" class="mt-1">
                          <span
                            class="inline-flex items-center gap-0.5 rounded px-1.5 py-0.5 text-[11px] font-medium cursor-help"
                            :class="{
                              'bg-green-100 text-green-800': channelDelivery(channel).state === 'current',
                              'bg-amber-100 text-amber-800': channelDelivery(channel).state === 'stale',
                              'bg-gray-100 text-gray-600': channelDelivery(channel).state === 'unknown',
                            }"
                            v-tooltip="{ content: channelTooltip(channelDelivery(channel)), html: true }"
                          >
                            <CheckCircleIcon v-if="channelDelivery(channel).state === 'current'" class="h-3.5 w-3.5" aria-hidden="true" />
                            <ClockIcon v-else-if="channelDelivery(channel).state === 'stale'" class="h-3.5 w-3.5" aria-hidden="true" />
                            {{ channelDelivery(channel).state === 'current' ? $t('On machine') : (channelDelivery(channel).state === 'stale' ? $t('Waiting') : $t('Not checked yet')) }}
                          </span>
                        </div>
                      </td>
                      <td class="py-1 pl-1 pr-1 text-xs font-medium sm:pl-1 text-center">
                        <div class="flex flex-col space-y-2 w-full hover:cursor-pointer" @click="onChannelErrorClicked(channel)">
                            <span
                            v-if="channel.error_rate_json && 'one_day_error_rate' in channel.error_rate_json"
                            :class="[
                                channel.is_active ?
                                (channel.error_rate_json.one_day_error_rate >= 3 ? 'text-red-700' : 'text-green-700') :
                                'text-gray-400'
                            ]">
                              ({{channel.error_rate_json['one_day_error_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}}/{{channel.error_rate_json['one_day_total_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}})
                            </span>
                            <span
                            v-if="channel.error_rate_json && 'two_days_error_rate' in channel.error_rate_json"
                            :class="[
                                channel.is_active ?
                                (channel.error_rate_json.two_days_error_rate >= 3 ? 'text-red-700' : 'text-green-700') :
                                'text-gray-400'
                            ]">
                              ({{channel.error_rate_json['two_days_error_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}}/{{channel.error_rate_json['two_days_total_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}})
                            </span>
                            <span
                            v-if="channel.error_rate_json && 'seven_days_error_rate' in channel.error_rate_json"
                            :class="[
                                channel.is_active ?
                                (channel.error_rate_json['seven_days_error_rate'] >= 3 ? 'text-red-700' : 'text-green-700') :
                                'text-gray-400'
                            ]">
                              ({{channel.error_rate_json['seven_days_error_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}}/{{channel.error_rate_json['seven_days_total_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}})
                            </span>
                        </div>
                      </td>
                      <td
                        class="whitespace-nowrap py-4 pl-4 pr-3 text-xs font-medium sm:pl-6 text-center"
                        :class="[vend.is_active ? 'text-gray-900' : 'text-gray-400']"
                      >
                        <div class="flex flex-col space-y-1">
                          {{ channel.qty_sold_at_date_formatted }} <br>
                          {{ channel.qty_sold_at_time_formatted }} <br>
                          {{ channel.qty_sold_at_human_formatted }}
                        </div>
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center"
                      v-if="permissions.includes('admin-access vends') && vend.is_active">
                        <div class="flex justify-center space-x-1">
                          <Button
                            type="button" class="bg-yellow-300 hover:bg-yellow-400 px-1 py-1 text-xs text-gray-800 flex space-x-1"
                            @click="onDispenseClicked(channel)"
                            v-if="vend.is_mqtt && showFilters"
                          >
                            <span>
                              {{ $t('Dispense') }}
                            </span>
                          </Button>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        <div class="flex justify-between">
          <span>
            <Button
                type="button"
                class=" px-2 py-1 mt-2 ml-1 text-xs  flex space-x-1"
                :class="[editable ? 'bg-green-300 hover:bg-green-400 text-green-800' : 'bg-gray-300 hover:bg-gray-400 text-gray-800']"
                @click="onEditClicked()"
                v-if="vend.product_mapping_name"
            >
              <span class="flex space-x-1" v-if="!editable">
                <PencilSquareIcon class="w-4 h-4"></PencilSquareIcon>
                <span>
                    {{ $t('Edit Product') }}
                </span>
              </span>
              <span class="flex space-x-1" v-else>
                <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                <span>
                  {{ $t('Save') }}
                </span>
              </span>
            </Button>
          </span>
          <span v-if="vend.product_mapping_name">
            <a :href="'/product-mappings/' + vend.product_mapping_id + '/edit'" target="_blank" class="hover:cursor-pointer flex flex-col text-blue-800 text-sm p-3">
              <span class="" v-if="vend.product_mapping_name">
              {{ vend.product_mapping_name }}
              </span>
              <span v-if="vend.product_mapping_remarks">
                {{ vend.product_mapping_remarks }}
              </span>
            </a>
          </span>
        </div>
      </template>
    </Modal>
  </Teleport>

  <ErrorList
      v-if="showErrorListModal"
      :channel="channel"
      :vend="vend"
      :showErrorListModal="showErrorListModal"
      @errorListModalClose="onErrorListModalClosed"
  >
  </ErrorList>
</template>

<script setup>
import { ChevronDoubleDownIcon, ChevronDoubleUpIcon, CheckCircleIcon, ClockIcon, PencilSquareIcon } from '@heroicons/vue/20/solid';
import Button from '@/Components/Button.vue';
import Modal from '@/Components/Modal.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import ErrorList from '@/Pages/Vend/ErrorList.vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import axios from 'axios';
import moment from 'moment';
import { t } from '@/i18n';
import { router, usePage } from '@inertiajs/vue3';
import { vendCodeLabel } from '@/utils/vendCode';
import MachineIdLink from '@/Components/MachineIdLink.vue';

const props = defineProps({
  productOptions: Object,
  vend: Object,
  showModal: Boolean,
  // A historical snapshot (Report → Snapshot) keeps its stored prices: no live check over it.
  liveCheck: { type: Boolean, default: true },
})

const channel = ref()
const channels = ref([])
const operatorCountry = usePage().props.auth.operatorCountry
const permissions = usePage().props.auth.permissions
const productOptions = ref([])
const showFilters = ref(false)
const showErrorListModal = ref(false)


onMounted(() => {
  loadPriceDelivery().then(() => {
    if (priceDelivery.value && priceDelivery.value.follows_site && priceDelivery.value.state === 'stale') {
      startPricePolling()
    }
  })
  // loadChannelsErrorRate()
  productOptions.value = props.productOptions.data.map((data) => {return {id: data.id, full_name: data.full_name + (data.desc ?  ' ' + data.desc  : '')}})

  channels.value = props.vend.vendChannelsJson.map((channel) => {
    return {
      ...channel,
      product: channel.product ? {
        ...channel.product,
        option_data: {
          'id' : channel.product.id,
          'full_name' : channel.product.code + ' - ' + channel.product.name + (channel.product.desc ?  ' ' + channel.product.desc  : '')
        }
      } : null
    }
  })
})
const profile = usePage().props.auth.profile
const editable = ref(false)

// --- Live Ref Price + "has the machine taken it?" (MachinePriceDelivery, Brian 2026-11-11) ---
// The table's prices come from a snapshot of the channels; this check is live. Cents from the server, the table
// shows amount / 100 like the snapshot does.
const priceDelivery = ref(null)
const pricePolling = ref(false)
const pricePushing = ref(false)
const PRICE_POLL_EVERY_MS = 5000
const PRICE_POLL_FOR_MS = 2 * 60 * 1000
let pricePollTimer = null
let pricePollUntil = 0
let priceUnmounted = false

const machineId = () => props.vend.vend_id ?? props.vend.id

function applyPriceDelivery(data) {
  priceDelivery.value = data
  if (!data || !data.channels) {
    return
  }
  // The live Ref Price replaces the snapshot's, so the column and its red mismatch mark follow a change at once.
  channels.value.forEach((channel) => {
    const live = data.channels[String(channel.code)]
    if (live && channel.product) {
      channel.ref_price = live.ref_price !== null ? live.ref_price / 100 : null
    }
  })
}

async function loadPriceDelivery() {
  if (!props.liveCheck || !machineId()) {
    return
  }
  try {
    const { data } = await axios.get(`/vends/${machineId()}/price-delivery`)
    applyPriceDelivery(data)
  } catch (e) {
    // The table still shows the snapshot; the check is only an extra.
  }
}

function startPricePolling() {
  pricePollUntil = Date.now() + PRICE_POLL_FOR_MS
  if (!pricePollTimer) {
    pricePolling.value = true
    pricePollTimer = setTimeout(pollPriceDelivery, PRICE_POLL_EVERY_MS)
  }
}

async function pollPriceDelivery() {
  pricePollTimer = null
  await loadPriceDelivery()
  const waiting = priceDelivery.value && priceDelivery.value.follows_site && priceDelivery.value.state !== 'current'
  if (!priceUnmounted && waiting && Date.now() < pricePollUntil) {
    pricePollTimer = setTimeout(pollPriceDelivery, PRICE_POLL_EVERY_MS)
  } else {
    pricePolling.value = false
  }
}

async function pushPricesAgain() {
  pricePushing.value = true
  try {
    const { data } = await axios.post(`/vends/${machineId()}/price-delivery/push`)
    applyPriceDelivery(data)
    startPricePolling()
  } catch (e) {
    // Shown as still waiting; the person can press again.
  } finally {
    pricePushing.value = false
  }
}

onBeforeUnmount(() => {
  priceUnmounted = true
  if (pricePollTimer) {
    clearTimeout(pricePollTimer)
    pricePollTimer = null
  }
})

function channelDelivery(channel) {
  return priceDelivery.value && priceDelivery.value.channels ? priceDelivery.value.channels[String(channel.code)] : null
}

function formatTime(iso) {
  return moment(iso).format('D MMM YYYY h:mm a')
}

function money(cents) {
  if (cents === null || cents === undefined) {
    return t('no price')
  }
  const digits = operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent
  const symbol = operatorCountry.currency_symbol || ''
  // "Rp 28,000" but "$3.20": a symbol made of letters gets a space.
  return symbol + (/[A-Za-z]$/.test(symbol) ? ' ' : '') + (cents / 100).toLocaleString(undefined, { minimumFractionDigits: digits, maximumFractionDigits: digits })
}

function escapeHtml(text) {
  return String(text ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))
}

// Hover text for one channel: what the machine holds, what it should hold, who set the price and when.
function channelTooltip(row) {
  const d = priceDelivery.value
  const lines = []
  if (row.state === 'current') {
    lines.push(t('The machine sells at :price.', { price: money(row.loaded) }))
    if (d.fetched_at) {
      lines.push(t('Loaded :time.', { time: formatTime(d.fetched_at) }))
    }
  } else if (row.state === 'stale') {
    lines.push(t('The machine still sells at :old; the new price is :new.', { old: money(row.loaded), new: money(row.expected) }))
    if (d.fetched_at) {
      lines.push(t('It last loaded its prices :time.', { time: formatTime(d.fetched_at) }))
    }
    lines.push(d.reachable ? t('It takes the new price when it re-reads its menu (usually within a minute).') : t('It is offline on MQTT, so it takes the new price when it is back online.'))
  } else {
    lines.push(t('No price load recorded for this machine yet (checking began :date).', { date: d.tracking_since }))
    lines.push(t('It is confirmed the next time the machine loads its menu.'))
  }
  if (row.price_set_at) {
    lines.push(row.price_set_by
      ? t('RP:tier price set :time by :name.', { tier: d.tier, time: formatTime(row.price_set_at), name: row.price_set_by })
      : t('RP:tier price set :time.', { tier: d.tier, time: formatTime(row.price_set_at) }))
  }
  if (d.pushed_at) {
    lines.push(t('Last told to re-read: :time.', { time: formatTime(d.pushed_at) }))
  }
  return lines.map(escapeHtml).join('<br>')
}

// Hover text for the summary badge above the table.
function summaryTooltip() {
  const d = priceDelivery.value
  const lines = []
  if (d.state === 'current') {
    lines.push(t('Every channel on the machine holds its RP:tier price.', { tier: d.tier }))
  } else if (d.state === 'stale') {
    lines.push(t(':count channel(s) still hold an older price.', { count: d.stale_count }))
    lines.push(d.reachable ? t('It takes the new price when it re-reads its menu (usually within a minute).') : t('It is offline on MQTT, so it takes the new price when it is back online.'))
  } else {
    lines.push(t('No price load recorded for this machine yet (checking began :date).', { date: d.tracking_since }))
    lines.push(t('It is confirmed the next time the machine loads its menu.'))
  }
  if (d.fetched_at) {
    lines.push(t('Loaded :time.', { time: formatTime(d.fetched_at) }))
  }
  if (d.pushed_at) {
    lines.push(t('Last told to re-read: :time.', { time: formatTime(d.pushed_at) }))
  }
  if (d.mqtt_seen_at) {
    lines.push(t('Last heard on MQTT: :time.', { time: formatTime(d.mqtt_seen_at) }))
  }
  return lines.map(escapeHtml).join('<br>')
}
const emit = defineEmits(['modalClose'])

// function loadChannelsErrorRate() {
//   axios({
//       method: 'post',
//       url: '/vends/' + props.vend.id + '/channels-error-rate',
//   },
//   {}
//   ).then(response => {
//     vendChannelErrors.value = response.data
//   }).catch(error => {
//   })
// }

function getSellingPrice(channel) {
  let type = props.vend && props.vend.customer ? props.vend.customer.selling_price_type : props.vend.selling_price_type

  if(channel && channel.product && channel.product.sellingPrices) {
    let sellingPrice = channel.product.sellingPrices.find((sellingPrice) => sellingPrice.type == type)
    if(sellingPrice) {
      return sellingPrice.amount
    }
  }
  return null
}


function compareSellingPrice(channel) {
  // let type = props.vend && props.vend.customer ? props.vend.customer.selling_price_type : props.vend.selling_price_type

  // if(channel && channel.product && channel.product.sellingPrices) {
  //   let sellingPrice = channel.product.sellingPrices.find((sellingPrice) => sellingPrice.type == type)
  //   if(sellingPrice) {
  //     if(channel.amount != sellingPrice.amount/ 100) {
  //       return 'text-red-500'
  //     }
  //   }else {
  //     return 'text-red-500'
  //   }
  // }

  if(channel.amount && channel.ref_price && channel.amount != channel.ref_price) {
    return 'text-red-500'
  }

  return 'text-gray-800'
}

function onDispenseClicked(channel) {
  router.post('/vends/' + props.vend.id + '/dispense-product', {
    channel_id: channel.id
  }, {
    preserveScroll: true,
    onSuccess: () => {
      emit('modalClose')
    }
  })
}

function onEditClicked() {
  if(editable.value) {
    router.post('/vends/' + props.vend.id + '/edit-products', {
      channels: channels.value.map((channel) => {
        return {
          id: channel.id,
          product_id : channel.product.id,
          edited_product_id: channel.product.option_data.id
        }
      })
    }, {
      preserveScroll: true,
      onSuccess: () => {
        editable.value = false
        emit('modalClose')
      }
    })

  }else {
    editable.value = true
  }
}

function onChannelErrorClicked(channelData) {
  channel.value = channelData
  showErrorListModal.value = true
}

function onErrorListModalClosed() {
  showErrorListModal.value = false
}

function onModalClose() {
  // Prevent closing parent modal while child ErrorList is open
  if (!showErrorListModal.value) {
    emit('modalClose')
  }
}

function formatDatetime(value) {
  return moment(value).format('YYMMDD hh:mm A');
}


</script>
