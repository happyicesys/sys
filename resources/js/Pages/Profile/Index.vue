
<template>

    <Head title="Profiles" />

    <BreezeAuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Profiles
            </h2>
        </template>

        <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
            <div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
            <div class="flex justify-end">
                <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-5 py-3 md:px-4 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                @click="onCreateProfileClicked()"
                v-if="can.create"
                >
                    <PlusIcon class="h-4 w-4" aria-hidden="true"/>
                    <span>
                        Create
                    </span>
                </Button>
            </div>
            <!-- <div class="flex flex-col md:flex-row md:space-x-3 space-y-1 md:space-y-0"> -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
                <SearchInput placeholderStr="Name" v-model="filters.name">
                    Name
                </SearchInput>
                <SearchInput placeholderStr="UEN" v-model="filters.uen">
                    UEN
                </SearchInput>
            </div>

            <div class="flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5">
                <div class="mt-3">
                    <div class="flex space-x-1">
                        <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        @click="onSearchFilterUpdated()"
                        >
                            <MagnifyingGlassIcon class="h-4 w-4" aria-hidden="true"/>
                            <span>
                                Search
                            </span>
                        </Button>
                        <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                        @click="resetFilters()"
                        >
                            <BackspaceIcon class="h-4 w-4" aria-hidden="true"/>
                            <span>
                                Reset
                            </span>
                        </Button>
                    </div>
                </div>
                <div class="flex flex-col space-y-2">
                    <p class="text-sm text-gray-700 leading-5 flex space-x-1">
                        <span>Showing</span>
                        <span class="font-medium">{{ profiles.meta.from ?? 0 }}</span>
                        <span>to</span>
                        <span class="font-medium">{{ profiles.meta.to ?? 0 }}</span>
                        <span>of</span>
                        <span class="font-medium">{{ profiles.meta.total }}</span>
                        <span>results</span>
                    </p>
                    <MultiSelect
                        v-model="filters.numberPerPage"
                        :options="numberPerPageOptions"
                        trackBy="id"
                        valueProp="id"
                        label="value"
                        placeholder="Select"
                        open-direction="bottom"
                        class="mt-1"
                        @selected="onSearchFilterUpdated"
                    >
                    </MultiSelect>
                </div>
            </div>
        </div>

         <div class="mt-6 flex flex-col">
         <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
            <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll">
                <table class="min-w-full border-separate" style="border-spacing: 0">
                    <thead class="bg-gray-100">
                        <tr class="divide-x divide-gray-200">
                            <TableHead>
                                #
                            </TableHead>
                            <TableHeadSort modelName="name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('name')">
                                Name
                            </TableHeadSort>
                            <TableHead>
                                Alias
                            </TableHead>
                            <TableHead>
                                UEN
                            </TableHead>
                            <TableHead>
                                Address
                            </TableHead>
                            <TableHead>
                            </TableHead>
                        </tr>
                    </thead>
                    <tbody class="bg-white">
                        <tr v-for="(profile, profileIndex) in profiles.data" :key="profile.id"
                            class="divide-x divide-gray-200">
                            <TableData :currentIndex="profileIndex" :totalLength="profiles.length" inputClass="text-center">
                                {{ profiles.meta.from + profileIndex }}
                            </TableData>
                            <TableData :currentIndex="profileIndex" :totalLength="profiles.length" inputClass="text-left">
                                {{ profile.name }}
                            </TableData>
                            <TableData :currentIndex="profileIndex" :totalLength="profiles.length" inputClass="text-center">
                                {{ profile.alias }}
                            </TableData>
                            <TableData :currentIndex="profileIndex" :totalLength="profiles.length" inputClass="text-center">
                                {{ profile.uen }}
                            </TableData>
                            <TableData :currentIndex="profileIndex" :totalLength="profiles.length" inputClass="text-left md:max-w-xs">
                                {{ profile.address.full_address }}
                            </TableData>
                            <TableData :currentIndex="profileIndex" :totalLength="profiles.length" inputClass="text-center">
                                <div class="flex justify-center space-x-1">
                                    <Button
                                        type="button" class="bg-gray-300 hover:bg-gray-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
                                        @click="onEditProfileClicked(profile)"
                                    >
                                        <PencilSquareIcon class="w-4 h-4"></PencilSquareIcon>
                                        <span>
                                            Edit
                                        </span>
                                    </Button>
                                    <Button
                                        type="button" class="bg-red-300 hover:bg-red-400 px-3 py-2 text-xs text-red-800 flex space-x-1"
                                        @click="onDeleteProfileClicked(profile)"
                                    >
                                        <TrashIcon class="w-4 h-4"></TrashIcon>
                                        <span>
                                            Delete
                                        </span>
                                    </Button>
                                </div>
                            </TableData>
                        </tr>
                        <tr v-if="!profiles.data.length">
                            <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                                No Results Found
                            </td>
                        </tr>
                    </tbody>
                </table>
                <Paginator v-if="profiles.data.length" :links="profiles.links" :meta="profiles.meta"></Paginator>
            </div>
        </div>
        </div>
    </div>
    <Form
        v-if="showEditModal"
        :profile="profile"
        :countries="props.countries"
        :type="type"
        :showModal="showEditModal"
        @modalClose="onModalClose"
    >
    </Form>
    </BreezeAuthenticatedLayout>
  </template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import Form from '@/Pages/Profile/Form.vue';
import Paginator from '@/Components/Paginator.vue';
import SearchInput from '@/Components/SearchInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { BackspaceIcon, MagnifyingGlassIcon, PencilSquareIcon, PlusIcon, TrashIcon } from '@heroicons/vue/20/solid';
import TableHead from '@/Components/TableHead.vue';
import TableData from '@/Components/TableData.vue';
import TableHeadSort from '@/Components/TableHeadSort.vue';
import { ref, onMounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';

const props = defineProps({
    profiles: Object,
    countries: Object,
    can: Object,
})

const filters = ref({
    name: '',
    uen: '',
    sortKey: '',
    sortBy: true,
    numberPerPage: 100,
})
const showEditModal = ref(false)
const profile = ref()
const type = ref('')
const numberPerPageOptions = ref([])

onMounted(() => {
    numberPerPageOptions.value = [
        { id: 100, value: 100 },
        { id: 200, value: 200 },
        { id: 500, value: 500 },
        { id: 'All', value: 'All' },
    ]
    filters.value.numberPerPage = numberPerPageOptions.value[0]
})

function onCreateProfileClicked() {
    type.value = 'create'
    profile.value = null
    showEditModal.value = true
}

function onDeleteProfileClicked(profile) {
    const approval = confirm('Are you sure to delete ' + profile.name + '?');
    if (!approval) {
        return;
    }
    router.delete('/profiles/' + profile.id)
}

function onEditProfileClicked(profileValue) {
    type.value = 'update'
    profile.value = profileValue
    showEditModal.value = true
}

function onSearchFilterUpdated() {
    router.get('/profiles', {
        ...filters.value,
        numberPerPage: filters.value.numberPerPage.id,
    }, {
        preserveState: true,
        replace: true,
    })
}

function resetFilters() {
    router.get('/profiles')
}

function sortTable(sortKey) {
    filters.value.sortKey = sortKey
    filters.value.sortBy = !filters.value.sortBy
    onSearchFilterUpdated()
}

function onModalClose() {
    showEditModal.value = false
}

</script>