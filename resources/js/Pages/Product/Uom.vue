<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600">
            Bind New Uom
          </span>
        </div>
      </template>
      <template #default>
        <form @submit.prevent="submit" id="submit">
          <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">
            <div class="sm:col-span-6">
              <div class="flex space-x-1">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  UOM
                </label>
                <span class="text-red-500">
                  *
                </span>
              </div>
              <MultiSelect
                v-model="form.uom_id"
                :options="uomOptions"
                trackBy="id"
                valueProp="id"
                label="name"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.uom_id">
                {{ form.errors.uom_id }}
              </div>
            </div>
            <div class="sm:col-span-6">
              <FormInput v-model="form.value" :error="form.errors.value" required="true" :disabled="form.is_base_uom">
                Value (equals how many Base UOM)
              </FormInput>
            </div>
            <div class="sm:col-span-6 pt-2">
              <div class="flex flex-col space-y-3 md:flex-row md:space-y-0 md:space-x-4">
                <div class="relative flex items-start">
                  <div class="flex h-5 items-center">
                    <input id="candidates" v-model="form.is_base_uom" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-75" @click="onBaseUomClicked"/>
                  </div>
                  <div class="ml-3 text-sm">
                    <label for="candidates" class="font-medium text-gray-700">Is Base UOM?</label>
                  </div>
                </div>

                <div class="relative flex items-start">
                  <div class="flex h-5 items-center">
                    <input id="candidates" v-model="form.is_transaction_uom" type="checkbox" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 disabled:opacity-75" />
                  </div>
                  <div class="ml-3 text-sm">
                    <label for="candidates" class="font-medium text-gray-700">Is Transacted UOM?</label>
                  </div>
                </div>
              </div>
            </div>
          </div>
          <div class="sm:col-span-6">
            <div class="flex space-x-1 mt-5 justify-end">
              <Button
                class="bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1"
                @click.prevent="$emit('modalClose')"
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
import MultiSelect from '@/Components/MultiSelect.vue'
import Modal from '@/Components/Modal.vue';
import { ArrowUturnLeftIcon, CheckCircleIcon } from '@heroicons/vue/20/solid';
import { useForm } from '@inertiajs/inertia-vue3';
import { ref, onMounted } from 'vue'

const props = defineProps({
  product: Object,
  uoms: Object,
  showModal: Boolean,
})

const emit = defineEmits(['modalClose'])

const form = ref(
  useForm(getDefaultForm())
)
const uomOptions = ref([])
const productUomId = ref([])

onMounted(() => {
  form.value = props.productUom ? useForm(props.productUom) : useForm(getDefaultForm())
  uomOptions.value = props.uoms.data.map((uom) => {return {id: uom.id, name: uom.name}})

  productUomId.value = props.product.productUoms.map((productUom) => productUom.uom.id)
  uomOptions.value = uomOptions.value.filter((uom) => {
    return !productUomId.value.includes(uom.id)
  })
  // console.log(JSON.parse(JSON.stringify()))
})

function getDefaultForm() {
  return {
    uom_id: '',
    value: '',
    is_base_uom: false,
    is_transaction_uom: false,
  }
}

function submit() {
  form.value.clearErrors()

    form.value
    .transform((data) => ({
      ...data,
      uom_id: data.uom_id.id,
    }))
    .post('/products/' + props.product.id + '/uom-binding', {
    onSuccess: () => {
      emit('modalClose')
    },
      preserveState: true,
      resetOnSuccess: true,
      replace: true,
    })
}

function onBaseUomClicked() {
  form.value.value = 1
}

</script>