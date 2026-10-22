<template>
  <!-- One small chart per measure on the same date axis. -->
  <div>
    <div class="mb-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-600">
      <span class="inline-flex items-center gap-1"><span class="inline-block h-3 w-3 rounded-sm" :style="{ background: BAR }"></span>Happy Hour</span>
      <span class="inline-flex items-center gap-1"><span class="inline-block h-3 w-3 rounded-sm" :style="{ background: OTHER }"></span>Rest of the same transactions</span>
      <span class="inline-flex items-center gap-1"><span class="inline-block h-0.5 w-4" :style="{ background: LINE }"></span>% non-HH of total (right axis)</span>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div v-for="m in measures" :key="m.key" class="rounded border border-gray-200 bg-white p-3">
        <div class="flex items-baseline justify-between gap-2">
          <span class="text-sm font-semibold text-gray-800">{{ m.title }}</span>
          <span class="text-xs text-gray-500 text-right">{{ m.total }}</span>
        </div>
        <div class="relative h-52 mt-2">
          <canvas :ref="el => (canvases[m.key] = el)" :aria-label="m.title" role="img"></canvas>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
// The Happy Hour campaign graph (Brian, 2026-10-16), from /happy-hour-campaigns/{id}/stats. Money arrives in
// cents and is shown in dollars.
//
// HH is the bridge to the rest of the basket (Daniel, 2026-10-22): sales, units and units per machine are
// STACKED — the Happy Hour part, then the rest of the transactions that held a Happy Hour unit ("Related
// Total Sales", counted whole) — with the total on top and a line for the non-HH share on a 0–100% axis.
// Discount stays a single bar.
import { Chart, registerables } from 'chart.js';
import ChartDataLabels from 'chartjs-plugin-datalabels';
import { computed, nextTick, onBeforeUnmount, reactive, watch } from 'vue';

Chart.register(...registerables);

const props = defineProps({
  // [{ date, machines, revenue, discount, units, avg_daily_units_per_machine, related_transactions,
  //    related_multiple, related_revenue, related_units, related_units_per_machine, non_hh_revenue_pct, non_hh_units_pct }]
  days: { type: Array, default: () => [] },
  total: { type: Object, default: null },
});

const BAR = '#3b6fd8';
const BAR_HOVER = '#2a55ad';
const OTHER = '#a9c2f2';
const OTHER_HOVER = '#8eaee9';
const LINE = '#111827';
const dollars = (cents) => '$' + ((cents ?? 0) / 100).toFixed(2);
const pctText = (v) => (v == null ? '—' : `${v}%`);

// hh / related: the day's Happy Hour part and its related total, in the chart's unit. pct: the non-HH share.
const measures = computed(() => {
  const t = props.total;
  return [
    {
      key: 'revenue', title: 'Happy Hour sales + related total', money: true, stacked: true,
      hh: (d) => d.revenue / 100, related: (d) => d.related_revenue / 100, pct: (d) => d.non_hh_revenue_pct,
      tip: (d) => [`Happy Hour: ${dollars(d.revenue)}`, `Rest of the transactions: ${dollars(d.related_revenue - d.revenue)}`,
        `Related Total Sales: ${dollars(d.related_revenue)}`, `Non-HH: ${pctText(d.non_hh_revenue_pct)}`],
      total: t ? `${dollars(t.revenue)} of ${dollars(t.related_revenue)} · non-HH ${pctText(t.non_hh_revenue_pct)}` : '',
    },
    {
      key: 'discount', title: 'Discount given', money: true, stacked: false,
      hh: (d) => d.discount / 100,
      tip: (d) => [`Discount given: ${dollars(d.discount)}`],
      total: t ? dollars(t.discount) : '',
    },
    {
      key: 'units', title: 'Units sold + related total', money: false, stacked: true,
      hh: (d) => d.units, related: (d) => d.related_units, pct: (d) => d.non_hh_units_pct,
      tip: (d) => [`Happy Hour: ${d.units}`, `Rest of the transactions: ${d.related_units - d.units}`,
        `Related total: ${d.related_units}`, `Non-HH: ${pctText(d.non_hh_units_pct)}`],
      total: t ? `${t.units} of ${t.related_units} units · non-HH ${pctText(t.non_hh_units_pct)}` : '',
    },
    {
      key: 'per_machine', title: 'Avg units per machine per day', money: false, decimals: true, stacked: true,
      hh: (d) => d.avg_daily_units_per_machine, related: (d) => d.related_units_per_machine, pct: (d) => d.non_hh_units_pct,
      tip: (d) => [`Happy Hour: ${d.avg_daily_units_per_machine ?? '—'}`, `Related total: ${d.related_units_per_machine ?? '—'}`,
        `Non-HH: ${pctText(d.non_hh_units_pct)}`],
      total: t?.related_units_per_machine != null
        ? `${t.avg_daily_units_per_machine} of ${t.related_units_per_machine} over the range` : '',
    },
  ];
});

const canvases = reactive({});
const charts = {};

