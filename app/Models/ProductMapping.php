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
        // Taxonomy: vending_machine / smart_freezer / smart_chiller (Vend::MACHINE_TYPE_*).
        // Kept in sync with is_smart by ProductMappingController (is_smart ⟺ smart_freezer);
        // the vend-side mapping restriction keys on this, not on the boolean.
        'machine_type',
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
        if (! $this->upcoming_product_mapping_start_date) {
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

    /**
     * Machines whose OWN upcoming product mapping points at THIS mapping —
     * i.e. still binded to another menu today but queued to switch onto this
     * one at their next changeover (vends.upcoming_product_mapping_id = this
     * mapping's id). Note vends.upcoming_product_mapping_id is cleared /
     * rolled forward once the machine actually switches (VendController
     * changeover), and an upcoming equal to the current mapping is coerced to
     * null on save — so this is strictly "pending", never "already on".
     *
     * NOTE (2026-07-30): this relation is NO LONGER the source of the
     * "N Machine(s) at upcoming stage" figure on the Product Mapping index.
     * vends.upcoming_product_mapping_id is only written by the vend-binding
     * paths, so a machine that simply inherits its changeover from its current
     * mapping's preset (product_mappings.upcoming_product_mapping_id) has NULL
     * here and was being missed. ProductMappingController::index() now counts
     * on the EFFECTIVE upcoming — coalesce(vend's own, current mapping's
     * preset) — matching OpsJobItem::resolveUpcomingMapping and the Ops Job /
     * CustomerIndex UIs. Kept because it is still a valid relation.
     */
    public function upcomingVends()
    {
        return $this->hasMany(Vend::class, 'upcoming_product_mapping_id');
    }

    public function vendPrefixes()
    {
        return $this->belongsToMany(VendPrefix::class);
    }
}
