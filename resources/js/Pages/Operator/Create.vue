<template>

  <Head title="Operator" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Create New Operator
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
        <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll p-5">
          <form @submit.prevent="submit" id="submit">
            <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">
              <div class="sm:col-span-2">
                <FormInput v-model="form.code" :error="form.errors.code">
                  Code
                </FormInput>
              </div>
              <div class="sm:col-span-4">
                <FormInput v-model="form.name" :error="form.errors.name" required="true">
                  Name
                </FormInput>
              </div>
              <div class="sm:col-span-3">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Country
                </label>
                <MultiSelect
                  v-model="form.country_id"
                  :options="countryOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.country_id">
                  {{ form.errors.country_id }}
                </div>
              </div>
              <div class="sm:col-span-3">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Timezone
                </label>
                <MultiSelect
                  v-model="form.timezone"
                  :options="timezoneOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.timezone">
                  {{ form.errors.timezone }}
                </div>
              </div>
              <div class="sm:col-span-4">
                <FormInput v-model="form.gst_vat_rate" :error="form.errors.gst_vat_rate">
                  GST or VAT Rate (%)
                  <span class="text-[9px]">
                      (For Gross Margin Calculation)
                  </span>
                </FormInput>
              </div>
              <div class="sm:col-span-6">
                <FormTextarea v-model="form.remarks" :error="form.errors.remarks">
                  Remarks
                </FormTextarea>
              </div>

              <div class="sm:col-span-6">
                <div class="flex space-x-1 mt-5 justify-end">
                  <Link :href="'/operators'">
                    <Button
                      type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1"
                    >
                      <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
                      <span>
                        Back
                      </span>
                    </Button>
                  </Link>
                  <Button type="submit" v-if="permissions.includes('update operators')" class="bg-green-500 hover:bg-green-600 text-white flex space-x-1">
                    <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                    <span>
                      Save
                    </span>
                  </Button>
                </div>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import FormInput from '@/Components/FormInput.vue';
import FormTextarea from '@/Components/FormTextarea.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { ArrowUturnLeftIcon, BackspaceIcon, CheckCircleIcon, PauseCircleIcon, PlusCircleIcon, PlayIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    countries: [Array, Object],
    timezones: [Array, Object],
    permissions: [Array, Object],
  })

  const countryOptions = ref([])
  const form = ref(
    useForm(getDefaultForm())
  )
  const permissions = usePage().props.auth.permissions
  const timezoneOptions = ref([])

onMounted(() => {
    countryOptions.value = props.countries.data
    form.value = useForm(getDefaultForm())
    timezoneOptions.value = props.timezones.map((timezone, index) => {return {id: index, name: timezone}})
})

function getDefaultForm() {
  return {
    code: '',
    name: '',
    gst_vat_rate: '',
    country_id: '',
    timezone: '',
    remarks: '',
  }
}

function submit() {
  form.value.clearErrors()

  form.value
  .transform((data) => ({
    ...data,
    timezone: data.timezone ? data.timezone.name : null,
    country_id: data.country_id ? data.country_id.id : null,
  }))
  .post('/operators/store', {
    preserveState: true,
    replace: true,
  })
}
</script>