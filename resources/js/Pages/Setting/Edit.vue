<template>
  <Head title="VM Edit" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Editing Machine
        {{ vend.code }}
      </h2>
    </template>

     <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
        <div class="shadow-sm ring-1 ring-black ring-opacity-5 p-5 mb-3">
          <form @submit.prevent="submit" id="submit">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-6 pb-5 mb-3">
            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-start">
                  <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> Vending Machine </span>
                </div>
              </div>
            </div>

            <div class="sm:col-span-6 flex space-x-1">
              <div
                  class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit"
                  :class="[vend.is_testing ? 'bg-gray-300' : (vend.is_active ? 'bg-green-300' : 'bg-red-300')]"
              >
                <span v-if="vend.is_testing">
                  Factory
                </span>
                <span v-if="!vend.is_testing && vend.is_active">
                  Active
                </span>
                <span v-if="!vend.is_testing && !vend.is_active">
                  Not Active
                </span>
              </div>
              <span v-if="vend.delivery_product_mapping_vends" v-for="(deliveryProductMappingVend, index) in vend.delivery_product_mapping_vends">
                <div
                    class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit text-gray-800 bg-green-400"
                    v-if="deliveryProductMappingVend.delivery_product_mapping && deliveryProductMappingVend.delivery_product_mapping.delivery_platform_operator && deliveryProductMappingVend.delivery_product_mapping.delivery_platform_operator.delivery_platform"
                >
                  {{ deliveryProductMappingVend.delivery_product_mapping.delivery_platform_operator.delivery_platform.name }}
                </div>
              </span>
            </div>

            <div class="sm:col-span-2" v-if="vend">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Machine ID#
              </label>
              <div class="mt-1">
                <input
                  type="text"
                  class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                  :value="vend ? vend.code : ''"
                  disabled
                />
              </div>
            </div>
            <div class="sm:col-span-4">
              <FormInput v-model="form.label_name" :error="form.errors.label_name">
                Label
              </FormInput>
            </div>

            <div class="sm:col-span-2">
              <DatePicker v-model="form.begin_date" :error="form.errors.begin_date" @input="onDateFromChanged()"
              v-if="permissions.includes('update vend-settings')">
                Begin Date
              </DatePicker>
            </div>
            <div class="sm:col-span-2">
              <DatePicker v-model="form.termination_date" :error="form.errors.termination_date" :minDate="form.begin_date"
              v-if="permissions.includes('update vend-settings')">
                Retired Date
              </DatePicker>
            </div>
            <!-- <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Is Factory?
              </label>
              <MultiSelect
                v-model="form.is_testing"
                :options="booleanStrictOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors['customer.is_testing']">
                {{ form.errors['customer.is_testing'] }}
              </div>
            </div>
            <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Is Active? (Vending Machine)
              </label>
              <MultiSelect
                v-model="form.is_active"
                :options="booleanStrictOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors['is_active']">
                {{ form.errors['is_active'] }}
              </div>
            </div> -->
            <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Status
              </label>
              <MultiSelect
                v-model="form.status"
                :options="statusOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors['customer.is_testing']">
                {{ form.errors['customer.is_testing'] }}
              </div>
            </div>
            <div class="sm:col-span-3">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Model
                  <span class="text-red-500">
                    *
                  </span>
                </label>
                <MultiSelect
                  v-model="form.vend_model_id"
                  :options="vendModelOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.simcard_id">
                  {{ form.errors.vend_model_id }}
                </div>
            </div>
            <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                <div class="flex space-x-2 items-center">
                    <span>
                      Serial Number
                    </span>
                    <span v-if="form.vend_serial_number_id && form.vend_serial_number_id.id">
                      <a class="text-blue-700" target="_blank" :href="'/vend-serial-numbers?id=' + form.vend_serial_number_id.id">
                        <ArrowTopRightOnSquareIcon class="w-4 h-4"></ArrowTopRightOnSquareIcon>
                      </a>
                    </span>
                  </div>
                </label>
                <MultiSelect
                  v-model="form.vend_serial_number_id"
                  :options="vendSerialNumberOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.key_id">
                  {{ form.errors.key_id }}
                </div>
            </div>
            <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Machine Key
                </label>
                <MultiSelect
                  v-model="form.key_id"
                  :options="keyOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.key_id">
                  {{ form.errors.key_id }}
                </div>
            </div>
            <div class="sm:col-span-4">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Operator
                  <span class="text-red-500">
                    *
                  </span>
                </label>
                <MultiSelect
                  v-model="form.operator_id"
                  :options="operatorOptions"
                  trackBy="id"
                  valueProp="id"
                  label="full_name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.operator_id">
                  {{ form.errors.operator_id }}
                </div>
            </div>
            <div class="sm:col-span-6 text-blue-600 text-xs">
              ** If change Operator, the Binded Customer's Operator will be changed as well
            </div>

            <hr class="sm:col-span-6">
            <div class="sm:col-span-2">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  <div class="flex space-x-2 items-center">
                    Setting Chart
                    <span class="text-red-500">
                      *
                    </span>
                    <span v-if="form.vend_config_id && form.vend_config_id.id">
                      <a class="text-blue-700" target="_blank" :href="'/vend-configs/' + form.vend_config_id.id + '/edit'">
                        <ArrowTopRightOnSquareIcon class="w-4 h-4"></ArrowTopRightOnSquareIcon>
                      </a>
                    </span>
                  </div>
                </label>
                <MultiSelect
                  v-model="form.vend_config_id"
                  :options="vendConfigOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                  @selected="onVendConfigSelected"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.vend_config_id">
                  {{ form.errors.vend_config_id }}
                </div>
            </div>
            <div class="sm:col-span-2">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Current Version
                </label>
                <MultiSelect
                  v-model="form.vend_vend_config_version"
                  :options="versionOptions"
                  trackBy="id"
                  valueProp="id"
                  label="value"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.vend_vend_config_version">
                  {{ form.errors.vend_vend_config_version }}
                </div>
            </div>
            <div class="sm:col-span-2">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Latest Version
                </label>
                <input
                  type="text"
                  class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed mt-1"
                  :value="form.vend_config_id ? form.vend_config_version : ''"
                  disabled
                />
            </div>
            <div class="sm:col-span-3" v-if="form.vend_config_id">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  <div class="flex space-x-2 items-center">
                    Machine Prefix
                    <span class="text-red-500">
                      *
                    </span>
                    <span v-if="form.vend_prefix_id && form.vend_prefix_id.id">
                      <a class="text-blue-700" target="_blank" :href="'/vend-prefixes?vendPrefixes%5B0%5D=' + form.vend_prefix_id.id">
                        <ArrowTopRightOnSquareIcon class="w-4 h-4"></ArrowTopRightOnSquareIcon>
                      </a>
                    </span>
                  </div>
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
                  @selected="onVendPrefixSelected"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.vend_prefix_id">
                  {{ form.errors.vend_prefix_id }}
                </div>
            </div>

            <div class="sm:col-span-3">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Simcard
                </label>
                <MultiSelect
                  v-model="form.simcard_id"
                  :options="simcardOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.simcard_id">
                  {{ form.errors.simcard_id }}
                </div>
            </div>

            <div class="sm:col-span-3">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Cashless Terminal
                </label>
                <MultiSelect
                  v-model="form.cashless_terminal_id"
                  :options="cashlessTerminalOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.cashless_terminal_id">
                  {{ form.errors.cashless_terminal_id }}
                </div>
            </div>

            <div class="sm:col-span-3">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  <div class="flex space-x-2">
                    Modem Model
                    <span class="text-red-500">
                      *
                    </span>
                    <span v-if="form.modem_type_id && form.modem_type_id.id">
                      <a class="text-blue-700" target="_blank" :href="'/modem-types?id=' + form.modem_type_id.id">
                        <ArrowTopRightOnSquareIcon class="w-4 h-4"></ArrowTopRightOnSquareIcon>
                      </a>
                    </span>
                  </div>
                </label>
                <MultiSelect
                  v-model="form.modem_type_id"
                  :options="modemTypeOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.modem_type_id">
                  {{ form.errors.modem_type_id }}
                </div>
            </div>
            <div class="sm:col-span-3">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  <div class="flex space-x-2">
                    <span>
                      Modem IMEI
                    </span>
                    <span v-if="form.modem_unit_id && form.modem_unit_id.id">
                      <a class="text-blue-700" target="_blank" :href="'/modem-units?id=' + form.modem_unit_id.id">
                        <ArrowTopRightOnSquareIcon class="w-4 h-4"></ArrowTopRightOnSquareIcon>
                      </a>
                    </span>
                  </div>
                </label>
                <MultiSelect
                  v-model="form.modem_unit_id"
                  :options="modemUnitOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.modem_unit_id">
                  {{ form.errors.modem_unit_id }}
                </div>
            </div>
            <div class="sm:col-span-3">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Menu Frame
                  <span class="text-red-500">
                    *
                  </span>
                </label>
                <MultiSelect
                  v-model="form.menu_frame_id"
                  :options="menuFrameOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.modem_frame_id">
                  {{ form.errors.modem_frame_id }}
                </div>
            </div>
            <!-- hardcode form.vend_model_id is equals to claw -->
            <div class="sm:col-span-3" v-if="form.vend_model_id && form.vend_model_id.id == 5">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Claw Machine Body
                  <span class="text-red-500">
                    *
                  </span>
                </label>
                <MultiSelect
                  v-model="form.claw_machine_body_id"
                  :options="clawMachineBodyOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.claw_machine_body_id">
                  {{ form.errors.claw_machine_body_id }}
                </div>
            </div>
            <div class="sm:col-span-3" v-if="form.vend_model_id && form.vend_model_id.id == 5">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Claw Machine Board
                  <span class="text-red-500">
                    *
                  </span>
                </label>
                <MultiSelect
                  v-model="form.claw_machine_board_id"
                  :options="clawMachineBoardOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.claw_machine_board_id">
                  {{ form.errors.claw_machine_board_id }}
                </div>
            </div>
            <div class="sm:col-span-3">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  LCD Monitor
                </label>
                <MultiSelect
                  v-model="form.lcd_monitor_id"
                  :options="lcdMonitorOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.lcd_monitor_id">
                  {{ form.errors.lcd_monitor_id }}
                </div>
            </div>
            <div class="sm:col-span-3">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  LED Matrix Panel
                </label>
                <MultiSelect
                  v-model="form.led_matrix_panel_id"
                  :options="booleanStrictLEDOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.led_matrix_panel_id">
                  {{ form.errors.led_matrix_panel_id }}
                </div>
            </div>
            <div class="sm:col-span-3">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Is Using Server Price?
                </label>
                <MultiSelect
                  v-model="form.is_using_server_price"
                  :options="booleanStrictOptions"
                  trackBy="id"
                  valueProp="id"
                  label="value"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.is_using_server_price">
                  {{ form.errors.is_using_server_price }}
                </div>
            </div>

            <hr class="sm:col-span-6">
            <div class="sm:col-span-3" v-if="form.vend_prefix_id">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  <div class="flex space-x-2 items-center">
                    Product Mapping (current)
                    <span class="text-red-500">
                      *
                    </span>
                    <span v-if="form.product_mapping_id && form.product_mapping_id.id">
                      <a class="text-blue-700" target="_blank" :href="'/product-mappings/' + form.product_mapping_id.id + '/edit'">
                        <ArrowTopRightOnSquareIcon class="w-4 h-4"></ArrowTopRightOnSquareIcon>
                      </a>
                    </span>
                  </div>
                </label>
                <MultiSelect
                  v-model="form.product_mapping_id"
                  :options="productMappingOptions"
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
            <div class="sm:col-span-3" v-if="form.vend_prefix_id">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  <div class="flex space-x-2 items-center">
                    Product Mapping (upcoming)
                    <span class="text-red-500">
                      *
                    </span>
                    <span v-if="form.upcoming_product_mapping_id && form.upcoming_product_mapping_id.id">
                      <a class="text-blue-700" target="_blank" :href="'/product-mappings/' + form.upcoming_product_mapping_id.id + '/edit'">
                        <ArrowTopRightOnSquareIcon class="w-4 h-4"></ArrowTopRightOnSquareIcon>
                      </a>
                    </span>
                  </div>
                </label>
                <MultiSelect
                  v-model="form.upcoming_product_mapping_id"
                  :options="upcomingProductMappingOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.upcoming_product_mapping_id">
                  {{ form.errors.upcoming_product_mapping_id }}
                </div>
            </div>
            <hr class="sm:col-span-6">

            <div class="sm:col-span-6">
              <span class="flex space-x-1">
                <Button
                  type="button"
                  class="bg-green-500 hover:bg-green-600 text-white flex space-x-1"
                  v-if="permissions.includes('update vend-settings')"
                  @click.prevent="saveVend(vend.id)"
                >
                  <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                  <span>
                    Save Vending Machine
                  </span>
                </Button>
                <Button
                  type="button"
                  class="bg-blue-500 hover:bg-blue-600 text-white flex space-x-1"
                  v-if="permissions.includes('update vend-settings') && vend.upcoming_product_mapping_id"
                  @click.prevent="replaceProductMapping(vend.id)"
                >
                  <ArrowPathIcon class="w-4 h-4"></ArrowPathIcon>
                  <span>
                    Replace Current Product Mapping
                  </span>
                </Button>
                <Button
                    class="bg-red-500 hover:bg-red-600 text-white flex space-x-1"
                    @click.prevent="restartVMC(vend.id)"
                  >
                    <ArrowPathIcon class="w-4 h-4"></ArrowPathIcon>
                    <span>
                      Restart VMC
                    </span>
                </Button>
                <Button
                    class="bg-red-500 hover:bg-red-600 text-white flex space-x-1"
                    @click.prevent="restartAPK(vend.id)"
                  >
                    <ArrowPathIcon class="w-4 h-4"></ArrowPathIcon>
                    <span>
                      Restart APK
                    </span>
                </Button>
              </span>
            </div>
            </div>
            <div>

            <div class="relative mb-5">
              <div class="absolute inset-0 flex items-center" aria-hidden="true">
                <div class="w-full border-t border-gray-300"></div>
              </div>
              <div class="relative flex justify-start">
                <span class="px-2 bg-white text-lg font-medium text-gray-900 rounded"> Customer </span>
              </div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-6 pb-5 mb-3">
              <div class="sm:col-span-6">
                <div
                    class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit bg-green-300"
                    v-if="vend.customer && vend.customer.person_id"
                >
                    <div class="flex flex-col">
                      From CMS
                    </div>
                </div>
                <!-- <div
                    class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit bg-gray-300"
                    v-if="!vend.customer || !vend.customer.id"
                >
                    <div class="flex flex-col">
                      No Customer Binding
                    </div>
                </div> -->
              </div>
              <!-- {{ Boolean((form.customer.id && !form.customer.person_id) || !form.customer.id) }} <br>
              {{ Boolean(form.customer.id && !form.customer.person_id) }}
              {{ Boolean(!form.customer.id) }} -->
              <fieldset class="sm:col-span-6" v-if="!vend.customer">
                <legend class="sr-only">Plan</legend>
                <div class="space-y-5">
                  <div class="relative flex items-start">
                    <div class="flex h-6 items-center">
                      <input id="isExisting" aria-describedby="is-existing-description" name="isExisting" type="radio" v-model="isExisting" value="1" class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                    </div>
                    <div class="ml-3 text-sm leading-6">
                      <label for="is_existing" class="font-medium text-gray-900">Select Existing Customer</label>
                    </div>
                  </div>
                  <!-- <div class="relative flex items-start">
                    <div class="flex h-6 items-center">
                      <input id="isExisting" aria-describedby="is-new-description" name="isExisting" type="radio" v-model="isExisting" value="0" class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                    </div>
                    <div class="ml-3 text-sm leading-6">
                      <label for="is_new" class="font-medium text-gray-900">Create New Customer</label>
                    </div>
                  </div> -->
                </div>
              </fieldset>

              <div class="sm:col-span-6" v-if="!vend.customer && isExisting == 1">
                <div class="sm:col-span-6">
                  <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                    Customer
                  </label>
                  <MultiSelect
                    v-model="form.customer_id"
                    :options="adminCustomerOptions"
                    trackBy="id"
                    valueProp="id"
                    label="full_name"
                    placeholder="Select"
                    open-direction="bottom"
                    class="mt-1"
                  >
                  </MultiSelect>
                </div>
              </div>

              <div class="sm:col-span-6" v-if="vend.customer && vend.customer.person_id">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Customer
                </label>
                <div class="mt-1">
                  <input
                    type="text"
                    class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                    :value="'#'+(vend.customer.id + 20000) + ' - ' + vend.customer_code + ' - ' + vend.customer_name"
                    disabled
                  />
                </div>
              </div>
              <div class="sm:col-span-6" v-if="vend.customer && !vend.customer.person_id">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Customer
                </label>
                <div class="mt-1">
                  <input
                    type="text"
                    class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                    :value="'#'+(vend.customer.id + 20000) + ' - ' + (vend.customer.code ? vend.customer.code : '')  + ' - ' + vend.customer_name"
                    disabled
                  />
                </div>
              </div>

              <!-- <div class="sm:col-span-6 grid grid-cols-1 gap-3 sm:grid-cols-6" v-if="(vend.customer.id && !vend.customer.person_id) || (!vend.customer.id && isExisting != 1)"> -->
                <!-- <div class="sm:col-span-2">
                  <FormInput v-model="form.customer.code" :error="form.errors['customer.code']" :disabled="form.customer.person_id">
                    Cust Code
                  </FormInput>
                </div>
                <div class="sm:col-span-3">
                  <FormInput v-model="form.customer.name" :error="form.errors['customer.name']" required="true" :disabled="form.customer.person_id">
                    Cust Name
                  </FormInput>
                </div> -->
                <div class="sm:col-span-6 text-blue-600 text-xs" v-if="vend.customer && vend.customer.person_id">
                  ** Customer Data only editable from CMS
                  <span>
                    <a class="text-blue-700" target="_blank" :href="'//admin.happyice.com.sg/person/' + vend.customer.person_id + '/edit'">
                      (Click Here)
                    </a>
                  </span>
                </div>
                <div class="sm:col-span-6 text-blue-600 text-xs" v-if="vend.customer && !vend.customer.person_id">
                  ** Edit customer data
                  <span>
                    <a class="text-blue-700" target="_blank" :href="'/customers/' + vend.customer.id + '/edit'">
                      (Click Here)
                    </a>
                  </span>
                </div>

            <div class="sm:col-span-6 pt-3">
                <span class="flex space-x-1">
                  <span class="flex space-x-1">
                    <Button
                      type="button"
                      class="bg-green-500 hover:bg-green-600 text-white flex space-x-1"
                      v-if="permissions.includes('update vend-settings') && !vend.customer"
                      @click.prevent="saveCustomer(form.customer_id)"
                    >
                      <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                      <span>
                        Save Customer
                      </span>
                    </Button>
                    <Link :href="'/settings'">
                      <Button
                        class="bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1"
                      >
                        <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
                        <span>
                          Back
                        </span>
                      </Button>
                    </Link>
                  </span>
                  <Button
                    type="button"
                    class="bg-red-500 hover:bg-red-600 text-white flex space-x-1"
                    v-if="vend && vend.customer && permissions.includes('update vend-settings')"
                    @click.prevent="unbindCustomer(form.id)"
                  >
                    <XCircleIcon class="w-4 h-4"></XCircleIcon>
                    <span>
                      Unbind Machine & Customer
                    </span>
                  </Button>
                  <Button
                    type="button"
                    class="bg-red-500 hover:bg-red-600 text-white flex space-x-1"
                    v-if="vend && vend.customer && permissions.includes('update vend-settings')"
                    @click.prevent="unbindCustomerDeactivate(form.id)"
                  >
                    <XCircleIcon class="w-4 h-4"></XCircleIcon>
                    <span>
                      Unbind Machine & Deactivate Customer
                    </span>
                  </Button>
                </span>
              </div>
          </div>
          </div>

          <div class="relative mb-5">
            <div class="absolute inset-0 flex items-center" aria-hidden="true">
              <div class="w-full border-t border-gray-300"></div>
            </div>
            <div class="relative flex justify-start">
              <span class="px-2 bg-white text-lg font-medium text-gray-900 rounded"> Customer History </span>
            </div>
          </div>
          <nav aria-label="Progress">
            <ol role="list" class="overflow-hidden">
              <li v-for="(customer, customerIndex) in vend.customer_movement_history_json" :key="customer.id" :class="[customerIndex !== vend.customer_movement_history_json.length - 1? 'pb-3' : 'relative bg-gray-300 rounded']">
                <template v-if="true">
                  <span class="group relative flex items-start">
                    <span class="flex h-9 items-center">
                      <span class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full" :class="[customer.is_binding ? 'bg-green-600' : 'bg-red-600']">
                        <LockClosedIcon class="h-5 w-5 text-white" aria-hidden="true" v-if="customer.is_binding"/>
                        <LockOpenIcon class="h-5 w-5 text-white" aria-hidden="true" v-if="!customer.is_binding"/>
                      </span>
                    </span>
                    <span class="ml-4 flex min-w-0 flex-col">
                      <span class="text-sm font-medium">
                        <span v-if="customer.virtual_customer_prefix">
                          {{ customer.virtual_customer_prefix }}-{{ customer.virtual_customer_code }}
                        </span>
                        {{ customer.name }}
                      </span>
                      <span class="text-sm text-gray-500">{{ customer.created_at }}</span>
                    </span>
                  </span>
                </template>
                <!-- <template v-else-if="customerIndex === vend.customer_movement_history_json.length - 1">
                  <a :href="'/customers/' + customer.id + '/edit'" class="group relative flex items-start" aria-current="step">
                    <span class="flex h-9 items-center" aria-hidden="true">
                      <span class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full border-2 border-indigo-600 bg-white">
                        <span class="h-2.5 w-2.5 rounded-full bg-indigo-600" />
                      </span>
                    </span>
                    <span class="ml-4 flex min-w-0 flex-col text-blue-600">
                      <span class="text-sm font-medium">
                        <span v-if="customer.virtual_customer_prefix">
                          {{ customer.virtual_customer_prefix }}-{{ customer.virtual_customer_code }}
                        </span>
                        {{ customer.name }}
                      </span>
                      <span class="text-sm text-gray-500">{{ customer.created_at }}</span>
                    </span>
                  </a>
                </template> -->
              </li>
            </ol>
          </nav>
          <template v-if="!vend.customer_movement_history_json || !vend.customer_movement_history_json.length">
            <span class="group relative flex items-start">
              <span class="flex h-9 items-center">
                <span class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full bg-red-600">
                  <MinusCircleIcon class="h-5 w-5 text-white" aria-hidden="true"/>
                </span>
              </span>
              <span class="ml-4 flex min-w-0 flex-col pt-2">
                <span class="text-sm font-medium">
                  No Records Found
                </span>
              </span>
            </span>
          </template>

          <div class="grid grid-cols-1 gap-3 sm:grid-cols-6 pb-5 mb-3">
            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-start">
                  <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> APK Logs </span>
                </div>
              </div>
            </div>
            <div class="sm:col-span-6 flex justify-between">
              <div class="flex space-x-1 mt-5 justify-start">
                <Button
                  class="bg-yellow-500 hover:bg-yellow-600 text-white flex space-x-1"
                  @click.prevent="triggerLogUpload(vend.id)"
                >
                  <ArrowUpTrayIcon class="w-4 h-4"></ArrowUpTrayIcon>
                  <span>
                    Trigger Log Upload
                  </span>
                </Button>
              </div>
            </div>
            <div class="sm:col-span-6 flex flex-col mt-3">
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
                          Created At
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                          File
                        </th>
                      </tr>
                    </thead>
                    <tbody class="bg-white">
                      <tr v-for="(log, logIndex) in vend.logs" :key="log.id" :class="logIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                          {{ logIndex + 1 }}
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                          {{ formatDatetime(log.created_at) }}
                        </td>
                        <td class="whitespace-nowrap py-4 text-sm text-center">
                          <div class="flex w-0 space-x-2 items-center">
                            <PaperClipIcon class="h-5 w-5 flex-shrink-0 text-gray-400" aria-hidden="true" />
                            <a :href="log.full_url" target="_blank" class="font-medium text-indigo-600 hover:text-indigo-500">Download</a>
                          </div>
                        </td>
                      </tr>
                      <tr v-if="!vend.logs || !vend.logs.length">
                        <td colspan="3" class="whitespace-nowrap py-4 text-sm font-medium text-black text-center">
                          No records found
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
            </div>

            <!-- <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3" v-if="form.id">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center">
                  <span class="px-3 bg-white text-lg font-medium text-gray-900"> End of Month Inventory Snapshots </span>
                </div>
              </div>
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
                          End of Month
                          <span class="text-xs">
                            (every last day of the month 11.59:59pm)
                          </span>
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                          Action
                        </th>
                      </tr>
                    </thead>
                    <tbody class="bg-white">
                      <tr v-for="(vendSnapshot, vendSnapshotIndex) in vend.vendSnapshots" :key="vendSnapshot.id" :class="vendSnapshotIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                          {{ vendSnapshotIndex + 1 }}
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                          {{ vendSnapshot.endOfMonthNameYear }}
                        </td>
                        <td class="whitespace-nowrap py-4 text-sm text-center">
                          <Button
                            class="bg-gray-200 hover:bg-gray-300"
                            @click="downloadVendSnapshot(vendSnapshot.id)"
                          >
                            <ArrowDownTrayIcon class="w-4 h-4"></ArrowDownTrayIcon>
                          </Button>
                        </td>
                      </tr>
                      <tr v-if="!vend.vendSnapshots || !vend.vendSnapshots.length">
                        <td colspan="3" class="whitespace-nowrap py-4 text-sm font-medium text-black text-center">
                          No records found
                        </td>
                      </tr>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
            </div> -->

            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  </BreezeAuthenticatedLayout>
