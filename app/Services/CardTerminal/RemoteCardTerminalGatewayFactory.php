<?php

namespace App\Services\CardTerminal;

use App\Models\RemoteCardTerminal;
use App\Services\CardTerminal\Payrallel\PayrallelGateway;
use Illuminate\Contracts\Container\Container;

/** Picks the gateway for a terminal's provider. One provider today. */
class RemoteCardTerminalGatewayFactory
{
    public function __construct(private readonly Container $container) {}

    public function for(RemoteCardTerminal $terminal): RemoteCardTerminalGateway
    {
        return match ($terminal->provider) {
            RemoteCardTerminal::PROVIDER_PAYRALLEL => $this->container->make(PayrallelGateway::class),
            default => throw CardTerminalException::notConfigured("unknown provider '{$terminal->provider}'"),
        };
    }
}
