<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\OpsJob;
use App\Models\User;
use Database\Seeders\RolePermissionSyncSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Job codes are unique across operators, but each operator counts from its own highest code.
 * Reported on IDN 2026-11-05: the old search gave up after 50 taken numbers, so every operator but
 * the big one got "Unable to generate a unique job code" on Create New Job and Assign Job(s).
 */
class OpsJobCodeTest extends TestCase
{
    use RefreshDatabase;

    private Operator $big;

    private Operator $small;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSyncSeeder::class);

        $this->big = Operator::withoutGlobalScopes()->firstOrCreate(['code' => 'BIG'], ['name' => 'BIG', 'is_active' => true]);
        $this->small = Operator::withoutGlobalScopes()->firstOrCreate(['code' => 'SMALL'], ['name' => 'SMALL', 'is_active' => true]);

        $this->user = User::factory()->create(['operator_id' => $this->small->id]);
        $this->user->assignRole('operator_admin');
    }

    private function job(int $code, Operator $operator): void
    {
        DB::table('ops_jobs')->insert([
            'code' => $code,
            'date' => '2026-11-01',
            'operator_id' => $operator->id,
            'created_at' => now(),
        ]);
    }

    /** The IDN picture: the big operator holds far more than 50 numbers past the small one's. */
    private function bigOperatorHolds(int $from, int $to): void
    {
        foreach (range($from, $to) as $code) {
            $this->job($code, $this->big);
        }
    }

    private function createJob()
    {
        return $this->actingAs($this->user)->post('/ops-jobs/store', [
            'date' => '2026-11-05',
            'delivered_by' => $this->user->id,
        ]);
    }

    public function test_create_job_past_more_than_fifty_taken_codes_takes_the_next_after_the_highest(): void
    {
        $this->job(10007, $this->small);
        $this->bigOperatorHolds(10008, 10080);

        $this->createJob()->assertSessionHasNoErrors()->assertRedirect(route('ops-jobs'));

        $job = OpsJob::where('operator_id', $this->small->id)->orderByDesc('id')->first();
        $this->assertSame(10081, (int) $job->code);
    }

    public function test_an_operator_with_no_jobs_can_create_its_first(): void
    {
        $this->bigOperatorHolds(10001, 10080);

        $this->createJob()->assertSessionHasNoErrors();

        $this->assertSame(10081, (int) OpsJob::where('operator_id', $this->small->id)->value('code'));
    }

    public function test_assign_jobs_uses_the_same_rule(): void
    {
        $this->bigOperatorHolds(10001, 10080);

        $this->actingAs($this->user)->post('/ops-jobs/assign', [
            'vends_id' => [],
            'driver_id' => $this->user->id,
            'date' => '2026-11-05',
        ])->assertSessionHasNoErrors();

        $this->assertSame(10081, (int) OpsJob::where('operator_id', $this->small->id)->value('code'));
    }

    public function test_a_free_next_number_is_still_the_operators_own(): void
    {
        $this->job(10500, $this->big);
        $this->job(10007, $this->small);

        $this->createJob()->assertSessionHasNoErrors();

        $this->assertTrue(OpsJob::where('operator_id', $this->small->id)->where('code', 10008)->exists());
    }

    public function test_the_next_job_after_a_jump_keeps_counting_from_there(): void
    {
        $this->job(10007, $this->small);
        $this->bigOperatorHolds(10008, 10080);

        $this->createJob();
        // A second job for another date: its own highest is now 10081, so 10082.
        $this->actingAs($this->user)->post('/ops-jobs/store', [
            'date' => '2026-11-06',
            'delivered_by' => $this->user->id,
        ])->assertSessionHasNoErrors();

        $this->assertSame(10082, (int) OpsJob::where('operator_id', $this->small->id)->max('code'));
    }
}
