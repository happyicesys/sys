<template>
    <Head title="Incoming History" />

    <BreezeAuthenticatedLayout>
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="mb-6 flex justify-between items-center">
                <Link :href="route('product-movements.index')" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <ArrowLeftIcon class="h-5 w-5 mr-2 text-gray-500" />
                    Back to List
                </Link>
                <h1 class="text-2xl font-bold text-gray-900">Incoming Stock History</h1>
                <div class="w-32"></div>
            </div>

            <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-8">
                <!-- Filter Section -->
                <!-- Filter Section -->
                <div class="p-4 border-b border-gray-200 bg-gray-50 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                     <h3 class="text-lg leading-6 font-medium text-gray-900">
                        History Records
                    </h3>
                    <div class="flex flex-col md:flex-row space-y-2 md:space-y-0 md:space-x-3 items-center">
                         <div class="w-full md:w-64">
                            <DatePicker v-model="filters.date" :enableTimePicker="false" auto-apply placeholder="Filter by Date" :format="'yyyy-MM-dd'" clearable />
                        </div>
                        <div class="flex space-x-2">
                            <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2" @click="resetFilter">
                                <BackspaceIcon class="h-4 w-4" aria-hidden="true"/>
                                <span>Reset</span>
                            </Button>
                            <a :href="exportUrl" target="_blank" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 active:bg-green-900 focus:outline-none focus:border-green-900 focus:ring ring-green-300 disabled:opacity-25 transition ease-in-out duration-150">
                                <ArrowDownTrayIcon class="w-4 h-4 mr-2" />
                                Export Excel
                            </a>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-300">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="py-3.5 pl-4 pr-3 text-center text-xs font-medium uppercase tracking-wide text-gray-500 sm:pl-6">#</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-xs font-medium uppercase tracking-wide text-gray-500">Batch Number</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-xs font-medium uppercase tracking-wide text-gray-500">Date</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-xs font-medium uppercase tracking-wide text-gray-500">Time</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-xs font-medium uppercase tracking-wide text-gray-500">Input By</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-xs font-medium uppercase tracking-wide text-gray-500">Remarks</th>
                                <th scope="col" class="px-3 py-3.5 text-center text-xs font-medium uppercase tracking-wide text-gray-500">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 bg-white">
                            <tr v-for="(batch, index) in history.data" :key="index" :class="index % 2 === 0 ? 'bg-white' : 'bg-gray-50'">
                                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                                    {{ (history.current_page - 1) * history.per_page + index + 1 }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-900 text-center font-bold">{{ batch.batch_number }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 text-center">{{ formatDate(batch.created_at) }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 text-center">{{ formatTime(batch.created_at) }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 text-center">{{ batch.user ? batch.user.name : (batch.operator ? batch.operator.name : '-') }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 text-center max-w-xs truncate" :title="batch.remarks">{{ batch.remarks || '-' }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 text-center">
                                    <Link :href="route('product-movements.incoming-batch-detail', batch.batch_number)" class="text-indigo-600 hover:text-indigo-900 font-bold">
                                        View
                                    </Link>
                                </td>
                            </tr>
                            <tr v-if="history.data.length === 0">
                                <td colspan="7" class="px-3 py-4 text-sm text-gray-500 text-center">No history records found.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <!-- Pagination -->
                <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6" v-if="history.data.length > 0">
                     <Paginator
                        :links="{ prev: history.prev_page_url, next: history.next_page_url }"
                        :meta="{ from: history.from, to: history.to, total: history.total, links: history.links }"
                     />
                </div>
            </div>
        </div>
    </BreezeAuthenticatedLayout>
</template>

<script setup>
import { ref, watch } from 'vue'
import { Head, Link, router } from '@inertiajs/vue3'
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import DatePicker from '@/Components/DatePicker.vue'
import Button from '@/Components/Button.vue'
import Paginator from '@/Components/Paginator.vue'
import { ArrowLeftIcon, ArrowDownTrayIcon, BackspaceIcon } from '@heroicons/vue/24/solid'
import moment from 'moment'
import { computed } from 'vue'

const props = defineProps({
    history: Object,
    filters: Object,
})

const filters = ref({
    date: props.filters.date || null,
})

watch(filters.value, (value) => {
    router.get(route('product-movements.incoming-history'), { ...value }, {
        preserveState: true,
        replace: true,
    })
})

const resetFilter = () => {
    filters.value.date = null
}

const formatDate = (date) => {
    return moment(date).format('YYYY-MM-DD')
}

const formatTime = (date) => {
    return moment(date).format('hh:mm A')
}

const exportUrl = computed(() => {
    const params = new URLSearchParams()
    if (filters.value.date) {
        params.append('date', moment(filters.value.date).format('YYYY-MM-DD'))
    }
    return route('product-movements.incoming-history-export') + '?' + params.toString()
})
</script>
