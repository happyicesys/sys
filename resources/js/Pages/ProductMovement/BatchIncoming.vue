<template>
    <Head title="Incoming Stock Entry" />

    <BreezeAuthenticatedLayout>
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <!-- Action Bar -->
            <div class="mb-6 flex justify-between items-center">
                <Link :href="route('product-movements.index')" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <ArrowLeftIcon class="h-5 w-5 mr-2 text-gray-500" />
                    Back to List
                </Link>
                <h1 class="text-2xl font-bold text-gray-900">Incoming Stock Entry</h1>
                <div class="w-32"></div>
            </div>

            <!-- Batch Details Card -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-8">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        Batch Details
                    </h3>
                    <p class="mt-1 max-w-2xl text-sm text-gray-500">
                        Please fill in the batch information before entering quantities.
                    </p>
                </div>
                <div class="px-4 py-5 sm:p-6">
                    <div class="grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                        <div class="sm:col-span-3">
                            <label for="batch_number" class="block text-sm font-medium text-gray-700">Detailed Incoming Batch Number <span class="text-red-500">*</span></label>
                            <div class="mt-1">
                                <input type="text" name="batch_number" id="batch_number" v-model="form.batch_number" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md" placeholder="e.g. BATCH-2023-001" required>
                            </div>
                            <InputError :message="form.errors.batch_number" class="mt-2" />
                        </div>

                        <div class="sm:col-span-2">
                            <label for="created_at" class="block text-sm font-medium text-gray-700">Date</label>
                            <div class="mt-1">
                                <DatePicker v-model="form.created_at" :isPreviousNextButton="false" :clearable="false" :format="'yyyy-MM-dd'" auto-apply></DatePicker>
                            </div>
                            <InputError :message="form.errors.created_at" class="mt-2" />
                        </div>

                        <div class="sm:col-span-1">
                            <label for="user" class="block text-sm font-medium text-gray-700">Input by</label>
                            <div class="mt-1">
                                <div class="block w-full py-2 px-3 border border-gray-300 bg-gray-100 rounded-md shadow-sm sm:text-sm text-gray-500">
                                    {{ $page.props.auth.user.name }}
                                </div>
                            </div>
                        </div>

                        <div class="sm:col-span-6">
                            <label for="remarks" class="block text-sm font-medium text-gray-700">Remarks</label>
                            <div class="mt-1">
                                <textarea id="remarks" name="remarks" rows="3" v-model="form.remarks" class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md" placeholder="Optional notes..."></textarea>
                            </div>
                            <InputError :message="form.errors.remarks" class="mt-2" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products Table -->
            <div class="flex flex-col">
                 <div class="mb-4 flex justify-between items-center bg-white p-4 shadow sm:rounded-lg">
                    <div class="relative w-full max-w-md">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <MagnifyingGlassIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
                        </div>
                        <input type="text" v-model="searchQuery" class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-10 sm:text-sm border-gray-300 rounded-md" placeholder="Search by Product Name or Code..." />
                    </div>
                     <button type="button" @click="resetSearch" class="ml-3 inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        Reset Filter
                    </button>
                </div>
                <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
                    <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
                        <div class="shadow ring-1 ring-black ring-opacity-5 md:rounded-lg overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-300">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="py-3.5 pl-4 pr-3 text-center text-xs font-medium uppercase tracking-wide text-gray-500 sm:pl-6 w-16">#</th>
                                        <th scope="col" class="px-3 py-3.5 text-center text-xs font-medium uppercase tracking-wide text-gray-500 w-24">Image</th>
                                        <th scope="col" class="px-3 py-3.5 text-center text-xs font-medium uppercase tracking-wide text-gray-500 w-32">Code</th>
                                        <th scope="col" class="px-3 py-3.5 text-left text-xs font-medium uppercase tracking-wide text-gray-500">Product Name</th>
                                        <th scope="col" class="px-3 py-3.5 text-center text-xs font-medium uppercase tracking-wide text-gray-500 w-40">Qty (Pieces)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <tr v-for="item in filteredProducts" :key="item.data.id" :class="[item.index % 2 === 0 ? 'bg-white' : 'bg-gray-50', 'hover:bg-gray-100']">
                                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">{{ item.index + 1 }}</td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 justify-center flex">
                                             <img class="h-12 w-12 rounded-full border border-gray-200" :src="item.data.thumbnail.full_url" alt="" v-if="item.data.thumbnail" />
                                             <div v-else class="h-12 w-12 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 text-xs text-center border border-gray-200">No Img</div>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-900 text-center font-bold">{{ item.data.code }}</td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-700 font-medium">{{ item.data.name }}</td>
                                        <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 text-center">
                                            <input type="number"
                                                v-model="form.products[item.index].qty"
                                                class="shadow-sm focus:ring-green-500 focus:border-green-500 block w-full sm:text-sm border-gray-300 rounded-md text-center font-bold text-gray-900"
                                                :class="{'bg-green-50 border-green-300': form.products[item.index].qty > 0}"
                                                min="0"
                                                placeholder="0">
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sticky Footer for Submit -->
            <div class="fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 p-4 shadow-lg flex justify-end items-center z-50">
                <div class="max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 flex justify-end">
                    <Button class="inline-flex items-center rounded-md border border-transparent bg-green-600 px-8 py-3 text-base font-bold text-white shadow-sm hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 transition-colors duration-200" @click="submit" :disabled="form.processing">
                        <span v-if="form.processing">Saving...</span>
                        <span v-else>Confirm & Save Stock</span>
                    </Button>
                </div>
            </div>
            <div class="h-20"></div> <!-- Spacer for fixed footer -->
        </div>
    </BreezeAuthenticatedLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import DatePicker from '@/Components/DatePicker.vue'
import InputError from '@/Components/InputError.vue'
import Button from '@/Components/Button.vue'
import { ArrowLeftIcon, MagnifyingGlassIcon } from '@heroicons/vue/24/solid'
import moment from 'moment'

const props = defineProps({
    products: Object,
})

const form = useForm({
    batch_number: '',
    remarks: '',
    created_at: moment().format('YYYY-MM-DD'),
    products: props.products.data.map(product => ({
        id: product.id,
        qty: 0,
    })),
})

const searchQuery = ref('')

const filteredProducts = computed(() => {
    if (!searchQuery.value) {
        return props.products.data.map((data, index) => ({ data, index }))
    }
    const lowerQuery = searchQuery.value.toLowerCase()
    return props.products.data
        .map((data, index) => ({ data, index }))
        .filter(item => {
            return (item.data.name && item.data.name.toLowerCase().includes(lowerQuery)) ||
                   (item.data.code && item.data.code.toLowerCase().includes(lowerQuery))
        })
})

const resetSearch = () => {
    searchQuery.value = ''
}

const submit = () => {
    form.post(route('product-movements.batch-store'), {
        preserveScroll: true,
        onSuccess: () => {
            // Optional: Show success message or redirect
        },
    })
}
</script>
