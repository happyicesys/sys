
<template>
    <Head title="Vending Machines - Customers" />

	<BreezeAuthenticatedLayout>
		<template #header>
			<h2 class="font-semibold text-xl text-gray-800 leading-tight">
					Vending Machines (Customer View)
			</h2>
		</template>

		<div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
		<div class="-mx-4 sm:-mx-6 lg:-mx-8 bg-white rounded-md border my-3 px-3 md:px-3 py-3 ">
				<div class="grid grid-cols-1 md:grid-cols-5 gap-2">
					<SearchInput placeholderStr="4 to 5 Digits Number" v-model="filters.codes" @keyup.enter="onSearchFilterUpdated()">
						Machine ID
						<span class="text-[9px]">
								("," for multiple)
						</span>
					</SearchInput>
					<SearchInput :class="[showAllFilters ? 'block' : 'hidden']" placeholderStr="Channel ID" v-model="filters.channel_codes" @keyup.enter="onSearchFilterUpdated()">
						Channel ID
						<span class="text-[9px]">
								("," for multiple)
						</span>
					</SearchInput>
					<SearchInput :class="[showAllFilters ? 'block' : 'hidden']"  placeholderStr="Serial Num" v-model="filters.serialNum" @keyup.enter="onSearchFilterUpdated()" v-if="permissions.includes('admin-access vend-customers')">
						Serial Num
					</SearchInput>
					<SearchInput placeholderStr="Number" v-model="filters.tempHigherThan" @keyup.enter="onSearchFilterUpdated()" :class="[showAllFilters ? 'block' : 'hidden']" v-if="permissions.includes('admin-access vend-customers')">
						T1 &gt;&gt;
					</SearchInput>
					<SearchInput placeholderStr="Number" v-model="filters.t2HigherThan" @keyup.enter="onSearchFilterUpdated()" :class="[showAllFilters ? 'block' : 'hidden']" v-if="permissions.includes('admin-access vend-customers')">
						T2 &gt;&gt;
					</SearchInput>
					<SearchInput :class="[showAllFilters ? 'block' : 'hidden']"  placeholderStr="Number" v-model="filters.tempDeltaHigherThan" @keyup.enter="onSearchFilterUpdated()" v-if="permissions.includes('admin-access vend-customers')">
						T1-T2 Delta &gt;&gt;
					</SearchInput>
					<div :class="[showAllFilters ? 'block' : 'hidden']">
						<label for="text" class="block text-sm font-medium text-gray-700">
							Channel Errors
						</label>
						<MultiSelect
								v-model="filters.errors"
								:options="vendChannelErrorsOptions"
								valueProp="id"
								label="desc"
								mode="tags"
								placeholder="Select"
								open-direction="bottom"
								class="mt-1"
						>
						</MultiSelect>
					</div>
					<!-- <SearchInput class="md:block" :class="[showAllFilters ? 'block' : 'hidden']" placeholderStr="Cust ID" v-model="filters.customer_code" v-if="permissions.includes('admin-access vend-customers')" @keyup.enter="onSearchFilterUpdated()">
							Cust ID
					</SearchInput> -->
					<SearchInput placeholderStr="Customer" v-model="filters.customer" v-if="permissions.includes('admin-access vend-customers')" @keyup.enter="onSearchFilterUpdated()">
						Customer
					</SearchInput>
					<div :class="[showAllFilters ? 'block' : 'hidden']">
						<label for="text" class="block text-sm font-medium text-gray-700">
							Is Online?
						</label>
						<MultiSelect
							v-model="filters.is_online"
							:options="booleanOptions"
							trackBy="id"
							valueProp="id"
							label="value"
							placeholder="Select"
							open-direction="bottom"
							class="mt-1"
						>
						</MultiSelect>
					</div>
					<div :class="[showAllFilters ? 'block' : 'hidden']" v-if="permissions.includes('admin-access vend-customers') && indexType === 'customers'">
							<label for="text" class="block text-sm font-medium text-gray-700">
								Customer Active?
							</label>
							<MultiSelect
								v-model="filters.is_active"
								:options="booleanOptions"
								trackBy="id"
								valueProp="id"
								label="value"
								placeholder="Select"
								open-direction="bottom"
								class="mt-1"
							>
							</MultiSelect>
					</div>
					<div :class="[showAllFilters ? 'block' : 'hidden']" v-if="permissions.includes('admin-access vend-customers')">
						<label for="text" class="block text-sm font-medium text-gray-700">
							Machine Status
						</label>
						<MultiSelect
							v-model="filters.status"
							:options="statusOptions"
							trackBy="id"
							valueProp="id"
							label="value"
							placeholder="Select"
							open-direction="bottom"
							class="mt-1"
						>
						</MultiSelect>
					</div>
					<div :class="[showAllFilters ? 'block' : 'hidden']" v-if="permissions.includes('admin-access vend-customers')">
							<label for="text" class="block text-sm font-medium text-gray-700">
								Sensor Status
							</label>
							<MultiSelect
								v-model="filters.is_sensor"
								:options="enableOptions"
								trackBy="id"
								valueProp="id"
								label="value"
								placeholder="Select"
								open-direction="bottom"
								class="mt-1"
							>
							</MultiSelect>
					</div>
					<div :class="[showAllFilters ? 'block' : 'hidden']" v-if="permissions.includes('admin-access vend-customers')">
							<label for="text" class="block text-sm font-medium text-gray-700">
								Is Door Open
							</label>
							<MultiSelect
								v-model="filters.is_door_open"
								:options="doorOptions"
								trackBy="id"
								valueProp="id"
								label="value"
								placeholder="Select"
								open-direction="bottom"
								class="mt-1"
							>
							</MultiSelect>
					</div>
					<SearchInput :class="[showAllFilters ? 'block' : 'hidden']" placeholderStr="Fan Speed" v-model="filters.fanSpeedLowerThan" @keyup.enter="onSearchFilterUpdated()" v-if="permissions.includes('admin-access vend-customers')">
							Fan Speed &lt;&lt;
					</SearchInput>
					<div v-if="permissions.includes('admin-access vend-customers')">
							<label for="text" class="block text-sm font-medium text-gray-700">
								Operator
							</label>
							<MultiSelect
								v-model="filters.operators"
								:options="operatorOptions"
								trackBy="id"
								valueProp="id"
								label="full_name"
								placeholder="Select"
								open-direction="bottom"
								class="mt-1"
								mode="tags"
							>
							</MultiSelect>
					</div>
					<div v-if="indexType === 'customers' && permissions.includes('admin-access vend-customers')">
							<label for="text" class="block text-sm font-medium text-gray-700">
								Location Type
							</label>
							<MultiSelect
								v-model="filters.locationType"
								:options="locationTypeOptions"
								trackBy="id"
								valueProp="id"
								label="value"
								placeholder="Select"
								open-direction="bottom"
								class="mt-1"
							>
							</MultiSelect>
					</div>
					<SearchInput :class="[showAllFilters ? 'block' : 'hidden']" placeholderStr="How many Day(s)" v-model="filters.lastVisitedGreaterThan" @keyup.enter="onSearchFilterUpdated()" v-if="indexType === 'customers' && permissions.includes('admin-access vend-customers')">
						Last Visited Day &gt;&gt;
					</SearchInput>
					<SearchInput :class="[showAllFilters ? 'block' : 'hidden']" placeholderStr="Balance Stock Less Than" v-model="filters.balanceStockLessThan" @keyup.enter="onSearchFilterUpdated()" v-if="permissions.includes('admin-access vend-customers')">
						Balance Stock(%) &lt;&lt;
					</SearchInput>
					<SearchInput :class="[showAllFilters ? 'block' : 'hidden']" placeholderStr="Remaining SKU Less Than" v-model="filters.remainingSkuLessThan" @keyup.enter="onSearchFilterUpdated()" v-if="permissions.includes('admin-access vend-customers')">
						Remaining SKU(%) &lt;&lt;
					</SearchInput>
					<SearchInput :class="[showAllFilters ? 'block' : 'hidden']" placeholderStr="Firmware Ver" v-model="filters.firmware_ver" v-if="permissions.includes('admin-access vend-customers')" @keyup.enter="onSearchFilterUpdated()">
						Firmware Ver
					</SearchInput>
					<SearchInput :class="[showAllFilters ? 'block' : 'hidden']" placeholderStr="APK Ver" v-model="filters.apk_ver" v-if="permissions.includes('admin-access vend-customers')" @keyup.enter="onSearchFilterUpdated()">
						APK Ver
					</SearchInput>
					<div :class="[showAllFilters ? 'block' : 'hidden']" v-if="permissions.includes('admin-access vend-customers')">
						<label for="text" class="block text-sm font-medium text-gray-700">
							Android Device Type
						</label>
						<MultiSelect
							v-model="filters.deviceType"
							:options="deviceTypeOptions"
							trackBy="id"
							valueProp="id"
							label="value"
							placeholder="Select"
							open-direction="bottom"
							class="mt-1"
						>
						</MultiSelect>
					</div>
					<SearchInput :class="[showAllFilters ? 'block' : 'hidden']" placeholderStr="Avg Day Sales Less Than" v-model="filters.vendRecordsThirtyDaysAmountAverageLessThan" v-if="permissions.includes('admin-access vend-customers')" @keyup.enter="onSearchFilterUpdated()">
						Avg/Day Sales (30d) &lt;&lt;
					</SearchInput>
					<div :class="[showAllFilters ? 'block' : 'hidden']" v-if="permissions.includes('admin-access vend-customers')">
						<label for="text" class="block text-sm font-medium text-gray-700">
							Is MQTT?
						</label>
						<MultiSelect
							v-model="filters.is_mqtt"
							:options="booleanOptions"
							trackBy="id"
							valueProp="id"
							label="value"
							placeholder="Select"
							open-direction="bottom"
							class="mt-1"
						>
						</MultiSelect>
					</div>
					<div :class="[showAllFilters ? 'block' : 'hidden']" v-if="permissions.includes('admin-access vend-customers')">
						<label for="text" class="block text-sm font-medium text-gray-700">
							Is MQTT Active?
						</label>
						<MultiSelect
							v-model="filters.is_mqtt_active"
							:options="booleanOptions"
							trackBy="id"
							valueProp="id"
							label="value"
							placeholder="Select"
							open-direction="bottom"
							class="mt-1"
						>
						</MultiSelect>
					</div>
					<SearchInput :class="[showAllFilters ? 'block' : 'hidden']" placeholderStr="Account Manager" v-model="filters.account_manager_name" v-if="permissions.includes('admin-access vend-customers') && indexType === 'customers'" @keyup.enter="onSearchFilterUpdated()">
						Account Manager
					</SearchInput>
					<SearchInput :class="[showAllFilters ? 'block' : 'hidden']"  placeholderStr="Number" v-model="filters.coinLessThan" @keyup.enter="onSearchFilterUpdated()" v-if="permissions.includes('admin-access vend-customers')">
						Coin Amount &lt;&lt;
					</SearchInput>
					<div :class="[showAllFilters ? 'block' : 'hidden']" v-if="indexType === 'customers' && permissions.includes('admin-access vend-customers')">
						<label for="text" class="block text-sm font-medium text-gray-700">
							Next Planned Driver
						</label>
						<MultiSelect
							v-model="filters.next_planned_driver"
							:options="nextDeliveryDriverOptions"
							trackBy="id"
							valueProp="id"
							label="value"
							placeholder="Select"
							open-direction="bottom"
							class="mt-1"
						>
						</MultiSelect>
					</div>
					<div :class="[showAllFilters ? 'block' : 'hidden']" v-if="indexType === 'customers' && permissions.includes('admin-access vend-customers')">
						<DatePicker
							v-model="filters.next_planned_date"
						>
							Next Planned Date
						</DatePicker>
					</div>
					<div>
						<label for="text" class="block text-sm font-medium text-gray-700">
							Machine Prefix
						</label>
						<MultiSelect
							v-model="filters.vendPrefixes"
							:options="vendPrefixOptions"
							trackBy="id"
							valueProp="id"
							label="value"
							placeholder="Select"
							open-direction="bottom"
							mode="tags"
							class="mt-1"
						>
						</MultiSelect>
					</div>
					<div :class="[showAllFilters ? 'block' : 'hidden']" v-if="permissions.includes('admin-access vend-customers')">
						<label for="text" class="block text-sm font-medium text-gray-700">
							Ref Price Type
						</label>
						<MultiSelect
							v-model="filters.selling_price_type"
							:options="sellingPriceTypeOptions"
							trackBy="id"
							valueProp="id"
							label="value"
							placeholder="Select"
							open-direction="bottom"
							class="mt-1"
						>
						</MultiSelect>
					</div>
					<div :class="[showAllFilters ? 'block' : 'hidden']" v-if="permissions.includes('admin-access vend-customers')">
            <label for="text" class="block text-sm font-medium text-gray-700">
                Machine Model
            </label>
            <MultiSelect
                v-model="filters.vendModels"
                :options="vendModelOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
                mode="tags"
            >
            </MultiSelect>
          </div>
					<div :class="[showAllFilters ? 'block' : 'hidden']" v-if="permissions.includes('admin-access vend-customers')">
						<label for="text" class="block text-sm font-medium text-gray-700">
							Zone
						</label>
						<MultiSelect
							v-model="filters.zones"
							:options="zoneOptions"
							trackBy="id"
							valueProp="id"
							label="value"
							placeholder="Select"
							open-direction="bottom"
							class="mt-1"
							mode="tags"
						>
						</MultiSelect>
					</div>
					<div :class="[showAllFilters ? 'block' : 'hidden']" v-if="permissions.includes('admin-access vend-customers')">
						<label for="text" class="block text-sm font-medium text-gray-700">
							Preferred Day(s)
						</label>
						<MultiSelect
							v-model="filters.preferredDays"
							:options="dayOptions"
							trackBy="id"
							valueProp="id"
							label="value"
							placeholder="Select"
							open-direction="bottom"
							class="mt-1"
							mode="tags"
						>
						</MultiSelect>
					</div>
				</div>

				<div class="flex flex-col space-y-3 md:flex-row md:space-y-0 justify-between mt-5">
					<div class="mt-3">
						<div class="flex flex-col space-y-1 md:flex-row md:space-y-0 md:space-x-1">
							<Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
							@click="onSearchFilterUpdated()"
							>
								<MagnifyingGlassIcon class="h-4 w-4" aria-hidden="true"/>
								<span>
										Search
								</span>
							</Button>
							<Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
							@click="onShowAllFiltersClicked()"
							>
								<span v-if="!showAllFilters" class="flex">
										<ChevronDoubleDownIcon class="h-4 w-4" aria-hidden="true"/>
										Show
								</span>
								<span v-if="showAllFilters" class="flex">
										<ChevronDoubleUpIcon class="h-4 w-4" aria-hidden="true"/>
										Hide
								</span>
								<span>
										All Filters
								</span>
							</Button>
							<Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-gray-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
							@click="resetFilters()"
							>
								<BackspaceIcon class="h-4 w-4" aria-hidden="true"/>
								<span>
										Reset
								</span>
							</Button>
							<Button type="button" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-400 hover:bg-gray-100"
							@click="onIsShowOperationDivButtonClicked()"
							v-if="permissions.includes('export vend-customers') && permissions.includes('admin-access vend-customers')">
								<div class="flex space-x-1">
										<div>
												<ChevronDoubleDownIcon class="h-4 w-4" aria-hidden="true" v-if="!isShowOperationDiv"/>
												<ChevronDoubleUpIcon class="h-4 w-4" aria-hidden="true" v-if="isShowOperationDiv"/>
										</div>
										<span>
												Operations
										</span>
								</div>
							</Button>
						</div>
					</div>
					<div class="flex flex-col space-y-1">
						<span class="text-sm text-gray-700 leading-5">
							<p>Last loaded: {{ now }}</p>
						</span>
						<p class="text-sm text-gray-700 leading-5 flex space-x-1">
							<span>Showing</span>
							<span class="font-medium">{{ vends.meta.from ?? 0 }}</span>
							<span>to</span>
							<span class="font-medium">{{ vends.meta.to ?? 0 }}</span>
							<span>of</span>
							<span class="font-medium">{{ vends.meta.total }}</span>
							<span>results</span>
						</p>
						<MultiSelect
							v-model="filters.numberPerPage"
							:options="numberPerPageOptions"
							trackBy="id"
							valueProp="id"
							label="value"
							placeholder="Select"
							open-direction="bottom"
							class="mt-1"
							@selected="onSearchFilterUpdated"
						>
						</MultiSelect>
					</div>
				</div>
				<div v-if="isShowOperationDiv">
					<!-- <hr class="mt-2"> -->
					<div class="flex flex-col space-y-3 md:flex-row md:space-y-0 space-x-1 mt-5">
						<Button type="button" class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-400 hover:bg-gray-100"
						@click="onExportChannelExcelClicked()"
						v-if="permissions.includes('export vend-customers')">
							<div class="flex space-x-1">
								<div>
										<ArrowDownTrayIcon v-if="!loading" class="h-4 w-4" aria-hidden="true"/>
										<svg v-if="loading" aria-hidden="true" class="mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
												<path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
												<path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
										</svg>
								</div>
								<span>
										Export Channels Excel
								</span>
							</div>
						</Button>
						<!-- <Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-blue-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-blue-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
						@click="onSyncNextDeliveryDate()"
						v-if="permissions.includes('admin-access vend-customers')"
						>
							<div class="flex space-x-1">
								<div>
									<ArrowPathIcon  v-if="!loadingSyncNextDeliveryDate" class="h-4 w-4" aria-hidden="true"/>
									<svg v-if="loadingSyncNextDeliveryDate" aria-hidden="true" class="mr-2 w-4 h-4 text-gray-200 animate-spin dark:text-gray-400 fill-red-600" viewBox="0 0 100 101" fill="none" xmlns="http://www.w3.org/2000/svg">
											<path d="M100 50.5908C100 78.2051 77.6142 100.591 50 100.591C22.3858 100.591 0 78.2051 0 50.5908C0 22.9766 22.3858 0.59082 50 0.59082C77.6142 0.59082 100 22.9766 100 50.5908ZM9.08144 50.5908C9.08144 73.1895 27.4013 91.5094 50 91.5094C72.5987 91.5094 90.9186 73.1895 90.9186 50.5908C90.9186 27.9921 72.5987 9.67226 50 9.67226C27.4013 9.67226 9.08144 27.9921 9.08144 50.5908Z" fill="currentColor"/>
											<path d="M93.9676 39.0409C96.393 38.4038 97.8624 35.9116 97.0079 33.5539C95.2932 28.8227 92.871 24.3692 89.8167 20.348C85.8452 15.1192 80.8826 10.7238 75.2124 7.41289C69.5422 4.10194 63.2754 1.94025 56.7698 1.05124C51.7666 0.367541 46.6976 0.446843 41.7345 1.27873C39.2613 1.69328 37.813 4.19778 38.4501 6.62326C39.0873 9.04874 41.5694 10.4717 44.0505 10.1071C47.8511 9.54855 51.7191 9.52689 55.5402 10.0491C60.8642 10.7766 65.9928 12.5457 70.6331 15.2552C75.2735 17.9648 79.3347 21.5619 82.5849 25.841C84.9175 28.9121 86.7997 32.2913 88.1811 35.8758C89.083 38.2158 91.5421 39.6781 93.9676 39.0409Z" fill="currentFill"/>
									</svg>
								</div>
								<span>
										Sync Next Delivery Date
								</span>
							</div>
						</Button> -->
						<Button class="inline-flex space-x-1 items-center rounded-md border border-green bg-green-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
						:class="{ 'opacity-50 cursor-not-allowed': vends.data.filter(vend => vend.is_selected).length === 0 }"
						@click="onGeneratePickListClicked()"
						:disabled="vends.data.filter(vend => vend.is_selected).length === 0"
						>
							<ClipboardDocumentCheckIcon class="h-4 w-4" aria-hidden="true"/>
							<span class="flex flex-col space-y-1">
								<span>
										Generate Pick List
								</span>
								<span class="text-xs leading-3">
									(Checkbox)
								</span>
							</span>
						</Button>
						<Button class="inline-flex space-x-1 items-center rounded-md border border-sky bg-sky-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-sky-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
						@click="onProductAvailableModalClicked()"
						>
							<PlayCircleIcon class="h-4 w-4" aria-hidden="true"/>
							<span class="flex flex-col space-y-1">
								<span>
										Set Product Availability
								</span>
							</span>
						</Button>

						<!-- if there is any checkbox selected (vend.is_selected) -->
						<Button class="inline-flex space-x-1 items-center rounded-md border border-sky bg-sky-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-black shadow-sm hover:bg-sky-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
						:class="{ 'opacity-50 cursor-not-allowed': vends.data.filter(vend => vend.is_selected).length === 0 }"
						@click="onAssignJobClicked()"
            :disabled="vends.data.filter(vend => vend.is_selected).length === 0"
						>
							<ClipboardDocumentCheckIcon class="h-4 w-4" aria-hidden="true"/>
							<span class="flex flex-col space-y-1">
								<span>
										Assign Job(s)
								</span>
								<span class="text-xs leading-3">
										(Checkbox)
								</span>
							</span>
						</Button>
						<Button class="inline-flex space-x-1 items-center rounded-md border border-yellow bg-yellow-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-black shadow-sm hover:bg-yellow-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
						@click="onSyncCMSInvoiceItemsClicked()"
						>
							<ClipboardDocumentCheckIcon class="h-4 w-4" aria-hidden="true"/>
							<span class="flex flex-col space-y-1">
								<span>
										Sync CMS Invoice Items
								</span>
								<span class="text-xs leading-3">
										(Filter)
								</span>
							</span>
						</Button>
					</div>
				</div>
				<dl class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-3">
					<div class="overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow md:block" :class="[showAllFilters ? 'block' : 'hidden']">
						<dt class="truncate text-sm font-medium text-gray-500">Total Sales (Last 30 days)</dt>
						<dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
							{{totals['thirtyDays'].toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
						</dd>
					</div>
					<div class="overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow md:block" :class="[showAllFilters ? 'block' : 'hidden']">
						<dt class="truncate text-sm font-medium text-gray-500">Avg per VM (Last 30 days)</dt>
						<dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
							{{(totals['thirtyDays']/vends.meta.to ? totals['thirtyDays']/vends.meta.to : 0).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
						</dd>
					</div>
					<div class="overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow md:block" :class="[showAllFilters ? 'block' : 'hidden']">
						<dt class="truncate text-sm font-medium text-gray-500">Avg per Day per VM (Last 30 days)</dt>
						<dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
							{{(totals['thirthyDaysAvg']/vends.meta.to ? totals['thirthyDaysAvg']/vends.meta.to : 0).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
						</dd>
					</div>
				</dl>
		</div>

		<div class="mt-6 flex flex-col">
		<div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
		<div class="overflow-scroll max-h-[600px] md:max-h-[700px] shadow-sm ring-1 ring-black ring-opacity-5">
			<table class="min-w-full border-separate" style="border-spacing: 0">
				<thead class="bg-gray-100">
					<tr class="divide-x divide-gray-200">
						<TableHead v-if="isShowOperationDiv">
							<input
									id="isSelectedAll"
									type="checkbox"
									v-model="isSelectedAll"
									@change="toggleSelectAll"
									class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600"
							>
						</TableHead>
						<TableHead>
							#
						</TableHead>
						<TableHeadSort modelName="vends.code" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vends.code')" v-if="indexType !== 'customers'">
							Machine ID
						</TableHeadSort>
						<TableHead>
							<div class="flex flex-col space-y-2">
								<SingleSortItem modelName="vends.code" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vends.code')">
									Machine ID
								</SingleSortItem>
								<SingleSortItem modelName="vends.vend_prefix_name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vends.vend_prefix_name', false)">
									Machine Prefix
								</SingleSortItem>
								<SingleSortItem modelName="customers.virtual_customer_code" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('customers.virtual_customer_code')">
									Customer
								</SingleSortItem>
								<SingleSortItem modelName="customers.selling_price_type" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('customers.selling_price_type', false)">
									Ref Price
								</SingleSortItem>
							</div>
						</TableHead>
						<TableHead>
							<div class="flex flex-col space-y-2">
								<SingleSortItem modelName="temp" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('temp', true)">
									T1: Machine Temp
								</SingleSortItem>
								<SingleSortItem modelName="parameter_json->t2" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('parameter_json->t2', true)">
									T2: Evaporator Temp
								</SingleSortItem>
								<SingleSortItem modelName="temp_updated_at" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('temp_updated_at', true)">
									Updated
								</SingleSortItem>
								<span>
									&Delta;T1-T2
								</span>
							</div>
						</TableHead>
						<TableHead>
							<div class="flex flex-col space-y-2">
								<span class="flex flex-col space-y-1">
									<span>
										Inventory Status
									</span>
									<span>
										#Channel, Needed, Balance/Capacity (LastStockIn)
									</span>
								</span>
								<SingleSortItem modelName="total_stock_cost" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('total_stock_cost')">
									Stock Cost
								</SingleSortItem>
								<SingleSortItem modelName="total_stock_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('total_stock_amount')">
									Stock Value
								</SingleSortItem>
							</div>
						</TableHead>
						<TableHead>
							<div class="flex flex-col space-y-2">
								<span>
									Error
								</span>
								<SingleSortItem modelName="vend_channel_error_logs_json" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_channel_error_logs_json')">
									Uncleared Error(s)
								</SingleSortItem>
								<SingleSortItem modelName="totals_json->three_days_error_rate" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('totals_json->three_days_error_rate', false)">
									3d Rate
								</SingleSortItem>
								<SingleSortItem modelName="totals_json->seven_days_error_rate" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('totals_json->seven_days_error_rate', false)">
									7d Rate
								</SingleSortItem>
							</div>
						</TableHead>
						<TableHead v-if="!roles.includes('operator_3pl')">
							<div class="flex flex-col space-y-2">
								<span>
									Stock
								</span>
								<SingleSortItem modelName="balance_percent" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('balance_percent', true)">
									Balance Qty
								</SingleSortItem>
								<SingleSortItem modelName="out_of_stock_sku_percent" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('out_of_stock_sku_percent', false)">
									Remaining SKU#
								</SingleSortItem>
								<SingleSortItem modelName="actual_stock_in_value" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('actual_stock_in_value')">
									Refillable Value
								</SingleSortItem>
								<SingleSortItem modelName="actual_stock_in_qty" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('actual_stock_in_qty')">
									Refillable Qty
								</SingleSortItem>
							</div>
						</TableHead>
						<TableHead v-if="!roles.includes('operator_3pl')">
							Sales(qty)
							<SingleSortItem modelName="totals_json->today_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('totals_json->today_amount', false)">
								Today
							</SingleSortItem>
							<SingleSortItem modelName="totals_json->yesterday_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('totals_json->yesterday_amount', false)">
								Y'day
							</SingleSortItem>
							<SingleSortItem modelName="totals_json->seven_days_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('totals_json->seven_days_amount', false)">
								Last7d
							</SingleSortItem>
							<SingleSortItem modelName="totals_json->thirty_days_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('totals_json->thirty_days_amount', false)">
								Last30d
							</SingleSortItem>
						</TableHead>
						<TableHead v-if="!roles.includes('operator_3pl')">
							<div class="flex flex-col space-y-2">
								<span>
									CMS Last Stock In
								</span>
								<SingleSortItem modelName="last_ops_job_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('last_ops_job_amount')">
									Value
								</SingleSortItem>
								<SingleSortItem modelName="last_ops_job_count" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('last_ops_job_count')">
									Qty
								</SingleSortItem>
							</div>
						</TableHead>
						<TableHead v-if="!roles.includes('operator_3pl')">
							<div class="flex flex-col space-y-2">
								<span>
									CMS Next Picked
								</span>
								<SingleSortItem modelName="next_ops_job_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('next_ops_job_amount')">
									Value
								</SingleSortItem>
								<SingleSortItem modelName="next_ops_job_count" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('next_ops_job_count')">
									Qty
								</SingleSortItem>
							</div>
						</TableHead>
						<TableHead v-if="!roles.includes('operator_3pl')">
							<div class="flex flex-col space-y-2">
								<span>
									SYS Last Job
								</span>
							</div>
						</TableHead>
						<TableHead v-if="!roles.includes('operator_3pl')">
							<div class="flex flex-col space-y-2">
								<span>
									SYS Next Job
								</span>
							</div>
						</TableHead>
						<TableHead v-if="!roles.includes('operator_3pl')">
							<div class="flex flex-col space-y-2">
								<SingleSortItem modelName="vend_transaction_totals_json->vend_records_amount_latest" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('totals_json->vend_records_amount_latest', true)">
									Lifetime Sales
								</SingleSortItem>
								<SingleSortItem modelName="begin_date" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('begin_date', false)">
									Begin Dt
								</SingleSortItem>
								<SingleSortItem modelName="vend_transaction_totals_json->vend_records_amount_average_day" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_transaction_totals_json->vend_records_amount_average_day', true)">
									Avg Sales/ Day
								</SingleSortItem>
								<SingleSortItem modelName="virtual_vend_records_thirty_days_amount_average" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('virtual_vend_records_thirty_days_amount_average', true)">
									Avg Sales (Last30d)
								</SingleSortItem>
							</div>
						</TableHead>
						<TableHead v-if="!roles.includes('operator_3pl')">
							Machine Status
						</TableHead>
						<TableHead v-if="!roles.includes('operator_3pl')">
							Payment Device
						</TableHead>
						<TableHead v-if="!roles.includes('operator_3pl')">
							<div class="flex flex-col space-y-2">
								<SingleSortItem modelName="operator_code" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('operator_code')">
									Operator
								</SingleSortItem>
								<SingleSortItem modelName="account_manager_name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('account_manager_name')">
									Acc Manager
								</SingleSortItem>
								<SingleSortItem modelName="postcode" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('postcode')">
									Postcode
								</SingleSortItem>
								<SingleSortItem modelName="location_type_name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('location_type_name')">
									Location
								</SingleSortItem>
							</div>
						</TableHead>
						<TableHead v-if="indexType === 'customers' && !roles.includes('operator_3pl')">
							<div class="flex flex-col space-y-2">
								<SingleSortItem modelName="zone_name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('zone_name')">
									Zone
								</SingleSortItem>
								<div>
									Preferred Day(s)
								</div>
							</div>
						</TableHead>
						<TableHead v-if="!roles.includes('operator_3pl')">
							<div class="flex flex-col space-y-2">
								<span>
									VMC Board
								</span>
								<span>
									Firmware Rev
								</span>
								<span>
									Android Board
								</span>
								<span>
									APK Ver
								</span>
								<span>
									ACB Rev
								</span>
							</div>
						</TableHead>
					</tr>
				</thead>
				<tbody class="bg-white">
					<tr v-for="(vend, vendIndex) in vends.data" :key="vendIndex"
						class="divide-x divide-gray-200">
						<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center" v-if="isShowOperationDiv">
							<input type="checkbox" v-model="vend.is_selected" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
						</TableData>
						<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
							{{ vends.meta.from + vendIndex }}
						</TableData>
						<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center" v-if="indexType !== 'customers'">
							<Link :href="'/settings/vend/' + vend.vend_id + '/update'" :class="[vend.is_active || vend.is_testing ? 'text-blue-600' : 'text-gray-400']">
							{{ vend.code }}
							</Link>
						</TableData>
						<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-left">
							<div class="flex flex-col space-y-1">
								<Link :href="'/settings/vend/' + vend.vend_id + '/update'" :class="[vend.is_active || vend.is_testing ? 'text-blue-600' : 'text-gray-400']" class="text-left" v-if="permissions.includes('admin-access vend-customers')">
									{{ vend.code }}
								</Link>
								<span v-if="!permissions.includes('admin-access vend-customers')">
									{{ vend.code }}
								</span>
								<div class="text-left">
									{{ vend.vend_prefix_name }}
								</div>
								<span v-if="vend.person_id" class="flex flex-col">
									<span v-if="permissions.includes('admin-access vend-customers')">
										<a :class="[vend.person_id && vend.customer_is_active || vend.is_testing ? 'text-blue-700' : 'text-gray-400']" target="_blank" :href="'/customers/' + vend.customer_id + '/edit'">
												{{ vend.virtual_customer_code }}
												<br>
												{{ vend.customer_name }}
										</a>
									</span>
									<span v-else :class="[vend.customer_is_active || vend.is_testing ? 'text-gray-800' : 'text-gray-400']">
										{{ vend.customer_name }}
									</span>

									<a target="_blank" :href="cmsEndpoint + '/person/' + vend.person_id + '/edit'" class="" v-if="permissions.includes('admin-access vend-customers')">
										<div
												class="inline-flex justify-center items-center rounded px-2 py-1 text-[10px] font-small border bg-blue-200 text-gray-800"
										>
												CMS
										</div>
									</a>
								</span>
								<span v-else-if="!vend.person_id">
									<span v-if="permissions.includes('admin-access vend-customers')" :class="[vend.customer_is_active || vend.is_testing ? 'text-gray-800' : 'text-gray-400']">
										<a class="text-blue-700" target="_blank" :href="'/customers/' + vend.customer_id + '/edit'">
												{{ vend.customer_name }}
										</a>
									</span>
									<span v-else :class="[vend.customer_is_active || vend.is_testing ? 'text-gray-800' : 'text-gray-400']">
										<a class="text-blue-700" target="_blank" :href="'/customers/' + vend.customer_id + '/edit'">
										{{ vend.customer_name }}
										</a>
									</span>
								</span>
								<div
									class="inline-flex rounded px-0.5 py-0.5 text-xs border w-fit bg-indigo-100 text-indigo-800 border-indigo-300"
								>
									RP{{ vend.selling_price_type }}
								</div>
							</div>
						</TableData>
						<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
							<div class="flex flex-col items-center space-y-1">
								<a :href="'/vends/' + vend.vend_id + '/temp/' + 1 " target="_blank" class="w-full">
										<button
										type="button"
										class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center"
										:class="[(vend.is_online || vend.is_testing) && vend.is_temp_active ? (vend.temp > -15 ? 'bg-red-400 active:bg-red-500 hover:bg-red-600' : 'bg-green-400 active:bg-green-500 hover:bg-green-600') : 'bg-gray-300 active:bg-gray-500 hover:bg-gray-600']"
										v-if="vend.temp_updated_at"
										>
												{{ vend.is_temp_error ? 'Error' : vend.temp }}
										</button>
								</a>
								<!-- <button
										type="button"
										class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 w-4/5 text-right justify-center"
										:class="[vend.is_online && vend.is_active ? (vend.temp > -15 ? 'bg-red-400 active:bg-red-500 hover:bg-red-600 text-black' : 'bg-green-400 active:bg-green-500 hover:bg-green-600 text-black') : 'bg-gray-300 active:bg-gray-500 hover:bg-gray-600 text-gray-700']"
										@click="onVendTempClicked(vend.id, 1)"
										v-if="vend.temp_updated_at"
								>
										{{ vend.is_temp_error ? 'Error' : vend.temp }}
								</button> -->
								<a :href="'/vends/' + vend.vend_id + '/temp/' + 2 " target="_blank" class="w-full">
										<button
												type="button"
												class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center"
												:class="[(vend.is_online || vend.is_testing) && vend.is_temp_active ? (vend.temp > -15 || vend.parameterJson['t2'] == constTempError ? 'bg-red-400 active:bg-red-500 hover:bg-red-600' : 'bg-green-400 active:bg-green-500 hover:bg-green-600') : 'bg-gray-300 active:bg-gray-500 hover:bg-gray-600']"
												v-if="vend.parameterJson && 't2' in vend.parameterJson"
										>
										<!-- @click="onVendTempClicked(vend.id, 2)" -->
												{{ vend.parameterJson['t2'] == constTempError ? 'Error' : vend.parameterJson['t2']/10 }}(t2)
										</button>
								</a>
								<a :href="'/vends/' + vend.vend_id + '/temp/' + 3 " target="_blank" class="w-full">
										<button
												type="button"
												class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center"
												:class="[(vend.is_online || vend.is_testing) && vend.is_temp_active ? (vend.temp > -15 || vend.parameterJson['t3'] == constTempError ? 'bg-red-400 active:bg-red-500 hover:bg-red-600' : 'bg-green-400 active:bg-green-500 hover:bg-green-600') : 'bg-gray-300 active:bg-gray-500 hover:bg-gray-600']"
												v-if="vend.parameterJson && vend.parameterJson['t3'] && vend.parameterJson['t3'] != constTempError"
										>
												{{ vend.parameterJson['t3'] == constTempError ? 'Error' : vend.parameterJson['t3']/10 }}(t3)
										</button>
								</a>
								<a :href="'/vends/' + vend.vend_id + '/temp/' + 4 " target="_blank" class="w-full">
										<button
												type="button"
												class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-4/5 text-right justify-center"
												:class="[(vend.is_online || vend.is_testing) && vend.is_temp_active ? (vend.temp > -15 || vend.parameterJson['t4'] == constTempError ? 'bg-red-400 active:bg-red-500 hover:bg-red-600' : 'bg-green-400 active:bg-green-500 hover:bg-green-600') : 'bg-gray-300 active:bg-gray-500 hover:bg-gray-600']"
												v-if="vend.parameterJson && vend.parameterJson['t4'] && vend.parameterJson['t4'] != constTempError"
										>
												{{ vend.parameterJson['t4'] == constTempError ? 'Error' : vend.parameterJson['t4']/10 }}(t4)
										</button>
								</a>
								<span class="mt-1">
										{{ vend.temp_updated_at }}
								</span>
								<span
										class="mt-1"
										:class="[vend.is_active || vend.is_testing ? ((vend.temp - vend.parameterJson['t2']/10).toFixed(1) >= 4 ? 'text-red-700' : 'text-green-700') : 'text-gray-400' ]"
										v-if="vend.parameterJson && vend.parameterJson['t2'] && vend.parameterJson['t2'] != constTempError && !vend.is_temp_error"
								>
										{{ (vend.temp - vend.parameterJson['t2']/10).toFixed(1) }}
								</span>
							</div>
						</TableData>
						<!-- class="sm:grid sm:grid-cols-[105px_minmax(110px,_1fr)_100px] hover:cursor-pointer" -->
						<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-left">
							<div class="flex flex-col space-y-2">
								<ul
								class="sm:grid sm:grid-cols-[1fr_1fr] hover:cursor-pointer"
								v-if="vend.vendChannels"
								@click="onChannelOverviewClicked(vend)"
								>
									<li v-for="(channel, channelIndex) in vend.vendChannels"
											class="quick-look"
											:class="[
												channelIndex > 0 && (String(channel['code'])[0] !== String(vend.vendChannels[channelIndex - 1]['code'])[0]) ? 'col-start-1' : '',
												channel.product && !channel.product.is_available ? 'bg-gray-300' : ''
											]"
									>
										<span :class="[channelIndex > 0 && (String(channel['code'])[0] !== String(vend.vendChannels[channelIndex - 1]['code'])[0]) ? 'border-t-4 pt-1' : '']">
												<span :class="[vend.is_active || vend.is_testing ? compareRefPrice(vend, channel) : 'text-gray-600']">
														#{{channel['code']}}
												</span>,
												<span :class="[vend.is_active || vend.is_testing ? 'text-blue-600' : 'text-gray-500']">
														{{channel['capacity'] - channel['qty']}},
												</span>
												<span :class="[vend.is_active || vend.is_testing ? (channel['qty'] <= 2 ? 'text-red-700' : 'text-green-700') : 'text-gray-400']">
														{{channel['qty']}}/{{channel['capacity']}}
												</span>
												<span class="text-gray-500" v-if="channel.latestOpsJobItemChannel">
														({{channel.latestOpsJobItemChannel.actual_qty}})
												</span>
										</span>
									</li>
								</ul>
								<div class="flex flex-col space-y-1 pl-2 text-center">
									<span>
										<div
											class="text-gray-800"
										>
											Cost: {{ operatorCountry.currency_symbol }}{{ vend.total_stock_cost ? vend.total_stock_cost.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : 0 }}
										</div>
									</span>
									<span>
										<div
											class="text-gray-800"
										>
											Value: {{ operatorCountry.currency_symbol }}{{ vend.total_stock_amount ? vend.total_stock_amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : 0 }}
										</div>
									</span>
								</div>
							</div>
						</TableData>
						<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
							<div class="flex flex-col space-y-2">
								<span v-for="vendChannelErrorLog in vend.vendChannelErrorLogsJson" class="inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border"
								:class="[
									vendChannelErrorLog['vendChannelError'] ?
									(
											vendChannelErrorLog['vendChannelError']['code'] == 4 ||
											vendChannelErrorLog['vendChannelError']['code'] == 5 ||
											vendChannelErrorLog['vendChannelError']['code'] == 7 ?
											'bg-blue-100 text-blue-800' :
											'bg-red-100 text-red-800'
									) :
									(
											vendChannelErrorLog['vend_channel']['code'] == 4 ||
											vendChannelErrorLog['vend_channel']['code'] == 5 ||
											vendChannelErrorLog['vend_channel']['code'] == 7 ?
											'bg-blue-100 text-blue-800' :
											'bg-red-100 text-red-800'
									)]">
									<div class="flex flex-col">
											<div>
													#{{vendChannelErrorLog['vendChannel'] ? vendChannelErrorLog['vendChannel']['code'] : vendChannelErrorLog['vend_channel']['code']}},
													<span class="font-bold">
													({{ vendChannelErrorLog['vendChannelError'] ? vendChannelErrorLog['vendChannelError']['code'] : vendChannelErrorLog['vend_channel_error']['code'] }})
													</span>
											</div>
											<div>
													{{vendChannelErrorLog['created_at']}}
											</div>
									</div>
								</span>
								<span
								v-if="vend.vendTransactionTotalsJson && 'three_days_error_rate' in vend.vendTransactionTotalsJson"
								:class="[
										vend.is_active || vend.is_testing ?
										(vend.vendTransactionTotalsJson['three_days_error_rate'] >= 3 ? 'text-red-700' : 'text-green-700') :
										'text-gray-400'
								]">
										{{vend.vendTransactionTotalsJson['three_days_error_rate'].toLocaleString(undefined, {minimumFractionDigits: 1, maximumFractionDigits: 1})}}%
										({{vend.vendTransactionTotalsJson['three_days_error_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}}/{{vend.vendTransactionTotalsJson['three_days_all_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}})
								</span>
								<span
								v-if="vend.vendTransactionTotalsJson && 'seven_days_error_rate' in vend.vendTransactionTotalsJson"
								:class="[
										vend.is_active || vend.is_testing ?
										(vend.vendTransactionTotalsJson['seven_days_error_rate'] >= 3 ? 'text-red-700' : 'text-green-700') :
										'text-gray-400'
								]">
										{{vend.vendTransactionTotalsJson['seven_days_error_rate'].toLocaleString(undefined, {minimumFractionDigits: 1, maximumFractionDigits: 1})}}%
										({{vend.vendTransactionTotalsJson['seven_days_error_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}}/{{vend.vendTransactionTotalsJson['seven_days_all_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}})
								</span>
							</div>
						</TableData>
						<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center" v-if="!roles.includes('operator_3pl')">
							<div class="flex flex-col space-y-2">
								<span
										v-if="vend.vendChannelTotalsJson"
										:class="[vend.is_active || vend.is_testing ? (vend.balance_percent <= 20 ? 'text-red-700' : (vend.balance_percent > 50 ? 'text-green-700' : 'text-blue-700')) : 'text-gray-400']"
								>
										{{ vend.vendChannelTotalsJson['qty'] }}/ {{ vend.vendChannelTotalsJson['capacity'] }} <br>
										({{ vend.balance_percent }}%)
								</span>

								<span
									v-if="vend.vendChannelTotalsJson"
									:class="[vend.is_active || vend.is_testing ? (100 - vend.out_of_stock_sku_percent <= 40 ? 'text-red-700' : (100 - vend.out_of_stock_sku_percent > 70 ? 'text-green-700' : 'text-blue-700')) : 'text-gray-400']"
								>
										{{ vend.vendChannelTotalsJson['count'] - vend.vendChannelTotalsJson['outOfStockSku'] }}/ {{ vend.vendChannelTotalsJson['count'] }} <br>
										({{ 100 - vend.out_of_stock_sku_percent }}%)
								</span>
								<span :class="[vend.actual_stock_in_value < 100 ? 'text-red-500' : 'text-gray-800']" v-if="vend.actual_stock_in_value">
									{{ operatorCountry.currency_symbol }}{{(vend.actual_stock_in_value ? vend.actual_stock_in_value : 0).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
								</span>
								<span :class="[vend.actual_stock_in_value < 100 ? 'text-red-500' : 'text-gray-800']" v-if="vend.actual_stock_in_value">
									{{vend.actual_stock_in_qty ? vend.actual_stock_in_qty.toLocaleString(undefined, {minimumFractionDigits: 0}) : 0}}
								</span>
							</div>
						</TableData>
						<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center" v-if="!roles.includes('operator_3pl')">
							<span
							v-if="vend.vendTransactionTotalsJson && 'today_amount' in vend.vendTransactionTotalsJson"
							:class="[
									vend.is_active || vend.is_testing ?
									((vend.vendTransactionTotalsJson['today_amount']/ (Math.pow(10, operatorCountry.currency_exponent))) >= 30 ? 'text-green-700' : 'text-red-700') :
									'text-gray-400'
							]">
									{{ operatorCountry.currency_symbol }}{{(vend.vendTransactionTotalsJson['today_amount'] / (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
									({{vend.vendTransactionTotalsJson['today_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}})
							</span>
							<span
							v-if="vend.vendTransactionTotalsJson && 'yesterday_amount' in vend.vendTransactionTotalsJson"
							:class="[
									vend.is_active || vend.is_testing ?
									((vend.vendTransactionTotalsJson['yesterday_amount']/ (Math.pow(10, operatorCountry.currency_exponent))) >= 30 ? 'text-green-700' : 'text-red-700') :
									'text-gray-400'
							]">
									<br>
									{{ operatorCountry.currency_symbol }}{{(vend.vendTransactionTotalsJson['yesterday_amount']/ (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
									({{vend.vendTransactionTotalsJson['yesterday_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}})
							</span>
							<span
							v-if="vend.vendTransactionTotalsJson && 'seven_days_amount' in vend.vendTransactionTotalsJson"
							:class="[
									vend.is_active || vend.is_testing ?
									((vend.vendTransactionTotalsJson['seven_days_amount']/ (Math.pow(10, operatorCountry.currency_exponent))) > 200 ? 'text-green-700' : 'text-red-700') :
									'text-gray-400'
							]">
									<br>
									{{ operatorCountry.currency_symbol }}{{(vend.vendTransactionTotalsJson['seven_days_amount']/ (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}({{vend.vendTransactionTotalsJson['seven_days_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}})
							</span>
							<span
							v-if="vend.vendTransactionTotalsJson && 'thirty_days_amount' in vend.vendTransactionTotalsJson"
							:class="[
									vend.is_active || vend.is_testing ?
									((vend.vendTransactionTotalsJson['thirty_days_amount']/ (Math.pow(10, operatorCountry.currency_exponent))) > 1000 ? 'text-green-700' : 'text-red-700') :
									'text-gray-400'
							]">
									<br>
									{{ operatorCountry.currency_symbol }}{{(vend.vendTransactionTotalsJson['thirty_days_amount']/ (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}({{vend.vendTransactionTotalsJson['thirty_days_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}})
							</span>
						</TableData>
						<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center" v-if="indexType === 'customers' && !roles.includes('operator_3pl')">
							<!-- <div v-if="vend.vend && !vend.vend.lastOpsJobItem"> -->
								<span v-if="vend.cms_invoice_history && 'last_delivery_driver' in vend.cms_invoice_history" :class="[vend.is_active || vend.is_testing ? 'text-gray-900' : 'text-gray-400']">
										{{ vend.cms_invoice_history['last_delivery_driver'] }} <br>
								</span>
								<span :class="[vend.is_active || vend.is_testing ? 'text-gray-900' : 'text-gray-400']">
										{{ vend.last_invoice_date }}
										<div
											class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full text-gray-900"
											:class="[(vend.last_invoice_diff_count >= 5 && vend.last_invoice_diff_count < 7) ? 'bg-yellow-200' : (vend.last_invoice_diff_count >= 7 ? 'bg-red-200' : '') ]"
											v-if="vend.last_invoice_diff"
										>
											<span>
												{{ vend.last_invoice_diff }}
											</span>
										</div>
								</span>
							<!-- </div> -->
						</TableData>
						<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center" v-if="indexType === 'customers' && !roles.includes('operator_3pl')">
							<!-- <div v-if="vend.vend && !vend.vend.nextOpsJobItem"> -->
								<span v-if="vend.cms_invoice_history && 'next_delivery_driver' in vend.cms_invoice_history" :class="[vend.is_active || vend.is_testing ? 'text-gray-900' : 'text-gray-400']">
											{{ vend.cms_invoice_history['next_delivery_driver'] }} <br>
								</span>
								<span :class="[vend.is_active || vend.is_testing ? 'text-gray-900' : 'text-gray-400']">
										{{ vend.next_invoice_date }} <br>
										<div
											class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full text-gray-900"
											:class="[(vend.next_invoice_diff_count < 1 &&  vend.next_invoice_diff_count > 0) ? 'bg-green-200' : ((vend.next_invoice_diff_count > -1 && vend.next_invoice_diff_count < 0) ? 'bg-yellow-200' : '') ]"
											v-if="vend.next_invoice_diff"
										>
											<span>
												{{ vend.next_invoice_diff }}
											</span>
										</div>
								</span>
							<!-- </div> -->
						</TableData>
						<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center" v-if="indexType == 'customers' && !roles.includes('operator_3pl')">
							<div class="flex flex-col space-y-2">
								<div v-if="vend.vend && vend.vend.lastOpsJobItem" class="flex flex-col space-y-1">
									<span>
										{{ vend.vend.lastOpsJobItem.opsJob.deliveredBy.name }}
									</span>
									<span class="flex flex-col space-y-1">
										<span>
											{{ vend.vend.lastOpsJobItem.opsJob.date_formatted }}
										</span>
										<div
											class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full text-gray-900"
											:class="[(vend.vend.lastOpsJobItem.opsJob.date_diff_count < 1 &&  vend.vend.lastOpsJobItem.opsJob.date_diff_count > 0) ? 'bg-green-200' : ((vend.vend.lastOpsJobItem.opsJob.date_diff_count > -1 && vend.vend.lastOpsJobItem.opsJob.date_diff_count < 0) ? 'bg-yellow-200' : '') ]"
											v-if="vend.vend.lastOpsJobItem.opsJob.date_diff_human"
										>
											<span>
												{{ vend.vend.lastOpsJobItem.opsJob.date_diff_human }}
											</span>
										</div>
									</span>
									<span class="flex flex-col space-y-1"
										v-if="vend.vend.lastOpsJobItem.status >= 3"
										:class="[vend.vend.lastOpsJobItem.status == 4 ? 'text-green-700' : (vend.vend.lastOpsJobItem.status == 98 ? 'text-red-700' : '')]"
									>
										<span>
											{{ operatorCountry.currency_symbol }}{{ vend.last_ops_job_amount ? vend.last_ops_job_amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : 0 }}
										</span>
										<span>
											{{ vend.last_ops_job_count ? vend.last_ops_job_count.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : 0 }}
										</span>
									</span>
								</div>
							</div>
						</TableData>
						<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center" v-if="indexType == 'customers' && !roles.includes('operator_3pl')">
							<div class="flex flex-col space-y-2">
								<div v-if="vend.vend && vend.vend.nextOpsJobItem" class="flex flex-col space-y-1">
									<span>
										{{ vend.vend.nextOpsJobItem.opsJob.deliveredBy.name }}
									</span>
									<span class="flex flex-col space-y-1">
										<span>
											{{ vend.vend.nextOpsJobItem.opsJob.date_formatted }}
										</span>
										<div
											class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full text-gray-900"
											:class="[(vend.vend.nextOpsJobItem.opsJob.date_diff_count < 1 &&  vend.vend.nextOpsJobItem.opsJob.date_diff_count > 0) ? 'bg-green-200' : ((vend.vend.nextOpsJobItem.opsJob.date_diff_count > -1 && vend.vend.nextOpsJobItem.opsJob.date_diff_count < 0) ? 'bg-yellow-200' : '') ]"
											v-if="vend.vend.nextOpsJobItem.opsJob.date_diff_human"
										>
											<span>
												{{ vend.vend.nextOpsJobItem.opsJob.date_diff_human }}
											</span>
										</div>
									</span>
									<span class="flex flex-col space-y-1" v-if="vend.vend.nextOpsJobItem.status == 2"
										:class="[vend.vend.nextOpsJobItem.status == 4 ? 'text-green-700' : (vend.vend.nextOpsJobItem.status == 98 ? 'text-red-700' : '')]">
										<span>
											{{ operatorCountry.currency_symbol }}{{ vend.next_ops_job_amount ? vend.next_ops_job_amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : 0 }}
										</span>
										<span>
											{{ vend.next_ops_job_count ? vend.next_ops_job_count.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : 0 }}
										</span>
									</span>
								</div>
							</div>
						</TableData>
						<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center" v-if="!roles.includes('operator_3pl')">
							<div class="flex flex-col space-y-2">
								<span
								v-if="vend.vendTransactionTotalsJson && 'vend_records_amount_latest' in vend.vendTransactionTotalsJson"
								:class="vend.is_active || vend.is_testing ? 'text-gray-900' : 'text-gray-400'"
								>
									{{ operatorCountry.currency_symbol }}{{(vend.vendTransactionTotalsJson['vend_records_amount_latest'] / (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0})}}
								</span>
								<span
									v-if="vend.begin_date"
									:class="vend.is_active || vend.is_testing ? 'text-gray-900' : 'text-gray-400'"
								>
									{{ vend.begin_date_short }}
								</span>
								<span
								v-if="vend.vendTransactionTotalsJson && 'vend_records_amount_average_day' in vend.vendTransactionTotalsJson"
								:class="[ vend.is_active || vend.is_testing ? getVendRecordsAmountAverageDayClass(vend.vendTransactionTotalsJson['vend_records_amount_average_day']) : 'text-gray-400']"
								>
									{{ operatorCountry.currency_symbol }}{{(vend.vendTransactionTotalsJson['vend_records_amount_average_day'] / (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
								</span>
								<span :class="[(vend.is_active || vend.is_testing) && vend.vendTransactionTotalsJson && 'vend_records_amount_average_day' in vend.vendTransactionTotalsJson ? (vend.virtual_vend_records_thirty_days_amount_average >= vend.vendTransactionTotalsJson['vend_records_amount_average_day']/100 ? 'text-green-700' : 'text-red-700') : 'text-gray-400']">
										{{ operatorCountry.currency_symbol }}{{ vend.virtual_vend_records_thirty_days_amount_average.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
								</span>
							</div>
						</TableData>
						<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center" v-if="!roles.includes('operator_3pl')">
							<div class="flex flex-col space-y-1">
								<div
										class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
										:class="[vend.is_testing ? 'bg-gray-200' : (vend.is_active ? 'bg-green-200' : 'bg-red-200')]"
								>
										<div class="flex flex-col">
												<span class="font-bold">
														{{vend.is_testing ? 'Factory' : (vend.is_active ? 'Active' : 'Not Active')}}
												</span>
										</div>

								</div>
								<div
										class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
										:class="[vend.is_active || vend.is_testing ? (vend.is_online ? 'bg-green-200' : 'bg-red-200') : 'bg-gray-200 text-gray-400']"
								>
										<div class="flex flex-col">
												<span class="font-bold">
														{{vend.is_online ? 'Online' : 'Offline'}}
												</span>
												<span v-if="vend.last_online_at">
														{{vend.last_online_at}}
												</span>
										</div>

								</div>
								<div
										class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
										:class="[vend.is_active || vend.is_testing ? (vend.parameterJson['Sensor'] % 2 == 0 ? 'bg-red-200' : 'bg-green-200') : 'bg-gray-200 text-gray-400']"
										v-if="vend.parameterJson"
								>
										<div class="flex flex-col">
												<span class="font-bold">
														Drop Sensor
												</span>
												<span>
														{{vend.parameterJson['Sensor'] % 2 == 0 ? 'Disabled' : 'Enabled'}}
												</span>
										</div>
								</div>
								<div
										class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
										:class="[vend.is_active || vend.is_testing ? 'bg-green-200' : 'bg-gray-200 text-gray-400']"
										v-if="vend.parameterJson && 'fan' in vend.parameterJson"
								>
										<div class="flex flex-col">
												<span class="font-bold">
														Fan Speed
												</span>
												<span>
														{{vend.parameterJson['fan']}}
												</span>
										</div>
								</div>
								<div
										class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
										:class="[vend.is_active || vend.is_testing ? (vend.parameterJson['door'] == 'close' ? 'bg-green-200' : 'bg-red-200') : 'bg-gray-200 text-gray-400']"
										v-if="vend.parameterJson && vend.parameterJson['door']"
								>
										<div class="flex flex-col">
												<span class="font-bold">
														Door
												</span>
												<span>
														{{vend.parameterJson['door'] == 'open' ? 'Open' : 'Close'}}
												</span>
										</div>
								</div>
								<div
										class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
										:class="[vend.is_active || vend.is_testing ? (vend.is_mqtt_active ? 'bg-green-200' : 'bg-gray-200') : 'bg-gray-200 text-gray-400']"
										v-if="vend.is_mqtt"
								>
										<div class="flex flex-col">
												<span class="font-bold">
														MQTT
												</span>
												<span v-if="vend.mqtt_last_updated_at">
														{{ vend.mqtt_last_updated_at }}
												</span>
										</div>
								</div>
								<div
									class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full bg-green-200 text-gray-900"
									v-if="vend.acbVmcPaJson && 'TempLimit' in vend.acbVmcPaJson"
								>
												<div class="flex flex-col">
														<span class="font-bold">
																Temp Limit
														</span>
														<span>
																{{vend.acbVmcPaJson['TempLimit']}}
														</span>
												</div>
								</div>
							</div>
						</TableData>
						<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center" v-if="!roles.includes('operator_3pl')">
							<div class="flex flex-col space-y-1">
								<div
										class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
										:class="[vend.is_active || vend.is_testing ? (vend.acbVmcPaJson['QRCode'] == 1 ? 'bg-green-200' : 'bg-gray-200') : 'bg-gray-200 text-gray-400']"
										v-if="vend.acbVmcPaJson && 'QRCode' in vend.acbVmcPaJson"
								>
										<div class="flex flex-col">
												<span class="font-bold">
														QR Code
												</span>
												<span>
														{{vend.acbVmcPaJson['QRCode'] == 1 ? 'Active' : 'NA'}}
												</span>
										</div>
								</div>
								<div
										class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
										:class="[vend.is_active || vend.is_testing ? (vend.parameterJson['BILLStat'] == 3 ? 'bg-green-200' : (vend.parameterJson['BILLStat'] == 1 ? 'bg-red-200' : 'bg-gray-200')) : 'bg-gray-200 text-gray-400']"
										v-if="vend.parameterJson && 'BILLStat' in vend.parameterJson"
								>
										<div class="flex flex-col">
												<span class="font-bold">
														Bill Acceptor
												</span>
												<span>
														{{vend.parameterJson['BILLStat'] == 3 ? 'Active' : (vend.parameterJson['BILLStat'] == 1 ? 'Inactive' : 'NA') }}
												</span>
										</div>
								</div>
								<div
										class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
										:class="[vend.is_active || vend.is_testing ? (vend.acbVmcPaJson['BILL_MFG'] ? 'bg-green-200' : 'bg-gray-200') : 'bg-gray-200 text-gray-400']"
										v-if="vend.acbVmcPaJson && 'BILL_MFG' in vend.acbVmcPaJson"
								>
										<div class="flex flex-col">
												<span class="font-bold">
														Bill Mfg
												</span>
												<span>
														{{vend.acbVmcPaJson['BILL_MFG'] ? vend.acbVmcPaJson['BILL_MFG'] : 'NA' }}
												</span>
										</div>
								</div>
								<div
										class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
										:class="[vend.is_active || vend.is_testing ? (vend.parameterJson['CHGEStat'] == 3 ? 'bg-green-200' : (vend.parameterJson['CHGEStat'] == 1 ? 'bg-red-200' : 'bg-gray-200')) : 'bg-gray-200 text-gray-400']"
										v-if="vend.parameterJson && 'CHGEStat' in vend.parameterJson"
								>
										<div class="flex flex-col">
												<span class="font-bold">
														Coin Acceptor
												</span>
												<span>
														{{vend.parameterJson['CHGEStat'] == 3 ? 'Active' : (vend.parameterJson['CHGEStat'] == 1 ? 'Inactive' : 'NA') }}
												</span>
										</div>
								</div>
								<div
								class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
								:class="[vend.is_active || vend.is_testing ? (vend.parameterJson['CoinCnt'] > 1600 ? 'bg-green-200' : 'bg-red-200') : 'bg-gray-200 text-gray-400']"
								v-if="vend.parameterJson && vend.parameterJson['CoinCnt']"
								>
										<div class="flex flex-col">
												<span class="font-bold">
														Coin Float
												</span>
												<span>
														{{(vend.parameterJson['CoinCnt']/ (Math.pow(10, operatorCountry.currency_exponent))).toFixed(2)}}
												</span>
										</div>
								</div>
								<div
										class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full bg-green-200 text-gray-800"
										v-if="vend.acbVmcPaJson && 'CoinLimit' in vend.acbVmcPaJson"
								>
										<div class="flex flex-col">
												<span class="font-bold">
														Coin Limit
												</span>
												<span>
														{{vend.acbVmcPaJson['CoinLimit']}}
												</span>
										</div>
								</div>
								<div
										class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
										:class="[vend.is_active || vend.is_testing ? (vend.acbVmcPaJson['COIN_MFG'] ? 'bg-green-200' : 'bg-gray-200') : 'bg-gray-200 text-gray-400']"
										v-if="vend.acbVmcPaJson && 'COIN_MFG' in vend.acbVmcPaJson"
								>
										<div class="flex flex-col">
												<span class="font-bold">
														Coin Mfg
												</span>
												<span>
														{{vend.acbVmcPaJson['COIN_MFG'] ? vend.acbVmcPaJson['COIN_MFG'] : 'NA' }}
												</span>
										</div>
								</div>
								<div
										class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
										:class="[vend.is_active || vend.is_testing ? (vend.parameterJson['CSHLStat'] == 3 ? 'bg-green-200' : (vend.parameterJson['CSHLStat'] == 1 ? 'bg-red-200' : 'bg-gray-200')) : 'bg-gray-200 text-gray-400']"
										v-if="vend.parameterJson && 'CSHLStat' in vend.parameterJson"
								>
										<div class="flex flex-col">
												<span class="font-bold">
														Cashless Status
												</span>
												<span>
														{{vend.parameterJson['CSHLStat'] == 3 ? 'Active' : (vend.parameterJson['CSHLStat'] == 1 ? 'Inactive' : 'NA') }}
												</span>
										</div>
								</div>
								<div
										class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
										:class="[vend.is_active || vend.is_testing ? (vend.acbVmcPaJson['CSHL_MFG'] ? 'bg-green-200' : 'bg-gray-200') : 'bg-gray-200 text-gray-400']"
										v-if="vend.acbVmcPaJson && 'CSHL_MFG' in vend.acbVmcPaJson"
								>
										<div class="flex flex-col">
												<span class="font-bold">
														Cashless Mfg
												</span>
												<span>
														{{vend.acbVmcPaJson['CSHL_MFG'] ? vend.acbVmcPaJson['CSHL_MFG'] : 'NA' }}
												</span>
										</div>
								</div>
							</div>
						</TableData>
						<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center" v-if="indexType === 'customers' && !roles.includes('operator_3pl')">
							<span :class="vend.is_active || vend.is_testing ? 'text-gray-900' : 'text-gray-400'">
								<div class="flex flex-col space-y-2">
									<span>
										{{ vend.operator_code }}
									</span>
									<span>
										{{ vend.account_manager_name }}
									</span>
									<span>
										{{ vend.postcode }}
									</span>
									<span>
										{{ vend.location_type_name }}
									</span>
								</div>
							</span>
						</TableData>
						<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center" v-if="indexType === 'customers' && !roles.includes('operator_3pl')">
							<span :class="vend.is_active || vend.is_testing ? 'text-gray-900' : 'text-gray-400'">
								<div class="flex flex-col space-y-2">
									<span>
										{{ vend.zone_name }}
									</span>
									<div class="flex flex-col space-y-1">
										<span
											v-for="(day, dayIndex) in dayOptions"
											:key="dayIndex"
											v-if="vend.preferred_visit_days_json"
										>
											<span v-if="vend.preferred_visit_days_json[dayIndex - 1] == true" class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800">
												{{ day.value }}
											</span>
										</span>
									</div>
								</div>
							</span>
						</TableData>
						<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center" v-if="indexType === 'customers' && !roles.includes('operator_3pl')">
							<span :class="vend.is_active || vend.is_testing ? 'text-gray-900' : 'text-gray-400'">
								<div class="flex flex-col space-y-2">
									<span class="flex flex-col space-y-1">
										<span class="text-blue-600" v-if="vend.acbVmcPaJson && 'VMC_MDL' in vend.acbVmcPaJson">
												{{ vend.acbVmcPaJson['VMC_MDL'] }}
										</span>
										<span class="text-blue-600">
											{{ vend.parameterJson && vend.parameterJson['Ver'] ? vend.parameterJson['Ver'].toString(16) : null }}
										</span>
										<span class="text-gray-900" v-if="vend.apkVerJson && 'deviceType' in vend.apkVerJson">
												{{ vend.apkVerJson['deviceType'] }}
										</span>
										<span class="text-gray-900" v-if="vend.apkVerJson && 'apkver' in vend.apkVerJson">
											Apk: {{ vend.apkVerJson['apkver'] }}
											<span v-if="vend.apkVerJson && 'buildtime' in vend.apkVerJson">
													{{ moment(new Date(vend.apkVerJson['buildtime'])).format('YYMMDD HH:mm:ss')  }}
											</span>
										</span>
										<span class="text-blue-600" v-if="vend.acbVmcPaJson && 'ACBVer' in vend.acbVmcPaJson">
											{{ vend.acbVmcPaJson['ACBVer'] }}
										</span>
									</span>
									<Link :href="'/vends/' + vend.vend_id + '/edit'" v-if="permissions.includes('admin-access vend-customers')">
										<Button
										type="button" class="bg-blue-300 hover:bg-blue-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
										>
										<EllipsisHorizontalCircleIcon class="w-4 h-4"></EllipsisHorizontalCircleIcon>
										<span>
												more
										</span>
										</Button>
									</Link>
								</div>
							</span>
						</TableData>
					</tr>
					<tr v-if="!vends.data.length">
						<td colspan="24" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
								No Results Found
						</td>
					</tr>
				</tbody>
			</table>
		</div>
		<Paginator v-if="vends.data.length" :links="vends.links" :meta="vends.meta"></Paginator>
</div>
</div>
</div>
<ChannelOverview
		v-if="showChannelOverviewModal"
		:productOptions="productOptions"
		:vend="vend"
		:showModal="showChannelOverviewModal"
		@modalClose="onChannelOverviewClosed"
>
</ChannelOverview>
<Create
		v-if="showCreateModal"
		:showModal="showCreateModal"
		:permissions="permissions"
		:type="type"
		@modalClose="onCreateModalClose"
>
</Create>
<Form
		v-if="showEditModal"
		:vend="vend"
		:type="type"
		:showModal="showEditModal"
		:permissions="permissions"
		@modalClose="onModalClose"
>
</Form>
<PickList
		v-if="showPickListModal"
		:pickLists="pickLists"
		:showModal="showPickListModal"
		@modalClose="onPickListModalClose"
>
</PickList>
<ProductAvailability
	v-if="showProductAvailabilityModal"
	:products="productOptions"
	:showModal="showProductAvailabilityModal"
	@modalClose="onProductAvailabilityModalClose"
	@productUpdated="refreshProductOptions"
>
</ProductAvailability>
<AssignJob
	v-if="showAssignJobModal"
	:driverOptions="driverOptions"
	:showModal="showAssignJobModal"
	@modalClose="onAssignJobModalClose"
	@jobAssigned="onJobAssigned"
	:vends="vends.data.filter(vend => vend.is_selected)"
>
</AssignJob>

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
		import AssignJob from '@/Pages/Vend/AssignJob.vue';
    import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
    import Button from '@/Components/Button.vue';
    import ChannelOverview from '@/Pages/Vend/ChannelOverview.vue';
    import Create from '@/Pages/Vend/Create.vue';
    import DatePicker from '@/Components/DatePicker.vue';
    import Form from '@/Pages/Vend/Form.vue';
    import Paginator from '@/Components/Paginator.vue';
    import PickList from '@/Pages/Vend/PickList.vue';
		import ProductAvailability from '@/Pages/Vend/ProductAvailability.vue';
    import SearchInput from '@/Components/SearchInput.vue';
		import Toast from '@/Components/Toast.vue';
    import MultiSelect from '@/Components/MultiSelect.vue';
    import { ArrowDownTrayIcon, ArrowPathIcon, ChevronDoubleDownIcon, ChevronDoubleUpIcon, EllipsisHorizontalCircleIcon, MagnifyingGlassIcon, BackspaceIcon, PlayCircleIcon, ClipboardDocumentCheckIcon} from '@heroicons/vue/20/solid';
    import TableHead from '@/Components/TableHead.vue';
    import TableData from '@/Components/TableData.vue';
    import TableHeadSort from '@/Components/TableHeadSort.vue';
    import SingleSortItem from '@/Components/SingleSortItem.vue';
    import { ref, onMounted } from 'vue';
    import { router, Link, Head, usePage } from '@inertiajs/vue3';
    import moment from 'moment';
    import axios from 'axios';

    const props = defineProps({
        cmsEndpoint: String,
        constTempError: Number,
				dayOptions: [Array, Object],
        deviceTypes: [Array, Object],
				driverOptions: Object,
        indexType: String,
        locationTypeOptions: Object,
        nextDeliveryDriverOptions: [Array, Object],
        operatorOptions: Object,
        productOptions: Object,
        sellingPriceTypeOptions: [Array, Object],
        totals: [Array, Object],
        vends: Object,
        vendChannelErrors: Object,
				vendModelOptions: Object,
        vendPrefixOptions: Object,
				zoneOptions: Object,
    })

    const filters = ref({
        account_manager_name: '',
        apk_ver: '',
        codes: '',
        coinLessThan: '',
        channel_codes: '',
        serialNum: '',
        customer: '',
        deviceType: '',
        errors: [],
        firmware_ver: '',
        locationType: '',
        is_active: true,
        is_binded_customer: '',
        tempHigherThan: '',
        t2HigherThan: '',
        tempDeltaHigherThan: '',
        vend_channel_error_id: '',
        lastVisitedGreaterThan: '',
        next_planned_date: '',
        next_planned_driver: '',
				operators: [],
        is_mqtt: '',
        is_mqtt_active: '',
        is_online: '',
        is_sensor: '',
        //   is_testing: '',
        is_door_open: '',
				preferredDays: [],
        fanSpeedLowerThan: '',
        balanceStockLessThan: '',
        remainingSkuLessThan: '',
        // vend_prefix_id: '',
				vendPrefixes: [],
        selling_price_type: '',
        status: '',
        sortKey: '',
				vendModels: [],
        vendRecordsThirtyDaysAmountAverageLessThan: '',
        sortBy: true,
        numberPerPage: '',
        visited: true,
				zones: [],
    })

		const showAssignJobModal = ref(false)
    const authOperator = usePage().props.auth.operator
    const baseUrl = ref(props.indexType === 'customers' ? '/vends/customers' : '/vends')
    const booleanOptions = ref([])
    const booleanStrictOptions = ref([])
    const deviceTypeOptions = ref([])
		const dayOptions = ref([])
    const doorOptions = ref([])
    const enableOptions = ref([])
    const isActiveFactoryOptions = ref([])
    const isShowOperationDiv = ref(false)
    const isSelectedAll = ref(false)
    const loading = ref(false)
    const loadingSyncNextDeliveryDate = ref(false)
    const locationTypeOptions = ref([])
    const nextDeliveryDriverOptions = ref([])
    const numberPerPageOptions = ref([])
    const operatorOptions = ref([])
    const pickLists = ref([])
    const sellingPriceTypeOptions = ref([])
    const showAllFilters = ref(false)
    const showChannelOverviewModal = ref(false)
    const showCreateModal = ref(false)
    const showEditModal = ref(false)
    const showPickListModal = ref(false)
		const showProductAvailabilityModal = ref(false)
    const statusOptions = ref([])
    const type = ref('')
    const vend = ref()

    const vends = ref(getVendsField())
    const vendChannelErrorsOptions = ref([])
		const vendModelOptions = ref([])
    const vendPrefixOptions = ref([])
		const zoneOptions = ref([])
    //   const vendOptions = ref([])
    const operatorCountry = usePage().props.auth.operatorCountry
    const operatorRole = usePage().props.auth.operatorRole
    const permissions = usePage().props.auth.permissions
    const roles = usePage().props.auth.roles
    const initBinded = usePage().props.initBinded
    const now = ref(moment().format('HH:mm:ss'))

onMounted(() => {
  filters.value.visited = true
  vendChannelErrorsOptions.value = [
      // {'id': '', 'desc': 'All'},
      {'id': 'errors_only', 'desc': 'Errors Only'},
      ...props.vendChannelErrors.data
  ]
  numberPerPageOptions.value = [
      { id: 50, value: 50 },
      { id: 100, value: 100 },
      { id: 200, value: 200 },
      { id: 500, value: 500 },
      { id: 'All', value: 'All' },
  ]
  filters.value.vend_channel_error_id = vendChannelErrorsOptions.value[0]
  filters.value.numberPerPage = numberPerPageOptions.value[0]

	deviceTypeOptions.value =
	[
			{id: 'all', value: 'All'},
			...Object.entries(props.deviceTypes).map(([id, name]) => ({id: id, value: name}))
	]
  booleanOptions.value = [
      {id: 'all', value: 'All'},
      {id: 'true', value: 'Yes'},
      {id: 'false', value: 'No'},
  ]
  booleanStrictOptions.value = [
      {id: 'true', value: 'Yes'},
      {id: 'false', value: 'No'},
  ]
	dayOptions.value = [
			{id: 'all', value: 'All'},
			...Object.entries(props.dayOptions).map(([id, name]) => ({id: id, value: name}))
	]
  enableOptions.value = [
      {id: 'all', value: 'All'},
      {id: 'true', value: 'Enabled'},
      {id: 'false', value: 'Disabled'},
  ]
  doorOptions.value = [
      {id: 'all', value: 'All'},
      {id: 'open', value: 'Open'},
      {id: 'close', value: 'Close'},
  ]
  isActiveFactoryOptions.value = [
      {id: 'all', value: 'All'},
      {id: '1', value: 'Factory'},
      {id: '2', value: 'Active'},
      {id: '3', value: 'Not Active'},
  ]
  locationTypeOptions.value = [
      {id: 'all', value: 'All'},
      ...props.locationTypeOptions.data.map((data) => {return {id: data.id, value: data.name}})
  ]
  nextDeliveryDriverOptions.value = [
      {id: 'all', value: 'All'},
      ...props.nextDeliveryDriverOptions.map((data) => {return {id: data.name, value: data.name}})
  ]
  operatorOptions.value = [
			{id: 'all', full_name: 'All'},
      ...props.operatorOptions.data.map((data) => {return {id: data.id, code: data.code, full_name: data.full_name}})
  ]
	sellingPriceTypeOptions.value = Object.entries(props.sellingPriceTypeOptions).map(([id, name]) => ({id: id, value: name}))
	statusOptions.value = [
			{id: 'all', value: 'All'},
			{id: 'factory', value: 'Factory'},
			{id: 'active', value: 'Active'},
			{id: 'inactive', value: 'Not Active'},
	]

	vendModelOptions.value = [
			{id: 'all', value: 'All'},
			...props.vendModelOptions.data.map((data) => {return {id: data.id, value: data.name}})
	]

	vendPrefixOptions.value = [
			...props.vendPrefixOptions.data.map((data) => {return {id: data.id, value: data.name}})
	]

	zoneOptions.value = [
			{id: 'all', value: 'All'},
			...props.zoneOptions.data.map((data) => {return {id: data.id, value: data.name}})
	]

  filters.value.is_active = booleanOptions.value[1]
  filters.value.deviceType = deviceTypeOptions.value[0]
  filters.value.is_door_open = doorOptions.value[0]
  filters.value.is_mqtt = booleanOptions.value[0]
  filters.value.is_mqtt_active = booleanOptions.value[0]
  filters.value.is_online = booleanOptions.value[0]
  filters.value.is_sensor = enableOptions.value[0]
  filters.value.is_testing = booleanOptions.value[2]
  // console.log(initBinded, roles[0])
  filters.value.is_binded_customer = initBinded && (roles[0] == 'superadmin' || roles[0] == 'admin' ||  roles[0] == 'supervisor' || roles[0] == 'driver') ? booleanOptions.value[1] : booleanOptions.value[0]
  filters.value.locationType = locationTypeOptions.value[0]
    filters.value.next_planned_driver = nextDeliveryDriverOptions.value[0]
//   filters.value.operator = operatorOptions.value[0]
  filters.value.operators = authOperator ? [
		operatorOptions.value.find(operator => operator.id === authOperator.id),
		...authOperator.code == 'HIPL' ? [operatorOptions.value.find(operator => operator.code == 'HIMD')] : [],
	] : operatorOptions.value[0]
  filters.value.status = statusOptions.value[2]
    // filters.value.vend_prefix_id = vendPrefixOptions.value[0]
  // vendOptions.value = props.vendOptions.data.map((vend) => {return {id: vend.id, code: vend.code}})
})

function compareRefPrice(vend, channel) {
	let type = vend && vend.customer ? vend.customer.selling_price_type : vend.selling_price_type

	if(channel.product && channel.product.sellingPrices) {
		let sellingPrice = channel.product.sellingPrices.find((sellingPrice) => sellingPrice.type == type)
		if(sellingPrice) {
			if(channel.amount != sellingPrice.amount/ 100) {
				return 'text-red-500'
			}
		}else {
			return 'text-red-500'
		}
	}

	return 'text-gray-900'
}

function getVendsField() {
    return {
        ...props.vends,
        data: props.vends.data.map((data) => {return {
            ...data,
            vendChannels: props.indexType === 'customers' ? data.vend.vendChannels : data.vendChannels,
        }})
    }
}

  function getVendRecordsAmountAverageDayClass(amount) {
      if(amount >= 3000) {
          return 'text-green-700'
      } else if(amount >= 2000 && amount < 3000) {
          return 'text-blue-700'
      } else if(amount >= 1500 && amount < 2000) {
          return 'text-gray-700'
      } else if(amount >= 1000 && amount < 1500) {
          return 'text-red-700'
      }else {
          return 'text-gray-700 bg-red-300 px-1 rounded-sm'
      }
  }

  function onChannelOverviewClicked(vendData) {
      vend.value = vendData
      showChannelOverviewModal.value = true
  }

  function onChannelOverviewClosed() {
      showChannelOverviewModal.value = false
  }

  function onCreateClicked() {
      type.value = 'create'
      vend.value = null
      showCreateModal.value = true
  }

  function onCreateModalClose() {
      showCreateModal.value = false
  }

  function onEditClicked(vendData) {
      type.value = 'edit'
      vend.value = vendData
      showEditModal.value = true
  }

  function onGeneratePickListClicked() {
    if(vends.value.data.some(vend => vend.is_selected == true)) {
        axios({
            method: 'POST',
            url: '/vends/pick-lists',
            data: vends.value.data.filter((vend) => { return vend.is_selected == true }),
        }).then(response => {
            pickLists.value = response.data
        }).catch(error => {
        }).finally(() => {
            showPickListModal.value = true
        })
    }
  }

	function onAssignJobClicked() {
		showAssignJobModal.value = true
	}

	function onAssignJobModalClose() {
		showAssignJobModal.value = false
	}

	function onJobAssigned() {
		onAssignJobModalClose()

	}

  function onModalClose() {
      showEditModal.value = false
  }

  function onPickListModalClose() {
      showPickListModal.value = false
  }

	function onProductAvailableModalClicked() {
		showProductAvailabilityModal.value = true
	}

	function onProductAvailabilityModalClose() {
		showProductAvailabilityModal.value = false
	}

  function onShowAllFiltersClicked() {
      showAllFilters.value = !showAllFilters.value
  }

	function onSearchFilterUpdated() {
    router.get(baseUrl.value, {
        ...filters.value,
        deviceType: filters.value.deviceType.id,
        errors: filters.value.errors.map((error) => { return error.id }),
        location_type_id: filters.value.locationType.id,
        next_planned_date: filters.value.next_planned_date,
        next_planned_driver: filters.value.next_planned_driver.id,
        operators: filters.value.operators.map((operator) => { return operator.id }),
        is_active: filters.value.is_active.id,
        is_binded_customer: filters.value.is_binded_customer.id,
        is_door_open: filters.value.is_door_open.id,
        is_mqtt: filters.value.is_mqtt.id,
        is_mqtt_active: filters.value.is_mqtt_active.id,
        is_online: filters.value.is_online.id,
        is_sensor: filters.value.is_sensor.id,
				preferredDays: filters.value.preferredDays.map((preferredDay) => { return preferredDay.id }),
        // is_testing: filters.value.is_testing.id,
        status: filters.value.status.id,
        // vend_prefix_id: filters.value.vend_prefix_id.id,
				vendModels: filters.value.vendModels.map((vendModel) => { return vendModel.id }),
				vendPrefixes: filters.value.vendPrefixes.map((vendPrefix) => { return vendPrefix.id }),
				zones: filters.value.zones.map((zone) => { return zone.id }),
        numberPerPage: filters.value.numberPerPage.id,
    }, {
        preserveState: true,
        preserveScroll: true, // Ensure this is set
        // replace: true,
        onFinish: visit => {
            vends.value = getVendsField()
            now.value = moment().format('HH:mm:ss')
        },
    })
	}

	function onSyncCMSInvoiceItemsClicked() {
		axios({
				method: 'POST',
				url: '/customers/sync-cms-invoice-items',
				data: {customerIDs: vends.value.data.map((vend) => { return vend.customer_id })},
		}).then(response => {
		}).catch(error => {
		}).finally(() => {
		})
	}


  function onSyncNextDeliveryDate() {
      loadingSyncNextDeliveryDate.value = true
      axios({
          method: 'get',
          url: '/customers/sync-next-delivery-date',
      }).then(response => {
      }).catch(error => {
      }).finally(() => {
          loadingSyncNextDeliveryDate.value = false
      })
  }

  function onVendTempClicked(vendId, type) {

      const url = '/vends/' + vendId + '/temp/' + type

      window.open(url, '_blank')
      // router.get('/vends/' + vendId + '/temp/' + type)
  }

  function onIsShowOperationDivButtonClicked() {
        isShowOperationDiv.value = !isShowOperationDiv.value
  }

	function refreshProductOptions() {
		router.reload({ only: ['productOptions'] });
	}

  function resetFilters() {
      router.get(baseUrl.value)
  }

function sortTable(sortKey, inverse = false) {
  filters.value.sortBy = !filters.value.sortBy
  if(inverse && filters.value.sortKey != sortKey) {
      filters.value.sortBy = !filters.value.sortBy
  }
  filters.value.sortKey = sortKey
  onSearchFilterUpdated()
}

function toggleSelectAll() {
    if(isSelectedAll.value) {
        vends.value.data.forEach((vend) => {
            vend.is_selected = true
        })
    } else {
        vends.value.data.forEach((vend) => {
            vend.is_selected = false
        })
    }
}

function onExportChannelExcelClicked() {
  loading.value = true
  axios({
      method: 'get',
      url: '/vends/channels/excel',
      params: {
          ...filters.value,
          deviceType: filters.value.deviceType.id,
          errors: filters.value.errors.map((error) => { return error.id }),
          location_type_id: filters.value.locationType.id,
          operators: filters.value.operators.map((operator) => { return operator.id }),
					preferredDays: filters.value.preferredDays.map((preferredDay) => { return preferredDay.id }),
          is_active: filters.value.is_active.id,
          is_binded_customer: filters.value.is_binded_customer.id,
          is_door_open: filters.value.is_door_open.id,
          is_mqtt: filters.value.is_mqtt.id,
          is_mqtt_active: filters.value.is_mqtt_active.id,
          is_online: filters.value.is_online.id,
          is_sensor: filters.value.is_sensor.id,
          is_testing: filters.value.is_testing.id,
          status: filters.value.status.id,
          // vend_prefix_id: filters.value.vend_prefix_id.id,
					vendModels: filters.value.vendModels.map((vendModel) => { return vendModel.id }),
					vendPrefixes: filters.value.vendPrefixes.map((vendPrefix) => { return vendPrefix.id }),
					zones: filters.value.zones.map((zone) => { return zone.id }),
      },
      responseType: 'blob',
  }).then(response => {
      fileDownload(response.data, 'Vending_Channels_' + moment().format('YYMMDDhhmmss') +'.xlsx')
  }).catch(error => {
      console.log(error)
  }).finally(() => {
      loading.value = false
  })
}
</script>