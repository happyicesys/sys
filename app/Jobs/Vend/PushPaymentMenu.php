<?php

namespace App\Jobs\Vend;

use App\Models\Vend;
use App\Services\OtaChannelResolver;
use App\Services\VendJobService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tell vending machines to re-read their QR payment menu (PAYMENTMENU) after it changed (Brian, 2026-10-30).
 *
 * A board keeps the wallet list in memory and re-read it only on start, so an Operator → Edit → Machine Payment Menu
 * change waited for a restart (2003 kept showing ten wallets after six were hidden, 2026-10-29). Big-board app 321+
 * re-reads on this frame at once; older apps log it and carry on, so it goes only to machines that act on it — the
 * rest pick the change up at their next restart, and Operator → Edit says so per machine.
 *
 * One operator's choices → that operator's machines; a payment method / merchant / gateway edit (no operator) →
 * every machine. DEBOUNCED like PushMappingMenuSync: each Show / No Show click saves on its own, so a burst of clicks
 * becomes one push per machine, and the delay puts the fetch after the save's commit and cache clear. The frame
 * carries no data: the machine reads whatever is current when it fetches. It never touches a sale in progress — the
 * board only replaces its list in memory, on a background thread.
 */
class PushPaymentMenu implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const DEBOUNCE_SECONDS = 10;

    private const GUARD_TTL_SECONDS = 120;

    private const SMALL_BOARD_CHANNEL = 'vending_small';

    public function __construct(protected ?int $operatorId = null) {}

    public static function cacheKey(?int $operatorId): string
    {
        return 'payment-menu-push:'.($operatorId ?? 'all');
    }

    /** Queue a push for this operator's machines (null = every machine) unless one is already pending. */
    public static function schedule(?int $operatorId = null): void
    {
        if (! config('payment.menu_push', true)) {
            return;
        }

        if (Cache::add(self::cacheKey($operatorId), true, self::GUARD_TTL_SECONDS)) {
            self::dispatch($operatorId)
                ->delay(now()->addSeconds(self::DEBOUNCE_SECONDS))
                ->onQueue('default');
        }
    }

    /** A big-board vending machine on an app that re-reads its menu on PAYMENTMENU. */
    public static function supports(Vend $vend): bool
    {
        if ($vend->machine_type !== Vend::MACHINE_TYPE_VENDING_MACHINE) {
            return false;
        }
        $reported = $vend->reportedApkVersionDetail();
        if ($reported->deviceType !== null
            && app(OtaChannelResolver::class)->forDeviceType($reported->deviceType) === self::SMALL_BOARD_CHANNEL) {
            return false;
        }

        return $reported->code >= (int) config('payment.menu_push_min_vending_apk', 321);
    }

    public function handle(VendJobService $vendJobService): void
    {
        // Released first, so a change made while this job runs schedules the next push.
        Cache::forget(self::cacheKey($this->operatorId));

        // withoutGlobalScopes: a queue worker has no authenticated user.
        $vends = Vend::withoutGlobalScopes()
            ->when($this->operatorId !== null, fn ($q) => $q->where('operator_id', $this->operatorId))
            ->where('machine_type', Vend::MACHINE_TYPE_VENDING_MACHINE)
            ->where(fn ($q) => $q->where('is_active', true)->orWhere('is_testing', true))
            ->get();

        foreach ($vends as $vend) {
            if (! self::supports($vend)) {
                continue;
            }
            try {
                $vendJobService->nudgePaymentMenuToVend($vend);
                DB::table('vends')->where('id', $vend->id)->update(['payment_menu_pushed_at' => now()]);
            } catch (\Throwable $e) {
                // One unreachable machine must not stop the rest.
                Log::warning('PushPaymentMenu failed for vend', [
                    'operator_id' => $this->operatorId,
                    'vend_code' => $vend->code,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
