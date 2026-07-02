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
              <div class="sm:col-span-6">
                <label class="flex justify-start text-sm font-medium text-gray-700">
                  Operator Logo
                </label>
                <div class="mt-2 flex flex-col md:flex-row md:items-center md:space-x-4 space-y-3 md:space-y-0">
                  <div class="flex h-24 w-36 items-center justify-center overflow-hidden rounded-md border border-dashed border-gray-300 bg-white">
                    <img v-if="displayedLogoUrl" :src="displayedLogoUrl" alt="Operator logo preview" class="max-h-24 max-w-full object-contain">
                    <span v-else class="text-xs text-gray-400">
                      No logo
                    </span>
                  </div>
                  <div class="flex flex-col space-y-2">
                    <input
                      ref="logoInput"
                      type="file"
                      accept="image/*"
                      @change="onLogoSelected"
                      :disabled="!permissions.includes('update operators')"
                      class="rounded-md border border-gray-300 bg-white py-2 px-3 text-sm font-medium leading-4 text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                    />
                    <progress v-if="form.progress" :value="form.progress.percentage" max="100">
                      {{ form.progress.percentage }}%
                    </progress>
                    <Button
                      v-if="canShowRemovalButton"
                      type="button"
                      class="w-max bg-red-500 text-white hover:bg-red-600"
                      @click="toggleLogoRemoval"
                    >
                      {{ removalButtonLabel }}
                    </Button>
                    <span class="text-sm text-gray-500">
                      * Image file, max 400kb
                    </span>
                    <div class="text-sm text-red-600" v-if="form.errors.logo">
                      {{ form.errors.logo }}
                    </div>
                  </div>
                </div>
                <p class="mt-2 text-xs text-gray-500">
                  Custom logo override is
                  <span :class="props.operatorCanOverrideLogo ? 'text-green-600 font-medium' : 'text-yellow-600 font-medium'">
                    {{ props.operatorCanOverrideLogo ? 'enabled' : 'disabled' }}
                  </span>
                  for this operator. Update the system settings to change this behaviour.
                </p>
                <p
                  v-if="initialLogoUrl && !props.operatorCanOverrideLogo"
                  class="mt-1 text-xs text-yellow-600"
                >
                  Note: A custom logo is stored but suppressed because override is disabled.
                </p>
              </div>
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
              <div class="sm:col-span-2">
                <label class="flex justify-start text-sm font-medium text-gray-700">
                  Status
                </label>
                <div class="mt-2 flex items-center space-x-3">
                  <button
                    type="button"
                    role="switch"
                    :aria-checked="form.is_active"
                    @click="form.is_active = !form.is_active"
                    :disabled="!permissions.includes('update operators')"
                    :class="[
                      form.is_active ? 'bg-green-500' : 'bg-gray-300',
                      'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed'
                    ]"
                  >
                    <span
                      :class="[
                        form.is_active ? 'translate-x-5' : 'translate-x-0',
                        'pointer-events-none inline-block h-6 w-6 transform rounded-full bg-white shadow ring-0 transition'
                      ]"
                    ></span>
                  </button>
                  <span class="text-sm font-medium" :class="form.is_active ? 'text-green-700' : 'text-red-700'">
                    {{ form.is_active ? 'Active' : 'Inactive' }}
                  </span>
                </div>
                <p class="mt-1 text-xs text-gray-500">
                  Inactive operators are hidden across the app instead of being deleted.
                </p>
              </div>
              <div class="sm:col-span-6">
                <FormTextarea v-model="form.remarks" :error="form.errors.remarks">
                    Remarks
                  </FormTextarea>
              </div>

              <!-- Refund bulk transfer (CIMB) — originator account for the bank file header -->
              <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
                <div class="relative">
                  <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-gray-300"></div>
                  </div>
                  <div class="relative flex justify-center">
                    <span class="px-3 bg-white text-lg font-medium text-gray-900"> Refund Bulk Transfer (CIMB) </span>
                  </div>
                </div>
              </div>
              <div class="sm:col-span-6">
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                  <p class="text-xs text-gray-500 mb-3">
                    Originating account used in the header of the CIMB BizChannel bulk transaction file (Refund Requests page export).
                  </p>
                  <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">
                    <div class="sm:col-span-3">
                      <FormInput v-model="form.bank_account_no" :error="form.errors.bank_account_no">
                        CIMB Bank Account No.
                      </FormInput>
                    </div>
                    <div class="sm:col-span-3">
                      <FormInput v-model="form.bank_account_name" :error="form.errors.bank_account_name">
                        Bank Account Name
                        <span class="text-[9px] ml-1">
                            (Registered Company Name at Bank)
                        </span>
                      </FormInput>
                    </div>
                  </div>
                </div>
              </div>
              <div class="sm:col-span-6">
                <label class="flex justify-start text-sm font-medium text-gray-700">
                  Machine Email Alert User(s)
                </label>
                <MultiSelect
                  v-model="form.email_recipients"
                  :options="emailUserOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                  mode="tags"
                />
                <div class="text-sm text-red-600" v-if="form.errors.email_recipients">
                  {{ form.errors.email_recipients }}
                </div>
                <label class="flex justify-start text-sm font-medium text-blue-500 pt-2">
                  ** Please make sure the user has a valid email address, the binded user will receive channel error alert email, machine offline alerts, power restoration alerts, and no transactions alerts. **
                </label>
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
                            Public Key
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
                    <span class="px-3 bg-white text-lg font-medium text-gray-900"> Callbacks </span>
                  </div>
                </div>
              </div>

               <div class="sm:col-span-6 mt-3" v-if="form.id">
                  <FormInput v-model="form.transaction_callback_url" :error="form.errors.transaction_callback_url" placeholder="https://example.com/webhook/transaction">
                      Transaction Callback URL
                  </FormInput>
                  <p class="text-xs text-gray-500 mt-2">
                      Triggered when a new transaction is uploaded (Event: transaction_upload).
                  </p>
               </div>

               <div class="sm:col-span-6 mt-3" v-if="form.id">
                  <FormInput v-model="form.alert_callback_url" :error="form.errors.alert_callback_url" placeholder="https://example.com/webhook/alert">
                      Alert Callback URL
                  </FormInput>
                  <p class="text-xs text-gray-500 mt-2">
                      Triggered for system alerts: Machine Offline, Power Restored, and Channel Errors.
                  </p>
               </div>


              <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3" v-if="form.id">
                <div class="relative">
                  <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-gray-300"></div>
                  </div>
                  <div class="relative flex justify-center">
                    <span class="px-3 bg-white text-lg font-medium text-gray-900"> Machine(s) </span>
                  </div>
                </div>
              </div>

              <div class="sm:col-span-5" v-if="form.id">
                <SearchVendCodeWithOperatorInput v-model="form.vend_id" @selected="selected" required="true" :error="form.errors.vend_id">
                  Machine to Bind
                </SearchVendCodeWithOperatorInput>
                <div class="text-sm text-red-600" v-if="form.errors.vend_id">
                  {{ form.errors.vend_id }}
                </div>
              </div>

              <div class="sm:col-span-1" v-if="form.id">
                <Button
                type="button"
                @click="bindOperatorVend()"
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
                        <tr class="bg-gray-200">
                          <th scope="col" colspan="4" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            <span class="flex space-x-2">
                              <SearchInput placeholderStr="Machine ID" v-model="filters.vend_code" @input="onSearchFilterUpdated()">
                                  Machine ID
                              </SearchInput>
                              <SearchInput placeholderStr="Cust Name" v-model="filters.name" @input="onSearchFilterUpdated()">
                                  Cust Name
                              </SearchInput>
                              <SearchInput placeholderStr="Prefix Code" v-model="filters.prefix_code" @input="onSearchFilterUpdated()">
                                  Prefix Code
                              </SearchInput>
                              <div class="w-1/5">
                                <label for="text" class="block text-sm font-medium text-gray-700">
                                    Has Active VM?
                                </label>
                                <MultiSelect
                                    v-model="filters.is_active_vend"
                                    :options="booleanOptions"
                                    trackBy="id"
                                    valueProp="id"
                                    label="value"
                                    placeholder="Select"
                                    open-direction="bottom"
                                    class="mt-1"
                                    @selected="onSearchFilterUpdated()"
                                >
                                </MultiSelect>
                              </div>
                            </span>
                          </th>
                        </tr>
                        <tr>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            #
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Machine ID
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Site
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
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-left">
                            {{ vend ? vend.code : null }}
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left">
                            <span v-if="vend.customer && vend.customer.person_id">
                                {{ vend.customer.virtual_customer_code }} ({{ vend.customer.virtual_customer_prefix }})
                                <br>
                                {{ vend.customer.name }}
                            </span>
                            <span v-else>
                              {{ vend.customer && vend.customer.name ? vend.customer.name : ''}}
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
                          <td colspan="4" class="whitespace-nowrap py-4 text-sm font-medium text-black text-center">
                            No Result Found
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
import SearchInput from '@/Components/SearchInput.vue';
import FormTextarea from '@/Components/FormTextarea.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import SearchVendCodeWithOperatorInput from '@/Components/SearchVendCodeWithOperatorInput.vue';
import { ArrowUturnLeftIcon, BackspaceIcon, CheckCircleIcon, PauseCircleIcon, PlusCircleIcon, PlayIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted, computed, onBeforeUnmount, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";

const props = defineProps({
    countries: [Array, Object],
    emailUserOptions: Object,
    operator: Object,
    timezones: [Array, Object],
    type: String,
    countryDeliveryPlatforms: [Array, Object],
    countryPaymentGateways: [Array, Object],
    deliveryPlatformOperatorTypes: [Array, Object],
    operatorPaymentGatewayTypes: [Array, Object],
    permissions: [Array, Object],
    operatorCanOverrideLogo: Boolean,
  })

  const booleanOptions = ref([
    {id: 'all', value: 'All'},
    {id: 'true', value: 'Yes'},
    {id: 'false', value: 'No'},
  ])
  const countryOptions = ref([])
  const countryDeliveryPlatformOptions = ref([])
  const countryPaymentGatewayOptions = ref([])
  // const customers = ref([])
  const deliveryPlatformOperators = ref([])
  const deliveryPlatformOperatorTypes = ref([])
  const form = ref(
    useForm(getDefaultForm())
  )
  const filters = ref({
    vend_code: '',
    name: '',
    is_active_vend: booleanOptions.value[1],
    prefix_code: ''
  })
  const emailUserOptions = ref([])
  const loading = ref(false)

  const page = usePage()
  const operatorPaymentGateways = ref([])
  const operatorPaymentGatewayTypes = ref([])
  const permissions = page.props.auth.permissions
  const defaultLogoUrl = page.props.defaultLogoUrl || ''
  const timezoneOptions = ref([])
  const typeName = ref('')
  const vends = ref([])
  const logoInput = ref(null)
  const initialLogoUrl = ref(null)
  const tempLogoPreview = ref(null)
  const toast = useToast()
  // const operatorCallbacks = ref([])

  const displayedLogoUrl = computed(() => {
    if (tempLogoPreview.value) {
      return tempLogoPreview.value
    }
    if (form.value.logo_remove) {
      return defaultLogoUrl
    }
    return initialLogoUrl.value || defaultLogoUrl
  })

  const canShowRemovalButton = computed(() => {
    return permissions.includes('update operators') && (Boolean(initialLogoUrl.value) || Boolean(tempLogoPreview.value))
  })

  const removalButtonLabel = computed(() => form.value.logo_remove ? 'Keep Existing Logo' : 'Use Default Logo')

  const isEmail = (v) => /[\w.+-]+@[\w.-]+\.[a-zA-Z]{2,}/.test(String(v||''));
  const toEmailObj = (v) => {
    if (typeof v === 'string') return { email: v.toLowerCase().trim(), label: '' };
    return { email: String(v.email||'').toLowerCase().trim(), label: String(v.label||'').trim() };
  };

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
    emailUserOptions.value = props.emailUserOptions.data
      .map(u => ({
        ...u,
        // show: "Full Name (email)" or "Full Name (no email)"
        name: `${u.name}${u.email ? ` (${u.email})` : ' no email'}`
      }));

    const existingUserIds = props.operator?.data?.email_user_ids ?? [];
    const existingCustoms = props.operator?.data?.email_customs ?? [];

    const userOptionsById = new Map(emailUserOptions.value.map(u => [u.id, u]));
    const mixedRecipients = [
      ...existingUserIds
        .map(id => userOptionsById.get(id))
        .filter(Boolean),
      ...existingCustoms.map(toEmailObj),
    ];

    form.value = props.operator
      ? useForm({
          ...getDefaultForm(),
          ...props.operator.data,
          email_recipients: Array.isArray(mixedRecipients) ? mixedRecipients : [],
          logo: null,
          logo_remove: false,
        })
      : useForm(getDefaultForm());

    // hydrate new fields
    if (props.operator && props.operator.data.operatorCallbacks) {
        const callbacks = props.operator.data.operatorCallbacks;
        const txnCb = callbacks.find(c => c.code === 'transaction_upload');
        const alertCb = callbacks.find(c => ['channel_error_alert', 'vend_offline_alert', 'vend_power_restored_alert'].includes(c.code));

        if (txnCb) form.value.transaction_callback_url = txnCb.url;
        if (alertCb) form.value.alert_callback_url = alertCb.url;
    }

    // operatorCallbacks.value = props.operator?.data?.operatorCallbacks || [];
});



    timezoneOptions.value = props.timezones.map((timezone, index) => {return {id: index, name: timezone}})
    operatorPaymentGatewayTypes.value = props.operatorPaymentGatewayTypes
    operatorPaymentGateways.value = props.operator ? props.operator.data.operatorPaymentGateways : null
    vends.value = props.operator ? props.operator.data.vends : null
    // customers.value = props.operator ? props.operator.data.customers : null

    hydrateLogoState()



