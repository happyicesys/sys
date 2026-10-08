<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Welcome-screen sketches (the treat that falls to the polar bear)
    |--------------------------------------------------------------------------
    | Each product can carry a hand-drawn-style sketch (transparent WebP) that the
    | freezer's welcome scene drops instead of the catalog photo
    | (ProductWelcomeSketchService). When a product's photo is saved, mark1 asks an
    | image model to redraw it in the style of the approved sketches. Inert until
    | OPENAI_API_KEY is set (config services.openai.api_key); every product keeps its
    | photo as the fallback meanwhile.
    */
    'welcome_sketch' => [
        // false stops automatic conversion on save; Regenerate on Product → Edit still works.
        'auto_generate' => env('WELCOME_SKETCH_AUTO', true),
        'model' => env('WELCOME_SKETCH_MODEL', 'gpt-image-1'),
        'quality' => env('WELCOME_SKETCH_QUALITY', 'medium'),
        // Longest side of the stored sketch, px (the approved set is ≤ 512).
        'max_px' => (int) env('WELCOME_SKETCH_MAX_PX', 512),
        'timeout_seconds' => (int) env('WELCOME_SKETCH_TIMEOUT', 150),
        // Free fallback (RembgCutout): background removed, finished as a white-outlined sticker.
        // Used when no image model is configured or its drawing fails. Missing binary = off.
        'rembg_bin' => env('WELCOME_SKETCH_REMBG_BIN', '/home/forge/.local/bin/rembg'),
        'rembg_model' => env('WELCOME_SKETCH_REMBG_MODEL', 'isnet-general-use'),
        // Passed as REMBG_HOME so the queue worker finds the downloaded model whatever its HOME.
        'rembg_home' => env('WELCOME_SKETCH_REMBG_HOME', '/home/forge/.rembg'),
    ],

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
        // Product approval callback (算法服务接口文档 §7) is unsigned plain JSON: once set, its URL
        // must carry ?token= (we choose the callbackUrl). Unset: the library check alone guards it.
        'sku_callback_token' => env('ZIJIA_SKU_CALLBACK_TOKEN'),
        // Raw request body cap. A push carries URLs + metadata, never the video itself.
        'video_webhook_max_bytes' => (int) env('ZIJIA_VIDEO_WEBHOOK_MAX_BYTES', 256 * 1024),

        /*
        |----------------------------------------------------------------------
        | Zijia algorithm service (智佳算法服务接口文档 v1.0, 2026-09-14)
        |----------------------------------------------------------------------
        | mark1 asks the algorithm which goods left the cabinet in a door session
        | (`dynamic.cabinet.add.queue`, the videos + the cabinet's candidate SKUs)
        | and receives the answer on `notify_url` (`cabinet.algorithm.order.result`).
        | See App\Services\SmartFreezer\Zijia\ZijiaAlgorithmClient.
        |
        | Established against the live service on 2026-09-27 (see
        | apk/smart-freezer/ZIJIA_ALGORITHM_2026-09-27.md):
        |  - host: https://algorithm.zjoyvd.cn (`/api/algorithm/api` answers there);
        |  - requests are signed with OUR appSecret — the key printed in the
        |    supplier's document is rejected ("签名验证失败");
        |  - a missing goodsList makes their server throw, so one is always sent.
        |
        | Inert until app_id + app_secret are set. `auto_submit` stays off until the
        | freezer products are modelled in Zijia's portal and carry that barcode in mark1
        | and a live door session has been proven end to end: a recognition is
        | a metered call on their side (`remainIdentifyTime`).
        */
        'algorithm' => [
            'base_url' => env('ZIJIA_ALGO_BASE_URL', 'https://algorithm.zjoyvd.cn'),
            'app_id' => env('ZIJIA_ALGO_APP_ID'),
            'app_secret' => env('ZIJIA_ALGO_APP_SECRET'),
            // Comma-separated `modelIdList`. OPTIONAL — Zijia (2026-09-30): "可以先不用传";
            // empty sends `[]` (never omitted: a missing goodsList made their server throw).
            'model_ids' => array_values(array_filter(array_map('trim', explode(',', (string) env('ZIJIA_ALGO_MODEL_IDS', ''))))),
            // Where their result is POSTed. Defaults to this app's notify route.
            'notify_url' => env('ZIJIA_ALGO_NOTIFY_URL'),
            // Submit a recognition once a video push is matched to a freezer...
            'auto_submit' => (bool) env('ZIJIA_ALGO_AUTO_SUBMIT', false),
            // ...after this settle window, so a door session pushed as one push per camera goes
            // to the algorithm with every camera's video, not just the first to arrive.
            'submit_delay_seconds' => (int) env('ZIJIA_ALGO_SUBMIT_DELAY', 60),
            'timeout' => (int) env('ZIJIA_ALGO_TIMEOUT', 20),
            // Callback signature: `log` records the verdict and processes anyway;
            // `enforce` refuses a callback whose signature does not verify. Their
            // callbacks verify with our appSecret (2026-09-30); prod runs `enforce`.
            'callback_verification' => env('ZIJIA_ALGO_CALLBACK_VERIFICATION', 'log'),
            // Their timestamps are China time; the freezers run in Singapore — both UTC+8.
            'timezone' => env('ZIJIA_ALGO_TIMEZONE', 'Asia/Shanghai'),
        ],
    ],
];
