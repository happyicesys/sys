<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
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
        User::create([
            'name' => 'Brian',
            'username' => 'brian',
            'email' => 'leehongjie91@gmail.com',
            'password' => 'brian1234',
        ]);

        User::create([
            'name' => 'Daniel',
            'username' => 'daniel',
            'email' => 'daniel.ma@happyice.com.sg',
            'password' => 'daniel1234',
        ]);

        User::create([
            'name' => 'Kent',
            'username' => 'kent',
            'email' => 'kent@happyice.com.sg',
            'password' => 'kent1234',
        ]);

        User::create([
            'name' => 'Stephen',
            'username' => 'stephen',
            'email' => 'stephen@happyice.com.sg',
            'password' => 'stephen1234',
        ]);


        $this->call([
            PaymentMethodSeeder::class,
            VendChannelErrorSeeder::class,
            CountrySeeder::class,
            // PaymentTermSeeder::class,
            // TaxSeeder::class,

            // CashlessProviderSeeder::class,
            // TelcoSeeder::class,
            // VendTypeSeeder::class,
            UomSeeder::class,
            UserAssignProfileSeeder::class,
            RoleSeeder::class,
            // ThaiCountrySeeder::class,
            OperatorSeeder::class,
            OperaterRoleSeeder::class,
            OperatorPaymentGatewaySeeder::class,
            // UmamiTokenSeeder::class,
            PaymentGatewayUpdateSeeder::class,
            IndoMidtransSeeder::class,
            OperatorUserSeeder::class,
            PermissionSeeder::class,
            // OmisePaymentGatewaySeeder::class,
            ExportPermissionSeeder::class,
            // MsiaThaiPaymentMethodSeeder::class,
            VendCriteriaSeeder::class,
            // VendBindingFromToSeeder::class,
            ObserverRoleSeeder::class,
            InitVendBeginTerminationDateSeeder::class,
        ]);
    }
}
