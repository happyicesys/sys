<?php

namespace App\Http\Controllers;

use App\Models\FreezerControlCommand;
use App\Models\FreezerSetpointSchedule;
use App\Models\Vend;
use App\Services\Freezer\FreezerControlService;
use App\Services\Freezer\FreezerPhotoThumbnail;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Setting/Edit > Smart Freezer > Remote controls.
 *
 * `show` is polled by the page (fast while a command is pending, slow otherwise); `store` sends one
 * command. Both are JSON: the page never reloads Inertia props for this panel.
 */
class FreezerControlController extends Controller
{
    /** Opening the door remotely is unmetered stock access, so it has its own permission. */
    public const DOOR_PERMISSION = 'update freezer-remote-door';

    /** How many recent stills the panel keeps PER CAMERA — the viewer shows one row each. */
    public const PHOTO_HISTORY = 5;

    /**
     * How far back to look for them. A camera nobody has photographed in the last [self::PHOTO_SCAN]
     * shots simply has no history to show, which is the truth rather than an expensive full scan.
     */
    private const PHOTO_SCAN = 120;

    /** A stored still never changes, so a viewer may keep it; it is private to the operator. */
    private const PHOTO_CACHE_CONTROL = 'private, max-age=31536000, immutable';

    /** A raw SDK call can ask the host anything its plugin answers to; superadmin only. */
    public const SDK_RAW_PERMISSION = 'update freezer-sdk-raw';

    public function __construct(private FreezerControlService $service) {}

