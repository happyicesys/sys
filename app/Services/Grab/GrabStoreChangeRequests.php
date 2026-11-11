<?php

namespace App\Services\Grab;

use App\Models\Address;
use App\Models\DeliveryPlatformRefNumber;
use App\Models\DeliveryPlatformStoreEvent;
use App\Models\Operator;
use App\Services\GrabStoreService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Asking Grab to change a store's details — its address when the machine moves to another site, its name, its hours
 * (Brian, 2026-11-11). Grab takes no new request by email (help@grab.com replies only on a CRM thread), so ConnectVend
 * prepares the request for the GrabMerchant app (one store: Store → Address, a go-live date, 2 address changes a month)
 * or Grab's Help Centre form (several stores), and keeps track of it: every request and its completion is a store
 * event, so the store's history says who asked what, by which route, for when, and when it was done.
 *
 * Only the HIPL (Unilever / HISG vending) stores: the other operators change their stores themselves in the
 * GrabMerchant app.
 */
class GrabStoreChangeRequests
{
    const KINDS = ['address', 'name', 'hours', 'other'];

    const ROUTES = ['app', 'form'];

    // Grab's own page for it; the form at its foot is the only way to open a new request.
    const HELP_CENTRE_URL = 'https://help.grab.com/merchant/en-sg/360027910531-%E2%9C%8D%EF%B8%8F-Request-to-update-store%27s-information';

    // Grab asks 1 to 2 days' lead for an address to go live (at 09:00 on the chosen day).
    const LEAD_DAYS = 2;

    public function __construct(private GrabStoreService $stores) {}

    public function eligible(DeliveryPlatformRefNumber $ref): bool
    {
        return (int) $ref->operator_id === self::hiplId();
    }

    /** What the request window starts with: the store, its machine, the machine's Site address, a go-live date. */
    public function draft(DeliveryPlatformRefNumber $ref): array
    {
        $binding = $ref->openDeliveryProductMappingVends()->withoutGlobalScopes()->with('customer.deliveryAddress')->first();
        $address = $binding?->customer?->deliveryAddress;

        return [
            'merchant_id' => $ref->ref_number,
            'vend_code' => $binding?->vend_code,
            'site' => $binding?->siteName(),
            'address' => $address ? self::addressLine($address) : null,
            'postal_code' => $address?->postcode,
            'go_live_on' => Carbon::today()->addDays(self::LEAD_DAYS)->toDateString(),
            'open' => $this->open(collect([$ref->id]))[$ref->id] ?? null,
            'help_centre_url' => self::HELP_CENTRE_URL,
        ];
    }

    /**
     * Records that a person sent the request to Grab (in the app or on the form).
     *
     * @param  array{kind: string, route: string, address?: ?string, postal_code?: ?string, go_live_on?: ?string, details?: ?string}  $data
     */
    public function requested(DeliveryPlatformRefNumber $ref, array $data, ?int $userId): DeliveryPlatformStoreEvent
    {
        $binding = $ref->openDeliveryProductMappingVends()->withoutGlobalScopes()->first();

        return $this->stores->event($ref, DeliveryPlatformStoreEvent::EVENT_GRAB_CHANGE_REQUESTED, $userId, $binding, null, [
            'kind' => $data['kind'],
            'route' => $data['route'],
            'address' => self::clean($data['address'] ?? null),
            'postal_code' => self::clean($data['postal_code'] ?? null),
            'go_live_on' => $data['go_live_on'] ?? null,
            'details' => self::clean($data['details'] ?? null),
            'vend_code' => $binding?->vend_code,
        ]);
    }

    // Grab shows the change: the open request is closed.
    public function done(DeliveryPlatformRefNumber $ref, ?string $note, ?int $userId): ?DeliveryPlatformStoreEvent
    {
        $open = $this->open(collect([$ref->id]))[$ref->id] ?? null;
        if (! $open) {
            return null;
        }

        return $this->stores->event($ref, DeliveryPlatformStoreEvent::EVENT_GRAB_CHANGE_DONE, $userId, null, null, [
            'request_id' => $open['id'],
            'kind' => $open['kind'],
            'note' => self::clean($note),
        ]);
    }

    /**
     * Each store's request still waiting for Grab: its newest request with no completion after it (two queries).
     *
     * @param  Collection<int, int>  $refIds
     * @return array<int, array> keyed by ref id
     */
    public function open(Collection $refIds): array
    {
        if ($refIds->isEmpty()) {
            return [];
        }
        $events = DeliveryPlatformStoreEvent::query()
            ->with('user:id,name')
            ->whereIn('delivery_platform_ref_number_id', $refIds)
            ->whereIn('event', [DeliveryPlatformStoreEvent::EVENT_GRAB_CHANGE_REQUESTED, DeliveryPlatformStoreEvent::EVENT_GRAB_CHANGE_DONE])
            ->orderBy('id')
            ->get()
            ->groupBy('delivery_platform_ref_number_id');

        $open = [];
        foreach ($events as $refId => $list) {
            $last = $list->last();
            if ($last->event !== DeliveryPlatformStoreEvent::EVENT_GRAB_CHANGE_REQUESTED) {
                continue;
            }
            $open[$refId] = [
                'id' => $last->id,
                'kind' => $last->data['kind'] ?? 'other',
                'route' => $last->data['route'] ?? null,
                'go_live_on' => $last->data['go_live_on'] ?? null,
                'address' => $last->data['address'] ?? null,
                'requested_at' => $last->created_at?->toIso8601String(),
                'requested_by' => $last->user?->name,
            ];
        }

        return $open;
    }

    // "Blk 180C Rivervale Crescent #01-02 Rivervale Arc" — the Site's own full address when it has one.
    public static function addressLine(Address $address): string
    {
        $full = trim((string) $address->full_address);
        if ($full !== '') {
            return $full;
        }
        $street = trim(implode(' ', array_filter([$address->block_num ? 'Blk '.$address->block_num : null, $address->street_name])));

        return implode(', ', array_filter([$street, $address->unit_num ? '#'.ltrim($address->unit_num, '#') : null, $address->building]));
    }

    private static function hiplId(): int
    {
        return once(fn () => (int) Operator::withoutGlobalScopes()->where('code', 'HIPL')->value('id'));
    }

    private static function clean(?string $text): ?string
    {
        $text = trim((string) $text);

        return $text === '' ? null : mb_substr($text, 0, 1000);
    }
}
