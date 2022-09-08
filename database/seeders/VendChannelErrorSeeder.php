<?php

namespace Database\Seeders;

use App\Models\VendChannelError;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VendChannelErrorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        VendChannelError::create([
            'code' => 0,
            'desc' => 'No Malfunction (0)',
        ]);

        VendChannelError::create([
            'code' => 1,
            'desc' => 'Index Error (1)',
        ]);

        VendChannelError::create([
            'code' => 4,
            'desc' => 'Open circuit, motor not detected (4)',
        ]);

        VendChannelError::create([
            'code' => 5,
            'desc' => 'Current overlimit (5)',
        ]);

        VendChannelError::create([
            'code' => 6,
            'desc' => 'Microswitch pressed over time (6)',
        ]);

        VendChannelError::create([
            'code' => 7,
            'desc' => 'Sensor error (7)',
        ]);

        VendChannelError::create([
            'code' => 42,
            'desc' => 'Motor board communication error (42)',
        ]);

        VendChannelError::create([
            'code' => 45,
            'desc' => 'Motor board is not connected or does not start (45)',
        ]);

        VendChannelError::create([
            'code' => 3,
            'desc' => 'Microswith not detected (3)',
        ]);

        VendChannelError::create([
            'code' => 77,
            'desc' => 'Drop sensor error (77)',
        ]);

        VendChannelError::create([
            'code' => 9,
            'desc' => 'Sensor error and disabled (9)',
        ]);
    }
}
