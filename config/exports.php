<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Export Queue Threshold
    |--------------------------------------------------------------------------
    |
    | The number of rows above which exports will be automatically queued
    | instead of processed synchronously. This prevents timeouts and
    | improves user experience for large exports.
    |
    */
    'queue_threshold' => env('EXPORT_QUEUE_THRESHOLD', 10000),

    /*
    |--------------------------------------------------------------------------
    | Export Chunk Size
    |--------------------------------------------------------------------------
    |
    | The number of rows to process at a time when chunking large exports.
    | Lower values use less memory but may be slower. Higher values are
    | faster but use more memory.
    |
    */
    'chunk_size' => env('EXPORT_CHUNK_SIZE', 1000),

    /*
    |--------------------------------------------------------------------------
    | Export Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum time in seconds that a synchronous export is allowed to run
    | before timing out. Queued exports are not subject to this limit.
    |
    */
    'timeout' => env('EXPORT_TIMEOUT', 300),

    /*
    |--------------------------------------------------------------------------
    | Export Storage Disk
    |--------------------------------------------------------------------------
    |
    | The storage disk where export files will be stored. This should be
    | configured in config/filesystems.php
    |
    */
    'storage_disk' => env('EXPORT_STORAGE_DISK', 'digitaloceanspaces'),

    /*
    |--------------------------------------------------------------------------
    | Export File Retention
    |--------------------------------------------------------------------------
    |
    | Number of days to keep export files before they are automatically
    | deleted. Set to 0 to keep files indefinitely.
    |
    */
    'retention_days' => env('EXPORT_RETENTION_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Export Memory Limit
    |--------------------------------------------------------------------------
    |
    | Memory limit for export operations. Set to null to use PHP's default.
    | Format: '512M', '1G', etc.
    |
    */
    'memory_limit' => env('EXPORT_MEMORY_LIMIT', '512M'),
];
