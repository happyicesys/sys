<template>
  <Head title="Campaign" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Create New Campaign
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <Form
        type="create"
        :operator-options="props.operatorOptions"
        :tag-options="props.tagOptions"
        :promo-type-options="props.promoTypeOptions"
      />
    </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Form from './Form.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
  operatorOptions: Object,
  tagOptions: Object,
  promoTypeOptions: Array,
});
</script>
