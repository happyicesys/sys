<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600" v-if="props.vend">
            Editing
          </span>
          <span v-if="props.vend">
            {{ props.vend.code }}
          </span>
        </div>
      </template>
      <template #default>
        <form @submit.prevent="submit" id="submit">
          <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">
            <div class="sm:col-span-4">
              <FormInput v-model="form.name" :error="form.errors.name" required="true">
                Name
              </FormInput>
            </div>
            <div class="sm:col-span-4">
              <FormInput v-model="form.serial_num" :error="form.errors.serial_num">
                Serial Number
              </FormInput>
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
                <!-- @click.prevent="submit" -->
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
import Modal from '@/Components/Modal.vue';
import MultiSelect from '@/Components/MultiSelect.vue'
import { ArrowUturnLeftIcon, CheckCircleIcon } from '@heroicons/vue/20/solid';
import { useForm } from '@inertiajs/inertia-vue3';
import { ref, onMounted } from 'vue'

const props = defineProps({
  vend: Object,
  countries: Object,
  type: String,
  showModal: Boolean,
})

const emit = defineEmits(['modalClose'])

const form = ref(
  useForm(getDefaultForm())
)
const countryOptions = ref([])

onMounted(() => {
  countryOptions.value = props.countries.data
  form.value = props.vend ? useForm(props.vend) : useForm(getDefaultForm())
  if(props.type === 'create') {
    form.value.base_currency_id = form.value.base_currency_id ? form.value.base_currency_id : countryOptions.value[0]
    form.value.address.country_id = form.value.address.country_id ? form.value.address.country_id : countryOptions.value[0]
    form.value.contact.phone_country_id = form.value.contact.phone_country_id ? form.value.contact.phone_country_id : countryOptions.value[0]
  }
})

function getDefaultForm() {
  return {
    alias: '',
    name: '',
    uen: '',
    base_currency_id: '',
    address: {
      block_num: '',
      building: '',
      country_id: '',
      latitude: '',
      longitude: '',
      postcode: '',
      street_name: '',
      unit_num: '',
    },
    contact: {
      name: '',
      email: '',
      phone_country_id: '',
      phone_num: '',
    },
  }
}

function onAddressSelected(address) {
  form.value.address = {
    block_num: address.BLK_NO,
    building: address.BUILDING,
    country_id: countryOptions.value[0],
    latitude: address.LATITUDE,
    longitude: address.LONGTITUDE,
    postcode: address.POSTAL,
    street_name: address.ROAD_NAME,
    unit_num: '',
  }
  // searchAddressForm.value = null
}

function submit() {
  form.value.clearErrors()

  if(props.type === 'create') {
    form.value
    .transform((data) => ({
      ...data,
      base_currency_id: data.base_currency_id.id,
      address: {
        ...data.address,
        country_id: data.address.country_id.id,
      },
      contact: {
        ...data.contact,
        phone_country_id: data.contact.phone_country_id.id,
      }
    }))
    .post('/vends/create', {
      onSuccess: () => {
        emit('modalClose')
      },
      preserveState: true,
      replace: true,
    })
  }

  if(props.type === 'update') {
    form.value
      .transform((data) => ({
        ...data,
        base_currency_id: data.base_currency_id.id,
        address: {
          ...data.address,
          country_id: data.address.country_id.id,
        },
        contact: {
          ...data.contact,
          phone_country_id: data.contact.phone_country_id.id,
        }
      }))
      .post('/vends/' + form.value.id + '/update', {
      onSuccess: () => {
        emit('modalClose')
      },
      preserveState: true,
      replace: true,
    })
  }
}

</script>