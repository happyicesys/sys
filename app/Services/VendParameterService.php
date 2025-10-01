<?php

namespace App\Services;
use Carbon\Carbon;

class VendParameterService
{
    public function getDefaultParameter()
    {
        // enableHeaderTextRunning
        // enablePromoRunningText
        // runningTextStartDate
        // runningTextEndDate
        $parameters = [
            'enablePromoHeaderText' => false,
            'promoHeaderText' => null,
            'promoBannerKind' => 'video',
            'headerTextStartDate' => null,
            'headerTextEndDate' => null,

            'promoRunningText' => null,

            'enableP2Price' => false,
            'disableP1P2CrossGrp' => false,

            'enableBuy1Free1' => false,
            'buy1free1X' => 0,
            'buy1free1Y' => 0,
            'buy1free1StartDate' => null,
            'buy1free1EndDate' => null,

            'enableBuy2Free1' => false,
            'buy2free1X' => 1,
            'buy2free1Y' => 0,
            'buy2free1StartDate' => null,
            'buy2free1EndDate' => null,

            'enableBundleDiscount' => false,
            'bundleStartDate' => null,
            'bundleEndDate' => null,
            'enableDiscount01' => true,
            'discountPercent01' => 1,
            'enableDiscount02' => false,
            'discountPercent02' => 1,
            'enableDiscount03' => false,
            'discountPercent03' => 1,

            'enableLabelPromo' => false,
            'labelPromoStartDate' => null,
            'labelPromoEndDate' => null,

            'bannerKind' => 'picture',
            'supportContactNum' => '87188597',
            'poweredBy' => 'Powered By Happy Ice',

            'selectedPricingSource' => 'machine',

            'enableDebugMode' => false,

            "dcvendFreePlanPromoValue" => 15,
            "dcvendGoldPlanPromoValue" => 30,
            "dcvendPlatinumPlanPromoValue" => 30,
        ];

        return $parameters;
    }

    public function getCampaignParameter($parameters)
    {
        $settings = [
            'enablePromoHeaderText' => $parameters['enablePromoHeaderText'],
            'promoHeaderText' => $parameters['promoHeaderText'],
            'promoBannerKind' => $parameters['promoBannerKind'],
            'headerTextStartDate' => $parameters['headerTextStartDate'],
            'headerTextEndDate' => $parameters['headerTextEndDate'],

            'promoRunningText' => $parameters['promoRunningText'],

            'enableP2Price' => $parameters['enableP2Price'],
            'disableP1P2CrossGrp' => $parameters['disableP1P2CrossGrp'],

            'enableBuy1Free1' => $parameters['enableBuy1Free1'],
            'buy1free1X' => $parameters['buy1free1X'] ?? -1,
            'buy1free1Y' => $parameters['buy1free1Y'] ?? -1,
            'buy1free1StartDate' => $parameters['buy1free1StartDate'],
            'buy1free1EndDate' => $parameters['buy1free1EndDate'],

            'enableBuy2Free1' => $parameters['enableBuy2Free1'],
            'buy2free1X' => $parameters['buy2free1X'] ?? -1,
            'buy2free1Y' => $parameters['buy2free1Y'] ?? -1,
            'buy2free1StartDate' => $parameters['buy2free1StartDate'],
            'buy2free1EndDate' => $parameters['buy2free1EndDate'],

            'enableBundleDiscount' => $parameters['enableBundleDiscount'],
            'bundleStartDate' => $parameters['bundleStartDate'],
            'bundleEndDate' => $parameters['bundleEndDate'],
            'enableDiscount01' => $parameters['enableDiscount01'],
            'discountPercent01' => $parameters['discountPercent01'],
            'enableDiscount02' => $parameters['enableDiscount02'],
            'discountPercent02' => $parameters['discountPercent02'],
            'enableDiscount03' => $parameters['enableDiscount03'],
            'discountPercent03' => $parameters['discountPercent03'],

            'enableLabelPromo' => $parameters['enableLabelPromo'],
            'labelPromoStartDate' => $parameters['labelPromoStartDate'],
            'labelPromoEndDate' => $parameters['labelPromoEndDate'],

            'bannerKind' => $parameters['bannerKind'],
            'supportContactNum' => $parameters['supportContactNum'],
            'poweredBy' => $parameters['poweredBy'],

            'selectedPricingSource' => $parameters['selectedPricingSource'],

            'enableDebugMode' => $parameters['enableDebugMode'],

            "dcvendFreePlanPromoValue" => $parameters['dcvendFreePlanPromoValue'],
            "dcvendGoldPlanPromoValue" => $parameters['dcvendGoldPlanPromoValue'],
            "dcvendPlatinumPlanPromoValue" => $parameters['dcvendPlatinumPlanPromoValue'],
        ];

        return $settings;
    }
}