<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    // Get mart menu
    // {
    //     "merchantID": "1-CYNGRUNGSBCCC",
    //     "partnerMerchantID": "Partner-ABECU",
    //     "currency": {
    //         "code": "SGD",
    //         "symbol": "S$",
    //         "exponent": 2
    //     },
    //     "sections": [
    //         {
    //             "categories": [
    //                 {
    //                 "id": "category_id",
    //                 "name": "category_name",
    //                 "availableStatus": "AVAILABLE",
    //                 "subCategories": []
    //                 }
    //             ]
    //         }
    //     ]
    // }
    public function getMenu(Request $request)
    {
        $merchantId = $request->merchantID;
        $partnerMerchantID = $request->partnerMerchantID;


    }
}
