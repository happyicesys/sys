<template>
    <Head :title="$t('Translations')" />

    <BreezeAuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ $t('Translations') }}</h2>
        </template>

        <!-- Settings → Translations (Brian, 2026-10-26): every text of the UI and its wording. The shipped
             wording comes with the code (lang/zh_CN.json); a change saved here wins over it at once, for
             everyone, with no deploy. Reset goes back to the shipped wording. -->
        <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
            <div class="-mx-3 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 py-3 space-y-3">
                <p class="text-sm text-gray-600">
                    {{ $t('Each line is one text of the system, written in English. Type new wording and press Save; everyone sees it on their next page. Reset brings back the original wording.') }}
                </p>
                <p class="text-xs text-gray-500">
                    {{ $t('"Shown on" lists every page that uses the text. The same English text shares one wording, so a change appears in all of those places. Pick a page to review its texts one page at a time.') }}
                </p>
                <div class="flex flex-wrap gap-4 text-xs text-gray-600">
                    <span>{{ $t('Texts: :count', { count: counts.total }) }}</span>
                    <span>{{ $t('Translated (shipped): :count', { count: counts.translated }) }}</span>
                    <span>{{ $t('Changed here: :count', { count: counts.changed }) }}</span>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-5 gap-2 items-end">
                    <label class="block text-sm">
                        <span class="font-medium text-gray-700">{{ $t('Language') }}</span>
                        <select v-model="form.locale" class="mt-1 block w-full rounded-md border-gray-300 text-sm" @change="search()">
                            <option v-for="(label, code) in locales" :key="code" :value="code">{{ label }}</option>
                        </select>
                    </label>
                    <label class="block text-sm md:col-span-2">
                        <span class="font-medium text-gray-700">{{ $t('Search') }}</span>
                        <input v-model="form.search" type="text" class="mt-1 block w-full rounded-md border-gray-300 text-sm"
                            :placeholder="$t('English or translated text')" @keyup.enter="search()" />
                    </label>
                    <label class="block text-sm">
                        <span class="font-medium text-gray-700">{{ $t('Page') }}</span>
                        <select v-model="form.page_name" class="mt-1 block w-full rounded-md border-gray-300 text-sm" @change="search()">
                            <option value="">{{ $t('All pages') }}</option>
                            <option v-for="(count, name) in pages" :key="name" :value="name">{{ name }} ({{ count }})</option>
                        </select>
                    </label>
                    <label class="block text-sm">
                        <span class="font-medium text-gray-700">{{ $t('Show') }}</span>
                        <select v-model="form.status" class="mt-1 block w-full rounded-md border-gray-300 text-sm" @change="search()">
                            <option value="all">{{ $t('All') }}</option>
                            <option value="missing">{{ $t('Not translated yet') }}</option>
                            <option value="changed">{{ $t('Changed here') }}</option>
                        </select>
                    </label>
                </div>
                <div>
                    <button type="button" class="rounded-md bg-green-500 px-5 py-2 text-sm font-medium text-white hover:bg-green-600" @click="search()">
                        {{ $t('Search') }}
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-700">
                            <tr>
                                <th class="px-2 py-2 text-left w-1/3">{{ $t('English (in the code)') }}</th>
                                <th class="px-2 py-2 text-left w-1/4">{{ $t('Shipped wording') }}</th>
                                <th class="px-2 py-2 text-left">{{ $t('Your wording') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="entry in entries.data" :key="entry.key" class="align-top">
                                <td class="px-2 py-2 text-gray-800">
                                    <div class="whitespace-pre-wrap break-words">{{ entry.key }}</div>
                                    <!-- Where it is shown: one wording serves every place listed. -->
                                    <div v-if="entry.places.length" class="mt-1 flex flex-wrap items-center gap-1 text-[11px] text-gray-500">
                                        <span>{{ $t('Shown on:') }}</span>
                                        <span v-for="place in entry.places.slice(0, 4)" :key="place" class="rounded bg-gray-100 px-1.5 py-0.5">{{ place }}</span>
                                        <span v-if="entry.places.length > 4" class="rounded bg-gray-100 px-1.5 py-0.5 cursor-help"
                                            v-tooltip="entry.places.slice(4).join(', ')">
                                            {{ $t('+:count more', { count: entry.places.length - 4 }) }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-2 py-2 whitespace-pre-wrap break-words" :class="entry.shipped ? 'text-gray-700' : 'text-red-600 italic'">
                                    {{ entry.shipped || $t('Not translated yet') }}
                                </td>
                                <td class="px-2 py-2">
                                    <textarea v-model="drafts[entry.key]" rows="2" class="block w-full rounded-md border-gray-300 text-sm"
                                        :placeholder="entry.shipped || entry.key"></textarea>
                                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                                        <button type="button" class="rounded bg-indigo-600 px-3 py-1 font-medium text-white hover:bg-indigo-700 disabled:opacity-40"
                                            :disabled="saving === entry.key || (drafts[entry.key] || '') === (entry.value || '')"
                                            @click="save(entry, drafts[entry.key])">
                                            {{ $t('Save') }}
                                        </button>
                                        <button v-if="entry.value" type="button" class="rounded border border-gray-300 px-3 py-1 text-gray-700 hover:bg-gray-100"
                                            :disabled="saving === entry.key" @click="save(entry, '')">
                                            {{ $t('Reset to default') }}
                                        </button>
                                        <span v-if="entry.value" class="text-gray-500">
                                            {{ $t('Changed by :name at :time', { name: entry.updated_by || '—', time: entry.updated_at }) }}
                                        </span>
                                    </div>
                                </td>
                            </tr>
                            <tr v-if="!entries.data.length">
                                <td colspan="3" class="px-2 py-6 text-center text-gray-500">{{ $t('No results found') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-600">{{ $t('Showing :from to :to of :total', { from: entries.from || 0, to: entries.to || 0, total: entries.total }) }}</span>
                    <div class="space-x-2">
                        <Link v-if="entries.prev_page_url" :href="entries.prev_page_url" preserve-scroll class="rounded border px-3 py-1 hover:bg-gray-50">{{ $t('Previous') }}</Link>
                        <Link v-if="entries.next_page_url" :href="entries.next_page_url" preserve-scroll class="rounded border px-3 py-1 hover:bg-gray-50">{{ $t('Next') }}</Link>
                    </div>
                </div>
            </div>
        </div>
    </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';
import { useToast } from 'vue-toastification';
import { t } from '@/i18n';

const props = defineProps({
    entries: Object,
    locales: Object,
    filters: Object,
    counts: Object,
    pages: Object,
});

const toast = useToast();
const form = reactive({ ...props.filters });
const drafts = reactive({});
const saving = ref(null);

watch(() => props.entries, (entries) => {
    for (const entry of entries.data) drafts[entry.key] = entry.value || '';
}, { immediate: true });

function search() {
    router.get(route('translations'), { ...form }, { preserveState: true, preserveScroll: true, replace: true });
}

function save(entry, value) {
    saving.value = entry.key;
    router.put(route('translations'), { locale: form.locale, key: entry.key, value }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => toast.success(value ? t('Saved. Everyone sees it on their next page.') : t('Back to the shipped wording.')),
        onError: () => toast.error(t('Could not save. Please try again.')),
        onFinish: () => { saving.value = null; },
    });
}
</script>
