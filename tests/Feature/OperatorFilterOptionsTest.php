<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * The shared `operatorFilterOptions` Inertia prop feeds every page's
 * Operator filter (Components/OperatorFilter.vue). It must carry deactivated
 * operators too, flagged, so the All / Active toggle works client-side -
 * while still honouring the per-operator restriction for operator users.
 */
class OperatorFilterOptionsTest extends TestCase
{
    use RefreshDatabase;

    private function hipl(): Operator
    {
        $id = OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID;

        DB::table('operators')->insert([
            'id' => $id,
            'code' => 'HIPL',
            'name' => 'HIPL',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return Operator::withoutGlobalScopes()->findOrFail($id);
    }

    private function userFor(Operator $operator): User
    {
        $user = User::factory()->create(['operator_id' => $operator->id]);
        $user->givePermissionTo(Permission::findOrCreate('read operators', 'web'));

        return $user;
    }

    public function test_shared_prop_includes_inactive_operators_with_flag(): void
    {
        $hipl = $this->hipl();
        Operator::withoutGlobalScopes()->create(['code' => 'ACT', 'name' => 'Active Co', 'is_active' => true]);
        Operator::withoutGlobalScopes()->create(['code' => 'OLD', 'name' => 'Gone Co', 'is_active' => false]);

        $this->actingAs($this->userFor($hipl))
            ->get('/operators')
            ->assertInertia(fn (Assert $page) => $page
                ->where('operatorFilterOptions', function ($options) {
                    $byCode = collect($options)->keyBy('code');

                    return $byCode->has('OLD')
                        && $byCode['OLD']['is_active'] === false
                        && $byCode['ACT']['is_active'] === true
                        && $byCode['ACT']['full_name'] === 'ACT - Active Co';
                })
            );
    }

    public function test_operator_user_only_sees_their_own_operator(): void
    {
        $this->hipl();
        $mine = Operator::withoutGlobalScopes()->create(['code' => 'ACT', 'name' => 'Active Co', 'is_active' => true]);
        Operator::withoutGlobalScopes()->create(['code' => 'OLD', 'name' => 'Gone Co', 'is_active' => false]);

        $this->actingAs($this->userFor($mine))
            ->get('/operators')
            ->assertInertia(fn (Assert $page) => $page
                ->where('operatorFilterOptions', fn ($options) => collect($options)->pluck('code')->all() === ['ACT'])
            );
    }
}
