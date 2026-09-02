<template>
  <!--
    CityBox Smart Chiller row for the two machine indexes, cell-for-cell under
    the SAME column headers as a vending machine (Brian, 2026-09-02): a column
    that has no meaning for a chiller (temperature, modem, coin, APK, mark1
    sales…) is rendered EMPTY; a column that does keeps the vending cell's
    format. Multi-root component: one <TableData> per header cell, so the
    page just swaps this in for the vending cells after "#".

    page="customers" → Vend/CustomerIndex (Operation Dashboard)
    page="index"     → Vend/Index
  -->

  <!-- ══════════ Operation Dashboard (Vend/CustomerIndex) ══════════ -->
  <template v-if="page === 'customers'">
    <!-- Machine ID · Setting Chart · Prefix · Product Mapping · Site · Postcode · Ref Price · Campaign -->
    <TableData :currentIndex="vendIndex" :totalLength="totalLength" inputClass="text-left">
      <div class="flex flex-col space-y-1 max-w-[150px]">
        <Link :href="settingsHref" :class="[active ? 'text-blue-600' : 'text-gray-400']" class="text-left hover:underline" v-tooltip="'Open this machine\'s settings'">{{ vend.code }}</Link>
        <span class="inline-flex rounded px-1 py-0.5 text-[10px] font-semibold border w-fit bg-indigo-100 text-indigo-800 border-indigo-300 leading-none">Smart Chiller · CityBox</span>
        <span class="text-[10px] text-gray-500 font-mono leading-none">{{ vend.citybox_equipment_id }}</span>
        <span v-if="status.name" class="text-xs text-gray-800">{{ status.name }}</span>
        <span class="flex flex-col space-y-0.5" v-if="mappingName">
          <a v-if="mappingId" :href="'/product-mappings/' + mappingId + '/edit'" target="_blank" :title="mappingName" class="text-gray-800 text-xs font-medium underline decoration-gray-400 underline-offset-2 min-w-0 break-all">{{ mappingName }}</a>
          <span v-else :title="mappingName" class="text-xs text-gray-800 min-w-0 break-all">{{ mappingName }}</span>
        </span>
        <span v-if="vend.customer_id" :class="[vend.customer_is_active || vend.is_testing ? 'text-gray-800' : 'text-gray-400']">
          <a class="text-blue-700 hover:underline" target="_blank" :href="'/customers/' + vend.customer_id + '/edit'" v-tooltip="'Open this site in the Site editor'">{{ vend.customer_name }}</a>
        </span>
        <span v-else class="text-xs text-red-600">No site bound</span>
        <div class="inline-flex rounded px-0.5 py-0.5 text-xs border w-fit bg-gray-100 text-gray-800 border-gray-300" v-if="vend.postcode">{{ vend.postcode }}</div>
        <div class="flex flex-col space-y-1 pt-1">
          <ActionButtons :vend="vend" :status="status" :machine="machine" />
        </div>
      </div>
    </TableData>

    <!-- T1/T2/Updated/Fan/PWRON/SIM → the chiller's machine telemetry from CityBox -->
    <TableData :currentIndex="vendIndex" :totalLength="totalLength" inputClass="text-center">
      <div class="flex flex-col space-y-1">
        <Badge :title="machine.stateLabel" :sub="machine.stateAt ? shortAgo(machine.stateAt) : 'not polled'" :cls="active ? machine.stateBadge : 'bg-gray-200 text-gray-400'" tip="Live session state (get_device_status_new)" />
        <Badge v-if="status.recoveredAt" title="Last back online" :sub="status.recoveredAt" :cls="active ? 'bg-gray-100' : 'bg-gray-200 text-gray-400'" />
        <Badge v-if="machine.lastOpen" title="Last door open" :sub="machine.lastOpen.at + (machine.lastOpen.source ? ' · ' + sourceLabel(machine.lastOpen.source) : '')" :cls="active ? 'bg-indigo-100' : 'bg-gray-200 text-gray-400'" tip="Last ops door-open through mark1" />
        <Badge v-if="machine.poll" title="Last poll" :sub="shortAgo(machine.poll.at) + ' · ' + (machine.poll.ok ? machine.poll.products_seen + ' SKU, ' + machine.poll.duration_ms + ' ms' : 'failed')" :cls="active ? (machine.poll.ok ? 'bg-green-100' : 'bg-amber-100') : 'bg-gray-200 text-gray-400'" :tip="machine.poll.error || ''" />
      </div>
    </TableData>

    <!-- Inventory Status: #Channel, Required, Balance/Capacity · Stock Cost/Value → channels + their prices -->
    <TableData :currentIndex="vendIndex" :totalLength="totalLength" inputClass="text-left">
      <div class="flex flex-col space-y-2 hover:bg-gray-100 p-2 rounded cursor-pointer transition duration-150 ease-in-out border border-transparent hover:border-gray-200" @click="$emit('overview', vend)" v-tooltip="'View Channel Status'">
        <ul class="sm:grid sm:grid-cols-[1fr_1fr]" v-if="channels.length">
          <li v-for="(ch, i) in channels" :key="ch.code" class="quick-look" :class="[i > 0 && String(ch.code)[0] !== String(channels[i - 1].code)[0] ? 'col-start-1' : '']">
            <span :class="[i > 0 && String(ch.code)[0] !== String(channels[i - 1].code)[0] ? 'border-t-4 pt-1' : '']">
              <span :class="[active ? 'text-black' : 'text-gray-600']">#{{ ch.code }}</span>,
              <span :class="[active ? 'text-blue-600' : 'text-gray-500']">{{ ch.capacity - ch.qty }},</span>
              <span :class="[active ? (ch.qty <= 2 && ch.qty > 0 ? 'text-blue-700' : (ch.qty == 0 ? 'text-red-700' : 'text-green-700')) : 'text-gray-400']">{{ ch.qty }}/{{ ch.capacity }}</span>
            </span>
          </li>
        </ul>
        <span v-else class="text-xs text-gray-500">No planogram yet — Pull to re-mirror.</span>
        <div class="flex flex-col space-y-1 pl-2 text-center" v-if="machine.prices.length">
          <div class="text-gray-800">Value: {{ money(stock.valueCents) }}</div>
          <div class="text-gray-800">Full Load Value: {{ money(stock.fullLoadCents) }}</div>
          <div class="text-gray-800">Stock Qty: {{ stock.qty }} pcs</div>
          <div class="text-[11px] text-gray-500">Layers {{ machine.layersUsed.join(', ') }} of {{ machine.layerCount }}</div>
        </div>
      </div>
    </TableData>

    <!-- Error: Uncleared / rates / refunds → the only error a chiller reports is a failed poll -->
    <TableData :currentIndex="vendIndex" :totalLength="totalLength" inputClass="text-center">
      <div class="flex flex-col space-y-1" v-if="machine.poll && !machine.poll.ok">
        <span class="inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border bg-amber-100 text-amber-800 border-amber-300" v-tooltip="machine.poll.error || ''">Poll failed</span>
        <span class="text-[10px] text-gray-500 break-all">{{ machine.poll.error }}</span>
      </div>
    </TableData>

    <!-- Stock: Balance Qty · Remaining Channel# -->
    <TableData :currentIndex="vendIndex" :totalLength="totalLength" inputClass="text-center" v-if="!isDriver">
      <div class="flex flex-col space-y-2" v-if="channels.length">
        <span>&nbsp;</span>
        <span :class="[active ? (stock.percent <= 20 ? 'text-red-700' : (stock.percent > 50 ? 'text-green-700' : 'text-blue-700')) : 'text-gray-400']">
          {{ stock.qty }}/ {{ stock.capacity }} <br>({{ stock.percent }}%)
        </span>
        <div class="flex justify-center border-b border-gray-300 pb-2 mb-2 w-full">
          <span :class="[active ? (stock.inStockPercent <= 40 ? 'text-red-700' : (stock.inStockPercent > 70 ? 'text-green-700' : 'text-blue-700')) : 'text-gray-400']">
            {{ stock.inStockSkus }}/ {{ channels.length }} <br>({{ stock.inStockPercent }}%)
          </span>
        </div>
      </div>
    </TableData>

    <!-- Sales(qty) — no mark1 sales for a chiller (their app sells) -->
    <TableData :currentIndex="vendIndex" :totalLength="totalLength" inputClass="text-center" v-if="!isDriver"></TableData>

    <!-- Last Job -->
    <TableData :currentIndex="vendIndex" :totalLength="totalLength" inputClass="text-center" v-if="indexType === 'customers' && !isDriver">
      <JobCell :item="vend.lastOpsJobItem" />
    </TableData>

    <!-- Upcoming Job -->
    <TableData :currentIndex="vendIndex" :totalLength="totalLength" inputClass="text-center" v-if="indexType === 'customers' && !isDriver">
      <JobCell :item="vend.nextOpsJobItem" />
    </TableData>

    <!-- Refilling Routes: zone · preferred days · frequency -->
    <TableData :currentIndex="vendIndex" :totalLength="totalLength" inputClass="text-center" v-if="indexType === 'customers' && !isDriver">
      <span :class="active ? 'text-gray-900' : 'text-gray-400'">
        <div class="flex flex-col space-y-2">
          <span>{{ vend.zone_name }}</span>
          <div class="flex flex-col space-y-1" v-if="vend.preferred_visit_days_json">
            <span v-for="(day, i) in DAYS" :key="i">
              <span v-if="vend.preferred_visit_days_json[i] == true" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">{{ day }}</span>
            </span>
          </div>
          <span>{{ vend.frequency_per_week_status_name }}</span>
        </div>
      </span>
    </TableData>

    <!-- Lifetime Sales · Begin Dt · Contract dates … → only the begin date applies -->
    <TableData :currentIndex="vendIndex" :totalLength="totalLength" inputClass="text-center" v-if="!isDriver">
      <span :class="active ? 'text-gray-900' : 'text-gray-400'" v-if="vend.begin_date">{{ vend.begin_date }}</span>
    </TableData>

    <!-- Contract Type · Loc Fees … — site commercial terms, edited on the Site page -->
    <TableData :currentIndex="vendIndex" :totalLength="totalLength" inputClass="text-center" v-if="!isDriver"></TableData>

    <!-- Machine Status: HTTP/MQTT/SIM/Modem → CityBox online + their ops status -->
    <TableData :currentIndex="vendIndex" :totalLength="totalLength" inputClass="text-center" v-if="!isDriver">
      <div class="flex flex-col space-y-1">
        <Badge :title="vend.is_online ? 'CityBox Online' : 'CityBox Offline'" :sub="vend.is_online ? (status.syncedAt ? shortAgo(status.syncedAt) : '') : (status.offlineSince || '')" :cls="active ? (vend.is_online ? 'bg-green-200' : 'bg-red-200') : 'bg-gray-200 text-gray-400'" />
        <Badge :title="status.opsLabel" :sub="status.isStale ? 'stale — last sync ' + shortAgo(status.syncedAt) : ''" :cls="active ? (status.isRunning ? 'bg-green-100' : 'bg-red-100') : 'bg-gray-200 text-gray-400'" tip="CityBox ops status (box_list.status)" />
      </div>
    </TableData>

    <!-- Payment Device — CityBox's own app collects; nothing of ours -->
    <TableData :currentIndex="vendIndex" :totalLength="totalLength" inputClass="text-center" v-if="!isDriver"></TableData>

    <!-- Operator · Acc Manager · Location · Machine active badge -->
    <TableData :currentIndex="vendIndex" :totalLength="totalLength" inputClass="text-center" v-if="indexType === 'customers' && !isDriver">
      <span :class="active ? 'text-gray-900' : 'text-gray-400'">
        <div class="flex flex-col space-y-2">
          <span class="flex flex-col space-y-1">
            <span>{{ vend.operator_code }}</span>
            <span>{{ vend.account_manager_name }}</span>
            <span>{{ vend.location_type_name }}</span>
            <hr class="border-t border-gray-300 my-2" />
            <span class="text-gray-900">{{ status.model }}</span>
          </span>
          <MachineBadge :vend="vend" />
        </div>
      </span>
    </TableData>
  </template>

  <!-- ══════════ Vend/Index ══════════ -->
  <template v-else>
    <!-- Machine ID · Setting Chart · Prefix · Product Mapping · Site · Ref Price -->
    <TableData :currentIndex="vendIndex" :totalLength="totalLength" inputClass="text-left">
      <div class="flex flex-col space-y-1">
        <Link :href="settingsHref" :class="[active ? 'text-blue-600' : 'text-gray-400']" class="hover:underline">{{ vend.code }}</Link>
        <span class="inline-flex rounded px-1 py-0.5 text-[10px] font-semibold border w-fit bg-indigo-100 text-indigo-800 border-indigo-300 leading-none">Smart Chiller · CityBox</span>
        <span class="text-[10px] text-gray-500 font-mono leading-none">{{ vend.citybox_equipment_id }}</span>
        <span v-if="status.name" class="text-xs text-gray-800">{{ status.name }}</span>
        <span class="flex flex-col space-y-0.5" v-if="mappingName">
          <a v-if="mappingId" :href="'/product-mappings/' + mappingId + '/edit'" target="_blank" class="text-gray-800 text-xs font-medium underline decoration-gray-400 underline-offset-2">{{ mappingName }}</a>
          <span v-else class="text-xs text-gray-800">{{ mappingName }}</span>
        </span>
        <span v-if="vend.customer_id" :class="[vend.customer_is_active || vend.is_testing ? 'text-gray-800' : 'text-gray-400']">
          <a class="text-blue-700 hover:underline" target="_blank" :href="'/customers/' + vend.customer_id + '/edit'">{{ vend.customer_name }}</a>
        </span>
        <span v-else class="text-xs text-red-600">No site bound</span>
      </div>
    </TableData>

    <!-- T1: Machine Temp … → machine telemetry from CityBox -->
    <TableData :currentIndex="vendIndex" :totalLength="totalLength" inputClass="text-center">
      <div class="flex flex-col space-y-1">
        <Badge :title="machine.stateLabel" :sub="machine.stateAt ? shortAgo(machine.stateAt) : 'not polled'" :cls="active ? machine.stateBadge : 'bg-gray-200 text-gray-400'" tip="Live session state (get_device_status_new)" />
        <Badge v-if="machine.lastOpen" title="Last door open" :sub="machine.lastOpen.at" :cls="active ? 'bg-indigo-100' : 'bg-gray-200 text-gray-400'" />
        <Badge v-if="machine.poll" title="Last poll" :sub="shortAgo(machine.poll.at) + ' · ' + (machine.poll.ok ? machine.poll.products_seen + ' SKU' : 'failed')" :cls="active ? (machine.poll.ok ? 'bg-green-100' : 'bg-amber-100') : 'bg-gray-200 text-gray-400'" :tip="machine.poll.error || ''" />
      </div>
    </TableData>

    <!-- Setting Chart / version / mapping cell — APK config: nothing for a chiller -->
    <TableData :currentIndex="vendIndex" :totalLength="totalLength" inputClass="text-center"></TableData>

    <!-- Modem / IMEI / Status — their connectivity, not ours -->
    <TableData :currentIndex="vendIndex" :totalLength="totalLength" inputClass="text-center"></TableData>

    <!-- Inventory Status -->
    <TableData :currentIndex="vendIndex" :totalLength="totalLength" inputClass="text-left">
      <div class="flex flex-col space-y-2">
        <ul class="sm:grid sm:grid-cols-[105px_minmax(110px,_1fr)_100px] hover:cursor-pointer" v-if="channels.length" @click="$emit('overview', vend)">
          <li v-for="(ch, i) in channels" :key="ch.code" class="quick-look" :class="[i > 0 && String(ch.code)[0] !== String(channels[i - 1].code)[0] ? 'col-start-1' : '']">
            <span :class="[i > 0 && String(ch.code)[0] !== String(channels[i - 1].code)[0] ? 'border-t-4 pt-1' : '']">
              <span :class="[active ? 'text-black' : 'text-gray-600']">#{{ ch.code }},</span>
              <span :class="[active ? 'text-blue-600' : 'text-gray-500']">{{ ch.capacity - ch.qty }},</span>
              <span :class="[active ? (ch.qty <= 2 ? 'text-red-700' : 'text-green-700') : 'text-gray-400']">{{ ch.qty }}/{{ ch.capacity }}</span>
            </span>
          </li>
        </ul>
        <span v-else class="text-xs text-gray-500">No planogram yet — Pull to re-mirror.</span>
        <div class="flex flex-col space-y-1 pl-2 text-center" v-if="machine.prices.length">
          <div class="text-gray-800">Value: {{ money(stock.valueCents) }}</div>
          <div class="text-gray-800">Full Load Value: {{ money(stock.fullLoadCents) }}</div>
          <div class="text-gray-800">Stock Qty: {{ stock.qty }} pcs</div>
        </div>
      </div>
    </TableData>

    <!-- Errors -->
    <TableData :currentIndex="vendIndex" :totalLength="totalLength" inputClass="text-center">
      <span v-if="machine.poll && !machine.poll.ok" class="inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border bg-amber-100 text-amber-800 border-amber-300" v-tooltip="machine.poll.error || ''">Poll failed</span>
    </TableData>

    <!-- Balance Stock · Remaining SKU# -->
    <TableData :currentIndex="vendIndex" :totalLength="totalLength" inputClass="text-center">
      <div class="flex flex-col space-y-1" v-if="channels.length">
        <span :class="[active ? (stock.percent <= 20 ? 'text-red-700' : (stock.percent > 50 ? 'text-green-700' : 'text-blue-700')) : 'text-gray-400']">
          {{ stock.qty }}/ {{ stock.capacity }} <br>({{ stock.percent }}%)
        </span>
        <span :class="[active ? (stock.inStockPercent <= 40 ? 'text-red-700' : (stock.inStockPercent > 70 ? 'text-green-700' : 'text-blue-700')) : 'text-gray-400']">
          {{ stock.inStockSkus }}/ {{ channels.length }} <br>({{ stock.inStockPercent }}%)
        </span>
      </div>
    </TableData>

    <!-- Sales(qty) -->
    <TableData :currentIndex="vendIndex" :totalLength="totalLength" inputClass="text-center"></TableData>

    <!-- Machine Status -->
    <TableData :currentIndex="vendIndex" :totalLength="totalLength" inputClass="text-center">
      <div class="flex flex-col space-y-1">
        <MachineBadge :vend="vend" />
        <Badge :title="vend.is_online ? 'Online' : 'Offline'" :sub="vend.is_online ? (status.syncedAt ? shortAgo(status.syncedAt) : '') : (status.offlineSince || '')" :cls="active ? (vend.is_online ? 'bg-green-200' : 'bg-red-200') : 'bg-gray-200 text-gray-400'" />
        <Badge :title="status.opsLabel" :sub="status.isStale ? 'stale' : ''" :cls="active ? (status.isRunning ? 'bg-green-100' : 'bg-red-100') : 'bg-gray-200 text-gray-400'" tip="CityBox ops status (box_list.status)" />
      </div>
    </TableData>

    <!-- Payment Device -->
    <TableData :currentIndex="vendIndex" :totalLength="totalLength" inputClass="text-center"></TableData>

    <!-- Last Visited (customers view) -->
    <TableData :currentIndex="vendIndex" :totalLength="totalLength" inputClass="text-center" v-if="indexType === 'customers'"></TableData>

    <!-- Avg Per Day (Last 30d) — mark1 sales only -->
    <TableData :currentIndex="vendIndex" :totalLength="totalLength" inputClass="text-center"></TableData>

    <!-- Postcode (customers view) -->
    <TableData :currentIndex="vendIndex" :totalLength="totalLength" inputClass="text-center" v-if="indexType === 'customers'">
      <span :class="active ? 'text-gray-900' : 'text-gray-400'">{{ vend.postcode }}</span>
    </TableData>

    <!-- Firmware / LCD / Operator — only the operator and the CityBox model apply -->
    <TableData :currentIndex="vendIndex" :totalLength="totalLength" inputClass="text-center">
      <div class="flex flex-col space-y-1" :class="active ? 'text-gray-900' : 'text-gray-400'">
        <span>{{ status.model }}</span>
        <span>{{ vend.operator_code }}</span>
        <span v-if="indexType === 'customers'">{{ vend.location_type_name }}</span>
        <span v-if="indexType === 'customers'">{{ vend.account_manager_name }}</span>
      </div>
    </TableData>

    <!-- Actions -->
    <TableData :currentIndex="vendIndex" :totalLength="totalLength" inputClass="text-center">
      <div class="flex flex-col items-center space-y-1">
        <Link :href="'/vends/' + vendId + '/edit'">
          <Button type="button" class="bg-blue-300 hover:bg-blue-400 px-3 py-2 text-xs text-gray-800 flex space-x-1">
            <EllipsisHorizontalCircleIcon class="w-4 h-4" /><span>more</span>
          </Button>
        </Link>
        <ActionButtons :vend="vend" :status="status" :machine="machine" />
      </div>
    </TableData>
  </template>
