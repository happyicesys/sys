<?php

namespace App\Jobs;

use App\Contracts\Citybox\ChillerGateway;
use App\Models\CityboxDoorOpenLog;
use App\Models\OpsJobItem;
use App\Services\Citybox\RestockPhotoService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Send a chiller visit's before/after restock photos to CityBox: ONE deliver_pic_submit call with
 * the visit's door-open msg_id and the latest before + after URL (public on Spaces, so CityBox can
 * fetch them). Queued by RestockPhotoService::pushIfReady once the stop is stocked in and both
 * photos exist. Retries with backoff; the outcome is on the item (citybox_photo_push_*).
 */
class PushCityboxRestockPhotos implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300];

    public int $uniqueFor = 300;

    public function __construct(public int $opsJobItemId) {}

    public function uniqueId(): string
    {
        return (string) $this->opsJobItemId;
    }

    public function handle(ChillerGateway $gateway, RestockPhotoService $photos): void
    {
        $item = OpsJobItem::withoutGlobalScopes()->with('vend')->find($this->opsJobItemId);
        if (! $item || ! $item->vend?->citybox_equipment_id) {
            return;
        }
        $urls = $photos->latestUrls($item);
        if (! $urls['before'] || ! $urls['after']) {
            return; // a photo was removed since this was queued
        }
        // The visit's session; the count submit fills it from the chiller's latest open if the
        // driver opened from elsewhere.
        $msgId = $item->citybox_msg_id ?: CityboxDoorOpenLog::where('vend_id', $item->vend_id)
            ->where('result', CityboxDoorOpenLog::RESULT_OPENED)->whereNotNull('msg_id')
            ->latest('requested_at')->value('msg_id');
        if (! $msgId) {
            $this->markFailed($item, 'No door-open session (msg_id) for this chiller.');

            return;
        }

        try {
            $gateway->submitRestockPhotos((string) $item->vend->citybox_equipment_id, $msgId, $urls['before'], $urls['after']);
        } catch (\Throwable $e) {
            $this->markFailed($item, $e->getMessage());
            throw $e;
        }

        $item->forceFill(['citybox_photo_push_status' => 'ok', 'citybox_photo_pushed_at' => now(), 'citybox_photo_push_error' => null])->saveQuietly();
    }

    private function markFailed(OpsJobItem $item, string $error): void
    {
        $item->forceFill(['citybox_photo_push_status' => 'failed', 'citybox_photo_push_error' => mb_substr($error, 0, 255)])->saveQuietly();
    }
}
