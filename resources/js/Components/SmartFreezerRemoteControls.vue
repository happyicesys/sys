<template>
  <!--
    Setting/Edit > Smart Freezer > Remote controls (FreezerControlController).

    Every button sends ONE signed FREEZERCTL command over MQTT; the freezer answers with its verdict and a
    fresh status snapshot. The panel polls the JSON endpoint every 2 s while a command is waiting and every
    20 s otherwise (paused while the tab is hidden), so the result appears without a page reload.
  -->
  <div class="sm:col-span-6">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <label class="text-sm font-medium text-gray-700">Remote controls</label>
      <div class="flex items-center gap-2 text-xs">
        <span class="inline-flex items-center rounded-full px-2 py-0.5 font-medium"
              :class="data.is_online ? 'bg-green-100 text-green-800' : 'bg-gray-200 text-gray-700'">
          {{ data.is_online ? 'online' : 'offline' }}
        </span>
        <span v-if="data.last_seen_at" class="text-gray-500">last seen {{ ago(data.last_seen_at) }}</span>
        <Button type="button" class="text-white"
                :class="canSend ? 'bg-sky-700 hover:bg-sky-800' : 'bg-gray-300 cursor-not-allowed'"
                :disabled="!canSend" @click.prevent="send('status')">
          <ArrowPathIcon class="h-4 w-4 mr-1" :class="data.pending ? 'animate-spin' : ''" />
          Sync now
        </Button>
      </div>
    </div>

    <div class="mt-1 rounded-md border border-gray-200 bg-white p-3 text-sm space-y-3">
      <p v-if="!loaded" class="text-gray-500">Loading…</p>
      <p v-else-if="!data.supported" class="text-amber-800 text-xs">
        This machine's app (versionCode {{ data.apk_version_code ?? 'unknown' }}) is too old for remote controls.
      </p>

      <template v-if="loaded">
        <!-- Status -->
        <div>
          <div class="flex flex-wrap items-baseline justify-between gap-2">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Current status</span>
            <span class="text-xs" :class="statusStale ? 'text-amber-700' : 'text-gray-500'">
              <template v-if="!data.status_at">No status yet — press Sync now.</template>
              <template v-else>Reported by the machine {{ formatTime(data.status_at) }} ({{ ago(data.status_at) }})</template>
            </span>
          </div>

          <div v-if="s" class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-6">
            <div class="col-span-2 rounded-md border px-3 py-2"
                 :class="t.available && t.celsius !== null && t.sensorOk ? 'border-sky-200 bg-sky-50' : 'border-gray-200 bg-gray-50'">
              <div class="text-xs text-gray-500">Chamber</div>
              <div class="text-2xl font-semibold text-gray-900">
                {{ t.available && t.celsius !== null && t.celsius !== undefined ? Number(t.celsius).toFixed(1) + ' °C' : '—' }}
              </div>
              <div class="text-xs text-gray-500">
                <template v-if="!t.available">Thermostat not reported by the host plugin</template>
                <template v-else-if="!t.connected">Thermostat port closed</template>
                <template v-else-if="!t.communicating">Thermostat not answering</template>
                <template v-else-if="!t.sensorOk">Probe fault</template>
                <template v-else>{{ t.model || 'Thermostat' }} · probe OK</template>
              </div>
            </div>
            <StatusCell label="Compressor" :value="onOff(t.compressorOn)" :tone="t.compressorOn === true ? 'on' : 'neutral'" />
            <StatusCell label="Cooling demand" :value="onOff(t.coolingDemand)" />
            <StatusCell label="Defrost" :value="onOff(t.defrosting)" :tone="t.defrosting ? 'warn' : 'neutral'" />
            <StatusCell label="Alarm" :value="alarmText" :tone="alarmText === 'none' ? 'ok' : alarmText === '—' ? 'neutral' : 'bad'" />
            <StatusCell label="Lock" :value="s.door?.lockState ?? '—'" :tone="s.door?.lockState === 'locked' ? 'ok' : s.door?.lockState === 'unlocked' ? 'warn' : 'neutral'" />
            <StatusCell label="Door" :value="s.door?.doorState ?? '—'" :tone="s.door?.doorState === 'closed' ? 'ok' : s.door?.doorState === 'opened' ? 'warn' : 'neutral'" />
            <StatusCell label="Lock link" :value="s.door?.lockOnlineState ?? '—'" :tone="s.door?.lockOnlineState === 'online' ? 'ok' : 'neutral'" />
            <StatusCell label="Cabinet fan" :value="onOff(s.fanOn)" />
            <StatusCell label="Light" :value="s.lightState || 'not reported'" />
            <StatusCell label="Volume" :value="s.volume ?? '—'" />
            <StatusCell label="Host bridge" :value="s.bridge ? (s.bridge.bound ? (s.bridge.hostReady ? 'ready' : 'bound, not ready') : 'not bound') : '—'"
                        :tone="s.bridge?.hostReady ? 'ok' : 'bad'" />
            <StatusCell label="Sale in progress" :value="s.saleInProgress ? 'yes' : 'no'" :tone="s.saleInProgress ? 'warn' : 'neutral'" />
            <StatusCell label="App" :value="s.apk ? 'v' + s.apk.versionName + ' (' + s.apk.versionCode + ')' : '—'" />
          </div>

          <p v-if="s?.telemetry" class="mt-2 text-xs text-gray-500">
            Machine reports on its own: temperature &amp; door every {{ secs(s.telemetry.venderSeconds) }},
            presence every {{ secs(s.telemetry.pSeconds) }}, hardware status every {{ secs(s.telemetry.statusSeconds) }}
            (and on change), MQTT heartbeat every {{ secs(s.telemetry.heartbeatSeconds) }}; temperature excursions are checked every
            {{ secs(s.telemetry.excursionSampleSeconds) }}.
          </p>
        </div>

        <!-- Controls -->
        <div v-if="data.can_control && data.supported" class="border-t pt-3">
          <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Controls</span>
          <p v-if="data.pending" class="text-xs text-sky-700">Waiting for the machine to answer the last command…</p>
          <p v-else-if="!data.is_online" class="text-xs text-amber-700">The machine looks offline — commands expire after a minute if it does not answer.</p>

          <div class="mt-2 grid grid-cols-1 gap-3 md:grid-cols-2">
            <div class="rounded-md border border-gray-200 p-2">
              <div class="text-xs text-gray-500 mb-1">Temperature controller setpoint (whole °C, {{ data.setpoint.min }} to {{ data.setpoint.max }})</div>
              <div class="flex items-center gap-2">
                <Button type="button" class="bg-gray-100 hover:bg-gray-200 text-gray-800" :disabled="!canSend" @click.prevent="setpoint = Math.max(data.setpoint.min, setpoint - 1)">−</Button>
                <span class="w-20 text-center text-lg font-semibold">{{ setpoint }} °C</span>
                <Button type="button" class="bg-gray-100 hover:bg-gray-200 text-gray-800" :disabled="!canSend" @click.prevent="setpoint = Math.min(data.setpoint.max, setpoint + 1)">+</Button>
                <Button type="button" class="text-white" :class="canSend ? 'bg-sky-700 hover:bg-sky-800' : 'bg-gray-300'" :disabled="!canSend"
                        @click.prevent="ask('setpoint', { celsius: setpoint }, 'Set the temperature controller to ' + setpoint + ' °C?', 'The cabinet will cool to this target until someone changes it. The machine cannot report its current setpoint, so check the chamber temperature afterwards.')">
                  Set
                </Button>
              </div>
            </div>

            <ToggleRow label="Compressor" :disabled="!canSend" @on="ask('compressor', { on: true }, 'Switch the compressor on?', 'Overrides the controller until it switches again.')" @off="ask('compressor', { on: false }, 'Switch the compressor off?', 'The chamber will warm until the controller switches it back on.')" />
            <ToggleRow label="Cabinet fan" :disabled="!canSend" @on="send('fan', { on: true })" @off="send('fan', { on: false })" />
            <ToggleRow label="Light" :disabled="!canSend" @on="send('light', { on: true })" @off="send('light', { on: false })" />

            <div class="rounded-md border border-gray-200 p-2 flex items-center justify-between gap-2">
              <span class="text-sm text-gray-700">Music volume</span>
              <span class="flex gap-1">
                <Button type="button" class="bg-gray-100 hover:bg-gray-200 text-gray-800" :disabled="!canSend" @click.prevent="ask('volume', { step: 'mute' }, 'Mute the machine?', 'Mute persists across restarts until someone turns it back up.')">Mute</Button>
                <Button type="button" class="bg-gray-100 hover:bg-gray-200 text-gray-800" :disabled="!canSend" @click.prevent="send('volume', { step: 'down' })">Down</Button>
                <Button type="button" class="bg-gray-100 hover:bg-gray-200 text-gray-800" :disabled="!canSend" @click.prevent="send('volume', { step: 'up' })">Up</Button>
              </span>
            </div>

            <div v-if="data.can_door" class="rounded-md border border-red-200 bg-red-50 p-2 flex items-center justify-between gap-2">
              <span class="text-sm text-gray-700">Door</span>
              <span class="flex gap-1">
                <Button type="button" class="text-white" :class="canSend ? 'bg-gray-700 hover:bg-gray-800' : 'bg-gray-300'" :disabled="!canSend" @click.prevent="send('lock')">
                  <LockClosedIcon class="h-4 w-4 mr-1" /> Lock
                </Button>
                <Button type="button" class="text-white" :class="canSend ? 'bg-red-600 hover:bg-red-700' : 'bg-gray-300'" :disabled="!canSend"
                        @click.prevent="ask('unlock', {}, 'Unlock the door remotely?', 'This opens the lock with no order, no video and no AI check — stock taken is not recorded. It is logged with your name. The machine refuses while a customer sale is in progress.', true)">
                  <LockOpenIcon class="h-4 w-4 mr-1" /> Unlock
                </Button>
              </span>
            </div>
          </div>
        </div>

        <!-- Command log -->
        <div v-if="data.commands.length" class="border-t pt-3">
          <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Recent commands</span>
          <div class="mt-1 overflow-x-auto">
            <table class="min-w-full text-xs">
              <thead class="text-left text-gray-500">
                <tr><th class="py-1 pr-3">Sent</th><th class="py-1 pr-3">Command</th><th class="py-1 pr-3">By</th><th class="py-1 pr-3">Result</th><th class="py-1">Machine said</th></tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr v-for="c in data.commands" :key="c.id">
                  <td class="py-1 pr-3 whitespace-nowrap text-gray-600">{{ formatTime(c.requested_at) }}</td>
                  <td class="py-1 pr-3 whitespace-nowrap">{{ describe(c) }}</td>
                  <td class="py-1 pr-3 whitespace-nowrap text-gray-600">{{ c.requested_by || '—' }}</td>
                  <td class="py-1 pr-3 whitespace-nowrap">
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 font-medium" :class="badge(c.status)">
                      {{ label(c.status) }}
                    </span>
                    <span v-if="c.responded_at" class="ml-1 text-gray-400">{{ answeredIn(c) }}</span>
                  </td>
                  <td class="py-1 text-gray-700">{{ c.message || '' }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </div>

    <Teleport to="body">
      <Modal :open="!!confirm" @modalClose="confirm = null">
        <template #header><span class="font-semibold text-black">{{ confirm?.title }}</span></template>
        <template #default>
          <p class="text-sm text-gray-700">{{ confirm?.detail }}</p>
          <div class="mt-4 flex justify-end space-x-2">
            <Button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-800" @click.prevent="confirm = null">Cancel</Button>
            <Button type="button" class="text-white" :class="confirm?.danger ? 'bg-red-600 hover:bg-red-700' : 'bg-sky-700 hover:bg-sky-800'" @click.prevent="confirmSend">Yes, send</Button>
          </div>
        </template>
      </Modal>
    </Teleport>
  </div>
</template>

<script setup>
import Button from '@/Components/Button.vue'
import Modal from '@/Components/Modal.vue'
import { ArrowPathIcon, LockClosedIcon, LockOpenIcon } from '@heroicons/vue/20/solid'
import { computed, defineComponent, h, onMounted, onUnmounted, ref } from 'vue'
import moment from 'moment'
import { useToast } from 'vue-toastification'

const props = defineProps({
  vendId: { type: Number, required: true },
})

const toast = useToast()
const loaded = ref(false)
const sending = ref(false)
const confirm = ref(null)
const setpoint = ref(-18)
const now = ref(Date.now())
const data = ref({ commands: [], setpoint: { min: -30, max: -5 }, pending: false, supported: false })

const s = computed(() => data.value.status || null)
const t = computed(() => s.value?.thermostat || { available: false })
const canSend = computed(() => data.value.can_control && data.value.supported && !data.value.pending && !sending.value)
const statusStale = computed(() => !data.value.status_at || now.value - Date.parse(data.value.status_at) > 10 * 60 * 1000)
const alarmText = computed(() => {
  if (!t.value.available) return '—'
  if (t.value.highTempAlarm) return 'HIGH temp'
  if (t.value.lowTempAlarm) return 'LOW temp'
  return 'none'
})

const OP_LABELS = { status: 'Sync status', lock: 'Lock door', unlock: 'Unlock door', fan: 'Cabinet fan', light: 'Light', compressor: 'Compressor', setpoint: 'Setpoint', volume: 'Volume' }
const RESULT_LABELS = { pending: 'waiting', ok: 'done', refused: 'refused', indeterminate: 'no answer from host', unsupported: 'not supported', busy: 'busy (sale)', invalid: 'invalid', expired: 'expired', duplicate: 'duplicate', error: 'error', timeout: 'no answer' }

function describe(c) {
  const a = c.args || {}
  if ('on' in a) return OP_LABELS[c.op] + ' ' + (a.on ? 'on' : 'off')
  if ('celsius' in a) return OP_LABELS[c.op] + ' ' + a.celsius + ' °C'
  if ('step' in a) return OP_LABELS[c.op] + ' ' + a.step
  return OP_LABELS[c.op] || c.op
}
function label(status) { return RESULT_LABELS[status] || status }
function badge(status) {
  if (status === 'ok') return 'bg-green-100 text-green-800'
  if (status === 'pending') return 'bg-sky-100 text-sky-800 animate-pulse'
  if (['timeout', 'indeterminate', 'busy'].includes(status)) return 'bg-amber-100 text-amber-800'
  if (status === 'duplicate') return 'bg-gray-100 text-gray-700'
  return 'bg-red-100 text-red-800'
}
function onOff(v) { return v === true ? 'on' : v === false ? 'off' : '—' }
function secs(n) { return !n ? '—' : n % 60 === 0 ? (n / 60) + ' min' : n + ' s' }
function formatTime(iso) { return iso ? moment(iso).format('DD MMM HH:mm:ss') : '' }
function ago(iso) { return iso ? moment(iso).from(moment(now.value)) : '' }
function answeredIn(c) {
  const ms = Date.parse(c.responded_at) - Date.parse(c.requested_at)
  return Number.isFinite(ms) && ms >= 0 ? 'in ' + Math.max(1, Math.round(ms / 1000)) + ' s' : ''
}

let timer = null
async function load() {
  try {
    const res = await axios.get('/vends/' + props.vendId + '/freezer-controls')
    const wasPending = data.value.pending
    data.value = res.data
    loaded.value = true
    if (wasPending && !res.data.pending) {
      const last = res.data.commands[0]
      if (last && last.op !== 'status') {
        last.status === 'ok' ? toast.success(describe(last) + ': done') : toast.warning(describe(last) + ': ' + label(last.status))
      }
    }
  } catch (e) {
    loaded.value = true
  }
  schedule()
}
function schedule() {
  clearTimeout(timer)
  now.value = Date.now()
  if (document.hidden) return
  timer = setTimeout(load, data.value.pending ? 2000 : 20000)
}
function onVisibility() { if (!document.hidden) load() }

function ask(op, args, title, detail, danger = false) {
  confirm.value = { op, args, title, detail, danger }
}
function confirmSend() {
  const c = confirm.value
  confirm.value = null
  if (c) send(c.op, c.args)
}
async function send(op, args = {}) {
  if (!canSend.value) return
  sending.value = true
  try {
    await axios.post('/vends/' + props.vendId + '/freezer-controls', { op, args })
    await load()
  } catch (e) {
    toast.error(e.response?.data?.message || 'Could not send the command.')
  } finally {
    sending.value = false
  }
}

onMounted(() => {
  load()
  document.addEventListener('visibilitychange', onVisibility)
})
onUnmounted(() => {
  clearTimeout(timer)
  document.removeEventListener('visibilitychange', onVisibility)
})

const TONES = { ok: 'text-green-700', on: 'text-sky-700', warn: 'text-amber-700', bad: 'text-red-700', neutral: 'text-gray-900' }
const StatusCell = defineComponent({
  props: { label: String, value: [String, Number], tone: { type: String, default: 'neutral' } },
  setup: (p) => () => h('div', { class: 'rounded-md border border-gray-200 px-3 py-2' }, [
    h('div', { class: 'text-xs text-gray-500' }, p.label),
    h('div', { class: 'text-sm font-semibold ' + (TONES[p.tone] || TONES.neutral) }, String(p.value ?? '—')),
  ]),
})
const ToggleRow = defineComponent({
  props: { label: String, disabled: Boolean },
  emits: ['on', 'off'],
  setup: (p, { emit }) => () => h('div', { class: 'rounded-md border border-gray-200 p-2 flex items-center justify-between gap-2' }, [
    h('span', { class: 'text-sm text-gray-700' }, p.label),
    h('span', { class: 'flex gap-1' }, [
      h('button', { type: 'button', disabled: p.disabled, class: 'inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md shadow-sm ' + (p.disabled ? 'bg-gray-100 text-gray-400' : 'bg-gray-100 hover:bg-gray-200 text-gray-800'), onClick: () => emit('on') }, 'On'),
      h('button', { type: 'button', disabled: p.disabled, class: 'inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md shadow-sm ' + (p.disabled ? 'bg-gray-100 text-gray-400' : 'bg-gray-100 hover:bg-gray-200 text-gray-800'), onClick: () => emit('off') }, 'Off'),
    ]),
  ]),
})
</script>
