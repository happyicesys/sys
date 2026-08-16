<?php

namespace App\Jobs\Vend;

use App\Models\Vend;
use App\Models\VendTransaction;
use App\Services\VendTransactionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class CreateVendTransaction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $input;

    protected $vend;

    protected $isCurrentTime;

    public function __construct($input, Vend $vend, $isCurrentTime = true)
    {
        $this->input = $input;
        $this->vend = $vend;
        $this->isCurrentTime = $isCurrentTime;
    }

    /**
     * Execute the job.
     *
     * Idempotent on `(order_id, vend_id)` — the pair is UNIQUE in the schema
     * (`uniq_order_id_vend_id`), which is what physically prevents a double charge.
     *
     * A device draining an offline sale outbox re-sends any sale it delivered but never got an ack
     * for, so re-delivery is normal operation, not an error. Without the guards below the insert
     * violates the unique index, the job burns its 3 attempts into `failed_jobs`, the device never
     * receives an ack, and it retries the same sale forever.
     *
     * @return void
     */
    public function handle(VendTransactionService $vendTransactionService)
    {
        // Cheap indexed pre-check (covered by uniq_order_id_vend_id): skip before entering the
        // service at all, so a re-delivery cannot trigger any of its side effects a second time.
        if ($this->alreadyRecorded()) {
            Log::info('CreateVendTransaction: order already recorded, skipping re-delivery', [
                'vend_id' => $this->vend->id,
                'order_id' => $this->orderId(),
            ]);

            return;
        }

        try {
            $vendTransactionService->create($this->vend, $this->input, $this->isCurrentTime);
        } catch (QueryException $e) {
            // Backstop for the race between the pre-check and the insert (two workers draining the
            // same outbox). Anything that is not a duplicate-key violation still fails loudly.
            if (! $this->isDuplicateEntry($e)) {
                throw $e;
            }

            Log::info('CreateVendTransaction: duplicate order ignored (idempotent re-delivery)', [
                'vend_id' => $this->vend->id,
                'order_id' => $this->orderId(),
            ]);
        }
    }

    /**
     * Best-effort: matches the raw device order id AND the TXN_SRC-50 prefixed form the service
     * stores (`Carbon 'y' + first char of 'm'` + ORDRID — see VendTransactionService::create()).
     * The prefix is computed with the CURRENT date, so a re-delivery that crosses a month/year
     * boundary can miss here — the QueryException backstop above still catches that case.
     */
    private function alreadyRecorded(): bool
    {
        $orderId = $this->orderId();

        if ($orderId === null || $orderId === '') {
            return false;
        }

        $prefixed = Carbon::now()->format('y').(Carbon::now()->format('m'))[0].$orderId;

        $existing = VendTransaction::withoutGlobalScopes()
            ->where('vend_id', $this->vend->id)
            ->whereIn('order_id', [$orderId, $prefixed])
            ->first(['id', 'is_found_in_transaction', 'payment_gateway_log_id']);

        return self::isAlreadyApplied($existing);
    }

    /**
     * Does an existing row mean this TRADE has already been applied?
     *
     * The row's mere existence is NOT the test. Under unified transactions
     * GatewayVendTransactionService pre-creates a row at gateway paid-time on the same
     * `(order_id, vend_id)` — `is_found_in_transaction = false`, linked to a PG log — and that row
     * is waiting for exactly this TRADE to fill in the machine's ground truth
     * (VendTransactionService::applyTradeToPreCreatedRow()). Treating it as "already recorded"
     * silently drops every QR sale's TRADE, which is what happened between 2026-08-10 20:02 and
     * this fix: ~4,600 gateway rows kept `is_found_in_transaction = false`, empty
     * `vend_transaction_json`, `success_qty`/`dispensed_qty` = 0, and 199 stayed PENDING (excluded
     * from all sales aggregation). See `repair:gateway-trade-gap` for the historical repair.
     *
     * Everything else still short-circuits, so the anti-double-charge guarantee is unchanged:
     * once the TRADE has landed the flag is true and re-deliveries skip as before.
     */
    public static function isAlreadyApplied(?VendTransaction $existing): bool
    {
        if (! $existing) {
            return false;
        }

        $awaitingTrade = ! $existing->is_found_in_transaction && $existing->payment_gateway_log_id;

        return ! $awaitingTrade;
    }

    /**
     * The job receives the DEVICE-shaped payload (`ORDRID`), not the service-processed one
     * (`orderID` only exists after VendTransactionService::processInput()). Accept both so the
     * pre-check works for every dispatch shape.
     */
    private function orderId(): ?string
    {
        if (! is_array($this->input)) {
            return null;
        }

        $orderId = $this->input['ORDRID'] ?? $this->input['orderID'] ?? null;

        return is_scalar($orderId) ? (string) $orderId : null;
    }

    /**
     * MySQL 1062 (ER_DUP_ENTRY) / 1586 (ER_DUP_ENTRY_WITH_KEY_NAME) ONLY. Deliberately NOT
     * SQLSTATE 23000: that class also covers FK (1452) and NOT NULL (1048) violations, which are
     * genuine failures that must throw and retry — swallowing them here would silently drop a sale.
     */
    private function isDuplicateEntry(QueryException $e): bool
    {
        return in_array($e->errorInfo[1] ?? null, [1062, 1586], true);
    }
}
