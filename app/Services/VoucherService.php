<?php

namespace App\Services;
use App\Models\Voucher;
use Carbon\Carbon;

class VoucherService
{
  public function getVoucherRunningNumber()
  {
    $randomNumber = mt_rand(100000, 999999);

    while (Voucher::where('code', $randomNumber)->exists()) {
        $randomNumber = mt_rand(100000, 999999);
    }

    return $randomNumber;
  }
}