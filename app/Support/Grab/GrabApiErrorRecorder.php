<?php

namespace App\Support\Grab;

use App\Models\DeliveryPlatformApiError;
use App\Models\DeliveryPlatformRefNumber;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * The only writer of delivery_platform_api_errors (Brian, 2026-10-30: "store their
 * logs … and the callbacks … if any failure"). Three doors:
 * - outgoing(): a call to Grab answered >= 400 or not at all (HTTP client events,
 *   AppServiceProvider), whatever code made it;
 * - incoming(): a Grab callback we answered >= 400 (RecordGrabCallbackFailures);
 * - missedOrder(): an order Grab lists that never reached us (grab:missed-orders).
 * Never throws: recording a failure must not become one. Secrets (tokens, client
 * secrets) and the customer (receiver) are never stored.
 */
class GrabApiErrorRecorder
{
    // Context key the Grab model sets, naming the account a call is made with.
    public const CONTEXT_OPERATOR = 'grab.delivery_platform_operator_id';

    private const BODY_LIMIT = 8000;

    private const SECRET_KEYS = '/secret|token|password|authorization|client_id/i';

    // Path (after the endpoint base) → action, first match wins.
    private const OUTGOING_ACTIONS = [
        '#/grabid/v1/oauth2/token#' => 'oauth_token',
        '#/partner/v1/menu/categories#' => 'menu_categories',
        '#/partner/v1/merchant/menu/notification#' => 'menu_notify',
        '#/partner/v1/merchant/menu/trace#' => 'menu_trace',
        '#/partner/v1/batch/menu#' => 'menu_batch_update',
        '#/partner/v1/menu$#' => 'menu_update',
        '#/partner/v1/order/prepare#' => 'order_accept',
        '#/partner/v1/orders/mark#' => 'order_ready',
        '#/partner/v1/orders$#' => 'order_list',
        '#/partner/v[12]/orders/#' => 'order_edit',
        '#/partner/v1/order/delivery#' => 'order_delivery_state',
        '#/partner/v1/order/readytime#' => 'order_ready_time',
        '#/partner/v1/order/cancelable#' => 'order_cancelable',
        '#/partner/v1/order/cancel#' => 'order_cancel',
        '#/partner/v1/merchant/pause#' => 'store_pause',
        '#/store/status#' => 'store_status',
        '#/store/(hours|opening-hours|dine-in-hours|special-opening-hour)#' => 'store_hours',
        '#/partner/v1/campaigns/#' => 'campaign_change',
        '#/partner/v1/campaigns$#' => 'campaign_create_or_list',
    ];

    private const INCOMING_ACTIONS = [
        'merchant/menu' => 'callback_menu',
        'order/create' => 'callback_order_create',
        'order/update' => 'callback_order_update',
        'sync-menu-webhook' => 'callback_menu_sync',
    ];

    public static function isGrabUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return $host === 'grab.com' || str_ends_with($host, '.grab.com');
    }

    /** A call to Grab that failed: $response null = no answer ($error says why). */
    public static function outgoing(ClientRequest $request, ?ClientResponse $response, ?string $error = null): void
    {
        self::safely(function () use ($request, $response, $error) {
            $url = $request->url();
            $path = (string) parse_url($url, PHP_URL_PATH);
            $method = strtoupper($request->method());
            $isToken = str_contains($path, '/oauth2/token');
            $body = $isToken ? null : self::decode($request->body());
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

            $merchantId = Arr::get($body ?? [], 'merchantID')
                ?? ($query['merchantID'] ?? null)
                ?? $request->header('merchantID')[0] ?? null
                ?? (preg_match('#/merchants/([^/]+)/#', $path, $m) ? rawurldecode($m[1]) : null);
            $orderId = Arr::get($body ?? [], 'orderID') ?? ($query['orderID'] ?? null)
                ?? (preg_match('#/orders/([^/]+)$#', $path, $m) && $m[1] !== 'mark' ? $m[1] : null);

            $action = self::outgoingAction($path, $method);
            // Pausing a store Grab already has closed answers 409: nothing failed.
            if ($action === 'store_pause' && $response?->status() === 409) {
                return;
            }

            $responseBody = $response ? self::decode($response->body()) : null;

            self::store([
                'direction' => DeliveryPlatformApiError::DIRECTION_OUTGOING,
                'action' => $action,
                'http_method' => $method,
                'path' => Str::limit($path, 250, ''),
                'status' => $response?->status(),
                'merchant_id' => $merchantId,
                'order_id' => $orderId,
                'delivery_platform_operator_id' => Context::getHidden(self::CONTEXT_OPERATOR),
                'message' => $response ? self::grabMessage($responseBody, $response->body()) : ($error ?: 'No answer from Grab'),
                'request_json' => $body === null ? null : self::clean(array_merge($query ? ['query' => $query] : [], $body)),
                'response_json' => $responseBody === null ? null : self::clean($responseBody),
            ]);
        });
    }

    /** A Grab callback we answered with an error. */
    public static function incoming(Request $request, Response $response): void
    {
        self::safely(function () use ($request, $response) {
            $path = $request->path();
            $payload = $request->isMethod('GET') ? $request->query() : $request->all();
            $exception = $response->exception ?? null;
            $answer = self::decode((string) $response->getContent());

            $message = $exception
                ? class_basename($exception).': '.$exception->getMessage()
                : (is_array($answer) ? ($answer['error_message'] ?? $answer['message'] ?? null) : null);

            self::store([
                'direction' => DeliveryPlatformApiError::DIRECTION_INCOMING,
                'action' => self::incomingAction($path),
                'http_method' => $request->method(),
                'path' => Str::limit('/'.$path, 250, ''),
                'status' => $response->getStatusCode(),
                'merchant_id' => $request->input('merchantID') ?? $request->input('order.merchantID'),
                'order_id' => $request->input('orderID') ?? $request->input('order.orderID'),
                'message' => Str::limit($message ?: 'HTTP '.$response->getStatusCode(), 2000),
                'request_json' => self::clean(Arr::except($payload, ['receiver'])),
                'response_json' => is_array($answer) ? self::clean($answer) : null,
            ]);
        });
    }

    /** An order Grab has that never reached mark1 (once per order). */
    public static function missedOrder(string $merchantId, array $order, ?int $deliveryPlatformOperatorId): void
    {
        self::safely(function () use ($merchantId, $order, $deliveryPlatformOperatorId) {
            $orderId = (string) ($order['orderID'] ?? '');
            if ($orderId === '' || DeliveryPlatformApiError::query()->where('order_id', $orderId)->where('action', 'order_missed')->exists()) {
                return;
            }

            self::store([
                'direction' => DeliveryPlatformApiError::DIRECTION_INCOMING,
                'action' => 'order_missed',
                'merchant_id' => $merchantId,
                'order_id' => $orderId,
                'delivery_platform_operator_id' => $deliveryPlatformOperatorId,
                'message' => sprintf(
                    'Grab has order %s (%s, %s) but it never reached mark1',
                    $order['shortOrderNumber'] ?? $orderId,
                    $order['orderState'] ?? 'state unknown',
                    $order['orderTime'] ?? ($order['submitTime'] ?? 'time unknown'),
                ),
                'response_json' => self::clean(Arr::except($order, ['receiver'])),
            ]);
        });
    }

    private static function store(array $row): void
    {
        if (! empty($row['merchant_id'])) {
            $row['merchant_id'] = Str::limit((string) $row['merchant_id'], 250, '');
            $row['delivery_platform_ref_number_id'] = DeliveryPlatformRefNumber::query()
                ->where('ref_number', $row['merchant_id'])->value('id');
        }
        if (! empty($row['order_id'])) {
            $row['order_id'] = Str::limit((string) $row['order_id'], 250, '');
        }

        DeliveryPlatformApiError::create($row);
    }

    private static function outgoingAction(string $path, string $method): string
    {
        foreach (self::OUTGOING_ACTIONS as $pattern => $action) {
            if (preg_match($pattern, $path)) {
                return match ($action) {
                    'campaign_change' => $method === 'DELETE' ? 'campaign_delete' : 'campaign_update',
                    'campaign_create_or_list' => $method === 'GET' ? 'campaign_list' : 'campaign_create',
                    default => $action,
                };
            }
        }

        return 'grab_call';
    }

    private static function incomingAction(string $path): string
    {
        foreach (self::INCOMING_ACTIONS as $suffix => $action) {
            if (str_ends_with($path, $suffix)) {
                return $action;
            }
        }

        return 'callback';
    }

    // Grab's error body is {"reason": "...", "message": "..."}, its message sometimes a
    // JSON string of its own ({"statusCode":409,"target":"NotCoverOpeningHours",…}).
    private static function grabMessage(?array $body, string $raw): string
    {
        if (! is_array($body)) {
            return Str::limit(trim($raw) ?: 'Empty answer', 2000);
        }

        $parts = array_filter([
            $body['reason'] ?? null,
            $body['target'] ?? null,
            is_string($body['message'] ?? null) ? $body['message'] : null,
            is_string($body['error'] ?? null) ? $body['error'] : null,
            is_string($body['error_description'] ?? null) ? $body['error_description'] : null,
        ], fn ($part) => $part !== null && $part !== '');

        return Str::limit($parts ? implode(' — ', array_unique($parts)) : json_encode($body), 2000);
    }

    private static function decode(?string $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : ['raw' => Str::limit($raw, self::BODY_LIMIT)];
    }

    // Secrets out, then capped so one huge menu cannot bloat the table.
    private static function clean(array $data): array
    {
        $walk = function (array $data) use (&$walk): array {
            foreach ($data as $key => $value) {
                if (is_string($key) && preg_match(self::SECRET_KEYS, $key)) {
                    $data[$key] = '[removed]';
                } elseif (is_array($value)) {
                    $data[$key] = $walk($value);
                }
            }

            return $data;
        };
        $data = $walk($data);

        $json = json_encode($data);

        return strlen((string) $json) > self::BODY_LIMIT
            ? ['truncated' => true, 'raw' => Str::limit((string) $json, self::BODY_LIMIT)]
            : $data;
    }

    private static function safely(callable $record): void
    {
        try {
            $record();
        } catch (\Throwable $e) {
            Log::warning('Could not record a Grab API error', ['error' => $e->getMessage()]);
        }
    }
}