</template>

<script setup>
import AttachmentList from '@/Components/AttachmentList.vue';
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import DatePicker from '@/Components/DatePicker.vue';

import FormInput from '@/Components/FormInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import SearchAddressInput from '@/Components/SearchAddressInput.vue';
import { ArrowPathIcon, ArrowUpTrayIcon, ArrowTopRightOnSquareIcon, ArrowUturnLeftIcon, CheckCircleIcon, MinusCircleIcon, CheckIcon, LockClosedIcon, LockOpenIcon, PaperClipIcon, XCircleIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { fromPairs } from 'lodash';
import { useToast } from "vue-toastification";

const props = defineProps({
    adminCustomerOptions: Object,
    cashlessTerminalOptions: Object,
    clawMachineBoardOptions: [Array, Object],
    clawMachineBodyOptions: [Array, Object],
    countries: Object,
    keyOptions: Object,
    lcdMonitorOptions: [Array, Object],
    ledMatrixPanelOptions: [Array, Object],
    menuFrameOptions: [Array, Object],
    modemTypeOptions: [Array, Object],
    modemUnitOptions: [Array, Object],
    operatorOptions: Object,
    productMappingOptions: Object,
    simcardOptions: Object,
    type: String,
    upcomingProductMappingOptions: Object,
    vend: Object,
    vendConfigOptions: Object,
    vendModelOptions: Object,
    vendPrefixOptions: Object,
    vendSerialNumberOptions: Object,
    versionOptions: Object,
  })

const form = ref(
  useForm(getDefaultForm())
)

const adminCustomerOptions = ref([])

const booleanStrictOptions = ref([
    {id: 'true', value: 'Yes'},
    {id: 'false', value: 'No'},
])
const booleanStrictLEDOptions = ref([
    {id: 'true', value: 'Yes -> Hard'},
    {id: 'false', value: 'No -> Soft'},
])
const statusOptions = ref([
    {id: 'factory', value: 'Factory'},
    {id: 'active', value: 'Active'},
    {id: 'inactive', value: 'Inactive'},
])

const cashlessTerminalOptions = ref([])
const clawMachineBoardOptions = ref([])
const clawMachineBodyOptions = ref([])
const countryOptions = ref([])
const lcdMonitorOptions = ref([])
const ledMatrixPanelOptions = ref([])
const menuFrameOptions = ref([])
const modemTypeOptions = ref([])
const modemUnitOptions = ref([])
const keyOptions = ref([])
const isExisting = ref(1)
const operatorOptions = ref([])
const permissions = usePage().props.auth.permissions
const productMappingOptions = ref([])
const simcardOptions = ref([])
const upcomingProductMappingOptions = ref([])
const toast = useToast()
const vendConfigOptions = ref([])
const vendModelOptions = ref([])
const vendPrefixOptions = ref([])
const vendSerialNumberOptions = ref([])
const versionOptions = ref([])

function getDefaultForm() {
  return {
    id: '',
    begin_date: '',
    cashless_terminal_id: '',
    claw_machine_board_id: '',
    claw_machine_body_id: '',
    code: '',
    customer_id: '',
    label_name: '',
    lcd_monitor_id: '',
    led_matrix_panel_id: '',
    customer: {
      begin_date: '',
      termination_date: '',
      id: '',
      code: '',
      name: '',
      address: {
        block_num: '',
        building: '',
        country_id: '',
        latitude: '',
        longitude: '',
        postcode: '',
        street_name: '',
        unit_num: '',
      },
      contact: {
        name: '',
        email: '',
        phone_country_id: '',
        phone_num: '',
      },
    },
    menu_frame_id: '',
    modem_type_id: '',
    modem_unit_id: '',
    operator_id: '',
    key_id: '',
    product_mapping_id: '',
    simcard_id: '',
    status: '',
    termination_date: '',
    is_testing: '',
    is_active: '',
    is_using_server_price: '',
    upcoming_product_mapping_id: '',
    vend_config_id: '',
    vend_config_version: '',
    vend_model_id: '',
    vend_prefix_id: '',
    vend_serial_number_id: '',
    vend_vend_config_version: '',
  }
}

onMounted(() => {
  cashlessTerminalOptions.value = [
    { id: '', name: '--- Clear ---'},
    ...props.cashlessTerminalOptions.data,
  ]
  clawMachineBoardOptions.value = [
    { id: '', name: '--- Clear ---'},
    ...Object.entries(props.clawMachineBoardOptions).map(([id, name]) => ({ id: id, name: name }))
  ];
  countryOptions.value = props.countries.data
  clawMachineBodyOptions.value = [
    { id: '', name: '--- Clear ---'},
    ...Object.entries(props.clawMachineBodyOptions).map(([id, name]) => ({ id: id, name: name }))
  ];
  lcdMonitorOptions.value = [
    { id: '', name: '--- Clear ---'},
    ...Object.entries(props.lcdMonitorOptions).map(([id, name]) => ({ id: id, name: name }))
  ];
  ledMatrixPanelOptions.value = [
    { id: '', name: '--- Clear ---'},
    ...Object.entries(props.ledMatrixPanelOptions).map(([id, name]) => ({ id: id, name: name }))
  ];
  keyOptions.value = [
    { id: '', name: '--- Clear ---'},
    ...props.keyOptions.data,
  ]
  menuFrameOptions.value = [
    { id: '', name: '--- Clear ---'},
    ...Object.entries(props.menuFrameOptions).map(([id, name]) => ({ id: id, name: name }))
  ];
  modemTypeOptions.value = [
    { id: '', name: '--- Clear ---'},
    ...props.modemTypeOptions.data.map(modemType => ({
      id: modemType.id,
      name: modemType.name,
    }))
  ];

  modemUnitOptions.value = [
    { id: '', name: '--- Clear ---'},
    ...props.modemUnitOptions.data.filter(modemUnit => modemUnit.modem_type_id == props.vend.modem_type_id).map(modemUnit => ({
      id: modemUnit.id,
      name: modemUnit.imei,
      modem_type_id: modemUnit.modem_type_id
    }))
  ];
  operatorOptions.value = props.operatorOptions.data
  productMappingOptions.value = [
    { id: '', name: '--- Clear ---'},
    ...props.productMappingOptions.data,
  ]
  simcardOptions.value = [
    { id: '', name: '--- Clear ---'},
    ...props.simcardOptions.data.map(simcard => ({
      id: simcard.id,
      name: simcard.code,
    }))
  ]
  upcomingProductMappingOptions.value = [
    { id: '', name: '--- Clear ---'},
    ...props.upcomingProductMappingOptions.data,
  ]
  vendConfigOptions.value = [
    { id: '', name: '--- Clear ---'},
    ...props.vendConfigOptions.data,
  ]
  vendModelOptions.value = [
    { id: '', name: '--- Clear ---'},
    ...props.vendModelOptions.data,
  ]
  vendPrefixOptions.value = props.vendPrefixOptions ? [
    { id: '', name: '--- Clear ---'},
    ...props.vendPrefixOptions.data,
  ] : []

  vendSerialNumberOptions.value = [
    { id: '', name: '--- Clear ---'},
    ...props.vendSerialNumberOptions.data.map(vendSerialNumber => ({
      id: vendSerialNumber.id,
      name: vendSerialNumber.code,
    }))
  ]
  versionOptions.value = [
    { id: '-', value: '-'},
    ...Object.entries(props.versionOptions).map(([id, version]) => ({id: version, value: version}))
  ]

  form.value = props.vend ? useForm({
    ...props.vend,
    cashless_terminal_id: props.vend.cashless_terminal_id ? props.vend.cashless_terminal_id : null,
    claw_machine_board_id: props.vend.claw_machine_board_id ? clawMachineBoardOptions.value.find(clawMachineBoard => clawMachineBoard.id == props.vend.claw_machine_board_id) : null,
    claw_machine_body_id: props.vend.claw_machine_body_id ? clawMachineBodyOptions.value.find(clawMachineBody => clawMachineBody.id == props.vend.claw_machine_body_id) : null,
    is_using_server_price: booleanStrictOptions.value.find(booleanStrict => booleanStrict.id == props.vend.is_using_server_price.toString()),
    lcd_monitor_id: props.vend.lcd_monitor_id ? lcdMonitorOptions.value.find(lcdMonitor => lcdMonitor.id == props.vend.lcd_monitor_id) : null,
    led_matrix_panel_id: props.vend.led_matrix_panel_id ? ledMatrixPanelOptions.value.find(ledMatrixPanel => ledMatrixPanel.id == props.vend.led_matrix_panel_id) : null,
    key_id: props.vend.key_id ? keyOptions.value.find(keyModel => keyModel.id === props.vend.key_id) : null,
    menu_frame_id: props.vend.menu_frame_id ? menuFrameOptions.value.find(menuFrame => menuFrame.id == props.vend.menu_frame_id) : null,
    modem_type_id: props.vend.modem_type_id ? modemTypeOptions.value.find(modemType => modemType.id == props.vend.modem_type_id) : null,
    modem_unit_id: props.vend.modem_unit_id ? modemUnitOptions.value.find(modemUnit => modemUnit.id == props.vend.modem_unit_id) : null,
    product_mapping_id: props.vend.product_mapping_id ? productMappingOptions.value.find(productMapping =>    productMapping.id === props.vend.product_mapping_id) : null,
    simcard_id: props.vend.simcard_id ? props.vend.simcard_id : null,
    status: statusOptions.value.find(status => status.id === (props.vend.is_testing == 1 ? 'factory' : props.vend.is_active == 1 ? 'active' : 'inactive')),
    operator_id: props.vend ? props.vend.operator_id ? operatorOptions.value.find(operator => operator.id === props.vend.operator_id) : null : null,
    upcoming_product_mapping_id: props.vend.upcoming_product_mapping_id ? upcomingProductMappingOptions.value.find(upcomingProductMapping => upcomingProductMapping.id === props.vend.upcoming_product_mapping_id) : upcomingProductMappingOptions.value[1],
    vend_config_id: props.vend ? props.vend.vend_config_id ? vendConfigOptions.value.find(vendConfig => vendConfig.id === props.vend.vend_config_id) : null : null,
    vend_config_version: props.vend ? props.vend.vend_config_id ? vendConfigOptions.value.find(vendConfig => vendConfig.id === props.vend.vend_config_id).version : null : null,
    vend_model_id: props.vend ? props.vend.vend_model_id ? vendModelOptions.value.find(vendModel => vendModel.id === props.vend.vend_model_id) : null : null,
    vend_prefix_id: props.vend ? props.vend.vend_prefix_id ? vendPrefixOptions.value.find(vendPrefix => vendPrefix.id === props.vend.vend_prefix_id) : null : null,
    vend_serial_number_id: props.vend ? props.vend.vend_serial_number_id ? vendSerialNumberOptions.value.find(vendSerialNumber => vendSerialNumber.id === props.vend.vend_serial_number_id) : null : null,
    vend_vend_config_version: props.vend.vend_vend_config_version ? {id: props.vend.vend_vend_config_version, value: props.vend.vend_vend_config_version} : null,
    customer: {
      ...JSON.parse(JSON.stringify(props.vend.customer)),
      code: props.vend.customer && props.vend.customer.person_id ? props.vend.customer.virtual_customer_code + ' (' + props.vend.customer.virtual_customer_prefix + ')' : (props.vend.customer ? props.vend.customer.code : null),
      contact: props.vend.customer ? {
        ...JSON.parse(JSON.stringify(props.vend.customer.contact))
      } : {
        name: '',
        email: '',
        phone_country_id: '',
        phone_num: '',
      },
      address: props.vend.customer ? {
        ...props.vend.customer.delivery_address,
        country_id: props.vend && props.vend.customer && props.vend.customer.delivery_address ? countryOptions.value.find(country => country.id === props.vend.customer.delivery_address.country_id) : null,
      } : {
        block_num: '',
        building: '',
        country_id: '',
        latitude: '',
        longitude: '',
        postcode: '',
        street_name: '',
        unit_num: '',
      },
    },
  }) : useForm(getDefaultForm())

  adminCustomerOptions.value = props.adminCustomerOptions.data.map(customer => ({
    id: customer.id,
    full_name: customer.person_id && customer.virtual_customer_code ? customer.virtual_customer_code + ' (' + customer.virtual_customer_prefix + ') - ' + customer.name + ' [cms]'  : customer.name,
  }))
})

function formatDatetime(datetime) {
  return datetime ? moment(datetime).format('YYYY-MM-DD hh:mm a') : ''
}

function onVendConfigSelected() {
  form.value.vend_prefix_id = ''
  form.value.product_mapping_id = ''
  vendPrefixOptions.value = []
  productMappingOptions.value = []
  router.reload({
    only: ['vendPrefixOptions', 'productMappingOptions', 'upcomingProductMappingOptions'],
    data: {
      vend_config_id: form.value.vend_config_id.id,
    },
    replace: true,
    preserveState: true,
    onSuccess: page => {
      vendPrefixOptions.value = props.vendPrefixOptions.data
      productMappingOptions.value = props.productMappingOptions.data
    }
  })
}

function onVendPrefixSelected() {
  form.value.product_mapping_id = ''
  productMappingOptions.value = []
  router.reload({
    only: ['productMappingOptions', 'upcomingProductMappingOptions'],
    data: {
      vend_prefix_id: form.value.vend_prefix_id.id,
    },
    replace: true,
    preserveState: true,
    onSuccess: page => {
      productMappingOptions.value = props.productMappingOptions.data
    }
  })
}

function replaceProductMapping(vendID) {
  const approval = confirm('Are you sure to replace the current product mapping to upcoming product mapping?');
  if (!approval) {
      return;
  }

  form.value.clearErrors()

  form.value
    .post('/vends/' + vendID + '/replace-product-mapping', {
    onSuccess: () => {

    },
    preserveState: true,
    replace: true,
  })

}

function restartAPK(vendID) {
  router.post('/vends/' + vendID + '/restart-apk', {}, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    onSuccess: () => {
      emit('modalClose')
    }
  })
}