</template>

<script setup>
import { computed, defineComponent, h } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { ArrowPathIcon, EllipsisHorizontalCircleIcon, LockOpenIcon } from '@heroicons/vue/20/solid'
import { useToast } from 'vue-toastification'
import Button from '@/Components/Button.vue'
import TableData from '@/Components/TableData.vue'

const props = defineProps({
  vend: { type: Object, required: true },
  vendIndex: { type: Number, required: true },
  totalLength: { type: Number, required: true },
  page: { type: String, required: true },          // 'customers' | 'index'
  indexType: { type: String, default: null },
  isDriver: { type: Boolean, default: false },
  currencySymbol: { type: String, default: 'S$' },
})
defineEmits(['overview'])

const toast = useToast()

const vendId = computed(() => props.vend.vend_id ?? props.vend.id)
const settingsHref = computed(() => '/settings/vend/' + vendId.value + '/update')
const active = computed(() => !!(props.vend.is_active || props.vend.is_testing))
const mappingName = computed(() => props.vend.productMapping?.name ?? props.vend.vend?.productMapping?.name ?? props.vend.product_mapping_name ?? null)
const mappingId = computed(() => props.vend.productMapping?.id ?? props.vend.vend?.productMapping?.id ?? props.vend.product_mapping_id ?? null)

