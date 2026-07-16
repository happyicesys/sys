<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VendSerialNumber extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'desc',
    ];

    public function vend()
    {
        return $this->hasOne(Vend::class);
    }

    public function scopeFilterIndex($query, $request)
    {
        return $query->when($request->id, function($query, $search) {
            $query->where('id', $search);
        })
        ->when($request->lcd_monitor_id, function($query, $search) {
            if($search != 'all') {
                if($search == 'undefined') {
                    $query->whereNull('vends.lcd_monitor_id');
                }else {
                    $query->where('vends.lcd_monitor_id', $search);
                }
            }
        })
        ->when($request->code, function($query, $search) {
            $query->where('vend_serial_numbers.code', 'LIKE', "%{$search}%");
        })
        ->when($request->vend_codes, function($query, $search) {
            if(strpos($search, ',') !== false) {
                $search = explode(',', $search);
                $query->whereIn('vends.code', $search);
            }else {
                $query->where('vends.code', 'LIKE', "%{$search}%");
            }
        })
        ->when($request->vendModels, function($query, $search) {
            if(!in_array('all', $search)){
                $query->whereIn('vends.vend_model_id', $search);
            }
        })
        ->when($request->vendConfigs, function($query, $search) {
            if(!in_array('all', $search)){
                $query->whereIn('vends.vend_config_id', $search);
            }
        })
        ->when($request->vendContracts, function($query, $search) {
            if(!in_array('all', $search)){
                $query->whereIn('vends.vend_contract_id', $search);
            }
        })
        ->when($request->vendPrefixes, function($query, $search) {
            if(!in_array('all', $search)){
                $query->whereIn('vends.vend_prefix_id', $search);
            }
        })
        ->when($request->stickers, function($query, $search) {
            if(!in_array('all', $search)){
                $query->whereHas('vend.stickers', function($q) use ($search) {
                    $q->whereIn('vend_stickers.id', $search);
                });
            }
        })
        ->when($request->status, function($query, $search) {
            if($search != 'all') {
                if($search === 'active') {
                    $query->where('vends.is_active', true);
                }else if($search === 'factory') {
                    $query->where('vends.is_testing', true);
                }else if($search === 'disposed') {
                    $query->where('vends.is_disposed', true);
                }else if($search === 'sold') {
                    $query->where('vends.is_sold', true);
                }else {
                    $query->where('vends.is_active', false)
                        ->where('vends.is_testing', false)
                        ->where('vends.is_disposed', false)
                        ->where('vends.is_sold', false);
                }
            }
        })
        ->when($request->customer, function($query, $search) {
            $query->where('customers.name', 'LIKE', "%{$search}%");
        })
        ->when($request->locationTypes, function($query, $search) {
            if(!in_array('all', $search)){
                $query->whereIn('customers.location_type_id', $search);
            }
        })
        ->when($request->operators, function($query, $search) {
            if(!in_array('all', $search)){
                $query->whereIn('customers.operator_id', $search);
            }
        })
        ->when($request->sortKey, function($query, $search) use ($request) {
            $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
        });
    }
}
