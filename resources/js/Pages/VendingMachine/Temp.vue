
<template>

    <Head title="Vending Machine" />

    <BreezeAuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Vending Machine Temp {{ vendingMachine.code }}
            </h2>
        </template>

        <!-- <div class="py-5"> -->
        <!-- <div class="max-w-10xl mx-auto sm:px-6 lg:px-8"> -->
        <div class="p-4 sm:px-6 lg:px-8">
            <div class="sm:flex sm:items-center">
                <section class="sm:flex sm:space-x-5 space-y-2 sm:space-y-0">
                    <Datepicker v-model="searchFilters.date_from"></Datepicker>
                </section>
                <!-- {{ searchFilters + 'ddd' }} -->
            </div>
            <div class="py-3 text-left">
                <button type="button"
                    class="inline-flex items-center px-3 py-2 border border-gray-300 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    @click="back()">Back</button>
            </div>
            <div class="px-2 mt-5 flex flex-col">
                <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
                    <div class="inline-block min-w-full py-2 align-middle">
                        <div class="shadow-sm ring-1 ring-black ring-opacity-5">
                            <Graph type="line" :labels="vendingMachineTime" :values="vendingMachineTemps"></Graph>
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
import Datepicker from '@/Components/Datepicker.vue';
import { Head } from '@inertiajs/inertia-vue3';
import { Link } from '@inertiajs/inertia-vue3';
import SearchInput from '@/Components/SearchInput.vue';
import Graph from '@/Components/Graph.vue';

export default {
    components: {
        BreezeAuthenticatedLayout,
        Datepicker,
        Head,
        Link,
        SearchInput,
        Graph,
    },
    props: {
        vendingMachineObj: Object,
        vendingMachineTempsObj: Object,
    },
    data() {
        return {
            searchFilters: {
                date_from: '',
                date_to: '',
            },
            dateFormatter: {
                date: 'YYYY-MM-DD',
                month: 'MM'
            },
            vendingMachineTemps: this.vendingMachineTempsObj.data.map(a => a.value),
            vendingMachineTime: this.vendingMachineTempsObj.data.map(a => a.created_at),
            vendingMachine: this.vendingMachineObj.data,
        }
    },
    methods: {
        onSearchFilterUpdated() {
            console.log('hereman')
            this.$inertia.get('/vending-machine', {
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