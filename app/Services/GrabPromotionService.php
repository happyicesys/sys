<?php

namespace App\Services;

use App\Jobs\SendGrabPromotion;
use App\Models\DeliveryPlatformCampaignSend;
use App\Models\DeliveryPlatformLiveCampaign;
use App\Models\DeliveryPlatformOperator;
use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryPlatforms\Grab;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Grab Promotions, one page (Brian, 2026-10-31): a promotion is made, changed and ended
 * ON GRAB, store by store, and what runs is read back from Grab (GrabCampaignMirror).
 * ConnectVend keeps no campaign of its own — only the send log
 * (delivery_platform_campaign_sends), of which this is the only writer.
 *
 * Every send is queued, one store per job, and sent exactly once: a create whose answer
 * was lost may already be on Grab, and Grab refuses the same items in two campaigns, so
 * nothing here retries. The store is read again after each send, so the list shows
 * Grab's answer within seconds.
 */
class GrabPromotionService
{
    public function __construct(
        private GrabStoreService $grabStoreService,
        private GrabCampaignMirror $mirror,
    ) {}

    /**
     * @param  array  $setup  see payload()
     * @param  Collection<int, DeliveryPlatformRefNumber>  $refs
     */
    public function create(array $setup, Collection $refs, ?int $userId): string
    {
        $batch = (string) Str::uuid();
        DB::transaction(function () use ($setup, $refs, $userId, $batch) {
            foreach ($refs as $ref) {
                $this->queue($batch, DeliveryPlatformCampaignSend::ACTION_CREATE, $ref, null, $this->payload($setup, $ref->ref_number), $userId);
            }
        });

        return $batch;
    }

    /**
     * The same new setup on each of these Grab campaigns.
     *
     * @param  Collection<int, DeliveryPlatformLiveCampaign>  $campaigns
     */
    public function update(Collection $campaigns, array $setup, ?int $userId): string
    {
        $batch = (string) Str::uuid();
        DB::transaction(function () use ($campaigns, $setup, $userId, $batch) {
            foreach ($campaigns as $campaign) {
                $this->queue($batch, DeliveryPlatformCampaignSend::ACTION_UPDATE, $campaign->deliveryPlatformRefNumber, $campaign, $this->payload($setup, $campaign->merchant_id), $userId);
            }
        });

        return $batch;
    }

    /** @param  Collection<int, DeliveryPlatformLiveCampaign>  $campaigns */
    public function end(Collection $campaigns, ?int $userId): string
    {
        $batch = (string) Str::uuid();
        DB::transaction(function () use ($campaigns, $userId, $batch) {
            foreach ($campaigns as $campaign) {
                $this->queue($batch, DeliveryPlatformCampaignSend::ACTION_END, $campaign->deliveryPlatformRefNumber, $campaign, null, $userId);
            }
        });

        return $batch;
    }

    /**
     * A store left its machine: the promotions ConnectVend made on it end on Grab, as the
     * old campaign pages did. Ones made in the merchant app or by Grab are left alone.
     */
    public function endForStore(DeliveryPlatformRefNumber $ref, ?int $userId): ?string
    {
        $campaigns = DeliveryPlatformLiveCampaign::query()->live()
            ->where('merchant_id', $ref->ref_number)
            ->with(['deliveryPlatformRefNumber', 'createSend', 'deliveryPlatformCampaignItemVend.deliveryProductMappingVend'])
            ->get()
            ->filter(fn ($campaign) => in_array($campaign->source(), [DeliveryPlatformLiveCampaign::SOURCE_CONNECTVEND, DeliveryPlatformLiveCampaign::SOURCE_LEFTOVER], true))
            ->filter(fn ($campaign) => ! $this->hasOpenSend($campaign->grab_campaign_id, DeliveryPlatformCampaignSend::ACTION_END));

        return $campaigns->isEmpty() ? null : $this->end($campaigns, $userId);
    }

    public function hasOpenSend(string $grabCampaignId, ?string $action = null): bool
    {
        return DeliveryPlatformCampaignSend::query()
            ->where('grab_campaign_id', $grabCampaignId)
            ->whereIn('status', [DeliveryPlatformCampaignSend::STATUS_PENDING, DeliveryPlatformCampaignSend::STATUS_SENDING])
            ->when($action, fn ($query, $action) => $query->where('action', $action))
            ->exists();
    }

    /** The queued job: one store, one call to Grab, once. */
    public function perform(int $sendId): void
    {
        $claimed = DeliveryPlatformCampaignSend::query()
            ->whereKey($sendId)
            ->where('status', DeliveryPlatformCampaignSend::STATUS_PENDING)
            ->update(['status' => DeliveryPlatformCampaignSend::STATUS_SENDING, 'sent_at' => now(), 'updated_at' => now()]);
        if (! $claimed) {
            return;
        }

        $send = DeliveryPlatformCampaignSend::query()->findOrFail($sendId);
        $account = $send->delivery_platform_operator_id
            ? DeliveryPlatformOperator::query()->with('externalOauthToken')->find($send->delivery_platform_operator_id)
            : null;

        if (! $account || ! $account->externalOauthToken?->access_token) {
            $this->finish($send, false, null, __('This operator has no Grab account in ConnectVend.'));

            return;
        }

        try {
            $grab = new Grab($account);
            $response = match ($send->action) {
                DeliveryPlatformCampaignSend::ACTION_CREATE => $grab->createCampaign($send->request_json),
                DeliveryPlatformCampaignSend::ACTION_UPDATE => $grab->updateCampaign($send->grab_campaign_id, $send->request_json),
                DeliveryPlatformCampaignSend::ACTION_END => $grab->deleteCampaign($send->grab_campaign_id),
            };
        } catch (\Throwable $e) {
            // No answer: a create may still have reached Grab; the next read will show it.
            $this->finish($send, false, null, __('No answer from Grab: :error', ['error' => $e->getMessage()]));
            $this->reread($send);

            return;
        }

        $ok = (bool) ($response['success'] ?? false);
        $data = $response['data'] ?? null;
        if ($ok && $send->action === DeliveryPlatformCampaignSend::ACTION_CREATE && ! empty($data['id'])) {
            $send->grab_campaign_id = (string) $data['id'];
        }
        $this->finish($send, $ok, $response, $ok ? null : $this->grabMessage($response));
        $this->reread($send);
    }

