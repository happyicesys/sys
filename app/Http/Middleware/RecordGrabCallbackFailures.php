<?php

namespace App\Http\Middleware;

use App\Support\Grab\GrabApiErrorRecorder;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Outermost on Grab's callback routes, so it also sees a webhook-auth refusal and a
 * crash (the router renders an exception into the response it hands back here): any
 * answer >= 400 is kept with what Grab sent and why we refused.
 */
class RecordGrabCallbackFailures
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() >= 400) {
            GrabApiErrorRecorder::incoming($request, $response);
        }

        return $response;
    }
}