function getDefaultForm() {
  return {
    id: '',
    code: '',
    name: '',
    gst_vat_rate: '',
    bank_account_no: '',
    bank_account_name: '',
    is_active: true,
    country_id: '',
    delivery_platform_id: '',
    delivery_platform_type: '',
    delivery_platform_field1: '',
    delivery_platform_field2: '',
    delivery_platform_field3: '',
    delivery_platform_field4: '',
    delivery_platform_oauth_client_id: '',
    delivery_platform_oauth_client_secret: '',
    email_recipients: [],
    endpoint: '',
    payment_gateway_id: '',
    payment_gateway_type: '',
    payment_gateway_key1: '',
    payment_gateway_key2: '',
    payment_gateway_key3: '',
    timezone: '',
    remarks: '',
    vend_id: '',
    vend_id_value: '',
    logo: null,
    logo: null,
    logo_remove: false,
    transaction_callback_url: '',
    alert_callback_url: '',
  }
}

function hydrateLogoState() {
  const operatorData = props.operator?.data ?? null
  const latestUrl = operatorData?.logo_url || operatorData?.logo?.full_url || null
  const previousUrl = initialLogoUrl.value

  initialLogoUrl.value = latestUrl

  if (previousUrl === latestUrl && !tempLogoPreview.value) {
    return
  }

  if (tempLogoPreview.value) {
    URL.revokeObjectURL(tempLogoPreview.value)
    tempLogoPreview.value = null
  }

  if (form.value) {
    form.value.logo = null
    form.value.logo_remove = false
  }

  if (logoInput.value) {
    logoInput.value.value = ''
  }
}

