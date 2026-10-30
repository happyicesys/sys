<?php

namespace App\Console\Commands;

use App\Models\DeliveryProductMappingVend;
use App\Services\DeliveryPlatformService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Re-assert on Grab the pause state that mark1 already holds locally.
 *
 * WHY THIS IS NEEDED AT ALL. Grab caps a store pause at 24 hours - their
 * PauseStoreRequest accepts 30 minutes, 1 hour or 24 hours and nothing longer.
 * There is no indefinite pause. So a vend paused in mark1 is paused on Grab for
 * one day and then quietly starts taking orders again, however correctly the
 * pause was pushed. Pausing is a temporary stop, never a delisting.
 *
 * WHAT WENT WRONG WITHOUT IT. DeliveryPlatformService::pauseStore() wrapped its
 * three arguments in one array, so Grab rejected every call and the failure was
 * swallowed. Vend 4754 was paused on 2026-09-05 and kept taking Grab orders:
 * 150 order attempts since, each returning 500 and each retried 15 times by
 * Grab before the customer's order died. The argument bug is fixed, but the
 * 24-hour ceiling means a single push still would not have held.
 *
 * SCOPE. Mappings that are paused (is_active = 0) or whose ref number is Inactive
 * (2026-10-29: Inactive on the Ref Numbers page switches the store off, so Grab must
 * be kept from reopening it too - 2871's ref was Inactive while Grab still listed
 * it), and NOT ended (end_date null).
 * An ended mapping is a retired site whose merchant Grab has normally closed
 * already - on 2026-09-23 that was 221 of 237 paused production mappings, and
 * re-pausing them every run would mean hundreds of pointless calls and a flood
 * of failures for merchants that no longer exist. Pass --include-ended to widen
 * it anyway.
 *
 * DIRECTION. Pause only. It never resumes a store, because ops can pause a
 * merchant directly in Grab's portal and an auto-resume here would silently
 * undo that. Resuming stays a deliberate act in the mark1 UI.
 *
 * THE MERCHANT, NOT THE ROW. pauseStore() takes a Grab merchant ID, and a
 * merchant is REUSED across vends when a listing moves machine - 4-C7CDNZDXKEBJPE
 * has a stale paused row on vend 2658 and a LIVE one on vend 2873, and
 * 4-C7CCRTEJJYUJR6 has run 2629 -> 2734 -> 2114 -> 2401. Pausing on the stale
 * row would take the live machine off Grab for 24 hours. So any merchant holding
 * an active mapping row anywhere is skipped, however many paused rows it also
 * has: mark1's intent for a merchant is the union of its rows, not one of them.
 *
 * Dry run by default; pass --apply to send.
 */
class SyncDeliveryStorePause extends Command
{
    protected $signature = 'delivery:sync-store-pause
                            {--apply : Send the pause calls; without it the command only reports}
                            {--include-ended : Also re-pause mappings that have an end_date}
                            {--mapping-vend= : Only this delivery_product_mapping_vend id}
                            {--type=production : Operator type to act on (production or sandbox)}
                            {--only-if-open : Ask Grab first and pause only a store Grab reports open}';

    protected $description = 'Re-assert paused Grab stores, which Grab expires after 24 hours';

    // Also read by grab:missed-orders: a store Grab refuses us is skipped there too.
    public const REFUSED_CACHE_PREFIX = 'grab-store-refused:';

    // Grab's store endpoints allow 5 requests a second per project, shared by every
    // store; the first run's burst drew 429s.
    private function throttle(): void
    {
        usleep((int) config('grab.store_api_spacing_ms', 300) * 1000);
    }

