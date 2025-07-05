<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportJobChunk extends Model
{
    protected $fillable = [
        'export_job_id',
        'chunk_index',
        'status',
        'filename',
        'error_message',
    ];

}
