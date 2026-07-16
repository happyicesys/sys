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
                <span v-if="!vend.is_testing && !vend.is_active && !vend.is_disposed && !vend.is_sold">
                  Not Active
                </span>
                <span v-if="vend.is_disposed">
                  Disposed
                </span>
                <span v-if="vend.is_sold">
                  Sold
                </span>
              </div>
              <!-- <span v-if="vend.delivery_product_mapping_vends" v-for="(deliveryProductMappingVend, index) in vend.delivery_product_mapping_vends">
                <div
                    class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit text-gray-800 bg-green-400"
                    v-if="deliveryProductMappingVend.delivery_product_mapping && deliveryProductMappingVend.delivery_product_mapping.delivery_platform_operator && deliveryProductMappingVend.delivery_product_mapping.delivery_platform_operator.delivery_platform"
                >
                  {{ deliveryProductMappingVend.delivery_product_mapping.delivery_platform_operator.delivery_platform.name }}
                </div>
              </span> -->
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
                <FieldAudit :entry="fieldAudit.label_name" />
            </div>

            <div class="sm:col-span-2">
              <DatePicker v-model="form.begin_date" :error="form.errors.begin_date" @input="onDateFromChanged()"
              v-if="permissions.includes('update machine-settings')">
                Begin Date
              </DatePicker>
                <FieldAudit :entry="fieldAudit.begin_date" />
            </div>
            <div class="sm:col-span-2">
              <DatePicker v-model="form.termination_date" :error="form.errors.termination_date" :minDate="form.begin_date"
              v-if="permissions.includes('update machine-settings')">
                Retired Date
              </DatePicker>
                <FieldAudit :entry="fieldAudit.termination_date" />
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
                <FieldAudit :entry="fieldAudit.status" />
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
                <FieldAudit :entry="fieldAudit.vend_model_id" />
                <div class="text-sm text-red-600" v-if="form.errors.simcard_id">
                  {{ form.errors.vend_model_id }}
                </div>
            </div>
            <div class="sm:col-span-3">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Machine Sticker
                </label>
                <MultiSelect
                  v-model="form.sticker_id"
                  :options="stickerOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
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
                <FieldAudit :entry="fieldAudit.vend_serial_number_id" />
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
                <FieldAudit :entry="fieldAudit.key_id" />
                <div class="text-sm text-red-600" v-if="form.errors.key_id">
                  {{ form.errors.key_id }}
                </div>
            </div>
            <div class="sm:col-span-3">
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
                <FieldAudit :entry="fieldAudit.operator_id" />
                <div class="text-sm text-red-600" v-if="form.errors.operator_id">
                  {{ form.errors.operator_id }}
                </div>
                <div class="sm:col-span-3 text-blue-600 text-xs">
                  ** If change Operator, the Binded Site's Operator will be changed as well
                </div>
            </div>


            <div class="sm:col-span-3">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Contract
                </label>
                <MultiSelect
                  v-model="form.vend_contract_id"
                  :options="vendContractOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.vend_contract_id">
                  {{ form.errors.vend_contract_id }}
                </div>
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
                <FieldAudit :entry="fieldAudit.vend_config_id" />
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
                    <span class="text-red-500" v-if="!isVendConfigNA">
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
                <FieldAudit :entry="fieldAudit.vend_prefix_id" />
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
                <FieldAudit :entry="fieldAudit.simcard_id" />
                <div class="text-sm text-red-600" v-if="form.errors.simcard_id">
                  {{ form.errors.simcard_id }}
                </div>
            </div>

            <div class="sm:col-span-3">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Card Terminal
                </label>
                <MultiSelect
                  v-model="form.card_terminal_id"
                  :options="cardTerminalOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <FieldAudit :entry="fieldAudit.card_terminal_id" />
                <div class="text-sm text-red-600" v-if="form.errors.card_terminal_id">
                  {{ form.errors.card_terminal_id }}
                </div>
            </div>

            <div class="sm:col-span-3">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  <div class="flex space-x-2">
                    Modem Model
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
                <FieldAudit :entry="fieldAudit.modem_type_id" />
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
                  <span class="text-red-500">
                    *
                  </span>
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
                  :options="ledMatrixPanelOptions"
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
                  v-model="form.server_price_type"
                  :options="serverPriceTypeOptions"
                  trackBy="id"
                  valueProp="id"
                  label="value"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.server_price_type">
                  {{ form.errors.server_price_type }}
                </div>
            </div>
            <div class="sm:col-span-3">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Fan speed signal available?
                </label>
                <MultiSelect
                  v-model="form.is_fan_enabled"
                  :options="booleanStrictOptions"
                  trackBy="id"
                  valueProp="id"
                  label="value"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.is_fan_enabled">
                  {{ form.errors.is_fan_enabled }}
                </div>
            </div>

            <hr class="sm:col-span-6">
            <!-- DEPRECATED (2026-07): prefix→mapping binding retired — this dropdown now
                 lists ALL active mappings (name asc) and no longer depends on the prefix,
                 so the v-if="form.vend_prefix_id" gate was removed. -->
            <div class="sm:col-span-3">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  <div class="flex space-x-2 items-center">
                    Product Mapping (current)
                    <span class="text-red-500" v-if="!isVendConfigNA">
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
            <div class="sm:col-span-3">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  <div class="flex space-x-2 items-center">
                    Product Mapping (upcoming)
                    <span v-if="form.upcoming_product_mapping_id && form.upcoming_product_mapping_id.id">
                      <a class="text-blue-700" target="_blank" :href="'/product-mappings/' + form.upcoming_product_mapping_id.id + '/edit'">
                        <ArrowTopRightOnSquareIcon class="w-4 h-4"></ArrowTopRightOnSquareIcon>
                      </a>
                    </span>
                    <span v-if="presetUpcomingName" class="text-xs font-normal text-blue-600">
                      Pre-set upcoming: {{ presetUpcomingName }}
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
                <!-- <div class="flex justify-end mt-2" v-if="showPromoteUpcoming">
                  <Button
                    type="button"
                    :class="[
                      'bg-blue-500 hover:bg-blue-600 text-white flex space-x-1',
                      isPromoting ? 'opacity-50 cursor-not-allowed' : '',
                    ]"
                    :disabled="isPromoting"
                    @click.prevent="promoteUpcomingProductMapping"
                  >
                    <ArrowPathIcon class="w-4 h-4"></ArrowPathIcon>
                    <span>
                      Update Product Mapping
                    </span>
                  </Button>
                </div> -->
            </div>

            <!-- Vend Channels Section -->
              <div class="flex flex-col sm:col-span-5">
                <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5">
                  <div class="inline-block min-w-full py-2 align-middle md:px-4 lg:px-6">
                    <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                      <table class="table-fixed min-w-full divide-y divide-gray-300">
                        <thead class="bg-gray-50">
                          <tr>
                            <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"> # </th>
                            <th scope="col" class="w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900" v-if="form.product_mapping_id && form.product_mapping_id.name !== 'N/A'"> Image </th>
                            <th scope="col" class="w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900" v-if="form.product_mapping_id && form.product_mapping_id.name !== 'N/A'"> Product </th>
                            <th scope="col" class="w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                              <div class="flex justify-center">
                                <span> P1 </span>
                                <span v-if="profile && profile.base_currency">
                                  ({{ profile.base_currency.currency_symbol }})
                                </span>
                                <ExclamationCircleIcon class="w-5 h-5 self-center pl-1" v-tooltip="'Actual Price on Vending Machine'"></ExclamationCircleIcon>
                              </div>
                            </th>
                            <th scope="col" class="w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                              <div class="flex justify-center">
                                <span> P2 </span>
                                <span v-if="profile && profile.base_currency">
                                  ({{ profile.base_currency.currency_symbol }})
                                </span>
                                <ExclamationCircleIcon class="w-5 h-5 self-center pl-1" v-tooltip="'Discounted Price on 2nd Purchase'"></ExclamationCircleIcon>
                              </div>
                            </th>
                            <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"> Ref Price {{ vend?.customer?.selling_price_type }} </th>
                          </tr>
                        </thead>
                        <tbody class="bg-white">
                          <tr v-for="(channel, channelIndex) in vendChannels" :key="channel.id" :class="channelIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold sm:pl-6 text-center text-gray-900"> {{ channel.code }} </td>
                            <td class="whitespace-nowrap text-sm  font-semibold text-gray-900 text-center" v-if="form.product_mapping_id && form.product_mapping_id.name !== 'N/A'">
                              <div class="flex justify-center items-center">
                                <img class="h-16 w-16 rounded-full" :src="channel.product.thumbnail.full_url" alt="" v-if="channel.product && channel.product.thumbnail"/>
                              </div>
                            </td>
                            <td class="py-4 text-sm font-semibold text-center text-gray-900" v-if="form.product_mapping_id && form.product_mapping_id.name !== 'N/A'">
                              <span v-if="channel.product && channel.product.code"> {{ channel.product.code }} </span>
                              <span class="break-normal text-xs" v-if="channel.product && channel.product.name"> <br> {{ channel.product.name }} </span>
                            </td>
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center" >
                              {{ formatCurrency(channel.amount) }}
                            </td>
                            <td
                              class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center text-gray-800"
                              v-if="vendChannels.some(channel => 'amount2' in channel)"
                            >
                              {{ formatCurrency(channel.amount2) }}
                            </td>
                            <td
                              class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center text-gray-800"
                            >
                              {{ channel.product && channel.product.selling_prices[0] ? (channel.product.selling_prices[0].amount/ (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) : null }}
                            </td>
                          </tr>
                          <tr v-if="!vendChannels || !vendChannels.length">
                            <td class="whitespace-nowrap py-4 pl-4 pr-3 text-xs font-normal sm:pl-6 text-center text-gray-900" colspan="6"> No Results Found </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
            <hr class="sm:col-span-6">

            <!-- Validation error summary — always visible when save fails -->
            <div
              id="vend-error-summary"
              v-if="form && form.errors && Object.keys(form.errors).length > 0"
              class="sm:col-span-6 rounded-md border border-red-300 bg-red-50 p-4"
            >
              <div class="flex items-start space-x-2">
                <ExclamationCircleIcon class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" />
                <div class="flex-1">
                  <p class="text-sm font-semibold text-red-700 mb-1">Please fix the following errors before saving:</p>
                  <ul class="list-disc list-inside space-y-0.5">
                    <li
                      v-for="(message, field) in form.errors"
                      :key="field"
                      class="text-sm text-red-600"
                    >
                      {{ message }}
                    </li>
                  </ul>
                </div>
              </div>
            </div>

            <div class="sm:col-span-6">
              <span class="flex space-x-1">
                <Button
                  type="button"
                  class="bg-green-500 hover:bg-green-600 text-white flex space-x-1"
                  v-if="permissions.includes('update machine-settings')"
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
                  v-if="permissions.includes('update machine-settings') && form.upcoming_product_mapping_id"
                  @click.prevent="replaceProductMapping(vend.id)"
                >
                  <ArrowPathIcon class="w-4 h-4"></ArrowPathIcon>
                  <span>
                    Replace Current Product Mapping
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
                <span class="px-2 bg-white text-lg font-medium text-gray-900 rounded"> Site </span>
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
                      No Site Binding
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
                      <label for="is_existing" class="font-medium text-gray-900">Select Existing Site</label>
                    </div>
                  </div>
                  <!-- <div class="relative flex items-start">
                    <div class="flex h-6 items-center">
                      <input id="isExisting" aria-describedby="is-new-description" name="isExisting" type="radio" v-model="isExisting" value="0" class="h-4 w-4 border-gray-300 text-indigo-600 focus:ring-indigo-600" />
                    </div>
                    <div class="ml-3 text-sm leading-6">
                      <label for="is_new" class="font-medium text-gray-900">Create New Site</label>
                    </div>
                  </div> -->
                </div>
              </fieldset>

              <div class="sm:col-span-6" v-if="!vend.customer && isExisting == 1">
                <div class="sm:col-span-6">
                  <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                    Site
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
                  Site
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
                  Site
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
                        Save Site
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
                      Unbind Machine & Site
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
                      Unbind Machine & Deactivate Site
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
              <span class="px-2 bg-white text-lg font-medium text-gray-900 rounded"> Site Binding History </span>
            </div>
          </div>
          <nav aria-label="Progress">
            <ol role="list" class="overflow-hidden">
              <li v-for="(customerVendBinding, customerVendBindingIndex) in customerVendBindings" :key="customerVendBinding.id" :class="[customerVendBindingIndex !== customerVendBindings.length - 1? 'pb-3' : 'relative bg-gray-300 rounded']">
                <template v-if="true">
                  <span class="group relative flex items-start">
                    <span class="flex h-9 items-center">
                      <span class="relative z-10 flex h-8 w-8 items-center justify-center rounded-full" :class="[customerVendBinding.is_binding ? 'bg-green-600' : 'bg-red-600']">
                        <LockClosedIcon class="h-5 w-5 text-white" aria-hidden="true" v-if="customerVendBinding.is_binding"/>
                        <LockOpenIcon class="h-5 w-5 text-white" aria-hidden="true" v-if="!customerVendBinding.is_binding"/>
                      </span>
                    </span>
                    <span class="ml-4 flex min-w-0 flex-col">
                      <span class="text-sm font-medium">
                        <span v-if="customerVendBinding.customer?.virtual_customer_prefix">
                          ({{ customerVendBinding.customer.id + 20000 }}{{ vend.vend_prefix_name ? ' - ' + vend.vend_prefix_name : '' }})
                        </span>
                        {{ customerVendBinding?.customer?.name }}
                      </span>
                      <span class="text-sm text-gray-500">{{ customerVendBinding.created_at ? formatDatetime(customerVendBinding.created_at) : '' }}</span>
                    </span>
                  </span>
                </template>
              </li>
            </ol>
          </nav>
          <template v-if="!customerVendBindings || !customerVendBindings.length">
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
              <div class="flex space-x-2 items-center">
                <div>
                  <DatePicker
                      v-model="form.trigger_log_date"
                  >
                      From
                  </DatePicker>
                </div>
                <Button
                  class="bg-yellow-500 hover:bg-yellow-600 text-white flex space-x-1 h-fit"
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

            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-start">
                  <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> APK Parameter </span>
                </div>
              </div>
            </div>

             <div class="sm:col-span-2">
                <label class="flex justify-start text-sm font-medium text-gray-700">
                  Grab Enabled?
                </label>
                <div class="mt-2 block sm:text-sm flex items-center">
                   <CheckIcon v-if="isApkParamTrue(vend.is_enable_grab_collection)" class="w-5 h-5 text-green-500" />
                   <XMarkIcon v-else-if="isApkParamFalse(vend.is_enable_grab_collection)" class="w-5 h-5 text-red-500" />
                   <span v-else>Not Detected</span>
                </div>
            </div>
            <div class="sm:col-span-2">
                <label class="flex justify-start text-sm font-medium text-gray-700">
                  Display Screen Available?
                </label>
                <div class="mt-2 block sm:text-sm flex items-center">
                   <CheckIcon v-if="isApkParamTrue(vend.has_display_screen)" class="w-5 h-5 text-green-500" />
                   <XMarkIcon v-else-if="isApkParamFalse(vend.has_display_screen)" class="w-5 h-5 text-red-500" />
                   <span v-else>Not Detected</span>
                </div>
            </div>
            <div class="sm:col-span-2">
                <label class="flex justify-start text-sm font-medium text-gray-700">
                  QR Payment Method
                </label>
                <div class="mt-2 block sm:text-sm flex items-center">
                   <CheckIcon v-if="isApkParamTrue(vend.is_enable_soft_keyboard_qr_pay)" class="w-5 h-5 text-green-500" />
                   <XMarkIcon v-else-if="isApkParamFalse(vend.is_enable_soft_keyboard_qr_pay)" class="w-5 h-5 text-red-500" />
                   <span v-else>Not Detected</span>
                </div>
            </div>
            <div class="sm:col-span-2">
                <label class="flex justify-start text-sm font-medium text-gray-700">
                  Cash Payment Method
                </label>
                <div class="mt-2 block sm:text-sm flex items-center">
                   <CheckIcon v-if="isApkParamTrue(vend.is_enable_soft_keyboard_cash_pay)" class="w-5 h-5 text-green-500" />
                   <XMarkIcon v-else-if="isApkParamFalse(vend.is_enable_soft_keyboard_cash_pay)" class="w-5 h-5 text-red-500" />
                   <span v-else>Not Detected</span>
                </div>
            </div>
             <div class="sm:col-span-2">
                <label class="flex justify-start text-sm font-medium text-gray-700">
                  Credit Card Payment Method
                </label>
                <div class="mt-2 block sm:text-sm flex items-center">
                   <CheckIcon v-if="isApkParamTrue(vend.is_enable_soft_keyboard_credit_card_pay)" class="w-5 h-5 text-green-500" />
                   <XMarkIcon v-else-if="isApkParamFalse(vend.is_enable_soft_keyboard_credit_card_pay)" class="w-5 h-5 text-red-500" />
                   <span v-else>Not Detected</span>
                </div>
            </div>
            <div class="sm:col-span-2">
                <label class="flex justify-start text-sm font-medium text-gray-700">
                  HID Payment Method
                </label>
                <div class="mt-2 block sm:text-sm flex items-center">
                   <CheckIcon v-if="isApkParamTrue(vend.is_enable_soft_keyboard_hid_pay)" class="w-5 h-5 text-green-500" />
                   <XMarkIcon v-else-if="isApkParamFalse(vend.is_enable_soft_keyboard_hid_pay)" class="w-5 h-5 text-red-500" />
                   <span v-else>Not Detected</span>
                </div>
            </div>

            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-start">
                  <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> Advance Control </span>
                </div>
              </div>
            </div>

            <div class="sm:col-span-6 mb-4 rounded-md bg-blue-50 p-4 shadow-sm border border-blue-200">
              <div class="flex">
                <div class="flex-shrink-0">
                  <svg class="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
                  </svg>
                </div>
                <div class="ml-3 flex-1 text-sm text-blue-700">
                  <p class="font-bold text-blue-800 mb-1">
                    Remote Restart Instructions
                  </p>
                  <p>
                    Remotely restart VMC and APK to address issues of Card Terminal not responding and Alert on Lost of Transaction/Sales from Cash Sales/ Card Terminal /via QR / No Digital Screen Activity.
                  </p>
                  <div class="mt-3 text-blue-800">
                    <p class="font-semibold">Steps:</p>
                    <ol class="list-decimal list-inside ml-1 mt-1 font-medium">
                      <li>Restart VMC. Wait for 1 min, then</li>
                      <li>Restart APK</li>
                    </ol>
                  </div>
                  <div class="mt-4 flex flex-col space-y-1">
                    <p class="text-red-500 font-semibold text-xs">
                      * Note: Not able to remote restart on machines with 'offline' status.
                    </p>
                    <p class="text-xs italic text-blue-600">
                      If issue persists after restarting, an onsite check is needed for connectivity and hardware.
                    </p>
                  </div>
                </div>
              </div>
            </div>

            <div class="sm:col-span-6">
              <span class="flex space-x-1">
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
                <Button
                    class="bg-yellow-500 hover:bg-yellow-600 text-black flex space-x-1"
                    @click.prevent="syncChannels(vend.id)"
                  >
                    <ArrowPathIcon class="w-4 h-4"></ArrowPathIcon>
                    <span>
                      Push Products Info to Machine
                    </span>
                </Button>
                <Button
                    class="bg-yellow-500 hover:bg-yellow-600 text-black flex space-x-1"
                    @click.prevent="syncApkSettings(vend.id)"
                  >
                    <ArrowPathIcon class="w-4 h-4"></ArrowPathIcon>
                    <div class="flex flex-col text-left">
                      <span>
                        Sync APK Settings
                      </span>
                      <span class="text-xs" v-if="vend.latest_sync_apk_setting_job && vend.latest_sync_apk_setting_job.response_at">
                        (last response at: {{ formatDatetime(vend.latest_sync_apk_setting_job.response_at) }})
                      </span>
                    </div>
                </Button>
              </span>
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
import FieldAudit from '@/Components/FieldAudit.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import SearchAddressInput from '@/Components/SearchAddressInput.vue';
import { ArrowPathIcon, ArrowUpTrayIcon, ArrowTopRightOnSquareIcon, ArrowUturnLeftIcon, CheckCircleIcon, MinusCircleIcon, CheckIcon, LockClosedIcon, LockOpenIcon, ExclamationCircleIcon, PaperClipIcon, XCircleIcon, XMarkIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted, computed, watch, nextTick } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { fromPairs } from 'lodash';
import { useToast } from "vue-toastification";

const props = defineProps({
    adminCustomerOptions: Object,
    cardTerminalOptions: Object,
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
    sellingPriceTypeOptions: [Array, Object],
    simcardOptions: Object,
    type: String,
    upcomingProductMappingOptions: Object,
    vend: Object,
    selectedProductMapping: Object,
    vendConfigOptions: Object,
    vendContractOptions: Object,
    vendModelOptions: Object,
    vendPrefixOptions: Object,
    vendSerialNumberOptions: Object,
    stickerOptions: Object,
    versionOptions: Object,
  })

const form = ref(
  useForm(getDefaultForm())
)

const page = usePage();
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
    {id: 'factory', value: 'Factory (JB)'},
    {id: 'active', value: 'Active'},
    {id: 'inactive', value: 'Inactive'},
    {id: 'disposed', value: 'Disposed'},
    {id: 'sold', value: 'Sold'},
])

