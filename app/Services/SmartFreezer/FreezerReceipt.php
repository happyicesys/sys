<?php

namespace App\Services\SmartFreezer;

use App\Http\Middleware\VerifyDeviceSignature;
use App\Models\CardPaymentIntent;
use App\Models\Product;
use App\Models\Vend;
use App\Models\VendTransaction;
use App\Services\CardTerminal\CardPaymentService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The live tax invoice a customer opens from the freezer's thank-you QR (Brian, 2026-11-02).
 *
 * A T05 card sale is charged only once Zijia's AI has judged the door session, a minute or two
 * after the lid closes — and the charge can differ from the cart: the customer may have taken more,
 * less, or nothing. So the page follows the money as it is decided:
 *
 *   calculating   — the hold is waiting for the AI (or the sale has not reached mark1 yet)
 *   review        — the AI gave no verdict, or a charge's outcome is uncertain: a person decides
 *   final         — what was charged, itemised, with the extra / not-taken lines badged
 *   nothing       — the AI saw nothing taken: the hold is released, nothing charged
 *   not_completed — the payment never went through
 *
 * A PayNow or NETS sale was charged in full at the machine, so its invoice is final as soon as the
 * sale arrives. Every figure is read from the rows the charge path already writes (the intent, the
 * sale and its `meta_json.audit`), never re-derived, so the invoice and the money always agree.
 *
 * The QR carries a signature the freezer computes offline with its own key ({@see signature()}), so
 * the QR shows at once even with the board's data link down. The first valid open stores it in
 * `freezer_receipts`, and the stored value decides every later open — a key change never breaks a
 * link a customer already holds.
 */
class FreezerReceipt
{
    public const CALCULATING = 'calculating';

    public const REVIEW = 'review';

    public const FINAL = 'final';

    public const NOTHING = 'nothing';

    public const NOT_COMPLETED = 'not_completed';

    /** Hex characters of the HMAC kept in the link (96 bits: short QR, not guessable). */
    public const SIGNATURE_LENGTH = 24;

    /** "SF-<vend code>-<epoch>-<seq>", the freezer's own session ref (the TRADE's SFREF). */
    private const SESSION_REF = '/^SF-(\d+)-(\d{9,11})-\d+$/';

    public function __construct(
        private readonly FreezerSaleLocator $locator,
        private readonly CardPaymentService $cards,
    ) {}

    /**
     * What the freezer signs for its QR. Pinned byte for byte to the APK's `ReceiptLink`.
     */
    public static function signature(Vend $vend, string $sessionRef): string
    {
        $message = "receipt\n".$vend->code."\n".$sessionRef;

        return substr(hash_hmac('sha256', $message, VerifyDeviceSignature::keyFor($vend)), 0, self::SIGNATURE_LENGTH);
    }

    /** The customer-facing link, as the freezer encodes it in the QR. */
    public static function url(Vend $vend, string $sessionRef): string
    {
        return url('/r/'.$vend->code.'/'.$sessionRef).'?s='.self::signature($vend, $sessionRef);
    }

    /** A session ref this freezer could have issued. */
    public static function isSessionRefOf(Vend $vend, string $sessionRef): bool
    {
        return preg_match(self::SESSION_REF, $sessionRef, $m) === 1 && $m[1] === (string) $vend->code;
    }

