<?php

namespace App\Console\Commands;

use App\Models\DeliveryPlatformOrder;
use App\Models\DeliveryPlatforms\Grab;
use App\Models\DeliveryProductMappingVend;
use App\Support\Grab\GrabApiErrorRecorder;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Orders Grab has that never reached mark1 (Brian, 2026-10-30): a callback Grab could
 * not deliver, or one we refused, leaves no trace here. For each store that was on a
 * machine on the day, Grab's own order list is compared with ours; every order we do
 * not have is kept on Grab Errors (action order_missed, once per order).
 */
class GrabMissedOrders extends Command
{
    protected $signature = 'grab:missed-orders
                            {--date= : Day to check (Y-m-d, Singapore), default yesterday}
                            {--days=1 : Check this many days ending on --date}
                            {--store= : One merchantID only}';

    protected $description = 'List orders Grab has that never reached mark1, onto Grab Errors';

    private const MAX_PAGES = 20;

    public function handle(): int
    {
        $tz = 'Asia/Singapore';
        $last = $this->option('date') ? Carbon::parse($this->option('date'), $tz) : Carbon::yesterday($tz);
        $days = max(1, (int) $this->option('days'));

        $missed = 0;
        $checked = 0;
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = $last->copy()->subDays($i);
            [$dayChecked, $dayMissed, $stopped] = $this->checkDay($day);
            $checked += $dayChecked;
            $missed += $dayMissed;
            if ($stopped) {
                $this->warn('Stopped at Grab rate limit; run again later.');
                break;
            }
        }

        $this->info("Stores checked: {$checked}   orders missing in mark1: {$missed}");

        return self::SUCCESS;
    }

    /** @return array{int, int, bool} stores checked, orders missed, rate-limited */
    private function checkDay(Carbon $day): array
    {
        $start = $day->copy()->startOfDay()->setTimezone(config('app.timezone'));
        $end = $day->copy()->endOfDay()->setTimezone(config('app.timezone'));

        // Each store once per day, with the account its binding's template uses.
        $stores = DeliveryProductMappingVend::query()->withoutGlobalScopes()
            ->with(['deliveryPlatformRefNumber', 'deliveryProductMapping.deliveryPlatformOperator.externalOauthToken', 'deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform'])
            ->where('start_date', '<=', $end)
            ->where(fn ($query) => $query->whereNull('end_date')->orWhere('end_date', '>=', $start))
            ->when($this->option('store'), fn ($query, $store) => $query->where('platform_ref_id', $store))
            ->orderByDesc('start_date')
            ->get()
            ->unique(fn ($binding) => $binding->deliveryPlatformRefNumber?->ref_number ?? $binding->platform_ref_id);

        $checked = 0;
        $missed = 0;
        foreach ($stores as $binding) {
            $merchantId = $binding->deliveryPlatformRefNumber?->ref_number ?? $binding->platform_ref_id;
            $account = $binding->deliveryProductMapping?->deliveryPlatformOperator;
            if (! $merchantId || ! $account || $account->type !== 'production'
                || $account->deliveryPlatform?->slug !== 'grab' || ! $account->externalOauthToken?->access_token) {
                continue;
            }

            // Grab refused us this store in the last day (DCVIC's 11, paused and off since
            // 2025-12): asking for its orders only adds a 401 to Grab Errors each night.
            if (Cache::has(SyncDeliveryStorePause::REFUSED_CACHE_PREFIX.$merchantId)) {
                continue;
            }

            $grab = new Grab($account);
            $orders = [];
            for ($page = 1; $page <= self::MAX_PAGES; $page++) {
                try {
                    $response = $grab->listOrders($merchantId, $day->toDateString(), $page);
                } catch (\Throwable $e) {
                    $this->line("{$merchantId}: {$e->getMessage()}");

                    continue 2;
                }
                usleep((int) config('grab.store_api_spacing_ms', 300) * 1000);

                if (($response['code'] ?? null) === 429) {
                    return [$checked, $missed, true];
                }
                if (! ($response['success'] ?? false)) {
                    // Kept on Grab Errors by the HTTP listener already.
                    $this->line("{$merchantId}: Grab answered ".($response['code'] ?? '?'));

                    continue 2;
                }

                $orders = array_merge($orders, $response['data']['orders'] ?? []);
                if (! ($response['data']['more'] ?? false)) {
                    break;
                }
            }
            $checked++;

            $ids = array_values(array_filter(array_map(fn ($order) => $order['orderID'] ?? null, $orders)));
            if (! $ids) {
                continue;
            }
            $ours = DeliveryPlatformOrder::query()->withoutGlobalScopes()->whereIn('order_id', $ids)->pluck('order_id')->all();

            foreach ($orders as $order) {
                if (! empty($order['orderID']) && ! in_array($order['orderID'], $ours, true)) {
                    GrabApiErrorRecorder::missedOrder($merchantId, $order, $account->id);
                    $missed++;
                    $this->line("{$day->toDateString()} {$merchantId}: missing {$order['orderID']} ({$binding->vend_code})");
                }
            }
        }

        return [$checked, $missed, false];
    }
}
