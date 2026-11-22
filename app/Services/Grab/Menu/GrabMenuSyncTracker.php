<?php

namespace App\Services\Grab\Menu;

use App\Jobs\NotifyDeliveryPlatformUpdateMenu;
use App\Models\DeliveryPlatformApiError;
use App\Models\DeliveryProductMappingVend;
use App\Models\GrabMenuSync;
use App\Models\GrabMenuSyncStore;
use App\Services\DeliveryPlatformService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Grab Menu Sync (Brian, 2026-11-21): every change that asks Grab to read a store's menu again is one
 * `grab_menu_syncs` row, and each store it reached one `grab_menu_sync_stores` row, followed until Grab's own
 * SUCCESS says the menu is on Grab. The only writer of both tables.
 *
 * - A writer wraps its change in record(); every NotifyDeliveryPlatformUpdateMenu::debounced() inside it adds
 *   the store (queued()). Nested record() calls ride along on the outer one, so a store-rule save made from the
 *   store setup window is one row, not three.
 * - The notify job reports what it did (skipped / refused / alreadyOnGrab / sent / turnedAway); the menu pull
 *   (pulled) and Grab's menu sync webhook (grabAnswered) finish it. A job serves EVERY open row of its store,
 *   so a change that rode along on another change's job is followed too.
 * - check() is the system's own check (grab:check-menu-sync, every 10 min): stuck rows are asked again, and a
 *   store whose prices on Grab (the menu it last pulled) differ from what the rule gives now is sent again
 *   under a "System check" row.
 *
 * Never throws into a save or a job: tracking is a record, never a reason for a change to fail.
 */
class GrabMenuSyncTracker
{
    // Kinds kept even when no store was asked: the rule is saved, and the page says why Grab was not asked.
    const KEEP_EMPTY = [GrabMenuSync::KIND_COMPANY_RULE, GrabMenuSync::KIND_STORE_RULE];

    // Grab answers a notify by pulling within seconds; a row with no pull this long after the ask is asked again.
    const PULL_WAIT_MINUTES = 20;

    // A pulled menu whose SUCCESS never came this long after is taken as on Grab.
    const CONFIRM_WAIT_MINUTES = 20;

    // A row that has not moved this long (its job lost to a deploy or a queue restart) is queued again.
    const STALE_MINUTES = 30;

    // "Grab never pulled" is asked again this many times before the row reads Not synced.
    const MAX_REASKS = 2;

    // The system check sends a store again at most this often.
    const RECHECK_MINUTES = 60;

    // A store whose last ask failed is sent again by the system check at most this often.
    const FAILED_RECHECK_MINUTES = 24 * 60;

    // A queued ask that still has not run this long after its change gives up (its job keeps throwing before it
    // reaches Grab, e.g. an operator with no Grab token, or the store link is gone) instead of being queued forever.
    const STALE_GIVE_UP_MINUTES = 180;

    // A difference is acted on only when the previous check saw it too: a planogram change is on its way to
    // Grab through its own (unlogged) notify for a few minutes, and must not be sent twice.
    const DIFFER_SEEN_MINUTES = 8;

    private ?GrabMenuSync $current = null;

    // Stores added to the current change so far, so their asks can be spaced out.
    private int $queuedCount = 0;

    /**
     * Run $work as one change. Stores notified inside it are added to it.
     *
     * @template T
     *
     * @param  callable(): T  $work
     * @return T
     */
    public function record(string $kind, ?int $operatorId, ?int $userId, string $summary, callable $work, ?array $details = null): mixed
    {
        if ($this->current) {
            return $work();
        }

        $sync = $this->safely(fn () => GrabMenuSync::create([
            'operator_id' => $operatorId,
            'user_id' => $userId,
            'kind' => $kind,
            'summary' => mb_substr($summary, 0, 500),
            'details' => $details,
        ]));
        $this->current = $sync;
        $this->queuedCount = 0;

        try {
            return $work();
        } finally {
            $this->current = null;
            if ($sync) {
                $this->safely(function () use ($sync, $kind) {
                    $count = $sync->stores()->count();
                    if ($count === 0 && ! in_array($kind, self::KEEP_EMPTY, true)) {
                        $sync->delete();
                    } else {
                        $sync->update(['store_count' => $count]);
                    }
                });
            }
        }
    }

