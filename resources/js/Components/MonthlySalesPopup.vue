<template>
    <TransitionRoot as="template" :show="open">
        <Dialog as="div" class="relative z-[60]" @close="dismiss">
            <TransitionChild as="template" enter="ease-out duration-300" enter-from="opacity-0" enter-to="opacity-100"
                             leave="ease-in duration-200" leave-from="opacity-100" leave-to="opacity-0">
                <div class="fixed inset-0 bg-slate-900/70 backdrop-blur-sm transition-opacity"/>
            </TransitionChild>

            <div class="fixed inset-0 z-[60] overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <TransitionChild as="template" enter="ease-out duration-300"
                                     enter-from="opacity-0 translate-y-6 scale-95"
                                     enter-to="opacity-100 translate-y-0 scale-100" leave="ease-in duration-200"
                                     leave-from="opacity-100 translate-y-0 scale-100"
                                     leave-to="opacity-0 translate-y-6 scale-95">
                        <DialogPanel
                            class="relative w-full max-w-md overflow-hidden rounded-3xl bg-white text-center shadow-2xl ring-1 ring-black/5"
                            @click.stop>
                            <!-- Refresh (top-left) -->
                            <button type="button"
                                    class="absolute left-4 top-4 z-20 grid h-9 w-9 place-items-center rounded-full bg-white/10 text-white/90 ring-1 ring-white/30 backdrop-blur transition hover:bg-white/25 hover:text-white disabled:cursor-not-allowed disabled:opacity-60"
                                    :disabled="refreshing"
                                    @click="refresh">
                                <span class="sr-only">Refresh</span>
                                <ArrowPathIcon class="h-5 w-5" :class="{ 'animate-spin': refreshing }" aria-hidden="true"/>
                            </button>

                            <!-- Tier info (top-left, beside refresh) -->
                            <div class="absolute left-[3.75rem] top-4 z-30">
                                <button type="button"
                                        class="grid h-9 w-9 place-items-center rounded-full bg-white/10 text-white/90 ring-1 ring-white/30 backdrop-blur transition hover:bg-white/25 hover:text-white"
                                        :aria-expanded="showTiers"
                                        @click="showTiers = !showTiers">
                                    <span class="sr-only">Sales tiers</span>
                                    <InformationCircleIcon class="h-5 w-5" aria-hidden="true"/>
                                </button>

                                <transition enter-active-class="transition ease-out duration-150"
                                            enter-from-class="opacity-0 -translate-y-1" enter-to-class="opacity-100 translate-y-0"
                                            leave-active-class="transition ease-in duration-100"
                                            leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 -translate-y-1">
                                    <div v-if="showTiers"
                                         class="absolute left-0 top-11 z-30 w-56 origin-top-left rounded-2xl bg-white p-3 text-left shadow-2xl ring-1 ring-black/5">
                                        <p class="mb-2 px-1 text-[0.7rem] font-semibold uppercase tracking-wider text-gray-400">
                                            Sales tiers
                                        </p>
                                        <ul v-if="tierLegend.length" class="space-y-1">
                                            <li v-for="t in tierLegend" :key="t.name"
                                                class="flex items-center justify-between rounded-lg px-1.5 py-1">
                                                <span class="flex items-center gap-1.5">
                                                    <svg class="h-4 w-4 shrink-0" :class="t.color"
                                                         viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                                        <path d="M7.4 2h2.7l2.3 4.6-2.7 1.1L7.4 2z"/>
                                                        <path d="M16.6 2h-2.7l-2.3 4.6 2.7 1.1L16.6 2z"/>
                                                        <circle cx="12" cy="15.2" r="6.4"/>
                                                        <path fill="#fff" d="M12 11.4l.96 1.95 2.15.31-1.56 1.52.37 2.14L12 16.86l-1.92 1.01.37-2.14-1.56-1.52 2.15-.31z"/>
                                                    </svg>
                                                    <span class="text-sm font-semibold text-gray-700">{{ t.label }}</span>
                                                </span>
                                                <span class="text-sm font-bold tabular-nums text-gray-800">{{ currency }}{{ formatTarget(t.amount) }}</span>
                                            </li>
                                        </ul>
                                        <p v-else class="px-1.5 py-1 text-sm text-gray-500">No tiers set.</p>
                                    </div>
                                </transition>
                            </div>

                            <!-- Close (top-right) -->
                            <button type="button"
                                    class="absolute right-4 top-4 z-20 grid h-9 w-9 place-items-center rounded-full bg-white/10 text-white/90 ring-1 ring-white/30 backdrop-blur transition hover:bg-white/25 hover:text-white"
                                    @click="dismiss">
                                <span class="sr-only">Close</span>
                                <XMarkIcon class="h-5 w-5" aria-hidden="true"/>
                            </button>

                            <!-- Gradient header band -->
                            <div class="relative overflow-hidden bg-gradient-to-br from-sky-400 via-blue-600 to-indigo-700 px-8 pb-16 pt-10">
                                <!-- decorative glows -->
                                <div class="pointer-events-none absolute -right-12 -top-12 h-44 w-44 rounded-full bg-cyan-200/25 blur-3xl"></div>
                                <div class="pointer-events-none absolute -bottom-16 -left-10 h-48 w-48 rounded-full bg-indigo-300/25 blur-3xl"></div>

                                <div class="relative z-10">
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-sky-50 ring-1 ring-inset ring-white/25">
                                        <ArrowTrendingUpIcon class="h-3.5 w-3.5" aria-hidden="true"/>
                                        This month sales
                                    </span>

                                    <div class="mt-5 min-h-[3.75rem]">
                                        <div v-if="loading" class="flex items-center justify-center">
                                            <span class="h-9 w-48 animate-pulse rounded-lg bg-white/30"></span>
                                        </div>
                                        <div v-else class="flex items-baseline justify-center drop-shadow-sm" :class="figureTierClass(amount)">
                                            <svg v-if="tierFor(amount)" class="mr-1 h-7 w-7 self-center sm:h-8 sm:w-8"
                                                 viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                                <path d="M7.4 2h2.7l2.3 4.6-2.7 1.1L7.4 2z"/>
                                                <path d="M16.6 2h-2.7l-2.3 4.6 2.7 1.1L16.6 2z"/>
                                                <circle cx="12" cy="15.2" r="6.4"/>
                                                <path fill="#fff" d="M12 11.4l.96 1.95 2.15.31-1.56 1.52.37 2.14L12 16.86l-1.92 1.01.37-2.14-1.56-1.52 2.15-.31z"/>
                                            </svg>
                                            <span class="mr-0.5 self-start pt-2 text-2xl font-semibold text-sky-100 sm:text-3xl">{{ currency }}</span>
                                            <span class="text-5xl tracking-tight tabular-nums sm:text-6xl">{{ intPart }}</span>
                                            <span class="text-2xl font-bold text-sky-100 sm:text-3xl">.{{ decPart }}</span>
                                        </div>
                                    </div>

                                    <!-- As-of timestamp (under the figure) -->
                                    <p v-if="!loading" class="mt-3 flex items-center justify-center gap-2 text-sm text-sky-50/90">
                                        <span class="relative flex h-2 w-2">
                                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-300 opacity-75"></span>
                                            <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-300"></span>
                                        </span>
                                        <span v-if="asOf">As of {{ asOf }}</span>
                                        <span v-else>Live figure</span>
                                    </p>
                                </div>

                                <!-- soft wave divider -->
                                <svg class="absolute inset-x-0 -bottom-px h-10 w-full text-white" viewBox="0 0 500 40"
                                     preserveAspectRatio="none" aria-hidden="true">
                                    <path d="M0,22 C140,46 360,4 500,22 L500,40 L0,40 Z" fill="currentColor"/>
                                </svg>
                            </div>

                            <!-- Last 3 complete months -->
                            <div v-if="!loading && lastMonths.length" class="px-6 pt-1">
                                <p class="mb-2 text-center text-[0.7rem] font-semibold uppercase tracking-wider text-gray-400">
                                    Recent months
                                </p>
                                <ul class="space-y-1.5">
                                    <li v-for="m in lastMonths" :key="m.label"
                                        class="flex items-center justify-between rounded-xl bg-slate-50 px-4 py-2.5 ring-1 ring-slate-100">
                                        <span class="text-sm font-medium text-gray-500">{{ m.label }}</span>
                                        <span class="flex items-center gap-2">
                                            <!-- Tier chip: medal + amount (medal shown only when a tier is reached) -->
                                            <span :class="monthChipClass(m.amount)">
                                                <svg v-if="tierFor(m.amount)" class="h-4 w-4 shrink-0"
                                                     viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                                    <path d="M7.4 2h2.7l2.3 4.6-2.7 1.1L7.4 2z"/>
                                                    <path d="M16.6 2h-2.7l-2.3 4.6 2.7 1.1L16.6 2z"/>
                                                    <circle cx="12" cy="15.2" r="6.4"/>
                                                    <path fill="#fff" d="M12 11.4l.96 1.95 2.15.31-1.56 1.52.37 2.14L12 16.86l-1.92 1.01.37-2.14-1.56-1.52 2.15-.31z"/>
                                                </svg>
                                                <span class="text-base tabular-nums" :class="monthWeightClass(m.amount)">{{ currency }}{{ formatAmount(m.amount) }}</span>
                                            </span>
                                            <span class="inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 text-xs font-semibold tabular-nums"
                                                  :class="trendClass(m.trend)">
                                                <ArrowUpRightIcon v-if="m.trend === 'up'" class="h-3.5 w-3.5" aria-hidden="true"/>
                                                <ArrowDownRightIcon v-else-if="m.trend === 'down'" class="h-3.5 w-3.5" aria-hidden="true"/>
                                                <MinusSmallIcon v-else class="h-3.5 w-3.5" aria-hidden="true"/>
                                                <span v-if="m.change_pct !== null">{{ Math.abs(m.change_pct).toFixed(1) }}%</span>
                                            </span>
                                        </span>
                                    </li>
                                </ul>
                            </div>

                            <!-- Footer -->
                            <div class="px-8 pb-7 pt-4">
                                <button type="button"
                                        class="inline-flex w-full justify-center rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-600/20 transition hover:from-blue-700 hover:to-indigo-700 active:scale-[0.99]"
                                        @click="dismiss">
                                    Got it
                                </button>
                            </div>
                        </DialogPanel>
                    </TransitionChild>
                </div>
            </div>
        </Dialog>
    </TransitionRoot>
