<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;

class CheckHorizonStatus extends Command
{
    protected $signature = 'horizon:check';
    protected $description = 'Check if Horizon is running and dump a status message';

    public function handle()
    {
        $this->info('Checking Horizon status...');

        try {
            $masters = app(MasterSupervisorRepository::class)->all();

            if (empty($masters)) {
                $this->error('❌ Horizon is NOT running (no master supervisor found).');
                $this->line('   → Start it with: php artisan horizon');
                return 1;
            }

            foreach ($masters as $master) {
                $status = $master->status ?? 'unknown';
                $this->info("✅ Horizon is RUNNING — status: {$status}");
                $this->line("   Master: {$master->name}");
            }

            $this->newLine();
            $this->info('✅ Horizon is working!');

        } catch (\Exception $e) {
            $this->error('❌ Could not connect to Horizon: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
