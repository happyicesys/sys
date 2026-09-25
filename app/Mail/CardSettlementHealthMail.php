<?php

namespace App\Mail;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;

/**
 * The nightly card-settlement check (CardSettlementHealthCheck): only what
 * needs a person. Never sent when there is nothing to act on.
 */
class CardSettlementHealthMail extends Mailable implements ShouldQueue
{
    use Queueable;

    // Named so it never collides with the view's `$generatedAt` (a Carbon):
    // a Mailable's public properties are merged INTO the view data and win.
    public function __construct(public array $sections, public string $generatedAtIso) {}

    public function build()
    {
        $count = collect($this->sections)->sum(fn ($s) => count($s['items']));

        return $this
            ->subject(sprintf('Card settlement: %d item(s) need attention (%s)', $count, Carbon::parse($this->generatedAtIso)->format('Y-m-d')))
            ->view('emails.card-settlement-health', [
                'sections' => $this->sections,
                'generatedAt' => Carbon::parse($this->generatedAtIso),
            ]);
    }
}