</template>

<script setup>
import { Dialog, DialogPanel, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { ArrowDownRightIcon, ArrowPathIcon, ArrowTrendingUpIcon, ArrowUpRightIcon, InformationCircleIcon, MinusSmallIcon, XMarkIcon } from '@heroicons/vue/20/solid'
import { computed, onMounted, ref } from 'vue'

const open = ref(false)
const loading = ref(true)
const refreshing = ref(false)
const showTiers = ref(false)   // tier-legend tooltip visibility
const amount = ref(0)        // final value
const display = ref(0)       // animated value
const currency = ref('$')
const asOf = ref(null)
const lastMonths = ref([])
// Achievement-tier thresholds (in dollars), e.g. { silver: 200000, gold: 220000 }.
// Supplied by the server (DashboardController::MONTHLY_SALES_TIERS); disabled
// tiers are omitted, so absence of a key means that tier is off.
const tiers = ref({})

function formatAmount(value) {
    return Number(value).toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })
}

// Whole-dollar formatting for tier targets (no cents).
function formatTarget(value) {
    return Number(value).toLocaleString(undefined, { maximumFractionDigits: 0 })
}

// Enabled tiers (highest first) for the legend tooltip, each with its target.
const tierLegend = computed(() => {
    const meta = {
        gold:   { label: 'Gold',   color: 'text-amber-500' },
        silver: { label: 'Silver', color: 'text-slate-500' },
        bronze: { label: 'Bronze', color: 'text-orange-700' },
    }
    return ['gold', 'silver', 'bronze']
        .filter((name) => tiers.value?.[name] != null)
        .map((name) => ({ name, ...meta[name], amount: tiers.value[name] }))
})

