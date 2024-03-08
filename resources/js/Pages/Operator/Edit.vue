<template>

  <Head title="Operator" />

  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ typeName }}
        <span v-if="type == 'update'">
          {{ form.code }} - {{ form.name }}
        </span>
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
        <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll p-5">
          <form @submit.prevent="submit" id="submit">
            <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">
              <div class="sm:col-span-2">
                <FormInput v-model="form.code" :error="form.errors.code">
                  Code
                </FormInput>
              </div>
              <div class="sm:col-span-4">
                <FormInput v-model="form.name" :error="form.errors.name" required="true">
                  Name
                </FormInput>
              </div>
              <div class="sm:col-span-3">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Country
                </label>
                <MultiSelect
                  v-model="form.country_id"
                  :options="countryOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.country_id">
                  {{ form.errors.country_id }}
                </div>
              </div>
              <div class="sm:col-span-3">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Timezone
                </label>
                <MultiSelect
                  v-model="form.timezone"
                  :options="timezoneOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.timezone">
                  {{ form.errors.timezone }}
                </div>
              </div>
              <div class="sm:col-span-4">
                <FormInput v-model="form.gst_vat_rate" :error="form.errors.gst_vat_rate">
                  GST or VAT Rate (%)
                  <span class="text-[9px]">
                      (For Gross Margin Calculation)
                  </span>
                </FormInput>
              </div>
              <div class="sm:col-span-6">
                <FormTextarea v-model="form.remarks" :error="form.errors.remarks">
                  Remarks
                </FormTextarea>
              </div>

              <div class="sm:col-span-6">
                <div class="flex space-x-1 mt-5 justify-end">
                  <Link :href="'/operators'">
                    <Button
                      type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1"
                    >
                      <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
                      <span>
                        Back
                      </span>
                    </Button>
                  </Link>
                  <Button type="submit" v-if="permissions.includes('update operators')" class="bg-green-500 hover:bg-green-600 text-white flex space-x-1">
                    <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                    <span>
                      Save
                    </span>
                  </Button>
                </div>
              </div>

              <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3" v-if="form.id">
                <div class="relative">
                  <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-gray-300"></div>
                  </div>
                  <div class="relative flex justify-center">
                    <span class="px-3 bg-white text-lg font-medium text-gray-900"> QR Payment Gateway(s) </span>
                  </div>
                </div>
              </div>

              <div class="sm:col-span-2" :class="[form.payment_gateway ? 'sm:col-span-6' : 'sm:col-span-5']" v-if="form.id">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Payment Gateway
                </label>
                <MultiSelect
                  v-model="form.payment_gateway"
                  :options="countryPaymentGatewayOptions"
                  trackBy="id"
                  valueProp="id"
                  label="full_name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                  ref="multiselect"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.payment_gateway">
                  {{ form.errors.payment_gateway }}
                </div>
              </div>

              <div class="sm:col-span-1" v-if="form.id && !form.payment_gateway">
                <Button
                type="button"
                @click="storeOperatorPaymentGateway()"
                class="bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6"
                :class="[
                  !form.payment_gateway || !form.payment_gateway_type ? 'opacity-50 cursor-not-allowed' : ''
                  ]"
                :disabled="!form.payment_gateway || !form.payment_gateway_type "
                >
                  <PlusCircleIcon class="w-4 h-4"></PlusCircleIcon>
                  <span>
                    Add
                  </span>
                </Button>
              </div>

              <div class="sm:col-span-3" v-if="form.id && form.payment_gateway && form.payment_gateway.key1_name">
                <FormInput v-model="form.payment_gateway_key1" :error="form.errors.payment_gateway_key1" required="true">
                  {{ form.payment_gateway.key1_name }}
                </FormInput>
              </div>

              <div class="sm:col-span-3" v-if="form.id && form.payment_gateway && form.payment_gateway.key2_name">
                <FormInput v-model="form.payment_gateway_key2" :error="form.errors.payment_gateway_key2">
                  {{ form.payment_gateway.key2_name }}
                </FormInput>
              </div>

              <div class="sm:col-span-3" v-if="form.id && form.payment_gateway && form.payment_gateway.key3_name">
                <FormInput v-model="form.payment_gateway_key3" :error="form.errors.payment_gateway_key3">
                  {{ form.payment_gateway.key3_name }}
                </FormInput>
              </div>

              <div class="sm:col-span-3" v-if="form.id && form.payment_gateway">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Type
                  <span class="text-red-500">*</span>
                </label>
                <MultiSelect
                  v-model="form.payment_gateway_type"
                  :options="operatorPaymentGatewayTypes"
                  trackBy="id"
                  valueProp="id"
                  label="id"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                  ref="multiselect"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.payment_gateway_type">
                  {{ form.errors.payment_gateway_type }}
                </div>
              </div>
              <div class="sm:col-span-6 flex justify-end" v-if="form.id && form.payment_gateway">
                <Button
                type="button"
                @click="storeOperatorPaymentGateway()"
                class="bg-green-500 hover:bg-green-600 text-white"
                :class="[
                  !form.payment_gateway ||
                  !form.payment_gateway_type ||
                  !form.payment_gateway_key1 ?
                  'opacity-50 cursor-not-allowed' : ''
                  ]"
                :disabled="!form.payment_gateway || !form.payment_gateway_type || !form.payment_gateway_key1 || !permissions.includes('update operators')"
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
                            Name
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Type
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Private Key
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Action
                          </th>
                        </tr>
                      </thead>
                      <tbody class="bg-white">
                        <tr v-for="(operatorPaymentGateway, operatorPaymentGatewayIndex) in operatorPaymentGateways" :key="operatorPaymentGateway.id" :class="operatorPaymentGatewayIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                            {{ operatorPaymentGatewayIndex + 1 }}
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                            {{ operatorPaymentGateway.paymentGateway.name }}
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                            {{ operatorPaymentGateway.type }}
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                            {{ operatorPaymentGateway.key1 }}
                          </td>
                          <td class="whitespace-nowrap py-4 text-sm text-center">
                            <Button
                              class="bg-red-400 hover:bg-red-500 text-white"
                              @click.prevent="deleteOperatorPaymentGateway(operatorPaymentGateway)"
                            >
                              <BackspaceIcon class="w-4 h-4"></BackspaceIcon>
                            </Button>
                          </td>
                        </tr>
                        <tr v-if="!operatorPaymentGateways.length">
                          <td colspan="5" class="whitespace-nowrap py-4 text-sm font-medium text-center">
                            No Result Found
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
            </div>

            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3" v-if="form.id">
                <div class="relative">
                  <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-gray-300"></div>
                  </div>
                  <div class="relative flex justify-center">
                    <span class="px-3 bg-white text-lg font-medium text-gray-900"> Delivery Platform(s) </span>
                  </div>
                </div>
              </div>

              <div :class="[form.payment_gateway ? 'sm:col-span-6' : 'sm:col-span-5']" v-if="form.id">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Delivery Platform
                </label>
                <MultiSelect
                  v-model="form.delivery_platform"
                  :options="countryDeliveryPlatformOptions"
                  trackBy="id"
                  valueProp="id"
                  label="full_name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                  ref="multiselect"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.delivery_platform">
                  {{ form.errors.delivery_platform }}
                </div>
              </div>

              <div class="sm:col-span-1" v-if="form.id && !form.delivery_platform">

                <Button
                  type="button"
                  @click="storeDeliveryPlatformOperator()"
                  class="bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6"
                  :class="[
                    !form.delivery_platform ||
                    !form.delivery_platform_type ?
                    'opacity-50 cursor-not-allowed' : ''
                    ]"
                  :disabled="!form.delivery_platform || !form.delivery_platform_type || !permissions.includes('update operators')"
                >
                  <PlusCircleIcon class="w-4 h-4"></PlusCircleIcon>
                  <span>
                    Add
                  </span>
                </Button>
              </div>

              <div class="sm:col-span-3" v-if="form.id && form.delivery_platform && form.delivery_platform.field1_name">
                <FormInput v-model="form.delivery_platform_field1" :error="form.errors.delivery_platform_field1" required="true">
                  {{ form.delivery_platform.field1_name }}
                </FormInput>
              </div>

              <div class="sm:col-span-3" v-if="form.id && form.delivery_platform && form.delivery_platform.field2_name">
                <FormInput v-model="form.delivery_platform_field2" :error="form.errors.delivery_platform_field2">
                  {{ form.delivery_platform.field2_name }}
                </FormInput>
              </div>

              <div class="sm:col-span-3" v-if="form.id && form.delivery_platform && form.delivery_platform.field3_name">
                <FormInput v-model="form.delivery_platform_field3" :error="form.errors.delivery_platform_field3">
                  {{ form.delivery_platform.field3_name }}
                </FormInput>
              </div>

              <div class="sm:col-span-3" v-if="form.id && form.delivery_platform && form.delivery_platform.field4_name">
                <FormInput v-model="form.delivery_platform_field4" :error="form.errors.delivery_platform_field4">
                  {{ form.delivery_platform.field4_name }}
                </FormInput>
              </div>

              <div class="sm:col-span-3" v-if="form.id && form.delivery_platform && form.delivery_platform.default_access_method == 'oauth'">
                <FormInput v-model="form.delivery_platform_oauth_client_id" :error="form.errors.delivery_platform_oauth_client_id">
                  Oauth Client ID
                </FormInput>
              </div>

              <div class="sm:col-span-3" v-if="form.id && form.delivery_platform && form.delivery_platform.default_access_method == 'oauth'">
                <FormInput v-model="form.delivery_platform_oauth_client_secret" :error="form.errors.delivery_platform_oauth_client_secret">
                  Oauth Client Secret
                </FormInput>
              </div>

              <div class="sm:col-span-3" v-if="form.id && form.delivery_platform">
                <FormInput v-model="form.endpoint" :error="form.errors.endpoint">
                  Endpoint
                </FormInput>
              </div>

              <div class="sm:col-span-3" v-if="form.id && form.delivery_platform">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Type
                  <span class="text-red-500">*</span>
                </label>
                <MultiSelect
                  v-model="form.delivery_platform_type"
                  :options="deliveryPlatformOperatorTypes"
                  trackBy="id"
                  valueProp="id"
                  label="id"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                  ref="multiselect"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.delivery_platform_type">
                  {{ form.errors.delivery_platform_type }}
                </div>
              </div>
              <div class="sm:col-span-6 flex justify-end pt-2" v-if="form.id && form.delivery_platform">
                <Button
                type="button"
                @click="storeDeliveryPlatformOperator()"
                class="bg-green-500 hover:bg-green-600 text-white"
                :class="[
                  !form.delivery_platform ||
                  !form.delivery_platform_type ||
                  !form.delivery_platform_field1 ?
                  'opacity-50 cursor-not-allowed' : ''
                  ]"
                :disabled="!form.delivery_platform || !form.delivery_platform_type || !form.delivery_platform_field1 || !permissions.includes('update operators')"
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
                            Name
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Type
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Merchant ID
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Action
                          </th>
                        </tr>
                      </thead>
                      <tbody class="bg-white">
                        <tr v-for="(deliveryPlatformOperator, deliveryPlatformOperatorIndex) in deliveryPlatformOperators" :key="deliveryPlatformOperator.id" :class="deliveryPlatformOperatorIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                            {{ deliveryPlatformOperatorIndex + 1 }}
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                            {{ deliveryPlatformOperator.deliveryPlatform.name }}
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                            {{ deliveryPlatformOperator.type }}
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                            {{ deliveryPlatformOperator.field1 }}
                          </td>
                          <td class="whitespace-nowrap py-4 text-sm text-center">
                            <Button
                              class="bg-red-400 hover:bg-red-500 text-white"
                              @click.prevent="deleteDeliveryPlatformOperator(deliveryPlatformOperator)"
                            >
                              <BackspaceIcon class="w-4 h-4"></BackspaceIcon>
                            </Button>
                          </td>
                        </tr>
                        <tr v-if="!deliveryPlatformOperators.length">
                          <td colspan="5" class="whitespace-nowrap py-4 text-sm font-medium text-center">
                            No Result Found
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>


              <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3" v-if="form.id">
                <div class="relative">
                  <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-gray-300"></div>
                  </div>
                  <div class="relative flex justify-center">
                    <span class="px-3 bg-white text-lg font-medium text-gray-900"> Access Vending Machine(s) </span>
                  </div>
                </div>
              </div>

              <div class="sm:col-span-5" v-if="form.id">
                <!-- <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Vending Machine to Bind
                </label>
                <MultiSelect
                  v-model="form.vend_id"
                  :options="unbindedVendOptions"
                  trackBy="id"
                  valueProp="id"
                  label="full_name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                  ref="multiselect"
                >
                </MultiSelect> -->
                <SearchVendCodeWithOperatorInput v-model="form.vend_id" @selected="onVendCodeSelected" required="true" :error="form.errors.code">
                  Vending Machine to Bind
                </SearchVendCodeWithOperatorInput>
                <div class="text-sm text-red-600" v-if="form.errors.vend_id">
                  {{ form.errors.vend_id }}
                </div>
              </div>

              <div class="sm:col-span-1" v-if="form.id">
                <Button
                type="button"
                @click="storeOperatorVend()"
                class="bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6"
                :class="[!form.vend_id ? 'opacity-50 cursor-not-allowed' : '']"
                :disabled="!form.vend_id && !permissions.includes('update operators')"
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
                            Vend ID
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Name
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Action
                          </th>
                        </tr>
                      </thead>
                      <tbody class="bg-white">
                        <tr v-for="(vend, vendIndex) in vends" :key="vend.id" :class="vendIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                            {{ vendIndex + 1 }}
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                            {{ vend.code }}
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-left">
                            <!-- <span v-if="vend.latestVendBinding && vend.latestVendBinding.customer">
                              {{ vend.latestVendBinding.customer.code }} <br>
                              {{ vend.latestVendBinding.customer.name }}
                            </span>
                            <span v-else>
                              {{ vend.name }}
                            </span> -->
                            <span v-if="vend.latestVendBinding && vend.latestVendBinding.customer && vend.latestVendBinding.customer.virtual_customer_code">
                                <span v-if="permissions.includes('admin-access vends')">
                                  {{ vend.latestVendBinding.customer.virtual_customer_prefix }}-{{ vend.latestVendBinding.customer.virtual_customer_code }}
                                  <br>
                                  {{ vend.latestVendBinding.customer.name }}
                                </span>
                            </span>
                            <span v-else>
                                {{ vend.name }}
                            </span>
                          </td>
                          <td class="whitespace-nowrap py-4 text-sm text-center">
                            <Button
                              class="bg-red-400 hover:bg-red-500 text-white"
                              @click.prevent="deleteOperatorVend(vend)"
                              v-if="permissions.includes('update operators')"
                            >
                              <BackspaceIcon class="w-4 h-4"></BackspaceIcon>
                            </Button>
                          </td>
                        </tr>
                        <tr v-if="!vends.length">
                          <td colspan="4" class="whitespace-nowrap py-4 text-sm font-medium text-red-600 text-center">
                            No Binding = Access to All
                          </td>
                        </tr>
                      </tbody>
                    </table>
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
import Button from '@/Components/Button.vue';
import FormInput from '@/Components/FormInput.vue';
import FormTextarea from '@/Components/FormTextarea.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import SearchVendCodeWithOperatorInput from '@/Components/SearchVendCodeWithOperatorInput.vue';
import { ArrowUturnLeftIcon, BackspaceIcon, CheckCircleIcon, PauseCircleIcon, PlusCircleIcon, PlayIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    countries: [Array, Object],
    operator: Object,
    timezones: [Array, Object],
    type: String,
    countryDeliveryPlatforms: [Array, Object],
    countryPaymentGateways: [Array, Object],
    deliveryPlatformOperatorTypes: [Array, Object],
    operatorPaymentGatewayTypes: [Array, Object],
    permissions: [Array, Object],
  })

  const countryOptions = ref([])
  const countryDeliveryPlatformOptions = ref([])
  const countryPaymentGatewayOptions = ref([])
  const deliveryPlatformOperators = ref([])
  const deliveryPlatformOperatorTypes = ref([])
  const form = ref(
    useForm(getDefaultForm())
  )
  const loading = ref(false)

  const operatorPaymentGateways = ref([])
  const operatorPaymentGatewayTypes = ref([])
  const permissions = usePage().props.auth.permissions
  const timezoneOptions = ref([])
  const typeName = ref('')
  const vends = ref([])

