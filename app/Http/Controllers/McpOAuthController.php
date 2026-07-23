<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\ClientRepository;

/**
 * OAuth 2.0 support for the MCP connector (Claude "Add custom connector").
 *
 * Strictly ADDITIVE to the existing Passport install: machine/APK personal
 * access tokens and every existing oauth route are untouched. This controller
 * only publishes RFC 8414 discovery metadata and an RFC 7591 Dynamic Client
 * Registration endpoint that creates PUBLIC (secret-less, PKCE-required)
 * clients limited to an allow-listed set of redirect hosts.
 *
 * The real data-access gate stays at the MCP resource server (mcp/server.py):
 * an OAuth token is only honoured for users holding an active (non-revoked)
 * row in mcp_access_tokens — i.e. the Admin ▸ MCP Access page remains the
 * single on/off switch per person, for BOTH auth methods.
 */
class McpOAuthController extends Controller
{
    private const CORS_HEADERS = [
        'Access-Control-Allow-Origin'  => '*',
        'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
        'Access-Control-Allow-Headers' => 'Content-Type, Authorization, mcp-protocol-version',
        'Access-Control-Max-Age'       => '86400',
    ];

    /**
     * RFC 8414 Authorization Server Metadata. Served at
     * /.well-known/oauth-authorization-server (and the openid-configuration
     * alias some clients probe first).
     */
    public function authorizationServerMetadata()
    {
        $base = rtrim(config('app.url'), '/');

        return response()->json([
            'issuer'                                => $base,
            'authorization_endpoint'                => $base.'/oauth/authorize',
            'token_endpoint'                        => $base.'/oauth/token',
            'registration_endpoint'                 => $base.'/api/oauth/register',
            'response_types_supported'              => ['code'],
            'grant_types_supported'                 => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported'      => ['S256'],
            'token_endpoint_auth_methods_supported' => ['none'],
            'scopes_supported'                      => ['mcp'],
        ], 200, self::CORS_HEADERS + ['Cache-Control' => 'public, max-age=3600']);
    }

    /**
     * RFC 7591 Dynamic Client Registration. Open (Claude registers without
     * credentials, per spec) but constrained: throttled at the route, PUBLIC
     * clients only (no secret, PKCE enforced by the oauth server for public
     * clients), and redirect URIs must be loopback or on an allow-listed host.
     */
    public function register(Request $request)
    {
        $uris = $request->input('redirect_uris');

        if (!is_array($uris) || empty($uris)) {
            return $this->dcrError('invalid_client_metadata', 'redirect_uris (non-empty array) is required.');
        }

        foreach ($uris as $uri) {
            if (!is_string($uri) || !$this->isAllowedRedirect($uri)) {
                // Log the rejected URI so the allow-list can be extended via
                // MCP_OAUTH_REDIRECT_HOSTS if a legitimate client changes hosts.
                Log::warning('MCP DCR rejected redirect_uri', ['redirect_uris' => $uris]);

                return $this->dcrError('invalid_redirect_uri', 'redirect_uri host is not allowed: '.(is_string($uri) ? $uri : '(non-string)'));
            }
        }

        $name = substr((string) $request->input('client_name', 'MCP Client'), 0, 191);

        // Public client: secret NULL → league/oauth2-server requires PKCE.
        $client = app(ClientRepository::class)->create(
            null, $name, implode(',', $uris), null, false, false, false
        );

        return response()->json([
            'client_id'                  => (string) $client->id,
            'client_id_issued_at'        => $client->created_at?->getTimestamp() ?? time(),
            'client_name'                => $name,
            'redirect_uris'              => array_values($uris),
            'grant_types'                => ['authorization_code', 'refresh_token'],
            'response_types'             => ['code'],
            'token_endpoint_auth_method' => 'none',
            'scope'                      => 'mcp',
        ], 201, self::CORS_HEADERS + ['Cache-Control' => 'no-store']);
    }

    /** CORS preflight for the registration endpoint. */
    public function preflight()
    {
        return response()->noContent(204)->withHeaders(self::CORS_HEADERS);
    }

    private function dcrError(string $code, string $description)
    {
        return response()->json([
            'error'             => $code,
            'error_description' => $description,
        ], 400, self::CORS_HEADERS);
    }

    private function isAllowedRedirect(string $uri): bool
    {
        $parts = parse_url($uri);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return false;
        }

        $scheme = strtolower($parts['scheme']);
        $host   = strtolower($parts['host']);

        // Loopback redirects (mcp-remote's OAuth mode) may be plain http.
        if (in_array($host, ['localhost', '127.0.0.1', '[::1]'], true)) {
            return in_array($scheme, ['http', 'https'], true);
        }

        if ($scheme !== 'https') {
            return false;
        }

        $allowed = array_filter(array_map('trim', explode(',', (string) config('services.mcp_oauth.redirect_hosts'))));

        foreach ($allowed as $domain) {
            $domain = strtolower($domain);
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }
}
