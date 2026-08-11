
<template>
	<Head title="Operation Dashboard (Lite) - Live Status" />

<BreezeAuthenticatedLayout>
	<template #header>
		<h2 class="font-semibold text-xl text-gray-800 leading-tight">
				Operation Dashboard (Lite)
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
				<SearchInput placeholderStr="Site name / ID" v-model="filters.customer" v-if="permissions.includes('admin-access vend-customers')" @keyup.enter="onSearchFilterUpdated()">
					Site
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
				<div v-if="showAllFilters && permissions.includes('admin-access vend-customers') && indexType === 'customers'">
						<label for="text" class="block text-sm font-medium text-gray-700">
							Site Status
						</label>
						<MultiSelect
							v-model="filters.customer_status"
							:options="customerStatusOptions"
							trackBy="id"
							valueProp="id"
							label="value"
							mode="tags"
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
				<!-- Upcoming Mapping — matches the mapping the machine is scheduled to
				     switch to, i.e. the same value the "New" badge / Upcoming Job column
				     shows: the machine's own upcoming first, else its current mapping's
				     preset upcoming. "— None —" lists machines with no upcoming at all. -->
				<div v-if="showAllFilters && permissions.includes('admin-access vend-customers')">
						<label for="text" class="block text-sm font-medium text-gray-700">
								Upcoming Mapping
						</label>
						<MultiSelect
								v-model="filters.upcomingProductMappings"
								:options="upcomingProductMappingOptions"
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
					<label class="block text-sm font-medium text-gray-700">
						Site Grouping
					</label>
					<label class="mt-1 flex items-center gap-2 h-[38px] text-sm text-gray-700 cursor-pointer select-none"
						title="Show co-located sites as a group: if any member matches the filters, all its group-mates appear too, kept next to each other.">
						<input type="checkbox" v-model="filters.group_siblings"
							class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" />
						Grouped? (show siblings together)
					</label>
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
	</div>

	<div class="mt-6 flex flex-col" v-if="hasSearched">
	<!-- Deferred-load notice: heavy $/stock/job columns stream in via a 2nd
	     request after the table paints; this tells the user those cells (and
	     the matching cards) are still populating so brief 0s aren't mistaken
	     for real values. -->
	<div v-if="aggregatesLoading" class="mb-2 flex items-center gap-2 rounded-md border border-sky-200 bg-sky-50 px-3 py-2 text-sm text-sky-800">
		<svg class="h-4 w-4 animate-spin text-sky-500" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
			<circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
			<path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
		</svg>
		<span>Loading financial &amp; stock columns…</span>
	</div>
	<div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
	<div class="cv-scroll overflow-scroll max-h-[900px] md:max-h-[1500px] shadow-sm ring-1 ring-black ring-opacity-5">
		<table class="min-w-full border-separate" style="border-spacing: 0">
			<thead class="bg-gray-100">
				<tr class="divide-x divide-gray-200">
					<TableHead>
						#
					</TableHead>
					<TableHeadSort modelName="vends.code" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('vends.code')" v-if="indexType !== 'customers'">
						Machine ID
					</TableHeadSort>
					<TableHead>
						<div class="flex flex-col space-y-2 max-w-[150px] mx-auto">
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
								Site
							</SingleSortItem>
							<SingleSortItem modelName="postcode" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('postcode')">
								Postcode
							</SingleSortItem>
							<SingleSortItem modelName="customers.selling_price_type" :sortKey="filters.sortKey" :sortBy="filters.sortBy" @sort-table="sortTable('customers.selling_price_type', false)">
								Ref Price
							</SingleSortItem>
							<span class="text-sm font-medium text-gray-700">
								Campaign
							</span>
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
					</TableHead>
				</tr>
			</thead>
			<tbody class="bg-white">
				<tr v-for="(vend, vendIndex) in vends.data" :key="vendIndex"
					class="cv-row divide-x divide-y-2 divide-gray-300 odd:bg-white even:bg-gray-100"
					:style="groupRowStyle(vend)">
					<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center">
						{{ vends.meta.from + vendIndex }}
					</TableData>
					<TableData :currentIndex="vendIndex" :totalLength="vends.length" inputClass="text-center" v-if="indexType !== 'customers'">
						<div class="flex flex-col space-y-2 items-center">
							<Link :href="'/settings/vend/' + vend.vend_id + '/update'" :class="[vend.is_active || vend.is_testing ? 'text-blue-600' : 'text-gray-400']" v-tooltip="'Open this machine\'s settings'">
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
						<div class="flex flex-col space-y-1 max-w-[150px]">
							<Link :href="'/settings/vend/' + vend.vend_id + '/update'" :class="[vend.is_active || vend.is_testing ? 'text-blue-600' : 'text-gray-400']" class="text-left hover:underline" v-if="permissions.includes('admin-access vend-customers') || permissions.includes('update machine-settings')" v-tooltip="'Open this machine\'s settings'">
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
								<span class="flex items-start space-x-1">
									<a v-if="vend.vend.productMapping" :href="'/product-mappings/' + vend.vend.productMapping.id + '/edit'" target="_blank" :title="vend.vend.productMapping.name" class="text-gray-800 text-xs font-medium underline decoration-gray-400 underline-offset-2 min-w-0 break-all">
										{{ vend.vend.productMapping.name }}
									</a>
									<span v-else-if="vend.product_mapping_name" :title="vend.product_mapping_name" class="text-xs text-gray-800 min-w-0 break-all">
										{{ vend.product_mapping_name }}
									</span>
									<!-- "New" badge: machine has an upcoming new mapping. Tooltip names it;
									     clicking opens THAT mapping (not the current one) in a new tab, the
									     same target the current-mapping link above uses.

									     Rendered as <a> only when the resolved mapping carries an id. Both
									     upcoming relations are eager-loaded `:id,name` so it always does
									     today — the <span> branch exists so a future select-trim degrades
									     to the old non-clickable badge instead of linking to
									     /product-mappings/undefined/edit.

									     target="_blank" matches the current-mapping link beside it. No
									     permission gate for the same reason: /product-mappings/{id}/edit is
									     auth-only, and the current-mapping link next to it is ungated too. -->
									<a
										v-if="getUpcomingMappingName(vend.vend) && getUpcomingMapping(vend.vend).id"
										:href="'/product-mappings/' + getUpcomingMapping(vend.vend).id + '/edit'"
										target="_blank"
										class="inline-flex shrink-0 items-center rounded px-1 py-0.5 text-[10px] font-semibold border w-fit bg-indigo-100 text-indigo-800 border-indigo-400 leading-none hover:bg-indigo-200 hover:underline cursor-pointer"
										:title="'Upcoming new mapping: ' + getUpcomingMappingName(vend.vend) + ' — click to open in a new tab'"
									>
										New
									</a>
									<span
										v-else-if="getUpcomingMappingName(vend.vend)"
										class="inline-flex shrink-0 items-center rounded px-1 py-0.5 text-[10px] font-semibold border w-fit bg-indigo-100 text-indigo-800 border-indigo-400 leading-none"
										:title="'Upcoming new mapping: ' + getUpcomingMappingName(vend.vend)"
									>
										New
									</span>
								</span>
								<!-- Mapping implementation date + age, so Ops can see how long the
								     current mapping has run and plan the next implementation. -->
								<span
									v-if="getMappingDateInfo(vend.vend)"
									class="text-[10px] text-gray-500 leading-none w-fit"
									:title="getMappingDateInfo(vend.vend).title"
								>
									Mapped {{ getMappingDateInfo(vend.vend).date }} · {{ getMappingDateInfo(vend.vend).ageLabel }}
								</span>

							</span>
							<span v-if="vend.person_id" class="flex flex-col">
								<span v-if="permissions.includes('admin-access vend-customers')">
									<a :class="[vend.person_id && vend.customer_is_active || vend.is_testing ? 'text-blue-700' : 'text-gray-400']" class="hover:underline" target="_blank" :href="'/customers/' + vend.customer_id + '/edit'" v-tooltip="'Open this site in the Site editor'">
											{{ vend.customer_id + 20000 }}
											<br>
											{{ vend.customer_name }}
									</a>
								</span>
								<span v-else :class="[vend.customer_is_active || vend.is_testing ? 'text-gray-800' : 'text-gray-400']">
									{{ vend.customer_name }}
								</span>

								<a target="_blank" :href="cmsEndpoint + '/person/' + vend.person_id + '/edit'" class="" v-if="permissions.includes('admin-access vend-customers')" v-tooltip="'Open this customer in CMS'">
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
									<a class="text-blue-700 hover:underline" target="_blank" :href="'/customers/' + vend.customer_id + '/edit'" v-tooltip="'Open this site in the Site editor'">
											{{ vend.customer_name }}
									</a>
								</span>
								<span v-else :class="[vend.customer_is_active || vend.is_testing ? 'text-gray-800' : 'text-gray-400']">
									<a class="text-blue-700 hover:underline" target="_blank" :href="'/customers/' + vend.customer_id + '/edit'" v-tooltip="'Open this site in the Site editor'">
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
							<div
								v-for="campaign in (vend.vend && vend.vend.campaigns) ? vend.vend.campaigns : []"
								:key="campaign.id"
								class="inline-flex flex-col rounded px-0.5 py-0.5 text-xs border w-fit bg-pink-100 text-pink-800 border-pink-300"
							>
								<span>{{ campaign.name }}</span>
								<span v-if="campaign.end_at" class="text-pink-600">
									Exp: {{ campaign.end_at }}
								</span>
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
									v-tooltip="'Open this location in Google Maps'"
								>
									<span class="text-blue-800 underline">GPS</span>
								</a>
							</span>


              <Link :href="'/vends/' + vend.vend_id + '/edit'" v-if="permissions.includes('admin-access vend-customers')" v-tooltip="'Open the full machine record'">
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
							<a :href="'/vends/' + vend.vend_id + '/temp/' + 1 " target="_blank" class="w-full" v-tooltip="'Open T1 temperature history'">
									<button
									type="button"
									class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-full text-right justify-center"
									:class="[(vend.is_online || vend.is_testing) && vend.is_temp_active ? (vend.temp > -15 ? 'bg-red-400 active:bg-red-500 hover:bg-red-600' : 'bg-green-400 active:bg-green-500 hover:bg-green-600') : 'bg-gray-300 active:bg-gray-500 hover:bg-gray-600']"
									v-if="vend.temp_updated_at"
									>
											<div class="flex items-center justify-center w-full">
												<span class="text-blue-800 underline">{{ vend.is_temp_error ? 'Error' : vend.temp }}</span>
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
							<a :href="'/vends/' + vend.vend_id + '/temp/' + 2 " target="_blank" class="w-full" v-tooltip="'Open T2 temperature history'">
									<button
											type="button"
											class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-full text-right justify-center"
											:class="[(vend.is_online || vend.is_testing) && vend.is_temp_active ? (vend.temp > -15 || vend.parameterJson['t2'] == constTempError ? 'bg-red-400 active:bg-red-500 hover:bg-red-600' : 'bg-green-400 active:bg-green-500 hover:bg-green-600') : 'bg-gray-300 active:bg-gray-500 hover:bg-gray-600']"
											v-if="vend.parameterJson && 't2' in vend.parameterJson"
									>
											<div class="flex items-center justify-center w-full">
												<span class="text-blue-800 underline">{{ vend.parameterJson['t2'] == constTempError ? 'Error' : vend.parameterJson['t2']/10 }}(t2)</span>
											</div>
									</button>
							</a>
							<a :href="'/vends/' + vend.vend_id + '/temp/' + 3 " target="_blank" class="w-full" v-tooltip="'Open T3 temperature history'">
									<button
											type="button"
											class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-full text-right justify-center"
											:class="[(vend.is_online || vend.is_testing) && vend.is_temp_active ? (vend.temp > -15 || vend.parameterJson['t3'] == constTempError ? 'bg-red-400 active:bg-red-500 hover:bg-red-600' : 'bg-green-400 active:bg-green-500 hover:bg-green-600') : 'bg-gray-300 active:bg-gray-500 hover:bg-gray-600']"
											v-if="vend.parameterJson && vend.parameterJson['t3'] && vend.parameterJson['t3'] != constTempError"
									>
											<div class="flex items-center justify-center w-full">
												<span class="text-blue-800 underline">{{ vend.parameterJson['t3'] == constTempError ? 'Error' : vend.parameterJson['t3']/10 }}(t3)</span>
											</div>
									</button>
							</a>
							<a :href="'/vends/' + vend.vend_id + '/temp/' + 4 " target="_blank" class="w-full" v-tooltip="'Open T4 temperature history'">
									<button
											type="button"
											class="inline-flex items-center px-4 py-2 border border-transparent rounded-md font-semibold text-xs tracking-wide focus:outline-none disabled:opacity-25 transition ease-in-out duration-150 text-black w-full text-right justify-center"
											:class="[(vend.is_online || vend.is_testing) && vend.is_temp_active ? (vend.temp > -15 || vend.parameterJson['t4'] == constTempError ? 'bg-red-400 active:bg-red-500 hover:bg-red-600' : 'bg-green-400 active:bg-green-500 hover:bg-green-600') : 'bg-gray-300 active:bg-gray-500 hover:bg-gray-600']"
											v-if="vend.parameterJson && vend.parameterJson['t4'] && vend.parameterJson['t4'] != constTempError"
									>
											<div class="flex items-center justify-center w-full">
												<span class="text-blue-800 underline">{{ vend.parameterJson['t4'] == constTempError ? 'Error' : vend.parameterJson['t4']/10 }}(t4)</span>
											</div>
									</button>
							</a>
							<span class="mt-1">
									{{ shortTimeAgo(vend.temp_updated_at) }}
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
						<div class="flex flex-col space-y-2 hover:bg-gray-100 p-2 rounded cursor-pointer transition duration-150 ease-in-out border border-transparent hover:border-gray-200" @click="onChannelOverviewClicked(vend)" v-tooltip="'View Channel Status'">
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
					</TableData>
				</tr>
				<tr v-if="!vends.data.length">
					<td colspan="6" class="relative whitespace-nowrap py-4 pr-4 pl-3 text-sm font-medium sm:pr-6 lg:pr-8 text-center">
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
<SmartFreezerChannelOverview
	v-if="showSmartChannelOverviewModal"
	:vend="vend"
	:showModal="showSmartChannelOverviewModal"
	@modalClose="onSmartChannelOverviewClosed"
