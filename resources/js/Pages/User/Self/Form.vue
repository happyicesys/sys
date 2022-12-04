
<template>
  <Head title="Account Settings" />
  <BreezeAuthenticatedLayout>
      <template #header>
          <h2 class="font-semibold text-xl text-gray-800 leading-tight">
              Account Settings
          </h2>
      </template>

      <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3 pt-5 md:pt-2">
            <form @submit.prevent="submit">
              <div class="overflow-hidden shadow sm:rounded-md">
                <div class="bg-white px-4 py-5 sm:p-6">
                  <div class="grid grid-cols-12 gap-6">
                    <div class="col-span-12 sm:col-span-8">
                      <FormInput v-model="form.name" :error="form.errors.name" required="true">
                        Name
                      </FormInput>
                    </div>
                    <div class="col-span-12 sm:col-span-6">
                      <FormInput v-model="form.email" :error="form.errors.email">
                        Email
                      </FormInput>
                    </div>
                    <div class="col-span-12 sm:col-span-6">
                      <FormInput v-model="form.username" :error="form.errors.username">
                        Username
                      </FormInput>
                    </div>
                    <div class="col-span-12 sm:col-span-6">
                      <FormInput v-model="form.password" :error="form.errors.password" placeholderStr="Leave blank for same password" inputType="password" autocomplete="new-password">
                        Password
                      </FormInput>
                    </div>
                    <div class="col-span-12 sm:col-span-6">
                      <FormInput v-model="form.password_confirmation" :error="form.errors.password_confirmation" placeholderStr="Leave blank for same password" inputType="password" autocomplete="new-password-confirmation">
                        Password Confirmation
                      </FormInput>
                    </div>
                  </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 text-right sm:px-6">
                  <button type="submit" class="inline-flex justify-center rounded-md border border-transparent bg-indigo-600 py-2 px-4 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">Save</button>
                </div>
              </div>
            </form>
      </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import FormInput from '@/Components/FormInput.vue';
import { useForm } from '@inertiajs/inertia-vue3';
import { Head } from '@inertiajs/inertia-vue3';
import { ref, onMounted } from 'vue';
import { Inertia } from '@inertiajs/inertia';

onMounted(() => {
  form.value = props.user ? useForm({...getDefaultForm(), ...props.user.data}) : useForm(getDefaultForm())
})


const props = defineProps({
  user: Object,
})

const form = ref(
  useForm(getDefaultForm())
)

function getDefaultForm() {
  return {
    id: '',
    name: '',
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
  }
}

function submit() {
  form.value.clearErrors()
  // Inertia.post('/self/' + form.value.id + '/update', form.value,
  //   {
  //     preserveState: true,
  //     replace: true,
  //   })
  form.value.post('/self/' + form.value.id + '/update',
    {
      preserveState: true,
      replace: true,
    }
  )
}

</script>