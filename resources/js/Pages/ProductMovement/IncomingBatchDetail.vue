<template>
    <Head title="Incoming Batch Detail" />

    <BreezeAuthenticatedLayout>
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <div class="mb-6 flex justify-between items-center">
                <Link :href="route('product-movements.incoming-history')" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <ArrowLeftIcon class="h-5 w-5 mr-2 text-gray-500" />
                    Back to History
                </Link>
                <div class="w-32"></div>
            </div>

            <!-- Metadata Card -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-8">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg leading-2 font-medium text-gray-900">
                        Batch Details
                    </h3>
                </div>
                <div class="px-4 py-5 sm:p-6">
                    <dl class="grid grid-cols-1 gap-x-4 gap-y-8 sm:grid-cols-2">
                        <div class="sm:col-span-1">
                            <dt class="text-sm font-medium text-gray-500">Batch Number</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ metadata.batch_number }}</dd>
                        </div>
                        <div class="sm:col-span-1">
                            <dt class="text-sm font-medium text-gray-500">Input Date</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ formatDate(metadata.created_at) }}</dd>
                        </div>
                        <div class="sm:col-span-1">
                            <dt class="text-sm font-medium text-gray-500">Input Time</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ formatTime(metadata.created_at) }}</dd>
                        </div>
                        <div class="sm:col-span-1">
                            <dt class="text-sm font-medium text-gray-500">Input By</dt>
                             <dd class="mt-1 text-sm text-gray-900">{{ metadata.user ? metadata.user.name : (metadata.operator ? metadata.operator.name : '-') }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-sm font-medium text-gray-500">Remarks</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ metadata.remarks || '-' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Attachments -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-8">
                <div class="px-4 py-5 sm:px-6 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">Attachments</h3>
                    <label class="inline-flex cursor-pointer items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50"
                        :class="{ 'pointer-events-none opacity-50': uploadForm.processing }">
                        <PaperClipIcon class="mr-2 h-4 w-4 text-gray-500" />
                        {{ uploadForm.processing ? 'Uploading...' : 'Add files' }}
                        <input type="file" multiple class="hidden" :accept="accept" @change="upload">
                    </label>
                </div>
                <div class="px-4 py-4 sm:px-6">
                    <p class="text-xs text-gray-500 mb-3">PDF, image, Excel or Word, up to {{ attachmentLimits.max_mb }} MB each, {{ attachmentLimits.max_files }} files per upload.</p>
                    <InputError :message="uploadError" class="mb-3" />
                    <ul v-if="attachments.length" class="divide-y divide-gray-200">
                        <li v-for="file in attachments" :key="file.id" class="flex items-center justify-between py-2">
                            <a :href="file.full_url" target="_blank" rel="noopener" class="inline-flex min-w-0 items-center text-sm text-indigo-600 hover:text-indigo-900">
                                <PaperClipIcon class="mr-2 h-4 w-4 shrink-0" />
                                <span class="truncate">{{ file.name }}</span>
                            </a>
                            <div class="ml-4 flex shrink-0 items-center gap-4 text-xs text-gray-500">
                                <span>{{ file.user ? file.user.name : '-' }} · {{ moment(file.created_at).format('YYYY-MM-DD hh:mm A') }}</span>
                                <button type="button" class="text-red-600 hover:text-red-800" @click="remove(file)">Delete</button>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-gray-500">No attachments.</p>
                </div>
            </div>

            <!-- Items Table -->
            <div class="bg-white shadow overflow-hidden sm:rounded-lg mb-8">
                  <div class="px-4 py-5 sm:px-6 border-b border-gray-200 bg-gray-50">
                    <h3 class="text-lg leading-6 font-medium text-gray-900">
                        Stocked Items
                    </h3>
                </div>
                 <div class="overflow-x-auto">
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
                            <tr v-for="(movement, index) in movements" :key="movement.id" :class="index % 2 === 0 ? 'bg-white' : 'bg-gray-50'">
                                <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">{{ index + 1 }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-500 justify-center flex">
                                     <img class="h-12 w-12 rounded-lg object-contain bg-white border border-gray-200 p-0.5" :src="movement.product.thumbnail.full_url" alt="" v-if="movement.product && movement.product.thumbnail" />
                                     <div v-else class="h-12 w-12 rounded-full bg-gray-100 flex items-center justify-center text-gray-400 text-xs text-center border border-gray-200">No Img</div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-900 text-center font-bold">{{ movement.product ? movement.product.code : '-' }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-700 font-medium">{{ movement.product ? movement.product.name : '-' }}</td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-center font-bold" :class="movement.qty < 0 ? 'text-red-600' : 'text-gray-900'">{{ movement.qty }}</td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-gray-100 border-t-2 border-gray-300">
                            <tr>
                                <td colspan="4" class="px-3 py-4 text-sm font-bold text-gray-900 text-right uppercase tracking-wide">
                                    Total
                                    <span class="ml-2 font-medium normal-case text-gray-500">({{ movements.length }} {{ movements.length === 1 ? 'product' : 'products' }})</span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-base font-bold text-center" :class="totalQty < 0 ? 'text-red-600' : 'text-gray-900'">{{ totalQty > 0 ? '+' + totalQty : totalQty }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </BreezeAuthenticatedLayout>
</template>

<script setup>
import { computed } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue'
import InputError from '@/Components/InputError.vue'
import { ArrowLeftIcon, PaperClipIcon } from '@heroicons/vue/24/solid'
import moment from 'moment'

const props = defineProps({
    movements: Array,
    metadata: Object,
    attachments: Array,
    attachmentLimits: Object,
})

const accept = computed(() => props.attachmentLimits.mimes.split(',').map(ext => '.' + ext).join(','))

const uploadForm = useForm({
    batch_number: props.metadata.batch_number,
    attachments: [],
})

const upload = (event) => {
    uploadForm.attachments = Array.from(event.target.files).slice(0, props.attachmentLimits.max_files)
    event.target.value = ''
    if (!uploadForm.attachments.length) return
    uploadForm.post(route('product-movements.incoming-attachments.store'), {
        preserveScroll: true,
        onSuccess: () => uploadForm.reset('attachments'),
    })
}

const uploadError = computed(() => Object.entries(uploadForm.errors)
    .filter(([key]) => key === 'attachments' || key.startsWith('attachments.'))
    .map(([, message]) => message)
    .join(' '))

const remove = (file) => {
    if (!confirm(`Delete ${file.name}?`)) return
    router.delete(route('product-movements.incoming-attachments.destroy', file.id), { preserveScroll: true })
}

const totalQty = computed(() => props.movements.reduce((sum, m) => sum + (Number(m.qty) || 0), 0))

const formatDate = (date) => {
    return moment(date).format('YYYY-MM-DD')
}

const formatTime = (date) => {
    return moment(date).format('hh:mm A')
}
</script>
