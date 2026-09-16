<template>
  <!--
    Setting/Edit > Smart Freezer > Remote controls (FreezerControlController).

    Every button sends ONE signed FREEZERCTL command over MQTT; the freezer answers with its verdict
    and a fresh status snapshot, which the panel polls for (useFreezerControls). Layout only: the
    transport lives in that composable and the meaning of each field in FreezerStatus, so a field and
    its colour cannot drift between the status grid and the control rows.
  -->
  <div class="sm:col-span-6">
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
      <header class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 bg-gray-50 px-4 py-3">
        <div class="flex items-center gap-2">
          <h3 class="text-sm font-semibold text-gray-900">Remote controls</h3>
          <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset"
                :class="data.is_online ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-gray-100 text-gray-600 ring-gray-300'">
            <span class="h-1.5 w-1.5 rounded-full" :class="data.is_online ? 'bg-emerald-500' : 'bg-gray-400'"></span>
            {{ data.is_online ? 'online' : 'offline' }}
          </span>
          <span v-if="data.last_seen_at" class="text-xs text-gray-500">last seen {{ ago(data.last_seen_at) }}</span>
        </div>
        <Button type="button" class="text-white"
                :class="canSend ? 'bg-sky-700 hover:bg-sky-800' : 'bg-gray-300 cursor-not-allowed'"
                :disabled="!canSend" @click.prevent="send('status')">
          <ArrowPathIcon class="mr-1 h-4 w-4" :class="data.pending ? 'animate-spin' : ''" />
          Sync now
        </Button>
      </header>

      <div class="space-y-5 p-4 text-sm">
        <p v-if="!loaded" class="text-gray-500">Loading…</p>
        <p v-else-if="!data.supported" class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800 ring-1 ring-inset ring-amber-200">
          This machine's app (versionCode {{ data.apk_version_code ?? 'unknown' }}) is too old for remote controls.
        </p>

        <!-- A control the controller owns is accepted by the host and changes nothing: say so first. -->
        <div v-if="loaded && data.supported && status.ignoredControls.length"
             class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800 ring-1 ring-inset ring-amber-200">
          <p v-for="note in status.ignoredControls" :key="note">{{ note }}</p>
        </div>

        <template v-if="loaded">
          <section>
            <header class="flex flex-wrap items-baseline justify-between gap-2">
              <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Current status</h4>
              <span class="text-xs" :class="statusStale ? 'text-amber-700' : 'text-gray-500'">
                <template v-if="!data.status_at">No status yet — press Sync now.</template>
                <template v-else>Reported by the machine {{ formatTime(data.status_at) }} ({{ ago(data.status_at) }})</template>
              </span>
            </header>

            <div v-if="status.known" class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-6">
              <!-- The one number the cold chain is judged on gets the space, and the colour band. -->
              <div class="col-span-2 rounded-lg border px-4 py-3 ring-1 ring-inset" :class="chamberClasses">
                <div class="text-[11px] font-medium uppercase tracking-wide text-gray-500">Chamber</div>
                <div class="mt-0.5 text-3xl font-semibold tracking-tight text-gray-900">{{ status.chamber.text }}</div>
                <div class="mt-0.5 text-xs text-gray-500">{{ status.chamber.detail }}</div>
              </div>
              <StatusTile v-for="tile in status.tiles" :key="tile.label" v-bind="tile" />
            </div>

            <p v-if="status.telemetry" class="mt-2 text-xs leading-relaxed text-gray-500">
              Machine reports on its own: temperature &amp; door every {{ everySeconds(status.telemetry.venderSeconds) }},
              presence every {{ everySeconds(status.telemetry.pSeconds) }}, hardware status every
              {{ everySeconds(status.telemetry.statusSeconds) }} (and on change), MQTT heartbeat every
              {{ everySeconds(status.telemetry.heartbeatSeconds) }}; temperature excursions are checked every
              {{ everySeconds(status.telemetry.excursionSampleSeconds) }}.
            </p>
          </section>

          <!--
            Every row states WHAT IT IS NOW before the buttons that change it (Brian, 2026-09-16):
            pressing On/Off blind is how a cabinet ends up with the compressor left off. The values
            are the machine's last status snapshot, so the header says how old that is.
          -->
          <section v-if="data.can_control && data.supported" class="border-t border-gray-100 pt-4">
            <header class="flex flex-wrap items-baseline justify-between gap-2">
              <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">Controls</h4>
              <span class="text-xs" :class="statusStale ? 'text-amber-700' : 'text-gray-500'">
                <template v-if="!data.status_at">Values unknown until the machine reports — press Sync now.</template>
                <template v-else>Values as at {{ formatTime(data.status_at) }} ({{ ago(data.status_at) }})</template>
              </span>
            </header>
            <p v-if="data.pending" class="mt-1 text-xs text-sky-700">Waiting for the machine to answer the last command…</p>
            <p v-else-if="!data.is_online" class="mt-1 text-xs text-amber-700">The machine looks offline — commands expire after a minute if it does not answer.</p>

            <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
              <!-- The controller has no setpoint read, so "now" is the last setpoint mark1 got accepted. -->
              <div class="rounded-lg border border-gray-200 bg-white p-3">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="text-sm font-medium text-gray-800">Temperature setpoint</span>
                  <StateChip v-if="lastSetpoint" :value="lastSetpoint.celsius + ' °C'" tone="info" />
                  <StateChip v-else value="not reported" tone="unknown" />
                </div>
                <p class="mt-0.5 text-xs text-gray-500">
                  <template v-if="lastSetpoint">
                    Set from mark1 {{ ago(lastSetpoint.at) }}{{ lastSetpoint.by ? ' by ' + lastSetpoint.by : '' }} · whole °C, {{ data.setpoint.min }} to {{ data.setpoint.max }}
                  </template>
                  <template v-else>
                    The controller cannot report its setpoint — chamber is {{ status.chamber.text }} · whole °C, {{ data.setpoint.min }} to {{ data.setpoint.max }}
                  </template>
                </p>
                <div class="mt-2 flex items-center gap-2">
                  <span class="isolate inline-flex -space-x-px shadow-sm">
                    <ControlButton :disabled="!canSend" @click="setpoint = Math.max(data.setpoint.min, setpoint - 1)">−</ControlButton>
                    <span class="inline-flex w-20 items-center justify-center bg-white px-3 py-1.5 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300">
                      {{ setpoint }} °C
                    </span>
                    <ControlButton :disabled="!canSend" @click="setpoint = Math.min(data.setpoint.max, setpoint + 1)">+</ControlButton>
                  </span>
                  <ControlButton tone="primary" class="rounded-md" :disabled="!canSend"
                                 @click="ask('setpoint', { celsius: setpoint }, 'Set the temperature controller to ' + setpoint + ' °C?', 'The cabinet will cool to this target until someone changes it. The machine cannot report its current setpoint, so check the chamber temperature afterwards.')">
                    Set
                  </ControlButton>
                </div>
              </div>

              <ControlRow label="Compressor" :state="onOff(status.compressorOn)" :tone="boolTone(status.compressorOn)">
                <ControlButton :disabled="!canSend" :active="status.compressorOn === true"
                               @click="ask('compressor', { on: true }, 'Switch the compressor on?', 'Overrides the controller until it switches again.')">On</ControlButton>
                <ControlButton :disabled="!canSend" :active="status.compressorOn === false"
                               @click="ask('compressor', { on: false }, 'Switch the compressor off?', 'The chamber will warm until the controller switches it back on.')">Off</ControlButton>
              </ControlRow>

              <ControlRow label="Compressor control" :state="modeText(status.compressorRemote)" :tone="modeTone(status.compressorRemote)">
                <ControlButton :disabled="!canSend" :active="status.compressorRemote === false"
                               @click="ask('comprmode', { on: false }, 'Give the compressor back to the controller?', 'The controller then runs it from its own setpoint and differential, and remote on/off stops working.')">Controller</ControlButton>
                <ControlButton :disabled="!canSend" :active="status.compressorRemote === true"
                               @click="ask('comprmode', { on: true }, 'Take remote control of the compressor?', 'The controller stops cycling it on its own. Hand it back when you are done, or the cabinet will not hold temperature.')">Remote</ControlButton>
              </ControlRow>

              <ControlRow label="Cabinet fan" :state="onOff(status.fanOn)" :tone="boolTone(status.fanOn)" :note="modeText(status.fanRemote)">
                <ControlButton :disabled="!canSend" :active="status.fanOn === true" @click="send('fan', { on: true })">On</ControlButton>
                <ControlButton :disabled="!canSend" :active="status.fanOn === false" @click="send('fan', { on: false })">Off</ControlButton>
              </ControlRow>

              <!-- lightState is null on every unit so far: Zijia's own portal answers 不支持 for light. -->
              <ControlRow label="Light" :state="status.lightState || 'not reported'" :tone="status.lightState ? 'info' : 'unknown'">
                <ControlButton :disabled="!canSend" @click="send('light', { on: true })">On</ControlButton>
                <ControlButton :disabled="!canSend" @click="send('light', { on: false })">Off</ControlButton>
              </ControlRow>

              <ControlRow label="Music volume" :state="status.volume ?? '—'" :tone="volumeTone">
                <ControlButton :disabled="!canSend"
                               @click="ask('volume', { step: 'mute' }, 'Mute the machine?', 'Mute persists across restarts until someone turns it back up.')">Mute</ControlButton>
                <ControlButton :disabled="!canSend" @click="send('volume', { step: 'down' })">Down</ControlButton>
                <ControlButton :disabled="!canSend" @click="send('volume', { step: 'up' })">Up</ControlButton>
              </ControlRow>

              <div class="rounded-lg border border-gray-200 bg-white p-3 md:col-span-2">
                <div class="text-sm font-medium text-gray-800">Machine log</div>
                <p class="mt-0.5 text-xs text-gray-500">
                  Kept on the machine for about a day; the host's own lines are included when READ_LOGS was granted at install.
                </p>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-gray-600">
                  <label>last
                    <input v-model.number="logPull.minutes" type="number" :min="data.log_pull?.minutes.min" :max="data.log_pull?.minutes.max"
                           class="ml-1 w-20 rounded-md border-gray-300 py-1 text-xs" /> min</label>
                  <label>up to
                    <input v-model.number="logPull.lines" type="number" :min="data.log_pull?.lines.min" :max="data.log_pull?.lines.max" step="100"
                           class="ml-1 w-24 rounded-md border-gray-300 py-1 text-xs" /> lines</label>
                  <label>containing
                    <input v-model.trim="logPull.grep" type="text" maxlength="64" placeholder="e.g. Ag325, SERVICE-MODE, camera"
                           class="ml-1 w-56 rounded-md border-gray-300 py-1 text-xs" /></label>
                  <ControlButton tone="primary" class="rounded-md" :disabled="!canSend"
                                 @click="send('logs', { minutes: logPull.minutes, lines: logPull.lines, grep: logPull.grep || undefined })">
                    <DocumentArrowDownIcon class="mr-1 h-4 w-4" /> Pull logs
                  </ControlButton>
                </div>
              </div>

              <ControlRow v-if="data.can_door" label="Door" :state="status.doorText" :tone="status.doorTone" danger
                          hint="Unlocking here opens the lock with no order, no video and no AI check." class="md:col-span-2">
                <ControlButton :disabled="!canSend" @click="send('lock')">
                  <LockClosedIcon class="mr-1 h-4 w-4" /> Lock
                </ControlButton>
                <ControlButton tone="danger" :disabled="!canSend"
                               @click="ask('unlock', {}, 'Unlock the door remotely?', 'This opens the lock with no order, no video and no AI check — stock taken is not recorded. It is logged with your name. The machine refuses while a customer sale is in progress.', true)">
                  <LockOpenIcon class="mr-1 h-4 w-4" /> Unlock
                </ControlButton>
              </ControlRow>
            </div>
          </section>

          <section v-if="data.commands.length" class="border-t border-gray-100 pt-4">
            <CommandTimeline :vend-id="vendId" :commands="data.commands" :total="data.total" :wide="limit >= 200" @limit="setLimit" />
          </section>
        </template>
      </div>
    </div>

    <Teleport to="body">
      <Modal :open="!!confirm" @modalClose="confirm = null">
        <template #header><span class="font-semibold text-black">{{ confirm?.title }}</span></template>
        <template #default>
          <p class="text-sm text-gray-700">{{ confirm?.detail }}</p>
          <div class="mt-4 flex justify-end space-x-2">
            <Button type="button" class="bg-gray-200 hover:bg-gray-300 text-gray-800" @click.prevent="confirm = null">Cancel</Button>
            <Button type="button" class="text-white" :class="confirm?.danger ? 'bg-red-600 hover:bg-red-700' : 'bg-sky-700 hover:bg-sky-800'"
                    @click.prevent="confirmSend">Yes, send</Button>
          </div>
        </template>
      </Modal>
    </Teleport>
  </div>
