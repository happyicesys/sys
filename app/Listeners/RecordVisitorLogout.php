<?php

namespace App\Listeners;

use App\Services\VisitorHistoryService;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Closes the visitor session when the user actually clicks Log Out — the only
 * case where "session ended" is exact rather than inferred.
 *
 * The Logout event fires inside SessionGuard::logout(), i.e. BEFORE
 * AuthenticatedSessionController calls $request->session()->invalidate(), and
 * clearUserDataFromStorage() only removes the auth keys — so our
 * visitor_history.* session keys are still readable here.
 */
class RecordVisitorLogout
{
    public function __construct(protected VisitorHistoryService $service, protected Request $request)
    {
    }

    public function handle(Logout $event): void
    {
        try {
            if ($event->guard !== 'web' || !$this->request->hasSession()) {
                return;
            }

            // Stamp the page they were on when they hit Log Out, then close the
            // session itself.
            $this->service->closeOpenPageView($this->request, 'inferred');

            $sessionId = $this->request->session()->get(VisitorHistoryService::SESSION_KEY);
            $this->service->endSession($sessionId ? (int) $sessionId : null, 'logout');

            $this->request->session()->forget([
                VisitorHistoryService::SESSION_KEY,
                VisitorHistoryService::USER_KEY,
                VisitorHistoryService::LAST_VIEW_KEY,
            ]);
        } catch (\Throwable $e) {
            Log::warning('VisitorHistory logout listener failed: ' . $e->getMessage());
        }
    }
}