>
</SmartFreezerChannelOverview>
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
:vends="selectedVends"
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

<!-- Coin Float history pop-up. Self-contained overlay (no shared Modal dep):
     opens on the Coin Float badge, lazy-loads 14 days of change events, shows
     the latest 20 in a table and exports the full window as CSV. -->
<div
	v-if="showCoinFloatModal"
	class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
	@click.self="onCoinFloatModalClose"
>
	<div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[85vh] flex flex-col">
		<div class="flex items-center justify-between border-b px-5 py-3">
			<div>
				<h3 class="text-base font-semibold text-gray-800">
					Coin Float History
					<span class="text-gray-500 font-normal">— Machine {{ coinFloatVend?.code }}</span>
				</h3>
				<p class="text-xs text-gray-500">Latest 20 changes shown · last 14 days · {{ operatorCountry.currency_symbol }}</p>
			</div>
			<button class="text-gray-400 hover:text-gray-700 text-xl leading-none" @click="onCoinFloatModalClose">&times;</button>
		</div>

		<div class="px-5 py-3 overflow-y-auto">
			<div v-if="coinFloatLoading" class="py-10 text-center text-gray-500 text-sm">Loading…</div>
			<div v-else-if="coinFloatError" class="py-10 text-center text-red-600 text-sm">
				{{ coinFloatError }}
			</div>
			<div v-else-if="!coinFloatLogs.length" class="py-10 text-center text-gray-500 text-sm">
				No coin float changes recorded in the last 14 days.
			</div>
			<table v-else class="w-full text-xs">
				<thead>
					<tr class="text-left text-gray-500 border-b">
						<th class="py-1.5 pr-2 font-semibold">Date / Time</th>
						<th class="py-1.5 px-2 font-semibold text-right">Coin Float</th>
						<th class="py-1.5 px-2 font-semibold text-right">Change</th>
						<th class="py-1.5 pl-2 font-semibold">Coin Acceptor</th>
					</tr>
				</thead>
				<tbody>
					<tr v-for="row in coinFloatLogs.slice(0, 20)" :key="row.id" class="border-b last:border-0">
						<td class="py-1.5 pr-2 tabular-nums text-gray-700">{{ formatCoinFloatTime(row.created_at) }}</td>
						<td class="py-1.5 px-2 text-right tabular-nums font-medium">{{ coinFloatDisplay(row.coin_cnt) }}</td>
						<td
							class="py-1.5 px-2 text-right tabular-nums"
							:class="row.delta === null ? 'text-gray-400' : (row.delta >= 0 ? 'text-green-700' : 'text-red-700')"
						>{{ coinFloatDelta(row.delta) }}</td>
						<td class="py-1.5 pl-2">{{ row.coin_stat == 3 ? 'Active' : (row.coin_stat == 1 ? 'Inactive' : 'NA') }}</td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="flex items-center justify-between border-t px-5 py-3">
			<span class="text-xs text-gray-500">{{ coinFloatLogs.length }} change(s) in window</span>
			<div class="flex gap-2">
				<button
					class="px-3 py-1.5 text-xs rounded border border-gray-300 text-gray-700 hover:bg-gray-50"
					@click="onCoinFloatModalClose"
				>Close</button>
				<button
					class="px-3 py-1.5 text-xs rounded bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50"
					:disabled="!coinFloatLogs.length"
					@click="exportCoinFloatCsv"
				>Export CSV (14 days)</button>
			</div>
		</div>
	</div>
