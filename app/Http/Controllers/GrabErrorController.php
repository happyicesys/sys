<?php

namespace App\Http\Controllers;

use App\Models\DeliveryPlatformApiError;
use App\Models\DeliveryPlatformRefNumber;
use App\Support\Grab\GrabStoreVisibility;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Grab Errors (Brian, 2026-10-30): every failed exchange with Grab in the last 30
 * days, both ways. Read only; GrabApiErrorRecorder writes it.
 */
class GrabErrorController extends Controller
{
    public function index(Request $request)
    {
        $direction = in_array($request->direction, ['outgoing', 'incoming'], true) ? $request->direction : 'all';
        $action = array_key_exists((string) $request->action, DeliveryPlatformApiError::ACTIONS) ? $request->action : 'all';
        $search = trim((string) $request->search);
        $tz = 'Asia/Singapore';
        $from = $request->date_from ? Carbon::parse($request->date_from, $tz)->startOfDay() : Carbon::today($tz)->subDays(6);
        $to = $request->date_to ? Carbon::parse($request->date_to, $tz)->endOfDay() : Carbon::today($tz)->endOfDay();

        $errors = $this->visible(DeliveryPlatformApiError::query())
            ->with(['deliveryPlatformRefNumber:id,ref_number,operator_id', 'deliveryPlatformOperator:id,operator_id,type'])
            ->whereBetween('created_at', [$from->copy()->setTimezone(config('app.timezone')), $to->copy()->setTimezone(config('app.timezone'))])
            ->when($direction !== 'all', fn ($query) => $query->where('direction', $direction))
            ->when($action !== 'all', fn ($query) => $query->where('action', $action))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('merchant_id', 'LIKE', "%{$search}%")
                ->orWhere('order_id', 'LIKE', "%{$search}%")
                ->orWhere('message', 'LIKE', "%{$search}%")))
            ->latest('id')
            ->paginate((int) ($request->numberPerPage ?: 50))
            ->withQueryString();

        // Which machine each store is on now, for the row's Machine ID link.
        $machines = \App\Models\DeliveryProductMappingVend::query()->withoutGlobalScopes()
            ->whereNull('end_date')
            ->whereIn('delivery_platform_ref_number_id', $errors->getCollection()->pluck('delivery_platform_ref_number_id')->filter()->unique())
            ->pluck('vend_code', 'delivery_platform_ref_number_id');

        return Inertia::render('GrabError/Index', [
            'errors' => [
                'data' => $errors->getCollection()->map(fn ($error) => self::row($error) + [
                    'vend_code' => $machines[$error->delivery_platform_ref_number_id] ?? null,
                ])->values(),
                'links' => ['prev' => $errors->previousPageUrl(), 'next' => $errors->nextPageUrl()],
                'meta' => ['from' => $errors->firstItem(), 'to' => $errors->lastItem(), 'total' => $errors->total()],
            ],
            'filters' => [
                'direction' => $direction,
                'action' => $action,
                'search' => $search,
                'date_from' => $from->toDateString(),
                'date_to' => $to->toDateString(),
            ],
            'actionOptions' => collect(DeliveryPlatformApiError::ACTIONS)->map(fn ($name, $key) => ['id' => $key, 'name' => $name])->values(),
            'keepDays' => DeliveryPlatformApiError::KEEP_DAYS,
        ]);
    }

    public static function row(DeliveryPlatformApiError $error): array
    {
        return [
            'id' => $error->id,
            'at' => $error->created_at?->toIso8601String(),
            'direction' => $error->direction,
            'action' => $error->action,
            'action_name' => DeliveryPlatformApiError::ACTIONS[$error->action] ?? $error->action,
            'http_method' => $error->http_method,
            'path' => $error->path,
            'status' => $error->status,
            'merchant_id' => $error->merchant_id,
            'order_id' => $error->order_id,
            'message' => $error->message,
            'request_json' => $error->request_json,
            'response_json' => $error->response_json,
        ];
    }

    // A store's errors follow the store's operator; one with no store (a login, a
    // category list) is HIPL's to see.
    private function visible($query)
    {
        if (GrabStoreVisibility::isHipl()) {
            return $query;
        }

        $refIds = DeliveryPlatformRefNumber::query()->whereIn('operator_id', GrabStoreVisibility::operatorIds())->pluck('id');

        return $query->whereIn('delivery_platform_ref_number_id', $refIds);
    }
}
