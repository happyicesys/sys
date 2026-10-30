<?php

namespace App\Services;

use App\Jobs\PublishMqtt;
use App\Models\Vend;
use App\Models\VendJob;
use App\Services\HappyHour\HappyHourEligibility;

class VendJobService
{
    /**
     * Create a new VendJob and dispatch it via MQTT.
     *
     * @param  int|Vend  $vend
     * @param  callable|null  $formatter  Function to format the final MQTT message: function($payload, $vend)
     */
    public function dispatch($vend, string $type, array $payload = [], ?callable $formatter = null): ?VendJob
    {
        $vendModel = $vend instanceof Vend ? $vend : Vend::find($vend);
        $vendId = $vendModel->id;
        $vendCode = $vendModel->code;

        // BETA TESTING CHECK: Only execute VendJob logic for code 2007
        if ((string) $vendCode !== '2007') {
            if ($formatter) {
                $message = $formatter($payload, $vendModel);
            } else {
                $message = json_encode($payload);
            }
            PublishMqtt::dispatch('CM'.$vendCode, $message, 0)->onQueue('default');

            return null;
        }

        // 1. Create the job record first to get the ID
        $vendJob = VendJob::create([
            'vend_id' => $vendId,
            'type' => $type,
            'payload' => json_encode($payload), // Initial payload must be stringified since we removed model casting
            'is_returned' => false,
            'retries_count' => 0,
        ]);

        // 2. Inject the vend_job_id into the payload
        $payload['vend_job_id'] = $vendJob->id;

        // 3. Determine the final message and stored payload
        if ($formatter) {
            $message = $formatter($payload, $vendModel);
            $storedPayload = $message;
        } else {
            $message = json_encode($payload);
            $storedPayload = $message; // Store the JSON string
        }

        // 4. Update the job with the final payload
        $vendJob->update(['payload' => $storedPayload]);

        // 5. Dispatch the MQTT message
        PublishMqtt::dispatch('CM'.$vendCode, $message, 0)->onQueue('default');

        return $vendJob;
    }

    /**
     * Canonical "re-read your settings now" nudge (TYPESYNCSETTINGSPARAM).
     *
     * The payload carries NO settings values — it tells the machine to re-fetch
     * /api/vends/{code}/parameters and to re-sync its banner/campaign media. So
     * it is always safe to send late or twice: the machine reads whatever is
     * current at the moment it fetches.
     *
     * Lives here rather than in a controller because several callers need it
     * (the manual Push button, the auto-push on save/bind/media change, and the
     * queued fan-out job).
     *
     * @param  int|Vend  $vend
     */
    public function syncSettingsToVend($vend): ?VendJob
    {
        $vendModel = $vend instanceof Vend ? $vend : Vend::find($vend);

        if (! $vendModel) {
            return null;
        }

        $payload = [
            'Type' => 'TYPESYNCSETTINGSPARAM',
            'time' => now()->timestamp,
            'action' => '',
            'mid' => $vendModel->code,
        ];

        return $this->dispatch($vendModel, 'TYPESYNCSETTINGSPARAM', $payload, function ($payload, $vend) {
            $fid = 1;
            $content = base64_encode(json_encode($payload));
            $contentLength = strlen($content);
            $key = $vend && $vend->private_key ? $vend->private_key : config('vend.private_key');
            $md5 = md5($fid.','.$contentLength.','.$content.$key);

            return $fid.','.$contentLength.','.$content.','.$md5;
        });
    }

