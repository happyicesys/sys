<?php

namespace App\Services;
use Carbon\Carbon;

class VendParameterService
{
    public function getCampaignParameter($parameters)
    {
        $settings = [
            'enablePromoHeaderText' => $parameters['enablePromoHeaderText'],
            'promoHeaderText' => $parameters['promoHeaderText'],
            'enableHeaderTextRunning' => $parameters['enableHeaderTextRunning'],
            'promoBannerKind' => $parameters['promoBannerKind'],
            'headerTextStartDate' => $parameters['headerTextStartDate'],
            'headerTextEndDate' => $parameters['headerTextEndDate'],

            'enablePromoRunningText' => $parameters['enablePromoRunningText'],
            'promoRunningText' => $parameters['promoRunningText'],
            'runningTextStartDate' => $parameters['runningTextStartDate'],
            'runningTextEndDate' => $parameters['runningTextEndDate'],

            'disableP1P2CrossGrp' => $parameters['disableP1P2CrossGrp'],

            'enableBuy1Free1' => $parameters['enableBuy1Free1'],
            'buy1free1X' => $parameters['buy1free1X'],
            'buy1free1Y' => $parameters['buy1free1Y'],
            'buy1free1StartDate' => $parameters['buy1free1StartDate'],
            'buy1free1EndDate' => $parameters['buy1free1EndDate'],

            'enableBuy2Free1' => $parameters['enableBuy2Free1'],
            'buy2free1X' => $parameters['buy2free1X'],
            'buy2free1Y' => $parameters['buy2free1Y'],
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
        ];

        return $settings;
    }
}