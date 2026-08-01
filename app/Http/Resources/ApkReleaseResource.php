<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Admin (APK OTA Updates page) shape for one release row.
 *
 * NOTE: this is the ADMIN shape — snake_case and richer. The device-facing
 * UpdateManifest JSON is built separately in OtaController@manifest with the exact
 * camelCase keys the APK deserialises; do not merge the two.
 */
class ApkReleaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel' => $this->channel,
            'package_name' => $this->package_name,
            'version_code' => $this->version_code,
            'version_name' => $this->version_name,
            'file_url' => $this->file_url,
            'sha256' => $this->sha256,
            'size_bytes' => $this->size_bytes,
            'size_mb' => $this->size_bytes ? round($this->size_bytes / 1048576, 2) : 0,
            'mandatory' => (bool) $this->mandatory,
            'min_supported_version_code' => $this->min_supported_version_code,
            'rollout_permille' => $this->rollout_permille,
            'rollout_percent' => $this->rollout_permille ? round($this->rollout_permille / 10, 1) : 0,
            'status' => $this->status,
            // True for the one row currently served as this channel's live manifest.
            'is_live' => (bool) ($this->is_live ?? false),
            'release_notes' => $this->release_notes,
            'uploaded_by' => $this->whenLoaded('uploader', fn () => $this->uploader?->name),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
