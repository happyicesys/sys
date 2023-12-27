<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600">
            Editing Channel ID
          </span>
          <span>
            {{ deliveryProductMappingItemObj.channel_code }}
          </span>
        </div>
      </template>
      <template #default>
        <form @submit.prevent="submit" id="submit">
          <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6 pb-2">
            <div class="sm:col-span-6">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Product
              </label>
              <div class="mt-1">
                <input
                  type="text"
                  class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                  :value="deliveryProductMappingItemObj.product.full_name"
                  disabled
                />
              </div>
            </div>
            <div class="sm:col-span-6">
              <FormInput v-model="form.amount" :error="form.errors.amount" :disabled="!permissions.includes('update vends')">
                Amount
              </FormInput>
            </div>
            <div class="sm:col-span-6">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Sub Category
              </label>
              <MultiSelect
                v-model="form.sub_category_json"
                :options="subCategoryOptions"
                trackBy="id"
                valueProp="id"
                label="name"
                placeholder="Select"
                open-direction="top"
                class="mt-1"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.sub_category_json">
                {{ form.errors.sub_category_json }}
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
              <Button
                type="submit"
                class="bg-green-500 hover:bg-green-600 text-white flex space-x-1"
                v-if="permissions.includes('update vends')"
              >
                <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                <span>
                  Save
                </span>
              </Button>
              <Button
                  class="flex space-x-1"
                  :class="[deliveryProductMappingItemObj.is_active ? 'bg-yellow-300 hover:bg-yellow-400 text-black' : 'bg-green-500 hover:bg-green-600 text-white']"
                  @click.prevent="togglePauseDeliveryProductMappingItem(deliveryProductMappingItemObj)"
                >
                <PauseCircleIcon class="w-3 h-3" v-if="deliveryProductMappingItemObj.is_active"></PauseCircleIcon>
                <PlayCircleIcon class="w-3 h-3" v-else></PlayCircleIcon>
                <span class="text-xs" v-if="deliveryProductMappingItemObj.is_active">
                  Pause SKU
                </span>
                <span class="text-xs" v-else>
                  Resume SKU
                </span>
              </Button>
              <Button
                class="bg-red-400 hover:bg-red-500 text-white flex space-x-1"
                @click.prevent="unbindDeliveryProductMappingItem(deliveryProductMappingItemObj.id)"
                v-if="roles.includes('superadmin')"
              >
                <BackspaceIcon class="w-3 h-3"></BackspaceIcon>
                <span class="text-xs">
                  Unbind SKU
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
import MultiSelect from '@/Components/MultiSelect.vue'
import FormInput from '@/Components/FormInput.vue';
import Modal from '@/Components/Modal.vue';
import { ArrowUturnLeftIcon, BackspaceIcon, CheckCircleIcon, PauseCircleIcon, PlayCircleIcon } from '@heroicons/vue/20/solid';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue'

const permissions = usePage().props.auth.permissions
const roles = usePage().props.auth.roles
const props = defineProps({
  deliveryProductMapping: Object,
  deliveryProductMappingItemObj: Object,
  showModal: Boolean,
})

const emit = defineEmits(['modalClose'])

const form = ref(
  useForm(getDefaultForm())
)

const subCategoryOptions = ref([])

onMounted(() => {
  subCategoryOptions.value = props.deliveryProductMapping.category_json.subCategories
  form.value = props.deliveryProductMappingItemObj ? useForm(props.deliveryProductMappingItemObj) : useForm(getDefaultForm())
})

function getDefaultForm() {
  return {
    amount: '',
    sub_category_json: '',
  }
}

function submit() {
  form.value.clearErrors()

  form.value
    .post('/delivery-product-mapping-items/' + form.value.id + '/update', {
    onSuccess: () => {
      emit('modalClose')
    },
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

function togglePauseDeliveryProductMappingItem(deliveryProductMappingItem) {
  let approvalText = deliveryProductMappingItem.is_active ? 'Are you sure to pause this SKU?' : 'Are you sure to resume this SKU?'
  const approval = confirm(approvalText);
  if (!approval) {
      return;
  }
  router.post('/delivery-product-mapping-items/' + deliveryProductMappingItem.id + '/toggle-pause', {}, {
      onSuccess: () => {
        emit('modalClose')
      },
      preserveState: false,
      preserveScroll: true,
      replace: true,
  })
}

function unbindDeliveryProductMappingItem(deliveryProductMappingItemId) {
  const approval = confirm('Are you sure to delete this entry?');
  if (!approval) {
      return;
  }
  router.delete('/delivery-product-mapping-items/' + deliveryProductMappingItemId, {
      onSuccess: () => {
        emit('modalClose')
      },
      preserveState: false,
      preserveScroll: true,
      replace: true,
  })
}

function unbindCustomer(vendId) {
  form.value
      .post('/vends/' + vendId + '/unbind', {
        onSuccess: () => {
          emit('modalClose')
        },
        preserveState: true,
        replace: true,
      })
}

function downloadVendSnapshot(vendSnapshotId) {
    axios({
        method: 'get',
        url: '/vends/vend-snapshots/excel/' + vendSnapshotId,
        responseType: 'blob',
    }).then(response => {
        fileDownload(response.data, 'Vending_Channels_' + moment().format('YYMMDDhhmmss') +'.xlsx')
    }).catch(error => {
        console.log(error)
    }).finally(() => {
    })
}

</script>