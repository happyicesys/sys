<?php

namespace App\Services\PrepaidVoucher;

use App\Models\Operator;
use App\Models\PrepaidVoucherCampaign;
use App\Models\Vend;
use App\Services\OtaChannelResolver;
use App\Support\OperatorScope;

/**
 * Which next-visit voucher settings a machine runs, and what it may do with them — the ONE place this is
 * decided (Brian, 2026-11-20).
 *
 * - The settings row is the machine operator's own; failing that, an HIPL-group machine takes HIPL's.
 * - The OFFER needs the row active ("Pause" off), the machine not excluded, and a big touchscreen board on
 *   app `prepaid_voucher.min_vending_apk_version`+. Small boards (ZC-83A) never qualify.
 * - REDEMPTION needs the same board, and `is_redeem_enabled`; a pause never stops it. A voucher is spent on
 *   any machine of its owner's operator group ("every HIPL machine").
 */
final class PrepaidVoucherProgram
{
    private const SMALL_BOARD_CHANNEL = 'vending_small';

    public static function minVendingApk(): int
    {
        return (int) config('prepaid_voucher.min_vending_apk_version', 329);
    }

    /** Null when the board can run the voucher screens; otherwise why not, in words for the page. */
    public static function boardReason(Vend $vend): ?string
    {
        if (! (bool) $vend->is_active) {
            return 'inactive';
        }
        if ($vend->machine_type !== Vend::MACHINE_TYPE_VENDING_MACHINE) {
            return 'not a touchscreen vending machine';
        }
        $reported = $vend->reportedApkVersionDetail();
        if ($reported->deviceType !== null
            && app(OtaChannelResolver::class)->forDeviceType($reported->deviceType) === self::SMALL_BOARD_CHANNEL) {
            return "small board ({$reported->deviceType})";
        }
        if ($reported->code < self::minVendingApk()) {
            return sprintf('app %s — needs %d or later', $reported->code ?: 'unknown', self::minVendingApk());
        }

        return null;
    }

    public function campaignFor(Vend $vend): ?PrepaidVoucherCampaign
    {
        $operatorId = (int) $vend->operator_id;
        if ($operatorId <= 0) {
            return null;
        }
        $own = PrepaidVoucherCampaign::query()->where('operator_id', $operatorId)->first();
        if ($own) {
            return $own;
        }
        $parent = Operator::withoutGlobalScopes()->where('code', OperatorScope::PARENT_CODE)->value('id');
        if ($parent && in_array($operatorId, OperatorScope::groupOf((int) $parent), true)) {
            return PrepaidVoucherCampaign::query()->where('operator_id', (int) $parent)->first();
        }

        return null;
    }

    public function offers(Vend $vend, ?PrepaidVoucherCampaign $campaign = null): bool
    {
        $campaign ??= $this->campaignFor($vend);

        return $campaign !== null
            && $campaign->is_active
            && ! in_array((int) $vend->id, $campaign->excludedVendIds(), true)
            && self::boardReason($vend) === null;
    }

    public function redeems(Vend $vend, ?PrepaidVoucherCampaign $campaign = null): bool
    {
        $campaign ??= $this->campaignFor($vend);

        return $campaign !== null && $campaign->is_redeem_enabled && self::boardReason($vend) === null;
    }

    /**
     * Operators whose vouchers this machine may spend: its campaign owner's group.
     *
     * @return array<int, int>
     */
    public function redeemableOperatorIds(PrepaidVoucherCampaign $campaign): array
    {
        $group = OperatorScope::groupOf((int) $campaign->operator_id);

        return $group === [] ? [(int) $campaign->operator_id] : $group;
    }

    /** @return array{timezone: string, phone_code: ?string} the machine operator's clock and dialling code */
    public function locale(Vend $vend): array
    {
        $operator = $vend->operator_id ? Operator::withoutGlobalScopes()->with('country')->find($vend->operator_id) : null;

        return [
            'timezone' => $operator?->timezone ?: (string) config('app.timezone'),
            'phone_code' => $operator?->country?->phone_code ? (string) $operator->country->phone_code : null,
        ];
    }

    /**
     * What the APK reads (GET /api/v1/vends/{code}/prepaid-voucher). Every amount in minor units.
     *
     * @return array<string, mixed>
     */
    public function machineConfig(Vend $vend): array
    {
        $campaign = $this->campaignFor($vend);
        $locale = $this->locale($vend);

        return [
            'offer' => $campaign !== null && $this->offers($vend, $campaign),
            'redeem' => $campaign !== null && $this->redeems($vend, $campaign),
            // Card may pay for the voucher (NETS on the VMC); false = QR only.
            'card' => (bool) ($campaign?->is_card_enabled ?? false),
            'discount_pct' => (int) ($campaign?->discount_pct ?? 0),
            'price_step_cents' => (int) ($campaign?->price_step_cents ?? 10),
            'expiry_days' => (int) ($campaign?->expiry_days ?? 0),
            'offer_seconds' => (int) ($campaign?->offer_seconds ?? 20),
            'excluded_product_ids' => $campaign?->excludedProductIds() ?? [],
            'phone' => PrepaidVoucherPhone::rule($locale['phone_code']),
        ];
    }

    /** The voucher price for a list price: (100 − pct)% rounded UP to the step, never above the list price. */
    public static function voucherPrice(int $listPriceCents, int $discountPct, int $stepCents): int
    {
        $raw = intdiv($listPriceCents * (100 - max(0, min(100, $discountPct))) + 99, 100);
        $step = max(1, $stepCents);
        $rounded = intdiv($raw + $step - 1, $step) * $step;

        return min($listPriceCents, $rounded);
    }
}
