<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UmamiTokenSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = User::where('name', 'Umami')->first();

        if($user) {
            $token = $user->createToken($user->name.'-access');
            $user->access_token = $token->plainTextToken;
            $user->save();
        }
    }
}
