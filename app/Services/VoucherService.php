<?php

namespace App\Services;
use App\Jobs\ReleaseVoucherLock;
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

  public function lockVoucherCode($model, $vendID)
  {
    if($model instanceof VoucherItem) {
      $voucherItem = VoucherItem::find($model->id);
      $voucherItem->is_locked = true;
      $voucherItem->locked_at = Carbon::now();
      $voucherItem->locked_by_vend_id = $vendID;
      $voucherItem->save();
    }
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

  public function syncVoucherCount($model)
  {
    if($model instanceof Voucher) {
      $voucher = Voucher::find($model->id);
      $voucher->used_qty += 1;

      if($voucher->qty <= $voucher->used_qty + 1) {
        $voucher->status = Voucher::STATUS_REDEEMED;
      }
      $voucher->save();
    }

    if($model instanceof VoucherItem) {
      $voucherItem = VoucherItem::find($model->id);
      $voucherItem->status = Voucher::STATUS_REDEEMED;
      $voucherItem->is_redeemed = true;
      $voucherItem->redeemed_at = Carbon::now();
      $voucherItem->save();

      $voucher = $voucherItem->voucher;
      $voucher->used_qty += 1;
      $voucher->save();

    }

  }

  public function updateUsedVoucher($voucherCode)
  {
    $voucher = Voucher::where('code', $voucherCode)->first();

    if($voucher) {
      $this->syncVoucherCount($voucher);
      return;
    }

    $voucherItem = VoucherItem::where('code', $voucherCode)->first();

    if($voucherItem) {
      // ReleaseVoucherLock::dispatch($voucherItem);
      $this->syncVoucherCount($voucherItem);
      return;
    }
  }
}