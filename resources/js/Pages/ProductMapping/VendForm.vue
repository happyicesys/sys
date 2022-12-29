<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600" v-if="props.productMapping">
            Product Mapping for
          </span>
          <span v-if="props.productMapping">
            {{ props.productMapping.name }}
          </span>
          <span class="text-gray-600" v-else>
            Create New Product Mapping
          </span>
        </div>
      </template>
      <template #default>
        <div class="overflow-hidden bg-white shadow sm:rounded-lg">
          <div class="border-t border-gray-200 px-4 py-5 sm:p-0">
            <dl class="sm:divide-y sm:divide-gray-200">
              <div class="py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:py-5 sm:px-6">
                <dt class="text-sm font-medium text-gray-500">
                  Name
                </dt>
                <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0">
                  {{ form.name }}
                </dd>
              </div>
              <div class="py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:py-5 sm:px-6">
                <dt class="text-sm font-medium text-gray-500">
                  Remarks
                </dt>
                <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0">
                  {{ form.remarks }}
                </dd>
              </div>
              <div class="py-4 sm:grid sm:grid-cols-3 sm:gap-4 sm:py-5 sm:px-6">
                <dt class="text-sm font-medium text-gray-500">Channel - Product</dt>
                <dd class="mt-1 text-sm text-gray-900 sm:col-span-2 sm:mt-0">
                  <ul role="list" class="divide-y divide-gray-200 rounded-md border border-gray-200">
                    <li v-for="productMappingItem in productMapping.productMappingItemsJson" class="flex items-center justify-between py-3 pl-3 pr-4 text-sm">
                      <div class="flex w-0 flex-1 space-x-2 items-center">
                        <span class="text-blue-700 text-md pr-2">
                          {{ productMappingItem['channel_code'] }}
                        </span>
                        <span v-if="productMappingItem['product']['code']">
                          {{ productMappingItem['product']['code'] }}
                        </span>
                        <span>
                          - {{ productMappingItem['product']['name'] }}
                        </span>
                      </div>
                    </li>
                  </ul>
                </dd>
              </div>
            </dl>
          </div>
        </div>
        <form @submit.prevent="submit" id="submit">
          <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">
            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3" v-if="form.id">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center">
                  <span class="px-3 bg-white text-lg font-medium text-gray-900"> Vending Machine Binding </span>
                </div>
              </div>
            </div>

            <div class="sm:col-span-5" v-if="form.id">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Vending Machine
              </label>
              <MultiSelect
                v-model="form.vend_id"
                :options="unbindedVendOptions"
                trackBy="id"
                valueProp="id"
                label="full_name"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.vend_id">
                {{ form.errors.vend_id }}
              </div>
            </div>

            <div class="sm:col-span-1" v-if="form.id">
              <Button
              type="button"
              @click="bindProductMappingItem()"
              class="bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6"
              :class="[!form.vend_id ? 'opacity-50 cursor-not-allowed' : '']"
              :disabled="!form.vend_id"
              >
                <PlusCircleIcon class="w-4 h-4"></PlusCircleIcon>
                <span>
                  Add
                </span>
              </Button>
            </div>

            <div class="sm:col-span-6 flex flex-col mt-3" v-if="form.id">
            <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5">
              <div class="inline-block min-w-full py-2 align-middle md:px-4 lg:px-6">
                <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                  <table class="min-w-full divide-y divide-gray-300">
                    <thead class="bg-gray-50">
                      <tr>
                        <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                          #
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                          Vend ID
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                          Vend Name
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                          Action
                        </th>
                      </tr>
                    </thead>
                    <tbody class="bg-white">
                      <tr v-for="(productMappingVend, productMappingVendIndex) in productMappingVends" :key="productMappingVend.id" :class="productMappingVendIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                          {{ productMappingVendIndex + 1 }}
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                          {{ productMappingVend.code }}
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left">
                          <span v-if="productMappingVend.latestVendBinding && productMappingVend.latestVendBinding.customer">
                            {{ productMappingVend.latestVendBinding.customer.code }} <br>
                            {{ productMappingVend.latestVendBinding.customer.name }}
                          </span>
                          <span v-else>
                            {{ productMappingVend.name }}
                          </span>
                        </td>
                        <td class="whitespace-nowrap py-4 text-sm text-center">
                          <Button
                            class="bg-red-400 hover:bg-red-500 text-white"
                            @click="unbindProductMappingItem(productMappingVend)"
                          >
                            <BackspaceIcon class="w-4 h-4"></BackspaceIcon>
                          </Button>
                        </td>
                      </tr>
                      <tr v-if="!productMappingVends.length">
                        <td colspan="5" class="whitespace-nowrap py-4 text-sm font-medium text-gray-600 text-center">
                          No Records Found
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
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
import Modal from '@/Components/Modal.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { ArrowUturnLeftIcon, BackspaceIcon, CheckCircleIcon, PlusCircleIcon } from '@heroicons/vue/20/solid';
import { useForm } from '@inertiajs/inertia-vue3';
import { ref, onMounted } from 'vue'

const props = defineProps({
  productMapping: Object,
  type: String,
  showModal: Boolean,
  unbindedVends: Object,
})

const emit = defineEmits(['modalClose'])

const form = ref(
  useForm(getDefaultForm())
)
const unbindedVendOptions = ref([])
const productMappingVends = ref([])

onMounted(() => {
  form.value = props.productMapping ? useForm(props.productMapping) : useForm(getDefaultForm())
  unbindedVendOptions.value = props.unbindedVends.data
  productMappingVends.value = JSON.parse(JSON.stringify(props.productMapping.vends))
})

function getDefaultForm() {
  return {
    id: '',
    name: '',
    remarks: '',
    vend_id: '',
  }
}

function submit() {
  form.value.clearErrors()

  if(props.type === 'update') {
    form.value
      .transform((data) => ({
        ...data,
        productMappingVends: productMappingVends.value,
      }))
      .post('/product-mappings/' + form.value.id + '/update/vends', {
      onSuccess: () => {
        emit('modalClose')
      },
      preserveState: true,
      replace: true,
    })
  }
}

function bindProductMappingItem() {
  if(productMappingVends.value.indexOf(form.value.vend_id) < 0) {
    productMappingVends.value.push(form.value.vend_id)
    productMappingVends.value.sort((a, b) => a.code - b.code)
    unbindedVendOptions.value.splice(unbindedVendOptions.value.indexOf(form.value.vend_id), 1)
    unbindedVendOptions.value.sort((a, b) => a.code - b.code)
  }
}

function unbindProductMappingItem(vend) {
  productMappingVends.value.splice(productMappingVends.value.indexOf(vend), 1)
  unbindedVendOptions.value.push(vend)
  unbindedVendOptions.value.sort((a, b) => a.code - b.code)
}

</script>