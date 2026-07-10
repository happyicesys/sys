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
              :class="opsJob.stock_action_type == 'implement_new_mapping' ? 'bg-purple-100 text-purple-700 border-purple-200' : opsJob.stock_action_type == 'onsite_adjustment' ? 'bg-teal-100 text-teal-700 border-teal-200' : 'bg-orange-100 text-orange-700 border-orange-200'"
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

              <div class="sm:col-span-1 flex space-x-2">
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

              <div class="sm:col-span-6 flex flex-col md:flex-row md:justify-between md:items-start gap-3 text-sm mt-3">
                <div class="flex flex-wrap items-center gap-2">
                  <Button
                    type="button"
                    class="inline-flex space-x-1 items-center rounded-md border border-yellow bg-sky-300 px-4 py-2.5 text-sm font-medium leading-4 text-gray-700 shadow-sm hover:bg-sky-400 focus:outline-none focus:ring-2 focus:ring-sky-500 focus:ring-offset-2 hover:cursor-pointer"
                    @click="onMapAllMarkerClicked"
                    v-if="opsJob.opsJobItems && opsJob.opsJobItems.some(item => item.customer && item.customer.deliveryAddress && item.customer.deliveryAddress.latitude && item.customer.deliveryAddress.longitude)"
                  >
                    <MapPinIcon class="h-4 w-4" aria-hidden="true" />
                    <span>Show Map Markers</span>
                  </Button>
                  <Button class="inline-flex space-x-1 items-center rounded-md border border-blue bg-blue-400 px-4 py-2.5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2"
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
                  <Button class="inline-flex space-x-1 items-center rounded-md border border-yellow bg-yellow-500 px-4 py-2.5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2"
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
                  <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-4 py-2.5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2"
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
                  <a :href="'/ops-jobs/' + opsJob.id + '/route'" target="_blank" class="text-blue-700 inline-flex" v-if="opsJob.opsJobItems">
                    <span class="inline-flex space-x-1 items-center rounded-md border border-yellow bg-yellow-300 px-4 py-2.5 text-sm font-medium leading-4 text-gray-700 shadow-sm hover:bg-yellow-400 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 hover:cursor-pointer">
                      <PlayIcon class="h-4 w-4" aria-hidden="true" />
                      <span>Route Planning</span>
                    </span>
                  </a>

                  <!-- Batch Assign Driver -->
                  <template v-if="permissions.includes('update operations') && opsJob.opsJobItems && opsJob.opsJobItems.some(item => item.status < 3)">
                    <!-- Active: green when items/tasks selected -->
                    <Button
                      v-if="batchMode && (selectedItemIds.length > 0 || selectedTaskIds.length > 0)"
                      type="button"
                      class="inline-flex space-x-1 items-center rounded-md bg-green-500 hover:bg-green-600 px-4 py-2.5 text-sm font-medium leading-4 text-white shadow-sm"
                      @click.prevent="onBatchAssignDriverClicked"
                    >
                      <UserGroupIcon class="h-4 w-4" aria-hidden="true" />
                      <span>Batch Assign Driver ({{ selectedItemIds.length + selectedTaskIds.length }})</span>
                    </Button>
                    <!-- Batch mode on, none selected: show cancel hint -->
                    <Button
                      v-else-if="batchMode && selectedItemIds.length === 0 && selectedTaskIds.length === 0"
                      type="button"
                      class="inline-flex space-x-1 items-center rounded-md bg-orange-400 hover:bg-orange-500 px-4 py-2.5 text-sm font-medium leading-4 text-white shadow-sm"
                      @click.prevent="onBatchAssignDriverClicked"
                    >
                      <XMarkIcon class="h-4 w-4" aria-hidden="true" />
                      <span>Cancel Batch</span>
                    </Button>
                    <!-- Default: enter batch mode -->
                    <Button
                      v-else
                      type="button"
                      class="inline-flex space-x-1 items-center rounded-md bg-purple-500 hover:bg-purple-600 px-4 py-2.5 text-sm font-medium leading-4 text-white shadow-sm"
                      @click.prevent="onBatchAssignDriverClicked"
                    >
                      <UserGroupIcon class="h-4 w-4" aria-hidden="true" />
                      <span>Batch Assign Driver</span>
                    </Button>
                  </template>

                  <!-- New Task -->
                  <Button
                    v-if="permissions.includes('update operations')"
                    type="button"
                    @click="openNewTaskModal()"
                    class="inline-flex space-x-1 items-center rounded-md bg-violet-500 hover:bg-violet-600 px-4 py-2.5 text-sm font-medium leading-4 text-white shadow-sm"
                  >
                    <PlusCircleIcon class="h-4 w-4" aria-hidden="true" />
                    <span>New Task</span>
                  </Button>
                </div>
                <div class="flex flex-col space-y-1">
                  <span class="text-gray-500">
                    Total of
                    <span class="text-gray-800">
                      {{ (opsJob.opsJobItems ? opsJob.opsJobItems.length : 0) + (opsJob.opsJobTasks ? opsJob.opsJobTasks.length : 0) }}
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
                                Product Mapping
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
                                Site
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
                        <!-- ── Task rows ────────────────────────────────────── -->
                        <template v-for="(row, rowIndex) in mergedRows" :key="row._key">
                        <tr v-if="row._isTask" :class="rowIndex % 2 === 0 ? 'bg-violet-50' : 'bg-violet-100'">
                          <!-- Sequence -->
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                            <div class="flex items-center justify-center">
                              <input
                                type="text"
                                class="shadow-sm focus:ring-violet-500 focus:border-violet-500 block w-fit text-sm border-violet-300 rounded-md max-w-14 text-center"
                                v-model.lazy="row.sequence"
                                @change="onSequenceChange(row)"
                              />
                            </div>
                          </td>
                          <!-- Task name / badge -->
                          <td class="whitespace-pre-line py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                            <div class="flex flex-col space-y-2 max-w-24">
                              <span class="inline-flex items-center justify-center rounded px-2 py-0.5 text-xs font-bold bg-violet-600 text-white w-fit mx-auto">TASK</span>
                              <span class="text-violet-800 font-semibold break-words">{{ row.task_name }}</span>
                              <a
                                v-if="row.ref_url"
                                :href="row.ref_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-flex items-center justify-center gap-1 text-xs text-blue-600 hover:text-blue-800 underline break-all"
                              >
                                <LinkIcon class="h-3 w-3 flex-shrink-0" />
                                <span>Link</span>
                              </a>
                            </div>
                          </td>
                          <!-- Status / Created at / postcode / ops note -->
                          <td class="whitespace py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left">
                            <div class="flex flex-col space-y-1 max-w-40">
                              <!-- Status badge -->
                              <button
                                type="button"
                                class="inline-flex justify-center items-center rounded px-1 py-0.5 text-xs font-medium border w-fit cursor-pointer"
                                :class="taskStatusClass(row.status)"
                                @click.prevent="openTaskStatusModal(row)"
                              >
                                {{ row.status_name || 'Pending' }}
                              </button>
                              <span v-if="row.created_at" class="text-xs font-medium text-gray-600">
                                {{ row.created_at }}
                                <span v-if="row.created_by_name">({{ row.created_by_name }})</span>
                              </span>
                              <span class="text-xs text-gray-500">{{ row.postcode }}</span>
                              <span class="text-left font-medium bg-gray-200 py-1 px-1 rounded" v-if="row.ops_note">
                                {{ row.ops_note }}
                              </span>
                            </div>
                          </td>
                          <!-- Value -->
                          <td class="whitespace-pre-line py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left align-top">
                            <div class="flex flex-col space-y-2 text-center" v-if="row.value || row.qty != null">
                              <span class="text-indigo-800">
                                <div class="flex space-x-1 px-5 justify-center">
                                  <span>
                                    {{ operatorCountry.currency_symbol }}{{ parseFloat(row.value || 0).toLocaleString(undefined, { minimumFractionDigits: operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent, maximumFractionDigits: operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent }) }} <br>
                                    ({{ row.qty ?? 0 }})
                                  </span>
                                </div>
                              </span>
                            </div>
                            <span v-else class="text-gray-400">—</span>
                          </td>
                          <!-- Cash collected — N/A for tasks -->
                          <td class="whitespace-pre-line py-4 pl-4 pr-3 text-sm text-center text-gray-400">—</td>
                          <!-- Address -->
                          <td class="whitespace-pre-line py-4 px-1 text-sm text-left">
                            <div class="flex flex-col space-y-1 break-words max-w-32 md:max-w-52">
                              <span>{{ row.address }}</span>
                            </div>
                          </td>
                          <!-- Action -->
                          <td class="whitespace-nowrap py-4 px-1 text-sm text-center">
                            <template v-if="batchMode && permissions.includes('update operations')">
                              <input
                                type="checkbox"
                                class="h-5 w-5 rounded border-violet-300 text-violet-600 cursor-pointer accent-violet-500"
                                :value="row.id"
                                v-model="selectedTaskIds"
                              />
                            </template>
                            <template v-else>
                              <div class="flex flex-col space-y-1 items-center" v-if="permissions.includes('update operations')">
                                <Button
                                  class="bg-blue-500 hover:bg-blue-600 text-white"
                                  @click.prevent="onTaskDriverClicked(row)"
                                >
                                  <div class="flex space-x-2 items-center">
                                    <ArrowsRightLeftIcon class="h-3 w-3" />
                                    <span>Driver</span>
                                  </div>
                                </Button>
                                <Button
                                  class="bg-violet-500 hover:bg-violet-600 text-white"
                                  @click.prevent="openEditTaskModal(row)"
                                >
                                  <div class="flex space-x-2 items-center">
                                    <PencilSquareIcon class="h-3 w-3" />
                                    <span>Edit</span>
                                  </div>
                                </Button>
                              </div>
                            </template>
                          </td>
                        </tr>
                        <!-- ── Regular item rows ───────────────────────────── -->
                        <tr v-else :class="rowIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                          <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                            <div class="flex items-center justify-center">
                              <input
                                type="text"
                                class="shadow-sm focus:ring-indigo-500 focus:border-indigo-500 block w-fit text-sm border-gray-300 rounded-md max-w-14 text-center"
                                v-model.lazy="row.sequence"
                                :disabled="row.status >= 3"
                                @change="onSequenceChange(row)"
                                />
                            </div>
                          </td>
                          <td class="whitespace-pre-line py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                            <div class="flex flex-col space-y-2 max-w-24">
                              <Link :href="'/vends/customers?codes=' + row.vend.code" class="text-blue-700" v-if="row && row.vend">
                                <span>
                                  {{ row.vend.code }}
                                </span>
                              </Link>
                              <span>
                                {{ row.vend && row.vend.vendPrefix ? row.vend.vendPrefix.name : '' }}
                              </span>
                              <span class="flex flex-col text-xs font-medium whitespace-normal break-all max-w-24 min-w-0 mt-1" v-if="rowMappingCurrent(row)">
                                <span class="text-gray-500 break-all">{{ rowMappingCurrent(row) }}</span>
                                <span class="flex flex-wrap items-center justify-center gap-1 mt-0.5 max-w-24 min-w-0" v-if="rowMappingUpcoming(row)">
                                  <span class="bg-purple-100 text-purple-700 text-xs font-medium px-1.5 py-0.5 rounded shrink-0">New</span>
                                  <span class="text-red-500 text-xs font-medium break-all max-w-24 min-w-0">{{ rowMappingUpcoming(row) }}</span>
                                </span>
                              </span>
                              <div>
                                <Link :href="'/ops-jobs/items/' + row.id + '/edit'">
                                  <Button
                                    class="bg-indigo-400 hover:bg-indigo-500 text-white text-xs font-medium"
                                    v-if="permissions.includes('update operations')"
                                  >
                                    {{ row.ref_id }}
                                  </Button>
                                </Link>
                              </div>
                              <div class="text-left text-red-800">
                                {{ row.remarks }}
                              </div>
                            </div>
                          </td>
                          <td class="whitespace py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left">
                            <div class="flex flex-col space-y-1 max-w-40">
                              <div class="flex flex-col space-y-1">
                                <div class="flex space-x-1">
                                  <div
                                      class="inline-flex justify-center items-center rounded px-1 py-0.5 text-xs font-medium border w-fit"
                                      :class="statusClass(row.status)"
                                  >
                                      <div class="flex flex-col">
                                          <span class="font-semibold grow-0">
                                            {{ row.status_name }}
                                          </span>
                                      </div>
                                  </div>
                                  <span class="rounded-full p-1 shadow-sm focus-visible:outline focus-visible:outline-1 focus-visible:outline-offset-1 h-fit"
                                  :class="[row.attachments && row.attachments.length ? 'bg-green-500 text-white' : 'bg-red-500 text-white']"
                                  v-if="row.status >= 3"
                                  >
                                    <PaperClipIcon class="w-4 h-4"></PaperClipIcon>
                                  </span>
                                  <span
                                    v-if="row.frozen_at"
                                    class="inline-flex items-center rounded px-1 py-0.5 text-xs font-medium border w-fit bg-gray-200 text-gray-700 border-gray-300"
                                    :title="'Freezed ' + row.frozen_at + ' — mapping / channel errors / coin float snapshotted 10 min after stock-in.'"
                                  >
                                    🔒 Freezed
                                  </span>
                                </div>
                                <span v-if="row.status_at" class="text-xs font-medium text-gray-600">
                                  {{ row.status_at }}
                                  <span v-if="row.statusBy">
                                    ({{ row.statusBy.name }})
                                  </span>
                                </span>
                                <div class="flex flex-col gap-1" v-if="row.stock_action_type">
                                  <div
                                      class="inline-flex justify-center items-center rounded px-1 py-0.5 text-xs font-bold border w-fit"
                                      :class="row.stock_action_type == 'implement_new_mapping' ? 'bg-purple-100 text-purple-700' : row.stock_action_type == 'onsite_adjustment' ? 'bg-teal-100 text-teal-700' : 'bg-orange-100 text-orange-700'"
                                  >
                                      {{ row.stock_action_type == 'implement_new_mapping' ? 'Implement New Mapping' : row.stock_action_type == 'return_stock' ? 'Return Stock' : 'Onsite Adjustment' }}
                                  </div>
                                  <div
                                      class="text-xs text-gray-700 whitespace-pre-line break-words max-w-40 border border-purple-300 rounded px-1.5 py-1 bg-purple-50"
                                      v-if="row.stock_action_type == 'implement_new_mapping' && rowMappingRemarks(row)"
                                  >
                                      {{ rowMappingRemarks(row) }}
                                  </div>
                                </div>
                              </div>
                              <div
                                  class="inline-flex justify-center items-center rounded px-1 py-0.5 text-xs font-medium border w-fit"
                                  :class="opsJobItemChannelErrorCheck(row) == 2 ? 'bg-red-500 text-white' : (opsJobItemChannelErrorCheck(row) == 1 ? 'bg-green-500 text-white' : '')"
                                  v-if="row.status >= 3"
                              >
                                  <div class="flex flex-col">
                                      <span class="font-semibold grow-0">
                                        {{ opsJobItemChannelErrorCheck(row) == 2 ? 'Not tally havent fixed' : (opsJobItemChannelErrorCheck(row) == 1 ? 'Not tally fixed' : 'All tally') }}
                                      </span>
                                  </div>
                              </div>
                              <span>
                                <span v-if="row.customer && row.customer.person_id">
                                    {{ row.customer.id + 20000 }} ({{ row.vend && row.vend.vendPrefix ? row.vend.vendPrefix.name : '' }})
                                    <br>
                                    {{ row.customer.name }}
                                </span>
                                <span v-else>
                                  <span v-if="row.customer && row.customer.code">
                                    {{ row.customer.code }} <br>
                                  </span>
                                  {{ row.customer && row.customer.name ? row.customer.name : ''}}
                                </span>
                                <span
                                  class="inline-flex rounded px-0.5 py-0.5 text-xs border align-middle ml-1 bg-indigo-100 text-indigo-800 border-indigo-300"
                                  v-if="row.customer && row.customer.selling_price_type"
                                >
                                  RP{{ row.customer.selling_price_type }}
                                </span>
                                <!--
                                  Delivery platform badges (e.g. green "Grab" pill)
                                  — surfaced from any active delivery_product_mapping_vends
                                  row tied to the vend. Style mirrors the Grab pill on
                                  ProductMapping/Index.vue and Vend/CustomerIndex.vue.
                                -->
                                <span
                                  v-if="row.vend && row.vend.deliveryProductMappingVends"
                                  v-for="(deliveryProductMappingVend, dpmvIndex) in row.vend.deliveryProductMappingVends"
                                  :key="'opsjob-dpmv-' + dpmvIndex"
                                >
                                  <span
                                    class="ml-1 inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit text-gray-800 bg-green-400 align-middle"
                                    v-if="deliveryProductMappingVend.deliveryProductMapping && deliveryProductMappingVend.deliveryProductMapping.deliveryPlatformOperator && deliveryProductMappingVend.deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform"
                                  >
                                    {{ deliveryProductMappingVend.deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform.name }}
                                  </span>
                                </span>
                              </span>
                              <span>
                                <a :href="cmsBaseUrl + '/transaction/' + row.cms_transaction_id + '/edit'" v-if="row.cms_transaction_id">
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
                              <span v-if="row.customer && row.customer.deliveryAddress">
                                <div class="flex space-x-2 items-center font-medium text-xs">
                                  <span class="flex space-x-1 items-center" v-if="row.customer && row.customer.deliveryAddress">
                                    <span>
                                      <Button
                                      type="button" class="bg-sky-300 hover:bg-sky-400 px-3 py-1 text-xs text-sky-800 flex space-x-1 w-fit"
                                      @click="onMapMarkerClicked(row)"
                                      v-if="row.customer.deliveryAddress && row.customer.deliveryAddress.latitude && row.customer.deliveryAddress.longitude"
                                      >
                                        <MapPinIcon class="h-3 w-3" aria-hidden="true"/>
                                      </Button>
                                    </span>
                                    <a
                                      :href="row.customer && row.customer.deliveryAddress && row.customer.deliveryAddress.map_url
                                        ? '//' + row.customer.deliveryAddress.map_url
                                        : (row.customer.deliveryAddress.latitude && row.customer.deliveryAddress.longitude
                                          ? 'https://www.google.com/maps/search/?api=1&query=' + row.customer.deliveryAddress.latitude + ',' + row.customer.deliveryAddress.longitude
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
                                    {{ row.delivery_postcode }}
                                  </span>
                                </div>
                              </span>
                              <!-- Coin Float: frozen rows use the snapshot (rowCoinFloat); red when at/below COIN_FLOAT_LOW_THRESHOLD, green otherwise -->
                              <div
                                class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit"
                                :class="rowCoinFloat(row) > COIN_FLOAT_LOW_THRESHOLD ? 'bg-green-200 text-gray-800 border-green-300' : 'bg-red-200 text-gray-800 border-red-300'"
                                v-if="rowCoinFloat(row)"
                              >
                                <span class="font-bold mr-1">Coin Float</span>
                                <span>
                                  {{ operatorCountry.currency_symbol }}{{ (rowCoinFloat(row) / Math.pow(10, operatorCountry.currency_exponent)).toFixed(operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent) }}
                                </span>
                              </div>
                              <!-- Channel Errors: single inline badge, channel + error code, comma-separated -->
                              <div
                                class="inline-flex items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit bg-red-100 text-red-800 border-red-300"
                                v-if="rowChannelErrorLogs(row).length"
                              >
                                <span class="font-semibold mr-1">Error:</span>
                                <span>
                                  <template v-for="(vendChannelErrorLog, errIndex) in rowChannelErrorLogs(row)" :key="vendChannelErrorLog.id">
                                    <span v-if="errIndex > 0">, </span>#{{ vendChannelErrorLog['vendChannel'] ? vendChannelErrorLog['vendChannel']['code'] : (vendChannelErrorLog['vend_channel'] ? vendChannelErrorLog['vend_channel']['code'] : '') }}<span class="font-bold">({{ vendChannelErrorLog['vendChannelError'] ? vendChannelErrorLog['vendChannelError']['code'] : (vendChannelErrorLog['vend_channel_error'] ? vendChannelErrorLog['vend_channel_error']['code'] : '') }})</span>
                                  </template>
                                </span>
                              </div>
                              <span class="text-left font-medium bg-gray-200 py-1 px-1 rounded">
                                {{ row.customer ? row.customer.ops_note : '' }}
                              </span>
                            </div>
                          </td>
                          <td class="whitespace-pre-line py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left align-top">
                            <div class="flex flex-col space-y-2 text-center">
                              <span class="text-indigo-800" v-if="row.refillable_amount !== null">
                                <div class="flex space-x-1 px-5 justify-center">
                                  <span>
                                    {{ operatorCountry.currency_symbol }}{{ row.refillable_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }} <br>
                                  ({{ row.refillable_count }})
                                  </span>
                                </div>
                              </span>
                              <div class="flex space-x-1 px-5 justify-center" v-if="row.status >= 2">
                                <span>
                                  {{ operatorCountry.currency_symbol }}{{ row.picked_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }} <br>
                                  ({{ row.picked_count }})
                                </span>
                              </div>
                              <span :class="[row.stock_in_amount == row.picked_amount ? 'text-green-600' : (row.stock_in_amount < row.picked_amount ? 'text-red-600' : 'text-blue-600')]" v-if="row.status >= 3">
                                <div class="flex space-x-1 px-5 justify-center">
                                <span>
                                  {{ operatorCountry.currency_symbol }}{{ row.stock_in_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }} <br>
                                ({{ row.stock_in_count }})
                                </span>
                                </div>
                              </span>
                              <span v-if="row.status >= 3">
                                <div class="flex space-x-1 px-5 justify-center">
                                  <span>
                                    {{ operatorCountry.currency_symbol }}{{ row.acc_vend_transactions_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }} <br>
                                  ({{ row.acc_vend_transactions_count }})
                                  </span>
                                </div>
                              </span>
                            </div>
                          </td>
                          <td class="whitespace-pre-line py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left align-top">
                            <div class="flex flex-col space-y-2 text-center" v-if="row.status >= 3">
                              <div class="flex space-x-2 px-6 justify-center">
                                <span>
                                  {{ operatorCountry.currency_symbol }}{{ row.cash_amount !== null ? row.cash_amount.toLocaleString(undefined, { minimumFractionDigits: operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent, maximumFractionDigits: operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent }) : 0 }}
                                </span>
                              </div>
                              <span>
                                <div class="flex space-x-2 px-6 justify-center">
                                  {{ operatorCountry.currency_symbol }}{{ row.total_cash_amount_from_vmc.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                                </div>
                              </span>
                              <span :class="[row.delta_cash_amount == 0 ? 'text-green-600' : (row.delta_cash_amount < 0 ? 'text-red-600' : 'text-blue-600')]">
                                <div class="flex space-x-2 px-6 justify-center">
                                  {{ operatorCountry.currency_symbol }}{{ row.delta_cash_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                                </div>
                              </span>
                              <span :class="[row.acc_vend_transactions_cash_amount == row.cash_amount ? 'text-green-600' : 'text-red-600']">
                                <div class="flex space-x-2 px-6 justify-center">
                                  {{ operatorCountry.currency_symbol }}{{ row.acc_vend_transactions_cash_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                                </div>
                              </span>
                            </div>
                          </td>
                          <td class="whitespace-pre-line py-4 px-1 text-sm text-left">
                            <div class="flex flex-col space-y-2 break-words max-w-32 md:max-w-52" v-if="row.customer && row.customer.deliveryAddress">
                              <span>
                                <a :href="row.customer.deliveryAddress.map_url" v-if="row.customer.deliveryAddress.map_url" class="text-blue-700" target="_blank">
                                  {{ row.customer.deliveryAddress.full_address }}
                                </a>
                                <span v-else>
                                  {{ row.customer.deliveryAddress.full_address }}
                                </span>
                              </span>
                              <span v-if="!row.customer.deliveryAddress.full_address">
                                <a :href="row.customer.deliveryAddress.map_url" v-if="row.customer.deliveryAddress.map_url" class="text-blue-700" target="_blank">
                                  {{ row.customer.deliveryAddress.postcode }}
                                </a>
                                <span v-else>
                                  {{ row.customer.deliveryAddress.postcode }}
                                </span>
                              </span>
                            </div>
                          </td>
                          <td class="whitespace-nowrap py-4 px-1 text-sm text-center">
                            <!-- Batch mode: show checkbox for eligible items -->
                            <template v-if="batchMode && permissions.includes('update operations')">
                              <input
                                v-if="row.status < 3"
                                type="checkbox"
                                class="h-5 w-5 rounded border-gray-300 text-green-600 cursor-pointer accent-green-500"
                                :value="row.id"
                                v-model="selectedItemIds"
                              />
                              <span v-else class="text-xs text-gray-400">—</span>
                            </template>
                            <!-- Normal mode: individual driver button -->
                            <template v-else>
                              <Button
                                class="bg-blue-500 hover:bg-blue-600 text-white"
                                :class="[row.status >= 3 ? 'opacity-50 cursor-not-allowed' : '']"
                                @click.prevent="onChangeDriverClicked(row)"
                                v-if="permissions.includes('update operations') && row.status < 3"
                                :disabled="row.status >= 3"
                              >
                                <div class="flex space-x-2 items-center">
                                  <ArrowsRightLeftIcon class="h-3 w-3"></ArrowsRightLeftIcon>
                                  <span>Driver</span>
                                </div>
                              </Button>
                            </template>
                          </td>
                        </tr>
                        </template><!-- end mergedRows v-for -->
                        <tr v-if="!mergedRows.length">
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

  <BatchChangeDriver
    v-if="showBatchChangeDriverModal"
    :opsJob="opsJob"
    :selectedItemIds="selectedItemIds"
    :selectedTaskIds="selectedTaskIds"
    :showModal="showBatchChangeDriverModal"
    :userOptions="userOptions"
    @modalClose="onBatchChangeDriverModalClosed"
    @statusUpdated="onBatchAssignSuccess"
  >
  </BatchChangeDriver>

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

  <!-- ── Task Status Modal ──────────────────────────────────── -->
  <Teleport to="body">
    <Modal :open="showTaskStatusModal" @modalClose="closeTaskStatusModal">
      <template #header>
        <div class="flex items-center space-x-2">
          <span class="text-base font-semibold">{{ statusTask?.task_name }}</span>
          <span
            class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border"
            :class="taskStatusClass(statusTask?.status)"
          >{{ statusTask?.status_name || 'Pending' }}</span>
        </div>
      </template>
      <template #default>
        <div class="space-y-3 px-1">
          <!-- Picked by -->
          <div v-if="statusTask?.status >= 2 && statusTask?.picked_by_name" class="flex space-x-2 text-sm">
            <span class="font-medium text-gray-700 w-28 shrink-0">Picked By</span>
            <span class="text-gray-600">{{ statusTask.picked_by_name }} ({{ statusTask.picked_at }})</span>
          </div>
          <!-- Completed by -->
          <div v-if="statusTask?.status >= 3 && statusTask?.completed_by_name" class="flex space-x-2 text-sm">
            <span class="font-medium text-gray-700 w-28 shrink-0">Completed By</span>
            <span class="text-gray-600">{{ statusTask.completed_by_name }} ({{ statusTask.completed_at }})</span>
          </div>

          <!-- Action buttons -->
          <div class="flex justify-between pt-3 border-t border-gray-100">
            <!-- Undo button (left) -->
            <Button
              v-if="statusTask?.status >= 2"
              type="button"
              class="bg-orange-400 hover:bg-orange-500 text-white flex space-x-1"
              :class="taskStatusProcessing ? 'opacity-50 cursor-not-allowed' : ''"
              :disabled="taskStatusProcessing"
              @click.prevent="undoTaskStatus(statusTask)"
            >
              <ArrowUturnLeftIcon class="w-4 h-4" />
              <span>{{ statusTask?.status === 2 ? 'Undo Picked' : 'Undo Completed' }}</span>
            </Button>
            <div v-else />

            <!-- Advance button (right) -->
            <Button
              v-if="statusTask?.status < 3"
              type="button"
              class="text-white flex space-x-1"
              :class="[statusTask?.status === 1 ? 'bg-yellow-500 hover:bg-yellow-600' : 'bg-green-500 hover:bg-green-600', taskStatusProcessing ? 'opacity-50 cursor-not-allowed' : '']"
              :disabled="taskStatusProcessing"
              @click.prevent="advanceTaskStatus(statusTask)"
            >
              <CheckCircleIcon class="w-4 h-4" />
              <span>{{ statusTask?.status === 1 ? 'Mark as Picked' : 'Mark as Completed' }}</span>
            </Button>
          </div>
        </div>
      </template>
    </Modal>
  </Teleport>

  <!-- ── Task Create / Edit Modal ─────────────────────────── -->
  <Teleport to="body">
    <Modal :open="showTaskModal" @modalClose="closeTaskModal">
      <template #header>
        <span class="text-base font-semibold">{{ editingTask ? 'Edit Task' : 'New Task' }}</span>
      </template>
      <template #default>
        <form @submit.prevent="submitTask">
          <div class="grid grid-cols-1 gap-y-3 gap-x-3 sm:grid-cols-6">

            <!-- Status badge only (edit mode) -->
            <div class="sm:col-span-6" v-if="editingTask">
              <div class="flex flex-wrap items-center gap-2 bg-gray-50 rounded-lg px-3 py-2 border border-gray-200">
                <span class="text-sm font-medium text-gray-700">Status:</span>
                <span
                  class="inline-flex justify-center items-center rounded px-2 py-0.5 text-xs font-medium border"
                  :class="taskStatusClass(editingTask.status)"
                >{{ editingTask.status_name || 'Pending' }}</span>
                <span v-if="editingTask.status >= 2 && editingTask.picked_by_name" class="text-xs text-gray-500">
                  · Picked by <span class="font-medium text-gray-700">{{ editingTask.picked_by_name }}</span> ({{ editingTask.picked_at }})
                </span>
                <span v-if="editingTask.status >= 3 && editingTask.completed_by_name" class="text-xs text-gray-500">
                  · Completed by <span class="font-medium text-gray-700">{{ editingTask.completed_by_name }}</span> ({{ editingTask.completed_at }})
                </span>
              </div>
            </div>

            <!-- Sequence (optional) -->
            <div class="sm:col-span-6">
              <label class="flex justify-start text-sm font-medium text-gray-700">
                Sequence <span class="text-gray-400 ml-1 text-xs">(optional)</span>
              </label>
              <input
                type="number"
                min="0.1"
                step="any"
                v-model="taskForm.sequence"
                class="mt-1 shadow-sm block w-full text-sm border-gray-300 rounded-md focus:ring-violet-500 focus:border-violet-500"
                placeholder="Leave blank to append"
              />
            </div>

            <!-- Task Name -->
            <div class="sm:col-span-6">
              <label class="flex justify-start text-sm font-medium text-gray-700">
                Task Name <span class="text-red-500 ml-0.5">*</span>
              </label>
              <input
                type="text"
                v-model="taskForm.task_name"
                class="mt-1 shadow-sm block w-full text-sm border-gray-300 rounded-md focus:ring-violet-500 focus:border-violet-500"
                :class="taskFormErrors.task_name ? 'border-red-400' : ''"
                placeholder="e.g. Collect key from security"
              />
              <p class="text-xs text-red-500 mt-0.5" v-if="taskFormErrors.task_name">{{ taskFormErrors.task_name }}</p>
            </div>

            <!-- Postcode + Address (same row) -->
            <div class="sm:col-span-2">
              <label class="flex justify-start text-sm font-medium text-gray-700">
                Postcode <span class="text-red-500 ml-0.5">*</span>
              </label>
              <input
                type="text"
                v-model="taskForm.postcode"
                maxlength="6"
                class="mt-1 shadow-sm block w-full text-sm border-gray-300 rounded-md focus:ring-violet-500 focus:border-violet-500"
                :class="taskFormErrors.postcode ? 'border-red-400' : ''"
                placeholder="6-digit postcode"
              />
              <p class="text-xs text-red-500 mt-0.5" v-if="taskFormErrors.postcode">{{ taskFormErrors.postcode }}</p>
            </div>

            <div class="sm:col-span-4">
              <label class="flex justify-start text-sm font-medium text-gray-700">
                Address <span class="text-red-500 ml-0.5">*</span>
              </label>
              <input
                type="text"
                v-model="taskForm.address"
                class="mt-1 shadow-sm block w-full text-sm border-gray-300 rounded-md focus:ring-violet-500 focus:border-violet-500"
                :class="taskFormErrors.address ? 'border-red-400' : ''"
                placeholder="Full address"
              />
              <p class="text-xs text-red-500 mt-0.5" v-if="taskFormErrors.address">{{ taskFormErrors.address }}</p>
            </div>

            <!-- Amount + Qty (same row) -->
            <div class="sm:col-span-3">
              <label class="flex justify-start text-sm font-medium text-gray-700">
                Amount <span class="text-gray-400 ml-1 text-xs">(optional)</span>
              </label>
              <input
                type="number"
                min="0"
                step="0.01"
                v-model="taskForm.value"
                class="mt-1 shadow-sm block w-full text-sm border-gray-300 rounded-md focus:ring-violet-500 focus:border-violet-500"
                :class="taskFormErrors.value ? 'border-red-400' : ''"
                placeholder="0.00"
              />
              <p class="text-xs text-red-500 mt-0.5" v-if="taskFormErrors.value">{{ taskFormErrors.value }}</p>
            </div>

            <div class="sm:col-span-3">
              <label class="flex justify-start text-sm font-medium text-gray-700">
                Qty <span class="text-gray-400 ml-1 text-xs">(optional)</span>
              </label>
              <input
                type="number"
                min="0"
                step="1"
                v-model="taskForm.qty"
                class="mt-1 shadow-sm block w-full text-sm border-gray-300 rounded-md focus:ring-violet-500 focus:border-violet-500"
                :class="taskFormErrors.qty ? 'border-red-400' : ''"
                placeholder="e.g. 5"
              />
              <p class="text-xs text-red-500 mt-0.5" v-if="taskFormErrors.qty">{{ taskFormErrors.qty }}</p>
            </div>

            <!-- Ops Note -->
            <div class="sm:col-span-6">
              <label class="flex justify-start text-sm font-medium text-gray-700">
                Ops Note <span class="text-gray-400 ml-1 text-xs">(optional)</span>
              </label>
              <textarea
                rows="2"
                v-model="taskForm.ops_note"
                class="mt-1 shadow-sm block w-full text-sm border-gray-300 rounded-md focus:ring-violet-500 focus:border-violet-500"
                placeholder="Any special instructions..."
              ></textarea>
            </div>

            <!-- Ref URL -->
            <div class="sm:col-span-6">
              <label class="flex justify-start text-sm font-medium text-gray-700">
                Ref URL <span class="text-gray-400 ml-1 text-xs">(optional)</span>
              </label>
              <input
                type="text"
                v-model="taskForm.ref_url"
                class="mt-1 shadow-sm block w-full text-sm border-gray-300 rounded-md focus:ring-violet-500 focus:border-violet-500"
                :class="taskFormErrors.ref_url ? 'border-red-400' : ''"
                placeholder="https:// or www."
              />
              <p class="text-xs text-red-500 mt-0.5" v-if="taskFormErrors.ref_url">{{ taskFormErrors.ref_url }}</p>
            </div>

            <!-- Submit / Delete / Status row -->
            <div class="sm:col-span-6 flex justify-between items-center pt-2 gap-2">
              <!-- Left: Delete -->
              <Button
                v-if="editingTask"
                type="button"
                class="bg-red-500 hover:bg-red-600 text-white flex space-x-1"
                :class="taskFormProcessing ? 'opacity-50 cursor-not-allowed' : ''"
                :disabled="taskFormProcessing"
                @click.prevent="deleteTask(editingTask)"
              >
                <TrashIcon class="w-4 h-4" />
                <span>Delete</span>
              </Button>
              <div v-else />

              <!-- Right: Undo + Status advance + Update -->
              <div class="flex items-center gap-2">
                <!-- Undo -->
                <Button
                  v-if="editingTask && editingTask.status >= 2"
                  type="button"
                  class="bg-orange-400 hover:bg-orange-500 text-white flex space-x-1"
                  :class="taskStatusProcessing ? 'opacity-50 cursor-not-allowed' : ''"
                  :disabled="taskStatusProcessing"
                  @click.prevent="undoTaskStatusInline(editingTask)"
                >
                  <ArrowUturnLeftIcon class="w-4 h-4" />
                  <span>{{ editingTask.status === 2 ? 'Undo Picked' : 'Undo Completed' }}</span>
                </Button>

                <!-- Advance status (Picked / Completed) -->
                <Button
                  v-if="editingTask && editingTask.status < 3"
                  type="button"
                  class="flex space-x-1 text-white"
                  :class="[editingTask.status === 1 ? 'bg-yellow-500 hover:bg-yellow-600' : 'bg-green-500 hover:bg-green-600', taskStatusProcessing ? 'opacity-50 cursor-not-allowed' : '']"
                  :disabled="taskStatusProcessing"
                  @click.prevent="advanceTaskStatusInline(editingTask)"
                >
                  <CheckCircleIcon class="w-4 h-4" />
                  <span>{{ editingTask.status === 1 ? 'Picked' : 'Completed' }}</span>
                </Button>

                <!-- Update Task -->
                <Button
                  type="submit"
                  class="bg-violet-500 hover:bg-violet-600 text-white flex space-x-1"
                  :class="taskFormProcessing ? 'opacity-50 cursor-not-allowed' : ''"
                  :disabled="taskFormProcessing"
                >
                  <CheckCircleIcon class="w-4 h-4" />
                  <span>{{ editingTask ? 'Update Task' : 'Create Task' }}</span>
                </Button>
              </div>
            </div>
          </div>
        </form>
      </template>
    </Modal>
  </Teleport>

  </BreezeAuthenticatedLayout>
</template>

<script setup>
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import BatchChangeDriver from '@/Pages/OpsJob/BatchChangeDriver.vue';
import Modal from '@/Components/Modal.vue';
import Channel from '@/Pages/OpsJob/Channel.vue';
import ChangeDriver from '@/Pages/OpsJob/ChangeDriver.vue';
import MapMarker from '@/Components/MapMarker.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import PickList from '@/Pages/Vend/PickList.vue';
import SearchInput from '@/Components/SearchInput.vue';
import SingleSortItem from '@/Components/SingleSortItem.vue';
import TableHead from '@/Components/TableHead.vue';
import {ArrowPathIcon, ArrowUturnLeftIcon, ArrowsRightLeftIcon, ArrowsUpDownIcon, BarsArrowDownIcon, CheckCircleIcon, ChevronDownIcon, ClipboardDocumentCheckIcon, CurrencyDollarIcon, LinkIcon, MapIcon, MapPinIcon, PaperClipIcon, PencilSquareIcon, PlayIcon, PlusCircleIcon, TrashIcon, UserGroupIcon, XMarkIcon } from '@heroicons/vue/20/solid';
import { ref, computed, onMounted, watch } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";
import { COIN_FLOAT_LOW_THRESHOLD } from '@/constants/vendThresholds';

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

const batchMode = ref(false)
const customerModel = ref([])
const operatorCountry = usePage().props.auth.operatorCountry
const opsJob = ref([])
const opsJobItemModel = ref([])
const permissions = usePage().props.auth.permissions
const pickLists = ref([])
const pickListType = ref(1)
const selectedItemIds = ref([])
const selectedTaskIds = ref([])
const showBatchChangeDriverModal = ref(false)
const showTaskModal = ref(false)
const showTaskStatusModal = ref(false)
const editingTask = ref(null)
const statusTask = ref(null)
const taskFormProcessing = ref(false)
const taskStatusProcessing = ref(false)
const taskFormErrors = ref({})
const taskForm = ref({
  sequence: '',
  task_name: '',
  address: '',
  postcode: '',
  ops_note: '',
  ref_url: '',
  value: '',
  qty: '',
})
const showChannelModal = ref(false)
const showChangeDriverModal = ref(false)
const showMapMarkerModal = ref(false)
const showPickListModal = ref(false)
const toast = useToast()
const unbindedVendOptions = ref([])
const userOptions = ref([])

onMounted(() => {
  opsJob.value = props.opsJob.data
  rebuildMergedRows()

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

// ----------------------------------------------------------------
// Merged and sorted list of job items + tasks for the table display.
// We annotate the original reactive objects (not copies) so that
// v-model on sequence works correctly against the reactive source.
//
// IMPORTANT: This is intentionally a ref (not a computed) so that
// editing the Job Sequence input does NOT immediately re-sort the
// rows on blur — the user only wants the new sequence value saved.
// The list is rebuilt only when:
//   (a) opsJob is re-fetched / replaced (page refresh, filter change,
//       Renumber click, add / delete item, etc.), or
//   (b) we explicitly call rebuildMergedRows().
// ----------------------------------------------------------------
const mergedRows = ref([])

function rebuildMergedRows() {
  if (!opsJob.value) {
    mergedRows.value = []
    return
  }
  const items = (opsJob.value.opsJobItems || []).map(item => {
    item._isTask = false
    item._key = 'item_' + item.id
    return item
  })
  const tasks = (opsJob.value.opsJobTasks || []).map(task => {
    task._isTask = true
    task._key = 'task_' + task.id
    return task
  })
  mergedRows.value = [...items, ...tasks].sort((a, b) => {
    const seqA = (a.sequence != null) ? a.sequence : Infinity
    const seqB = (b.sequence != null) ? b.sequence : Infinity
    if (seqA !== seqB) return seqA - seqB
    // Stable sort: items before tasks for same sequence
    return a._isTask ? 1 : -1
  })
}

// Rebuild whenever the entire opsJob object is replaced (e.g. after
// router.reload / form.post that reassigns opsJob.value). Mutating
// row.sequence in-place will NOT trigger this watcher, which is
// exactly what we want — typing in the sequence input must not
// reorder the visible rows.
watch(opsJob, () => {
  rebuildMergedRows()
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
    onSuccess: (page) => {
      // Prefer the server's flash so skipped (unlinked) sites are surfaced
      // instead of a blanket "sent" toast. flash.error = some sites had no
      // CMS Linking ID; flash.success = all eligible sites were queued.
      const flash = page?.props?.flash || {}
      if (flash.error) {
        toast.error(flash.error, { timeout: 8000 })
      } else {
        toast.success(flash.success || "Data Sent to CMS", { timeout: 3000 })
      }
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

function onTaskDriverClicked(task) {
  // Open the batch change driver modal with just this task pre-selected
  selectedItemIds.value = []
  selectedTaskIds.value = [task.id]
  showBatchChangeDriverModal.value = true
}

function onBatchAssignDriverClicked() {
  if (!batchMode.value) {
    // Enter batch mode
    batchMode.value = true
    selectedItemIds.value = []
    selectedTaskIds.value = []
  } else if (selectedItemIds.value.length === 0 && selectedTaskIds.value.length === 0) {
    // Exit batch mode if nothing selected yet (acts as Cancel)
    batchMode.value = false
  } else {
    // Open batch modal
    showBatchChangeDriverModal.value = true
  }
}

function onBatchChangeDriverModalClosed() {
  showBatchChangeDriverModal.value = false
}

function onBatchAssignSuccess() {
  // Reset batch state then reload
  batchMode.value = false
  selectedItemIds.value = []
  selectedTaskIds.value = []
  statusUpdated()
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
  // Build the unified ordered list in current display sequence
  const mergedOrder = mergedRows.value.map(row => ({
    type: row._isTask ? 'task' : 'item',
    id: row.id,
  }))
  form.value
    .transform((data) => ({
      ...data,
      mergedOrder,
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

// Upcoming-mapping remarks for a row — frozen rows read the snapshot text, live
// rows read the (live) upcoming mapping config. Mirrors the original preference:
// vend.upcomingProductMapping first, else productMapping.upcomingProductMapping.
function rowMappingRemarks(row) {
  if (row.frozen_at) {
    return row.frozen_mapping_remarks
  }
  if (row.vend && (row.vend.upcomingProductMapping || (row.vend.productMapping && row.vend.productMapping.upcomingProductMapping))) {
    return (row.vend.upcomingProductMapping || row.vend.productMapping.upcomingProductMapping).remarks
  }
  return null
}

// Product-mapping names for a row — frozen rows read the snapshot, live rows
// read the (live) vend mapping relations. Upcoming prefers vend.upcomingProductMapping
// then productMapping.upcomingProductMapping (matches the live template order).
function rowMappingCurrent(row) {
  if (row.frozen_at) return row.frozen_mapping_current_name || ''
  return row.vend && row.vend.productMapping ? row.vend.productMapping.name : ''
}
function rowMappingUpcoming(row) {
  if (row.frozen_at) return row.frozen_mapping_upcoming_direct || row.frozen_mapping_upcoming_via_current || ''
  if (!row.vend) return ''
  const obj = row.vend.upcomingProductMapping || (row.vend.productMapping && row.vend.productMapping.upcomingProductMapping)
  return obj ? obj.name : ''
}

// Machine channel-error logs for a row — frozen rows read the snapshot copy
// (what the machine reported at the job), live rows read current telemetry.
function rowChannelErrorLogs(row) {
  if (row.frozen_at) {
    return row.frozen_channel_error_logs || []
  }
  return (row.vend && row.vend.vendChannelErrorLogsJson) ? row.vend.vendChannelErrorLogsJson : []
}

// Coin float for a row — frozen rows read the snapshot value, live rows read
// the machine's current CoinCnt telemetry.
function rowCoinFloat(row) {
  if (row.frozen_at) {
    return row.frozen_coin_float
  }
  return row.vend && row.vend.parameterJson ? row.vend.parameterJson['CoinCnt'] : null
}

function opsJobItemChannelErrorCheck(opsJobItem) {
  // Frozen rows show the tally state captured at freeze time.
  if (opsJobItem.frozen_at) {
    return opsJobItem.frozen_tally_status != null ? opsJobItem.frozen_tally_status : 0
  }

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

// Fires once when the user commits the sequence field (blur / Tab / Enter)
// v-model.lazy ensures row.sequence is already updated before this runs
function onSequenceChange(row) {
  if (row._isTask) {
    updateTaskSequence(row)
  } else {
    updateSequence(row)
  }
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

// ----------------------------------------------------------------
// Task status helpers
// ----------------------------------------------------------------
function taskStatusClass(status) {
  switch (status) {
    case 1: return 'bg-blue-400 text-white border-blue-400'
    case 2: return 'bg-yellow-400 text-gray-800 border-yellow-400'
    case 3: return 'bg-green-400 text-gray-800 border-green-400'
    default: return 'bg-blue-400 text-white border-blue-400'
  }
}

function openTaskStatusModal(task) {
  statusTask.value = task
  showTaskStatusModal.value = true
}

function closeTaskStatusModal() {
  showTaskStatusModal.value = false
  statusTask.value = null
}

function _applyTaskUpdate(updatedTask) {
  // Patch the reactive task in opsJob.opsJobTasks in-place so the table
  // updates without a full page reload
  const tasks = opsJob.value.opsJobTasks || []
  const idx = tasks.findIndex(t => t.id === updatedTask.id)
  if (idx !== -1) {
    Object.assign(tasks[idx], updatedTask)
    // Also keep statusTask in sync if the modal is still open
    if (statusTask.value?.id === updatedTask.id) {
      Object.assign(statusTask.value, updatedTask)
    }
  }
}

function advanceTaskStatus(task) {
  if (!task) return
  taskStatusProcessing.value = true
  axios.post('/ops-jobs/tasks/' + task.id + '/update-status')
    .then(res => {
      toast.success('Status updated', { timeout: 3000 })
      _applyTaskUpdate(res.data.task)
    })
    .catch(() => toast.error('Failed to update status', { timeout: 3000 }))
    .finally(() => { taskStatusProcessing.value = false })
}

function undoTaskStatus(task) {
  if (!task) return
  if (!confirm('Undo status change?')) return
  taskStatusProcessing.value = true
  axios.post('/ops-jobs/tasks/' + task.id + '/undo-status')
    .then(res => {
      toast.success('Status undone', { timeout: 3000 })
      _applyTaskUpdate(res.data.task)
    })
    .catch(() => toast.error('Failed to undo status', { timeout: 3000 }))
    .finally(() => { taskStatusProcessing.value = false })
}

// Inline variants used inside the Edit Task modal — also patch editingTask ref
function advanceTaskStatusInline(task) {
  if (!task) return
  taskStatusProcessing.value = true
  axios.post('/ops-jobs/tasks/' + task.id + '/update-status')
    .then(res => {
      toast.success('Status updated', { timeout: 3000 })
      _applyTaskUpdate(res.data.task)
      // patch editingTask so the modal badge + info refreshes immediately
      Object.assign(editingTask.value, res.data.task)
    })
    .catch(() => toast.error('Failed to update status', { timeout: 3000 }))
    .finally(() => { taskStatusProcessing.value = false })
}

function undoTaskStatusInline(task) {
  if (!task) return
  if (!confirm('Undo status change?')) return
  taskStatusProcessing.value = true
  axios.post('/ops-jobs/tasks/' + task.id + '/undo-status')
    .then(res => {
      toast.success('Status undone', { timeout: 3000 })
      _applyTaskUpdate(res.data.task)
      Object.assign(editingTask.value, res.data.task)
    })
    .catch(() => toast.error('Failed to undo status', { timeout: 3000 }))
    .finally(() => { taskStatusProcessing.value = false })
}

// ----------------------------------------------------------------
// Task modal functions
// ----------------------------------------------------------------
function openNewTaskModal() {
  editingTask.value = null
  taskForm.value = {
    sequence: '',
    task_name: '',
    address: '',
    postcode: '',
    ops_note: '',
    ref_url: '',
    value: '',
    qty: '',
  }
  taskFormErrors.value = {}
  showTaskModal.value = true
}

function openEditTaskModal(task) {
  editingTask.value = task
  taskForm.value = {
    sequence: task.sequence ?? '',
    task_name: task.task_name || '',
    address: task.address || '',
    postcode: task.postcode || '',
    ops_note: task.ops_note || '',
    ref_url: task.ref_url || '',
    value: task.value ?? '',
    qty: task.qty ?? '',
  }
  taskFormErrors.value = {}
  showTaskModal.value = true
}

function closeTaskModal() {
  showTaskModal.value = false
  editingTask.value = null
  taskFormErrors.value = {}
}

function submitTask() {
  taskFormErrors.value = {}
  // Client-side validation
  if (!taskForm.value.task_name?.trim()) {
    taskFormErrors.value.task_name = 'Task name is required.'
  }
  if (!taskForm.value.address?.trim()) {
    taskFormErrors.value.address = 'Address is required.'
  }
  if (!taskForm.value.postcode?.trim()) {
    taskFormErrors.value.postcode = 'Postcode is required.'
  } else if (!/^\d{6}$/.test(taskForm.value.postcode.trim())) {
    taskFormErrors.value.postcode = 'Postcode must be a 6-digit number.'
  }
  if (taskForm.value.value !== '' && taskForm.value.value !== null) {
    const val = parseFloat(taskForm.value.value)
    if (isNaN(val) || val < 0) {
      taskFormErrors.value.value = 'Must be a non-negative number.'
    }
  }
  if (Object.keys(taskFormErrors.value).length > 0) return

  taskFormProcessing.value = true
  const payload = {
    sequence: taskForm.value.sequence !== '' ? parseFloat(taskForm.value.sequence) : null,
    task_name: taskForm.value.task_name.trim(),
    address: taskForm.value.address.trim(),
    postcode: taskForm.value.postcode.trim(),
    ops_note: taskForm.value.ops_note?.trim() || null,
    ref_url: (() => {
      const raw = taskForm.value.ref_url?.trim()
      if (!raw) return null
      if (!/^https?:\/\//i.test(raw)) return 'https://' + raw
      return raw
    })(),
    value: (taskForm.value.value !== '' && taskForm.value.value !== null)
      ? parseFloat(taskForm.value.value)
      : 0,
    qty: (taskForm.value.qty !== '' && taskForm.value.qty !== null)
      ? parseInt(taskForm.value.qty)
      : null,
  }

  const url = editingTask.value
    ? '/ops-jobs/tasks/' + editingTask.value.id + '/update'
    : '/ops-jobs/' + opsJob.value.id + '/tasks'

  axios.post(url, payload)
    .then(() => {
      toast.success(editingTask.value ? 'Task updated' : 'Task created', { timeout: 3000 })
      closeTaskModal()
      statusUpdated()
    })
    .catch(error => {
      if (error.response?.data?.errors) {
        const serverErrors = {}
        Object.keys(error.response.data.errors).forEach(k => {
          serverErrors[k] = Array.isArray(error.response.data.errors[k])
            ? error.response.data.errors[k][0]
            : error.response.data.errors[k]
        })
        taskFormErrors.value = serverErrors
      } else {
        toast.error('Failed to save task', { timeout: 3000 })
      }
    })
    .finally(() => {
      taskFormProcessing.value = false
    })
}

function deleteTask(task) {
  if (!confirm('Are you sure to delete this task?')) return
  closeTaskModal()
  axios.delete('/ops-jobs/tasks/' + task.id)
    .then(() => {
      toast.success('Task deleted', { timeout: 3000 })
      statusUpdated()
    })
    .catch(() => {
      toast.error('Failed to delete task', { timeout: 3000 })
    })
}

function updateTaskSequence(task) {
  axios.post('/ops-jobs/tasks/' + task.id + '/update-sequence', {
    sequence: task.sequence !== '' ? parseFloat(task.sequence) : null,
  }).catch(error => {
    console.error('Failed to update task sequence', error)
  })
}

</script>