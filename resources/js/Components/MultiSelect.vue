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
      :reset-after="resetAfter"
    />
  </div>
</template>

<script>
import VueMultiselect from 'vue-multiselect'
export default {
  components: { VueMultiselect },
  props: {
    options: Array,
    allowEmpty: Boolean,
    deselectLabel: String,
    customLabel: Function,
  },
  data () {
    return {
      selected: this.options[0],
    }
  },
  methods: {
    onSelected() {
      this.$emit('onSelected', this.selected)
    }
  }
}
</script>

<style src="vue-multiselect/dist/vue-multiselect.css"></style>