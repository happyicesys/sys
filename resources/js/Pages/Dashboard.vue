<template>

    <Head title="Dashboard" />

    <BreezeAuthenticatedLayout>
        <template #header>
            <div class="flex flex-col space-y-1">
                <div class="flex space-x-2 items-center">
                    Dashboard
                </div>
            </div>
        </template>


        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-3 lg:px-2">
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-1 bg-white border-b border-gray-200 flex flex-col space-x-6">
                        <Graph
                            key="dayGraphData"
                            type="scatter"
                            :labels="dayGraphLabels"
                            :datasets="dayGraphDatasets"
                            :options="dayGraphOptions"
                        ></Graph>

                        <div class="flex flex-col md:flex-row pt-5">
                            <div class="md:basis-1/3 m-1">
                                <Graph
                                    key="productGraphData"
                                    type="pie"
                                    :labels="productGraphLabels"
                                    :datasets="productGraphDatasets"
                                    :options="productGraphOptions"
                                ></Graph>
                            </div>
                            <div class="md:basis-2/3 my-2 mx-4 px-4">
                                <p class="text-sm">
                                    Past 7 Days - Top 10 Best Performance
                                </p>
                                <div class="mt-2 flow-root">
                                    <div class="-mx-4 -my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                                        <div class="inline-block min-w-full py-2 align-middle sm:px-3 lg:px-4">
                                        <div class="overflow-auto shadow ring-1 ring-black ring-opacity-5 sm:rounded-lg">
                                            <table class="min-w-full divide-y divide-gray-300">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th scope="col" class="py-2 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">
                                                        #
                                                    </th>
                                                    <th scope="col" class="px-3 py-2 text-left text-sm font-semibold text-gray-900">
                                                        Vending Machine
                                                    </th>
                                                    <th scope="col" class="px-3 py-2 text-left text-sm font-semibold text-gray-900">
                                                        Amount($)
                                                    </th>
                                                    <th scope="col" class="px-3 py-2 text-left text-sm font-semibold text-gray-900">
                                                        Sales(#)
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-gray-200 bg-white">
                                                <tr v-for="(vend, vendIndex) in performerGraphData.data" :key="vend.id">
                                                    <td class="whitespace-nowrap py-2 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">
                                                        {{ vendIndex + 1 }}
                                                    </td>
                                                    <td class="whitespace-nowrap px-3 py-2 text-sm text-gray-600">
                                                        <span v-if="vend.customer">
                                                            {{ vend.customer.code }} <br>
                                                            {{ vend.customer.name }}
                                                        </span>
                                                        <span v-else>
                                                            {{ vend.name }}
                                                        </span>
                                                    </td>
                                                    <td class="whitespace-nowrap px-3 py-2 text-sm text-gray-500 text-right mx-3">
                                                        {{ vend.amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) }}
                                                    </td>
                                                    <td class="whitespace-nowrap px-3 py-2 text-sm text-gray-500 text-right mx-3">
                                                        {{ vend.count }}
                                                    </td>
                                                </tr>
                                                <tr v-if="!performerGraphData.data.length">
                                                    <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                                                        No Results Found
                                                    </td>
                                                </tr>
                                            </tbody>
                                            </table>
                                        </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </BreezeAuthenticatedLayout>
</template>
<script setup>
    import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
    import Graph from '@/Components/Graph.vue';
    import { ref, onBeforeMount, watch } from 'vue';
    import { Head, router, usePage } from '@inertiajs/vue3';

    const props = defineProps({
        dayGraphData: Object,
        productGraphData: Object,
        performerGraphData: Object,
    });

    const filters = ref({
        day_date_from: '',
        day_date_to: '',
    })

    const componentKey = ref(0);

    const operator = usePage().props.auth.operator
        const forceRerender = () => {
        componentKey.value += 1;
    };

    const dayGraphData = ref([]);
    const dayGraphDatasets = ref([])
    const dayGraphLabels = ref([])
    const dayGraphOptions = ref({
        scales: {
            x: {
                ticks: {
                    min: 1,  // Minimum value on the x-axis
                    max: 31, // Maximum value on the x-axis
                    stepSize: 1 // Increment between ticks
                }
            },
            y: {
                position: 'left',
                title: {
                    display: true,
                    text: 'Sales($)'
                },
                beginAtZero: true
            },
            y1: {
                position: 'right',
                title: {
                    display: true,
                    text: 'Sales(#)'
                },
                beginAtZero: true
            },
            yAxes: [
                {

                }
            ]
        },
        plugins: {
            title: {
                display: true,
                text: 'Sales by Days (' + operator.name + ')'
            },
            legend: {
                reverse: true,
            }
        }
    })

    const productGraphData = ref([])
    const productGraphDatasets = ref([])
    const productGraphLabels = ref([])
    const productGraphOptions = ref({
        plugins: {
            legend: {
                display: false,
            },
            title: {
                display: true,
                text: 'Past 7 Days - 10 Best Sellers (' + operator.name + ')'
            },
        }
    })

    const performerGraphData = ref([])


    onBeforeMount(() => {

        dayGraphData.value = JSON.parse(JSON.stringify(props.dayGraphData))
        let months = []
        let colors = ['#3e95cd', '#ff7f7f', '#007500', '#808080', '#c45850']
        let generalColors = [
            '#37a2eb',
            '#ff6384',
            '#4cc1c0',
            '#ff9f40',
            '#9a66ff',
            '#ffcd56',
            '#c9cbcf'
        ]
        months = _.groupBy(JSON.parse(JSON.stringify(props.dayGraphData)).data, 'month_name')
        Object.keys(months).forEach((month, monthIndex) => {
            dayGraphDatasets.value.push({
                label: month + ' (#)',
                data: months[month].map((data) => {return data.count}),
                backgroundColor: monthIndex % 2 == 0 ? hexToRGBA(colors[monthIndex + 2], 0.2) : hexToRGBA(colors[monthIndex + 2], 0.9),
                borderColor: monthIndex % 2 == 0 ? hexToRGBA(colors[monthIndex + 2], 0.2) : hexToRGBA(colors[monthIndex + 2], 0.9),
                yAxisID: 'y1',
                type: 'line',
                order: 1,
            })
            dayGraphDatasets.value.push({
                label: month + ' ($)',
                data: months[month].map((data) => {return data.amount}),
                backgroundColor: monthIndex % 2 == 0 ? hexToRGBA(colors[monthIndex], 0.2) : hexToRGBA(colors[monthIndex], 1),
                borderColor: monthIndex % 2 == 0 ? hexToRGBA(colors[monthIndex], 0.2) : hexToRGBA(colors[monthIndex], 1),
                fill: false,
                yAxisID: 'y',
                type: 'bar',
                order: 2,
            })
        })
        for(let i = 1; i <= 31; i++) {
            dayGraphLabels.value.push(i)
        }

        productGraphData.value = JSON.parse(JSON.stringify(props.productGraphData))
        productGraphDatasets.value.push({
            label: 'Sales',
            data: productGraphData.value.data.map((data) => {return data.count}),
            backgroundColor: generalColors,
        })
        productGraphLabels.value = productGraphData.value.data.map((data) => {return data.product.code + ' - ' + data.product.name})

        performerGraphData.value = JSON.parse(JSON.stringify(props.performerGraphData))

    })

    function hexToRGBA(hex, alpha) {
        var r = parseInt(hex.slice(1, 3), 16);
        var g = parseInt(hex.slice(3, 5), 16);
        var b = parseInt(hex.slice(5, 7), 16);

        return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
    }

</script>
