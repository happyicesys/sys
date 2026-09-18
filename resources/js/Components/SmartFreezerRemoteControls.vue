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
        <span class="inline-block" v-tooltip="canSync ? '' : blockedReason">
          <Button type="button" class="text-white"
                  :class="canSync ? 'bg-sky-700 hover:bg-sky-800' : 'bg-gray-300 cursor-not-allowed'"
                  :disabled="!canSync" @click.prevent="send('status')">
            <ArrowPathIcon class="mr-1 h-4 w-4" :class="data.pending ? 'animate-spin' : ''" />
            Sync now
          </Button>
        </span>
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

            <p v-if="status.identity" class="mt-2 text-xs text-gray-500">
              Zijia device no. <span class="font-mono text-gray-700">{{ status.identity.deviceNo || '—' }}</span>
              · modem IMEI <span class="font-mono text-gray-700">{{ status.identity.imei || '—' }}</span>
              <template v-if="status.identity.hostVersion"> · host {{ status.identity.hostVersion }}</template>
              <template v-if="status.identity.pluginVersion"> · plugin {{ status.identity.pluginVersion }}</template>
              <template v-if="status.identity.driverVersion"> · driver {{ status.identity.driverVersion }}</template>
            </p>
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
            <p v-else-if="!data.is_online" class="mt-1 text-xs text-amber-700">
              The machine is offline, so the controls are disabled — a command it never receives would expire unanswered. Press Sync now to check.
            </p>

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
                <div class="mt-2 flex items-center gap-2" v-tooltip="blockedReason">
                  <span class="isolate inline-flex -space-x-px shadow-sm">
                    <ControlButton :disabled="!canSend" @click="setpoint = Math.max(data.setpoint.min, setpoint - 1)">−</ControlButton>
                    <span class="inline-flex w-20 items-center justify-center bg-white px-3 py-1.5 text-sm font-semibold text-gray-900 ring-1 ring-inset ring-gray-300">
                      {{ setpoint }} °C
                    </span>
                    <ControlButton :disabled="!canSend" @click="setpoint = Math.min(data.setpoint.max, setpoint + 1)">+</ControlButton>
                  </span>
                  <ArrowPathIcon v-if="busyOp === 'setpoint'" class="h-4 w-4 animate-spin text-sky-700" />
                  <ControlButton tone="primary" class="rounded-md" :disabled="!canSend"
                                 @click="ask('setpoint', { celsius: setpoint }, 'Set the temperature controller to ' + setpoint + ' °C?', 'The cabinet will cool to this target until someone changes it. The machine cannot report its current setpoint, so check the chamber temperature afterwards.')">
                    Set
                  </ControlButton>
                </div>
              </div>

              <!--
                Daily setpoint plan (night setback, pre-cool). mark1 sends each entry as an ordinary
                Setpoint command at its time, so every run shows in the timeline with the machine's
                verdict. The controller cannot read its setpoint back: the chamber is the proof.
              -->
              <div class="rounded-lg border border-gray-200 bg-white p-3">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="text-sm font-medium text-gray-800">Setpoint schedule</span>
                  <StateChip :value="activeSchedule ? activeSchedule + ' active' : 'none'" :tone="activeSchedule ? 'info' : 'unknown'" />
                </div>
                <p class="mt-0.5 text-xs text-gray-500">Every day, Singapore time. An entry more than 15 min late (machine busy or offline) waits for the next day.</p>
                <ul v-if="(data.schedule || []).length" class="mt-2 divide-y divide-gray-100 text-xs">
                  <li v-for="entry in data.schedule" :key="entry.id" class="flex flex-wrap items-center gap-2 py-1.5">
                    <span class="w-12 font-mono font-semibold" :class="entry.is_active ? 'text-gray-900' : 'text-gray-400 line-through'">{{ entry.run_at }}</span>
                    <span class="w-14" :class="entry.is_active ? 'text-gray-900' : 'text-gray-400'">{{ entry.celsius }} °C</span>
                    <span class="flex-1 text-gray-500">
                      <template v-if="entry.last_run_on">last {{ entry.last_run_on }}<template v-if="entry.last_status"> · {{ resultLabel(entry.last_status) }}</template></template>
                      <template v-else>not run yet</template>
                      <template v-if="entry.created_by"> · added by {{ entry.created_by }}</template>
                    </span>
                    <button type="button" class="text-sky-700 hover:underline disabled:text-gray-300" :disabled="scheduleBusy"
                            @click="toggleSchedule(entry)">{{ entry.is_active ? 'Pause' : 'Resume' }}</button>
                    <button type="button" class="text-red-600 hover:underline disabled:text-gray-300" :disabled="scheduleBusy"
                            @click="removeSchedule(entry)">Remove</button>
                  </li>
                </ul>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                  <input v-model="newSchedule.run_at" type="time" class="rounded-md border-gray-300 py-1 text-xs" />
                  <input v-model.number="newSchedule.celsius" type="number" :min="data.setpoint.min" :max="data.setpoint.max" step="1"
                         class="w-20 rounded-md border-gray-300 py-1 text-xs" /> °C
                  <ControlButton tone="primary" class="rounded-md" :disabled="scheduleBusy || !newSchedule.run_at" @click="addSchedule">Add</ControlButton>
                  <span v-if="scheduleError" class="text-red-600">{{ scheduleError }}</span>
                </div>
              </div>

              <ControlRow label="Compressor" :busy="busyOp === 'compressor'" :state="onOff(status.compressorOn)" :tone="boolTone(status.compressorOn)" :reason="blockedReason">
                <ControlButton :disabled="!canSend" :active="status.compressorOn === true"
                               @click="ask('compressor', { on: true }, 'Switch the compressor on?', 'Overrides the controller until it switches again.')">On</ControlButton>
                <ControlButton :disabled="!canSend" :active="status.compressorOn === false"
                               @click="ask('compressor', { on: false }, 'Switch the compressor off?', 'The chamber will warm until the controller switches it back on.')">Off</ControlButton>
              </ControlRow>

              <ControlRow label="Compressor control" :busy="busyOp === 'comprmode'" :state="modeText(status.compressorRemote)" :tone="modeTone(status.compressorRemote)" :reason="blockedReason">
                <ControlButton :disabled="!canSend" :active="status.compressorRemote === false"
                               @click="ask('comprmode', { on: false }, 'Give the compressor back to the controller?', 'The controller then runs it from its own setpoint and differential, and remote on/off stops working.')">Controller</ControlButton>
                <ControlButton :disabled="!canSend" :active="status.compressorRemote === true"
                               @click="ask('comprmode', { on: true }, 'Take remote control of the compressor?', 'The controller stops cycling it on its own. Hand it back when you are done, or the cabinet will not hold temperature.')">Remote</ControlButton>
              </ControlRow>

              <ControlRow label="Cabinet fan" :busy="busyOp === 'fan'" :state="onOff(status.fanOn)" :tone="boolTone(status.fanOn)" :note="modeText(status.fanRemote)" :reason="blockedReason">
                <ControlButton :disabled="!canSend" :active="status.fanOn === true" @click="send('fan', { on: true })">On</ControlButton>
                <ControlButton :disabled="!canSend" :active="status.fanOn === false" @click="send('fan', { on: false })">Off</ControlButton>
              </ControlRow>

              <!-- lightState is null on every unit so far: Zijia's own portal answers 不支持 for light. -->
              <ControlRow label="Light" :busy="busyOp === 'light'" :state="status.lightState || 'not reported'" :tone="status.lightState ? 'info' : 'unknown'" :reason="blockedReason">
                <ControlButton :disabled="!canSend" @click="send('light', { on: true })">On</ControlButton>
                <ControlButton :disabled="!canSend" @click="send('light', { on: false })">Off</ControlButton>
              </ControlRow>

              <ControlRow label="Music volume" :busy="busyOp === 'volume'" :state="status.volume ?? '—'" :tone="volumeTone" :reason="blockedReason">
                <ControlButton :disabled="!canSend"
                               @click="ask('volume', { step: 'mute' }, 'Mute the machine?', 'Mute persists across restarts until someone turns it back up.')">Mute</ControlButton>
                <ControlButton :disabled="!canSend" @click="send('volume', { step: 'down' })">Down</ControlButton>
                <ControlButton :disabled="!canSend" @click="send('volume', { step: 'up' })">Up</ControlButton>
              </ControlRow>

              <!--
                Cameras get their own card: a photo is the one control whose answer is a picture, so
                the newest one from EACH camera lives on the page instead of behind a button — three
                thumbnails, one view each, rather than five shots of whichever camera was used last.
                Clicking one opens it full size, where each camera's own history is a row. The
                machine is asked only when Take photo is pressed.
              -->
              <div class="rounded-lg border border-gray-200 bg-white p-3 md:col-span-2">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="text-sm font-medium text-gray-800">Cameras</span>
                  <StateChip v-if="!data.supported_batch2" value="needs app v14" tone="warn" />
                  <StateChip v-else-if="status.cameras.length" :value="cameraSummary" :tone="cameraTone" />
                </div>
                <p class="mt-0.5 text-xs text-gray-500">
                  The cabinet's own cameras — the newest shot of each. A photo takes a few seconds; open one for that
                  camera's history.
                </p>
                <div class="mt-2 flex flex-wrap items-center gap-2" v-tooltip="batch2Blocked">
                  <span class="isolate inline-flex -space-x-px shadow-sm">
                    <select v-model.number="photoCamera" class="rounded-l-md border-gray-300 py-1.5 text-xs" :disabled="!canSendBatch2">
                      <option v-for="cam in cameraChoices" :key="cam.id" :value="cam.id">{{ cam.label }}</option>
                    </select>
                    <ControlButton class="rounded-r-md" :disabled="!canSendBatch2" @click="send('photo', { cameraId: photoCamera })">
                      <ArrowPathIcon v-if="busyOp === 'photo'" class="mr-1 h-4 w-4 animate-spin" /> Take photo
                    </ControlButton>
                  </span>
                  <ControlButton v-if="data.photos?.length" class="rounded-md" @click="openPhoto(null)">View larger</ControlButton>
                  <span v-if="busyOp === 'photo'" class="text-xs text-sky-700">Waiting for the machine…</span>
                </div>
                <div v-if="latestPerCamera.length" class="mt-2 flex flex-wrap gap-2">
                  <button
                    v-for="photo in latestPerCamera"
                    :key="photo.id"
                    type="button"
                    class="overflow-hidden rounded-md border border-gray-200 hover:border-sky-500 focus:outline-none"
                    @click="openPhoto(photo)"
                  >
                    <img :src="photo.url" :alt="photoLabel(photo)" class="h-16 w-28 object-cover" loading="lazy" />
                    <span class="block bg-gray-50 px-1 py-0.5 text-[11px] text-gray-600">
                      {{ photoLabel(photo) }} · {{ ago(photo.taken_at) }}
                    </span>
                  </button>
                </div>
                <p v-else class="mt-2 text-xs text-gray-400">No photo yet.</p>
              </div>

              <div class="rounded-lg border border-gray-200 bg-white p-3 md:col-span-2">
                <div class="text-sm font-medium text-gray-800">Machine log</div>
                <p class="mt-0.5 text-xs text-gray-500">
                  Kept on the machine for about a day; the host's own lines are included when READ_LOGS was granted at install.
                </p>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-xs text-gray-600" v-tooltip="blockedReason">
                  <label>last
                    <input v-model.number="logPull.minutes" type="number" :min="data.log_pull?.minutes.min" :max="data.log_pull?.minutes.max"
                           class="ml-1 w-20 rounded-md border-gray-300 py-1 text-xs" /> min</label>
                  <label>up to
                    <input v-model.number="logPull.lines" type="number" :min="data.log_pull?.lines.min" :max="data.log_pull?.lines.max" step="100"
                           class="ml-1 w-24 rounded-md border-gray-300 py-1 text-xs" /> lines</label>
                  <label>containing
                    <input v-model.trim="logPull.grep" type="text" maxlength="64" placeholder="e.g. Ag325, SERVICE-MODE, camera"
                           class="ml-1 w-56 rounded-md border-gray-300 py-1 text-xs" /></label>
                  <ArrowPathIcon v-if="busyOp === 'logs'" class="h-4 w-4 animate-spin text-sky-700" />
                  <ControlButton tone="primary" class="rounded-md" :disabled="!canSend"
                                 @click="send('logs', { minutes: logPull.minutes, lines: logPull.lines, grep: logPull.grep || undefined })">
                    <DocumentArrowDownIcon class="mr-1 h-4 w-4" /> Pull logs
                  </ControlButton>
                </div>
              </div>

              <!--
                APK v14: the machine itself. Read-only probes first (self-check, photo, diagnostics),
                the two that drop the app or the box behind a confirm, and the raw SDK call last —
                a research tool, superadmin only, with the same bounds the device enforces.
              -->
              <div class="rounded-lg border border-gray-200 bg-white p-3 md:col-span-2">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="text-sm font-medium text-gray-800">Machine</span>
                  <StateChip v-if="!data.supported_batch2" value="needs app v14" tone="warn" />
                </div>
                <p class="mt-0.5 text-xs text-gray-500">
                  Self-check and diagnostics read; Beep sounds the machine so someone on site can find it; restart and reboot are refused by the machine while a sale is in progress.
                </p>
                <div class="mt-2 flex flex-wrap items-center gap-2" v-tooltip="batch2Blocked">
                  <ControlButton tone="primary" class="rounded-md" :disabled="!canSendBatch2" @click="send('selfcheck')">
                    <ArrowPathIcon v-if="busyOp === 'selfcheck'" class="mr-1 h-4 w-4 animate-spin" /> Self-check
                  </ControlButton>
                  <span class="isolate inline-flex -space-x-px shadow-sm">
                    <select v-model="diagProbe" class="rounded-l-md border-gray-300 py-1.5 text-xs" :disabled="!canSendBatch2">
                      <option v-for="(label, key) in data.diag_probes || {}" :key="key" :value="key">{{ label }}</option>
                    </select>
                    <ControlButton class="rounded-r-md" :disabled="!canSendBatch2" @click="send('diag', { probe: diagProbe })">
                      <ArrowPathIcon v-if="busyOp === 'diag'" class="mr-1 h-4 w-4 animate-spin" /> Run diagnostics
                    </ControlButton>
                  </span>
                  <span class="isolate inline-flex -space-x-px shadow-sm">
                    <select v-model.number="beepSeconds" class="rounded-l-md border-gray-300 py-1.5 text-xs" :disabled="!canSendBatch2">
                      <option v-for="s in beepChoices" :key="s" :value="s">{{ s }} s</option>
                    </select>
                    <ControlButton class="rounded-r-md" :disabled="!canSendBatch2" @click="send('beep', { seconds: beepSeconds })">
                      <ArrowPathIcon v-if="busyOp === 'beep'" class="mr-1 h-4 w-4 animate-spin" /> Beep
                    </ControlButton>
                  </span>
                  <ControlButton :disabled="!canSendBatch2"
                                 @click="ask('restart', {}, 'Restart the kiosk app?', 'The app relaunches in a few seconds and reports a boot event when it is back. The machine refuses while a sale is in progress.')">
                    <ArrowPathIcon v-if="busyOp === 'restart'" class="mr-1 h-4 w-4 animate-spin" /> Restart app
                  </ControlButton>
                  <ControlButton tone="danger" :disabled="!canSendBatch2"
                                 @click="ask('reboot', {}, 'Reboot the whole box?', 'Android restarts; the kiosk is back in about a minute and reports a boot event. The machine refuses while a sale is in progress.', true)">
                    <ArrowPathIcon v-if="busyOp === 'reboot'" class="mr-1 h-4 w-4 animate-spin" /> Reboot Android
                  </ControlButton>
                </div>
                <div v-if="data.can_sdk_raw" class="mt-3 border-t border-dashed border-gray-200 pt-2">
                  <div class="text-xs font-medium text-gray-700">Raw SDK call <span class="font-normal text-gray-500">— superadmin · what the host's plugin answers to an action name; e.g. <code>thermostatControl</code> with <code>{"key":"fanMode","value":1}</code></span></div>
                  <div class="mt-1 flex flex-wrap items-center gap-2" v-tooltip="batch2Blocked">
                    <input v-model.trim="sdkCall.action" type="text" maxlength="40" placeholder="action" pattern="[A-Za-z][A-Za-z0-9_]{2,39}"
                           class="w-44 rounded-md border-gray-300 py-1 font-mono text-xs" :disabled="!canSendBatch2" />
                    <input v-model.trim="sdkCall.params" type="text" maxlength="512" placeholder='{"doorId":1}'
                           class="w-80 rounded-md border-gray-300 py-1 font-mono text-xs" :disabled="!canSendBatch2" />
                    <ControlButton tone="danger" class="rounded-md" :disabled="!canSendBatch2 || !sdkCall.action"
                                   @click="ask('sdkcall', { action: sdkCall.action, params: sdkCall.params || '{}' }, 'Send raw action ' + sdkCall.action + '?', 'This hands the action straight to the Zijia host. Whatever it does, it does — the reply is shown in the timeline.', true)">
                      <ArrowPathIcon v-if="busyOp === 'sdkcall'" class="mr-1 h-4 w-4 animate-spin" /> Send
                    </ControlButton>
                  </div>
                </div>
              </div>

              <ControlRow v-if="data.can_door" label="Door" :busy="['lock', 'unlock'].includes(busyOp)" :state="status.doorText" :tone="status.doorTone" danger :reason="blockedReason"
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
      <CameraPhotoDialog
        :open="cameraOpen"
        :photos="data.photos || []"
        :cameras="cameraChoices"
        :camera-id="cameraOpenCam"
        :can-take="canSendBatch2"
        :busy="busyOp === 'photo'"
        :blocked-reason="batch2Blocked"
        :initial-photo-id="cameraOpenId"
        @take="(id) => send('photo', { cameraId: id })"
        @close="cameraOpen = false"
      />
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
import CameraPhotoDialog from '@/Components/SmartFreezer/CameraPhotoDialog.vue'
import CommandTimeline from '@/Components/SmartFreezer/CommandTimeline.vue'
import ControlButton from '@/Components/SmartFreezer/ControlButton.vue'
import ControlRow from '@/Components/SmartFreezer/ControlRow.vue'
import StateChip from '@/Components/SmartFreezer/StateChip.vue'
import StatusTile from '@/Components/SmartFreezer/StatusTile.vue'
import { ArrowPathIcon, DocumentArrowDownIcon, LockClosedIcon, LockOpenIcon } from '@heroicons/vue/20/solid'
import { computed, ref } from 'vue'
import moment from 'moment'
import { resultLabel, useFreezerControls } from '@/composables/useFreezerControls'
import { boolTone, cameraLabel, cameraOnline, everySeconds, modeText, modeTone, onOff, TONE } from '@/support/freezerStatus'

