<?php

namespace App\Services\Grab;

use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryPlatformStoreEvent;
use App\Models\DeliveryProductMappingVend;
use App\Services\GrabStoreService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What customers see on Grab for each store — name, address, delivery hours — read once a day from the GrabMerchant
 * website's Store profile page (Brian, 2026-11-12: Grab's partner API gives no address, so a fix made in the
 * GrabMerchant app never reached ConnectVend). The read happens in a signed-in browser and is sent here; this class
 * keeps it on the store and compares Grab's postal code with the Site the machine is at now.
 *
 * When they match and the machine had moved Site, Grab already shows the new address: the "Site changed" note on
 * Grab Stores clears by itself (an open address request is marked done; otherwise the link records the new Site).
 */
class GrabStoreProfiles
{
    public function __construct(private GrabStoreChangeRequests $changes, private GrabStoreService $stores) {}

    /**
     * The Store profile page's text (its `main` element), line by line:
     *   <store name> · Edit · Delivery hours · • · <hours> · Edit · <address> · Edit · <phone> … Store ID · <id>
     *
     * @return array{merchant_id: ?string, name: ?string, hours: ?string, address: ?string}
     */
    public static function parse(string $text): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/', $text)), fn ($line) => $line !== '' && $line !== '•' && $line !== 'Edit'));
        $after = function (string $label) use ($lines): ?string {
            $i = array_search($label, $lines, true);

            return $i === false ? null : ($lines[$i + 1] ?? null);
        };

        $hoursAt = array_search('Delivery hours', $lines, true);

        return [
            'merchant_id' => $after('Store ID'),
            'name' => $lines[0] ?? null,
            'hours' => $hoursAt === false ? null : ($lines[$hoursAt + 1] ?? null),
            'address' => $hoursAt === false ? null : ($lines[$hoursAt + 2] ?? null),
        ];
    }

    // The last 6-digit group (Singapore), else the last 5-digit group (Indonesia).
    public static function postalCode(?string $address): ?string
    {
        foreach (['/\b(\d{6})\b/', '/\b(\d{5})\b/'] as $pattern) {
            if ($address && preg_match_all($pattern, $address, $matches)) {
                return end($matches[1]);
            }
        }

        return null;
    }

    /**
     * Saves what was read. Each store: merchant_id plus either the page `text` or `name` / `address` / `hours`.
     * Stores outside $operatorIds, or not on file, are only counted.
     *
     * @param  array<int, array{merchant_id?: ?string, text?: ?string, name?: ?string, address?: ?string, hours?: ?string}>  $stores
     * @param  int[]  $operatorIds
     * @return array{read: int, changed: int, unknown: string[], site_confirmed: string[], mismatched: string[]}
     */
    public function import(array $stores, array $operatorIds, ?int $userId, ?Carbon $checkedAt = null): array
    {
        $checkedAt ??= now();
        $result = ['read' => 0, 'changed' => 0, 'unknown' => [], 'site_confirmed' => [], 'mismatched' => []];

        foreach ($stores as $store) {
            $read = ! empty($store['text']) ? self::parse((string) $store['text']) : [];
            $merchantId = strtoupper(trim((string) ($store['merchant_id'] ?? $read['merchant_id'] ?? '')));
            $name = self::clean($store['name'] ?? $read['name'] ?? null, 255);
            $address = self::clean($store['address'] ?? $read['address'] ?? null, 500);
            $hours = self::clean($store['hours'] ?? $read['hours'] ?? null, 255);
            if ($merchantId === '' || $address === null) {
                continue;
            }

            $ref = DeliveryPlatformRefNumber::query()->where('ref_number', $merchantId)->whereIn('operator_id', $operatorIds)->first();
            if (! $ref) {
                $result['unknown'][] = $merchantId;

                continue;
            }
            $result['read']++;

            $outcome = $this->save($ref, $name, $address, $hours, $checkedAt, $userId);
            if ($outcome['changed']) {
                $result['changed']++;
            }
            if ($outcome['site_confirmed']) {
                $result['site_confirmed'][] = $merchantId;
            }
            if ($outcome['match'] === false) {
                $result['mismatched'][] = $merchantId;
            }
        }

        return $result;
    }

    /** @return array{changed: bool, site_confirmed: bool, match: ?bool} */
    private function save(DeliveryPlatformRefNumber $ref, ?string $name, string $address, ?string $hours, Carbon $checkedAt, ?int $userId): array
    {
        $postal = self::postalCode($address);
        $changes = [];
        if ($ref->grab_store_name !== $name) {
            $changes['name'] = ['from' => $ref->grab_store_name, 'to' => $name];
        }
        if ($ref->grab_address !== $address) {
            $changes['address'] = ['from' => $ref->grab_address, 'to' => $address];
        }

        $binding = $ref->openDeliveryProductMappingVends()->withoutGlobalScopes()
            ->with(['vend' => fn ($query) => $query->withoutGlobalScopes(), 'vend.customer' => fn ($query) => $query->withoutGlobalScopes()->with('deliveryAddress')])
            ->first();
        $match = self::matches($postal, $binding);
        $siteConfirmed = false;

        DB::transaction(function () use ($ref, $name, $address, $postal, $hours, $checkedAt, $userId, $binding, $match, &$changes, &$siteConfirmed) {
            $ref->forceFill([
                'grab_store_name' => $name,
                'grab_address' => $address,
                'grab_postal_code' => $postal,
                'grab_hours' => $hours,
                'grab_profile_checked_at' => $checkedAt,
            ])->save();

            // The machine moved Site and Grab already shows the new one: nothing left to ask Grab.
            if ($match && $binding->siteChangedFrom() !== null) {
                $siteConfirmed = true;
                $open = $this->changes->open(collect([$ref->id]))[$ref->id] ?? null;
                if ($open && $open['kind'] === 'address') {
                    $this->changes->done($ref, __('Grab shows the new address (GrabMerchant check).'), $userId);
                } else {
                    $changes['site'] = ['from' => $binding->customer_id, 'to' => $binding->vend->customer_id];
                    $binding->forceFill(['customer_id' => $binding->vend->customer_id])->save();
                }
            }

            if ($changes) {
                $this->stores->event($ref, DeliveryPlatformStoreEvent::EVENT_GRAB_PROFILE, $userId, $binding, null, $changes + ['postal_code' => $postal]);
            }
        });

        return ['changed' => (bool) $changes, 'site_confirmed' => $siteConfirmed, 'match' => $match];
    }

    /** Grab's postal code against the postal code of the Site the machine is at now; null when either is unknown. */
    public static function matches(?string $grabPostal, ?DeliveryProductMappingVend $binding): ?bool
    {
        $sitePostal = self::sitePostalCode($binding);

        return $grabPostal === null || $sitePostal === null ? null : $grabPostal === $sitePostal;
    }

    public static function sitePostalCode(?DeliveryProductMappingVend $binding): ?string
    {
        $postal = trim((string) $binding?->currentSite()?->deliveryAddress?->postcode);

        return $postal === '' ? null : $postal;
    }

    private static function clean(mixed $value, int $max): ?string
    {
        $value = trim(preg_replace('/\s+/', ' ', (string) $value));

        return $value === '' ? null : mb_substr($value, 0, $max);
    }
}
