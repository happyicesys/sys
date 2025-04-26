<?php

namespace App\Services;
use App\Models\Voucher;
use App\Models\VoucherItem;
use Carbon\Carbon;

class VoucherService
{
  public function getVoucherRunningNumber()
  {
      do {
          $randomString = strtoupper(substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 6));
      } while (Voucher::where('code', $randomString)->exists());

      return $randomString;
  }


  public function syncVoucherItems(Voucher $voucher)
  {
    $voucherItems = [];

    if($voucher->is_batch_code) {
      for ($i = 0; $i < $voucher->qty; $i++) {
        $voucherItems[] = [
            'code' => $this->getVoucherRunningNumber(),
            'member_id' => null,
            'is_active' => true,
            'is_redeemed' => false,
            'redeemed_at' => null,
            'status' => Voucher::STATUS_ACTIVE,
            'voucher_id' => $voucher->id,
        ];
      }
    }

    return VoucherItem::insert($voucherItems);
  }
}