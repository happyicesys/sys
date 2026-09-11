<?php

namespace Tests\Feature;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Operator;
use App\Models\User;
use App\Support\OperatorScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

/**
 * XO and MSW joined HIPL's default Operator filter on 2026-09-11: their
 * machines take payment on our Omise account and our NETS terminals, so HIPL
 * ops staff run them with our own. The list is OperatorScope::DEFAULT_FILTER_CODES
 * and every page and controller reads it from there.
 */
class OperatorDefaultFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        OperatorScope::flush();
    }

    protected function tearDown(): void
    {
        OperatorScope::flush();
        parent::tearDown();
    }

    public function test_hipl_default_filter_includes_xo_and_msw_but_not_cb_or_a_deactivated_sibling(): void
    {
        $expected = collect(['HIPL', 'HIMD', 'HIESG', 'UL-ST', 'XO', 'MSW'])
            ->mapWithKeys(fn ($code) => [$code => (int) $this->operator($code)->id]);
        $cb = $this->operator('CB');
        $lea = $this->operator('LEA', false);

        $default = OperatorScope::defaultFilterIds();

        foreach ($expected as $code => $id) {
            $this->assertContains($id, $default, "{$code} is missing from the default Operator filter");
        }
        $this->assertNotContains((int) $cb->id, $default);
        $this->assertNotContains((int) $lea->id, $default);
    }

    public function test_hipl_ceiling_includes_xo_and_msw_while_their_own_staff_stay_pinned(): void
    {
        $hipl = $this->operator('HIPL');
        $xo = $this->operator('XO');
        $msw = $this->operator('MSW');

        $hiplCeiling = OperatorScope::forUser(User::factory()->create(['operator_id' => $hipl->id]));

        $this->assertContains((int) $xo->id, $hiplCeiling);
        $this->assertContains((int) $msw->id, $hiplCeiling);

        // XO is an outside leasing customer with its own logins: joining HIPL's
        // group must not widen what XO's staff can see.
        $this->assertSame([(int) $xo->id], OperatorScope::forUser(User::factory()->create(['operator_id' => $xo->id])));
        $this->assertSame([(int) $msw->id], OperatorScope::forUser(User::factory()->create(['operator_id' => $msw->id])));
    }

    public function test_the_default_codes_reach_every_inertia_page(): void
    {
        $shared = app(HandleInertiaRequests::class)->share(Request::create('/'));

        $this->assertSame(OperatorScope::DEFAULT_FILTER_CODES, $shared['defaultOperatorCodes']);
    }

    private function operator(string $code, bool $isActive = true): Operator
    {
        return Operator::withoutGlobalScopes()->firstOrCreate(['code' => $code], [
            'name' => $code,
            'is_active' => $isActive,
        ]);
    }
}
