<?php

namespace App\Jobs\Vend;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Store the OTA updater / square-module state a big-board 307+ "P" heartbeat
 * carries (OtaFail, OtaErr, ModemFw, ModemPdp) on its `vends` row.
 *
 * `$fields` holds only what the heartbeat carried, already bounded by
 * VendDataService::recordOtaModem, keyed by column: ota_fail_streak +
 * ota_last_error travel together (null error = the last poll succeeded);
 * modem_firmware / modem_pdp only when the board has a square module.
 * A key that is absent keeps its stored value.
 *
 * Writes only the columns that differ, so a re-delivered or out-of-date job
 * that changes nothing writes nothing. ota_modem_changed_at moves only with a
 * real change. Columns missing (code deployed before the migration) = no-op.
 */
class RecordVendOtaModem implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const COLUMNS = ['ota_fail_streak', 'ota_last_error', 'modem_firmware', 'modem_pdp'];

    private static ?bool $columnsExist = null;

    public function __construct(
        public int $vendId,
        public array $fields,
        public string $reportedAt,
    ) {}

    public function handle(): void
    {
        $fields = array_intersect_key($this->fields, array_flip(self::COLUMNS));
        if ($fields === [] || ! self::columnsExist()) {
            return;
        }

        $current = DB::table('vends')->where('id', $this->vendId)->first(array_keys($fields));
        if ($current === null) {
            return;
        }

        $changes = [];
        foreach ($fields as $column => $value) {
            // Compare as strings: the driver may hand back ints as strings.
            if ((string) ($current->{$column} ?? '') !== (string) ($value ?? '')
                || ($current->{$column} === null) !== ($value === null)) {
                $changes[$column] = $value;
            }
        }
        if ($changes === []) {
            return;
        }

        $changes['ota_modem_changed_at'] = $this->reportedAt;
        DB::table('vends')->where('id', $this->vendId)->update($changes);
    }

    /** One schema check per worker process; fails closed (write nothing). */
    private static function columnsExist(): bool
    {
        if (self::$columnsExist === null) {
            try {
                self::$columnsExist = Schema::hasColumn('vends', 'ota_modem_changed_at');
            } catch (\Throwable $e) {
                self::$columnsExist = false;
            }
        }

        return self::$columnsExist;
    }
}
