<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DcvendAuthSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::where('username', 'dcvend')->first();

        if($user) {
            $token = $user->createToken('dcvend_token')->accessToken;

            echo($token);
        }
    }
}
