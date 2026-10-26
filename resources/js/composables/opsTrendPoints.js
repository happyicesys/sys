// The Operation Dashboard's trend graphs (TrendSparkline) read the figures SyncVendTransactionTotalsJson stores in
// a machine's totals — App\Support\TransactionMix (touch_usage, cashless_usage) and
// App\Services\OpsDashboard\MachineHappyHourTrend (happy_hour). These turn them into the four points, oldest first.
import { t, tc, tk } from '@/i18n';

const WINDOWS = [
	{ key: 'thirty_days', label: tk('Last 30 days') },
	{ key: 'seven_days', label: tk('Last 7 days') },
	{ key: 'yesterday', label: tk('Yesterday') },
	{ key: 'today', label: tk('Today') },
];

const rate = (part, total) => (total > 0 ? Math.round((1000 * part) / total) / 10 : null);

/** Share of transactions, e.g. ratePoints(totals.touch_usage, 'touch') → "50% (5 of 10 transactions)". */
export function ratePoints(usage, partKey) {
	return WINDOWS.map((w) => {
		const win = usage?.[w.key];
		const part = Number(win?.[partKey] ?? 0);
		const total = Number(win?.total ?? 0);
		return { ...w, label: t(w.label), value: rate(part, total), detail: total > 0 ? t(':part of :total transactions', { part, total }) : '' };
	});
}

/** A rate graph is worth drawing once the machine has used that way at least once in 30 days. */
export function hasRate(usage, partKey) {
	return Number(usage?.thirty_days?.[partKey] ?? 0) > 0;
}

/** Daily HH sold qty: L30d / L7d are a daily average over the days the machine ran Happy Hour. */
export function happyHourQtyPoints(hh) {
	return WINDOWS.map((w) => {
		const value = hh?.daily_units?.[w.key] ?? null;
		const days = Number(hh?.ran_days?.[w.key] ?? 0);
		const units = Number(hh?.units?.[w.key] ?? 0);
		const detail = value === null
			? t('no Happy Hour')
			: (w.key === 'today' || w.key === 'yesterday' ? t(':units pcs', { units }) : tc(':units pcs over :count HH day|:units pcs over :count HH days', days, { units }));
		return { ...w, label: t(w.label), value, detail };
	});
}
