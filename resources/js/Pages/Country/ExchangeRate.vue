<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col md:flex-row space-x-2">
          <span class="text-gray-600" v-if="props.country">
            New Rate for
          </span>
          <span v-if="props.country">
            {{ props.country.currency_name }}
          </span>
        </div>
      </template>
      <template #default>
        <form @submit.prevent="submit" id="submit">
          <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">
            <div class="sm:col-span-6">
              <FormInput v-model="form.rate" :error="form.errors.rate" required="true">
                Rate (1 {{authUser.profile.base_currency.currency_name}} &#8776; ? {{props.country.currency_name}})
              </FormInput>
            </div>
            <div class="sm:col-span-6">
              <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
                <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
                  <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-300">
                      <thead class="bg-gray-50">
                        <tr>
                          <th scope="col" class="py-3.5 pl-4 pr-3 text-left text-sm font-semibold text-gray-900 sm:pl-6">
                            #
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                            History Rate
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-left text-sm font-semibold text-gray-900">
                            Date
                          </th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-gray-200 bg-white">
                        <tr v-for="(quoteExchangeRate, quoteExchangeRateIndex) in country.quoteExchangeRates" :key="quoteExchangeRate.id">
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6">
                            {{ quoteExchangeRateIndex + 1 }}
                          </td>
                          <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-600 ">
                            {{ quoteExchangeRate.rate }}
                          </td>
                          <td class="whitespace-nowrap px-3 py-4 text-sm text-gray-600">
                            {{ quoteExchangeRate.created_at }}
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
      <!-- <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-6 lg:-mx-8">
        <div class="inline-block min-w-full py-2 align-middle md:px-6 lg:px-8">
          <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
            <table class="min-w-full divide-y divide-gray-300">
                  <thead class="bg-gray-50">
                    <tr class="divide-x divide-gray-200">
                      <TableHead>
                        #
                      </TableHead>
                      <TableHead>
                        History Rate
                      </TableHead>
                      <TableHead>
                        Date
                      </TableHead>
                    </tr>
                  </thead>
                  <tbody class="bg-white">
                    <tr v-for="(quoteExchangeRate, quoteExchangeRateIndex) in country.quoteExchangeRates" :key="quoteExchangeRate.id" class="divide-x divide-gray-200">
                      <TableData :currentIndex="quoteExchangeRateIndex" :totalLength="country.quoteExchangeRates.length" inputClass="text-center">
                        {{ quoteExchangeRateIndex + 1  }}
                      </TableData>
                      <TableData :currentIndex="quoteExchangeRateIndex" :totalLength="country.quoteExchangeRates.length" inputClass="text-right">
                        {{ quoteExchangeRate.rate }}
                      </TableData>
                      <TableData :currentIndex="quoteExchangeRateIndex" :totalLength="country.quoteExchangeRates.length" inputClass="text-center">
                        {{ quoteExchangeRate.created_at }}
                      </TableData>
                    </tr>
                    <tr v-if="!country.quoteExchangeRates.length">
                      <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                          No Results Found
                      </td>
                    </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div> -->

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
import { ArrowUturnLeftIcon, CheckCircleIcon } from '@heroicons/vue/20/solid';
import { useForm, usePage } from '@inertiajs/inertia-vue3';
import { ref, computed } from 'vue'

const props = defineProps({
  country: Object,
  type: String,
  showModal: Boolean,
})

const emit = defineEmits(['modalClose'])

const form = ref(
  useForm(getDefaultForm())
)
const authUser = computed(() => usePage().props.value.auth.user)

function getDefaultForm() {
  return {
    rate: ''
  }
}

function submit() {
  form.value.clearErrors()

  if(props.type === 'update') {
    form.value
      .post('/countries/' + props.country.id + '/exchange-rate', {
      onSuccess: () => {
        emit('modalClose')
      },
      preserveState: true,
      replace: true,
    })
  }
}

</script>