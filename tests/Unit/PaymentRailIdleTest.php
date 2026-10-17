<?php

namespace Tests\Unit;

use App\Support\PaymentRailIdle;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Operation Dashboard red frames (Brian, 2026-10-17): a payment rail the machine HAS — card terminal,
 * LCD monitor showing the QR, coin mech — with no sale on it for 48 h.
 */
class PaymentRailIdleTest extends TestCase
{
    private Carbon $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = Carbon::parse('2026-10-17 12:00:00');
    }

    private function row(array $overrides = []): array
    {
        return array_merge([
            'vend_is_active' => 1, 'is_testing' => 0,
            'card_terminal_id' => 3, 'lcd_monitor_id' => 1, 'parameter_json' => '{"CoinCnt": 5860}',
            'last_vend_transaction_at' => '2026-10-17 11:00:00',
            'last_card_vend_transaction_at' => '2026-10-17 10:00:00',
            'last_cashless_vend_transaction_at' => '2026-10-17 09:00:00',
            'last_cash_vend_transaction_at' => '2026-10-17 08:00:00',
        ], $overrides);
    }

    public function test_a_rail_with_a_recent_sale_is_not_flagged(): void
    {
        $this->assertSame([], PaymentRailIdle::forRow($this->row(), $this->now));
    }

    public function test_a_fitted_rail_silent_for_48h_is_flagged_with_when_it_last_sold(): void
    {
        $idle = PaymentRailIdle::forRow($this->row([
            'last_card_vend_transaction_at' => '2026-10-14 12:00:00',
            'last_cashless_vend_transaction_at' => null,
        ]), $this->now);

        $this->assertSame(['card', 'qr'], array_keys($idle));
        $this->assertSame(72.0, $idle['card']['hours_since']);
        $this->assertNull($idle['qr']['last_at'], 'never sold on that rail');
        $this->assertNull($idle['qr']['hours_since']);
        $this->assertFalse($idle['card']['machine_idle'], 'other payments still come in');
    }

    public function test_a_rail_the_machine_does_not_have_is_never_flagged(): void
    {
        $idle = PaymentRailIdle::forRow($this->row([
            'card_terminal_id' => null,
            'lcd_monitor_id' => 99, // the mapping's own "N/A"
            'parameter_json' => ['CoinCnt' => 0],
            'last_card_vend_transaction_at' => null,
            'last_cashless_vend_transaction_at' => null,
            'last_cash_vend_transaction_at' => null,
        ]), $this->now);

        $this->assertSame([], $idle);
    }

    public function test_a_machine_with_no_sale_at_all_is_marked_so(): void
    {
        $idle = PaymentRailIdle::forRow($this->row([
            'last_vend_transaction_at' => '2026-10-15 00:00:00',
            'last_card_vend_transaction_at' => '2026-10-15 00:00:00',
            'last_cashless_vend_transaction_at' => '2026-10-15 00:00:00',
            'last_cash_vend_transaction_at' => '2026-10-15 00:00:00',
        ]), $this->now);

        $this->assertSame(['card', 'qr', 'cash'], array_keys($idle));
        $this->assertTrue($idle['cash']['machine_idle']);
    }

    public function test_an_inactive_or_testing_machine_is_quiet_on_purpose(): void
    {
        $stale = ['last_card_vend_transaction_at' => '2026-10-01 00:00:00'];

        $this->assertSame([], PaymentRailIdle::forRow($this->row($stale + ['vend_is_active' => 0]), $this->now));
        $this->assertSame([], PaymentRailIdle::forRow($this->row($stale + ['is_testing' => 1]), $this->now));
    }
}
