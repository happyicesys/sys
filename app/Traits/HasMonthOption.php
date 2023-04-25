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
}