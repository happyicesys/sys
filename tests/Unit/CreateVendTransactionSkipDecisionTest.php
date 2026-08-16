<?php

namespace Tests\Unit;

use App\Jobs\Vend\CreateVendTransaction;
use App\Models\VendTransaction;
use Tests\TestCase;

/**
 * Regression cover for the dropped-TRADE gap (2026-08-10 20:02 → fix).
 *
 * The pre-check that makes CreateVendTransaction idempotent must not treat a gateway row
 * pre-created at paid-time as "already recorded" — that row exists precisely because it is WAITING
 * for the TRADE, and skipping the job leaves it with no machine ground truth and, when the
 * dispense ACK lost its race, stuck at PENDING (i.e. excluded from every sales figure).
 */
class CreateVendTransactionSkipDecisionTest extends TestCase
{
    public function test_no_existing_row_is_processed(): void
    {
        $this->assertFalse(CreateVendTransaction::isAlreadyApplied(null));
    }

    public function test_gateway_row_awaiting_its_trade_is_processed(): void
    {
        $preCreated = $this->transaction(isFound: false, paymentGatewayLogId: 517110);

        $this->assertFalse(
            CreateVendTransaction::isAlreadyApplied($preCreated),
            'A gateway row pre-created at paid-time must let its TRADE through.'
        );
    }

    public function test_gateway_row_that_already_received_its_trade_is_skipped(): void
    {
        $filled = $this->transaction(isFound: true, paymentGatewayLogId: 517110);

        $this->assertTrue(
            CreateVendTransaction::isAlreadyApplied($filled),
            'Re-delivery of an already-applied TRADE must still short-circuit.'
        );
    }

    public function test_non_gateway_trade_row_is_skipped(): void
    {
        $trade = $this->transaction(isFound: true, paymentGatewayLogId: null);

        $this->assertTrue(CreateVendTransaction::isAlreadyApplied($trade));
    }

    /**
     * Belt and braces: a row with neither the flag nor a PG log is not the gateway pre-create
     * shape, so it keeps the original skip behaviour rather than risking a second insert.
     */
    public function test_unlinked_unflagged_row_is_skipped(): void
    {
        $odd = $this->transaction(isFound: false, paymentGatewayLogId: null);

        $this->assertTrue(CreateVendTransaction::isAlreadyApplied($odd));
    }

    private function transaction(bool $isFound, ?int $paymentGatewayLogId): VendTransaction
    {
        return (new VendTransaction)->forceFill([
            'id' => 1,
            'is_found_in_transaction' => $isFound,
            'payment_gateway_log_id' => $paymentGatewayLogId,
        ]);
    }
}
