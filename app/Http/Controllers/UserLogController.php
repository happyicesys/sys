<?php

namespace App\Http\Controllers;

use App\Models\UserLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Read-only JSON feed for the reusable history drawer (HistoryButton.vue).
 *
 * Keyset pagination: returns the newest PER_PAGE rows; pass ?before=<id> to
 * fetch the next older page. Cheaper and stable under inserts vs OFFSET.
 */
class UserLogController extends Controller
{
    private const PER_PAGE = 10;

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type'   => ['required', 'string', 'max:120'], // model basename, e.g. "Customer"
            'id'     => ['nullable', 'integer'],           // omit for type-wide history
            'before' => ['nullable', 'integer'],           // keyset cursor
        ]);

        // Resolve to a concrete model class, constrained to App\Models — strip
        // any path/namespace separators so only a basename can ever be used.
        $class = 'App\\Models\\' . preg_replace('/[^A-Za-z0-9_]/', '', $data['type']);

        $rows = UserLog::query()
            ->where('auditable_type', $class)
            ->when(isset($data['id']), fn ($q) => $q->where('auditable_id', $data['id']))
            ->when(isset($data['before']), fn ($q) => $q->where('id', '<', $data['before']))
            ->orderByDesc('id')
            ->limit(self::PER_PAGE + 1) // +1 sentinel to detect "has more"
            ->get(['id', 'user_id', 'user_name', 'event', 'auditable_type', 'auditable_id', 'changes', 'created_at']);

        $hasMore = $rows->count() > self::PER_PAGE;
        $rows    = $rows->take(self::PER_PAGE);

        return response()->json([
            'data' => $rows->map(fn (UserLog $log) => [
                'id'         => $log->id,
                'event'      => $log->event,
                'who'        => $log->user_name ?: ('User #' . $log->user_id),
                'entity'     => class_basename($log->auditable_type) . ' #' . $log->auditable_id,
                'changes'    => $log->changes,
                'created_at' => optional($log->created_at)->toIso8601String(),
            ])->values(),
            'next_before' => $hasMore ? $rows->last()->id : null,
        ]);
    }
}
