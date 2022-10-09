<template>
  <div>
    <VueMultiselect
      v-model="selected"
      :options="options"
      :allow-empty="allowEmpty"
      :deselect-label="deselectLabel"
      @update:model-value="onSelected()"
      open-direction="bottom"
      :custom-label="customLabel"
      :multiple="multiple"
      :reset-after="resetAfter"
    />
  </div>
</template>

<script setup>
  import VueMultiselect from 'vue-multiselect'
  import { ref } from 'vue'

  const emit = defineEmits(['onSelected'])

  const props = defineProps({
    options: Array,
    allowEmpty: Boolean,
    deselectLabel: String,
    customLabel: Function,
    multiple: Boolean,
  })

  const selected = ref(props.multiple ? null : props.options[0])

  function onSelected() {
    emit('onSelected', selected.value)
  }
</script>

<style src="vue-multiselect/dist/vue-multiselect.css"></style>