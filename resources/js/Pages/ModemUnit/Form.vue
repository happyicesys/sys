<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600" v-if="props.modemUnit">
            Editing
          </span>
          <span v-if="props.modemUnit">
            {{ props.modemUnit.name }}
          </span>
          <span class="text-gray-600" v-else>
            Create New Modem IMEI
          </span>
        </div>
      </template>
      <template #default>
        <form @submit.prevent="submit" id="submit">
          <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">
            <div class="sm:col-span-6">
              <FormInput v-model="form.imei" :error="form.errors.imei" required="true">
                IMEI
              </FormInput>
            </div>
            <div class="sm:col-span-6">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Modem Model
                </label>
                <MultiSelect
                  v-model="form.modem_type_id"
                  :options="modemTypeOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.modem_type_id">
                  {{ form.errors.modem_type_id }}
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
import FormInput from '@/Components/FormInput.vue';
import Modal from '@/Components/Modal.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { ArrowUturnLeftIcon, CheckCircleIcon } from '@heroicons/vue/20/solid';
import { useForm } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue'

const props = defineProps({
  modemTypeOptions: Object,
  modemUnit: Object,
  type: String,
  showModal: Boolean,
})

const emit = defineEmits(['modalClose'])

const form = ref(
  useForm(getDefaultForm())
)

const modemTypeOptions = ref([])

onMounted(() => {
  if (props.modemTypeOptions && props.modemTypeOptions.data) {
    modemTypeOptions.value = [
      { id: '', name: '--- Clear ---' },
      ...props.modemTypeOptions.data.map(modemType => ({
        id: modemType.id,
        name: modemType.name,
      }))
    ];
  } else {
    modemTypeOptions.value = [];
  }

  if (props.modemUnit) {
    form.value = useForm({
      ...props.modemUnit,
      modem_type_id: modemTypeOptions.value.find(modemType => modemType.id === props.modemUnit.modem_type_id)
    });
  } else {
    form.value = useForm(getDefaultForm());
  }
});



function getDefaultForm() {
  return {
    imei: '',
    modem_type_id: ''
  }
}

function submit() {
  form.value.clearErrors()

  if(props.type === 'create') {
    form.value
    .transform((data) => {
      return {
        ...data,
        modem_type_id: data.modem_type_id ? data.modem_type_id.id : null
      }
    })
    .post('/modem-units/store', {
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
          modem_type_id: data.modem_type_id ? data.modem_type_id.id : null
        }
      })
      .post('/modem-units/' + form.value.id + '/update', {
      onSuccess: () => {
        emit('modalClose')
      },
      preserveState: true,
      replace: true,
    })
  }
}

</script>