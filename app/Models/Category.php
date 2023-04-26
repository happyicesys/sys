<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_group_id',
        'classname',
        'desc',
        'is_active',
        'name',
        'remarks',
        'sequence',
        'type',
    ];

    // relationships
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'modelable');
    }

    public function categoryGroup()
    {
        return $this->belongsTo(CategoryGroup::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    // scopes
    public function scopeFilterIndex($query, $request)
    {
        $sortKey = $request->sortKey ? $request->sortKey : 'name';
        $sortBy = $request->sortBy ? $request->sortBy : true;

        return $query->when($request->codes, function($query, $search) {
            if(strpos($search, ',') !== false) {
                $search = explode(',', $search);
            }else {
                $search = [$search];
            }
            $query->whereHas('customers.vendBinding.vend', function($query) use ($search) {
                $query->whereIn('code', $search);
            });
        })
        ->when($request->customer_code, function($query, $search) {
            $query->whereHas('customers', function($query) use ($search) {
                $query->where('code', 'LIKE', "%{$search}%");
            });
        })
        ->when($request->customer_name, function($query, $search) {
            $query->whereHas('customers', function($query) use ($search) {
                $query->where('name', 'LIKE', "%{$search}%");
            })
            ->orWhereHas('customers.vendBinding.vend', function($query) use ($search) {
                $query->where('name', 'LIKE', "%{$search}%");
            });
        })
        ->when($request->categories, function($query, $search) {
            $query->whereIn('categories.id', $search);
        })
        ->when($request->operator_id, function($query, $search) {
            if($search != 'all') {
                $query->whereHas('customers.vendBinding.vend.opeartors', function($query) use ($search) {
                    $query->where('operators.id', $search);
                });
            }
        })
        ->when($sortKey, function($query, $search) use ($sortBy) {
            if(strpos($search, '->')) {
                $inputSearch = explode("->", $search);
                $query->orderByRaw('LENGTH(json_unquote(json_extract(`'.$inputSearch[0].'`, "$.'.$inputSearch[1].'")))'.(filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'))
                ->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
            }else {
                $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
            }
        });
    }
}
