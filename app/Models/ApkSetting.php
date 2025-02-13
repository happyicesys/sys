<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApkSetting extends Model
{
    use HasFactory;

    const FILE_TYPE_IMAGE = 1;
    const FILE_TYPE_VIDEO = 2;
    const FILE_TYPE_CAMPAIGN_IMAGE = 3;
    const FILE_TYPE_CAMPAIGN_VIDEO = 4;

    protected static function booted()
    {
        static::addGlobalScope(new OperatorApkSettingScope);
    }

    protected $fillable = [
        'name',
        'remarks',
        'settings_parameter_json',
    ];

    protected $casts = [
        'settings_parameter_json' => 'json',
    ];

    public function campaignImages()
    {
        return $this->morphMany(Attachment::class, 'modelable')->where('type', ApkSetting::FILE_TYPE_CAMPAIGN_IMAGE)->oldest();
    }

    public function campaignItems()
    {
        return $this->hasMany(CampaignItem::class);
    }

    public function campaignVideos()
    {
        return $this->morphMany(Attachment::class, 'modelable')->where('type', ApkSetting::FILE_TYPE_CAMPAIGN_VIDEO)->oldest();
    }

    public function images()
    {
        return $this->morphMany(Attachment::class, 'modelable')->where('type', ApkSetting::FILE_TYPE_IMAGE)->oldest();
    }

    public function vends()
    {
        return $this->belongsToMany(Vend::class)->using(ApkSettingVend::class);
    }

    public function videos()
    {
        return $this->morphMany(Attachment::class, 'modelable')->where('type', ApkSetting::FILE_TYPE_VIDEO)->oldest();
    }

    // filter index
    public function scopeFilterIndex($query, $request)
    {
        return $query->when($request->name, function($query, $search) {
            $query->where('name', 'LIKE', "{$search}%");
        })
        ->when($request->codes, function($query, $search) {
            if(strpos($search, ',') !== false) {
                $search = explode(',', $search);
                $query->whereHas('vends', function($query) use ($search) {
                    $query->whereIn('code', $search);
                });
            }else {
                $query->whereHas('vends', function($query) use ($search)  {
                    $query->where('code', 'LIKE', "%{$search}%");
                });
            }
        })
        ->when($request->sortKey, function($query, $search) use ($request) {

            // Handle sorting for non-JSON fields
            $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
        });
    }
}
