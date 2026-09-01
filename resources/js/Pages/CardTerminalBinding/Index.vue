<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    bindings: { type: Object, required: true },
    providers: { type: Array, default: () => ['nets'] },
    filters: { type: Object, default: () => ({}) },
});

const filters = ref({
    provider: props.filters.provider || 'all',
    search: props.filters.search || '',
    active_only: props.filters.active_only ?? true,
});

function applyFilters() {
    router.get('/card-terminal-bindings', { ...filters.value, active_only: filters.value.active_only ? 1 : 0 },
        { preserveState: true, replace: true });
}

const blankForm = { provider: props.providers[0] || 'nets', terminal_id: '', vend_code: '', bound_from: '', bound_until: '', remarks: '' };
const form = useForm({ ...blankForm });
const editingId = ref(null);

function startCreate() {
    editingId.value = null;
    Object.assign(form, { ...blankForm });
    form.clearErrors();
    showForm.value = true;
}
function startEdit(b) {
    editingId.value = b.id;
    Object.assign(form, {
        provider: b.provider,
        terminal_id: b.terminal_id,
        vend_code: b.vend_code,
        bound_from: b.bound_from || '',
        bound_until: b.bound_until || '',
        remarks: b.remarks || '',
    });
    form.clearErrors();
    showForm.value = true;
}
const showForm = ref(false);

function submit() {
    const opts = { preserveScroll: true, onSuccess: () => { showForm.value = false; } };
    if (editingId.value) {
        form.put(`/card-terminal-bindings/${editingId.value}`, opts);
    } else {
        form.post('/card-terminal-bindings', opts);
    }
}

function destroyBinding(b) {
    if (!confirm(`Delete binding ${b.terminal_id} → ${b.vend_code}?`)) return;
    router.delete(`/card-terminal-bindings/${b.id}`, { preserveScroll: true });
}
</script>

