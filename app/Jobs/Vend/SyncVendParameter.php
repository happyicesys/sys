<?php

namespace App\Jobs\Vend;

use App\Models\Vend;
use App\Models\VendFan;
use App\Models\VendTemp;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Schema;

class SyncVendParameter implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $input;

    protected $vend;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($input, Vend $vend)
    {
        $this->input = $input;
        $this->vend = $vend;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $input = $this->input;
        $vend = $this->vend;
        $vendTempService = new \App\Services\VendTempService($vend);

        $this->createVendFan($input, $vend);
        $this->createVendTemp($input, $vend, $vendTempService);
        $this->saveParameter($input, $vend);
        $this->logCoinFloatChange($input, $vend);
        $this->syncInternetStatus($input, $vend);

        if ($vend->isDirty()) {
            $vend->save();
        }
    }

    /**
     * Memoized "do the internet_* columns exist yet?".
     *
     * Static so it survives per queued job and is answered once per worker
     * process, not once per packet.
     */
    private static $internetColumnsExist = null;

    /**
     * Promote the APK-reported internet link out of the packet onto the vend.
     *
     * Shape, present only from APK v302 / small-board v134:
     *
     *   "Internet":{"Source":"telco","Provider":"Digi","Signal":4,"SignalMax":5,"Network":"4G"}
     *
     * The APK omits any field it could not read rather than sending null or "".
     *
     * ABSENT OBJECT IS A NO-OP, NOT A CLEAR. Every machine on an older APK sends
     * a VENDER packet every ~5 minutes with no Internet key at all; treating
     * that as "link unknown" would blank the columns of any machine that later
     * downgrades, and would write on every packet from the whole legacy fleet.
     *
     * ABSENT FIELD INSIDE A PRESENT OBJECT means "could not read it THIS time",
     * which is not the same as "it is gone". While Source is unchanged, an
     * omitted field keeps its previous value - otherwise a Wi-Fi machine whose
     * SSID the platform withholds on one poll would flap between the SSID and
     * blank every five minutes, and one malformed Signal would destroy a good
     * bar count. When Source CHANGES the link is a different thing entirely, so
     * the whole row is replaced with exactly what the packet says (which is how
     * the leftover Network clears on a telco -> wifi move).
     *
     * Values are written on the in-memory model only - the existing
     * $vend->save() in handle() persists them, so this costs no extra query, and
     * the legacy fleet gains no write it was not already making (createVendTemp
     * already dirties temp_updated_at on every packet).
     *
     * Fully fault-isolated, and gated on the columns actually existing: the
     * save() in handle() is NOT inside a try, so writing to a column that has
     * not been migrated yet would fail the whole job and take parameter_json and
     * the temperature down with it. That makes deploy order not matter.
     */
    private function syncInternetStatus($input, Vend $vend): void
    {
        try {
            if (! is_array($input) || ! isset($input['Internet']) || ! is_array($input['Internet'])) {
                return;
            }
            if (! $this->internetColumnsExist()) {
                return;
            }

            $net = $input['Internet'];

            // Source is the one required member. Without it the object is
            // malformed and nothing in it can be trusted.
            $source = $this->trimmedString($net['Source'] ?? null, 16);
            if ($source === null) {
                return;
            }

            $signalMax = $this->boundedInt($net['SignalMax'] ?? null, 1, 255);
            $signal = $this->boundedInt($net['Signal'] ?? null, 0, 255);

            // A bar count above its own scale means the device reported on a
            // different scale than it declared (a ROM handing back raw ASU 0..31
            // instead of bars). Neither number can be trusted together, so drop
            // the reading rather than store "31/5".
            if ($signal !== null && $signalMax !== null && $signal > $signalMax) {
                $signal = null;
                $signalMax = null;
            }
            if ($signal === null) {
                $signalMax = null;
            }

            $provider = $this->trimmedString($net['Provider'] ?? null, 64);
            $network = $this->trimmedString($net['Network'] ?? null, 16);

            if ($vend->internet_source !== $source) {
                // Different kind of link - replace wholesale.
                $vend->internet_source = $source;
                $vend->internet_provider = $provider;
                $vend->internet_signal = $signal;
                $vend->internet_signal_max = $signalMax;
                $vend->internet_network = $network;
            } else {
                // Same link - a field the packet did not carry keeps its value.
                if ($provider !== null) {
                    $vend->internet_provider = $provider;
                }
                if ($signal !== null) {
                    if ($signalMax !== null) {
                        $vend->internet_signal = $signal;
                        $vend->internet_signal_max = $signalMax;
                    } elseif ($vend->internet_signal_max === null || $signal <= $vend->internet_signal_max) {
                        // SignalMax unreadable this poll: the scale keeps its value like any
                        // other omitted field — writing null here destroyed a 10-bar scale and
                        // the UI's "|| 5" fallback then graded 3/10 as 3/5.
                        $vend->internet_signal = $signal;
                    }
                    // else: bars exceed the stored scale with no new scale to trust —
                    // incoherent pair, drop the reading (same rule as the in-packet check).
                }
                if ($network !== null) {
                    $vend->internet_network = $network;
                }
            }

            $vend->internet_updated_at = Carbon::now();

            $this->syncDataUsage($net, $vend);
        } catch (\Throwable $e) {
            \Log::warning('syncInternetStatus failed', [
                'vend_id' => $vend->id ?? null,
                'vend_code' => $vend->code ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Memoized "do the internet_data_* columns exist yet?". Same idea as above. */
    private static $dataColumnsExist = null;

    /**
     * Promote the APK's cumulative data-usage counters (APK v303+):
     *
     *   "DataKB":1843201,"DataMobileKB":1790854,"DataAppKB":211077,"DataDays":38
     *
     * CUMULATIVE lifetime decimal KB since the ledger's epoch — usage over a
     * window comes from diffing the daily vend_data_usage_snapshots rows, not
     * from these columns. Unlike the link fields these are DEVICE-scoped, so a
     * Source change never wipes them; and a value LOWER than the stored one is
     * accepted as-is, because it means the ledger reset (APK reinstall / prefs
     * wipe) and the new number is the truth.
     *
     * DataKB is the one required member: the APK's ledger emits the total
     * channel whenever it emits anything, so an object carrying only the
     * optional members is malformed and skipped whole.
     */
    private function syncDataUsage(array $net, Vend $vend): void
    {
        if (! $this->dataColumnsExist()) {
            return;
        }

        // 11 digits of KB (~100 TB) matches the APK-side DATA_MAX_KB cap.
        $totalKb = $this->boundedInt($net['DataKB'] ?? null, 0, 99_999_999_999);
        if ($totalKb === null) {
            return;
        }

        $vend->internet_data_kb = $totalKb;

        // Optional members keep their previous value when omitted (a channel
        // the ROM reports UNSUPPORTED simply never arrives), same rule as the
        // link fields.
        $mobileKb = $this->boundedInt($net['DataMobileKB'] ?? null, 0, 99_999_999_999);
        if ($mobileKb !== null) {
            $vend->internet_data_mobile_kb = $mobileKb;
        }
        $appKb = $this->boundedInt($net['DataAppKB'] ?? null, 0, 99_999_999_999);
        if ($appKb !== null) {
            $vend->internet_data_app_kb = $appKb;
        }
        $days = $this->boundedInt($net['DataDays'] ?? null, 0, 9_999);
        if ($days !== null) {
            $vend->internet_data_days = $days;
        }

        $vend->internet_data_updated_at = Carbon::now();
    }

    /** True once the internet_data_* columns have been migrated. Fails closed. */
    private function dataColumnsExist(): bool
    {
        if (self::$dataColumnsExist === null) {
            try {
                self::$dataColumnsExist = Schema::hasColumn('vends', 'internet_data_kb');
            } catch (\Throwable $e) {
                self::$dataColumnsExist = false;
            }
        }

        return self::$dataColumnsExist;
    }

    /**
     * True once the internet_* columns have been migrated.
     *
     * Answered once per worker process and then memoized, so this is one extra
     * query per worker lifetime rather than one per packet. Fails CLOSED: if the
     * check itself errors we simply do not write the columns.
     */
    private function internetColumnsExist(): bool
    {
        if (self::$internetColumnsExist === null) {
            try {
                self::$internetColumnsExist = Schema::hasColumn('vends', 'internet_source');
            } catch (\Throwable $e) {
                self::$internetColumnsExist = false;
            }
        }

        return self::$internetColumnsExist;
    }

    /**
     * A scalar reduced to a bounded, non-empty string, or null.
     *
     * Bounds every value against its column width so a long or hostile field
     * truncates instead of throwing a "Data too long" and failing the whole
     * $vend->save() - which would take the temperature write down with it.
     */
    private function trimmedString($value, int $max): ?string
    {
        if ($value === null || is_array($value) || is_object($value)) {
            return null;
        }
        $string = trim((string) $value);
        if ($string === '') {
            return null;
        }

        return mb_substr($string, 0, $max);
    }

    /** An integer inside [$min, $max], or null when absent or out of range. */
    private function boundedInt($value, int $min, int $max): ?int
    {
        if ($value === null || ! is_numeric($value)) {
            return null;
        }
        $int = (int) $value;

        return ($int < $min || $int > $max) ? null : $int;
    }

    /**
     * Record a coin-float change event.
     *
     * Fires on every VENDER packet, so it must stay O(1): the comparison is a
     * plain read of vends.last_coin_cnt (already loaded on $vend) — no query,
     * no table crawl. A history row is written ONLY when the coin float
     * actually changed AND the coin acceptor is active (CHGEStat IN (1,3)),
     * so cashless machines and stale/zero readings never generate rows.
     *
     * The last-value columns are updated on the in-memory model; the existing
     * $vend->save() below persists them (no extra write). Fully fault-isolated:
     * any failure here is logged and swallowed so it can never break ingest.
     */
    private function logCoinFloatChange($input, Vend $vend): void
    {
        try {
            // Coin acceptor must be active (1 = inactive-but-present, 3 = active
            // per the UI). Anything else means no meaningful coin float.
            if (! is_array($input) || ! array_key_exists('CHGEStat', $input) || ! array_key_exists('CoinCnt', $input)) {
                return;
            }
            $coinStat = (int) $input['CHGEStat'];
            if (! in_array($coinStat, [1, 3], true)) {
                return;
            }

            // CoinCnt may arrive as a numeric string; normalise to int.
            if ($input['CoinCnt'] === '' || $input['CoinCnt'] === null) {
                return;
            }
            $coinCnt = (int) $input['CoinCnt'];

            $prev = $vend->last_coin_cnt; // null on first-ever observation
            if ($prev !== null && (int) $prev === $coinCnt) {
                return; // unchanged — nothing to log (the common case)
            }

            \Illuminate\Support\Facades\DB::table('vend_coin_float_logs')->insert([
                'vend_id' => $vend->id,
                'vend_code' => $vend->code,
                'coin_cnt' => $coinCnt,
                'prev_coin_cnt' => $prev,
                'delta' => $prev === null ? null : $coinCnt - (int) $prev,
                'coin_stat' => $coinStat,
                'created_at' => Carbon::now(),
            ]);

            // Advance the last-known value; persisted by the caller's save().
            $vend->last_coin_cnt = $coinCnt;
            $vend->last_coin_cnt_at = Carbon::now();
        } catch (\Throwable $e) {
            \Log::warning('logCoinFloatChange failed', [
                'vend_id' => $vend->id ?? null,
                'vend_code' => $vend->code ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function createVendFan($input, Vend $vend)
    {
        if (isset($input['fan']) && $input['fan'] !== '') {
            $vend->vendFans()->create([
                'value' => $input['fan'],
                'type' => VendFan::TYPE_MAIN,
            ]);

            // Event-driven latch, replacing the every-10-min check:vend-fan-enabled
            // fleet scan (json_extract of parameter_json per row = ~4.3s). saveParameter()
            // writes parameter_json from this same $input, so `fan > 1000` here is
            // equivalent to the batch's `parameter_json->fan > 1000`. One-way latch;
            // it rides the existing isDirty()->save() in handle() — no extra query.
            if (! $vend->is_fan_enabled && (float) $input['fan'] > 1000) {
                $vend->is_fan_enabled = true;
            }
        }
    }

    private function createVendTemp($input, Vend $vend, $vendTempService)
    {
        // more than 3 minutes only update same machine temp
        // if(!$vend->temp_updated_at or $vend->temp_updated_at->addMinutes(2)->isPast()) {

        if (isset($input['TEMP']) && $input['TEMP'] !== '') {
            $temp = $input['TEMP'];
            $snapshot = [
                't2' => $input['t2'] ?? null,
                't3' => $input['t3'] ?? null,
                't4' => $input['t4'] ?? null,
            ];

            if ($temp == VendTemp::TEMPERATURE_ERROR) {
                $vend->is_temp_error = true;
            } else {
                $createdTemp = $vend->vendTemps()->create([
                    'value' => $temp,
                    'type' => VendTemp::TYPE_CHAMBER,
                ]);

                if (isset($input['t2']) && $input['t2'] !== '') {
                    $vend->vendTemps()->create([
                        'value' => $input['t2'],
                        'type' => VendTemp::TYPE_EVAPORATOR,
                    ]);
                }

                if (isset($input['t3']) && $input['t3'] !== '') {
                    $vend->vendTemps()->create([
                        'value' => $input['t3'],
                        'type' => VendTemp::TYPE_THREE,
                    ]);
                }

                if (isset($input['t4']) && $input['t4'] !== '') {
                    $vend->vendTemps()->create([
                        'value' => $input['t4'],
                        'type' => VendTemp::TYPE_FOUR,
                    ]);
                }

                $vend->temp = $temp;
                $vend->is_temp_error = false;

                if (isset($input['t2']) && $input['t2'] !== '' && is_numeric($input['t2'])) {
                    $alert = $vendTempService->runVendTempAlert($temp, $input['t2']);

                    if ($alert) {
                        $this->dispatchAiAnalysis($vend, $createdTemp->id, $snapshot, $alert->id);
                    }
                }

                // $this->dispatchAiAnalysis($vend, $createdTemp->id, $snapshot);
            }
        }
        $vend->temp_updated_at = Carbon::now();
        $vend->is_temp_active = true;
    }

    private function saveParameter($input, Vend $vend)
    {
        $vend->parameter_json = $input;
    }

    private function dispatchAiAnalysis(Vend $vend, int $latestTempId, array $snapshot = [], ?int $alertId = null): void
    {
        $aiService = app(\App\Services\VendTempAiService::class);

        if (! $aiService->isEnabled()) {
            return;
        }

        AnalyzeVendTempWithAi::dispatch($vend->id, $latestTempId, $snapshot, $alertId)->onQueue('default');
    }
}
