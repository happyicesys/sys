<template>
  <Head title="VM Edit" />
  <BreezeAuthenticatedLayout>
    <template #header >
        <div class="flex flex-col md:flex-row justify-between items-center w-full">
          <div class="flex flex-col md:flex-row space-x-2">
            <span class="text-gray-600" v-if="opsJob && opsJob.id">
              Daily Job for
            </span>
            <div
              v-if="opsJob.stock_action_type"
              class="inline-flex justify-center items-center rounded px-1 py-0.5 text-xs font-medium border w-fit uppercase"
              :class="opsJob.stock_action_type == 'implement_new_mapping' ? 'bg-purple-100 text-purple-700 border-purple-200' : 'bg-orange-100 text-orange-700 border-orange-200'"
            >
              <span class="font-semibold grow-0">
                {{ opsJob.stock_action_type.replace(/_/g, ' ') }}
              </span>
            </div>
          </div>

        </div>
      </template>
    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
        <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll p-5">
          <form @submit.prevent="submit" id="submit">
          <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">
            <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Date
              </label>
              <div class="mt-1">
                <input
                  type="text"
                  class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                  :value=" opsJob ? opsJob.date_formatted : ''"
                  disabled
                />
              </div>
            </div>
            <div class="sm:col-span-5">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Delivery By
              </label>
              <div class="mt-1">
                <input
                  type="text"
                  class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full text-sm border-gray-300 rounded-md bg-gray-200 hover:cursor-not-allowed"
                  :value=" opsJob && opsJob.deliveredBy ? opsJob.deliveredBy.name : ''"
                  disabled
                />
              </div>
            </div>

            <div class="sm:col-span-6">
              <label for="remarks" class="flex justify-start text-sm font-medium text-gray-700">
                Remarks
              </label>
              <div class="mt-1 flex flex-col items-end space-y-2">
                <textarea
                  id="remarks"
                  name="remarks"
                  rows="3"
                  class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-full sm:text-sm border-gray-300 rounded-md"
                  v-model="opsJob.remarks"
                ></textarea>
                <Button
                  type="button"
                  @click.prevent="updateRemarks(opsJob)"
                  class="bg-green-500 hover:bg-green-600 text-white flex space-x-1"
                >
                  <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                  <span>
                    Save
                  </span>
                </Button>
              </div>
            </div>


            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
                <div class="relative">
                  <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-gray-300"></div>
                  </div>
                  <div class="relative flex justify-center">
                    <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> Job(s) </span>
                  </div>
                </div>
              </div>

              <div class="sm:col-span-5">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Add More Job(s)
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
                </MultiSelect>
                <div class="text-sm text-red-600" v-if="form.errors.vend_id">
                  {{ form.errors.vend_id }}
                </div>
              </div>

              <div class="sm:col-span-1">
                <Button
                type="button"
                @click="addOpsJobItem()"
                class="bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6"
                :class="[!form.vend_id ? 'opacity-50 cursor-not-allowed' : '']"
                :disabled="!form.vend_id && !permissions.includes('update operations')"
                >
                  <PlusCircleIcon class="w-4 h-4"></PlusCircleIcon>
                  <span>
                    Add
                  </span>
                </Button>
              </div>

              <div class="sm:col-span-6 flex justify-between space-x-1 text-sm mt-3">
                <div class="flex flex-col md:flex-row space-x-1">
                  <Button
                    type="button"
                    class="inline-flex space-x-1 items-center rounded-md border border-yellow bg-sky-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-700 shadow-sm hover:bg-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 hover:cursor-pointer ml-1 w-fit h-fit"
                    @click="onMapAllMarkerClicked"
                    v-if="opsJob.opsJobItems && opsJob.opsJobItems.some(item => item.customer && item.customer.deliveryAddress && item.customer.deliveryAddress.latitude && item.customer.deliveryAddress.longitude)"
                  >
                    <MapPinIcon class="h-4 w-4" aria-hidden="true" />
                    <span>Show Map Markers</span>
                  </Button>
                  <Button class="inline-flex space-x-1 items-center rounded-md border border-blue bg-blue-400 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2 w-fit h-fit"
                  @click.prevent="onGeneratePickListClicked()"
                  v-if="opsJob.opsJobItems && opsJob.opsJobItems.length && opsJob.opsJobItems.some(item => item.status < 2)"
                  >
                    <ClipboardDocumentCheckIcon class="h-4 w-4" aria-hidden="true"/>
                    <span class="flex flex-col space-y-1">
                      <span>
                          Qty List (Live)
                      </span>
                    </span>
                  </Button>
                  <Button class="inline-flex space-x-1 items-center rounded-md border border-yellow bg-yellow-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 w-fit h-fit"
                  @click.prevent="onGenerateQtyListClicked(2)"
                  v-if="opsJob.opsJobItems && opsJob.opsJobItems.length && opsJob.opsJobItems.some(item => item.status == 2)"
                  >
                    <ClipboardDocumentCheckIcon class="h-4 w-4" aria-hidden="true"/>
                    <span class="flex flex-col space-y-1">
                      <span>
                        Qty List (Picked)
                      </span>
                    </span>
                  </Button>
                  <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 w-fit h-fit"
                  @click.prevent="onGenerateQtyListClicked(3)"
                  v-if="opsJob.opsJobItems && opsJob.opsJobItems.length && opsJob.opsJobItems.some(item => item.status >= 3)"
                  >
                    <ClipboardDocumentCheckIcon class="h-4 w-4" aria-hidden="true"/>
                    <span class="flex flex-col space-y-1">
                      <span>
                        Qty List (Stocked In)
                      </span>
                    </span>
                  </Button>
                  <a :href="'/ops-jobs/' + opsJob.id + '/route'" target="_blank" class="text-blue-700" v-if="opsJob.opsJobItems">
                    <span class="inline-flex space-x-1 items-center rounded-md border border-yellow bg-yellow-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-700 shadow-sm hover:bg-yellow-400 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 hover:cursor-pointer">
                      <PlayIcon class="h-4 w-4" aria-hidden="true" />
                      <span>Route Planning</span>
                    </span>
                  </a>
                </div>
                <div class="flex flex-col space-y-1">
                  <span class="text-gray-500">
                    Total of
                    <span class="text-gray-800">
                      {{ opsJob.opsJobItems ? opsJob.opsJobItems.length : 0 }}
                    </span>
                    Job(s)
                  </span>
                  <span class="text-gray-500">
                    Total of
                    <span class="text-gray-800">
                      {{ opsJob.opsJobItems ? getApiInvCount() : 0 }}
                    </span>
                    API Inv(s)
                  </span>
                </div>
              </div>
              <div class="sm:col-span-6 flex flex-col">
              <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5">
                <div class="inline-block min-w-full py-2 align-middle md:px-4 lg:px-6">
                  <div class="overflow-scroll max-h-[800px] md:max-h-[1000px] shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-300">
                      <thead class="bg-gray-50">
                        <tr class="">
                          <TableHead>
                            <div class="flex flex-col space-y-1 max-w-20 items-center">
                              <SingleSortItem modelName="sequence" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('sequence')">
                                Job Sequence
                              </SingleSortItem>
                              <!-- <span>
                                Job Sequence
                              </span> -->
                              <!-- <Button
                                class="bg-yellow-300 hover:bg-yellow-400 text-gray-800 text-[11px] font-medium"
                                @click.prevent="sortTable('sequence')"
                                v-if="opsJob.opsJobItems && opsJob.opsJobItems.length && opsJob.opsJobItems.some(item => item.status < 3) && permissions.includes('admin-access operations')"
                              >
                                <div class="flex space-x-1 items-center">
                                  <ArrowPathIcon class="h-3 w-3"></ArrowPathIcon>
                                  <span>
                                    Sort
                                  </span>
                                </div>
                              </Button> -->
                              <Button
                                class="bg-yellow-300 hover:bg-yellow-400 text-gray-800 text-xs font-medium"
                                @click.prevent="onRenumberItemsClicked()"
                                v-if="opsJob.opsJobItems && opsJob.opsJobItems.length && opsJob.opsJobItems.some(item => item.status < 3) && permissions.includes('admin-access operations')"
                              >
                                <div class="flex space-x-1 items-center">
                                  <BarsArrowDownIcon class="h-3 w-3"></BarsArrowDownIcon>
                                  <span>
                                    Renumber
                                  </span>
                                </div>
                              </Button>
                            </div>
                          </TableHead>
                          <TableHead>
                            <div class="flex flex-col space-y-2">
                              <span>
                                Machine ID
                              </span>
                              <span>
                                Machine Prefix
                              </span>
                              <span>
                                Mapping
                              </span>
                              <span>
                                Job ID#
                              </span>
                              <span>
                                Remarks
                              </span>
                            </div>
                          </TableHead>
                          <TableHead>
                            <div class="flex flex-col space-y-2">
                              <span>
                                Status
                              </span>
                              <span>
                                Customer
                              </span>
                              <SingleSortItem modelName="delivery_postcode" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('delivery_postcode')">
                                Postcode
                              </SingleSortItem>
                              <span>
                                Ops Note
                              </span>
                            </div>
                          </TableHead>
                          <TableHead>
                            <div class="flex flex-col space-y-2">
                              <span>
                                Value <br>
                                (Qty)
                              </span>
                              <SingleSortItem modelName="refillable_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('refillable_amount')">
                                Refillable
                              </SingleSortItem>
                              <SingleSortItem modelName="picked_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('picked_amount')">
                                Picked
                              </SingleSortItem>
                              <SingleSortItem modelName="stock_in_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('stock_in_amount')">
                                Stock-in
                              </SingleSortItem>
                              <SingleSortItem modelName="acc_vend_transactions_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('acc_vend_transactions_amount')">
                                Stock-out <br>
                                (Transactions)
                              </SingleSortItem>
                            </div>
                          </TableHead>
                          <TableHead>
                            <div class="flex flex-col space-y-2">
                              <span>
                                Cash Amount
                              </span>
                              <SingleSortItem modelName="total_cash_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('total_cash_amount')">
                                Cash Collected (Machine)
                              </SingleSortItem>
                              <SingleSortItem modelName="total_cash_amount_from_vmc" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('total_cash_amount_from_vmc')">
                                CashAmt$ (VMC)
                              </SingleSortItem>
                              <SingleSortItem modelName="delta_cash_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('delta_cash_amount')">
                                Cash Adjustment
                              </SingleSortItem>
                              <SingleSortItem modelName="acc_vend_transactions_cash_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('acc_vend_transactions_cash_amount')">
                                CashAmt$ (Trans)
                              </SingleSortItem>
                            </div>
                          </TableHead>
                          <TableHead>
                            Address
                          </TableHead>
                          <TableHead>
                            Action
                          </TableHead>
                        </tr>

                      </thead>
                      <tbody class="bg-white">
                        <tr v-for="(opsJobItem, opsJobItemIndex) in opsJob.opsJobItems" :key="opsJobItem.id" :class="opsJobItemIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                            <!-- {{ opsJobItemIndex + 1 }} -->
                            <div class="flex items-center justify-center">
                              <input
                                type="text"
                                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-fit text-sm border-gray-300 rounded-md max-w-14 text-center"
                                v-model="opsJobItem.sequence"
                                :disabled="opsJobItem.status >= 3"
                                @input="updateSequence(opsJobItem)"
                                />
                            </div>
                          </td>
                          <td class="whitespace-pre-line py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                            <div class="flex flex-col space-y-2 max-w-24">
                              <Link :href="'/vends/customers?codes=' + opsJobItem.vend.code" class="text-blue-700" v-if="opsJobItem && opsJobItem.vend">
                                <span>
                                  {{ opsJobItem.vend.code }}
                                </span>
                              </Link>
                              <span>
                                {{ opsJobItem.vend && opsJobItem.vend.vendPrefix ? opsJobItem.vend.vendPrefix.name : '' }}
                              </span>
                              <span class="text-xs text-gray-500 font-medium whitespace-normal break-words max-w-24">
                                {{ opsJobItem.vend && opsJobItem.vend.productMapping ? opsJobItem.vend.productMapping.name : '' }}
                              </span>
                              <div>
                                <!-- <Button
                                  class="bg-indigo-400 hover:bg-indigo-500 text-white text-xs font-medium"
                                  @click.prevent="onChannelClicked(opsJobItem)"
                                  v-if="permissions.includes('update operations')"
                                >
                                  {{ opsJobItem.ref_id }}
                                </Button> -->
                                <Link :href="'/ops-jobs/items/' + opsJobItem.id + '/edit'">
                                  <Button
                                    class="bg-indigo-400 hover:bg-indigo-500 text-white text-xs font-medium"
                                    v-if="permissions.includes('update operations')"
                                  >
                                    {{ opsJobItem.ref_id }}
                                  </Button>
                                </Link>
                              </div>
                              <div class="text-left text-red-800">
                                {{ opsJobItem.remarks }}
                              </div>
                            </div>
                          </td>
                          <td class="whitespace py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left">
                            <div class="flex flex-col space-y-1 max-w-40">
                              <div class="flex flex-col space-y-1">
                                <div class="flex space-x-1">
                                  <div
                                      class="inline-flex justify-center items-center rounded px-1 py-0.5 text-xs font-medium border w-fit"
                                      :class="statusClass(opsJobItem.status)"
                                  >
                                      <div class="flex flex-col">
                                          <span class="font-semibold grow-0">
                                            {{ opsJobItem.status_name }}
                                          </span>
                                      </div>
                                  </div>
                                  <span class="rounded-full p-1 shadow-sm focus-visible:outline focus-visible:outline-1 focus-visible:outline-offset-1 h-fit"
                                  :class="[opsJobItem.attachments && opsJobItem.attachments.length ? 'bg-green-500 text-white' : 'bg-red-500 text-white']"
                                  v-if="opsJobItem.status >= 3"
                                  >
                                    <PaperClipIcon class="w-4 h-4"></PaperClipIcon>
                                  </span>
                                </div>
                                <div
                                    class="inline-flex justify-center items-center rounded px-1 py-0.5 text-xs font-bold border w-fit"
                                    :class="opsJobItem.stock_action_type == 'implement_new_mapping' ? 'bg-purple-100 text-purple-700' : 'bg-orange-100 text-orange-700'"
                                    v-if="opsJobItem.stock_action_type"
                                >
                                    {{ opsJobItem.stock_action_type == 'implement_new_mapping' ? 'Implement New Mapping' : 'Return Stock' }}
                                </div>
                                <span v-if="opsJobItem.status_at" class="text-xs font-medium text-gray-600">
                                  {{ opsJobItem.status_at }}
                                  <span v-if="opsJobItem.statusBy">
                                    ({{ opsJobItem.statusBy.name }})
                                  </span>
                                </span>
                              </div>
                              <div
                                  class="inline-flex justify-center items-center rounded px-1 py-0.5 text-xs font-medium border w-fit"
                                  :class="opsJobItemChannelErrorCheck(opsJobItem) == 2 ? 'bg-red-500 text-white' : (opsJobItemChannelErrorCheck(opsJobItem) == 1 ? 'bg-green-500 text-white' : '')"
                                  v-if="opsJobItem.status >= 3"
                              >
                                  <div class="flex flex-col">
                                      <span class="font-semibold grow-0">
                                        {{ opsJobItemChannelErrorCheck(opsJobItem) == 2 ? 'Not tally havent fixed' : (opsJobItemChannelErrorCheck(opsJobItem) == 1 ? 'Not tally fixed' : 'All tally') }}
                                      </span>
                                  </div>
                              </div>
                              <span>
                                <span v-if="opsJobItem.customer && opsJobItem.customer.person_id">
                                    {{ opsJobItem.customer.virtual_customer_code }} ({{ opsJobItem.vend && opsJobItem.vend.vendPrefix ? opsJobItem.vend.vendPrefix.name : '' }})
                                    <br>
                                    {{ opsJobItem.customer.name }}
                                </span>
                                <span v-else>
                                  <span v-if="opsJobItem.customer && opsJobItem.customer.code">
                                    {{ opsJobItem.customer.code }} <br>
                                  </span>
                                  {{ opsJobItem.customer && opsJobItem.customer.name ? opsJobItem.customer.name : ''}}
                                </span>
                              </span>
                              <span>
                                <a :href="cmsBaseUrl + '/transaction/' + opsJobItem.cms_transaction_id + '/edit'" v-if="opsJobItem.cms_transaction_id">
                                  <div
                                      class="inline-flex justify-center items-center rounded px-1 py-0.5 text-xs font-medium border w-xs bg-indigo-100 text-indigo-800"
                                  >
                                      <div class="flex flex-col">
                                          <span class="font-normal grow-0">
                                            API Inv
                                          </span>
                                      </div>
                                  </div>
                                </a>
                              </span>
                              <span v-if="opsJobItem.customer && opsJobItem.customer.deliveryAddress">
                                <div class="flex space-x-2 items-center font-medium text-xs">
                                  <span class="flex space-x-1 items-center" v-if="opsJobItem.customer && opsJobItem.customer.deliveryAddress">
                                    <span>
                                      <Button
                                      type="button" class="bg-sky-300 hover:bg-sky-400 px-3 py-1 text-xs text-sky-800 flex space-x-1 w-fit"
                                      @click="onMapMarkerClicked(opsJobItem)"
                                      v-if="opsJobItem.customer.deliveryAddress && opsJobItem.customer.deliveryAddress.latitude && opsJobItem.customer.deliveryAddress.longitude"
                                      >
                                        <MapPinIcon class="h-3 w-3" aria-hidden="true"/>
                                      </Button>
                                    </span>
                                    <a
                                      :href="opsJobItem.customer && opsJobItem.customer.deliveryAddress && opsJobItem.customer.deliveryAddress.map_url
                                        ? opsJobItem.customer.deliveryAddress.map_url
                                        : (opsJobItem.customer.deliveryAddress.latitude && opsJobItem.customer.deliveryAddress.longitude
                                          ? 'https://www.google.com/maps/search/?api=1&query=' + opsJobItem.customer.deliveryAddress.latitude + ',' + opsJobItem.customer.deliveryAddress.longitude
                                          : '')"
                                      target="_blank"
                                      rel="noopener noreferrer"
                                      type="button"
                                      class="bg-green-300 hover:bg-green-400 px-3 py-2 text-xs text-green-800 flex space-x-1 w-fit rounded shadow font-bold"
                                    >
                                      GPS
                                    </a>
                                  </span>
                                  <span>
                                    <!-- {{ opsJobItem.customer.deliveryAddress.postcode }} -->
                                    {{ opsJobItem.delivery_postcode }}
                                  </span>
                                </div>
                              </span>
                              <span class="text-left font-medium bg-gray-200 py-1 px-1 rounded">
                                {{ opsJobItem.customer ? opsJobItem.customer.ops_note : '' }}
                              </span>
                            </div>
                          </td>
                          <td class="whitespace-pre-line py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left align-top">
                            <div class="flex flex-col space-y-2 text-center">
                              <span class="text-indigo-800" v-if="opsJobItem.refillable_amount !== null">
                                <div class="flex space-x-1 px-5 justify-center">
                                  <span>
                                    {{ operatorCountry.currency_symbol }}{{ opsJobItem.refillable_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }} <br>
                                  ({{ opsJobItem.refillable_count }})
                                  </span>
                                </div>
                              </span>
                              <div class="flex space-x-1 px-5 justify-center" v-if="opsJobItem.status >= 2">
                                <!-- <span class="inline-flex items-center rounded-full bg-blue-50 px-1 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10 h-fit">P</span> -->
                                <span>
                                  {{ operatorCountry.currency_symbol }}{{ opsJobItem.picked_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }} <br>
                                  ({{ opsJobItem.picked_count }})
                                </span>
                              </div>
                              <span :class="[opsJobItem.stock_in_amount == opsJobItem.picked_amount ? 'text-green-600' : (opsJobItem.stock_in_amount < opsJobItem.picked_amount ? 'text-red-600' : 'text-blue-600')]" v-if="opsJobItem.status >= 3">
                                <div class="flex space-x-1 px-5 justify-center">
                                  <!-- <span class="inline-flex items-center rounded-full bg-blue-50 px-1 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10 h-fit">SI</span> -->
                                <span>
                                  {{ operatorCountry.currency_symbol }}{{ opsJobItem.stock_in_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }} <br>
                                ({{ opsJobItem.stock_in_count }})
                                </span>
                                </div>
                              </span>
                              <span v-if="opsJobItem.status >= 3">
                                <div class="flex space-x-1 px-5 justify-center">
                                  <!-- <span class="inline-flex items-center rounded-full bg-blue-50 px-1 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10 h-fit">SO</span> -->
                                  <span>
                                    {{ operatorCountry.currency_symbol }}{{ opsJobItem.acc_vend_transactions_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }} <br>
                                  ({{ opsJobItem.acc_vend_transactions_count }})
                                  </span>
                                </div>
                              </span>
                            </div>
                          </td>
                          <td class="whitespace-pre-line py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left align-top">
                            <div class="flex flex-col space-y-2 text-center" v-if="opsJobItem.status >= 3">
                              <div class="flex space-x-2 px-6 justify-center">
                                <!-- <span class="text-blue-600 flex items-center">
                                  $
                                  <ArrowLeftEndOnRectangleIcon class="w-4 h-4 text-blue-600">
                                  </ArrowLeftEndOnRectangleIcon>
                                </span> -->
                                <span>
                                  {{ operatorCountry.currency_symbol }}{{ opsJobItem.cash_amount !== null ? opsJobItem.cash_amount.toLocaleString(undefined, { minimumFractionDigits: operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent, maximumFractionDigits: operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent }) : 0 }}
                                  <!-- {{ operatorCountry.currency_symbol }}{{ opsJobItem.total_cash_amount.toLocaleString(undefined, { minimumFractionDigits: operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent, maximumFractionDigits: operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent }) }} -->
                                </span>
                              </div>
                              <span>
                                <div class="flex space-x-2 px-6 justify-center">
                                  <!-- <span class="inline-flex items-center rounded-full bg-blue-50 px-1 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10 h-fit">$VMC</span> -->
                                  {{ operatorCountry.currency_symbol }}{{ opsJobItem.total_cash_amount_from_vmc.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                                </div>
                              </span>
                              <span :class="[opsJobItem.delta_cash_amount == 0 ? 'text-green-600' : (opsJobItem.delta_cash_amount < 0 ? 'text-red-600' : 'text-blue-600')]">
                                <div class="flex space-x-2 px-6 justify-center">
                                  <!-- <span class="inline-flex items-center rounded-full bg-blue-50 px-1 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10 h-fit">$Adj</span> -->
                                  {{ operatorCountry.currency_symbol }}{{ opsJobItem.delta_cash_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                                </div>
                              </span>

                              <span :class="[opsJobItem.acc_vend_transactions_cash_amount == opsJobItem.cash_amount ? 'text-green-600' : 'text-red-600']">
                                <div class="flex space-x-2 px-6 justify-center">
                                  <!-- <span class="inline-flex items-center rounded-full bg-blue-50 px-1 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10 h-fit">$Adj</span> -->
                                  {{ operatorCountry.currency_symbol }}{{ opsJobItem.acc_vend_transactions_cash_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                                </div>
                              </span>
                            </div>
                          </td>
                          <td class="whitespace-pre-line py-4 px-1 text-sm text-left">
                            <div class="flex flex-col space-y-2 break-words max-w-32 md:max-w-52" v-if="opsJobItem.customer && opsJobItem.customer.deliveryAddress">
                              <span>
                                <a :href="opsJobItem.customer.deliveryAddress.map_url" v-if="opsJobItem.customer.deliveryAddress.map_url" class="text-blue-700" target="_blank">
                                  {{ opsJobItem.customer.deliveryAddress.full_address }}
                                </a>
                                <span v-else>
                                  {{ opsJobItem.customer.deliveryAddress.full_address }}
                                </span>
                              </span>
                              <span v-if="!opsJobItem.customer.deliveryAddress.full_address">
                                <a :href="opsJobItem.customer.deliveryAddress.map_url" v-if="opsJobItem.customer.deliveryAddress.map_url" class="text-blue-700" target="_blank">
                                  {{ opsJobItem.customer.deliveryAddress.postcode }}
                                </a>
                                <span v-else>
                                  {{ opsJobItem.customer.deliveryAddress.postcode }}
                                </span>
                              </span>
                              <!-- <span>
                                <Button
                                type="button" class="bg-sky-300 hover:bg-sky-400 px-3 py-2 text-xs text-sky-800 flex space-x-1 w-fit"
                                @click="onMapMarkerClicked(opsJobItem)"
                                v-if="opsJobItem.customer && opsJobItem.customer.deliveryAddress && opsJobItem.customer.deliveryAddress.latitude && opsJobItem.customer.deliveryAddress.longitude"
                                >
                                  <MapPinIcon class="h-4 w-4" aria-hidden="true"/>
                                </Button>
                              </span> -->
                            </div>
                          </td>
                          <td class="whitespace-nowrap py-4 px-1 text-sm text-center">
                            <Button
                              class="bg-blue-500 hover:bg-blue-600 text-white"
                              :class="[opsJobItem.status >= 3 ? 'opacity-50 cursor-not-allowed' : '']"
                              @click.prevent="onChangeDriverClicked(opsJobItem)"
                              v-if="permissions.includes('update operations') && opsJobItem.status < 3"
                              :disabled="opsJobItem.status >= 3"
                            >
                              <div class="flex space-x-2 items-center">
                                <ArrowsRightLeftIcon class="h-3 w-3"></ArrowsRightLeftIcon>
                                <span>
                                  Driver
                                </span>
                              </div>
                            </Button>
                          </td>
                        </tr>
                        <tr v-if="!opsJob.opsJobItems || !opsJob.opsJobItems.length">
                          <td colspan="11" class="whitespace-nowrap py-4 text-sm font-medium text-black text-center">
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
              <div class="flex flex-col space-y-1 md:space-y-0 md:flex-row md:justify-between">
                <div class="flex flex-col space-y-1 md:space-y-0 md:flex-row md:space-x-1">
                  <Button
                      type="button"
                      class=" px-2 py-2 mt-2 ml-1 text-md  flex space-x-1 bg-red-500 hover:bg-red-700 text-white"
                      @click="onDeleteClicked()"
                      v-if="opsJob.opsJobItems && !opsJob.opsJobItems.length"
                  >
                    <span class="flex space-x-1 items-center">
                      <TrashIcon class="w-4 h-4"></TrashIcon>
                      <span>
                        Delete
                      </span>
                    </span>
                  </Button>
                  <Button class="inline-flex space-x-1 items-center rounded-md border border-yellow bg-yellow-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 hover:cursor-pointer w-fit"
                  @click.prevent="syncCMSInvoices()"
                  v-if="props.cmsBaseUrl && (!opsJob.opsJobItems || opsJob.opsJobItems.length && opsJob.opsJobItems.some(item => item.cms_transaction_id == null) && (opsJob.opsJobItems.some(item => item.status >= 3 && item.status != 99)))"
                  >
                    <ClipboardDocumentCheckIcon class="h-4 w-4" aria-hidden="true"/>
                    <span class="flex flex-col space-y-1">
                      <span>
                          Create API Invoice(s)
                      </span>
                    </span>
                  </Button>

                </div>
                <div class="flex space-x-1 md:justify-end">
                  <Link :href="'/ops-jobs'">
                    <Button
                      type="button" class="bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1 "
                    >
                      <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
                      <span>
                        Back
                      </span>
                    </Button>
                  </Link>

                  <!-- <Button type="submit" class="bg-green-500 hover:bg-green-600 text-white flex space-x-1">
                    <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                    <span>
                      Save
                    </span>
                  </Button> -->
                </div>
              </div>
            </div>
          </div>
        </form>
        </div>
      </div>
    </div>
  </div>

  <Channel
		v-if="showChannelModal"
		:opsJobItem="opsJobItemModel"
		:showModal="showChannelModal"
		@modalClose="onChannelClosed"
    @statusUpdated="statusUpdated"
    @updatedNoRefresh="onChannelUpdatedNoRefresh"
  >
  </Channel>

  <ChangeDriver
		v-if="showChangeDriverModal"
    :opsJob="opsJob"
		:opsJobItem="opsJobItemModel"
		:showModal="showChangeDriverModal"
    :userOptions="userOptions"
		@modalClose="onChangeDriverModalClosed"
    @statusUpdated="statusUpdated"
  >
  </ChangeDriver>

  <MapMarker
    v-if="showMapMarkerModal"
    :customers="customerModel"
    :api-key="mapApiKey"
    :showModal="showMapMarkerModal"
    isShowDirectionButton=true
    @modalClose="onMapMarkerModalClose"
  >
  </MapMarker>

  <PickList
		v-if="showPickListModal"
		:pickLists="pickLists"
    :type="pickListType"
		:showModal="showPickListModal"
		@modalClose="onPickListModalClose"
  >
  </PickList>

  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import Channel from '@/Pages/OpsJob/Channel.vue';
import ChangeDriver from '@/Pages/OpsJob/ChangeDriver.vue';
import MapMarker from '@/Components/MapMarker.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import PickList from '@/Pages/Vend/PickList.vue';
import SearchInput from '@/Components/SearchInput.vue';
import SingleSortItem from '@/Components/SingleSortItem.vue';
import TableHead from '@/Components/TableHead.vue';
import {ArrowPathIcon, ArrowUturnLeftIcon, ArrowsRightLeftIcon, ArrowsUpDownIcon, BarsArrowDownIcon, CheckCircleIcon, ChevronDownIcon, ClipboardDocumentCheckIcon, CurrencyDollarIcon, MapIcon, MapPinIcon, PaperClipIcon, PlayIcon, PlusCircleIcon, TrashIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";

const props = defineProps({
  addressDestination: [Array, Object],
  addressStart: [Array, Object],
  cmsBaseUrl: String,
  mapApiKey: String,
  opsJob: Object,
  unbindedVendOptions: [Array, Object],
  userOptions: [Array, Object],
})

const booleanStrictOptions = ref([
    {id: 'true', value: 'Yes'},
    {id: 'false', value: 'No'},
])

const emit = defineEmits(['modalClose'])

const filters = ref({
    vend_code: '',
    customer: '',
  })

const form = ref(
  useForm(getDefaultForm())
)

const customerModel = ref([])
const operatorCountry = usePage().props.auth.operatorCountry
const opsJob = ref([])
const opsJobItemModel = ref([])
const permissions = usePage().props.auth.permissions
const pickLists = ref([])
const pickListType = ref(1)
const showChannelModal = ref(false)
const showChangeDriverModal = ref(false)
const showMapMarkerModal = ref(false)
const showPickListModal = ref(false)
const toast = useToast()
const unbindedVendOptions = ref([])
const userOptions = ref([])

onMounted(() => {
  opsJob.value = props.opsJob.data

  unbindedVendOptions.value = props.unbindedVendOptions.data.map(vend => {
    return {
      id: vend.id,
      full_name: vend.cust_full_name,
    }
  })
  userOptions.value = [
    ...props.userOptions.data.map((data) => {return {id: data.id, value: data.name + (data.roles && data.roles.length > 0 ? ' (' + data.roles[0].name + ')' : '')}})
  ]
})

function getDefaultForm() {
  return {
    id: '',
    vend_id: '',
  }
}

function addOpsJobItem() {
  form.value
    .transform((data) => ({
      ...data,
      vend_id: data.vend_id.id,
    }))
    .post('/ops-jobs/' + opsJob.value.id + '/item/create', {
      onSuccess: () => {
        toast.success("Successfully Saved", {
          timeout: 3000
        });
        form.value.vend_id = ''
        opsJob.value = props.opsJob.data
      },
      preserveState: true,
      replace: true,
    })
}

function syncCMSInvoices() {
  const approval = confirm('Are you sure to send the data to CMS?');
  if (!approval) {
      return;
  }
  form.value.post('/ops-jobs/' + opsJob.value.id + '/sync-cms-invoices', {
    onSuccess: () => {
      toast.success("Data Sent to CMS", {
        timeout: 3000
      });
    },
    preserveState: true,
    replace: true,
  })
}

function syncInventory() {
  const approval = confirm('Are you sure to sync inventory?');
  if (!approval) {
      return;
  }
  form.value.post('/ops-jobs/' + opsJob.value.id + '/sync-inventory', {
    onSuccess: () => {
      toast.success("Inventory Synced", {
        timeout: 3000
      });
    },
    preserveState: true,
    replace: true,
  })
}

function deleteOpsJobItem(opsJobItem) {
  const approval = confirm('Are you sure to delete this entry?');
  if (!approval) {
      return;
  }

  form.value.delete('/ops-jobs/items/' + opsJobItem.id, {
    onSuccess: () => {
      toast.success("Successfully Deleted", {
        timeout: 3000
      });
      opsJob.value = props.opsJob.data
    },
    preserveState: true,
    replace: true,
  })
}

function getApiInvCount() {
  return opsJob.value.opsJobItems.filter(item => item.cms_transaction_id != null).length
}

// reload opsJob when modal opened
function onChannelClicked(obj) {
  router.reload({
    only: ['opsJob'],
    data: {
      ...filters.value,
    },
    replace: true,
    preserveState: true,
    onSuccess: page => {
      opsJob.value = props.opsJob ? props.opsJob.data : null
    }
  })
  // get the opsJobItem where obj is the opsJobItem
    opsJobItemModel.value = opsJob.value.opsJobItems.find(item => item.id === obj.id)
    showChannelModal.value = true
}

function onChannelClosed() {
    showChannelModal.value = false
}

function onChannelUpdatedNoRefresh() {
  router.reload({
    only: ['opsJob'],
    data: {
      ...filters.value,
    },
    replace: true,
    preserveState: true,
    onSuccess: page => {
      opsJob.value = props.opsJob ? props.opsJob.data : null
    }
  })
}

function onChangeDriverClicked(obj) {
  opsJobItemModel.value = opsJob.value.opsJobItems.find(item => item.id === obj.id)
  showChangeDriverModal.value = true
}

function onChangeDriverModalClosed() {
  showChangeDriverModal.value = false
}

function onDeleteClicked() {
  const approval = confirm('Are you sure to delete this job?');
  if (!approval) {
      return;
  }
  router.delete('/ops-jobs/' + opsJob.value.id)
}


function onGeneratePickListClicked() {
    axios({
        method: 'POST',
        url: '/vends/pick-lists',
        // get all the vends from the opsJobItems
        data: opsJob.value.opsJobItems,
    }).then(response => {
        pickLists.value = response.data
        pickListType.value = 1
    }).catch(error => {
    }).finally(() => {
        showPickListModal.value = true
    })
}

function onGenerateQtyListClicked(status) {
  axios({
        method: 'POST',
        url: '/ops-jobs/qty-list/status/' + status,
        // get all the vends from the opsJobItems
        data: opsJob.value.opsJobItems,
    }).then(response => {
        pickLists.value = response.data
        pickListType.value = status
    }).catch(error => {
    }).finally(() => {
        showPickListModal.value = true
    })
}

function onMapMarkerClicked(opsJobItem) {
  axios({
    method: 'POST',
    url: '/customers/map',
    data: [{
      ops_job_item_id: opsJobItem.id,
      sequence: opsJobItem.sequence,
      customer_id: opsJobItem.customer.id,
    }],
  })
  .then((response) => {
    customerModel.value = [{
      ops_job_item_id: opsJobItem.id,
      sequence: opsJobItem.sequence,
      ...response.data.data[0],
    }];
    showMapMarkerModal.value = true; // Open the modal to show the map
  })
  .catch((error) => {
    console.error('API Error:', error);  // Log errors to debug
  });
}

function onMapAllMarkerClicked() {
  // Extract all the opsJobItems' customer information and send the request
  const opsJobItems = opsJob.value.opsJobItems.map((item) => ({
    ops_job_item_id: item.id,
    sequence: item.sequence,
    customer_id: item.customer.id,
  }));

  axios({
    method: 'POST',
    url: '/customers/map',
    data: opsJobItems,  // Send all opsJobItems for mapping
  })
  .then((response) => {
    customerModel.value = response.data.data.map((customerData, index) => ({
      ...customerData,
      sequence: opsJobItems[index].sequence,  // Maintain the correct sequence for each customer
      ops_job_item_id: opsJobItems[index].ops_job_item_id,  // Add ops_job_item_id
    }));
    showMapMarkerModal.value = true;  // Open the map modal with all markers
  })
  .catch((error) => {
    console.error('API Error:', error);  // Handle the API error
  });
}




function onMapMarkerModalClose() {
  showMapMarkerModal.value = false
}

function onPickListModalClose() {
  showPickListModal.value = false
}

function onRenumberItemsClicked() {
  form.value.clearErrors()
  form.value
    .transform((data) => ({
      ...data,
      opsJobItems: opsJob.value.opsJobItems,
    }))
    .post('/ops-jobs/' + opsJob.value.id + '/renumber', {
    onSuccess: () => {
      toast.success("Successfully Renumbered", {
        timeout: 3000
      });
      opsJob.value = props.opsJob.data
    },
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

function onSortClicked() {
  form.value.clearErrors()
  form.value
    .transform((data) => ({
      ...data,
    }))
    .post('/ops-jobs/' + opsJob.value.id + '/sort', {
    onSuccess: () => {
      toast.success("Successfully Sort", {
        timeout: 3000
      });
      opsJob.value = props.opsJob.data
    },
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

function onSearchFilterUpdated() {
  router.reload({
    only: ['opsJob'],
    data: {
      ...filters.value,
    },
    replace: true,
    preserveState: true,
    preserveScroll: true,
    onSuccess: page => {
      opsJob.value = props.opsJob ? props.opsJob.data : null
    }
  })
}

function opsJobItemChannelErrorCheck(opsJobItem) {
  let status = 0;

  opsJobItem.opsJobItemChannels.forEach(channel => {

    if(opsJobItem.status >= 3) {
      if (channel.virtual_is_error == 1 && channel.is_error_settle == 0) {
        status = 2; // Highest status
      } else if (channel.virtual_is_error == 1 && channel.is_error_settle == 1 && status < 2) {
        status = 1; // Middle status
      } else if (channel.virtual_is_error == 0 && status < 1) {
        status = 0; // Lowest status
      }
    }else {
      status = 0;
    }

  });

  return status;
}

function sortTable(sortKey) {
  filters.value.sortKey = sortKey
  filters.value.sortBy = !filters.value.sortBy
  onSearchFilterUpdated()
}

function statusClass(status) {
  let statusClass = ''
  switch(status) {
    case 1:
      statusClass = 'bg-blue-400 text-white'
      break;
    case 2:
      statusClass = 'bg-yellow-400 text-gray-800'
      break;
    case 3:
      statusClass = 'bg-green-400 text-gray-800'
      break;
    case 4:
      statusClass = 'bg-indigo-400 text-gray-800'
      break;
    case 98:
    case 99:
      statusClass = 'bg-red-500 text-white'
      break;
  }
  return statusClass
}

function statusUpdated() {
  router.reload({
    only: ['opsJob'],
    data: {
      ...filters.value,
    },
    replace: true,
    preserveState: true,
    onSuccess: page => {
      opsJob.value = props.opsJob ? props.opsJob.data : null
    }
  })
}

function updateSequence(opsJobItem) {
  form.value.clearErrors()
  form.value
    .transform((data) => ({
      ...data,
      sequence: opsJobItem.sequence,
    }))
    .post('/ops-jobs/items/' + opsJobItem.id + '/update', {
    onSuccess: () => {
    },
    preserveScroll: true,
    preserveState: true,
    replace: true,
  })
}

function updateRemarks(opsJobData) {
  form.value.clearErrors()
  form.value
    .transform((data) => ({
      ...data,
      remarks: opsJobData.remarks,
    }))
    .post('/ops-jobs/' + opsJobData.id + '/update', {
    onSuccess: () => {
    },
    preserveScroll: true,
    preserveState: true,
    replace: true,
  })
}



</script>