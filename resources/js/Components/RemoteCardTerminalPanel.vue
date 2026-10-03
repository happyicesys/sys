<template>
  <!--
    Setting/Edit > Smart Freezer > Remote card terminal (T05 · Payrallel). RemoteCardTerminalController.

    Read-only status. A T05 is a Data Management > Card Terminal unit (SN + access token) under the
    company Payrallel (T05); choosing it in the Card Terminal picker on this page and saving switches
    this freezer's card payments to it (app v25+, within 5 minutes). Choosing another terminal, or
    none, switches it back to the wired reader. Unless the Payrallel (T05) company allows a terminal
    to serve several machines, binding it here takes it off any other freezer.
  -->
  <div class="sm:col-span-6">
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
      <header class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 bg-gray-50 px-4 py-3">
        <div class="flex flex-wrap items-center gap-2">
          <h3 class="text-sm font-semibold text-gray-900">Remote card terminal (T05 · Payrallel)</h3>
          <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset" :class="badge.cls">
            <span class="h-1.5 w-1.5 rounded-full" :class="badge.dot"></span>
            {{ badge.text }}
          </span>
          <span v-if="terminal?.is_active && terminal?.last_status_at" class="text-xs text-gray-500">checked {{ ago(terminal.last_status_at) }}</span>
        </div>
        <Button v-if="terminal?.is_active" type="button" class="bg-sky-700 hover:bg-sky-800 text-white"
                :disabled="busy" @click.prevent="check">
          <ArrowPathIcon class="mr-1 h-4 w-4" :class="busy ? 'animate-spin' : ''" />
          Check terminal
        </Button>
      </header>

      <div class="space-y-2 px-4 py-3 text-sm">
        <div v-if="terminal?.is_active" class="text-gray-800">
          <span class="font-medium">SN {{ terminal.sn || terminal.label || '—' }}</span>
          <span v-if="terminal.from_command" class="ml-2 text-xs text-amber-700">
            bound with the command line — register this T05 under Data Management → Card Terminal and pick it below
          </span>
        </div>
        <p class="text-xs text-gray-500">
          To use a T05 on this freezer, pick it in <span class="font-medium">Card Terminal (Terminal ID)</span> above and save.
          Pick another terminal, or Clear, to go back to the wired card reader. The freezer follows within 5 minutes.
          The T05's SN and access token are kept under Data Management → Card Terminal.
        </p>
        <div v-if="errors" class="text-sm text-red-600">{{ errors }}</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import Button from '@/Components/Button.vue'
import { ArrowPathIcon } from '@heroicons/vue/20/solid'
import moment from 'moment'
import { computed, onMounted, ref, watch } from 'vue'

const props = defineProps({
  vendId: { type: Number, required: true },
  // The machine's bound Card Terminal unit; the panel re-reads when a save changes it.
  boundUnitId: { type: [Number, String, null], default: null },
})

const terminal = ref(null)
const busy = ref(false)
const errors = ref('')

const badge = computed(() => {
  const t = terminal.value
  if (!t || !t.is_active) return { text: 'not in use', cls: 'bg-gray-100 text-gray-600 ring-gray-300', dot: 'bg-gray-400' }
  if (t.last_online === false) return { text: 'bound · offline', cls: 'bg-red-50 text-red-700 ring-red-200', dot: 'bg-red-500' }
  if (t.last_online === true) {
    return { text: `bound · ${t.last_state || 'online'}`, cls: 'bg-emerald-50 text-emerald-700 ring-emerald-200', dot: 'bg-emerald-500' }
  }
  return { text: 'bound · not checked yet', cls: 'bg-amber-50 text-amber-700 ring-amber-200', dot: 'bg-amber-500' }
})

function ago(iso) {
  return iso ? moment(iso).fromNow() : ''
}

async function call(request) {
  busy.value = true
  errors.value = ''
  try {
    terminal.value = (await request()).data.terminal
  } catch (e) {
    errors.value = e.response?.data?.message || 'Could not read the terminal status'
  } finally {
    busy.value = false
  }
}

function check() {
  call(() => axios.post(`/vends/${props.vendId}/remote-card-terminal/check`))
}

const load = () => call(() => axios.get(`/vends/${props.vendId}/remote-card-terminal`))
onMounted(load)
watch(() => props.boundUnitId, load)
</script>
