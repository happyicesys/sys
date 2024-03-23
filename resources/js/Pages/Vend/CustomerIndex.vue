
<template>
    <Head title="Vending Machines - Customers" />

    <BreezeAuthenticatedLayout>
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Vending Machines (Customers)
            </h2>
        </template>

        <IndexComponent
            :categories="categories"
            :categoryGroups="categoryGroups"
            :constTempError="constTempError"
            :locationTypeOptions="locationTypeOptions"
            :operatorOptions="operatorOptions"
            :productOptions="productOptions"
            :totals="totals"
            :vends="vends"
            :vendChannelErrors="vendChannelErrors"
        >
        </IndexComponent>
    </BreezeAuthenticatedLayout>
  </template>

  <script setup>
  import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
  import { Head } from '@inertiajs/vue3';
  import IndexComponent from '@/Pages/Vend/IndexComponent.vue';

  const props = defineProps({
    categories: Object,
    categoryGroups: Object,
    constTempError: Number,
    locationTypeOptions: Object,
    operatorOptions: Object,
    productOptions: Object,
    totals: [Array, Object],
    vends: Object,
    vendChannelErrors: Object,
  })

  </script>