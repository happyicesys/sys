<?php

namespace App\Services\Freezer;

use App\Jobs\PublishMqtt;
use App\Models\FreezerControlCommand;
use App\Models\User;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Remote cabinet controls for smart freezers: mark1 -> device over MQTT, device -> mark1 answer.
 *
 * Protocol (mirrored in apk/smart-freezer FREEZER_REMOTE_CONTROL.md):
 * - Down: signed `CM<code>` frame, JSON `{"Type":"FREEZERCTL","time","action":"","mid","cmdId","op","args","expiresAt"}`.
 * - Up:   `FREEZERCTLACK` over MQTT or HTTP vend-data, `{"cmdId","op","result","msg","status":{…},"ts"}`.
 *
 * The device refuses replays (it remembers cmdIds), expired frames, and door commands during a sale,
 * so this side only has to record intent, send once, and store the answer.
 *
 * Log retention is 72 hours on both sides: `freezer-logs:prune` (hourly) clears uploaded files and
 * excerpts older than that; the machine's own archive drops its segments on the same window.
 */
class FreezerControlService
{
    /** Seconds a command may wait for the device before it is refused as expired / shown as timeout. */
    public const TTL_SECONDS = 60;

    /** Ops mark1 may send, with the argument each takes. */
    public const OPS = ['status', 'lock', 'unlock', 'fan', 'light', 'compressor', 'setpoint', 'volume', 'logs'];

    /** Bounds for `logs` args (mirror DeviceLog on the APK). */
    public const LOG_LINES_MIN = 200;

    public const LOG_LINES_MAX = 20000;

    public const LOG_LINES_DEFAULT = 5000;

    public const LOG_MINUTES_MIN = 1;

    public const LOG_MINUTES_MAX = 2880;

    public const LOG_MINUTES_DEFAULT = 60;

    public const LOG_GREP_MAX = 64;

    /** Longest `log` excerpt kept per command. */
    public const RESPONSE_LOG_MAX_BYTES = 16384;

    /** Largest full-log upload accepted, bytes gzipped. */
    public const LOG_UPLOAD_MAX_BYTES = 4194304;

    /** Setpoint range accepted by the APK (ThermostatLimits). */
    public const SETPOINT_MIN = -30;

    public const SETPOINT_MAX = -5;

    /** Smallest APK versionCode that understands FREEZERCTL. Older builds log and drop it. */
    public const MIN_APK_VERSION_CODE = 11;

    /**
     * Validates, records and sends one command.
     *
     * @throws ValidationException when the op or its argument is not acceptable
     */
    public function dispatch(Vend $vend, string $op, array $args, ?User $user, ?string $ip): FreezerControlCommand
    {
        $clean = $this->validate($op, $args);
        $now = Carbon::now();

        $command = FreezerControlCommand::create([
            'vend_id' => $vend->id,
            'cmd_id' => (string) Str::ulid(),
            'op' => $op,
            'args' => $clean ?: null,
            'status' => FreezerControlCommand::STATUS_PENDING,
            'requested_by' => $user?->id,
            'requested_by_name' => $user?->name,
            'ip' => $ip,
            'expires_at' => $now->copy()->addSeconds(self::TTL_SECONDS),
        ]);

        PublishMqtt::dispatch('CM'.$vend->code, $this->frame($vend, $command, $now))->onQueue('high');

        Log::info('Freezer remote control sent', [
            'vend_code' => $vend->code, 'cmd_id' => $command->cmd_id, 'op' => $op, 'args' => $clean,
            'user_id' => $user?->id, 'user_name' => $user?->name, 'ip' => $ip,
        ]);

        return $command;
    }

    /** Signed downlink frame, same envelope as SCREENSHOT / OTA_CHECK. */
    public function frame(Vend $vend, FreezerControlCommand $command, Carbon $now): string
    {
        $fid = 1;
        $content = base64_encode(json_encode([
            'Type' => 'FREEZERCTL',
            'time' => $now->timestamp,
            'action' => '',
            'mid' => $vend->code,
            'cmdId' => $command->cmd_id,
            'op' => $command->op,
            'args' => (object) ($command->args ?? []),
            'expiresAt' => $command->expires_at->timestamp,
        ]));
        $length = strlen($content);
        $key = $vend->private_key ?: config('vend.private_key', '123456789110138A');

        return $fid.','.$length.','.$content.','.md5($fid.','.$length.','.$content.$key);
    }