    public function recording(): bool
    {
        return $this->current !== null;
    }

    /**
     * A store notified while a change is being recorded joins it. Returns its place in the change (0, 1, 2 …),
     * so a change reaching many stores asks Grab for them a few seconds apart; null when nothing is recorded.
     */
    public function queued(DeliveryProductMappingVend $binding): ?int
    {
        if (! $this->current) {
            return null;
        }

        $row = $this->safely(fn () => $this->addStore($this->current, $binding));

        return $row?->wasRecentlyCreated ? $this->queuedCount++ : null;
    }

    // ---- What the notify job did ------------------------------------------------------------------

    public function skipped(DeliveryProductMappingVend $binding, string $why): void
    {
        $this->update($binding, GrabMenuSyncStore::BEFORE_SEND, ['status' => GrabMenuSyncStore::STATUS_SKIPPED, 'last_message' => $why, 'retry_at' => null]);
    }

    public function refused(DeliveryProductMappingVend $binding, ?int $code, string $why): void
    {
        $this->update($binding, GrabMenuSyncStore::BEFORE_SEND, ['status' => GrabMenuSyncStore::STATUS_FAILED, 'last_code' => $code, 'last_message' => $why, 'retry_at' => null]);
    }

    // The menu Grab would pull equals the one it pulled last: nothing to send.
    public function alreadyOnGrab(DeliveryProductMappingVend $binding): void
    {
        $this->update($binding, GrabMenuSyncStore::BEFORE_SEND, [
            'status' => GrabMenuSyncStore::STATUS_SYNCED,
            'synced_at' => now(),
            'last_message' => 'Grab already had this menu',
            'retry_at' => null,
        ]);
    }

    public function sent(DeliveryProductMappingVend $binding, ?array $menu): void
    {
        $this->safely(function () use ($binding, $menu) {
            $rows = $this->rowsOf($binding, GrabMenuSyncStore::BEFORE_SEND);
            foreach ($rows as $row) {
                $row->update([
                    'status' => GrabMenuSyncStore::STATUS_SENT,
                    'attempts' => min(255, $row->attempts + 1),
                    'sent_at' => now(),
                    'retry_at' => null,
                    'last_code' => 200,
                    'last_message' => null,
                    'expected_json' => $menu ? self::prices($menu) : $row->expected_json,
                ]);
            }
        });
    }

    // Grab refused the ask; $retryAt null = no more asks, the row reads Not synced.
    public function turnedAway(DeliveryProductMappingVend $binding, ?int $code, string $why, ?Carbon $retryAt): void
    {
        $this->safely(function () use ($binding, $code, $why, $retryAt) {
            foreach ($this->rowsOf($binding, GrabMenuSyncStore::BEFORE_SEND) as $row) {
                $row->update([
                    'status' => $retryAt ? GrabMenuSyncStore::STATUS_TURNED_AWAY : GrabMenuSyncStore::STATUS_FAILED,
                    'attempts' => min(255, $row->attempts + 1),
                    'retry_at' => $retryAt,
                    'last_code' => $code,
                    'last_message' => mb_substr($why, 0, 500),
                ]);
            }
        });
    }

    // ---- What Grab did ----------------------------------------------------------------------------

    /** Grab pulled this store's menu at $at: every change made before then is in what it took. */
    public function pulled(DeliveryProductMappingVend $binding, Carbon $at, ?array $before = null, ?array $after = null): void
    {
        $this->safely(function () use ($binding, $at, $before, $after) {
            // When Grab last took the store's menu, and when what it took changed: the items listed, their prices.
            // A plain table update: these are facts about Grab, not an edit of the link (no audit row, no updated_at).
            $times = ['grab_menu_pulled_at' => $at];
            if ($after !== null) {
                if ($before === null || self::listing($before) !== self::listing($after)) {
                    $times['grab_items_changed_at'] = $at;
                }
                if ($before === null || self::prices($before) !== self::prices($after)) {
                    $times['grab_prices_changed_at'] = $at;
                }
            }
            DB::table('delivery_product_mapping_vend')->where('id', $binding->id)->update($times);

            GrabMenuSyncStore::query()
                ->where('delivery_product_mapping_vend_id', $binding->id)
                ->whereIn('status', [...GrabMenuSyncStore::BEFORE_SEND, GrabMenuSyncStore::STATUS_SENT])
                ->where('created_at', '<=', $at)
                ->update(['status' => GrabMenuSyncStore::STATUS_PULLED, 'pulled_at' => $at, 'retry_at' => null, 'updated_at' => now()]);
        });
    }

