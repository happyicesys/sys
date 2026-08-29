<?php

namespace Tests\Feature;

use App\Models\VendTransaction;
use App\Services\Refund\RetainedCreditSettlementRecorder;
use App\Support\AutoRefundSource;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Retained-credit settlement accounting (2026-08-29, bench 2031).
 *
 * A card trade approved in under CARD_APPROVAL_SUSPECT_MS was served from
 * credit the VMC/reader banked after an earlier failed paid vend — no card
 * was presented, so it is settlement of that earlier sale, not fresh card
 * revenue. These tests pin the recorder: marking, source linking (most
 * recent prior failed paid trade, same machine, 7-day lookback, NOT slot or
 * amount bound), and the falsified-reversal correction.
 */
class RetainedCreditSettlementRecorderTest extends TestCase
{
    use RefreshDatabase;

    private const VEND_ID = 1357; // 2031, the bench rig the fault was proven on

    private function makeTransaction(array $overrides = []): VendTransaction
    {
        return VendTransaction::create(array_merge([
            'order_id' => 'RC'.uniqid(),
            'vend_id' => self::VEND_ID,
            'transaction_datetime' => Carbon::parse('2026-08-29 16:41:29'),
            'amount' => 20,
            'qty' => 1,
            'success_qty' => 0,
            'dispensed_qty' => 0,
            'vend_channel_id' => 0,
            'gst_vat_rate' => 0,
        ], $overrides));
    }

    private function record(VendTransaction $settlement): void
    {
        app(RetainedCreditSettlementRecorder::class)->record($settlement);
    }

    /** The bench pair: real-tap failed sale at 16:41, phantom at 16:42. */
    public function test_settlement_is_marked_and_linked_to_the_preceding_failed_sale()
    {
        $failedSale = $this->makeTransaction([
            'transaction_datetime' => Carbon::parse('2026-08-29 16:41:29'),
        ]);
        $settlement = $this->makeTransaction([
            'transaction_datetime' => Carbon::parse('2026-08-29 16:42:03'),
            'vend_transaction_json' => ['CSHL_ARMED_MS' => 3326],
        ]);

        $this->record($settlement);

        $settlement->refresh();
        $this->assertTrue($settlement->is_retained_credit_settlement);
        $this->assertSame($failedSale->id, $settlement->retained_credit_settles_txn_id);
    }

    /** $2.40 on slot 12 was served against a $0.20 slot-11 failure — the link must not filter on amount. */
    public function test_link_is_not_amount_bound()
    {
        $failedSale = $this->makeTransaction([
            'amount' => 20,
            'transaction_datetime' => Carbon::parse('2026-08-29 11:00:37'),
        ]);
        $settlement = $this->makeTransaction([
            'amount' => 240,
            'transaction_datetime' => Carbon::parse('2026-08-29 12:49:13'),
            'vend_transaction_json' => ['CSHL_ARMED_MS' => 1713],
        ]);

        $this->record($settlement);

        $this->assertSame($failedSale->id, $settlement->refresh()->retained_credit_settles_txn_id);
    }

    /** Fully dispensed prior sales banked nothing — they are never the source. */
    public function test_successful_prior_sales_are_not_linked()
    {
        $successfulSale = $this->makeTransaction([
            'success_qty' => 1,
            'dispensed_qty' => 1,
            'transaction_datetime' => Carbon::parse('2026-08-29 16:00:00'),
        ]);
        $settlement = $this->makeTransaction([
            'transaction_datetime' => Carbon::parse('2026-08-29 16:42:03'),
            'vend_transaction_json' => ['CSHL_ARMED_MS' => 1228],
        ]);

        $this->record($settlement);

        $settlement->refresh();
        $this->assertTrue($settlement->is_retained_credit_settlement);
        $this->assertNull($settlement->retained_credit_settles_txn_id, 'A successful sale banks no credit and must not be linked.');
    }

