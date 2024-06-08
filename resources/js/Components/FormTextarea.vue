<template>
  <div>
      <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
          <slot></slot>
          <span v-if="required" class="text-red-500">
            *
          </span>
      </label>
      <div class="mt-1">
          <textarea
            class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md"
            :placeholder="placeholderStr" @input="$emit('update:modelValue', $event.target.value)"
            :value="modelValue"
            :rows="rows"
          />
          <div class="text-sm text-red-600" v-if="error">
            {{ error }}
          </div>
      </div>
  </div>
</template>

<script setup>
  defineProps({
    placeholderStr: {
      type: String,
    },
    error: String,
    modelValue: String,
    required: {
      type: [Boolean, String],
      default: false,
    },
    rows: {
      type: [Number, String],
      default: 5
    }
  })
</script>