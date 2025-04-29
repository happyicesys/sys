<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VoucherResource extends JsonResource
{
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
            'code_formatted' => strtoupper($this->code),
            'created_at' => $this->created_at,
            'customer_id' => $this->customer_id,
            'customer' => CustomerResource::make($this->whenLoaded('customer')),
            'date_from' => $this->date_from,
            'date_from_formatted' => $this->date_from ? $this->date_from->format('Y-m-d') : null,
            'date_to' => $this->date_to,
            'date_to_formatted' => $this->date_to ? $this->date_to->format('Y-m-d') : null,
            'desc' => $this->desc,
            'is_active' => $this->is_active,
            'is_batch_code' => $this->is_batch_code,
            'max_promo_value' => $this->max_promo_value,
            'max_redemption_count' => $this->max_redemption_count,
            'min_value' => $this->min_value,
            'name' => $this->name,
            'product_json' => $this->product_json,
            'product_json_mapped' => $this->product_json ? $this->mapProductJson($this->product_json) : null,
            'qty' => $this->qty,
            'response_json' => $this->response_json,
            'status' => $this->status,
            'type' => $this->type,
            'type_name' => $this->type ? Voucher::TYPE_MAPPINGS[$this->type] : null,
            'value' => $this->value,
            'used_qty' => $this->used_qty,
            'vend_id' => $this->vend_id,
            'vend' => VendResource::make($this->whenLoaded('vend')),
        ];
    }

    private function mapProductJson($productIDs)
    {
        $products = [];
        // dd($productIDs);
        foreach ($productIDs as $productID) {
            $product = Product::with('thumbnail')->find($productID);
            if ($product) {
                $products[] = [
                    'id' => $product->id,
                    'name' => $product->name,
                    'code' => $product->code,
                    'thumbnail_url' => $product->thumbnail->full_url ?? null,
                ];
            }
        }
        return $products;
    }

}
