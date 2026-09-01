<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    reports: { type: Object, required: true },
    providers: { type: Array, default: () => ['nets'] },
    filters: { type: Object, default: () => ({}) },
});

const statusLabels = {
    uploaded: 'Uploaded',
    matching: 'Matching…',
    review: 'Review',
    synced: 'Synced',
    failed: 'Failed',
};

const statusClass = (s) => ({
    uploaded: 'bg-gray-100 text-gray-700',
    matching: 'bg-amber-100 text-amber-800',
    review: 'bg-blue-100 text-blue-800',
    synced: 'bg-green-100 text-green-800',
    failed: 'bg-red-100 text-red-700',
}[s] || 'bg-gray-100 text-gray-700');

const filters = ref({
    status: props.filters.status || 'all',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
});

function applyFilters() {
    router.get('/card-settlements', { ...filters.value }, { preserveState: true, replace: true });
}

const uploadForm = useForm({ provider: props.providers[0] || 'nets', file: null });
const uploadProgress = ref(0);

function submitUpload() {
    uploadForm.post('/card-settlements', {
        forceFormData: true,
        preserveScroll: true,
        onProgress: (e) => { uploadProgress.value = e ? Math.round(e.percentage) : 0; },
        onSuccess: () => { uploadForm.reset(); uploadProgress.value = 0; },
        onError: () => { uploadProgress.value = 0; },
    });
}
</script>

<template>
<Head title="Card Settlement" />
<BreezeAuthenticatedLayout>
    <template #header>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">Card Settlement</h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
        <!-- upload -->
        <div class="bg-white rounded-md border p-3 mb-3">
            <div class="text-sm font-semibold text-gray-700 mb-2">Upload settlement report</div>
            <div class="flex flex-wrap items-center gap-2">
                <select v-model="uploadForm.provider" class="border rounded-md px-3 py-2 text-sm">
                    <option v-for="p in providers" :key="p" :value="p">{{ p.toUpperCase() }}</option>
                </select>
                <input type="file" accept=".csv,text/csv" class="text-sm"
                    @change="uploadForm.file = $event.target.files[0]" />
                <button @click="submitUpload" :disabled="!uploadForm.file || uploadForm.processing"
                    class="bg-teal-600 text-white rounded-md px-4 py-2 text-sm font-medium hover:bg-teal-700 disabled:opacity-50">
                    {{ uploadForm.processing ? ('Uploading… ' + uploadProgress + '%') : 'Upload & Match' }}
                </button>
                <span v-if="uploadForm.errors.file" class="text-xs text-red-600">{{ uploadForm.errors.file }}</span>
            </div>
            <p class="text-xs text-gray-400 mt-2">
                NETS: upload the raw MerchantConnect daily CSV (MCONNECT_…_STDRPT01_….csv). Avoid opening
                it in Excel first — a re-saved file loses the hour of each transaction time and rows can
                only be matched approximately.
            </p>
        </div>

        <!-- filters -->
        <div class="bg-white rounded-md border p-3 mb-3 flex flex-wrap gap-2 items-center">
            <select v-model="filters.status" class="border rounded-md px-3 py-2 text-sm" @change="applyFilters">
                <option value="all">All statuses</option>
                <option v-for="(label, key) in statusLabels" :key="key" :value="key">{{ label }}</option>
            </select>
            <input type="date" v-model="filters.date_from" class="border rounded-md px-3 py-2 text-sm" />
            <input type="date" v-model="filters.date_to" class="border rounded-md px-3 py-2 text-sm" />
            <button @click="applyFilters" class="bg-teal-600 text-white rounded-md px-4 py-2 text-sm font-medium hover:bg-teal-700">Search</button>
        </div>

        <!-- table -->
        <div class="bg-white rounded-md border overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr class="[&>th]:px-4 [&>th]:py-2 [&>th]:whitespace-nowrap [&>th]:text-left">
                        <th>Cutover</th>
                        <th>File</th>
                        <th class="text-center">Status</th>
                        <th class="text-right">Purchases</th>
                        <th class="text-right">Matched</th>
                        <th class="text-right">Queries</th>
                        <th class="text-right">Duplicates</th>
                        <th class="text-right">Synced</th>
                        <th>Uploaded</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in reports.data" :key="r.id" class="border-t hover:bg-gray-50 cursor-pointer"
                        @click="router.visit('/card-settlements/' + r.id)">
                        <td class="px-4 py-3 whitespace-nowrap font-semibold text-teal-700">{{ r.cutover_date || '—' }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">
                            {{ r.original_filename }}
                            <span class="ml-1 text-[10px] uppercase text-gray-400">{{ r.provider }}</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="inline-block text-xs font-bold px-2 py-1 rounded-full" :class="statusClass(r.status)">
                                {{ statusLabels[r.status] || r.status }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">{{ r.purchase_rows }}</td>
                        <td class="px-4 py-3 text-right text-green-700 font-medium">{{ r.matched_count }}</td>
                        <td class="px-4 py-3 text-right" :class="(r.unmatched_count + r.ambiguous_count) ? 'text-amber-700 font-medium' : 'text-gray-300'">
                            {{ r.unmatched_count + r.ambiguous_count }}
                        </td>
                        <td class="px-4 py-3 text-right" :class="r.duplicate_count ? 'text-gray-600' : 'text-gray-300'">{{ r.duplicate_count }}</td>
                        <td class="px-4 py-3 text-right">
                            <span v-if="r.synced_at">{{ r.synced_count }}</span>
                            <span v-else class="text-gray-300">—</span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-gray-500">{{ r.created_at }}</td>
                    </tr>
                    <tr v-if="!reports.data.length">
                        <td colspan="9" class="px-4 py-8 text-center text-gray-400">No reports uploaded yet.</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- pagination -->
        <div class="flex items-center justify-between mt-3 text-sm text-gray-600">
            <span>Showing {{ reports.from || 0 }}–{{ reports.to || 0 }} of {{ reports.total }}</span>
            <div class="flex gap-1">
                <template v-for="(l, i) in reports.links" :key="i">
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