    /**
     * Grab's menu sync webhook: the job it ran on the menu it pulled. SUCCESS = on Grab; FAILED = not, with
     * Grab's reasons. QUEUEING / PROCESSING change nothing.
     */
    public function grabAnswered(?string $merchantId, ?string $status, ?Carbon $at, array $errors = []): void
    {
        $status = strtoupper((string) $status);
        if (! $merchantId || ! in_array($status, ['SUCCESS', 'FAILED'], true)) {
            return;
        }

        $this->safely(function () use ($merchantId, $status, $at, $errors) {
            $at ??= now();
            // A row is answered when Grab's job came after it was asked (pulled rows: Grab already took it).
            $rows = GrabMenuSyncStore::query()
                ->where('merchant_id', $merchantId)
                ->whereIn('status', GrabMenuSyncStore::AWAITING_GRAB)
                ->where(fn ($query) => $query->where('status', GrabMenuSyncStore::STATUS_PULLED)
                    ->orWhere('sent_at', '<=', $at->copy()->addSeconds(5)));

            if ($status === 'SUCCESS') {
                $rows->update(['status' => GrabMenuSyncStore::STATUS_SYNCED, 'synced_at' => $at, 'last_message' => null, 'updated_at' => now()]);

                return;
            }

            $why = collect($errors)->map(fn ($error) => is_array($error) ? json_encode($error, JSON_UNESCAPED_UNICODE) : (string) $error)->implode('; ');
            $rows->update([
                'status' => GrabMenuSyncStore::STATUS_FAILED,
                'last_message' => mb_substr('Grab could not take the menu'.($why !== '' ? ': '.$why : ''), 0, 500),
                'updated_at' => now(),
            ]);
        });
    }

    // ---- Asked again by a person ------------------------------------------------------------------

    /**
     * Ask Grab again for these rows (a person's "Send again"): back to waiting, sent even if the menu did not
     * change (Grab may have failed to take it). Rows of a store that does not sell now are skipped.
     *
     * @param  Collection<int, GrabMenuSyncStore>  $rows
     */
    public function resend(Collection $rows): int
    {
        $sent = 0;
        foreach ($rows->groupBy('delivery_product_mapping_vend_id') as $bindingRows) {
            $binding = $bindingRows->first()->binding;
            if (! $binding) {
                continue;
            }
            GrabMenuSyncStore::query()->whereIn('id', $bindingRows->pluck('id'))->update([
                'status' => GrabMenuSyncStore::STATUS_WAITING,
                'attempts' => 0,
                'retry_at' => null,
                'last_code' => null,
                'last_message' => 'Sent again by a person',
                'updated_at' => now(),
            ]);
            NotifyDeliveryPlatformUpdateMenu::dispatch($binding, true)->onQueue('default')->delay($this->spacedAsk($sent));
            $sent++;
        }

        return $sent;
    }

    // ---- The system's own check -------------------------------------------------------------------

