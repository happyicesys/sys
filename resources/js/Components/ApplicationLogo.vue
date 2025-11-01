<template>
    <div class="h-fit w-fit">
        <img :class="[useContainLogo ? 'object-contain h-28 w-52 p-3' : 'object-cover h-28 w-52']" :src="logoUrl" alt="Company Logo">
    </div>
</template>

<script setup>
    import { usePage } from '@inertiajs/vue3';
    import { computed } from 'vue';

    const page = usePage()
    const logoUrl = computed(() => page.props.logoUrl)
    const defaultLogoUrl = computed(() => page.props.defaultLogoUrl)
    const useContainLogo = computed(() => logoUrl.value !== defaultLogoUrl.value)
</script>
