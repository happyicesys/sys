<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600" v-if="props.cardTerminalUnit">
            Editing
          </span>
          <span v-if="props.cardTerminalUnit">
            {{ props.cardTerminalUnit.terminal_id }}
          </span>
          <span class="text-gray-600" v-else>
            Create New Card Terminal
          </span>
        </div>
      </template>
      <template #default>
        <form @submit.prevent="submit" id="submit">
          <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">
            <div class="sm:col-span-6">
              <FormInput v-model="form.terminal_id" :error="form.errors.terminal_id" required="true">
                Terminal ID
              </FormInput>
            </div>
            <div class="sm:col-span-6">
              <label for="text" class="block text-sm font-medium text-gray-700">
                Card Terminal Company
              </label>
              <MultiSelect
                v-model="form.card_terminal_id"
                :options="companyOptions"
                trackBy="id"
                valueProp="id"
                label="name"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.card_terminal_id">
                {{ form.errors.card_terminal_id }}
              </div>
            </div>
            <div class="sm:col-span-6">
              <FormInput v-model="form.remarks" :error="form.errors.remarks">
                Remarks
              </FormInput>
            </div>
            <!--
              No machine field here on purpose: putting a terminal on a machine
              is done on that machine's Settings page, which keeps the dated
              binding history card settlement matches against.
            -->
            <div class="sm:col-span-6 text-xs text-gray-500">
              To put this terminal on a machine, open that machine under
              Machine Settings — bindings are not editable here.
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
import { ref, computed, onMounted } from 'vue'
import { useToast } from "vue-toastification";

const props = defineProps({
  cardTerminalUnit: Object,
  cardTerminalOptions: Object,
  type: String,
  showModal: Boolean,
})

const emit = defineEmits(['modalClose'])

const form = ref(
  useForm(getDefaultForm())
)
const toast = useToast()

const companyOptions = computed(() => ((props.cardTerminalOptions?.data) ?? []).map(company => ({
  id: company.id,
  name: company.name,
})))

onMounted(() => {
  const unit = props.cardTerminalUnit

  // Only the editable columns go into the form — the listing resource also
  // carries the read-only machine columns, which the server would reject.
  form.value = useForm({
    id: unit?.id ?? null,
    terminal_id: unit?.terminal_id ?? '',
    card_terminal_id: unit?.card_terminal_id
      ? companyOptions.value.find(c => c.id === unit.card_terminal_id) ?? null
      : null,
    remarks: unit?.remarks ?? '',
  })
})

function getDefaultForm() {
  return {
    id: null,
    terminal_id: '',
    card_terminal_id: null,
    remarks: '',
  }
}

function submit() {
  form.value.clearErrors()

  const payload = form.value.transform((data) => ({
    ...data,
    card_terminal_id: data.card_terminal_id ? data.card_terminal_id.id : null,
  }))

  if(props.type === 'create') {
    payload
    .post('/card-terminal-units/create', {
      onSuccess: () => {
        toast.success("Card terminal created successfully", { timeout: 3000 })
        emit('modalClose')
      },
      onError: () => {
        toast.error("Failed to create card terminal", { timeout: 3000 })
      },
      preserveState: true,
      replace: true,
    })
  }

  if(props.type === 'update') {
    payload
      .post('/card-terminal-units/' + form.value.id + '/update', {
      onSuccess: () => {
        toast.success("Card terminal updated successfully", { timeout: 3000 })
        emit('modalClose')
      },
      onError: () => {
        toast.error("Failed to update card terminal", { timeout: 3000 })
      },
      preserveState: true,
      replace: true,
    })
  }
}

</script>
