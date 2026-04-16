
<template>

  <Head title="Vending Machine" />

  <BreezeAuthenticatedLayout>
      <template #header>
          <div class="flex flex-col space-y-1">
              <div class="flex space-x-2 items-center">
                  <h2 class="font-semibold text-md md:text-xl text-gray-700 leading-tight">
                      Machine ID
                  </h2>
                  <h2 class="font-semibold text-xl md:text-2xl text-gray-900 leading-tight">
                      {{ vend.code }}
                  </h2>
                  <h2 class="font-semibold text-md md:text-xl text-gray-700 leading-tight">
                      Temperature
                  </h2>
              </div>
          </div>
      </template>

      <!-- <div class="py-5"> -->
      <!-- <div class="max-w-10xl mx-auto sm:px-6 lg:px-8"> -->
      <div class="p-4 sm:px-6 lg:px-8">
          <div class="pl-1 pb-4 w-full md:w-96">
              <label class="block text-sm font-medium text-gray-700">
                  Machine
              </label>
              <MultiSelect
                  v-model="selectedVendOption"
                  :options="vendSelectionOptions"
                  valueProp="id"
                  label="label"
                  placeholder="Search by code, customer, or ref ID"
                  open-direction="bottom"
                  class="mt-1"
                  :can-clear="false"
                  @selected="onVendSelected"
              >
              </MultiSelect>
          </div>
          <div class="flex flex-col items-start pl-1">
              <h2 class="font-semibold text-md md:text-lg text-gray-700 leading-tight" v-if="vend.customer_name">
                  <span v-if="vend.vend_prefix_name">
                      {{ vend.vend_prefix_name }}-
                  </span>
                  {{ vend.code }}
              </h2>
              <h2 class="font-semibold text-md md:text-lg text-gray-700 leading-tight" v-if="vend.customer_id && vend.customer_name">
                   {{ vend.customer_id + 20000 }} - {{ vend.customer_name }}
              </h2>
          </div>
          <div class="flex space-x-2 font-semibold text-md text-gray-500 leading-tight pl-1">
              <h2>
                  {{ startDateString }}
              </h2>
              <h2>
                  to
              </h2>
              <h2>
                  {{ endDateString }}
              </h2>
          </div>
          <div class="pl-1 py-2 text-left">
              <Button
                  type="button"
                  class="border-gray-300 text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 px-7 sm:px-3"
                  @click="back()">
                  <ArrowUturnLeftIcon class="mr-2 flex-shrink-0 h-4 w-4 text-gray-400 group-hover:text-gray-500" aria-hidden="true" />
                  Back
              </Button>
          </div>
