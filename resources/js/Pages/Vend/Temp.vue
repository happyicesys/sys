
<template>

    <Head title="Vending Machine" />

    <BreezeAuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Vending Machine Temp {{ vend.code }}
            </h2>
        </template>

        <!-- <div class="py-5"> -->
        <!-- <div class="max-w-10xl mx-auto sm:px-6 lg:px-8"> -->
        <div class="p-4 sm:px-6 lg:px-8">
            <div class="py-3 text-left">
                <Button
                    class="border-gray-300 text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    @click="back()">
                    Back
                </Button>
            </div>
            <div class="py-3 flex space-x-2">
                <Button v-for="durationFilter in durationFilters"
                    class="border-transparent bg-indigo-600 px-3 py-2 text-sm font-medium leading-4 text-white shadow-sm hover:bg-indigo-700"
                    :class="durationFilter == duration ? 'outline-none ring-2 ring-indigo-500 ring-offset-2' : ''"
                    @click="onDurationFilterClicked(durationFilter)">
                    {{ durationFilter }} Days
                </Button>
            </div>
            <div class="px-2 mt-5 flex flex-col">
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
import { Head } from '@inertiajs/inertia-vue3';
import { Link } from '@inertiajs/inertia-vue3';
import SearchInput from '@/Components/SearchInput.vue';
import Graph from '@/Components/Graph.vue';
import { isInteger } from 'lodash';

export default {
    components: {
        BreezeAuthenticatedLayout,
        Button,
        Datepicker,
        Head,
        Link,
        SearchInput,
        Graph,
    },
    props: {
        duration: Number,
        vendObj: Object,
        vendTempsObj: Object,
    },
    data() {
        return {
            durationFilters: {
                3: 3,
                7: 7,
                14: 14,
            },
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