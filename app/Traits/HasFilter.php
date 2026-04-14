<?php

namespace App\Traits;
use App\Models\Vend;
use App\Models\Customer;
use App\Models\VendTemp;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;

trait HasFilter
{

    public function filterUserHasOperator($query)
    {
        if (auth()->check()) {
            $operatorId = auth()->user()->operator_id;
            $isHappyIce = $operatorId == 1 ? true : false;
            if ($isHappyIce) {
                $operatorId = null;
            }
            if ($operatorId) {
                $query = $query->whereHas('operator', function ($query) use ($operatorId) {
                    $query->where('id', $operatorId);
                });
            }
        }
        return $query;
    }

    public function filterOperator($query)
    {
        if (auth()->check()) {
            $operatorId = auth()->user()->operator_id;
            $isHappyIce = $operatorId == 1 ? true : false;
            if ($isHappyIce) {
                $operatorId = null;
            }
            if ($operatorId) {
                $query = $query->whereHas('operator', function ($query) use ($operatorId) {
                    $query->where('id', $operatorId);
                });
            }

            $vendIds = auth()->user()->vends ? auth()->user()->vends->pluck('id')->toArray() : null;
            if ($vendIds != null) {
                $query->whereIn('vends.id', $vendIds);
            }
        }
        return $query;
    }

    public function filterOperatorDB($query, $model = 'vends')
    {
        $columnName = $model . '.operator_id';

        if (auth()->check()) {
            $operatorId = auth()->user()->operator_id;
            $isHappyIce = $operatorId == 1 ? true : false;
            if ($isHappyIce) {
                $operatorId = null;
            }
            if ($operatorId) {
                $query = $query->where($columnName, $operatorId);
            }

            $vendIds = auth()->user()->vends?->pluck('id')->toArray();
            if ($vendIds != null) {
                $query->whereIn('vends.id', $vendIds);
            }
        }
        return $query;
    }

    public function filterOperatorVendTransactionDB($query)
    {
        if (auth()->check()) {
            $operatorId = auth()->user()->operator_id;
            $isHappyIce = $operatorId == 1 ? true : false;
            if ($isHappyIce) {
                $operatorId = null;
            }
            if ($operatorId) {
                $query = $query->where('operators.id', $operatorId);
            }

            $vendIds = auth()->user()->vends()->exists() ? auth()->user()->vends->pluck('id')->toArray() : null;
            if ($vendIds) {
                $query->whereIn('vends.id', $vendIds);
            }
        }
        return $query;
    }

