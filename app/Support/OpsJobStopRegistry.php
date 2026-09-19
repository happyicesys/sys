<?php

namespace App\Support;

use App\Models\OpsJob;
use App\Models\OpsJobItem;
use App\Models\OpsJobTask;
use App\Models\ServiceNotice;
use App\Models\StockCheck;

/**
 * The kinds of row an ops job carries, and the one way to write their place in
 * the visiting order. The job table and the Route page send a merged, typed
 * list (`{type, id}`); every sequence writer resolves the type here instead of
 * growing another if-branch. Add the next stop type to TYPES and nowhere else.
 */
final class OpsJobStopRegistry
{
    public const TYPE_ITEM = 'item';

    public const TYPE_TASK = 'task';

    /** @var array<string, class-string<\Illuminate\Database\Eloquent\Model>> */
    public const TYPES = [
        self::TYPE_ITEM => OpsJobItem::class,
        self::TYPE_TASK => OpsJobTask::class,
        ServiceNotice::STOP_TYPE => ServiceNotice::class,
        StockCheck::STOP_TYPE => StockCheck::class,
    ];

    /**
     * Stop types that keep a job's route editable for as long as they exist,
     * whatever their own status — the rule tasks already followed.
     */
    private const ALWAYS_REORDERABLE = [
        self::TYPE_TASK => 'opsJobTasks',
        ServiceNotice::STOP_TYPE => 'serviceNotices',
        StockCheck::STOP_TYPE => 'stockChecks',
    ];

    /**
     * An unknown or missing type is an item: that is what the payload meant
     * before tasks existed, and the legacy callers still send it that way.
     *
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    public static function modelFor(?string $type): string
    {
        return self::TYPES[$type] ?? self::TYPES[self::TYPE_ITEM];
    }

    /** Write one stop's sequence, only if it belongs to this job. */
    public static function applySequence(OpsJob $opsJob, ?string $type, int|string $id, int|float|string|null $sequence): void
    {
        self::modelFor($type)::query()
            ->whereKey($id)
            ->where('ops_job_id', $opsJob->id)
            ->update(['sequence' => $sequence]);
    }

    public static function hasReorderableStops(OpsJob $opsJob): bool
    {
        foreach (self::ALWAYS_REORDERABLE as $relation) {
            if ($opsJob->{$relation}()->exists()) {
                return true;
            }
        }

        return false;
    }
}
