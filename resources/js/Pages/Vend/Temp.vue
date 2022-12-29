
<template>

    <Head title="Vending Machine" />

    <BreezeAuthenticatedLayout>
        <template #header>
            <div class="flex flex-col space-y-1">
                <div class="flex space-x-2 items-center">
                    <h2 class="font-semibold text-md md:text-xl text-gray-700 leading-tight">
                        Vend ID
                    </h2>
                    <h2 class="font-semibold text-xl md:text-2xl text-gray-900 leading-tight">
                        {{ vend.code }}
                    </h2>
                    <h2 class="font-semibold text-md md:text-xl text-gray-700 leading-tight">
                        {{ type.name }} Temperature
                    </h2>
                </div>
            </div>
        </template>

        <!-- <div class="py-5"> -->
        <!-- <div class="max-w-10xl mx-auto sm:px-6 lg:px-8"> -->
        <div class="p-4 sm:px-6 lg:px-8">
            <div class="flex flex-col items-start pl-1">
                <h2 class="font-semibold text-md md:text-lg text-gray-700 leading-tight" v-if="vend.latestVendBinding && vend.latestVendBinding.customer">
                    {{ vend.latestVendBinding.customer.code }}
                </h2>
                <h2 class="font-semibold text-md md:text-lg text-gray-700 leading-tight" v-if="vend.latestVendBinding && vend.latestVendBinding.customer">
                    {{ vend.latestVendBinding.customer.name }}
                </h2>
            </div>
            <div class="flex space-x-2 font-semibold text-md text-gray-500 leading-tight pl-1">
                <h2>
                    {{ startDateString }}
                </h2>
                <h2>
                    to
                </h2>
                <h2>
                    {{ endDateString }}
                </h2>
            </div>
            <div class="pl-1 py-2 text-left">
                <Button
                    class="border-gray-300 text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 px-7 sm:px-3"
                    @click="back()">
                    <ArrowUturnLeftIcon class="mr-2 flex-shrink-0 h-4 w-4 text-gray-400 group-hover:text-gray-500" aria-hidden="true" />
                    Back
                </Button>
            </div>
<!--
            <div class="grid grid-cols-1 md:grid-cols-3">
                <div>
                    <label for="text" class="block text-sm font-medium text-gray-700">
                        Vend ID
                    </label>
                    <MultiSelect
                        v-model="filters.vend_id"
                        :options="vendOptions"
                        valueProp="id"
                        label="code"
                        placeholder="Select"
                        open-direction="bottom"
                        class="mt-1"
                    >
                    </MultiSelect>
                </div>
            </div> -->

            <label for="text" class="pt-4 pl-1 block text-sm font-medium text-gray-700">
                Shortcut
            </label>
            <div class="pl-1 py-2 flex space-x-2 overflow-x-scroll">
                <Button
                    v-for="hourDurationFilter in hourDurationFilters"
                    class="border-transparent bg-indigo-600 py-2 text-sm font-medium leading-4 text-white shadow-sm hover:bg-indigo-700 px-10 sm:px-3"
                    :class="hourDurationFilter == filters.duration ? 'outline-none ring-2 ring-indigo-500 ring-offset-2' : ''"
                    @click="onDurationFilterClicked(hourDurationFilter, 'hour')">
                    {{ hourDurationFilter }} {{ hourDurationFilter > 1 ? 'Hours' : 'Hour' }}
                </Button>
                <Button
                    v-for="durationFilter in durationFilters"
                    class="border-transparent bg-indigo-600 py-2 text-sm font-medium leading-4 text-white shadow-sm hover:bg-indigo-700 px-10 sm:px-3"
                    :class="durationFilter == filters.duration ? 'outline-none ring-2 ring-indigo-500 ring-offset-2' : ''"
                    @click="onDurationFilterClicked(durationFilter, 'day')">
                    {{ durationFilter }} {{ durationFilter > 1 ? 'Days' : 'Day' }}
                </Button>

            </div>
            <div class="pl-1 py-3 grid grid-cols-1 md:grid-cols-5 gap-2">
                <DatetimePicker
                    v-model="filters.datetime_from"
                    :maxDate="new Date()"
                    class="col-span-5 md:col-span-1"
                >
                    From
                </DatetimePicker>
                <DatetimePicker
                    v-model="filters.datetime_to"
                    :minDate="filters.datetime_from"
                    :maxDate="new Date()"
                    class="col-span-5 md:col-span-1"
                >
                    To
                </DatetimePicker>
                <div class="col-span-5">
                    <Button
                        class="border-transparent bg-green-600 py-3 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-700 px-10 sm:px-3 md:py-2 active:outline-none active:ring-2 active:ring-green-500 active:ring-offset-2"
                        @click.prevent="onCustomDatetimeSearched">

                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-4 h-4 pr-1">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
                        </svg>

                        <span>
                            Search
                        </span>
                    </Button>
                </div>
            </div>
            <div class="px-1 mt-2 flex flex-col">
                <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
                    <div class="inline-block min-w-full py-2 align-middle">
                        <div class="shadow-sm ring-1 ring-black ring-opacity-5">
                            <Graph type="line" :labels="vendTime" :values="vendTemps" :startDatetime="startDate" :endDatetime="endDate"></Graph>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- </div> -->
        <!-- </div> -->
    </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import DatetimePicker from '@/Components/DatetimePicker.vue';
import Graph from '@/Components/Graph.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { ArrowUturnLeftIcon } from '@heroicons/vue/20/solid'
import { ref, onMounted } from 'vue';
import { Inertia } from '@inertiajs/inertia';
import { Head } from '@inertiajs/inertia-vue3';

const props = defineProps({
    duration: [Number, String],
    endDate: String,
    endDateString: String,
    request: Object,
    startDate: String,
    startDateString: String,
    type: [String, Object, Array],
    vendOptions: Object,
    vendObj: Object,
    vendTempsObj: Object,
});

const hourDurationFilters = ref([6])
const durationFilters = ref([1, 3, 7, 14])
const filters = ref({
    datetime_from: props.startDate ? new Date(props.startDate) : new Date(),
    datetime_to: props.endDate ? new Date(props.endDate) : new Date(),
    duration: props.duration,
    // showDurationFilters: this.showDurationFilters,
})
const vendTemps = ref(props.vendTempsObj.data.map(a => a.value))
const vendTime = ref(props.vendTempsObj.data.map(a => a.created_at))
const vend = ref(props.vendObj.data)
const vendOptions = ref([])


onMounted(() => {
    vendOptions.value = props.vendOptions.data.map((vend) => {return {id: vend.id, code: vend.code}})
  })

function onCustomDatetimeSearched() {
    Inertia.get(
        '/vends/' +
        vend.value.id +
        '/temp/' +
        props.type.value
    , filters.value, {
        preserveScroll: true,
    })
}

function onDurationFilterClicked(duration, durationType) {
    Inertia.get('/vends/' + vend.value.id + '/temp/'+ props.type.value +'?duration=' + duration + '&durationType=' + durationType)
}

function back() {
    window.history.back();
}
</script>