    /**
     * Stuck rows are moved on, and every selling Grab store's prices on Grab (the menu it last pulled) are
     * compared with what the rule gives now; a store that differs is sent again under one "System check" row.
     *
     * @param  int[]|null  $operatorIds
     * @return array{moved: int, matched: int, differ: int, resent: int}
     */
    public function check(?array $operatorIds = null): array
    {
        $result = ['moved' => $this->moveStuckRows(), 'matched' => 0, 'differ' => 0, 'resent' => 0];

        $bindings = DeliveryProductMappingVend::query()->withoutGlobalScopes()
            ->selling()
            ->with('deliveryPlatformRefNumber')
            ->whereHas('deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform', fn ($query) => $query->where('slug', 'grab'))
            ->when($operatorIds !== null, fn ($query) => $query->whereHas('deliveryPlatformRefNumber', fn ($ref) => $ref->whereIn('operator_id', $operatorIds)))
            ->get();

        $platform = app(DeliveryPlatformService::class);
        $differing = [];
        $baseline = [];
        foreach ($bindings as $binding) {
            $merchantId = $platform->merchantIdFromMappingVend($binding);
            if ($merchantId && DeliveryPlatformApiError::refusedRecently($merchantId, [403])) {
                continue;
            }
            if (GrabMenuSyncStore::query()->where('delivery_product_mapping_vend_id', $binding->id)->whereIn('status', GrabMenuSyncStore::OPEN)->exists()) {
                continue;
            }

            try {
                $diff = self::difference($platform->buildMenu($binding), $binding->last_menu_json);
            } catch (Throwable $e) {
                Log::warning('grab menu check could not build a menu', ['binding' => $binding->id, 'error' => $e->getMessage()]);

                continue;
            }

            $latest = GrabMenuSyncStore::query()->where('delivery_product_mapping_vend_id', $binding->id)->latest('id')->first();
            $grabFailed = $latest?->status === GrabMenuSyncStore::STATUS_FAILED;

            if ($diff === null && ! $grabFailed) {
                Cache::forget(self::differKey($binding));
                $result['matched']++;
                if ($latest) {
                    $latest->update(['last_checked_at' => now()]);
                } else {
                    $baseline[] = $binding;
                }

                continue;
            }

            $result['differ']++;
            $firstSeen = Cache::get(self::differKey($binding));
            if (! $firstSeen) {
                Cache::put(self::differKey($binding), now()->getTimestamp(), now()->addHours(2));
            }
            if (! $grabFailed && (! $firstSeen || $firstSeen > now()->subMinutes(self::DIFFER_SEEN_MINUTES)->getTimestamp())) {
                continue;
            }
            // The store's last ask failed (Grab refused the menu, e.g. "subCategories shouldn't be empty", or the ask
            // never reached Grab, e.g. no token): the system check tries it again at most once a day, not hourly. A
            // person's own change is its own record and still goes out at once.
            $recheckMinutes = $grabFailed ? self::FAILED_RECHECK_MINUTES : self::RECHECK_MINUTES;
            $recent = GrabMenuSyncStore::query()->where('delivery_product_mapping_vend_id', $binding->id)
                ->where('created_at', '>=', now()->subMinutes($recheckMinutes))
                ->whereHas('sync', fn ($sync) => $sync->where('kind', GrabMenuSync::KIND_SELF_CHECK))
                ->exists();
            if (! $recent) {
                $differing[] = [$binding, $diff ?? 'Grab could not take the last menu'];
            }
        }

        if ($differing || $baseline) {
            $summary = $differing
                ? trans_choice('System check: :count store on Grab differs from its price rule, sent again|System check: :count stores on Grab differ from their price rule, sent again', count($differing), ['count' => count($differing)])
                : trans_choice('System check: :count store on Grab matches its price rule|System check: :count stores on Grab match their price rule', count($baseline), ['count' => count($baseline)]);

            $this->record(GrabMenuSync::KIND_SELF_CHECK, null, null, $summary, function () use ($differing, $baseline, &$result) {
                foreach ($baseline as $binding) {
                    $row = $this->addStore($this->current, $binding);
                    $row?->update(['status' => GrabMenuSyncStore::STATUS_SYNCED, 'synced_at' => now(), 'last_checked_at' => now(), 'last_message' => 'Prices on Grab match the rule']);
                }
                foreach ($differing as [$binding, $why]) {
                    $row = $this->addStore($this->current, $binding);
                    $row?->update(['last_message' => mb_substr($why, 0, 500)]);
                    // Forced: the menu may equal the one Grab pulled when Grab failed to take it.
                    NotifyDeliveryPlatformUpdateMenu::dispatch($binding, true)->onQueue('default')->delay(now()->addSeconds($result['resent'] * (int) config('grab.daily_sync_spacing_seconds', 3)));
                    $result['resent']++;
                }
            });
        }

        return $result;
    }

