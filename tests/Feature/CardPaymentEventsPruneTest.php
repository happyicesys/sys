<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Audit SF2-02: only old T05 status-poll lines with no order go; anything tied to a payment stays. */
class CardPaymentEventsPruneTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_status_polls_go_and_payment_rows_stay(): void
    {
        $row = fn (string $event, ?string $order, int $daysAgo) => [
            'event' => $event, 'custom_order_id' => $order,
            'created_at' => now()->subDays($daysAgo),
        ];
        DB::table('card_payment_events')->insert([
            $row('provider.http', null, 45),          // goes
            $row('provider.http', 'SF-1', 45),        // a payment's: stays
            $row('terminal.status', null, 45),        // another event: stays
            $row('provider.http', null, 3),           // recent: stays
        ]);

        $this->artisan('card-payments:prune-events')->assertSuccessful();

        $this->assertSame(3, DB::table('card_payment_events')->count());
        $this->assertSame(0, DB::table('card_payment_events')->where('event', 'provider.http')->whereNull('custom_order_id')
            ->where('created_at', '<', now()->subDays(30))->count());
    }
}
