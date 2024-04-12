<template>
    <div class="h-fit w-fit">
        <img class="object-cover h-28 w-52" :src="logoUrl" alt="Company Logo">
    </div>
</template>

<script setup>
    import { usePage } from '@inertiajs/vue3';

    const logoUrl = usePage().props.logoUrl
    const smallLogoUrl = usePage().props.smallLogoUrl
</script>
