<template>
    <!-- The top-right language switch (Brian, 2026-10-26): English by default, Simplified Chinese or Bahasa
         Indonesia (2026-10-30) on request. Saved on the user's account (users.locale), so it follows them to
         every device; the page reloads to draw everything in the new language. A menu rather than one button
         per language, so the top bar stays the same width however many languages there are. Each entry is
         written in its own language, with the English name under it for anyone who cannot read the one
         currently shown. -->
    <BreezeDropdown :align="align" width="48">
        <template #trigger>
            <button type="button"
                class="inline-flex items-center gap-1.5 rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 transition"
                :disabled="saving" :aria-label="$t('Language')" :title="$t('Language')">
                <LanguageIcon class="h-4 w-4 text-indigo-600" aria-hidden="true" />
                <span>{{ locales[current] ?? current }}</span>
                <ChevronDownIcon class="h-3.5 w-3.5 text-gray-400" aria-hidden="true" />
            </button>
        </template>
        <template #content>
            <div class="px-3 pt-1 pb-1.5 text-[11px] font-medium uppercase tracking-wide text-gray-400">{{ $t('Language') }}</div>
            <button v-for="(label, code) in locales" :key="code" type="button" role="menuitemradio"
                :aria-checked="code === current"
                class="flex w-full items-center gap-2 px-3 py-1.5 text-left transition"
                :class="code === current ? 'bg-indigo-50' : 'hover:bg-gray-100'"
                @click="choose(code)">
                <span class="flex-1 min-w-0">
                    <span class="block text-sm" :class="code === current ? 'font-semibold text-indigo-700' : 'text-gray-800'">{{ label }}</span>
                    <span v-if="ENGLISH_NAMES[code] && ENGLISH_NAMES[code] !== label" class="block text-[11px] text-gray-500">{{ ENGLISH_NAMES[code] }}</span>
                </span>
                <CheckIcon v-if="code === current" class="h-4 w-4 shrink-0 text-indigo-600" aria-hidden="true" />
            </button>
        </template>
    </BreezeDropdown>
</template>

<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';
import { CheckIcon, ChevronDownIcon, LanguageIcon } from '@heroicons/vue/20/solid';
import BreezeDropdown from '@/Components/Dropdown.vue';
import { t } from '../i18n';

defineProps({
    align: { type: String, default: 'right' },
});

// Not wrapped on purpose: the hint is for someone who cannot read the language on screen.
const ENGLISH_NAMES = { en: 'English', zh_CN: 'Chinese (Simplified)', id: 'Indonesian' };

const page = usePage();
const toast = useToast();
const saving = ref(false);
const locales = computed(() => page.props.i18n?.locales ?? { en: 'English' });
const current = computed(() => page.props.i18n?.locale ?? 'en');

async function choose(code) {
    if (code === current.value || saving.value) return;
    saving.value = true;
    try {
        await window.axios.post('/user/locale', { locale: code });
        window.location.reload();
    } catch (e) {
        saving.value = false;
        toast.error(t('The language could not be changed. Please try again.'));
    }
}
</script>