    /**
     * Applies one FREEZERCTLACK. Unknown cmdIds, another vend's cmdId, and a second answer for a row
     * that already has one are ignored — the device answers `duplicate` to replays, and that must
     * not overwrite the real verdict.
     */
    public function recordAck(Vend $vend, array $input): void
    {
        $cmdId = is_string($input['cmdId'] ?? null) ? $input['cmdId'] : null;
        $result = is_string($input['result'] ?? null) ? strtolower($input['result']) : null;
        if (! $cmdId || ! in_array($result, FreezerControlCommand::RESULTS, true)) {
            Log::warning('FREEZERCTLACK ignored: malformed', ['vend_code' => $vend->code, 'input' => $input]);

            return;
        }

        $status = is_array($input['status'] ?? null) ? $input['status'] : null;
        $source = in_array($input['source'] ?? null, [FreezerControlCommand::SOURCE_PANEL, FreezerControlCommand::SOURCE_EVENT], true)
            ? $input['source'] : null;
        $answer = [
            'status' => $result,
            'response_msg' => is_string($input['msg'] ?? null) ? Str::limit($input['msg'], 250, '') : null,
            'response_log' => is_string($input['log'] ?? null) ? mb_strcut($input['log'], 0, self::RESPONSE_LOG_MAX_BYTES) : null,
            'log_scope' => in_array($input['logScope'] ?? null, ['app', 'system'], true) ? $input['logScope'] : null,
            'responded_at' => Carbon::now(),
        ];

        DB::transaction(function () use ($vend, $cmdId, $input, $status, $source, $answer) {
            $command = FreezerControlCommand::where('cmd_id', $cmdId)->where('vend_id', $vend->id)->lockForUpdate()->first();
            if (! $command && $source && preg_match('/^'.$source.'-[A-Za-z0-9]{1,40}$/', $cmdId)) {
                // Not an answer to us: a control pressed on the kiosk's own panel, or something the
                // machine reported by itself (a boot, an ERROR log line). Filed in the same log so
                // one page is the timeline of what was done and what went wrong in between.
                FreezerControlCommand::create($answer + [
                    'vend_id' => $vend->id,
                    'cmd_id' => $cmdId,
                    'op' => is_string($input['op'] ?? null) ? Str::limit($input['op'], 60, '') : 'unknown',
                    'source' => $source,
                    'requested_by_name' => $source === FreezerControlCommand::SOURCE_PANEL ? 'Kiosk panel' : 'Machine',
                ]);
            } elseif (! $command) {
                Log::warning('FREEZERCTLACK ignored: unknown cmdId for this vend', ['vend_code' => $vend->code, 'cmd_id' => $cmdId]);
            } elseif ($command->status === FreezerControlCommand::STATUS_PENDING) {
                $command->update($answer);
            }

            if ($status !== null) {
                DB::table('vends')->where('id', $vend->id)->update([
                    'freezer_control_status_json' => json_encode($status),
                    'freezer_control_status_at' => Carbon::now(),
                ]);
            }
        });
    }

    /**
     * Stores a full-log upload from the device. The cmdId must belong to a `logs` command of this
     * vend that is still pending — that is the whole authorisation, the same way the screenshot
     * upload is keyed by its single-use token. Returns false when refused.
     */
    public function storeLogUpload(Vend $vend, string $cmdId, int $lines, string $tmpPath, int $bytes): bool
    {
        if ($bytes <= 0 || $bytes > self::LOG_UPLOAD_MAX_BYTES) {
            return false;
        }
        $command = FreezerControlCommand::where('cmd_id', $cmdId)->where('vend_id', $vend->id)
            ->where('op', 'logs')->where('status', FreezerControlCommand::STATUS_PENDING)->first();
        if (! $command) {
            return false;
        }
        // Default disk = DigitalOcean Spaces on prod (config/filesystems.php), private: a log can
        // carry order ids and the host's own chatter, so it is served only through the gated route.
        $path = 'freezer-logs/'.$vend->id.'/'.$cmdId.'.log.gz';
        Storage::put($path, file_get_contents($tmpPath), 'private');
        $command->update(['log_path' => $path, 'log_lines' => max(0, $lines)]);

        return true;
    }

    /** @return array<string, mixed> the arguments to send */
    private function validate(string $op, array $args): array
    {
        if (! in_array($op, self::OPS, true)) {
            throw ValidationException::withMessages(['op' => "Unknown control '$op'."]);
        }

        return match ($op) {
            'status', 'lock', 'unlock' => [],
            'fan', 'light', 'compressor' => is_bool($args['on'] ?? null)
                ? ['on' => $args['on']]
                : throw ValidationException::withMessages(['args.on' => 'Choose on or off.']),
            'setpoint' => $this->setpoint($args['celsius'] ?? null),
            'volume' => in_array($args['step'] ?? null, ['up', 'down', 'mute'], true)
                ? ['step' => $args['step']]
                : throw ValidationException::withMessages(['args.step' => 'Choose up, down or mute.']),
            'logs' => array_filter([
                'lines' => is_int($args['lines'] ?? null) ? max(self::LOG_LINES_MIN, min(self::LOG_LINES_MAX, $args['lines'])) : self::LOG_LINES_DEFAULT,
                'minutes' => is_int($args['minutes'] ?? null) ? max(self::LOG_MINUTES_MIN, min(self::LOG_MINUTES_MAX, $args['minutes'])) : self::LOG_MINUTES_DEFAULT,
                'grep' => is_string($args['grep'] ?? null) && trim($args['grep']) !== '' ? Str::limit(trim($args['grep']), self::LOG_GREP_MAX, '') : null,
            ], fn ($v) => $v !== null),
        };
    }

    private function setpoint(mixed $celsius): array
    {
        if (! is_int($celsius) || $celsius < self::SETPOINT_MIN || $celsius > self::SETPOINT_MAX) {
            throw ValidationException::withMessages([
                'args.celsius' => 'Setpoint must be a whole number from '.self::SETPOINT_MIN.' to '.self::SETPOINT_MAX.' °C.',
            ]);
        }

        return ['celsius' => $celsius];
    }
}