const cardTerminalOptions = ref([])
const cashlessTerminalOptions = ref([])
const clawMachineBoardOptions = ref([])
const clawMachineBodyOptions = ref([])
const countryOptions = ref([])
const customerVendBindings = ref([])
const lcdMonitorOptions = ref([])
const ledMatrixPanelOptions = ref([])
const menuFrameOptions = ref([])
const modemTypeOptions = ref([])
const modemUnitOptions = ref([])
const operatorCountry = page.props.auth.operatorCountry;
const keyOptions = ref([])
const isExisting = ref(1)
const operatorOptions = ref([])
const permissions = page.props.auth.permissions
const productMappingOptions = ref([])
const serverPriceTypeOptions = ref([])
const simcardOptions = ref([])
const upcomingProductMappingOptions = ref([])
const toast = useToast()
const vendChannels = ref([]);
const originalVendChannels = ref([]);
const selectedProductMapping = ref(props.selectedProductMapping ?? null);
const vendConfigOptions = ref([])
const vendContractOptions = ref([])
const vendModelOptions = ref([])
const vendPrefixOptions = ref([])
const vendSerialNumberOptions = ref([])
const stickerOptions = ref([])
const fieldAudit = ref({})
const versionOptions = ref([])
const isPromoting = ref(false)
let hasMounted = false;