const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']

// Vocabularies mirror the PHP enums (DeviceOpsStatus / DeviceState / DeviceType); ChillerStatus is the source.
const OPS_LABELS = { 0: 'Banned (禁运)', 1: 'Running (启运)', 98: 'Being removed (撤机中)', 99: 'Removed (已撤机)' }
const MODELS = { 'visual-2': 'CityBox F5 (visual-2)', 'visual-8': 'CityBox C5 (visual-8)' }
const STATE_LABELS = { FREE: 'Idle', OPENING: 'Door open', BUSY: 'In use', MAINTENANCE: 'Maintenance', NOT_FOUND: 'Unreachable', OTHER: 'Unknown state' }
const STATE_BADGES = { FREE: 'bg-green-200', OPENING: 'bg-indigo-200', BUSY: 'bg-blue-200', MAINTENANCE: 'bg-amber-200', NOT_FOUND: 'bg-gray-200' }
const SOURCE_LABELS = { ops_job_page: 'ops job', ops_job_item_page: 'ops job item', vend_settings: 'settings', api: 'API' }
const STALE_AFTER_MS = 5 * 60 * 1000
function sourceLabel(src) { return SOURCE_LABELS[src] || src }

const status = computed(() => {
  const j = props.vend.citybox_status_json || {}
  const ops = j.equipment_status === undefined || j.equipment_status === null ? null : Number(j.equipment_status)
  const syncedAt = props.vend.citybox_synced_at ? new Date(props.vend.citybox_synced_at) : null
  const age = syncedAt ? Date.now() - syncedAt.getTime() : null
  return {
    name: j.name || null,
    model: MODELS[j.device_type] || 'CityBox',
    opsLabel: (ops !== null && OPS_LABELS[ops]) || j.equipment_status_str || 'Status unknown',
    isRunning: ops === 1,
    offlineSince: j.heartbeat_last_offline || null,
    recoveredAt: j.heartbeat_last_recovery || null,
    syncedAt,
    isStale: age === null || age > STALE_AFTER_MS,
  }
})

