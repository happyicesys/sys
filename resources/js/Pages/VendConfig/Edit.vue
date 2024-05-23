<template>
  <Head title="VM Edit" />
  <BreezeAuthenticatedLayout>
    <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600" v-if="vendConfig.data && vendConfig.data.id">
            Editing Setting Chart
          </span>
          <span v-if="vendConfig.data && vendConfig.data.id">
            {{ vendConfig.data.name }}
          </span>
        </div>
      </template>
    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
        <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll p-5">
          <form @submit.prevent="submit" id="submit">
          <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">
            <div class="sm:col-span-6">
              <FormInput v-model="form.name" :error="form.errors.name" required="true">
                Name
              </FormInput>
            </div>
            <div class="sm:col-span-6">
              <FormTextarea v-model="form.desc" :error="form.errors.desc">
                Desc
              </FormTextarea>
            </div>

            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center ">
                  <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded-md"> Attachment(s) </span>
                </div>
              </div>
            </div>

            <div class="sm:col-span-6">
              <AttachmentList
                :items="vendConfig.data.attachments"
              >
            </AttachmentList>
            </div>

            <div class="sm:col-span-6">
              <UploadFileInput
                :endpoint="'/vend-configs/' + vendConfig.data.id + '/upload-attachments'"
              >
              </UploadFileInput>
            </div>


            <div class="sm:col-span-6 mt-5 ">
              <div class="flex justify-end">
                <!-- <div class="flex space-x-1 justify-start">
                  <Button type="button" class="bg-blue-500 hover:bg-blue-600 text-white flex space-x-1" v-if="form.id" @click="replicateProductMapping()">
                    <DocumentDuplicateIcon class="w-4 h-4"></DocumentDuplicateIcon>
                    <span>
                      Replicate
                    </span>
                  </Button>

                  <Button type="button" v-if="form.id" @click="toggleActivateDeactivate" class="text-white" :class="[form.is_active ? 'bg-red-500 hover:bg-red-600' : 'bg-green-500 hover:bg-green-600']">
                    <div>
                      <span class="flex space-x-1 items-center" v-if="form.is_active">
                        <FolderMinusIcon class="w-4 h-4"></FolderMinusIcon>
                        <span>
                          Deactivate
                        </span>
                      </span>
                      <span class="flex space-x-1 items-center" v-else>
                        <FolderPlusIcon class="w-4 h-4"></FolderPlusIcon>
                        <span>
                          Activate
                        </span>
                      </span>
                    </div>
                  </Button>
                </div> -->

                <div class="flex space-x-1 justify-end">
                  <Link :href="'/vend-configs'">
                    <Button
                      type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1"
                    >
                      <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
                      <span>
                        Back
                      </span>
                    </Button>
                  </Link>

                  <Button type="submit" class="bg-green-500 hover:bg-green-600 text-white flex space-x-1">
                    <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                    <span>
                      Save
                    </span>
                  </Button>
                </div>
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
import AttachmentList from '@/Components/AttachmentList.vue';
import Button from '@/Components/Button.vue';
import FormInput from '@/Components/FormInput.vue';
import FormTextarea from '@/Components/FormTextarea.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import UploadFileInput from '@/Components/UploadFileInput.vue';
import { ArrowUturnLeftIcon, BackspaceIcon, CheckCircleIcon, DocumentDuplicateIcon, FolderMinusIcon, FolderPlusIcon, PlusCircleIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted } from 'vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3';

const props = defineProps({
  products: Object,
  vendConfig: Object,
})

const booleanStrictOptions = ref([
    {id: 'true', value: 'Yes'},
    {id: 'false', value: 'No'},
])

const emit = defineEmits(['modalClose'])

const form = ref(
  useForm(getDefaultForm())
)

onMounted(() => {
  form.value = props.vendConfig ? useForm({
    ...props.vendConfig.data,
  }) : useForm(getDefaultForm())
})

function getDefaultForm() {
  return {
    id: '',
    name: '',
    // is_active: '',
    desc: '',
    // channel_code: '',
    // product_id: '',
  }
}

function submit() {
  form.value.clearErrors()
    form.value
      .transform((data) => ({
        ...data,
      }))
      .post('/vend-configs/' + form.value.id + '/update', {
      onSuccess: () => {
        emit('modalClose')
      },
      preserveState: true,
      replace: true,
    })
}


function toggleActivateDeactivate() {
  form.value.post('/vend-configs/' + form.value.id + '/toggle-activate-deactivate', {
    onSuccess: () => {
      emit('modalClose')
    },
      preserveState: true,
      replace: true,
  })
}

function replicateProductMapping() {
  router.post('/vend-configs/replicate',
  {
    id: form.value.id,
  },
  {
    preserveState: true,
    replace: true,
  })
}

</script>