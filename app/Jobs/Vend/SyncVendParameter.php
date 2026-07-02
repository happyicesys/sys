<?php

namespace App\Jobs\Vend;

use App\Jobs\Vend\AnalyzeVendTempWithAi;
use App\Models\Vend;
use App\Models\VendFan;
use App\Models\VendTemp;
use App\Services\VendTempService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
     *
     * @return void
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

        if ($vend->isDirty()) {
            $vend->save();
        }
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
            if (!is_array($input) || !array_key_exists('CHGEStat', $input) || !array_key_exists('CoinCnt', $input)) {
                return;
            }
            $coinStat = (int) $input['CHGEStat'];
            if (!in_array($coinStat, [1, 3], true)) {
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
            if (!$vend->is_fan_enabled && (float) $input['fan'] > 1000) {
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

                if (isset($input['t2'])) {
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

        if (!$aiService->isEnabled()) {
            return;
        }

        AnalyzeVendTempWithAi::dispatch($vend->id, $latestTempId, $snapshot, $alertId)->onQueue('default');
    }
}