// Reorders an options array so any "N/A" entry sits last, keeping the
// relative order of all other options unchanged. Matches on the common
// label keys used across the dropdowns (name / value / full_name).
function moveNaLast(options) {
  if (!Array.isArray(options)) return options
  const isNa = (o) => {
    const label = o?.name ?? o?.value ?? o?.full_name
    return typeof label === 'string' && label.trim().toUpperCase() === 'N/A'
  }
  const naItems = options.filter(isNa)
  if (!naItems.length) return options
  return [...options.filter((o) => !isNa(o)), ...naItems]
}

const isVendConfigNA = computed(() => {
  return form.value.vend_config_id && form.value.vend_config_id.name === 'N/A';
})

const showPromoteUpcoming = computed(() => {
  const upcomingId = form.value?.upcoming_product_mapping_id?.id
  const currentId = form.value?.product_mapping_id?.id

  if (upcomingId === undefined || upcomingId === null || upcomingId === '') {
    return false
  }

  return String(upcomingId) !== String(currentId ?? '')
})

// Name of the preset/bound upcoming mapping configured on the CURRENTLY-selected
// mapping (set in the Product Mapping edit page). Since the "upcoming" field is
// now a free-select dropdown of ALL active mappings, the user can lose track of
// what the current mapping's own preset upcoming was — this label surfaces it.
// Prefer the name shipped on the option (upcoming_product_mapping_name, loaded
// via the upcomingProductMapping relation); fall back to resolving the id.
const presetUpcomingName = computed(() => {
  const current = form.value?.product_mapping_id
  const presetId = current?.upcoming_product_mapping_id
  if (!current || !presetId) {
    return null
  }
  if (current.upcoming_product_mapping_name) {
    return current.upcoming_product_mapping_name
  }
  const match = upcomingProductMappingOptions.value.find(
    (option) => String(option.id) === String(presetId)
  )
  return match ? (match.name || null) : null
})

