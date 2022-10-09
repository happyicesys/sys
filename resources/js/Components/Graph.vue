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
    import { ref } from 'vue';

    const props = defineProps({
        type: String,
        labels: Object,
        values: Object,
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
                })
</script>