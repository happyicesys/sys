<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\Product;
use App\Models\User;
use App\Support\ProductOwners;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Products by operator group (Brian, 2026-10-27): a member of HIPL's group sees
 * its own products plus HIPL's — HIMD owns none and sells HIPL's — never another
 * member's; anyone outside the group sees only its own; HIPL sees everything.
 * Picking an operator on a product list lists the products it works with.
 */
class ProductOperatorGroupVisibilityTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, int> code => operator id */
    private array $ops = [];

    protected function setUp(): void
    {
        parent::setUp();

        // HIPL must be operator id 1: OperatorProductFilterScope keys "sees every
        // product" on id 1 (as in prod), and RefreshDatabase does not reset autoincrement.
        DB::table('operators')->insert([
            'id' => 1, 'code' => 'HIPL', 'name' => 'HI SG', 'is_active' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->ops['HIPL'] = 1;
        foreach (['HIMD', 'CB', 'XO', 'OUT'] as $code) {
            $this->ops[$code] = Operator::withoutGlobalScopes()->create(['code' => $code, 'name' => $code, 'is_active' => true])->id;
        }

        foreach (['HIPL', 'CB', 'XO', 'OUT'] as $code) {
            Product::withoutGlobalScopes()->create(['code' => "P-{$code}", 'name' => "{$code} bar", 'operator_id' => $this->ops[$code]]);
        }
    }

    public function test_an_operator_works_with_its_own_products_plus_hipls_inside_the_group(): void
    {
        $owners = app(ProductOwners::class);

        $this->assertSame([1], $owners->of(1));
        $this->assertSame([$this->ops['HIMD'], 1], $owners->of($this->ops['HIMD']));
        $this->assertSame([$this->ops['CB'], 1], $owners->of($this->ops['CB']));
        $this->assertSame([$this->ops['OUT']], $owners->of($this->ops['OUT']));
        $this->assertSame([999999], $owners->of(999999), 'an operator not on file keeps its own id');
        $this->assertEqualsCanonicalizing(
            [$this->ops['HIMD'], 1, $this->ops['XO']],
            $owners->forSelection([(string) $this->ops['HIMD'], $this->ops['XO']]),
        );
    }

    public function test_the_product_scope_shows_a_group_member_hipls_products_but_never_another_members(): void
    {
        $this->assertSame(['P-HIPL'], $this->visibleCodesAs('HIMD'));
        $this->assertSame(['P-CB', 'P-HIPL'], $this->visibleCodesAs('CB'));
        $this->assertSame(['P-HIPL', 'P-XO'], $this->visibleCodesAs('XO'));
        $this->assertSame(['P-OUT'], $this->visibleCodesAs('OUT'));
        $this->assertSame(['P-CB', 'P-HIPL', 'P-OUT', 'P-XO'], $this->visibleCodesAs('HIPL'));
    }

    public function test_warehouse_qty_lists_the_products_the_picked_operator_works_with(): void
    {
        foreach (['read product-availability', 'read product-availability-planning', 'read products'] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
        $user = User::factory()->create(['operator_id' => 1]);
        $user->givePermissionTo(['read product-availability', 'read product-availability-planning', 'read products']);
        config(['app.cms_url' => 'https://cms.test']);
        Http::fake(['cms.test/*' => Http::response([])]);

        $codes = fn (string $operator) => $this->actingAs($user)
            ->get('/products/availability?operators[]='.$this->ops[$operator])
            ->assertOk()
            ->viewData('page')['props']['products']['data'];

        $this->assertSame(['P-HIPL'], collect($codes('HIMD'))->pluck('code')->sort()->values()->all());
        $this->assertSame(['P-CB', 'P-HIPL'], collect($codes('CB'))->pluck('code')->sort()->values()->all());
        $this->assertSame(['P-OUT'], collect($codes('OUT'))->pluck('code')->sort()->values()->all());
    }

    public function test_products_index_operator_filter_lists_the_products_the_operator_works_with(): void
    {
        $this->actingAs(User::factory()->create(['operator_id' => 1]));

        $codes = Product::query()
            ->filterIndex(new Request(['operator_id' => $this->ops['HIMD']]))
            ->pluck('code')->sort()->values()->all();

        $this->assertSame(['P-HIPL'], $codes);
    }

    private function visibleCodesAs(string $operator): array
    {
        $this->actingAs(User::factory()->create(['operator_id' => $this->ops[$operator]]));

        return Product::query()->orderBy('code')->pluck('code')->all();
    }
}
