<template>
  <!--
    One-cell summary of a CityBox Smart Chiller for the machine index pages
    (Vend/Index, Vend/CustomerIndex). A chiller has no VMC, APK, temperature
    probe, coin float, modem or mark1 sales, so the vending-machine columns
    are meaningless for it; this cell replaces them with what IS true of a
    chiller: identity, CityBox's own status (their heartbeat + ops status,
    from the last poll on the row), the mirrored planogram stock, the ops
    jobs, and the two actions that work (Open Door, Pull).
  -->
  <div class="flex flex-wrap gap-x-6 gap-y-3 items-start text-[13px]">

    <!-- Identity -->
    <div class="flex flex-col space-y-1 min-w-[160px]">
      <div class="flex items-center gap-2">
        <Link :href="settingsHref" class="font-semibold hover:underline" :class="vend.is_active || vend.is_testing ? 'text-blue-600' : 'text-gray-400'" v-tooltip="'Open this machine\'s settings'">
          {{ vend.code }}
        </Link>
        <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[11px] font-medium bg-indigo-50 text-indigo-700 border border-indigo-200">Smart Chiller · CityBox</span>
        <span v-if="vend.is_testing" class="inline-flex rounded px-1.5 py-0.5 text-[11px] bg-yellow-100 text-yellow-800 border border-yellow-300">Test</span>
        <span v-if="!vend.is_active" class="inline-flex rounded px-1.5 py-0.5 text-[11px] bg-gray-100 text-gray-600 border border-gray-300">Inactive</span>
      </div>
      <a v-if="vend.customer_id" class="text-blue-700 hover:underline" target="_blank" :href="'/customers/' + vend.customer_id + '/edit'">{{ vend.customer_name }}</a>
      <span v-else class="text-red-600">No site bound</span>
      <div class="text-xs text-gray-600">
        <span v-if="status.name" class="font-medium text-gray-800">{{ status.name }}</span>
        <span v-if="status.name"> · </span>{{ status.model }}
      </div>
      <div class="font-mono text-[11px] text-gray-500">{{ vend.citybox_equipment_id || '— not linked —' }}</div>
    </div>

    <!-- CityBox status: THEIR view (ops status, heartbeat), from the last poll -->
    <div class="flex flex-col space-y-1 min-w-[170px]">
      <span class="text-[11px] font-semibold text-gray-900">CityBox status</span>
      <div class="flex items-center gap-2">
        <span class="inline-flex rounded px-1.5 py-0.5 text-[11px] font-medium border"
              :class="status.isRunning ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200'">
          {{ status.opsLabel }}
        </span>
        <span class="inline-flex rounded px-1.5 py-0.5 text-[11px] font-medium border"
              :class="vend.is_online ? 'bg-green-50 text-green-700 border-green-200' : 'bg-gray-100 text-gray-700 border-gray-300'">
          {{ vend.is_online ? 'online' : 'offline' }}
        </span>
      </div>
      <span v-if="!vend.is_online && status.offlineSince" class="text-xs text-gray-600">since {{ status.offlineSince }}</span>
      <span class="text-xs" :class="status.isStale ? 'text-amber-700 font-medium' : 'text-gray-500'">
        <template v-if="!status.syncedAt">Not polled yet</template>
        <template v-else-if="status.isStale">Stale — last sync {{ status.syncedAgo }}</template>
        <template v-else>Synced {{ status.syncedAgo }}</template>
      </span>
      <span v-if="status.isRetired" class="text-xs text-red-700">Removed by CityBox (已撤机)</span>
    </div>

    <!-- Stock: the mirrored planogram (vend_channels), qty = their live count -->
    <div class="flex flex-col space-y-1 min-w-[190px] cursor-pointer hover:bg-gray-100 rounded p-1 -m-1" @click="$emit('overview', vend)" v-tooltip="'View channel status'">
      <span class="text-[11px] font-semibold text-gray-900">Stock (from CityBox)</span>
      <template v-if="channels.length">
        <div class="flex items-center gap-3">
          <span :class="stock.qty === 0 ? 'text-red-600 font-semibold' : 'text-gray-800'">{{ stock.qty }} / {{ stock.capacity }}</span>
          <span class="text-xs text-gray-600">{{ stock.percent }}% full</span>
          <span class="text-xs" :class="stock.emptySkus ? 'text-red-600' : 'text-gray-600'">{{ stock.inStockSkus }}/{{ channels.length }} SKUs in stock</span>
        </div>
        <div class="flex flex-wrap gap-1">
          <span v-for="ch in channels.slice(0, 8)" :key="ch.code"
                class="inline-flex items-center rounded px-1.5 py-0.5 text-[11px] border"
                :class="ch.qty === 0 ? 'bg-red-50 text-red-700 border-red-200' : ch.qty <= 2 ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-green-50 text-green-700 border-green-200'"
                v-tooltip="ch.product ? ch.product.name : ''">
            #{{ ch.code }} {{ ch.qty }}/{{ ch.capacity }}
          </span>
          <span v-if="channels.length > 8" class="text-[11px] text-gray-500">+{{ channels.length - 8 }} more</span>
        </div>
      </template>
      <span v-else class="text-xs text-gray-500">No planogram yet — nothing in CityBox's Pre-Stock Setup, or never polled. Pull to re-mirror.</span>
    </div>

    <!-- Machine parameters crawled from their API: live session state (get_device_status_new),
         per-SKU price + promo price and layer use (device_product), last poll health, last
         ops door-open (our log, mirrored on the row). -->
    <div class="flex flex-col space-y-1 min-w-[210px]">
      <span class="text-[11px] font-semibold text-gray-900">Machine (from CityBox)</span>
      <div class="flex items-center gap-2 flex-wrap">
        <span class="inline-flex rounded px-1.5 py-0.5 text-[11px] font-medium border" :class="machine.stateClass" v-tooltip="machine.stateAt ? 'Session state at ' + machine.stateAt : 'Session state not polled yet'">
          {{ machine.stateLabel }}
        </span>
        <span v-if="machine.layersUsed.length" class="text-xs text-gray-600" v-tooltip="'Layers holding a SKU in the Pre-Stock Setup (5-layer cabinet)'">
          layers {{ machine.layersUsed.join(', ') }} of {{ machine.layerCount }}
        </span>
      </div>
      <div v-if="machine.prices.length" class="flex flex-col">
        <span class="text-[11px] text-gray-500">Prices (their portal)</span>
        <span v-for="p in machine.prices.slice(0, 6)" :key="p.id" class="text-xs text-gray-700 truncate max-w-[220px]" v-tooltip="p.name">
          <span class="font-medium" :class="p.promo ? 'text-rose-700' : ''">{{ money(p.active) }}</span>
          <span v-if="p.promo" class="line-through text-gray-400 ml-1">{{ money(p.price) }}</span>
          <span class="text-gray-500"> · L{{ p.layer }} · {{ p.name }}</span>
        </span>
        <span v-if="machine.prices.length > 6" class="text-[11px] text-gray-500">+{{ machine.prices.length - 6 }} more</span>
      </div>
      <span v-if="machine.lastOpen" class="text-xs text-gray-600" v-tooltip="'Last ops door-open through mark1 (msg ' + (machine.lastOpen.msg_id || '—') + ')'">
        Last door open: {{ machine.lastOpen.at }}<span v-if="machine.lastOpen.source"> · {{ sourceLabel(machine.lastOpen.source) }}</span>
      </span>
      <span v-if="machine.poll" class="text-xs" :class="machine.poll.ok ? 'text-gray-500' : 'text-amber-700'" v-tooltip="machine.poll.error || ''">
        Last poll {{ machine.poll.at }} · {{ machine.poll.ok ? machine.poll.products_seen + ' SKU, ' + machine.poll.duration_ms + ' ms' : 'failed: ' + (machine.poll.error || 'error') }}
      </span>
      <span v-if="status.recoveredAt" class="text-xs text-gray-500">Last back online: {{ status.recoveredAt }}</span>
    </div>

    <!-- Ops jobs (same fields the vending row shows) -->
    <div class="flex flex-col space-y-1 min-w-[150px]" v-if="vend.lastOpsJobItem || vend.nextOpsJobItem">
      <template v-if="vend.lastOpsJobItem">
        <span class="text-[11px] font-semibold text-gray-900">Last job</span>
        <a :href="'/ops-jobs/items/' + vend.lastOpsJobItem.id + '/edit'" class="text-blue-700 hover:underline text-xs">{{ vend.lastOpsJobItem.ref_id }}</a>
        <span class="text-xs text-gray-600" v-if="vend.lastOpsJobItem.opsJob">
          {{ vend.lastOpsJobItem.opsJob.date_formatted }}<span v-if="vend.lastOpsJobItem.opsJob.date_diff_human"> · {{ vend.lastOpsJobItem.opsJob.date_diff_human }}</span>
          <span v-if="vend.lastOpsJobItem.opsJob.deliveredBy"> · {{ vend.lastOpsJobItem.opsJob.deliveredBy.name }}</span>
        </span>
      </template>
      <template v-if="vend.nextOpsJobItem">
        <span class="text-[11px] font-semibold text-gray-900">Upcoming job</span>
        <a :href="'/ops-jobs/items/' + vend.nextOpsJobItem.id + '/edit'" class="text-blue-700 hover:underline text-xs">{{ vend.nextOpsJobItem.ref_id }}</a>
        <span class="text-xs text-gray-600" v-if="vend.nextOpsJobItem.opsJob">
          {{ vend.nextOpsJobItem.opsJob.date_formatted }}<span v-if="vend.nextOpsJobItem.opsJob.date_diff_human"> · {{ vend.nextOpsJobItem.opsJob.date_diff_human }}</span>
        </span>
      </template>
    </div>

    <!-- Actions: the only two that exist for a chiller -->
    <div class="flex flex-col space-y-1 min-w-[130px]">
      <span class="text-[11px] font-semibold text-gray-900">Actions</span>
      <div class="flex flex-wrap gap-1">
        <button type="button"
                class="inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium text-white bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                :disabled="busy || !vend.citybox_equipment_id || !vend.is_online || machine.doorBlocked"
                v-tooltip="!vend.citybox_equipment_id ? 'Not linked to a CityBox device' : !vend.is_online ? 'Offline — CityBox cannot open the door' : machine.doorBlocked ? 'CityBox reports the machine is ' + machine.stateLabel.toLowerCase() + ' — try again when idle' : 'Unlock the cabinet for restocking (ops open, not a customer session)'"
                @click.prevent="openDoor">
          <LockOpenIcon class="w-3.5 h-3.5" /> Open Door
        </button>
        <button type="button"
                class="inline-flex items-center gap-1 rounded px-2 py-1 text-xs font-medium text-indigo-700 bg-indigo-50 border border-indigo-200 hover:bg-indigo-100 disabled:opacity-50"
                :disabled="busy || !vend.citybox_equipment_id"
                v-tooltip="'Refresh status, planogram and live stock from CityBox now'"
                @click.prevent="pull">
          <ArrowPathIcon class="w-3.5 h-3.5" :class="busy ? 'animate-spin' : ''" /> Pull
        </button>
      </div>
      <span class="text-[11px] text-gray-500">No VMC/APK controls — CityBox hardware.</span>
    </div>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import { ArrowPathIcon, LockOpenIcon } from '@heroicons/vue/20/solid'
