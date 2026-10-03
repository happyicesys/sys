<template>
  <!--
    Setting/Edit > Smart Freezer > Remote card terminal (T05 · Payrallel). RemoteCardTerminalController.

    Bind = paste the terminal's Payrallel access token (write-only: stored encrypted, never shown
    again) and save; Deactivate = the freezer goes back to its wired MDB/NETS reader. The freezer
    (app v25+) follows within 5 minutes, or at once on Recheck in its setup page. Unless Data
    Management > Card Terminal Company > Payrallel (T05) allows a terminal to serve several machines,
    binding a terminal takes it off any other freezer that had it.
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
          <span v-if="terminal?.last_status_at" class="text-xs text-gray-500">checked {{ ago(terminal.last_status_at) }}</span>
        </div>
        <Button v-if="terminal?.is_active" type="button" class="bg-sky-700 hover:bg-sky-800 text-white"
                :disabled="busy" @click.prevent="check">
          <ArrowPathIcon class="mr-1 h-4 w-4" :class="busy === 'check' ? 'animate-spin' : ''" />
          Check terminal
        </Button>
      </header>

      <div class="space-y-3 px-4 py-3 text-sm">
        <p class="text-xs text-gray-500">
          Card payments on this freezer go through the T05 while it is bound here; otherwise the freezer uses its
          wired card reader (or QR only). The freezer follows a change within 5 minutes.
        </p>

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-6">
          <div class="sm:col-span-2">
            <label class="block text-xs font-medium text-gray-700">Label (e.g. T05 serial)</label>
            <input v-model="form.label" type="text" maxlength="64" :disabled="!canEdit"
                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100" />
          </div>
          <div class="sm:col-span-4">
            <label class="block text-xs font-medium text-gray-700">
              Payrallel access token
              <span v-if="terminal?.has_token" class="font-normal text-gray-500">— set; leave blank to keep it</span>
            </label>
            <input v-model="form.access_token" type="password" autocomplete="new-password" :disabled="!canEdit"
                   :placeholder="terminal?.has_token ? '•••••••• (stored, not shown)' : 'Paste the token Payrallel issued for this terminal'"
                   class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100" />
          </div>
        </div>

        <div v-if="errors" class="text-sm text-red-600">{{ errors }}</div>
        <div v-if="notice" class="rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800 ring-1 ring-inset ring-amber-200">{{ notice }}</div>

        <div v-if="canEdit" class="flex flex-wrap justify-end gap-2">
          <Button v-if="terminal?.is_active" type="button" class="bg-red-600 hover:bg-red-700 text-white"
                  :disabled="busy" @click.prevent="save(false)">
            Deactivate
          </Button>
          <Button type="button" class="bg-green-600 hover:bg-green-700 text-white"
                  :disabled="busy" @click.prevent="save(true)">
            {{ terminal?.is_active ? 'Save' : 'Bind T05' }}
          </Button>
        </div>
        <p v-else class="text-xs text-gray-500">Binding needs the "update card-terminals" permission.</p>
      </div>
    </div>
  </div>
</template>

<script setup>
import Button from '@/Components/Button.vue'
import { ArrowPathIcon } from '@heroicons/vue/20/solid'
import { usePage } from '@inertiajs/vue3'
import moment from 'moment'
import { computed, onMounted, ref } from 'vue'

const props = defineProps({
  vendId: { type: Number, required: true },
})

const page = usePage()
const canEdit = computed(() => {
  const p = page.props.auth?.permissions ?? []
  return p.includes('update machine-settings') && p.includes('update card-terminals')
})

const terminal = ref(null)
const form = ref({ label: '', access_token: '' })
const busy = ref(null)
const errors = ref('')
const notice = ref('')

const badge = computed(() => {
  const t = terminal.value
  if (!t || !t.is_active) return { text: 'not bound', cls: 'bg-gray-100 text-gray-600 ring-gray-300', dot: 'bg-gray-400' }
  if (t.last_online === false) return { text: 'bound · offline', cls: 'bg-red-50 text-red-700 ring-red-200', dot: 'bg-red-500' }
  if (t.last_online === true) {
    return { text: `bound · ${t.last_state || 'online'}`, cls: 'bg-emerald-50 text-emerald-700 ring-emerald-200', dot: 'bg-emerald-500' }
  }
  return { text: 'bound · not checked yet', cls: 'bg-amber-50 text-amber-700 ring-amber-200', dot: 'bg-amber-500' }
})

function ago(iso) {
  return iso ? moment(iso).fromNow() : ''
}

function apply(data) {
  terminal.value = data.terminal
  form.value = { label: data.terminal?.label ?? '', access_token: '' }
  const released = data.released_from ?? []
  notice.value = released.length ? `Taken off ${released.join(', ')} — one T05 serves one machine (Card Terminal Company setting).` : ''
}

async function call(kind, request) {
  busy.value = kind
  errors.value = ''
  try {
    apply((await request()).data)
  } catch (e) {
    const bag = e.response?.data?.errors
    errors.value = bag ? Object.values(bag).flat().join(' ') : (e.response?.data?.message || 'Request failed')
  } finally {
    busy.value = null
  }
}

function save(active) {
  if (!active && !window.confirm('Deactivate the T05 on this freezer? It goes back to its wired card reader within 5 minutes.')) return
  call('save', () => axios.put(`/vends/${props.vendId}/remote-card-terminal`, {
    label: form.value.label || null,
    access_token: form.value.access_token || null,
    is_active: active,
  }))
}

function check() {
  call('check', () => axios.post(`/vends/${props.vendId}/remote-card-terminal/check`))
}

onMounted(() => call('load', () => axios.get(`/vends/${props.vendId}/remote-card-terminal`)))
</script>