const props = defineProps({
  vendId: { type: Number, required: true },
})

const { data, status, loaded, busyOp, canSend, canSync, blockedReason, statusStale, lastSetpoint, limit, now, load, send, setLimit, whenLoaded } =
  useFreezerControls(props.vendId)

const confirm = ref(null)
const setpoint = ref(-18)
const logPull = ref({ minutes: 60, lines: 5000, grep: '' })
const cameraOpen = ref(false)
const cameraOpenId = ref(null)
const cameraOpenCam = ref(3)
const photoCamera = ref(3)

/**
 * Opens one camera's window — the view of the thumbnail that was clicked, or the view the picker
 * names when the button was pressed instead. The dialog has no picker of its own, so this is the
 * only place the view is chosen.
 */
function openPhoto(photo) {
  cameraOpenCam.value = photo?.camera_id ?? photoCamera.value
  cameraOpenId.value = photo?.id ?? null
  cameraOpen.value = true
}

/**
 * One thumbnail per camera — its newest shot — in the picker's order, so the card always reads
 * Inner / Planar / Customer rather than three copies of whichever camera was asked last. A camera
 * with nothing in the kept history is simply absent; the popup holds the rest.
 */
const latestPerCamera = computed(() => {
  const photos = data.value.photos || []
  const seen = new Set()
  const ordered = []
  for (const cam of cameraChoices.value) {
    const newest = photos.find((p) => p.camera_id === cam.id)
    if (newest) { ordered.push(newest); seen.add(cam.id) }
  }
  // A photo from a camera the machine no longer lists still deserves its tile.
  for (const photo of photos) {
    if (!seen.has(photo.camera_id)) { ordered.push(photo); seen.add(photo.camera_id) }
  }
  return ordered
})

