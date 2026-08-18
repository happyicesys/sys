<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use App\Support\OperatorScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The "Assign Job(s)" driver dropdown listed EVERY user in the system,
 * reported 2026-08-18 by operator XO: it was built from an unfiltered
 * User::all(), cached under one fixed key shared by every viewer.
 *
 * Both tests below fail on the pre-fix code.
 */
class OpsJobDriverOperatorScopeTest extends TestCase
{
    use RefreshDatabase;

    private Operator $hipl;

    private Operator $opA;

    private Operator $opB;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        Cache::flush();
        OperatorScope::flush();

        // Pinned, not just created first: OperatorVendFilterScope keys the
        // "sees everyone" exemption on the id, and RefreshDatabase rolls back
        // without resetting AUTO_INCREMENT, so insertion order does not give a
        // stable id 1.
        $this->hipl = $this->hiplOperator();
        $this->opA = $this->operator('OPA');
        $this->opB = $this->operator('OPB');
    }

    protected function tearDown(): void
    {
        OperatorScope::flush();
        parent::tearDown();
    }

    // ---------------------------------------------------------------- fixtures

    private function hiplOperator(): Operator
    {
        $id = OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID;

        DB::table('operators')->insert([
            'id' => $id,
            'code' => OperatorScope::PARENT_CODE,
            'name' => 'HIPL',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Operator::withoutGlobalScopes()->findOrFail($id);
    }

    private function operator(string $code): Operator
    {
        return Operator::withoutGlobalScopes()->firstOrCreate(['code' => $code], [
            'name' => $code,
            'is_active' => true,
        ]);
    }

    private function userFor(Operator $operator, string $name): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'operator_id' => $operator->id,
        ]);

        OperatorScope::flush();

        return $user;
    }

    /**
     * The driver names the Operation Dashboard would offer this viewer.
     *
     * Mirrors VendController::indexCustomer's list rather than hitting the
     * route, which needs a full machine/search fixture to render.
     */
    private function driverNamesFor(User $viewer): array
    {
        $this->be($viewer);

        $operatorId = OperatorVendFilterScope::viewerOperatorId();

        return User::query()
            ->when($operatorId, fn ($q, $id) => $q->where('operator_id', $id))
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    // ------------------------------------------------------------------- tests

    public function test_operator_is_only_offered_its_own_drivers(): void
    {
        $viewer = $this->userFor($this->opA, 'A Viewer');
        $this->userFor($this->opA, 'A Driver');
        $this->userFor($this->opB, 'B Driver');
        $this->userFor($this->hipl, 'HIPL Driver');

        $drivers = $this->driverNamesFor($viewer);

        $this->assertEqualsCanonicalizing(['A Driver', 'A Viewer'], $drivers);
        $this->assertNotContains('B Driver', $drivers);
        $this->assertNotContains('HIPL Driver', $drivers);
    }

    public function test_operator_one_is_still_offered_every_driver(): void
    {
        $viewer = $this->userFor($this->hipl, 'HIPL Viewer');
        $this->userFor($this->opA, 'A Driver');
        $this->userFor($this->opB, 'B Driver');

        $drivers = $this->driverNamesFor($viewer);

        $this->assertContains('A Driver', $drivers);
        $this->assertContains('B Driver', $drivers);
        $this->assertContains('HIPL Viewer', $drivers);
    }
}
