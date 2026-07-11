<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    settlements: { type: Object, required: true },
    counts: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
});

const statusLabels = {
    open: 'Open',
    closed: 'Closed',
};

const filters = ref({
    search: props.filters.search || '',
    status: props.filters.status || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
});

function applyFilters() {
    router.get('/refund-settlements', { ...filters.value }, { preserveState: true, replace: true });
}
function clearFilters() {
    filters.value = { search: '', status: '', date_from: '', date_to: '' };
    applyFilters();
}
function pickStatus(key) {
    filters.value.status = filters.value.status === key ? '' : key;
    applyFilters();
}

const statusClass = (s) => ({
    open: 'bg-amber-100 text-amber-800',
    closed: 'bg-blue-100 text-blue-800',
}[s] || 'bg-gray-100 text-gray-700');
</script>

<template>
<Head title="Refund Settlement" />
<BreezeAuthenticatedLayout>
    <template #header>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Refund Settlement</h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
        <!-- status chips -->
        <div class="flex flex-wrap gap-2 mb-3">
            <span v-for="(label, key) in statusLabels" :key="key"
                class="text-xs font-semibold px-3 py-1.5 rounded-full border bg-white cursor-pointer"
                :class="filters.status === key ? 'border-teal-500 text-teal-700' : 'border-gray-200 text-gray-600'"
                @click="pickStatus(key)">
                {{ label }} <b class="text-gray-900">{{ counts[key] || 0 }}</b>
            </span>
        </div>

        <!-- filters -->
        <div class="bg-white rounded-md border p-3 mb-3 grid grid-cols-1 md:grid-cols-6 gap-2 items-start">
            <input v-model="filters.search" placeholder="RST reference" class="border rounded-md px-3 py-2 text-sm md:col-span-2" @keyup.enter="applyFilters" />
            <input type="date" v-model="filters.date_from" class="border rounded-md px-3 py-2 text-sm" />
            <input type="date" v-model="filters.date_to" class="border rounded-md px-3 py-2 text-sm" />
            <div class="flex gap-2 md:col-span-2">
                <button @click="applyFilters" class="bg-teal-600 text-white rounded-md px-4 py-2 text-sm font-medium hover:bg-teal-700">Search</button>
                <button @click="clearFilters" class="bg-gray-100 text-gray-700 rounded-md px-3 py-2 text-sm border">Clear</button>
            </div>
        </div>

        <!-- table -->
        <div class="bg-white rounded-md border overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr class="[&>th]:px-4 [&>th]:py-2 [&>th]:whitespace-nowrap [&>th]:text-left">
                        <th>Settlement</th>
                        <th>Date</th>
                        <th>Payout Group / Operator</th>
                        <th class="text-center">Status</th>
                        <th class="text-right">PayNow</th>
                        <th class="text-right">PayPal</th>
                        <th class="text-right">Total</th>
                        <th class="text-center">Done</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="s in settlements.data" :key="s.id" class="border-t hover:bg-gray-50 cursor-pointer" @click="router.visit('/refund-settlements/' + s.id)">
                        <td class="px-4 py-3 whitespace-nowrap">
                            <span class="font-semibold text-teal-700">{{ s.reference }}</span>
                            <span v-if="s.is_stale" class="ml-2 text-[10px] font-semibold uppercase tracking-wide bg-red-100 text-red-700 px-1.5 py-0.5 rounded">stale</span>
                        </td>
                        <td class="px-4 py-3">{{ s.settlement_date }}</td>
                        <td class="px-4 py-3">{{ s.head }}</td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-block text-xs font-bold px-2 py-1 rounded-full" :class="statusClass(s.status)">{{ statusLabels[s.status] || s.status }}</span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <span v-if="s.paynow_count">{{ s.paynow_count }} · ${{ s.paynow_total }}</span>
                            <span v-else class="text-gray-300">—</span>
                        </td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <span v-if="s.paypal_count">{{ s.paypal_count }} · ${{ s.paypal_total }}</span>
                            <span v-else class="text-gray-300">—</span>
                        </td>
                        <td class="px-4 py-3 text-right font-medium whitespace-nowrap">${{ s.total }}</td>
                        <td class="px-4 py-3 text-center whitespace-nowrap">{{ s.done_count }} / {{ s.count }}</td>
                    </tr>
                    <tr v-if="!settlements.data.length"><td colspan="8" class="px-4 py-8 text-center text-gray-400">No settlements yet. Push approved refunds from the Refund Requests page.</td></tr>
                </tbody>
            </table>
        </div>

        <!-- pagination -->
        <div class="flex items-center justify-between mt-3 text-sm text-gray-600">
            <span>Showing {{ settlements.from || 0 }}–{{ settlements.to || 0 }} of {{ settlements.total }}</span>
            <div class="flex gap-1">
                <template v-for="(l, i) in settlements.links" :key="i">
                    <Link v-if="l.url" :href="l.url" v-html="l.label" preserve-scroll
                        class="px-3 py-1.5 rounded border text-sm"
                        :class="l.active ? 'bg-teal-600 text-white border-teal-600' : 'bg-white text-gray-600'" />
                    <span v-else v-html="l.label" class="px-3 py-1.5 rounded border text-sm text-gray-300"></span>
                </template>
            </div>
        </div>
    </div>
</BreezeAuthenticatedLayout>
</template>
