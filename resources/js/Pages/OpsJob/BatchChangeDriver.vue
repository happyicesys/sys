<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header>
        <div class="flex flex-col space-y-1 text-left">
          <span class="text-base font-semibold">Batch Assign Driver</span>
          <span class="text-sm text-gray-500">
            Reassign
            <span class="font-semibold text-blue-700">{{ selectedItemIds.length }}</span>
            job(s)
            <span v-if="selectedTaskIds && selectedTaskIds.length > 0" class="text-teal-700">
              + <span class="font-semibold">{{ selectedTaskIds.length }}</span> task(s)
            </span>
            to a new driver / date
          </span>
        </div>
      </template>
      <template #default>
        <form @submit.prevent="submit" id="batch-driver-submit">
          <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">
            <div class="sm:col-span-6">
              <label class="flex justify-start text-sm font-medium text-gray-700">
                Current Driver
              </label>
              <div class="mt-1">
                <input
                  type="text"
                  class="shadow-sm block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                  :value="opsJob && opsJob.deliveredBy ? opsJob.deliveredBy.name : ''"
                  disabled
                />
              </div>
            </div>

            <div class="sm:col-span-6">
              <DatePicker v-model="form.date" :error="form.errors && form.errors.date">
                New Date
              </DatePicker>
            </div>

            <div class="sm:col-span-6">
              <label class="flex justify-start text-sm font-medium text-gray-700">
                New Driver
                <span class="text-red-500 ml-0.5">*</span>
              </label>
              <MultiSelect
                v-model="form.delivered_by"
                :options="userOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select driver"
                open-direction="top"
                class="mt-1"
              />
              <div class="text-sm text-red-600 mt-1" v-if="form.errors && form.errors.delivered_by">
                {{ form.errors.delivered_by }}
              </div>
            </div>

            <div class="sm:col-span-6">
              <div class="flex md:justify-end py-3">
                <Button
                  type="button"
                  class="px-3 py-2 ml-1 text-xs md:text-md flex space-x-1 bg-green-500 hover:bg-green-600 text-white w-full md:w-fit"
                  :class="[!form.delivered_by || !form.date ? 'opacity-50 cursor-not-allowed' : '']"
                  :disabled="!form.delivered_by || !form.date"
                  @click.prevent="submit"
                >
                  <span class="flex space-x-1 items-center">
                    <CheckCircleIcon class="w-5 h-5" />
                    <span>Assign {{ selectedItemIds.length + (selectedTaskIds?.length ?? 0) }} Item(s)</span>
                  </span>
                </Button>
              </div>
            </div>
          </div>
        </form>
      </template>
    </Modal>
  </Teleport>
</template>

<script setup>
import { CheckCircleIcon } from '@heroicons/vue/20/solid';
import Button from '@/Components/Button.vue';
import DatePicker from '@/Components/DatePicker.vue';
import Modal from '@/Components/Modal.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { onMounted, ref } from 'vue';
import { router, usePage, useForm } from '@inertiajs/vue3';
import { useToast } from 'vue-toastification';

const props = defineProps({
  opsJob: Object,
  selectedItemIds: {
    type: Array,
    default: () => [],
  },
  selectedTaskIds: {
    type: Array,
    default: () => [],
  },
  showModal: Boolean,
  userOptions: {
    type: [Array, Object],
    default: () => [],
  },
});

const toast = useToast();
const form = ref({});

onMounted(() => {
  form.value = {
    ...useForm({
      delivered_by: '',
      date: props.opsJob ? props.opsJob.date : '',
    }),
  };
});

const emit = defineEmits(['modalClose', 'statusUpdated']);

function submit() {
  if (!form.value.delivered_by || !form.value.date) return;

  form.value
    .transform((data) => ({
      item_ids: props.selectedItemIds,
      task_ids: props.selectedTaskIds ?? [],
      delivered_by: data.delivered_by.id ?? data.delivered_by,
      date: data.date,
    }))
    .post('/ops-jobs/items/batch-update', {
      onSuccess: () => {
        const total = props.selectedItemIds.length + (props.selectedTaskIds?.length ?? 0)
        toast.success(`Successfully assigned ${total} item(s)`, {
          timeout: 3000,
        });
        emit('statusUpdated');
        emit('modalClose');
      },
      preserveState: true,
      replace: true,
    });
}
</script>
