<?php

namespace Database\Seeders;

use App\Models\VMChannelError;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VMChannelErrorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        VMChannelError::create([
            'code' => 0,
            'desc' => 'No Malfunction (0)',
        ]);

        VMChannelError::create([
            'code' => 1,
            'desc' => 'Index Error (1)',
        ]);

        VMChannelError::create([
            'code' => 4,
            'desc' => 'Open circuit, motor not detected (4)',
        ]);

        VMChannelError::create([
            'code' => 5,
            'desc' => 'Current overlimit (5)',
        ]);

        VMChannelError::create([
            'code' => 6,
            'desc' => 'Microswitch pressed over time (6)',
        ]);

        VMChannelError::create([
            'code' => 7,
            'desc' => 'Sensor error (7)',
        ]);

        VMChannelError::create([
            'code' => 42,
            'desc' => 'Motor board communication error (42)',
        ]);

        VMChannelError::create([
            'code' => 45,
            'desc' => 'Motor board is not connected or does not start (45)',
        ]);

        VMChannelError::create([
            'code' => 3,
            'desc' => 'Microswith not detected (3)',
        ]);

        VMChannelError::create([
            'code' => 77,
            'desc' => 'Drop sensor error (77)',
        ]);

        VMChannelError::create([
            'code' => 9,
            'desc' => 'Sensor error and disabled (9)',
        ]);
    }
}
