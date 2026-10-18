<template>
  <!-- One small bar chart per measure on the same date axis — never two scales on one chart. -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div v-for="m in measures" :key="m.key" class="rounded border border-gray-200 bg-white p-3">
      <div class="flex items-baseline justify-between">
        <span class="text-sm font-semibold text-gray-800">{{ m.title }}</span>
        <span class="text-xs text-gray-500">{{ m.total }}</span>
      </div>
      <div class="relative h-44 mt-2">
        <canvas :ref="el => (canvases[m.key] = el)" :aria-label="m.title" role="img"></canvas>
      </div>
    </div>
  </div>
</template>

<script setup>
// The Happy Hour campaign graph (Brian, 2026-10-16): daily sales, discount, units and average units per
// machine, from /happy-hour-campaigns/{id}/stats. Money arrives in cents and is shown in dollars.
import { Chart, registerables } from 'chart.js';
import ChartDataLabels from 'chartjs-plugin-datalabels';
import { computed, nextTick, onBeforeUnmount, reactive, watch } from 'vue';

Chart.register(...registerables);

const props = defineProps({
  // [{ date, revenue, discount, units, machines, avg_daily_units_per_machine }]
  days: { type: Array, default: () => [] },
  total: { type: Object, default: null },
});

const BAR = '#3b6fd8';
const BAR_HOVER = '#2a55ad';
const dollars = (cents) => '$' + ((cents ?? 0) / 100).toFixed(2);

const measures = computed(() => [
  { key: 'revenue', title: 'Happy Hour sales', money: true, total: props.total ? dollars(props.total.revenue) : '' },
  { key: 'discount', title: 'Discount given', money: true, total: props.total ? dollars(props.total.discount) : '' },
  { key: 'units', title: 'Units sold', money: false, total: props.total ? `${props.total.units} units` : '' },
  {
    key: 'avg_daily_units_per_machine', title: 'Avg units per machine per day', money: false, decimals: true,
    total: props.total?.avg_daily_units_per_machine != null ? `${props.total.avg_daily_units_per_machine} over the range` : '',
  },
]);

const canvases = reactive({});
const charts = {};

function label(date) {
  // "16 Oct" — the year is in the range picker above.
  const d = new Date(date + 'T00:00:00');
  return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
}

// The figure printed on top of each bar (Brian, 2026-10-18): whole dollars for money, the count for units,
// two decimals for the per-machine average. Empty days get no label.
function barLabel(m, v) {
  if (v == null || v === 0) return '';
  if (m.money) return '$' + Math.round(v);
  return m.decimals ? Number(v).toFixed(2) : String(v);
}

function draw() {
  for (const m of measures.value) {
    charts[m.key]?.destroy();
    const el = canvases[m.key];
    if (!el) continue;
    const values = props.days.map(d => d[m.key] == null ? null : (m.money ? d[m.key] / 100 : d[m.key]));
    charts[m.key] = new Chart(el, {
      type: 'bar',
      plugins: [ChartDataLabels],
      data: {
        labels: props.days.map(d => label(d.date)),
        datasets: [{
          label: m.title,
          data: values,
          backgroundColor: BAR,
          hoverBackgroundColor: BAR_HOVER,
          borderRadius: 4,
          borderSkipped: 'bottom',
          maxBarThickness: 28,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: false,
        layout: { padding: { top: 18 } },
        plugins: {
          legend: { display: false },
          datalabels: {
            anchor: 'end',
            align: 'end',
            offset: 2,
            clamp: true,
            color: '#374151',
            font: { size: 11, weight: '600' },
            formatter: (v) => barLabel(m, v),
          },
          tooltip: {
            displayColors: false,
            callbacks: {
              title: (items) => props.days[items[0].dataIndex]?.date ?? '',
              label: (ctx) => {
                const day = props.days[ctx.dataIndex] ?? {};
                const value = m.money ? dollars(day[m.key]) : (day[m.key] ?? '—');
                return [`${m.title}: ${value}`, `Machines that ran Happy Hour: ${day.machines ?? 0}`];
              },
            },
          },
        },
        scales: {
          x: { grid: { display: false }, ticks: { color: '#6b7280', maxRotation: 0, autoSkip: true } },
          y: {
            beginAtZero: true,
            grid: { color: '#eef0f3', drawBorder: false },
            ticks: {
              color: '#6b7280',
              precision: m.decimals ? undefined : 0,
              callback: (v) => (m.money ? '$' + v : v),
            },
          },
        },
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
