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
                                                class="flex items-center justify-between gap-2 rounded-lg px-1.5 py-1">
                                                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-bold"
                                                      :class="t.badge">{{ t.text }}</span>
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
                            <div class="relative overflow-hidden bg-gradient-to-br from-sky-400 via-blue-600 to-indigo-700 px-8 pb-16 pt-16">
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
                                            <span v-if="bonusFor(amount)"
                                                  class="mr-2 self-center inline-flex items-center rounded-full px-2.5 py-1 text-sm font-bold shadow-sm"
                                                  :class="bonusFor(amount).badge">{{ bonusFor(amount).text }}</span>
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
                            <div v-if="!loading && lastMonths.length" class="px-4 pt-1 sm:px-6">
                                <p class="mb-2 text-center text-[0.7rem] font-semibold uppercase tracking-wider text-gray-400">
                                    Recent months
                                </p>
                                <ul class="space-y-1.5">
                                    <li v-for="m in lastMonths" :key="m.label"
                                        class="flex items-center gap-2 rounded-xl bg-slate-50 px-3 py-2.5 ring-1 ring-slate-100 sm:gap-3 sm:px-4">
                                        <span class="w-11 shrink-0 text-sm font-medium text-gray-500 sm:w-14">{{ m.label }}</span>
                                        <!-- Amount column: right-aligned so all amounts share a clean right edge -->
                                        <span class="flex min-w-0 flex-1 items-center justify-end gap-1.5">
                                            <!-- Bonus badge: shown only when a tier is reached -->
                                            <span v-if="bonusFor(m.amount)"
                                                  class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-xs font-bold"
                                                  :class="bonusFor(m.amount).badge">{{ bonusFor(m.amount).text }}</span>
                                            <span class="text-base font-bold tabular-nums text-gray-800">{{ currency }}{{ formatAmount(m.amount) }}</span>
                                        </span>
                                        <!-- Trend column: fixed width + right-aligned number so percentages line up -->
                                        <span class="flex w-[4.25rem] shrink-0 justify-end sm:w-[4.75rem]">
                                            <span class="inline-flex items-center gap-0.5 rounded-full px-1.5 py-0.5 text-xs font-semibold tabular-nums"
                                                  :class="trendClass(m.trend)">
                                                <ArrowUpRightIcon v-if="m.trend === 'up'" class="h-3.5 w-3.5 shrink-0" aria-hidden="true"/>
                                                <ArrowDownRightIcon v-else-if="m.trend === 'down'" class="h-3.5 w-3.5 shrink-0" aria-hidden="true"/>
                                                <MinusSmallIcon v-else class="h-3.5 w-3.5 shrink-0" aria-hidden="true"/>
                                                <span v-if="m.change_pct !== null" class="w-9 text-right">{{ Math.abs(m.change_pct).toFixed(1) }}%</span>
                                            </span>
                                        </span>
                                    </li>
                                </ul>
                            </div>

                            <!-- Bonus-policy fineprint -->
                            <div v-if="!loading" class="px-6 pt-4 text-left">
                                <p class="text-[0.7rem] leading-relaxed text-gray-500">
                                    <span class="font-semibold text-gray-600">*集体销售奖金制度*：</span>一旦达标，全体职员将获得销售奖金。<br>
                                    <span class="font-semibold text-gray-600">销售奖金发放条件（个别员工）：</span><br>
                                    • 当月无 MC、无无薪假，可获得 100% 销售奖金。<br>
                                    • 当月如有 MC 或无薪假累计 1–2 天，可获得 50% 销售奖金。<br>
                                    • 当月如有 MC 或无薪假累计 3 天或以上，则不享有该月销售奖金。
                                </p>
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

// Staff bonus earned per achieved tier. The label is the bonus payout (not the
// sales target) and is rendered as a coloured badge in place of the old medals.
// bronze = lowest tier, gold = highest. Adjust labels/colours here if the bonus
// scheme changes.
const tierBonus = {
    bronze: { text: '$100奖金', badge: 'bg-yellow-100 text-yellow-800 ring-1 ring-inset ring-yellow-300' },
    silver: { text: '$300奖金', badge: 'bg-emerald-100 text-emerald-700 ring-1 ring-inset ring-emerald-300' },
    gold:   { text: '$500奖金', badge: 'bg-amber-100 text-amber-800 ring-1 ring-inset ring-amber-300' },
}

// Bonus badge config for the highest tier the amount reaches, or null if none.
function bonusFor(value) {
    const name = tierFor(value)
    return name ? tierBonus[name] : null
}

// Enabled tiers (highest first) for the legend tooltip, each with its target
// and bonus badge.
const tierLegend = computed(() => {
    return ['gold', 'silver', 'bronze']
        .filter((name) => tiers.value?.[name] != null)
        .map((name) => ({ name, ...tierBonus[name], amount: tiers.value[name] }))
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
    // Persist the once-per-session flag server-side so the popup won't
    // auto-show again until the next login. The auto-fetch deliberately does
    // NOT set this flag, so the popup survives layout remounts and stays
    // visible until the user explicitly closes it here. Fire-and-forget:
    // closing must never block on the network.
    try {
        window.axios.get(route('dashboard.monthly-sales-popup'), {
            params: { dismiss: 1 },
        })
    } catch (e) {
        // Non-fatal: worst case the popup may re-show on a later remount.
    }
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
