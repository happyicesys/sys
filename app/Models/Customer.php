<?php

namespace App\Models;

use App\Models\Scopes\OperatorCustomerFilterScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    const STATUS_NEW = 4;
    const STATUS_PENDING = 3;
    const STATUS_ACTIVE = 2;
    const STATUS_INACTIVE = 1;

    const STATUSES_MAPPING = [
        self::STATUS_NEW => 'New',
        self::STATUS_PENDING => 'Pending',
        self::STATUS_ACTIVE => 'Active',
        self::STATUS_INACTIVE => 'Inactive',
    ];

    protected static function booted()
    {
        static::addGlobalScope(new OperatorCustomerFilterScope);
    }

    protected $casts = [
        'account_manager_json' => 'json',
        'cms_invoice_history' => 'json',
        'person_json' => 'json',
        'last_invoice_date' => 'datetime',
        'next_invoice_date' => 'datetime',
    ];

    protected $fillable = [
        'account_manager_json',
        'category_id',
        'cms_invoice_history',
        'code',
        'created_at',
        'person_json',
        'first_transaction_id',
        'name',
        'is_active',
        'last_invoice_date',
        'location_type_id',
        'next_invoice_date',
        'operator_id',
        // for cms person id
        'person_id',
        'profile_id',
        'status_id',
        'virtual_customer_code',
        'virtual_customer_prefix',
        'zone_id',
    ];

    // relationships
    public function addresses()
    {
        return $this->morphMany(Address::class, 'modelable');
    }

    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'modelable')->orderBy('sequence');
    }

    public function billingAddress()
    {
        return $this->morphOne(Address::class, 'modelable')->ofMany('type', 'min');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function contact()
    {
        return $this->morphOne(Contact::class, 'modelable');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deliveryAddress()
    {
        return $this->morphOne(Address::class, 'modelable')->ofMany('type', 'max');
    }

    public function handledBy()
    {
        return $this->belongsTo(User::class, 'handled_by');
    }

    public function firstTransaction()
    {
        return $this->belongsTo(Transaction::class, 'first_transaction_id');
    }

    public function latestVendBinding()
    {
        return $this->hasOne(VendBinding::class)->where('is_active', true)->latest('begin_date');
    }

    public function locationType()
    {
        return $this->belongsTo(LocationType::class);
    }

    public function operator()
    {
        return $this->belongsTo(Operator::class);
    }

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    public function status()
    {
        return $this->belongsTo(Status::class);
    }

    public function tagBindings()
    {
        return $this->hasMany(TagBinding::class);
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function vendBindings()
    {
        return $this->hasMany(VendBinding::class)->latest('begin_date');
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }
}
