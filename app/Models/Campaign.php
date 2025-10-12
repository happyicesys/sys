<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'is_using_qty',
        'bundle_qty',
        'value',
        'min_basket_value',
        'max_discount_value',
        'start_at',
        'end_at',
    ];

    protected $casts = [
        'is_using_qty' => 'string',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
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

    public function apkSettings()
    {
        return $this->belongsToMany(ApkSetting::class, 'apk_setting_campaign')->withTimestamps();
    }

    protected function value(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->convertStoredIntegerToDecimal($value),
            set: fn ($value) => $this->convertDecimalToStoredInteger($value)
        );
    }

    protected function minBasketValue(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->convertStoredIntegerToDecimal($value),
            set: fn ($value) => $this->convertDecimalToStoredInteger($value)
        );
    }

    protected function maxDiscountValue(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $this->convertStoredIntegerToDecimal($value),
            set: fn ($value) => $this->convertDecimalToStoredInteger($value)
        );
    }

    private function convertDecimalToStoredInteger($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = trim($value);

            if ($value === '') {
                return null;
            }
        }

        return (int) round((float) $value * 100);
    }

    private function convertStoredIntegerToDecimal($value): ?float
    {
        if ($value === null) {
            return null;
        }

        return round(((int) $value) / 100, 2);
    }
}
