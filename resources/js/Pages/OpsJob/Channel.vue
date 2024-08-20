<template>
  <Teleport to="body">
    <Modal :open="showModal" @modalClose="$emit('modalClose')">
      <template #header >
        <div class="flex flex-col space-y-2 md:flex-row md:space-x-3 md:space-y-0 text-left">
          <span>
            Job ID#
            <span class="text-blue-800">
              {{ opsJobItem.ref_id }}
            </span>
          </span>
          <span>
            Machine ID#
            <span class="text-blue-800">
              {{ vend.code }}
            </span>
          </span>
          <span v-if="vend.customer" class="text-gray-700">
            ({{ vend.customer.id + 20000 }})
            {{ vend.customer.name }}
          </span>
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
        </div>
      </template>
      <template #default>
        <div class="px-2 border-b mb-2 border-gray-100 text-left">
          <dl class="divide-y divide-gray-100">
            <div class="px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0" v-if="opsJobItem.status >= 2  && opsJobItem.pickedBy">
              <dt class="text-sm font-medium leading-6 text-gray-900">
                Picked By
              </dt>
              <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                <div class="flex space-x-2">
                  <span>
                    {{ opsJobItem.pickedBy.name }}
                  </span>
                  <span>
                    ({{ opsJobItem.picked_at }})
                  </span>
                </div>
              </dd>
            </div>
            <div class="px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0" v-if="opsJobItem.status >= 3 && opsJobItem.completedBy">
              <dt class="text-sm font-medium leading-6 text-gray-900">
                Stock In By
              </dt>
              <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                <div class="flex space-x-2">
                  <span>
                    {{ opsJobItem.completedBy.name }}
                  </span>
                  <span>
                    ({{ opsJobItem.completed_at }})
                  </span>
                </div>
              </dd>
            </div>
            <!-- <div class="px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0" v-if="opsJobItem.status >= 2">
              <dt class="text-sm font-medium leading-6 text-gray-900">
                Cashless Collected
                <span class="text-red-500">*</span>
              </dt>
              <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                <div class="flex space-x-2">
                  <FormInput inputType="number" v-model="form.cashless_amount" class="text-center" :disabled="opsJobItem.status >= 3">
                  </FormInput>
                </div>
              </dd>
            </div> -->
            <div class="px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
              <dt class="text-sm font-medium leading-6 text-gray-900">
                Remarks
              </dt>
              <dd class="text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                <FormTextarea v-model="form.remarks" class="w-full" rows="3">
                </FormTextarea>
              </dd>
            </div>
          </dl>
        </div>
        <div class="flex md:justify-end mb-2 px-4 py-3">
        <Button
            type="button"
            class="px-2 py-2 ml-1 text-xs md:text-md flex space-x-1 bg-green-500 hover:bg-green-600 text-white w-full md:w-fit"
            @click.prevent="onSaveFormClicked()"
        >
          <span class="flex space-x-1 items-center">
            <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
            <span>
              Save
            </span>
          </span>
        </Button>
        </div>

        <div class="flex flex-col">
          <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5">
            <div class="inline-block min-w-full py-2 align-middle md:px-3 lg:px-6">
              <div class="overflow-hidden shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                <table class="min-w-full divide-y divide-gray-300">
                  <thead class="bg-gray-50">
                    <tr v-if="opsJobItem.status >= 3">
                      <th scope="col" class="px-2 py-1 text-center text-xs font-semibold text-gray-900" colspan="6">
                      </th>
                      <th scope="col" class="px-2 py-1 text-center text-xs font-bold text-gray-900 bg-gray-200" colspan="4">
                        <div class="flex flex-col space-y-1 items-center">
                          <span>
                            From VMC
                          </span>
                          <div
                            class="inline-flex justify-center items-center rounded px-1 py-0.5 text-xs font-medium border w-xs text-white w-fit"
                            :class="[opsJobItem.vendChannelRecord ? 'bg-green-500' : 'bg-red-500']"
                          >
                            <div class="flex flex-col font-semibold grow-0">
                              <span v-if="opsJobItem.vendChannelRecord">
                                {{ opsJobItem.vendChannelRecord.before_date_created_at_formatted }}
                              </span>
                              <span v-else>
                                Not Detected
                              </span>
                            </div>
                          </div>
                        </div>
                      </th>
                    </tr>
                    <tr>
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        #
                      </th>
                      <th scope="col" class="w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        Image
                      </th>
                      <th scope="col" class="w-2/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        Product
                      </th>
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold text-gray-900">
                        Needed/ Capacity
                      </th>
                      <th scope="col" class="w-2/12 px-3 py-3.5 text-center text-xs font-semibold" :class="[opsJobItem.status < 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status < 2">
                        Picked
                      </th>
                      <th scope="col" class="w-2/12 px-3 py-3.5 text-center text-xs font-semibold" :class="[opsJobItem.status == 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status >= 2">
                        Stock In
                      </th>
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold" :class="[opsJobItem.status == 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status > 2">
                        VMC Inventory Count
                      </th>
                      <th scope="col" class="w-2/12 px-1 py-2 text-center text-xs font-semibold bg-gray-200" :class="[opsJobItem.status == 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status > 2">
                        <div class="flex flex-col space-y-1">
                          <span>
                            Before Refill
                          </span>
                          <!-- <div
                            class="inline-flex justify-center items-center rounded px-0.5 py-0.5 text-xs font-medium border w-xs bg-green-500 text-white w-fit"
                            v-if="opsJobItem.vendChannelRecord && opsJobItem.vendChannelRecord.before_data_created_at"
                          >
                            <div class="flex flex-col">
                                <span class="font-semibold grow-0">
                                  {{ opsJobItem.vendChannelRecord.before_date_created_at_formatted }}
                                </span>
                            </div>
                          </div> -->
                        </div>
                      </th>
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold bg-gray-200" :class="[opsJobItem.status == 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status > 2">
                        Stock In
                      </th>
                      <th scope="col" class="w-2/12 px-1 py-2 text-center text-xs font-semibold bg-gray-200" :class="[opsJobItem.status == 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status > 2">
                        <div>
                          <span>
                            After Refill
                          </span>
                          <!-- <div
                            class="inline-flex justify-center items-center rounded px-0.5 py-0.5 text-xs font-medium border w-xs bg-green-500 text-white w-fit"
                            v-if="opsJobItem.vendChannelRecord && opsJobItem.vendChannelRecord.after_data_created_at"
                          >
                            <div class="flex flex-col">
                                <span class="font-semibold grow-0">
                                  {{ opsJobItem.vendChannelRecord.after_data_created_at_formatted }}
                                </span>
                            </div>
                          </div> -->
                        </div>
                      </th>
                      <th scope="col" class="w-1/12 px-3 py-3.5 text-center text-xs font-semibold bg-gray-200" :class="[opsJobItem.status == 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status > 2">
                        Error
                      </th>
                    </tr>
                  </thead>
                  <tbody class="bg-white">
                    <tr v-for="(channel, channelIndex) in channels" :key="channel.id" :class="channelIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold sm:pl-6 text-center text-gray-800">
                        {{ channel.code }}
                      </td>
                      <td class="whitespace-nowrap text-sm  font-semibold text-gray-800 text-center">
                        <div class="flex justify-center items-center" >
                          <img class="h-20 w-20 min-w-20 min-h-20 rounded-full" :src="channel.product.thumbnail.full_url" alt="" v-if="channel.product && channel.product.thumbnail" :class="[channel.product && channel.product.is_available ? '' : 'opacity-50']"/>
                        </div>
                      </td>
                      <td class="py-4 text-sm font-semibold text-center" :class="[(channel.product && channel.product.is_available) ? 'text-gray-800' : 'text-gray-400']">
                        <span v-if="channel.product">
                          <span v-if="channel.product && channel.product.code">
                            {{ channel.product.code }}
                          </span>
                          <span class="break-normal text-xs" v-if="channel.product && channel.product.name">
                            <br> {{ channel.product.name }}
                          </span>
                        </span>
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-bold sm:pl-6 text-center text-gray-900">
                        {{ channel.capacity - channel.qty }}/ {{ channel.capacity }}
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center" :class="[opsJobItem.status < 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status < 2">
                        <!-- <FormInput inputType="number" v-model="channel.picked" :maxValue="channel.capacity" class="text-center min-w-12" :disabled="channel.product && !channel.product.is_available" v-if="opsJobItem.status < 2">
                        </FormInput> -->
                        <select name="channel_picked" id="channel_picked" class="rounded" :class="[channel.picked != (channel.capacity - channel.qty) ? 'text-red-500' : '']" v-model="channel.picked" :disabled="channel.product && !channel.product.is_available" v-if="opsJobItem.status < 2">
                          <option v-for="n in channel.capacity + 1" :key="n-1" :value="n-1">{{ n-1 }}</option>
                        </select>
                        <span v-if="opsJobItem.status >= 2">
                          {{ channel.picked }}
                        </span>
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center" :class="[opsJobItem.status == 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status >= 2">
                        <!-- <FormInput inputType="number" v-model="channel.refill" :maxValue="channel.capacity" class="text-center min-w-12" :disabled="channel.product && !channel.product.is_available" v-if="opsJobItem.status >= 2 && opsJobItem.status < 3">
                        </FormInput> -->
                        <select name="channel_refill" id="channel_refill" class="rounded" v-model="channel.refill" :disabled="channel.product && !channel.product.is_available" v-if="opsJobItem.status >= 2 && opsJobItem.status < 3">
                          <option v-for="n in channel.capacity + 1" :key="n-1" :value="n-1">{{ n-1 }}</option>
                        </select>

                        <span v-if="opsJobItem.status > 2">
                          {{ channel.refill }}
                        </span>
                      </td>
                      <td
                        class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-bold sm:pl-6 text-center text-gray-900" v-if="opsJobItem.status >= 3"
                        :class="[channel.vmc_after_qty && (channel.qty + channel.refill) != channel.vmc_after_qty ? 'text-red-500' : '']"
                        >
                        {{ (channel.capacity - (channel.capacity - channel.qty)) + channel.refill }}
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-bold sm:pl-6 text-center text-gray-900 bg-gray-100" v-if="opsJobItem.status >= 3">
                        {{ channel.vmc_before_qty }}
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-bold sm:pl-6 text-center text-gray-900 bg-gray-100" v-if="opsJobItem.status >= 3">
                        {{ (channel.vmc_after_qty - channel.vmc_before_qty) ? (channel.vmc_after_qty - channel.vmc_before_qty) : 0 }}
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-bold sm:pl-6 text-center text-gray-900 bg-gray-100" v-if="opsJobItem.status >= 3">
                        {{ channel.vmc_after_qty }}
                      </td>
                      <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-bold sm:pl-6 text-center text-gray-900 bg-gray-100"  v-if="opsJobItem.status >= 3">
                        <button type="button" class="rounded-full bg-green-600 p-1.5 text-white shadow-sm hover:bg-green-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-600"
                        @click.prevent="isErrorSettleClicked(channel)"
                        v-if="channel.vmc_after_qty && (channel.qty + channel.refill) != channel.vmc_after_qty && channel.is_error_settle == 0"
                        >
                          <CheckCircleIcon class="h-4 w-4" aria-hidden="true" />
                        </button>

                        <div
                          class="inline-flex justify-center items-center rounded px-1 py-0.5 text-xs font-medium border w-fit bg-green-500 text-white"
                          v-if="channel.is_error_settle == 1"
                        >
                          <div class="flex flex-col">
                              <span class="font-semibold grow-0">
                                Fixed
                              </span>
                          </div>
                        </div>
                      </td>
                    </tr>
                    <tr v-if="channels && channels.length" class="bg-gray-200 shadow-lg rounded">
                      <td class="py-6 text-sm font-bold text-center text-gray-800" colspan="3">
                        <div class="flex justify-center">
                          Total
                        </div>
                      </td>
                      <td class="py-4 text-sm font-bold text-center text-gray-800">
                        {{ getSubtotalNeeded() }}/ {{ getSubtotalCapacity() }}
                      </td>
                      <td class="py-4 text-sm font-bold text-center text-gray-800" v-if="opsJobItem.status < 2">
                        {{ getSubtotalPicked() }}
                      </td>
                      <td class="py-4 text-sm font-bold text-center text-gray-800" v-if="opsJobItem.status >= 2">
                        {{ getSubtotalRefill() }}
                      </td>
                      <td class="py-4 text-sm font-bold text-center text-gray-800" v-if="opsJobItem.status >= 3">
                        {{ getSubtotalVMCInventoryCount() }}
                      </td>
                      <td class="py-4 text-sm font-bold text-center text-gray-800" v-if="opsJobItem.status >= 3">
                        {{ getSubtotalVMCBeforeQty() }}
                      </td>
                      <td class="py-4 text-sm font-bold text-center text-gray-800" v-if="opsJobItem.status >= 3">
                        {{ getSubtotalVMCQty() }}
                      </td>
                      <td class="py-4 text-sm font-bold text-center text-gray-800" v-if="opsJobItem.status >= 3">
                        {{ getSubtotalVMCAfterQty() }}
                      </td>
                      <td v-if="opsJobItem.status >= 3"></td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
        <div class="px-2 pt-3 border-b mb-2 border-gray-100 text-left">
          <dl class="divide-y divide-gray-100">
            <div class="flex flex-col md:flex-row md:items-center px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0" v-if="opsJobItem.status >= 2">
              <dt class="text-sm font-medium leading-6 text-gray-900">
                Cash Collected
                <span class="text-red-500">*</span>
              </dt>
              <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                <div class="flex space-x-2">
                  <FormInput inputType="number" v-model="form.cash_amount" class="text-center" :disabled="opsJobItem.status >= 3">
                  </FormInput>
                </div>
              </dd>
            </div>
            <div class="flex flex-col md:flex-row md:items-center px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0" v-if="opsJobItem.status >= 2">
              <dt class="text-sm font-medium leading-6 text-gray-900">
                Stock Out (VMC)
                <span class="text-red-500">*</span>
              </dt>
              <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                <div class="flex flex-col md:flex-row md:items-center space-x-2 justify-between">
                  <label for="temp_cash_amount_from_vmc" class="font-semibold">
                    Cash Amount
                  </label>
                  <FormInput inputType="number" v-model="form.temp_cash_amount_from_vmc" class="text-center" :disabled="opsJobItem.status >= 3">
                  </FormInput>
                </div>
              </dd>
            </div>
            <div class="flex flex-col md:flex-row md:items-center px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-2 sm:px-0" v-if="opsJobItem.status > 2">
              <dt class="text-sm font-medium leading-6 text-gray-900">
                <div class="flex flex-col space-y-1 items-center">
                  <div>
                    Stock Out (Transactions)
                  </div>
                  <div
                    class="inline-flex justify-center items-center rounded px-1 py-0.5 text-xs font-medium border w-xs text-white w-fit"
                    :class="[opsJobItem.previous_ops_job_item_id ? 'bg-green-500' : 'bg-red-500']"
                  >
                    <div class="flex flex-col font-semibold grow-0">
                      <span>
                        {{ opsJobItem.previous_ops_job_item_id ? 'Detected' : 'Not Detected' }}
                      </span>
                    </div>
                  </div>
                </div>
              </dt>
              <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                <div class="flex flex-col md:flex-row md:items-center space-x-2 justify-between">
                  <label for="acc_vend_transactions_count" class="font-semibold">
                    Total Qty
                  </label>
                  <FormInput inputType="number" v-model="form.acc_vend_transactions_count" class="text-center" :disabled="true">
                  </FormInput>
                </div>
                <div class="flex flex-col md:flex-row md:items-center space-x-2 justify-between">
                  <label for="acc_vend_transactions_amount" class="font-semibold">
                    Total Amount
                  </label>
                  <FormInput inputType="number" v-model="form.acc_vend_transactions_amount" class="text-center" :disabled="true">
                  </FormInput>
                </div>
                <div class="flex flex-col md:flex-row md:items-center space-x-2 justify-between">
                  <label for="acc_vend_transactions_cash_amount" class="font-semibold">
                    Cash Amount
                  </label>
                  <FormInput inputType="number" v-model="form.acc_vend_transactions_cash_amount" class="text-center" :disabled="true">
                  </FormInput>
                </div>
                <div class="flex flex-col md:flex-row md:items-center space-x-2 justify-between">
                  <label for="acc_vend_transactions_cashless_amount" class="font-semibold">
                    Cashless Amount
                  </label>
                  <FormInput inputType="number" v-model="form.acc_vend_transactions_cashless_amount" class="text-center" :disabled="true">
                  </FormInput>
                </div>
                <div class="flex flex-col md:flex-row md:items-center space-x-2 justify-between">
                  <label for="acc_vend_transactions_promo_amount" class="font-semibold">
                    Discount Amount
                  </label>
                  <FormInput inputType="number" v-model="form.acc_vend_transactions_promo_amount" class="text-center" :disabled="true">
                  </FormInput>
                </div>
              </dd>
            </div>
          </dl>
        </div>

        <div class="sm:col-span-6 pb-2 md:pt-2 md:pb-3">
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
          <AttachmentList :items="opsJobItem.attachments"></AttachmentList>
        </div>
        <div class="sm:col-span-6">
          <UploadFileInput :endpoint="'/ops-jobs/items/' + opsJobItem.id + '/upload-attachments'"></UploadFileInput>
        </div>

        <div class="flex justify-between">
          <span>
            <Button
                type="button"
                class=" px-2 py-2 mt-2 ml-1 text-md  flex space-x-1 bg-red-500 hover:bg-red-700 text-white"
                @click="onStatusClicked(99)"
                v-if="(opsJobItem.status < 3 && opsJobItem.status != 99) && permissions.includes('delete operations')"
            >
              <span class="flex space-x-1 items-center">
                <XCircleIcon class="w-4 h-4"></XCircleIcon>
                <span>
                  Cancel
                </span>
              </span>
            </Button>
            <Button
                type="button"
                class=" px-2 py-2 mt-2 ml-1 text-md  flex space-x-1 bg-red-500 hover:bg-red-700 text-white"
                @click="onStatusClicked(-1)"
                v-if="opsJobItem.status == 99 && permissions.includes('delete operations')"
            >
              <span class="flex space-x-1 items-center">
                <TrashIcon class="w-4 h-4"></TrashIcon>
                <span>
                  Delete
                </span>
              </span>
            </Button>
          </span>
          <span>
            <Button
                type="button"
                class=" px-2 py-2 mt-2 ml-1 text-md  flex space-x-1 bg-yellow-400 hover:bg-yellow-500 text-gray-800"
                @click="onConfirmClicked()"
                v-if="opsJobItem.status < 2 && opsJobItem.status < 3"
            >
              <span class="flex space-x-1 items-center">
                <ClipboardDocumentCheckIcon class="w-4 h-4"></ClipboardDocumentCheckIcon>
                <span>
                  Picked
                </span>
              </span>
            </Button>
            <Button
                type="button"
                class=" px-2 py-2 mt-2 ml-1 text-md  flex space-x-1 bg-green-400 hover:bg-green-500 text-gray-800"
                @click="onConfirmClicked()"
                v-if="opsJobItem.status == 2 && opsJobItem.status < 3"
            >
            <span class="flex space-x-1 items-center">
                <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                <span>
                  Stock In
                </span>
              </span>
            </Button>

            <Button
                type="button"
                class=" px-2 py-2 mt-2 ml-1 text-md  flex space-x-1 bg-green-500 hover:bg-green-600 text-white"
                @click="onVerifyClicked(1)"
                v-if="opsJobItem.status >= 3 && opsJobItem.status != 4"
            >
              <span class="flex space-x-1 items-center">
                <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                <span>
                  Verified
                </span>
              </span>
            </Button>
            <Button
                type="button"
                class=" px-2 py-2 mt-2 ml-1 text-md  flex space-x-1 bg-red-500 hover:bg-red-700 text-white"
                @click="onVerifyClicked(0)"
                v-if="opsJobItem.status >= 3 && opsJobItem.status != 98"
            >
              <span class="flex space-x-1 items-center">
                <FlagIcon class="w-4 h-4"></FlagIcon>
                <span>
                  Flagged
                </span>
              </span>
            </Button>
          </span>
          <span v-if="vend.product_mapping_name">
            <a :href="'/product-mappings/' + vend.product_mapping_id + '/edit'" target="_blank" class="hover:cursor-pointer flex flex-col text-blue-800 text-sm p-3">
              <span class="" v-if="vend.product_mapping_name">
              {{ vend.product_mapping_name }}
              </span>
              <span v-if="vend.product_mapping_remarks">
                {{ vend.product_mapping_remarks }}
              </span>
            </a>
          </span>
        </div>
      </template>
    </Modal>
  </Teleport>
</template>

<script setup>
import {CheckCircleIcon, ClipboardDocumentCheckIcon, FlagIcon, TrashIcon, XCircleIcon } from '@heroicons/vue/20/solid';
import AttachmentList from '@/Components/AttachmentList.vue';
import Button from '@/Components/Button.vue';
import FormInput from '@/Components/FormInput.vue';
import FormTextarea from '@/Components/FormTextarea.vue';
import Modal from '@/Components/Modal.vue';
import UploadFileInput from '@/Components/UploadFileInput.vue';
import { onMounted, ref } from 'vue';
import { router, usePage, useForm } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";

const props = defineProps({
  opsJobItem: Object,
  showModal: Boolean,
})

const channels = ref([])
const permissions = usePage().props.auth.permissions
const toast = useToast()
const vend = ref([])


onMounted(() => {
  vend.value = props.opsJobItem.vend

  form.value = props.opsJobItem ? useForm({
    ...props.opsJobItem,
  }) : useForm(getDefaultForm())

  channels.value = props.opsJobItem.opsJobItemChannels.map((opsJobItemChannel) => {
    return {
      ...opsJobItemChannel.vendChannel,
      id: opsJobItemChannel.id,
      is_error_settle: opsJobItemChannel.is_error_settle,
      ops_job_item_channel_id: opsJobItemChannel.id,
      picked: props.opsJobItem.status < 2 ? (opsJobItemChannel.vendChannel.product && opsJobItemChannel.vendChannel.product.is_available ? (opsJobItemChannel.vendChannel.capacity - opsJobItemChannel.vendChannel.qty) : 0) : opsJobItemChannel.picked_qty,
      refill: props.opsJobItem.status == 2 ? opsJobItemChannel.picked_qty : opsJobItemChannel.actual_qty,
      product: opsJobItemChannel.vendChannel.product ? {
        ...opsJobItemChannel.vendChannel.product,
      } : null,
      vmc_before_qty: opsJobItemChannel.vmc_before_qty,
      vmc_after_qty: opsJobItemChannel.vmc_after_qty,
      // set static capacity and qty once opsJobItem status is more than 3 (stocked in)
      capatity: props.opsJobItem.status >= 3 ? opsJobItemChannel.capacity : opsJobItemChannel.vendChannel.capacity,
      qty: props.opsJobItem.status >= 3 ? opsJobItemChannel.qty : opsJobItemChannel.vendChannel.qty,
    }
  })
})
const form = ref(
  useForm(getDefaultForm())
)
const profile = usePage().props.auth.profile
const emit = defineEmits(['modalClose', 'statusUpdated', 'updatedNoRefresh'])

function getDefaultForm() {
  return {
    cash_amount: '',
    cashless_amount: '',
    temp_cash_amount_from_vmc: '',
    remarks: '',
  }
}

// subtotals
function getSubtotalCapacity() {
  return channels.value.reduce((acc, channel) => {
    return acc + channel.capacity
  }, 0)
}

function getSubtotalNeeded() {
  return channels.value.reduce((acc, channel) => {
    return acc + (channel.capacity - channel.qty)
  }, 0)
}

function getSubtotalPicked() {
  return channels.value.reduce((acc, channel) => {
    return acc + channel.picked
  }, 0)
}

function getSubtotalRefill() {
  return channels.value.reduce((acc, channel) => {
    return acc + channel.refill
  }, 0)
}

function getSubtotalVMCInventoryCount() {
  return channels.value.reduce((acc, channel) => {
    return acc + (channel.capacity - (channel.capacity - channel.qty)) + channel.refill
  }, 0)
}

function getSubtotalVMCBeforeQty() {
  return channels.value.reduce((acc, channel) => {
    return acc + channel.vmc_before_qty
  }, 0)
}

function getSubtotalVMCAfterQty() {
  return channels.value.reduce((acc, channel) => {
    return acc + channel.vmc_after_qty
  }, 0)
}

function getSubtotalVMCQty() {
  return channels.value.reduce((acc, channel) => {
    return acc + (channel.vmc_after_qty - channel.vmc_before_qty)
  }, 0)
}

function isErrorSettleClicked(channel) {
  router.post('/ops-jobs/item-channels/' + channel.id + '/settle-error', {
  }, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success("Successfully Settled", {
        timeout: 3000
      });
      emit('updatedNoRefresh')
      channels.value = channels.value.map((c) => {
        if (c.id == channel.id) {
          c.is_error_settle = 1
        }
        return c
      })
    }
  })
}

function onConfirmClicked() {
  let isConfirm = false;
  let confirmText = 'Are you sure to Stock In? with ';

  if(form.value.status == 2 && form.value.cash_amount == 0) {
    confirmText += 'Cash Collected = 0; ';
    isConfirm = true;
  }

  if(form.value.status == 2 && form.value.temp_cash_amount_from_vmc == 0) {
    confirmText += 'VMC Cash Amount = 0; ';
    isConfirm = true;
  }

  if(isConfirm) {
    const approval = confirm(confirmText);
    if (!approval) {
        return;
    }
  }
  // console.log(channels.value.map((channel) => {
  //     return {
  //       id: channel.ops_job_item_channel_id,
  //       capacity: channel.capacity,
  //       picked: channel.picked,
  //       qty: channel.qty,
  //       refill: channel.refill,
  //     }
  //   }))

  router.post('/ops-jobs/items/' + props.opsJobItem.id + '/confirm', {
    channels: channels.value.map((channel) => {
      return {
        id: channel.ops_job_item_channel_id,
        capacity: channel.capacity,
        picked: channel.picked,
        qty: channel.qty,
        refill: channel.refill,
      }
    }),
    ...form.value
  }, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success("Successfully Updated", {
        timeout: 3000
      });
      emit('statusUpdated')
      emit('modalClose')
    }
  })
}

function onSaveFormClicked() {
  router.post('/ops-jobs/items/' + props.opsJobItem.id + '/update', form.value, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success("Successfully Saved", {
        timeout: 3000
      });
    }
  })
}

function onStatusClicked(nextStatus) {
  router.post('/ops-jobs/items/' + props.opsJobItem.id + '/status', {
    ...form.value,
    nextStatus: nextStatus,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success("Successfully Updated", {
        timeout: 3000
      });
      emit('statusUpdated')
      emit('modalClose')
    }
  })
}

function onVerifyClicked(verify) {
  router.post('/ops-jobs/items/' + props.opsJobItem.id + '/verify', {
    verify: verify
  }, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success("Successfully Verified", {
        timeout: 3000
      });
      emit('statusUpdated')
      emit('modalClose')
    }
  })
}

function formatDatetime(value) {
  return moment(value).format('YYMMDD hh:mm A');
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
    case 4:
      statusClass = 'bg-green-400 text-gray-800'
      break;
    case 98:
    case 99:
      statusClass = 'bg-red-500 text-white'
      break;
  }
  return statusClass
}


</script>