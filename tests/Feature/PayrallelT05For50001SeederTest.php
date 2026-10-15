<?php

namespace Tests\Feature;

use App\Models\CardTerminal;
use App\Models\CardTerminalBinding;
use App\Models\CardTerminalUnit;
use App\Models\RemoteCardTerminal;
use App\Models\Vend;
use App\Services\CardSettlement\CardTerminalBindingService;
use Database\Seeders\PayrallelT05For50001Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The new T05 is registered at once, but bound to 50001 only once its access token is entered:
 * a token-less binding would move the history to the new SN while the freezer kept charging
 * through the old one.
 */
class PayrallelT05For50001SeederTest extends TestCase
{
    use RefreshDatabase;

    private Vend $freezer;

    private Vend $bench;

    private CardTerminalUnit $oldT05;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        $company = CardTerminal::query()->firstOrCreate(['name' => CardTerminal::NAME_PAYRALLEL]);
        $company->update(['can_bind_multiple_vends' => true]);
        $this->freezer = $this->vend(50001);
        $this->bench = $this->vend(2009);
        $this->oldT05 = CardTerminalUnit::query()->create([
            'card_terminal_id' => $company->id, 'terminal_id' => 'N620W325842', 'access_token' => 'old-token',
        ])->load('company');
        $bindings = app(CardTerminalBindingService::class);
        $bindings->assignToVend($this->freezer, $this->oldT05, null, null);
        $bindings->assignToVend($this->bench, $this->oldT05, null, null);
    }

    private function vend(int $code): Vend
    {
        $vend = new Vend;
        $vend->forceFill([
            'code' => $code, 'machine_type' => Vend::MACHINE_TYPE_SMART_FREEZER, 'is_active' => 1, 'operator_id' => 1,
            'vend_model_id' => 1, 'private_key' => 'TESTKEY000000001',
        ])->save();

        return $vend->refresh();
    }

    private function openSnFor(Vend $vend): ?string
    {
        return CardTerminalBinding::query()->where('vend_id', $vend->id)->whereNull('until_at')->value('terminal_id');
    }

    public function test_without_a_token_it_registers_the_t05_but_leaves_50001_on_the_old_one(): void
    {
        $this->seed(PayrallelT05For50001Seeder::class);

        $unit = CardTerminalUnit::query()->where('terminal_id', 'N620W342856')->firstOrFail();
        $this->assertSame(CardTerminal::NAME_PAYRALLEL, $unit->company->name);
        $this->assertFalse($unit->hasAccessToken());
        $this->assertSame('N620W325842', $this->openSnFor($this->freezer));
        $this->assertSame((int) $this->oldT05->id, (int) RemoteCardTerminal::query()->where('vend_id', $this->freezer->id)->value('card_terminal_unit_id'));
    }

    public function test_with_the_token_entered_a_rerun_binds_it_to_50001_and_leaves_2009_alone(): void
    {
        $this->seed(PayrallelT05For50001Seeder::class);
        CardTerminalUnit::query()->where('terminal_id', 'N620W342856')->firstOrFail()->update(['access_token' => 'new-token']);

        $this->seed(PayrallelT05For50001Seeder::class);
        $this->seed(PayrallelT05For50001Seeder::class); // idempotent

        $unit = CardTerminalUnit::query()->where('terminal_id', 'N620W342856')->firstOrFail();
        $this->assertSame(1, CardTerminalUnit::query()->where('terminal_id', 'N620W342856')->count());
        $this->assertSame('N620W342856', $this->openSnFor($this->freezer));
        $this->assertSame(1, CardTerminalBinding::query()->where('vend_id', $this->freezer->id)->whereNull('until_at')->count());
        $rail = RemoteCardTerminal::query()->where('vend_id', $this->freezer->id)->firstOrFail();
        $this->assertSame((int) $unit->id, (int) $rail->card_terminal_unit_id);
        $this->assertTrue((bool) $rail->is_active);
        $this->assertSame('N620W325842', $this->openSnFor($this->bench));
        $this->assertSame((int) $this->oldT05->id, (int) RemoteCardTerminal::query()->where('vend_id', $this->bench->id)->value('card_terminal_unit_id'));
    }
}
