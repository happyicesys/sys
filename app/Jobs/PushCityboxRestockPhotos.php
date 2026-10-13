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
 * Send a chiller stop's before/after restock photo URLs to CityBox, tied to the visit's door-open
 * session. Their endpoint is not delivered yet: RestockPhotoService only queues this once
 * citybox.openapi.restock_photo_path is set. Retries with backoff; the outcome is on the item
 * (citybox_photo_push_*), like the stock submit.
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
        $openLogId = CityboxDoorOpenLog::where('ops_job_item_id', $item->id)->where('result', CityboxDoorOpenLog::RESULT_OPENED)
            ->latest('requested_at')->value('open_log_id');

        try {
            foreach ($photos->payload($item) as $kind => $list) {
                foreach ($list as $photo) {
                    $gateway->submitRestockPhoto((string) $item->vend->citybox_equipment_id, $item->citybox_msg_id, $openLogId, $kind, $photo['full_url'], \Carbon\Carbon::parse($photo['taken_at'] ?? now()));
                }
            }
        } catch (\Throwable $e) {
            $item->forceFill(['citybox_photo_push_status' => 'failed', 'citybox_photo_push_error' => mb_substr($e->getMessage(), 0, 255)])->saveQuietly();
            throw $e;
        }

        $item->forceFill(['citybox_photo_push_status' => 'ok', 'citybox_photo_pushed_at' => now(), 'citybox_photo_push_error' => null])->saveQuietly();
    }
}