const machine = computed(() => {
  const j = props.vend.citybox_status_json || {}
  const state = j.device_state ? String(j.device_state).toUpperCase() : null
  const stock = j.stock && typeof j.stock === 'object' ? Object.values(j.stock) : []
  return {
    state,
    stateLabel: state ? (STATE_LABELS[state] || state) : 'State not polled',
    stateBadge: (state && STATE_BADGES[state]) || 'bg-gray-100',
    stateAt: j.device_state_at || null,
    // Only a KNOWN busy state blocks Open Door; NOT_FOUND does not (units are seen online per
    // box_list yet NOT_FOUND on the status call — the open itself is the authority).
    doorBlocked: state !== null && ['OPENING', 'BUSY', 'MAINTENANCE'].includes(state),
    layersUsed: [...new Set(stock.map(p => Number(p.layer)).filter(n => n > 0))].sort((a, b) => a - b),
    layerCount: 5,
    prices: stock.map(p => ({ id: p.product_id, price: Number(p.price ?? 0), active: Number(p.active_price ?? p.price ?? 0), qty: Number(p.quantity ?? 0) })),
    lastOpen: j.last_ops_open && j.last_ops_open.at ? j.last_ops_open : null,
    poll: j.poll && j.poll.at ? j.poll : null,
  }
})

