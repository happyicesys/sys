<template>
    <div class="md:hidden overflow-x-hidden overflow-y-auto">
        <Vue3ChartJs :height="410" id="chartjs-mobile" :type="type" :data="data" ref="chartRef" :plugins="plugins" :options="options">
        </Vue3ChartJs>
    </div>
    <div class="hidden md:block">
        <Vue3ChartJs id="chartjs-desktop" :type="type" :data="data" ref="chartRef"  :plugins="plugins" :options="options">
        </Vue3ChartJs>
    </div>
</template>

<script setup>
    import Vue3ChartJs from '@j-t-mcc/vue3-chartjs';
    import { Chart } from 'chart.js';
    import annotationPlugin from 'chartjs-plugin-annotation';
    import 'chartjs-adapter-moment';

    Chart.register(annotationPlugin);
    import ChartDataLabels from 'chartjs-plugin-datalabels';
    import { ref } from 'vue'

    const props = defineProps({
        type: {
            type: String,
            default: 'line',
        },
        labels: [Array, Object, String],
        values: Object,
        options: Object,
        datasets: [Array, Object],
        plugins: [Array, Object],
    });

    const chartRef = ref(null)
    const data = ref({
                    labels: props.labels,
                    datasets: props.datasets
                })
    const options = ref({
                    responsive: true,
                    elements: {
                        point: {
                            radius: 2
                        }
                    },
                    ...props.options,
                })
    // const plugins = ref([ChartDataLabels])
    const plugins = ref(props.plugins)

</script>