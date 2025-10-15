<template>
  <Head title="Edit Machine Parameters" />
  <BreezeAuthenticatedLayout>
    <template #header>
      <div class="flex space-x-2 items-center">
        <span>
          Edit APK Settings
        </span>
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
          {{ apkSetting.name }}
        </h2>
      </div>
    </template>

     <div class="m-2 sm:mx-5 sm:my-3 px-1 sm:px-2 lg:px-3">
      <div class="mt-6 flex flex-col">
       <div class="-my-2 -mx-4 sm:-mx-6 lg:-mx-8">
        <div class="shadow-sm ring-1 ring-black ring-opacity-5 p-5 mb-3">
          <form @submit.prevent="submit" id="submit">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-6 pb-5 mb-3">
            <div class="sm:col-span-5">
              <FormInput v-model="form.name">
                <div class="text-base">
                  Name
                </div>
              </FormInput>
            </div>

            <div class="sm:col-span-5">
              <FormTextarea v-model="form.remarks">
                <div class="text-base">
                  Remarks
                </div>
              </FormTextarea>
            </div>

            <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-base font-medium text-gray-700">
                Background Video/ Picture
              </label>
              <span class="text-sm text-gray-600">
                (MainView Default background type)
              </span>
              <MultiSelect
                v-model="form.bannerKind"
                :options="promoBannerKindOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
            </div>

            <div class="sm:col-span-3">
              <FormInput v-model="form.poweredBy">
                <div class="flex flex-col space-y-1">
                  <span class="text-base">
                    Display Text
                  </span>
                  <span class="text-sm text-gray-600">
                    (top right corner text)
                  </span>
                </div>
              </FormInput>
            </div>

            <div class="sm:col-span-3">
              <FormInput v-model="form.supportContactNum">
                <div class="flex flex-col space-y-1">
                  <span class="text-base">
                    Support contact num
                  </span>
                </div>
              </FormInput>
            </div>

            <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-base font-medium text-gray-700">
                Pricing Source
              </label>
              <MultiSelect
                v-model="form.selectedPricingSource"
                :options="pricingSourceOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
            </div>

            <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-base font-medium text-gray-700">
                Enable Debug Mode
              </label>
              <MultiSelect
                v-model="form.enableDebugMode"
                :options="booleanStrictOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
            </div>

            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center ">
                  <div class="flex flex-col items-center">
                    <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded-md"> DCVend </span>
                  </div>
                </div>
              </div>
            </div>

            <div class="sm:col-span-5">
              <FormInput v-model="form.dcvendFreePlanPromoValue" disabled="true">
                <div class="text-base">
                  Free Plan Promo Rate
                </div>
              </FormInput>
            </div>

            <div class="sm:col-span-5">
              <FormInput v-model="form.dcvendGoldPlanPromoValue" disabled="true">
                <div class="text-base">
                  Gold Plan Promo Rate
                </div>
              </FormInput>
            </div>

            <div class="sm:col-span-5">
              <FormInput v-model="form.dcvendPlatinumPlanPromoValue" disabled="true">
                <div class="text-base">
                  Platinum Plan Promo Rate
                </div>
              </FormInput>
            </div>

            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center ">
                  <div class="flex flex-col items-center">
                    <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded-md"> Default Video(s) </span>
                    <span class="text-sm">
                      (device file location: Internal Memory/Android/data/com.venderroute/files/DefaultMedia/Videos)
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <div class="sm:col-span-6">
              <AttachmentList
                :items="apkSetting.videos"
              >
              </AttachmentList>
            </div>

            <div class="sm:col-span-6" v-if="apkSetting.id">
              <DropzoneFileInput
                :endpoint="'/apk-settings/' + apkSetting.id + '/upload-videos'"
                :accepted-files="'video/*'"
                :max-filesize="10"
                >
              </DropzoneFileInput>
            </div>

            <hr class="sm:col-span-6">

            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center ">
                  <div class="flex flex-col items-center">
                    <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded-md"> Default Picture(s) </span>
                    <span class="text-sm">
                      (device file location: Internal Memory/Android/data/com.venderroute/files/DefaultMedia/Pictures)
                    </span>
                  </div>
                </div>
              </div>
            </div>


            <div class="sm:col-span-6">
              <AttachmentList
                :items="apkSetting.images"
              >
              </AttachmentList>
            </div>
            <div class="sm:col-span-6" v-if="apkSetting.id">
              <DropzoneFileInput
                :endpoint="'/apk-settings/' + apkSetting.id + '/upload-images'"
                :accepted-files="'image/*'"
                :max-filesize="1"
                >
              </DropzoneFileInput>
            </div>

            <hr class="sm:col-span-6">

            <div class="sm:col-span-6 py-2">
              <span class="flex space-x-1">
                <Button
                  type="button"
                  class="bg-blue-400 hover:bg-blue-500 text-white flex space-x-1"
                  @click.prevent="isShowCampaignSection = !isShowCampaignSection"
                >
                  <div class="flex space-x-2 items-center" v-if="!isShowCampaignSection">
                    <ArrowDownCircleIcon class="w-4 h-4"></ArrowDownCircleIcon>
                    <span>
                      Show Campaign Section
                    </span>
                  </div>
                  <div class="flex space-x-2 items-center" v-if="isShowCampaignSection">
                    <ArrowUpCircleIcon class="w-4 h-4"></ArrowUpCircleIcon>
                    <span>
                      Hide Campaign Section
                    </span>
                  </div>
                </Button>
              </span>
            </div>

            <hr class="sm:col-span-6" v-if="!isShowCampaignSection">

            <div class="sm:col-span-6 grid grid-cols-1 gap-3 sm:grid-cols-6 pb-5 mb-3" v-if="isShowCampaignSection">
            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-start">
                  <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> Campaigns </span>
                </div>
              </div>
            </div>


            <div class="sm:col-span-4">
              <label for="text" class="flex justify-start text-lg font-bold text-gray-700">
                Enable Campaign Advertisement?
              </label>
              <span class="text-sm text-gray-600">
                (Enable Main Banner text show on MainView)
              </span>
              <MultiSelect
                v-model="form.enablePromoHeaderText"
                :options="booleanStrictOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
            </div>

            <div class="sm:col-span-3">
              <DatePicker v-model="form.headerTextStartDate">
                <div class="flex flex-col space-y-1">
                  <span class="text-base">
                    Campaign Advertisement Start Date
                  </span>
                </div>
              </DatePicker>
            </div>

            <div class="sm:col-span-3">
              <DatePicker v-model="form.headerTextEndDate">
                <div class="flex flex-col space-y-1">
                  <span class="text-base">
                    Campaign Advertisement End Date
                  </span>
                </div>
              </DatePicker>
            </div>

            <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-base font-medium text-gray-700">
                Campaign Advertisement Material
              </label>
              <span class="text-sm text-gray-600">
                (MainView Campaign background type picture/video)
              </span>
              <MultiSelect
                v-model="form.promoBannerKind"
                :options="promoBannerKindOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
            </div>


            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center ">
                  <div class="flex flex-col items-center">
                    <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded-md"> Campaign Video(s) </span>
                    <span class="text-sm">
                      (device file location: Internal Memory/Android/data/com.venderroute/files/Campaign/Videos)
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <div class="sm:col-span-6">
              <AttachmentList
                :items="apkSetting.campaignVideos"
              >
              </AttachmentList>
            </div>

            <div class="sm:col-span-6" v-if="apkSetting.id">
              <DropzoneFileInput
                :endpoint="'/apk-settings/' + apkSetting.id + '/upload-campaign-videos'"
                :accepted-files="'video/*'"
                :max-filesize="10"
                >
              </DropzoneFileInput>
            </div>

            <hr class="sm:col-span-6">

            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center ">
                  <div class="flex flex-col items-center">
                    <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded-md"> Campaign Picture(s) </span>
                    <span class="text-sm">
                      (device file location: Internal Memory/Android/data/com.venderroute/files/Campaign/Pictures)
                    </span>
                  </div>
                </div>
              </div>
            </div>

            <div class="sm:col-span-6">
              <AttachmentList
                :items="apkSetting.campaignImages"
              >
              </AttachmentList>
            </div>
            <div class="sm:col-span-6" v-if="apkSetting.id">
              <DropzoneFileInput
                :endpoint="'/apk-settings/' + apkSetting.id + '/upload-campaign-images'"
                :accepted-files="'image/*'"
                :max-filesize="1"
                >
              </DropzoneFileInput>
            </div>

            <div class="sm:col-span-5">
              <FormInput v-model="form.promoHeaderText">
                <div class="flex flex-col space-y-1">
                  <span class="text-base">
                    Campaign Running Text 1
                  </span>
                  <span class="text-sm text-gray-600">
                    (located at Main Page)
                  </span>
                </div>
              </FormInput>
            </div>

            <div class="sm:col-span-5">
              <FormInput v-model="form.promoRunningText">
                <div class="flex flex-col space-y-1">
                  <span class="text-base">
                    Campaign Running Text 2
                  </span>
                  <span class="text-xs text-gray-600">
                    (located on top of Soft Keypad)
                  </span>
                </div>
              </FormInput>
            </div>

            <hr class="sm:col-span-6 my-2">

            <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-base font-medium text-gray-700">
                Enable P2 Price?
              </label>
              <MultiSelect
                v-model="form.enableP2Price"
                :options="booleanStrictOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
            </div>

            <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-base font-medium text-gray-700">
                Disable P1 P2 Cross Group?
              </label>
              <span class="text-sm text-gray-600">
                (Disable Old Discount Logic cross group, old discount logic can cross group to apply)
              </span>
              <MultiSelect
                v-model="form.disableP1P2CrossGrp"
                :options="booleanStrictOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
            </div>

            <hr class="sm:col-span-6 my-2">

            <div class="sm:col-span-4">
              <label for="text" class="flex justify-start text-lg font-bold text-gray-700">
                Enable Buy 1 Free 1?
              </label>
              <span class="text-sm text-gray-600">
                (Enable buy1free1 Campaign)
              </span>
              <MultiSelect
                v-model="form.enableBuy1Free1"
                :options="booleanStrictOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
            </div>

            <div class="sm:col-span-3">
              <FormInput v-model="form.buy1free1X">
                <div class="flex flex-col space-y-1">
                  <span class="text-base">
                    Buy 1 Free 1 (Buy Group)
                  </span>
                  <span class="text-sm text-gray-600">
                    (buy1free1 buy group, *cannot same with buy2free1X, numbers only)
                  </span>
                </div>
              </FormInput>
            </div>

            <div class="sm:col-span-3">
              <FormInput v-model="form.buy1free1Y">
                <div class="flex flex-col space-y-1">
                  <span class="text-base">
                    Buy 1 Free 1 (Free Group)
                  </span>
                  <span class="text-sm text-gray-600">
                    (buy1free1 free group, numbers only)
                  </span>
                </div>
              </FormInput>
            </div>

            <div class="sm:col-span-3">
              <DatePicker v-model="form.buy1free1StartDate">
                <div class="flex flex-col space-y-1">
                  <span class="text-base">
                    Buy 1 Free 1 Start Date
                  </span>
                  <span class="text-sm text-gray-600">
                    (buy1free1 campaign starting time)
                  </span>
                </div>
              </DatePicker>
            </div>

            <div class="sm:col-span-3">
              <DatePicker v-model="form.buy1free1EndDate">
                <div class="flex flex-col space-y-1">
                  <span class="text-base">
                    Buy 1 Free 1 End Date
                  </span>
                  <span class="text-sm text-gray-600">
                    (buy1free1 campaign ending time)
                  </span>
                </div>
              </DatePicker>
            </div>

            <hr class="sm:col-span-6 my-2">

            <div class="sm:col-span-4">
              <label for="text" class="flex justify-start text-lg font-bold text-gray-700">
                Enable Buy 2 Free 1?
              </label>
              <span class="text-sm text-gray-600">
                (Enable buy2free1 Campaign)
              </span>
              <MultiSelect
                v-model="form.enableBuy2Free1"
                :options="booleanStrictOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
            </div>

            <div class="sm:col-span-3">
              <FormInput v-model="form.buy2free1X">
                <div class="flex flex-col space-y-1">
                  <span class="text-base">
                    Buy 2 Free 1 (Buy Group)
                  </span>
                  <span class="text-sm text-gray-600">
                    (buy2free1 buy group, *cannot same with buy1free1X, numbers only)
                  </span>
                </div>
              </FormInput>
            </div>

            <div class="sm:col-span-3">
              <FormInput v-model="form.buy2free1Y">
                <div class="flex flex-col space-y-1">
                  <span class="text-base">
                    Buy 2 Free 1 (Free Group)
                  </span>
                  <span class="text-sm text-gray-600">
                    (buy2free1 buy group, numbers only)
                  </span>
                </div>
              </FormInput>
            </div>

            <div class="sm:col-span-3">
              <DatePicker v-model="form.buy2free1StartDate">
                <div class="flex flex-col space-y-1">
                  <span class="text-base">
                    Buy 2 Free 1 Start Date
                  </span>
                  <span class="text-sm text-gray-600">
                    (buy2free1 campaign starting time)
                  </span>
                </div>
              </DatePicker>
            </div>

            <div class="sm:col-span-3">
              <DatePicker v-model="form.buy2free1EndDate">
                <div class="flex flex-col space-y-1">
                  <span class="text-base">
                    Buy 2 Free 1 End Date
                  </span>
                  <span class="text-sm text-gray-600">
                    (buy2free1 campaign ending time)
                  </span>
                </div>
              </DatePicker>
            </div>

            <hr class="sm:col-span-6 my-2">

            <div class="sm:col-span-4">
              <label for="text" class="flex justify-start text-lg font-bold text-gray-700">
                Enable Bundle Discount?
              </label>
              <span class="text-sm text-gray-600">
                (Enable bundle discount Campaign)
              </span>
              <MultiSelect
                v-model="form.enableBundleDiscount"
                :options="booleanStrictOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
            </div>

            <div class="sm:col-span-3">
              <DatePicker v-model="form.bundleStartDate">
                <div class="flex flex-col space-y-1">
                  <span class="text-base">
                    Bundle Start Date
                  </span>
                  <span class="text-sm text-gray-600">
                    (bundle discount Campaign starting time)
                  </span>
                </div>
              </DatePicker>
            </div>

            <div class="sm:col-span-3">
              <DatePicker v-model="form.bundleEndDate">
                <div class="flex flex-col space-y-1">
                  <span class="text-base">
                    Bundle End Date
                  </span>
                  <span class="text-sm text-gray-600">
                    (bundle discount Campaign ending time)
                  </span>
                </div>
              </DatePicker>
            </div>

            <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-lg font-bold text-gray-700">
                Enable Buy 2 Get Discount?
              </label>
              <span class="text-sm text-gray-600">
                (Enable buy2 get discount)
              </span>
              <MultiSelect
                v-model="form.enableDiscount01"
                :options="booleanStrictOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
            </div>

            <div class="sm:col-span-3">
              <FormInput v-model="form.discountPercent01">
                <div class="flex flex-col space-y-1">
                  <span class="text-base">
                    Buy 2 Discount %
                  </span>
                  <span class="text-sm text-gray-600">
                    (set buy2 get how many % discount, *default 10%)
                  </span>
                </div>
              </FormInput>
            </div>

            <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-lg font-bold text-gray-700">
                Enable Buy 3 Get Discount?
              </label>
              <span class="text-sm text-gray-600">
                (Enable buy3 get discount)
              </span>
              <MultiSelect
                v-model="form.enableDiscount02"
                :options="booleanStrictOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
            </div>

            <div class="sm:col-span-3">
              <FormInput v-model="form.discountPercent02">
                <div class="flex flex-col space-y-1">
                  <span class="text-base">
                    Buy 3 Discount %
                  </span>
                  <span class="text-sm text-gray-600">
                    (set buy3 get how many % discount, *default 10%)
                  </span>
                </div>
              </FormInput>
            </div>

            <div class="sm:col-span-3">
              <label for="text" class="flex justify-start text-lg font-bold text-gray-700">
                Enable Buy 4 Get Discount?
              </label>
              <span class="text-sm text-gray-600">
                (Enable buy4 get discount)
              </span>
              <MultiSelect
                v-model="form.enableDiscount03"
                :options="booleanStrictOptions"
                trackBy="id"
                valueProp="id"
                label="value"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
            </div>

            <div class="sm:col-span-3">
              <FormInput v-model="form.discountPercent03">
                <div class="flex flex-col space-y-1">
                  <span class="text-base">
                    Buy 4 Discount %
                  </span>
                  <span class="text-sm text-gray-600">
                    (set buy4 get how many % discount, *default 10%)
                  </span>
                </div>
              </FormInput>
            </div>


            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center ">
                  <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded-md">Campaign Bindings</span>
                </div>
              </div>
            </div>

            <div class="sm:col-span-6 grid grid-cols-1 gap-3 sm:grid-cols-6 pb-5 mb-3 bg-gray-200 rounded p-4">
              <div class="sm:col-span-4">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Campaigns
                </label>
                <MultiSelect
                  v-model="selectedCampaigns"
                  :options="campaignOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                  mode="tags"
                >
                </MultiSelect>
              </div>

              <div class="sm:col-span-2 flex items-end">
                <Button
                  type="button"
                  @click="bindCampaigns()"
                  class="bg-green-500 hover:bg-green-600 text-white flex space-x-1"
                  :class="[!selectedCampaigns.length ? 'opacity-50 cursor-not-allowed' : '']"
                  :disabled="!selectedCampaigns.length"
                >
                  <PlusCircleIcon class="w-4 h-4"></PlusCircleIcon>
                  <span>
                    Add Campaign
                  </span>
                </Button>
              </div>
            </div>

            <div class="sm:col-span-6 flex flex-col mt-3">
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
                          Campaign
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                          Operator
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                          Active Period
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                          Bound At
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                          Action
                        </th>
                      </tr>
                    </thead>
                    <tbody class="bg-white">
                      <tr v-for="(campaign, campaignIndex) in campaignBindings" :key="campaign.id" :class="campaignIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                          {{ campaignIndex + 1 }}
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left">
                          <div class="flex flex-col space-y-1">
                            <div class="flex flex-col">
                              <span class="font-semibold">
                                {{ campaign.name }}
                              </span>
                              <span class="text-xs text-gray-500" v-if="campaign.slug">
                                {{ campaign.slug }}
                              </span>
                            </div>
                            <span class="text-xs text-gray-600">
                              Promo: {{ campaign.promo_type || 'N/A' }} • Value: {{ formatCampaignValue(campaign.value) }} • Qty: {{ campaign.bundle_qty ?? 'N/A' }}
                            </span>
                            <span class="text-xs text-gray-600">
                              Labels X: {{ formatCampaignLabels(campaign.labels_x) }}
                            </span>
                            <span class="text-xs text-gray-600">
                              Labels Y: {{ formatCampaignLabels(campaign.labels_y) }}
                            </span>
                          </div>
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                          {{ campaign.operator?.name || 'N/A' }}
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                          <span v-if="campaign.start_at || campaign.end_at">
                            {{ campaign.start_at || 'N/A' }} - {{ campaign.end_at || 'N/A' }}
                          </span>
                          <span v-else>
                            N/A
                          </span>
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                          {{ campaign.pivot?.created_at || 'N/A' }}
                        </td>
                        <td class="whitespace-nowrap py-4 text-sm text-center">
                          <Button
                            class="bg-red-400 hover:bg-red-500 text-white"
                            @click="unbindCampaign(campaign)"
                          >
                            <BackspaceIcon class="w-4 h-4"></BackspaceIcon>
                          </Button>
                        </td>
                      </tr>
                      <tr v-if="!campaignBindings?.length">
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

            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-center ">
                  <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded-md">Old Campaign Item Bindings</span>
                </div>
              </div>
            </div>

            <div class="sm:col-span-6 grid grid-cols-1 gap-3 sm:grid-cols-6 pb-5 mb-3 bg-gray-200 rounded p-4">
              <div class="sm:col-span-3">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Labels
                </label>
                <MultiSelect
                  v-model="form.tags"
                  :options="productTagOptions"
                  trackBy="id"
                  valueProp="id"
                  label="name"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                  mode="tags"
                >
                </MultiSelect>
              </div>

              <div class="sm:col-span-1">
                <FormInput v-model="form.qty">
                  <div class="flex flex-col space-y-1">
                    <span class="text-base">
                      Bundle Qty
                    </span>
                  </div>
                </FormInput>
              </div>

              <div class="sm:col-span-1">
                <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                  Promo Type
                </label>
                <MultiSelect
                  v-model="form.promo_type"
                  :options="promoTypeOptions"
                  trackBy="id"
                  valueProp="id"
                  label="value"
                  placeholder="Select"
                  open-direction="bottom"
                  class="mt-1"
                >
                </MultiSelect>
              </div>

              <div class="sm:col-span-1">
                <FormInput v-model="form.value">
                  <div class="flex flex-col space-y-1">
                    <span class="text-base">
                      Value
                    </span>
                  </div>
                </FormInput>
              </div>

              <div class="sm:col-span-6 lg:col-span-1 flex items-end">
                <Button
                  type="button"
                  @click="addCampaignItem()"
                  class="bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-0"
                  :class="[!canAddCampaignItem ? 'opacity-50 cursor-not-allowed' : '']"
                  :disabled="!canAddCampaignItem"
                >
                  <PlusCircleIcon class="w-4 h-4"></PlusCircleIcon>
                  <span>
                    Add Item
                  </span>
                </Button>
              </div>
            </div>

            <div class="sm:col-span-6 flex flex-col mt-3">
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
                          Labels
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                          Bundle Qty
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                          Promo Type
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                          Value
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                          Action
                        </th>
                      </tr>
                    </thead>
                    <tbody class="bg-white">
                      <tr v-for="(campaignItem, campaignItemIndex) in campaignItems" :key="campaignItem.id ?? campaignItemIndex" :class="campaignItemIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                          {{ campaignItemIndex + 1 }}
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left">
                          <div class="flex flex-wrap gap-1">
                            <span
                              v-for="(tagBinding, tagBindingIndex) in campaignItem.tagBindings"
                              :key="tagBinding.id ?? tagBindingIndex"
                              class="inline-flex rounded px-1 py-0.5 text-xs border w-fit bg-blue-100 text-blue-800 border-blue-300"
                            >
                              {{ formatTagBindingName(tagBinding) }}
                            </span>
                          </div>
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                          {{ campaignItem.qty }}
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                          {{ campaignItem.promo_type || 'N/A' }}
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                          {{ formatCampaignValue(campaignItem.value) }}
                        </td>
                        <td class="whitespace-nowrap py-4 text-sm text-center">
                          <Button
                            class="bg-red-400 hover:bg-red-500 text-white"
                            @click="deleteCampaignItem(campaignItem)"
                          >
                            <BackspaceIcon class="w-4 h-4"></BackspaceIcon>
                          </Button>
                        </td>
                      </tr>
                      <tr v-if="!campaignItems?.length">
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

            <div class="sm:col-span-6 py-4">
              <span class="flex space-x-1">
                <Button
                  type="button"
                  class="bg-blue-400 hover:bg-blue-500 text-white flex space-x-1"
                  @click.prevent="isShowVendSection = !isShowVendSection"
                >
                  <div class="flex space-x-2 items-center" v-if="!isShowVendSection">
                    <ArrowDownCircleIcon class="w-4 h-4"></ArrowDownCircleIcon>
                    <span>
                      Show Machine Binding Section
                    </span>
                  </div>
                  <div class="flex space-x-2 items-center" v-if="isShowVendSection">
                    <ArrowUpCircleIcon class="w-4 h-4"></ArrowUpCircleIcon>
                    <span>
                      Hide Machine Binding Section
                    </span>
                  </div>
                </Button>

                <Button
                  @click.prevent="pushApkSettings()"
                  class="bg-yellow-500 hover:bg-yellow-600 text-black flex space-x-1"
                >
                  <ArrowPathIcon class="w-4 h-4"></ArrowPathIcon>
                  <span>
                    Push Settings to Binded Machines
                  </span>
                </Button>
              </span>
            </div>

            <hr class="sm:col-span-6" v-if="!isShowVendSection">

            <div class="sm:col-span-6 grid grid-cols-1 gap-3 sm:grid-cols-6 pb-5 mb-3" v-if="isShowVendSection">
            <div class="sm:col-span-6 pt-2 pb-1 md:pt-5 md:pb-3">
              <div class="relative">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                  <div class="w-full border-t border-gray-300"></div>
                </div>
                <div class="relative flex justify-start">
                  <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded"> Machine Binding </span>
                </div>
              </div>
            </div>

            <div class="sm:col-span-5">
              <label for="text" class="flex justify-start text-sm font-medium text-gray-700">
                Vending Machine
              </label>
              <MultiSelect
                v-model="form.vend_id"
                :options="unbindedVendOptions"
                trackBy="id"
                valueProp="id"
                label="cust_full_name"
                placeholder="Select"
                open-direction="bottom"
                class="mt-1"
              >
              </MultiSelect>
              <div class="text-sm text-red-600" v-if="form.errors.vend_id">
                {{ form.errors.vend_id }}
              </div>
            </div>

            <div class="sm:col-span-1">
              <Button
              type="button"
              @click="bindVendItem()"
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

            <div class="sm:col-span-6 flex flex-col mt-3">
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
                          Machine ID
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                          Machine Name
                        </th>
                        <th scope="col" class="px-3 py-3.5 text-center text-sm font-semibold text-gray-900">
                          Action
                        </th>
                      </tr>
                    </thead>
                    <tbody class="bg-white">
                      <tr v-for="(vend, vendIndex) in vends" :key="vend.id" :class="vendIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                          {{ vendIndex + 1 }}
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                          {{ vend.code }}
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-left">
                          <span v-if="vend && vend.customer">
                            <span v-if="vend.customer.person_id">
                              {{ vend.customer.virtual_customer_code }} ({{ vend.customer.virtual_customer_prefix }}) <br>
                            </span>
                            <span v-else>
                              {{ vend.customer.code }} <br>
                            </span>
                            {{ vend.customer.name }}
                          </span>
                          <span v-else>
                            {{ vend.name }}
                          </span>
                        </td>
                        <td class="whitespace-nowrap py-4 text-sm text-center">
                          <Button
                            class="bg-red-400 hover:bg-red-500 text-white"
                            @click="unbindVendItem(vend)"
                          >
                            <BackspaceIcon class="w-4 h-4"></BackspaceIcon>
                          </Button>
                        </td>
                      </tr>
                      <tr v-if="!apkSetting.vends?.length">
                        <td colspan="5" class="whitespace-nowrap py-4 text-sm font-medium text-gray-600 text-center">
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

            <div class="sm:col-span-6 py-4">
              <span class="flex space-x-1 justify-end">
                <Button
                  type="submit"
                  class="bg-green-500 hover:bg-green-600 text-white flex space-x-1"
                >
                  <CheckCircleIcon class="w-4 h-4"></CheckCircleIcon>
                  <span>
                    Save Parameter Settings
                  </span>
                </Button>
              </span>
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
import AttachmentList from '@/Components/AttachmentList.vue';
import BreezeAuthenticatedLayout from '@/Layouts/Authenticated.vue';
import Button from '@/Components/Button.vue';
import DatePicker from '@/Components/DatePicker.vue';
import DropzoneFileInput from '@/Components/DropzoneFileInput.vue';
import FormInput from '@/Components/FormInput.vue';
import FormTextarea from '@/Components/FormTextarea.vue';
import MultiSelect from '@/Components/MultiSelect.vue';
import { ArrowDownCircleIcon, ArrowPathIcon, ArrowUpCircleIcon, BackspaceIcon, CheckCircleIcon, PlusCircleIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted, computed } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";
import axios from 'axios';

