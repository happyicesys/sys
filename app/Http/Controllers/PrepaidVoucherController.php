<?php

namespace App\Http\Controllers;

use App\Http\Resources\PrepaidVoucherResource;
use App\Models\PrepaidVoucher;
use App\Models\PrepaidVoucherCampaign;
use App\Models\Product;
use App\Models\Scopes\OperatorVendFilterScope;
use App\Models\Vend;
use App\Services\HappyHour\HappyHourPriceStep;
use App\Services\PrepaidVoucher\PrepaidVoucherExpiry;
use App\Services\PrepaidVoucher\PrepaidVoucherPhone;
use App\Services\PrepaidVoucher\PrepaidVoucherProgram;
use App\Services\VendJobService;
use App\Support\OperatorScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Machine Management → Next-visit Voucher, its own menu entry below Happy Hour (Brian, 2026-11-20): the operator's settings
 * (discount, expiry, Pause), and every voucher bought, searchable by the customer's full number.
 */
class PrepaidVoucherController extends Controller
{
    public function index(Request $request): Response
    {
        $ownerId = $this->ownerOperatorId();
        $campaign = PrepaidVoucherCampaign::query()->where('operator_id', $ownerId)->first();
        $machines = $this->machineOptions($ownerId);

        return Inertia::render('PrepaidVoucher/Index', [
            'campaign' => fn () => $campaign ? $this->campaignPayload($campaign) : null,
            'machines' => fn () => $machines,
            'products' => fn () => $this->productOptions(array_column($machines, 'id')),
            'minVendingApkVersion' => PrepaidVoucherProgram::minVendingApk(),
            'priceStep' => fn () => HappyHourPriceStep::choices(HappyHourPriceStep::currencyOfOperator($ownerId)),
            'currency' => fn () => $this->currency($ownerId),
            'summary' => fn () => $this->summary(),
            'vouchers' => fn () => PrepaidVoucherResource::collection($this->voucherQuery($request)
                ->with(['product:id,code,name', 'redeemedProduct:id,code,name', 'boughtVend:id,code,code_prefix', 'redeemedVend:id,code,code_prefix'])
                ->orderByDesc('id')
                ->paginate(50)
                ->withQueryString()),
            'filters' => $request->only(['phone', 'status']),
            'phoneSearchInvalid' => $request->filled('phone') && $this->searchPhone($request) === null,
        ]);
    }

