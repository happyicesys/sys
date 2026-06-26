<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    tickets: { type: Object, required: true },
    counts: { type: Object, default: () => ({}) },
    filters: { type: Object, default: () => ({}) },
    statuses: { type: Object, default: () => ({}) },
});

const filters = ref({
    search: props.filters.search || '',
    status: props.filters.status || '',
    refund_method: props.filters.refund_method || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
});

function applyFilters() {
    router.get('/refunds', filters.value, { preserveState: true, replace: true });
}
function clearFilters() {
    filters.value = { search: '', status: '', refund_method: '', date_from: '', date_to: '' };
    applyFilters();
}

const statusClass = (s) => ({
    submitted: 'bg-yellow-100 text-yellow-800',
    auto_resolved: 'bg-cyan-100 text-cyan-800',
    verified: 'bg-blue-100 text-blue-800',
    rejected: 'bg-red-100 text-red-800',
    pending_approval: 'bg-indigo-100 text-indigo-800',
    approved: 'bg-indigo-100 text-indigo-800',
    pending_transfer_info: 'bg-orange-100 text-orange-800',
    scheduled: 'bg-violet-100 text-violet-800',
    completed: 'bg-green-100 text-green-800',
}[s] || 'bg-gray-100 text-gray-700');

const recClass = (r) => ({
    proceed: 'text-green-700', review: 'text-amber-700', reject: 'text-red-700',
}[r] || 'text-gray-500');
</script>

<template>
<Head title="Refunds" />
<BreezeAuthenticatedLayout>
    <template #header>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Refund Requests</h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
        <!-- status chips -->
        <div class="flex flex-wrap gap-2 mb-3">
            <span v-for="(label, key) in statuses" :key="key"
                class="text-xs font-semibold px-3 py-1.5 rounded-full border bg-white cursor-pointer"
                :class="filters.status === key ? 'border-teal-500 text-teal-700' : 'border-gray-200 text-gray-600'"
                @click="filters.status = filters.status === key ? '' : key; applyFilters()">
                {{ label }} <b class="text-gray-900">{{ counts[key] || 0 }}</b>
            </span>
        </div>

        <!-- filters -->
        <div class="bg-white rounded-md border p-3 mb-3 grid grid-cols-1 md:grid-cols-6 gap-2">
            <input v-model="filters.search" placeholder="Ref / machine / email" class="border rounded-md px-3 py-2 text-sm md:col-span-2" @keyup.enter="applyFilters" />
            <select v-model="filters.refund_method" class="border rounded-md px-3 py-2 text-sm">
                <option value="">All methods</option>
                <option value="paynow">PayNow</option>
                <option value="paypal">PayPal</option>
                <option value="nayax_auto">Nayax (auto)</option>
                <option value="none">None</option>
            </select>
            <input type="date" v-model="filters.date_from" class="border rounded-md px-3 py-2 text-sm" />
            <input type="date" v-model="filters.date_to" class="border rounded-md px-3 py-2 text-sm" />
            <div class="flex gap-2">
                <button @click="applyFilters" class="bg-teal-600 text-white rounded-md px-4 py-2 text-sm font-medium hover:bg-teal-700">Search</button>
                <button @click="clearFilters" class="bg-gray-100 text-gray-700 rounded-md px-3 py-2 text-sm border">Clear</button>
            </div>
        </div>

        <!-- table -->
        <div class="bg-white rounded-md border overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr>
                        <th class="text-left px-4 py-3">Ref</th>
                        <th class="text-left px-4 py-3">Machine</th>
                        <th class="text-left px-4 py-3">Amount</th>
                        <th class="text-left px-4 py-3">Method</th>
                        <th class="text-left px-4 py-3">Channel</th>
                        <th class="text-left px-4 py-3">Advice</th>
                        <th class="text-left px-4 py-3">Age</th>
                        <th class="text-left px-4 py-3">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="t in tickets.data" :key="t.id" class="border-t hover:bg-gray-50 cursor-pointer" @click="router.get('/refunds/' + t.id)">
                        <td class="px-4 py-3 font-semibold text-teal-700">{{ t.reference }}</td>
                        <td class="px-4 py-3">{{ t.vend_code }}</td>
                        <td class="px-4 py-3">${{ t.amount }}</td>
                        <td class="px-4 py-3">{{ t.refund_method }}</td>
                        <td class="px-4 py-3">{{ t.payment_channel }}</td>
                        <td class="px-4 py-3 font-semibold" :class="recClass(t.recommendation)">{{ t.recommendation }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ t.created_ago }}</td>
                        <td class="px-4 py-3"><span class="text-xs font-bold px-2 py-1 rounded-full" :class="statusClass(t.status)">{{ statuses[t.status] || t.status }}</span></td>
                    </tr>
                    <tr v-if="!tickets.data.length"><td colspan="8" class="px-4 py-8 text-center text-gray-400">No refund tickets found.</td></tr>
                </tbody>
            </table>
        </div>

        <!-- pagination -->
        <div class="flex items-center justify-between mt-3 text-sm text-gray-600">
            <span>Showing {{ tickets.from || 0 }}–{{ tickets.to || 0 }} of {{ tickets.total }}</span>
            <div class="flex gap-1">
                <template v-for="(l, i) in tickets.links" :key="i">
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
