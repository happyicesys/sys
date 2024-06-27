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
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.operator_id">
                {{ form.errors.operator_id }}
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
import { ref, onMounted } from 'vue'

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

onMounted(() => {
  operatorOptions.value = [
    ...props.operatorOptions.data.map((data) => {return {id: data.id, full_name: data.full_name}})
  ]
  productMappingOptions.value = props.productMappingOptions.data.map((data) => {return {id: data.id, value: data.name}})
// console.log(productMappingOptions.value)
  // productMappingOptions.value = [
  //   ...props.productMappingOptions.data.map((data) => {return {id: data.id, name: data.name}})
  // ]
  vendConfigOptions.value = props.vendConfigOptions
  form.value = props.vendPrefix ? useForm(
  {
    ...props.vendPrefix,
    // product_mapping_id: productMappingOptions.value.find(productMapping => productMapping.id === props.vendPrefix.product_mapping_id),
    productMappings: props.vendPrefix.productMappings.map(productMapping => productMappingOptions.value.find(productMappingOption => productMappingOption.id === productMapping.id)),
    operator_id: form.value.operator_id ? operatorOptions.value.find(operator => operator.id === props.vendPrefix.operator_id) : operatorOptions.value.find(operator => operator.id === authOperator.id),
    // vend_config_id: vendConfigOptions.value.find(vendConfig => vendConfig.id === props.vendPrefix.vend_config_id),
  }) : useForm(getDefaultForm())
})

function getDefaultForm() {
  return {
    name: '',
    desc: '',
    operator_id: '',
    productMappings: [],
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
      }
    })
    .post('/vend-prefixes/create', {
      onSuccess: () => {
        emit('modalClose')
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
        }
      })
      .post('/vend-prefixes/' + form.value.id + '/update', {
      onSuccess: () => {
        emit('modalClose')
      },
      preserveState: true,
      replace: true,
    })
  }
}

</script>