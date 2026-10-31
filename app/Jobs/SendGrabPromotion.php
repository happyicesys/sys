<?php

namespace App\Jobs;

use App\Services\GrabPromotionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

// One store's promotion send to Grab. Never retried: a lost answer may already be on Grab.
class SendGrabPromotion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $timeout = 120;

    public function __construct(public int $sendId) {}

    public function handle(GrabPromotionService $service): void
    {
        $service->perform($this->sendId);
    }
}