import { useToast } from 'vue-toastification'

const props = defineProps({
  vend: { type: Object, required: true },
})
defineEmits(['overview'])

const toast = useToast()
const busy = ref(false)

const settingsHref = computed(() => '/settings/vend/' + (props.vend.vend_id ?? props.vend.id) + '/update')

// Mirrors App\Enums\Citybox\DeviceOpsStatus / DeviceType labels. Kept tiny on purpose;
// the server-side value object (ChillerStatus) is the source of these words.
const OPS_LABELS = { 0: 'Banned (禁运)', 1: 'Running (启运)', 98: 'Being removed (撤机中)', 99: 'Removed (已撤机)' }
const MODELS = { 'visual-2': 'CityBox F5 (visual-2)', 'visual-8': 'CityBox C5 (visual-8)' }
const STALE_AFTER_MS = 5 * 60 * 1000 // poll is every minute; same threshold as ChillerStatus

const status = computed(() => {
  const j = props.vend.citybox_status_json || {}
  const ops = j.equipment_status === undefined || j.equipment_status === null ? null : Number(j.equipment_status)
  const syncedAt = props.vend.citybox_synced_at ? new Date(props.vend.citybox_synced_at) : null
  const age = syncedAt ? Date.now() - syncedAt.getTime() : null
  return {
    name: j.name || null,
    model: MODELS[j.device_type] || 'CityBox (unknown type)',
    opsLabel: (ops !== null && OPS_LABELS[ops]) || j.equipment_status_str || 'Unknown',
    isRunning: ops === 1,
    isRetired: ops === 99,
    offlineSince: j.heartbeat_last_offline || null,
    recoveredAt: j.heartbeat_last_recovery || null,
    syncedAt,
    isStale: age === null || age > STALE_AFTER_MS,
    syncedAgo: age === null ? '' : humanAgo(age),
  }
})

