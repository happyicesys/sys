<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600" v-if="props.telco">
            Editing
          </span>
          <span v-if="props.telco">
            {{ props.telco.name }}
          </span>
          <span class="text-gray-600" v-else>
            Create New SimCard Package
          </span>
        </div>
      </template>
      <template #default>
        <form @submit.prevent="submit" id="submit">
          <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">
            <div class="sm:col-span-6">
              <FormInput v-model="form.name" :error="form.errors.name" required="true">
                Name
              </FormInput>
            </div>
            <div class="sm:col-span-6">
              <FormTextarea v-model="form.desc" :error="form.errors.desc">
                Desc
              </FormTextarea>
            </div>
            <!-- Colour: tints this package's badge on the Operation
                 Dashboard so ops can tell packages apart at a glance. Five
                 tints only - green / grey / red are reserved for machine
                 status on that page. See constants/telcoColors.js. -->
            <div class="sm:col-span-6">
              <label class="block text-sm font-medium text-gray-700">
                Color (Operation Dashboard badge)
              </label>
              <div class="mt-1 flex flex-wrap gap-2">
                <button
                  type="button"
                  class="inline-flex items-center space-x-2 rounded-md px-3 py-2 text-xs"
                  :class="form.color === null ? 'ring-2 ring-offset-1 ring-indigo-500' : ''"
                  :style="telcoBadgeStyle(null)"
                  @click="form.color = null"
                >
                  <span>Default</span>
                </button>
                <button
                  v-for="option in colorChoices"
                  :key="option.id"
                  type="button"
                  class="inline-flex items-center space-x-2 rounded-md px-3 py-2 text-xs"
                  :class="form.color === option.id ? 'ring-2 ring-offset-1 ring-indigo-500' : ''"
                  :style="telcoBadgeStyle(option.id)"
                  @click="form.color = option.id"
                >
                  <span>{{ option.name }}</span>
                </button>
              </div>
              <div class="text-sm text-red-600" v-if="form.errors.color">
                {{ form.errors.color }}
              </div>
            </div>
            <!-- Usage API: which provider simcards:sync-usage polls for this
                 package's Status column. Blank = no live status. -->
            <div class="sm:col-span-6">
              <label class="block text-sm font-medium text-gray-700">
                Usage API
              </label>
              <MultiSelect
                v-model="form.usage_provider"
                :options="usageProviderOptions"
                trackBy="id"
                valueProp="id"
                label="name"
                placeholder="None (no live status)"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.usage_provider">
                {{ form.errors.usage_provider }}
              </div>
            </div>
            <div class="sm:col-span-6" v-if="form.usage_provider">
              <FormInput
                v-model="form.usage_endpoint"
                :error="form.errors.usage_endpoint"
                :placeholderStr="selectedProviderEndpoint"
              >
                API query link
              </FormInput>
              <p class="mt-1 text-xs text-gray-500">
                Leave blank to use the provider default ({{ selectedProviderEndpoint }}).
              </p>
            </div>
          </div>
          <div class="sm:col-span-6">
            <div class="flex space-x-1 mt-5 justify-end">
              <Button
                class="bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1"
                @click="$emit('modalClose')"
                form="submit"
              >
                <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
                <span>
                  Back
                </span>
              </Button>
              <Button type="submit" class="bg-green-500 hover:bg-green-600 text-white flex space-x-1">
                <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                <span>
                  Save
                </span>
              </Button>
            </div>
          </div>
        </form>
      </template>
    </Modal>
  </Teleport>
</template>

<script setup>
import Button from '@/Components/Button.vue';
import FormInput from '@/Components/FormInput.vue';
import FormTextarea from '@/Components/FormTextarea.vue';
import Modal from '@/Components/Modal.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { ArrowUturnLeftIcon, CheckCircleIcon } from '@heroicons/vue/20/solid';
import { TELCO_COLORS, telcoBadgeStyle } from '@/constants/telcoColors';
import { useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue'
import { useToast } from "vue-toastification";

const props = defineProps({
  telco: Object,
  type: String,
  showModal: Boolean,
  usageProviderOptions: {
    type: Array,
    default: () => [],
  },
  // Telco::COLORS - the keys the backend will accept. The swatches come from
  // constants/telcoColors.js; anything the backend no longer offers is
  // dropped here so the form can never post a rejected value.
  colorOptions: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['modalClose'])

const form = ref(
  useForm(getDefaultForm())
)
const toast = useToast()

const colorChoices = computed(() => props.colorOptions.length
  ? TELCO_COLORS.filter(option => props.colorOptions.includes(option.id))
  : TELCO_COLORS
)

const selectedProviderEndpoint = computed(() => {
  const option = props.usageProviderOptions.find(o => o.id === form.value.usage_provider)
  return option ? option.endpoint : ''
})

onMounted(() => {
  form.value = props.telco ? useForm({ ...getDefaultForm(), ...props.telco }) : useForm(getDefaultForm())
})

function getDefaultForm() {
  return {
    name: '',
    desc: '',
    color: null,
    usage_provider: null,
    usage_endpoint: '',
  }
}

function submit() {
  form.value.clearErrors()

  if(props.type === 'create') {
    form.value
    .post('/telcos/create', {
      onSuccess: () => {
        toast.success("SimCard Package created successfully", { timeout: 3000 })
        emit('modalClose')
      },
      onError: () => {
        toast.error("Failed to create SimCard Package", { timeout: 3000 })
      },
      preserveState: true,
      replace: true,
    })
  }

  if(props.type === 'update') {
    form.value
      .post('/telcos/' + form.value.id + '/update', {
      onSuccess: () => {
        toast.success("SimCard Package updated successfully", { timeout: 3000 })
        emit('modalClose')
      },
      onError: () => {
        toast.error("Failed to update SimCard Package", { timeout: 3000 })
      },
      preserveState: true,
      replace: true,
    })
  }
}

</script>