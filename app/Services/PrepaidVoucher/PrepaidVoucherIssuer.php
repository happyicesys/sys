<?php

namespace App\Services\PrepaidVoucher;

use App\Models\PrepaidVoucher;
use App\Models\Vend;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Writes a bought voucher — once per (machine, order), whichever path gets there first: the QR webhook
 * (the money is in: the voucher must exist even if the machine dies now) or the purchase TRADE (the card
 * rail's only path). Money was taken by then, so it never refuses: a missing campaign or an odd number is
 * logged and the voucher is still written.
 */
final class PrepaidVoucherIssuer
{
    public function __construct(private readonly PrepaidVoucherProgram $program) {}

    /**
     * @param  array{order_id: string, phone: ?string, product_id: int, list_price_cents: int, price_paid_cents: int,
     *               pay_rail: string, first_order_id?: ?string, vend_transaction_id?: ?int, payment_gateway_log_id?: ?int,
     *               issued_at?: ?\Carbon\CarbonInterface}  $data
     */
    public function issue(Vend $vend, array $data): ?PrepaidVoucher
    {
        $orderId = trim((string) ($data['order_id'] ?? ''));
        $productId = (int) ($data['product_id'] ?? 0);
        if ($orderId === '' || $productId <= 0) {
            Log::warning('prepaid voucher: purchase without order or product, not issued', ['vend' => $vend->code, 'data' => self::redact($data)]);

            return null;
        }

        $existing = $this->find($vend, $orderId);
        if ($existing) {
            return $this->fillLinks($existing, $data);
        }

        $campaign = $this->program->campaignFor($vend);
        $locale = $this->program->locale($vend);
        $phone = PrepaidVoucherPhone::normalise($data['phone'] ?? null, $locale['phone_code']);
        if ($phone === null) {
            // The machine validates the number before taking payment; keep what was typed rather than lose a paid voucher.
            $digits = preg_replace('/\D/', '', (string) ($data['phone'] ?? ''));
            $phone = $digits !== '' ? '+'.substr($digits, 0, 18) : null;
            Log::warning('prepaid voucher: number failed the country rule, kept as typed', ['vend' => $vend->code, 'order' => $orderId]);
        }
        $issuedAt = isset($data['issued_at']) ? Carbon::instance($data['issued_at']) : Carbon::now();
        $listPrice = max(0, (int) ($data['list_price_cents'] ?? 0));
        $paid = max(0, (int) ($data['price_paid_cents'] ?? 0));
        $pct = $campaign ? (int) $campaign->discount_pct
            : ($listPrice > 0 ? (int) round(100 - $paid * 100 / $listPrice) : 0);
        $days = $campaign ? (int) $campaign->expiry_days : 30;

        try {
            return PrepaidVoucher::create([
                'operator_id' => (int) ($campaign?->operator_id ?: $vend->operator_id),
                'prepaid_voucher_campaign_id' => $campaign?->id,
                'phone' => $phone,
                'product_id' => $productId,
                'list_price_cents' => max($listPrice, $paid),
                'price_paid_cents' => $paid,
                'discount_pct' => max(0, min(100, $pct)),
                'pay_rail' => $data['pay_rail'] === PrepaidVoucher::RAIL_CARD ? PrepaidVoucher::RAIL_CARD : PrepaidVoucher::RAIL_QR,
                'bought_vend_id' => $vend->id,
                'bought_order_id' => $orderId,
                'bought_vend_transaction_id' => $data['vend_transaction_id'] ?? null,
                'payment_gateway_log_id' => $data['payment_gateway_log_id'] ?? null,
                'first_order_id' => isset($data['first_order_id']) ? substr((string) $data['first_order_id'], 0, 64) : null,
                'issued_at' => $issuedAt,
                'expires_at' => PrepaidVoucherExpiry::expiresAt($issuedAt, $days, $locale['timezone']),
                'status' => PrepaidVoucher::STATUS_ACTIVE,
            ]);
        } catch (QueryException $e) {
            // The other path wrote it a moment ago (unique machine + order).
            $again = $this->find($vend, $orderId);
            if ($again) {
                return $this->fillLinks($again, $data);
            }
            throw $e;
        }
    }

    public function find(Vend $vend, string $orderId): ?PrepaidVoucher
    {
        return PrepaidVoucher::query()->where('bought_vend_id', $vend->id)->where('bought_order_id', $orderId)->first();
    }

    /** The second path only adds what the first did not know (the sale row, the gateway log). */
    private function fillLinks(PrepaidVoucher $voucher, array $data): PrepaidVoucher
    {
        $updates = [];
        if (! $voucher->bought_vend_transaction_id && ! empty($data['vend_transaction_id'])) {
            $updates['bought_vend_transaction_id'] = (int) $data['vend_transaction_id'];
        }
        if (! $voucher->payment_gateway_log_id && ! empty($data['payment_gateway_log_id'])) {
            $updates['payment_gateway_log_id'] = (int) $data['payment_gateway_log_id'];
        }
        if (! $voucher->first_order_id && ! empty($data['first_order_id'])) {
            $updates['first_order_id'] = substr((string) $data['first_order_id'], 0, 64);
        }
        if ($updates !== []) {
            $voucher->forceFill($updates)->save();
        }

        return $voucher;
    }

    private static function redact(array $data): array
    {
        unset($data['phone']);

        return $data;
    }
}