/** "Planar View (cam 4)" for a stored photo, from the same labels the picker uses. */
function photoLabel(photo) {
  return cameraChoices.value.find((c) => c.id === photo.camera_id)?.label || `cam ${photo.camera_id}`
}

const cameraSummary = computed(() => {
  const cams = status.value.cameras
  const online = cams.filter(cameraOnline).length
  return `${online}/${cams.length} online`
})
const cameraTone = computed(() => {
  const cams = status.value.cameras
  return cams.length && cams.every(cameraOnline) ? 'ok' : 'bad'
})
const diagProbe = ref('system')
const sdkCall = ref({ action: '', params: '' })
const beepSeconds = ref(3)
const beepChoices = computed(() => Array.from({ length: data.value.beep_seconds_max || 10 }, (_, i) => i + 1))

const newSchedule = ref({ run_at: '22:00', celsius: -20 })
const scheduleBusy = ref(false)
const scheduleError = ref('')
const activeSchedule = computed(() => (data.value.schedule || []).filter((s) => s.is_active).length)

async function scheduleCall(fn) {
  scheduleBusy.value = true
  scheduleError.value = ''
  try {
    await fn()
    await load()
  } catch (e) {
    const errors = e.response?.data?.errors
    scheduleError.value = errors ? Object.values(errors).flat()[0] : e.response?.data?.message || 'Could not save the schedule.'
  } finally {
    scheduleBusy.value = false
  }
}