<!--
          <div class="grid grid-cols-1 md:grid-cols-3">
              <div>
                  <label for="text" class="block text-sm font-medium text-gray-700">
                      Machine ID
                  </label>
                  <MultiSelect
                      v-model="filters.vend_id"
                      :options="vendOptions"
                      valueProp="id"
                      label="code"
                      placeholder="Select"
                      open-direction="bottom"
                      class="mt-1"
                  >
                  </MultiSelect>
              </div>
          </div> -->

          <div class="pl-1 py-2 grid grid-cols-1 md:grid-cols-5 gap-2">
            <div class="col-span-5 md:col-span-5">
              <label for="text" class="pt-4 pl-1 block text-sm font-medium text-gray-700">
                  Shortcut
              </label>
              <div class="pl-1 py-2 flex space-x-2 overflow-x-scroll">
                  <Button
                      v-for="hourDurationFilter in hourDurationFilters"
                      class="border-transparent bg-indigo-600 py-2 text-sm font-medium leading-4 text-white shadow-sm hover:bg-indigo-700 px-10 sm:px-3"
                      :class="hourDurationFilter == filters.duration ? 'outline-none ring-2 ring-indigo-500 ring-offset-2' : ''"
                      @click="onDurationFilterClicked(hourDurationFilter, 'hour')">
                      {{ hourDurationFilter }} {{ hourDurationFilter > 1 ? 'Hours' : 'Hour' }}
                  </Button>
                  <Button
                      v-for="durationFilter in durationFilters"
                      class="border-transparent bg-indigo-600 py-2 text-sm font-medium leading-4 text-white shadow-sm hover:bg-indigo-700 px-10 sm:px-3"
                      :class="durationFilter == filters.duration ? 'outline-none ring-2 ring-indigo-500 ring-offset-2' : ''"
                      @click="onDurationFilterClicked(durationFilter, 'day')">
                      {{ durationFilter }} {{ durationFilter > 1 ? 'Days' : 'Day' }}
                  </Button>
              </div>
            </div>
              <DatetimePicker
                  v-model="filters.datetime_from"
                  class="col-span-5 md:col-span-2"
              >
                  From
              </DatetimePicker>
              <DatetimePicker
                  v-model="filters.datetime_to"
                  :minDate="filters.datetime_from"
                  class="col-span-5 md:col-span-2"
              >
                  To
              </DatetimePicker>
              <div class="col-span-5 flex space-x-1 mt-2">
                  <Button
                      class="border-transparent bg-green-600 py-3 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-700 px-5 sm:px-3 md:py-2 active:outline-none active:ring-2 active:ring-green-500 active:ring-offset-2"
                      @click.prevent="onCustomDatetimeSearched">

                      <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 pr-1">
                          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                      </svg>

                      <span>
                          Search
                      </span>
                  </Button>
                  <Button class="inline-flex space-x-1 items-center rounded-md border border-gray-600 bg-white px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                  @click="onExportExcelClicked()"
                  v-if="permissions.includes('export vends')"
                  >
                      <ArrowDownTrayIcon v-if="!loading" class="h-4 w-4" aria-hidden="true"/>
                      <svg v-if="loading" aria-hidden="true" class="mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                          <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                          <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                      </svg>
                      <span>
                          Export Excel
                      </span>
                  </Button>
              </div>
          </div>
          <div class="px-1 mt-2 flex flex-col">
              <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
                  <div class="inline-block min-w-full py-2 align-middle">
                      <div class="shadow-sm ring-1 ring-black ring-opacity-5">
                          <div class="flex flex-col space-y-1">
                              <div class="p-2 flex flex-wrap gap-1">
                                  <span class="inline-flex rounded-md shadow-sm" v-if="vend.temp && ('t2' in vend.parameterJson || 't3' in vend.parameterJson || 't4' in vend.parameterJson)">
                                      <span class="inline-flex items-center rounded-l-md rounded-r-md border border-gray-300 bg-white px-2 py-2">
                                      <input type="checkbox" value="1" v-model="types" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                          <label class="pl-2">T1: Machine Temp</label>
                                      </span>
                                  </span>
                                  <span class="inline-flex rounded-md shadow-sm" v-if="'t2' in vend.parameterJson && vend.parameterJson['t2'] != tempError">
                                      <span class="inline-flex items-center rounded-l-md rounded-r-md border border-gray-300 bg-white px-2 py-2">
                                      <input type="checkbox" value="2" v-model="types" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                          <label class="pl-2">T2: Evaporator Temp</label>
                                      </span>
                                  </span>
                                  <!-- <span class="inline-flex rounded-md shadow-sm " v-if="'t3' in vend.parameterJson && vend.parameterJson['t3'] != tempError"> -->
                                  <span class="inline-flex rounded-md shadow-sm ">
                                      <span class="inline-flex items-center rounded-l-md rounded-r-md border border-gray-300 bg-white px-2 py-2">
                                      <input type="checkbox" value="3" v-model="types" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                          <label class="pl-2">T3</label>
                                      </span>
                                  </span>
                                  <!-- <span class="inline-flex rounded-md shadow-sm " v-if="'t4' in vend.parameterJson && vend.parameterJson['t4'] != tempError"> -->
                                  <span class="inline-flex rounded-md shadow-sm ">
                                      <span class="inline-flex items-center rounded-l-md rounded-r-md border border-gray-300 bg-white px-2 py-2">
                                      <input type="checkbox" value="4" v-model="types" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                          <label class="pl-2">T4</label>
                                      </span>
                                  </span>
                                  <span class="inline-flex rounded-md shadow-sm " v-if="'fan' in vend.parameterJson">
                                      <span class="inline-flex items-center rounded-l-md rounded-r-md border border-gray-300 bg-white px-2 py-2">
                                      <input type="checkbox" value="1" v-model="fans" :disabled="!vend.is_fan_enabled" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-50" />
                                          <label class="pl-2" :class="[!vend.is_fan_enabled ? 'text-gray-400' : '']">Fan</label>
                                      </span>
                                  </span>
                                  <span class="inline-flex rounded-md shadow-sm ">
                                      <span class="inline-flex items-center rounded-l-md rounded-r-md border border-gray-300 bg-white px-2 py-2">
                                      <input type="checkbox" v-model="showMarkers" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                          <label class="pl-2">Show Alert Markers</label>
                                      </span>
                                  </span>
                              </div>
                              <div class="flex space-x-2 px-2">
                                <div
                                    class="inline-flex justify-center items-center rounded px-2 py-2 text-xs font-medium border w-fit"
                                    :class="[vend.is_active || vend.is_testing ? (vend.is_online ? 'bg-green-200' : 'bg-red-200') : 'bg-gray-200 text-gray-400']"
                                >
                                    <div class="flex flex-col">
                                            <span class="font-bold">
                                                    {{vend.is_online ? 'Online' : 'Offline'}}
                                            </span>
                                            <span v-if="vend.last_updated_at">
                                                    {{vend.last_updated_at}}
                                            </span>
                                    </div>
                                </div>
                                <div
                                    class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-fit"
                                    :class="[vend.is_active || vend.is_testing ? (vend.is_mqtt_active ? 'bg-green-200' : 'bg-gray-200') : 'bg-gray-200 text-gray-400']"
                                    v-if="vend.is_mqtt"
                                >
                                    <div class="flex flex-col">
                                        <span class="font-bold">
                                            MQTT
                                        </span>
                                        <span v-if="vend.mqtt_last_updated_at">
                                            {{ vend.mqtt_last_updated_at }}
                                        </span>
                                    </div>
                                </div>
                                <div
                                    class="inline-flex justify-center items-center rounded px-2 py-2 text-xs font-medium border w-fit"
                                    :class="[vend.is_active || vend.is_testing ? (vend.parameterJson['door'] == 'close' ? 'bg-green-200' : 'bg-red-200') : 'bg-gray-200 text-gray-400']"
                                    v-if="vend.parameterJson && vend.parameterJson['door']"
                                >
                                  <div class="flex flex-col">
                                      <span class="font-bold">
                                          Door
                                      </span>
                                      <span>
                                          {{vend.parameterJson['door'] == 'open' ? 'Open' : 'Close'}}
                                      </span>
                                  </div>
                                </div>
                              </div>
                          </div>
                          <Graph
                              :key="componentKey"
                              type="line"
                              :labels="labels"
                              :datasets="datasets"
                              :options="graphOptions"
                          ></Graph>
                      </div>
                  </div>
              </div>
          </div>
      </div>
      <!-- </div> -->
      <!-- </div> -->
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import DatetimePicker from '@/Components/DatetimePicker.vue';
import Graph from '@/Components/Graph.vue';
import { ArrowDownTrayIcon, ArrowUturnLeftIcon } from '@heroicons/vue/20/solid'
import { computed, ref, onBeforeMount, watch } from 'vue';
import { Head, router, usePage, usePoll } from '@inertiajs/vue3';
import moment from 'moment';
import { useToast } from "vue-toastification";