onMounted(() => {
    if(props.type == 'create') {
        typeName.value = 'Create New'
    } else {
        typeName.value = 'Edit'
    }
    countryDeliveryPlatformOptions.value = props.countryDeliveryPlatforms.data
    countryPaymentGatewayOptions.value = props.countryPaymentGateways.data
    countryOptions.value = props.countries.data
    deliveryPlatformOperators.value = props.operator ? props.operator.data.deliveryPlatformOperators : null
    deliveryPlatformOperatorTypes.value = props.deliveryPlatformOperatorTypes
    form.value = props.operator ? useForm(props.operator.data) : useForm(getDefaultForm())
    timezoneOptions.value = props.timezones.map((timezone, index) => {return {id: index, name: timezone}})
    operatorPaymentGatewayTypes.value = props.operatorPaymentGatewayTypes
    operatorPaymentGateways.value = props.operator ? props.operator.data.operatorPaymentGateways : null
    vends.value = props.operator ? props.operator.data.vends : null
})

function getDefaultForm() {
  return {
    id: '',
    code: '',
    name: '',
    gst_vat_rate: '',
    country_id: '',
    delivery_platform_id: '',
    delivery_platform_type: '',
    delivery_platform_field1: '',
    delivery_platform_field2: '',
    delivery_platform_field3: '',
    delivery_platform_field4: '',
    delivery_platform_oauth_client_id: '',
    delivery_platform_oauth_client_secret: '',
    endpoint: '',
    payment_gateway_id: '',
    payment_gateway_type: '',
    payment_gateway_key1: '',
    payment_gateway_key2: '',
    payment_gateway_key3: '',
    timezone: '',
    remarks: '',
    vend_id: '',
  }
}