const channels = computed(() => {
  const list = Array.isArray(props.vend.vendChannelsJson) ? props.vend.vendChannelsJson : []
  return list
    .filter(c => c && c.is_active !== 0 && c.is_active !== false)
    .map(c => ({ code: c.code, qty: Number(c.qty ?? 0), capacity: Number(c.capacity ?? 0), amount: Number(c.amount ?? 0), product: c.product || null }))
    .sort((a, b) => Number(a.code) - Number(b.code))
})

const stock = computed(() => {
  const qty = channels.value.reduce((s, c) => s + c.qty, 0)
  const capacity = channels.value.reduce((s, c) => s + c.capacity, 0)
  const inStockSkus = channels.value.filter(c => c.qty > 0).length
  // Value at THEIR active price (cents, from the poll snapshot); channel.amount is dollars.
  const valueCents = machine.value.prices.reduce((s, p) => s + p.active * p.qty, 0)
  const fullLoadCents = channels.value.reduce((s, c) => s + Math.round(c.amount * 100) * c.capacity, 0)
  return {
    qty, capacity, inStockSkus, valueCents, fullLoadCents,
    percent: capacity ? Math.round((qty / capacity) * 100) : 0,
    inStockPercent: channels.value.length ? Math.round((inStockSkus / channels.value.length) * 100) : 0,
  }
})

