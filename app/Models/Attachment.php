<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Attachment extends Model
{
    use HasFactory;

    protected $fillable = [
        'local_url',
        'modelable_id',
        'modelable_type',
        'full_url',
        'is_active',
        'type',
        'sequence',
        'name',
        'desc',
    ];

    // protected function createdAt(): Attribute
    // {
    //     return Attribute::make(
    //         get: fn (string $value) => Carbon::parse($value)->format('ymd h:i a'),
    //     );
    // }

    // relationships
    public function modelable()
    {
        return $this->morphTo();
    }

    /**
     * Delete this attachment's file from the default disk (DO Spaces in prod) —
     * unless another attachment row still points at the same file. Replicating
     * a product mapping used to share its files instead of copying them (prod
     * 2026-10-09: 52 files held by 158 rows), so deleting one copy's image must
     * not break the others. Returns whether the file was deleted.
     *
     * Product thumbnails and machine photos store their full URL in local_url
     * (660 rows in prod). There is no disk path to delete for those, and other
     * records may link that URL, so they are left alone as before.
     */
    public function deleteFileIfUnshared(): bool
    {
        if (! $this->local_url || str_contains($this->local_url, '://')) {
            return false;
        }

        $shared = static::query()
            ->where('local_url', $this->local_url)
            ->whereKeyNot($this->getKey())
            ->exists();

        return $shared ? false : Storage::delete($this->local_url);
    }
}
