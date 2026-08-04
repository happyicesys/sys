<template>
  <div>
    <label class="flex justify-start text-sm font-medium text-gray-700">
      <slot></slot>
      <span v-if="required" class="text-red-500">
        *
      </span>
    </label>

    <div class="mt-1 flex flex-col space-y-2">
      <!-- Preset swatches -->
      <div class="flex flex-wrap gap-1.5">
        <button
          v-for="preset in presets"
          :key="preset.hex"
          type="button"
          :title="preset.name"
          :aria-label="preset.name"
          class="h-7 w-7 rounded-full shadow-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1"
          :class="isSelected(preset.hex) ? 'ring-2 ring-offset-1 ring-gray-700' : ''"
          :style="stickerSwatchStyle(preset.hex)"
          @click="select(preset.hex)"
        ></button>
      </div>

      <!-- Custom colour / hex / clear / preview -->
      <div class="flex flex-wrap items-center gap-2">
        <label class="inline-flex items-center space-x-1 text-xs text-gray-600">
          <span>Custom</span>
          <input
            type="color"
            class="h-8 w-10 cursor-pointer rounded border border-gray-300 bg-white p-0.5"
            :value="nativePickerValue"
            @input="select($event.target.value)"
          />
        </label>

        <input
          type="text"
          maxlength="7"
          placeholder="#RRGGBB"
          class="w-28 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm border-gray-300 rounded-md"
          :value="hexText"
          @input="onHexInput($event.target.value)"
          @blur="onHexBlur"
        />

        <button
          v-if="modelValue"
          type="button"
          class="text-xs text-gray-500 underline hover:text-gray-700"
          @click="select(null)"
        >
          Clear
        </button>

        <span
          class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium"
          :style="stickerBadgeStyle(modelValue)"
        >
          {{ previewText || 'Preview' }}
        </span>
      </div>

      <p class="text-xs text-gray-500">
        Leave empty for no colour — the sticker then shows as a plain white badge.
      </p>

      <div class="text-sm text-red-600" v-if="error">
        {{ error }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import {
  STICKER_PRESET_COLORS,
  normalizeHex,
  stickerBadgeStyle,
  stickerSwatchStyle,
} from '@/constants/stickerColors'

const props = defineProps({
  modelValue: {
    type: String,
    default: null,
  },
  error: String,
  required: {
    type: [Boolean, String],
    default: false,
  },
  previewText: {
    type: String,
    default: '',
  },
  presets: {
    type: Array,
    default: () => STICKER_PRESET_COLORS,
  },
})

const emit = defineEmits(['update:modelValue'])

// Local copy so the user can type a partial hex ("#ff") without the parent
// form being fed an invalid value.
const hexText = ref(props.modelValue ?? '')

watch(
  () => props.modelValue,
  (value) => {
    if (normalizeHex(value) !== normalizeHex(hexText.value)) {
      hexText.value = value ?? ''
    }
  }
)

const nativePickerValue = computed(() => normalizeHex(props.modelValue) ?? '#FFFFFF')

function isSelected(hex) {
  return normalizeHex(props.modelValue) === normalizeHex(hex)
}

function select(value) {
  const hex = value === null ? null : normalizeHex(value)
  hexText.value = hex ?? ''
  emit('update:modelValue', hex)
}

function onHexInput(value) {
  hexText.value = value

  if (value.trim() === '') {
    emit('update:modelValue', null)
    return
  }

  const hex = normalizeHex(value)
  if (hex) {
    emit('update:modelValue', hex)
  }
}

// Snap the text box back to the committed value if the user left it invalid.
function onHexBlur() {
  hexText.value = props.modelValue ?? ''
}
</script>
