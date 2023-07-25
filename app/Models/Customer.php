<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $casts = [
        'account_manager_json' => 'json',
        'cms_invoice_history' => 'json',
        'last_invoice_date' => 'datetime',
        'next_invoice_date' => 'datetime',
    ];

    protected $fillable = [
        'account_manager_json',
        'category_id',
        'cms_invoice_history',
        'code',
        'created_at',
        'first_transaction_id',
        'name',
        'is_active',
        'is_freezer',
        'last_invoice_date',
        'location_type_id',
        'next_invoice_date',
        'ops_note',
        'payment_term_id',
        'person_id',
        'profile_id',
        'remarks',
        'status_id',
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

    public function children()
    {
        return $this->hasMany(Customer::class, 'parent_id');
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

    public function locationType()
    {
        return $this->belongsTo(LocationType::class);
    }

    public function paymentTerm()
    {
        return $this->belongsTo(PaymentTerm::class);
    }

    public function parent()
    {
        return $this->belongsTo(Customer::class, 'parent_id');
    }

    public function priceTemplate()
    {
        return $this->belongsTo(PriceTemplate::class);
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

    public function vendBinding()
    {
        return $this->hasOne(VendBinding::class);
    }

    public function zone()
    {
        return $this->belongsTo(Zone::class);
    }
}