// Highest tier whose threshold the amount meets, or null if below them all.
// Evaluated gold → silver → bronze so the top achieved tier always wins.
function tierFor(value) {
    const amt = Number(value) || 0
    for (const name of ['gold', 'silver', 'bronze']) {
        const threshold = tiers.value?.[name]
        if (threshold != null && amt >= threshold) return name
    }
    return null
}

// Recent-month chip (on the white card): tinted background + a coloured ring/
// border so the tier reads clearly. The medal and amount inherit the chip's
// text colour via currentColor. Normal (no tier) → no chip, plain dark text.
function monthChipClass(value) {
    const base = 'inline-flex items-center gap-1.5'
    const chip = ' rounded-full px-2.5 py-1 ring-1 shadow-sm'
    switch (tierFor(value)) {
        case 'gold':   return base + chip + ' bg-gradient-to-b from-amber-50 to-amber-100 text-amber-600 ring-amber-400/80'
        case 'silver': return base + chip + ' bg-gradient-to-b from-slate-100 to-slate-300 text-slate-700 ring-slate-400'
        case 'bronze': return base + chip + ' bg-gradient-to-b from-orange-50 to-orange-100 text-orange-700 ring-orange-400/80'
        default:       return base + ' text-gray-800'
    }
}

// Font weight for the recent-month amount, escalating with tier.
function monthWeightClass(value) {
    switch (tierFor(value)) {
        case 'gold':   return 'font-black'
        case 'silver': return 'font-extrabold'
        case 'bronze': return 'font-bold'
        default:       return 'font-bold'
    }
}

