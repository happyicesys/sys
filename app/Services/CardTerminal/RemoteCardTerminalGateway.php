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

    /**
     * Charge a held pre-authorisation.
     *
     * @return string|null the provider's id of the CHARGE, which a capture creates as its own
     *                     transaction: this, not the hold's id, is what a refund has to name
     */
    public function capture(RemoteCardTerminal $terminal, string $orderId, int $cents): ?string;

    /** Clear the payment screen that is currently waiting for a card; name the attempt when known. */
    public function cancelActiveRequest(RemoteCardTerminal $terminal, ?string $orderId = null, ?int $cents = null): void;

    /** Reverse an approved transaction on its original gateway. */
    public function void(RemoteCardTerminal $terminal, string $orderId): void;

    /**
     * Give $cents of a captured charge back to the card, whole or in part. $transactionId is the
     * charge's id from capture(); $orderId only labels the timeline. The answer's status is
     * APPROVED (refunded), DECLINED (nothing happened) or PROCESSING (outcome not known).
     */
    public function refund(RemoteCardTerminal $terminal, string $transactionId, int $cents, ?string $orderId = null): TerminalTransaction;

    public function query(RemoteCardTerminal $terminal, string $orderId): TerminalTransaction;

    public function status(RemoteCardTerminal $terminal): TerminalStatus;
}
