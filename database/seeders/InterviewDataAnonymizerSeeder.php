<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Transaction;
use App\Models\Vend;
use App\Models\VendRecord;
use App\Models\VendTransaction;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;
use Illuminate\Support\Facades\DB;

class InterviewDataAnonymizerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $faker = Faker::create();

        $this->command->info('Anonymizing Customers...');
        Customer::query()->chunk(100, function ($customers) use ($faker) {
            foreach ($customers as $customer) {
                $customer->update([
                    'name' => $faker->company,
                    'code' => $faker->lexify('???') . '-' . $faker->unique()->randomNumber(5),
                    'person_json' => null,
                    'ops_note' => $faker->sentence,
                ]);
            }
        });

        $this->command->info('Anonymizing Addresses...');
        Address::query()->chunk(100, function ($addresses) use ($faker) {
            foreach ($addresses as $address) {
                $address->update([
                    'name' => \Illuminate\Support\Str::limit($faker->name, 190),
                    'block_num' => \Illuminate\Support\Str::limit($faker->buildingNumber, 100),
                    'building' => \Illuminate\Support\Str::limit($faker->streetName . ' Building', 190),
                    'city' => \Illuminate\Support\Str::limit($faker->city, 100),
                    'company_name' => \Illuminate\Support\Str::limit($faker->company, 190),
                    'full_address' => \Illuminate\Support\Str::limit($faker->address, 190),
                    'postcode' => \Illuminate\Support\Str::limit($faker->postcode, 20),
                    'street_name' => \Illuminate\Support\Str::limit($faker->streetName, 100),
                    'unit_num' => \Illuminate\Support\Str::limit($faker->numerify('#-##'), 50),
                ]);
            }
        });

        $this->command->info('Anonymizing Contacts...');
        Contact::query()->chunk(100, function ($contacts) use ($faker) {
            foreach ($contacts as $contact) {
                $contact->update([
                    'name' => $faker->name,
                    'email' => $faker->unique()->safeEmail,
                    'phone_num' => $faker->phoneNumber,
                    'alt_phone_num' => $faker->phoneNumber,
                ]);
            }
        });

        $this->command->info('Anonymizing Transactions...');
        if (\Illuminate\Support\Facades\Schema::hasTable('transactions')) {
            DB::table('transactions')->orderBy('id')->chunk(100, function ($transactions) use ($faker) {
                foreach ($transactions as $transaction) {
                    DB::table('transactions')->where('id', $transaction->id)->update([
                        'code' => 'TXN' . str_pad($transaction->id, 8, '0', STR_PAD_LEFT),
                        'po_num' => 'PO' . str_pad($transaction->id, 6, '0', STR_PAD_LEFT),
                        'inner_remarks' => $faker->sentence,
                        'remarks' => $faker->sentence,
                    ]);
                }
            });
        }

        $this->command->info('Anonymizing Vends...');
        try {
            Vend::query()->chunk(100, function ($vends) use ($faker) {
                foreach ($vends as $vend) {
                    $vend->update([
                        'code' => 1000 + $vend->id,
                        'name' => 'Machine ' . (1000 + $vend->id),
                        'label_name' => 'Label ' . $faker->word,
                        'serial_num' => 5000 + $vend->id,
                        'private_key' => $faker->sha256,
                        'last_ip_address' => $faker->ipv4,
                    ]);
                }
            });
        } catch (\Exception $e) {
            $this->command->warn('Failed to anonymize vends: ' . $e->getMessage());
        }

        $this->command->info('Anonymizing VendTransactions (Randomizing amounts)...');
        if (\Illuminate\Support\Facades\Schema::hasTable('vend_transactions')) {
            $this->command->info('Using bulk update for VendTransactions (4.4M records)...');
            DB::table('vend_transactions')->update([
                'order_id' => DB::raw("CONCAT('ORD', id)"),
                'amount' => DB::raw("FLOOR(amount * (0.8 + RAND() * 0.4))"), // Randomize amount +/- 20%
                'revenue' => DB::raw("FLOOR(revenue * (0.8 + RAND() * 0.4))"),
                'gross_profit' => DB::raw("FLOOR(gross_profit * (0.8 + RAND() * 0.4))"),
                'unit_cost' => DB::raw("FLOOR(unit_cost * (0.8 + RAND() * 0.4))"),
                'meta_json' => null,
                'vend_transaction_json' => null,
                'items_json' => '[]',
                'label_json' => '[]',
            ]);
        }

        $this->command->info('Anonymizing VendRecords (Randomizing totals)...');
        if (\Illuminate\Support\Facades\Schema::hasTable('vend_records')) {
            $this->command->info('Using bulk update for VendRecords (565k records)...');
            DB::table('vend_records')->update([
                'vend_code' => DB::raw("1000 + vend_id"),
                'total_amount' => DB::raw("FLOOR(total_amount * (0.8 + RAND() * 0.4))"),
                'failure_amount' => DB::raw("FLOOR(failure_amount * (0.8 + RAND() * 0.4))"),
                'revenue' => DB::raw("FLOOR(revenue * (0.8 + RAND() * 0.4))"),
                'gross_profit' => DB::raw("FLOOR(gross_profit * (0.8 + RAND() * 0.4))"),
                'total_count' => DB::raw("FLOOR(total_count * (0.9 + RAND() * 0.2))"), // Randomize counts slightly too
                'all_total_count' => DB::raw("FLOOR(all_total_count * (0.9 + RAND() * 0.2))"),
            ]);
        }

        $this->command->info('Anonymizing Users (Skipping SuperAdmin)...');
        if (\Illuminate\Support\Facades\Schema::hasTable('users')) {
            // Restore SuperAdmin just in case it was already anonymized in previous run
            $superAdmin = \App\Models\User::find(1);
            if ($superAdmin) {
                $superAdmin->update([
                    'name' => 'SuperAdmin',
                    'email' => 'leehongjie91@gmail.com',
                    'username' => 'leehongjie91',
                    'password' => 'password',
                ]);
            }

            \App\Models\User::where('id', '!=', 1)->chunk(100, function ($users) use ($faker) {
                foreach ($users as $user) {
                    $user->update([
                        'name' => $faker->name,
                        'email' => $faker->unique()->safeEmail,
                        'phone_number' => $faker->phoneNumber,
                        'username' => $faker->unique()->userName,
                        'password' => 'password',
                    ]);
                }
            });
        }

        $this->command->info('Data anonymization completed.');
    }
}
