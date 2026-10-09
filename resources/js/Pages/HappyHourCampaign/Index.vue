<template>

  <Head title="Happy Hour Campaign" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Happy Hour Campaign
        <span class="ml-2 align-middle inline-flex items-center rounded px-1.5 py-0.5 text-xs font-bold border bg-sky-100 text-sky-800 border-sky-300">
          Smart Freezer
        </span>
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="-mx-3 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3">
        <p class="text-xs text-gray-500">
          During a campaign's hours each machine shows one discounted SKU at a time on its welcome screen
          (Happy Hour page) and charges the promo price for it. The SKUs are picked automatically about
          10 minutes before the window opens: the slowest movers first ("days of cover" = stock ÷ daily sales;
          a tie goes to the higher stock), one SKU per slot, in rank order. A SKU that sells out ends its slot early.
          Only freezers on app {{ minApkVersion }} or later can run it.
        </p>

        <div class="flex justify-end mt-3" v-if="permissions.includes('create happy-hour-campaigns')">
          <Button class="bg-green-500 hover:bg-green-600 text-white flex space-x-1" @click="openCreate()">
            <PlusIcon class="w-4 h-4" />
            <span>New campaign</span>
          </Button>
        </div>

        <div class="mt-4 flex flex-col">
          <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
              <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                <table class="min-w-full divide-y divide-gray-300">
                  <thead class="bg-gray-100">
                    <tr class="divide-x divide-gray-200">
                      <TableHead>Campaign</TableHead>
                      <TableHead>Status</TableHead>
                      <TableHead>When</TableHead>
                      <TableHead>Offer</TableHead>
                      <TableHead>Machines</TableHead>
                      <TableHead></TableHead>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-gray-200 bg-white">
                    <tr v-for="c in campaigns" :key="c.id" class="divide-x divide-gray-200 align-top">
                      <TableData>
                        <div class="font-medium text-gray-900">{{ c.name }}</div>
                        <div class="text-xs text-gray-500">{{ ruleLabel(c.selection_rule) }}</div>
                      </TableData>
                      <TableData>
                        <span class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-bold border" :class="statusClass(c.status)">
                          {{ statusLabels[c.status] }}
                        </span>
                        <div v-for="s in c.today.filter(s => s.live)" :key="s.id" class="mt-1 text-xs font-semibold text-rose-700 whitespace-nowrap">
                          <span v-if="c.vends.length > 1">{{ vendCode(c, s.vend_id) }} · </span>{{ s.product }} {{ money(s.promo_price) }}
                        </div>
                      </TableData>
                      <TableData>
                        <div class="text-sm">{{ c.days_label }}, {{ c.window_start }}–{{ c.window_end }}</div>
                        <div class="text-xs text-gray-500">{{ c.slot_minutes }} min per SKU · top {{ c.sku_count }}</div>
                        <div class="text-xs text-gray-500" v-if="c.starts_on || c.ends_on">
                          {{ c.starts_on || '…' }} → {{ c.ends_on || 'no end' }}
                        </div>
                      </TableData>
                      <TableData>
                        <div class="text-sm font-semibold text-rose-600">−{{ c.discount_pct }}%</div>
                        <div class="text-xs text-gray-500">
                          floor {{ c.min_balance_pct }}% · min {{ c.min_qty }} units
                        </div>
                      </TableData>
                      <TableData>
                        <div class="flex flex-wrap gap-1">
                          <span v-for="v in c.vends" :key="v.id"
                            class="inline-flex items-center rounded px-1.5 py-0.5 text-xs border"
                            :class="v.supported ? 'bg-gray-50 text-gray-800 border-gray-300' : 'bg-amber-50 text-amber-800 border-amber-300'"
                            :title="v.supported ? '' : 'Not on app ' + minApkVersion + '+ (or inactive): no slots until it updates'">
                            {{ v.code }}<span v-if="!v.supported">&nbsp;⚠</span>
                          </span>
                        </div>
                      </TableData>
                      <TableData>
                        <div class="flex flex-col space-y-1">
                          <Button class="bg-sky-500 hover:bg-sky-600 text-white text-xs" @click="openPreview(c)">Preview lineup</Button>
                          <Button class="bg-white hover:bg-gray-50 text-sky-700 border border-sky-300 text-xs" @click="openToday(c)">
                            Today's slots ({{ c.today.length }})
                          </Button>
                          <Button v-if="permissions.includes('update happy-hour-campaigns')" class="bg-gray-200 hover:bg-gray-300 text-gray-800 text-xs" @click="openEdit(c)">Edit</Button>
                          <Button v-if="permissions.includes('delete happy-hour-campaigns')" class="bg-red-50 hover:bg-red-100 text-red-700 text-xs" @click="destroy(c)">Delete</Button>
                        </div>
                      </TableData>
                    </tr>
                    <tr v-if="!campaigns.length">
                      <td colspan="6" class="text-center text-sm text-gray-500 py-6">No Happy Hour campaigns yet.</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create / edit -->
    <Modal :open="showForm" @modalClose="showForm = false">
      <template #header>
        <span>{{ editingId ? 'Edit Happy Hour campaign' : 'New Happy Hour campaign' }}</span>
      </template>
      <template #default>
        <form @submit.prevent="submit">
          <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
            <div class="md:col-span-4">
              <label class="block text-sm font-medium text-gray-700">Name</label>
              <input v-model="form.name" type="text" :class="inputClass" />
              <p v-if="form.errors.name" class="text-red-600 text-xs mt-1">{{ form.errors.name }}</p>
            </div>
            <div class="md:col-span-2 flex items-end">
              <label class="inline-flex items-center space-x-2 text-sm text-gray-700">
                <input type="checkbox" v-model="form.is_active" class="rounded border-gray-300" />
                <span>Active</span>
              </label>
            </div>

            <div class="md:col-span-6">
              <label class="block text-sm font-medium text-gray-700">Machines</label>
              <MultiSelect v-model="selectedMachines" :options="machines" mode="tags" trackBy="id" valueProp="id" label="name"
                placeholder="Smart freezers" open-direction="bottom" class="mt-1" />
              <p class="text-xs text-gray-500 mt-1" v-if="unsupportedPicked.length">
                ⚠ {{ unsupportedPicked.join(', ') }}: not on app {{ minApkVersion }}+ yet — kept, but no slots until it updates.
              </p>
              <p v-if="form.errors.vend_ids" class="text-red-600 text-xs mt-1">{{ form.errors.vend_ids }}</p>
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700">Discount %</label>
              <input v-model.number="form.discount_pct" type="number" min="1" max="90" :class="inputClass" />
              <p v-if="form.errors.discount_pct" class="text-red-600 text-xs mt-1">{{ form.errors.discount_pct }}</p>
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700">Duration per SKU (minutes)</label>
              <input v-model.number="form.slot_minutes" type="number" min="5" max="720" step="5" :class="inputClass" />
              <p v-if="form.errors.slot_minutes" class="text-red-600 text-xs mt-1">{{ form.errors.slot_minutes }}</p>
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700">How many SKUs take turns</label>
              <input v-model.number="form.sku_count" type="number" min="1" max="30" :class="inputClass" />
              <p v-if="form.errors.sku_count" class="text-red-600 text-xs mt-1">{{ form.errors.sku_count }}</p>
            </div>

            <div class="md:col-span-3">
              <label class="block text-sm font-medium text-gray-700">Pick SKUs by</label>
              <select v-model="form.selection_rule" :class="inputClass">
                <option v-for="r in rules" :key="r.id" :value="r.id">{{ r.name }}</option>
              </select>
            </div>
            <div class="md:col-span-3">
              <label class="block text-sm font-medium text-gray-700">Sales look back (days)</label>
              <input v-model.number="form.lookback_days" type="number" min="1" max="90" :class="inputClass" />
            </div>

            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700">Skip when stock ≤ (%)</label>
              <input v-model.number="form.min_balance_pct" type="number" min="0" max="99" :class="inputClass" />
              <p class="text-xs text-gray-400 mt-0.5">Where Real Capacity is set</p>
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700">Skip when fewer units than</label>
              <input v-model.number="form.min_qty" type="number" min="1" max="999" :class="inputClass" />
            </div>
            <div class="md:col-span-2">
              <label class="block text-sm font-medium text-gray-700">Round promo price down to</label>
              <select v-model.number="form.price_step_cents" :class="inputClass">
                <option :value="1">1¢</option>
                <option :value="5">5¢</option>
                <option :value="10">10¢</option>
                <option :value="50">50¢</option>
                <option :value="100">$1</option>
              </select>
            </div>

            <div class="md:col-span-6">
              <label class="block text-sm font-medium text-gray-700">Days</label>
              <div class="flex flex-wrap gap-2 mt-1">
                <button v-for="p in dayPresets" :key="p.mask" type="button" @click="form.days_mask = p.mask"
                  class="rounded border px-2 py-1 text-xs"
                  :class="form.days_mask === p.mask ? 'bg-sky-600 text-white border-sky-600' : 'bg-white text-gray-700 border-gray-300'">
                  {{ p.name }}
                </button>
                <span class="mx-1 text-gray-300">|</span>
                <button v-for="(d, i) in dayNames" :key="d" type="button" @click="toggleDay(i)"
                  class="rounded border w-10 py-1 text-xs"
                  :class="(form.days_mask & (1 << i)) ? 'bg-sky-100 text-sky-800 border-sky-300' : 'bg-white text-gray-400 border-gray-200'">
                  {{ d }}
                </button>
              </div>
              <p v-if="form.errors.days_mask" class="text-red-600 text-xs mt-1">{{ form.errors.days_mask }}</p>
            </div>

            <div class="md:col-span-3">
              <label class="block text-sm font-medium text-gray-700">Hours from</label>
              <input v-model="form.window_start" type="time" :class="inputClass" />
            </div>
            <div class="md:col-span-3">
              <label class="block text-sm font-medium text-gray-700">Hours to</label>
              <input v-model="form.window_end" type="time" :class="inputClass" />
              <p v-if="form.errors.window_end" class="text-red-600 text-xs mt-1">{{ form.errors.window_end }}</p>
            </div>
            <div class="md:col-span-3">
              <DatePicker v-model="form.starts_on">Active from (optional)</DatePicker>
            </div>
            <div class="md:col-span-3">
              <DatePicker v-model="form.ends_on" :minDate="form.starts_on">Active to (optional)</DatePicker>
              <p v-if="form.errors.ends_on" class="text-red-600 text-xs mt-1">{{ form.errors.ends_on }}</p>
            </div>

            <div class="md:col-span-4">
              <label class="block text-sm font-medium text-gray-700">Never discount</label>
              <MultiSelect v-model="selectedExcluded" :options="products" mode="tags" trackBy="id" valueProp="id" label="name"
                placeholder="Products to leave out" open-direction="top" class="mt-1" />
            </div>
            <div class="md:col-span-2 flex items-end">
              <label class="inline-flex items-center space-x-2 text-sm text-gray-700">
                <input type="checkbox" v-model="form.allow_below_cost" class="rounded border-gray-300" />
                <span>Allow below unit cost</span>
              </label>
            </div>
          </div>

          <p class="text-xs text-gray-500 mt-4">
            {{ slotSummary }}
          </p>

          <div class="flex space-x-1 mt-5 justify-end">
            <Button class="bg-gray-300 hover:bg-gray-400 text-gray-700" type="button" @click="showForm = false">Back</Button>
            <Button type="submit" :disabled="form.processing" class="bg-green-500 hover:bg-green-600 text-white">Save</Button>
          </div>
        </form>
      </template>
    </Modal>

    <!-- Today's slots -->
    <Modal :open="!!todayCampaign" @modalClose="todayId = null">
      <template #header>
        <span>Today's slots · {{ todayCampaign?.name }}</span>
      </template>
      <template #default>
        <div v-if="todayCampaign" class="max-h-[70vh] overflow-y-auto">
          <div v-if="!todayCampaign.today.length" class="text-sm text-gray-500">No slots today.</div>
          <table v-else class="min-w-full text-xs border">
            <thead class="bg-gray-50 sticky top-0">
              <tr class="text-left">
                <th class="px-2 py-1">Time</th>
                <th class="px-2 py-1" v-if="todayCampaign.vends.length > 1">Machine</th>
                <th class="px-2 py-1">SKU</th>
                <th class="px-2 py-1 text-right">Price</th>
                <th class="px-2 py-1">Status</th>
                <th class="px-2 py-1 text-right">Sold</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="s in todayCampaign.today" :key="s.id" class="border-t"
                :class="s.live ? 'font-semibold text-rose-700 bg-rose-50' : (s.status === 'scheduled' ? 'text-gray-700' : 'text-gray-400')">
                <td class="px-2 py-1 whitespace-nowrap">{{ s.starts_at }}–{{ s.ends_at }}</td>
                <td class="px-2 py-1" v-if="todayCampaign.vends.length > 1">{{ vendCode(todayCampaign, s.vend_id) }}</td>
                <td class="px-2 py-1">{{ s.product }}</td>
                <td class="px-2 py-1 text-right whitespace-nowrap">
                  <span class="line-through text-gray-400 mr-1">{{ money(s.original_price) }}</span>{{ money(s.promo_price) }}
                </td>
                <td class="px-2 py-1">{{ s.live ? 'live' : (s.status === 'scheduled' ? 'scheduled' : slotStatusLabels[s.status]) }}</td>
                <td class="px-2 py-1 text-right">{{ s.units_sold ?? '' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </template>
    </Modal>

    <!-- Preview lineup -->
    <Modal :open="showPreview" @modalClose="showPreview = false">
      <template #header>
        <span>Lineup now · {{ preview?.campaign }}</span>
      </template>
      <template #default>
        <div v-if="!preview" class="text-sm text-gray-500">Loading…</div>
        <div v-else class="space-y-4 max-h-[70vh] overflow-y-auto">
          <p class="text-xs text-gray-500">As ranked at {{ preview.at }}. Rank 1 takes the first slot, rank 2 the next…</p>
          <div v-for="m in preview.machines" :key="m.vend_id">
            <div class="font-semibold text-sm">
              {{ m.code }}
              <span v-if="!m.supported" class="ml-1 text-xs text-amber-700 font-normal">⚠ app {{ m.apk_version_code ?? '?' }} — no slots until it runs {{ minApkVersion }}+</span>
            </div>
            <table class="min-w-full text-xs mt-1 border">
              <thead class="bg-gray-50">
                <tr class="text-left">
                  <th class="px-2 py-1">Rank</th>
                  <th class="px-2 py-1">SKU</th>
                  <th class="px-2 py-1 text-right">Stock</th>
                  <th class="px-2 py-1 text-right">Sold / day</th>
                  <th class="px-2 py-1 text-right">Days of cover</th>
                  <th class="px-2 py-1 text-right">Price</th>
                  <th class="px-2 py-1">Why not</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="row in m.candidates" :key="row.product_id" class="border-t" :class="row.rank ? '' : 'text-gray-400'">
                  <td class="px-2 py-1 font-semibold">{{ row.rank ?? '—' }}</td>
                  <td class="px-2 py-1">{{ row.product_code }} {{ row.product_name }}</td>
                  <td class="px-2 py-1 text-right whitespace-nowrap">
                    {{ row.qty }}<span v-if="row.capacity"> / {{ row.capacity }} ({{ row.balance_pct }}%)</span>
                  </td>
                  <td class="px-2 py-1 text-right">{{ row.avg_daily_sales }}</td>
                  <td class="px-2 py-1 text-right">{{ row.days_of_cover ?? '∞' }}</td>
                  <td class="px-2 py-1 text-right whitespace-nowrap">
                    <span v-if="row.promo_price" class="line-through mr-1">{{ money(row.original_price) }}</span>
                    {{ money(row.promo_price ?? row.original_price) }}
                  </td>
                  <td class="px-2 py-1">{{ row.excluded_reason }}</td>
                </tr>
                <tr v-if="!m.candidates.length"><td colspan="7" class="px-2 py-2 text-gray-500">No stock rows on this machine.</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </template>
    </Modal>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import DatePicker from '@/Components/DatePicker.vue';
import Modal from '@/Components/Modal.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import { PlusIcon } from '@heroicons/vue/20/solid';
import { computed, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';

const props = defineProps({
  campaigns: Array,
  machines: Array,
  products: Array,
  rules: Array,
  minApkVersion: Number,
})

const permissions = computed(() => usePage().props.auth?.permissions ?? [])

const inputClass = 'mt-1 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md'
const dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun']
const dayPresets = [
  { name: 'Every day', mask: 127 },
  { name: 'Weekdays', mask: 31 },
  { name: 'Weekends', mask: 96 },
]
const statusLabels = { running: 'Running now', scheduled: 'Scheduled', paused: 'Paused', ended: 'Ended' }
const slotStatusLabels = { ended: 'done', sold_out: 'sold out', cancelled: 'cancelled' }

const blank = () => ({
  name: '', is_active: true, starts_on: null, ends_on: null, days_mask: 127,
  window_start: '14:00', window_end: '18:00', slot_minutes: 60, sku_count: 4,
  selection_rule: 'days_of_cover', lookback_days: 14, discount_pct: 40,
  min_balance_pct: 15, min_qty: 2, price_step_cents: 10, allow_below_cost: false,
  excluded_product_ids: [], vend_ids: [],
})

const form = useForm(blank())
const editingId = ref(null)
const showForm = ref(false)
const selectedMachines = ref([])
const selectedExcluded = ref([])
const showPreview = ref(false)
const preview = ref(null)
const todayId = ref(null)
const todayCampaign = computed(() => props.campaigns.find(c => c.id === todayId.value) ?? null)

const unsupportedPicked = computed(() => selectedMachines.value.filter(m => !m.supported).map(m => m.name))

const slotSummary = computed(() => {
  const [h1, m1] = (form.window_start || '0:0').split(':').map(Number)
  const [h2, m2] = (form.window_end || '0:0').split(':').map(Number)
  const minutes = (h2 * 60 + m2) - (h1 * 60 + m1)
  if (minutes <= 0 || !form.slot_minutes) return ''
  const slots = Math.ceil(minutes / form.slot_minutes)
  return `${slots} slot${slots === 1 ? '' : 's'} a day of ${form.slot_minutes} min; the top ${form.sku_count} SKU${form.sku_count === 1 ? '' : 's'} take turns, at ${form.discount_pct}% off.`
})

function ruleLabel(id) {
  return props.rules.find(r => r.id === id)?.name ?? id
}

function statusClass(status) {
  return {
    running: 'bg-rose-100 text-rose-800 border-rose-300',
    scheduled: 'bg-sky-100 text-sky-800 border-sky-300',
    paused: 'bg-gray-100 text-gray-700 border-gray-300',
    ended: 'bg-gray-50 text-gray-400 border-gray-200',
  }[status]
}

function money(cents) {
  return cents === null || cents === undefined ? '' : '$' + (cents / 100).toFixed(2)
}

function vendCode(c, vendId) {
  return c.vends.find(v => v.id === vendId)?.code ?? vendId
}

function toggleDay(i) {
  form.days_mask ^= (1 << i)
}

function openCreate() {
  editingId.value = null
  Object.assign(form, blank())
  form.clearErrors()
  selectedMachines.value = []
  selectedExcluded.value = []
  showForm.value = true
}

function openEdit(c) {
  editingId.value = c.id
  form.clearErrors()
  Object.assign(form, {
    ...blank(),
    ...Object.fromEntries(Object.keys(blank()).filter(k => k in c).map(k => [k, c[k]])),
  })
  selectedMachines.value = props.machines.filter(m => c.vends.some(v => v.id === m.id))
  selectedExcluded.value = props.products.filter(p => c.excluded_product_ids.includes(p.id))
  showForm.value = true
}

function submit() {
  form.vend_ids = selectedMachines.value.map(m => m.id)
  form.excluded_product_ids = selectedExcluded.value.map(p => p.id)
  const url = editingId.value ? `/happy-hour-campaigns/${editingId.value}` : '/happy-hour-campaigns'
  form.post(url, {
    preserveScroll: true,
    onSuccess: () => { showForm.value = false },
  })
}

function destroy(c) {
  if (!confirm(`Delete "${c.name}"? Its remaining slots today stop at once.`)) return
  router.delete(`/happy-hour-campaigns/${c.id}`, { preserveScroll: true })
}

function openToday(c) {
  todayId.value = c.id
}

async function openPreview(c) {
  preview.value = null
  showPreview.value = true
  const { data } = await axios.get(`/happy-hour-campaigns/${c.id}/preview`)
  preview.value = data
}
</script>
