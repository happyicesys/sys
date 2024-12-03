<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApkSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'remarks',
        'settings_parameter_json',
    ];

    protected $casts = [
        'settings_parameter_json' => 'json',
    ];

    public function images()
    {
        return $this->morphMany(Attachment::class, 'modelable')->where('type', 'image')->oldest();
    }

    public function vends()
    {
        return $this->belongsToMany(Vend::class)->using(ApkSettingVend::class);
    }

    public function videos()
    {
        return $this->morphMany(Attachment::class, 'modelable')->where('type', 'video')->oldest();
    }

    // filter index
    public function scopeFilterIndex($query, $request)
    {
        return $query->when($request->name, function($query, $search) {
            $query->where('name', 'LIKE', "{$search}%");
        })
        ->when($request->codes, function($query, $codes) {
            $query->whereHas('vends', function($query) use ($codes) {
                $query->whereIn('vend_id', $codes);
            });
        })
        ->when($request->sortKey, function($query, $search) use ($request) {

            // Handle sorting for non-JSON fields
            $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
        });
    }
}
