<template>
    <!-- Wording edit mode (Brian, 2026-10-26): an admin turns it on from the top bar, then clicks any text
         on the page to change its wording in the current language. While it is on, clicks never reach
         the page — no button, link or checkbox acts. Tooltips and browser alert/confirm boxes cannot be
         clicked; those are changed on Admin → Translations. The layout mounts one `host` instance (banner,
         dialog, click capture, and keeping the dictionary current — refreshIfStale) and the buttons. -->
    <button v-if="canEdit && !host" type="button" data-i18n-editor
        class="inline-flex items-center gap-1 rounded-md border px-2 py-1 text-xs font-medium transition"
        :class="editing ? 'border-amber-500 bg-amber-100 text-amber-900' : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-100'"
        :title="editing ? t('Turn off wording edit mode') : t('Edit the wording on this page')"
        @click="toggle">
        <PencilSquareIcon class="h-4 w-4" aria-hidden="true" />
        <span>{{ editing ? t('Editing wording') : t('Edit wording') }}</span>
    </button>

    <Teleport v-if="host" to="body">
        <div v-if="editing" data-i18n-editor
            class="fixed bottom-0 inset-x-0 z-[9998] bg-amber-500 text-white text-sm px-4 py-2 flex flex-wrap items-center justify-between gap-2 shadow-lg">
            <span>{{ t('Wording edit mode: click any text to change it. Buttons and links do nothing until you turn this off.') }}</span>
            <button type="button" class="rounded bg-white px-3 py-1 text-amber-700 font-semibold" @click="toggle">{{ t('Turn off') }}</button>
        </div>

        <div v-if="dialog" data-i18n-editor class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/30 p-4" @click.self="close">
            <div class="w-full max-w-lg rounded-lg bg-white shadow-xl p-4 space-y-3 text-sm">
                <div class="flex items-center justify-between">
                    <h3 class="font-semibold text-gray-800">{{ t('Edit wording') }} · {{ localeLabel }}</h3>
                    <button type="button" class="text-gray-400 hover:text-gray-600" @click="close">✕</button>
                </div>

                <div v-if="dialog.keys.length > 1" class="space-y-1">
                    <div class="text-xs text-gray-500">{{ t('This spot holds several texts. Pick one:') }}</div>
                    <button v-for="key in dialog.keys" :key="key" type="button"
                        class="block w-full truncate rounded border px-2 py-1 text-left"
                        :class="key === dialog.key ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:bg-gray-50'"
                        @click="pick(key)">
                        {{ shown(key) }}
                    </button>
                </div>

                <div>
                    <div class="text-xs text-gray-500">{{ t('English (in the code)') }}</div>
                    <div class="whitespace-pre-wrap break-words rounded bg-gray-50 px-2 py-1 text-gray-800">{{ dialog.key }}</div>
                </div>
                <label class="block">
                    <span class="text-xs text-gray-500">{{ t('Wording shown') }}</span>
                    <textarea v-model="dialog.value" rows="3" class="mt-1 block w-full rounded-md border-gray-300 text-sm"></textarea>
                </label>
                <p v-if="dialog.key.includes(':')" class="text-xs text-gray-500">
                    {{ t('Keep words that start with ":" (such as :count) — the system fills them in.') }}
                </p>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <a :href="translationsLink" target="_blank" class="text-xs text-indigo-600 hover:underline">{{ t('Open in Translations') }}</a>
                    <div class="space-x-2">
                        <button type="button" class="rounded border border-gray-300 px-3 py-1 text-gray-700 hover:bg-gray-100" :disabled="saving" @click="save('')">
                            {{ t('Reset') }}
                        </button>
                        <button type="button" class="rounded bg-indigo-600 px-4 py-1 font-medium text-white hover:bg-indigo-700 disabled:opacity-40"
                            :disabled="saving || !dialog.value.trim()" @click="save(dialog.value)">
                            {{ t('Save') }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';
import { PencilSquareIcon } from '@heroicons/vue/20/solid';
import { applyWording, isEditing, keysIn, rawText, refreshIfStale, setEditing, t } from '@/i18n';

// `host`: the one instance (in the layout) that owns the banner, the dialog and the page-wide click
// capture; every other instance is only the button.
const props = defineProps({ host: { type: Boolean, default: false } });

const page = usePage();
const toast = useToast();
const canEdit = computed(() => (page.props.auth?.permissions ?? []).includes('update translations'));
const editing = ref(isEditing());
const dialog = ref(null);
const saving = ref(false);
const locale = computed(() => page.props.i18n?.locale ?? 'en');
const localeLabel = computed(() => page.props.i18n?.locales?.[locale.value] ?? locale.value);
const translationsLink = computed(() => dialog.value
    ? `/translations?locale=${encodeURIComponent(locale.value)}&search=${encodeURIComponent(dialog.value.key.slice(0, 80))}`
    : '/translations');

watch(() => page.props.i18n, (i18n) => { if (props.host) refreshIfStale(i18n); });

function toggle() {
    setEditing(!editing.value);
}

function shown(key) {
    return rawText(key);
}

function pick(key) {
    dialog.value.key = key;
    dialog.value.value = rawText(key) === key && locale.value !== 'en' ? '' : rawText(key);
}

const ATTRIBUTES = ['placeholder', 'title', 'aria-label', 'alt', 'value'];

/** Every marked key on the clicked element, then up to three parents. */
function keysAt(target) {
    let el = target;
    for (let depth = 0; el && depth < 4; depth += 1, el = el.parentElement) {
        const parts = [el.textContent || ''];
        for (const name of ATTRIBUTES) parts.push(el.getAttribute?.(name) || '');
        if (el.placeholder) parts.push(el.placeholder);
        const keys = keysIn(parts.join(' '));
        if (keys.length) return keys.slice(0, 12);
    }
    return [];
}

function swallow(event) {
    if (!editing.value || event.target.closest?.('[data-i18n-editor]')) return;
    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();
}

function onClick(event) {
    if (!editing.value || event.target.closest?.('[data-i18n-editor]')) return;
    swallow(event);
    const keys = keysAt(event.target);
    if (!keys.length) {
        toast.info(t('No editable text here. Tooltips and pop-up boxes are changed on the Translations page.'));
        return;
    }
    dialog.value = { keys, key: keys[0], value: '' };
    pick(keys[0]);
}

let outlined = null;
function onOver(event) {
    if (!editing.value || event.target.closest?.('[data-i18n-editor]')) return;
    if (outlined) outlined.style.outline = '';
    outlined = keysAt(event.target).length ? event.target : null;
    if (outlined) outlined.style.outline = '2px dashed #f59e0b';
}

function close() {
    dialog.value = null;
}

async function save(value) {
    saving.value = true;
    try {
        const { data } = await window.axios.put('/translations', { locale: locale.value, key: dialog.value.key, value },
            { headers: { Accept: 'application/json' } });
        applyWording(data.key, data.value);
        toast.success(value ? t('Saved. Everyone sees it on their next page.') : t('Back to the shipped wording.'));
        // Template text redraws at once (t() reads reactive state); a text a script built earlier
        // shows the new words when the page is next opened.
        close();
    } catch (e) {
        toast.error(t('Could not save. Please try again.'));
    } finally {
        saving.value = false;
    }
}

const EVENTS = ['mousedown', 'mouseup', 'pointerdown', 'pointerup', 'dblclick', 'submit', 'change', 'input', 'keydown'];

onMounted(() => {
    if (!props.host || !editing.value) return;
    document.body.style.paddingBottom = '3rem';
    document.addEventListener('click', onClick, true);
    document.addEventListener('mouseover', onOver, true);
    for (const name of EVENTS) document.addEventListener(name, swallow, true);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onClick, true);
    document.removeEventListener('mouseover', onOver, true);
    for (const name of EVENTS) document.removeEventListener(name, swallow, true);
});
</script>
