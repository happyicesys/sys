<?php

namespace App\Services\Grab;

use App\Models\DeliveryPlatformOperator;
use App\Models\DeliveryPlatforms\Grab;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Keeps every live Grab account's access token valid (2026-11-01). A Grab token lasts
 * 7 days and nothing renewed one on a schedule: only a menu notify that got 401 asked for
 * a new one, so an account whose stores were paused or quiet lapsed — DCVIC's lapsed
 * 2026-10-29 19:40 and every call for its 11 stores was refused from that evening; LEA's
 * lapsed 2026-10-31 00:00. The expiry was never kept either (Grab's "expires_in" seconds
 * were written into a datetime, so every row read 0000-00-00).
 *
 * grab:renew-tokens runs hourly and renews a token within RENEW_MARGIN_HOURS of its expiry;
 * a call answered 401 renews at once (renewIfRefused) and tries again.
 */
class GrabTokenKeeper
{
    const RENEW_MARGIN_HOURS = 24;

    // A failed renewal is not tried again for this long (one Grab Errors row, not 24 a day).
    const FAILURE_BACKOFF_HOURS = 6;

    /** @return Collection<int, DeliveryPlatformOperator> live Grab accounts with credentials */
    public function accounts(): Collection
    {
        return DeliveryPlatformOperator::query()
            ->where('type', 'production')
            ->whereHas('deliveryPlatform', fn ($query) => $query->where('slug', 'grab'))
            ->whereHas('externalOauthToken', fn ($query) => $query->whereNotNull('client_id')->whereNotNull('client_secret'))
            ->with('externalOauthToken', 'operator:id,code')
            ->orderBy('id')
            ->get();
    }

    public function expiresAt(DeliveryPlatformOperator $account): ?Carbon
    {
        $raw = $account->externalOauthToken?->getRawOriginal('expired_at');
        if (! $raw || str_starts_with((string) $raw, '0000')) {
            return null;
        }

        return Carbon::parse($raw);
    }

    // Unknown expiry counts as due: the first run after this change renews every account once.
    public function isDue(DeliveryPlatformOperator $account): bool
    {
        $expiresAt = $this->expiresAt($account);

        return ! $expiresAt || $expiresAt->lt(now()->addHours(self::RENEW_MARGIN_HOURS));
    }

    /**
     * Ask Grab for a new token and keep it with its real expiry.
     *
     * @return bool false when Grab refused or did not answer (kept on Grab Errors)
     */
    public function renew(DeliveryPlatformOperator $account): bool
    {
        try {
            $response = (new Grab($account))->getOauthToken();
        } catch (\Throwable $e) {
            $response = ['success' => false, 'message' => $e->getMessage()];
        }

        $data = $response['data'] ?? null;
        if (! ($response['success'] ?? false) || empty($data['access_token'])) {
            Cache::put($this->backoffKey($account), true, now()->addHours(self::FAILURE_BACKOFF_HOURS));
            Log::warning('Grab token renewal failed', [
                'delivery_platform_operator_id' => $account->id,
                'code' => $response['code'] ?? null,
                'message' => $response['message'] ?? null,
            ]);

            return false;
        }

        $account->externalOauthToken->update([
            'access_token' => $data['access_token'],
            'token_type' => $data['token_type'] ?? 'Bearer',
            'expired_at' => self::expiryFrom($data['expires_in'] ?? null),
        ]);
        Cache::forget($this->backoffKey($account));
        $account->unsetRelation('externalOauthToken');

        return true;
    }

    /**
     * Renew every account due, skipping one whose last renewal failed within the backoff.
     *
     * @return array<int, string> account id => renewed / failed / backoff / valid
     */
    public function renewDue(bool $force = false): array
    {
        $results = [];
        foreach ($this->accounts() as $account) {
            if (! $force && ! $this->isDue($account)) {
                $results[$account->id] = 'valid';
            } elseif (! $force && Cache::has($this->backoffKey($account))) {
                $results[$account->id] = 'backoff';
            } else {
                $results[$account->id] = $this->renew($account) ? 'renewed' : 'failed';
            }
        }

        return $results;
    }

    /**
     * Run a Grab call; on 401, renew the account's token once and run it again with the
     * fresh account. $call gets the account and returns Grab's response array.
     */
    public function retryOnUnauthorized(DeliveryPlatformOperator $account, callable $call): array
    {
        $response = $call($account);
        if (($response['code'] ?? null) !== 401 || Cache::has($this->backoffKey($account)) || ! $this->renew($account)) {
            return $response;
        }

        return $call($account->fresh(['externalOauthToken', 'operator', 'deliveryPlatform']));
    }

    // Grab's expires_in is seconds from now (604799 = 7 days).
    public static function expiryFrom($expiresIn): ?Carbon
    {
        return is_numeric($expiresIn) && (int) $expiresIn > 0 ? now()->addSeconds((int) $expiresIn) : null;
    }

    private function backoffKey(DeliveryPlatformOperator $account): string
    {
        return 'grab-token-renew-failed:'.$account->id;
    }
}
