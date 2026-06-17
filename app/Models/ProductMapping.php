<?php

namespace App\Models;

use App\Models\Scopes\OperatorProductMappingScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductMapping extends Model
{
    use HasFactory;

    // const ATTACHMENT_PRICE_TYPE = [
    //     11 => 'P1',
    //     12 => 'P2',
    //     13 => 'P3',
    //     14 => 'P4',
    //     15 => 'P5',
    // ];

    protected static function booted()
    {
        static::addGlobalScope(new OperatorProductMappingScope);
    }

    protected $casts = [
        // 'product_mapping_items_json' => 'json',
        // Smart-freezer planogram flag + per-basket layout. See migration
        // 2026_06_14 for the JSON shape. is_smart is the single switch the
        // ProductMapping/Edit UI branches on (basket grid vs channel-row table).
        'is_smart' => 'boolean',
        'basket_layout_json' => 'array',
        // Date the bound upcoming product mapping is scheduled to take over.
        'upcoming_product_mapping_start_date' => 'date',
    ];

    protected $fillable = [
        'name',
        'remarks',
        'is_active',
        'operator_id',
        // 'product_mapping_items_json',
        'selling_price_type',
        'upcoming_product_mapping_id',
        'upcoming_product_mapping_start_date',
        'is_smart',
        'basket_layout_json',
    ];


    /**
     * Whether this mapping's bound upcoming product mapping has taken effect.
     *
     * The "start date" is declared on the current mapping (this row) and gates
     * when the upcoming mapping becomes active in ops jobs (display + the
     * "implement new mapping" stock action). Rule:
     *   - no start date declared  => always effective (rule ignored)
     *   - start date declared      => effective on/after that calendar date
     *
     * Compared at day granularity in the app timezone (one instance per
     * country, so app TZ == operator TZ).
     */
    public function isUpcomingMappingEffective($asOf = null): bool
    {
        if (!$this->upcoming_product_mapping_start_date) {
            return true;
        }

        $asOf = $asOf ? \Illuminate\Support\Carbon::parse($asOf) : now();

        return $asOf->copy()->startOfDay()
            ->gte($this->upcoming_product_mapping_start_date->copy()->startOfDay());
    }

    // relationships
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'modelable')->oldest();
    }

    // public function attachmentP1()
    // {
    //     return $this->morphMany(Attachment::class, 'modelable')
    //             ->where('type', )
    //             ->orderBy('sequence');
    // }

    public function currentProductMappings()
    {
        return $this->belongsToMany(ProductMapping::class, 'product_mapping_product_mapping', 'upcoming_product_mapping_id', 'product_mapping_id')->orderBy('name');
    }

    public function upcomingProductMappings()
    {
        return $this->belongsToMany(ProductMapping::class, 'product_mapping_product_mapping', 'product_mapping_id', 'upcoming_product_mapping_id')->orderBy('name');
    }

    public function upcomingProductMapping()
    {
        return $this->belongsTo(ProductMapping::class, 'upcoming_product_mapping_id');
    }

    public function productMappingItems()
    {
        return $this->hasMany(ProductMappingItem::class)->orderBy('channel_code', 'asc');
    }

    public function productMappingItemsNormalSequence()
    {
        return $this->hasMany(ProductMappingItem::class);
    }

    public function productMappingItemsBySequence()
    {
        return $this->hasMany(ProductMappingItem::class)
                    ->orderByRaw('sequence IS NULL')
                    ->orderBy('sequence')
                    ->orderBy('channel_code');
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function vends()
    {
        return $this->hasMany(Vend::class)->orderBy('code');
    }

    public function vendPrefixes()
    {
        return $this->belongsToMany(VendPrefix::class);
    }
}
