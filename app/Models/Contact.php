<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Propaganistas\LaravelPhone\PhoneNumber;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'company',
        'email',
        'phone_num',
        'phone_country_id',
        'alt_phone_num',
        'alt_phone_country_id',
    ];

    /**
     * Normalise the email field on assignment. The Billing Contact email may
     * hold more than one address (e.g. migrated from CMS where it's a free
     * textarea). Split on whitespace / newline / comma / semicolon, trim,
     * drop blanks, de-duplicate case-insensitively, and re-join as a clean
     * comma-separated string. Idempotent for a single address.
     *
     * When sending later, explode(', ', $email) gives an array Mail::to()
     * accepts directly.
     */
    public function setEmailAttribute($value): void
    {
        $this->attributes['email'] = self::normalizeEmails($value);
    }

    public static function normalizeEmails($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $parts = preg_split('/[\s,;]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $seen = [];
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            $key = mb_strtolower($part);
            if (!array_key_exists($key, $seen)) {
                $seen[$key] = $part;
            }
        }

        return empty($seen) ? null : implode(', ', array_values($seen));
    }

    // relationships
    public function modelable()
    {
        return $this->morphTo();
    }

    public function phoneCountry()
    {
        return $this->belongsTo(Country::class, 'phone_country_id');
    }

    public function altPhoneCountry()
    {
        return $this->belongsTo(Country::class, 'alt_phone_country_id');
    }

    // mutator and accessor
    protected function phoneNum(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => PhoneNumber::make(str_replace(' ', '', $profileData['contact']), $this->phoneCountry->code)->formatForCountry($this->phoneCountry->code),
        );
    }
}
