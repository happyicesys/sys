<?php

namespace App\Http\Resources;

use App\Models\Vend;
use App\Models\VendChannel;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherApiResource extends JsonResource
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
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type,
            'channels' => $this->getVendChannelsByProducts($this->product_json),
            'date_from' => $this->date_from->format('Y-m-d'),
            'date_to' => $this->date_to->format('Y-m-d'),
            'name' => $this->name,
            'desc' => $this->desc,
            'status' => Voucher::STATUS_MAPPINGS[$this->status],
            'min_value' => $this->min_value,
            'max_promo_value' => $this->max_promo_value,
            'qty' => 1,
            'value' => $this->value,
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
