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

      <p v-if="loaded && data.supported && (t.compressorRemoteMode === false || t.fanRemoteMode === false)" class="text-amber-800 text-xs">
        <template v-if="t.compressorRemoteMode === false">The temperature controller is running the compressor itself, so Compressor on/off is ignored until you press Remote.</template>
        <template v-if="t.compressorRemoteMode === false && t.fanRemoteMode === false"><br></template>
        <template v-if="t.fanRemoteMode === false">The fan follows the door switch on the controller, so Cabinet fan on/off is ignored. Only Zijia's portal can change that today.</template>
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
            <StatusCell label="Compressor control" :value="modeText(t.compressorRemoteMode)" :tone="t.compressorRemoteMode === true ? 'warn' : 'neutral'" />
            <StatusCell label="Cooling demand" :value="onOff(t.coolingDemand)" />
            <StatusCell label="Defrost" :value="onOff(t.defrosting)" :tone="t.defrosting ? 'warn' : 'neutral'" />
            <StatusCell label="Alarm" :value="alarmText" :tone="alarmText === 'none' ? 'ok' : alarmText === '—' ? 'neutral' : 'bad'" />
            <StatusCell label="Lock" :value="s.door?.lockState ?? '—'" :tone="s.door?.lockState === 'locked' ? 'ok' : s.door?.lockState === 'unlocked' ? 'warn' : 'neutral'" />
            <StatusCell label="Door" :value="s.door?.doorState ?? '—'" :tone="s.door?.doorState === 'closed' ? 'ok' : s.door?.doorState === 'opened' ? 'warn' : 'neutral'" />
            <StatusCell label="Lock link" :value="s.door?.lockOnlineState ?? '—'" :tone="s.door?.lockOnlineState === 'online' ? 'ok' : 'neutral'" />
            <StatusCell label="Cabinet fan" :value="onOff(s.fanOn)" />
            <StatusCell label="Fan control" :value="modeText(t.fanRemoteMode)" :tone="t.fanRemoteMode === true ? 'warn' : 'neutral'" />
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
          <!--
            Every row states WHAT IT IS NOW before the buttons that change it (Brian, 2026-09-16):
            pressing On/Off blind is how a cabinet ends up with the compressor left off. The values
            are the machine's last status snapshot, so the row also says how old that is.
          -->
          <div class="flex flex-wrap items-baseline justify-between gap-2">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Controls</span>
            <span class="text-xs" :class="statusStale ? 'text-amber-700' : 'text-gray-500'">
              <template v-if="!data.status_at">Values unknown until the machine reports — press Sync now.</template>
              <template v-else>Values as at {{ formatTime(data.status_at) }} ({{ ago(data.status_at) }})</template>
            </span>
          </div>
          <p v-if="data.pending" class="text-xs text-sky-700">Waiting for the machine to answer the last command…</p>
          <p v-else-if="!data.is_online" class="text-xs text-amber-700">The machine looks offline — commands expire after a minute if it does not answer.</p>

          <div class="mt-2 grid grid-cols-1 gap-3 md:grid-cols-2">
            <div class="rounded-md border border-gray-200 p-2">
              <div class="text-xs text-gray-500 mb-1">Temperature controller setpoint (whole °C, {{ data.setpoint.min }} to {{ data.setpoint.max }})</div>
              <!-- The controller has no setpoint read, so "now" is the last setpoint mark1 got accepted. -->
              <div class="mb-1 text-sm">
                <span class="text-gray-500 text-xs">Now:</span>
                <template v-if="lastSetpoint">
                  <span class="font-semibold text-gray-900">{{ lastSetpoint.celsius }} °C</span>
                  <span class="text-xs text-gray-500"> · set from mark1 {{ ago(lastSetpoint.at) }}{{ lastSetpoint.by ? ' by ' + lastSetpoint.by : '' }}</span>
                </template>
                <span v-else class="text-gray-500">not reported by the machine — chamber is {{ t.available && t.celsius !== null && t.celsius !== undefined ? Number(t.celsius).toFixed(1) + ' °C' : 'unknown' }}</span>
              </div>
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

            <ToggleRow label="Compressor" :state="onOff(t.compressorOn)" :tone="boolTone(t.compressorOn)" :disabled="!canSend" @on="ask('compressor', { on: true }, 'Switch the compressor on?', 'Overrides the controller until it switches again.')" @off="ask('compressor', { on: false }, 'Switch the compressor off?', 'The chamber will warm until the controller switches it back on.')" />
            <div class="rounded-md border border-gray-200 p-2 flex items-center justify-between gap-2">
              <span class="text-sm text-gray-700">
                Compressor control
                <StateChip :value="modeText(t.compressorRemoteMode)" :tone="modeTone(t.compressorRemoteMode)" />
              </span>
              <span class="flex gap-1">
                <Button type="button" class="bg-gray-100 hover:bg-gray-200 text-gray-800" :disabled="!canSend"
                        @click.prevent="ask('comprmode', { on: false }, 'Give the compressor back to the controller?', 'The controller then runs it from its own setpoint and differential, and remote on/off stops working.')">Controller</Button>
                <Button type="button" class="bg-gray-100 hover:bg-gray-200 text-gray-800" :disabled="!canSend"
                        @click.prevent="ask('comprmode', { on: true }, 'Take remote control of the compressor?', 'The controller stops cycling it on its own. Hand it back when you are done, or the cabinet will not hold temperature.')">Remote</Button>
              </span>
            </div>
            <ToggleRow label="Cabinet fan" :state="onOff(s?.fanOn)" :tone="boolTone(s?.fanOn)"
                       :note="t.fanRemoteMode === true ? 'remote (us)' : t.fanRemoteMode === false ? 'controller' : ''"
                       :disabled="!canSend" @on="send('fan', { on: true })" @off="send('fan', { on: false })" />
            <!-- lightState is null on every unit so far: Zijia's own portal answers 不支持 for light. -->
            <ToggleRow label="Light" :state="s?.lightState || 'not reported'" :tone="s?.lightState ? 'info' : 'unknown'" :disabled="!canSend"
                       @on="send('light', { on: true })" @off="send('light', { on: false })" />

            <div class="rounded-md border border-gray-200 p-2 flex items-center justify-between gap-2">
              <span class="text-sm text-gray-700">
                Music volume
                <StateChip :value="s?.volume ?? '—'" :tone="s?.volume === 0 ? 'warn' : s?.volume === undefined || s?.volume === null ? 'unknown' : 'info'" />
              </span>
              <span class="flex gap-1">
                <Button type="button" class="bg-gray-100 hover:bg-gray-200 text-gray-800" :disabled="!canSend" @click.prevent="ask('volume', { step: 'mute' }, 'Mute the machine?', 'Mute persists across restarts until someone turns it back up.')">Mute</Button>
                <Button type="button" class="bg-gray-100 hover:bg-gray-200 text-gray-800" :disabled="!canSend" @click.prevent="send('volume', { step: 'down' })">Down</Button>
                <Button type="button" class="bg-gray-100 hover:bg-gray-200 text-gray-800" :disabled="!canSend" @click.prevent="send('volume', { step: 'up' })">Up</Button>
              </span>
            </div>

            <div class="rounded-md border border-gray-200 p-2 md:col-span-2">
              <div class="text-xs text-gray-500 mb-1">Pull the machine's log (kept on the machine for about a day; the host's own lines are included when READ_LOGS was granted at install)</div>
              <div class="flex flex-wrap items-center gap-2">
                <label class="text-xs text-gray-600">last
                  <input v-model.number="logPull.minutes" type="number" :min="data.log_pull?.minutes.min" :max="data.log_pull?.minutes.max" class="ml-1 w-20 rounded border-gray-300 text-xs py-1" /> min</label>
                <label class="text-xs text-gray-600">up to
                  <input v-model.number="logPull.lines" type="number" :min="data.log_pull?.lines.min" :max="data.log_pull?.lines.max" step="100" class="ml-1 w-24 rounded border-gray-300 text-xs py-1" /> lines</label>
                <label class="text-xs text-gray-600">containing
                  <input v-model.trim="logPull.grep" type="text" maxlength="64" placeholder="e.g. Ag325, SERVICE-MODE, camera" class="ml-1 w-56 rounded border-gray-300 text-xs py-1" /></label>
                <Button type="button" class="text-white" :class="canSend ? 'bg-sky-700 hover:bg-sky-800' : 'bg-gray-300'" :disabled="!canSend"
                        @click.prevent="send('logs', { minutes: logPull.minutes, lines: logPull.lines, grep: logPull.grep || undefined })">
                  <DocumentArrowDownIcon class="h-4 w-4 mr-1" /> Pull logs
                </Button>
              </div>
            </div>

            <div v-if="data.can_door" class="rounded-md border border-red-200 bg-red-50 p-2 flex items-center justify-between gap-2">
              <span class="text-sm text-gray-700">
                Door
                <StateChip :value="(s?.door?.lockState ?? '—') + ' · ' + (s?.door?.doorState ?? '—')"
                           :tone="!s?.door?.lockState ? 'unknown' : s.door.lockState === 'unlocked' || s.door?.doorState === 'opened' ? 'warn' : 'ok'" />
              </span>
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

        <!-- Timeline: commands from here, controls pressed on the kiosk, and the machine's own events -->
        <div v-if="data.commands.length" class="border-t pt-3">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Timeline · commands, kiosk panel and machine events</span>
            <span class="flex items-center gap-2 text-xs">
              <input v-model.trim="timelineFilter" type="text" placeholder="filter rows…" class="w-40 rounded border-gray-300 text-xs py-1" />
              <label class="text-gray-600"><input v-model="showEvents" type="checkbox" class="rounded border-gray-300 mr-1" />machine events</label>
              <span class="text-gray-500">
                last {{ data.commands.length }}<template v-if="data.total"> of {{ data.total }}</template>
              </span>
              <button type="button" class="text-sky-700 hover:underline" @click.prevent="limit = limit >= 200 ? 20 : 200; load()">{{ limit >= 200 ? 'fewer' : 'more' }}</button>
            </span>
          </div>
          <div class="mt-1 overflow-x-auto">
            <table class="min-w-full text-xs">
              <thead class="text-left text-gray-500">
                <tr><th class="py-1 pr-3">When</th><th class="py-1 pr-3">What</th><th class="py-1 pr-3">From</th><th class="py-1 pr-3">Result</th><th class="py-1 pr-3">Machine said</th><th class="py-1">Log</th></tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <!--
                  Runs of the same machine event collapse into their newest row (Brian, 2026-09-16):
                  an unanswered card reader files one every two minutes, and 20 identical CardDetect
                  lines buried everything else. Click the count to see the individual rows.
                -->
                <template v-for="g in timelineGroups" :key="g.lead.id">
                  <tr :class="g.lead.source === 'event' ? 'bg-gray-50' : ''">
                    <td class="py-1 pr-3 whitespace-nowrap text-gray-600">
                      {{ formatTime(g.lead.requested_at) }}
                      <span v-if="g.count > 1" class="block text-gray-400">since {{ formatTime(g.oldest.requested_at) }}</span>
                    </td>
                    <td class="py-1 pr-3 whitespace-nowrap">
                      {{ describe(g.lead) }}
                      <button v-if="g.count > 1" type="button" class="ml-1 rounded-full bg-slate-600 px-2 py-0.5 font-medium text-white"
                              @click.prevent="toggleGroup(g.lead.id)">
                        ×{{ g.count }} {{ openGroups.has(g.lead.id) ? '▾' : '▸' }}
                      </button>
                    </td>
                    <td class="py-1 pr-3 whitespace-nowrap text-gray-600">
                      <span class="inline-flex items-center rounded px-1.5 py-0.5" :class="sourceBadge(g.lead.source)">{{ sourceLabel(g.lead) }}</span>
                    </td>
                    <td class="py-1 pr-3 whitespace-nowrap">
                      <span class="inline-flex items-center rounded-full px-2 py-0.5 font-medium" :class="badge(g.lead.status)">{{ label(g.lead.status) }}</span>
                      <span v-if="g.lead.responded_at && g.lead.source === 'mark1'" class="ml-1 text-gray-400">{{ answeredIn(g.lead) }}</span>
                    </td>
                    <td class="py-1 pr-3 text-gray-700 max-w-md">{{ g.lead.message || '' }}</td>
                    <td class="py-1 whitespace-nowrap">
                      <button v-if="g.lead.has_log" type="button" class="text-sky-700 hover:underline mr-2" @click.prevent="toggle(g.lead.id)">
                        {{ open.has(g.lead.id) ? 'hide' : 'excerpt' }}<span v-if="g.lead.log_scope === 'app'" class="text-gray-400"> (app only)</span>
                      </button>
                      <template v-if="g.lead.log_file">
                        <a :href="g.lead.log_file.url" target="_blank" class="text-sky-700 hover:underline mr-2">view {{ g.lead.log_file.lines ? g.lead.log_file.lines + ' lines' : 'file' }}</a>
                        <a :href="g.lead.log_file.url + '?download=1'" class="text-sky-700 hover:underline">download</a>
                      </template>
                    </td>
                  </tr>
                  <tr v-if="g.lead.has_log && open.has(g.lead.id)">
                    <td colspan="6" class="py-1">
                      <pre class="max-h-72 overflow-auto rounded bg-gray-900 p-2 text-[11px] leading-snug text-gray-100 whitespace-pre-wrap break-all">{{ excerpts[g.lead.id] ?? 'loading…' }}</pre>
                    </td>
                  </tr>
                  <!-- The collapsed run, opened on demand: same rows, just indented and dimmed. -->
                  <template v-if="g.count > 1 && openGroups.has(g.lead.id)">
                    <tr v-for="m in g.rest" :key="m.id" class="bg-gray-50 text-gray-500">
                      <td class="py-1 pr-3 pl-4 whitespace-nowrap">{{ formatTime(m.requested_at) }}</td>
                      <td class="py-1 pr-3 whitespace-nowrap">{{ describe(m) }}</td>
                      <td class="py-1 pr-3 whitespace-nowrap">
                        <span class="inline-flex items-center rounded px-1.5 py-0.5" :class="sourceBadge(m.source)">{{ sourceLabel(m) }}</span>
                      </td>
                      <td class="py-1 pr-3 whitespace-nowrap">
                        <span class="inline-flex items-center rounded-full px-2 py-0.5 font-medium" :class="badge(m.status)">{{ label(m.status) }}</span>
                      </td>
                      <td class="py-1 pr-3 max-w-md">{{ m.message || '' }}</td>
                      <td class="py-1"></td>
                    </tr>
                  </template>
                </template>
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
import { ArrowPathIcon, DocumentArrowDownIcon, LockClosedIcon, LockOpenIcon } from '@heroicons/vue/20/solid'
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
const logPull = ref({ minutes: 60, lines: 5000, grep: '' })
const timelineFilter = ref('')
const showEvents = ref(true)
/** Newest 20 rows by default (Brian, 2026-09-16); "more" widens to 200 without a page reload. */
const limit = ref(20)
const open = ref(new Set())
const excerpts = ref({})
async function toggle(id) {
  const n = new Set(open.value); n.has(id) ? n.delete(id) : n.add(id); open.value = n
  if (n.has(id) && excerpts.value[id] === undefined) {
    try {
      const res = await axios.get('/vends/' + props.vendId + '/freezer-controls/' + id + '/excerpt')
      excerpts.value = { ...excerpts.value, [id]: res.data.log || '(empty)' }
    } catch (e) {
      excerpts.value = { ...excerpts.value, [id]: 'Could not load the excerpt.' }
    }
  }
}
const visibleCommands = computed(() => data.value.commands.filter(c => {
  if (!showEvents.value && c.source === 'event') return false
  const q = timelineFilter.value.toLowerCase()
  if (!q) return true
  return [describe(c), c.requested_by, c.message, c.status, c.source].filter(Boolean).join(' ').toLowerCase().includes(q)
}))
/**
 * Consecutive rows that say exactly the same thing (same source, op, verdict and message) fold into
 * the newest one. Only CONSECUTIVE runs fold, so the order of events is never rearranged: an
 * unrelated row between two CardDetect errors splits them into two runs, as it should.
 */