</div>
</BreezeAuthenticatedLayout>
</template>

<style setup>
/* PERF (scroll): when "All" is selected the table can hold thousands of very
   heavy rows (each row inlines a per-channel <li> grid). content-visibility
   lets the browser skip layout/paint for rows that are off-screen, so
   scrolling stays smooth instead of repainting the whole tree.
   - contain-intrinsic-size reserves an estimated row height so the scrollbar
     and row positions stay stable; the `auto` keyword makes the browser
     remember each row's real height once it has been rendered.
   - Only <tbody> rows get this; <thead> stays fully rendered so its
     fixed-width header cells keep the column widths anchored (no jitter). */
.cv-row {
	content-visibility: auto;
	contain-intrinsic-size: auto 480px;
}
/* PERF (scroll): the shared TableHead applies `backdrop-blur-3xl` + a
   translucent bg to the sticky header. A backdrop-filter re-samples and
   re-blurs everything behind it on EVERY scroll frame as rows pass under
   the header — across a full-width header that is the dominant scroll cost.
   Scoped to this page's table only (via .cv-scroll), so the shared
   component and every other table that uses it are untouched. We drop the
   blur and make the header opaque (visually near-identical, just not
   see-through). */
.cv-scroll thead th {
	-webkit-backdrop-filter: none !important;
	backdrop-filter: none !important;
}
.cv-scroll thead th.bg-opacity-75 {
	--tw-bg-opacity: 1 !important;
	background-color: rgb(249 250 251) !important; /* solid gray-50 */
}
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
	import MentionTextarea from '@/Components/MentionTextarea.vue';
	import { ArrowDownTrayIcon, ArrowUpIcon, ArrowDownIcon, ChevronDoubleDownIcon, ChevronDoubleUpIcon, EllipsisHorizontalCircleIcon, ExclamationCircleIcon, MagnifyingGlassIcon, BackspaceIcon, PlayCircleIcon, ClipboardDocumentCheckIcon, MapPinIcon, CheckCircleIcon, XCircleIcon } from '@heroicons/vue/20/solid';
	import TableHead from '@/Components/TableHead.vue';
	import TableData from '@/Components/TableData.vue';
	import TableHeadSort from '@/Components/TableHeadSort.vue';
	import SingleSortItem from '@/Components/SingleSortItem.vue';
	import { ref, computed, onMounted, defineAsyncComponent, watch, nextTick } from 'vue';
	import { router, Link, Head, usePage } from '@inertiajs/vue3';
	import { Dropdown, Tooltip, Menu, vTooltip } from 'floating-vue';
	import moment from 'moment';
	import axios from 'axios';
	import { COIN_FLOAT_LOW_THRESHOLD } from '@/constants/vendThresholds';

	const AssignJob = defineAsyncComponent(() => import('@/Pages/Vend/AssignJob.vue'));
	const ChannelOverview = defineAsyncComponent(() => import('@/Pages/Vend/ChannelOverview.vue'));
	const SmartFreezerChannelOverview = defineAsyncComponent(() => import('@/Pages/Vend/SmartFreezerChannelOverview.vue'));
	const Create = defineAsyncComponent(() => import('@/Pages/Vend/Create.vue'));
	const Form = defineAsyncComponent(() => import('@/Pages/Vend/Form.vue'));
	const MapMarker = defineAsyncComponent(() => import('@/Components/MapMarker.vue'));
	const PickList = defineAsyncComponent(() => import('@/Pages/Vend/PickList.vue'));

	const props = defineProps({
			autoLoad: Boolean,
			cardTerminalOptions: [Array, Object],
			// 5-value Site Status options ([{id, name}, ...]) — only used
			// on the customers view. Server passes STATUSES_MAPPING + an "All"
			// sentinel.
			customerStatuses: Array,
			cmsEndpoint: String,
			constTempError: Number,
			dayOptions: [Array, Object],
			deliveryPlatformOptions: [Array, Object],
			deviceTypes: [Array, Object],
			driverOptions: Object,
			frequencyPerWeekOptions: [Array, Object],
			indexType: String,
			// Pre-Search aggregate cards — backend-computed over ALL rows
			// matching the filters (not capped by itemPerPage). Only set on
			// the initial non-autoload load; null after a Search, at which
			// point the cards fall back to the page-scoped totals/currentStats.
			// Shape: { vmCount, totals: {...}, current: {...} }.
			initialStats: Object,
			locationTypeOptions: Object,
			// Same-operator users for the Site Note @-mention dropdown — same
			// prop and shape as Customer/Summary.vue.
			mentionableUsers: { type: Array, default: () => [] },
			mapApiKey: String,
			nextDeliveryDriverOptions: [Array, Object],
			operatorOptions: Object,
			productMappingOptions: Object,
			// Active mappings (+ any still bound as an upcoming) for the
			// "Upcoming Mapping" filter. See VendController::indexCustomer.
			upcomingProductMappingOptions: Object,
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

	// Number of machines (VMs) backing the "Avg/VM" figure on the L30d
	// aggregate cards. The totals are summed over the rows on the current
	// page, so the per-machine average divides by the row count. Guard against
	// 0 so the cards never render NaN/Infinity before a search returns rows.
	// ── Deferred aggregates (perf) ──────────────────────────────────────────
	// The heavy $/stock/job columns are loaded in a 2nd background request so
	// the table paints fast on big page sizes ("All"). See VendController
	// @indexCustomer ($deferAggregates) + @customerIndexAggregates.
	//
	// KILL SWITCH: set to false to fully restore the old synchronous load —
	// the page stops sending defer_aggregates and never calls Phase 2.
	const ENABLE_DEFERRED_AGGREGATES = true;
	// Only defer for page sizes big enough to be slow; small pages compute
	// inline as before (no extra round-trip).
	const DEFER_PAGE_SIZES = ['All', 500, 200];
	// Phase-2 card totals once they arrive (overrides props.totals).
	const deferredTotals = ref(null);
	// True while the Phase-2 request is in flight (drives the table banner).
	const aggregatesLoading = ref(false);

	const vmCount = computed(() => {
		// Pre-Search: the backend's all-rows count (full filtered fleet, not
		// capped by itemPerPage). After Search: the page rows, as before.
		if (!hasSearched.value && props.initialStats) {
			const n = props.initialStats.vmCount ?? 0;
			return n > 0 ? n : 1;
		}
		const n = props.vends?.data?.length ?? 0;
		return n > 0 ? n : 1;
	});

	// Source for the "Last 30 days" card: pre-Search the backend-computed
	// all-rows initialStats.totals, after Search the page-scoped `totals`
	// prop (previous behaviour). Same keys/units either way.
	const cardTotals = computed(() => {
		if (!hasSearched.value && props.initialStats) {
			return props.initialStats.totals;
		}
		// After a deferred load, Phase 2 supplies the heavy card totals; until
		// it returns we fall back to props.totals (Sales filled, rest 0).
		return deferredTotals.value ?? props.totals;
	});

	// "Current" snapshot card — averages/counts over EVERY machine in the
	// current filter (props.vends.data, the page rows, default ~50). All five
	// figures are derived from per-row fields already present on each vend, so
	// this is a pure client-side computed (no backend/SQL touched).
	//   - stockQtyBal: avg of balance_percent (qty/capacity %)
	//   - stockSkuBal: avg of (100 − out_of_stock_sku_percent) (in-stock SKU %)
	//   - todayError:  avg of totals_json.one_day_error_rate (%)
	//   - greenCount/greenPct: share of machines whose L30D daily avg is at or
	//     above the fleet-wide "Overall Avg/day" baseline (mean of L30D daily
	//     avg across reporting VMs). This is fleet-RELATIVE and intentionally
	//     does NOT mirror the per-row Avg Sales/Day colour (still per-machine).
	//   - salesUpCount/salesUpPct: share of machines whose L30D sales
	//     (thirty_days_amount) beat last calendar month (last_mth_amount).
	//   - nextDayJobCount: machines whose next scheduled ops job is dated tomorrow.
	//   - refillableOver120..450: count of ALL machines whose Refillable Value
	//     (actual_stock_in_value, currency units) exceeds each fixed threshold.
	//   - nextDayRefillableOver120..450: same thresholds, restricted to
	//     next-day-job machines (the parenthesised figure on the tiles).
	const currentStats = computed(() => {
		// Pre-Search: backend-computed over ALL filtered rows (same shape,
		// same per-metric denominators — see VendController::computeCustomerIndexCardStats).
		if (!hasSearched.value && props.initialStats?.current) {
			return props.initialStats.current;
		}
		const rows = props.vends?.data ?? [];
		const n = rows.length;
		const empty = { total: 0, stockTotal: 0, errTotal: 0, stockQtyBal: 0, stockSkuBal: 0, todayError: 0, greenCount: 0, greenPct: 0, greenTotal: 0, refillableOver120: 0, refillableOver150: 0, refillableOver200: 0, refillableOver250: 0, refillableOver300: 0, refillableOver350: 0, refillableOver400: 0, refillableOver450: 0, salesUpCount: 0, salesUpTotal: 0, salesUpPct: 0, nextDayJobCount: 0, nextDayRefillableOver120: 0, nextDayRefillableOver150: 0, nextDayRefillableOver200: 0, nextDayRefillableOver250: 0, nextDayRefillableOver300: 0, nextDayRefillableOver350: 0, nextDayRefillableOver400: 0, nextDayRefillableOver450: 0 };
		if (!n) return empty;

		// "Overall Avg/day" baseline (fleet-wide): the mean of each VM's L30D
		// average daily sales (virtual_vend_records_thirty_days_amount_average,
		// currency units) over the VMs that report an avg/day figure. The
		// "L30D ≥ Overall Avg/day" card then counts how many VMs sit at or above
		// this single fleet mean. NOTE: this is a deliberately fleet-RELATIVE
		// rule and no longer mirrors the per-row Avg Sales/Day colour (which
		// stays per-machine: L30D ≥ that machine's own avg/day).
		let baselineSum = 0, baselineN = 0;
		for (const v of rows) {
			const json = v.vendTransactionTotalsJson;
			if (json && 'vend_records_amount_average_day' in json) {
				baselineSum += Number(v.virtual_vend_records_thirty_days_amount_average ?? 0);
				baselineN++;
			}
		}
		const overallAvgDaily = baselineN > 0 ? baselineSum / baselineN : 0;

		// "Next day" = tomorrow's date in the app/operator timezone. OpsJobResource
		// already serialises opsJob.date as a plain 'Y-m-d' app-TZ string, so a
		// string compare against moment().add(1,'day') is exact (no UTC shift).
		const tomorrow = moment().add(1, 'day').format('YYYY-MM-DD');

		// Each rate is averaged over the machines for which that metric is
		// actually DEFINED — not blindly over all n rows. Mixing "missing → 0"
		// across metrics is wrong: a machine with no channel data would count
		// as 0% Qty-Bal but 100% SKU-Bal. So Qty/SKU-Bal divide by the count of
		// rows that have vendChannelTotalsJson (stock data), and Today-Error by
		// the count that actually reports one_day_error_rate. Refillable>150 is
		// defined for every machine (no data = 0, simply not >150), so its
		// denominator is the full filtered count n.
		let stockTotal = 0, sumQtyBal = 0, sumSkuBal = 0;
		let errTotal = 0, sumErr = 0;
		let greenTotal = 0, greenCount = 0;
		let refillCount120 = 0, refillCount150 = 0, refillCount200 = 0, refillCount250 = 0;
		let refillCount300 = 0, refillCount350 = 0, refillCount400 = 0, refillCount450 = 0;
		let salesUpTotal = 0, salesUpCount = 0;
		let nextDayJobCount = 0;
		// Next-day-job subset of the Refillable threshold counts (shown in
		// parentheses on the two Refillable tiles).
		let nextRefill120 = 0, nextRefill150 = 0, nextRefill200 = 0, nextRefill250 = 0;
		let nextRefill300 = 0, nextRefill350 = 0, nextRefill400 = 0, nextRefill450 = 0;
		for (const v of rows) {
			if (v.vendChannelTotalsJson) {
				stockTotal++;
				sumQtyBal += Number(v.balance_percent ?? 0);
				sumSkuBal += (100 - Number(v.out_of_stock_sku_percent ?? 0));
			}
			const json = v.vendTransactionTotalsJson;
			if (json && 'one_day_error_rate' in json) {
				errTotal++;
				sumErr += Number(json.one_day_error_rate ?? 0);
			}
			// L30D ≥ Overall Avg/day — share of VMs whose L30D average daily
			// sales are at or above the fleet-wide baseline computed above. Same
			// qualifying set (VMs reporting an avg/day) as before, so the
			// denominator is unchanged; only the comparison baseline differs.
			if (json && 'vend_records_amount_average_day' in json) {
				greenTotal++;
				const l30d = Number(v.virtual_vend_records_thirty_days_amount_average ?? 0);
				if (l30d >= overallAvgDaily) greenCount++;
			}
			// % of VM, Sales L30D > LMonth — rolling 30-day sales beat the last
			// full calendar month. Both are cents in vendTransactionTotalsJson.
			if (json && 'thirty_days_amount' in json && 'last_mth_amount' in json) {
				salesUpTotal++;
				if (Number(json.thirty_days_amount ?? 0) > Number(json.last_mth_amount ?? 0)) salesUpCount++;
			}
			const refillVal = Number(v.actual_stock_in_value ?? 0);
			// # of Job, next day — VMs whose next scheduled ops job is dated tomorrow.
			// The Refillable tiles show each threshold's total count with this
			// next-day subset in parentheses.
			if (v.nextOpsJobItem?.opsJob?.date === tomorrow) {
				nextDayJobCount++;
				if (refillVal > 120) nextRefill120++;
				if (refillVal > 150) nextRefill150++;
				if (refillVal > 200) nextRefill200++;
				if (refillVal > 250) nextRefill250++;
				if (refillVal > 300) nextRefill300++;
				if (refillVal > 350) nextRefill350++;
				if (refillVal > 400) nextRefill400++;
				if (refillVal > 450) nextRefill450++;
			}
			if (refillVal > 120) refillCount120++;
			if (refillVal > 150) refillCount150++;
			if (refillVal > 200) refillCount200++;
			if (refillVal > 250) refillCount250++;
			if (refillVal > 300) refillCount300++;
			if (refillVal > 350) refillCount350++;
			if (refillVal > 400) refillCount400++;
			if (refillVal > 450) refillCount450++;
		}
		return {
			total: n,
			stockTotal,
			errTotal,
			stockQtyBal: stockTotal > 0 ? sumQtyBal / stockTotal : 0,
			stockSkuBal: stockTotal > 0 ? sumSkuBal / stockTotal : 0,
			todayError: errTotal > 0 ? sumErr / errTotal : 0,
			greenCount,
			greenTotal,
			greenPct: greenTotal > 0 ? (greenCount / greenTotal) * 100 : 0,
			refillableOver120: refillCount120,
			refillableOver150: refillCount150,
			refillableOver200: refillCount200,
			refillableOver250: refillCount250,
			refillableOver300: refillCount300,
			refillableOver350: refillCount350,
			refillableOver400: refillCount400,
			refillableOver450: refillCount450,
			salesUpCount,
			salesUpTotal,
			salesUpPct: salesUpTotal > 0 ? (salesUpCount / salesUpTotal) * 100 : 0,
			nextDayJobCount,
			nextDayRefillableOver120: nextRefill120,
			nextDayRefillableOver150: nextRefill150,
			nextDayRefillableOver200: nextRefill200,
			nextDayRefillableOver250: nextRefill250,
			nextDayRefillableOver300: nextRefill300,
			nextDayRefillableOver350: nextRefill350,
			nextDayRefillableOver400: nextRefill400,
			nextDayRefillableOver450: nextRefill450,
		};
	});

	// PERF: single cached source for "which rows are checkbox-selected". The
	// template previously ran vends.data.filter(...) inline in 5 places (two
	// :class bindings, two :disabled bindings, the AssignJob :vends prop) on
	// every re-render. Same output, computed once per change.
	const selectedVends = computed(() => vends.value.data.filter((v) => v.is_selected))

	// Grouped "stick together" cue on the Operation Dashboard. When the Grouped?
	// filter is on, sibling rows that belong to the same cluster
	// (customer_group_id) share a light colour — a soft left bar + faint tint —
	// so a co-located cluster reads as one block at a glance. Ungrouped rows and
	// the non-grouped view are untouched (returns {} → no inline style).
	const GROUP_COLORS = ['#6366f1', '#10b981', '#f59e0b', '#ef4444', '#0ea5e9', '#8b5cf6', '#ec4899', '#14b8a6'];
	function groupRowStyle(vend) {
		if (!filters.value.group_siblings || !vend || !vend.customer_group_id) return {};
		const c = GROUP_COLORS[vend.customer_group_id % GROUP_COLORS.length];
		return { boxShadow: `inset 4px 0 0 0 ${c}`, backgroundColor: `${c}14` };
	}

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
			// Site Status (5-value) — only used on the customers view.
			// Multi-select: stores an array of {id, value}; ids are forwarded as
			// `customer_status` (empty selection sent as ['all'] = every status).
			customer_status: [],
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
			upcomingProductMappings: [],
			vendConfigs: [],
			vendContracts: [],
			visited: true,
			zones: [],
			// "Grouped?" — when on, co-located sites (customer_group_id) travel
			// together: any member matching the filters pulls in all its
			// group-mates, ordered adjacent. Plain boolean; spread into router.get.
			// Defaults ON for the Operation Dashboard (customers view); the
			// machines view leaves it off since grouping is site-based.
			group_siblings: props.indexType === 'customers',
	})

	const showAssignJobModal = ref(false)
	const authOperator = usePage().props.auth.operator
	const baseUrl = ref('/vends/customers-lite')
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
	const locationTypeOptions = ref([])
	const nextDeliveryDriverOptions = ref([])
	const numberPerPageOptions = ref([])
	const operatorOptions = ref([])
	const pickLists = ref([])
	const productMappingOptions = ref([])
	const upcomingProductMappingOptions = ref([])
	const sellingPriceTypeOptions = ref([])
	const showAllFilters = ref(false)
	const showChannelOverviewModal = ref(false)
	const showSmartChannelOverviewModal = ref(false)
	const showCreateModal = ref(false)
	const showEditModal = ref(false)
	const showMapMarkerModal = ref(false)
	const showPickListModal = ref(false)
	const showProductAvailabilityModal = ref(false)
	const showCoinFloatModal = ref(false)
	const coinFloatVend = ref(null)
	const coinFloatLogs = ref([])
	const coinFloatLoading = ref(false)
	const coinFloatError = ref('')
	const statusOptions = ref([])
	// 5-value Site Status options — populated from props.customerStatuses
	// (Potential / New / Active / Pending / Inactive + "All" sentinel). Only
	// used on the customers view; the existing `statusOptions` is for vend
	// machine status and stays untouched.
	const customerStatusOptions = ref([])
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
// 5-value Site Status — controller passes {id, name}; remap to {id, value}
// to match the MultiSelect `label` prop used by every other dropdown here.
customerStatusOptions.value = (props.customerStatuses ?? []).map((s) => ({id: s.id, value: s.name}))
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

