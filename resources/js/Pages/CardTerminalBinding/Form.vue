<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600" v-if="props.binding">
            Editing
          </span>
          <span v-if="props.binding">
            {{ props.binding.terminal_id }}
          </span>
          <span class="text-gray-600" v-else>
            Create New Binding
          </span>
        </div>
      </template>
      <template #default>
        <form @submit.prevent="submit" id="submit">
          <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">
            <div class="sm:col-span-6">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Provider
              </label>
              <MultiSelect
                v-model="form.provider"
                :options="providerOptions"
                trackBy="id"
                valueProp="id"
                label="name"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.provider">
                {{ form.errors.provider }}
              </div>
            </div>
            <div class="sm:col-span-6">
              <FormInput v-model="form.terminal_id" :error="form.errors.terminal_id" required="true" placeholderStr="e.g. 23082824">
                Terminal ID (TID)
              </FormInput>
            </div>
            <div class="sm:col-span-6">
              <label for="vend_id" class="flex justify-start text-sm font-medium text-gray-700">
                Machine ID
              </label>
              <MultiSelect
                v-model="form.vend_id"
                :options="vendOptions"
                trackBy="id"
                valueProp="id"
                label="name"
                placeholder="Select Machine"
                open-direction="bottom"
                class="mt-1"
                :searchable="true"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.vend_id">
                {{ form.errors.vend_id }}
              </div>
            </div>
            <div class="sm:col-span-3">
              <FormInput v-model="form.bound_from" :error="form.errors.bound_from" inputType="date">
                Bound From
              </FormInput>
            </div>
            <!-- Blank Bound Until = the binding is CURRENT. Set a date when the
                 terminal comes off the machine so historical reports still match. -->
            <div class="sm:col-span-3">
              <FormInput v-model="form.bound_until" :error="form.errors.bound_until" inputType="date">
                Bound Until (blank = current)
              </FormInput>
            </div>
            <div class="sm:col-span-6">
              <FormInput v-model="form.remarks" :error="form.errors.remarks">
                Remarks
              </FormInput>
            </div>
          </div>
          <div class="sm:col-span-6">
            <div class="flex space-x-1 mt-5 justify-end">
              <!-- type="button": the shared Button defaults to submit, and a submit
                   button inside this form would save (or 422) on the way out. -->
              <Button
                type="button"
                class="bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1"
                @click="$emit('modalClose')"
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
import { useToast } from "vue-toastification";

const props = defineProps({
  binding: Object,
  providers: Array,
  vends: Array,
  type: String,
  showModal: Boolean,
})

const emit = defineEmits(['modalClose'])

const form = ref(
  useForm(getDefaultForm())
)
const providerOptions = ref([])
const vendOptions = ref([])
const toast = useToast()

onMounted(() => {
  providerOptions.value = props.providers.map((p) => ({ id: p, name: p.toUpperCase() }))
  vendOptions.value = props.vends.map((v) => {
    let vendName = v.name ? ' - ' + v.name : '';
    return { id: v.id, name: '(' + v.code + ')' + vendName };
  })

  form.value = props.binding ? useForm({
    ...props.binding,
    provider: providerOptions.value.find((data) => data.id == props.binding.provider),
    vend_id: vendOptions.value.find((v) => v.id == props.binding.vend_id),
    bound_from: props.binding.bound_from || '',
    bound_until: props.binding.bound_until || '',
    remarks: props.binding.remarks || '',
  }) : useForm(getDefaultForm())

  if (!props.binding && providerOptions.value.length) {
    form.value.provider = providerOptions.value[0]
  }
})

function getDefaultForm() {
  return {
    provider: null,
    terminal_id: '',
    vend_id: null,
    bound_from: '',
    bound_until: '',
    remarks: '',
  }
}

function submit() {
  form.value.clearErrors()

  const transform = (data) => {
    return {
      ...data,
      provider: data.provider ? data.provider.id : null,
      vend_id: data.vend_id ? data.vend_id.id : null,
    }
  }

  if(props.type === 'create') {
    form.value
    .transform(transform)
    .post('/card-terminal-bindings', {
      onSuccess: () => {
        toast.success("Binding created successfully", { timeout: 3000 })
        emit('modalClose')
      },
      onError: () => {
        toast.error("Failed to create binding", { timeout: 3000 })
      },
      preserveState: true,
      replace: true,
    })
  }

  if(props.type === 'update') {
    form.value
    .transform(transform)
    .put('/card-terminal-bindings/' + form.value.id, {
      onSuccess: () => {
        toast.success("Binding updated successfully", { timeout: 3000 })
        emit('modalClose')
      },
      onError: () => {
        toast.error("Failed to update binding", { timeout: 3000 })
      },
      preserveState: true,
      replace: true,
    })
  }
}

</script>
