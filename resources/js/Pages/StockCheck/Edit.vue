<template>
  <Head :title="check.display_code" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex flex-wrap items-center gap-2">
        <ClipboardDocumentListIcon class="h-5 w-5 text-cyan-700" />
        <span class="text-gray-600">Stock Count 盘点</span>
        <span class="font-semibold">{{ check.display_code }}</span>
        <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium border" :class="stopStatusClass(check.status)">
          {{ check.status_name }}
        </span>
      </div>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 space-y-3">
      <!-- ── Header ─────────────────────────────────────────── -->
      <div class="bg-white shadow-sm ring-1 ring-black ring-opacity-5 rounded-lg p-4 grid grid-cols-1 sm:grid-cols-6 gap-3 text-sm">
        <div class="sm:col-span-2">
          <div class="text-xs text-gray-500">Machine</div>
          <div class="font-semibold text-gray-900">{{ check.vend_code }}</div>
          <div class="text-gray-700">{{ check.customer_name }}</div>
        </div>
        <div class="sm:col-span-2">
          <div class="text-xs text-gray-500">Daily Job</div>
          <Link :href="'/ops-jobs/' + check.ops_job_id + '/edit'" class="text-blue-600 underline">
            {{ check.ops_job?.date_label }} · #{{ check.ops_job?.code }}
          </Link>
          <div class="text-gray-700">{{ check.ops_job?.delivered_by_name || 'Unassigned' }}</div>
        </div>
        <div class="sm:col-span-2">
          <div class="text-xs text-gray-500">Sample</div>
          <div class="text-gray-900">
            {{ check.channels_count }} channel(s) ·
            <span v-if="check.is_random">random {{ check.sample_size }}</span>
            <span v-else>all stocked</span>
            <span v-if="check.product_filter.length"> · filtered by product</span>
          </div>
          <div class="text-xs text-gray-500">Opened {{ check.created_at }}<span v-if="check.created_by_name"> ({{ check.created_by_name }})</span></div>
        </div>
        <div class="sm:col-span-6">
          <label class="text-xs text-gray-500">Remarks</label>
          <input
            type="text" v-model="remarks" :disabled="!canUpdate"
            class="mt-1 shadow-sm focus:ring-cyan-500 focus:border-cyan-500 block w-full text-sm border-gray-300 rounded-md"
            @change="saveRemarks"
          />
        </div>
      </div>

      <div class="rounded-md bg-red-50 border border-red-200 text-red-700 text-sm px-3 py-2" v-if="pageError">{{ pageError }}</div>

      <!-- ── Result summary (after the count) ───────────────── -->
      <div v-if="isCounted" class="rounded-lg border px-4 py-3 text-sm" :class="check.mismatch_count ? 'bg-red-50 border-red-200' : 'bg-green-50 border-green-200'">
        <div class="font-semibold" :class="check.mismatch_count ? 'text-red-800' : 'text-green-800'">
          <template v-if="check.mismatch_count">
            {{ check.mismatch_count }} of {{ check.channels_count }} channel(s) do not match ·
            {{ signed(check.variance_qty) }} pcs · {{ money(check.variance_value) }}
          </template>
          <template v-else>All {{ check.channels_count }} channel(s) match the system.</template>
        </div>
        <div class="text-xs text-gray-600">
          Counted {{ check.counted_at }}<span v-if="check.counted_by_name"> by {{ check.counted_by_name }}</span>
          <span v-if="check.synced_at"> · Synced {{ check.synced_at }}<span v-if="check.synced_by_name"> by {{ check.synced_by_name }}</span></span>
        </div>
      </div>

      <!-- ── Channels ───────────────────────────────────────── -->
      <div class="bg-white shadow-sm ring-1 ring-black ring-opacity-5 rounded-lg overflow-hidden">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
          <thead class="bg-gray-50 text-xs text-gray-600">
            <tr>
              <th class="px-2 py-2 text-center w-14">Ch</th>
              <th class="px-2 py-2 text-left">Product</th>
              <th class="px-2 py-2 text-center w-20">Current Qty<br><span class="font-normal">系统</span></th>
              <th class="px-2 py-2 text-center w-24">Real Qty<br><span class="font-normal">实际</span></th>
              <th class="px-2 py-2 text-center w-20" v-if="isCounted">Variance</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-100">
            <template v-for="channel in check.channels" :key="channel.id">
              <tr :class="rowClass(channel)">
                <td class="px-2 py-3 text-center font-semibold text-gray-900">{{ channel.vend_channel_code }}</td>
                <td class="px-2 py-3">
                  <div class="flex items-center gap-2">
                    <img v-if="channel.product_thumbnail_url" :src="channel.product_thumbnail_url" class="h-10 w-10 rounded object-cover bg-gray-100 flex-none" alt="" loading="lazy" />
                    <div class="min-w-0">
                      <div class="text-gray-900 break-words">{{ channel.product_name || '—' }}</div>
                      <div class="text-xs text-gray-500">{{ channel.product_code }}</div>
                      <span v-if="channel.is_product_changed" class="inline-flex rounded bg-orange-100 text-orange-800 border border-orange-300 px-1 text-[10px] font-medium">
                        product changed since the draw
                      </span>
                    </div>
                  </div>
                </td>
                <td class="px-2 py-3 text-center text-base font-semibold text-gray-700">{{ channel.current_qty ?? '—' }}</td>
                <td class="px-2 py-3 text-center">
                  <!-- Starts unselected on purpose: the driver must choose for every
                       line. Choosing the number the system shows is a valid answer. -->
                  <select
                    v-if="isPending && canUpdate"
                    v-model="answers[channel.id]"
                    class="shadow-sm focus:ring-cyan-500 focus:border-cyan-500 block w-full text-base border-gray-300 rounded-md text-center"
                    :class="{ 'border-red-400 bg-red-50': lineErrors[channel.id] }"
                  >
                    <option :value="null" disabled>—</option>
                    <option v-for="n in channel.max_qty + 1" :key="n - 1" :value="n - 1">{{ n - 1 }}</option>
                  </select>
                  <span v-else class="text-base font-semibold text-gray-900">{{ channel.counted_qty ?? '—' }}</span>
                </td>
                <td class="px-2 py-3 text-center" v-if="isCounted">
                  <span v-if="channel.variance_qty === null" class="text-gray-400">—</span>
                  <span v-else-if="channel.variance_qty === 0" class="text-green-700 font-semibold">✓</span>
                  <span v-else class="font-semibold" :class="channel.variance_qty < 0 ? 'text-red-700' : 'text-orange-700'">
                    {{ signed(channel.variance_qty) }}
                  </span>
                  <div v-if="channel.is_synced" class="text-[10px] text-gray-500 leading-tight">
                    synced {{ channel.qty_before_sync }}→{{ channel.qty_after_sync }}
                  </div>
                </td>
              </tr>
              <tr v-if="(isPending && canUpdate) || channel.note || lineErrors[channel.id]" :class="rowClass(channel)">
                <td></td>
                <td :colspan="isCounted ? 4 : 3" class="px-2 pb-3">
                  <input
                    v-if="isPending && canUpdate"
                    type="text" v-model="notes[channel.id]" maxlength="500" placeholder="Note (optional)"
                    class="shadow-sm focus:ring-cyan-500 focus:border-cyan-500 block w-full text-xs border-gray-200 rounded-md"
                  />
                  <div v-else-if="channel.note" class="text-xs text-gray-600">Note: {{ channel.note }}</div>
                  <div class="text-xs text-red-600" v-if="lineErrors[channel.id]">{{ lineErrors[channel.id] }}</div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <!-- ── Photos ─────────────────────────────────────────── -->
      <div class="bg-white shadow-sm ring-1 ring-black ring-opacity-5 rounded-lg p-4" v-if="canUpdate || check.attachments.length">
        <div class="text-sm font-medium text-gray-700 mb-2">Photo(s)</div>
        <StopAttachmentSlot
          :files="check.attachments"
          :uploadUrl="'/stock-checks/' + check.id + '/attachments'"
          :deleteUrl="file => '/stock-checks/' + check.id + '/attachments/' + file.id"
          :editable="canUpdate && check.status !== 99"
          @changed="apply"
        />
      </div>

      <!-- ── Sync (supervisor and above) ────────────────────── -->
      <div v-if="isCounted && check.mismatch_count && canSync" class="bg-white shadow-sm ring-1 ring-black ring-opacity-5 rounded-lg p-4 text-sm space-y-2">
        <div class="font-medium text-gray-800">Sync the system quantity with this count</div>
        <div class="text-gray-600">{{ sync.refusal || sync.notice }}</div>
        <ul v-if="syncResults.length" class="text-xs space-y-0.5">
          <li v-for="result in syncResults" :key="result.channel_code" :class="result.applied ? 'text-green-700' : 'text-orange-700'">
            Channel {{ result.channel_code }}:
            <span v-if="result.applied">{{ result.qty_before }} → {{ result.qty_after }}<span v-if="result.reason"> ({{ result.reason }})</span></span>
            <span v-else>skipped — {{ result.reason }}</span>
          </li>
        </ul>
        <Button
          v-if="!sync.refusal"
          class="bg-indigo-600 hover:bg-indigo-700 text-white"
          :class="{ 'opacity-50 cursor-not-allowed': !check.unsynced_mismatch_count || busy }"
          :disabled="!check.unsynced_mismatch_count || busy"
          @click="runSync"
        >
          <div class="flex items-center space-x-1">
            <ArrowPathIcon class="h-4 w-4" />
            <span>{{ check.unsynced_mismatch_count ? 'Sync ' + check.unsynced_mismatch_count + ' channel(s)' : 'Synced' }}</span>
          </div>
        </Button>
      </div>

      <!-- ── Actions ────────────────────────────────────────── -->
      <div class="bg-white shadow-sm ring-1 ring-black ring-opacity-5 rounded-lg p-4 flex flex-wrap items-center justify-between gap-2">
        <div class="text-sm text-gray-600">
          <span v-if="isPending">{{ answeredCount }} of {{ check.channels.length }} channel(s) counted</span>
          <span v-else-if="check.status === 99">Cancelled {{ check.cancelled_at }}</span>
        </div>
        <div class="flex flex-wrap gap-2">
          <Link :href="'/ops-jobs/' + check.ops_job_id + '/edit'">
            <Button class="bg-gray-300 hover:bg-gray-400 text-gray-800">Back to Job</Button>
          </Link>
          <Button v-if="isPending && canRedraw && check.is_random" class="bg-white hover:bg-gray-100 text-gray-700 border border-gray-300" :disabled="busy" @click="act('redraw', 'Draw a new random sample? The current channels are replaced.')">
            <div class="flex items-center space-x-1"><ArrowPathIcon class="h-4 w-4" /><span>Re-draw</span></div>
          </Button>
          <template v-if="canUpdate">
            <Button v-if="isPending" class="bg-gray-500 hover:bg-gray-600 text-white" :disabled="busy" @click="act('cancel', 'Cancel this stock count?')">Cancel Count</Button>
            <Button v-if="isCounted && !check.synced_at" class="bg-white hover:bg-gray-100 text-gray-700 border border-gray-300" :disabled="busy" @click="act('undo', 'Reopen this count? The counted figures are cleared.')">Reopen</Button>
            <Button
              v-if="isPending" class="bg-cyan-600 hover:bg-cyan-700 text-white"
              :class="{ 'opacity-50 cursor-not-allowed': !allAnswered || busy }"
              :title="allAnswered ? '' : 'Choose the real quantity for every channel first'"
              :disabled="busy"
              @click="submit"
            >Submit Count 提交</Button>
          </template>
          <Button v-if="canDelete && !check.synced_at" class="bg-red-500 hover:bg-red-600 text-white" :disabled="busy" @click="destroy">Delete</Button>
        </div>
      </div>
    </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import Button from '@/Components/Button.vue'
