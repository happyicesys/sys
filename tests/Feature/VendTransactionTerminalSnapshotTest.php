<?php

namespace Tests\Feature;

use App\Models\CardTerminal;
use App\Models\CardTerminalBinding;
use App\Models\CardTerminalUnit;
use App\Models\Operator;
use App\Models\PaymentMethod;
use App\Models\Vend;
use App\Models\VendChannelError;
use App\Models\VendTransaction;
use App\Services\CardSettlement\CardTerminalBindingService;
use App\Services\VendTransactionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * `vend_transactions.terminal_id` freezes the acquirer TID the sale went
 * through, resolved from the binding in force on the sale's DAY at write
 * time. It is history: a later rebind never touches it. Only card-terminal
 * sales carry one — cash and QR rails have no terminal — and a card sale on
 * a machine with no binding that day stays null rather than guessing.
 */
class VendTransactionTerminalSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private const TID_A = '23100701';

    private const TID_B = '23100702';

    private Vend $vend;

    private CardTerminal $nets;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-12 14:00:00');
        Bus::fake();

        $operator = Operator::create(['code' => 'OP1', 'name' => 'Op', 'timezone' => 'Asia/Singapore']);
        $this->vend = Vend::create(['code' => '9001', 'operator_id' => $operator->id, 'is_active' => 1]);
        PaymentMethod::firstOrCreate(['code' => 0], ['name' => 'Cash', 'is_active' => true]);
        PaymentMethod::firstOrCreate(['code' => PaymentMethod::CODE_CARD_TERMINAL], ['name' => 'Card Terminal', 'is_active' => true]);
        VendChannelError::firstOrCreate(['code' => 0], ['desc' => 'No Malfunction (0)']);

        $this->nets = CardTerminal::create(['name' => 'Nets']);
        CardTerminalUnit::create(['terminal_id' => self::TID_A, 'card_terminal_id' => $this->nets->id]);
        CardTerminalUnit::create(['terminal_id' => self::TID_B, 'card_terminal_id' => $this->nets->id]);
        CardTerminalBinding::create(['provider' => 'nets', 'terminal_id' => self::TID_A, 'vend_id' => $this->vend->id, 'bound_from' => '2026-08-01']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** One TRADE frame through the real ingest; PAY_TYPE 1 = card terminal. */
    private function trade(string $orderId, array $frame = []): VendTransaction
    {
        app(VendTransactionService::class)->create($this->vend->fresh(), array_merge([
            'ORDRID' => $orderId, 'PAY_TYPE' => 1, 'TIME' => '2026-09-12 13:59:50', 'SErr' => 0, 'SId' => 11, 'Price' => 200, 'TXN_SRC' => 0,
        ], $frame));

        return VendTransaction::withoutGlobalScopes()->where('vend_id', $this->vend->id)->where('order_id', $orderId)->firstOrFail();
    }

    public function test_a_card_sale_freezes_the_terminal_bound_that_day(): void
    {
        $this->assertSame(self::TID_A, $this->trade('T1')->terminal_id);
        $this->assertSame(self::TID_A, VendTransaction::withoutGlobalScopes()->find($this->trade('T1b')->id)->cardTerminalUnit->terminal_id);
    }

    public function test_cash_sales_carry_no_terminal(): void
    {
        $this->assertNull($this->trade('T2', ['PAY_TYPE' => 0])->terminal_id);
    }

    public function test_a_card_sale_on_a_machine_with_no_binding_stays_null(): void
    {
        CardTerminalBinding::query()->delete();

        $this->assertNull($this->trade('T3')->terminal_id, 'never guess a terminal');
    }

    public function test_a_rebind_changes_new_sales_but_never_the_history(): void
    {
        $before = $this->trade('T4');
        $this->assertSame(self::TID_A, $before->terminal_id);

        // Swap the reader on the machine today, through the only writer.
        app(CardTerminalBindingService::class)->assignToVend(
            $this->vend->fresh(),
            CardTerminalUnit::where('terminal_id', self::TID_B)->firstOrFail(),
            '2026-09-12'
        );

        $after = $this->trade('T5');
        $this->assertSame(self::TID_B, $after->terminal_id, 'newest binding wins on the swap day');
        $this->assertSame(self::TID_A, $before->fresh()->terminal_id, 'the earlier sale keeps the terminal it went through');
        $this->assertSame(self::TID_A, $before->fresh()->cardTerminalUnit->terminal_id);
    }

    public function test_a_replayed_frame_resolves_the_binding_of_its_own_day_not_todays(): void
    {
        app(CardTerminalBindingService::class)->assignToVend(
            $this->vend->fresh(),
            CardTerminalUnit::where('terminal_id', self::TID_B)->firstOrFail(),
            '2026-09-10'
        );

        // A sale the APK replays from before the swap: booked on 09-05, when A was fitted.
        $this->assertSame(self::TID_A, $this->trade('T6', ['TIME' => '2026-09-05 10:00:00'])->terminal_id);
        $this->assertSame(self::TID_B, $this->trade('T7')->terminal_id);
    }
}