function onLogoSelected(event) {
  const [file] = event.target?.files ?? []
  if (!file) {
    return
  }

  if (tempLogoPreview.value) {
    URL.revokeObjectURL(tempLogoPreview.value)
  }

  tempLogoPreview.value = URL.createObjectURL(file)
  form.value.logo = file
  form.value.logo_remove = false
}

function toggleLogoRemoval() {
  const isRemoving = !form.value.logo_remove

  if (tempLogoPreview.value) {
    URL.revokeObjectURL(tempLogoPreview.value)
    tempLogoPreview.value = null
  }

  if (logoInput.value) {
    logoInput.value.value = ''
  }

  form.value.logo = null
  form.value.logo_remove = isRemoving ? true : false
}

watch(
  () => props.operator,
  () => {
    hydrateLogoState()
  }
)

onBeforeUnmount(() => {
  if (tempLogoPreview.value) {
    URL.revokeObjectURL(tempLogoPreview.value)
  }
})

function deleteDeliveryPlatformOperator(model) {
  const approval = confirm('Are you sure to delete this entry?');
  if (!approval) {
      return;
  }
  router.delete('/delivery-platform-operators/' + model.id, {
      onSuccess: () => {
        toast.success("Delivery platform operator deleted successfully", { timeout: 3000 })
      },
      onError: () => {
        toast.error("Failed to delete delivery platform operator", { timeout: 3000 })
      },
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
      onSuccess: () => {
        toast.success("Operator payment gateway deleted successfully", { timeout: 3000 })
      },
      onError: () => {
        toast.error("Failed to delete operator payment gateway", { timeout: 3000 })
      },
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
      onSuccess: () => {
        toast.success("Machine unbound from operator successfully", { timeout: 3000 })
      },
      onError: () => {
        toast.error("Failed to unbind machine from operator", { timeout: 3000 })
      },
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
      onSuccess: () => {
        toast.success("Delivery platform operator added successfully", { timeout: 3000 })
      },
      onError: () => {
        toast.error("Failed to add delivery platform operator", { timeout: 3000 })
      },
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
      onSuccess: () => {
        toast.success("Operator payment gateway added successfully", { timeout: 3000 })
      },
      onError: () => {
        toast.error("Failed to add operator payment gateway", { timeout: 3000 })
      },
      preserveState: false,
      preserveScroll: true,
      replace: true,
    },
  )
}

