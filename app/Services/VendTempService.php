<?php

namespace App\Services;

use App\Models\Vend;
use App\Models\VendTemp;
use App\Jobs\SendVendTempAlert;
use Carbon\Carbon;

class VendTempService
{

  const TEMP_DIVISOR = 10;
  protected $vend;

  public function __construct(Vend $vend)
  {
    $this->vend = $vend;
  }

  public function compareLast($type)
  {
    $tempArr = $this
                ->vend
                ->vendTemps()
                ->where('type', $type)
                ->latest()
                ->take(2)
                ->get();

    if ($tempArr->count() == 2) {
      $diff = $tempArr[0]->value - $tempArr[1]->value;
      if ($diff > 0) {
        // dd($diff);
        return true;
      }
    }
    return false;
  }

  public function getLatestVendTemp($type)
  {
    return $this
            ->vend
            ->vendTemps()
            ->where('type', $type)
            ->latest()
            ->first();
  }

  public function getTypeVariance($t1, $t2)
  {
    // $temp1 = $this->getLatestVendTemp($type1);

    // $temp2 = $this->getLatestVendTemp($type2);

    return ($t1 - $t2)/ self::TEMP_DIVISOR;
  }

  public function initVendTempAlertData()
  {
    if($this->vend->vend_temp_alert_json == null) {
      Vend::where('id', $this->vend->id)->update([
        'vend_temp_alert_json' => VendTemp::DEFAULT_ALERTS
      ]);
    }
  }

  public function runVendTempAlert($t1, $t2)
  {
    $this->initVendTempAlertData();

    $variance = $this->getTypeVariance($t1, $t2);

    foreach(VendTemp::DEFAULT_ALERTS as $name => $valueArr) {
      $vend = $this->vend->fresh();

      $dataArr = [
        'column_name' => 'vend_temp_alert_json->'.$name,
        'current_is_triggered' => false,
        'desc' => $valueArr['desc'],
        'is_alert_action' => false,
        'name' => $name,
        'previous_is_triggered' => $vend->vend_temp_alert_json[$name]['is_triggered'],
        't1' => $t1/ self::TEMP_DIVISOR,
        't2' => $t2/ self::TEMP_DIVISOR,
        'value' => $valueArr['value'],
        'variance' => $variance,
      ];

      if ($dataArr['variance'] >= $dataArr['value'] && !$dataArr['previous_is_triggered']) {
        $dataArr['current_is_triggered'] = true;
        $dataArr['is_alert_action'] = true;
      } else if ($dataArr['variance'] < $dataArr['value']) {
          $dataArr['current_is_triggered'] = false;
          $dataArr['is_alert_action'] = false;
      }


      Vend::where('id', $this->vend->id)->update([
        $dataArr['column_name'] . '->is_triggered' => $dataArr['current_is_triggered'],
        $dataArr['column_name'] . '->current_t1' => $dataArr['t1'],
        $dataArr['column_name'] . '->current_t2' => $dataArr['t2'],
        $dataArr['column_name'] . '->current_variance' => $dataArr['variance'],
      ]);

      if($dataArr['is_alert_action']) {
        // SendVendTempAlert::dispatchSync($this->vend, $dataArr);
        // SendVendTempAlert::dispatch($this->vend, $dataArr)->onQueue('default');
      }
    }
  }
}