const props = defineProps({
    apkSetting: Object,
    campaignOptions: Object,
    operatorOptions: Object,
    productTagOptions: Object,
    unbindedVendOptions: Object,
  })

const booleanStrictOptions = ref([
  {id: 'true', value: 'Yes'},
  {id: 'false', value: 'No'},
])

const pricingSourceOptions = ref([
  {id: 'server', value: 'Server'},
  {id: 'machine', value: 'Machine'},
])

const promoBannerKindOptions = ref([
  {id: 'video', value: 'Video'},
  {id: 'picture', value: 'Picture'},
])

const promoTypeOptions = ref([
  {id: '1', value: 'Amount'},
  {id: '2', value: 'Percent'},
])

const isShowCampaignSection = ref(false)
const isShowVendSection = ref(false)
const operatorOptions = ref([])
const campaignOptions = ref([])
const productTagOptions = ref([])
const campaignBindings = ref([])
const campaignItems = ref([])
const selectedCampaigns = ref([])
const toast = useToast()
const form = ref(
  useForm(getDefaultForm())
)
const canAddCampaignItem = computed(() => {
  const currentForm = form.value || {}
  const tags = Array.isArray(currentForm.tags) ? currentForm.tags : []
  const qty = currentForm.qty
  const promoType = currentForm.promo_type
  const value = currentForm.value

  const hasTags = tags.some(tag => (tag && (tag.id ?? tag)))
  const hasQty = qty !== '' && qty !== null && qty !== undefined
  const hasPromoType = Boolean(promoType && (promoType.id ?? promoType))
  const hasValue = value !== '' && value !== null && value !== undefined

  return hasTags && hasQty && hasPromoType && hasValue
})
const apkSetting = ref([])
const unbindedVendOptions = ref([])
const vends = ref([])

