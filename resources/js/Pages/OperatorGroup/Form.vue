<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600" v-if="props.group">Editing</span>
          <span v-if="props.group">{{ props.group.name }}</span>
          <span class="text-gray-600" v-else>Create Operator Group</span>
        </div>
      </template>
      <template #default>
        <form @submit.prevent="submit" id="submit">
          <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">
            <div class="sm:col-span-2">
              <FormInput v-model="form.code" :error="form.errors.code" required="true" placeholderStr="e.g. HIPL">
                Code
              </FormInput>
            </div>
            <div class="sm:col-span-4">
              <FormInput v-model="form.name" :error="form.errors.name" required="true" placeholderStr="e.g. HIPL Group">
                Name
              </FormInput>
            </div>

            <div class="sm:col-span-6 border-t pt-3">
              <p class="text-xs font-semibold text-gray-500 uppercase">Shared CIMB account</p>
              <p class="text-xs text-gray-400">The originating account for this group's refund settlement (CIMB bulk file header).</p>
            </div>
            <div class="sm:col-span-3">
              <FormInput v-model="form.bank_account_no" :error="form.errors.bank_account_no" placeholderStr="10-digit CIMB account">
                Bank Account No.
              </FormInput>
            </div>
            <div class="sm:col-span-3">
              <FormInput v-model="form.bank_account_name" :error="form.errors.bank_account_name" placeholderStr="Account holder name">
                Bank Account Name
              </FormInput>
            </div>

            <div class="sm:col-span-6 border-t pt-3">
              <label class="flex justify-start text-sm font-medium text-gray-700">Operators in this group</label>
              <p class="text-xs text-gray-400 mb-1">These operators all pay refunds from the account above.</p>
              <MultiSelect
                v-model="form.operators"
                :options="operatorChoices"
                mode="tags"
                trackBy="id"
                valueProp="id"
                label="label"
                placeholder="Select operators"
                open-direction="bottom"
                :searchable="true"
                class="mt-1"
              ></MultiSelect>
            </div>

            <div class="sm:col-span-6 flex items-center gap-2">
              <input
                type="checkbox"
                id="group_is_active"
                v-model="form.is_active"
                class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
              />
              <label for="group_is_active" class="text-sm font-medium text-gray-700 cursor-pointer">Active</label>
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
                <span>Back</span>
              </Button>
              <Button type="submit" class="bg-green-500 hover:bg-green-600 text-white flex space-x-1">
                <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                <span>Save</span>
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
import MultiSelect from '@/Components/MultiSelect.vue';
import Modal from '@/Components/Modal.vue';
import { ArrowUturnLeftIcon, CheckCircleIcon } from '@heroicons/vue/20/solid';
import { useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue'
import { useToast } from "vue-toastification";

const props = defineProps({
  group: Object,
  type: String,
  showModal: Boolean,
  operatorOptions: Array,
})

const emit = defineEmits(['modalClose'])
const toast = useToast()

// Display label combines name + code for the tags picker.
const operatorChoices = computed(() => (props.operatorOptions || []).map((o) => ({
  id: o.id,
  code: o.code,
  label: o.code ? (o.name + ' (' + o.code + ')') : o.name,
})))

const form = ref(useForm(getDefaultForm()))

onMounted(() => {
  if (props.group) {
    const ids = props.group.operator_ids || []
    form.value = useForm({
      id: props.group.id,
      code: props.group.code,
      name: props.group.name,
      bank_account_no: props.group.bank_account_no || '',
      bank_account_name: props.group.bank_account_name || '',
      is_active: props.group.is_active ?? true,
      operators: operatorChoices.value.filter((o) => ids.includes(o.id)),
    })
  } else {
    form.value = useForm(getDefaultForm())
  }
})

function getDefaultForm() {
  return {
    code: '',
    name: '',
    bank_account_no: '',
    bank_account_name: '',
    is_active: true,
    operators: [],
  }
}

function submit() {
  form.value.clearErrors()

  const transformed = form.value.transform((data) => ({
    ...data,
    operator_ids: (data.operators || []).map((o) => (o && o.id !== undefined) ? o.id : o),
  }))

  const opts = {
    onSuccess: () => {
      toast.success('Operator group saved', { timeout: 3000 })
      emit('modalClose')
    },
    onError: () => toast.error('Failed to save', { timeout: 3000 }),
    preserveState: true,
    replace: true,
  }

  if (props.type === 'create') {
    transformed.post('/operator-groups/store', opts)
  } else {
    transformed.post('/operator-groups/' + form.value.id + '/update', opts)
  }
}
</script>
