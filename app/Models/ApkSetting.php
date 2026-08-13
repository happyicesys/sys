<?php

namespace App\Models;

use App\Casts\AsApkSettingParameters;
use App\Models\Scopes\OperatorApkSettingScope;
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
        // Read-heals rows saved before newer settings existed (missing keys
        // get schema defaults) and whitelists on write. See
        // App\ValueObjects\ApkSettingParameters — the single place to add a
        // new setting key.
        'settings_parameter_json' => AsApkSettingParameters::class,
    ];

    public function campaignImages()
    {
        return $this->morphMany(Attachment::class, 'modelable')->where('type', ApkSetting::FILE_TYPE_CAMPAIGN_IMAGE)->oldest();
    }

    public function campaignItems()
    {
        return $this->hasMany(CampaignItem::class);
    }

    public function campaigns()
    {
        return $this->belongsToMany(Campaign::class, 'apk_setting_campaign')->withTimestamps();
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

    /**
     * Default-set media — pictures AND videos as one list, ordered by name:
     * the order the v302+ "mixed" banner mode plays them on the machine.
     * The per-type relations above still feed the four legacy endpoints the
     * deployed fleet polls; these combined ones exist for the unified UI.
     */
    public function defaultMedia()
    {
        return $this->morphMany(Attachment::class, 'modelable')
            ->whereIn('type', [self::FILE_TYPE_IMAGE, self::FILE_TYPE_VIDEO])
            ->orderBy('name');
    }

    public function campaignMedia()
    {
        return $this->morphMany(Attachment::class, 'modelable')
            ->whereIn('type', [self::FILE_TYPE_CAMPAIGN_IMAGE, self::FILE_TYPE_CAMPAIGN_VIDEO])
            ->orderBy('name');
    }

    // filter index
    public function scopeFilterIndex($query, $request)
    {
        return $query->when($request->name, function ($query, $search) {
            $query->where('name', 'LIKE', "{$search}%");
        })
            ->when($request->codes, function ($query, $search) {
                if (strpos($search, ',') !== false) {
                    $search = explode(',', $search);
                    $query->whereHas('vends', function ($query) use ($search) {
                        $query->whereIn('code', $search);
                    });
                } else {
                    $query->whereHas('vends', function ($query) use ($search) {
                        $query->where('code', 'LIKE', "%{$search}%");
                    });
                }
            })
            ->when($request->sortKey, function ($query, $search) use ($request) {

                // Handle sorting for non-JSON fields
                $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
            });
    }
}
