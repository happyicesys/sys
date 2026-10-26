<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600" v-if="props.profile">
            {{ $t('Editing') }}
          </span>
          <span v-if="props.profile">
            {{ props.profile.name }}
          </span>
          <span class="text-gray-600" v-else>
            {{ $t('Create New Profile') }}
          </span>
        </div>
      </template>
      <template #default>
        <form @submit.prevent="submit" id="submit">
          <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">
            <div class="sm:col-span-4">
              <FormInput v-model="form.name" :error="form.errors.name" required="true">
                {{ $t('Name') }}
              </FormInput>
            </div>
            <div class="sm:col-span-2">
              <FormInput v-model="form.alias" :error="form.errors.alias">
                {{ $t('Alias') }}
              </FormInput>
            </div>
            <div class="sm:col-span-3">
              <FormInput v-model="form.uen" :error="form.errors.uen">
                {{ $t('UEN') }}
              </FormInput>
            </div>

            <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                {{ $t('Base Currency') }}
              </label>
              <MultiSelect
                v-model="form.base_currency_id"
                :options="countryOptions"
                trackBy="id"
                valueProp="id"
                label="currency_name"
                :placeholder="$t('Select')"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.base_currency_id">
                {{ form.errors.base_currency_id }}
              </div>
            </div>

            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center">
                  <span class="px-4 bg-white text-lg font-medium text-gray-900"> {{ $t('Contact') }} </span>
                </div>
              </div>
            </div>
            <div class="sm:col-span-3">
              <FormInput v-model="form.contact.name" :error="form.errors['contact.name']">
                {{ $t('Name') }}
              </FormInput>
            </div>
            <div class="sm:col-span-3">
              <FormInput v-model="form.contact.email" :error="form.errors['contact.email']">
                {{ $t('Email') }}
              </FormInput>
            </div>
            <div class="sm:col-span-2">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                {{ $t('Phone Code') }}
              </label>
              <MultiSelect
                v-model="form.contact.phone_country_id"
                :options="countryOptions"
                trackBy="id"
                valueProp="id"
                label="phone_code"
                :placeholder="$t('Select')"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors['contact.phone_country_id']">
                {{ form.errors['contact.phone_country_id'] }}
              </div>
            </div>
            <div class="sm:col-span-4">
              <FormInput v-model="form.contact.phone_num" required="true" :error="form.errors['contact.phone_num']">
                {{ $t('Phone Number') }}
              </FormInput>
            </div>

            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center">
                  <span class="px-3 bg-white text-lg font-medium text-gray-900"> {{ $t('Address') }} </span>
                </div>
              </div>
            </div>


            <div class="sm:col-span-6">
              <SearchAddressInput v-model="form.address.postcode" @selected="onAddressSelected" required="true" :error="form.errors['address.postcode']">
                {{ $t('Postcode') }}
              </SearchAddressInput>
            </div>
            <div class="sm:col-span-3">
              <FormInput v-model="form.address.unit_num" required="true" :error="form.errors['address.unit_num']">
                {{ $t('Unit Num') }}
              </FormInput>
            </div>
            <div class="sm:col-span-3">
              <FormInput v-model="form.address.block_num" :error="form.errors['address.block_num']">
                {{ $t('Block Num') }}
              </FormInput>
            </div>
            <div class="sm:col-span-3">
              <FormInput v-model="form.address.building" :error="form.errors['address.building']">
                {{ $t('Building Name') }}
              </FormInput>
            </div>
            <div class="sm:col-span-3">
              <FormInput v-model="form.address.street_name" required="true" :error="form.errors['address.street_name']">
                {{ $t('Street Name') }}
              </FormInput>
            </div>
            <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                {{ $t('Country') }}
              </label>
              <MultiSelect
                v-model="form.address.country_id"
                :options="countryOptions"
                trackBy="id"
                valueProp="id"
                label="name"
                :placeholder="$t('Select')"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors['address.country_id']">
                {{ form.errors['address.country_id'] }}
              </div>
            </div>
            <div class="sm:col-span-3 hidden">
              <FormInput v-model="form.address.latitude">
                {{ $t('Latitude') }}
              </FormInput>
            </div>
            <div class="sm:col-span-3 hidden">
              <FormInput v-model="form.address.longitude">
                {{ $t('Longitude') }}
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
                  {{ $t('Back') }}
                </span>
              </Button>
              <Button type="submit" class="bg-green-500 hover:bg-green-600 text-white flex space-x-1">
                <!-- @click.prevent="submit" -->
                <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                <span>
                  {{ $t('Save') }}
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
import SearchAddressInput from '@/Components/SearchAddressInput.vue';
import { ArrowUturnLeftIcon, CheckCircleIcon } from '@heroicons/vue/20/solid';
import { useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue'
import { useToast } from "vue-toastification";
import { t } from '@/i18n';

const props = defineProps({
  profile: Object,
  countries: Object,
  type: String,
  showModal: Boolean,
})

const emit = defineEmits(['modalClose'])

const form = ref(
  useForm(getDefaultForm())
)
const countryOptions = ref([])
const toast = useToast()

onMounted(() => {
  countryOptions.value = props.countries.data
  form.value = props.profile ? useForm(props.profile) : useForm(getDefaultForm())
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
    .post('/profiles/create', {
      onSuccess: () => {
        toast.success(t("Profile created successfully"), { timeout: 3000 })
        emit('modalClose')
      },
      onError: () => {
        toast.error(t("Failed to create profile"), { timeout: 3000 })
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
      .post('/profiles/' + form.value.id + '/update', {
      onSuccess: () => {
        toast.success(t("Profile updated successfully"), { timeout: 3000 })
        emit('modalClose')
      },
      onError: () => {
        toast.error(t("Failed to update profile"), { timeout: 3000 })
      },
      preserveState: true,
      replace: true,
    })
  }
}

</script>