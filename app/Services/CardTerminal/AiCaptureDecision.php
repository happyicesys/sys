<?php

namespace App\Services\CardTerminal;

use App\Services\SmartFreezer\RecognitionVerdict;

/**
 * What a T05 hold is charged once the kiosk session's AI verdict is in (Brian, 2026-10-03).
 *
 * The hold is the checkout amount and it is the ceiling: one capture can take less, never
 * more. So:
 *
 *   match                         → the cart total
 *   unrecognised / incomplete     → the cart total (the AI cannot tell, the customer agreed it)
 *   took_less / mixed / took_more → what the AI saw, valued: nothing → release the hold;
 *                                   less than the hold → that amount; more → the whole hold,
 *                                   and the rest is recorded as owed (no API takes it today)
 *   backstop (no verdict in time) → the cart total
 *
 * Valued at the price the customer paid for a product in this sale, else the machine's
 * current price for it. A product seen with no price at all cannot be valued, so the cart
 * total is charged — under-charging on a guess is not allowed.
 *
 * Pure: arrays in, decision out, so every case is a unit test.
 */
final class AiCaptureDecision
{
    public const VOID = 'void';

    public const CAPTURE = 'capture';

    /**
     * @param  int  $captureCents  0 when the hold is released
     * @param  int|null  $judgedCents  the AI basket's value; null when the cart total stands in for it
     * @param  int  $owedCents  judged beyond the hold, which no capture can take
     */
    private function __construct(
        public readonly string $action,
        public readonly int $captureCents,
        public readonly ?int $judgedCents,
        public readonly int $owedCents,
        public readonly string $reason,
    ) {}

    /**
     * @param  int  $holdCents  the approved pre-auth, the most any capture can take
     * @param  int  $cartCents  what the kiosk settled at door close (≤ the hold)
     * @param  list<array{product_id: int|null, taken: int}>  $lines  RecognitionVerdict lines
     * @param  array<int, int>  $paidPrice  product id => unit cents paid in this sale
     * @param  array<int, int>  $shelfPrice  product id => the machine's unit cents today
     */
    public static function decide(int $holdCents, int $cartCents, ?string $verdict, array $lines, array $paidPrice, array $shelfPrice): self
    {
        $cart = min($cartCents, $holdCents);

        if ($verdict === RecognitionVerdict::MATCH) {
            return new self(self::CAPTURE, $cart, $cart, 0, 'AI matched the paid cart');
        }
        if (! in_array($verdict, [RecognitionVerdict::TOOK_LESS, RecognitionVerdict::TOOK_MORE, RecognitionVerdict::MIXED], true)) {
            return new self(self::CAPTURE, $cart, null, 0, "AI could not judge the session ({$verdict}): cart total");
        }

        $judged = 0;
        foreach ($lines as $line) {
            $taken = (int) ($line['taken'] ?? 0);
            if ($taken <= 0) {
                continue;
            }
            $productId = (int) ($line['product_id'] ?? 0);
            $unit = $paidPrice[$productId] ?? $shelfPrice[$productId] ?? null;
            if ($productId === 0 || $unit === null) {
                return new self(self::CAPTURE, $cart, null, 0, "AI saw product {$productId} with no price: cart total");
            }
            $judged += $taken * $unit;
        }

        if ($judged === 0) {
            return new self(self::VOID, 0, 0, 0, 'AI saw nothing taken: hold released');
        }
        if ($judged <= $holdCents) {
            return new self(self::CAPTURE, $judged, $judged, 0, "AI judged {$verdict}: charged what was taken");
        }

        return new self(self::CAPTURE, $holdCents, $judged, $judged - $holdCents,
            "AI judged {$verdict} above the hold: hold charged in full, rest owed");
    }

    /** The cart total, when no verdict came before the hold could expire. */
    public static function backstop(int $holdCents, int $cartCents, int $hours): self
    {
        $cart = min($cartCents, $holdCents);

        return new self(self::CAPTURE, $cart, null, 0, "no AI verdict within {$hours} h: cart total");
    }

    /** @return array<string, mixed> what is stored on the intent (`ai_decision`) */
    public function toArray(): array
    {
        return [
            'action' => $this->action,
            'capture_cents' => $this->captureCents,
            'judged_cents' => $this->judgedCents,
            'owed_cents' => $this->owedCents,
            'reason' => $this->reason,
        ];
    }
}
