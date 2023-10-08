
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
                <h2 class="font-semibold text-md md:text-lg text-gray-700 leading-tight" v-if="vend.customer_code">
                    {{ vend.customer_code }}
                </h2>
                <h2 class="font-semibold text-md md:text-lg text-gray-700 leading-tight" v-if="vend.customer_name">
                    {{ vend.customer_name }}
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
                    class="col-span-5 md:col-span-1"
                >
                    From
                </DatetimePicker>
                <DatetimePicker
                    v-model="filters.datetime_to"
                    :minDate="filters.datetime_from"
                    class="col-span-5 md:col-span-1"
                >
                    To
                </DatetimePicker>
                <div class="col-span-5 flex space-x-1">
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
                    <Button class="inline-flex space-x-1 items-center rounded-md border border-gray-600 bg-white px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    @click="onExportExcelClicked()"
                    v-if="permissions.includes('export excel')"
                    >
                        <ArrowDownTrayIcon v-if="!loading" class="h-4 w-4" aria-hidden="true"/>
                        <svg v-if="loading" aria-hidden="true" class="mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
                            <path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
                        </svg>
                        <span>
                            Export Excel
                        </span>
                    </Button>
                </div>
            </div>
            <div class="px-1 mt-2 flex flex-col">
                <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
                    <div class="inline-block min-w-full py-2 align-middle">
                        <div class="shadow-sm ring-1 ring-black ring-opacity-5">
                            <div class="p-2 flex space-x-1">
                                <span class="inline-flex rounded-md shadow-sm" v-if="vend.temp && ('t2' in vend.parameterJson || 't3' in vend.parameterJson || 't4' in vend.parameterJson)">
                                    <span class="inline-flex items-center rounded-l-md rounded-r-md border border-gray-300 bg-white px-2 py-2">
                                    <input type="checkbox" value="1" v-model="types" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                        <label class="pl-2">T1</label>
                                    </span>
                                </span>
                                <span class="inline-flex rounded-md shadow-sm" v-if="'t2' in vend.parameterJson && vend.parameterJson['t2'] != tempError">
                                    <span class="inline-flex items-center rounded-l-md rounded-r-md border border-gray-300 bg-white px-2 py-2">
                                    <input type="checkbox" value="2" v-model="types" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                        <label class="pl-2">T2</label>
                                    </span>
                                </span>
                                <span class="inline-flex rounded-md shadow-sm " v-if="'t3' in vend.parameterJson && vend.parameterJson['t3'] != tempError">
                                    <span class="inline-flex items-center rounded-l-md rounded-r-md border border-gray-300 bg-white px-2 py-2">
                                    <input type="checkbox" value="3" v-model="types" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                        <label class="pl-2">T3</label>
                                    </span>
                                </span>
                                <span class="inline-flex rounded-md shadow-sm " v-if="'t4' in vend.parameterJson && vend.parameterJson['t4'] != tempError">
                                    <span class="inline-flex items-center rounded-l-md rounded-r-md border border-gray-300 bg-white px-2 py-2">
                                    <input type="checkbox" value="4" v-model="types" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                        <label class="pl-2">T4</label>
                                    </span>
                                </span>
                                <!-- <span class="inline-flex rounded-md shadow-sm " v-if="'fan' in vend.parameterJson">
                                    <span class="inline-flex items-center rounded-l-md rounded-r-md border border-gray-300 bg-white px-2 py-2">
                                    <input type="checkbox" value="1" v-model="fans" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
                                        <label class="pl-2">Fan</label>
                                    </span>
                                </span> -->
                            </div>
                            <Graph
                                :key="componentKey"
                                type="line"
                                :labels="labels"
                                :datasets="datasets"
                                :options="graphOptions"
                            ></Graph>
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
  // import MultiSelect from '@/Components/MultiSelect.vue';
  import { ArrowDownTrayIcon, ArrowUturnLeftIcon } from '@heroicons/vue/20/solid'
  import { ref, onBeforeMount, watch } from 'vue';
  import { Head, router, usePage } from '@inertiajs/vue3';
  import moment from 'moment';

  const props = defineProps({
    duration: [Number, String],
    endDate: String,
    endDateString: String,
    request: Object,
    startDate: String,
    startDateString: String,
    type: [String, Object, Array],
    tempError: [Number, String],
    fans: [String, Object, Array],
    vendObj: Object,
    vendTempsObj: Object,
    vendFansObj: Object,
  });

  const hourDurationFilters = ref([6])
  const durationFilters = ref([1, 3, 7, 14])
  const filters = ref({
    datetime_from: props.startDate ? props.startDate : moment().format('YYYY-MM-DD HH:mm:ss'),
    datetime_to: props.endDate ? props.endDate : moment().format('YYYY-MM-DD HH:mm:ss'),
    duration: props.duration,
  })
  const labels = ref([])
  const datasets = ref([])
  const permissions = usePage().props.auth.permissions
  const vend = ref(props.vendObj.data)
  const vendTemps = ref()
  const vendFans = ref()
  const types = ref([props.type.value])
  const fans = ref([props.fans])
  const componentKey = ref(0);
  const loading = ref(false)
  const graphOptions = ref({
    scales: {
        x: {
            type: 'time',
            time: {
                displayFormats: {
                    hour: 'ha (DD)'
                },
                tooltipFormat: 'YYMMDD hh:mma',
            }
        },
        y: {
            type: 'linear',
            display: true,
            position: 'left',
            ticks: {
                callback: function(value, index, values) {
                    return value + '°C';
                }
            }
        },
        // y1: {
        //     type: 'linear',
        //     display: true,
        //     position: 'right',
        // }
    },
    plugins: {
        title: {
            display: true,
            text: '#'+vend.value.code + ' (' + vend.value.full_name + ')'
        },
        tooltip: {
            callbacks: {
                label: function(context) {
                    var label = context.dataset.label.slice(0,2) || '';
                    if (label) {
                        label += ': ';
                    }
                    if (context.parsed.y !== null) {
                        label += context.parsed.y + '°C';
                    }
                    return label;
                }
            }
        }
    }
  })
  const forceRerender = () => {
  componentKey.value += 1;
  };

  onBeforeMount(() => {
    getVendTempsData()
  })

  watch(types, async (newTypes, oldTypes) => {
    router.visit(
        route('temp', {
            id: vend.value.id,
            type: props.type.value,
            types: newTypes,
            ...filters.value,
        }),{
            only: ['vendTempsObj'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onSuccess: (page) => {
                router.reload({
                    only: ['vendTempsObj'],
                    preserveState: true,
                    preserveScroll: true,
                })
                getVendTempsData()
            },
        }
    );
  })

  watch(fans, async (newFans, oldFans) => {
    router.visit(
        route('temp', {
            id: vend.value.id,
            type: props.type.value,
            types: types.value,
            fans: newFans,
            ...filters.value,
        }),{
            only: ['vendTempsObj'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onSuccess: (page) => {
                router.reload({
                    only: ['vendTempsObj'],
                    preserveState: true,
                    preserveScroll: true,
                })
                getVendTempsData()
            },
        }
    );
  })

  function onCustomDatetimeSearched() {
    // syncTimeLatest.value = false
    router.get(
        '/vends/' +
        vend.value.id +
        '/temp/' +
        props.type.value
    , {...filters.value, types: types.value, fans: fans.value}, {
        preserveScroll: true,
    })
  }

  function onDurationFilterClicked(duration, durationType) {
    // syncTimeLatest.value = false
    // Inertia.get(
    //     '/vends/' +
    //     vend.value.id +
    //     '/temp/' +
    //     props.type.value
    // , {
    //     ...filters.value,
    //     types: types.value,
    //     duration: duration,
    //     durationType: durationType
    // }, {
    //     preserveScroll: true,
    // })
    // forceRerender()

    router.get('/vends/' + vend.value.id + '/temp/'+ props.type.value +'?duration=' + duration + '&durationType=' + durationType)
  }

  function back() {
    window.history.back();
  }

  function getVendTempsData() {
    let colors = ['#E6676B', '#36a2eb', '#cc65fe', '#ffce56']
    let vendTempsAllArr = JSON.parse(JSON.stringify(props.vendTempsObj.data))
    let vendFansAllArr = JSON.parse(JSON.stringify(props.vendFansObj.data))
    let vendTempsArr = []
    let vendFansArr = []
    let lastTempValue = []

    if(types.value.length > 0 || fans.value.length > 0) {
        types.value.forEach((type, typeIndex) => {
            let vendTempsDataByType = vendTempsAllArr.filter((vendTemp) => {
                return vendTemp.type == type;
            })

            vendTempsArr[type] = vendTempsDataByType
            let processList = []
            for(let i = 0; i < vendTempsArr[type].length; i++) {
                if(i > 0 && Math.abs(moment(vendTempsArr[type][i].created_at).diff(moment(vendTempsArr[type][i-1].created_at), 'minutes')) > 5) {
                    processList.push({
                        past: vendTempsArr[type][i-1],
                        current: vendTempsArr[type][i]
                    })
                }
            }
            lastTempValue[type] = vendTempsArr[type][vendTempsArr[type].length - 1].value
            if(processList.length) {
                processList.forEach((value, index) => {
                    let tempTimer = moment(value.past.created_at).add(5, 'minutes')
                    do {
                        vendTempsArr[type].push({
                            value: 'NaN',
                            created_at: tempTimer.format(),
                            type: type,
                        })
                        tempTimer = tempTimer.add(5, 'minutes')
                    }while (moment(value.current.created_at).diff(tempTimer, 'minutes')> 5)
                })
            }

            if(vendTempsArr[type][vendTempsDataByType.length - 1] && moment().diff(moment(vendTempsArr[type][vendTempsArr[type].length - 1].created_at), 'minutes') > 10 ) {
                let addNullTempSetting = {
                    unit: 'hours',
                    qty: 2,
                }
                let startTimer = moment(vendTempsArr[type][0].created_at)
                let endTimer = moment(vendTempsArr[type][vendTempsArr[type].length - 1].created_at)
                let timerDiffMinutes = endTimer.diff(startTimer, 'minutes')

                if(timerDiffMinutes <= 60) {
                    addNullTempSetting = {
                        unit: 'hours',
                        qty: 2,
                    }
                }else if(timerDiffMinutes > 60 && timerDiffMinutes <= 360) {
                    addNullTempSetting = {
                        unit: 'hours',
                        qty: 3,
                    }
                }else if(timerDiffMinutes > 360 && timerDiffMinutes <= 1440) {
                    addNullTempSetting = {
                        unit: 'hours',
                        qty: 4,
                    }
                }else {
                    addNullTempSetting = {
                        unit: 'hours',
                        qty: 6,
                    }
                }

                let finalTimer = moment(vendTempsArr[type][vendTempsArr[type].length - 1].created_at).add(addNullTempSetting.qty, addNullTempSetting.unit)
                if(moment().diff(finalTimer, 'minutes') < 10) {
                    addNullTempSetting = {
                        unit: 'hours',
                        qty: 2,
                    }
                }

                let tempTimer = moment(vendTempsArr[type][vendTempsArr[type].length - 1].created_at).add(5, 'minutes')

                do {
                    vendTempsArr[type].push({
                        value: 'NaN',
                        created_at: tempTimer.format(),
                        type: type,
                    })
                    tempTimer = tempTimer.add(5, 'minutes')
                }while (finalTimer.diff(tempTimer, 'minutes') > 5)
            }
            vendTempsArr[type].sort((a,b) => moment(a.created_at).unix() - moment(b.created_at).unix())
        })
        vendTemps.value = vendTempsArr

        fans.value.forEach((type, typeIndex) => {
            let vendFansDataByType = vendFansAllArr.filter((vendFan) => {
                return vendFan.type == type;
            })

            vendFansArr[type] = vendFansDataByType
            let processList = []
            for(let i = 0; i < vendFansArr[type].length; i++) {
                if(i > 0 && Math.abs(moment(vendFansArr[type][i].created_at).diff(moment(vendFansArr[type][i-1].created_at), 'minutes')) > 5) {
                    processList.push({
                        past: vendFansArr[type][i-1],
                        current: vendFansArr[type][i]
                    })
                }
            }
            if(processList.length) {
                processList.forEach((value, index) => {
                    let fanTimer = moment(value.past.created_at).add(5, 'minutes')
                    do {
                        vendFansArr[type].push({
                            value: 'NaN',
                            created_at: fanTimer.format(),
                            type: type,
                        })
                        fanTimer = fanTimer.add(5, 'minutes')
                    }while (moment(value.current.created_at).diff(fanTimer, 'minutes')> 5)
                })
            }

            if(vendFansArr[type][vendFansDataByType.length - 1] && moment().diff(moment(vendFansArr[type][vendFansArr[type].length - 1].created_at), 'minutes') > 10 ) {
                let addNullTempSetting = {
                    unit: 'hours',
                    qty: 2,
                }
                let startTimer = moment(vendFansArr[type][0].created_at)
                let endTimer = moment(vendFansArr[type][vendFansArr[type].length - 1].created_at)
                let timerDiffMinutes = endTimer.diff(startTimer, 'minutes')

                if(timerDiffMinutes <= 60) {
                    addNullTempSetting = {
                        unit: 'hours',
                        qty: 2,
                    }
                }else if(timerDiffMinutes > 60 && timerDiffMinutes <= 360) {
                    addNullTempSetting = {
                        unit: 'hours',
                        qty: 3,
                    }
                }else if(timerDiffMinutes > 360 && timerDiffMinutes <= 1440) {
                    addNullTempSetting = {
                        unit: 'hours',
                        qty: 4,
                    }
                }else {
                    addNullTempSetting = {
                        unit: 'hours',
                        qty: 6,
                    }
                }

                let finalTimer = moment(vendFansArr[type][vendFansArr[type].length - 1].created_at).add(addNullTempSetting.qty, addNullTempSetting.unit)
                if(moment().diff(finalTimer, 'minutes') < 10) {
                    addNullTempSetting = {
                        unit: 'hours',
                        qty: 2,
                    }
                }

                let fanTimer = moment(vendFansArr[type][vendFansArr[type].length - 1].created_at).add(5, 'minutes')

                do {
                    vendFansArr[type].push({
                        value: 'NaN',
                        created_at: fanTimer.format(),
                        type: type,
                    })
                    fanTimer = fanTimer.add(5, 'minutes')
                }while (finalTimer.diff(fanTimer, 'minutes') > 5)
            }
            vendFansArr[type].sort((a,b) => moment(a.created_at).unix() - moment(b.created_at).unix())
        })
        vendFans.value = vendFansArr

        if(vendTemps.value.length > 0) {
            let allTimings = []
            datasets.value = []
            vendTemps.value.forEach((vendTemp, vendTempIndex) => {
                 datasets.value.push({
                    label: 'T' + vendTempIndex + (lastTempValue[vendTempIndex] ? (' (' + lastTempValue[vendTempIndex] + "\u2103" + ')' ) : ''),
                    data: vendTemp.map((temp) => {return {x: temp.created_at, y: temp.value}}),
                    borderColor: colors[vendTempIndex - 1],
                    backgroundColor: colors[vendTempIndex -1],
                    tension: 0.1,
                    spanGaps: true,
                    yAxisID: 'y',
                    // datalabels: {
                    //     color: '#000000',
                    //     display: function(value, context) {
                    //         return value.dataIndex === vendTemp.length - 1;
                    //     },
                    //     anchor: 'start',
                    //     align: '240',
                    //     offset: 13,
                    //     formatter: function(value, context) {
                    //         return 'T' + context.dataset.label.charAt(1) + '= ' + value.y;
                    //     },
                    //     font: {
                    //         size: 14,
                    //     },
                    // },
                })
                allTimings.push(vendTemp)
            })
        }
        if(vendFans.value.length > 0) {
            let allTimings = []
            datasets.value = []
            vendFans.value.forEach((vendFan, vendFanIndex) => {
                datasets.value.push({
                    label: 'Fan' + vendFanIndex,
                    data: vendFan.map((fan) => {return {x: fan.created_at, y: fan.value}}),
                    borderColor: colors[vendFanIndex - 1],
                    backgroundColor: colors[vendFanIndex -1],
                    tension: 0.1,
                    spanGaps: true,
                    yAxisID: 'y1',
                })
                allTimings.push(vendFans)
            })
        }

        forceRerender()
    }
  }

function onExportExcelClicked() {
    // window.open('/vends/transactions/excel', '_blank');
    loading.value = true

    axios({
        method: 'get',
        url: '/vends/' + vend.value.id + '/temp/' + props.type.value + '/excel',
        params: {
            ...filters.value,
            types: types.value,
        },
        responseType: 'blob',
    }).then(response => {
        fileDownload(response.data, 'Vending_Temp_' + moment().format('YYMMDDhhmmss') +'.xlsx')
        loading.value = false
    })
}

  </script>