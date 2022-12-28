<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // \App\Models\User::factory()->create([
        //     'name' => 'Brian',
        //     'email' => 'leehongjie91@gmail.com',
        //     'password' => 'brian1234',
        // ]);

        // \App\Models\User::factory()->create([
        //     'name' => 'Daniel',
        //     'email' => 'daniel.ma@happyice.com.sg',
        //     'password' => 'daniel1234',
        // ]);

        // \App\Models\User::factory()->create([
        //     'name' => 'Kent',
        //     'email' => 'kent@happyice.com.sg',
        //     'password' => 'kent1234',
        // ]);

        // \App\Models\User::factory()->create([
        //     'name' => 'Stephen',
        //     'email' => 'stephen@happyice.com.sg',
        //     'password' => 'stephen1234',
        // ]);


        $this->call([
            // PaymentMethodSeeder::class,
            // VendChannelErrorSeeder::class,
            // CountrySeeder::class,
            // PaymentTermSeeder::class,
            // TaxSeeder::class,

            // CashlessProviderSeeder::class,
            // TelcoSeeder::class,
            // VendTypeSeeder::class,
            // UomSeeder::class,
            // UserAssignProfileSeeder::class,
            // RoleSeeder::class,
            // ThaiCountrySeeder::class,
            // OperatorSeeder::class,
        ]);
    }
}
