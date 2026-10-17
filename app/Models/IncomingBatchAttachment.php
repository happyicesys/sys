<?php

namespace App\Models;

use App\Models\Scopes\OperatorIncomingBatchAttachmentScope;
use Illuminate\Database\Eloquent\Model;

/**
 * A file kept with an Incoming Stock batch, keyed by batch_number (a batch is the
 * product_movements rows sharing it — there is no batch table) and the batch's operator.
 *
 * Supplier invoices and DOs carry cost prices, so the file is PRIVATE on Spaces and is only
 * ever served through the authed route (audit UI-02, 2026-10-17): `full_url` answers that
 * route for every row, whatever the column holds.
 */
class IncomingBatchAttachment extends Model
{
    protected static function booted()
    {
        static::addGlobalScope(new OperatorIncomingBatchAttachmentScope);
    }

    public const DIR = 'sys/incoming-batches';

    public const MIMES = 'jpg,jpeg,png,webp,heic,heif,gif,pdf,xls,xlsx,csv,doc,docx';

    public const MAX_KB = 20480;

    // Prod PHP's max_file_uploads is 20 and silently drops the rest.
    public const MAX_FILES = 10;

    protected $fillable = [
        'batch_number',
        'operator_id',
        'local_url',
        'full_url',
        'name',
        'mime_type',
        'size',
        'user_id',
    ];

    /** The authed download route — never the file's own URL (the file is private). */
    public function getFullUrlAttribute(): string
    {
        return route('product-movements.incoming-attachments.show', $this->id);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