import StopAttachmentSlot from '@/Pages/OpsJob/Stops/StopAttachmentSlot.vue'
import { firstErrors, stopStatusClass } from '@/Pages/OpsJob/Stops/stopTypes'
import { ArrowPathIcon, ClipboardDocumentListIcon } from '@heroicons/vue/20/solid'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { useToast } from 'vue-toastification'

const props = defineProps({
  stockCheck: Object,
  sync: Object, // { refusal, notice } — what Sync does for THIS machine kind
})

const toast = useToast()
const page = usePage()
const permissions = page.props.auth.permissions
const operatorCountry = page.props.auth.operatorCountry
const canUpdate = permissions.includes('update stock-checks')
const canRedraw = permissions.includes('redraw stock-checks')
const canSync = permissions.includes('sync stock-checks')
const canDelete = permissions.includes('delete stock-checks')

const check = ref(props.stockCheck.data)
const remarks = ref(check.value.remarks || '')
const answers = ref({}) // channel id -> real qty (null = not chosen yet)
const notes = ref({})
const lineErrors = ref({})
const pageError = ref('')
const syncResults = ref([])
const busy = ref(false)

resetAnswers()

const isPending = computed(() => check.value.status === 1)
const isCounted = computed(() => check.value.status === 3)
const answeredCount = computed(() => check.value.channels.filter(c => answers.value[c.id] !== null && answers.value[c.id] !== undefined).length)
const allAnswered = computed(() => answeredCount.value === check.value.channels.length)

