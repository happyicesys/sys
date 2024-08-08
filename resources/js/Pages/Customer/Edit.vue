<template>
  <Head title="Customer" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        Editing Customer
      </h2>
    </template>

    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
        <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 p-5 mb-3">
            <form @submit.prevent="submit" id="submit">
              <div class="pb-5">
                <!-- Customer Header -->
                <div class="relative mb-5">
                  <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-gray-300"></div>
                  </div>
                  <div class="relative flex justify-start">
                    <span class="px-2 bg-white text-lg font-medium text-gray-900 rounded"> Customer </span>
                  </div>
                </div>

                <!-- Customer Form Fields -->
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-6 pb-2 mb-2">
                  <div class="sm:col-span-6 flex space-x-1">
                    <div
                      class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit"
                      :class="[customer.is_active ? 'bg-green-300' : 'bg-red-300']"
                    >
                      <span v-if="customer.is_active"> Active </span>
                      <span v-if="!customer.is_active"> Not Active </span>
                    </div>
                    <div
                      class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit bg-green-300"
                      v-if="customer.person_id"
                    >
                      <div class="flex flex-col"> From CMS </div>
                    </div>
                  </div>

                  <div class="sm:col-span-3" v-if="customer.id">
                    <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                      Customer ID#
                    </label>
                    <div class="mt-1">
                      <input
                        type="text"
                        class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                        :value="customer ? customer.id + 20000 : ''"
                        disabled
                      />
                    </div>
                  </div>
                  <div class="sm:col-span-3">
                    <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                      Operator <span class="text-red-500">*</span>
                    </label>
                    <MultiSelect
                      v-model="form.operator_id"
                      :options="operatorOptions"
                      trackBy="id"
                      valueProp="id"
                      label="full_name"
                      placeholder="Select"
                      open-direction="top"
                      class="mt-1"
                    ></MultiSelect>
                    <div class="text-sm text-red-600" v-if="form.errors.operator_id">
                      {{ form.errors.operator_id }}
                    </div>
                    <div class="text-blue-600 text-xs">
                      ** Changing Operator will change Vending Machine's Operator as well
                    </div>
                  </div>

                  <div class="sm:col-span-4" v-if="customer.id && customer.person_id">
                    <label for="text" class="flex justify-start text-sm font-medium text-gray-700"> Customer </label>
                    <div class="mt-1">
                      <input
                        type="text"
                        class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                        :value="customer.person_id ? ' (' + customer.virtual_customer_code + ')  ' + customer.name : customer.code + ' - ' + customer.name"
                        disabled
                      />
                    </div>
                    <div class="text-blue-600 text-xs" v-if="customer.person_id">
                      ** Customer Data only editable from CMS
                      <span>
                        <a :class="[form.person_id ? 'text-blue-700' : 'text-gray-500']" target="_blank" :href="'//admin.happyice.com.sg/person/' + form.person_id + '/edit'">
                          (Click Here)
                        </a>
                      </span>
                    </div>
                  </div>

                  <div class="sm:col-span-4 grid grid-cols-1 gap-3 sm:grid-cols-6" v-if="(customer.id && !customer.person_id) || (!customer.id && isExisting != 1)">
                    <div class="sm:col-span-5">
                      <FormInput v-model="form.name" :error="form.errors.name" required="true" :disabled="form.person_id">
                        Cust Name
                      </FormInput>
                    </div>
                  </div>

                  <div class="sm:col-span-2" v-if="customer.id">
                    <label for="text" class="flex justify-start text-sm font-medium text-gray-700"> Created At </label>
                    <div class="mt-1">
                      <input
                        type="text"
                        class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                        :value="formatDatetime(customer.created_at)"
                        disabled
                      />
                    </div>
                  </div>
                  <div class="sm:col-span-3">
                    <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                      Is Active? (Customer)
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
                    ></MultiSelect>
                    <div class="text-sm text-red-600" v-if="form.errors['customer.is_active']">
                      {{ form.errors['customer.is_active'] }}
                    </div>
                  </div>
                  <div class="sm:col-span-3">
                    <DatePicker v-model="form.begin_date" :error="form.errors.begin_date" @input="onDateFromChanged()" v-if="permissions.includes('update customers')">
                      Begin Date
                    </DatePicker>
                  </div>
                  <div class="sm:col-span-3">
                    <FormInput v-model="form.power_socket_key_number" :error="form.errors.power_socket_key_number"> Power Socket Key Num </FormInput>
                  </div>
                  <div class="sm:col-span-3">
                    <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                      Location Type
                    </label>
                    <MultiSelect
                      v-model="form.location_type_id"
                      :options="locationTypeOptions"
                      trackBy="id"
                      valueProp="id"
                      label="value"
                      placeholder="Select"
                      open-direction="bottom"
                      class="mt-1"
                    ></MultiSelect>
                    <div class="text-sm text-red-600" v-if="form.errors['customer.location_type_id']">
                      {{ form.errors['customer.location_type_id'] }}
                    </div>
                  </div>
                  <div class="sm:col-span-5">
                    <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                      Reference Price Type
                      <ExclamationCircleIcon class="w-5 h-5 self-center pl-1" v-tooltip="'Desired Price to be Set on Vending Machine'"></ExclamationCircleIcon>
                    </label>
                    <MultiSelect
                      v-model="form.selling_price_type"
                      :options="sellingPriceTypeOptions"
                      trackBy="id"
                      valueProp="id"
                      label="value"
                      placeholder="Select"
                      open-direction="bottom"
                      class="mt-1"
                      @selected="onSellingPriceTypeSelected"
                    ></MultiSelect>
                    <div class="text-sm text-red-600" v-if="form.errors['customer.selling_price_type']">
                      {{ form.errors['customer.selling_price_type'] }}
                    </div>
                  </div>
                </div>

                <!-- Menu Section -->
                <hr class="sm:col-span-6">
                <div class="sm:col-span-6 pb-1 md:pt-5 md:pb-3">
                  <div class="relative">
                    <div class="absolute inset-0 flex items-center" aria-hidden="true">
                      <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-start">
                      <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> Menu </span>
                    </div>
                  </div>
                </div>
                <div class="sm:col-span-6">
                  <AttachmentListProductMapping
                    :items="customer.vend.product_mapping.attachments"
                    :priceTypeOptions="sellingPriceTypeOptions"
                    v-if="customer.vend && customer.vend.product_mapping && customer.vend.product_mapping.attachments"
                  >
                  </AttachmentListProductMapping>

                </div>

                <!-- Binded Machine Section -->
                <div class="sm:col-span-6 pb-1 md:pt-5 md:pb-3">
                  <div class="relative">
                    <div class="absolute inset-0 flex items-center" aria-hidden="true">
                      <div class="w-full border-t border-gray-300"></div>
                    </div>
                    <div class="relative flex justify-start">
                      <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> Binded Machine </span>
                    </div>
                  </div>
                </div>
                <div class="sm:col-span-6" v-if="customer.vend">
                  <label for="text" class="flex justify-start text-sm font-medium text-gray-700"> Machine ID# </label>
                  <div class="mt-1">
                    <a :href="'/settings/vend/' + customer.vend.id + '/update'" target="_blank">
                      <input
                        type="text"
                        class="shadow-sm focus:ring-indigo-300 focus:border-indigo-300 block w-full text-sm border-gray-200 rounded-md bg-gray-100 hover:cursor-pointer text-blue-600 hover:text-blue-700"
                        :value="customer.vend.code"
                        readonly
                      />
                    </a>
                  </div>
                </div>
                <div class="sm:col-span-6" v-if="!customer.vend">
                  <label for="text" class="flex justify-start text-sm font-medium text-gray-700"> Machine ID# </label>
                  <MultiSelect
                    v-model="form.vend_id"
                    :options="vendOptions"
                    trackBy="id"
                    valueProp="id"
                    label="full_name"
                    placeholder="Select"
                    open-direction="bottom"
                    class="mt-1"
                  ></MultiSelect>
                </div>
                <div class="sm:col-span-6 mt-1">
                  <span class="flex justify-between">
                    <Button
                      type="button"
                      class="text-white flex space-x-1"
                      :class="!form.vend_id ? 'cursor-not-allowed bg-gray-400' : 'cursor-pointer bg-green-500 hover:bg-green-600'"
                      :disabled="!form.vend_id"
                      v-if="!customer.vend && permissions.includes('update customers')"
                      @click.prevent="saveCustomer(form.id)"
                    >
                      <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                      <span> Bind Machine </span>
                    </Button>
                    <Button
                      type="button"
                      class="bg-red-500 hover:bg-red-600 text-white flex space-x-1"
                      v-if="customer.vend && permissions.includes('update customers')"
                      @click.prevent="unbindCustomer(customer.vend.id)"
                    >
                      <XCircleIcon class="w-4 h-4"></XCircleIcon>
                      <span> Unbind Machine </span>
                    </Button>
                  </span>
                </div>

                <!-- Product Mapping Section -->
                <div class="sm:col-span-6" v-if="customer.vend && customer.vend.product_mapping">
                  <label for="text" class="flex justify-start text-sm font-medium text-gray-700"> Product Mapping </label>
                  <div class="mt-1">
                    <a :href="'/product-mappings/' + customer.vend.product_mapping.id + '/edit'" target="_blank">
                      <input
                        type="text"
                        class="shadow-sm focus:ring-indigo-300 focus:border-indigo-300 block w-full text-sm border-gray-200 rounded-md bg-gray-100 hover:cursor-pointer text-blue-600 hover:text-blue-700"
                        :value="customer.vend.product_mapping.name"
                        readonly
                      />
                    </a>
                  </div>
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
                              <th scope="col" class="w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900" v-if="customer.vend && customer.vend.product_mapping_id"> Image </th>
                              <th scope="col" class="w-3/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900" v-if="customer.vend && customer.vend.product_mapping_id"> Product </th>
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
                              <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900"> Ref Price {{ form.selling_price_type ? form.selling_price_type.id : '' }} </th>
                            </tr>
                          </thead>
                          <tbody class="bg-white">
                            <tr v-for="(channel, channelIndex) in vendChannels" :key="channel.id" :class="channelIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                              <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold sm:pl-6 text-center text-gray-900"> {{ channel.code }} </td>
                              <td class="whitespace-nowrap text-sm  font-semibold text-gray-900 text-center" v-if="customer.vend && customer.vend.product_mapping_id">
                                <div class="flex justify-center items-center">
                                  <img class="h-16 w-16 rounded-full" :src="channel.product.thumbnail.full_url" alt="" v-if="channel.product && channel.product.thumbnail"/>
                                </div>
                              </td>
                              <td class="py-4 text-sm font-semibold text-center text-gray-900" v-if="customer.vend && customer.vend.product_mapping_id">
                                <span v-if="channel.product && channel.product.code"> {{ channel.product.code }} </span>
                                <span class="break-normal text-xs" v-if="channel.product && channel.product.name"> <br> {{ channel.product.name }} </span>
                              </td>
                              <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center" :class="compareSellingPrice(channel)">
                                {{ (channel.amount/ (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                              </td>
                              <td
                                class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center text-gray-800"
                                v-if="vendChannels.some(channel => 'amount2' in channel)"
                              >
                                {{ (channel.amount2/ (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
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

                <!-- Contact Section -->
                <div class="sm:col-span-6 grid grid-cols-1 gap-3 sm:grid-cols-6" v-if="(customer.id && !customer.person_id) || (!customer.id && isExisting != 1)">
                  <div class="sm:col-span-6 pt-2 pb-1 md:pt-6 md:pb-3">
                    <div class="relative">
                      <div class="absolute inset-0 flex items-center" aria-hidden="true">
                        <div class="w-full border-t border-gray-300"></div>
                      </div>
                      <div class="relative flex justify-start">
                        <span class="px-4 bg-white text-lg font-medium text-gray-900 rounded"> Contact </span>
                      </div>
                    </div>
                  </div>
                  <div class="sm:col-span-3">
                    <FormInput v-model="form.contact.name" :error="form.errors['contact.name']" :disabled="customer.person_id"> Name </FormInput>
                  </div>
                  <div class="sm:col-span-3">
                    <FormInput v-model="form.contact.email" :error="form.errors['contact.email']" :disabled="customer.person_id"> Email </FormInput>
                  </div>
                  <div class="sm:col-span-2">
                    <label for="text" class="flex justify-start text-sm font-medium text-gray-700"> Phone Code </label>
                    <MultiSelect
                      v-model="form.contact.phone_country_id"
                      :options="countryOptions"
                      :disabled="form.person_id"
                      trackBy="id"
                      valueProp="id"
                      label="phone_code"
                      placeholder="Select"
                      open-direction="bottom"
                      class="mt-1"
                    ></MultiSelect>
                    <div class="text-sm text-red-600" v-if="form.errors['contact.phone_country_id']">
                      {{ form.errors['contact.phone_country_id'] }}
                    </div>
                  </div>
                  <div class="sm:col-span-4">
                    <FormInput v-model="form.contact.phone_num" :error="form.errors['contact.phone_num']" :disabled="customer.person_id"> Phone Number </FormInput>
                  </div>
                </div>

                <!-- Address Section -->
                <div class="sm:col-span-6 grid grid-cols-1 gap-3 sm:grid-cols-6">
                  <div class="sm:col-span-6 pt-2 mt-2 md:pt-5 md:pb-3">
                    <div class="relative">
                      <div class="absolute inset-0 flex items-center" aria-hidden="true">
                        <div class="w-full border-t border-gray-300"></div>
                      </div>
                      <div class="relative flex justify-start">
                        <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> Operations </span>
                      </div>
                    </div>
                  </div>
                  <div class="sm:col-span-3">
                    <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                      Zone
                    </label>
                    <MultiSelect
                      v-model="form.zone_id"
                      :options="zoneOptions"
                      trackBy="id"
                      valueProp="id"
                      label="value"
                      placeholder="Select"
                      open-direction="bottom"
                      class="mt-1"
                    ></MultiSelect>
                    <div class="text-sm text-red-600" v-if="form.errors['customer.zone_id']">
                      {{ form.errors['customer.zone_id'] }}
                    </div>
                  </div>
                  <div class="sm:col-span-3">
                    <FormTextarea v-model="form.ops_note" :error="form.errors.ops_note" rows="3">
                      Ops Note
                    </FormTextarea>
                  </div>
                  <div class="sm:col-span-6">
                      <label>Preferred Visit Days:</label>
                      <div class="flex flex-wrap gap-2 mt-2 space-x-3">
                        <label v-for="(day, index) in days" :key="index" class="flex items-center">
                          <input type="checkbox" v-model="form.preferred_visit_days_json[index]" class="mr-2 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
                          <span>
                            {{ day }}
                          </span>
                        </label>
                      </div>
                  </div>


                  <div class="sm:col-span-6 pt-2 mt-2 md:pt-5 md:pb-3">
                    <div class="relative">
                      <div class="absolute inset-0 flex items-center" aria-hidden="true">
                        <div class="w-full border-t border-gray-300"></div>
                      </div>
                      <div class="relative flex justify-start">
                        <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> Address </span>
                      </div>
                    </div>
                  </div>

                  <div class="sm:col-span-6">
                    <FormInput v-model="form.address.map_url" :error="form.errors['address.map_url']"> Google Map URL </FormInput>
                  </div>
                  <div class="sm:col-span-6">
                    <SearchAddressInput v-model="form.address.postcode" @selected="onAddressSelected" :error="form.errors['address.postcode']" :disabled="customer.person_id"> Postcode </SearchAddressInput>
                  </div>
                  <div class="col-span-3">
                    <FormInput v-model="form.address.unit_num" :error="form.errors['address.unit_num']"> Unit Num </FormInput>
                  </div>
                  <div class="col-span-3">
                    <FormInput v-model="form.address.block_num" :error="form.errors['address.block_num']" :disabled="customer.person_id"> Block Num </FormInput>
                  </div>
                  <div class="col-span-3">
                    <FormInput v-model="form.address.building" :error="form.errors['address.building']" :disabled="customer.person_id"> Building Name </FormInput>
                  </div>
                  <div class="col-span-5">
                    <FormTextarea v-model="form.address.street_name" :error="form.errors['address.street_name']" :disabled="customer.person_id">
                      Street Name
                    </FormTextarea>
                  </div>
                  <div class="col-span-3">
                    <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                      Country
                      <span class="text-red-500">
                        *
                      </span>
                    </label>
                    <MultiSelect
                      v-model="form.address.country_id"
                      :options="countryOptions"
                      trackBy="id"
                      valueProp="id"
                      label="name"
                      placeholder="Select"
                      open-direction="bottom"
                      class="mt-1"
                      :disabled="customer.person_id"
                    ></MultiSelect>
                    <div class="text-sm text-red-600" v-if="form.errors['address.country_id']">
                      {{ form.errors['address.country_id'] }}
                    </div>
                  </div>
                  <div class="sm:col-span-3 hidden">
                    <FormInput v-model="form.address.latitude"> Latitude </FormInput>
                  </div>
                  <div class="sm:col-span-3 hidden">
                    <FormInput v-model="form.address.longitude"> Longitude </FormInput>
                  </div>

                  <!-- Save and Delete Buttons -->
                  <div class="sm:col-span-6 mt-3 pt-2">
                    <span class="flex justify-between">
                      <span class="flex space-x-1">
                        <Button
                          type="button"
                          class="bg-green-500 hover:bg-green-600 text-white flex space-x-1"
                          v-if="permissions.includes('update customers')"
                          @click.prevent="saveCustomer(form.id)"
                        >
                          <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                          <span> Save Customer </span>
                        </Button>
                        <Link :href="'/customers'">
                          <Button class="bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1">
                            <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
                            <span> Back </span>
                          </Button>
                        </Link>
                      </span>
                      <span class="flex space-x-1">
                        <Button
                          type="button"
                          class="bg-yellow-500 hover:bg-yellow-600 text-gray-800 flex space-x-1"
                          v-if="permissions.includes('update customers') && customer.person_id"
                          @click.prevent="disconnectCMSCustomer(customer.id)"
                        >
                          <StopCircleIcon class="w-4 h-4"></StopCircleIcon>
                          <span> Disconnect from CMS </span>
                        </Button>
                        <Button
                          type="button"
                          class="bg-red-500 hover:bg-red-600 text-white flex space-x-1"
                          v-if="!customer.vend && permissions.includes('update customers')"
                          @click.prevent="deleteCustomer(customer.id)"
                        >
                          <XCircleIcon class="w-4 h-4"></XCircleIcon>
                          <span> Delete Customer </span>
                        </Button>
                      </span>
                    </span>
                  </div>
                </div>

                <!-- Attachment Section -->
                <div class="sm:col-span-6 mt-5 pb-1 md:pt-5 md:pb-3">
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
                  <AttachmentList :items="customer.attachments"></AttachmentList>
                </div>
                <div class="sm:col-span-6" v-if="customer.id">
                  <UploadFileInput :endpoint="'/customers/' + customer.id + '/upload-attachments'"></UploadFileInput>
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
import AttachmentListProductMapping from '@/Components/AttachmentListProductMapping.vue';
import Button from '@/Components/Button.vue';
import DatePicker from '@/Components/DatePicker.vue';
import FormInput from '@/Components/FormInput.vue';
import FormTextarea from '@/Components/FormTextarea.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import SearchAddressInput from '@/Components/SearchAddressInput.vue';
import UploadFileInput from '@/Components/UploadFileInput.vue';
import { ArrowUturnLeftIcon, CheckCircleIcon, ExclamationCircleIcon, StopCircleIcon, XCircleIcon } from '@heroicons/vue/20/solid';
import { Dropdown, Tooltip, Menu, vTooltip } from 'floating-vue';
import { ref, onMounted, watch } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";

const props = defineProps({
  vendOptions: Object,
  countries: Object,
  days: Object,
  locationTypeOptions: [Array, Object],
  operatorOptions: Object,
  customer: Object,
  sellingPriceTypeOptions: [Array, Object],
  type: String,
  zoneOptions: Object,
});

const form = ref(useForm(getDefaultForm()));
const adminCustomerOptions = ref([]);
const booleanStrictOptions = ref([
  { id: 'true', value: 'Yes' },
  { id: 'false', value: 'No' },
]);

const countryOptions = ref([]);
const customer = ref([]);
const isExisting = ref(1);
const locationTypeOptions = ref([]);
const operatorCountry = usePage().props.auth.operatorCountry;
const operatorOptions = ref([]);
const permissions = usePage().props.auth.permissions;
const profile = usePage().props.auth.profile;
const toast = useToast();
const vendChannels = ref([]);
const sellingPriceTypeOptions = ref([]);
const vendOptions = ref([]);
const zoneOptions = ref([]);

function getDefaultForm() {
  return {
    id: '',
    customer_id: '',
    person_id: '',
    operator_id: '',
    begin_date: '',
    location_type_id: '',
    ops_note: '',
    preferred_visit_days_json:  {
      1: false,
      2: false,
      3: false,
      4: false,
      5: false,
      6: false,
      7: false,
    },
    selling_price_type: '',
    termination_date: '',
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
    vend_id: '',
    zone_id: '',
  };
}

onMounted(() => {
  countryOptions.value = props.countries.data;
  customer.value = props.customer;
  locationTypeOptions.value = [
    { id: '', value: '--- Clear ---' },
    ...props.locationTypeOptions.data.map(locationType => ({
      id: locationType.id,
      value: locationType.name,
    }))
  ];

  operatorOptions.value = props.operatorOptions.data;
  zoneOptions.value = [
    { id: '', value: '--- Clear ---' },
    ...props.zoneOptions.data.map(zone => ({
      id: zone.id,
      value: zone.name,
    }))
  ];
  sellingPriceTypeOptions.value = Object.entries(props.sellingPriceTypeOptions).map(([id, value]) => {
    return {
      id: id,
      value: value,
    };
  });
  const initialPreferredVisitDays = {
    1: false,
    2: false,
    3: false,
    4: false,
    5: false,
    6: false,
    7: false,
  };
  form.value = props.customer ? useForm({
    ...JSON.parse(JSON.stringify(props.customer)),
    code: props.customer && props.customer.person_id ? props.customer.virtual_customer_code + ' (' + props.customer.virtual_customer_prefix + ')' : (props.customer ? props.customer.code : null),
    location_type_id: props.customer ? props.customer.location_type_id ? locationTypeOptions.value.find(locationType => locationType.id === props.customer.location_type_id) : null : null,
    operator_id: props.customer ? props.customer.operator_id ? operatorOptions.value.find(operator => operator.id === props.customer.operator_id) : null : null,
    vend_id: '',
    person_id: props.customer ? props.customer.person_id : null,
    contact: props.customer ? {
      ...JSON.parse(JSON.stringify(props.customer.contact)),
      phone_country_id: props.customer && props.customer.contact ? countryOptions.value.find(country => country.id === props.customer.contact.phone_country_id) : null,
    } : {
      name: '',
      email: '',
      phone_country_id: '',
      phone_num: '',
    },
    address: props.customer ? {
      ...props.customer.delivery_address,
      country_id: props.customer && props.customer.delivery_address ? countryOptions.value.find(country => country.id === props.customer.delivery_address.country_id) : null,
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
    is_active: props.customer ? props.customer.is_active ? booleanStrictOptions.value.find(option => option.id === 'true') : booleanStrictOptions.value.find(option => option.id === 'false') : booleanStrictOptions.value.find(option => option.id === 'true'),
    preferred_visit_days_json: { ...initialPreferredVisitDays, ...props.customer.preferred_visit_days_json },
    selling_price_type: props.customer && props.customer.selling_price_type ? sellingPriceTypeOptions.value.find(option => option.id == props.customer.selling_price_type) : null,
    zone_id: props.customer && props.customer.zone_id ? zoneOptions.value.find(zone => zone.id === props.customer.zone_id) : null,
  }) : useForm(getDefaultForm());

  vendChannels.value = props.customer && props.customer.vend ? props.customer.vend.vend_channels : [];

  vendOptions.value = props.vendOptions.map(vend => ({
    id: vend.id,
    full_name: vend.code,
  }));
});

function compareSellingPrice(channel) {
  if (channel.product && channel.product.selling_prices[0] && channel.product.selling_prices[0].amount) {
    if (channel.amount != channel.product.selling_prices[0].amount) {
      return 'text-red-500';
    }
  }
  return 'text-gray-800';
}

function deleteCustomer(customerID) {
  form.value.clearErrors();

  form.value.delete('/customers/' + customerID, {
    onSuccess: () => {
      router.push('/customers');
    },
    preserveState: true,
    replace: true,
  });
}

function disconnectCMSCustomer(customerID) {
  const approval = confirm('Are you sure to disconnect this customer from CMS ?');
  if (!approval) {
    return;
  }

  form.value.clearErrors();

  form.value.post('/customers/' + customerID + '/disconnect-cms', {
    onSuccess: () => {
      location.reload();
    },
    preserveState: true,
    replace: true,
  });
}

function formatDatetime(datetime) {
  return datetime ? moment(datetime).format('YYYY-MM-DD hh:mm a') : '';
}

function toggleActivationCustomer(customerID) {
  form.value.clearErrors();

  form.value.post('/customers/' + customerID + '/toggle-activation', {
    onSuccess: () => { },
    preserveState: true,
    replace: true,
  });
}

function saveCustomer(customerID) {
  form.value.clearErrors();

  form.value.transform((data) => ({
    id: data.vend_id ? data.vend_id.id : null,
    customer: {
      ...data,
      begin_date: data.begin_date && data.begin_date != 'Invalid date' ? data.begin_date : null,
      termination_date: data.termination_date && data.termination_date != 'Invalid date' ? data.termination_date : null,
      location_type_id: data.location_type_id ? data.location_type_id.id : null,
      operator_id: data.operator_id ? data.operator_id.id : null,
      contact: {
        ...data.contact,
        phone_country_id: data.contact.phone_country_id ? data.contact.phone_country_id.id : null,
      },
      address: {
        ...data.address,
        country_id: data.address.country_id ? data.address.country_id.id : null,
      },
      is_active: data.is_active.id,
      selling_price_type: data.selling_price_type ? data.selling_price_type.id : null,
      vend_id: data.vend_id ? data.vend_id.id : null,
      zone_id: data.zone_id ? data.zone_id.id : null,
    }
  })).post('/customers/' + customerID + '/update', {
    onSuccess: () => {
      router.reload({
        only: ['customer'],
        data: {
          id: form.value.id,
        },
        replace: true,
        preserveState: true,
        onSuccess: () => {
          customer.value = props.customer;
          vendChannels.value = props.customer.vend.vend_channels;
          vendChannels.value = [...vendChannels.value];
          toast.success("Successfully Saved", {
            timeout: 3000
          });
        },
        onError: (errors) => {
          toast.error("Failed, Please Try Again", {
            timeout: 3000
          });
        },
      });
    },
    preserveState: true,
    replace: true,
  });
}

function saveVend(vendID) {
  form.value.clearErrors();

  form.value.transform((data) => ({
    ...data,
    begin_date: data.begin_date && data.begin_date != 'Invalid date' ? data.begin_date : null,
    termination_date: data.termination_date && data.termination_date != 'Invalid date' ? data.termination_date : null,
    is_testing: data.is_testing.id,
  })).post('/customers/' + vendID + '/update', {
    onSuccess: () => { },
    preserveState: true,
    replace: true,
  });
}

function submit() {
  form.value.clearErrors();

  form.value.post('/customers/' + form.value.id + '/update', {
    onSuccess: () => {
      emit('modalClose');
    },
    preserveState: true,
    replace: true,
  });
}

function onAddressSelected(address) {
  form.value.address = {
    block_num: address.BLK_NO,
    building: address.BUILDING,
    country_id: countryOptions.value[0],
    latitude: address.LATITUDE,
    longitude: address.LONGTITUDE,
    postcode: address.POSTAL,
    street_name: address.ROAD_NAME,
    unit_num: '',
  };
}

function onSellingPriceTypeSelected() {
  router.reload({
    only: ['customer'],
    data: {
      id: form.value.id,
      selling_price_type: form.value.selling_price_type.id,
    },
    replace: true,
    preserveState: true,
    onSuccess: page => {
      customer.value = props.customer;
      vendChannels.value = props.customer.vend.vend_channels;
      vendChannels.value = [...vendChannels.value];
    }
  });
}

function bindVend(customerID) {
  form.value.clearErrors();

  form.value.post('/customers/' + customerID + '/bind-vend', {
    vendID: form.value.vend_id.id
  }, {
    onSuccess: () => { },
    preserveState: true,
    replace: true,
  });
}

function unbindCustomer(vendID) {
  form.value.clearErrors();

  form.value.post('/vends/' + vendID + '/unbind-customer/customers', {
    onSuccess: () => {
      router.reload({
        only: ['customer'],
        data: {
          id: form.value.id,
        },
        replace: true,
        preserveState: true,
        onSuccess: (page) => {
          customer.value = props.customer;
          vendChannels.value = props.customer.vend ? props.customer.vend.vend_channels : [];
        },
      });
    },
  });
}

function downloadVendSnapshot(vendSnapshotId) {
  axios({
    method: 'get',
    url: '/customers/customer-snapshots/excel/' + vendSnapshotId,
    responseType: 'blob',
  }).then(response => {
    fileDownload(response.data, 'Vending_Channels_' + moment().format('YYMMDDhhmmss') + '.xlsx');
  }).catch(error => {
    console.log(error);
  }).finally(() => { });
}
</script>