const channels = computed(() => {
  const list = Array.isArray(props.vend.vendChannelsJson) ? props.vend.vendChannelsJson : []
  return list
    .filter(c => c && c.is_active !== 0 && c.is_active !== false)
    .map(c => ({ code: c.code, qty: Number(c.qty ?? 0), capacity: Number(c.capacity ?? 0), product: c.product || null }))
    .sort((a, b) => Number(a.code) - Number(b.code))
})

const stock = computed(() => {
  const qty = channels.value.reduce((s, c) => s + c.qty, 0)
  const capacity = channels.value.reduce((s, c) => s + c.capacity, 0)
  const inStockSkus = channels.value.filter(c => c.qty > 0).length
  return {
    qty, capacity,
    percent: capacity ? Math.round((qty / capacity) * 100) : 0,
    inStockSkus,
    emptySkus: channels.value.length - inStockSkus,
  }
})

// get_device_status_new codes → ops wording (mirrors App\Enums\Citybox\DeviceState::label()).
const STATE_LABELS = { FREE: 'Idle', OPENING: 'Door open', BUSY: 'In use (customer session)', MAINTENANCE: 'Maintenance', NOT_FOUND: 'Unreachable', OTHER: 'Unknown state' }
const STATE_CLASSES = {
  FREE: 'bg-green-50 text-green-700 border-green-200',
  OPENING: 'bg-indigo-50 text-indigo-700 border-indigo-200',
  BUSY: 'bg-blue-50 text-blue-700 border-blue-200',
  MAINTENANCE: 'bg-amber-50 text-amber-800 border-amber-300',
  NOT_FOUND: 'bg-gray-100 text-gray-600 border-gray-300',
}
const SOURCE_LABELS = { ops_job_page: 'ops job', ops_job_item_page: 'ops job item', vend_settings: 'machine settings', api: 'API' }
function sourceLabel(src) { return SOURCE_LABELS[src] || src }