    /**
     * Rows whose next step never came: Grab never pulled (asked again, twice), never confirmed a pull (taken
     * as on Grab), or a queued ask that never ran (queued again).
     */
    private function moveStuckRows(): int
    {
        $moved = 0;
        $asked = 0;

        $unpulled = GrabMenuSyncStore::query()->with('binding')->where('status', GrabMenuSyncStore::STATUS_SENT)
            ->where('sent_at', '<', now()->subMinutes(self::PULL_WAIT_MINUTES))->get();
        foreach ($unpulled as $row) {
            $moved++;
            if ($row->attempts > self::MAX_REASKS || ! $row->binding?->isSelling()) {
                $row->update(['status' => GrabMenuSyncStore::STATUS_FAILED, 'last_message' => 'Grab never pulled the menu after being asked']);

                continue;
            }
            $row->update(['status' => GrabMenuSyncStore::STATUS_WAITING, 'last_message' => 'Grab did not pull the menu; asked again']);
            NotifyDeliveryPlatformUpdateMenu::dispatch($row->binding, true)->onQueue('default')->delay($this->spacedAsk($asked++));
        }

        $moved += GrabMenuSyncStore::query()->where('status', GrabMenuSyncStore::STATUS_PULLED)
            ->where('pulled_at', '<', now()->subMinutes(self::CONFIRM_WAIT_MINUTES))
            ->update([
                'status' => GrabMenuSyncStore::STATUS_SYNCED,
                'synced_at' => now(),
                'last_message' => 'Grab took the menu; its confirmation never came',
                'updated_at' => now(),
            ]);

        $stale = GrabMenuSyncStore::query()->with('binding')->whereIn('status', GrabMenuSyncStore::BEFORE_SEND)
            ->where('updated_at', '<', now()->subMinutes(self::STALE_MINUTES))
            ->where(fn ($query) => $query->whereNull('retry_at')->orWhere('retry_at', '<', now()->subMinutes(10)))
            ->get();
        foreach ($stale->groupBy('delivery_product_mapping_vend_id') as $rows) {
            $binding = $rows->first()->binding;
            $moved += $rows->count();
            $givenUp = $rows->filter(fn ($row) => $row->created_at < now()->subMinutes(self::STALE_GIVE_UP_MINUTES));
            if (! $binding || $givenUp->isNotEmpty()) {
                GrabMenuSyncStore::query()->whereIn('id', $rows->pluck('id'))->update([
                    'status' => GrabMenuSyncStore::STATUS_FAILED,
                    'last_message' => $binding ? 'The ask to Grab never ran (its job kept failing before reaching Grab)' : 'The store link no longer exists',
                    'updated_at' => now(),
                ]);

                continue;
            }
            GrabMenuSyncStore::query()->whereIn('id', $rows->pluck('id'))->update(['updated_at' => now()]);
            NotifyDeliveryPlatformUpdateMenu::dispatch($binding, true)->onQueue('default')->delay($this->spacedAsk($asked++));
        }

        return $moved;
    }

    /**
     * Several stores asked again at once go out `grab.daily_sync_spacing_seconds` apart, like the nightly
     * sync: one company-rule save re-asked for 63 stores in the same second drew 429s from Grab (2026-11-21).
     */
    private function spacedAsk(int $place): \DateTimeInterface
    {
        return now()->addSeconds($place * (int) config('grab.daily_sync_spacing_seconds', 3));
    }

    // ---- Shared ------------------------------------------------------------------------------------

    /** {item id: price in cents} of a Grab menu (ours or the one Grab pulled). */
    public static function prices(?array $menu): array
    {
        return collect($menu['categories'] ?? [])
            ->flatMap(fn ($category) => $category['subCategories'] ?? [])
            ->flatMap(fn ($sub) => $sub['items'] ?? [])
            ->mapWithKeys(fn ($item) => [(string) ($item['id'] ?? '') => (int) round((float) ($item['price'] ?? 0))])
            ->forget('')
            ->sortKeys()
            ->all();
    }

