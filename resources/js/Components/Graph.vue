<template>
    <div class="md:hidden overflow-scroll">
        <Vue3ChartJs :height="410" id="chartjs" type="line" :data="data" ref="chartRef" :options="options">
        </Vue3ChartJs>
    </div>
    <div class="hidden md:block overflow-scroll">
        <Vue3ChartJs id="chartjs" type="line" :data="data" ref="chartRef" :options="options">
        </Vue3ChartJs>
    </div>
</template>

<script setup>
    import Vue3ChartJs from '@j-t-mcc/vue3-chartjs';
    import 'chartjs-adapter-moment';
    import { ref, watch } from 'vue'

    const props = defineProps({
        type: String,
        labels: [Array, Object, String],
        values: Object,
        options: Object,
        datasets: [Array, Object],
    });

    const chartRef = ref(null)
    const data = ref({
                    labels: props.labels,
                    datasets: props.datasets
                })
    const options = ref({
                    elements: {
                        point: {
                            radius: 2
                        }
                    },
                    ...props.options,
                })

</script>