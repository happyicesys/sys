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
                                        <div v-else class="flex items-baseline justify-center text-white drop-shadow-sm">
                                            <span class="mr-0.5 self-start pt-2 text-2xl font-semibold text-sky-100 sm:text-3xl">{{ currency }}</span>
                                            <span class="text-5xl font-extrabold tracking-tight tabular-nums sm:text-6xl">{{ intPart }}</span>
                                            <span class="text-2xl font-bold text-sky-100 sm:text-3xl">.{{ decPart }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- soft wave divider -->
                                <svg class="absolute inset-x-0 -bottom-px h-10 w-full text-white" viewBox="0 0 500 40"
                                     preserveAspectRatio="none" aria-hidden="true">
                                    <path d="M0,22 C140,46 360,4 500,22 L500,40 L0,40 Z" fill="currentColor"/>
                                </svg>
                            </div>

                            <!-- Footer: as-of timestamp -->
                            <div class="px-8 pb-7 pt-3">
                                <p class="flex items-center justify-center gap-2 text-sm text-gray-500">
                                    <span class="relative flex h-2 w-2">
                                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-75"></span>
                                        <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-500"></span>
                                    </span>
                                    <span v-if="asOf">As of {{ asOf }}</span>
                                    <span v-else>Live figure</span>
                                </p>
                                <button type="button"
                                        class="mt-5 inline-flex w-full justify-center rounded-xl bg-gradient-to-r from-blue-600 to-indigo-600 px-4 py-3 text-sm font-semibold text-white shadow-lg shadow-blue-600/20 transition hover:from-blue-700 hover:to-indigo-700 active:scale-[0.99]"
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
import { ArrowTrendingUpIcon, XMarkIcon } from '@heroicons/vue/20/solid'
import { computed, onMounted, ref } from 'vue'

const open = ref(false)
const loading = ref(true)
const amount = ref(0)        // final value
const display = ref(0)       // animated value
const currency = ref('$')
const asOf = ref(null)

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
        loading.value = false
        open.value = true
        countUp(amount.value)
    } catch (e) {
        // Stay silent on error rather than popping an empty card.
        loading.value = false
    }
})
</script>