function bindOperatorVend() {
  router.post(
    '/operators/bind-vend', {
      vend_id: form.value.vend_id_value,
      operator_id: form.value.id,
    }, {
      onSuccess: () => {
        toast.success("Machine bound to operator successfully", { timeout: 3000 })
      },
      onError: () => {
        toast.error("Failed to bind machine to operator", { timeout: 3000 })
      },
      preserveState: false,
      preserveScroll: true,
      replace: true,
    },
  )
}

function onSearchFilterUpdated() {
  router.reload({
    only: ['operator'],
    data: {
      ...filters.value,
      is_active_vend: filters.value.is_active_vend.id,
    },
    replace: true,
    preserveState: true,
    onSuccess: page => {
      // customers.value = props.operator ? props.operator.data.customers : null
      vends.value = props.operator ? props.operator.data.vends : null
    }
  })

  // router.get('/operators/' + form.value.id + '/edit', {
  //     ...filters.value,
  // }, {
  //     preserveState: true,
  //     replace: true,
  //     onFinish: visit => {
  //         now.value = moment().format('HH:mm:ss')
  //     },
  // })
}

function selected(obj) {
  form.value.vend_id = obj.code + ' - ' + obj.customer.virtual_customer_code + ' (' + obj.customer.virtual_customer_prefix + ') ' + obj.customer.name
  form.value.vend_id_value = obj.id
}

