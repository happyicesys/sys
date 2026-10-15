<?php

namespace App\Services\Citybox;

use App\Jobs\PushCityboxRestockPhotos;
use App\Models\Attachment;
use App\Models\OpsJob;
use App\Models\OpsJobItem;
use App\Models\Vend;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Before/after restock photos on a smart machine's stop (Brian, 2026-10-13): a CityBox chiller and,
 * the same day, a Smart Freezer. The driver takes the BEFORE photo, which unlocks Open Door
 * (checked in mark1 — CityboxOpsJobItemController / FreezerOpsJobItemController); after Stock In
 * the page asks for the AFTER photo. Both stay on the item as the job's record. Only a CHILLER's
 * go further: once the stop is stocked in and both exist, the latest before + after URL go to
 * CityBox in one deliver_pic_submit call with the visit's door-open msg_id
 * (PushCityboxRestockPhotos, 2026-10-15). A freezer has no such API; its photos are mark1's own
 * record.
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

        $this->pushIfReady($item->fresh('vend'));

        return $attachment;
    }

    public function delete(OpsJobItem $item, int $attachmentId): void
    {
        $attachment = $item->attachments()->whereIn('type', OpsJobItem::RESTOCK_PHOTO_TYPES)->findOrFail($attachmentId);
        $attachment->deleteFileIfUnshared();
        $attachment->delete();
    }

    /** The machine kinds whose stops carry restock photos. */
    public static function appliesTo(?Vend $vend): bool
    {
        return $vend !== null && (($vend->isSmartChiller() && $vend->citybox_equipment_id) || $vend->isSmartFreezer());
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

    /**
     * Send a chiller visit's photos once it is stocked in and both exist — called on each photo
     * upload and at Stock In (CityboxOpsJobItemObserver), whichever completes the set. A newer
     * photo after a send sends again (CityBox keeps one record per submit).
     */
    public function pushIfReady(OpsJobItem $item): void
    {
        // CityBox's API only: a freezer's photos stay in mark1.
        if (! $item->vend?->isSmartChiller() || ! $item->vend->citybox_equipment_id
            || ! config('citybox.openapi.enabled') || ! config('citybox.openapi.restock_photo_push')) {
            return;
        }
        if ((int) $item->status < (int) OpsJob::STATUS_DELIVERED) {
            return; // not stocked in yet: the "after" is not final
        }
        $photos = $this->payload($item);
        if ($photos['before'] === [] || $photos['after'] === []) {
            return;
        }
        $item->forceFill(['citybox_photo_push_status' => 'pending', 'citybox_photo_push_error' => null])->saveQuietly();
        // After the count submit (5 s), which may fill in the visit's msg_id.
        PushCityboxRestockPhotos::dispatch($item->id)->delay(now()->addSeconds(15))->onQueue('default');
    }

    /** @return array{before: ?string, after: ?string} the latest photo URL of each slot */
    public function latestUrls(OpsJobItem $item): array
    {
        $photos = $this->payload($item);

        return ['before' => end($photos['before'])['full_url'] ?? null, 'after' => end($photos['after'])['full_url'] ?? null];
    }
}
