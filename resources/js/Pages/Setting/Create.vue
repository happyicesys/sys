<template>

  <Head title="VM Management" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ typeName }} Vending Machine
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
        <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll p-5">
          <form @submit.prevent="submit" id="submit">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-6 pb-2">
              <div class="sm:col-span-6">
                <SearchVendCodeInput v-model="form.code" @selected="onVendCodeSelected" required="true" :error="form.errors.code" v-if="type == 'create'">
                  Code
                </SearchVendCodeInput>
              </div>
              <div class="sm:col-span-3">
                <DatePicker v-model="form.begin_date" :error="form.errors.begin_date" @input="onDateFromChanged()"
                v-if="permissions.includes('update vends')">
                  Begin Date
                </DatePicker>
              </div>
            </div>
            <div class="sm:col-span-6">
              <div class="flex flex-col space-y-1 sm:flex-row sm:space-x-1 sm:space-y-0 mt-5 justify-end">
                <Link href="/settings" class="bg-gray-300 hover:bg-gray-400 text-gray-700 rounded">
                  <Button
                   class="space-x-1"
                  >
                    <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
                    <span>
                      Back
                    </span>
                  </Button>
                </Link>
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
import DatePicker from '@/Components/DatePicker.vue';
import SearchVendCodeInput from '@/Components/SearchVendCodeInput.vue';
import { ArrowUturnDownIcon, ArrowUturnLeftIcon, CheckCircleIcon, PauseCircleIcon, PlayIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import moment from 'moment';

const props = defineProps({
    vend: Object,
    type: String,
  })

  const booleanOptions = ref([])
  const form = ref(
    useForm(getDefaultForm())
  )
  const loading = ref(false)
  const typeName = ref('')
  const operatorCountry = usePage().props.auth.operatorCountry
  const operatorRole = usePage().props.auth.operatorRole
  const permissions = usePage().props.auth.permissions
  const now = ref(moment().format('HH:mm:ss'))

onMounted(() => {
    typeName.value = 'Create New'
    form.value = useForm(getDefaultForm())
})

function getDefaultForm() {
  return {
    code: '',
    begin_date: moment().format('YYYY-MM-DD'),
  }
}

function onVendCodeSelected(vend) {
  form.value.code = vend.code
}

function submit() {
  form.value.clearErrors()
  if(props.type === 'create') {
    form.value
    .post('/settings/vend/store', {
      preserveState: true,
      replace: true,
    })
  }
}
</script>