function addSchedule() {
  scheduleCall(() => axios.post(`/vends/${props.vendId}/freezer-controls/schedules`, { ...newSchedule.value }))
}

function toggleSchedule(entry) {
  scheduleCall(() => axios.patch(`/vends/${props.vendId}/freezer-controls/schedules/${entry.id}`, { is_active: !entry.is_active }))
}

function removeSchedule(entry) {
  if (!window.confirm(`Remove the ${entry.run_at} entry (${entry.celsius} °C)?`)) return
  scheduleCall(() => axios.delete(`/vends/${props.vendId}/freezer-controls/schedules/${entry.id}`))
}

/** The second batch of controls needs app v14 on top of everything `canSend` checks. */
const canSendBatch2 = computed(() => canSend.value && !!data.value.supported_batch2)
const batch2Blocked = computed(() => (blockedReason.value ? blockedReason.value : data.value.supported_batch2 ? '' : "This machine's app is older than v14; these controls need the update."))

/** Cameras as the machine listed them in its last status, else the ids Zijia's boards usually carry. */
const cameraChoices = computed(() => {
  const listed = status.value.cameras
  if (listed.length) return listed.map((c, i) => ({ id: c.id, label: cameraLabel(c, i) + (cameraOnline(c) ? '' : ' — offline') }))
  // Nothing reported yet: offer the ids these boards use, named by the same order.
  return [3, 4, 5].map((id, i) => ({ id, label: cameraLabel({ id }, i) }))
})

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
