
<template>
	<Head title="Ops Dashboard - Live Status" />

<BreezeAuthenticatedLayout>
	<template #header>
		<h2 class="font-semibold text-xl text-gray-800 leading-tight">
				Ops Dashboard (Live Status)
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
				<SearchInput v-if="showAllFilters" placeholderStr="Channel ID" v-model="filters.channel_codes" @keyup.enter="onSearchFilterUpdated()">
					Channel ID
					<span class="text-[9px]">
							("," for multiple)
					</span>
				</SearchInput>
				<SearchInput v-if="showAllFilters && permissions.includes('admin-access vend-customers')"  placeholderStr="Serial Num" v-model="filters.serialNum" @keyup.enter="onSearchFilterUpdated()">
					Serial Num
				</SearchInput>
				<SearchInput placeholderStr="Number" v-model="filters.tempHigherThan" @keyup.enter="onSearchFilterUpdated()" v-if="showAllFilters && permissions.includes('admin-access vend-customers')">
					T1 &gt;&gt;
				</SearchInput>
				<SearchInput placeholderStr="Number" v-model="filters.t2HigherThan" @keyup.enter="onSearchFilterUpdated()" v-if="showAllFilters && permissions.includes('admin-access vend-customers')">
					T2 &gt;&gt;
				</SearchInput>
				<SearchInput v-if="showAllFilters && permissions.includes('admin-access vend-customers')"  placeholderStr="Number" v-model="filters.tempDeltaHigherThan" @keyup.enter="onSearchFilterUpdated()">
					T1-T2 Delta &gt;&gt;
				</SearchInput>
				<div v-if="showAllFilters">
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
				<div v-if="showAllFilters">
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
				<div v-if="showAllFilters && permissions.includes('admin-access vend-customers') && indexType === 'customers'">
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
				<div v-if="showAllFilters && permissions.includes('admin-access vend-customers')">
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
				<div v-if="showAllFilters && permissions.includes('admin-access vend-customers')">
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
				<div v-if="showAllFilters && permissions.includes('admin-access vend-customers')">
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
				<div v-if="showAllFilters && permissions.includes('admin-access vend-customers')">
					<label for="text" class="block text-sm font-medium text-gray-700">
						Fan RPM
					</label>
					<MultiSelect
						v-model="filters.fan_rpm"
						:options="fanRpmOptions"
						trackBy="id"
						valueProp="id"
						label="value"
						placeholder="Select"
						open-direction="bottom"
						class="mt-1"
					>
					</MultiSelect>
				</div>
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
				<SearchInput placeholderStr="How many Day(s)" v-model="filters.lastVisitedGreaterThan" @keyup.enter="onSearchFilterUpdated()" v-if="showAllFilters && indexType === 'customers' && permissions.includes('admin-access vend-customers')">
					Last Visited Day &gt;&gt;
				</SearchInput>
				<SearchInput placeholderStr="Balance Stock Less Than" v-model="filters.balanceStockLessThan" @keyup.enter="onSearchFilterUpdated()" v-if="showAllFilters && permissions.includes('admin-access vend-customers')">
					Balance Stock(%) &lt;&lt;
				</SearchInput>
				<SearchInput placeholderStr="Remaining Channel Less Than" v-model="filters.remainingSkuLessThan" @keyup.enter="onSearchFilterUpdated()" v-if="showAllFilters && permissions.includes('admin-access vend-customers')">
					Remaining Channel(%) &lt;&lt;
				</SearchInput>
				<SearchInput placeholderStr="Firmware Ver" v-model="filters.firmware_ver" v-if="showAllFilters && permissions.includes('admin-access vend-customers')" @keyup.enter="onSearchFilterUpdated()">
					Firmware Ver
				</SearchInput>
				<SearchInput placeholderStr="APK Ver" v-model="filters.apk_ver" v-if="showAllFilters && permissions.includes('admin-access vend-customers')" @keyup.enter="onSearchFilterUpdated()">
					APK Ver
				</SearchInput>
				<div v-if="showAllFilters && permissions.includes('admin-access vend-customers')">
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
				<div v-if="showAllFilters">
					<label for="text" class="block text-sm font-medium text-gray-700">
						Card Terminal
					</label>
					<MultiSelect
						v-model="filters.cashless_mfg"
						:options="cardTerminalOptions"
						trackBy="id"
						valueProp="id"
						label="value"
						placeholder="Select"
						open-direction="bottom"
						class="mt-1"
					>
					</MultiSelect>
				</div>
				<SearchInput placeholderStr="Avg Day Sales Less Than" v-model="filters.vendRecordsThirtyDaysAmountAverageLessThan" v-if="showAllFilters && permissions.includes('admin-access vend-customers')" @keyup.enter="onSearchFilterUpdated()">
					Avg/Day Sales (30d) &lt;&lt;
				</SearchInput>
				<div v-if="showAllFilters && permissions.includes('admin-access vend-customers')">
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
				<div v-if="showAllFilters && permissions.includes('admin-access vend-customers')">
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
				<SearchInput placeholderStr="Account Manager" v-model="filters.account_manager_name" v-if="showAllFilters && permissions.includes('admin-access vend-customers') && indexType === 'customers'" @keyup.enter="onSearchFilterUpdated()">
					Account Manager
				</SearchInput>
				<SearchInput placeholderStr="Number" v-model="filters.coinLessThan" @keyup.enter="onSearchFilterUpdated()" v-if="showAllFilters && permissions.includes('admin-access vend-customers')">
					Coin Amount &lt;&lt;
				</SearchInput>
				<div v-if="showAllFilters && indexType === 'customers' && permissions.includes('admin-access vend-customers')">
					<label for="text" class="block text-sm font-medium text-gray-700">
						Upcoming Job Driver
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
				<div v-if="showAllFilters && indexType === 'customers' && permissions.includes('admin-access vend-customers')">
					<DatePicker
						v-model="filters.next_planned_date"
					>
					Upcoming Job Date
					</DatePicker>
				</div>
				<div v-if="showAllFilters && permissions.includes('admin-access vend-customers')">
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
				<div v-if="showAllFilters && permissions.includes('admin-access vend-customers')">
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
				<div v-if="showAllFilters && permissions.includes('admin-access vend-customers')">
					<label for="text" class="block text-sm font-medium text-gray-700">
						Refilling Routes
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
				<div v-if="showAllFilters && permissions.includes('admin-access vend-customers')">
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
				<div v-if="showAllFilters && permissions.includes('admin-access vend-customers')">
					<label for="text" class="block text-sm font-medium text-gray-700">
						#Refill per Week
					</label>
					<MultiSelect
						v-model="filters.frequency_per_week_status"
						:options="frequencyPerWeekOptions"
						trackBy="id"
						valueProp="id"
						label="value"
						placeholder="Select"
						open-direction="bottom"
						class="mt-1"
						mode="tags"
					></MultiSelect>
				</div>
				<div v-if="showAllFilters && permissions.includes('admin-access vend-customers')">
					<label for="text" class="block text-sm font-medium text-gray-700">
							Delivery Platform
					</label>
					<MultiSelect
							v-model="filters.delivery_platform_id"
							:options="deliveryPlatformOptions"
							trackBy="id"
							valueProp="id"
							label="value"
							placeholder="Select"
							open-direction="bottom"
							class="mt-1"
					>
					</MultiSelect>
				</div>
				<div v-if="showAllFilters && permissions.includes('admin-access vend-customers')">
						<label for="text" class="block text-sm font-medium text-gray-700">
								Setting Chart
						</label>
						<MultiSelect
								v-model="filters.vendConfigs"
								:options="vendConfigOptions"
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
				<div v-if="showAllFilters && permissions.includes('admin-access vend-customers')">
						<label for="text" class="block text-sm font-medium text-gray-700">
								Machine Contract
						</label>
						<MultiSelect
								v-model="filters.vendContracts"
								:options="vendContractOptions"
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
				<div v-if="showAllFilters && permissions.includes('admin-access vend-customers')">
						<label for="text" class="block text-sm font-medium text-gray-700">
								Product Mapping
						</label>
						<MultiSelect
								v-model="filters.productMappings"
								:options="productMappingOptions"
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
						<Button
							class="inline-flex space-x-1 items-center rounded-md border border-sky bg-sky-300 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-gray-800 shadow-sm hover:bg-sky-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
							@click.prevent="onMapAllMarkerClicked"
							v-if="vends.data && vends.data.some(vend => vend.deliveryAddress && vend.deliveryAddress.latitude && vend.deliveryAddress.longitude)"
						>
							<MapPinIcon class="h-4 w-4" aria-hidden="true" />
							<span>Show Map Markers</span>
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
					<a :href="'/products/availability'" target="_blank" class="inline-flex space-x-1 items-center rounded-md border border-sky bg-sky-500 px-8 py-3 md:px-5 text-sm font-medium leading-4 text-white shadow-sm hover:bg-sky-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
					>
					<!-- @click="onProductAvailableModalClicked()" -->
						<PlayCircleIcon class="h-4 w-4" aria-hidden="true"/>
						<span class="flex flex-col space-y-1">
							<span>
									Set Product Availability
							</span>
						</span>
					</a>

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
			<dl v-if="hasSearched" class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
				<div class="overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow md:block">
					<dt class="truncate text-sm font-medium text-gray-500">Total Stock In (Last 30 days)</dt>
					<dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
						{{totals['thirthyDaysStockIn'].toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
					</dd>
				</div>
				<div class="overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow md:block">
					<dt class="truncate text-sm font-medium text-gray-500">Total Sales (Last 30 days)</dt>
					<dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
						{{totals['thirtyDays'].toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
					</dd>
				</div>
				<div class="overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow md:block">
					<dt class="truncate text-sm font-medium text-gray-500">Avg Sales per VM (Last 30 days)</dt>
					<dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
						{{(totals['thirtyDays']/vends.meta.to ? totals['thirtyDays']/vends.meta.to : 0).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
					</dd>
				</div>
				<div class="overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow md:block">
					<dt class="truncate text-sm font-medium text-gray-500">Avg Daily Sales per VM (Last 30 days)</dt>
					<dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
						{{(totals['thirthyDaysAvg']/vends.meta.to ? totals['thirthyDaysAvg']/vends.meta.to : 0).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
					</dd>
				</div>
				<div class="overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow md:block">
					<dt class="truncate text-sm font-medium text-gray-500">Total Gross Earning (Last 30 days)</dt>
					<dd class="mt-1 text-2xl font-semibold tracking-normal text-gray-900">
						{{(totals['thirtyDaysGrossEarning'] || 0).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
					</dd>
				</div>
				<div class="overflow-hidden rounded-lg bg-gray-100 mt-1 px-4 py-3 shadow md:block">
					<dt class="truncate text-sm font-medium text-gray-500">Total Vend Earning (Last 30 days)</dt>
					<dd
						class="mt-1 text-2xl font-semibold tracking-normal"
						:class="(totals['thirtyDaysVendingEarning'] || 0) >= 0 ? 'text-gray-900' : 'text-red-700'"
					>
						{{(totals['thirtyDaysVendingEarning'] || 0).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
					</dd>
				</div>
			</dl>
	</div>

	<div class="mt-6 flex flex-col" v-if="hasSearched">
	<div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
	<div class="overflow-scroll max-h-[900px] md:max-h-[1500px] shadow-sm ring-1 ring-black ring-opacity-5">
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
							<SingleSortItem modelName="vend_configs.name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_configs.name', false)">
								Setting Chart
							</SingleSortItem>
							<SingleSortItem modelName="vend_prefixes.name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_prefixes.name', false)">
								Machine Prefix
							</SingleSortItem>
							<SingleSortItem modelName="product_mappings.name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('product_mappings.name', false)">
								Product Mapping
							</SingleSortItem>
							<SingleSortItem modelName="customers.virtual_customer_code" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('customers.virtual_customer_code')">
								Customer
							</SingleSortItem>
							<SingleSortItem modelName="postcode" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('postcode')">
								Postcode
							</SingleSortItem>
							<SingleSortItem modelName="customers.selling_price_type" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('customers.selling_price_type', false)">
								Ref Price
							</SingleSortItem>
						</div>
					</TableHead>
					<TableHead>
						<div class="flex flex-col space-y-2">
							<div class="flex justify-center items-center">
								<SingleSortItem modelName="temp" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('temp', true)">
									T1: Machine Temp
								</SingleSortItem>
								<ExclamationCircleIcon class="min-w-5 w-5 h-5 self-center pl-1 text-sky-500" v-tooltip="{ content: 'Temp Probe Located at the Top of the Freezer <br> Red: > -12c <br> Blue: -12c to -18c <br> Green: < -18c', html: true }"></ExclamationCircleIcon>
							</div>
							<div class="flex justify-center items-center">
								<SingleSortItem modelName="parameter_json->t2" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('parameter_json->t2', true)">
								T2: Evaporator Temp
								</SingleSortItem>
								<ExclamationCircleIcon class="min-w-5 w-5 h-5 self-center pl-1 text-sky-500" v-tooltip="{ content: 'Temp Probe Located at the Top of the Evaporator <br> Red: > -12c <br> Blue: -12c to -18c <br> Green: < -18c', html: true }"></ExclamationCircleIcon>
							</div>
							<div class="flex justify-center items-center">
								<SingleSortItem modelName="temp_updated_at" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('temp_updated_at', true)">
									Updated
								</SingleSortItem>
								<ExclamationCircleIcon class="min-w-5 w-5 h-5 self-center pl-1 text-sky-500" v-tooltip="{ content: 'Last Updated Timestamp <br> (every 3 mins)', html: true }"></ExclamationCircleIcon>
							</div>
							<div class="flex justify-center items-center mt-2">
								<SingleSortItem modelName="t1_lowest_48h" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('t1_lowest_48h', true)">
									T1 lowest L48hr
								</SingleSortItem>
								<ExclamationCircleIcon class="min-w-5 w-5 h-5 self-center pl-1 text-sky-500" v-tooltip="{ content: 'Lowest T1 Temp Last 48h <br> Red: > -18c <br> Blue: -21c to -18c <br> Green: < -21c', html: true }"></ExclamationCircleIcon>
							</div>
							<div class="flex justify-center items-center">
								<SingleSortItem modelName="temp_diff" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('temp_diff', true)">
									&Delta;T1-T2
								</SingleSortItem>
								<ExclamationCircleIcon class="min-w-5 w-5 h-5 self-center pl-1 text-sky-500" v-tooltip="{ content: 'Delta of T1 and T2 <br> Under normal condition, 1.5C to 3.5C', html: true }"></ExclamationCircleIcon>
							</div>
							<div class="flex justify-center items-center mt-2">
								<SingleSortItem modelName="parameter_json->fan" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('parameter_json->fan', false)">
									Fan RPM
								</SingleSortItem>
							</div>
						</div>
					</TableHead>
					<TableHead>
						<div class="flex flex-col space-y-2">
							<div class="flex justify-center items-center">
								<span class="flex flex-col space-y-1">
									<span>
										Inventory Status
									</span>
									<span>
										#Channel, Required, Balance/Capacity (LastStockIn)
									</span>
								</span>
								<ExclamationCircleIcon class="min-w-5 w-5 h-5 self-center pl-1 text-sky-500" v-tooltip="{ content: '#Channel <br> Black = P1 same as RP, correct <br> Red = P1 not same with RP <br><br> Balance/Capacity <br> Green = Balance > 2 <br> Blue = Balance 1 & 2 <br> Red = Balance = 0', html: true }"></ExclamationCircleIcon>
							</div>
							<div class="flex justify-center items-center">
								<SingleSortItem modelName="total_stock_cost" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('total_stock_cost')">
									Stock Cost
								</SingleSortItem>
								<ExclamationCircleIcon class="min-w-5 w-5 h-5 self-center pl-1 text-sky-500" v-tooltip="{ content: 'Exclusive GST or VAT', html: true }"></ExclamationCircleIcon>
							</div>
							<div class="flex justify-center items-center">
								<SingleSortItem modelName="total_stock_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('total_stock_amount')">
									Stock Value
								</SingleSortItem>
								<ExclamationCircleIcon class="min-w-5 w-5 h-5 self-center pl-1 text-sky-500" v-tooltip="{ content: 'Inclusive GST or VAT', html: true }"></ExclamationCircleIcon>
							</div>
							<div class="flex justify-center items-center">
								<SingleSortItem modelName="total_full_load_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('total_full_load_amount')">
									Full Load Value
								</SingleSortItem>
							</div>
							<div class="flex flex-col items-center pt-2 space-y-1 w-full mt-2">
								<span>
									Refill Planning
								</span>
								<div class="bg-gray-300 px-2 py-0.5 rounded text-xs text-gray-800 w-fit">
									Grey: Capped Qty (Next Day)
								</div>
								<div class="bg-red-200 px-2 py-0.5 rounded text-xs text-gray-800 w-fit flex items-center space-x-1">
									<span>Red: Not Available</span>
								</div>
							</div>
						</div>
					</TableHead>
					<TableHead>
						<div class="flex flex-col space-y-0.5">
							<span>
								Error
							</span>
							<div class="flex justify-center items-center">
								<SingleSortItem modelName="vend_channel_error_logs_json" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vend_channel_error_logs_json')">
									Uncleared Error(s)
								</SingleSortItem>
								<ExclamationCircleIcon class="min-w-5 w-5 h-5 self-center pl-1 text-sky-500" v-tooltip="{ content: 'Error(s) occurred before and need to be cleared in VMC', html: true }"></ExclamationCircleIcon>
							</div>
							<div class="flex justify-center items-center">
								<SingleSortItem modelName="totals_json->one_day_error_rate" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('totals_json->one_day_error_rate', false)">
									1d Rate
								</SingleSortItem>
								<ExclamationCircleIcon class="min-w-5 w-5 h-5 self-center pl-1 text-sky-500" v-tooltip="{ content: 'Today error rates <br> Green: < 2% <br> Red: >= 2%', html: true }"></ExclamationCircleIcon>
							</div>
							<div class="flex justify-center items-center">
								<SingleSortItem modelName="totals_json->two_days_error_rate" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('totals_json->two_days_error_rate', false)">
									2d Rate
								</SingleSortItem>
								<ExclamationCircleIcon class="min-w-5 w-5 h-5 self-center pl-1 text-sky-500" v-tooltip="{ content: 'Last 2 days error rates <br> Green: < 2% <br> Red: >= 2%', html: true }"></ExclamationCircleIcon>
							</div>
							<div class="flex justify-center items-center">
								<SingleSortItem modelName="totals_json->seven_days_error_rate" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('totals_json->seven_days_error_rate', false)">
									7d Rate
								</SingleSortItem>
								<ExclamationCircleIcon class="min-w-5 w-5 h-5 self-center pl-1 text-sky-500" v-tooltip="{ content: 'Last 7 days error rates <br> Green: < 2% <br> Red: >= 2%', html: true }"></ExclamationCircleIcon>
							</div>
							<!-- PWRON 1d/2d/3d trend block — header has per-day sort
							     handles. Each SingleSortItem hits the matching
							     `pwron_{1,2,3}d_count` alias exposed conditionally by
							     VendController::indexCustomer (single leftJoin against
							     vend_daily_stats). Actual counts render at the bottom of
							     the data cell below. Tooltip wording is product-supplied
							     so it stays in sync with what we tell users PWRON means. -->
							<hr class="border-t border-gray-300 my-1 w-full" />
							<div class="flex justify-center items-center">
								<span class="text-[11px] font-semibold text-gray-900">PWRON</span>
								<ExclamationCircleIcon class="min-w-5 w-5 h-5 self-center pl-1 text-sky-500" v-tooltip="{ content: 'Stability of connectivity. PWRON = Count of machine auto reconnect to server after dropline.<br>1d color vs 2d, 2d color vs 3d — red if higher, green if lower, black if equal. 3d is the baseline.', html: true }"></ExclamationCircleIcon>
							</div>
							<!-- No text-* override here — TableHead drives the base
							     text-[11px] size for every header cell. Adding text-sm
							     pushed these handles to ~14px and made them visually
							     louder than "1d Rate" / "2d Rate" etc. above. -->
							<div class="flex justify-center items-center space-x-1">
								<SingleSortItem modelName="pwron_1d_count" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('pwron_1d_count', false)">
									1d
								</SingleSortItem>
								<span class="text-gray-400">/</span>
								<SingleSortItem modelName="pwron_2d_count" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('pwron_2d_count', false)">
									2d
								</SingleSortItem>
								<span class="text-gray-400">/</span>
								<SingleSortItem modelName="pwron_3d_count" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('pwron_3d_count', false)">
									3d
								</SingleSortItem>
							</div>
							<!-- "# of No Found in Txn" 1d/2d/3d block — sits directly
							     under PWRON, same Error column. Counter is written by
							     LogNofoundTxnIfStillMissing (5 min after a PG payment is
							     approved, if the matching vend_transactions row still
							     hasn't landed) and decremented when the txn eventually
							     arrives — so the number is the count of *currently
							     unresolved* payment-without-transaction anomalies for
							     that day. Headers are per-day sortable; data renders at
							     the bottom of the cell below. -->
							<hr class="border-t border-gray-300 my-1 w-full" />
							<div class="flex justify-center items-center">
								<span class="text-[11px] font-semibold text-gray-900"># of No Found in Txn</span>
								<ExclamationCircleIcon class="min-w-5 w-5 h-5 self-center pl-1 text-sky-500" v-tooltip="{ content: 'Daily count of payment-gateway transactions where the matching machine transaction never arrived within 5 minutes of payment approval. Decrements automatically if the transaction lands later. Sourced from vend_daily_stats (metric=nofound_txn). 1d color vs 2d, 2d color vs 3d — red if higher, green if lower, black if equal.', html: true }"></ExclamationCircleIcon>
							</div>
							<!-- Match the PWRON row above — no explicit text size class
							     so we inherit TableHead's text-[11px]. -->
							<div class="flex justify-center items-center space-x-1">
								<SingleSortItem modelName="nofound_txn_1d_count" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('nofound_txn_1d_count', false)">
									1d
								</SingleSortItem>
								<span class="text-gray-400">/</span>
								<SingleSortItem modelName="nofound_txn_2d_count" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('nofound_txn_2d_count', false)">
									2d
								</SingleSortItem>
								<span class="text-gray-400">/</span>
								<SingleSortItem modelName="nofound_txn_3d_count" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('nofound_txn_3d_count', false)">
									3d
								</SingleSortItem>
							</div>
						</div>
					</TableHead>
					<TableHead v-if="!roles.includes('operator_driver')">
						<div class="flex flex-col space-y-2">
							<span>
								Stock
							</span>
							<div class="flex justify-center items-center">
								<SingleSortItem modelName="balance_percent" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('balance_percent', true)">
									Balance Qty
								</SingleSortItem>
								<ExclamationCircleIcon class="min-w-5 w-5 h-5 self-center pl-1 text-sky-500" v-tooltip="{ content: 'Balance Qty % <br> Red: < 30% <br> Blue: >= 30% and < 50% <br> Green: >= 50%', html: true }"></ExclamationCircleIcon>
							</div>
							<div class="flex justify-center items-center border-b border-gray-300 pb-2 mb-2 w-full">
								<SingleSortItem modelName="out_of_stock_sku_percent" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('out_of_stock_sku_percent', false)">
									Remaining Channel#
								</SingleSortItem>
								<ExclamationCircleIcon class="min-w-5 w-5 h-5 self-center pl-1 text-sky-500" v-tooltip="{ content: 'Channel Availability % <br> Red: < 50% <br> Blue: >= 50% and < 75% <br> Green: >= 75%', html: true }"></ExclamationCircleIcon>
							</div>
							<span>
									Refill Planning
							</span>
							<SingleSortItem modelName="actual_stock_in_value" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('actual_stock_in_value')">
								Refillable Value
							</SingleSortItem>
							<SingleSortItem modelName="actual_stock_in_qty" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('actual_stock_in_qty')">
								Refillable Qty
							</SingleSortItem>
						</div>
					</TableHead>
					<TableHead v-if="!roles.includes('operator_driver')">
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
						<!-- Mthly Sales $ — calendar-month split sitting under the rolling
							Today/Yday/Last7d/Last30d block. Four sub-rows (Current Mth /
							Last Mth / Last 2Mth / Last 3Mth) sourced from vend_records
							(cheaper than re-aggregating vend_transactions) and synced via
							SyncVendTransactionTotalsJson. Up/down arrow chips on the data
							side compare each row to the previous month. -->
						<hr class="border-t border-gray-300 my-2" />
						<span class="text-[11px] font-semibold text-gray-900">Mthly Sales $</span>
						<SingleSortItem modelName="totals_json->current_mth_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('totals_json->current_mth_amount', false)">
							Current Mth
						</SingleSortItem>
						<SingleSortItem modelName="totals_json->last_mth_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('totals_json->last_mth_amount', false)">
							Last Mth
						</SingleSortItem>
						<SingleSortItem modelName="totals_json->last_2_mth_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('totals_json->last_2_mth_amount', false)">
							Last 2Mth
						</SingleSortItem>
						<SingleSortItem modelName="totals_json->last_3_mth_amount" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('totals_json->last_3_mth_amount', false)">
							Last 3Mth
						</SingleSortItem>
					</TableHead>
					<TableHead v-if="!roles.includes('operator_driver')">
						<div class="flex flex-col space-y-2">
							<span>
								Last Job
							</span>
							<span>
								Stock In Value{{ operatorCountry.currency_symbol }}
							</span>
							<span>
								Qty
							</span>
							<span>
								Transaction{{ operatorCountry.currency_symbol }} (Qty)
							</span>
							<div class="border-t border-gray-300 my-1 pt-1">
								<span>
									Last 2 Job
								</span>
							</div>
						</div>
					</TableHead>
					<TableHead v-if="!roles.includes('operator_driver')" inputClass="!px-1">
						<div class="flex flex-col space-y-2 max-w-28 mx-auto">
							<span>
								Upcoming Job
							</span>
							<span>
								Picked Value{{ operatorCountry.currency_symbol }}
							</span>
							<span>
								Qty
							</span>
						</div>
					</TableHead>
					<TableHead v-if="indexType === 'customers' && !roles.includes('operator_driver')">
						<div class="flex flex-col space-y-2">
							<SingleSortItem modelName="zone_name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('zone_name')">
								Refilling Routes
							</SingleSortItem>
							<div>
								Preferred Day(s)
							</div>
							<SingleSortItem modelName="frequency_per_week_status" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('frequency_per_week_status')">
								#Refill per Week
							</SingleSortItem>
							<div>
								Ops Note
							</div>
						</div>
					</TableHead>
					<TableHead v-if="!roles.includes('operator_driver')">
						<div class="flex flex-col space-y-2">
							<SingleSortItem modelName="totals_json->vend_records_amount_latest" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('totals_json->vend_records_amount_latest', true)">
								Lifetime Sales
							</SingleSortItem>
							<SingleSortItem modelName="accumulate_vending_earning_cents" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('accumulate_vending_earning_cents', true)">
								Accumulated VendEarning
							</SingleSortItem>
							<!-- Section divider — visually groups the sales metrics
								above (Lifetime / Accumulated) from the contract /
								date cluster that starts at Begin Dt. The matching
								<hr> sits at the same position in the TableData. -->
							<hr class="border-t border-gray-300 my-2" />
							<SingleSortItem modelName="begin_date" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('begin_date', false)">
								Begin Dt
							</SingleSortItem>
							<!-- Contract End Date — moved out of the Contract Type
								column so it sits beside Begin Dt, which makes the
								start/end dates easier to read together at a glance.
								The auto-renewal icon travels with it (see the
								matching span in the TableData below). -->
							<SingleSortItem modelName="customers.contract_until" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('customers.contract_until', false)">
								Contract End Date
							</SingleSortItem>
							<!-- Notice Period — string column (e.g. "1 mth"). Pairs
								with Contract End Date so the contract summary reads
								end-date + notice together. Same column as Customer
								Summary's Contract terms cluster. -->
							<SingleSortItem modelName="customers.contract_notice_period" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('customers.contract_notice_period', false)">
								Notice Period
							</SingleSortItem>
							<!-- Section divider — closes off the contract/date
								cluster (Begin Dt → Contract End Date → Notice
								Period) before the daily-sales pair (Avg Sales/Day
								→ AvgDailySales). Matching <hr> in the TableData. -->
							<hr class="border-t border-gray-300 my-2" />
							<SingleSortItem modelName="totals_json->vend_records_amount_average_day" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('totals_json->vend_records_amount_average_day', true)">
								Avg Sales/ Day
							</SingleSortItem>
							<SingleSortItem modelName="virtual_vend_records_thirty_days_amount_average" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('virtual_vend_records_thirty_days_amount_average', true)">
								AvgDailySales (Last30d)
							</SingleSortItem>
							<!-- Section divider — separates the daily-sales pair
								from the Customer Tag chips below. Matching <hr>
								in the TableData. Gated on indexType so the line
								only appears when Customer Tag is being shown. -->
							<hr v-if="indexType === 'customers'" class="border-t border-gray-300 my-2" />
							<!-- Customer Tag — chips render at the bottom of this
								column's TableData, mirroring the Customer Summary
								"Customer Tag" row. Header label only shows on the
								customers-index path because tag_bindings is empty
								on the regular /vends rows. -->
							<span v-if="indexType === 'customers'">
								Customer Tag
							</span>
						</div>
					</TableHead>
					<TableHead v-if="!roles.includes('operator_driver')">
						<div class="flex flex-col space-y-2">
							<SingleSortItem modelName="customers.contract_commission_type" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('customers.contract_commission_type', false)">
								Contract Type
							</SingleSortItem>
							<!-- External Subsidize + Net Loc Fee replace the old
								"Location Fees" line (Net Loc Fee already nets out
								to Location Fees when no subsidy applies). Both
								sortable server-side via SQL aliases. -->
							<SingleSortItem modelName="external_subsidize" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('external_subsidize', true)">
								Ext Subsidize
							</SingleSortItem>
							<SingleSortItem modelName="net_loc_fee" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('net_loc_fee', true)">
								Net Loc Fee
							</SingleSortItem>
							<!-- Section divider — splits the contract terms
								(Contract Type / Net Loc Fee) from the L30d
								earnings cluster (GrossEarning / VendEarning).
								Matching <hr> in the TableData. -->
							<hr class="border-t border-gray-300 my-2" />
							<SingleSortItem modelName="totals_json->thirty_days_gross_profit" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('totals_json->thirty_days_gross_profit', true)">
								L30d GrossEarning
							</SingleSortItem>
							<SingleSortItem modelName="thirty_days_vending_earning_cents" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('thirty_days_vending_earning_cents', true)">
								L30d VendEarning
							</SingleSortItem>
							<!-- Section divider — splits the L30d earnings pair
								from the Loc Grading + Customer Note cluster
								below. Matching <hr> in the TableData. -->
							<hr class="border-t border-gray-300 my-2" />
							<div class="flex justify-center items-center">
								<span>
									Loc Grading
								</span>
								<ExclamationCircleIcon class="min-w-5 w-5 h-5 self-center pl-1 text-sky-500" v-tooltip="{ content: '<div class=&quot;text-left&quot;><b>Machine placement &amp; removal</b><br>A = Smooth surface, 1 person can perform<br>B = Smooth surface, need 2 persons to perform<br>C = Not smooth surface, need min 2 persons to perform<br><br><b>Easy access &amp; refill</b><br>A = Low/Free parking and easy access<br>B = Low/Free parking, but need to pre-apply entry<br>C = No proper parking space; need guide to go in<br><br><b>Flexible to terminate/ or replace with another machine later</b><br>A = Less than 1 week<br>B = Less than 2 week<br>C = 2 weeks and above</div>', html: true }"></ExclamationCircleIcon>
							</div>
							<!-- Customer Note — inline-editable textarea renders at
								the bottom of this column's TableData, mirroring the
								Customer Summary "Note" row. Header label hidden on
								the regular /vends path since notes aren't loaded
								for vend rows. -->
							<span v-if="indexType === 'customers'">
								Customer Note
							</span>
						</div>
					</TableHead>
					<TableHead v-if="!roles.includes('operator_driver')">
						Machine Status
					</TableHead>
					<TableHead v-if="!roles.includes('operator_driver')">
						Payment Device
					</TableHead>
					<TableHead v-if="!roles.includes('operator_driver')">
						<div class="flex flex-col space-y-2">
							<SingleSortItem modelName="operator_code" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('operator_code')">
								Operator
							</SingleSortItem>
							<SingleSortItem modelName="account_manager_name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('account_manager_name')">
								Acc Manager
							</SingleSortItem>
							<SingleSortItem modelName="location_type_name" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('location_type_name')">
								Location
							</SingleSortItem>
							<!-- Section divider — splits the operator/location
								cluster (Operator / Acc Manager / Location) from
								the hardware-version cluster below (VMC / Firmware
								/ Android / APK / ACB). Matching <hr> in the
								TableData. -->
							<hr class="border-t border-gray-300 my-2" />
							<span>
								VMC Board
							</span>
							<span class="text-blue-600">
								Firmware Rev
							</span>
							<span>
								Android Board
							</span>
							<span>
								APK Ver
							</span>
							<span class="text-green-600">
								ACB Rev
							</span>
						</div>
					</TableHead>
				</tr>
			</thead>
			<tbody class="bg-white">
				<tr v-for="(vend, vendIndex) in vends.data" :key="vendIndex"
					class="divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100">
					<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center" v-if="isShowOperationDiv">
						<input type="checkbox" v-model="vend.is_selected" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-600">
					</TableData>
					<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
						{{ vends.meta.from + vendIndex }}
					</TableData>
					<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center" v-if="indexType !== 'customers'">
						<div class="flex flex-col space-y-2 items-center">
							<Link :href="'/settings/vend/' + vend.vend_id + '/update'" :class="[vend.is_active || vend.is_testing ? 'text-blue-600' : 'text-gray-400']">
							{{ vend.code }}
							</Link>
							<div
								class="inline-flex rounded px-0.5 py-0.5 text-xs border w-fit bg-yellow-100 text-yellow-800 border-yellow-300 max-w-48"
								v-if="vend.label_name"
							>
								{{ vend.label_name }}
							</div>
						</div>
					</TableData>
					<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-left">
						<div class="flex flex-col space-y-1">
							<Link :href="'/settings/vend/' + vend.vend_id + '/update'" :class="[vend.is_active || vend.is_testing ? 'text-blue-600' : 'text-gray-400']" class="text-left hover:underline" v-if="permissions.includes('admin-access vend-customers') || permissions.includes('update machine-settings')">
								{{ vend.code }}
							</Link>
							<span v-if="!(permissions.includes('admin-access vend-customers') || permissions.includes('update machine-settings'))">
								{{ vend.code }}
							</span>
							<div class="text-left text-gray-800" v-if="vend.vend_config_name">
								{{ vend.vend_config_name }}
							</div>
							<div class="text-left text-blue-700 cursor-default select-none">
								{{ vend.vend_prefix_name }}
							</div>
							<span class="flex flex-col space-y-0.5" v-if="vend.vend">
								<span class="flex items-center space-x-1">
									<a v-if="vend.vend.productMapping" :href="'/product-mappings/' + vend.vend.productMapping.id + '/edit'" target="_blank" class="text-gray-800 text-xs font-medium underline decoration-gray-400 underline-offset-2">
										{{ vend.vend.productMapping.name }}
									</a>
									<span v-else-if="vend.product_mapping_name" class="text-xs text-gray-800">
										{{ vend.product_mapping_name }}
									</span>
									<!-- "New" badge: machine has an upcoming new mapping. Tooltip shows what it's changing to. -->
									<span
										v-if="getUpcomingMappingName(vend.vend)"
										class="inline-flex items-center rounded px-1 py-0.5 text-[10px] font-semibold border w-fit bg-indigo-100 text-indigo-800 border-indigo-400 leading-none"
										:title="'Upcoming new mapping: ' + getUpcomingMappingName(vend.vend)"
									>
										New
									</span>
								</span>

							</span>
							<span v-if="vend.person_id" class="flex flex-col">
								<span v-if="permissions.includes('admin-access vend-customers')">
									<a :class="[vend.person_id && vend.customer_is_active || vend.is_testing ? 'text-blue-700' : 'text-gray-400']" class="hover:underline" target="_blank" :href="'/customers/' + vend.customer_id + '/edit'">
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
								<span v-if="vend && vend.vend" v-for="(deliveryProductMappingVend, index) in vend.vend.deliveryProductMappingVends">
									<div
											class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border w-fit text-gray-800 bg-green-400"
											v-if="deliveryProductMappingVend.deliveryProductMapping && deliveryProductMappingVend.deliveryProductMapping.deliveryPlatformOperator && deliveryProductMappingVend.deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform"
									>
										{{ deliveryProductMappingVend.deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform.name }}
									</div>
								</span>
							</span>
							<span v-else-if="!vend.person_id">
								<span v-if="permissions.includes('admin-access vend-customers')" :class="[vend.customer_is_active || vend.is_testing ? 'text-gray-800' : 'text-gray-400']">
									<a class="text-blue-700 hover:underline" target="_blank" :href="'/customers/' + vend.customer_id + '/edit'">
											{{ vend.customer_name }}
									</a>
								</span>
								<span v-else :class="[vend.customer_is_active || vend.is_testing ? 'text-gray-800' : 'text-gray-400']">
									<a class="text-blue-700 hover:underline" target="_blank" :href="'/customers/' + vend.customer_id + '/edit'">
									{{ vend.customer_name }}
									</a>
								</span>
							</span>
							<div
								class="inline-flex rounded px-0.5 py-0.5 text-xs border w-fit bg-gray-100 text-gray-800 border-gray-300"
								v-if="vend.postcode"
							>
								{{ vend.postcode }}
							</div>
							<div
								class="inline-flex rounded px-0.5 py-0.5 text-xs border w-fit bg-indigo-100 text-indigo-800 border-indigo-300"
								v-if="vend.selling_price_type"
							>
								RP{{ vend.selling_price_type }}
							</div>
							<span class="flex space-x-1 items-center">
								<span>
									<Button
									type="button" class="bg-sky-300 hover:bg-sky-400 px-3 py-1 text-xs text-sky-800 flex space-x-1 w-fit"
									@click="onMapMarkerClicked(vend)"
									v-if="vend.deliveryAddress && vend.deliveryAddress.latitude && vend.deliveryAddress.longitude"
									>
										<MapPinIcon class="h-3 w-3" aria-hidden="true"/>
									</Button>
								</span>
								<a
									:href="vend.deliveryAddress && vend.deliveryAddress.map_url
										? vend.deliveryAddress.map_url
										: (vend.deliveryAddress && vend.deliveryAddress.latitude && vend.deliveryAddress.longitude
											? 'https://www.google.com/maps/search/?api=1&query=' + vend.deliveryAddress.latitude + ',' + vend.deliveryAddress.longitude
											: '')"
									target="_blank"
									rel="noopener noreferrer"
									type="button"
									class="bg-green-300 hover:bg-green-400 px-3 py-2 text-xs flex space-x-1 w-fit rounded shadow font-bold"
								>
									<span class="text-blue-800 underline">GPS</span>
								</a>
							</span>


              <Link :href="'/vends/' + vend.vend_id + '/edit'" v-if="permissions.includes('admin-access vend-customers')">
                <Button
                type="button" class="bg-blue-300 hover:bg-blue-400 px-3 py-2 text-xs text-gray-800 flex space-x-1"
                >
                <EllipsisHorizontalCircleIcon class="w-4 h-4"></EllipsisHorizontalCircleIcon>
                <span class="text-blue-800 underline">
                    more
                </span>
                </Button>
              </Link>
						</div>
					</TableData>
					<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
						<div class="flex flex-col items-center space-y-1">
							<a :href="'/vends/' + vend.vend_id + '/temp/' + 1 " target="_blank" class="w-full">
									<button
									type="button"
									class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-full text-right justify-center"
									:class="[(vend.is_online || vend.is_testing) && vend.is_temp_active ? (vend.temp > -15 ? 'bg-red-400 active:bg-red-500 hover:bg-red-600' : 'bg-green-400 active:bg-green-500 hover:bg-green-600') : 'bg-gray-300 active:bg-gray-500 hover:bg-gray-600']"
									v-if="vend.temp_updated_at"
									>
											<div class="flex items-center justify-between w-full">
												<span class="text-blue-800 underline">{{ vend.is_temp_error ? 'Error' : vend.temp }}</span>
												<CursorArrowRippleIcon class="w-3 h-3 flex-shrink-0 ml-1" />
											</div>
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
											class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-full text-right justify-center"
											:class="[(vend.is_online || vend.is_testing) && vend.is_temp_active ? (vend.temp > -15 || vend.parameterJson['t2'] == constTempError ? 'bg-red-400 active:bg-red-500 hover:bg-red-600' : 'bg-green-400 active:bg-green-500 hover:bg-green-600') : 'bg-gray-300 active:bg-gray-500 hover:bg-gray-600']"
											v-if="vend.parameterJson && 't2' in vend.parameterJson"
									>
											<div class="flex items-center justify-between w-full">
												<span class="text-blue-800 underline">{{ vend.parameterJson['t2'] == constTempError ? 'Error' : vend.parameterJson['t2']/10 }}(t2)</span>
												<CursorArrowRippleIcon class="w-3 h-3 flex-shrink-0 ml-1" />
											</div>
									</button>
							</a>
							<a :href="'/vends/' + vend.vend_id + '/temp/' + 3 " target="_blank" class="w-full">
									<button
											type="button"
											class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-full text-right justify-center"
											:class="[(vend.is_online || vend.is_testing) && vend.is_temp_active ? (vend.temp > -15 || vend.parameterJson['t3'] == constTempError ? 'bg-red-400 active:bg-red-500 hover:bg-red-600' : 'bg-green-400 active:bg-green-500 hover:bg-green-600') : 'bg-gray-300 active:bg-gray-500 hover:bg-gray-600']"
											v-if="vend.parameterJson && vend.parameterJson['t3'] && vend.parameterJson['t3'] != constTempError"
									>
											<div class="flex items-center justify-between w-full">
												<span class="text-blue-800 underline">{{ vend.parameterJson['t3'] == constTempError ? 'Error' : vend.parameterJson['t3']/10 }}(t3)</span>
												<CursorArrowRippleIcon class="w-3 h-3 flex-shrink-0 ml-1" />
											</div>
									</button>
							</a>
							<a :href="'/vends/' + vend.vend_id + '/temp/' + 4 " target="_blank" class="w-full">
									<button
											type="button"
											class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-full text-right justify-center"
											:class="[(vend.is_online || vend.is_testing) && vend.is_temp_active ? (vend.temp > -15 || vend.parameterJson['t4'] == constTempError ? 'bg-red-400 active:bg-red-500 hover:bg-red-600' : 'bg-green-400 active:bg-green-500 hover:bg-green-600') : 'bg-gray-300 active:bg-gray-500 hover:bg-gray-600']"
											v-if="vend.parameterJson && vend.parameterJson['t4'] && vend.parameterJson['t4'] != constTempError"
									>
											<div class="flex items-center justify-between w-full">
												<span class="text-blue-800 underline">{{ vend.parameterJson['t4'] == constTempError ? 'Error' : vend.parameterJson['t4']/10 }}(t4)</span>
												<CursorArrowRippleIcon class="w-3 h-3 flex-shrink-0 ml-1" />
											</div>
									</button>
							</a>
							<span class="mt-1">
									{{ vend.temp_updated_at }}
							</span>
							<span
									class="mt-1 text-xs font-semibold"
									:class="[vend.is_active || vend.is_testing ? (vend.t1_lowest_48h > -18 ? 'text-red-600' : (vend.t1_lowest_48h < -21 ? 'text-green-600' : 'text-blue-600')) : 'text-gray-400' ]"
									v-if="vend.t1_lowest_48h !== null && vend.t1_lowest_48h !== undefined"
							>
									{{ vend.t1_lowest_48h }}
							</span>
							<span
									class="mt-1"
									:class="[vend.is_active || vend.is_testing ? (((vend.temp - vend.parameterJson['t2']/10).toFixed(1) >= 4 || (vend.temp - vend.parameterJson['t2']/10).toFixed(1) <= 0) ? 'text-red-700' : 'text-green-700') : 'text-gray-400' ]"
									v-if="vend.parameterJson && vend.parameterJson['t2'] && vend.parameterJson['t2'] != constTempError && !vend.is_temp_error"
							>
									{{ (vend.temp - vend.parameterJson['t2']/10).toFixed(1) }}
							</span>
							<!-- Fan RPM Section -->
							<div
								v-if="!vend.is_fan_enabled"
								class="flex flex-col items-center justify-center border border-gray-400 rounded-md p-1 min-w-[80px] bg-white text-gray-800"
								v-tooltip="{ content: 'Fan Speed Signal Disabled' }"
							>
								<span class="text-[10px] font-bold">Fan RPM</span>
								<span>N/A</span>
							</div>
							<a
								v-else-if="vend.parameterJson && 'fan' in vend.parameterJson"
								:href="'/vends/' + vend.vend_id + '/temp/' + 1 "
								target="_blank"
								class="w-full mt-1"
							>
								<button
									type="button"
									class="flex flex-col items-center justify-center border border-transparent rounded-md p-1 min-w-[80px] w-full focus:outline-none disabled:opacity-25 transition ease-in-out duration-150"
									:class="[
										(vend.is_online || vend.is_testing)
											? (vend.parameterJson['fan'] !== null && vend.parameterJson['fan'] !== undefined && vend.parameterJson['fan'] !== 'NaN'
												? (vend.parameterJson['fan'] > 0 ? 'bg-green-200 active:bg-green-300 hover:bg-green-300 text-gray-800' : 'bg-red-200 active:bg-red-300 hover:bg-red-300 text-gray-800')
												: 'bg-gray-200 text-gray-500')
											: 'bg-gray-300 text-gray-600'
									]"
									v-tooltip="{ content: 'Fan Speed Signal exists' }"
								>
									<span class="text-[10px] font-bold">Fan RPM</span>
									<div class="flex items-center justify-center w-full">
										<span class="text-blue-800 underline">{{ vend.parameterJson['fan'] }}</span>
										<CursorArrowRippleIcon class="w-3 h-3 flex-shrink-0 ml-1" />
									</div>
								</button>
							</a>
							<a
								v-else
								:href="'/vends/' + vend.vend_id + '/temp/' + 1 "
								target="_blank"
								class="w-full mt-1"
							>
								<button
									type="button"
									class="flex flex-col items-center justify-center border border-transparent rounded-md p-1 min-w-[80px] w-full focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 bg-gray-300 hover:bg-gray-400 active:bg-gray-500 text-gray-600"
									v-tooltip="{ content: 'Fan Speed Signal Missing' }"
								>
									<span class="text-[10px] font-bold">Fan RPM</span>
									<div class="flex items-center justify-center w-full">
										<span class="text-blue-800 underline">--</span>
										<CursorArrowRippleIcon class="w-3 h-3 flex-shrink-0 ml-1" />
									</div>
								</button>
							</a>
								<!-- Consolidated Machine Health Alerts (1, 2, 3) -->
								<div v-if="getMachineAlertsGroup(vend, [1, 2, 3]).length > 0" class="mt-2 w-full flex flex-wrap gap-1 items-center justify-center">
									<span v-for="alert in getMachineAlertsGroup(vend, [1, 2, 3])" :key="alert.type + alert.group"
										class="inline-flex justify-center items-center rounded-md px-1 py-0.5 text-[10px] font-bold border cursor-help shadow-sm min-w-[28px]"
										:class="getAlertClass(alert)"
										v-tooltip="getAlertTooltip(alert)"
									>
										({{ getAlertLabel(alert) }})
									</span>
								</div>
						</div>
					</TableData>
					<!-- class="sm:grid sm:grid-cols-[105px_minmax(110px,_1fr)_100px] hover:cursor-pointer" -->
					<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-left">
						<div class="flex flex-col space-y-2 hover:bg-gray-100 p-2 rounded cursor-pointer transition duration-150 ease-in-out border border-transparent hover:border-gray-200" @click="onChannelOverviewClicked(vend)">
							<div class="flex items-center space-x-1 text-xs text-gray-500 mb-1">
								<TableCellsIcon class="w-4 h-4 text-blue-500" />
								<span class="font-semibold text-blue-600">View Channel Status</span>
							</div>
							<ul
							class="sm:grid sm:grid-cols-[1fr_1fr]"
							v-if="vend && vend.vendChannelsJson"
							>
								<li v-for="(channel, channelIndex) in vend.vendChannelsJson"
										class="quick-look"
										:class="[
											channelIndex > 0 && (String(channel.code)[0] !== String(vend.vendChannelsJson[channelIndex - 1]['code'])[0]) ? 'col-start-1' : '',
											channel.product && !channel.product.is_available ? 'bg-red-200' : '',
											channel.product && channel.product.limit_qty > 0 && !channel.product.limit_is_created_by_system ? 'bg-gray-300' : ''
										]"
								>
									<span :class="[channelIndex > 0 && (String(channel.code)[0] !== String(vend.vendChannelsJson[channelIndex - 1]['code'])[0]) ? 'border-t-4 pt-1' : '']">
											<span :class="[vend.is_active || vend.is_testing ? compareRefPrice(vend, channel) : 'text-gray-600']">
													#{{channel.code}}
											</span>,
											<span :class="[vend.is_active || vend.is_testing ? 'text-blue-600' : 'text-gray-500']">
													{{channel.capacity - channel.qty}},
											</span>
											<span :class="[vend.is_active || vend.is_testing ? (channel['qty'] <= 2 && channel['qty'] > 0 ? 'text-blue-700' : (channel['qty'] == 0 ? 'text-red-700' : 'text-green-700')) : 'text-gray-400']">
													{{channel.qty}}/{{channel.capacity}}
											</span>
											<span class="text-gray-500">
													({{channel.last_stock_in_qty}})
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
								<span>
									<div
										class="text-gray-800"
									>
										Full Load Value: {{ operatorCountry.currency_symbol }}{{ vend.total_full_load_amount ? vend.total_full_load_amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : 0 }}
									</div>
								</span>
								<!-- Smart Alerts (Stockout - Hidden per user request for only 1-5) -->
							</div>
						</div>
					</TableData>
					<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
						<div class="flex flex-col space-y-2">
							<span v-for="vendChannelErrorLog in vend.vendChannelErrorLogsJson" :key="vendChannelErrorLog.id" class="inline-flex items-center rounded px-2.5 py-0.5 text-xs font-medium border"
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
							v-if="vend.vendTransactionTotalsJson && 'one_day_error_rate' in vend.vendTransactionTotalsJson"
							:class="[
									vend.is_active || vend.is_testing ?
									(vend.vendTransactionTotalsJson['one_day_error_rate'] >= 3 ? 'text-red-700' : 'text-green-700') :
									'text-gray-400'
							]">
									{{vend.vendTransactionTotalsJson['one_day_error_rate'].toLocaleString(undefined, {minimumFractionDigits: 1, maximumFractionDigits: 1})}}%
									({{vend.vendTransactionTotalsJson['one_day_error_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}}/{{vend.vendTransactionTotalsJson['one_day_all_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}})
							</span>
							<span
							v-if="vend.vendTransactionTotalsJson && 'two_days_error_rate' in vend.vendTransactionTotalsJson"
							:class="[
									vend.is_active || vend.is_testing ?
									(vend.vendTransactionTotalsJson['two_days_error_rate'] >= 3 ? 'text-red-700' : 'text-green-700') :
									'text-gray-400'
							]">
									{{vend.vendTransactionTotalsJson['two_days_error_rate'].toLocaleString(undefined, {minimumFractionDigits: 1, maximumFractionDigits: 1})}}%
									({{vend.vendTransactionTotalsJson['two_days_error_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}}/{{vend.vendTransactionTotalsJson['two_days_all_count'].toLocaleString(undefined, {minimumFractionDigits: 0})}})
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
							<!-- PWRON 1d/2d/3d trend (counts from vend_daily_stats).
							     Coloring (inactive machines stay gray):
							       - 1d: red if 1d > 2d, green if 1d < 2d, black if equal
							       - 2d: red if 2d > 3d, green if 2d < 3d, black if equal
							       - 3d: always black (baseline for comparison)
							     v-if guards the block when the controller hasn't
							     attached the counts (e.g. older /vends path). -->
							<template v-if="vend.pwron_1d_count !== null && vend.pwron_1d_count !== undefined">
								<hr class="border-t border-gray-300 my-2 w-full" />
								<div class="flex justify-center items-center space-x-1 text-sm">
									<span
										:class="
											(vend.is_active || vend.is_testing) ?
											(
												vend.pwron_1d_count > vend.pwron_2d_count ? 'text-red-700' :
												(vend.pwron_1d_count < vend.pwron_2d_count ? 'text-green-700' : 'text-gray-900')
											) :
											'text-gray-400'
										"
									>
										{{ vend.pwron_1d_count }}
									</span>
									<span class="text-gray-400">/</span>
									<span
										:class="
											(vend.is_active || vend.is_testing) ?
											(
												vend.pwron_2d_count > vend.pwron_3d_count ? 'text-red-700' :
												(vend.pwron_2d_count < vend.pwron_3d_count ? 'text-green-700' : 'text-gray-900')
											) :
											'text-gray-400'
										"
									>
										{{ vend.pwron_2d_count }}
									</span>
									<span class="text-gray-400">/</span>
									<span :class="(vend.is_active || vend.is_testing) ? 'text-gray-900' : 'text-gray-400'">
										{{ vend.pwron_3d_count }}
									</span>
								</div>
							</template>
							<!-- "# of No Found in Txn" 1d/2d/3d (counts from vend_daily_stats
							     metric=nofound_txn). Coloring rule mirrors the PWRON block
							     directly above so the two trend lines read consistently:
							       - 1d: red if 1d > 2d, green if 1d < 2d, black if equal
							       - 2d: red if 2d > 3d, green if 2d < 3d, black if equal
							       - 3d: always black (baseline)
							     Inactive machines stay gray (matches PWRON). Block is
							     hidden when the controller hasn't enriched the counts. -->
							<template v-if="vend.nofound_txn_1d_count !== null && vend.nofound_txn_1d_count !== undefined">
								<hr class="border-t border-gray-300 my-2 w-full" />
								<div class="flex justify-center items-center space-x-1 text-sm">
									<span
										:class="
											(vend.is_active || vend.is_testing) ?
											(
												vend.nofound_txn_1d_count > vend.nofound_txn_2d_count ? 'text-red-700' :
												(vend.nofound_txn_1d_count < vend.nofound_txn_2d_count ? 'text-green-700' : 'text-gray-900')
											) :
											'text-gray-400'
										"
									>
										{{ vend.nofound_txn_1d_count }}
									</span>
									<span class="text-gray-400">/</span>
									<span
										:class="
											(vend.is_active || vend.is_testing) ?
											(
												vend.nofound_txn_2d_count > vend.nofound_txn_3d_count ? 'text-red-700' :
												(vend.nofound_txn_2d_count < vend.nofound_txn_3d_count ? 'text-green-700' : 'text-gray-900')
											) :
											'text-gray-400'
										"
									>
										{{ vend.nofound_txn_2d_count }}
									</span>
									<span class="text-gray-400">/</span>
									<span :class="(vend.is_active || vend.is_testing) ? 'text-gray-900' : 'text-gray-400'">
										{{ vend.nofound_txn_3d_count }}
									</span>
								</div>
							</template>
						</div>
						<!-- Machine Health Alerts (5) removed per user request — redundant with vendChannelErrorLogs shown above in the same column -->
					</TableData>
					<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center" v-if="!roles.includes('operator_driver')">
						<div class="flex flex-col space-y-2">
							<!-- Spacer mirroring the header's "Stock" section label so the
								 border-b divider below aligns vertically with the header's
								 divider (header has 3 rows above / 3 below; data needs the
								 same). Without this, the divider drifts upward. -->
							<span>&nbsp;</span>
							<span
									v-if="vend.vendChannelTotalsJson"
									:class="[vend.is_active || vend.is_testing ? (vend.balance_percent <= 20 ? 'text-red-700' : (vend.balance_percent > 50 ? 'text-green-700' : 'text-blue-700')) : 'text-gray-400']"
							>
									{{ vend.vendChannelTotalsJson['qty'] }}/ {{ vend.vendChannelTotalsJson['capacity'] }} <br>
									({{ vend.balance_percent }}%)
							</span>

							<div class="flex justify-center border-b border-gray-300 pb-2 mb-2 w-full">
								<span
									v-if="vend.vendChannelTotalsJson"
									:class="[vend.is_active || vend.is_testing ? (100 - vend.out_of_stock_sku_percent <= 40 ? 'text-red-700' : (100 - vend.out_of_stock_sku_percent > 70 ? 'text-green-700' : 'text-blue-700')) : 'text-gray-400']"
								>
										{{ vend.vendChannelTotalsJson['count'] - vend.vendChannelTotalsJson['outOfStockSku'] }}/ {{ vend.vendChannelTotalsJson['count'] }} <br>
										({{ 100 - vend.out_of_stock_sku_percent }}%)
								</span>
							</div>
							<span>&nbsp;</span>
							<span :class="[vend.actual_stock_in_value < 100 ? 'text-red-500' : 'text-gray-800']" v-if="vend.actual_stock_in_value">
								{{ operatorCountry.currency_symbol }}{{(vend.actual_stock_in_value ? vend.actual_stock_in_value : 0).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
							</span>
							<span :class="[vend.actual_stock_in_value < 100 ? 'text-red-500' : 'text-gray-800']" v-if="vend.actual_stock_in_value">
								{{vend.actual_stock_in_qty ? vend.actual_stock_in_qty.toLocaleString(undefined, {minimumFractionDigits: 0}) : 0}}
							</span>
						</div>
					</TableData>
					<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center" v-if="!roles.includes('operator_driver')">
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
						<!-- Machine Health Alerts (4) — sits between Last30d and the
							Mthly Sales $ block (above the hr/border) per ops request,
							so the alert chips read as part of the rolling totals
							summary rather than under the calendar-month section. -->
						<div v-if="getMachineAlertsGroup(vend, [4]).length > 0" class="mt-2 w-full flex flex-wrap gap-1 items-center justify-center">
							<span v-for="alert in getMachineAlertsGroup(vend, [4])" :key="alert.type + alert.group"
								class="inline-flex justify-center items-center rounded-md px-1 py-0.5 text-[10px] font-bold border cursor-help shadow-sm min-w-[28px]"
								:class="getAlertClass(alert)"
								v-tooltip="getAlertTooltip(alert)"
							>
								({{ getAlertLabel(alert) }})
							</span>
						</div>
						<!-- Mthly Sales $ — calendar-month split (Current / Last / Last 2 / Last 3).
							Amount only (no qty) per ops request; current month already
							includes today via SyncVendTransactionTotalsJson. Each row
							shows a heroicon arrow chip comparing it to the previous
							month (green up = exceeded; red down = below). Last 3 Mth
							is the baseline so it carries no chip. Last 3 row is rendered
							only when last_3_mth_amount exists in the JSON, which handles
							rows synced before the column was added. -->
						<div
							v-if="vend.vendTransactionTotalsJson && 'current_mth_amount' in vend.vendTransactionTotalsJson"
							class="mt-1 pt-1 border-t border-gray-300 flex flex-col items-center gap-0.5"
						>
							<div class="flex items-center justify-center gap-1">
								<span :class="[vend.is_active || vend.is_testing ? 'text-gray-800' : 'text-gray-400']">
									{{ operatorCountry.currency_symbol }}{{(vend.vendTransactionTotalsJson['current_mth_amount']/ (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
								</span>
								<span
									v-if="vend.vendTransactionTotalsJson['current_mth_amount'] > vend.vendTransactionTotalsJson['last_mth_amount']"
									class="inline-flex items-center justify-center"
									v-tooltip="'Higher than last month'"
								>
									<ArrowUpIcon class="h-4 w-4 text-green-600" stroke="currentColor" stroke-width="1.25" stroke-linejoin="round" aria-hidden="true" />
								</span>
								<span
									v-else-if="vend.vendTransactionTotalsJson['current_mth_amount'] < vend.vendTransactionTotalsJson['last_mth_amount']"
									class="inline-flex items-center justify-center"
									v-tooltip="'Lower than last month'"
								>
									<ArrowDownIcon class="h-4 w-4 text-red-600" stroke="currentColor" stroke-width="1.25" stroke-linejoin="round" aria-hidden="true" />
								</span>
							</div>
							<div class="flex items-center justify-center gap-1">
								<span :class="[vend.is_active || vend.is_testing ? 'text-gray-800' : 'text-gray-400']">
									{{ operatorCountry.currency_symbol }}{{(vend.vendTransactionTotalsJson['last_mth_amount']/ (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
								</span>
								<span
									v-if="vend.vendTransactionTotalsJson['last_mth_amount'] > vend.vendTransactionTotalsJson['last_2_mth_amount']"
									class="inline-flex items-center justify-center"
									v-tooltip="'Higher than 2 months ago'"
								>
									<ArrowUpIcon class="h-4 w-4 text-green-600" stroke="currentColor" stroke-width="1.25" stroke-linejoin="round" aria-hidden="true" />
								</span>
								<span
									v-else-if="vend.vendTransactionTotalsJson['last_mth_amount'] < vend.vendTransactionTotalsJson['last_2_mth_amount']"
									class="inline-flex items-center justify-center"
									v-tooltip="'Lower than 2 months ago'"
								>
									<ArrowDownIcon class="h-4 w-4 text-red-600" stroke="currentColor" stroke-width="1.25" stroke-linejoin="round" aria-hidden="true" />
								</span>
							</div>
							<div class="flex items-center justify-center gap-1">
								<span :class="[vend.is_active || vend.is_testing ? 'text-gray-800' : 'text-gray-400']">
									{{ operatorCountry.currency_symbol }}{{(vend.vendTransactionTotalsJson['last_2_mth_amount']/ (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
								</span>
								<span
									v-if="'last_3_mth_amount' in vend.vendTransactionTotalsJson && vend.vendTransactionTotalsJson['last_2_mth_amount'] > vend.vendTransactionTotalsJson['last_3_mth_amount']"
									class="inline-flex items-center justify-center"
									v-tooltip="'Higher than 3 months ago'"
								>
									<ArrowUpIcon class="h-4 w-4 text-green-600" stroke="currentColor" stroke-width="1.25" stroke-linejoin="round" aria-hidden="true" />
								</span>
								<span
									v-else-if="'last_3_mth_amount' in vend.vendTransactionTotalsJson && vend.vendTransactionTotalsJson['last_2_mth_amount'] < vend.vendTransactionTotalsJson['last_3_mth_amount']"
									class="inline-flex items-center justify-center"
									v-tooltip="'Lower than 3 months ago'"
								>
									<ArrowDownIcon class="h-4 w-4 text-red-600" stroke="currentColor" stroke-width="1.25" stroke-linejoin="round" aria-hidden="true" />
								</span>
							</div>
							<div class="flex items-center justify-center" v-if="'last_3_mth_amount' in vend.vendTransactionTotalsJson">
								<span :class="[vend.is_active || vend.is_testing ? 'text-gray-800' : 'text-gray-400']">
									{{ operatorCountry.currency_symbol }}{{(vend.vendTransactionTotalsJson['last_3_mth_amount']/ (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
								</span>
							</div>
						</div>
					</TableData>
					<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center" v-if="indexType == 'customers' && !roles.includes('operator_driver')">
						<div class="flex flex-col space-y-1">
							<div v-if="vend && vend.lastOpsJobItem" class="flex flex-col space-y-1">
								<a :href="'/ops-jobs/items/' + vend.lastOpsJobItem.id + '/edit'">
									<div
										class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full text-gray-900 bg-indigo-300"
									>
										<span class="text-blue-800 underline">
											{{ vend.lastOpsJobItem.ref_id }}
										</span>
									</div>
								</a>
								<span>
									{{ vend.lastOpsJobItem.opsJob.deliveredBy.name }}
								</span>
								<span class="flex flex-col space-y-1">
									<span>
										{{ vend.lastOpsJobItem.opsJob.date_formatted }}
									</span>
									<div
										class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full text-gray-900"
										:class="[(vend.lastOpsJobItem.opsJob.date_diff_count < 1 && vend.lastOpsJobItem.opsJob.date_diff_count > 0) ? 'bg-green-200' : ((vend.lastOpsJobItem.opsJob.date_diff_count > -1 && vend.lastOpsJobItem.opsJob.date_diff_count < 0) ? 'bg-yellow-200' : vend.lastOpsJobItem.opsJob.date_diff_count > 10 ? 'bg-red-300' : '') ]"
										v-if="vend.lastOpsJobItem.opsJob.date_diff_human"
									>
										<span>
											{{ vend.lastOpsJobItem.opsJob.date_diff_human }}
										</span>
									</div>
								</span>
								<span class="flex flex-col space-y-1"
									v-if="vend.lastOpsJobItem.status >= 3"
									:class="[vend.lastOpsJobItem.status == 4 ? 'text-green-700' : (vend.lastOpsJobItem.status == 98 ? 'text-red-700' : '')]"
								>
									<span>
										{{ operatorCountry.currency_symbol }}{{ vend.last_ops_job_amount ? vend.last_ops_job_amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : 0 }}
									</span>
									<span>
										{{ vend.last_ops_job_count ? vend.last_ops_job_count.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : 0 }}
									</span>
								</span>
								<span>
									{{ operatorCountry.currency_symbol }}{{ vend.last_ops_job_acc_total_amount ? vend.last_ops_job_acc_total_amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : 0 }} ({{ vend.last_ops_job_acc_total_count ? vend.last_ops_job_acc_total_count.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0}) : 0 }})
								</span>
								<!-- Stock Action Type Badges -->
								<div
									v-if="vend.lastOpsJobItem.stock_action_type === 'implement_new_mapping'"
									class="flex flex-col items-center mt-1 space-y-0.5"
								>
									<span class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-bold border w-full bg-purple-100 text-purple-700 border-purple-300">
										Implement New Mapping
									</span>
									<span class="text-[10px] text-purple-600 font-medium leading-tight text-center" v-if="vend.lastOpsJobItem.vend && (vend.lastOpsJobItem.vend.upcomingProductMapping || (vend.lastOpsJobItem.vend.productMapping && vend.lastOpsJobItem.vend.productMapping.upcomingProductMapping))">
										{{ vend.lastOpsJobItem.vend.productMapping ? vend.lastOpsJobItem.vend.productMapping.name : '' }}
										<span v-if="(vend.lastOpsJobItem.vend.productMapping && vend.lastOpsJobItem.vend.productMapping.upcomingProductMapping && vend.lastOpsJobItem.vend.productMapping.upcomingProductMapping.name !== 'N/A') || (vend.lastOpsJobItem.vend.upcomingProductMapping && vend.lastOpsJobItem.vend.upcomingProductMapping.name !== 'N/A')">
											&RightArrow;
											{{ (vend.lastOpsJobItem.vend.productMapping && vend.lastOpsJobItem.vend.productMapping.upcomingProductMapping && vend.lastOpsJobItem.vend.productMapping.upcomingProductMapping.name !== 'N/A') ? vend.lastOpsJobItem.vend.productMapping.upcomingProductMapping.name : (vend.lastOpsJobItem.vend.upcomingProductMapping && vend.lastOpsJobItem.vend.upcomingProductMapping.name !== 'N/A' ? vend.lastOpsJobItem.vend.upcomingProductMapping.name : '') }}
										</span>
									</span>
								</div>
								<div
									v-else-if="vend.lastOpsJobItem.stock_action_type === 'return_stock'"
									class="mt-1"
								>
									<span class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-bold border w-full bg-orange-100 text-orange-700 border-orange-300">
										Return Stock
									</span>
								</div>
							</div>
							<!-- Divider between Last Job and Last 2 Job -->
							<div v-if="vend && vend.lastSecondOpsJobItem" class="border-t border-gray-300 my-2 pt-2"></div>
							<div v-if="vend && vend.lastSecondOpsJobItem" class="flex flex-col space-y-1">
								<a :href="'/ops-jobs/items/' + vend.lastSecondOpsJobItem.id + '/edit'">
									<div
										class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full text-gray-900 bg-indigo-300"
									>
										<span class="text-blue-800 underline">
											{{ vend.lastSecondOpsJobItem.ref_id }}
										</span>
									</div>
								</a>
								<span>
									{{ vend.lastSecondOpsJobItem.opsJob.deliveredBy.name }}
								</span>
								<span class="flex flex-col space-y-1">
									<span>
										{{ vend.lastSecondOpsJobItem.opsJob.date_formatted }}
									</span>
									<div
										class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full text-gray-900"
										:class="[(vend.lastSecondOpsJobItem.opsJob.date_diff_count < 1 && vend.lastSecondOpsJobItem.opsJob.date_diff_count > 0) ? 'bg-green-200' : ((vend.lastSecondOpsJobItem.opsJob.date_diff_count > -1 && vend.lastSecondOpsJobItem.opsJob.date_diff_count < 0) ? 'bg-yellow-200' : vend.lastSecondOpsJobItem.opsJob.date_diff_count > 10 ? 'bg-red-300' : '') ]"
										v-if="vend.lastSecondOpsJobItem.opsJob.date_diff_human"
									>
										<span>
											{{ vend.lastSecondOpsJobItem.opsJob.date_diff_human }}
										</span>
									</div>
								</span>
								<span class="flex flex-col space-y-1"
									v-if="vend.lastSecondOpsJobItem.status >= 3"
									:class="[vend.lastSecondOpsJobItem.status == 4 ? 'text-green-700' : (vend.lastSecondOpsJobItem.status == 98 ? 'text-red-700' : '')]"
								>
									<span>
										{{ operatorCountry.currency_symbol }}{{ vend.last_second_ops_job_amount ? vend.last_second_ops_job_amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : 0 }}
									</span>
									<span>
										{{ vend.last_second_ops_job_count ? vend.last_second_ops_job_count.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : 0 }}
									</span>
								</span>
								<span>
									{{ operatorCountry.currency_symbol }}{{ vend.last_second_ops_job_acc_total_amount ? vend.last_second_ops_job_acc_total_amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : 0 }} ({{ vend.last_ops_job_acc_total_count ? vend.last_second_ops_job_acc_total_count.toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0}) : 0 }})
								</span>
								<!-- Stock Action Type Badges -->
								<div
									v-if="vend.lastSecondOpsJobItem.stock_action_type === 'implement_new_mapping'"
									class="flex flex-col items-center mt-1 space-y-0.5"
								>
									<span class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-bold border w-full bg-purple-100 text-purple-700 border-purple-300">
										Implement New Mapping
									</span>
									<span class="text-[10px] text-purple-600 font-medium leading-tight text-center" v-if="vend.lastSecondOpsJobItem.vend && (vend.lastSecondOpsJobItem.vend.upcomingProductMapping || (vend.lastSecondOpsJobItem.vend.productMapping && vend.lastSecondOpsJobItem.vend.productMapping.upcomingProductMapping))">
										{{ vend.lastSecondOpsJobItem.vend.productMapping ? vend.lastSecondOpsJobItem.vend.productMapping.name : '' }}
										<span v-if="(vend.lastSecondOpsJobItem.vend.productMapping && vend.lastSecondOpsJobItem.vend.productMapping.upcomingProductMapping && vend.lastSecondOpsJobItem.vend.productMapping.upcomingProductMapping.name !== 'N/A') || (vend.lastSecondOpsJobItem.vend.upcomingProductMapping && vend.lastSecondOpsJobItem.vend.upcomingProductMapping.name !== 'N/A')">
											&RightArrow;
											{{ (vend.lastSecondOpsJobItem.vend.productMapping && vend.lastSecondOpsJobItem.vend.productMapping.upcomingProductMapping && vend.lastSecondOpsJobItem.vend.productMapping.upcomingProductMapping.name !== 'N/A') ? vend.lastSecondOpsJobItem.vend.productMapping.upcomingProductMapping.name : (vend.lastSecondOpsJobItem.vend.upcomingProductMapping && vend.lastSecondOpsJobItem.vend.upcomingProductMapping.name !== 'N/A' ? vend.lastSecondOpsJobItem.vend.upcomingProductMapping.name : '') }}
										</span>
									</span>
								</div>
								<div
									v-else-if="vend.lastSecondOpsJobItem.stock_action_type === 'return_stock'"
									class="mt-1"
								>
									<span class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-bold border w-full bg-orange-100 text-orange-700 border-orange-300">
										Return Stock
									</span>
								</div>
							</div>
						</div>
					</TableData>
					<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center" v-if="indexType == 'customers' && !roles.includes('operator_driver')">
						<div class="flex flex-col space-y-1 max-w-28 mx-auto">
							<div v-if="vend && vend.nextOpsJobItem" class="flex flex-col space-y-1">
								<span v-if="vend.nextOpsJobItem.sequence && vend.nextOpsJobItem.status < 3" class="font-semibold">
									({{ vend.nextOpsJobItem.sequence }})
								</span>
								<a :href="'/ops-jobs/items/' + vend.nextOpsJobItem.id + '/edit'">
									<div
										class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full text-gray-900 bg-indigo-300"
									>
										<span class="text-blue-800 underline">
											{{ vend.nextOpsJobItem.ref_id }}
										</span>
									</div>
								</a>
								<span>
									{{ vend.nextOpsJobItem.opsJob.deliveredBy.name }}
								</span>
								<span class="flex flex-col space-y-1">
									<span>
										{{ vend.nextOpsJobItem.opsJob.date_formatted }}
									</span>
									<div
										class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full text-gray-900"
										:class="[(vend.nextOpsJobItem.opsJob.date_diff_count < 1 &&  vend.nextOpsJobItem.opsJob.date_diff_count > 0) ? 'bg-green-200' : ((vend.nextOpsJobItem.opsJob.date_diff_count > -1 && vend.nextOpsJobItem.opsJob.date_diff_count < 0) ? 'bg-yellow-200' : '') ]"
										v-if="vend.nextOpsJobItem.opsJob.date_diff_human"
									>
										<span>
											{{ vend.nextOpsJobItem.opsJob.date_diff_human }}
										</span>
									</div>
								</span>
								<span class="flex flex-col space-y-1" v-if="vend.nextOpsJobItem.status == 2 && (vend.next_ops_job_amount > 0 || vend.next_ops_job_count > 0)"
									:class="[vend.nextOpsJobItem.status == 4 ? 'text-green-700' : (vend.nextOpsJobItem.status == 98 ? 'text-red-700' : '')]">
									<span>
										{{ operatorCountry.currency_symbol }}{{ vend.next_ops_job_amount ? vend.next_ops_job_amount.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : 0 }}
									</span>
									<span>
										{{ vend.next_ops_job_count ? vend.next_ops_job_count.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2}) : 0 }}
									</span>
								</span>
								<div class="max-w-24 text-left bg-gray-100 px-1 py-1 rounded break-words shadow text-xs mt-1" v-if="vend.nextOpsJobItem.remarks">
									{{ vend.nextOpsJobItem.remarks }}
								</div>
								<!-- Stock Action Type Badges -->
								<div
									v-if="vend.nextOpsJobItem.stock_action_type === 'implement_new_mapping'"
									class="flex flex-col items-center mt-1 space-y-0.5"
								>
									<span class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-bold border w-full bg-purple-100 text-purple-700 border-purple-300">
										Implement New Mapping
									</span>
									<span class="text-[10px] text-purple-600 font-medium leading-tight text-center" v-if="vend.nextOpsJobItem.vend && (vend.nextOpsJobItem.vend.upcomingProductMapping || (vend.nextOpsJobItem.vend.productMapping && vend.nextOpsJobItem.vend.productMapping.upcomingProductMapping))">
										{{ vend.nextOpsJobItem.vend.productMapping ? vend.nextOpsJobItem.vend.productMapping.name : '' }}
										<span v-if="(vend.nextOpsJobItem.vend.productMapping && vend.nextOpsJobItem.vend.productMapping.upcomingProductMapping && vend.nextOpsJobItem.vend.productMapping.upcomingProductMapping.name !== 'N/A') || (vend.nextOpsJobItem.vend.upcomingProductMapping && vend.nextOpsJobItem.vend.upcomingProductMapping.name !== 'N/A')">
											&RightArrow;
											{{ (vend.nextOpsJobItem.vend.productMapping && vend.nextOpsJobItem.vend.productMapping.upcomingProductMapping && vend.nextOpsJobItem.vend.productMapping.upcomingProductMapping.name !== 'N/A') ? vend.nextOpsJobItem.vend.productMapping.upcomingProductMapping.name : (vend.nextOpsJobItem.vend.upcomingProductMapping && vend.nextOpsJobItem.vend.upcomingProductMapping.name !== 'N/A' ? vend.nextOpsJobItem.vend.upcomingProductMapping.name : '') }}
										</span>
									</span>
								</div>
								<div
									v-else-if="vend.nextOpsJobItem.stock_action_type === 'return_stock'"
									class="mt-1"
								>
									<span class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-bold border w-full bg-orange-100 text-orange-700 border-orange-300">
										Return Stock
									</span>
								</div>
							</div>
						</div>
					</TableData>
					<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center" v-if="indexType === 'customers' && !roles.includes('operator_driver')">
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
								<span>
									{{ vend.frequency_per_week_status_name }}
								</span>
								<!--
									Ops Note — inline-editable textarea matching the
									Customer Note pattern. Edits POST to
									/customers/{id}/update-ops-note and the page only
									reloads the `vends` prop so filters / scroll are
									preserved. The audit line beneath shows who last
									saved it and when, mirroring the Customer Note
									footer for consistency. Gated on indexType ===
									'customers' because ops_note + its audit columns
									are only selected on the customers-index path.
								-->
								<!--
									w-28 (7rem) keeps the Ops Note textarea narrow
									so it lines up with the Sat / 1 Time markers
									above it in the Refilling Routes column. We
									intentionally drop the previous min-w-[180px]
									(which made the textarea push the column wide)
									but KEEP the rows=4 + autoGrowTextarea pair so
									the height can still grow with content — that's
									what "height remain" is referring to.
								-->
								<div v-if="indexType === 'customers'" class="mt-1 flex flex-col w-[82px]">
									<textarea
										v-model="vend.ops_note"
										@change="onOpsNoteChanged(vend)"
										@input="autoGrowTextarea($event.target)"
										:ref="(el) => autoGrowTextarea(el)"
										rows="4"
										class="text-xs text-gray-700 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-1 block w-full text-left resize-none overflow-hidden"
										placeholder="Ops Note"
									></textarea>
									<span class="text-[10px] text-gray-500 mt-1" v-if="vend.ops_note_updated_by_user">
										{{ vend.ops_note_updated_by_user.name }} ({{ moment(vend.ops_note_updated_at).format('YYMMDD hh:mma') }})
									</span>
								</div>
							</div>
						</span>
					</TableData>
					<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center" v-if="!roles.includes('operator_driver')">
						<div class="flex flex-col space-y-2">
							<span
							v-if="vend.vendTransactionTotalsJson && 'vend_records_amount_latest' in vend.vendTransactionTotalsJson"
							:class="vend.is_active || vend.is_testing ? 'text-gray-900' : 'text-gray-400'"
							>
								{{ operatorCountry.currency_symbol }}{{(vend.vendTransactionTotalsJson['vend_records_amount_latest'] / (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0})}}
							</span>
							<!-- Accumulated VendingEarning — moved here from the Contract Type column.
								Formatted without decimals to match the Lifetime Sales row directly above it. -->
							<span v-if="vend.accumulate_vending_earning_cents != null" :class="vend.accumulate_vending_earning_cents >= 0 ? 'text-emerald-700 font-medium' : 'text-red-700 font-medium'">
								{{ operatorCountry.currency_symbol }}{{ (vend.accumulate_vending_earning_cents / Math.pow(10, operatorCountry.currency_exponent)).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0}) }}
							</span>
							<!-- Section divider — mirrors the <hr> in the TableHead
								between Accumulated VendEarning and Begin Dt so the
								eye reads the contract/date cluster as its own group. -->
							<hr class="border-t border-gray-300 my-2" />
							<span
								v-if="vend.begin_date"
								:class="vend.is_active || vend.is_testing ? 'text-gray-900' : 'text-gray-400'"
							>
								{{ vend.begin_date_short }}
							</span>
							<!-- Contract End Date (yymmdd) — moved here from the
								Contract Type column so it sits directly under
								Begin Dt. The auto-renewal icon (green check /
								red cross) travels with it, with an invisible
								left-side spacer to keep the date itself optically
								centered. -->
							<span v-if="vend.contract_until_short" class="text-gray-800 inline-flex items-center justify-center gap-1">
								<span
									v-if="vend.contract_auto_renewal === true || vend.contract_auto_renewal === false"
									class="h-4 w-4 inline-block"
									aria-hidden="true"
								></span>
								<span>{{ vend.contract_until_short }}</span>
								<CheckCircleIcon
									v-if="vend.contract_auto_renewal === true"
									class="h-4 w-4 text-green-600 shrink-0"
									aria-hidden="true"
									v-tooltip="'Auto Renewal: Yes'"
								/>
								<XCircleIcon
									v-else-if="vend.contract_auto_renewal === false"
									class="h-4 w-4 text-red-600 shrink-0"
									aria-hidden="true"
									v-tooltip="'Auto Renewal: No'"
								/>
							</span>
							<!-- Notice Period — free-text string column (e.g.
								"1 mth"). Renders directly under Contract End Date
								to mirror the header order. Em-dash placeholder when
								the field is blank keeps the column visually
								consistent across rows. -->
							<span
								v-if="vend.contract_notice_period"
								class="text-gray-900"
							>
								{{ vend.contract_notice_period }}
							</span>
							<span v-else class="text-gray-400">—</span>
							<!-- Section divider — mirrors the <hr> in the TableHead
								between Notice Period and Avg Sales/Day. -->
							<hr class="border-t border-gray-300 my-2" />
							<span
							v-if="vend.vendTransactionTotalsJson && 'vend_records_amount_average_day' in vend.vendTransactionTotalsJson"
							:class="[ vend.is_active || vend.is_testing ? getVendRecordsAmountAverageDayClass(vend.vendTransactionTotalsJson['vend_records_amount_average_day']) : 'text-gray-400']"
							>
								{{ operatorCountry.currency_symbol }}{{(vend.vendTransactionTotalsJson['vend_records_amount_average_day'] / (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)})}}
							</span>
							<span :class="[(vend.is_active || vend.is_testing) && vend.vendTransactionTotalsJson && 'vend_records_amount_average_day' in vend.vendTransactionTotalsJson ? (vend.virtual_vend_records_thirty_days_amount_average >= vend.vendTransactionTotalsJson['vend_records_amount_average_day']/100 ? 'text-green-700' : 'text-red-700') : 'text-gray-400']">
									{{ operatorCountry.currency_symbol }}{{ vend.virtual_vend_records_thirty_days_amount_average.toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
							</span>
							<!-- Section divider — mirrors the <hr> in the TableHead
								between AvgDailySales (Last30d) and Customer Tag.
								Gated on indexType so the line only appears when
								Customer Tag chips are being rendered below. -->
							<hr v-if="indexType === 'customers'" class="border-t border-gray-300 my-2" />
							<!-- Customer Tag chips — sit under the column's
								AvgDailySales (Last30d) value to match the column
								header's "Customer Tag" label. Mirrors the Customer
								Summary chip treatment: alternating bg-blue-50 /
								bg-blue-100 with a darker blue-400 border so adjacent
								tags read as distinct chips. break-all wrapping keeps
								long tag names from blowing out the column width. -->
							<div v-if="indexType === 'customers'" class="flex flex-col gap-1 mt-1">
								<span
									v-for="(binding, tagIdx) in (vend.tag_bindings ?? [])"
									:key="binding.id"
									:class="[
										'inline-block w-28 px-2 py-0.5 rounded text-xs font-medium text-blue-900 border border-blue-400 break-words whitespace-normal leading-tight',
										tagIdx % 2 === 0 ? 'bg-blue-50' : 'bg-blue-100',
									]"
								>
									{{ binding.tag?.name }}
								</span>
							</div>
						</div>
					</TableData>
					<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center" v-if="!roles.includes('operator_driver')">
						<div class="flex flex-col space-y-2">
							<!-- Contract Type -->
							<span v-if="vend.contract_commission_type" class="font-semibold text-gray-800">
								{{ contractTypeLabel(vend.contract_commission_type) }}
								<span v-if="vend.contract_commission_value != null" class="block text-[11px] font-normal text-gray-600">
									<span v-if="['PS','PS+U','PSORU'].includes(vend.contract_commission_type)">
										{{ Number(vend.contract_commission_value) }}%<span
											v-if="vend.contract_commission_value2 != null && ['PS+U','PSORU'].includes(vend.contract_commission_type)"
										>+{{ operatorCountry.currency_symbol }}{{ Number(vend.contract_commission_value2) }}</span>
									</span>
									<span v-else>
										{{ operatorCountry.currency_symbol }}{{ Number(vend.contract_commission_value).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 2}) }}
									</span>
								</span>
								<span v-if="vend.contract_ps_term != null" class="block text-[11px] font-normal text-gray-600">
									PS Term: {{ Number(vend.contract_ps_term) }}%
								</span>
							</span>
							<!-- External Subsidize — number only (label lives in the
								header). Live from the customer's current contract
								(Customer/Edit.vue); dash when the toggle is off. -->
							<span class="text-gray-600">
								<template v-if="extSubCents(vend)">{{ fmtCents(extSubCents(vend)) }}</template><template v-else>—</template>
							</span>
							<!-- Net Loc Fee = Location Fees − Ext Subsidize (number
								only, no conditional coloring). Equals Location Fees
								when no subsidy applies. -->
							<span v-if="vend.location_fees_cents != null" class="text-gray-800">
								{{ fmtCents(netLocFeeCents(vend)) }}
							</span>
							<!-- Section divider — mirrors the <hr> in the
								TableHead between Net Loc Fee and L30d
								GrossEarning. -->
							<hr class="border-t border-gray-300 my-2" />
							<!-- L30d GrossEarning -->
							<span
								v-if="vend.vendTransactionTotalsJson && 'thirty_days_gross_profit' in vend.vendTransactionTotalsJson"
								:class="[vend.vendTransactionTotalsJson['thirty_days_gross_profit'] > 0 ? 'text-green-700' : 'text-red-700']"
							>
								{{ operatorCountry.currency_symbol }}{{ (vend.vendTransactionTotalsJson['thirty_days_gross_profit'] / (Math.pow(10, operatorCountry.currency_exponent))).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
							</span>
							<!-- L30d VendingEarning -->
							<span v-if="vend.thirty_days_vending_earning_cents != null" :class="[vend.thirty_days_vending_earning_cents >= 0 ? 'text-green-700' : 'text-red-700', 'text-base font-bold']">
								{{ operatorCountry.currency_symbol }}{{ (vend.thirty_days_vending_earning_cents / Math.pow(10, operatorCountry.currency_exponent)).toLocaleString(undefined, {minimumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent), maximumFractionDigits: (operatorCountry.is_currency_exponent_hidden ? 0 : operatorCountry.currency_exponent)}) }}
							</span>
							<!-- Section divider — mirrors the <hr> in the
								TableHead between L30d VendEarning and Loc Grading. -->
							<hr class="border-t border-gray-300 my-2" />
							<!-- Location Grading -->
							<span
								v-if="vend.location_grading_placement || vend.location_grading_access || vend.location_grading_flexibility"
								class="text-gray-700"
								v-tooltip="{ content: 'Placement / Access / Flexibility', html: true }"
							>
								{{ vend.location_grading_placement || '-' }}, {{ vend.location_grading_access || '-' }}, {{ vend.location_grading_flexibility || '-' }}
							</span>
							<!--
								Customer Note — inline-editable textarea matches the
								Customer Summary "Note" row. Saves to the existing
								/customers/{id}/update-notes endpoint and only reloads
								the `vends` prop so filters / scroll are preserved.
								Gated on indexType === 'customers' because notes
								aren't selected on the regular /vends query.

								w-28 (7rem) keeps this textarea narrow so it stays
								within the Loc Grading column header width, matching
								the Ops Note sizing. rows=4 + autoGrowTextarea are
								preserved so the box still expands with content.
							-->
							<div v-if="indexType === 'customers'" class="mt-2 flex flex-col w-[82px]">
								<textarea
									v-model="vend.notes"
									@change="onNotesChanged(vend)"
									@input="autoGrowTextarea($event.target)"
									:ref="(el) => autoGrowTextarea(el)"
									rows="4"
									class="text-xs text-gray-700 border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 p-1 block w-full text-left resize-none overflow-hidden"
									placeholder="Cust Notes"
								></textarea>
								<span class="text-[10px] text-gray-500 mt-1" v-if="vend.notes_updated_by_user">
									{{ vend.notes_updated_by_user.name }} ({{ moment(vend.notes_updated_at).format('YYMMDD hh:mma') }})
								</span>
							</div>
						</div>
					</TableData>
					<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center" v-if="!roles.includes('operator_driver')">
						<div class="flex flex-col space-y-1">
							<div
									class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
									:class="[vend.is_active || vend.is_testing ? (vend.is_online ? 'bg-green-200' : 'bg-red-200') : 'bg-gray-200 text-gray-400']"
							>
									<div class="flex flex-col">
											<span class="font-bold">
													{{vend.is_online ? 'HTTP Online' : 'HTTP Offline'}}
											</span>
											<span v-if="vend.last_online_at">
													{{vend.last_online_at}}
											</span>
									</div>
							</div>
							<!-- Consolidated alerts moved to column 3 -->
							<div
									class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
									:class="[vend.is_active || vend.is_testing ? (vend.is_mqtt_active ? 'bg-green-200' : 'bg-gray-200') : 'bg-gray-200 text-gray-400']"
									v-if="vend.is_mqtt"
							>
									<div class="flex flex-col">
											<span class="font-bold">
												{{vend.is_mqtt_active ? 'MQTT Online' : 'MQTT Offline'}}
											</span>
											<span v-if="vend.mqtt_last_updated_at">
													{{ vend.mqtt_last_updated_at }}
											</span>
									</div>
							</div>
							<!--
								Remote Modem — minimal badge, matches HTTP / MQTT styling.
								Modem type, IMEI and Reset button have been moved to
								Vend/Edit.vue (Advance Control) so this column stays
								narrow.

								Always rendered so the column is visually consistent
								across rows. When the vend has no modem unit bound,
								the badge falls back to a gray N/A — same pattern the
								Payment Device column uses for missing parameters.

								Coloring logic when a modemUnit IS present mirrors
								HTTP / MQTT badges:
								  - green when modem reports online
								  - red when modem reports offline (last_updated_at present)
								  - gray when N/A (no last_updated_at) or vend inactive
							-->
							<div
								class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
								:class="[
									vend.vend && vend.vend.modemUnit
										? ((vend.is_active || vend.is_testing)
											? (vend.vend.modemUnit.last_updated_at
												? (vend.vend.modemUnit.is_online ? 'bg-green-200' : 'bg-red-200')
												: 'bg-gray-200')
											: 'bg-gray-200 text-gray-400')
										: ((vend.is_active || vend.is_testing) ? 'bg-gray-200' : 'bg-gray-200 text-gray-400')
								]"
							>
								<div class="flex flex-col">
									<span class="font-bold">Remote Modem</span>
									<template v-if="vend.vend && vend.vend.modemUnit">
										<span class="font-bold" v-if="vend.vend.modemUnit.last_updated_at">
											{{ vend.vend.modemUnit.is_online ? 'Online' : 'Offline' }}
										</span>
										<span v-else>
											N/A
										</span>
										<span v-if="vend.vend.modemUnit.last_updated_at">
											{{ vend.vend.modemUnit.last_updated_at }}
										</span>
									</template>
									<span v-else>
										N/A
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
													Product Drop Sensor
											</span>
											<span>
													{{vend.parameterJson['Sensor'] % 2 == 0 ? 'Disabled' : 'Enabled'}}
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
													Dispensing Door
											</span>
											<span>
													{{vend.parameterJson['door'] == 'open' ? 'Open' : 'Close'}}
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
					<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center" v-if="!roles.includes('operator_driver')">
						<div class="flex flex-col space-y-1">
							<div
									class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
									:class="[vend.is_active || vend.is_testing ? (vend.acbVmcPaJson['QRCode'] == 1 ? 'bg-green-200' : 'bg-gray-200') : 'bg-gray-200 text-gray-400']"
									v-if="vend.acbVmcPaJson && 'QRCode' in vend.acbVmcPaJson"
							>
									<div class="flex flex-col">
											<span class="font-bold">
													Keypad QR Code
											</span>
											<span>
													{{vend.acbVmcPaJson['QRCode'] == 1 ? 'Enabled' : 'Disabled'}}
											</span>
									</div>
							</div>
							<!--
								LCD Monitor — short label sourced from
								Vend::LCD_MONITOR_SHORT_MAPPINGS (exposed through
								VendResource as `lcd_monitor_short`). Always rendered
								so the Payment Device column lines up across rows.

								Two paths produce an "N/A" display: the vend has no
								lcd_monitor_id bound, OR the user explicitly bound it
								to mapping id 99 (which the mapping itself labels
								'N/A'). Both should render the badge in the same
								Bill-Acceptor-style gray — bg-gray-200 with default
								dark text when the vend is active, dimmed only when
								the vend itself is inactive.
							-->
							<div
									class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
									:class="[
										(vend.vend && vend.vend.lcd_monitor_short && vend.vend.lcd_monitor_short !== 'N/A')
											? ((vend.is_active || vend.is_testing) ? 'bg-green-200' : 'bg-gray-200 text-gray-400')
											: ((vend.is_active || vend.is_testing) ? 'bg-gray-200' : 'bg-gray-200 text-gray-400')
									]"
							>
									<div class="flex flex-col">
											<span class="font-bold">
													LCD Monitor
											</span>
											<span>
													{{ vend.vend && vend.vend.lcd_monitor_short ? vend.vend.lcd_monitor_short : 'N/A' }}
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
							<!-- <div
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
							</div> -->
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
							:class="[vend.is_active || vend.is_testing ? (vend.parameterJson['CoinCnt'] > COIN_FLOAT_LOW_THRESHOLD ? 'bg-green-200' : 'bg-red-200') : 'bg-gray-200 text-gray-400']"
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
							<!-- <div
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
							</div> -->
							<!-- <div
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
							</div> -->
							<!-- Card Terminal (merged from former Cashless Status + Cashless Mfg badges):
								 user-defined value from vends.card_terminal_id -> card_terminals.name.
								 Green when set, gray "N/A" when null. -->
							<div
									class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
									:class="[vend.is_active || vend.is_testing ? (vend.card_terminal_name ? 'bg-green-200' : 'bg-gray-200') : 'bg-gray-200 text-gray-400']"
							>
									<div class="flex flex-col">
											<span class="font-bold">
													Card Terminal
											</span>
											<span>
													{{ vend.card_terminal_name ? vend.card_terminal_name : 'N/A' }}
											</span>
									</div>
							</div>
						</div>
					</TableData>
					<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center" v-if="indexType === 'customers' && !roles.includes('operator_driver')">
						<span :class="vend.is_active || vend.is_testing ? 'text-gray-900' : 'text-gray-400'">
							<div class="flex flex-col space-y-2">
								<span class="flex flex-col space-y-1">
									<span>
										{{ vend.operator_code }}
									</span>
									<span>
										{{ vend.account_manager_name }}
									</span>
									<span>
										{{ vend.location_type_name }}
									</span>
									<!-- Section divider — mirrors the <hr> in the
										TableHead between Location and VMC Board,
										separating the operator/location cluster
										from the hardware-version cluster. -->
									<hr class="border-t border-gray-300 my-2" />
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
									<span class="text-green-600" v-if="vend.acbVmcPaJson && 'ACBVer' in vend.acbVmcPaJson">
										{{ vend.acbVmcPaJson['ACBVer'] }}
									</span>
								</span>
								<div
									class="inline-flex justify-center items-center rounded px-1.5 py-0.5 text-xs font-medium border min-w-full"
									:class="[vend.is_sold ? 'bg-yellow-200' : (vend.is_testing ? 'bg-gray-200' : (vend.vend_is_active ? 'bg-blue-200' : (vend.is_disposed ? 'bg-red-300' : 'bg-red-200')))]"
								>
										<div class="flex flex-col">
												<span class="font-bold">
													Machine
												</span>
												<span>
														{{vend.is_sold ? 'Sold' : (vend.is_testing ? 'Testing' : (vend.vend_is_active ? 'Active' : (vend.is_disposed ? 'Disposed' : 'Not Active')))}}
												</span>
										</div>

								</div>

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
	<!-- <div class="py-5"> -->
		<Paginator class="py-14" v-if="vends.data.length" :links="vends.links" :meta="vends.meta"></Paginator>
	<!-- </div> -->
	</div>
	</div>
	</div>
	<div v-if="!hasSearched" class="mt-6 rounded-lg border border-dashed border-gray-300 bg-white px-6 py-12 text-center text-gray-500">
		Use the available filters and click <span class="font-semibold">Search</span> to load customer vending data.
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
<!-- <ProductAvailability
v-if="showProductAvailabilityModal"
:products="productOptions"
:showModal="showProductAvailabilityModal"
@modalClose="onProductAvailabilityModalClose"
@productUpdated="refreshProductOptions"
>
</ProductAvailability> -->
<AssignJob
v-if="showAssignJobModal"
:driverOptions="driverOptions"
:showModal="showAssignJobModal"
@modalClose="onAssignJobModalClose"
@jobAssigned="onJobAssigned"
:vends="vends.data.filter(vend => vend.is_selected)"
>
</AssignJob>
<MapMarker
v-if="showMapMarkerModal"
:customers="customerModel"
:api-key="mapApiKey"
:showModal="showMapMarkerModal"
@modalClose="onMapMarkerModalClose"
>
</MapMarker>
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
	import DatePicker from '@/Components/DatePicker.vue';
	import Paginator from '@/Components/Paginator.vue';
	// import ProductAvailability from '@/Pages/Vend/ProductAvailability.vue';
	import SearchInput from '@/Components/SearchInput.vue';
	import MultiSelect from '@/Components/MultiSelect.vue';
	import { ArrowDownTrayIcon, ArrowUpIcon, ArrowDownIcon, ChevronDoubleDownIcon, ChevronDoubleUpIcon, EllipsisHorizontalCircleIcon, ExclamationCircleIcon, MagnifyingGlassIcon, BackspaceIcon, PlayCircleIcon, ClipboardDocumentCheckIcon, MapPinIcon, CursorArrowRippleIcon, TableCellsIcon, CheckCircleIcon, XCircleIcon } from '@heroicons/vue/20/solid';
	import TableHead from '@/Components/TableHead.vue';
	import TableData from '@/Components/TableData.vue';
	import TableHeadSort from '@/Components/TableHeadSort.vue';
	import SingleSortItem from '@/Components/SingleSortItem.vue';
	import { ref, onMounted, defineAsyncComponent, watch, nextTick } from 'vue';
	import { router, Link, Head, usePage } from '@inertiajs/vue3';
	import { Dropdown, Tooltip, Menu, vTooltip } from 'floating-vue';
	import moment from 'moment';
	import axios from 'axios';
	import { COIN_FLOAT_LOW_THRESHOLD } from '@/constants/vendThresholds';

	const AssignJob = defineAsyncComponent(() => import('@/Pages/Vend/AssignJob.vue'));
	const ChannelOverview = defineAsyncComponent(() => import('@/Pages/Vend/ChannelOverview.vue'));
	const Create = defineAsyncComponent(() => import('@/Pages/Vend/Create.vue'));
	const Form = defineAsyncComponent(() => import('@/Pages/Vend/Form.vue'));
	const MapMarker = defineAsyncComponent(() => import('@/Components/MapMarker.vue'));
	const PickList = defineAsyncComponent(() => import('@/Pages/Vend/PickList.vue'));

	const props = defineProps({
			autoLoad: Boolean,
			cardTerminalOptions: [Array, Object],
			cmsEndpoint: String,
			constTempError: Number,
			dayOptions: [Array, Object],
			deliveryPlatformOptions: [Array, Object],
			deviceTypes: [Array, Object],
			driverOptions: Object,
			frequencyPerWeekOptions: [Array, Object],
			indexType: String,
			locationTypeOptions: Object,
			mapApiKey: String,
			nextDeliveryDriverOptions: [Array, Object],
			operatorOptions: Object,
			productMappingOptions: Object,
			productOptions: Object,
			sellingPriceTypeOptions: [Array, Object],
			totals: [Array, Object],
			vends: Object,
			vendChannelErrors: Object,
			vendConfigOptions: Object,
			vendContractOptions: Object,
			vendModelOptions: Object,
			vendPrefixOptions: Object,
			zoneOptions: Object,
	})

	const filters = ref({
			account_manager_name: '',
			apk_ver: '',
			cashless_mfg: '',
			codes: '',
			coinLessThan: '',
			channel_codes: '',
			delivery_platform_id: '',
			serialNum: '',
			customer: '',
			deviceType: '',
			errors: [],
			firmware_ver: '',
			frequency_per_week_status: [],
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
			fan_rpm: '',
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
			productMappings: [],
			vendConfigs: [],
			vendContracts: [],
			visited: true,
			zones: [],
	})

	const showAssignJobModal = ref(false)
	const authOperator = usePage().props.auth.operator
	const baseUrl = ref(props.indexType === 'customers' ? '/vends/customers' : '/vends')
	const booleanOptions = ref([])
	const booleanStrictOptions = ref([])
	const cardTerminalOptions = ref([])
	const customerModel = ref([])
	const deliveryPlatformOptions = ref([])
	const deviceTypeOptions = ref([])
	const dayOptions = ref([])
	const doorOptions = ref([])
	const fanRpmOptions = ref([])
	const enableOptions = ref([])
	const frequencyPerWeekOptions = ref([])
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
	const productMappingOptions = ref([])
	const sellingPriceTypeOptions = ref([])
	const showAllFilters = ref(false)
	const showChannelOverviewModal = ref(false)
	const showCreateModal = ref(false)
	const showEditModal = ref(false)
	const showMapMarkerModal = ref(false)
	const showPickListModal = ref(false)
	const showProductAvailabilityModal = ref(false)
	const statusOptions = ref([])
	const type = ref('')
	const vend = ref()

	const vends = ref(getVendsField())
	const vendChannelErrorsOptions = ref([])
	const vendConfigOptions = ref([])
	const vendContractOptions = ref([])
	const vendModelOptions = ref([])
	const vendPrefixOptions = ref([])
	const zoneOptions = ref([])
	//   const vendOptions = ref([])
	const operatorCountry = usePage().props.auth.operatorCountry
	const operatorRole = usePage().props.auth.operatorRole
	const permissions = usePage().props.auth.permissions
	const roles = usePage().props.auth.roles
	const initBinded = usePage().props.initBinded
	const hasSearched = ref(props.autoLoad ?? false)
	const now = ref((props.autoLoad ? moment().format('HH:mm:ss') : '--:--'))

onMounted(() => {
	// console.log(props.vends)
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

deliveryPlatformOptions.value = [
	{id: 'all', value: 'All'},
	...props.deliveryPlatformOptions.data.map((data) => {return {id: data.id, value: data.name}})
]
deviceTypeOptions.value =
[
		{id: 'all', value: 'All'},
		...Object.entries(props.deviceTypes).map(([id, name]) => ({id: id, value: name}))
]
// Card terminal types (Nayax / Nets / Nets-Auresys / PAX / MLS) sourced from
// Vend::CARD_TERMINALS via the controller. Posts back the name (e.g.
// "Nayax") as the `cashless_mfg` query param; resolved against
// card_terminals.name via the subquery in HasFilter::filterVendsDB.
cardTerminalOptions.value = [
		{id: 'all', value: 'All'},
		...(props.cardTerminalOptions ?? []).map((name) => ({id: name, value: name}))
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
fanRpmOptions.value = [
		{id: 'all', value: 'All'},
		{id: '0', value: '0'},
		{id: '>0', value: '>0'},
		{id: 'N/A', value: 'N/A'},
		{id: '--', value: '--'},
]
frequencyPerWeekOptions.value = [
	...Object.entries(props.frequencyPerWeekOptions).map(([id, value]) => {
		return {
			id: id,
			value: value,
		};
	})
]
isActiveFactoryOptions.value = [
		{id: 'all', value: 'All'},
		{id: '1', value: 'Factory (JB)'},
		{id: '2', value: 'Active'},
		{id: '3', value: 'Not Active'},
		{id: '4', value: 'Disposed'},
		{id: '5', value: 'Sold'},
]
locationTypeOptions.value = [
		{id: 'all', value: 'All'},
		...props.locationTypeOptions.data.map((data) => {return {id: data.id, value: data.name}})
]
nextDeliveryDriverOptions.value = [
		{id: 'all', value: 'All'},
		...props.nextDeliveryDriverOptions.data.map((data) => {return {id: data.id, value: data.name}})
]
operatorOptions.value = [
		{id: 'all', full_name: 'All'},
		...props.operatorOptions.data.map((data) => {return {id: data.id, code: data.code, full_name: data.full_name}})
]
sellingPriceTypeOptions.value = Object.entries(props.sellingPriceTypeOptions).map(([id, name]) => ({id: id, value: name}))
statusOptions.value = [
		{id: 'all', value: 'All'},
		{id: 'factory', value: 'Factory (JB)'},
		{id: 'active', value: 'Active'},
		{id: 'inactive', value: 'Not Active'},
		{id: 'disposed', value: 'Disposed'},
		{id: 'sold', value: 'Sold'},
]
vendConfigOptions.value = [
		{id: 'all', value: 'All'},
		...props.vendConfigOptions.data.map((data) => {return {id: data.id, value: data.name}})
	]
vendContractOptions.value = [
		{id: 'all', value: 'All'},
		...props.vendContractOptions.data.map((data) => {return {id: data.id, value: data.name}})
	]
vendModelOptions.value = [
		{id: 'all', value: 'All'},
		...props.vendModelOptions.data.map((data) => {return {id: data.id, value: data.name}})
]

vendPrefixOptions.value = [
		{id: 'single-ud', value: 'Single UD'},
		...props.vendPrefixOptions.data.map((data) => {return {id: data.id, value: data.name}})
]

zoneOptions.value = [
		{id: 'all', value: 'All'},
		...props.zoneOptions.data.map((data) => {return {id: data.id, value: data.name}})
]

productMappingOptions.value = [
		{id: 'all', value: 'All'},
		...props.productMappingOptions.data.map((data) => {return {id: data.id, value: data.name}})
]

filters.value.cashless_mfg = cardTerminalOptions.value[0]
filters.value.delivery_platform_id = deliveryPlatformOptions.value[0]
filters.value.is_active = booleanOptions.value[1]
filters.value.deviceType = deviceTypeOptions.value[0]
// filters.value.frequency_per_week_status = frequencyPerWeekOptions.value[0]
filters.value.is_door_open = doorOptions.value[0]
filters.value.fan_rpm = fanRpmOptions.value[0]
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
	...authOperator.code == 'HIPL' ? [
		operatorOptions.value.find(operator => operator.code == 'HIMD'),
		operatorOptions.value.find(operator => operator.code == 'LEA'),
		operatorOptions.value.find(operator => operator.code == 'HIESG'),
		operatorOptions.value.find(operator => operator.code == 'UL-ST'),
	] : [],
].filter(operator => operator !== undefined) : [operatorOptions.value[0]]
filters.value.status = statusOptions.value[2]
	// filters.value.vend_prefix_id = vendPrefixOptions.value[0]
// vendOptions.value = props.vendOptions.data.map((vend) => {return {id: vend.id, code: vend.code}})

const urlParams = new URLSearchParams(window.location.search);
if(urlParams.has('codes')) {
    filters.value.codes = urlParams.get('codes');
    filters.value.is_active = booleanOptions.value[0];
    filters.value.status = statusOptions.value[0];
    filters.value.operators = [operatorOptions.value[0]];
}
if(urlParams.has('channel_codes')) {
    filters.value.channel_codes = urlParams.get('channel_codes');
    filters.value.is_active = booleanOptions.value[0];
    filters.value.status = statusOptions.value[0];
    filters.value.operators = [operatorOptions.value[0]];
}

	// Hydrate filters from URL
	for(const [key, value] of urlParams.entries()) {
		let cleanKey = key.replace(/\[\d*\]$/, '');

		// string fields
		if([
			'account_manager_name', 'apk_ver', 'codes', 'coinLessThan', 'channel_codes',
			'serialNum', 'customer', 'firmware_ver', 'tempHigherThan', 't2HigherThan',
			'tempDeltaHigherThan', 'lastVisitedGreaterThan',
			'balanceStockLessThan', 'remainingSkuLessThan', 'vendRecordsThirtyDaysAmountAverageLessThan',
			'sortKey', 'next_planned_date'
		].includes(cleanKey)) {
				filters.value[cleanKey] = value;
		}

		if(key === 'sortBy') filters.value.sortBy = (value === 'true');

		if(cleanKey === 'cashless_mfg') filters.value.cashless_mfg = cardTerminalOptions.value.find(opt => String(opt.id) === String(value)) || filters.value.cashless_mfg;
		if(cleanKey === 'delivery_platform_id') filters.value.delivery_platform_id = deliveryPlatformOptions.value.find(opt => String(opt.id) === String(value)) || filters.value.delivery_platform_id;
		if(cleanKey === 'deviceType') filters.value.deviceType = deviceTypeOptions.value.find(opt => String(opt.id) === String(value)) || filters.value.deviceType;
		if(cleanKey === 'location_type_id') filters.value.locationType = locationTypeOptions.value.find(opt => String(opt.id) === String(value)) || filters.value.locationType;
		if(cleanKey === 'next_planned_driver') filters.value.next_planned_driver = nextDeliveryDriverOptions.value.find(opt => String(opt.id) === String(value)) || filters.value.next_planned_driver;
		if(cleanKey === 'is_active') filters.value.is_active = booleanOptions.value.find(opt => String(opt.id) === String(value)) || filters.value.is_active;
		if(cleanKey === 'is_binded_customer') filters.value.is_binded_customer = booleanOptions.value.find(opt => String(opt.id) === String(value)) || filters.value.is_binded_customer;
		if(cleanKey === 'is_door_open') filters.value.is_door_open = doorOptions.value.find(opt => String(opt.id) === String(value)) || filters.value.is_door_open;
		if(cleanKey === 'fan_rpm') filters.value.fan_rpm = fanRpmOptions.value.find(opt => String(opt.id) === String(value)) || filters.value.fan_rpm;
		if(cleanKey === 'is_mqtt') filters.value.is_mqtt = booleanOptions.value.find(opt => String(opt.id) === String(value)) || filters.value.is_mqtt;
		if(cleanKey === 'is_mqtt_active') filters.value.is_mqtt_active = booleanOptions.value.find(opt => String(opt.id) === String(value)) || filters.value.is_mqtt_active;
		if(cleanKey === 'is_online') filters.value.is_online = booleanOptions.value.find(opt => String(opt.id) === String(value)) || filters.value.is_online;
		if(cleanKey === 'is_sensor') filters.value.is_sensor = enableOptions.value.find(opt => String(opt.id) === String(value)) || filters.value.is_sensor;
		if(cleanKey === 'status') filters.value.status = statusOptions.value.find(opt => String(opt.id) === String(value)) || filters.value.status;
		if(cleanKey === 'numberPerPage') filters.value.numberPerPage = numberPerPageOptions.value.find(opt => String(opt.id) === String(value)) || filters.value.numberPerPage;
		if(cleanKey === 'selling_price_type') filters.value.selling_price_type = sellingPriceTypeOptions.value.find(opt => String(opt.id) === String(value)) || filters.value.selling_price_type;
	}

	const hydrateMulti = (paramKey, options, filterKey) => {
		let values = [];
		for (const [pKey, pValue] of urlParams.entries()) {
				if (pKey === paramKey || pKey.startsWith(paramKey + '[') ) {
					values.push(pValue);
				}
		}
		if (values.length > 0) {
				filters.value[filterKey] = options.filter(opt => values.includes(String(opt.id)));
		}
	}

	hydrateMulti('errors', vendChannelErrorsOptions.value, 'errors');
	hydrateMulti('frequency_per_week_status', frequencyPerWeekOptions.value, 'frequency_per_week_status');
	hydrateMulti('operators', operatorOptions.value, 'operators');
	hydrateMulti('preferredDays', dayOptions.value, 'preferredDays');
	hydrateMulti('productMappings', productMappingOptions.value, 'productMappings');
	hydrateMulti('vendConfigs', vendConfigOptions.value, 'vendConfigs');
	hydrateMulti('vendContracts', vendContractOptions.value, 'vendContracts');
	hydrateMulti('vendModels', vendModelOptions.value, 'vendModels');
	hydrateMulti('vendPrefixes', vendPrefixOptions.value, 'vendPrefixes');
	hydrateMulti('zones', zoneOptions.value, 'zones');

	// Only auto-search if codes/channel_codes are non-empty AND the server hasn't already
	// loaded data (autoload=true means the server already ran the query — no need to re-fetch)
	if((urlParams.get('codes') || urlParams.get('channel_codes')) && !urlParams.get('autoload')) {
		onSearchFilterUpdated();
	}
})

const activeMachineHealthAlerts = ref({});

const fetchActiveAlerts = (data) => {
	if (!data || data.length === 0) {
		activeMachineHealthAlerts.value = {};
		return;
	}
	let vendIds = [];
	data.forEach(v => {
		if (v.vend_id) vendIds.push(v.vend_id);
		if (v.id) vendIds.push(v.id);
	});
	vendIds = [...new Set(vendIds.filter(id => id))];

	if (vendIds.length > 0) {
		axios.post('/reports/machine-health/active-alerts', { vend_ids: vendIds })
			.then(res => {
				activeMachineHealthAlerts.value = res.data;
			})
			.catch(err => {
				activeMachineHealthAlerts.value = {};
			});
	}
};

watch(() => vends.value.data, (newData) => {
	fetchActiveAlerts(newData);
}, { immediate: true, deep: true });

const getMachineAlerts = (vend, group) => {
	const id = vend.vend_id || vend.id;
	const alerts = activeMachineHealthAlerts.value[id] || [];
	return alerts.filter(a => a.group === group);
};

const getMachineAlertsGroup = (vend, numbers) => {
	const id = vend.vend_id || vend.id;
	const alerts = activeMachineHealthAlerts.value[id] || [];
	return alerts.filter(a => {
		const label = getAlertLabel(a);
		if (!label) return false;
		// Check if the label (e.g., '1', '2B', '5') starts with any of the requested numbers
		return numbers.some(n => label.startsWith(n.toString()));
	});
};

const getAllMachineHealthAlerts = (vend) => {
	const id = vend.vend_id || vend.id;
	const alerts = activeMachineHealthAlerts.value[id] || [];
	// Only return 1-5 (connectivity, temperature, no_transactions, error_code)
	return alerts.filter(a => ['connectivity', 'temperature', 'no_transactions', 'error_code'].includes(a.group));
};

const getAlertClass = (alert) => {
	const code = getAlertLabel(alert);
	if (alert.group === 'connectivity' || (code && code.startsWith('1'))) {
		return 'bg-red-500 text-white border-red-600'; // High visibility for (1)
	}
	if (alert.group === 'error_code' || code === '5') {
		return 'bg-red-600 text-white border-red-700'; // Critical red for (5)
	}
	if (alert.group === 'no_transactions' || code === '4') {
		return 'bg-purple-500 text-white border-purple-600'; // Purple for (4)
	}
	if (alert.group === 'temperature') {
		if (code && code.startsWith('2')) return 'bg-orange-500 text-white border-orange-600'; // Orange for (2)
		if (code && code.startsWith('3')) return 'bg-yellow-400 text-gray-900 border-yellow-500'; // Yellow for (3)
	}
	return 'bg-red-100 text-red-800 border-red-200';
};

const getAlertLabel = (alert) => {
	const map = {
		'connectivity': '1',
		'comp_fan_off': '2A',
		'temps_above_0': '2B',
		'temps_above_minus_8': '2C',
		'not_reach_minus_18': '2D',
		'temps_above_minus_17_upward': '2E',
		'lowest_24h_above': '3A',
		'lowest_72h_above': '3B',
		'rising_t1_trend': '3C',
		'rising_t2_trend': '3C',
		'rising_lowest_t1_smart': '3C',
		'rising_lowest_t2_smart': '3C',
		't2_frozen': '3D',
		't2_frozen_smart': '3D',
		't1_higher_than_t2_smart': '3F',
	};

	if (alert.group === 'no_transactions') return '4';
	if (alert.group === 'error_code') return '5';

	return map[alert.type] || map[alert.group] || null;
};

const getAlertTooltip = (alert) => {
	let header = '';

	if (alert.group === 'no_transactions') {
		header = '(4) Alert on Lost of Transaction/Sales';
	} else if (alert.group === 'connectivity') {
		header = '(1) Alert on Lost of Connectivity or Electricity';
	} else if (alert.group === 'error_code') {
		header = '(5) Channel Errors';
	} else if (alert.group === 'stockout') {
		header = '(6) Stockout Alert';
	} else if (alert.group === 'temperature') {
		const code = getAlertLabel(alert);
		if (code && code.startsWith('1')) {
			header = '(1) Alert on Lost of Connectivity or Electricity';
		} else if (code && code.startsWith('2')) {
			header = '(2) Operation Error / Critical Parts Failure';
		} else if (code && code.startsWith('3')) {
			header = '(3) Preventive maintenance / Temp raise alert';
		}
	}

	const parts = [];
	if (header) parts.push(`<b>${header}</b>`);

	// Add subtitle (label)
	if (alert.label) {
		parts.push(alert.label);
	}

	// Add duration if available
	const duration = (alert.duration && String(alert.duration).toLowerCase() !== 'null' && String(alert.duration).toLowerCase() !== 'null hours') ? alert.duration : null;
	if (duration) {
		parts.push(`Duration: ${duration}`);
	}

	// Add occurred at if available
	if (alert.occurred_at) {
		parts.push(`Since: ${moment(alert.occurred_at).format('DD MMM YY, HH:mm')}`);
	}

	return {
		content: parts.join('<br>'),
		html: true
	};
};


function compareRefPrice(vend, channel) {
// let type = vend && vend.customer ? vend.customer.selling_price_type : vend.selling_price_type
if(channel && channel.amount && channel.amount != channel.ref_price) {
	return 'text-red-500'
}

return 'text-gray-900'
}

// Maps contract type code to human-readable label. Mirrors Customer/Summary.vue.
function contractTypeLabel(type) {
	switch (type) {
		case 'F':     return 'Free Placement'
		case 'S':     return 'Subsidized Plan'
		case 'R':     return 'Fix Rental'
		case 'U':     return 'Utility only'
		case 'PS':    return 'PS'
		case 'PS+U':  return 'PS + U'
		case 'PSORU': return 'PS OR U'
		default:      return type ?? ''
	}
}

// Money formatter for cent amounts — mirrors the inline toLocaleString
// pattern used elsewhere on this page, honouring the operator country's
// currency symbol / exponent. Negative values get a leading '-'.
function fmtCents(cents) {
	if (cents == null) return ''
	const exp = operatorCountry.currency_exponent
	const sym = operatorCountry.currency_symbol
	const sign = Number(cents) < 0 ? '-' : ''
	const value = Math.abs(Number(cents)) / Math.pow(10, exp)
	return sign + sym + value.toLocaleString(undefined, {
		minimumFractionDigits: operatorCountry.is_currency_exponent_hidden ? 0 : exp,
		maximumFractionDigits: operatorCountry.is_currency_exponent_hidden ? 0 : exp,
	})
}

// External Subsidize for a row, in cents. Pulled live from the customer's
// current contract (Customer/Edit.vue): external_subsidize_amount is stored
// in dollars and only counts when the is_external_subsidize toggle is on.
// Returns 0 when disabled/unset.
function extSubCents(vend) {
	if (!vend || !vend.is_external_subsidize || vend.external_subsidize_amount == null) return 0
	return Math.round(Number(vend.external_subsidize_amount) * Math.pow(10, operatorCountry.currency_exponent))
}

// Net Loc Fee = Location Fees − External Subsidize (both in cents).
function netLocFeeCents(vend) {
	return Number(vend.location_fees_cents || 0) - extSubCents(vend)
}

// Returns the name of a machine's upcoming new product mapping, or null when
// there isn't one. An upcoming mapping can live directly on the vend
// (vend.upcomingProductMapping) or on its current mapping
// (productMapping.upcomingProductMapping). 'N/A' is treated as "no upcoming
// mapping". Drives the "New" badge in the machine column. Mirrors the same
// precedence the Ops Job columns use (current mapping's upcoming wins first).
function getUpcomingMappingName(vendData) {
	if (!vendData) return null
	const fromMapping = vendData.productMapping
		&& vendData.productMapping.upcomingProductMapping
		&& vendData.productMapping.upcomingProductMapping.name !== 'N/A'
			? vendData.productMapping.upcomingProductMapping.name
			: null
	if (fromMapping) return fromMapping
	const fromVend = vendData.upcomingProductMapping
		&& vendData.upcomingProductMapping.name !== 'N/A'
			? vendData.upcomingProductMapping.name
			: null
	return fromVend
}

function getVendsField() {
	return {
			...props.vends,
			data: props.vends.data.map((data) => {return {
					...data,
					// vendChannelsJson: props.indexType === 'customers' ? data.vend?.vendChannelsJson : data.vendChannelsJson,
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

function onMapMarkerClicked(customer) {
customerModel.value = [{
	sequence: props.vends.data.findIndex((data) => data.customer_id == customer.id) + 1,
	...customer
}];
showMapMarkerModal.value = true;
}

function onMapAllMarkerClicked() {
	// Extract all the opsJobItems' customer information and send the request
	customerModel.value = props.vends.data.map((customer, index) => ({
		sequence: index + 1,
		...customer
	}));
	showMapMarkerModal.value = true;
}

function onMapMarkerModalClose() {
	showMapMarkerModal.value = false
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

// function onProductAvailableModalClicked() {
// 	showProductAvailabilityModal.value = true
// }

// function onProductAvailabilityModalClose() {
// 	showProductAvailabilityModal.value = false
// }

function onShowAllFiltersClicked() {
		showAllFilters.value = !showAllFilters.value
}

function onSearchFilterUpdated() {
	router.get(baseUrl.value, {
			autoload: true,
			...filters.value,
			cashless_mfg: filters.value.cashless_mfg?.id ?? '',
			delivery_platform_id: filters.value.delivery_platform_id.id,
			deviceType: filters.value.deviceType.id,
			errors: filters.value.errors.map((error) => { return error.id }),
			fan_rpm: filters.value.fan_rpm.id,
			frequency_per_week_status: filters.value.frequency_per_week_status.map((frequency) => { return frequency.id }),
			location_type_id: filters.value.locationType.id,
			next_planned_date: filters.value.next_planned_date,
			next_planned_driver: filters.value.next_planned_driver.id,
			operators: filters.value.operators.filter(operator => operator).map((operator) => { return operator.id }),
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
			productMappings: filters.value.productMappings.map((pm) => { return pm.id }),
			vendConfigs: filters.value.vendConfigs.map(vc => vc.id),
			vendContracts: filters.value.vendContracts.map(vc => vc.id),
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
					hasSearched.value = true
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

function refreshProductOptions(data) {

	router.reload({
		only: ['productOptions'],
		data: {
			productFilters: data
		}
	});
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

// Persist the inline-edited customer-level Note. Same endpoint and refresh
// pattern as Customer/Summary.vue — POST to /customers/{id}/update-notes
// and then router.reload only the `vends` prop so filters, scroll, etc. are
// preserved. We key off vend.customer_id because in the customers-index
// path each row's customer_id is the canonical customer being edited.
function onNotesChanged(vend) {
	const customerId = vend?.customer_id ?? vend?.id;
	if (!customerId) return;
	axios.post('/customers/' + customerId + '/update-notes', {
		notes: vend.notes,
	})
		.then(() => {
			router.reload({ only: ['vends'], preserveScroll: true });
		})
		.catch((error) => {
			console.error('Error updating customer notes:', error);
		});
}

// Auto-grow the inline-edit textareas (Ops Note + Customer Note) so the
// full content is visible without scrolling inside the cell. Bound via
// :ref-callback (initial mount + after vends partial-reload swaps row
// instances) and via @input for live typing. nextTick guarantees the
// new value is in the DOM before we measure scrollHeight.
function autoGrowTextarea(el) {
	if (!el) return;
	nextTick(() => {
		el.style.height = 'auto';
		el.style.height = el.scrollHeight + 'px';
	});
}

// Same shape as onNotesChanged — separate endpoint so the two free-text
// fields (Customer Note for finance/admin, Ops Note for refilling/operations)
// have independent audit trails. Hits /customers/{id}/update-ops-note and
// partial-reloads `vends` so the audit line refreshes without losing state.
function onOpsNoteChanged(vend) {
	const customerId = vend?.customer_id ?? vend?.id;
	if (!customerId) return;
	axios.post('/customers/' + customerId + '/update-ops-note', {
		ops_note: vend.ops_note,
	})
		.then(() => {
			router.reload({ only: ['vends'], preserveScroll: true });
		})
		.catch((error) => {
			console.error('Error updating customer ops note:', error);
		});
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
				cashless_mfg: filters.value.cashless_mfg?.id ?? '',
				delivery_platform_id: filters.value.delivery_platform_id.id,
				deviceType: filters.value.deviceType.id,
				errors: filters.value.errors.map((error) => { return error.id }),
				frequency_per_week_status: filters.value.frequency_per_week_status.id,
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
				vendConfigs: filters.value.vendConfigs.map(vc => vc.id),
				vendContracts: filters.value.vendContracts.map(vc => vc.id),
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
