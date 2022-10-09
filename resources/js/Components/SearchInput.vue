<template>
    <div>
        <label for="text" class="block text-sm font-medium text-gray-700">
            <slot></slot>
        </label>
        <div class="mt-1">
            <input type="text"
                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md"
                :placeholder="placeholderStr" @input="$emit('update:modelValue', $event.target.value)"
                :value="modelValue" />
        </div>
    </div>
</template>

<script setup>

defineProps({
    placeholderStr: {
        type: String,
        default: 'Please fill in'
    },
    modelValue: String
})

</script>