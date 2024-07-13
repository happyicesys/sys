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
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Version
              </label>
              <MultiSelect
                v-model="form.version"
                :options="versionOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.version">
                {{ form.errors.version }}
              </div>
            </div>
            <div class="sm:col-span-6">
              <FormTextarea v-model="form.desc" :error="form.errors.desc" rows="10">
                Desc
              </FormTextarea>
            </div>
            <div class="sm:col-span-6">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Compatible Setting Chart(s)
              </label>
              <MultiSelect
                v-model="form.vendConfigCompatibles"
                :options="vendConfigOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                mode="tags"
                class="mt-1"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.vendConfigCompatibles">
                {{ form.errors.vendConfigCompatibles }}
              </div>
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
              <AttachmentOverview
                :items="vendConfig.data.attachments"
              >
            </AttachmentOverview>
            </div>

            <div class="sm:col-span-6">
              <UploadFileInput
                :endpoint="'/vend-configs/' + vendConfig.data.id + '/upload-attachments'"
              >
              </UploadFileInput>
            </div>

            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3" v-if="form.id">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center">
                  <span class="px-3 bg-white text-lg font-medium text-gray-900"> Machine Prefix Binding </span>
                </div>
              </div>
            </div>

            <div class="sm:col-span-5" v-if="form.id">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Machine Prefix
              </label>
              <MultiSelect
                v-model="form.vend_prefix_id"
                :options="vendPrefixOptions"
                trackBy="id"
                valueProp="id"
                label="name"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.vend_prefix_id">
                {{ form.errors.vend_prefix_id }}
              </div>
            </div>

            <div class="sm:col-span-1" v-if="form.id">
              <Button
              type="button"
              @click="bindVendPrefix()"
              class="bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6"
              :class="[!form.vend_prefix_id ? 'opacity-50 cursor-not-allowed' : '']"
              :disabled="!form.vend_prefix_id"
              >
                <PlusCircleIcon class="w-4 h-4"></PlusCircleIcon>
                <span>
                  Add
                </span>
              </Button>
            </div>

            <div class="sm:col-span-6 flex flex-col mt-3" v-if="form.id">
            <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5">
              <div class="inline-block min-w-full py-2 align-middle md:px-4 lg:px-6">
                <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                  <table class="min-w-full divide-y divide-gray-300">
                    <thead class="bg-gray-50">
                      <tr>
                        <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                          #
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                          Machine Prefix
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                          Action
                        </th>
                      </tr>
                    </thead>
                    <tbody class="bg-white">
                      <tr v-for="(vendPrefix, vendPrefixIndex) in vendPrefixes" :key="vendPrefix.id" :class="vendPrefixIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                          {{ vendPrefixIndex + 1 }}
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                          {{ vendPrefix.name }}
                        </td>
                        <td class="whitespace-nowrap py-4 text-sm text-center">
                          <Button
                            class="bg-red-400 hover:bg-red-500 text-white"
                            @click="unbindVendPrefix(vendPrefix)"
                          >
                            <BackspaceIcon class="w-4 h-4"></BackspaceIcon>
                          </Button>
                        </td>
                      </tr>
                      <tr v-if="!vendPrefixes.length">
                        <td colspan="5" class="whitespace-nowrap py-4 text-sm font-medium text-gray-600 text-center">
                          No Records Found
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
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
import AttachmentOverview from '@/Components/AttachmentOverview.vue';
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
  vendConfigOptions: Object,
  vendPrefixOptions: Object,
  versionOptions: Object,
})

const booleanStrictOptions = ref([
    {id: 'true', value: 'Yes'},
    {id: 'false', value: 'No'},
])

const emit = defineEmits(['modalClose'])

const form = ref(
  useForm(getDefaultForm())
)

const vendConfigOptions = ref([])
const vendPrefixes = ref([])
const vendPrefixOptions = ref([])
const versionOptions = ref([])

onMounted(() => {
  vendConfigOptions.value = props.vendConfigOptions.data.map((data) => {return {id: data.id, value: data.name}})
  vendPrefixOptions.value = props.vendPrefixOptions.data
  vendPrefixes.value = JSON.parse(JSON.stringify(props.vendConfig.data.vendPrefixes))
  versionOptions.value = Object.entries(props.versionOptions).map(([id, version]) => ({id: version, value: version}))

  form.value = props.vendConfig ? useForm({
    ...props.vendConfig.data,
    vendConfigCompatibles: props.vendConfig.data.vendConfigCompatibles.map((vendConfigCompatible) => {return {id: vendConfigCompatible.id, value: vendConfigCompatible.name}}),
    version: props.vendConfig.data.version ? {id: props.vendConfig.data.version, value: props.vendConfig.data.version} : null,
  }) : useForm(getDefaultForm())
})

function bindVendPrefix() {
  if(vendPrefixes.value.indexOf(form.value.vend_prefix_id) < 0) {
    vendPrefixes.value.push(form.value.vend_prefix_id)
    vendPrefixes.value.sort((a, b) => a.name - b.name)
    vendPrefixOptions.value.splice(vendPrefixOptions.value.indexOf(form.value.vend_prefix_id), 1)
    vendPrefixOptions.value.sort((a, b) => a.name - b.name)
    form.value.vend_prefix_id = ''
  }
}

function unbindVendPrefix(vendPrefix) {
  vendPrefixes.value.splice(vendPrefixes.value.indexOf(vendPrefix), 1)
  vendPrefixOptions.value.push(vendPrefix)
  vendPrefixOptions.value.sort((a, b) => a.name - b.name)
}

function getDefaultForm() {
  return {
    id: '',
    name: '',
    // is_active: '',
    desc: '',
    vendConfigCompatibles: [],
    vend_prefix_id: '',
    version: '',
    // channel_code: '',
    // product_id: '',
  }
}

function submit() {
  form.value.clearErrors()
    form.value
      .transform((data) => {
        return {
          ...data,
          vendConfigCompatibles: data.vendConfigCompatibles.map(vendConfigCompatible => vendConfigCompatible.id),
          vendPrefixes: vendPrefixes.value,
          version: data.version ? data.version.id : null,
        }
      })
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