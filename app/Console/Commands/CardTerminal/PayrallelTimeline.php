<?php

namespace App\Console\Commands\CardTerminal;

use App\Models\CardPaymentEvent;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Reads the remote-terminal trial timeline (card_payment_events): what the
 * device saw, what mark1 decided, what Payrallel answered — one machine,
 * oldest first.
 *
 *   php artisan payrallel:timeline 50001                 last 2 hours
 *   php artisan payrallel:timeline 50001 --since=24h
 *   php artisan payrallel:timeline 50001 --ref=SF1759...  one attempt
 *   php artisan payrallel:timeline 50001 --raw           full detail JSON
 */
class PayrallelTimeline extends Command
{
    protected $signature = 'payrallel:timeline
        {vend : bare machine code}
        {--since=2h : how far back, e.g. 30m, 2h, 3d}
        {--ref= : only one attempt (device reference SF…)}
        {--raw : print each event\'s full detail}';

    protected $description = 'Show the Payrallel terminal trial timeline for a machine';

    public function handle(): int
    {
        $vend = Vend::withoutGlobalScope(OperatorVendFilterScope::class)->bareCode($this->argument('vend'))->first();
        if (! $vend) {
            $this->error('No machine with that code.');

            return self::FAILURE;
        }

        $query = CardPaymentEvent::query()->where('vend_id', $vend->id)->orderBy('id');
        if ($ref = $this->option('ref')) {
            $query->where('custom_order_id', $vend->code.'-'.$ref);
        } else {
            $query->where('created_at', '>=', $this->since((string) $this->option('since')));
        }

        $rows = $query->limit(2000)->get();
        if ($rows->isEmpty()) {
            $this->line('No events.');

            return self::SUCCESS;
        }

        foreach ($rows as $e) {
            $detail = (array) $e->detail;
            $line = sprintf(
                '%s %-7s %-22s %s%s',
                $e->created_at->format('m-d H:i:s.v'),
                strtoupper($e->level),
                $e->event,
                $e->custom_order_id ? '['.substr($e->custom_order_id, strlen((string) $vend->code) + 1).'] ' : '',
                $this->option('raw') ? json_encode($detail, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $this->summary($detail),
            );
            if ($e->duration_ms !== null) {
                $line .= " ({$e->duration_ms} ms)";
            }
            $this->line($line);
        }
        $this->line(sprintf('%d event(s).', $rows->count()));

        return self::SUCCESS;
    }

    private function since(string $since): Carbon
    {
        if (preg_match('/^(\d+)\s*([mhd])$/', trim($since), $m)) {
            return match ($m[2]) {
                'm' => Carbon::now()->subMinutes((int) $m[1]),
                'h' => Carbon::now()->subHours((int) $m[1]),
                'd' => Carbon::now()->subDays((int) $m[1]),
            };
        }

        return Carbon::parse($since);
    }

    /** One readable line per event; the raw body is behind --raw. */
    private function summary(array $d): string
    {
        $keys = ['action', 'from', 'to', 'path', 'http_status', 'state', 'online', 'why', 'rail', 'reason', 'error', 'provider_status', 'payment_method'];
        $parts = [];
        foreach ($keys as $k) {
            if (array_key_exists($k, $d) && $d[$k] !== null && $d[$k] !== '') {
                $v = is_bool($d[$k]) ? ($d[$k] ? 'yes' : 'no') : (is_scalar($d[$k]) ? (string) $d[$k] : json_encode($d[$k]));
                $parts[] = "{$k}=".mb_substr($v, 0, 120);
            }
        }

        return implode(' ', $parts);
    }
}
