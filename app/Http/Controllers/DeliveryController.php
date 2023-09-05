<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function getMenu(Request $request)
    {
        $merchantId = $request->merchantID;
        $partnerMerchantID = $request->partnerMerchantID;


    }
}