const toast = useToast();

const props = defineProps({
duration: [Number, String],
endDate: String,
endDateString: String,
request: Object,
startDate: String,
startDateString: String,
type: [String, Object, Array],
tempError: [Number, String],
fans: [String, Object, Array],
vendObj: Object,
vendTempsObj: Object,
vendFansObj: Object,
vendAlertLogsObj: [Array, Object],
vendOptions: [Array, Object],
});

// usePoll(2000, {
//   only: ['vendTempsObj'],
// });

const hourDurationFilters = ref([6, 12])
const durationFilters = ref([1, 2, 5, 7, 14])
const filters = ref({
datetime_from: props.startDate ? props.startDate : moment().format('YYYY-MM-DD HH:mm:ss'),
datetime_to: props.endDate ? props.endDate : moment().format('YYYY-MM-DD HH:mm:ss'),
duration: props.duration,
})
const firstLoad = ref(true); // Tracks whether it's the first time loading
const labels = ref([])
const datasets = ref([])
const permissions = usePage().props.auth.permissions
const vend = ref(props.vendObj.data)
const selectedVendOption = ref(null);
const vendSelectionOptions = computed(() => {
  return (props.vendOptions ?? []).map((option) => {
    const virtualCustomer = option.virtual_customer_prefix && option.virtual_customer_code
      ? `${option.virtual_customer_prefix}-${option.virtual_customer_code}`
      : null;
    const segments = [
      option.code,
      option.customer_name,
      virtualCustomer,
      option.customer_ref_id ? ` #${option.customer_ref_id}` : null,
    ].filter(Boolean);

    return {
      ...option,
      label: segments.join(' • '),
    };
  });
});
const vendTemps = ref()
const vendFans = ref()
const types = ref([1, 2]) // Default to [1, 2]
const fans = ref(props.fans ? props.fans : [])
const componentKey = ref(0);
const loading = ref(false)
const showMarkers = ref(true)

