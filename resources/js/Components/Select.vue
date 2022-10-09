<template>
    <div>
        <label for="text" class="block text-sm font-medium text-gray-700">
            <slot></slot>
        </label>
        <Select2 class="mt-1" :input="modelValue" :options="options" :settings="{ allowClear: true }"
            @select="onSelected"/>
    </div>
</template>

<script setup>
import Select2 from 'v-select2-component';

const emit = defineEmits(['update:modelValue'])

defineProps({
    modelValue: String,
    options: Object
})

function onSelected() {
    emit('update:modelValue', $event.target.value)
}
</script>