<?php

namespace App\Http\Resources;

use App\Models\PrepaidVoucher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** One row of the next-visit voucher list. The number is masked; staff search by the full number instead. */
class PrepaidVoucherResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var PrepaidVoucher $v */
        $v = $this->resource;

        return [
            'id' => $v->id,
            'phone' => PrepaidVoucher::maskPhone($v->phone),
            'phone_purged' => $v->phone === null,
            'product' => $v->product ? trim($v->product->code.' '.$v->product->name) : null,
            'list_price_cents' => $v->list_price_cents,
            'price_paid_cents' => $v->price_paid_cents,
            'discount_pct' => $v->discount_pct,
            'pay_rail' => $v->pay_rail,
            'bought_vend' => $v->boughtVend?->codeLabel(),
            'bought_order_id' => $v->bought_order_id,
            'issued_at' => $v->issued_at?->format('Y-m-d H:i'),
            'expires_at' => $v->expires_at?->format('Y-m-d H:i'),
            'status' => $v->displayStatus(),
            'redeemed_at' => $v->redeemed_at?->format('Y-m-d H:i'),
            'redeemed_vend' => $v->redeemedVend?->codeLabel(),
            'redeemed_mode' => $v->redeemed_mode,
            'redeemed_product' => $v->redeemed_product_id && $v->relationLoaded('redeemedProduct') && $v->redeemedProduct
                ? trim($v->redeemedProduct->code.' '.$v->redeemedProduct->name) : null,
            'value_used_cents' => $v->value_used_cents,
            'forfeited_cents' => $v->forfeited_cents,
            'balance_paid_cents' => $v->balance_paid_cents,
            'void_reason' => $v->void_reason,
        ];
    }
}
