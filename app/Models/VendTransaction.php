<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendTransaction extends Model
{
    use HasFactory;

    protected $casts = [
        'transaction_datetime' => 'datetime'
    ];

    protected $fillable = [
        'order_id',
        'transaction_datetime',
        'amount',
        'payment_method_id',
        'vend_channel_id',
        'vend_channel_error_id',
        'vend_id'
    ];

    // relationships
    public function paymentMethod()
    {
        return $this->belongsTo(PaymentMethod::class);
    }

    public function vend()
    {
        return $this->belongsTo(Vend::class);
    }

    public function vendChannel()
    {
        return $this->belongsTo(VendChannel::class);
    }

    public function vendChannelError()
    {
        return $this->belongsTo(VendChannelError::class);
    }

    // scopes
    public function scopeFilterTransactionIndex($query, $request)
    {
        $sortKey = $request->sortKey ? $request->sortKey : 'transaction_datetime';
        $sortBy = $request->sortBy ? $request->sortBy : false;
        $startDate =  $request->date_from ?? Carbon::today()->startOfMonth()->toDateString();
        $endDate =  $request->date_to ?? Carbon::today()->toDateString();
        // return
        $query = $query->when($request->codes, function($query, $search) {
            $query->whereHas('vend', function($query) use ($search) {
                $query->whereIn('id', $search);
            });
        })
        ->when($request->errors, function($query, $search) {
            $query->whereHas('vendChannel.vendChannelErrorLogs', function($query) use ($search) {
                $query->where('vend_channel_error_id', 'IN', $search)->where('is_error_cleared', false);
            });
        })
        ->when($request->paymentMethod, function($query, $search) {
            $query->where('payment_method_id', $search);
        })
        ->when($request->categories, function($query, $search) {
            $query->whereHas('vend.latestVendBinding.customer.category', function($query) use ($search) {
                $query->whereIn('id', $search);
            });
        })
        ->when($request->categoryGroups, function($query, $search) {
            $query->whereHas('vend.latestVendBinding.customer.category.categoryGroup', function($query) use ($search) {
                $query->whereIn('id', $search);
            });
        })
        ->when($startDate, function($query, $search) {
            $query->whereDate('transaction_datetime', '>=', $search);
        })
        ->when($endDate, function($query, $search) {
            $query->whereDate('transaction_datetime', '<=', $search);
        })
        ->when($sortKey, function($query, $search) use ($sortBy) {
            $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
        });
        return $query;
    }
}
