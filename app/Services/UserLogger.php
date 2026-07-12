<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Throwable;

/**
 * App-wide user-action audit capture.
 *
 * A single set of wildcard Eloquent listeners feeds every Create/Update/Delete
 * across all models into one place — no per-model traits or observers needed.
 *
 * What is intentionally NOT logged (by design), because none have a 'web'
 * authenticated user (no "who"):
 *   - artisan commands, scheduled cron, and queue workers,
 *   - VendDataService HTTP/MQTT machine ingestion,
 *   - token/API requests and anonymous public pages,
 *   - plus any model on the config('userlog.deny') list.
 */
class UserLogger
{
    /** Eloquent lifecycle hooks we record, mapped to stored event names. */
    private const EVENTS = ['created', 'updated', 'deleted', 'restored'];

    /**
     * Register the wildcard listeners. Call once from AppServiceProvider::boot().
     */
    public static function listen(): void
    {
        if (! config('userlog.enabled', true)) {
            return;
        }

        // Never register the wildcard listeners in console contexts — artisan
        // commands, scheduled cron, and queue workers (queue:work runs in the
        // console) have no 'web' user and would only ever be gated out anyway.
        // Skipping registration entirely keeps those high-volume Eloquent writes
        // (machine ingestion via queued jobs) completely off this listener,
        // avoiding the boot-time overhead that caused the 2026-07-01 backlog.
        if (app()->runningInConsole()) {
            return;
        }

        foreach (self::EVENTS as $event) {
            Event::listen("eloquent.{$event}: *", function (string $eventName, array $payload) use ($event): void {
                $model = $payload[0] ?? null;
                if ($model instanceof Model) {
                    self::record($event, $model);
                }
            });
        }
    }

    /**
     * Gate + persist a single audit row. Must never throw into the host request.
     */
    public static function record(string $event, Model $model): void
    {
        try {
            // GATE 1 — only genuine human web actions.
            // No 'web' session means: cron / artisan, queue workers, machine
            // ingestion (VendDataService HTTP/MQTT), token/API requests, and
            // anonymous public pages — all correctly skipped (no "who").
            if (Auth::guard('web')->guest()) {
                return;
            }

            $user = Auth::guard('web')->user();

            // GATE 2 — deny-list (high-volume / self-auditing models).
            $class = get_class($model);
            $base  = class_basename($class);
            if (in_array($base, (array) config('userlog.deny', []), true)) {
                return;
            }

            // Build the change set; skip no-op updates (e.g. only timestamps).
            $changes = self::changesFor($event, $model, $base);
            if ($event === 'updated' && $changes === []) {
                return;
            }

            // Raw insert: deliberately bypasses Eloquent events so writing the
            // log can never re-trigger this listener (no recursion) and stays cheap.
            DB::table('user_logs')->insert([
                'user_id'        => $user?->getKey(),
                'user_name'      => $user?->name,
                'event'          => $event,
                'auditable_type' => $class,
                'auditable_id'   => $model->getKey(),
                'changes'        => $changes === [] ? null : json_encode($changes),
                'source'         => 'web',
                'ip'             => request()->ip(),
                'url'            => mb_substr((string) request()->path(), 0, 2048),
                'created_at'     => now(),
            ]);
        } catch (Throwable $e) {
            // Auditing must never break the user's action.
            report($e);
        }
    }

    /**
     * updated   -> { field: [old, new] } for changed, non-ignored columns.
     * created   -> filtered snapshot of the new attributes.
     * deleted   -> filtered snapshot of what was removed.
     * restored  -> filtered snapshot of the restored row.
     */
    private static function changesFor(string $event, Model $model, string $base): array
    {
        $conf   = (array) config('userlog.ignore_columns', []);
        $ignore = array_merge((array) ($conf['*'] ?? []), (array) ($conf[$base] ?? []));

        if ($event === 'updated') {
            $diff     = [];
            $original = $model->getOriginal();
            foreach ($model->getChanges() as $key => $new) {
                if (in_array($key, $ignore, true)) {
                    continue;
                }
                $old = $original[$key] ?? null;
                if ($old === $new) {
                    continue;
                }
                $diff[$key] = [$old, $new];
            }

            return $diff;
        }

        $attrs = $model->attributesToArray();
        foreach ($ignore as $key) {
            unset($attrs[$key]);
        }

        return $attrs;
    }
}