// Map alert_type -> short dashboard label
const ALERT_TYPE_LABEL = {
  'connectivity':        '1',
  'comp_fan_off':        '2A',
  'temps_above_0':       '2B',
  'temps_above_minus_8': '2C',
  'not_reach_minus_18':  '2D',
  'lowest_24h_above':    '3A',
  'lowest_72h_above':    '3B',
  'rising_t1_trend':     '3C',
  'rising_t2_trend':     '3C',
  't2_frozen':           '3D',
  'temps_above_minus_17_upward': '2E',
}

// Map alert_type -> human readable title
const ALERT_TYPE_TITLE = {
  'connectivity':        'Connectivity',
  'comp_fan_off':        'Compressor & or Fan, in OFF condition',
  'temps_above_0':       'T1 & or T2, above 0°C',
  'temps_above_minus_8': 'T1 & or T2, above -8°C',
  'not_reach_minus_18':  'T1 & or T2, did not reach -18°C',
  'lowest_24h_above':    'T1 & T2 lowest (last 24hrs)',
  'lowest_72h_above':    'T1 & T2 lowest (last 72hrs)',
  'rising_t1_trend':     'Rising lowest T1 and T2 (Last 24hrs vs Last 48hrs)',
  'rising_t2_trend':     'Rising lowest T1 and T2 (Last 24hrs vs Last 48hrs)',
  't2_frozen':           'T2, never above 2°C',
  'temps_above_minus_17_upward': 'T1 or T2 above -17°C and upward trending',
}

// Map alert_type -> duration offset in minutes (to show when the event actually started)
const ALERT_TYPE_OFFSET_MINUTES = {
  'connectivity':        15,
  'comp_fan_off':        40,
  'temps_above_0':       30,
  'temps_above_minus_8': 60,
  'not_reach_minus_18':  480, // 8 hours
  'temps_above_minus_17_upward': 30, // Default to Tier 1
}

