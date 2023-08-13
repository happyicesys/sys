<?php

namespace Database\Seeders;

use App\Models\Month;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MonthSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Month::create([
            'name' => 'January',
            'number' => 1,
            'short_name' => 'Jan',
        ]);
        Month::create([
            'name' => 'February',
            'number' => 2,
            'short_name' => 'Feb',
        ]);
        Month::create([
            'name' => 'March',
            'number' => 3,
            'short_name' => 'Mar',
        ]);
        Month::create([
            'name' => 'April',
            'number' => 4,
            'short_name' => 'Apr',
        ]);
        Month::create([
            'name' => 'May',
            'number' => 5,
            'short_name' => 'May',
        ]);
        Month::create([
            'name' => 'June',
            'number' => 6,
            'short_name' => 'Jun',
        ]);
        Month::create([
            'name' => 'July',
            'number' => 7,
            'short_name' => 'Jul',
        ]);
        Month::create([
            'name' => 'August',
            'number' => 8,
            'short_name' => 'Aug',
        ]);
        Month::create([
            'name' => 'September',
            'number' => 9,
            'short_name' => 'Sep',
        ]);
        Month::create([
            'name' => 'October',
            'number' => 10,
            'short_name' => 'Oct',
        ]);
        Month::create([
            'name' => 'November',
            'number' => 11,
            'short_name' => 'Nov',
        ]);
        Month::create([
            'name' => 'December',
            'number' => 12,
            'short_name' => 'Dec',
        ]);

    }
}
