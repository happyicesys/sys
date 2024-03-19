<?php

namespace App\Traits;
use App\Models\VendTemp;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Facades\Http;

trait HasFilter {

    public function filterOperatorDB($query) {
        if(auth()->check()) {
            $operatorId = auth()->user()->operator_id;
            $isHappyIce = $operatorId == 1 ? true : false;
            if($isHappyIce) {
              $operatorId = null;
            }
            if($operatorId) {
                $query = $query->where('operators.id', $operatorId);
            }

            $vendIds = auth()->user()->vends()->exists() ? auth()->user()->vends->pluck('id')->toArray() : null;
            if($vendIds) {
                $query->whereIn('vends.id', $vendIds);
            }
        }
        return $query;
    }

    public function filterOperatorVendTransactionDB($query) {
        if(auth()->check()) {
            $operatorId = auth()->user()->operator_id;
            $isHappyIce = $operatorId == 1 ? true : false;
            if($isHappyIce) {
              $operatorId = null;
            }
            if($operatorId) {
                $query = $query->where('operators.id', $operatorId);
            }

            $vendIds = auth()->user()->vends()->exists() ? auth()->user()->vends->pluck('id')->toArray() : null;
            if($vendIds) {
                $query->whereIn('vends.id', $vendIds);
            }
        }
        return $query;
    }

    public function filterVendTransactionReport($query, $request)
    {
        $query->when($request->has('visited'), function($query, $search) use ($request) {
            if($request->visited == 'true') {
                $query->whereRaw('1 = 1');
            }else {
                $query->whereRaw('1 = 0');
            }
        })
        ->when($request->categories, function($query, $search) {
            $query->whereIn('categories.id', $search);
        })
        ->when($request->categoryGroups, function($query, $search) {
            $query->whereIn('category_groups.id', $search);
        })
        ->when($request->codes, function($query, $search) {
            if(strpos($search, ',') !== false) {
                $search = explode(',', $search);
                $query->whereIn('vends.code', $search);
            }else {
                $query->where('vends.code', 'LIKE', "%{$search}%");
            }
        })
        ->when($request->customer_code, function($query, $search) {
            $query->where('customers.code', 'LIKE', "%{$search}%");
        })
        ->when($request->customer_name, function($query, $search) {
            $query->where(function($query) use ($search) {
              $query->where('customers.name', 'LIKE', "%{$search}%")
                    ->orWhere('vends.name', 'LIKE', "%{$search}%");
            });
        })
        ->when($request->customer, function($query, $search) {
            if(strpos($search, "-")) {
                $searchArray = explode("-", $search);
                $query->where('customers.virtual_customer_prefix', $searchArray[0])
                    ->where('customers.virtual_customer_code', 'LIKE', "{$searchArray[1]}%");
            }else {
                $query->where(function($query) use ($search) {
                    $query->where('customers.virtual_customer_prefix', 'LIKE', "{$search}%")
                          ->orWhere('customers.virtual_customer_code', 'LIKE', "{$search}%")
                          ->orWhere('customers.name', 'LIKE', "%{$search}%")
                          ->orWhere('vends.name', 'LIKE', "%{$search}%");
                  });
            }
        })
        ->when($request->is_binded_customer, function($query, $search) {
            if($search != 'all') {
                if($search == 'true') {
                    $query->whereNotNull('customers.id');
                }else {
                    $query->whereNull('customers.id');
                }
            }
        })
        ->when($request->location_type_id, function($query, $search) {
            if($search != 'all') {
              $query->where('location_type_id', $search);
            }
        })
        ->when($request->operator_id, function($query, $search) {
            if($search != 'all') {
              $query->where('operators.id', $search);
            }
        })
        ->when($request->product_code, function($query, $search) {
            $query->where('products.code', 'LIKE', "%{$search}%");
        })
        ->when($request->product_name, function($query, $search) {
            $query->where('products.name', 'LIKE', "%{$search}%");
        });

        return $query;
    }

