<template #dp-input="value">
  <div>
    <label for="text" class="block text-sm font-medium text-gray-700">
        <slot></slot>
    </label>
    <div class="mt-1">
      <Datepicker
        :modelValue="modelValue"
        @update:modelValue="onSelected"
        format="yyyy-MM-dd HH:mm"
        :clearable="false"
        :monthChangeOnScroll="false"
        autoApply
        :closeOnAutoApply="true"
        :minDate="minDate"
        :maxDate="maxDate"
        :enableTimePicker="enableTimePicker"
      >
      </Datepicker>
    </div>
  </div>
</template>

<script setup>
  import Datepicker from '@vuepic/vue-datepicker';
  import '@vuepic/vue-datepicker/dist/main.css'

  const emit = defineEmits(['update:modelValue'])

  defineProps({
    modelValue: [Date, String, Object],
    minDate: [Date, String, Object],
    maxDate: [Date, String, Object],
    enableTimePicker: {
      type: Boolean,
      default: true,
    },
  })

  function onSelected(modelData) {
    emit('update:modelValue', modelData)
  }
</script>
