<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A file kept with an Incoming Stock batch, keyed by batch_number (a batch is the
 * product_movements rows sharing it — there is no batch table).
 */
class IncomingBatchAttachment extends Model
{
    public const DIR = 'sys/incoming-batches';

    public const MIMES = 'jpg,jpeg,png,webp,heic,heif,gif,pdf,xls,xlsx,csv,doc,docx';

    public const MAX_KB = 20480;

    // Prod PHP's max_file_uploads is 20 and silently drops the rest.
    public const MAX_FILES = 10;

    protected $fillable = [
        'batch_number',
        'local_url',
        'full_url',
        'name',
        'mime_type',
        'size',
        'user_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
