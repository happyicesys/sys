<?php

namespace Tests\Unit;

use App\Models\RefundTicket;
use App\Services\Refund\RefundMatchingService;
use App\Services\Refund\RefundValidationService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Refund path follows DispenseVerdict (Brian, 2026-09-08): codes 0 and 6 are
 * dispensed and 99 is "not found" — none of them is a genuine non-dispense a
 * ticket may be recommended PROCEED on. Only a real machine fault is.
 * Before this rule, isRealChannelError() treated any non-zero code (6 and 99
 * included) as real.
 */
class RefundChannelErrorRuleTest extends TestCase
{
    #[DataProvider('codes')]
    public function test_is_real_channel_error_follows_dispense_verdict(?string $code, bool $expected): void
    {
        $this->assertSame($expected, (new RefundMatchingService)->isRealChannelError($code), var_export($code, true));
    }

    public static function codes(): array
    {
        return [
            'null' => [null, false],
            'empty' => ['', false],
            '0' => ['0', false],
            '00' => ['00', false],
            '6 dispensed' => ['6', false],
            '99 not found' => ['99', false],
            '7 sensor' => ['7', true],
            '4 motor' => ['4', true],
            ' 9 padded' => [' 9 ', true],
            'junk' => ['abc', false],
        ];
    }

    public function test_only_a_machine_fault_is_recommended_proceed(): void
    {
        $validator = new RefundValidationService;

        $fault = $validator->validate([['had_channel_error' => true]]);
        $this->assertSame(RefundTicket::REC_PROCEED, $fault['items'][0]['item_recommendation']);

        $noFault = $validator->validate([['had_channel_error' => false]]);
        $this->assertSame(RefundTicket::REC_REVIEW, $noFault['items'][0]['item_recommendation']);
    }
}
