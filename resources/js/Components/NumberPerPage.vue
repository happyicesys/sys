<template>
  <div class="flex flex-col space-y-2">
    <p class="text-sm text-gray-700 leading-5 flex space-x-1">
        <span>Showing</span>
        <span class="font-medium">{{ from }}</span>
        <span>to</span>
        <span class="font-medium">{{ to }}</span>
        <span>of</span>
        <span class="font-medium">{{ total }}</span>
        <span>results</span>
    </p>
    <MultiSelect
        v-model="modelValue"
        :options="options"
        trackBy="id"
        valueProp="id"
        label="value"
        placeholder="Select"
        open-direction="bottom"
        class="mt-1"
        @change="onSelected"
    >
    </MultiSelect>
  </div>
</template>

<script setup>
  import MultiSelect from '@/Components/MultiSelect.vue';

  const emit = defineEmits(['update:modelValue'])

  defineProps({
      modelValue: String,
      from: [Number, String],
      to: [Number, String],
      total: [Number, String],
      options: {
        type: [Array, Object, String],
        default: [
                { id: 100, value: 100 },
                { id: 200, value: 200 },
                { id: 500, value: 500 },
                { id: 'All', value: 'All' },
            ]
      },
    })

  function onSelected(value) {
    emit('update:modelValue', $event.target.value)
  }

</script>