function getDefaultForm() {
  return {
    id: '',
    begin_date: '',
    card_terminal_id: '',
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
    trigger_log_date: moment().format('YYYY-MM-DD'),
    is_testing: '',
    is_active: '',
    is_using_server_price: '',
    is_fan_enabled: {id: 'true', value: 'Yes'},
    upcoming_product_mapping_id: '',
    vend_config_id: '',
    vend_config_version: '',
    vend_contract_id: '',
    vend_model_id: '',
    vend_prefix_id: '',
    vend_serial_number_id: '',
    vend_vend_config_version: '',
  }
}

onMounted(() => {
  stickerOptions.value = [{ id: '', name: '--- Clear ---' }, ...(((props.stickerOptions && props.stickerOptions.data) ? props.stickerOptions.data : []).map(s => ({ id: s.id, name: s.name })))]

  // Per-field audit (who/when) for this machine, derived from user_logs.
  if (props.vend && props.vend.id) {
    axios.get('/vends/' + props.vend.id + '/field-audit')
      .then((res) => { fieldAudit.value = res.data || {} })
      .catch(() => {})
  }

  // Card Terminal types (Nayax / Nets / Nets-Auresys / PAX / MLS) — populated from
  // CardTerminalResource::collection in SettingController@edit.
  cardTerminalOptions.value = [
    { id: '', name: '--- Clear ---'},
    ...((props.cardTerminalOptions?.data) ?? []).map(terminal => ({
      id: terminal.id,
      name: terminal.name,
    })),
  ]
  cashlessTerminalOptions.value = [
    { id: '', name: '--- Clear ---'},
    ...props.cashlessTerminalOptions.data.map(terminal => ({
      id: terminal.id,
      name: terminal.code
    })),
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
  serverPriceTypeOptions.value = [
    { id: '', value: '--- Not Using ---'},
    ...Object.entries(props.sellingPriceTypeOptions).map(([id, name]) => ({id: id, value: name}))
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
  vendContractOptions.value = [
    { id: '', name: '--- Clear ---'},
    ...props.vendContractOptions.data,
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

  // Keep any "N/A" choice at the bottom of every dropdown that has one
  // (Product Mapping, Machine Prefix, Setting Chart, etc.). No-op for
  // lists without an N/A entry. Relative order of the other options is
  // preserved, so the "--- Clear ---" placeholder stays on top.
  const naOrderedDropdowns = [
    cardTerminalOptions, cashlessTerminalOptions, clawMachineBoardOptions,
    clawMachineBodyOptions, lcdMonitorOptions, ledMatrixPanelOptions,
    keyOptions, menuFrameOptions, modemTypeOptions, modemUnitOptions,
    operatorOptions, productMappingOptions, serverPriceTypeOptions,
    simcardOptions, upcomingProductMappingOptions, vendConfigOptions,
    vendContractOptions, vendModelOptions, vendPrefixOptions,
    vendSerialNumberOptions,
  ]
  naOrderedDropdowns.forEach((optionsRef) => {
    optionsRef.value = moveNaLast(optionsRef.value)
  })

  form.value = props.vend ? useForm({
    ...props.vend,
    card_terminal_id: props.vend.card_terminal_id ? cardTerminalOptions.value.find(t => t.id == props.vend.card_terminal_id) : null,
    cashless_terminal_id: props.vend.cashless_terminal_id ? cashlessTerminalOptions.value.find(t => t.id == props.vend.cashless_terminal_id) : null,
    claw_machine_board_id: props.vend.claw_machine_board_id ? clawMachineBoardOptions.value.find(clawMachineBoard => clawMachineBoard.id == props.vend.claw_machine_board_id) : null,
    claw_machine_body_id: props.vend.claw_machine_body_id ? clawMachineBodyOptions.value.find(clawMachineBody => clawMachineBody.id == props.vend.claw_machine_body_id) : null,
    // is_using_server_price: booleanStrictOptions.value.find(booleanStrict => booleanStrict.id == props.vend.is_using_server_price.toString()),
    lcd_monitor_id: props.vend.lcd_monitor_id ? lcdMonitorOptions.value.find(lcdMonitor => lcdMonitor.id == props.vend.lcd_monitor_id) : null,
    led_matrix_panel_id: props.vend.led_matrix_panel_id ? ledMatrixPanelOptions.value.find(ledMatrixPanel => ledMatrixPanel.id == props.vend.led_matrix_panel_id) : null,
    key_id: props.vend.key_id ? keyOptions.value.find(keyModel => keyModel.id === props.vend.key_id) : null,
    menu_frame_id: props.vend.menu_frame_id ? menuFrameOptions.value.find(menuFrame => menuFrame.id == props.vend.menu_frame_id) : null,
    modem_type_id: props.vend.modem_type_id ? modemTypeOptions.value.find(modemType => modemType.id == props.vend.modem_type_id) : null,
    modem_unit_id: props.vend.modem_unit_id ? modemUnitOptions.value.find(modemUnit => modemUnit.id == props.vend.modem_unit_id) : null,
    product_mapping_id: props.vend.product_mapping_id ? productMappingOptions.value.find(productMapping =>    productMapping.id == props.vend.product_mapping_id) : null,
    server_price_type: props.vend.server_price_type ? serverPriceTypeOptions.value.find(serverPriceType => serverPriceType.id == props.vend.server_price_type) : null,
    is_fan_enabled: (props.vend.is_fan_enabled === false || props.vend.is_fan_enabled === 0 || props.vend.is_fan_enabled === '0' || props.vend.is_fan_enabled === 'false') ? {id: 'false', value: 'No'} : {id: 'true', value: 'Yes'},
    simcard_id: props.vend.simcard_id ? simcardOptions.value.find(simcard => simcard.id == props.vend.simcard_id) : null,
    status: statusOptions.value.find(status => status.id == (props.vend.is_sold == 1 ? 'sold' : (props.vend.is_disposed == 1 ? 'disposed' : (props.vend.is_testing == 1 ? 'factory' : props.vend.is_active == 1 ? 'active' : 'inactive')))),
    operator_id: props.vend ? props.vend.operator_id ? operatorOptions.value.find(operator => operator.id == props.vend.operator_id) : null : null,
    trigger_log_date: moment().format('YYYY-MM-DD'),
    upcoming_product_mapping_id: computeUpcomingSelection(props.vend.upcoming_product_mapping_id),
    vend_config_id: props.vend ? props.vend.vend_config_id ? vendConfigOptions.value.find(vendConfig => vendConfig.id == props.vend.vend_config_id) : null : null,
    vend_config_version: props.vend ? props.vend.vend_config_id ? vendConfigOptions.value.find(vendConfig => vendConfig.id == props.vend.vend_config_id).version : null : null,
    vend_contract_id: props.vend ? props.vend.vend_contract_id ? vendContractOptions.value.find(vendContract => vendContract.id == props.vend.vend_contract_id) : null : null,
    vend_model_id: props.vend ? props.vend.vend_model_id ? vendModelOptions.value.find(vendModel => vendModel.id == props.vend.vend_model_id) : null : null,
    vend_prefix_id: props.vend ? props.vend.vend_prefix_id ? vendPrefixOptions.value.find(vendPrefix => vendPrefix.id == props.vend.vend_prefix_id) : null : null,
    vend_serial_number_id: props.vend ? props.vend.vend_serial_number_id ? vendSerialNumberOptions.value.find(vendSerialNumber => vendSerialNumber.id == props.vend.vend_serial_number_id) : null : null,
    sticker_id: props.vend && props.vend.stickers && props.vend.stickers.length ? (stickerOptions.value.find(o => o.id === props.vend.stickers[0].id) || null) : null,
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


  customerVendBindings.value = props.vend.customer_vend_bindings

  adminCustomerOptions.value = props.adminCustomerOptions.data.map(customer => ({
    id: customer.id,
    full_name: customer.person_id && customer.virtual_customer_code ? (customer.id + 20000) + ' - ' + customer.name + ' [cms]'  : customer.name,
  }))

  const initialChannels = props.vend && Array.isArray(props.vend.vend_channels) ? props.vend.vend_channels : [];
  originalVendChannels.value = initialChannels ? JSON.parse(JSON.stringify(initialChannels)) : [];
  vendChannels.value = Array.isArray(originalVendChannels.value) ? [...originalVendChannels.value] : [];

  if (selectedProductMapping.value) {
    applyMappingPreview(selectedProductMapping.value);
  }

  // Defer flipping hasMounted until AFTER the watchers scheduled by the
  // `form.value = useForm(...)` reassignment above have flushed. Reassigning
  // form.value changes form.value.product_mapping_id (default '' -> loaded
  // option), which triggers the product_mapping_id watcher below. If hasMounted
  // were already true when that initial trigger runs, the watcher would treat it
  // as a user-initiated mapping change and overwrite the freshly-loaded
  // upcoming_product_mapping_id with the current mapping's preset (or null) --
  // making a saved "upcoming" appear unbound after refresh. nextTick resolves
  // after the watcher flush, so the initial trigger sees hasMounted === false
  // and correctly skips; only genuine user changes take effect afterwards.
  nextTick(() => {
    hasMounted = true;
  })
})

watch(() => form.value.product_mapping_id, (newVal) => {
  // When the current mapping changes, pre-fill "upcoming" with that mapping's
  // preset upcoming as a sensible DEFAULT. The field is now a user-editable
  // dropdown, so we resolve the derived id to a full option object (MultiSelect
  // binds objects), and the user can still override the pick afterwards.
  if (hasMounted && newVal && newVal.upcoming_product_mapping_id) {
    const derived = upcomingProductMappingOptions.value.find(
      (option) => String(option.id) === String(newVal.upcoming_product_mapping_id)
    )
    form.value.upcoming_product_mapping_id = derived || null
  } else if (hasMounted) {
    form.value.upcoming_product_mapping_id = null
  }

  if (hasMounted) {
    applyMappingPreview(newVal)
  }
})

function compareSellingPrice(channel) {
  if (channel.product && channel.product.selling_prices[0] && channel.product.selling_prices[0].amount) {
    if (channel.amount != channel.product.selling_prices[0].amount) {
      return 'text-red-500';
    }
  }
  return 'text-gray-800';
}

function formatCurrency(amount) {
  if (amount === null || amount === undefined) {
    return '-';
  }

  const numericAmount = Number(amount);

  if (Number.isNaN(numericAmount)) {
    return '-';
  }

  const exponent = operatorCountry?.currency_exponent ?? 0;
  const divisor = Math.pow(10, exponent);
  const minimumFractionDigits = operatorCountry?.is_currency_exponent_hidden ? 0 : exponent;
  const maximumFractionDigits = operatorCountry?.is_currency_exponent_hidden ? 0 : exponent;

  return (numericAmount / divisor).toLocaleString(undefined, {
    minimumFractionDigits,
    maximumFractionDigits,
  });
}

function formatDatetime(datetime) {
  return datetime ? moment(datetime).format('YYYY-MM-DD hh:mm a') : ''
}

function isApkParamTrue(value) {
    return value === true || value === 1 || value === '1';
}

function isApkParamFalse(value) {
    return value === false || value === 0 || value === '0';
}

function formatApkParameter(value) {
  if (value === true || value === 1 || value === '1') {
    return 'Yes'
  } else if (value === false || value === 0 || value === '0') {
    return 'No'
  }
  return 'Not Detected'
}

function computeUpcomingSelection(existingId = null) {
  const normalizedId = existingId ? existingId : null
  if (normalizedId) {
    const matched = upcomingProductMappingOptions.value.find(
      (option) => option.id !== '' && String(option.id) === String(normalizedId)
    )
    if (matched) {
      return matched
    }
  }

  const available = upcomingProductMappingOptions.value.filter((option) => option.id)

  if (available.length === 1) {
    return available[0]
  }

  return null
}

function refreshUpcomingSelection() {
  const currentId = form.value.upcoming_product_mapping_id?.id || null
  form.value.upcoming_product_mapping_id = computeUpcomingSelection(currentId)
}

function extractOptionId(option) {
  if (!option) {
    return null;
  }

  if (typeof option === 'object') {
    if (option.id !== undefined && option.id !== null && option.id !== '') {
      return option.id;
    }

    if (option.value !== undefined && option.value !== null && option.value !== '') {
      return option.value;
    }

    return null;
  }

  if (option === '') {
    return null;
  }

  return option;
}

function normalizeProductForChannel(product) {
  if (!product) {
    return null;
  }

  const sellingPrices = product.selling_prices ?? product.sellingPrices ?? [];

  return {
    ...product,
    selling_prices: sellingPrices,
  };
}

function buildChannelFromMappingItem(item, index) {
  if (!item) {
    return null;
  }

  const sellingPrice = item.sellingPrice ?? item.selling_price ?? null;
  const amount = item.server_amount ?? sellingPrice?.amount ?? null;

  return {
    id: `mapping-${item.id ?? index}`,
    code: item.channel_code,
    amount,
    amount2: item.server_amount2 ?? null,
    product: normalizeProductForChannel(item.product),
  };
}

function applyMappingPreview(mapping) {
  if (!mapping) {
    resetMappingPreview();
    return;
  }

  const items = Array.isArray(mapping.productMappingItems)
    ? mapping.productMappingItems
    : Array.isArray(mapping.product_mapping_items)
      ? mapping.product_mapping_items
      : [];

  if (!items.length) {
    if (mapping.name === 'N/A') {
      selectedProductMapping.value = mapping;
      vendChannels.value = [];
    } else {
      resetMappingPreview();
    }
    return;
  }

  if (selectedProductMapping.value !== mapping) {
    selectedProductMapping.value = mapping;
  }

  const channels = items
    .map((item, index) => buildChannelFromMappingItem(item, index))
    .filter((channel) => channel && channel.code);

  vendChannels.value = channels;
}

function resetMappingPreview() {
  selectedProductMapping.value = null;
  vendChannels.value = Array.isArray(originalVendChannels.value) ? [...originalVendChannels.value] : [];
}

function fetchProductMappingPreviewById(mappingId) {
  if (!mappingId) {
    resetMappingPreview();
    return;
  }

  const data = {
    product_mapping_id: mappingId,
  };

  const vendPrefixId = extractOptionId(form.value.vend_prefix_id);
  if (vendPrefixId) {
    data.vend_prefix_id = vendPrefixId;
  }

  const vendConfigId = extractOptionId(form.value.vend_config_id);
  if (vendConfigId) {
    data.vend_config_id = vendConfigId;
  }

  router.reload({
    only: ['selectedProductMapping'],
    data,
    replace: true,
    preserveState: true,
    onSuccess: (page) => {
      applyMappingPreview(page.props.selectedProductMapping ?? null);
    },
    onError: () => {
      resetMappingPreview();
    },
  });
}

watch(
  () => extractOptionId(form.value.product_mapping_id),
  (newId, oldId) => {
    if (!hasMounted) {
      return;
    }

    const normalizedNew = newId ? String(newId) : null;
    const normalizedOld = oldId ? String(oldId) : null;

    if (normalizedNew === normalizedOld) {
      return;
    }

    if (!normalizedNew) {
      resetMappingPreview();
      return;
    }

    fetchProductMappingPreviewById(normalizedNew);
  }
);

watch(
  () => page.props.selectedProductMapping,
  (mapping) => {
    if (!hasMounted || mapping === undefined) {
      return;
    }

    if (!mapping) {
      resetMappingPreview();
      return;
    }

    applyMappingPreview(mapping);
  }
);

watch(
  () => page.props.vend?.vend_channels,
  (channels) => {
    if (channels === undefined) {
      return;
    }

    const snapshot = Array.isArray(channels) ? JSON.parse(JSON.stringify(channels)) : [];
    originalVendChannels.value = snapshot;

    if (!selectedProductMapping.value) {
      vendChannels.value = [...snapshot];
    }
  },
  { deep: true }
);

watch(
  () => props.adminCustomerOptions,
  (newVal) => {
    if (newVal && newVal.data) {
      adminCustomerOptions.value = newVal.data.map(customer => ({
        id: customer.id,
        full_name: customer.person_id && customer.virtual_customer_code ? (customer.id + 20000) + ' - ' + customer.name + ' [cms]'  : customer.name,
      }))
    }
  },
  { deep: true }
);

function onVendConfigSelected() {
  // DEPRECATED (2026-07): prefix→mapping binding retired — changing the Setting
  // Chart no longer clears the chosen Product Mapping (only the prefix, which
  // still depends on the config). Mapping options are prefix-independent now.
  form.value.vend_prefix_id = ''
  vendPrefixOptions.value = []
  router.reload({
    only: ['vendPrefixOptions'],
    data: {
      vend_config_id: form.value.vend_config_id.id,
    },
    replace: true,
    preserveState: true,
    onSuccess: page => {
      vendPrefixOptions.value = page.props.vendPrefixOptions
        ? [
            { id: '', name: '--- Clear ---'},
            ...page.props.vendPrefixOptions.data,
          ]
        : []
    }
  })
}

function onVendPrefixSelected() {
  // DEPRECATED (2026-07): prefix→mapping binding retired — the Product Mapping
  // dropdown lists ALL active mappings regardless of prefix, so changing the
  // prefix no longer clears the mapping or reloads its options.
}

function promoteUpcomingProductMapping() {
  const upcomingSelection = form.value.upcoming_product_mapping_id

  if (isPromoting.value || !upcomingSelection || !upcomingSelection.id) {
    return
  }

  const approval = confirm('Are you sure you want to update the current product mapping to the selected upcoming product mapping?')
  if (!approval) {
    return
  }

  const upcomingId = upcomingSelection.id
  const upcomingName = upcomingSelection.name || upcomingSelection.value || ''

  isPromoting.value = true

  router.post('/vends/' + form.value.id + '/promote-upcoming-product-mapping', {
    upcoming_product_mapping_id: upcomingId,
  }, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    onSuccess: () => {
      if (!productMappingOptions.value.some((option) => String(option.id) === String(upcomingId))) {
        productMappingOptions.value.push({
          id: upcomingId,
          name: upcomingName,
        })
      }

      const matchedCurrentOption = productMappingOptions.value.find(
        (option) => String(option.id) === String(upcomingId)
      )

      if (matchedCurrentOption) {
        form.value.product_mapping_id = matchedCurrentOption
      } else {
        form.value.product_mapping_id = {
          id: upcomingId,
          name: upcomingName,
        }
      }

      form.value.upcoming_product_mapping_id = null
      upcomingProductMappingOptions.value = upcomingProductMappingOptions.value.filter((option) => {
        if (!option.id) {
          return true
        }
        return String(option.id) !== String(upcomingId)
      })

      refreshUpcomingSelection()

      toast.success('Product mapping updated successfully.', {
        timeout: 3000,
      })
    },
    onError: () => {
      toast.error('Failed to update product mapping. Please try again.', {
        timeout: 3000,
      })
    },
    onFinish: () => {
      isPromoting.value = false
    },
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
      card_terminal_id: data.card_terminal_id ? data.card_terminal_id.id : null,
      cashless_terminal_id: data.cashless_terminal_id ? data.cashless_terminal_id.id : null,
      claw_machine_board_id: data.claw_machine_board_id ? data.claw_machine_board_id.id : null,
      claw_machine_body_id: data.claw_machine_body_id ? data.claw_machine_body_id.id : null,
      lcd_monitor_id: data.lcd_monitor_id ? data.lcd_monitor_id.id : null,
      led_matrix_panel_id: data.led_matrix_panel_id ? data.led_matrix_panel_id.id : null,
      begin_date: data.begin_date && data.begin_date != 'Invalid date' ? data.begin_date : null,
      key_id: data.key_id ? data.key_id.id : null,
      // is_using_server_price: data.is_using_server_price.id === 'true' ? 1 : 0,
      menu_frame_id: data.menu_frame_id ? data.menu_frame_id.id : null,
      modem_type_id: data.modem_type_id ? data.modem_type_id.id : null,
      modem_unit_id: data.modem_unit_id ? data.modem_unit_id.id : null,
      server_price_type: data.server_price_type ? data.server_price_type.id : null,
      simcard_id: data.simcard_id ? data.simcard_id.id : null,
      termination_date: data.termination_date && data.termination_date != 'Invalid date' ? data.termination_date : null,
      operator_id: data.operator_id ? data.operator_id.id : null,
      is_fan_enabled: data.is_fan_enabled ? (data.is_fan_enabled.id === 'true' ? true : false) : true,
      product_mapping_id: data.product_mapping_id ? data.product_mapping_id.id : null,
      status: data.status.id,
      upcoming_product_mapping_id: data.upcoming_product_mapping_id ? data.upcoming_product_mapping_id.id : null,
      vend_config_id: data.vend_config_id ? data.vend_config_id.id : null,
      vend_contract_id: data.vend_contract_id ? data.vend_contract_id.id : null,
      vend_model_id: data.vend_model_id ? data.vend_model_id.id : null,
      vend_prefix_id: data.vend_prefix_id ? data.vend_prefix_id.id : null,
      vend_serial_number_id: data.vend_serial_number_id ? data.vend_serial_number_id.id : null,
      sticker_ids: data.sticker_id && data.sticker_id.id ? [data.sticker_id.id] : [],
      vend_vend_config_version: data.vend_vend_config_version ? data.vend_vend_config_version.id : null,
    }))
    .post('/vends/' + vendID + '/update', {
    onSuccess: () => {
      toast.success("Successfully Saved", {
        timeout: 3000
      });
    },
    onError: (errors) => {
      const errorCount = Object.keys(errors).length;
      toast.error(
        errorCount === 1
          ? `1 validation error — see details below`
          : `${errorCount} validation errors — see details below`,
        { timeout: 5000 }
      );
      nextTick(() => {
        const el = document.getElementById('vend-error-summary');
        if (el) {
          el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      });
    },
    preserveState: true,
    replace: true,
  })
}

function submit() {
  saveVend(form.value.id)
}

function syncApkSettings(vendID) {
  router.post('/vends/' + vendID + '/sync-apk-settings', {}, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    onSuccess: () => {
        toast.success('Sync APK Settings command sent successfully', {
            timeout: 3000
        });
    }
  })
}

function syncChannels(vendID) {
  router.post('/vends/' + vendID + '/sync-vend-channels', {}, {
    preserveScroll: true,
    preserveState: true,
    replace: true,
    onSuccess: () => {
      emit('modalClose')
    }
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