onMounted(() => {
  apkSetting.value = props.apkSetting.data
  operatorOptions.value = props.operatorOptions.data
  campaignOptions.value = props.campaignOptions?.data ?? []
  productTagOptions.value = props.productTagOptions?.data?.map(tag => ({
    id: tag.id,
    name: tag.name,
  })) ?? []
  campaignBindings.value = props.apkSetting.data.campaigns ?? []
  campaignItems.value = mapCampaignItems(props.apkSetting.data.campaignItems ?? [])
  unbindedVendOptions.value = props.unbindedVendOptions.data
  vends.value = props.apkSetting.data.vends

  form.value = props.apkSetting?.data.settings_parameter_json ? useForm({
    ...props.apkSetting.data,
    ...JSON.parse(JSON.stringify(props.apkSetting.data.settings_parameter_json)),
    enablePromoHeaderText: booleanStrictOptions.value.find(
      option => option.id == props.apkSetting.data.settings_parameter_json.enablePromoHeaderText.toString()
    ),
    promoBannerKind: promoBannerKindOptions.value.find(
      option => option.id == props.apkSetting.data.settings_parameter_json.promoBannerKind.toString()
    ),
    enableP2Price: booleanStrictOptions.value.find(
      option => option.id == props.apkSetting.data.settings_parameter_json.enableP2Price.toString()
    ),
    disableP1P2CrossGrp: booleanStrictOptions.value.find(
      option => option.id == props.apkSetting.data.settings_parameter_json.disableP1P2CrossGrp.toString()
    ),
    enableBuy1Free1: booleanStrictOptions.value.find(
      option => option.id == props.apkSetting.data.settings_parameter_json.enableBuy1Free1.toString()
    ),
    enableBuy2Free1: booleanStrictOptions.value.find(
      option => option.id == props.apkSetting.data.settings_parameter_json.enableBuy2Free1.toString()
    ),
    enableBundleDiscount: booleanStrictOptions.value.find(
      option => option.id == props.apkSetting.data.settings_parameter_json.enableBundleDiscount.toString()
    ),
    enableDiscount01: booleanStrictOptions.value.find(
      option => option.id == props.apkSetting.data.settings_parameter_json.enableDiscount01.toString()
    ),
    enableDiscount02: booleanStrictOptions.value.find(
      option => option.id == props.apkSetting.data.settings_parameter_json.enableDiscount02.toString()
    ),
    enableDiscount03: booleanStrictOptions.value.find(
      option => option.id == props.apkSetting.data.settings_parameter_json.enableDiscount03.toString()
    ),
    enableLabelPromo: booleanStrictOptions.value.find(
      option => option.id == props.apkSetting.data.settings_parameter_json.enableLabelPromo.toString()
    ),
    bannerKind: promoBannerKindOptions.value.find(
      option => option.id == props.apkSetting.data.settings_parameter_json.bannerKind.toString()
    ),
    selectedPricingSource: pricingSourceOptions.value.find(
      option => option.id == props.apkSetting.data.settings_parameter_json.selectedPricingSource.toString()
    ),
    enableDebugMode: booleanStrictOptions.value.find(
      option => option.id == props.apkSetting.data.settings_parameter_json.enableDebugMode.toString()
    ),

  }) : useForm(getDefaultForm())

  if (!Array.isArray(form.value.tags)) {
    form.value.tags = []
  }
  if (!form.value.qty) {
    form.value.qty = ''
  }
  if (!form.value.promo_type) {
    form.value.promo_type = ''
  }
  if (form.value.value === undefined) {
    form.value.value = ''
  }
})