function money(cents) { return props.currencySymbol + (Number(cents || 0) / 100).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }

function shortAgo(v) {
  const d = v instanceof Date ? v : new Date(String(v).replace(' ', 'T'))
  if (isNaN(d.getTime())) return String(v)
  const m = Math.round((Date.now() - d.getTime()) / 60000)
  if (m < 1) return 'just now'
  if (m < 60) return m + ' min ago'
  const h = Math.round(m / 60)
  if (h < 48) return h + ' h ago'
  return Math.round(h / 24) + ' d ago'
}

// ── tiny render helpers, same badge shape the vending cells use ─────────────
const Badge = defineComponent({
  props: { title: String, sub: String, cls: String, tip: String },
  setup: (p) => () => h('div', {
    class: ['inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full', p.cls],
    title: p.tip || undefined,
  }, [h('div', { class: 'flex flex-col' }, [h('span', { class: 'font-bold' }, p.title), p.sub ? h('span', p.sub) : null])]),
})

const MachineBadge = defineComponent({
  props: { vend: Object },
  setup: (p) => () => {
    const v = p.vend
    const cls = v.is_sold ? 'bg-yellow-200' : (v.is_testing ? 'bg-gray-200' : ((v.vend_is_active ?? v.is_active) ? 'bg-blue-200' : (v.is_disposed ? 'bg-red-300' : 'bg-red-200')))
    const label = v.is_sold ? 'Sold' : (v.is_testing ? 'Testing' : ((v.vend_is_active ?? v.is_active) ? 'Active' : (v.is_disposed ? 'Disposed' : 'Not Active')))
    return h('div', { class: ['inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full', cls] },
      [h('div', { class: 'flex flex-col' }, [h('span', { class: 'font-bold' }, 'Machine'), h('span', label)])])
  },
})

