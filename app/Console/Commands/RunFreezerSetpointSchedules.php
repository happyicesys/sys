<?php

namespace App\Console\Commands;

use App\Models\FreezerControlCommand;
use App\Models\FreezerSetpointSchedule;
use App\Models\Vend;
use App\Services\Freezer\FreezerControlService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Sends each due smart-freezer setpoint entry as an ordinary `setpoint` command, so every run is a
 * row on the machine's timeline with the machine's own verdict.
 *
 * - Due = `run_at` has passed today and the entry has not run today. When several entries of one
 *   machine are due (the job was down, or two times were added), only the LATEST is sent: it is the
 *   one that should hold now, and the earlier ones are marked done for today.
 * - An entry more than --grace minutes late is skipped for today rather than fired hours late (a
 *   night setback sent at 09:00 would warm a full cabinet).
 * - A machine that is busy with another command is retried next minute; one that is too old for
 *   remote controls is skipped and logged.
 *
 * The controller cannot read its setpoint back, so an `ok` means "the host accepted the write" —
 * the chamber temperature is the confirmation, same as a manual Set.
 */
class RunFreezerSetpointSchedules extends Command
{
    protected $signature = 'freezer:run-setpoint-schedules {--grace=15 : minutes after run_at an entry may still be sent}';

    protected $description = 'Send due smart-freezer setpoint schedule entries';

    public function handle(FreezerControlService $service): int
    {
        $now = Carbon::now();
        $today = $now->toDateString();
        $grace = max(1, (int) $this->option('grace'));

        $due = FreezerSetpointSchedule::where('is_active', true)
            ->where('run_at', '<=', $now->format('H:i:s'))
            ->where(fn ($q) => $q->whereNull('last_run_on')->orWhere('last_run_on', '<', $today))
            ->orderBy('vend_id')->orderBy('run_at')
            ->get()
            ->groupBy('vend_id');

        $sent = 0;
        foreach ($due as $vendId => $entries) {
            $latest = $entries->last();
            $earlier = $entries->slice(0, -1);
            $runAt = Carbon::parse($today.' '.$latest->run_at);

            if ($runAt->copy()->addMinutes($grace)->lt($now)) {
                FreezerSetpointSchedule::whereIn('id', $entries->pluck('id'))->update(['last_run_on' => $today]);
                Log::warning('Freezer setpoint schedule skipped: too late', ['vend_id' => $vendId, 'run_at' => $latest->runAtLabel(), 'grace' => $grace]);

                continue;
            }

            $vend = Vend::withoutGlobalScopes()->find($vendId);
            if (! $vend || ! $vend->isSmartFreezer() || ! $vend->is_active) {
                FreezerSetpointSchedule::whereIn('id', $entries->pluck('id'))->update(['last_run_on' => $today]);

                continue;
            }
            if ((int) $vend->apk_version_code < FreezerControlService::MIN_APK_VERSION_CODE) {
                FreezerSetpointSchedule::whereIn('id', $entries->pluck('id'))->update(['last_run_on' => $today]);
                Log::warning('Freezer setpoint schedule skipped: app too old', ['vend_code' => $vend->code, 'apk' => $vend->apk_version_code]);

                continue;
            }

            $busy = FreezerControlCommand::where('vend_id', $vend->id)
                ->where('status', FreezerControlCommand::STATUS_PENDING)
                ->where('expires_at', '>', $now)
                ->exists();
            if ($busy) {
                continue; // next minute, inside the grace window
            }

            $command = $service->dispatch(
                $vend, 'setpoint', ['celsius' => $latest->celsius], null, null,
                FreezerControlCommand::SOURCE_SCHEDULE, 'Schedule '.$latest->runAtLabel(),
            );
            $latest->update(['last_run_on' => $today, 'last_command_id' => $command->id]);
            if ($earlier->isNotEmpty()) {
                FreezerSetpointSchedule::whereIn('id', $earlier->pluck('id'))->update(['last_run_on' => $today]);
            }
            $sent++;
        }

        $this->info("Sent {$sent} scheduled setpoint(s).");

        return self::SUCCESS;
    }
}
