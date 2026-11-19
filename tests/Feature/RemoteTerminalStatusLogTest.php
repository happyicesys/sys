<?php

namespace Tests\Feature;

use App\Models\CardTerminal;
use App\Models\CardTerminalUnit;
use App\Models\RemoteCardTerminal;
use App\Models\RemoteCardTerminalStatusSpan as Span;
use App\Models\User;
use App\Models\Vend;
use App\Services\CardTerminal\CardTerminalException;
use App\Services\CardTerminal\Payrallel\PayrallelGateway;
use App\Services\CardTerminal\TerminalStatus;
use App\Services\CardTerminal\TerminalStatusLog;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Permission;
use Tests\Support\FakeRemoteCardTerminalGateway;
use Tests\TestCase;

/**
 * The T05 online / offline log (Brian, 2026-11-19): one row per stretch of the same answer, mark1's own minute
 * check, and the history on Setting/Edit's Remote card terminal panel.
 */
class RemoteTerminalStatusLogTest extends TestCase
{
    use RefreshDatabase;

    private FakeRemoteCardTerminalGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        config(['payrallel.base_url' => 'https://payrallel.test/api']);
        $this->gateway = new FakeRemoteCardTerminalGateway;
        $this->app->instance(PayrallelGateway::class, $this->gateway);
        Carbon::setTestNow('2026-11-19 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function vend(int $code): Vend
    {
        $vend = new Vend;
        $vend->forceFill([
            'code' => $code, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1,
            'operator_id' => 1, 'vend_model_id' => 1, 'private_key' => 'TESTKEY000000001',
        ])->save();

        return $vend->refresh();
    }

    private function unit(string $sn): CardTerminalUnit
    {
        $company = CardTerminal::query()->firstOrCreate(['name' => CardTerminal::NAME_PAYRALLEL]);

        return CardTerminalUnit::query()->create(['card_terminal_id' => $company->id, 'terminal_id' => $sn, 'access_token' => 'tok']);
    }

    private function terminal(Vend $vend, ?CardTerminalUnit $unit, bool $active = true): RemoteCardTerminal
    {
        return RemoteCardTerminal::create([
            'vend_id' => $vend->id, 'card_terminal_unit_id' => $unit?->id, 'provider' => 'payrallel',
            'label' => $unit?->terminal_id, 'access_token' => 'tok', 'is_active' => $active,
        ]);
    }

    private function answerAt(RemoteCardTerminal $t, string $time, ?bool $online, ?string $state = 'ready'): void
    {
        Carbon::setTestNow($time);
        $status = $online === null
            ? new TerminalStatus(false, null, ['error' => 'Payrallel unreachable (terminal/status): timed out'])
            : new TerminalStatus($online, $online ? $state : 'offline');
        app(TerminalStatusLog::class)->record($t, $status);
    }

    public function test_the_same_answer_grows_one_row_and_a_change_or_a_gap_starts_another(): void
    {
        $t = $this->terminal($this->vend(50002), $this->unit('N620W342857'));

        $this->answerAt($t, '2026-11-19 10:00:00', true);
        $this->answerAt($t, '2026-11-19 10:01:00', true, 'in_payment');
        $this->answerAt($t, '2026-11-19 10:02:00', true);
        $this->answerAt($t, '2026-11-19 10:03:00', false);
        $this->answerAt($t, '2026-11-19 10:04:00', false);
        $this->answerAt($t, '2026-11-19 10:05:00', null);
        $this->answerAt($t, '2026-11-19 10:06:00', true);
        // 20 minutes nobody asked, then the same answer: a new row, the gap left unchecked.
        $this->answerAt($t, '2026-11-19 10:26:00', true);

        $rows = Span::query()->orderBy('id')->get();
        $this->assertSame([true, false, null, true, true], $rows->pluck('online')->all());
        $this->assertSame([3, 2, 1, 1, 1], $rows->pluck('checks')->all());
        $this->assertSame('ready', $rows[0]->last_state); // the state of its last check
        $this->assertStringContainsString('timed out', $rows[2]->error);
        $this->assertSame('2026-11-19 10:02:00', $rows[0]->last_seen_at->format('Y-m-d H:i:s'));
    }

    public function test_the_summary_counts_checked_time_drops_and_the_longest_offline(): void
    {
        $t = $this->terminal($this->vend(50002), $this->unit('N620W342857'));
        foreach (range(0, 9) as $m) {
            $this->answerAt($t, sprintf('2026-11-19 11:%02d:00', $m), true);
        }
        $this->answerAt($t, '2026-11-19 11:10:00', false);
        $this->answerAt($t, '2026-11-19 11:12:00', false);
        $this->answerAt($t, '2026-11-19 11:14:00', true);
        $this->answerAt($t, '2026-11-19 11:17:00', true);
        $this->answerAt($t, '2026-11-19 11:20:00', false); // a second, shorter drop
        $this->answerAt($t, '2026-11-19 11:21:00', true);
        $this->answerAt($t, '2026-11-19 11:25:00', true);
        $this->answerAt($t, '2026-11-19 11:30:00', true);

        $now = Carbon::parse('2026-11-19 11:31:00');
        $sum = app(TerminalStatusLog::class)->summary($t, Carbon::parse('2026-11-19 11:00:00'), $now, $now);

        $this->assertSame(2, $sum['drops']);
        $this->assertSame(240, $sum['longest_offline_seconds']); // 11:10 → 11:14
        $this->assertSame(300, $sum['offline_seconds']);         // 4 min + 1 min
        $this->assertSame(1560, $sum['online_seconds']);
        $this->assertSame(0, $sum['unchecked_seconds']);
        $this->assertSame(83.9, $sum['uptime_pct']);
    }

    public function test_time_nobody_checked_is_not_counted_as_online_or_offline(): void
    {
        $t = $this->terminal($this->vend(50002), $this->unit('N620W342857'));
        $this->answerAt($t, '2026-11-19 11:00:00', true);
        $this->answerAt($t, '2026-11-19 11:01:00', true);
        $this->answerAt($t, '2026-11-19 11:40:00', true);

        $now = Carbon::parse('2026-11-19 11:41:00');
        $sum = app(TerminalStatusLog::class)->summary($t, Carbon::parse('2026-11-19 11:00:00'), $now, $now);

        $this->assertSame(120, $sum['online_seconds']);
        $this->assertSame(2340, $sum['unchecked_seconds']);
        $this->assertSame(100.0, $sum['uptime_pct']);

        $history = app(TerminalStatusLog::class)->history($t, 30, $now);
        $this->assertSame(2340, $history[1]['unchecked_after_seconds']);
        $this->assertTrue($history[0]['open']);
    }

    public function test_offline_again_after_a_gap_is_the_same_drop(): void
    {
        $t = $this->terminal($this->vend(50002), $this->unit('N620W342857'));
        $this->answerAt($t, '2026-11-19 11:00:00', true);
        $this->answerAt($t, '2026-11-19 11:01:00', false);
        $this->answerAt($t, '2026-11-19 11:20:00', false); // nobody asked for 19 min: still the one drop
        $this->answerAt($t, '2026-11-19 11:21:00', true);

        $now = Carbon::parse('2026-11-19 11:22:00');
        $sum = app(TerminalStatusLog::class)->summary($t, Carbon::parse('2026-11-19 11:00:00'), $now, $now);

        $this->assertSame(1, $sum['drops']);
        $this->assertSame(1140, $sum['unchecked_seconds']); // 11:01 → 11:20: the first offline answer covers no time
    }

    public function test_the_check_reads_only_bound_t05s(): void
    {
        $this->terminal($this->vend(50001), $this->unit('N620W342856'));
        $this->terminal($this->vend(50002), $this->unit('N620W342857'), active: false); // link switched off
        $this->terminal($this->vend(2009), null, active: false);

        $this->artisan('card-terminals:watch-status')->assertSuccessful();

        $this->assertCount(1, array_filter($this->gateway->calls, fn ($c) => $c[0] === 'status'));
        $this->assertSame(1, Span::query()->count());
        $this->assertTrue((bool) RemoteCardTerminal::query()->where('label', 'N620W342856')->value('last_online'));
    }

    public function test_a_terminal_answered_in_the_last_25_seconds_is_not_asked_again(): void
    {
        $t = $this->terminal($this->vend(50001), $this->unit('N620W342856'));
        $t->forceFill(['last_status_at' => now()->subSeconds(10)])->save();

        $this->artisan('card-terminals:watch-status')->assertSuccessful();
        $this->assertSame([], $this->gateway->calls);

        Carbon::setTestNow(now()->addSeconds(30));
        Cache::flush();
        $this->artisan('card-terminals:watch-status')->assertSuccessful();
        $this->assertCount(1, $this->gateway->calls);
    }

    public function test_an_answer_the_freezer_got_seconds_ago_costs_no_second_call(): void
    {
        $vend = $this->vend(50001);
        $this->terminal($vend, $this->unit('N620W342856'));

        app(\App\Services\CardTerminal\CardPaymentService::class)->terminalStatus($vend); // the freezer's poll
        $this->artisan('card-terminals:watch-status')->assertSuccessful();
        // mark1's own check reading the same terminal through the cache costs no call either.
        app(\App\Services\CardTerminal\CardPaymentService::class)->statusOf(RemoteCardTerminal::query()->sole());

        $this->assertCount(1, $this->gateway->calls);
        $this->assertSame(1, Span::query()->value('checks'));
    }

    public function test_payrallel_unreachable_is_logged_apart_from_offline(): void
    {
        $this->terminal($this->vend(50001), $this->unit('N620W342856'));
        $this->gateway->failStatus = CardTerminalException::notConfigured('Payrallel unreachable (terminal/status): timed out');

        $this->artisan('card-terminals:watch-status')->assertSuccessful();

        $span = Span::query()->sole();
        $this->assertNull($span->online);
        $this->assertSame('check_failed', $span->kind());
        $this->assertStringContainsString('unreachable', $span->error);
    }

    public function test_nothing_is_asked_where_payrallel_is_not_set_up(): void
    {
        config(['payrallel.base_url' => null]);
        $this->terminal($this->vend(50001), $this->unit('N620W342856'));

        $this->artisan('card-terminals:watch-status')->assertSuccessful();

        $this->assertSame([], $this->gateway->calls);
        $this->assertSame(0, Span::query()->count());
    }

    public function test_a_swapped_t05_starts_its_own_history_on_the_panel(): void
    {
        $vend = $this->vend(50002);
        $old = $this->unit('N622W113239');
        $t = $this->terminal($vend, $old);
        $this->answerAt($t, '2026-11-19 11:00:00', false);

        $new = $this->unit('N620W342857');
        $t->update(['card_terminal_unit_id' => $new->id, 'label' => $new->terminal_id]);
        $this->answerAt($t, '2026-11-19 11:01:00', true);

        $now = Carbon::parse('2026-11-19 11:02:00');
        $history = app(TerminalStatusLog::class)->history($t->refresh(), 30, $now);
        $this->assertCount(1, $history);
        $this->assertSame('online', $history[0]['kind']);
        $this->assertSame(2, Span::query()->count()); // the old T05's row is kept
    }

    public function test_the_panel_shows_the_history_and_check_works_with_the_link_switched_off(): void
    {
        $user = User::factory()->create(['operator_id' => 1]);
        $user->givePermissionTo(Permission::findOrCreate('read machine-settings', 'web'));
        $this->actingAs($user);
        $vend = $this->vend(50002);
        $t = $this->terminal($vend, $this->unit('N620W342857'), active: false);
        $this->answerAt($t, '2026-11-19 11:58:00', false);
        Carbon::setTestNow('2026-11-19 12:00:00');
        Cache::flush();

        $this->postJson("/vends/{$vend->id}/remote-card-terminal/check")
            ->assertOk()
            ->assertJsonPath('terminal.is_active', false)
            ->assertJsonPath('terminal.last_online', true)
            ->assertJsonPath('history.day.drops', 1)
            ->assertJsonPath('history.spans.0.kind', 'online')
            ->assertJsonPath('history.spans.0.open', true)
            ->assertJsonPath('history.spans.1.kind', 'offline');
    }

    public function test_the_dashboard_badge_reads_the_bound_t05_and_its_history_opens_under_the_dashboard_gate(): void
    {
        $this->actingAs(User::factory()->create(['operator_id' => 1])); // no machine-settings permission
        $vend = $this->vend(50002);
        $t = $this->terminal($vend, $this->unit('N620W342857'));
        $this->answerAt($t, '2026-11-19 11:59:30', true);
        Carbon::setTestNow('2026-11-19 12:00:00');

        $this->getJson("/vends/{$vend->id}/remote-card-terminal/history")
            ->assertOk()
            ->assertJsonPath('terminal.sn', 'N620W342857')
            ->assertJsonPath('history.spans.0.kind', 'online');
        $this->assertSame([], $this->gateway->calls); // reading the history asks Payrallel nothing
        $this->getJson("/vends/{$vend->id}/remote-card-terminal")->assertForbidden();

        $t->refresh()->forceFill(['last_online' => true, 'last_state' => 'ready', 'last_status_at' => '2026-11-19 11:59:30'])->save();
        $packed = \DB::table('vends')->where('id', $vend->id)->value(\DB::raw(RemoteCardTerminal::dashboardStatusSql()));
        $status = \App\Http\Resources\VendResource::t05Status($packed);
        $this->assertTrue($status['online']);
        $this->assertSame('ready', $status['state']);
        $this->assertSame(Carbon::parse('2026-11-19 11:59:30')->toIso8601String(), $status['checked_at']);

        $t->update(['is_active' => false]);
        $this->assertNull(\DB::table('vends')->where('id', $vend->id)->value(\DB::raw(RemoteCardTerminal::dashboardStatusSql())));
    }
}
