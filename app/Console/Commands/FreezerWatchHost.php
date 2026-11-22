<?php

namespace App\Console\Commands;

use App\Services\SmartFreezer\FreezerHostWatch;
use Illuminate\Console\Command;

/** Every 5 minutes: a smart freezer cut off from its Zijia host, or a T05 capture with no sale (FreezerHostWatch). */
class FreezerWatchHost extends Command
{
    protected $signature = 'freezer:watch-host';

    protected $description = 'Alert when a smart freezer loses its door/cameras or a card capture has no sale record';

    public function handle(FreezerHostWatch $watch): int
    {
        $found = $watch->run();
        $this->line('dark: '.(implode(', ', $found['dark']) ?: 'none'));
        $this->line('card captures with no sale: '.(implode(', ', $found['no_trade']) ?: 'none'));

        return self::SUCCESS;
    }
}
