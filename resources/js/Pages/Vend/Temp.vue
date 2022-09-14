
<template>

    <Head title="Vending Machine" />

    <BreezeAuthenticatedLayout>
        <template #header>
            <div class="flex flex-col space-y-2">
                <div class="flex space-x-2 items-center">
                    <h2 class="font-semibold text-xl text-gray-700 leading-tight">
                        Vending Machine
                    </h2>
                    <h2 class="font-semibold text-2xl text-gray-900 leading-tight">
                        {{ vend.code }}
                    </h2>
                    <h2 class="font-semibold text-xl text-gray-700 leading-tight">
                        Temperature
                    </h2>
                </div>
                <div class="flex space-x-2 font-semibold text-md text-gray-500 leading-tight">
                    <h2>
                        {{ startDate }}
                    </h2>
                    <h2>
                        to
                    </h2>
                    <h2>
                        {{ endDate }}
                    </h2>
                </div>
            </div>
        </template>

        <!-- <div class="py-5"> -->
        <!-- <div class="max-w-10xl mx-auto sm:px-6 lg:px-8"> -->
        <div class="p-4 sm:px-6 lg:px-8">
            <div class="pl-1 py-2 text-left">
                <Button
                    class="border-gray-300 text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 px-7 sm:px-3"
                    @click="back()">
                    <ArrowLeftIcon class="mr-2 flex-shrink-0 h-4 w-4 text-gray-400 group-hover:text-gray-500" aria-hidden="true" />
                    Back
                </Button>
            </div>
            <div class="pl-1 py-3 flex space-x-2 overflow-x-scroll">
                <Button v-for="durationFilter in durationFilters"
                    class="border-transparent bg-indigo-600 py-2 text-sm font-medium leading-4 text-white shadow-sm hover:bg-indigo-700 px-10 sm:px-3"
                    :class="durationFilter == duration ? 'outline-none ring-2 ring-indigo-500 ring-offset-2' : ''"
                    @click="onDurationFilterClicked(durationFilter)">
                    {{ durationFilter }} {{ durationFilter > 1 ? 'Days' : 'Day' }}
                </Button>
            </div>
            <div class="px-1 mt-2 flex flex-col">
                <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
                    <div class="inline-block min-w-full py-2 align-middle">
                        <div class="shadow-sm ring-1 ring-black ring-opacity-5">
                            <Graph type="line" :labels="vendTime" :values="vendTemps"></Graph>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- </div> -->
        <!-- </div> -->
    </BreezeAuthenticatedLayout>
</template>

<script>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import Datepicker from '@/Components/Datepicker.vue';
import SearchInput from '@/Components/SearchInput.vue';
import Graph from '@/Components/Graph.vue';
import { isInteger } from 'lodash';
import { ArrowLeftIcon } from '@heroicons/vue/solid'

export default {
    components: {
		ArrowLeftIcon,
        BreezeAuthenticatedLayout,
        Button,
        Datepicker,
        SearchInput,
        Graph,
    },
    props: {
        duration: Number,
		endDate: String,
		startDate: String,
        vendObj: Object,
        vendTempsObj: Object,
    },
    data() {
        return {
            durationFilters: [
                1, 3, 7, 14
            ],
            vendTemps: this.vendTempsObj.data.map(a => a.value),
            vendTime: this.vendTempsObj.data.map(a => a.created_at),
            vend: this.vendObj.data,
        }
    },
    methods: {
        onDurationFilterClicked(duration) {
            this.$inertia.get('/vend/' + this.vend.id + '/temp/' + duration)
        },
        onSearchFilterUpdated() {
            this.$inertia.get('/vend', {
                date_from: this.searchFilters.date_from,
                date_to: this.searchFilters.date_to,
            }, {
                preserveState: true,
                replace: true,
            })
        },
        back() {
            window.history.back();
        }
    },
}
</script>