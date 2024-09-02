<template>
  <Head title="VM Edit" />
  <BreezeAuthenticatedLayout>
    <template #header >
      <div class="flex flex-col space-y-1 md:flex-row md:space-x-3 md:space-y-0 text-left">
          <span class="font-semibold" v-if="opsJobItem.sequence">
            ({{ opsJobItem.sequence }})
          </span>
          <span>
            Job ID#
            <span class="text-blue-800">
              {{ opsJobItem.ref_id }}
            </span>
          </span>
          <span v-if="opsJobItem.vend">
            Machine ID#
            <span class="text-blue-800">
              {{ opsJobItem.vend.code }}
            </span>
          </span>
          <!-- <span v-if="opsJobItem.customer" class="text-gray-700">
            ({{ opsJobItem.customer.id + 20000 }})
            {{ opsJobItem.customer.name }}
          </span> -->
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
    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll p-5">
            <div class="px-2 border-b mb-2 border-gray-100 text-left">
            <dl class="divide-y divide-gray-100">
              <div class="px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0" v-if="opsJobItem.opsJob">
                <dt class="text-sm font-medium leading-6 text-gray-900">
                  Date
                </dt>
                <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                  <div class="flex space-x-2">
                    <span>
                      {{ opsJobItem.opsJob.date_formatted }}
                    </span>
                  </div>
                </dd>
              </div>
              <div class="px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0" v-if="opsJobItem.customer">
                <dt class="text-sm font-medium leading-6 text-gray-900">
                  Customer
                </dt>
                <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                  <div class="flex space-x-2">
                    <span>
                      ({{ opsJobItem.customer.id + 20000 }})
                      {{ opsJobItem.customer.name }}
                    </span>
                  </div>
                </dd>
              </div>
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
              <div class="px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0">
                <dt class="text-sm font-medium leading-6 text-gray-900">
                  Remarks
                </dt>
                <dd class="text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                  <FormTextarea v-model="form.remarks" class="w-full" rows="3">
                  </FormTextarea>
                  <span v-if="opsJobItem.remarks_updated_at" class="text-gray-500 text-xs">
                    Last {{ opsJobItem.remarks_updated_at }} ({{ opsJobItem.remarksUpdatedBy.name }})
                  </span>
                </dd>
              </div>
            </dl>
          </div>
          <div class="flex md:justify-end mb-2 px-4 py-3">
            <Button
                type="button"
                class="px-2 py-2 ml-1 text-xs md:text-md flex space-x-1 bg-green-500 hover:bg-green-600 text-white w-full md:w-fit"
                @click.prevent="onSaveRemarksClicked()"
            >
              <span class="flex space-x-1 items-center">
                <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                <span>
                  Save Remarks
                </span>
              </span>
            </Button>
          </div>

          <div class="flex flex-col">
            <div class="-my-2 -mx-4 overflow-x-auto sm:-mx-3 lg:-mx-5">
              <div class="inline-block min-w-full py-2 align-middle md:px-3 lg:px-6">
                <div class="overflow-scroll max-h-[900px] md:max-h-[1500px] shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
                  <!-- mobile view -->
                  <table class="md:hidden min-w-full divide-y divide-gray-300">
                    <thead class="bg-gray-50">
                      <tr v-if="opsJobItem.status >= 3">
                        <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-3 pr-3 text-center text-xs font-semibold text-gray-900 backdrop-blur-3xl backdrop-filter sm:pl-2 lg:pl-2" colspan="2">
                        </th>
                        <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-200 bg-opacity-75 py-3.5 pl-3 pr-3 text-center text-xs font-semibold text-gray-900 backdrop-blur-3xl backdrop-filter sm:pl-2 lg:pl-2" colspan="4">
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
                                (B) {{ opsJobItem.vendChannelRecord.before_date_created_at_formatted }}
                                </span>
                                <span v-else>
                                Not Detected
                                </span>
                              </div>
                            </div>
                            <div
                              class="inline-flex justify-center items-center rounded px-1 py-0.5 text-xs font-medium border w-xs text-white w-fit"
                              :class="[opsJobItem.vendChannelRecord ? 'bg-green-500' : 'bg-red-500']"
                              v-if="opsJobItem.vendChannelRecord && opsJobItem.vendChannelRecord.after_data_created_at"
                            >
                              <div class="flex flex-col font-semibold grow-0">
                                (A) {{ opsJobItem.vendChannelRecord.after_data_created_at_formatted }}
                              </div>
                            </div>
                          </div>
                        </th>
                      </tr>
                      <tr>
                        <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-3 pr-3 text-center text-xs font-semibold text-gray-900 backdrop-blur-3xl backdrop-filter sm:pl-2 lg:pl-2">
                          <div class="flex flex-col space-y-2">
                            <span>
                              Channel
                            </span>
                            <span>
                              Image
                            </span>
                            <span>
                              Product
                            </span>
                          </div>
                        </th>
                        <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-3 pr-3 text-center text-xs font-semibold text-gray-900 backdrop-blur-3xl backdrop-filter sm:pl-2 lg:pl-2">
                          <div class="flex flex-col space-y-2">
                            <span>
                              Needed/Capacity
                            </span>
                            <span :class="[opsJobItem.status < 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status < 2">
                              Picked
                            </span>
                            <span :class="[opsJobItem.status == 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status >= 2">
                              <div class="flex flex-col space-y-1">
                                <span>
                                  Stock In
                                </span>
                                <span>
                                  <div
                                    class="inline-flex justify-center items-center rounded px-1 py-0.5 text-xs font-medium border w-xs text-white w-fit bg-green-500"
                                    v-if="opsJobItem.completed_at"
                                  >
                                    <div class="flex flex-col font-semibold grow-0">
                                      {{ opsJobItem.completed_at }}
                                    </div>
                                  </div>
                                </span>
                              </div>
                            </span>
                            <span :class="[opsJobItem.status == 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status >= 2">
                              VMC Inventory Count
                            </span>
                          </div>
                        </th>
                        <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-200 bg-opacity-75 py-3.5 pl-3 pr-3 text-center text-xs font-semibold text-gray-900 backdrop-blur-3xl backdrop-filter sm:pl-2 lg:pl-2" :class="[opsJobItem.status == 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status > 2">
                          <div class="flex flex-col space-y-2">
                            <span>
                              Before Refill
                            </span>
                            <span>
                              Stock In
                            </span>
                            <span>
                              After Refill
                            </span>
                          </div>
                        </th>
                        <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-200 bg-opacity-75 py-3.5 pl-3 pr-3 text-center text-xs font-semibold text-gray-900 backdrop-blur-3xl backdrop-filter sm:pl-2 lg:pl-2" :class="[opsJobItem.status == 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status > 2">
                          VMC Inventory Not Tally, Fixed?
                        </th>
                      </tr>
                    </thead>
                    <tbody class="bg-white">
                      <tr v-for="(channel, channelIndex) in channels" :key="channel.id" :class="channelIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                        <td class="whitespace py-5 pl-4 pr-3 text-sm font-semibold sm:pl-6 text-left text-gray-800 text-center">
                          <div class="flex flex-col space-y-1">
                            <div>
                              #{{ channel.code }}
                            </div>
                            <div class="flex items-center justify-center" >
                              <img class="h-20 w-20 min-w-20 min-h-20 rounded-lg" :src="channel.product.thumbnail.full_url" alt="" v-if="channel.product && channel.product.thumbnail" :class="[channel.product && channel.product.is_available ? '' : 'opacity-50']"/>
                            </div>
                            <div :class="[(channel.product && channel.product.is_available) ? 'text-gray-700' : 'text-gray-400']">
                                <p class="break-words text-xs" v-if="channel.product && channel.product.name">
                                  {{ channel.product.name }}
                                </p>
                            </div>
                          </div>
                        </td>
                        <td class="whitespace py-5 pl-4 pr-3 text-sm font-bold sm:pl-6 text-center text-gray-900">
                          <div class="flex flex-col space-y-1 justify-center px-1.5">
                            <div class="flex justify-between items-center">
                              <span class="inline-flex items-center rounded-full bg-blue-50 px-1 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">need</span>
                              {{ channel.capacity - channel.qty }}/ {{ channel.capacity }}
                            </div>
                            <div class="flex justify-between items-center" :class="[opsJobItem.status < 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status < 2">
                              <select name="channel_picked" id="channel_picked" class="rounded" :class="[channel.picked != (channel.capacity - channel.qty) ? 'text-red-500' : '']" v-model="channel.picked" :disabled="channel.product && !channel.product.is_available" v-if="opsJobItem.status < 2">
                                <option v-for="n in channel.capacity + 1" :key="n-1" :value="n-1">{{ n-1 }}</option>
                              </select>
                              <span v-if="opsJobItem.status >= 2">
                                {{ channel.picked }}
                              </span>
                            </div>
                            <div class="flex space-x-1 items-center justify-between" :class="[opsJobItem.status == 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status >= 2">
                              <ArrowRightEndOnRectangleIcon class="w-5 h-5 text-blue-600">
                              </ArrowRightEndOnRectangleIcon>
                              <select name="channel_refill" id="channel_refill" class="rounded" v-model="channel.refill" :disabled="channel.product && !channel.product.is_available" v-if="opsJobItem.status >= 2 && opsJobItem.status < 3">
                                <option v-for="n in channel.capacity + 1" :key="n-1" :value="n-1">{{ n-1 }}</option>
                              </select>
                              <span v-if="opsJobItem.status > 2">
                                {{ channel.refill }}
                              </span>
                            </div>
                            <div class="flex justify-between space-x-1 items-center" :class="[opsJobItem.status == 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status >= 2">
                              <span class="inline-flex items-center rounded-full bg-blue-50 px-1 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">VMC</span>
                              <span>
                                {{ (channel.capacity - (channel.capacity - channel.qty)) + channel.refill }}
                              </span>
                            </div>
                          </div>
                        </td>
                        <td class="whitespace-nowrap py-5 pl-4 pr-3 text-sm font-bold sm:pl-6 text-center text-gray-900 bg-gray-100" v-if="opsJobItem.status >= 3">
                          <div class="flex flex-col space-y-1" v-if="opsJobItem.vendChannelRecord">
                            <span>
                              {{ channel.vmc_before_qty }}
                            </span>
                            <span>
                              {{ (channel.vmc_after_qty - channel.vmc_before_qty) ? (channel.vmc_after_qty - channel.vmc_before_qty) : 0 }}
                            </span>
                            <span :class="[channel.virtual_is_error && !channel.is_error_settle ? 'text-red-500' : (channel.virtual_is_error && channel.is_error_settle ? 'text-blue-500' : '')]">
                              {{ channel.vmc_after_qty }}
                            </span>
                          </div>
                        </td>
                        <td class="whitespace-nowrap py-5 pl-4 pr-3 text-sm font-bold sm:pl-6 text-center text-gray-900 bg-gray-100"  v-if="opsJobItem.status >= 3">
                          <button type="button" class="rounded-full bg-red-500 p-1.5 text-white shadow-sm hover:bg-red-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600"
                          @click.prevent="isErrorSettleClicked(channel)"
                          v-if="channel.virtual_is_error && !channel.is_error_settle"
                          >
                            <span class="text-white text-xs shadow-sm">
                              Fix?
                            </span>
                          </button>
                          {{ channel }}

                          <div class="flex flex-col space-y-1 items-center">
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
                            <span class="text-xs text-gray-600" v-if="channel.is_error_settle && channel.error_settled_at_formatted">
                              {{ channel.error_settled_at_formatted }}
                            </span>
                          </div>
                        </td>
                      </tr>
                      <tr v-if="channels && channels.length" class="bg-gray-200 shadow-lg rounded">
                        <td class="py-6 text-sm font-bold text-center text-gray-800" colspan="1">
                          <div class="flex justify-center">
                            Total
                          </div>
                        </td>
                        <td class="py-4 text-sm font-bold text-center text-gray-800">
                          <div class="flex flex-col space-y-1 items-center">
                            <span>
                              {{ getSubtotalNeeded() }}/ {{ getSubtotalCapacity() }}
                            </span>
                            <span :class="[opsJobItem.status < 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status < 2">
                              {{ getSubtotalPicked() }}
                            </span>
                            <span :class="[opsJobItem.status >= 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status >= 2">
                              <div class="flex space-x-1 items-center text-center">
                                <!-- <ArrowRightEndOnRectangleIcon class="w-4 h-4 text-blue-600">
                                </ArrowRightEndOnRectangleIcon> -->
                                <span>
                                  {{ getSubtotalRefill() }}
                                </span>
                              </div>
                            </span>
                            <span :class="[opsJobItem.status >= 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status >= 2">
                              <div class="flex space-x-1 items-center text-center">
                                <!-- <ComputerDesktopIcon class="w-3 h-3 text-blue-600">
                                </ComputerDesktopIcon> -->
                                <span>
                                  {{ getSubtotalVMCInventoryCount() }}
                                </span>
                              </div>
                            </span>
                          </div>
                        </td>
                        <td class="py-4 text-sm font-bold text-center text-gray-800" v-if="opsJobItem.status >= 3">
                          <div class="flex flex-col space-y-1" >
                            <span>
                              {{ getSubtotalVMCBeforeQty() }}
                            </span>
                            <span>
                              {{ getSubtotalVMCQty() }}
                            </span>
                            <span>
                              {{ getSubtotalVMCAfterQty() }}
                            </span>
                          </div>
                        </td>
                        <td v-if="opsJobItem.status >= 3"></td>
                      </tr>
                    </tbody>
                  </table>

                  <!-- desktop view -->
                  <table class="hidden md:table min-w-full divide-y divide-gray-300">
                    <thead class="bg-gray-50">
                      <tr v-if="opsJobItem.status >= 3">
                        <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-3 pr-3 text-center text-xs font-semibold text-gray-900 backdrop-blur-3xl backdrop-filter sm:pl-2 lg:pl-2" colspan="6">
                        </th>
                        <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-200 bg-opacity-75 py-3.5 pl-3 pr-3 text-center text-xs font-semibold text-gray-900 backdrop-blur-3xl backdrop-filter sm:pl-2 lg:pl-2" colspan="4">
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
                                (B) {{ opsJobItem.vendChannelRecord.before_date_created_at_formatted }}
                                </span>
                                <span v-else>
                                Not Detected
                                </span>
                              </div>
                            </div>
                            <div
                              class="inline-flex justify-center items-center rounded px-1 py-0.5 text-xs font-medium border w-xs text-white w-fit"
                              :class="[opsJobItem.vendChannelRecord ? 'bg-green-500' : 'bg-red-500']"
                              v-if="opsJobItem.vendChannelRecord && opsJobItem.vendChannelRecord.after_data_created_at"
                            >
                              <div class="flex flex-col font-semibold grow-0">
                                (A) {{ opsJobItem.vendChannelRecord.after_data_created_at_formatted }}
                              </div>
                            </div>
                          </div>
                        </th>
                      </tr>
                      <tr>
                        <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-3 pr-3 text-center text-xs font-semibold text-gray-900 backdrop-blur-3xl backdrop-filter sm:pl-2 lg:pl-2">
                          #
                        </th>
                        <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-3 pr-3 text-center text-xs font-semibold text-gray-900 backdrop-blur-3xl backdrop-filter sm:pl-2 lg:pl-2">
                          Image
                        </th>
                        <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-3 pr-3 text-center text-xs font-semibold text-gray-900 backdrop-blur-3xl backdrop-filter sm:pl-2 lg:pl-2">
                          Product
                        </th>
                        <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-3 pr-3 text-center text-xs font-semibold text-gray-900 backdrop-blur-3xl backdrop-filter sm:pl-2 lg:pl-2">
                          Needed/ Capacity
                        </th>
                        <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-3 pr-3 text-center text-xs font-semibold text-gray-900 backdrop-blur-3xl backdrop-filter sm:pl-2 lg:pl-2" :class="[opsJobItem.status < 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status < 2">
                          Picked
                        </th>
                        <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-3 pr-3 text-center text-xs font-semibold text-gray-900 backdrop-blur-3xl backdrop-filter sm:pl-2 lg:pl-2" :class="[opsJobItem.status == 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status >= 2">
                          <div class="flex flex-col space-y-1">
                            <span>
                              Stock In
                            </span>
                            <span>
                              <div
                                class="inline-flex justify-center items-center rounded px-1 py-0.5 text-xs font-medium border w-xs text-white w-fit bg-green-500"
                                v-if="opsJobItem.completed_at"
                              >
                                <div class="flex flex-col font-semibold grow-0">
                                  {{ opsJobItem.completed_at }}
                                </div>
                              </div>
                            </span>
                          </div>
                        </th>
                        <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-3 pr-3 text-center text-xs font-semibold text-gray-900 backdrop-blur-3xl backdrop-filter sm:pl-2 lg:pl-2" :class="[opsJobItem.status == 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status >= 2">
                          VMC Inventory Count
                        </th>
                        <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-200 bg-opacity-75 py-3.5 pl-3 pr-3 text-center text-xs font-semibold text-gray-900 backdrop-blur-3xl backdrop-filter sm:pl-2 lg:pl-2" :class="[opsJobItem.status == 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status > 2">
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
                        <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-200 bg-opacity-75 py-3.5 pl-3 pr-3 text-center text-xs font-semibold text-gray-900 backdrop-blur-3xl backdrop-filter sm:pl-2 lg:pl-2" :class="[opsJobItem.status == 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status > 2">
                          Stock In
                        </th>
                        <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-200 bg-opacity-75 py-3.5 pl-3 pr-3 text-center text-xs font-semibold text-gray-900 backdrop-blur-3xl backdrop-filter sm:pl-2 lg:pl-2" :class="[opsJobItem.status == 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status > 2">
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
                        <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-200 bg-opacity-75 py-3.5 pl-3 pr-3 text-center text-xs font-semibold text-gray-900 backdrop-blur-3xl backdrop-filter sm:pl-2 lg:pl-2" :class="[opsJobItem.status == 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status > 2">
                          VMC Inventory Not Tally, Fixed?
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
                          class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-bold sm:pl-6 text-center text-gray-900" v-if="opsJobItem.status >= 2"
                          >
                          {{ (channel.capacity - (channel.capacity - channel.qty)) + channel.refill }}
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-bold sm:pl-6 text-center text-gray-900 bg-gray-100" v-if="opsJobItem.status >= 3 && opsJobItem.vendChannelRecord">
                          {{ channel.vmc_before_qty }}
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-bold sm:pl-6 text-center text-gray-900 bg-gray-100" v-if="opsJobItem.status >= 3 && opsJobItem.vendChannelRecord">
                          {{ (channel.vmc_after_qty - channel.vmc_before_qty) ? (channel.vmc_after_qty - channel.vmc_before_qty) : 0 }}
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-bold sm:pl-6 text-center text-gray-900 bg-gray-100" v-if="opsJobItem.status >= 3 && opsJobItem.vendChannelRecord">
                          <span :class="[channel.virtual_is_error && !channel.is_error_settle ? 'text-red-500' : (channel.virtual_is_error && channel.is_error_settle ? 'text-blue-500' : '')]">
                            {{ channel.vmc_after_qty }}
                          </span>
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-bold sm:pl-6 text-center text-gray-900 bg-gray-100"  v-if="opsJobItem.status >= 3">
                          <button type="button" class="rounded-full bg-red-500 p-1.5 text-white shadow-sm hover:bg-red-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-600"
                          @click.prevent="isErrorSettleClicked(channel)"
                          v-if="channel.virtual_is_error && !channel.is_error_settle"
                          >
                            <span class="text-white text-xs shadow-sm">
                              Fix?
                            </span>
                          </button>

                          <div class="flex flex-col space-y-1 items-center">
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
                            <span class="text-xs text-gray-600" v-if="channel.is_error_settle && channel.error_settled_at_formatted">
                              {{ channel.error_settled_at_formatted }}
                            </span>
                          </div>
                        </td>
                      </tr>
                      <tr v-if="channels && channels.length" class="bg-gray-200 shadow-lg rounded">
                        <td class="py-4 text-sm font-bold text-center text-gray-800 align-top" colspan="3">
                          <div class="flex flex-col space-y-2">
                            <span>
                              Total Qty
                            </span>
                            <span>
                              Stock Value
                            </span>
                          </div>
                        </td>
                        <td class="py-4 text-sm font-bold text-center text-gray-800 align-top">
                          {{ getSubtotalNeeded() }}/ {{ getSubtotalCapacity() }}
                        </td>
                        <td class="py-4 text-sm font-bold text-center text-gray-800 align-top" v-if="opsJobItem.status < 2">
                          <div class="flex flex-col space-y-2">
                            <span>
                              {{ getSubtotalPicked() }}
                            </span>
                            <span>
                              {{ operatorCountry.currency_symbol }}{{ getSubtotalPickedAmount().toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                            </span>
                          </div>
                        </td>
                        <td class="py-4 text-sm font-bold text-center text-gray-800 align-top" v-if="opsJobItem.status >= 2">
                          <div class="flex flex-col space-y-2">
                            <span>
                              {{ getSubtotalRefill() }}
                            </span>
                            <span>
                              {{ operatorCountry.currency_symbol }}{{ getSubtotalRefillAmount().toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                            </span>
                          </div>
                        </td>
                        <td class="py-4 text-sm font-bold text-center text-gray-800 align-top" v-if="opsJobItem.status >= 2">
                          {{ getSubtotalVMCInventoryCount() }}
                        </td>
                        <td class="py-4 text-sm font-bold text-center text-gray-800 align-top" v-if="opsJobItem.status >= 3 && opsJobItem.vendChannelRecord">
                          {{ getSubtotalVMCBeforeQty() }}
                        </td>
                        <td class="py-4 text-sm font-bold text-center text-gray-800 align-top" v-if="opsJobItem.status >= 3 && opsJobItem.vendChannelRecord">
                          {{ getSubtotalVMCQty() }}
                        </td>
                        <td class="py-4 text-sm font-bold text-center text-gray-800 align-top" v-if="opsJobItem.status >= 3 && opsJobItem.vendChannelRecord">
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
              <div class="flex justify-between">
                <div class="flex flex-col md:flex-row md:items-center px-4 py-3 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0" v-if="opsJobItem.status >= 2">
                  <dt class="text-sm font-medium leading-6 text-gray-900">
                    Cash Collected (Machine)
                    <span class="text-red-500">*</span>
                  </dt>
                  <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                    <div class="flex space-x-2">
                      <FormInput inputType="text" v-model="form.cash_amount" class="text-center" :disabled="opsJobItem.status > 3 || opsJobItem.is_cash_collected">
                      </FormInput>
                    </div>
                  </dd>
                  <dt class="text-sm font-medium leading-6 text-gray-900">
                    CashAmt$ (VMC)
                    <span class="text-red-500">*</span>
                  </dt>
                  <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                    <div class="flex space-x-2">
                      <FormInput inputType="text" v-model="form.temp_cash_amount_from_vmc" class="text-center" :disabled="opsJobItem.status > 3 || opsJobItem.is_cash_collected">
                      </FormInput>
                    </div>
                  </dd>
                  <dt class="text-sm font-medium leading-6 text-gray-900">
                    Cash Adjustment
                  </dt>
                  <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                    <div class="flex space-x-2">
                      <span class="py-2 px-1" :class="[(form.cash_amount - form.temp_cash_amount_from_vmc) == 0 ? 'text-green-600' : ((form.cash_amount - form.temp_cash_amount_from_vmc) < 0 ? 'text-red-600' : 'text-blue-600')]">
                        {{ (form.cash_amount - form.temp_cash_amount_from_vmc).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
                      </span>
                    </div>
                  </dd>
                </div>
                <div class="flex items-center">
                  <Button
                      type="button"
                      class=" px-2 py-2 mt-2 ml-1 text-md flex space-x-1 bg-green-500 hover:bg-green-600 text-white"
                      @click="onCashCollectedClicked()"
                      v-if="opsJobItem.status > 1 && opsJobItem.status <= 3 && !opsJobItem.is_cash_collected"
                  >
                    <span class="flex space-x-1 items-center">
                      <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                      <span>
                        Update Cash
                      </span>
                    </span>
                  </Button>
                </div>
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
                        <span v-if="!opsJobItem.previous_ops_job_item_id">
                          Not Detected
                        </span>
                        <span v-if="opsJobItem.previous_ops_job_item_id">
                          #{{ opsJobItem.previousOpsJobItem.ref_id }}
                        </span>
                        <span class="flex space-x-2 justify-between" v-if="opsJobItem.previous_ops_job_item_id">
                          <span>
                            from
                          </span>
                          <span>
                            {{ opsJobItem.previousOpsJobItem ? opsJobItem.previousOpsJobItem.completed_at : '' }}
                          </span>
                        </span>
                        <span class="flex space-x-2 justify-between" v-if="opsJobItem.previous_ops_job_item_id">
                          <span>
                            to
                          </span>
                          <span>
                            {{ opsJobItem.completed_at ? opsJobItem.completed_at : '' }}
                          </span>
                        </span>
                      </div>
                    </div>
                  </div>
                </dt>
                <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                  <div class="flex items-center space-x-2 justify-between">
                    <label for="acc_vend_transactions_count" class="font-semibold">
                      Total Qty
                    </label>
                    <span class="py-3 px-2 font-semibold">
                      {{form.acc_vend_transactions_count.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0 })}}
                    </span>

                    <!-- <FormInput inputType="number" v-model="form.acc_vend_transactions_count" class="text-center" :disabled="true">
                    </FormInput> -->
                  </div>
                  <div class="flex items-center space-x-2 justify-between">
                    <label for="acc_vend_transactions_amount" class="font-semibold">
                      Total Amount
                    </label>
                    <span class="py-3 px-2 font-semibold">
                      {{ operatorCountry.currency_symbol }}{{form.acc_vend_transactions_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
                    </span>
                    <!-- <FormInput inputType="number" v-model="form.acc_vend_transactions_amount" class="text-center" :disabled="true">
                    </FormInput> -->
                  </div>
                  <div class="flex items-center space-x-2 justify-between">
                    <label for="acc_vend_transactions_cash_amount" class="font-semibold">
                      Cash Amount
                    </label>
                    <span class="py-3 px-2 font-semibold">
                      {{ operatorCountry.currency_symbol }}{{form.acc_vend_transactions_cash_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
                    </span>
                    <!-- <FormInput inputType="number" v-model="form.acc_vend_transactions_cash_amount" class="text-center" :disabled="true">
                    </FormInput> -->
                  </div>
                  <div class="flex items-center space-x-2 justify-between">
                    <label for="acc_vend_transactions_cashless_amount" class="font-semibold">
                      Cashless Amount
                    </label>
                    <span class="py-3 px-2 font-semibold">
                      {{ operatorCountry.currency_symbol }}{{form.acc_vend_transactions_cashless_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
                    </span>
                    <!-- <FormInput inputType="number" v-model="form.acc_vend_transactions_cashless_amount" class="text-center" :disabled="true">
                    </FormInput> -->
                  </div>
                  <div class="flex items-center space-x-2 justify-between">
                    <label for="acc_vend_transactions_promo_amount" class="font-semibold">
                      Discount Amount
                    </label>
                    <span class="py-3 px-2 font-semibold">
                      {{ operatorCountry.currency_symbol }}{{form.acc_vend_transactions_promo_amount.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
                    </span>
                    <!-- <FormInput inputType="number" v-model="form.acc_vend_transactions_promo_amount" :step="[operatorCountry.currency_exponent == 2 && !operatorCountry.is_currency_exponent_hidden ? .01 : '' ]" class="text-center" :disabled="true">
                    </FormInput> -->
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
            <AttachmentList :items="opsJobItem.attachments" :isEditEnabled="opsJobItem.status >= 3 && opsJobItem.is_cash_collected ? false : true"></AttachmentList>
          </div>
          <div class="sm:col-span-6">
            <UploadFileInput :endpoint="'/ops-jobs/items/' + props.opsJobItem.data.id + '/upload-attachments'"></UploadFileInput>
          </div>
          <div class="sm:col-span-6">
            <DropzoneFileInput :endpoint="'/ops-jobs/items/' + props.opsJobItem.data.id + '/upload-attachments'"></DropzoneFileInput>
          </div>

          <div class="flex justify-between">
            <span>
              <Link :href="'/ops-jobs/' + opsJobItem.ops_job_id + '/edit'">
                <Button
                  type="button" class="px-2 py-2 mt-2 bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1 "
                >
                  <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
                  <span>
                    Back
                  </span>
                </Button>
              </Link>
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
            <span v-if="vend.productMapping">
              <a :href="'/product-mappings/' + vend.productMapping.id + '/edit'" target="_blank" class="hover:cursor-pointer flex flex-col text-blue-800 text-sm p-3">
                <span class="" v-if="vend.productMapping">
                {{ vend.productMapping.name }}
                </span>
                <span v-if="vend.productMapping.remarks">
                  {{ vend.productMapping.remarks }}
                </span>
              </a>
            </span>
          </div>

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
import DropzoneFileInput from '@/Components/DropzoneFileInput.vue';
import FormInput from '@/Components/FormInput.vue';
import FormTextarea from '@/Components/FormTextarea.vue';
import SingleSortItem from '@/Components/SingleSortItem.vue';
import UploadFileInput from '@/Components/UploadFileInput.vue';
import {ArrowUturnLeftIcon, ArrowRightEndOnRectangleIcon, CheckCircleIcon, ClipboardDocumentCheckIcon, ComputerDesktopIcon, FlagIcon, TrashIcon, XCircleIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";

const props = defineProps({
  opsJobItem: Object,
})
const channels = ref([])
const operatorCountry = usePage().props.auth.operatorCountry
const opsJobItem = ref([])
const permissions = usePage().props.auth.permissions
const toast = useToast()
const vend = ref([])

const form = ref(
  useForm(getDefaultForm())
)

onMounted(() => {
  loadingData()
})

function loadingData() {
  opsJobItem.value = props.opsJobItem.data
  vend.value = props.opsJobItem.data.vend

  form.value = opsJobItem.value ? useForm({
    ...opsJobItem.value,
  }) : useForm(getDefaultForm())

  channels.value = props.opsJobItem.data.opsJobItemChannels.map((opsJobItemChannel) => {
    return {
      ...opsJobItemChannel.vendChannel,
      id: opsJobItemChannel.id,
      amount: opsJobItemChannel.vendChannel.amount,
      error_settled_at_formatted: opsJobItemChannel.error_settled_at_formatted,
      is_error_settle: opsJobItemChannel.is_error_settle,
      ops_job_item_channel_id: opsJobItemChannel.id,
      picked: props.opsJobItem.data.status < 2 ? (opsJobItemChannel.vendChannel.product && opsJobItemChannel.vendChannel.product.is_available ? (opsJobItemChannel.vendChannel.capacity - opsJobItemChannel.vendChannel.qty) : 0) : opsJobItemChannel.picked_qty,
      refill: props.opsJobItem.data.status == 2 ? opsJobItemChannel.picked_qty : opsJobItemChannel.actual_qty,
      product: opsJobItemChannel.vendChannel.product ? {
        ...opsJobItemChannel.vendChannel.product,
      } : null,
      vmc_before_qty: opsJobItemChannel.vmc_before_qty,
      vmc_after_qty: opsJobItemChannel.vmc_after_qty,
      // set static capacity and qty once opsJobItem status is more than 3 (stocked in)
      capatity: props.opsJobItem.data.status >= 3 ? opsJobItemChannel.capacity : opsJobItemChannel.vendChannel.capacity,
      qty: props.opsJobItem.data.vendChannelRecord ? opsJobItemChannel.vmc_before_qty : (props.opsJobItem.data.status >= 3 ? opsJobItemChannel.qty : opsJobItemChannel.vendChannel.qty),
      virtual_is_error: opsJobItemChannel.virtual_is_error,
    }
  })
}

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
function getSubtotalPickedAmount() {
  return channels.value.reduce((acc, channel) => {
    return acc + (channel.picked * channel.amount)
  }, 0)
}

function getSubtotalRefill() {
  return channels.value.reduce((acc, channel) => {
    return acc + channel.refill
  }, 0)
}

function getSubtotalRefillAmount() {
  return channels.value.reduce((acc, channel) => {
    return acc + (channel.refill * channel.amount)
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
  let confirmText = '';

  if(form.value.status == 1) {
    confirmText = 'Are you sure you want to Picked? ';
    isConfirm = true;
  }

  if(form.value.status == 2) {
    confirmText = 'Are you sure you want to Stock In? ';
    isConfirm = true;
  }

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

  router.post('/ops-jobs/items/' + opsJobItem.value.id + '/confirm', {
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
      router.reload({
        only: ['opsJobItem'],
        replace: true,
        preserveState: true,
        onSuccess: page => {
          loadingData()
        }
      })
    }
  })
}

function onCashCollectedClicked() {
  const approval = confirm('Are you sure to confirm Cash Collection?');
  if (!approval) {
      return;
  }

  if(form.value.cash_amount == 0 || form.value.temp_cash_amount_from_vmc == 0) {
    const zeroApproval = confirm('Are you sure to confirm Cash Collection with 0 amount?');
    if (!zeroApproval) {
        return;
    }
  }

  if(form.value.cash_amount == null || form.value.temp_cash_amount_from_vmc == null) {
    const nullApproval = confirm('Are you sure to confirm Cash Collection with 0 amount?');
    if (!nullApproval) {
        return;
    }
  }

  router.post('/ops-jobs/items/' + opsJobItem.value.id + '/cash-collected', {
    ...form.value,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success("Successfully Saved", {
        timeout: 3000
      });
      router.reload({
        only: ['opsJobItem'],
        replace: true,
        preserveState: true,
        onSuccess: page => {
          loadingData()
        }
      })
    }
  })
}

function onSaveFormClicked() {
  router.post('/ops-jobs/items/' + opsJobItem.value.id + '/update', form.value, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success("Successfully Saved", {
        timeout: 3000
      });
    }
  })
}

function onSaveRemarksClicked() {
  router.post('/ops-jobs/items/' + opsJobItem.value.id + '/update/remarks', {remarks: form.value.remarks}, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success("Successfully Saved", {
        timeout: 3000
      });
    }
  })
}

function onStatusClicked(nextStatus) {
  router.post('/ops-jobs/items/' + opsJobItem.value.id + '/status', {
    ...form.value,
    nextStatus: nextStatus,
  }, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success("Successfully Updated", {
        timeout: 3000
      });
      router.reload({
        only: ['opsJobItem'],
        replace: true,
        preserveState: true,
        onSuccess: page => {
          loadingData()
        }
      })
    }
  })
}

function onVerifyClicked(verify) {
  router.post('/ops-jobs/items/' + opsJobItem.value.id + '/verify', {
    verify: verify
  }, {
    preserveScroll: true,
    onSuccess: () => {
      toast.success("Successfully Verified", {
        timeout: 3000
      });
      router.reload({
        only: ['opsJobItem'],
        replace: true,
        preserveState: true,
        onSuccess: page => {
          loadingData()
        }
      })
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