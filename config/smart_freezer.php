<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Zijia (smart-freezer supplier) → mark1 video push
    |--------------------------------------------------------------------------
    | Zijia's servers POST the door-session camera video URLs to
    | POST /api/smart-freezer/zijia/videos. The receiver is inert (503) until a
    | token is set; Zijia sends it as `Authorization: Bearer <token>` (or
    | `X-Api-Key`, or `?token=` when their side can only configure a URL).
    | Generate with: php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
    | Keep it in .env only.
    */
    'zijia' => [
        'video_webhook_token' => env('ZIJIA_VIDEO_WEBHOOK_TOKEN'),
        // Raw request body cap. A push carries URLs + metadata, never the video itself.
        'video_webhook_max_bytes' => (int) env('ZIJIA_VIDEO_WEBHOOK_MAX_BYTES', 256 * 1024),
    ],

    /*
    |--------------------------------------------------------------------------
    | Planogram channels
    |--------------------------------------------------------------------------
    | A freezer's vend_channels are written from its planogram (FreezerChannelSync).
    | Nobody has measured how many pieces a basket division holds, so this is the
    | placeholder par every new slot starts on; it must be > 0 or the channel is
    | filed inactive and never shows. A capacity ops has already set is kept.
    */
    'channel_capacity' => (int) env('SMART_FREEZER_CHANNEL_CAPACITY', 20),
];
