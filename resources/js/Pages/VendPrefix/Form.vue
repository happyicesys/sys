<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600" v-if="props.vendPrefix">
            Editing
          </span>
          <span v-if="props.vendPrefix">
            {{ props.vendPrefix.name }}
          </span>
          <span class="text-gray-600" v-else>
            Create New Vend Prefix
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
            <div class="sm:col-span-6">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Product Mapping
                <span class="text-red-500">
                   *
                </span>
              </label>
              <MultiSelect
                v-model="form.productMappings"
                :options="productMappingOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="top"
                mode="tags"
                class="mt-1"
                max="4"
              >
              </MultiSelect>
              <span>
                <small class="text-xs text-blue-800">
                  (Maximum 4 options)
                </small>
              </span>
              <div class="text-sm text-red-600" v-if="form.errors.productMappings">
                {{ form.errors.productMappings }}
              </div>
            </div>
            <div class="sm:col-span-6">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Upcoming Product Mapping(s)
              </label>
              <MultiSelect
                v-model="form.upcomingProductMappings"
                :options="upcomingOptions()"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="top"
                mode="tags"
                class="mt-1"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.upcomingProductMappings">
                {{ form.errors.upcomingProductMappings }}
              </div>
            </div>
            <div class="sm:col-span-6">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Operator
                <span class="text-red-500">
                   *
                </span>
              </label>
              <MultiSelect
                v-model="form.operator_id"
                :options="operatorOptions"
                trackBy="id"
                valueProp="id"
                label="full_name"
                placeholder="Select"
                open-direction="top"
                class="mt-1"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.operator_id">
                {{ form.errors.operator_id }}
              </div>
            </div>
            <!-- <div class="sm:col-span-6">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Setting Chart
              </label>
              <MultiSelect
                v-model="form.vend_config_id"
                :options="vendConfigOptions"
                trackBy="id"
                valueProp="id"
                label="name"
                placeholder="Select"
                open-direction="top"
                class="mt-1"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.vend_config_id">
                {{ form.errors.vend_config_id }}
              </div>
            </div> -->
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
import MultiSelect from '@/Components/MultiSelect.vue'
import { ArrowUturnLeftIcon, CheckCircleIcon } from '@heroicons/vue/20/solid';
import { useForm, usePage } from '@inertiajs/vue3';
import { ref, onMounted, watch } from 'vue'
import { useToast } from "vue-toastification";

const authOperator = usePage().props.auth.operator

const props = defineProps({
  operatorOptions: [Array, Object],
  productMappingOptions: [Array, Object],
  vendConfigOptions: [Array, Object],
  vendPrefix: Object,
  type: String,
  showModal: Boolean,
})

const emit = defineEmits(['modalClose'])

const form = ref(
  useForm(getDefaultForm())
)
const operatorOptions = ref([])
const productMappingOptions = ref([])
const vendConfigOptions = ref([])
const toast = useToast()

onMounted(() => {
  operatorOptions.value = [
    ...props.operatorOptions.data.map((data) => {return {id: data.id, full_name: data.full_name}})
  ]
  productMappingOptions.value = props.productMappingOptions.data.map((data) => {
    return { id: data.id, value: data.name }
  })
  vendConfigOptions.value = props.vendConfigOptions
  form.value = props.vendPrefix
    ? useForm({
        ...props.vendPrefix,
        productMappings: normalizeCollection(props.vendPrefix.productMappings)
          .map((productMapping) => {
            return (
              findProductMappingOption(productMapping.id) ||
              buildOptionFromMapping(productMapping)
            )
          })
          .filter(Boolean),
        operator_id: props.vendPrefix.operator_id
          ? operatorOptions.value.find((operator) => operator.id == props.vendPrefix.operator_id)
          : operatorOptions.value.find((operator) => operator.id == authOperator.id),
        upcomingProductMappings: buildInitialUpcomingSelections(
          props.vendPrefix?.productMappings,
          props.vendPrefix?.upcomingProductMappingsUnique
        ),
      })
    : useForm(getDefaultForm())
  ensureUpcomingValid()
})

