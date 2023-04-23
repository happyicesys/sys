<?php

namespace App\Models;
use App\Models\Scopes\OperatorProductFilterScope;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected static function booted()
    {
        static::addGlobalScope(new OperatorProductFilterScope);
    }

    protected $fillable = [
        'code',
        'name',
        'remarks',
        'desc',
        'is_active',
        'is_commission',
        'is_inventory',
        'is_supermarket_fee',
        'category_id',
        'category_group_id',
        'operator_id',
    ];

    // relationships
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'modelable')->orderBy('sequence');
    }

    // public function productImages()
    // {
    //     $this->whereHasMorph('attachments', )
    // }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function categoryGroup()
    {
        return $this->belongsTo(CategoryGroup::class);
    }

    public function latestUnitCost()
    {
        return $this->hasOne(UnitCost::class)->where('is_current', true)->orderBy('date_from', 'desc')->orderBy('created_at', 'desc');
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function productUoms()
    {
        return $this->hasMany(ProductUom::class, 'product_id')->orderBy('value');
    }

    public function thumbnail()
    {
        return $this->morphOne(Attachment::class, 'modelable')->ofMany('type', 'min');
    }

    public function unitCosts()
    {
        return $this->hasMany(UnitCost::class)->orderBy('is_current', 'desc')->orderBy('date_from', 'desc')->orderBy('created_at', 'desc');
    }

    public function vendTransactions()
    {
        return $this->hasMany(VendTransaction::class);
    }

    // mutators
    protected function isInventory(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value,
            set: fn ($value) => $value ? true : false,
        );
    }

    protected function isCommission(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value,
            set: fn ($value) => $value ? true : false,
        );
    }

    protected function isSupermarketFee(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value,
            set: fn ($value) => $value ? true : false,
        );
    }
}
