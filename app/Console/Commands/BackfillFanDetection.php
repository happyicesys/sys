<?php

namespace App\Console\Commands;

use App\Models\Vend;
use App\Models\VendFan;
use App\Support\FanSignal;
use App\Support\VendCode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time move to detected fans (Brian, 2026-10-19). Until then every machine started as "fan: Yes"
 * and only a person could set No, so fan-less boards raised 2A for months (2256 for 240 days). This sets
 * each machine from what its board has reported (App\Support\FanSignal): a running fan in the kept
 * readings (vend_fans, ~14 days) or in its last parameters → detected, stamped with the first running
 * reading; nothing → no fan. A fan that died before the kept readings goes to "no fan" too; the 2B–2E
 * temperature alarms still catch that machine warming up. Each change is saved through the model, so
 * Setting/Edit's "last changed" names it. Dry run unless --apply.
 */
class BackfillFanDetection extends Command
{
    protected $signature = 'fan:backfill-detection {--apply : write the changes (default: list them only)}';

    protected $description = 'Set every machine\'s fan detection from the fan speed its board has reported';

    public function handle(): int
    {
        $firstRunning = DB::table('vend_fans')
            ->where('type', VendFan::TYPE_MAIN)
            ->where('value', '>', FanSignal::RUNNING_MIN)
            ->groupBy('vend_id')
            ->pluck(DB::raw('MIN(created_at)'), 'vend_id');

        $toYes = [];
        $toNo = [];
        $stamped = 0;
        Vend::withoutGlobalScopes()->orderBy('id')->get()->each(function (Vend $vend) use ($firstRunning, &$toYes, &$toNo, &$stamped) {
            $params = is_array($vend->parameter_json) ? $vend->parameter_json : (json_decode((string) $vend->parameter_json, true) ?: []);
            $seenAt = $firstRunning[$vend->id] ?? null;
            $detected = $seenAt !== null || FanSignal::isRunning($params['fan'] ?? null);
            $label = VendCode::label($vend->code_prefix, $vend->code);

            if ($detected) {
                $changed = ! $vend->is_fan_enabled || ! $vend->fan_detected_at;
                if (! $vend->is_fan_enabled) {
                    $toYes[] = $label;
                }
                if ($changed) {
                    $stamped++;
                    if ($this->option('apply')) {
                        $vend->is_fan_enabled = true;
                        $vend->fan_detected_at ??= $seenAt ?? now();
                        $vend->save();
                    }
                }

                return;
            }

            if ($vend->is_fan_enabled || $vend->fan_detected_at) {
                if ($vend->is_fan_enabled) {
                    $toNo[] = ($vend->is_active ? '' : '(inactive) ').$label;
                }
                if ($this->option('apply')) {
                    $vend->is_fan_enabled = false;
                    $vend->fan_detected_at = null;
                    $vend->save();
                }
            }
        });

        $verb = $this->option('apply') ? 'Set' : 'Would set';
        $this->info("{$verb} fan detected (stamped) on {$stamped} machine(s); No → Yes: ".count($toYes).($toYes ? ' ('.implode(', ', $toYes).')' : ''));
        $this->info("{$verb} 'no fan' on ".count($toNo).' machine(s) that never reported a running fan'.($toNo ? ': '.implode(', ', $toNo) : ''));
        if (! $this->option('apply')) {
            $this->comment('Dry run — nothing written. Run again with --apply.');
        }

        return self::SUCCESS;
    }
}