function getDefaultForm() {
  return {
    name: '',
    remarks: '',

    enablePromoHeaderText: '',
    promoHeaderText: '',
    promoBannerKind: '',
    headerTextStartDate: '',
    headerTextEndDate: '',

    promoRunningText: '',

    enableP2Price: '',
    disableP1P2CrossGrp: '',

    enableBuy1Free1: '',
    buy1free1X: '',
    buy1free1Y: '',
    buy1free1StartDate: '',
    buy1free1EndDate: '',

    enableBuy2Free1: '',
    buy2free1X: '',
    buy2free1Y: '',
    buy2free1StartDate: '',
    buy2free1EndDate: '',

    enableBundleDiscount: '',
    bundleStartDate: '',
    bundleEndDate: '',
    enableDiscount01: '',
    discountPercent01: '',
    enableDiscount02: '',
    discountPercent02: '',
    enableDiscount03: '',
    discountPercent03: '',

    tags: [],
    qty: '',
    promo_type: '',
    value: '',

    enableLabelPromo: '',
    labelPromoStartDate: '',
    labelPromoEndDate: '',

    bannerKind: '',
    supportContactNum: '',
    poweredBy: '',

    selectedPricingSource: '',

    enableDebugMode: '',

    vend_id: '',

    dcvendFreePlanPromoValue: '',
    dcvendGoldPlanPromoValue: '',
    dcvendPlatinumPlanPromoValue: '',
  }
}

