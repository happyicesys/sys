<template>
  <Head title="Create UI Setting" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Create UI Setting
      </h2>
    </template>

     <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
        <div class="shadow-sm ring-1 ring-black ring-opacity-5 p-5 mb-3">
          <form @submit.prevent="submit" id="submit">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-6 pb-5 mb-3">
            <div class="sm:col-span-5">
              <FormInput v-model="form.name">
                Name
              </FormInput>
            </div>

            <div class="sm:col-span-5">
              <FormTextarea v-model="form.remarks">
                Remarks
              </FormTextarea>
            </div>

            <div class="sm:col-span-6 py-4">
              <span class="flex space-x-1">
                <Button
                  type="submit"
                  class="bg-green-500 hover:bg-green-600 text-white flex space-x-1"
                >
                  <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                  <span>
                    Create UI Setting
                  </span>
                </Button>
              </span>
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
import { CheckCircleIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";

const props = defineProps({
    operatorOptions: Object,
    type: String,
    vend: Object,
  })

const toast = useToast()
const form = ref(
  useForm(getDefaultForm())
)
const vend = ref([])

onMounted(() => {

  form.value = useForm(getDefaultForm())

})

function getDefaultForm() {
  return {
    name: '',
    remarks: '',
  }
}

function submit() {
  form.value.clearErrors()

  form.value
    .post('/apk-settings/store', {
    onSuccess: () => {
      toast.success("Successfully created, please continue to edit the settings", {
        timeout: 3000
      });
    },
    preserveState: true,
    replace: true,
  })
}

</script>