<template>
    <!-- The top-right language switch (Brian, 2026-10-26): English by default, Simplified Chinese on
         request. Saved on the user's account (users.locale), so it follows them to every device; the
         page reloads to draw everything in the new language. -->
    <div class="inline-flex items-center rounded-md border border-gray-300 bg-white text-xs font-medium overflow-hidden"
        role="group" :aria-label="$t('Language')">
        <button v-for="(label, code) in locales" :key="code" type="button"
            class="px-2 py-1 transition"
            :class="code === current ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-100'"
            :disabled="saving"
            @click="choose(code)">
            {{ label }}
        </button>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';
import { t } from '../i18n';

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
