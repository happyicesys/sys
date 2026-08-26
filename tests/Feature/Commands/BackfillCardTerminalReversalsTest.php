<?php

namespace Tests\Feature\Commands;

use App\Models\PaymentMethod;
use App\Models\Vend;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Support\AutoRefundSource;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * refund:backfill-card-reversals must select BOTH frame shapes the live
 * predicate (VendTransactionService::isCardTerminalReversal) accepts:
 *
 *  - VMC-keypad frames (TXN_SRC 0): header SErr → error_code_normalized (a
 *    VIRTUAL column generated from $.SErr), ISOK = 0 as a veto.
 *  - Android soft-keyboard frames (TXN_SRC ≥ 1): NO header SErr, so
 *    error_code_normalized is NULL — the error lives in transf_info[0].SErr
 *    and ISOK is hard-coded 1. Regression: the original query's whereIn on
 *    error_code_normalized silently excluded every one of these rows.
 *
 * And it must NOT mark: Android err 7 (v301 retained-credit ambiguity) or
 * non-card rows whose cashless_mfg was stamped from telemetry.
 */
class BackfillCardTerminalReversalsTest extends TestCase
{
    use RefreshDatabase;

    private PaymentMethod $card;

    private PaymentMethod $cash;

    private Vend $vend;

    protected function setUp(): void
    {
        parent::setUp();

        config(['refund.card_reversal_terminals' => ['Nets', 'Nets-Auresys']]);
        // The VMC arm's index filter comes from the known channel errors.
        VendChannelError::create(['code' => 7, 'desc' => 'Dispense fail']);
        $this->card = PaymentMethod::create(['code' => 1, 'name' => 'Card']);
        $this->cash = PaymentMethod::create(['code' => 0, 'name' => 'Cash']);
        $this->vend = Vend::create(['code' => 9931, 'name' => 'Backfill rig']);
    }

    private function txn(string $orderId, array $attrs = [], array $json = []): VendTransaction
    {
        return VendTransaction::create(array_merge([
            'order_id' => $orderId,
            'vend_id' => $this->vend->id,
            'vend_channel_id' => 0,
            'transaction_datetime' => Carbon::parse('2026-08-10 12:00:00'),
            'amount' => 350,
            'gst_vat_rate' => 0,
            'is_multiple' => false,
            'success_qty' => 0,
            'is_refunded' => false,
            'cashless_mfg' => 'Nets',
            'payment_method_id' => $this->card->id,
            'vend_transaction_json' => $json,
        ], $attrs));
    }

    public function test_it_marks_both_frame_shapes_and_skips_the_ambiguous_or_non_card_ones(): void
    {
        // VMC-keypad reversal: header SErr 7 (→ error_code_normalized), ISOK 0.
        $vmc = $this->txn('VMC-ERR7', ['interface_type' => 0], ['SErr' => 7, 'ISOK' => 0]);

        // Android soft-keyboard reversal, SErr 4 — the shape the original
        // whereIn(error_code_normalized) query could never select.
        $androidReversal = $this->txn('AND-ERR4', ['interface_type' => 1],
            ['ISOK' => 1, 'TXN_SRC' => 1, 'transf_info' => [['SId' => 42, 'SErr' => 4]]]);

        // Android err 7: retained-credit ambiguity on APK <= v301 — never marked.
        $androidErr7 = $this->txn('AND-ERR7', ['interface_type' => 1],
            ['ISOK' => 1, 'TXN_SRC' => 1, 'transf_info' => [['SId' => 42, 'SErr' => 7]]]);

        // VMC frame with ISOK 1: the VMC says the trade was OK — vetoed.
        $vmcIsokOk = $this->txn('VMC-ISOK1', ['interface_type' => 0], ['SErr' => 7, 'ISOK' => 1]);

        // Cash row with a telemetry-stamped cashless_mfg (pre-2026-05 shape):
        // the card gate must exclude it.
        $cashRow = $this->txn('CASH-ERR4', ['interface_type' => 1, 'payment_method_id' => $this->cash->id],
            ['ISOK' => 1, 'TXN_SRC' => 1, 'transf_info' => [['SId' => 42, 'SErr' => 4]]]);

        // Dry-run: reports, writes nothing.
        $this->artisan('refund:backfill-card-reversals', ['--from' => '2026-08-01'])->assertSuccessful();
        $this->assertSame(0, VendTransaction::withoutGlobalScopes()->where('is_refunded', true)->count());

        // Apply: exactly the two real reversals are marked.
        $this->artisan('refund:backfill-card-reversals', ['--from' => '2026-08-01', '--apply' => true])
            ->assertSuccessful();

        foreach ([$vmc, $androidReversal] as $marked) {
            $marked->refresh();
            $this->assertTrue((bool) $marked->is_refunded, $marked->order_id);
            $this->assertSame(AutoRefundSource::CARD_TERMINAL_REVERSAL, $marked->auto_refund_source, $marked->order_id);
        }
        foreach ([$androidErr7, $vmcIsokOk, $cashRow] as $skipped) {
            $skipped->refresh();
            $this->assertFalse((bool) $skipped->is_refunded, $skipped->order_id);
        }
    }
}
