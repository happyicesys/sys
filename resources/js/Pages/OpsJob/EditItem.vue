<template>
  <Head title="VM Edit" />
  <BreezeAuthenticatedLayout>
    <template #header >
      <div class="flex flex-col space-y-1 md:flex-row md:justify-between md:items-center text-left">
        <div class="flex flex-col space-y-1 md:flex-row md:space-x-3 md:space-y-0">
          <span class="font-semibold" v-if="opsJobItem.sequence && opsJobItem.status < 3">
            ({{ opsJobItem.sequence }})
          </span>
          <span>
            Job ID#
            <span class="text-blue-800">
              {{ opsJobItem.ref_id }}
            </span>
          </span>
          <span v-if="opsJobItem.vend && opsJobItem.vend.vendPrefix">
            Prefix
            <span class="text-blue-800">
              {{ opsJobItem.vend.vendPrefix.name }}
            </span>
          </span>
          <span v-if="opsJobItem.vend">
            Machine ID#
            <span class="text-blue-800">
              <a :href="'/vends/customers?codes=' + opsJobItem.vend.code" target="_blank" class="text-blue-700">
                <span>
                  {{ opsJobItem.vend.code }}
                </span>
              </a>
            </span>
          </span>
          <!-- <span v-if="opsJobItem.customer" class="text-gray-700">
            ({{ opsJobItem.customer.id + 20000 }})
            {{ opsJobItem.customer.name }}
          </span> -->
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
            <div
                class="inline-flex justify-center items-center rounded px-1 py-0.5 text-xs font-medium border w-fit capitalize"
                :class="opsJobItem.stock_action_type == 'implement_new_mapping' ? 'bg-purple-100 text-purple-700 border-purple-200' : opsJobItem.stock_action_type == 'onsite_adjustment' ? 'bg-teal-100 text-teal-700 border-teal-200' : 'bg-orange-100 text-orange-700 border-orange-200'"
                v-if="opsJobItem.stock_action_type"
            >
                {{ opsJobItem.stock_action_type.replace(/_/g, ' ') }}
            </div>
          </div>
        </div>
      </div>
    </template>
    <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
          <div class="shadow-sm ring-1 ring-black ring-opacity-5 overflow-scroll p-5">
            <div class="px-2 border-b mb-2 border-gray-100 text-left relative flex justify-between items-center">
              <div></div>
              <div class="absolute right-0 top-0 mt-2 mr-2">
                <Menu as="div" class="relative inline-block text-left" v-if="permissions.includes('admin-access operations') && opsJobItem.status == 1">
                  <div>
                    <MenuButton class="inline-flex w-full justify-center gap-x-1.5 rounded-md bg-sky-400 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-sky-500 focus:outline-none ring-1 ring-inset ring-sky-300">
                      Stock Action
                      <ChevronDownIcon class="-mr-1 h-5 w-5 text-sky-100" aria-hidden="true" />
                    </MenuButton>
                  </div>

                  <transition enter-active-class="transition ease-out duration-100" enter-from-class="transform opacity-0 scale-95" enter-to-class="transform opacity-100 scale-100" leave-active-class="transition ease-in duration-75" leave-from-class="transform opacity-100 scale-100" leave-to-class="transform opacity-0 scale-95">
                    <MenuItems class="absolute right-0 z-10 mt-2 w-auto origin-top-right rounded-md bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none">
                      <div class="py-1">
                        <MenuItem v-slot="{ active }" v-if="vend && vend.productMapping && vend.productMapping.upcoming_product_mapping_id">
                          <button type="button" @click="onUpdateStockAction('implement_new_mapping')" :class="[active ? 'bg-gray-100 text-gray-900' : 'text-gray-700', 'block w-full px-4 py-2 text-left text-sm whitespace-nowrap']">
                            Implement New Mapping <span class="text-gray-600">换新菜单</span>
                          </button>
                        </MenuItem>
                        <MenuItem v-slot="{ active }">
                          <button type="button" @click="onUpdateStockAction('return_stock')" :class="[active ? 'bg-gray-100 text-gray-900' : 'text-gray-700', 'block w-full px-4 py-2 text-left text-sm whitespace-nowrap']">
                            Return All Stock <span class="text-gray-600">撤货</span>
                          </button>
                        </MenuItem>
                        <MenuItem v-slot="{ active }">
                          <button type="button" @click="onUpdateStockAction('onsite_adjustment')" :class="[active ? 'bg-gray-100 text-gray-900' : 'text-gray-700', 'block w-full px-4 py-2 text-left text-sm whitespace-nowrap']">
                            Stock Adjustment <span class="text-gray-600">调整货量</span>
                          </button>
                        </MenuItem>
                        <MenuItem v-slot="{ active }" v-if="opsJobItem.stock_action_type">
                          <button type="button" @click="onUpdateStockAction(null)" :class="[active ? 'bg-gray-100 text-gray-900' : 'text-gray-700', 'block w-full px-4 py-2 text-left text-sm']">
                            Clear Action
                          </button>
                        </MenuItem>
                      </div>
                    </MenuItems>
                  </transition>
                </Menu>
              </div>
            </div>
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
              <div class="px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0" v-if="opsJobItem.status == 1  && opsJobItem.undoPickedBy">
                <dt class="text-sm font-medium leading-6 text-gray-900">
                  Undo Picked By
                </dt>
                <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                  <div class="flex space-x-2">
                    <span>
                      {{ opsJobItem.undoPickedBy.name }}
                    </span>
                    <span>
                      ({{ opsJobItem.undo_picked_at }})
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
              <div class="px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0" v-if="opsJobItem.status == 2 && opsJobItem.undoCompletedBy">
                <dt class="text-sm font-medium leading-6 text-gray-900">
                  Undo Stock In By
                </dt>
                <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                  <div class="flex space-x-2">
                    <span>
                      {{ opsJobItem.undoCompletedBy.name }}
                    </span>
                    <span>
                      ({{ opsJobItem.undo_completed_at }})
                    </span>
                  </div>
                </dd>
              </div>
              <div class="px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0" v-if="opsJobItem.status == 4 && opsJobItem.verifiedBy">
                <dt class="text-sm font-medium leading-6 text-gray-900">
                  Verified By
                </dt>
                <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                  <div class="flex space-x-2">
                    <span>
                      {{ opsJobItem.verifiedBy.name }}
                    </span>
                    <span>
                      ({{ opsJobItem.verified_at }})
                    </span>
                  </div>
                </dd>
              </div>
              <div class="px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0" v-if="opsJobItem.status == 3 && opsJobItem.undoVerifiedBy">
                <dt class="text-sm font-medium leading-6 text-gray-900">
                  Undo Verified By
                </dt>
                <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                  <div class="flex space-x-2">
                    <span>
                      {{ opsJobItem.undoVerifiedBy.name }}
                    </span>
                    <span>
                      ({{ opsJobItem.undo_verified_at }})
                    </span>
                  </div>
                </dd>
              </div>
              <div class="px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0" v-if="opsJobItem.status == 98 && opsJobItem.flaggedBy">
                <dt class="text-sm font-medium leading-6 text-gray-900">
                  Flagged By
                </dt>
                <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                  <div class="flex space-x-2">
                    <span>
                      {{ opsJobItem.flaggedBy.name }}
                    </span>
                    <span>
                      ({{ opsJobItem.flagged_at }})
                    </span>
                  </div>
                </dd>
              </div>
              <div class="px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0" v-if="opsJobItem.status == 3 && opsJobItem.undoFlaggedBy">
                <dt class="text-sm font-medium leading-6 text-gray-900">
                  Undo Flagged By
                </dt>
                <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                  <div class="flex space-x-2">
                    <span>
                      {{ opsJobItem.undoFlaggedBy.name }}
                    </span>
                    <span>
                      ({{ opsJobItem.undo_flagged_at }})
                    </span>
                  </div>
                </dd>
              </div>
              <div class="px-4 py-2 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-0" v-if="opsJobItem.cms_transaction_id && opsJobItem.cmsTransactionBy">
                <dt class="text-sm font-medium leading-6 text-gray-900">
                  API Inv By
                </dt>
                <dd class="mt-1 text-sm leading-6 text-gray-700 sm:col-span-2 sm:mt-0">
                  <div class="flex space-x-2">
                    <span>
                      {{ opsJobItem.cmsTransactionBy.name }}
                    </span>
                    <span>
                      ({{ opsJobItem.cms_transaction_at }})
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
          <div class="flex md:justify-between mb-2 px-4 py-3">
            <Button
                type="button"
                class="px-2 py-2 ml-1 text-xs md:text-md flex space-x-1 bg-yellow-500 hover:bg-yellow-600 text-black w-full md:w-fit"
                @click.prevent="onIsIgnoreLimitClicked()"
                v-if="!opsJobItem.is_ignore_limit"
            >
              <span class="flex space-x-1 items-center">
                <StopCircleIcon class="w-4 h-4"></StopCircleIcon>
                <span>
                  Bypass Capped Qty
                </span>
              </span>
            </Button>
            <Button
                type="button"
                class="px-2 py-2 ml-1 text-xs md:text-md flex space-x-1 bg-yellow-500 hover:bg-yellow-600 text-black w-full md:w-fit"
                @click.prevent="onIsIgnoreLimitClicked()"
                v-if="opsJobItem.is_ignore_limit"
            >
              <span class="flex space-x-1 items-center">
                <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                <span>
                  Abide Capped Qty
                </span>
              </span>
            </Button>
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
                <div class="overflow-scroll max-h-[600px] md:max-h-[800px] shadow ring-1 ring-black ring-opacity-5 md:rounded-lg">
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
                              Needed Qty/Capacity
                            </span>
                            <span :class="[opsJobItem.status < 2 ? 'text-blue-700' : 'text-gray-900']">
                              To Pick Qty
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
                      <tr v-for="(channel, channelIndex) in channels" :key="channel.id" :class="[channel.is_replaced ? ('bg-red-50 border-t-2 border-r-2 border-l-2 border-dashed border-gray-400 ' + (channels[channelIndex + 1] && channels[channelIndex + 1].is_upcoming_product && channels[channelIndex + 1].vend_channel_id == channel.vend_channel_id ? '' : 'border-b-2')) : (channel.is_upcoming_product ? ('border-b-2 border-r-2 border-l-2 border-dashed border-gray-400 ' + (channels[channelIndex - 1] && channels[channelIndex - 1].is_replaced && channels[channelIndex - 1].vend_channel_id == channel.vend_channel_id ? '' : 'border-t-2')) : (channelIndex % 2 === 0 ? undefined : 'bg-gray-50'))]">
                        <td class="whitespace py-5 pl-4 pr-3 text-sm font-semibold sm:pl-6 text-left text-gray-800 text-center">
                          <div class="flex flex-col space-y-1">
                            <div class="flex items-center justify-center space-x-1">
                              <span>#{{ channel.code }}</span>
                              <span v-if="channel.is_upcoming_product" class="inline-flex items-center rounded bg-purple-100 px-1 py-0.5 text-[10px] font-bold text-purple-700 ring-1 ring-inset ring-purple-700/10">Upcoming</span>
                              <span v-if="channel.is_replaced" class="inline-flex items-center rounded bg-gray-100 px-1 py-0.5 text-[10px] font-bold text-gray-700 ring-1 ring-inset ring-gray-700/10">Current</span>
                            </div>
                            <div class="flex items-center justify-center" >
                              <img class="h-20 w-20 min-w-20 min-h-20 rounded-lg" :src="channel.product.thumbnail.full_url" alt="" v-if="channel.product && channel.product.thumbnail" :class="[channel.product && channel.product.is_available ? '' : 'opacity-50']"/>
                            </div>
                            <div :class="[(channel.product && channel.product.is_available) ? 'text-gray-700' : 'text-gray-400']">
                                <p class="break-words text-xs font-bold" :class="[channel.is_upcoming_product ? 'text-purple-700' : '']" v-if="channel.product && channel.product.name">
                                  {{ channel.product.name }}
                                </p>
                            </div>
                          </div>
                        </td>
                        <td class="whitespace py-5 pl-4 pr-3 text-sm font-bold sm:pl-6 text-center">
                          <div class="flex flex-col space-y-2 items-center justify-center px-1.5">
                            <div class="flex space-x-2 items-center">
                              <span class="inline-flex items-center rounded-full bg-blue-50 px-1 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">need</span>
                              <span :class="[channel.is_replaced ? 'line-through text-gray-400' : (channel.product && channel.product.is_available ? 'text-gray-800' : 'text-gray-400')]">
                                {{ channel.capacity - channel.qty }}/ {{ channel.capacity }}
                              </span>
                            </div>
                            <div class="flex flex-col items-center" :class="[opsJobItem.status == 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status > 1">
                              <span class="flex flex-col space-y-1" :class="[opsJobItem.status >= 2 || (channel.product && channel.product.is_available) ? (channel.before_picked != null && channel.picked < (channel.capacity - channel.before_picked) ? 'text-red-500' : (channel.picked > (channel.capacity - channel.before_picked) ? 'text-blue-500' : 'text-black')) : 'text-gray-400']">
                                <span class="inline-flex items-center rounded-full bg-blue-50 px-1 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10" v-if="!channel.is_replaced">picked</span>
                                <span class="inline-flex items-center rounded-full bg-red-50 px-1 py-0.5 text-xs font-medium text-red-700 ring-1 ring-inset ring-red-700/10" v-else>return</span>
                                <span>
                                  {{ channel.picked }}
                                </span>
                                <span class="text-xs text-red-500" v-if="channel.picked_limit != null && !opsJobItem.is_ignore_limit">
                                  capped ({{ channel.picked_limit }})
                                </span>
                              </span>
                            </div>
                            <div class="flex flex-col items-center" :class="[opsJobItem.status < 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status < 2">
                              <div v-if="channel.is_replaced" class="text-xs text-gray-500 italic py-2">
                                N/A
                              </div>
                              <select v-else-if="channel.is_return_stock || channel.is_onsite_adjustment" name="channel_picked" :id="channel.is_onsite_adjustment ? 'channel_picked_onsite' : 'channel_picked_return'" class="rounded" :class="channel.is_onsite_adjustment ? 'text-teal-600' : 'text-orange-600'" v-model="channel.picked">
                                <option v-for="n in channel.qty + 1" :key="-(n-1)" :value="-(n-1)">{{ -(n-1) }}</option>
                              </select>
                              <select v-else name="channel_picked" id="channel_picked" class="rounded" :class="[channel.picked != (channel.capacity - channel.qty) ? 'text-red-500' : 'text-black', channel.is_upcoming_product ? 'ring-2 ring-purple-500' : '']" v-model="channel.picked" v-if="opsJobItem.status < 2">
                                <option v-for="v in getPickOptions(channel)" :key="v" :value="v">{{ v }}</option>
                              </select>
                              <span class="text-xs text-red-500" v-if="channel.picked_limit != null && !opsJobItem.is_ignore_limit">
                                capped ({{ channel.picked_limit }})
                              </span>
                            </div>
                            <div class="flex flex-col items-center" :class="[opsJobItem.status == 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status >= 2">
                              <select name="channel_refill" id="channel_refill" class="rounded" :class="[channel.refill < channel.picked ? 'text-red-500' : (channel.refill > channel.picked ? 'text-blue-500' : 'text-black')]" v-model="channel.refill" v-if="opsJobItem.status >= 2 && opsJobItem.status < 3">
                                <option v-for="v in getRefillOptions(channel)" :key="v" :value="v">{{ v }}</option>
                              </select>
                              <span v-if="opsJobItem.status > 2" :class="[opsJobItem.status >= 2 || (channel.product && channel.product.is_available) ? (channel.refill < channel.picked ? 'text-red-500' : (channel.refill > channel.picked ? 'text-blue-500' : 'text-black')) : 'text-gray-400']">
                                <ArrowRightEndOnRectangleIcon class="w-5 h-5 text-blue-600">
                                </ArrowRightEndOnRectangleIcon>
                                {{ channel.refill }}
                              </span>
                            </div>
                            <div class="flex flex-col items-center" :class="[opsJobItem.status == 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status >= 2">
                              <span :class="[opsJobItem.status >= 2 || (channel.product && channel.product.is_available) ? (opsJobItem.status == 2 ? 'text-blue-700' : 'text-gray-900') : 'text-gray-400']">
                                <span class="inline-flex items-center rounded-full bg-blue-50 px-1 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">VMC</span>
                                <span :class="[(opsJobItem.status == 2 && channel.refill != 0) ? 'font-bold text-blue-600 transition-colors duration-300' : 'transition-colors duration-300']">
                                  {{ Number(channel.qty) + (opsJobItem.status < 3 ? Number(channel.refill) : 0) }}
                                </span>
                              </span>
                            </div>
                          </div>
                        </td>
                        <td class="whitespace-nowrap py-5 pl-4 pr-3 text-sm font-bold sm:pl-6 text-center bg-gray-100" :class="[channel.product && channel.product.is_available ? 'text-gray-800' : 'text-gray-400']" v-if="opsJobItem.status >= 3">
                          <div class="flex flex-col space-y-1" v-if="opsJobItem.vendChannelRecord">
                            <template v-if="!channel.is_replaced">
                              <span>
                                {{ channel.vmc_before_qty }}
                              </span>
                              <span>
                                {{ (channel.vmc_after_qty - channel.vmc_before_qty) ? (channel.vmc_after_qty - channel.vmc_before_qty) : 0 }}
                              </span>
                              <span :class="[channel.virtual_is_error && !channel.is_error_settle ? 'text-red-500' : (channel.virtual_is_error && channel.is_error_settle ? 'text-blue-500' : '')]">
                                {{ channel.vmc_after_qty }}
                              </span>
                            </template>
                            <template v-else>
                              <span class="text-xs text-gray-500 italic py-2">N/A</span>
                            </template>
                          </div>
                        </td>
                        <td class="whitespace-nowrap py-5 pl-4 pr-3 text-sm font-bold sm:pl-6 text-center text-gray-900 bg-gray-100"  v-if="opsJobItem.status >= 3">
                          <template v-if="!channel.is_replaced">
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
                          </template>
                          <template v-else>
                            <span class="text-xs text-gray-500 italic py-2">N/A</span>
                          </template>
                        </td>
                      </tr>
                      <tr v-if="channels && channels.length" class="bg-gray-200 shadow-lg rounded">
                        <td class="py-6 text-sm font-bold text-center text-gray-800" colspan="1">
                          <div class="flex justify-center">
                            Total
                          </div>
                        </td>
                        <td class="py-4 pl-3 pr-3 text-sm font-bold text-center text-gray-800">
                          <div class="flex flex-col space-y-1">
                            <span class="flex justify-between space-x-1 items-center">
                              <span class="inline-flex items-center rounded-full bg-blue-50 px-1 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">need</span>
                              {{ getSubtotalNeeded() }}/ {{ getSubtotalCapacity() }}
                            </span>
                            <span class="flex justify-between space-x-1 items-center" :class="[opsJobItem.status < 2 ? 'text-blue-700' : 'text-gray-900']">
                              <span class="inline-flex items-center rounded-full bg-blue-50 px-1 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">picked</span>
                              {{ getSubtotalPicked() }}
                            </span>
                            <span :class="[opsJobItem.status >= 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status >= 2">
                              <div class="flex justify-between space-x-1 items-center text-center">
                                <ArrowRightEndOnRectangleIcon class="w-5 h-5 text-blue-600">
                                </ArrowRightEndOnRectangleIcon>
                                <span>
                                  {{ getSubtotalRefill() }}
                                </span>
                              </div>
                            </span>
                            <span :class="[opsJobItem.status >= 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status >= 2">
                              <div class="flex justify-between space-x-1 items-center text-center">
                                <span class="inline-flex items-center rounded-full bg-blue-50 px-1 py-0.5 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10">VMC</span>
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
                        <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-3 pr-3 text-center text-xs font-semibold text-gray-900 backdrop-blur-3xl backdrop-filter sm:pl-2 lg:pl-2" colspan="7">
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
                          Needed Qty/ Capacity
                        </th>
                        <th scope="col" class="sticky top-0 z-10 border-b border-gray-300 bg-gray-50 bg-opacity-75 py-3.5 pl-3 pr-3 text-center text-xs font-semibold text-gray-900 backdrop-blur-3xl backdrop-filter sm:pl-2 lg:pl-2" :class="[opsJobItem.status < 2 ? 'text-blue-700' : 'text-gray-900']">
                          To Pick Qty
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
                      <tr v-for="(channel, channelIndex) in channels" :key="channel.id" :class="[channel.is_replaced ? ('bg-red-50 border-t-2 border-r-2 border-l-2 border-dashed border-gray-400 ' + (channels[channelIndex + 1] && channels[channelIndex + 1].is_upcoming_product && channels[channelIndex + 1].vend_channel_id == channel.vend_channel_id ? '' : 'border-b-2')) : (channel.is_upcoming_product ? ('border-b-2 border-r-2 border-l-2 border-dashed border-gray-400 ' + (channels[channelIndex - 1] && channels[channelIndex - 1].is_replaced && channels[channelIndex - 1].vend_channel_id == channel.vend_channel_id ? '' : 'border-t-2')) : (channelIndex % 2 === 0 ? undefined : 'bg-gray-50'))]">
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold sm:pl-6 text-center text-gray-800">
                          <div class="flex flex-col items-center space-y-1">
                            <span>{{ channel.code }}</span>
                            <span v-if="channel.is_upcoming_product" class="inline-flex items-center rounded bg-purple-100 px-1 py-0.5 text-[10px] font-bold text-purple-700 ring-1 ring-inset ring-purple-700/10">Upcoming</span>
                            <span v-if="channel.is_replaced" class="inline-flex items-center rounded bg-gray-100 px-1 py-0.5 text-[10px] font-bold text-gray-700 ring-1 ring-inset ring-gray-700/10">Current</span>
                          </div>
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
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-bold sm:pl-6 text-center" :class="[channel.is_replaced ? 'line-through text-gray-400' : (channel.product && channel.product.is_available ? 'text-gray-800' : 'text-gray-400')]">
                          {{ channel.capacity - channel.qty }}/ {{ channel.capacity }}
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-bold sm:pl-6 text-center" v-if="opsJobItem.status > 1" :class="[opsJobItem.status >= 2 || (channel.product && channel.product.is_available) ? ((channel.before_picked != null && channel.picked < (channel.capacity - channel.before_picked)) ? 'text-red-500' : ((channel.picked > (channel.capacity - channel.before_picked)) ? 'text-sky-600' : 'text-gray-900')) : 'text-gray-400']">
                          <div class="flex flex-col space-y-1 items-center">
                            <span>
                              {{ channel.picked }}
                            </span>
                            <span class="text-xs text-red-500" v-if="!channel.is_replaced && channel.picked_limit != null && !opsJobItem.is_ignore_limit">
                              capped ({{ channel.picked_limit }})
                            </span>
                          </div>
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center" :class="[opsJobItem.status < 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status < 2">
                          <div class="flex flex-col items-center justify-center">
                            <div v-if="channel.is_replaced" class="text-xs text-gray-500 italic py-2">
                               N/A
                            </div>
                            <select v-else-if="channel.is_return_stock || channel.is_onsite_adjustment" name="channel_picked" :id="channel.is_onsite_adjustment ? 'channel_picked_onsite' : 'channel_picked_return'" class="rounded w-fit" :class="channel.is_onsite_adjustment ? 'text-teal-600' : 'text-orange-600'" v-model="channel.picked">
                              <option v-for="n in channel.qty + 1" :key="-(n-1)" :value="-(n-1)">{{ -(n-1) }}</option>
                            </select>
                            <select v-else name="channel_picked" id="channel_picked" class="rounded w-fit" :class="[channel.picked != (channel.capacity - channel.qty) ? 'text-red-500' : '', channel.is_upcoming_product ? 'ring-2 ring-purple-500' : '']" v-model="channel.picked" v-if="opsJobItem.status < 2">
                              <option v-for="v in getPickOptions(channel)" :key="v" :value="v">{{ v }}</option>
                            </select>
                            <span class="text-xs text-red-500" v-if="channel.picked_limit != null && !opsJobItem.is_ignore_limit">
                              capped ({{ channel.picked_limit }})
                            </span>
                          </div>
                        </td>

                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium sm:pl-6 text-center" :class="[opsJobItem.status == 2 ? 'text-blue-700' : 'text-gray-900']" v-if="opsJobItem.status >= 2">
                          <select v-if="opsJobItem.status >= 2 && opsJobItem.status < 3" name="channel_refill" id="channel_refill" class="rounded" :class="[channel.refill < channel.picked ? 'text-red-500' : (channel.refill > channel.picked ? 'text-blue-500' : 'text-black')]" v-model="channel.refill">
                            <option v-for="v in getRefillOptions(channel)" :key="v" :value="v">{{ v }}</option>
                          </select>

                          <span v-else-if="opsJobItem.status > 2" :class="[opsJobItem.status >= 2 || (channel.product && channel.product.is_available) ? (channel.refill < channel.picked ? 'text-red-500' : (channel.refill > channel.picked ? 'text-blue-500' : 'text-black')) : 'text-gray-400']">
                            {{ channel.refill }}
                          </span>
                        </td>
                        <td
                          class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-bold sm:pl-6 text-center" :class="[opsJobItem.status >= 2 || (channel.product && channel.product.is_available) ? 'text-gray-800' : 'text-gray-400']" v-if="opsJobItem.status >= 2"
                          >
                          <span :class="[(opsJobItem.status == 2 && channel.refill != 0) ? 'font-bold text-blue-600 transition-colors duration-300' : 'transition-colors duration-300']">
                            {{ Number(channel.qty) + (opsJobItem.status < 3 ? Number(channel.refill) : 0) }}
                          </span>
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-bold sm:pl-6 text-center bg-gray-100" :class="[channel.product && channel.product.is_available ? 'text-gray-800' : 'text-gray-400']" v-if="opsJobItem.status >= 3 && opsJobItem.vendChannelRecord">
                          <span v-if="!channel.is_replaced">{{ channel.vmc_before_qty }}</span>
                          <span v-else class="text-xs text-gray-500 italic">N/A</span>
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-bold sm:pl-6 text-center bg-gray-100" :class="[channel.product && channel.product.is_available ? 'text-gray-800' : 'text-gray-400']" v-if="opsJobItem.status >= 3 && opsJobItem.vendChannelRecord">
                          <span v-if="!channel.is_replaced">{{ (channel.vmc_after_qty - channel.vmc_before_qty) ? (channel.vmc_after_qty - channel.vmc_before_qty) : 0 }}</span>
                          <span v-else class="text-xs text-gray-500 italic">N/A</span>
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-bold sm:pl-6 text-center bg-gray-100" :class="[channel.product && channel.product.is_available ? 'text-gray-800' : 'text-gray-400']" v-if="opsJobItem.status >= 3 && opsJobItem.vendChannelRecord">
                          <template v-if="!channel.is_replaced">
                            <span :class="[channel.virtual_is_error && !channel.is_error_settle ? 'text-red-500' : (channel.virtual_is_error && channel.is_error_settle ? 'text-blue-500' : '')]">
                              {{ channel.vmc_after_qty }}
                            </span>
                          </template>
                          <template v-else>
                            <span class="text-xs text-gray-500 italic">N/A</span>
                          </template>
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-bold sm:pl-6 text-center text-gray-900 bg-gray-100"  v-if="opsJobItem.status >= 3">
                          <template v-if="!channel.is_replaced">
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
                          </template>
                          <template v-else>
                            <span class="text-xs text-gray-500 italic">N/A</span>
                          </template>
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
                        <td class="py-4 text-sm font-bold text-center text-gray-800 align-top">
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
                  <Button
                      type="button"
                      class=" px-2 py-2 mt-2 ml-1 text-md flex space-x-1 bg-yellow-500 hover:bg-yellow-600 text-gray-800"
                      @click="onUndoCashCollectedClicked()"
                      v-if="opsJobItem.status > 1 && opsJobItem.status <= 3 && opsJobItem.is_cash_collected && permissions.includes('admin-access operations')"
                  >
                    <span class="flex space-x-1 items-center">
                      <ArrowPathRoundedSquareIcon class="w-4 h-4"></ArrowPathRoundedSquareIcon>
                      <span>
                        Undo Cash Collection
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
                      :class="[opsJobItem.previous_ops_job_item_id && opsJobItem.previousOpsJobItem ? 'bg-green-500' : 'bg-red-500']"
                    >
                      <div class="flex flex-col font-semibold grow-0">
                        <span v-if="!opsJobItem.previous_ops_job_item_id || !opsJobItem.previousOpsJobItem">
                          Not Detected
                        </span>
                        <span v-if="opsJobItem.previous_ops_job_item_id && opsJobItem.previousOpsJobItem">
                          #{{ opsJobItem.previousOpsJobItem.ref_id }}
                        </span>
                        <span class="flex space-x-2 justify-between" v-if="opsJobItem.previous_ops_job_item_id && opsJobItem.previousOpsJobItem">
                          <span>
                            from
                          </span>
                          <span>
                            {{ opsJobItem.previousOpsJobItem.completed_at }}
                          </span>
                        </span>
                        <span class="flex space-x-2 justify-between" v-if="opsJobItem.previous_ops_job_item_id && opsJobItem.previousOpsJobItem">
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

          <div class="flex flex-col md:flex-row space-y-3 md:space-y-0 sm:col-span-6 md:justify-between">
            <span>
              <Link :href="'/ops-jobs/' + opsJobItem.ops_job_id + '/edit'">
                <Button
                  type="button" class="px-2 py-2 mt-2 bg-gray-300 hover:bg-gray-400 text-gray-700 flex space-x-1 w-full md:w-fit"
                >
                  <ArrowUturnLeftIcon class="w-4 h-4"></ArrowUturnLeftIcon>
                  <span>
                    Back
                  </span>
                </Button>
              </Link>
              <Button
                  type="button"
                  class=" px-2 py-2 mt-2 ml-1 text-md  flex space-x-1 bg-red-500 hover:bg-red-700 text-white w-full md:w-fit"
                  @click="onStatusClicked(99)"
                  v-if="(opsJobItem.status < 2 && opsJobItem.status != 99) && permissions.includes('delete operations')"
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
                  class=" px-2 py-2 mt-2 ml-1 text-md  flex space-x-1 bg-red-500 hover:bg-red-700 text-white w-full md:w-fit"
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
              <Button
                  type="button"
                  class="px-2 py-2 mt-2 ml-1 text-md  flex space-x-1 bg-yellow-400 hover:bg-yellow-500 text-gray-800 w-full md:w-fit"
                  @click="onUndoStatusClicked(opsJobItem.id)"
                  v-if="opsJobItem.status == 3 && permissions.includes('admin-access operations')"
              >
                <span class="flex space-x-1 items-center">
                  <ArrowPathRoundedSquareIcon class="w-4 h-4"></ArrowPathRoundedSquareIcon>
                  <span>
                    Undo Stock In
                  </span>
                </span>
              </Button>
              <Button
                  type="button"
                  class="px-2 py-2 mt-2 ml-1 text-md  flex space-x-1 bg-yellow-400 hover:bg-yellow-500 text-gray-800 w-full md:w-fit"
                  @click="onUndoStatusClicked(opsJobItem.id)"
                  v-if="opsJobItem.status == 2 && permissions.includes('admin-access operations') && opsJobItem.stock_action_type !== 'return_stock' && opsJobItem.stock_action_type !== 'onsite_adjustment'"
              >
                <span class="flex space-x-1 items-center">
                  <ArrowPathRoundedSquareIcon class="w-4 h-4"></ArrowPathRoundedSquareIcon>
                  <span>
                    Undo Picked
                  </span>
                </span>
              </Button>
              <Button
                  type="button"
                  class="px-2 py-2 mt-2 ml-1 text-md flex space-x-1 bg-red-500 hover:bg-red-700 text-white w-full md:w-fit"
                  @click="onDeleteClicked()"
                  v-if="opsJobItem.status == 2 && permissions.includes('admin-access operations') && (opsJobItem.stock_action_type === 'return_stock' || opsJobItem.stock_action_type === 'onsite_adjustment')"
              >
                <span class="flex space-x-1 items-center">
                  <TrashIcon class="w-4 h-4"></TrashIcon>
                  <span>
                    Delete
                  </span>
                </span>
              </Button>
              <Button
                  type="button"
                  class="px-2 py-2 mt-2 ml-1 text-md  flex space-x-1 bg-yellow-400 hover:bg-yellow-500 text-gray-800 w-full md:w-fit"
                  @click="onUndoStatusClicked(opsJobItem.id)"
                  v-if="opsJobItem.status == 4 && permissions.includes('admin-access operations')"
              >
                <span class="flex space-x-1 items-center">
                  <ArrowPathRoundedSquareIcon class="w-4 h-4"></ArrowPathRoundedSquareIcon>
                  <span>
                    Undo Verified
                  </span>
                </span>
              </Button>
              <Button
                  type="button"
                  class="px-2 py-2 mt-2 ml-1 text-md  flex space-x-1 bg-yellow-400 hover:bg-yellow-500 text-gray-800 w-full md:w-fit"
                  @click="onUndoStatusClicked(opsJobItem.id)"
                  v-if="opsJobItem.status == 98 && permissions.includes('admin-access operations')"
              >
                <span class="flex space-x-1 items-center">
                  <ArrowPathRoundedSquareIcon class="w-4 h-4"></ArrowPathRoundedSquareIcon>
                  <span>
                    Undo Flagged
                  </span>
                </span>
              </Button>
            </span>
            <span>
              <Button
                  type="button"
                  class="px-2 py-2 mt-2 ml-1 text-md  flex space-x-1 bg-gray-300 hover:bg-gray-400 text-gray-800 w-full md:w-fit"
                  @click="onSaveClicked()"
                  v-if="opsJobItem.status == 1 && opsJobItem.stock_action_type !== 'return_stock' && opsJobItem.stock_action_type !== 'onsite_adjustment'"
              >
                <span class="flex space-x-1 items-center">
                  <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                  <span>
                    Save & Freeze 'To Pick Qty'
                  </span>
                </span>
              </Button>
              <Button
                  type="button"
                  class="px-2 py-2 mt-2 ml-1 text-md  flex space-x-1 bg-yellow-400 hover:bg-yellow-500 text-gray-800 w-full md:w-fit"
                  @click="onConfirmClicked()"
                  v-if="opsJobItem.status == 1"
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
                  class=" px-2 py-2 mt-2 ml-1 text-md  flex space-x-1 bg-green-400 hover:bg-green-500 text-gray-800 w-full md:w-fit"
                  @click="onConfirmClicked()"
                  v-if="opsJobItem.status == 2"
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
                  class=" px-2 py-2 mt-2 ml-1 text-md  flex space-x-1 bg-green-500 hover:bg-green-600 text-white w-full md:w-fit"
                  @click="onVerifyClicked(1)"
                  v-if="opsJobItem.status >= 3 && opsJobItem.status != 4 && permissions.includes('admin-access operations')"
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
                  class=" px-2 py-2 mt-2 ml-1 text-md  flex space-x-1 bg-red-500 hover:bg-red-700 text-white w-full md:w-fit"
                  @click="onVerifyClicked(0)"
                  v-if="opsJobItem.status >= 3 && opsJobItem.status != 98 && permissions.includes('admin-access operations')"
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
import { Menu, MenuButton, MenuItem, MenuItems } from '@headlessui/vue';
import {ArrowUturnLeftIcon, ArrowRightEndOnRectangleIcon, CheckCircleIcon, ClipboardDocumentCheckIcon, ChevronDownIcon, ComputerDesktopIcon, FlagIcon, StopCircleIcon, TrashIcon, XCircleIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted } from 'vue'
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";
import { ArrowPathRoundedSquareIcon } from '@heroicons/vue/24/outline';

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

  const allChannels = props.opsJobItem.data.opsJobItemChannels
  channels.value = allChannels.map((opsJobItemChannel) => {
    // Identify if this row is being replaced or removed.
    // We prioritize the DB snapshot (allChannels) to ensure the UI visually matches the loaded rows.
    const is_replaced = (() => {
      if (opsJobItemChannel.is_upcoming_product) return false;
      if (opsJobItem.value.stock_action_type !== 'implement_new_mapping') return false;

      // 1. Is there an upcoming row for this slot in the DB snapshot?
      const upcomingRow = allChannels.find(u => u.is_upcoming_product && u.vend_channel_code == opsJobItemChannel.vend_channel_code);

      if (upcomingRow) {
        // If there's an upcoming row but it's the SAME product (a stale generated row), treat as normal refill.
        if (upcomingRow.product_id == opsJobItemChannel.product_id) {
          return false;
        }
        // Different product -> slot is definitively being replaced
        return true;
      }

      // 2. If NO upcoming row in DB, check if the slot is being purely cleared off.
      // We check the LIVE mapping to confirm it's no longer present.
      const v = opsJobItem.value.vend;
      if (!v) return false;

      const cMapping = v.productMapping;
      const uMapping = cMapping?.upcomingProductMapping || v.upcomingProductMapping;
      if (!uMapping) return false;

      // Key is 'productMappingItems' in the serialised JSON
      const uItems = uMapping.productMappingItems || [];
      const upcomingLiveItem = uItems.find(i => i.channel_code == opsJobItemChannel.vend_channel_code);

      if (!upcomingLiveItem) {
        // This slot has no entry in live upcoming mapping → being purely cleared off
        return true;
      }

      return false;
    })();

    // Return Stock: all channels are being cleared out (nothing to pick, return all)
    const is_return_stock = !opsJobItemChannel.is_upcoming_product &&
                            opsJobItem.value.stock_action_type === 'return_stock';

    // Onsite Adjustment: same as return stock but Stock In defaults to 0 (not -currentQty)
    const is_onsite_adjustment = !opsJobItemChannel.is_upcoming_product &&
                                  opsJobItem.value.stock_action_type === 'onsite_adjustment';

    // picked logic
    // picked logic
    const finalProduct = opsJobItemChannel.product || opsJobItemChannel.vendChannel.product;
    let pickedQty = 0;
    if (opsJobItem.value.status >= 2) {
      pickedQty = opsJobItemChannel.picked_qty;
    } else {
      if (is_return_stock || is_onsite_adjustment) {
        // Restore previously saved negative qty if it exists, otherwise default to 0
        pickedQty = (opsJobItemChannel.saved_picked_qty != null && opsJobItemChannel.saved_picked_qty <= 0)
          ? opsJobItemChannel.saved_picked_qty
          : 0;
      } else if (is_replaced) {
        pickedQty = -opsJobItemChannel.qty;
      } else {
        const activeProduct = opsJobItemChannel.product || opsJobItemChannel.vendChannel.product;
        const initialDefault = opsJobItemChannel.is_upcoming_product ? 5 : (opsJobItemChannel.vendChannel.capacity - opsJobItemChannel.vendChannel.qty);
        pickedQty = initialDefault;

        if (opsJobItemChannel.saved_picked_qty != null) {
          if (opsJobItemChannel.is_upcoming_product && opsJobItemChannel.saved_picked_qty === 0) {
            pickedQty = 5;
          } else {
            pickedQty = opsJobItemChannel.saved_picked_qty;
          }
        } else if (activeProduct) {
          if (!activeProduct.is_available) {
            pickedQty = 0;
          } else if (activeProduct.max_ops_job_pick_limit != null && !opsJobItem.value.is_ignore_limit) {
            let maxLimit = activeProduct.max_ops_job_pick_limit;
            let currentQty = opsJobItemChannel.is_upcoming_product ? 0 : opsJobItemChannel.vendChannel.qty;
            let limitBasedQty = Math.max(0, maxLimit - currentQty);

            if (limitBasedQty < pickedQty) {
                pickedQty = limitBasedQty;
            }
          }
        }
      }

    }

    // Default refill (Stock In) logic
    let refill = opsJobItemChannel.actual_qty;
    if (opsJobItem.value.status == 2 && (refill === null || refill === 0)) {
      if (is_return_stock) {
        // Use pickedQty (the user-selected partial return) as the refill default.
        // Fall back to full -currentQty only if pickedQty is still 0 (untouched).
        const currentQty = opsJobItemChannel.vendChannel ? opsJobItemChannel.vendChannel.qty : opsJobItemChannel.qty;
        refill = pickedQty !== 0 ? pickedQty : -currentQty;
      } else if (is_onsite_adjustment) {
        // Onsite Adjustment: Stock In always defaults to 0
        refill = 0;
      } else if (is_replaced) {
        refill = -opsJobItemChannel.qty;
      } else {
        refill = pickedQty;
      }
    }


    return {
      ...opsJobItemChannel.vendChannel,
      id: opsJobItemChannel.id,
      amount: opsJobItemChannel.amount || opsJobItemChannel.vendChannel.amount,
      is_upcoming_product: opsJobItemChannel.is_upcoming_product,
      is_replaced: is_replaced,
      is_return_stock: is_return_stock,
      is_onsite_adjustment: is_onsite_adjustment,
      vend_channel_id: opsJobItemChannel.vend_channel_id,
      error_settled_at_formatted: opsJobItemChannel.error_settled_at_formatted,
      is_error_settle: opsJobItemChannel.is_error_settle,
      ops_job_item_channel_id: opsJobItemChannel.id,
      picked_limit: (opsJobItemChannel.product || opsJobItemChannel.vendChannel.product) && (opsJobItemChannel.product || opsJobItemChannel.vendChannel.product).max_ops_job_pick_limit != null && !opsJobItem.value.is_ignore_limit ? (opsJobItemChannel.product || opsJobItemChannel.vendChannel.product).max_ops_job_pick_limit : null,
      before_picked: opsJobItemChannel.picked_before_qty,
      picked: pickedQty,
      before_refill: opsJobItemChannel.actual_before_qty,
      refill: refill,
      product: opsJobItemChannel.product ? opsJobItemChannel.product : (opsJobItemChannel.vendChannel.product ? {
        ...opsJobItemChannel.vendChannel.product,
      } : null),
      vmc_before_qty: opsJobItemChannel.vmc_before_qty,
      vmc_after_qty: opsJobItemChannel.vmc_after_qty,
      // set static capacity and qty once opsJobItem status is more than 3 (stocked in)
      capacity: props.opsJobItem.data.status >= 3 ? opsJobItemChannel.capacity : opsJobItemChannel.vendChannel.capacity,
      qty: opsJobItemChannel.is_upcoming_product ? 0 : (props.opsJobItem.data.vendChannelRecord ? opsJobItemChannel.vmc_before_qty : (props.opsJobItem.data.status >= 3 ? opsJobItemChannel.qty : opsJobItemChannel.vendChannel.qty)),
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

// Refill options: original is_replaced/is_return_stock logic preserved;
// normal channels get full range from -currentQty to +capacity so user can reduce stock.
// Unavailable products also get the full range (both negative and positive).
function getRefillOptions(channel) {
  if (channel.is_replaced || channel.is_return_stock) {
    // Return stock: 0 to -capacity (negative-only range)
    return Array.from({ length: channel.capacity + 1 }, (_, i) => -(i));
  }
  const negCount = Number(channel.qty) || 0;
  const posCount = Number(channel.capacity) || 0;
  const options = [];
  for (let v = -negCount; v <= posCount; v++) {
    options.push(v);
  }
  return options;
}

// Pick options: range from -currentQty to +capacity so user can select
// both negative and positive values even when the product is unavailable.
function getPickOptions(channel) {
  const negCount = Number(channel.qty) || 0;
  const posCount = Number(channel.capacity) || 0;
  const options = [];
  for (let v = -negCount; v <= posCount; v++) {
    options.push(v);
  }
  return options;
}

// subtotals
function getSubtotalNeeded() {
  return channels.value.filter(c => !c.is_replaced).reduce((acc, channel) => {
    return acc + (Number(channel.capacity) - Number(channel.qty));
  }, 0);
}

function getSubtotalCapacity() {
  return channels.value.filter(c => !c.is_replaced).reduce((acc, channel) => {
    return acc + (Number(channel.capacity));
  }, 0);
}

function getSubtotalPicked() {
  return channels.value.reduce((acc, channel) => {
    return acc + Number(channel.picked);
  }, 0);
}

function getSubtotalPickedAmount() {
  const sum = channels.value.reduce((acc, channel) => {
    return acc + (Number(channel.picked) * Number(channel.amount));
  }, 0);
  return sum / Math.pow(10, operatorCountry?.currency_exponent || 0);
}

function getSubtotalRefill() {
  return channels.value.reduce((acc, channel) => {
    return acc + Number(channel.refill);
  }, 0);
}

function getSubtotalRefillAmount() {
  const sum = channels.value.reduce((acc, channel) => {
    return acc + (Number(channel.refill) * Number(channel.amount));
  }, 0);
  return sum / Math.pow(10, operatorCountry?.currency_exponent || 0);
}

function getSubtotalVMCInventoryCount() {
  return channels.value.reduce((acc, channel) => {
    return acc + Number(channel.qty) + Number(channel.refill);
  }, 0);
}

function getSubtotalVMCBeforeQty() {
  return channels.value.filter(c => !c.is_replaced).reduce((acc, channel) => {
    return acc + Number(channel.vmc_before_qty);
  }, 0);
}

function getSubtotalVMCAfterQty() {
  return channels.value.filter(c => !c.is_replaced).reduce((acc, channel) => {
    return acc + Number(channel.vmc_after_qty);
  }, 0);
}

function getSubtotalVMCQty() {
  return channels.value.filter(c => !c.is_replaced).reduce((acc, channel) => {
    return acc + (Number(channel.vmc_after_qty) - Number(channel.vmc_before_qty));
  }, 0);
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
    const isAutoPickAction = opsJobItem.value.stock_action_type === 'return_stock' || opsJobItem.value.stock_action_type === 'onsite_adjustment';
    confirmText = isAutoPickAction
      ? 'Are you sure you want to mark as Picked? (No stock picking required for this action) '
      : 'Are you sure you want to Picked? ';
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
        code: channel.code,
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

function onSaveClicked() {
  router.post(`/ops-jobs/items/${opsJobItem.value.id}/save`, {
    channels: channels.value.map((channel) => {
      return {
        id: channel.ops_job_item_channel_id,
        code: channel.code,
        capacity: channel.capacity,
        picked: channel.picked,
        qty: channel.qty,
        refill: channel.refill,
      }
    }),
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

function onIsIgnoreLimitClicked() {
  const approval = confirm('Are you sure to toggle Qty Limit Check?');
  if (!approval) {
      return;
  }
  router.post('/ops-jobs/items/' + opsJobItem.value.id + '/toggle/is-ignore-limit', {}, {
    preserveScroll: true,
    onSuccess: () => {
      router.reload({
        only: ['opsJobItem'],
        replace: true,
        preserveState: true,
        onSuccess: page => {
          loadingData()
        }
      })
      toast.success("Successfully Saved", {
        timeout: 3000
      });
    }
  })
}

function onUndoCashCollectedClicked() {
  const approval = confirm('Are you sure to undo Cash Collection?');
  if (!approval) {
      return;
  }
  router.post('/ops-jobs/items/' + opsJobItem.value.id + '/undo-cash-collected', {
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

  if(nextStatus == 99) {
    const approval = confirm('Are you sure to cancel this job?');
    if (!approval) {
        return;
    }
  }
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

function onUndoStatusClicked(opsJobItemID) {
  const approval = confirm('Are you sure to undo this status?');
  if (!approval) {
      return;
  }
  router.post('/ops-jobs/items/' + opsJobItemID + '/undo-status', {
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

function onUpdateStockAction(type) {
  let typeName = 'normal job';
  if (type == 'implement_new_mapping') typeName = 'Implement New Mapping';
  else if (type == 'return_stock') typeName = 'Return Stock';
  else if (type == null) typeName = 'Reset to Normal Job';

  const approval = confirm(`Are you sure to ${typeName}?`);
  if (!approval) {
      return;
  }
  router.post('/ops-jobs/items/' + opsJobItem.value.id + '/update/stock-action', {
    stock_action_type: type,
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

function onDeleteClicked() {
  const approval = confirm('Are you sure you want to delete this job item? This action cannot be undone.');
  if (!approval) return;

  router.delete('/ops-jobs/items/' + opsJobItem.value.id, {}, {
    onSuccess: () => {
      toast.success("Job item deleted successfully", {
        timeout: 3000
      });
    }
  })
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