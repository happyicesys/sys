<template>
  <section>
    <header class="flex flex-wrap items-center justify-between gap-2">
      <h4 class="text-xs font-semibold uppercase tracking-wide text-gray-500">
        Timeline · commands, kiosk panel and machine events
      </h4>
      <div class="flex flex-wrap items-center gap-2 text-xs">
        <input v-model.trim="filter" type="text" placeholder="filter rows…"
               class="w-40 rounded-md border-gray-300 py-1 text-xs focus:border-sky-500 focus:ring-sky-500" />
        <label class="inline-flex items-center gap-1 text-gray-600">
          <input v-model="showEvents" type="checkbox" class="rounded border-gray-300 text-sky-700 focus:ring-sky-500" />
          machine events
        </label>
        <span class="text-gray-500">last {{ commands.length }}<template v-if="total"> of {{ total }}</template></span>
        <button type="button" class="font-medium text-sky-700 hover:underline" @click.prevent="$emit('limit', wide ? 20 : 200)">
          {{ wide ? 'fewer' : 'more' }}
        </button>
      </div>
    </header>

    <div class="mt-2 overflow-x-auto rounded-lg border border-gray-200">
      <table class="min-w-full divide-y divide-gray-200 text-xs">
        <thead class="bg-gray-50 text-left text-[11px] uppercase tracking-wide text-gray-500">
          <tr>
            <th class="px-3 py-2 font-medium">When</th>
            <th class="px-3 py-2 font-medium">What</th>
            <th class="px-3 py-2 font-medium">From</th>
            <th class="px-3 py-2 font-medium">Result</th>
            <th class="px-3 py-2 font-medium">Machine said</th>
            <th class="px-3 py-2 font-medium">Log</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 bg-white">
          <!--
            Runs of the same machine event collapse into their newest row (Brian, 2026-09-16): an
            unanswered card reader files one every two minutes, and 20 identical CardDetect lines
            buried everything else. Click the count to see the individual rows.
          -->
          <template v-for="group in groups" :key="group.lead.id">
            <tr :class="group.lead.source === 'event' ? 'bg-gray-50/60' : 'hover:bg-sky-50/40'">
              <td class="whitespace-nowrap px-3 py-1.5 text-gray-600">
                {{ formatTime(group.lead.requested_at) }}
                <span v-if="group.count > 1" class="block text-[11px] text-gray-400">since {{ formatTime(group.oldest.requested_at) }}</span>
              </td>
              <td class="whitespace-nowrap px-3 py-1.5 text-gray-800">
                {{ describeCommand(group.lead) }}
                <button v-if="group.count > 1" type="button"
                        class="ml-1 rounded-full bg-slate-600 px-2 py-0.5 text-[11px] font-semibold text-white hover:bg-slate-700"
                        @click.prevent="toggleGroup(group.lead.id)">
                  ×{{ group.count }} {{ openGroups.has(group.lead.id) ? '▾' : '▸' }}
                </button>
              </td>
              <td class="whitespace-nowrap px-3 py-1.5">
                <span class="inline-flex items-center rounded px-1.5 py-0.5 font-medium" :class="sourceBadge(group.lead.source)">
                  {{ sourceLabel(group.lead) }}
                </span>
              </td>
              <td class="whitespace-nowrap px-3 py-1.5">
                <span class="inline-flex items-center rounded-full px-2 py-0.5 font-medium" :class="resultBadge(group.lead.status)">
                  {{ resultLabel(group.lead.status) }}
                </span>
                <span v-if="group.lead.responded_at && ['mark1', 'schedule'].includes(group.lead.source)" class="ml-1 text-gray-400">{{ answeredIn(group.lead) }}</span>
              </td>
              <td class="max-w-md px-3 py-1.5 text-gray-700">{{ group.lead.message || '' }}</td>
              <td class="whitespace-nowrap px-3 py-1.5">
                <button v-if="group.lead.has_log" type="button" class="mr-2 text-sky-700 hover:underline" @click.prevent="toggleExcerpt(group.lead.id)">
                  {{ openExcerpts.has(group.lead.id) ? 'hide' : (group.lead.log_scope === 'output' ? 'output' : 'excerpt') }}
                  <span v-if="group.lead.log_scope === 'app'" class="text-gray-400">(app only)</span>
                </button>
                <a v-if="group.lead.attachment" :href="group.lead.attachment.url" target="_blank" class="mr-2 text-sky-700 hover:underline">
                  {{ group.lead.attachment.type === 'photo' ? 'open photo' : 'open file' }}
                </a>
                <template v-if="group.lead.log_file">
                  <a :href="group.lead.log_file.url" target="_blank" class="mr-2 text-sky-700 hover:underline">
                    view {{ group.lead.log_file.lines ? group.lead.log_file.lines + ' lines' : 'file' }}
                  </a>
                  <a :href="group.lead.log_file.url + '?download=1'" class="text-sky-700 hover:underline">download</a>
                </template>
              </td>
            </tr>
            <tr v-if="group.lead.has_log && openExcerpts.has(group.lead.id)">
              <td colspan="6" class="px-3 py-1.5">
                <pre class="max-h-72 overflow-auto rounded-md bg-gray-900 p-2 text-[11px] leading-snug text-gray-100 whitespace-pre-wrap break-all">{{ excerpts[group.lead.id] ?? 'loading…' }}</pre>
              </td>
            </tr>
            <!-- The folded run, opened on demand: the same rows, indented and dimmed. -->
            <template v-if="group.count > 1 && openGroups.has(group.lead.id)">
              <tr v-for="row in group.rest" :key="row.id" class="bg-gray-50 text-gray-500">
                <td class="whitespace-nowrap px-3 py-1.5 pl-6">{{ formatTime(row.requested_at) }}</td>
                <td class="whitespace-nowrap px-3 py-1.5">{{ describeCommand(row) }}</td>
                <td class="whitespace-nowrap px-3 py-1.5">
                  <span class="inline-flex items-center rounded px-1.5 py-0.5" :class="sourceBadge(row.source)">{{ sourceLabel(row) }}</span>
                </td>
                <td class="whitespace-nowrap px-3 py-1.5">
                  <span class="inline-flex items-center rounded-full px-2 py-0.5 font-medium" :class="resultBadge(row.status)">{{ resultLabel(row.status) }}</span>
                </td>
                <td class="max-w-md px-3 py-1.5">{{ row.message || '' }}</td>
                <td class="px-3 py-1.5"></td>
              </tr>
            </template>
          </template>
          <tr v-if="!groups.length">
            <td colspan="6" class="px-3 py-4 text-center text-gray-500">Nothing recorded yet.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import moment from 'moment'