    public function handle(DeliveryPlatformService $deliveryPlatformService): int
    {
        $apply = (bool) $this->option('apply');

        // No authenticated user in the console, and DeliveryProductMappingVend
        // carries an operator viewer scope - drop it explicitly rather than rely
        // on the unauthenticated branch staying permissive.
        $query = DeliveryProductMappingVend::query()
            ->withoutGlobalScopes()
            ->where(function ($q) {
                $q->where('is_active', false)->orRefNumberInactive();
            })
            ->whereHas('deliveryProductMapping.deliveryPlatformOperator', function ($q) {
                $q->where('type', $this->option('type'))
                    ->whereHas('deliveryPlatform', fn ($p) => $p->where('slug', 'grab'));
            });

        if (! $this->option('include-ended')) {
            $query->whereNull('end_date');
        }

        if ($mappingVendId = $this->option('mapping-vend')) {
            $query->where('id', $mappingVendId);
        }

        $mappingVends = $query->orderBy('id')->get();

        // Merchants that are live on SOME vend. Built from the same platform and
        // operator type, with no end_date/paused filter - one active row anywhere
        // means the merchant is selling and must never be paused from here. A row
        // whose ref number is Inactive does not sell, so it does not count.
        $liveMerchantIds = DeliveryProductMappingVend::query()
            ->withoutGlobalScopes()
            ->where('is_active', true)
            ->refNumberNotInactive()
            ->whereHas('deliveryProductMapping.deliveryPlatformOperator', function ($q) {
                $q->where('type', $this->option('type'))
                    ->whereHas('deliveryPlatform', fn ($p) => $p->where('slug', 'grab'));
            })
            ->with('deliveryPlatformRefNumber')
            ->get()
            ->map(fn ($mv) => $mv->deliveryPlatformRefNumber?->ref_number ?? $mv->platform_ref_id)
            ->filter()
            ->unique()
            ->flip();

        if ($mappingVends->isEmpty()) {
            $this->info('No paused Grab mappings in scope.');

            return self::SUCCESS;
        }

        $this->line(($apply ? 'Pausing ' : 'DRY RUN - would pause ').$mappingVends->count().' store(s) on Grab.');
        $this->newLine();

        $rows = [];
        $sent = 0;
        $failed = 0;
        $skipped = 0;
        $alreadyClosed = 0;
        $refused = 0;
        $rateLimited = false;

        foreach ($mappingVends as $mappingVend) {
            $merchantId = $mappingVend->deliveryPlatformRefNumber?->ref_number
                ?? $mappingVend->platform_ref_id;

            if (! $merchantId) {
                $rows[] = [$mappingVend->id, $mappingVend->vend_code, '-', 'skipped: no merchant id'];

                continue;
            }

            if ($liveMerchantIds->has($merchantId)) {
                $rows[] = [$mappingVend->id, $mappingVend->vend_code, $merchantId, 'SKIPPED: merchant is live on another vend'];
                $skipped++;

                continue;
            }

            if (! $apply) {
                $rows[] = [$mappingVend->id, $mappingVend->vend_code, $merchantId, 'would pause'];

                continue;
            }

            if ($this->option('only-if-open')) {
                // A store still paused on Grab is left alone: re-sending the same
                // state answers 409 and does not extend the pause. Grab ends a pause
                // after 24 h, so this re-pauses within one scheduled run of that.
                if (Cache::has(self::REFUSED_CACHE_PREFIX.$merchantId)) {
                    $rows[] = [$mappingVend->id, $mappingVend->vend_code, $merchantId, 'skipped: Grab refused this store in the last 24 h'];
                    $refused++;

                    continue;
                }

                try {
                    $status = $deliveryPlatformService->storeStatus($mappingVend);
                } catch (\Throwable $e) {
                    $status = ['success' => false, 'code' => null, 'data' => null];
                }
                $this->throttle();

                // 403 / 401: Grab no longer lets us act for this store (DCVIC's 11, paused
                // in mark1 since 2025-12-29: 403 on the first run 2026-10-29, 401 from that
                // evening, its account's token refused). Nothing to pause; asked again only
                // after a day, with one warning a day instead of an ERROR every 10 min.
                $code = $status['code'] ?? null;
                if ($code === 403 || $code === 401) {
                    Cache::put(self::REFUSED_CACHE_PREFIX.$merchantId, true, now()->addDay());
                    Log::warning('Grab refuses this store; pause re-assert skips it for 24 h', [
                        'delivery_product_mapping_vend_id' => $mappingVend->id,
                        'merchant_id' => $merchantId,
                        'code' => $code,
                    ]);
                    $rows[] = [$mappingVend->id, $mappingVend->vend_code, $merchantId, "skipped: Grab answers {$code} for this store"];
                    $refused++;

                    continue;
                }

                // Over Grab's store-API rate limit: stop, the next run carries on.
                if (($status['code'] ?? null) === 429) {
                    $rows[] = [$mappingVend->id, $mappingVend->vend_code, $merchantId, 'stopped: Grab rate limit (429), next run continues'];
                    $rateLimited = true;

                    break;
                }

                if ($status['success'] && ($status['data']['isOpen'] ?? null) === false) {
                    $rows[] = [$mappingVend->id, $mappingVend->vend_code, $merchantId, 'already closed on Grab'.(isset($status['data']['closeReason']) ? ' ('.$status['data']['closeReason'].')' : '')];
                    $alreadyClosed++;

                    continue;
                }
                // Open, or an unreadable answer: pause (a 409 then is harmless).
            }

            try {
                // pauseStore() returns the whole Grab response on success (a
                // successful pause has an EMPTY body, so the payload alone would be
                // null) and null on failure, having already logged the status code.
                // A merchant Grab no longer knows about fails here, expected noise.
                $result = $deliveryPlatformService->pauseStore($mappingVend, true);
                $ok = $result !== null;
            } catch (\Throwable $e) {
                // One missing oauth token must not abandon the rest of the run.
                $rows[] = [$mappingVend->id, $mappingVend->vend_code, $merchantId, 'error: '.$e->getMessage()];
                $failed++;

                continue;
            }

            $rows[] = [$mappingVend->id, $mappingVend->vend_code, $merchantId, $ok ? 'paused' : 'FAILED (see log)'];
            $ok ? $sent++ : $failed++;
            $this->throttle();
        }

        $this->table(['mapping_vend', 'vend_code', 'merchant_id', 'result'], $rows);

        if ($apply) {
            $this->line("paused: {$sent}   failed: {$failed}   skipped: {$skipped}   already closed: {$alreadyClosed}   refused by Grab: {$refused}".($rateLimited ? '   (stopped at Grab rate limit)' : ''));

            if ($failed > 0) {
                $this->warn('Failures are logged as "Grab pauseStore failed" with the HTTP code.');
            }
        } else {
            $this->warn('Nothing was sent. Re-run with --apply.');
        }

        if ($skipped > 0) {
            $this->warn("{$skipped} row(s) skipped: their merchant still sells on another vend.");
        }

        return self::SUCCESS;
    }
}
