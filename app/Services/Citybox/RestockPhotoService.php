<?php

namespace App\Services\Citybox;

use App\Jobs\PushCityboxRestockPhotos;
use App\Models\Attachment;
use App\Models\OpsJob;
use App\Models\OpsJobItem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Before/after restock photos on a CityBox chiller stop (Brian, 2026-10-13). The driver takes the
 * BEFORE photo, which unlocks Open Door (checked here, in mark1 — CityboxOpsJobItemController);
 * after Stock In the page asks for the AFTER photo. Both stay on the item, shown in the job, and
 * once both exist their URLs go to CityBox (PushCityboxRestockPhotos) — inert until CityBox ships
 * the endpoint (config citybox.openapi.restock_photo_path).
 *
 * Stored as attachments in OpsJobItem::PHOTO_RESTOCK_* slots on the default disk (DO Spaces in
 * prod), public, because CityBox needs a URL it can fetch. A phone photo is 3–12 MB; every upload
 * is turned upright and scaled to MAX_EDGE as JPEG first, so a driver on mobile data and the
 * page that shows it never move the original. A file that cannot be decoded is kept as sent.
 */
class RestockPhotoService
{
    public const MAX_EDGE = 1600;

    public const SLOTS = ['before' => OpsJobItem::PHOTO_RESTOCK_BEFORE, 'after' => OpsJobItem::PHOTO_RESTOCK_AFTER];

    public function store(OpsJobItem $item, string $slot, UploadedFile $file): Attachment
    {
        $type = self::SLOTS[$slot];
        $dir = 'sys/ops-job-items/restock/'.$item->id;
        $name = $slot.'-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(4));

        try {
            $binary = (string) Image::read($file->getRealPath())->orient()->scaleDown(self::MAX_EDGE, self::MAX_EDGE)->encode(new JpegEncoder(quality: 80));
            $path = $dir.'/'.$name.'.jpg';
            Storage::put($path, $binary, ['visibility' => 'public']);
        } catch (\Throwable $e) {
            Log::notice('Restock photo kept as uploaded (could not be resized)', ['ops_job_item_id' => $item->id, 'error' => $e->getMessage()]);
            $path = $file->storePubliclyAs($dir, $name.'.'.($file->extension() ?: 'jpg'));
        }

        $attachment = $item->attachments()->create([
            'type' => $type,
            'full_url' => Storage::url($path),
            'local_url' => $path,
            'name' => mb_substr((string) $file->getClientOriginalName(), 0, 255),
            'desc' => 'restock:'.$slot,
        ]);

        $this->pushIfReady($item->fresh());

        return $attachment;
    }

    public function delete(OpsJobItem $item, int $attachmentId): void
    {
        $attachment = $item->attachments()->whereIn('type', OpsJobItem::RESTOCK_PHOTO_TYPES)->findOrFail($attachmentId);
        $attachment->deleteFileIfUnshared();
        $attachment->delete();
    }

    public static function hasBefore(OpsJobItem $item): bool
    {
        return $item->attachments()->where('type', OpsJobItem::PHOTO_RESTOCK_BEFORE)->exists();
    }

    /** Open Door refuses until the before photo exists — only before Stock In. */
    public static function blocksOpenDoor(OpsJobItem $item): bool
    {
        return (bool) config('citybox.require_before_photo', true)
            && (int) $item->status < (int) OpsJob::STATUS_DELIVERED
            && ! self::hasBefore($item);
    }

    /** @return array{before: list<array>, after: list<array>} */
    public static function payloadFrom(Collection $attachments): array
    {
        $out = ['before' => [], 'after' => []];
        foreach ($attachments as $a) {
            $slot = array_search((int) $a->type, self::SLOTS, true);
            if ($slot !== false) {
                $out[$slot][] = ['id' => $a->id, 'full_url' => $a->full_url, 'taken_at' => $a->created_at?->toIso8601String()];
            }
        }

        return $out;
    }

    public function payload(OpsJobItem $item): array
    {
        return self::payloadFrom($item->attachments()->whereIn('type', OpsJobItem::RESTOCK_PHOTO_TYPES)->get());
    }

    /** Both photos in and CityBox has the endpoint → send them (once per new photo set). */
    public function pushIfReady(OpsJobItem $item): void
    {
        if (! config('citybox.openapi.restock_photo_path')) {
            return;
        }
        $photos = $this->payload($item);
        if ($photos['before'] === [] || $photos['after'] === []) {
            return;
        }
        $item->forceFill(['citybox_photo_push_status' => 'pending', 'citybox_photo_push_error' => null])->saveQuietly();
        PushCityboxRestockPhotos::dispatch($item->id)->onQueue('default');
    }
}