import { describeCommand, resultBadge, resultLabel } from '@/composables/useFreezerControls'

const props = defineProps({
  vendId: { type: Number, required: true },
  commands: { type: Array, default: () => [] },
  total: { type: Number, default: 0 },
  wide: { type: Boolean, default: false },
})

defineEmits(['limit'])

const filter = ref('')
const showEvents = ref(true)
const openGroups = ref(new Set())
const openExcerpts = ref(new Set())
const excerpts = ref({})

const visible = computed(() => props.commands.filter((c) => {
  if (!showEvents.value && c.source === 'event') return false
  const q = filter.value.toLowerCase()
  if (!q) return true
  return [describeCommand(c), c.requested_by, c.message, c.status, c.source].filter(Boolean).join(' ').toLowerCase().includes(q)
}))

/**
 * Consecutive rows that say exactly the same thing (same source, op, verdict and message) fold into
 * the newest one. Only CONSECUTIVE runs fold, so the order of events is never rearranged: an
 * unrelated row between two CardDetect errors splits them into two runs, as it should.
 */
const groups = computed(() => {
  const out = []
  for (const c of visible.value) {
    const key = [c.source, c.op, c.status, c.message].join('\u0000')
    const last = out[out.length - 1]
    if (last && last.key === key) {
      last.rest.push(c)
      last.oldest = c
      last.count++
    } else {
      out.push({ key, lead: c, oldest: c, rest: [], count: 1 })
    }
  }
  return out
})

function toggleGroup(id) {
  const next = new Set(openGroups.value)
  next.has(id) ? next.delete(id) : next.add(id)
  openGroups.value = next
}

/** The excerpt is fetched on demand: 20 rows x 12 KB on every poll would be most of the traffic. */
async function toggleExcerpt(id) {
  const next = new Set(openExcerpts.value)
  next.has(id) ? next.delete(id) : next.add(id)
  openExcerpts.value = next
  if (next.has(id) && excerpts.value[id] === undefined) {
    try {
      const res = await axios.get(`/vends/${props.vendId}/freezer-controls/${id}/excerpt`)
      excerpts.value = { ...excerpts.value, [id]: res.data.log || '(empty)' }
    } catch (e) {
      excerpts.value = { ...excerpts.value, [id]: 'Could not load the excerpt.' }
    }
  }
}

function sourceLabel(c) {
  return c.source === 'panel' ? 'Kiosk panel' : c.source === 'event' ? 'Machine' : (c.requested_by || 'mark1')
}

function sourceBadge(source) {
  if (source === 'panel') return 'bg-indigo-100 text-indigo-800'
  if (source === 'event') return 'bg-gray-200 text-gray-700'
  if (source === 'schedule') return 'bg-violet-100 text-violet-800'
  return 'bg-sky-100 text-sky-800'
}

function answeredIn(c) {
  const ms = Date.parse(c.responded_at) - Date.parse(c.requested_at)
  return Number.isFinite(ms) && ms >= 0 ? `in ${Math.max(1, Math.round(ms / 1000))} s` : ''
}

function formatTime(iso) {
  return iso ? moment(iso).format('DD MMM HH:mm:ss') : ''
}
</script>