    public function save(Request $request, VendJobService $jobs): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['required', 'boolean'],
            'is_redeem_enabled' => ['required', 'boolean'],
            'is_card_enabled' => ['required', 'boolean'],
            'discount_pct' => ['required', 'integer', 'min:1', 'max:90'],
            'price_step_cents' => ['required', 'integer', 'min:1'],
            'expiry_days' => ['required', 'integer', 'min:1', 'max:365'],
            'offer_seconds' => ['required', 'integer', 'min:10', 'max:60'],
            'excluded_product_ids' => ['array'],
            'excluded_product_ids.*' => ['integer'],
            'excluded_vend_ids' => ['array'],
            'excluded_vend_ids.*' => ['integer'],
        ]);
        $ownerId = $this->ownerOperatorId();

        $campaign = PrepaidVoucherCampaign::query()->firstOrNew(['operator_id' => $ownerId]);
        $campaign->fill([
            ...$data,
            'excluded_product_ids' => array_values(array_unique(array_map('intval', $data['excluded_product_ids'] ?? []))),
            'excluded_vend_ids' => array_values(array_unique(array_map('intval', $data['excluded_vend_ids'] ?? []))),
            'updated_by' => auth()->id(),
        ]);
        if (! $campaign->exists) {
            $campaign->created_by = auth()->id();
        }
        $campaign->save();

        // Every machine that can run it re-reads its settings now (app 329+ answers PREPAIDVOUCHER).
        // Each save is in user_logs (UserLogger records every model write).
        foreach ($this->campaignVends($ownerId) as $vend) {
            if (PrepaidVoucherProgram::boardReason($vend) === null) {
                $jobs->nudgePrepaidVoucherToVend($vend);
            }
        }

        return back()->with('success', $campaign->is_active
            ? __('Next-visit voucher settings saved. Machines are offering it.')
            : __('Next-visit voucher settings saved. The offer is paused; bought vouchers can still be used.'));
    }

    public function void(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']]);
        $voucher = $this->visibleVoucher($id);
        if (! in_array($voucher->status, [PrepaidVoucher::STATUS_ACTIVE, PrepaidVoucher::STATUS_RESERVED], true)) {
            return back()->with('error', __('Only an unused voucher can be voided.'));
        }
        $voucher->forceFill([
            'status' => PrepaidVoucher::STATUS_VOIDED,
            'voided_at' => Carbon::now(),
            'voided_by' => auth()->id(),
            'void_reason' => $data['reason'],
            'reserved_token' => null,
            'reserved_until' => null,
        ])->save();

        return back()->with('success', __('Voucher #:id voided.', ['id' => $voucher->id]));
    }

    public function extend(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['days' => ['required', 'integer', 'min:1', 'max:365']]);
        $voucher = $this->visibleVoucher($id);
        if (in_array($voucher->status, [PrepaidVoucher::STATUS_REDEEMED, PrepaidVoucher::STATUS_VOIDED], true)) {
            return back()->with('error', __('A used or voided voucher cannot be extended.'));
        }
        $vend = Vend::withoutGlobalScopes()->find($voucher->bought_vend_id);
        $tz = $vend ? app(PrepaidVoucherProgram::class)->locale($vend)['timezone'] : config('app.timezone');
        // From today if it has already run out, else from its current last day.
        $from = $voucher->expires_at->isPast() ? Carbon::now() : $voucher->expires_at->copy();
        $voucher->forceFill(['expires_at' => PrepaidVoucherExpiry::expiresAt($from, (int) $data['days'], $tz)])->save();

        return back()->with('success', __('Voucher #:id now runs until :date.', ['id' => $voucher->id, 'date' => $voucher->expires_at->format('Y-m-d')]));
    }

    private function visibleVoucher(int $id): PrepaidVoucher
    {
        return $this->scoped(PrepaidVoucher::query())->whereKey($id)->firstOrFail();
    }

    private function voucherQuery(Request $request)
    {
        $query = $this->scoped(PrepaidVoucher::query());
        if ($request->filled('phone')) {
            // Exact number only: a list of everyone's numbers is not something a search should give out.
            $query->where('phone', $this->searchPhone($request) ?? '__none__');
        }
        $now = Carbon::now();
        match ($request->input('status')) {
            'active' => $query->usable($now),
            'expired' => $query->whereIn('status', [PrepaidVoucher::STATUS_ACTIVE, PrepaidVoucher::STATUS_RESERVED])->where('expires_at', '<=', $now),
            'redeemed' => $query->where('status', PrepaidVoucher::STATUS_REDEEMED),
            'voided' => $query->where('status', PrepaidVoucher::STATUS_VOIDED),
            default => null,
        };

        return $query;
    }

    private function searchPhone(Request $request): ?string
    {
        $phoneCode = DB::table('operators')->join('countries', 'countries.id', '=', 'operators.country_id')
            ->where('operators.id', $this->ownerOperatorId())->value('countries.phone_code');

        return PrepaidVoucherPhone::normalise((string) $request->input('phone'), $phoneCode ? (string) $phoneCode : null);
    }

    /** A viewer sees the vouchers of the operators they may see (an HIPL viewer: the group). */
    private function scoped($query)
    {
        $ids = OperatorScope::current();

        return $query->whereIn('operator_id', $ids === [] ? [0] : $ids);
    }

    /** @return array<string, int> */
    private function summary(): array
    {
        $now = Carbon::now();
        $usable = $this->scoped(PrepaidVoucher::query())->usable($now);

        return [
            'active_count' => (clone $usable)->count(),
            'active_value_cents' => (int) (clone $usable)->sum('price_paid_cents'),
            'sold_30d_count' => $this->scoped(PrepaidVoucher::query())->where('issued_at', '>=', $now->copy()->subDays(30))->count(),
            'sold_30d_value_cents' => (int) $this->scoped(PrepaidVoucher::query())->where('issued_at', '>=', $now->copy()->subDays(30))->sum('price_paid_cents'),
            'redeemed_30d_count' => $this->scoped(PrepaidVoucher::query())->where('status', PrepaidVoucher::STATUS_REDEEMED)->where('redeemed_at', '>=', $now->copy()->subDays(30))->count(),
            'expired_30d_count' => $this->scoped(PrepaidVoucher::query())->whereIn('status', [PrepaidVoucher::STATUS_ACTIVE, PrepaidVoucher::STATUS_RESERVED])
                ->whereBetween('expires_at', [$now->copy()->subDays(30), $now])->count(),
            'expired_30d_value_cents' => (int) $this->scoped(PrepaidVoucher::query())->whereIn('status', [PrepaidVoucher::STATUS_ACTIVE, PrepaidVoucher::STATUS_RESERVED])
                ->whereBetween('expires_at', [$now->copy()->subDays(30), $now])->sum('price_paid_cents'),
        ];
    }

    private function campaignPayload(PrepaidVoucherCampaign $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'is_active' => (bool) $c->is_active,
            'is_redeem_enabled' => (bool) $c->is_redeem_enabled,
            'is_card_enabled' => (bool) $c->is_card_enabled,
            'discount_pct' => $c->discount_pct,
            'price_step_cents' => $c->price_step_cents,
            'expiry_days' => $c->expiry_days,
            'offer_seconds' => $c->offer_seconds,
            'excluded_product_ids' => $c->excludedProductIds(),
            'excluded_vend_ids' => $c->excludedVendIds(),
            'updated_at' => $c->updated_at?->format('Y-m-d H:i'),
        ];
    }

    /** The settings owner: the viewer's operator, HIPL when unset (as Happy Hour). */
    private function ownerOperatorId(): int
    {
        return (int) auth()->user()->operator_id ?: OperatorVendFilterScope::UNRESTRICTED_OPERATOR_ID;
    }

    /** Active vending machines the owner's settings cover: its own, and for HIPL the group's. */
    private function campaignVends(int $ownerId)
    {
        $ids = OperatorScope::groupOf($ownerId) ?: [$ownerId];

        return Vend::withoutGlobalScopes()
            ->where('machine_type', Vend::MACHINE_TYPE_VENDING_MACHINE)
            ->where('is_active', 1)
            ->whereIn('operator_id', $ids)
            ->get(['id', 'code', 'code_prefix', 'machine_type', 'is_active', 'apk_version_code', 'apk_ver_json', 'apk_checked_in_at', 'operator_id']);
    }

    /** @return list<array{id: int, name: string, ready: bool, reason: ?string}> */
    private function machineOptions(int $ownerId): array
    {
        return $this->campaignVends($ownerId)->sortBy('code')->map(function (Vend $v) {
            $reason = PrepaidVoucherProgram::boardReason($v);

            return ['id' => $v->id, 'name' => $v->codeLabel(), 'ready' => $reason === null, 'reason' => $reason];
        })->values()->all();
    }

    /** @param  list<int>  $vendIds */
    private function productOptions(array $vendIds): array
    {
        if ($vendIds === []) {
            return [];
        }
        $ids = DB::table('vend_channels')->whereIn('vend_id', $vendIds)->whereNotNull('product_id')->distinct()->pluck('product_id');

        return Product::query()->whereIn('id', $ids)->orderBy('code')->get(['id', 'code', 'name'])
            ->map(fn (Product $p) => ['id' => $p->id, 'name' => trim("{$p->code} {$p->name}")])
            ->values()->all();
    }

    /** @return array{symbol: string, exponent: int, hidden: bool} */
    private function currency(int $operatorId): array
    {
        $country = DB::table('operators')->join('countries', 'countries.id', '=', 'operators.country_id')
            ->where('operators.id', $operatorId)
            ->first(['countries.currency_symbol', 'countries.currency_exponent', 'countries.is_currency_exponent_hidden']);

        return [
            'symbol' => (string) ($country->currency_symbol ?? '$'),
            'exponent' => (int) ($country->currency_exponent ?? 2),
            'hidden' => (bool) ($country->is_currency_exponent_hidden ?? false),
        ];
    }
}
