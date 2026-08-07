<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Observe-only probe for the Grab-facing delivery routes.
 *
 * Records whether the caller presented a bearer token and whether that token
 * resolves to a Passport client, then ALWAYS passes the request through
 * untouched. It never rejects, never reads the request body beyond one field,
 * never modifies the request or the response, and swallows every one of its
 * own errors - so it cannot break an inbound Grab webhook. If the probe table
 * does not exist yet, the insert throws and is swallowed; the request is still
 * served normally.
 *
 * Purpose: prove (or disprove) that Grab actually attaches the
 * client_credentials token it mints from /oauth/token, before we consider
 * enforcing auth on these routes. Grab refreshes that token roughly weekly,
 * so let this run for at least 8 days before drawing a conclusion.
 *
 * Interpretation caveat: route middleware only runs on a MATCHED route, so a
 * request Grab sends to a wrong path or verb (404/405) is never recorded here.
 * "No rows" means "nothing reached these routes", not "Grab sent nothing".
 *
 * Delete this class, its alias and its table once the question is settled.
 */
class LogDeliveryPlatformAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $this->probe($request);
        } catch (\Throwable $e) {
            // Deliberately swallowed. This middleware must never be able to
            // affect an inbound delivery-platform request.
        }

        return $next($request);
    }

    private function probe(Request $request): void
    {
        $header = $this->authorizationHeader($request);

        $row = [
            'route' => mb_substr($request->path(), 0, 191),
            'method' => $request->method(),
            'has_auth_header' => $header !== null,
            'auth_scheme' => null,
            'token_jti' => null,
            'client_id' => null,
            'token_found' => false,
            'token_revoked' => null,
            'token_expires_at' => null,
            'merchant_id' => $this->merchantId($request),
            'ip' => mb_substr((string) $request->ip(), 0, 45),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255) ?: null,
            'created_at' => now(),
        ];

        if ($header !== null) {
            $parts = explode(' ', trim($header), 2);
            $row['auth_scheme'] = mb_substr($parts[0], 0, 32);

            // Derived from $header rather than $request->bearerToken() so the
            // server-var fallback below is handled on the same code path.
            $jwt = strcasecmp($parts[0], 'Bearer') === 0 ? trim($parts[1] ?? '') : '';

            if ($jwt !== '') {
                $claims = $this->decodeJwtPayload($jwt);

                if (isset($claims['jti'])) {
                    $row['token_jti'] = mb_substr((string) $claims['jti'], 0, 100);
                }

                if (isset($claims['aud'])) {
                    $aud = is_array($claims['aud']) ? ($claims['aud'][0] ?? '') : $claims['aud'];
                    $row['client_id'] = mb_substr((string) $aud, 0, 100);
                }

                if ($row['token_jti'] !== null) {
                    // Best-effort enrichment only. Isolated so that a failure
                    // here can never cost us the probe row itself - those are
                    // exactly the authenticated requests we need to count.
                    try {
                        $token = DB::table('oauth_access_tokens')
                            ->select('revoked', 'expires_at', 'client_id')
                            ->where('id', $row['token_jti'])
                            ->first();

                        if ($token !== null) {
                            $row['token_found'] = true;
                            $row['token_revoked'] = (bool) $token->revoked;
                            $row['token_expires_at'] = $token->expires_at;
                            $row['client_id'] = mb_substr((string) $token->client_id, 0, 100);
                        }
                    } catch (\Throwable $e) {
                        // Leave token_found = false; the row still gets written.
                    }
                }
            }
        }

        DB::table('delivery_platform_auth_probes')->insert($row);
    }

    /**
     * Apache moves the Authorization header into REDIRECT_HTTP_AUTHORIZATION
     * (see public/.htaccess), and Symfony only re-materialises it as a header
     * when the value starts with basic/digest/bearer. A custom scheme would
     * therefore record as "no header at all" - the exact wrong conclusion for
     * the question this probe exists to answer. So fall back to the raw
     * server vars.
     */
    private function authorizationHeader(Request $request): ?string
    {
        $candidates = [
            $request->header('Authorization'),
            $request->server('HTTP_AUTHORIZATION'),
            $request->server('REDIRECT_HTTP_AUTHORIZATION'),
        ];

        foreach ($candidates as $value) {
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Grab sends merchantID at the top level on some calls and nested under
     * "order" on others (see DeliveryPlatformController::createGrabOrder).
     */
    private function merchantId(Request $request): ?string
    {
        $value = $request->input('merchantID') ?? $request->input('order.merchantID');

        if (!is_scalar($value) || $value === '') {
            return null;
        }

        return mb_substr((string) $value, 0, 191);
    }

    private function decodeJwtPayload(string $jwt): array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            return [];
        }

        $payload = base64_decode(strtr($parts[1], '-_', '+/'), false);

        if ($payload === false) {
            return [];
        }

        $claims = json_decode($payload, true);

        return is_array($claims) ? $claims : [];
    }
}
