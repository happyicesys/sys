<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyGrabWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\ResourceServer;
use Mockery;
use Psr\Http\Message\ServerRequestInterface;
use Tests\TestCase;

/**
 * Grab's webhooks carry a Passport client-credentials token (every one of ~600k
 * calls, 2026-08-07 → 10-29, from client 3 or 4). VerifyGrabWebhook checks it;
 * config grab.webhook_auth decides whether a failure is only logged or refused.
 */
class GrabWebhookAuthTest extends TestCase
{
    use RefreshDatabase;

    private function serverAccepting(?string $clientId): void
    {
        $server = Mockery::mock(ResourceServer::class);
        $expectation = $server->shouldReceive('validateAuthenticatedRequest');

        $clientId === null
            ? $expectation->andThrow(OAuthServerException::accessDenied('bad token'))
            : $expectation->andReturnUsing(fn (ServerRequestInterface $psr) => $psr->withAttribute('oauth_client_id', $clientId));

        $this->app->instance(ResourceServer::class, $server);
    }

    private function through(array $headers = []): int
    {
        $request = Request::create('/api/delivery/grab/order/create', 'POST', ['merchantID' => '4-X']);
        foreach ($headers as $name => $value) {
            $request->headers->set($name, $value);
        }

        return (new VerifyGrabWebhook)->handle($request, fn () => response('passed', 200))->getStatusCode();
    }

    public function test_enforce_lets_a_grab_client_token_through(): void
    {
        config(['grab.webhook_auth' => 'enforce', 'grab.webhook_client_ids' => ['3', '4']]);
        $this->serverAccepting('4');

        $this->assertSame(200, $this->through(['Authorization' => 'Bearer good']));
    }

    public function test_enforce_refuses_no_token_a_bad_token_and_another_clients_token(): void
    {
        config(['grab.webhook_auth' => 'enforce', 'grab.webhook_client_ids' => ['3', '4']]);

        $this->assertSame(401, $this->through());

        $this->serverAccepting(null);
        $this->assertSame(401, $this->through(['Authorization' => 'Bearer bad']));

        $this->serverAccepting('7');
        $this->assertSame(401, $this->through(['Authorization' => 'Bearer other']));
    }

    public function test_log_mode_lets_everything_through_and_logs_what_it_would_refuse(): void
    {
        config(['grab.webhook_auth' => 'log']);
        Log::spy();

        $this->assertSame(200, $this->through());

        Log::shouldHaveReceived('warning')->withArgs(fn ($message, $context) => $message === 'Grab webhook auth would refuse' && $context['reason'] === 'no bearer token');
    }

    public function test_the_routes_carry_it(): void
    {
        $route = collect(app('router')->getRoutes())->first(fn ($r) => $r->uri() === 'api/delivery/grab/order/create');

        $this->assertContains('grab.webhook', $route->gatherMiddleware());
        $this->assertNull(collect(app('router')->getRoutes())->first(fn ($r) => str_starts_with($r->uri(), 'api/delivery/grab/oauth')), 'the public token-refresh route is gone');
    }
}
