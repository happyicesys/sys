<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class VendConfig extends Model
{
    use HasFactory;

    const VERSION = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z');

    protected $fillable = [
        'desc',
        'is_active',
        'name',
        'operator_id',
        'version'
    ];

    // relationships
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'modelable')->latest();
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    // public function compatibleVendConfigs()
    // {
    //     return $this->belongsToMany(VendConfig::class);
    // }

    public function vends()
    {
        return $this->hasMany(Vend::class);
    }

    public function vendPrefixes() : BelongsToMany
    {
        return $this->belongsToMany(VendPrefix::class);
    }

    // add many to many to self
    public function vendConfigCompatibles()
    {
        return $this->belongsToMany(VendConfig::class, 'vend_config_vend_config', 'vend_config_id', 'compatible_vend_config_id');
    }

    public function vendConfigCompatibleWith()
    {
        return $this->belongsToMany(VendConfig::class, 'vend_config_vend_config', 'compatible_vend_config_id', 'vend_config_id');
    }

    public function getAllCompatiblesAttribute()
    {
        return $this->compatibles->merge($this->compatibleWith);
    }

    public function syncCompatibles(array $compatibleIDs)
    {
        // Eager load relationships
        $this->load(['vendConfigCompatibles', 'vendConfigCompatibleWith']);

        // Retrieve current compatible settings
        $currentCompatibles = $this->vendConfigCompatibles ? $this->vendConfigCompatibles->pluck('id')->toArray() : [];
        $currentCompatibleWith = $this->vendConfigCompatibleWith ? $this->vendConfigCompatibleWith->pluck('id')->toArray() : [];

        // Merge both current compatible arrays and remove duplicates
        $currentSettings = array_unique(array_merge($currentCompatibles, $currentCompatibleWith));

        // Compute the new settings that need to be added
        $newSettings = array_diff($compatibleIDs, $currentSettings);

        // Sync the new settings, detach any not in the new settings
        $this->vendConfigCompatibles()->sync($compatibleIDs);

        // Ensure reverse relationships
        foreach ($newSettings as $settingId) {
            $setting = VendConfig::find($settingId);
            if ($setting) {
                $setting->vendConfigCompatibles()->syncWithoutDetaching([$this->id]);
            }
        }

        // Detach reverse relationships that are no longer needed
        $detachIDs = array_diff($currentSettings, $compatibleIDs);
        foreach ($detachIDs as $settingId) {
            $setting = VendConfig::find($settingId);
            if ($setting) {
                $setting->vendConfigCompatibles()->detach([$this->id]);
            }
        }
    }

}
