<!-- This example requires Tailwind CSS v2.0+ -->
<template>
    <div class="bg-white px-4 py-3 flex items-center justify-between border-t border-gray-200 sm:px-6">
        <div class="flex-1 flex justify-between sm:hidden">
            <Link :href="links && links.prev ? links.prev : '#'"
                class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md"
                :class="[!links && links.prev ? 'opacity-25 cursor-not-allowed' : 'text-gray-700 bg-white hover:bg-gray-50']"
                :disabled="!links.prev" preserve-scroll>
                Previous </Link>
            <Link :href="links && links.next ? links.next : '#'"
                class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md"
                :class="[!links && links.next ? 'opacity-25 cursor-not-allowed' : 'text-gray-700 bg-white hover:bg-gray-50']"
                :disabled="!links.next" preserve-scroll>
                Next </Link>
        </div>
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-gray-700">
                    Showing
                    {{ ' ' }}
                    <span class="font-medium">{{ meta.from }}</span>
                    {{ ' ' }}
                    to
                    {{ ' ' }}
                    <span class="font-medium">{{ meta.to }}</span>
                    {{ ' ' }}
                    of
                    {{ ' ' }}
                    <span class="font-medium">{{ meta.total }}</span>
                    {{ ' ' }}
                    results
                </p>
            </div>
            <div>
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                    <Component :is="links && links.prev ? 'Link' : 'span'" :href="links && links.prev ? links.prev : '#'"
                        class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium"
                        :class="[!links.prev ? 'opacity-25 cursor-not-allowed' : 'text-gray-500 hover:bg-gray-50']"
                        :disabled="!links.prev" preserve-scroll>
                        <span class="sr-only">Previous</span>
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
                        <span class="sr-only">Next</span>
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

const props = defineProps({
    links: Object,
    meta: Object,
})

</script>