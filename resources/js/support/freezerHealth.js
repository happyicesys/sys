/**
 * Ops Dashboard badges for a smart freezer, from the `freezer_health` column VendController
 * selects off the machine's own status blob (FREEZERSTATUS every 15 min + the daily self-check).
 *
 * Only problems get a badge; a fresh, clean report gets one green "Freezer OK", and a report older
 * than STALE_MINUTES gets a grey "status stale" instead of a green that is no longer true.
 */

import { t } from '@/i18n';

export const STALE_MINUTES = 60;

const RED = 'bg-red-200';
const AMBER = 'bg-amber-200';
const GREEN = 'bg-green-200';
const GREY = 'bg-gray-200';

function minutesSince(at, now) {
    if (!at) return null;
    // Stored as "YYYY-MM-DD HH:mm:ss" in the app timezone (Asia/Singapore).
    const t = Date.parse(String(at).replace(' ', 'T') + '+08:00');
    return Number.isFinite(t) ? Math.round((now - t) / 60000) : null;
}

/** @returns {{label: string, detail: string|null, cls: string}[]} */
export function freezerHealthBadges(health, now = Date.now()) {
    if (!health || typeof health !== 'object') return [];
    const age = minutesSince(health.status_at, now);
    if (age === null) return [{ label: t('No freezer status'), detail: t('never reported'), cls: GREY }];

    const badges = [];
    if (health.power === 'cut') badges.push({ label: t('Mains cut'), detail: t('on battery'), cls: RED });
    if (health.lock_link && health.lock_link !== 'online') badges.push({ label: t('Lock offline'), detail: health.lock_link, cls: RED });

    const cams = Array.isArray(health.cameras) ? health.cameras : [];
    const down = cams.filter((c) => c && c.state && c.state !== 'online');
    if (down.length) badges.push({ label: t('Camera down'), detail: down.map((c) => `#${c.id} ${c.state}`).join(', '), cls: AMBER });

    const a = health.alarms && typeof health.alarms === 'object' ? health.alarms : {};
    if (a.communicating === false) badges.push({ label: t('Controller offline'), detail: t('thermostat not answering'), cls: RED });
    if (a.sensorOk === false) badges.push({ label: t('Probe fault'), detail: t('temperature sensor'), cls: RED });
    if (a.highTemp === true) badges.push({ label: t('High temp alarm'), detail: t('from the controller'), cls: RED });
    if (a.lowTemp === true) badges.push({ label: t('Low temp alarm'), detail: t('from the controller'), cls: RED });

    if (health.selfcheck_passed === false) {
        const errs = Array.isArray(health.selfcheck_errors) ? health.selfcheck_errors : [];
        badges.push({ label: t('Self-check failed'), detail: errs.length ? String(errs[0]).slice(0, 60) : null, cls: RED });
    }

    if (age > STALE_MINUTES) {
        badges.push({ label: t('Freezer status stale'), detail: age >= 120 ? t(':hours h old', { hours: Math.round(age / 60) }) : t(':minutes min old', { minutes: age }), cls: GREY });
    } else if (!badges.length) {
        badges.push({ label: t('Freezer OK'), detail: t(':minutes min ago', { minutes: age }), cls: GREEN });
    }
    return badges;
}