// "— None —" is a sentinel, not a mapping: the backend reads id 'none' as "this
// machine has no effective upcoming mapping". Kept next to 'all' so both
// sentinels sit at the top of the list, ahead of the alphabetical mappings.
upcomingProductMappingOptions.value = [
		{id: 'all', value: 'All'},
		{id: 'none', value: '— None —'},
		...props.upcomingProductMappingOptions.data.map((data) => {return {id: data.id, value: data.name}})
]

filters.value.cashless_mfg = cardTerminalOptions.value[0]
filters.value.delivery_platform_id = deliveryPlatformOptions.value[0]
filters.value.is_active = booleanOptions.value[1]
// Site Status — multi-select default = Active (id=2) + Removed (id=3), matching
// Customer/Index.vue. Clearing the selection sends ['all'] (every status).
filters.value.customer_status = customerStatusOptions.value.filter((s) => s.id === 2 || s.id === 3)
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
		if(cleanKey === 'group_siblings') filters.value.group_siblings = (value === 'true' || value === '1');

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
	hydrateMulti('customer_status', customerStatusOptions.value, 'customer_status');
	hydrateMulti('operators', operatorOptions.value, 'operators');
	hydrateMulti('preferredDays', dayOptions.value, 'preferredDays');
	hydrateMulti('productMappings', productMappingOptions.value, 'productMappings');
	hydrateMulti('upcomingProductMappings', upcomingProductMappingOptions.value, 'upcomingProductMappings');
	hydrateMulti('vendConfigs', vendConfigOptions.value, 'vendConfigs');
	hydrateMulti('vendContracts', vendContractOptions.value, 'vendContracts');
	hydrateMulti('vendModels', vendModelOptions.value, 'vendModels');
	hydrateMulti('vendPrefixes', vendPrefixOptions.value, 'vendPrefixes');
	hydrateMulti('zones', zoneOptions.value, 'zones');

	// Only auto-search if codes/channel_codes are non-empty AND the server hasn't already
	// loaded data (autoload=true means the server already ran the query — no need to re-fetch)
	if((urlParams.get('codes') || urlParams.get('channel_codes')) && !urlParams.get('autoload')) {
		onSearchFilterUpdated();
	} else if (props.autoLoad) {
		// Initial server-rendered page: if it came back deferred, pull the
		// heavy columns in now. No-op for normal (non-deferred) renders.
		maybeFetchDeferredAggregates();
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

// PERF: no `deep` here on purpose. The row set only ever changes when
// `vends.value` is reassigned wholesale (onSearchFilterUpdated's onFinish),
// which already swaps the `data` array reference and re-fires this watcher.
// With deep:true, EVERY row mutation — each keystroke in the Ops/Site Note
// textareas (v-model), every is_selected checkbox toggle — forced Vue to
// re-traverse all ~50-880 nested row objects AND re-POSTed
// /reports/machine-health/active-alerts each time (same ids, same response).
watch(() => vends.value.data, (newData) => {
	fetchActiveAlerts(newData);
}, { immediate: true });

const getMachineAlerts = (vend, group) => {
	const id = vend.vend_id || vend.id;
	const alerts = activeMachineHealthAlerts.value[id] || [];
	return alerts.filter(a => a.group === group);
};

// Shorten the backend "x seconds/minutes/... ago" string (VendResource uses
// diffForHumans()) into a compact unit on this page only — e.g.
// "39 seconds ago" -> "39s ago", "5 minutes ago" -> "5m ago". Done in the
// frontend so we don't touch the shared VendResource (used by many pages).
const shortTimeAgo = (str) => {
	if (!str) return str;
	return str
		.replace(/\bseconds?\b/, 's')
		.replace(/\bminutes?\b/, 'm')
		.replace(/\bhours?\b/, 'h')
		.replace(/\bdays?\b/, 'd')
		.replace(/\bweeks?\b/, 'w')
		.replace(/\bmonths?\b/, 'mo')
		.replace(/\byears?\b/, 'y')
		.replace(/(\d)\s+([smhdwy])/, '$1$2');
};

// PERF: pre-bucket the alerts per vend id once per alerts payload instead of
// re-running getAlertLabel + filter for every row on every re-render (each
// badge block calls getMachineAlertsGroup twice — once in v-if, once in
// v-for). Buckets are keyed by the joined `numbers` arg; only '1,2,3' and
// '4' are used in the template today, the function below falls back to the
// original filter for any other grouping. Output is identical.
const machineAlertsBuckets = computed(() => {
	const buckets = {};
	const map = activeMachineHealthAlerts.value || {};
	for (const id in map) {
		const alerts = map[id] || [];
		const g123 = [];
		const g4 = [];
		for (const a of alerts) {
			const label = getAlertLabel(a);
			if (!label) continue;
			if (label.startsWith('1') || label.startsWith('2') || label.startsWith('3')) g123.push(a);
			if (label.startsWith('4')) g4.push(a);
		}
		buckets[id] = { '1,2,3': g123, '4': g4 };
	}
	return buckets;
});

const EMPTY_ALERTS = [];

const getMachineAlertsGroup = (vend, numbers) => {
	const id = vend.vend_id || vend.id;
	const bucket = machineAlertsBuckets.value[id];
	if (!bucket) return EMPTY_ALERTS;
	const cached = bucket[numbers.join(',')];
	if (cached) return cached;
	// Fallback — same logic as before for groupings not pre-bucketed above.
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
		case 'R+U':   return 'R + U'
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
	// "Access Product(s)": location_fees_cents is blanked server-side for a
	// product-restricted viewer. Return null rather than 0 so the cell renders
	// empty - subtracting the (still-visible) external subsidy from 0 would
	// print a negative figure that looks like a real net fee.
	if (vend.location_fees_cents == null) return null
	return Number(vend.location_fees_cents || 0) - extSubCents(vend)
}

// Returns the RESOLVED upcoming new product mapping object ({id, name, ...}) for
// a machine, or null when there isn't one. An upcoming mapping can live directly
// on the vend (vend.upcomingProductMapping) or on its current mapping
// (productMapping.upcomingProductMapping). 'N/A' is treated as "no upcoming
// mapping". Drives the "New" badge in the machine column.
//
// Precedence: the vend's OWN upcoming wins first, falling back to the current
// mapping's preset upcoming. Since the Setting/Edit form now lets ops manually
// set upcoming_product_mapping_id per machine, that per-machine value is the
// source of truth — matching the promotion logic (OpsJobController) and
// OpsJob/Edit.vue. Only fall back to the mapping's preset when the vend has no
// own upcoming (legacy rows never saved through the new form).
//
// Returns the whole object (not just the name) so the "New" badge can link
// straight to /product-mappings/{id}/edit. Both relations are eager-loaded as
// `:id,name` in VendController::customerIndex, so `id` is always present in
// practice — but the badge still falls back to plain text if it ever isn't,
// rather than rendering a link to /product-mappings/undefined/edit.
function getUpcomingMapping(vendData) {
	if (!vendData) return null
	const fromVend = vendData.upcomingProductMapping
		&& vendData.upcomingProductMapping.name !== 'N/A'
			? vendData.upcomingProductMapping
			: null
	// `&& fromVend.name` keeps EXACT parity with the pre-2026-08 version, which
	// held the name string here and so fell through to the mapping's preset when
	// the name was empty. Returning the object made that check vacuously true.
	// No mapping on live has a blank name (product_mappings.name is NOT NULL and
	// the controller validates it), so this is parity insurance, not a live fix.
	if (fromVend && fromVend.name) return fromVend
	const fromMapping = vendData.productMapping
		&& vendData.productMapping.upcomingProductMapping
		&& vendData.productMapping.upcomingProductMapping.name !== 'N/A'
			? vendData.productMapping.upcomingProductMapping
			: null
	return fromMapping
}

// Name-only wrapper — kept because the Last 2 Job / Upcoming Job columns render
// the name inline. Delegates so there is ONE resolution path: changing the
// precedence above can never leave the badge and those columns disagreeing.
function getUpcomingMappingName(vendData) {
	const mapping = getUpcomingMapping(vendData)
	return mapping ? mapping.name : null
}

// Last Job mapping (the "Last Job" column) — frozen-aware. Once the last-job
// item is frozen, use the snapshot mapping names so the column reflects what the
// mapping was at that job; otherwise derive live from the vend mapping relations.
function lastJobMappingHas(oji) {
	if (!oji) return false
	if (oji.frozen_at) return !!(oji.frozen_mapping_current_name || lastJobMappingUpcoming(oji))
	return !!(oji.vend && (oji.vend.upcomingProductMapping || (oji.vend.productMapping && oji.vend.productMapping.upcomingProductMapping)))
}
function lastJobMappingCurrent(oji) {
	if (!oji) return ''
	if (oji.frozen_at) return oji.frozen_mapping_current_name || ''
	return oji.vend && oji.vend.productMapping ? oji.vend.productMapping.name : ''
}
function lastJobMappingUpcoming(oji) {
	if (!oji) return ''
	if (oji.frozen_at) {
		const via = oji.frozen_mapping_upcoming_via_current
		const direct = oji.frozen_mapping_upcoming_direct
		if (via && via !== 'N/A') return via
		if (direct && direct !== 'N/A') return direct
		return ''
	}
	if (!oji.vend) return ''
	// Prefer the vend's OWN manually-set upcoming, then fall back to the current
	// mapping's preset upcoming (matches getUpcomingMappingName + the promotion
	// logic). Frozen rows above keep their snapshot precedence untouched.
	if (oji.vend.upcomingProductMapping && oji.vend.upcomingProductMapping.name !== 'N/A') {
		return oji.vend.upcomingProductMapping.name
	}
	if (oji.vend.productMapping && oji.vend.productMapping.upcomingProductMapping && oji.vend.productMapping.upcomingProductMapping.name !== 'N/A') {
		return oji.vend.productMapping.upcomingProductMapping.name
	}
	return ''
}

// Mapping implementation date for a machine. binded_at is stamped (server-side,
// in the operator's timezone) whenever a vend's product mapping is changed or an
// upcoming mapping is promoted to current. Lets Ops see how long the current
// mapping has run so they can plan the next implementation. Returns null when
// binded_at is unset (e.g. mappings bound before the field existed).
// PERF: memoized per vend object (WeakMap) — the template calls this up to
// 3× per row per render, and each cold call builds several moment instances.
// The cache entry is keyed on binded_at AND today's date, so the result is
// byte-identical to recomputing (including the day-age rolling over at
// midnight or after a data reload swaps in new row objects).
const mappingDateInfoCache = new WeakMap()
function getMappingDateInfo(vendData) {
	if (!vendData || !vendData.binded_at) return null
	const today = moment().format('YYYY-MM-DD')
	const hit = mappingDateInfoCache.get(vendData)
	if (hit && hit.bindedAt === vendData.binded_at && hit.today === today) return hit.info
	const binded = moment(vendData.binded_at)
	if (!binded.isValid()) return null
	const days = moment().startOf('day').diff(binded.clone().startOf('day'), 'days')
	const ageLabel = days <= 0 ? 'today' : days === 1 ? '1 day' : `${days} days`
	const info = {
		date: binded.format('YYMMDD'),
		ageLabel,
		title: `Current mapping implemented on ${binded.format('YYMMDD HH:mm')} (${ageLabel} ago)`,
	}
	mappingDateInfoCache.set(vendData, { bindedAt: vendData.binded_at, today, info })
	return info
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

// VM Binding History cell — renders one entry as
// "<vend code> (<vend prefix>) <yymmdd>". Built as a single string (rather
// than adjacent template nodes) because Vue's whitespace condensing eats the
// separating spaces between interpolations. Every part is optional: the bind
// log only starts partway through the fleet's life, so an entry can carry a
// machine with no date, and older machines may have no prefix on record.
function vmBindingLabel(entry) {
		if (!entry) {
				return ''
		}
		const parts = []
		if (entry.vend_code !== null && entry.vend_code !== undefined && entry.vend_code !== '') {
				parts.push(String(entry.vend_code))
		}
		if (entry.vend_prefix_name) {
				parts.push('(' + entry.vend_prefix_name + ')')
		}
		if (entry.bound_at_short) {
				parts.push(entry.bound_at_short)
		}
		return parts.join(' ')
}

// Avg Mthly Sales $ — true average monthly sales over the machine's operating
// lifetime, NOT a 30-day projection. Numerator is lifetime sales
// (vend_records_amount_latest, raw minor units). Denominator is the COUNT of
// calendar months the machine has operated in, inclusive of both the begin
// month and the current month — e.g. a machine that started 2026-03-10 and is
// viewed in May 2026 has operated in Mar/Apr/May = 3 months, regardless of the
// day of month it started. The begin month is floored at 2023-01 (earliest
// reliable transaction data) so an abnormally old/garbage begin_date can't
// inflate the month count and crush the average. Floor of 1 month guards
// against bad data.
function avgMthlySales(vend) {
		const totals = vend.vendTransactionTotalsJson
		if (!totals || !('vend_records_amount_latest' in totals)) {
				return 0
		}
		const exponent = operatorCountry.currency_exponent ?? 2
		const lifetime = (totals['vend_records_amount_latest'] || 0) / Math.pow(10, exponent)

		// Single source of truth — shared from config/reporting.php so each
		// per-country deployment's floor matches the backend lifetime numerator.
		const floorStr = (usePage().props.reportingFloorDate || '2023-01-01')
		const FLOOR = new Date(floorStr + 'T00:00:00')
		let begin = vend.begin_date ? new Date(vend.begin_date + 'T00:00:00') : null
		if (!begin || isNaN(begin.getTime()) || begin < FLOOR) {
				begin = FLOOR
		}

		const now = new Date()
		// Inclusive calendar-month count between begin month and current month.
		const months = Math.max(
				1,
				(now.getFullYear() - begin.getFullYear()) * 12 + (now.getMonth() - begin.getMonth()) + 1
		)

		return lifetime / months
}

function onChannelOverviewClicked(vendData) {
		vend.value = vendData
		// Smart freezers report no channel telemetry, so the vending overview
		// would render blank. Route them to the planogram-driven 2D basket view.
		if (vendData.product_mapping_is_smart) {
			showSmartChannelOverviewModal.value = true
		} else {
			showChannelOverviewModal.value = true
		}
}

function onChannelOverviewClosed() {
		showChannelOverviewModal.value = false
}

function onSmartChannelOverviewClosed() {
		showSmartChannelOverviewModal.value = false
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

// ── Coin Float history pop-up ────────────────────────────────────────────
const coinFloatExponent = () => operatorCountry.currency_exponent ?? 2

function coinFloatDisplay(raw) {
	if (raw === null || raw === undefined) return '—'
	return (raw / Math.pow(10, coinFloatExponent())).toFixed(2)
}

function coinFloatDelta(delta) {
	if (delta === null || delta === undefined) return '—'
	const v = delta / Math.pow(10, coinFloatExponent())
	return (v >= 0 ? '+' : '') + v.toFixed(2)
}

function formatCoinFloatTime(ts) {
	if (!ts) return '—'
	const d = new Date(ts)
	if (isNaN(d.getTime())) return ts
	const pad = (n) => String(n).padStart(2, '0')
	return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`
}

function onCoinFloatClicked(vendData) {
	coinFloatVend.value = vendData
	coinFloatLogs.value = []
	coinFloatError.value = ''
	coinFloatLoading.value = true
	showCoinFloatModal.value = true

	axios.get(`/vends/${vendData.id}/coin-float-history`, { params: { days: 14 } })
		.then((response) => {
			coinFloatLogs.value = response.data?.data ?? []
		})
		.catch((error) => {
			coinFloatLogs.value = []
			coinFloatError.value = 'Could not load coin float history'
				+ (error?.response?.status ? ` (HTTP ${error.response.status})` : '')
				+ '. The endpoint may be unreachable or the table not yet migrated.'
		})
		.finally(() => {
			coinFloatLoading.value = false
		})
}

function onCoinFloatModalClose() {
	showCoinFloatModal.value = false
}

function exportCoinFloatCsv() {
	if (!coinFloatLogs.value.length) return

	const header = ['Machine ID', 'Date/Time', 'Coin Float', 'Change', 'Coin Acceptor']
	const rows = coinFloatLogs.value.map((row) => {
		const acceptor = row.coin_stat == 3 ? 'Active' : (row.coin_stat == 1 ? 'Inactive' : 'NA')
		return [
			coinFloatVend.value?.code ?? '',
			formatCoinFloatTime(row.created_at),
			coinFloatDisplay(row.coin_cnt),
			row.delta === null || row.delta === undefined ? '' : coinFloatDelta(row.delta),
			acceptor,
		]
	})

	const escape = (val) => {
		const s = String(val ?? '')
		return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s
	}
	const csv = [header, ...rows].map((r) => r.map(escape).join(',')).join('\n')

	const blob = new Blob(["﻿" + csv], { type: 'text/csv;charset=utf-8;' })
	const url = URL.createObjectURL(blob)
	const link = document.createElement('a')
	link.href = url
	link.download = `coin-float-${coinFloatVend.value?.code ?? 'machine'}-14d.csv`
	document.body.appendChild(link)
	link.click()
	document.body.removeChild(link)
	URL.revokeObjectURL(url)
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
			// Defer the heavy columns for big page sizes so the table paints
			// fast; the backend ignores this when sorting by a heavy column.
			defer_aggregates: (ENABLE_DEFERRED_AGGREGATES && DEFER_PAGE_SIZES.includes(filters.value.numberPerPage?.id)) ? 1 : 0,
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
			customer_status: (filters.value.customer_status?.length ? filters.value.customer_status.map((s) => s.id) : ['all']),
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
			// Overrides the object array spread by ...filters.value above — the
			// backend expects bare ids (numeric, or the 'none' sentinel).
			upcomingProductMappings: filters.value.upcomingProductMappings.map((pm) => { return pm.id }),
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
					// Clear any prior deferred state so a non-deferred load (e.g.
					// switching All → 50) doesn't keep stale Phase-2 totals/banner.
					deferredTotals.value = null
					aggregatesLoading.value = false
					// If the server returned a deferred page, fetch the heavy
					// columns now and merge them in.
					maybeFetchDeferredAggregates()
			},
	})
}

// Phase 2 of the deferred load: when the server returned a deferred page
// (totals.deferred === true), POST the on-screen rows and merge the heavy
// $/stock/job columns + recomputed card totals back in. No-op when deferral
// is off or the page wasn't deferred. Fully revertible via the kill switch.
function maybeFetchDeferredAggregates() {
	if (!ENABLE_DEFERRED_AGGREGATES) return;
	if (!props.totals || props.totals.deferred !== true) return;

	const list = vends.value?.data ?? [];
	const rows = list
			.filter(r => r && r.customer_id != null)
			.map(r => ({ vend_id: r.vend_id ?? null, customer_id: r.customer_id }));
	if (!rows.length) { deferredTotals.value = null; return; }

	aggregatesLoading.value = true;
	deferredTotals.value = null;
	axios.post('/vends/customers/aggregates', {
			rows,
			operators: filters.value.operators.filter(o => o).map(o => o.id),
	})
	.then(({ data: resp }) => {
			const map = (resp && resp.rows) ? resp.rows : {};
			const target = vends.value?.data ?? [];
			for (const row of target) {
					const key = row.vend_id != null ? String(row.vend_id) : ('cust-' + row.customer_id);
					const agg = map[key];
					if (agg) Object.assign(row, agg);
			}
			// New array reference so the table + cards re-render with merged values.
			vends.value = { ...vends.value, data: [...target] };
			if (resp && resp.totals) deferredTotals.value = resp.totals;
			aggregatesLoading.value = false;
	})
	.catch(() => {
			// Phase 2 failed — recover by loading the heavy columns the normal
			// (synchronous) way so the user never sees permanent placeholder 0s.
			router.reload({
					data: { defer_aggregates: 0 },
					only: ['vends', 'totals'],
					preserveScroll: true,
					onFinish: () => {
							vends.value = getVendsField();
							deferredTotals.value = null;
							aggregatesLoading.value = false;
					},
			});
	});
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

// Auto-grow the inline-edit textareas (Ops Note + Site Note) so the
// full content is visible without scrolling inside the cell. Bound via
// :ref-callback (initial mount + after vends partial-reload swaps row
// instances) and via @input for live typing. nextTick guarantees the
// new value is in the DOM before we measure scrollHeight.
// PERF: skip re-measuring when the content hasn't changed since the last
// grow. The inline :ref="(el) => autoGrowTextarea(el)" callbacks are
// re-created on every re-render of this (very large) page, so Vue re-invokes
// them for every textarea on every render — and each scrollHeight read forces
// a full layout pass (2 textareas × every row × every render). The height
// only depends on the value (the wrapper width is fixed at w-[82px]), so
// re-measuring the same value is pure waste. @input still passes here with a
// changed value, so live typing keeps auto-growing as before. The last-grown
// value is tracked in a WeakMap (not a data- attribute) so the rendered DOM
// stays byte-identical; entries are GC'd with their elements.
const autoGrowLastValue = new WeakMap();
function autoGrowTextarea(el) {
	if (!el) return;
	if (autoGrowLastValue.get(el) === el.value) return;
	autoGrowLastValue.set(el, el.value);
	nextTick(() => {
		el.style.height = 'auto';
		el.style.height = el.scrollHeight + 'px';
	});
}

// Same shape as onNotesChanged — separate endpoint so the two free-text
// fields (Site Note for finance/admin, Ops Note for refilling/operations)
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
				frequency_per_week_status: filters.value.frequency_per_week_status.map((frequency) => { return frequency.id }),
				location_type_id: filters.value.locationType.id,
				operators: filters.value.operators.map((operator) => { return operator.id }),
				preferredDays: filters.value.preferredDays.map((preferredDay) => { return preferredDay.id }),
				is_active: filters.value.is_active.id,
				customer_status: (filters.value.customer_status?.length ? filters.value.customer_status.map((s) => s.id) : ['all']),
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