    /** Another machine's failures are not this machine's credit. */
    public function test_link_stays_on_the_same_machine()
    {
        $this->makeTransaction([
            'vend_id' => self::VEND_ID + 1,
            'transaction_datetime' => Carbon::parse('2026-08-29 16:41:00'),
        ]);
        $settlement = $this->makeTransaction([
            'transaction_datetime' => Carbon::parse('2026-08-29 16:42:03'),
            'vend_transaction_json' => ['CSHL_ARMED_MS' => 2176],
        ]);

        $this->record($settlement);

        $this->assertNull($settlement->refresh()->retained_credit_settles_txn_id);
    }

    /** Beyond the 7-day lookback a link would be a guess — null is more honest. */
    public function test_source_older_than_the_lookback_is_not_linked()
    {
        $this->makeTransaction([
            'transaction_datetime' => Carbon::parse('2026-08-20 10:00:00'),
        ]);
        $settlement = $this->makeTransaction([
            'transaction_datetime' => Carbon::parse('2026-08-29 16:42:03'),
            'vend_transaction_json' => ['CSHL_ARMED_MS' => 900],
        ]);

        $this->record($settlement);

        $this->assertNull($settlement->refresh()->retained_credit_settles_txn_id);
    }

    /**
     * The settlement proves the reader never reversed the source sale: a
     * TRADE-time card_terminal_reversal verdict is rewritten to
     * retained_credit_revend. is_refunded stays true — the customer was made
     * whole by goods, and no surface may pay them a second time on top.
     */
    public function test_falsified_reversal_is_rewritten_and_is_refunded_kept()
    {
        $failedSale = $this->makeTransaction([
            'transaction_datetime' => Carbon::parse('2026-08-29 16:41:29'),
            'is_refunded' => true,
        ]);
        $failedSale->forceFill(['auto_refund_source' => AutoRefundSource::CARD_TERMINAL_REVERSAL])->save();

        $settlement = $this->makeTransaction([
            'transaction_datetime' => Carbon::parse('2026-08-29 16:42:03'),
            'vend_transaction_json' => ['CSHL_ARMED_MS' => 3326],
        ]);

        $this->record($settlement);

        $failedSale->refresh();
        $this->assertSame(AutoRefundSource::RETAINED_CREDIT_REVEND, $failedSale->auto_refund_source);
        $this->assertTrue((bool) $failedSale->is_refunded, 'The do-not-pay-again guard must survive the rewrite.');
    }

    /** An Omise-refunded source (e.g. QR fail already refunded) is linked but its record is untouched. */
    public function test_non_reversal_sources_are_left_alone()
    {
        $qrFail = $this->makeTransaction([
            'transaction_datetime' => Carbon::parse('2026-08-29 11:00:37'),
            'is_refunded' => true,
        ]);
        $qrFail->forceFill(['auto_refund_source' => AutoRefundSource::OMISE_TRADE_FAIL])->save();

        $settlement = $this->makeTransaction([
            'transaction_datetime' => Carbon::parse('2026-08-29 12:49:13'),
            'vend_transaction_json' => ['CSHL_ARMED_MS' => 1713],
        ]);

        $this->record($settlement);

        $qrFail->refresh();
        $this->assertSame(AutoRefundSource::OMISE_TRADE_FAIL, $qrFail->auto_refund_source);
        $this->assertSame($qrFail->id, $settlement->refresh()->retained_credit_settles_txn_id);
    }

    /** A failed settlement re-banks the credit and becomes the next chain link. */
    public function test_chained_settlements_link_to_the_nearest_failed_trade()
    {
        $origin = $this->makeTransaction([
            'transaction_datetime' => Carbon::parse('2026-08-29 16:41:29'),
        ]);
        $firstPhantom = $this->makeTransaction([
            'transaction_datetime' => Carbon::parse('2026-08-29 16:42:03'),
            'vend_transaction_json' => ['CSHL_ARMED_MS' => 3326],
        ]);
        $this->record($firstPhantom);

        $secondPhantom = $this->makeTransaction([
            'transaction_datetime' => Carbon::parse('2026-08-29 17:16:22'),
            'vend_transaction_json' => ['CSHL_ARMED_MS' => 2176],
        ]);
        $this->record($secondPhantom);

        $this->assertSame($origin->id, $firstPhantom->refresh()->retained_credit_settles_txn_id);
        $this->assertSame($firstPhantom->id, $secondPhantom->refresh()->retained_credit_settles_txn_id);
    }
}