function restartVMC(vendID) {
  router.post('/vends/' + vendID + '/restart-vmc', {}, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    onSuccess: () => {
      emit('modalClose')
    }
  })
}

function saveCustomer(customerID) {
  form.value.clearErrors()

  form.value
    .transform((data) => ({
      ...data,
      customer: {
        ...data.customer,
        begin_date: data.customer.begin_date && data.customer.begin_date != 'Invalid date' ? data.customer.begin_date : null,
        termination_date: data.customer.termination_date && data.customer.termination_date != 'Invalid date' ? data.customer.termination_date : null,
        operator_id: data.customer.operator_id ? data.customer.operator_id.id : null,
        contact: {
          ...data.customer.contact,
          phone_country_id: data.customer.contact.phone_country_id ? data.customer.contact.phone_country_id.id : null,
        },
        address: {
          ...data.customer.address,
          country_id: data.customer.address.country_id ? data.customer.address.country_id.id : null,
        },
      },
      customer_id: data.customer_id ? data.customer_id.id : null,
      is_existing: isExisting.value,
    }))
    .post('/customers/' + customerID + '/update', {
    onSuccess: () => {
      // emit('modalClose')
    },
    preserveState: true,
    replace: true,
  })
}

function saveVend(vendID) {
  form.value.clearErrors()

  form.value
    .transform((data) => ({
      ...data,
      cashless_terminal_id: data.cashless_terminal_id ? data.cashless_terminal_id.id : null,
      claw_machine_board_id: data.claw_machine_board_id ? data.claw_machine_board_id.id : null,
      claw_machine_body_id: data.claw_machine_body_id ? data.claw_machine_body_id.id : null,
      lcd_monitor_id: data.lcd_monitor_id ? data.lcd_monitor_id.id : null,
      led_matrix_panel_id: data.led_matrix_panel_id ? data.led_matrix_panel_id.id : null,
      begin_date: data.begin_date && data.begin_date != 'Invalid date' ? data.begin_date : null,
      key_id: data.key_id ? data.key_id.id : null,
      is_using_server_price: data.is_using_server_price.id === 'true' ? 1 : 0,
      menu_frame_id: data.menu_frame_id ? data.menu_frame_id.id : null,
      modem_type_id: data.modem_type_id ? data.modem_type_id.id : null,
      modem_unit_id: data.modem_unit_id ? data.modem_unit_id.id : null,
      simcard_id: data.simcard_id ? data.simcard_id.id : null,
      termination_date: data.termination_date && data.termination_date != 'Invalid date' ? data.termination_date : null,
      operator_id: data.operator_id ? data.operator_id.id : null,
      product_mapping_id: data.product_mapping_id ? data.product_mapping_id.id : null,
      status: data.status.id,
      upcoming_product_mapping_id: data.upcoming_product_mapping_id ? data.upcoming_product_mapping_id.id : null,
      vend_config_id: data.vend_config_id ? data.vend_config_id.id : null,
      vend_model_id: data.vend_model_id ? data.vend_model_id.id : null,
      vend_prefix_id: data.vend_prefix_id ? data.vend_prefix_id.id : null,
      vend_serial_number_id: data.vend_serial_number_id ? data.vend_serial_number_id.id : null,
      vend_vend_config_version: data.vend_vend_config_version ? data.vend_vend_config_version.id : null,
    }))
    .post('/vends/' + vendID + '/update', {
    onSuccess: () => {
      toast.success("Successfully Saved", {
        timeout: 3000
      });
    },
    onError: (errors) => {
      toast.error("Failed, Please Try Again", {
        timeout: 3000
      });
    },
    preserveState: true,
    replace: true,
  })
}

