<?php

namespace Tests\Unit;

use App\Support\AutoRefundSource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Who fired a refund, for the badge under the auto-refund tick: 'server' when
 * mark1 decided and called the gateway on its own, 'user' when a person did it
 * (by hand here, or at the gateway / by dispute).
 *
 * Null for the card-terminal sources on purpose — there the useful distinction
 * is what the settlement report SHOWED, which those surfaces badge separately,
 * and calling a terminal's own reversal "server" would credit us with money we
 * did not return (Brian, 2026-09-09).
 */
class AutoRefundTriggerTest extends TestCase
{
    public static function sources(): array
    {
        return [
            'machine reported a dispense failure' => [AutoRefundSource::OMISE_TRADE_FAIL, AutoRefundSource::TRIGGER_SERVER],
            'no dispense ack within 10 min' => [AutoRefundSource::OMISE_NO_DISPENSE, AutoRefundSource::TRIGGER_SERVER],
            'paid after the QR expired' => [AutoRefundSource::OMISE_STALE_APPROVE, AutoRefundSource::TRIGGER_SERVER],
            'staff ran the refund command' => [AutoRefundSource::OMISE_MANUAL, AutoRefundSource::TRIGGER_USER],
            'refunded on the Omise dashboard / dispute' => [AutoRefundSource::OMISE_EXTERNAL, AutoRefundSource::TRIGGER_USER],
            'refunded at Midtrans' => [AutoRefundSource::MIDTRANS_EXTERNAL, AutoRefundSource::TRIGGER_USER],
            'NETS reversal line' => [AutoRefundSource::SETTLEMENT_REPORT_REVERSAL, null],
            'NETS never captured it' => [AutoRefundSource::SETTLEMENT_REPORT_NOT_CAPTURED, null],
            'legacy TRADE-time inference' => [AutoRefundSource::CARD_TERMINAL_REVERSAL, null],
            'made whole by goods' => [AutoRefundSource::RETAINED_CREDIT_REVEND, null],
            'not auto-refunded' => [null, null],
            'unknown source' => ['something_new', null],
        ];
    }

    #[DataProvider('sources')]
    public function test_every_source_reports_who_fired_it(?string $source, ?string $expected): void
    {
        $this->assertSame($expected, AutoRefundSource::trigger($source));
    }

    public function test_every_gateway_source_is_classified(): void
    {
        // A new gateway source must be added to the map, not silently fall through
        // to "no badge" — these are the ones the tick alone cannot explain.
        foreach (array_keys(AutoRefundSource::LABELS) as $source) {
            if (! str_starts_with($source, 'omise_') && ! str_starts_with($source, 'midtrans_')) {
                continue;
            }
            $this->assertNotNull(AutoRefundSource::trigger($source), "{$source} has no trigger");
        }
    }
}
