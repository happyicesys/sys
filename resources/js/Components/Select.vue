<template>
    <div>
        <label for="text" class="block text-sm font-medium text-gray-700">
            <slot></slot>
        </label>
        <Select2 class="mt-1" :input="modelValue" :options="options" :settings="{ allowClear: true }"
            @select="$emit('update:modelValue', $event.target.value)" />
    </div>
</template>
<script>
import Select2 from 'v-select2-component';

export default {
    // declare Select2Component
    components: { Select2 },
    data() {
        return {
            myOptions: ['op1', 'op2', 'op3'] // or [{id: key, text: value}, {id: key, text: value}]
        }
    },
    props: {
        modelValue: String,
        options: Object
    }
}
</script>