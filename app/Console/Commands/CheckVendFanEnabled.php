<?php

namespace App\Console\Commands;

use App\Models\Vend;
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
        $updatedCount = Vend::where('is_fan_enabled', false)
            ->where('parameter_json->fan', '>', 1000)
            ->update([
                'is_fan_enabled' => true,
            ]);

        $this->info("Successfully updated {$updatedCount} vends to is_fan_enabled = true.");

        return 0;
    }
}
