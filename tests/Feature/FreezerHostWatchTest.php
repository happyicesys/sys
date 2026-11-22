<?php

namespace Tests\Feature;

use App\Models\CardPaymentIntent;
use App\Models\Vend;
use App\Models\VendTransaction;
use App\Services\AlertEmailService;
use App\Services\SmartFreezer\FreezerHostWatch;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/** freezer:watch-host — 50001 on 2026-11-22 was online but cut off from its door/cameras for over an hour, unalarmed. */
class FreezerHostWatchTest extends TestCase
{
    use RefreshDatabase;

    private Vend $vend;

    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-22 17:30:00');
        Cache::flush();
        $this->vend = Vend::create(['code' => 50001, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1]);
        $alerts = Mockery::mock(AlertEmailService::class);
        $alerts->shouldReceive('sendVendOperationErrorNotificationMail')->andReturnUsing(function (Vend $v, string $type, string $label) {
            $this->sent[] = [$v->code, $type, $label];

            return 1;
        });
        $this->app->instance(AlertEmailService::class, $alerts);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function frame(string $type, array $payload, string $minutesAgo): void
    {
        DB::table('vend_data')->insert([
            'vend_code' => 50001, 'type' => $type, 'connection' => 'mqtt',
            'value' => json_encode(['f' => '1', 't' => '5', 'm' => '50001', 'g' => '20', 'p' => base64_encode(json_encode(['Type' => $type] + $payload))]),
            'created_at' => now()->subMinutes((int) $minutesAgo), 'updated_at' => now(),
        ]);
    }

    private function good(int $ago): void
    {
        $this->frame('FREEZERSTATUS', ['powerState' => 'connected', 'locks' => [['doorId' => 1, 'lockState' => 'locked']]], $ago);
    }

    private function empty(int $ago): void
    {
        $this->frame('FREEZERSTATUS', ['ts' => 1], $ago);
    }

    public function test_an_online_freezer_whose_status_lost_the_host_alerts_once_per_episode(): void
    {
        $this->good(60);
        $this->empty(45);
        $this->empty(30);
        $this->frame('VENDER', ['TEMP' => -250], 1);

        $this->assertSame([50001], app(FreezerHostWatch::class)->run()['dark']);
        $this->assertSame(FreezerHostWatch::TYPE_DARK, $this->sent[0][1]);
        $this->assertStringContainsString('since 16:45', $this->sent[0][2]);

        // Still dark five minutes later: no second email.
        $this->assertSame([], app(FreezerHostWatch::class)->run()['dark']);
        $this->assertCount(1, $this->sent);

        // Back, then dark again: a new episode alerts again.
        $this->good(2);
        app(FreezerHostWatch::class)->run();
        Carbon::setTestNow(now()->addMinutes(40));
        $this->empty(25);
        $this->empty(12);
        $this->frame('VENDER', ['TEMP' => -250], 1);
        $this->assertSame([50001], app(FreezerHostWatch::class)->run()['dark']);
        $this->assertCount(2, $this->sent);
    }

    public function test_a_fine_offline_or_just_restarted_freezer_does_not_alert(): void
    {
        $this->good(20);
        $this->frame('VENDER', ['TEMP' => -250], 1);
        $this->assertSame([], app(FreezerHostWatch::class)->run()['dark'], 'host data in the newest status');

        $this->empty(4);                                // one empty status right after a restart: under 10 min
        $this->assertSame([], app(FreezerHostWatch::class)->run()['dark']);

        DB::table('vend_data')->where('type', 'VENDER')->delete();
        $this->empty(30);
        $this->assertSame([], app(FreezerHostWatch::class)->run()['dark'], 'offline is the offline alert\'s job');
        $this->assertSame([], $this->sent);
    }

    private function hold(string $ref, int $closedMinutesAgo): CardPaymentIntent
    {
        $terminal = \App\Models\RemoteCardTerminal::firstOrCreate(['vend_id' => $this->vend->id], [
            'provider' => \App\Models\RemoteCardTerminal::PROVIDER_PAYRALLEL, 'access_token' => 'tok-50001', 'is_active' => true]);

        return CardPaymentIntent::forceCreate([
            'vend_id' => $this->vend->id, 'remote_card_terminal_id' => $terminal->id, 'provider' => 'payrallel',
            'reference' => 'SF'.uniqid(), 'custom_order_id' => '50001-'.uniqid(), 'mode' => 'preauth',
            'amount_cents' => 1030, 'state' => CardPaymentIntent::STATE_AWAITING_AI, 'session_ref' => $ref,
            'door_closed_at' => now()->subMinutes($closedMinutesAgo), 'created_at' => now()->subMinutes($closedMinutesAgo + 1),
        ]);
    }

    public function test_a_card_capture_with_no_sale_after_it_alerts_once(): void
    {
        $this->hold('SF-50001-1795334818-9', 10);
        $this->hold('SF-50001-1795334818-10', 1);       // too fresh: its TRADE may still be on the way
        $withSale = $this->hold('SF-50001-1795332409-8', 10);
        VendTransaction::forceCreate([
            'order_id' => 'O-'.uniqid(), 'vend_id' => $this->vend->id, 'vend_channel_id' => 0, 'amount' => 800,
            'transaction_datetime' => $withSale->created_at, 'gst_vat_rate' => 9, 'operator_id' => 1, 'is_multiple' => false,
            'vend_transaction_json' => ['SFREF' => 'SF-50001-1795332409-8'],
        ]);

        $this->assertSame(['SF-50001-1795334818-9'], app(FreezerHostWatch::class)->run()['no_trade']);
        $this->assertSame(FreezerHostWatch::TYPE_NO_TRADE, $this->sent[0][1]);
        $this->assertStringContainsString('$10.30', $this->sent[0][2]);
        $this->assertSame([], app(FreezerHostWatch::class)->run()['no_trade'], 'once per hold');
    }
}
