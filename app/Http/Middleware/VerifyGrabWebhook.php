<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Response;

/**
 * Grab's webhooks authenticate with a Passport client-credentials token mark1 issued
 * it (Grab fetches one from our /oauth/token and sends it as a Bearer). The token has
 * no user, so `auth:api` cannot check it: this validates the token itself and that it
 * belongs to one of Grab's clients (config grab.webhook_client_ids).
 *
 * config grab.webhook_auth: off | log (record what enforce would refuse, let it in) |
 * enforce (401 - Grab fetches a fresh token on a 401 and retries).
 */
class VerifyGrabWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $mode = config('grab.webhook_auth', 'log');

        if ($mode === 'off') {
            return $next($request);
        }

        $refusal = $this->refusal($request);

        if ($refusal === null) {
            return $next($request);
        }

        Log::warning('Grab webhook auth '.($mode === 'enforce' ? 'refused' : 'would refuse'), [
            'reason' => $refusal,
            'route' => $request->path(),
            'ip' => $request->ip(),
            'merchant_id' => $request->input('merchantID') ?? $request->input('order.merchantID'),
        ]);

        if ($mode !== 'enforce') {
            return $next($request);
        }

        return response()->json(['error_code' => 401, 'error_message' => 'Unauthenticated.'], 401);
    }

    /** null when the request carries a valid token of a Grab client, else why not. */
    private function refusal(Request $request): ?string
    {
        if (! $request->bearerToken()) {
            return 'no bearer token';
        }

        $psrFactory = new Psr17Factory;
        $psr = (new PsrHttpFactory($psrFactory, $psrFactory, $psrFactory, $psrFactory))->createRequest($request);

        try {
            // Resolved here, not injected: building it reads Passport's key, which an
            // 'off' route must never need.
            $psr = app(ResourceServer::class)->validateAuthenticatedRequest($psr);
        } catch (OAuthServerException $e) {
            return 'invalid token: '.$e->getMessage();
        } catch (\LogicException $e) {
            return 'token check unavailable: '.$e->getMessage();
        }

        $clientId = (string) $psr->getAttribute('oauth_client_id');
        $allowed = array_map('strval', (array) config('grab.webhook_client_ids', []));

        if (! in_array($clientId, $allowed, true)) {
            return "token of client {$clientId}, not a Grab client";
        }

        return null;
    }
}
