<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class SimcardResource extends JsonResource
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
            'begin_date' => $this->begin_date,
            'code' => $this->code,
            'createdBy' => UserResource::make($this->whenLoaded('createdBy')),
            'created_by' => $this->created_by,
            'is_active' => $this->is_active,
            'msisdn' => $this->msisdn,
            'phone_number' => $this->phone_number,
            'telco' => TelcoResource::make($this->whenLoaded('telco')),
            'telco_id' => $this->telco_id,
            'termination_date' => $this->termination_date,
            'updatedBy' => UserResource::make($this->whenLoaded('updatedBy')),
            'updated_by' => $this->updated_by,
            // For the Index "Updated By" column — only meaningful once updated_by
            // is set (rows only ever created show '—' there).
            'updated_at' => optional($this->updated_at)->format('ymd h:i a'),
            // Site column — ONE entry per bound machine, in bound-machine order,
            // so the stack lines up with the Machine APK column even when a
            // machine sits at no Site (id null there, rendered '—').
            // ref_id is the displayed Site ID (customers.id +
            // Customer::RUNNING_NUMBER_INIT), the same value the Machine List
            // shows. Customer carries OperatorCustomerFilterScope, so a Site the
            // viewer may not see resolves to null and reads as '—', never a name.
            'sites' => $this->whenLoaded('vends', function () {
                return $this->vends->map(fn ($vend) => [
                    'vend_id' => $vend->id,
                    'id' => $vend->customer?->id,
                    'ref_id' => $vend->customer ? $vend->customer->id + \App\Models\Customer::RUNNING_NUMBER_INIT : null,
                    'name' => $vend->customer?->name,
                ])->values();
            }),
            // Machine APK + Signal Strength read plain vend attributes. The
            // customer relation is eager-loaded for 'sites' above only — strip it
            // here so every row does not ship the same Site twice.
            'vends' => $this->whenLoaded('vends', fn () => $this->vends->map->withoutRelations()),
            'vend_code' => $this->whenLoaded('vends', function () {
                return $this->vends->pluck('code')->implode(', ');
            }),

        ];
    }
}