function submit() {
  form.value.clearErrors()

  axios({
          method: 'POST',
          url: '/apk-settings/' + apkSetting.value.id + '/update',
          data: {
            ...form.value,
            enablePromoHeaderText: form.value.enablePromoHeaderText?.id,
            promoBannerKind: form.value.promoBannerKind?.id,
            enableP2Price: form.value.enableP2Price?.id,
            disableP1P2CrossGrp: form.value.disableP1P2CrossGrp?.id,
            enableBuy1Free1: form.value.enableBuy1Free1?.id,
            enableBuy2Free1: form.value.enableBuy2Free1?.id,
            enableBundleDiscount: form.value.enableBundleDiscount?.id,
            enableDiscount01: form.value.enableDiscount01?.id,
            enableDiscount02: form.value.enableDiscount02?.id,
            enableDiscount03: form.value.enableDiscount03?.id,
            enableLabelPromo: form.value.enableLabelPromo?.id,
            bannerKind: form.value.bannerKind?.id,
            selectedPricingSource: form.value.selectedPricingSource?.id,
            enableDebugMode: form.value.enableDebugMode?.id,
            headerTextStartDate: form.value.headerTextStartDate != 'Invalid date' ? form.value.headerTextStartDate : null,
            headerTextEndDate: form.value.headerTextEndDate != 'Invalid date' ? form.value.headerTextEndDate : null,
            buy1free1StartDate: form.value.buy1free1StartDate != 'Invalid date' ? form.value.buy1free1StartDate : null,
            buy1free1EndDate: form.value.buy1free1EndDate != 'Invalid date' ? form.value.buy1free1EndDate : null,
            buy2free1StartDate: form.value.buy2free1StartDate != 'Invalid date' ? form.value.buy2free1StartDate : null,
            buy2free1EndDate: form.value.buy2free1EndDate != 'Invalid date' ? form.value.buy2free1EndDate : null,
            bundleStartDate: form.value.bundleStartDate != 'Invalid date' ? form.value.bundleStartDate : null,
            bundleEndDate: form.value.bundleEndDate != 'Invalid date' ? form.value.bundleEndDate : null,
            labelPromoStartDate: form.value.labelPromoStartDate != 'Invalid date' ? form.value.labelPromoStartDate : null,
            labelPromoEndDate: form.value.labelPromoEndDate != 'Invalid date' ? form.value.labelPromoEndDate : null,
            vends: vends.value.map(vend => vend.id),
          }
      }).then(response => {
        toast.success("Successfully Saved", {
          timeout: 3000
        });
      }).catch(error => {
      }).finally(() => {
        // location.reload()
      })
}

