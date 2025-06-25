<?php

namespace App\Http\Resources;

use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\Voucher;
use App\Models\VoucherItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;


class VoucherCheckingApiResource extends JsonResource
{
    protected $vendCode;
    protected $dcvendUserID;

    public function __construct($resource, $vendCode = null, $dcvendUserID = null)
    {
        parent::__construct($resource);
        $this->vendCode = $vendCode;
        $this->dcvendUserID = $dcvendUserID;
    }

    public function toArray($request): array
    {
        // Detect base model (Voucher or VoucherItem)
        $isVoucherItem = $this->resource instanceof VoucherItem;

        $voucher = $isVoucherItem ? $this->voucher : $this->resource;
        $statusKey = $this->status ?? $voucher->status;

        $channels = $this->getVendChannelsByProducts($voucher->product_json);

        // ✅ Shuffle if voucher has is_random_channel_sequence enabled
        if ($voucher->is_random_channel_sequence && count($channels) > 1) {
            shuffle($channels);
        }

        return [
            'id' => $voucher->id,
            'code' => $this->code ?? $voucher->code,
            'type' => $voucher->type,
            'channels' => $channels,
            'date_from' => optional($voucher->date_from)->format('Y-m-d'),
            'date_to' => optional($voucher->date_to)->format('Y-m-d'),
            'name' => $voucher->name,
            'desc' => $voucher->desc,
            'status' => Voucher::STATUS_MAPPINGS[$statusKey] ?? 'active',
            'min_value' => $voucher->min_value != null ? $voucher->min_value * 100 : null,
            'max_promo_value' => $voucher->max_promo_value != null ? $voucher->max_promo_value * 100 : null,
            'qty' => $this->qty ?? 1,
            'value' => $voucher->type == Voucher::TYPE_PERCENT ? $voucher->value : $voucher->value * 100,
            'matrix' => [],
        ];
    }

    private function getVendChannelsByProducts($productIDArr)
    {
        if (is_string($productIDArr)) {
            $productIDArr = json_decode($productIDArr, true);
        }


        if (!is_array($productIDArr) || count($productIDArr) === 0) {
            return []; // safely return empty result
        }

        $vend = Vend::where('code', $this->vendCode)->first();

        if (!$vend) {
            return [];
        }

        return VendChannel::where('vend_id', $vend->id)
            ->where('is_active', true)
            ->whereIn('product_id', $productIDArr)
            ->pluck('code')
            ->toArray();
    }

}

