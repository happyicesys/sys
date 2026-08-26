<?php

namespace Tests\Unit;

use App\Services\VendTransactionService;
use App\Support\AutoRefundSource;
use Tests\TestCase;

/**
 * Card-terminal reversal detection (REFUND_INTEGRITY_AUDIT_2026-08-23.md, Phase 2).
 * A SINGLE-item card vend that fails is reversed by the MDB reader at the
 * machine; the only evidence mark1 receives is the TRADE footprint. The predicate
 * must fire on exactly that footprint, on known-reversing terminals only.
 */
class CardTerminalReversalPredicateTest extends TestCase
{
    private function trade(array $overrides = [], array $json = []): array
    {
        return array_merge([
            'paymentClassification' => 'card',
            'isMultiple' => false,
            'errorCode' => 7,
            'success_qty' => 0,
            'dispensed_qty' => 1,
            'interfaceType' => 0, // VMC-keypad frame unless a test says otherwise
            'originalJson' => array_merge(['ISOK' => 0, 'SErr' => 7, 'PAY_TYPE' => 1], $json),
        ], $overrides);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['refund.card_reversal_terminals' => ['Nets', 'Nets-Auresys']]);
    }

    public function test_nets_single_sensor_error_with_isok_0_is_a_reversal(): void
    {
        // Exact prod shape (e.g. txn 5985883, 2026-08-22): Nets, single, SErr 7, ISOK 0.
        $this->assertTrue(VendTransactionService::isCardTerminalReversal($this->trade(), 'Nets'));
        $this->assertTrue(VendTransactionService::isCardTerminalReversal($this->trade(['errorCode' => 4, 'dispensed_qty' => 0]), 'Nets-Auresys'));
    }

    public function test_multi_item_is_never_a_reversal(): void
    {
        $this->assertFalse(VendTransactionService::isCardTerminalReversal($this->trade(['isMultiple' => true]), 'Nets'));
    }

    public function test_successful_vend_is_not_a_reversal(): void
    {
        $this->assertFalse(VendTransactionService::isCardTerminalReversal($this->trade(['errorCode' => 0, 'success_qty' => 1], ['ISOK' => 1, 'SErr' => 0]), 'Nets'));
        $this->assertFalse(VendTransactionService::isCardTerminalReversal($this->trade(['errorCode' => 6, 'success_qty' => 1], ['ISOK' => 1]), 'Nets'));
    }

    public function test_vmc_frame_requires_isok_0(): void
    {
        // Defensive: a failure code but the VMC says the trade was OK → don't claim a reversal.
        $this->assertFalse(VendTransactionService::isCardTerminalReversal($this->trade([], ['ISOK' => 1]), 'Nets'));
        $this->assertFalse(VendTransactionService::isCardTerminalReversal($this->trade([], ['ISOK' => null]), 'Nets'));
        $json = $this->trade();
        unset($json['originalJson']['ISOK']);
        $this->assertFalse(VendTransactionService::isCardTerminalReversal($json, 'Nets'));
    }

    public function test_soft_keyboard_trade_is_a_reversal_despite_hardcoded_isok_1(): void
    {
        // Real prod shape: order 2026082415513017924 (Nets, vend 2401, 2026-08-24):
        // Android-built TRADE, TXN_SRC=1, ISOK hard-coded 1, SErr 4 in transf_info.
        // This is the very flow the reversal was photographed on — must match.
        $input = $this->trade(
            ['interfaceType' => 1, 'errorCode' => 4, 'dispensed_qty' => 0],
            ['ISOK' => 1, 'TXN_SRC' => 1, 'GET_TYPE' => 1, 'transf_info' => [['SId' => 42, 'SErr' => 4]]]
        );
        $this->assertTrue(VendTransactionService::isCardTerminalReversal($input, 'Nets'));

        // But a soft-keyboard SUCCESS never matches.
        $ok = $this->trade(['interfaceType' => 1, 'errorCode' => 0, 'success_qty' => 1], ['ISOK' => 1]);
        $this->assertFalse(VendTransactionService::isCardTerminalReversal($ok, 'Nets'));
    }

    public function test_soft_keyboard_err_7_needs_the_machine_on_v303(): void
    {
        // On APK <= v301, a soft-keyboard SErr 7 can be NETS *retaining* the
        // credit for a free re-vend (0x21 tradeId ownership bug, fixed in the
        // big-board v303) rather than reversing — and 96 of the 106 Android
        // card-single failures in Aug 2026 are err 7. Claiming is_refunded on
        // that ambiguity would auto-block genuine refund claims. The gate
        // widens machine-by-machine as the v303 OTA lands.
        $input = $this->trade(
            ['interfaceType' => 1, 'errorCode' => 7],
            ['ISOK' => 1, 'TXN_SRC' => 1, 'transf_info' => [['SId' => 42, 'SErr' => 7]]]
        );
        // No reported version / pre-fix versions (incl. small-board 13x): excluded.
        $this->assertFalse(VendTransactionService::isCardTerminalReversal($input, 'Nets'));
        $this->assertFalse(VendTransactionService::isCardTerminalReversal($input, 'Nets', 301));
        $this->assertFalse(VendTransactionService::isCardTerminalReversal($input, 'Nets', 134));

        // Machine reports the retained-credit fix (v303+): err 7 is a reversal.
        $this->assertTrue(VendTransactionService::isCardTerminalReversal($input, 'Nets', 303));

        // The VMC-keypad flow keeps err 7 regardless of version — ISOK = 0
        // corroborates the failure there (1:1 in prod, field-verified 2026-08-23).
        $this->assertTrue(VendTransactionService::isCardTerminalReversal($this->trade(), 'Nets'));
    }

    public function test_only_card_payments_qualify(): void
    {
        $this->assertFalse(VendTransactionService::isCardTerminalReversal($this->trade(['paymentClassification' => 'cash']), 'Nets'));
        $this->assertFalse(VendTransactionService::isCardTerminalReversal($this->trade(['paymentClassification' => 'cashless']), 'Nets'));
        $this->assertFalse(VendTransactionService::isCardTerminalReversal($this->trade(['paymentClassification' => null]), 'Nets'));
    }

    public function test_unverified_terminals_are_excluded_until_configured(): void
    {
        $this->assertFalse(VendTransactionService::isCardTerminalReversal($this->trade(), 'PAX'));
        $this->assertFalse(VendTransactionService::isCardTerminalReversal($this->trade(), 'MLS'));
        $this->assertFalse(VendTransactionService::isCardTerminalReversal($this->trade(), 'Nayax'));
        $this->assertFalse(VendTransactionService::isCardTerminalReversal($this->trade(), null));

        config(['refund.card_reversal_terminals' => ['Nets', 'PAX']]);
        $this->assertTrue(VendTransactionService::isCardTerminalReversal($this->trade(), 'PAX'));
    }

    public function test_source_labels_exist_for_every_constant(): void
    {
        foreach ([
            AutoRefundSource::OMISE_NO_DISPENSE, AutoRefundSource::OMISE_STALE_APPROVE,
            AutoRefundSource::OMISE_TRADE_FAIL, AutoRefundSource::OMISE_MANUAL,
            AutoRefundSource::CARD_TERMINAL_REVERSAL,
        ] as $src) {
            $this->assertNotEmpty(AutoRefundSource::label($src));
        }
        $this->assertNull(AutoRefundSource::label(null));
    }
}