</template>

<script setup>
import Button from '@/Components/Button.vue'
import Modal from '@/Components/Modal.vue'
import CommandTimeline from '@/Components/SmartFreezer/CommandTimeline.vue'
import ControlButton from '@/Components/SmartFreezer/ControlButton.vue'
import ControlRow from '@/Components/SmartFreezer/ControlRow.vue'
import StateChip from '@/Components/SmartFreezer/StateChip.vue'
import StatusTile from '@/Components/SmartFreezer/StatusTile.vue'
import { ArrowPathIcon, DocumentArrowDownIcon, LockClosedIcon, LockOpenIcon } from '@heroicons/vue/20/solid'
import { computed, ref } from 'vue'
import moment from 'moment'
import { useFreezerControls } from '@/composables/useFreezerControls'
import { boolTone, everySeconds, modeText, modeTone, onOff, TONE } from '@/support/freezerStatus'

const props = defineProps({
  vendId: { type: Number, required: true },
})

const { data, status, loaded, canSend, statusStale, lastSetpoint, limit, now, send, setLimit, whenLoaded } =
  useFreezerControls(props.vendId)

const confirm = ref(null)
const setpoint = ref(-18)
const logPull = ref({ minutes: 60, lines: 5000, grep: '' })

// Open the stepper on the last setpoint we set, so "Set" without touching it is a no-op rather than
// a silent jump to the -18 default; the log-pull boxes start on the server's documented defaults.
whenLoaded((payload) => {
  if (payload.setpoint?.last) setpoint.value = payload.setpoint.last.celsius
  if (payload.log_pull) logPull.value = { minutes: payload.log_pull.minutes.default, lines: payload.log_pull.lines.default, grep: '' }
})

const CHAMBER_CLASSES = {
  [TONE.OK]: 'border-emerald-200 bg-emerald-50 ring-emerald-100',
  [TONE.WARN]: 'border-amber-200 bg-amber-50 ring-amber-100',
  [TONE.BAD]: 'border-red-200 bg-red-50 ring-red-100',
  [TONE.UNKNOWN]: 'border-gray-200 bg-gray-50 ring-gray-100',
}
const chamberClasses = computed(() => CHAMBER_CLASSES[status.value.chamber.tone] || CHAMBER_CLASSES[TONE.UNKNOWN])
const volumeTone = computed(() => (status.value.volume === 0 ? TONE.WARN : status.value.volume === null ? TONE.UNKNOWN : TONE.INFO))

function ask(op, args, title, detail, danger = false) {
  confirm.value = { op, args, title, detail, danger }
}

function confirmSend() {
  const pending = confirm.value
  confirm.value = null
  if (pending) send(pending.op, pending.args)
}

function formatTime(iso) {
  return iso ? moment(iso).format('DD MMM HH:mm:ss') : ''
}

function ago(iso) {
  return iso ? moment(iso).from(moment(now.value)) : ''
}
</script>
