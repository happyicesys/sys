
<template>

    <Head title="Vending Machine" />

    <BreezeAuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Vending Machine
            </h2>
        </template>

        <!-- <div class="py-5"> -->
        <!-- <div class="max-w-10xl mx-auto sm:px-6 lg:px-8"> -->

        <div class="p-4 sm:px-6 lg:px-8">
            <div class="sm:flex sm:items-center">
                <section class="sm:flex sm:space-x-5 space-y-2 sm:space-y-0">
                    <SearchInput placeholderStr="Code" v-model="searchFilters.code" @input="onSearchFilterUpdated()">
                        Code
                    </SearchInput>
                    <SearchInput placeholderStr="Serial Num" v-model="searchFilters.serial_num"
                        @input="onSearchFilterUpdated()">
                        Serial Num
                    </SearchInput>
                    <SearchInput placeholderStr="Name" v-model="searchFilters.name" @input="onSearchFilterUpdated()">
                        Name
                    </SearchInput>
                    <Button type="button" @click.prevent="onVendingMachineChannelErrorLogEmailClicked()">
                        Send Vending Machine Channel Error Log Mail
                    </Button>
                </section>
            </div>
            <div class="px-2 mt-8 flex flex-col">
                <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
                    <div class="inline-block min-w-full py-2 align-middle">
                        <div class="shadow-sm ring-1 ring-black ring-opacity-5">
                            <table class="min-w-full border-separate" style="border-spacing: 0">
                                <thead class="bg-gray-100">
                                    <tr class="divide-x divide-gray-200">
                                        <th scope="col"
                                            class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                                            #</th>
                                        <th scope="col"
                                            class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                                            Code</th>
                                        <th scope="col"
                                            class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                                            Serial Num</th>
                                        <th scope="col"
                                            class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                                            Name</th>
                                        <th scope="col"
                                            class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                                            Temp(C)</th>
                                        <th scope="col"
                                            class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                                            Last Temp</th>
                                        <th scope="col"
                                            class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                                            Coin Amount($)</th>
                                        <th scope="col"
                                            class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-4 pr-3 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter sm:pl-6 lg:pl-8">
                                            Firmware Ver</th>
                                        <th scope="col"
                                            class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 px-3 py-3.5 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter">
                                            Door Opening?</th>
                                        <th scope="col"
                                            class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 px-3 py-3.5 text-center text-sm font-semibold text-gray-900 backdrop-blur backdrop-filter">
                                            Sensor Normal?</th>
                                        <th scope="col"
                                            class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pr-4 pl-3 backdrop-blur backdrop-filter sm:pr-6 lg:pr-8">
                                            <span class="sr-only">Edit</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <tr v-for="(vendingMachine, vendingMachineIndex) in vendingMachines.data"
                                        :key="vendingMachine.id" class="divide-x divide-gray-200">
                                        <td :class="[vendingMachineIndex !== vendingMachines.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-600 sm:pl-6 lg:pl-8']"
                                            class="text-right">
                                            {{ vendingMachines.meta.from + vendingMachineIndex }}
                                        </td>
                                        <td :class="[vendingMachineIndex !== vendingMachines.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-800 sm:pl-6 lg:pl-8']"
                                            class="text-right">
                                            {{ vendingMachine.code }}
                                        </td>
                                        <td :class="[vendingMachineIndex !== vendingMachines.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-800 sm:pl-6 lg:pl-8']"
                                            class="text-center">
                                            {{ vendingMachine.serial_num }}</td>
                                        <td :class="[vendingMachineIndex !== vendingMachines.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-800 sm:pl-6 lg:pl-8']"
                                            class="text-center">
                                            {{ vendingMachine.name }}</td>
                                        <td :class="[vendingMachineIndex !== vendingMachines.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-blue-600 sm:pl-6 lg:pl-8']"
                                            class="text-right flex flex-col">
                                            <span class="hover:cursor-pointer"
                                                @click="onVendingMachineTempClicked(vendingMachine.id)">
                                                {{ vendingMachine.temp }}
                                            </span>
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800"
                                                v-if="vendingMachine.is_temp_error">
                                                Abnormal</span>
                                        </td>
                                        <td :class="[vendingMachineIndex !== vendingMachines.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-600 sm:pl-6 lg:pl-8']"
                                            class="text-center">
                                            {{ vendingMachine.temp_updated_at }}</td>
                                        <td :class="[vendingMachineIndex !== vendingMachines.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-600 sm:pl-6 lg:pl-8']"
                                            class="text-right">
                                            {{ vendingMachine.coin_amount }}</td>
                                        <td :class="[vendingMachineIndex !== vendingMachines.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-600 sm:pl-6 lg:pl-8']"
                                            class="text-center">
                                            {{ vendingMachine.firmware_ver }}</td>
                                        <td :class="[vendingMachineIndex !== vendingMachines.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-600 sm:pl-6 lg:pl-8']"
                                            class="text-center">
                                            {{ vendingMachine.is_door_open }}</td>
                                        <td :class="[vendingMachineIndex !== vendingMachines.length - 1 ? 'border-b border-gray-200' : '', 'whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-600 sm:pl-6 lg:pl-8']"
                                            class="text-center">
                                            {{ vendingMachine.is_sensor_normal }}</td>
                                        <td
                                            :class="[vendingMachineIndex !== vendingMachines.length - 1 ? 'border-b border-gray-200' : '', 'relative whitespace-nowrap py-4 pr-4 pl-3 text-right text-sm font-medium sm:pr-6 lg:pr-8']">
                                            <a href="#" class="text-indigo-600 hover:text-indigo-900">Edit<span
                                                    class="sr-only">, {{ vendingMachine.name }}</span></a>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                            <Paginator :links="vendingMachines.links" :meta="vendingMachines.meta"></Paginator>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- </div> -->
        <!-- </div> -->
    </BreezeAuthenticatedLayout>
</template>

<script>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import { Head } from '@inertiajs/inertia-vue3';
import { Link } from '@inertiajs/inertia-vue3';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';

export default {
    components: {
        BreezeAuthenticatedLayout,
        Button,
        Head,
        Link,
        Paginator,
        SearchInput,
    },
    props: {
        vendingMachines: Object,
        filters: Object,
    },
    data() {
        return {
            searchFilters: {
                code: this.filters.code,
                serial_num: this.filters.serial_num,
                name: this.filters.name,
            }
        }
    },
    methods: {
        onSearchFilterUpdated() {
            this.$inertia.get('/vending-machine', {
                code: this.searchFilters.code,
                serial_num: this.searchFilters.serial_num,
                name: this.searchFilters.name,
            }, {
                preserveState: true,
                replace: true,
            })
        },
        onVendingMachineTempClicked(vendingMachineId) {
            this.$inertia.get('/vending-machine/' + vendingMachineId + '/temp')
        },
        onVendingMachineChannelErrorLogEmailClicked() {
            this.$inertia.get('/vending-machines/channel-error-logs-email')
        }
    },
}
</script>