    /**
     * What a menu lists, without what moves on its own: prices (their own time), stock and availability (every sale)
     * and the selling-time start (today's date). Two menus with the same listing show customers the same items.
     * Keys are sorted: the stored copy sits in a MySQL JSON column, which keeps its own key order.
     */
    public static function listing(?array $menu): string
    {
        $strip = function ($value) use (&$strip) {
            if (! is_array($value)) {
                return $value;
            }
            foreach (['price', 'availableStatus', 'maxStock', 'maxCount', 'startTime', 'endTime'] as $moving) {
                unset($value[$moving]);
            }
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map($strip, $value);
        };

        return md5(json_encode($strip($menu['categories'] ?? [])));
    }

    /**
     * Why the menu Grab pulled last differs from ours in what customers pay (prices, items listed), in words;
     * null = the same. Stock is left out: it moves with every sale and has its own path to Grab.
     */
    public static function difference(array $ours, ?array $onGrab): ?string
    {
        if (! $onGrab) {
            return 'Grab has never pulled this store\'s menu';
        }

        $want = self::prices($ours);
        $have = self::prices($onGrab);
        $parts = [];

        $changed = collect($want)->filter(fn ($price, $id) => array_key_exists($id, $have) && $have[$id] !== $price);
        if ($changed->isNotEmpty()) {
            $parts[] = 'price '.$changed->map(fn ($price, $id) => $id.' '.self::dollars($have[$id]).' → '.self::dollars($price))->take(5)->implode(', ')
                .($changed->count() > 5 ? ' (+'.($changed->count() - 5).')' : '');
        }
        $missing = array_diff_key($want, $have);
        if ($missing) {
            $parts[] = 'not on Grab '.implode(', ', array_slice(array_keys($missing), 0, 5)).(count($missing) > 5 ? ' (+'.(count($missing) - 5).')' : '');
        }
        $extra = array_diff_key($have, $want);
        if ($extra) {
            $parts[] = 'should leave Grab '.implode(', ', array_slice(array_keys($extra), 0, 5)).(count($extra) > 5 ? ' (+'.(count($extra) - 5).')' : '');
        }

        return $parts ? 'Grab differs: '.implode('; ', $parts) : null;
    }

    private static function differKey(DeliveryProductMappingVend $binding): string
    {
        return 'grab-menu-check-differs:'.$binding->id;
    }

    private static function dollars(int $cents): string
    {
        return number_format($cents / 100, 2);
    }

    private function addStore(GrabMenuSync $sync, DeliveryProductMappingVend $binding): ?GrabMenuSyncStore
    {
        $ref = $binding->deliveryPlatformRefNumber;

        return GrabMenuSyncStore::query()->firstOrCreate(
            ['grab_menu_sync_id' => $sync->id, 'delivery_product_mapping_vend_id' => $binding->id],
            [
                'delivery_platform_ref_number_id' => $binding->delivery_platform_ref_number_id,
                'operator_id' => $ref?->operator_id ?? $binding->deliveryProductMapping()->withoutGlobalScopes()->value('operator_id'),
                'merchant_id' => $ref?->ref_number ?? $binding->platform_ref_id,
                'vend_code' => (string) $binding->vend_code,
                'status' => GrabMenuSyncStore::STATUS_WAITING,
            ],
        );
    }

    /** @return Collection<int, GrabMenuSyncStore> */
    private function rowsOf(DeliveryProductMappingVend $binding, array $statuses): Collection
    {
        return GrabMenuSyncStore::query()->where('delivery_product_mapping_vend_id', $binding->id)->whereIn('status', $statuses)->get();
    }

    private function update(DeliveryProductMappingVend $binding, array $statuses, array $values): void
    {
        $this->safely(fn () => GrabMenuSyncStore::query()
            ->where('delivery_product_mapping_vend_id', $binding->id)
            ->whereIn('status', $statuses)
            ->update([...$values, 'updated_at' => now()]));
    }

    private function safely(callable $write): mixed
    {
        try {
            return $write();
        } catch (Throwable $e) {
            Log::warning('grab menu sync tracking failed', ['error' => $e->getMessage()]);

            return null;
        }
    }
}
