<?php

namespace Tests\Support;

use App\Models\RemoteCardTerminal;
use App\Services\CardTerminal\CardTerminalException;
use App\Services\CardTerminal\RemoteCardTerminalGateway;
use App\Services\CardTerminal\TerminalStatus;
use App\Services\CardTerminal\TerminalTransaction;

/**
 * Scriptable stand-in for a remote-terminal provider: records every call and
 * answers query() from a per-order queue (the last answer repeats).
 */
class FakeRemoteCardTerminalGateway implements RemoteCardTerminalGateway
{
    /** @var list<array{0:string,1:?string,2:?int}> */
    public array $calls = [];

    /** @var array<string, list<TerminalTransaction>> */
    public array $answers = [];

    public ?CardTerminalException $failSend = null;

    public ?CardTerminalException $failVoid = null;

    public TerminalStatus $status;

    public function __construct()
    {
        $this->status = new TerminalStatus(true, TerminalStatus::STATE_READY);
    }

    public function answer(string $orderId, string ...$statuses): void
    {
        foreach ($statuses as $s) {
            $this->answers[$orderId][] = new TerminalTransaction($s, $s, 'TXN-'.$orderId, 'visa');
        }
    }

    public function callsOf(string $method): array
    {
        return array_values(array_filter($this->calls, fn ($c) => $c[0] === $method));
    }

    public function requestSale(RemoteCardTerminal $terminal, string $orderId, int $cents): void
    {
        $this->calls[] = ['sale', $orderId, $cents];
        if ($this->failSend) {
            throw $this->failSend;
        }
    }

    public function requestPreauth(RemoteCardTerminal $terminal, string $orderId, int $cents): void
    {
        $this->calls[] = ['preauth', $orderId, $cents];
        if ($this->failSend) {
            throw $this->failSend;
        }
    }

    public function capture(RemoteCardTerminal $terminal, string $orderId, int $cents): void
    {
        $this->calls[] = ['capture', $orderId, $cents];
    }

    public function cancelActiveRequest(RemoteCardTerminal $terminal): void
    {
        $this->calls[] = ['cancel', null, null];
    }

    public function void(RemoteCardTerminal $terminal, string $orderId): void
    {
        $this->calls[] = ['void', $orderId, null];
        if ($this->failVoid) {
            throw $this->failVoid;
        }
    }

    public function query(RemoteCardTerminal $terminal, string $orderId): TerminalTransaction
    {
        $this->calls[] = ['query', $orderId, null];
        $queue = $this->answers[$orderId] ?? [];
        if ($queue === []) {
            return new TerminalTransaction(TerminalTransaction::NOT_FOUND);
        }

        return count($queue) > 1 ? array_shift($this->answers[$orderId]) : $queue[0];
    }

    public function status(RemoteCardTerminal $terminal): TerminalStatus
    {
        $this->calls[] = ['status', null, null];

        return $this->status;
    }
}