// The big current figure (on the blue gradient header). Lighter shades so the
// tier colour reads against the blue, plus a soft outline glow for contrast.
function figureTierClass(value) {
    switch (tierFor(value)) {
        case 'gold':   return 'text-amber-300 font-black [text-shadow:0_1px_8px_rgba(252,211,77,0.55)]'
        case 'silver': return 'text-slate-100 font-extrabold [text-shadow:0_1px_8px_rgba(226,232,240,0.55)]'
        case 'bronze': return 'text-orange-200 font-bold [text-shadow:0_1px_8px_rgba(254,215,170,0.55)]'
        default:       return 'text-white font-extrabold'
    }
}

function trendClass(trend) {
    if (trend === 'up') return 'bg-emerald-50 text-emerald-600'
    if (trend === 'down') return 'bg-rose-50 text-rose-600'
    return 'bg-slate-100 text-slate-500'
}

const intPart = computed(() =>
    Math.floor(display.value).toLocaleString(undefined, { maximumFractionDigits: 0 })
)
const decPart = computed(() => {
    const cents = Math.round((display.value - Math.floor(display.value)) * 100)
    return String(cents).padStart(2, '0')
})

function dismiss() {
    open.value = false
}

// Manual on-demand re-pull of the live figure. Uses ?refresh=1 so the server
// bypasses its once-per-session gate without disturbing the auto-show flag.
async function refresh() {
    if (refreshing.value) return
    refreshing.value = true
    try {
        const { data } = await window.axios.get(route('dashboard.monthly-sales-popup'), {
            params: { refresh: 1 },
        })
        if (data && data.show) {
            amount.value = Number(data.amount) || 0
            currency.value = data.currency || '$'
            asOf.value = data.as_of || null
            lastMonths.value = Array.isArray(data.last_months) ? data.last_months : []
            tiers.value = data.tiers && typeof data.tiers === 'object' ? data.tiers : {}
            countUp(amount.value)
        }
    } catch (e) {
        // Keep the existing figure on a failed refresh rather than blanking it.
    } finally {
        refreshing.value = false
    }
}

// Ease-out count-up animation from 0 to the real figure.
function countUp(target) {
    const duration = 900
    const start = performance.now()
    const step = (now) => {
        const t = Math.min((now - start) / duration, 1)
        const eased = 1 - Math.pow(1 - t, 3)
        display.value = target * eased
        if (t < 1) {
            requestAnimationFrame(step)
        } else {
            display.value = target
        }
    }
    requestAnimationFrame(step)
}

onMounted(async () => {
    // "Show once per login session" is enforced server-side (Laravel session
    // flag), so it re-shows after a logout→login and won't repeat on in-session
    // navigation.
    try {
        const { data } = await window.axios.get(route('dashboard.monthly-sales-popup'))
        if (!data || !data.show) {
            return
        }
        amount.value = Number(data.amount) || 0
        currency.value = data.currency || '$'
        asOf.value = data.as_of || null
        lastMonths.value = Array.isArray(data.last_months) ? data.last_months : []
        tiers.value = data.tiers && typeof data.tiers === 'object' ? data.tiers : {}
        loading.value = false
        open.value = true
        countUp(amount.value)
    } catch (e) {
        // Stay silent on error rather than popping an empty card.
        loading.value = false
    }
})
</script>
