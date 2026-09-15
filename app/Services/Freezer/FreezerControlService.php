<?php

namespace App\Services\Freezer;

use App\Jobs\PublishMqtt;
use App\Models\FreezerControlCommand;
use App\Models\User;
use App\Models\Vend;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
 */
class FreezerControlService
{
    /** Seconds a command may wait for the device before it is refused as expired / shown as timeout. */
    public const TTL_SECONDS = 60;

    /** Ops mark1 may send, with the argument each takes. */
    public const OPS = ['status', 'lock', 'unlock', 'fan', 'light', 'compressor', 'setpoint', 'volume'];

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

        DB::transaction(function () use ($vend, $cmdId, $result, $input, $status) {
            $command = FreezerControlCommand::where('cmd_id', $cmdId)->where('vend_id', $vend->id)->lockForUpdate()->first();
            if (! $command) {
                Log::warning('FREEZERCTLACK ignored: unknown cmdId for this vend', ['vend_code' => $vend->code, 'cmd_id' => $cmdId]);
            } elseif ($command->status === FreezerControlCommand::STATUS_PENDING) {
                $command->update([
                    'status' => $result,
                    'response_msg' => is_string($input['msg'] ?? null) ? Str::limit($input['msg'], 250, '') : null,
                    'responded_at' => Carbon::now(),
                ]);
            }

            if ($status !== null) {
                DB::table('vends')->where('id', $vend->id)->update([
                    'freezer_control_status_json' => json_encode($status),
                    'freezer_control_status_at' => Carbon::now(),
                ]);
            }
        });
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
