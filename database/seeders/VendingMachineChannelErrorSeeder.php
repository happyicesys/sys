<?php

namespace Database\Seeders;

use App\Models\VendingMachineChannelError;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class VendingMachineChannelErrorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        VendingMachineChannelError::create([
            'code' => 0,
            'desc' => 'No Malfunction (0)',
        ]);

        VendingMachineChannelError::create([
            'code' => 1,
            'desc' => 'Index Error (1)',
        ]);

        VendingMachineChannelError::create([
            'code' => 4,
            'desc' => 'Open circuit, motor not detected (4)',
        ]);

        VendingMachineChannelError::create([
            'code' => 5,
            'desc' => 'Current overlimit (5)',
        ]);

        VendingMachineChannelError::create([
            'code' => 6,
            'desc' => 'Microswitch pressed over time (6)',
        ]);

        VendingMachineChannelError::create([
            'code' => 7,
            'desc' => 'Sensor error (7)',
        ]);

        VendingMachineChannelError::create([
            'code' => 42,
            'desc' => 'Motor board communication error (42)',
        ]);

        VendingMachineChannelError::create([
            'code' => 45,
            'desc' => 'Motor board is not connected or does not start (45)',
        ]);

        VendingMachineChannelError::create([
            'code' => 3,
            'desc' => 'Microswith not detected (3)',
        ]);

        VendingMachineChannelError::create([
            'code' => 77,
            'desc' => 'Drop sensor error (77)',
        ]);

        VendingMachineChannelError::create([
            'code' => 9,
            'desc' => 'Sensor error and disabled (9)',
        ]);
    }
}
