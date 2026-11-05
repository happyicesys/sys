<?php

namespace Tests\Feature;

use App\Models\CardPaymentEvent;
use App\Models\RemoteCardTerminal;
use App\Services\CardTerminal\CardTerminalEventLog;
use App\Services\CardTerminal\Payrallel\PayrallelGateway;
use App\Support\CardNumberMask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Brian, 2026-11-05: of a card, mark1 keeps the first 2 and last 4 digits and nothing else. */
class CardNumberMaskTest extends TestCase
{
    use RefreshDatabase;

    /** A published test card, in the shape Payrallel's answers carry it. */
    private const TRACK2 = '4111111111111111D29122010000000000000F';

    private const KEPT = '41**********1111';

    private function capture(): array
    {
        return [
            'success' => true,
            'transaction' => [
                'transactionId' => 'C261104132649D4F',
                'parentTransactionId' => '261104122953D48',
                'status' => 'approved',
                'cardinfo' => ['maskedPan' => '411111******1111', 'rrn' => '611040062298'],
                'gatewayResponse' => [
                    'accountMasked' => '411111******1111',
                    'accountBin' => '411111',
                    'accountLast4' => '1111',
                    'extraData' => json_encode(['57' => self::TRACK2, '5F24' => '291231', '5F20' => 'A CUSTOMER', '9F02' => '000000000170', 'rrn' => '611040064922']),
                ],
            ],
        ];
    }

    private function assertNoCard(string $text): void
    {
        foreach (['4111111111111111', '411111', 'D2912', '291231', 'A CUSTOMER'] as $secret) {
            $this->assertStringNotContainsString($secret, $text);
        }
    }

    public function test_it_keeps_first_2_and_last_4_at_any_escape_depth(): void
    {
        $once = json_encode($this->capture());
        foreach ([$once, json_encode(['response' => $once]), json_encode(['a' => json_encode(['response' => $once])])] as $text) {
            $masked = CardNumberMask::text($text);

            $this->assertNoCard($masked);
            $this->assertStringContainsString(self::KEPT, $masked);
            $this->assertSame($masked, CardNumberMask::text($masked), 'masking twice changes nothing');
        }
    }

    public function test_it_leaves_ids_and_every_other_field_alone(): void
    {
        $masked = CardNumberMask::data($this->capture());

        $this->assertSame('C261104132649D4F', $masked['transaction']['transactionId']);
        $this->assertSame('261104122953D48', $masked['transaction']['parentTransactionId']);
        $this->assertSame('611040062298', $masked['transaction']['cardinfo']['rrn']);
        $this->assertSame('1111', $masked['transaction']['gatewayResponse']['accountLast4']);
        $this->assertSame('41', $masked['transaction']['gatewayResponse']['accountBin']);
        $extra = json_decode($masked['transaction']['gatewayResponse']['extraData'], true);
        $this->assertSame(self::KEPT, $extra['57']);
        $this->assertSame('000000000170', $extra['9F02']);
    }

    public function test_a_body_cut_inside_the_number_keeps_nothing_of_it(): void
    {
        $cut = '{"extraData":"{\"57\":\"41111111';

        $this->assertStringNotContainsString('41111111', CardNumberMask::text($cut));
        $this->assertStringNotContainsString('4111111111111111', CardNumberMask::text('tail 4111111111111111D2912201 tail'));
    }

    public function test_the_gateway_hands_back_and_logs_only_the_cut_number(): void
    {
        config(['payrallel.base_url' => 'https://pay.example/api', 'payrallel.authorization_format' => 'Bearer {token}']);
        Http::fake(['pay.example/*' => Http::response($this->capture())]);
        $terminal = new RemoteCardTerminal(['provider' => 'payrallel', 'access_token' => 'tok-1']);
        $terminal->id = 7;
        $gateway = $this->app->make(PayrallelGateway::class);

        $this->assertSame('C261104132649D4F', $gateway->capture($terminal, '50001-SF1', 170));
        $answer = $gateway->query($terminal, '50001-SF1');

        $this->assertNoCard(json_encode($answer->raw));
        $this->assertSame(self::KEPT, $answer->raw['transaction']['cardinfo']['maskedPan']);
        $this->assertSame(2, CardPaymentEvent::where('event', 'provider.http')->count());
        CardPaymentEvent::where('event', 'provider.http')->get()->each(fn ($e) => $this->assertNoCard(json_encode($e->detail)));
    }

    public function test_the_event_log_cuts_a_detail_from_any_caller(): void
    {
        app(CardTerminalEventLog::class)->record('device.request', ['response' => json_encode($this->capture()), 'nested' => $this->capture()]);

        $this->assertNoCard((string) DB::table('card_payment_events')->value('detail'));
    }

    public function test_the_command_counts_first_then_masks_stored_rows(): void
    {
        $id = DB::table('card_payment_events')->insertGetId([
            'event' => 'provider.http', 'level' => 'info', 'created_at' => now(),
            'detail' => json_encode(['response' => json_encode($this->capture())]),
        ]);
        $clean = DB::table('card_payment_events')->insertGetId([
            'event' => 'terminal.status', 'level' => 'info', 'created_at' => now(), 'detail' => json_encode(['state' => 'ready']),
        ]);
        $before = DB::table('card_payment_events')->where('id', $id)->value('detail');

        $this->artisan('card-payments:mask-card-numbers')->expectsOutputToContain('card_payment_events.detail: 1 row(s) to mask')->assertSuccessful();
        $this->assertSame($before, DB::table('card_payment_events')->where('id', $id)->value('detail'));

        $this->artisan('card-payments:mask-card-numbers --apply')->expectsOutputToContain('card_payment_events.detail: 1 row(s) masked')->assertSuccessful();
        $after = (string) DB::table('card_payment_events')->where('id', $id)->value('detail');
        $this->assertNoCard($after);
        $this->assertStringContainsString(self::KEPT, $after);
        $this->assertSame('ready', json_decode(DB::table('card_payment_events')->where('id', $clean)->value('detail'), true)['state']);

        $this->artisan('card-payments:mask-card-numbers')->expectsOutputToContain('card_payment_events.detail: 0 row(s) to mask')->assertSuccessful();
    }
}
