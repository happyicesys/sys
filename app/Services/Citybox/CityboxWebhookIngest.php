<?php

namespace App\Services\Citybox;

use App\Models\CityboxWebhookEvent;
use App\Models\Vend;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;

/**
 * Ingests one CityBox-Openapi webhook push (order / refund / close).
 *
 * Contract with the controller: ingest() returns true only when the event is
 * DURABLY stored (fresh insert or already-stored duplicate) — that boolean
 * becomes the {success:true} ack that stops their retry loop. Anything that
 * should be retried (bad signature, disabled integration, DB failure)
 * returns false / throws.
 *
 * Scoping: this whole surface exists only for machine_type = smart_chiller
 * vends. Device matching goes box_no → vends.citybox_equipment_id; a push
 * for an unknown device is still stored (audit + import backlog) with
 * vend_id NULL and logged, mirroring the poller's never-auto-create rule.
 */
class CityboxWebhookIngest
{
    /**
     * @param  string  $type  CityboxWebhookEvent::TYPE_*
     * @param  array  $params  raw form fields as received (app_id, sign, data, ...)
     */
    public function ingest(string $type, array $params): bool
    {
        $secret = (string) config('citybox.openapi.secret');
        if (! config('citybox.openapi.enabled') || $secret === '') {
            // Not configured: refuse the ack so Citybox keeps retrying and no
            // order is lost while credentials/config are pending. (Close pushes
            // fire only once regardless — nothing we can do about that here.)
            Log::warning('Citybox webhook received while openapi disabled/unconfigured', ['type' => $type]);

            return false;
        }

        $variant = OpenapiSigner::verify($params, $secret);

        $rawData = $params['data'] ?? '';
        // Anything that isn't a JSON object/array — invalid JSON (null) but
        // also a bare scalar like `123` or `"abc"` — must fall back to [], or
        // the array-typed helpers below throw and the push is never stored.
        $decoded = is_array($rawData) ? $rawData : json_decode((string) $rawData, true);
        $payload = is_array($decoded) ? $decoded : [];
        // Malformed data must NOT collapse to a shared key (md5 of an empty
        // payload is a constant — every broken push would dedupe into one row
        // and silently drop the rest). Key such events by their raw bytes.
        $keyFallback = md5(is_string($rawData) ? $rawData : json_encode($rawData));
        // Refund pushes carry order_name beside data; fold it in so the stored
        // payload is self-contained.
        if (isset($params['order_name']) && ! isset($payload['order_name'])) {
            $payload['order_name'] = $params['order_name'];
        }

        if ($variant === null) {
            // Store for forensics (someone is POSTing at us with bad/no sign),
            // but never ack success and never attach a vend.
            $this->store($type, $this->eventKey($type, $payload, $keyFallback).':invalid:'.substr(md5(serialize($params)), 0, 8), null, $payload, $rawData, null);
            Log::warning('Citybox webhook signature verification FAILED', ['type' => $type]);

            return false;
        }

        $vend = $this->matchVend($payload);
        $stored = $this->store($type, $this->eventKey($type, $payload, $keyFallback), $vend?->id, $payload, $rawData, $variant);

        if ($vend === null && $boxNo = $this->boxNo($payload)) {
            Log::notice('Citybox webhook for unlinked device — stored, no vend matched', [
                'type' => $type,
                'box_no' => $boxNo,
            ]);
        }

        return $stored;
    }

    /**
     * Idempotency key per push type. Their retries resend identical content,
     * so a stable natural key per event is enough:
     *  - order: open_log_id (present for both real and empty-order pushes)
     *  - refund: order_name + refund_status (one refund progresses through
     *    statuses; each status change is a distinct event worth keeping)
     *  - close: open_log_id (pushed once, but idempotent anyway)
     */
    private function eventKey(string $type, array $payload, string $fallback): string
    {
        return match ($type) {
            CityboxWebhookEvent::TYPE_ORDER => (string) ($payload['open_log_id'] ?? $fallback),
            CityboxWebhookEvent::TYPE_REFUND => isset($payload['order_name'])
                ? $payload['order_name'].':'.($payload['refund_status'] ?? '?')
                : $fallback,
            CityboxWebhookEvent::TYPE_CLOSE => (string) ($payload['open_log_id'] ?? $fallback),
            default => $fallback,
        };
    }

    private function boxNo(array $payload): ?string
    {
        // order push nests the device in order.box_no; close push has box_no at
        // the top level; refund push carries no device at all.
        return $payload['box_no']
            ?? $payload['order']['box_no']
            ?? null;
    }

    private function matchVend(array $payload): ?Vend
    {
        $boxNo = $this->boxNo($payload);
        if (! $boxNo) {
            return null;
        }

        return Vend::where('citybox_equipment_id', $boxNo)
            ->where('machine_type', Vend::MACHINE_TYPE_SMART_CHILLER)
            ->first();
    }

    /** True when the row exists after the call (insert OR pre-existing duplicate). */
    private function store(string $type, string $eventKey, ?int $vendId, array $payload, mixed $rawData, ?string $variant): bool
    {
        $raw = is_string($rawData) ? $rawData : json_encode($rawData);

        try {
            CityboxWebhookEvent::create([
                'type' => $type,
                'event_key' => $eventKey,
                'vend_id' => $vendId,
                'payload' => $payload,
                'raw_data' => $raw,
                'signature_variant' => $variant,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Retry of an already-stored push — exactly what idempotency is for.
            // But a duplicate whose CONTENT differs is not a retry: it would mean
            // Citybox re-pushed a revised event under the same key — the exact
            // evidence that falsifies the "qty is final, corrections only via
            // refund" assumption. We keep v1 (ledger consistency) and log v2
            // loudly; the raw bytes of both versions survive for comparison.
            $existing = CityboxWebhookEvent::where('type', $type)->where('event_key', $eventKey)->first();
            if ($existing && $existing->raw_data !== $raw) {
                Log::warning('Citybox webhook DUPLICATE KEY WITH DIFFERENT CONTENT — supplier re-pushed a revised event', [
                    'type' => $type,
                    'event_key' => $eventKey,
                    'stored_at' => (string) $existing->created_at,
                    'new_raw_data' => $raw,
                ]);
            }
        }

        return true;
    }
}
