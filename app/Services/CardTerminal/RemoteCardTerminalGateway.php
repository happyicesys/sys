<?php

namespace App\Services\CardTerminal;

use App\Models\RemoteCardTerminal;

/**
 * One provider's cloud API for commanding a physical card terminal.
 *
 * Every method either completes or throws CardTerminalException; none of them
 * decides what the answer means for a sale — that is CardPaymentService's job,
 * so a second provider only has to translate its wire format.
 *
 * Amounts are integer cents. $orderId is our custom order id, unique per attempt.
 */
interface RemoteCardTerminalGateway
{
    /** Ask the terminal to take $cents now. Accepted ≠ delivered: poll query(). */
    public function requestSale(RemoteCardTerminal $terminal, string $orderId, int $cents): void;

    /** Ask the terminal to hold $cents; charge later with capture(). */
    public function requestPreauth(RemoteCardTerminal $terminal, string $orderId, int $cents): void;

    /** Charge a held pre-authorisation. */
    public function capture(RemoteCardTerminal $terminal, string $orderId, int $cents): void;

    /** Clear the payment screen that is currently waiting for a card; name the attempt when known. */
    public function cancelActiveRequest(RemoteCardTerminal $terminal, ?string $orderId = null, ?int $cents = null): void;

    /** Reverse an approved transaction on its original gateway. */
    public function void(RemoteCardTerminal $terminal, string $orderId): void;

    public function query(RemoteCardTerminal $terminal, string $orderId): TerminalTransaction;

    public function status(RemoteCardTerminal $terminal): TerminalStatus;
}
