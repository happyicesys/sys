<?php

namespace App\Listeners;

use App\Services\VisitorHistoryService;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Opens a visitor_sessions row when someone signs in (Admin > Visitor History).
 * Runs synchronously — it is a single insert, and queueing it would risk the
 * PHP session having moved on before the row exists.
 */
class RecordVisitorLogin
{
    public function __construct(protected VisitorHistoryService $service, protected Request $request)
    {
    }

    public function handle(Login $event): void
    {
        try {
            // Only web (session) logins produce a visitor session; API/passport
            // token guards have no browser behind them.
            if ($event->guard !== 'web' || !$event->user) {
                return;
            }

            $this->service->startSession($this->request, (int) $event->user->getAuthIdentifier());
        } catch (\Throwable $e) {
            Log::warning('VisitorHistory login listener failed: ' . $e->getMessage());
        }
    }
}
