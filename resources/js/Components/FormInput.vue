<template>
  <div>
      <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
          <slot></slot>
          <span v-if="required" class="text-red-500">
            *
          </span>
      </label>
      <div class="mt-1">
          <input :type="inputType"
            class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md"
            :class="[disabled ? 'bg-gray-200 hover:cursor-not-allowed' : '']"
            :placeholder="placeholderStr" @input="$emit('update:modelValue', $event.target.value)"
            :value="modelValue"
            :disabled="disabled"
            :autocomplete="autocomplete"
            :max="inputType === 'number' ? maxValue : null"
          />
          <div class="text-sm text-red-600" v-if="error">
            {{ error }}
          </div>
      </div>
  </div>
</template>

<script setup>

defineProps({
  autocomplete: {
    type: String,
    default: 'on',
  },
  placeholderStr: {
    type: [Array, String]
  },
  maxValue: {
    type: [String, Number],
    default: null,
  },
  modelValue: [String, Number],
  error: String,
  required: {
    type: [Boolean, String],
    default: false,
  },
  inputType: {
    type: String,
    default: 'text',
  },
  disabled: [Boolean, Object, String, Number],
})
</script>