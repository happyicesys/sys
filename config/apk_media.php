<?php

/*
 * Banner media (UI Setting → Default / Campaign Media) is normalised on upload
 * by App\Services\ApkMedia\BannerMediaNormalizer, so a phone photo or clip is
 * fitted to the machine instead of shipped as-is to every bound board.
 *
 * Spec from LOO (2026-10-09): 960 W x 1280 H, at most 4 MB. The big-board
 * media frame is a fixed 3:4 portrait (activity_main2.xml), and the APK
 * decodes pictures at full size (BitmapFactory.decodeFile, no sampling).
 * The caption on resources/js/Pages/ApkSetting/Edit.vue states these figures.
 */
return [

    'frame_width' => 960,
    'frame_height' => 1280,

    // What a machine receives.
    'image_max_bytes' => (int) (1.5 * 1024 * 1024),
    'video_max_bytes' => 4 * 1024 * 1024,

    // What a person may upload before it is fitted.
    'image_input_max_bytes' => 30 * 1024 * 1024,
    'video_input_max_bytes' => 500 * 1024 * 1024,

    'jpeg_qualities' => [85, 75, 65],

    // Video needs ffmpeg + ffprobe on the server. Without them a video is
    // stored as uploaded, and only one already within video_max_bytes is
    // accepted (the behaviour before normalising existed).
    'ffmpeg' => env('APK_MEDIA_FFMPEG', '/usr/bin/ffmpeg'),
    'ffprobe' => env('APK_MEDIA_FFPROBE', '/usr/bin/ffprobe'),
    'ffmpeg_threads' => (int) env('APK_MEDIA_FFMPEG_THREADS', 4),

    // The conversion runs inside the upload request; Cloudflare drops a
    // request at 100 s, so stop well before.
    'ffmpeg_timeout_seconds' => 80,

    // Below this the picture turns to mush; a clip that would need less to
    // fit video_max_bytes is refused as too long (~80 s at 4 MB).
    'video_min_bps' => 300_000,
    'video_max_bps' => 2_500_000,
    'audio_bps' => 64_000,
    'max_fps' => 30,

];
