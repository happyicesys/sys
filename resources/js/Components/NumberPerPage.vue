<template>
  <div class="flex flex-col space-y-2">
    <p class="text-sm text-gray-700 leading-5 flex space-x-1">
        <span v-for="(part, partIndex) in showingParts" :key="partIndex" :class="{ 'font-medium': part.bold }">{{ part.text }}</span>
    </p>
    <MultiSelect
        v-model="modelValue"
        :options="options"
        trackBy="id"
        valueProp="id"
        label="value"
        :placeholder="$t('Select')"
        open-direction="bottom"
        class="mt-1"
        @change="onSelected"
    >
    </MultiSelect>
  </div>
</template>

<script setup>
  import MultiSelect from '@/Components/MultiSelect.vue';
  import { computed } from 'vue';
  import { t } from '@/i18n';

  const emit = defineEmits(['update:modelValue'])

  const props = defineProps({
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
                { id: 'All', value: t('All') },
            ]
      },
    })

  // "Showing 1 to 10 of 50 results" as ONE translatable sentence, the three numbers still bold.
  const showingParts = computed(() => t('Showing :from to :to of :total results')
      .split(/(:from|:total|:to)/)
      .map((piece) => piece.trim())
      .filter((piece) => piece !== '')
      .map((piece) => (piece === ':from' || piece === ':to' || piece === ':total'
          ? { text: props[piece.slice(1)], bold: true }
          : { text: piece, bold: false })));

  function onSelected(value) {
    emit('update:modelValue', $event.target.value)
  }

</script>