    public function show(Request $request, Vend $vend): JsonResponse
    {
        abort_unless($vend->isSmartFreezer(), 404);

        $now = Carbon::now();
        // The page asks for the newest 20; `total` lets it say "last 20 of 143" so nobody assumes
        // the list is everything. Rows are kept for good (only log files and excerpts are pruned
        // at 72 h), and a busy machine writes a few hundred event rows a day.
        $total = FreezerControlCommand::where('vend_id', $vend->id)->count();
        $commands = FreezerControlCommand::where('vend_id', $vend->id)
            ->orderByDesc('id')
            ->limit((int) $request->integer('limit', 30) > 0 ? min(200, $request->integer('limit', 30)) : 30)
            ->get();

        $status = $vend->freezer_control_status_json;
        if (is_string($status)) {
            $status = json_decode($status, true);
        }
        $periodic = $vend->freezer_status_json;
        if (is_string($periodic)) {
            $periodic = json_decode($periodic, true);
        }

        return response()->json([
            'supported' => (int) $vend->apk_version_code >= FreezerControlService::MIN_APK_VERSION_CODE,
            'is_online' => (bool) $vend->is_online,
            'last_seen_at' => $vend->mqtt_last_updated_at?->toIso8601String(),
            'apk_version_code' => $vend->apk_version_code,
            'status' => $status,
            'status_at' => $vend->freezer_control_status_at ? Carbon::parse($vend->freezer_control_status_at)->toIso8601String() : null,
            'periodic_status_at' => $periodic['status_at'] ?? null,
            'can_control' => $request->user()->can('update machine-settings'),
            'can_door' => $request->user()->can(self::DOOR_PERMISSION),
            'can_sdk_raw' => $request->user()->can(self::SDK_RAW_PERMISSION),
            // The second batch of controls needs APK 14; the page greys them on an older build.
            'supported_batch2' => (int) $vend->apk_version_code >= FreezerControlService::MIN_APK_VERSION_CODE_BATCH2,
            'diag_probes' => FreezerControlService::DIAG_PROBES,
            'camera_id_max' => FreezerControlService::CAMERA_ID_MAX,
            'beep_seconds_max' => FreezerControlService::BEEP_SECONDS_MAX,
            'schedule' => $this->schedulePayload($vend),
            'photos' => $this->photoPayload($vend),
            'setpoint' => [
                'min' => FreezerControlService::SETPOINT_MIN,
                'max' => FreezerControlService::SETPOINT_MAX,
                // The AG325 has no setpoint read, so the panel shows the last one WE set. Resolved
                // here rather than from the timeline the page holds: that is capped at `limit` rows,
                // so a machine nobody has touched lately would read as "never set".
                'last' => $this->lastSetpoint($vend),
            ],
            'total' => $total,
            'pending' => $commands->contains(fn ($c) => $c->displayStatus($now) === FreezerControlCommand::STATUS_PENDING),
            'log_pull' => [
                'lines' => ['min' => FreezerControlService::LOG_LINES_MIN, 'max' => FreezerControlService::LOG_LINES_MAX, 'default' => FreezerControlService::LOG_LINES_DEFAULT],
                'minutes' => ['min' => FreezerControlService::LOG_MINUTES_MIN, 'max' => FreezerControlService::LOG_MINUTES_MAX, 'default' => FreezerControlService::LOG_MINUTES_DEFAULT],
            ],
            'commands' => $commands->map(fn (FreezerControlCommand $c) => [
                'id' => $c->id,
                'op' => $c->op,
                'args' => $c->args,
                'source' => $c->source,
                'status' => $c->displayStatus($now),
                'message' => $c->response_msg,
                // The excerpt itself is fetched on demand (excerpt route): 30 rows × 12 KB on
                // every 20 s poll would be most of the page's traffic for text nobody opened.
                'has_log' => $c->response_log !== null && $c->response_log !== '',
                'log_scope' => $c->log_scope,
                'log_file' => $c->log_path ? ['lines' => $c->log_lines, 'url' => route('vends.freezer-controls.log', [$vend->id, $c->id])] : null,
                'attachment' => $c->attachment_path ? ['type' => $c->attachment_type, 'url' => route('vends.freezer-controls.attachment', [$vend->id, $c->id])] : null,
                'requested_by' => $c->requested_by_name,
                'requested_at' => $c->created_at?->toIso8601String(),
                'responded_at' => $c->responded_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * The newest setpoint this machine accepted: `{celsius, at, by}`, or null if it never took one.
     * Only `ok` counts — a refused or unanswered write left the controller where it was.
     */
    private function lastSetpoint(Vend $vend): ?array
    {
        $command = FreezerControlCommand::where('vend_id', $vend->id)
            ->where('op', 'setpoint')
            ->where('status', 'ok')
            ->orderByDesc('id')
            ->first();

        $celsius = $command?->args['celsius'] ?? null;

        return $celsius === null ? null : [
            'celsius' => (int) $celsius,
            'at' => ($command->responded_at ?? $command->created_at)?->toIso8601String(),
            'by' => $command->requested_by_name,
        ];
    }

    /**
     * The camera stills this machine uploaded, newest first, for the panel's photo viewer — at most
     * [self::PHOTO_HISTORY] per camera, so one busy camera cannot push the others off the list and
     * the page can show each camera's own latest shot side by side. The image itself is fetched
     * through the gated attachment route; only its URL travels in this payload.
     */
    private function photoPayload(Vend $vend): array
    {
        $perCamera = [];

        return FreezerControlCommand::where('vend_id', $vend->id)
            ->where('op', 'photo')
            ->whereNotNull('attachment_path')
            ->orderByDesc('id')
            ->limit(self::PHOTO_SCAN)
            ->get()
            ->filter(function (FreezerControlCommand $c) use (&$perCamera) {
                $camera = (int) ($c->args['cameraId'] ?? 0);
                $perCamera[$camera] = ($perCamera[$camera] ?? 0) + 1;

                return $perCamera[$camera] <= self::PHOTO_HISTORY;
            })
            ->map(fn (FreezerControlCommand $c) => [
                'id' => $c->id,
                'camera_id' => (int) ($c->args['cameraId'] ?? 0),
                'url' => route('vends.freezer-controls.attachment', [$vend->id, $c->id]),
                // Tiles and the history strip draw from this one: ~8 KB against 35-140 KB.
                'thumb_url' => route('vends.freezer-controls.attachment', [$vend->id, $c->id, 'thumb' => 1]),
                'taken_at' => ($c->responded_at ?? $c->created_at)?->toIso8601String(),
                'by' => $c->requested_by_name,
            ])->values()->all();
    }

    /** The machine's daily setpoint entries, earliest first, each with its last run's verdict. */
    private function schedulePayload(Vend $vend): array
    {
        $now = Carbon::now();

        return FreezerSetpointSchedule::with('lastCommand')
            ->where('vend_id', $vend->id)
            ->orderBy('run_at')
            ->get()
            ->map(fn (FreezerSetpointSchedule $s) => [
                'id' => $s->id,
                'run_at' => $s->runAtLabel(),
                'celsius' => $s->celsius,
                'is_active' => $s->is_active,
                'created_by' => $s->created_by_name,
                'last_run_on' => $s->last_run_on?->toDateString(),
                'last_status' => $s->lastCommand?->displayStatus($now),
                'last_message' => $s->lastCommand?->response_msg,
            ])->values()->all();
    }

    /** Adds one daily entry. Same permission and range as a manual setpoint. */
    public function storeSchedule(Request $request, Vend $vend): JsonResponse
    {
        abort_unless($vend->isSmartFreezer(), 404);
        $data = $request->validate([
            'run_at' => ['required', 'date_format:H:i', Rule::unique('freezer_setpoint_schedules', 'run_at')->where('vend_id', $vend->id)],
            'celsius' => ['required', 'integer', 'min:'.FreezerControlService::SETPOINT_MIN, 'max:'.FreezerControlService::SETPOINT_MAX],
        ], [
            'run_at.unique' => 'This machine already has an entry at that time.',
        ]);
        $schedule = FreezerSetpointSchedule::create([
            'vend_id' => $vend->id,
            'run_at' => $data['run_at'].':00',
            'celsius' => $data['celsius'],
            // An entry added after its time today waits for tomorrow rather than firing at once.
            'last_run_on' => $data['run_at'] < Carbon::now()->format('H:i') ? Carbon::today() : null,
            'created_by' => $request->user()->id,
            'created_by_name' => $request->user()->name,
        ]);
        Log::info('Freezer setpoint schedule added', ['vend_code' => $vend->code, 'run_at' => $data['run_at'], 'celsius' => $data['celsius'], 'user' => $request->user()->name]);

        return response()->json(['id' => $schedule->id], 201);
    }

    /** Pauses or resumes one entry. */
    public function updateSchedule(Request $request, Vend $vend, FreezerSetpointSchedule $schedule): JsonResponse
    {
        abort_unless($schedule->vend_id === $vend->id, 404);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        $schedule->update(['is_active' => $data['is_active']]);
        Log::info('Freezer setpoint schedule '.($data['is_active'] ? 'resumed' : 'paused'), ['vend_code' => $vend->code, 'run_at' => $schedule->runAtLabel(), 'user' => $request->user()->name]);

        return response()->json(['ok' => true]);
    }

    public function destroySchedule(Request $request, Vend $vend, FreezerSetpointSchedule $schedule): JsonResponse
    {
        abort_unless($schedule->vend_id === $vend->id, 404);
        Log::info('Freezer setpoint schedule removed', ['vend_code' => $vend->code, 'run_at' => $schedule->runAtLabel(), 'celsius' => $schedule->celsius, 'user' => $request->user()->name]);
        $schedule->delete();

        return response()->json(['ok' => true]);
    }

    /** One row's device-log excerpt, fetched when the technician expands it. */
    public function excerpt(Vend $vend, FreezerControlCommand $command): JsonResponse
    {
        abort_unless($command->vend_id === $vend->id, 404);

        return response()->json(['log' => $command->response_log, 'log_scope' => $command->log_scope]);
    }

    /**
     * A full-log upload as text (gunzipped); `?q=` keeps only lines containing it (case-insensitive),
     * `?download=1` sends it as a file.
     */
    public function log(Request $request, Vend $vend, FreezerControlCommand $command)
    {
        abort_unless($command->vend_id === $vend->id && $command->log_path, 404);
        $gz = Storage::get($command->log_path);
        $text = @gzdecode($gz);
        abort_if($text === false, 404);
        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $text = implode("\n", array_values(array_filter(explode("\n", $text), fn ($l) => mb_stripos($l, $q) !== false)));
        }
        $name = 'freezer-'.$vend->code.'-'.$command->created_at?->format('Ymd-His').'.log';
        $headers = ['Content-Type' => 'text/plain; charset=utf-8'];
        if ($request->boolean('download')) {
            $headers['Content-Disposition'] = 'attachment; filename="'.$name.'"';
        }

        return response($text, 200, $headers);
    }

    /** A `photo` command's still, streamed from the private disk. */
    public function attachment(Request $request, Vend $vend, FreezerControlCommand $command)
    {
        abort_unless($command->vend_id === $vend->id && $command->attachment_path, 404);
        abort_unless(Storage::exists($command->attachment_path), 404);

        // `?thumb=1` is the tile-sized copy, made on first ask for photos older than it. A stored
        // photo never changes, so either file may be held by the browser for good.
        if ($request->boolean('thumb')) {
            $thumb = app(FreezerPhotoThumbnail::class)->pathFor($command);
            if ($thumb !== null) {
                return Storage::response($thumb, 'camera-'.$command->id.'-thumb.webp', [
                    'Content-Type' => 'image/webp',
                    'Cache-Control' => self::PHOTO_CACHE_CONTROL,
                ]);
            }
        }
        $name = 'freezer-'.$vend->code.'-'.$command->created_at?->format('Ymd-His').'.jpg';

        return Storage::response($command->attachment_path, $name, ['Content-Type' => 'image/jpeg', 'Cache-Control' => self::PHOTO_CACHE_CONTROL]);
    }

    /**
     * Device -> mark1: a `photo` command's still. Same contract as the log upload: unauthenticated,
     * the pending cmdId is the authorisation (FreezerControlService::storePhotoUpload).
     */
    public function uploadPhoto(Request $request, string $code): JsonResponse
    {
        $request->validate([
            'cmdId' => 'required|string|max:64',
            'cameraId' => 'nullable|integer|min:0|max:'.FreezerControlService::CAMERA_ID_MAX,
            'file' => 'required|file|max:6144',
        ]);
        $vend = Vend::withoutGlobalScopes()->bareCode($code)->first();
        if (! $vend) {
            return response()->json(['ok' => false, 'message' => 'Unknown machine.'], 404);
        }
        $file = $request->file('file');
        $ok = $this->service->storePhotoUpload($vend, $request->input('cmdId'), (int) $request->input('cameraId', 0), $file->getRealPath(), (int) $file->getSize());
        if (! $ok) {
            Log::warning('Freezer photo upload rejected', ['vend_code' => $code, 'cmd_id' => $request->input('cmdId'), 'ip' => $request->ip()]);

            return response()->json(['ok' => false, 'message' => 'No pending photo command for this machine.'], 403);
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Device -> mark1: the `logs` command's file. Unauthenticated like the other device endpoints;
     * the pending cmdId is the authorisation (see FreezerControlService::storeLogUpload).
     */
    public function upload(Request $request, string $code): JsonResponse
    {
        $request->validate([
            'cmdId' => 'required|string|max:64',
            'lines' => 'nullable|integer',
            'file' => 'required|file|max:4096',
        ]);
        $vend = Vend::withoutGlobalScopes()->bareCode($code)->first();
        if (! $vend) {
            return response()->json(['ok' => false, 'message' => 'Unknown machine.'], 404);
        }
        $file = $request->file('file');
        $ok = $this->service->storeLogUpload($vend, $request->input('cmdId'), (int) $request->input('lines', 0), $file->getRealPath(), (int) $file->getSize());
        if (! $ok) {
            Log::warning('Freezer log upload rejected', ['vend_code' => $code, 'cmd_id' => $request->input('cmdId'), 'ip' => $request->ip()]);

            return response()->json(['ok' => false, 'message' => 'No pending log command for this machine.'], 403);
        }

        return response()->json(['ok' => true]);
    }

    public function store(Request $request, Vend $vend): JsonResponse
    {
        abort_unless($vend->isSmartFreezer(), 404);
        if (empty($vend->code)) {
            return response()->json(['message' => 'This machine has no code.'], 422);
        }

        $data = $request->validate([
            'op' => ['required', 'string', 'in:'.implode(',', FreezerControlService::OPS)],
            'args' => ['nullable', 'array'],
        ]);

        if (in_array($data['op'], ['lock', 'unlock'], true) && ! $request->user()->can(self::DOOR_PERMISSION)) {
            return response()->json(['message' => 'You do not have permission to lock or unlock the door remotely.'], 403);
        }
        if ($data['op'] === 'sdkcall' && ! $request->user()->can(self::SDK_RAW_PERMISSION)) {
            return response()->json(['message' => 'Raw SDK calls are limited to superadmins.'], 403);
        }

        if ((int) $vend->apk_version_code < FreezerControlService::minApkVersionFor($data['op'])) {
            return response()->json(['message' => 'This machine\'s app is too old for this control (needs versionCode '.FreezerControlService::minApkVersionFor($data['op']).').'], 422);
        }

        // One command at a time per machine: the device executes them serially anyway, and a
        // queue of taps behind a slow host is how a technician ends up double-actuating.
        $now = Carbon::now();
        $busy = FreezerControlCommand::where('vend_id', $vend->id)
            ->where('status', FreezerControlCommand::STATUS_PENDING)
            ->where('expires_at', '>', $now)
            ->exists();
        if ($busy) {
            return response()->json(['message' => 'Waiting for the machine to answer the previous command.'], 429);
        }

        $command = $this->service->dispatch($vend, $data['op'], $data['args'] ?? [], $request->user(), $request->ip());

        return response()->json([
            'id' => $command->id,
            'op' => $command->op,
            'status' => $command->status,
            'expires_at' => $command->expires_at->toIso8601String(),
        ], 202);
    }
}