const timelineGroups = computed(() => {
  const groups = []
  for (const c of visibleCommands.value) {
    const key = [c.source, c.op, c.status, c.message].join('\u0000')
    const last = groups[groups.length - 1]
    if (last && last.key === key) {
      last.rest.push(c)
      last.oldest = c
      last.count++
    } else {
      groups.push({ key, lead: c, oldest: c, rest: [], count: 1 })
    }
  }
  return groups
})
const openGroups = ref(new Set())
function toggleGroup(id) {
  const n = new Set(openGroups.value)
  n.has(id) ? n.delete(id) : n.add(id)
  openGroups.value = n
}
function sourceLabel(c) { return c.source === 'panel' ? 'Kiosk panel' : c.source === 'event' ? 'Machine' : (c.requested_by || 'mark1') }
function sourceBadge(source) { return source === 'panel' ? 'bg-indigo-100 text-indigo-800' : source === 'event' ? 'bg-gray-200 text-gray-700' : 'bg-sky-100 text-sky-800' }

const s = computed(() => data.value.status || null)
const t = computed(() => s.value?.thermostat || { available: false })
const canSend = computed(() => data.value.can_control && data.value.supported && !data.value.pending && !sending.value)
const statusStale = computed(() => !data.value.status_at || now.value - Date.parse(data.value.status_at) > 10 * 60 * 1000)
/**
 * The setpoint the controller is on, as far as mark1 can know: the newest `setpoint` command it
 * accepted (resolved server-side, so it does not fall out of the timeline window). The AG325
 * exposes no setpoint read, so an untouched machine shows "not reported" rather than a number
 * nobody verified.
 */
