<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class CardSettlementReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider,
            'original_filename' => $this->original_filename,
            'merchant_account' => $this->merchant_account,
            'cutover_date' => $this->cutover_date?->format('Y-m-d'),
            'status' => $this->status,
            'total_rows' => $this->total_rows,
            'purchase_rows' => $this->purchase_rows,
            'matched_count' => $this->matched_count,
            'unmatched_count' => $this->unmatched_count,
            'ambiguous_count' => $this->ambiguous_count,
            'duplicate_count' => $this->duplicate_count,
            'ignored_count' => $this->ignored_count,
            'synced_count' => $this->synced_count,
            'error_message' => $this->error_message,
            'uploadedBy' => UserResource::make($this->whenLoaded('uploader')),
            'created_at' => $this->created_at?->format('Y-m-d H:i'),
            'synced_at' => $this->synced_at?->format('Y-m-d H:i'),
        ];
    }
}
