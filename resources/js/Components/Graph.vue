<template>
    <div>
        <vue3-chart-js id="chartjs" type="line" :data="data" :options="options"></vue3-chart-js>
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
                    borderColor: 'rgb(75, 192, 192)',
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