<template>
  <Combobox as="div">
    <div class="flex space-x-1">
      <ComboboxLabel class="block text-sm font-medium text-gray-700">
        <slot/>
      </ComboboxLabel>
      <span v-if="required" class="text-red-500">
        *
      </span>
    </div>
    <div class="relative mt-1">
      <ComboboxInput class="w-full rounded-md border border-gray-300 py-2 pl-3 pr-10 shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 sm:text-sm" @input="onInputChanged" :value="modelValue" :disabled="disabled" :class="[disabled ? 'bg-gray-200' : 'bg-white']"/>
      <ComboboxButton class="absolute inset-y-0 right-0 flex items-center rounded-r-md px-2 focus:outline-none">
        <MagnifyingGlassCircleIcon class="h-5 w-5 text-gray-400" aria-hidden="true" />
      </ComboboxButton>

      <ComboboxOptions v-if="options.length > 0" class="absolute z-10 mt-1 max-h-60 w-full overflow-auto rounded-md bg-white py-1 text-base shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none sm:text-sm">
        <ComboboxOption v-for="option in options"  as="template">
          <li class="relative cursor-default select-none py-2 pl-3 pr-9 hover:bg-gray-100" @click="selected(option)">
            <span class="block truncate">
              {{ option.ADDRESS }}
            </span>
          </li>
        </ComboboxOption>
      </ComboboxOptions>
    </div>
  </Combobox>
</template>

<script setup>
import { ref } from 'vue'
import { MagnifyingGlassCircleIcon } from '@heroicons/vue/20/solid'
import {
  Combobox,
  ComboboxButton,
  ComboboxInput,
  ComboboxLabel,
  ComboboxOption,
  ComboboxOptions,
} from '@headlessui/vue'

const props = defineProps({
  disabled: [Boolean, String, Number],
  modelValue: String,
  required: [Boolean, String],
  // When false, the autocomplete API is not called and no suggestion dropdown
  // is shown — the field becomes a plain manual-entry input. Defaults to true
  // so existing usages keep their autocomplete behaviour.
  apiEnabled: {
    type: [Boolean, String, Number],
    default: true,
  },
  // Address-search provider. 'onemap' calls OneMap directly (CORS-open).
  // 'google' calls the backend proxy (/maps/search) which queries the Google
  // Geocoding API server-side and returns results in the same shape.
  provider: {
    type: String,
    default: 'onemap',
  },
})

const emit = defineEmits(['update:modelValue', 'selected'])

const options = ref([])

const fetchAddresses = _.debounce(async (e) => {
  const value = e.target.value
  let url
  if (props.provider === 'google') {
    url = '/maps/search?q=' + encodeURIComponent(value)
  } else {
    url = 'https://www.onemap.gov.sg/api/common/elastic/search?searchVal=' + value + '&returnGeom=Y&getAddrDetails=Y'
  }
  // Fail soft: if the request errors or the provider isn't usable (e.g.
  // MAP_PROVIDER=google but no GOOGLE_MAPS_API_KEY, which returns an empty
  // result set), just show no suggestions instead of throwing.
  try {
    const res = await fetch(url)
    if (!res.ok) {
      options.value = []
      return
    }
    const response = await res.json()
    options.value = response?.results || []
  } catch (err) {
    options.value = []
  }
}, 300)

function onInputChanged(e) {
  emit('update:modelValue', e.target.value)
  if (!props.apiEnabled) {
    options.value = []
    return
  }
  fetchAddresses(e)
}

function selected(option) {
  emit('selected', option);
}


</script>