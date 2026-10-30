<?php

namespace App\Console\Commands;

use App\Models\Vend;
use App\Support\FanSignal;
use Illuminate\Console\Command;

class CheckVendFanEnabled extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:vend-fan-enabled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and set is_fan_enabled to true if parameter_json has fan value > 1000 for N/A vends';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Backstop for the ingest latch (SyncVendParameter): a machine whose board reports a running
        // fan but was never stamped. Same 60-min rule and one-way, like the latch (App\Support\FanSignal).
        $updatedCount = Vend::withoutGlobalScopes()
            ->where(fn ($q) => $q->where('is_fan_enabled', false)->orWhereNull('fan_detected_at'))
            ->where('parameter_json->fan', '>', FanSignal::RUNNING_MIN)
            ->get()
            ->filter(fn (Vend $vend) => FanSignal::hasRunLongEnough($vend->id))
            ->each(function (Vend $vend) {
                $vend->is_fan_enabled = true;
                $vend->fan_detected_at ??= now();
                $vend->save();
            })
            ->count();

        $this->info("Successfully updated {$updatedCount} vends to is_fan_enabled = true.");

        return 0;
    }
}