function buildAnnotations() {
  if (!showMarkers.value) return {}
  const logs = props.vendAlertLogsObj ?? []
  const annotations = {}
  const activeAlertTypes = {}
  const hasSeenLogForType = {}
  let markerCount = 0

  logs.forEach((log, idx) => {
    const isTriggered = log.event === 'machine_health_alert'
    const isDismissed = log.event === 'machine_health_alert_dismissed'
    const alertType = log.alert_type
    const shortLabel = ALERT_TYPE_LABEL[alertType]
    const alertTitle = ALERT_TYPE_TITLE[alertType] || ''

    let displaySubject = log.subject
    if (isDismissed) {
      if (log.context?.bucket && alertTitle) {
        displaySubject = `${alertTitle} (${log.context.bucket})`
      } else {
        displaySubject = log.subject.replace(/\[?Dismissed\]?\s*/i, alertTitle ? alertTitle + ' ' : '').trim()
      }
    } else {
      // It's triggered
      if (log.context?.bucket && alertTitle) {
        displaySubject = `${alertTitle} (${log.context.bucket})`
      } else if (alertTitle) {
        // Fallback: if we just don't have a bucket, at least show the proper full title
        displaySubject = alertTitle
      }
    }

    if (!shortLabel) return

    // Skip 2A (comp_fan_off) markers for machines without a fan:
    //  - N/A  → !vend.is_fan_enabled (Fan Signal Disabled)
    //  - "--"  → 'fan' key missing from parameterJson (Fan Signal Missing)
    if (alertType === 'comp_fan_off') {
      const hasFanEnabled = vend.value && vend.value.is_fan_enabled
      const hasFanKey = vend.value && vend.value.parameterJson && 'fan' in vend.value.parameterJson
      if (!hasFanEnabled || !hasFanKey) return
    }

    if (isTriggered) {
      if (hasSeenLogForType[shortLabel] && activeAlertTypes[shortLabel]) {
        return // Ignore tier escalations, only show initial trigger in timeframe
      }
      activeAlertTypes[shortLabel] = true
    } else if (isDismissed) {
      if (hasSeenLogForType[shortLabel] && !activeAlertTypes[shortLabel]) {
        return // Already dismissed
      }
      activeAlertTypes[shortLabel] = false
    }
    hasSeenLogForType[shortLabel] = true

    const currentMarkerIdx = markerCount++
    const color = isTriggered ? 'rgba(220,38,38,0.85)' : 'rgba(22,163,74,0.85)'
    const borderColor = isTriggered ? '#dc2626' : '#16a34a'

    // Adjust Triggered markers to show when the event started
    let markerValue = log.occurred_at
    let tooltipTimeLabel = isTriggered ? 'Triggered At' : 'Dismissed At'
    let eventStartedAt = null

    if (isTriggered) {
      // Priority 1: Use explicit 'started_at' from log context (new behavior)
      if (log.context?.started_at) {
        markerValue = moment(log.context.started_at).format('YYYY-MM-DD HH:mm:ss');
        tooltipTimeLabel = 'Event Started At';
        eventStartedAt = markerValue;
      } else {
        // Priority 2: Use hardcoded offset (legacy behavior)
        const offset = ALERT_TYPE_OFFSET_MINUTES[alertType] || 0;
        if (offset > 0) {
          const baseTime = log.context?.triggered_at || log.occurred_at;
          markerValue = moment(baseTime).subtract(offset, 'minutes').format('YYYY-MM-DD HH:mm:ss');
          tooltipTimeLabel = 'Event Started At';
          eventStartedAt = markerValue;
        }
      }
    }

    annotations[`alert_${idx}`] = {
      type: 'line',
      scaleID: 'x',
      value: markerValue,
      borderColor: borderColor,
      borderWidth: 2,
      hoverBorderWidth: 4,
      borderDash: isTriggered ? [] : [4, 3],
      clip: false,
      label: {
        display: true,
        content: shortLabel,
        position: 'start',
        backgroundColor: color,
        color: '#fff',
        font: { size: 10, weight: 'bold' },
        padding: { x: 4, y: 2 },
        borderRadius: 3,
        yAdjust: currentMarkerIdx % 2 === 0 ? 0 : 18,
        textAlign: 'center',
        zIndex: 1,
      },
      enter(ctx) {
        ctx.chart.canvas.style.cursor = 'pointer'
        const timeDisplay = `${tooltipTimeLabel}: ${moment(markerValue).format('YYYY-MM-DD HH:mm:ss')}`
        const triggerTime = moment(log.context?.triggered_at || log.occurred_at).format('YYYY-MM-DD HH:mm:ss')
        const triggerDisplay = isTriggered ? `\n(Triggered At: ${triggerTime})` : ''

        ctx.element.options.label.content = `[${shortLabel}] ${displaySubject}\n${timeDisplay}${triggerDisplay}`
        ctx.element.options.label.backgroundColor = isTriggered ? '#991b1b' : '#15803d'
        ctx.element.options.label.font.size = 12
        ctx.element.options.label.padding = { x: 8, y: 6 }
        ctx.element.options.label.yAdjust = -10
        ctx.element.options.label.zIndex = 100
        ctx.chart.update('none')
      },
      leave(ctx) {
        ctx.chart.canvas.style.cursor = 'default'
        ctx.element.options.label.content = shortLabel
        ctx.element.options.label.backgroundColor = color
        ctx.element.options.label.font.size = 10
        ctx.element.options.label.padding = { x: 4, y: 2 }
        ctx.element.options.label.yAdjust = currentMarkerIdx % 2 === 0 ? 0 : 18
        ctx.element.options.label.zIndex = 1
        ctx.chart.update('none')
      },
      click(ctx) {
        const timeDisplay = `${tooltipTimeLabel}: ${moment(markerValue).format('YYYY-MM-DD HH:mm:ss')}`
        const triggerTime = moment(log.context?.triggered_at || log.occurred_at).format('YYYY-MM-DD HH:mm:ss')
        const triggerDisplay = isTriggered ? `\n(Triggered At: ${triggerTime})` : ''
        const message = `[${shortLabel}] ${displaySubject}\n${timeDisplay}${triggerDisplay}`
        const config = {
          timeout: 7500,
          position: "bottom-right"
        }
        if (isTriggered) {
            toast.error(message, config)
        } else {
            toast.success(message, config)
        }
      },
    }
  })
  return annotations
}
const graphOptions = ref({
scales: {
  x: {
    type: 'time',
    time: {
      displayFormats: {
        hour: 'ha (DD)'
      },
      tooltipFormat: 'YYMMDD hh:mma',
    }
  },
  y: {
    type: 'linear',
    display: true,
    position: 'left',
    ticks: {
      callback: function(value, index, values) {
        return value + '°C';
      }
    }
  },
  y1: {
    type: 'linear',
    display: true,
    position: 'right',
    min: 0,
    grid: {
      drawOnChartArea: false,
    },
  },
},
plugins: {
  title: {
    display: true,
    text: '#' + vend.value.code + ' (' + (vend.value.customer_id ? (vend.value.customer_id + 20000) + (vend.value.vend_prefix_name ? ' (' + vend.value.vend_prefix_name + ')' : '') + ' - ' + vend.value.customer_name : vend.value.customer_name) + ')'
  },
  tooltip: {
    callbacks: {
      label: function(context) {
        var label = context.dataset.label.slice(0, 2) || '';
        if (label) {
          label += ': ';
        }
        if (context.parsed.y !== null) {
          label += context.parsed.y + (context.dataset.yAxisID == 'y' ? '°C' : ' RPM');
        }
        return label;
      }
    }
  },
  legend: {
    labels: {
      padding: 10,
      color: '#333',
      font: {
        size: 14,
      }
    },
  },
  annotation: {
    annotations: buildAnnotations(),
  },
},
interaction: {
  mode: 'nearest',
  intersect: false,
  axis: 'xy',
},
events: ['mousemove', 'mouseout', 'click', 'touchstart', 'touchmove'],
})