<template>
<Head title="Card Terminal Bindings" />
<BreezeAuthenticatedLayout>
    <template #header>
        <div class="flex items-center justify-between flex-wrap gap-2">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">Card Terminal Bindings</h2>
            <button @click="startCreate" class="bg-teal-600 text-white rounded-md px-4 py-2 text-sm font-medium hover:bg-teal-700">New Binding</button>
        </div>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
        <!-- create / edit -->
        <div v-if="showForm" class="bg-white rounded-md border p-3 mb-3">
            <div class="text-sm font-semibold text-gray-700 mb-2">{{ editingId ? 'Edit binding' : 'New binding' }}</div>
            <div class="grid grid-cols-2 md:grid-cols-6 gap-2 items-start text-sm">
                <select v-model="form.provider" class="border rounded-md px-3 py-2">
                    <option v-for="p in providers" :key="p" :value="p">{{ p.toUpperCase() }}</option>
                </select>
                <div>
                    <input v-model="form.terminal_id" placeholder="Terminal ID (TID)" class="border rounded-md px-3 py-2 w-full" />
                    <div v-if="form.errors.terminal_id" class="text-xs text-red-600 mt-0.5">{{ form.errors.terminal_id }}</div>
                </div>
                <div>
                    <input v-model="form.vend_code" placeholder="Machine code" class="border rounded-md px-3 py-2 w-full" />
                    <div v-if="form.errors.vend_code" class="text-xs text-red-600 mt-0.5">{{ form.errors.vend_code }}</div>
                </div>
                <div>
                    <input type="date" v-model="form.bound_from" title="Bound from" class="border rounded-md px-3 py-2 w-full" />
                    <div class="text-[10px] text-gray-400">Bound from</div>
                </div>
                <div>
                    <input type="date" v-model="form.bound_until" title="Bound until (blank = current)" class="border rounded-md px-3 py-2 w-full" />
                    <div class="text-[10px] text-gray-400">Bound until (blank = current)</div>
                    <div v-if="form.errors.bound_until" class="text-xs text-red-600 mt-0.5">{{ form.errors.bound_until }}</div>
                </div>
                <div class="flex gap-2">
                    <button @click="submit" :disabled="form.processing" class="bg-teal-600 text-white rounded-md px-4 py-2 font-medium hover:bg-teal-700 disabled:opacity-50">Save</button>
                    <button @click="showForm = false" class="bg-gray-100 border rounded-md px-3 py-2 text-gray-600">Cancel</button>
                </div>
                <input v-model="form.remarks" placeholder="Remarks" class="border rounded-md px-3 py-2 md:col-span-3" />
            </div>
        </div>

        <!-- filters -->
        <div class="bg-white rounded-md border p-3 mb-3 flex flex-wrap gap-2 items-center text-sm">
            <select v-model="filters.provider" class="border rounded-md px-3 py-2" @change="applyFilters">
                <option value="all">All providers</option>
                <option v-for="p in providers" :key="p" :value="p">{{ p.toUpperCase() }}</option>
            </select>
            <input v-model="filters.search" placeholder="Terminal ID or machine code" class="border rounded-md px-3 py-2 w-56" @keyup.enter="applyFilters" />
            <label class="flex items-center gap-1.5 text-gray-600">
                <input type="checkbox" v-model="filters.active_only" @change="applyFilters" />
                Current bindings only
            </label>
            <button @click="applyFilters" class="bg-teal-600 text-white rounded-md px-4 py-2 font-medium hover:bg-teal-700">Search</button>
        </div>

        <!-- table -->
        <div class="bg-white rounded-md border overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-500 text-xs uppercase">
                    <tr class="[&>th]:px-4 [&>th]:py-2 [&>th]:whitespace-nowrap [&>th]:text-left">
                        <th>Provider</th>
                        <th>Terminal ID</th>
                        <th>Machine</th>
                        <th>Bound From</th>
                        <th>Bound Until</th>
                        <th>Remarks</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="b in bindings.data" :key="b.id" class="border-t hover:bg-gray-50">
                        <td class="px-4 py-2 uppercase text-gray-500">{{ b.provider }}</td>
                        <td class="px-4 py-2 font-semibold text-teal-700 whitespace-nowrap">{{ b.terminal_id }}</td>
                        <td class="px-4 py-2 whitespace-nowrap">{{ b.vend_code }} <span class="text-gray-400">{{ b.vend_name }}</span></td>
                        <td class="px-4 py-2 whitespace-nowrap">{{ b.bound_from || '—' }}</td>
                        <td class="px-4 py-2 whitespace-nowrap">
                            <span v-if="b.bound_until">{{ b.bound_until }}</span>
                            <span v-else class="text-green-600 text-xs font-semibold">current</span>
                        </td>
                        <td class="px-4 py-2 text-gray-500">{{ b.remarks }}</td>
                        <td class="px-4 py-2 whitespace-nowrap text-right">
                            <button @click="startEdit(b)" class="text-xs border rounded px-2 py-1 text-gray-600 hover:bg-gray-50 mr-1">Edit</button>
                            <button @click="destroyBinding(b)" class="text-xs border border-red-200 rounded px-2 py-1 text-red-600 hover:bg-red-50">Delete</button>
                        </td>
                    </tr>
                    <tr v-if="!bindings.data.length">
                        <td colspan="7" class="px-4 py-8 text-center text-gray-400">
                            No bindings. Seed from the NETS sheet with<br>
                            <code class="text-xs">php artisan card-settlement:import-bindings database/data/card_terminal_bindings_nets_2026-08.csv --apply</code>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- pagination -->
        <div class="flex items-center justify-between mt-3 text-sm text-gray-600">
            <span>Showing {{ bindings.from || 0 }}–{{ bindings.to || 0 }} of {{ bindings.total }}</span>
            <div class="flex gap-1">
                <template v-for="(l, i) in bindings.links" :key="i">
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
