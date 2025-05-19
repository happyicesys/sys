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
                  <span class="px-3 bg-white text-lg font-medium text-gray-900 rounded-md">By Label(s) </span>
                </div>
              </div>
            </div>

            <div class="sm:col-span-2">
              <label for="text" class="flex justify-start text-lg font-bold text-gray-700">
                Enable Label Promo?
              </label>
              <MultiSelect
                v-model="form.enableLabelPromo"
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

            <div class="sm:col-span-2">
              <DatePicker v-model="form.labelPromoStartDate">
                <div class="flex flex-col space-y-1">
                  <span>
                    Start Date
                  </span>
                </div>
              </DatePicker>
            </div>

            <div class="sm:col-span-2">
              <DatePicker v-model="form.labelPromoEndDate">
                <div class="flex flex-col space-y-1">
                  <span>
                    End Date
                  </span>
                </div>
              </DatePicker>
            </div>

            <div class="sm:col-span-6 grid grid-cols-1 gap-3 sm:grid-cols-6 pb-5 mb-3 bg-gray-200 rounded p-4">
              <div class="sm:col-span-4">
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

              <div class="sm:col-span-2">
                <FormInput v-model="form.qty">
                  <div class="flex flex-col space-y-1">
                    <span>
                      Bundle Qty
                    </span>
                  </div>
                </FormInput>
              </div>

              <div class="sm:col-span-3">
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

              <div class="sm:col-span-3">
                <FormInput v-model="form.value">
                  <div class="flex flex-col space-y-1">
                    <span>
                      Value
                    </span>
                  </div>
                </FormInput>
              </div>

              <div class="sm:col-span-1">
                <Button
                type="button"
                @click="addCampaignItem()"
                class="bg-green-500 hover:bg-green-600 text-white flex space-x-1 sm:mt-6"
                :class="[!form.tags || !form.qty || !form.promo_type || !form.value ? 'opacity-50 cursor-not-allowed' : '']"
                :disabled="!form.tags || !form.qty || !form.promo_type || !form.value"
                >
                  <PlusCircleIcon class="w-4 h-4"></PlusCircleIcon>
                  <span>
                    Add Label
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
                          Label(s)
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
                      <tr v-for="(campaignItem, campaignItemIndex) in campaignItems" :key="campaignItem.id" :class="campaignItemIndex % 2 === 0 ? undefined : 'bg-gray-50'">
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-medium text-gray-900 sm:pl-6 text-center">
                          {{ campaignItemIndex + 1 }}
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                          <div class="flex flex-col space-y-1">
                            <span v-for="(tagBinding, tagBindingIndex) in campaignItem.tagBindings" class="flex space-x-1 justify-center">
                              <div
                                  class="inline-flex rounded px-0.5 py-0.5 text-xs border w-fit bg-blue-100 text-blue-800 border-blue-300"
                              >
                                  <div class="flex space-x-1">
                                      <span class="font-semibold grow-0">
                                        {{ tagBinding?.name }}
                                      </span>
                                  </div>
                              </div>
                            </span>
                          </div>
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                          {{ campaignItem.qty }}
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                          {{ campaignItem.promo_type }}
                        </td>
                        <td class="whitespace-nowrap py-4 pl-4 pr-3 text-sm font-semibold text-gray-900 sm:pl-6 text-center">
                          {{ campaignItem.value }}
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
import UploadFileInput from '@/Components/UploadFileInput.vue';
import { ArrowDownCircleIcon, ArrowPathIcon, ArrowUpCircleIcon, BackspaceIcon, CheckCircleIcon, PlusCircleIcon } from '@heroicons/vue/20/solid';
import { ref, onMounted } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { useToast } from "vue-toastification";
import axios from 'axios';

const props = defineProps({
    apkSetting: Object,
    operatorOptions: Object,
    productTagOptions: Object,
    type: String,
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

const isShowCampaignSection = ref(false)
const isShowVendSection = ref(false)
const operatorOptions = ref([])
const productTagOptions = ref([])
const permissions = usePage().props.auth.permissions
const campaignItems = ref([])
const toast = useToast()
const form = ref(
  useForm(getDefaultForm())
)
const apkSetting = ref([])
const promoTypeOptions = ref([
  {id: '1', value: 'Amount'},
  {id: '2', value: 'Percent'},
])
const unbindedVendOptions = ref([])
const vends = ref([])

onMounted(() => {
  apkSetting.value = props.apkSetting.data
  operatorOptions.value = props.operatorOptions.data
  productTagOptions.value = props.productTagOptions.data.map(tag => ({ id: tag.id, name: tag.name }));
  unbindedVendOptions.value = props.unbindedVendOptions.data
  vends.value = props.apkSetting.data.vends

  campaignItems.value = props.apkSetting.data.campaignItems.map(campaignItem => ({
    ...campaignItem,
    tagBindings: campaignItem.tagBindings?.map(tagBinding => productTagOptions.value.find(
      option => option.id == tagBinding.tag.id
    )),
  }))

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

    is_campaign_items_active: '',
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

    campaignItems: [],

    vend_id: '',

    dcvendFreePlanPromoValue: '',
    dcvendGoldPlanPromoValue: '',
    dcvendPlatinumPlanPromoValue: '',
  }
}

function submit() {
  form.value.clearErrors()
  let campaignItemsObj = JSON.parse(JSON.stringify(campaignItems.value))

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
            campaignItems: campaignItemsObj.map(campaignItem => ({
              qty: campaignItem.qty,
              value: campaignItem.value,
              promo_type: promoTypeOptions.value.find(
                option => option.value == campaignItem.promo_type
              )?.id,
              tags: campaignItem.tags?.map(tag => tag.id) || [],
            })),
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
  form.value.clearErrors();

  if (!Array.isArray(form.value.tags)) {
    form.value.tags = [];
  }

  if (Array.isArray(campaignItems.value)) {
    // campaignItems.value.push({
    //   ...form.value,
    //   promo_type: form.value.promo_type?.value,
    //   tagBindings: form.value.tags.map(tag => ({ id: tag.id, name: tag.name })),
    // });

    router.post('/apk-settings/' + apkSetting.value.id + '/create-campaign-item', {
      ...form.value,
      tags: form.value.tags.map(tag => tag.id),
      promo_type: form.value.promo_type?.id,
    },
    {
      onSuccess: () => {
        toast.success("Successfully Saved", {
          timeout: 3000
        });
        router.reload({
          only: ['apkSetting'],
          replace: true,
          preserveState: true,
          preserveScroll: true,
          onSuccess: page => {
            campaignItems.value = props.apkSetting.data.campaignItems.map(campaignItem => ({
              ...campaignItem,
              tagBindings: campaignItem.tagBindings?.map(tagBinding => productTagOptions.value.find(
                option => option.id == tagBinding.tag.id
              )),
            }))
          }
        })
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

    // Clear the form fields after adding the campaign item
    form.value.tags = [];
    form.value.qty = '';
    form.value.promo_type = '';
    form.value.value = '';
  } else {
    console.error('campaignItems is not an array:', campaignItems.value);
  }
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

function deleteCampaignItem(campaignItem) {
  const approval = confirm('Are you sure to delete this entry?');
  if (!approval) {
      return;
  }
  router.delete('/apk-settings/campaign-items/' + campaignItem.id + '/delete-campaign-item',
  {
    onSuccess: () => {
      toast.success("Successfully Deleted", {
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
  campaignItems.value.splice(campaignItems.value.indexOf(campaignItem), 1)
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