function deleteDeliveryPlatformOperator(model) {
  const approval = confirm('Are you sure to delete this entry?');
  if (!approval) {
      return;
  }
  router.delete('/delivery-platform-operators/' + model.id, {
      preserveState: false,
      preserveScroll: true,
      replace: true,
  })
}

function deleteOperatorPaymentGateway(model) {
  const approval = confirm('Are you sure to delete this entry?');
  if (!approval) {
      return;
  }
  router.delete('/operator-payment-gateways/' + model.id, {
      preserveState: false,
      preserveScroll: true,
      replace: true,
  })
}

function deleteOperatorVend(model) {
  const approval = confirm('Are you sure to delete this entry?');
  if (!approval) {
      return;
  }
  router.post('/operators/unbind-vend', {
    vend_id: model.id,
    operator_id: form.value.id,
  },{
      preserveState: false,
      preserveScroll: true,
      replace: true,
  })
}

function storeDeliveryPlatformOperator() {
  router.post(
    '/delivery-platform-operators/operator/'+ form.value.id +'/store', {
        delivery_platform_id: form.value.delivery_platform.id,
        field1: form.value.delivery_platform_field1,
        field2: form.value.delivery_platform_field2,
        field3: form.value.delivery_platform_field3,
        field4: form.value.delivery_platform_field4,
        oauth_client_id: form.value.delivery_platform_oauth_client_id,
        oauth_client_secret: form.value.delivery_platform_oauth_client_secret,
        type: form.value.delivery_platform_type.id,
    }, {
      preserveState: false,
      preserveScroll: true,
      replace: true,
    },
  )
}

function storeOperatorPaymentGateway() {
  router.post(
    '/operator-payment-gateways/operator/'+ form.value.id +'/store', {
      payment_gateway_id: form.value.payment_gateway.id,
      key1: form.value.payment_gateway_key1,
      key2: form.value.payment_gateway_key2,
      key3: form.value.payment_gateway_key3,
      type: form.value.payment_gateway_type.id,
    }, {
      preserveState: false,
      preserveScroll: true,
      replace: true,
    },
  )
}

function storeOperatorVend() {
  router.post(
    '/operators/bind-vend', {
      code: form.value.vend_id,
      operator_id: form.value.id,
    }, {
      preserveState: false,
      preserveScroll: true,
      replace: true,
    },
  )
}

function onVendCodeSelected(vend) {
  form.value.vend_id = vend.vend_code
}

function submit() {
  form.value.clearErrors()
  if(props.type === 'update') {
    form.value
      .transform((data) => ({
        ...data,
        timezone: data.timezone ? data.timezone.name : null,
        country_id: data.country_id ? data.country_id.id : null,
      }))
      .post('/operators/' + form.value.id + '/update', {
      preserveState: true,
      replace: true,
    })
  }
}

</script>