function addCampaignItem() {
  if (!apkSetting.value?.id) {
    return;
  }

  form.value.clearErrors();

  const tagIds = Array.from(new Set(
    (form.value.tags || [])
      .map(tag => tag?.id ?? tag)
      .filter(Boolean)
  ));

  const hasQty = form.value.qty !== '' && form.value.qty !== null && form.value.qty !== undefined;
  const hasPromoType = Boolean(form.value.promo_type && (form.value.promo_type.id ?? form.value.promo_type));
  const hasValue = form.value.value !== '' && form.value.value !== null && form.value.value !== undefined;

  if (!tagIds.length || !hasQty || !hasPromoType || !hasValue) {
    return;
  }

  router.post('/apk-settings/' + apkSetting.value.id + '/create-campaign-item', {
    qty: form.value.qty,
    promo_type: form.value.promo_type?.id ?? form.value.promo_type,
    value: form.value.value,
    tags: tagIds,
  },
  {
    onSuccess: () => {
      toast.success("Campaign item added successfully", {
        timeout: 3000
      });
      form.value.tags = [];
      form.value.qty = '';
      form.value.promo_type = '';
      form.value.value = '';
      router.reload({
        only: ['apkSetting'],
        replace: true,
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
          refreshApkSetting(page);
        }
      })
    },
    onError: () => {
      toast.error("Failed, Please Try Again", {
        timeout: 3000
      });
    },
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

function deleteCampaignItem(campaignItem) {
  if (!campaignItem?.id) {
    return;
  }

  const approval = confirm('Are you sure you want to remove this campaign item?');
  if (!approval) {
    return;
  }

  router.delete('/apk-settings/campaign-items/' + campaignItem.id + '/delete-campaign-item',
  {
    onSuccess: () => {
      toast.success("Campaign item removed successfully", {
        timeout: 3000
      });
      router.reload({
        only: ['apkSetting'],
        replace: true,
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
          refreshApkSetting(page);
        }
      })
    },
    onError: () => {
      toast.error("Failed, Please Try Again", {
        timeout: 3000
      });
    },
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

function bindCampaigns() {
  const campaignIds = Array.from(new Set(
    (selectedCampaigns.value || [])
      .map((campaign) => campaign?.id ?? campaign)
      .filter(Boolean)
  ));

  if (!campaignIds.length) {
    return;
  }

  router.post('/apk-settings/' + apkSetting.value.id + '/campaigns/bind', {
    campaign_ids: campaignIds,
  },
  {
    onSuccess: () => {
      toast.success("Campaign(s) bound successfully", {
        timeout: 3000
      });
      selectedCampaigns.value = [];
      router.reload({
        only: ['apkSetting'],
        replace: true,
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
          refreshApkSetting(page);
        }
      })
    },
    onError: () => {
      toast.error("Failed, Please Try Again", {
        timeout: 3000
      });
    },
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

function mapCampaignItems(items) {
  if (!Array.isArray(items)) {
    return [];
  }

  const tagLookup = new Map(
    (productTagOptions.value || []).map(tag => [tag.id, tag.name])
  );

  return items.map(item => ({
    ...item,
    tagBindings: (item.tagBindings || []).map(binding => ({
      ...binding,
      name: binding.name
        ?? binding.tag?.name
        ?? tagLookup.get(binding.tag?.id ?? binding.tag_id ?? binding.id)
        ?? binding.tag?.slug
        ?? (binding.tag?.id || binding.tag_id || binding.id ? `Tag ${binding.tag?.id ?? binding.tag_id ?? binding.id}` : 'Tag'),
    })),
  }));
}

function formatTagBindingName(tagBinding) {
  if (!tagBinding) {
    return 'Tag';
  }

  if (tagBinding.name) {
    return tagBinding.name;
  }

  if (tagBinding.tag?.name) {
    return tagBinding.tag.name;
  }

  if (tagBinding.tag?.slug) {
    return tagBinding.tag.slug;
  }

  if (tagBinding.tag_id !== undefined && tagBinding.tag_id !== null) {
    return `Tag ${tagBinding.tag_id}`;
  }

  if (tagBinding.tag?.id !== undefined && tagBinding.tag?.id !== null) {
    return `Tag ${tagBinding.tag.id}`;
  }

  if (tagBinding.id !== undefined && tagBinding.id !== null) {
    return `Tag ${tagBinding.id}`;
  }

  return 'Tag';
}

function formatCampaignLabels(labels) {
  if (!Array.isArray(labels) || !labels.length) {
    return 'None';
  }

  const names = labels
    .map((label) => {
      if (!label) {
        return null;
      }

      if (label.name) {
        return label.name;
      }

      if (label.slug) {
        return label.slug;
      }

      if (label.id !== undefined && label.id !== null) {
        return `Tag ${label.id}`;
      }

      return null;
    })
    .filter(Boolean);

  return names.length ? names.join(', ') : 'None';
}

function formatCampaignValue(value) {
  if (value === null || value === undefined || value === '') {
    return 'N/A';
  }

  const numericValue = Number(value);
  if (!Number.isFinite(numericValue)) {
    return value;
  }

  return numericValue % 1 === 0 ? numericValue.toString() : numericValue.toFixed(2);
}

function refreshApkSetting(page) {
  const data = page?.props?.apkSetting?.data;
  if (!data) {
    return;
  }

  apkSetting.value = data;
  campaignBindings.value = data.campaigns ?? [];
  campaignItems.value = mapCampaignItems(data.campaignItems ?? []);
  vends.value = data.vends ?? vends.value;
}

function bindVendItem() {
  if(vends.value.indexOf(form.value.vend_id) < 0) {
    vends.value.push(form.value.vend_id)
    vends.value.sort((a, b) => a.code - b.code)
    unbindedVendOptions.value.splice(unbindedVendOptions.value.indexOf(form.value.vend_id), 1)
    unbindedVendOptions.value.sort((a, b) => a.code - b.code)
    form.value.vend_id = ''
  }
}

function unbindCampaign(campaign) {
  if (!campaign || !campaign.id) {
    return;
  }

  const approval = confirm('Are you sure you want to unbind this campaign?');
  if (!approval) {
    return;
  }

  router.delete('/apk-settings/' + apkSetting.value.id + '/campaigns/' + campaign.id, {
    onSuccess: () => {
      toast.success("Campaign unbound successfully", {
        timeout: 3000
      });
      campaignBindings.value = campaignBindings.value.filter(boundCampaign => boundCampaign.id !== campaign.id);
      router.reload({
        only: ['apkSetting'],
        replace: true,
        preserveState: true,
        preserveScroll: true,
        onSuccess: (page) => {
          refreshApkSetting(page);
        }
      })
    },
    onError: () => {
      toast.error("Failed, Please Try Again", {
        timeout: 3000
      });
    },
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

function pushApkSettings() {
  router.post('/apk-settings/' + apkSetting.value.id + '/push',
  {
    onSuccess: () => {
      toast.success("Successfully Pushed", {
        timeout: 3000
      });
    },
    onError: (errors) => {
      toast.error("Failed, Please Try Again", {
        timeout: 3000
      });
    },
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

function unbindVendItem(vend) {
  vends.value.splice(vends.value.indexOf(vend), 1)
  unbindedVendOptions.value.push(vend)
  unbindedVendOptions.value.sort((a, b) => a.code - b.code)
}

</script>