watch(() => props.vendObj.data, (newVend) => {
  if (newVend) {
    vend.value = newVend;
  }
}, { immediate: true });

watch(
  () => [vendSelectionOptions.value, vend.value ? vend.value.id : null],
  ([options, currentVendId]) => {
    if (!currentVendId) {
      selectedVendOption.value = null;
      return;
    }

    const match = options.find((option) => option.id === currentVendId);
    selectedVendOption.value = match ?? null;
  },
  { immediate: true }
);

const forceRerender = () => {
componentKey.value += 1;
};

onBeforeMount(() => {
getVendTempsData()
})

watch(types, async (newTypes, oldTypes) => {
    if (JSON.stringify(newTypes) === JSON.stringify(oldTypes)) return; // Prevent unnecessary reloads

    router.visit(
        route('temp', {
            id: vend.value.id,
            type: props.type.value,
            types: newTypes,
            ...filters.value,
        }), {
            only: ['vendTempsObj'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onSuccess: (page) => {
                getVendTempsData(); // Remove router.reload() to avoid loops
            },
        }
    );
});


watch(fans, async (newFans, oldFans) => {
router.visit(
  route('temp', {
    id: vend.value.id,
    type: props.type.value,
    types: types.value,
    fans: newFans,
    ...filters.value,
  }), {
    only: ['vendTempsObj'],
    preserveState: true,
    preserveScroll: true,
    replace: true,
    onSuccess: (page) => {
      router.reload({
        only: ['vendTempsObj'],
        preserveState: true,
        preserveScroll: true,
      })
      getVendTempsData()
    },
  }
);
})

// watch(() => props.vendTempsObj, () => {
//   getVendTempsData();
// });

function onCustomDatetimeSearched() {
router.get(
  '/vends/' +
  vend.value.id +
  '/temp/' +
  props.type.value, {...filters.value, types: types.value, fans: fans.value}, {
    preserveScroll: true,
  }
)
}

function onDurationFilterClicked(duration, durationType) {
router.get('/vends/' + vend.value.id + '/temp/' + props.type.value + '?duration=' + duration + '&durationType=' + durationType)
}

function back() {
  if (window.history.length > 1) {
    window.history.back();
  } else {
    router.visit('/vends')
  }
}

function onVendSelected(option) {
  if (!option || option.id === vend.value.id) {
    return;
  }

  const query = {
    ...filters.value,
    types: types.value,
  };

  if (fans.value && Array.isArray(fans.value) && fans.value.length) {
    query.fans = fans.value;
  }

  if (props.request && props.request.durationType) {
    query.durationType = props.request.durationType;
  }

  router.get(
    route('temp', {
      id: option.id,
      type: props.type.value,
    }),
    query,
    {
      preserveScroll: true,
    },
  );
}

function getVendTempsData() {
  const colors = ['#E6676B', '#36a2eb', '#cc65fe', '#ffce56']
  const vendTempsAllArr = props.vendTempsObj.data
  const vendFansAllArr = props.vendFansObj.data
  const vendTempsArr = []
  const vendFansArr = []
  const lastTempValue = []
  const lowest = []
  const highest = []

  if (firstLoad.value) {
    updateTemperatureTypes(vendTempsAllArr);
  }

  const nowMomentLabel = moment();
  const nowTs = nowMomentLabel.valueOf();

  if (types.value.length > 0 || fans.value.length > 0) {
    types.value.forEach((type) => {
      const vendTempsDataByType = vendTempsAllArr.filter(vt => vt.type == type);
      if (vendTempsDataByType.length === 0) return;

      // Add numeric timestamp for faster processing/sorting
      vendTempsDataByType.forEach(vt => {
        vt.ts = vt.ts || moment(vt.created_at).valueOf();
        const val = parseFloat(vt.value);
        if (!isNaN(val)) {
          if (lowest[type] === undefined || lowest[type] > val) lowest[type] = val;
          if (highest[type] === undefined || highest[type] < val) highest[type] = val;
        }
      });

      vendTempsDataByType.sort((a, b) => a.ts - b.ts);

      lastTempValue[type] = vendTempsDataByType[vendTempsDataByType.length - 1].value;
      const resultArr = [...vendTempsDataByType];
      const FIVE_MINS_MS = 5 * 60 * 1000;

      // Find gaps and fill them
      for (let i = 1; i < vendTempsDataByType.length; i++) {
        const prev = vendTempsDataByType[i - 1];
        const curr = vendTempsDataByType[i];
        if (curr.ts - prev.ts > FIVE_MINS_MS + 1000) { // Gap > 5 mins (plus small buffer)
          let gapTs = prev.ts + FIVE_MINS_MS;
          while (curr.ts - gapTs > FIVE_MINS_MS) {
            resultArr.push({
              value: 'NaN',
              created_at: moment(gapTs).format(),
              ts: gapTs,
              type: type,
            });
            gapTs += FIVE_MINS_MS;
          }
        }
      }

      // Add trailing NaNs if needed
      const lastPoint = resultArr[resultArr.length - 1];
      const endTs = moment(filters.value.datetime_to).valueOf();
      if (endTs > lastPoint.ts) {
        let gapTs = lastPoint.ts + FIVE_MINS_MS;
        while (endTs - gapTs >= 0) {
          resultArr.push({
            value: 'NaN',
            created_at: moment(gapTs).format('YYYY-MM-DD HH:mm:ss'),
            ts: gapTs,
            type: type,
          });
          gapTs += FIVE_MINS_MS;
        }
      }

      resultArr.sort((a, b) => a.ts - b.ts);
      vendTempsArr[type] = resultArr;
    });
    vendTemps.value = vendTempsArr;

    fans.value.forEach((type) => {
      const vendFansDataByType = vendFansAllArr.filter(vf => vf.type == type);
      if (vendFansDataByType.length === 0) return;

      vendFansDataByType.forEach(vf => vf.ts = vf.ts || moment(vf.created_at).valueOf());

      vendFansDataByType.sort((a, b) => a.ts - b.ts);

      const resultArr = [...vendFansDataByType];
      const FIVE_MINS_MS = 5 * 60 * 1000;

      for (let i = 1; i < vendFansDataByType.length; i++) {
        const prev = vendFansDataByType[i - 1];
        const curr = vendFansDataByType[i];
        if (curr.ts - prev.ts > FIVE_MINS_MS + 1000) {
          let gapTs = prev.ts + FIVE_MINS_MS;
          while (curr.ts - gapTs > FIVE_MINS_MS) {
            resultArr.push({
              value: 'NaN',
              created_at: moment(gapTs).format(),
              ts: gapTs,
              type: type,
            });
            gapTs += FIVE_MINS_MS;
          }
        }
      }

      const lastPoint = resultArr[resultArr.length - 1];
      const endTs = moment(filters.value.datetime_to).valueOf();
      if (endTs > lastPoint.ts) {
        let gapTs = lastPoint.ts + FIVE_MINS_MS;
        while (endTs - gapTs >= 0) {
          resultArr.push({
            value: 'NaN',
            created_at: moment(gapTs).format('YYYY-MM-DD HH:mm:ss'),
            ts: gapTs,
            type: type,
          });
          gapTs += FIVE_MINS_MS;
        }
      }

      resultArr.sort((a, b) => a.ts - b.ts);
      vendFansArr[type] = resultArr;
    });
    vendFans.value = vendFansArr;

  if (vendTemps.value.length > 0) {
    let allTimings = []
    datasets.value = []
    vendTemps.value.forEach((vendTemp, vendTempIndex) => {
      datasets.value.push({
        label: 'T' + vendTempIndex + (lastTempValue[vendTempIndex] ? (' (' + lastTempValue[vendTempIndex] + "\u2103" + ')' ) : '') + ' [ ' + ('H: ' + highest[vendTempIndex] + "\u2103" + ' L: ' + lowest[vendTempIndex] + "\u2103") + ' ] ' + (vend.value.parameterJson && 'fan' in vend.value.parameterJson ? ('Fan: ' + (vend.value.is_fan_enabled && vend.value.parameterJson['fan'] !== null && vend.value.parameterJson['fan'] !== undefined && vend.value.parameterJson['fan'] !== 'NaN' ? vend.value.parameterJson['fan'] : '--')) : ''),
        data: vendTemp.map((temp) => { return { x: temp.ts, y: temp.value } }),
        borderColor: colors[vendTempIndex - 1],
        backgroundColor: colors[vendTempIndex - 1],
        tension: 0.1,
        spanGaps: true,
        yAxisID: 'y',
        normalized: true,
      })
      allTimings.push(vendTemp)
    })
  }
  if (vendFans.value.length > 0 && vend.value.is_fan_enabled) {
    let fanColors = ['#808080']
    vendFans.value.forEach((vendFan, vendFanIndex) => {
      datasets.value.push({
        label: 'Fan Speed' + (vend.value.parameterJson['fan'] !== null && vend.value.parameterJson['fan'] !== undefined ? (' (' + vend.value.parameterJson['fan'] + ')' ) : ''),
        data: vendFan.map((fan) => {
          let yVal = parseFloat(fan.value)
          if(isNaN(yVal) || yVal === 0) {
            yVal = null
          }
          return { x: fan.ts, y: yVal }
        }),
        borderColor: fanColors[0],
        backgroundColor: fanColors[0],
        tension: 0.1,
        spanGaps: false,
        yAxisID: 'y1',
        normalized: true,
      })
    })
  }

  // Rebuild annotations in case alert logs changed
  graphOptions.value.plugins.annotation.annotations = buildAnnotations()
  forceRerender()
}
}

function onExportExcelClicked() {
loading.value = true

axios({
  method: 'get',
  url: '/vends/' + vend.value.id + '/temp/' + props.type.value + '/excel',
  params: {
    ...filters.value,
    types: types.value,
  },
  responseType: 'blob',
}).then(response => {
  fileDownload(response.data, 'Vending_Temp_' + moment().format('YYMMDDhhmmss') + '.xlsx')
  loading.value = false
})
}

function updateTemperatureTypes(tempData) {
    if (!tempData || !tempData.length) return;

    const typesSet = new Set();
    for (let i = 0; i < tempData.length; i++) {
        typesSet.add(tempData[i].type);
    }
    const availableTypes = Array.from(typesSet);

    if (firstLoad.value) {
        types.value = availableTypes;
        firstLoad.value = false;
    } else {
        availableTypes.forEach(type => {
            if (!types.value.includes(type)) {
                types.value.push(type);
            }
        });
    }
}

watch(showMarkers, () => {
  graphOptions.value.plugins.annotation.annotations = buildAnnotations()
  forceRerender()
})

</script>
