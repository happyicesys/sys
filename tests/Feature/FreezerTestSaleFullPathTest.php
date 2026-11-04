<?php

namespace Tests\Feature;

use App\Jobs\RemoveOddTransactions;
use App\Models\Operator;
use App\Models\SmartFreezerRecognition;
use App\Models\User;
use App\Models\VendTransaction;
use App\Support\FreezerTestSale;
use App\Support\OperatorScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * A freezer's Testing Mode sale goes the whole way like a normal purchase (Brian, 2026-11-05): it
 * outlives the nightly odd-amount sweep and shows on Sales Transactions, AI Recognition and the AI
 * Dashboard, with a TEST badge.
 */
class FreezerTestSaleFullPathTest extends TestCase
{
    use RefreshDatabase;

    private Operator $operator;

    private int $vendId;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('DELETE_ODD_TRANSACTIONS=true');
        $_ENV['DELETE_ODD_TRANSACTIONS'] = 'true';
        $_SERVER['DELETE_ODD_TRANSACTIONS'] = 'true';

        $this->operator = Operator::withoutGlobalScopes()->create(['code' => 'TSTOP', 'name' => 'Test Operator', 'is_active' => true]);
        $customerId = DB::table('customers')->insertGetId([
            'name' => 'Freezer site', 'profile_id' => 1, 'status_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->vendId = DB::table('vends')->insertGetId([
            'code' => 50002, 'name' => 'Freezer 50002', 'operator_id' => $this->operator->id, 'customer_id' => $customerId,
            'machine_type' => 'smart_freezer', 'is_testing' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        putenv('DELETE_ODD_TRANSACTIONS');
        unset($_ENV['DELETE_ODD_TRANSACTIONS'], $_SERVER['DELETE_ODD_TRANSACTIONS']);
        parent::tearDown();
    }

    private function sale(string $orderId, int $cents, bool $test, ?int $operatorId = null): VendTransaction
    {
        return VendTransaction::create([
            'order_id' => $orderId, 'vend_id' => $this->vendId, 'operator_id' => $operatorId ?? $this->operator->id,
            'transaction_datetime' => now(), 'created_at' => now(), 'amount' => $cents, 'qty' => 1, 'vend_channel_code' => 42, 'vend_channel_id' => 0,
            'gst_vat_rate' => 0, 'interface_type' => 0, 'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
            'vend_transaction_json' => ['Type' => 'TRADE', 'SFREF' => 'SF-50002-'.$orderId, 'TXN_SRC' => 0] + ($test ? ['TEST' => 1] : []),
        ]);
    }

    private function recognitionOf(VendTransaction $sale): SmartFreezerRecognition
    {
        return SmartFreezerRecognition::create([
            'vend_id' => $this->vendId, 'trade_id' => 'SDK'.$sale->order_id, 'session_ref' => 'SF-50002-'.$sale->order_id,
            'vend_transaction_id' => $sale->id, 'status' => SmartFreezerRecognition::STATUS_COMPLETED, 'order_status' => 0,
            'verdict' => 'match', 'verdict_lines' => [['product_id' => 955, 'code' => '111', 'paid' => 1, 'taken' => 1, 'delta' => 0]],
        ]);
    }

    private function sweep(): void
    {
        (new RemoveOddTransactions(today()->toDateString(), today()->toDateString()))->handle();
    }

    private function viewer(string $permission): User
    {
        Permission::findOrCreate($permission, 'web');
        $user = User::factory()->create(['operator_id' => $this->operator->id]);
        $user->givePermissionTo($permission);
        OperatorScope::flush();

        return $user;
    }

    public function test_the_sweep_keeps_a_test_sale_at_any_amount_and_still_takes_the_rest(): void
    {
        $oneItem = $this->sale('TEST-ONE', FreezerTestSale::UNIT_PRICE_CENTS, true);
        $released = $this->sale('TEST-RELEASED', 0, true);
        $twoItems = $this->sale('TEST-TWO', 2 * FreezerTestSale::UNIT_PRICE_CENTS, true);
        $this->sale('REAL-ODD', FreezerTestSale::UNIT_PRICE_CENTS, false);
        $this->sale('REAL-RELEASED', 0, false);
        $real = $this->sale('REAL', 430, false);

        $this->sweep();

        $this->assertEqualsCanonicalizing(
            [$oneItem->id, $released->id, $twoItems->id, $real->id],
            VendTransaction::withoutGlobalScopes()->pluck('id')->all()
        );
    }

    /** Everything under the TEST operator is swept: that rule is about the machine, not the sale. */
    public function test_a_test_sale_under_the_test_operator_is_still_swept(): void
    {
        $testOperator = Operator::withoutGlobalScopes()->create(['code' => VendTransaction::ODD_TRANSACTION_OPERATOR_CODE, 'name' => 'Test', 'is_active' => true]);
        $this->sale('TEST-OP', FreezerTestSale::UNIT_PRICE_CENTS, true, $testOperator->id);

        $this->sweep();

        $this->assertSame(0, VendTransaction::withoutGlobalScopes()->count());
    }

    public function test_after_the_sweep_a_test_sale_shows_on_sales_transactions_with_its_badge(): void
    {
        $this->sale('TEST-ONE', FreezerTestSale::UNIT_PRICE_CENTS, true);
        $this->sale('REAL', 430, false);

        $this->sweep();

        $rows = [];
        $this->actingAs($this->viewer('read transactions'))
            ->get('/vends/transactions?'.http_build_query([
                'date_from' => now()->subDay()->toDateTimeString(),
                'date_to' => now()->addDay()->toDateTimeString(),
                'operators' => [$this->operator->id],
            ]))
            ->assertOk()
            ->assertInertia(function (AssertableInertia $page) use (&$rows) {
                foreach ($page->toArray()['props']['vendTransactions']['data'] as $row) {
                    $rows[$row['order_id']] = $row;
                }
            });

        $this->assertTrue($rows['TEST-ONE']['is_test_sale']);
        $this->assertFalse($rows['REAL']['is_test_sale']);
    }

    public function test_after_the_sweep_a_test_sale_shows_on_ai_recognition_and_counts_on_the_ai_dashboard(): void
    {
        $this->recognitionOf($this->sale('TEST-ONE', FreezerTestSale::UNIT_PRICE_CENTS, true));
        $this->recognitionOf($this->sale('REAL', 430, false));

        $this->sweep();

        $this->actingAs($this->viewer('read ai-recognition'))->get('/ai-recognition')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('recognitions.data', 2)
                ->where('recognitions.data', fn ($rows) => collect($rows)->pluck('sale.is_test_sale', 'sale.order_id')->sortKeys()->all() === ['REAL' => false, 'TEST-ONE' => true])
                ->where('dashboard.charged_cents', 430 + FreezerTestSale::UNIT_PRICE_CENTS)
                ->where('dashboard.successful', 2)
                ->where('dashboard.total', 2)
                ->where('dashboard.correct_qty', 2));
    }

    /** Not a test sale: the sweep takes it as before, and its session stays on the page with no sale. */
    public function test_an_ordinary_odd_amount_sale_is_still_swept_from_under_its_session(): void
    {
        $recognition = $this->recognitionOf($this->sale('REAL-ODD', FreezerTestSale::UNIT_PRICE_CENTS, false));

        $this->sweep();

        $this->actingAs($this->viewer('read ai-recognition'))->get('/ai-recognition?search='.$recognition->trade_id)
            ->assertInertia(fn (AssertableInertia $page) => $page->where('recognitions.data.0.sale', null));
    }
}
