<?php

namespace App\Jobs;

use App\Models\SmartFreezerRecognition;
use App\Services\SmartFreezer\OwnAi\OwnAiLink;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * The safety net for announcing one door session to our own freezer AI on the office Macs ({@see OwnAiLink}): the job
 * normally goes out the moment the session is ready (OwnAiLink::videosCopied); this sends it anyway, once, when that
 * never happened. A few tries: the broker may blip, and a missed one is caught up by the Macs' own sweep anyway.
 */
class PublishFreezerAiJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function __construct(public readonly int $recognitionId)
    {
        $this->onQueue('low');
    }

    public function handle(OwnAiLink $link): void
    {
        $recognition = SmartFreezerRecognition::with('vend')->find($this->recognitionId);
        if ($recognition !== null && $link->enabledFor($recognition) && ! $link->wasPublished($recognition->id)) {
            $link->publishJob($recognition);
        }
    }
}
