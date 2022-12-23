<template #dp-input="value">
  <div>
    <label for="text" class="block text-sm font-medium text-gray-700">
        <slot></slot>
    </label>
    <div class="mt-1 flex rounded-md shadow-sm">
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
      <button type="button" class="border border-gray-300 bg-gray-50 px-2 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
        @click="onPreviousDateClicked()"
      >
        <span>
          <ChevronDoubleLeftIcon class="h-4 w-4" aria-hidden="true"/>
        </span>
      </button>
      <button type="button" class="rounded-r-md border border-gray-300 bg-gray-50 px-2 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500"
      @click="onNextDateClicked()"
      >
        <span>
          <ChevronDoubleRightIcon class="h-4 w-4" aria-hidden="true"/>
        </span>
      </button>
    </div>
  </div>
</template>

<script setup>
  import Datepicker from '@vuepic/vue-datepicker';
  import { ChevronDoubleLeftIcon, ChevronDoubleRightIcon } from '@heroicons/vue/20/solid';
  import '@vuepic/vue-datepicker/dist/main.css'
  import moment from 'moment';
  import { ref } from 'vue';

  const emit = defineEmits(['update:modelValue'])

  const props = defineProps({
    modelValue: [Date, String],
    minDate: [Date, String],
    maxDate: [Date, String],
    enableTimePicker: {
      type: Boolean,
      default: false,
    },
  })

  function onPreviousDateClicked() {
    emit('update:modelValue', moment(props.modelValue).subtract(1, 'days').format('Y-M-D'))
  }

  function onNextDateClicked() {
    emit('update:modelValue', moment(props.modelValue).add(1, 'days').format('Y-M-D'))
  }

  function onSelected(modelData) {
    emit('update:modelValue', moment(modelData).format('Y-M-D'))
  }
</script>