function resetFilters() {
  router.get('/operators/' + form.value.id + '/edit', filters.value, {
    onSuccess: page => {
      // customers.value = props.operator ? props.operator.data.customers : null
      vends.value = props.operator ? props.operator.data.vends : null
    }
  })
}

function submit() {
  form.value.clearErrors()
  if(props.type === 'update') {
    form.value
      .transform((data) => {
        const items = Array.isArray(data.email_recipients) ? data.email_recipients : [];

        const email_user_ids = new Set();
        const email_customs = [];

        items.forEach((it) => {
          // If it's a selected user option (object with id)
          if (it && typeof it === 'object' && 'id' in it && Number(it.id)) {
            email_user_ids.add(Number(it.id));
            return;
          }
          // If it's a raw id (edge case)
          if ((typeof it === 'string' || typeof it === 'number') && String(it).match(/^\d+$/)) {
            email_user_ids.add(Number(it));
            return;
          }
          // If it's an email object or email string (tag)
          if (it && typeof it === 'object' && it.email) {
            const obj = toEmailObj(it);
            if (isEmail(obj.email)) email_customs.push(obj);
            return;
          }
          if (typeof it === 'string' && isEmail(it)) {
            email_customs.push(toEmailObj(it));
          }
        });

        // de-dupe custom emails
        const customsDeduped = email_customs.reduce((acc, cur) => {
          if (!acc.find(x => x.email === cur.email)) acc.push(cur);
          return acc;
        }, []);

        return {
          ...data,
          timezone: data.timezone ? data.timezone.name : null,
          country_id: data.country_id ? data.country_id.id : null,
          // send both to backend:
          email_user_ids: Array.from(email_user_ids),
          email_customs: customsDeduped,
          logo: data.logo ?? null,
          logo_remove: Boolean(data.logo_remove),
        };
      })
      .post('/operators/' + form.value.id + '/update', {
      preserveState: true,
      replace: true,
    })
  }
}

</script>