watch(
  () => form.value.productMappings,
  () => {
    ensureUpcomingValid()
  },
  { deep: true }
)

function normalizeCollection(collection) {
  if (Array.isArray(collection)) {
    return collection
  }
  if (collection && Array.isArray(collection.data)) {
    return collection.data
  }
  return []
}

function normalizeId(id) {
  if (id === null || typeof id === 'undefined') {
    return null
  }
  return String(id)
}

function buildOptionFromMapping(mapping) {
  const id = normalizeId(mapping?.id)
  if (!id) {
    return null
  }

  return {
    id,
    value: mapping?.value || mapping?.name || '',
  }
}

function findProductMappingOption(id) {
  const normalizedId = normalizeId(id)
  if (!normalizedId) {
    return null
  }

  return productMappingOptions.value.find(
    (option) => normalizeId(option.id) === normalizedId
  )
}

function buildInitialUpcomingSelections(productMappings = [], uniqueUpcoming = []) {
  const selections = []
  const seen = new Set()

  const normalizedUpcoming = normalizeCollection(uniqueUpcoming)
  const normalizedProductMappings = normalizeCollection(productMappings)

  const upcomingSource = normalizedUpcoming.length
    ? normalizedUpcoming
    : normalizedProductMappings.flatMap((productMapping) =>
        normalizeCollection(productMapping?.upcomingProductMappings)
      )

  for (const upcoming of upcomingSource) {
    const normalizedId = normalizeId(upcoming?.id)
    if (!normalizedId || seen.has(normalizedId)) {
      continue
    }

    const option =
      findProductMappingOption(normalizedId) ||
      buildOptionFromMapping(upcoming)

    if (option) {
      seen.add(normalizedId)
      selections.push(option)
    }
  }

  return selections
}

function upcomingOptions() {
  return productMappingOptions.value
}

function ensureUpcomingValid() {
  if (!Array.isArray(form.value.upcomingProductMappings)) {
    form.value.upcomingProductMappings = []
    return
  }

  const seen = new Set()

  form.value.upcomingProductMappings = form.value.upcomingProductMappings.filter((upcoming) => {
    const id = normalizeId(upcoming?.id)
    if (!id || seen.has(id)) {
      return false
    }
    seen.add(id)
    return true
  })
}

function getDefaultForm() {
  return {
    name: '',
    desc: '',
    operator_id: '',
    productMappings: [],
    upcomingProductMappings: [],
  }
}

function submit() {
  form.value.clearErrors()

  if(props.type === 'create') {
    form.value
    .transform((data) => {
      return {
        ...data,
        operator_id: data.operator_id.id,
        productMappings: data.productMappings.map(productMapping => productMapping.id),
        upcomingProductMappings: (data.upcomingProductMappings || []).map(productMapping => productMapping.id),
      }
    })
    .post('/vend-prefixes/create', {
      onSuccess: () => {
        toast.success("Machine prefix created successfully", { timeout: 3000 })
        emit('modalClose')
      },
      onError: () => {
        toast.error("Failed to create vend prefix", { timeout: 3000 })
      },
      preserveState: true,
      replace: true,
    })
  }

  if(props.type === 'update') {
    form.value
      .transform((data) => {
        return {
          ...data,
          operator_id: data.operator_id.id,
          productMappings: data.productMappings.map(productMapping => productMapping.id),
          upcomingProductMappings: (data.upcomingProductMappings || []).map(productMapping => productMapping.id),
        }
      })
      .post('/vend-prefixes/' + form.value.id + '/update', {
      onSuccess: () => {
        toast.success("Machine prefix updated successfully", { timeout: 3000 })
        emit('modalClose')
      },
      onError: () => {
        toast.error("Failed to update vend prefix", { timeout: 3000 })
      },
      preserveState: true,
      replace: true,
    })
  }
}

</script>
