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

<script>
  import Datepicker from '@vuepic/vue-datepicker';
  import '@vuepic/vue-datepicker/dist/main.css'

  export default {
      components: { Datepicker },
      props: {
        modelValue: Date,
        minDate: String,
        maxDate: String,
        enableTimePicker: {
          type: Boolean,
          default: true,
        },
      },
      emit: ['update:modelValue'],
      methods: {
        onSelected(modelData) {
          this.$emit('update:modelValue', modelData)
        }
      }
  }
</script>
