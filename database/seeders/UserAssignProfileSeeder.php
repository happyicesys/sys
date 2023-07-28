<?php

namespace Database\Seeders;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserAssignProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $profile = Profile::firstOrCreate([
            'name' => 'HAPPY ICE PTE LTD',
            'alias' => 'HI',
            'uen' => '201302530W',
            'base_currency_id' => 1,
        ]);

        if($profile) {
            $users = User::all();

            foreach($users as $user) {
                $user->profile_id = $profile->id;
                $user->save();
            }
        }
    }
}
