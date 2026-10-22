<template>
	<!-- Touch screen usage rate (Brian, 2026-10-22): pieces sold via the touch screen ÷ all pieces sold,
	     drawn L30d → L7d → Y'day → Today. Green when today (else yesterday) is at or above the 30-day
	     rate, red when below. Figures from App\Support\TouchScreenUsage via vend_transaction_totals_json. -->
	<div
		v-if="shown"
		class="w-full rounded border border-gray-200 bg-white px-1 py-0.5 text-[10px] leading-tight text-gray-600"
		v-tooltip="{ content: tooltip, html: true }"
	>
		<div class="whitespace-nowrap font-semibold text-gray-700">Touch screen</div>
		<div class="flex items-baseline justify-center gap-1">
			<span class="text-sm font-bold tabular-nums" :class="toneText">{{ headline }}</span>
			<span class="text-[9px] text-gray-400">{{ latestLabel }}</span>
		</div>
		<svg :viewBox="`0 0 ${W} ${H}`" class="block h-7 w-full" preserveAspectRatio="none" aria-hidden="true">
			<line :x1="0" :x2="W" :y1="y(50)" :y2="y(50)" class="stroke-gray-200" stroke-width="0.5" stroke-dasharray="2 2" />
			<polyline v-for="(segment, i) in segments" :key="i" :points="segment" fill="none" :class="toneStroke" stroke-width="1.6" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
			<circle v-for="point in plotted" :key="point.key" :cx="point.x" :cy="point.y" r="2" :class="toneFill" />
		</svg>
		<div class="flex justify-between text-[8px] text-gray-400">
			<span>L30d</span>
			<span>Today</span>
		</div>
	</div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
	// { today: {touch, total}, yesterday: …, seven_days: …, thirty_days: … }
	usage: { type: Object, default: null },
});

const W = 100;
const H = 24;
const PAD = 3;
const WINDOWS = [
	{ key: 'thirty_days', label: 'Last 30 days' },
	{ key: 'seven_days', label: 'Last 7 days' },
	{ key: 'yesterday', label: 'Yesterday' },
	{ key: 'today', label: 'Today' },
];

const rate = (w) => (w && w.total > 0 ? Math.round((1000 * w.touch) / w.total) / 10 : null);
const y = (pct) => PAD + ((100 - pct) / 100) * (H - 2 * PAD);

// A keypad-only machine never sold a piece through a touch screen: nothing to draw.
const shown = computed(() => !!props.usage && (props.usage.thirty_days?.touch ?? 0) > 0);

const points = computed(() => WINDOWS.map((w, i) => {
	const pct = rate(props.usage?.[w.key]);
	return { ...w, pct, x: PAD + (i * (W - 2 * PAD)) / (WINDOWS.length - 1), y: pct === null ? null : y(pct) };
}));
const plotted = computed(() => points.value.filter((p) => p.pct !== null));
const point = computed(() => Object.fromEntries(points.value.map((p) => [p.key, p])));

// A window with no sales breaks the line rather than drawing it to 0%.
const segments = computed(() => {
	const out = [];
	let run = [];
	for (const p of points.value) {
		if (p.pct === null) {
			if (run.length > 1) out.push(run.join(' '));
			run = [];
		} else {
			run.push(`${p.x},${p.y}`);
		}
	}
	if (run.length > 1) out.push(run.join(' '));
	return out;
});

// Today, or yesterday when nothing has sold yet today.
const latest = computed(() => [point.value.today, point.value.yesterday].find((p) => p.pct !== null) ?? null);
const tone = computed(() => {
	const base = point.value.thirty_days.pct;
	if (!latest.value || base === null) return 'flat';
	return latest.value.pct >= base ? 'up' : 'down';
});
const toneStroke = computed(() => ({ up: 'stroke-green-600', down: 'stroke-red-600', flat: 'stroke-gray-400' })[tone.value]);
const toneFill = computed(() => ({ up: 'fill-green-600', down: 'fill-red-600', flat: 'fill-gray-400' })[tone.value]);
const toneText = computed(() => ({ up: 'text-green-700', down: 'text-red-700', flat: 'text-gray-500' })[tone.value]);
const headline = computed(() => (latest.value ? `${latest.value.pct}%` : '—'));
const latestLabel = computed(() => (latest.value ? latest.value.label.toLowerCase() : ''));

const tooltip = computed(() => {
	const rows = [...WINDOWS].reverse().map((w) => {
		const win = props.usage?.[w.key];
		const pct = rate(win);
		const figure = pct === null ? 'no sales' : `<b>${pct}%</b> (${Number(win.touch)} of ${Number(win.total)} pcs)`;
		return `<tr><td class="pr-3">${w.label}</td><td class="text-right">${figure}</td></tr>`;
	});
	return '<div class="mb-1 font-semibold">Touch screen usage</div>'
		+ '<div class="mb-1 opacity-80">Pieces sold via the touch screen ÷ all pieces sold</div>'
		+ `<table>${rows.join('')}</table>`
		+ '<div class="mt-1 opacity-80">Green: today at or above the 30-day rate. Red: below it.</div>';
});
</script>
