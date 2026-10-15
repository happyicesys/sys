<?php

namespace Database\Seeders;

use App\Models\CardTerminal;
use App\Models\CardTerminalUnit;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\Vend;
use App\Services\CardSettlement\CardTerminalBindingService;
use Illuminate\Database\Seeder;

/**
 * Registers the second Payrallel T05 (SN N620W342856, label photographed on smart freezer 50001,
 * 2026-10-15) under Data Management > Card Terminal, and binds it to 50001 — the same as picking
 * it in 50001's Setting/Edit Card Terminal field, so the binding history and the freezer's card
 * rail (remote_card_terminals) both follow. The first T05 (N620W325842) stays on bench 2009.
 *
 * The Payrallel access token is per terminal and secret, so it is never in source. Until it is
 * entered (Data Management > Card Terminal > N620W342856), this seeder only registers the unit:
 * binding a token-less T05 would record the new terminal in the history while 50001 kept
 * charging through the old one. Enter the token, then run the seeder again — or
 * `php artisan payrallel:bind-terminal 50001 --sn=N620W342856`. Safe to re-run.
 *
 *   php artisan db:seed --class=PayrallelT05For50001Seeder --force
 */
class PayrallelT05For50001Seeder extends Seeder
{
    public const SN = 'N620W342856';

    public const VEND_CODE = '50001';

    // users.id 1 = Brian; recorded on the binding as a manual change.
    private const CREATED_BY = 1;

    public function run(CardTerminalBindingService $bindings): void
    {
        $company = CardTerminal::query()->where('name', CardTerminal::NAME_PAYRALLEL)->first();
        if (! $company) {
            $this->command?->error('No "'.CardTerminal::NAME_PAYRALLEL.'" card terminal company — nothing done.');

            return;
        }

        $unit = CardTerminalUnit::query()->firstOrCreate(
            ['terminal_id' => self::SN],
            ['card_terminal_id' => $company->id, 'remarks' => 'T05 for smart freezer 50001 (2026-10-15)'],
        )->load('company');
        if ((int) $unit->card_terminal_id !== (int) $company->id) {
            $this->command?->error(self::SN.' is already registered under another company — nothing bound.');

            return;
        }
        $this->command?->info(($unit->wasRecentlyCreated ? 'Registered' : 'Already registered').' T05 '.self::SN." (card_terminal_units #{$unit->id}).");

        $vend = Vend::withoutGlobalScope(OperatorVendFilterScope::class)->bareCode(self::VEND_CODE)->first();
        if (! $vend || ! $vend->isSmartFreezer()) {
            $this->command?->error('Machine '.self::VEND_CODE.' not found or not a smart freezer — nothing bound.');

            return;
        }

        if (! $unit->hasAccessToken()) {
            $this->command?->warn('Not bound yet: enter the T05\'s Payrallel access token under Data Management > Card Terminal > '
                .self::SN.', then run this seeder again.');

            return;
        }

        $changed = $bindings->assignToVend($vend, $unit, null, self::CREATED_BY);
        $this->command?->info($changed
            ? 'Bound '.self::SN.' to '.self::VEND_CODE.'; its card rail now uses this T05.'
            : self::SN.' was already '.self::VEND_CODE.'\'s terminal; card rail re-checked.');
    }
}
