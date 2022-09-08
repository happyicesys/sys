<template>
    <div class="md:hidden overflow-scroll">
        <vue3-chart-js height="430" id="chartjs" type="line" :data="data" :options="options">
        </vue3-chart-js>
    </div>
    <div class="hidden md:block overflow-scroll">
        <vue3-chart-js id="chartjs" type="line" :data="data" :options="options">
        </vue3-chart-js>
    </div>
</template>

<script>
import Vue3ChartJs from '@j-t-mcc/vue3-chartjs';

export default {
    name: 'App',
    components: {
        Vue3ChartJs,
    },
    props: {
        type: String,
        labels: Object,
        values: Object,
    },
    data() {
        return {
            data: {
                labels: this.labels,
                datasets: [{
                    label: 'Temp',
                    data: this.values,
                    borderColor: '#E6676B',
                    backgroundColor: '#E6676B',
                    tension: 0.1,
                }]
            },
            options: {
                elements: {
                    point: {
                        radius: 2
                    }
                },
            }
        }
    },
}
</script>