    public function filterVendsDB($query, $request)
    {
        // dd($request->all());
    // $isActive = $request->is_active != null ? $request->is_active : 'all';
      $isDoorOpen = $request->is_door_open != null ? $request->is_door_open : 'all';
      $isOnline = $request->is_online != null ? $request->is_online : 'all';
      $isSensor = $request->is_sensor != null ? $request->is_sensor : 'all';

      return $query->when($request->has('visited'), function($query, $search) use ($request) {
          if($request->visited == 'true') {
              $query->whereRaw('1 = 1');
          }else {
              $query->whereRaw('1 = 0');
          }
      })
      ->when($request->account_manager_name, function($query, $search) {
        $query->where('customers.account_manager_json->name', 'LIKE', "{$search}%");
      })
      ->when($request->codes, function($query, $search) {
        if(strpos($search, ',') !== false) {
            $search = explode(',', $search);
            $query->whereIn('vends.code', $search);
        }else {
            $query->where('vends.code', 'LIKE', "%{$search}%");
        }
      })
      ->when($request->channel_codes, function($query, $search) {
          if(strpos($search, ',') !== false) {
              $search = explode(',', $search);
          }else {
              $search = [$search];
          }

          $query->whereIn('vends.id', DB::table('vend_channels')->select('vend_id')->whereIn('code', $search)->where('vend_channels.is_active', true)->pluck('vend_id'));
      })
      ->when($request->serialNum, function($query, $search) {
          $query->where('serial_num', 'LIKE', "%{$search}%");
      })
    ->when($request->customer, function($query, $search) {
        if(strpos($search, "-")) {
            $searchArray = explode("-", $search);
            $query->where('customers.virtual_customer_prefix', $searchArray[0])
                ->where('customers.virtual_customer_code', 'LIKE', "{$searchArray[1]}%");
        }else {
            $query->where(function($query) use ($search) {
                $query->where('customers.virtual_customer_prefix', 'LIKE', "{$search}%")
                      ->orWhere('customers.virtual_customer_code', 'LIKE', "{$search}%")
                      ->orWhere('customers.name', 'LIKE', "%{$search}%");
              });
        }
    })
      ->when($request->product_code, function($query, $search) {
        $query->where('products.code', 'LIKE', "%{$search}%");
    })
    ->when($request->product_name, function($query, $search) {
        $query->where('products.name', 'LIKE', "%{$search}%");
    })
      ->when($request->categories, function($query, $search) {
          $query->whereIn('categories.id', $search);
      })
      ->when($request->categoryGroups, function($query, $search) {
          $query->whereIn('category_groups.id', $search);
      })
      ->when($request->fanSpeedLowerThan, function($query, $search) {
          if(is_numeric($search)) {
              $query->where('parameter_json->fan', '<=', $search)->where('parameter_json->fan', '>', 0);
          }
      })
      ->when($request->is_active, function($query, $search) {
        // dd($search);
        if($search != 'all') {
            $query->where('customers.is_active', filter_var($search, FILTER_VALIDATE_BOOLEAN));
        }
    })
    ->when($request->is_mqtt, function($query, $search) {
        if($search != 'all') {
            $query->where('vends.is_mqtt', filter_var($search, FILTER_VALIDATE_BOOLEAN));
        }
    })
    ->when($request->is_mqtt_active, function($query, $search) {
        if($search != 'all') {
            $query->where('vends.is_mqtt', true)->where('vends.is_mqtt_active', filter_var($search, FILTER_VALIDATE_BOOLEAN));
        }
    })
      ->when($isDoorOpen, function($query, $search) {
          if($search != 'all') {
              $query->where('parameter_json->door', '=', $search);
          }
      })
    //   ->when($request->is_binded_customer, function($query, $search) {
    //       if($search !== 'all') {
    //           if($search == 'true') {
    //             $query->whereNotNull('customer_id');
    //           }else {
    //             $query->whereNull('customer_id');
    //           }
    //       }
    //   })
      ->when($request->tempHigherThan, function($query, $search) {
          if(is_numeric($search)) {
              $query->where('temp', '>=', $search * 10);
          }
      })
      ->when($request->tempDeltaHigherThan, function($query, $search) {
          if(is_numeric($search)) {
              $query
                  ->whereNotNull('parameter_json->t2')
                  ->where('parameter_json->t2', '!=', VendTemp::TEMPERATURE_ERROR)
                  ->whereRaw('temp - json_extract(parameter_json, "$.t2") > ?', [$search * 10]);
          }
      })
      ->when($request->errors, function($query, $search) {
          if(in_array('errors_only', $search)) {
            $query->whereIn('vends.id',
                DB::table('vend_channels')
                ->select('vend_id')
                ->where('vend_channels.is_active', true)
                ->whereIn('vend_channels.id', DB::table('vend_channel_error_logs')
                    ->select('vend_channel_id')
                    ->where('is_error_cleared', false)
                    ->pluck('vend_channel_id'))
                ->pluck('vend_id')
            );
          }else {
            $query->whereIn('vends.id',
                DB::table('vend_channels')
                ->select('vend_id')
                ->where('vend_channels.is_active', true)
                ->whereIn('vend_channels.id', DB::table('vend_channel_error_logs')
                    ->leftJoin('vend_channel_errors', 'vend_channel_errors.id', '=', 'vend_channel_error_logs.vend_channel_error_id')
                    ->select('vend_channel_id')
                    ->where('is_error_cleared', false)
                    ->whereIn('vend_channel_errors.id', $search)
                    ->pluck('vend_channel_id'))
                ->pluck('vend_id')
            );
          }
      })
      ->when($request->location_type_id, function($query, $search) {
          if($search != 'all') {
            $query->where('location_type_id', $search);
          }
      })
      ->when($request->operator_id, function($query, $search) {
          if($search != 'all') {
            $query->whereIn('vends.id',
                DB::table('vends')
                    ->leftJoin('customers', 'customers.id', '=', 'vends.customer_id')
                    ->select('vends.id')
                    ->where('customers.operator_id', $search)
                    ->pluck('vends.id')
            );
          }
      })
      ->when($isOnline, function($query, $search) {
          if($search != 'all') {
              if($search == 'true') {
                  $search = true;
              }else {
                  $search = false;
              }
              $query->where('is_online', $search);
          }
      })
      ->when($isSensor, function($query, $search) {
          if($search != 'all') {
              if($search == 'true') {
                  $query->whereIn('parameter_json->Sensor', ['1', '3', '5', '7', '9']);
              }else {
                  $query->whereIn('parameter_json->Sensor', ['0', '2', '4', '6', '8', '10']);
              }
          }
      })
      ->when($request->lastVisitedGreaterThan, function($query, $search) {
          $query->whereDate('customers.last_invoice_date', '<=', Carbon::now()->subDays($search)->toDateString());
      })
      ->when($request->balanceStockLessThan, function($query, $search) {
          $query->where('balance_percent', '<=', $search);
      })
      ->when($request->remainingSkuLessThan, function($query, $search) {
          $query->where('out_of_stock_sku_percent', '>=', (100 - $search));
      })
        ->when($request->virtual_apk_ver, function($query, $search) {
            $query->where('virtual_apk_ver', 'LIKE', "%{$search}%");
        })
        ->when($request->virtual_firmware_ver, function($query, $search) {
            $query->where('virtual_firmware_ver', 'LIKE', "%{$search}%");
        })
        ->when($request->vendRecordsThirtyDaysAmountAverageLessThan, function($query, $search) {
            $query->where('virtual_vend_records_thirty_days_amount_average', '<=', $search*100);
        })
      ->when($request->sortKey, function($query, $search) use ($request) {
            // if($search === 'balance_percent') {
            //     $query->orderBy('is_online', 'desc');
            // }

          if(strpos($search, '->')) {
              $inputSearch = explode("->", $search);
              $query->orderByRaw('LENGTH(json_unquote(json_extract(`'.$inputSearch[0].'`, "$.'.$inputSearch[1].'")))'.(filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'))
              ->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
          }else {
            if($search == 'balance_percent' or $search == 'out_of_stock_sku_percent') {
                $query->orderByRaw('ISNULL('.$search.'), '.$search.' '.(filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'));
            }else {
                $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
            }
          }

          if($search === 'vends.is_online') {
            $query->orderBy('vends.code', 'asc');
          }
      });

      return $query;
    }

    public function filterVendChannelsDB($query, $request)
    {
        $isDoorOpen = $request->is_door_open != null ? $request->is_door_open : 'all';
        $isOnline = $request->is_online != null ? $request->is_online : 'all';
        $isSensor = $request->is_sensor != null ? $request->is_sensor : 'all';
        $isBindedCustomer = $request->is_binded_customer != null ? $request->is_binded_customer : 'true';
        $isBindedCustomer = auth()->user()->hasRole('operator') ? 'all' : $isBindedCustomer;
        $sortKey = $request->sortKey ? $request->sortKey : 'vends.is_online';
        $sortBy = $request->sortBy ? $request->sortBy : false;

        $query = $query->when($request->codes, function($query, $search) {
            if(strpos($search, ',') !== false) {
                $search = explode(',', $search);
                $query->whereIn('vends.code', $search);
            }else {
                $query->where('vends.code', 'LIKE', "%{$search}%");
            }
        })
        ->when($request->channel_codes, function($query, $search) {
            if(strpos($search, ',') !== false) {
                $search = explode(',', $search);
            }else {
                $search = [$search];
            }
            $query->whereIn('vend_channels.code', $search);
        })
        ->when($request->serialNum, function($query, $search) {
            $query->where('vends.serial_num', 'LIKE', "%{$search}%");
        })
        ->when($request->customer_code, function($query, $search) {
            $query->where('customers.code', 'LIKE', "%{$search}%");
        })
        ->when($request->customer_name, function($query, $search) {
            $query->where(function($query) use ($search) {
              $query->where('customers.name', 'LIKE', "%{$search}%")
                    ->orWhere('vends.name', 'LIKE', "%{$search}%");
            });
        })
        ->when($request->categories, function($query, $search) {
            $query->whereIn('categories.id', $search);
        })
        ->when($request->categoryGroups, function($query, $search) {
            $query->whereIn('category_groups.id', $search);
        })
        ->when($request->fanSpeedLowerThan, function($query, $search) {
            if(is_numeric($search)) {
                $query->where('parameter_json->fan', '<=', $search)->where('parameter_json->fan', '>', 0);
            }
        })
        ->when($request->is_active, function($query, $search) use ($request) {
            if($search != 'all') {
                $query->where('customers.is_active', filter_var($search, FILTER_VALIDATE_BOOLEAN));
            }
        })
        ->when($isDoorOpen, function($query, $search) {
            if($search != 'all') {
                $query->where('parameter_json->door', '=', $search);
            }
        })
        ->when($request->is_binded_customer, function($query, $search) {
            if($search != 'all') {
                if($search == 'true') {
                    $query->whereNotNull('customers.id');
                }else {
                    $query->whereNull('customers.id');
                }
            }
        })
        ->when($request->tempHigherThan, function($query, $search) {
            if(is_numeric($search)) {
                $query->where('temp', '>=', $search * 10);
            }
        })
        ->when($request->tempDeltaHigherThan, function($query, $search) {
            if(is_numeric($search)) {
                $query
                    ->whereNotNull('parameter_json->t2')
                    ->where('parameter_json->t2', '!=', VendTemp::TEMPERATURE_ERROR)
                    ->whereRaw('temp - json_extract(parameter_json, "$.t2") > ?', [$search * 10]);
            }
        })
        ->when($request->errors, function($query, $search) {
            if(in_array('errors_only', $search)) {
              $query
              ->whereIn('vends.id', DB::table('vend_channels')
                ->select('vend_id')
                ->whereIn('id', DB::table('vend_channel_error_logs')
                  ->select('vend_channel_id')
                  ->where('is_error_cleared', false)
                  ->pluck('vend_channel_id')));
            }else {
              $query
              ->whereIn('vends.id', DB::table('vend_channels')
                ->select('vend_id')
                ->whereIn('id', DB::table('vend_channel_error_logs')
                  ->select('vend_channel_id')
                  ->whereIn('vend_channel_error_id', $search)
                  ->where('is_error_cleared', false)
                  ->pluck('vend_channel_id')));
            }
        })
        ->when($request->location_type_id, function($query, $search) {
            if($search != 'all') {
              $query->where('location_type_id', $search);
            }
        })
        ->when($request->operator_id, function($query, $search) {
            if($search != 'all') {
                $query->whereIn('vends.id',
                    DB::table('vends')
                    ->leftJoin('customers', 'customers.id', '=', 'vends.customer_id')
                    ->select('vends.id')
                    ->where('customers.operator_id', $search)
                    ->pluck('vends.id'));
            }
        })
        ->when($isOnline, function($query, $search) {
            if($search != 'all') {
                if($search == 'true') {
                    $search = true;
                }else {
                    $search = false;
                }
                $query->where('is_online', $search);
            }
        })
        ->when($isSensor, function($query, $search) {
            if($search != 'all') {
                if($search == 'true') {
                    $query->whereIn('parameter_json->Sensor', ['1', '3', '5', '7', '9']);
                }else {
                    $query->whereIn('parameter_json->Sensor', ['0', '2', '4', '6', '8', '10']);
                }
            }
        })
        ->when($request->lastVisitedGreaterThan, function($query, $search) {
            $query->whereDate('customers.last_invoice_date', '<=', Carbon::now()->subDays($search)->toDateString());
        })
        ->when($request->sortKey, function($query, $search) use ($request) {
            if(strpos($search, '->')) {
                $inputSearch = explode("->", $search);
                $query->orderByRaw('LENGTH(json_unquote(json_extract(`'.$inputSearch[0].'`, "$.'.$inputSearch[1].'")))'.(filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'))
                ->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
            }else {
                $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
            }

            if($search === 'vends.is_online') {
                $query->orderBy('vends.code', 'asc');
            }
        });

        return $query;
    }

    public function filterVendTransactionsDB($query, $request)
    {
        $sortKey = $request->sortKey ? $request->sortKey : 'vend_transactions.created_at';
        $sortBy = $request->sortBy ? $request->sortBy : false;
        $isPaymentReceived = $request->is_payment_received != null ? $request->is_payment_received : 'all';

        $query = $query->when($request->has('visited'), function($query, $search) use ($request) {
            if($request->visited == 'true') {
                $query->whereRaw('1 = 1');
            }else {
                $query->whereRaw('1 = 0');
            }
        })
        ->when($request->codes, function($query, $search) {
            if(strpos($search, ',') !== false) {
                $search = explode(',', $search);
                $query->whereIn('vends.code', $search);
            }else {
                $query->where('vends.code', 'LIKE', "%{$search}%");
            }
        })
        ->when($request->channel_codes, function($query, $search) {
            if(strpos($search, ',') !== false) {
                $search = explode(',', $search);
            }else {
                $search = [$search];
            }
            $query->whereIn('vend_channel_code', $search);
        })
        ->when($request->errors, function($query, $search) {
            if(in_array('errors_only', $search)) {
              $query
              ->whereIn('vend_channel_errors.id', DB::table('vend_channel_error_logs')
                ->select('vend_channel_error_id')
                ->where('is_error_cleared', false)
                ->pluck('vend_channel_error_id'));
            }else {
              $query
              ->whereIn('vend_channel_errors.id', $search);
            }
        })
        ->when($request->is_binded_customer, function($query, $search) {
            if($search != 'all') {
                if($search == 'true') {
                    $query->whereNotNull('customers.id');
                }else {
                    $query->whereNull('customers.id');
                }
            }
        })
        ->when($isPaymentReceived, function($query, $search) {
            if($search != 'all') {
                if($search == 'true') {
                    $query->where('is_payment_received', true);
                }else {
                    $query->where('is_payment_received', false);
                }
            }
        })
        ->when($request->paymentMethod, function($query, $search) {
            $query->where('payment_method_id', $search);
        })
        ->when($request->categories, function($query, $search) {
            $query->whereIn('categories.id', $search);
        })
        ->when($request->categoryGroups, function($query, $search) {
            $query->whereIn('category_groups.id', $search);
        })
        ->when($request->customer_code, function($query, $search) {
            $query->where('customers.code', 'LIKE', "%{$search}%");
        })
        ->when($request->customer_name, function($query, $search) {
            $query->where(function($query) use ($search) {
              $query->where('customers.name', 'LIKE', "%{$search}%")
                    ->orWhere('vends.name', 'LIKE', "%{$search}%");
            });
        })
        ->when($request->product_code, function($query, $search) {
            $query->where('products.code', 'LIKE', "%{$search}%");
        })
        ->when($request->product_name, function($query, $search) {
            $query->where('products.name', 'LIKE', "%{$search}%");
        })
        ->when($request->location_type_id, function($query, $search) {
            if($search != 'all') {
              $query->where('location_type_id', $search);
            }
        })
        ->when($request->operator_id, function($query, $search) {
            if($search != 'all') {
              $query->where('operators.id', $search);
            }
        })
        ->when($request->date_from, function($query, $search) {
            $query->where('vend_transactions.created_at', '>=', Carbon::parse($search)->startOfDay());
        })
        ->when($request->date_to, function($query, $search) {
            $query->where('vend_transactions.created_at', '<=', Carbon::parse($search)->endOfDay());
        })
        ->when($sortKey, function($query, $search) use ($sortBy) {
            $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc' );
        });


        return $query;
    }

}