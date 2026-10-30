<?php

namespace App\Services;

use App\Console\Commands\SyncDeliveryStorePause;
use App\Models\DeliveryPlatformApiError;
use App\Models\DeliveryPlatformCampaignItemVend;
use App\Models\DeliveryPlatformCampaignRead;
use App\Models\DeliveryPlatformLiveCampaign;
use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryPlatforms\Grab;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Grab is the truth for what IS live (plan §9.3, 2026-10-30). Reads each store's
 * ongoing and upcoming campaigns from Grab into delivery_platform_live_campaigns.
 * Read only: nothing here creates, changes or ends a campaign on Grab.
 *
 * Today mark1 records a campaign as ended when it asked Grab to end it, whatever Grab
 * answered; a failed DELETE left campaigns live on Grab that mark1 believed gone, and
 * the next create for that store was refused ("same item in two campaigns"). The mirror
 * shows those as leftovers.
 */
class GrabCampaignMirror
{
    public function __construct(private GrabStoreService $grabStoreService) {}

    /**
     * Stores worth reading: Active refs, refs open on a machine, and refs that still
     * have campaigns in the mirror (so a campaign that ends on an Inactive store is seen
     * to end).
     *
     * @return Collection<int, DeliveryPlatformRefNumber>
     */
    public function stores(?string $merchantId = null): Collection
    {
        return DeliveryPlatformRefNumber::query()
            ->whereHas('deliveryPlatform', fn ($query) => $query->where('slug', 'grab'))
            ->when($merchantId, fn ($query, $merchantId) => $query->where('ref_number', $merchantId))
            ->where(fn ($query) => $query
                ->where('status', DeliveryPlatformRefNumber::STATUS_ACTIVE)
                ->orWhereHas('openDeliveryProductMappingVends', fn ($query) => $query->withoutGlobalScopes())
                ->orWhereIn('id', DeliveryPlatformLiveCampaign::query()->live()->whereNotNull('delivery_platform_ref_number_id')->select('delivery_platform_ref_number_id')))
            ->orderBy('id')
            ->get();
    }

    /**
     * Read one store from Grab and update the mirror.
     *
     * @return array{result: string, code: ?int, ongoing: int, upcoming: int}
     *                                                                        result: ok | refused | no_account | failed | rate_limited
     */
    public function refresh(DeliveryPlatformRefNumber $ref): array
    {
        $merchantId = $ref->ref_number;
        $none = ['code' => null, 'ongoing' => 0, 'upcoming' => 0];

        // DCVIC's 11 stores: Grab no longer lets us act for them; asking adds a 401 a run.
        if (Cache::has(SyncDeliveryStorePause::REFUSED_CACHE_PREFIX.$merchantId) || DeliveryPlatformApiError::refusedRecently($merchantId)) {
            return ['result' => 'refused'] + $none;
        }

        // Grab answered "Merchant not found" in the last day: a merchantID Grab does not
        // know under this account (GFSBPOS-800-085, a sandbox id on HIPL's production
        // account). Asked again after a day, not every run.
        $lastRead = DeliveryPlatformCampaignRead::query()->where('merchant_id', $merchantId)->first();
        if ($lastRead && $lastRead->last_status === 404 && $lastRead->last_read_at->gt(now()->subDay())) {
            return ['result' => 'not_found', 'code' => 404, 'ongoing' => 0, 'upcoming' => 0];
        }

        $account = $ref->operator_id ? $this->grabStoreService->grabAccountFor((int) $ref->operator_id) : null;

        // Live stores only. The TEST operator's sandbox stores (GFSBPOS-…, 2023) answer 401:
        // their sandbox login is long dead, and no customer sees a sandbox campaign.
        if ($account && $account->type !== 'production') {
            $lastRead?->delete();

            return ['result' => 'sandbox'] + $none;
        }

        if (! $account || ! $account->externalOauthToken?->access_token) {
            $this->recordRead($ref, null, __('This operator has no Grab account in mark1.'));

            return ['result' => 'no_account'] + $none;
        }

        try {
            $response = (new Grab($account))->listCampaigns($merchantId);
        } catch (\Throwable $e) {
            $this->recordRead($ref, null, $e->getMessage());

            return ['result' => 'failed'] + $none;
        }

        $code = $response['code'] ?? null;
        if (! ($response['success'] ?? false)) {
            // Kept on Grab Errors by the HTTP listener already.
            $this->recordRead($ref, $code, $response['message'] ?? null);

            return ['result' => $code === 429 ? 'rate_limited' : (in_array($code, [401, 403], true) ? 'refused' : 'failed'), 'code' => $code, 'ongoing' => 0, 'upcoming' => 0];
        }

        // A 204 with no body means nothing is live.
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];
        $ongoing = is_array($data['ongoing'] ?? null) ? $data['ongoing'] : [];
        $upcoming = is_array($data['upcoming'] ?? null) ? $data['upcoming'] : [];
        $this->sync($ref, $account->id, $ongoing, $upcoming);
        $this->recordRead($ref, $code, null, count($ongoing), count($upcoming));

