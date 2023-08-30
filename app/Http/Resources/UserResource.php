<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->email,
            'username' => $this->username,
            'operator_id' => OperatorResource::make($this->whenLoaded('operator')),
            'operator' => OperatorResource::make($this->whenLoaded('operator')),
            'roles' => $this->whenLoaded('roles'),
            // 'role_id' => $this->whenLoaded('roles'),
            'role_id' =>  $this->when($this->relationLoaded('roles'), function() {
                return $this->roles()->first();
            }),
            'vends' => VendResource::collection($this->whenLoaded('vends')),
        ];
    }
}
