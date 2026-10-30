// English / Simplified Chinese UI (Brian, 2026-10-26), Bahasa Indonesia since 2026-10-30. Every text in the
// code is written in English and wrapped: `$t('Save')` in a template, `t('Saved :count items', { count })`
// in a script. The English text is the key, so a text with no translation simply shows in English. A
// language's wording — the shipped
// lang/{locale}.json with the wording people changed on top (App\Services\I18n\TranslationCatalog) —
// is fetched once per version from /i18n/{locale}.json?v= before the app mounts.
//
// Rules for anyone adding text:
//   - Wrap every word a person reads: labels, headings, buttons, placeholders, tooltips, toasts,
//     alert/confirm/prompt, empty states. Not: data from the database, request ids, CSV headers.
//   - A text kept in a script and shown later (menu entries, option lists) is marked with tk('…') where it
//     is written and shown with $t(…) — tk() only lets `php artisan i18n:scan` find it.
//   - Never glue pieces: t('Last sale :hours ago', { hours }) — never t('Last sale') + ' ' + hours.
//   - Counts: tc(':count freeze|:count freezes', n). Chinese and Indonesian give one form.
//   - Then run `php artisan i18n:scan --sync` and translate the new keys in lang/zh_CN.json and lang/id.json.
//
// Wording edit mode (admins, `update translations`): every text t() returns carries an invisible marker
// naming its key, so components/I18nEditMode.vue can tell which text was clicked and edit it in place.
import { reactive } from 'vue';

const state = reactive({ locale: 'en', messages: {} });
let loadedVersion = null;

// ── wording edit mode ────────────────────────────────────────────────────
const EDIT_FLAG = 'i18n-edit-mode';
const MARK_START = '⁣';
const MARK_END = '⁤';
const BIT = ['​', '‌'];
const registry = [];
const registryIndex = new Map();
let editing = false;

function readEditFlag() {
    try { return window.sessionStorage.getItem(EDIT_FLAG) === '1'; } catch (e) { return false; }
}

function mark(key, text) {
    if (!editing || typeof text !== 'string') return text;
    let id = registryIndex.get(key);
    if (id === undefined) {
        id = registry.push(key) - 1;
        registryIndex.set(key, id);
    }
    return MARK_START + id.toString(2).split('').map((b) => BIT[b]).join('') + MARK_END + text;
}

/** The keys whose marked text appears in a string (edit mode). */
export function keysIn(text) {
    if (!text || typeof text !== 'string' || !text.includes(MARK_START)) return [];
    const found = [];
    const re = new RegExp(`${MARK_START}([${BIT.join('')}]+)${MARK_END}`, 'g');
    let m;
    while ((m = re.exec(text)) !== null) {
        const id = parseInt(m[1].split('').map((c) => (c === BIT[1] ? '1' : '0')).join(''), 2);
        if (registry[id] !== undefined && !found.includes(registry[id])) found.push(registry[id]);
    }
    return found;
}

/** A string with edit-mode markers removed (the tab title, anything leaving the page). */
export function stripMarks(text) {
    return typeof text === 'string' ? text.replace(/[\u2063\u2064\u200B\u200C]/g, '') : text;
}

export function isEditing() {
    return editing;
}

export function setEditing(on) {
    try {
        if (on) window.sessionStorage.setItem(EDIT_FLAG, '1');
        else window.sessionStorage.removeItem(EDIT_FLAG);
    } catch (e) { /* storage blocked: the reload below still shows the right mode for this page */ }
    window.location.reload();
}

/** After a save in edit mode: show the new wording without a reload. */
export function applyWording(key, value) {
    if (value === null || value === undefined || value === '') delete state.messages[key];
    else state.messages[key] = value;
}

export function rawText(key) {
    const found = state.messages[key];
    return typeof found === 'string' && found !== '' ? found : String(key);
}
// ─────────────────────────────────────────────────────────────────────────

function interpolate(text, params) {
    if (!params) return text;
    return text.replace(/:([A-Za-z_][A-Za-z0-9_]*)/g, (match, name) =>
        Object.prototype.hasOwnProperty.call(params, name) && params[name] !== null && params[name] !== undefined
            ? String(params[name]) : match);
}

/** The text in the user's language; the English key when it has no translation. */
export function t(key, params = null) {
    if (key === null || key === undefined || key === '') return key;
    return mark(String(key), interpolate(rawText(key), params));
}

/** Count-dependent text: 'one form|other form' (Laravel's choice format), :count filled in. */
export function tc(key, count, params = {}) {
    const forms = rawText(key).split('|');
    const form = forms.length > 1 && Number(count) !== 1 ? forms[1] : forms[0];
    return mark(String(key), interpolate(form, { count, ...params }));
}

/** Marks a text for translation where it is written, for one shown later with $t(). Returns it as is. */
export function tk(key) {
    return key;
}

export function currentLocale() {
    return state.locale;
}

/** Load the language the page was rendered for (the shared `i18n` prop). Resolves even on failure. */
export async function loadLocale(i18n, canEdit = false) {
    const locale = i18n?.locale || 'en';
    state.locale = locale;
    editing = Boolean(canEdit) && readEditFlag();
    document.documentElement.lang = locale.replace('_', '-');
    loadedVersion = i18n?.version ?? null;
    if (!i18n || !i18n.version || i18n.version === 'none') {
        state.messages = {};
        return;
    }
    try {
        const response = await fetch(`/i18n/${locale}.json?v=${encodeURIComponent(i18n.version)}`, {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        state.messages = response.ok ? await response.json() : {};
    } catch (e) {
        state.messages = {};
    }
}

/**
 * Pages change without a reload, so a page whose `i18n.version` differs from the loaded dictionary
 * (someone changed wording, or the user switched language elsewhere) fetches it again.
 */
export async function refreshIfStale(i18n) {
    if (!i18n || (i18n.version === loadedVersion && i18n.locale === state.locale)) return;
    const keepEditing = editing;
    await loadLocale(i18n, false);
    editing = keepEditing;
}

export const i18nPlugin = {
    install(app) {
        app.config.globalProperties.$t = t;
        app.config.globalProperties.$tc = tc;
    },
};
