<?php

namespace App\Services\CardTerminal;

use App\Services\SmartFreezer\RecognitionVerdict;

/**
 * What a T05 hold is charged once the kiosk session's AI verdict is in (Brian, 2026-10-03).
 *
 * Always ONE capture (Brian, 2026-10-04, matching Payrallel: "you only need to do capture, as
 * auth_incr is done on our backend"). The hold is the checkout amount; a capture above it is
 * allowed, and Payrallel raises the authorisation itself. So:
 *
 *   match                         → the cart total
 *   unrecognised                  → the cart total (the AI named a product we do not know)
 *   incomplete                    → the middle ground (2026-10-05): products the AI was asked about
 *                                   count as it saw them; a paid product with no barcode (never in
 *                                   goodsList, so its "not taken" means nothing) counts as taken
 *   took_less / mixed / took_more → what the AI saw, valued: nothing → release the hold;
 *                                   otherwise that amount in one capture, above the hold
 *                                   included — with the hold as the fallback if Payrallel
 *                                   refuses the increase (the rest is then recorded as owed)
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
     * @param  int  $captureCents  the one capture; 0 when the hold is released
     * @param  int|null  $fallbackCents  above the hold: what to capture instead if Payrallel refuses
     *                                   the increase (the hold itself); null otherwise
     * @param  int|null  $judgedCents  the AI basket's value; null when the cart total stands in for it
     */
    private function __construct(
        public readonly string $action,
        public readonly int $captureCents,
        public readonly ?int $fallbackCents,
        public readonly ?int $judgedCents,
        public readonly string $reason,
    ) {}

    /** One capture of $cents, within the hold. */
    private static function capture(int $cents, ?int $judged, string $reason): self
    {
        return new self(self::CAPTURE, $cents, null, $judged, $reason);
    }

    /**
     * @param  int  $holdCents  the approved pre-auth (Payrallel raises it for a larger capture)
     * @param  int  $cartCents  what the kiosk settled at door close (≤ the hold)
     * @param  list<array{product_id: int|null, taken: int}>  $lines  RecognitionVerdict lines
     * @param  array<int, int>  $paidPrice  product id => unit cents paid in this sale
     * @param  array<int, int>  $shelfPrice  product id => the machine's unit cents today
     */
    public static function decide(int $holdCents, int $cartCents, ?string $verdict, array $lines, array $paidPrice, array $shelfPrice): self
    {
        $cart = min($cartCents, $holdCents);

        if ($verdict === RecognitionVerdict::MATCH) {
            return self::capture($cart, $cart, 'AI matched the paid cart');
        }
        $incomplete = $verdict === RecognitionVerdict::INCOMPLETE;
        if (! $incomplete && ! in_array($verdict, [RecognitionVerdict::TOOK_LESS, RecognitionVerdict::TOOK_MORE, RecognitionVerdict::MIXED], true)) {
            return self::capture($cart, null, "AI could not judge the session ({$verdict}): cart total");
        }

        $judged = 0;
        $units = 0;
        $assumed = 0;
        foreach ($lines as $line) {
            $taken = (int) ($line['taken'] ?? 0);
            // Incomplete (Brian, 2026-10-05, the middle ground): a paid product the AI was never
            // asked about — no barcode, so no `code` on its line — is charged as paid, as if taken.
            // Its "0 taken" is not evidence. Products the AI WAS asked about count as it saw them.
            if ($incomplete && ($line['product_id'] ?? null) !== null && ($line['code'] ?? null) === null) {
                $paid = (int) ($line['paid'] ?? 0);
                $assumed += max(0, $paid - $taken);
                $taken = max($taken, $paid);
            }
            if ($taken <= 0) {
                continue;
            }
            $units += $taken;
            $productId = (int) ($line['product_id'] ?? 0);
            // A price only counts when it is positive: a 0 or negative TRADE line (a promo, a bug)
            // falls back to the shelf price, and a product with no positive price at all cannot be
            // valued — the cart is charged rather than guessing low.
            $unit = self::positive($paidPrice[$productId] ?? null) ?? self::positive($shelfPrice[$productId] ?? null);
            if ($productId === 0 || $unit === null) {
                return self::capture($cart, null, "AI saw product {$productId} with no price: cart total");
            }
            $judged += $taken * $unit;
        }

        // Incomplete never releases a hold: something was paid for that the AI could not see, so
        // with nothing left to value (lines missing or malformed) the cart stands.
        if ($incomplete && $units === 0) {
            return self::capture($cart, null, 'AI could not judge the session (incomplete, nothing to value): cart total');
        }
        // Released only when the AI saw no unit taken — never because something valued at 0.
        if ($units === 0) {
            return new self(self::VOID, 0, null, 0, 'AI saw nothing taken: hold released');
        }
        $how = $assumed > 0
            ? "AI judged {$verdict}: what it saw, plus {$assumed} unit(s) it was not asked about charged as paid"
            : "AI judged {$verdict}: charged what was taken";
        if ($judged <= $holdCents) {
            return self::capture($judged, $judged, $how);
        }

        // Above the hold: one capture of the whole judged amount; Payrallel increments the
        // authorisation on their side. If they refuse, the hold itself is captured instead.
        return new self(self::CAPTURE, $judged, $holdCents, $judged, "{$how} — above the hold: one capture with auth increment");
    }

    /** The cart total, when the AI result cannot be trusted to decide this card sale. */
    public static function undecidable(int $holdCents, int $cartCents, string $why): self
    {
        return self::capture(min($cartCents, $holdCents), null, "{$why}: cart total");
    }

    private static function positive(mixed $cents): ?int
    {
        return is_numeric($cents) && (int) $cents > 0 ? (int) $cents : null;
    }

    /** The cart total, when no verdict came before the hold could expire. */
    public static function backstop(int $holdCents, int $cartCents, int $hours): self
    {
        $cart = min($cartCents, $holdCents);

        return self::capture($cart, null, "no AI verdict within {$hours} h: cart total");
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
        ];
    }
}