    /**
     * Is $signature this session's link? The first valid open is remembered and decides from then
     * on. Counting is for opens of the page, not for its own refresh calls.
     */
    public function verify(Vend $vend, string $sessionRef, string $signature, bool $countOpen): bool
    {
        if ($signature === '' || ! self::isSessionRefOf($vend, $sessionRef)) {
            return false;
        }

        $stored = DB::table('freezer_receipts')
            ->where('vend_id', $vend->id)
            ->where('session_ref', $sessionRef)
            ->value('signature');

        if ($stored !== null) {
            $valid = hash_equals((string) $stored, strtolower($signature));
        } else {
            $valid = hash_equals(self::signature($vend, $sessionRef), strtolower($signature));
            if ($valid) {
                DB::table('freezer_receipts')->insertOrIgnore([
                    'vend_id' => $vend->id,
                    'session_ref' => $sessionRef,
                    'signature' => strtolower($signature),
                    'open_count' => 0,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }
        }

        if ($valid && $countOpen) {
            DB::table('freezer_receipts')
                ->where('vend_id', $vend->id)
                ->where('session_ref', $sessionRef)
                ->update([
                    'open_count' => DB::raw('open_count + 1'),
                    'first_opened_at' => DB::raw('COALESCE(first_opened_at, NOW())'),
                    'last_opened_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
        }

        return $valid;
    }

    /**
     * Everything the page shows, as plain data (money in integer cents).
     *
     * @return array<string, mixed>
     */
    public function state(Vend $vend, string $sessionRef): array
    {
        $intent = CardPaymentIntent::query()
            ->where('vend_id', $vend->id)
            ->where('session_ref', $sessionRef)
            ->orderByDesc('id')
            ->first();
        $sale = $this->locator->find($vend, $sessionRef, $intent?->created_at ?? Carbon::now());

        $status = $intent !== null ? $this->cardStatus($intent, $sale) : ($sale !== null ? self::FINAL : self::CALCULATING);

        $state = [
            'status' => $status,
            'session_ref' => $sessionRef,
            'machine' => (string) $vend->code,
            'company' => $this->company($vend),
            'invoice_no' => $sale?->order_id ?: $sessionRef,
            'issued_at' => $this->issuedAt($sale, $intent, $sessionRef)?->format('d M Y, g:i A'),
            'payment' => $this->payment($sale, $intent),
            'is_card_hold' => $intent !== null,
            'hold_cents' => $intent !== null ? (int) $intent->amount_cents : null,
            'lines' => [],
            'total_cents' => null,
            'gst_rate' => null,
            'gst_cents' => null,
            'subtotal_cents' => null,
            'refunded' => false,
        ];

        if ($status !== self::FINAL) {
            return $state;
        }

        $total = $intent !== null ? (int) $intent->captured_cents : (int) $sale?->amount;
        $rate = (float) ($sale?->gst_vat_rate ?? $vend->operator?->gst_vat_rate ?? 0);
        $lines = $sale !== null ? $this->lines($sale) : [];
        $adjustment = $total - array_sum(array_column($lines, 'amount_cents'));
        if ($lines !== [] && $adjustment !== 0) {
            // The charge and the basket only part when Payrallel refused to go above the hold
            // (the hold was charged instead) — the invoice states what was actually charged.
            $lines[] = ['name' => 'Adjustment', 'qty' => null, 'unit_cents' => null, 'amount_cents' => $adjustment, 'badge' => null];
        }
        $excluding = $rate > 0 ? (int) round($total * 100 / (100 + $rate)) : $total;

        return array_merge($state, [
            'lines' => $lines,
            'total_cents' => $total,
            'gst_rate' => $rate,
            'gst_cents' => $total - $excluding,
            'subtotal_cents' => $excluding,
            'refunded' => $sale !== null && ((bool) $sale->is_refunded
                || (int) $sale->settlement_status === VendTransaction::SETTLEMENT_REFUNDED),
        ]);
    }

    /** Where a T05 hold stands, in the page's words. */
    private function cardStatus(CardPaymentIntent $intent, ?VendTransaction $sale): string
    {
        $decision = (array) $intent->ai_decision;

        return match ($intent->state) {
            // A captured charge whose sale is still on hold settles within the minute sweep.
            CardPaymentIntent::STATE_CAPTURED => $sale !== null && $sale->audit_pending_at !== null
                ? self::CALCULATING
                : self::FINAL,
            CardPaymentIntent::STATE_VOIDED => $intent->approved_at !== null ? self::NOTHING : self::NOT_COMPLETED,
            CardPaymentIntent::STATE_DECLINED,
            CardPaymentIntent::STATE_CANCELLING,
            CardPaymentIntent::STATE_CANCELLED,
            CardPaymentIntent::STATE_ERROR => self::NOT_COMPLETED,
            CardPaymentIntent::STATE_VOID_FAILED => self::REVIEW,
            default => isset($decision['uncertain']) || $this->cards->personNeededBecause($intent) !== null
                ? self::REVIEW
                : self::CALCULATING,
        };
    }

    /**
     * The invoice lines: what was charged per product and unit price, plus a zero line for each
     * paid unit that was not taken. Badges come from the audit's two baskets — set only when the
     * AI changed the cart.
     *
     * @return list<array{name: string, qty: int|null, unit_cents: int|null, amount_cents: int, badge: string|null}>
     */
    private function lines(VendTransaction $sale): array
    {
        $groups = [];
        $items = $sale->is_multiple ? $sale->vendTransactionItems()->get() : collect();
        if ($items->isEmpty()) {
            $qty = max(1, (int) $sale->qty);
            $groups[] = ['product_id' => (int) $sale->product_id, 'qty' => $qty, 'unit_cents' => intdiv((int) $sale->amount, $qty)];
        } else {
            foreach ($items as $item) {
                $key = $item->product_id.':'.(int) $item->unit_price_amount;
                $groups[$key] ??= ['product_id' => (int) $item->product_id, 'qty' => 0, 'unit_cents' => (int) $item->unit_price_amount];
                $groups[$key]['qty']++;
            }
            $groups = array_values($groups);
        }

        $audit = (array) (((array) $sale->meta_json)['audit'] ?? []);
        $paid = array_map('intval', (array) ($audit['basket_paid'] ?? []));
        $charged = array_map('intval', (array) ($audit['basket_charged'] ?? []));
        $audited = $paid !== [] && $charged !== [];

        $ids = array_unique(array_merge(array_column($groups, 'product_id'), array_keys($paid)));
        $names = Product::withoutGlobalScopes()->whereIn('id', $ids)->pluck('name', 'id');
        $name = fn (int $id) => (string) ($names[$id] ?? 'Item');

        $lines = [];
        foreach ($groups as $g) {
            $extra = $audited ? max(0, ($charged[$g['product_id']] ?? 0) - ($paid[$g['product_id']] ?? 0)) : 0;
            $lines[] = [
                'name' => $name($g['product_id']),
                'qty' => $g['qty'],
                'unit_cents' => $g['unit_cents'],
                'amount_cents' => $g['qty'] * $g['unit_cents'],
                'badge' => $extra > 0 ? ($extra === $g['qty'] ? 'Taken extra' : "{$extra} taken extra") : null,
            ];
        }

        if ($audited) {
            foreach ($paid as $productId => $units) {
                $notTaken = $units - ($charged[$productId] ?? 0);
                if ($notTaken > 0) {
                    $lines[] = [
                        'name' => $name((int) $productId),
                        'qty' => $notTaken,
                        'unit_cents' => 0,
                        'amount_cents' => 0,
                        'badge' => 'Not taken – not charged',
                    ];
                }
            }
        }

        return $lines;
    }

    /** @return array{name: string|null, uen: string|null, gst_reg_no: string|null, address: string|null, phone: string|null, email: string|null} */
    private function company(Vend $vend): array
    {
        $profile = $vend->operator?->profile()->with(['address', 'contact'])->first();

        return [
            'name' => $profile?->name,
            'uen' => $profile?->uen,
            'gst_reg_no' => $profile?->gst_reg_no,
            'address' => $profile?->address?->full_address,
            'phone' => $profile?->contact?->phone_num ? '+65 '.$profile->contact->phone_num : null,
            'email' => $profile?->contact?->email,
        ];
    }

    private function issuedAt(?VendTransaction $sale, ?CardPaymentIntent $intent, string $sessionRef): ?Carbon
    {
        if ($sale?->transaction_datetime !== null) {
            return Carbon::parse($sale->transaction_datetime);
        }
        if ($intent?->approved_at !== null) {
            return $intent->approved_at;
        }

        return preg_match(self::SESSION_REF, $sessionRef, $m) === 1
            ? Carbon::createFromTimestamp((int) $m[2])->setTimezone(config('app.timezone'))
            : null;
    }

    /** "Visa •••• 8256" for a T05 card, else the sale's payment method. */
    private function payment(?VendTransaction $sale, ?CardPaymentIntent $intent): ?string
    {
        if ($intent !== null) {
            $brand = ucfirst((string) ($intent->payment_method ?: 'card'));
            $pan = (string) data_get($intent->last_response, 'transaction.cardinfo.maskedPan', '');

            return strlen($pan) >= 4 ? $brand.' •••• '.substr($pan, -4) : $brand;
        }

        return $sale?->paymentMethod?->name;
    }
}