    /**
     * Grab's campaign body for one store, from the page's setup:
     * type, scope (items | category | order), object_ids, eater_type, value_cents,
     * cap_cents, qty, min_basket_cents, total_count, total_count_per_user, name,
     * starts_at, ends_at (Carbon, app time). Grab takes dollars and UTC.
     */
    public function payload(array $setup, string $merchantId): array
    {
        $days = collect(['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'])
            ->mapWithKeys(fn ($day) => [$day => ['periods' => [['startTime' => Grab::START_TIME, 'endTime' => Grab::END_TIME]]]])
            ->all();

        $scope = ['type' => $setup['scope']];
        if ($setup['scope'] !== Grab::CAMPAIGN_SCOPE_ORDER) {
            $scope['objectIDs'] = array_values(array_map('strval', $setup['object_ids'] ?? []));
        }

        $quotas = array_filter([
            'totalCount' => $setup['total_count'] ?? null,
            'totalCountPerUser' => $setup['total_count_per_user'] ?? null,
        ], fn ($value) => $value !== null && (int) $value > 0);

        $body = [
            'merchantID' => $merchantId,
            'name' => $setup['name'],
            'conditions' => [
                'startTime' => $this->utc($setup['starts_at']),
                'endTime' => $this->utc($setup['ends_at']),
                'eaterType' => $setup['eater_type'],
                'minBasketAmount' => ($setup['min_basket_cents'] ?? 0) / 100,
                'bundleQuantity' => (int) ($setup['qty'] ?? 0),
                'workingHour' => $days,
            ],
            'discount' => [
                'type' => $setup['type'],
                'cap' => ($setup['cap_cents'] ?? 0) / 100,
                'value' => ($setup['value_cents'] ?? 0) / 100,
                'scope' => $scope,
            ],
            'customTag' => '',
        ];
        if ($quotas) {
            $body['quotas'] = array_map('intval', $quotas);
        }

        return $body;
    }

    private function queue(string $batch, string $action, ?DeliveryPlatformRefNumber $ref, ?DeliveryPlatformLiveCampaign $campaign, ?array $body, ?int $userId): void
    {
        $merchantId = $campaign?->merchant_id ?? $ref?->ref_number;
        $account = $campaign?->delivery_platform_operator_id
            ?? ($ref?->operator_id ? $this->grabStoreService->grabAccountFor((int) $ref->operator_id)?->id : null);

        $send = DeliveryPlatformCampaignSend::query()->create([
            'batch' => $batch,
            'action' => $action,
            'status' => DeliveryPlatformCampaignSend::STATUS_PENDING,
            'user_id' => $userId,
            'delivery_platform_ref_number_id' => $ref?->id ?? $campaign?->delivery_platform_ref_number_id,
            'merchant_id' => (string) $merchantId,
            'delivery_platform_operator_id' => $account,
            'grab_campaign_id' => $campaign?->grab_campaign_id,
            'request_json' => $body,
        ]);

        SendGrabPromotion::dispatch($send->id)->afterCommit();
    }

    private function finish(DeliveryPlatformCampaignSend $send, bool $ok, ?array $response, ?string $message): void
    {
        $send->fill([
            'status' => $ok ? DeliveryPlatformCampaignSend::STATUS_OK : DeliveryPlatformCampaignSend::STATUS_FAILED,
            'response_code' => $response['code'] ?? null,
            'response_json' => $response['data'] ?? null,
            'response_message' => $message !== null ? mb_substr($message, 0, 1000) : null,
        ])->save();
    }

    // Read the store from Grab again so the list follows the send at once.
    private function reread(DeliveryPlatformCampaignSend $send): void
    {
        $ref = $send->deliveryPlatformRefNumber;
        if (! $ref) {
            return;
        }

        try {
            $this->mirror->refresh($ref);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function grabMessage(?array $response): string
    {
        $data = $response['data'] ?? null;
        $text = is_array($data) ? ($data['message'] ?? $data['error'] ?? $data['reason'] ?? null) : null;
        $code = $response['code'] ?? null;

        return trim(($code ? 'HTTP '.$code.' ' : '').($text ?: ($response['message'] ?? __('Grab refused it.'))));
    }

    private function utc($time): string
    {
        return Carbon::parse($time, config('app.timezone'))->setTimezone('UTC')->format('Y-m-d\TH:i:s\Z');
    }
}
