
<template>
  <Head title="Vending Machines - Devices" />

  <BreezeAuthenticatedLayout>
      <template #header>
          <h2 class="font-semibold text-xl text-gray-800 leading-tight">
              Vending Machines (Machine View)
          </h2>
      </template>

      <IndexComponent
          :categories="categories"
          :categoryGroups="categoryGroups"
          :cmsEndpoint="cmsEndpoint"
          :constTempError="constTempError"
          :deviceTypes="deviceTypes"
          :locationTypeOptions="locationTypeOptions"
          :nextDeliveryDriverOptions="nextDeliveryDriverOptions"
          :operatorOptions="operatorOptions"
          :productOptions="productOptions"
          :totals="totals"
          :indexType="indexType"
          :vends="vends"
          :vendChannelErrors="vendChannelErrors"
          :vendPrefixOptions="vendPrefixOptions"
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
  cmsEndpoint: String,
  constTempError: Number,
  deviceTypes: [Array, Object],
  indexType: String,
  locationTypeOptions: Object,
  nextDeliveryDriverOptions: [Array, Object],
  operatorOptions: Object,
  productOptions: Object,
  totals: [Array, Object],
  vends: Object,
  vendChannelErrors: Object,
  vendPrefixOptions: Object,
})

</script>