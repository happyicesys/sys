<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;
use App\Models\Operator;
use App\Models\Tag;
use App\Models\User;
use App\Support\OperatorScope;
use App\Traits\GetUserTimezone;

class Campaign extends Model
{
    use GetUserTimezone, HasFactory;

    const TYPE_PERCENTAGE = 'Percentage';
    const TYPE_AMOUNT = 'Amount';
    const TYPE_ABSOLUTE = 'Absolute';
    const TYPE_ITEM = 'Free';

    const TYPES_MAPPING = [
        self::TYPE_PERCENTAGE => 'Percentage',
        self::TYPE_AMOUNT => 'Amount',
        self::TYPE_ABSOLUTE => 'Absolute',
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

    /**
     * Constrain the query to the campaigns the viewer's operator may see -
     * the same rule Vend/CustomerIndex enforces (HIPL staff see the HIPL
     * sibling group, everyone else sees only their own operator).
     *
     * This is the authorization gate and is applied BEFORE the user's own
     * Operator filter, so a hand-crafted `operators[]` in the query string can
     * only narrow the result set, never widen it. The predicate is applied
     * unconditionally: an empty ceiling must yield zero rows, not every row.
     */
    public function scopeVisibleTo($query, ?User $user = null)
    {
        return $query->whereIn(
            $query->getModel()->qualifyColumn('operator_id'),
            OperatorScope::forUser($user ?: auth()->user())
        );
    }

    public function scopeFilterIndex($query, $request)
    {
        $query->when($request->name, function ($query, $search) {
            $query->where('name', 'LIKE', "%{$search}%");
        });

        // Operator narrowing. CampaignController::index always merges a
        // RESOLVED array of ids here, so the array branch is the live path and
        // must be applied UNCONDITIONALLY: an empty array means "sees nothing"
        // and has to produce zero rows. The old `when($request->operators, ...)`
        // treated [] as falsy and dropped the predicate entirely, which turned
        // "sees nothing" into "sees everything".
        $operators = $request->operators;

        if (is_array($operators)) {
            $query->whereIn('campaigns.operator_id', $operators);
        } elseif ($operators !== null && $operators !== '' && $operators !== 'all') {
            // Back-compat: a single operator_id passed as a scalar.
            $query->where('campaigns.operator_id', $operators);
        }

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

    public static function promoTypeValidationValues(): array
    {
        return array_keys(self::TYPES_MAPPING);
    }

    public static function normalizePromoType(?string $promoType): ?string
    {
        if ($promoType === null) {
            return null;
        }

        $trimmed = trim($promoType);

        if ($trimmed === '') {
            return null;
        }

        foreach (self::TYPES_MAPPING as $type => $label) {
            if (strcasecmp($trimmed, $type) === 0 || strcasecmp($trimmed, $label) === 0) {
                return $type;
            }
        }

        return $trimmed;
    }

    protected function value(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $this->convertStoredIntegerToDecimal($value),
            set: fn($value) => $this->convertDecimalToStoredInteger($value)
        );
    }

    protected function minBasketValue(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $this->convertStoredIntegerToDecimal($value),
            set: fn($value) => $this->convertDecimalToStoredInteger($value)
        );
    }

    protected function maxDiscountValue(): Attribute
    {
        return Attribute::make(
            get: fn($value) => $this->convertStoredIntegerToDecimal($value),
            set: fn($value) => $this->convertDecimalToStoredInteger($value)
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