function label(date) {
  // "16 Oct" — the year is in the range picker above.
  const d = new Date(date + 'T00:00:00');
  return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
}

// The figure printed on each column (Brian, 2026-10-18; the related TOTAL on a stacked chart): whole dollars
// for money — no "$", so four digits still fit a phone-width bar (Brian, 2026-10-22; the axis carries the $) —
// the count for units, two decimals for the per-machine average. Empty days get no label.
function barLabel(m, v) {
  if (v == null || v === 0) return '';
  if (m.money) return String(Math.round(v));
  return m.decimals ? Number(v).toFixed(2) : String(Math.round(v));
}

const round2 = (v) => Math.round(v * 100) / 100;

// Whether a bar segment is tall enough (px) for a figure inside it.
const MIN_INSIDE_LABEL_PX = 16;
function fitsInside(ctx) {
  const bar = ctx.chart.getDatasetMeta(ctx.datasetIndex).data[ctx.dataIndex];
  return !!bar && Math.abs(bar.base - bar.y) >= MIN_INSIDE_LABEL_PX;
}

function datasets(m) {
  const hh = props.days.map(d => (m.hh(d) == null ? null : m.hh(d)));
  const bar = { type: 'bar', borderRadius: 3, borderSkipped: 'bottom', maxBarThickness: 34, barPercentage: 0.92, yAxisID: 'y', order: 2 };
  if (!m.stacked) {
    return [{ ...bar, label: m.title, data: hh, backgroundColor: BAR, hoverBackgroundColor: BAR_HOVER,
      datalabels: { formatter: (v) => barLabel(m, v) } }];
  }
  const total = props.days.map(d => m.related(d));
  const other = props.days.map((d, i) => (total[i] == null ? null : Math.max(0, round2(total[i] - (hh[i] ?? 0)))));

  return [
    { ...bar, label: 'Happy Hour', data: hh, stack: 's', backgroundColor: BAR, hoverBackgroundColor: BAR_HOVER,
      // The Happy Hour figure inside the top of its own segment, in white (Brian, 2026-10-22).
      // Left out on a segment too short to hold it (it would spill under the axis); the tooltip still has it.
      borderRadius: 0, datalabels: { anchor: 'end', align: 'start', offset: 2, clamp: false, color: '#ffffff', display: fitsInside,
        formatter: (v) => barLabel(m, v) } },
    { ...bar, label: 'Rest of the transactions', data: other, stack: 's', backgroundColor: OTHER, hoverBackgroundColor: OTHER_HOVER,
      // The column's total sits on its top segment.
      datalabels: { formatter: (v, ctx) => barLabel(m, total[ctx.dataIndex]) } },
    { type: 'line', label: '% non-HH', data: props.days.map(d => m.pct(d)), yAxisID: 'pct', order: 1,
      borderColor: LINE, backgroundColor: LINE, borderWidth: 2, pointRadius: 3, tension: 0.25, spanGaps: true,
      datalabels: { display: false } },
  ];
}

function draw() {
  for (const m of measures.value) {
    charts[m.key]?.destroy();
    const el = canvases[m.key];
    if (!el) continue;
    const scales = {
      x: { stacked: m.stacked, grid: { display: false }, ticks: { color: '#6b7280', maxRotation: 0, autoSkip: true } },
      y: {
        stacked: m.stacked,
        beginAtZero: true,
        grid: { color: '#eef0f3', drawBorder: false },
        ticks: { color: '#6b7280', precision: m.decimals ? undefined : 0, callback: (v) => (m.money ? '$' + v : v) },
      },
    };
    if (m.stacked) {
      scales.pct = {
        position: 'right', min: 0, max: 100,
        grid: { display: false, drawBorder: false },
        ticks: { color: '#6b7280', stepSize: 25, callback: (v) => v + '%' },
      };
    }
    charts[m.key] = new Chart(el, {
      type: 'bar',
      data: { labels: props.days.map(d => label(d.date)), datasets: datasets(m) },
      plugins: [ChartDataLabels],
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        layout: { padding: { top: 18 } },
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: { display: false },
          datalabels: {
            anchor: 'end',
            align: 'end',
            offset: 2,
            clamp: true,
            color: '#374151',
            font: { size: 10, weight: '600' },
          },
          tooltip: {
            displayColors: false,
            // One block per day, whatever bar or point is under the cursor.
            filter: (item) => item.datasetIndex === 0,
            callbacks: {
              title: (items) => props.days[items[0].dataIndex]?.date ?? '',
              label: (ctx) => {
                const day = props.days[ctx.dataIndex] ?? {};
                return [
                  ...m.tip(day),
                  `Transactions with a Happy Hour unit: ${day.related_transactions ?? 0} (${day.related_multiple ?? 0} multiple purchases)`,
                  `Machines that ran Happy Hour: ${day.machines ?? 0}`,
                ];
              },
            },
          },
        },
        scales,
      },
    });
  }
}

watch(() => props.days, async () => {
  await nextTick();
  draw();
}, { immediate: true, deep: true });

onBeforeUnmount(() => Object.values(charts).forEach(c => c?.destroy()));
</script>
