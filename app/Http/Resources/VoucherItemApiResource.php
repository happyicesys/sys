<?php

namespace App\Http\Resources;

use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherItemApiResource extends JsonResource
{
    protected $vendCode;
    protected $dcvendUserID;

    public function __construct($resource, $vendCode = null, $dcvendUserID = null)
    {
        parent::__construct($resource);
        $this->vendCode = $vendCode;
        $this->dcvendUserID = $dcvendUserID;
    }
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->voucher->id,
            'code' => $this->code,
            'type' => $this->voucher->type,
            'channels' => $this->getVendChannelsByProducts($this->voucher->product_json),
            'date_from' => $this->voucher->date_from->format('Y-m-d'),
            'date_to' => $this->voucher->date_to->format('Y-m-d'),
            'name' => $this->voucher->name,
            'desc' => $this->voucher->desc,
            'status' => Voucher::STATUS_MAPPINGS[$this->status],
            'min_value' => $this->voucher->min_value,
            'max_promo_value' => $this->voucher->max_promo_value,
            'qty' => 1,
            'value' => $this->voucher->value,
            'matrix' => []
        ];
    }

    private function getVendChannelsByProducts($productIDArr)
    {
        $vend = Vend::where('code', $this->vendCode)->first();

        if (!$vend) {
            return [];
        }

        return VendChannel::where('vend_id', $vend->id)
            ->where('is_active', true)
            ->whereIn('product_id', $productIDArr)
            ->pluck('code');
    }
}
