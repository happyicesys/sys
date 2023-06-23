<?php

namespace App\Traits;
use Carbon\Carbon;

trait HasMonthOption{

  public function getMonthOption()
  {
    $months = [
      '1' => 'January',
      '2' => 'February',
      '3' => 'March',
      '4' => 'April',
      '5' => 'May',
      '6' => 'June',
      '7' => 'July',
      '8' => 'August',
      '9' => 'September',
      '10' => 'October',
      '11' => 'November',
      '12' => 'December',
    ];

    $today = Carbon::today();
    $past = Carbon::today()->subYears(2);
    $diffInMonths = $today->copy()->diffInMonths($past);
    $monthOption = [];
    for($i = 0; $i <= $diffInMonths; $i++) {
      $monthOption [] = [
        'id' => $past->copy()->addMonths($i)->format('Y-m'),
        'name' => $months[$past->copy()->addMonths($i)->format('n')] . ' ' . $past->copy()->addMonths($i)->format('Y'),
      ];
      // $monthOption[$past->copy()->addMonths($i)->format('Y-m')] = $months[$past->copy()->addMonths($i)->format('n')] . ' ' . $past->copy()->addMonths($i)->format('Y');
    }

    return array_reverse($monthOption);
  }

  public function getYearOptions()
  {
    $today = Carbon::today();
    $nextYear = $today->copy()->addYear();
    $yearOptions = [];
      $yearOptions[] = [
        'id' => $nextYear->year,
        'name' => $nextYear->year,
      ];
    for($i = 0; $i < 5; $i++) {
      $yearOptions [] = [
        'id' => $today->copy()->subYears($i)->year,
        'name' => $today->copy()->subYears($i)->year,
      ];
    }

    return $yearOptions;
  }

  public function getReportDateOptions()
  {
    $date = Carbon::today()->setTimezone($this->getUserTimezone());
    $processedOptions = [];
    $options = [
      Carbon::today()->toDateString().','.Carbon::today()->toDateString() => 'Today',
      Carbon::yesterday()->toDateString().','.Carbon::yesterday()->toDateString() => 'Yesterday',
      Carbon::today()->startOfWeek()->toDateString().','.Carbon::today()->endOfWeek()->toDateString() => 'This Week',
      Carbon::today()->subWeek()->startOfWeek()->toDateString().','.Carbon::today()->subWeek()->endOfWeek()->toDateString() => 'Last Week',
      Carbon::today()->subWeeks(2)->startOfWeek()->toDateString().','.Carbon::today()->subWeeks(2)->endOfWeek()->toDateString() => 'Last Two Weeks',
      Carbon::today()->startOfMonth()->toDateString().','.Carbon::today()->endOfMonth()->toDateString() => 'This Month',
      Carbon::today()->subMonth()->startOfMonth()->toDateString().','.Carbon::today()->subMonth()->endOfMonth()->toDateString() => 'Last Month',
      Carbon::today()->subMonths(2)->startOfMonth()->toDateString().','.Carbon::today()->subMonths(2)->endOfMonth()->toDateString() => 'Last 2 Months',
      Carbon::today()->startOfYear()->toDateString().','.Carbon::today()->endOfYear()->toDateString() => 'This Year',
      Carbon::today()->subYear()->startOfYear()->toDateString().','.Carbon::today()->subYear()->endOfYear()->toDateString() => 'Last Year',
    ];

    foreach($options as $optionIndex => $option) {
      $processedOptions [] = [
        'id' => $optionIndex,
        'name' => $option,
      ];
    }

    return $processedOptions;
  }
}