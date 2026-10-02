<?php

namespace App\Services\CardTerminal\Payrallel;

use App\Models\RemoteCardTerminal;
use App\Services\CardTerminal\CardTerminalEventLog;
use App\Services\CardTerminal\CardTerminalException;
use App\Services\CardTerminal\RemoteCardTerminalGateway;
use App\Services\CardTerminal\TerminalStatus;
use App\Services\CardTerminal\TerminalTransaction;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Payrallel Remote Terminal API (https://www.payrallel.io/docs/remote-terminal/).
 *
 * Written against the public guide only, which shows representative bodies and
 * no error catalogue, so this reads defensively:
 *  - a call succeeded only on HTTP 2xx AND `success` not false;
 *  - query: only `transaction.status === "approved"` is an approval; an unknown
 *    status stays PROCESSING (see TerminalTransaction);
 *  - "Transaction Not Found" before the tap is normal for card and maps to
 *    NOT_FOUND, not an error.
 *
 * Auth is per terminal: every request carries that terminal's own token in the
 * header shape `payrallel.authorization_format`. Tokens never reach a log or
 * an exception message.
 *
 * Every call is written to the trial timeline (CardTerminalEventLog
 * `provider.http` / `provider.unreachable`) with its raw answer and timing —
 * the public guide shows no error catalogue, so the real bodies are the spec.
 */
class PayrallelGateway implements RemoteCardTerminalGateway
{
    public function __construct(private readonly CardTerminalEventLog $events) {}

    public function requestSale(RemoteCardTerminal $terminal, string $orderId, int $cents): void
    {
        $this->send($terminal, 'terminal/payment-request/sale', $this->amountBody($orderId, $cents));
    }

    public function requestPreauth(RemoteCardTerminal $terminal, string $orderId, int $cents): void
    {
        $this->send($terminal, 'terminal/payment-request/preauth', $this->amountBody($orderId, $cents));
    }

    public function capture(RemoteCardTerminal $terminal, string $orderId, int $cents): void
    {
        $this->send($terminal, 'transactions/card/capture', $this->amountBody($orderId, $cents));
    }

    /**
     * Live test 2026-10-02: a bare {} cancel answered success but the terminal
     * still read in_payment; with the order id and amount it was back to ready
     * within 3 s. So the attempt is named whenever we know it.
     */
    public function cancelActiveRequest(RemoteCardTerminal $terminal, ?string $orderId = null, ?int $cents = null): void
    {
        $this->send($terminal, 'terminal/payment-request/cancel', array_filter([
            'amountInCents' => $cents,
            'customOrderId' => $orderId,
        ], fn ($v) => $v !== null));
    }

    public function void(RemoteCardTerminal $terminal, string $orderId): void
    {
        $this->send($terminal, 'transactions/actions/void', ['customOrderId' => $orderId]);
    }

    public function query(RemoteCardTerminal $terminal, string $orderId): TerminalTransaction
    {
        $response = $this->post($terminal, 'transactions/actions/query', ['customOrderId' => $orderId]);
        $body = (array) ($response->json() ?? []);

        if ($this->isNotFound($response, $body)) {
            return new TerminalTransaction(TerminalTransaction::NOT_FOUND, raw: $body);
        }
        $this->assertOk($response, $body, 'query');

        $txn = (array) ($body['transaction'] ?? []);
        $providerStatus = isset($txn['status']) ? strtolower(trim((string) $txn['status'])) : null;

        return new TerminalTransaction(
            status: match ($providerStatus) {
                'approved' => TerminalTransaction::APPROVED,
                'declined' => TerminalTransaction::DECLINED,
                'voided' => TerminalTransaction::VOIDED,
                default => TerminalTransaction::PROCESSING,
            },
            providerStatus: $providerStatus,
            providerTxnId: isset($txn['transactionId']) ? (string) $txn['transactionId'] : null,
            paymentMethod: isset($txn['paymentMethod']) ? (string) $txn['paymentMethod'] : null,
            raw: $body,
        );
    }

    public function status(RemoteCardTerminal $terminal): TerminalStatus
    {
        $response = $this->post($terminal, 'terminal/status', []);
        $body = (array) ($response->json() ?? []);
        $this->assertOk($response, $body, 'terminal status');

        return new TerminalStatus(
            online: strtolower((string) ($body['status'] ?? '')) === 'online',
            state: isset($body['state']) ? strtolower((string) $body['state']) : null,
            raw: $body,
        );
    }

    /** Integer cents on the wire, as their guide requires. */
    private function amountBody(string $orderId, int $cents): array
    {
        if ($cents <= 0) {
            throw CardTerminalException::notSent("amount must be positive cents, got {$cents}");
        }

        return ['amountInCents' => $cents, 'customOrderId' => $orderId];
    }

    private function send(RemoteCardTerminal $terminal, string $path, array $body): void
    {
        $response = $this->post($terminal, $path, $body);
        $this->assertOk($response, (array) ($response->json() ?? []), $path);
    }

    private function post(RemoteCardTerminal $terminal, string $path, array $body): Response
    {
        $client = $this->client($terminal);
        $orderId = isset($body['customOrderId']) ? (string) $body['customOrderId'] : null;
        $started = hrtime(true);
        try {
            // (object) so an empty body is sent as {} rather than [].
            $response = $client->post($path, (object) $body);
        } catch (ConnectionException $e) {
            $this->events->record('provider.unreachable', [
                'path' => $path, 'request' => $body, 'error' => $e->getMessage(),
            ], $terminal, $orderId, $this->elapsedMs($started), 'warning');
            throw new CardTerminalException("Payrallel unreachable ({$path}): ".$e->getMessage(), 0, $e);
        }

        $this->events->record('provider.http', [
            'path' => $path,
            'request' => $body,
            'http_status' => $response->status(),
            'response' => CardTerminalEventLog::body($response->body()),
        ], $terminal, $orderId, $this->elapsedMs($started), $response->successful() ? 'info' : 'warning');

        return $response;
    }

    private function elapsedMs(int $startedNs): int
    {
        return (int) round((hrtime(true) - $startedNs) / 1_000_000);
    }

    private function client(RemoteCardTerminal $terminal): PendingRequest
    {
        $base = (string) config('payrallel.base_url');
        if ($base === '') {
            throw CardTerminalException::notConfigured('PAYRALLEL_API_BASE_URL');
        }
        $token = (string) $terminal->access_token;
        if ($token === '') {
            throw CardTerminalException::notConfigured("no access token on terminal #{$terminal->id}");
        }

        return Http::baseUrl(rtrim($base, '/').'/')
            ->acceptJson()
            ->asJson()
            // One Content-Type, set through the body format: a second one in
            // withHeaders() was sent as "application/json,application/json;
            // charset=UTF-8" and Payrallel refused it with 415 (live, 2026-10-02).
            ->contentType('application/json; charset=UTF-8')
            ->withHeaders([
                'Authorization' => str_replace('{token}', $token, (string) config('payrallel.authorization_format', 'Bearer {token}')),
            ])
            ->timeout((int) config('payrallel.timeout', 8))
            ->connectTimeout((int) config('payrallel.connect_timeout', 4));
    }

    private function assertOk(Response $response, array $body, string $what): void
    {
        if ($response->successful() && ($body['success'] ?? true) !== false) {
            return;
        }
        $message = $body['message'] ?? $body['error'] ?? $body['errorMessage'] ?? null;
        $text = sprintf(
            'Payrallel %s refused (HTTP %d)%s',
            $what,
            $response->status(),
            is_string($message) && $message !== '' ? ': '.mb_substr($message, 0, 180) : '',
        );
        // A 4xx is an explicit refusal of this request; a 5xx may have been
        // half-processed on their side, so it stays "may have reached".
        throw $response->clientError()
            ? CardTerminalException::notSent($text)
            : new CardTerminalException($text);
    }

    private function isNotFound(Response $response, array $body): bool
    {
        if ($response->status() === 404) {
            return true;
        }
        if (($body['success'] ?? null) !== false) {
            return false;
        }
        $message = strtolower((string) ($body['message'] ?? $body['error'] ?? ''));

        return str_contains($message, 'not found');
    }
}
