<?php

namespace Tests\Feature;

use App\Models\Operator;
use App\Models\Product;
use App\Models\SmartFreezerRecognition;
use App\Models\User;
use App\Models\VendTransaction;
use App\Support\OperatorScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Sales Transactions "AI Recognition" column: each Smart Freezer sale carries the AI check of
 * its door session — found by the recognition's vend_transaction_id, or before the verdict by
 * the TRADE's SFREF = session_ref — with the verdict, what the AI saw and the processing time.
 */
class TransactionIndexAiRecognitionColumnTest extends TestCase
{
    use RefreshDatabase;

    private Operator $operator;

    private int $vendId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->operator = Operator::withoutGlobalScopes()->create(['code' => 'TSTOP', 'name' => 'Test Operator', 'is_active' => true]);
        $customerId = DB::table('customers')->insertGetId([
            'name' => 'Freezer site', 'profile_id' => 1, 'status_id' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->vendId = DB::table('vends')->insertGetId([
            'code' => 50001, 'name' => 'Freezer 50001', 'operator_id' => $this->operator->id, 'customer_id' => $customerId,
            'machine_type' => 'smart_freezer', 'is_testing' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);
        Product::forceCreate(['code' => 'CC-01', 'name' => 'Basque Cheesecake', 'operator_id' => 1, 'barcode' => '9726436016148']);
    }

    private function sale(string $orderId, ?string $sfref): VendTransaction
    {
        return VendTransaction::create([
            'order_id' => $orderId, 'vend_id' => $this->vendId, 'operator_id' => $this->operator->id,
            'transaction_datetime' => now(), 'amount' => 430, 'qty' => 1, 'vend_channel_code' => 41, 'vend_channel_id' => 0,
            'gst_vat_rate' => 0, 'interface_type' => 0, 'settlement_status' => VendTransaction::SETTLEMENT_SETTLED,
            'vend_transaction_json' => array_filter(['Type' => 'TRADE', 'SFREF' => $sfref]),
        ]);
    }

    /** @return array<string, array> */
    private function gridRows(): array
    {
        Permission::findOrCreate('read transactions', 'web');
        $user = User::factory()->create(['operator_id' => $this->operator->id]);
        $user->givePermissionTo('read transactions');
        OperatorScope::flush();

        $rows = [];
        $this->actingAs($user)
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

        return $rows;
    }

    public function test_the_column_carries_the_verdict_what_the_ai_saw_and_the_processing_time(): void
    {
        $judged = $this->sale('JUDGED', 'SF-50001-1790746050-1');
        $recognition = SmartFreezerRecognition::create([
            'vend_id' => $this->vendId, 'trade_id' => 'SF-50001-1790746050-1-r3', 'session_ref' => 'SF-50001-1790746050-1',
            'vend_transaction_id' => $judged->id, 'status' => SmartFreezerRecognition::STATUS_COMPLETED,
            'order_status' => 0, 'items' => ['9726436016148' => 1], 'verdict' => 'match',
            'verdict_lines' => [['product_id' => 1, 'code' => '9726436016148', 'paid' => 1, 'taken' => 1, 'delta' => 0]],
            'submitted_at' => now()->subSeconds(140), 'completed_at' => now(),
        ]);
        $recognition->forceFill(['created_at' => now()->subSeconds(200)])->save();

        // Sent to the AI, no result yet — so no vend_transaction_id; found by the TRADE's SFREF.
        $this->sale('CHECKING', 'SF-50001-1790746999-2');
        SmartFreezerRecognition::create([
            'vend_id' => $this->vendId, 'trade_id' => 'SF-50001-1790746999-2', 'session_ref' => 'SF-50001-1790746999-2',
            'status' => SmartFreezerRecognition::STATUS_SUBMITTED, 'submitted_at' => now(),
        ]);

        // Their AI refused: the reason is put in plain words for ops.
        $this->sale('REFUSED', 'SF-50001-1790747000-3');
        SmartFreezerRecognition::create([
            'vend_id' => $this->vendId, 'trade_id' => 'SF-50001-1790747000-3', 'session_ref' => 'SF-50001-1790747000-3',
            'status' => SmartFreezerRecognition::STATUS_FAILED, 'order_status' => 501,
            'callback_payload' => ['bizContent' => json_encode(['tradeId' => 'SF-50001-1790747000-3', 'orderStatus' => 501, 'jsOrderStatus' => 503])],
        ]);

        $this->sale('PLAIN', null);

        $rows = $this->gridRows();

        $ai = $rows['JUDGED']['ai_recognition'];
        $this->assertSame('match', $ai['verdict']);
        $this->assertSame([['code' => '9726436016148', 'name' => 'Basque Cheesecake', 'number' => 1]], $ai['items']);
        $this->assertSame(140, $ai['ai_seconds']);
        $this->assertSame(200, $ai['total_seconds']);
        $this->assertSame('SF-50001-1790746050-1-r3', $ai['trade_id']);

        $this->assertSame('submitted', $rows['CHECKING']['ai_recognition']['status']);
        $this->assertNull($rows['CHECKING']['ai_recognition']['ai_seconds']);

        $this->assertSame('Reason: a product is not set up for camera checking yet', $rows['REFUSED']['ai_recognition']['reason']);

        $this->assertNull($rows['PLAIN']['ai_recognition']);
    }
}
