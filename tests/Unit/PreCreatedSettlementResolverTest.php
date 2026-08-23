<?php

namespace Tests\Unit;

use App\Jobs\RefundOmiseJob;
use App\Models\VendTransaction;
use App\Services\VendTransactionService;
use Tests\TestCase;

/**
 * Regression cover for the Omise single-purchase dispense-error auto-refund
 * (dead 2026-05-26 → restored 2026-08-23, see REFUND_INTEGRITY_AUDIT_2026-08-23.md).
 *
 * resolvePreCreatedSettlement() decides whether a gateway row pre-created at
 * paid-time becomes a sale (SETTLED) or is handed to the refund path (PENDING)
 * once the machine's TRADE arrives. The caller dispatches
 * HandleFailedVendTransaction → RefundOmiseJob for every PENDING result.
 */
class PreCreatedSettlementResolverTest extends TestCase
{
    private function trade(array $overrides = []): array
    {
        return array_merge([
            'isMultiple' => false,
            'errorCode' => 0,
            'success_qty' => 1,
            'dispensed_qty' => 1,
        ], $overrides);
    }

    private function resolve(int $current, bool $acked, array $input): int
    {
        return VendTransactionService::resolvePreCreatedSettlement($current, $acked, $input);
    }

    // ── single-item dispense failures → PENDING (refund path) ──────────────

    public function test_single_sensor_error_7_is_refunded_even_when_acked_and_already_settled(): void
    {
        // The exact prod shape of the 106 ticketed cases: CONFIRM ACK landed first
        // (row SETTLED, is_dispensed = 1), then the TRADE says err 7 with the motor
        // having run (dispensed_qty = 1) but no successful drop (success_qty = 0).
        $input = $this->trade(['errorCode' => 7, 'success_qty' => 0, 'dispensed_qty' => 1]);

        $this->assertSame(
            VendTransaction::SETTLEMENT_PENDING,
            $this->resolve(VendTransaction::SETTLEMENT_SETTLED, true, $input),
            'A single-item err-7 TRADE must hand the row to the refund path despite the pre-dispense ACK.'
        );
    }

    public function test_single_sensor_error_9_is_refunded(): void
    {
        $input = $this->trade(['errorCode' => 9, 'success_qty' => 0, 'dispensed_qty' => 1]);
        $this->assertSame(VendTransaction::SETTLEMENT_PENDING, $this->resolve(VendTransaction::SETTLEMENT_SETTLED, true, $input));
    }

    public function test_single_motor_error_4_with_nothing_dispensed_is_refunded_even_when_acked(): void
    {
        $input = $this->trade(['errorCode' => 4, 'success_qty' => 0, 'dispensed_qty' => 0]);
        $this->assertSame(VendTransaction::SETTLEMENT_PENDING, $this->resolve(VendTransaction::SETTLEMENT_PENDING, true, $input));
        $this->assertSame(VendTransaction::SETTLEMENT_PENDING, $this->resolve(VendTransaction::SETTLEMENT_SETTLED, true, $input));
    }

    public function test_single_string_error_code_is_normalised(): void
    {
        // SErr arrives as a string from some firmware.
        $input = $this->trade(['errorCode' => '7', 'success_qty' => 0, 'dispensed_qty' => 1]);
        $this->assertSame(VendTransaction::SETTLEMENT_PENDING, $this->resolve(VendTransaction::SETTLEMENT_SETTLED, true, $input));
    }

    // ── successful / ambiguous single-item TRADEs → SETTLED ────────────────

    public function test_single_success_code_0_is_a_sale(): void
    {
        $this->assertSame(VendTransaction::SETTLEMENT_SETTLED, $this->resolve(VendTransaction::SETTLEMENT_PENDING, false, $this->trade()));
    }

    public function test_single_success_code_6_is_a_sale(): void
    {
        $input = $this->trade(['errorCode' => 6]);
        $this->assertSame(VendTransaction::SETTLEMENT_SETTLED, $this->resolve(VendTransaction::SETTLEMENT_PENDING, false, $input));
    }

    public function test_single_success_qty_positive_is_never_a_failure_whatever_the_code(): void
    {
        // Defensive: if the machine says a drop succeeded, an odd error code must
        // not trigger a refund.
        $input = $this->trade(['errorCode' => 7, 'success_qty' => 1, 'dispensed_qty' => 1]);
        $this->assertSame(VendTransaction::SETTLEMENT_SETTLED, $this->resolve(VendTransaction::SETTLEMENT_PENDING, false, $input));
    }

    public function test_single_non_numeric_error_code_is_not_a_failure(): void
    {
        // Never refund on a guess: unknown code + ACK → sale; unknown code + no
        // ACK + nothing dispensed → PENDING by the ordinary rule only.
        $input = $this->trade(['errorCode' => 'ERR', 'success_qty' => 0, 'dispensed_qty' => 0]);
        $this->assertFalse(VendTransactionService::isSingleItemDispenseFailure($input));
        $this->assertSame(VendTransaction::SETTLEMENT_SETTLED, $this->resolve(VendTransaction::SETTLEMENT_PENDING, true, $input));
        $this->assertSame(VendTransaction::SETTLEMENT_PENDING, $this->resolve(VendTransaction::SETTLEMENT_PENDING, false, $input));
    }

    // ── multi-item purchases keep the existing rule (never auto-refunded) ──

    public function test_multi_item_with_all_items_failed_but_acked_stays_a_sale(): void
    {
        $input = $this->trade(['isMultiple' => true, 'errorCode' => 7, 'success_qty' => 0, 'dispensed_qty' => 0]);
        $this->assertFalse(VendTransactionService::isSingleItemDispenseFailure($input));
        $this->assertSame(VendTransaction::SETTLEMENT_SETTLED, $this->resolve(VendTransaction::SETTLEMENT_SETTLED, true, $input));
    }

    public function test_multi_item_partial_dispense_is_a_sale(): void
    {
        $input = $this->trade(['isMultiple' => true, 'errorCode' => 7, 'success_qty' => 1, 'dispensed_qty' => 2]);
        $this->assertSame(VendTransaction::SETTLEMENT_SETTLED, $this->resolve(VendTransaction::SETTLEMENT_PENDING, false, $input));
    }

    public function test_multi_item_nothing_dispensed_and_never_acked_is_pending(): void
    {
        $input = $this->trade(['isMultiple' => true, 'errorCode' => 7, 'success_qty' => 0, 'dispensed_qty' => 0]);
        $this->assertSame(VendTransaction::SETTLEMENT_PENDING, $this->resolve(VendTransaction::SETTLEMENT_PENDING, false, $input));
    }

    // ── refunded rows are never revived ─────────────────────────────────────

    public function test_refunded_row_stays_refunded_even_on_a_successful_late_trade(): void
    {
        $this->assertSame(VendTransaction::SETTLEMENT_REFUNDED, $this->resolve(VendTransaction::SETTLEMENT_REFUNDED, true, $this->trade()));
    }

    public function test_refunded_row_stays_refunded_on_a_failed_late_trade(): void
    {
        $input = $this->trade(['errorCode' => 7, 'success_qty' => 0]);
        $this->assertSame(VendTransaction::SETTLEMENT_REFUNDED, $this->resolve(VendTransaction::SETTLEMENT_REFUNDED, true, $input));
    }

    // ── the refund job retries instead of silently giving up ────────────────

    public function test_refund_omise_job_retries_transient_failures(): void
    {
        $job = new RefundOmiseJob('26082223323802502');
        $this->assertSame(3, $job->tries);
        $this->assertNotEmpty($job->backoff);
    }
}
