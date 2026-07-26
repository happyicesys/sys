<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitorPageViewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // 'beacon' = the browser reported the real on-screen time (works even for
        // the last page before the tab closed). 'inferred' = we only know the gap
        // to the next page view, so idle time is included. null = still open.
        $source = $this->duration_source;

        return [
            'id'                 => $this->id,
            'visitor_session_id' => $this->visitor_session_id,
            'user_id'            => $this->user_id,
            'user_name'          => $this->user?->name,
            'path'               => $this->path,
            'query_string'       => $this->query_string,
            'full_path'          => $this->query_string ? $this->path . '?' . $this->query_string : $this->path,
            'route_name'         => $this->route_name,
            'ip'                 => $this->ip,
            'viewed_at'          => optional($this->viewed_at)->toDateTimeString(),
            'left_at'            => optional($this->left_at)->toDateTimeString(),
            'duration_seconds'   => $this->duration_seconds,
            'duration_label'     => VisitorSessionResource::humanDuration($this->duration_seconds),
            'active_seconds'     => $this->active_seconds,
            'active_label'       => VisitorSessionResource::humanDuration($this->active_seconds),
            'duration_source'    => $source,
            'is_estimated'       => $source === 'inferred',
        ];
    }
}
