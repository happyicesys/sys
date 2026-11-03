<?php

namespace App\Services\CardTerminal;

use App\Services\SmartFreezer\RecognitionVerdict;

/**
 * What a T05 hold is charged once the kiosk session's AI verdict is in (Brian, 2026-10-03).
 *
 * Only a match charges on its own (Brian, 2026-11-03: "once have any dispute or not sure from AI,
 * we should not let AI to finalize the charge"). Everything else is {@see PERSON}: nothing is
 * charged or stored, and the hold waits for a person (CardPaymentService::chargeByPerson):
 *
 *   match                         → the cart total, charged
 *   took_less / mixed / took_more → a person decides; what the AI saw is only a suggestion, in
 *                                   judgedCents + basket (nothing taken = 0)
 *   anything the AI cannot judge  → a person decides, no suggestion: cannot judge, unrecognised,
 *                                   a taken product with no price
 *   no verdict                    → the same, without coming here (Brian 2026-10-10)
 *
 * The suggestion is valued at the price the customer paid for a product in this sale, else the
 * machine's current price for it.
 *
 * Pure: arrays in, decision out, so every case is a unit test.
 */
final class AiCaptureDecision
{
    public const VOID = 'void';

    public const CAPTURE = 'capture';

    /** Nothing is charged and nothing is stored: the hold waits for a person. */
    public const PERSON = 'person';

    /**
     * @param  int  $captureCents  the one capture; 0 when nothing is charged
     * @param  int|null  $fallbackCents  always null here (a person's charge sets its own)
     * @param  int|null  $judgedCents  the amount; on PERSON what the AI's count comes to, null when
     *                                 the AI cannot judge
     * @param  array<int, int>|null  $basket  on PERSON, product id => units behind $judgedCents, so
     *                                        the sale's basket follows if a person charges exactly
     *                                        that (FreezerSaleAudit); null otherwise
     */
    private function __construct(
        public readonly string $action,
        public readonly int $captureCents,
        public readonly ?int $fallbackCents,
        public readonly ?int $judgedCents,
        public readonly string $reason,
        public readonly ?array $basket = null,
    ) {}

    /**
     * @param  int  $holdCents  the approved pre-auth
     * @param  int  $cartCents  what the kiosk settled at door close (≤ the hold)
     * @param  list<array{product_id: int|null, taken: int}>  $lines  RecognitionVerdict lines
     * @param  array<int, int>  $paidPrice  product id => unit cents paid in this sale
     * @param  array<int, int>  $shelfPrice  product id => the machine's unit cents today
     */
    public static function decide(int $holdCents, int $cartCents, ?string $verdict, array $lines, array $paidPrice, array $shelfPrice): self
    {
        $cart = min($cartCents, $holdCents);

        if ($verdict === RecognitionVerdict::MATCH) {
            return new self(self::CAPTURE, $cart, null, $cart, 'AI matched the paid cart');
        }
        if (! in_array($verdict, [RecognitionVerdict::TOOK_LESS, RecognitionVerdict::TOOK_MORE, RecognitionVerdict::MIXED], true)) {
            return self::person("AI could not judge the session ({$verdict})");
        }

        $judged = 0;
        $basket = [];
        foreach ($lines as $line) {
            $taken = (int) ($line['taken'] ?? 0);
            if ($taken <= 0) {
                continue;
            }
            $productId = (int) ($line['product_id'] ?? 0);
            $basket[$productId] = ($basket[$productId] ?? 0) + $taken;
            // A price only counts when it is positive: a 0 or negative TRADE line (a promo, a bug)
            // falls back to the shelf price, and a product with no positive price at all cannot be
            // valued, so there is no suggestion.
            $unit = self::positive($paidPrice[$productId] ?? null) ?? self::positive($shelfPrice[$productId] ?? null);
            if ($productId === 0 || $unit === null) {
                return self::person("AI saw product {$productId} with no price");
            }
            $judged += $taken * $unit;
        }

        return self::person("AI judged {$verdict}", $judged, $basket);
    }

    /**
     * The AI result does not settle this card sale: nothing is charged, a person keys it in.
     *
     * @param  array<int, int>|null  $basket
     */
    public static function person(string $why, ?int $suggestedCents = null, ?array $basket = null): self
    {
        return new self(self::PERSON, 0, null, $suggestedCents, $why, $basket);
    }

    private static function positive(mixed $cents): ?int
    {
        return is_numeric($cents) && (int) $cents > 0 ? (int) $cents : null;
    }

    /** @return array<string, mixed> what is stored on the intent (`ai_decision`) */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'capture_cents' => $this->captureCents,
            'fallback_cents' => $this->fallbackCents,
            'judged_cents' => $this->judgedCents,
            'reason' => $this->reason,
            'basket' => $this->basket,
        ];
    }
}
