<?php

return [

    'private_key' => env('VM_DEFAULT_PRIVATE_KEY', '123456789110138A'),

    // Setting/Edit "View Screen" (App\Support\ScreenshotEligibility): the first app build of each
    // family that answers the SCREENSHOT frame. Big board 317 (2026-10-14); freezer 12.
    'screenshot' => [
        'min_vending_apk' => (int) env('SCREENSHOT_MIN_VENDING_APK', 317),
        'min_freezer_apk' => (int) env('SCREENSHOT_MIN_FREEZER_APK', 12),
        // The system's own capture at 07:00 and 17:00 (screenshots:capture-scheduled, Brian
        // 2026-10-16), spread over this many minutes so the fleet's uploads do not land together.
        'auto_enabled' => (bool) env('SCREENSHOT_AUTO_CAPTURE', true),
        'auto_spread_minutes' => (int) env('SCREENSHOT_AUTO_SPREAD_MINUTES', 10),
    ],

    // Ops Dashboard "Needs attention" (App\Services\OpsDashboard\CriticalAttention, Brian 2026-10-17).
    // A board restarts now and then (power cut, watchdog) — on a normal day 89 of 240 did at least
    // once, 25 five times or more — so only a machine that keeps restarting is flagged.
    // External 4G modem reset (App\Support\ModemReset, Brian 2026-11-07): only when the board has had no HTTP
    // and no MQTT for offline_minutes, its modem was heard within modem_contact_minutes, at most once per
    // cooldown_minutes. MODEM_RESET_ENABLED=false stops every reset.
    'modem_reset' => [
        'enabled' => (bool) env('MODEM_RESET_ENABLED', true),
        'offline_minutes' => (int) env('MODEM_RESET_OFFLINE_MINUTES', 10),
        'cooldown_minutes' => (int) env('MODEM_RESET_COOLDOWN_MINUTES', 30),
        'modem_contact_minutes' => (int) env('MODEM_RESET_MODEM_CONTACT_MINUTES', 15),
        // At most this many resets per minute's run (2026-11-26): when many boards look silent at once the cause is
        // usually OUR side (mark1 down 10:18–10:28, then 4 modems reset at 10:36 for nothing), not their modems.
        'max_per_run' => (int) env('MODEM_RESET_MAX_PER_RUN', 3),
    ],

    'attention' => [
        'no_sales_hours' => (int) env('OPS_ATTENTION_NO_SALES_HOURS', 48),
        'apk_restarts_per_day' => (int) env('OPS_ATTENTION_APK_RESTARTS_PER_DAY', 5),
        // A fitted payment rail (card terminal / LCD QR / coin mech) with no sale for this long gets a
        // red frame on its Operation Dashboard badge (App\Support\PaymentRailIdle, Brian 2026-10-17).
        'payment_idle_hours' => (int) env('OPS_ATTENTION_PAYMENT_IDLE_HOURS', 48),
        // (1B) Controller not responding: no VENDER / ACBSTATUS for this long while the machine is still
        // in contact (App\Support\ControllerLink, Brian 2026-11-05). Normal silences, refill visits
        // included, lasted about 10 min on 2351. The switch stops the emails only.
        'controller_silent_minutes' => (int) env('OPS_ATTENTION_CONTROLLER_SILENT_MINUTES', 30),
        'controller_silent_email' => (bool) env('OPS_ATTENTION_CONTROLLER_SILENT_EMAIL', true),
        // A controller that keeps dropping out emails once per this many minutes, not per drop.
        'controller_silent_email_cooldown_minutes' => (int) env('OPS_ATTENTION_CONTROLLER_SILENT_EMAIL_COOLDOWN_MINUTES', 60),
        // (1C) Temperature probe error: a probe that read normally now sends 32767 (App\Support\TempProbe,
        // Brian 2026-11-07, machine 2573). Alerts after this long in error; the fault is over once every probe
        // in it has read normally this long in a row. The switch stops the emails only.
        'temp_probe_error_minutes' => (int) env('OPS_ATTENTION_TEMP_PROBE_ERROR_MINUTES', 30),
        'temp_probe_recover_minutes' => (int) env('OPS_ATTENTION_TEMP_PROBE_RECOVER_MINUTES', 60),
        'temp_probe_error_email' => (bool) env('OPS_ATTENTION_TEMP_PROBE_ERROR_EMAIL', true),
        'temp_probe_error_email_cooldown_minutes' => (int) env('OPS_ATTENTION_TEMP_PROBE_ERROR_EMAIL_COOLDOWN_MINUTES', 60),
    ],

    // Fleet-wide "re-read" frames are paced, never sent to every machine in the same second
    // (App\Services\Vend\MachineNudgePacer; Brian, 2026-11-26, after the 10:18 outage). Each machine
    // costs weight units; the queue releases units_per_second. A full menu or settings re-read
    // re-downloads photos / banner media, so weighs 5 (2 machines a second at the default 10); a
    // JSON-only re-read weighs 1. 0 units_per_second = send at once (the old behaviour).
    'nudge_pace' => [
        'units_per_second' => (int) env('MACHINE_NUDGE_UNITS_PER_SECOND', 10),
        'min_delay_seconds' => (int) env('MACHINE_NUDGE_MIN_DELAY_SECONDS', 1),
    ],

];