const machine = computed(() => {
  const j = props.vend.citybox_status_json || {}
  const state = j.device_state ? String(j.device_state).toUpperCase() : null
  const stock = j.stock && typeof j.stock === 'object' ? Object.values(j.stock) : []
  const layersUsed = [...new Set(stock.map(p => Number(p.layer)).filter(n => n > 0))].sort((a, b) => a - b)
  const prices = stock
    .map(p => ({ id: p.product_id, name: p.name, layer: p.layer, price: Number(p.price ?? 0), active: Number(p.active_price ?? p.price ?? 0) }))
    .map(p => ({ ...p, promo: p.active !== p.price }))
    .sort((a, b) => Number(a.layer) - Number(b.layer) || String(a.name).localeCompare(String(b.name)))
  return {
    state,
    stateLabel: state ? (STATE_LABELS[state] || state) : 'Not polled',
    stateClass: (state && STATE_CLASSES[state]) || 'bg-gray-50 text-gray-500 border-gray-200',
    stateAt: j.device_state_at || null,
    // Only a KNOWN busy state blocks (door open / customer session / maintenance). NOT_FOUND
    // does not: prod 2026-09-02 shows units online per box_list yet NOT_FOUND on the status
    // call (C6001/C6002, no planogram yet) — their open-door API is the arbiter there.
    doorBlocked: state !== null && ['OPENING', 'BUSY', 'MAINTENANCE'].includes(state),
    layersUsed,
    layerCount: 5,
    prices,
    lastOpen: j.last_ops_open && j.last_ops_open.at ? j.last_ops_open : null,
    poll: j.poll && j.poll.at ? j.poll : null,
  }
})

// Their prices arrive as integer cents (StockPollService snapshot).
function money(cents) { return 'S$' + (Number(cents || 0) / 100).toFixed(2) }

function humanAgo(ms) {
  const m = Math.round(ms / 60000)
  if (m < 1) return 'just now'
  if (m < 60) return m + ' min ago'
  const h = Math.round(m / 60)
  if (h < 48) return h + ' h ago'
  return Math.round(h / 24) + ' d ago'
}

// Same endpoints and behaviour as the buttons on Setting/Edit.
function openDoor() {
  if (!confirm('Open chiller ' + props.vend.code + ' (' + (status.value.name || props.vend.citybox_equipment_id) + ') for restocking now?')) return
  busy.value = true
  router.post('/vends/' + (props.vend.vend_id ?? props.vend.id) + '/citybox-open-door', {}, {
    preserveScroll: true,
    onSuccess: () => toast.success('Door opened', { timeout: 3000 }),
    onError: (errors) => toast.error(errors.citybox || 'Door open failed', { timeout: 6000 }),
    onFinish: () => { busy.value = false },
  })
}

function pull() {
  busy.value = true
  router.post('/vends/' + (props.vend.vend_id ?? props.vend.id) + '/citybox-pull', {}, {
    preserveScroll: true,
    onSuccess: () => toast.success('Pulled from CityBox', { timeout: 3000 }),
    onError: (errors) => toast.error(errors.citybox || 'Pull failed', { timeout: 6000 }),
    onFinish: () => { busy.value = false },
  })
}
</script>
