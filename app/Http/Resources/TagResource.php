<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TagResource extends JsonResource
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
            'classname' => $this->classname,
            'name' => $this->name,
            'slug' => $this->slug,
            'desc' => $this->desc,
            // Number of records currently bound to this tag (filled in by
            // TagController::index via withCount('tagBindings')). The Vue
            // index page uses this to disable the Delete button while the
            // tag is still in use somewhere. Falls back to null when the
            // count wasn't requested (e.g. when used via OptionsService).
            'bindings_count' => isset($this->tag_bindings_count)
                ? (int) $this->tag_bindings_count
                : null,
        ];
    }
}
