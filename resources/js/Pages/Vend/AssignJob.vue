<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2 text-gray-800">
          <span class="text-gray-600">
            <div class="flex space-x-2">
              <span>
                Assign {{ vends.length }} Job(s)
              </span>
            </div>
          </span>
        </div>
      </template>
      <template #default>
        <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-2 sm:px-0">
          <dt class="text-sm font-medium leading-6 text-gray-900">Machine(s)</dt>
          <dd class="mt-1 mb-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
            <span v-for="(vend, vendIndex) in vends" class="flex flex-col space-y-1">
              <span>
                <span class="text-blue-700">
                  {{ vend.code }}
                </span>
                -
                <span v-if="vend.person_id">
                  {{ vend.customer_code }}
                </span>
                -
                <span>
                  {{ vend.customer_name }}
                </span>
                <br v-if="vendIndex > 0">
              </span>
            </span>
          </dd>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-5 gap-2">
          <DatePicker
              v-model="form.date"
              class="col-span-5"
          >
              Date
          </DatePicker>
          <div class="col-span-5">
            <label for="text" class="block text-sm font-medium text-gray-700">
              Driver
            </label>
            <MultiSelect
                v-model="form.driver_id"
                :options="driverOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
            >
            </MultiSelect>
          </div>
          <Button
              type="button"
              class="px-3 py-2 mt-2 mb-4 text-xs flex space-x-1 bg-green-500 hover:bg-green-600 text-white"
              :class="[form.date && form.driver_id ? '' : 'opacity-50 cursor-not-allowed']"
              :disabled="!form.date || !form.driver_id"
              @click="onAssignJobClicked()"
          >
            <span class="flex space-x-1">
              <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
              <span>
                Assign Job(s)
              </span>
            </span>
          </Button>
        </div>
      </template>
    </Modal>
  </Teleport>
</template>

<script setup>
import { ChevronDoubleDownIcon, ChevronDoubleUpIcon, CheckCircleIcon, PencilSquareIcon, XCircleIcon } from '@heroicons/vue/20/solid';
import Button from '@/Components/Button.vue';
import DatePicker from '@/Components/DatePicker.vue';
import Modal from '@/Components/Modal.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { onMounted, ref } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";
const props = defineProps({
  driverOptions: [Array, Object],
  vends: Object,
  showModal: Boolean,
})

const authUser = usePage().props.auth.user
const driverOptions = ref([])
const operatorCountry = usePage().props.auth.operatorCountry
const toast = useToast()

const form = ref(
  useForm(getDefaultForm())
)

function getDefaultForm() {
  return {
    driver_id: '',
    date: moment().add(1, 'days').format('YYYY-MM-DD'),
  }
}

onMounted(() => {
  driverOptions.value = props.driverOptions.data.map(driver => {
    let roleStr = ''
    if(driver.roles) {
      if(driver.roles.length > 0) {
        roleStr = ' (' + driver.roles.map(role => role.name).join(', ') + ')'
      }else if(driver.role_id) {
         roleStr = ' (' + driver.role_id.name + ')'
      }
    }
    return {
      id: driver.id,
      value: driver.name + roleStr,
    }
  })
})
const emit = defineEmits(['modalClose', 'jobAssigned'])


function onAssignJobClicked(product) {
  form.value
  .transform((data) => {
    return {
      ...data,
      vends_id: props.vends.map(vend => vend.vend_id),
      driver_id: data.driver_id.id,
      date: data.date,
    }
  })
  .post('/ops-jobs/assign', {
    onSuccess: () => {
      toast.success("Successfully Saved", {
        timeout: 3000
      });
      emit('jobAssigned')
    },
    preserveState: true,
    replace: true,
  })
}

</script>