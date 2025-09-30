<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use App\Models\Operator;

class Campaign extends Model
{
    use HasFactory;

    const TYPE_BUY_ONE_FREE_ONE = 1;
    const TYPE_BUY_TWO_FREE_ONE = 2;
    const TYPE_BUNDLE = 3;

    protected $fillable = [
        'is_active',
        'name',
        'operator_id',
        'remarks',
        'uuid',
        'slug',
        'description',
        'start_at',
        'end_at',
    ];

    protected $casts = [
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
}
