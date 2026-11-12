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
 * the TRADE's SFREF = session_ref — with the verdict and the processing time.
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

    public function test_the_column_carries_the_verdict_and_the_processing_time(): void
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
        $this->assertSame(140, $ai['ai_seconds']);
        $this->assertSame(200, $ai['total_seconds']);
        $this->assertSame('SF-50001-1790746050-1-r3', $ai['trade_id']);

        $this->assertSame('submitted', $rows['CHECKING']['ai_recognition']['status']);
        $this->assertNull($rows['CHECKING']['ai_recognition']['ai_seconds']);

        $this->assertSame('Reason: a product is not set up for camera checking yet', $rows['REFUSED']['ai_recognition']['reason']);

        $this->assertNull($rows['PLAIN']['ai_recognition']);
    }

    public function test_a_sale_a_person_adjusted_says_so_and_keeps_the_ai_verdict(): void
    {
        $sale = $this->sale('ORD-ADJ', 'SF-50001-1-4');
        $sale->forceFill(['meta_json' => ['correction' => ['by' => ['name' => 'Daniel'], 'basket' => []]]])->save();
        SmartFreezerRecognition::create([
            'trade_id' => 'SF-50001-1-4', 'session_ref' => 'SF-50001-1-4', 'vend_id' => $this->vendId,
            'vend_transaction_id' => $sale->id, 'status' => 'completed', 'verdict' => 'mixed',
        ]);

        $ai = $this->gridRows()['ORD-ADJ']['ai_recognition'];

        $this->assertSame(['kind' => 'adjusted', 'by' => 'Daniel'], $ai['handled']);
        $this->assertSame('mixed', $ai['verdict']);
    }

    /** Brian, 2026-11-12: a count equal to what the AI saw confirms it, and must not read as the AI being corrected. */
    public function test_a_count_equal_to_the_ais_says_confirmed_and_a_different_one_adjusted(): void
    {
        $lines = [
            ['product_id' => 11, 'code' => '111', 'paid' => 0, 'taken' => 1, 'delta' => 1],
            ['product_id' => 12, 'code' => '222', 'paid' => 1, 'taken' => 0, 'delta' => -1],
        ];
        foreach (['ORD-SAME' => [11 => 1], 'ORD-DIFF' => [12 => 1]] as $order => $basket) {
            $ref = 'SF-50001-1-'.($order === 'ORD-SAME' ? 5 : 6);
            $sale = $this->sale($order, $ref);
            $sale->forceFill(['meta_json' => ['correction' => ['by' => ['name' => 'Daniel'], 'basket' => $basket]]])->save();
            SmartFreezerRecognition::create([
                'trade_id' => $ref, 'session_ref' => $ref, 'vend_id' => $this->vendId,
                'vend_transaction_id' => $sale->id, 'status' => 'completed', 'verdict' => 'mixed', 'verdict_lines' => $lines,
            ]);
        }

        $rows = $this->gridRows();

        $this->assertSame(['kind' => 'confirmed', 'by' => 'Daniel'], $rows['ORD-SAME']['ai_recognition']['handled']);
        $this->assertSame(['kind' => 'adjusted', 'by' => 'Daniel'], $rows['ORD-DIFF']['ai_recognition']['handled']);
        $this->assertSame('mixed', $rows['ORD-SAME']['ai_recognition']['verdict'], 'the AI verdict is kept');
    }

    public function test_a_hold_keyed_in_without_a_count_is_handled_and_an_untouched_one_is_not(): void
    {
        $keyed = $this->sale('ORD-KEY', 'SF-50001-2-4');
        $open = $this->sale('ORD-OPEN', 'SF-50001-3-4');
        foreach ([[$keyed, 'SF-50001-2-4'], [$open, 'SF-50001-3-4']] as [$sale, $ref]) {
            SmartFreezerRecognition::create([
                'trade_id' => $ref, 'session_ref' => $ref, 'vend_id' => $this->vendId,
                'vend_transaction_id' => $sale->id, 'status' => 'completed', 'verdict' => 'mixed',
            ]);
        }
        $terminal = \App\Models\RemoteCardTerminal::create([
            'vend_id' => $this->vendId, 'provider' => \App\Models\RemoteCardTerminal::PROVIDER_PAYRALLEL,
            'access_token' => 'tok', 'is_active' => true,
        ]);
        \App\Models\CardPaymentIntent::forceCreate([
            'vend_id' => $this->vendId, 'remote_card_terminal_id' => $terminal->id, 'reference' => 'R-KEY', 'custom_order_id' => 'C-KEY', 'session_ref' => 'SF-50001-2-4',
            'amount_cents' => 430, 'state' => 'captured', 'ai_decision' => ['by_person' => ['name' => 'Daniel']],
        ]);

        $rows = $this->gridRows();

        $this->assertSame(['kind' => 'keyed_in', 'by' => 'Daniel'], $rows['ORD-KEY']['ai_recognition']['handled']);
        $this->assertNull($rows['ORD-OPEN']['ai_recognition']['handled']);
    }

    public function test_a_released_hold_says_released_and_a_person_releasing_it_counts_as_handled(): void
    {
        $auto = $this->sale('ORD-AUTO', 'SF-50001-4-4');
        $byPerson = $this->sale('ORD-PERSON', 'SF-50001-5-4');
        $charged = $this->sale('ORD-CHARGED', 'SF-50001-6-4');
        foreach ([[$auto, 'SF-50001-4-4'], [$byPerson, 'SF-50001-5-4'], [$charged, 'SF-50001-6-4']] as [$sale, $ref]) {
            SmartFreezerRecognition::create([
                'trade_id' => $ref, 'session_ref' => $ref, 'vend_id' => $this->vendId,
                'vend_transaction_id' => $sale->id, 'status' => 'completed', 'verdict' => 'took_less',
            ]);
        }
        $terminal = \App\Models\RemoteCardTerminal::create([
            'vend_id' => $this->vendId, 'provider' => \App\Models\RemoteCardTerminal::PROVIDER_PAYRALLEL,
            'access_token' => 'tok', 'is_active' => true,
        ]);
        foreach ([
            ['A', 'SF-50001-4-4', 'voided', ['reason' => 'AI saw nothing taken: hold released']],
            ['P', 'SF-50001-5-4', 'voided', ['by_person' => ['name' => 'Daniel']]],
            ['C', 'SF-50001-6-4', 'captured', []],
        ] as [$key, $ref, $state, $decision]) {
            \App\Models\CardPaymentIntent::forceCreate([
                'vend_id' => $this->vendId, 'remote_card_terminal_id' => $terminal->id, 'reference' => 'R-'.$key, 'custom_order_id' => 'C-'.$key,
                'session_ref' => $ref, 'amount_cents' => 430, 'state' => $state, 'ai_decision' => $decision,
            ]);
        }

        $rows = $this->gridRows();

        $this->assertTrue($rows['ORD-AUTO']['ai_recognition']['released']);
        $this->assertNull($rows['ORD-AUTO']['ai_recognition']['handled']);
        $this->assertSame('took_less', $rows['ORD-AUTO']['ai_recognition']['verdict']);
        $this->assertSame(['kind' => 'keyed_in', 'by' => 'Daniel'], $rows['ORD-PERSON']['ai_recognition']['handled']);
        $this->assertFalse($rows['ORD-CHARGED']['ai_recognition']['released']);
    }
}
