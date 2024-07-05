<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2 text-gray-800">
          <span class="text-gray-600">
            Set Product Availability
          </span>
        </div>
      </template>
      <template #default>
        <!-- <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
          <SearchInput placeholderStr="Code" v-model="filters.code" @input="onSearchFilterUpdated()">
              Code
          </SearchInput>
          <SearchInput placeholderStr="Name" v-model="filters.name" @input="onSearchFilterUpdated()">
              Name
          </SearchInput>
          <div class="w-1/5">
            <label for="text" class="block text-sm font-medium text-gray-700">
                Is Available?
            </label>
            <MultiSelect
                v-model="filters.is_available"
                :options="isAvailableOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
                @selected="onSearchFilterUpdated()"
            >
            </MultiSelect>
          </div>
        </div> -->
        <div class="flex flex-col">
          <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5">
            <div class="inline-block min-w-full py-2 align-middle md:px-4 lg:px-6">
              <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                <table class="table-fixed min-w-full divide-y divide-gray-300">
                  <thead class="bg-gray-50">
                    <tr>
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        #
                      </th>
                      <th scope="col" class="w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        Image
                      </th>
                      <th scope="col" class="w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        Product
                      </th>
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        Available?
                      </th>
                    </tr>
                  </thead>
                  <tbody class="bg-white">
                    <tr v-for="(product, productIndex) in products.data" :key="product.id" :class="productIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold sm:pl-6 text-center text-gray-900">
                        {{ productIndex + 1 }}
                      </td>
                      <td class="whitespace-nowrap text-sm  font-semibold text-gray-900 text-center">
                        <div class="flex justify-center items-center">
                          <img class="h-16 w-16 rounded-full" :class="[product.is_available ? '' : 'opacity-50']" :src="product.thumbnail.full_url" alt="" v-if="product.thumbnail"/>
                        </div>
                      </td>
                      <td class="py-4 text-sm font-semibold text-left" :class="[product.is_available ? 'text-gray-800' : 'text-gray-400']">
                        <span v-if="product.code">
                          {{ product.code }}
                        </span>
                        <span class="break-normal text-xs" v-if="product.name">
                          <br> {{ product.name }}
                        </span>
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center text-blue-600">
                        <div class="flex flex-col justify-center items-center">
                          <span v-if="product.is_available">
                            <CheckCircleIcon class="h-6 w-6 text-green-500 hover:cursor-pointer hover:text-green-600" @click.prevent="onIsAvailableClicked(product)"/>
                          </span>
                          <span v-else>
                            <XCircleIcon class="h-6 w-6 text-red-500 hover:cursor-pointer hover:text-red-600" @click.prevent="onIsAvailableClicked(product)" />
                          </span>
                          <span class="text-xs text-gray-500">
                            {{ product.isAvailableUpdatedBy ? product.isAvailableUpdatedBy.name : '' }}
                          </span>
                          <span class="text-xs text-gray-500">
                            {{ product.is_available_updated_at }}
                          </span>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </template>
    </Modal>
  </Teleport>
</template>

<script setup>
import { ChevronDoubleDownIcon, ChevronDoubleUpIcon, CheckCircleIcon, PencilSquareIcon, XCircleIcon } from '@heroicons/vue/20/solid';
import Button from '@/Components/Button.vue';
import Modal from '@/Components/Modal.vue';
import { onMounted, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
const props = defineProps({
  products: Object,
  showModal: Boolean,
})

const authUser = usePage().props.auth.user
const operatorCountry = usePage().props.auth.operatorCountry
const filters = ref({
  name: '',
  code: '',
  is_available: '',
})

onMounted(() => {
})
const emit = defineEmits(['modalClose', 'productUpdated'])


function onIsAvailableClicked(product) {
  axios({
      method: 'POST',
      url: '/products/toggle-is-available',
      data: {product_id: product.id},
  }).then(response => {
    emit('productUpdated')
  })
}

// function onSearchFilterUpdated() {
//   router.get('/products', {
//       ...filters.value,
//       is_available: filters.value.is_available.id
//   }, {
//       preserveState: true,
//       replace: true,
//   })
// }

</script>