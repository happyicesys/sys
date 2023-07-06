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
                    <div class="p-1 bg-white border-b border-gray-200">
                        <Graph
                            :key="componentKey"
                            type="scatter"
                            :labels="labels"
                            :datasets="datasets"
                            :options="graphOptions"
                        ></Graph>
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
    import moment from 'moment';

    const props = defineProps({
        dayGraphData: Object,
    });

    const filters = ref({
        day_date_from: '',
        day_date_to: '',
    })

    const componentKey = ref(0);
    const dayGraphData = ref([]);
    const datasets = ref([])
    const labels = ref([])
    const operator = usePage().props.auth.operator
    const graphOptions = ref({
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
                }
            },
            y1: {
                position: 'right',
                title: {
                    display: true,
                    text: 'Sales(#)'
                }
            }
        },
        plugins: {
            title: {
                display: true,
                text: 'Sales by Days (' + operator.name + ')'
            },
            // tooltip: {
            //     callbacks: {
            //         label: function(context) {
            //             var label = context.dataset.label.slice(0,2) || '';
            //             if (label) {
            //                 label += ': ';
            //             }
            //             if (context.parsed.y !== null) {
            //                 label += context.parsed.y + '°C';
            //             }
            //             return label;
            //         }
            //     }
            // }
        }
    })
    const forceRerender = () => {
        componentKey.value += 1;
    };

    onBeforeMount(() => {

        dayGraphData.value = JSON.parse(JSON.stringify(props.dayGraphData))
        let months = []
        let colors = ['#3e95cd', '#ff7f7f', '#3cba9f', '#c45850', '#c45850']
        months = _.groupBy(JSON.parse(JSON.stringify(props.dayGraphData)).data, 'month_name')
        Object.keys(months).forEach((month, monthIndex) => {
            datasets.value.push({
                label: month + ' ($)',
                data: months[month].map((data) => {return data.amount}),
                backgroundColor: monthIndex % 2 == 0 ? hexToRGBA(colors[monthIndex], 0.4) : hexToRGBA(colors[monthIndex], 0.9),
                borderColor: colors[monthIndex],
                fill: false,
                yAxisID: 'y',
                type: 'bar',
                order: 2,
            })
            datasets.value.push({
                label: month + ' (#)',
                data: months[month].map((data) => {return data.count}),
                backgroundColor: monthIndex % 2 == 0 ? hexToRGBA(colors[monthIndex + 2], 0.4) : hexToRGBA(colors[monthIndex + 2], 0.9),
                borderColor: monthIndex % 2 == 0 ? hexToRGBA(colors[monthIndex + 2], 0.4) : hexToRGBA(colors[monthIndex + 2], 0.9),
                yAxisID: 'y1',
                type: 'line',
                order: 1,
            })
        })

        for(let i = 1; i <= 31; i++) {
            labels.value.push(i)
        }
    })

    function hexToRGBA(hex, alpha) {
        var r = parseInt(hex.slice(1, 3), 16);
        var g = parseInt(hex.slice(3, 5), 16);
        var b = parseInt(hex.slice(5, 7), 16);

        return 'rgba(' + r + ', ' + g + ', ' + b + ', ' + alpha + ')';
    }

</script>