function resetAnswers() {
  answers.value = Object.fromEntries(check.value.channels.map(c => [c.id, c.counted_qty ?? null]))
  notes.value = Object.fromEntries(check.value.channels.map(c => [c.id, c.note ?? '']))
  lineErrors.value = {}
}

function rowClass(channel) {
  if (!isCounted.value || !channel.variance_qty) return ''
  return channel.variance_qty < 0 ? 'bg-red-50' : 'bg-orange-50'
}

function signed(value) {
  const n = Number(value || 0)
  return (n > 0 ? '+' : '') + n
}

// Cents on the wire; divide only here, at the point of display.
function money(cents) {
  const exponent = operatorCountry?.is_currency_exponent_hidden ? 0 : (operatorCountry?.currency_exponent ?? 2)
  const value = Number(cents || 0)
  const amount = (Math.abs(value) / 100).toLocaleString(undefined, { minimumFractionDigits: exponent, maximumFractionDigits: exponent })
  return (value < 0 ? '-' : value > 0 ? '+' : '') + (operatorCountry?.currency_symbol ?? '$') + amount
}

// Every write answers with the whole check, so the page has one way to update.
function apply(data) {
  if (data?.stockCheck) {
    check.value = data.stockCheck.data ?? data.stockCheck
    remarks.value = check.value.remarks || ''
    resetAnswers()
  }
  pageError.value = ''
}

