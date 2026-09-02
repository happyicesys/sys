<?php

namespace Tests\Feature\Jobs;

use App\Jobs\RemoveOddTransactions;
use App\Models\Operator;
use App\Models\PaymentMethod;
use App\Models\Vend;
use App\Models\VendTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RemoveOddTransactionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Model::unguard();
        putenv('DELETE_ODD_TRANSACTIONS=true');
        $_ENV['DELETE_ODD_TRANSACTIONS'] = 'true';
        $_SERVER['DELETE_ODD_TRANSACTIONS'] = 'true';
    }

    protected function tearDown(): void
    {
        putenv('DELETE_ODD_TRANSACTIONS');
        unset($_ENV['DELETE_ODD_TRANSACTIONS'], $_SERVER['DELETE_ODD_TRANSACTIONS']);
        parent::tearDown();
    }

    private function txn(Vend $vend, Operator $operator, PaymentMethod $pm, int $amount): VendTransaction
    {
        return VendTransaction::create([
            'vend_id' => $vend->id,
            'operator_id' => $operator->id,
            'payment_method_id' => $pm->id,
            'amount' => $amount,
            'order_id' => uniqid('t'),
            'transaction_datetime' => now(),
            'vend_channel_id' => 1,
            'gst_vat_rate' => 0,
            'created_at' => now(),
        ]);
    }

    public function test_retained_vend_code_survives_the_sweep_while_others_are_deleted()
    {
        $testOp = Operator::create(['code' => VendTransaction::ODD_TRANSACTION_OPERATOR_CODE, 'name' => 'Test', 'country_id' => 1, 'created_by' => 1, 'gst_vat_rate' => 0]);
        $liveOp = Operator::create(['code' => 'HIPL', 'name' => 'Live', 'country_id' => 1, 'created_by' => 1, 'gst_vat_rate' => 0]);
        $cash = PaymentMethod::create(['code' => 1, 'name' => 'Cash']);

        $bench = Vend::create(['code' => VendTransaction::ODD_TRANSACTION_RETAIN_VEND_CODES[0], 'operator_id' => $liveOp->id]);
        $other = Vend::create(['code' => '2031', 'operator_id' => $liveOp->id]);

        $keptOdd = $this->txn($bench, $liveOp, $cash, 10);      // odd amount on retained vend
        $keptTest = $this->txn($bench, $testOp, $cash, 150);    // TEST operator on retained vend
        $keptReal = $this->txn($other, $liveOp, $cash, 150);    // ordinary sale elsewhere
        $sweptOdd = $this->txn($other, $liveOp, $cash, 10);     // odd amount elsewhere
        $sweptTest = $this->txn($other, $testOp, $cash, 150);   // TEST operator elsewhere

        (new RemoveOddTransactions(today()->toDateString(), today()->toDateString()))->handle();

        $remaining = VendTransaction::withoutGlobalScopes()->pluck('id')->all();
        sort($remaining);
        $this->assertSame([$keptOdd->id, $keptTest->id, $keptReal->id], $remaining);
    }
}
