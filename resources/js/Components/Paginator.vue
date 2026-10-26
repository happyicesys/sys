<!-- This example requires Tailwind CSS v2.0+ -->
<template>
    <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
        <div class="flex-1 flex justify-between sm:hidden">
            <Link :href="links && links.prev ? links.prev : '#'"
                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md"
                :class="[!links && links.prev ? 'opacity-25 cursor-not-allowed' : 'text-gray-700 bg-white hover:bg-gray-50']"
                :disabled="!links.prev" preserve-scroll>
                {{ $t('Previous') }} </Link>
            <Link :href="links && links.next ? links.next : '#'"
                class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md"
                :class="[!links && links.next ? 'opacity-25 cursor-not-allowed' : 'text-gray-700 bg-white hover:bg-gray-50']"
                :disabled="!links.next" preserve-scroll>
                {{ $t('Next') }} </Link>
        </div>
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-gray-700">
                    <template v-for="(part, partIndex) in showingParts" :key="partIndex"><span v-if="part.bold" class="font-medium">{{ part.text }}</span><template v-else>{{ part.text }}</template></template>
                </p>
            </div>
            <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" :aria-label="$t('Pagination')">
                    <Component :is="links && links.prev ? 'Link' : 'span'" :href="links && links.prev ? links.prev : '#'"
                        class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium"
                        :class="[!links.prev ? 'opacity-25 cursor-not-allowed' : 'text-gray-500 hover:bg-gray-50']"
                        :disabled="!links.prev" preserve-scroll>
                        <span class="sr-only">{{ $t('Previous') }}</span>
                        <ChevronDoubleLeftIcon class="h-5 w-5" aria-hidden="true" />
                    </Component>
                    <!-- Current: "z-10 bg-indigo-50 border-indigo-500 text-indigo-600", Default: "bg-white border-gray-300 text-gray-500 hover:bg-gray-50" -->
                    <Link v-for="(link, linkIndex) in meta.links"
                        v-show="linkIndex != 0 && linkIndex != meta.links.length - 1" :href="link && link.url ? link.url : '#'"
                        class="relative inline-flex items-center px-4 py-2 border text-sm font-medium"
                        :class="[link.active ? 'z-10 bg-indigo-50 border-indigo-500 text-indigo-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50']"
                        preserve-scroll>
                    {{ link.label }}
                    </Link>
                    <Component :is="links && links.next ? 'Link' : 'span'" :href="links && links.next ? links.next : '#'"
                        class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium"
                        :class="[!links.next ? 'opacity-25 cursor-not-allowed' : 'text-gray-500 hover:bg-gray-50']"
                        :disabled="!links.next" preserve-scroll>
                        <span class="sr-only">{{ $t('Next') }}</span>
                        <ChevronDoubleRightIcon class="h-5 w-5" aria-hidden="true" />
                    </Component>

                </nav>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ChevronDoubleLeftIcon, ChevronDoubleRightIcon } from '@heroicons/vue/24/outline'
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { t } from '@/i18n';

const props = defineProps({
    links: Object,
    meta: Object,
})

// "Showing 1 to 10 of 50 results" as ONE translatable sentence, the three numbers still bold.
const showingParts = computed(() => t('Showing :from to :to of :total results')
    .split(/(:from|:total|:to)/)
    .filter((piece) => piece !== '')
    .map((piece) => (piece === ':from' || piece === ':to' || piece === ':total'
        ? { text: props.meta ? props.meta[piece.slice(1)] : '', bold: true }
        : { text: piece, bold: false })));

</script>