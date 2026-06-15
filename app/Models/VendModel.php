<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendModel extends Model
{
    use HasFactory;

    /**
     * Canonical name of the AI smart-freezer model (seeded by VendModelSeeder).
     *
     * Single source of truth for identifying a smart vend across environments: ids vary per
     * database, but the name is fixed by the seeder, so behaviour gates resolve on the name
     * (see Vend::isSmart()) rather than on a hard-coded id.
     */
    const SMART_VEND = 'Smart Vend';

    protected $fillable = [
        'name',
        'desc',
        'is_sortable'
    ];

    /** True when this model is the AI smart-freezer model. */
    public function getIsSmartAttribute(): bool
    {
        return $this->name === self::SMART_VEND;
    }

    public function vends()
    {
        return $this->hasMany(Vend::class);
    }
}