function fail(error) {
  const errors = firstErrors(error)
  const perLine = {}
  Object.entries(errors).forEach(([key, message]) => {
    const match = key.match(/^channels\.(\d+)$/)
    if (match) perLine[match[1]] = message
  })
  lineErrors.value = perLine
  pageError.value = Object.keys(perLine).length ? 'Some channels still need an answer.' : Object.values(errors)[0]
}

function saveRemarks() {
  axios.post('/stock-checks/' + check.value.id + '/update', { remarks: remarks.value || null }).then(({ data }) => apply(data)).catch(fail)
}

function submit() {
  if (!allAnswered.value) {
    lineErrors.value = Object.fromEntries(check.value.channels
      .filter(c => answers.value[c.id] === null || answers.value[c.id] === undefined)
      .map(c => [c.id, 'Choose the real quantity.']))
    pageError.value = 'Choose the real quantity for every channel first.'
    return
  }
  busy.value = true
  axios.post('/stock-checks/' + check.value.id + '/submit', {
    channels: check.value.channels.map(c => ({ id: c.id, counted_qty: answers.value[c.id], note: notes.value[c.id] || null })),
  })
    .then(({ data }) => { apply(data); toast.success('Count submitted', { timeout: 2000 }) })
    .catch(fail)
    .finally(() => { busy.value = false })
}

function act(action, question = null) {
  if (question && !confirm(question)) return
  busy.value = true
  axios.post('/stock-checks/' + check.value.id + '/' + action)
    .then(({ data }) => { apply(data); syncResults.value = []; toast.success('Saved', { timeout: 2000 }) })
    .catch(fail)
    .finally(() => { busy.value = false })
}

function runSync() {
  if (!confirm(props.sync.notice + '\n\nSync ' + check.value.unsynced_mismatch_count + ' channel(s) now?')) return
  busy.value = true
  axios.post('/stock-checks/' + check.value.id + '/sync')
    .then(({ data }) => { apply(data); syncResults.value = data.results || []; toast.success('Synced', { timeout: 2000 }) })
    .catch(fail)
    .finally(() => { busy.value = false })
}

function destroy() {
  if (!confirm('Delete ' + check.value.display_code + '? This cannot be undone.')) return
  axios.delete('/stock-checks/' + check.value.id)
    .then(({ data }) => router.visit(data.redirect))
    .catch(fail)
}
</script>