function submit() {
  form.value.clearErrors()

  form.value
    .post('/vends/' + form.value.id + '/update', {
    onSuccess: () => {
      emit('modalClose')
    },
    preserveState: true,
    replace: true,
  })
}

function triggerLogUpload() {
  form.value
    .post('/vends/' + form.value.id + '/trigger-log-upload', {
    onSuccess: () => {
      emit('modalClose')
    },
    preserveState: true,
    replace: true,
  })
}

function onAddressSelected(address) {
  form.value.customer.address = {
    block_num: address.BLK_NO,
    building: address.BUILDING,
    country_id: countryOptions.value[0],
    latitude: address.LATITUDE,
    longitude: address.LONGTITUDE,
    postcode: address.POSTAL,
    street_name: address.ROAD_NAME,
    unit_num: '',
  }
  // searchAddressForm.value = null
}

function unbindCustomer(vendID) {
  const approval = confirm('Are you sure to unbind this customer?');
  if (!approval) {
    return;
  }
  form.value
      .post('/vends/' + vendID + '/unbind-customer/settings', {
        onSuccess: () => {
        },
        preserveState: true,
        replace: true,
      })
}

function unbindCustomerDeactivate(vendID) {
  const approval = confirm('Are you sure to unbind this customer and deactivate it ?');
  if (!approval) {
    return;
  }

  form.value
      .post('/vends/' + vendID + '/unbind-customer-deactivate/settings', {
        onSuccess: () => {
        },
        preserveState: true,
        replace: true,
      })
}

</script>