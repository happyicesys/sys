<template>
    <div class="md:hidden overflow-scroll">
        <Vue3ChartJs :height="410" id="chartjs" type="line" :data="data" :options="options">
        </Vue3ChartJs>
    </div>
    <div class="hidden md:block overflow-scroll">
        <Vue3ChartJs id="chartjs" type="line" :data="data" :options="options">
        </Vue3ChartJs>
    </div>
</template>

<script setup>
    import Vue3ChartJs from '@j-t-mcc/vue3-chartjs';
import moment from 'moment';
    import { ref } from 'vue';

    const props = defineProps({
        type: String,
        labels: Object,
        values: Object,
        startDatetime: [String, Object],
        endDatetime: [String, Object],
    });

    const data = ref({
                    labels: props.labels,
                    datasets: [{
                        label: 'Temp',
                        data: props.values,
                        borderColor: '#E6676B',
                        backgroundColor: '#E6676B',
                        tension: 0.1,
                    }]
                })
    const options = ref({
                    elements: {
                        point: {
                            radius: 2
                        }
                    },
                    // scales: {
                    //     x: {
                    //         suggestedMin: moment(props.startDatetime).startOf('hour').toString(),
                    //         suggestedMax: moment(props.endDatetime).endOf('hour').toString(),
                    //     }
                    // }
                })
</script>