<template>
  <div class="overflow-visible">
    <Multiselect
      class="custom-multiselect"
      :modelValue="internalValue"
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
  </div>
</template>

<script setup>
import Multiselect from '@vueform/multiselect';
import { ref, watch } from 'vue';

const emit = defineEmits(['update:modelValue', 'selected']);

const props = defineProps({
  canClear: [Boolean, String],
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
  modelValue: {
    type: [Array, Boolean, Object, String, Number],
    default: () => ([]),
  },
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
    default: 'multiselect',
  },
  max: {
    type: [Number, String],
    default: -1,
  },
});

const internalValue = ref(Array.isArray(props.modelValue) ? [...props.modelValue] : props.modelValue);

watch(() => props.modelValue, (newValue) => {
  internalValue.value = Array.isArray(newValue) ? [...newValue] : newValue;
});

function onSelected(data) {
  if (props.mode === 'tags') {
    if (!internalValue.value) {
      internalValue.value = [];
    }
    if (!internalValue.value.find(el => el.id === data.id)) {
      internalValue.value.push(data);
      emit('update:modelValue', internalValue.value);
    }
  } else {
    emit('update:modelValue', data);
  }
  emit('selected', data);
}

function onDeselected(data) {
  if (props.mode === 'tags') {
    internalValue.value = internalValue.value.filter(el => el.id !== data.id);
    emit('update:modelValue', internalValue.value);
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
  z-index: 100;
}
.multiselect__content-wrapper {
  z-index: 100;
  position: static;
}
.custom-multiselect .multiselect__content-wrapper {
  z-index: 100; /* Ensure this is higher than the modal's z-index */
}
</style>
