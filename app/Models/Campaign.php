<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Models\Operator;
use App\Models\Tag;
use App\Traits\GetUserTimezone;

class Campaign extends Model
{
    use GetUserTimezone, HasFactory;

    const TYPE_PERCENTAGE = 'Percentage';
    const TYPE_AMOUNT = 'Amount';
    const TYPE_ITEM = 'Item';

    const TYPES_MAPPING = [
        self::TYPE_PERCENTAGE => 'Percentage',
        self::TYPE_AMOUNT => 'Absolute Amount',
        self::TYPE_ITEM => 'Free Item',
    ];

    protected $fillable = [
        'is_active',
        'name',
        'operator_id',
        'remarks',
        'uuid',
        'slug',
        'description',
        'promo_type',
        'bundle_qty',
        'value',
        'start_at',
        'end_at',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'value' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    // scopes
    public function scopeFilterIndex($query, $request)
    {

        $query = $query
            ->when($request->name, function($query, $search) {
                $query->where('name', 'LIKE', "%{$search}%");
            })
            ->when($request->operators, function($query, $search) {
                if($search != 'all') {
                    // Keep compatibility; if a single operator_id is passed
                    // use it directly, else assume an array of IDs
                    $query->when(is_array($search), function($q) use ($search) {
                        $q->whereIn('operator_id', $search);
                    }, function($q) use ($search) {
                        $q->where('operator_id', $search);
                    });
                }
            });

        return $query;
    }

    // relationships
    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'campaign_tag')->withPivot('type')->withTimestamps();
    }

    public function labelsX()
    {
        return $this->tags()->wherePivot('type', 'x');
    }

    public function labelsY()
    {
        return $this->tags()->wherePivot('type', 'y');
    }
}
