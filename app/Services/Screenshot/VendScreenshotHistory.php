<?php

namespace App\Services\Screenshot;

use App\Models\Vend;
use App\Models\VendScreenshot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The only writer of `vend_screenshots`: keeps each machine's newest View Screen captures
 * (Brian, 2026-10-15: "save the last five pictures as history; more than five, delete").
 *
 * Files are private on the default disk (DO Spaces in prod) and reached only through
 * VendScreenshotController::historyImage, which re-checks permission on every fetch.
 */
class VendScreenshotHistory
{
    public const KEEP = 5;

    public function store(Vend $vend, string $bytes, string $mime, ?int $width, ?int $height, ?int $requestedBy): VendScreenshot
    {
        $now = Carbon::now();
        $ext = $mime === 'image/png' ? 'png' : 'jpg';
        $path = sprintf('vend-screenshots/%d/%s-%s.%s', $vend->id, $now->format('Ymd-His'), Str::lower(Str::random(8)), $ext);
        Storage::put($path, $bytes, ['visibility' => 'private', 'mimetype' => $mime]);

        $shot = VendScreenshot::create([
            'vend_id' => $vend->id,
            'requested_by' => $requestedBy,
            'path' => $path,
            'mime' => $mime,
            'bytes' => strlen($bytes),
            'width' => $width,
            'height' => $height,
            'captured_at' => $now,
        ]);

        $this->prune($vend);

        return $shot;
    }

    /** Newest first, at most KEEP. */
    public function recent(Vend $vend): Collection
    {
        return VendScreenshot::query()->with('requester:id,name')
            ->where('vend_id', $vend->id)->orderByDesc('id')->limit(self::KEEP)->get();
    }

    /** Everything older than the newest KEEP goes: the file first, then its row. */
    private function prune(Vend $vend): void
    {
        $old = VendScreenshot::query()->where('vend_id', $vend->id)->orderByDesc('id')->skip(self::KEEP)->take(PHP_INT_MAX)->get();
        foreach ($old as $shot) {
            try {
                Storage::delete($shot->path);
            } catch (\Throwable $e) {
                // A file we cannot delete now must not keep its row out of the count forever.
                Log::warning('View Screen history: old file not deleted', ['path' => $shot->path, 'error' => $e->getMessage()]);
            }
            $shot->delete();
        }
    }
}
