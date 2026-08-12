<?php

namespace Database\Seeders;

use App\Models\Vend;
use App\Models\VendModel;
use Illuminate\Database\Seeder;

/**
 * VendModelSeeder — seeds the "Smart Vend" vend model AND tags the freezers with it.
 *
 * `vend_models` is the hardware-model classifier a Vend points at via `vends.vend_model_id`
 * (used today for filtering, reporting and list sorting — see App\Traits\HasFilter). Since
 * 2026-07-31 it is ALSO how the OTA layer decides which APK channel a machine belongs to
 * when the polling device does not report its own applicationId (see config/ota.php and
 * App\Services\OtaChannelResolver).
 *
 * That second job is why this seeder now backfills as well as creates. Every channel except
 * one is scoped to a vend model name; the remaining channel (`vending`) is the catch-all
 * that owns every model nobody claimed. So an UNTAGGED smart freezer resolves to `vending`
 * and would be offered the legacy vending APK's manifest. Tagging the freezers closes that.
 *
 * WHICH MACHINES GET TAGGED: every vend with `machine_type = smart_freezer` — the machine's
 * own declared identity (2026-08-12, Setting → Edit), which the binding guards enforce.
 * Previously this inferred from the bound mapping's `is_smart`, but that inference is exactly
 * what the machine_type column retired: a grandfathered mis-bound vending machine on a smart
 * mapping would have been tagged Smart Vend and offered the freezer APK by the OTA fallback.
 *
 * SAFE TO RE-RUN. Both halves are idempotent: firstOrCreate keyed on name, and the update
 * skips machines already on the model. Machines are only ever moved ONTO the Smart Vend
 * model, never off it, so a manual correction made afterwards in Machine Settings survives
 * the next run.
 *
 * NOTE: a machine moved here keeps all of its history — only vends.vend_model_id changes.
 * If a freezer was previously classified under another model (on live today both sit under
 * "test"), any report or filter grouped by model name shows it under "Smart Vend" from now
 * on. That is the intended reclassification, but it is a visible change.
 */
class VendModelSeeder extends Seeder
{
    public function run(): void
    {
        $smartVend = VendModel::firstOrCreate(
            ['name' => VendModel::SMART_VEND],
            [
                'desc' => 'AI smart freezer — vision-based grab-and-go (sg.mark1.freezer APK)',
                'is_sortable' => true,
            ],
        );

        $this->command?->info("Vend model [{$smartVend->name}] id={$smartVend->id} ready.");

        // withoutGlobalScopes(): this is a data-integrity backfill, not an operator-facing
        // read. Vend carries OperatorVendFilterScope and a seeder runs with no authenticated
        // user, so leaving the scope on would make the result depend on how that scope treats
        // a null user — the last thing a backfill should be sensitive to.
        $affected = Vend::query()
            ->withoutGlobalScopes()
            ->where('machine_type', Vend::MACHINE_TYPE_SMART_FREEZER)
            // Never touch a machine that is already correct, so a second run is a true no-op
            // and a hand-fix is never reverted.
            ->where(function ($query) use ($smartVend) {
                $query->whereNull('vend_model_id')->orWhere('vend_model_id', '!=', $smartVend->id);
            })
            ->get(['id', 'code', 'vend_model_id']);

        if ($affected->isEmpty()) {
            $this->command?->info('All smart-freezer machines already tagged — nothing to do.');

            return;
        }

        foreach ($affected as $vend) {
            $this->command?->line("  {$vend->code}: vend_model_id {$vend->vend_model_id} -> {$smartVend->id}");
        }

        // Bulk update without model events: vends carries heavy observers/listeners and this
        // is a classifier change, not an operational one — nothing downstream should fire.
        Vend::query()
            ->withoutGlobalScopes()
            ->whereIn('id', $affected->pluck('id'))
            ->update(['vend_model_id' => $smartVend->id]);

        $this->command?->info("Tagged {$affected->count()} machine(s) as [{$smartVend->name}].");
    }
}
