<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Telco extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'desc',
        'remarks',
        // config/simcard_usage.php provider key ('voiceping', ...) — null means
        // this telco has no usage API and simcards:sync-usage skips its sims.
        'usage_provider',
        // Optional per-package override of that provider's default endpoint —
        // the "API query link" on the SimCard Package form. Null = provider default.
        'usage_endpoint',
    ];

    // relationships
    public function simcards()
    {
        return $this->hasMany(Simcard::class);
    }
}
