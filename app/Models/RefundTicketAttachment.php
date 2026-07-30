<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RefundTicketAttachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'refund_ticket_id',
        'path',
        'original_name',
        'mime',
        'size',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function ticket()
    {
        return $this->belongsTo(RefundTicket::class, 'refund_ticket_id');
    }

    /** The disk new refund attachments are written to (config/refund.php). */
    public static function storageDisk(): string
    {
        return config('refund.attachments.disk', 'local');
    }

    /**
     * The disk this attachment's file actually lives on right now: prefers the
     * configured disk, falls back to 'local' (pre-migration files). Returns
     * null when the file exists on neither — callers 404/skip.
     */
    public function resolveDisk(): ?string
    {
        $configured = static::storageDisk();

        if (\Illuminate\Support\Facades\Storage::disk($configured)->exists($this->path)) {
            return $configured;
        }

        if ($configured !== 'local'
            && \Illuminate\Support\Facades\Storage::disk('local')->exists($this->path)) {
            return 'local';
        }

        return null;
    }
}
