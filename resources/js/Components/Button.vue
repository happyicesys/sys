<script setup>
defineProps({
    type: {
        type: String,
        default: 'submit',
    },
    class: {
        type: String,
    }
});
</script>

<template>
    <button :type="type"
        class="inline-flex items-center px-2 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm"
        :class="class">
        <slot />
    </button>
</template>


