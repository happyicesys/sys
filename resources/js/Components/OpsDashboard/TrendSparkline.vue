<template>
	<!-- A four-point trend on the Operation Dashboard (Brian, 2026-10-22/23): L30d → L7d → Y'day → Today, green
	     when the latest point (today, else yesterday) is at or above L30d, red below, grey with nothing to
	     compare. Points come from composables/opsTrendPoints.js; the figures are stored server-side
	     (SyncVendTransactionTotalsJson), so drawing costs no request. -->
	<div
		class="w-full rounded border border-gray-200 bg-white px-1 py-0.5 text-[10px] leading-tight text-gray-600"
		v-tooltip="{ content: tooltip, html: true }"
	>
		<div class="whitespace-nowrap font-semibold text-gray-700">{{ title }}</div>
		<div class="flex items-baseline justify-center gap-1">
			<span class="text-sm font-bold tabular-nums" :class="toneText">{{ headline }}</span>
			<span class="text-[9px] text-gray-400">{{ latestLabel }}</span>
		</div>
		<svg :viewBox="`0 0 ${W} ${H}`" class="block h-7 w-full" preserveAspectRatio="none" aria-hidden="true">
			<line v-if="percent" :x1="0" :x2="W" :y1="y(50)" :y2="y(50)" class="stroke-gray-200" stroke-width="0.5" stroke-dasharray="2 2" />
			<polyline v-for="(segment, i) in segments" :key="i" :points="segment" fill="none" :class="toneStroke" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
			<circle v-for="point in plotted" :key="point.key" :cx="point.x" :cy="point.y" r="2" :class="toneFill" />
		</svg>
		<div class="flex justify-between text-[8px] text-gray-400">
			<span>{{ $t('L30d') }}</span>
			<span>{{ $t('Today') }}</span>
		</div>
	</div>
</template>

<script setup>
import { computed } from 'vue';
import { t } from '@/i18n';

const props = defineProps({
	title: { type: String, required: true },
	// [{ key: 'thirty_days' | 'seven_days' | 'yesterday' | 'today', label, value: number|null, detail: string }], oldest first
	points: { type: Array, required: true },
	// true: values are 0–100 %; false: counts, scaled to the largest
	percent: { type: Boolean, default: true },
	// one line under the tooltip title saying what the figure is
	hint: { type: String, default: '' },
});

const W = 100;
const H = 24;
const PAD = 3;

const top = computed(() => (props.percent ? 100 : Math.max(1, ...props.points.map((p) => p.value ?? 0)) * 1.1));
const y = (value) => PAD + ((top.value - value) / top.value) * (H - 2 * PAD);
const format = (value) => (value === null ? '—' : props.percent ? `${value}%` : `${value}`);

const placed = computed(() => props.points.map((p, i) => ({
	...p,
	x: PAD + (i * (W - 2 * PAD)) / Math.max(1, props.points.length - 1),
	y: p.value === null ? null : y(p.value),
})));
const plotted = computed(() => placed.value.filter((p) => p.value !== null));
const byKey = computed(() => Object.fromEntries(placed.value.map((p) => [p.key, p])));

// A window with nothing to show breaks the line rather than drawing it to 0.
const segments = computed(() => {
	const out = [];
	let run = [];
	for (const p of placed.value) {
		if (p.value === null) {
			if (run.length > 1) out.push(run.join(' '));
			run = [];
		} else {
			run.push(`${p.x},${p.y}`);
		}
	}
	if (run.length > 1) out.push(run.join(' '));
	return out;
});

// Today, or yesterday when today has nothing yet.
const latest = computed(() => [byKey.value.today, byKey.value.yesterday].find((p) => p && p.value !== null) ?? null);
const tone = computed(() => {
	const base = byKey.value.thirty_days?.value ?? null;
	if (!latest.value || base === null) return 'flat';
	return latest.value.value >= base ? 'up' : 'down';
});
const toneStroke = computed(() => ({ up: 'stroke-green-600', down: 'stroke-red-600', flat: 'stroke-gray-400' })[tone.value]);
const toneFill = computed(() => ({ up: 'fill-green-600', down: 'fill-red-600', flat: 'fill-gray-400' })[tone.value]);
const toneText = computed(() => ({ up: 'text-green-700', down: 'text-red-700', flat: 'text-gray-500' })[tone.value]);
const headline = computed(() => format(latest.value ? latest.value.value : null));
const latestLabel = computed(() => (latest.value ? latest.value.label.toLowerCase() : ''));

const tooltip = computed(() => {
	const rows = [...props.points].reverse().map((p) => `<tr><td class="pr-3">${p.label}</td><td class="text-right">`
		+ (p.value === null ? (p.detail || t('no sales')) : `<b>${format(p.value)}</b>${p.detail ? ` (${p.detail})` : ''}`)
		+ '</td></tr>');
	return `<div class="mb-1 font-semibold">${props.title}</div>`
		+ (props.hint ? `<div class="mb-1 opacity-80">${props.hint}</div>` : '')
		+ `<table>${rows.join('')}</table>`
		+ `<div class="mt-1 opacity-80">${t('Green: latest at or above the 30-day figure. Red: below it.')}</div>`;
});
</script>
