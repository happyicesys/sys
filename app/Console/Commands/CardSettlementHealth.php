<?php

namespace App\Console\Commands;

use App\Mail\CardSettlementHealthMail;
use App\Models\AlertEmailItem;
use App\Models\Operator;
use App\Services\CardSettlement\CardSettlementHealthCheck;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Nightly: email what in card settlement needs a person — and stay silent
 * when nothing does. Recipients: the active alert-email list of the operator
 * in config('card_settlement.alert_operator_code').
 *
 *   php artisan card-settlement:health --dry-run      # print, send nothing
 *   php artisan card-settlement:health --to=me@x.com  # send to one address
 */
class CardSettlementHealth extends Command
{
    protected $signature = 'card-settlement:health
        {--dry-run : print the findings, send nothing}
        {--to= : send to this address instead of the alert list}';

    protected $description = 'Email the card-settlement items that need a person (silent when none)';

    public function handle(CardSettlementHealthCheck $check): int
    {
        $sections = $check->run();

        foreach ($sections as $s) {
            $this->line("== {$s['title']} (".count($s['items']).')');
            foreach ($s['items'] as $item) {
                $this->line('  - '.$item['text']);
            }
        }
        if (! $sections) {
            $this->info('Nothing needs a person — no email.');

            return self::SUCCESS;
        }
        if ($this->option('dry-run')) {
            $this->comment('Dry run — nothing sent.');

            return self::SUCCESS;
        }

        $recipients = $this->option('to')
            ? collect([$this->option('to')])
            : AlertEmailItem::query()
                ->where('is_active', true)
                ->where('operator_id', Operator::withoutGlobalScopes()->where('code', config('card_settlement.alert_operator_code', 'HIPL'))->value('id'))
                ->pluck('email')->filter()->unique()->values();

        foreach ($recipients as $email) {
            Mail::to($email)->queue(new CardSettlementHealthMail($sections, now()->toDateTimeString()));
        }
        $this->info('Sent to '.$recipients->implode(', '));

        return self::SUCCESS;
    }
}