    public function filterVendTransactionReport($query, $request)
    {
        $query->when($request->has('visited'), function ($query, $search) use ($request) {
            if ($request->visited == 'true') {
                $query->whereRaw('1 = 1');
            } else {
                $query->whereRaw('1 = 0');
            }
        })
            ->when($request->categories, function ($query, $search) {
                $query->whereIn('categories.id', $search);
            })
            ->when($request->categoryGroups, function ($query, $search) {
                $query->whereIn('category_groups.id', $search);
            })
            ->when($request->codes, function ($query, $search) {
                if (strpos($search, ',') !== false) {
                    $search = array_map('trim', explode(',', $search));
                    $query->whereIn('vends.code', $search);
                } else {
                    $query->where('vends.code', 'LIKE', "{$search}%");
                }
            })
            ->when($request->customer_code, function ($query, $search) {
                $query->where('customers.code', 'LIKE', "%{$search}%");
            })
            ->when($request->customer_name, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('customers.name', 'LIKE', "%{$search}%")
                        ->orWhere('vends.name', 'LIKE', "%{$search}%");
                });
            })
            ->when($request->customer, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('customers.virtual_customer_code', 'LIKE', "{$search}%")
                        ->orWhere('vend_prefixes.name', 'LIKE', "{$search}%")
                        ->orWhere('customers.name', 'LIKE', "%{$search}%");
                });
            })
            ->when($request->is_binded_customer, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'true') {
                        $query->whereNotNull('customers.id');
                    } else {
                        $query->whereNull('customers.id');
                    }
                }
            })
            ->when($request->location_type_id, function ($query, $search) {
                if ($search != 'all') {
                    $query->where('vend_transactions.location_type_id', $search);
                }
            })
            ->when($request->operators, function ($query, $search) {
                if (!in_array('all', $search)) {
                    $query->whereIn('vend_transactions.operator_id', $search);
                }
            })
            ->when($request->product_code, function ($query, $search) {
                $query->where('products.code', 'LIKE', "%{$search}%");
            })
            ->when($request->product_name, function ($query, $search) {
                $query->where('products.name', 'LIKE', "%{$search}%");
            })
            ->when($request->vendContracts, function ($query, $search) {
                if (!in_array('all', $search)) {
                    $query->whereIn('vend_transactions.vend_contract_id', $search);
                }
            })
            ->when($request->vendModels, function ($query, $search) {
                if (!in_array('all', $search)) {
                    $query->whereIn('vend_transactions.vend_model_id', $search);
                }
            })
            ->when($request->vendPrefixes, function ($query, $search) {
                if (!in_array('all', $search)) {
                    if (in_array('single-ud', $search)) {
                        $search = array_unique(array_merge($search, [56, 57, 58, 60, 63, 64, 76, 83]));
                        unset($search[array_search('single-ud', $search)]);
                    }
                    $query->whereIn('vend_transactions.vend_prefix_id', $search);
                }
            });

        return $query;
    }

    public function filterGpMetricsReport($query, $request, string $locationTypeColumn = 'gm.transaction_location_type_id')
    {
        $query->when($request->has('visited'), function ($query) use ($request) {
            if ($request->visited == 'true') {
                $query->whereRaw('1 = 1');
            } else {
                $query->whereRaw('1 = 0');
            }
        })
            ->when($request->categories, function ($query, $search) {
                $query->whereIn('gm.category_id', $search);
            })
            ->when($request->categoryGroups, function ($query, $search) {
                $query->whereIn('gm.category_group_id', $search);
            })
            ->when($request->codes, function ($query, $search) {
                if (strpos($search, ',') !== false) {
                    $codes = array_filter(array_map('trim', explode(',', $search)));
                    if (!empty($codes)) {
                        $query->whereIn('vends.code', $codes);
                    }
                } else {
                    $query->where('vends.code', 'LIKE', "{$search}%");
                }
            })
            ->when($request->customer_code, function ($query, $search) {
                $query->where('customers.code', 'LIKE', "%{$search}%");
            })
            ->when($request->customer_name, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('customers.name', 'LIKE', "%{$search}%")
                        ->orWhere('vends.name', 'LIKE', "%{$search}%");
                });
            })
            ->when($request->customer, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('customers.virtual_customer_code', 'LIKE', "{$search}%")
                        ->orWhere('vend_prefixes.name', 'LIKE', "{$search}%")
                        ->orWhere('customers.name', 'LIKE', "%{$search}%");
                });
            })
            ->when($request->is_binded_customer, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'true') {
                        $query->where('gm.is_binded_customer', true);
                    } else {
                        $query->where('gm.is_binded_customer', false);
                    }
                }
            })
            ->when($request->location_type_id, function ($query, $search) use ($locationTypeColumn) {
                if ($search != 'all') {
                    $query->where($locationTypeColumn, $search);
                }
            })
            ->when($request->operators, function ($query, $search) {
                if (is_array($search) && !in_array('all', $search, true)) {
                    $query->whereIn('gm.operator_id', $search);
                }
            })
            ->when($request->product_code, function ($query, $search) {
                $query->where('products.code', 'LIKE', "%{$search}%");
            })
            ->when($request->product_name, function ($query, $search) {
                $query->where('products.name', 'LIKE', "%{$search}%");
            })
            ->when($request->vendContracts, function ($query, $search) {
                if (is_array($search) && !in_array('all', $search, true)) {
                    $query->whereIn('gm.vend_contract_id', $search);
                }
            })
            ->when($request->vendModels, function ($query, $search) {
                if (is_array($search) && !in_array('all', $search, true)) {
                    $query->whereIn('gm.vend_model_id', $search);
                }
            })
            ->when($request->vendPrefixes, function ($query, $search) {
                if (is_array($search) && !in_array('all', $search, true)) {
                    if (in_array('single-ud', $search, true)) {
                        $search = array_unique(array_merge($search, [56, 57, 58, 60, 63, 64, 76, 83]));
                        $index = array_search('single-ud', $search, true);
                        if ($index !== false) {
                            unset($search[$index]);
                        }
                    }
                    $query->whereIn('gm.vend_prefix_id', $search);
                }
            });

        return $query;
    }

    public function filterVendRecordsReport($query, $request)
    {
        return $query
            ->when($request->has('visited'), function ($query) use ($request) {
                if ($request->visited === 'true') {
                    $query->whereRaw('1 = 1');
                } else {
                    $query->whereRaw('1 = 0');
                }
            })
            ->when($request->categories, function ($query, $search) {
                $ids = is_array($search) ? $search : [$search];
                $ids = array_filter($ids);
                if (!empty($ids)) {
                    $query->whereIn('customers.category_id', $ids);
                }
            })
            ->when($request->categoryGroups, function ($query, $search) {
                $ids = is_array($search) ? $search : [$search];
                $ids = array_filter($ids);
                if (!empty($ids)) {
                    $query->whereIn('categories.category_group_id', $ids);
                }
            })
            ->when($request->codes, function ($query, $search) {
                if (strpos($search, ',') !== false) {
                    $codes = array_filter(array_map('trim', explode(',', $search)));
                    if (!empty($codes)) {
                        $query->whereIn('vends.code', $codes);
                    }
                } else {
                    $query->where('vends.code', 'LIKE', "{$search}%");
                }
            })
            ->when($request->customer_code, function ($query, $search) {
                $query->where('customers.code', 'LIKE', "%{$search}%");
            })
            ->when($request->customer_name, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('customers.name', 'LIKE', "%{$search}%")
                        ->orWhere('vends.name', 'LIKE', "%{$search}%");
                });
            })
            ->when($request->customer, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('customers.virtual_customer_code', 'LIKE', "{$search}%")
                        ->orWhere('vend_prefixes.name', 'LIKE', "{$search}%")
                        ->orWhere('customers.name', 'LIKE', "%{$search}%");
                });
            })
            ->when($request->is_binded_customer, function ($query, $search) {
                if ($search === 'all') {
                    return;
                }
                if ($search === 'true') {
                    $query->whereNotNull('vr.customer_id');
                } else {
                    $query->whereNull('vr.customer_id');
                }
            })
            ->when($request->location_type_id, function ($query, $search) {
                if ($search !== 'all') {
                    $query->where('vr.location_type_id', $search);
                }
            })
            ->when($request->operators, function ($query, $search) {
                $ids = is_array($search) ? $search : [$search];
                $ids = array_filter($ids, fn($value) => $value !== null && $value !== '');
                if (!in_array('all', $ids, true) && !empty($ids)) {
                    $query->whereIn('vr.operator_id', $ids);
                }
            })
            ->when($request->vendContracts, function ($query, $search) {
                $ids = is_array($search) ? $search : [$search];
                $ids = array_filter($ids, fn($value) => $value !== null && $value !== '');
                if (!in_array('all', $ids, true) && !empty($ids)) {
                    $query->whereIn('vends.vend_contract_id', $ids);
                }
            })
            ->when($request->vendModels, function ($query, $search) {
                $ids = is_array($search) ? $search : [$search];
                $ids = array_filter($ids, fn($value) => $value !== null && $value !== '');
                if (!in_array('all', $ids, true) && !empty($ids)) {
                    $query->whereIn('vr.vend_model_id', $ids);
                }
            })
            ->when($request->vendPrefixes, function ($query, $search) {
                $ids = is_array($search) ? $search : [$search];
                if (in_array('single-ud', $ids, true)) {
                    $ids = array_unique(array_merge($ids, [56, 57, 58, 60, 63, 64, 76, 83]));
                    $ids = array_values(array_filter($ids, fn($value) => $value !== 'single-ud'));
                }
                $ids = array_filter($ids, fn($value) => $value !== null && $value !== '');
                if (!in_array('all', $ids, true) && !empty($ids)) {
                    $query->whereIn('vr.vend_prefix_id', $ids);
                }
            });
    }

    public function filterVendsDB($query, $request)
    {
        $request->merge([
            'is_door_open' => $request->is_door_open != null ? $request->is_door_open : 'all',
            'is_online' => $request->is_online != null ? $request->is_online : 'all',
            'is_sensor' => $request->is_sensor != null ? $request->is_sensor : 'all',
            // 'is_testing' => $request->is_testing != null ? $request->is_testing : 'all',
        ]);

        return $query->when($request->has('visited'), function ($query, $search) use ($request) {
            if ($request->visited == 'true') {
                $query->whereRaw('1 = 1');
            } else {
                $query->whereRaw('1 = 0');
            }
        })
            ->when($request->account_manager_name, function ($query, $search) {
                $query->where('customers.account_manager_json->name', 'LIKE', "{$search}%");
            })
            ->when($request->allTempHigherThan, function ($query, $search) {
                if (is_numeric($search)) {
                    $query->where('temp', '>=', $search * 10);
                }
            })
            ->when($request->cashless_terminal_id, function ($query, $search) {
                if ($search != 'all') {
                    $query->where('vends.cashless_terminal_id', $search);
                }
            })
            ->when($request->codes, function ($query, $search) {
                if (strpos($search, ',') !== false) {
                    $search = array_map('trim', explode(',', $search));
                    $query->whereIn('vends.code', $search);
                } else {
                    $query->where('vends.code', 'LIKE', "{$search}%");
                }
            })
            ->when($request->channel_codes, function ($query, $search) {
                if (strpos($search, ',') !== false) {
                    $search = array_map('trim', explode(',', $search));
                } else {
                    $search = [$search];
                }

                // $query->whereIn('vends.id', DB::table('vend_channels')->select('vend_id')->whereIn('code', $search)->where('vend_channels.is_active', true)->pluck('vend_id'));
                $query->whereExists(function ($sub) use ($search) {
                    $sub->select(DB::raw(1))
                        ->from('vend_channels')
                        ->whereRaw('vend_channels.vend_id = vends.id')
                        ->where('vend_channels.is_active', true)
                        ->whereIn('vend_channels.code', $search);
                });
            })
            ->when($request->delivery_platform_id, function ($query, $search) use ($request) {
                if ($search != 'all') {
                    if ($request->indexType == 'customers') {
                        $query->whereHas('vend.deliveryProductMappingVends.deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform', function ($query) use ($search) {
                            $query->where('id', $search);
                        });
                    } else {
                        $query->whereHas('deliveryProductMappingVends.deliveryProductMapping.deliveryPlatformOperator.deliveryPlatform', function ($query) use ($search) {
                            $query->where('id', $search);
                        });
                    }
                }
            })
            ->when($request->deviceType, function ($query, $search) {
                if ($search != 'all') {
                    $query->where('apk_ver_json->deviceType', $search);
                }
            })
            ->when($request->frequency_per_week_status, function ($query, $search) {
                if ($search != 'all') {
                    $query->where('customers.frequency_per_week_status', $search);
                }
            })
            ->when($request->serialNum, function ($query, $search) {
                $query->where('serial_num', 'LIKE', "%{$search}%");
            })
            ->when($request->coinLessThan, function ($query, $search) {
                if (is_numeric($search)) {
                    $query->where('parameter_json->CoinCnt', '<=', $search)->where('parameter_json->CoinCnt', '>', 0);
                }
            })
            ->when($request->customer, function ($query, $search) {
                // if(strpos($search, "-")) {
                //     $searchArray = explode("-", $search);
                //     $query->where('customers.virtual_customer_prefix', $searchArray[0])
                //         ->where('customers.virtual_customer_code', 'LIKE', "{$searchArray[1]}%");
                // }else {
                $query->where(function ($query) use ($search) {
                    // $query->where('customers.virtual_customer_prefix', 'LIKE', "{$search}%")
                    $query->where('customers.virtual_customer_code', 'LIKE', "{$search}%")
                        ->orWhere('vend_prefixes.name', 'LIKE', "{$search}%")
                        ->orWhere('customers.name', 'LIKE', "%{$search}%");
                });
                // }
            })
            ->when($request->has_customer, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'true') {
                        $query->whereNotNull('customers.id');
                    } else {
                        $query->whereNull('customers.id');
                    }
                }
            })
            ->when($request->lcd_monitor_id, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'undefined') {
                        $query->whereNull('vends.lcd_monitor_id');
                    } else {
                        $query->where('vends.lcd_monitor_id', $search);
                    }
                }
            })
            ->when($request->modem_type_id, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'undefined') {
                        $query->whereNull('vends.modem_type_id');
                    } else {
                        $query->where('vends.modem_type_id', $search);
                    }
                }
            })
            ->when($request->product_code, function ($query, $search) {
                $query->where('products.code', 'LIKE', "%{$search}%");
            })
            ->when($request->product_name, function ($query, $search) {
                $query->where('products.name', 'LIKE', "%{$search}%");
            })
            ->when($request->categories, function ($query, $search) {
                $query->whereIn('categories.id', $search);
            })
            ->when($request->categoryGroups, function ($query, $search) {
                $query->whereIn('category_groups.id', $search);
            })
            ->when($request->fan_rpm !== null && $request->fan_rpm !== '' && $request->fan_rpm !== 'all', function ($query) use ($request) {
                $search = $request->fan_rpm;
                if ($search == '0') {
                    $query->where('vends.is_fan_enabled', true)->where('vends.parameter_json->fan', 0);
                } else if ($search == '>0') {
                    $query->where('vends.is_fan_enabled', true)->where('vends.parameter_json->fan', '>', 0);
                } else if ($search == 'N/A') {
                    $query->where('vends.is_fan_enabled', false);
                } else if ($search == '--') {
                    $query->where('vends.is_fan_enabled', true)->where(function ($q) {
                        $q->whereNull('vends.parameter_json->fan');
                    });
                }
            })
            ->when($request->preferredDays, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    foreach ($search as $day) {
                        $subQuery->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(customers.preferred_visit_days_json, '$.\"$day\"')) = 'true'");
                    }
                });
            })
            ->when($request->simcard_id, function ($query, $search) {
                if ($search != 'all') {
                    $query->where('vends.simcard_id', $search);
                }
            })
            ->when($request->selling_price_type, function ($query, $search) {
                if ($search != 'all') {
                    $query->where('customers.selling_price_type', $search);
                }
            })
            ->when($request->status, function ($query, $search) {
                if ($search != 'all') {
                    switch ($search) {
                        case 'factory':
                            $query->where('vends.is_testing', true)->where('vends.is_active', false);
                            break;
                        case 'active':
                            $query->where('vends.is_active', true)->where('vends.is_testing', false);
                            break;
                        case 'inactive':
                            $query->where('vends.is_active', false)
                                  ->where('vends.is_testing', false)
                                  ->where('vends.is_disposed', false)
                                  ->where('vends.is_sold', false);
                            break;
                        case 'disposed':
                            $query->where('vends.is_disposed', true);
                            break;
                        case 'sold':
                            $query->where('vends.is_sold', true);
                            break;
                    }
                }
                // dd($query->toSql());
            })
            ->when($request->is_active, function ($query, $search) use ($request) {
                if ($request->indexType == 'customers') {
                    $columnName = $request->indexType ? $request->indexType . '.is_active' : 'vends.is_active';
                    if ($search != 'all') {
                        $query->where($columnName, filter_var($search, FILTER_VALIDATE_BOOLEAN));
                    }
                }
            })
            ->when($request->is_mqtt, function ($query, $search) {
                if ($search != 'all') {
                    $query->where('vends.is_mqtt', filter_var($search, FILTER_VALIDATE_BOOLEAN));
                }
            })
            ->when($request->is_mqtt_active, function ($query, $search) {
                if ($search != 'all') {
                    $query->where('vends.is_mqtt', true)->where('vends.is_mqtt_active', filter_var($search, FILTER_VALIDATE_BOOLEAN));
                }
            })
            ->when($request->is_door_open, function ($query, $search) {
                if ($search != 'all') {
                    $query->where('parameter_json->door', '=', $search);
                }
            })
            ->when($request->is_qr_code_active, function ($query, $search) {
                if ($search != 'all') {
                    $query->where('acb_vmc_pa_json->QRCode', filter_var($search, FILTER_VALIDATE_BOOLEAN) ? 1 : 0);
                }
            })
            ->when($request->tempHigherThan, function ($query, $search) {
                if (is_numeric($search)) {
                    $query->where('temp', '>=', $search * 10);
                }
            })
            ->when($request->t2HigherThan, function ($query, $search) {
                if (is_numeric($search)) {
                    $query->where('parameter_json->t2', '>=', $search * 10);
                }
            })
            ->when($request->tempDeltaHigherThan, function ($query, $search) {
                if (is_numeric($search)) {
                    $query
                        ->whereNotNull('parameter_json->t2')
                        ->where('parameter_json->t2', '!=', VendTemp::TEMPERATURE_ERROR)
                        ->whereRaw('temp - json_extract(parameter_json, "$.t2") > ?', [$search * 10]);
                }
            })
            ->when($request->tempLimitHigherThan, function ($query, $search) {
                if (is_numeric($search)) {
                    $query->where('acb_vmc_pa_json->TempLimit', '>=', $search);
                }
            })
            ->when($request->errors, function ($query, $search) {
                if (in_array('errors_only', $search)) {
                    $query->whereIn(
                        'vends.id',
                        DB::table('vend_channels')
                            ->select('vend_id')
                            ->where('vend_channels.is_active', true)
                            ->whereIn('vend_channels.id', DB::table('vend_channel_error_logs')
                                ->select('vend_channel_id')
                                ->where('is_error_cleared', false)
                                ->pluck('vend_channel_id'))
                            ->pluck('vend_id')
                    );
                } else {
                    $query->whereIn(
                        'vends.id',
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
            ->when($request->location_type_id, function ($query, $search) {
                if ($search != 'all') {
                    $query->where('location_type_id', $search);
                }
            })
            ->when($request->operator_id, function ($query, $search) use ($request) {
                if ($search != 'all') {
                    if ($request->indexType) {
                        $query->where($request->indexType . '.operator_id', $search);
                    } else {
                        $query->where('vends.operator_id', $search);
                    }
                }
            })
            ->when($request->operators, function ($query, $search) {
                $operators = Arr::wrap($search);
                if (!in_array('all', $operators)) {
                    $query->whereIn('vends.operator_id', $operators);
                }
            })
            ->when($request->is_online, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'true') {
                        $search = true;
                    } else {
                        $search = false;
                    }
                    $query->where('vends.is_online', $search);
                }
            })
            ->when($request->is_sensor, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'true') {
                        $query->whereIn('parameter_json->Sensor', ['1', '3', '5', '7', '9']);
                    } else {
                        $query->whereIn('parameter_json->Sensor', ['0', '2', '4', '6', '8', '10']);
                    }
                }
            })
            ->when($request->lastVisitedGreaterThan, function ($query, $search) {
                $query->whereDate('customers.last_invoice_date', '<=', Carbon::now()->subDays($search)->toDateString());
            })
            ->when($request->balanceStockLessThan, function ($query, $search) {
                $query->where('balance_percent', '<=', $search);
            })
            ->when($request->remainingSkuLessThan, function ($query, $search) {
                $query->where('out_of_stock_sku_percent', '>=', (100 - $search));
            })
            ->when($request->apk_ver, function ($query, $search) {
                $query->where('apk_ver_json->apkver', 'LIKE', "{$search}%");
            })
            ->when($request->firmware_ver, function ($query, $search) {
                $search = hexdec($search);
                $query->where('parameter_json->Ver', 'LIKE', "{$search}%");
            })
            ->when($request->next_planned_driver, function ($query, $search) {
                if ($search != 'all') {
                    $query->whereHas('vend.nextOpsJobItem.opsJob', function ($query) use ($search) {
                        $query->where('delivered_by', $search);
                    });
                }
            })
            ->when($request->next_planned_date, function ($query, $search) {
                $query->whereHas('vend.nextOpsJobItem.opsJob', function ($query) use ($search) {
                    $query->whereDate('date', $search);
                });
            })
            ->when($request->vendRecordsThirtyDaysAmountAverageLessThan, function ($query, $search) {
                $query->where('virtual_vend_records_thirty_days_amount_average', '<=', $search * 100);
            })
            ->when($request->vend_config_id, function ($query, $search) {
                if ($search != 'all') {
                    $query->where('vends.vend_config_id', $search);
                }
            })
            ->when($request->vendConfigs, function ($query, $search) {
                if (!in_array('all', $search)) {
                    $query->whereIn('vends.vend_config_id', $search);
                }
            })
            ->when($request->vendContracts, function ($query, $search) {
                if (!in_array('all', $search)) {
                    $query->whereIn('vend_contract_id', $search);
                }
            })
            ->when($request->vend_model_id, function ($query, $search) {
                if ($search != 'all') {
                    $query->where('vends.vend_model_id', $search);
                }
            })
            // ->when($request->vend_prefix_id, function($query, $search) {
            //     if($search != 'all') {
            //         $query->where('vends.vend_prefix_id', $search);
            //     }
            // })
            ->when($request->vendModels, function ($query, $search) {
                if (!in_array('all', $search)) {
                    $query->whereIn('vends.vend_model_id', $search);
                }
            })
            ->when($request->vendPrefixes, function ($query, $search) {
                if (!in_array('all', $search)) {
                    if (in_array('single-ud', $search)) {
                        $search = array_unique(array_merge($search, [56, 57, 58, 60, 63, 64, 76, 83]));
                        unset($search[array_search('single-ud', $search)]);
                    }
                    $query->whereIn('vends.vend_prefix_id', $search);
                }
            })
            ->when($request->zones, function ($query, $search) {
                if (!in_array('all', $search)) {
                    $query->whereIn('zone_id', $search);
                }
            })
            ->when($request->sortKey, function ($query, $search) use ($request) {
                if (strpos($search, '->')) {
                    $inputSearch = explode("->", $search);
                    if (
                        $search === 'totals_json->three_days_error_rate' or
                        $search === 'totals_json->seven_days_error_rate' or
                        $search === 'totals_json->vend_records_amount_average_day' or
                        $search === 'thirty_days_stock_in_delta_percent'
                    ) {
                        $query->orderByRaw('(CAST(json_unquote(json_extract(`' . $inputSearch[0] . '`, "$.' . $inputSearch[1] . '")) AS DECIMAL(10,2))) ' . (filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'));
                    } else {
                        $query->orderByRaw('LENGTH(json_unquote(json_extract(`' . $inputSearch[0] . '`, "$.' . $inputSearch[1] . '")))' . (filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'));
                    }

                    $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
                } else {
                    if ($search == 'balance_percent' or $search == 'out_of_stock_sku_percent') {
                        $excludedModelIds = \App\Models\VendModel::where('is_sortable', false)->pluck('id')->toArray();

                        if (!empty($excludedModelIds)) {
                            $excludedModelIdsString = implode(',', $excludedModelIds);
                            $query->orderByRaw('vends.vend_model_id IN (' . $excludedModelIdsString . ') ASC');
                        }

                        $query->orderByRaw('ISNULL(' . $search . '), ' . $search . ' ' . (filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'));
                    } elseif ($search == 'temp_diff') {
                        $query->orderByRaw('(temp - CAST(json_unquote(json_extract(parameter_json, "$.t2")) AS DECIMAL(10,2))) ' . (filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'));
                    } else {
                        $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
                    }
                }
                if ($search === 'vends.is_online') {
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

        $query = $query->when($request->codes, function ($query, $search) {
            if (strpos($search, ',') !== false) {
                $search = array_map('trim', explode(',', $search));
                $query->whereIn('vends.code', $search);
            } else {
                $query->where('vends.code', 'LIKE', "%{$search}%");
            }
        })
            ->when($request->channel_codes, function ($query, $search) {
                if (strpos($search, ',') !== false) {
                    $search = array_map('trim', explode(',', $search));
                } else {
                    $search = [$search];
                }
                $query->whereIn('vend_channels.code', $search);
            })
            ->when($request->serialNum, function ($query, $search) {
                $query->where('vends.serial_num', 'LIKE', "%{$search}%");
            })
            ->when($request->customer_code, function ($query, $search) {
                $query->where('customers.code', 'LIKE', "%{$search}%");
            })
            ->when($request->customer_name, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('customers.name', 'LIKE', "%{$search}%")
                        ->orWhere('vends.name', 'LIKE', "%{$search}%");
                });
            })
            ->when($request->categories, function ($query, $search) {
                $query->whereIn('categories.id', $search);
            })
            ->when($request->categoryGroups, function ($query, $search) {
                $query->whereIn('category_groups.id', $search);
            })
            ->when($request->fan_rpm !== null && $request->fan_rpm !== '' && $request->fan_rpm !== 'all', function ($query) use ($request) {
                $search = $request->fan_rpm;
                if ($search == '0') {
                    $query->where('vends.is_fan_enabled', true)->where('vends.parameter_json->fan', 0);
                } else if ($search == '>0') {
                    $query->where('vends.is_fan_enabled', true)->where('vends.parameter_json->fan', '>', 0);
                } else if ($search == 'N/A') {
                    $query->where('vends.is_fan_enabled', false);
                } else if ($search == '--') {
                    $query->where('vends.is_fan_enabled', true)->where(function ($q) {
                        $q->whereNull('vends.parameter_json->fan');
                    });
                }
            })
            ->when($request->is_active, function ($query, $search) use ($request) {
                if ($search != 'all') {
                    $query->where('customers.is_active', filter_var($search, FILTER_VALIDATE_BOOLEAN));
                }
            })
            ->when($isDoorOpen, function ($query, $search) {
                if ($search != 'all') {
                    $query->where('parameter_json->door', '=', $search);
                }
            })
            ->when($request->is_binded_customer, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'true') {
                        $query->whereNotNull('customers.id');
                    } else {
                        $query->whereNull('customers.id');
                    }
                }
            })
            ->when($request->tempHigherThan, function ($query, $search) {
                if (is_numeric($search)) {
                    $query->where('temp', '>=', $search * 10);
                }
            })
            ->when($request->tempDeltaHigherThan, function ($query, $search) {
                if (is_numeric($search)) {
                    $query
                        ->whereNotNull('parameter_json->t2')
                        ->where('parameter_json->t2', '!=', VendTemp::TEMPERATURE_ERROR)
                        ->whereRaw('temp - json_extract(parameter_json, "$.t2") > ?', [$search * 10]);
                }
            })
            ->when($request->errors, function ($query, $search) {
                if (in_array('errors_only', $search)) {
                    $query
                        ->whereIn('vends.id', DB::table('vend_channels')
                            ->select('vend_id')
                            ->whereIn('id', DB::table('vend_channel_error_logs')
                                ->select('vend_channel_id')
                                ->where('is_error_cleared', false)
                                ->pluck('vend_channel_id')));
                } else {
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
            ->when($request->location_type_id, function ($query, $search) {
                if ($search != 'all') {
                    $query->where('location_type_id', $search);
                }
            })
            ->when($request->operator_id, function ($query, $search) {
                if ($search != 'all') {
                    $query->where('vends.operator_id', $search);
                }
            })
            ->when($isOnline, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'true') {
                        $search = true;
                    } else {
                        $search = false;
                    }
                    $query->where('vends.is_online', $search);
                }
            })
            ->when($isSensor, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'true') {
                        $query->whereIn('parameter_json->Sensor', ['1', '3', '5', '7', '9']);
                    } else {
                        $query->whereIn('parameter_json->Sensor', ['0', '2', '4', '6', '8', '10']);
                    }
                }
            })
            ->when($request->lastVisitedGreaterThan, function ($query, $search) {
                $query->whereDate('customers.last_invoice_date', '<=', Carbon::now()->subDays($search)->toDateString());
            })
            ->when($request->sortKey, function ($query, $search) use ($request) {
                if (strpos($search, '->')) {
                    $inputSearch = explode("->", $search);
                    $query->orderByRaw('LENGTH(json_unquote(json_extract(`' . $inputSearch[0] . '`, "$.' . $inputSearch[1] . '")))' . (filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc'))
                        ->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
                } else {
                    $query->orderBy($search, filter_var($request->sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
                }

                if ($search === 'vends.is_online') {
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

        $query = $query->when($request->has('visited'), function ($query, $search) use ($request) {
            if ($request->visited == 'true') {
                $query->whereRaw('1 = 1');
            } else {
                $query->whereRaw('1 = 0');
            }
        })
            ->when($request->codes, function ($query, $search) {
                if (strpos($search, ',') !== false) {
                    $search = explode(',', $search);
                    $query->whereIn('vends.code', $search);
                } else {
                    $query->where('vends.code', 'LIKE', "%{$search}%");
                }
            })
            ->when($request->channel_codes, function ($query, $search) {
                if (strpos($search, ',') !== false) {
                    $search = explode(',', $search);
                } else {
                    $search = [$search];
                }
                $query->whereIn('vend_channel_code', $search);
            })
            ->when($request->errors, function ($query, $search) {
                if (in_array('errors_only', $search)) {
                    $query
                        ->whereIn('vend_channel_errors.id', DB::table('vend_channel_error_logs')
                            ->select('vend_channel_error_id')
                            ->where('is_error_cleared', false)
                            ->pluck('vend_channel_error_id'));
                } else {
                    $query
                        ->whereIn('vend_channel_errors.id', $search);
                }
            })
            ->when($request->is_binded_customer, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'true') {
                        $query->whereNotNull('customers.id');
                    } else {
                        $query->whereNull('customers.id');
                    }
                }
            })
            ->when($isPaymentReceived, function ($query, $search) {
                if ($search != 'all') {
                    if ($search == 'true') {
                        $query->where('is_payment_received', true);
                    } else {
                        $query->where('is_payment_received', false);
                    }
                }
            })
            ->when($request->paymentMethod, function ($query, $search) {
                $query->where('payment_method_id', $search);
            })
            ->when($request->preferredDays, function ($query, $search) {
                $query->where(function ($subQuery) use ($search) {
                    foreach ($search as $day) {
                        $subQuery->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(customers.preferred_visit_days_json, '$.\"$day\"')) = 'true'");
                    }
                });
            })
            ->when($request->categories, function ($query, $search) {
                $query->whereIn('categories.id', $search);
            })
            ->when($request->categoryGroups, function ($query, $search) {
                $query->whereIn('category_groups.id', $search);
            })
            ->when($request->customer_code, function ($query, $search) {
                $query->where('customers.code', 'LIKE', "%{$search}%");
            })
            ->when($request->customer_name, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('customers.name', 'LIKE', "%{$search}%")
                        ->orWhere('vends.name', 'LIKE', "%{$search}%");
                });
            })
            ->when($request->product_code, function ($query, $search) {
                $query->where('products.code', 'LIKE', "%{$search}%");
            })
            ->when($request->product_name, function ($query, $search) {
                $query->where('products.name', 'LIKE', "%{$search}%");
            })
            ->when($request->location_type_id, function ($query, $search) {
                if ($search != 'all') {
                    $query->where('location_type_id', $search);
                }
            })
            ->when($request->operator_id, function ($query, $search) {
                if ($search != 'all') {
                    $query->where('operator_id', $search);
                }
            })
            ->when($request->date_from, function ($query, $search) {
                $query->where('vend_transactions.created_at', '>=', Carbon::parse($search)->startOfDay());
            })
            ->when($request->date_to, function ($query, $search) {
                $query->where('vend_transactions.created_at', '<=', Carbon::parse($search)->endOfDay());
            })
            ->when($sortKey, function ($query, $search) use ($sortBy) {
                $query->orderBy($search, filter_var($sortBy, FILTER_VALIDATE_BOOLEAN) ? 'asc' : 'desc');
            });


        return $query;
    }

}
