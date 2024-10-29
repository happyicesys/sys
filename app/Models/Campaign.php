<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'remarks'
    ];

    // scopes
    public function scopeFilterIndex($query, $request)
    {

        $query = $query
            ->when($request->name, function($query, $search) {
                $query->where('name', 'LIKE', "%{$search}%");
            })
            ->when($request->operators, function($query, $search) {
                if($search != 'all') {
                    $query->whereIn('operators', $search);
                }
            });

        return $query;
    }
}
