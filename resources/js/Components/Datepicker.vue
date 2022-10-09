<template #dp-input="value">
  <div>
    <label for="text" class="block text-sm font-medium text-gray-700">
        <slot></slot>
    </label>
    <div class="mt-1">
      <Datepicker
        :modelValue="modelValue"
        @update:modelValue="onSelected"
        format="yyyy-MM-dd"
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
  import moment from 'moment';
  import { ref } from 'vue';

  const emit = defineEmits(['update:modelValue'])

  defineProps({
    modelValue: [Date, String],
    minDate: [Date, String],
    maxDate: [Date, String],
    enableTimePicker: {
      type: Boolean,
      default: false,
    },
  })

  function onSelected(modelData) {
    emit('update:modelValue', moment(modelData).format('Y-M-D'))
  }
</script>