        return ['result' => 'ok', 'code' => $code, 'ongoing' => count($ongoing), 'upcoming' => count($upcoming)];
    }

    /** Write one good read: every listed campaign upserted, every unlisted one gone. */
    public function sync(DeliveryPlatformRefNumber $ref, ?int $accountId, array $ongoing, array $upcoming): void
    {
        $now = now();
        $listed = collect($ongoing)->map(fn ($campaign) => [$campaign, DeliveryPlatformLiveCampaign::STATE_ONGOING])
            ->concat(collect($upcoming)->map(fn ($campaign) => [$campaign, DeliveryPlatformLiveCampaign::STATE_UPCOMING]))
            ->filter(fn ($pair) => is_array($pair[0]) && ! empty($pair[0]['id']));

        // Ours: a campaign item vend that Grab gave this id when mark1 created it.
        $ours = DeliveryPlatformCampaignItemVend::query()
            ->whereIn('platform_ref_id', $listed->map(fn ($pair) => (string) $pair[0]['id'])->all())
            ->orderByDesc('id')
            ->get(['id', 'platform_ref_id'])
            ->unique('platform_ref_id')
            ->pluck('id', 'platform_ref_id');

        DB::transaction(function () use ($ref, $accountId, $listed, $ours, $now) {
            $ids = [];
            foreach ($listed as [$campaign, $state]) {
                $id = (string) $campaign['id'];
                $ids[] = $id;
                $conditions = $campaign['conditions'] ?? [];
                $discount = $campaign['discount'] ?? [];

                $row = DeliveryPlatformLiveCampaign::query()->firstOrNew(['grab_campaign_id' => $id]);
                $row->fill([
                    'merchant_id' => $ref->ref_number,
                    'delivery_platform_ref_number_id' => $ref->id,
                    'delivery_platform_operator_id' => $accountId,
                    'state' => $state,
                    'created_by' => isset($campaign['createdBy']) ? mb_substr((string) $campaign['createdBy'], 0, 32) : null,
                    'name' => isset($campaign['name']) ? mb_substr((string) $campaign['name'], 0, 255) : null,
                    'discount_type' => isset($discount['type']) ? mb_substr((string) $discount['type'], 0, 32) : null,
                    'scope_type' => isset($discount['scope']['type']) ? mb_substr((string) $discount['scope']['type'], 0, 16) : null,
                    'starts_at' => $this->time($conditions['startTime'] ?? null),
                    'ends_at' => $this->time($conditions['endTime'] ?? null),
                    'campaign_json' => $campaign,
                    'delivery_platform_campaign_item_vend_id' => $ours[$id] ?? null,
                    'last_seen_at' => $now,
                    'gone_at' => null,
                ]);
                if (! $row->exists) {
                    $row->first_seen_at = $now;
                }
                $row->save();
            }

            DeliveryPlatformLiveCampaign::query()
                ->where('merchant_id', $ref->ref_number)
                ->live()
                ->when($ids, fn ($query) => $query->whereNotIn('grab_campaign_id', $ids))
                ->update(['gone_at' => $now, 'updated_at' => $now]);
        });
    }

    /** Ended rows older than KEEP_GONE_DAYS. */
    public function prune(): int
    {
        return DeliveryPlatformLiveCampaign::query()
            ->whereNotNull('gone_at')
            ->where('gone_at', '<', now()->subDays(DeliveryPlatformLiveCampaign::KEEP_GONE_DAYS))
            ->delete();
    }

    private function recordRead(DeliveryPlatformRefNumber $ref, ?int $status, ?string $message, int $ongoing = 0, int $upcoming = 0): void
    {
        $read = DeliveryPlatformCampaignRead::query()->firstOrNew(['merchant_id' => $ref->ref_number]);
        $read->fill([
            'delivery_platform_ref_number_id' => $ref->id,
            'last_read_at' => now(),
            'last_status' => $status,
            'last_message' => $message !== null ? mb_substr($message, 0, 500) : null,
        ]);
        if ($message === null) {
            $read->last_ok_at = now();
            $read->ongoing_count = $ongoing;
            $read->upcoming_count = $upcoming;
        }
        $read->save();
    }

    // Grab sends UTC ISO times; stored in the app timezone like every other column.
    private function time($value): ?Carbon
    {
        if (! $value) {
            return null;
        }

        try {
            return Carbon::parse($value)->setTimezone(config('app.timezone'));
        } catch (\Throwable) {
            return null;
        }
    }
}