    /**
     * Canonical "re-fetch your menu now" nudge (TYPESYNCAPICHANNELSLOTLIST).
     *
     * Same contract as syncSettingsToVend above: the payload carries NO channel
     * data — it tells the terminal to re-fetch /api/vends/{code}/thumbnails and
     * re-download channel images, so it reads whatever is current at the moment
     * it fetches and is always safe to send late or twice. Callers must commit
     * the vend_channels write (ProductMappingService::syncChannels) BEFORE
     * sending, or the machine re-fetches the old menu.
     *
     * Sent to vending boards (mark1-apk / mark1-apk-small) AND smart freezers:
     * the freezer APK maps this Type to a catalog revalidation of
     * /api/vends/{code}/menu (debounced + conflated, so a second nudge from
     * SmartFreezerCatalogPush costs nothing). Only Citybox chillers are skipped
     * — no APK of ours. Freezers used to be skipped too (2026-08-14), which
     * left every non-planogram menu change — pricing source, Site bind, Site
     * RP, Setting/Edit rebind, ops-job new mapping, the manual Push button —
     * invisible on a freezer until reboot (50001, 2026-09-14). Gated in ONE
     * place so no caller has to remember.
     *
     * Deliberately does NOT go through dispatch() / create a VendJob row, unlike
     * syncSettingsToVend. VendJob tracking only works for frames the terminal
     * acks: SyncJobApkSetting flips is_returned when the APK replies JOBAPKSETTING
     * to a settings push, and that is the ONLY ack path. The APK answers this
     * frame with no reply at all (CvMqttService just calls
     * OnSyncApiChannelSlotList()), so a row here could never be marked returned
     * and vend:retry-jobs would re-publish it every 5 minutes for an hour —
     * a dozen forced menu re-fetches and image re-downloads per rebind.
     *
     * Published at QoS 1 on the `high` queue, matching what the three call sites
     * did inline before they were consolidated here: a menu change is exactly
     * the case where at-least-once delivery matters.
     *
     * @param  int|Vend  $vend
     * @return bool true when a nudge was published
     */
    public function syncChannelSlotListToVend($vend): bool
    {
        $vendModel = $vend instanceof Vend ? $vend : Vend::withoutGlobalScopes()->find($vend);

        if (! $vendModel) {
            return false;
        }

        if ($vendModel->isSmartChiller()) {
            return false;
        }

        $this->publishBareFrame($vendModel, 'TYPESYNCAPICHANNELSLOTLIST');

        return true;
    }

    /**
     * The lightweight Happy Hour nudge for a big-board vending machine on app 315+: `HAPPYHOUR`, on
     * which the APK re-reads the `/thumbnails` JSON only (its `happy_hour` slots and welcome
     * sketches) — never TYPESYNCAPICHANNELSLOTLIST, which re-downloads every channel image over a
     * metered SIM (apk/mark1-apk/UNRELEASED_V315.md). Older builds route the unknown type to
     * OtherMessage (harmless), but it is only sent where HappyHourEligibility says the app reads it.
     * Same envelope, QoS 1 and `high` queue as the menu nudge; no VendJob row (the APK sends no ack).
     *
     * @param  int|Vend  $vend
     * @return bool true when a nudge was published
     */
    public function nudgeHappyHourToVend($vend): bool
    {
        $vendModel = $vend instanceof Vend ? $vend : Vend::withoutGlobalScopes()->find($vend);
        if (! $vendModel || ! HappyHourEligibility::supportsVending($vendModel)) {
            return false;
        }
        $this->publishBareFrame($vendModel, 'HAPPYHOUR');

        return true;
    }

    /**
     * The machine's QR payment menu changed (PushPaymentMenu): app 321+ re-reads its wallet list only — no settings,
     * no menu images, no restart.
     */
    public function nudgePaymentMenuToVend(Vend $vend): void
    {
        $this->publishBareFrame($vend, 'PAYMENTMENU');
    }

    /** `{"Type":…,"time":…,"action":"","mid":<code>}` on CM<code>, signed with the vend's key, QoS 1. */
    private function publishBareFrame(Vend $vend, string $type): void
    {
        $fid = 1;
        $content = base64_encode(json_encode([
            'Type' => $type,
            'time' => now()->timestamp,
            'action' => '',
            'mid' => $vend->code,
        ]));
        $contentLength = strlen($content);
        $key = $vend->private_key ?: config('vend.private_key');
        $md5 = md5($fid.','.$contentLength.','.$content.$key);

        PublishMqtt::dispatch('CM'.$vend->code, $fid.','.$contentLength.','.$content.','.$md5, 1)
            ->onQueue('high');
    }
}
