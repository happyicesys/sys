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
        $profile = Profile::where('name', 'HAPPY ICE PTE LTD')->first();

        if($profile) {
            $users = User::all();

            foreach($users as $user) {
                $user->profile_id = $profile->id;
                $user->save();
            }
        }
    }
}
