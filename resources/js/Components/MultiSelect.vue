<template>
  <div class="overflow-visible">
    <Multiselect
      :modelValue="modelValue"
      :canClear="canClear"
      :canDeselect="false"
      :disabled="disabled"
      :label="label"
      :mode="mode"
      :object="true"
      :options="options"
      :placeholder="placeholder"
      :required="required"
      :searchable="true"
      :valueProp="valueProp"
      @select="onSelected"
      @deselect="onDeselected"
      :clear="clear"
      @refreshOptions="refreshOptions"
      :clearOnBlur="clearOnBlur"
      :openDirection="openDirection"
      :ref="ref"
      :max="max"
    />
    <!-- @select="$emit('update:modelValue', $event)" -->
  </div>
</template>

<script setup>
  import Multiselect from '@vueform/multiselect'
  import { ref } from 'vue';

  const emit = defineEmits(['update:modelValue', 'selected']);

  // const modelValue = ref(props.mode == 'tags' ? [] : '')
  const dataArr = ref([])

  const props = defineProps({
    canClear: Boolean,
    clear: Boolean,
    clearOnBlur: {
      type: [Boolean, String],
      default: true,
    },
    disabled: [Array, Boolean, Object, String, Number],
    openDirection: {
      type: String,
      default: 'bottom',
    },
    label: String,
    mode: String,
    modelValue: [Array, Boolean, Object, String, Number],
    options: [Array, Object, String],
    placeholder: String,
    refreshOptions: Boolean,
    trackBy: String,
    valueProp: String,
    required: {
      type: [Boolean, String],
      default: false,
    },
    ref: {
      type: String,
      default: 'multiselect'
    },
    max: {
      type: Number,
      default: -1
    }
  })

  function onSelected(data) {
    if(props.mode === 'tags') {
      dataArr.value.push(data)
      emit('update:modelValue', dataArr.value)
    }else {
      emit('update:modelValue', data)
    }
    emit('selected')
  }

  function onDeselected(data) {
    if(props.mode === 'tags') {
      dataArr.value = dataArr.value.filter(el => {
        return el.id != data.id
      })
      emit('update:modelValue', dataArr.value)
    }
  }
</script>

<style src="@vueform/multiselect/themes/default.css"></style>
<style>
  .multiselect {
    width: 100% !important;
    overflow: visible;
  }
  .multiselect-tags {
    overflow-x: scroll;
  }
  .multiselect--active {
    z-index: 20;
  }
  .multiselect__content-wrapper {
    z-index: 20;
    position: static;
  }
</style>