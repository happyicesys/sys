<template>
  <div>
    <Multiselect
      v-model="value"
      :canClear="canClear"
      :canDeselect="false"
      :label="label"
      :mode="mode"
      :object="true"
      :options="options"
      :placeholder="placeholder"
      :required="true"
      :searchable="true"
      :valueProp="valueProp"
      @select="onSelected"
    />
    <!-- @select="$emit('update:modelValue', $event)" -->
  </div>
</template>

<script>
  import Multiselect from '@vueform/multiselect'

  export default {
    components: {
      Multiselect,
    },
    props: {
      modelValue: Object,
      canClear: Boolean,
      label: String,
      mode: String,
      options: Array,
      placeholder: String,
      trackBy: String,
      valueProp: String,
    },
    data() {
      return {
        value: this.mode == 'tags' ? [] : this.options[0],
      }
    },
    methods: {
      onSelected() {
        if(this.mode == 'tags') {
          this.$emit('update:modelValue', this.value.map((x) => {return x.id}))
        }else {
          this.$emit('update:modelValue', this.value.id)
        }
      }
    },
  }
</script>

<style src="@vueform/multiselect/themes/default.css"></style>
<style>
  .multiselect {
    width: 100% !important;
  }
  .multiselect-tags {
    overflow-x: scroll;
  }
</style>