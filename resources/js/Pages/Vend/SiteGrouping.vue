<template>

  <Head title="Site Grouping" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex items-center justify-between gap-3">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          Site Grouping
        </h2>
        <button type="button" @click="openCreate"
          class="inline-flex items-center rounded-md bg-emerald-600 px-3 py-2 text-sm font-medium text-white hover:bg-emerald-700 shrink-0">
          + New Group
        </button>
      </div>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">

      <p class="text-sm text-gray-500 mb-3 max-w-3xl">
        Group co-located Sites into a cluster, then manage membership here instead of
        typing the same label on each Site. A Site belongs to at most one group. Turning
        on <span class="font-medium">"Grouped?"</span> on the Operation Dashboard makes a
        cluster "travel together" — if any member matches your filters, all of them show.
      </p>

      <!-- ===================== TOOLBAR ===================== -->
      <div class="bg-white rounded-md border my-3 px-3 py-3">
        <div class="flex flex-wrap items-end gap-3">
          <div v-if="canPickOperator" class="min-w-[240px]">
            <label class="block text-sm font-medium text-gray-700">Operator</label>
            <MultiSelect class="mt-1" v-model="operatorFilter" :options="operatorOptions"
              valueProp="id" trackBy="id" label="name" mode="tags" placeholder="All operators"
              @change="applyOperatorFilter" />
          </div>

          <div class="min-w-[220px] flex-1">
            <label class="block text-sm font-medium text-gray-700">Find a group</label>
            <input v-model="nameFilter" type="text" placeholder="Filter by group name…"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
          </div>
        </div>
      </div>

      <!-- ===================== EMPTY STATE ===================== -->
      <div v-if="!visibleGroups.length"
        class="bg-amber-50 border border-amber-200 text-amber-800 rounded-md px-4 py-3 text-sm">
        No groups {{ nameFilter ? 'match your filter' : 'yet' }}. Create one with
        <span class="font-medium">"+ New Group"</span>, then add Sites to it.
      </div>

      <!-- ===================== GROUP CARDS ===================== -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
        <div v-for="group in visibleGroups" :key="group.id"
          class="bg-white rounded-md border shadow-sm flex flex-col">

          <!-- header -->
          <div class="flex items-start justify-between px-4 py-3 border-b">
            <div>
              <div class="flex items-center gap-2">
                <h3 class="font-semibold text-gray-800">{{ group.name }}</h3>
                <span v-if="group.operator_name"
                  class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">
                  {{ group.operator_name }}
                </span>
                <span class="inline-flex items-center rounded-full bg-indigo-50 px-2 py-0.5 text-xs text-indigo-700">
                  {{ group.member_count }} site{{ group.member_count === 1 ? '' : 's' }}
                </span>
              </div>
              <p v-if="group.notes" class="text-xs text-gray-500 mt-1 whitespace-pre-line">{{ group.notes }}</p>
            </div>
            <div class="flex items-center gap-1 shrink-0">
              <button type="button" @click="openEdit(group)"
                class="rounded p-1 text-gray-400 hover:text-indigo-600 hover:bg-gray-50" title="Rename / notes">
                <PencilSquareIcon class="w-5 h-5" />
              </button>
              <button type="button" @click="destroyGroup(group)"
                class="rounded p-1 text-gray-400 hover:text-red-600 hover:bg-gray-50" title="Delete group">
                <TrashIcon class="w-5 h-5" />
              </button>
            </div>
          </div>

          <!-- members -->
          <div class="px-4 py-3 flex-1">
            <div v-if="!group.members.length" class="text-xs text-gray-400 italic mb-2">
              No sites in this group yet.
            </div>
            <ul v-else class="flex flex-wrap gap-2 mb-3">
              <li v-for="site in group.members" :key="site.id"
                class="inline-flex items-center gap-1.5 rounded-md bg-gray-100 pl-2 pr-1 py-1 text-xs text-gray-700">
                <span>
                  <span v-if="site.machine_id" class="text-gray-500">(#{{ site.machine_id }}) - {{ site.site_id }}</span>
                  <span v-else class="text-gray-500">{{ site.site_id }}</span>
                  <span class="font-medium ml-1">{{ site.name }}</span>
                  <span v-if="site.status_name && site.status_name !== 'Active'"
                    class="text-amber-600"> · {{ site.status_name }}</span>
                </span>
                <button type="button" @click="removeMember(group, site)"
                  class="rounded p-0.5 text-gray-400 hover:text-red-600 hover:bg-gray-200" title="Remove from group">
                  <XMarkIcon class="w-4 h-4" />
                </button>
              </li>
            </ul>

            <!-- add sites -->
            <div class="relative">
              <input :value="pickerFor === group.id ? pickerQuery : ''"
                @focus="openPicker(group)" @input="onPickerInput($event, group)"
                type="text" placeholder="Add site by name, Machine ID or Site ID…"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm" />
              <div v-if="pickerFor === group.id && (pickerLoading || pickerResults.length || pickerQuery)"
                class="absolute z-20 mt-1 w-full bg-white border rounded-md shadow-lg max-h-60 overflow-auto">
                <div v-if="pickerLoading" class="px-3 py-2 text-xs text-gray-400">Searching…</div>
                <div v-else-if="!pickerResults.length" class="px-3 py-2 text-xs text-gray-400">
                  No unassigned sites match.
                </div>
                <button v-for="site in pickerResults" :key="site.id" type="button"
                  @click="addMember(group, site)"
                  class="block w-full text-left px-3 py-1.5 text-sm hover:bg-indigo-50">
                  <span v-if="site.machine_id" class="text-gray-500">(#{{ site.machine_id }}) - {{ site.site_id }}</span>
                  <span v-else class="text-gray-500">{{ site.site_id }}</span>
                  <span class="font-medium ml-1">{{ site.name }}</span>
                  <span v-if="site.operator_name" class="text-gray-400 text-xs"> · {{ site.operator_name }}</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ===================== CREATE / EDIT MODAL ===================== -->
    <div v-if="modalOpen" class="fixed inset-0 z-40 flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/40" @click="closeModal"></div>
      <div class="relative bg-white rounded-lg shadow-xl w-full max-w-md p-5">
        <h3 class="text-lg font-semibold text-gray-800 mb-4">
          {{ form.id ? 'Edit Group' : 'New Group' }}
        </h3>

        <div class="space-y-3">
          <div>
            <label class="block text-sm font-medium text-gray-700">Group name</label>
            <input v-model="form.name" type="text" placeholder="e.g. Blk 123 cluster"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" />
            <p v-if="formError" class="text-sm text-red-600 mt-1">{{ formError }}</p>
          </div>

          <div v-if="canPickOperator && !form.id">
            <label class="block text-sm font-medium text-gray-700">Operator</label>
            <select v-model="form.operator_id"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
              <option :value="null">— None —</option>
              <option v-for="op in operatorOptions" :key="op.id" :value="op.id">{{ op.name }}</option>
            </select>
            <p class="text-xs text-gray-400 mt-1">Only sites of this operator can join the group.</p>
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700">Notes <span class="text-gray-400">(optional)</span></label>
            <textarea v-model="form.notes" rows="2"
              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
          </div>
        </div>

        <div class="flex justify-end gap-2 mt-5">
          <button type="button" @click="closeModal"
            class="rounded-md border px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50">Cancel</button>
          <button type="button" @click="saveGroup" :disabled="saving || !form.name.trim()"
            class="rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50">
            {{ saving ? 'Saving…' : 'Save' }}
          </button>
        </div>
      </div>
    </div>

  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { Head, router } from '@inertiajs/vue3';
import { PencilSquareIcon, TrashIcon, XMarkIcon } from '@heroicons/vue/24/outline';
import { ref, reactive, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
  groups: { type: Array, default: () => [] },
  operatorOptions: { type: Array, default: () => [] },
  canPickOperator: { type: Boolean, default: false },
  filters: { type: Object, default: () => ({ operator_id: null, q: '' }) },
});

// --- toolbar filters ---
const operatorFilter = ref([...(props.filters?.operator_ids ?? [])]);
const nameFilter = ref('');

const visibleGroups = computed(() => {
  const q = nameFilter.value.trim().toLowerCase();
  if (!q) return props.groups;
  return props.groups.filter((g) => (g.name || '').toLowerCase().includes(q));
});

function applyOperatorFilter() {
  router.get('/vends/grouping',
    operatorFilter.value.length ? { operator_ids: operatorFilter.value } : {},
    { preserveScroll: true, preserveState: false });
}

// --- create / edit modal ---
const modalOpen = ref(false);
const saving = ref(false);
const formError = ref('');
const form = reactive({ id: null, name: '', operator_id: null, notes: '' });

function openCreate() {
  form.id = null;
  form.name = '';
  // Pre-fill the operator only when exactly one is being filtered on.
  form.operator_id = operatorFilter.value.length === 1 ? operatorFilter.value[0] : null;
  form.notes = '';
  formError.value = '';
  modalOpen.value = true;
}

function openEdit(group) {
  form.id = group.id;
  form.name = group.name;
  form.operator_id = group.operator_id;
  form.notes = group.notes ?? '';
  formError.value = '';
  modalOpen.value = true;
}

function closeModal() {
  modalOpen.value = false;
}

function saveGroup() {
  if (!form.name.trim()) return;
  saving.value = true;
  formError.value = '';

  const done = {
    preserveScroll: true,
    onSuccess: () => { modalOpen.value = false; },
    onError: (errors) => { formError.value = errors.name || 'Could not save the group.'; },
    onFinish: () => { saving.value = false; },
  };

  if (form.id) {
    router.put(`/vends/grouping/${form.id}`, { name: form.name, notes: form.notes }, done);
  } else {
    router.post('/vends/grouping',
      { name: form.name, operator_id: form.operator_id, notes: form.notes }, done);
  }
}

function destroyGroup(group) {
  if (!confirm(`Delete group "${group.name}"? Its ${group.member_count} site(s) will be ungrouped (not deleted).`)) return;
  router.delete(`/vends/grouping/${group.id}`, { preserveScroll: true });
}

// --- membership ---
function removeMember(group, site) {
  router.delete(`/vends/grouping/${group.id}/members/${site.id}`, { preserveScroll: true });
}

function addMember(group, site) {
  router.post(`/vends/grouping/${group.id}/members`, { customer_ids: [site.id] }, {
    preserveScroll: true,
    onSuccess: () => { closePicker(); },
  });
}

// --- site picker ---
const pickerFor = ref(null);
const pickerQuery = ref('');
const pickerResults = ref([]);
const pickerLoading = ref(false);
let pickerTimer = null;

function openPicker(group) {
  if (pickerFor.value !== group.id) {
    pickerFor.value = group.id;
    pickerQuery.value = '';
    pickerResults.value = [];
    fetchSites(group);
  }
}

function closePicker() {
  pickerFor.value = null;
  pickerQuery.value = '';
  pickerResults.value = [];
}

function onPickerInput(e, group) {
  pickerFor.value = group.id;
  pickerQuery.value = e.target.value;
  clearTimeout(pickerTimer);
  pickerTimer = setTimeout(() => fetchSites(group), 250);
}

function fetchSites(group) {
  pickerLoading.value = true;
  axios.get('/vends/grouping/site-search', {
    params: { q: pickerQuery.value, group_id: group.id },
  }).then((r) => {
    if (pickerFor.value === group.id) pickerResults.value = r.data.sites || [];
  }).finally(() => { pickerLoading.value = false; });
}
</script>
