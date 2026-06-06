<template>

  <Head title="Ops Performance" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Operations Performance
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">

      <!-- ===================== FILTERS ===================== -->
      <div class="bg-white rounded-md border my-3 px-3 py-3">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-2">
          <DatePicker v-model="f.date_from" :isPreviousNextButton="false">From</DatePicker>
          <DatePicker v-model="f.date_to" :isPreviousNextButton="false">As of (To)</DatePicker>

          <SearchInput placeholderStr="Code(s), comma separated" v-model="f.codes" @keyup.enter="applyFilters()">
            Machine ID
          </SearchInput>

          <SearchInput placeholderStr="Site name or code" v-model="f.customer" @keyup.enter="applyFilters()">
            Site
          </SearchInput>

          <div>
            <label class="block text-sm font-medium text-gray-700">Operator</label>
            <MultiSelect class="mt-1" v-model="f.operators" :options="filterOptions.operators"
              valueProp="id" trackBy="id" label="name" mode="tags" placeholder="All" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Location Type</label>
            <MultiSelect class="mt-1" v-model="f.location_type_ids" :options="filterOptions.locationTypes"
              valueProp="id" trackBy="id" label="name" mode="tags" placeholder="All" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Machine Prefix</label>
            <MultiSelect class="mt-1" v-model="f.vend_prefix_ids" :options="filterOptions.vendPrefixes"
              valueProp="id" trackBy="id" label="name" mode="tags" placeholder="All" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Vend Model</label>
            <MultiSelect class="mt-1" v-model="f.vend_model_ids" :options="filterOptions.vendModels"
              valueProp="id" trackBy="id" label="name" mode="tags" placeholder="All" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Category</label>
            <MultiSelect class="mt-1" v-model="f.category_ids" :options="filterOptions.categories"
              valueProp="id" trackBy="id" label="name" mode="tags" placeholder="All" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Machine Status</label>
            <MultiSelect class="mt-1" v-model="f.statuses" :options="filterOptions.statuses"
              valueProp="id" trackBy="id" label="name" mode="tags" placeholder="Active" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700">Site Status</label>
            <MultiSelect class="mt-1" v-model="f.site_statuses" :options="filterOptions.siteStatuses"
              valueProp="id" trackBy="id" label="name" mode="tags" placeholder="Active" />
          </div>
        </div>

        <div class="flex items-center gap-2 mt-3">
          <button type="button" @click="applyFilters()"
            class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-indigo-700">
            Apply
          </button>
          <button type="button" @click="resetFilters()"
            class="inline-flex items-center rounded-md bg-white px-3 py-1.5 text-sm font-medium text-gray-700 border border-gray-300 hover:bg-gray-50">
            Reset
          </button>
          <button type="button" @click="exportExcel()"
            class="inline-flex items-center rounded-md bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700">
            Export to Excel
          </button>
          <span class="text-xs text-gray-400 ml-1">Machine Status filters the component section only.</span>
        </div>
      </div>

      <p class="text-xs text-gray-500 mb-3">
        Count of machine, calculated as of end of day. Day columns anchored to {{ anchorDate }}.
        <span v-if="component">Component snapshot as of {{ component.snapshotDate }}.</span>
        <span v-if="generatedAt"> Generated {{ generatedAt }}.</span>
      </p>

      <div v-if="componentIsLive" class="bg-sky-50 border border-sky-200 text-sky-800 rounded-md px-3 py-2 text-xs mb-3">
        Component figures are live (current fleet) — the nightly snapshot (03:00) hasn't frozen this day yet.
      </div>

      <div v-if="!hasData" class="bg-amber-50 border border-amber-200 text-amber-800 rounded-md px-4 py-3 text-sm">
        No data for the current filters. Component history builds from when <code>ops:snapshot-daily</code> first runs;
        financials come from <code>gp_metrics</code>.
      </div>

      <template v-else>

        <!-- ===================== KEY KPI ===================== -->
        <div class="bg-white rounded-md border my-3 overflow-x-auto">
          <table class="min-w-full text-xs text-right border-collapse">
            <thead>
              <tr class="bg-amber-50 text-gray-600">
                <th class="px-3 py-1 sticky left-0 bg-amber-50 z-10"></th>
                <th class="px-2 py-1 text-[11px] font-semibold text-center border-l-2 border-gray-700"
                  :colspan="2 + dayColumns.length">
                  Daily, Count of Machine (% of total machine)
                </th>
                <th class="px-2 py-1 text-[11px] font-semibold text-center border-l-2 border-gray-700"
                  :colspan="monthColumns.length">
                  Monthly, Count of Machine (% of total machine)
                </th>
              </tr>
              <tr class="bg-amber-100 text-gray-700">
                <th class="text-left px-3 py-2 font-semibold sticky left-0 bg-amber-100 z-10">Key KPI</th>
                <th class="px-2 py-2 font-medium border-l-2 border-gray-700">Avg L7d</th>
                <th class="px-2 py-2 font-medium">Avg L30d</th>
                <th v-for="col in dayColumns" :key="col.key" class="px-2 py-2 font-medium whitespace-nowrap border-l">
                  <div>{{ col.label }}</div>
                  <div class="text-xs text-gray-600 font-semibold">{{ ymd(col.date) }}</div>
                </th>
                <th v-for="(col, i) in monthColumns" :key="col.key" class="px-2 py-2 font-medium whitespace-nowrap"
                  :class="i === 0 ? 'border-l-2 border-gray-700' : 'border-l'">
                  {{ col.label }}
                </th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in kpis" :key="row.id" class="border-t hover:bg-gray-50">
                <td class="text-left px-3 py-1.5 font-medium text-gray-700 sticky left-0 bg-white z-10 whitespace-nowrap">
                  {{ row.label }}
                </td>
                <td class="px-2 py-1.5 border-l-2 border-gray-700" :class="bg(row.daily.avg_l7d, row.daily.avg_l30d, row.format)">{{ fmt(row.daily.avg_l7d, row.format) }}</td>
                <td class="px-2 py-1.5 text-gray-500">{{ fmt(row.daily.avg_l30d, row.format) }}</td>
                <td v-for="(col, i) in dayColumns" :key="col.key" class="px-2 py-1.5 border-l"
                  :class="bg(row.daily[col.key], row.daily[dayColumns[i + 1]?.key], row.format)">
                  {{ fmt(row.daily[col.key], row.format) }}
                </td>
                <td v-for="(col, i) in monthColumns" :key="col.key" class="px-2 py-1.5"
                  :class="[bg(row.monthly[col.key], row.monthly[monthColumns[i + 1]?.key], row.format), i === 0 ? 'border-l-2 border-gray-700' : 'border-l']">
                  {{ fmt(row.monthly[col.key], row.format) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- ============ MACHINES' STATUS & COMPONENT ============ -->
        <div v-if="component" class="bg-white rounded-md border my-3 overflow-x-auto">
          <table class="min-w-full text-xs border-collapse">
            <thead>
              <tr class="bg-amber-100 text-gray-700">
                <th class="text-left px-3 py-2 font-semibold" colspan="2">Machines' Status &amp; Component</th>
                <th class="text-right px-3 py-2 font-medium">Count</th>
                <th class="text-right px-3 py-2 font-medium">% of set</th>
              </tr>
            </thead>
            <tbody>
              <ComponentGroup v-for="(g, gi) in (component.groups || [])" :key="g.key"
                :label="g.label" :rows="g.rows" :total="component.total"
                :lead="g.lead" :leadValue="g.leadValue" :alt="gi % 2 === 1" />
            </tbody>
          </table>
        </div>

      </template>
    </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import SearchInput from '@/Components/SearchInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import DatePicker from '@/Components/DatePicker.vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import { computed, reactive, h } from 'vue';

const props = defineProps({
  generatedAt: String,
  anchorDate: String,
  totalMachines: { type: Number, default: 0 },
  componentIsLive: { type: Boolean, default: false },
  hasData: { type: Boolean, default: false },
  dayColumns: { type: Array, default: () => [] },
  monthColumns: { type: Array, default: () => [] },
  kpis: { type: Array, default: () => [] },
  component: { type: Object, default: null },
  filterOptions: { type: Object, default: () => ({}) },
  filters: { type: Object, default: () => ({}) },
});

const operatorCountry = usePage().props.auth.operatorCountry;

// --- filter state (MultiSelect is object-valued, so hydrate ids -> options) ---
function toObjects(options, ids) {
  return (ids || []).map((id) => (options || []).find((o) => o.id === id)).filter(Boolean);
}

const f = reactive({
  date_from: props.filters.date_from || '',
  date_to: props.filters.date_to || '',
  codes: props.filters.codes || '',
  customer: props.filters.customer || '',
  operators: toObjects(props.filterOptions.operators, props.filters.operators),
  location_type_ids: toObjects(props.filterOptions.locationTypes, props.filters.location_type_ids),
  vend_prefix_ids: toObjects(props.filterOptions.vendPrefixes, props.filters.vend_prefix_ids),
  vend_model_ids: toObjects(props.filterOptions.vendModels, props.filters.vend_model_ids),
  category_ids: toObjects(props.filterOptions.categories, props.filters.category_ids),
  statuses: toObjects(props.filterOptions.statuses, props.filters.statuses),
  site_statuses: toObjects(props.filterOptions.siteStatuses, props.filters.site_statuses),
});

const ids = (arr) => (arr || []).map((o) => o.id);

function applyFilters() {
  router.get('/vends/ops-performance', {
    date_from: f.date_from,
    date_to: f.date_to,
    codes: f.codes,
    customer: f.customer,
    operators: ids(f.operators),
    location_type_ids: ids(f.location_type_ids),
    vend_prefix_ids: ids(f.vend_prefix_ids),
    vend_model_ids: ids(f.vend_model_ids),
    category_ids: ids(f.category_ids),
    statuses: ids(f.statuses),
    site_statuses: ids(f.site_statuses),
  }, { preserveState: true, preserveScroll: true });
}

function resetFilters() {
  router.get('/vends/ops-performance', {}, { preserveScroll: true });
}

// Export the currently-filtered view to Excel (server-side, same data source).
function exportExcel() {
  const p = new URLSearchParams();
  if (f.date_from) p.append('date_from', f.date_from);
  if (f.date_to) p.append('date_to', f.date_to);
  if (f.codes) p.append('codes', f.codes);
  if (f.customer) p.append('customer', f.customer);
  ids(f.operators).forEach((v) => p.append('operators[]', v));
  ids(f.location_type_ids).forEach((v) => p.append('location_type_ids[]', v));
  ids(f.vend_prefix_ids).forEach((v) => p.append('vend_prefix_ids[]', v));
  ids(f.vend_model_ids).forEach((v) => p.append('vend_model_ids[]', v));
  ids(f.category_ids).forEach((v) => p.append('category_ids[]', v));
  ids(f.statuses).forEach((v) => p.append('statuses[]', v));
  ids(f.site_statuses).forEach((v) => p.append('site_statuses[]', v));
  window.location.href = '/vends/ops-performance/excel?' + p.toString();
}

// --- formatting ---
function money(cents) {
  if (cents === null || cents === undefined) return '–';
  const exp = operatorCountry?.currency_exponent ?? 2;
  const v = Number(cents) / Math.pow(10, exp);
  const digits = operatorCountry?.is_currency_exponent_hidden ? 0 : exp;
  return (operatorCountry?.currency_symbol ?? '') + v.toLocaleString(undefined, {
    minimumFractionDigits: digits, maximumFractionDigits: digits,
  });
}
function int(v) { return (v === null || v === undefined) ? '–' : Number(v).toLocaleString(); }
function percent(v) { return (v === null || v === undefined) ? '–' : Number(v).toFixed(2) + '%'; }
function fmt(v, format) {
  if (format === 'text') return (v === null || v === undefined || v === '') ? '–' : v;
  if (format === 'money') return money(v);
  if (format === 'percent') return percent(v);
  return int(v);
}
function pct(count, total) { return (!total || total <= 0) ? '' : Math.round((Number(count) / total) * 100) + '%'; }

// 'YYYY-MM-DD' -> 'yymmdd' (e.g. 2026-06-04 -> 260604)
function ymd(d) { return d ? d.slice(2).replace(/-/g, '') : ''; }

// Green when a cell beats the chronologically previous (older) period, red when
// lower. Skips text rows and any cell where either value is missing/non-numeric.
function bg(cur, prev, format) {
  if (format === 'text') return '';
  if (typeof cur !== 'number' || typeof prev !== 'number') return '';
  if (cur > prev) return 'bg-green-200';
  if (cur < prev) return 'bg-red-200';
  return '';
}

// --- component section: data-driven group renderer ---
// Reads from component.groups (controller is the single source of truth), so a
// new category added server-side renders here AND in the Excel export with no
// further frontend change.
const ComponentGroup = (p) => {
  const rows = p.rows || [];
  const hasLead = p.lead !== undefined && p.lead !== null && p.lead !== '';
  const children = [];
  // Alternate section background so adjacent groups are easy to tell apart.
  const sectionBg = p.alt ? ' bg-gray-100' : '';

  if (hasLead) {
    children.push(h('tr', { class: 'border-t' + sectionBg }, [
      h('td', { class: 'px-3 py-1.5 font-semibold text-gray-700', rowspan: rows.length + 1 }, p.label),
      h('td', { class: 'px-3 py-1.5 font-medium text-gray-700' }, p.lead),
      h('td', { class: 'px-3 py-1.5 text-right font-medium' }, int(p.leadValue)),
      h('td', { class: 'px-3 py-1.5 text-right text-gray-400' }, ''),
    ]));
  }
  rows.forEach((r, idx) => {
    const cells = [];
    if (!hasLead && idx === 0) {
      cells.push(h('td', { class: 'px-3 py-1.5 font-semibold text-gray-700', rowspan: rows.length }, p.label));
    }
    cells.push(h('td', { class: 'px-3 py-1.5 text-gray-600' }, r.label));
    cells.push(h('td', { class: 'px-3 py-1.5 text-right' }, int(r.count)));
    cells.push(h('td', { class: 'px-3 py-1.5 text-right text-gray-400' }, pct(r.count, p.total)));
    children.push(h('tr', { class: 'border-t' + sectionBg }, cells));
  });
  return children;
};
ComponentGroup.props = ['label', 'rows', 'total', 'lead', 'leadValue', 'alt'];
</script>
