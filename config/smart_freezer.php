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
        // Claude compares each drawn sketch with its photo and flags faults in the album (advice only;
        // inert without ANTHROPIC_API_KEY, config services.anthropic.api_key).
        'check_model' => env('WELCOME_SKETCH_CHECK_MODEL', 'claude-opus-5-5'),
        'check_effort' => env('WELCOME_SKETCH_CHECK_EFFORT', 'low'),
        'check_timeout_seconds' => (int) env('WELCOME_SKETCH_CHECK_TIMEOUT', 60),
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
    /*
    |--------------------------------------------------------------------------
    | Our own door-session recordings (camera 5)
    |--------------------------------------------------------------------------
    | The freezer app records camera 5 from payment until the door closes (max 5 min), shrinks it to
    | 640x360 H.264 and uploads one mp4 per sale (FreezerClipStore, `smart_freezer_clips`). Private on
    | the default disk; viewed through /freezer-clips/{clip} (permission read ai-recognition).
    */
    'clips' => [
        // 5 min at ~500 kbps is ~19 MB; the cap leaves room for a busier scene.
        'max_bytes' => (int) env('FREEZER_CLIP_MAX_BYTES', 40 * 1024 * 1024),
        // Duration is read from the file when ffprobe is present, else the device's figure is kept.
        'ffprobe_bin' => env('FREEZER_CLIP_FFPROBE', '/usr/bin/ffprobe'),
        // How long a viewer's signed Spaces link lives.
        'link_minutes' => (int) env('FREEZER_CLIP_LINK_MINUTES', 30),
        // The switch the freezer reads (GET /api/v1/vends/{code}/clips/settings, every 30 min): the camera
        // to record, 0 = recording off everywhere — turns it off with no OTA. FREEZER_CLIP_VENDS narrows
        // it to a comma list of machine codes (empty = every smart freezer); "2009:3" also picks that
        // machine's camera.
        'camera' => (int) env('FREEZER_CLIP_CAMERA', 5),
        'vends' => array_values(array_filter(array_map('trim', explode(',', (string) env('FREEZER_CLIP_VENDS', ''))))),
        // One host recording per piece; the app chains pieces until the door closes, capped at max.
        'segment_ms' => (int) env('FREEZER_CLIP_SEGMENT_MS', 10000),
        'max_ms' => (int) env('FREEZER_CLIP_MAX_MS', 300000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Camera guard (Brian, 2026-10-16)
    |--------------------------------------------------------------------------
    | Zijia's AI judges a door session from cameras 3 and 4. A freezer on this list asks the host whether
    | they are online when the customer picks a payment method and again after payment, before the door
    | unlocks; a camera down stops the sale (after payment: a card hold is voided, PayNow is refunded by
    | mark1 — see unfulfilled_refund). Served to the freezer as `guard_cameras` on clips/settings, read
    | every 30 min. FREEZER_CAMERA_GUARD_VENDS: empty = off everywhere; "50001" uses the default cameras,
    | "50002:3" or "50002:3+4" its own.
    */
    'camera_guard' => [
        'cameras' => array_values(array_filter(array_map('intval', explode(',', (string) env('FREEZER_CAMERA_GUARD_CAMERAS', '3,4'))))),
        'vends' => array_values(array_filter(array_map('trim', explode(',', (string) env('FREEZER_CAMERA_GUARD_VENDS', ''))))),
    ],

    /*
    | A freezer that took a PayNow payment but never opened its door reports it here
    | (POST /api/v1/vends/{code}/orders/{orderId}/unfulfilled). Only these reasons — each means the door
    | was never commanded to open — and only within max_age_minutes of the approval are refunded.
    */
    'unfulfilled_refund' => [
        // The hw: codes are the host's own door refusals (APK 51+, UnfulfilledOrderReporter).
        'reasons' => [
            'cameras_down', 'unlock_authorization_failed',
            'hw:shoplock', 'hw:business', 'hw:camera', 'hw:lock', 'hw:storage', 'hw:door', 'hw:machine', 'hw:power', 'hw:exception', 'hw:unknown',
        ],
        'max_age_minutes' => (int) env('FREEZER_UNFULFILLED_REFUND_MAX_AGE_MINUTES', 360),
    ],

    'zijia' => [
        'video_webhook_token' => env('ZIJIA_VIDEO_WEBHOOK_TOKEN'),
        /*
        | Our own copy of every door-session video Zijia pushes (Brian, 2026-11-09): kept private on our
        | default disk (Spaces) under freezer-videos/{vend}/zijia/, played on AI Recognition when their
        | link no longer answers. The AI is still sent THEIR link. Only https URLs on these hosts (or a
        | subdomain of an entry starting with ".") are fetched, redirects are not followed.
        */
        'video_copy' => [
            'enabled' => (bool) env('ZIJIA_VIDEO_COPY', true),
            'hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('ZIJIA_VIDEO_COPY_HOSTS', '.aliyuncs.com'))))),
            'max_bytes' => (int) env('ZIJIA_VIDEO_COPY_MAX_BYTES', 100 * 1024 * 1024),
            'timeout' => (int) env('ZIJIA_VIDEO_COPY_TIMEOUT', 180),
            // How long a "their link still answers" check is trusted before asking again.
            'probe_cache_minutes' => (int) env('ZIJIA_VIDEO_PROBE_CACHE_MINUTES', 10),
        ],
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
            // to the algorithm with every camera's video, not just the first to arrive. Short since
            // 2026-11-05: every session since 10-20 arrived as a single push, and the 60 s it was
            // cost every result a minute (Brian).
            'submit_delay_seconds' => (int) env('ZIJIA_ALGO_SUBMIT_DELAY', 5),
            'timeout' => (int) env('ZIJIA_ALGO_TIMEOUT', 20),
            // Callback signature: `log` records the verdict and processes anyway;
            // `enforce` refuses a callback whose signature does not verify. Their
            // callbacks verify with our appSecret (2026-09-30); prod runs `enforce`.
            'callback_verification' => env('ZIJIA_ALGO_CALLBACK_VERIFICATION', 'log'),
            // Their timestamps are China time; the freezers run in Singapore — both UTC+8.
            'timezone' => env('ZIJIA_ALGO_TIMEZONE', 'Asia/Shanghai'),
        ],

        /*
        |----------------------------------------------------------------------
        | Early order video (Brian, 2026-11-09)
        |----------------------------------------------------------------------
        | The order mp4 is on Zijia's OSS ~1 s after the door closes, but their push
        | reaches us ~5 min 50 s later. On the TRADE, mark1 looks for the file itself
        | (EarlyOrderVideo: the machine's last push with this order number) and opens
        | the AI check as soon as it is there; the push stays the fallback.
        | FREEZER_EARLY_VIDEO_VENDS: empty = off everywhere; "50001,50002" or "*".
        */
        'early_video' => [
            'vends' => array_values(array_filter(array_map('trim', explode(',', (string) env('FREEZER_EARLY_VIDEO_VENDS', ''))))),
            // The TRADE arrives at door close; the mp4 is on OSS about a second after it.
            'first_check_seconds' => (int) env('FREEZER_EARLY_VIDEO_FIRST_CHECK', 3),
            'retry_seconds' => (int) env('FREEZER_EARLY_VIDEO_RETRY', 5),
            // Cameras 3 and 4 are both looked for. Once one is there, the other gets this long to show up
            // (both land within ~1 s when both work); then what exists is sent. An offline camera never holds the sale.
            'camera_grace_seconds' => (int) env('FREEZER_EARLY_VIDEO_CAMERA_GRACE', 10),
            // After this, nothing is recorded and Zijia's push is waited for as before.
            'give_up_seconds' => (int) env('FREEZER_EARLY_VIDEO_GIVE_UP', 90),
            'head_timeout' => (int) env('FREEZER_EARLY_VIDEO_HEAD_TIMEOUT', 5),
            // A TRADE further than this from now is an outbox replay: not looked for.
            'max_trade_age_seconds' => (int) env('FREEZER_EARLY_VIDEO_MAX_TRADE_AGE', 900),
            // The machine's last push is the link pattern; one older than this is not trusted.
            'template_max_age_days' => (int) env('FREEZER_EARLY_VIDEO_TEMPLATE_DAYS', 14),
            // Only links on these hosts are ever built.
            'hosts' => array_values(array_filter(array_map('trim', explode(',', (string) env('FREEZER_EARLY_VIDEO_HOSTS', 'jishi1.oss-cn-hangzhou.aliyuncs.com'))))),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Claude's count beside Zijia's (Brian, 2026-11-13)
    |--------------------------------------------------------------------------
    | Every door session Zijia's AI is asked about is also counted by Claude from the same videos (cameras 3
    | and 4 only — never the customer-facing camera), so the two can be compared on AI Recognition and with
    | `smart-freezer:claude-recognition --report`. SHADOW ONLY: nothing reads Claude's count to charge,
    | release, refund or move stock. Off until CLAUDE_FREEZER_AI=true and ANTHROPIC_API_KEY is set
    | (config services.anthropic.api_key).
    */
    'claude_recognition' => [
        'enabled' => (bool) env('CLAUDE_FREEZER_AI', false),
        // Empty = every smart freezer; else a comma list of machine codes.
        'vends' => array_values(array_filter(array_map('trim', explode(',', (string) env('CLAUDE_FREEZER_AI_VENDS', ''))))),
        // The default; AI Recognition's picker (permission update ai-recognition) chooses among `models` and wins.
        'model' => env('CLAUDE_FREEZER_AI_MODEL', 'claude-opus-5-5'),
        // What the picker offers, most capable first. Each needs a row in `prices`.
        'models' => [
            'claude-fable-5-2' => 'Claude Fable 5.2',
            'claude-opus-5-5' => 'Claude Opus 5.5',
            'claude-sonnet-5-5' => 'Claude Sonnet 5.5',
            'claude-haiku-5-5' => 'Claude Haiku 5.5',
        ],
        'effort' => env('CLAUDE_FREEZER_AI_EFFORT', 'high'),
        // Waits after the session opens, so every camera's video and our own copy are in.
        'delay_seconds' => (int) env('CLAUDE_FREEZER_AI_DELAY', 120),
        // Cameras sent (from the file name "-d1c3-"): 3 Real-Front View, 4 Top View. Camera 5 sees faces.
        'cameras' => array_values(array_filter(array_map('intval', explode(',', (string) env('CLAUDE_FREEZER_AI_CAMERAS', '3,4'))))),
        // The first and last frame of each video (the baskets before and after), the rest spread over the part where
        // something moves (ffmpeg scene change above motion_threshold, padded motion_pad_ms), so a hand reaching in is
        // seen in more frames for the same cost. A video with no motion found is spread evenly. The budget is per
        // SESSION, split over the cameras that have a video: 30 = 15 each from cameras 3 and 4, or 30 from camera 3
        // alone (2026-11-13, raised from 12 per camera after a one-camera session missed two of four items).
        'frames_per_session' => (int) env('CLAUDE_FREEZER_AI_FRAMES', 30),
        // Each camera's baskets at full detail at the start and the end of the video (Brian, 2026-11-13: two identical
        // bars lifted together read as one at 576 px). box = the basket area as shares of the picture (left, top, right,
        // bottom), checked on 50001's camera 3 on 2026-11-13; a camera without a box keeps the whole picture.
        'closeups' => [
            'enabled' => (bool) env('CLAUDE_FREEZER_AI_CLOSEUPS', true),
            'width_px' => (int) env('CLAUDE_FREEZER_AI_CLOSEUP_WIDTH', 1568),
            'box' => [3 => [0.15, 0.36, 0.87, 0.80]],
        ],
        // Contact sheets over the part of each video where something moves: 3x3 small pictures 4 a second, cropped to the
        // baskets and the space above them (box as for close-ups). Tuning, 2026-11-13: 15-16 of 20 at 4 a second, 14 at 3, 13 without.
        'sheets' => [
            'enabled' => (bool) env('CLAUDE_FREEZER_AI_SHEETS', true),
            'fps' => (float) env('CLAUDE_FREEZER_AI_SHEET_FPS', 4),
            'cols' => 3,
            'rows' => 3,
            'max' => (int) env('CLAUDE_FREEZER_AI_SHEET_MAX', 8),
            'width_px' => 1568,
            'pad_ms' => 600,
            'box' => [3 => [0.12, 0.08, 0.90, 0.85]],
        ],
        // Each session is counted `runs` times (2) and once more while the counts disagree, up to `max_runs` (3); the
        // majority wins and only an agreed count with no doubt is "sure". Tuning, 2026-11-13: majority of three 17/20,
        // one count 15-16/20. The repeats read the cached request at a tenth of the input price.
        'runs' => (int) env('CLAUDE_FREEZER_AI_RUNS', 2),
        'max_runs' => (int) env('CLAUDE_FREEZER_AI_MAX_RUNS', 3),
        // Worked examples sent with every call: door sessions (AI Recognition ids) whose count a person confirmed,
        // shown with that count, the closest Claude comes to being trained on our freezers. The answer is the person's
        // Adjust Qty, else Zijia's count. A session is never its own example; the same freezer's come first.
        // The block is cached for 5 minutes (a repeat read costs a tenth), which a run of past sessions reuses.
        'examples' => array_values(array_filter(array_map('intval', explode(',', (string) env('CLAUDE_FREEZER_AI_EXAMPLES', ''))))),
        'max_examples' => (int) env('CLAUDE_FREEZER_AI_MAX_EXAMPLES', 2),
        'example_frames' => (int) env('CLAUDE_FREEZER_AI_EXAMPLE_FRAMES', 8),
        'motion_threshold' => (float) env('CLAUDE_FREEZER_AI_MOTION', 0.02),
        'motion_pad_ms' => (int) env('CLAUDE_FREEZER_AI_MOTION_PAD_MS', 500),
        // Height of each frame sent, px; width follows the picture (1024x576 ≈ 790 input tokens a frame).
        'frame_height_px' => (int) env('CLAUDE_FREEZER_AI_FRAME_HEIGHT', 576),
        // Share of the width kept, centred, per camera: camera 3's fisheye leaves black corners outside the baskets
        // (checked on 50001's video, 2026-11-13: every basket kept), so 80% cuts ~20% of its tokens. 1 = no crop.
        'crop_width' => [3 => (float) env('CLAUDE_FREEZER_AI_CROP_CAM3', 0.8)],
        // Hard stop in US dollars a calendar month (person or automatic): above it no call is made. The Ultra plan's
        // credit is US$200 a month and Console auto-reload is off, so nothing is ever charged to a card.
        'monthly_budget_usd' => (float) env('CLAUDE_FREEZER_AI_MONTHLY_BUDGET', 150),
        'product_photo_px' => (int) env('CLAUDE_FREEZER_AI_PHOTO_PX', 256),
        'max_video_bytes' => (int) env('CLAUDE_FREEZER_AI_MAX_VIDEO_BYTES', 100 * 1024 * 1024),
        'ffmpeg_bin' => env('CLAUDE_FREEZER_AI_FFMPEG', '/usr/bin/ffmpeg'),
        // The SDK's own timeout is advisory; the Guzzle client enforces this one.
        'timeout_seconds' => (int) env('CLAUDE_FREEZER_AI_TIMEOUT', 300),
        // Calls a day, counted from midnight. A session over the cap is skipped with the reason, never queued.
        'daily_limit' => (int) env('CLAUDE_FREEZER_AI_DAILY_LIMIT', 100),
        // Anthropic list prices, US dollars per million tokens (2026-11-13). The model the API says answered
        // is the one priced; an unknown model is stored with no cost.
        'prices' => [
            'claude-fable-5-2' => ['input' => 8.00, 'output' => 40.00, 'cache_write' => 10.00, 'cache_read' => 0.20],
            'claude-opus-5-5' => ['input' => 4.00, 'output' => 20.00, 'cache_write' => 5.00, 'cache_read' => 0.20],
            'claude-opus-5' => ['input' => 5.00, 'output' => 25.00, 'cache_write' => 6.25, 'cache_read' => 0.50],
            'claude-opus-4-8' => ['input' => 5.00, 'output' => 25.00, 'cache_write' => 6.25, 'cache_read' => 0.50],
            'claude-sonnet-5-5' => ['input' => 2.00, 'output' => 10.00, 'cache_write' => 2.50, 'cache_read' => 0.10],
            'claude-sonnet-5' => ['input' => 2.00, 'output' => 10.00, 'cache_write' => 2.50, 'cache_read' => 0.20],
            'claude-haiku-5-5' => ['input' => 0.10, 'output' => 0.50, 'cache_write' => 0.125, 'cache_read' => 0.01],
        ],
        // Costs are shown in the site's currency (ClaudeCostMoney): this one when set, else the one currency all of the
        // server's operators share, else SGD. The API bills in US dollars; units per US dollar on 2026-11-13: SGD OFX
        // interbank 1.2865, IDR Valuta/Wise ~18,200, MYR Pluang 4.078. A currency missing here is shown in US$.
        'currency' => env('CLAUDE_FREEZER_AI_CURRENCY'),
        'usd_rates' => [
            'SGD' => (float) env('CLAUDE_FREEZER_AI_USD_SGD', 1.29),
            'IDR' => (float) env('CLAUDE_FREEZER_AI_USD_IDR', 18200),
            'MYR' => (float) env('CLAUDE_FREEZER_AI_USD_MYR', 4.08),
        ],
    ],
];
