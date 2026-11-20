<?php

namespace App\Services\PrepaidVoucher;

use App\Models\PrepaidVoucher;
use App\Models\Vend;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Spending a voucher: look up a number's vouchers, hold one for this machine (reserve), and settle it from
 * the redemption TRADE — or give it back when the item did not drop or the customer walked away.
 *
 * A reservation is the lock against two machines (or two taps) spending one voucher: only a usable voucher
 * can be reserved, in one conditional UPDATE. A redemption TRADE settles the voucher it names even if its
 * reservation lapsed meanwhile (the item already dropped); one naming an already-spent voucher is recorded
 * on the sale as a conflict for a person to look at.
 */
final class PrepaidVoucherRedeemer
{
    public function __construct(private readonly PrepaidVoucherProgram $program) {}

    /**
     * @return array{ok: bool, error?: string, phone?: string, vouchers?: Collection<int, PrepaidVoucher>}
     */
    public function lookup(Vend $vend, ?string $rawPhone): array
    {
        $campaign = $this->program->campaignFor($vend);
        if (! $campaign || ! $this->program->redeems($vend, $campaign)) {
            return ['ok' => false, 'error' => 'not_available'];
        }
        $phone = PrepaidVoucherPhone::normalise($rawPhone, $this->program->locale($vend)['phone_code']);
        if ($phone === null) {
            return ['ok' => false, 'error' => 'invalid_phone'];
        }

        $vouchers = PrepaidVoucher::query()
            ->usable()
            ->where('phone', $phone)
            ->whereIn('operator_id', $this->program->redeemableOperatorIds($campaign))
            ->with(['product:id,code,name', 'product.thumbnail'])
            ->orderBy('expires_at')
            ->orderBy('id')
            ->limit(50)
            ->get();

        return ['ok' => true, 'phone' => $phone, 'vouchers' => $vouchers];
    }

    /**
     * @return array{ok: bool, error?: string, voucher?: PrepaidVoucher}
     */
    public function reserve(Vend $vend, ?string $rawPhone, int $voucherId): array
    {
        $found = $this->lookup($vend, $rawPhone);
        if (! $found['ok']) {
            return $found;
        }
        if (! $found['vouchers']->contains('id', $voucherId)) {
            return ['ok' => false, 'error' => 'not_usable'];
        }

        $now = Carbon::now();
        $token = Str::random(32);
        $until = $now->copy()->addMinutes(max(1, (int) config('prepaid_voucher.reserve_minutes', 15)));
        $taken = PrepaidVoucher::query()
            ->whereKey($voucherId)
            ->where('phone', $found['phone'])
            ->usable($now)
            ->update([
                'status' => PrepaidVoucher::STATUS_RESERVED,
                'reserved_vend_id' => $vend->id,
                'reserved_token' => $token,
                'reserved_until' => $until,
                'updated_at' => $now,
            ]);

        if ($taken !== 1) {
            return ['ok' => false, 'error' => 'not_usable'];
        }

        return ['ok' => true, 'voucher' => PrepaidVoucher::query()->with(['product:id,code,name', 'product.thumbnail'])->find($voucherId)];
    }

    /** The customer backed out (or the dispense failed): the voucher is usable again. */
    public function release(Vend $vend, int $voucherId, ?string $token): bool
    {
        return PrepaidVoucher::query()
            ->whereKey($voucherId)
            ->where('status', PrepaidVoucher::STATUS_RESERVED)
            ->where('reserved_vend_id', $vend->id)
            ->when($token !== null && $token !== '', fn ($q) => $q->where('reserved_token', $token))
            ->update([
                'status' => PrepaidVoucher::STATUS_ACTIVE,
                'reserved_vend_id' => null,
                'reserved_token' => null,
                'reserved_until' => null,
                'updated_at' => Carbon::now(),
            ]) === 1;
    }

    /**
     * Settles a voucher from its redemption TRADE.
     *
     * @param  array{id: int, token: ?string, mode: string, used: int, lost: int, balance: int, product_id: ?int,
     *               order_id: string, vend_transaction_id: int, dispensed: bool}  $r
     * @return string redeemed | released | conflict | unknown | already
     */
    public function settle(Vend $vend, array $r): string
    {
        return DB::transaction(function () use ($vend, $r) {
            /** @var PrepaidVoucher|null $voucher */
            $voucher = PrepaidVoucher::query()->whereKey($r['id'])->lockForUpdate()->first();
            if (! $voucher) {
                Log::warning('prepaid voucher: redemption TRADE names an unknown voucher', ['vend' => $vend->code, 'voucher' => $r['id']]);

                return 'unknown';
            }
            if ($voucher->status === PrepaidVoucher::STATUS_REDEEMED) {
                return $voucher->redeemed_vend_id === $vend->id && $voucher->redeemed_order_id === $r['order_id'] ? 'already' : 'conflict';
            }
            if ($voucher->status === PrepaidVoucher::STATUS_VOIDED) {
                return 'conflict';
            }

            if (! $r['dispensed']) {
                // Nothing dropped: the customer keeps the voucher. Any balance they paid follows the rail's own refund rules.
                if ($voucher->status === PrepaidVoucher::STATUS_RESERVED) {
                    $voucher->forceFill([
                        'status' => PrepaidVoucher::STATUS_ACTIVE,
                        'reserved_vend_id' => null,
                        'reserved_token' => null,
                        'reserved_until' => null,
                    ])->save();
                }

                return 'released';
            }

            $mode = $r['mode'] === PrepaidVoucher::MODE_VALUE ? PrepaidVoucher::MODE_VALUE : PrepaidVoucher::MODE_SKU;
            $voucher->forceFill([
                'status' => PrepaidVoucher::STATUS_REDEEMED,
                'reserved_token' => null,
                'reserved_until' => null,
                'redeemed_at' => Carbon::now(),
                'redeemed_vend_id' => $vend->id,
                'redeemed_order_id' => substr($r['order_id'], 0, 64),
                'redeemed_vend_transaction_id' => $r['vend_transaction_id'],
                'redeemed_mode' => $mode,
                'redeemed_product_id' => $r['product_id'] ?: ($mode === PrepaidVoucher::MODE_SKU ? $voucher->product_id : null),
                'value_used_cents' => $mode === PrepaidVoucher::MODE_SKU ? $voucher->price_paid_cents : max(0, min($r['used'], $voucher->price_paid_cents)),
                'forfeited_cents' => $mode === PrepaidVoucher::MODE_SKU ? 0 : max(0, min($r['lost'], $voucher->price_paid_cents)),
                'balance_paid_cents' => max(0, $r['balance']),
            ])->save();

            return 'redeemed';
        });
    }

    /** Machine payload for one voucher. Never carries the number. */
    public function payload(PrepaidVoucher $v, string $timezone): array
    {
        return [
            'id' => (int) $v->id,
            'product_id' => (int) $v->product_id,
            'product_code' => $v->product?->code,
            'product_name' => $v->product?->name,
            'thumbnail' => $v->product?->thumbnail?->full_url,
            'value_cents' => (int) $v->price_paid_cents,
            'list_price_cents' => (int) $v->list_price_cents,
            'expires_at' => $v->expires_at->copy()->setTimezone($timezone)->format('Y-m-d H:i:s'),
            'expires_on' => $v->expires_at->copy()->setTimezone($timezone)->format('Y-m-d'),
        ];
    }
}