const lastSetpoint = computed(() => data.value.setpoint?.last ?? null)
const alarmText = computed(() => {
  if (!t.value.available) return '—'
  if (t.value.highTempAlarm) return 'HIGH temp'
  if (t.value.lowTempAlarm) return 'LOW temp'
  return 'none'
})

const OP_LABELS = { status: 'Sync status', lock: 'Lock door', unlock: 'Unlock door', fan: 'Cabinet fan', light: 'Light', compressor: 'Compressor', comprmode: 'Compressor control', setpoint: 'Setpoint', volume: 'Volume', logs: 'Pull logs', boot: 'Machine booted' }

// The controller's own flags: false = it runs that output from its setpoint loop and ignores our
// commands; true = it obeys us and stops cycling on its own.
const modeText = (v) => (v === true ? 'remote (us)' : v === false ? 'controller' : '—')
const RESULT_LABELS = { pending: 'waiting', ok: 'done', refused: 'refused', indeterminate: 'no answer from host', unsupported: 'not supported', busy: 'busy (sale)', invalid: 'invalid', expired: 'expired', duplicate: 'duplicate', error: 'error', timeout: 'no answer' }

function describe(c) {
  const a = c.args || {}
  if (c.op && c.op.startsWith('error:')) return 'Error logged by ' + c.op.slice(6)
  if ('on' in a) return OP_LABELS[c.op] + ' ' + (a.on ? 'on' : 'off')
  if ('celsius' in a) return OP_LABELS[c.op] + ' ' + a.celsius + ' °C'
  if ('step' in a) return OP_LABELS[c.op] + ' ' + a.step
  if (c.op === 'logs') return 'Pull logs' + (a.minutes ? ' · last ' + a.minutes + ' min' : '') + (a.grep ? ' · "' + a.grep + '"' : '')
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
/** Chip colour for a tri-state flag: green on, slate off, grey when the machine never said. */
function boolTone(v) { return v === true ? 'on' : v === false ? 'off' : 'unknown' }
/** Controller = normal (indigo); remote = we are holding it, which someone must hand back (amber). */
function modeTone(v) { return v === true ? 'warn' : v === false ? 'info' : 'unknown' }
function secs(n) { return !n ? '—' : n % 60 === 0 ? (n / 60) + ' min' : n + ' s' }
function formatTime(iso) { return iso ? moment(iso).format('DD MMM HH:mm:ss') : '' }
function ago(iso) { return iso ? moment(iso).from(moment(now.value)) : '' }
function answeredIn(c) {
  const ms = Date.parse(c.responded_at) - Date.parse(c.requested_at)
  return Number.isFinite(ms) && ms >= 0 ? 'in ' + Math.max(1, Math.round(ms / 1000)) + ' s' : ''
}

let timer = null
let logPullInit = false
async function load() {
  try {
    const res = await axios.get('/vends/' + props.vendId + '/freezer-controls', { params: { limit: limit.value } })
    const wasPending = data.value.pending
    const firstLoad = !loaded.value
    data.value = res.data
    loaded.value = true
    // Open the stepper on the last setpoint we set, so "Set" without touching it is a no-op
    // rather than a silent jump to the -18 default.
    if (firstLoad && lastSetpoint.value) setpoint.value = lastSetpoint.value.celsius
    if (res.data.log_pull && !logPullInit) {
      logPullInit = true
      logPull.value = { minutes: res.data.log_pull.minutes.default, lines: res.data.log_pull.lines.default, grep: '' }
    }
    if (wasPending && !res.data.pending) {
      const last = res.data.commands.find(c => c.source === 'mark1')
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
/** The "what it is now" chip that every control row wears, so no button is pressed blind. */
// Solid fills, not the pale badges used elsewhere: this chip is the one thing that must be read
// before a button is pressed, and a grey-on-grey pill was missed at a glance (Brian, 2026-09-16).
// Grey is kept for "we do not know" only, so a colourless chip always means exactly that.
const CHIP_TONES = {
  ok: 'bg-emerald-600 text-white',
  on: 'bg-emerald-600 text-white',
  off: 'bg-slate-600 text-white',
  info: 'bg-indigo-600 text-white',
  warn: 'bg-amber-500 text-white',
  bad: 'bg-red-600 text-white',
  unknown: 'bg-gray-400 text-white',
  neutral: 'bg-slate-600 text-white',
}
const StateChip = defineComponent({
  props: { value: [String, Number], tone: { type: String, default: 'neutral' }, note: String },
  setup: (p) => () => h('span', { class: 'ml-2 inline-flex items-center gap-1' }, [
    h('span', { class: 'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ' + (CHIP_TONES[p.tone] || CHIP_TONES.neutral) }, String(p.value ?? '—')),
    p.note ? h('span', { class: 'text-xs text-gray-500' }, p.note) : null,
  ]),
})
const ToggleRow = defineComponent({
  props: { label: String, disabled: Boolean, state: [String, Number], tone: String, note: String },
  emits: ['on', 'off'],
  setup: (p, { emit }) => () => h('div', { class: 'rounded-md border border-gray-200 p-2 flex items-center justify-between gap-2' }, [
    h('span', { class: 'text-sm text-gray-700' }, [
      p.label,
      p.state !== undefined && p.state !== null ? h(StateChip, { value: p.state, tone: p.tone || 'neutral', note: p.note }) : null,
    ]),
    h('span', { class: 'flex gap-1' }, [
      h('button', { type: 'button', disabled: p.disabled, class: 'inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md shadow-sm ' + (p.disabled ? 'bg-gray-100 text-gray-400' : 'bg-gray-100 hover:bg-gray-200 text-gray-800'), onClick: () => emit('on') }, 'On'),
      h('button', { type: 'button', disabled: p.disabled, class: 'inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md shadow-sm ' + (p.disabled ? 'bg-gray-100 text-gray-400' : 'bg-gray-100 hover:bg-gray-200 text-gray-800'), onClick: () => emit('off') }, 'Off'),
    ]),
  ]),
})
</script>
