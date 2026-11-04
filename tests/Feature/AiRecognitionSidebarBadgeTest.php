<?php

namespace Tests\Feature;

use App\Models\FreezerControlCommand;
use App\Models\SmartFreezerRecognition;
use App\Models\User;
use App\Models\Vend;
use App\Services\NoteNotificationService;
use App\Services\SmartFreezer\RecognitionVerdict;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/** The number beside Transactions → AI Recognition: new AI issues and door faults since the last visit. */
class AiRecognitionSidebarBadgeTest extends TestCase
{
    use RefreshDatabase;

    private Vend $vend;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-04 12:00:00');
        Permission::findOrCreate('read ai-recognition', 'web');
        $this->vend = Vend::create(['code' => 50001, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function viewer(int $operatorId = 1): User
    {
        $user = User::factory()->create(['operator_id' => $operatorId]);
        $user->givePermissionTo('read ai-recognition');

        return $user;
    }

    private function recognition(?string $verdict, string $status = SmartFreezerRecognition::STATUS_COMPLETED, ?Vend $vend = null): SmartFreezerRecognition
    {
        return SmartFreezerRecognition::create([
            'vend_id' => ($vend ?? $this->vend)->id, 'trade_id' => 'SDK'.uniqid(), 'status' => $status, 'verdict' => $verdict,
        ]);
    }

    private function machineLine(string $line, string $op = 'error:BoxVmc', ?Vend $vend = null): void
    {
        FreezerControlCommand::forceCreate([
            'vend_id' => ($vend ?? $this->vend)->id, 'cmd_id' => substr(md5(uniqid('', true)), 0, 26), 'op' => $op, 'status' => 'error',
            'source' => FreezerControlCommand::SOURCE_EVENT, 'response_msg' => $line,
        ]);
    }

    private function seen(User $user): void
    {
        app(NoteNotificationService::class)->markViewed($user, NoteNotificationService::PAGE_AI_RECOGNITION);
    }

    private function badge(User $user): int
    {
        $this->actingAs($user);

        return app(NoteNotificationService::class)->badgeCounts($user)['/ai-recognition'];
    }

    public function test_every_verdict_but_a_tick_counts(): void
    {
        $user = $this->viewer();
        $this->seen($user);
        Carbon::setTestNow(now()->addMinute());

        $this->recognition(RecognitionVerdict::MATCH);
        $this->assertSame(0, $this->badge($user));

        foreach ([RecognitionVerdict::TOOK_MORE, RecognitionVerdict::TOOK_LESS, RecognitionVerdict::MIXED, RecognitionVerdict::UNRECOGNISED, RecognitionVerdict::INCOMPLETE] as $verdict) {
            $this->recognition($verdict);
        }
        $this->assertSame(5, $this->badge($user));
    }

    public function test_an_ai_that_could_not_answer_counts_but_a_session_still_waiting_for_its_verdict_does_not(): void
    {
        $user = $this->viewer();
        $this->seen($user);
        Carbon::setTestNow(now()->addMinute());

        $this->recognition(null, SmartFreezerRecognition::STATUS_FAILED);
        $this->recognition(null, SmartFreezerRecognition::STATUS_COMPLETED);
        $this->recognition(null, SmartFreezerRecognition::STATUS_SUBMITTED);

        $this->assertSame(1, $this->badge($user));
    }

    public function test_a_verdict_written_after_the_visit_counts_though_the_session_is_older(): void
    {
        $waiting = $this->recognition(null);
        Carbon::setTestNow(now()->addMinute());
        $user = $this->viewer();
        $this->seen($user);
        $this->assertSame(0, $this->badge($user));

        Carbon::setTestNow(now()->addMinute());
        $waiting->update(['verdict' => RecognitionVerdict::TOOK_MORE]);

        $this->assertSame(1, $this->badge($user));
    }

    public function test_door_faults_count_and_other_machine_lines_do_not(): void
    {
        $user = $this->viewer();
        $this->seen($user);
        Carbon::setTestNow(now()->addMinute());

        $this->machineLine('Door refused for SF-50001-1793784418-6: LOCK (lock timeout)');
        $this->machineLine('Door call never reached host for SF-50001-1793784418-7: THREW');
        $this->machineLine('Box call threw synchronously');
        $this->machineLine('Card port did not open', 'error:CardDetect');

        $this->assertSame(2, $this->badge($user));
    }

    public function test_opening_the_page_clears_it(): void
    {
        $user = $this->viewer();
        $this->seen($user);
        Carbon::setTestNow(now()->addMinute());
        $this->recognition(RecognitionVerdict::TOOK_LESS);
        Carbon::setTestNow(now()->addMinute());

        $this->actingAs($user)->get('/ai-recognition')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('noteBadges./ai-recognition', 0));

        Carbon::setTestNow(now()->addMinute());
        $this->recognition(RecognitionVerdict::MIXED);
        $this->assertSame(1, $this->badge($user));
    }

    public function test_another_page_shows_the_number(): void
    {
        $user = $this->viewer();
        $this->seen($user);
        Carbon::setTestNow(now()->addMinute());
        $this->recognition(RecognitionVerdict::TOOK_LESS);

        $user->givePermissionTo(Permission::findOrCreate('read refunds', 'web'));
        $this->actingAs($user)->get('/refunds')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('noteBadges./ai-recognition', 1));
    }

    public function test_a_link_to_one_session_and_a_table_reload_leave_it(): void
    {
        $user = $this->viewer();
        $this->seen($user);
        Carbon::setTestNow(now()->addMinute());
        $this->recognition(RecognitionVerdict::TOOK_LESS);
        Carbon::setTestNow(now()->addMinute());

        $this->actingAs($user)->get('/ai-recognition?search=SDK1')->assertOk();
        $this->assertSame(1, $this->badge($user));

        $this->actingAs($user)->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Partial-Data' => 'recognitions', 'X-Inertia-Partial-Component' => 'AiRecognition/Index'])
            ->get('/ai-recognition');
        $this->assertSame(1, $this->badge($user));
    }

    public function test_never_opened_counts_the_last_day_only(): void
    {
        $this->recognition(RecognitionVerdict::TOOK_LESS);
        $this->machineLine('Door refused for SF-50001-1793784418-6: LOCK (lock timeout)');
        Carbon::setTestNow(now()->addHours(25));
        $this->recognition(RecognitionVerdict::MIXED);

        $this->assertSame(1, $this->badge($this->viewer()));
    }

    public function test_it_needs_the_page_permission(): void
    {
        $user = User::factory()->create(['operator_id' => 1]);
        $this->recognition(RecognitionVerdict::TOOK_LESS);

        $this->assertSame(0, $this->badge($user));
    }

    public function test_an_operator_counts_its_own_machines_only(): void
    {
        $other = Vend::create(['code' => 50002, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 7]);
        $mine = $this->viewer(7);
        $this->seen($mine);
        $head = $this->viewer(1);
        $this->seen($head);
        Carbon::setTestNow(now()->addMinute());

        $this->recognition(RecognitionVerdict::TOOK_LESS);
        $this->machineLine('Door refused for SF-50001-1793784418-6: LOCK (lock timeout)');
        $this->recognition(RecognitionVerdict::MIXED, vend: $other);

        $this->assertSame(1, $this->badge($mine));
        $this->assertSame(3, $this->badge($head));
    }
}
