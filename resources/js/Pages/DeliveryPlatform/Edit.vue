<template>
  <Head title="Delivery Product Mapping" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 leading-tight">
        {{ typeName }} Delivery Product Mapping
        <span v-if="type == 'update'">
          {{ deliveryProductMapping.name }}
        </span>
      </h2>
    </template>

     <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
        <div class="shadow-sm ring-1 ring-black ring-opacity-5 p-5">
          <form @submit.prevent="submit" id="submit">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-6 pb-2">
              <div class="sm:col-span-6">
                <FormInput v-model="form.name" :error="form.errors.name">
                  Name
                </FormInput>
              </div>
              <div class="sm:col-span-6">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Operator
                </label>
                <div class="mt-1">
                  <input
                    type="text"
                    class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                    :value="props.deliveryProductMapping.data.operator.full_name"
                    disabled
                  />
                </div>
              </div>
              <div class="sm:col-span-6">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Delivery Platform
                </label>
                <div class="mt-1">
                  <input
                    type="text"
                    class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                    :value="props.deliveryProductMapping.data.deliveryPlatformOperator.deliveryPlatform.name  + ' (' + props.deliveryProductMapping.data.deliveryPlatformOperator.type + ')'"
                    disabled
                  />
                </div>
              </div>
              <div class="sm:col-span-6">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Platform Category
                </label>
                <div class="mt-1">
                  <input
                    type="text"
                    class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                    :value="props.deliveryProductMapping.data.category_json.id + ' - ' + props.deliveryProductMapping.data.category_json.name"
                    disabled
                  />
                </div>
              </div>
              <div class="sm:col-span-6">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Refer to Product Mapping
                </label>
                <div class="mt-1">
                  <input
                    type="text"
                    class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                    :value="props.deliveryProductMapping.data.productMapping.name"
                    disabled
                  />
                </div>
              </div>
              <div class="sm:col-span-3">
                <FormInput v-model="form.reserved_percent" :error="form.errors.reserved_percent">
                  Reserved Percentage (%)
                </FormInput>
              </div>
              <div class="sm:col-span-3">
                <FormInput v-model="form.reserved_qty" :error="form.errors.reserved_qty">
                  Reserved Quantity
                </FormInput>
              </div>
              <div class="sm:col-span-6">
                <label for="reserved" class="italic text-blue-800">
                  By setting "Reserved Percentage" and "Reserved Quantity", the sellable qty equivalent to whichever higher. If lower than reserved, channel becomes inactive, both default value are 0.
                </label>
              </div>
              <div class="sm:col-span-6">
              <div class="flex space-x-1 mt-5 justify-end">
                <Link href="/delivery-product-mappings">
                  <Button
                    class="bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1"
                  >
                    <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
                    <span>
                      Back
                    </span>
                  </Button>
                </Link>
                <Button
                  type="submit"
                  class="bg-green-500 hover:bg-green-600 text-white flex space-x-1"
                >
                  <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                  <span>
                    Save
                  </span>
                </Button>
              </div>
            </div>
            <!-- <div class="sm:col-span-6" v-if="form.product_mapping_id"> -->
              <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
                <div class="relative">
                  <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-gray-300"></div>
                  </div>
                  <div class="relative flex justify-center">
                    <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded">Delivery Platform Product(s) </span>
                  </div>
                </div>
              </div>

              <div v-if="form.id">
                <FormInput v-model="form.channel_code" :error="form.errors.channel_code" placeholderStr="Channel ID">
                  Channel ID
                </FormInput>
              </div>
              <div v-if="form.id">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Product
                </label>
                <MultiSelect
                  v-model="form.product_id"
                  :options="productOptions"
                  trackBy="id"
                  valueProp="id"
                  label="full_name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.product_id">
                  {{ form.errors.product_id }}
                </div>
              </div>
              <div v-if="form.id">
                <FormInput v-model="form.amount" :error="form.errors.amount" placeholderStr="Platform Price">
                  Price
                </FormInput>
              </div>
              <div v-if="form.id">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Platform SubCategory
                </label>
                <MultiSelect
                  v-model="form.sub_category_json"
                  :options="subCategoryOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
              </div>

              <div class="sm:col-span-1" v-if="form.product_mapping_id">
                <Button
                type="button"
                @click.prevent="addDeliveryProductMappingItem()"
                class="bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6"
                :class="[!form.channel_code || !form.product_id ? 'opacity-50 cursor-not-allowed' : '']"
                :disabled="!form.channel_code || !form.product_id"
                >
                  <PlusCircleIcon class="w-4 h-4"></PlusCircleIcon>
                  <span>
                    Add
                  </span>
                </Button>
              </div>

              <div class="sm:col-span-6 flex flex-col mt-3" v-if="form.product_mapping_id">
              <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5">
                <div class="inline-block min-w-full py-2 align-middle md:px-4 lg:px-6">
                  <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                    <table class="table-fixed min-w-full divide-y divide-gray-300">
                      <thead class="bg-gray-50">
                        <tr>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            #
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Channel ID
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Thumbnail
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Product
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Status
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Price
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Platform SubCategory
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Action
                          </th>
                        </tr>
                      </thead>
                      <tbody class="bg-white">
                        <tr v-for="(deliveryProductMappingItem, deliveryProductMappingItemIndex) in props.deliveryProductMapping.data.deliveryProductMappingItems" :key="deliveryProductMappingItem.id" :class="deliveryProductMappingItemIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                            {{ deliveryProductMappingItemIndex + 1 }}
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                            {{ deliveryProductMappingItem.channel_code }}
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                            <div class="flex justify-center">
                              <img class="h-24 w-24 md:h-20 md:w-20 rounded-full" :src="deliveryProductMappingItem.product.thumbnail.full_url" alt="" v-if="deliveryProductMappingItem.product && deliveryProductMappingItem.product.thumbnail"/>
                            </div>
                          </td>
                          <td class="whitespace-normal py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left flex flex-col">
                            <span v-if="deliveryProductMappingItem.product.code">
                              {{ deliveryProductMappingItem.product.code }}
                            </span>
                            <span class="break-words" v-if="deliveryProductMappingItem.product.name">
                              {{ deliveryProductMappingItem.product.name }}
                            </span>
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                            <span class="inline-flex items-center rounded-md bg-green-300 px-1.5 py-0.5 text-xs font-medium text-green-800 ring-1 ring-inset ring-indigo-700/10" v-if="deliveryProductMappingItem.is_active == 1">
                              Active
                            </span>
                            <span class="inline-flex items-center rounded-md bg-red-200 px-1.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-indigo-700/10" v-if="deliveryProductMappingItem.is_active == 0">
                              Paused
                            </span>
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                            {{ deliveryProductMappingItem.amount.toLocaleString(undefined, {minimumFractionDigits: (props.deliveryProductMapping.data.operator.country.is_currency_exponent_hidden ? 0 : props.deliveryProductMapping.data.operator.country.currency_exponent), maximumFractionDigits: (props.deliveryProductMapping.data.operator.country.is_currency_exponent_hidden ? 0 : props.deliveryProductMapping.data.operator.country.currency_exponent)}) }}
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                            {{ deliveryProductMappingItem.sub_category_json.name }}
                          </td>
                          <td class="whitespace-nowrap py-4 pr-2 text-sm text-center">
                            <Button
                              class="bg-gray-300 hover:bg-gray-400 text-black flex space-x-1"
                              @click.prevent="onItemEditClicked(deliveryProductMappingItem)"
                            >
                              <PencilSquareIcon class="w-3 h-3"></PencilSquareIcon>
                              <span class="text-xs">
                                Edit
                              </span>
                            </Button>
                          </td>
                        </tr>
                        <tr v-if="!props.deliveryProductMapping.data.deliveryProductMappingItems || !props.deliveryProductMapping.data.deliveryProductMappingItems.length">
                          <td colspan="7" class="whitespace-nowrap py-4 text-sm font-medium text-gray-600 text-center">
                            No Records Found
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>

              <!-- <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
                <div class="relative">
                  <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-gray-300"></div>
                  </div>
                  <div class="relative flex justify-center">
                    <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded">Bundle Sales</span>
                  </div>
                </div>
              </div>
              <div v-if="form.id" class="sm:col-span-6">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  (Create New) Type
                </label>
                <MultiSelect
                  v-model="form.promo_label"
                  :options="bundleSalesOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                  @selected="onBundleSalesSelected"
                >
                </MultiSelect>
              </div>
              <div v-if="form.promo_label" class="sm:col-span-6">
                <FormInput v-model="form.bundle_name" :error="form.errors.bundle_name" placeholderStr="Name">
                  Template Name
                </FormInput>
              </div>
              <div v-if="form.promo_label" class="sm:col-span-3">
                <FormInput v-model="form.total_qty" :error="form.errors.total_qty" placeholderStr="Total Qty">
                  Total Qty
                </FormInput>
              </div>
              <div v-if="form.promo_label" class="sm:col-span-3">
                <FormInput v-model="form.promo_value" :error="form.errors.promo_value" placeholderStr="Value">
                  Value (final price OR value off OR percentage)
                </FormInput>
              </div>
              <span class="sm:col-span-6 text-blue-700 text-semibold">
                {{ bundleDesc }}
              </span>
              <span v-if="form.promo_label" class="sm:col-span-5">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Product
                </label>
                <MultiSelect
                  v-model="form.delivery_product_mapping_item_id"
                  :options="deliveryProductMappingItemOptions"
                  trackBy="id"
                  valueProp="id"
                  label="full_name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
              </span>
              <div class="sm:col-span-1" v-if="form.product_mapping_id">
                <Button
                type="button"
                @click.prevent="addBundleSalesItem()"
                class="bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6"
                :class="[!isBundleAddable ? 'opacity-50 cursor-not-allowed' : '']"
                :disabled="!isBundleAddable"
                v-if="form.promo_label"
                >
                  <PlusCircleIcon class="w-4 h-4"></PlusCircleIcon>
                  <span>
                    Add
                  </span>
                </Button>
              </div>
              <span v-if="bundleSalesErrorMsg" class="sm:col-span-6 text-red-400 text-sm font-bold">
                {{ bundleSalesErrorMsg }}
              </span>
              <div class="sm:col-span-6 flex flex-col mt-3" v-if="form.product_mapping_id">
                <ul role="list" class="divide-y divide-gray-100 overflow-hidden bg-white shadow-sm ring-1 ring-gray-900/5 sm:rounded-xl">
                  <li v-for="(bundleSalesItem, bundleSalesItemIndex) in bundleSalesItems" class=" flex justify-between gap-x-6 px-4 py-5 hover:bg-gray-50 sm:px-6">
                    <div class="flex min-w-0 gap-x-4">
                      <span class="text-sm pt-3">
                        {{ bundleSalesItemIndex + 1 }}
                      </span>
                      <img class="h-12 w-12 flex-none rounded-full bg-gray-50" :src="bundleSalesItem.delivery_product_mapping_item_id.img_url" alt="">
                      <div class="min-w-0 flex-auto pt-3">
                        <p class="text-sm font-semibold leading-6 text-gray-900">
                          <span class="absolute inset-x-0 -top-px bottom-0" />
                          {{ bundleSalesItem.delivery_product_mapping_item_id.full_name }}
                        </p>
                      </div>
                    </div>
                    <div class="flex items-center gap-x-3">
                      <Button
                        @click.prevent="removeBundleSalesItem(bundleSalesItemIndex)"
                        class="flex space-x-1 bg-red-500 hover:bg-red-600 text-white"
                      >
                        <XCircleIcon class="w-4 h-4" ></XCircleIcon>
                      </Button>
                    </div>
                  </li>
                </ul>
              </div>
              <div class="sm:col-span-6" v-if="form.product_mapping_id">
                <Button
                type="button"
                @click.prevent="saveBundleSales()"
                class="bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-1"
                :class="[!isBundleCompleted ? 'opacity-50 cursor-not-allowed' : '']"
                :disabled="!isBundleCompleted"
                v-if="form.promo_label"
                >
                  <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                  <span>
                    Save Bundle
                  </span>
                </Button>
              </div>

              <div class="sm:col-span-6 flex flex-col mt-2 mx-5 mb-3" v-if="form.product_mapping_id">
                <div class="mt-6 flex flex-col">
                  <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8 px-3">
                      <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll">
                        <table class="min-w-full border-separate" style="border-spacing: 0">
                            <thead class="bg-gray-100">
                              <tr class="divide-x divide-gray-200">
                                <TableHead>
                                  #
                                </TableHead>
                                <TableHead>
                                  Bundle Name
                                </TableHead>
                                <TableHead>
                                  Type
                                </TableHead>
                                <TableHead>
                                  (Channel) Product
                                </TableHead>
                                <TableHead>
                                  Action
                                </TableHead>
                              </tr>
                            </thead>
                            <tbody class="bg-white">
                              <tr v-for="(deliveryProductMappingBulk, deliveryProductMappingBulkIndex) in props.deliveryProductMapping.data.deliveryProductMappingBulks" :key="deliveryProductMappingBulk.id" class="divide-x divide-gray-300">
                                <TableData :currentIndex="deliveryProductMappingBulkIndex" :totalLength="deliveryProductMappingBulks.length" inputClass="text-center">
                                  {{ deliveryProductMappingBulkIndex + 1 }}
                                </TableData>
                                <TableData :currentIndex="deliveryProductMappingBulkIndex" :totalLength="deliveryProductMappingBulks.length" inputClass="text-center">
                                  {{ deliveryProductMappingBulk.name }}
                                </TableData>
                                <TableData :currentIndex="deliveryProductMappingBulkIndex" :totalLength="deliveryProductMappingBulks.length" inputClass="text-center">
                                  {{ deliveryProductMappingBulk.promo_desc }}
                                </TableData>
                                <TableData :currentIndex="deliveryProductMappingBulkIndex" :totalLength="deliveryProductMappingBulks.length" inputClass="text-left">
                                  <ul class="divide-y divide-gray-200">
                                    <li class="flex py-1 px-3 space-x-2" v-for="deliveryProductMappingBulkItem in deliveryProductMappingBulk.deliveryProductMappingBulkItems">
                                      <span class="self-center font-semibold text-blue-700">
                                        (#{{ deliveryProductMappingBulkItem.deliveryProductMappingItem.channel_code }})
                                      </span>
                                      <div class="flex self-center">
                                        <a :href="deliveryProductMappingBulkItem.deliveryProductMappingItem.product.thumbnail.full_url" target="_blank" v-if="deliveryProductMappingBulkItem.deliveryProductMappingItem.product.thumbnail">
                                          <img class="object-scale-down h-24 w-24 md:h-16 md:w-20 rounded-full" :src="deliveryProductMappingBulkItem.deliveryProductMappingItem.product.thumbnail.full_url" alt="" />
                                        </a>
                                      </div>
                                      <span class="mt-3">
                                        {{ deliveryProductMappingBulkItem.deliveryProductMappingItem.product.code }} <br>
                                        {{ deliveryProductMappingBulkItem.deliveryProductMappingItem.product.name }}
                                      </span>
                                    </li>
                                  </ul>
                                </TableData>
                                <TableData :currentIndex="deliveryProductMappingBulkIndex" :totalLength="deliveryProductMappingBulks.length" inputClass="text-center">
                                    <Button
                                      @click.prevent="removeDeliveryProductMappingBulk(deliveryProductMappingBulk.id)"
                                      class="flex flex-col space-x-1 bg-red-500 hover:bg-red-600 text-white"
                                      :disabled="deliveryProductMappingBulk.delivery_platform_campaign_id"
                                      :class="[deliveryProductMappingBulk.delivery_platform_campaign_id ? 'opacity-50 cursor-not-allowed' : '']"
                                    >
                                      <XCircleIcon class="w-4 h-4" ></XCircleIcon>
                                      <span v-if="deliveryProductMappingBulk.delivery_platform_campaign_id">
                                        (Campaigns running, cannot delete)
                                      </span>
                                    </Button>
                                </TableData>
                              </tr>
                               <tr v-if="!deliveryProductMappingBulks.length">
                                <td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
                                    No Results Found
                                </td>
                              </tr>
                            </tbody>
                        </table>
                      </div>
                  </div>
              </div>
              </div> -->

             <!-- <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">  -->
              <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3" v-if="form.product_mapping_id">
                <div class="relative">
                  <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-gray-300"></div>
                  </div>
                  <div class="relative flex justify-center">
                    <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> Vending Machine Binding </span>
                  </div>
                </div>
              </div>

              <div class="sm:col-span-3" v-if="form.product_mapping_id">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Vending Machine
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
                >
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.vend_id">
                  {{ form.errors.vend_id }}
                </div>
              </div>

              <div class="sm:col-span-2" v-if="form.product_mapping_id">
                <FormInput v-model="form.platform_ref_id" :error="form.errors.platform_ref_id" placeholderStr="Platform ID">
                  Platform ID (Store ID)
                </FormInput>
              </div>

              <div class="sm:col-span-1" v-if="form.product_mapping_id">
                <Button
                type="button"
                @click.prevent="bindVend(form.vend_id.id)"
                class="bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6"
                :class="[!form.vend_id ? 'opacity-50 cursor-not-allowed' : '']"
                :disabled="!form.vend_id"
                >
                  <PlusCircleIcon class="w-4 h-4"></PlusCircleIcon>
                  <span>
                    Add
                  </span>
                </Button>
              </div>

              <div class="sm:col-span-6">
                <Button
                  class="flex space-x-1 bg-yellow-300 hover:bg-yellow-400 text-black"
                  @click.prevent="togglePauseAllVends()"
                >
                  <PauseCircleIcon class="w-3 h-3"></PauseCircleIcon>
                  <span class="text-xs">
                    Pause All VM
                  </span>
                </Button>
              </div>

              <div class="sm:col-span-6 flex flex-col mt-3" v-if="form.product_mapping_id">
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
                            Vend ID <br>
                            (Platform Ref ID)
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Vend Name
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Channel Status
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            VM Status
                          </th>
                          <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                            Action
                          </th>
                        </tr>
                      </thead>
                      <tbody class="bg-white">
                        <tr v-for="(deliveryProductMappingVend, vendIndex) in props.deliveryProductMapping.data.deliveryProductMappingVends" :key="deliveryProductMappingVend.id" :class="vendIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                            {{ vendIndex + 1 }}
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                            {{ deliveryProductMappingVend.vend.code }} <br>
                            ({{ deliveryProductMappingVend.platform_ref_id }})
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left">
                            <span v-if="deliveryProductMappingVend.vend.latestVendBinding && deliveryProductMappingVend.vend.latestVendBinding.customer">
                              {{ deliveryProductMappingVend.vend.latestVendBinding.customer.code }} <br>
                              {{ deliveryProductMappingVend.vend.latestVendBinding.customer.name }}
                            </span>
                            <span v-else>
                              {{ deliveryProductMappingVend.vend.name }}
                            </span>
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                            <ul
                              class="sm:grid sm:grid-cols-[105px_minmax(110px,_1fr)_100px] hover:cursor-pointer"
                              v-if="deliveryProductMappingVend.deliveryProductMappingVendChannels"
                              @click="onChannelOverviewClicked(deliveryProductMappingVend)"
                              >
                                  <li v-for="(channel, channelIndex) in deliveryProductMappingVend.deliveryProductMappingVendChannels"
                                      class="quick-look"
                                      :class="[channelIndex > 0 && (String(channel['vend_channel_code'])[0] !== String(deliveryProductMappingVend.deliveryProductMappingVendChannels[channelIndex - 1]['vend_channel_code'])[0]) ? 'col-start-1' : '']"
                                  >
                                  <span :class="[channelIndex > 0 && (String(channel['vend_channel_code'])[0] !== String(deliveryProductMappingVend.deliveryProductMappingVendChannels[channelIndex - 1]['vend_channel_code'])[0]) ? 'border-t-4 pt-1' : '']" class="flex space-x-2">
                                      <span>
                                        #{{channel.vend_channel_code}}
                                      </span>
                                      <CheckCircleIcon v-if="channel.is_active == 1" class="w-4 h-4 fill-green-500"></CheckCircleIcon>
                                      <PauseCircleIcon v-else class="w-4 h-4 fill-red-500"></PauseCircleIcon>
                                  </span>
                                  </li>
                              </ul>
                          </td>
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                            <span class="inline-flex items-center rounded-md bg-green-300 px-1.5 py-0.5 text-xs font-medium text-green-800 ring-1 ring-inset ring-indigo-700/10" v-if="deliveryProductMappingVend.is_active == 1">
                              Operating
                            </span>
                            <span class="inline-flex items-center rounded-md bg-red-200 px-1.5 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-indigo-700/10" v-if="deliveryProductMappingVend.is_active == 0">
                              Paused
                            </span>
                          </td>
                          <td class="whitespace-nowrap py-2 text-xs text-center p-3">
                            <div class="flex flex-col space-y-1">
                              <Button
                                class="flex space-x-1"
                                :class="[deliveryProductMappingVend.is_active ? 'bg-yellow-300 hover:bg-yellow-400 text-black' : 'bg-green-500 hover:bg-green-600 text-white']"
                                @click.prevent="togglePauseVend(deliveryProductMappingVend)"
                              >
                                <PauseCircleIcon class="w-3 h-3" v-if="deliveryProductMappingVend.is_active"></PauseCircleIcon>
                                <PlayCircleIcon class="w-3 h-3" v-else></PlayCircleIcon>
                                <span class="text-xs" v-if="deliveryProductMappingVend.is_active">
                                  Pause VM
                                </span>
                                <span class="text-xs" v-else>
                                  Resume VM
                                </span>
                              </Button>
                              <Button
                                class="flex space-x-1 bg-red-500 hover:bg-red-600 text-white"
                                v-if="!deliveryProductMappingVend.is_active"
                                @click.prevent="unbindVend(deliveryProductMappingVend.id)"
                              >
                                <XCircleIcon class="w-3 h-3" ></XCircleIcon>
                                <span class="text-xs">Unbind VM</span>
                              </Button>
                            </div>
                          </td>
                        </tr>
                        <tr v-if="!props.deliveryProductMapping.data.deliveryProductMappingVends.length">
                          <td colspan="6" class="whitespace-nowrap py-4 text-sm font-medium text-gray-600 text-center">
                            No Records Found
                          </td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
              </div>
          </div>
          </form>
        </div>
      </div>
    </div>
  </div>
  <ChannelOverview
    v-if="showChannelOverviewModal"
    :vend="vend"
    :deliveryProductMapping="props.deliveryProductMapping.data"
    :showModal="showChannelOverviewModal"
    @modalClose="onChannelOverviewClosed"
  >
  </ChannelOverview>
  <EditItem
    v-if="showItemEditModal"
    :deliveryProductMapping="props.deliveryProductMapping.data"
    :deliveryProductMappingItemObj="deliveryProductMappingItemObj"
    :showModal="showItemEditModal"
    @modalClose="onItemEditClosed"
  >
  </EditItem>
  </BreezeAuthenticatedLayout>
</template>

<style setup>
	.quick-look
	{
		-webkit-border-horizontal-spacing: 0px;
		-webkit-border-image: none;
		-webkit-border-vertical-spacing: 0px;
		border-bottom-color: white;
		border-bottom-left-radius: 3px;
		border-bottom-right-radius: 3px;
		border-bottom-style: none;
		border-width: 0px;
		border-collapse: separate;
		border-left-color: white;
		border-left-style: none;
		border-right-color: white;
		border-right-style: none;
		border-top-color: white;
		border-top-left-radius: 3px;
		border-top-right-radius: 3px;
		border-top-style: none;
		line-height: 14px;
		max-width: none;
		text-align: left;
		vertical-align: baseline;
		white-space: nowrap;
		padding:5px;
		margin:3px;
		display:block;
		float:left;
		/* width:170px; */
		font-size:13px;
	}
</style>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import ChannelOverview from '@/Pages/DeliveryPlatform/ChannelOverview.vue';
import EditItem from '@/Pages/DeliveryPlatform/EditItem.vue';
import FormInput from '@/Components/FormInput.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import TableData from '@/Components/TableData.vue';
import TableHead from '@/Components/TableHead.vue';
import { ArrowUturnLeftIcon, CheckCircleIcon, PauseCircleIcon, PencilSquareIcon, PlayCircleIcon, PlusCircleIcon, XCircleIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted, computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';

const props = defineProps({
    bundleSalesOptions: [Array, Object],
    deliveryProductMapping: Object,
    productMappingItems: Object,
    productOptions: Object,
    type: String,
    unbindedVendOptions: [Array, Object],
  })

  const bundleSalesErrorMsg = ref('')
  const bundleSalesItems = ref([])
  const bundleSalesOptions = ref([])
  const deliveryProductMapping = ref([])
  const deliveryProductMappingBulks = ref([])
  const deliveryProductMappingItemObj = ref([])
  const deliveryProductMappingItemOptions = ref([])
  const form = ref(
    useForm(getDefaultForm())
  )
  const loading = ref(false)
  const isBundleSingleSKU = ref(true)
  const productMappingItems = ref([])
  const productOptions = ref([])
  const showChannelOverviewModal = ref(false)
  const showItemEditModal = ref(false)
  const subCategoryOptions = ref([])
  const typeName = ref('')
  const roles = usePage().props.auth.roles
  // const permissions = usePage().props.auth.permissions
  const vend = ref()
  const unbindedVendOptions = ref([])

onMounted(() => {
    if(props.type == 'create') {
        typeName.value = 'Create New'
    } else {
        typeName.value = 'Edit'
    }
    bundleSalesOptions.value = [
      ...props.bundleSalesOptions.map((data) => {return {
        id: data.id,
        name: data.name,
        is_same: data.is_same,
        type: data.type,
        phrase_1: data.phrase_1,
        phrase_2: data.phrase_2,
        phrase_3: data.phrase_3,
      }})
    ]
    deliveryProductMapping.value = props.deliveryProductMapping.data
    deliveryProductMappingBulks.value = props.deliveryProductMapping.data.deliveryProductMappingBulks
    deliveryProductMappingItemOptions.value = props.deliveryProductMapping.data.deliveryProductMappingItems.map((data) => {return {
      id: data.id,
      full_name: '(#' + data.channel_code + ') ' + data.product.code + ' ' + data.product.name,
      img_url: data.product.thumbnail.full_url,
      amount: data.amount,
    }})
    form.value = deliveryProductMapping.value ?
      useForm(deliveryProductMapping.value) :
      useForm(getDefaultForm())

    unbindedVendOptions.value = [
      ...props.unbindedVendOptions.data.map((data) => {return {id: data.id, full_name: data.full_name}})
    ]
})

const bundleDesc = computed(function() {
  if(form.value.promo_label && form.value.promo_value && form.value.total_qty) {
    return form.value.promo_label.phrase_1 + form.value.total_qty + form.value.promo_label.phrase_2 + form.value.promo_value + form.value.promo_label.phrase_3
  }else {
    return ''
  }
})

const isBundleAddable = computed(function() {
  let isAddable = true
  bundleSalesErrorMsg.value = ''

  if(!form.value.promo_label || !form.value.promo_value || !form.value.delivery_product_mapping_item_id) {
    isAddable = false
  }

  if(form.value.total_qty == 0) {
    isAddable = false
  }

  if(isBundleSingleSKU.value && bundleSalesItems.value.length == 1) {
    isAddable = false
  }

  if(!isBundleSingleSKU.value && bundleSalesItems.value.length > 0 && bundleSalesItems.value.filter((item) => {return form.value.delivery_product_mapping_item_id.id == item.delivery_product_mapping_item_id.id}).length > 0) {
    isAddable = false
    bundleSalesErrorMsg.value = 'Product already added'
  }

  return isAddable
})

const isBundleCompleted = computed(function() {
  let isCompleted = false

  if(bundleSalesItems.value.length > 1 && !isBundleSingleSKU.value) {
    isCompleted = true
  }

  if(bundleSalesItems.value.length == 1 && isBundleSingleSKU.value) {
    isCompleted = true
  }

  return isCompleted
})

function getDefaultForm() {
  return {
    id: '',
    bundle_name: '',
    category_json: '',
    delivery_platform_operator_id: '',
    name: '',
    operator_id: '',
    platform_ref_id: '',
    product_mapping_id: '',
    promo_label: '',
    promo_type: '',
    promo_value: '',
    reserved_percent: 0,
    reserved_qty: 0,
    sub_category_json: '',
    total_amount: '',
    total_qty: '',
  }
}

function addBundleSalesItem() {
  if(form.value.delivery_product_mapping_item_id) {
    bundleSalesItems.value.push({
      delivery_product_mapping_item_id: form.value.delivery_product_mapping_item_id,
    })
  }
}

function addDeliveryProductMappingItem() {
  if(productMappingItems.value.map(function(productMapping) { return productMapping.channel_code; }).indexOf(form.value.channel_code) < 0) {
    router.post('/delivery-product-mapping-items/delivery-product-mapping/' + form.value.id + '/store', {
      ...form.value,
        product_id: form.value.product_id ? form.value.product_id.id : null,
    },{
        preserveState: false,
        preserveScroll: true,
        replace: true,
    })
  }
}

function bindVend(vendId) {
  router.post(
    '/delivery-product-mappings/' + form.value.id + '/bind-vend', {
      vend_id: vendId,
      platform_ref_id: form.value.platform_ref_id,
    }, {
    preserveState: false,
    preserveScroll: true,
    replace: true,
  })
}

function onBundleSalesSelected() {
  if(form.value.promo_label.is_same == true) {
    isBundleSingleSKU.value = true
  }else {
    isBundleSingleSKU.value = false
  }
}

function onChannelOverviewClicked(deliveryProductMappingVend) {
  vend.value = deliveryProductMappingVend
  showChannelOverviewModal.value = true
}

function onChannelOverviewClosed() {
  showChannelOverviewModal.value = false
}

function onItemEditClicked(deliveryProductMappingItem) {
  deliveryProductMappingItemObj.value = deliveryProductMappingItem
  showItemEditModal.value = true
}

function onItemEditClosed() {
  showItemEditModal.value = false
}

function removeBundleSalesItem(bundleSalesItemIndex) {
  bundleSalesItems.value.splice(bundleSalesItemIndex, 1)
}

function removeDeliveryProductMappingBulk(deliveryProductMappingBulkID) {
  const approval = confirm('Are you sure to delete this entry?');
  if (!approval) {
      return;
  }
  router.delete('/delivery-product-mappings/bulks/' + deliveryProductMappingBulkID, {
      preserveState: false,
      preserveScroll: true,
      replace: true,
  })
}

function removeDeliveryProductMappingItem(productMappingItem) {
  form.value
    .delete('/delivery-product-mapping-items/' + productMappingItem.id, {
      preserveState: false,
      replace: true,
    })
}

function saveBundleSales()
{
  router.post('/delivery-product-mappings/' + form.value.id + '/save-bundle-sales', {
      bundle_label: form.value.promo_label.id,
      bundle_name: form.value.bundle_name,
      bundle_type: form.value.promo_label.type,
      bundle_value: form.value.promo_value,
      bundle_desc: bundleDesc.value,
      bundleSalesItems: bundleSalesItems.value && bundleSalesItems.value.length > 0 ? bundleSalesItems.value.map((data) => {return {id: data.delivery_product_mapping_item_id.id, qty: data.qty}}) : [],
      total_qty: form.value.total_qty,
  }, {
      preserveState: false,
      preserveScroll: true,
      replace: true,
  })
}

function submit() {
  form.value.clearErrors()
  form.value
  .transform((data) => ({
    name: data.name,
    reserved_percent: data.reserved_percent,
    reserved_qty: data.reserved_qty,
  }))
  .post('/delivery-product-mappings/' + form.value.id + '/update', {
    preserveState: true,
    replace: true,
  })
}

function togglePauseAllVends() {
  let approvalText =  'Are you sure to pause all vending machines?'
  const approval = confirm(approvalText);
  if (!approval) {
      return;
  }
  router.post('/delivery-product-mappings/' + form.value.id + '/toggle-pause-all-vends', {}, {
      preserveState: false,
      preserveScroll: true,
      replace: true,
  })
}

function togglePauseVend(deliveryProductMappingVend) {
  let approvalText = deliveryProductMappingVend.is_active ? 'Are you sure to pause this vending machine?' : 'Are you sure to resume this vending machine?'
  const approval = confirm(approvalText);
  if (!approval) {
      return;
  }
  router.post('/delivery-product-mappings/vends/' + deliveryProductMappingVend.id + '/toggle-pause-vend', {}, {
      preserveState: false,
      preserveScroll: true,
      replace: true,
  })
}

function unbindVend(deliveryProductMappingVendId) {
  const approval = confirm('Are you sure to delete this entry?');
  if (!approval) {
      return;
  }
  router.delete('/delivery-product-mappings/unbind/' + deliveryProductMappingVendId, {
      preserveState: false,
      preserveScroll: true,
      replace: true,
      onSuccess: () => {
        router.reload({only: ['unbindedVendOptions']})
        unbindedVendOptions.value = props.unbindedVendOptions ? props.unbindedVendOptions.data : []
      },
  })
}
</script>