const JobCell = defineComponent({
  props: { item: Object },
  setup: (p) => () => {
    const it = p.item
    if (!it) return null
    const job = it.opsJob || {}
    const diffCls = (job.date_diff_count < 1 && job.date_diff_count > 0) ? 'bg-green-200' : ((job.date_diff_count > -1 && job.date_diff_count < 0) ? 'bg-yellow-200' : (job.date_diff_count > 10 ? 'bg-red-300' : ''))
    return h('div', { class: 'flex flex-col space-y-1 max-w-28 mx-auto' }, [
      it.sequence ? h('span', { class: 'font-semibold' }, '(' + it.sequence + ')') : null,
      h('a', { href: '/ops-jobs/items/' + it.id + '/edit', title: 'Open this ops job item' },
        [h('div', { class: 'inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full text-gray-900 bg-indigo-300' },
          [h('span', { class: 'text-blue-800 underline' }, it.ref_id)])]),
      job.deliveredBy ? h('span', job.deliveredBy.name) : null,
      job.date_formatted ? h('span', job.date_formatted) : null,
      job.date_diff_human ? h('div', { class: ['inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full text-gray-900', diffCls] }, job.date_diff_human) : null,
    ])
  },
})

// Open Door + Pull — the only two actions a chiller has. Same endpoints as Setting/Edit.
const ActionButtons = defineComponent({
  props: { vend: Object, status: Object, machine: Object },
  setup: (p) => {
    let busy = false
    const id = () => p.vend.vend_id ?? p.vend.id
    const openDoor = () => {
      if (!confirm('Open chiller ' + p.vend.code + ' (' + (p.status.name || p.vend.citybox_equipment_id) + ') for restocking now?')) return
      busy = true
      router.post('/vends/' + id() + '/citybox-open-door', {}, {
        preserveScroll: true,
        onSuccess: () => toast.success('Door opened', { timeout: 3000 }),
        onError: (e) => toast.error(e.citybox || 'Door open failed', { timeout: 6000 }),
        onFinish: () => { busy = false },
      })
    }
    const pull = () => {
      busy = true
      router.post('/vends/' + id() + '/citybox-pull', {}, {
        preserveScroll: true,
        onSuccess: () => toast.success('Pulled from CityBox', { timeout: 3000 }),
        onError: (e) => toast.error(e.citybox || 'Pull failed', { timeout: 6000 }),
        onFinish: () => { busy = false },
      })
    }
    return () => {
      const noLink = !p.vend.citybox_equipment_id
      const blocked = noLink || !p.vend.is_online || p.machine.doorBlocked
      const why = noLink ? 'Not linked to a CityBox device' : !p.vend.is_online ? 'Offline — CityBox cannot open the door' : p.machine.doorBlocked ? 'CityBox reports the machine is ' + p.machine.stateLabel.toLowerCase() : 'Unlock the cabinet for restocking (ops open)'
      return h('div', { class: 'flex flex-wrap gap-1' }, [
        h('button', { type: 'button', title: why, disabled: busy || blocked, onClick: (e) => { e.preventDefault(); openDoor() },
          class: 'inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed' },
          [h(LockOpenIcon, { class: 'w-3.5 h-3.5' }), 'Open Door']),
        h('button', { type: 'button', title: 'Refresh status, planogram and live stock from CityBox now', disabled: busy || noLink, onClick: (e) => { e.preventDefault(); pull() },
          class: 'inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 hover:bg-indigo-100 disabled:opacity-50' },
          [h(ArrowPathIcon, { class: 'w-3.5 h-3.5' }), 'Pull']),
      ])
    }
  },
})
</script>
